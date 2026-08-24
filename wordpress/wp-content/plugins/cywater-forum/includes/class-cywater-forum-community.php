<?php
/**
 * Lightweight Forum community features.
 *
 * Likes are an interaction record, not a second publishing system. Public
 * author pages use an opaque Forum identifier instead of WordPress' login-
 * derived author slug, and the account panel projects existing Forum data
 * without copying posts or membership state.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Forum_Community {
	private const SCHEMA_OPTION     = 'cywater_forum_community_schema_version';
	private const AUTHOR_TOKEN_META = 'cywater_forum_public_token';
	private const AUTHOR_QUERY_VAR  = 'cywater_forum_member';
	private const AUTHOR_TOKEN_RX   = '/^[a-f0-9]{32}$/';

	private static $current_author = null;
	private static $account_rendered = false;

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_routes' ), 5 );
		add_action( 'init', array( __CLASS__, 'maybe_install_schema' ), 6 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'template_include', array( __CLASS__, 'author_template' ), 20 );
		add_filter( 'document_title_parts', array( __CLASS__, 'author_document_title' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'ensure_author_token_on_publish' ), 10, 3 );
		add_action( 'cywater_forum_after_article', array( __CLASS__, 'render_article_engagement' ), 20 );
		add_action( 'admin_post_cywater_forum_toggle_like', array( __CLASS__, 'handle_toggle_like' ) );
		add_action( 'admin_post_nopriv_cywater_forum_toggle_like', array( __CLASS__, 'handle_signed_out_like' ) );
		add_action( 'admin_post_cywater_forum_clear_likes', array( __CLASS__, 'handle_clear_likes' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_account_panel' ), 40 );
		add_filter( 'manage_' . CYWater_Forum_Content::POST_TYPE . '_posts_columns', array( __CLASS__, 'admin_columns' ) );
		add_action( 'manage_' . CYWater_Forum_Content::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'admin_column' ), 10, 2 );
		add_action( 'add_meta_boxes_' . CYWater_Forum_Content::POST_TYPE, array( __CLASS__, 'add_engagement_meta_box' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_post_likes' ), 10, 2 );
		add_action( 'delete_user', array( __CLASS__, 'delete_user_likes' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'cywater_forum_likes';
	}

	public static function install_schema() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			post_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (post_id,user_id),
			KEY user_created (user_id,created_at),
			KEY post_created (post_id,created_at)
		) {$charset};";
		dbDelta( $sql );
		update_option( self::SCHEMA_OPTION, CYWATER_FORUM_VERSION, false );
	}

	public static function maybe_install_schema() {
		if ( CYWATER_FORUM_VERSION !== get_option( self::SCHEMA_OPTION ) ) {
			self::install_schema();
		}
	}

	public static function register_routes() {
		add_rewrite_rule(
			'^forum/member/([a-f0-9]{32})/?$',
			'index.php?' . self::AUTHOR_QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	public static function query_vars( $vars ) {
		$vars[] = self::AUTHOR_QUERY_VAR;
		return $vars;
	}

	public static function is_author_request() {
		return (bool) get_query_var( self::AUTHOR_QUERY_VAR );
	}

	public static function author_template( $template ) {
		if ( ! self::is_author_request() ) {
			return $template;
		}

		$author = self::current_author();
		if ( ! $author instanceof WP_User ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return get_404_template();
		}

		$community_template = locate_template( 'forum-member.php', false, false );
		if ( $community_template ) {
			status_header( 200 );
			return $community_template;
		}

		return $template;
	}

	public static function author_document_title( $parts ) {
		if ( ! self::is_author_request() ) {
			return $parts;
		}

		$author = self::current_author();
		$parts['title'] = $author instanceof WP_User
			? sprintf( __( '%s — Forum author', 'cywater-forum' ), $author->display_name )
			: __( 'Forum author unavailable', 'cywater-forum' );
		return $parts;
	}

	public static function current_author() {
		if ( self::$current_author instanceof WP_User ) {
			return self::$current_author;
		}

		$token = sanitize_text_field( (string) get_query_var( self::AUTHOR_QUERY_VAR ) );
		if ( ! preg_match( self::AUTHOR_TOKEN_RX, $token ) ) {
			return null;
		}

		$users = get_users(
			array(
				'number'     => 1,
				'meta_key'   => self::AUTHOR_TOKEN_META,
				'meta_value' => $token,
			)
		);
		$author = $users[0] ?? null;
		if ( ! $author instanceof WP_User || CYWater_Forum_Content::published_count( $author->ID ) < 1 ) {
			return null;
		}

		self::$current_author = $author;
		return $author;
	}

	public static function ensure_author_token_on_publish( $new_status, $old_status, $post ) {
		unset( $old_status );
		if ( 'publish' === $new_status && $post instanceof WP_Post && CYWater_Forum_Content::POST_TYPE === $post->post_type ) {
			self::author_token( $post->post_author, true );
		}
	}

	public static function author_token( $user_id, $create = false ) {
		$user_id = absint( $user_id );
		$token   = (string) get_user_meta( $user_id, self::AUTHOR_TOKEN_META, true );
		if ( preg_match( self::AUTHOR_TOKEN_RX, $token ) ) {
			return $token;
		}
		if ( ! $create || ! $user_id ) {
			return '';
		}

		for ( $attempt = 0; $attempt < 5; ++$attempt ) {
			$token = bin2hex( random_bytes( 16 ) );
			$exists = get_users(
				array(
					'number'     => 1,
					'fields'     => 'ids',
					'meta_key'   => self::AUTHOR_TOKEN_META,
					'meta_value' => $token,
				)
			);
			if ( ! $exists ) {
				update_user_meta( $user_id, self::AUTHOR_TOKEN_META, $token );
				return $token;
			}
		}
		return '';
	}

	public static function author_url( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || CYWater_Forum_Content::published_count( $user_id ) < 1 ) {
			return get_post_type_archive_link( CYWater_Forum_Content::POST_TYPE );
		}
		$token = self::author_token( $user_id, true );
		return $token ? home_url( '/forum/member/' . $token . '/' ) : get_post_type_archive_link( CYWater_Forum_Content::POST_TYPE );
	}

	/**
	 * Only expose profile values the member explicitly opted into. A historical
	 * byline remains public with its article, but professional details disappear
	 * if membership or email verification later lapses.
	 *
	 * @return array<string,array{label:string,value:mixed}>
	 */
	public static function public_profile( $user_id ) {
		$user_id = absint( $user_id );
		if (
			! $user_id
			|| ! get_user_meta( $user_id, 'cyw_profile_public', true )
			|| ! CYWater_Forum_Roles::has_active_membership( $user_id )
			|| ! class_exists( 'CYWater_Membership_Account_Security' )
			|| ! CYWater_Membership_Account_Security::is_verified( $user_id )
		) {
			return array();
		}

		$labels   = class_exists( 'CYWater_Membership_Privacy' ) ? CYWater_Membership_Privacy::public_field_labels() : array();
		$selected = array_intersect( (array) get_user_meta( $user_id, 'cyw_public_fields', true ), array_keys( $labels ) );
		$profile  = array();
		foreach ( $selected as $key ) {
			$value = get_user_meta( $user_id, $key, true );
			if ( 'cyw_profile_photo' === $key ) {
				$value = get_avatar( $user_id, 160, '', '', array( 'class' => 'forum-member-photo' ) );
			}
			if ( '' !== trim( is_scalar( $value ) ? (string) $value : '' ) ) {
				$profile[ $key ] = array( 'label' => (string) $labels[ $key ], 'value' => $value );
			}
		}
		return $profile;
	}

	public static function can_like( $user_id ) {
		return CYWater_Forum_Comments::may_comment( absint( $user_id ) );
	}

	public static function has_liked( $post_id, $user_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM ' . self::table_name() . ' WHERE post_id = %d AND user_id = %d LIMIT 1',
				absint( $post_id ),
				absint( $user_id )
			)
		);
	}

	public static function like_count( $post_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE post_id = %d', absint( $post_id ) )
		);
	}

	/** @return bool|WP_Error True when liked, false when unliked. */
	public static function toggle_like( $post_id, $user_id ) {
		global $wpdb;
		$post_id = absint( $post_id );
		$user_id = absint( $user_id );
		$post    = get_post( $post_id );
		if ( ! $post instanceof WP_Post || CYWater_Forum_Content::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'article_unavailable', __( 'That Forum article is unavailable.', 'cywater-forum' ) );
		}
		if ( ! self::can_like( $user_id ) ) {
			return new WP_Error( 'like_forbidden', __( 'An active, email-verified CYWater membership is required to like Forum articles.', 'cywater-forum' ) );
		}

		if ( self::has_liked( $post_id, $user_id ) ) {
			$wpdb->delete( self::table_name(), array( 'post_id' => $post_id, 'user_id' => $user_id ), array( '%d', '%d' ) );
			$liked = false;
		} else {
			$inserted = $wpdb->insert(
				self::table_name(),
				array( 'post_id' => $post_id, 'user_id' => $user_id, 'created_at' => current_time( 'mysql', true ) ),
				array( '%d', '%d', '%s' )
			);
			if ( false === $inserted ) {
				return new WP_Error( 'like_not_saved', __( 'The like could not be saved. Please try again.', 'cywater-forum' ) );
			}
			$liked = true;
		}

		clean_post_cache( $post_id );
		do_action( 'litespeed_purge_post', $post_id );
		return $liked;
	}

	public static function handle_toggle_like() {
		$post_id = absint( $_POST['forum_post_id'] ?? 0 );
		if ( ! is_user_logged_in() ) {
			self::redirect_to_login( $post_id );
		}
		check_admin_referer( 'cywater_forum_like_' . $post_id, 'cywater_forum_like_nonce' );
		$result = self::toggle_like( $post_id, get_current_user_id() );
		$args   = is_wp_error( $result ) ? array( 'forum_like' => $result->get_error_code() ) : array( 'forum_like' => $result ? 'liked' : 'unliked' );
		$url    = get_permalink( $post_id );
		wp_safe_redirect( add_query_arg( $args, $url ?: get_post_type_archive_link( CYWater_Forum_Content::POST_TYPE ) ) . '#forum-engagement' );
		exit;
	}

	public static function handle_signed_out_like() {
		self::redirect_to_login( absint( $_POST['forum_post_id'] ?? 0 ) );
	}

	private static function redirect_to_login( $post_id ) {
		$return = get_permalink( absint( $post_id ) );
		$return = $return ?: get_post_type_archive_link( CYWater_Forum_Content::POST_TYPE );
		$login  = class_exists( 'CYWater_Membership_Account_Routing' )
			? CYWater_Membership_Account_Routing::login_url( $return )
			: wp_login_url( $return );
		wp_safe_redirect( $login );
		exit;
	}

	public static function render_article_engagement( $post ) {
		if ( ! $post instanceof WP_Post || CYWater_Forum_Content::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		$post_id = $post->ID;
		$user_id = get_current_user_id();
		$count   = self::like_count( $post_id );
		$liked   = $user_id ? self::has_liked( $post_id, $user_id ) : false;
		?>
		<section id="forum-engagement" class="forum-engagement" aria-label="<?php esc_attr_e( 'Forum article appreciation', 'cywater-forum' ); ?>">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cywater_forum_toggle_like">
				<input type="hidden" name="forum_post_id" value="<?php echo esc_attr( (string) $post_id ); ?>">
				<?php wp_nonce_field( 'cywater_forum_like_' . $post_id, 'cywater_forum_like_nonce' ); ?>
				<button class="btn <?php echo $liked ? 'btn-accent' : 'btn-outline'; ?> forum-like-button" type="submit" <?php disabled( $user_id && ! self::can_like( $user_id ) ); ?> aria-pressed="<?php echo $liked ? 'true' : 'false'; ?>">
					<?php echo esc_html( $liked ? __( 'Liked', 'cywater-forum' ) : __( 'Like', 'cywater-forum' ) ); ?>
				</button>
			</form>
			<span class="forum-like-count"><?php echo esc_html( sprintf( _n( '%d like', '%d likes', $count, 'cywater-forum' ), $count ) ); ?></span>
			<?php if ( $user_id && ! self::can_like( $user_id ) ) : ?>
				<span class="forum-engagement-note"><?php esc_html_e( 'An active, email-verified membership is required.', 'cywater-forum' ); ?></span>
			<?php endif; ?>
		</section>
		<?php
	}

	public static function append_account_panel( $content ) {
		$page_id = absint( get_option( 'pmpro_account_page_id' ) );
		if (
			self::$account_rendered
			|| ! is_user_logged_in()
			|| ! $page_id
			|| ! is_page( $page_id )
			|| ! in_the_loop()
			|| ! is_main_query()
			|| get_the_ID() !== $page_id
		) {
			return $content;
		}
		self::$account_rendered = true;
		return $content . self::account_panel( get_current_user_id() );
	}

	public static function liked_posts( $user_id, $limit = 20, $offset = 0 ) {
		global $wpdb;
		$limit  = max( 1, min( 100, absint( $limit ) ) );
		$offset = absint( $offset );
		$ids    = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT l.post_id FROM ' . self::table_name() . " l INNER JOIN {$wpdb->posts} p ON p.ID = l.post_id WHERE l.user_id = %d AND p.post_type = %s AND p.post_status = 'publish' ORDER BY l.created_at DESC LIMIT %d OFFSET %d",
				absint( $user_id ),
				CYWater_Forum_Content::POST_TYPE,
				$limit,
				$offset
			)
		);
		return array_values( array_filter( array_map( 'get_post', array_map( 'absint', $ids ) ) ) );
	}

	public static function user_like_count( $user_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE user_id = %d', absint( $user_id ) ) );
	}

	public static function account_panel( $user_id ) {
		$articles = CYWater_Forum_Workspace::articles_for_user( $user_id );
		$liked    = self::liked_posts( $user_id, 12 );
		ob_start();
		?>
		<section class="forum-account-panel" aria-labelledby="forum-account-heading">
			<div class="forum-account-heading">
				<div><span class="eyebrow"><?php esc_html_e( 'Community', 'cywater-forum' ); ?></span><h2 id="forum-account-heading"><?php esc_html_e( 'Forum activity', 'cywater-forum' ); ?></h2></div>
				<a class="btn btn-accent" href="<?php echo esc_url( CYWater_Forum_Workspace::url() ); ?>"><?php esc_html_e( 'Write or manage posts', 'cywater-forum' ); ?></a>
			</div>
			<div class="forum-account-grid">
				<div class="forum-account-list">
					<h3><?php esc_html_e( 'My posts', 'cywater-forum' ); ?></h3>
					<?php if ( ! $articles ) : ?><p><?php esc_html_e( 'You have not written a Forum post yet.', 'cywater-forum' ); ?></p><?php endif; ?>
					<?php foreach ( array_slice( $articles, 0, 12 ) as $article ) : ?>
						<p><a href="<?php echo esc_url( 'publish' === $article->post_status ? get_permalink( $article ) : add_query_arg( 'edit', $article->ID, CYWater_Forum_Workspace::url() ) ); ?>"><?php echo esc_html( get_the_title( $article ) ); ?></a><span><?php echo esc_html( CYWater_Forum_Workspace::status_label( $article->post_status ) ); ?></span></p>
					<?php endforeach; ?>
					<?php if ( CYWater_Forum_Content::published_count( $user_id ) > 0 ) : ?><a class="link" href="<?php echo esc_url( self::author_url( $user_id ) ); ?>"><?php esc_html_e( 'View my public author page', 'cywater-forum' ); ?></a><?php endif; ?>
				</div>
				<div class="forum-account-list">
					<h3><?php esc_html_e( 'Liked posts', 'cywater-forum' ); ?></h3>
					<?php if ( ! $liked ) : ?><p><?php esc_html_e( 'Posts you like will appear here.', 'cywater-forum' ); ?></p><?php endif; ?>
					<?php foreach ( $liked as $article ) : ?><p><a href="<?php echo esc_url( get_permalink( $article ) ); ?>"><?php echo esc_html( get_the_title( $article ) ); ?></a><span><?php echo esc_html( get_the_date( '', $article ) ); ?></span></p><?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function admin_columns( $columns ) {
		$rebuilt = array();
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$rebuilt['cywater_forum_likes'] = __( 'Likes', 'cywater-forum' );
			}
			$rebuilt[ $key ] = $label;
		}
		return $rebuilt;
	}

	public static function admin_column( $column, $post_id ) {
		if ( 'cywater_forum_likes' === $column ) {
			echo esc_html( (string) self::like_count( $post_id ) );
		}
	}

	public static function add_engagement_meta_box() {
		add_meta_box( 'cywater-forum-engagement', __( 'Forum engagement', 'cywater-forum' ), array( __CLASS__, 'render_engagement_meta_box' ), CYWater_Forum_Content::POST_TYPE, 'side', 'default' );
	}

	public static function render_engagement_meta_box( $post ) {
		$count = self::like_count( $post->ID );
		echo '<p><strong>' . esc_html( sprintf( _n( '%d like', '%d likes', $count, 'cywater-forum' ), $count ) ) . '</strong></p>';
		if ( $count && current_user_can( 'edit_others_cyw_forum_posts' ) ) {
			echo '<p class="description">' . esc_html__( 'Clearing likes is a moderation action and cannot be undone.', 'cywater-forum' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="cywater_forum_clear_likes"><input type="hidden" name="forum_post_id" value="' . esc_attr( (string) $post->ID ) . '">';
			wp_nonce_field( 'cywater_forum_clear_likes_' . $post->ID, 'cywater_forum_clear_likes_nonce' );
			submit_button( __( 'Clear all likes', 'cywater-forum' ), 'secondary', 'submit', false );
			echo '</form>';
		}
	}

	public static function handle_clear_likes() {
		global $wpdb;
		$post_id = absint( $_POST['forum_post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_others_cyw_forum_posts' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to clear Forum likes.', 'cywater-forum' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cywater_forum_clear_likes_' . $post_id, 'cywater_forum_clear_likes_nonce' );
		$removed = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table_name() . ' WHERE post_id = %d', $post_id ) );
		if ( class_exists( 'CYWater_Operations_Audit' ) ) {
			CYWater_Operations_Audit::record( 'forum_article', $post_id, 'likes_cleared', (string) $removed, '0', 0, 'forum_moderation' );
		}
		clean_post_cache( $post_id );
		do_action( 'litespeed_purge_post', $post_id );
		wp_safe_redirect( add_query_arg( 'cywater_forum_likes_cleared', $removed, get_edit_post_link( $post_id, 'url' ) ) );
		exit;
	}

	public static function render_admin_notice() {
		if ( ! isset( $_GET['cywater_forum_likes_cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$removed = absint( $_GET['cywater_forum_likes_cleared'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html( sprintf( _n( '%d Forum like was cleared.', '%d Forum likes were cleared.', $removed, 'cywater-forum' ), $removed ) )
			. '</p></div>';
	}

	public static function delete_post_likes( $post_id, $post ) {
		if ( $post instanceof WP_Post && CYWater_Forum_Content::POST_TYPE === $post->post_type ) {
			global $wpdb;
			$wpdb->delete( self::table_name(), array( 'post_id' => absint( $post_id ) ), array( '%d' ) );
		}
	}

	public static function delete_user_likes( $user_id ) {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'user_id' => absint( $user_id ) ), array( '%d' ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['cywater-forum-community'] = array(
			'exporter_friendly_name' => __( 'CYWater Forum likes', 'cywater-forum' ),
			'callback'               => array( __CLASS__, 'export_personal_data' ),
		);
		return $exporters;
	}

	public static function export_personal_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}
		global $wpdb;
		$per_page = 50;
		$offset   = ( max( 1, absint( $page ) ) - 1 ) * $per_page;
		$records  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT l.post_id, l.created_at, p.post_title FROM ' . self::table_name() . " l LEFT JOIN {$wpdb->posts} p ON p.ID = l.post_id WHERE l.user_id = %d ORDER BY l.created_at DESC LIMIT %d OFFSET %d",
				$user->ID,
				$per_page,
				$offset
			)
		);
		$data     = array();
		foreach ( $records as $record ) {
			$data[] = array(
				'group_id'    => 'cywater-forum-likes',
				'group_label' => __( 'CYWater Forum likes', 'cywater-forum' ),
				'item_id'     => 'cywater-forum-like-' . absint( $record->post_id ),
				'data'        => array(
					array( 'name' => __( 'Liked article', 'cywater-forum' ), 'value' => $record->post_title ?: sprintf( __( 'Article #%d (no longer available)', 'cywater-forum' ), absint( $record->post_id ) ) ),
					array( 'name' => __( 'Liked at', 'cywater-forum' ), 'value' => (string) $record->created_at ),
				),
			);
		}
		return array( 'data' => $data, 'done' => count( $records ) < $per_page );
	}

	public static function register_eraser( $erasers ) {
		$erasers['cywater-forum-community'] = array(
			'eraser_friendly_name' => __( 'CYWater Forum likes', 'cywater-forum' ),
			'callback'             => array( __CLASS__, 'erase_personal_data' ),
		);
		return $erasers;
	}

	public static function erase_personal_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user || 1 !== absint( $page ) ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		global $wpdb;
		$removed = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table_name() . ' WHERE user_id = %d', $user->ID ) );
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => CYWater_Forum_Content::published_count( $user->ID ) > 0,
			'messages'       => CYWater_Forum_Content::published_count( $user->ID ) > 0 ? array( __( 'Published Forum articles and their public byline are retained for editorial and association-record review.', 'cywater-forum' ) ) : array(),
			'done'           => true,
		);
	}
}
