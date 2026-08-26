<?php
/**
 * Read-only production audit for membership-aware header and Account actions.
 *
 * Run with: wp eval-file /absolute/path/to/cywater-production-membership-ui-audit.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$assertions = array();
$assert     = static function ( $condition, $message ) use ( &$assertions ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$assertions[] = $message;
};

try {
	$assert( 'production' === wp_get_environment_type(), 'WordPress environment is production' );
	$assert( 'cywater.org' === strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ), 'Home URL is the CYWater production host' );
	$assert( defined( 'CYWATER_MEMBERSHIP_VERSION' ) && '0.9.7' === CYWATER_MEMBERSHIP_VERSION, 'CYWater Membership version is 0.9.7' );
	$assert( '0.6.50' === wp_get_theme()->get( 'Version' ), 'CYWater theme version is 0.6.50' );
	$assert( class_exists( 'CYWater_Membership_Account_Routing' ), 'Membership account-routing authority is active' );
	$registration_page = get_page_by_path( 'member-register' );
	$assert( $registration_page instanceof WP_Post, 'Canonical member registration page exists' );
	$registration_content = $registration_page instanceof WP_Post ? (string) $registration_page->post_content : '';
	$assert( 1 === substr_count( $registration_content, '[cywater_member_register]' ), 'Canonical member registration page renders the shared form exactly once' );
	$assert( false === has_shortcode( $registration_content, 'pmpro_login' ) && false === has_shortcode( $registration_content, 'pmpro_checkout' ), 'Canonical member registration page contains no legacy or parallel account form' );
	$assert( ! (bool) get_option( 'users_can_register' ), 'WordPress core public registration remains disabled in favor of the managed CYWater form' );

	$original_user = get_current_user_id();
	wp_set_current_user( 0 );
	$guest_action = CYWater_Membership_Account_Routing::membership_action();
	$assert( 'Join CYWater' === (string) ( $guest_action['label'] ?? '' ), 'Signed-out action is Join CYWater' );

	$active_user   = 0;
	$inactive_user = 0;
	foreach ( get_users( array( 'fields' => 'ids' ) ) as $user_id ) {
		if ( CYWater_Membership_Account_Routing::has_active_individual_membership( $user_id ) ) {
			$active_user = $active_user ?: absint( $user_id );
		} else {
			$inactive_user = $inactive_user ?: absint( $user_id );
		}
		if ( $active_user && $inactive_user ) {
			break;
		}
	}
	$assert( $active_user > 0, 'At least one active individual member is available for a read-only projection' );
	$assert( $inactive_user > 0, 'At least one registered non-member is available for a read-only projection' );

	wp_set_current_user( $inactive_user );
	$inactive_action = CYWater_Membership_Account_Routing::membership_action();
	$assert( 'Choose Membership' === (string) ( $inactive_action['label'] ?? '' ), 'Registered non-member action is Choose Membership' );

	wp_set_current_user( $active_user );
	$active_action = CYWater_Membership_Account_Routing::membership_action();
	$assert( 'My Membership' === (string) ( $active_action['label'] ?? '' ), 'Active-member action is My Membership' );
	$assert( false !== strpos( (string) ( $active_action['url'] ?? '' ), '/account/#pmpro_account-membership' ), 'My Membership links to the Account membership section' );

	$account_html = do_shortcode( '[pmpro_account sections="membership"]' );
	$details_at   = strpos( $account_html, '<details class="cywater-membership-management">' );
	$assert( false !== $details_at, 'Account membership actions include the Manage membership disclosure' );
	$details_html = false === $details_at ? '' : substr( $account_html, $details_at );
	$assert( false !== strpos( $details_html, 'pmpro_actionlink-change-' ), 'Change membership is inside the disclosure' );
	$assert( false !== strpos( $details_html, 'pmpro_actionlink-cancel-' ), 'Cancel membership is inside the disclosure' );

	wp_set_current_user( $original_user );
	WP_CLI::success( 'CYWater production membership UI audit passed ' . count( $assertions ) . ' read-only assertions. No account, membership, order, payment, or session data was changed.' );
} catch ( Throwable $error ) {
	if ( isset( $original_user ) ) {
		wp_set_current_user( $original_user );
	}
	WP_CLI::error( 'Production membership UI audit failed: ' . $error->getMessage() );
}
