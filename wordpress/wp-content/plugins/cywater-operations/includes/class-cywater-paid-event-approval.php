<?php
/**
 * Governance approval gate for paid CYWater Events.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Paid_Event_Approval {
	public const META_PAID          = '_cywater_paid_event';
	public const META_STATE         = '_cywater_event_operation_state';
	public const META_FEE           = '_cywater_event_fee_amount';
	public const META_CURRENCY      = '_cywater_event_currency';
	public const META_DEADLINE      = '_cywater_event_cancellation_deadline';
	public const META_REFUND        = '_cywater_event_refund_terms';
	public const META_TRANSFER      = '_cywater_event_transfer_terms';
	public const META_CAPACITY      = '_cywater_event_capacity_terms';
	public const META_CHANGES       = '_cywater_event_change_terms';
	public const META_SUBMIT_HASH   = '_cywater_event_submission_hash';
	public const META_SUBMIT_SNAPSHOT = '_cywater_event_submission_snapshot';
	public const META_APPROVAL_HASH = '_cywater_event_approval_hash';
	public const META_APPROVED_BY   = '_cywater_event_approved_by';
	public const META_APPROVED_AT   = '_cywater_event_approved_at';

	public const STATE_DRAFT              = 'draft';
	public const STATE_TERMS_COMPLETE     = 'terms_complete';
	public const STATE_PENDING_APPROVAL   = 'pending_approval';
	public const STATE_APPROVED           = 'approved';
	public const STATE_REGISTRATION_OPEN  = 'registration_open';
	public const STATE_CLOSED             = 'closed';

	/** @var bool */
	private static $integrity_guard = false;

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'add_meta_boxes_cyw_event', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_cyw_event', array( __CLASS__, 'save_event' ), 50, 2 );
		add_action( 'post_updated', array( __CLASS__, 'post_updated' ), 20, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'critical_meta_changed' ), 20, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'critical_meta_changed' ), 20, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'critical_meta_changed' ), 20, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'workflow_admin_notice' ) );
		add_action( 'admin_post_cywater_paid_event_decision', array( __CLASS__, 'handle_decision' ) );

		// Event Tickets or another association-approved ticket adapter should
		// pass its current readiness through this filter before exposing payment.
		add_filter( 'cywater_paid_event_registration_ready', array( __CLASS__, 'filter_registration_ready' ), 10, 2 );
	}

	/** @return array<string, string> */
	public static function states() {
		return array(
			self::STATE_DRAFT             => __( 'Draft', 'cywater-operations' ),
			self::STATE_TERMS_COMPLETE    => __( 'Terms complete', 'cywater-operations' ),
			self::STATE_PENDING_APPROVAL  => __( 'Pending approval', 'cywater-operations' ),
			self::STATE_APPROVED          => __( 'Approved', 'cywater-operations' ),
			self::STATE_REGISTRATION_OPEN => __( 'Registration open', 'cywater-operations' ),
			self::STATE_CLOSED            => __( 'Closed', 'cywater-operations' ),
		);
	}

	public static function register_meta() {
		$definitions = array(
			self::META_PAID          => array( 'string', 'sanitize_text_field' ),
			self::META_STATE         => array( 'string', 'sanitize_key' ),
			self::META_FEE           => array( 'string', array( __CLASS__, 'sanitize_amount' ) ),
			self::META_CURRENCY      => array( 'string', array( __CLASS__, 'sanitize_currency' ) ),
			self::META_DEADLINE      => array( 'string', array( __CLASS__, 'sanitize_date' ) ),
			self::META_REFUND        => array( 'string', 'sanitize_textarea_field' ),
			self::META_TRANSFER      => array( 'string', 'sanitize_textarea_field' ),
			self::META_CAPACITY      => array( 'string', 'sanitize_textarea_field' ),
			self::META_CHANGES       => array( 'string', 'sanitize_textarea_field' ),
			self::META_SUBMIT_HASH   => array( 'string', 'sanitize_text_field' ),
			self::META_SUBMIT_SNAPSHOT => array( 'string', array( __CLASS__, 'sanitize_snapshot' ) ),
			self::META_APPROVAL_HASH => array( 'string', 'sanitize_text_field' ),
			self::META_APPROVED_BY   => array( 'integer', 'absint' ),
			self::META_APPROVED_AT   => array( 'string', 'sanitize_text_field' ),
		);

		foreach ( $definitions as $key => $definition ) {
			register_post_meta(
				'cyw_event',
				$key,
				array(
					'type'              => $definition[0],
					'single'            => true,
					'show_in_rest'      => false,
					'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
					'sanitize_callback' => $definition[1],
				)
			);
		}
	}

	public static function can_edit_meta( $allowed, $meta_key, $post_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $post_id && current_user_can( 'edit_post', $post_id );
	}

	public static function add_meta_box() {
		add_meta_box(
			'cywater-paid-event-approval',
			__( 'Paid event readiness', 'cywater-operations' ),
			array( __CLASS__, 'render_meta_box' ),
			'cyw_event',
			'normal',
			'high'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'cywater_paid_event_save', 'cywater_paid_event_nonce' );
		$state   = self::state( $post->ID );
		$missing = self::missing_requirements( $post->ID );
		?>
		<p><label><input type="checkbox" name="cywater_paid_event" value="1" <?php checked( self::is_paid( $post->ID ) ); ?>> <strong><?php esc_html_e( 'This Event charges a registration fee', 'cywater-operations' ); ?></strong></label></p>
		<p class="description"><?php esc_html_e( 'This workflow approves public terms and readiness only. Event Tickets and Stripe remain the owners of registration, orders, charges, refunds and webhooks.', 'cywater-operations' ); ?></p>
		<table class="form-table" role="presentation"><tbody>
		<tr><th><label for="cywater-event-fee"><?php esc_html_e( 'Fee', 'cywater-operations' ); ?></label></th><td><input id="cywater-event-fee" name="cywater_event_fee" type="number" min="0" step="0.01" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_FEE, true ) ); ?>"> <input name="cywater_event_currency" type="text" maxlength="3" size="4" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_CURRENCY, true ) ?: 'USD' ); ?>" aria-label="<?php esc_attr_e( 'Currency', 'cywater-operations' ); ?>"></td></tr>
		<tr><th><label for="cywater-event-deadline"><?php esc_html_e( 'Cancellation deadline', 'cywater-operations' ); ?></label></th><td><input id="cywater-event-deadline" name="cywater_event_deadline" type="date" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_DEADLINE, true ) ); ?>"><p class="description"><?php esc_html_e( 'Publish the deadline shown to registrants. Use the Event-specific policy even when no refund is offered.', 'cywater-operations' ); ?></p></td></tr>
		<?php
		$textareas = array(
			'refund'   => __( 'Cancellation and refund terms', 'cywater-operations' ),
			'transfer' => __( 'Transfer or substitution terms', 'cywater-operations' ),
			'capacity' => __( 'Capacity, waitlist and sell-out terms', 'cywater-operations' ),
			'changes'  => __( 'Cancellation, postponement and format-change terms', 'cywater-operations' ),
		);
		foreach ( $textareas as $key => $label ) :
			$constant = constant( __CLASS__ . '::META_' . strtoupper( $key ) );
			?>
			<tr><th><label for="cywater-event-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><textarea class="large-text" rows="3" id="cywater-event-<?php echo esc_attr( $key ); ?>" name="cywater_event_<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( get_post_meta( $post->ID, $constant, true ) ); ?></textarea></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<hr>
		<p><strong><?php esc_html_e( 'Workflow state:', 'cywater-operations' ); ?></strong> <?php echo esc_html( self::states()[ $state ] ); ?></p>
		<?php if ( self::is_paid( $post->ID ) && ! self::adapter_available( $post->ID ) ) : ?>
			<p><strong><?php esc_html_e( 'Payment gate:', 'cywater-operations' ); ?></strong> <?php esc_html_e( 'Closed — no verified paid-ticket adapter is active.', 'cywater-operations' ); ?></p>
		<?php endif; ?>
		<?php if ( $missing ) : ?>
			<p><strong><?php esc_html_e( 'Still required:', 'cywater-operations' ); ?></strong> <?php echo esc_html( implode( ', ', $missing ) ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'The required public terms are complete. Any material change after submission invalidates the approval and closes the paid-registration gate.', 'cywater-operations' ); ?></p>
		<?php endif; ?>
		<?php self::render_editor_actions( $state ); ?>
		<?php
	}

	private static function render_editor_actions( $state ) {
		if ( ! current_user_can( 'cywater_submit_paid_event_approval' ) ) {
			return;
		}
		$options = array( '' => __( 'Save without changing workflow', 'cywater-operations' ) );
		if ( in_array( $state, array( self::STATE_DRAFT, self::STATE_CLOSED ), true ) ) {
			$options['terms_complete'] = __( 'Mark terms complete', 'cywater-operations' );
		}
		if ( self::STATE_TERMS_COMPLETE === $state ) {
			$options['submit'] = __( 'Submit for independent governance approval', 'cywater-operations' );
		}
		if ( self::STATE_APPROVED === $state && current_user_can( 'cywater_open_paid_event_registration' ) ) {
			$options['open'] = __( 'Open paid registration', 'cywater-operations' );
		}
		if ( in_array( $state, array( self::STATE_APPROVED, self::STATE_REGISTRATION_OPEN ), true ) ) {
			$options['close'] = __( 'Close paid registration', 'cywater-operations' );
		}
		if ( count( $options ) < 2 ) {
			return;
		}
		echo '<p><label for="cywater-event-action"><strong>' . esc_html__( 'After saving', 'cywater-operations' ) . '</strong></label><br><select id="cywater-event-action" name="cywater_event_action">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
	}

	public static function save_event( $post_id, $post ) {
		if ( self::$integrity_guard || wp_is_post_revision( $post_id ) || ! $post instanceof WP_Post || 'cyw_event' !== $post->post_type ) {
			return;
		}

		$has_nonce = isset( $_POST['cywater_paid_event_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_paid_event_nonce'] ) ), 'cywater_paid_event_save' );
		if ( $has_nonce && current_user_can( 'edit_post', $post_id ) ) {
			self::save_fields( $post_id );
		}

		self::enforce_integrity( $post_id, 'event_changed' );

		if ( $has_nonce && isset( $_POST['cywater_event_action'] ) ) {
			$action = sanitize_key( wp_unslash( $_POST['cywater_event_action'] ) );
			if ( $action ) {
				$result = self::editor_transition( $post_id, $action );
				if ( is_wp_error( $result ) ) {
					self::queue_workflow_error( $result->get_error_code() );
				}
			}
		}
	}

	private static function queue_workflow_error( $error_code ) {
		$error_code = sanitize_key( $error_code );
		add_filter(
			'redirect_post_location',
			static function ( $location ) use ( $error_code ) {
				return add_query_arg( 'cywater_paid_event_error', $error_code, $location );
			},
			99
		);
	}

	/** Explain a rejected transition without echoing Event terms or adapter data. */
	public static function workflow_admin_notice() {
		if ( ! current_user_can( 'edit_cyw_events' ) ) {
			return;
		}
		$error_code = sanitize_key( wp_unslash( $_GET['cywater_paid_event_error'] ?? '' ) );
		if ( ! $error_code ) {
			return;
		}
		?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The paid-event workflow change was rejected. Review the required public terms, ticket readiness and approval status; no paid-registration gate was opened.', 'cywater-operations' ); ?></p></div>
		<?php
	}

	private static function save_fields( $post_id ) {
		self::$integrity_guard = true;
		try {
			update_post_meta( $post_id, self::META_PAID, isset( $_POST['cywater_paid_event'] ) ? '1' : '0' );
			update_post_meta( $post_id, self::META_FEE, self::sanitize_amount( wp_unslash( $_POST['cywater_event_fee'] ?? '' ) ) );
			update_post_meta( $post_id, self::META_CURRENCY, self::sanitize_currency( wp_unslash( $_POST['cywater_event_currency'] ?? '' ) ) );
			update_post_meta( $post_id, self::META_DEADLINE, self::sanitize_date( wp_unslash( $_POST['cywater_event_deadline'] ?? '' ) ) );
			foreach ( array( 'refund', 'transfer', 'capacity', 'changes' ) as $key ) {
				$constant = constant( __CLASS__ . '::META_' . strtoupper( $key ) );
				update_post_meta( $post_id, $constant, sanitize_textarea_field( wp_unslash( $_POST[ 'cywater_event_' . $key ] ?? '' ) ) );
			}
		} finally {
			self::$integrity_guard = false;
		}
	}

	public static function sanitize_amount( $value ) {
		$value = preg_replace( '/[^0-9.]/', '', (string) $value );
		return $value && (float) $value > 0 ? number_format( (float) $value, 2, '.', '' ) : '';
	}

	public static function sanitize_currency( $value ) {
		$value = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) );
		return 3 === strlen( $value ) ? $value : '';
	}

	public static function sanitize_date( $value ) {
		$value = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	public static function sanitize_snapshot( $value ) {
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';
	}

	public static function post_updated( $post_id, $post_after, $post_before ) {
		if ( $post_after instanceof WP_Post && 'cyw_event' === $post_after->post_type && $post_after->post_title !== $post_before->post_title ) {
			self::enforce_integrity( $post_id, 'event_title_changed' );
		}
	}

	public static function critical_meta_changed( $meta_id, $post_id, $meta_key, $meta_value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( self::$integrity_guard || 'cyw_event' !== get_post_type( $post_id ) || ! in_array( $meta_key, self::critical_meta_keys(), true ) ) {
			return;
		}
		self::enforce_integrity( $post_id, 'critical_terms_changed' );
	}

	/** @return array<int, string> */
	private static function critical_meta_keys() {
		return array(
			self::META_PAID,
			self::META_FEE,
			self::META_CURRENCY,
			self::META_DEADLINE,
			self::META_REFUND,
			self::META_TRANSFER,
			self::META_CAPACITY,
			self::META_CHANGES,
			'_cyw_start_date',
			'_cyw_end_date',
			'_cyw_date_label',
			'_cyw_location',
			'_cyw_format',
		);
	}

	public static function enforce_integrity( $event_id, $reason = 'integrity_check' ) {
		if ( self::$integrity_guard || 'cyw_event' !== get_post_type( $event_id ) ) {
			return;
		}

		self::$integrity_guard = true;
		try {
			$state           = self::state( $event_id );
			$current_hash    = self::fingerprint( $event_id );
			$submission_hash = (string) get_post_meta( $event_id, self::META_SUBMIT_HASH, true );
			$approval_hash   = (string) get_post_meta( $event_id, self::META_APPROVAL_HASH, true );

			if ( ! self::is_paid( $event_id ) ) {
				if ( self::STATE_DRAFT !== $state || $submission_hash || $approval_hash ) {
					$result = self::set_state( $event_id, self::STATE_DRAFT, $reason );
					if ( ! is_wp_error( $result ) ) {
						self::clear_approval( $event_id );
					}
				}
				return;
			}

			$invalid = false;
			if ( self::STATE_PENDING_APPROVAL === $state ) {
				$invalid = ! $submission_hash || ! hash_equals( $submission_hash, $current_hash );
			} elseif ( in_array( $state, array( self::STATE_APPROVED, self::STATE_REGISTRATION_OPEN, self::STATE_CLOSED ), true ) ) {
				$invalid = ! $approval_hash || ! hash_equals( $approval_hash, $current_hash );
			}

			if ( $invalid ) {
				$result = self::set_state( $event_id, self::requirements_complete( $event_id ) ? self::STATE_TERMS_COMPLETE : self::STATE_DRAFT, $reason );
				if ( ! is_wp_error( $result ) ) {
					self::clear_approval( $event_id );
					do_action( 'cywater_operations_paid_event_invalidated', $event_id, $state, $reason );
				}
			}
		} finally {
			self::$integrity_guard = false;
		}
	}

	private static function clear_approval( $event_id ) {
		delete_post_meta( $event_id, self::META_SUBMIT_HASH );
		delete_post_meta( $event_id, self::META_SUBMIT_SNAPSHOT );
		self::clear_approval_decision( $event_id );
	}

	private static function clear_approval_decision( $event_id ) {
		delete_post_meta( $event_id, self::META_APPROVAL_HASH );
		delete_post_meta( $event_id, self::META_APPROVED_BY );
		delete_post_meta( $event_id, self::META_APPROVED_AT );
	}

	public static function editor_transition( $event_id, $action, $actor_user_id = 0 ) {
		$actor_user_id = $actor_user_id ?: get_current_user_id();
		$state         = self::state( $event_id );
		if ( ! user_can( $actor_user_id, 'cywater_submit_paid_event_approval' ) || ! self::is_paid( $event_id ) ) {
			return new WP_Error( 'forbidden', __( 'This account cannot submit paid-event terms.', 'cywater-operations' ) );
		}

		if ( 'terms_complete' === $action && in_array( $state, array( self::STATE_DRAFT, self::STATE_CLOSED ), true ) ) {
			if ( ! self::requirements_complete( $event_id ) ) {
				return new WP_Error( 'incomplete', __( 'Complete every required public term first.', 'cywater-operations' ) );
			}
			$result = self::set_state( $event_id, self::STATE_TERMS_COMPLETE, 'terms_marked_complete' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			self::clear_approval( $event_id );
			return true;
		}

		if ( 'submit' === $action && self::STATE_TERMS_COMPLETE === $state ) {
			if ( ! self::requirements_complete( $event_id ) ) {
				return new WP_Error( 'incomplete', __( 'Complete every required public term first.', 'cywater-operations' ) );
			}
			$adapter = self::adapter_snapshot( $event_id );
			if ( is_wp_error( $adapter ) ) {
				return $adapter;
			}
			$snapshot = wp_json_encode( self::fingerprint_data( $event_id ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( false === $snapshot ) {
				return new WP_Error( 'submission_write_failed', __( 'The approval snapshot could not be encoded. Paid registration remains closed.', 'cywater-operations' ) );
			}
			$hash = hash( 'sha256', $snapshot );
			self::$integrity_guard = true;
			try {
				update_post_meta( $event_id, self::META_SUBMIT_HASH, $hash );
				update_post_meta( $event_id, self::META_SUBMIT_SNAPSHOT, $snapshot );
				self::clear_approval_decision( $event_id );
			} finally {
				self::$integrity_guard = false;
			}
			$stored_hash     = (string) get_post_meta( $event_id, self::META_SUBMIT_HASH, true );
			$stored_snapshot = (string) get_post_meta( $event_id, self::META_SUBMIT_SNAPSHOT, true );
			if ( ! hash_equals( $hash, $stored_hash ) || ! hash_equals( $snapshot, $stored_snapshot ) ) {
				self::clear_approval( $event_id );
				return new WP_Error( 'submission_write_failed', __( 'The approval snapshot could not be stored. Paid registration remains closed.', 'cywater-operations' ) );
			}
			$result = self::set_state( $event_id, self::STATE_PENDING_APPROVAL, 'submitted_for_approval' );
			if ( is_wp_error( $result ) ) {
				self::clear_approval( $event_id );
				return $result;
			}
			return true;
		}

		if ( 'open' === $action && self::STATE_APPROVED === $state && user_can( $actor_user_id, 'cywater_open_paid_event_registration' ) ) {
			if ( ! self::approval_current( $event_id ) ) {
				self::enforce_integrity( $event_id, 'approval_no_longer_current' );
				return new WP_Error( 'stale_approval', __( 'The approved terms have changed.', 'cywater-operations' ) );
			}
			if ( ! self::adapter_available( $event_id ) ) {
				return new WP_Error( 'adapter_unavailable', __( 'No verified paid-ticket adapter is active. Paid registration remains closed.', 'cywater-operations' ) );
			}
			$result = self::set_state( $event_id, self::STATE_REGISTRATION_OPEN, 'registration_opened' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return true;
		}

		if ( 'close' === $action && in_array( $state, array( self::STATE_APPROVED, self::STATE_REGISTRATION_OPEN ), true ) ) {
			$result = self::set_state( $event_id, self::STATE_CLOSED, 'registration_closed' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return true;
		}

		return new WP_Error( 'invalid_transition', __( 'That workflow transition is not available.', 'cywater-operations' ) );
	}

	public static function governance_decision( $event_id, $decision, $actor_user_id = 0 ) {
		$actor_user_id = $actor_user_id ?: get_current_user_id();
		if ( ! user_can( $actor_user_id, 'cywater_approve_paid_event' ) ) {
			return new WP_Error( 'forbidden', __( 'This account cannot approve paid Events.', 'cywater-operations' ) );
		}

		$state = self::state( $event_id );
		if ( 'approve' === $decision ) {
			$submitted      = (string) get_post_meta( $event_id, self::META_SUBMIT_HASH, true );
			$stored_snapshot = (string) get_post_meta( $event_id, self::META_SUBMIT_SNAPSHOT, true );
			$current_snapshot = wp_json_encode( self::fingerprint_data( $event_id ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$current          = false === $current_snapshot ? '' : hash( 'sha256', $current_snapshot );
			if ( self::STATE_PENDING_APPROVAL !== $state || ! self::requirements_complete( $event_id ) || ! $submitted || ! $stored_snapshot || ! $current || ! hash_equals( $submitted, $current ) || ! hash_equals( $stored_snapshot, $current_snapshot ) ) {
				self::enforce_integrity( $event_id, 'governance_stale_submission' );
				return new WP_Error( 'not_approvable', __( 'The submitted terms are incomplete or no longer current.', 'cywater-operations' ) );
			}
			$approved_at = current_time( 'mysql', true );
			self::$integrity_guard = true;
			try {
				update_post_meta( $event_id, self::META_APPROVAL_HASH, $current );
				update_post_meta( $event_id, self::META_APPROVED_BY, $actor_user_id );
				update_post_meta( $event_id, self::META_APPROVED_AT, $approved_at );
			} finally {
				self::$integrity_guard = false;
			}
			$stored_approval_hash = (string) get_post_meta( $event_id, self::META_APPROVAL_HASH, true );
			$stored_approved_by   = (int) get_post_meta( $event_id, self::META_APPROVED_BY, true );
			$stored_approved_at   = (string) get_post_meta( $event_id, self::META_APPROVED_AT, true );
			if ( ! hash_equals( $current, $stored_approval_hash ) || (int) $actor_user_id !== $stored_approved_by || ! hash_equals( $approved_at, $stored_approved_at ) ) {
				self::clear_approval_decision( $event_id );
				return new WP_Error( 'approval_write_failed', __( 'The approval record could not be stored. Paid registration remains closed.', 'cywater-operations' ) );
			}
			$result = self::set_state( $event_id, self::STATE_APPROVED, 'governance_approved' );
			if ( is_wp_error( $result ) ) {
				self::clear_approval_decision( $event_id );
				return $result;
			}
			return true;
		}

		if ( 'return' === $decision && in_array( $state, array( self::STATE_PENDING_APPROVAL, self::STATE_APPROVED, self::STATE_REGISTRATION_OPEN ), true ) ) {
			$result = self::set_state( $event_id, self::requirements_complete( $event_id ) ? self::STATE_TERMS_COMPLETE : self::STATE_DRAFT, 'governance_returned' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			self::clear_approval( $event_id );
			return true;
		}

		return new WP_Error( 'invalid_decision', __( 'That governance decision is not available.', 'cywater-operations' ) );
	}

	private static function set_state( $event_id, $new_state, $reason ) {
		if ( ! isset( self::states()[ $new_state ] ) ) {
			return new WP_Error( 'invalid_state', __( 'The requested paid-event state is invalid.', 'cywater-operations' ) );
		}
		$old_state = self::state( $event_id );
		if ( $old_state === $new_state ) {
			return true;
		}
		if ( ! CYWater_Operations_Audit::record( 'paid_event', $event_id, 'transition_authorized', $old_state, $new_state, 0, $reason ) ) {
			return new WP_Error( 'audit_unavailable', __( 'The required audit record could not be written. No workflow change was made.', 'cywater-operations' ) );
		}
		$had_state = metadata_exists( 'post', $event_id, self::META_STATE );
		$raw_state = get_post_meta( $event_id, self::META_STATE, true );
		update_post_meta( $event_id, self::META_STATE, $new_state );
		if ( self::state( $event_id ) !== $new_state ) {
			if ( $had_state ) {
				update_post_meta( $event_id, self::META_STATE, $raw_state );
			} else {
				delete_post_meta( $event_id, self::META_STATE );
			}
			CYWater_Operations_Audit::record( 'paid_event', $event_id, 'transition_failed', $old_state, $new_state, 0, 'write_failed' );
			return new WP_Error( 'state_write_failed', __( 'The workflow state could not be stored. Paid registration remains closed.', 'cywater-operations' ) );
		}
		return true;
	}

	public static function state( $event_id ) {
		$state = (string) get_post_meta( $event_id, self::META_STATE, true );
		return isset( self::states()[ $state ] ) ? $state : self::STATE_DRAFT;
	}

	public static function is_paid( $event_id ) {
		return '1' === (string) get_post_meta( $event_id, self::META_PAID, true );
	}

	/** @return array<int, string> */
	public static function missing_requirements( $event_id ) {
		if ( ! self::is_paid( $event_id ) ) {
			return array();
		}

		$requirements = array(
			'event_title' => array( get_the_title( $event_id ), __( 'Event title', 'cywater-operations' ), 2 ),
			'start_date'  => array( get_post_meta( $event_id, '_cyw_start_date', true ), __( 'Event start date', 'cywater-operations' ), 4 ),
			'location'    => array( get_post_meta( $event_id, '_cyw_location', true ), __( 'Event location or Online', 'cywater-operations' ), 2 ),
			'fee'         => array( get_post_meta( $event_id, self::META_FEE, true ), __( 'Registration fee', 'cywater-operations' ), 1 ),
			'currency'    => array( get_post_meta( $event_id, self::META_CURRENCY, true ), __( 'Three-letter currency', 'cywater-operations' ), 3 ),
			'deadline'    => array( get_post_meta( $event_id, self::META_DEADLINE, true ), __( 'Cancellation deadline', 'cywater-operations' ), 8 ),
			'refund'      => array( get_post_meta( $event_id, self::META_REFUND, true ), __( 'Cancellation and refund terms', 'cywater-operations' ), 20 ),
			'transfer'    => array( get_post_meta( $event_id, self::META_TRANSFER, true ), __( 'Transfer or substitution terms', 'cywater-operations' ), 20 ),
			'capacity'    => array( get_post_meta( $event_id, self::META_CAPACITY, true ), __( 'Capacity and waitlist terms', 'cywater-operations' ), 20 ),
			'changes'     => array( get_post_meta( $event_id, self::META_CHANGES, true ), __( 'Event-change terms', 'cywater-operations' ), 20 ),
		);

		$missing = array();
		foreach ( $requirements as $requirement ) {
			if ( strlen( trim( (string) $requirement[0] ) ) < (int) $requirement[2] ) {
				$missing[] = $requirement[1];
			}
		}
		if ( (float) get_post_meta( $event_id, self::META_FEE, true ) <= 0 ) {
			$missing[] = __( 'Positive registration fee', 'cywater-operations' );
		}

		return array_values( array_unique( apply_filters( 'cywater_operations_paid_event_missing_requirements', $missing, $event_id ) ) );
	}

	public static function requirements_complete( $event_id ) {
		return self::is_paid( $event_id ) && ! self::missing_requirements( $event_id );
	}

	/** @return array<string, mixed> */
	public static function fingerprint_data( $event_id ) {
		$data = array(
			'post_title' => get_the_title( $event_id ),
		);
		foreach ( self::critical_meta_keys() as $key ) {
			$data[ $key ] = get_post_meta( $event_id, $key, true );
		}
		$adapter = self::adapter_snapshot( $event_id );
		if ( is_wp_error( $adapter ) ) {
			$data['ticket_adapter_error'] = $adapter->get_error_code();
		} else {
			foreach ( $adapter as $key => $value ) {
				$data[ 'ticket_' . $key ] = $value;
			}
		}
		ksort( $data );
		return (array) apply_filters( 'cywater_operations_paid_event_fingerprint_data', $data, $event_id );
	}

	public static function fingerprint( $event_id ) {
		return hash( 'sha256', wp_json_encode( self::fingerprint_data( $event_id ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/** @return array<string, mixed> */
	public static function submission_snapshot( $event_id ) {
		$snapshot = json_decode( (string) get_post_meta( $event_id, self::META_SUBMIT_SNAPSHOT, true ), true );
		return is_array( $snapshot ) ? $snapshot : array();
	}

	/** @return array<string, mixed>|WP_Error */
	public static function adapter_snapshot( $event_id ) {
		$snapshot = apply_filters(
			'cywater_operations_paid_event_adapter_snapshot',
			new WP_Error( 'adapter_unavailable', __( 'No verified paid-ticket adapter is active.', 'cywater-operations' ) ),
			absint( $event_id )
		);
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		if ( ! is_array( $snapshot ) ) {
			return new WP_Error( 'adapter_invalid', __( 'The paid-ticket adapter did not provide a valid readiness snapshot.', 'cywater-operations' ) );
		}

		$normalized = array(
			'provider'       => sanitize_key( $snapshot['provider'] ?? '' ),
			'ticket_id'      => sanitize_text_field( (string) ( $snapshot['ticket_id'] ?? '' ) ),
			'enabled'        => ! empty( $snapshot['enabled'] ),
			'amount'         => self::sanitize_amount( $snapshot['amount'] ?? '' ),
			'currency'       => self::sanitize_currency( $snapshot['currency'] ?? '' ),
			'capacity'       => absint( $snapshot['capacity'] ?? 0 ),
			'checkout_ready' => ! empty( $snapshot['checkout_ready'] ),
		);
		// Provider-specific, non-secret readiness fields are retained when
		// supplied so changes to sales dates, reviewed Commerce pages or payment
		// mode invalidate the approved fingerprint. Generic QA/test adapters are
		// not required to implement these optional fields.
		if ( array_key_exists( 'sale_start', $snapshot ) ) {
			$normalized['sale_start'] = sanitize_text_field( (string) $snapshot['sale_start'] );
		}
		if ( array_key_exists( 'sale_end', $snapshot ) ) {
			$normalized['sale_end'] = sanitize_text_field( (string) $snapshot['sale_end'] );
		}
		if ( array_key_exists( 'checkout_page_id', $snapshot ) ) {
			$normalized['checkout_page_id'] = absint( $snapshot['checkout_page_id'] );
		}
		if ( array_key_exists( 'success_page_id', $snapshot ) ) {
			$normalized['success_page_id'] = absint( $snapshot['success_page_id'] );
		}
		if ( array_key_exists( 'live_mode', $snapshot ) ) {
			$normalized['live_mode'] = ! empty( $snapshot['live_mode'] );
		}
		if ( ! $normalized['provider'] || ! $normalized['ticket_id'] || ! $normalized['enabled'] || ! $normalized['amount'] || ! $normalized['currency'] || ! $normalized['capacity'] || ! $normalized['checkout_ready'] ) {
			return new WP_Error( 'adapter_not_ready', __( 'The paid-ticket configuration is incomplete or checkout is not ready.', 'cywater-operations' ) );
		}
		if ( ( isset( $normalized['sale_start'] ) && ! $normalized['sale_start'] ) || ( isset( $normalized['sale_end'] ) && ! $normalized['sale_end'] ) || ( isset( $normalized['checkout_page_id'] ) && ! $normalized['checkout_page_id'] ) || ( isset( $normalized['success_page_id'] ) && ! $normalized['success_page_id'] ) || ( isset( $normalized['live_mode'] ) && ! $normalized['live_mode'] ) ) {
			return new WP_Error( 'adapter_not_ready', __( 'The paid-ticket configuration is incomplete or checkout is not ready.', 'cywater-operations' ) );
		}
		$declared_amount   = self::sanitize_amount( get_post_meta( $event_id, self::META_FEE, true ) );
		$declared_currency = self::sanitize_currency( get_post_meta( $event_id, self::META_CURRENCY, true ) );
		if ( $normalized['amount'] !== $declared_amount || $normalized['currency'] !== $declared_currency ) {
			return new WP_Error( 'adapter_terms_mismatch', __( 'The ticket price or currency does not match the submitted public terms.', 'cywater-operations' ) );
		}
		return $normalized;
	}

	public static function adapter_available( $event_id ) {
		return ! is_wp_error( self::adapter_snapshot( $event_id ) );
	}

	private static function approval_current( $event_id ) {
		$approved = (string) get_post_meta( $event_id, self::META_APPROVAL_HASH, true );
		return $approved && hash_equals( $approved, self::fingerprint( $event_id ) ) && self::requirements_complete( $event_id );
	}

	public static function is_payment_ready( $event_id ) {
		if ( 'cyw_event' !== get_post_type( $event_id ) ) {
			return false;
		}
		if ( ! self::is_paid( $event_id ) ) {
			return true;
		}
		if ( ! self::adapter_available( $event_id ) ) {
			return false;
		}
		$ready = self::STATE_REGISTRATION_OPEN === self::state( $event_id ) && self::approval_current( $event_id );
		return (bool) apply_filters( 'cywater_operations_paid_event_is_ready', $ready, $event_id );
	}

	public static function filter_registration_ready( $ready, $event_id ) {
		return (bool) $ready && self::is_payment_ready( absint( $event_id ) );
	}

	public static function admin_menu() {
		add_menu_page(
			__( 'Paid Event approvals', 'cywater-operations' ),
			__( 'Event approvals', 'cywater-operations' ),
			'cywater_approve_paid_event',
			'cywater-event-approvals',
			array( __CLASS__, 'render_approval_page' ),
			'dashicons-yes-alt',
			26
		);
	}

	public static function render_approval_page() {
		if ( ! current_user_can( 'cywater_approve_paid_event' ) ) {
			wp_die( esc_html__( 'You are not allowed to approve paid Events.', 'cywater-operations' ) );
		}

		$events = get_posts(
			array(
				'post_type'      => 'cyw_event',
				'post_status'    => array( 'draft', 'pending', 'publish', 'private', 'future' ),
				'posts_per_page' => 100,
				'meta_key'       => self::META_STATE,
				'meta_value'     => self::STATE_PENDING_APPROVAL,
				'orderby'        => 'modified',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Paid Event approvals', 'cywater-operations' ); ?></h1>
		<p><?php esc_html_e( 'Approve only the submitted public terms. This screen cannot create a ticket, payment, refund or Stripe product.', 'cywater-operations' ); ?></p>
		<?php if ( ! $events ) : ?><p><?php esc_html_e( 'No paid Events are waiting for approval.', 'cywater-operations' ); ?></p><?php endif; ?>
		<?php foreach ( $events as $event ) : ?>
			<div class="card" style="max-width:900px"><h2><?php echo esc_html( get_the_title( $event ) ); ?></h2>
			<p><?php echo esc_html( implode( ', ', self::missing_requirements( $event->ID ) ) ?: __( 'Required terms complete', 'cywater-operations' ) ); ?></p>
			<?php $snapshot_current = self::render_submission_snapshot( $event->ID ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:.5rem"><input type="hidden" name="action" value="cywater_paid_event_decision"><input type="hidden" name="event_id" value="<?php echo esc_attr( $event->ID ); ?>"><input type="hidden" name="decision" value="approve"><?php wp_nonce_field( 'cywater_paid_event_decision_' . $event->ID ); ?><button class="button button-primary" type="submit" <?php disabled( ! $snapshot_current ); ?>><?php esc_html_e( 'Approve submitted terms', 'cywater-operations' ); ?></button></form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block"><input type="hidden" name="action" value="cywater_paid_event_decision"><input type="hidden" name="event_id" value="<?php echo esc_attr( $event->ID ); ?>"><input type="hidden" name="decision" value="return"><?php wp_nonce_field( 'cywater_paid_event_decision_' . $event->ID ); ?><button class="button" type="submit"><?php esc_html_e( 'Return for revision', 'cywater-operations' ); ?></button></form>
			</div>
		<?php endforeach; ?></div>
		<?php
	}

	private static function render_submission_snapshot( $event_id ) {
		$snapshot = self::submission_snapshot( $event_id );
		if ( ! $snapshot ) {
			echo '<p><strong>' . esc_html__( 'Submitted snapshot unavailable. Approval is blocked.', 'cywater-operations' ) . '</strong></p>';
			return false;
		}
		$submitted_hash = (string) get_post_meta( $event_id, self::META_SUBMIT_HASH, true );
		$current        = $submitted_hash && hash_equals( $submitted_hash, self::fingerprint( $event_id ) );
		echo '<p><strong>' . esc_html__( 'Snapshot integrity:', 'cywater-operations' ) . '</strong> ' . esc_html( $current ? __( 'Current', 'cywater-operations' ) : __( 'Changed — approval blocked', 'cywater-operations' ) ) . '</p>';
		$labels = array(
			'post_title'        => __( 'Event title', 'cywater-operations' ),
			'_cyw_start_date'   => __( 'Start date', 'cywater-operations' ),
			'_cyw_end_date'     => __( 'End date', 'cywater-operations' ),
			'_cyw_location'     => __( 'Location', 'cywater-operations' ),
			self::META_FEE      => __( 'Fee', 'cywater-operations' ),
			self::META_CURRENCY => __( 'Currency', 'cywater-operations' ),
			self::META_DEADLINE => __( 'Cancellation deadline', 'cywater-operations' ),
			self::META_REFUND   => __( 'Refund terms', 'cywater-operations' ),
			self::META_TRANSFER => __( 'Transfer terms', 'cywater-operations' ),
			self::META_CAPACITY => __( 'Capacity terms', 'cywater-operations' ),
			self::META_CHANGES  => __( 'Event-change terms', 'cywater-operations' ),
		);
		echo '<table class="widefat striped" style="margin:1rem 0"><tbody>';
		foreach ( $snapshot as $key => $value ) {
			if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
				continue;
			}
			$label = $labels[ $key ] ?? ucwords( str_replace( array( '_', '-' ), ' ', ltrim( (string) $key, '_' ) ) );
			echo '<tr><th style="width:220px">' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) $value ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		return (bool) $current;
	}

	public static function handle_decision() {
		if ( ! current_user_can( 'cywater_approve_paid_event' ) ) {
			wp_die( esc_html__( 'You are not allowed to approve paid Events.', 'cywater-operations' ), '', array( 'response' => 403 ) );
		}
		$event_id = absint( $_POST['event_id'] ?? 0 );
		$decision = sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) );
		check_admin_referer( 'cywater_paid_event_decision_' . $event_id );
		$result = self::governance_decision( $event_id, $decision );
		$status = is_wp_error( $result ) ? $result->get_error_code() : 'updated';
		wp_safe_redirect( add_query_arg( 'cywater_event_approval', sanitize_key( $status ), admin_url( 'admin.php?page=cywater-event-approvals' ) ) );
		exit;
	}
}
