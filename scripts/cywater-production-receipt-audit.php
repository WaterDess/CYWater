<?php
/**
 * Read-only production audit for the branded PMPro receipt surface.
 *
 * This script does not create an order, send email, or print member/payment
 * identifiers. It renders the newest completed order only in process memory.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
if ( 'cywater.org' !== $site_host ) {
	WP_CLI::error( 'Refusing to run: this receipt audit is restricted to cywater.org.' );
}
if ( ! class_exists( 'MemberOrder' ) || ! class_exists( 'CYWater_Membership_Receipt' ) || ! function_exists( 'pmpro_url' ) ) {
	WP_CLI::error( 'PMPro or the CYWater receipt model is unavailable.' );
}

$checks = array();
$assert = static function ( $condition, $label ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
	$checks[] = $label;
};

try {
	$assert( 'stripe' === get_option( 'pmpro_gateway' ), 'Production gateway remains Stripe' );
	$assert( 'live' === get_option( 'pmpro_gateway_environment' ), 'Production gateway remains Live' );
	$assert( 'USD' === get_option( 'pmpro_currency' ), 'Production membership prices remain USD' );
	$assert( 'yes' === get_option( 'pmpro_use_custom_page_template_invoice' ), 'CYWater receipt template is explicitly enabled' );
	$template_path = pmpro_get_template_path_to_load( 'invoice', 'local', 'pages' );
	$assert( false !== strpos( wp_normalize_path( (string) $template_path ), '/themes/cywater/paid-memberships-pro/pages/invoice.php' ), 'PMPro resolves the maintained CYWater receipt template' );

	global $wpdb;
	$table    = $wpdb->pmpro_membership_orders;
	$order_id = (int) $wpdb->get_var( "SELECT id FROM {$table} WHERE status IN ('success','refunded') ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$assert( $order_id > 0, 'A completed production order is available for read-only rendering' );
	$order = new MemberOrder();
	$order->getMemberOrderByID( $order_id );
	$model = CYWater_Membership_Receipt::view_model( $order );
	$user  = get_userdata( absint( $order->user_id ?? 0 ) );
	$assert( $user instanceof WP_User, 'Completed order resolves its registered member' );
	$identity = get_pmpro_membership_order_meta( $order_id, CYWater_Membership_Receipt::IDENTITY_META_KEY, true );
	$assert( is_array( $identity ) && 1 === absint( $identity['version'] ?? 0 ), 'Completed order has an immutable receipt identity snapshot' );
	$assert( ! empty( $identity['name'] ) && in_array( $identity['name'], $model['bill_to'], true ), 'Bill to contains the order-time member name' );
	$assert( ! empty( $identity['institution'] ) && in_array( $identity['institution'], $model['bill_to'], true ), 'Bill to contains the order-time institution or employer' );
	$assert( ! empty( $identity['email'] ) && in_array( $identity['email'], $model['bill_to'], true ), 'Bill to contains the order-time account email' );
	$assert( count( $model['bill_to'] ) >= 4, 'Bill to contains a complete member identity block' );

	$assert( 'International Association of Contemporary Young Scholars in Water Sciences' === $model['organization_name'], 'Receipt prints the association full name' );
	$assert( 'USD' === $model['currency'], 'Receipt states the actual production transaction currency' );
	$assert( false !== strpos( $model['total'], 'USD ' ), 'Receipt amounts use an unambiguous ISO currency code' );
	$assert( false !== strpos( $model['currency_note'], 'including CNY' ), 'Receipt explains issuer-side CNY conversion without inventing an exchange rate' );
	$assert( 'billing@cywater.org' === $model['organization_email'], 'Receipt routes financial questions to CYWater Billing' );
	$assert( ! empty( $model['order_number'] ) && ! empty( $model['membership_name'] ) && ! empty( $model['payment_method'] ), 'Receipt contains order, membership, and masked payment-method fields' );

	$email = (object) array(
		'template' => 'checkout_paid',
		'data'     => array( 'order_id' => $order->code ),
	);
	$email_subject = apply_filters( 'pmpro_email_subject', 'Default membership confirmation', $email );
	$email_body    = apply_filters( 'pmpro_email_body', '<p>Default membership confirmation</p>', $email );
	$assert( false !== strpos( $email_subject, 'membership payment receipt' ), 'Paid checkout sends one receipt-focused subject' );
	$assert( false !== strpos( $email_body, 'Membership Dues Receipt' ), 'Paid checkout email contains the compact receipt' );
	$assert( false !== strpos( $email_body, 'International Association of Contemporary Young Scholars in Water Sciences' ), 'Paid checkout email prints the association full name' );
	$assert( false !== strpos( $email_body, 'Transaction currency' ) && false !== strpos( $email_body, 'including CNY' ), 'Paid checkout email preserves currency guidance after sanitization' );
} catch ( Throwable $error ) {
	WP_CLI::error( 'Production receipt audit failed: ' . $error->getMessage() );
}

WP_CLI::success( sprintf( 'Production receipt audit passed %d read-only checks. No account, order, payment, email, or session data was changed.', count( $checks ) ) );
