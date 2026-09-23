<?php
/**
 * Plugin Name: CYWater Best Paper
 * Description: Award-cycle applications, private supporting files, scoped peer review, and committee decision support.
 * Version: 0.1.3
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cywater-best-paper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CYWATER_BEST_PAPER_VERSION', '0.1.3' );
define( 'CYWATER_BEST_PAPER_DIR', plugin_dir_path( __FILE__ ) );
define( 'CYWATER_BEST_PAPER_URL', plugin_dir_url( __FILE__ ) );

require_once CYWATER_BEST_PAPER_DIR . 'includes/class-cywater-best-paper.php';
require_once CYWATER_BEST_PAPER_DIR . 'includes/class-cywater-best-paper-admin.php';
require_once CYWATER_BEST_PAPER_DIR . 'includes/class-cywater-best-paper-public.php';

add_action( 'plugins_loaded', array( 'CYWater_Best_Paper', 'register' ), 30 );
add_action( 'plugins_loaded', array( 'CYWater_Best_Paper_Admin', 'register' ), 30 );
add_action( 'plugins_loaded', array( 'CYWater_Best_Paper_Public', 'register' ), 30 );
register_activation_hook( __FILE__, array( 'CYWater_Best_Paper', 'activate' ) );
