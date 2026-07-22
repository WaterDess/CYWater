<?php
/**
 * Plugin Name: CYWater Environment
 * Description: Environment-variable adapters, payment safety gates, local Mailpit routing, and readiness status.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cywater-environment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CYWATER_ENVIRONMENT_VERSION', '0.1.0' );
define( 'CYWATER_ENVIRONMENT_DIR', plugin_dir_path( __FILE__ ) );

require_once CYWATER_ENVIRONMENT_DIR . 'includes/class-cywater-config.php';
require_once CYWATER_ENVIRONMENT_DIR . 'includes/class-cywater-payment-guard.php';
require_once CYWATER_ENVIRONMENT_DIR . 'includes/class-cywater-local-mail.php';
require_once CYWATER_ENVIRONMENT_DIR . 'includes/class-cywater-readiness.php';

function cywater_environment_boot() {
	CYWater_Payment_Guard::register();
	CYWater_Local_Mail::register();
	CYWater_Readiness::register();
}
add_action( 'plugins_loaded', 'cywater_environment_boot' );
