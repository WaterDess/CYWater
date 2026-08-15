<?php
/**
 * Staging-only, self-cleaning partnership workflow QA.
 *
 * Run with: wp eval-file scripts/cywater-staging-partner-qa.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
	WP_CLI::log( 'PASS: ' . $message );
};

$assert( class_exists( 'CYWater_Partnerships' ), 'CYWater Partnerships plugin is active' );
$guide = get_page_by_path( 'become-a-partner' );
$assert( $guide instanceof WP_Post && 'publish' === $guide->post_status, 'Partner guide page is published' );
$assert( has_shortcode( $guide->post_content, 'cywater_partner_application' ), 'Partner guide page contains the application shortcode' );
$post_type_object = get_post_type_object( CYWater_Partnerships::POST_TYPE );
$assert( $post_type_object && 'manage_options' === $post_type_object->cap->edit_posts, 'Only Administrators can review partner applications' );
$assert( get_role( 'administrator' )->has_cap( 'manage_options' ) && ! get_role( 'editor' )->has_cap( 'manage_options' ), 'Administrator and Editor partnership-review boundary is enforced' );

$levels  = function_exists( 'pmpro_getAllLevels' ) ? pmpro_getAllLevels( true, true ) : array();
$partner = null;
foreach ( $levels as $level ) {
	if ( 'partner' === sanitize_key( $level->name ) ) {
		$partner = $level;
		break;
	}
}
$assert( $partner && empty( $partner->allow_signups ), 'Legacy Partner PMPro signup is disabled without deleting its history' );

$result = CYWater_Partnerships::create_application(
	array(
		'organization'      => 'CYWater Staging Partner QA',
		'organization_type' => 'research-institute',
		'website'           => 'https://example.org/',
		'country'           => 'United States',
		'contact_name'      => 'Staging QA',
		'contact_email'     => 'partner-qa@example.org',
		'interests'         => 'Self-cleaning staging acceptance test.',
		'consent'           => true,
	),
	false
);
$assert( ! is_wp_error( $result ), 'Partner application can be created without a member account or payment' );
$post_id = absint( $result['id'] ?? 0 );
$token   = (string) ( $result['token'] ?? '' );

try {
	$assert( 'submitted' === get_post_meta( $post_id, '_cyw_partner_stage', true ), 'New application starts in Submitted stage' );
	$stored_hash = get_post_meta( $post_id, '_cyw_partner_access_hash', true );
	$assert( $stored_hash && $token && $stored_hash !== $token, 'Application access token is stored only as a salted hash' );

	$_GET['partner_application'] = $post_id;
	$_GET['access_key']          = $token;
	$submitted_html              = do_shortcode( '[cywater_partner_application]' );
	$assert( false !== strpos( $submitted_html, 'No payment is due' ), 'Submitted status explicitly requests no payment' );
	$assert( false === strpos( $submitted_html, 'Pay approved partnership contribution' ), 'Payment action is hidden before approval' );

	update_post_meta( $post_id, '_cyw_partner_stage', 'approved' );
	update_post_meta( $post_id, '_cyw_partner_payment_url', 'https://example.org/approved-payment' );
	$approved_html = do_shortcode( '[cywater_partner_application]' );
	$assert( false !== strpos( $approved_html, 'Pay approved partnership contribution' ), 'Payment action appears after Board/MOU approval' );
	$assert( false !== strpos( $approved_html, 'https://example.org/approved-payment' ), 'Approved application exposes only its configured payment URL' );

	$_GET['access_key'] = 'invalid';
	$invalid_html       = do_shortcode( '[cywater_partner_application]' );
	$assert( false !== strpos( $invalid_html, 'invalid or has been replaced' ), 'Invalid application access token is rejected' );
} finally {
	unset( $_GET['partner_application'], $_GET['access_key'] );
	wp_delete_post( $post_id, true );
}

$assert( ! get_post( $post_id ), 'Temporary partner application was removed' );
WP_CLI::success( 'CYWater staging partnership QA passed.' );
