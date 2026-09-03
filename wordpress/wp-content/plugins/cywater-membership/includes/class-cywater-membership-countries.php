<?php
/**
 * Canonical country/region choices and the searchable single-select UI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Countries {
	/** @var array<string, string>|null */
	private static $options = null;

	/** @var array<string, string> */
	private static $profile_snapshot = array();

	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'normalize_current_user_country' ) );
		add_action( 'pmpro_personal_options_update', array( __CLASS__, 'capture_profile_snapshot' ), 5 );
		add_action( 'pmpro_user_profile_update_errors', array( __CLASS__, 'validate_frontend_profile' ), 10, 3 );
	}

	/**
	 * Reuse PMPro's maintained country/territory data instead of duplicating it.
	 *
	 * @return array<string, string>
	 */
	public static function options( $include_empty = false ) {
		if ( null === self::$options ) {
			self::$options = function_exists( 'pmpro_get_countries' ) ? (array) pmpro_get_countries() : array();
			self::$options = array_filter(
				array_map( 'sanitize_text_field', self::$options ),
				static function ( $label, $code ) {
					return '' !== (string) $code && '' !== $label;
				},
				ARRAY_FILTER_USE_BOTH
			);

			// Keep the four China-region choices explicit and consistent while
			// preserving PMPro's canonical ISO-compatible country codes.
			$china_regions = array(
				'CN' => __( 'China (Chinese mainland)', 'cywater-membership' ),
				'HK' => __( 'Hong Kong SAR, China', 'cywater-membership' ),
				'MO' => __( 'Macao SAR, China', 'cywater-membership' ),
				'TW' => __( 'Taiwan, China', 'cywater-membership' ),
			);
			foreach ( $china_regions as $code => $label ) {
				if ( isset( self::$options[ $code ] ) ) {
					self::$options[ $code ] = $label;
				}
			}
		}

		return $include_empty
			? array( '' => __( 'Select a country or region', 'cywater-membership' ) ) + self::$options
			: self::$options;
	}

	public static function canonical_code( $value ) {
		$value   = trim( (string) $value );
		$options = self::options();
		if ( isset( $options[ $value ] ) ) {
			return $value;
		}

		foreach ( $options as $code => $label ) {
			if ( 0 === strcasecmp( $value, $label ) ) {
				return (string) $code;
			}
		}
		return '';
	}

	public static function is_valid( $value ) {
		return '' !== self::canonical_code( $value );
	}

	public static function enqueue_assets() {
		wp_enqueue_style( 'cywater-membership-country', CYWATER_MEMBERSHIP_URL . 'assets/country-combobox.css', array(), CYWATER_MEMBERSHIP_VERSION );
		wp_enqueue_script( 'cywater-membership-country', CYWATER_MEMBERSHIP_URL . 'assets/country-combobox.js', array(), CYWATER_MEMBERSHIP_VERSION, true );
	}

	/** Transparently normalize a legacy stored country label to PMPro's canonical code. */
	public static function normalize_current_user_country() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$value = (string) get_user_meta( $user_id, 'cyw_country', true );
		$code  = self::canonical_code( $value );
		if ( '' !== $code && $code !== $value ) {
			update_user_meta( $user_id, 'cyw_country', $code );
		}
	}

	/** Preserve the last complete profile because PMPro saves custom fields before its frontend validation hook. */
	public static function capture_profile_snapshot( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		self::$profile_snapshot = array();
		foreach ( CYWater_Membership_Fields::required_professional_keys() as $key ) {
			self::$profile_snapshot[ $key ] = (string) get_user_meta( $user_id, $key, true );
		}
	}

	/**
	 * Make the Account profile's Name and Professional information genuinely required.
	 *
	 * @param array  $errors Validation messages, passed by reference by PMPro.
	 * @param bool   $update Whether this is an update.
	 * @param object $user   Pending WordPress user object.
	 */
	public static function validate_frontend_profile( &$errors, $update, &$user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$required = array(
			'first_name'             => __( 'Enter your first name.', 'cywater-membership' ),
			'last_name'              => __( 'Enter your last name.', 'cywater-membership' ),
			'cyw_institution_name'   => __( 'Enter your institution or employer.', 'cywater-membership' ),
			'cyw_country'            => __( 'Select a country or region from the list.', 'cywater-membership' ),
			'cyw_institution_type'   => __( 'Select your institution type.', 'cywater-membership' ),
			'cyw_professional_title' => __( 'Enter your current title or role.', 'cywater-membership' ),
			'cyw_career_stage'       => __( 'Select your career stage.', 'cywater-membership' ),
		);
		if ( CYWater_Membership_Fields::is_platform_owner_account( $user ) ) {
			unset( $required['first_name'], $required['last_name'] );
		}
		$invalid = false;
		foreach ( $required as $key => $message ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' === $value || ( 'cyw_country' === $key && ! self::is_valid( $value ) ) ) {
				$errors[] = $message;
				$invalid  = true;
			}
		}

		if ( ! $invalid ) {
			$code = self::canonical_code( sanitize_text_field( wp_unslash( $_POST['cyw_country'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_user_meta( (int) $user->ID, 'cyw_country', $code );
			return;
		}

		foreach ( self::$profile_snapshot as $key => $value ) {
			update_user_meta( (int) $user->ID, $key, $value );
		}
	}
}
