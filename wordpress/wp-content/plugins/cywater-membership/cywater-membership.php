<?php
/**
 * Plugin Name: CYWater Membership
 * Description: CYWater member profile, privacy choices, rolling annual terms, and PMPro setup integration.
 * Version: 0.9.9
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cywater-membership
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CYWATER_MEMBERSHIP_VERSION', '0.9.9' );
define( 'CYWATER_MEMBERSHIP_FILE', __FILE__ );
define( 'CYWATER_MEMBERSHIP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CYWATER_MEMBERSHIP_URL', plugin_dir_url( __FILE__ ) );

require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-countries.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-fields.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-privacy.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-setup.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-mail.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-email-routing.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-receipt.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-account-routing.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-account-security.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-account-flow.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-refunds.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-admin.php';
require_once CYWATER_MEMBERSHIP_DIR . 'includes/class-cywater-membership-avatars.php';

function cywater_membership_boot() {
	CYWater_Membership_Countries::register();
	CYWater_Membership_Fields::register();
	CYWater_Membership_Privacy::register();
	CYWater_Membership_Setup::register();
	CYWater_Membership_Email_Routing::register();
	CYWater_Membership_Receipt::register();
	CYWater_Membership_Account_Routing::register();
	CYWater_Membership_Account_Security::register();
	CYWater_Membership_Account_Flow::register();
	CYWater_Membership_Refunds::register();
	CYWater_Membership_Admin::register();
	CYWater_Membership_Avatars::register();
}
add_action( 'plugins_loaded', 'cywater_membership_boot' );
