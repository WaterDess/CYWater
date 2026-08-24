<?php
/**
 * arXiv-style author endorsement.
 *
 * A candidate names an existing author. That author receives a single-use link
 * and, by following it while signed in as themselves, vouches for the
 * candidate. Once the required number of endorsements is recorded the candidate
 * gains the forum author role.
 *
 * The token mechanics deliberately mirror
 * CYWater_Membership_Account_Security: an HMAC of the token is stored, never
 * the token; links expire; replay is rejected; sending is rate limited. A
 * second, subtly different token implementation is exactly the kind of thing
 * that rots, so the shape is kept identical on purpose.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Forum_Endorsement {
	private const ENDORSEMENTS_META = 'cyw_forum_endorsements';
	private const REQUESTS_META     = 'cyw_forum_endorsement_requests';
	private const ADMIN_GRANT_META  = 'cyw_forum_admin_authorized';
	private const WINDOW_META       = 'cyw_forum_endorsement_window';
	private const COUNT_META        = 'cyw_forum_endorsement_count';
	private const PAGE_SLUG         = 'forum-endorsement';

	public static function register() {
		add_action( 'template_redirect', array( __CLASS__, 'process_request' ), 4 );
		add_shortcode( 'cywater_forum_endorsement', array( __CLASS__, 'endorsement_shortcode' ) );
		add_action( 'cywater_after_core_setup', array( __CLASS__, 'setup_page' ), 15 );
	}

	/**
	 * Keep the historical page renderable while the workflow is paused.
	 *
	 * No request handler is registered, so old invitation links and request
	 * forms cannot create, consume, or send anything. Existing user metadata and
	 * all legacy implementation remain intact for a later reviewed policy change.
	 */
	public static function register_paused() {
		add_shortcode( 'cywater_forum_endorsement', array( __CLASS__, 'paused_shortcode' ) );
	}

	public static function paused_shortcode() {
		return '<div class="cywater-register forum-endorsement"><h2>'
			. esc_html__( 'Forum participation', 'cywater-forum' )
			. '</h2><p>'
			. esc_html__( 'Invitations and endorsements are currently paused. Verified CYWater members with an active individual membership may publish Forum articles directly.', 'cywater-forum' )
			. '</p><a class="btn btn-primary" href="'
			. esc_url( get_post_type_archive_link( CYWater_Forum_Content::POST_TYPE ) )
			. '">'
			. esc_html__( 'Visit the Forum', 'cywater-forum' )
			. '</a></div>';
	}

	public static function page_url() {
		return home_url( '/' . self::PAGE_SLUG . '/' );
	}

	public static function setup_page() {
		if ( get_page_by_path( self::PAGE_SLUG ) ) {
			return;
		}
		wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Forum endorsement',
				'post_name'    => self::PAGE_SLUG,
				'post_content' => '[cywater_forum_endorsement]',
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * State
	 * ------------------------------------------------------------------ */

	/**
	 * Recorded endorsements for a candidate.
	 *
	 * @return array<int, array{endorser:int, at:int, via:string}>
	 */
	public static function endorsements( $user_id ) {
		$records = get_user_meta( absint( $user_id ), self::ENDORSEMENTS_META, true );
		return is_array( $records ) ? $records : array();
	}

	public static function is_admin_authorized( $user_id ) {
		return (bool) get_user_meta( absint( $user_id ), self::ADMIN_GRANT_META, true );
	}

	/**
	 * Endorsement is recomputed against current policy rather than frozen at
	 * the moment it was granted. Raising `endorsements_required` therefore
	 * applies to everyone, including existing authors, which is the intended
	 * reading of a policy parameter. Administrator grants are exempt.
	 */
	public static function is_endorsed( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}
		if ( self::is_admin_authorized( $user_id ) && CYWater_Forum_Settings::is_enabled( 'admin_override' ) ) {
			return true;
		}
		$required = max( 1, CYWater_Forum_Settings::get_int( 'endorsements_required' ) );
		return count( self::endorsements( $user_id ) ) >= $required;
	}

	/**
	 * Whether this user may endorse somebody else.
	 */
	public static function is_qualified_endorser( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}
		if ( CYWater_Forum_Roles::is_staff( $user_id ) ) {
			return true;
		}
		if ( ! self::is_endorsed( $user_id ) ) {
			return false;
		}
		$required = max( 1, CYWater_Forum_Settings::get_int( 'endorsement_articles_required' ) );
		return CYWater_Forum_Content::published_count( $user_id ) >= $required;
	}

	/* ---------------------------------------------------------------------
	 * Granting
	 * ------------------------------------------------------------------ */

	/**
	 * @param string $via One of 'link' or 'admin'.
	 */
	public static function record_endorsement( $candidate_id, $endorser_id, $via = 'link' ) {
		$candidate_id = absint( $candidate_id );
		$endorser_id  = absint( $endorser_id );
		if ( ! $candidate_id || $candidate_id === $endorser_id ) {
			return false;
		}

		$records = self::endorsements( $candidate_id );
		foreach ( $records as $record ) {
			if ( absint( $record['endorser'] ?? 0 ) === $endorser_id ) {
				// Already counted. Re-endorsing must not inflate the total.
				return false;
			}
		}

		$records[] = array(
			'endorser' => $endorser_id,
			'at'       => time(),
			'via'      => 'admin' === $via ? 'admin' : 'link',
		);
		update_user_meta( $candidate_id, self::ENDORSEMENTS_META, $records );

		self::sync_role( $candidate_id );
		return true;
	}

	/**
	 * Bring the durable author role into line with the current endorsement
	 * state. The role is only ever added here; removal is an administrator
	 * action so that an author's drafts are never orphaned by a policy edit.
	 */
	public static function sync_role( $user_id ) {
		if ( self::is_endorsed( $user_id ) ) {
			if ( CYWater_Forum_Roles::grant_author_role( $user_id ) ) {
				self::notify_candidate_authorized( $user_id );
			}
		}
	}

	public static function admin_grant( $candidate_id, $administrator_id ) {
		if ( ! CYWater_Forum_Settings::is_enabled( 'admin_override' ) ) {
			return false;
		}
		update_user_meta( absint( $candidate_id ), self::ADMIN_GRANT_META, absint( $administrator_id ) ?: 1 );
		self::sync_role( $candidate_id );
		return true;
	}

	/**
	 * Withdraw an administrator grant.
	 *
	 * The role is only removed if nothing else still justifies it. Someone who
	 * collected real endorsements and was *also* granted the override keeps
	 * their authorship when the override is withdrawn — otherwise ticking and
	 * unticking the box would strip an earned role, leaving the admin panel
	 * reporting "can publish: yes" while the member could not even edit their
	 * own drafts.
	 */
	public static function admin_revoke( $candidate_id ) {
		$candidate_id = absint( $candidate_id );
		delete_user_meta( $candidate_id, self::ADMIN_GRANT_META );
		if ( ! self::is_endorsed( $candidate_id ) ) {
			CYWater_Forum_Roles::revoke_author_role( $candidate_id );
		}
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Requesting
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<int, array{hash:string, expires:int, requested:int}>
	 */
	private static function pending_requests( $candidate_id ) {
		$requests = get_user_meta( absint( $candidate_id ), self::REQUESTS_META, true );
		return is_array( $requests ) ? $requests : array();
	}

	/**
	 * Issue an endorsement request. The return value never distinguishes
	 * "no such account" from "that account cannot endorse", so this form
	 * cannot be used to discover who holds an account.
	 *
	 * @return string One of 'sent', 'rate_limited', 'self', 'already', 'invalid'.
	 */
	public static function request_endorsement( $candidate_id, $endorser_email ) {
		$candidate = get_user_by( 'id', absint( $candidate_id ) );
		if ( ! $candidate ) {
			return 'invalid';
		}
		if ( self::request_rate_limited( $candidate->ID ) ) {
			return 'rate_limited';
		}

		$endorser_email = sanitize_email( $endorser_email );
		if ( ! is_email( $endorser_email ) ) {
			return 'invalid';
		}
		if ( strtolower( $endorser_email ) === strtolower( $candidate->user_email ) ) {
			return 'self';
		}

		$endorser = get_user_by( 'email', $endorser_email );

		// Count the attempt before deciding whether it can succeed, so that a
		// probe for valid addresses costs the same as a real request.
		self::record_request_attempt( $candidate->ID );

		if ( ! $endorser || ! self::is_qualified_endorser( $endorser->ID ) ) {
			return 'sent';
		}
		foreach ( self::endorsements( $candidate->ID ) as $record ) {
			if ( absint( $record['endorser'] ?? 0 ) === $endorser->ID ) {
				return 'already';
			}
		}

		$token   = wp_generate_password( 64, false, false );
		$ttl     = max( 1, CYWater_Forum_Settings::get_int( 'endorsement_token_ttl_hours' ) ) * HOUR_IN_SECONDS;
		$expires = time() + $ttl;

		$requests                  = self::pending_requests( $candidate->ID );
		$requests[ $endorser->ID ] = array(
			'hash'      => self::token_hash( $token ),
			'expires'   => $expires,
			'requested' => time(),
		);
		update_user_meta( $candidate->ID, self::REQUESTS_META, $requests );

		self::send_endorsement_request( $candidate, $endorser, $token, $ttl );
		return 'sent';
	}

	private static function send_endorsement_request( WP_User $candidate, WP_User $endorser, $token, $ttl ) {
		$url = add_query_arg(
			array(
				'cywater_endorse' => '1',
				'candidate'       => $candidate->ID,
				'endorser'        => $endorser->ID,
				'token'           => $token,
			),
			self::page_url()
		);

		$candidate_name = $candidate->display_name ? $candidate->display_name : $candidate->user_login;

		wp_mail(
			$endorser->user_email,
			__( 'A CYWater member asked for your endorsement', 'cywater-forum' ),
			sprintf(
				/* translators: 1: candidate name, 2: candidate email, 3: endorsement URL, 4: link lifetime in hours. */
				__( "%1\$s (%2\$s) has asked you to endorse them as a CYWater forum author.\n\nEndorse only someone whose work you know well enough to vouch for. Your name is recorded with the endorsement.\n\nTo endorse them, sign in as yourself and open this single-use link within %4\$d hours:\n%3\$s\n\nIf you do not recognise this person, ignore this message. No endorsement is recorded unless you follow the link.", 'cywater-forum' ),
				$candidate_name,
				$candidate->user_email,
				$url,
				(int) ( $ttl / HOUR_IN_SECONDS )
			),
			self::mail_headers()
		);
	}

	private static function notify_candidate_authorized( $candidate_id ) {
		$candidate = get_user_by( 'id', absint( $candidate_id ) );
		if ( ! $candidate ) {
			return;
		}
		wp_mail(
			$candidate->user_email,
			__( 'You can now publish in the CYWater forum', 'cywater-forum' ),
			sprintf(
				/* translators: %s: forum URL. */
				__( "Your endorsement is recorded and you can now publish forum articles.\n\nWrite and publish here:\n%s\n\nPublishing also requires a current membership and a verified email address.", 'cywater-forum' ),
				(string) get_post_type_archive_link( CYWater_Forum_Content::POST_TYPE )
			),
			self::mail_headers()
		);
	}

	/* ---------------------------------------------------------------------
	 * Redeeming
	 * ------------------------------------------------------------------ */

	public static function process_request() {
		if ( ! is_page( self::PAGE_SLUG ) ) {
			return;
		}

		if ( isset( $_GET['cywater_endorse'], $_GET['candidate'], $_GET['endorser'], $_GET['token'] ) ) {
			self::consume_endorsement_link();
			return;
		}

		if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['cywater_forum_endorsement_request'] ) ) {
			self::process_form();
		}
	}

	private static function consume_endorsement_link() {
		$candidate_id = absint( $_GET['candidate'] );
		$endorser_id  = absint( $_GET['endorser'] );
		$token        = sanitize_text_field( wp_unslash( $_GET['token'] ) );

		if ( ! is_user_logged_in() ) {
			// Send them through sign-in and back to the same link. The token is
			// still single-use and still expires.
			wp_safe_redirect( self::login_url( self::current_link_url( $candidate_id, $endorser_id, $token ) ) );
			exit;
		}

		$state = self::redeem( $candidate_id, $endorser_id, $token, get_current_user_id() );
		wp_safe_redirect( add_query_arg( 'cywater_endorsement', $state, self::page_url() ) );
		exit;
	}

	private static function current_link_url( $candidate_id, $endorser_id, $token ) {
		return add_query_arg(
			array(
				'cywater_endorse' => '1',
				'candidate'       => $candidate_id,
				'endorser'        => $endorser_id,
				'token'           => $token,
			),
			self::page_url()
		);
	}

	/**
	 * @return string One of 'endorsed', 'wrong_account', 'invalid', 'not_qualified', 'already'.
	 */
	public static function redeem( $candidate_id, $endorser_id, $token, $current_user_id ) {
		$candidate_id    = absint( $candidate_id );
		$endorser_id     = absint( $endorser_id );
		$current_user_id = absint( $current_user_id );

		// The link authorises one specific person. Being signed in as anybody
		// else must not spend the token.
		if ( $current_user_id !== $endorser_id ) {
			return 'wrong_account';
		}

		$requests = self::pending_requests( $candidate_id );
		$request  = $requests[ $endorser_id ] ?? null;
		if ( ! is_array( $request ) ) {
			return 'invalid';
		}

		$expected = (string) ( $request['hash'] ?? '' );
		$expires  = absint( $request['expires'] ?? 0 );
		if ( '' === $expected || $expires < time() || ! hash_equals( $expected, self::token_hash( $token ) ) ) {
			return 'invalid';
		}

		// Spend the token before acting on it, so a replay of the same URL
		// cannot be processed twice even if the work below fails.
		unset( $requests[ $endorser_id ] );
		update_user_meta( $candidate_id, self::REQUESTS_META, $requests );

		// Re-check eligibility at redemption time. An endorser may have lapsed
		// between the request and the click.
		if ( ! self::is_qualified_endorser( $endorser_id ) ) {
			return 'not_qualified';
		}

		return self::record_endorsement( $candidate_id, $endorser_id, 'link' ) ? 'endorsed' : 'already';
	}

	private static function process_form() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Sign in before requesting an endorsement.', 'cywater-forum' ), '', array( 'response' => 403 ) );
		}
		if ( ! isset( $_POST['cywater_forum_endorsement_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_forum_endorsement_nonce'] ) ), 'cywater_forum_endorsement' ) ) {
			wp_die( esc_html__( 'This request expired. Please try again.', 'cywater-forum' ), '', array( 'response' => 403 ) );
		}

		$email = isset( $_POST['cywater_endorser_email'] ) ? sanitize_email( wp_unslash( $_POST['cywater_endorser_email'] ) ) : '';
		$state = self::request_endorsement( get_current_user_id(), $email );

		wp_safe_redirect( add_query_arg( 'cywater_endorsement', $state, self::page_url() ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Front end
	 * ------------------------------------------------------------------ */

	public static function endorsement_shortcode() {
		$state    = isset( $_GET['cywater_endorsement'] ) ? sanitize_key( wp_unslash( $_GET['cywater_endorsement'] ) ) : '';
		$messages = array(
			'sent'          => __( 'If that address belongs to a CYWater author who is able to endorse, we have sent them your request.', 'cywater-forum' ),
			'self'          => __( 'You cannot endorse yourself.', 'cywater-forum' ),
			'already'       => __( 'That author has already endorsed you.', 'cywater-forum' ),
			'invalid'       => __( 'That endorsement link is invalid, already used, or expired.', 'cywater-forum' ),
			'rate_limited'  => __( 'You have sent several endorsement requests today. Please try again tomorrow.', 'cywater-forum' ),
			'endorsed'      => __( 'Your endorsement has been recorded. Thank you.', 'cywater-forum' ),
			'wrong_account' => __( 'This endorsement link belongs to a different account. Sign in as the person who was asked to endorse.', 'cywater-forum' ),
			'not_qualified' => __( 'This account is not currently able to endorse a new author.', 'cywater-forum' ),
		);

		if ( ! is_user_logged_in() ) {
			return '<div class="cywater-register"><h2>' . esc_html__( 'Forum endorsement', 'cywater-forum' ) . '</h2><p>' . esc_html__( 'Sign in to request or give an endorsement.', 'cywater-forum' ) . '</p><a class="btn btn-primary" href="' . esc_url( self::login_url( self::page_url() ) ) . '">' . esc_html__( 'Sign in', 'cywater-forum' ) . '</a></div>';
		}

		$user_id  = get_current_user_id();
		$required = max( 1, CYWater_Forum_Settings::get_int( 'endorsements_required' ) );
		$have     = count( self::endorsements( $user_id ) );
		$blockers = CYWater_Forum_Roles::publish_blockers( $user_id );

		ob_start();
		?>
		<div class="cywater-register forum-endorsement">
			<h2><?php esc_html_e( 'Forum endorsement', 'cywater-forum' ); ?></h2>

			<?php if ( isset( $messages[ $state ] ) ) : ?>
				<div class="notice" role="status"><p><?php echo esc_html( $messages[ $state ] ); ?></p></div>
			<?php endif; ?>

			<?php if ( array() === $blockers ) : ?>
				<p><?php esc_html_e( 'You are able to publish forum articles.', 'cywater-forum' ); ?></p>
				<a class="btn btn-primary" href="<?php echo esc_url( (string) get_post_type_archive_link( CYWater_Forum_Content::POST_TYPE ) ); ?>"><?php esc_html_e( 'Go to the forum', 'cywater-forum' ); ?></a>
			<?php else : ?>
				<p><?php esc_html_e( 'CYWater forum authors are endorsed by an existing author, in the way arXiv endorsement works. Ask someone who knows your work.', 'cywater-forum' ); ?></p>
				<ul class="forum-endorsement-status">
					<li><?php echo esc_html( sprintf( /* translators: 1: endorsements held, 2: endorsements required. */ __( 'Endorsements: %1$d of %2$d', 'cywater-forum' ), $have, $required ) ); ?></li>
					<?php if ( in_array( 'membership_inactive', $blockers, true ) ) : ?>
						<li><?php esc_html_e( 'An active membership is required.', 'cywater-forum' ); ?> <a href="<?php echo esc_url( home_url( '/membership/' ) ); ?>"><?php esc_html_e( 'View membership', 'cywater-forum' ); ?></a></li>
					<?php endif; ?>
					<?php if ( in_array( 'email_unverified', $blockers, true ) ) : ?>
						<li>
							<?php esc_html_e( 'Your email address is not verified.', 'cywater-forum' ); ?>
							<?php
							// The verification page is created by PMPro setup, so it is
							// absent wherever PMPro is inactive. Link to it only when it
							// is really there rather than pointing at a 404.
							if ( get_page_by_path( 'verify-email' ) ) :
								?>
								<a href="<?php echo esc_url( home_url( '/verify-email/' ) ); ?>"><?php esc_html_e( 'Verify email', 'cywater-forum' ); ?></a>
							<?php endif; ?>
						</li>
					<?php endif; ?>
				</ul>

				<?php if ( in_array( 'not_endorsed', $blockers, true ) ) : ?>
					<form method="post" action="">
						<?php wp_nonce_field( 'cywater_forum_endorsement', 'cywater_forum_endorsement_nonce' ); ?>
						<input type="hidden" name="cywater_forum_endorsement_request" value="1" />
						<p>
							<label for="cywater-endorser-email"><?php esc_html_e( 'Email address of the author you are asking', 'cywater-forum' ); ?></label>
							<input type="email" id="cywater-endorser-email" name="cywater_endorser_email" required autocomplete="off" />
						</p>
						<button class="btn btn-primary" type="submit"><?php esc_html_e( 'Send endorsement request', 'cywater-forum' ); ?></button>
					</form>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( self::is_qualified_endorser( $user_id ) ) : ?>
				<p class="forum-endorsement-note"><?php esc_html_e( 'You are able to endorse new authors. You will receive a single-use link by email when someone asks you.', 'cywater-forum' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	public static function request_rate_limited( $user_id ) {
		$window = absint( get_user_meta( absint( $user_id ), self::WINDOW_META, true ) );
		$count  = absint( get_user_meta( absint( $user_id ), self::COUNT_META, true ) );
		$max    = max( 1, CYWater_Forum_Settings::get_int( 'endorsement_requests_per_day' ) );
		return $window && time() - $window < DAY_IN_SECONDS && $count >= $max;
	}

	private static function record_request_attempt( $user_id ) {
		$user_id = absint( $user_id );
		$window  = absint( get_user_meta( $user_id, self::WINDOW_META, true ) );
		$count   = absint( get_user_meta( $user_id, self::COUNT_META, true ) );
		if ( ! $window || time() - $window >= DAY_IN_SECONDS ) {
			$window = time();
			$count  = 0;
			update_user_meta( $user_id, self::WINDOW_META, $window );
		}
		update_user_meta( $user_id, self::COUNT_META, $count + 1 );
	}

	private static function token_hash( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	/**
	 * @return array<int, string>
	 */
	private static function mail_headers() {
		/**
		 * Filter the forum's outgoing mail identity.
		 *
		 * Forum participation is a member-program responsibility. Keep its
		 * replies with Membership rather than the web-platform owner identity.
		 *
		 * @param array<int, string> $headers
		 */
		return apply_filters(
			'cywater_forum_mail_headers',
			array(
				'From: CYWater Community <membership@cywater.org>',
				'Reply-To: CYWater Membership <membership@cywater.org>',
			)
		);
	}

	private static function login_url( $redirect ) {
		$url = function_exists( 'pmpro_url' ) ? pmpro_url( 'login' ) : wp_login_url();
		return add_query_arg( 'redirect_to', wp_validate_redirect( $redirect, home_url( '/' ) ), $url );
	}
}
