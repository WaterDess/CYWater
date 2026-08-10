<?php
/**
 * Minimize public WordPress identity and legacy-protocol exposure.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Public_Surface {
	public static function register() {
		add_filter( 'rest_endpoints', array( __CLASS__, 'hide_user_endpoints' ) );
		add_action( 'template_redirect', array( __CLASS__, 'disable_author_archives' ), 0 );
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array', PHP_INT_MAX );
		add_filter( 'wp_headers', array( __CLASS__, 'remove_pingback_header' ) );
		add_filter( 'the_generator', '__return_empty_string' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'rsd_link' );
	}

	/**
	 * Keep the REST user collection available only to administrators who can
	 * list accounts. Public content endpoints remain unchanged.
	 *
	 * @param array $endpoints Registered REST endpoints.
	 * @return array
	 */
	public static function hide_user_endpoints( $endpoints ) {
		if ( current_user_can( 'list_users' ) ) {
			return $endpoints;
		}

		foreach ( array_keys( $endpoints ) as $route ) {
			if ( preg_match( '#^/wp/v2/users(?:/|$)#', $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}

	/**
	 * CYWater has no public author pages. Return the normal theme 404 instead of
	 * exposing account slugs through numeric author discovery.
	 */
	public static function disable_author_archives() {
		if ( ! is_author() ) {
			return;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		include get_query_template( '404' );
		exit;
	}

	public static function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
}
