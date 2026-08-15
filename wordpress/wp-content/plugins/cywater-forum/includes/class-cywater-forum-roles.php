<?php
/**
 * Forum authorship roles and the publishing gate.
 *
 * Two separate ideas are deliberately kept apart:
 *
 * - The `cyw_forum_author` role is durable. It is granted when endorsement
 *   completes and is only removed by an administrator. Losing it would orphan
 *   an author's drafts.
 * - Permission to *publish* is evaluated per request, because membership can
 *   lapse and an email address can become unverified. A lapsed member keeps
 *   their role and their drafts, and keeps their already published articles,
 *   but cannot push anything new out until they are current again.
 *
 * That split is why the gate lives in map_meta_cap rather than in the role.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Forum_Roles {
	public const AUTHOR_ROLE = 'cyw_forum_author';

	public static function register() {
		add_action( 'cywater_after_core_setup', array( __CLASS__, 'install_roles' ), 10 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'gate_publishing' ), 10, 4 );
	}

	/**
	 * @return array<int, string>
	 */
	public static function author_capabilities() {
		return array(
			'read',
			'upload_files',
			'edit_cyw_forum_posts',
			'edit_published_cyw_forum_posts',
			'publish_cyw_forum_posts',
			'delete_cyw_forum_posts',
			'delete_published_cyw_forum_posts',
		);
	}

	/**
	 * @return array<int, string>
	 */
	public static function editor_capabilities() {
		return array_merge(
			self::author_capabilities(),
			array(
				'edit_others_cyw_forum_posts',
				'edit_private_cyw_forum_posts',
				'read_private_cyw_forum_posts',
				'delete_others_cyw_forum_posts',
				'delete_private_cyw_forum_posts',
				'manage_cyw_forum_terms',
			)
		);
	}

	public static function install_roles() {
		if ( ! get_role( self::AUTHOR_ROLE ) ) {
			add_role( self::AUTHOR_ROLE, __( 'Forum author', 'cywater-forum' ), array() );
		}

		$role = get_role( self::AUTHOR_ROLE );
		if ( $role ) {
			foreach ( self::author_capabilities() as $cap ) {
				$role->add_cap( $cap );
			}
		}

		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$existing = get_role( $role_name );
			if ( ! $existing ) {
				continue;
			}
			foreach ( self::editor_capabilities() as $cap ) {
				$existing->add_cap( $cap );
			}
		}
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
	 * Reasons this user may not publish right now. An empty array means they
	 * may. Returning reasons rather than a bare boolean lets the front end tell
	 * an author exactly what to fix.
	 *
	 * @return array<int, string>
	 */
	public static function publish_blockers( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array( 'signed_out' );
		}
		if ( self::is_staff( $user_id ) ) {
			return array();
		}

		$blockers = array();

		if ( ! CYWater_Forum_Endorsement::is_endorsed( $user_id ) ) {
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
		return array() === self::publish_blockers( $user_id );
	}

	public static function has_active_membership( $user_id ) {
		if ( ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
			// PMPro absent means membership state is unknowable. Treat the
			// prerequisite as unmet rather than silently waiving it.
			return false;
		}
		return (bool) pmpro_hasMembershipLevel( null, absint( $user_id ) );
	}

	/**
	 * Deny the publish capability when a prerequisite is unmet, leaving drafting
	 * and editing intact.
	 *
	 * @param array<int, string> $caps    Primitive capabilities required.
	 * @param string             $cap     Capability being checked.
	 * @param int                $user_id User under test.
	 * @param array<int, mixed>  $args    Context, typically the post ID.
	 * @return array<int, string>
	 */
	public static function gate_publishing( $caps, $cap, $user_id, $args ) {
		if ( 'publish_cyw_forum_posts' !== $cap ) {
			return $caps;
		}
		if ( self::can_publish( $user_id ) ) {
			return $caps;
		}
		// do_not_allow is the documented way to deny a mapped capability
		// outright; returning an empty array would grant it instead.
		return array( 'do_not_allow' );
	}
}
