<?php
/**
 * Read-only production purity gate.
 *
 * Run only after restoring the accepted staging backup into the production
 * target and before DNS cutover:
 *   wp eval-file scripts/cywater-production-purity-audit.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;

$checks   = 0;
$failures = array();
$check    = static function ( $condition, $message ) use ( &$checks, &$failures ) {
	++$checks;
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$check( 'production' === wp_get_environment_type(), 'WP_ENVIRONMENT_TYPE is not production.' );
$check( is_plugin_active( 'cywater-logo-call/cywater-logo-call.php' ), 'CYWater Logo Call is not active.' );
$published_logo_events = (int) $wpdb->get_var(
	"SELECT COUNT(*)
	 FROM {$wpdb->posts} p
	 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
	 WHERE p.post_type = 'cyw_event'
	   AND p.post_status = 'publish'
	   AND p.post_name = 'logo-design-call-2026'
	   AND pm.meta_key = '_cywater_logo_call_enabled'
	   AND pm.meta_value = '1'"
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed tables and fixed marker literals.
$check( 1 === $published_logo_events, 'The accepted 2026 Logo Call Event is missing, duplicated, or disabled.' );

$logo_event_id = (int) $wpdb->get_var(
	"SELECT ID FROM {$wpdb->posts}
	 WHERE post_type = 'cyw_event'
	   AND post_status = 'publish'
	   AND post_name = 'logo-design-call-2026'
	 LIMIT 1"
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table and fixed marker literals.
$logo_expectations = array(
	'_cywater_logo_call_open_at'         => '2026-08-12 00:00',
	'_cywater_logo_call_close_at'        => '2026-09-30 23:59',
	'_cywater_logo_call_vote_open'       => '',
	'_cywater_logo_call_vote_close'      => '',
	'_cywater_logo_call_submit_audience' => 'registered',
	'_cywater_logo_call_vote_audience'   => 'registered',
	'_cywater_logo_call_reward'          => 'CYWater Lifetime membership (final term subject to Board confirmation)',
);
foreach ( $logo_expectations as $meta_key => $expected_value ) {
	$actual_value = $logo_event_id ? (string) get_post_meta( $logo_event_id, $meta_key, true ) : '';
	$check( $expected_value === $actual_value, 'The accepted Logo Call setting is missing or changed: ' . $meta_key );
}

$logo_entries = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts}
	 WHERE post_type = 'cyw_logo_entry'
	   AND post_status NOT IN ('auto-draft', 'trash')"
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table and post type.
$check( 0 === $logo_entries, 'Staging Logo Call submissions remain in production.' );

$logo_votes = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->usermeta}
	 WHERE meta_key LIKE '_cywater_logo_vote_%'"
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table and marker prefix.
$check( 0 === $logo_votes, 'Staging Logo Call votes remain in production.' );
$check( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG, 'WP_DEBUG is enabled.' );
$check( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG, 'SCRIPT_DEBUG is enabled.' );
$check( '1' === (string) get_option( 'blog_public' ), 'Search-engine visibility is disabled.' );

$home_url = strtolower( (string) get_option( 'home' ) );
$site_url = strtolower( (string) get_option( 'siteurl' ) );
$check( false === strpos( $home_url, 'staging.' ) && false === strpos( $site_url, 'staging.' ), 'Home or Site URL still points to staging.' );

$qa_users = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->users}
	 WHERE user_email LIKE '%@example.invalid'
	    OR user_login LIKE 'cyw\\_%\\_qa%'
	    OR user_login LIKE 'cywater\\_test\\_%'
	    OR user_login LIKE '%staging.cywater.org%'
	    OR user_email LIKE '%staging.cywater.org%'"
); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table and fixed marker literals.
$check( 0 === $qa_users, 'QA or staging-identity users remain.' );

$qa_posts = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts}
	 WHERE post_status NOT IN ('auto-draft', 'inherit')
	   AND (
	     post_title LIKE 'CYWater%QA%'
	     OR post_title LIKE '%placeholder%'
	     OR post_title LIKE '%staging preview%'
	     OR post_title LIKE '% test%'
	   )"
); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table and fixed marker literals.
$check( 0 === $qa_posts, 'QA, test, or placeholder posts remain.' );

$staging_links = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts}
	 WHERE post_status NOT IN ('auto-draft', 'inherit', 'trash')
	   AND (post_content LIKE '%staging.cywater.org%' OR post_excerpt LIKE '%staging.cywater.org%')"
); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table and fixed marker literal.
$check( 0 === $staging_links, 'Published or retained content still references staging.cywater.org.' );

$draft_policy_copy = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts}
	 WHERE post_status = 'publish'
	   AND post_content LIKE '%Draft for Board Review%Not approved or in effect%'"
); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table and fixed review banner.
$check( 0 === $draft_policy_copy, 'Published policy pages still carry the Board-review draft banner.' );

$levels_table      = $wpdb->prefix . 'pmpro_membership_levels';
$memberships_table = $wpdb->prefix . 'pmpro_memberships_users';
$orders_table      = $wpdb->prefix . 'pmpro_membership_orders';
$sandbox_level_id  = (int) $wpdb->get_var( "SELECT id FROM {$levels_table} WHERE name = 'Sandbox Payment Test' LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$configured_levels = (array) get_option( 'cywater_membership_level_ids', array() );
$check( 0 === $sandbox_level_id && empty( $configured_levels['sandbox_test'] ), 'Sandbox Payment Test level or option remains.' );

if ( $sandbox_level_id ) {
	$active_test_memberships = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$memberships_table} WHERE membership_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sandbox_level_id
		)
	);
	$check( 0 === $active_test_memberships, 'An active Sandbox Payment Test entitlement remains.' );
}

$environment_column = $wpdb->get_var( "SHOW COLUMNS FROM {$orders_table} LIKE 'gateway_environment'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
if ( $environment_column ) {
	$sandbox_orders = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$orders_table} WHERE gateway_environment IN ('sandbox', 'test')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$check( 0 === $sandbox_orders, 'Sandbox/test PMPro orders remain in the production database.' );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		WP_CLI::warning( $failure );
	}
	WP_CLI::error( sprintf( 'Production purity audit failed %1$d of %2$d checks. No data was changed.', count( $failures ), $checks ) );
}

WP_CLI::success( sprintf( 'Production purity audit passed %d read-only checks. No data was changed.', $checks ) );
