<?php
/**
 * Plugin Name: CYWater Partnerships
 * Description: Board-reviewed institutional partnership applications and approved payment handoff.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cywater-partnerships
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CYWATER_PARTNERSHIPS_VERSION', '0.1.0' );
define( 'CYWATER_PARTNERSHIPS_DIR', plugin_dir_path( __FILE__ ) );

require_once CYWATER_PARTNERSHIPS_DIR . 'includes/class-cywater-partnerships.php';

function cywater_partnerships_boot() {
	CYWater_Partnerships::register();
}
add_action( 'plugins_loaded', 'cywater_partnerships_boot' );

register_activation_hook( __FILE__, array( 'CYWater_Partnerships', 'activate' ) );
