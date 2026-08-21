<?php
/**
 * Capability adapters for separately owned CYWater modules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Operations_Integrations {
	public static function register_runtime_adapters() {
		add_filter( 'register_post_type_args', array( __CLASS__, 'post_type_args' ), 20, 2 );
		add_filter( 'register_taxonomy_args', array( __CLASS__, 'taxonomy_args' ), 20, 3 );
		add_filter( 'postbox_classes_cyw_event_cywater-logo-call', array( __CLASS__, 'logo_event_box_classes' ) );

		self::wrap_logo_call_hooks();
		self::wrap_partnership_hooks();
	}

	/** Keep the optional program policy compact until an editor opens it. */
	public static function logo_event_box_classes( $classes ) {
		$classes[] = 'closed';
		return array_values( array_unique( $classes ) );
	}

	/**
	 * Keep custom module records outside generic Post capabilities.
	 *
	 * @param array  $args      Post type arguments.
	 * @param string $post_type Post type slug.
	 * @return array
	 */
	public static function post_type_args( $args, $post_type ) {
		$types = array(
			'cyw_event'      => array( 'cyw_event', 'cyw_events' ),
			'cyw_award'      => array( 'cyw_award', 'cyw_awards' ),
			'cyw_board_role' => array( 'cyw_board_role', 'cyw_board_roles' ),
			'cyw_logo_entry' => array( 'cyw_logo_entry', 'cyw_logo_entries' ),
		);

		if ( isset( $types[ $post_type ] ) ) {
			$args['capability_type'] = $types[ $post_type ];
			$args['map_meta_cap']    = true;
		}

		if ( 'cyw_logo_entry' === $post_type ) {
			// Submissions are created only by the protected public workflow. A
			// reviewer can inspect and update them but cannot fabricate an entry.
			// The dedicated Logo reviews workspace is the only daily operational
			// entry. Keep the private CPT UI addressable for recovery Administrators
			// without exposing a duplicate "Logo submissions" menu.
			$args['show_in_menu']                              = false;
			$args['capabilities']['create_posts']              = 'do_not_allow';
			$args['capabilities']['publish_posts']             = 'do_not_allow';
			$args['capabilities']['delete_post']               = 'cywater_delete_logo_entries';
			$args['capabilities']['delete_posts']              = 'cywater_delete_logo_entries';
			$args['capabilities']['delete_private_posts']      = 'cywater_delete_logo_entries';
			$args['capabilities']['delete_published_posts']    = 'cywater_delete_logo_entries';
			$args['capabilities']['delete_others_posts']       = 'cywater_delete_logo_entries';
		}

		if ( 'cyw_partner_app' === $post_type ) {
			$review = 'cywater_review_partnerships';
			$delete = 'cywater_delete_partnership_applications';
			$args['capabilities'] = array(
				'edit_post'              => $review,
				'read_post'              => $review,
				'delete_post'            => $delete,
				'edit_posts'             => $review,
				'edit_others_posts'      => $review,
				'publish_posts'          => $review,
				'read_private_posts'     => $review,
				'delete_posts'           => $delete,
				'delete_private_posts'   => $delete,
				'delete_published_posts' => $delete,
				'delete_others_posts'    => $delete,
				'edit_private_posts'     => $review,
				'edit_published_posts'   => $review,
				'create_posts'           => 'do_not_allow',
			);
			$args['map_meta_cap'] = false;
		}

		return $args;
	}

	/**
	 * Event categories are managed without granting access to ordinary Post
	 * categories or tags.
	 *
	 * @param array        $args        Taxonomy arguments.
	 * @param string       $taxonomy    Taxonomy slug.
	 * @param array|string $object_type Object types.
	 * @return array
	 */
	public static function taxonomy_args( $args, $taxonomy, $object_type ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 'cyw_event_type' === $taxonomy ) {
			$args['capabilities'] = array(
				'manage_terms' => 'manage_cyw_event_terms',
				'edit_terms'   => 'edit_cyw_event_terms',
				'delete_terms' => 'delete_cyw_event_terms',
				'assign_terms' => 'assign_cyw_event_terms',
			);
		}
		return $args;
	}

	private static function wrap_logo_call_hooks() {
		if ( ! class_exists( 'CYWater_Logo_Call' ) ) {
			return;
		}

		remove_action( 'add_meta_boxes_cyw_event', array( 'CYWater_Logo_Call', 'add_event_box' ) );
		remove_action( 'save_post_cyw_event', array( 'CYWater_Logo_Call', 'save_event' ) );
		remove_action( 'add_meta_boxes_cyw_logo_entry', array( 'CYWater_Logo_Call', 'add_entry_box' ) );
		remove_action( 'save_post_cyw_logo_entry', array( 'CYWater_Logo_Call', 'save_entry_review' ) );

		add_action( 'add_meta_boxes_cyw_event', array( __CLASS__, 'add_logo_event_box' ) );
		add_action( 'save_post_cyw_event', array( __CLASS__, 'save_logo_event' ) );
	}

	/**
	 * Return whether an Event already owns the removable Logo Call module.
	 *
	 * The Logo Call is an exceptional program workflow, not a generic Event
	 * option. New host Events are created or enabled by the Logo Call module's
	 * own setup path; ordinary Event editors must not be invited to configure it.
	 *
	 * @param int|WP_Post $post Event ID or object.
	 * @return bool
	 */
	public static function is_logo_call_host_event( $post ) {
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( absint( $post ) );
		}

		return $post instanceof WP_Post
			&& 'cyw_event' === $post->post_type
			&& '1' === (string) get_post_meta( $post->ID, '_cywater_logo_call_enabled', true );
	}

	/** Show Logo Call controls only on the Event that already hosts the module. */
	public static function add_logo_event_box( $post ) {
		if ( current_user_can( 'cywater_configure_logo_call' ) && self::is_logo_call_host_event( $post ) ) {
			// Participation is an Event setting, not part of the public narrative.
			// Keep the Logo module's own renderer and save path, but place its
			// audience controls beside the document instead of below the canvas.
			add_meta_box(
				'cywater-logo-call',
				__( 'Program participation — Logo Design Call', 'cywater-operations' ),
				array( 'CYWater_Logo_Call', 'render_event_box' ),
				'cyw_event',
				'side',
				'default'
			);
		}
	}

	public static function save_logo_event( $post_id ) {
		if ( current_user_can( 'cywater_configure_logo_call' ) && self::is_logo_call_host_event( $post_id ) ) {
			CYWater_Logo_Call::save_event( $post_id );
		}
	}

	private static function wrap_partnership_hooks() {
		if ( ! class_exists( 'CYWater_Partnerships' ) ) {
			return;
		}

		add_filter( 'cywater_partnership_review_transition_allowed', array( __CLASS__, 'authorize_partnership_review' ), 10, 6 );
		add_action( 'cywater_partnership_review_transition_failed', array( __CLASS__, 'record_partnership_write_failure' ), 10, 3 );
	}

	public static function authorize_partnership_review( $allowed, $post_id, $old_stage, $new_stage, $old_url, $new_url ) {
		if ( is_wp_error( $allowed ) ) {
			// Partnerships supplies this sentinel as its fail-closed default.
			// Consume only that expected value; preserve any earlier rejection.
			if ( 'cywater_operations_audit_adapter_unavailable' !== $allowed->get_error_code() ) {
				return $allowed;
			}
		} elseif ( ! $allowed ) {
			return $allowed;
		}

		$stage_changed = (string) $old_stage !== (string) $new_stage;
		$url_changed   = (string) $old_url !== (string) $new_url;
		if ( ! $stage_changed && ! $url_changed ) {
			return true;
		}

		$action = $stage_changed && $url_changed
			? 'transition_and_payment_authorized'
			: ( $stage_changed ? 'transition_authorized' : 'payment_handoff_changed' );
		$from   = $stage_changed ? ( $old_stage ?: 'submitted' ) : ( $old_url ? 'present' : 'absent' );
		$to     = $stage_changed ? ( $new_stage ?: 'submitted' ) : ( $new_url ? 'present' : 'absent' );
		if ( $stage_changed && $url_changed ) {
			$from .= $old_url ? '-url_present' : '-url_absent';
			$to   .= $new_url ? '-url_present' : '-url_absent';
		}

		// One material save is authorized by exactly one strict pre-commit row.
		// This prevents a stage+URL edit from leaving a misleading partial audit
		// trail if a later audit insert would have failed.
		if ( ! CYWater_Operations_Audit::record(
			'partner_application',
			$post_id,
			$action,
			$from,
			$to,
			0,
			'partnership_review'
		) ) {
			return new WP_Error( 'cywater_audit_unavailable', __( 'The review was not saved because the audit log is unavailable.', 'cywater-operations' ) );
		}

		return true;
	}

	/** Record a failed post-audit write without applicant notes or payment URLs. */
	public static function record_partnership_write_failure( $post_id, $old_stage, $attempted_stage ) {
		CYWater_Operations_Audit::record(
			'partner_application',
			$post_id,
			'transition_failed',
			$old_stage ?: 'submitted',
			$attempted_stage ?: 'submitted',
			0,
			'write_failed'
		);
	}
}
