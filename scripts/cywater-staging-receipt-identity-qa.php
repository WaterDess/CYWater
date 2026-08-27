<?php
/**
 * Self-cleaning staging acceptance for immutable receipt identity snapshots.
 * Creates no charge, membership, subscription, email, or Stripe object.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
if ( 'staging.cywater.org' !== strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ) {
	WP_CLI::error( 'This QA is restricted to staging.cywater.org.' );
}
if ( ! class_exists( 'MemberOrder' ) || ! class_exists( 'CYWater_Membership_Receipt' ) ) {
	WP_CLI::error( 'Required receipt classes are unavailable.' );
}

$checks   = array();
$user_id  = 0;
$order    = null;
$order_id = 0;
$marker   = 'cyw_receipt_identity_' . gmdate( 'YmdHis' ) . '_' . wp_generate_password( 6, false, false );
$assert   = static function ( $condition, $label ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
	$checks[] = $label;
};

try {
	$user_id = wp_insert_user(
		array(
			'user_login'   => $marker,
			'user_pass'    => wp_generate_password( 28, true, true ),
			'user_email'   => $marker . '@example.invalid',
			'display_name' => 'Receipt QA Member',
			'first_name'   => 'Receipt',
			'last_name'    => 'Member',
			'role'         => 'subscriber',
		)
	);
	if ( is_wp_error( $user_id ) ) {
		throw new RuntimeException( $user_id->get_error_message() );
	}
	update_user_meta( $user_id, 'cyw_institution_name', 'Snapshot University' );
	update_user_meta( $user_id, 'cyw_country', 'US' );
	update_user_meta( $user_id, 'cyw_professional_title', 'Researcher' );

	$levels = pmpro_getAllLevels( true, true );
	$level  = $levels ? reset( $levels ) : null;
	$order  = new MemberOrder();
	$order->user_id             = $user_id;
	$order->membership_id       = $level ? absint( $level->id ) : 1;
	$order->subtotal            = 1;
	$order->tax                 = 0;
	$order->total               = 1;
	$order->status              = 'success';
	$order->gateway             = 'stripe';
	$order->gateway_environment = 'sandbox';
	$order->payment_type        = 'Card';
	$order->cardtype            = 'visa';
	$order->accountnumber       = '4242';
	$order->billing->name       = '';
	$order->notes               = $marker;
	$assert( (bool) $order->saveOrder(), 'Temporary non-transactional order was stored' );
	$order_id = absint( $order->id );

	CYWater_Membership_Receipt::snapshot_after_checkout( $user_id, $order );
	$model = CYWater_Membership_Receipt::view_model( $order );
	$assert( in_array( 'Receipt Member', $model['bill_to'], true ), 'Bill to contains the registered first and last name' );
	$assert( in_array( 'Snapshot University', $model['bill_to'], true ), 'Bill to contains the registered institution' );
	$assert( in_array( 'Researcher', $model['bill_to'], true ), 'Bill to contains the registered professional title' );
	$assert( in_array( 'United States', $model['bill_to'], true ), 'Bill to contains the canonical institution country or region' );
	$assert( in_array( $marker . '@example.invalid', $model['bill_to'], true ), 'Bill to contains the registered email' );

	update_user_meta( $user_id, 'cyw_institution_name', 'Changed After Payment' );
	$fresh = new MemberOrder();
	$fresh->getMemberOrderByID( $order_id );
	$after = CYWater_Membership_Receipt::view_model( $fresh );
	$assert( in_array( 'Snapshot University', $after['bill_to'], true ), 'Historical receipt preserves its order-time institution snapshot' );
	$assert( ! in_array( 'Changed After Payment', $after['bill_to'], true ), 'Later profile edits do not rewrite a historical receipt' );
} catch ( Throwable $error ) {
	WP_CLI::warning( 'Receipt identity QA failed: ' . $error->getMessage() );
	$failure = true;
} finally {
	if ( $order_id ) {
		$all_meta = get_pmpro_membership_order_meta( $order_id );
		foreach ( array_keys( (array) $all_meta ) as $meta_key ) {
			delete_pmpro_membership_order_meta( $order_id, $meta_key );
		}
		$cleanup_order = new MemberOrder();
		$cleanup_order->getMemberOrderByID( $order_id );
		$cleanup_order->deleteMe();
	}
	if ( $user_id ) {
		wp_delete_user( $user_id );
	}
}

global $wpdb;
$residue = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_login = %s", $marker ) );
$assert( 0 === $residue, 'Temporary receipt QA user was removed' );
$order_residue = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pmpro_membership_orders} WHERE notes = %s", $marker ) );
$assert( 0 === $order_residue, 'Temporary receipt QA order was removed' );

if ( ! empty( $failure ) ) {
	WP_CLI::error( 'Receipt identity QA did not pass.' );
}
WP_CLI::success( sprintf( 'Receipt identity QA passed %d checks with complete cleanup and no charge, membership, subscription, email, or Stripe mutation.', count( $checks ) ) );
