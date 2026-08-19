<?php
/**
 * Destructive-but-self-cleaning lifecycle acceptance test for CYWater staging.
 *
 * Run only through WP-CLI:
 *   wp eval-file scripts/cywater-staging-lifecycle-qa.php
 *
 * The host guard deliberately prevents execution anywhere except the canonical
 * staging site. Temporary posts and the temporary subscriber are force-deleted
 * in a finally block, including when an assertion fails.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
if ( 'staging.cywater.org' !== $site_host ) {
	WP_CLI::error( 'Refusing to run: this lifecycle test is restricted to staging.cywater.org.' );
}

$created_post_ids = array();
$created_user_id   = 0;
$failure_message   = '';
$results           = array(
	'site'       => $site_host,
	'content'    => array(),
	'membership' => array(),
	'payments'   => array(),
	'security'   => array(),
);

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

try {
	$content_cases = array(
		'page' => array(
			'meta_key'   => '_cyw_eyebrow',
			'meta_value' => 'Lifecycle QA page',
		),
		'post' => array(
			'meta_key'   => '_cyw_visual_title',
			'meta_value' => 'QA news visual',
		),
		'cyw_event' => array(
			'meta_key'   => '_cyw_start_date',
			'meta_value' => '2099-12-30',
		),
		'cyw_award' => array(
			'meta_key'   => '_cyw_year',
			'meta_value' => 2099,
		),
		'cyw_board_role' => array(
			'meta_key'   => '_cyw_person_name',
			'meta_value' => 'Lifecycle QA',
		),
	);

	foreach ( $content_cases as $post_type => $case ) {
		$assert( post_type_exists( $post_type ), "Missing post type: {$post_type}" );
		$post_id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_status'  => 'publish',
				'post_title'   => "CYWater lifecycle QA {$post_type}",
				'post_content' => 'Temporary acceptance-test content. It must be deleted automatically.',
				'post_excerpt' => 'Temporary lifecycle QA record.',
			),
			true
		);
		$assert( ! is_wp_error( $post_id ), "Create failed for {$post_type}" );
		$created_post_ids[] = (int) $post_id;

		update_post_meta( $post_id, $case['meta_key'], $case['meta_value'] );
		$assert( (string) get_post_meta( $post_id, $case['meta_key'], true ) === (string) $case['meta_value'], "Metadata persistence failed for {$post_type}" );

		$updated_title = "CYWater lifecycle QA updated {$post_type}";
		$updated_id    = wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $updated_title,
			),
			true
		);
		$assert( ! is_wp_error( $updated_id ) && $updated_title === get_the_title( $post_id ), "Update failed for {$post_type}" );

		$trashed = wp_trash_post( $post_id );
		$assert( $trashed && 'trash' === get_post_status( $post_id ), "Trash failed for {$post_type}" );
		$deleted = wp_delete_post( $post_id, true );
		$assert( $deleted && null === get_post( $post_id ), "Permanent delete failed for {$post_type}" );
		$created_post_ids = array_values( array_diff( $created_post_ids, array( (int) $post_id ) ) );
		$results['content'][ $post_type ] = 'create-update-trash-delete: pass';
	}

	$login = 'cywater_qa_' . strtolower( wp_generate_password( 10, false, false ) );
	$created_user_id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $login . '@example.invalid',
			'user_pass'    => wp_generate_password( 32, true, true ),
			'display_name' => 'CYWater Lifecycle QA',
			'role'         => 'subscriber',
		)
	);
	$assert( ! is_wp_error( $created_user_id ), 'Temporary subscriber creation failed.' );
	$created_user_id = (int) $created_user_id;
	$created_user     = get_userdata( $created_user_id );
	$assert( $created_user && in_array( 'subscriber', $created_user->roles, true ), 'New account does not have the subscriber role.' );
	$assert( ! (bool) get_user_meta( $created_user_id, 'cyw_profile_public', true ), 'New account was not private by default.' );

	$profile = array(
		'cyw_institution_name'   => 'CYWater Lifecycle QA',
		'cyw_country'            => 'United States',
		'cyw_institution_type'   => 'research_institute',
		'cyw_professional_title' => 'Acceptance tester',
		'cyw_career_stage'       => 'professional',
	);
	foreach ( $profile as $key => $value ) {
		update_user_meta( $created_user_id, $key, $value );
		$assert( $value === get_user_meta( $created_user_id, $key, true ), "Profile persistence failed: {$key}" );
	}

	update_user_meta( $created_user_id, 'cyw_profile_public', 1 );
	update_user_meta( $created_user_id, 'cyw_public_fields', array( 'institution', 'country' ) );
	$assert( (bool) get_user_meta( $created_user_id, 'cyw_profile_public', true ), 'Directory opt-in did not persist.' );
	update_user_meta( $created_user_id, 'cyw_profile_public', 0 );
	$assert( ! (bool) get_user_meta( $created_user_id, 'cyw_profile_public', true ), 'Directory opt-out did not persist.' );

	global $wpdb;
	$levels_table = $wpdb->prefix . 'pmpro_membership_levels';
	$level_id     = (int) $wpdb->get_var( "SELECT id FROM {$levels_table} ORDER BY id ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$assert( $level_id > 0 && function_exists( 'pmpro_changeMembershipLevel' ), 'No PMPro level is available for membership lifecycle testing.' );
	$assert( (bool) pmpro_changeMembershipLevel( $level_id, $created_user_id ), 'Membership activation failed.' );
	$assert( (bool) pmpro_hasMembershipLevel( $level_id, $created_user_id ), 'Activated membership was not readable.' );
	$assert( (bool) pmpro_cancelMembershipLevel( $level_id, $created_user_id, 'admin' ), 'Membership cancellation failed.' );
	$assert( ! pmpro_hasMembershipLevel( $level_id, $created_user_id ), 'Cancelled membership remained active.' );

	$results['membership'] = array(
		'account' => 'create-read-delete: pass',
		'profile' => 'required fields and private-by-default controls: pass',
		'level'   => 'activate-read-cancel: pass',
	);

	$roles = wp_roles();
	$results['security'] = array(
		'application_password_users' => count( get_users( array( 'meta_key' => '_application_passwords', 'fields' => 'ids' ) ) ),
		'core_public_registration'    => (bool) get_option( 'users_can_register' ),
		'default_role'                => (string) get_option( 'default_role' ),
		'editor_can_edit_content'     => (bool) $roles->get_role( 'editor' )->has_cap( 'edit_posts' ),
		'subscriber_can_edit_content' => (bool) $roles->get_role( 'subscriber' )->has_cap( 'edit_posts' ),
	);
	$assert( false === $results['security']['core_public_registration'], 'Core public registration unexpectedly became enabled.' );
	$assert( 'subscriber' === $results['security']['default_role'], 'The default role is not Subscriber.' );
	$assert( true === $results['security']['editor_can_edit_content'], 'Editors cannot operate editorial content.' );
	$assert( false === $results['security']['subscriber_can_edit_content'], 'Subscribers can unexpectedly edit editorial content.' );

	$orders_table = $wpdb->prefix . 'pmpro_membership_orders';
	$order_counts = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$orders_table} GROUP BY status", OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$total_orders = 0;
	foreach ( $order_counts as $status => $row ) {
		$total_orders += (int) $row->total;
		$results['payments'][ sanitize_key( $status ) . '_orders' ] = (int) $row->total;
	}
	foreach ( array( 'success', 'refunded' ) as $status ) {
		$results['payments'][ $status . '_orders' ] = isset( $order_counts[ $status ] ) ? (int) $order_counts[ $status ]->total : 0;
	}
	$results['payments']['total_orders'] = $total_orders;
	$assert( $total_orders > 0, 'No Sandbox order exists for payment evidence.' );
	$assert( $results['payments']['refunded_orders'] > 0, 'No refunded Sandbox order exists for refund evidence.' );

	$refunded_orders = $wpdb->get_results( "SELECT id, user_id, membership_id FROM {$orders_table} WHERE status = 'refunded'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$recorded_refunds = 0;
	foreach ( $refunded_orders as $refunded_order ) {
		$result = get_pmpro_membership_order_meta( (int) $refunded_order->id, 'cywater_refund_entitlement_result', true );
		$assert( is_array( $result ) && ! empty( $result['status'] ), 'A refunded membership order has no entitlement result.' );
		++$recorded_refunds;
		if ( 'preserved_by_later_order' !== $result['status'] ) {
			$assert( ! pmpro_hasMembershipLevel( (int) $refunded_order->membership_id, (int) $refunded_order->user_id ), 'A fully refunded membership entitlement remains active.' );
		}
	}
	$results['payments']['refund_entitlements_verified'] = $recorded_refunds;

	require_once ABSPATH . 'wp-admin/includes/user.php';
	$assert( (bool) wp_delete_user( $created_user_id ), 'Temporary subscriber deletion failed.' );
	$assert( false === get_userdata( $created_user_id ), 'Deleted subscriber remained in the user table.' );
	$created_user_id = 0;

	$results['cleanup'] = 'pass';
	WP_CLI::line( wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	WP_CLI::success( 'CYWater staging lifecycle acceptance passed; all temporary records were removed.' );
} catch ( Throwable $error ) {
	WP_CLI::warning( $error->getMessage() );
	$failure_message = 'CYWater staging lifecycle acceptance failed; temporary records were cleaned.';
} finally {
	foreach ( $created_post_ids as $post_id ) {
		if ( get_post( $post_id ) ) {
			wp_delete_post( $post_id, true );
		}
	}
	if ( $created_user_id && get_userdata( $created_user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $created_user_id );
	}
}

if ( $failure_message ) {
	WP_CLI::error( $failure_message );
}
