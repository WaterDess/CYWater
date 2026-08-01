<?php
/**
 * Plugin Name: CYWater Core
 * Description: Portable content models, editorial fields, and idempotent static-content import for CYWater.
 * Version: 0.1.1
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cywater-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CYWATER_CORE_VERSION', '0.1.1' );
define( 'CYWATER_CORE_FILE', __FILE__ );
define( 'CYWATER_CORE_DIR', plugin_dir_path( __FILE__ ) );

require_once CYWATER_CORE_DIR . 'includes/class-cywater-content-types.php';
require_once CYWATER_CORE_DIR . 'includes/class-cywater-meta-boxes.php';
require_once CYWATER_CORE_DIR . 'includes/class-cywater-importer.php';
require_once CYWATER_CORE_DIR . 'includes/class-cywater-setup.php';

function cywater_core_boot() {
	CYWater_Content_Types::register();
	CYWater_Meta_Boxes::register();
	CYWater_Setup::register();
	add_action( 'init', 'cywater_core_maybe_flush_rewrite_rules', 99 );
}
add_action( 'plugins_loaded', 'cywater_core_boot' );

function cywater_core_maybe_flush_rewrite_rules() {
	if ( CYWATER_CORE_VERSION === get_option( 'cywater_core_rewrite_version' ) ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'cywater_core_rewrite_version', CYWATER_CORE_VERSION, false );
}

function cywater_core_activate() {
	CYWater_Content_Types::register_content_types();
	flush_rewrite_rules();
	update_option( 'cywater_core_rewrite_version', CYWATER_CORE_VERSION, false );
}
register_activation_hook( __FILE__, 'cywater_core_activate' );

function cywater_core_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'cywater_core_deactivate' );
