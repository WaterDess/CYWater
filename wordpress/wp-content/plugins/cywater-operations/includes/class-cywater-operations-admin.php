<?php
/**
 * Administrator-only assignment surface for composable operational roles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Operations_Admin {
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_cywater_operations_assign_roles', array( __CLASS__, 'assign_roles' ) );
	}

	/**
	 * Assignment is intentionally narrower than a capability check. A custom
	 * role that happens to receive manage_options must not delegate roles.
	 */
	public static function current_user_is_administrator() {
		if ( is_multisite() && is_super_admin() ) {
			return true;
		}

		$user = wp_get_current_user();
		return $user instanceof WP_User && in_array( 'administrator', (array) $user->roles, true );
	}

	public static function admin_menu() {
		if ( ! self::current_user_is_administrator() ) {
			return;
		}

		add_users_page(
			__( 'CYWater operational roles', 'cywater-operations' ),
			__( 'CYWater operational roles', 'cywater-operations' ),
			'manage_options',
			'cywater-operational-roles',
			array( __CLASS__, 'render_page' )
		);
	}

	private static function require_administrator() {
		if ( ! self::current_user_is_administrator() ) {
			wp_die( esc_html__( 'Only an Administrator may assign CYWater operational roles.', 'cywater-operations' ), '', array( 'response' => 403 ) );
		}
	}

	public static function assign_roles() {
		self::require_administrator();

		$user_id = absint( $_POST['user_id'] ?? 0 );
		check_admin_referer( 'cywater_operations_assign_roles_' . $user_id );
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			wp_die( esc_html__( 'The selected account no longer exists.', 'cywater-operations' ), '', array( 'response' => 404 ) );
		}

		$requested = array_map( 'sanitize_key', (array) wp_unslash( $_POST['operational_roles'] ?? array() ) );
		$result    = self::update_user_operational_roles( $user->ID, $requested );
		$query     = array( 'page' => 'cywater-operational-roles' );
		if ( is_wp_error( $result ) ) {
			$query['cywater_roles_error'] = $result->get_error_code();
		} else {
			$query['updated'] = '1';
		}

		wp_safe_redirect(
			add_query_arg(
				$query,
				admin_url( 'users.php' )
			)
		);
		exit;
	}

	/**
	 * Atomically replace only CYWater-managed role bundles for one account.
	 *
	 * The single pre-commit row is the authorization record. WordPress' ordinary
	 * per-role hooks are suppressed for this batch so one UI action cannot appear
	 * as several independent assignments in the operational audit.
	 *
	 * @param int               $user_id   Account ID.
	 * @param array<int,string> $requested Requested managed role slugs.
	 * @return true|WP_Error
	 */
	public static function update_user_operational_roles( $user_id, $requested ) {
		if ( ! self::current_user_is_administrator() ) {
			return new WP_Error( 'forbidden', __( 'Only an Administrator may assign CYWater operational roles.', 'cywater-operations' ) );
		}

		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'user_not_found', __( 'The selected account no longer exists.', 'cywater-operations' ) );
		}

		$managed = CYWater_Operations_Roles::role_slugs();
		$before  = self::managed_roles_for_user( $user );
		$after   = array_values( array_intersect( $managed, array_map( 'sanitize_key', (array) $requested ) ) );
		if ( $before === $after ) {
			return true;
		}

		if ( ! CYWater_Operations_Audit::record(
			'user_role_bundle',
			$user->ID,
			'assignment_authorized',
			self::role_state( $before ),
			self::role_state( $after ),
			$user->ID,
			'administrator_assignment'
		) ) {
			return new WP_Error( 'audit_unavailable', __( 'Role assignments were not changed because the audit log is unavailable.', 'cywater-operations' ) );
		}

		CYWater_Operations_Audit::suspend_role_hooks();
		try {
			$write_failed = false;
			try {
				self::write_managed_roles( $user, $after, $managed );
			} catch ( Throwable $error ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				$write_failed = true;
			}

			$readback = get_user_by( 'id', $user->ID );
			if ( $write_failed || ! $readback instanceof WP_User || self::managed_roles_for_user( $readback ) !== $after ) {
				$rollback_user = $readback instanceof WP_User ? $readback : get_user_by( 'id', $user->ID );
				if ( $rollback_user instanceof WP_User ) {
					try {
						self::write_managed_roles( $rollback_user, $before, $managed );
					} catch ( Throwable $error ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
						// The exact readback below decides whether rollback succeeded.
					}
				}
				$rolled_back = get_user_by( 'id', $user->ID );
				if ( ! $rolled_back instanceof WP_User || self::managed_roles_for_user( $rolled_back ) !== $before ) {
					return new WP_Error( 'role_rollback_failed', __( 'Role assignment and rollback could not be verified. Review this account immediately.', 'cywater-operations' ) );
				}
				return new WP_Error( 'role_write_failed', __( 'Role assignment could not be verified and was rolled back.', 'cywater-operations' ) );
			}

			return true;
		} finally {
			CYWater_Operations_Audit::resume_role_hooks();
		}
	}

	/** @return array<int,string> */
	private static function managed_roles_for_user( $user ) {
		return array_values( array_intersect( CYWater_Operations_Roles::role_slugs(), (array) $user->roles ) );
	}

	/** @param array<int,string> $roles */
	private static function role_state( $roles ) {
		return $roles ? implode( '-', $roles ) : 'none';
	}

	/**
	 * @param WP_User          $user    Account being updated.
	 * @param array<int,string> $target Target managed roles.
	 * @param array<int,string> $managed All managed roles.
	 */
	private static function write_managed_roles( $user, $target, $managed ) {
		foreach ( $managed as $role ) {
			if ( in_array( $role, $target, true ) ) {
				$user->add_role( $role );
			} else {
				$user->remove_role( $role );
			}
		}
	}

	public static function render_page() {
		self::require_administrator();

		$users = get_users(
			array(
				'number'  => 200,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'user_login', 'display_name' ),
			)
		);
		$labels = CYWater_Operations_Roles::role_labels();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CYWater operational roles', 'cywater-operations' ); ?></h1>
			<p><?php esc_html_e( 'Operational bundles are composable and do not grant membership, finance, Stripe, refund, or full Administrator access.', 'cywater-operations' ); ?></p>
			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Operational role assignments updated.', 'cywater-operations' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['cywater_roles_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Operational role assignments were not changed. The audit log or exact role readback was unavailable; review the account before retrying.', 'cywater-operations' ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Account', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'Operational bundles', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'Action', 'cywater-operations' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $users as $listed_user ) :
					$user = get_user_by( 'id', $listed_user->ID );
					if ( ! $user instanceof WP_User ) {
						continue;
					}
					?>
					<tr>
						<td><strong><?php echo esc_html( $listed_user->display_name ?: $listed_user->user_login ); ?></strong><br><code>#<?php echo esc_html( $listed_user->ID ); ?></code> <?php echo esc_html( $listed_user->user_login ); ?></td>
						<td colspan="2">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="cywater_operations_assign_roles">
								<input type="hidden" name="user_id" value="<?php echo esc_attr( $listed_user->ID ); ?>">
								<?php wp_nonce_field( 'cywater_operations_assign_roles_' . $listed_user->ID ); ?>
								<fieldset style="display:inline-block;margin-right:1rem">
								<?php foreach ( $labels as $role => $label ) : ?>
									<label style="display:block"><input type="checkbox" name="operational_roles[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $user->roles, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
								</fieldset>
								<button class="button button-primary" type="submit"><?php esc_html_e( 'Save bundles', 'cywater-operations' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Recent operational audit', 'cywater-operations' ); ?></h2>
			<p class="description"><?php esc_html_e( 'The audit stores IDs and workflow states only; it does not copy email addresses, content, payment data, credentials, or application notes.', 'cywater-operations' ); ?></p>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'UTC time', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'Actor ID', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'Object', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'Action', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'Transition', 'cywater-operations' ); ?></th></tr></thead><tbody>
			<?php foreach ( CYWater_Operations_Audit::recent( 50 ) as $row ) : ?>
				<tr><td><?php echo esc_html( $row->occurred_at ); ?></td><td><?php echo esc_html( $row->actor_user_id ); ?></td><td><?php echo esc_html( $row->object_type . ' #' . $row->object_id ); ?></td><td><?php echo esc_html( $row->action ); ?></td><td><?php echo esc_html( trim( $row->from_state . ' -> ' . $row->to_state ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php
	}
}
