<?php
/**
 * Idempotent production setup for the accepted 2026 Logo Call Event.
 *
 * Run after activating CYWater Core and CYWater Logo Call:
 *   wp eval-file cywater-production-logo-event-setup.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

if ( 'production' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Refusing to run outside a production WordPress environment.' );
}

if ( ! post_type_exists( 'cyw_event' ) || ! post_type_exists( 'cyw_logo_entry' ) ) {
	WP_CLI::error( 'CYWater Core and CYWater Logo Call must be active first.' );
}

global $wpdb;

$retained_entries = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts}
	 WHERE post_type = 'cyw_logo_entry'
	   AND post_status NOT IN ('auto-draft', 'trash')"
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table and post type.
$retained_votes = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->usermeta}
	 WHERE meta_key LIKE '_cywater_logo_vote_%'"
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table and marker prefix.

if ( $retained_entries || $retained_votes ) {
	WP_CLI::error( 'Refusing to configure the production Logo Event while retained submissions or votes exist.' );
}

$slug    = 'logo-design-call-2026';
$event   = get_page_by_path( $slug, OBJECT, 'cyw_event' );
$content = '<p>CYWater invites registered users to submit one original association logo through September 30, 2026. A separate voting activity follows; the five highest-ranked eligible designs receive two years of Professional membership and advance to Board selection. Every valid entrant receives Student membership through December 31, 2026. This Event hosts the removable submission and voting module below.</p>';
$data    = array(
	'post_type'    => 'cyw_event',
	'post_status'  => 'publish',
	'post_name'    => $slug,
	'post_title'   => 'CYWater Logo Design Call 2026',
	'post_content' => $content,
);

if ( $event ) {
	$data['ID'] = (int) $event->ID;
	$event_id   = wp_update_post( wp_slash( $data ), true );
} else {
	$event_id = wp_insert_post( wp_slash( $data ), true );
}

if ( is_wp_error( $event_id ) ) {
	WP_CLI::error( $event_id->get_error_message() );
}

$settings = array(
	'_cywater_logo_call_enabled'         => '1',
	'_cywater_logo_call_open_at'         => '2026-08-12 00:00',
	'_cywater_logo_call_close_at'        => '2026-09-30 23:59',
	'_cywater_logo_call_vote_open'       => '',
	'_cywater_logo_call_vote_close'      => '',
	'_cywater_logo_call_submit_audience' => 'registered',
	'_cywater_logo_call_submit_levels'   => array(),
	'_cywater_logo_call_vote_audience'   => 'registered',
	'_cywater_logo_call_vote_levels'     => array(),
	'_cywater_logo_call_reward'          => 'CYWater Lifetime membership (final term subject to Board confirmation)',
	'_cyw_start_date'                    => '2026-08-12',
	'_cyw_end_date'                      => '2026-09-30',
	'_cyw_date_label'                    => 'Aug 12–Sep 30, 2026',
	'_cyw_location'                      => 'Online',
	'_cyw_format'                        => 'Logo design call',
	'_cyw_status'                        => 'upcoming',
);

foreach ( $settings as $meta_key => $value ) {
	update_post_meta( $event_id, $meta_key, $value );
}

clean_post_cache( $event_id );
flush_rewrite_rules();

WP_CLI::success( 'Accepted production Logo Call Event configured at post ID ' . (int) $event_id . '; no staging entries or votes were imported.' );
