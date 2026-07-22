<?php
/**
 * Non-secret environment status page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Readiness {
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}

	public static function admin_menu() {
		add_management_page( 'CYWater readiness', 'CYWater readiness', 'manage_options', 'cywater-readiness', array( __CLASS__, 'render' ) );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$checks = array(
			'WordPress environment' => wp_get_environment_type(),
			'Payment mode'          => CYWater_Config::payment_mode(),
			'Live payment gate'     => CYWater_Config::live_payments_allowed() ? 'open' : 'closed',
			'PMPro active'          => class_exists( 'PMPro_Membership_Level' ) ? 'yes' : 'no',
			'PMPro gateway mode'    => get_option( 'pmpro_gateway_environment', 'not configured' ),
			'Stripe publishable key'=> CYWater_Config::has( 'STRIPE_PUBLISHABLE_KEY' ) ? 'configured' : 'missing',
			'Stripe secret key'     => CYWater_Config::has( 'STRIPE_SECRET_KEY' ) ? 'configured' : 'missing',
			'Mail transport'        => (string) CYWater_Config::get( 'CYWATER_MAIL_TRANSPORT', 'not configured' ),
		);
		?>
		<div class="wrap"><h1>CYWater readiness</h1><p>This page reports presence and mode only. It never displays credential values.</p><table class="widefat striped"><tbody>
		<?php foreach ( $checks as $label => $value ) : ?><tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr><?php endforeach; ?>
		</tbody></table></div>
		<?php
	}
}
