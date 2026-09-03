<?php
/**
 * Self-cleaning staging QA for the CYWater editorial workspace.
 *
 * Run only on staging:
 * wp eval-file scripts/cywater-staging-editor-qa.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

if ( 'staging' !== wp_get_environment_type() ) {
	WP_CLI::error( 'CYWater editor QA is restricted to staging.' );
}

$checks      = 0;
$created     = 0;
$created_ids = array();
$marker      = 'cyw_editor_qa_' . strtolower( wp_generate_password( 10, false, false ) );

$assert = static function ( $condition, $message ) use ( &$checks ) {
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

try {
	$assert( defined( 'CYWATER_CORE_VERSION' ) && '0.6.9' === CYWATER_CORE_VERSION, 'Unexpected Core version.' );
	$assert( defined( 'CYWATER_OPERATIONS_VERSION' ) && '0.3.5' === CYWATER_OPERATIONS_VERSION, 'Unexpected Operations version.' );
	$assert( defined( 'CYWATER_THEME_VERSION' ) && '0.6.57' === CYWATER_THEME_VERSION, 'Unexpected theme version.' );
	$assert( current_theme_supports( 'editor-styles' ), 'Theme editor styles are not enabled.' );
	$assert( file_exists( get_theme_file_path( 'assets/css/editor.css' ) ), 'Theme editor stylesheet is missing.' );
	$assert( file_exists( CYWATER_CORE_DIR . 'assets/editor-workspace.js' ), 'Editor workspace script is missing.' );

	$event_meta = get_registered_meta_keys( 'post', 'cyw_event' );
	foreach ( array( '_cyw_start_date', '_cyw_end_date', '_cyw_date_label', '_cyw_location', '_cyw_format', '_cyw_attendees', '_cyw_image_alt', '_cyw_status', '_cyw_source_url' ) as $key ) {
		$assert( ! empty( $event_meta[ $key ]['show_in_rest'] ), 'Event field is not available to the block editor: ' . $key );
	}
	$event_fields = CYWater_Meta_Boxes::fields_for( 'cyw_event' );
	$assert( 'past' === array_key_first( $event_fields['status']['options'] ), 'Archive is not the Event placement default.' );
	$assert( ! empty( $event_fields['status']['help'] ), 'Event placement guidance is missing.' );
	$assert( ! empty( $event_fields['format']['help'] ), 'Optional Format guidance is missing.' );
	$editor_script = file_get_contents( CYWATER_CORE_DIR . 'assets/editor-workspace.js' );
	$assert( false !== $editor_script && str_contains( $editor_script, 'help: field.help || undefined' ), 'Editor field guidance is not rendered.' );
	$editor_service = file_get_contents( CYWATER_CORE_DIR . 'includes/class-cywater-editor.php' );
	$assert( false !== $editor_service && str_contains( $editor_service, 'cover image is separate presentation data' ) && str_contains( $editor_service, 'never inserted into the detail body automatically' ), 'News/Event editor guidance does not preserve the cover/body boundary.' );
	$theme_functions = file_get_contents( get_theme_file_path( 'functions.php' ) );
	$event_template  = file_get_contents( get_theme_file_path( 'single-cyw_event.php' ) );
	$news_template   = file_get_contents( get_theme_file_path( 'single.php' ) );
	$wordpress_css   = file_get_contents( get_theme_file_path( 'wordpress.css' ) );
	$assert( false !== $theme_functions && ! str_contains( $theme_functions, 'cywater_detail_featured_figure' ) && ! str_contains( $theme_functions, 'cywater_detail_article_content' ), 'Retired automatic detail-cover helpers remain in the theme.' );
	$assert( false !== $event_template && ! str_contains( $event_template, 'cywater_detail_featured_figure' ) && str_contains( $event_template, 'cywater_article_content' ), 'Event detail does not render editor-owned body content directly.' );
	$assert( false !== $news_template && ! str_contains( $news_template, 'cywater_detail_featured_figure' ) && str_contains( $news_template, 'cywater_article_content' ), 'News detail does not render editor-owned body content directly.' );
	$assert( false !== $wordpress_css && ! str_contains( $wordpress_css, 'cywater-detail-cover' ), 'Retired automatic detail-cover styles remain in the theme.' );

	$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
	$assert( ! empty( $administrators ), 'No Administrator is available for the internal REST probe.' );
	wp_set_current_user( (int) $administrators[0] );

	$created = wp_insert_post(
		array(
			'post_type'    => 'cyw_event',
			'post_status'  => 'draft',
			'post_title'   => $marker,
			'post_content' => '<!-- wp:paragraph --><p>Temporary editor QA.</p><!-- /wp:paragraph -->',
		),
		true
	);
	$assert( ! is_wp_error( $created ) && $created > 0, 'Temporary Event could not be created.' );
	$created_ids[] = (int) $created;
	$image_ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	$assert( ! empty( $image_ids ), 'No existing image attachment is available for content-image QA.' );
	$cover_id = (int) $image_ids[0];
	$assert( set_post_thumbnail( $created, $cover_id ), 'Temporary Event cover could not be assigned.' );
	$cover_url = wp_get_attachment_url( $cover_id );
	$assert( false !== $cover_url, 'Temporary Event cover URL could not be resolved.' );
	wp_update_post(
		array(
			'ID'           => $created,
			'post_content' => '<!-- wp:paragraph --><p>Opening detail paragraph.</p><!-- /wp:paragraph --><!-- wp:image {"id":' . $cover_id . '} --><figure class="wp-block-image"><img src="' . esc_url( $cover_url ) . '" alt="" class="wp-image-' . $cover_id . '"/></figure><!-- /wp:image --><!-- wp:heading --><h2>First section</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Section detail.</p><!-- /wp:paragraph -->',
		)
	);
	$sequenced_content = cywater_article_content( $created );
	$opening_position  = strpos( $sequenced_content, 'Opening detail paragraph.' );
	$image_position    = strpos( $sequenced_content, 'wp-image-' . $cover_id );
	$section_position  = strpos( $sequenced_content, 'First section' );
	$assert( false !== $opening_position && false !== $image_position && false !== $section_position, 'The rendered Event detail is missing editor-authored content.' );
	$assert( $opening_position < $image_position && $image_position < $section_position, 'An explicit content image did not retain its authored story position.' );
	$assert( 1 === substr_count( $sequenced_content, 'wp-image-' . $cover_id ), 'Assigning the same attachment as a listing cover duplicated the explicit body image.' );
	$assert( ! str_contains( $sequenced_content, 'cywater-detail-cover' ), 'A listing cover leaked into the Event detail body.' );
	wp_update_post(
		array(
			'ID'           => $created,
			'post_content' => '<!-- wp:paragraph --><p>Temporary editor QA.</p><!-- /wp:paragraph -->',
		)
	);

	$request = new WP_REST_Request( 'POST', '/wp/v2/cyw_event/' . $created );
	$request->set_param( 'id', $created );
	$request->set_param(
		'meta',
		array(
			'_cyw_start_date' => '2030-04-18',
			'_cyw_end_date'   => '2030-04-19',
			'_cyw_date_label' => 'April 18–19, 2030',
			'_cyw_location'   => 'QA location',
			'_cyw_format'     => 'Hybrid',
			'_cyw_status'     => 'upcoming',
		)
	);
	$controller = new WP_REST_Posts_Controller( 'cyw_event' );
	$assert( true === $controller->update_item_permissions_check( $request ), 'Administrator cannot update the temporary Event through REST.' );
	$response = $controller->update_item( $request );
	$assert( $response instanceof WP_REST_Response && 200 === $response->get_status(), 'Block-editor REST save was rejected.' );
	$assert(
		'2030-04-18' === get_post_meta( $created, '_cyw_start_date', true ),
		'Start date did not persist; REST meta response: ' . wp_json_encode( (array) ( $response->get_data()['meta'] ?? array() ) )
	);
	$assert( 'QA location' === get_post_meta( $created, '_cyw_location', true ), 'Location did not persist.' );
	$assert( 'upcoming' === get_post_meta( $created, '_cyw_status', true ), 'Event status did not persist.' );

	$sort_cases = array(
		array( 'title' => $marker . '_upcoming_later', 'status' => 'upcoming', 'date' => '2031-04-18' ),
		array( 'title' => $marker . '_archive_newer', 'status' => 'past', 'date' => '2032-04-18' ),
		array( 'title' => $marker . '_archive_older', 'status' => 'past', 'date' => '2029-04-18' ),
	);
	foreach ( $sort_cases as $case ) {
		$sort_id = wp_insert_post(
			array(
				'post_type'   => 'cyw_event',
				'post_status' => 'draft',
				'post_title'  => $case['title'],
			),
			true
		);
		$assert( ! is_wp_error( $sort_id ) && $sort_id > 0, 'Temporary Event sort case could not be created.' );
		$created_ids[] = (int) $sort_id;
		update_post_meta( $sort_id, '_cyw_status', $case['status'] );
		update_post_meta( $sort_id, '_cyw_start_date', $case['date'] );
	}
	$sorted = cywater_sort_events_for_archive( array_map( 'get_post', array_reverse( $created_ids ) ) );
	$assert(
		$created_ids === array_map( static fn( $event ) => (int) $event->ID, $sorted ),
		'Events are not ordered Upcoming first, then by authoritative Start date.'
	);

	global $wp_meta_boxes;
	$previous_boxes = $wp_meta_boxes;
	$wp_meta_boxes  = array();
	// Exercise only CYWater's registration layer. Third-party editor hooks may
	// require an HTTP Screen object that WP-CLI intentionally does not provide.
	add_meta_box( 'litespeed_meta_boxes', 'LiteSpeed', '__return_empty_string', 'cyw_event', 'side' );
	add_meta_box( 'pmpro_page_meta', 'Require Membership', '__return_empty_string', 'cyw_event', 'side' );
	CYWater_Meta_Boxes::add_boxes();
	CYWater_Editor::remove_irrelevant_meta_boxes();
	CYWater_Operations_Integrations::add_logo_event_box( get_post( $created ) );
	CYWater_Paid_Event_Approval::add_meta_box();
	$side   = (array) ( $wp_meta_boxes['cyw_event']['side'] ?? array() );
	$normal = (array) ( $wp_meta_boxes['cyw_event']['normal'] ?? array() );
	$find   = static function ( $contexts, $id ) {
		foreach ( $contexts as $boxes ) {
			if ( ! empty( $boxes[ $id ] ) ) {
				return true;
			}
		}
		return false;
	};
	$assert( ! $find( $side, 'cywater-logo-call' ), 'Logo participation appears on an ordinary Event.' );
	$assert( ! $find( $normal, 'cywater-logo-call' ), 'Logo participation still appears below the canvas.' );
	$assert( ! $find( $side, 'litespeed_meta_boxes' ), 'LiteSpeed still appears in the Event editor.' );
	$assert( ! $find( $side, 'pmpro_page_meta' ), 'PMPro content restriction still appears in the Event editor.' );
	$assert( ! $find( $normal, 'cywater-details' ), 'Legacy CYWater details still appear below the block editor.' );
	$assert( $find( $normal, 'cywater-paid-event-approval' ), 'Paid Event readiness workflow was lost.' );
	update_post_meta( $created, '_cywater_logo_call_enabled', '1' );
	CYWater_Operations_Integrations::add_logo_event_box( get_post( $created ) );
	$side = (array) ( $wp_meta_boxes['cyw_event']['side'] ?? array() );
	$assert( $find( $side, 'cywater-logo-call' ), 'Logo participation is missing from an enabled Logo host Event.' );
	$wp_meta_boxes = $previous_boxes;
} catch ( Throwable $error ) {
	foreach ( array_unique( $created_ids ) as $created_id ) {
		wp_delete_post( (int) $created_id, true );
	}
	WP_CLI::error( 'CYWater editor QA failed after ' . $checks . ' checks: ' . $error->getMessage() );
}

foreach ( array_unique( $created_ids ) as $created_id ) {
	wp_delete_post( (int) $created_id, true );
}

$leftovers = get_posts(
	array(
		'post_type'      => 'any',
		'post_status'    => 'any',
		's'              => $marker,
		'fields'         => 'ids',
		'posts_per_page' => -1,
	)
);
$assert( empty( $leftovers ), 'Temporary editor QA content remains.' );

WP_CLI::success( 'CYWater editor QA passed ' . $checks . ' checks; temporary Events and metadata were removed.' );
