<?php
/** One-time editorial selection, not a runtime cover-to-body rule. Default: review only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
$host = wp_parse_url( home_url(), PHP_URL_HOST );
if ( ! in_array( $host, array( 'cywater.org', 'staging.cywater.org' ), true ) ) { WP_CLI::error( 'Unexpected site.' ); }
$selections = array(
 'post' => array(
  '13th-annual-3rd'=>'meeting-2025.jpg', '12th-summer-xian'=>'meeting-2024.jpg',
  '11th-summer-beijing'=>'meeting-2023.jpg', '10th-summer-2022'=>'meeting-2022.png',
  '9th-summer-2021'=>'meeting-2021.jpg', 'bpa-2020-result'=>'2020-cover.jpg',
 ),
 'cyw_event' => array(
  '13th-annual'=>'meeting-2025.jpg', '12th-summer'=>'meeting-2024.jpg',
  '11th-summer'=>'meeting-2023.jpg', '9th-summer'=>'meeting-2021.jpg',
  '7th-summer'=>'meeting-2019.jpg', '6th-summer'=>'meeting-2018.jpg',
  'annual-gathering-2017'=>'2017-dinner.png', '5th-summer'=>'meeting-2017.jpg',
  '4th-summer'=>'meeting-2016.jpg', '3rd-summer'=>'meeting-2015.jpg',
  '2nd-summer'=>'meeting-2014.jpg', '1st-summer'=>'meeting-2013.jpg',
 ),
);
$apply = '1' === getenv( 'CYWATER_APPLY_PHOTOS' );
$backup_dir = getenv( 'CYWATER_PHOTO_BACKUP' );
if ( $apply && ( ! $backup_dir || ! is_dir( $backup_dir ) || ! is_writable( $backup_dir ) ) ) { WP_CLI::error( 'Writable private backup directory required.' ); }
$plans = array();
foreach ( $selections as $type => $entries ) {
 foreach ( $entries as $slug => $filename ) {
  $post = get_page_by_path( $slug, OBJECT, $type );
  if ( ! $post || 'publish' !== $post->post_status ) { WP_CLI::error( 'Missing published record: ' . $slug ); }
  $body = $post->post_content;
  if ( preg_match( '/<img\b/i', $body ) ) { WP_CLI::log( 'Already illustrated: ' . $slug ); continue; }
  $id = get_post_thumbnail_id( $post );
  if ( ! $id || basename( get_post_meta( $id, '_wp_attached_file', true ) ) !== $filename || ! is_file( get_attached_file( $id ) ) ) { WP_CLI::error( 'Selected photograph mismatch: ' . $slug ); }
  $src = wp_get_attachment_image_url( $id, 'large' );
  $image = '<!-- wp:image ' . wp_json_encode( array( 'id'=>$id, 'sizeSlug'=>'large', 'linkDestination'=>'none' ) ) . ' -->' . "\n";
  $image .= '<figure class="wp-block-image size-large"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( wp_strip_all_tags( $post->post_title ) ) . '" class="wp-image-' . $id . '"/></figure>' . "\n<!-- /wp:image -->";
  // Existing archive stories use classic HTML. Preserve every original byte
  // and put the editable image after the first complete opening paragraph.
  if ( ! preg_match( '#</p>(?:\s*<!-- /wp:paragraph -->)?#i', $body, $match, PREG_OFFSET_CAPTURE ) ) { WP_CLI::error( 'Opening paragraph missing: ' . $slug ); }
  $offset = $match[0][1] + strlen( $match[0][0] );
  $updated = substr( $body, 0, $offset ) . "\n\n" . $image . "\n\n" . substr( $body, $offset );
  $plans[] = array( 'post'=>$post, 'updated'=>$updated, 'image'=>$id );
  WP_CLI::log( 'Selected: ' . $slug . ' / ' . $filename );
 }
}
foreach ( $plans as $plan ) {
 if ( ! $apply ) { continue; }
 $post = $plan['post'];
 $backup = $backup_dir . '/post-' . $post->ID . '.json';
 $handle = fopen( $backup, 'x' );
 if ( ! $handle ) { WP_CLI::error( 'Cannot create exclusive backup.' ); }
 $json = wp_json_encode( array( 'host'=>$host, 'post'=>(array)$post, 'thumbnail_id'=>$plan['image'] ), JSON_PRETTY_PRINT );
 $written = fwrite( $handle, $json ); fclose( $handle ); chmod( $backup, 0600 );
 if ( strlen( $json ) !== $written ) { WP_CLI::error( 'Incomplete backup.' ); }
 if ( get_post_field( 'post_content', $post->ID, 'raw' ) !== $post->post_content ) { WP_CLI::error( 'Content changed during review.' ); }
 $result = wp_update_post( wp_slash( array( 'ID'=>$post->ID, 'post_content'=>$plan['updated'] ) ), true );
 if ( is_wp_error( $result ) || get_post_field( 'post_content', $post->ID, 'raw' ) !== $plan['updated'] || get_post_thumbnail_id( $post->ID ) !== $plan['image'] ) { WP_CLI::error( 'Readback failed: ' . $post->ID ); }
}
WP_CLI::success( ( $apply ? 'Saved and verified ' : 'Reviewed ' ) . count( $plans ) . ' editorial image insertions.' );
