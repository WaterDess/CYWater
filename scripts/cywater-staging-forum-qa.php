<?php
/**
 * Self-cleaning Forum acceptance probe for the real Hostinger staging stack.
 *
 * Run with: wp eval-file scripts/cywater-staging-forum-qa.php
 */

if ( 'staging' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Forum QA may run only when WP_ENVIRONMENT_TYPE is staging.' );
}

foreach ( array( 'CYWater_Forum_Roles', 'CYWater_Forum_Endorsement', 'CYWater_Forum_Comments' ) as $class_name ) {
	if ( ! class_exists( $class_name ) ) {
		WP_CLI::error( "Missing required Forum class: {$class_name}" );
	}
}
if ( ! function_exists( 'pmpro_changeMembershipLevel' ) || ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
	WP_CLI::error( 'Paid Memberships Pro is unavailable.' );
}

global $wpdb;
$suffix       = strtolower( wp_generate_password( 8, false, false ) );
$author_id    = 0;
$nonmember_id = 0;
$post_id      = 0;
$comment_ids  = array();
$mail_count   = 0;

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

// Exercise Forum mail generation without sending disposable test addresses to
// Postmark. Real transport delivery is accepted separately with a real inbox.
$capture_mail = static function ( $preempt, $atts ) use ( &$mail_count ) {
	++$mail_count;
	return true;
};
add_filter( 'pre_wp_mail', $capture_mail, 10, 2 );

try {
	$level_id = (int) $wpdb->get_var(
		"SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE name IN ('Professional', 'Student') ORDER BY FIELD(name, 'Professional', 'Student') LIMIT 1"
	);
	$assert( $level_id > 0, 'No annual PMPro membership level was found.' );

	$administrator_ids = get_users(
		array(
			'role'   => 'administrator',
			'fields' => 'ID',
			'number' => 1,
		)
	);
	$assert( ! empty( $administrator_ids ), 'No administrator is available for the QA grant.' );

	$author_id = wp_insert_user(
		array(
			'user_login'   => "cyw_forum_qa_{$suffix}",
			'user_email'   => "cyw-forum-qa-{$suffix}@example.invalid",
			'user_pass'    => wp_generate_password( 30, true, true ),
			'display_name' => 'CYWater Forum staging QA',
			'role'         => 'subscriber',
		)
	);
	$assert( ! is_wp_error( $author_id ), 'Could not create the temporary author.' );
	$author = get_userdata( $author_id );
	update_user_meta( $author_id, 'cyw_verified_email', $author->user_email );
	$assert( pmpro_changeMembershipLevel( $level_id, $author_id ), 'Could not assign the temporary PMPro membership.' );
	$assert( pmpro_hasMembershipLevel( null, $author_id ), 'PMPro does not report the temporary membership as active.' );
	$assert( CYWater_Forum_Endorsement::admin_grant( $author_id, (int) $administrator_ids[0] ), 'Administrator grant failed.' );
	$assert( CYWater_Forum_Roles::can_publish( $author_id ), 'The qualified author is still blocked from publishing.' );

	$post_id = wp_insert_post(
		array(
			'post_type'      => 'cyw_forum_post',
			'post_status'    => 'publish',
			'post_title'     => 'CYWater Forum staging QA',
			'post_content'   => 'Temporary acceptance record. It is removed automatically.',
			'post_author'    => $author_id,
			'comment_status' => 'open',
		),
		true
	);
	$assert( ! is_wp_error( $post_id ), 'Could not publish the temporary Forum article.' );
	$assert( 'publish' === get_post_status( $post_id ), 'The temporary article was not published.' );

	$first_comment = wp_new_comment(
		array(
			'comment_post_ID'      => $post_id,
			'comment_content'      => 'First temporary reply.',
			'comment_type'         => 'comment',
			'user_id'              => $author_id,
			'comment_author'        => $author->display_name,
			'comment_author_email'  => $author->user_email,
			'comment_author_url'    => '',
			'comment_approved'      => 1,
		),
		true
	);
	$assert( ! is_wp_error( $first_comment ), is_wp_error( $first_comment ) ? $first_comment->get_error_message() : 'Could not create the first reply.' );
	$comment_ids[] = (int) $first_comment;
	$assert( '0' === (string) get_comment( $first_comment )->comment_approved, 'The first reply bypassed moderation.' );
	wp_set_comment_status( $first_comment, 'approve' );
	// Core's anti-flood interval is intentionally enforced on staging. Wait it
	// out so the next reply exercises the previously-approved-member policy.
	sleep( 16 );

	$second_comment = wp_new_comment(
		array(
			'comment_post_ID'      => $post_id,
			'comment_content'      => 'Second temporary reply.',
			'comment_type'         => 'comment',
			'user_id'              => $author_id,
			'comment_author'        => $author->display_name,
			'comment_author_email'  => $author->user_email,
			'comment_author_url'    => '',
			'comment_approved'      => 0,
		),
		true
	);
	$assert( ! is_wp_error( $second_comment ), is_wp_error( $second_comment ) ? $second_comment->get_error_message() : 'Could not create the second reply.' );
	$comment_ids[] = (int) $second_comment;
	$assert( '1' === (string) get_comment( $second_comment )->comment_approved, 'A previously approved member was not auto-approved.' );

	$nonmember_id = wp_insert_user(
		array(
			'user_login'   => "cyw_forum_nonmember_{$suffix}",
			'user_email'   => "cyw-forum-nonmember-{$suffix}@example.invalid",
			'user_pass'    => wp_generate_password( 30, true, true ),
			'display_name' => 'CYWater Forum nonmember QA',
			'role'         => 'subscriber',
		)
	);
	$assert( ! is_wp_error( $nonmember_id ), 'Could not create the temporary nonmember.' );
	$assert( ! CYWater_Forum_Comments::may_comment( $nonmember_id ), 'A nonmember was allowed to reply.' );

	$response = wp_remote_get( get_permalink( $post_id ), array( 'timeout' => 20 ) );
	$assert( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ), 'The published Forum route did not return HTTP 200.' );

	WP_CLI::success(
		wp_json_encode(
			array(
				'real_pmpro_membership' => true,
				'publishing_gate'       => true,
				'first_reply_held'      => true,
				'later_reply_approved'  => true,
				'nonmember_refused'     => true,
				'public_route'          => 200,
				'forum_mail_generated'  => $mail_count > 0,
			),
			JSON_UNESCAPED_SLASHES
		)
	);
} catch ( Throwable $error ) {
	WP_CLI::warning( $error->getMessage() );
	$failed = true;
} finally {
	remove_filter( 'pre_wp_mail', $capture_mail, 10 );
	foreach ( $comment_ids as $comment_id ) {
		wp_delete_comment( $comment_id, true );
	}
	if ( $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( array( $author_id, $nonmember_id ) as $user_id ) {
		if ( $user_id && get_userdata( $user_id ) ) {
			pmpro_changeMembershipLevel( 0, $user_id );
			wp_delete_user( $user_id );
		}
	}
}

if ( ! empty( $failed ) ) {
	WP_CLI::error( 'Forum staging QA failed after cleanup.' );
}
