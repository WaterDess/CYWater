<?php
/**
 * PMPro levels, frontend pages, and calendar-year behavior.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Setup {
	public static function register() {
		add_action( 'cywater_after_core_setup', array( __CLASS__, 'setup' ) );
		add_filter( 'pmpro_checkout_end_date', array( __CLASS__, 'calendar_year_end' ), 10, 4 );
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
			'account'             => array( 'Member account', 'account', '[pmpro_account]', 'pmpro_account_page_id' ),
			'billing'             => array( 'Membership billing', 'membership-billing', '[pmpro_billing]', 'pmpro_billing_page_id' ),
			'cancel'              => array( 'Cancel membership', 'membership-cancel', '[pmpro_cancel]', 'pmpro_cancel_page_id' ),
			'checkout'            => array( 'Membership checkout', 'membership-checkout', '[pmpro_checkout]', 'pmpro_checkout_page_id' ),
			'confirmation'        => array( 'Membership confirmation', 'membership-confirmation', '[pmpro_confirmation]', 'pmpro_confirmation_page_id' ),
			'invoice'             => array( 'Membership order', 'membership-order', '[pmpro_invoice]', 'pmpro_invoice_page_id' ),
			'levels'              => array( 'Membership', 'membership', '', 'pmpro_levels_page_id' ),
			'login'               => array( 'Member sign in', 'member-login', '[pmpro_login]', 'pmpro_login_page_id' ),
			'member_profile_edit' => array( 'Member profile', 'member-profile', '[pmpro_member_profile_edit][cywater_privacy_settings]', 'pmpro_member_profile_edit_page_id' ),
		);
		$result = array();
		foreach ( $pages as $key => $page ) {
			$existing = get_page_by_path( $page[1] );
			if ( $existing ) {
				$page_id = $existing->ID;
				if ( $page[2] && ! has_shortcode( $existing->post_content, trim( strtok( $page[2], ']' ), '[' ) ) ) {
					wp_update_post( array( 'ID' => $page_id, 'post_content' => $page[2] ) );
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
			'Professional' => array( 70, 'For researchers and practitioners in water sciences.', true ),
			'Lifetime'     => array( 700, 'A one-time individual lifetime membership.', false ),
			'Partner'      => array( 1000, 'An annual partnership for institutions and organizations.', true ),
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
		return $result;
	}

	public static function calendar_year_end( $enddate, $user_id, $level, $startdate ) {
		if ( ! defined( 'CYWATER_ENABLE_CALENDAR_YEAR_END' ) || ! CYWATER_ENABLE_CALENDAR_YEAR_END ) {
			return $enddate;
		}
		$ids    = (array) get_option( 'cywater_membership_level_ids', array() );
		$annual = array_filter( array( $ids['student'] ?? 0, $ids['professional'] ?? 0, $ids['partner'] ?? 0 ) );
		if ( ! in_array( (int) $level->id, array_map( 'intval', $annual ), true ) ) {
			return $enddate;
		}
		$start = $startdate ? strtotime( $startdate ) : current_time( 'timestamp' );
		return wp_date( 'Y-12-31', $start );
	}
}
