<?php
/**
 * Local avatar fallback for CYWater accounts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Avatars {
	public static function register() {
		add_filter( 'pre_get_avatar_data', array( __CLASS__, 'avatar_data' ), 10, 2 );
	}

	public static function avatar_data( $args, $id_or_email ) {
		$user        = self::resolve_user( $id_or_email );
		$photo       = $user ? self::profile_photo_url( $user ) : '';
		$default_url = plugins_url( 'assets/default-avatar.svg', CYWATER_MEMBERSHIP_FILE );
		$site_scheme = wp_parse_url( home_url( '/' ), PHP_URL_SCHEME );
		if ( in_array( $site_scheme, array( 'http', 'https' ), true ) ) {
			$default_url = set_url_scheme( $default_url, $site_scheme );
		}

		$args['url']          = $photo ?: $default_url;
		$args['found_avatar'] = true;
		return $args;
	}

	private static function profile_photo_url( $user ) {
		$may_show = get_current_user_id() === (int) $user->ID;
		if ( ! $may_show && get_user_meta( $user->ID, 'cyw_profile_public', true ) ) {
			$public_fields = (array) get_user_meta( $user->ID, 'cyw_public_fields', true );
			$may_show      = in_array( 'cyw_profile_photo', $public_fields, true );
		}
		if ( ! $may_show ) {
			return '';
		}

		$photo = get_user_meta( $user->ID, 'cyw_profile_photo', true );
		if ( is_array( $photo ) ) {
			$photo = $photo['fullurl'] ?? ( $photo['previewurl'] ?? '' );
		}
		return is_string( $photo ) && wp_http_validate_url( $photo ) ? $photo : '';
	}

	private static function resolve_user( $id_or_email ) {
		if ( $id_or_email instanceof WP_User ) {
			return $id_or_email;
		}
		if ( $id_or_email instanceof WP_Post ) {
			return get_user_by( 'id', (int) $id_or_email->post_author );
		}
		if ( $id_or_email instanceof WP_Comment ) {
			if ( $id_or_email->user_id ) {
				return get_user_by( 'id', (int) $id_or_email->user_id );
			}
			return get_user_by( 'email', (string) $id_or_email->comment_author_email );
		}
		if ( is_numeric( $id_or_email ) ) {
			return get_user_by( 'id', absint( $id_or_email ) );
		}
		if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			return get_user_by( 'email', $id_or_email );
		}
		return false;
	}
}
