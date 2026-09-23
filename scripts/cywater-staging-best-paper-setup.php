<?php
/** Create an unpublished 2026 staging review surface; never enable intake. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || 'staging' !== wp_get_environment_type() ) { exit( 1 ); }
if ( ! class_exists( 'CYWater_Best_Paper' ) ) { WP_CLI::error( 'Activate the Best Paper plugin first.' ); }
$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
if ( ! $administrators ) { WP_CLI::error( 'No staging administrator is available.' ); }
wp_set_current_user( (int) $administrators[0] );
$post = get_page_by_path( 'best-paper-2026-workflow-preview', OBJECT, 'cyw_award' );
if ( ! $post ) {
	$id = wp_insert_post( array(
		'post_type' => 'cyw_award', 'post_status' => 'draft', 'post_name' => 'best-paper-2026-workflow-preview',
		'post_title' => 'CYWater Young Scientist Best Paper Award 2026',
		'post_excerpt' => 'Recognizing outstanding contributions to water sciences. Application and review workflow under preparation.',
		'post_content' => '<!-- wp:paragraph --><p>The CYWater Young Scientist Best Paper Award recognizes outstanding contributions to water sciences. The 2026 call is planned for October, with applications closing in November and results announced before the December AGU meeting. Exact dates, committee appointments and ceremony arrangements are to be confirmed.</p><!-- /wp:paragraph -->',
		'comment_status' => 'closed',
	), true );
	if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
	update_post_meta( $id, '_cyw_year', '2026' );
	$result = CYWater_Best_Paper::save_config( $id, array( 'enabled' => true, 'status' => 'draft' ) );
	if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
} else { $id = $post->ID; }
$cfg = CYWater_Best_Paper::config( $id );
if ( 'draft' !== get_post_status( $id ) || 'draft' !== $cfg['status'] || $cfg['open_at'] || $cfg['close_at'] ) { WP_CLI::error( 'Existing preview changed; left untouched.' ); }
WP_CLI::log( wp_json_encode( array( 'award_id' => $id, 'status' => get_post_status( $id ), 'phase' => CYWater_Best_Paper::phase( $id ), 'preview_url' => get_preview_post_link( $id ), 'workflow_url' => admin_url( 'edit.php?post_type=cyw_award&page=cywater-best-paper&award_id=' . $id ) ) ) );
