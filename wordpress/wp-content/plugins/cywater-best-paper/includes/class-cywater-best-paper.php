<?php
/** Award-scoped private application and independent review service. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class CYWater_Best_Paper {
	private const META = '_cyw_best_paper_config';
	private const MAX_BYTES = 20971520;

	public static function register() {
		add_action( 'admin_post_cywater_bp_file', array( __CLASS__, 'stream_file' ) );
		add_action( 'cywater_bp_receipt', array( __CLASS__, 'send_receipt' ), 10, 2 );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$wpdb->prefix}cyw_bp_applications (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			award_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			paper_key varchar(64) NOT NULL,
			record longtext NOT NULL,
			files longtext NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'submitted',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			decision varchar(24) NOT NULL DEFAULT 'none',
			PRIMARY KEY  (id),
			UNIQUE KEY award_user (award_id,user_id),
			UNIQUE KEY award_paper (award_id,paper_key)
		) ENGINE=InnoDB $collate;" );
		dbDelta( "CREATE TABLE {$wpdb->prefix}cyw_bp_reviews (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			application_id bigint(20) unsigned NOT NULL,
			reviewer_id bigint(20) unsigned NOT NULL,
			score decimal(6,3) DEFAULT NULL,
			comments longtext NOT NULL,
			history longtext DEFAULT NULL,
			status varchar(24) NOT NULL DEFAULT 'assigned',
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY application_reviewer (application_id,reviewer_id)
		) ENGINE=InnoDB $collate;" );
		foreach ( array( 'cyw_bp_applications', 'cyw_bp_reviews' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				return self::error( 'storage', 'Best Paper storage could not be installed.' );
			}
		}
		return true;
	}

	private static function error( $code, $message ) { return new WP_Error( 'cywater_bp_' . $code, $message ); }
	public static function can_manage() { return current_user_can( 'manage_options' ); }
	public static function config( $award_id ) {
		$stored = get_post_meta( absint( $award_id ), self::META, true );
		return array_merge( array( 'enabled' => false, 'status' => 'draft', 'open_at' => '', 'close_at' => '', 'review_deadline' => '', 'timezone' => wp_timezone_string(), 'chair_id' => 0, 'reviewer_ids' => array(), 'prize' => '', 'ceremony' => '' ), is_array( $stored ) ? $stored : array() );
	}

	/** Strict dates: never silently normalize an impossible date or accept relative prose. */
	private static function date( $value, $timezone, $day_only = false ) {
		if ( ! is_string( $value ) ) { return false; }
		$value = str_replace( 'T', ' ', trim( $value ) );
		if ( ! $day_only && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D', $value ) ) { $value .= ':00'; }
		$format = $day_only ? 'Y-m-d' : 'Y-m-d H:i:s';
		try { $date = DateTimeImmutable::createFromFormat( '!' . $format, $value, new DateTimeZone( $timezone ) ); }
		catch ( Exception $e ) { return false; }
		$errors = DateTimeImmutable::getLastErrors();
		return $date && ( false === $errors || ( ! $errors['warning_count'] && ! $errors['error_count'] ) ) && $date->format( $format ) === $value ? $date : false;
	}

	/** Serialize all mutations for an edition, including result publication. */
	private static function lock( $award_id ) {
		global $wpdb;
		$key = 'cyw_bp_' . substr( hash( 'sha256', $wpdb->prefix . ':' . absint( $award_id ) ), 0, 40 );
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $key ) ) ? $key : self::error( 'busy', 'This award is being updated. Please try again.' );
	}
	private static function unlock( $key ) { global $wpdb; $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); }

	public static function save_config( $award_id, $input ) {
		$award_id = absint( $award_id );
		if ( ! self::can_manage() || 'cyw_award' !== get_post_type( $award_id ) ) { return self::error( 'forbidden', 'Only an administrator can configure an Award record.' ); }
		$lock = self::lock( $award_id ); if ( is_wp_error( $lock ) ) { return $lock; }
		try {
			$old = self::config( $award_id ); $cfg = array_merge( $old, (array) $input );
			$cfg = array_intersect_key( $cfg, $old );
			$cfg['enabled'] = ! empty( $cfg['enabled'] );
			$cfg['status'] = sanitize_key( $cfg['status'] );
			if ( ! in_array( $cfg['status'], array( 'draft', 'applications', 'review', 'decision', 'announced' ), true ) ) { return self::error( 'phase', 'Choose a valid award phase.' ); }
			if ( ! is_string( $cfg['timezone'] ) ) { return self::error( 'timezone', 'Choose a valid timezone.' ); }
			try { new DateTimeZone( $cfg['timezone'] ); } catch ( Exception $e ) { return self::error( 'timezone', 'Choose a valid timezone.' ); }
			foreach ( array( 'open_at', 'close_at', 'review_deadline' ) as $field ) {
				if ( '' === $cfg[ $field ] ) { continue; }
				$date = self::date( $cfg[ $field ], $cfg['timezone'] );
				if ( ! $date ) { return self::error( 'date', 'Enter a valid date and time for ' . str_replace( '_', ' ', $field ) . '.' ); }
				$cfg[ $field ] = $date->format( 'Y-m-d H:i:s' );
			}
			if ( 'draft' !== $cfg['status'] && ( ! $cfg['open_at'] || ! $cfg['close_at'] ) ) { return self::error( 'dates', 'Opening and closing times are required before enabling applications.' ); }
			if ( $cfg['open_at'] && $cfg['close_at'] && $cfg['open_at'] >= $cfg['close_at'] ) { return self::error( 'dates', 'The application closing time must follow the opening time.' ); }
			if ( $cfg['review_deadline'] && $cfg['close_at'] && $cfg['review_deadline'] <= $cfg['close_at'] ) { return self::error( 'dates', 'The review deadline must follow the application deadline.' ); }
			$cfg['chair_id'] = absint( $cfg['chair_id'] );
			$ids = is_array( $cfg['reviewer_ids'] ) ? $cfg['reviewer_ids'] : preg_split( '/[\s,]+/', (string) $cfg['reviewer_ids'] );
			$cfg['reviewer_ids'] = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
			foreach ( array_filter( array_merge( array( $cfg['chair_id'] ), $cfg['reviewer_ids'] ) ) as $id ) { if ( ! get_userdata( $id ) ) { return self::error( 'reviewer', 'Chair and reviewers must be existing CYWater accounts.' ); } }
			$cfg['prize'] = sanitize_text_field( $cfg['prize'] ); $cfg['ceremony'] = sanitize_textarea_field( $cfg['ceremony'] );
			$rows = self::raw_applications( $award_id );
			global $wpdb;
			if ( $wpdb->last_error ) { return self::error( 'storage', 'The existing applications could not be verified.' ); }
			if ( $rows && ( $old['close_at'] !== $cfg['close_at'] || $old['timezone'] !== $cfg['timezone'] ) ) { return self::error( 'frozen_rules', 'The publication window and timezone cannot change after the first application. Contact the administrator for an audited migration.' ); }
			$phases = array( 'draft' => 0, 'applications' => 1, 'review' => 2, 'decision' => 3, 'announced' => 4 );
			if ( $phases[ $old['status'] ] >= 2 && $phases[ $cfg['status'] ] < $phases[ $old['status'] ] ) { return self::error( 'phase_reversal', 'A review or decision phase cannot be reopened for application changes. This protects the reviewed paper versions.' ); }
			if ( 'announced' === $old['status'] && $cfg['status'] !== $old['status'] ) { return self::error( 'frozen_results', 'Published results cannot be silently reopened.' ); }
			if ( 'announced' === $cfg['status'] && 'announced' !== $old['status'] ) {
				if ( 'decision' !== $old['status'] || empty( $input['confirm_results'] ) ) { return self::error( 'confirmation', 'Move to the decision phase and explicitly confirm the final results before announcing.' ); }
				$best = array_filter( $rows, static function( $row ) { return 'best' === $row['decision'] && 'eligible' === $row['status']; } );
				if ( 1 !== count( $best ) ) { return self::error( 'best_count', 'Exactly one eligible Best Paper recipient must be confirmed.' ); }
				foreach ( $rows as $row ) { if ( 'none' !== $row['decision'] && 'eligible' !== $row['status'] ) { return self::error( 'ineligible_winner', 'An ineligible application cannot receive an award.' ); } }
				$cfg['announced_at'] = gmdate( 'Y-m-d H:i:s' );
			}
			if ( $cfg === $old ) { return $cfg; }
			if ( ! update_post_meta( $award_id, self::META, $cfg ) && self::config( $award_id ) !== $cfg ) { return self::error( 'storage', 'The award settings could not be saved.' ); }
			return $cfg;
		} finally { self::unlock( $lock ); }
	}

	public static function phase( $award_id ) {
		$cfg = self::config( $award_id );
		if ( ! $cfg['enabled'] ) { return 'draft'; }
		if ( 'applications' !== $cfg['status'] ) { return $cfg['status']; }
		$open = self::date( $cfg['open_at'], $cfg['timezone'] ); $close = self::date( $cfg['close_at'], $cfg['timezone'] );
		if ( ! $open || ! $close ) { return 'draft'; }
		if ( time() < $open->getTimestamp() ) { return 'not_open'; }
		return time() <= $close->getTimestamp() ? 'applications' : 'closed';
	}
	public static function is_reviewer( $award_id, $uid = 0 ) {
		$uid = $uid ? absint( $uid ) : get_current_user_id(); $cfg = self::config( $award_id );
		return $uid && ( (int) $cfg['chair_id'] === $uid || in_array( $uid, array_map( 'intval', (array) $cfg['reviewer_ids'] ), true ) );
	}
	private static function raw_application( $id ) { global $wpdb; return self::decode( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cyw_bp_applications WHERE id=%d", absint( $id ) ), ARRAY_A ) ); }
	private static function raw_applications( $award_id ) { global $wpdb; return array_values( array_filter( array_map( array( __CLASS__, 'decode' ), (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cyw_bp_applications WHERE award_id=%d ORDER BY id ASC", absint( $award_id ) ), ARRAY_A ) ) ) ); }
	private static function decode( $row ) {
		if ( ! is_array( $row ) ) { return null; }
		foreach ( array( 'record', 'files' ) as $key ) { $row[ $key ] = json_decode( $row[ $key ], true ); if ( ! is_array( $row[ $key ] ) ) { return null; } }
		foreach ( array( 'id', 'award_id', 'user_id' ) as $key ) { $row[ $key ] = (int) $row[ $key ]; }
		return $row;
	}
	private static function raw_reviews( $app_id ) { global $wpdb; return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cyw_bp_reviews WHERE application_id=%d ORDER BY reviewer_id ASC", absint( $app_id ) ), ARRAY_A ); }
	private static function conflicted( $app, $uid ) {
		global $wpdb;
		if ( (int) $app['user_id'] === (int) $uid ) { return true; }
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}cyw_bp_reviews WHERE application_id=%d AND reviewer_id=%d AND status='recused'", $app['id'], absint( $uid ) ) );
		return null === $count || $wpdb->last_error || (int) $count > 0;
	}
	public static function can_view_application( $app, $uid = 0 ) {
		if ( ! is_array( $app ) ) { return false; }
		$uid = $uid ? absint( $uid ) : get_current_user_id();
		if ( ! $uid ) { return false; }
		if ( user_can( $uid, 'manage_options' ) || (int) $app['user_id'] === $uid ) { return true; }
		return 'eligible' === $app['status'] && in_array( self::phase( $app['award_id'] ), array( 'review', 'decision', 'announced' ), true ) && self::is_reviewer( $app['award_id'], $uid ) && ! self::conflicted( $app, $uid );
	}
	public static function get_application( $id ) { $app = self::raw_application( $id ); return self::can_view_application( $app ) ? $app : null; }
	public static function applications( $award_id ) { return array_values( array_filter( self::raw_applications( $award_id ), array( __CLASS__, 'can_view_application' ) ) ); }
	public static function own_application( $award_id, $uid = 0 ) {
		global $wpdb; $uid = $uid ? absint( $uid ) : get_current_user_id();
		if ( ! $uid || ( $uid !== get_current_user_id() && ! self::can_manage() ) ) { return null; }
		return self::decode( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cyw_bp_applications WHERE award_id=%d AND user_id=%d", absint( $award_id ), $uid ), ARRAY_A ) );
	}
	private static function truth( $value ) { return in_array( $value, array( true, 1, '1', 'yes', 'on' ), true ); }
	private static function audit( $old, $action, $extra = array() ) {
		return array_merge( array( 'at' => gmdate( 'c' ), 'actor_id' => get_current_user_id(), 'action' => $action, 'version' => (int) ( $old['record']['version'] ?? 1 ), 'record' => array_diff_key( $old['record'], array( 'history' => true ) ), 'files' => $old['files'], 'status' => $old['status'], 'decision' => $old['decision'] ), $extra );
	}
	public static function submit( $award_id, $input, $uploads = array(), $uid = 0 ) {
		global $wpdb; $award_id = absint( $award_id ); $uid = $uid ? absint( $uid ) : get_current_user_id();
		if ( ! is_array( $input ) || ! is_array( $uploads ) ) { return self::error( 'input', 'The application input is invalid.' ); }
		foreach ( array( 'first_name', 'last_name', 'institution', 'title', 'journal', 'email', 'dob', 'online_date', 'doi', 'no_prior_award', 'eligibility' ) as $field ) { if ( isset( $input[ $field ] ) && ! is_scalar( $input[ $field ] ) ) { return self::error( 'input', 'Provide a single value for each application field.' ); } }
		if ( ! $uid || $uid !== get_current_user_id() ) { return self::error( 'login', 'Sign in to submit your own application.' ); }
		if ( 'cyw_award' !== get_post_type( $award_id ) || ( 'publish' !== get_post_status( $award_id ) && ! self::can_manage() ) ) { return self::error( 'award', 'This award is not available for applications.' ); }
		$lock = self::lock( $award_id ); if ( is_wp_error( $lock ) ) { return $lock; }
		$new_files = array();
		try {
			if ( 'applications' !== self::phase( $award_id ) ) { return self::error( 'closed', 'Applications are not currently open.' ); }
			$cfg = self::config( $award_id ); $old = self::own_application( $award_id, $uid ); $record = array();
			foreach ( array( 'first_name', 'last_name', 'institution', 'title', 'journal' ) as $key ) {
				$record[ $key ] = sanitize_text_field( $input[ $key ] ?? '' );
				if ( '' === $record[ $key ] || strlen( $record[ $key ] ) > ( 'title' === $key ? 1500 : 400 ) ) { return self::error( 'required', 'Provide a valid ' . str_replace( '_', ' ', $key ) . '.' ); }
			}
			$record['email'] = sanitize_email( $input['email'] ?? '' ); if ( ! is_email( $record['email'] ) ) { return self::error( 'email', 'Provide a valid contact email.' ); }
			$dob = self::date( $input['dob'] ?? '', $cfg['timezone'], true );
			$online = self::date( $input['online_date'] ?? '', $cfg['timezone'], true );
			$first = $old ? new DateTimeImmutable( $old['created_at'], new DateTimeZone( 'UTC' ) ) : new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
			$submission_day = $first->setTimezone( new DateTimeZone( $cfg['timezone'] ) )->setTime( 0, 0 );
			if ( ! $dob || $dob > $submission_day || $dob->diff( $submission_day )->y > 35 ) { return self::error( 'age', 'Applicants must be 35 or younger on their first submission date.' ); }
			if ( $old && $old['record']['dob'] !== $dob->format( 'Y-m-d' ) ) { return self::error( 'birthdate', 'The date of birth cannot be changed after submission. Contact the administrator for a correction.' ); }
			$close = self::date( $cfg['close_at'], $cfg['timezone'] );
			$start = $close->modify( '-12 months' )->setTime( 0, 0 );
			if ( ! $online || $online < $start || $online > $close || $online > ( new DateTimeImmutable( 'now', new DateTimeZone( $cfg['timezone'] ) ) )->setTime( 0, 0 ) ) { return self::error( 'online_date', 'The formal online publication date must fall in the 12 months ending on the application deadline and must not be in the future.' ); }
			if ( ! self::truth( $input['no_prior_award'] ?? false ) || ! self::truth( $input['eligibility'] ?? false ) ) { return self::error( 'eligibility', 'Confirm the eligibility declaration and that you have never received Best Paper or Outstanding Paper.' ); }
			$prior_awards = $wpdb->get_col( $wpdb->prepare( "SELECT award_id FROM {$wpdb->prefix}cyw_bp_applications WHERE user_id=%d AND award_id<>%d AND decision IN ('best','outstanding')", $uid, $award_id ) );
			if ( $wpdb->last_error ) { return self::error( 'storage', 'Previous award eligibility could not be verified. Please try again.' ); }
			foreach ( (array) $prior_awards as $prior_award ) { if ( 'announced' === self::config( $prior_award )['status'] ) { return self::error( 'prior_award', 'Previous Best Paper and Outstanding Paper recipients cannot apply again.' ); } }
			$doi = trim( strtolower( sanitize_text_field( $input['doi'] ?? '' ) ) );
			$doi = preg_replace( '~^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)~i', '', $doi );
			if ( '' !== $doi && ( strlen( $doi ) > 255 || ! preg_match( '~^10\.\d{4,9}/\S+$~D', $doi ) ) ) { return self::error( 'doi', 'Enter a valid DOI or leave it blank when none exists.' ); }
			$title_key = preg_replace( '/[^\p{L}\p{N}]/u', '', function_exists( 'mb_strtolower' ) ? mb_strtolower( remove_accents( $record['title'] ), 'UTF-8' ) : strtolower( remove_accents( $record['title'] ) ) );
			$paper_key = hash( 'sha256', $doi ? 'doi:' . $doi : 'title:' . $title_key );
			$existing_apps = self::raw_applications( $award_id );
			if ( $wpdb->last_error ) { return self::error( 'storage', 'Duplicate-paper checks could not be completed. Please try again.' ); }
			foreach ( $existing_apps as $app ) {
				if ( $old && $app['id'] === $old['id'] ) { continue; }
				$existing_title = preg_replace( '/[^\p{L}\p{N}]/u', '', function_exists( 'mb_strtolower' ) ? mb_strtolower( remove_accents( $app['record']['title'] ), 'UTF-8' ) : strtolower( remove_accents( $app['record']['title'] ) ) );
				if ( $app['paper_key'] === $paper_key || $existing_title === $title_key ) { return self::error( 'duplicate', 'This paper already has an application for this award. One paper is considered only once.' ); }
			}
			$files = $old ? $old['files'] : array();
			foreach ( array( 'paper', 'cv' ) as $kind ) {
				$upload = $uploads[ $kind ] ?? null;
				if ( $upload && UPLOAD_ERR_NO_FILE !== (int) ( $upload['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
					$file = self::store_upload( $upload );
					if ( is_wp_error( $file ) ) { return $file; }
					$new_files[] = $file; $files[ $kind ] = $file;
				}
				if ( empty( $files[ $kind ] ) ) { return self::error( 'file', 'Upload both the paper PDF and CV PDF.' ); }
			}
			$record = array_merge( $record, array( 'dob' => $dob->format( 'Y-m-d' ), 'online_date' => $online->format( 'Y-m-d' ), 'doi' => $doi, 'no_prior_award' => true, 'eligibility' => true, 'submitted_at' => $first->format( 'c' ), 'age_at_submission' => $dob->diff( $submission_day )->y, 'window_start' => $start->format( 'Y-m-d' ), 'window_end' => $close->format( 'Y-m-d' ), 'timezone' => $cfg['timezone'], 'version' => $old ? (int) $old['record']['version'] + 1 : 1, 'history' => $old ? $old['record']['history'] : array() ) );
			if ( $old ) { $record['history'][] = self::audit( $old, 'applicant_update' ); }
			$record_json = wp_json_encode( $record ); $files_json = wp_json_encode( $files );
			if ( false === $record_json || false === $files_json ) { return self::error( 'encoding', 'The application could not be encoded safely.' ); }
			$data = array( 'award_id' => $award_id, 'user_id' => $uid, 'paper_key' => $paper_key, 'record' => $record_json, 'files' => $files_json, 'status' => 'submitted', 'updated_at' => gmdate( 'Y-m-d H:i:s' ), 'decision' => 'none' );
			if ( $old ) { $ok = $wpdb->update( $wpdb->prefix . 'cyw_bp_applications', $data, array( 'id' => $old['id'], 'user_id' => $uid ) ); $id = $old['id']; }
			else { $data['created_at'] = $first->format( 'Y-m-d H:i:s' ); $ok = $wpdb->insert( $wpdb->prefix . 'cyw_bp_applications', $data ); $id = (int) $wpdb->insert_id; }
			if ( false === $ok || ! $id ) { return self::error( 'storage', 'The application could not be saved. Check for an existing application and try again.' ); }
			$new_files = array(); // Files now belong to the saved immutable version history.
			try { wp_schedule_single_event( time() + 20, 'cywater_bp_receipt', array( $id, $record['version'] ), true ); } catch ( Throwable $e ) { /* Receipt transport must never replace a saved application with an error screen. */ }
			return $id;
		} finally { foreach ( $new_files as $file ) { self::delete_new_file( $file ); } self::unlock( $lock ); }
	}

	/** Storage is outside the entire HTTP document tree, including when WP runs in staging/. */
	public static function private_directory() {
		$wp_root = realpath( ABSPATH ); if ( ! $wp_root ) { return self::error( 'private_dir', 'Cannot resolve the WordPress root.' ); }
		$document = $wp_root;
		for ( $cursor = $wp_root; dirname( $cursor ) !== $cursor; $cursor = dirname( $cursor ) ) {
			if ( in_array( strtolower( basename( $cursor ) ), array( 'public_html', 'htdocs', 'httpdocs', 'www', 'html' ), true ) ) { $document = $cursor; break; }
		}
		$server_root = ! empty( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( $_SERVER['DOCUMENT_ROOT'] ) : false;
		if ( $server_root && self::within( $document, $server_root ) ) { $document = $server_root; }
		$path = defined( 'CYWATER_BEST_PAPER_PRIVATE_DIR' ) ? CYWATER_BEST_PAPER_PRIVATE_DIR : dirname( $document ) . '/cywater-private/best-paper-' . substr( hash( 'sha256', $wp_root . ':' . get_current_blog_id() ), 0, 12 );
		if ( ! is_string( $path ) || '' === $path || self::within( wp_normalize_path( $path ), wp_normalize_path( $document ) ) ) { return self::error( 'private_dir', 'Best Paper files require a private directory outside the document root.' ); }
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) { return self::error( 'private_dir', 'Private file storage is unavailable.' ); }
		$real = realpath( $path );
		if ( ! $real || self::within( $real, $document ) || self::within( $real, $wp_root ) || ! is_writable( $real ) ) { return self::error( 'private_dir', 'Private storage failed its containment check.' ); }
		@chmod( $real, 0700 ); return $real;
	}
	private static function within( $path, $root ) { $path = rtrim( wp_normalize_path( $path ), '/' ); $root = rtrim( wp_normalize_path( $root ), '/' ); if ( '\\' === DIRECTORY_SEPARATOR ) { $path = strtolower( $path ); $root = strtolower( $root ); } return $path === $root || 0 === strpos( $path, $root . '/' ); }
	private static function store_upload( $upload ) {
		if ( ! is_array( $upload ) || ! isset( $upload['error'], $upload['tmp_name'], $upload['name'] ) || ! is_scalar( $upload['error'] ) || ! is_string( $upload['tmp_name'] ) || ! is_string( $upload['name'] ) || UPLOAD_ERR_OK !== (int) $upload['error'] ) { return self::error( 'upload', 'The upload did not complete. Please try again.' ); }
		$tmp = $upload['tmp_name'] ?? ''; $name = sanitize_file_name( $upload['name'] ?? '' );
		if ( ! is_string( $tmp ) || ! is_file( $tmp ) || ( ! is_uploaded_file( $tmp ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) ) { return self::error( 'upload', 'The uploaded file cannot be verified.' ); }
		$bytes = filesize( $tmp );
		if ( ! $bytes || $bytes > self::MAX_BYTES || 'pdf' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) { return self::error( 'file_type', 'Only PDF files up to 20 MB are accepted.' ); }
		if ( ! class_exists( 'finfo' ) || 'application/pdf' !== ( new finfo( FILEINFO_MIME_TYPE ) )->file( $tmp ) || '%PDF-' !== file_get_contents( $tmp, false, null, 0, 5 ) ) { return self::error( 'file_type', 'The uploaded file must be a genuine PDF.' ); }
		$directory = self::private_directory(); if ( is_wp_error( $directory ) ) { return $directory; }
		$stored = wp_generate_uuid4() . '.pdf'; $target = $directory . '/' . $stored;
		$ok = defined( 'WP_CLI' ) && WP_CLI ? copy( $tmp, $target ) : move_uploaded_file( $tmp, $target );
		if ( ! $ok || ! is_file( $target ) ) { return self::error( 'upload', 'The private PDF could not be stored.' ); }
		@chmod( $target, 0600 );
		return array( 'stored' => $stored, 'original' => $name, 'bytes' => $bytes, 'mime' => 'application/pdf', 'sha256' => hash_file( 'sha256', $target ) );
	}
	private static function delete_new_file( $file ) { $directory = self::private_directory(); if ( is_wp_error( $directory ) ) { return; } $path = realpath( $directory . '/' . basename( $file['stored'] ) ); if ( $path && self::within( $path, $directory ) && is_file( $path ) ) { wp_delete_file( $path ); } }
	public static function file_path( $app, $kind ) {
		if ( ! is_array( $app ) ) { $app = self::raw_application( $app ); }
		if ( ! self::can_view_application( $app ) || ! in_array( $kind, array( 'paper', 'cv' ), true ) || empty( $app['files'][ $kind ] ) ) { return self::error( 'forbidden', 'You do not have access to this file.' ); }
		$file = $app['files'][ $kind ]; $directory = self::private_directory(); if ( is_wp_error( $directory ) ) { return $directory; }
		if ( ! preg_match( '/^[a-f0-9-]{36}\.pdf$/D', $file['stored'] ?? '' ) ) { return self::error( 'file', 'The protected file is unavailable.' ); }
		$path = realpath( $directory . '/' . $file['stored'] );
		if ( ! $path || ! self::within( $path, $directory ) || ! is_file( $path ) || ! is_readable( $path ) ) { return self::error( 'file', 'The protected file is unavailable.' ); }
		if ( empty( $file['sha256'] ) || ! hash_equals( $file['sha256'], hash_file( 'sha256', $path ) ) ) { return self::error( 'file_integrity', 'The protected file failed its integrity check. Contact the administrator.' ); }
		return $path;
	}
	public static function download_url( $app_id, $kind ) { return wp_nonce_url( add_query_arg( array( 'action' => 'cywater_bp_file', 'application_id' => absint( $app_id ), 'kind' => sanitize_key( $kind ) ), admin_url( 'admin-post.php' ) ), 'cywater_bp_file_' . absint( $app_id ) . '_' . sanitize_key( $kind ) ); }
	public static function stream_file() {
		$id = absint( $_GET['application_id'] ?? 0 ); $kind = sanitize_key( $_GET['kind'] ?? '' );
		if ( ! is_user_logged_in() || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'cywater_bp_file_' . $id . '_' . $kind ) ) { wp_die( 'You do not have access to this file.', '', array( 'response' => 403 ) ); }
		$app = self::raw_application( $id ); $path = self::file_path( $app, $kind );
		if ( is_wp_error( $path ) ) { wp_die( esc_html( $path->get_error_message() ), '', array( 'response' => 403 ) ); }
		nocache_headers(); header( 'Cache-Control: private, no-store, max-age=0' ); header( 'X-Robots-Tag: noindex, noarchive' ); header( 'X-Content-Type-Options: nosniff' ); header( 'Content-Type: application/pdf' ); header( 'Content-Length: ' . filesize( $path ) ); header( "Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode( $app['files'][ $kind ]['original'] ) );
		readfile( $path ); exit;
	}

	public static function staff_update( $app_id, $input ) {
		global $wpdb; $app = self::raw_application( $app_id );
		if ( ! self::can_manage() || ! $app ) { return self::error( 'forbidden', 'Only an administrator can change application eligibility or decisions.' ); }
		$lock = self::lock( $app['award_id'] ); if ( is_wp_error( $lock ) ) { return $lock; }
		try {
			$app = self::raw_application( $app_id ); $phase = self::phase( $app['award_id'] );
			if ( in_array( $phase, array( 'announced', 'draft', 'not_open' ), true ) ) { return self::error( 'phase', 'This edition does not permit application changes.' ); }
			$status = isset( $input['status'] ) ? sanitize_key( $input['status'] ) : $app['status'];
			if ( ! in_array( $status, array( 'submitted', 'eligible', 'ineligible', 'needs_changes' ), true ) ) { return self::error( 'status', 'Choose a valid eligibility status.' ); }
			$decision = isset( $input['decision'] ) ? sanitize_key( $input['decision'] ) : $app['decision'];
			if ( ! in_array( $decision, array( 'none', 'best', 'outstanding' ), true ) || ( $decision !== $app['decision'] && 'decision' !== $phase ) ) { return self::error( 'decision', 'Award decisions can only be recorded during the decision phase.' ); }
			if ( 'none' !== $decision && 'eligible' !== $status ) { return self::error( 'decision', 'Only eligible applications may receive an award.' ); }
			if ( 'best' === $decision ) { foreach ( self::raw_applications( $app['award_id'] ) as $other ) { if ( $other['id'] !== $app['id'] && 'best' === $other['decision'] ) { return self::error( 'best_count', 'This edition already has a Best Paper decision. Clear it before selecting another.' ); } } }
			$record = $app['record']; $record['history'][] = self::audit( $app, 'staff_update', array( 'note' => sanitize_textarea_field( $input['note'] ?? '' ) ) );
			$record['version'] = (int) $record['version'] + 1;
			$record['staff_note'] = sanitize_textarea_field( $input['note'] ?? ( $record['staff_note'] ?? '' ) );
			$result = $wpdb->update( $wpdb->prefix . 'cyw_bp_applications', array( 'status' => $status, 'decision' => $decision, 'record' => wp_json_encode( $record ), 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $app['id'] ) );
			return false === $result ? self::error( 'storage', 'The application change could not be saved.' ) : true;
		} finally { self::unlock( $lock ); }
	}
	public static function assign( $app_id, $reviewer_id ) {
		global $wpdb; $app = self::raw_application( $app_id ); $reviewer_id = absint( $reviewer_id );
		if ( ! self::can_manage() || ! $app ) { return self::error( 'forbidden', 'Only an administrator can assign reviewers.' ); }
		$lock = self::lock( $app['award_id'] ); if ( is_wp_error( $lock ) ) { return $lock; }
		try {
			$app = self::raw_application( $app_id ); $cfg = self::config( $app['award_id'] );
			if ( ! in_array( self::phase( $app['award_id'] ), array( 'closed', 'review' ), true ) || 'eligible' !== $app['status'] || ! in_array( $reviewer_id, array_map( 'intval', (array) $cfg['reviewer_ids'] ), true ) || self::conflicted( $app, $reviewer_id ) ) { return self::error( 'assignment', 'Assign an eligible paper to a configured, non-conflicted reviewer after applications close.' ); }
			foreach ( self::raw_reviews( $app_id ) as $row ) { if ( (int) $row['reviewer_id'] === $reviewer_id ) { return true; } }
			$result = $wpdb->insert( $wpdb->prefix . 'cyw_bp_reviews', array( 'application_id' => $app['id'], 'reviewer_id' => $reviewer_id, 'score' => null, 'comments' => '', 'history' => '[]', 'status' => 'assigned', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ) );
			return false === $result ? self::error( 'storage', 'The review assignment could not be saved.' ) : true;
		} finally { self::unlock( $lock ); }
	}
	public static function review( $app_id, $score, $comments, $recuse = false ) {
		global $wpdb; $app = self::raw_application( $app_id ); $uid = get_current_user_id();
		if ( ! $app || ! $uid || ! self::is_reviewer( $app['award_id'], $uid ) || $uid === $app['user_id'] ) { return self::error( 'forbidden', 'You are not assigned to review this application.' ); }
		$lock = self::lock( $app['award_id'] ); if ( is_wp_error( $lock ) ) { return $lock; }
		try {
			$app = self::raw_application( $app_id );
			$cfg = self::config( $app['award_id'] ); $deadline = $cfg['review_deadline'] ? self::date( $cfg['review_deadline'], $cfg['timezone'] ) : false;
			if ( 'review' !== self::phase( $app['award_id'] ) || 'eligible' !== $app['status'] || ( $deadline && time() > $deadline->getTimestamp() ) ) { return self::error( 'review_closed', 'Independent review is not currently open.' ); }
			$assignment = null; foreach ( self::raw_reviews( $app_id ) as $row ) { if ( (int) $row['reviewer_id'] === $uid ) { $assignment = $row; break; } }
			if ( ! $assignment || 'recused' === $assignment['status'] ) { return self::error( 'assignment', 'An active review assignment is required.' ); }
			if ( ! $recuse && ( ! is_scalar( $score ) || ! is_numeric( $score ) || ! is_finite( (float) $score ) || (float) $score < 0 || (float) $score > 10 ) ) { return self::error( 'score', 'Enter a numeric score from 0 to 10.' ); }
			$comments = sanitize_textarea_field( $comments ); if ( strlen( $comments ) > 20000 ) { return self::error( 'comments', 'Comments are limited to 20,000 bytes.' ); }
			$history = json_decode( $assignment['history'] ?? '[]', true ); $history = is_array( $history ) ? $history : array();
			$history[] = array( 'at' => gmdate( 'c' ), 'actor_id' => $uid, 'action' => $recuse ? 'recuse' : 'submit_score', 'previous_score' => $assignment['score'], 'previous_comments' => $assignment['comments'], 'previous_status' => $assignment['status'], 'application_version' => $app['record']['version'] );
			$result = $wpdb->update( $wpdb->prefix . 'cyw_bp_reviews', array( 'score' => $recuse ? null : round( (float) $score, 3 ), 'comments' => $comments, 'history' => wp_json_encode( $history ), 'status' => $recuse ? 'recused' : 'submitted', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => (int) $assignment['id'], 'reviewer_id' => $uid ) );
			return false === $result ? self::error( 'storage', 'The review could not be saved.' ) : true;
		} finally { self::unlock( $lock ); }
	}
	public static function reviews( $app_id ) {
		$app = self::raw_application( $app_id ); if ( ! $app || ! self::can_view_application( $app ) ) { return array(); }
		if ( self::can_manage() ) { return self::raw_reviews( $app_id ); }
		$uid = get_current_user_id(); if ( ! self::is_reviewer( $app['award_id'], $uid ) || self::conflicted( $app, $uid ) ) { return array(); }
		$all = in_array( self::phase( $app['award_id'] ), array( 'decision', 'announced' ), true );
		return array_values( array_filter( self::raw_reviews( $app_id ), static function( $row ) use ( $uid, $all ) { return $all || (int) $row['reviewer_id'] === $uid; } ) );
	}
	public static function score_summary( $award_id ) {
		$cfg = self::config( $award_id ); $uid = get_current_user_id();
		if ( ! self::can_manage() && ( ! self::is_reviewer( $award_id, $uid ) || ! in_array( self::phase( $award_id ), array( 'decision', 'announced' ), true ) ) ) { return array(); }
		$apps = self::raw_applications( $award_id ); $reviewer_scores = array(); $scores = array();
		foreach ( $apps as $app ) {
			if ( 'eligible' !== $app['status'] ) { continue; }
			foreach ( self::raw_reviews( $app['id'] ) as $review ) {
				if ( 'submitted' !== $review['status'] || null === $review['score'] || ! in_array( (int) $review['reviewer_id'], array_map( 'intval', $cfg['reviewer_ids'] ), true ) ) { continue; }
				$rid = (int) $review['reviewer_id']; $value = (float) $review['score']; $reviewer_scores[ $rid ][] = $value; $scores[ $app['id'] ][ $rid ] = $value;
			}
		}
		$stats = array(); foreach ( $reviewer_scores as $rid => $values ) {
			$count = count( $values ); $mean = array_sum( $values ) / $count; $squared = 0.0; foreach ( $values as $value ) { $squared += pow( $value - $mean, 2 ); }
			$stats[ $rid ] = array( 'mean' => $mean, 'sd' => $count > 1 ? sqrt( $squared / ( $count - 1 ) ) : null );
		}
		$result = array(); foreach ( $apps as $app ) {
			if ( ! self::can_view_application( $app ) || ( ! self::can_manage() && self::conflicted( $app, $uid ) ) ) { continue; }
			$values = $scores[ $app['id'] ] ?? array(); $zs = array(); $complete = true;
			foreach ( $values as $rid => $value ) { $sd = $stats[ $rid ]['sd']; if ( null === $sd || $sd < 0.000000001 ) { $complete = false; continue; } $zs[] = ( $value - $stats[ $rid ]['mean'] ) / $sd; }
			$result[ $app['id'] ] = array( 'count' => count( $values ), 'mean' => $values ? array_sum( $values ) / count( $values ) : null, 'z_mean' => $complete && $zs ? array_sum( $zs ) / count( $zs ) : null, 'z_count' => count( $zs ) );
		}
		return $result;
	}
	public static function send_receipt( $app_id, $version ) {
		$app = self::raw_application( $app_id ); if ( ! $app || (int) $version !== (int) $app['record']['version'] ) { return; }
		try {
			wp_mail( $app['record']['email'], 'CYWater Best Paper application received', 'We received your application #' . $app['id'] . ' for ' . wp_strip_all_tags( get_the_title( $app['award_id'] ) ) . ".\n\nThis confirms receipt only, not eligibility or an award decision. Sign in to review your application and uploaded files:\n" . get_permalink( $app['award_id'] ) );
		} catch ( Throwable $e ) { /* Mail failure never rolls back an accepted application. */ }
	}
}
