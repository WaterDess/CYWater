<?php
/**
 * Remove only what this plugin created for itself.
 *
 * Articles, authors, endorsement records, and discussion are association
 * records. Uninstalling the plugin must not destroy them, in the same way
 * cywater-core leaves its imported content in place.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cywater_forum_settings' );
delete_option( 'cywater_forum_category_seed_version' );
delete_option( 'cywater_forum_community_schema_version' );

if ( function_exists( 'remove_role' ) ) {
	remove_role( 'cyw_forum_author' );
}
