<?php
/**
 * Plugin Name: CYWater Logo Call
 * Description: Event-scoped, removable logo submissions and configurable registered-user or membership participation policies.
 * Version: 0.2.1
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cywater-logo-call
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CYWATER_LOGO_CALL_VERSION', '0.2.1' );
define( 'CYWATER_LOGO_CALL_DIR', plugin_dir_path( __FILE__ ) );
define( 'CYWATER_LOGO_CALL_URL', plugin_dir_url( __FILE__ ) );

require_once CYWATER_LOGO_CALL_DIR . 'includes/class-cywater-logo-call-eligibility.php';
require_once CYWATER_LOGO_CALL_DIR . 'includes/class-cywater-logo-call.php';

add_action( 'plugins_loaded', array( 'CYWater_Logo_Call', 'register' ) );
register_activation_hook( __FILE__, array( 'CYWater_Logo_Call', 'activate' ) );
