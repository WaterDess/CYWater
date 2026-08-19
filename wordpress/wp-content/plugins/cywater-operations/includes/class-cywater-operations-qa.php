<?php
/**
 * Staging-only, self-cleaning acceptance probe for operational permissions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Operations_QA {
	private const STAGING_HOST = 'staging.cywater.org';

	/** @var int */
	private static $checks = 0;

	public static function register() {
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'cywater operations qa', array( __CLASS__, 'cli_qa' ) );
		}
	}

	/**
	 * Exercise the operational permission matrix without retaining QA records.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cywater operations qa
	 *
	 * @param array<int, string>   $args Positional arguments (unused).
	 * @param array<string, mixed> $assoc_args Named arguments (unused).
	 */
	public static function cli_qa( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		self::guard_staging_cli();

		global $wpdb;

		self::$checks            = 0;
		$original_user_id        = get_current_user_id();
		$created_user_ids        = array();
		$created_post_ids        = array();
		$context                 = 'operations_qa_' . strtolower( wp_generate_password( 12, false, false ) );
		$admin_recovery_cap      = 'cywater_approve_paid_event';
		$admin_recovery_restored = true;
		$failure                 = null;
		$cleanup_failures        = array();

		CYWater_Operations_Audit::install();
		CYWater_Operations_Roles::install();
		CYWater_Operations_Audit::set_context( $context );

		try {
			self::test_role_definitions();
			self::test_partner_delete_boundaries();
			self::test_administrator_core_cap_migration();

			$administrator = get_role( 'administrator' );
			self::assert_true( $administrator instanceof WP_Role, 'The Administrator role exists.' );
			foreach ( CYWater_Operations_Roles::all_capabilities() as $capability ) {
				self::assert_true( $administrator->has_cap( $capability ), 'Administrator recovery includes ' . $capability . '.' );
			}

			// Exercise the recovery path using one plugin-owned capability. Restore it
			// immediately even if maybe_install() itself unexpectedly fails.
			$administrator->remove_cap( $admin_recovery_cap );
			$admin_recovery_restored = false;
			CYWater_Operations_Roles::maybe_install();
			$administrator = get_role( 'administrator' );
			self::assert_true( $administrator instanceof WP_Role && $administrator->has_cap( $admin_recovery_cap ), 'Administrator recovery restores a missing managed capability.' );
			$admin_recovery_restored = true;

			$suffix = strtolower( wp_generate_password( 10, false, false ) );
			$editor = self::create_user( 'cyw_ops_editor_' . $suffix, 'cyw-ops-editor-' . $suffix . '@example.invalid', $created_user_ids );
			$governance = self::create_user( 'cyw_ops_governance_' . $suffix, 'cyw-ops-governance-' . $suffix . '@example.invalid', $created_user_ids );
			$program = self::create_user( 'cyw_ops_program_' . $suffix, 'cyw-ops-program-' . $suffix . '@example.invalid', $created_user_ids );
			self::test_atomic_role_assignment( $program );

			// update_user_operational_roles() deliberately reads and writes through
			// its own WP_User instance. Rebind this QA subject so later object-level
			// user_can() checks inspect the exact persisted least-privilege bundle.
			$program = get_user_by( 'id', $program->ID );
			$program_managed_roles = $program instanceof WP_User
				? array_values( array_intersect( CYWater_Operations_Roles::role_slugs(), (array) $program->roles ) )
				: array();
			self::assert_true( $program instanceof WP_User && array( CYWater_Operations_Roles::PROGRAM_REVIEWER ) === $program_managed_roles, 'Program Reviewer QA subject is rebound with the exact persisted managed-role bundle.' );
			self::assert_true( $program instanceof WP_User && user_can( $program, 'cywater_review_logo_entries' ), 'Program Reviewer QA subject reads back the dedicated Logo review capability.' );
			foreach ( array( 'edit_cyw_logo_entries', 'edit_others_cyw_logo_entries', 'read_private_cyw_logo_entries', 'edit_private_cyw_logo_entries', 'edit_published_cyw_logo_entries' ) as $native_logo_capability ) {
				self::assert_true( $program instanceof WP_User && ! user_can( $program, $native_logo_capability ), 'Program Reviewer QA subject has no native Logo primitive: ' . $native_logo_capability . '.' );
			}

			$editor->add_role( CYWater_Operations_Roles::CONTENT_EDITOR );
			$editor->add_role( CYWater_Operations_Roles::GOVERNANCE_APPROVER );
			self::assert_true( user_can( $editor, 'edit_cyw_events' ), 'Multiple roles grant the content capability union.' );
			self::assert_true( user_can( $editor, 'cywater_approve_paid_event' ), 'Multiple roles grant the governance capability union.' );

			$editor->remove_role( CYWater_Operations_Roles::GOVERNANCE_APPROVER );
			$editor = get_user_by( 'id', $editor->ID );
			self::assert_true( $editor instanceof WP_User && user_can( $editor, 'edit_cyw_events' ), 'Revoking governance preserves the independent content bundle.' );
			self::assert_true( $editor instanceof WP_User && ! user_can( $editor, 'cywater_approve_paid_event' ), 'Revoking governance removes its approval capability.' );

			$governance->add_role( CYWater_Operations_Roles::GOVERNANCE_APPROVER );
			self::test_logo_review_workflow( $program, $editor, $created_post_ids, $suffix );
			self::test_partnership_audit_failure( $governance, $created_post_ids, $suffix );
			self::test_paid_event_workflow( $editor, $governance, $created_post_ids, $suffix );
			self::test_audit_redaction( $context, $suffix );
		} catch ( Throwable $throwable ) {
			$failure = $throwable;
		} finally {
			wp_set_current_user( $original_user_id );

			if ( ! $admin_recovery_restored ) {
				$administrator = get_role( 'administrator' );
				if ( $administrator instanceof WP_Role ) {
					$administrator->add_cap( $admin_recovery_cap, true );
					$admin_recovery_restored = true;
				}
			}

			foreach ( array_reverse( $created_post_ids ) as $post_id ) {
				if ( get_post( $post_id ) && ! wp_delete_post( $post_id, true ) ) {
					$cleanup_failures[] = 'post #' . absint( $post_id );
				}
				if ( get_post( $post_id ) ) {
					$cleanup_failures[] = 'retained post #' . absint( $post_id );
				}
			}

			require_once ABSPATH . 'wp-admin/includes/user.php';
			foreach ( array_reverse( $created_user_ids ) as $user_id ) {
				if ( get_user_by( 'id', $user_id ) && ! wp_delete_user( $user_id ) ) {
					$cleanup_failures[] = 'user #' . absint( $user_id );
				}
				if ( get_user_by( 'id', $user_id ) ) {
					$cleanup_failures[] = 'retained user #' . absint( $user_id );
				}
			}

			CYWater_Operations_Audit::delete_context_rows( $context );
			$remaining_audit_rows = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . CYWater_Operations_Audit::table_name() . ' WHERE reason = %s', $context ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $remaining_audit_rows ) {
				$cleanup_failures[] = $remaining_audit_rows . ' retained audit row(s)';
			}
			CYWater_Operations_Audit::clear_context();
		}

		if ( $cleanup_failures ) {
			WP_CLI::error( 'CYWater Operations QA cleanup failed for: ' . implode( ', ', $cleanup_failures ) . '.' );
		}
		if ( $failure instanceof Throwable ) {
			WP_CLI::error( 'CYWater Operations QA failed: ' . $failure->getMessage() );
		}

		WP_CLI::success( sprintf( 'CYWater Operations QA passed %d checks; all temporary users, Events and audit rows were removed.', self::$checks ) );
	}

	private static function guard_staging_cli() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			throw new RuntimeException( 'CYWater Operations QA is available only through WP-CLI.' );
		}

		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( site_url( '/' ), PHP_URL_HOST ) );
		if ( self::STAGING_HOST !== $home_host || self::STAGING_HOST !== $site_host ) {
			WP_CLI::error( 'Refusing to run: both WordPress home and site hosts must be exactly ' . self::STAGING_HOST . '.' );
		}
	}

	private static function test_role_definitions() {
		$bundles = CYWater_Operations_Roles::bundles();
		self::assert_true( 4 === count( $bundles ), 'Exactly four composable operational bundles are managed.' );

		foreach ( $bundles as $slug => $capabilities ) {
			$role = get_role( $slug );
			self::assert_true( $role instanceof WP_Role, 'Managed role ' . $slug . ' exists.' );
			foreach ( $capabilities as $capability ) {
				self::assert_true( $role->has_cap( $capability ), $slug . ' has ' . $capability . '.' );
			}
		}
		$program_role = get_role( CYWater_Operations_Roles::PROGRAM_REVIEWER );
		$program_caps = $program_role instanceof WP_Role ? array_keys( array_filter( $program_role->capabilities ) ) : array();
		sort( $program_caps );
		$expected_program_caps = array( 'cywater_review_logo_entries', 'read' );
		sort( $expected_program_caps );
		self::assert_true( $expected_program_caps === $program_caps, 'Program Reviewer persists only read and the dedicated Logo review capability.' );

		$positive = array(
			CYWater_Operations_Roles::CONTENT_EDITOR      => array( 'edit_cyw_events', 'cywater_submit_paid_event_approval', 'cywater_open_paid_event_registration' ),
			CYWater_Operations_Roles::COMMUNITY_MODERATOR => array( 'moderate_comments', 'edit_cyw_forum_posts' ),
			CYWater_Operations_Roles::PROGRAM_REVIEWER    => array( 'cywater_review_logo_entries' ),
			CYWater_Operations_Roles::GOVERNANCE_APPROVER => array( 'edit_cyw_board_roles', 'cywater_approve_partnerships', 'cywater_approve_paid_event' ),
		);
		$negative = array(
			CYWater_Operations_Roles::CONTENT_EDITOR      => array( 'cywater_approve_paid_event', 'cywater_approve_partnerships', 'cywater_review_logo_entries', 'moderate_comments', 'edit_cyw_board_roles' ),
			CYWater_Operations_Roles::COMMUNITY_MODERATOR => array( 'edit_cyw_events', 'cywater_approve_paid_event', 'cywater_review_logo_entries', 'cywater_approve_partnerships' ),
			CYWater_Operations_Roles::PROGRAM_REVIEWER    => array(
				'edit_cyw_events',
				'cywater_configure_logo_call',
				'moderate_comments',
				'cywater_approve_paid_event',
				'cywater_approve_partnerships',
				'cywater_fulfill_logo_reward',
				'edit_cyw_logo_entries',
				'edit_others_cyw_logo_entries',
				'read_private_cyw_logo_entries',
				'edit_private_cyw_logo_entries',
				'edit_published_cyw_logo_entries',
				'create_cyw_logo_entries',
				'publish_cyw_logo_entries',
				'delete_cyw_logo_entries',
				'delete_private_cyw_logo_entries',
				'delete_published_cyw_logo_entries',
				'delete_others_cyw_logo_entries',
			),
			CYWater_Operations_Roles::GOVERNANCE_APPROVER => array( 'edit_cyw_events', 'cywater_open_paid_event_registration', 'moderate_comments', 'cywater_review_logo_entries' ),
		);

		foreach ( $positive as $slug => $capabilities ) {
			$role = get_role( $slug );
			foreach ( $capabilities as $capability ) {
				self::assert_true( $role instanceof WP_Role && $role->has_cap( $capability ), $slug . ' positive boundary includes ' . $capability . '.' );
			}
		}
		foreach ( $negative as $slug => $capabilities ) {
			$role = get_role( $slug );
			foreach ( $capabilities as $capability ) {
				self::assert_true( $role instanceof WP_Role && ! $role->has_cap( $capability ), $slug . ' negative boundary excludes ' . $capability . '.' );
			}
		}

		$forbidden_roles = array(
			'cywater_finance_manager',
			'cywater_refund_approver',
			'cywater_membership_manager',
			'cywater_pmpro_manager',
			'pmpro_membership_manager',
			'pmpro_membership_manager_role',
		);
		foreach ( $forbidden_roles as $slug ) {
			self::assert_true( null === get_role( $slug ), 'No unlicensed or unused finance/refund/PMPro manager role exists: ' . $slug . '.' );
		}
		foreach ( CYWater_Operations_Roles::role_slugs() as $slug ) {
			self::assert_true( ! preg_match( '/finance|refund|pmpro|membership_manager/', $slug ), 'Managed role ' . $slug . ' is not a dormant finance/refund/PMPro role.' );
		}
		self::assert_true( ! in_array( 'cywater_fulfill_logo_reward', CYWater_Operations_Roles::all_capabilities(), true ), 'No obsolete standalone Logo reward-fulfillment capability is managed.' );
	}

	/**
	 * One administrator submission is one strict pre-commit audit event. A
	 * failed audit or an inexact WordPress role write must leave the exact prior
	 * managed-role bundle in place.
	 */
	private static function test_atomic_role_assignment( $subject ) {
		$administrators = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
			)
		);
		self::assert_true( $subject instanceof WP_User && ! empty( $administrators ) && $administrators[0] instanceof WP_User, 'An Administrator and temporary role-assignment subject are available.' );
		if ( ! $subject instanceof WP_User || empty( $administrators ) || ! $administrators[0] instanceof WP_User ) {
			return;
		}

		$previous_user_id = get_current_user_id();
		wp_set_current_user( $administrators[0]->ID );
		try {
			$audit_before = self::audit_context_count();
			$fail_audit   = static function ( $allowed, $row ) use ( $subject ) {
				if ( 'user_role_bundle' === ( $row['object_type'] ?? '' ) && (int) $subject->ID === (int) ( $row['object_id'] ?? 0 ) ) {
					return false;
				}
				return $allowed;
			};
			add_filter( 'cywater_operations_audit_before_insert', $fail_audit, 10, 2 );
			try {
				$result = CYWater_Operations_Admin::update_user_operational_roles( $subject->ID, array( CYWater_Operations_Roles::CONTENT_EDITOR ) );
			} finally {
				remove_filter( 'cywater_operations_audit_before_insert', $fail_audit, 10 );
			}
			$readback = get_user_by( 'id', $subject->ID );
			self::assert_true( is_wp_error( $result ) && 'audit_unavailable' === $result->get_error_code(), 'Operational role assignment fails closed when its pre-commit audit cannot be inserted.' );
			self::assert_true( $readback instanceof WP_User && ! array_intersect( CYWater_Operations_Roles::role_slugs(), (array) $readback->roles ), 'Audit failure leaves the exact prior managed-role bundle unchanged.' );
			self::assert_true( $audit_before === self::audit_context_count(), 'Failed role-assignment audit inserts no partial row.' );

			$forced_once = false;
			$force_mismatch = static function ( $user_id, $role ) use ( $subject, &$forced_once ) {
				if ( ! $forced_once && (int) $subject->ID === (int) $user_id && CYWater_Operations_Roles::CONTENT_EDITOR === $role ) {
					$forced_once = true;
					$fresh       = get_user_by( 'id', $user_id );
					if ( $fresh instanceof WP_User ) {
						$fresh->remove_role( $role );
					}
				}
			};
			add_action( 'add_user_role', $force_mismatch, 20, 2 );
			try {
				$result = CYWater_Operations_Admin::update_user_operational_roles( $subject->ID, array( CYWater_Operations_Roles::CONTENT_EDITOR ) );
			} finally {
				remove_action( 'add_user_role', $force_mismatch, 20 );
			}
			$readback = get_user_by( 'id', $subject->ID );
			self::assert_true( is_wp_error( $result ) && 'role_write_failed' === $result->get_error_code(), 'An inexact role write is detected and reported.' );
			self::assert_true( $readback instanceof WP_User && ! array_intersect( CYWater_Operations_Roles::role_slugs(), (array) $readback->roles ), 'An inexact role write rolls back to the exact prior managed-role bundle.' );

			$audit_before = self::audit_context_count();
			$result       = CYWater_Operations_Admin::update_user_operational_roles( $subject->ID, array( CYWater_Operations_Roles::PROGRAM_REVIEWER ) );
			$readback     = get_user_by( 'id', $subject->ID );
			$managed      = $readback instanceof WP_User ? array_values( array_intersect( CYWater_Operations_Roles::role_slugs(), (array) $readback->roles ) ) : array();
			self::assert_true( true === $result && array( CYWater_Operations_Roles::PROGRAM_REVIEWER ) === $managed, 'A successful assignment stores the exact requested managed-role bundle.' );
			self::assert_true( $audit_before + 1 === self::audit_context_count(), 'A successful role-bundle assignment creates exactly one audit row.' );
		} finally {
			wp_set_current_user( $previous_user_id );
		}
	}

	/**
	 * Partnership reviewers may update the review record but every destructive
	 * primitive is mapped to a separate Administrator-recovery capability.
	 */
	private static function test_partner_delete_boundaries() {
		$role = get_role( CYWater_Operations_Roles::GOVERNANCE_APPROVER );
		self::assert_true( $role instanceof WP_Role, 'The governance bundle exists for the Partner deletion boundary probe.' );

		$post_type = get_post_type_object( 'cyw_partner_app' );
		self::assert_true( $post_type instanceof WP_Post_Type, 'The Partner application post type is registered.' );
		if ( ! $role instanceof WP_Role || ! $post_type instanceof WP_Post_Type ) {
			return;
		}

		foreach ( array( 'delete_post', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts' ) as $property ) {
			$capability = isset( $post_type->cap->{$property} ) ? (string) $post_type->cap->{$property} : '';
			self::assert_true( '' !== $capability && 'cywater_review_partnerships' !== $capability, 'Partner ' . $property . ' is not mapped to the review capability.' );
			self::assert_true( ! $role->has_cap( $capability ), 'Partner reviewer is denied ' . $property . '.' );
		}
	}

	/**
	 * Simulate an upgrade from a legacy managed-cap list which incorrectly
	 * included WordPress core Administrator capabilities. The role migration
	 * must never interpret those core capabilities as plugin-owned stale caps.
	 */
	private static function test_administrator_core_cap_migration() {
		$administrator = get_role( 'administrator' );
		self::assert_true( $administrator instanceof WP_Role, 'Administrator exists before the legacy-cap migration probe.' );
		if ( ! $administrator instanceof WP_Role ) {
			return;
		}

		$core_caps           = array( 'upload_files', 'edit_posts' );
		$obsolete_plugin_cap = 'cywater_fulfill_logo_reward';
		foreach ( $core_caps as $capability ) {
			$administrator->add_cap( $capability, true );
		}
		$administrator->add_cap( $obsolete_plugin_cap, true );

		$previous = array_values(
			array_unique(
				array_merge(
					(array) get_option( 'cywater_operations_managed_caps', array() ),
					$core_caps,
					array( $obsolete_plugin_cap )
				)
			)
		);
		update_option( 'cywater_operations_managed_caps', $previous, false );
		CYWater_Operations_Roles::install();

		$administrator = get_role( 'administrator' );
		$preserved     = array();
		foreach ( $core_caps as $capability ) {
			$preserved[ $capability ] = $administrator instanceof WP_Role && $administrator->has_cap( $capability );
			if ( ! $preserved[ $capability ] && $administrator instanceof WP_Role ) {
				// Keep the self-cleaning promise even when the assertion exposes a
				// migration regression.
				$administrator->add_cap( $capability, true );
			}
		}
		foreach ( $core_caps as $capability ) {
			self::assert_true( $preserved[ $capability ], 'Administrator migration preserves WordPress core capability ' . $capability . '.' );
		}
		self::assert_true( $administrator instanceof WP_Role && ! $administrator->has_cap( $obsolete_plugin_cap ), 'Administrator migration removes the obsolete plugin-owned Logo reward capability.' );
	}

	/**
	 * @param array<int, int> $created_user_ids User cleanup list.
	 * @return WP_User
	 */
	private static function create_user( $login, $email, &$created_user_ids ) {
		$user_id = wp_create_user( $login, wp_generate_password( 32, true, true ), $email );
		self::assert_true( ! is_wp_error( $user_id ), 'Temporary QA account was created.' );
		if ( is_wp_error( $user_id ) ) {
			throw new RuntimeException( $user_id->get_error_message() );
		}

		$created_user_ids[] = (int) $user_id;
		$user               = get_user_by( 'id', $user_id );
		self::assert_true( $user instanceof WP_User, 'Temporary QA account can be read back.' );
		$user->set_role( 'subscriber' );
		return $user;
	}

	/**
	 * The Program Reviewer has one nonce-bound review workflow and no native
	 * Logo-entry edit primitives. WordPress may map edit_post to the dedicated
	 * review capability only for the exact private entry named by that request,
	 * or for the matching protected source/lockup asset request.
	 *
	 * @param WP_User        $program          Program reviewer account.
	 * @param WP_User        $author           Different entry author.
	 * @param array<int,int> $created_post_ids Cleanup list.
	 */
	private static function test_logo_review_workflow( $program, $author, &$created_post_ids, $suffix ) {
		self::assert_true( class_exists( 'CYWater_Logo_Call' ) && class_exists( 'CYWater_Operations_Logo_Review' ) && post_type_exists( 'cyw_logo_entry' ), 'The removable Logo Call and dedicated least-privilege review workflow are available.' );
		self::assert_true( (bool) has_filter( 'map_meta_cap', array( 'CYWater_Operations_Logo_Review', 'map_request_scoped_capability' ) ), 'The nonce-bound Logo review capability adapter is registered.' );
		self::assert_true( false !== has_action( 'admin_post_' . CYWater_Operations_Logo_Review::ACTION, array( 'CYWater_Operations_Logo_Review', 'handle_review' ) ), 'The dedicated Logo review admin-post handler is registered.' );
		self::assert_true( false !== has_action( 'admin_post_cywater_logo_asset', array( 'CYWater_Logo_Call', 'stream_asset' ) ), 'The protected Logo asset admin-post handler is registered.' );

		$entry_id = wp_insert_post(
			array(
				'post_type'   => 'cyw_logo_entry',
				'post_status' => 'private',
				'post_title'  => 'Operations QA Logo entry ' . $suffix,
				'post_author' => $author->ID,
			),
			true
		);
		self::assert_true( ! is_wp_error( $entry_id ) && $entry_id > 0, 'Temporary Logo entry was created outside the reviewer workflow.' );
		if ( is_wp_error( $entry_id ) ) {
			throw new RuntimeException( $entry_id->get_error_message() );
		}
		$created_post_ids[] = (int) $entry_id;
		update_post_meta( $entry_id, '_cywater_logo_status', 'submitted' );
		update_post_meta( $entry_id, '_cywater_logo_reward_status', 'not_applicable' );

		$wrong_type_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'private',
				'post_title'  => 'Operations QA wrong review type ' . $suffix,
				'post_author' => $author->ID,
			),
			true
		);
		self::assert_true( ! is_wp_error( $wrong_type_id ) && $wrong_type_id > 0, 'Temporary non-Logo record was created for the type-confusion boundary.' );
		if ( is_wp_error( $wrong_type_id ) ) {
			throw new RuntimeException( $wrong_type_id->get_error_message() );
		}
		$created_post_ids[] = (int) $wrong_type_id;

		wp_set_current_user( $program->ID );
		self::assert_true( ! user_can( $program, 'edit_post', $entry_id ), 'Program Reviewer cannot ordinarily edit another account\'s private Logo entry.' );

		$immutable_before         = get_post( $entry_id );
		$original_post            = $_POST;
		$original_get             = $_GET;
		$had_request_method       = isset( $_SERVER['REQUEST_METHOD'] );
		$original_request_method = $had_request_method ? $_SERVER['REQUEST_METHOD'] : null;
		$probe_action_capability  = static function ( $hook, $live_handler, $probe ) {
			$live_priority = has_action( $hook, $live_handler );
			if ( false !== $live_priority ) {
				remove_action( $hook, $live_handler, $live_priority );
			}

			$result = false;
			$runner = static function () use ( &$result, $probe ) {
				$result = (bool) call_user_func( $probe );
			};
			add_action( $hook, $runner, PHP_INT_MAX );
			try {
				do_action( $hook );
			} finally {
				remove_action( $hook, $runner, PHP_INT_MAX );
				if ( false !== $live_priority ) {
					add_action( $hook, $live_handler, $live_priority );
				}
			}

			return $result;
		};
		$review_hook             = 'admin_post_' . CYWater_Operations_Logo_Review::ACTION;
		$review_handler          = array( 'CYWater_Operations_Logo_Review', 'handle_review' );
		$asset_hook              = 'admin_post_cywater_logo_asset';
		$asset_handler           = array( 'CYWater_Logo_Call', 'stream_asset' );
		try {
			$_GET                      = array();
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST                     = array(
				'action'                              => CYWater_Operations_Logo_Review::ACTION,
				'entry_id'                            => $entry_id,
				CYWater_Operations_Logo_Review::NONCE_FIELD => wp_create_nonce( CYWater_Operations_Logo_Review::NONCE_ACTION_PREFIX . $entry_id ),
				'cywater_logo_review_nonce'           => wp_create_nonce( 'cywater_logo_review' ),
				'cywater_logo_status'                 => 'shortlisted',
				'cywater_logo_reward_status'          => 'fulfilled',
				'post_title'                          => 'forged-title-' . $suffix,
				'post_author'                         => $program->ID,
				'post_status'                         => 'publish',
				'cywater_logo_source'                 => 'forged-source-' . $suffix,
			);
			$mapped = $probe_action_capability(
				$review_hook,
				$review_handler,
				static function () use ( $program, $entry_id ) {
					return user_can( $program, 'edit_post', $entry_id );
				}
			);
			self::assert_true( $mapped, 'The exact nonce-bound review action maps only its target entry to the dedicated review capability.' );

			$result = CYWater_Operations_Logo_Review::transition( $entry_id, 'shortlisted', 'fulfilled' );
			self::assert_true( true === $result, 'The dedicated Logo review transition accepts a shortlist decision.' );
			self::assert_true( 'shortlisted' === get_post_meta( $entry_id, '_cywater_logo_status', true ), 'Program Reviewer records the shortlist through the one real Logo Call storage workflow.' );
			self::assert_true( 'not_applicable' === get_post_meta( $entry_id, '_cywater_logo_reward_status', true ), 'A shortlist cannot falsely record reward fulfillment.' );

			$entry_after_shortlist = get_post( $entry_id );
			self::assert_true(
				$immutable_before instanceof WP_Post
				&& $entry_after_shortlist instanceof WP_Post
				&& $immutable_before->post_title === $entry_after_shortlist->post_title
				&& (int) $immutable_before->post_author === (int) $entry_after_shortlist->post_author
				&& $immutable_before->post_status === $entry_after_shortlist->post_status,
				'Forged title, author and publication fields cannot escape the allowlisted review workflow.'
			);

			$result = CYWater_Operations_Logo_Review::transition( $entry_id, 'selected', 'fulfilled' );
			self::assert_true( true === $result, 'The dedicated Logo review transition accepts a selected decision and its fulfillment state.' );
			self::assert_true( 'selected' === get_post_meta( $entry_id, '_cywater_logo_status', true ) && 'fulfilled' === get_post_meta( $entry_id, '_cywater_logo_reward_status', true ), 'The same review workflow records selection and reward fulfillment without a separate capability.' );

			$result = CYWater_Operations_Logo_Review::transition( $entry_id, 'forged_status', 'fulfilled' );
			self::assert_true( is_wp_error( $result ) && 'cywater_logo_review_status' === $result->get_error_code(), 'The dedicated review transition rejects a forged status value.' );
			self::assert_true( 'selected' === get_post_meta( $entry_id, '_cywater_logo_status', true ) && 'fulfilled' === get_post_meta( $entry_id, '_cywater_logo_reward_status', true ), 'A forged review status leaves both allowlisted review fields unchanged.' );

			$result = CYWater_Operations_Logo_Review::transition( $wrong_type_id, 'selected', 'fulfilled' );
			self::assert_true( is_wp_error( $result ) && 'cywater_logo_review_unavailable' === $result->get_error_code(), 'The dedicated review transition rejects a non-Logo post type.' );

			$audit_before  = self::audit_context_count();
			$captured_rows = array();
			$fail_audit    = static function ( $allowed, $row ) use ( &$captured_rows ) {
				if ( 'logo_entry' === ( $row['object_type'] ?? '' ) ) {
					$captured_rows[] = $row;
					return false;
				}
				return $allowed;
			};
			add_filter( 'cywater_operations_audit_before_insert', $fail_audit, 10, 2 );
			try {
				$result = CYWater_Operations_Logo_Review::transition( $entry_id, 'not_selected', 'not_applicable' );
			} finally {
				remove_filter( 'cywater_operations_audit_before_insert', $fail_audit, 10 );
			}
			self::assert_true( is_wp_error( $result ) && 'cywater_logo_review_audit' === $result->get_error_code(), 'Logo review fails closed when its strict pre-commit audit insert is unavailable.' );
			self::assert_true( 'selected' === get_post_meta( $entry_id, '_cywater_logo_status', true ) && 'fulfilled' === get_post_meta( $entry_id, '_cywater_logo_reward_status', true ), 'Logo review audit failure leaves both review fields unchanged.' );
			self::assert_true( $audit_before === self::audit_context_count(), 'Logo review audit failure inserts no partial audit row.' );
			$captured = wp_json_encode( $captured_rows );
			self::assert_true( false === stripos( $captured, 'Operations QA Logo entry ' . $suffix ) && false === stripos( $captured, 'forged-title-' . $suffix ) && false === stripos( $captured, '@example.invalid' ), 'The failed Logo review audit request contains only redacted IDs, action and states.' );

			$valid_review_nonce = $_POST[ CYWater_Operations_Logo_Review::NONCE_FIELD ];
			$_POST[ CYWater_Operations_Logo_Review::NONCE_FIELD ] = 'invalid-review-nonce';
			$mapped = $probe_action_capability(
				$review_hook,
				$review_handler,
				static function () use ( $program, $entry_id ) {
					return user_can( $program, 'edit_post', $entry_id );
				}
			);
			self::assert_true( ! $mapped, 'An invalid dedicated review nonce cannot map edit_post inside the review action.' );

			$_POST[ CYWater_Operations_Logo_Review::NONCE_FIELD ] = $valid_review_nonce;
			$_POST['action'] = 'cywater_operations_logo_review_forged';
			$mapped = $probe_action_capability(
				$review_hook,
				$review_handler,
				static function () use ( $program, $entry_id ) {
					return user_can( $program, 'edit_post', $entry_id );
				}
			);
			self::assert_true( ! $mapped, 'A different POST action cannot reuse the dedicated review nonce inside the review action.' );

			$_POST['action']   = CYWater_Operations_Logo_Review::ACTION;
			$_POST['entry_id'] = $entry_id + 1;
			$mapped = $probe_action_capability(
				$review_hook,
				$review_handler,
				static function () use ( $program, $entry_id ) {
					return user_can( $program, 'edit_post', $entry_id );
				}
			);
			self::assert_true( ! $mapped, 'A review request cannot reuse its nonce for a different entry ID.' );

			$_POST['entry_id'] = $wrong_type_id;
			$_POST[ CYWater_Operations_Logo_Review::NONCE_FIELD ] = wp_create_nonce( CYWater_Operations_Logo_Review::NONCE_ACTION_PREFIX . $wrong_type_id );
			$mapped = $probe_action_capability(
				$review_hook,
				$review_handler,
				static function () use ( $program, $wrong_type_id ) {
					return user_can( $program, 'edit_post', $wrong_type_id );
				}
			);
			self::assert_true( ! $mapped, 'A valid-shaped review request cannot target a different post type.' );

			$_POST                     = array();
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$_GET                      = array(
				'action'   => 'cywater_logo_asset',
				'entry'    => $entry_id,
				'kind'     => 'source',
				'_wpnonce' => wp_create_nonce( 'cywater_logo_asset_' . $entry_id . '_source' ),
			);
			$mapped = $probe_action_capability(
				$asset_hook,
				$asset_handler,
				static function () use ( $program, $entry_id ) {
					return user_can( $program, 'edit_post', $entry_id );
				}
			);
			self::assert_true( $mapped, 'The exact nonce-bound protected source action maps only its target entry.' );

			$_GET['_wpnonce'] = 'invalid-asset-nonce';
			$mapped = $probe_action_capability(
				$asset_hook,
				$asset_handler,
				static function () use ( $program, $entry_id ) {
					return user_can( $program, 'edit_post', $entry_id );
				}
			);
			self::assert_true( ! $mapped, 'An invalid protected-asset nonce cannot map edit_post inside the asset action.' );

			$_GET['kind']     = 'preview';
			$_GET['_wpnonce'] = wp_create_nonce( 'cywater_logo_asset_' . $entry_id . '_preview' );
			$mapped = $probe_action_capability(
				$asset_hook,
				$asset_handler,
				static function () use ( $program, $entry_id ) {
					return user_can( $program, 'edit_post', $entry_id );
				}
			);
			self::assert_true( ! $mapped, 'An unsupported protected-asset kind cannot map edit_post.' );

			$_GET['kind']     = 'lockup';
			$_GET['_wpnonce'] = wp_create_nonce( 'cywater_logo_asset_' . $entry_id . '_lockup' );
			$mapped = $probe_action_capability(
				$asset_hook,
				$asset_handler,
				static function () use ( $program, $entry_id ) {
					return user_can( $program, 'edit_post', $entry_id );
				}
			);
			self::assert_true( $mapped, 'The exact nonce-bound protected lockup action maps only its target entry.' );

			$_GET['entry'] = $entry_id + 1;
			$mapped = $probe_action_capability(
				$asset_hook,
				$asset_handler,
				static function () use ( $program, $entry_id ) {
					return user_can( $program, 'edit_post', $entry_id );
				}
			);
			self::assert_true( ! $mapped, 'A protected-asset request cannot reuse its nonce for a different entry ID.' );
		} finally {
			$_POST = $original_post;
			$_GET  = $original_get;
			if ( $had_request_method ) {
				$_SERVER['REQUEST_METHOD'] = $original_request_method;
			} else {
				unset( $_SERVER['REQUEST_METHOD'] );
			}
		}

		self::assert_true( ! user_can( $program, 'edit_post', $entry_id ), 'Program Reviewer loses request-scoped edit_post immediately after the exact review or asset request ends.' );
		$post_type = get_post_type_object( 'cyw_logo_entry' );
		self::assert_true( $post_type instanceof WP_Post_Type, 'The Logo entry capability map can be inspected.' );
		if ( $post_type instanceof WP_Post_Type ) {
			self::assert_true( 'do_not_allow' === (string) $post_type->cap->create_posts && 'do_not_allow' === (string) $post_type->cap->publish_posts, 'Logo entries cannot be created or published from the review role.' );
			self::assert_true( ! user_can( $program, 'delete_post', $entry_id ), 'Program Reviewer cannot delete the reviewed Logo entry.' );
			foreach ( array( 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts' ) as $property ) {
				$capability = isset( $post_type->cap->{$property} ) ? (string) $post_type->cap->{$property} : '';
				self::assert_true( '' !== $capability && ! user_can( $program, $capability ), 'Program Reviewer is denied Logo ' . $property . '.' );
			}
			self::assert_true( ! $post_type->show_in_rest, 'Private Logo entries have no REST collection or deletion route.' );
		}
	}

	/**
	 * A partnership review must be atomic with its required audit record. Audit
	 * failure must not change stage, payment handoff, notes, access token, or mail.
	 *
	 * @param WP_User        $governance      Governance account.
	 * @param array<int,int> $created_post_ids Cleanup list.
	 */
	private static function test_partnership_audit_failure( $governance, &$created_post_ids, $suffix ) {
		self::assert_true( class_exists( 'CYWater_Partnerships' ) && post_type_exists( 'cyw_partner_app' ), 'The partnership review workflow is available.' );
		self::assert_true( (bool) has_filter( 'cywater_partnership_review_transition_allowed', array( 'CYWater_Operations_Integrations', 'authorize_partnership_review' ) ), 'Partnership transitions are connected to the strict audit adapter.' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'cyw_partner_app',
				'post_status' => 'private',
				'post_title'  => 'Operations QA Partner application ' . $suffix,
			),
			true
		);
		self::assert_true( ! is_wp_error( $post_id ) && $post_id > 0, 'Temporary Partner application was created.' );
		if ( is_wp_error( $post_id ) ) {
			throw new RuntimeException( $post_id->get_error_message() );
		}
		$created_post_ids[] = (int) $post_id;
		self::assert_true( ! user_can( $governance, 'delete_post', $post_id ), 'Partner reviewer cannot delete a real Partner application.' );

		$prefix       = CYWater_Partnerships::META_PREFIX;
		$initial_note = 'retained-note-' . $suffix;
		$initial_hash = 'retained-token-hash-' . $suffix;
		update_post_meta( $post_id, $prefix . 'stage', 'submitted' );
		update_post_meta( $post_id, $prefix . 'payment_url', '' );
		update_post_meta( $post_id, $prefix . 'notes', $initial_note );
		update_post_meta( $post_id, $prefix . 'access_hash', $initial_hash );
		update_post_meta( $post_id, $prefix . 'contact_email', 'cyw-ops-partner-' . $suffix . '@example.invalid' );

		$mail_calls     = 0;
		$intercept_mail = static function ( $return, $atts ) use ( &$mail_calls ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			++$mail_calls;
			return true;
		};
		$original_post  = $_POST;
		$audit_before   = self::audit_context_count();
		$adapter        = array( 'CYWater_Operations_Integrations', 'authorize_partnership_review' );

		wp_set_current_user( $governance->ID );
		add_filter( 'pre_wp_mail', $intercept_mail, 10, 2 );
		remove_filter( 'cywater_partnership_review_transition_allowed', $adapter, 10 );
		try {
			$_POST = array(
				'cywater_partner_review_nonce' => wp_create_nonce( 'cywater_partner_review' ),
				'cywater_partner_stage'        => 'approved',
				'cywater_partner_payment_url'  => 'https://example.invalid/no-adapter-' . $suffix,
				'cywater_partner_notes'        => 'must-not-persist-without-adapter-' . $suffix,
			);
			CYWater_Partnerships::save_review( $post_id, get_post( $post_id ) );
		} finally {
			add_filter( 'cywater_partnership_review_transition_allowed', $adapter, 10, 6 );
			$_POST = $original_post;
		}

		self::assert_true( 'submitted' === get_post_meta( $post_id, $prefix . 'stage', true ) && '' === get_post_meta( $post_id, $prefix . 'payment_url', true ), 'A missing Operations adapter fails a material Partner review closed.' );
		self::assert_true( $initial_note === get_post_meta( $post_id, $prefix . 'notes', true ) && $initial_hash === get_post_meta( $post_id, $prefix . 'access_hash', true ), 'A missing Operations adapter leaves Partner notes and access token unchanged.' );
		self::assert_true( 0 === $mail_calls && $audit_before === self::audit_context_count(), 'A missing Operations adapter sends no mail and creates no audit row.' );

		$captured_rows = array();
		$fail_insert   = static function ( $allowed, $row ) use ( &$captured_rows ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
			$captured_rows[] = $row;
			return false;
		};

		add_filter( 'cywater_operations_audit_before_insert', $fail_insert, 10, 2 );
		try {
			$_POST = array(
				'cywater_partner_review_nonce' => wp_create_nonce( 'cywater_partner_review' ),
				'cywater_partner_stage'        => 'approved',
				'cywater_partner_payment_url'  => 'https://example.invalid/qa-payment-' . $suffix,
				'cywater_partner_notes'        => 'must-not-persist-' . $suffix,
			);
			CYWater_Partnerships::save_review( $post_id, get_post( $post_id ) );

			self::assert_true( 'submitted' === get_post_meta( $post_id, $prefix . 'stage', true ) && '' === get_post_meta( $post_id, $prefix . 'payment_url', true ), 'Failed stage approval leaves the Partner stage and payment URL unchanged.' );
			self::assert_true( $initial_note === get_post_meta( $post_id, $prefix . 'notes', true ) && $initial_hash === get_post_meta( $post_id, $prefix . 'access_hash', true ), 'Failed stage approval leaves notes and access token unchanged.' );

			$old_url = 'https://example.invalid/original-payment-' . $suffix;
			update_post_meta( $post_id, $prefix . 'stage', 'approved' );
			update_post_meta( $post_id, $prefix . 'payment_url', $old_url );
			$_POST = array(
				'cywater_partner_review_nonce' => wp_create_nonce( 'cywater_partner_review' ),
				'cywater_partner_stage'        => 'approved',
				'cywater_partner_payment_url'  => 'https://example.invalid/replacement-payment-' . $suffix,
				'cywater_partner_notes'        => 'still-must-not-persist-' . $suffix,
			);
			CYWater_Partnerships::save_review( $post_id, get_post( $post_id ) );
			self::assert_true( 'approved' === get_post_meta( $post_id, $prefix . 'stage', true ) && $old_url === get_post_meta( $post_id, $prefix . 'payment_url', true ), 'Failed payment-handoff audit leaves the approved stage and prior URL unchanged.' );
			self::assert_true( $initial_note === get_post_meta( $post_id, $prefix . 'notes', true ) && $initial_hash === get_post_meta( $post_id, $prefix . 'access_hash', true ), 'Failed payment-handoff audit leaves notes and access token unchanged.' );
		} finally {
			$_POST = $original_post;
			remove_filter( 'cywater_operations_audit_before_insert', $fail_insert, 10 );
			remove_filter( 'pre_wp_mail', $intercept_mail, 10 );
		}

		self::assert_true( 0 === $mail_calls, 'Failed partnership transitions send no status mail.' );
		self::assert_true( $audit_before === self::audit_context_count(), 'Failed partnership transitions insert no partial audit row.' );
		$captured = wp_json_encode( $captured_rows );
		self::assert_true( false === stripos( $captured, 'example.invalid' ) && false === stripos( $captured, $initial_note ) && false === stripos( $captured, $initial_hash ), 'Partnership audit attempts contain no email, notes, token, or payment URL.' );
	}

	/**
	 * @param WP_User        $editor          Content editor account.
	 * @param WP_User        $governance      Governance account.
	 * @param array<int,int> $created_post_ids Event cleanup list.
	 */
	private static function test_paid_event_workflow( $editor, $governance, &$created_post_ids, $suffix ) {
		self::assert_true( post_type_exists( 'cyw_event' ), 'The CYWater Event post type is registered.' );

		$title    = 'Operations QA paid Event ' . $suffix;
		$event_id = wp_insert_post(
			array(
				'post_type'   => 'cyw_event',
				'post_status' => 'draft',
				'post_title'  => $title,
				'post_author' => $editor->ID,
			),
			true
		);
		self::assert_true( ! is_wp_error( $event_id ) && $event_id > 0, 'Temporary paid Event was created.' );
		if ( is_wp_error( $event_id ) ) {
			throw new RuntimeException( $event_id->get_error_message() );
		}
		$created_post_ids[] = (int) $event_id;

		self::assert_true( user_can( $editor, 'edit_post', $event_id ), 'Content editor can edit its Event.' );
		self::assert_true( ! user_can( $governance, 'edit_post', $event_id ), 'Governance approval does not grant Event editing.' );

		wp_set_current_user( $editor->ID );
		update_post_meta( $event_id, CYWater_Paid_Event_Approval::META_PAID, '1' );
		self::assert_true( ! CYWater_Paid_Event_Approval::requirements_complete( $event_id ), 'An incomplete paid Event is not ready for approval.' );
		$result = CYWater_Paid_Event_Approval::editor_transition( $event_id, 'terms_complete', $editor->ID );
		self::assert_true( is_wp_error( $result ) && 'incomplete' === $result->get_error_code(), 'Incomplete terms cannot enter the approval workflow.' );
		self::assert_true( ! CYWater_Paid_Event_Approval::is_payment_ready( $event_id ), 'Incomplete terms cannot expose paid registration.' );

		$terms_marker = 'qa-policy-' . $suffix;
		$meta          = array(
			'_cyw_start_date'                              => '2027-02-15',
			'_cyw_location'                                => 'Online',
			CYWater_Paid_Event_Approval::META_FEE          => '25.00',
			CYWater_Paid_Event_Approval::META_CURRENCY     => 'USD',
			CYWater_Paid_Event_Approval::META_DEADLINE     => '2027-02-01',
			CYWater_Paid_Event_Approval::META_REFUND       => 'Event-specific final sale terms ' . $terms_marker . ' are displayed before payment.',
			CYWater_Paid_Event_Approval::META_TRANSFER     => 'Registrant substitutions require written approval before the published deadline.',
			CYWater_Paid_Event_Approval::META_CAPACITY     => 'Capacity is limited and the waitlist does not guarantee admission to this Event.',
			CYWater_Paid_Event_Approval::META_CHANGES      => 'If CYWater postpones, cancels, or changes format, registrants receive written notice.',
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $event_id, $key, $value );
		}
		self::assert_true( CYWater_Paid_Event_Approval::requirements_complete( $event_id ), 'All paid-Event requirements can be completed.' );

		$result = CYWater_Paid_Event_Approval::editor_transition( $event_id, 'terms_complete', $editor->ID );
		self::assert_true( true === $result && CYWater_Paid_Event_Approval::STATE_TERMS_COMPLETE === CYWater_Paid_Event_Approval::state( $event_id ), 'Content editor marks the complete terms ready for submission.' );

		$default_adapter = CYWater_Paid_Event_Approval::adapter_snapshot( $event_id );
		self::assert_true( is_wp_error( $default_adapter ), 'Paid Events fail closed while the real ticket adapter cannot prove a ready Commerce configuration.' );
		$result = CYWater_Paid_Event_Approval::editor_transition( $event_id, 'submit', $editor->ID );
		self::assert_true( is_wp_error( $result ) && is_wp_error( $default_adapter ) && $default_adapter->get_error_code() === $result->get_error_code(), 'Submission is refused with the adapter fail-closed reason until a verified snapshot is available.' );
		self::assert_true( CYWater_Paid_Event_Approval::STATE_TERMS_COMPLETE === CYWater_Paid_Event_Approval::state( $event_id ), 'A missing adapter leaves the paid-Event state at terms complete.' );
		self::assert_true( '' === get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_SUBMIT_HASH, true ) && '' === get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_SUBMIT_SNAPSHOT, true ), 'A missing adapter leaves no submission side metadata.' );
		self::assert_true( ! CYWater_Paid_Event_Approval::is_payment_ready( $event_id ), 'A missing adapter cannot expose paid registration.' );

		$mismatched_adapter = static function ( $snapshot, $candidate_event_id ) use ( $event_id ) {
			if ( (int) $candidate_event_id !== (int) $event_id ) {
				return $snapshot;
			}
			return array(
				'provider'       => 'event_tickets',
				'ticket_id'      => 'qa-ticket-mismatch',
				'enabled'        => true,
				'amount'         => '26.00',
				'currency'       => 'USD',
				'capacity'       => 100,
				'checkout_ready' => true,
			);
		};
		add_filter( 'cywater_operations_paid_event_adapter_snapshot', $mismatched_adapter, 10, 2 );
		try {
			$mismatch_result = CYWater_Paid_Event_Approval::adapter_snapshot( $event_id );
		} finally {
			remove_filter( 'cywater_operations_paid_event_adapter_snapshot', $mismatched_adapter, 10 );
		}
		self::assert_true( is_wp_error( $mismatch_result ) && 'adapter_terms_mismatch' === $mismatch_result->get_error_code(), 'A ticket snapshot with a mismatched amount is rejected.' );

		$not_ready_adapter = static function ( $snapshot, $candidate_event_id ) use ( $event_id ) {
			if ( (int) $candidate_event_id !== (int) $event_id ) {
				return $snapshot;
			}
			return array(
				'provider'       => 'event_tickets',
				'ticket_id'      => 'qa-ticket-not-ready',
				'enabled'        => true,
				'amount'         => '25.00',
				'currency'       => 'USD',
				'capacity'       => 100,
				'checkout_ready' => false,
			);
		};
		add_filter( 'cywater_operations_paid_event_adapter_snapshot', $not_ready_adapter, 10, 2 );
		try {
			$not_ready_result = CYWater_Paid_Event_Approval::adapter_snapshot( $event_id );
		} finally {
			remove_filter( 'cywater_operations_paid_event_adapter_snapshot', $not_ready_adapter, 10 );
		}
		self::assert_true( is_wp_error( $not_ready_result ) && 'adapter_not_ready' === $not_ready_result->get_error_code(), 'A ticket snapshot whose checkout is not ready is rejected.' );

		$valid_adapter = static function ( $snapshot, $candidate_event_id ) use ( $event_id ) {
			if ( (int) $candidate_event_id !== (int) $event_id ) {
				return $snapshot;
			}
			return array(
				'provider'       => 'event_tickets',
				'ticket_id'      => 'qa-ticket-1',
				'enabled'        => true,
				'amount'         => '25.00',
				'currency'       => 'USD',
				'capacity'       => 100,
				'checkout_ready' => true,
			);
		};
		$submit_result      = null;
		$state_after_submit = '';
		$editor_approval    = null;
		$approve_result     = null;
		$state_after_approve = '';
		$governance_open    = null;
		$open_result        = null;
		$state_after_open   = '';
		$ready_with_adapter = false;
		add_filter( 'cywater_operations_paid_event_adapter_snapshot', $valid_adapter, 10, 2 );
		try {
			wp_set_current_user( $editor->ID );
			self::test_failed_submission_write( $event_id, $editor->ID );
			self::test_failed_submission_audit( $event_id, $editor->ID, $title, $terms_marker );
			$submit_result      = CYWater_Paid_Event_Approval::editor_transition( $event_id, 'submit', $editor->ID );
			$state_after_submit = CYWater_Paid_Event_Approval::state( $event_id );
			$editor_approval    = CYWater_Paid_Event_Approval::governance_decision( $event_id, 'approve', $editor->ID );

			wp_set_current_user( $governance->ID );
			self::test_failed_governance_write( $event_id, $governance->ID );
			self::test_failed_governance_audit( $event_id, $governance->ID, $title, $terms_marker );
			$approve_result      = CYWater_Paid_Event_Approval::governance_decision( $event_id, 'approve', $governance->ID );
			$state_after_approve = CYWater_Paid_Event_Approval::state( $event_id );
			$governance_open     = CYWater_Paid_Event_Approval::editor_transition( $event_id, 'open', $governance->ID );

			wp_set_current_user( $editor->ID );
			$open_result        = CYWater_Paid_Event_Approval::editor_transition( $event_id, 'open', $editor->ID );
			$state_after_open   = CYWater_Paid_Event_Approval::state( $event_id );
			$ready_with_adapter = CYWater_Paid_Event_Approval::is_payment_ready( $event_id );
		} finally {
			remove_filter( 'cywater_operations_paid_event_adapter_snapshot', $valid_adapter, 10 );
		}
		self::assert_true( true === $submit_result && CYWater_Paid_Event_Approval::STATE_PENDING_APPROVAL === $state_after_submit, 'Content editor submits an immutable Event-and-ticket snapshot for approval.' );
		self::assert_true( '' !== get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_SUBMIT_HASH, true ) && '' !== get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_SUBMIT_SNAPSHOT, true ), 'Successful submission stores both its fingerprint and redacted snapshot.' );
		self::assert_true( is_wp_error( $editor_approval ) && 'forbidden' === $editor_approval->get_error_code(), 'Event editor cannot approve its own paid Event.' );
		self::assert_true( true === $approve_result && CYWater_Paid_Event_Approval::STATE_APPROVED === $state_after_approve, 'Independent governance account approves the current submitted Event-and-ticket snapshot.' );
		$approval_hash = (string) get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_APPROVAL_HASH, true );
		$submit_hash   = (string) get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_SUBMIT_HASH, true );
		$approved_by   = (int) get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_APPROVED_BY, true );
		$approved_at   = (string) get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_APPROVED_AT, true );
		self::assert_true( '' !== $submit_hash && hash_equals( $submit_hash, $approval_hash ) && $governance->ID === $approved_by && 1 === preg_match( '/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $approved_at ), 'Successful governance approval has an exact fingerprint, actor, and UTC timestamp readback.' );
		self::assert_true( is_wp_error( $governance_open ) && 'forbidden' === $governance_open->get_error_code(), 'Governance approval alone cannot open registration.' );
		self::assert_true( true === $open_result && CYWater_Paid_Event_Approval::STATE_REGISTRATION_OPEN === $state_after_open, 'Content editor opens only the independently approved Event while its verified adapter snapshot matches.' );
		self::assert_true( $ready_with_adapter, 'Approved and open paid Event passes readiness while the structured QA adapter is available.' );
		self::assert_true( ! CYWater_Paid_Event_Approval::is_payment_ready( $event_id ), 'Removing the structured adapter immediately restores the paid-Event fail-closed gate.' );

		update_post_meta( $event_id, CYWater_Paid_Event_Approval::META_REFUND, 'Revised Event-specific cancellation terms ' . $terms_marker . ' require a new approval.' );
		self::assert_true( CYWater_Paid_Event_Approval::STATE_TERMS_COMPLETE === CYWater_Paid_Event_Approval::state( $event_id ), 'A material terms change invalidates approval and closes registration.' );
		self::assert_true( ! CYWater_Paid_Event_Approval::is_payment_ready( $event_id ), 'Invalidated approval cannot expose paid registration.' );
	}

	/** Simulate a metadata layer that reports success without storing the snapshot. */
	private static function test_failed_submission_write( $event_id, $editor_user_id ) {
		$audit_before = self::audit_context_count();
		$drop_snapshot = static function ( $check, $object_id, $meta_key ) use ( $event_id ) {
			if ( (int) $object_id === (int) $event_id && CYWater_Paid_Event_Approval::META_SUBMIT_SNAPSHOT === $meta_key ) {
				return true;
			}
			return $check;
		};

		add_filter( 'update_post_metadata', $drop_snapshot, 10, 3 );
		try {
			$result = CYWater_Paid_Event_Approval::editor_transition( $event_id, 'submit', $editor_user_id );
		} finally {
			remove_filter( 'update_post_metadata', $drop_snapshot, 10 );
		}

		self::assert_true( is_wp_error( $result ) && 'submission_write_failed' === $result->get_error_code(), 'Paid-Event submission rejects an unverified snapshot write.' );
		self::assert_true( CYWater_Paid_Event_Approval::STATE_TERMS_COMPLETE === CYWater_Paid_Event_Approval::state( $event_id ), 'Failed submission metadata readback leaves the workflow state unchanged.' );
		self::assert_true( '' === get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_SUBMIT_HASH, true ) && '' === get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_SUBMIT_SNAPSHOT, true ), 'Failed submission metadata readback removes all submission side metadata.' );
		self::assert_true( $audit_before === self::audit_context_count(), 'Failed submission metadata readback creates no audit authorization row.' );
	}

	/** Simulate a metadata layer that drops the approval timestamp write. */
	private static function test_failed_governance_write( $event_id, $governance_user_id ) {
		$audit_before = self::audit_context_count();
		$drop_timestamp = static function ( $check, $object_id, $meta_key ) use ( $event_id ) {
			if ( (int) $object_id === (int) $event_id && CYWater_Paid_Event_Approval::META_APPROVED_AT === $meta_key ) {
				return true;
			}
			return $check;
		};

		add_filter( 'update_post_metadata', $drop_timestamp, 10, 3 );
		try {
			$result = CYWater_Paid_Event_Approval::governance_decision( $event_id, 'approve', $governance_user_id );
		} finally {
			remove_filter( 'update_post_metadata', $drop_timestamp, 10 );
		}

		self::assert_true( is_wp_error( $result ) && 'approval_write_failed' === $result->get_error_code(), 'Paid-Event approval rejects an incomplete actor/timestamp record.' );
		self::assert_true( CYWater_Paid_Event_Approval::STATE_PENDING_APPROVAL === CYWater_Paid_Event_Approval::state( $event_id ), 'Failed approval metadata readback leaves the workflow pending.' );
		$approval_meta = array(
			get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_APPROVAL_HASH, true ),
			get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_APPROVED_BY, true ),
			get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_APPROVED_AT, true ),
		);
		self::assert_true( ! array_filter( $approval_meta ), 'Failed approval metadata readback removes the hash, actor, and timestamp together.' );
		self::assert_true( '' !== get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_SUBMIT_HASH, true ) && '' !== get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_SUBMIT_SNAPSHOT, true ), 'Failed approval metadata readback preserves the immutable submitted snapshot.' );
		self::assert_true( $audit_before === self::audit_context_count(), 'Failed approval metadata readback creates no audit authorization row.' );
	}

	/**
	 * Submission writes an immutable side snapshot before requesting its audit
	 * row. A simulated audit outage must roll those side writes back as well as
	 * leaving the public workflow state unchanged.
	 */
	private static function test_failed_submission_audit( $event_id, $editor_user_id, $event_title, $terms_marker ) {
		$captured_rows = array();
		$fail_insert   = static function ( $allowed, $row ) use ( &$captured_rows ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
			$captured_rows[] = $row;
			return false;
		};

		$state_before = CYWater_Paid_Event_Approval::state( $event_id );
		$audit_before = self::audit_context_count();
		$result       = null;

		add_filter( 'cywater_operations_audit_before_insert', $fail_insert, 10, 2 );
		try {
			$result = CYWater_Paid_Event_Approval::editor_transition( $event_id, 'submit', $editor_user_id );
		} finally {
			remove_filter( 'cywater_operations_audit_before_insert', $fail_insert, 10 );
		}

		self::assert_true( is_wp_error( $result ) && 'audit_unavailable' === $result->get_error_code(), 'Paid-Event submission fails closed when the audit insert is unavailable.' );
		self::assert_true( $state_before === CYWater_Paid_Event_Approval::state( $event_id ) && CYWater_Paid_Event_Approval::STATE_TERMS_COMPLETE === $state_before, 'Submission audit failure leaves the paid-Event workflow state unchanged.' );
		self::assert_true( '' === get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_SUBMIT_HASH, true ) && '' === get_post_meta( $event_id, CYWater_Paid_Event_Approval::META_SUBMIT_SNAPSHOT, true ), 'Submission audit failure rolls back its fingerprint and snapshot side metadata.' );
		self::assert_true( $audit_before === self::audit_context_count(), 'Submission audit failure inserts no partial audit row.' );

		$captured = wp_json_encode( $captured_rows );
		self::assert_true( false === stripos( $captured, (string) $event_title ) && false === stripos( $captured, (string) $terms_marker ) && false === stripos( $captured, '@example.invalid' ), 'The failed submission audit request contains only redacted identifiers and states.' );
	}

	/**
	 * A critical audit outage must leave the governance decision untouched and
	 * must not persist either approval side metadata or sensitive content.
	 */
	private static function test_failed_governance_audit( $event_id, $governance_user_id, $event_title, $terms_marker ) {
		$captured_rows = array();
		$fail_insert   = static function ( $allowed, $row ) use ( &$captured_rows ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
			$captured_rows[] = $row;
			return false;
		};

		$state_before = CYWater_Paid_Event_Approval::state( $event_id );
		$audit_before = self::audit_context_count();
		$result       = null;
		$state_after  = '';
		$audit_after  = -1;
		$side_meta    = array();

		add_filter( 'cywater_operations_audit_before_insert', $fail_insert, 10, 2 );
		try {
			$result      = CYWater_Paid_Event_Approval::governance_decision( $event_id, 'approve', $governance_user_id );
			$state_after = CYWater_Paid_Event_Approval::state( $event_id );
			$audit_after = self::audit_context_count();
			foreach ( array( CYWater_Paid_Event_Approval::META_APPROVAL_HASH, CYWater_Paid_Event_Approval::META_APPROVED_BY, CYWater_Paid_Event_Approval::META_APPROVED_AT ) as $meta_key ) {
				$side_meta[ $meta_key ] = get_post_meta( $event_id, $meta_key, true );
			}
		} finally {
			remove_filter( 'cywater_operations_audit_before_insert', $fail_insert, 10 );
		}

		self::assert_true( is_wp_error( $result ) && 'audit_unavailable' === $result->get_error_code(), 'Governance approval fails closed when the audit insert is unavailable.' );
		self::assert_true( $state_before === $state_after && CYWater_Paid_Event_Approval::STATE_PENDING_APPROVAL === $state_after, 'Audit failure leaves the paid-Event workflow state unchanged.' );
		self::assert_true( ! array_filter( $side_meta ), 'Audit failure leaves no approval hash, actor, or timestamp side metadata.' );
		self::assert_true( $audit_before === $audit_after, 'Audit failure inserts no partial audit row.' );

		$captured = wp_json_encode( $captured_rows );
		self::assert_true( false === stripos( $captured, (string) $event_title ) && false === stripos( $captured, (string) $terms_marker ) && false === stripos( $captured, '@example.invalid' ), 'The simulated audit request contains only redacted identifiers and states.' );
	}

	private static function audit_context_count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . CYWater_Operations_Audit::table_name() . ' WHERE reason = %s', CYWater_Operations_Audit::context() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private static function test_audit_redaction( $context, $suffix ) {
		global $wpdb;

		$table    = CYWater_Operations_Audit::table_name();
		$columns  = $wpdb->get_col( 'DESCRIBE ' . $table, 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$expected = array( 'id', 'occurred_at', 'actor_user_id', 'subject_user_id', 'object_type', 'object_id', 'action', 'from_state', 'to_state', 'reason' );
		sort( $columns );
		sort( $expected );
		self::assert_true( $columns === $expected, 'Audit schema contains only time, IDs, action, states, and reason.' );

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE reason = %s ORDER BY id ASC', $context ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::assert_true( count( $rows ) >= 6, 'Role and paid-Event transitions produced auditable QA rows.' );

		$serialized = wp_json_encode( $rows );
		self::assert_true( false === stripos( $serialized, '@example.invalid' ), 'Audit rows do not copy account email addresses.' );
		self::assert_true( false === stripos( $serialized, 'Operations QA paid Event ' . $suffix ), 'Audit rows do not copy Event titles.' );
		self::assert_true( false === stripos( $serialized, 'qa-policy-' . $suffix ), 'Audit rows do not copy policy or content text.' );
		foreach ( $rows as $row ) {
			self::assert_true( (bool) preg_match( '/^[a-z0-9_-]*$/', $row->object_type . $row->action . $row->from_state . $row->to_state . $row->reason ), 'Audit text fields contain identifier/state keys only.' );
		}
	}

	private static function assert_true( $condition, $message ) {
		++self::$checks;
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
	}
}
