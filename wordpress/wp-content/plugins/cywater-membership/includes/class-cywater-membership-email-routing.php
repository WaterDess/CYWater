<?php
/**
 * Scope transactional mail to CYWater's role-based addresses.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Email_Routing {
	private const ACCOUNTS_EMAIL   = 'accounts@cywater.org';
	private const MEMBERSHIP_EMAIL = 'membership@cywater.org';
	private const BILLING_EMAIL    = 'billing@cywater.org';

	/** PMPro templates that represent a payment, order, renewal, or refund. */
	private const PMPRO_BILLING_TEMPLATES = array(
		'billing',
		'billing_admin',
		'billing_failure',
		'billing_failure_admin',
		'cancel_on_next_payment_date',
		'cancel_on_next_payment_date_admin',
		'checkout_check',
		'checkout_check_admin',
		'checkout_paid',
		'checkout_paid_admin',
		'credit_card_expiring',
		'invoice',
		'membership_recurring',
		'payment_action',
		'payment_action_admin',
		'refund',
		'refund_admin',
	);

	/** PMPro templates that represent membership state rather than payment. */
	private const PMPRO_MEMBERSHIP_TEMPLATES = array(
		'admin_change',
		'admin_change_admin',
		'cancel',
		'cancel_admin',
		'checkout_free',
		'checkout_free_admin',
		'membership_expired',
		'membership_expiring',
	);

	public static function register() {
		add_filter( 'pmpro_email_data', array( __CLASS__, 'pmpro_data' ), 20, 2 );
		add_filter( 'pmpro_email_sender', array( __CLASS__, 'pmpro_sender' ), 20, 2 );
		add_filter( 'pmpro_email_sender_name', array( __CLASS__, 'pmpro_sender_name' ), 20, 2 );
		add_filter( 'pmpro_email_headers', array( __CLASS__, 'pmpro_headers' ), 20, 2 );

		add_filter( 'retrieve_password_notification_email', array( __CLASS__, 'account_notification' ), 20, 4 );
		add_filter( 'password_change_email', array( __CLASS__, 'account_notification' ), 20, 3 );
		add_filter( 'email_change_email', array( __CLASS__, 'account_notification' ), 20, 3 );
		add_filter( 'wp_new_user_notification_email', array( __CLASS__, 'account_notification' ), 20, 3 );
		add_filter( 'wp_password_change_notification_email', array( __CLASS__, 'account_notification' ), 20, 3 );
	}

	/**
	 * Keep variables embedded in PMPro templates consistent with the visible
	 * sender. Otherwise a Membership email can still print the global Billing
	 * address in its body.
	 */
	public static function pmpro_data( $data, $email ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$identity                = self::pmpro_identity( $email );
		$data['siteemail']        = $identity['email'];
		$data['pmpro_from_email'] = $identity['email'];
		return $data;
	}

	public static function pmpro_sender( $sender, $email ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		$identity = self::pmpro_identity( $email );
		return $identity['email'];
	}

	public static function pmpro_sender_name( $sender_name, $email ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		$identity = self::pmpro_identity( $email );
		return $identity['name'];
	}

	public static function pmpro_headers( $headers, $email ) {
		$identity = self::pmpro_identity( $email );
		return self::role_headers( $headers, $identity['name'], $identity['email'], false );
	}

	/**
	 * Route native WordPress account-recovery and account-change messages only.
	 * Platform, plugin, and administrator mail remains outside this scoped filter.
	 */
	public static function account_notification( $email ) {
		if ( ! is_array( $email ) ) {
			return $email;
		}
		$email['headers'] = self::role_headers(
			$email['headers'] ?? array(),
			'CYWater Accounts',
			self::ACCOUNTS_EMAIL,
			true,
			'CYWater Membership',
			self::MEMBERSHIP_EMAIL
		);
		return $email;
	}

	/**
	 * Exposed for deterministic QA and future PMPro template reviews.
	 * Unknown PMPro templates fail to the Membership identity, never Web Ops.
	 *
	 * @return array{name:string,email:string,role:string}
	 */
	public static function pmpro_identity( $email ) {
		$template = is_object( $email ) && isset( $email->template ) ? sanitize_key( (string) $email->template ) : '';
		if ( in_array( $template, self::PMPRO_BILLING_TEMPLATES, true ) ) {
			return array(
				'name'  => 'CYWater Billing',
				'email' => self::BILLING_EMAIL,
				'role'  => 'billing',
			);
		}
		if ( in_array( $template, self::PMPRO_MEMBERSHIP_TEMPLATES, true ) ) {
			return array(
				'name'  => 'CYWater Membership',
				'email' => self::MEMBERSHIP_EMAIL,
				'role'  => 'membership',
			);
		}
		return array(
			'name'  => 'CYWater Membership',
			'email' => self::MEMBERSHIP_EMAIL,
			'role'  => 'membership',
		);
	}

	/**
	 * Preserve unrelated headers while replacing From/Reply-To deterministically.
	 * PMPro has already added its From header before pmpro_email_headers runs.
	 */
	private static function role_headers( $headers, $name, $email, $replace_from, $reply_name = '', $reply_email = '' ) {
		if ( is_string( $headers ) ) {
			$headers = preg_split( '/\r\n|\r|\n/', $headers, -1, PREG_SPLIT_NO_EMPTY );
		}
		$headers = is_array( $headers ) ? $headers : array();
		foreach ( $headers as $key => $header ) {
			$header = ltrim( (string) $header );
			if ( 0 === stripos( $header, 'Reply-To:' ) || ( $replace_from && 0 === stripos( $header, 'From:' ) ) ) {
				unset( $headers[ $key ] );
			}
		}
		if ( $replace_from ) {
			$headers[] = sprintf( 'From: %s <%s>', $name, $email );
		}
		$headers[] = sprintf( 'Reply-To: %s <%s>', $reply_name ?: $name, $reply_email ?: $email );
		return array_values( $headers );
	}
}
