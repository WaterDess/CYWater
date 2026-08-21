<?php
/**
 * Email ownership verification and reviewed account-closure requests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Account_Security {
	private const VERIFIED_EMAIL_META = 'cyw_verified_email';
	private const TOKEN_HASH_META     = 'cyw_email_verification_hash';
	private const TOKEN_EXPIRES_META  = 'cyw_email_verification_expires';
	private const TOKEN_SENT_META     = 'cyw_email_verification_sent';
	private const TOKEN_WINDOW_META   = 'cyw_email_verification_window';
	private const TOKEN_COUNT_META    = 'cyw_email_verification_count';
	private const CLOSURE_META        = 'cyw_account_closure_requested_at';
	private const LAST_LOGIN_META     = 'cyw_last_login_at';
	private const TOKEN_TTL           = DAY_IN_SECONDS;
	private const RESEND_INTERVAL     = MINUTE_IN_SECONDS;
	private const RESEND_WINDOW       = HOUR_IN_SECONDS;
	private const RESEND_MAX          = 5;
	private const CLOSURE_COOLING_OFF = 7 * DAY_IN_SECONDS;
	private const PASSWORD_MIN_LENGTH = 12;

	public static function register() {
		add_action( 'init', array( __CLASS__, 'enforce_frontend_password_policy' ), 9 );
		add_action( 'validate_password_reset', array( __CLASS__, 'validate_password_reset' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'process_request' ), 4 );
		add_shortcode( 'cywater_email_verification', array( __CLASS__, 'verification_shortcode' ) );
		add_shortcode( 'cywater_account_security', array( __CLASS__, 'account_security_shortcode' ) );
		add_shortcode( 'cywater_account_closure', array( __CLASS__, 'account_closure_shortcode' ) );
		add_action( 'profile_update', array( __CLASS__, 'handle_email_change' ), 10, 3 );
		add_action( 'wp_login', array( __CLASS__, 'record_last_login' ), 10, 2 );
		add_action( 'cywater_after_core_setup', array( __CLASS__, 'backfill_existing_accounts' ), 20 );
	}

	/** Keep PMPro's front-end password change aligned with registration policy. */
	public static function enforce_frontend_password_policy() {
		if ( empty( $_POST['action'] ) || 'change-password' !== sanitize_key( wp_unslash( $_POST['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}
		$user_id = get_current_user_id();
		$nonce   = isset( $_POST['change_password_user_nonce'] ) ? sanitize_key( wp_unslash( $_POST['change_password_user_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $user_id || absint( $_POST['user_id'] ?? 0 ) !== $user_id || ! wp_verify_nonce( $nonce, 'change-password-user_' . $user_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}
		$password = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $password || self::password_length( $password ) >= self::PASSWORD_MIN_LENGTH ) {
			return true;
		}

		remove_action( 'init', 'pmpro_change_password_process' );
		if ( function_exists( 'pmpro_setMessage' ) ) {
			pmpro_setMessage( __( 'Use a password with at least 12 characters.', 'cywater-membership' ), 'pmpro_error' );
		}
		return false;
	}

	/** Enforce the same rule on WordPress lost-password resets. */
	public static function validate_password_reset( $errors, $user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$password = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' !== $password && self::password_length( $password ) < self::PASSWORD_MIN_LENGTH ) {
			$errors->add( 'password_too_short', __( 'Use a password with at least 12 characters.', 'cywater-membership' ) );
		}
	}

	private static function password_length( $password ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $password, 'UTF-8' ) : strlen( $password );
	}

	public static function is_verified( $user_id ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user ) {
			return false;
		}
		$verified_email = strtolower( sanitize_email( get_user_meta( $user->ID, self::VERIFIED_EMAIL_META, true ) ) );
		return '' !== $verified_email && hash_equals( strtolower( $user->user_email ), $verified_email );
	}

	public static function issue_verification( $user_id, $redirect = '' ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user || ! is_email( $user->user_email ) || self::verification_rate_limited( $user->ID ) ) {
			return false;
		}

		$token   = wp_generate_password( 64, false, false );
		$expires = time() + self::TOKEN_TTL;
		update_user_meta( $user->ID, self::TOKEN_HASH_META, self::token_hash( $token ) );
		update_user_meta( $user->ID, self::TOKEN_EXPIRES_META, $expires );
		update_user_meta( $user->ID, self::TOKEN_SENT_META, time() );
		self::record_verification_send( $user->ID );

		$fallback = home_url( '/account/' );
		$redirect = wp_validate_redirect( $redirect, $fallback );
		$url      = add_query_arg(
			array(
				'cywater_verify' => '1',
				'user_id'         => $user->ID,
				'token'           => $token,
				'redirect_to'     => $redirect,
			),
			home_url( '/verify-email/' )
		);

		$sent = CYWater_Membership_Mail::send(
			$user->user_email,
			__( 'Verify your CYWater email address', 'cywater-membership' ),
			array(
				'preheader'    => __( 'Confirm your email address to finish setting up your CYWater account.', 'cywater-membership' ),
				'eyebrow'      => __( 'Account security', 'cywater-membership' ),
				'title'        => __( 'Confirm your email address', 'cywater-membership' ),
				'intro'        => __( 'Thank you for creating a CYWater account. Please confirm that this email address belongs to you before continuing to member services.', 'cywater-membership' ),
				'body'         => __( 'This secure link expires in 24 hours and can be used only once.', 'cywater-membership' ),
				'button_label' => __( 'Verify email address', 'cywater-membership' ),
				'button_url'   => $url,
				'notice'       => __( 'If you did not create this account, no action is required. For your security, do not forward this email or share the verification link.', 'cywater-membership' ),
			)
		);
		if ( ! $sent ) {
			delete_user_meta( $user->ID, self::TOKEN_SENT_META );
		}
		return (bool) $sent;
	}

	public static function process_request() {
		if ( is_page( 'verify-email' ) && isset( $_GET['cywater_verify'], $_GET['user_id'], $_GET['token'] ) ) {
			self::consume_verification_link();
		}
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( is_page( 'verify-email' ) && isset( $_POST['cywater_resend_verification'] ) ) {
			self::process_resend();
		}
		if ( is_page( 'close-account' ) && isset( $_POST['cywater_closure_action'] ) ) {
			self::process_closure();
		}
		if ( is_page( 'account' ) && isset( $_POST['cywater_session_action'] ) ) {
			self::process_sessions();
		}
	}

	private static function consume_verification_link() {
		$user_id  = absint( $_GET['user_id'] );
		$token    = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		$state    = self::verify_token( $user_id, $token ) ? 'verified' : 'invalid';
		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/account/' );
		wp_safe_redirect(
			add_query_arg(
				array(
					'cywater_verification' => $state,
					'redirect_to'          => wp_validate_redirect( $redirect, home_url( '/account/' ) ),
				),
				home_url( '/verify-email/' )
			)
		);
		exit;
	}

	public static function verify_token( $user_id, $token ) {
		$user_id  = absint( $user_id );
		$expected = (string) get_user_meta( $user_id, self::TOKEN_HASH_META, true );
		$expires  = absint( get_user_meta( $user_id, self::TOKEN_EXPIRES_META, true ) );
		$user     = get_user_by( 'id', $user_id );
		if ( $user && $expected && $expires >= time() && hash_equals( $expected, self::token_hash( $token ) ) ) {
			update_user_meta( $user_id, self::VERIFIED_EMAIL_META, strtolower( $user->user_email ) );
			delete_user_meta( $user_id, self::TOKEN_HASH_META );
			delete_user_meta( $user_id, self::TOKEN_EXPIRES_META );
			return true;
		}
		return false;
	}

	private static function process_resend() {
		if ( ! is_user_logged_in() || ! isset( $_POST['cywater_verification_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_verification_nonce'] ) ), 'cywater_resend_verification' ) ) {
			wp_die( esc_html__( 'This request expired. Please try again.', 'cywater-membership' ), '', array( 'response' => 403 ) );
		}
		$user_id  = get_current_user_id();
		$last_sent = absint( get_user_meta( $user_id, self::TOKEN_SENT_META, true ) );
		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/account/' );
		if ( $last_sent && time() - $last_sent < self::RESEND_INTERVAL ) {
			$state = 'too_soon';
		} elseif ( self::verification_rate_limited( $user_id ) ) {
			$state = 'rate_limited';
		} else {
			$state = self::issue_verification( $user_id, $redirect ) ? 'sent' : 'send_failed';
		}
		wp_safe_redirect( add_query_arg( array( 'cywater_verification' => $state, 'redirect_to' => $redirect ), home_url( '/verify-email/' ) ) );
		exit;
	}

	private static function process_closure() {
		if ( ! is_user_logged_in() || ! isset( $_POST['cywater_closure_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_closure_nonce'] ) ), 'cywater_account_closure' ) ) {
			wp_die( esc_html__( 'This request expired. Please try again.', 'cywater-membership' ), '', array( 'response' => 403 ) );
		}
		$user_id = get_current_user_id();
		$action  = sanitize_key( wp_unslash( $_POST['cywater_closure_action'] ) );
		if ( 'withdraw' === $action ) {
			self::withdraw_closure( $user_id );
			$state = 'withdrawn';
		} elseif ( 'request' === $action && ! empty( $_POST['cywater_confirm_closure'] ) ) {
			self::request_closure( $user_id );
			$state = 'requested';
		} else {
			$state = 'confirm_required';
		}
		wp_safe_redirect( add_query_arg( 'cywater_closure', $state, home_url( '/close-account/' ) ) );
		exit;
	}

	private static function process_sessions() {
		if ( ! is_user_logged_in() || ! isset( $_POST['cywater_session_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_session_nonce'] ) ), 'cywater_destroy_other_sessions' ) ) {
			wp_die( esc_html__( 'This request expired. Please try again.', 'cywater-membership' ), '', array( 'response' => 403 ) );
		}
		wp_destroy_other_sessions();
		wp_safe_redirect( add_query_arg( 'cywater_sessions', 'revoked', home_url( '/account/' ) ) );
		exit;
	}

	public static function verification_shortcode() {
		$state    = isset( $_GET['cywater_verification'] ) ? sanitize_key( wp_unslash( $_GET['cywater_verification'] ) ) : '';
		$fallback = home_url( '/account/' );
		$redirect = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), $fallback ) : $fallback;
		$messages = array(
			'verified'    => __( 'Your email address is verified.', 'cywater-membership' ),
			'sent'        => __( 'We sent a verification link. Check your inbox and spam folder.', 'cywater-membership' ),
			'send_failed' => __( 'The verification email could not be sent. Please try again.', 'cywater-membership' ),
			'too_soon'    => __( 'Please wait one minute before requesting another verification email.', 'cywater-membership' ),
			'rate_limited' => __( 'Too many verification messages were requested. Please wait one hour and try again.', 'cywater-membership' ),
			'invalid'     => __( 'This verification link is invalid or expired. Request a new one below.', 'cywater-membership' ),
			'required'    => __( 'Verify your email address before continuing to checkout.', 'cywater-membership' ),
		);
		ob_start();
		?>
		<div class="cywater-register">
			<h2><?php esc_html_e( 'Verify your email', 'cywater-membership' ); ?></h2>
			<?php if ( isset( $messages[ $state ] ) ) : ?><div class="notice" role="status"><p><?php echo esc_html( $messages[ $state ] ); ?></p></div><?php endif; ?>
			<?php if ( 'verified' === $state || ( is_user_logged_in() && self::is_verified( get_current_user_id() ) ) ) : ?>
				<p><?php esc_html_e( 'Email ownership has been confirmed. You may continue.', 'cywater-membership' ); ?></p>
				<a class="btn btn-primary" href="<?php echo esc_url( $redirect ); ?>"><?php esc_html_e( 'Continue', 'cywater-membership' ); ?></a>
			<?php elseif ( is_user_logged_in() ) : $user = wp_get_current_user(); ?>
				<p><?php echo esc_html( sprintf( __( 'A verification link is required for %s.', 'cywater-membership' ), $user->user_email ) ); ?></p>
				<form method="post" action="">
					<?php wp_nonce_field( 'cywater_resend_verification', 'cywater_verification_nonce' ); ?>
					<input type="hidden" name="cywater_resend_verification" value="1" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>" />
					<button class="btn btn-primary" type="submit"><?php esc_html_e( 'Send a new verification link', 'cywater-membership' ); ?></button>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'Sign in to request another verification email.', 'cywater-membership' ); ?></p>
				<a class="btn btn-primary" href="<?php echo esc_url( self::login_url( home_url( '/verify-email/' ) ) ); ?>"><?php esc_html_e( 'Sign in', 'cywater-membership' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function account_security_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$verified = self::is_verified( get_current_user_id() );
		$closure  = self::closure_status( get_current_user_id() );
		$sessions = isset( $_GET['cywater_sessions'] ) ? sanitize_key( wp_unslash( $_GET['cywater_sessions'] ) ) : '';
		ob_start();
		?>
		<section class="cywater-register">
			<h2><?php esc_html_e( 'Account security', 'cywater-membership' ); ?></h2>
			<p><strong><?php esc_html_e( 'Email:', 'cywater-membership' ); ?></strong> <?php echo esc_html( $verified ? __( 'Verified', 'cywater-membership' ) : __( 'Verification required', 'cywater-membership' ) ); ?></p>
			<?php if ( ! $verified ) : ?><p><a href="<?php echo esc_url( home_url( '/verify-email/' ) ); ?>"><?php esc_html_e( 'Verify email address', 'cywater-membership' ); ?></a></p><?php endif; ?>
			<?php if ( 'revoked' === $sessions ) : ?><div class="notice" role="status"><p><?php esc_html_e( 'Other signed-in devices have been signed out.', 'cywater-membership' ); ?></p></div><?php endif; ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'cywater_destroy_other_sessions', 'cywater_session_nonce' ); ?>
				<input type="hidden" name="cywater_session_action" value="destroy_others" />
				<button class="btn btn-outline" type="submit"><?php esc_html_e( 'Sign out other devices', 'cywater-membership' ); ?></button>
			</form>
			<?php if ( 'none' !== $closure ) : ?><p><?php esc_html_e( 'New checkout is paused while your account-closure request is open.', 'cywater-membership' ); ?></p><?php endif; ?>
			<p><a href="<?php echo esc_url( home_url( '/close-account/' ) ); ?>"><?php esc_html_e( 'Request account closure', 'cywater-membership' ); ?></a></p>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function account_closure_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<div class="cywater-register"><p>' . esc_html__( 'Sign in before requesting account closure.', 'cywater-membership' ) . '</p><a class="btn btn-primary" href="' . esc_url( self::login_url( home_url( '/close-account/' ) ) ) . '">' . esc_html__( 'Sign in', 'cywater-membership' ) . '</a></div>';
		}
		$requested_at = absint( get_user_meta( get_current_user_id(), self::CLOSURE_META, true ) );
		$deadline     = self::closure_deadline( get_current_user_id() );
		$state        = isset( $_GET['cywater_closure'] ) ? sanitize_key( wp_unslash( $_GET['cywater_closure'] ) ) : '';
		ob_start();
		?>
		<div class="cywater-register">
			<h2><?php esc_html_e( 'Request account closure', 'cywater-membership' ); ?></h2>
			<?php if ( 'confirm_required' === $state ) : ?><div class="notice" role="alert"><p><?php esc_html_e( 'Confirm the acknowledgement before submitting.', 'cywater-membership' ); ?></p></div><?php endif; ?>
			<?php if ( 'checkout_paused' === $state ) : ?><div class="notice" role="status"><p><?php esc_html_e( 'New checkout is paused while this closure request is open. Withdraw the request before making another purchase.', 'cywater-membership' ); ?></p></div><?php endif; ?>
			<?php if ( $requested_at ) : ?>
				<p><?php echo esc_html( sprintf( __( 'Your request was recorded on %1$s. The seven-day cooling-off period ends on %2$s. New checkout is paused, and membership support will review membership, event, and accounting records before deletion or anonymization.', 'cywater-membership' ), wp_date( get_option( 'date_format' ), $requested_at ), wp_date( get_option( 'date_format' ), $deadline ) ) ); ?></p>
				<?php if ( self::closure_is_due( get_current_user_id() ) ) : ?><p><strong><?php esc_html_e( 'The cooling-off period is complete and the request is ready for administrator review.', 'cywater-membership' ); ?></strong></p><?php endif; ?>
				<form method="post" action="">
					<?php wp_nonce_field( 'cywater_account_closure', 'cywater_closure_nonce' ); ?>
					<input type="hidden" name="cywater_closure_action" value="withdraw" />
					<button class="btn btn-outline" type="submit"><?php esc_html_e( 'Withdraw closure request', 'cywater-membership' ); ?></button>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'This request starts a seven-day cooling-off period and pauses new checkout. It does not immediately erase the account. Active membership or event registrations must be resolved, and required payment/refund records must be retained under association policy.', 'cywater-membership' ); ?></p>
				<form method="post" action="">
					<?php wp_nonce_field( 'cywater_account_closure', 'cywater_closure_nonce' ); ?>
					<input type="hidden" name="cywater_closure_action" value="request" />
					<label><input type="checkbox" name="cywater_confirm_closure" value="1" required /> <?php esc_html_e( 'I understand that membership access may be cancelled while legally required transaction records are retained.', 'cywater-membership' ); ?></label>
					<p><button class="btn btn-primary" type="submit"><?php esc_html_e( 'Submit closure request', 'cywater-membership' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function handle_email_change( $user_id, $old_user_data, $userdata ) {
		if ( ! $old_user_data instanceof WP_User ) {
			return;
		}
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( $user && strtolower( $old_user_data->user_email ) !== strtolower( $user->user_email ) ) {
			delete_user_meta( $user->ID, self::VERIFIED_EMAIL_META );
			update_user_meta( $user->ID, 'cyw_profile_public', 0 );
			self::issue_verification( $user->ID, home_url( '/account/' ) );
		}
	}

	public static function record_last_login( $user_login, $user ) {
		if ( $user instanceof WP_User ) {
			update_user_meta( $user->ID, self::LAST_LOGIN_META, time() );
		}
	}

	public static function backfill_existing_accounts() {
		if ( get_option( 'cywater_email_verification_backfill_version' ) ) {
			return;
		}
		foreach ( get_users() as $user ) {
			if ( $user instanceof WP_User && is_email( $user->user_email ) ) {
				update_user_meta( $user->ID, self::VERIFIED_EMAIL_META, strtolower( $user->user_email ) );
			}
		}
		update_option( 'cywater_email_verification_backfill_version', 1, false );
	}

	public static function closure_requested_at( $user_id ) {
		return absint( get_user_meta( absint( $user_id ), self::CLOSURE_META, true ) );
	}

	public static function closure_deadline( $user_id ) {
		$requested_at = self::closure_requested_at( $user_id );
		return $requested_at ? $requested_at + self::CLOSURE_COOLING_OFF : 0;
	}

	public static function closure_is_due( $user_id ) {
		$deadline = self::closure_deadline( $user_id );
		return $deadline > 0 && $deadline <= time();
	}

	public static function closure_status( $user_id ) {
		if ( ! self::closure_requested_at( $user_id ) ) {
			return 'none';
		}
		return self::closure_is_due( $user_id ) ? 'review_due' : 'cooling_off';
	}

	public static function checkout_block_reason( $user_id ) {
		if ( self::closure_requested_at( $user_id ) ) {
			return 'closure_requested';
		}
		if ( ! self::is_verified( $user_id ) ) {
			return 'email_unverified';
		}
		return '';
	}

	public static function request_closure( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! get_user_by( 'id', $user_id ) ) {
			return false;
		}
		$existing = self::closure_requested_at( $user_id );
		if ( $existing ) {
			return true;
		}
		$requested_at = time();
		update_user_meta( $user_id, self::CLOSURE_META, $requested_at );
		self::send_closure_notifications( $user_id, $requested_at );
		return true;
	}

	public static function withdraw_closure( $user_id ) {
		delete_user_meta( absint( $user_id ), self::CLOSURE_META );
	}

	public static function last_login_at( $user_id ) {
		return absint( get_user_meta( absint( $user_id ), self::LAST_LOGIN_META, true ) );
	}

	public static function revoke_all_sessions( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! get_user_by( 'id', $user_id ) ) {
			return false;
		}
		WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		return true;
	}

	public static function verification_rate_limited( $user_id ) {
		$window = absint( get_user_meta( absint( $user_id ), self::TOKEN_WINDOW_META, true ) );
		$count  = absint( get_user_meta( absint( $user_id ), self::TOKEN_COUNT_META, true ) );
		return $window && time() - $window < self::RESEND_WINDOW && $count >= self::RESEND_MAX;
	}

	private static function send_closure_notifications( $user_id, $requested_at ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user ) {
			return;
		}
		$record_url = add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) ) . '#cywater-member-record';
		wp_mail(
			'membership@cywater.org',
			__( 'CYWater account closure request', 'cywater-membership' ),
			sprintf( __( "A member requested account closure.\n\nUser ID: %1\$d\nEmail: %2\$s\nRequested: %3\$s\nReview: %4\$s", 'cywater-membership' ), $user->ID, $user->user_email, gmdate( 'c', $requested_at ), $record_url ),
			array( 'From: CYWater Accounts <accounts@cywater.org>', 'Reply-To: ' . $user->user_email )
		);
		CYWater_Membership_Mail::send(
			$user->user_email,
			__( 'We received your CYWater account closure request', 'cywater-membership' ),
			array(
				'preheader'    => __( 'Your CYWater account closure request has been recorded.', 'cywater-membership' ),
				'eyebrow'      => __( 'Account administration', 'cywater-membership' ),
				'title'        => __( 'Account closure request received', 'cywater-membership' ),
				'intro'        => __( 'We have recorded your request to close your CYWater account. A seven-day cooling-off period has started, and new membership checkout is paused during this period.', 'cywater-membership' ),
				'body'         => __( 'You may withdraw the request from your account before the cooling-off period ends. Membership support will then review membership, event, and accounting records before any deletion or anonymization takes place.', 'cywater-membership' ),
				'button_label' => __( 'Review account request', 'cywater-membership' ),
				'button_url'   => home_url( '/close-account/' ),
				'notice'       => __( 'This request does not immediately delete your account. Records that CYWater must retain for legal, accounting, security, or dispute-resolution purposes may be preserved under association policy.', 'cywater-membership' ),
			)
		);
	}

	private static function record_verification_send( $user_id ) {
		$user_id = absint( $user_id );
		$window  = absint( get_user_meta( $user_id, self::TOKEN_WINDOW_META, true ) );
		$count   = absint( get_user_meta( $user_id, self::TOKEN_COUNT_META, true ) );
		if ( ! $window || time() - $window >= self::RESEND_WINDOW ) {
			$window = time();
			$count  = 0;
			update_user_meta( $user_id, self::TOKEN_WINDOW_META, $window );
		}
		update_user_meta( $user_id, self::TOKEN_COUNT_META, $count + 1 );
	}

	private static function token_hash( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	private static function login_url( $redirect ) {
		$url = function_exists( 'pmpro_url' ) ? pmpro_url( 'login' ) : wp_login_url();
		return add_query_arg( 'redirect_to', wp_validate_redirect( $redirect, home_url( '/account/' ) ), $url );
	}
}
