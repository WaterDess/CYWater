<?php
/**
 * Plugin Name: CYWater Environment
 * Description: Environment-variable adapters, payment safety gates, local Mailpit routing, and readiness status.
 * Version: 0.5.6
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cywater-environment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CYWATER_ENVIRONMENT_VERSION', '0.5.6' );
define( 'CYWATER_ENVIRONMENT_DIR', plugin_dir_path( __FILE__ ) );

require_once CYWATER_ENVIRONMENT_DIR . 'includes/class-cywater-config.php';
require_once CYWATER_ENVIRONMENT_DIR . 'includes/class-cywater-payment-guard.php';
require_once CYWATER_ENVIRONMENT_DIR . 'includes/class-cywater-local-mail.php';
require_once CYWATER_ENVIRONMENT_DIR . 'includes/class-cywater-readiness.php';
require_once CYWATER_ENVIRONMENT_DIR . 'includes/class-cywater-security-headers.php';
require_once CYWATER_ENVIRONMENT_DIR . 'includes/class-cywater-public-surface.php';

function cywater_environment_boot() {
	CYWater_Payment_Guard::register();
	CYWater_Local_Mail::register();
	CYWater_Readiness::register();
	CYWater_Security_Headers::register();
	CYWater_Public_Surface::register();
}
add_action( 'plugins_loaded', 'cywater_environment_boot' );
