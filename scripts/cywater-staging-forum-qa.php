<?php
/**
 * Staging-only, self-cleaning runtime QA for CYWater Forum 0.6.3.
 *
 * Run with:
 *   wp eval-file /absolute/path/to/cywater-staging-forum-qa.php
 *
 * This script deliberately uses the real WordPress REST controllers for member
 * posting, comment submission, moderation, and negative permission checks. It
 * creates no payment order and intercepts every email before transport.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$forum_qa_url_is_exact_staging = static function ( $url ) {
	$parts = wp_parse_url( (string) $url );
	return is_array( $parts )
		&& 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
		&& 'staging.cywater.org' === strtolower( (string) ( $parts['host'] ?? '' ) );
};

if ( ! $forum_qa_url_is_exact_staging( home_url( '/' ) ) || ! $forum_qa_url_is_exact_staging( site_url( '/' ) ) ) {
	WP_CLI::error( 'Refusing to run: home_url and site_url must both be the exact HTTPS staging host.' );
}
if ( 'staging' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Refusing to run: WP_ENVIRONMENT_TYPE is not staging.' );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

if ( ! is_plugin_active( 'cywater-forum/cywater-forum.php' ) ) {
	WP_CLI::error( 'Refusing to run: CYWater Forum is not active.' );
}

$forum_plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/cywater-forum/cywater-forum.php', false, false );
if ( '0.6.3' !== (string) ( $forum_plugin_data['Version'] ?? '' ) || ! defined( 'CYWATER_FORUM_VERSION' ) || '0.6.3' !== CYWATER_FORUM_VERSION ) {
	WP_CLI::error( 'Refusing to run: this QA is pinned to CYWater Forum 0.6.3.' );
}

$forum_qa_required_classes = array(
	'CYWater_Forum_Content',
	'CYWater_Forum_Roles',
	'CYWater_Forum_Endorsement',
	'CYWater_Forum_Comments',
	'CYWater_Forum_Settings',
	'CYWater_Forum_Covers',
	'CYWater_Forum_Workspace',
	'CYWater_Forum_Community',
	'CYWater_Membership_Account_Security',
	'CYWater_Operations_Roles',
	'CYWater_Operations_Audit',
	'CYWater_Logo_Call_Eligibility',
);
foreach ( $forum_qa_required_classes as $forum_qa_class ) {
	if ( ! class_exists( $forum_qa_class ) ) {
		WP_CLI::error( 'Refusing to run: a required forum dependency is unavailable.' );
	}
}
if ( ! function_exists( 'pmpro_changeMembershipLevel' ) || ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
	WP_CLI::error( 'Refusing to run: the real PMPro membership API is unavailable.' );
}

$forum_qa_policy = array(
	'admin_override',
	'membership_required',
	'comments_enabled',
	'comments_require_membership',
);
foreach ( $forum_qa_policy as $forum_qa_policy_key ) {
	if ( ! CYWater_Forum_Settings::is_enabled( $forum_qa_policy_key ) ) {
		WP_CLI::error( 'Refusing to run: the deployed forum policy does not match this acceptance matrix.' );
	}
}
if ( CYWater_Forum_Settings::is_enabled( 'endorsements_enabled' ) ) {
	WP_CLI::error( 'Refusing to run: the invitation/endorsement workflow is not paused.' );
}
if ( 'auto' !== CYWater_Forum_Settings::get_string( 'comments_moderation_mode' ) ) {
	WP_CLI::error( 'Refusing to run: eligible member replies are not configured for immediate publication.' );
}
if ( false !== has_action( 'template_redirect', array( 'CYWater_Forum_Endorsement', 'process_request' ) ) ) {
	WP_CLI::error( 'Refusing to run: the paused endorsement request handler is still registered.' );
}
if ( ! shortcode_exists( 'cywater_forum_endorsement' ) ) {
	WP_CLI::error( 'Refusing to run: the historical endorsement page has no paused-state renderer.' );
}

$forum_qa_templates = array(
	'archive-cyw_forum_post.php',
	'single-cyw_forum_post.php',
	'page-forum-workspace.php',
	'forum-member.php',
	'forum-activity.php',
	'comments.php',
);
foreach ( $forum_qa_templates as $forum_qa_template ) {
	if ( ! locate_template( $forum_qa_template, false, false ) ) {
		WP_CLI::error( 'Refusing to run: an active-theme forum template is missing.' );
	}
}

$forum_qa_role_requirements = array(
	CYWater_Operations_Roles::COMMUNITY_MODERATOR => array(
		'edit_others_cyw_forum_posts',
		'publish_cyw_forum_posts',
		'delete_others_cyw_forum_posts',
		'moderate_comments',
	),
	CYWater_Operations_Roles::CONTENT_EDITOR => array( 'read' ),
);
foreach ( $forum_qa_role_requirements as $forum_qa_role_slug => $forum_qa_caps ) {
	$forum_qa_role = get_role( $forum_qa_role_slug );
	if ( ! $forum_qa_role instanceof WP_Role ) {
		WP_CLI::error( 'Refusing to run: an operations role required by forum QA is missing.' );
	}
	foreach ( $forum_qa_caps as $forum_qa_cap ) {
		if ( empty( $forum_qa_role->capabilities[ $forum_qa_cap ] ) ) {
			WP_CLI::error( 'Refusing to run: an operations role is missing a required forum capability.' );
		}
	}
}

global $wpdb;
$forum_qa_level_id = (int) $wpdb->get_var(
	"SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE allow_signups = 1 ORDER BY id ASC LIMIT 1"
);
if ( $forum_qa_level_id < 1 ) {
	WP_CLI::error( 'Refusing to run: no signup-enabled PMPro membership level is available.' );
}

$forum_qa_admin_ids = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ids',
	)
);
$forum_qa_admin_id = absint( $forum_qa_admin_ids[0] ?? 0 );
if ( ! $forum_qa_admin_id || ! user_can( $forum_qa_admin_id, 'manage_options' ) ) {
	WP_CLI::error( 'Refusing to run: no recovery Administrator is available.' );
}

$forum_qa_original_user_id = get_current_user_id();
$forum_qa_users            = array();
$forum_qa_posts            = array();
$forum_qa_comments         = array();
$forum_qa_temp_files       = array();
$forum_qa_protected_files  = array();
$forum_qa_failures         = array();
$forum_qa_cleanup_failures = array();
$forum_qa_passes           = 0;
$forum_qa_intercepted_mail = array();
$forum_qa_audit_reason     = 'cywater_forum_qa_' . strtolower( wp_generate_password( 8, false, false ) );

CYWater_Operations_Audit::set_context( $forum_qa_audit_reason );

$forum_qa_assert = static function ( $condition, $message ) use ( &$forum_qa_failures, &$forum_qa_passes ) {
	if ( $condition ) {
		++$forum_qa_passes;
		return true;
	}
	$forum_qa_failures[] = (string) $message;
	return false;
};

$forum_qa_mail_filter = static function ( $short_circuit, $attributes ) use ( &$forum_qa_intercepted_mail ) {
	$forum_qa_intercepted_mail[] = is_array( $attributes ) ? $attributes : array();
	return true;
};
add_filter( 'pre_wp_mail', $forum_qa_mail_filter, 999, 2 );

$forum_qa_rest = static function ( $user_id, $method, $route, $params = array() ) {
	$prior_user_id = get_current_user_id();
	wp_set_current_user( absint( $user_id ) );
	$nonce   = wp_create_nonce( 'wp_rest' );
	$method  = strtoupper( (string) $method );
	$request = new WP_REST_Request( $method, (string) $route );
	$request->set_header( 'X-WP-Nonce', $nonce );
	if ( 'GET' === $method ) {
		$request->set_query_params( (array) $params );
	} else {
		$request->set_body_params( (array) $params );
	}
	$response = rest_do_request( $request );
	wp_set_current_user( $prior_user_id );
	return $response;
};

$forum_qa_rest_status = static function ( $response ) {
	return $response instanceof WP_REST_Response ? (int) $response->get_status() : 500;
};

$forum_qa_rest_data = static function ( $response ) {
	return $response instanceof WP_REST_Response && is_array( $response->get_data() ) ? $response->get_data() : array();
};

$forum_qa_rest_diagnostic = static function ( $response ) {
	$status  = $response instanceof WP_REST_Response ? (int) $response->get_status() : 500;
	$data    = $response instanceof WP_REST_Response && is_array( $response->get_data() ) ? $response->get_data() : array();
	$code    = sanitize_key( (string) ( $data['code'] ?? 'none' ) );
	$message = sanitize_text_field( (string) ( $data['message'] ?? 'No REST error message was returned.' ) );
	return sprintf( 'HTTP %1$d; code=%2$s; message=%3$s', $status, $code ?: 'none', $message );
};

$forum_qa_create_user = static function ( $suffix, $role ) use ( &$forum_qa_users ) {
	$token = strtolower( wp_generate_password( 8, false, false ) );
	$id    = wp_insert_user(
		array(
			'user_login'   => 'cyw_forum_qa_' . sanitize_key( $suffix ) . '_' . $token,
			'user_pass'    => wp_generate_password( 32, true, true ),
			'user_email'   => 'cyw-forum-qa-' . sanitize_key( $suffix ) . '-' . $token . '@example.com',
			'display_name' => 'CYWater Forum QA ' . ucfirst( (string) $suffix ),
			'role'         => (string) $role,
		)
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'A temporary QA account could not be created.' );
	}
	$forum_qa_users[] = absint( $id );
	return absint( $id );
};

$forum_qa_verify_email = static function ( $user_id ) use ( &$forum_qa_intercepted_mail ) {
	$start = count( $forum_qa_intercepted_mail );
	if ( ! CYWater_Membership_Account_Security::issue_verification( $user_id, home_url( '/forum/' ) ) ) {
		return false;
	}
	for ( $index = count( $forum_qa_intercepted_mail ) - 1; $index >= $start; --$index ) {
		$message = (string) ( $forum_qa_intercepted_mail[ $index ]['message'] ?? '' );
		if ( ! preg_match( '#https://[^\s]+#', $message, $matches ) ) {
			continue;
		}
		$url   = html_entity_decode( trim( $matches[0] ) );
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$args  = array();
		parse_str( $query, $args );
		if ( absint( $args['user_id'] ?? 0 ) !== absint( $user_id ) || empty( $args['token'] ) ) {
			continue;
		}
		return CYWater_Membership_Account_Security::verify_token( $user_id, (string) $args['token'] );
	}
	return false;
};

$forum_qa_activate_member = static function ( $user_id ) use ( $forum_qa_level_id ) {
	return (bool) pmpro_changeMembershipLevel( $forum_qa_level_id, absint( $user_id ) );
};

$forum_qa_find_comment = static function ( $response ) {
	if ( ! $response instanceof WP_REST_Response ) {
		return 0;
	}
	$data = $response->get_data();
	return is_array( $data ) ? absint( $data['id'] ?? 0 ) : 0;
};

$forum_qa_comment_status = static function ( $comment_id ) {
	$comment = get_comment( absint( $comment_id ) );
	return $comment instanceof WP_Comment ? (string) $comment->comment_approved : '';
};

$forum_qa_basic_user = (string) getenv( 'CYWATER_QA_BASIC_AUTH_USER' );
$forum_qa_basic_pass = (string) getenv( 'CYWATER_QA_BASIC_AUTH_PASSWORD' );
$forum_qa_basic_auth = '';
if ( '' !== $forum_qa_basic_user && '' !== $forum_qa_basic_pass ) {
	$forum_qa_basic_auth = 'Basic ' . base64_encode( $forum_qa_basic_user . ':' . $forum_qa_basic_pass );
}
$forum_qa_can_reach_application_http = '' !== $forum_qa_basic_auth;

$forum_qa_http_get = static function ( $url ) use ( $forum_qa_basic_auth ) {
	$headers = array( 'Cache-Control' => 'no-cache' );
	if ( '' !== $forum_qa_basic_auth ) {
		$headers['Authorization'] = $forum_qa_basic_auth;
	}
	return wp_remote_get(
		add_query_arg( 'cywater_forum_qa', wp_generate_password( 10, false, false ), $url ),
		array(
			'timeout'     => 20,
			'redirection' => 0,
			'headers'     => $headers,
		)
	);
};

$forum_qa_user_http_get = static function ( $user_id, $url ) use ( $forum_qa_basic_auth ) {
	$headers = array(
		'Cache-Control' => 'no-cache',
		'Cookie'        => LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( absint( $user_id ), time() + 300, 'logged_in' ),
	);
	if ( '' !== $forum_qa_basic_auth ) {
		$headers['Authorization'] = $forum_qa_basic_auth;
	}
	return wp_remote_get(
		add_query_arg( 'cywater_forum_qa', wp_generate_password( 10, false, false ), $url ),
		array( 'timeout' => 20, 'redirection' => 0, 'headers' => $headers )
	);
};

$forum_qa_admin_http_get = static function ( $user_id, $relative_path ) use ( $forum_qa_basic_auth ) {
	$admin_https = 'https' === strtolower( (string) wp_parse_url( admin_url(), PHP_URL_SCHEME ) );
	$scheme      = $admin_https ? 'secure_auth' : 'auth';
	$cookie_name = $admin_https ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
	$expiration  = time() + 300;
	$cookie      = wp_generate_auth_cookie( absint( $user_id ), $expiration, $scheme );
	$headers     = array(
		'Cache-Control' => 'no-cache',
		'Cookie'        => $cookie_name . '=' . $cookie,
	);
	if ( '' !== $forum_qa_basic_auth ) {
		$headers['Authorization'] = $forum_qa_basic_auth;
	}
	return wp_remote_get(
		admin_url( ltrim( (string) $relative_path, '/' ) ),
		array(
			'timeout'     => 20,
			'redirection' => 0,
			'headers'     => $headers,
		)
	);
};

try {
	$forum_qa_marker        = 'CYW Forum QA ' . gmdate( 'YmdHis' ) . ' ' . wp_generate_password( 6, false, false );
	$forum_qa_first_reply   = $forum_qa_marker . ' auto-published first reply';
	$forum_qa_second_reply  = $forum_qa_marker . ' auto-published second reply';
	$forum_qa_staff_reply   = $forum_qa_marker . ' eligible moderator reply';
	$forum_qa_pending_reply = $forum_qa_marker . ' manually held moderation fixture';
	$forum_qa_outside_reply = $forum_qa_marker . ' unrelated approved comment';

	$forum_qa_author_id    = $forum_qa_create_user( 'author', 'subscriber' );
	$forum_qa_pending_id   = $forum_qa_create_user( 'pending', 'subscriber' );
	$forum_qa_moderator_id = $forum_qa_create_user( 'moderator', CYWater_Operations_Roles::COMMUNITY_MODERATOR );
	$forum_qa_editor_id    = $forum_qa_create_user( 'editor', CYWater_Operations_Roles::CONTENT_EDITOR );
	$forum_qa_program_id   = $forum_qa_create_user( 'program', CYWater_Operations_Roles::PROGRAM_REVIEWER );
	$forum_qa_governance_id = $forum_qa_create_user( 'governance', CYWater_Operations_Roles::GOVERNANCE_APPROVER );
	$forum_qa_builtin_editor_id = $forum_qa_create_user( 'builtin_editor', 'editor' );
	$forum_qa_subscriber_id = $forum_qa_create_user( 'subscriber', 'subscriber' );
	$forum_qa_builtin_editor_role = get_role( 'editor' );
	$forum_qa_legacy_editor_caps  = array_diff( CYWater_Forum_Roles::editor_capabilities(), array( 'read', 'upload_files' ) );
	$forum_qa_builtin_editor_forum_caps = array_filter(
		$forum_qa_legacy_editor_caps,
		static function ( $cap ) use ( $forum_qa_builtin_editor_role ) {
			return $forum_qa_builtin_editor_role instanceof WP_Role && ! empty( $forum_qa_builtin_editor_role->capabilities[ $cap ] );
		}
	);

	$forum_qa_assert( ! user_can( $forum_qa_author_id, 'edit_cyw_forum_posts' ), 'A plain registered account can create forum articles before authorship is granted.' );
	$forum_qa_assert( ! user_can( $forum_qa_editor_id, 'edit_cyw_forum_posts' ), 'Content Editor unexpectedly has a Forum Author primitive.' );
	$forum_qa_assert( ! user_can( $forum_qa_editor_id, 'edit_others_cyw_forum_posts' ), 'Content Editor unexpectedly has cross-author forum editing.' );
	$forum_qa_assert( ! user_can( $forum_qa_editor_id, 'moderate_comments' ), 'Content Editor unexpectedly has comment moderation.' );
	$forum_qa_assert( $forum_qa_builtin_editor_role instanceof WP_Role && array() === $forum_qa_builtin_editor_forum_caps, 'Built-in Editor retains legacy Forum-specific CPT or term capabilities.' );
	$forum_qa_assert( user_can( $forum_qa_builtin_editor_id, 'moderate_comments' ), 'Built-in Editor lost the core moderate_comments capability needed for non-Forum content.' );
	$forum_qa_assert( user_can( $forum_qa_admin_id, 'edit_others_cyw_forum_posts' ) && user_can( $forum_qa_admin_id, 'manage_cyw_forum_terms' ), 'Administrator lost required Forum staff capabilities during role migration.' );
	$forum_qa_assert( ! user_can( $forum_qa_subscriber_id, 'edit_cyw_forum_posts' ), 'Subscriber unexpectedly has a Forum Author primitive.' );
	$forum_qa_assert( user_can( $forum_qa_moderator_id, 'edit_others_cyw_forum_posts' ), 'Community Moderator cannot edit another author\'s forum article.' );
	$forum_qa_assert( user_can( $forum_qa_moderator_id, 'publish_cyw_forum_posts' ), 'Community Moderator cannot publish forum articles.' );
	$forum_qa_assert( user_can( $forum_qa_moderator_id, 'moderate_comments' ), 'Community Moderator cannot moderate comments.' );
	$forum_qa_assert( ! CYWater_Forum_Workspace::can_access_admin( $forum_qa_author_id ), 'Ordinary registered account can access WordPress administration.' );
	$forum_qa_assert( ! CYWater_Forum_Workspace::can_access_admin( $forum_qa_builtin_editor_id ), 'Unassigned built-in Editor bypasses the Operations staff boundary.' );
	$forum_qa_assert( CYWater_Forum_Workspace::can_access_admin( $forum_qa_admin_id ), 'Administrator is incorrectly denied WordPress administration.' );
	$forum_qa_assert( CYWater_Forum_Workspace::can_access_admin( $forum_qa_moderator_id ), 'Community Moderator is incorrectly denied WordPress administration.' );
	$forum_qa_assert( CYWater_Forum_Workspace::can_access_admin( $forum_qa_editor_id ), 'Content & Event Editor is incorrectly denied WordPress administration.' );
	$forum_qa_assert( CYWater_Forum_Workspace::can_access_admin( $forum_qa_program_id ), 'Program Reviewer is incorrectly denied WordPress administration.' );
	$forum_qa_assert( CYWater_Forum_Workspace::can_access_admin( $forum_qa_governance_id ), 'Governance Approver is incorrectly denied WordPress administration.' );
	$forum_qa_denial_hook = has_action( 'init', array( 'CYWater_Forum_Workspace', 'deny_nonstaff_admin' ) );
	$forum_qa_assert( false !== $forum_qa_denial_hook && $forum_qa_denial_hook < 30, 'The member admin denial does not run before wp-admin menu construction.' );
	if ( $forum_qa_can_reach_application_http ) {
		foreach ( array( 'index.php', 'profile.php', 'upload.php', 'edit.php?post_type=cyw_forum_post' ) as $forum_qa_admin_path ) {
			$forum_qa_admin_response = $forum_qa_admin_http_get( $forum_qa_author_id, $forum_qa_admin_path );
			$forum_qa_admin_location = is_wp_error( $forum_qa_admin_response ) ? '' : wp_remote_retrieve_header( $forum_qa_admin_response, 'location' );
			$forum_qa_assert(
				! is_wp_error( $forum_qa_admin_response )
					&& 403 === wp_remote_retrieve_response_code( $forum_qa_admin_response )
					&& '' === (string) $forum_qa_admin_location,
				'Ordinary member did not receive a direct 403 denial for wp-admin route: ' . $forum_qa_admin_path
			);
		}
	} else {
		WP_CLI::log( 'HTTP route assertions skipped because staging Basic Auth credentials were not supplied; application-layer permission assertions still run.' );
	}
	$forum_qa_assert( false === has_filter( 'login_redirect', array( 'CYWater_Forum_Workspace', 'filter_login_redirect' ) ), 'Forum workspace still specializes WordPress login redirects.' );
	wp_set_current_user( $forum_qa_author_id );
	$forum_qa_assert( false === CYWater_Forum_Workspace::filter_admin_bar( true ), 'Ordinary member still receives the WordPress administration toolbar.' );
	wp_set_current_user( $forum_qa_moderator_id );
	$forum_qa_assert( false === CYWater_Forum_Workspace::filter_admin_bar( true ), 'Community Moderator sees a WordPress administration toolbar on the public site.' );
	wp_set_current_user( $forum_qa_admin_id );
	$forum_qa_workspace_page_id = CYWater_Forum_Workspace::page_id();
	$forum_qa_assert( $forum_qa_workspace_page_id > 0 && 'publish' === get_post_status( $forum_qa_workspace_page_id ), 'The published front-end Forum workspace page is unavailable.' );
	$forum_qa_assert( 'forum-workspace' === get_post_field( 'post_name', $forum_qa_workspace_page_id ), 'The Forum workspace page does not use the stable /forum-workspace/ route.' );
	$forum_qa_author_role = get_role( CYWater_Forum_Roles::AUTHOR_ROLE );
	$forum_qa_assert( $forum_qa_author_role instanceof WP_Role, 'The durable Forum Author role is unavailable.' );
	$forum_qa_assert( ! empty( $forum_qa_author_role->capabilities['publish_cyw_forum_posts'] ), 'Forum Author lacks the direct publication primitive.' );
	$forum_qa_assert( ! empty( $forum_qa_author_role->capabilities['edit_published_cyw_forum_posts'] ), 'Forum Author cannot update their own published article.' );
	$forum_qa_assert( empty( $forum_qa_author_role->capabilities['delete_published_cyw_forum_posts'] ), 'Forum Author can delete a published article.' );
	$forum_qa_assert( empty( $forum_qa_author_role->capabilities['upload_files'] ), 'Forum Author can bypass the front-end cover handler through the Media library.' );

	$forum_qa_logo_event_ids = get_posts(
		array(
			'post_type'      => 'cyw_event',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_cywater_logo_call_enabled',
			'meta_value'     => '1',
		)
	);
	$forum_qa_logo_event_id = absint( $forum_qa_logo_event_ids[0] ?? 0 );
	$forum_qa_assert( $forum_qa_logo_event_id > 0, 'The enabled Logo Design Call event is unavailable for the read-only separation check.' );
	$forum_qa_assert( $forum_qa_verify_email( $forum_qa_subscriber_id ), 'Registered non-member Logo fixture could not complete email verification.' );
	$forum_qa_logo_submit_policy = CYWater_Logo_Call_Eligibility::policy( $forum_qa_logo_event_id, 'submit' );
	$forum_qa_logo_vote_policy   = CYWater_Logo_Call_Eligibility::policy( $forum_qa_logo_event_id, 'vote' );
	$forum_qa_assert( CYWater_Logo_Call_Eligibility::AUDIENCE_REGISTERED === $forum_qa_logo_submit_policy['audience'], 'Forum policy changed the Logo Call submission audience.' );
	$forum_qa_assert( CYWater_Logo_Call_Eligibility::AUDIENCE_REGISTERED === $forum_qa_logo_vote_policy['audience'], 'Forum policy changed the Logo Call voting audience.' );
	$forum_qa_assert( CYWater_Logo_Call_Eligibility::can_submit( $forum_qa_logo_event_id, $forum_qa_subscriber_id ), 'A registered non-member lost Logo Call submission eligibility.' );
	$forum_qa_assert( CYWater_Logo_Call_Eligibility::can_vote( $forum_qa_logo_event_id, $forum_qa_subscriber_id ), 'A registered non-member lost Logo Call voting eligibility.' );
	$forum_qa_paused_endorsement = do_shortcode( '[cywater_forum_endorsement]' );
	$forum_qa_assert( false !== strpos( $forum_qa_paused_endorsement, 'Invitations and endorsements are currently paused' ), 'Historical endorsement page does not explain the paused state.' );
	$forum_qa_assert( false === strpos( $forum_qa_paused_endorsement, '<form' ), 'Paused endorsement page still exposes a request form.' );

	$forum_qa_plain_create = $forum_qa_rest(
		$forum_qa_subscriber_id,
		'POST',
		'/wp/v2/cyw_forum_post',
		array( 'title' => $forum_qa_marker . ' forbidden subscriber article', 'status' => 'draft' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_plain_create ) >= 400, 'Subscriber created a forum article through REST.' );
	$forum_qa_plain_workspace_create = CYWater_Forum_Workspace::save_article(
		$forum_qa_subscriber_id,
		array(
			'title'   => $forum_qa_marker . ' forbidden non-member workspace article',
			'content' => $forum_qa_marker . ' forbidden non-member workspace body',
			'status'  => 'draft',
		)
	);
	$forum_qa_assert( is_wp_error( $forum_qa_plain_workspace_create ) && 'submission_blocked' === $forum_qa_plain_workspace_create->get_error_code(), 'A non-member bypassed eligibility through the front-end service.' );

	$forum_qa_editor_create = $forum_qa_rest(
		$forum_qa_editor_id,
		'POST',
		'/wp/v2/cyw_forum_post',
		array( 'title' => $forum_qa_marker . ' forbidden editor article', 'status' => 'draft' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_editor_create ) >= 400, 'Content Editor created a forum article through REST.' );

	$forum_qa_assert( $forum_qa_activate_member( $forum_qa_author_id ), 'Temporary Forum Author membership could not be activated.' );
	$forum_qa_assert( pmpro_hasMembershipLevel( null, $forum_qa_author_id ), 'Temporary Forum Author membership is not active after activation.' );
	$forum_qa_unverified_blockers = CYWater_Forum_Roles::submission_blockers( $forum_qa_author_id );
	$forum_qa_assert( in_array( 'email_unverified', $forum_qa_unverified_blockers, true ), 'An active member with an unverified email is not blocked from article submission.' );
	wp_set_current_user( $forum_qa_author_id );
	CYWater_Forum_Roles::sync_current_member_role();
	wp_set_current_user( $forum_qa_admin_id );
	$forum_qa_author = get_user_by( 'id', $forum_qa_author_id );
	$forum_qa_assert( $forum_qa_author instanceof WP_User && ! in_array( CYWater_Forum_Roles::AUTHOR_ROLE, (array) $forum_qa_author->roles, true ), 'An unverified member received Forum Author submission access.' );

	$forum_qa_unverified_create = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/cyw_forum_post',
		array( 'title' => $forum_qa_marker . ' forbidden unverified article', 'status' => 'draft' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_unverified_create ) >= 400, 'An active member with an unverified email created a Forum draft.' );
	$forum_qa_unverified_workspace = CYWater_Forum_Workspace::save_article(
		$forum_qa_author_id,
		array(
			'title'   => $forum_qa_marker . ' forbidden unverified workspace article',
			'content' => $forum_qa_marker . ' forbidden unverified workspace body',
			'status'  => 'draft',
		)
	);
	$forum_qa_assert( is_wp_error( $forum_qa_unverified_workspace ) && 'submission_blocked' === $forum_qa_unverified_workspace->get_error_code(), 'An unverified member bypassed eligibility through the front-end service.' );

	$forum_qa_assert( $forum_qa_verify_email( $forum_qa_author_id ), 'Temporary Forum Author email could not be verified through the real token flow.' );
	$forum_qa_assert( CYWater_Membership_Account_Security::is_verified( $forum_qa_author_id ), 'Temporary Forum Author verification state is not active.' );
	wp_set_current_user( $forum_qa_author_id );
	CYWater_Forum_Roles::sync_current_member_role();
	wp_set_current_user( $forum_qa_admin_id );
	$forum_qa_author = get_user_by( 'id', $forum_qa_author_id );
	$forum_qa_assert( $forum_qa_author instanceof WP_User && in_array( CYWater_Forum_Roles::AUTHOR_ROLE, (array) $forum_qa_author->roles, true ), 'An eligible member did not receive the durable Forum Author role.' );
	$forum_qa_assert( CYWater_Forum_Roles::can_submit( $forum_qa_author_id ), 'Verified active member remains blocked from article submission.' );
	$forum_qa_assert( user_can( $forum_qa_author_id, 'edit_cyw_forum_posts' ), 'Eligible member cannot create a Forum draft.' );
	$forum_qa_assert( user_can( $forum_qa_author_id, 'publish_cyw_forum_posts' ), 'Eligible member cannot publish a Forum article directly.' );
	$forum_qa_assert( user_can( $forum_qa_author_id, 'edit_published_cyw_forum_posts' ), 'Eligible member cannot update their own published Forum article.' );
	$forum_qa_assert( ! user_can( $forum_qa_author_id, 'edit_others_cyw_forum_posts' ), 'Forum Author can edit another author\'s article.' );

	$forum_qa_category_ids = get_terms(
		array(
			'taxonomy'   => CYWater_Forum_Content::CATEGORY,
			'hide_empty' => false,
			'fields'     => 'ids',
			'number'     => 1,
		)
	);
	$forum_qa_topic_ids = get_terms(
		array(
			'taxonomy'   => CYWater_Forum_Content::TOPIC,
			'hide_empty' => false,
			'fields'     => 'ids',
			'number'     => 1,
		)
	);
	$forum_qa_category_id = ! is_wp_error( $forum_qa_category_ids ) ? absint( $forum_qa_category_ids[0] ?? 0 ) : 0;
	$forum_qa_topic_id    = ! is_wp_error( $forum_qa_topic_ids ) ? absint( $forum_qa_topic_ids[0] ?? 0 ) : 0;
	$forum_qa_assert( $forum_qa_category_id > 0, 'No existing Forum category is available to the front-end workspace.' );

	wp_set_current_user( $forum_qa_author_id );
	$forum_qa_workspace_create = CYWater_Forum_Workspace::save_article(
		$forum_qa_author_id,
		array(
			'title'       => $forum_qa_marker . ' workspace draft',
			'content'     => $forum_qa_marker . ' workspace initial body',
			'category_id' => $forum_qa_category_id,
			'topic_id'    => $forum_qa_topic_id,
			'status'      => 'draft',
		)
	);
	$forum_qa_workspace_post_id = is_wp_error( $forum_qa_workspace_create ) ? 0 : absint( $forum_qa_workspace_create );
	$forum_qa_assert( $forum_qa_workspace_post_id > 0, 'Eligible member could not save a draft through the front-end service.' );
	if ( ! $forum_qa_workspace_post_id ) {
		throw new RuntimeException( 'The front-end Forum fixture could not be created safely.' );
	}
	$forum_qa_posts[] = $forum_qa_workspace_post_id;
	$forum_qa_assert( 'draft' === get_post_status( $forum_qa_workspace_post_id ) && $forum_qa_author_id === absint( get_post_field( 'post_author', $forum_qa_workspace_post_id ) ), 'Front-end draft status or ownership was not persisted.' );
	$forum_qa_workspace_category_ids = wp_get_object_terms( $forum_qa_workspace_post_id, CYWater_Forum_Content::CATEGORY, array( 'fields' => 'ids' ) );
	$forum_qa_assert( ! is_wp_error( $forum_qa_workspace_category_ids ) && in_array( $forum_qa_category_id, array_map( 'absint', (array) $forum_qa_workspace_category_ids ), true ), 'Front-end workspace did not persist the selected existing category.' );

	$forum_qa_workspace_draft_update = CYWater_Forum_Workspace::save_article(
		$forum_qa_author_id,
		array(
			'post_id'     => $forum_qa_workspace_post_id,
			'title'       => $forum_qa_marker . ' workspace draft updated',
			'content'     => $forum_qa_marker . ' workspace updated draft body',
			'category_id' => $forum_qa_category_id,
			'topic_id'    => $forum_qa_topic_id,
			'status'      => 'draft',
		)
	);
	$forum_qa_assert( $forum_qa_workspace_post_id === $forum_qa_workspace_draft_update && false !== strpos( (string) get_post_field( 'post_content', $forum_qa_workspace_post_id ), 'updated draft body' ), 'Eligible member could not update their own front-end draft.' );

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$forum_qa_cover_temp = wp_tempnam( 'cywater-forum-qa-cover.png' );
	if ( ! $forum_qa_cover_temp || false === file_put_contents( $forum_qa_cover_temp, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		throw new RuntimeException( 'The front-end cover fixture could not be prepared safely.' );
	}
	$forum_qa_temp_files[] = $forum_qa_cover_temp;
	$forum_qa_cover = CYWater_Forum_Covers::import_file_for_qa( $forum_qa_cover_temp, 'cywater-forum-qa-cover.png', $forum_qa_author_id );
	$forum_qa_assert( ! is_wp_error( $forum_qa_cover ) && ! empty( $forum_qa_cover['stored'] ), 'The protected front-end cover-image fixture could not be created.' );
	if ( is_wp_error( $forum_qa_cover ) || ! $forum_qa_cover ) {
		throw new RuntimeException( 'The front-end cover-image fixture could not be created safely.' );
	}
	$forum_qa_workspace_cover_update = CYWater_Forum_Workspace::save_article(
		$forum_qa_author_id,
		array(
			'post_id'     => $forum_qa_workspace_post_id,
			'title'       => $forum_qa_marker . ' workspace draft updated',
			'content'     => $forum_qa_marker . ' workspace updated draft body',
			'category_id' => $forum_qa_category_id,
			'topic_id'    => $forum_qa_topic_id,
			'status'      => 'draft',
		),
		$forum_qa_cover
	);
	$forum_qa_saved_cover = CYWater_Forum_Covers::get( $forum_qa_workspace_post_id );
	$forum_qa_assert( $forum_qa_workspace_post_id === $forum_qa_workspace_cover_update && ! empty( $forum_qa_saved_cover['stored'] ) && 0 === absint( get_post_thumbnail_id( $forum_qa_workspace_post_id ) ), 'Front-end workspace did not attach the eligible member\'s cover exclusively in protected storage.' );
	$forum_qa_cover_url   = CYWater_Forum_Covers::url( $forum_qa_workspace_post_id );
	$forum_qa_cover_query = array();
	parse_str( (string) wp_parse_url( $forum_qa_cover_url, PHP_URL_QUERY ), $forum_qa_cover_query );
	$forum_qa_assert( ! CYWater_Forum_Covers::can_stream( $forum_qa_workspace_post_id, 0, '' ), 'An anonymous visitor can stream a draft Forum cover.' );
	$forum_qa_assert( CYWater_Forum_Covers::can_stream( $forum_qa_workspace_post_id, $forum_qa_author_id, (string) ( $forum_qa_cover_query['_wpnonce'] ?? '' ) ), 'The article owner cannot preview their protected draft cover.' );

	$forum_qa_replacement = CYWater_Forum_Covers::import_file_for_qa( $forum_qa_cover_temp, 'cywater-forum-qa-cover-replacement.png', $forum_qa_author_id );
	$forum_qa_old_path    = CYWater_Forum_Covers::path_for_qa( $forum_qa_saved_cover );
	$forum_qa_workspace_cover_replacement = CYWater_Forum_Workspace::save_article(
		$forum_qa_author_id,
		array(
			'post_id'     => $forum_qa_workspace_post_id,
			'title'       => $forum_qa_marker . ' workspace draft updated',
			'content'     => $forum_qa_marker . ' workspace updated draft body',
			'category_id' => $forum_qa_category_id,
			'topic_id'    => $forum_qa_topic_id,
			'status'      => 'draft',
		),
		$forum_qa_replacement
	);
	$forum_qa_replaced_cover = CYWater_Forum_Covers::get( $forum_qa_workspace_post_id );
	$forum_qa_replaced_path = CYWater_Forum_Covers::path_for_qa( $forum_qa_replaced_cover );
	$forum_qa_protected_files[] = $forum_qa_replaced_path;
	$forum_qa_assert( $forum_qa_workspace_post_id === $forum_qa_workspace_cover_replacement && ! empty( $forum_qa_replaced_cover['stored'] ) && $forum_qa_replaced_cover['stored'] !== $forum_qa_saved_cover['stored'] && ! file_exists( $forum_qa_old_path ), 'Replacing a Forum cover did not remove the prior protected file.' );
	CYWater_Forum_Covers::discard( $forum_qa_replaced_cover );
	$forum_qa_assert( file_exists( $forum_qa_replaced_path ), 'Cleanup deleted a Forum cover that is still referenced by its article.' );

	$forum_qa_rollback_cover = CYWater_Forum_Covers::import_file_for_qa( $forum_qa_cover_temp, 'cywater-forum-qa-cover-rollback.png', $forum_qa_author_id );
	$forum_qa_rollback_path  = ! is_wp_error( $forum_qa_rollback_cover ) ? CYWater_Forum_Covers::path_for_qa( $forum_qa_rollback_cover ) : '';
	$forum_qa_meta_failure = static function ( $check, $object_id, $meta_key ) use ( $forum_qa_workspace_post_id ) {
		return $forum_qa_workspace_post_id === absint( $object_id ) && '_cywater_forum_private_cover' === $meta_key ? false : $check;
	};
	add_filter( 'update_post_metadata', $forum_qa_meta_failure, 10, 3 );
	$forum_qa_rollback_result = ! is_wp_error( $forum_qa_rollback_cover ) ? CYWater_Forum_Covers::replace( $forum_qa_workspace_post_id, $forum_qa_rollback_cover, $forum_qa_author_id ) : $forum_qa_rollback_cover;
	remove_filter( 'update_post_metadata', $forum_qa_meta_failure, 10 );
	$forum_qa_assert( is_wp_error( $forum_qa_rollback_result ) && CYWater_Forum_Covers::get( $forum_qa_workspace_post_id ) === $forum_qa_replaced_cover && file_exists( $forum_qa_replaced_path ) && ( ! $forum_qa_rollback_path || ! file_exists( $forum_qa_rollback_path ) ), 'A failed cover metadata write did not preserve the prior cover and discard only the uncommitted replacement.' );

	$forum_qa_workspace_publish = CYWater_Forum_Workspace::save_article(
		$forum_qa_author_id,
		array(
			'post_id'     => $forum_qa_workspace_post_id,
			'title'       => $forum_qa_marker . ' workspace published',
			'content'     => $forum_qa_marker . ' workspace published body',
			'category_id' => $forum_qa_category_id,
			'topic_id'    => $forum_qa_topic_id,
			'status'      => 'publish',
		)
	);
	$forum_qa_assert( $forum_qa_workspace_post_id === $forum_qa_workspace_publish && 'publish' === get_post_status( $forum_qa_workspace_post_id ), 'Eligible member could not publish a front-end draft directly.' );

	$forum_qa_workspace_published_update = CYWater_Forum_Workspace::save_article(
		$forum_qa_author_id,
		array(
			'post_id'     => $forum_qa_workspace_post_id,
			'title'       => $forum_qa_marker . ' workspace published updated',
			'content'     => $forum_qa_marker . ' workspace updated published body',
			'category_id' => $forum_qa_category_id,
			'topic_id'    => $forum_qa_topic_id,
			'status'      => 'publish',
		)
	);
	$forum_qa_assert( $forum_qa_workspace_post_id === $forum_qa_workspace_published_update && false !== strpos( (string) get_post_field( 'post_content', $forum_qa_workspace_post_id ), 'updated published body' ), 'Eligible member could not update their own published article.' );

	$forum_qa_author_token = CYWater_Forum_Community::author_token( $forum_qa_author_id, false );
	$forum_qa_author_url   = CYWater_Forum_Community::author_url( $forum_qa_author_id );
	$forum_qa_assert( 1 === preg_match( '/^[a-f0-9]{32}$/', $forum_qa_author_token ), 'Published Forum author did not receive an opaque public token.' );
	$forum_qa_assert( false !== strpos( $forum_qa_author_url, '/forum/member/' . $forum_qa_author_token . '/' ), 'Forum byline does not use the controlled public-author route.' );
	$forum_qa_assert( false === strpos( $forum_qa_author_url, get_userdata( $forum_qa_author_id )->user_nicename ), 'Forum author URL exposes the WordPress login-derived nicename.' );
	update_user_meta( $forum_qa_author_id, 'cyw_profile_public', 1 );
	update_user_meta( $forum_qa_author_id, 'cyw_public_fields', array( 'cyw_institution_name' ) );
	update_user_meta( $forum_qa_author_id, 'cyw_institution_name', $forum_qa_marker . ' public institution' );
	$forum_qa_public_profile = CYWater_Forum_Community::public_profile( $forum_qa_author_id );
	$forum_qa_assert( isset( $forum_qa_public_profile['cyw_institution_name'] ) && ! isset( $forum_qa_public_profile['cyw_country'] ), 'Forum author page does not honor the member\'s selected public-profile fields.' );
	$forum_qa_anonymous_article_read = $forum_qa_rest( 0, 'GET', '/wp/v2/cyw_forum_post/' . $forum_qa_workspace_post_id );
	$forum_qa_assert( 401 === $forum_qa_rest_status( $forum_qa_anonymous_article_read ), 'Signed-out REST client could read a Forum article.' );
	$forum_qa_registered_article_read = $forum_qa_rest( $forum_qa_subscriber_id, 'GET', '/wp/v2/cyw_forum_post/' . $forum_qa_workspace_post_id );
	$forum_qa_assert( 200 === $forum_qa_rest_status( $forum_qa_registered_article_read ), 'Registered non-member could not read a Forum article through REST.' );

	$forum_qa_assert( 0 === CYWater_Forum_Community::like_count( $forum_qa_workspace_post_id ), 'Temporary Forum article started with unrelated likes.' );
	$forum_qa_like_result = CYWater_Forum_Community::toggle_like( $forum_qa_workspace_post_id, $forum_qa_author_id );
	$forum_qa_assert( true === $forum_qa_like_result && 1 === CYWater_Forum_Community::like_count( $forum_qa_workspace_post_id ) && CYWater_Forum_Community::has_liked( $forum_qa_workspace_post_id, $forum_qa_author_id ), 'Eligible member could not like a published Forum article.' );
	$forum_qa_assert( true === CYWater_Forum_Community::toggle_like( $forum_qa_workspace_post_id, $forum_qa_subscriber_id ) && 2 === CYWater_Forum_Community::like_count( $forum_qa_workspace_post_id ), 'Registered non-member could not like a Forum article.' );
	$forum_qa_account_entry = CYWater_Forum_Community::account_entry();
	$forum_qa_assert( false !== strpos( $forum_qa_account_entry, '/forum/activity/' ) && false === strpos( $forum_qa_account_entry, esc_html( get_the_title( $forum_qa_workspace_post_id ) ) ), 'Member Account does not use the compact Forum activity link.' );
	$forum_qa_activity_panel = CYWater_Forum_Community::activity_panel( $forum_qa_author_id );
	$forum_qa_assert( false !== strpos( $forum_qa_activity_panel, esc_html( get_the_title( $forum_qa_workspace_post_id ) ) ) && false !== strpos( $forum_qa_activity_panel, 'Liked posts' ), 'Private Forum activity view omitted owned or liked posts.' );
	$forum_qa_assert( false === CYWater_Forum_Community::toggle_like( $forum_qa_workspace_post_id, $forum_qa_subscriber_id ) && 1 === CYWater_Forum_Community::like_count( $forum_qa_workspace_post_id ), 'Registered non-member could not remove their own like.' );
	$forum_qa_assert( false === CYWater_Forum_Community::toggle_like( $forum_qa_workspace_post_id, $forum_qa_author_id ) && 0 === CYWater_Forum_Community::like_count( $forum_qa_workspace_post_id ), 'Eligible member could not remove their own like.' );
	$forum_qa_assert( true === CYWater_Forum_Community::toggle_like( $forum_qa_workspace_post_id, $forum_qa_author_id ), 'Eligible member could not restore a like for lifecycle checks.' );

	if ( $forum_qa_can_reach_application_http ) {
		$forum_qa_activity_response = $forum_qa_http_get( CYWater_Forum_Community::activity_url() );
		$forum_qa_assert( ! is_wp_error( $forum_qa_activity_response ) && 302 === (int) wp_remote_retrieve_response_code( $forum_qa_activity_response ), 'Signed-out visitor was not redirected away from private Forum activity.' );
		$forum_qa_activity_response = $forum_qa_user_http_get( $forum_qa_author_id, CYWater_Forum_Community::activity_url() );
		$forum_qa_activity_body     = is_wp_error( $forum_qa_activity_response ) ? '' : (string) wp_remote_retrieve_body( $forum_qa_activity_response );
		$forum_qa_assert( ! is_wp_error( $forum_qa_activity_response ) && 200 === (int) wp_remote_retrieve_response_code( $forum_qa_activity_response ) && false !== strpos( $forum_qa_activity_body, 'Liked posts' ), 'Signed-in author could not open private Forum activity.' );
		$forum_qa_author_response = $forum_qa_http_get( $forum_qa_author_url );
		$forum_qa_assert( ! is_wp_error( $forum_qa_author_response ) && 302 === (int) wp_remote_retrieve_response_code( $forum_qa_author_response ), 'Signed-out visitor was not redirected away from the controlled Forum author route.' );
		$forum_qa_author_response = $forum_qa_user_http_get( $forum_qa_subscriber_id, $forum_qa_author_url );
		$forum_qa_author_body     = is_wp_error( $forum_qa_author_response ) ? '' : (string) wp_remote_retrieve_body( $forum_qa_author_response );
		$forum_qa_assert( ! is_wp_error( $forum_qa_author_response ) && 200 === (int) wp_remote_retrieve_response_code( $forum_qa_author_response ), 'Registered account could not open the controlled Forum author route.' );
		$forum_qa_assert( false !== strpos( $forum_qa_author_body, esc_html( $forum_qa_marker . ' public institution' ) ) && false === strpos( $forum_qa_author_body, esc_html( get_userdata( $forum_qa_author_id )->user_email ) ), 'Controlled author page omitted opted-in data or exposed the account email.' );
	}

	$forum_qa_workspace_unpublish = CYWater_Forum_Workspace::save_article(
		$forum_qa_author_id,
		array(
			'post_id' => $forum_qa_workspace_post_id,
			'title'   => $forum_qa_marker . ' forbidden workspace unpublish',
			'content' => $forum_qa_marker . ' forbidden workspace unpublish body',
			'status'  => 'draft',
		)
	);
	$forum_qa_assert( is_wp_error( $forum_qa_workspace_unpublish ) && 'status_forbidden' === $forum_qa_workspace_unpublish->get_error_code() && 'publish' === get_post_status( $forum_qa_workspace_post_id ), 'Member bypassed staff take-down control by reverting a published article to draft.' );

	wp_set_current_user( $forum_qa_moderator_id );
	$forum_qa_foreign_post_id = wp_insert_post(
		array(
			'post_type'    => CYWater_Forum_Content::POST_TYPE,
			'post_status'  => 'draft',
			'post_author'  => $forum_qa_moderator_id,
			'post_title'   => $forum_qa_marker . ' foreign workspace draft',
			'post_content' => $forum_qa_marker . ' foreign workspace body',
		)
	);
	if ( $forum_qa_foreign_post_id ) {
		$forum_qa_posts[] = absint( $forum_qa_foreign_post_id );
	} else {
		throw new RuntimeException( 'The cross-author Forum fixture could not be created safely.' );
	}
	wp_set_current_user( $forum_qa_author_id );
	$forum_qa_foreign_update = CYWater_Forum_Workspace::save_article(
		$forum_qa_author_id,
		array(
			'post_id' => $forum_qa_foreign_post_id,
			'title'   => $forum_qa_marker . ' forbidden foreign update',
			'content' => $forum_qa_marker . ' forbidden foreign update body',
			'status'  => 'draft',
		)
	);
	$forum_qa_assert( is_wp_error( $forum_qa_foreign_update ) && 'article_not_owned' === $forum_qa_foreign_update->get_error_code(), 'Member updated another author\'s article through the front-end service.' );

	wp_set_current_user( $forum_qa_moderator_id );
	$forum_qa_assert( CYWater_Forum_Covers::can_stream( $forum_qa_workspace_post_id, 0, '' ), 'A published Forum cover is not publicly streamable.' );
	wp_set_current_user( $forum_qa_author_id );
	$forum_qa_workspace_trashed = CYWater_Forum_Workspace::trash_article( $forum_qa_author_id, $forum_qa_workspace_post_id );
	$forum_qa_assert( $forum_qa_workspace_trashed instanceof WP_Post && ! CYWater_Forum_Covers::can_stream( $forum_qa_workspace_post_id, 0, '' ), 'An author could not remove their own published article or its anonymous cover access remained open.' );
	$forum_qa_foreign_trash = CYWater_Forum_Workspace::trash_article( $forum_qa_author_id, $forum_qa_foreign_post_id );
	$forum_qa_assert( is_wp_error( $forum_qa_foreign_trash ) && 'article_not_owned' === $forum_qa_foreign_trash->get_error_code(), 'Member removed another author\'s Forum article through the front-end service.' );
	wp_set_current_user( $forum_qa_moderator_id );
	$forum_qa_workspace_restored = wp_untrash_post( $forum_qa_workspace_post_id );
	$forum_qa_assert( $forum_qa_workspace_restored instanceof WP_Post && 'publish' === get_post_status( $forum_qa_workspace_post_id ) && CYWater_Forum_Covers::can_stream( $forum_qa_workspace_post_id, 0, '' ), 'Moderator restoration did not restore the published cover lifecycle.' );
	wp_set_current_user( $forum_qa_author_id );
	$forum_qa_published_workspace_update = CYWater_Forum_Workspace::save_article(
		$forum_qa_author_id,
		array(
			'post_id' => $forum_qa_workspace_post_id,
			'title'   => $forum_qa_marker . ' allowed published update',
			'content' => $forum_qa_marker . ' allowed published update body',
			'status'  => 'publish',
		)
	);
	$forum_qa_assert( $forum_qa_workspace_post_id === $forum_qa_published_workspace_update && false !== strpos( (string) get_post_field( 'post_content', $forum_qa_workspace_post_id ), 'allowed published update body' ), 'Member could not update their own published article through the front-end service.' );
	$forum_qa_author_articles = wp_list_pluck( CYWater_Forum_Workspace::articles_for_user( $forum_qa_author_id ), 'ID' );
	$forum_qa_assert( in_array( $forum_qa_workspace_post_id, array_map( 'absint', $forum_qa_author_articles ), true ), 'The front-end My articles query omitted the member\'s published article.' );
	wp_set_current_user( $forum_qa_admin_id );

	$forum_qa_draft_response = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/cyw_forum_post',
		array(
			'title'          => $forum_qa_marker,
			'content'        => $forum_qa_marker . ' initial body',
			'excerpt'        => $forum_qa_marker . ' excerpt',
			'status'         => 'draft',
			'comment_status' => 'open',
		)
	);
	$forum_qa_draft_data = $forum_qa_rest_data( $forum_qa_draft_response );
	$forum_qa_post_id    = absint( $forum_qa_draft_data['id'] ?? 0 );
	$forum_qa_assert( 201 === $forum_qa_rest_status( $forum_qa_draft_response ) && $forum_qa_post_id > 0, 'Eligible member could not create a Forum draft through REST.' );
	if ( $forum_qa_post_id ) {
		$forum_qa_posts[] = $forum_qa_post_id;
	}

	$forum_qa_pending_response = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'status' => 'pending' )
	);
	$forum_qa_assert( 'draft' === get_post_status( $forum_qa_post_id ), 'Retired pending-review state remains writable through REST.' );

	$forum_qa_publish_response = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'status' => 'publish', 'comment_status' => 'open' )
	);
	$forum_qa_assert( 200 === $forum_qa_rest_status( $forum_qa_publish_response ) && 'publish' === get_post_status( $forum_qa_post_id ), 'Eligible member could not publish their Forum article directly through REST.' );

	$forum_qa_author_published_update = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'content' => $forum_qa_marker . ' allowed post-publication author update' )
	);
	$forum_qa_assert( 200 === $forum_qa_rest_status( $forum_qa_author_published_update ), 'Member could not edit their own published Forum article.' );

	$forum_qa_editor_update = $forum_qa_rest(
		$forum_qa_editor_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'content' => $forum_qa_marker . ' forbidden editor update' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_editor_update ) >= 400, 'Content Editor edited another account\'s forum article through REST.' );

	$forum_qa_subscriber_update = $forum_qa_rest(
		$forum_qa_subscriber_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'content' => $forum_qa_marker . ' forbidden subscriber update' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_subscriber_update ) >= 400, 'Subscriber edited another account\'s forum article through REST.' );

	$forum_qa_moderator_update = $forum_qa_rest(
		$forum_qa_moderator_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'content' => $forum_qa_marker . ' moderator-reviewed body' )
	);
	$forum_qa_assert( 200 === $forum_qa_rest_status( $forum_qa_moderator_update ), 'Community Moderator could not edit another account\'s forum article.' );
	$forum_qa_assert( false !== strpos( (string) get_post_field( 'post_content', $forum_qa_post_id ), 'moderator-reviewed body' ), 'Community Moderator edit was not persisted.' );

	// Keep the approved-count helper scoped to Forum replies even though the
	// accepted policy now auto-publishes every eligible member reply.
	wp_set_current_user( $forum_qa_admin_id );
	$forum_qa_other_post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => $forum_qa_marker . ' unrelated post',
			'post_content' => $forum_qa_marker . ' unrelated body',
		),
		true
	);
	if ( is_wp_error( $forum_qa_other_post_id ) ) {
		throw new RuntimeException( 'The non-forum regression fixture could not be created.' );
	}
	$forum_qa_other_post_id = absint( $forum_qa_other_post_id );
	$forum_qa_posts[]       = $forum_qa_other_post_id;
	$forum_qa_other_comment_id = wp_insert_comment(
		array(
			'comment_post_ID'  => $forum_qa_other_post_id,
			'comment_content'  => $forum_qa_outside_reply,
			'comment_approved' => 1,
			// This represents an older non-Forum contribution. Keeping it well
			// outside WordPress' flood window prevents the regression fixture
			// itself from blocking the immediately following real Forum reply.
			'comment_date'      => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 5 * MINUTE_IN_SECONDS ) ),
			'comment_date_gmt'  => gmdate( 'Y-m-d H:i:s', time() - ( 5 * MINUTE_IN_SECONDS ) ),
			'user_id'          => $forum_qa_author_id,
			'comment_author'   => 'CYWater Forum QA Author',
			'comment_author_email' => get_userdata( $forum_qa_author_id )->user_email,
		)
	);
	$forum_qa_other_comment_id = absint( $forum_qa_other_comment_id );
	if ( $forum_qa_other_comment_id ) {
		$forum_qa_comments[] = $forum_qa_other_comment_id;
	}
	$forum_qa_assert( $forum_qa_other_comment_id > 0, 'The non-forum approved-comment fixture could not be created.' );
	$forum_qa_assert( 0 === CYWater_Forum_Comments::approved_comment_count( $forum_qa_author_id ), 'A non-forum approved comment incorrectly counts as an approved forum reply.' );

	$forum_qa_editor_status_reply = $forum_qa_marker . ' forbidden pre-approved Editor reply';
	$forum_qa_editor_status_response = $forum_qa_rest(
		$forum_qa_builtin_editor_id,
		'POST',
		'/wp/v2/comments',
		array(
			'post'    => $forum_qa_post_id,
			'content' => $forum_qa_editor_status_reply,
			'status'  => 'approved',
		)
	);
	$forum_qa_editor_status_data = $forum_qa_rest_data( $forum_qa_editor_status_response );
	$forum_qa_assert(
		403 === $forum_qa_rest_status( $forum_qa_editor_status_response )
			&& 'cywater_forum_comment_submission_forbidden' === (string) ( $forum_qa_editor_status_data['code'] ?? '' ),
		'Built-in Editor bypassed Forum moderation through a pre-approved collection POST (' . $forum_qa_rest_diagnostic( $forum_qa_editor_status_response ) . ').'
	);
	$forum_qa_assert(
		0 === (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_content = %s",
				$forum_qa_post_id,
				$forum_qa_editor_status_reply
			)
		),
		'Rejected built-in Editor pre-approved reply was inserted.'
	);

	$forum_qa_editor_foreign_reply = $forum_qa_marker . ' forbidden foreign-author Editor reply';
	$forum_qa_editor_foreign_response = $forum_qa_rest(
		$forum_qa_builtin_editor_id,
		'POST',
		'/wp/v2/comments',
		array(
			'post'    => $forum_qa_post_id,
			'content' => $forum_qa_editor_foreign_reply,
			'author'  => $forum_qa_author_id,
		)
	);
	$forum_qa_editor_foreign_data = $forum_qa_rest_data( $forum_qa_editor_foreign_response );
	$forum_qa_assert(
		403 === $forum_qa_rest_status( $forum_qa_editor_foreign_response )
			&& 'cywater_forum_comment_submission_forbidden' === (string) ( $forum_qa_editor_foreign_data['code'] ?? '' ),
		'Built-in Editor attributed a Forum collection reply to another account (' . $forum_qa_rest_diagnostic( $forum_qa_editor_foreign_response ) . ').'
	);
	$forum_qa_assert(
		0 === (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_content = %s",
				$forum_qa_post_id,
				$forum_qa_editor_foreign_reply
			)
		),
		'Rejected built-in Editor foreign-author reply was inserted.'
	);

	$forum_qa_first_comment_response = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/comments',
		array( 'post' => $forum_qa_post_id, 'content' => $forum_qa_first_reply )
	);
	$forum_qa_first_comment_id = $forum_qa_find_comment( $forum_qa_first_comment_response );
	if ( $forum_qa_first_comment_id ) {
		$forum_qa_comments[] = $forum_qa_first_comment_id;
	}
	$forum_qa_assert(
		201 === $forum_qa_rest_status( $forum_qa_first_comment_response ) && $forum_qa_first_comment_id > 0,
		'Active member could not submit a first forum reply (' . $forum_qa_rest_diagnostic( $forum_qa_first_comment_response ) . ').'
	);
	$forum_qa_assert( '1' === $forum_qa_comment_status( $forum_qa_first_comment_id ), 'An eligible member\'s first Forum reply was not published immediately.' );
	wp_set_current_user( 0 );
	$forum_qa_assert( 0 === (int) get_comments( array( 'post_id' => $forum_qa_post_id, 'count' => true, 'status' => 'approve' ) ), 'A signed-out visitor could query published Forum replies.' );
	$forum_qa_anonymous_comment_read = $forum_qa_rest( 0, 'GET', '/wp/v2/comments/' . $forum_qa_first_comment_id );
	$forum_qa_assert( 401 === $forum_qa_rest_status( $forum_qa_anonymous_comment_read ), 'A signed-out REST client could read an individual Forum reply.' );
	wp_set_current_user( $forum_qa_subscriber_id );
	$forum_qa_assert( 1 === (int) get_comments( array( 'post_id' => $forum_qa_post_id, 'count' => true, 'status' => 'approve' ) ), 'A registered non-member could not view published Forum replies.' );
	$forum_qa_registered_comment_read = $forum_qa_rest( $forum_qa_subscriber_id, 'GET', '/wp/v2/comments/' . $forum_qa_first_comment_id );
	$forum_qa_assert( 200 === $forum_qa_rest_status( $forum_qa_registered_comment_read ), 'A registered non-member could not read an individual Forum reply.' );
	$forum_qa_assert( ! CYWater_Forum_Comments::may_comment( $forum_qa_subscriber_id ), 'A registered non-member unexpectedly gained Forum reply permission.' );

	// Keep the second-reply policy test independent from WordPress' generic
	// comment-flood throttle. The first reply remains the same approved Forum
	// contribution; only its fixture timestamp is moved outside the flood window.
	$forum_qa_first_comment_backdated = wp_update_comment(
		array(
			'comment_ID'       => $forum_qa_first_comment_id,
			'comment_date'     => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 5 * MINUTE_IN_SECONDS ) ),
			'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - ( 5 * MINUTE_IN_SECONDS ) ),
		),
		true
	);
	$forum_qa_assert(
		! is_wp_error( $forum_qa_first_comment_backdated ) && false !== $forum_qa_first_comment_backdated,
		'The approved first-reply fixture could not be moved outside the comment-flood window.'
	);

	$forum_qa_second_comment_response = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/comments',
		array(
			'post'    => $forum_qa_post_id,
			'content' => $forum_qa_second_reply,
			'author'  => $forum_qa_author_id,
		)
	);
	$forum_qa_second_comment_id = $forum_qa_find_comment( $forum_qa_second_comment_response );
	if ( $forum_qa_second_comment_id ) {
		$forum_qa_comments[] = $forum_qa_second_comment_id;
	}
	$forum_qa_assert(
		201 === $forum_qa_rest_status( $forum_qa_second_comment_response ) && '1' === $forum_qa_comment_status( $forum_qa_second_comment_id ),
		'An eligible member\'s later signed-in reply was not published immediately (' . $forum_qa_rest_diagnostic( $forum_qa_second_comment_response ) . ').'
	);

	$forum_qa_assert( $forum_qa_verify_email( $forum_qa_moderator_id ), 'Community Moderator email verification fixture could not be created.' );
	$forum_qa_moderator_article_blockers = CYWater_Forum_Roles::submission_blockers( $forum_qa_moderator_id );
	$forum_qa_assert( in_array( 'membership_inactive', $forum_qa_moderator_article_blockers, true ) && ! CYWater_Forum_Roles::can_submit( $forum_qa_moderator_id ), 'Community Moderator role replaced the personal membership requirement for Forum publishing.' );
	$forum_qa_moderator_nonmember_article = CYWater_Forum_Workspace::save_article(
		$forum_qa_moderator_id,
		array(
			'title'   => $forum_qa_marker . ' forbidden non-member moderator article',
			'content' => $forum_qa_marker . ' forbidden non-member moderator article body',
			'status'  => 'draft',
		)
	);
	$forum_qa_assert( is_wp_error( $forum_qa_moderator_nonmember_article ) && 'submission_blocked' === $forum_qa_moderator_nonmember_article->get_error_code(), 'Community Moderator used the personal workspace without active membership.' );
	if ( $forum_qa_can_reach_application_http ) {
		$forum_qa_moderator_nonmember_archive_response = $forum_qa_user_http_get( $forum_qa_moderator_id, (string) get_post_type_archive_link( CYWater_Forum_Content::POST_TYPE ) );
		$forum_qa_moderator_nonmember_archive_body     = is_wp_error( $forum_qa_moderator_nonmember_archive_response ) ? '' : (string) wp_remote_retrieve_body( $forum_qa_moderator_nonmember_archive_response );
		$forum_qa_assert( ! is_wp_error( $forum_qa_moderator_nonmember_archive_response ) && 200 === (int) wp_remote_retrieve_response_code( $forum_qa_moderator_nonmember_archive_response ) && false !== strpos( $forum_qa_moderator_nonmember_archive_body, 'View membership' ) && false === strpos( $forum_qa_moderator_nonmember_archive_body, 'Manage Forum' ), 'Community Moderator without membership did not receive the ordinary non-member Forum experience.' );
	}
	$forum_qa_moderator_nonmember_response = $forum_qa_rest(
		$forum_qa_moderator_id,
		'POST',
		'/wp/v2/comments',
		array( 'post' => $forum_qa_post_id, 'content' => $forum_qa_marker . ' forbidden non-member moderator reply' )
	);
	$forum_qa_moderator_nonmember_data = $forum_qa_rest_data( $forum_qa_moderator_nonmember_response );
	$forum_qa_assert(
		403 === $forum_qa_rest_status( $forum_qa_moderator_nonmember_response )
			&& 'cywater_forum_membership_required' === (string) ( $forum_qa_moderator_nonmember_data['code'] ?? '' ),
		'Community Moderator role bypassed the active-member participation requirement.'
	);
	$forum_qa_assert( $forum_qa_activate_member( $forum_qa_moderator_id ), 'Community Moderator membership fixture could not be activated.' );
	wp_set_current_user( $forum_qa_moderator_id );
	CYWater_Forum_Roles::sync_current_member_role();
	wp_set_current_user( $forum_qa_admin_id );
	$forum_qa_moderator_user = get_userdata( $forum_qa_moderator_id );
	$forum_qa_assert( CYWater_Forum_Roles::can_submit( $forum_qa_moderator_id ) && $forum_qa_moderator_user instanceof WP_User && in_array( CYWater_Forum_Roles::AUTHOR_ROLE, (array) $forum_qa_moderator_user->roles, true ), 'Eligible Community Moderator did not retain the ordinary member authorship path.' );
	$forum_qa_moderator_workspace_article = CYWater_Forum_Workspace::save_article(
		$forum_qa_moderator_id,
		array(
			'title'   => $forum_qa_marker . ' eligible moderator personal article',
			'content' => $forum_qa_marker . ' eligible moderator personal article body',
			'status'  => 'draft',
		)
	);
	$forum_qa_moderator_workspace_post_id = is_wp_error( $forum_qa_moderator_workspace_article ) ? 0 : absint( $forum_qa_moderator_workspace_article );
	if ( $forum_qa_moderator_workspace_post_id ) {
		$forum_qa_posts[] = $forum_qa_moderator_workspace_post_id;
	}
	$forum_qa_assert( $forum_qa_moderator_workspace_post_id > 0 && $forum_qa_moderator_id === absint( get_post_field( 'post_author', $forum_qa_moderator_workspace_post_id ) ), 'Eligible Community Moderator could not use the ordinary personal Forum workspace.' );
	$forum_qa_assert( user_can( $forum_qa_moderator_id, 'edit_others_cyw_forum_posts' ) && user_can( $forum_qa_moderator_id, 'moderate_comments' ), 'Using the personal member path removed the Community Moderator management capabilities.' );
	if ( $forum_qa_can_reach_application_http ) {
		$forum_qa_moderator_archive_response = $forum_qa_user_http_get( $forum_qa_moderator_id, (string) get_post_type_archive_link( CYWater_Forum_Content::POST_TYPE ) );
		$forum_qa_moderator_archive_body     = is_wp_error( $forum_qa_moderator_archive_response ) ? '' : (string) wp_remote_retrieve_body( $forum_qa_moderator_archive_response );
		$forum_qa_assert( ! is_wp_error( $forum_qa_moderator_archive_response ) && 200 === (int) wp_remote_retrieve_response_code( $forum_qa_moderator_archive_response ) && false !== strpos( $forum_qa_moderator_archive_body, 'Write a Forum post' ) && false === strpos( $forum_qa_moderator_archive_body, 'Manage Forum' ), 'Eligible Community Moderator did not receive the ordinary member Forum archive experience.' );

		$forum_qa_moderator_workspace_response = $forum_qa_user_http_get( $forum_qa_moderator_id, CYWater_Forum_Workspace::url() );
		$forum_qa_moderator_workspace_body     = is_wp_error( $forum_qa_moderator_workspace_response ) ? '' : (string) wp_remote_retrieve_body( $forum_qa_moderator_workspace_response );
		$forum_qa_assert( ! is_wp_error( $forum_qa_moderator_workspace_response ) && 200 === (int) wp_remote_retrieve_response_code( $forum_qa_moderator_workspace_response ) && false !== strpos( $forum_qa_moderator_workspace_body, 'Publish article' ) && false !== strpos( $forum_qa_moderator_workspace_body, 'My articles' ) && false === strpos( $forum_qa_moderator_workspace_body, 'Manage Forum' ), 'Eligible Community Moderator did not receive the ordinary personal publishing workspace.' );

		$forum_qa_admin_account_response = $forum_qa_user_http_get( $forum_qa_admin_id, home_url( '/account/' ) );
		$forum_qa_admin_account_body     = is_wp_error( $forum_qa_admin_account_response ) ? '' : (string) wp_remote_retrieve_body( $forum_qa_admin_account_response );
		$forum_qa_assert( ! is_wp_error( $forum_qa_admin_account_response ) && 200 === (int) wp_remote_retrieve_response_code( $forum_qa_admin_account_response ) && false === strpos( $forum_qa_admin_account_body, 'Edit this page' ) && false === strpos( $forum_qa_admin_account_body, 'Manage Forum' ), 'Administrator-only shortcuts leaked into the public Account experience.' );
	}
	$forum_qa_moderator_comment_response = $forum_qa_rest(
		$forum_qa_moderator_id,
		'POST',
		'/wp/v2/comments',
		array( 'post' => $forum_qa_post_id, 'content' => $forum_qa_staff_reply )
	);
	$forum_qa_moderator_comment_id = $forum_qa_find_comment( $forum_qa_moderator_comment_response );
	if ( $forum_qa_moderator_comment_id ) {
		$forum_qa_comments[] = $forum_qa_moderator_comment_id;
	}
	$forum_qa_assert( 201 === $forum_qa_rest_status( $forum_qa_moderator_comment_response ) && '1' === $forum_qa_comment_status( $forum_qa_moderator_comment_id ), 'Eligible verified Community Moderator reply was not published immediately.' );

	$forum_qa_assert( $forum_qa_activate_member( $forum_qa_pending_id ), 'Temporary pending-comment membership could not be activated.' );
	$forum_qa_pending_comment_response = $forum_qa_rest(
		$forum_qa_pending_id,
		'POST',
		'/wp/v2/comments',
		array( 'post' => $forum_qa_post_id, 'content' => $forum_qa_marker . ' forbidden unverified member reply' )
	);
	$forum_qa_pending_comment_data = $forum_qa_rest_data( $forum_qa_pending_comment_response );
	$forum_qa_assert(
		403 === $forum_qa_rest_status( $forum_qa_pending_comment_response )
			&& 'cywater_forum_verification_required' === (string) ( $forum_qa_pending_comment_data['code'] ?? '' ),
		'An active member with an unverified email submitted a Forum reply.'
	);

	wp_set_current_user( $forum_qa_admin_id );
	$forum_qa_pending_comment_id = absint(
		wp_insert_comment(
			array(
				'comment_post_ID'      => $forum_qa_post_id,
				'comment_content'      => $forum_qa_pending_reply,
				'comment_approved'     => 0,
				'user_id'              => $forum_qa_author_id,
				'comment_author'       => 'CYWater Forum QA Author',
				'comment_author_email' => get_userdata( $forum_qa_author_id )->user_email,
			)
		)
	);
	if ( $forum_qa_pending_comment_id ) {
		$forum_qa_comments[] = $forum_qa_pending_comment_id;
	}
	$forum_qa_assert( $forum_qa_pending_comment_id > 0 && '0' === $forum_qa_comment_status( $forum_qa_pending_comment_id ), 'The manually held moderation fixture could not be created.' );
	$forum_qa_assert( ! user_can( $forum_qa_builtin_editor_id, 'edit_comment', $forum_qa_pending_comment_id ), 'Built-in Editor can edit an exact Forum reply through the core edit_comment capability.' );
	$forum_qa_assert( user_can( $forum_qa_moderator_id, 'edit_comment', $forum_qa_pending_comment_id ) && user_can( $forum_qa_admin_id, 'edit_comment', $forum_qa_pending_comment_id ), 'Forum staff cannot access legitimate comment moderation controls.' );

	$forum_qa_builtin_editor_approve = $forum_qa_rest(
		$forum_qa_builtin_editor_id,
		'POST',
		'/wp/v2/comments/' . $forum_qa_pending_comment_id,
		array( 'status' => 'approved' )
	);
	$forum_qa_assert( 403 === $forum_qa_rest_status( $forum_qa_builtin_editor_approve ), 'Built-in Editor changed a held Forum reply through the core REST comments controller.' );
	$forum_qa_assert( '0' === $forum_qa_comment_status( $forum_qa_pending_comment_id ), 'Rejected built-in Editor moderation changed the Forum reply state.' );

	$forum_qa_prior_query_user = get_current_user_id();
	wp_set_current_user( $forum_qa_builtin_editor_id );
	$forum_qa_forum_only_query = CYWater_Forum_Comments::exclude_forum_from_nonstaff_comment_query(
		array( 'post_type' => CYWater_Forum_Content::POST_TYPE )
	);
	$forum_qa_ordinary_query = CYWater_Forum_Comments::exclude_forum_from_nonstaff_comment_query(
		array( 'post_type' => 'post' )
	);
	wp_set_current_user( $forum_qa_prior_query_user );
	$forum_qa_assert( array( '__cywater_no_allowed_post_type__' ) === (array) ( $forum_qa_forum_only_query['post_type'] ?? array() ), 'Built-in Editor Forum-only moderation query was not closed by an impossible post type.' );
	$forum_qa_assert( array( 'post' ) === (array) ( $forum_qa_ordinary_query['post_type'] ?? array() ), 'Built-in Editor lost its legitimate non-Forum comment queue.' );

	$forum_qa_builtin_editor_queue = $forum_qa_rest(
		$forum_qa_builtin_editor_id,
		'GET',
		'/wp/v2/comments',
		array(
			'context'  => 'edit',
			'status'   => 'hold',
			'post'     => $forum_qa_post_id,
			'per_page' => 100,
		)
	);
	$forum_qa_builtin_editor_queue_data = $forum_qa_rest_data( $forum_qa_builtin_editor_queue );
	$forum_qa_builtin_editor_queue_ids  = array();
	foreach ( $forum_qa_builtin_editor_queue_data as $forum_qa_queue_item ) {
		if ( is_array( $forum_qa_queue_item ) && isset( $forum_qa_queue_item['id'] ) ) {
			$forum_qa_builtin_editor_queue_ids[] = absint( $forum_qa_queue_item['id'] );
		}
	}
	$forum_qa_assert( 200 === $forum_qa_rest_status( $forum_qa_builtin_editor_queue ) && ! in_array( $forum_qa_pending_comment_id, $forum_qa_builtin_editor_queue_ids, true ), 'Held Forum reply leaked into the built-in Editor REST moderation queue.' );

	$forum_qa_editor_approve = $forum_qa_rest(
		$forum_qa_editor_id,
		'POST',
		'/wp/v2/comments/' . $forum_qa_pending_comment_id,
		array( 'status' => 'approved' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_editor_approve ) >= 400, 'Content Editor approved a held forum reply.' );
	$forum_qa_assert( '0' === $forum_qa_comment_status( $forum_qa_pending_comment_id ), 'Rejected Content Editor approval changed the reply state.' );

	$forum_qa_assert( CYWater_Membership_Account_Security::is_verified( $forum_qa_subscriber_id ), 'Verified non-member fixture lost email verification.' );
	$forum_qa_subscriber_comment = $forum_qa_rest(
		$forum_qa_subscriber_id,
		'POST',
		'/wp/v2/comments',
		array( 'post' => $forum_qa_post_id, 'content' => $forum_qa_marker . ' forbidden subscriber reply' )
	);
	$forum_qa_subscriber_comment_data = $forum_qa_rest_data( $forum_qa_subscriber_comment );
	$forum_qa_assert(
		403 === $forum_qa_rest_status( $forum_qa_subscriber_comment ),
		'Subscriber without membership submitted a forum reply (' . $forum_qa_rest_diagnostic( $forum_qa_subscriber_comment ) . ').'
	);
	$forum_qa_assert( 'cywater_forum_membership_required' === (string) ( $forum_qa_subscriber_comment_data['code'] ?? '' ), 'Subscriber rejection did not use the forum membership boundary.' );

	if ( $forum_qa_can_reach_application_http ) {
		$forum_qa_archive_response = $forum_qa_http_get( (string) get_post_type_archive_link( CYWater_Forum_Content::POST_TYPE ) );
		$forum_qa_anonymous_archive_body = is_wp_error( $forum_qa_archive_response ) ? '' : (string) wp_remote_retrieve_body( $forum_qa_archive_response );
		$forum_qa_assert( ! is_wp_error( $forum_qa_archive_response ) && 200 === (int) wp_remote_retrieve_response_code( $forum_qa_archive_response ), 'Signed-out visitor could not open the public Forum registration gate.' );
		$forum_qa_assert( false !== strpos( $forum_qa_anonymous_archive_body, 'Create an account' ) && false !== strpos( $forum_qa_anonymous_archive_body, 'Sign in' ), 'Signed-out Forum registration gate is missing its account actions.' );
		$forum_qa_assert( false !== strpos( $forum_qa_anonymous_archive_body, '>Forum<' ), 'Signed-out primary navigation hides the Forum entry.' );
		$forum_qa_assert( false === strpos( $forum_qa_anonymous_archive_body, esc_html( $forum_qa_marker ) ) && false === strpos( $forum_qa_anonymous_archive_body, 'forum-card' ), 'Signed-out Forum gate leaked member writing or its card projection.' );
		$forum_qa_archive_response = $forum_qa_user_http_get( $forum_qa_subscriber_id, (string) get_post_type_archive_link( CYWater_Forum_Content::POST_TYPE ) );
		$forum_qa_archive_body     = is_wp_error( $forum_qa_archive_response ) ? '' : (string) wp_remote_retrieve_body( $forum_qa_archive_response );
		$forum_qa_assert( ! is_wp_error( $forum_qa_archive_response ) && 200 === (int) wp_remote_retrieve_response_code( $forum_qa_archive_response ), 'Registered account could not open the Forum archive.' );
		$forum_qa_assert( false !== strpos( $forum_qa_archive_body, esc_html( $forum_qa_marker ) ), 'Published QA forum article is absent from the registered-account archive.' );

		$forum_qa_single_response = $forum_qa_http_get( get_permalink( $forum_qa_post_id ) );
		$forum_qa_assert( ! is_wp_error( $forum_qa_single_response ) && 302 === (int) wp_remote_retrieve_response_code( $forum_qa_single_response ), 'Signed-out visitor was not redirected away from a Forum article.' );
		$forum_qa_single_response = $forum_qa_user_http_get( $forum_qa_subscriber_id, get_permalink( $forum_qa_post_id ) );
		$forum_qa_single_body     = is_wp_error( $forum_qa_single_response ) ? '' : (string) wp_remote_retrieve_body( $forum_qa_single_response );
		$forum_qa_assert( ! is_wp_error( $forum_qa_single_response ) && 200 === (int) wp_remote_retrieve_response_code( $forum_qa_single_response ), 'Registered account could not open a Forum article.' );
		$forum_qa_assert( false !== strpos( $forum_qa_single_body, esc_html( $forum_qa_marker ) ), 'Published article title/body is absent from its registered-account route.' );
		$forum_qa_assert( false !== strpos( $forum_qa_single_body, esc_html( $forum_qa_first_reply ) ), 'Approved first reply is absent for a registered account.' );
		$forum_qa_assert( false !== strpos( $forum_qa_single_body, esc_html( $forum_qa_second_reply ) ), 'Approved later reply is absent for a registered account.' );
		$forum_qa_assert( false !== strpos( $forum_qa_single_body, esc_html( $forum_qa_staff_reply ) ), 'Approved Community Moderator reply is absent for a registered account.' );
		$forum_qa_assert( false === strpos( $forum_qa_single_body, esc_html( $forum_qa_pending_reply ) ), 'Held reply leaked to a registered account.' );
		$forum_qa_assert( false === strpos( $forum_qa_single_body, 'name="comment"' ) && false !== strpos( $forum_qa_single_body, 'active CYWater Student' ), 'Registered non-member received a reply form instead of the membership gate.' );
	}

	$forum_qa_moderator_approve = $forum_qa_rest(
		$forum_qa_moderator_id,
		'POST',
		'/wp/v2/comments/' . $forum_qa_pending_comment_id,
		array( 'status' => 'approved' )
	);
	$forum_qa_assert( 200 === $forum_qa_rest_status( $forum_qa_moderator_approve ), 'Community Moderator could not approve an explicitly held Forum reply.' );
	$forum_qa_assert( '1' === $forum_qa_comment_status( $forum_qa_pending_comment_id ), 'Community Moderator approval was not persisted.' );

	$forum_qa_assert( pmpro_changeMembershipLevel( 0, $forum_qa_author_id ), 'Temporary author membership could not be cancelled for the lapse check.' );
	$forum_qa_lapsed_author = get_userdata( $forum_qa_author_id );
	$forum_qa_assert( $forum_qa_lapsed_author instanceof WP_User && in_array( CYWater_Forum_Roles::AUTHOR_ROLE, (array) $forum_qa_lapsed_author->roles, true ), 'Membership lapse destructively removed the historical Forum Author role.' );
	$forum_qa_assert( ! CYWater_Forum_Roles::can_submit( $forum_qa_author_id ), 'Lapsed member remains eligible to submit a Forum article.' );
	$forum_qa_assert( ! user_can( $forum_qa_author_id, 'edit_cyw_forum_posts' ) && ! user_can( $forum_qa_author_id, 'publish_cyw_forum_posts' ) && ! user_can( $forum_qa_author_id, 'edit_published_cyw_forum_posts' ), 'Lapsed member retained a request-time Forum participation primitive.' );
	$forum_qa_lapsed_create = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/cyw_forum_post',
		array( 'title' => $forum_qa_marker . ' forbidden lapsed-member article', 'status' => 'draft' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_lapsed_create ) >= 400, 'Lapsed member created a Forum draft through REST.' );
	$forum_qa_lapsed_workspace_create = CYWater_Forum_Workspace::save_article(
		$forum_qa_author_id,
		array(
			'title'   => $forum_qa_marker . ' forbidden lapsed workspace article',
			'content' => $forum_qa_marker . ' forbidden lapsed workspace body',
			'status'  => 'draft',
		)
	);
	$forum_qa_assert( is_wp_error( $forum_qa_lapsed_workspace_create ) && 'submission_blocked' === $forum_qa_lapsed_workspace_create->get_error_code(), 'A lapsed member bypassed eligibility through the front-end service.' );

	// The front-end service lets an author withdraw their own work, but does not
	// grant the backend lifecycle capability. Once in Trash, only staff may
	// restore the record or permanently delete it.
	$forum_qa_assert( ! user_can( $forum_qa_author_id, 'delete_post', $forum_qa_post_id ), 'Forum Author gained the backend published-post deletion capability.' );
	wp_set_current_user( $forum_qa_admin_id );
	$forum_qa_trashed = wp_trash_post( $forum_qa_post_id );
	$forum_qa_assert( $forum_qa_trashed instanceof WP_Post && 'trash' === get_post_status( $forum_qa_post_id ), 'Administrator could not take down the QA forum article.' );
	$forum_qa_assert( ! user_can( $forum_qa_author_id, 'delete_post', $forum_qa_post_id ), 'Forum Author has the capability used by WordPress to restore a taken-down article.' );
	wp_set_current_user( $forum_qa_author_id );
	$forum_qa_direct_restore = wp_untrash_post( $forum_qa_post_id );
	$forum_qa_assert( false === $forum_qa_direct_restore, 'Forum Author restored a taken-down article through the direct WordPress untrash API.' );
	$forum_qa_assert( 'trash' === get_post_status( $forum_qa_post_id ), 'Rejected direct Forum Author restore changed the article status.' );
	$forum_qa_restore_response = $forum_qa_rest(
		$forum_qa_author_id,
		'POST',
		'/wp/v2/cyw_forum_post/' . $forum_qa_post_id,
		array( 'status' => 'publish' )
	);
	$forum_qa_assert( $forum_qa_rest_status( $forum_qa_restore_response ) >= 400, 'Forum Author restored a taken-down published article through REST.' );
	$forum_qa_assert( 'trash' === get_post_status( $forum_qa_post_id ), 'Rejected Forum Author restore changed the article status.' );

	$forum_qa_assert( user_can( $forum_qa_moderator_id, 'delete_post', $forum_qa_post_id ), 'Community Moderator cannot access the staff-only restore path.' );
	wp_set_current_user( $forum_qa_moderator_id );
	$forum_qa_staff_restore = wp_untrash_post( $forum_qa_post_id );
	$forum_qa_assert( $forum_qa_staff_restore instanceof WP_Post && 'publish' === get_post_status( $forum_qa_post_id ), 'Community Moderator could not restore the prior published state.' );
} catch ( Throwable $forum_qa_exception ) {
	$forum_qa_failures[] = 'QA execution stopped unexpectedly (' . get_class( $forum_qa_exception ) . ').';
} finally {
	wp_set_current_user( $forum_qa_admin_id );

	foreach ( array_reverse( array_unique( array_map( 'absint', $forum_qa_comments ) ) ) as $forum_qa_comment_id ) {
		if ( get_comment( $forum_qa_comment_id ) && ! wp_delete_comment( $forum_qa_comment_id, true ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary comment could not be deleted.';
		}
	}

	foreach ( array_reverse( array_unique( array_map( 'absint', $forum_qa_posts ) ) ) as $forum_qa_cleanup_post_id ) {
		if ( get_post( $forum_qa_cleanup_post_id ) && ! wp_delete_post( $forum_qa_cleanup_post_id, true ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary post could not be deleted.';
		}
	}
	foreach ( array_unique( array_filter( $forum_qa_temp_files ) ) as $forum_qa_temp_file ) {
		if ( file_exists( $forum_qa_temp_file ) ) {
			wp_delete_file( $forum_qa_temp_file );
		}
		if ( file_exists( $forum_qa_temp_file ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary cover-image file could not be deleted.';
		}
	}
	foreach ( array_unique( array_filter( $forum_qa_protected_files ) ) as $forum_qa_protected_file ) {
		if ( file_exists( $forum_qa_protected_file ) ) {
			$forum_qa_cleanup_failures[] = 'A protected Forum cover remains after its temporary article was deleted.';
		}
	}

	foreach ( array_unique( array_map( 'absint', $forum_qa_users ) ) as $forum_qa_cleanup_user_id ) {
		CYWater_Forum_Endorsement::admin_revoke( $forum_qa_cleanup_user_id );
		pmpro_changeMembershipLevel( 0, $forum_qa_cleanup_user_id );
		// PMPro deliberately retains cancelled membership history. These rows
		// belong only to disposable QA users, so remove them by exact user id
		// before deleting the user and verify the table is clean below.
		if (
			false === $wpdb->delete(
				$wpdb->pmpro_memberships_users,
				array( 'user_id' => $forum_qa_cleanup_user_id ),
				array( '%d' )
			)
		) {
			$forum_qa_cleanup_failures[] = 'Temporary membership rows could not be deleted.';
		}
		if ( get_user_by( 'id', $forum_qa_cleanup_user_id ) && ! wp_delete_user( $forum_qa_cleanup_user_id ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary user could not be deleted.';
		}
	}
	$forum_qa_remaining_likes = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . CYWater_Forum_Community::table_name() . ' WHERE post_id IN (' . implode( ',', array_map( 'absint', array_unique( $forum_qa_posts ) ) ) . ') OR user_id IN (' . implode( ',', array_map( 'absint', array_unique( $forum_qa_users ) ) ) . ')' );
	if ( $forum_qa_remaining_likes > 0 ) {
		$forum_qa_cleanup_failures[] = 'Temporary Forum likes remain after post and user cleanup.';
	}

	foreach ( array_unique( array_map( 'absint', $forum_qa_comments ) ) as $forum_qa_comment_id ) {
		if ( get_comment( $forum_qa_comment_id ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary comment remains after cleanup.';
		}
	}
	foreach ( array_unique( array_map( 'absint', $forum_qa_posts ) ) as $forum_qa_cleanup_post_id ) {
		if ( get_post( $forum_qa_cleanup_post_id ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary post remains after cleanup.';
		}
	}
	foreach ( array_unique( array_map( 'absint', $forum_qa_users ) ) as $forum_qa_cleanup_user_id ) {
		if ( get_user_by( 'id', $forum_qa_cleanup_user_id ) ) {
			$forum_qa_cleanup_failures[] = 'A temporary user remains after cleanup.';
		}
		$forum_qa_membership_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->pmpro_memberships_users} WHERE user_id = %d", $forum_qa_cleanup_user_id )
		);
		if ( $forum_qa_membership_rows > 0 ) {
			$forum_qa_cleanup_failures[] = 'Temporary membership rows remain after user cleanup.';
		}
	}

	$forum_qa_remaining_users = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_login LIKE 'cyw\\_forum\\_qa\\_%'" );
	if ( $forum_qa_remaining_users > 0 ) {
		$forum_qa_cleanup_failures[] = 'A marker-matched temporary Forum user remains after cleanup.';
	}
	if ( ! empty( $forum_qa_marker ) ) {
		$forum_qa_like_marker = '%' . $wpdb->esc_like( $forum_qa_marker ) . '%';
		$forum_qa_remaining_posts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s",
				$forum_qa_like_marker,
				$forum_qa_like_marker,
				$forum_qa_like_marker
			)
		);
		$forum_qa_remaining_comments = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_content LIKE %s", $forum_qa_like_marker )
		);
		if ( $forum_qa_remaining_posts > 0 ) {
			$forum_qa_cleanup_failures[] = 'A marker-matched temporary Forum post or revision remains after cleanup.';
		}
		if ( $forum_qa_remaining_comments > 0 ) {
			$forum_qa_cleanup_failures[] = 'A marker-matched temporary Forum comment remains after cleanup.';
		}
	}

	CYWater_Operations_Audit::clear_context();
	if ( false === CYWater_Operations_Audit::delete_context_rows( $forum_qa_audit_reason ) ) {
		$forum_qa_cleanup_failures[] = 'Temporary Operations audit rows could not be deleted.';
	}
	$forum_qa_remaining_audit = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . CYWater_Operations_Audit::table_name() . ' WHERE reason = %s',
			sanitize_key( $forum_qa_audit_reason )
		)
	);
	if ( $forum_qa_remaining_audit > 0 ) {
		$forum_qa_cleanup_failures[] = 'Temporary Operations audit rows remain after cleanup.';
	}

	remove_filter( 'pre_wp_mail', $forum_qa_mail_filter, 999 );
	wp_set_current_user( $forum_qa_original_user_id );
}

if ( $forum_qa_cleanup_failures ) {
	$forum_qa_failures = array_merge( $forum_qa_failures, $forum_qa_cleanup_failures );
}

if ( $forum_qa_failures ) {
	foreach ( array_values( array_unique( $forum_qa_failures ) ) as $forum_qa_failure ) {
		WP_CLI::warning( $forum_qa_failure );
	}
	WP_CLI::error(
		sprintf(
			'CYWater Forum staging QA failed after %d passing assertions; cleanup was attempted for every fixture.',
			$forum_qa_passes
		)
	);
}

WP_CLI::success(
	sprintf(
		'CYWater Forum staging QA passed %1$d assertions. %2$d outgoing messages were intercepted, and all temporary users, posts, revisions, comments, membership rows, and Operations audit rows were removed.',
		$forum_qa_passes,
		count( $forum_qa_intercepted_mail )
	)
);
