<?php
/**
 * Single, least-privilege Logo Call review, governance and export workspace.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Operations_Logo_Review {
	public const PAGE_SLUG           = 'cywater-logo-reviews';
	public const ACTION              = 'cywater_operations_logo_review';
	public const NONCE_FIELD         = 'cywater_operations_logo_review_nonce';
	public const NONCE_ACTION_PREFIX = 'cywater_operations_logo_review_';

	private const ENTRY_TYPE       = 'cyw_logo_entry';
	private const FINALISTS_ACTION = 'cywater_operations_logo_finalists';
	private const SELECT_ACTION    = 'cywater_operations_logo_select';
	private const FULFILL_ACTION   = 'cywater_operations_logo_fulfillment';
	private const EXPORT_ACTION    = 'cywater_operations_logo_export';
	private const REVIEW_STATUSES  = array( 'submitted', 'shortlisted', 'not_selected', 'withdrawn' );
	private const RIGHTS_STATUSES  = array( 'pending', 'accepted', 'declined' );
	private const FILE_STATUSES    = array( 'not_requested', 'requested', 'received', 'accepted' );
	private const REWARD_STATUSES  = array( 'pending', 'fulfilled' );

	public static function register() {
		if ( ! class_exists( 'CYWater_Logo_Call' ) ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'add_review_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_review' ) );
		add_action( 'admin_post_' . self::FINALISTS_ACTION, array( __CLASS__, 'handle_finalists' ) );
		add_action( 'admin_post_' . self::SELECT_ACTION, array( __CLASS__, 'handle_selection' ) );
		add_action( 'admin_post_' . self::FULFILL_ACTION, array( __CLASS__, 'handle_fulfillment' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'handle_export' ) );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_request_scoped_capability' ), 20, 4 );
	}

	public static function add_review_page() {
		add_menu_page( __( 'Logo reviews', 'cywater-operations' ), __( 'Logo reviews', 'cywater-operations' ), 'cywater_review_logo_entries', self::PAGE_SLUG, array( __CLASS__, 'render_review_page' ), 'dashicons-awards', 26 );
	}

	/** Map edit_post only for the exact nonce-bound review or source-file request. */
	public static function map_request_scoped_capability( $caps, $cap, $user_id, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 'edit_post' !== $cap || empty( $args[0] ) || get_current_user_id() !== absint( $user_id ) ) {
			return $caps;
		}
		$entry_id = absint( $args[0] );
		$entry    = get_post( $entry_id );
		if ( ! $entry || self::ENTRY_TYPE !== $entry->post_type || 'private' !== $entry->post_status ) {
			return $caps;
		}
		return self::is_exact_review_request( $entry_id ) || self::is_exact_asset_request( $entry_id ) ? array( 'cywater_review_logo_entries' ) : $caps;
	}

	private static function is_exact_review_request( $entry_id ) {
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		$posted = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		$nonce  = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		return doing_action( 'admin_post_' . self::ACTION ) && 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) && self::ACTION === $action && $entry_id === $posted && wp_verify_nonce( $nonce, self::NONCE_ACTION_PREFIX . $entry_id );
	}

	private static function is_exact_asset_request( $entry_id ) {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$target = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0;
		$kind   = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		return doing_action( 'admin_post_cywater_logo_asset' ) && 'GET' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) && 'cywater_logo_asset' === $action && $entry_id === $target && 'source' === $kind && wp_verify_nonce( $nonce, 'cywater_logo_asset_' . $entry_id . '_source' );
	}

	public static function render_review_page() {
		self::require_capability( 'cywater_review_logo_entries' );
		$event_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$entries  = CYWater_Logo_Call::entries( $event_id );
		$events   = self::entry_events( CYWater_Logo_Call::entries() );
		$selected = self::selected_entry( $entries );

		echo '<div class="wrap cywater-logo-review"><h1>' . esc_html__( 'Logo Call reviews', 'cywater-operations' ) . '</h1>';
		echo '<p>' . esc_html__( 'This is the sole operational entry for protected submissions, vote rankings, finalist confirmation, Board selection and handoff tracking. Public voting never exposes entrant identity.', 'cywater-operations' ) . '</p>';
		self::render_notice();
		self::render_toolbar( $events, $event_id, ! empty( $entries ) );
		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'There are no private Logo Call submissions in this view.', 'cywater-operations' ) . '</p></div>';
			return;
		}
		self::render_entries_table( $entries, $event_id );
		if ( $selected ) {
			self::render_entry_panel( $selected, $event_id );
		}
		if ( $event_id ) {
			self::render_governance_panels( $event_id, $entries );
		}
		echo '</div>';
	}

	private static function render_notice() {
		$result = isset( $_GET['cywater_logo_review'] ) ? sanitize_key( wp_unslash( $_GET['cywater_logo_review'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $result ) {
			return;
		}
		$messages = array(
			'updated'   => __( 'The review was saved and audited.', 'cywater-operations' ),
			'finalists' => __( 'The five finalists and their Professional membership rewards were recorded and audited.', 'cywater-operations' ),
			'selected'  => __( 'The Board selection was recorded and audited.', 'cywater-operations' ),
			'fulfilled' => __( 'The rights, final-file and reward handoff was updated and audited.', 'cywater-operations' ),
			'error'     => __( 'The requested change was not saved.', 'cywater-operations' ),
		);
		echo '<div class="notice ' . ( 'error' === $result ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $messages[ $result ] ?? $messages['error'] ) . '</p></div>';
	}

	private static function render_toolbar( $events, $event_id, $has_entries ) {
		echo '<div class="cywater-logo-review__toolbar"><form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '"><label for="cywater-logo-event-filter"><strong>' . esc_html__( 'Event', 'cywater-operations' ) . '</strong></label> <select id="cywater-logo-event-filter" name="event"><option value="0">' . esc_html__( 'All Logo Calls', 'cywater-operations' ) . '</option>';
		foreach ( $events as $id => $title ) {
			echo '<option value="' . esc_attr( $id ) . '" ' . selected( $event_id, $id, false ) . '>' . esc_html( $title ) . '</option>';
		}
		echo '</select> '; submit_button( __( 'Filter', 'cywater-operations' ), 'secondary', '', false ); echo '</form>';
		if ( $has_entries ) {
			$url = wp_nonce_url( add_query_arg( array( 'action' => self::EXPORT_ACTION, 'event' => $event_id ), admin_url( 'admin-post.php' ) ), self::EXPORT_ACTION . '_' . $event_id );
			echo '<a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Export ZIP + CSV', 'cywater-operations' ) . '</a>';
		}
		echo '</div>';
	}

	private static function render_entries_table( $entries, $event_id ) {
		echo '<table class="widefat striped cywater-logo-review__table"><thead><tr><th>' . esc_html__( 'Preview', 'cywater-operations' ) . '</th><th>' . esc_html__( 'Work / Event', 'cywater-operations' ) . '</th><th>' . esc_html__( 'Entrant', 'cywater-operations' ) . '</th><th>' . esc_html__( 'Professional information', 'cywater-operations' ) . '</th><th>' . esc_html__( 'Status / Votes', 'cywater-operations' ) . '</th><th>' . esc_html__( 'Submitted', 'cywater-operations' ) . '</th></tr></thead><tbody>';
		foreach ( $entries as $entry ) {
			$user        = get_userdata( (int) $entry->post_author );
			$entry_event = absint( get_post_meta( $entry->ID, '_cywater_logo_event_id', true ) );
			$file        = CYWater_Logo_Call::source_file( $entry->ID );
			$image       = wp_nonce_url( add_query_arg( array( 'action' => 'cywater_logo_asset', 'entry' => $entry->ID, 'kind' => 'source', 'preview' => 1 ), admin_url( 'admin-post.php' ) ), 'cywater_logo_asset_' . $entry->ID . '_source' );
			$country     = self::country_label( (string) get_user_meta( $entry->post_author, 'cyw_country', true ) );
			$status      = (string) get_post_meta( $entry->ID, '_cywater_logo_status', true );
			$link        = self::page_url( $entry->ID, $event_id );
			echo '<tr><td><a href="' . esc_url( $link ) . '"><img class="cywater-logo-review__thumb" src="' . esc_url( $image ) . '" alt=""></a></td>';
			echo '<td><strong><a href="' . esc_url( $link ) . '">' . esc_html( CYWater_Logo_Call::work_number( $entry->ID ) ) . '</a></strong><br>' . esc_html( get_the_title( $entry_event ) ) . '<br><small>' . esc_html( $file['original'] ?? '' ) . '</small></td>';
			echo '<td>' . esc_html( (string) get_post_meta( $entry->ID, '_cywater_logo_submitter_name', true ) ?: ( $user ? $user->display_name : '—' ) ) . '<br><a href="mailto:' . esc_attr( $user ? $user->user_email : '' ) . '">' . esc_html( $user ? $user->user_email : '—' ) . '</a></td>';
			echo '<td>' . esc_html( (string) get_user_meta( $entry->post_author, 'cyw_institution_name', true ) ?: '—' ) . '<br>' . esc_html( (string) get_user_meta( $entry->post_author, 'cyw_professional_title', true ) ?: '—' ) . '<br>' . esc_html( $country ?: '—' ) . '</td>';
			echo '<td>' . esc_html( CYWater_Logo_Call::statuses()[ $status ] ?? $status ) . '<br>' . esc_html( sprintf( __( '%d votes', 'cywater-operations' ), CYWater_Logo_Call::vote_count( $entry->ID ) ) ) . '</td>';
			echo '<td>' . esc_html( get_date_from_gmt( $entry->post_date_gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_entry_panel( $entry, $event_id ) {
		$status = (string) get_post_meta( $entry->ID, '_cywater_logo_status', true );
		$url    = wp_nonce_url( add_query_arg( array( 'action' => 'cywater_logo_asset', 'entry' => $entry->ID, 'kind' => 'source' ), admin_url( 'admin-post.php' ) ), 'cywater_logo_asset_' . $entry->ID . '_source' );
		echo '<section class="cywater-logo-review__panel"><h2>' . esc_html( CYWater_Logo_Call::work_number( $entry->ID ) ) . '</h2><p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Download submitted file', 'cywater-operations' ) . '</a></p>';
		echo '<p><strong>' . esc_html__( 'Design statement', 'cywater-operations' ) . '</strong><br>' . nl2br( esc_html( (string) get_post_meta( $entry->ID, '_cywater_logo_statement', true ) ?: '—' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Submission terms record', 'cywater-operations' ) . '</strong><br>' . esc_html( (string) get_post_meta( $entry->ID, '_cywater_logo_terms_version', true ) ?: '—' ) . ' · ' . esc_html( (string) get_post_meta( $entry->ID, '_cywater_logo_terms_accepted_at', true ) ?: '—' ) . '</p>';
		if ( in_array( $status, self::REVIEW_STATUSES, true ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="entry_id" value="' . esc_attr( $entry->ID ) . '"><input type="hidden" name="event" value="' . esc_attr( $event_id ) . '">';
			wp_nonce_field( self::NONCE_ACTION_PREFIX . $entry->ID, self::NONCE_FIELD );
			echo '<label for="cywater-logo-status"><strong>' . esc_html__( 'Eligibility review', 'cywater-operations' ) . '</strong></label> <select id="cywater-logo-status" name="cywater_logo_status">';
			foreach ( self::REVIEW_STATUSES as $key ) {
				echo '<option value="' . esc_attr( $key ) . '" ' . selected( $status, $key, false ) . '>' . esc_html( CYWater_Logo_Call::statuses()[ $key ] ) . '</option>';
			}
			echo '</select> '; submit_button( __( 'Save eligibility review', 'cywater-operations' ), 'primary', '', false ); echo '</form>';
		}
		if ( 'selected' === $status && current_user_can( 'cywater_manage_logo_fulfillment' ) ) {
			self::render_fulfillment_form( $entry, $event_id );
		}
		echo '</section>';
	}

	private static function render_governance_panels( $event_id, $entries ) {
		if ( 'results' !== CYWater_Logo_Call::current_phase( $event_id ) ) {
			return;
		}
		$finalists = CYWater_Logo_Call::finalists( $event_id );
		if ( current_user_can( 'cywater_select_logo_finalists' ) && empty( $finalists ) ) {
			$ranked = array_values( array_filter( $entries, static function ( $entry ) { return 'shortlisted' === get_post_meta( $entry->ID, '_cywater_logo_status', true ); } ) );
			usort( $ranked, static function ( $a, $b ) { return CYWater_Logo_Call::vote_count( $b->ID ) <=> CYWater_Logo_Call::vote_count( $a->ID ) ?: $a->ID <=> $b->ID; } );
			echo '<section class="cywater-logo-review__panel"><h2>' . esc_html__( 'Confirm five voting finalists', 'cywater-operations' ) . '</h2><p>' . esc_html__( 'Select exactly five entries from the highest-ranked pool. When fifth place is tied, this records the Board’s tie resolution. Each finalist earns two years of Professional membership; any existing paid-membership conflict is flagged for manual fulfillment.', 'cywater-operations' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::FINALISTS_ACTION ) . '"><input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
			wp_nonce_field( self::FINALISTS_ACTION . '_' . $event_id );
			foreach ( $ranked as $index => $entry ) {
				echo '<label class="cywater-logo-review__choice"><input type="checkbox" name="finalist_ids[]" value="' . esc_attr( $entry->ID ) . '"> <strong>' . esc_html( CYWater_Logo_Call::work_number( $entry->ID ) ) . '</strong> — ' . esc_html( sprintf( __( 'rank %1$d, %2$d votes', 'cywater-operations' ), $index + 1, CYWater_Logo_Call::vote_count( $entry->ID ) ) ) . '</label>';
			}
			submit_button( __( 'Confirm five finalists', 'cywater-operations' ) ); echo '</form></section>';
		}
		if ( current_user_can( 'cywater_select_official_logo' ) && 5 === count( $finalists ) && ! self::selected_for_event( $event_id ) ) {
			echo '<section class="cywater-logo-review__panel"><h2>' . esc_html__( 'Record Board selection', 'cywater-operations' ) . '</h2><p>' . esc_html__( 'This action selects the official design from the five confirmed finalists. It does not itself mark the rights assignment, final files or selected-design reward complete.', 'cywater-operations' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::SELECT_ACTION ) . '"><input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
			wp_nonce_field( self::SELECT_ACTION . '_' . $event_id );
			foreach ( $finalists as $entry ) {
				echo '<label class="cywater-logo-review__choice"><input required type="radio" name="entry_id" value="' . esc_attr( $entry->ID ) . '"> ' . esc_html( CYWater_Logo_Call::work_number( $entry->ID ) ) . '</label>';
			}
			submit_button( __( 'Record Board-selected design', 'cywater-operations' ) ); echo '</form></section>';
		}
	}

	private static function render_fulfillment_form( $entry, $event_id ) {
		$rights = (string) get_post_meta( $entry->ID, '_cywater_logo_rights_status', true ) ?: 'pending';
		$files  = (string) get_post_meta( $entry->ID, '_cywater_logo_final_files_status', true ) ?: 'requested';
		$reward = (string) get_post_meta( $entry->ID, '_cywater_logo_reward_status', true ) ?: 'pending';
		echo '<hr><h3>' . esc_html__( 'Selected-design handoff', 'cywater-operations' ) . '</h3><p>' . esc_html__( 'Mark the signed rights assignment and production-ready final files only after independently verifying them. Reward fulfillment is blocked until both are accepted.', 'cywater-operations' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::FULFILL_ACTION ) . '"><input type="hidden" name="entry_id" value="' . esc_attr( $entry->ID ) . '"><input type="hidden" name="event" value="' . esc_attr( $event_id ) . '">';
		wp_nonce_field( self::FULFILL_ACTION . '_' . $entry->ID );
		self::render_status_select( 'cywater-logo-rights', 'rights_status', __( 'Signed rights assignment', 'cywater-operations' ), self::RIGHTS_STATUSES, $rights );
		self::render_status_select( 'cywater-logo-files', 'files_status', __( 'Production-ready final files', 'cywater-operations' ), self::FILE_STATUSES, $files );
		self::render_status_select( 'cywater-logo-reward', 'reward_status', __( 'Reward fulfillment', 'cywater-operations' ), self::REWARD_STATUSES, $reward );
		submit_button( __( 'Save selected-design handoff', 'cywater-operations' ) ); echo '</form>';
	}

	private static function render_status_select( $id, $name, $label, $options, $current ) {
		echo '<p><label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $options as $option ) {
			echo '<option value="' . esc_attr( $option ) . '" ' . selected( $current, $option, false ) . '>' . esc_html( ucwords( str_replace( '_', ' ', $option ) ) ) . '</option>';
		}
		echo '</select></p>';
	}

	public static function handle_review() {
		self::require_capability( 'cywater_review_logo_entries' );
		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		check_admin_referer( self::NONCE_ACTION_PREFIX . $entry_id, self::NONCE_FIELD );
		if ( ! current_user_can( 'edit_post', $entry_id ) ) {
			wp_die( esc_html__( 'The Logo Call review request is not authorized.', 'cywater-operations' ), '', array( 'response' => 403 ) );
		}
		$status = isset( $_POST['cywater_logo_status'] ) ? sanitize_key( wp_unslash( $_POST['cywater_logo_status'] ) ) : '';
		$result = self::transition( $entry_id, $status );
		self::redirect( $entry_id, absint( $_POST['event'] ?? 0 ), true === $result ? 'updated' : 'error' );
	}

	/** Persist an allowlisted eligibility decision; finalists and selection use governance-only actions. */
	public static function transition( $entry_id, $status, $deprecated_reward = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! current_user_can( 'cywater_review_logo_entries' ) ) {
			return new WP_Error( 'cywater_logo_review_forbidden', __( 'You are not allowed to review Logo Call submissions.', 'cywater-operations' ) );
		}
		$entry = self::entry( $entry_id );
		if ( ! $entry ) {
			return new WP_Error( 'cywater_logo_review_unavailable', __( 'The requested Logo Call submission is unavailable.', 'cywater-operations' ) );
		}
		$status = sanitize_key( $status );
		if ( ! in_array( $status, self::REVIEW_STATUSES, true ) ) {
			return new WP_Error( 'cywater_logo_review_status', __( 'The requested eligibility status is not allowed.', 'cywater-operations' ) );
		}
		$old = (string) get_post_meta( $entry->ID, '_cywater_logo_status', true );
		$event_id = absint( get_post_meta( $entry->ID, '_cywater_logo_event_id', true ) );
		if ( $event_id && in_array( CYWater_Logo_Call::current_phase( $event_id ), array( 'voting', 'results' ), true ) ) {
			return new WP_Error( 'cywater_logo_review_locked', __( 'Eligibility review is locked once voting opens.', 'cywater-operations' ) );
		}
		if ( in_array( $old, array( 'finalist', 'selected' ), true ) ) {
			return new WP_Error( 'cywater_logo_review_governance', __( 'A governance result cannot be replaced through eligibility review.', 'cywater-operations' ) );
		}
		if ( $old === $status ) {
			return true;
		}
		if ( ! CYWater_Operations_Audit::record( 'logo_entry', $entry->ID, 'eligibility_authorized', $old, $status, (int) $entry->post_author, 'logo_review' ) ) {
			return new WP_Error( 'cywater_logo_review_audit', __( 'The review was not saved because the audit log is unavailable.', 'cywater-operations' ) );
		}
		update_post_meta( $entry->ID, '_cywater_logo_status', $status );
		update_post_meta( $entry->ID, '_cywater_logo_reward_status', 'not_applicable' );
		if ( $status !== (string) get_post_meta( $entry->ID, '_cywater_logo_status', true ) ) {
			update_post_meta( $entry->ID, '_cywater_logo_status', $old );
			return new WP_Error( 'cywater_logo_review_write', __( 'The review could not be saved.', 'cywater-operations' ) );
		}
		return true;
	}

	public static function handle_finalists() {
		self::require_capability( 'cywater_select_logo_finalists' );
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		check_admin_referer( self::FINALISTS_ACTION . '_' . $event_id );
		$ids    = isset( $_POST['finalist_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['finalist_ids'] ) ) : array();
		$result = self::finalize_finalists( $event_id, $ids );
		self::redirect( 0, $event_id, true === $result ? 'finalists' : 'error' );
	}

	public static function finalize_finalists( $event_id, $ids ) {
		if ( ! current_user_can( 'cywater_select_logo_finalists' ) ) {
			return new WP_Error( 'cywater_logo_finalists_forbidden' );
		}
		$event_id = absint( $event_id );
		$ids      = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		if ( 5 !== count( $ids ) || 'results' !== CYWater_Logo_Call::current_phase( $event_id ) || self::selected_for_event( $event_id ) ) {
			return new WP_Error( 'cywater_logo_finalists_invalid' );
		}
		$ranked = array_values( array_filter( CYWater_Logo_Call::entries( $event_id ), static function ( $entry ) { return 'shortlisted' === get_post_meta( $entry->ID, '_cywater_logo_status', true ); } ) );
		usort( $ranked, static function ( $a, $b ) { return CYWater_Logo_Call::vote_count( $b->ID ) <=> CYWater_Logo_Call::vote_count( $a->ID ) ?: $a->ID <=> $b->ID; } );
		if ( count( $ranked ) < 5 ) {
			return new WP_Error( 'cywater_logo_finalists_insufficient' );
		}
		$cutoff = CYWater_Logo_Call::vote_count( $ranked[4]->ID );
		$pool   = array_map( static function ( $entry ) { return (int) $entry->ID; }, array_filter( $ranked, static function ( $entry ) use ( $cutoff ) { return CYWater_Logo_Call::vote_count( $entry->ID ) >= $cutoff; } ) );
		if ( array_diff( $ids, $pool ) ) {
			return new WP_Error( 'cywater_logo_finalists_rank' );
		}
		if ( ! CYWater_Operations_Audit::record( 'logo_event', $event_id, 'finalists_authorized', 'voting_closed', 'five_finalists', 0, 'logo_governance' ) ) {
			return new WP_Error( 'cywater_logo_finalists_audit' );
		}
		$old = array();
		foreach ( $ranked as $entry ) {
			$old[ $entry->ID ] = (string) get_post_meta( $entry->ID, '_cywater_logo_status', true );
			$is_finalist = in_array( (int) $entry->ID, $ids, true );
			update_post_meta( $entry->ID, '_cywater_logo_status', $is_finalist ? 'finalist' : 'not_selected' );
			if ( $is_finalist ) {
				$reward = CYWater_Logo_Call::grant_finalist_reward( $entry->ID );
				if ( is_wp_error( $reward ) ) {
					update_post_meta( $entry->ID, '_cywater_logo_finalist_reward_status', 'manual_required' );
					update_post_meta( $entry->ID, '_cywater_logo_finalist_reward_note', sanitize_key( $reward->get_error_code() ) );
				}
			}
		}
		if ( 5 !== count( CYWater_Logo_Call::finalists( $event_id ) ) ) {
			foreach ( $old as $id => $status ) { update_post_meta( $id, '_cywater_logo_status', $status ); }
			return new WP_Error( 'cywater_logo_finalists_write' );
		}
		return true;
	}

	public static function handle_selection() {
		self::require_capability( 'cywater_select_official_logo' );
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		check_admin_referer( self::SELECT_ACTION . '_' . $event_id );
		$result = self::select_official( $event_id, $entry_id );
		self::redirect( $entry_id, $event_id, true === $result ? 'selected' : 'error' );
	}

	public static function select_official( $event_id, $entry_id ) {
		if ( ! current_user_can( 'cywater_select_official_logo' ) ) {
			return new WP_Error( 'cywater_logo_select_forbidden' );
		}
		$finalists = CYWater_Logo_Call::finalists( $event_id );
		$ids       = wp_list_pluck( $finalists, 'ID' );
		if ( 'results' !== CYWater_Logo_Call::current_phase( $event_id ) || 5 !== count( $ids ) || ! in_array( absint( $entry_id ), array_map( 'absint', $ids ), true ) || self::selected_for_event( $event_id ) ) {
			return new WP_Error( 'cywater_logo_select_invalid' );
		}
		$entry = self::entry( $entry_id );
		if ( ! $entry || ! CYWater_Operations_Audit::record( 'logo_event', $event_id, 'board_selection_authorized', 'five_finalists', 'official_selected', (int) $entry->post_author, 'logo_governance' ) ) {
			return new WP_Error( 'cywater_logo_select_audit' );
		}
		update_post_meta( $entry_id, '_cywater_logo_status', 'selected' );
		update_post_meta( $entry_id, '_cywater_logo_rights_status', 'pending' );
		update_post_meta( $entry_id, '_cywater_logo_final_files_status', 'requested' );
		update_post_meta( $entry_id, '_cywater_logo_reward_status', 'pending' );
		return 'selected' === (string) get_post_meta( $entry_id, '_cywater_logo_status', true ) ? true : new WP_Error( 'cywater_logo_select_write' );
	}

	public static function handle_fulfillment() {
		self::require_capability( 'cywater_manage_logo_fulfillment' );
		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		check_admin_referer( self::FULFILL_ACTION . '_' . $entry_id );
		$rights = isset( $_POST['rights_status'] ) ? sanitize_key( wp_unslash( $_POST['rights_status'] ) ) : '';
		$files  = isset( $_POST['files_status'] ) ? sanitize_key( wp_unslash( $_POST['files_status'] ) ) : '';
		$reward = isset( $_POST['reward_status'] ) ? sanitize_key( wp_unslash( $_POST['reward_status'] ) ) : '';
		$result = self::fulfill( $entry_id, $rights, $files, $reward );
		self::redirect( $entry_id, absint( $_POST['event'] ?? 0 ), true === $result ? 'fulfilled' : 'error' );
	}

	public static function fulfill( $entry_id, $rights, $files, $reward ) {
		if ( ! current_user_can( 'cywater_manage_logo_fulfillment' ) ) {
			return new WP_Error( 'cywater_logo_fulfillment_forbidden' );
		}
		$entry = self::entry( $entry_id );
		if ( ! $entry || 'selected' !== get_post_meta( $entry_id, '_cywater_logo_status', true ) || ! in_array( $rights, self::RIGHTS_STATUSES, true ) || ! in_array( $files, self::FILE_STATUSES, true ) || ! in_array( $reward, self::REWARD_STATUSES, true ) ) {
			return new WP_Error( 'cywater_logo_fulfillment_invalid' );
		}
		if ( 'fulfilled' === $reward && ( 'accepted' !== $rights || 'accepted' !== $files ) ) {
			return new WP_Error( 'cywater_logo_fulfillment_gate' );
		}
		$from = get_post_meta( $entry_id, '_cywater_logo_rights_status', true ) . '-' . get_post_meta( $entry_id, '_cywater_logo_final_files_status', true ) . '-' . get_post_meta( $entry_id, '_cywater_logo_reward_status', true );
		$to   = $rights . '-' . $files . '-' . $reward;
		if ( ! CYWater_Operations_Audit::record( 'logo_entry', $entry_id, 'fulfillment_authorized', $from, $to, (int) $entry->post_author, 'logo_fulfillment' ) ) {
			return new WP_Error( 'cywater_logo_fulfillment_audit' );
		}
		update_post_meta( $entry_id, '_cywater_logo_rights_status', $rights );
		update_post_meta( $entry_id, '_cywater_logo_final_files_status', $files );
		update_post_meta( $entry_id, '_cywater_logo_reward_status', $reward );
		return $rights === (string) get_post_meta( $entry_id, '_cywater_logo_rights_status', true )
			&& $files === (string) get_post_meta( $entry_id, '_cywater_logo_final_files_status', true )
			&& $reward === (string) get_post_meta( $entry_id, '_cywater_logo_reward_status', true )
			? true
			: new WP_Error( 'cywater_logo_fulfillment_write' );
	}

	public static function handle_export() {
		self::require_capability( 'cywater_review_logo_entries' );
		$event_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
		check_admin_referer( self::EXPORT_ACTION . '_' . $event_id );
		$entries = CYWater_Logo_Call::entries( $event_id );
		if ( empty( $entries ) || ! class_exists( 'ZipArchive' ) || ! CYWater_Operations_Audit::record( 'logo_export', $event_id, 'export_authorized', 'protected', 'zip_csv', 0, 'logo_export' ) ) {
			wp_die( esc_html__( 'The protected export could not be created.', 'cywater-operations' ), '', array( 'response' => 500 ) );
		}
		$path = wp_tempnam( 'cywater-logo-export.zip' );
		$zip  = new ZipArchive();
		if ( ! $path || true !== $zip->open( $path, ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'The protected export could not be created.', 'cywater-operations' ), '', array( 'response' => 500 ) );
		}
		$zip->addFromString( 'submissions.csv', self::csv_manifest( $entries ) );
		foreach ( $entries as $entry ) {
			$file   = CYWater_Logo_Call::source_file( $entry->ID );
			$source = CYWater_Logo_Call::protected_source_path( $entry->ID );
			if ( $source ) {
				$extension = strtolower( pathinfo( (string) ( $file['original'] ?? $source ), PATHINFO_EXTENSION ) );
				$zip->addFile( $source, 'files/' . CYWater_Logo_Call::work_number( $entry->ID ) . ( $extension ? '.' . $extension : '' ) );
			}
		}
		$zip->close();
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="cywater-logo-submissions-' . gmdate( 'Ymd-His' ) . '.zip"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $path );
		exit;
	}

	private static function csv_manifest( $entries ) {
		$stream = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $stream, array( 'work_number', 'event', 'entrant_legal_name', 'account_name', 'verified_email', 'institution', 'country_or_region', 'title_or_role', 'career_stage', 'submitted_utc', 'statement', 'status', 'votes', 'original_file', 'mime_type', 'terms_version', 'terms_accepted_utc', 'participation_reward_status', 'participation_reward_end', 'finalist_reward_status', 'finalist_reward_end', 'rights_status', 'final_files_status', 'selected_design_reward_status' ) );
		foreach ( $entries as $entry ) {
			$user = get_userdata( (int) $entry->post_author );
			$file = CYWater_Logo_Call::source_file( $entry->ID );
			fputcsv( $stream, array(
				CYWater_Logo_Call::work_number( $entry->ID ), get_the_title( absint( get_post_meta( $entry->ID, '_cywater_logo_event_id', true ) ) ), get_post_meta( $entry->ID, '_cywater_logo_submitter_name', true ), $user ? $user->display_name : '', $user ? $user->user_email : '', get_user_meta( $entry->post_author, 'cyw_institution_name', true ), self::country_label( (string) get_user_meta( $entry->post_author, 'cyw_country', true ) ), get_user_meta( $entry->post_author, 'cyw_professional_title', true ), get_user_meta( $entry->post_author, 'cyw_career_stage', true ), $entry->post_date_gmt, get_post_meta( $entry->ID, '_cywater_logo_statement', true ), get_post_meta( $entry->ID, '_cywater_logo_status', true ), CYWater_Logo_Call::vote_count( $entry->ID ), $file['original'] ?? '', $file['type'] ?? '', get_post_meta( $entry->ID, '_cywater_logo_terms_version', true ), get_post_meta( $entry->ID, '_cywater_logo_terms_accepted_at', true ), get_post_meta( $entry->ID, '_cywater_logo_participation_reward_status', true ), get_post_meta( $entry->ID, '_cywater_logo_participation_reward_end', true ), get_post_meta( $entry->ID, '_cywater_logo_finalist_reward_status', true ), get_post_meta( $entry->ID, '_cywater_logo_finalist_reward_end', true ), get_post_meta( $entry->ID, '_cywater_logo_rights_status', true ), get_post_meta( $entry->ID, '_cywater_logo_final_files_status', true ), get_post_meta( $entry->ID, '_cywater_logo_reward_status', true ),
			) );
		}
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return "\xEF\xBB\xBF" . $csv;
	}

	private static function entry( $entry_id ) {
		$entry = get_post( absint( $entry_id ) );
		return $entry && self::ENTRY_TYPE === $entry->post_type && 'private' === $entry->post_status ? $entry : null;
	}

	private static function selected_entry( $entries ) {
		$requested = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( $entries as $entry ) {
			if ( $requested === (int) $entry->ID ) { return $entry; }
		}
		return $entries[0] ?? null;
	}

	private static function entry_events( $entries ) {
		$events = array();
		foreach ( $entries as $entry ) {
			$id = absint( get_post_meta( $entry->ID, '_cywater_logo_event_id', true ) );
			if ( $id ) { $events[ $id ] = get_the_title( $id ) ?: sprintf( __( 'Event #%d', 'cywater-operations' ), $id ); }
		}
		asort( $events, SORT_NATURAL | SORT_FLAG_CASE );
		return $events;
	}

	private static function selected_for_event( $event_id ) {
		foreach ( CYWater_Logo_Call::entries( $event_id ) as $entry ) {
			if ( 'selected' === get_post_meta( $entry->ID, '_cywater_logo_status', true ) ) { return $entry; }
		}
		return null;
	}

	private static function country_label( $value ) {
		if ( class_exists( 'CYWater_Membership_Countries' ) ) {
			$options = CYWater_Membership_Countries::options();
			$code    = CYWater_Membership_Countries::canonical_code( $value );
			return $options[ $code ] ?? $value;
		}
		return $value;
	}

	private static function require_capability( $capability ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You are not allowed to use this Logo Call operation.', 'cywater-operations' ), '', array( 'response' => 403 ) );
		}
	}

	private static function page_url( $entry_id = 0, $event_id = 0 ) {
		$args = array( 'page' => self::PAGE_SLUG );
		if ( $entry_id ) { $args['entry'] = absint( $entry_id ); }
		if ( $event_id ) { $args['event'] = absint( $event_id ); }
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	private static function redirect( $entry_id, $event_id, $result ) {
		wp_safe_redirect( add_query_arg( 'cywater_logo_review', sanitize_key( $result ), self::page_url( $entry_id, $event_id ) ) );
		exit;
	}
}
