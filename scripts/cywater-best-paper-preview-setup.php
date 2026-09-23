<?php
/** Explicitly authorized switch to a public direct-link, non-submitting preview. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'cywater.org', 'staging.cywater.org' ), true ) || ! shortcode_exists( 'cywater_best_paper_preview' ) ) { WP_CLI::error( 'Wrong site or unavailable preview renderer.' ); }
$award = get_page_by_path( 'best-paper-award-2026', OBJECT, 'cyw_award' );
if ( ! $award ) { WP_CLI::error( 'The existing 2026 Award was not found.' ); }
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
if ( ! $admins ) { WP_CLI::error( 'No administrator available.' ); }
wp_set_current_user( (int) $admins[0] );
$slug = 'best-paper-2026-preview';
$page = get_page_by_path( $slug, OBJECT, 'page' );
if ( $page ) {
	if ( 'interactive-preview-20260923' !== get_post_meta( $page->ID, '_cyw_bp_preview_marker', true ) ) { WP_CLI::error( 'A different page already uses this path; left untouched.' ); }
	if ( 'public-link' === get_post_meta( $page->ID, '_cyw_bp_preview_access', true ) || 'private' !== $page->post_status ) {
		WP_CLI::success( 'Existing preview preserved (' . $page->post_status . '): ' . get_permalink( $page->ID ) );
		return; // A preview deliberately taken offline must never be republished.
	}
}
// Publishing only this unlisted page must not trigger Core's automatic menu add.
remove_action( 'transition_post_status', '_wp_auto_add_pages_to_menu', 10 );
$id = wp_insert_post( array(
	'ID' => $page ? $page->ID : 0,
	'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug,
	'post_title' => 'Best Paper 2026 — application preview',
	'post_excerpt' => 'Explore the complete applicant form. No sign-in is required; nothing entered or selected here is saved or uploaded.',
	'post_content' => '<!-- wp:shortcode -->[cywater_best_paper_preview award_id="' . absint( $award->ID ) . '"]<!-- /wp:shortcode -->',
	'comment_status' => 'closed', 'ping_status' => 'closed',
	'meta_input' => array( '_cyw_bp_preview_marker' => 'interactive-preview-20260923', '_cyw_bp_preview_access' => 'public-link', '_cyw_eyebrow' => 'Preview' ),
), true );
if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
WP_CLI::log( wp_json_encode( array( 'page_id' => $id, 'url' => get_permalink( $id ), 'visibility' => get_post_status( $id ), 'award_id' => $award->ID, 'phase_unchanged' => CYWater_Best_Paper::phase( $award->ID ), 'can_submit' => false ) ) );
