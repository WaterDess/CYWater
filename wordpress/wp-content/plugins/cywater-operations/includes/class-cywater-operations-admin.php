<?php
/**
 * Administrator-only assignment surface for composable operational roles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Operations_Admin {
	private const PAGE_SLUG = 'cywater-operational-roles';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_cywater_operations_assign_roles', array( __CLASS__, 'assign_roles' ) );
		add_filter( 'manage_users_columns', array( __CLASS__, 'add_users_column' ) );
		add_filter( 'manage_users_columns', array( __CLASS__, 'organize_users_columns' ), 1000 );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'render_users_column' ), 10, 3 );
		add_filter( 'user_row_actions', array( __CLASS__, 'add_user_row_action' ), 10, 2 );
		add_filter( 'user_row_actions', array( __CLASS__, 'organize_user_row_actions' ), 1000, 2 );
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
			__( 'CYWater staff access', 'cywater-operations' ),
			__( 'CYWater staff access', 'cywater-operations' ),
			'manage_options',
			self::PAGE_SLUG,
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
		$query     = array(
			'page'    => self::PAGE_SLUG,
			'user_id' => $user->ID,
		);
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

	public static function page_url( $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE_SLUG ), (array) $args ),
			admin_url( 'users.php' )
		);
	}

	/** @return array<string,string> */
	private static function bundle_descriptions() {
		return array(
			CYWater_Operations_Roles::CONTENT_EDITOR      => __( 'Publish and update News, Events, and Awards. Paid Events can be prepared and submitted, but this role cannot approve its own paid registration.', 'cywater-operations' ),
			CYWater_Operations_Roles::COMMUNITY_MODERATOR => __( 'Publish and moderate Forum articles and replies. This role does not grant access to Events, membership, payments, or site settings.', 'cywater-operations' ),
			CYWater_Operations_Roles::PROGRAM_REVIEWER    => __( 'Review Logo Call entries and Annual Meeting registrations through dedicated review screens, including protected meeting files and structured CSV/ZIP exports. This role cannot edit Events, confirm payments, confirm Logo finalists, select the official logo, fulfill rewards, or delete entries.', 'cywater-operations' ),
			CYWater_Operations_Roles::GOVERNANCE_APPROVER => __( 'Review Board records, Partner applications, paid-Event approval, and the Logo Call finalist and official-design decisions. This role does not grant final-file, reward, plugin, user, membership, or Stripe administration.', 'cywater-operations' ),
		);
	}

	public static function add_users_column( $columns ) {
		if ( self::current_user_is_administrator() ) {
			$columns['cywater_operational_access'] = __( 'CYWater staff access', 'cywater-operations' );
		}
		return $columns;
	}

	/**
	 * Keep one authoritative membership summary and remove the irrelevant post
	 * count from the association account directory. Unknown future columns are
	 * retained after the documented CYWater columns.
	 */
	public static function organize_users_columns( $columns ) {
		if ( ! self::current_user_is_administrator() ) {
			return $columns;
		}

		unset( $columns['posts'], $columns['pmpro_membership_level'] );
		$ordered = array();
		foreach ( array( 'cb', 'username', 'name', 'email', 'cywater_account', 'cywater_membership', 'cyw_forum', 'cywater_operational_access', 'role' ) as $key ) {
			if ( isset( $columns[ $key ] ) ) {
				$ordered[ $key ] = $columns[ $key ];
				unset( $columns[ $key ] );
			}
		}

		return array_merge( $ordered, $columns );
	}

	public static function render_users_column( $output, $column_name, $user_id ) {
		if ( 'cywater_operational_access' !== $column_name || ! self::current_user_is_administrator() ) {
			return $output;
		}

		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user instanceof WP_User ) {
			return '&mdash;';
		}

		$labels   = CYWater_Operations_Roles::role_labels();
		$assigned = self::managed_roles_for_user( $user );
		if ( ! $assigned ) {
			return '<span class="cywater-access-none">' . esc_html__( 'None', 'cywater-operations' ) . '</span>';
		}

		$names = array();
		foreach ( $assigned as $role ) {
			if ( isset( $labels[ $role ] ) ) {
				$names[] = '<span class="cywater-access-badge">' . esc_html( str_replace( 'CYWater ', '', $labels[ $role ] ) ) . '</span>';
			}
		}
		return implode( ' ', $names );
	}

	public static function add_user_row_action( $actions, $user ) {
		if ( self::current_user_is_administrator() && $user instanceof WP_User ) {
			$actions['cywater_staff_access'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::page_url( array( 'user_id' => $user->ID ) ) ),
				esc_html__( 'Manage CYWater access', 'cywater-operations' )
			);
		}
		return $actions;
	}

	/**
	 * Replace a long pipe-separated action row with three daily actions and one
	 * keyboard-accessible overflow menu. Original nonce-bearing links are kept.
	 */
	public static function organize_user_row_actions( $actions, $user ) {
		if ( ! self::current_user_is_administrator() || ! $user instanceof WP_User ) {
			return $actions;
		}

		$primary = array();
		$edit_url = get_edit_user_link( $user->ID );
		if ( $edit_url ) {
			$primary[] = sprintf( '<a class="cywater-user-action" href="%1$s">%2$s</a>', esc_url( $edit_url ), esc_html__( 'Account', 'cywater-operations' ) );
		}
		if ( isset( $actions['cywater_member_record'] ) ) {
			$record_url = add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) ) . '#cywater-member-record';
			$primary[]  = sprintf( '<a class="cywater-user-action" href="%1$s">%2$s</a>', esc_url( $record_url ), esc_html__( 'Member record', 'cywater-operations' ) );
		}
		$primary[] = sprintf(
			'<a class="cywater-user-action cywater-user-action--primary" href="%1$s">%2$s</a>',
			esc_url( self::page_url( array( 'user_id' => $user->ID ) ) ),
			esc_html__( 'Staff access', 'cywater-operations' )
		);

		$remaining = $actions;
		unset( $remaining['edit'], $remaining['cywater_member_record'], $remaining['cywater_staff_access'] );
		$more = '';
		foreach ( $remaining as $key => $html ) {
			$class = 'cywater-user-action-menu__item';
			if ( 'delete' === $key ) {
				$class .= ' cywater-user-action-menu__item--danger';
			}
			$more .= '<span class="' . esc_attr( $class ) . '">' . $html . '</span>';
		}

		if ( '' !== $more ) {
			$primary[] = '<span class="cywater-user-actions-more"><button type="button" class="cywater-user-actions-more__toggle" aria-expanded="false">' . esc_html__( 'More', 'cywater-operations' ) . '</button><span class="cywater-user-action-menu" role="menu">' . $more . '</span></span>';
		}

		return array(
			'cywater_manage' => '<span class="cywater-user-actions" role="group" aria-label="' . esc_attr__( 'Account management', 'cywater-operations' ) . '">' . implode( '', $primary ) . '</span>',
		);
	}

	private static function render_account_editor( $user ) {
		$labels       = CYWater_Operations_Roles::role_labels();
		$descriptions = self::bundle_descriptions();
		$profile_url  = get_edit_user_link( $user->ID );
		$base_roles   = array_diff( (array) $user->roles, CYWater_Operations_Roles::role_slugs() );
		$wp_roles     = wp_roles()->role_names;
		$base_labels  = array();
		foreach ( $base_roles as $role ) {
			$base_labels[] = $wp_roles[ $role ] ?? $role;
		}
		?>
		<p><a href="<?php echo esc_url( self::page_url() ); ?>">&larr; <?php esc_html_e( 'Back to staff access', 'cywater-operations' ); ?></a></p>
		<div class="cywater-access-person">
			<div>
				<h2><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></h2>
				<p><strong><?php esc_html_e( 'Username:', 'cywater-operations' ); ?></strong> <?php echo esc_html( $user->user_login ); ?><br>
				<strong><?php esc_html_e( 'Email:', 'cywater-operations' ); ?></strong> <a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a><br>
				<strong><?php esc_html_e( 'WordPress account role:', 'cywater-operations' ); ?></strong> <?php echo esc_html( $base_labels ? implode( ', ', $base_labels ) : __( 'None', 'cywater-operations' ) ); ?></p>
			</div>
			<?php if ( $profile_url ) : ?>
				<a class="button" href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Open WordPress profile', 'cywater-operations' ); ?></a>
			<?php endif; ?>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cywater_operations_assign_roles">
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>">
			<?php wp_nonce_field( 'cywater_operations_assign_roles_' . $user->ID ); ?>
			<div class="cywater-access-grid">
			<?php foreach ( $labels as $role => $label ) : ?>
				<label class="cywater-access-card">
					<span class="cywater-access-card__control"><input type="checkbox" name="operational_roles[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $user->roles, true ) ); ?>> <strong><?php echo esc_html( $label ); ?></strong></span>
					<span><?php echo esc_html( $descriptions[ $role ] ); ?></span>
				</label>
			<?php endforeach; ?>
			</div>
			<p class="description"><?php esc_html_e( 'These bundles may be combined. They never grant membership status, PMPro/Stripe/refund access, plugin administration, or full Administrator access.', 'cywater-operations' ); ?></p>
			<p><button class="button button-primary" type="submit"><?php esc_html_e( 'Save CYWater staff access', 'cywater-operations' ); ?></button></p>
		</form>
		<?php
	}

	private static function render_staff_directory() {
		$search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page_num = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = 20;
		$args     = array(
			'number'      => $per_page,
			'offset'      => ( $page_num - 1 ) * $per_page,
			'orderby'     => 'display_name',
			'order'       => 'ASC',
			'count_total' => true,
		);
		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		} else {
			$args['role__in'] = CYWater_Operations_Roles::role_slugs();
		}

		$query  = new WP_User_Query( $args );
		$users  = $query->get_results();
		$labels = CYWater_Operations_Roles::role_labels();
		?>
		<form class="cywater-access-search" method="get" action="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
			<label class="screen-reader-text" for="cywater-user-search"><?php esc_html_e( 'Search accounts', 'cywater-operations' ); ?></label>
			<input id="cywater-user-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, username, or email', 'cywater-operations' ); ?>">
			<button class="button" type="submit"><?php esc_html_e( 'Find account', 'cywater-operations' ); ?></button>
			<?php if ( '' !== $search ) : ?><a class="button-link" href="<?php echo esc_url( self::page_url() ); ?>"><?php esc_html_e( 'Clear search', 'cywater-operations' ); ?></a><?php endif; ?>
		</form>
		<p class="description"><?php echo '' === $search ? esc_html__( 'Showing accounts that already have CYWater operational access. Search to find any registered account before assigning staff access.', 'cywater-operations' ) : esc_html__( 'Search results may include members without staff access. Opening an account does not change it.', 'cywater-operations' ); ?></p>
		<table class="widefat striped cywater-access-table">
			<thead><tr><th><?php esc_html_e( 'Person', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'Email', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'CYWater access', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'Action', 'cywater-operations' ); ?></th></tr></thead>
			<tbody>
			<?php if ( ! $users ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No matching accounts.', 'cywater-operations' ); ?></td></tr>
			<?php else : foreach ( $users as $user ) :
				$assigned = self::managed_roles_for_user( $user );
				$names    = array();
				foreach ( $assigned as $role ) {
					if ( isset( $labels[ $role ] ) ) {
						$names[] = str_replace( 'CYWater ', '', $labels[ $role ] );
					}
				}
				?>
				<tr>
					<td><strong><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></strong><br><span class="description"><?php echo esc_html( $user->user_login ); ?> · #<?php echo esc_html( $user->ID ); ?></span></td>
					<td><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
					<td><?php echo esc_html( $names ? implode( ', ', $names ) : __( 'No operational access', 'cywater-operations' ) ); ?></td>
					<td><a class="button" href="<?php echo esc_url( self::page_url( array( 'user_id' => $user->ID ) ) ); ?>"><?php esc_html_e( 'Manage access', 'cywater-operations' ); ?></a></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
		<?php
		$total_pages = (int) ceil( (int) $query->get_total() / $per_page );
		if ( $total_pages > 1 ) {
			$base = add_query_arg(
				array(
					'page'  => self::PAGE_SLUG,
					'paged' => 999999999,
					's'     => $search,
				),
				admin_url( 'users.php' )
			);
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
				paginate_links(
					array(
						'base'    => str_replace( '999999999', '%#%', $base ),
						'current' => $page_num,
						'total'   => $total_pages,
					)
				)
			) . '</div></div>';
		}
	}

	private static function render_audit() {
		$rows = CYWater_Operations_Audit::recent( 25 );
		?>
		<details class="cywater-audit">
			<summary><?php esc_html_e( 'Recent operational audit', 'cywater-operations' ); ?></summary>
			<p class="description"><?php esc_html_e( 'The audit stores IDs and workflow states only; it does not copy email addresses, content, payment data, credentials, or application notes.', 'cywater-operations' ); ?></p>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'UTC time', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'Actor ID', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'Object', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'Action', 'cywater-operations' ); ?></th><th><?php esc_html_e( 'Transition', 'cywater-operations' ); ?></th></tr></thead><tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No operational changes have been recorded yet.', 'cywater-operations' ); ?></td></tr>
			<?php else : foreach ( $rows as $row ) : ?>
				<tr><td><?php echo esc_html( $row->occurred_at ); ?></td><td><?php echo esc_html( $row->actor_user_id ); ?></td><td><?php echo esc_html( $row->object_type . ' #' . $row->object_id ); ?></td><td><?php echo esc_html( $row->action ); ?></td><td><?php echo esc_html( trim( $row->from_state . ' -> ' . $row->to_state ) ); ?></td></tr>
			<?php endforeach; endif; ?>
			</tbody></table>
		</details>
		<?php
	}

	public static function render_page() {
		self::require_administrator();
		$user_id = absint( $_GET['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user    = $user_id ? get_user_by( 'id', $user_id ) : false;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CYWater staff access', 'cywater-operations' ); ?></h1>
			<p><?php esc_html_e( 'Find a registered account, open it, and assign only the operational work that person is authorized to perform.', 'cywater-operations' ); ?></p>
			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'CYWater staff access updated and recorded in the operational audit.', 'cywater-operations' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['cywater_roles_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Operational role assignments were not changed. The audit log or exact role readback was unavailable; review the account before retrying.', 'cywater-operations' ); ?></p></div>
			<?php endif; ?>
			<?php
			if ( $user_id && ! $user instanceof WP_User ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'The selected account no longer exists.', 'cywater-operations' ) . '</p></div>';
				self::render_staff_directory();
			} elseif ( $user instanceof WP_User ) {
				self::render_account_editor( $user );
			} else {
				self::render_staff_directory();
				self::render_audit();
			}
			?>
		</div>
		<?php
	}
}
