<?php
/**
 * Composable, least-privilege operational role bundles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Operations_Roles {
	public const CONTENT_EDITOR      = 'cywater_content_event_editor';
	public const COMMUNITY_MODERATOR = 'cywater_community_moderator';
	public const PROGRAM_REVIEWER    = 'cywater_program_reviewer';
	public const GOVERNANCE_APPROVER = 'cywater_governance_approver';

	private const OPTION_CAPS_HASH = 'cywater_operations_caps_hash';
	private const OPTION_OLD_CAPS  = 'cywater_operations_managed_caps';
	private const RETIRED_CAPS     = array(
		'cywater_fulfill_logo_reward',
	);

	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ), 5 );
	}

	/** @return array<string, string> */
	public static function role_labels() {
		return array(
			self::CONTENT_EDITOR      => __( 'CYWater Content & Event Editor', 'cywater-operations' ),
			self::COMMUNITY_MODERATOR => __( 'CYWater Community Moderator', 'cywater-operations' ),
			self::PROGRAM_REVIEWER    => __( 'CYWater Program Reviewer', 'cywater-operations' ),
			self::GOVERNANCE_APPROVER => __( 'CYWater Governance Approver', 'cywater-operations' ),
		);
	}

	/** @return array<int, string> */
	public static function role_slugs() {
		return array_keys( self::role_labels() );
	}

	public static function is_managed_role( $role ) {
		return in_array( (string) $role, self::role_slugs(), true );
	}

	/**
	 * Primitive capabilities generated for a mapped custom post type.
	 * Singular edit/read/delete names are meta capabilities and are not stored.
	 *
	 * @return array<int, string>
	 */
	public static function post_type_capabilities( $singular, $plural ) {
		return array(
			"edit_{$plural}",
			"edit_others_{$plural}",
			"publish_{$plural}",
			"read_private_{$plural}",
			"delete_{$plural}",
			"delete_private_{$plural}",
			"delete_published_{$plural}",
			"delete_others_{$plural}",
			"edit_private_{$plural}",
			"edit_published_{$plural}",
			"create_{$plural}",
		);
	}

	/** @return array<string, array<int, string>> */
	public static function bundles() {
		$content_caps = array_merge(
			array(
				'read',
				'upload_files',
				'edit_posts',
				'edit_others_posts',
				'edit_published_posts',
				'edit_private_posts',
				'publish_posts',
				'read_private_posts',
				'delete_posts',
				'delete_others_posts',
				'delete_published_posts',
				'delete_private_posts',
				'assign_cyw_event_terms',
				'manage_cyw_event_terms',
				'edit_cyw_event_terms',
				'delete_cyw_event_terms',
				'cywater_configure_logo_call',
				'cywater_submit_paid_event_approval',
				'cywater_open_paid_event_registration',
			),
			self::post_type_capabilities( 'cyw_event', 'cyw_events' ),
			self::post_type_capabilities( 'cyw_award', 'cyw_awards' )
		);

		$moderator_caps = array(
			'read',
			'upload_files',
			'moderate_comments',
			'edit_cyw_forum_posts',
			'edit_others_cyw_forum_posts',
			'publish_cyw_forum_posts',
			'read_private_cyw_forum_posts',
			'delete_cyw_forum_posts',
			'delete_private_cyw_forum_posts',
			'delete_published_cyw_forum_posts',
			'delete_others_cyw_forum_posts',
			'edit_private_cyw_forum_posts',
			'edit_published_cyw_forum_posts',
			'create_cyw_forum_posts',
			'manage_cyw_forum_terms',
		);

		$program_caps = array(
			'read',
			'cywater_review_logo_entries',
		);

		$governance_caps = array_merge(
			array(
				'read',
				'cywater_review_logo_entries',
				'cywater_select_logo_finalists',
				'cywater_select_official_logo',
				'cywater_review_partnerships',
				'cywater_approve_partnerships',
				'cywater_record_partnership_payment',
				'cywater_approve_paid_event',
			),
			self::post_type_capabilities( 'cyw_board_role', 'cyw_board_roles' )
		);

		return array(
			self::CONTENT_EDITOR      => array_values( array_unique( $content_caps ) ),
			self::COMMUNITY_MODERATOR => array_values( array_unique( $moderator_caps ) ),
			self::PROGRAM_REVIEWER    => array_values( array_unique( $program_caps ) ),
			self::GOVERNANCE_APPROVER => array_values( array_unique( $governance_caps ) ),
		);
	}

	/** @return array<int, string> */
	public static function all_capabilities() {
		$all = array();
		foreach ( self::bundles() as $capabilities ) {
			$all = array_merge( $all, $capabilities );
		}
		return array_values(
			array_unique(
				array_merge(
					$all,
					array(
						'cywater_delete_logo_entries',
						'cywater_manage_logo_fulfillment',
						'cywater_delete_partnership_applications',
						// Recovery Administrators retain the native entry editor. Program
						// Reviewers use the dedicated, allowlisted review workflow only.
						'edit_cyw_logo_entries',
						'edit_others_cyw_logo_entries',
						'read_private_cyw_logo_entries',
						'edit_private_cyw_logo_entries',
						'edit_published_cyw_logo_entries',
					)
				)
			)
		);
	}

	public static function maybe_install() {
		$hash = hash( 'sha256', wp_json_encode( self::bundles() ) );
		if ( CYWATER_OPERATIONS_VERSION !== get_option( 'cywater_operations_version' ) || $hash !== get_option( self::OPTION_CAPS_HASH ) ) {
			self::install();
			update_option( 'cywater_operations_version', CYWATER_OPERATIONS_VERSION, false );
			return;
		}

		// Recovery Administrators must never lose newly introduced capabilities.
		self::grant_administrator_capabilities();
	}

	public static function install() {
		$labels       = self::role_labels();
		$bundles      = self::bundles();
		$previous     = array_map( 'sanitize_key', (array) get_option( self::OPTION_OLD_CAPS, array() ) );
		$current_caps = self::all_capabilities();

		foreach ( $labels as $slug => $label ) {
			$role = get_role( $slug );
			if ( ! $role instanceof WP_Role ) {
				$role = add_role( $slug, $label, array( 'read' => true ) );
			}
			if ( ! $role instanceof WP_Role ) {
				continue;
			}

			// Remove only capabilities this plugin previously owned. Unrelated
			// capabilities deliberately assigned by an Administrator are preserved.
			foreach ( $previous as $capability ) {
				$role->remove_cap( $capability );
			}
			foreach ( $bundles[ $slug ] as $capability ) {
				$role->add_cap( $capability, true );
			}
		}

		self::grant_administrator_capabilities( $previous );
		update_option( self::OPTION_OLD_CAPS, $current_caps, false );
		update_option( self::OPTION_CAPS_HASH, hash( 'sha256', wp_json_encode( $bundles ) ), false );
	}

	private static function grant_administrator_capabilities( $previous = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$administrator = get_role( 'administrator' );
		if ( ! $administrator instanceof WP_Role ) {
			return;
		}

		// Recovery Administrators are a safety boundary. Remove only explicitly
		// retired CYWater capabilities; never infer removals from a historical
		// option that may contain WordPress-core or third-party capabilities.
		foreach ( self::RETIRED_CAPS as $capability ) {
			$administrator->remove_cap( $capability );
		}
		foreach ( self::all_capabilities() as $capability ) {
			$administrator->add_cap( $capability, true );
		}
	}
}
