<?php
/**
 * One-time, idempotent order-identity snapshot backfill for existing receipts.
 * Set CYWATER_RECEIPT_BACKFILL_APPLY=1 to write snapshots after a database backup.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
if ( ! in_array( $host, array( 'cywater.org', 'staging.cywater.org' ), true ) ) {
	WP_CLI::error( 'Backfill is restricted to CYWater staging or production.' );
}
if ( ! class_exists( 'MemberOrder' ) || ! class_exists( 'CYWater_Membership_Receipt' ) ) {
	WP_CLI::error( 'Required receipt classes are unavailable.' );
}

$apply = '1' === getenv( 'CYWATER_RECEIPT_BACKFILL_APPLY' );
global $wpdb;
$ids = $wpdb->get_col( "SELECT id FROM {$wpdb->pmpro_membership_orders} WHERE status IN ('success','refunded') ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$created = 0;
$present = 0;
$incomplete = 0;
foreach ( array_map( 'absint', $ids ) as $id ) {
	$existing = get_pmpro_membership_order_meta( $id, CYWater_Membership_Receipt::IDENTITY_META_KEY, true );
	if ( is_array( $existing ) && ! empty( $existing['name'] ) && ! empty( $existing['institution'] ) && ! empty( $existing['email'] ) ) {
		++$present;
		continue;
	}
	$order = new MemberOrder();
	$order->getMemberOrderByID( $id );
	$model = CYWater_Membership_Receipt::view_model( $order );
	if ( count( $model['bill_to'] ?? array() ) < 3 ) {
		++$incomplete;
		continue;
	}
	if ( $apply && CYWater_Membership_Receipt::snapshot_order_identity( $order, false ) ) {
		++$created;
	}
}

WP_CLI::line(
	wp_json_encode(
		array(
			'host'       => $host,
			'mode'       => $apply ? 'apply' : 'dry-run',
			'scanned'    => count( $ids ),
			'created'    => $created,
			'present'    => $present,
			'incomplete' => $incomplete,
		)
	)
);
