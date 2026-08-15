<?php
/**
 * Idempotently create the clearly labelled Forum preview article on staging.
 */

if ( 'staging' !== wp_get_environment_type() ) {
	WP_CLI::error( 'The Forum preview fixture may run only on staging.' );
}

$existing = get_page_by_path( 'forum-staging-preview', OBJECT, 'cyw_forum_post' );
$admin_ids = get_users(
	array(
		'role'   => 'administrator',
		'fields' => 'ID',
		'number' => 1,
	)
);
if ( empty( $admin_ids ) ) {
	WP_CLI::error( 'No administrator is available to own the staging preview.' );
}

$post_id = wp_insert_post(
	array(
		'ID'             => $existing ? (int) $existing->ID : 0,
		'post_type'      => 'cyw_forum_post',
		'post_status'    => 'publish',
		'post_name'      => 'forum-staging-preview',
		'post_title'     => '[Staging Preview] Welcome to the CYWater Forum',
		'post_excerpt'   => 'A non-production article used to review the Forum card, article, author, topic, and discussion experience.',
		'post_content'   => '<p>This is a staging-only preview of the CYWater Forum publication flow. It demonstrates how a member-authored article, its topics, and the discussion beneath it fit into the existing CYWater website.</p><h2>What this preview verifies</h2><p>The entire card opens this article. Editorial categories and topics remain ordinary WordPress taxonomies, revisions remain available to editors, and replies use the core WordPress moderation and privacy tools rather than a separate comment database.</p><p>This record must be removed before production content is launched.</p>',
		'post_author'    => (int) $admin_ids[0],
		'comment_status' => 'open',
	),
	true
);
if ( is_wp_error( $post_id ) ) {
	WP_CLI::error( $post_id->get_error_message() );
}

$category = get_term_by( 'slug', 'community', 'cyw_forum_category' );
if ( $category ) {
	wp_set_object_terms( $post_id, array( (int) $category->term_id ), 'cyw_forum_category' );
}
wp_set_object_terms( $post_id, array( 'staging-preview', 'community' ), 'cyw_forum_topic' );
update_post_meta( $post_id, '_cywater_staging_fixture', 1 );

WP_CLI::success( get_permalink( $post_id ) );
