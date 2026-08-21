<?php
/**
 * Staging-only, self-cleaning partnership workflow QA.
 *
 * Run with: wp eval-file scripts/cywater-staging-partner-qa.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
$site_host = strtolower( (string) wp_parse_url( site_url( '/' ), PHP_URL_HOST ) );
if ( 'staging.cywater.org' !== $home_host || 'staging.cywater.org' !== $site_host || 'staging' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Refusing to run partnership QA outside the exact CYWater staging environment.' );
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
	WP_CLI::log( 'PASS: ' . $message );
};

$assert( class_exists( 'CYWater_Partnerships' ), 'CYWater Partnerships plugin is active' );
$assert( defined( 'CYWATER_PARTNERSHIPS_VERSION' ) && '0.1.5' === CYWATER_PARTNERSHIPS_VERSION, 'CYWater Partnerships 0.1.5 is active' );
$guide = get_page_by_path( 'become-a-partner' );
$assert( $guide instanceof WP_Post && 'publish' === $guide->post_status, 'Partner guide page is published' );
$assert( has_shortcode( $guide->post_content, 'cywater_partner_application' ), 'Partner guide page contains the application shortcode' );
$post_type_object = get_post_type_object( CYWater_Partnerships::POST_TYPE );
$assert( $post_type_object && CYWater_Partnerships::CAP_REVIEW === $post_type_object->cap->edit_posts, 'Partner review uses its dedicated capability' );
$assert( $post_type_object && CYWater_Partnerships::CAP_DELETE === $post_type_object->cap->delete_posts, 'Partner deletion uses its separate Administrator-only capability' );
$administrator = get_role( 'administrator' );
$editor        = get_role( 'editor' );
$assert( $administrator && $administrator->has_cap( CYWater_Partnerships::CAP_REVIEW ) && $administrator->has_cap( CYWater_Partnerships::CAP_APPROVE ) && $administrator->has_cap( CYWater_Partnerships::CAP_PAYMENT ) && $administrator->has_cap( CYWater_Partnerships::CAP_DELETE ), 'Administrator retains recovery access to every partnership workflow capability' );
$assert( $editor && ! $editor->has_cap( CYWater_Partnerships::CAP_REVIEW ) && ! $editor->has_cap( CYWater_Partnerships::CAP_APPROVE ) && ! $editor->has_cap( CYWater_Partnerships::CAP_PAYMENT ) && ! $editor->has_cap( CYWater_Partnerships::CAP_DELETE ), 'Built-in Editor receives no partnership workflow or deletion capability' );

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
$intercepted_mail = array();
$mail_interceptor = static function ( $return, $atts ) use ( &$intercepted_mail ) {
	$intercepted_mail[] = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $mail_interceptor, 999, 2 );

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
	$valid_payment_urls = array(
		'https://buy.stripe.com/test_qa_handoff',
		'https://invoice.stripe.com/i/acct_12345678/test_1234567890123456?s=em',
		'https://checkout.stripe.com/c/pay/cs_test_1234567890123456#1234567890123456',
	);
	foreach ( $valid_payment_urls as $valid_payment_url ) {
		update_post_meta( $post_id, '_cyw_partner_payment_url', $valid_payment_url );
		$approved_html = do_shortcode( '[cywater_partner_application]' );
		$assert( false !== strpos( $approved_html, 'Pay approved partnership contribution' ), 'A documented Stripe hosted-payment URL is accepted: ' . $valid_payment_url );
	}

	$invalid_payment_urls = array(
		'https://example.org/approved-payment',
		'http://buy.stripe.com/test_qa_handoff',
		'https://user@buy.stripe.com/test_qa_handoff',
		'https://buy.stripe.com:443/test_qa_handoff',
		'https://buy.stripe.com.evil.example/test_qa_handoff',
		'https://evil.example/?next=https://buy.stripe.com/test_qa_handoff',
		'https://buy.stripe.com/redirect/test_qa_handoff',
		'https://buy.stripe.com/test_qa_handoff?redirect=https://evil.example/',
		'https://invoice.stripe.com/i/acct_12345678/test_1234567890123456?next=evil',
		'https://checkout.stripe.com/c/pay/cs_test_1234567890123456',
	);
	foreach ( $invalid_payment_urls as $invalid_payment_url ) {
		update_post_meta( $post_id, '_cyw_partner_payment_url', $invalid_payment_url );
		$untrusted_html = do_shortcode( '[cywater_partner_application]' );
		$assert( false === strpos( $untrusted_html, 'Pay approved partnership contribution' ), 'An unsafe or non-Stripe payment URL is rejected: ' . $invalid_payment_url );
	}

	update_post_meta( $post_id, '_cyw_partner_payment_url', $valid_payment_urls[0] );

	$_GET['access_key'] = 'invalid';
	$invalid_html       = do_shortcode( '[cywater_partner_application]' );
	$assert( false !== strpos( $invalid_html, 'invalid or has been replaced' ), 'Invalid application access token is rejected' );

	$_GET['access_key'] = $token;
	$trashed             = wp_trash_post( $post_id );
	$assert( $trashed instanceof WP_Post && ! get_post_meta( $post_id, '_cyw_partner_access_hash', true ) && ! get_post_meta( $post_id, '_cyw_partner_payment_url', true ), 'Trashing an application revokes its bearer token and payment handoff' );
	$trashed_html = do_shortcode( '[cywater_partner_application]' );
	$assert( false !== strpos( $trashed_html, 'invalid or has been replaced' ), 'A trashed application cannot be opened with its former token' );
	wp_untrash_post( $post_id );
	$assert( 'private' === get_post_status( $post_id ), 'Restoring an application returns it to the private workflow' );
	$restored_html = do_shortcode( '[cywater_partner_application]' );
	$assert( false !== strpos( $restored_html, 'invalid or has been replaced' ), 'Restoring an application does not revive its former bearer token' );
	$new_hash = (string) get_post_meta( $post_id, '_cyw_partner_access_hash', true );
	$assert( $new_hash && $new_hash !== $stored_hash, 'Restoring an application stores a fresh bearer-token hash' );
	$assert( ! get_post_meta( $post_id, '_cyw_partner_payment_url', true ), 'Restoring an application does not revive its former payment handoff' );
	$restore_mail = end( $intercepted_mail );
	$restore_body = is_array( $restore_mail ) ? (string) ( $restore_mail['message'] ?? '' ) : '';
	$new_token    = preg_match( '/access_key=([a-f0-9]{48})/', $restore_body, $token_match ) ? $token_match[1] : '';
	$assert( $new_token && $new_token !== $token, 'Restore issues a fresh bearer token only through the intercepted status message' );
	$_GET['access_key'] = $new_token;
	$fresh_html         = do_shortcode( '[cywater_partner_application]' );
	$assert( false === strpos( $fresh_html, 'invalid or has been replaced' ), 'The newly issued restore token opens the active private application' );
	$assert( false === strpos( $fresh_html, 'Pay approved partnership contribution' ), 'The newly issued restore token cannot expose the revoked payment handoff' );
} finally {
	remove_filter( 'pre_wp_mail', $mail_interceptor, 999 );
	unset( $_GET['partner_application'], $_GET['access_key'] );
	wp_delete_post( $post_id, true );
}

$assert( ! get_post( $post_id ), 'Temporary partner application was removed' );
WP_CLI::success( 'CYWater staging partnership QA passed.' );
