<?php
/**
 * Read-only acceptance checks for CYWater's Board-review policy surfaces.
 *
 * Run only through WP-CLI on the canonical staging host.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
$site_host = strtolower( (string) wp_parse_url( site_url( '/' ), PHP_URL_HOST ) );
if ( 'staging.cywater.org' !== $home_host || 'staging.cywater.org' !== $site_host || 'staging' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Refusing to run policy QA outside the exact CYWater staging environment.' );
}

if ( ! class_exists( 'CYWater_Policy_Drafts' ) || ! method_exists( 'CYWater_Policy_Drafts', 'content_for' ) ) {
	WP_CLI::error( 'CYWater policy provider is unavailable.' );
}

$checks = 0;
$assert = static function ( $condition, $message ) use ( &$checks ) {
	++$checks;
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
};

$slugs = array(
	'privacy-notice-draft',
	'terms-of-use-draft',
	'billing-cancellation-refund-policy-draft',
	'data-retention-account-closure-policy-draft',
);

$menu_items = get_posts(
	array(
		'post_type'      => 'nav_menu_item',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $slugs as $slug ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	$assert( $page instanceof WP_Post, "Missing Board-review page: {$slug}." );
	$assert( 'publish' === $page->post_status, "Board-review surface is not published: {$slug}." );
	$assert( '1' === (string) get_post_meta( $page->ID, CYWater_Policy_Drafts::META_KEY, true ), "Board-review marker is missing: {$slug}." );
	$assert( CYWater_Policy_Drafts::CONTENT_VERSION === (string) get_post_meta( $page->ID, CYWater_Policy_Drafts::VERSION_META_KEY, true ), "Policy version marker is stale: {$slug}." );
	$assert( hash_equals( hash( 'sha256', CYWater_Policy_Drafts::content_for( $slug ) ), hash( 'sha256', (string) $page->post_content ) ), "Stored policy differs from the canonical review copy: {$slug}." );
	$assert( false !== strpos( (string) $page->post_content, 'Draft for Board Review — Not approved or in effect.' ), "Board-review warning is missing: {$slug}." );

	$linked = false;
	foreach ( $menu_items as $menu_item_id ) {
		if ( (int) get_post_meta( $menu_item_id, '_menu_item_object_id', true ) === (int) $page->ID ) {
			$linked = true;
			break;
		}
	}
	$assert( ! $linked, "Board-review page is unexpectedly present in navigation: {$slug}." );

	$response = wp_remote_get(
		get_permalink( $page ),
		array(
			'timeout'     => 15,
			'redirection' => 0,
		)
	);
	$assert( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ), "Board-review URL is unavailable: {$slug}." );
	$body = (string) wp_remote_retrieve_body( $response );
	$assert( false !== strpos( $body, 'Draft for Board Review' ), "Public review warning did not render: {$slug}." );
	$assert( 1 === preg_match( '/<meta[^>]+name=["\x27]robots["\x27][^>]+content=["\x27][^"\x27]*noindex[^"\x27]*nofollow[^"\x27]*noarchive/i', $body ), "Robots exclusion did not render completely: {$slug}." );
}

$terms   = get_page_by_path( 'terms-of-use-draft', OBJECT, 'page' );
$billing = get_page_by_path( 'billing-cancellation-refund-policy-draft', OBJECT, 'page' );
$assert( false !== strpos( (string) $terms->post_content, 'All membership sales are final and non-refundable.' ), 'Terms do not contain the strict membership final-sale rule.' );
$assert( false !== strpos( (string) $billing->post_content, '<strong>All membership sales are final and non-refundable.</strong>' ), 'Billing policy does not prominently contain the strict membership final-sale rule.' );

WP_CLI::success( sprintf( 'CYWater policy QA passed %d read-only checks.', $checks ) );
