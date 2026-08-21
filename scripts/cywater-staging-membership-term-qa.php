<?php
/**
 * Self-cleaning staging QA for rolling annual CYWater membership terms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

if ( wp_get_environment_type() !== 'staging' ) {
	fwrite( STDERR, "This QA may run only in staging.\n" );
	exit( 1 );
}

$failures = array();
$checks   = 0;

$check = static function ( $condition, $message ) use ( &$failures, &$checks ) {
	++$checks;
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$ids          = (array) get_option( 'cywater_membership_level_ids', array() );
$student_id   = absint( $ids['student'] ?? 0 );
$professional = absint( $ids['professional'] ?? 0 );
$lifetime     = absint( $ids['lifetime'] ?? 0 );
$sandbox_test = absint( $ids['sandbox_test'] ?? 0 );

$check( $student_id > 0 && $professional > 0 && $lifetime > 0 && $sandbox_test > 0, 'Expected CYWater membership and Sandbox test levels were not found.' );
$check( 'test' === CYWater_Config::payment_mode(), 'Staging payment mode is not test.' );
$check( 'sandbox' === get_option( 'pmpro_gateway_environment' ), 'PMPro checkout is not isolated to Stripe Sandbox.' );
$check( 'USD' === get_option( 'pmpro_currency' ), 'PMPro currency is not USD.' );
$check( 'checkout' === get_option( 'pmpro_stripe_payment_flow' ), 'PMPro is not using hosted Stripe Checkout.' );
$check( class_exists( 'PMProGateway_stripe' ) && PMProGateway_stripe::has_connect_credentials( 'sandbox' ), 'Stripe Sandbox Connect is unavailable.' );
$check( false !== has_action( 'pmpro_order_status_refunded', array( 'CYWater_Membership_Refunds', 'revoke_refunded_membership' ) ), 'The full-refund entitlement handler is not registered.' );
$check( class_exists( 'CYWater_Membership_Email_Routing' ), 'Role-based transactional email routing is unavailable.' );
$check( false !== has_filter( 'pmpro_email_sender', array( 'CYWater_Membership_Email_Routing', 'pmpro_sender' ) ), 'PMPro sender routing is not registered.' );
$check( false !== has_filter( 'retrieve_password_notification_email', array( 'CYWater_Membership_Email_Routing', 'account_notification' ) ), 'Account-recovery sender routing is not registered.' );
$check( 'membership@cywater.org' === get_option( 'pmpro_from_email' ), 'PMPro fallback sender is not membership@cywater.org.' );
$check( 'CYWater Membership' === get_option( 'pmpro_from_name' ), 'PMPro fallback sender name is not CYWater Membership.' );
$check( (bool) get_option( 'pmpro_only_filter_pmpro_emails' ), 'PMPro is overriding unrelated WordPress email identities.' );

$login_page_id      = absint( get_option( 'pmpro_login_page_id' ) );
$login_page_content = $login_page_id ? (string) get_post_field( 'post_content', $login_page_id ) : '';
$check( 1 === substr_count( $login_page_content, '[cywater_member_login]' ), 'The managed login page does not contain exactly one CYWater login wrapper.' );
$check( 0 === substr_count( $login_page_content, '[pmpro_login]' ), 'The superseded PMPro login shortcode still duplicates the managed login form.' );
$login_migration = CYWater_Membership_Setup::reconcile_managed_shortcodes(
	'[pmpro_login]<p>Retained copy.</p>[cywater_member_login][cywater_member_login]',
	array( 'cywater_member_login' ),
	array( 'pmpro_login' )
);
$check( '<p>Retained copy.</p>[cywater_member_login]' === $login_migration, 'Login-page migration is not idempotent or did not preserve editorial copy.' );

$countries = CYWater_Membership_Countries::options();
$check( 'China (Chinese mainland)' === ( $countries['CN'] ?? '' ), 'The Chinese mainland label is not canonical.' );
$check( 'Hong Kong SAR, China' === ( $countries['HK'] ?? '' ), 'The Hong Kong SAR label is not canonical.' );
$check( 'Macao SAR, China' === ( $countries['MO'] ?? '' ), 'The Macao SAR label is not canonical.' );
$check( 'Taiwan, China' === ( $countries['TW'] ?? '' ), 'The Taiwan label is not canonical.' );

$billing_email       = (object) array( 'template' => 'checkout_paid' );
$membership_email    = (object) array( 'template' => 'membership_expiring' );
$unknown_email       = (object) array( 'template' => 'future_addon_template' );
$billing_identity    = CYWater_Membership_Email_Routing::pmpro_identity( $billing_email );
$membership_identity = CYWater_Membership_Email_Routing::pmpro_identity( $membership_email );
$unknown_identity    = CYWater_Membership_Email_Routing::pmpro_identity( $unknown_email );
$check( 'billing' === $billing_identity['role'] && 'billing@cywater.org' === $billing_identity['email'], 'Paid checkout is not routed to Billing.' );
$check( 'membership' === $membership_identity['role'] && 'membership@cywater.org' === $membership_identity['email'], 'Membership lifecycle mail is not routed to Membership.' );
$check( 'membership' === $unknown_identity['role'], 'Unknown PMPro templates do not fail to the Membership identity.' );
$billing_headers = CYWater_Membership_Email_Routing::pmpro_headers( array( 'Content-Type: text/html', 'Reply-To: stale@example.invalid' ), $billing_email );
$check( 1 === count( preg_grep( '/^Reply-To:/i', $billing_headers ) ) && in_array( 'Reply-To: CYWater Billing <billing@cywater.org>', $billing_headers, true ), 'PMPro Billing Reply-To is not deterministic.' );
$account_mail = CYWater_Membership_Email_Routing::account_notification( array( 'headers' => array( 'Content-Type: text/plain', 'From: stale@example.invalid' ) ) );
$check( in_array( 'From: CYWater Accounts <accounts@cywater.org>', $account_mail['headers'], true ) && in_array( 'Reply-To: CYWater Membership <membership@cywater.org>', $account_mail['headers'], true ), 'Native account-security mail identity is incorrect.' );
$membership_data = CYWater_Membership_Email_Routing::pmpro_data( array( 'siteemail' => 'stale@example.invalid' ), $membership_email );
$check( 'membership@cywater.org' === $membership_data['siteemail'] && 'membership@cywater.org' === $membership_data['pmpro_from_email'], 'PMPro body variables do not match the Membership sender.' );
$check( 20.0 === (float) pmpro_getLevel( $student_id )->initial_payment, 'Student price is not $20.' );
$check( 50.0 === (float) pmpro_getLevel( $professional )->initial_payment, 'Professional price is not $50.' );
$check( 700.0 === (float) pmpro_getLevel( $lifetime )->initial_payment, 'Lifetime price is not $700.' );

foreach ( array( $student_id, $professional ) as $level_id ) {
	$level = pmpro_getLevel( $level_id );
	$check( (float) $level->billing_amount === 0.0, 'Annual level must not create a recurring charge.' );
	$check( (int) $level->expiration_number === 1 && $level->expiration_period === 'Year', 'Annual level must be configured for one year.' );
}

$student_level = pmpro_getLevel( $student_id );
$expected      = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+1 year' )->format( 'Y-m-d' );
$start         = CYWater_Membership_Setup::rolling_annual_start( "'1999-01-01 00:00:00'", 0, $student_level );
$actual        = CYWater_Membership_Setup::rolling_annual_end( '2000-12-31 23:59:59', 0, $student_level, "'1999-01-01 00:00:00'" );
$check( trim( $start, "'" ) === current_time( 'mysql' ), 'Annual checkout did not start on the payment date.' );
$check( substr( $actual, 0, 10 ) === $expected, 'Annual checkout did not end one year after the payment date.' );
$check( substr( $actual, 11 ) === '23:59:59', 'Annual checkout did not end at the end of its final day.' );

$lifetime_level = pmpro_getLevel( $lifetime );
$unchanged      = CYWater_Membership_Setup::rolling_annual_end( 'NULL', 0, $lifetime_level, "'1999-01-01 00:00:00'" );
$check( $unchanged === 'NULL', 'Lifetime expiration was changed.' );

$sandbox_level = pmpro_getLevel( $sandbox_test );
$check( 0.50 === (float) $sandbox_level->initial_payment, 'Sandbox checkout fixture is not the Stripe USD minimum of $0.50.' );
$check( 0.0 === (float) $sandbox_level->billing_amount, 'Sandbox checkout fixture unexpectedly recurs.' );
$check( 1 === (int) $sandbox_level->expiration_number && 'Day' === $sandbox_level->expiration_period, 'Sandbox checkout fixture does not expire after one day.' );
$check( 1 === (int) $sandbox_level->allow_signups, 'Sandbox checkout fixture is not available for checkout.' );
$check( pmpro_get_group_id_for_level( $sandbox_test ) !== pmpro_get_group_id_for_level( $lifetime ), 'Sandbox checkout fixture can replace a real membership.' );
$sandbox_group = pmpro_get_level_group( pmpro_get_group_id_for_level( $sandbox_test ) );
$check( $sandbox_group && 'Staging payment QA' === $sandbox_group->name, 'Sandbox checkout fixture is not isolated in its dedicated PMPro group.' );

if ( $failures ) {
	echo wp_json_encode( array( 'status' => 'failed', 'checks' => $checks, 'failures' => $failures ), JSON_PRETTY_PRINT );
	exit( 1 );
}

echo wp_json_encode( array( 'status' => 'passed', 'checks' => $checks ), JSON_PRETTY_PRINT );
