<?php
/**
 * Questions and replies on forum articles.
 *
 * Core comments are used deliberately rather than a bespoke table. Core brings
 * threading, the moderation queue, spam hooks, notification mail, and — the one
 * that actually matters here — automatic participation in WordPress personal
 * data export and erasure. cywater-membership already relies on that machinery,
 * and a private comment table would sit outside it and quietly break a privacy
 * guarantee that has already been tested on staging.
 *
 * Discussion is scoped to the forum post type. News, Events, Awards, and Board
 * roles stay closed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Forum_Comments {
	public const AI_REVIEW_HOOK = 'cywater_forum_ai_review_comment';

	public static function register() {
		add_filter( 'comments_open', array( __CLASS__, 'comments_open' ), 10, 2 );
		add_filter( 'pings_open', '__return_false' );
		add_filter( 'pre_comment_approved', array( __CLASS__, 'moderation_decision' ), 10, 2 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'gate_comment_moderation' ), 10, 4 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'gate_rest_comment_moderation' ), 10, 3 );
		add_filter( 'rest_comment_query', array( __CLASS__, 'exclude_forum_from_nonstaff_comment_query' ), 10, 2 );
		add_filter( 'comments_list_table_query_args', array( __CLASS__, 'exclude_forum_from_nonstaff_comment_query' ), 10, 1 );
		add_action( 'comment_post', array( __CLASS__, 'schedule_ai_review' ), 10, 3 );
		// `get_default_comment_status` is the filter; `default_comment_status`
		// is the option this filter overrides.
		add_filter( 'get_default_comment_status', array( __CLASS__, 'default_comment_status' ), 10, 2 );
		add_action( 'cywater_after_core_setup', array( __CLASS__, 'apply_discussion_defaults' ), 25 );
	}

	/**
	 * Discussion is available only where both the global policy switch and the
	 * article's own WordPress discussion checkbox are open.
	 *
	 * @param bool $open
	 * @param int  $post_id
	 * @return bool
	 */
	public static function comments_open( $open, $post_id ) {
		if ( CYWater_Forum_Content::POST_TYPE !== get_post_type( $post_id ) ) {
			return false;
		}
		if ( ! CYWater_Forum_Settings::is_enabled( 'comments_enabled' ) ) {
			return false;
		}
		return $open;
	}

	public static function may_comment( $user_id ) {
		return array() === self::participation_blockers( $user_id );
	}

	/**
	 * Forum discussion uses the same verified, active-individual-member boundary
	 * as article submission. Operational moderation access does not create a
	 * membership or waive the participation rule.
	 *
	 * @return array<int, string>
	 */
	public static function participation_blockers( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array( 'signed_out' );
		}

		$blockers = array();
		if ( CYWater_Forum_Settings::is_enabled( 'comments_require_membership' ) && ! CYWater_Forum_Roles::has_active_membership( $user_id ) ) {
			$blockers[] = 'membership_inactive';
		}
		if ( ! class_exists( 'CYWater_Membership_Account_Security' ) || ! CYWater_Membership_Account_Security::is_verified( $user_id ) ) {
			$blockers[] = 'email_unverified';
		}
		return $blockers;
	}

	/**
	 * Decide standing and moderation for one incoming reply.
	 *
	 * Eligibility is enforced here rather than in `preprocess_comment` because
	 * returning a WP_Error is the only rejection that behaves correctly in both
	 * contexts: WordPress renders it as an error page for a front-end
	 * submission, and hands it back as a WP_Error to any programmatic caller.
	 * Calling wp_die() instead would kill an import or a CLI run outright.
	 *
	 * The commenting identity is taken from the comment data, which
	 * wp_handle_comment_submission fills in from the session rather than from
	 * the request body, so it cannot be forged by posting a user id.
	 *
	 * Eligible-member replies follow the configured mode. The accepted staging
	 * policy is `auto`, so their first and later replies are immediately public.
	 *
	 * @param int|string|WP_Error  $approved
	 * @param array<string, mixed> $commentdata
	 * @return int|string|WP_Error
	 */
	public static function moderation_decision( $approved, $commentdata ) {
		$post_id = absint( $commentdata['comment_post_ID'] ?? 0 );
		if ( CYWater_Forum_Content::POST_TYPE !== get_post_type( $post_id ) ) {
			return $approved;
		}

		// An upstream verdict wins. Replacing a flood or duplicate error with
		// "sign in to reply" would tell the visitor the wrong thing.
		if ( is_wp_error( $approved ) || 'spam' === $approved || 'trash' === $approved ) {
			return $approved;
		}

		$user_id = absint( $commentdata['user_id'] ?? $commentdata['user_ID'] ?? 0 );

		if ( ! $user_id ) {
			return new WP_Error(
				'cywater_forum_signin_required',
				__( 'Sign in to reply to a forum article.', 'cywater-forum' ),
				array( 'status' => 403 )
			);
		}

		$blockers = self::participation_blockers( $user_id );
		if ( $blockers ) {
			if ( in_array( 'email_unverified', $blockers, true ) ) {
				return new WP_Error(
					'cywater_forum_verification_required',
					__( 'Verify your email address before replying to a Forum article.', 'cywater-forum' ),
					array( 'status' => 403 )
				);
			}
			return new WP_Error(
				'cywater_forum_membership_required',
				__( 'An active CYWater Student, Professional or Lifetime membership is required to reply.', 'cywater-forum' ),
				array( 'status' => 403 )
			);
		}

		$mode = CYWater_Forum_Settings::get_string( 'comments_moderation_mode' );
		if ( 'auto' === $mode ) {
			return 1;
		}
		if ( 'all' === $mode ) {
			return 0;
		}
		return self::approved_comment_count( $user_id ) > 0 ? 1 : 0;
	}

	public static function approved_comment_count( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}
		return (int) get_comments(
			array(
				'user_id'  => $user_id,
				'post_type' => CYWater_Forum_Content::POST_TYPE,
				'status'   => 'approve',
				'count'    => true,
			)
		);
	}

	/**
	 * Core maps wp-admin comment actions through edit_comment. Deny that exact
	 * meta capability for Forum replies unless the user is Forum staff, even if
	 * the built-in role still owns WordPress' site-wide moderate_comments cap.
	 *
	 * @param array<int, string> $caps
	 * @param string             $cap
	 * @param int                $user_id
	 * @param array<int, mixed>  $args
	 * @return array<int, string>
	 */
	public static function gate_comment_moderation( $caps, $cap, $user_id, $args ) {
		if ( 'edit_comment' !== $cap || empty( $args[0] ) ) {
			return $caps;
		}

		$comment = get_comment( absint( $args[0] ) );
		if ( ! $comment instanceof WP_Comment || ! self::is_forum_comment( $comment ) ) {
			return $caps;
		}

		return CYWater_Forum_Roles::is_staff( $user_id ) ? $caps : array( 'do_not_allow' );
	}

	/**
	 * WordPress REST checks the primitive moderate_comments capability directly
	 * for ordinary comment mutation. That bypasses map_meta_cap(edit_comment),
	 * so reject mutations and edit-context reads for an exact Forum reply before
	 * the core controller reaches that shortcut. The collection create route is
	 * covered separately: a built-in Editor legitimately retains the site-wide
	 * primitive, but must not use it to create an already-approved Forum reply or
	 * attribute that reply to another account.
	 *
	 * @param mixed           $result
	 * @param WP_REST_Server  $server
	 * @param WP_REST_Request $request
	 * @return mixed
	 */
	public static function gate_rest_comment_moderation( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result ) {
			return $result;
		}

		$route    = rtrim( $request->get_route(), '/' );
		$method   = strtoupper( $request->get_method() );
		$user_id  = get_current_user_id();
		$is_staff = CYWater_Forum_Roles::is_staff( $user_id );

		if ( '/wp/v2/comments' === $route && 'POST' === $method ) {
			$post_id = absint( $request->get_param( 'post' ) );
			if ( CYWater_Forum_Content::POST_TYPE !== get_post_type( $post_id ) || $is_staff ) {
				return $result;
			}

			$foreign_author = $request->has_param( 'author' )
				&& absint( $request->get_param( 'author' ) ) !== absint( $user_id );
			$forbidden_identity = false;
			foreach ( array( 'author_ip', 'author_name', 'author_email', 'author_url' ) as $identity_field ) {
				if ( $request->has_param( $identity_field ) ) {
					$forbidden_identity = true;
					break;
				}
			}
			$invalid_type = $request->has_param( 'type' )
				&& 'comment' !== sanitize_key( (string) $request->get_param( 'type' ) );

			if ( $request->has_param( 'status' ) || $foreign_author || $forbidden_identity || $invalid_type ) {
				return new WP_Error(
					'cywater_forum_comment_submission_forbidden',
					__( 'Forum replies must use your signed-in identity and the Forum moderation workflow.', 'cywater-forum' ),
					array( 'status' => 403 )
				);
			}

			return $result;
		}

		if ( $is_staff || ! preg_match( '#^/wp/v2/comments/(\\d+)$#', $route, $matches ) ) {
			return $result;
		}

		$edit_context = 'GET' === $method && 'edit' === $request->get_param( 'context' );
		if ( ! in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) && ! $edit_context ) {
			return $result;
		}

		$comment = get_comment( absint( $matches[1] ) );
		if ( ! $comment instanceof WP_Comment || ! self::is_forum_comment( $comment ) ) {
			return $result;
		}

		return new WP_Error(
			'cywater_forum_comment_moderation_forbidden',
			__( 'Only CYWater Forum moderators may review or change Forum replies.', 'cywater-forum' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Keep pending Forum replies out of the generic core comment queues for a
	 * built-in Editor who may legitimately retain moderate_comments for other
	 * post types. Mutation is independently denied above; this also avoids
	 * disclosing held Forum replies and their moderation metadata.
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public static function exclude_forum_from_nonstaff_comment_query( $args ) {
		if ( ! current_user_can( 'moderate_comments' ) || CYWater_Forum_Roles::is_staff( get_current_user_id() ) ) {
			return $args;
		}

		$allowed_post_types = array_values(
			array_diff(
				get_post_types( array(), 'names' ),
				array( CYWater_Forum_Content::POST_TYPE )
			)
		);
		$requested_post_types = $args['post_type'] ?? array();
		if ( $requested_post_types && 'any' !== $requested_post_types ) {
			$allowed_post_types = array_values( array_intersect( (array) $requested_post_types, $allowed_post_types ) );
		}
		// An empty post_type array disables the constraint in WP_Comment_Query.
		$args['post_type'] = $allowed_post_types ?: array( '__cywater_no_allowed_post_type__' );
		return $args;
	}

	private static function is_forum_comment( $comment ) {
		return CYWater_Forum_Content::POST_TYPE === get_post_type( absint( $comment->comment_post_ID ) );
	}

	/**
	 * Hand a held reply to the AI review seam.
	 *
	 * Nothing is implemented behind this hook yet. It is scheduled now so that
	 * the later review pass does not have to backfill events for replies that
	 * were held before it existed.
	 *
	 * @param int        $comment_id
	 * @param int|string $approved
	 */
	public static function schedule_ai_review( $comment_id, $approved, $commentdata ) {
		if ( 1 === (int) $approved ) {
			return;
		}
		if ( ! CYWater_Forum_Settings::is_enabled( 'ai_comment_review_enabled' ) ) {
			return;
		}
		$post_id = absint( $commentdata['comment_post_ID'] ?? 0 );
		if ( CYWater_Forum_Content::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$delay = max( 1, CYWater_Forum_Settings::get_int( 'ai_comment_review_delay_hours' ) ) * HOUR_IN_SECONDS;
		if ( ! wp_next_scheduled( self::AI_REVIEW_HOOK, array( absint( $comment_id ) ) ) ) {
			wp_schedule_single_event( time() + $delay, self::AI_REVIEW_HOOK, array( absint( $comment_id ) ) );
		}
	}

	/**
	 * Align the WordPress discussion options with the forum policy at setup
	 * time. These are global options, so they are written once rather than
	 * enforced on every request.
	 */
	public static function apply_discussion_defaults() {
		$mode = CYWater_Forum_Settings::get_string( 'comments_moderation_mode' );
		update_option( 'comment_registration', 1 );
		update_option( 'require_name_email', 0 );
		update_option( 'close_comments_for_old_posts', 0 );
		update_option( 'thread_comments', 1 );
		update_option( 'thread_comments_depth', 3 );
		update_option( 'comment_moderation', 'all' === $mode ? 1 : 0 );
		update_option( 'comment_previously_approved', 'first' === $mode ? 1 : 0 );
	}

	/**
	 * New forum articles open for discussion; everything else is created closed.
	 * An author can still untick the box on an individual article.
	 *
	 * `comments_open` already refuses discussion elsewhere, so this is belt and
	 * braces — but without it a new News post is stored as `open` while
	 * behaving as closed, which reads as a bug in the editor.
	 *
	 * @param string $status
	 * @param string $post_type
	 * @return string
	 */
	public static function default_comment_status( $status, $post_type ) {
		if ( CYWater_Forum_Content::POST_TYPE === $post_type ) {
			return CYWater_Forum_Settings::is_enabled( 'comments_enabled' ) ? 'open' : 'closed';
		}
		return 'closed';
	}
}
