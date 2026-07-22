<?php
/**
 * Content type registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Content_Types {
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_content_types' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'order_archives' ) );
	}

	public static function register_content_types() {
		register_post_type(
			'cyw_event',
			array(
				'labels'       => self::labels( 'Event', 'Events' ),
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => 'events',
				'rewrite'      => array( 'slug' => 'events' ),
				'menu_icon'    => 'dashicons-calendar-alt',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);

		register_taxonomy(
			'cyw_event_type',
			'cyw_event',
			array(
				'labels'            => array( 'name' => 'Event types', 'singular_name' => 'Event type' ),
				'public'            => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'event-type' ),
			)
		);

		register_post_type(
			'cyw_award',
			array(
				'labels'       => self::labels( 'Award record', 'Award records' ),
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => 'awards',
				'rewrite'      => array( 'slug' => 'awards' ),
				'menu_icon'    => 'dashicons-awards',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);

		register_post_type(
			'cyw_board_role',
			array(
				'labels'             => self::labels( 'Board role', 'Board roles' ),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_rest'       => false,
				'menu_icon'          => 'dashicons-groups',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'revisions' ),
			)
		);
	}

	private static function labels( $singular, $plural ) {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'add_new_item'  => 'Add new ' . strtolower( $singular ),
			'edit_item'     => 'Edit ' . strtolower( $singular ),
			'view_item'     => 'View ' . strtolower( $singular ),
			'search_items'  => 'Search ' . strtolower( $plural ),
		);
	}

	public static function register_meta() {
		$public_fields = array(
			'page'      => array( 'eyebrow', 'hero_title', 'lead', 'contact_email', 'mailing_address' ),
			'post'      => array( 'visual_title', 'visual_year', 'source_image', 'source_url', 'source_id' ),
			'cyw_event' => array( 'start_date', 'end_date', 'date_label', 'location', 'format', 'attendees', 'status', 'source_image', 'source_url', 'source_id' ),
			'cyw_award' => array( 'year', 'recipient', 'paper_title', 'journal', 'applications', 'chair', 'source_id' ),
		);

		foreach ( $public_fields as $post_type => $keys ) {
			foreach ( $keys as $key ) {
				$sanitizer = 'sanitize_text_field';
				if ( in_array( $key, array( 'year', 'applications' ), true ) ) {
					$sanitizer = 'absint';
				} elseif ( 'contact_email' === $key ) {
					$sanitizer = 'sanitize_email';
				} elseif ( 'mailing_address' === $key ) {
					$sanitizer = 'sanitize_textarea_field';
				}
				register_post_meta(
					$post_type,
					'_cyw_' . $key,
					array(
						'type'              => in_array( $key, array( 'year', 'applications' ), true ) ? 'integer' : 'string',
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => $sanitizer,
						'auth_callback'     => static function () {
							return current_user_can( 'edit_posts' );
						},
					)
				);
			}
		}

		foreach ( array( 'person_name', 'affiliation', 'term', 'order', 'confirmed_public', 'source_id' ) as $key ) {
			register_post_meta(
				'cyw_board_role',
				'_cyw_' . $key,
				array(
					'type'          => in_array( $key, array( 'order', 'confirmed_public' ), true ) ? 'integer' : 'string',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static function () {
						return current_user_can( 'edit_others_posts' );
					},
				)
			);
		}
	}

	public static function order_archives( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->is_post_type_archive( 'cyw_event' ) ) {
			$query->set( 'meta_key', '_cyw_start_date' );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'order', 'DESC' );
			$query->set( 'posts_per_page', 20 );
		}
		if ( $query->is_post_type_archive( 'cyw_award' ) ) {
			$query->set( 'meta_key', '_cyw_year' );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'order', 'DESC' );
			$query->set( 'posts_per_page', 30 );
		}
	}
}
