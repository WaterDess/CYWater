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
	private static $membership_support_rendered = false;

	public static function register() {
		/* PMPro installs its public-login filter at priority 50 on wp_loaded. */
		add_filter( 'login_url', array( __CLASS__, 'filter_admin_login_url' ), 100, 2 );
		add_filter( 'logout_url', array( __CLASS__, 'filter_frontend_logout_url' ), 100, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'prevent_identity_page_cache' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'prevent_frontend_membership_self_service' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_authenticated_login_page' ), 14 );
		add_filter( 'pmpro_member_action_links', array( __CLASS__, 'remove_self_service_membership_actions' ), 20, 2 );
		add_action( 'pmpro_member_action_links_after', array( __CLASS__, 'render_membership_support' ) );
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

	/** Return whether an account owns a current individual CYWater membership. */
	public static function has_active_individual_membership( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id || ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
			return false;
		}

		$ids = self::individual_level_ids();

		return $ids && (bool) pmpro_hasMembershipLevel( $ids, $user_id );
	}

	/** Return the configured Student, Professional, and Lifetime level IDs. */
	public static function individual_level_ids() {
		$levels = (array) get_option( 'cywater_membership_level_ids', array() );
		return array_values(
			array_filter(
				array_map(
					'absint',
					array(
						$levels['student'] ?? 0,
						$levels['professional'] ?? 0,
						$levels['lifetime'] ?? 0,
					)
				)
			)
		);
	}

	/** Return the active individual level IDs owned by one account. */
	public static function active_individual_level_ids( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id || ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
			return array();
		}

		return array_values(
			array_filter(
				self::individual_level_ids(),
				static function ( $level_id ) use ( $user_id ) {
					return (bool) pmpro_hasMembershipLevel( absint( $level_id ), $user_id );
				}
			)
		);
	}

	/**
	 * Return the single membership action used by both desktop and mobile headers.
	 *
	 * Account identity and membership entitlement are intentionally separate:
	 * signing in changes the account control, while only an active individual
	 * membership changes this action to "My Membership".
	 */
	public static function membership_action() {
		if ( ! is_user_logged_in() ) {
			return array(
				'label' => __( 'Join CYWater', 'cywater-membership' ),
				'url'   => home_url( '/membership/' ),
			);
		}

		if ( self::has_active_individual_membership() ) {
			return array(
				'label' => __( 'My Membership', 'cywater-membership' ),
				'url'   => self::account_url() . '#pmpro_account-membership',
			);
		}

		return array(
			'label' => __( 'Choose Membership', 'cywater-membership' ),
			'url'   => home_url( '/membership/' ),
		);
	}

	/** Remove member-facing plan-change and cancellation controls completely. */
	public static function remove_self_service_membership_actions( $links, $level_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		foreach ( array( 'change', 'cancel' ) as $key ) {
			if ( isset( $links[ $key ] ) ) {
				unset( $links[ $key ] );
			}
		}
		return $links;
	}

	/** Render one support path in place of self-service change/cancel actions. */
	public static function render_membership_support() {
		if ( self::$membership_support_rendered || ! self::has_active_individual_membership() ) {
			return;
		}
		self::$membership_support_rendered = true;
		$email = class_exists( 'CYWater_Membership_Email_Routing' )
			? CYWater_Membership_Email_Routing::membership_email()
			: 'membership@cywater.org';
		?>
		<p class="cywater-membership-support">
			<?php esc_html_e( 'Membership changes and cancellations are handled by the CYWater Membership team.', 'cywater-membership' ); ?>
			<?php esc_html_e( 'Contact', 'cywater-membership' ); ?>
			<a href="<?php echo esc_url( 'mailto:' . $email . '?subject=CYWater%20membership%20request' ); ?>"><?php echo esc_html( $email ); ?></a>
			<?php esc_html_e( 'from the email address used for your account.', 'cywater-membership' ); ?>
		</p>
		<?php
	}

	/** Whether an active member is attempting to switch to another level. */
	public static function is_prohibited_level_change( $requested_level_id, $user_id = 0 ) {
		$requested_level_id = absint( $requested_level_id );
		if ( ! $requested_level_id || ! in_array( $requested_level_id, self::individual_level_ids(), true ) ) {
			return false;
		}
		$active_ids = self::active_individual_level_ids( $user_id );
		return ! empty( $active_ids ) && ! in_array( $requested_level_id, $active_ids, true );
	}

	/**
	 * Keep change/cancellation requests on the Membership support path.
	 * PMPro remains the data authority and administrators retain its backend
	 * controls; this gate only removes public self-service mutations.
	 */
	public static function prevent_frontend_membership_self_service() {
		$cancel_page_id = absint( get_option( 'pmpro_cancel_page_id' ) );
		if ( $cancel_page_id && is_page( $cancel_page_id ) ) {
			self::redirect_to_membership_support();
		}

		if ( ! is_user_logged_in() ) {
			return;
		}
		$checkout_page_id = absint( get_option( 'pmpro_checkout_page_id' ) );
		if ( ! $checkout_page_id || ! is_page( $checkout_page_id ) ) {
			return;
		}

		$requested_level_id = 0;
		if ( isset( $_GET['level'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested_level_id = absint( wp_unslash( $_GET['level'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_POST['level'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$requested_level_id = absint( wp_unslash( $_POST['level'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( self::is_prohibited_level_change( $requested_level_id ) ) {
			self::redirect_to_membership_support();
		}
	}

	/** Redirect to the authoritative account section without mutating state. */
	private static function redirect_to_membership_support() {
		$url = add_query_arg( 'membership_support', '1', self::account_url() ) . '#pmpro_account-membership';
		wp_safe_redirect( $url );
		exit;
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
