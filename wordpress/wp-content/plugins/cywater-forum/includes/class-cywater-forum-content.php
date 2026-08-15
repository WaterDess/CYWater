<?php
/**
 * Forum article content model.
 *
 * The forum is an authored publication, not a thread board: one post type with
 * an editorial category and a free-form topic, browsable by author, category,
 * or topic. Discussion hangs off an article through core comments rather than
 * through a second content model.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Forum_Content {
	public const POST_TYPE = 'cyw_forum_post';
	public const CATEGORY  = 'cyw_forum_category';
	public const TOPIC     = 'cyw_forum_topic';

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_content_types' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'order_archives' ) );
		add_action( 'cywater_after_core_setup', array( __CLASS__, 'seed_categories' ), 30 );
		add_filter( 'cywater_public_author_archive_allowed', array( __CLASS__, 'allow_author_archive' ), 10, 2 );
		add_filter( 'wp_untrash_post_status', array( __CLASS__, 'restore_previous_status' ), 10, 3 );
	}

	/**
	 * Restoring a forum article from the bin puts it back as it was.
	 *
	 * WordPress calls this `wp_untrash_post_status`; the similarly named
	 * `wp_untrash_post_set_previous_status` is a different hook and registering
	 * against it does nothing at all.
	 *
	 * Since WordPress 5.6 `wp_untrash_post()` restores everything to `draft`
	 * regardless of what it was before, so a published article that is binned
	 * and then restored silently vanishes from the public archive with no
	 * indication of why. For a member-authored article that reads as data loss.
	 *
	 * This is a restore, not a new publication, so it does not re-run the
	 * publishing gate: the article was already public before it was binned.
	 *
	 * @param string $new_status      Status WordPress intends to use.
	 * @param int    $post_id         Post being restored.
	 * @param string $previous_status Status it held before it was trashed.
	 * @return string
	 */
	public static function restore_previous_status( $new_status, $post_id, $previous_status ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return $new_status;
		}
		return $previous_status ? $previous_status : $new_status;
	}

	/**
	 * Open an author archive only for someone who has actually published here.
	 *
	 * cywater-environment 404s author archives so that numeric account
	 * discovery cannot enumerate members. Reading by author is a stated forum
	 * requirement, so the forum opts one account in at a time rather than
	 * turning the protection off. A member who has published nothing still
	 * 404s, which is what keeps enumeration closed.
	 *
	 * Only the byline is exposed. Affiliation, ORCID, and the rest of the
	 * professional profile stay behind the member directory's opt-in, because
	 * publishing an article is consent to a byline, not to a public profile.
	 *
	 * @param bool $allowed
	 * @param int  $author_id
	 * @return bool
	 */
	public static function allow_author_archive( $allowed, $author_id ) {
		if ( $allowed ) {
			return true;
		}

		/*
		 * Refuse the numeric `?author=N` form outright.
		 *
		 * WordPress's redirect_canonical turns `?author=1` into
		 * `/author/<user_nicename>/`, and the nicename is derived from the
		 * login. Allowing the numeric form back would hand out the
		 * administrator's login slug to anyone who guesses an id — the exact
		 * enumeration that cywater-environment closes, against the one account
		 * that currently has no MFA. Only the pretty permalink is served, and
		 * only for an account that has actually published.
		 */
		if ( isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		/*
		 * Staff never get a public archive. They own the imported News posts, so
		 * opening one would both expose an operations account and fill the page
		 * with content that is not forum writing.
		 */
		if ( CYWater_Forum_Roles::is_staff( $author_id ) ) {
			return false;
		}

		return self::published_count( $author_id ) > 0;
	}

	public static function register_content_types() {
		/*
		 * Taxonomies are registered BEFORE the post type, and the order is
		 * load-bearing.
		 *
		 * WordPress builds rewrite rules in permastruct registration order. The
		 * post type's slug is `forum` and the taxonomies nest under it, so
		 * registering the post type first puts its attachment rule
		 * `forum/[^/]+/([^/]+)/?$` ahead of `forum/category/([^/]+)/?$`. Every
		 * category and topic URL then resolves as an attachment and 404s.
		 */
		register_taxonomy(
			self::CATEGORY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Forum categories', 'cywater-forum' ),
					'singular_name' => __( 'Forum category', 'cywater-forum' ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'forum/category', 'with_front' => false ),
				'capabilities'      => self::taxonomy_capabilities(),
			)
		);

		register_taxonomy(
			self::TOPIC,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Forum topics', 'cywater-forum' ),
					'singular_name' => __( 'Forum topic', 'cywater-forum' ),
				),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'forum/topic', 'with_front' => false ),
				'capabilities'      => self::taxonomy_capabilities(),
			)
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Forum articles', 'cywater-forum' ),
					'singular_name' => __( 'Forum article', 'cywater-forum' ),
					'add_new_item'  => __( 'Add new forum article', 'cywater-forum' ),
					'edit_item'     => __( 'Edit forum article', 'cywater-forum' ),
					'view_item'     => __( 'View forum article', 'cywater-forum' ),
					'search_items'  => __( 'Search forum articles', 'cywater-forum' ),
				),
				'public'          => true,
				'show_in_rest'    => true,
				'has_archive'     => 'forum',
				'rewrite'         => array( 'slug' => 'forum', 'with_front' => false ),
				'menu_icon'       => 'dashicons-format-aside',
				'menu_position'   => 6,
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author', 'comments' ),
				'capability_type' => array( 'cyw_forum_post', 'cyw_forum_posts' ),
				'map_meta_cap'    => true,
				'taxonomies'      => array( self::CATEGORY, self::TOPIC ),
			)
		);

		// Keep the archive root stable across hosts and rewrite engines, the
		// same way cywater-core pins /events and /awards.
		add_rewrite_rule( '^forum/?$', 'index.php?post_type=' . self::POST_TYPE, 'top' );
	}

	/**
	 * An author may attach terms to their own article. Creating, renaming, and
	 * deleting terms stays with editors and administrators so the category set
	 * remains an editorial decision rather than a by-product of publishing.
	 *
	 * @return array<string, string>
	 */
	private static function taxonomy_capabilities() {
		return array(
			'manage_terms' => 'manage_cyw_forum_terms',
			'edit_terms'   => 'manage_cyw_forum_terms',
			'delete_terms' => 'manage_cyw_forum_terms',
			'assign_terms' => 'edit_cyw_forum_posts',
		);
	}

	public static function order_archives( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$is_forum_archive = $query->is_post_type_archive( self::POST_TYPE )
			|| $query->is_tax( array( self::CATEGORY, self::TOPIC ) );
		if ( $is_forum_archive ) {
			$query->set( 'posts_per_page', 12 );
			return;
		}
		// An author archive is a forum byline page and shows forum writing only.
		// Including core posts would list imported News under a Forum
		// breadcrumb and contradict the article count in the header.
		if ( $query->is_author() ) {
			$query->set( 'post_type', self::POST_TYPE );
			$query->set( 'posts_per_page', 12 );
		}
	}

	/**
	 * Create the starting editorial categories once. Reruns never overwrite an
	 * edited term, and an administrator may add or rename freely afterwards.
	 */
	public static function seed_categories() {
		if ( get_option( 'cywater_forum_category_seed_version' ) ) {
			return;
		}
		$categories = array(
			'Research notes'    => __( 'Findings, methods, and work in progress.', 'cywater-forum' ),
			'Perspectives'      => __( 'Opinion and commentary on water science and policy.', 'cywater-forum' ),
			'Career'            => __( 'Positions, funding, mentoring, and early-career guidance.', 'cywater-forum' ),
			'Community'         => __( 'Association news, meetings, and member activity.', 'cywater-forum' ),
		);
		foreach ( $categories as $name => $description ) {
			if ( ! term_exists( $name, self::CATEGORY ) ) {
				wp_insert_term( $name, self::CATEGORY, array( 'description' => $description ) );
			}
		}
		update_option( 'cywater_forum_category_seed_version', CYWATER_FORUM_VERSION, false );
	}

	/**
	 * Published forum articles by one author. Used by the endorsement policy
	 * and by the author archive header.
	 */
	public static function published_count( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}
		// count_user_posts is a single COUNT query and is called per row on the
		// Users list, so the cheaper core helper is used rather than WP_Query.
		return (int) count_user_posts( $user_id, self::POST_TYPE, true );
	}
}
