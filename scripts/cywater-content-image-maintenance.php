<?php
/**
 * Idempotent News/Event content-image maintenance for staging or production.
 *
 * - normalizes legacy upload URLs to the current HTTPS environment;
 * - removes the legacy founding-story body copy of its listing cover;
 * - adds the verified 2020 keynote photograph to the matching Event detail;
 * - never copies a listing cover into the detail body.
 *
 * Run with: wp eval-file /absolute/path/to/cywater-content-image-maintenance.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
if ( ! in_array( $host, array( 'cywater.org', 'staging.cywater.org' ), true ) ) {
	WP_CLI::error( 'Refusing to run outside CYWater staging or production.' );
}

$uploads = wp_upload_dir();
if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) ) {
	WP_CLI::error( 'The current upload URL could not be resolved.' );
}
$upload_base = untrailingslashit( set_url_scheme( $uploads['baseurl'], 'https' ) );
$updated_ids = array();

$posts = get_posts(
	array(
		'post_type'      => array( 'post', 'cyw_event' ),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $posts as $post_id ) {
	$content    = (string) get_post_field( 'post_content', $post_id, 'raw' );
	$normalized = preg_replace(
		'#https?://(?:staging\.)?cywater\.org/wp-content/uploads#i',
		$upload_base,
		$content
	);
	if ( is_string( $normalized ) && $normalized !== $content ) {
		$result = wp_update_post( array( 'ID' => $post_id, 'post_content' => $normalized ), true );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( 'Could not normalize image URLs for post ' . $post_id . ': ' . $result->get_error_message() );
		}
		$updated_ids[] = (int) $post_id;
	}
}

$founding_story = get_page_by_path( 'founding-story', OBJECT, 'post' );
if ( $founding_story instanceof WP_Post && has_post_thumbnail( $founding_story ) ) {
	$featured_file = get_attached_file( get_post_thumbnail_id( $founding_story ) );
	if ( $featured_file && 'founding-2011.jpg' === strtolower( wp_basename( $featured_file ) ) ) {
		$founding_content = (string) get_post_field( 'post_content', $founding_story->ID, 'raw' );
		$deduplicated     = preg_replace(
			'#<figure\b[^>]*>(?:(?!</figure>).)*founding-2011(?:-\d+x\d+)?\.jpg(?:(?!</figure>).)*</figure>#is',
			'',
			$founding_content,
			1
		);
		if ( is_string( $deduplicated ) && $deduplicated !== $founding_content ) {
			$result = wp_update_post(
				array(
					'ID'           => $founding_story->ID,
					'post_content' => $deduplicated,
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( 'Could not remove the duplicate founding-story cover: ' . $result->get_error_message() );
			}
			$updated_ids[] = (int) $founding_story->ID;
		}
	}
}

$event = get_page_by_path( '8th-summer', OBJECT, 'cyw_event' );
if ( ! $event instanceof WP_Post ) {
	WP_CLI::error( 'The 8th Annual Meeting Event could not be found.' );
}

global $wpdb;
$attachment_id = absint(
	$wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id ASC LIMIT 1",
			'%summer-2020-trenberth.png'
		)
	)
);
if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
	WP_CLI::error( 'The verified 2020 keynote attachment could not be found.' );
}

$event_content = (string) get_post_field( 'post_content', $event->ID, 'raw' );
if ( false === strpos( $event_content, 'summer-2020-trenberth.png' ) ) {
	$image_html = wp_get_attachment_image(
		$attachment_id,
		'large',
		false,
		array(
			'loading' => 'lazy',
			'alt'     => 'Invited keynote speaker Prof. Kevin Trenberth presenting at the 8th CYWater Annual Meeting.',
		)
	);
	if ( ! $image_html ) {
		WP_CLI::error( 'The verified keynote attachment could not be rendered.' );
	}
	$detail_figure = '<figure class="is-wide">' . $image_html . '<figcaption>Invited keynote speaker Prof. Kevin Trenberth presenting during the 2020 online meeting.</figcaption></figure>';
	$result = wp_update_post(
		array(
			'ID'           => $event->ID,
			'post_content' => rtrim( $event_content ) . "\n\n" . $detail_figure,
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		WP_CLI::error( 'Could not add the verified Event detail image: ' . $result->get_error_message() );
	}
	$updated_ids[] = (int) $event->ID;
}

$updated_ids = array_values( array_unique( $updated_ids ) );
WP_CLI::success( 'Content-image maintenance completed; updated post IDs: ' . ( $updated_ids ? implode( ', ', $updated_ids ) : 'none (already current)' ) . '. No featured images were copied into detail content.' );
