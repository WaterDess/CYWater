<?php
/**
 * Staging-only PMPro receipt/invoice-surface acceptance.
 *
 * This verifies the Sandbox document surface without creating an order,
 * charging a payment method, or printing member/payment identifiers.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
if ( 'staging.cywater.org' !== $site_host ) {
	WP_CLI::error( 'Refusing to run: this invoice test is restricted to staging.cywater.org.' );
}
if ( ! class_exists( 'MemberOrder' ) || ! function_exists( 'pmpro_url' ) ) {
	WP_CLI::error( 'PMPro order and invoice APIs are unavailable.' );
}

$checks = array();
$assert = static function ( $condition, $label ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
	$checks[] = $label;
};

try {
	$page_id = absint( get_option( 'pmpro_invoice_page_id' ) );
	$page    = $page_id ? get_post( $page_id ) : null;
	$assert( $page instanceof WP_Post && 'publish' === $page->post_status, 'Membership order page is published' );
	$assert( 1 === substr_count( (string) $page->post_content, '[pmpro_invoice]' ), 'Membership order page contains one PMPro invoice shortcode' );
	$assert( 'sandbox' === get_option( 'pmpro_gateway_environment' ), 'PMPro remains in Stripe Sandbox mode' );
	$assert( false !== strpos( pmpro_url( 'invoice' ), '/membership-order/' ), 'PMPro invoice route resolves to the CYWater membership order page' );

	global $wpdb;
	$table       = $wpdb->pmpro_membership_orders;
	$order_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$assert( $order_count > 0, 'Staging contains PMPro order evidence for receipt rendering' );
	$document_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('success','refunded')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$assert( $document_count > 0, 'At least one completed or refunded Sandbox order can render a receipt' );
} catch ( Throwable $error ) {
	WP_CLI::error( 'Invoice QA failed: ' . $error->getMessage() );
}

WP_CLI::success( sprintf( 'Invoice QA passed %d checks without creating an order or exposing member/payment identifiers.', count( $checks ) ) );
