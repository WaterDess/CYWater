<?php
/**
 * Capability-aware WordPress administration navigation for CYWater.
 *
 * This class changes presentation only. WordPress capabilities and every
 * protected route remain the authorization boundary.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Operations_Admin_Navigation {
	private const GROUP_DASHBOARD  = 'dashboard';
	private const GROUP_CONTENT    = 'content';
	private const GROUP_COMMUNITY  = 'community';
	private const GROUP_MEMBERSHIP = 'membership';
	private const GROUP_SYSTEM     = 'system';
	private const GROUP_WORK       = 'work';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'organize_menu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_dashboard_widget' ) );
	}

	public static function enqueue_assets() {
		$user = wp_get_current_user();
		if ( ! CYWater_Operations_Admin::current_user_is_administrator() && ! self::is_operational_user( $user ) ) {
			return;
		}

		wp_enqueue_style(
			'cywater-operations-admin',
			plugins_url( 'assets/admin.css', CYWATER_OPERATIONS_FILE ),
			array(),
			CYWATER_OPERATIONS_VERSION
		);

		$critical_css = self::critical_stylesheet();
		if ( '' !== $critical_css ) {
			// The core admin stylesheet is already required by every WordPress
			// screen. Attaching the small navigation baseline here avoids another
			// transport request while the full Operations stylesheet remains an
			// optional enhancement.
			wp_enqueue_style( 'common' );
			wp_add_inline_style( 'common', $critical_css );
		}

		$navigation_script = self::navigation_script();
		if ( '' !== $navigation_script ) {
			// The grouping interaction is small and shares the same reliability
			// requirement as its critical CSS. Inline it after WordPress' required
			// core admin script so a fresh session does not need a second request.
			wp_enqueue_script( 'common' );
			wp_add_inline_script( 'common', $navigation_script, 'after' );
		}
	}

	/** Return the dependency-free navigation baseline kept in its own module. */
	public static function critical_stylesheet() {
		$path = CYWATER_OPERATIONS_DIR . 'assets/admin-critical.css';
		if ( ! is_readable( $path ) ) {
			return '';
		}

		$css = file_get_contents( $path );
		return is_string( $css ) ? trim( $css ) : '';
	}

	/** Return the dependency-free navigation enhancement embedded in admin HTML. */
	public static function navigation_script() {
		$path = CYWATER_OPERATIONS_DIR . 'assets/admin-navigation.js';
		if ( ! is_readable( $path ) ) {
			return '';
		}

		$script = file_get_contents( $path );
		return is_string( $script ) ? trim( $script ) : '';
	}

	/**
	 * Group an Administrator's complete menu and remove unrelated entries from
	 * delegated staff menus. Unknown Administrator plugins are retained inside
	 * Site system so a new tool is never silently made inaccessible.
	 */
	public static function organize_menu() {
		global $menu;

		if ( ! is_array( $menu ) ) {
			return;
		}

		$user = wp_get_current_user();
		if ( CYWater_Operations_Admin::current_user_is_administrator() ) {
			self::organize_administrator_menu( $menu );
			return;
		}

		if ( self::is_operational_user( $user ) ) {
			self::prune_operational_menu( $menu );
		}
	}

	/**
	 * @param array<int,array<int,mixed>> $menu WordPress top-level menu.
	 */
	private static function organize_administrator_menu( &$menu ) {
		$rank = self::administrator_rank();
		$rows = array();

		foreach ( $menu as $item ) {
			$slug = self::menu_slug( $item );
			if ( '' === $slug || self::is_separator( $slug ) ) {
				continue;
			}

			$group = self::administrator_group( $slug );
			self::tag_menu_item( $item, $group );
			if ( 'edit.php' === $slug ) {
				$item[0] = __( 'News', 'cywater-operations' );
			}
			$rows[] = array(
				'item'  => $item,
				'rank'  => $rank[ $slug ] ?? ( 9000 + count( $rows ) ),
				'group' => self::group_rank( $group ),
			);
		}

		usort(
			$rows,
			static function ( $left, $right ) {
				$group_compare = $left['group'] <=> $right['group'];
				return 0 !== $group_compare ? $group_compare : ( $left['rank'] <=> $right['rank'] );
			}
		);

		$menu = array();
		foreach ( $rows as $position => $row ) {
			$menu[ 2 + $position ] = $row['item'];
		}
		self::tag_group_starts( $menu );
	}

	/**
	 * @param array<int,array<int,mixed>> $menu WordPress top-level menu.
	 */
	private static function prune_operational_menu( &$menu ) {
		$allowed = self::operational_menu_slugs();
		$kept    = array();

		foreach ( $menu as $item ) {
			$slug = self::menu_slug( $item );
			if ( '' === $slug || self::is_separator( $slug ) || ! in_array( $slug, $allowed, true ) ) {
				continue;
			}

			self::tag_menu_item( $item, 'index.php' === $slug ? self::GROUP_DASHBOARD : self::GROUP_WORK );
			if ( 'edit.php' === $slug ) {
				$item[0] = __( 'News', 'cywater-operations' );
			}
			$kept[] = $item;
		}

		$menu = array();
		foreach ( $kept as $position => $item ) {
			$menu[ 2 + $position ] = $item;
		}
		self::tag_group_starts( $menu );
	}

	/** @return array<int,string> */
	private static function operational_menu_slugs() {
		$allowed = array( 'index.php', 'profile.php' );

		if ( current_user_can( 'edit_posts' ) ) {
			$allowed[] = 'edit.php';
		}
		if ( current_user_can( 'upload_files' ) ) {
			$allowed[] = 'upload.php';
		}
		if ( current_user_can( 'edit_cyw_events' ) ) {
			$allowed[] = 'edit.php?post_type=cyw_event';
		}
		if ( current_user_can( 'edit_cyw_awards' ) ) {
			$allowed[] = 'edit.php?post_type=cyw_award';
		}
		if ( current_user_can( 'edit_cyw_forum_posts' ) ) {
			$allowed[] = 'edit.php?post_type=cyw_forum_post';
		}
		if ( current_user_can( 'moderate_comments' ) ) {
			$allowed[] = 'edit-comments.php';
		}
		if ( current_user_can( 'cywater_review_logo_entries' ) ) {
			$allowed[] = 'cywater-logo-reviews';
		}
		if ( current_user_can( 'cywater_approve_paid_event' ) ) {
			$allowed[] = 'cywater-event-approvals';
		}
		if ( current_user_can( 'cywater_review_partnerships' ) ) {
			$allowed[] = 'edit.php?post_type=cyw_partner_app';
		}
		if ( current_user_can( 'edit_cyw_board_roles' ) ) {
			$allowed[] = 'edit.php?post_type=cyw_board_role';
		}

		return array_values( array_unique( $allowed ) );
	}

	/** @return array<string,int> */
	private static function administrator_rank() {
		$slugs = array(
			'index.php',
			'edit.php',
			'edit.php?post_type=cyw_event',
			'edit.php?post_type=cyw_award',
			'edit.php?post_type=cyw_board_role',
			'edit.php?post_type=page',
			'upload.php',
			'edit.php?post_type=cyw_forum_post',
			'edit-comments.php',
			'edit.php?post_type=cyw_partner_app',
			'cywater-logo-reviews',
			'cywater-event-approvals',
			'tec-tickets',
			'pmpro-dashboard',
			'users.php',
			'themes.php',
			'plugins.php',
			'tools.php',
			'options-general.php',
			'litespeed',
			'hostinger',
			'hostinger-reach',
		);

		return array_flip( $slugs );
	}

	private static function administrator_group( $slug ) {
		if ( 'index.php' === $slug ) {
			return self::GROUP_DASHBOARD;
		}
		if ( in_array( $slug, array( 'edit.php', 'edit.php?post_type=cyw_event', 'edit.php?post_type=cyw_award', 'edit.php?post_type=cyw_board_role', 'edit.php?post_type=page', 'upload.php' ), true ) ) {
			return self::GROUP_CONTENT;
		}
		if ( in_array( $slug, array( 'edit.php?post_type=cyw_forum_post', 'edit-comments.php', 'edit.php?post_type=cyw_partner_app', 'cywater-logo-reviews', 'cywater-event-approvals', 'tec-tickets' ), true ) ) {
			return self::GROUP_COMMUNITY;
		}
		if ( in_array( $slug, array( 'pmpro-dashboard', 'users.php' ), true ) ) {
			return self::GROUP_MEMBERSHIP;
		}
		return self::GROUP_SYSTEM;
	}

	private static function group_rank( $group ) {
		$groups = array(
			self::GROUP_DASHBOARD  => 0,
			self::GROUP_CONTENT    => 10,
			self::GROUP_COMMUNITY  => 20,
			self::GROUP_MEMBERSHIP => 30,
			self::GROUP_SYSTEM     => 40,
		);
		return $groups[ $group ] ?? 99;
	}

	private static function tag_menu_item( &$item, $group ) {
		$classes = preg_split( '/\s+/', trim( (string) ( $item[4] ?? '' ) ) ) ?: array();
		$classes[] = 'cywater-menu-group-' . sanitize_html_class( $group );
		$item[4]   = implode( ' ', array_unique( array_filter( $classes ) ) );
	}

	/**
	 * Mark the first item in each visible work group. CSS uses this marker to
	 * render a server-backed section label even when the optional collapse
	 * script is delayed, blocked, or served from a stale browser cache.
	 *
	 * @param array<int,array<int,mixed>> $items Organized top-level menu.
	 */
	private static function tag_group_starts( &$items ) {
		$seen   = array();
		$groups = array( self::GROUP_CONTENT, self::GROUP_COMMUNITY, self::GROUP_MEMBERSHIP, self::GROUP_SYSTEM, self::GROUP_WORK );

		foreach ( $items as &$item ) {
			$classes = preg_split( '/\s+/', trim( (string) ( $item[4] ?? '' ) ) ) ?: array();
			foreach ( $groups as $group ) {
				$token = 'cywater-menu-group-' . $group;
				if ( isset( $seen[ $group ] ) || ! in_array( $token, $classes, true ) ) {
					continue;
				}

				$classes[]      = 'cywater-menu-group-start';
				$seen[ $group ] = true;
				break;
			}
			$item[4] = implode( ' ', array_unique( array_filter( $classes ) ) );
		}
		unset( $item );
	}

	private static function menu_slug( $item ) {
		return isset( $item[2] ) ? (string) $item[2] : '';
	}

	private static function is_separator( $slug ) {
		return 0 === strpos( $slug, 'separator' );
	}

	private static function is_operational_user( $user ) {
		return $user instanceof WP_User && (bool) array_intersect( CYWater_Operations_Roles::role_slugs(), (array) $user->roles );
	}

	public static function register_dashboard_widget() {
		$user = wp_get_current_user();
		if ( ! CYWater_Operations_Admin::current_user_is_administrator() && ! self::is_operational_user( $user ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'cywater_operations_work_areas',
			__( 'CYWater work areas', 'cywater-operations' ),
			array( __CLASS__, 'render_dashboard_widget' )
		);
	}

	public static function render_dashboard_widget() {
		$areas = array();
		if ( current_user_can( 'edit_posts' ) ) {
			$areas[] = array( __( 'News', 'cywater-operations' ), admin_url( 'edit.php' ), __( 'Publish and update association news.', 'cywater-operations' ) );
		}
		if ( current_user_can( 'edit_cyw_events' ) ) {
			$areas[] = array( __( 'Events', 'cywater-operations' ), admin_url( 'edit.php?post_type=cyw_event' ), __( 'Create and maintain Event records.', 'cywater-operations' ) );
		}
		if ( current_user_can( 'edit_cyw_awards' ) ) {
			$areas[] = array( __( 'Awards', 'cywater-operations' ), admin_url( 'edit.php?post_type=cyw_award' ), __( 'Publish and update Award records.', 'cywater-operations' ) );
		}
		if ( current_user_can( 'edit_cyw_forum_posts' ) ) {
			$areas[] = array( __( 'Forum', 'cywater-operations' ), admin_url( 'edit.php?post_type=cyw_forum_post' ), __( 'Publish Forum articles and moderate replies.', 'cywater-operations' ) );
		}
		if ( current_user_can( 'cywater_review_logo_entries' ) ) {
			$areas[] = array( __( 'Logo reviews', 'cywater-operations' ), admin_url( 'admin.php?page=cywater-logo-reviews' ), __( 'Review protected submissions and record outcomes.', 'cywater-operations' ) );
		}
		if ( current_user_can( 'cywater_approve_paid_event' ) ) {
			$areas[] = array( __( 'Paid Event approvals', 'cywater-operations' ), admin_url( 'admin.php?page=cywater-event-approvals' ), __( 'Review submitted terms before paid registration opens.', 'cywater-operations' ) );
		}
		if ( current_user_can( 'cywater_review_partnerships' ) ) {
			$areas[] = array( __( 'Partner applications', 'cywater-operations' ), admin_url( 'edit.php?post_type=cyw_partner_app' ), __( 'Review expressions of interest and Board/MOU status.', 'cywater-operations' ) );
		}
		if ( current_user_can( 'edit_cyw_board_roles' ) ) {
			$areas[] = array( __( 'Board roles', 'cywater-operations' ), admin_url( 'edit.php?post_type=cyw_board_role' ), __( 'Maintain public Board records.', 'cywater-operations' ) );
		}
		if ( CYWater_Operations_Admin::current_user_is_administrator() ) {
			$areas[] = array( __( 'Staff access', 'cywater-operations' ), CYWater_Operations_Admin::page_url(), __( 'Search accounts and assign audited operational bundles.', 'cywater-operations' ) );
		}

		if ( ! $areas ) {
			echo '<p>' . esc_html__( 'No CYWater operational work area is assigned to this account.', 'cywater-operations' ) . '</p>';
			return;
		}

		echo '<div class="cywater-work-areas">';
		foreach ( $areas as $area ) {
			printf(
				'<a class="cywater-work-area" href="%1$s"><strong>%2$s</strong><span>%3$s</span></a>',
				esc_url( $area[1] ),
				esc_html( $area[0] ),
				esc_html( $area[2] )
			);
		}
		echo '</div>';
	}
}
