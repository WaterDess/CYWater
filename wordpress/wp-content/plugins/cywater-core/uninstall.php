<?php
/**
 * CYWater Core intentionally preserves content on uninstall.
 *
 * Removing a presentation or data-model plugin must not delete association
 * records. A separately reviewed migration tool is required for destructive
 * cleanup.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
