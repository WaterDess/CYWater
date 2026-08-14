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
	 * CYWater has no public author pages by default. Return the normal theme 404
	 * instead of exposing account slugs through numeric author discovery.
	 *
	 * A module that publishes under a byline may open an archive for one
	 * specific account. Nothing is opened wholesale: an author who has published
	 * nothing still 404s, so numeric account enumeration stays closed.
	 */
	public static function disable_author_archives() {
		if ( ! is_author() ) {
			return;
		}

		/**
		 * Allow a public author archive for this queried account.
		 *
		 * @param bool $allowed   False by default.
		 * @param int  $author_id Queried author, 0 when unresolved.
		 */
		if ( apply_filters( 'cywater_public_author_archive_allowed', false, (int) get_queried_object_id() ) ) {
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
