<?php
/**
 * Self-cleaning staging QA for public publishing visibility.
 *
 * Run with:
 * wp eval-file scripts/cywater-staging-publishing-qa.php
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
$site_host = strtolower( (string) wp_parse_url( site_url( '/' ), PHP_URL_HOST ) );
if ( 'staging.cywater.org' !== $home_host || 'staging.cywater.org' !== $site_host || 'staging' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Refusing to run: this QA is restricted to staging.cywater.org.' );
}

$marker     = 'CYWATER_PUBLISHING_QA_' . gmdate( 'Ymd_His' ) . '_' . wp_generate_password( 8, false, false );
$created    = array();
$assertions = 0;
$failures   = array();

$assert = static function ( $condition, $message ) use ( &$assertions, &$failures ) {
	++$assertions;
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$fetch = static function ( $url ) {
	$last = null;
	for ( $attempt = 0; $attempt < 3; ++$attempt ) {
		$last = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'redirection' => 3,
				'headers'    => array( 'Cache-Control' => 'no-cache' ),
				'user-agent' => 'CYWater staging publishing QA',
			)
		);
		if ( ! is_wp_error( $last ) && 200 === wp_remote_retrieve_response_code( $last ) ) {
			break;
		}
		usleep( 250000 );
	}
	return $last;
};

try {
	$news_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => $marker . '_NEWS',
			'post_content' => '<p>' . esc_html( $marker ) . '</p>',
		),
		true
	);
	$assert( ! is_wp_error( $news_id ), 'Could not create the temporary News post.' );
	if ( ! is_wp_error( $news_id ) ) {
		$created[] = (int) $news_id;
		$assert( ! metadata_exists( 'post', $news_id, '_cyw_news_order' ), 'Temporary News unexpectedly has a migration order.' );
		$assert( ! metadata_exists( 'post', $news_id, '_cyw_source_id' ), 'Temporary News unexpectedly has a migration source ID.' );
	}

	$event_id = wp_insert_post(
		array(
			'post_type'    => 'cyw_event',
			'post_status'  => 'publish',
			'post_title'   => $marker . '_EVENT',
			'post_content' => '<p>' . esc_html( $marker ) . '</p>',
		),
		true
	);
	$assert( ! is_wp_error( $event_id ), 'Could not create the temporary Event.' );
	if ( ! is_wp_error( $event_id ) ) {
		$created[] = (int) $event_id;
		$assert( ! metadata_exists( 'post', $event_id, '_cyw_start_date' ), 'Temporary Event unexpectedly has a start date.' );
	}

	$award_id = wp_insert_post(
		array(
			'post_type'   => 'cyw_award',
			'post_status' => 'publish',
			'post_title'  => $marker . '_AWARD',
		),
		true
	);
	$assert( ! is_wp_error( $award_id ), 'Could not create the temporary Award.' );
	if ( ! is_wp_error( $award_id ) ) {
		$created[] = (int) $award_id;
		update_post_meta( $award_id, '_cyw_award_record', wp_json_encode( array( 'note' => $marker . '_AWARD' ) ) );
		$assert( ! metadata_exists( 'post', $award_id, '_cyw_year' ), 'Temporary Award unexpectedly has a year.' );
	}

	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $marker . '_PAGE',
			'post_name'    => strtolower( str_replace( '_', '-', $marker ) ),
			'post_content' => '<p>' . esc_html( $marker . '_PAGE' ) . '</p>',
		),
		true
	);
	$assert( ! is_wp_error( $page_id ), 'Could not create the temporary Page.' );
	if ( ! is_wp_error( $page_id ) ) {
		$created[] = (int) $page_id;
	}

	$targets = array(
		'News archive'  => array( home_url( '/news/' ), $marker . '_NEWS' ),
		'Events archive' => array( home_url( '/events/' ), $marker . '_EVENT' ),
		'Awards archive' => array( home_url( '/awards/' ), $marker . '_AWARD' ),
	);
	if ( ! is_wp_error( $page_id ) ) {
		$targets['Page permalink'] = array( get_permalink( $page_id ), $marker . '_PAGE' );
	}

	foreach ( $targets as $label => $target ) {
		$url      = add_query_arg( 'cywater_publishing_qa', rawurlencode( $marker ), $target[0] );
		$response = $fetch( $url );
		$assert( ! is_wp_error( $response ), $label . ' request failed.' );
		if ( ! is_wp_error( $response ) ) {
			$assert( 200 === wp_remote_retrieve_response_code( $response ), $label . ' did not return HTTP 200.' );
			$assert( false !== strpos( wp_remote_retrieve_body( $response ), $target[1] ), $label . ' omitted its published temporary record.' );
		}
	}
} finally {
	foreach ( array_reverse( $created ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}

	$remaining = get_posts(
		array(
			'post_type'      => array( 'post', 'page', 'cyw_event', 'cyw_award' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			's'              => $marker,
			'fields'         => 'ids',
		)
	);
	$assert( array() === $remaining, 'Temporary publishing QA records were not completely removed.' );
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		WP_CLI::warning( $failure );
	}
	WP_CLI::error( sprintf( 'Publishing QA failed: %d/%d assertions failed; cleanup completed.', count( $failures ), $assertions ) );
}

WP_CLI::success( sprintf( 'Publishing QA passed %d assertions; all temporary records were removed.', $assertions ) );
