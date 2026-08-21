<?php
/**
 * Create or repair the PMPro Stripe Live webhook from Hostinger staging.
 *
 * This intentionally leaves the saved PMPro gateway environment unchanged.
 * It prints only booleans and never prints account IDs, keys, endpoint IDs,
 * provider errors, or webhook secrets.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$site_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
if ( 'staging.cywater.org' !== $site_host ) {
	WP_CLI::error( 'Refusing to run: this command is restricted to staging.cywater.org.' );
}

if ( ! class_exists( 'PMProGateway_stripe' ) ) {
	WP_CLI::error( 'Paid Memberships Pro Stripe gateway is unavailable.' );
}

if ( ! PMProGateway_stripe::has_connect_credentials( 'live' ) ) {
	WP_CLI::error( 'Stripe Live Connect is not configured.' );
}

$saved_environment = get_option( 'pmpro_gateway_environment', 'unset' );
$force_live        = static function () {
	return 'live';
};

// Select Live credentials for this process only. No WordPress option changes.
add_filter( 'pre_option_pmpro_gateway_environment', $force_live, PHP_INT_MAX );

$created = false;
try {
	$gateway = new PMProGateway_stripe();
	$result  = $gateway->update_webhook_events();
	$created = ! empty( $result ) && ! is_wp_error( $result );
} catch ( Throwable $error ) {
	WP_CLI::warning( 'Live webhook creation failed without exposing the provider response.' );
}

remove_filter( 'pre_option_pmpro_gateway_environment', $force_live, PHP_INT_MAX );

WP_CLI::line(
	wp_json_encode(
		array(
			'live_webhook_created_or_repaired' => $created,
			'saved_environment_unchanged'      => $saved_environment === get_option( 'pmpro_gateway_environment', 'unset' ),
			'saved_environment'                => get_option( 'pmpro_gateway_environment', 'unset' ),
			'payment_mode'                     => defined( 'CYWATER_PAYMENT_MODE' ) ? CYWATER_PAYMENT_MODE : 'undefined',
			'live_gate_open'                   => defined( 'CYWATER_ALLOW_LIVE_PAYMENTS' ) && (bool) CYWATER_ALLOW_LIVE_PAYMENTS,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	)
);

if ( ! $created ) {
	WP_CLI::halt( 2 );
}
