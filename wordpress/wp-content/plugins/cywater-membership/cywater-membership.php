<?php
/**
 * Plugin Name: CYWater Membership
 * Description: CYWater member profile, privacy choices, calendar-year policy, and PMPro setup integration.
 * Version: 0.8.0
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cywater-membership
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CYWATER_MEMBERSHIP_VERSION', '0.8.0' );
define( 'CYWATER_MEMBERSHIP_DIR', plugin_dir_path( __FILE__ ) );

require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-fields.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-privacy.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-setup.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-account-security.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-account-flow.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-refunds.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-admin.php';

function cywater_membership_boot() {
	CYWater_Membership_Fields::register();
	CYWater_Membership_Privacy::register();
	CYWater_Membership_Setup::register();
	CYWater_Membership_Account_Security::register();
	CYWater_Membership_Account_Flow::register();
	CYWater_Membership_Refunds::register();
	CYWater_Membership_Admin::register();
}
add_action( 'plugins_loaded', 'cywater_membership_boot' );
