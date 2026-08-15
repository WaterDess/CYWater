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
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}
		if ( ! CYWater_Forum_Settings::is_enabled( 'comments_require_membership' ) ) {
			return true;
		}
		if ( CYWater_Forum_Roles::is_staff( $user_id ) ) {
			return true;
		}
		return CYWater_Forum_Roles::has_active_membership( $user_id );
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
	 * Moderation then holds a member's first reply and lets them through
	 * afterwards. Pre-moderating every reply on a volunteer-run association site
	 * produces a queue nobody drains, and silence reads as a broken feature.
	 * Turning `comments_hold_first` off restores full pre-moderation.
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
				403
			);
		}

		if ( ! self::may_comment( $user_id ) ) {
			return new WP_Error(
				'cywater_forum_membership_required',
				__( 'An active CYWater membership is required to reply.', 'cywater-forum' ),
				403
			);
		}

		if ( CYWater_Forum_Roles::is_staff( $user_id ) ) {
			return 1;
		}

		if ( ! CYWater_Forum_Settings::is_enabled( 'comments_hold_first' ) ) {
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
				'user_id' => $user_id,
				'status'  => 'approve',
				'count'   => true,
			)
		);
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
		update_option( 'comment_registration', 1 );
		update_option( 'require_name_email', 0 );
		update_option( 'close_comments_for_old_posts', 0 );
		update_option( 'thread_comments', 1 );
		update_option( 'thread_comments_depth', 3 );
		update_option( 'comment_moderation', CYWater_Forum_Settings::is_enabled( 'comments_hold_first' ) ? 0 : 1 );
		update_option( 'comment_previously_approved', CYWater_Forum_Settings::is_enabled( 'comments_hold_first' ) ? 1 : 0 );
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
