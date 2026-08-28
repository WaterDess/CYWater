<?php
/**
 * Plugin Name: CYWater Meeting Registration
 * Description: Event-scoped Annual Meeting registration, membership-aware fee guidance, protected student evidence, and attendee export.
 * Version: 0.1.11
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cywater-meeting-registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CYWATER_MEETING_REGISTRATION_VERSION', '0.1.11' );
define( 'CYWATER_MEETING_REGISTRATION_DIR', plugin_dir_path( __FILE__ ) );
define( 'CYWATER_MEETING_REGISTRATION_URL', plugin_dir_url( __FILE__ ) );

require_once CYWATER_MEETING_REGISTRATION_DIR . 'includes/class-cywater-meeting-registration.php';

add_action( 'plugins_loaded', array( 'CYWater_Meeting_Registration', 'register' ), 30 );
register_activation_hook( __FILE__, array( 'CYWater_Meeting_Registration', 'activate' ) );
