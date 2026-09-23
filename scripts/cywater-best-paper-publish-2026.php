<?php
/** Explicitly authorized public information release. Never opens applications. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'cywater.org', 'staging.cywater.org' ), true ) || ! class_exists( 'CYWater_Best_Paper' ) ) { WP_CLI::error( 'Wrong site or inactive Best Paper module.' ); }
$storage = CYWater_Best_Paper::install();
if ( is_wp_error( $storage ) ) { WP_CLI::error( $storage->get_error_message() ); }
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
if ( ! $admins ) { WP_CLI::error( 'No administrator available.' ); }
wp_set_current_user( (int) $admins[0] );
$slug = 'best-paper-award-2026';
$post = get_page_by_path( $slug, OBJECT, 'cyw_award' );
if ( ! $post && 'staging.cywater.org' === wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	$post = get_page_by_path( 'best-paper-2026-workflow-preview', OBJECT, 'cyw_award' );
}
if ( $post ) {
	$cfg = CYWater_Best_Paper::config( $post->ID );
	if ( 'draft' !== $cfg['status'] || $cfg['open_at'] || $cfg['close_at'] || $cfg['chair_id'] || $cfg['reviewer_ids'] || CYWater_Best_Paper::applications( $post->ID ) ) { WP_CLI::error( 'Existing cycle has progressed; no changes made.' ); }
	if ( 'publish' === $post->post_status ) {
		WP_CLI::success( 'Information page already published; content preserved: ' . get_permalink( $post->ID ) );
		return;
	}
	$owned_draft = 'best-paper-2026-workflow-preview' === $post->post_name || '20260923-information-release' === get_post_meta( $post->ID, '_cyw_bp_release_marker', true );
	if ( ! $owned_draft || 'draft' !== $post->post_status ) { WP_CLI::error( 'Unexpected existing record; left untouched.' ); }
}
$id = wp_insert_post( array(
	'ID' => $post ? $post->ID : 0, 'post_type' => 'cyw_award', 'post_status' => 'draft',
	'post_name' => $slug, 'post_title' => 'CYWater Young Scientist Best Paper Award 2026',
	'post_excerpt' => 'The 2026 Best Paper Award cycle is in preparation. Read the eligibility rules, planned timeline and application requirements. Applications are not open yet.',
	'post_content' => '<!-- wp:paragraph --><p>The CYWater Young Scientist Best Paper Award recognizes outstanding contributions to water sciences by early-career researchers. The 2026 call is planned for October, with applications closing in November and results announced before the AGU meeting in December.</p><!-- /wp:paragraph -->' .
		'<!-- wp:paragraph --><p>Applications are not open yet. The confirmed opening date, deadline, Chair and review committee will be published before the call opens. The eligibility rules and preparation guidance below are available now.</p><!-- /wp:paragraph -->' .
		'<!-- wp:heading --><h2 class="wp-block-heading">Prepare your application</h2><!-- /wp:heading -->' .
		'<!-- wp:paragraph --><p>Prepare your paper PDF and CV PDF (up to 20 MB each), paper title, journal, DOI and first formal online publication date. When applications open, sign in with your CYWater account to submit through this page. A paid membership is not required. Your saved application and uploaded files will be available only to you and authorized award staff and reviewers.</p><!-- /wp:paragraph -->' .
		'<!-- wp:heading --><h2 class="wp-block-heading">How selection works</h2><!-- /wp:heading -->' .
		'<!-- wp:paragraph --><p>Staff will check applications for completeness and eligibility after the deadline. The review committee will evaluate eligible papers, submit independent scores and discuss the results before confirming the recipients. One Best Paper Award is planned; the committee will determine the number of Outstanding Paper Awards. Scores support the discussion and do not automatically determine awards.</p><!-- /wp:paragraph -->',
	'comment_status' => 'closed',
	'meta_input' => array( '_cyw_bp_release_marker' => '20260923-information-release' ),
), true );
if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
update_post_meta( $id, '_cyw_year', '2026' );
$cfg = CYWater_Best_Paper::save_config( $id, array( 'enabled' => true, 'status' => 'draft' ) );
if ( is_wp_error( $cfg ) ) { WP_CLI::error( $cfg->get_error_message() ); }
$published = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
if ( is_wp_error( $published ) ) { WP_CLI::error( $published->get_error_message() ); }
WP_CLI::log( wp_json_encode( array( 'id' => $id, 'url' => get_permalink( $id ), 'status' => get_post_status( $id ), 'phase' => CYWater_Best_Paper::phase( $id ), 'intake_open' => false ) ) );
