<?php
/**
 * Read-only production audit for the CYWater Forum identity/administration split.
 *
 * Run with:
 *   wp eval-file /absolute/path/to/cywater-production-forum-role-audit.php
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$audit_assertions = 0;
$audit_assert     = static function ( $condition, $message ) use ( &$audit_assertions ) {
	++$audit_assertions;
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
};

$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/cywater-forum/cywater-forum.php', false, false );
$theme       = wp_get_theme( 'cywater' );
$audit_assert( '0.6.3' === (string) ( $plugin_data['Version'] ?? '' ), 'Unexpected CYWater Forum production version.' );
$audit_assert( '0.6.51' === (string) $theme->get( 'Version' ), 'Unexpected CYWater theme production version.' );
$audit_assert( 'live' === (string) get_option( 'pmpro_gateway_environment' ), 'Production payment gateway is not Live.' );
$audit_assert( 'cywater.org' === strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ), 'Refusing to audit a non-production host.' );
$audit_assert( class_exists( 'CYWater_Forum_Roles' ) && class_exists( 'CYWater_Forum_Workspace' ), 'Forum role or workspace service is unavailable.' );
$audit_assert( function_exists( 'cywater_forum_hero_actions' ), 'Forum public action renderer is unavailable.' );

$theme_php_files = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( get_theme_root() . '/cywater', FilesystemIterator::SKIP_DOTS )
);
foreach ( $theme_php_files as $theme_file ) {
	if ( ! $theme_file instanceof SplFileInfo || 'php' !== strtolower( $theme_file->getExtension() ) ) {
		continue;
	}
	$contents = file_get_contents( $theme_file->getPathname() );
	$audit_assert( false === strpos( $contents, 'edit_post_link(' ), 'A public theme template still contains edit_post_link().' );
	$audit_assert( false === strpos( $contents, 'Manage Forum' ), 'A public theme template still contains a Forum administration shortcut.' );
}

$staff_count    = 0;
$eligible_count = 0;
$moderator_count = 0;
$eligible_moderator_count = 0;
$blocker_counts = array();
$ineligible_moderators_with_success_orders = 0;
$users          = get_users( array( 'fields' => 'all' ) );
foreach ( $users as $user ) {
	if ( ! $user instanceof WP_User || ! CYWater_Forum_Roles::is_staff( $user->ID ) ) {
		continue;
	}
	++$staff_count;
	$is_moderator = in_array( 'cywater_community_moderator', (array) $user->roles, true );
	if ( $is_moderator ) {
		++$moderator_count;
	}
	wp_set_current_user( $user->ID );
	$blockers = CYWater_Forum_Roles::submission_blockers( $user->ID );
	$eligible = array() === $blockers;
	$actions  = cywater_forum_hero_actions();
	$audit_assert( false === strpos( $actions, 'Manage Forum' ), 'A staff account received a public Forum management shortcut.' );
	$audit_assert( false === apply_filters( 'show_admin_bar', true ), 'A staff account received the WordPress admin bar on the public site.' );
	$audit_assert( user_can( $user->ID, 'edit_others_cyw_forum_posts' ), 'A Forum staff account lost cross-author backend editing.' );
	$audit_assert( user_can( $user->ID, 'moderate_comments' ), 'A Forum staff account lost backend reply moderation.' );
	if ( $eligible ) {
		++$eligible_count;
		if ( $is_moderator ) {
			++$eligible_moderator_count;
		}
		$audit_assert( false !== strpos( $actions, 'Write a Forum post' ), 'An eligible dual-role account did not receive the ordinary member posting action.' );
	} else {
		foreach ( $blockers as $blocker ) {
			$blocker_counts[ $blocker ] = ( $blocker_counts[ $blocker ] ?? 0 ) + 1;
		}
		if ( $is_moderator ) {
			global $wpdb;
			$successful_orders = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}pmpro_membership_orders WHERE user_id = %d AND status = 'success'",
					$user->ID
				)
			);
			if ( $successful_orders > 0 ) {
				++$ineligible_moderators_with_success_orders;
			}
		}
		$audit_assert( false === strpos( $actions, 'Write a Forum post' ), 'An ineligible staff account received personal front-end publishing.' );
	}
}
wp_set_current_user( 0 );

$audit_assert( $staff_count > 0, 'No Forum staff accounts were available for the production audit.' );
WP_CLI::success(
	sprintf(
		'CYWater production Forum role audit passed %1$d assertions across %2$d staff accounts (%3$d personally eligible). No data was changed.',
		$audit_assertions,
		$staff_count,
		$eligible_count
	)
);
WP_CLI::log(
	sprintf(
		'Moderator accounts: %1$d total, %2$d personally eligible. Ineligible staff blockers: %3$s. Ineligible moderators with historical successful orders: %4$d.',
		$moderator_count,
		$eligible_moderator_count,
		$blocker_counts ? wp_json_encode( $blocker_counts ) : 'none',
		$ineligible_moderators_with_success_orders
	)
);
