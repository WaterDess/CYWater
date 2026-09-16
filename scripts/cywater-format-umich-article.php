<?php
/** Targeted, backed-up editorial block repair. Default is read-only preview. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
if ( 'cywater.org' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) { WP_CLI::error( 'Unexpected site.' ); }
$post = get_page_by_path( 'umich-phd-and-postdoctoral-opportunities-for-2027', OBJECT, 'post' );
if ( ! $post ) { WP_CLI::error( 'Article missing.' ); }
$content = $post->post_content;
$start = strpos( $content, '<!-- wp:paragraph -->' . "\n<p>For PhD applicants:</p>" );
if ( false === $start ) { WP_CLI::error( 'Expected application section missing or already repaired.' ); }
$end = strpos( $content, '<!-- /wp:list -->', $start );
if ( false === $end ) { WP_CLI::error( 'List end missing.' ); }
$end += strlen( '<!-- /wp:list -->' );
$original = substr( $content, $start, $end - $start );
preg_match_all( '#<li>(.*?)</li>#s', $original, $matches );
$items = array_values( array_filter( $matches[1], static function( $item ) {
 return '' !== trim( str_replace( "\xc2\xa0", ' ', html_entity_decode( wp_strip_all_tags( $item ), ENT_QUOTES, 'UTF-8' ) ) );
} ) );
if ( 10 !== count( $items ) || 'For Postdoc applicants:' !== $items[5] || 0 !== strpos( $items[9], 'When sending a contact request' ) ) { WP_CLI::error( 'Unexpected application list structure.' ); }
$heading = static fn( $text ) => '<!-- wp:heading {"level":3} -->' . "\n" . '<h3 class="wp-block-heading">' . $text . '</h3>' . "\n<!-- /wp:heading -->";
$list = static function( $entries ) {
 $html = "<!-- wp:list -->\n<ul class=\"wp-block-list\">";
 foreach ( $entries as $entry ) { $html .= "<!-- wp:list-item -->\n<li>" . $entry . "</li>\n<!-- /wp:list-item -->"; }
 return $html . "</ul>\n<!-- /wp:list -->";
};
$replacement = $heading( 'For PhD applicants:' ) . "\n\n" . $list( array_slice( $items, 0, 5 ) ) . "\n\n" . $heading( $items[5] ) . "\n\n" . $list( array_slice( $items, 6, 3 ) ) . "\n\n<!-- wp:paragraph -->\n<p>" . $items[9] . "</p>\n<!-- /wp:paragraph -->";
$updated = substr( $content, 0, $start ) . $replacement . substr( $content, $end );
$updated = preg_replace( '#<!-- wp:heading \{"level":1\} -->\s*<h1 class="wp-block-heading">(.*?)</h1>\s*<!-- /wp:heading -->#s', '<!-- wp:heading -->' . "\n<h2 class=\"wp-block-heading\">$1</h2>\n<!-- /wp:heading -->", $updated );
foreach ( array( 'Basic Requirements', 'Preferred Experience (not mandatory)' ) as $label ) {
 $updated = str_replace( "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . $label . "</h2>\n<!-- /wp:heading -->", $heading( $label ), $updated );
}
$words = static fn( $html ) => preg_replace( '/[\s\x{00a0}]+/u', '', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
if ( $words( $content ) !== $words( $updated ) ) { WP_CLI::error( 'Text preservation failed.' ); }
if ( '1' === getenv( 'CYWATER_APPLY_FORMAT' ) ) {
 $dir = getenv( 'CYWATER_FORMAT_BACKUP' );
 if ( ! $dir || ! is_dir( $dir ) ) { WP_CLI::error( 'Private backup directory required.' ); }
 $file = fopen( $dir . '/article-242.json', 'x' );
 if ( ! $file ) { WP_CLI::error( 'Cannot create exclusive backup.' ); }
 $json = wp_json_encode( (array) $post, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
 $size = fwrite( $file, $json ); fclose( $file );
 if ( $size !== strlen( $json ) ) { WP_CLI::error( 'Incomplete backup.' ); }
 if ( get_post_field( 'post_content', $post->ID, 'raw' ) !== $content ) { WP_CLI::error( 'Concurrent article edit detected.' ); }
 $result = wp_update_post( wp_slash( array( 'ID'=>$post->ID, 'post_content'=>$updated ) ), true );
 if ( is_wp_error( $result ) || get_post_field( 'post_content', $post->ID, 'raw' ) !== $updated ) { WP_CLI::error( 'Save verification failed.' ); }
}
WP_CLI::success( 'Application groups: 5 PhD items, 3 Postdoc items, standalone contact paragraph. Text preserved. ' . ( '1' === getenv( 'CYWATER_APPLY_FORMAT' ) ? 'Saved.' : 'Preview only.' ) );
