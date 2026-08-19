<?php
/**
 * Conservative response headers that do not alter frontend rendering.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Security_Headers {
	public static function register() {
		add_action( 'send_headers', array( __CLASS__, 'send' ), 20 );
	}

	public static function send() {
		if ( headers_sent() ) {
			return;
		}

		header_remove( 'X-Powered-By' );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'X-Frame-Options: SAMEORIGIN', true );
		header( 'Referrer-Policy: strict-origin-when-cross-origin', true );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()', true );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000', true );
		}
	}
}
