<?php
/** Create the native editorial category and classify the requested recruitment post. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'cywater.org', 'staging.cywater.org' ), true ) ) { WP_CLI::error( 'Unexpected site.' ); }
$post = get_page_by_path( 'umich-phd-and-postdoctoral-opportunities-for-2027', OBJECT, 'post' );
$backup = getenv( 'CYWATER_CATEGORY_BACKUP' );
if ( ! $backup || ! is_dir( $backup ) ) { WP_CLI::error( 'Private backup directory required.' ); }
if ( $post ) {
 $handle = fopen( $backup . '/article-categories.json', 'x' );
 if ( ! $handle ) { WP_CLI::error( 'Backup already exists or cannot be written.' ); }
 $data = wp_json_encode( array( 'post_id'=>$post->ID, 'categories'=>wp_get_post_categories( $post->ID ), 'post_content_sha256'=>hash( 'sha256', $post->post_content ) ) );
 $written = fwrite( $handle, $data ); fclose( $handle );
 if ( $written !== strlen( $data ) ) { WP_CLI::error( 'Incomplete backup.' ); }
}
$term = term_exists( 'opportunities', 'category' );
if ( ! $term ) { $term = wp_insert_term( 'Opportunities', 'category', array( 'slug'=>'opportunities', 'description'=>'Academic positions, jobs, fellowships and other opportunities for the water-science community.' ) ); }
if ( is_wp_error( $term ) ) { WP_CLI::error( $term->get_error_message() ); }
$term_id = (int) ( is_array( $term ) ? $term['term_id'] : $term );
if ( $post ) {
 $result = wp_set_post_categories( $post->ID, array( $term_id ), true );
 if ( is_wp_error( $result ) || ! has_category( $term_id, $post ) ) { WP_CLI::error( 'Category assignment failed.' ); }
 if ( get_post_field( 'post_content', $post->ID, 'raw' ) !== $post->post_content ) { WP_CLI::error( 'Unexpected content change.' ); }
}
WP_CLI::success( 'Opportunities category ready; requested article ' . ( $post ? 'classified with content preserved.' : 'not present in this environment.' ) );
