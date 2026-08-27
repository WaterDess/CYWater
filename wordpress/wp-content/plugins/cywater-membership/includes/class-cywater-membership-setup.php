<?php
/**
 * PMPro levels, frontend pages, and rolling annual-term behavior.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Setup {
	public static function register() {
		add_action( 'cywater_after_core_setup', array( __CLASS__, 'setup' ) );
		add_filter( 'pmpro_checkout_start_date', array( __CLASS__, 'rolling_annual_start' ), 20, 3 );
		add_filter( 'pmpro_checkout_end_date', array( __CLASS__, 'rolling_annual_end' ), 10, 4 );
		add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
	}

	public static function dependency_notice() {
		if ( ! current_user_can( 'activate_plugins' ) || class_exists( 'PMPro_Membership_Level' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>CYWater Membership:</strong> Paid Memberships Pro is required for checkout and membership state. Profile privacy remains inactive until PMPro is installed.</p></div>';
	}

	public static function setup() {
		$directory_id = self::setup_directory_page();
		if ( ! class_exists( 'PMPro_Membership_Level' ) ) {
			return array( 'status' => 'pmpro-missing', 'pages' => array( 'member_directory' => $directory_id ) );
		}
		$page_ids = self::setup_pages();
		$page_ids['member_directory'] = $directory_id;
		$level_ids = self::setup_levels();
		update_option( 'pmpro_from_email', 'membership@cywater.org' );
		update_option( 'pmpro_from_name', 'CYWater Membership' );
		update_option( 'pmpro_only_filter_pmpro_emails', 1 );
		update_option( 'pmpro_use_custom_page_template_invoice', 'yes' );
		if ( ! get_option( 'cywater_membership_setup_version' ) ) {
			update_option( 'pmpro_gateway', 'stripe' );
			update_option( 'pmpro_gateway_environment', 'sandbox' );
			update_option( 'pmpro_stripe_payment_flow', 'checkout' );
			update_option( 'pmpro_currency', 'USD' );
		}
		update_option( 'cywater_membership_level_ids', $level_ids );
		update_option( 'cywater_membership_setup_version', CYWATER_MEMBERSHIP_VERSION );
		return array( 'status' => 'ready', 'pages' => $page_ids, 'levels' => $level_ids );
	}

	private static function setup_directory_page() {
		$page = get_page_by_path( 'members' );
		if ( $page ) {
			return (int) $page->ID;
		}
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Member directory',
				'post_name'    => 'members',
				'post_content' => '[cywater_member_directory]',
			)
		);
		return is_wp_error( $page_id ) ? 0 : (int) $page_id;
	}

	private static function setup_pages() {
		$pages = array(
			'account'             => array( 'Member account', 'account', '[pmpro_account][cywater_account_security]', 'pmpro_account_page_id' ),
			'billing'             => array( 'Membership billing', 'membership-billing', '[pmpro_billing]', 'pmpro_billing_page_id' ),
			'cancel'              => array( 'Cancel membership', 'membership-cancel', '[pmpro_cancel]', 'pmpro_cancel_page_id' ),
			'checkout'            => array( 'Membership checkout', 'membership-checkout', '[pmpro_checkout]', 'pmpro_checkout_page_id' ),
			'confirmation'        => array( 'Membership confirmation', 'membership-confirmation', '[pmpro_confirmation]', 'pmpro_confirmation_page_id' ),
			'invoice'             => array( 'Membership order', 'membership-order', '[pmpro_invoice]', 'pmpro_invoice_page_id' ),
			'levels'              => array( 'Membership', 'membership', '', 'pmpro_levels_page_id' ),
			'login'               => array( 'Member sign in', 'member-login', '[cywater_member_login]', 'pmpro_login_page_id', array( 'pmpro_login' ) ),
			'register'            => array( 'Create member account', 'member-register', '[cywater_member_register]', '' ),
			'verify_email'        => array( 'Verify email', 'verify-email', '[cywater_email_verification]', '' ),
			'close_account'       => array( 'Close account', 'close-account', '[cywater_account_closure]', '' ),
			'member_profile_edit' => array( 'Member profile', 'member-profile', '[pmpro_member_profile_edit][cywater_privacy_settings]', 'pmpro_member_profile_edit_page_id' ),
		);
		$result = array();
		foreach ( $pages as $key => $page ) {
			$existing = get_page_by_path( $page[1] );
			if ( $existing ) {
				$page_id = $existing->ID;
				$content = $existing->post_content;
				if ( $page[2] && preg_match_all( '/\[([a-zA-Z0-9_-]+)/', $page[2], $matches ) ) {
					$content = self::reconcile_managed_shortcodes(
						$content,
						array_unique( $matches[1] ),
						(array) ( $page[4] ?? array() )
					);
					if ( $content !== $existing->post_content ) {
						wp_update_post( array( 'ID' => $page_id, 'post_content' => $content ) );
					}
				}
			} else {
				$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $page[0], 'post_name' => $page[1], 'post_content' => $page[2] ) );
			}
			if ( ! is_wp_error( $page_id ) ) {
				if ( $page[3] ) {
					update_option( $page[3], absint( $page_id ) );
				}
				$result[ $key ] = absint( $page_id );
			}
		}
		return $result;
	}

	private static function setup_levels() {
		$definitions = array(
			'Student'      => array( 20, 'For full-time undergraduate, graduate, and Ph.D. students.', true ),
			'Professional' => array( 50, 'For researchers and practitioners in water sciences.', true ),
			'Lifetime'     => array( 700, 'A one-time individual lifetime membership.', false ),
		);
		$existing = function_exists( 'pmpro_getAllLevels' ) ? pmpro_getAllLevels( true, true ) : array();
		$by_name  = array();
		foreach ( $existing as $level ) {
			$by_name[ $level->name ] = $level;
		}
		$result = array();
		foreach ( $definitions as $name => $definition ) {
			$level = isset( $by_name[ $name ] ) ? new PMPro_Membership_Level( $by_name[ $name ]->id ) : new PMPro_Membership_Level();
			$level->name              = $name;
			$level->description       = $definition[1];
			$level->confirmation      = 'Thank you for joining CYWater. You can now complete your member profile and privacy choices.';
			$level->initial_payment   = $definition[0];
			$level->billing_amount    = 0;
			$level->cycle_number      = 0;
			$level->cycle_period      = 'Year';
			$level->billing_limit     = 0;
			$level->trial_amount      = 0;
			$level->trial_limit       = 0;
			$level->allow_signups     = 1;
			$level->expiration_number = $definition[2] ? 1 : 0;
			$level->expiration_period = $definition[2] ? 'Year' : '';
			$level->save();
			$result[ sanitize_key( $name ) ] = absint( $level->id );
		}
		if ( isset( $by_name['Partner'] ) ) {
			$legacy_partner               = new PMPro_Membership_Level( $by_name['Partner']->id );
			$legacy_partner->description  = 'Legacy institutional partnership payment record. Public signup is disabled; new partnerships require Board and MOU review.';
			$legacy_partner->allow_signups = 0;
			$legacy_partner->save();
			$result['partner_legacy'] = absint( $legacy_partner->id );
		}

		$sandbox_test_id = self::setup_sandbox_test_level( $by_name );
		if ( $sandbox_test_id ) {
			$result['sandbox_test'] = $sandbox_test_id;
		}
		return $result;
	}

	/**
	 * Reconcile a plugin-managed page without overwriting editorial copy.
	 *
	 * CYWater's login wrapper renders the PMPro login shortcode internally.
	 * Earlier installations used `[pmpro_login]` directly, so retaining both
	 * shortcodes renders two complete forms. Remove superseded aliases, retain
	 * the first canonical shortcode, and append it only when it is missing.
	 *
	 * @param string   $content           Existing page content.
	 * @param string[] $required          Canonical managed shortcodes.
	 * @param string[] $legacy_shortcodes Superseded shortcodes to remove.
	 * @return string
	 */
	public static function reconcile_managed_shortcodes( $content, $required, $legacy_shortcodes = array() ) {
		$content = (string) $content;
		foreach ( array_unique( array_filter( array_map( 'sanitize_key', (array) $legacy_shortcodes ) ) ) as $shortcode ) {
			$tag     = preg_quote( $shortcode, '/' );
			$content = (string) preg_replace( '/\[' . $tag . '(?:\s[^\]]*)?\](?:.*?\[\/' . $tag . '\])?/is', '', $content );
		}

		foreach ( array_unique( array_filter( array_map( 'sanitize_key', (array) $required ) ) ) as $shortcode ) {
			$tag   = preg_quote( $shortcode, '/' );
			$count = 0;
			$content = (string) preg_replace_callback(
				'/\[' . $tag . '(?:\s[^\]]*)?\]/i',
				static function ( $match ) use ( &$count ) {
					++$count;
					return 1 === $count ? $match[0] : '';
				},
				$content
			);
			if ( 0 === $count ) {
				$content .= '[' . $shortcode . ']';
			}
		}

		return trim( $content );
	}

	/**
	 * Create a staging-only Stripe Sandbox checkout fixture.
	 *
	 * The fixture lives in its own PMPro level group so completing a test
	 * checkout cannot replace Student, Professional, or Lifetime membership.
	 * It is deliberately absent from every CYWater benefit/eligibility allowlist.
	 */
	private static function setup_sandbox_test_level( $by_name ) {
		$name    = 'Sandbox Payment Test';
		$level   = isset( $by_name[ $name ] ) ? new PMPro_Membership_Level( $by_name[ $name ]->id ) : new PMPro_Membership_Level();
		$enabled = 'staging' === wp_get_environment_type()
			&& 'sandbox' === get_option( 'pmpro_gateway_environment' )
			&& class_exists( 'CYWater_Config' )
			&& 'test' === CYWater_Config::payment_mode();

		if ( ! $enabled ) {
			if ( ! empty( $level->id ) && ! empty( $level->allow_signups ) ) {
				$level->allow_signups = 0;
				$level->save();
			}
			return 0;
		}

		$level->name              = $name;
		$level->description       = 'Staging-only one-time Stripe Sandbox checkout and refund test. It grants no CYWater membership benefits.';
		$level->confirmation      = 'Stripe Sandbox checkout completed. No real funds moved and no CYWater membership benefit was granted.';
		$level->initial_payment   = 0.50;
		$level->billing_amount    = 0;
		$level->cycle_number      = 0;
		$level->cycle_period      = 'Day';
		$level->billing_limit     = 0;
		$level->trial_amount      = 0;
		$level->trial_limit       = 0;
		$level->allow_signups     = 1;
		$level->expiration_number = 1;
		$level->expiration_period = 'Day';
		$level->save();

		$group_id = self::sandbox_test_group_id();
		if ( $group_id && function_exists( 'pmpro_add_level_to_group' ) ) {
			pmpro_add_level_to_group( $level->id, $group_id );
		}

		return absint( $level->id );
	}

	private static function sandbox_test_group_id() {
		if ( ! function_exists( 'pmpro_get_level_groups' ) || ! function_exists( 'pmpro_create_level_group' ) ) {
			return 0;
		}

		foreach ( pmpro_get_level_groups() as $group ) {
			if ( 'Staging payment QA' === $group->name ) {
				return absint( $group->id );
			}
		}

		return absint( pmpro_create_level_group( 'Staging payment QA', false ) );
	}

	public static function rolling_annual_start( $startdate, $user_id, $level ) {
		$ids    = (array) get_option( 'cywater_membership_level_ids', array() );
		$annual = array_filter( array( $ids['student'] ?? 0, $ids['professional'] ?? 0 ) );
		if ( ! in_array( (int) $level->id, array_map( 'intval', $annual ), true ) ) {
			return $startdate;
		}

		return "'" . current_time( 'mysql' ) . "'";
	}

	public static function rolling_annual_end( $enddate, $user_id, $level, $startdate ) {
		$ids    = (array) get_option( 'cywater_membership_level_ids', array() );
		$annual = array_filter( array( $ids['student'] ?? 0, $ids['professional'] ?? 0 ) );
		if ( ! in_array( (int) $level->id, array_map( 'intval', $annual ), true ) ) {
			return $enddate;
		}

		// Each paid annual term is measured from the successful checkout date.
		$payment_date = new DateTimeImmutable( 'now', wp_timezone() );
		return $payment_date->modify( '+1 year' )->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
	}
}
