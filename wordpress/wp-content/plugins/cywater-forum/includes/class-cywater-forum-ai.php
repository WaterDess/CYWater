<?php
/**
 * AI seam. Nothing is implemented here.
 *
 * This file exists so the later per-viewer reaction work has a fixed contract
 * to attach to, and so the failure behaviour is settled before any provider is
 * connected. No API is called, no key is read, no content is generated, and no
 * data leaves the site.
 *
 * ---------------------------------------------------------------------------
 * Design that the implementation must honour
 * ---------------------------------------------------------------------------
 *
 * 1. The reaction never renders server-side. The cached article HTML is
 *    identical for every viewer, so a page cache stays fully effective. The
 *    per-viewer variation lives entirely in the uncached REST response.
 *
 * 2. Absence is the default. If the provider is unconfigured, over budget, rate
 *    limited, slow, or broken, the endpoint answers 204 and the page shows
 *    nothing: no empty container, no heading, no spinner, no error. A reader
 *    must not be able to tell that a feature exists at all.
 *
 * 3. The panel is never styled as a comment. Because it appears for some
 *    viewers and not others, next to real replies from named scientists, it
 *    must be visually and textually distinct and explicitly labelled as
 *    machine-generated.
 *
 * 4. The daily token budget is a hard ceiling, not a warning. Crossing it
 *    switches the feature off for the rest of the window by the same silent
 *    path as any other failure.
 *
 * To implement: hook `cywater_forum_ai_reaction` with a provider that returns a
 * string, and enqueue a small script that fetches the route below and injects
 * the result at the `cywater_forum_after_article` action's mount point. Until
 * then the route is registered but always answers 204.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Forum_AI {
	public const REST_NAMESPACE = 'cywater/v1';
	public const REST_ROUTE     = '/forum-reaction/(?P<id>\d+)';

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( CYWater_Forum_Comments::AI_REVIEW_HOOK, array( __CLASS__, 'review_held_comment' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_reaction' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => static function ( $value ) {
							return is_numeric( $value );
						},
					),
				),
			)
		);
	}

	/**
	 * Always 204 until a provider is attached.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function get_reaction( $request ) {
		$empty = new WP_REST_Response( null, 204 );

		if ( ! CYWater_Forum_Settings::is_enabled( 'ai_reaction_enabled' ) ) {
			return $empty;
		}

		$post = get_post( absint( $request['id'] ) );
		if ( ! $post || CYWater_Forum_Content::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return $empty;
		}

		/**
		 * Return a viewer-specific reaction to a forum article, or an empty
		 * string to render nothing.
		 *
		 * No provider is registered. Any implementation must fail closed:
		 * return '' rather than an error message, and respect the daily token
		 * budget in the forum settings.
		 *
		 * @param string  $reaction Empty by default.
		 * @param WP_Post $post     The article being viewed.
		 * @param int     $user_id  Current viewer, 0 when signed out.
		 */
		$reaction = (string) apply_filters( 'cywater_forum_ai_reaction', '', $post, get_current_user_id() );

		if ( '' === trim( $reaction ) ) {
			return $empty;
		}

		return new WP_REST_Response(
			array(
				'reaction' => wp_kses_post( $reaction ),
				'label'    => __( 'AI-generated perspective', 'cywater-forum' ),
			),
			200
		);
	}

	/**
	 * Scheduled look at a reply that is still waiting for a human moderator.
	 *
	 * Not implemented. When it is, it may only recommend: a machine must not be
	 * the thing that publishes a named member's words. The intended shape is to
	 * record a recommendation the moderator sees in the queue.
	 *
	 * @param int $comment_id
	 */
	public static function review_held_comment( $comment_id ) {
		if ( ! CYWater_Forum_Settings::is_enabled( 'ai_comment_review_enabled' ) ) {
			return;
		}

		$comment = get_comment( absint( $comment_id ) );
		if ( ! $comment || '0' !== (string) $comment->comment_approved ) {
			return;
		}

		/**
		 * Advise on a held reply. Return 'approve', 'hold', or '' for no opinion.
		 *
		 * Approval here is advisory only; the caller records it for a human and
		 * does not change the comment's status.
		 *
		 * @param string     $recommendation Empty by default.
		 * @param WP_Comment $comment
		 */
		$recommendation = (string) apply_filters( 'cywater_forum_ai_comment_recommendation', '', $comment );

		if ( in_array( $recommendation, array( 'approve', 'hold' ), true ) ) {
			update_comment_meta( $comment->comment_ID, 'cyw_forum_ai_recommendation', $recommendation );
		}
	}
}
