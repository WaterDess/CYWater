<?php
/**
 * Forum participation roles and the submission/publishing gates.
 *
 * Two separate ideas are deliberately kept apart:
 *
 * - The `cyw_forum_author` role is durable. It is granted lazily when a member
 *   first satisfies the current participation policy. Losing it would orphan
 *   an author's drafts and historical byline.
 * - Permission to submit is evaluated per request, because membership can
 *   lapse and an email address can become unverified. A lapsed member keeps
 *   their role and records but cannot create or edit a submission.
 * - Publication is always staff-only. A member may save a draft or submit it
 *   for review; a Community Moderator decides whether it becomes public.
 *
 * That split is why the gate lives in map_meta_cap rather than in the role.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Forum_Roles {
	public const AUTHOR_ROLE = 'cyw_forum_author';
	private const ROLE_SCHEMA_OPTION = 'cywater_forum_role_schema_version';

	public static function register() {
		add_action( 'cywater_after_core_setup', array( __CLASS__, 'install_roles' ), 10 );
		add_action( 'init', array( __CLASS__, 'maybe_migrate_roles' ), 1 );
		add_action( 'wp_loaded', array( __CLASS__, 'sync_current_member_role' ), 20 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'gate_publishing' ), 10, 4 );
		add_filter( 'user_has_cap', array( __CLASS__, 'gate_member_submission_primitives' ), 10, 4 );
	}

	/**
	 * @return array<int, string>
	 */
	public static function author_capabilities() {
		return array(
			'read',
			'edit_cyw_forum_posts',
			'delete_cyw_forum_posts',
		);
	}

	/**
	 * @return array<int, string>
	 */
	public static function editor_capabilities() {
		return array(
				'read',
				'upload_files',
				'edit_cyw_forum_posts',
				'edit_published_cyw_forum_posts',
				'publish_cyw_forum_posts',
				'delete_cyw_forum_posts',
				'delete_published_cyw_forum_posts',
				'edit_others_cyw_forum_posts',
				'edit_private_cyw_forum_posts',
				'read_private_cyw_forum_posts',
				'delete_others_cyw_forum_posts',
				'delete_private_cyw_forum_posts',
				'manage_cyw_forum_terms',
			);
	}

	/**
	 * Apply capability changes once after a plugin update as well as on a fresh
	 * activation. Running on init also closes the boundary for REST requests
	 * made before an administrator next visits wp-admin.
	 */
	public static function maybe_migrate_roles() {
		if ( CYWATER_FORUM_VERSION === get_option( self::ROLE_SCHEMA_OPTION ) ) {
			return;
		}
		self::install_roles();
	}

	public static function install_roles() {
		if ( ! get_role( self::AUTHOR_ROLE ) ) {
			add_role( self::AUTHOR_ROLE, __( 'Forum author', 'cywater-forum' ), array() );
		}

		$role = get_role( self::AUTHOR_ROLE );
		if ( $role ) {
			foreach ( array( 'upload_files', 'edit_published_cyw_forum_posts', 'publish_cyw_forum_posts', 'delete_published_cyw_forum_posts' ) as $retired_cap ) {
				$role->remove_cap( $retired_cap );
			}
			foreach ( self::author_capabilities() as $cap ) {
				$role->add_cap( $cap );
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::editor_capabilities() as $cap ) {
				$administrator->add_cap( $cap );
			}
		}

		/*
		 * Community moderation belongs to the dedicated Community Moderator
		 * role. CYWater Forum 0.1.0 also granted these custom primitives to the
		 * built-in Editor role; remove only that legacy grant and leave core
		 * Editor capabilities such as moderate_comments untouched.
		 */
		$editor = get_role( 'editor' );
		if ( $editor ) {
			foreach ( array_diff( self::editor_capabilities(), array( 'read', 'upload_files' ) ) as $cap ) {
				$editor->remove_cap( $cap );
			}
		}

		update_option( self::ROLE_SCHEMA_OPTION, CYWATER_FORUM_VERSION, false );
	}

	public static function grant_author_role( $user_id ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user || in_array( self::AUTHOR_ROLE, (array) $user->roles, true ) ) {
			return false;
		}
		$user->add_role( self::AUTHOR_ROLE );
		return true;
	}

	public static function revoke_author_role( $user_id ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user || ! in_array( self::AUTHOR_ROLE, (array) $user->roles, true ) ) {
			return false;
		}
		$user->remove_role( self::AUTHOR_ROLE );
		return true;
	}

	/**
	 * Staff publish on the association's behalf and are never subject to the
	 * endorsement or membership gate.
	 */
	public static function is_staff( $user_id ) {
		return user_can( absint( $user_id ), 'edit_others_cyw_forum_posts' );
	}

	/**
	 * Reasons this user may not publish right now.
	 *
	 * @return array<int, string>
	 */
	public static function publish_blockers( $user_id ) {
		if ( self::is_staff( $user_id ) ) {
			return array();
		}
		$blockers = self::submission_blockers( $user_id );
		if ( ! $blockers ) {
			$blockers[] = 'moderator_review_required';
		}
		return $blockers;
	}

	/**
	 * Reasons a member may not submit a new Forum article for moderation.
	 * Publishing itself is always staff-only.
	 *
	 * @return array<int, string>
	 */
	public static function submission_blockers( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array( 'signed_out' );
		}
		if ( self::is_staff( $user_id ) ) {
			return array();
		}

		$blockers = array();

		if ( CYWater_Forum_Settings::is_enabled( 'endorsements_enabled' ) && ! CYWater_Forum_Endorsement::is_endorsed( $user_id ) ) {
			$blockers[] = 'not_endorsed';
		}

		if ( CYWater_Forum_Settings::is_enabled( 'membership_required' ) && ! self::has_active_membership( $user_id ) ) {
			$blockers[] = 'membership_inactive';
		}

		// Reuse the membership plugin's verification state rather than
		// introducing a second notion of a trusted email address.
		if ( class_exists( 'CYWater_Membership_Account_Security' ) && ! CYWater_Membership_Account_Security::is_verified( $user_id ) ) {
			$blockers[] = 'email_unverified';
		}

		return $blockers;
	}

	public static function can_publish( $user_id ) {
		return self::is_staff( $user_id );
	}

	public static function can_submit( $user_id ) {
		return array() === self::submission_blockers( $user_id );
	}

	/**
	 * Lazily grant the durable draft-author role to the signed-in member once
	 * the authoritative membership and email gates are satisfied. The gates
	 * remain request-time checks, so retaining the role never preserves access
	 * after membership expires.
	 */
	public static function sync_current_member_role() {
		$user_id = get_current_user_id();
		if ( $user_id && ! self::is_staff( $user_id ) && self::can_submit( $user_id ) ) {
			self::grant_author_role( $user_id );
		}
	}

	public static function has_active_membership( $user_id ) {
		if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			// PMPro absent means membership state is unknowable. Treat the
			// prerequisite as unmet rather than silently waiving it.
			return false;
		}

		$configured = (array) get_option( 'cywater_membership_level_ids', array() );
		$individual_level_ids = array_filter(
			array_map(
				'absint',
				array(
					$configured['student'] ?? 0,
					$configured['professional'] ?? 0,
					$configured['lifetime'] ?? 0,
				)
			)
		);
		if ( ! $individual_level_ids ) {
			return false;
		}

		$now = current_time( 'timestamp' );
		foreach ( (array) pmpro_getMembershipLevelsForUser( absint( $user_id ) ) as $level ) {
			if ( ! in_array( (int) $level->id, $individual_level_ids, true ) ) {
				continue;
			}
			$enddate = isset( $level->enddate ) ? (int) $level->enddate : 0;
			if ( 0 === $enddate || $enddate > $now ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The author role is retained for record ownership, so remove its create/edit
	 * primitive at request time whenever the member no longer qualifies.
	 *
	 * @param array<string, bool> $allcaps
	 * @param array<int, string>  $caps
	 * @param array<int, mixed>   $args
	 * @param WP_User             $user
	 * @return array<string, bool>
	 */
	public static function gate_member_submission_primitives( $allcaps, $caps, $args, $user ) {
		$requested = (string) ( $args[0] ?? '' );
		if ( 'edit_cyw_forum_posts' !== $requested && ! in_array( 'edit_cyw_forum_posts', (array) $caps, true ) ) {
			return $allcaps;
		}
		if ( ! $user instanceof WP_User || ! empty( $allcaps['edit_others_cyw_forum_posts'] ) ) {
			return $allcaps;
		}
		if ( ! self::can_submit( $user->ID ) ) {
			$allcaps['edit_cyw_forum_posts'] = false;
		}
		return $allcaps;
	}

	/**
	 * Deny publication for every non-staff account.
	 *
	 * @param array<int, string> $caps    Primitive capabilities required.
	 * @param string             $cap     Capability being checked.
	 * @param int                $user_id User under test.
	 * @param array<int, mixed>  $args    Context, typically the post ID.
	 * @return array<int, string>
	 */
	public static function gate_publishing( $caps, $cap, $user_id, $args ) {
		/*
		 * A trashed Forum record is staff-controlled. WordPress maps the custom
		 * edit/delete meta capabilities back to these core names before this
		 * filter runs, so this closes wp-admin and REST update/untrash paths
		 * without removing an author's ordinary ability to trash their own work.
		 */
		if ( in_array( $cap, array( 'edit_post', 'delete_post' ), true ) && ! empty( $args[0] ) ) {
			$post = get_post( absint( $args[0] ) );
			if (
				$post instanceof WP_Post
				&& CYWater_Forum_Content::POST_TYPE === $post->post_type
				&& 'trash' === $post->post_status
				&& ! self::is_staff( $user_id )
			) {
				return array( 'do_not_allow' );
			}
		}

		if ( 'publish_cyw_forum_posts' !== $cap ) {
			return $caps;
		}
		if ( self::is_staff( $user_id ) ) {
			return $caps;
		}
		// do_not_allow is the documented way to deny a mapped capability
		// outright; returning an empty array would grant it instead.
		return array( 'do_not_allow' );
	}
}
