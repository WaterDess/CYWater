<?php
/**
 * Guarded staging-only migration for the four Board-review policy drafts.
 *
 * Normal plugin setup never overwrites an existing page. This one-shot script
 * updates only the exact previously generated drafts whose content hashes are
 * known. Any editorial change, missing marker, or unexpected host aborts the
 * whole preflight before a page is changed.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
if ( 'staging.cywater.org' !== $site_host ) {
	WP_CLI::error( 'Refusing to run: this policy migration is restricted to staging.cywater.org.' );
}

if ( ! class_exists( 'CYWater_Policy_Drafts' ) || ! method_exists( 'CYWater_Policy_Drafts', 'content_for' ) ) {
	WP_CLI::error( 'CYWater policy provider is unavailable.' );
}

$expected_previous_hashes = array(
	'privacy-notice-draft'                       => 'd353caca8e1bc44b9eecbd1b09cc42fd3f9a1074a78797f91ca053f2502084c8',
	'terms-of-use-draft'                         => 'fd03d74b4410d83b74bc49bfb9dcc9c39fdac5e8baf724142df68d6b90f13f66',
	'billing-cancellation-refund-policy-draft'   => '948e07463e272364a3b943ba913f61e7fc95ac53d81a5fa2b34c52458926dc65',
	'data-retention-account-closure-policy-draft' => '5cd0001e0a343cfb9bca88a8507d25d5b070c708910a55df99a987faa119f0ea',
);

$migration_plan = array();

// Preflight every page before changing any page.
foreach ( $expected_previous_hashes as $slug => $expected_previous_hash ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page instanceof WP_Post ) {
		WP_CLI::error( "Refusing to run: missing Board-review page {$slug}." );
	}

	if ( '1' !== (string) get_post_meta( $page->ID, CYWater_Policy_Drafts::META_KEY, true ) ) {
		WP_CLI::error( "Refusing to run: {$slug} is not marked as a CYWater Board-review draft." );
	}

	$desired_content = (string) CYWater_Policy_Drafts::content_for( $slug );
	if ( '' === $desired_content ) {
		WP_CLI::error( "Refusing to run: no canonical policy content exists for {$slug}." );
	}

	$current_hash = hash( 'sha256', (string) $page->post_content );
	$desired_hash = hash( 'sha256', $desired_content );

	if ( ! hash_equals( $expected_previous_hash, $current_hash ) && ! hash_equals( $desired_hash, $current_hash ) ) {
		WP_CLI::error( "Refusing to overwrite editorial changes detected in {$slug}." );
	}

	$migration_plan[] = array(
		'id'              => (int) $page->ID,
		'slug'            => $slug,
		'desired_content' => $desired_content,
		'desired_hash'    => $desired_hash,
		'already_current' => hash_equals( $desired_hash, $current_hash ),
	);
}

$results = array();
foreach ( $migration_plan as $item ) {
	if ( ! $item['already_current'] ) {
		$updated = wp_update_post(
			array(
				'ID'           => $item['id'],
				'post_content' => $item['desired_content'],
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			WP_CLI::error( "Policy update failed for {$item['slug']}; the prior revision remains recoverable." );
		}
	}

	update_post_meta( $item['id'], CYWater_Policy_Drafts::VERSION_META_KEY, CYWater_Policy_Drafts::CONTENT_VERSION );
	$verified = get_post( $item['id'] );
	if ( ! $verified instanceof WP_Post || ! hash_equals( $item['desired_hash'], hash( 'sha256', (string) $verified->post_content ) ) ) {
		WP_CLI::error( "Post-update verification failed for {$item['slug']}." );
	}

	$results[ $item['slug'] ] = $item['already_current'] ? 'already current' : 'updated with revision';
}

WP_CLI::line( wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
WP_CLI::success( 'CYWater Board-review policy drafts passed guarded migration.' );
