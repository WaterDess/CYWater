<?php
/** Read-only runtime regression: wp eval-file scripts/verify-awards-yearbook.php */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
$_SERVER['SERVER_NAME'] = wp_parse_url( home_url(), PHP_URL_HOST );
$records = get_posts( array( 'post_type' => 'cyw_award', 'post_status' => 'publish', 'posts_per_page' => -1 ) );
// Deliberately retain the first record as global context, reproducing the old bug.
$GLOBALS['post'] = $records[0];
ob_start();
include get_stylesheet_directory() . '/archive-cyw_award.php';
$html = ob_get_clean();
foreach ( $records as $record ) {
	$year = get_post_meta( $record->ID, '_cyw_year', true );
	$anchor = $year ? 'award-' . $year : 'award-record-' . $record->ID;
	if ( ! preg_match( '/<article class="award-year" id="' . preg_quote( $anchor, '/' ) . '"[^>]*>(.*?)<\/article>/s', $html, $match ) ) {
		WP_CLI::error( 'Missing award record ' . $record->ID );
	}
	$recipient = get_post_meta( $record->ID, '_cyw_recipient', true );
	if ( $recipient && ! str_contains( $match[1], esc_html( $recipient ) ) ) { WP_CLI::error( 'Wrong recipient ' . $year ); }
	if ( ! str_contains( $match[1], esc_url( get_permalink( $record->ID ) ) ) ) { WP_CLI::error( 'Missing independent detail link ' . $year ); }
}
preg_match_all( '/<article class="award-year" id="([^"]+)"/', $html, $matches );
if ( count( $matches[1] ) !== count( $records ) || count( array_unique( $matches[1] ) ) !== count( $records ) ) {
	WP_CLI::error( 'Duplicate or missing yearbook entries' );
}
WP_CLI::success( count( $records ) . ' distinct award records, recipients and independent detail links verified.' );
