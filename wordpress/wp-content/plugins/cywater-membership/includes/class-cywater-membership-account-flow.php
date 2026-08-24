<?php
/**
 * Account-first registration and checkout flow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Account_Flow {
	private const REGISTRATION_WINDOW = HOUR_IN_SECONDS;
	private const REGISTRATION_MAX    = 20;
	private static $errors;

	public static function register() {
		add_action( 'template_redirect', array( __CLASS__, 'require_account_for_checkout' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'process_registration' ), 6 );
		add_shortcode( 'cywater_member_register', array( __CLASS__, 'registration_form' ) );
		add_shortcode( 'cywater_member_login', array( __CLASS__, 'login_form' ) );
		add_filter( 'pmpro_login_forms_handler_nav', array( __CLASS__, 'add_create_account_link' ), 10, 2 );
	}

	public static function require_account_for_checkout() {
		if ( ! self::is_checkout_page() ) {
			return;
		}
		if ( ! self::payments_available() ) {
			wp_safe_redirect( add_query_arg( 'cywater_checkout', 'payments_paused', home_url( '/membership/' ) ) );
			exit;
		}
		if ( is_user_logged_in() ) {
			$block_reason = CYWater_Membership_Account_Security::checkout_block_reason( get_current_user_id() );
			if ( '' === $block_reason ) {
				return;
			}
			if ( 'closure_requested' === $block_reason ) {
				wp_safe_redirect( add_query_arg( 'cywater_closure', 'checkout_paused', home_url( '/close-account/' ) ) );
				exit;
			}
			$verification_url = add_query_arg(
				array(
					'cywater_verification' => 'required',
					'redirect_to'          => self::current_url(),
				),
				home_url( '/verify-email/' )
			);
			wp_safe_redirect( $verification_url );
			exit;
		}

		$login_url = CYWater_Membership_Account_Routing::login_url();
		$login_url = add_query_arg(
			array(
				'cywater_checkout' => 'login_required',
				'redirect_to'      => self::current_url(),
			),
			$login_url
		);
		wp_safe_redirect( $login_url );
		exit;
	}

	public static function process_registration() {
		if ( is_user_logged_in() || ! is_page( 'member-register' ) || 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( empty( $_POST['cywater_register_action'] ) ) {
			return;
		}

		self::$errors = new WP_Error();
		if ( ! isset( $_POST['cywater_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_register_nonce'] ) ), 'cywater_register_account' ) ) {
			self::$errors->add( 'expired', __( 'This form expired. Please try again.', 'cywater-membership' ) );
			return;
		}
		if ( ! empty( $_POST['company'] ) ) {
			self::$errors->add( 'invalid', __( 'The account could not be created.', 'cywater-membership' ) );
			return;
		}
		if ( self::registration_rate_limited() ) {
			self::$errors->add( 'rate_limited', __( 'Too many registration attempts were received from this network. Please wait one hour and try again.', 'cywater-membership' ) );
			return;
		}
		self::record_registration_attempt();

		$username         = sanitize_user( wp_unslash( $_POST['username'] ?? '' ), true );
		$email            = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$password         = (string) wp_unslash( $_POST['password'] ?? '' );
		$password_confirm = (string) wp_unslash( $_POST['password_confirm'] ?? '' );
		$first_name       = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$last_name        = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
		$professional     = array(
			'cyw_institution_name'   => sanitize_text_field( wp_unslash( $_POST['cyw_institution_name'] ?? '' ) ),
			'cyw_country'            => CYWater_Membership_Countries::canonical_code( sanitize_text_field( wp_unslash( $_POST['cyw_country'] ?? '' ) ) ),
			'cyw_institution_type'   => sanitize_key( wp_unslash( $_POST['cyw_institution_type'] ?? '' ) ),
			'cyw_professional_title' => sanitize_text_field( wp_unslash( $_POST['cyw_professional_title'] ?? '' ) ),
			'cyw_career_stage'       => sanitize_key( wp_unslash( $_POST['cyw_career_stage'] ?? '' ) ),
		);

		$identity_exists = false;
		if ( ! validate_username( $username ) || strlen( $username ) < 3 ) {
			self::$errors->add( 'username', __( 'Choose a username with at least three valid characters.', 'cywater-membership' ) );
		} elseif ( username_exists( $username ) ) {
			$identity_exists = true;
		}
		if ( ! is_email( $email ) ) {
			self::$errors->add( 'email', __( 'Enter a valid email address.', 'cywater-membership' ) );
		} elseif ( email_exists( $email ) ) {
			$identity_exists = true;
		}
		if ( $identity_exists ) {
			self::$errors->add( 'account_unavailable', __( 'An account could not be created with those details. Sign in or use account recovery if you may already be registered.', 'cywater-membership' ) );
		}
		if ( strlen( $password ) < 12 ) {
			self::$errors->add( 'password', __( 'Use a password with at least 12 characters.', 'cywater-membership' ) );
		} elseif ( ! hash_equals( $password, $password_confirm ) ) {
			self::$errors->add( 'password_match', __( 'The passwords do not match.', 'cywater-membership' ) );
		}
		if ( '' === $first_name ) {
			self::$errors->add( 'first_name', __( 'Enter your first name.', 'cywater-membership' ) );
		}
		if ( '' === $last_name ) {
			self::$errors->add( 'last_name', __( 'Enter your last name.', 'cywater-membership' ) );
		}
		if ( '' === $professional['cyw_institution_name'] ) {
			self::$errors->add( 'institution', __( 'Enter your institution or employer.', 'cywater-membership' ) );
		}
		if ( '' === $professional['cyw_country'] ) {
			self::$errors->add( 'country', __( 'Select a country or region from the list.', 'cywater-membership' ) );
		}
		if ( ! isset( CYWater_Membership_Fields::institution_types()[ $professional['cyw_institution_type'] ] ) ) {
			self::$errors->add( 'institution_type', __( 'Select your institution type.', 'cywater-membership' ) );
		}
		if ( '' === $professional['cyw_professional_title'] ) {
			self::$errors->add( 'professional_title', __( 'Enter your current title or role.', 'cywater-membership' ) );
		}
		if ( ! isset( CYWater_Membership_Fields::career_stages()[ $professional['cyw_career_stage'] ] ) ) {
			self::$errors->add( 'career_stage', __( 'Select your career stage.', 'cywater-membership' ) );
		}
		if ( self::$errors->has_errors() ) {
			return;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ),
				'role'         => 'subscriber',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			$collision_codes = array( 'existing_user_login', 'existing_user_email' );
			if ( array_intersect( $collision_codes, $user_id->get_error_codes() ) ) {
				self::$errors = new WP_Error( 'account_unavailable', __( 'An account could not be created with those details. Sign in or use account recovery if you may already be registered.', 'cywater-membership' ) );
			} else {
				self::$errors = $user_id;
			}
			return;
		}
		foreach ( $professional as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false, is_ssl() );
		do_action( 'wp_login', $username, get_user_by( 'id', $user_id ) );

		$fallback = home_url( '/membership/' );
		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : $fallback;
		$redirect = wp_validate_redirect( $redirect, $fallback );
		$sent     = CYWater_Membership_Account_Security::issue_verification( $user_id, $redirect );
		wp_safe_redirect(
			add_query_arg(
				array(
					'cywater_verification' => $sent ? 'sent' : 'send_failed',
					'redirect_to'          => $redirect,
				),
				home_url( '/verify-email/' )
			)
		);
		exit;
	}

	public static function registration_form() {
		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/membership/' );
		$redirect = wp_validate_redirect( $redirect, home_url( '/membership/' ) );
		if ( is_user_logged_in() ) {
			return sprintf(
				'<div class="cywater-register"><h2>%1$s</h2><p>%2$s</p><a class="btn btn-primary" href="%3$s">%4$s</a></div>',
				esc_html__( 'Your account is ready', 'cywater-membership' ),
				esc_html__( 'Continue to the selected membership checkout.', 'cywater-membership' ),
				esc_url( $redirect ),
				esc_html__( 'Continue', 'cywater-membership' )
			);
		}

		ob_start();
		?>
		<div class="cywater-register">
			<h2><?php esc_html_e( 'Create your CYWater account', 'cywater-membership' ); ?></h2>
			<p><?php esc_html_e( 'Create an account and verify your email address. You will then continue to membership details and secure payment.', 'cywater-membership' ); ?></p>
			<?php if ( self::$errors instanceof WP_Error && self::$errors->has_errors() ) : ?>
				<div class="notice" role="alert">
					<?php foreach ( self::$errors->get_error_messages() as $message ) : ?>
						<p><?php echo esc_html( $message ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'cywater_register_account', 'cywater_register_nonce' ); ?>
				<input type="hidden" name="cywater_register_action" value="1" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>" />
				<div aria-hidden="true" style="position:absolute;left:-10000px"><label>Company<input type="text" name="company" tabindex="-1" autocomplete="off" /></label></div>
				<fieldset class="cywater-register__section">
					<legend><?php esc_html_e( 'Name', 'cywater-membership' ); ?></legend>
					<div class="field-row">
						<div class="field">
							<label class="field-label" for="cywater-register-first-name"><?php esc_html_e( 'First name', 'cywater-membership' ); ?> <span class="req">*</span></label>
							<input class="input" id="cywater-register-first-name" name="first_name" type="text" maxlength="100" autocomplete="given-name" required value="<?php echo esc_attr( self::posted_text( 'first_name' ) ); ?>" />
						</div>
						<div class="field">
							<label class="field-label" for="cywater-register-last-name"><?php esc_html_e( 'Last name', 'cywater-membership' ); ?> <span class="req">*</span></label>
							<input class="input" id="cywater-register-last-name" name="last_name" type="text" maxlength="100" autocomplete="family-name" required value="<?php echo esc_attr( self::posted_text( 'last_name' ) ); ?>" />
						</div>
					</div>
				</fieldset>
				<fieldset class="cywater-register__section">
					<legend><?php esc_html_e( 'Professional information', 'cywater-membership' ); ?></legend>
					<p class="field-hint"><?php esc_html_e( 'All fields in this section are required.', 'cywater-membership' ); ?></p>
					<div class="field">
						<label class="field-label" for="cywater-register-institution"><?php esc_html_e( 'Institution or employer', 'cywater-membership' ); ?> <span class="req">*</span></label>
						<input class="input" id="cywater-register-institution" name="cyw_institution_name" type="text" maxlength="190" autocomplete="organization" required value="<?php echo esc_attr( self::posted_text( 'cyw_institution_name' ) ); ?>" />
					</div>
					<div class="field-row">
						<div class="field">
							<label class="field-label" for="cywater-register-country"><?php esc_html_e( 'Country or region of institution', 'cywater-membership' ); ?> <span class="req">*</span></label>
							<?php self::render_select( 'cywater-register-country', 'cyw_country', CYWater_Membership_Countries::options( true ), CYWater_Membership_Countries::canonical_code( self::posted_text( 'cyw_country' ) ) ); ?>
							<p class="field-hint"><?php esc_html_e( 'Start typing to search the complete list.', 'cywater-membership' ); ?></p>
						</div>
						<div class="field">
							<label class="field-label" for="cywater-register-institution-type"><?php esc_html_e( 'Institution type', 'cywater-membership' ); ?> <span class="req">*</span></label>
							<?php self::render_select( 'cywater-register-institution-type', 'cyw_institution_type', array( '' => __( 'Select one', 'cywater-membership' ) ) + CYWater_Membership_Fields::institution_types(), sanitize_key( self::posted_text( 'cyw_institution_type' ) ) ); ?>
						</div>
					</div>
					<div class="field-row">
						<div class="field">
							<label class="field-label" for="cywater-register-title"><?php esc_html_e( 'Current title or role', 'cywater-membership' ); ?> <span class="req">*</span></label>
							<input class="input" id="cywater-register-title" name="cyw_professional_title" type="text" maxlength="190" autocomplete="organization-title" required value="<?php echo esc_attr( self::posted_text( 'cyw_professional_title' ) ); ?>" />
						</div>
						<div class="field">
							<label class="field-label" for="cywater-register-career-stage"><?php esc_html_e( 'Career stage', 'cywater-membership' ); ?> <span class="req">*</span></label>
							<?php self::render_select( 'cywater-register-career-stage', 'cyw_career_stage', array( '' => __( 'Select one', 'cywater-membership' ) ) + CYWater_Membership_Fields::career_stages(), sanitize_key( self::posted_text( 'cyw_career_stage' ) ) ); ?>
						</div>
					</div>
				</fieldset>
				<fieldset class="cywater-register__section">
					<legend><?php esc_html_e( 'Account information', 'cywater-membership' ); ?></legend>
				<div class="field">
					<label class="field-label" for="cywater-register-username"><?php esc_html_e( 'Username', 'cywater-membership' ); ?> <span class="req">*</span></label>
					<input class="input" id="cywater-register-username" name="username" type="text" minlength="3" maxlength="60" autocomplete="username" required value="<?php echo esc_attr( sanitize_user( wp_unslash( $_POST['username'] ?? '' ), true ) ); ?>" />
				</div>
				<div class="field">
					<label class="field-label" for="cywater-register-email"><?php esc_html_e( 'Email address', 'cywater-membership' ); ?> <span class="req">*</span></label>
					<input class="input" id="cywater-register-email" name="email" type="email" autocomplete="email" required value="<?php echo esc_attr( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) ); ?>" />
				</div>
				<div class="field-row">
					<div class="field">
						<label class="field-label" for="cywater-register-password"><?php esc_html_e( 'Password', 'cywater-membership' ); ?> <span class="req">*</span></label>
						<input class="input" id="cywater-register-password" name="password" type="password" minlength="12" autocomplete="new-password" required />
						<p class="field-hint"><?php esc_html_e( 'At least 12 characters.', 'cywater-membership' ); ?></p>
					</div>
					<div class="field">
						<label class="field-label" for="cywater-register-password-confirm"><?php esc_html_e( 'Confirm password', 'cywater-membership' ); ?> <span class="req">*</span></label>
						<input class="input" id="cywater-register-password-confirm" name="password_confirm" type="password" minlength="12" autocomplete="new-password" required />
					</div>
				</div>
				</fieldset>
				<button class="btn btn-primary" type="submit"><?php esc_html_e( 'Create account and continue', 'cywater-membership' ); ?></button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function login_form() {
		$notice = '';
		if ( isset( $_GET['cywater_checkout'] ) && 'login_required' === sanitize_key( wp_unslash( $_GET['cywater_checkout'] ) ) ) {
			$notice = sprintf(
				'<div class="pmpro pmpro_message pmpro_alert" role="status">%s</div>',
				esc_html__( 'Sign in or create a CYWater account before continuing to membership checkout.', 'cywater-membership' )
			);
		}
		return $notice . do_shortcode( '[pmpro_login]' );
	}

	public static function add_create_account_link( $links, $form ) {
		if ( 'login' !== $form ) {
			return $links;
		}
		$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : home_url( '/membership/' );
		$url      = CYWater_Membership_Account_Routing::registration_url( $redirect );
		$links    = array( 'register' => sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Create account', 'cywater-membership' ) ) ) + $links;
		return $links;
	}

	private static function is_checkout_page() {
		$checkout_page_id = absint( get_option( 'pmpro_checkout_page_id' ) );
		return $checkout_page_id && is_page( $checkout_page_id );
	}

	private static function payments_available() {
		if ( ! class_exists( 'CYWater_Config' ) ) {
			return false;
		}
		return ( 'production' !== wp_get_environment_type() && 'test' === CYWater_Config::payment_mode() )
			|| CYWater_Config::live_payments_allowed();
	}

	private static function current_url() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/membership/';
		return home_url( $path );
	}

	private static function posted_text( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	private static function render_select( $id, $name, $options, $current ) {
		echo '<select class="select" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" required>';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	private static function registration_rate_limited() {
		$attempts = get_transient( self::registration_rate_key() );
		return is_array( $attempts ) && absint( $attempts['count'] ?? 0 ) >= self::REGISTRATION_MAX;
	}

	private static function record_registration_attempt() {
		$key      = self::registration_rate_key();
		$attempts = get_transient( $key );
		$count    = is_array( $attempts ) ? absint( $attempts['count'] ?? 0 ) : 0;
		set_transient( $key, array( 'count' => $count + 1 ), self::REGISTRATION_WINDOW );
	}

	private static function registration_rate_key() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$packed  = @inet_pton( $address );
		if ( false !== $packed ) {
			$address = inet_ntop( $packed );
		}
		return 'cyw_reg_' . hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) );
	}
}
