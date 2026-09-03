<?php
/**
 * Staging-only, self-cleaning account verification and closure-request QA.
 *
 * Run with: wp eval-file /path/to/cywater-staging-account-security-qa.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
if ( 'staging.cywater.org' !== $site_host ) {
	WP_CLI::error( 'Refusing to run: this account-security test is restricted to staging.cywater.org.' );
}
if ( ! class_exists( 'CYWater_Membership_Account_Security' ) ) {
	WP_CLI::error( 'CYWater Membership account security is unavailable.' );
}
if ( ! class_exists( 'CYWater_Membership_Account_Routing' ) ) {
	WP_CLI::error( 'CYWater Membership account routing is unavailable.' );
}

if ( '1' === getenv( 'CYWATER_ACCOUNT_QA_REAL_DELIVERY' ) ) {
	$delivery_username = 'cywater_delivery_qa_' . gmdate( 'YmdHis' );
	$delivery_user_id  = wp_create_user( $delivery_username, wp_generate_password( 24, true, true ), 'contact@cywater.org' );
	if ( is_wp_error( $delivery_user_id ) ) {
		WP_CLI::error( 'Could not create the disposable delivery account.' );
	}
	$delivery_sent = CYWater_Membership_Account_Security::issue_verification( $delivery_user_id, home_url( '/account/' ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $delivery_user_id );
	if ( ! $delivery_sent ) {
		WP_CLI::error( 'The real verification message was not accepted by the WordPress mail transport.' );
	}
	WP_CLI::success( 'A real verification message was handed to the configured transport; the disposable account was removed and its link is intentionally unusable.' );
	return;
}

$captured_mail = array();
$mail_filter   = static function ( $return, $atts ) use ( &$captured_mail ) {
	$captured_mail[] = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $mail_filter, 10, 2 );

$username = 'cywater_account_qa_' . gmdate( 'YmdHis' );
$email    = $username . '@example.org';
$initial_password = wp_generate_password( 32, false, false );
$changed_password = wp_generate_password( 32, false, false );
$reset_password   = wp_generate_password( 32, false, false );
$user_id  = 0;
$checks   = array();

$assert = static function ( $condition, $label ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
	$checks[] = $label;
};

$cleanup_user = static function ( $cleanup_user_id ) {
	$cleanup_user_id = absint( $cleanup_user_id );
	if ( ! $cleanup_user_id ) {
		return;
	}
	if ( function_exists( 'pmpro_changeMembershipLevel' ) ) {
		pmpro_changeMembershipLevel( 0, $cleanup_user_id );
	}
	global $wpdb;
	if ( isset( $wpdb->pmpro_memberships_users ) ) {
		$wpdb->delete( $wpdb->pmpro_memberships_users, array( 'user_id' => $cleanup_user_id ), array( '%d' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $cleanup_user_id );
};

$extract_verification = static function ( $mail ) {
	$message = (string) ( $mail['message'] ?? '' );
	if ( preg_match( '#href=["\'](https://[^"\']+/verify-email/\?[^"\']+)["\']#', $message, $match ) ) {
		$url = html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );
	} elseif ( preg_match( '#https://[^\s<]+/verify-email/\?[^\s<]+#', $message, $match ) ) {
		$url = html_entity_decode( $match[0], ENT_QUOTES, 'UTF-8' );
	} else {
		throw new RuntimeException( 'Verification link was not present in the intercepted message.' );
	}
	$query = array();
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
	return $query;
};

try {
	wp_set_current_user( 0 );
	$guest_membership_action = CYWater_Membership_Account_Routing::membership_action();
	$assert( 'Join CYWater' === (string) ( $guest_membership_action['label'] ?? '' ) && false !== strpos( (string) ( $guest_membership_action['url'] ?? '' ), '/membership/' ), 'Signed-out header action invites the visitor to join CYWater' );

	$user_id = wp_create_user( $username, $initial_password, $email );
	$assert( ! is_wp_error( $user_id ), 'Disposable Subscriber created' );
	wp_update_user( array( 'ID' => $user_id, 'role' => 'subscriber' ) );
	$assert( ! is_wp_error( wp_authenticate( $username, $initial_password ) ), 'Disposable account can authenticate with its initial password' );
	$renamed_username = $username . '_renamed';
	$renamed_user     = CYWater_Membership_Fields::update_username( $user_id, $renamed_username );
	$assert( $renamed_user instanceof WP_User && $renamed_username === $renamed_user->user_login, 'Username can be changed without replacing the account' );
	$assert( ! is_wp_error( wp_authenticate( $renamed_username, $initial_password ) ), 'Changed username authenticates with the existing password' );
	$assert( false === username_exists( $username ), 'Old username is no longer an active sign-in identifier' );
	$restored_user = CYWater_Membership_Fields::update_username( $user_id, $username );
	$assert( $restored_user instanceof WP_User && $username === $restored_user->user_login, 'Disposable username is restored for the remaining account checks' );
	$assert( ! CYWater_Membership_Account_Security::is_verified( $user_id ), 'New account starts unverified' );
	wp_set_current_user( $user_id );
	$frontend_username = $username . '_profile';
	$original_post     = $_POST;
	$_POST             = array( 'user_login' => $username . '_must_not_save' );
	$blocked_errors    = array( 'Synthetic peer-field validation failure.' );
	$blocked_user      = (object) array(
		'ID'           => $user_id,
		'display_name' => $username,
		'nickname'     => $username,
	);
	CYWater_Membership_Fields::append_username_error( $blocked_errors, true, $blocked_user );
	wp_update_user( array( 'ID' => $user_id, 'first_name' => 'Atomicity check' ) );
	$assert( $username === get_userdata( $user_id )->user_login, 'A peer-field validation error cannot leak a pending username into a later user update' );
	$_POST             = array( 'user_login' => $frontend_username );
	$frontend_errors   = array();
	$frontend_user     = (object) array(
		'ID'           => $user_id,
		'display_name' => '',
		'nickname'     => '',
	);
	CYWater_Membership_Fields::append_username_error( $frontend_errors, true, $frontend_user );
	$assert( empty( $frontend_errors ) && $frontend_username === $frontend_user->user_login && $frontend_username === $frontend_user->display_name, 'Profile validation uses username as the optional public-name fallback' );
	$frontend_update = wp_update_user( (array) $frontend_user );
	$frontend_fresh  = get_userdata( $user_id );
	$assert( ! is_wp_error( $frontend_update ) && $frontend_fresh instanceof WP_User && $frontend_username === $frontend_fresh->user_login && $frontend_username === $frontend_fresh->display_name, 'Validated profile save commits username and public-name fallback together' );
	$assert( (int) $frontend_fresh->ID === (int) $user_id && ! is_wp_error( wp_authenticate( $frontend_username, $initial_password ) ), 'Front-end username change preserves the account ID and existing password' );
	$_POST = $original_post;
	$restored_user = CYWater_Membership_Fields::update_username( $user_id, $username );
	$assert( $restored_user instanceof WP_User && $username === $restored_user->user_login, 'Profile-save username is restored for the remaining account checks' );
	wp_update_user( array( 'ID' => $user_id, 'display_name' => $username, 'nickname' => $username ) );
	$profile_fields = apply_filters(
		'pmpro_member_profile_edit_user_object_fields',
		array(
			'first_name'   => 'First Name',
			'last_name'    => 'Last Name',
			'display_name' => 'Display name publicly as',
			'user_email'   => 'Email',
		)
	);
	$assert( 'Public display name (optional)' === (string) ( $profile_fields['display_name'] ?? '' ), 'Profile distinguishes the optional public display name from the username' );
	$identity_columns = CYWater_Membership_Admin::add_user_columns(
		array(
			'username' => 'Username',
			'name'     => 'Name',
			'email'    => 'Email',
		)
	);
	$assert( 'Public display name' === (string) ( $identity_columns['name'] ?? '' ) && 'First / last name' === (string) ( $identity_columns['cywater_personal_name'] ?? '' ), 'Administrator Users table distinguishes the public display name from first and last name' );
	wp_update_user( array( 'ID' => $user_id, 'first_name' => 'Identity', 'last_name' => 'Member' ) );
	$personal_name_html = CYWater_Membership_Admin::render_user_column( '', 'cywater_personal_name', $user_id );
	$assert( false !== strpos( $personal_name_html, 'First:</strong> Identity' ) && false !== strpos( $personal_name_html, 'Last:</strong> Member' ), 'Administrator Users table reads the existing WordPress first_name and last_name metadata without copying it' );
	ob_start();
	CYWater_Membership_Fields::render_username_field( get_userdata( $user_id ) );
	$username_field_html = (string) ob_get_clean();
	$assert( false !== strpos( $username_field_html, 'name="user_login"' ) && false !== strpos( $username_field_html, 'data-cywater-profile-username-field' ), 'Profile renders one editable username field for the current account' );
	$photo_field = PMPro_Field_Group::get_field( 'cyw_profile_photo' );
	ob_start();
	$photo_field->display( '' );
	$photo_field_html = (string) ob_get_clean();
	$assert( false !== strpos( $photo_field_html, 'data-cywater-profile-file-input' ) && false !== strpos( $photo_field_html, 'cywater-profile-file__button' ), 'Profile photograph reuses the CYWater file control while retaining PMPro input ownership' );
	$inactive_membership_action = CYWater_Membership_Account_Routing::membership_action();
	$assert( 'Choose Membership' === (string) ( $inactive_membership_action['label'] ?? '' ) && false !== strpos( (string) ( $inactive_membership_action['url'] ?? '' ), '/membership/' ), 'Registered non-member header action offers membership without misidentifying the account' );
	$level_ids = (array) get_option( 'cywater_membership_level_ids', array() );
	$student_level_id = absint( $level_ids['student'] ?? 0 );
	$professional_level_id = absint( $level_ids['professional'] ?? 0 );
	$assert( $student_level_id > 0 && $professional_level_id > 0, 'Student and Professional membership levels are configured for routing QA' );
	$assert( function_exists( 'pmpro_changeMembershipLevel' ) && pmpro_changeMembershipLevel( $student_level_id, $user_id ), 'Disposable account receives a temporary Student membership for account-action QA' );
	$filtered_membership_actions = CYWater_Membership_Account_Routing::remove_self_service_membership_actions(
		array(
			'change' => '<span><a href="/membership/">Change</a></span>',
			'cancel' => '<span><a href="/membership-cancel/">Cancel</a></span>',
		),
		$student_level_id
	);
	ob_start();
	CYWater_Membership_Account_Routing::render_membership_support();
	$membership_support_html = (string) ob_get_clean();
	$assert( empty( $filtered_membership_actions ), 'Front-end Change and Cancel membership actions are removed completely' );
	$assert( false === strpos( $membership_support_html, '<details' ) && false === strpos( $membership_support_html, 'pmpro_actionlink-' ), 'Account membership support contains no hidden PMPro self-service actions' );
	$assert( false !== strpos( $membership_support_html, 'membership@cywater.org' ) && false !== strpos( $membership_support_html, 'mailto:' ), 'Active members receive the canonical Membership support address' );
	$assert( CYWater_Membership_Account_Routing::is_prohibited_level_change( $professional_level_id, $user_id ), 'An active member cannot switch to a different public membership level' );
	$assert( ! CYWater_Membership_Account_Routing::is_prohibited_level_change( $student_level_id, $user_id ), 'A same-level renewal remains distinct from a prohibited plan change' );
	$assert( pmpro_changeMembershipLevel( 0, $user_id ), 'Temporary membership is removed after account-action QA' );
	wp_set_current_user( 0 );

	$country_options = CYWater_Membership_Countries::options();
	$assert( count( $country_options ) >= 240 && 'United States' === ( $country_options['US'] ?? '' ), 'Country selector reuses PMPro complete canonical country and region data' );
	$assert( 'US' === CYWater_Membership_Countries::canonical_code( 'United States' ) && '' === CYWater_Membership_Countries::canonical_code( 'Typo Country' ), 'Country validation accepts canonical choices and rejects free-text typos' );
	$owner_identity = (object) array( 'ID' => $user_id, 'user_email' => 'web@cywater.org' );
	$assert( CYWater_Membership_Fields::is_platform_owner_account( $owner_identity ), 'The association-owned web account is recognized as the non-personal platform Owner account' );
	$original_post = $_POST;
	$_POST = array(
		'first_name'             => '',
		'last_name'              => '',
		'cyw_institution_name'   => 'CYWater platform operations',
		'cyw_country'            => 'US',
		'cyw_institution_type'   => 'other',
		'cyw_professional_title' => 'Platform owner',
		'cyw_career_stage'       => 'other',
	);
	$owner_profile_errors = array();
	CYWater_Membership_Countries::validate_frontend_profile( $owner_profile_errors, true, $owner_identity );
	$_POST = $original_post;
	$assert( empty( $owner_profile_errors ), 'First and last name validation exempts only the non-personal platform Owner account' );
	$register_html = CYWater_Membership_Account_Flow::registration_form();
	$registration_page = get_page_by_path( 'member-register' );
	$assert( $registration_page instanceof WP_Post, 'Canonical member registration page exists' );
	$registration_content = $registration_page instanceof WP_Post ? (string) $registration_page->post_content : '';
	$assert( 1 === substr_count( $registration_content, '[cywater_member_register]' ), 'Canonical member registration page renders the shared form exactly once' );
	$assert( false === has_shortcode( $registration_content, 'pmpro_login' ) && false === has_shortcode( $registration_content, 'pmpro_checkout' ), 'Canonical member registration page contains no legacy or parallel account form' );
	$assert( ! (bool) get_option( 'users_can_register' ), 'WordPress core public registration remains disabled in favor of the managed CYWater form' );
	foreach ( array( 'first_name', 'last_name', 'cyw_institution_name', 'cyw_country', 'cyw_institution_type', 'cyw_professional_title', 'cyw_career_stage' ) as $required_name ) {
		$assert( false !== strpos( $register_html, 'name="' . $required_name . '"' ), 'Registration renders required core field: ' . $required_name );
	}
	$assert( false !== strpos( $register_html, '<select class="select" id="cywater-register-country"' ), 'Registration country is a single-select control ready for searchable enhancement' );
	$public_login = CYWater_Membership_Account_Routing::login_url( home_url( '/account/' ) );
	$assert( false !== strpos( $public_login, '/member-login/' ) && false !== strpos( $public_login, 'redirect_to=' ), 'Public sign-in uses the managed member page and preserves a same-site return target' );
	$external_login = CYWater_Membership_Account_Routing::login_url( 'https://example.org/not-cywater' );
	$assert( false === strpos( $external_login, 'example.org' ), 'Public sign-in discards an off-site return target' );
	$registration_url = CYWater_Membership_Account_Routing::registration_url( home_url( '/membership/' ) );
	$assert( false !== strpos( $registration_url, '/member-register/' ) && false !== strpos( $registration_url, 'redirect_to=' ), 'Registration uses the managed member page and preserves the selected continuation' );
	$raw_logout      = wp_nonce_url( site_url( 'wp-login.php?action=logout', 'login' ), 'log-out' );
	$frontend_logout = CYWater_Membership_Account_Routing::filter_frontend_logout_url( $raw_logout, '' );
	$assert( false !== strpos( $frontend_logout, '_wpnonce=' ) && false !== strpos( $frontend_logout, 'redirect_to=' ) && false !== strpos( urldecode( $frontend_logout ), '/member-login/?loggedout=true' ), 'Front-end sign-out preserves the WordPress nonce and returns to the managed member page with a success notice' );
	$explicit_logout = CYWater_Membership_Account_Routing::filter_frontend_logout_url( $raw_logout, home_url( '/membership/' ) );
	$assert( $raw_logout === $explicit_logout, 'An explicit caller-owned sign-out destination is preserved' );
	$admin_login = CYWater_Membership_Account_Routing::filter_admin_login_url( $public_login, admin_url( '/' ) );
	$assert( false !== strpos( $admin_login, '/wp-login.php' ) && false === strpos( $admin_login, '/member-login/' ), 'An explicit WordPress administration request keeps the native staff login surface' );
	$frontend_login = CYWater_Membership_Account_Routing::filter_admin_login_url( $public_login, home_url( '/account/' ) );
	$assert( $public_login === $frontend_login, 'An ordinary member destination remains on the managed public sign-in surface' );
	$cache_hook = has_action( 'template_redirect', array( 'CYWater_Membership_Account_Routing', 'prevent_identity_page_cache' ) );
	$assert( false !== $cache_hook && 0 === $cache_hook, 'Member identity pages are excluded from full-page caching before other account redirects run' );

	$original_user_id = get_current_user_id();
	$original_post    = $_POST;
	try {
		wp_update_user( array( 'ID' => $user_id, 'first_name' => 'Original', 'last_name' => 'Member' ) );
		$original_profile = array(
			'cyw_institution_name'   => 'Original institution',
			'cyw_country'            => 'US',
			'cyw_institution_type'   => 'university',
			'cyw_professional_title' => 'Original role',
			'cyw_career_stage'       => 'professional',
		);
		foreach ( $original_profile as $key => $value ) { update_user_meta( $user_id, $key, $value ); }
		wp_set_current_user( $user_id );
		CYWater_Membership_Countries::capture_profile_snapshot( $user_id );
		foreach ( $original_profile as $key => $value ) { update_user_meta( $user_id, $key, 'invalid-new-value' ); }
		$_POST = array(
			'first_name'             => 'Original',
			'last_name'              => 'Member',
			'cyw_institution_name'   => 'New institution',
			'cyw_country'            => 'Typo Country',
			'cyw_institution_type'   => 'university',
			'cyw_professional_title' => 'New role',
			'cyw_career_stage'       => 'professional',
		);
		$profile_errors = array();
		$profile_user   = (object) array( 'ID' => $user_id );
		CYWater_Membership_Countries::validate_frontend_profile( $profile_errors, true, $profile_user );
		$restored_profile = array();
		foreach ( array_keys( $original_profile ) as $key ) { $restored_profile[ $key ] = (string) get_user_meta( $user_id, $key, true ); }
		$assert( ! empty( $profile_errors ) && $original_profile === $restored_profile, 'Invalid profile country fails closed and restores the last complete professional profile' );
	} finally {
		$_POST = $original_post;
		wp_set_current_user( $original_user_id );
	}

	$sent = CYWater_Membership_Account_Security::issue_verification( $user_id, home_url( '/membership-checkout/' ) );
	$assert( $sent && 1 === count( $captured_mail ), 'Verification mail handed to WordPress mail transport' );
	$headers = (array) ( $captured_mail[0]['headers'] ?? array() );
	$assert( in_array( 'From: CYWater Accounts <accounts@cywater.org>', $headers, true ), 'Verification sender is accounts@cywater.org' );
	$assert( in_array( 'Reply-To: CYWater Membership <membership@cywater.org>', $headers, true ), 'Verification replies route to membership@cywater.org' );
	$assert( in_array( 'Content-Type: text/html; charset=UTF-8', $headers, true ), 'Verification uses the official HTML transaction template' );
	$assert( false !== strpos( (string) $captured_mail[0]['message'], 'International Association of Contemporary Young Scholars in Water Sciences' ), 'Verification identifies the full association name' );
	$assert( false !== strpos( (string) $captured_mail[0]['message'], 'Verify email address' ), 'Verification includes a clear primary action' );

	$query = $extract_verification( $captured_mail[0] );
	$assert( (int) ( $query['user_id'] ?? 0 ) === (int) $user_id && ! empty( $query['token'] ), 'Verification link identifies the disposable account without exposing a stored token' );
	$assert( CYWater_Membership_Account_Security::verify_token( $user_id, $query['token'] ), 'One-time verification token accepted' );
	$assert( CYWater_Membership_Account_Security::is_verified( $user_id ), 'Verified email matches the current account email' );
	$assert( ! CYWater_Membership_Account_Security::verify_token( $user_id, $query['token'] ), 'Verification token replay rejected' );
	$level_ids      = (array) get_option( 'cywater_membership_level_ids', array() );
	$professional_id = absint( $level_ids['professional'] ?? 0 );
	$assert( $professional_id > 0 && pmpro_changeMembershipLevel( $professional_id, $user_id ), 'Disposable account received a temporary active individual membership' );
	wp_set_current_user( $user_id );
	$active_membership_action = CYWater_Membership_Account_Routing::membership_action();
	$assert( 'My Membership' === (string) ( $active_membership_action['label'] ?? '' ) && false !== strpos( (string) ( $active_membership_action['url'] ?? '' ), '/account/#pmpro_account-membership' ), 'Active individual member header action links directly to My Membership' );
	update_user_meta( $user_id, 'cyw_institution_name', 'CYWater directory verification QA' );
	update_user_meta( $user_id, 'cyw_profile_public', 1 );
	update_user_meta( $user_id, 'cyw_public_fields', array( 'cyw_institution_name' ) );
	$assert( false !== strpos( CYWater_Membership_Privacy::directory_shortcode(), 'CYWater directory verification QA' ), 'Verified active member can appear in the opt-in directory' );

	wp_update_user( array( 'ID' => $user_id, 'user_email' => $username . '@example.net' ) );
	$assert( ! CYWater_Membership_Account_Security::is_verified( $user_id ), 'Email change invalidates prior verification' );
	$assert( 0 === absint( get_user_meta( $user_id, 'cyw_profile_public', true ) ), 'Email change disables public-directory opt-in' );
	update_user_meta( $user_id, 'cyw_profile_public', 1 );
	$assert( false === strpos( CYWater_Membership_Privacy::directory_shortcode(), 'CYWater directory verification QA' ), 'Unverified active account stays out of the directory even if public metadata is forced' );
	$assert( count( $captured_mail ) >= 2, 'Email change issues a fresh verification message' );
	$new_query = $extract_verification( $captured_mail[1] );
	$assert( CYWater_Membership_Account_Security::verify_token( $user_id, $new_query['token'] ), 'Replacement address can be verified' );
	$assert( false !== strpos( CYWater_Membership_Privacy::directory_shortcode(), 'CYWater directory verification QA' ), 'Directory eligibility resumes only after the replacement email is verified' );
	$assert( CYWater_Membership_Account_Security::issue_verification( $user_id, home_url( '/account/' ) ), 'Verification hourly allowance accepts message three' );
	$assert( CYWater_Membership_Account_Security::issue_verification( $user_id, home_url( '/account/' ) ), 'Verification hourly allowance accepts message four' );
	$assert( CYWater_Membership_Account_Security::issue_verification( $user_id, home_url( '/account/' ) ), 'Verification hourly allowance accepts message five' );
	$assert( CYWater_Membership_Account_Security::verification_rate_limited( $user_id ), 'Verification messages are limited to five per hour' );
	$assert( ! CYWater_Membership_Account_Security::issue_verification( $user_id, home_url( '/account/' ) ), 'Verification message six is rejected' );

	$original_password_post = $_POST;
	$original_password_user = get_current_user_id();
	wp_set_current_user( $user_id );
	try {
		$_POST = array(
			'action'                     => 'change-password',
			'user_id'                    => $user_id,
			'change_password_user_nonce' => wp_create_nonce( 'change-password-user_' . $user_id ),
			'password_current'           => $initial_password,
			'pass1'                      => 'short',
			'pass2'                      => 'short',
		);
		$assert( false === CYWater_Membership_Account_Security::enforce_frontend_password_policy(), 'Front-end password change rejects fewer than 12 characters' );
		$assert( ! is_wp_error( wp_authenticate( $username, $initial_password ) ), 'Rejected short password leaves the current password unchanged' );

		$_POST['password_current'] = 'incorrect-current-password';
		$_POST['pass1']            = $changed_password;
		$_POST['pass2']            = $changed_password;
		pmpro_change_password_process();
		$assert( ! is_wp_error( wp_authenticate( $username, $initial_password ) ) && is_wp_error( wp_authenticate( $username, $changed_password ) ), 'PMPro change form rejects an incorrect current password' );

		$_POST['password_current'] = $initial_password;
		pmpro_change_password_process();
		$assert( is_wp_error( wp_authenticate( $username, $initial_password ) ), 'Successful front-end password change immediately rejects the old password' );
		$assert( ! is_wp_error( wp_authenticate( $username, $changed_password ) ), 'Successful front-end password change authenticates with the new password' );
	} finally {
		$_POST = $original_password_post;
		wp_set_current_user( $original_password_user );
	}
	$mail_count_before_reset = count( $captured_mail );
	$reset_request           = retrieve_password( $username );
	$assert( ! is_wp_error( $reset_request ) && count( $captured_mail ) === $mail_count_before_reset + 1, 'Lost-password request hands one reset message to WordPress mail transport' );
	$reset_user = get_userdata( $user_id );
	$reset_key  = get_password_reset_key( $reset_user );
	$assert( ! is_wp_error( $reset_key ) && get_class( check_password_reset_key( $reset_key, $username ) ) === 'WP_User', 'Password-reset key is accepted for the intended account' );
	$assert( is_wp_error( check_password_reset_key( $reset_key . 'tampered', $username ) ), 'Tampered password-reset key is rejected' );
	$reset_errors = new WP_Error();
	$_POST['pass1'] = 'short';
	CYWater_Membership_Account_Security::validate_password_reset( $reset_errors, $reset_user );
	unset( $_POST['pass1'] );
	$assert( $reset_errors->has_errors(), 'Lost-password reset rejects fewer than 12 characters' );
	reset_password( $reset_user, $reset_password );
	$assert( is_wp_error( check_password_reset_key( $reset_key, $username ) ), 'Password-reset key becomes unusable after reset' );
	$assert( is_wp_error( wp_authenticate( $username, $changed_password ) ), 'Reset immediately rejects the previous password' );
	$assert( ! is_wp_error( wp_authenticate( $username, $reset_password ) ), 'Reset password authenticates successfully' );

	$assert( CYWater_Membership_Account_Security::request_closure( $user_id ), 'Account closure request recorded' );
	$assert( CYWater_Membership_Account_Security::closure_requested_at( $user_id ) > 0, 'Administrator-visible closure timestamp exists' );
	$assert( 'cooling_off' === CYWater_Membership_Account_Security::closure_status( $user_id ), 'Closure request enters the seven-day cooling-off period' );
	$assert( CYWater_Membership_Account_Security::closure_deadline( $user_id ) > time() + ( 6 * DAY_IN_SECONDS ), 'Closure review date is seven days after the request' );
	$assert( 'closure_requested' === CYWater_Membership_Account_Security::checkout_block_reason( $user_id ), 'Open closure request blocks new membership checkout' );
	update_user_meta( $user_id, 'cyw_account_closure_requested_at', time() - ( 8 * DAY_IN_SECONDS ) );
	$assert( 'review_due' === CYWater_Membership_Account_Security::closure_status( $user_id ), 'Expired cooling-off period enters administrator review' );
	CYWater_Membership_Account_Security::withdraw_closure( $user_id );
	$assert( 0 === CYWater_Membership_Account_Security::closure_requested_at( $user_id ), 'Closure request can be withdrawn' );
	$assert( '' === CYWater_Membership_Account_Security::checkout_block_reason( $user_id ), 'Withdrawing closure restores checkout eligibility' );

	CYWater_Membership_Account_Security::record_last_login( $username, get_user_by( 'id', $user_id ) );
	$assert( CYWater_Membership_Account_Security::last_login_at( $user_id ) > 0, 'Last sign-in timestamp recorded' );

	$sessions = WP_Session_Tokens::get_instance( $user_id );
	$sessions->create( time() + HOUR_IN_SECONDS );
	$sessions->create( time() + HOUR_IN_SECONDS );
	$assert( count( $sessions->get_all() ) >= 2, 'Disposable account has multiple test sessions' );
	$assert( CYWater_Membership_Account_Security::revoke_all_sessions( $user_id ), 'Administrator session revocation succeeds' );
	$assert( 0 === count( $sessions->get_all() ), 'All disposable-account sessions are revoked' );

	update_user_meta( $user_id, 'cyw_institution_name', 'CYWater QA' );
	update_user_meta( $user_id, 'cyw_profile_public', 1 );
	update_user_meta( $user_id, 'cyw_public_fields', array( 'cyw_institution_name' ) );
	$export = CYWater_Membership_Privacy::export_personal_data( get_userdata( $user_id )->user_email, 1 );
	$assert( ! empty( $export['data'][0]['data'] ), 'WordPress privacy export includes CYWater member data' );
	$erase = CYWater_Membership_Privacy::erase_personal_data( get_userdata( $user_id )->user_email, 1 );
	$assert( ! empty( $erase['items_removed'] ) && ! empty( $erase['items_retained'] ), 'Privacy eraser removes custom profile data while retaining reviewed records' );
	$assert( '' === get_user_meta( $user_id, 'cyw_institution_name', true ), 'Privacy eraser removes the professional profile field' );

	$administrators = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$assert( ! empty( $administrators ), 'Administrator exists for record rendering' );
	wp_set_current_user( $administrators[0]->ID );
	ob_start();
	CYWater_Membership_Admin::render_user_record( get_user_by( 'id', $user_id ) );
	$record_html = ob_get_clean();
	$assert( false !== strpos( $record_html, 'Email ownership' ), 'Administrator record renders verification state' );
	$assert( false !== strpos( $record_html, 'Active membership' ), 'Administrator record renders membership summary' );
	$assert( false !== strpos( $record_html, 'Event registrations' ) && false !== strpos( $record_html, 'Account closure' ), 'Administrator record links event operations and closure review' );
	$columns = CYWater_Membership_Admin::add_user_columns( array( 'username' => 'Username' ) );
	$assert( isset( $columns['cywater_account'], $columns['cywater_membership'] ), 'Users list exposes account and membership columns' );
} catch ( Throwable $error ) {
	if ( $user_id && ! is_wp_error( $user_id ) ) {
		$cleanup_user( $user_id );
	}
	remove_filter( 'pre_wp_mail', $mail_filter, 10 );
	WP_CLI::error( 'Account-security QA failed: ' . $error->getMessage() );
}

$cleanup_user( $user_id );
remove_filter( 'pre_wp_mail', $mail_filter, 10 );
WP_CLI::success( sprintf( 'Account-security QA passed %d checks; intercepted mail was not sent and the disposable user was removed.', count( $checks ) ) );
