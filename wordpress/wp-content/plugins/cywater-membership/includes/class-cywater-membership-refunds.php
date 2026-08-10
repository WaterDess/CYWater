<?php
/**
 * Revoke the membership entitlement attached to a fully refunded PMPro order.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Refunds {
	private const META_RESULT = 'cywater_refund_entitlement_result';

	public static function register() {
		add_action( 'pmpro_order_status_refunded', array( __CLASS__, 'revoke_refunded_membership' ), 20, 2 );
	}

	/**
	 * Cancel only the membership level funded by the refunded order.
	 *
	 * A later successful order for the same user and level protects the current
	 * entitlement, so refunding an older payment cannot revoke a newer renewal.
	 * PMPro's level cancellation also cancels active gateway subscriptions for
	 * that user and level, preventing future renewal charges.
	 *
	 * @param MemberOrder $order           Refunded PMPro order.
	 * @param string      $original_status Status before the refund.
	 */
	public static function revoke_refunded_membership( $order, $original_status = '' ) {
		if ( ! is_object( $order ) || empty( $order->id ) || empty( $order->user_id ) || empty( $order->membership_id ) ) {
			return;
		}

		if ( ! function_exists( 'get_pmpro_membership_order_meta' ) || ! function_exists( 'pmpro_cancelMembershipLevel' ) ) {
			return;
		}

		if ( get_pmpro_membership_order_meta( $order->id, self::META_RESULT, true ) ) {
			return;
		}

		$later_order_id = self::find_later_successful_order( $order );
		if ( $later_order_id ) {
			self::record_result(
				$order,
				array(
					'status'                  => 'preserved_by_later_order',
					'later_membership_order'  => $later_order_id,
					'original_order_status'   => (string) $original_status,
				)
			);
			$order->add_order_note( __( 'CYWater: Membership remained active because a later successful order funds the same membership level.', 'cywater-membership' ) );
			return;
		}

		$was_active = self::user_has_active_level( (int) $order->user_id, (int) $order->membership_id );
		$subscription_cancelled = true;
		if ( ! empty( $order->subscription_transaction_id ) ) {
			$subscription_cancelled = (bool) $order->cancel();
		}

		$membership_cancelled = true;
		if ( $was_active ) {
			add_filter( 'pmpro_cancel_previous_subscriptions', '__return_false' );
			try {
				$membership_cancelled = (bool) pmpro_cancelMembershipLevel( (int) $order->membership_id, (int) $order->user_id, 'refunded' );
			} finally {
				remove_filter( 'pmpro_cancel_previous_subscriptions', '__return_false' );
			}
		}

		if ( ! $membership_cancelled || ! $subscription_cancelled ) {
			update_pmpro_membership_order_meta(
				$order->id,
				'cywater_refund_entitlement_error',
				array(
					'membership_cancelled'   => $membership_cancelled,
					'status'                 => 'entitlement_cancellation_failed',
					'subscription_cancelled' => $subscription_cancelled,
					'recorded_at'            => current_time( 'mysql', true ),
				)
			);
			$order->add_order_note( __( 'CYWater: The refunded membership or its renewal subscription could not be cancelled automatically and requires administrator review.', 'cywater-membership' ) );
			return;
		}

		self::record_result(
			$order,
			array(
				'status'                => $was_active ? 'membership_cancelled' : 'membership_already_inactive',
				'original_order_status' => (string) $original_status,
			)
		);
		$order->add_order_note( __( 'CYWater: Full refund revoked the membership level funded by this order and cancelled its active renewal subscription, if any.', 'cywater-membership' ) );

		do_action( 'cywater_refund_entitlement_revoked', 'membership', $order );
	}

	private static function find_later_successful_order( $order ) {
		global $wpdb;

		if ( ! empty( $order->datetime ) ) {
			$order_datetime = $order->datetime;
		} elseif ( is_numeric( $order->timestamp ) ) {
			$order_datetime = gmdate( 'Y-m-d H:i:s', (int) $order->timestamp );
		} else {
			$order_datetime = gmdate( 'Y-m-d H:i:s', strtotime( (string) $order->timestamp ) );
		}
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
				FROM {$wpdb->pmpro_membership_orders}
				WHERE user_id = %d
					AND membership_id = %d
					AND status = 'success'
					AND id <> %d
					AND (`timestamp` > %s OR (`timestamp` = %s AND id > %d))
				ORDER BY `timestamp` DESC, id DESC
				LIMIT 1",
				(int) $order->user_id,
				(int) $order->membership_id,
				(int) $order->id,
				$order_datetime,
				$order_datetime,
				(int) $order->id
			)
		);
	}

	private static function user_has_active_level( $user_id, $level_id ) {
		$levels = pmpro_getMembershipLevelsForUser( $user_id );
		return in_array( $level_id, array_map( 'intval', wp_list_pluck( $levels, 'id' ) ), true );
	}

	private static function record_result( $order, $result ) {
		$result['recorded_at'] = current_time( 'mysql', true );
		update_pmpro_membership_order_meta( $order->id, self::META_RESULT, $result );
		delete_pmpro_membership_order_meta( $order->id, 'cywater_refund_entitlement_error' );
	}
}
