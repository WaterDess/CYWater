<?php
/**
 * Read-only configuration access. Values are never logged or exposed by REST.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Config {
	public static function get( $name, $default = '' ) {
		if ( defined( $name ) ) {
			return constant( $name );
		}
		$value = getenv( $name );
		return false === $value || '' === $value ? $default : $value;
	}

	public static function payment_mode() {
		$mode = strtolower( (string) self::get( 'CYWATER_PAYMENT_MODE', 'disabled' ) );
		return in_array( $mode, array( 'disabled', 'test', 'live' ), true ) ? $mode : 'disabled';
	}

	public static function is_non_production() {
		return in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true );
	}

	public static function live_payments_allowed() {
		$approved = filter_var( self::get( 'CYWATER_ALLOW_LIVE_PAYMENTS', false ), FILTER_VALIDATE_BOOLEAN );
		return $approved && 'production' === wp_get_environment_type() && 'live' === self::payment_mode();
	}

	public static function has( $name ) {
		return '' !== (string) self::get( $name, '' );
	}
}
