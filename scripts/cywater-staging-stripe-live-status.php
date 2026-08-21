<?php
/**
 * Read-only staging probe for PMPro Stripe Live account and webhook readiness.
 *
 * Run with WP-CLI from the staging WordPress root. The probe never prints
 * account identifiers, API keys, webhook identifiers, or webhook secrets.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$site_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
if ( 'staging.cywater.org' !== $site_host ) {
	WP_CLI::error( 'Refusing to run: this probe is restricted to staging.cywater.org.' );
}

if ( ! class_exists( 'PMProGateway_stripe' ) ) {
	WP_CLI::error( 'Paid Memberships Pro Stripe gateway is unavailable.' );
}

$live_credentials_present = PMProGateway_stripe::has_connect_credentials( 'live' );
$saved_environment         = get_option( 'pmpro_gateway_environment', 'unset' );

// Select Live credentials only for this in-memory read. No option is updated.
$force_live = static function () {
	return 'live';
};
add_filter( 'pre_option_pmpro_gateway_environment', $force_live, PHP_INT_MAX );

$webhook = false;
$account = false;
if ( $live_credentials_present ) {
	try {
		$gateway = new PMProGateway_stripe();

		$webhook_method = new ReflectionMethod( PMProGateway_stripe::class, 'does_webhook_exist' );
		$webhook_method->setAccessible( true );
		$webhook = $webhook_method->invoke( $gateway );

		$account_method = new ReflectionMethod( PMProGateway_stripe::class, 'get_account' );
		$account_method->setAccessible( true );
		$account = $account_method->invoke( $gateway );
	} catch ( Throwable $error ) {
		WP_CLI::warning( 'Live readiness lookup failed without exposing the provider response.' );
	}
}

remove_filter( 'pre_option_pmpro_gateway_environment', $force_live, PHP_INT_MAX );

$enabled_events = is_array( $webhook ) && isset( $webhook['enabled_events'] ) && is_array( $webhook['enabled_events'] )
	? $webhook['enabled_events']
	: array();

$required_events = array(
	'invoice.created',
	'invoice.upcoming',
	'invoice.payment_succeeded',
	'invoice.payment_action_required',
	'customer.subscription.deleted',
	'charge.failed',
	'charge.refunded',
	'checkout.session.completed',
	'checkout.session.async_payment_succeeded',
	'checkout.session.async_payment_failed',
);

$missing_events = array_values( array_diff( $required_events, $enabled_events ) );
$requirements   = is_object( $account ) && isset( $account->requirements )
	? $account->requirements
	: null;
$currently_due  = is_object( $requirements ) && isset( $requirements->currently_due ) && is_array( $requirements->currently_due )
	? $requirements->currently_due
	: array();
$status         = array(
	'live_connect_present'    => (bool) $live_credentials_present,
	'live_account_reachable'  => is_object( $account ),
	'live_charges_enabled'    => is_object( $account ) && ! empty( $account->charges_enabled ),
	'live_payouts_enabled'    => is_object( $account ) && ! empty( $account->payouts_enabled ),
	'live_details_submitted'  => is_object( $account ) && ! empty( $account->details_submitted ),
	'live_requirements_clear' => is_object( $account ) && empty( $currently_due ),
	'live_webhook_present'    => is_array( $webhook ),
	'live_webhook_enabled'    => is_array( $webhook ) && 'enabled' === ( $webhook['status'] ?? '' ),
	'live_webhook_api_current' => is_array( $webhook ) && defined( 'PMPRO_STRIPE_API_VERSION' ) && PMPRO_STRIPE_API_VERSION === ( $webhook['api_version'] ?? '' ),
	'live_webhook_events_ok'  => is_array( $webhook ) && empty( $missing_events ),
	'saved_environment'       => $saved_environment,
	'payment_mode'            => defined( 'CYWATER_PAYMENT_MODE' ) ? CYWATER_PAYMENT_MODE : 'undefined',
	'live_gate_open'          => defined( 'CYWATER_ALLOW_LIVE_PAYMENTS' ) && (bool) CYWATER_ALLOW_LIVE_PAYMENTS,
);

WP_CLI::line( wp_json_encode( $status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
