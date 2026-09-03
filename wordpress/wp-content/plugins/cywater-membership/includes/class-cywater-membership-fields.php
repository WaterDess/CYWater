<?php
/**
 * PMPro user fields. Payment and presentation are intentionally absent here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Fields {
	private static $pending_username = array();

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_user_meta' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_pmpro_fields' ), 20 );
		add_filter( 'pmpro_member_profile_edit_user_object_fields', array( __CLASS__, 'clarify_account_fields' ) );
		add_action( 'pmpro_show_user_profile', array( __CLASS__, 'render_username_field' ), 5 );
		add_action( 'pmpro_user_profile_update_errors', array( __CLASS__, 'append_username_error' ), 100, 3 );
		add_filter( 'wp_pre_insert_user_data', array( __CLASS__, 'include_validated_username' ), 10, 4 );
		add_action( 'profile_update', array( __CLASS__, 'refresh_username_session' ), 10, 3 );
		add_filter( 'pmpro_field_get_html', array( __CLASS__, 'render_profile_file_control' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_profile_assets' ) );
	}

	/**
	 * Keep the optional public name distinct from the editable login username.
	 *
	 * @param array<string, string> $fields Core PMPro account fields.
	 * @return array<string, string>
	 */
	public static function clarify_account_fields( $fields ) {
		if ( isset( $fields['display_name'] ) ) {
			$fields['display_name'] = __( 'Public display name (optional)', 'cywater-membership' );
		}
		return $fields;
	}

	/**
	 * Render the editable username with the account fields. JavaScript moves this
	 * server-rendered field into PMPro's account grid; it remains usable without
	 * JavaScript.
	 *
	 * @param WP_User $user Profile owner.
	 */
	public static function render_username_field( $user ) {
		if ( ! $user instanceof WP_User || (int) $user->ID !== get_current_user_id() ) {
			return;
		}
		?>
		<div class="pmpro_form_field pmpro_form_field-user_login cywater-profile-username" data-cywater-profile-username-field>
			<label class="pmpro_form_label" for="cywater-profile-username"><?php esc_html_e( 'Username', 'cywater-membership' ); ?></label>
			<input class="pmpro_form_input pmpro_form_input-text" id="cywater-profile-username" name="user_login" type="text" minlength="3" maxlength="60" autocomplete="username" required value="<?php echo esc_attr( $user->user_login ); ?>" />
			<p class="pmpro_form_hint"><?php esc_html_e( 'Used to sign in. Changing it does not remove your profile, membership, posts, or registrations.', 'cywater-membership' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Change only the WordPress login value. All CYWater records remain linked by
	 * numeric user ID, so memberships and authored records are not rewritten.
	 *
	 * @return WP_User|WP_Error
	 */
	public static function update_username( $user_id, $raw_username ) {
		$user_id      = absint( $user_id );
		$raw_username = trim( (string) $raw_username );
		$current      = get_userdata( $user_id );

		if ( ! $current instanceof WP_User ) {
			return new WP_Error( 'cywater_username_account', __( 'The account could not be found.', 'cywater-membership' ) );
		}
		$username = self::validate_username_change( $current, $raw_username );
		if ( is_wp_error( $username ) ) {
			return $username;
		}
		if ( $username === $current->user_login ) {
			return $current;
		}

		global $wpdb;
		$updated = $wpdb->update( $wpdb->users, array( 'user_login' => $username ), array( 'ID' => $user_id ), array( '%s' ), array( '%d' ) );
		if ( false === $updated ) {
			return new WP_Error( 'cywater_username_update', __( 'The username could not be updated. Please try again.', 'cywater-membership' ) );
		}

		clean_user_cache( $user_id );
		$fresh = get_userdata( $user_id );
		if ( ! $fresh instanceof WP_User || $username !== $fresh->user_login ) {
			return new WP_Error( 'cywater_username_update', __( 'The username could not be updated. Please try again.', 'cywater-membership' ) );
		}
		do_action( 'cywater_membership_username_changed', $user_id, $current->user_login, $username );
		return $fresh;
	}

	/**
	 * Add the username to PMPro's existing user update only after every profile
	 * field has been validated. If any field fails, PMPro performs no user update.
	 */
	public static function append_username_error( &$errors, $update, &$user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$current = get_userdata( absint( $user->ID ?? 0 ) );
		if ( ! $update || ! $current instanceof WP_User || (int) $current->ID !== get_current_user_id() ) {
			return;
		}

		$raw_username = trim( (string) wp_unslash( $_POST['user_login'] ?? $current->user_login ) );
		$validation   = self::validate_username_change( $current, $raw_username );
		if ( is_wp_error( $validation ) ) {
			$errors[] = $validation->get_error_message();
			return;
		}

		$user->user_login = $validation;
		if ( empty( $user->display_name ) ) {
			$required_display_error = __( 'Please enter a display name.', 'paid-memberships-pro' );
			$errors                 = array_values(
				array_filter(
					$errors,
					static fn( $error ) => $required_display_error !== (string) $error
				)
			);
			$user->display_name = $validation;
			$user->nickname     = $validation;
		}
		if ( empty( $errors ) ) {
			self::$pending_username[ (int) $current->ID ] = $validation;
		}
	}

	/**
	 * WordPress intentionally omits user_login from ordinary profile updates.
	 * Add only the value validated for this exact PMPro profile save so the login
	 * and the remaining wp_users fields are committed in one database update.
	 */
	public static function include_validated_username( $data, $update, $user_id, $userdata ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$user_id = absint( $user_id );
		if ( $update && $user_id && isset( self::$pending_username[ $user_id ] ) ) {
			$data['user_login'] = self::$pending_username[ $user_id ];
			unset( self::$pending_username[ $user_id ] );
		}
		return $data;
	}

	/** Validate an intended username without changing the account. */
	private static function validate_username_change( $current, $raw_username ) {
		$username = sanitize_user( trim( (string) $raw_username ), true );
		if ( $username !== trim( (string) $raw_username ) || ! validate_username( $username ) || strlen( $username ) < 3 ) {
			return new WP_Error( 'cywater_username_invalid', __( 'Choose a username with at least three valid letters, numbers, periods, underscores, or hyphens.', 'cywater-membership' ) );
		}
		if ( is_email( $username ) ) {
			return new WP_Error( 'cywater_username_email', __( 'Choose a username rather than an email address.', 'cywater-membership' ) );
		}
		$owner_id = username_exists( $username );
		if ( $owner_id && (int) $owner_id !== (int) $current->ID ) {
			return new WP_Error( 'cywater_username_exists', __( 'That username is already in use. Please choose another.', 'cywater-membership' ) );
		}
		return $username;
	}

	/** Refresh the current browser's login cookie after a successful rename. */
	public static function refresh_username_session( $user_id, $old_user_data, $userdata ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$fresh = get_userdata( $user_id );
		if ( ! $fresh instanceof WP_User || ! $old_user_data instanceof WP_User || $fresh->user_login === $old_user_data->user_login || (int) $user_id !== get_current_user_id() ) {
			return;
		}
		$cookie   = wp_parse_auth_cookie( '', 'logged_in' );
		$remember = is_array( $cookie ) && absint( $cookie['expiration'] ?? 0 ) > time() + ( 2 * DAY_IN_SECONDS );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, $remember, is_ssl() );
	}

	/**
	 * Reuse CYWater's accepted file-control pattern while preserving PMPro's
	 * original input, upload processing, validation, and replace/delete actions.
	 *
	 * @param string      $html  PMPro field HTML.
	 * @param PMPro_Field $field PMPro field definition.
	 * @return string
	 */
	public static function render_profile_file_control( $html, $field ) {
		if ( ! is_object( $field ) || 'cyw_profile_photo' !== (string) ( $field->name ?? '' ) || 'file' !== (string) ( $field->type ?? '' ) ) {
			return $html;
		}

		if ( ! preg_match( '/<input\b[^>]*\btype=(?:"file"|\'file\')[^>]*>/i', $html, $match ) ) {
			return $html;
		}

		$input = $match[0];
		if ( ! preg_match( '/\bid=(?:"([^\"]+)"|\'([^\']+)\')/i', $input, $id_match ) ) {
			return $html;
		}
		$input_id = $id_match[1] ?: $id_match[2];
		$name_id  = $input_id . '-selected-file';

		if ( preg_match( '/\bclass=(?:"([^\"]*)"|\'([^\']*)\')/i', $input, $class_match ) ) {
			$classes = trim( ( $class_match[1] ?: $class_match[2] ) . ' cywater-profile-file__input' );
			$input   = preg_replace( '/\bclass=(?:"[^\"]*"|\'[^\']*\')/i', 'class="' . esc_attr( $classes ) . '"', $input, 1 );
		} else {
			$input = preg_replace( '/<input\b/i', '<input class="cywater-profile-file__input"', $input, 1 );
		}
		$input = preg_replace(
			'/<input\b/i',
			'<input data-cywater-profile-file-input aria-describedby="' . esc_attr( $name_id ) . '"',
			$input,
			1
		);

		$control  = '<span class="cywater-profile-file__control">';
		$control .= $input;
		$control .= '<label class="cywater-profile-file__button" for="' . esc_attr( $input_id ) . '">' . esc_html__( 'Choose File', 'cywater-membership' ) . '</label>';
		$control .= '<span class="cywater-profile-file__name" id="' . esc_attr( $name_id ) . '" data-cywater-profile-file-name aria-live="polite">' . esc_html__( 'No file chosen', 'cywater-membership' ) . '</span>';
		$control .= '</span>';

		return preg_replace_callback( '/' . preg_quote( $match[0], '/' ) . '/', static fn() => $control, $html, 1 );
	}

	public static function enqueue_profile_assets() {
		$profile_page_id = absint( get_option( 'pmpro_member_profile_edit_page_id' ) );
		if ( ! $profile_page_id || ! is_page( $profile_page_id ) ) {
			return;
		}
		wp_enqueue_script(
			'cywater-membership-profile',
			CYWATER_MEMBERSHIP_URL . 'assets/profile-controls.js',
			array(),
			CYWATER_MEMBERSHIP_VERSION,
			true
		);
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

		pmpro_add_field_group( 'cywater_essentials', 'Professional information', 'Required information used to understand CYWater\'s professional community and regional reach.' );
		$essential_fields = array(
			new PMPro_Field( 'cyw_institution_name', 'text', array( 'label' => 'Institution or employer', 'required' => true, 'profile' => true, 'memberslistcsv' => true ) ),
			new PMPro_Field(
				'cyw_country',
				'select',
				array(
					'label'          => 'Country or region of institution',
					'required'       => true,
					'profile'        => true,
					'memberslistcsv' => true,
					'options'        => CYWater_Membership_Countries::options( true ),
					'hint'           => 'Start typing to search the complete country and region list.',
				)
			),
			new PMPro_Field(
				'cyw_institution_type',
				'select',
				array(
					'label'          => 'Institution type',
					'required'       => true,
					'profile'        => true,
					'memberslistcsv' => true,
					'options'        => array( '' => 'Select one' ) + self::institution_types(),
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
					'options'        => array( '' => 'Select one' ) + self::career_stages(),
				)
			),
		);
		foreach ( $essential_fields as $field ) {
			pmpro_add_user_field( 'cywater_essentials', $field );
		}

		pmpro_add_field_group( 'just_profile', 'Optional member profile', 'These details are optional and may be completed or changed later.' );
		$profile_fields = array(
			new PMPro_Field( 'cyw_orcid', 'text', array( 'label' => 'ORCID iD', 'required' => false, 'profile' => 'only', 'memberslistcsv' => true, 'hint' => 'Use the form 0000-0000-0000-0000.' ) ),
			new PMPro_Field( 'cyw_research_interests', 'textarea', array( 'label' => 'About yourself', 'required' => false, 'profile' => 'only', 'memberslistcsv' => true, 'hint' => 'Optional. You may briefly describe your work or research interests.' ) ),
			new PMPro_Field(
				'cyw_profile_photo',
				'file',
				array(
					'label'              => 'Profile photograph',
					'required'           => false,
					'profile'            => 'only',
					'memberslistcsv'     => false,
					// PMPro expects a comma-separated extension list and a size in MB.
					'allowed_file_types' => 'jpg,jpeg,png,webp',
					'max_file_size'      => 2,
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

	public static function required_professional_keys() {
		return array( 'cyw_institution_name', 'cyw_country', 'cyw_institution_type', 'cyw_professional_title', 'cyw_career_stage' );
	}

	/** @return array<string, string> */
	public static function institution_types() {
		return array(
			'university'         => __( 'University', 'cywater-membership' ),
			'research_institute' => __( 'Research institute', 'cywater-membership' ),
			'government'         => __( 'Government', 'cywater-membership' ),
			'company'            => __( 'Company', 'cywater-membership' ),
			'other'              => __( 'Other', 'cywater-membership' ),
		);
	}

	/** @return array<string, string> */
	public static function career_stages() {
		return array(
			'undergraduate' => __( 'Undergraduate student', 'cywater-membership' ),
			'graduate'      => __( 'Graduate student', 'cywater-membership' ),
			'phd'           => __( 'Ph.D. student', 'cywater-membership' ),
			'early_career'  => __( 'Early-career professional', 'cywater-membership' ),
			'professional'  => __( 'Professional', 'cywater-membership' ),
			'other'         => __( 'Other', 'cywater-membership' ),
		);
	}
}
