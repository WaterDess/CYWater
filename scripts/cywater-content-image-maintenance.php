<?php
/**
 * Idempotent News/Event content-image maintenance for staging or production.
 *
 * - normalizes legacy upload URLs to the current HTTPS environment;
 * - preserves specific, editorially chosen photographs as body content;
 * - adds the verified 2020 keynote photograph to the matching Event detail;
 * - does not apply any blanket Featured-image-to-body rule.
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
global $wpdb;

$find_attachment = static function ( $filename ) use ( $wpdb ) {
	$attachment_id = absint(
		$wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id ASC LIMIT 1",
				'%' . $wpdb->esc_like( $filename )
			)
		)
	);
	return $attachment_id && 'attachment' === get_post_type( $attachment_id ) ? $attachment_id : 0;
};

$insert_figure_after_opening = static function ( $post, $attachment_id, $alt, $caption ) use ( &$updated_ids ) {
	$content = (string) get_post_field( 'post_content', $post->ID, 'raw' );
	$file    = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
	if ( '' !== $file && false !== stripos( $content, wp_basename( $file ) ) ) {
		return;
	}

	$image = wp_get_attachment_image(
		$attachment_id,
		'large',
		false,
		array(
			'loading' => 'lazy',
			'alt'     => $alt,
		)
	);
	if ( ! $image ) {
		WP_CLI::error( 'The selected content attachment could not be rendered for post ' . $post->ID . '.' );
	}

	$figure  = '<figure class="is-wide">' . $image . ( $caption ? '<figcaption>' . esc_html( $caption ) . '</figcaption>' : '' ) . '</figure>';
	$updated = preg_replace( '#(<p\b[^>]*>.*?</p>)#is', '$1' . "\n\n" . $figure, $content, 1, $count );
	if ( ! is_string( $updated ) || 1 !== $count ) {
		WP_CLI::error( 'The opening paragraph could not be resolved for post ' . $post->ID . '.' );
	}

	$result = wp_update_post( array( 'ID' => $post->ID, 'post_content' => $updated ), true );
	if ( is_wp_error( $result ) ) {
		WP_CLI::error( 'Could not place the selected content image for post ' . $post->ID . ': ' . $result->get_error_message() );
	}
	$updated_ids[] = (int) $post->ID;
};

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
if ( ! $founding_story instanceof WP_Post ) {
	WP_CLI::error( 'The founding story could not be found.' );
}
$founding_image = $find_attachment( 'founding-2011.jpg' );
if ( ! $founding_image ) {
	WP_CLI::error( 'The verified 2011 founding attachment could not be found.' );
}
$insert_figure_after_opening(
	$founding_story,
	$founding_image,
	'CYWater founding gathering in 2011',
	'CYWater was initiated in December 2011, San Francisco.'
);

$tenth_event = get_page_by_path( '10th-summer', OBJECT, 'cyw_event' );
if ( ! $tenth_event instanceof WP_Post ) {
	WP_CLI::error( 'The 10th Annual Meeting Event could not be found.' );
}
$tenth_image = $find_attachment( 'meeting-2022.png' );
if ( ! $tenth_image ) {
	WP_CLI::error( 'The verified 2022 meeting attachment could not be found.' );
}
$insert_figure_after_opening(
	$tenth_event,
	$tenth_image,
	'Online participants at the 10th CYWater Annual Meeting',
	'Online participants at the 10th CYWater Annual Meeting.'
);

$event = get_page_by_path( '8th-summer', OBJECT, 'cyw_event' );
if ( ! $event instanceof WP_Post ) {
	WP_CLI::error( 'The 8th Annual Meeting Event could not be found.' );
}

$attachment_id = $find_attachment( 'summer-2020-trenberth.png' );
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
WP_CLI::success( 'Content-image maintenance completed; updated post IDs: ' . ( $updated_ids ? implode( ', ', $updated_ids ) : 'none (already current)' ) . '. No blanket Featured-image-to-body rule was applied.' );
