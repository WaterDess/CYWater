<?php
/**
 * Keep payment configuration sandboxed until production is explicitly approved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Payment_Guard {
	public static function register() {
		add_action( 'init', array( __CLASS__, 'enforce_environment' ), 30 );
		add_filter( 'pre_update_option_pmpro_gateway_environment', array( __CLASS__, 'block_live_environment' ), 10, 2 );
		add_filter( 'pre_update_option_pmpro_stripe_secretkey', array( __CLASS__, 'block_live_secret' ), 10, 2 );
		add_filter( 'pre_update_option_pmpro_stripe_publishablekey', array( __CLASS__, 'block_live_publishable_key' ), 10, 2 );
		add_filter( 'pre_option_pmpro_stripe_secretkey', array( __CLASS__, 'secret_key_from_environment' ) );
		add_filter( 'pre_option_pmpro_stripe_publishablekey', array( __CLASS__, 'publishable_key_from_environment' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
	}

	public static function enforce_environment() {
		if ( ! CYWater_Config::live_payments_allowed() && 'sandbox' !== get_option( 'pmpro_gateway_environment' ) ) {
			update_option( 'pmpro_gateway_environment', 'sandbox' );
		}
		$secret = (string) CYWater_Config::get( 'STRIPE_SECRET_KEY', '' );
		if ( ! CYWater_Config::live_payments_allowed() && str_starts_with( $secret, 'sk_live_' ) ) {
			set_transient( 'cywater_live_key_blocked', 1, HOUR_IN_SECONDS );
		}
	}

	public static function block_live_environment( $new_value, $old_value ) {
		if ( 'live' === $new_value && ! CYWater_Config::live_payments_allowed() ) {
			set_transient( 'cywater_live_mode_blocked', 1, HOUR_IN_SECONDS );
			return 'sandbox';
		}
		return $new_value;
	}

	public static function block_live_secret( $new_value, $old_value ) {
		if ( ! CYWater_Config::live_payments_allowed() && is_string( $new_value ) && str_starts_with( $new_value, 'sk_live_' ) ) {
			set_transient( 'cywater_live_key_blocked', 1, HOUR_IN_SECONDS );
			return $old_value;
		}
		return $new_value;
	}

	public static function block_live_publishable_key( $new_value, $old_value ) {
		if ( ! CYWater_Config::live_payments_allowed() && is_string( $new_value ) && str_starts_with( $new_value, 'pk_live_' ) ) {
			set_transient( 'cywater_live_key_blocked', 1, HOUR_IN_SECONDS );
			return $old_value;
		}
		return $new_value;
	}

	public static function secret_key_from_environment( $pre_option ) {
		$key = (string) CYWater_Config::get( 'STRIPE_SECRET_KEY', '' );
		if ( ! $key ) {
			return $pre_option;
		}
		if ( ! CYWater_Config::live_payments_allowed() && ! str_starts_with( $key, 'sk_test_' ) && ! str_starts_with( $key, 'rk_test_' ) ) {
			return '';
		}
		return $key;
	}

	public static function publishable_key_from_environment( $pre_option ) {
		$key = (string) CYWater_Config::get( 'STRIPE_PUBLISHABLE_KEY', '' );
		if ( ! $key ) {
			return $pre_option;
		}
		if ( ! CYWater_Config::live_payments_allowed() && ! str_starts_with( $key, 'pk_test_' ) ) {
			return '';
		}
		return $key;
	}

	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_transient( 'cywater_live_mode_blocked' ) || get_transient( 'cywater_live_key_blocked' ) ) {
			echo '<div class="notice notice-error"><p><strong>CYWater safety gate:</strong> Live Stripe mode or a live key was blocked. Production environment, CYWATER_PAYMENT_MODE=live, and CYWATER_ALLOW_LIVE_PAYMENTS=true are all required.</p></div>';
		}
	}
}
