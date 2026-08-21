<?php
/**
 * Small native meta-box layer; no page-builder or ACF dependency.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Meta_Boxes {
	private static $fields = array(
		'page' => array(
			'eyebrow'   => array( 'label' => 'Eyebrow', 'type' => 'text' ),
			'hero_title'=> array( 'label' => 'Hero title override', 'type' => 'textarea' ),
			'lead'       => array( 'label' => 'Hero lead', 'type' => 'textarea' ),
			'contact_email'  => array( 'label' => 'Contact email (Contact page only)', 'type' => 'email' ),
			'mailing_address'=> array( 'label' => 'Mailing address (Contact page only)', 'type' => 'textarea' ),
		),
		'post' => array(
			'visual_title' => array( 'label' => 'Text-tile title', 'type' => 'text' ),
			'visual_year'  => array( 'label' => 'Text-tile year', 'type' => 'number' ),
			'source_url'   => array( 'label' => 'Source URL', 'type' => 'url' ),
		),
		'cyw_event' => array(
			'start_date' => array( 'label' => 'Start date', 'type' => 'date', 'help' => 'Required for chronological Event-list ordering.' ),
			'end_date'   => array( 'label' => 'End date', 'type' => 'date', 'help' => 'Optional for single-day Events.' ),
			'date_label' => array( 'label' => 'Public date label', 'type' => 'text', 'help' => 'Optional display override, for example “October 16–18, 2026”.' ),
			'location'   => array( 'label' => 'Location', 'type' => 'text', 'help' => 'Optional public venue, city, or online location.' ),
			'format'     => array( 'label' => 'Format', 'type' => 'text', 'help' => 'Optional badge on the Event page, for example In person, Online, or Hybrid. Blank displays “Event”.' ),
			'attendees'  => array( 'label' => 'Attendance/registration note', 'type' => 'text', 'help' => 'Optional short registration or attendance note shown with the Event facts.' ),
			'image_alt'  => array( 'label' => 'Cover image description', 'type' => 'text', 'help' => 'Describe the cover image for accessibility. Blank falls back to the Event title.' ),
			'status'     => array(
				'label'   => 'Event placement',
				'type'    => 'select',
				'help'    => 'Archive is the default. Choose Upcoming only to also place this Event in the top carousel.',
				'options' => array(
					'past'     => 'Archive — category list (default)',
					'upcoming' => 'Upcoming — top carousel and category list',
				),
			),
			'source_url' => array( 'label' => 'Source URL', 'type' => 'url', 'help' => 'Internal editorial provenance; not shown publicly.' ),
		),
		'cyw_award' => array(
			'year'         => array( 'label' => 'Award year', 'type' => 'number' ),
			'recipient'    => array( 'label' => 'Recipient(s)', 'type' => 'text' ),
			'paper_title'  => array( 'label' => 'Best paper title', 'type' => 'textarea' ),
			'journal'      => array( 'label' => 'Journal', 'type' => 'text' ),
			'applications' => array( 'label' => 'Applications', 'type' => 'number' ),
			'chair'        => array( 'label' => 'Selection chair', 'type' => 'text' ),
		),
		'cyw_board_role' => array(
			'person_name'      => array( 'label' => 'Person name', 'type' => 'text' ),
			'affiliation'      => array( 'label' => 'Affiliation', 'type' => 'text' ),
			'term'             => array( 'label' => 'Term', 'type' => 'text' ),
			'order'            => array( 'label' => 'Display order', 'type' => 'number' ),
			'confirmed_public' => array( 'label' => 'Confirmed for public display', 'type' => 'checkbox' ),
		),
	);

	public static function register() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ) );
	}

	public static function add_boxes() {
		foreach ( self::$fields as $post_type => $fields ) {
			// Public editorial types use the native block-editor document sidebar.
			// Keep the classic box only for Pages and the non-REST Board model.
			if ( in_array( $post_type, array( 'post', 'cyw_event', 'cyw_award' ), true ) && use_block_editor_for_post_type( $post_type ) ) {
				continue;
			}
			add_meta_box( 'cywater-details', 'CYWater details', array( __CLASS__, 'render' ), $post_type, 'normal', 'high', array( 'fields' => $fields ) );
		}
	}

	/** @return array<string,array<string,mixed>> */
	public static function fields_for( $post_type ) {
		return self::$fields[ $post_type ] ?? array();
	}

	public static function render( $post, $box ) {
		wp_nonce_field( 'cywater_save_meta', 'cywater_meta_nonce' );
		foreach ( $box['args']['fields'] as $key => $field ) {
			$value = get_post_meta( $post->ID, '_cyw_' . $key, true );
			echo '<p><label for="cyw_' . esc_attr( $key ) . '"><strong>' . esc_html( $field['label'] ) . '</strong></label><br>';
			if ( 'textarea' === $field['type'] ) {
				echo '<textarea class="widefat" rows="3" id="cyw_' . esc_attr( $key ) . '" name="cyw_' . esc_attr( $key ) . '">' . esc_textarea( $value ) . '</textarea>';
			} elseif ( 'select' === $field['type'] ) {
				echo '<select id="cyw_' . esc_attr( $key ) . '" name="cyw_' . esc_attr( $key ) . '">';
				foreach ( $field['options'] as $option_value => $label ) {
					echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'checkbox' === $field['type'] ) {
				echo '<input type="checkbox" id="cyw_' . esc_attr( $key ) . '" name="cyw_' . esc_attr( $key ) . '" value="1" ' . checked( $value, 1, false ) . '>';
			} else {
				echo '<input class="widefat" type="' . esc_attr( $field['type'] ) . '" id="cyw_' . esc_attr( $key ) . '" name="cyw_' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
			}
			if ( ! empty( $field['help'] ) ) {
				echo '<span class="description">' . esc_html( $field['help'] ) . '</span>';
			}
			echo '</p>';
		}
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['cywater_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_meta_nonce'] ) ), 'cywater_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$post_type = get_post_type( $post_id );
		if ( empty( self::$fields[ $post_type ] ) ) {
			return;
		}
		foreach ( self::$fields[ $post_type ] as $key => $field ) {
			$name = 'cyw_' . $key;
			if ( 'checkbox' === $field['type'] ) {
				update_post_meta( $post_id, '_cyw_' . $key, isset( $_POST[ $name ] ) ? 1 : 0 );
				continue;
			}
			if ( ! isset( $_POST[ $name ] ) ) {
				continue;
			}
			$raw   = wp_unslash( $_POST[ $name ] );
			$value = 'url' === $field['type'] ? esc_url_raw( $raw ) : ( 'email' === $field['type'] ? sanitize_email( $raw ) : ( 'number' === $field['type'] ? absint( $raw ) : sanitize_textarea_field( $raw ) ) );
			update_post_meta( $post_id, '_cyw_' . $key, $value );
		}
	}
}
