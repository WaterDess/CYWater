<?php
/**
 * Route local WordPress mail to Mailpit without affecting production SMTP.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Local_Mail {
	public static function register() {
		add_action( 'phpmailer_init', array( __CLASS__, 'configure_mailpit' ) );
		add_filter( 'wp_mail_from', array( __CLASS__, 'from_address' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ) );
	}

	public static function configure_mailpit( $phpmailer ) {
		if ( ! CYWater_Config::is_non_production() || 'mailpit' !== CYWater_Config::get( 'CYWATER_MAIL_TRANSPORT', '' ) ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = (string) CYWater_Config::get( 'MAILPIT_HOST', 'host.docker.internal' );
		$phpmailer->Port       = (int) CYWater_Config::get( 'MAILPIT_PORT', 1025 );
		$phpmailer->SMTPAuth   = false;
		$phpmailer->SMTPSecure = '';
		$phpmailer->SMTPAutoTLS = false;
	}

	public static function from_address( $address ) {
		if ( CYWater_Config::is_non_production() && 'mailpit' === CYWater_Config::get( 'CYWATER_MAIL_TRANSPORT', '' ) ) {
			return 'noreply@cywater.local';
		}
		return $address;
	}

	public static function from_name( $name ) {
		return CYWater_Config::is_non_production() ? 'CYWater Local' : $name;
	}
}
