<?php
/**
 * Protected cover-image storage for member-authored Forum articles.
 *
 * Draft covers must never enter WordPress' public uploads tree.
 * One private file is retained per article and is streamed only through this
 * lifecycle-aware controller. Publication makes the stream public; taking the
 * article down closes the same URL immediately.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Forum_Covers {
	private const META_KEY           = '_cywater_forum_private_cover';
	private const MIGRATION_OPTION   = 'cywater_forum_cover_migration_version';
	private const MIGRATION_VERSION  = '1';
	private const MAX_FILE_BYTES     = 5242880;
	private const MAX_USER_BYTES     = 26214400;
	private const RATE_LIMIT_MAX     = 10;
	private const RATE_LIMIT_SECONDS = 3600;
	private const STREAM_ACTION      = 'cywater_forum_cover';

	/** @var array<int,array<string,mixed>> Files queued until WordPress confirms deletion. */
	private static $delete_queue = array();

	public static function register() {
		add_action( 'admin_post_' . self::STREAM_ACTION, array( __CLASS__, 'stream' ) );
		add_action( 'admin_post_nopriv_' . self::STREAM_ACTION, array( __CLASS__, 'stream' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'queue_delete_for_post' ) );
		add_action( 'deleted_post', array( __CLASS__, 'cleanup_deleted_post' ), 10, 2 );
		add_action( 'add_meta_boxes_' . CYWater_Forum_Content::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate_legacy_covers' ), 40 );
		add_action( 'cywater_after_core_setup', array( __CLASS__, 'maybe_migrate_legacy_covers' ), 45 );
	}

	/** Prepare a deny-by-default storage directory. */
	public static function ensure_storage() {
		$directory = self::directory();
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return new WP_Error( 'cover_storage_unavailable', __( 'The protected cover-image store is unavailable.', 'cywater-forum' ) );
		}

		$guards = array(
			'.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'index.php' => "<?php\n// Silence is golden.\n",
			'index.html' => '',
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
		);
		foreach ( $guards as $name => $contents ) {
			$path = trailingslashit( $directory ) . $name;
			$current = is_file( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( $contents !== $current && false === file_put_contents( $path, $contents, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				return new WP_Error( 'cover_storage_unavailable', __( 'The protected cover-image store could not be secured.', 'cywater-forum' ) );
			}
		}
		return $directory;
	}

	/** @return array<string,mixed>|int|WP_Error Zero means no new upload. */
	public static function receive_upload( $user_id, $field = 'forum_cover', $replace_post_id = 0 ) {
		if ( empty( $_FILES[ $field ]['name'] ) ) {
			return 0;
		}
		$file = $_FILES[ $field ];
		if ( UPLOAD_ERR_OK !== absint( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'cover_upload_failed', __( 'The cover image upload did not complete.', 'cywater-forum' ) );
		}
		$replace_bytes = 0;
		$replace_post  = get_post( absint( $replace_post_id ) );
		if ( $replace_post instanceof WP_Post && CYWater_Forum_Content::POST_TYPE === $replace_post->post_type && absint( $replace_post->post_author ) === absint( $user_id ) ) {
			$current       = self::get( $replace_post->ID );
			$replace_bytes = absint( $current['bytes'] ?? 0 );
		}
		return self::store_file( $file['tmp_name'], (string) $file['name'], absint( $user_id ), true, true, $replace_bytes );
	}

	/**
	 * Import a local fixture only from non-production WP-CLI acceptance tests.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function import_file_for_qa( $path, $original_name, $user_id ) {
		$qa_runtime = ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'CYWATER_FORUM_COVER_QA' ) && true === CYWATER_FORUM_COVER_QA );
		if ( ! $qa_runtime || 'production' === wp_get_environment_type() ) {
			return new WP_Error( 'cover_qa_forbidden', __( 'Cover fixtures are available only to non-production command-line QA.', 'cywater-forum' ) );
		}
		return self::store_file( $path, $original_name, absint( $user_id ), false, false, 0 );
	}

	/** Replace the article's one current private cover and remove the old file. */
	public static function replace( $post_id, $cover, $user_id ) {
		$post = get_post( absint( $post_id ) );
		if (
			! $post instanceof WP_Post
			|| CYWater_Forum_Content::POST_TYPE !== $post->post_type
			|| absint( $post->post_author ) !== absint( $user_id )
			|| ! self::valid_record( $cover )
			|| absint( $cover['owner'] ?? 0 ) !== absint( $user_id )
			|| ! self::record_file_is_valid( $cover )
		) {
			self::discard( $cover );
			return new WP_Error( 'cover_invalid', __( 'The selected cover image is unavailable.', 'cywater-forum' ) );
		}

		$old = self::get( $post->ID );
		if ( self::reference_count( $cover ) > 0 && ( ! $old || (string) $old['stored'] !== (string) $cover['stored'] ) ) {
			return new WP_Error( 'cover_invalid', __( 'The selected cover image is already assigned to another article.', 'cywater-forum' ) );
		}

		$result = update_post_meta( $post->ID, self::META_KEY, $cover );
		$saved  = self::get( $post->ID );
		if ( ( false === $result && $old !== $cover ) || $saved !== $cover ) {
			self::discard( $cover );
			return new WP_Error( 'cover_storage_unavailable', __( 'The protected cover image could not be attached to this article.', 'cywater-forum' ) );
		}
		if ( $old && $old !== $cover ) {
			// discard() deletes only a record that is no longer referenced. The
			// new metadata is verified first, so a failed write never destroys the
			// prior valid cover.
			self::discard( $old );
		}
		self::remove_legacy_thumbnail( $post->ID );
		return true;
	}

	/** Delete an uncommitted protected cover. */
	public static function discard( $cover ) {
		if ( ! self::valid_record( $cover ) || self::reference_count( $cover ) > 0 ) {
			return;
		}
		$path = self::path_for( $cover );
		if ( $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/** @return array<string,mixed>|array{} */
	public static function get( $post_id ) {
		$cover = get_post_meta( absint( $post_id ), self::META_KEY, true );
		return self::valid_record( $cover ) ? $cover : array();
	}

	/** Return a lifecycle-aware stream URL or an empty string. */
	public static function url( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post instanceof WP_Post || CYWater_Forum_Content::POST_TYPE !== $post->post_type || ! self::get( $post_id ) ) {
			return '';
		}
		$args = array(
			'action'  => self::STREAM_ACTION,
			'post_id' => $post_id,
		);
		if ( 'publish' !== $post->post_status ) {
			$user_id = get_current_user_id();
			if ( ! self::can_view_private( $post, $user_id ) ) {
				return '';
			}
			$args['_wpnonce'] = wp_create_nonce( self::nonce_action( $post_id ) );
		}
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/** Pure authorization seam used by the stream controller and runtime QA. */
	public static function can_stream( $post_id, $user_id = 0, $nonce = '' ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post instanceof WP_Post || CYWater_Forum_Content::POST_TYPE !== $post->post_type || ! self::get( $post->ID ) ) {
			return false;
		}
		if ( 'publish' === $post->post_status ) {
			return true;
		}
		return self::can_view_private( $post, absint( $user_id ) ) && (bool) wp_verify_nonce( (string) $nonce, self::nonce_action( $post->ID ) );
	}

	public static function stream() {
		$post_id = absint( $_GET['post_id'] ?? 0 );
		$nonce   = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		if ( ! self::can_stream( $post_id, get_current_user_id(), $nonce ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		$cover = self::get( $post_id );
		$path  = self::path_for( $cover );
		if ( ! $path || ! is_readable( $path ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: ' . $cover['type'] );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $cover['original'] ) . '"' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/** Cache the record, but do not delete data before WordPress commits deletion. */
	public static function queue_delete_for_post( $post_id ) {
		if ( CYWater_Forum_Content::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$cover = self::get( $post_id );
		if ( $cover ) {
			self::$delete_queue[ absint( $post_id ) ] = $cover;
		}
	}

	/** Delete only after the post and its metadata have actually been removed. */
	public static function cleanup_deleted_post( $post_id, $post ) {
		$post_id = absint( $post_id );
		if ( ! $post instanceof WP_Post || CYWater_Forum_Content::POST_TYPE !== $post->post_type || empty( self::$delete_queue[ $post_id ] ) ) {
			return;
		}
		$cover = self::$delete_queue[ $post_id ];
		unset( self::$delete_queue[ $post_id ] );
		self::discard( $cover );
	}

	public static function add_meta_box() {
		add_meta_box(
			'cywater-forum-protected-cover',
			__( 'Protected Forum cover', 'cywater-forum' ),
			array( __CLASS__, 'render_meta_box' ),
			CYWater_Forum_Content::POST_TYPE,
			'side',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		$url = self::url( $post->ID );
		if ( ! $url ) {
			echo '<p>' . esc_html__( 'No protected cover is attached. Members add or replace covers in the front-end Forum workspace.', 'cywater-forum' ) . '</p>';
			return;
		}
		echo '<img src="' . esc_url( $url ) . '" alt="" style="display:block;height:auto;max-width:100%;">';
		echo '<p class="description">' . esc_html__( 'Stored outside the public Media Library. It is public only while the article is published.', 'cywater-forum' ) . '</p>';
	}

	/** One-time conversion of legacy public Forum featured images. */
	public static function maybe_migrate_legacy_covers() {
		if ( self::MIGRATION_VERSION === get_option( self::MIGRATION_OPTION ) ) {
			return true;
		}
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$posts = get_posts(
			array(
				'post_type'      => CYWater_Forum_Content::POST_TYPE,
				'post_status'    => array( 'draft', 'publish', 'trash' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post_id ) {
			if ( self::get( $post_id ) ) {
				if ( ! self::remove_legacy_thumbnail( $post_id ) ) {
					return false;
				}
				continue;
			}
			$attachment_id = absint( get_post_thumbnail_id( $post_id ) );
			$source        = $attachment_id ? get_attached_file( $attachment_id ) : '';
			$post          = get_post( $post_id );
			if ( ! $source || ! file_exists( $source ) || ! $post instanceof WP_Post ) {
				return false;
			}
			$cover = self::store_file( $source, wp_basename( $source ), absint( $post->post_author ), false, false, 0 );
			if ( is_wp_error( $cover ) || is_wp_error( self::replace( $post_id, $cover, absint( $post->post_author ) ) ) ) {
				self::discard( $cover );
				return false;
			}
		}
		update_option( self::MIGRATION_OPTION, self::MIGRATION_VERSION, false );
		return true;
	}

	/** @return array<string,mixed>|WP_Error */
	private static function store_file( $source, $original_name, $user_id, $uploaded, $enforce_limits, $replace_bytes ) {
		$user_id = absint( $user_id );
		$bytes   = is_file( $source ) ? (int) filesize( $source ) : 0;
		if ( ! $user_id || $bytes < 1 || $bytes > self::MAX_FILE_BYTES ) {
			return new WP_Error( $bytes > self::MAX_FILE_BYTES ? 'cover_too_large' : 'cover_invalid', __( 'The cover image must be a JPG, PNG, or WebP file no larger than 5 MB.', 'cywater-forum' ) );
		}
		if ( $enforce_limits && ! self::rate_limit_available( $user_id ) ) {
			return new WP_Error( 'cover_rate_limited', __( 'Too many cover images were uploaded recently. Please try again later.', 'cywater-forum' ) );
		}
		if ( $enforce_limits && max( 0, self::user_bytes( $user_id ) - absint( $replace_bytes ) ) + $bytes > self::MAX_USER_BYTES ) {
			return new WP_Error( 'cover_quota_exceeded', __( 'This account has reached its protected Forum cover-image storage limit.', 'cywater-forum' ) );
		}

		$mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $source ) : '';
		$exts = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' );
		if ( ! isset( $exts[ $mime ] ) ) {
			return new WP_Error( 'cover_invalid', __( 'The cover image must be a valid JPG, PNG, or WebP file.', 'cywater-forum' ) );
		}
		$directory = self::ensure_storage();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$stored = wp_generate_uuid4() . '.' . $exts[ $mime ];
		$target = trailingslashit( $directory ) . $stored;
		$moved  = $uploaded ? move_uploaded_file( $source, $target ) : copy( $source, $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		if ( ! $moved ) {
			return new WP_Error( 'cover_storage_unavailable', __( 'The protected cover image could not be stored.', 'cywater-forum' ) );
		}
		@chmod( $target, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$record = array(
			'stored'   => $stored,
			'original' => sanitize_file_name( (string) $original_name ) ?: 'forum-cover.' . $exts[ $mime ],
			'type'     => $mime,
			'bytes'    => $bytes,
			'owner'    => $user_id,
		);
		if ( $enforce_limits ) {
			self::record_upload( $user_id );
		}
		return $record;
	}

	private static function directory() {
		return trailingslashit( WP_CONTENT_DIR ) . 'cywater-private/forum-covers';
	}

	private static function valid_record( $cover ) {
		if ( ! is_array( $cover ) || empty( $cover['stored'] ) || empty( $cover['type'] ) || empty( $cover['bytes'] ) || empty( $cover['owner'] ) ) {
			return false;
		}
		return 1 === preg_match( '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(?:jpg|png|webp)\z/i', (string) $cover['stored'] )
			&& in_array( $cover['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true )
			&& absint( $cover['bytes'] ) <= self::MAX_FILE_BYTES
			&& absint( $cover['owner'] ) > 0;
	}

	private static function path_for( $cover ) {
		return self::valid_record( $cover ) ? trailingslashit( self::directory() ) . $cover['stored'] : '';
	}

	/** Return the exact path only to the existing non-production QA harnesses. */
	public static function path_for_qa( $cover ) {
		$qa_runtime = ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'CYWATER_FORUM_COVER_QA' ) && true === CYWATER_FORUM_COVER_QA );
		return $qa_runtime && 'production' !== wp_get_environment_type() ? self::path_for( $cover ) : '';
	}

	private static function record_file_is_valid( $cover ) {
		$path = self::path_for( $cover );
		if ( ! $path || ! is_file( $path ) || ! is_readable( $path ) || absint( filesize( $path ) ) !== absint( $cover['bytes'] ) ) {
			return false;
		}
		return ! function_exists( 'wp_get_image_mime' ) || (string) wp_get_image_mime( $path ) === (string) $cover['type'];
	}

	private static function can_view_private( $post, $user_id ) {
		if ( ! $user_id ) {
			return false;
		}
		if ( 'draft' === $post->post_status && absint( $post->post_author ) === $user_id ) {
			return true;
		}
		return user_can( $user_id, 'manage_options' ) || ( class_exists( 'CYWater_Forum_Roles' ) && CYWater_Forum_Roles::is_staff( $user_id ) );
	}

	private static function nonce_action( $post_id ) {
		return 'cywater_forum_cover_' . absint( $post_id );
	}

	private static function user_bytes( $user_id ) {
		$total = 0;
		$posts = get_posts(
			array(
				'post_type'      => CYWater_Forum_Content::POST_TYPE,
				'post_status'    => array( 'draft', 'publish', 'trash' ),
				'author'         => absint( $user_id ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post_id ) {
			$cover  = self::get( $post_id );
			$total += absint( $cover['bytes'] ?? 0 );
		}
		return $total;
	}

	private static function rate_limit_available( $user_id ) {
		$events = (array) get_transient( 'cywater_forum_cover_rate_' . absint( $user_id ) );
		$cutoff = time() - self::RATE_LIMIT_SECONDS;
		$events = array_filter( array_map( 'absint', $events ), static fn( $event ) => $event >= $cutoff );
		return count( $events ) < self::RATE_LIMIT_MAX;
	}

	private static function record_upload( $user_id ) {
		$key    = 'cywater_forum_cover_rate_' . absint( $user_id );
		$events = (array) get_transient( $key );
		$cutoff = time() - self::RATE_LIMIT_SECONDS;
		$events = array_values( array_filter( array_map( 'absint', $events ), static fn( $event ) => $event >= $cutoff ) );
		$events[] = time();
		set_transient( $key, $events, self::RATE_LIMIT_SECONDS );
	}

	/** Count every exact metadata reference before deleting a private file. */
	private static function reference_count( $cover ) {
		if ( ! self::valid_record( $cover ) ) {
			return 0;
		}
		global $wpdb;
		$count  = 0;
		$values = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::META_KEY
			)
		);
		foreach ( $values as $value ) {
			$record = maybe_unserialize( $value );
			if ( is_array( $record ) && ! empty( $record['stored'] ) && hash_equals( (string) $cover['stored'], (string) $record['stored'] ) ) {
				++$count;
			}
		}
		return $count;
	}

	private static function remove_legacy_thumbnail( $post_id ) {
		$attachment_id = absint( get_post_thumbnail_id( $post_id ) );
		if ( ! $attachment_id ) {
			return true;
		}
		$post       = get_post( $post_id );
		$attachment = get_post( $attachment_id );
		global $wpdb;
		$other_uses = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
				$attachment_id
			)
		);
		$exclusively_owned = $post instanceof WP_Post
			&& $attachment instanceof WP_Post
			&& 'attachment' === $attachment->post_type
			&& absint( $attachment->post_parent ) === absint( $post_id )
			&& absint( $attachment->post_author ) === absint( $post->post_author )
			&& 1 === $other_uses;
		if ( $exclusively_owned ) {
			// wp_delete_attachment() removes the original and every generated
			// derivative. Keep the thumbnail relationship intact if deletion
			// fails so migration can retry without losing its rollback handle.
			return (bool) wp_delete_attachment( $attachment_id, true );
		}

		// A shared or ambiguously owned attachment must never be destroyed by
		// Forum cleanup. Detach it from this article only.
		return (bool) delete_post_thumbnail( $post_id );
	}
}
