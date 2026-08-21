<?php
/**
 * Front-end Forum workspace for ordinary members.
 *
 * The workspace is intentionally separate from WordPress administration.
 * Members create and update their own draft or pending Forum articles here;
 * publication, take-down, restoration, and cross-author work remain in the
 * Community Moderator administration surface.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Forum_Workspace {
	private const PAGE_OPTION        = 'cywater_forum_workspace_page_id';
	private const SETUP_OPTION       = 'cywater_forum_workspace_setup_version';
	private const PAGE_SLUG          = 'forum-workspace';
	private const EDITABLE_STATUSES  = array( 'draft', 'pending' );
	private const OPERATIONS_ROLES   = array(
		'cywater_content_event_editor',
		'cywater_community_moderator',
		'cywater_program_reviewer',
		'cywater_governance_approver',
	);

	public static function register() {
		/*
		 * Run before wp-admin loads its menu so every protected administration
		 * route returns the same explicit denial instead of leaking a partial
		 * Dashboard or failing later during screen-specific menu construction.
		 */
		add_action( 'init', array( __CLASS__, 'deny_nonstaff_admin' ), 20 );
		add_action( 'init', array( __CLASS__, 'maybe_setup_page' ), 30 );
		add_action( 'cywater_after_core_setup', array( __CLASS__, 'setup_page' ), 40 );
		add_action( 'admin_post_cywater_forum_workspace_save', array( __CLASS__, 'handle_save' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'filter_admin_bar' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ) );
	}

	/** Create the one presentation-only Page used by the active theme. */
	public static function setup_page() {
		$page = get_page_by_path( self::PAGE_SLUG, OBJECT, 'page' );
		if ( ! $page instanceof WP_Post ) {
			$page_id = wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => __( 'Forum workspace', 'cywater-forum' ),
					'post_name'      => self::PAGE_SLUG,
					'post_content'   => '',
					'comment_status' => 'closed',
				),
				true
			);
			if ( is_wp_error( $page_id ) ) {
				return $page_id;
			}
			$page = get_post( $page_id );
		}

		if ( $page instanceof WP_Post ) {
			update_option( self::PAGE_OPTION, $page->ID, false );
			update_option( self::SETUP_OPTION, CYWATER_FORUM_VERSION, false );
			return $page->ID;
		}

		return new WP_Error( 'workspace_page_unavailable', __( 'The Forum workspace page could not be prepared.', 'cywater-forum' ) );
	}

	/** Run the page initializer once after each Forum schema release. */
	public static function maybe_setup_page() {
		if ( CYWATER_FORUM_VERSION !== get_option( self::SETUP_OPTION ) ) {
			self::setup_page();
		}
	}

	public static function page_id() {
		$page_id = absint( get_option( self::PAGE_OPTION ) );
		if ( $page_id && 'page' === get_post_type( $page_id ) ) {
			return $page_id;
		}

		$page = get_page_by_path( self::PAGE_SLUG, OBJECT, 'page' );
		return $page instanceof WP_Post ? $page->ID : 0;
	}

	public static function url() {
		$page_id = self::page_id();
		$url     = $page_id ? get_permalink( $page_id ) : home_url( '/' . self::PAGE_SLUG . '/' );
		return $url ? $url : home_url( '/' . self::PAGE_SLUG . '/' );
	}

	public static function is_workspace_request() {
		$page_id = self::page_id();
		return is_page( $page_id ?: self::PAGE_SLUG );
	}

	/**
	 * WordPress administration is reserved for Administrators and explicitly
	 * assigned Operations staff. This deliberately does not use edit_posts:
	 * ordinary members and unrelated built-in roles must not gain wp-admin from
	 * a generic content primitive.
	 */
	public static function can_access_admin( $user_id ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user instanceof WP_User ) {
			return false;
		}
		if ( is_super_admin( $user->ID ) || in_array( 'administrator', (array) $user->roles, true ) || CYWater_Forum_Roles::is_staff( $user->ID ) ) {
			return true;
		}

		$operations_roles = class_exists( 'CYWater_Operations_Roles' )
			? CYWater_Operations_Roles::role_slugs()
			: self::OPERATIONS_ROLES;

		return (bool) array_intersect( (array) $operations_roles, (array) $user->roles );
	}

	/** Exempt only infrastructure endpoints needed by front-end requests. */
	private static function admin_request_is_exempt() {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return true;
		}

		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? wp_basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		return in_array( $script, array( 'admin-post.php', 'admin-ajax.php', 'async-upload.php' ), true );
	}

	public static function deny_nonstaff_admin() {
		if ( ! is_admin() || ! is_user_logged_in() || self::admin_request_is_exempt() || self::can_access_admin( get_current_user_id() ) ) {
			return;
		}

		wp_die(
			esc_html__( 'Sorry, you are not allowed to access this page.' ),
			'',
			array( 'response' => 403 )
		);
	}

	public static function filter_admin_bar( $show ) {
		if ( is_user_logged_in() && ! self::can_access_admin( get_current_user_id() ) ) {
			return false;
		}
		return $show;
	}

	public static function filter_robots( $robots ) {
		if ( self::is_workspace_request() ) {
			$robots['noindex']   = true;
			$robots['nofollow']  = true;
			$robots['noarchive'] = true;
		}
		return $robots;
	}

	/** @return WP_Post[] */
	public static function articles_for_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => CYWater_Forum_Content::POST_TYPE,
				'post_status'    => array( 'draft', 'pending', 'publish' ),
				'author'         => $user_id,
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
	}

	public static function editable_post( $user_id, $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post instanceof WP_Post || CYWater_Forum_Content::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'article_not_found', __( 'That Forum article is unavailable.', 'cywater-forum' ) );
		}
		if ( absint( $post->post_author ) !== absint( $user_id ) ) {
			return new WP_Error( 'article_not_owned', __( 'You can edit only your own Forum articles.', 'cywater-forum' ) );
		}
		if ( ! in_array( $post->post_status, self::EDITABLE_STATUSES, true ) ) {
			return new WP_Error( 'article_read_only', __( 'Published and taken-down Forum articles are read-only for members.', 'cywater-forum' ) );
		}
		return $post;
	}

	/**
	 * Save through the front-end service boundary.
	 *
	 * @param int                 $user_id             Acting member.
	 * @param array<string,mixed> $input               Allowlisted article fields.
	 * @param array<string,mixed>|int $cover           Optional staged protected cover record.
	 * @return int|WP_Error
	 */
	public static function save_article( $user_id, $input, $cover = 0 ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! CYWater_Forum_Roles::can_submit( $user_id ) ) {
			return new WP_Error( 'submission_blocked', __( 'This account is not currently eligible to submit Forum articles.', 'cywater-forum' ) );
		}

		$post_id = absint( $input['post_id'] ?? 0 );
		if ( $post_id ) {
			$existing = self::editable_post( $user_id, $post_id );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}
		}

		$requested_status = sanitize_key( (string) ( $input['status'] ?? 'draft' ) );
		if ( ! in_array( $requested_status, self::EDITABLE_STATUSES, true ) ) {
			return new WP_Error( 'status_forbidden', __( 'Members may save a draft or submit it for review; they cannot publish directly.', 'cywater-forum' ) );
		}

		$title   = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		$content = sanitize_textarea_field( (string) ( $input['content'] ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'title_required', __( 'Add an article title.', 'cywater-forum' ) );
		}
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'content_required', __( 'Add the article text.', 'cywater-forum' ) );
		}

		$category_id = absint( $input['category_id'] ?? 0 );
		$topic_id    = absint( $input['topic_id'] ?? 0 );
		if ( $category_id && ! term_exists( $category_id, CYWater_Forum_Content::CATEGORY ) ) {
			return new WP_Error( 'category_invalid', __( 'Choose an existing Forum category.', 'cywater-forum' ) );
		}
		if ( $topic_id && ! term_exists( $topic_id, CYWater_Forum_Content::TOPIC ) ) {
			return new WP_Error( 'topic_invalid', __( 'Choose an existing Forum topic.', 'cywater-forum' ) );
		}

		$post_data = array(
			'ID'             => $post_id,
			'post_type'      => CYWater_Forum_Content::POST_TYPE,
			'post_author'    => $user_id,
			'post_title'     => $title,
			'post_content'   => $content,
			'post_status'    => $requested_status,
			'comment_status' => 'open',
		);
		$saved_id  = wp_insert_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $saved_id ) ) {
			return $saved_id;
		}

		$category_result = wp_set_object_terms( $saved_id, $category_id ? array( $category_id ) : array(), CYWater_Forum_Content::CATEGORY, false );
		$topic_result    = wp_set_object_terms( $saved_id, $topic_id ? array( $topic_id ) : array(), CYWater_Forum_Content::TOPIC, false );
		if ( is_wp_error( $category_result ) || is_wp_error( $topic_result ) ) {
			return new WP_Error( 'terms_not_saved', __( 'The article was saved, but its Forum category or topic could not be updated.', 'cywater-forum' ) );
		}

		if ( $cover ) {
			$cover_result = CYWater_Forum_Covers::replace( $saved_id, $cover, $user_id );
			if ( is_wp_error( $cover_result ) ) {
				return $cover_result;
			}
		}

		return absint( $saved_id );
	}

	private static function upload_cover( $user_id, $post_id = 0 ) {
		return CYWater_Forum_Covers::receive_upload( $user_id, 'forum_cover', $post_id );
	}

	public static function handle_save() {
		$user_id = get_current_user_id();
		$post_id = absint( $_POST['forum_post_id'] ?? 0 );
		if ( ! $user_id ) {
			auth_redirect();
		}
		check_admin_referer( 'cywater_forum_workspace_save_' . $post_id, 'cywater_forum_workspace_nonce' );

		$intent = sanitize_key( (string) ( $_POST['forum_intent'] ?? 'draft' ) );
		$status = 'pending' === $intent ? 'pending' : 'draft';
		$cover = self::upload_cover( $user_id, $post_id );
		if ( is_wp_error( $cover ) ) {
			self::redirect_with_result( 'error', $cover->get_error_code(), $post_id );
		}

		$result = self::save_article(
			$user_id,
			array(
				'post_id'     => $post_id,
				'title'       => wp_unslash( $_POST['forum_title'] ?? '' ),
				'content'     => wp_unslash( $_POST['forum_content'] ?? '' ),
				'category_id' => absint( $_POST['forum_category'] ?? 0 ),
				'topic_id'    => absint( $_POST['forum_topic'] ?? 0 ),
				'status'      => $status,
			),
			$cover
		);

		if ( is_wp_error( $result ) ) {
			CYWater_Forum_Covers::discard( $cover );
			self::redirect_with_result( 'error', $result->get_error_code(), $post_id );
		}

		self::redirect_with_result( 'notice', 'pending' === $status ? 'submitted' : 'saved', absint( $result ) );
	}

	private static function redirect_with_result( $type, $code, $post_id = 0 ) {
		$args = array( 'forum_' . sanitize_key( $type ) => sanitize_key( $code ) );
		if ( $post_id ) {
			$args['edit'] = absint( $post_id );
		}
		wp_safe_redirect( add_query_arg( $args, self::url() ) );
		exit;
	}

	public static function status_label( $status ) {
		$labels = array(
			'draft'   => __( 'Draft', 'cywater-forum' ),
			'pending' => __( 'Pending review', 'cywater-forum' ),
			'publish' => __( 'Published', 'cywater-forum' ),
		);
		return $labels[ (string) $status ] ?? __( 'Unavailable', 'cywater-forum' );
	}

	public static function result_message( $type, $code ) {
		$messages = array(
			'notice' => array(
				'saved'     => __( 'Draft saved.', 'cywater-forum' ),
				'submitted' => __( 'Article submitted for Community Moderator review.', 'cywater-forum' ),
			),
			'error'  => array(
				'submission_blocked' => __( 'Your account is not currently eligible to submit Forum articles.', 'cywater-forum' ),
				'article_not_found'   => __( 'That Forum article is unavailable.', 'cywater-forum' ),
				'article_not_owned'   => __( 'You can edit only your own Forum articles.', 'cywater-forum' ),
				'article_read_only'   => __( 'Published and taken-down articles are read-only for members.', 'cywater-forum' ),
				'status_forbidden'    => __( 'Members cannot publish Forum articles directly.', 'cywater-forum' ),
				'title_required'      => __( 'Add an article title.', 'cywater-forum' ),
				'content_required'    => __( 'Add the article text.', 'cywater-forum' ),
				'category_invalid'    => __( 'Choose an existing Forum category.', 'cywater-forum' ),
				'topic_invalid'       => __( 'Choose an existing Forum topic.', 'cywater-forum' ),
				'terms_not_saved'     => __( 'The article was saved, but its category or topic needs attention.', 'cywater-forum' ),
				'cover_invalid'       => __( 'The selected cover image is unavailable.', 'cywater-forum' ),
				'cover_upload_failed' => __( 'The cover image upload did not complete.', 'cywater-forum' ),
				'cover_too_large'     => __( 'The cover image must be 5 MB or smaller.', 'cywater-forum' ),
				'cover_rate_limited'  => __( 'Too many cover images were uploaded recently. Please try again later.', 'cywater-forum' ),
				'cover_quota_exceeded'=> __( 'This account has reached its protected Forum cover-image storage limit.', 'cywater-forum' ),
				'cover_storage_unavailable' => __( 'The protected cover-image store is temporarily unavailable.', 'cywater-forum' ),
			),
		);
		return $messages[ sanitize_key( $type ) ][ sanitize_key( $code ) ] ?? '';
	}
}
