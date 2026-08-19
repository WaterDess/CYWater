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
	private const ROLE_SCHEMA_OPTION = 'cywater_forum_role_schema_version';

	public static function register() {
		add_action( 'cywater_after_core_setup', array( __CLASS__, 'install_roles' ), 10 );
		add_action( 'init', array( __CLASS__, 'maybe_migrate_roles' ), 1 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'gate_publishing' ), 10, 4 );
		add_filter( 'user_has_cap', array( __CLASS__, 'grant_open_publishing' ), 10, 4 );
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

		if (
			CYWater_Forum_Settings::is_enabled( 'endorsement_required' )
			&& ! CYWater_Forum_Endorsement::is_endorsed( $user_id )
		) {
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
	 * Grant the author capabilities to any eligible member while endorsement is
	 * switched off.
	 *
	 * The `cyw_forum_author` role is handed out by the endorsement flow, so with
	 * endorsement disabled nothing would ever grant it: `publish_blockers()`
	 * would come back empty while the member still held no publishing
	 * capability, and the forum would look open while being shut. Rather than
	 * writing roles onto every paying member — which then has to be unwound when
	 * membership lapses — eligibility is answered live, so it follows membership
	 * and email verification automatically.
	 *
	 * `can_publish()` calls `user_can()` internally, which re-enters this filter;
	 * the reentry guard keeps that from recursing.
	 *
	 * @param array<string, bool> $allcaps Capabilities the user already has.
	 * @param array<int, string>  $caps    Primitive capabilities being tested.
	 * @param array<int, mixed>   $args    Context.
	 * @param WP_User             $user    User under test.
	 * @return array<string, bool>
	 */
	public static function grant_open_publishing( $allcaps, $caps, $args, $user ) {
		static $checking = false;

		if ( $checking || ! $user instanceof WP_User || ! $user->ID ) {
			return $allcaps;
		}
		if ( CYWater_Forum_Settings::is_enabled( 'endorsement_required' ) ) {
			return $allcaps;
		}
		if ( ! array_intersect( (array) $caps, self::author_capabilities() ) ) {
			return $allcaps;
		}

		$checking = true;
		$eligible = self::can_publish( $user->ID );
		$checking = false;

		if ( $eligible ) {
			foreach ( self::author_capabilities() as $cap ) {
				$allcaps[ $cap ] = true;
			}
		}

		return $allcaps;
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
		if ( self::can_publish( $user_id ) ) {
			return $caps;
		}
		// do_not_allow is the documented way to deny a mapped capability
		// outright; returning an empty array would grant it instead.
		return array( 'do_not_allow' );
	}
}
