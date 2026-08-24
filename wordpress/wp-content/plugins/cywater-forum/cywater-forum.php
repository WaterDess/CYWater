<?php
/**
 * Plugin Name: CYWater Forum
 * Description: Member-published forum articles, staff moderation, scoped discussion, and the dormant per-viewer AI reaction seam.
 * Version: 0.6.2
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cywater-forum
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CYWATER_FORUM_VERSION', '0.6.2' );
define( 'CYWATER_FORUM_DIR', plugin_dir_path( __FILE__ ) );

require_once CYWATER_FORUM_DIR . 'includes/class-cywater-forum-settings.php';
require_once CYWATER_FORUM_DIR . 'includes/class-cywater-forum-content.php';
require_once CYWATER_FORUM_DIR . 'includes/class-cywater-forum-roles.php';
require_once CYWATER_FORUM_DIR . 'includes/class-cywater-forum-endorsement.php';
require_once CYWATER_FORUM_DIR . 'includes/class-cywater-forum-comments.php';
require_once CYWATER_FORUM_DIR . 'includes/class-cywater-forum-ai.php';
require_once CYWATER_FORUM_DIR . 'includes/class-cywater-forum-admin.php';
require_once CYWATER_FORUM_DIR . 'includes/class-cywater-forum-covers.php';
require_once CYWATER_FORUM_DIR . 'includes/class-cywater-forum-workspace.php';
require_once CYWATER_FORUM_DIR . 'includes/class-cywater-forum-community.php';

function cywater_forum_boot() {
	CYWater_Forum_Settings::register();
	CYWater_Forum_Content::register();
	CYWater_Forum_Roles::register();
	if ( CYWater_Forum_Settings::is_enabled( 'endorsements_enabled' ) ) {
		CYWater_Forum_Endorsement::register();
	} else {
		CYWater_Forum_Endorsement::register_paused();
	}
	CYWater_Forum_Comments::register();
	CYWater_Forum_AI::register();
	CYWater_Forum_Admin::register();
	CYWater_Forum_Covers::register();
	CYWater_Forum_Workspace::register();
	CYWater_Forum_Community::register();
}
add_action( 'plugins_loaded', 'cywater_forum_boot' );

/**
 * Everything the forum needs to be usable is created here as well as during
 * `wp cywater setup`.
 *
 * Activation alone must leave a working section. Creating only roles and
 * rewrite rules once produced an incomplete Forum archive with no categories
 * or discussion settings. Activation now establishes every active dependency;
 * the paused legacy endorsement page is intentionally left untouched.
 */
function cywater_forum_activate() {
	CYWater_Forum_Content::register_content_types();
	CYWater_Forum_Roles::install_roles();
	if ( CYWater_Forum_Settings::is_enabled( 'endorsements_enabled' ) ) {
		CYWater_Forum_Endorsement::setup_page();
	}
	CYWater_Forum_Content::seed_categories();
	CYWater_Forum_Comments::apply_discussion_defaults();
	CYWater_Forum_Covers::ensure_storage();
	CYWater_Forum_Workspace::setup_page();
	CYWater_Forum_Community::register_routes();
	CYWater_Forum_Community::install_schema();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'cywater_forum_activate' );

function cywater_forum_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'cywater_forum_deactivate' );
