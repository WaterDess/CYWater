<?php
/**
 * Explicit opt-in privacy controls and a minimal public member directory.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Membership_Privacy {
	private static $public_fields = array(
		'cyw_institution_name'   => 'Institution or employer',
		'cyw_country'            => 'Country or region',
		'cyw_institution_type'   => 'Institution type',
		'cyw_professional_title' => 'Current title or role',
		'cyw_career_stage'       => 'Career stage',
		'cyw_orcid'              => 'ORCID iD',
		'cyw_research_interests' => 'Research interests',
		'cyw_profile_photo'      => 'Profile photograph',
	);

	public static function register() {
		add_shortcode( 'cywater_privacy_settings', array( __CLASS__, 'privacy_shortcode' ) );
		add_shortcode( 'cywater_member_directory', array( __CLASS__, 'directory_shortcode' ) );
		add_action( 'admin_post_cywater_save_privacy', array( __CLASS__, 'save_privacy' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	public static function public_field_labels() {
		return self::$public_fields;
	}

	public static function register_exporter( $exporters ) {
		$exporters['cywater-membership'] = array(
			'exporter_friendly_name' => __( 'CYWater membership profile', 'cywater-membership' ),
			'callback'               => array( __CLASS__, 'export_personal_data' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['cywater-membership'] = array(
			'eraser_friendly_name' => __( 'CYWater membership profile', 'cywater-membership' ),
			'callback'             => array( __CLASS__, 'erase_personal_data' ),
		);
		return $erasers;
	}

	public static function export_personal_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user || 1 !== absint( $page ) ) {
			return array( 'data' => array(), 'done' => true );
		}

		$data = array();
		foreach ( self::$public_fields as $key => $label ) {
			$value = get_user_meta( $user->ID, $key, true );
			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			}
			if ( '' !== trim( (string) $value ) ) {
				$data[] = array( 'name' => $label, 'value' => (string) $value );
			}
		}
		$data[] = array(
			'name'  => __( 'Public directory', 'cywater-membership' ),
			'value' => get_user_meta( $user->ID, 'cyw_profile_public', true ) ? __( 'Opted in', 'cywater-membership' ) : __( 'Private', 'cywater-membership' ),
		);
		$selected = array_intersect( (array) get_user_meta( $user->ID, 'cyw_public_fields', true ), array_keys( self::$public_fields ) );
		$data[]   = array(
			'name'  => __( 'Public fields', 'cywater-membership' ),
			'value' => $selected ? implode( ', ', array_map( static function ( $key ) { return self::$public_fields[ $key ]; }, $selected ) ) : __( 'None', 'cywater-membership' ),
		);
		$data[] = array(
			'name'  => __( 'Email ownership', 'cywater-membership' ),
			'value' => CYWater_Membership_Account_Security::is_verified( $user->ID ) ? __( 'Verified', 'cywater-membership' ) : __( 'Verification required', 'cywater-membership' ),
		);
		$last_login = CYWater_Membership_Account_Security::last_login_at( $user->ID );
		if ( $last_login ) {
			$data[] = array( 'name' => __( 'Last sign-in', 'cywater-membership' ), 'value' => gmdate( 'c', $last_login ) );
		}
		$closure = CYWater_Membership_Account_Security::closure_requested_at( $user->ID );
		if ( $closure ) {
			$data[] = array( 'name' => __( 'Account closure requested', 'cywater-membership' ), 'value' => gmdate( 'c', $closure ) );
		}

		return array(
			'data' => array(
				array(
					'group_id'    => 'cywater-membership',
					'group_label' => __( 'CYWater membership profile', 'cywater-membership' ),
					'item_id'     => 'cywater-membership-' . $user->ID,
					'data'        => $data,
				),
			),
			'done' => true,
		);
	}

	public static function erase_personal_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user || 1 !== absint( $page ) ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}

		$removed = false;
		foreach ( CYWater_Membership_Fields::field_keys() as $key ) {
			if ( 'cyw_profile_photo' === $key ) {
				self::delete_profile_photo_file( get_user_meta( $user->ID, $key, true ) );
			}
			$removed = delete_user_meta( $user->ID, $key ) || $removed;
		}
		foreach ( array( 'cyw_profile_public', 'cyw_public_fields', 'cyw_email_verification_hash', 'cyw_email_verification_expires', 'cyw_email_verification_sent', 'cyw_email_verification_window', 'cyw_email_verification_count', 'cyw_last_login_at' ) as $key ) {
			$removed = delete_user_meta( $user->ID, $key ) || $removed;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => true,
			'messages'       => array( __( 'The WordPress identity, account-closure request, PMPro orders, refunds, memberships, and event records are retained for separate administrator review. This eraser does not delete the user account.', 'cywater-membership' ) ),
			'done'           => true,
		);
	}

	public static function privacy_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>Please sign in to manage profile privacy.</p>';
		}
		$user_id = get_current_user_id();
		$enabled = (bool) get_user_meta( $user_id, 'cyw_profile_public', true );
		$selected = (array) get_user_meta( $user_id, 'cyw_public_fields', true );
		ob_start();
		?>
		<form class="cywater-privacy-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cywater_save_privacy">
			<?php wp_nonce_field( 'cywater_save_privacy' ); ?>
			<fieldset>
				<legend>Public profile</legend>
				<label><input type="checkbox" name="cyw_profile_public" value="1" <?php checked( $enabled ); ?>> Include me in the public CYWater member directory.</label>
				<p class="description">Your email address is never displayed. Leaving this unchecked keeps the entire profile private.</p>
			</fieldset>
			<fieldset>
				<legend>Information that may be displayed</legend>
				<?php foreach ( self::$public_fields as $key => $label ) : ?>
					<label class="privacy-field"><input type="checkbox" name="cyw_public_fields[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
			</fieldset>
			<button class="btn btn-accent" type="submit">Save privacy choices</button>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function save_privacy() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must sign in to update privacy settings.', 'cywater-membership' ) );
		}
		check_admin_referer( 'cywater_save_privacy' );
		$user_id = get_current_user_id();
		$public  = isset( $_POST['cyw_profile_public'] ) ? 1 : 0;
		$fields  = isset( $_POST['cyw_public_fields'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['cyw_public_fields'] ) ) : array();
		$fields  = array_values( array_intersect( $fields, array_keys( self::$public_fields ) ) );
		update_user_meta( $user_id, 'cyw_profile_public', $public );
		update_user_meta( $user_id, 'cyw_public_fields', $fields );
		$redirect = wp_get_referer() ?: home_url( '/member-profile/' );
		wp_safe_redirect( add_query_arg( 'privacy-updated', '1', $redirect ) );
		exit;
	}

	public static function directory_shortcode() {
		$users = get_users(
			array(
				'number'     => 100,
				'orderby'    => 'display_name',
				'order'      => 'ASC',
				'meta_key'   => 'cyw_profile_public',
				'meta_value' => 1,
			)
		);
		ob_start();
		echo '<div class="member-directory grid grid-3">';
		foreach ( $users as $user ) {
			if ( ! self::has_current_membership( $user->ID ) ) {
				continue;
			}
			$fields = (array) get_user_meta( $user->ID, 'cyw_public_fields', true );
			echo '<article class="card member-card"><div class="card-body">';
			if ( in_array( 'cyw_profile_photo', $fields, true ) ) {
				$photo = get_user_meta( $user->ID, 'cyw_profile_photo', true );
				if ( is_array( $photo ) ) {
					$photo = $photo['fullurl'] ?? ( $photo['previewurl'] ?? '' );
				}
				if ( is_string( $photo ) && wp_http_validate_url( $photo ) ) {
					echo '<img class="member-photo" src="' . esc_url( $photo ) . '" alt="">';
				}
			}
			echo '<h2 class="card-title">' . esc_html( $user->display_name ) . '</h2>';
			foreach ( self::$public_fields as $key => $label ) {
				if ( 'cyw_profile_photo' === $key || ! in_array( $key, $fields, true ) ) {
					continue;
				}
				$value = get_user_meta( $user->ID, $key, true );
				if ( ! $value ) {
					continue;
				}
				if ( 'cyw_orcid' === $key && preg_match( '/^\d{4}-\d{4}-\d{4}-[\dX]{4}$/', $value ) ) {
					$value = '<a href="https://orcid.org/' . esc_attr( $value ) . '" rel="noopener noreferrer">' . esc_html( $value ) . '</a>';
				} else {
					$value = esc_html( $value );
				}
				echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . wp_kses_post( $value ) . '</p>';
			}
			echo '</div></article>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	private static function has_current_membership( $user_id ) {
		if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			return false;
		}

		$now    = current_time( 'timestamp' );
		$levels = (array) pmpro_getMembershipLevelsForUser( $user_id );
		$ids    = (array) get_option( 'cywater_membership_level_ids', array() );
		$legacy_partner_id = absint( $ids['partner_legacy'] ?? ( $ids['partner'] ?? 0 ) );
		foreach ( $levels as $level ) {
			if ( $legacy_partner_id && $legacy_partner_id === (int) $level->id ) {
				continue;
			}
			$enddate = isset( $level->enddate ) ? (int) $level->enddate : 0;
			if ( 0 === $enddate || $enddate > $now ) {
				return true;
			}
		}

		return false;
	}

	private static function delete_profile_photo_file( $photo ) {
		if ( ! is_array( $photo ) ) {
			return;
		}
		if ( ! empty( $photo['attachment_id'] ) ) {
			wp_delete_attachment( absint( $photo['attachment_id'] ), true );
			return;
		}
		$path    = isset( $photo['fullpath'] ) ? wp_normalize_path( (string) $photo['fullpath'] ) : '';
		$uploads = wp_get_upload_dir();
		$base    = wp_normalize_path( (string) ( $uploads['basedir'] ?? '' ) );
		if ( $path && $base && str_starts_with( $path, trailingslashit( $base ) ) && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
