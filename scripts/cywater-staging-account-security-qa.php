<?php
/**
 * Staging-only, self-cleaning account verification and closure-request QA.
 *
 * Run with: wp eval-file /path/to/cywater-staging-account-security-qa.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
if ( 'staging.cywater.org' !== $site_host ) {
	WP_CLI::error( 'Refusing to run: this account-security test is restricted to staging.cywater.org.' );
}
if ( ! class_exists( 'CYWater_Membership_Account_Security' ) ) {
	WP_CLI::error( 'CYWater Membership account security is unavailable.' );
}

if ( '1' === getenv( 'CYWATER_ACCOUNT_QA_REAL_DELIVERY' ) ) {
	$delivery_username = 'cywater_delivery_qa_' . gmdate( 'YmdHis' );
	$delivery_user_id  = wp_create_user( $delivery_username, wp_generate_password( 24, true, true ), 'contact@cywater.org' );
	if ( is_wp_error( $delivery_user_id ) ) {
		WP_CLI::error( 'Could not create the disposable delivery account.' );
	}
	$delivery_sent = CYWater_Membership_Account_Security::issue_verification( $delivery_user_id, home_url( '/account/' ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $delivery_user_id );
	if ( ! $delivery_sent ) {
		WP_CLI::error( 'The real verification message was not accepted by the WordPress mail transport.' );
	}
	WP_CLI::success( 'A real verification message was handed to the configured transport; the disposable account was removed and its link is intentionally unusable.' );
	return;
}

$captured_mail = array();
$mail_filter   = static function ( $return, $atts ) use ( &$captured_mail ) {
	$captured_mail[] = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $mail_filter, 10, 2 );

$username = 'cywater_account_qa_' . gmdate( 'YmdHis' );
$email    = $username . '@example.org';
$user_id  = 0;
$checks   = array();

$assert = static function ( $condition, $label ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
	$checks[] = $label;
};

$extract_verification = static function ( $mail ) {
	$message = (string) ( $mail['message'] ?? '' );
	if ( ! preg_match( '#https://[^\s]+/verify-email/\?[^\s]+#', $message, $match ) ) {
		throw new RuntimeException( 'Verification link was not present in the intercepted message.' );
	}
	$query = array();
	parse_str( (string) wp_parse_url( html_entity_decode( $match[0] ), PHP_URL_QUERY ), $query );
	return $query;
};

try {
	$user_id = wp_create_user( $username, wp_generate_password( 24, true, true ), $email );
	$assert( ! is_wp_error( $user_id ), 'Disposable Subscriber created' );
	wp_update_user( array( 'ID' => $user_id, 'role' => 'subscriber' ) );
	$assert( ! CYWater_Membership_Account_Security::is_verified( $user_id ), 'New account starts unverified' );

	$sent = CYWater_Membership_Account_Security::issue_verification( $user_id, home_url( '/membership-checkout/' ) );
	$assert( $sent && 1 === count( $captured_mail ), 'Verification mail handed to WordPress mail transport' );
	$headers = (array) ( $captured_mail[0]['headers'] ?? array() );
	$assert( in_array( 'From: CYWater Accounts <accounts@cywater.org>', $headers, true ), 'Verification sender is accounts@cywater.org' );
	$assert( in_array( 'Reply-To: CYWater Membership <membership@cywater.org>', $headers, true ), 'Verification replies route to membership@cywater.org' );

	$query = $extract_verification( $captured_mail[0] );
	$assert( (int) ( $query['user_id'] ?? 0 ) === (int) $user_id && ! empty( $query['token'] ), 'Verification link identifies the disposable account without exposing a stored token' );
	$assert( CYWater_Membership_Account_Security::verify_token( $user_id, $query['token'] ), 'One-time verification token accepted' );
	$assert( CYWater_Membership_Account_Security::is_verified( $user_id ), 'Verified email matches the current account email' );
	$assert( ! CYWater_Membership_Account_Security::verify_token( $user_id, $query['token'] ), 'Verification token replay rejected' );

	wp_update_user( array( 'ID' => $user_id, 'user_email' => $username . '@example.net' ) );
	$assert( ! CYWater_Membership_Account_Security::is_verified( $user_id ), 'Email change invalidates prior verification' );
	$assert( count( $captured_mail ) >= 2, 'Email change issues a fresh verification message' );
	$new_query = $extract_verification( $captured_mail[1] );
	$assert( CYWater_Membership_Account_Security::verify_token( $user_id, $new_query['token'] ), 'Replacement address can be verified' );
	$assert( CYWater_Membership_Account_Security::issue_verification( $user_id, home_url( '/account/' ) ), 'Verification hourly allowance accepts message three' );
	$assert( CYWater_Membership_Account_Security::issue_verification( $user_id, home_url( '/account/' ) ), 'Verification hourly allowance accepts message four' );
	$assert( CYWater_Membership_Account_Security::issue_verification( $user_id, home_url( '/account/' ) ), 'Verification hourly allowance accepts message five' );
	$assert( CYWater_Membership_Account_Security::verification_rate_limited( $user_id ), 'Verification messages are limited to five per hour' );
	$assert( ! CYWater_Membership_Account_Security::issue_verification( $user_id, home_url( '/account/' ) ), 'Verification message six is rejected' );

	$assert( CYWater_Membership_Account_Security::request_closure( $user_id ), 'Account closure request recorded' );
	$assert( CYWater_Membership_Account_Security::closure_requested_at( $user_id ) > 0, 'Administrator-visible closure timestamp exists' );
	$assert( 'cooling_off' === CYWater_Membership_Account_Security::closure_status( $user_id ), 'Closure request enters the seven-day cooling-off period' );
	$assert( CYWater_Membership_Account_Security::closure_deadline( $user_id ) > time() + ( 6 * DAY_IN_SECONDS ), 'Closure review date is seven days after the request' );
	$assert( 'closure_requested' === CYWater_Membership_Account_Security::checkout_block_reason( $user_id ), 'Open closure request blocks new membership checkout' );
	update_user_meta( $user_id, 'cyw_account_closure_requested_at', time() - ( 8 * DAY_IN_SECONDS ) );
	$assert( 'review_due' === CYWater_Membership_Account_Security::closure_status( $user_id ), 'Expired cooling-off period enters administrator review' );
	CYWater_Membership_Account_Security::withdraw_closure( $user_id );
	$assert( 0 === CYWater_Membership_Account_Security::closure_requested_at( $user_id ), 'Closure request can be withdrawn' );
	$assert( '' === CYWater_Membership_Account_Security::checkout_block_reason( $user_id ), 'Withdrawing closure restores checkout eligibility' );

	CYWater_Membership_Account_Security::record_last_login( $username, get_user_by( 'id', $user_id ) );
	$assert( CYWater_Membership_Account_Security::last_login_at( $user_id ) > 0, 'Last sign-in timestamp recorded' );

	$sessions = WP_Session_Tokens::get_instance( $user_id );
	$sessions->create( time() + HOUR_IN_SECONDS );
	$sessions->create( time() + HOUR_IN_SECONDS );
	$assert( count( $sessions->get_all() ) >= 2, 'Disposable account has multiple test sessions' );
	$assert( CYWater_Membership_Account_Security::revoke_all_sessions( $user_id ), 'Administrator session revocation succeeds' );
	$assert( 0 === count( $sessions->get_all() ), 'All disposable-account sessions are revoked' );

	update_user_meta( $user_id, 'cyw_institution_name', 'CYWater QA' );
	update_user_meta( $user_id, 'cyw_profile_public', 1 );
	update_user_meta( $user_id, 'cyw_public_fields', array( 'cyw_institution_name' ) );
	$export = CYWater_Membership_Privacy::export_personal_data( get_userdata( $user_id )->user_email, 1 );
	$assert( ! empty( $export['data'][0]['data'] ), 'WordPress privacy export includes CYWater member data' );
	$erase = CYWater_Membership_Privacy::erase_personal_data( get_userdata( $user_id )->user_email, 1 );
	$assert( ! empty( $erase['items_removed'] ) && ! empty( $erase['items_retained'] ), 'Privacy eraser removes custom profile data while retaining reviewed records' );
	$assert( '' === get_user_meta( $user_id, 'cyw_institution_name', true ), 'Privacy eraser removes the professional profile field' );

	$administrators = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$assert( ! empty( $administrators ), 'Administrator exists for record rendering' );
	wp_set_current_user( $administrators[0]->ID );
	ob_start();
	CYWater_Membership_Admin::render_user_record( get_user_by( 'id', $user_id ) );
	$record_html = ob_get_clean();
	$assert( false !== strpos( $record_html, 'Email ownership' ), 'Administrator record renders verification state' );
	$assert( false !== strpos( $record_html, 'Active membership' ), 'Administrator record renders membership summary' );
	$assert( false !== strpos( $record_html, 'Event registrations' ) && false !== strpos( $record_html, 'Account closure' ), 'Administrator record links event operations and closure review' );
	$columns = CYWater_Membership_Admin::add_user_columns( array( 'username' => 'Username' ) );
	$assert( isset( $columns['cywater_account'], $columns['cywater_membership'] ), 'Users list exposes account and membership columns' );
} catch ( Throwable $error ) {
	if ( $user_id && ! is_wp_error( $user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
	}
	remove_filter( 'pre_wp_mail', $mail_filter, 10 );
	WP_CLI::error( 'Account-security QA failed: ' . $error->getMessage() );
}

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $user_id );
remove_filter( 'pre_wp_mail', $mail_filter, 10 );
WP_CLI::success( sprintf( 'Account-security QA passed %d checks; intercepted mail was not sent and the disposable user was removed.', count( $checks ) ) );
