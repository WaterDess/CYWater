<?php
/**
 * Focused block-editor workspace for association-owned public content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Editor {
	private const POST_TYPES = array( 'post', 'cyw_event', 'cyw_award' );

	public static function register() {
		add_action( 'init', array( __CLASS__, 'label_listing_images' ), 100 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'remove_irrelevant_meta_boxes' ), 1000 );
	}

	/** Give the native Featured image control a purpose-specific editorial name. */
	public static function label_listing_images() {
		$labels = array(
			'post'      => array( 'News cover image', 'Set news cover image', 'Remove news cover image', 'Use as news cover image' ),
			'cyw_event' => array( 'Event cover image', 'Set event cover image', 'Remove event cover image', 'Use as event cover image' ),
			'cyw_award' => array( 'Award cover image', 'Set award cover image', 'Remove award cover image', 'Use as award cover image' ),
		);

		foreach ( $labels as $post_type => $image_labels ) {
			$object = get_post_type_object( $post_type );
			if ( ! $object || ! isset( $object->labels ) ) {
				continue;
			}
			$object->labels->featured_image        = $image_labels[0];
			$object->labels->set_featured_image    = $image_labels[1];
			$object->labels->remove_featured_image = $image_labels[2];
			$object->labels->use_featured_image    = $image_labels[3];
		}
	}

	public static function enqueue_assets() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::POST_TYPES, true ) ) {
			return;
		}

		$script = 'assets/editor-workspace.js';
		$style  = 'assets/editor-workspace.css';
		wp_enqueue_script(
			'cywater-editor-workspace',
			plugins_url( $script, CYWATER_CORE_FILE ),
			array( 'wp-components', 'wp-data', 'wp-edit-post', 'wp-element', 'wp-i18n', 'wp-plugins' ),
			file_exists( CYWATER_CORE_DIR . $script ) ? (string) filemtime( CYWATER_CORE_DIR . $script ) : CYWATER_CORE_VERSION,
			true
		);
		wp_enqueue_style(
			'cywater-editor-workspace',
			plugins_url( $style, CYWATER_CORE_FILE ),
			array( 'wp-components' ),
			file_exists( CYWATER_CORE_DIR . $style ) ? (string) filemtime( CYWATER_CORE_DIR . $style ) : CYWATER_CORE_VERSION
		);

		wp_localize_script(
			'cywater-editor-workspace',
			'cywaterEditorWorkspace',
			array(
				'postType' => $screen->post_type,
				'fields'   => self::editor_fields( $screen->post_type ),
				'copy'     => array(
					'newsPanel'       => __( 'News presentation', 'cywater-core' ),
					'eventSchedule'   => __( 'Event schedule & place', 'cywater-core' ),
					'eventPublishing' => __( 'Event presentation & access', 'cywater-core' ),
					'awardPanel'      => __( 'Award details', 'cywater-core' ),
					'publicHeading'   => __( 'Public page visibility', 'cywater-core' ),
					'publicCopy'      => __( 'Everyone can view this public record. Registration, submissions, voting, and payment eligibility are controlled separately by their own Event modules.', 'cywater-core' ),
					'newsHelp'        => __( 'The editor canvas is the News detail story. News cover image appears on listings and once at the start of the detail page. Add other detail photographs here as Image or Gallery blocks; if the same cover is already in the body, the template will not repeat it.', 'cywater-core' ),
					'eventHelp'       => __( 'The editor canvas is the Event detail story. Event cover image appears on listings, cards, the carousel, and once at the start of the detail page. Add other detail photographs here as Image or Gallery blocks; if the same cover is already in the body, the template will not repeat it. The Start date controls list order; Event placement is maintained in the next panel.', 'cywater-core' ),
					'awardHelp'       => __( 'Use the canvas for the public citation or ceremony narrative. Structured Award facts are maintained here.', 'cywater-core' ),
				),
			)
		);
	}

	/** Remove server-owned per-post controls that conflict with the public model. */
	public static function remove_irrelevant_meta_boxes() {
		foreach ( self::POST_TYPES as $post_type ) {
			remove_meta_box( 'litespeed_meta_boxes', $post_type, 'side' );
			remove_meta_box( 'pmpro_page_meta', $post_type, 'side' );
		}
	}

	/** @return array<int,array<string,mixed>> */
	private static function editor_fields( $post_type ) {
		$groups = array(
			'post'      => array(
				'visual_title' => 'presentation',
				'visual_year'  => 'presentation',
				'source_url'   => 'source',
			),
			'cyw_event' => array(
				'start_date' => 'schedule',
				'end_date'   => 'schedule',
				'date_label' => 'schedule',
				'location'   => 'schedule',
				'format'     => 'schedule',
				'attendees'  => 'publishing',
				'image_alt'  => 'publishing',
				'status'     => 'publishing',
			),
			'cyw_award' => array(
				'year'         => 'details',
				'recipient'    => 'details',
				'paper_title'  => 'details',
				'journal'      => 'details',
				'applications' => 'details',
				'chair'        => 'details',
			),
		);
		$fields = array();
		foreach ( CYWater_Meta_Boxes::fields_for( $post_type ) as $key => $field ) {
			if ( ! isset( $groups[ $post_type ][ $key ] ) ) {
				continue;
			}
			$field['key']   = '_cyw_' . $key;
			$field['group'] = $groups[ $post_type ][ $key ];
			$fields[]       = $field;
		}
		return $fields;
	}
}
