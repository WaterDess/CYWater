<?php
/**
 * Canonical routing for public member identity flows and staff administration.
 *
 * WordPress remains the authentication authority and PMPro remains the owner of
 * the public member pages. This class only decides which surface a visitor sees
 * before and after those core actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Account_Routing {
	public static function register() {
		/* PMPro installs its public-login filter at priority 50 on wp_loaded. */
		add_filter( 'login_url', array( __CLASS__, 'filter_admin_login_url' ), 100, 2 );
		add_filter( 'logout_url', array( __CLASS__, 'filter_frontend_logout_url' ), 100, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'prevent_identity_page_cache' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_authenticated_login_page' ), 14 );
	}

	/**
	 * Keep authentication and account pages out of full-page caches.
	 *
	 * These surfaces vary by login state and may contain account-specific
	 * controls. A public cached copy must never mask the authenticated redirect
	 * or be reused as an account response.
	 */
	public static function prevent_identity_page_cache() {
		$page_ids = array_filter(
			array_map(
				'absint',
				array(
					get_option( 'pmpro_login_page_id' ),
					get_option( 'pmpro_account_page_id' ),
					get_option( 'pmpro_billing_page_id' ),
				)
			)
		);

		if ( ! is_page( $page_ids ) && ! is_page( 'member-register' ) ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'CYWater member identity flow' );
	}

	/**
	 * Return the canonical public member sign-in URL.
	 *
	 * @param string $redirect Optional same-site destination after sign-in.
	 */
	public static function login_url( $redirect = '' ) {
		$url = function_exists( 'pmpro_url' ) ? (string) pmpro_url( 'login' ) : '';
		if ( '' === $url ) {
			$url = wp_login_url();
		}

		$redirect = self::same_site_redirect( $redirect, '' );
		return '' === $redirect ? $url : add_query_arg( 'redirect_to', $redirect, $url );
	}

	/**
	 * Return the canonical public registration URL.
	 *
	 * @param string $redirect Optional same-site destination after registration.
	 */
	public static function registration_url( $redirect = '' ) {
		$url      = home_url( '/member-register/' );
		$redirect = self::same_site_redirect( $redirect, '' );
		return '' === $redirect ? $url : add_query_arg( 'redirect_to', $redirect, $url );
	}

	/** Return the canonical public account URL. */
	public static function account_url() {
		$url = function_exists( 'pmpro_url' ) ? (string) pmpro_url( 'account' ) : '';
		return '' !== $url ? $url : home_url( '/account/' );
	}

	/** Return the public landing page after a successful member sign-out. */
	public static function logout_landing_url() {
		return add_query_arg( 'loggedout', 'true', self::login_url() );
	}

	/**
	 * Keep public member sign-out on the CYWater surface.
	 *
	 * Explicit caller redirects are authoritative (for example, switching the
	 * account used during checkout). WordPress administration keeps its native
	 * sign-out destination so staff reauthentication is never forced through a
	 * public member page.
	 *
	 * @param string $logout_url Nonced WordPress logout URL.
	 * @param string $redirect   Caller-supplied destination, when present.
	 */
	public static function filter_frontend_logout_url( $logout_url, $redirect ) {
		if ( is_admin() || '' !== (string) $redirect ) {
			return $logout_url;
		}

		return add_query_arg( 'redirect_to', self::logout_landing_url(), $logout_url );
	}

	/**
	 * Preserve a native WordPress login surface for an explicit admin request.
	 *
	 * PMPro correctly routes ordinary public login links to its managed member
	 * page. A direct /wp-admin/ request is different: it is a staff surface and
	 * should retain WordPress reauthentication while the Forum capability gate
	 * independently returns 403 to a signed-in non-staff account.
	 *
	 * @param string $login_url URL after earlier login_url filters.
	 * @param string $redirect  Requested post-login destination.
	 */
	public static function filter_admin_login_url( $login_url, $redirect ) {
		if ( ! self::is_admin_destination( $redirect ) ) {
			return $login_url;
		}

		$args  = array();
		$query = (string) wp_parse_url( $login_url, PHP_URL_QUERY );
		if ( '' !== $query ) {
			parse_str( $query, $args );
		}
		if ( '' !== (string) $redirect && empty( $args['redirect_to'] ) ) {
			$args['redirect_to'] = $redirect;
		}

		$native_url = site_url( 'wp-login.php', 'login' );
		return $args ? add_query_arg( $args, $native_url ) : $native_url;
	}

	/** Send an already-authenticated visitor away from the public sign-in form. */
	public static function redirect_authenticated_login_page() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$login_page_id = absint( get_option( 'pmpro_login_page_id' ) );
		if ( ! $login_page_id || ! is_page( $login_page_id ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( '' !== $action && 'login' !== $action ) {
			return;
		}

		$fallback = self::account_url();
		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : $fallback;
		$redirect = self::same_site_redirect( $redirect, $fallback );
		wp_safe_redirect( $redirect );
		exit;
	}

	/** Whether a login request is explicitly targeting WordPress administration. */
	private static function is_admin_destination( $redirect ) {
		if ( is_admin() ) {
			return true;
		}

		$redirect = self::same_site_redirect( $redirect, '' );
		if ( '' === $redirect ) {
			return false;
		}

		$target_path = untrailingslashit( (string) wp_parse_url( $redirect, PHP_URL_PATH ) );
		$admin_path  = untrailingslashit( (string) wp_parse_url( admin_url( '/' ), PHP_URL_PATH ) );
		return $target_path === $admin_path || str_starts_with( $target_path, $admin_path . '/' );
	}

	/** Validate that a requested return target stays on this WordPress site. */
	private static function same_site_redirect( $redirect, $fallback ) {
		$redirect = trim( (string) $redirect );
		return '' === $redirect ? (string) $fallback : wp_validate_redirect( $redirect, (string) $fallback );
	}
}
