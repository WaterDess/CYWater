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
			if ( function_exists( 'pmpro_hasMembershipLevel' ) && ! pmpro_hasMembershipLevel( null, $user->ID ) ) {
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
}
