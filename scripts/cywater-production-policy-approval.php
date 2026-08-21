<?php
/**
 * Publish the four Board-approved initial CYWater policies.
 *
 * Run once on the accepted production target:
 *   wp eval-file cywater-production-policy-approval.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$environment = wp_get_environment_type();
if ( ! in_array( $environment, array( 'staging', 'production' ), true ) ) {
	WP_CLI::error( 'Refusing to publish policies outside staging or production.' );
}

$approved_at = '2026-08-21';
$effective   = '<p><strong>Effective date:</strong> August 21, 2026.</p>';
$policies    = array(
	'privacy-notice-draft' => array( 'privacy-notice', 'Privacy Notice' ),
	'terms-of-use-draft' => array( 'terms-of-use', 'Terms of Use' ),
	'billing-cancellation-refund-policy-draft' => array( 'billing-cancellation-refund-policy', 'Billing, Cancellation and Refund Policy' ),
	'data-retention-account-closure-policy-draft' => array( 'data-retention-account-closure-policy', 'Data Retention and Account Closure Policy' ),
);
$published = array();

foreach ( $policies as $draft_slug => $final ) {
	$final_slug  = $final[0];
	$final_title = $final[1];
	$page        = get_page_by_path( $final_slug, OBJECT, 'page' );

	if ( ! $page instanceof WP_Post ) {
		$page = get_page_by_path( $draft_slug, OBJECT, 'page' );
	}
	if ( ! $page instanceof WP_Post ) {
		WP_CLI::error( 'Missing approved policy source: ' . $draft_slug );
	}

	$content = (string) $page->post_content;
	$content = preg_replace( '#^<div class="cywater-policy-draft-notice">.*?</div>\s*#s', '', $content, 1 );
	$content = preg_replace( '#<h2>Board (?:review|decisions) before publication</h2>\s*<p><strong>Confirm:</strong>.*?</p>\s*$#s', '', $content, 1 );
	$content = trim( (string) $content );
	if ( '' === $content || str_contains( $content, 'Draft for Board Review' ) || str_contains( $content, '<strong>Confirm:</strong>' ) ) {
		WP_CLI::error( 'Policy review markers could not be removed safely: ' . $draft_slug );
	}
	if ( ! str_starts_with( $content, '<p><strong>Effective date:</strong>' ) ) {
		$content = $effective . $content;
	}

	$post_id = wp_update_post(
		wp_slash(
			array(
				'ID'           => (int) $page->ID,
				'post_status'  => 'publish',
				'post_name'    => $final_slug,
				'post_title'   => $final_title,
				'post_content' => $content,
			)
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		WP_CLI::error( $post_id->get_error_message() );
	}

	delete_post_meta( $post_id, '_cywater_board_review_policy' );
	update_post_meta( $post_id, '_cywater_policy_approved_from', $draft_slug );
	update_post_meta( $post_id, '_cywater_policy_approved_at', $approved_at );
	$published[ $final_slug ] = (int) $post_id;
}

update_option( 'wp_page_for_privacy_policy', $published['privacy-notice'] );
flush_rewrite_rules();

WP_CLI::success( 'Published four Board-approved CYWater policies effective August 21, 2026 on ' . $environment . '.' );
