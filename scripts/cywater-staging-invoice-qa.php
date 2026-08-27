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
	$assert( 'yes' === get_option( 'pmpro_use_custom_page_template_invoice' ), 'CYWater receipt template is explicitly enabled' );
	$template_path = pmpro_get_template_path_to_load( 'invoice', 'local', 'pages' );
	$assert( false !== strpos( wp_normalize_path( (string) $template_path ), '/themes/cywater/paid-memberships-pro/pages/invoice.php' ), 'PMPro resolves the maintained CYWater receipt template' );
	$assert( class_exists( 'CYWater_Membership_Receipt' ), 'CYWater receipt data model is available' );

	global $wpdb;
	$table       = $wpdb->pmpro_membership_orders;
	$order_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$assert( $order_count > 0, 'Staging contains PMPro order evidence for receipt rendering' );
	$document_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('success','refunded')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$assert( $document_count > 0, 'At least one completed or refunded Sandbox order can render a receipt' );
	$order_id = (int) $wpdb->get_var( "SELECT id FROM {$table} WHERE status IN ('success','refunded') ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$order    = new MemberOrder();
	$order->getMemberOrderByID( $order_id );
	$model    = CYWater_Membership_Receipt::view_model( $order );
	$assert( 'International Association of Contemporary Young Scholars in Water Sciences' === $model['organization_name'], 'Receipt prints the association full name' );
	$assert( 'USD' === $model['currency'], 'Receipt states the actual PMPro transaction currency' );
	$assert( false !== strpos( $model['total'], 'USD ' ), 'Receipt amounts use an unambiguous ISO currency code' );
	$assert( false !== strpos( $model['currency_note'], 'including CNY' ), 'Receipt explains issuer-side CNY conversion without inventing an exchange rate' );
	$assert( 'billing@cywater.org' === $model['organization_email'], 'Receipt routes financial questions to CYWater Billing' );
	$email         = (object) array(
		'template' => 'checkout_paid',
		'data'     => array( 'order_id' => $order->code ),
	);
	$email_subject = apply_filters( 'pmpro_email_subject', 'Default membership confirmation', $email );
	$email_body    = apply_filters( 'pmpro_email_body', '<p>Default membership confirmation</p>', $email );
	$assert( false !== strpos( $email_subject, 'membership payment receipt' ), 'Paid checkout sends one receipt-focused subject' );
	$assert( false !== strpos( $email_body, 'Membership Dues Receipt' ), 'Paid checkout email contains the compact receipt' );
	$assert( false !== strpos( $email_body, 'International Association of Contemporary Young Scholars in Water Sciences' ), 'Paid checkout email prints the association full name' );
	$assert( false !== strpos( $email_body, 'Transaction currency' ) && false !== strpos( $email_body, 'including CNY' ), 'Paid checkout email preserves the currency and issuer-conversion explanation after sanitization' );
} catch ( Throwable $error ) {
	WP_CLI::error( 'Invoice QA failed: ' . $error->getMessage() );
}

WP_CLI::success( sprintf( 'Invoice QA passed %d checks without creating an order or exposing member/payment identifiers.', count( $checks ) ) );
