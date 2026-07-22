<?php
/**
 * PMPro user fields. Payment and presentation are intentionally absent here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Fields {
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_user_meta' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_pmpro_fields' ), 20 );
	}

	public static function register_user_meta() {
		foreach ( self::field_keys() as $key ) {
			// PMPro stores file fields as arrays; it owns that metadata lifecycle.
			if ( 'cyw_profile_photo' === $key ) {
				continue;
			}
			register_meta(
				'user',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static function ( $allowed, $meta_key, $user_id ) {
						return get_current_user_id() === (int) $user_id || current_user_can( 'edit_user', $user_id );
					},
			)
		);
		}
	}

	public static function register_pmpro_fields() {
		if ( ! class_exists( 'PMPro_Field' ) || ! function_exists( 'pmpro_add_user_field' ) ) {
			return;
		}

		pmpro_add_field_group( 'cywater_essentials', 'Professional information', 'A short set of fields used for membership statistics and regional programming.' );
		$essential_fields = array(
			new PMPro_Field( 'cyw_institution_name', 'text', array( 'label' => 'Institution or employer', 'required' => true, 'profile' => true, 'memberslistcsv' => true ) ),
			new PMPro_Field( 'cyw_country', 'text', array( 'label' => 'Country or region of institution', 'required' => true, 'profile' => true, 'memberslistcsv' => true ) ),
			new PMPro_Field(
				'cyw_institution_type',
				'select',
				array(
					'label'          => 'Institution type',
					'required'       => true,
					'profile'        => true,
					'memberslistcsv' => true,
					'options'        => array( '' => 'Select one', 'university' => 'University', 'research_institute' => 'Research institute', 'government' => 'Government', 'company' => 'Company', 'other' => 'Other' ),
				)
			),
			new PMPro_Field( 'cyw_professional_title', 'text', array( 'label' => 'Current title or role', 'required' => true, 'profile' => true, 'memberslistcsv' => true, 'hint' => 'Examples: Ph.D. student, postdoctoral researcher, professor, engineer.' ) ),
			new PMPro_Field(
				'cyw_career_stage',
				'select',
				array(
					'label'          => 'Career stage',
					'required'       => true,
					'profile'        => true,
					'memberslistcsv' => true,
					'options'        => array( '' => 'Select one', 'undergraduate' => 'Undergraduate student', 'graduate' => 'Graduate student', 'phd' => 'Ph.D. student', 'early_career' => 'Early-career professional', 'professional' => 'Professional', 'other' => 'Other' ),
				)
			),
		);
		foreach ( $essential_fields as $field ) {
			pmpro_add_user_field( 'cywater_essentials', $field );
		}

		pmpro_add_field_group( 'just_profile', 'Optional member profile', 'These fields may be completed after registration.' );
		$profile_fields = array(
			new PMPro_Field( 'cyw_orcid', 'text', array( 'label' => 'ORCID iD', 'required' => false, 'profile' => 'only', 'memberslistcsv' => true, 'hint' => 'Use the form 0000-0000-0000-0000.' ) ),
			new PMPro_Field( 'cyw_research_interests', 'textarea', array( 'label' => 'Research interests', 'required' => false, 'profile' => 'only', 'memberslistcsv' => true ) ),
			new PMPro_Field(
				'cyw_profile_photo',
				'file',
				array(
					'label'              => 'Profile photograph',
					'required'           => false,
					'profile'            => 'only',
					'memberslistcsv'     => false,
					'allowed_file_types' => array( 'jpg', 'jpeg', 'png', 'webp' ),
					'max_file_size'      => 2 * MB_IN_BYTES,
				)
			),
		);
		foreach ( $profile_fields as $field ) {
			pmpro_add_user_field( 'just_profile', $field );
		}
	}

	public static function field_keys() {
		return array( 'cyw_institution_name', 'cyw_country', 'cyw_institution_type', 'cyw_professional_title', 'cyw_career_stage', 'cyw_orcid', 'cyw_research_interests', 'cyw_profile_photo' );
	}
}
