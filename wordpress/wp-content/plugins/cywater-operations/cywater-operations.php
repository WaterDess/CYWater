<?php
/**
 * Plugin Name: CYWater Operations
 * Description: Auditable operational roles and paid-event approval gates for CYWater.
 * Version: 0.3.5
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cywater-operations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CYWATER_OPERATIONS_VERSION', '0.3.5' );
define( 'CYWATER_OPERATIONS_FILE', __FILE__ );
define( 'CYWATER_OPERATIONS_DIR', plugin_dir_path( __FILE__ ) );

require_once CYWATER_OPERATIONS_DIR . 'includes/class-cywater-operations-audit.php';
require_once CYWATER_OPERATIONS_DIR . 'includes/class-cywater-operations-roles.php';
require_once CYWATER_OPERATIONS_DIR . 'includes/class-cywater-operations-integrations.php';
require_once CYWATER_OPERATIONS_DIR . 'includes/class-cywater-operations-logo-review.php';
require_once CYWATER_OPERATIONS_DIR . 'includes/class-cywater-paid-event-approval.php';
require_once CYWATER_OPERATIONS_DIR . 'includes/class-cywater-event-tickets-paid-adapter.php';
require_once CYWATER_OPERATIONS_DIR . 'includes/class-cywater-operations-admin.php';
require_once CYWATER_OPERATIONS_DIR . 'includes/class-cywater-operations-admin-navigation.php';
require_once CYWATER_OPERATIONS_DIR . 'includes/class-cywater-operations-qa.php';

/**
 * Return whether a CYWater Event may expose its paid registration handoff.
 *
 * Free Events return true. A paid Event returns true only while its approved
 * fingerprint is current and its workflow state is Registration open.
 *
 * @param int $event_id Event post ID.
 * @return bool
 */
function cywater_operations_paid_event_is_ready( $event_id ) {
	return CYWater_Paid_Event_Approval::is_payment_ready( absint( $event_id ) );
}

function cywater_operations_boot() {
	CYWater_Operations_Audit::register();
	CYWater_Operations_Roles::register();
	CYWater_Operations_Integrations::register_runtime_adapters();
	CYWater_Operations_Logo_Review::register();
	CYWater_Paid_Event_Approval::register();
	CYWater_Event_Tickets_Paid_Adapter::register();
	CYWater_Operations_Admin::register();
	CYWater_Operations_Admin_Navigation::register();
	CYWater_Operations_QA::register();
}
add_action( 'plugins_loaded', 'cywater_operations_boot', 100 );

function cywater_operations_activate() {
	CYWater_Operations_Audit::install();
	CYWater_Operations_Roles::install();
	update_option( 'cywater_operations_version', CYWATER_OPERATIONS_VERSION, false );
}
register_activation_hook( __FILE__, 'cywater_operations_activate' );
