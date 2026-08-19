<?php
/**
 * Dedicated, least-privilege Logo Call review workflow.
 *
 * Program Reviewers never receive native cyw_logo_entry edit primitives. A
 * narrowly scoped map_meta_cap adapter permits the dedicated review endpoint
 * and Logo Call asset handler to see edit_post only while their exact,
 * nonce-bound admin-post actions are running.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Operations_Logo_Review {
	public const PAGE_SLUG          = 'cywater-logo-reviews';
	public const ACTION             = 'cywater_operations_logo_review';
	public const NONCE_FIELD        = 'cywater_operations_logo_review_nonce';
	public const NONCE_ACTION_PREFIX = 'cywater_operations_logo_review_';

	private const ENTRY_TYPE = 'cyw_logo_entry';
	private const STATUSES   = array( 'submitted', 'shortlisted', 'not_selected', 'selected', 'withdrawn' );
	private const REWARDS    = array( 'not_applicable', 'pending', 'fulfilled' );

	public static function register() {
		if ( ! class_exists( 'CYWater_Logo_Call' ) ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'add_review_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_review' ) );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_request_scoped_capability' ), 20, 4 );
	}

	public static function add_review_page() {
		add_menu_page(
			__( 'Logo reviews', 'cywater-operations' ),
			__( 'Logo reviews', 'cywater-operations' ),
			'cywater_review_logo_entries',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_review_page' ),
			'dashicons-awards',
			26
		);
	}

	/**
	 * Map edit_post to the dedicated review capability only for an exact,
	 * nonce-bound review or protected-asset request for the same entry.
	 *
	 * @param array  $caps    Primitive capabilities selected by WordPress.
	 * @param string $cap     Requested meta capability.
	 * @param int    $user_id User ID (the primitive capability check follows).
	 * @param array  $args    Meta-capability arguments.
	 * @return array
	 */
	public static function map_request_scoped_capability( $caps, $cap, $user_id, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 'edit_post' !== $cap || empty( $args[0] ) ) {
			return $caps;
		}
		if ( get_current_user_id() !== absint( $user_id ) ) {
			return $caps;
		}

		$entry_id = absint( $args[0] );
		$entry    = get_post( $entry_id );
		if ( ! $entry || self::ENTRY_TYPE !== $entry->post_type || 'private' !== $entry->post_status ) {
			return $caps;
		}

		if ( self::is_exact_review_request( $entry_id ) || self::is_exact_asset_request( $entry_id ) ) {
			return array( 'cywater_review_logo_entries' );
		}

		return $caps;
	}

	private static function is_exact_review_request( $entry_id ) {
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		$posted = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		$nonce  = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';

		return doing_action( 'admin_post_' . self::ACTION )
			&& 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) )
			&& self::ACTION === $action
			&& $entry_id === $posted
			&& wp_verify_nonce( $nonce, self::NONCE_ACTION_PREFIX . $entry_id );
	}

	private static function is_exact_asset_request( $entry_id ) {
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$target = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0;
		$kind   = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		return doing_action( 'admin_post_cywater_logo_asset' )
			&& 'GET' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) )
			&& 'cywater_logo_asset' === $action
			&& $entry_id === $target
			&& in_array( $kind, array( 'source', 'lockup' ), true )
			&& wp_verify_nonce( $nonce, 'cywater_logo_asset_' . $entry_id . '_' . $kind );
	}

	public static function render_review_page() {
		if ( ! current_user_can( 'cywater_review_logo_entries' ) ) {
			wp_die( esc_html__( 'You are not allowed to review Logo Call submissions.', 'cywater-operations' ), '', array( 'response' => 403 ) );
		}

		$entries = get_posts(
			array(
				'post_type'      => self::ENTRY_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$selected_id = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0;
		$selected    = $selected_id ? get_post( $selected_id ) : ( $entries[0] ?? null );
		if ( $selected && ( self::ENTRY_TYPE !== $selected->post_type || 'private' !== $selected->post_status ) ) {
			$selected = null;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Logo Call reviews', 'cywater-operations' ) . '</h1>';
		if ( isset( $_GET['cywater_logo_review'] ) ) {
			$result = sanitize_key( wp_unslash( $_GET['cywater_logo_review'] ) );
			echo '<div class="notice ' . ( 'updated' === $result ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>';
			echo 'updated' === $result ? esc_html__( 'The review was saved and audited.', 'cywater-operations' ) : esc_html__( 'The review was not saved.', 'cywater-operations' );
			echo '</p></div>';
		}

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'There are no private Logo Call submissions to review.', 'cywater-operations' ) . '</p></div>';
			return;
		}

		echo '<h2>' . esc_html__( 'Submissions', 'cywater-operations' ) . '</h2><ul class="subsubsub">';
		$total = count( $entries );
		foreach ( $entries as $index => $entry ) {
			echo '<li><a href="' . esc_url( self::page_url( $entry->ID ) ) . '">' . esc_html( get_the_title( $entry ) ?: sprintf( __( 'Submission #%d', 'cywater-operations' ), $entry->ID ) ) . '</a>' . ( $index + 1 < $total ? ' | ' : '' ) . '</li>';
		}
		echo '</ul><div class="clear"></div>';

		if ( $selected ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
			echo '<input type="hidden" name="entry_id" value="' . esc_attr( $selected->ID ) . '">';
			wp_nonce_field( self::NONCE_ACTION_PREFIX . $selected->ID, self::NONCE_FIELD );
			CYWater_Logo_Call::render_entry_box( $selected );
			submit_button( __( 'Save review', 'cywater-operations' ) );
			echo '</form>';
		}
		echo '</div>';
	}

	public static function handle_review() {
		if ( ! current_user_can( 'cywater_review_logo_entries' ) ) {
			wp_die( esc_html__( 'You are not allowed to review Logo Call submissions.', 'cywater-operations' ), '', array( 'response' => 403 ) );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		check_admin_referer( self::NONCE_ACTION_PREFIX . $entry_id, self::NONCE_FIELD );
		$entry = get_post( $entry_id );
		if ( ! $entry || self::ENTRY_TYPE !== $entry->post_type || 'private' !== $entry->post_status ) {
			wp_die( esc_html__( 'The requested Logo Call submission is unavailable.', 'cywater-operations' ), '', array( 'response' => 400 ) );
		}
		if ( ! current_user_can( 'edit_post', $entry_id ) ) {
			wp_die( esc_html__( 'The Logo Call review request is not authorized.', 'cywater-operations' ), '', array( 'response' => 403 ) );
		}

		$status = isset( $_POST['cywater_logo_status'] ) ? sanitize_key( wp_unslash( $_POST['cywater_logo_status'] ) ) : '';
		$reward = isset( $_POST['cywater_logo_reward_status'] ) ? sanitize_key( wp_unslash( $_POST['cywater_logo_reward_status'] ) ) : '';
		$result = self::transition( $entry_id, $status, $reward );
		self::redirect( $entry_id, true === $result ? 'updated' : 'error' );
	}

	/**
	 * Persist one allowlisted review transition with strict pre-commit audit.
	 *
	 * The HTTP wrapper owns its exact request/nonce boundary. Keeping this core
	 * operation return-based lets WP-CLI QA exercise write, failure and cleanup
	 * paths without weakening the browser endpoint or granting native edit caps.
	 *
	 * @param int    $entry_id Logo submission post ID.
	 * @param string $status   Requested review status.
	 * @param string $reward   Requested reward-fulfillment status.
	 * @return true|WP_Error
	 */
	public static function transition( $entry_id, $status, $reward ) {
		if ( ! current_user_can( 'cywater_review_logo_entries' ) ) {
			return new WP_Error( 'cywater_logo_review_forbidden', __( 'You are not allowed to review Logo Call submissions.', 'cywater-operations' ) );
		}

		$entry_id = absint( $entry_id );
		$entry    = get_post( $entry_id );
		if ( ! $entry || self::ENTRY_TYPE !== $entry->post_type || 'private' !== $entry->post_status ) {
			return new WP_Error( 'cywater_logo_review_unavailable', __( 'The requested Logo Call submission is unavailable.', 'cywater-operations' ) );
		}

		$status = sanitize_key( $status );
		$reward = sanitize_key( $reward );
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new WP_Error( 'cywater_logo_review_status', __( 'The requested review status is not allowed.', 'cywater-operations' ) );
		}
		$reward = self::normalize_reward( $status, $reward );

		$old_status_exists = metadata_exists( 'post', $entry_id, '_cywater_logo_status' );
		$old_reward_exists = metadata_exists( 'post', $entry_id, '_cywater_logo_reward_status' );
		$old_status        = (string) get_post_meta( $entry_id, '_cywater_logo_status', true );
		$old_reward        = (string) get_post_meta( $entry_id, '_cywater_logo_reward_status', true );
		$changed           = $old_status !== $status || $old_reward !== $reward;

		if ( ! $changed ) {
			return true;
		}

		// Audit authorization is strict and pre-commit. A storage outage leaves
		// the submission untouched; a later write failure retains an immutable
		// record of the authorized attempt rather than an unaudited state change.
		if ( ! CYWater_Operations_Audit::record( 'logo_entry', $entry_id, 'review_authorized', $old_status . '-' . $old_reward, $status . '-' . $reward, (int) $entry->post_author, 'logo_review' ) ) {
			return new WP_Error( 'cywater_logo_review_audit', __( 'The review was not saved because the audit log is unavailable.', 'cywater-operations' ) );
		}

		// This dedicated workflow owns only the two allowlisted review fields. It
		// deliberately bypasses the generic post editor and never writes the entry
		// body, author, protected file metadata, Event binding or vote records.
		update_post_meta( $entry_id, '_cywater_logo_status', $status );
		update_post_meta( $entry_id, '_cywater_logo_reward_status', $reward );
		if ( $status !== (string) get_post_meta( $entry_id, '_cywater_logo_status', true ) || $reward !== (string) get_post_meta( $entry_id, '_cywater_logo_reward_status', true ) ) {
			self::restore_or_fail( $entry_id, $old_status_exists, $old_status, $old_reward_exists, $old_reward );
			CYWater_Operations_Audit::record( 'logo_entry', $entry_id, 'review_write_failed', $old_status . '-' . $old_reward, $status . '-' . $reward, (int) $entry->post_author, 'write_failed' );
			return new WP_Error( 'cywater_logo_review_write', __( 'The review could not be saved.', 'cywater-operations' ) );
		}

		return true;
	}

	private static function normalize_reward( $status, $reward ) {
		if ( 'selected' !== $status ) {
			return 'not_applicable';
		}
		return in_array( $reward, self::REWARDS, true ) && 'not_applicable' !== $reward ? $reward : 'pending';
	}

	private static function restore_or_fail( $entry_id, $status_exists, $status, $reward_exists, $reward ) {
		$status_exists ? update_post_meta( $entry_id, '_cywater_logo_status', $status ) : delete_post_meta( $entry_id, '_cywater_logo_status' );
		$reward_exists ? update_post_meta( $entry_id, '_cywater_logo_reward_status', $reward ) : delete_post_meta( $entry_id, '_cywater_logo_reward_status' );

		$status_restored = $status_exists
			? $status === (string) get_post_meta( $entry_id, '_cywater_logo_status', true )
			: ! metadata_exists( 'post', $entry_id, '_cywater_logo_status' );
		$reward_restored = $reward_exists
			? $reward === (string) get_post_meta( $entry_id, '_cywater_logo_reward_status', true )
			: ! metadata_exists( 'post', $entry_id, '_cywater_logo_reward_status' );
		if ( ! $status_restored || ! $reward_restored ) {
			wp_die( esc_html__( 'The review could not be saved or safely rolled back. Contact an Administrator before continuing.', 'cywater-operations' ), '', array( 'response' => 500 ) );
		}
	}

	private static function page_url( $entry_id ) {
		return add_query_arg( array( 'page' => self::PAGE_SLUG, 'entry' => absint( $entry_id ) ), admin_url( 'admin.php' ) );
	}

	private static function redirect( $entry_id, $result ) {
		wp_safe_redirect( add_query_arg( 'cywater_logo_review', sanitize_key( $result ), self::page_url( $entry_id ) ) );
		exit;
	}
}
