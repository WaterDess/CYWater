<?php
/** Read-only verification of the unlisted public preview and isolated renderer. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
$page = get_page_by_path( 'best-paper-2026-preview', OBJECT, 'page' );
$award = get_page_by_path( 'best-paper-award-2026', OBJECT, 'cyw_award' );
if ( ! $page || ! $award || 'publish' !== $page->post_status || 'publish' !== $award->post_status ) { WP_CLI::error( 'Expected published preview and existing published Award.' ); }
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
if ( ! $admins ) { WP_CLI::error( 'Administrator unavailable.' ); }
wp_set_current_user( (int) $admins[0] );
$user = wp_get_current_user();
$config_before = CYWater_Best_Paper::config( $award->ID );
$applications_before = CYWater_Best_Paper::applications( $award->ID );
$meta_before = get_user_meta( $user->ID );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $page->ID, 'post_status' => 'publish' ) );
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$GLOBALS['wp_query']->the_post();
CYWater_Best_Paper_Public::protect_personal_response();
$html = apply_filters( 'the_content', $page->post_content );
$dom = new DOMDocument();
$previous = libxml_use_internal_errors( true );
$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
libxml_clear_errors(); libxml_use_internal_errors( $previous );
$xpath = new DOMXPath( $dom );
$checks = array(
	'public_unlisted_page' => 'publish' === get_post_status( $page->ID ),
	'one_module' => 1 === $xpath->query( '//section[contains(@class,"cywater-best-paper")]' )->length,
	'no_form' => 0 === $xpath->query( '//form' )->length,
	'no_nonce_or_action' => 0 === $xpath->query( '//input[@name="action" or @name="award_id" or @name="cywater_best_paper_nonce"]' )->length,
	'editable_fields' => 0 === $xpath->query( '//fieldset[@disabled] | //input[@disabled]' )->length,
	'file_pickers' => 2 === $xpath->query( '//input[@type="file"]' )->length,
	'submit_disabled' => 1 === $xpath->query( '//button[@type="button" and @disabled]' )->length,
	'no_saved_files' => ! str_contains( $html, 'Your submitted files' ),
	'uncached' => defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE,
);
foreach ( array( 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->user_email, 'institution' => get_user_meta( $user->ID, 'cyw_institution_name', true ) ) as $name => $value ) {
	$input = $xpath->query( '//input[@name="' . $name . '"]' )->item( 0 );
	$checks[ $name . '_matches_account' ] = $input && $input->getAttribute( 'value' ) === sanitize_text_field( (string) $value );
}
$checks['profile_unchanged'] = $meta_before === get_user_meta( $user->ID );
$checks['cycle_unchanged'] = $config_before === CYWater_Best_Paper::config( $award->ID );
$checks['applications_unchanged'] = $applications_before === CYWater_Best_Paper::applications( $award->ID );
$nav_items = get_posts( array( 'post_type' => 'nav_menu_item', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_menu_item_object_id', 'meta_value' => $page->ID ) );
$checks['no_navigation_item'] = ! $nav_items;
wp_set_current_user( 0 );
$anonymous_cache_bypassed = false;
$cache_observer = static function ( $reason ) use ( &$anonymous_cache_bypassed ) {
	if ( 'CYWater Best Paper application state' === $reason ) { $anonymous_cache_bypassed = true; }
};
add_action( 'litespeed_control_set_nocache', $cache_observer );
CYWater_Best_Paper_Public::protect_personal_response();
remove_action( 'litespeed_control_set_nocache', $cache_observer );
$anonymous_html = apply_filters( 'the_content', $page->post_content );
$anonymous_dom = new DOMDocument();
$previous = libxml_use_internal_errors( true );
$anonymous_dom->loadHTML( '<?xml encoding="UTF-8">' . $anonymous_html );
libxml_clear_errors(); libxml_use_internal_errors( $previous );
$anonymous_xpath = new DOMXPath( $anonymous_dom );
$checks['anonymous_module'] = 1 === $anonymous_xpath->query( '//section[contains(@class,"cywater-best-paper")]' )->length;
$checks['anonymous_direct_renderer'] = str_contains( CYWater_Best_Paper_Public::render( $award->ID, true ), 'Applicant information' );
$checks['anonymous_uncached'] = $anonymous_cache_bypassed && defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE;
$checks['anonymous_no_form_or_action'] = 0 === $anonymous_xpath->query( '//form | //input[@name="action" or @name="award_id" or @name="cywater_best_paper_nonce"]' )->length;
$checks['anonymous_editable_fields'] = 0 === $anonymous_xpath->query( '//fieldset[@disabled] | //input[@disabled]' )->length && 2 === $anonymous_xpath->query( '//input[@type="file"]' )->length;
$checks['anonymous_submit_disabled'] = 1 === $anonymous_xpath->query( '//button[@type="button" and @disabled]' )->length && 0 === $anonymous_xpath->query( '//button[@type="submit"]' )->length;
$checks['anonymous_no_saved_files'] = ! str_contains( $anonymous_html, 'Your submitted files' ) && ! str_contains( $anonymous_html, 'cywater_best_paper_download' );
foreach ( array( 'first_name', 'last_name', 'email', 'institution', 'dob' ) as $name ) {
	$input = $anonymous_xpath->query( '//input[@name="' . $name . '"]' )->item( 0 );
	$checks[ 'anonymous_' . $name . '_blank' ] = $input && '' === $input->getAttribute( 'value' );
}
wp_set_current_user( (int) $admins[0] );
$checks['preview_modes_preserve_profile'] = $meta_before === get_user_meta( $user->ID );
$checks['preview_modes_preserve_cycle'] = $config_before === CYWater_Best_Paper::config( $award->ID );
$checks['preview_modes_preserve_applications'] = $applications_before === CYWater_Best_Paper::applications( $award->ID );
// Output only boolean verification results, never rendered account values.
WP_CLI::log( wp_json_encode( array( 'site' => home_url(), 'page_id' => $page->ID, 'checks' => $checks ) ) );
if ( in_array( false, $checks, true ) ) { WP_CLI::error( 'Unlisted public preview verification failed.' ); }
