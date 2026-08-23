<?php
/**
 * Create, inspect, or close the isolated production Live-payment acceptance level.
 *
 * Run only through WP-CLI. Creating or reopening the level requires both:
 *
 * CYWATER_LIVE_ACCEPTANCE_ACTION=create
 * CYWATER_LIVE_ACCEPTANCE_ACK=REAL_FUNDS_AND_REFUND
 *
 * The level is deliberately outside CYWater's configured benefit levels and in
 * its own PMPro group. Closing it disables new checkout while preserving the
 * PMPro/Stripe order and refund audit trail.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$action = sanitize_key( getenv( 'CYWATER_LIVE_ACCEPTANCE_ACTION' ) ?: 'status' );
$valid  = array( 'create', 'status', 'disable' );
if ( ! in_array( $action, $valid, true ) ) {
	WP_CLI::error( 'Action must be create, status, or disable.' );
}

if ( 'https://cywater.org' !== untrailingslashit( home_url() ) || 'production' !== wp_get_environment_type() ) {
	WP_CLI::error( 'This helper is restricted to the CYWater production site.' );
}

if ( ! class_exists( 'PMPro_Membership_Level' ) || ! function_exists( 'pmpro_get_level_groups' ) || ! function_exists( 'pmpro_add_level_to_group' ) ) {
	WP_CLI::error( 'Required PMPro level and group APIs are unavailable.' );
}

global $wpdb;
$level_name = 'Live Payment Acceptance Test';
$group_name = 'Production financial acceptance';
$level_id   = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE name = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$level_name
	)
);

if ( 'create' === $action ) {
	if ( 'REAL_FUNDS_AND_REFUND' !== getenv( 'CYWATER_LIVE_ACCEPTANCE_ACK' ) ) {
		WP_CLI::error( 'Creating the fixture requires the explicit real-funds acknowledgement.' );
	}
	if ( ! class_exists( 'CYWater_Config' ) || 'live' !== CYWater_Config::payment_mode() || ! CYWater_Config::live_payments_allowed() ) {
		WP_CLI::error( 'The CYWater production Live-payment gate is not open.' );
	}
	if ( 'stripe' !== get_option( 'pmpro_gateway' ) || 'live' !== get_option( 'pmpro_gateway_environment' ) || 'USD' !== get_option( 'pmpro_currency' ) ) {
		WP_CLI::error( 'PMPro is not configured for Stripe Live in USD.' );
	}

	$level                       = $level_id ? new PMPro_Membership_Level( $level_id ) : new PMPro_Membership_Level();
	$level->name                 = $level_name;
	$level->description          = 'One-time USD 0.50 production payment and full-refund acceptance. It grants no CYWater membership benefits.';
	$level->confirmation         = 'The USD 0.50 Live payment was received for CYWater financial acceptance. No membership benefit was granted; the transaction will be fully refunded after reconciliation.';
	$level->initial_payment      = 0.50;
	$level->billing_amount       = 0;
	$level->cycle_number         = 0;
	$level->cycle_period         = 'Day';
	$level->billing_limit        = 0;
	$level->trial_amount         = 0;
	$level->trial_limit          = 0;
	$level->allow_signups        = 1;
	$level->expiration_number    = 1;
	$level->expiration_period    = 'Day';
	$level->save();
	$level_id = absint( $level->id );

	$core_ids = array_map( 'absint', (array) get_option( 'cywater_membership_level_ids', array() ) );
	if ( in_array( $level_id, $core_ids, true ) ) {
		$level->allow_signups = 0;
		$level->save();
		WP_CLI::error( 'The acceptance level collided with a configured benefit level and was closed.' );
	}

	$group_id = 0;
	foreach ( pmpro_get_level_groups() as $group ) {
		if ( $group_name === $group->name ) {
			$group_id = absint( $group->id );
			break;
		}
	}
	if ( ! $group_id && function_exists( 'pmpro_create_level_group' ) ) {
		$group_id = absint( pmpro_create_level_group( $group_name, false ) );
	}
	if ( ! $group_id ) {
		$level->allow_signups = 0;
		$level->save();
		WP_CLI::error( 'The isolated acceptance group could not be created; the level was closed.' );
	}
	pmpro_add_level_to_group( $level_id, $group_id );

	update_option(
		'cywater_live_payment_acceptance_fixture',
		array(
			'level_id'   => $level_id,
			'group_id'   => $group_id,
			'status'     => 'open',
			'created_at' => gmdate( 'c' ),
		),
		false
	);
}

if ( 'disable' === $action ) {
	if ( ! $level_id ) {
		WP_CLI::error( 'The Live acceptance level does not exist.' );
	}
	$level = new PMPro_Membership_Level( $level_id );
	$level->allow_signups = 0;
	$level->save();
	$fixture           = (array) get_option( 'cywater_live_payment_acceptance_fixture', array() );
	$fixture['status'] = 'closed';
	$fixture['closed_at'] = gmdate( 'c' );
	update_option( 'cywater_live_payment_acceptance_fixture', $fixture, false );
}

$level = $level_id ? new PMPro_Membership_Level( $level_id ) : null;
$orders_table = $wpdb->pmpro_membership_orders;
$users_table  = $wpdb->pmpro_memberships_users;
$result = array(
	'action'              => $action,
	'level_present'       => (bool) $level_id,
	'level_id'            => $level_id,
	'amount_usd'          => $level ? (float) $level->initial_payment : null,
	'recurring_amount'    => $level ? (float) $level->billing_amount : null,
	'allow_signups'       => $level ? (bool) $level->allow_signups : false,
	'expires_after_1_day' => $level ? ( 1 === (int) $level->expiration_number && 'Day' === $level->expiration_period ) : false,
	'core_benefit_level'  => $level_id ? in_array( $level_id, array_map( 'absint', (array) get_option( 'cywater_membership_level_ids', array() ) ), true ) : false,
	'orders_total'        => $level_id ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders_table} WHERE membership_id = %d", $level_id ) ) : 0, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	'orders_success'      => $level_id ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders_table} WHERE membership_id = %d AND status = 'success'", $level_id ) ) : 0, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	'orders_refunded'     => $level_id ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders_table} WHERE membership_id = %d AND status = 'refunded'", $level_id ) ) : 0, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	'active_entitlements' => $level_id ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$users_table} WHERE membership_id = %d AND status = 'active'", $level_id ) ) : 0, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	'checkout_url'        => $level && $level->allow_signups ? add_query_arg( 'level', $level_id, pmpro_url( 'checkout' ) ) : null,
);

WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
