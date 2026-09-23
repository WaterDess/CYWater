<?php
/** Read-only release invariants; outputs hashes/counts, never applicant data. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
global $wpdb;
$out = array( 'site' => home_url(), 'environment' => wp_get_environment_type(), 'theme' => wp_get_theme()->get( 'Version' ), 'plugin' => defined( 'CYWATER_BEST_PAPER_VERSION' ) ? CYWATER_BEST_PAPER_VERSION : null );
foreach ( array( 'users', 'usermeta', 'pmpro_memberships_users', 'pmpro_membership_orders' ) as $suffix ) {
	$table = $wpdb->prefix . $suffix;
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) {
		// Primary-key order avoids unstable result order; hashed values stay private.
		$rows = $wpdb->get_results( "SELECT * FROM `$table` ORDER BY 1", ARRAY_A );
		$out[ $suffix ] = array( 'count' => count( $rows ), 'sha256' => hash( 'sha256', wp_json_encode( $rows ) ) );
	}
}
$history = array();
foreach ( get_posts( array( 'post_type' => 'cyw_award', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
	if ( (int) get_post_meta( $post->ID, '_cyw_year', true ) >= 2026 ) { continue; }
	$history[] = array( $post->to_array(), get_post_meta( $post->ID ) );
}
$out['historical_awards'] = array( 'count' => count( $history ), 'sha256' => hash( 'sha256', wp_json_encode( $history ) ) );
$out['payment_environment'] = get_option( 'pmpro_gateway_environment' );
foreach ( array( 'cyw_bp_applications', 'cyw_bp_reviews' ) as $suffix ) {
	$table = $wpdb->prefix . $suffix;
	$out[ $suffix ] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" ) : null;
}
WP_CLI::log( wp_json_encode( $out ) );
