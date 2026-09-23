<?php
/** Self-cleaning Best Paper integration regression. Staging only; no mail. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
if ( 'staging' !== wp_get_environment_type() || ! class_exists( 'CYWater_Best_Paper' ) ) {
	WP_CLI::error( 'Best Paper QA requires the staging runtime and active plugin.' );
}

global $wpdb;
$checks = 0;
$users = array();
$posts = array();
$files = array();
$renders = array();
$transients = array();
$original_get = $_GET;
$original_user = get_current_user_id();
$marker = 'cyw_bp_qa_' . strtolower( wp_generate_password( 10, false, false ) );
$mail_guard = static function () { return true; };
add_filter( 'pre_wp_mail', $mail_guard, PHP_INT_MAX );
$assert = static function ( $condition, $message ) use ( &$checks ) {
	++$checks;
	if ( ! $condition ) { throw new RuntimeException( $message ); }
};
$make_user = static function ( $suffix, $role = 'subscriber' ) use ( &$users, $marker ) {
	$id = wp_insert_user( array( 'user_login' => $marker . $suffix, 'user_pass' => wp_generate_password( 32, true, true ), 'user_email' => $marker . $suffix . '@example.invalid', 'role' => $role, 'first_name' => 'QA', 'last_name' => $suffix ) );
	if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
	$users[] = $id;
	return (int) $id;
};
$make_award = static function () use ( &$posts, $marker ) {
	$id = wp_insert_post( array( 'post_type' => 'cyw_award', 'post_status' => 'publish', 'post_title' => $marker . count( $posts ), 'post_content' => '<p>Temporary QA only.</p>' ), true );
	if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
	$posts[] = $id;
	return (int) $id;
};
$field_value = static function ( $html, $name ) {
	return preg_match( '/<input\b[^>]*\bname="' . preg_quote( $name, '/' ) . '"[^>]*\bvalue="([^"]*)"/', $html, $match ) ? html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ) : null;
};
$in_main_loop = static function ( $award_id, $callback ) {
	// Exercise content/excerpt filters in the same singular main-loop context
	// as the theme, then restore every global touched by WP_Query::the_post().
	$keys = array( 'wp_query', 'wp_the_query', 'post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' );
	$before = array();
	foreach ( $keys as $key ) { $before[$key] = array( array_key_exists( $key, $GLOBALS ), $GLOBALS[$key] ?? null ); }
	try {
		$query = new WP_Query( array( 'post_type' => 'cyw_award', 'p' => $award_id, 'post_status' => 'publish' ) );
		$GLOBALS['wp_query'] = $query;
		$GLOBALS['wp_the_query'] = $query;
		$query->the_post();
		return $callback();
	} finally {
		foreach ( $before as $key => $value ) {
			if ( $value[0] ) { $GLOBALS[$key] = $value[1]; } else { unset( $GLOBALS[$key] ); }
		}
	}
};
$cache_bypass_count = 0;
$cache_observer = static function ( $reason ) use ( &$cache_bypass_count ) {
	if ( 'CYWater Best Paper application state' === $reason ) { ++$cache_bypass_count; }
};
add_action( 'litespeed_control_set_nocache', $cache_observer );

try {
	CYWater_Best_Paper::install();
	$manager = $make_user( 'manager', 'administrator' );
	$applicant = $make_user( 'applicant' );
	$other = $make_user( 'other' );
	$reviewer = $make_user( 'reviewer' );
	$reviewer2 = $make_user( 'reviewer2' );
	wp_set_current_user( $manager );
	$award = $make_award();
	$config = CYWater_Best_Paper::config( $award );
	$assert( empty( $config['enabled'] ), 'A new Award must not accept applications by default.' );
	$in_main_loop( $award, array( CYWater_Best_Paper_Public::class, 'protect_personal_response' ) );
	$assert( 0 === $cache_bypass_count, 'Ordinary non-workflow Awards must retain their cache behavior.' );
	$assert( CYWater_Best_Paper::can_manage(), 'Administrator must manage the workflow.' );
	wp_set_current_user( $applicant );
	$assert( ! CYWater_Best_Paper::can_manage(), 'Applicant must not manage the workflow.' );
	$assert( is_wp_error( CYWater_Best_Paper::save_config( $award, array( 'enabled' => true ) ) ), 'Applicant configuration bypass.' );
	wp_set_current_user( $manager );
	$config = array_merge( $config, array( 'enabled' => true, 'status' => 'applications', 'open_at' => wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ), 'close_at' => wp_date( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ), 'reviewer_ids' => array( $reviewer, $reviewer2 ), 'chair_id' => $reviewer ) );
	$saved = CYWater_Best_Paper::save_config( $award, $config );
	$assert( ! is_wp_error( $saved ), 'Valid cycle configuration was rejected.' );
	wp_set_current_user( 0 );
	$in_main_loop( $award, array( CYWater_Best_Paper_Public::class, 'protect_personal_response' ) );
	$assert( 1 === $cache_bypass_count && defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE, 'Anonymous workflow pages can cache stale open/closed state.' );
	$renders['logged-out'] = CYWater_Best_Paper_Public::render( $award );
	$assert( str_contains( $renders['logged-out'], 'Sign in to apply' ) && ! str_contains( $renders['logged-out'], 'name="dob"' ), 'Anonymous view exposes a form instead of the login gate.' );
	wp_set_current_user( $applicant );
	update_user_meta( $applicant, 'cyw_institution_name', 'Account QA University' );
	$account_meta = get_user_meta( $applicant );
	$renders['form'] = CYWater_Best_Paper_Public::render( $award );
	$assert( 'QA' === $field_value( $renders['form'], 'first_name' ) && 'applicant' === $field_value( $renders['form'], 'last_name' ), 'New application did not use existing account name parts.' );
	$assert( $marker . 'applicant@example.invalid' === $field_value( $renders['form'], 'email' ) && 'Account QA University' === $field_value( $renders['form'], 'institution' ), 'New application did not use current account email and canonical institution.' );
	$assert( '' === $field_value( $renders['form'], 'dob' ), 'New application inferred a birth date.' );
	$assert( $account_meta === get_user_meta( $applicant ), 'Rendering the application mutated account profile metadata.' );
	wp_set_current_user( $manager );
	$assert( CYWater_Best_Paper::is_reviewer( $award, $reviewer ), 'Configured reviewer was not recognized.' );
	$assert( ! CYWater_Best_Paper::is_reviewer( $award, $other ), 'Unassigned account is a reviewer.' );
	$directory = CYWater_Best_Paper::private_directory();
	$assert( ! is_wp_error( $directory ) && ! str_contains( $directory, '/public_html/' ), 'Uploads must stay outside the entire public web root.' );
	$pdf = wp_tempnam( $marker . '.pdf' );
	$files[] = $pdf;
	file_put_contents( $pdf, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF" );
	$upload = array( 'name' => 'test-paper.pdf', 'tmp_name' => $pdf, 'error' => UPLOAD_ERR_OK, 'size' => filesize( $pdf ) );
	$uploads = array( 'paper' => $upload, 'cv' => array_merge( $upload, array( 'name' => 'test-cv.pdf' ) ) );
	$today = new DateTimeImmutable( 'today', wp_timezone() );
	$input = array( 'first_name' => 'QA', 'last_name' => 'Applicant', 'email' => $marker . '@example.invalid', 'institution' => 'QA University', 'title' => 'QA Water Science Paper', 'journal' => 'QA Journal', 'doi' => 'https://doi.org/10.1234/QA-A', 'dob' => $today->modify( '-35 years' )->format( 'Y-m-d' ), 'online_date' => $today->modify( '-1 day' )->format( 'Y-m-d' ), 'no_prior_award' => 'yes', 'eligibility' => 'yes' );
	wp_set_current_user( $applicant );
	$assert( is_wp_error( CYWater_Best_Paper::submit( $award, array_merge( $input, array( 'dob' => $today->modify( '-36 years' )->format( 'Y-m-d' ) ) ), $uploads ) ), 'Age 36 must be rejected.' );
	$assert( is_wp_error( CYWater_Best_Paper::submit( $award, array_merge( $input, array( 'online_date' => $today->modify( '-13 months' )->format( 'Y-m-d' ) ) ), $uploads ) ), 'Out-of-window paper was accepted.' );
	$assert( is_wp_error( CYWater_Best_Paper::submit( $award, array_merge( $input, array( 'no_prior_award' => '' ) ), $uploads ) ), 'Missing previous-winner declaration was accepted.' );
	$assert( is_wp_error( CYWater_Best_Paper::submit( $award, $input, $uploads, $other ) ), 'Application impersonation was accepted.' );
	$bad_uploads = $uploads; $bad_uploads['paper']['name'] = 'payload.php';
	$assert( is_wp_error( CYWater_Best_Paper::submit( $award, $input, $bad_uploads ) ), 'Non-PDF extension was accepted.' );
	$id = CYWater_Best_Paper::submit( $award, $input, $uploads );
	$assert( ! is_wp_error( $id ) && $id > 0, 'Valid age-35 application with PDFs failed.' );
	$app = CYWater_Best_Paper::get_application( $id );
	$assert( $account_meta === get_user_meta( $applicant ), 'Application submission changed account profile metadata.' );
	update_user_meta( $applicant, 'cyw_institution_name', 'Later Account University' );
	$account_meta = get_user_meta( $applicant );
	$assert( 35 === $app['record']['age_at_submission'] && '10.1234/qa-a' === $app['record']['doi'], 'Age or DOI normalization is incorrect.' );
	$assert( count( $app['files'] ) === 2 && is_file( CYWater_Best_Paper::file_path( $app, 'paper' ) ), 'Saved PDF is not available to its owner.' );
	$assert( str_contains( CYWater_Best_Paper::download_url( $id, 'paper' ), '_wpnonce=' ), 'Protected download is missing its nonce.' );
	$html = CYWater_Best_Paper_Public::render( $award );
	$renders['confirmation'] = $html;
	$assert( 'QA University' === $field_value( $html, 'institution' ) && $input['email'] === $field_value( $html, 'email' ), 'Later account changes replaced saved application details.' );
	$assert( str_contains( $html, 'test-paper.pdf' ) && str_contains( $html, 'test-cv.pdf' ) && str_contains( $html, 'Download' ), 'Applicant cannot review their saved files.' );
	$notice_token = wp_generate_password( 24, false, false );
	$notice_key = 'cyw_bp_flash_' . $applicant . '_' . $award . '_' . $notice_token;
	$transients[] = $notice_key;
	$_GET['bp_notice'] = $notice_token;
	$flash = array( 'success' => false, 'message' => 'QA retry notice', 'values' => array( 'first_name' => 'Retry name', 'institution' => 'Retry University', 'email' => 'retry@example.invalid', 'dob' => '2000-01-01' ) );
	set_transient( $notice_key, $flash, MINUTE_IN_SECONDS );
	// A signed-in participant may also own an application. The public layout-only
	// preview must show account defaults, not their saved record or retry flash.
	$interactive_own = CYWater_Best_Paper_Public::render( $award, true );
	$assert( 'Later Account University' === $field_value( $interactive_own, 'institution' ) && '' === $field_value( $interactive_own, 'title' ) && '' === $field_value( $interactive_own, 'dob' ), 'Interactive preview used a saved application or failed-submission values instead of account defaults.' );
	$assert( ! str_contains( $interactive_own, 'test-paper.pdf' ) && ! str_contains( $interactive_own, 'test-cv.pdf' ) && ! str_contains( $interactive_own, 'Your submitted files' ), 'Interactive preview exposed an existing application document.' );
	$assert( $flash === get_transient( $notice_key ) && $app === CYWater_Best_Paper::get_application( $id ), 'Interactive preview consumed a pending notice or changed the saved application.' );
	$excerpt_shortcode_guard = static function ( $excerpt ) use ( $award, $assert ) {
		$assert( '' === CYWater_Best_Paper_Public::shortcode( array( 'award_id' => $award ) ), 'An excerpt rendered the application shortcode.' );
		return $excerpt;
	};
	add_filter( 'get_the_excerpt', $excerpt_shortcode_guard, 9 );
	try {
		$in_main_loop( $award, static function () use ( $award, $assert, $field_value, $notice_key, $flash, $input ) {
			$assert( is_singular( 'cyw_award' ) && in_the_loop() && is_main_query(), 'Excerpt QA did not establish the singular main loop.' );
			$excerpt = get_the_excerpt( $award );
			$assert( ! str_contains( $excerpt, 'Apply for this award' ) && ! str_contains( $excerpt, 'Your application' ) && ! str_contains( $excerpt, 'QA retry notice' ), 'An automatic excerpt contains private workflow output.' );
			$assert( $flash === get_transient( $notice_key ), 'Automatic excerpt consumed the application notice before the body.' );
			$body = apply_filters( 'the_content', get_post_field( 'post_content', $award ) );
			$assert( str_contains( $body, 'QA retry notice' ) && false === get_transient( $notice_key ), 'The full article did not receive and consume its one-time notice.' );
			$assert( 'Retry name' === $field_value( $body, 'first_name' ) && 'Retry University' === $field_value( $body, 'institution' ) && 'retry@example.invalid' === $field_value( $body, 'email' ), 'Failed submission values did not take priority over saved application/account values.' );
			$assert( $input['dob'] === $field_value( $body, 'dob' ), 'Retry notice replaced the immutable first-submission birth date.' );
		} );
	} finally {
		remove_filter( 'get_the_excerpt', $excerpt_shortcode_guard, 9 );
		unset( $_GET['bp_notice'] );
	}
	$assert( is_wp_error( CYWater_Best_Paper::staff_update( $id, array( 'status' => 'eligible' ) ) ), 'Applicant changed eligibility.' );
	$assert( is_wp_error( CYWater_Best_Paper::submit( $award, array_merge( $input, array( 'dob' => $today->modify( '-34 years' )->format( 'Y-m-d' ) ) ) ) ), 'DOB snapshot could be silently changed.' );
	$update = CYWater_Best_Paper::submit( $award, array_merge( $input, array( 'institution' => 'Updated QA University' ) ) );
	$updated = CYWater_Best_Paper::get_application( $id );
	$assert( $id === $update && $app['created_at'] === $updated['created_at'] && 2 === $updated['record']['version'], 'Update duplicated the application or changed first submission.' );
	$assert( $app['files'] === $updated['files'] && count( $updated['record']['history'] ) === 1, 'Update lost documents or audit history.' );
	$assert( $account_meta === get_user_meta( $applicant ), 'Rendering/retrying/updating an application changed account profile metadata.' );
	$assert( array() === CYWater_Best_Paper::reviews( $id ), 'Applicant can see private reviews.' );
	wp_set_current_user( $other );
	$assert( null === CYWater_Best_Paper::get_application( $id ) && is_wp_error( CYWater_Best_Paper::file_path( $id, 'paper' ) ), 'Another applicant can read the private record/file.' );
	$assert( null === CYWater_Best_Paper::own_application( $award, $applicant ), 'Owner override leaked another application.' );
	$duplicate = CYWater_Best_Paper::submit( $award, array_merge( $input, array( 'doi' => 'DOI:10.1234/qa-a', 'title' => 'Different title same DOI' ) ), $uploads );
	$assert( is_wp_error( $duplicate ) && 'cywater_bp_duplicate' === $duplicate->get_error_code(), 'Duplicate normalized DOI was accepted.' );
	$id2 = CYWater_Best_Paper::submit( $award, array_merge( $input, array( 'doi' => '10.1234/qa-b', 'title' => 'QA Second Paper' ) ), $uploads );
	$assert( ! is_wp_error( $id2 ), 'Second independent paper failed.' );
	wp_set_current_user( 0 );
	$assert( null === CYWater_Best_Paper::get_application( $id ) && is_wp_error( CYWater_Best_Paper::file_path( $id, 'cv' ) ), 'Anonymous private access.' );
	wp_set_current_user( $manager );
	$assert( ! is_wp_error( CYWater_Best_Paper::staff_update( $id, array( 'status' => 'eligible', 'note' => 'Historical winner check completed for QA.' ) ) ), 'Staff eligibility update failed.' );
	$assert( ! is_wp_error( CYWater_Best_Paper::staff_update( $id2, array( 'status' => 'eligible' ) ) ), 'Second eligibility update failed.' );
	CYWater_Best_Paper::staff_update( $id2, array( 'status' => 'needs_changes' ) );
	$package = CYWater_Best_Paper_Admin::build_package( $award, 'eligible' );
	$assert( is_string( $package ) && is_file( $package ), 'Eligible-only ZIP package failed.' );
	$files[] = $package;
	$zip = new ZipArchive(); $zip->open( $package );
	$manifest = json_decode( $zip->getFromName( 'manifest.json' ), true );
	$assert( 1 === $manifest['application_count'] && 'eligible' === $manifest['scope'] && $id === $manifest['applications'][0]['application_id'], 'ZIP scope included unverified applications.' );
	$entry = $manifest['applications'][0];
	$record = $zip->getFromName( $entry['record'] );
	$assert( ! str_contains( $record, '"dob"' ) && ! str_contains( $record, '"stored"' ) && ! str_contains( $record, $directory ), 'Package contains unnecessary birthdate or server path.' );
	$assert( hash( 'sha256', $zip->getFromName( $entry['files']['paper']['archive_path'] ) ) === $app['files']['paper']['sha256'], 'ZIP changed uploaded paper bytes.' );
	$assert( false !== $zip->getFromName( 'applications.csv' ) && false !== $zip->getFromName( 'README.txt' ), 'ZIP index or guidance missing.' );
	$zip->close();
	$csv_method = new ReflectionMethod( CYWater_Best_Paper_Admin::class, 'csv_cell' ); $csv_method->setAccessible( true );
	$assert( "'=SUM(1,2)" === $csv_method->invoke( null, '=SUM(1,2)' ), 'Applicant text can execute as a spreadsheet formula.' );
	CYWater_Best_Paper::staff_update( $id2, array( 'status' => 'eligible' ) );
	$assert( is_wp_error( CYWater_Best_Paper::save_config( $award, array_merge( $config, array( 'close_at' => wp_date( 'Y-m-d H:i:s', time() + 20 * DAY_IN_SECONDS ) ) ) ) ), 'Eligibility window changed after intake.' );
	$config['status'] = 'review';
	$assert( ! is_wp_error( CYWater_Best_Paper::save_config( $award, $config ) ), 'Review phase failed.' );
	foreach ( array( $id, $id2 ) as $app_id ) {
		$assert( ! is_wp_error( CYWater_Best_Paper::assign( $app_id, $reviewer ) ), 'Review assignment failed.' );
		$assert( ! is_wp_error( CYWater_Best_Paper::assign( $app_id, $reviewer2 ) ), 'Second reviewer assignment failed.' );
	}
	$assert( is_wp_error( CYWater_Best_Paper::assign( $id, $other ) ), 'Unconfigured reviewer was assigned.' );
	wp_set_current_user( $applicant );
	$assert( is_wp_error( CYWater_Best_Paper::submit( $award, $input ) ), 'Intake continued during review.' );
	wp_set_current_user( $reviewer );
	$assert( is_wp_error( CYWater_Best_Paper_Admin::build_package( $award ) ), 'Reviewer can export unrestricted participant files.' );
	$assert( null !== CYWater_Best_Paper::get_application( $id ), 'Reviewer lacks eligible materials.' );
	$_GET['award_id'] = $award; $_GET['application_id'] = $id;
	ob_start(); CYWater_Best_Paper_Admin::page(); $renders['reviewer'] = ob_get_clean();
	$assert( str_contains( $renders['reviewer'], 'Save my review' ) && ! str_contains( $renders['reviewer'], 'Save annual workflow settings' ), 'Reviewer UI has incorrect controls.' );
	$assert( is_wp_error( CYWater_Best_Paper::review( $id, '', 'Missing score' ) ) && is_wp_error( CYWater_Best_Paper::review( $id, 11, 'Out of range' ) ), 'Missing/invalid score was accepted.' );
	$assert( ! is_wp_error( CYWater_Best_Paper::review( $id, 6, 'First assessment' ) ) && ! is_wp_error( CYWater_Best_Paper::review( $id2, 8, 'Second assessment' ) ), 'Reviewer scores failed.' );
	$assert( count( CYWater_Best_Paper::reviews( $id ) ) === 1 && array() === CYWater_Best_Paper::score_summary( $award ), 'Independent review leaked peers or aggregate scores.' );
	wp_set_current_user( $reviewer2 );
	CYWater_Best_Paper::review( $id, 2, 'Independent assessment' );
	CYWater_Best_Paper::review( $id2, 4, 'Independent assessment' );
	wp_set_current_user( $manager );
	$scores = CYWater_Best_Paper::score_summary( $award );
	$assert( 2 === $scores[$id]['count'] && abs( $scores[$id]['mean'] - 4 ) < 0.000001, 'Raw score summary is incorrect.' );
	$assert( abs( $scores[$id]['z_mean'] + 1 / sqrt(2) ) < 0.000001 && abs( $scores[$id2]['z_mean'] - 1 / sqrt(2) ) < 0.000001, 'Sample-SD z-score is incorrect.' );
	wp_set_current_user( $reviewer2 );
	CYWater_Best_Paper::review( $id2, 2, 'Equal scores' );
	wp_set_current_user( $manager );
	$assert( null === CYWater_Best_Paper::score_summary( $award )[$id]['z_mean'], 'Zero variance was presented as a valid z-score.' );
	wp_set_current_user( $reviewer2 );
	$assert( ! is_wp_error( CYWater_Best_Paper::review( $id, '', 'Conflict of interest', true ) ), 'Recusal failed.' );
	$assert( null === CYWater_Best_Paper::get_application( $id ) && is_wp_error( CYWater_Best_Paper::file_path( $id, 'paper' ) ), 'Recused reviewer still has paper access.' );
	wp_set_current_user( $manager );
	$config['status'] = 'decision';
	CYWater_Best_Paper::save_config( $award, $config );
	$assert( is_wp_error( CYWater_Best_Paper::save_config( $award, array_merge( $config, array( 'status' => 'announced', 'confirm_results' => 1 ) ) ) ), 'Announced without a Best Paper selection.' );
	$assert( ! is_wp_error( CYWater_Best_Paper::staff_update( $id, array( 'decision' => 'best' ) ) ), 'Explicit Best Paper decision failed.' );
	$assert( is_wp_error( CYWater_Best_Paper::staff_update( $id2, array( 'decision' => 'best' ) ) ), 'Two Best Paper selections were accepted.' );
	$assert( ! is_wp_error( CYWater_Best_Paper::staff_update( $id2, array( 'decision' => 'outstanding' ) ) ), 'Outstanding selection failed.' );
	$assert( is_wp_error( CYWater_Best_Paper::save_config( $award, array_merge( $config, array( 'status' => 'announced' ) ) ) ), 'Missing final confirmation accepted.' );
	$assert( ! is_wp_error( CYWater_Best_Paper::save_config( $award, array_merge( $config, array( 'status' => 'announced', 'confirm_results' => 1 ) ) ) ), 'Confirmed final outcome failed.' );
	$award2 = $make_award();
	$config['status'] = 'applications';
	CYWater_Best_Paper::save_config( $award2, $config );
	foreach ( array( $applicant, $other ) as $winner ) {
		wp_set_current_user( $winner );
		$reapply = CYWater_Best_Paper::submit( $award2, $input, $uploads );
		$assert( is_wp_error( $reapply ) && 'cywater_bp_prior_award' === $reapply->get_error_code(), 'Prior Best/Outstanding winner could reapply.' );
	}
	wp_set_current_user( $manager );
	$_GET['award_id'] = $award; unset( $_GET['application_id'] );
	ob_start(); CYWater_Best_Paper_Admin::page(); $renders['admin'] = ob_get_clean();
	$draft = $make_award();
	wp_update_post( array( 'ID' => $draft, 'post_status' => 'draft' ) );
	$assert( ! is_wp_error( CYWater_Best_Paper::save_config( $draft, array( 'enabled' => true, 'status' => 'draft' ) ) ), 'Date-free closed administrator preview failed.' );
	$renders['preview'] = CYWater_Best_Paper_Public::render( $draft );
	$assert( str_contains( $renders['preview'], 'disabled' ) && str_contains( $renders['preview'], 'Planned timeline' ), 'Draft preview is not visibly disabled.' );
	$assert( is_wp_error( CYWater_Best_Paper::submit( $draft, $input, $uploads ) ), 'Draft preview accepted an application.' );
	update_user_meta( $manager, 'cyw_institution_name', 'Administrator QA University' );
	$preview_meta = get_user_meta( $manager );
	$preview_config = CYWater_Best_Paper::config( $draft );
	$preview_applications = CYWater_Best_Paper::applications( $award );
	$renders['interactive-preview'] = CYWater_Best_Paper_Public::render( $draft, true );
	$interactive = $renders['interactive-preview'];
	$assert( 'QA' === $field_value( $interactive, 'first_name' ) && 'manager' === $field_value( $interactive, 'last_name' ) && $marker . 'manager@example.invalid' === $field_value( $interactive, 'email' ) && 'Administrator QA University' === $field_value( $interactive, 'institution' ), 'Interactive administrator preview did not prefill current account information.' );
	$assert( '' === $field_value( $interactive, 'dob' ), 'Interactive preview inferred a date of birth.' );
	$assert( ! preg_match( '/<form\b/i', $interactive ) && ! str_contains( $interactive, 'cywater_best_paper_nonce' ) && ! str_contains( $interactive, 'name="action"' ) && ! str_contains( $interactive, 'name="award_id"' ), 'Interactive preview contains a real submission form, nonce or action target.' );
	$assert( ! preg_match( '/<fieldset\b[^>]*\bdisabled/i', $interactive ) && preg_match( '/<input\b(?=[^>]*\bname="first_name")(?=[^>]*\btype="text")(?![^>]*\bdisabled)[^>]*>/i', $interactive ) && preg_match( '/<input\b(?=[^>]*\btype="file")(?![^>]*\bdisabled)[^>]*>/i', $interactive ), 'Interactive preview fields or file picker remain disabled.' );
	$assert( preg_match( '/<button\b(?=[^>]*\btype="button")(?=[^>]*\bdisabled)[^>]*>/i', $interactive ) && ! preg_match( '/<button\b[^>]*\btype="submit"/i', $interactive ), 'Interactive preview retains a working submit control.' );
	$assert( ! str_contains( $interactive, 'cywater_best_paper_download' ) && ! str_contains( $interactive, 'Your submitted files' ), 'Interactive preview includes protected document links.' );
	$interactive_shortcode = do_shortcode( '[cywater_best_paper_preview award_id="' . $draft . '"]' );
	$assert( str_contains( $interactive_shortcode, 'Applicant information' ) && ! preg_match( '/<form\b/i', $interactive_shortcode ), 'Standalone preview shortcode did not use the non-submitting preview renderer.' );
	$assert( $preview_meta === get_user_meta( $manager ) && $preview_config === CYWater_Best_Paper::config( $draft ) && $preview_applications === CYWater_Best_Paper::applications( $award ), 'Standalone preview changed profile, cycle configuration or applications.' );
	wp_set_current_user( $applicant );
	$subscriber_preview = CYWater_Best_Paper_Public::render( $award, true );
	$assert( str_contains( $subscriber_preview, 'Applicant information' ) && str_contains( do_shortcode( '[cywater_best_paper_preview award_id="' . $award . '"]' ), 'Applicant information' ), 'Signed-in participant cannot view the published-award preview.' );
	$assert( 'Later Account University' === $field_value( $subscriber_preview, 'institution' ) && 'applicant' === $field_value( $subscriber_preview, 'last_name' ) && '' === $field_value( $subscriber_preview, 'title' ), 'Signed-in preview did not use only the current account defaults.' );
	$assert( ! str_contains( $subscriber_preview, 'test-paper.pdf' ) && ! str_contains( $subscriber_preview, 'cywater_best_paper_download' ), 'Signed-in preview leaked saved files.' );
	wp_set_current_user( 0 );
	$anonymous_preview = CYWater_Best_Paper_Public::render( $award, true );
	$renders['interactive-preview-anonymous'] = $anonymous_preview;
	$assert( str_contains( $anonymous_preview, 'Applicant information' ) && str_contains( do_shortcode( '[cywater_best_paper_preview award_id="' . $award . '"]' ), 'Applicant information' ), 'Anonymous visitor cannot view the published-award preview.' );
	foreach ( array( 'first_name', 'last_name', 'email', 'institution', 'dob' ) as $name ) {
		$assert( '' === $field_value( $anonymous_preview, $name ), 'Anonymous preview exposed an account identity field.' );
	}
	$assert( ! preg_match( '/<form\b|<fieldset\b[^>]*\bdisabled/i', $anonymous_preview ) && ! str_contains( $anonymous_preview, 'cywater_best_paper_nonce' ) && preg_match( '/<button\b(?=[^>]*\btype="button")(?=[^>]*\bdisabled)[^>]*>/i', $anonymous_preview ), 'Anonymous preview is not editable and non-submitting.' );
	$assert( ! str_contains( $anonymous_preview, 'test-paper.pdf' ) && ! str_contains( $anonymous_preview, 'cywater_best_paper_download' ) && ! str_contains( $anonymous_preview, 'Your submitted files' ), 'Anonymous preview exposed a saved application or file.' );
	$assert( '' === CYWater_Best_Paper_Public::render( $draft, true ) && '' === do_shortcode( '[cywater_best_paper_preview award_id="' . $draft . '"]' ), 'Anonymous preview bypassed unpublished Award protection.' );
	$assert( '' === CYWater_Best_Paper_Public::render( $draft ), 'Anonymous user can view an unpublished award module.' );
	file_put_contents( '/tmp/cywater-best-paper-render.json', wp_json_encode( $renders ) );
	WP_CLI::log( 'Best Paper QA passed ' . $checks . ' assertions.' );
} finally {
	if ( isset( $manager ) ) { wp_set_current_user( $manager ); }
	foreach ( $posts as $id ) {
		foreach ( CYWater_Best_Paper::applications( $id ) as $app ) {
			$versions = array_merge( array( array( 'files' => $app['files'] ) ), $app['record']['history'] ?? array() );
			foreach ( $versions as $version ) {
				foreach ( ( $version['files'] ?? array() ) as $file ) {
					if ( isset( $directory ) && is_string( $directory ) && preg_match( '/^[a-f0-9-]{36}\.pdf$/D', $file['stored'] ?? '' ) ) { $files[] = $directory . '/' . $file['stored']; }
				}
			}
		}
		$app_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}cyw_bp_applications WHERE award_id = %d", $id ) );
		foreach ( $app_ids as $app_id ) {
			foreach ( ( _get_cron_array() ?: array() ) as $timestamp => $hooks ) { foreach ( ( $hooks['cywater_bp_receipt'] ?? array() ) as $event ) { if ( (int) ( $event['args'][0] ?? 0 ) === (int) $app_id ) { wp_unschedule_event( $timestamp, 'cywater_bp_receipt', $event['args'] ); } } }
			$wpdb->delete( $wpdb->prefix . 'cyw_bp_reviews', array( 'application_id' => $app_id ), array( '%d' ) );
		}
		$wpdb->delete( $wpdb->prefix . 'cyw_bp_applications', array( 'award_id' => $id ), array( '%d' ) );
		wp_delete_post( $id, true );
	}
	foreach ( $files as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
	foreach ( $transients as $key ) { delete_transient( $key ); }
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) { wp_delete_user( $id ); }
	wp_set_current_user( $original_user );
	$_GET = $original_get;
	remove_filter( 'pre_wp_mail', $mail_guard, PHP_INT_MAX );
	remove_action( 'litespeed_control_set_nocache', $cache_observer );
	WP_CLI::log( 'Temporary Best Paper fixtures removed.' );
}
