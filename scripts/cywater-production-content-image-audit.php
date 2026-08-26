<?php
/**
 * Read-only production inventory for News/Event listing covers and detail images.
 *
 * Run with: wp eval-file /absolute/path/to/cywater-production-content-image-audit.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
if ( 'cywater.org' !== $host ) {
	WP_CLI::error( 'Refusing to run outside cywater.org production.' );
}

/** Collect image blocks recursively without changing post content. */
$collect_image_blocks = static function ( array $blocks ) use ( &$collect_image_blocks ) {
	$images = array();
	foreach ( $blocks as $block ) {
		if ( 'core/image' === ( $block['blockName'] ?? '' ) ) {
			$images[] = array(
				'id'  => absint( $block['attrs']['id'] ?? 0 ),
				'url' => esc_url_raw( $block['attrs']['url'] ?? '' ),
			);
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$images = array_merge( $images, $collect_image_blocks( $block['innerBlocks'] ) );
		}
	}
	return $images;
};

$rows = array();
foreach ( array( 'post', 'cyw_event' ) as $post_type ) {
	$posts = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	foreach ( $posts as $post ) {
		$featured_id = get_post_thumbnail_id( $post );
		$block_images = $collect_image_blocks( parse_blocks( (string) $post->post_content ) );
		preg_match_all( '#<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>#i', (string) $post->post_content, $html_images );
		$body_urls = array_values(
			array_unique(
				array_filter(
					array_merge(
						array_column( $block_images, 'url' ),
						array_map( 'esc_url_raw', $html_images[1] ?? array() )
					)
				)
		));
		$body_ids = array_values( array_unique( array_filter( array_map( 'absint', array_column( $block_images, 'id' ) ) ) ) );
		$rows[] = array(
			'id'                => (int) $post->ID,
			'type'              => $post_type,
			'title'             => get_the_title( $post ),
			'url'               => get_permalink( $post ),
			'featured_image_id' => (int) $featured_id,
			'featured_image_url'=> $featured_id ? wp_get_attachment_image_url( $featured_id, 'full' ) : '',
			'body_image_ids'    => $body_ids,
			'body_image_urls'   => $body_urls,
			'has_body_image'    => ! empty( $body_ids ) || ! empty( $body_urls ),
		);
	}
}

$summary = array(
	'total'                       => count( $rows ),
	'featured_and_body_image'     => 0,
	'featured_without_body_image' => 0,
	'body_without_featured_image' => 0,
	'no_image'                    => 0,
);
foreach ( $rows as $row ) {
	$has_featured = ! empty( $row['featured_image_id'] );
	$has_body     = ! empty( $row['has_body_image'] );
	if ( $has_featured && $has_body ) {
		++$summary['featured_and_body_image'];
	} elseif ( $has_featured ) {
		++$summary['featured_without_body_image'];
	} elseif ( $has_body ) {
		++$summary['body_without_featured_image'];
	} else {
		++$summary['no_image'];
	}
}

WP_CLI::line( wp_json_encode( array( 'summary' => $summary, 'records' => $rows ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
WP_CLI::success( 'Read-only News/Event image inventory completed. No posts, media, metadata, or files were changed.' );
