<?php
/**
 * Minimal audit trail for operational role and workflow transitions.
 *
 * Rows intentionally contain identifiers and state names only. Names, email
 * addresses, application notes, payment data, credentials and uploaded content
 * must never be copied into this table.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Operations_Audit {
	private const TABLE_SUFFIX = 'cywater_operations_audit';

	/** @var string */
	private static $context = '';

	/** @var int Prevent duplicate hook rows during one audited bundle update. */
	private static $role_hooks_suspended = 0;

	public static function register() {
		add_action( 'add_user_role', array( __CLASS__, 'role_added' ), 10, 2 );
		add_action( 'remove_user_role', array( __CLASS__, 'role_removed' ), 10, 2 );
		add_action( 'set_user_role', array( __CLASS__, 'role_set' ), 10, 3 );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			occurred_at datetime NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			subject_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(64) NOT NULL,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(64) NOT NULL,
			from_state varchar(191) NOT NULL DEFAULT '',
			to_state varchar(191) NOT NULL DEFAULT '',
			reason varchar(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY object_lookup (object_type, object_id),
			KEY actor_lookup (actor_user_id, occurred_at),
			KEY action_lookup (action, occurred_at)
		) {$charset};";
		dbDelta( $sql );
	}

	public static function set_context( $context ) {
		self::$context = sanitize_key( $context );
	}

	public static function clear_context() {
		self::$context = '';
	}

	public static function context() {
		return self::$context ?: 'wordpress_role_change';
	}

	public static function suspend_role_hooks() {
		++self::$role_hooks_suspended;
	}

	public static function resume_role_hooks() {
		self::$role_hooks_suspended = max( 0, self::$role_hooks_suspended - 1 );
	}

	private static function role_hooks_suspended() {
		return self::$role_hooks_suspended > 0;
	}

	public static function record( $object_type, $object_id, $action, $from_state = '', $to_state = '', $subject_user_id = 0, $reason = '' ) {
		global $wpdb;

		// A temporary QA context deliberately overrides the ordinary reason so
		// every probe row can be removed without matching production records.
		$reason = sanitize_key( self::$context ?: ( $reason ?: self::context() ) );
		$row = array(
				'occurred_at'    => current_time( 'mysql', true ),
				'actor_user_id'  => get_current_user_id(),
				'subject_user_id' => absint( $subject_user_id ),
				'object_type'    => substr( sanitize_key( $object_type ), 0, 64 ),
				'object_id'      => absint( $object_id ),
				'action'         => substr( sanitize_key( $action ), 0, 64 ),
				'from_state'     => substr( sanitize_key( $from_state ), 0, 191 ),
				'to_state'       => substr( sanitize_key( $to_state ), 0, 191 ),
				'reason'         => substr( $reason, 0, 64 ),
			);

		// Critical workflow callers rely on this result and fail closed. The
		// filter is intentionally boolean and carries only the already-redacted
		// row so staging QA can simulate a storage outage without touching SQL.
		if ( ! apply_filters( 'cywater_operations_audit_before_insert', true, $row ) ) {
			return false;
		}

		$result = $wpdb->insert(
			self::table_name(),
			$row,
			array( '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	public static function role_added( $user_id, $role ) {
		if ( ! self::role_hooks_suspended() && CYWater_Operations_Roles::is_managed_role( $role ) ) {
			self::record( 'user_role', $user_id, 'role_added', '', $role, $user_id );
		}
	}

	public static function role_removed( $user_id, $role ) {
		if ( ! self::role_hooks_suspended() && CYWater_Operations_Roles::is_managed_role( $role ) ) {
			self::record( 'user_role', $user_id, 'role_removed', $role, '', $user_id );
		}
	}

	public static function role_set( $user_id, $role, $old_roles ) {
		if ( self::role_hooks_suspended() ) {
			return;
		}
		$old_managed = array_values( array_intersect( (array) $old_roles, CYWater_Operations_Roles::role_slugs() ) );
		$new_managed = CYWater_Operations_Roles::is_managed_role( $role ) ? array( $role ) : array();
		if ( $old_managed !== $new_managed ) {
			self::record( 'user_role', $user_id, 'roles_replaced', implode( '-', $old_managed ), implode( '-', $new_managed ), $user_id );
		}
	}

	public static function recent( $limit = 50 ) {
		global $wpdb;
		$limit = max( 1, min( 200, absint( $limit ) ) );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY id DESC LIMIT %d', $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function delete_context_rows( $reason ) {
		global $wpdb;
		return $wpdb->delete( self::table_name(), array( 'reason' => sanitize_key( $reason ) ), array( '%s' ) );
	}
}
