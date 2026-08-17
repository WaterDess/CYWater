<?php
/**
 * Staging-only, self-cleaning runtime QA for CYWater Forum 0.1.2.
 *
 * Run with:
 *   wp eval-file /absolute/path/to/cywater-staging-forum-qa.php
 *
 * This script deliberately uses the real WordPress REST controllers for member
 * posting, comment submission, moderation, and negative permission checks. It
 * creates no payment order and intercepts every email before transport.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$forum_qa_url_is_exact_staging = static function ( $url ) {
	$parts = wp_parse_url( (string) $url );
	return is_array( $parts )
		&& 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
		&& 'staging.cywater.org' === strtolower( (string) ( $parts['host'] ?? '' ) );
};

if ( ! $forum_qa_url_is_exact_staging( home_url( '/' ) ) || ! $forum_qa_url_is_exact_staging( site_url( '/' ) ) ) {
	WP_CLI::error( 'Refusing to run: home_url and site_url must both be the exact HTTPS staging host.' );
}
if ( 'staging' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Refusing to run: WP_ENVIRONMENT_TYPE is not staging.' );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

if ( ! is_plugin_active( 'cywater-forum/cywater-forum.php' ) ) {
	WP_CLI::error( 'Refusing to run: CYWater Forum is not active.' );
}

$forum_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/cywater-forum/cywater-forum.php', false, false );
if ( '0.1.2' !== (string) ( $forum_plugin_data['Version'] ?? '' ) || ! defined( 'CYWATER_FORUM_VERSION' ) || '0.1.2' !== CYWATER_FORUM_VERSION ) {
	WP_CLI::error( 'Refusing to run: this QA is pinned to CYWater Forum 0.1.2.' );
}

$forum_qa_required_classes = array(
	'CYWater_Forum_Content',
	'CYWater_Forum_Roles',
	'CYWater_Forum_Endorsement',
	'CYWater_Forum_Comments',
	'CYWater_Forum_Settings',
	'CYWater_Membership_Account_Security',
	'CYWater_Operations_Roles',
);
foreach ( $forum_qa_required_classes as $forum_qa_class ) {
	if ( ! class_exists( $forum_qa_class ) ) {
		WP_CLI::error( 'Refusing to run: a required forum dependency is unavailable.' );
	}
}
if ( ! function_exists( 'pmpro_changeMembershipLevel' ) || ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
	WP_CLI::error( 'Refusing to run: the real PMPro membership API is unavailable.' );
}

$forum_qa_policy = array(
	'admin_override',
	'membership_required',
	'comments_enabled',
	'comments_require_membership',
	'comments_hold_first',
);
foreach ( $forum_qa_policy as $forum_qa_policy_key ) {
	if ( ! CYWater_Forum_Settings::is_enabled( $forum_qa_policy_key ) ) {
		WP_CLI::error( 'Refusing to run: the deployed forum policy does not match this acceptance matrix.' );
	}
}

$forum_qa_templates = array(
	'archive-cyw_forum_post.php',
	'single-cyw_forum_post.php',
	'comments.php',
);
foreach ( $forum_qa_templates as $forum_qa_template ) {
	if ( ! locate_template( $forum_qa_template, false, false ) ) {
		WP_CLI::error( 'Refusing to run: an active-theme forum template is missing.' );
	}
}

$forum_qa_role_requirements = array(
	CYWater_Operations_Roles::COMMUNITY_MODERATOR => array(
		'edit_others_cyw_forum_posts',
		'publish_cyw_forum_posts',
		'delete_others_cyw_forum_posts',
		'moderate_comments',
	),
	CYWater_Operations_Roles::CONTENT_EDITOR => array( 'read' ),
);
foreach ( $forum_qa_role_requirements as $forum_qa_role_slug => $forum_qa_caps ) {
	$forum_qa_role = get_role( $forum_qa_role_slug );
	if ( ! $forum_qa_role instanceof WP_Role ) {
		WP_CLI::error( 'Refusing to run: an operations role required by forum QA is missing.' );
	}
	foreach ( $forum_qa_caps as $forum_qa_cap ) {
		if ( empty( $forum_qa_role->capabilities[ $forum_qa_cap ] ) ) {
			WP_CLI::error( 'Refusing to run: an operations role is missing a required forum capability.' );
		}
	}
}

global $wpdb;
$forum_qa_level_id = (int) $wpdb->get_var(
	"SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE allow_signups = 1 ORDER BY id ASC LIMIT 1"
);
if ( $forum_qa_level_id < 1 ) {
	WP_CLI::error( 'Refusing to run: no signup-enabled PMPro membership level is available.' );
}

$forum_qa_admin_ids = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ids',
	)
);
$forum_qa_admin_id = absint( $forum_qa_admin_ids[0] ?? 0 );
if ( ! $forum_qa_admin_id || ! user_can( $forum_qa_admin_id, 'manage_options' ) ) {
	WP_CLI::error( 'Refusing to run: no recovery Administrator is available.' );
}

$forum_qa_original_user_id = get_current_user_id();
$forum_qa_users            = array();
$forum_qa_posts            = array();
$forum_qa_comments         = array();
$forum_qa_failures         = array();
$forum_qa_cleanup_failures = array();
$forum_qa_passes           = 0;
$forum_qa_intercepted_mail = array();

$forum_qa_assert = static function ( $condition, $message ) use ( &$forum_qa_failures, &$forum_qa_passes ) {
	if ( $condition ) {
		++$forum_qa_passes;
		return true;
	}
	$forum_qa_failures[] = (string) $message;
	return false;
};

$forum_qa_mail_filter = static function ( $short_circuit, $attributes ) use ( &$forum_qa_intercepted_mail ) {
	$forum_qa_intercepted_mail[] = is_array( $attributes ) ? $attributes : array();
	return true;
};
add_filter( 'pre_wp_mail', $forum_qa_mail_filter, 999, 2 );

$forum_qa_rest = static function ( $user_id, $method, $route, $params = array() ) {
	$prior_user_id = get_current_user_id();
	wp_set_current_user( absint( $user_id ) );
	$nonce   = wp_create_nonce( 'wp_rest' );
	$method  = strtoupper( (string) $method );
	$request = new WP_REST_Request( $method, (string) $route );
	$request->set_header( 'X-WP-Nonce', $nonce );
	if ( 'GET' === $method ) {
		$request->set_query_params( (array) $params );
	} else {
		$request->set_body_params( (array) $params );
	}
	$response = rest_do_request( $request );
	wp_set_current_user( $prior_user_id );
	return $response;
};

$forum_qa_rest_status = static function ( $response ) {
	return $response instanceof WP_REST_Response ? (int) $response->get_status() : 500;
};

$forum_qa_rest_data = static function ( $response ) {
	return $response instanceof WP_REST_Response && is_array( $response->get_data() ) ? $response->get_data() : array();
};

$forum_qa_rest_diagnostic = static function ( $response ) {
	$status  = $response instanceof WP_REST_Response ? (int) $response->get_status() : 500;
	$data    = $response instanceof WP_REST_Response && is_array( $response->get_data() ) ? $response->get_data() : array();
	$code    = sanitize_key( (string) ( $data['code'] ?? 'none' ) );
	$message = sanitize_text_field( (string) ( $data['message'] ?? 'No REST error message was returned.' ) );
	return sprintf( 'HTTP %1$d; code=%2$s; message=%3$s', $status, $code ?: 'none', $message );
};

$forum_qa_create_user = static function ( $suffix, $role ) use ( &$forum_qa_users ) {
	$token = strtolower( wp_generate_password( 8, false, false ) );
	$id    = wp_insert_user(
		array(
			'user_login'   => 'cyw_forum_qa_' . sanitize_key( $suffix ) . '_' . $token,
			'user_pass'    => wp_generate_password( 32, true, true ),
			'user_email'   => 'cyw-forum-qa-' . sanitize_key( $suffix ) . '-' . $token . '@example.com',
			'display_name' => 'CYWater Forum QA ' . ucfirst( (string) $suffix ),
			'role'         => (string) $role,
		)
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'A temporary QA account could not be created.' );
	}
	$forum_qa_users[] = absint( $id );
	return absint( $id );
};

$forum_qa_verify_email = static function ( $user_id ) use ( &$forum_qa_intercepted_mail ) {
	$start = count( $forum_qa_intercepted_mail );
	if ( ! CYWater_Membership_Account_Security::issue_verification( $user_id, home_url( '/forum/' ) ) ) {
		return false;
	}
	for ( $index = count( $forum_qa_intercepted_mail ) - 1; $index >= $start; --$index ) {
		$message = (string) ( $forum_qa_intercepted_mail[ $index ]['message'] ?? '' );
		if ( ! preg_match( '#https://[^\s]+#', $message, $matches ) ) {
			continue;
		}
		$url   = html_entity_decode( trim( $matches[0] ) );
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$args  = array();
		parse_str( $query, $args );
		if ( absint( $args['user_id'] ?? 0 ) !== absint( $user_id ) || empty( $args['token'] ) ) {
			continue;
		}
		return CYWater_Membership_Account_Security::verify_token( $user_id, (string) $args['token'] );
	}
	return false;
};

$forum_qa_activate_member = static function ( $user_id ) use ( $forum_qa_level_id ) {
	return (bool) pmpro_changeMembershipLevel( $forum_qa_level_id, absint( $user_id ) );
};

$forum_qa_find_comment = static function ( $response ) {
	if ( ! $response instanceof WP_REST_Response ) {
		return 0;
	}
	$data = $response->get_data();
	return is_array( $data ) ? absint( $data['id'] ?? 0 ) : 0;
};

$forum_qa_comment_status = static function ( $comment_id ) {
	$comment = get_comment( absint( $comment_id ) );
	return $comment instanceof WP_Comment ? (string) $comment->comment_approved : '';
};

$forum_qa_http_get = static function ( $url ) {
	return wp_remote_get(
		add_query_arg( 'cywater_forum_qa', wp_generate_password( 10, false, false ), $url ),
		array(
			'timeout'     => 20,
			'redirection' => 3,
			'headers'     => array( 'Cache-Control' => 'no-cache' ),
		)
	);
};

try {
	$forum_qa_marker        = 'CYW Forum QA ' . gmdate( 'YmdHis' ) . ' ' . wp_generate_password( 6, false, false );
	$forum_qa_first_reply   = $forum_qa_marker . ' approved first reply';
	$forum_qa_second_reply  = $forum_qa_marker . ' approved second reply';
	$forum_qa_staff_reply   = $forum_qa_marker . ' moderator reply';
	$forum_qa_pending_reply = $forum_qa_marker . ' pending reply';
	$forum_qa_outside_reply = $forum_qa_marker . ' unrelated approved comment';

	$forum_qa_author_id    = $forum_qa_create_user( 'author', 'subscriber' );
	$forum_qa_pending_id   = $forum_qa_create_user( 'pending', 'subscriber' );
	$forum_qa_moderator_id = $forum_qa_create_user( 'moderator', CYWater_Operations_Roles::COMMUNITY_MODERATOR );
	$forum_qa_editor_id    = $forum_qa_create_user( 'editor', CYWater_Operations_Roles::CONTENT_EDITOR );
	$forum_qa_builtin_editor_id = $forum_qa_create_user( 'builtin_editor', 'editor' );
	$forum_qa_subscriber_id = $forum_qa_create_user( 'subscriber', 'subscriber' );
	$forum_qa_builtin_editor_role = get_role( 'editor' );
	$forum_qa_legacy_editor_caps  = array_diff( CYWater_Forum_Roles::editor_capabilities(), array( 'read', 'upload_files' ) );
	$forum_qa_builtin_editor_forum_caps = array_filter(
		$forum_qa_legacy_editor_caps,
		static function ( $cap ) use ( $forum_qa_builtin_editor_role ) {
			return $forum_qa_builtin_editor_role instanceof WP_Role && ! empty( $forum_qa_builtin_editor_role->capabilities[ $cap ] );
		}
	);

	$forum_qa_assert( ! user_can( $forum_qa_author_id, 'edit_cyw_forum_posts' ), 'A plain registered account can create forum articles before authorship is granted.' );
	$forum_qa_assert( ! user_can( $forum_qa_editor_id, 'edit_cyw_forum_posts' ), 'Content Editor unexpectedly has a Forum Author primitive.' );
	$forum_qa_assert( ! user_can( $forum_qa_editor_id, 'edit_others_cyw_forum_posts' ), 'Content Editor unexpectedly has cross-author forum editing.' );
	$forum_qa_assert( ! user_can( $forum_qa_editor_id, 'moderate_comments' ), 'Content Editor unexpectedly has comment moderation.' );
	$forum_qa_assert( $forum_qa_builtin_editor_role instanceof WP_Role && array() === $forum_qa_builtin_editor_forum_caps, 'Built-in Editor retains legacy Forum-specific CPT or term capabilities.' );
	$forum_qa_assert( user_can( $forum_qa_builtin_editor_id, 'moderate_comments' ), 'Built-in Editor lost the core moderate_comments capability needed for non-Forum content.' );
	$forum_qa_assert( user_can( $forum_qa_admin_id, 'edit_others_cyw_forum_posts' ) && user_can( $forum_qa_admin_id, 'manage_cyw_forum_terms' ), 'Administrator lost required Forum staff capabilities during role migration.' );
	$forum_qa_assert( ! user_can( $forum_qa_subscriber_id, 'edit_cyw_forum_posts' ), 'Subscriber unexpectedly has a Forum Author primitive.' );
	$forum_qa_assert( user_can( $forum_qa_moderator_id, 'edit_others_cyw_forum_posts' ), 'Community Moderator cannot edit another author\'s forum article.' );
	$forum_qa_assert( user_can( $forum_qa_moderator_id, 'publish_cyw_forum_posts' ), 'Community Moderator cannot publish forum articles.' );
	$forum_qa_assert( user_can( $forum_qa_moderator_id, 'moderate_comments' ), 'Community Moderator cannot moderate comments.' );

	$forum_qa_plain_create = $forum_qa_rest(
		$forum_qa_subscriber_id,
		'POST',
		'/wp/v2/cyw_forum_post',
		array( 'title' => $forum_qa_marker . ' forbidden subscriber article', 'status' => 'draft' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_plain_create ) >= 400, 'Subscriber created a forum article through REST.' );

	$forum_qa_editor_create = $forum_qa_rest(
		$forum_qa_editor_id,
		'POST',
		'/wp/v2/cyw_forum_post',
		array( 'title' => $forum_qa_marker . ' forbidden editor article', 'status' => 'draft' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_editor_create ) >= 400, 'Content Editor created a forum article through REST.' );

	$forum_qa_assert( CYWater_Forum_Endorsement::admin_grant( $forum_qa_author_id, $forum_qa_admin_id ), 'Administrator could not grant the temporary account Forum Author status.' );
	$forum_qa_author = get_user_by( 'id', $forum_qa_author_id );
	$forum_qa_assert( $forum_qa_author instanceof WP_User && in_array( CYWater_Forum_Roles::AUTHOR_ROLE, (array) $forum_qa_author->roles, true ), 'Forum Author role was not persisted on the account.' );
	$forum_qa_assert( user_can( $forum_qa_author_id, 'edit_cyw_forum_posts' ), 'Forum Author cannot create a draft.' );
	$forum_qa_assert( ! user_can( $forum_qa_author_id, 'edit_others_cyw_forum_posts' ), 'Forum Author can edit another author\'s article.' );

	$forum_qa_blockers = CYWater_Forum_Roles::publish_blockers( $forum_qa_author_id );
	$forum_qa_assert( in_array( 'membership_inactive', $forum_qa_blockers, true ), 'Forum Author without membership is not blocked from publication.' );
	$forum_qa_assert( in_array( 'email_unverified', $forum_qa_blockers, true ), 'Forum Author with unverified email is not blocked from publication.' );
	$forum_qa_assert( ! user_can( $forum_qa_author_id, 'publish_cyw_forum_posts' ), 'Unverified, inactive Forum Author has the publish capability.' );

	$forum_qa_draft_response = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/cyw_forum_post',
		array(
			'title'          => $forum_qa_marker,
			'content'        => $forum_qa_marker . ' initial body',
			'excerpt'        => $forum_qa_marker . ' excerpt',
			'status'         => 'draft',
			'comment_status' => 'open',
		)
	);
	$forum_qa_draft_data = $forum_qa_rest_data( $forum_qa_draft_response );
	$forum_qa_post_id    = absint( $forum_qa_draft_data['id'] ?? 0 );
	$forum_qa_assert( 201 === $forum_qa_rest_status( $forum_qa_draft_response ) && $forum_qa_post_id > 0, 'Eligible Forum Author could not create a draft through REST.' );
	if ( $forum_qa_post_id ) {
		$forum_qa_posts[] = $forum_qa_post_id;
	}

	$forum_qa_precondition_publish = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'status' => 'publish' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_precondition_publish ) >= 400, 'Forum Author published before membership and email verification.' );
	$forum_qa_assert( 'draft' === get_post_status( $forum_qa_post_id ), 'Rejected publication changed the draft status.' );

	$forum_qa_assert( $forum_qa_activate_member( $forum_qa_author_id ), 'Temporary Forum Author membership could not be activated.' );
	$forum_qa_assert( $forum_qa_verify_email( $forum_qa_author_id ), 'Temporary Forum Author email could not be verified through the real token flow.' );
	$forum_qa_assert( pmpro_hasMembershipLevel( null, $forum_qa_author_id ), 'Temporary Forum Author membership is not active after activation.' );
	$forum_qa_assert( CYWater_Membership_Account_Security::is_verified( $forum_qa_author_id ), 'Temporary Forum Author verification state is not active.' );
	$forum_qa_assert( array() === CYWater_Forum_Roles::publish_blockers( $forum_qa_author_id ), 'Forum Author remains blocked after satisfying all prerequisites.' );

	$forum_qa_publish_response = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'status' => 'publish', 'comment_status' => 'open' )
	);
	$forum_qa_assert( 200 === $forum_qa_rest_status( $forum_qa_publish_response ), 'Qualified Forum Author could not publish through REST.' );
	$forum_qa_assert( 'publish' === get_post_status( $forum_qa_post_id ), 'Qualified publication did not persist the published status.' );

	$forum_qa_editor_update = $forum_qa_rest(
		$forum_qa_editor_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'content' => $forum_qa_marker . ' forbidden editor update' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_editor_update ) >= 400, 'Content Editor edited another account\'s forum article through REST.' );

	$forum_qa_subscriber_update = $forum_qa_rest(
		$forum_qa_subscriber_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'content' => $forum_qa_marker . ' forbidden subscriber update' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_subscriber_update ) >= 400, 'Subscriber edited another account\'s forum article through REST.' );

	$forum_qa_moderator_update = $forum_qa_rest(
		$forum_qa_moderator_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'content' => $forum_qa_marker . ' moderator-reviewed body' )
	);
	$forum_qa_assert( 200 === $forum_qa_rest_status( $forum_qa_moderator_update ), 'Community Moderator could not edit another account\'s forum article.' );
	$forum_qa_assert( false !== strpos( (string) get_post_field( 'post_content', $forum_qa_post_id ), 'moderator-reviewed body' ), 'Community Moderator edit was not persisted.' );

	// A historical approved comment on any other post type must not waive the
	// first-reply hold for this forum. This is a regression check for a former
	// cross-post-type count bug.
	wp_set_current_user( $forum_qa_admin_id );
	$forum_qa_other_post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => $forum_qa_marker . ' unrelated post',
			'post_content' => $forum_qa_marker . ' unrelated body',
		),
		true
	);
	if ( is_wp_error( $forum_qa_other_post_id ) ) {
		throw new RuntimeException( 'The non-forum regression fixture could not be created.' );
	}
	$forum_qa_other_post_id = absint( $forum_qa_other_post_id );
	$forum_qa_posts[]       = $forum_qa_other_post_id;
	$forum_qa_other_comment_id = wp_insert_comment(
		array(
			'comment_post_ID'  => $forum_qa_other_post_id,
			'comment_content'  => $forum_qa_outside_reply,
			'comment_approved' => 1,
			// This represents an older non-Forum contribution. Keeping it well
			// outside WordPress' flood window prevents the regression fixture
			// itself from blocking the immediately following real Forum reply.
			'comment_date'      => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 5 * MINUTE_IN_SECONDS ) ),
			'comment_date_gmt'  => gmdate( 'Y-m-d H:i:s', time() - ( 5 * MINUTE_IN_SECONDS ) ),
			'user_id'          => $forum_qa_author_id,
			'comment_author'   => 'CYWater Forum QA Author',
			'comment_author_email' => get_userdata( $forum_qa_author_id )->user_email,
		)
	);
	$forum_qa_other_comment_id = absint( $forum_qa_other_comment_id );
	if ( $forum_qa_other_comment_id ) {
		$forum_qa_comments[] = $forum_qa_other_comment_id;
	}
	$forum_qa_assert( $forum_qa_other_comment_id > 0, 'The non-forum approved-comment fixture could not be created.' );
	$forum_qa_assert( 0 === CYWater_Forum_Comments::approved_comment_count( $forum_qa_author_id ), 'A non-forum approved comment incorrectly counts as an approved forum reply.' );

	$forum_qa_editor_status_reply = $forum_qa_marker . ' forbidden pre-approved Editor reply';
	$forum_qa_editor_status_response = $forum_qa_rest(
		$forum_qa_builtin_editor_id,
		'POST',
		'/wp/v2/comments',
		array(
			'post'    => $forum_qa_post_id,
			'content' => $forum_qa_editor_status_reply,
			'status'  => 'approved',
		)
	);
	$forum_qa_editor_status_data = $forum_qa_rest_data( $forum_qa_editor_status_response );
	$forum_qa_assert(
		403 === $forum_qa_rest_status( $forum_qa_editor_status_response )
			&& 'cywater_forum_comment_submission_forbidden' === (string) ( $forum_qa_editor_status_data['code'] ?? '' ),
		'Built-in Editor bypassed Forum moderation through a pre-approved collection POST (' . $forum_qa_rest_diagnostic( $forum_qa_editor_status_response ) . ').'
	);
	$forum_qa_assert(
		0 === (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_content = %s",
				$forum_qa_post_id,
				$forum_qa_editor_status_reply
			)
		),
		'Rejected built-in Editor pre-approved reply was inserted.'
	);

	$forum_qa_editor_foreign_reply = $forum_qa_marker . ' forbidden foreign-author Editor reply';
	$forum_qa_editor_foreign_response = $forum_qa_rest(
		$forum_qa_builtin_editor_id,
		'POST',
		'/wp/v2/comments',
		array(
			'post'    => $forum_qa_post_id,
			'content' => $forum_qa_editor_foreign_reply,
			'author'  => $forum_qa_author_id,
		)
	);
	$forum_qa_editor_foreign_data = $forum_qa_rest_data( $forum_qa_editor_foreign_response );
	$forum_qa_assert(
		403 === $forum_qa_rest_status( $forum_qa_editor_foreign_response )
			&& 'cywater_forum_comment_submission_forbidden' === (string) ( $forum_qa_editor_foreign_data['code'] ?? '' ),
		'Built-in Editor attributed a Forum collection reply to another account (' . $forum_qa_rest_diagnostic( $forum_qa_editor_foreign_response ) . ').'
	);
	$forum_qa_assert(
		0 === (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_content = %s",
				$forum_qa_post_id,
				$forum_qa_editor_foreign_reply
			)
		),
		'Rejected built-in Editor foreign-author reply was inserted.'
	);

	$forum_qa_first_comment_response = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/comments',
		array( 'post' => $forum_qa_post_id, 'content' => $forum_qa_first_reply )
	);
	$forum_qa_first_comment_id = $forum_qa_find_comment( $forum_qa_first_comment_response );
	if ( $forum_qa_first_comment_id ) {
		$forum_qa_comments[] = $forum_qa_first_comment_id;
	}
	$forum_qa_assert(
		201 === $forum_qa_rest_status( $forum_qa_first_comment_response ) && $forum_qa_first_comment_id > 0,
		'Active member could not submit a first forum reply (' . $forum_qa_rest_diagnostic( $forum_qa_first_comment_response ) . ').'
	);
	$forum_qa_assert( '0' === $forum_qa_comment_status( $forum_qa_first_comment_id ), 'A member\'s first forum reply did not enter moderation.' );

	$forum_qa_approve_response = $forum_qa_rest(
		$forum_qa_moderator_id,
		'POST',
		'/wp/v2/comments/' . $forum_qa_first_comment_id,
		array( 'status' => 'approved' )
	);
	$forum_qa_assert( 200 === $forum_qa_rest_status( $forum_qa_approve_response ), 'Community Moderator could not approve the held first reply.' );
	$forum_qa_assert( '1' === $forum_qa_comment_status( $forum_qa_first_comment_id ), 'Community Moderator approval was not persisted.' );

	// Keep the second-reply policy test independent from WordPress' generic
	// comment-flood throttle. The first reply remains the same approved Forum
	// contribution; only its fixture timestamp is moved outside the flood window.
	$forum_qa_first_comment_backdated = wp_update_comment(
		array(
			'comment_ID'       => $forum_qa_first_comment_id,
			'comment_date'     => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 5 * MINUTE_IN_SECONDS ) ),
			'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - ( 5 * MINUTE_IN_SECONDS ) ),
		),
		true
	);
	$forum_qa_assert(
		! is_wp_error( $forum_qa_first_comment_backdated ) && false !== $forum_qa_first_comment_backdated,
		'The approved first-reply fixture could not be moved outside the comment-flood window.'
	);

	$forum_qa_second_comment_response = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/comments',
		array(
			'post'    => $forum_qa_post_id,
			'content' => $forum_qa_second_reply,
			'author'  => $forum_qa_author_id,
		)
	);
	$forum_qa_second_comment_id = $forum_qa_find_comment( $forum_qa_second_comment_response );
	if ( $forum_qa_second_comment_id ) {
		$forum_qa_comments[] = $forum_qa_second_comment_id;
	}
	$forum_qa_assert(
		201 === $forum_qa_rest_status( $forum_qa_second_comment_response ) && '1' === $forum_qa_comment_status( $forum_qa_second_comment_id ),
		'A member\'s signed-in self-attributed later reply was not auto-approved after the first approval (' . $forum_qa_rest_diagnostic( $forum_qa_second_comment_response ) . ').'
	);

	$forum_qa_moderator_comment_response = $forum_qa_rest(
		$forum_qa_moderator_id,
		'POST',
		'/wp/v2/comments',
		array( 'post' => $forum_qa_post_id, 'content' => $forum_qa_staff_reply )
	);
	$forum_qa_moderator_comment_id = $forum_qa_find_comment( $forum_qa_moderator_comment_response );
	if ( $forum_qa_moderator_comment_id ) {
		$forum_qa_comments[] = $forum_qa_moderator_comment_id;
	}
	$forum_qa_assert( 201 === $forum_qa_rest_status( $forum_qa_moderator_comment_response ) && '1' === $forum_qa_comment_status( $forum_qa_moderator_comment_id ), 'Community Moderator reply was not auto-approved.' );

	$forum_qa_assert( $forum_qa_activate_member( $forum_qa_pending_id ), 'Temporary pending-comment membership could not be activated.' );
	$forum_qa_pending_comment_response = $forum_qa_rest(
		$forum_qa_pending_id,
		'POST',
		'/wp/v2/comments',
		array( 'post' => $forum_qa_post_id, 'content' => $forum_qa_pending_reply )
	);
	$forum_qa_pending_comment_id = $forum_qa_find_comment( $forum_qa_pending_comment_response );
	if ( $forum_qa_pending_comment_id ) {
		$forum_qa_comments[] = $forum_qa_pending_comment_id;
	}
	$forum_qa_assert( 201 === $forum_qa_rest_status( $forum_qa_pending_comment_response ) && '0' === $forum_qa_comment_status( $forum_qa_pending_comment_id ), 'A different member\'s first reply did not enter moderation.' );
	$forum_qa_assert( ! user_can( $forum_qa_builtin_editor_id, 'edit_comment', $forum_qa_pending_comment_id ), 'Built-in Editor can edit an exact Forum reply through the core edit_comment capability.' );
	$forum_qa_assert( user_can( $forum_qa_moderator_id, 'edit_comment', $forum_qa_pending_comment_id ) && user_can( $forum_qa_admin_id, 'edit_comment', $forum_qa_pending_comment_id ), 'Forum staff cannot access legitimate comment moderation controls.' );

	$forum_qa_builtin_editor_approve = $forum_qa_rest(
		$forum_qa_builtin_editor_id,
		'POST',
		'/wp/v2/comments/' . $forum_qa_pending_comment_id,
		array( 'status' => 'approved' )
	);
	$forum_qa_assert( 403 === $forum_qa_rest_status( $forum_qa_builtin_editor_approve ), 'Built-in Editor changed a held Forum reply through the core REST comments controller.' );
	$forum_qa_assert( '0' === $forum_qa_comment_status( $forum_qa_pending_comment_id ), 'Rejected built-in Editor moderation changed the Forum reply state.' );

	$forum_qa_prior_query_user = get_current_user_id();
	wp_set_current_user( $forum_qa_builtin_editor_id );
	$forum_qa_forum_only_query = CYWater_Forum_Comments::exclude_forum_from_nonstaff_comment_query(
		array( 'post_type' => CYWater_Forum_Content::POST_TYPE )
	);
	$forum_qa_ordinary_query = CYWater_Forum_Comments::exclude_forum_from_nonstaff_comment_query(
		array( 'post_type' => 'post' )
	);
	wp_set_current_user( $forum_qa_prior_query_user );
	$forum_qa_assert( array( '__cywater_no_allowed_post_type__' ) === (array) ( $forum_qa_forum_only_query['post_type'] ?? array() ), 'Built-in Editor Forum-only moderation query was not closed by an impossible post type.' );
	$forum_qa_assert( array( 'post' ) === (array) ( $forum_qa_ordinary_query['post_type'] ?? array() ), 'Built-in Editor lost its legitimate non-Forum comment queue.' );

	$forum_qa_builtin_editor_queue = $forum_qa_rest(
		$forum_qa_builtin_editor_id,
		'GET',
		'/wp/v2/comments',
		array(
			'context'  => 'edit',
			'status'   => 'hold',
			'post'     => $forum_qa_post_id,
			'per_page' => 100,
		)
	);
	$forum_qa_builtin_editor_queue_data = $forum_qa_rest_data( $forum_qa_builtin_editor_queue );
	$forum_qa_builtin_editor_queue_ids  = array();
	foreach ( $forum_qa_builtin_editor_queue_data as $forum_qa_queue_item ) {
		if ( is_array( $forum_qa_queue_item ) && isset( $forum_qa_queue_item['id'] ) ) {
			$forum_qa_builtin_editor_queue_ids[] = absint( $forum_qa_queue_item['id'] );
		}
	}
	$forum_qa_assert( 200 === $forum_qa_rest_status( $forum_qa_builtin_editor_queue ) && ! in_array( $forum_qa_pending_comment_id, $forum_qa_builtin_editor_queue_ids, true ), 'Held Forum reply leaked into the built-in Editor REST moderation queue.' );

	$forum_qa_editor_approve = $forum_qa_rest(
		$forum_qa_editor_id,
		'POST',
		'/wp/v2/comments/' . $forum_qa_pending_comment_id,
		array( 'status' => 'approved' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_editor_approve ) >= 400, 'Content Editor approved a held forum reply.' );
	$forum_qa_assert( '0' === $forum_qa_comment_status( $forum_qa_pending_comment_id ), 'Rejected Content Editor approval changed the reply state.' );

	$forum_qa_subscriber_comment = $forum_qa_rest(
		$forum_qa_subscriber_id,
		'POST',
		'/wp/v2/comments',
		array( 'post' => $forum_qa_post_id, 'content' => $forum_qa_marker . ' forbidden subscriber reply' )
	);
	$forum_qa_subscriber_comment_data = $forum_qa_rest_data( $forum_qa_subscriber_comment );
	$forum_qa_assert(
		403 === $forum_qa_rest_status( $forum_qa_subscriber_comment ),
		'Subscriber without membership submitted a forum reply (' . $forum_qa_rest_diagnostic( $forum_qa_subscriber_comment ) . ').'
	);
	$forum_qa_assert( 'cywater_forum_membership_required' === (string) ( $forum_qa_subscriber_comment_data['code'] ?? '' ), 'Subscriber rejection did not use the forum membership boundary.' );

	$forum_qa_archive_response = $forum_qa_http_get( (string) get_post_type_archive_link( CYWater_Forum_Content::POST_TYPE ) );
	$forum_qa_archive_body     = is_wp_error( $forum_qa_archive_response ) ? '' : (string) wp_remote_retrieve_body( $forum_qa_archive_response );
	$forum_qa_assert( ! is_wp_error( $forum_qa_archive_response ) && 200 === (int) wp_remote_retrieve_response_code( $forum_qa_archive_response ), 'Public forum archive did not return HTTP 200.' );
	$forum_qa_assert( false !== strpos( $forum_qa_archive_body, esc_html( $forum_qa_marker ) ), 'Published QA forum article is absent from the public archive.' );

	$forum_qa_single_response = $forum_qa_http_get( get_permalink( $forum_qa_post_id ) );
	$forum_qa_single_body     = is_wp_error( $forum_qa_single_response ) ? '' : (string) wp_remote_retrieve_body( $forum_qa_single_response );
	$forum_qa_assert( ! is_wp_error( $forum_qa_single_response ) && 200 === (int) wp_remote_retrieve_response_code( $forum_qa_single_response ), 'Public forum article did not return HTTP 200.' );
	$forum_qa_assert( false !== strpos( $forum_qa_single_body, esc_html( $forum_qa_marker ) ), 'Published article title/body is absent from its public route.' );
	$forum_qa_assert( false !== strpos( $forum_qa_single_body, esc_html( $forum_qa_first_reply ) ), 'Approved first reply is absent from the public article.' );
	$forum_qa_assert( false !== strpos( $forum_qa_single_body, esc_html( $forum_qa_second_reply ) ), 'Approved later reply is absent from the public article.' );
	$forum_qa_assert( false !== strpos( $forum_qa_single_body, esc_html( $forum_qa_staff_reply ) ), 'Approved Community Moderator reply is absent from the public article.' );
	$forum_qa_assert( false === strpos( $forum_qa_single_body, esc_html( $forum_qa_pending_reply ) ), 'Held reply leaked into the anonymous public article.' );

	// Authors may take down their own published work, but an Administrator
	// takedown is authoritative. Once the record is in trash, a Forum Author
	// must not regain the capability WordPress uses to untrash it or recover it
	// through REST. Community Moderators are staff and retain the explicit
	// recovery path, which restores the prior publication state.
	$forum_qa_assert( user_can( $forum_qa_author_id, 'delete_post', $forum_qa_post_id ), 'Forum Author cannot trash their own published article.' );
	wp_set_current_user( $forum_qa_admin_id );
	$forum_qa_trashed = wp_trash_post( $forum_qa_post_id );
	$forum_qa_assert( $forum_qa_trashed instanceof WP_Post && 'trash' === get_post_status( $forum_qa_post_id ), 'Administrator could not take down the QA forum article.' );
	$forum_qa_assert( ! user_can( $forum_qa_author_id, 'delete_post', $forum_qa_post_id ), 'Forum Author has the capability used by WordPress to restore a taken-down article.' );
	wp_set_current_user( $forum_qa_author_id );
	$forum_qa_direct_restore = wp_untrash_post( $forum_qa_post_id );
	$forum_qa_assert( false === $forum_qa_direct_restore, 'Forum Author restored a taken-down article through the direct WordPress untrash API.' );
	$forum_qa_assert( 'trash' === get_post_status( $forum_qa_post_id ), 'Rejected direct Forum Author restore changed the article status.' );
	$forum_qa_restore_response = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'status' => 'publish' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_restore_response ) >= 400, 'Forum Author restored a taken-down published article through REST.' );
	$forum_qa_assert( 'trash' === get_post_status( $forum_qa_post_id ), 'Rejected Forum Author restore changed the article status.' );

	$forum_qa_assert( user_can( $forum_qa_moderator_id, 'delete_post', $forum_qa_post_id ), 'Community Moderator cannot access the staff-only restore path.' );
	wp_set_current_user( $forum_qa_moderator_id );
	$forum_qa_staff_restore = wp_untrash_post( $forum_qa_post_id );
	$forum_qa_assert( $forum_qa_staff_restore instanceof WP_Post && 'publish' === get_post_status( $forum_qa_post_id ), 'Community Moderator could not restore the prior published state.' );
} catch ( Throwable $forum_qa_exception ) {
	$forum_qa_failures[] = 'QA execution stopped unexpectedly (' . get_class( $forum_qa_exception ) . ').';
} finally {
	wp_set_current_user( $forum_qa_admin_id );

	foreach ( array_reverse( array_unique( array_map( 'absint', $forum_qa_comments ) ) ) as $forum_qa_comment_id ) {
		if ( get_comment( $forum_qa_comment_id ) && ! wp_delete_comment( $forum_qa_comment_id, true ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary comment could not be deleted.';
		}
	}

	foreach ( array_reverse( array_unique( array_map( 'absint', $forum_qa_posts ) ) ) as $forum_qa_cleanup_post_id ) {
		if ( get_post( $forum_qa_cleanup_post_id ) && ! wp_delete_post( $forum_qa_cleanup_post_id, true ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary post could not be deleted.';
		}
	}

	foreach ( array_unique( array_map( 'absint', $forum_qa_users ) ) as $forum_qa_cleanup_user_id ) {
		CYWater_Forum_Endorsement::admin_revoke( $forum_qa_cleanup_user_id );
		pmpro_changeMembershipLevel( 0, $forum_qa_cleanup_user_id );
		// PMPro deliberately retains cancelled membership history. These rows
		// belong only to disposable QA users, so remove them by exact user id
		// before deleting the user and verify the table is clean below.
		if (
			false === $wpdb->delete(
				$wpdb->pmpro_memberships_users,
				array( 'user_id' => $forum_qa_cleanup_user_id ),
				array( '%d' )
			)
		) {
			$forum_qa_cleanup_failures[] = 'Temporary membership rows could not be deleted.';
		}
		if ( get_user_by( 'id', $forum_qa_cleanup_user_id ) && ! wp_delete_user( $forum_qa_cleanup_user_id ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary user could not be deleted.';
		}
	}

	foreach ( array_unique( array_map( 'absint', $forum_qa_comments ) ) as $forum_qa_comment_id ) {
		if ( get_comment( $forum_qa_comment_id ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary comment remains after cleanup.';
		}
	}
	foreach ( array_unique( array_map( 'absint', $forum_qa_posts ) ) as $forum_qa_cleanup_post_id ) {
		if ( get_post( $forum_qa_cleanup_post_id ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary post remains after cleanup.';
		}
	}
	foreach ( array_unique( array_map( 'absint', $forum_qa_users ) ) as $forum_qa_cleanup_user_id ) {
		if ( get_user_by( 'id', $forum_qa_cleanup_user_id ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary user remains after cleanup.';
		}
		$forum_qa_membership_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pmpro_memberships_users} WHERE user_id = %d", $forum_qa_cleanup_user_id )
		);
		if ( $forum_qa_membership_rows > 0 ) {
			$forum_qa_cleanup_failures[] = 'Temporary membership rows remain after user cleanup.';
		}
	}

	remove_filter( 'pre_wp_mail', $forum_qa_mail_filter, 999 );
	wp_set_current_user( $forum_qa_original_user_id );
}

if ( $forum_qa_cleanup_failures ) {
	$forum_qa_failures = array_merge( $forum_qa_failures, $forum_qa_cleanup_failures );
}

if ( $forum_qa_failures ) {
	foreach ( array_values( array_unique( $forum_qa_failures ) ) as $forum_qa_failure ) {
		WP_CLI::warning( $forum_qa_failure );
	}
	WP_CLI::error(
		sprintf(
			'CYWater Forum staging QA failed after %d passing assertions; cleanup was attempted for every fixture.',
			$forum_qa_passes
		)
	);
}

WP_CLI::success(
	sprintf(
		'CYWater Forum staging QA passed %1$d assertions. %2$d outgoing messages were intercepted, and all temporary users, posts, comments, and membership rows were removed.',
		$forum_qa_passes,
		count( $forum_qa_intercepted_mail )
	)
);
