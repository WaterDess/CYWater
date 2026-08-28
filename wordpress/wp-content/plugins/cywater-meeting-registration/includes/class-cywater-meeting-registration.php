<?php
/**
 * Reusable Event-scoped Annual Meeting registration.
 *
 * Event Tickets owns attendee records. PMPro owns membership. This module owns
 * only the meeting form, an immutable registration/fee snapshot, protected
 * student evidence, and staff reporting. It never creates a payment or marks a
 * registration paid.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Meeting_Registration {
	private const META_PREFIX       = '_cywater_meeting_';
	private const ATTENDEE_TYPE     = 'tribe_rsvp_attendees';
	private const MAX_PROOF_BYTES   = 5 * MB_IN_BYTES;
	private const MAX_REPORT_BYTES  = 20 * MB_IN_BYTES;
	private const DEFAULT_CAPACITY  = 1000;
	private const SUPPORT_EMAIL     = 'contact@cywater.org';
	public const STAFF_CAPABILITY   = 'cywater_review_meeting_registrations';

	public static function register() {
		add_action( 'add_meta_boxes_cyw_event', array( __CLASS__, 'add_event_box' ) );
		add_action( 'save_post_cyw_event', array( __CLASS__, 'save_event' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_event_module' ), 38 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_internal_ticket_view' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'suppress_internal_ticket_ui' ), 20 );
		add_filter( 'tribe_tickets_order_link_template_already_rendered', array( __CLASS__, 'hide_internal_ticket_link' ) );
		add_action( 'admin_post_cywater_meeting_register', array( __CLASS__, 'handle_registration' ) );
		add_action( 'cywater_meeting_send_confirmation', array( __CLASS__, 'send_queued_confirmation' ) );
		add_action( 'admin_post_cywater_meeting_proof', array( __CLASS__, 'stream_proof' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 30 );
		add_action( 'admin_post_cywater_meeting_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_cywater_meeting_export_package', array( __CLASS__, 'export_package' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_proof' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'cywater meeting-registration configure-2026', array( __CLASS__, 'cli_configure_2026' ) );
			WP_CLI::add_command( 'cywater meeting-registration qa', array( __CLASS__, 'cli_qa' ) );
		}
	}

	public static function activate() {
		self::private_directory();
	}

	public static function add_event_box() {
		add_meta_box(
			'cywater-meeting-registration',
			__( 'CYWater Annual Meeting registration', 'cywater-meeting-registration' ),
			array( __CLASS__, 'render_event_box' ),
			'cyw_event',
			'normal',
			'default'
		);
	}

	public static function render_event_box( $post ) {
		wp_nonce_field( 'cywater_meeting_event', 'cywater_meeting_event_nonce' );
		$enabled  = self::is_enabled( $post->ID );
		$deadline = (string) get_post_meta( $post->ID, self::META_PREFIX . 'early_deadline', true );
		$open_at  = (string) get_post_meta( $post->ID, self::META_PREFIX . 'open_at', true );
		$close_at = (string) get_post_meta( $post->ID, self::META_PREFIX . 'close_at', true );
		$rates    = self::rates( $post->ID );
		?>
		<p><label><input type="checkbox" name="cywater_meeting_enabled" value="1" <?php checked( $enabled ); ?>> <strong><?php esc_html_e( 'Enable the reusable registration module on this Annual Meeting', 'cywater-meeting-registration' ); ?></strong></label></p>
		<p class="description"><?php esc_html_e( 'This creates an Event Tickets RSVP attendee record and a fee recommendation only. Payment remains external and is never marked paid here.', 'cywater-meeting-registration' ); ?></p>
		<div style="display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:12px">
			<p><label><strong><?php esc_html_e( 'Registration opens', 'cywater-meeting-registration' ); ?></strong><br><input type="datetime-local" name="cywater_meeting_open_at" value="<?php echo esc_attr( str_replace( ' ', 'T', $open_at ) ); ?>"></label></p>
			<p><label><strong><?php esc_html_e( 'Early-bird deadline', 'cywater-meeting-registration' ); ?></strong><br><input type="datetime-local" name="cywater_meeting_early_deadline" value="<?php echo esc_attr( str_replace( ' ', 'T', $deadline ) ); ?>"></label></p>
			<p><label><strong><?php esc_html_e( 'Registration closes', 'cywater-meeting-registration' ); ?></strong><br><input type="datetime-local" name="cywater_meeting_close_at" value="<?php echo esc_attr( str_replace( ' ', 'T', $close_at ) ); ?>"></label></p>
		</div>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Registration category', 'cywater-meeting-registration' ); ?></th><th><?php esc_html_e( 'Early CNY', 'cywater-meeting-registration' ); ?></th><th><?php esc_html_e( 'Standard CNY', 'cywater-meeting-registration' ); ?></th><th><?php esc_html_e( 'Approx. early USD', 'cywater-meeting-registration' ); ?></th><th><?php esc_html_e( 'Approx. standard USD', 'cywater-meeting-registration' ); ?></th></tr></thead><tbody>
		<?php foreach ( self::rate_labels() as $key => $label ) : ?>
			<tr><th><?php echo esc_html( $label ); ?></th><?php foreach ( array( 'early_cny', 'standard_cny', 'early_usd', 'standard_usd' ) as $column ) : ?><td><input type="number" min="0" name="cywater_meeting_rates[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $column ); ?>]" value="<?php echo esc_attr( $rates[ $key ][ $column ] ); ?>"></td><?php endforeach; ?></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php
	}

	public static function save_event( $post_id ) {
		if ( ! isset( $_POST['cywater_meeting_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_meeting_event_nonce'] ) ), 'cywater_meeting_event' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, self::META_PREFIX . 'enabled', isset( $_POST['cywater_meeting_enabled'] ) ? '1' : '0' );
		foreach ( array( 'open_at', 'early_deadline', 'close_at' ) as $key ) {
			$value = isset( $_POST[ 'cywater_meeting_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'cywater_meeting_' . $key ] ) ) : '';
			update_post_meta( $post_id, self::META_PREFIX . $key, str_replace( 'T', ' ', $value ) );
		}
		$submitted = isset( $_POST['cywater_meeting_rates'] ) ? (array) wp_unslash( $_POST['cywater_meeting_rates'] ) : array();
		$rates     = self::default_rates();
		foreach ( $rates as $category => $columns ) {
			foreach ( array_keys( $columns ) as $column ) {
				if ( isset( $submitted[ $category ][ $column ] ) ) {
					$rates[ $category ][ $column ] = absint( $submitted[ $category ][ $column ] );
				}
			}
		}
		update_post_meta( $post_id, self::META_PREFIX . 'rates', $rates );
	}

	public static function enqueue_assets() {
		if ( is_singular( 'cyw_event' ) && self::is_enabled( get_queried_object_id() ) ) {
			wp_enqueue_style( 'cywater-meeting-registration', CYWATER_MEETING_REGISTRATION_URL . 'assets/meeting-registration.css', array(), CYWATER_MEETING_REGISTRATION_VERSION );
			wp_enqueue_script( 'cywater-meeting-registration', CYWATER_MEETING_REGISTRATION_URL . 'assets/meeting-registration.js', array(), CYWATER_MEETING_REGISTRATION_VERSION, true );
		}
	}

	/**
	 * Event Tickets is the private attendee store for this module, not the
	 * participant-facing workflow. Its generic "My tickets" screen exposes an
	 * editable Going/Not going control that does not represent the CYWater
	 * registration snapshot. Keep that implementation detail out of the public
	 * experience and return participants to the canonical confirmation instead.
	 */
	public static function redirect_internal_ticket_view() {
		if ( ! is_singular( 'cyw_event' ) ) {
			return;
		}
		$event_id = absint( get_queried_object_id() );
		$display  = (string) get_query_var( 'eventDisplay', '' );
		$is_edit  = isset( $_GET['tribe-edit-orders'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $event_id || ! self::is_enabled( $event_id ) || ( 'tickets' !== $display && ! $is_edit ) ) {
			return;
		}
		$url = get_permalink( $event_id );
		if ( get_current_user_id() && self::existing_attendee( $event_id, get_current_user_id() ) ) {
			$url = add_query_arg( 'meeting_registration', 'existing', $url );
		}
		wp_safe_redirect( $url . '#meeting-registration', 302 );
		exit;
	}

	public static function hide_internal_ticket_link( $already_rendered ) {
		// The Event Tickets content filter can temporarily change the loop post to
		// an attendee record. On a singular Event, the queried object is the only
		// authoritative participant-facing context; do not let an internal RSVP
		// post re-enable the vendor's parallel "View RSVP" workflow.
		$event_id = is_singular( 'cyw_event' )
			? absint( get_queried_object_id() )
			: absint( get_the_ID() );
		return $event_id && self::is_enabled( $event_id ) ? true : $already_rendered;
	}

	/**
	 * Remove Event Tickets' parallel attendee link only on the canonical Annual
	 * Meeting surface. Event Tickets remains the storage authority; CYWater owns
	 * the participant experience and its immutable registration confirmation.
	 */
	public static function suppress_internal_ticket_ui() {
		if ( ! is_singular( 'cyw_event' ) || ! self::is_enabled( get_queried_object_id() ) || ! class_exists( 'Tribe__Tickets__Tickets_View' ) ) {
			return;
		}
		$view = Tribe__Tickets__Tickets_View::instance();
		remove_filter( 'the_content', array( $view, 'inject_link_template_the_content' ), 9 );
		remove_action( 'tribe_events_single_event_after_the_meta', array( $view, 'inject_link_template' ), 4 );
	}

	public static function append_event_module( $content ) {
		if ( ! is_singular( 'cyw_event' ) || ! in_the_loop() || ! is_main_query() || ! self::is_enabled( get_the_ID() ) ) {
			return $content;
		}
		return $content . self::render_module( get_the_ID() );
	}

	private static function render_module( $event_id ) {
		$event_id = absint( $event_id );
		$user_id  = get_current_user_id();
		$existing = $user_id ? self::existing_attendee( $event_id, $user_id ) : 0;
		$phase    = self::phase( $event_id );
		$rates    = self::rates( $event_id );
		$profile  = self::profile( $user_id );
		$member   = self::membership_snapshot( $user_id );
		$category = 'student' === ( $profile['career_stage'] ?? '' ) ? 'student' : 'regular';
		$fee      = self::fee_snapshot( $event_id, $category, $member );
		$message  = isset( $_GET['meeting_registration'] ) ? sanitize_key( wp_unslash( $_GET['meeting_registration'] ) ) : '';
		ob_start();
		?>
		<section class="cywater-meeting" aria-labelledby="cywater-meeting-title">
			<p class="cywater-meeting__eyebrow"><?php esc_html_e( 'Annual Meeting registration', 'cywater-meeting-registration' ); ?></p>
			<h2 id="cywater-meeting-title"><?php echo esc_html( $existing ? __( 'Your meeting registration', 'cywater-meeting-registration' ) : __( 'Register for this meeting', 'cywater-meeting-registration' ) ); ?></h2>
			<p><?php echo esc_html( $existing ? __( 'Review the information and protected files submitted with your registration. Payment and accommodation remain separate organizer handoffs below.', 'cywater-meeting-registration' ) : __( 'Submit one registration per participant. CYWater records your registration and recommends the applicable external payment amount; it does not collect or confirm payment on this website.', 'cywater-meeting-registration' ) ); ?></p>
			<?php if ( $message ) : ?><div class="cywater-meeting__notice <?php echo esc_attr( 'submitted' === $message ? 'is-success' : 'is-error' ); ?>" role="status"><?php echo esc_html( self::message( $message ) ); ?></div><?php endif; ?>
			<?php if ( ! $user_id ) : ?>
				<div class="cywater-meeting__gate"><h3><?php esc_html_e( 'A registered account is required', 'cywater-meeting-registration' ); ?></h3><p><?php esc_html_e( 'Sign in to prefill your profile and receive the fee recommendation based on your current membership.', 'cywater-meeting-registration' ); ?></p><div class="cywater-meeting__actions"><a class="btn btn-primary" href="<?php echo esc_url( self::login_url( get_permalink( $event_id ) . '#meeting-registration' ) ); ?>"><?php esc_html_e( 'Sign in', 'cywater-meeting-registration' ); ?></a><a class="btn btn-secondary" href="<?php echo esc_url( self::registration_url( get_permalink( $event_id ) . '#meeting-registration' ) ); ?>"><?php esc_html_e( 'Create account', 'cywater-meeting-registration' ); ?></a></div></div>
			<?php elseif ( $existing ) : ?>
				<?php self::render_confirmation( $existing ); ?>
			<?php elseif ( 'open' !== $phase ) : ?>
				<div class="cywater-meeting__gate"><h3><?php echo esc_html( 'before' === $phase ? __( 'Registration is not open yet', 'cywater-meeting-registration' ) : __( 'Online registration is closed', 'cywater-meeting-registration' ) ); ?></h3><p><?php esc_html_e( 'For registration questions, contact contact@cywater.org.', 'cywater-meeting-registration' ); ?></p></div>
			<?php else : ?>
				<form id="meeting-registration" class="cywater-meeting__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" data-cywater-meeting-form data-rates="<?php echo esc_attr( wp_json_encode( $rates ) ); ?>" data-member-kind="<?php echo esc_attr( $member['kind'] ); ?>" data-early="<?php echo esc_attr( self::is_early( $event_id ) ? '1' : '0' ); ?>">
					<input type="hidden" name="action" value="cywater_meeting_register"><input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>"><?php wp_nonce_field( 'cywater_meeting_register_' . $event_id, 'cywater_meeting_nonce' ); ?>
					<div class="cywater-meeting__summary"><div><span><?php esc_html_e( 'Account', 'cywater-meeting-registration' ); ?></span><strong><?php echo esc_html( $profile['email'] ); ?></strong></div><div><span><?php esc_html_e( 'Current membership', 'cywater-meeting-registration' ); ?></span><strong><?php echo esc_html( $member['label'] ); ?></strong></div><div><span><?php esc_html_e( 'Recommended external payment', 'cywater-meeting-registration' ); ?></span><strong data-cywater-meeting-fee><?php echo esc_html( self::format_fee( $fee ) ); ?></strong></div></div>
					<fieldset><legend><?php esc_html_e( 'Participant information', 'cywater-meeting-registration' ); ?></legend><div class="cywater-meeting__grid">
					<?php self::input( 'first_name', __( 'First name', 'cywater-meeting-registration' ), $profile['first_name'], true ); self::input( 'last_name', __( 'Last name', 'cywater-meeting-registration' ), $profile['last_name'], true ); self::input( 'institution', __( 'Institution or employer', 'cywater-meeting-registration' ), $profile['institution'], true, 'wide' ); self::country_select( $profile['country'] ); self::input( 'professional_title', __( 'Position or professional title', 'cywater-meeting-registration' ), $profile['professional_title'], true ); self::input( 'phone', __( 'Phone number', 'cywater-meeting-registration' ), '', true ); ?>
					<label><span><?php esc_html_e( 'Registration category', 'cywater-meeting-registration' ); ?> *</span><select name="participant_category" required data-cywater-meeting-category><option value="regular" <?php selected( $category, 'regular' ); ?>><?php esc_html_e( 'Faculty or scholar', 'cywater-meeting-registration' ); ?></option><option value="student" <?php selected( $category, 'student' ); ?>><?php esc_html_e( 'Student', 'cywater-meeting-registration' ); ?></option><option value="corporate"><?php esc_html_e( 'Corporate representative', 'cywater-meeting-registration' ); ?></option><option value="invited"><?php esc_html_e( 'Invited speaker (subject to organizer verification)', 'cywater-meeting-registration' ); ?></option></select></label>
					<label><span><?php esc_html_e( 'Participation plan', 'cywater-meeting-registration' ); ?> *</span><select name="participation_plan" required data-cywater-meeting-plan><option value="attend"><?php esc_html_e( 'Attend only', 'cywater-meeting-registration' ); ?></option><option value="oral"><?php esc_html_e( 'Oral presentation', 'cywater-meeting-registration' ); ?></option><option value="flash"><?php esc_html_e( 'Youth flash talk', 'cywater-meeting-registration' ); ?></option><option value="poster"><?php esc_html_e( 'Poster presentation', 'cywater-meeting-registration' ); ?></option></select></label>
					<label class="wide" data-cywater-meeting-title-field hidden><span><?php esc_html_e( 'Proposed presentation title', 'cywater-meeting-registration' ); ?></span><input type="text" name="presentation_title" maxlength="300"></label>
					<label class="wide" data-cywater-meeting-title-field hidden><span><?php esc_html_e( 'Presentation summary or abstract', 'cywater-meeting-registration' ); ?></span><textarea name="presentation_abstract" rows="5" maxlength="3000"></textarea><small><?php esc_html_e( 'Provide a concise summary for programme review. You may update the organizing team later if the programme is revised.', 'cywater-meeting-registration' ); ?></small></label>
					<?php self::file_input( 'presentation_file', __( 'Presentation or poster file (optional; PDF, PPT, PPTX, DOC or DOCX; max 20 MB)', 'cywater-meeting-registration' ), '.pdf,.ppt,.pptx,.doc,.docx', __( 'This protected upload is available only to authorized Event staff. The submission deadline is September 28, 2026.', 'cywater-meeting-registration' ), false, true ); ?>
					<?php self::file_input( 'student_proof', __( 'Student status evidence (PDF, JPG or PNG; max 5 MB)', 'cywater-meeting-registration' ), '.pdf,.jpg,.jpeg,.png', __( 'Required only for the Student category. Files are stored outside public media and are available only to authorized Event staff.', 'cywater-meeting-registration' ), false, false, true ); ?>
					</div></fieldset>
					<fieldset><legend><?php esc_html_e( 'Travel and accommodation', 'cywater-meeting-registration' ); ?></legend><div class="cywater-meeting__grid">
					<label class="wide"><span><?php esc_html_e( 'Accommodation plan', 'cywater-meeting-registration' ); ?> *</span><select name="accommodation_plan" required><option value="hotel_qr"><?php esc_html_e( 'I plan to reserve Longshan Lake Hotel using the organizer link', 'cywater-meeting-registration' ); ?></option><option value="self_arranged"><?php esc_html_e( 'I will arrange accommodation independently', 'cywater-meeting-registration' ); ?></option><option value="undecided"><?php esc_html_e( 'Not decided yet', 'cywater-meeting-registration' ); ?></option></select></label>
					<label><span><?php esc_html_e( 'Expected arrival date', 'cywater-meeting-registration' ); ?></span><input type="date" name="arrival_date" min="2026-10-15" max="2026-10-19" value="2026-10-16"></label>
					<label><span><?php esc_html_e( 'Expected departure date', 'cywater-meeting-registration' ); ?></span><input type="date" name="departure_date" min="2026-10-17" max="2026-10-20" value="2026-10-19"></label>
					<label class="wide"><span><?php esc_html_e( 'Dietary, accessibility, or other participant notes', 'cywater-meeting-registration' ); ?></span><textarea name="participant_notes" rows="4" maxlength="1000"></textarea><small><?php esc_html_e( 'The organizing team will review these notes; lodging reservations are still completed through the hotel reservation link below.', 'cywater-meeting-registration' ); ?></small></label>
					</div></fieldset>
					<label class="cywater-meeting__consent"><input type="checkbox" name="accuracy" value="1" required> <span><?php esc_html_e( 'I confirm that the information is accurate. I understand that the website provides a fee recommendation only, payment is handled externally, and final fee eligibility is verified by the meeting organizer.', 'cywater-meeting-registration' ); ?></span></label>
					<button class="btn btn-primary" type="submit"><?php esc_html_e( 'Submit registration', 'cywater-meeting-registration' ); ?></button>
				</form>
			<?php endif; ?>
			<?php if ( $user_id ) : ?>
				<section class="cywater-meeting__handoff cywater-meeting__hotel" aria-labelledby="cywater-meeting-hotel-title"><div class="cywater-meeting__handoff-copy"><p class="cywater-meeting__eyebrow"><?php esc_html_e( 'Optional accommodation', 'cywater-meeting-registration' ); ?></p><h3 id="cywater-meeting-hotel-title"><?php esc_html_e( 'Reserve Longshan Lake Hotel', 'cywater-meeting-registration' ); ?></h3><p><?php esc_html_e( 'The organizer lists a reference rate of CNY 380 per night including breakfast.', 'cywater-meeting-registration' ); ?></p><p><?php esc_html_e( 'Scan the hotel mini-program code to reserve directly. Accommodation is booked and paid outside CYWater.', 'cywater-meeting-registration' ); ?></p></div><figure><img src="<?php echo esc_url( CYWATER_MEETING_REGISTRATION_URL . 'assets/img/annual-meeting-2026-accommodation-qr.png' ); ?>" alt="<?php esc_attr_e( 'Longshan Lake Hotel accommodation reservation code supplied by the meeting organizer', 'cywater-meeting-registration' ); ?>"><figcaption><?php esc_html_e( 'Organizer-provided hotel reservation code', 'cywater-meeting-registration' ); ?></figcaption></figure></section>
			<?php endif; ?>
			<?php if ( $existing ) : ?>
				<section class="cywater-meeting__handoff cywater-meeting__payment" aria-labelledby="cywater-meeting-payment-title"><div class="cywater-meeting__handoff-copy"><p class="cywater-meeting__eyebrow"><?php esc_html_e( 'External payment', 'cywater-meeting-registration' ); ?></p><h3 id="cywater-meeting-payment-title"><?php esc_html_e( 'Pay after registration', 'cywater-meeting-registration' ); ?></h3><p class="cywater-meeting__handoff-lead"><?php esc_html_e( 'Recipient: the Annual Meeting Organizing Committee’s designated Alipay account.', 'cywater-meeting-registration' ); ?></p><ol><li><?php esc_html_e( 'Scan the organizer-provided Alipay code.', 'cywater-meeting-registration' ); ?></li><li><?php esc_html_e( 'Add the participant’s full name to the payment note.', 'cywater-meeting-registration' ); ?></li><li><?php esc_html_e( 'Before paying, verify the recipient identity displayed by Alipay. If it does not match the organizer-designated meeting account, stop and contact contact@cywater.org.', 'cywater-meeting-registration' ); ?></li></ol><p class="cywater-meeting__handoff-note"><?php esc_html_e( 'Payment is outside CYWater and is not verified by this website. The official notice states that Nanjing University of Information Science and Technology (NUIST) will issue the invoice after the conference.', 'cywater-meeting-registration' ); ?></p></div><figure><img src="<?php echo esc_url( CYWATER_MEETING_REGISTRATION_URL . 'assets/img/annual-meeting-2026-payment-qr.png' ); ?>" alt="<?php esc_attr_e( 'Alipay payment QR code supplied by the 2026 Annual Meeting organizer', 'cywater-meeting-registration' ); ?>"><figcaption><?php esc_html_e( 'Organizer-provided external payment code', 'cywater-meeting-registration' ); ?></figcaption></figure></section>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function handle_registration() {
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$user_id  = get_current_user_id();
		if ( ! $user_id || ! self::is_enabled( $event_id ) || 'open' !== self::phase( $event_id ) || ! isset( $_POST['cywater_meeting_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_meeting_nonce'] ) ), 'cywater_meeting_register_' . $event_id ) ) {
			self::redirect( $event_id, 'invalid' );
		}
		if ( self::existing_attendee( $event_id, $user_id ) ) {
			self::redirect( $event_id, 'already' );
		}
		$fields = array();
		foreach ( array( 'first_name', 'last_name', 'institution', 'country', 'professional_title', 'phone', 'participant_category', 'participation_plan', 'presentation_title', 'accommodation_plan', 'arrival_date', 'departure_date' ) as $key ) {
			$fields[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}
		$fields['presentation_abstract'] = isset( $_POST['presentation_abstract'] ) ? sanitize_textarea_field( wp_unslash( $_POST['presentation_abstract'] ) ) : '';
		$fields['participant_notes'] = isset( $_POST['participant_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['participant_notes'] ) ) : '';
		$country_options = class_exists( 'CYWater_Membership_Countries' ) ? CYWater_Membership_Countries::options() : ( function_exists( 'pmpro_get_countries' ) ? (array) pmpro_get_countries() : array() );
		$country_code    = class_exists( 'CYWater_Membership_Countries' ) ? CYWater_Membership_Countries::canonical_code( $fields['country'] ) : ( isset( $country_options[ $fields['country'] ] ) ? $fields['country'] : '' );
		$fields['country_code'] = $country_code;
		$fields['country']      = $country_code && isset( $country_options[ $country_code ] ) ? sanitize_text_field( $country_options[ $country_code ] ) : '';
		$categories = array( 'regular', 'student', 'corporate', 'invited' );
		$plans      = array( 'attend', 'oral', 'flash', 'poster' );
		$accommodation = array( 'hotel_qr', 'self_arranged', 'undecided' );
		$dates_valid   = ( ! $fields['arrival_date'] || preg_match( '/^2026-10-(1[5-9]|20)$/', $fields['arrival_date'] ) ) && ( ! $fields['departure_date'] || preg_match( '/^2026-10-(1[7-9]|20)$/', $fields['departure_date'] ) ) && ( ! $fields['arrival_date'] || ! $fields['departure_date'] || $fields['departure_date'] >= $fields['arrival_date'] );
		if ( ! isset( $_POST['accuracy'] ) || ! $fields['first_name'] || ! $fields['last_name'] || ! $fields['institution'] || ! $fields['country_code'] || ! $fields['professional_title'] || ! $fields['phone'] || ! in_array( $fields['participant_category'], $categories, true ) || ! in_array( $fields['participation_plan'], $plans, true ) || ! in_array( $fields['accommodation_plan'], $accommodation, true ) || ! $dates_valid || ( 'attend' !== $fields['participation_plan'] && ! $fields['presentation_title'] ) ) {
			self::redirect( $event_id, 'required' );
		}
		$proof = array();
		if ( 'student' === $fields['participant_category'] ) {
			$proof = self::store_proof( 'student_proof' );
			if ( is_wp_error( $proof ) ) {
				self::redirect( $event_id, 'proof' );
			}
		}
		$presentation_file = array();
		if ( ! empty( $_FILES['presentation_file']['tmp_name'] ) ) {
			$presentation_file = self::store_presentation_file( 'presentation_file' );
			if ( is_wp_error( $presentation_file ) ) {
				self::delete_stored( $proof );
				self::redirect( $event_id, 'presentation_file' );
			}
		}
		$ticket = self::ticket( $event_id );
		if ( is_wp_error( $ticket ) ) {
			self::delete_stored( $proof ); self::delete_stored( $presentation_file ); self::redirect( $event_id, 'unavailable' );
		}
		$user       = get_userdata( $user_id );
		$membership = self::membership_snapshot( $user_id );
		$fee        = self::fee_snapshot( $event_id, $fields['participant_category'], $membership );
		try {
			$provider    = Tribe__Tickets__RSVP::get_instance();
			$attendee_id = $provider->create_attendee_for_ticket( $ticket, array( 'full_name' => trim( $fields['first_name'] . ' ' . $fields['last_name'] ), 'email' => $user->user_email, 'user_id' => $user_id, 'attendee_status' => 'going', 'optout' => true ) );
		} catch ( Throwable $exception ) {
			self::delete_stored( $proof ); self::delete_stored( $presentation_file ); self::redirect( $event_id, 'unavailable' );
		}
		if ( ! is_numeric( $attendee_id ) || $attendee_id <= 0 ) {
			self::delete_stored( $proof ); self::delete_stored( $presentation_file ); self::redirect( $event_id, 'unavailable' );
		}
		$snapshot = array_merge(
			$fields,
			array(
				'event_id'              => $event_id,
				'user_id'               => $user_id,
				'email'                 => $user->user_email,
				'membership_kind'       => $membership['kind'],
				'membership_label'      => $membership['label'],
				'membership_level_ids'  => $membership['level_ids'],
				'fee_period'             => $fee['period'],
				'recommended_fee_cny'    => $fee['cny'],
				'approximate_fee_usd'    => $fee['usd'],
				'payment_status'         => 'external_unverified',
				'submitted_at'           => current_time( 'mysql', true ),
			)
		);
		update_post_meta( $attendee_id, self::META_PREFIX . 'event_id', $event_id );
		update_post_meta( $attendee_id, self::META_PREFIX . 'user_id', $user_id );
		update_post_meta( $attendee_id, self::META_PREFIX . 'snapshot', $snapshot );
		if ( $proof ) {
			update_post_meta( $attendee_id, self::META_PREFIX . 'student_proof', $proof );
		}
		if ( $presentation_file ) {
			update_post_meta( $attendee_id, self::META_PREFIX . 'presentation_file', $presentation_file );
			$snapshot['presentation_file_name'] = $presentation_file['original'];
			update_post_meta( $attendee_id, self::META_PREFIX . 'snapshot', $snapshot );
		}
		// Persistence is the transaction boundary. Optional notification work runs
		// in a separate request so a mail transport or third-party hook can never
		// turn a completed registration into a browser-visible WordPress error.
		self::queue_confirmation_mail( $attendee_id );
		self::redirect( $event_id, 'submitted' );
	}

	private static function render_confirmation( $attendee_id ) {
		$snapshot = (array) get_post_meta( $attendee_id, self::META_PREFIX . 'snapshot', true );
		$files    = array_filter(
			array(
				'presentation' => self::attendee_file( $attendee_id, 'presentation' ),
				'student'      => self::attendee_file( $attendee_id, 'student' ),
			)
		);
		?>
		<div class="cywater-meeting__confirmation">
			<p class="cywater-meeting__eyebrow"><?php esc_html_e( 'Registration received', 'cywater-meeting-registration' ); ?></p>
			<h3><?php echo esc_html( trim( ( $snapshot['first_name'] ?? '' ) . ' ' . ( $snapshot['last_name'] ?? '' ) ) ); ?></h3>
			<dl>
				<div><dt><?php esc_html_e( 'Category', 'cywater-meeting-registration' ); ?></dt><dd><?php echo esc_html( self::participant_labels()[ $snapshot['participant_category'] ?? 'regular' ] ?? '' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Membership snapshot', 'cywater-meeting-registration' ); ?></dt><dd><?php echo esc_html( $snapshot['membership_label'] ?? '' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Presentation plan', 'cywater-meeting-registration' ); ?></dt><dd><?php echo esc_html( $snapshot['participation_plan'] ?? '' ); ?><?php if ( ! empty( $snapshot['presentation_title'] ) ) : ?><br><?php echo esc_html( $snapshot['presentation_title'] ); ?><?php endif; ?></dd></div>
				<div><dt><?php esc_html_e( 'Accommodation plan', 'cywater-meeting-registration' ); ?></dt><dd><?php echo esc_html( self::accommodation_labels()[ $snapshot['accommodation_plan'] ?? 'undecided' ] ?? '' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Recommended external payment', 'cywater-meeting-registration' ); ?></dt><dd><?php echo esc_html( sprintf( 'CNY %d (approx. USD %d)', (int) ( $snapshot['recommended_fee_cny'] ?? 0 ), (int) ( $snapshot['approximate_fee_usd'] ?? 0 ) ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Payment status', 'cywater-meeting-registration' ); ?></dt><dd><?php esc_html_e( 'External — not verified by this website', 'cywater-meeting-registration' ); ?></dd></div>
			</dl>
			<?php if ( $files ) : ?>
				<section class="cywater-meeting__submitted-files" aria-labelledby="cywater-meeting-submitted-files-title">
					<h4 id="cywater-meeting-submitted-files-title"><?php esc_html_e( 'Submitted files', 'cywater-meeting-registration' ); ?></h4>
					<p><?php esc_html_e( 'These protected links are available only to you and authorized Event staff.', 'cywater-meeting-registration' ); ?></p>
					<ul>
						<?php foreach ( $files as $kind => $file ) : ?>
							<li><span><strong><?php echo esc_html( 'presentation' === $kind ? __( 'Presentation or poster', 'cywater-meeting-registration' ) : __( 'Student status evidence', 'cywater-meeting-registration' ) ); ?></strong><small><?php echo esc_html( $file['original'] ); ?> · <?php echo esc_html( size_format( (int) ( $file['bytes'] ?? 0 ) ) ); ?></small></span><a class="btn btn-secondary" href="<?php echo esc_url( self::protected_file_url( $attendee_id, $kind ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View or download', 'cywater-meeting-registration' ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function admin_menu() {
		add_menu_page( __( 'Meeting registrations', 'cywater-meeting-registration' ), __( 'Meeting registrations', 'cywater-meeting-registration' ), self::STAFF_CAPABILITY, 'cywater-meeting-registrations', array( __CLASS__, 'admin_page' ), 'dashicons-groups', 27 );
	}

	public static function admin_page() {
		if ( ! current_user_can( self::STAFF_CAPABILITY ) ) { wp_die( esc_html__( 'You do not have permission to view meeting registrations.', 'cywater-meeting-registration' ) ); }
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : self::first_enabled_event();
		$rows     = self::attendees( $event_id );
		$export   = wp_nonce_url( admin_url( 'admin-post.php?action=cywater_meeting_export&event_id=' . $event_id ), 'cywater_meeting_export_' . $event_id );
		$package  = wp_nonce_url( admin_url( 'admin-post.php?action=cywater_meeting_export_package&event_id=' . $event_id ), 'cywater_meeting_export_package_' . $event_id );
		?><div class="wrap"><h1><?php esc_html_e( 'Meeting registrations', 'cywater-meeting-registration' ); ?></h1><p><?php esc_html_e( 'These are registration and fee-recommendation records, not payment confirmations.', 'cywater-meeting-registration' ); ?></p><form method="get"><input type="hidden" name="post_type" value="cyw_event"><input type="hidden" name="page" value="cywater-meeting-registrations"><select name="event_id"><?php foreach ( self::enabled_events() as $event ) : ?><option value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $event_id, $event->ID ); ?>><?php echo esc_html( $event->post_title ); ?></option><?php endforeach; ?></select> <button class="button"><?php esc_html_e( 'Filter', 'cywater-meeting-registration' ); ?></button> <a class="button" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Export CSV', 'cywater-meeting-registration' ); ?></a> <a class="button button-primary" href="<?php echo esc_url( $package ); ?>"><?php esc_html_e( 'Export complete package (.zip)', 'cywater-meeting-registration' ); ?></a></form><p class="description"><?php esc_html_e( 'The complete package contains the CSV, a manifest, one JSON record per participant, and protected uploads organized into participant folders.', 'cywater-meeting-registration' ); ?></p><table class="widefat striped" style="margin-top:18px"><thead><tr><th><?php esc_html_e( 'Participant', 'cywater-meeting-registration' ); ?></th><th><?php esc_html_e( 'Institution', 'cywater-meeting-registration' ); ?></th><th><?php esc_html_e( 'Participation', 'cywater-meeting-registration' ); ?></th><th><?php esc_html_e( 'Membership snapshot', 'cywater-meeting-registration' ); ?></th><th><?php esc_html_e( 'Recommended fee', 'cywater-meeting-registration' ); ?></th><th><?php esc_html_e( 'Protected files', 'cywater-meeting-registration' ); ?></th><th><?php esc_html_e( 'Submitted', 'cywater-meeting-registration' ); ?></th></tr></thead><tbody><?php if ( ! $rows ) : ?><tr><td colspan="7"><?php esc_html_e( 'No registrations yet.', 'cywater-meeting-registration' ); ?></td></tr><?php endif; foreach ( $rows as $row ) : $s = (array) get_post_meta( $row->ID, self::META_PREFIX . 'snapshot', true ); $proof = self::attendee_file( $row->ID, 'student' ); $presentation = self::attendee_file( $row->ID, 'presentation' ); ?><tr><td><strong><?php echo esc_html( trim( ( $s['first_name'] ?? '' ) . ' ' . ( $s['last_name'] ?? '' ) ) ); ?></strong><br><?php echo esc_html( $s['email'] ?? '' ); ?><br><?php echo esc_html( $s['phone'] ?? '' ); ?></td><td><?php echo esc_html( $s['institution'] ?? '' ); ?><br><?php echo esc_html( $s['country'] ?? '' ); ?></td><td><?php echo esc_html( self::participant_labels()[ $s['participant_category'] ?? 'regular' ] ?? '' ); ?><br><?php echo esc_html( $s['participation_plan'] ?? '' ); ?><?php if ( ! empty( $s['presentation_title'] ) ) : ?><br><?php echo esc_html( $s['presentation_title'] ); ?><?php endif; ?><?php if ( ! empty( $s['presentation_abstract'] ) ) : ?><br><small><?php echo esc_html( wp_trim_words( $s['presentation_abstract'], 24 ) ); ?></small><?php endif; ?><br><?php echo esc_html( self::accommodation_labels()[ $s['accommodation_plan'] ?? 'undecided' ] ?? '' ); ?><?php if ( ! empty( $s['arrival_date'] ) || ! empty( $s['departure_date'] ) ) : ?><br><?php echo esc_html( trim( ( $s['arrival_date'] ?? '' ) . ' – ' . ( $s['departure_date'] ?? '' ), ' –' ) ); ?><?php endif; ?></td><td><?php echo esc_html( $s['membership_label'] ?? '' ); ?></td><td><?php echo esc_html( sprintf( 'CNY %d / ~USD %d', (int) ( $s['recommended_fee_cny'] ?? 0 ), (int) ( $s['approximate_fee_usd'] ?? 0 ) ) ); ?><br><em><?php esc_html_e( 'External, unverified', 'cywater-meeting-registration' ); ?></em></td><td><?php if ( $proof ) : ?><a href="<?php echo esc_url( self::protected_file_url( $row->ID, 'student' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $proof['original'] ); ?></a><br><?php endif; ?><?php if ( $presentation ) : ?><a href="<?php echo esc_url( self::protected_file_url( $row->ID, 'presentation' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $presentation['original'] ); ?></a><?php endif; ?><?php if ( ! $proof && ! $presentation ) : ?>—<?php endif; ?></td><td><?php echo esc_html( $s['submitted_at'] ?? '' ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php
	}

	public static function export_csv() {
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
		if ( ! current_user_can( self::STAFF_CAPABILITY ) || ! check_admin_referer( 'cywater_meeting_export_' . $event_id ) ) { wp_die( esc_html__( 'Export denied.', 'cywater-meeting-registration' ) ); }
		nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="cywater-meeting-' . $event_id . '-registrations.csv"' );
		echo self::csv_contents( self::attendees( $event_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function export_package() {
		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
		if ( ! current_user_can( self::STAFF_CAPABILITY ) || ! check_admin_referer( 'cywater_meeting_export_package_' . $event_id ) ) { wp_die( esc_html__( 'Export denied.', 'cywater-meeting-registration' ) ); }
		$package = self::build_export_package( $event_id, self::attendees( $event_id ) );
		if ( is_wp_error( $package ) ) { wp_die( esc_html( $package->get_error_message() ) ); }
		nocache_headers(); header( 'Content-Type: application/zip' ); header( 'Content-Length: ' . filesize( $package ) ); header( 'Content-Disposition: attachment; filename="cywater-meeting-' . $event_id . '-complete-export.zip"' );
		readfile( $package ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $package );
		exit;
	}

	public static function stream_proof() {
		$attendee_id = isset( $_GET['attendee_id'] ) ? absint( $_GET['attendee_id'] ) : 0;
		$kind        = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
		$nonce       = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$is_owner    = get_current_user_id() && get_current_user_id() === absint( get_post_meta( $attendee_id, self::META_PREFIX . 'user_id', true ) );
		$is_staff    = current_user_can( self::STAFF_CAPABILITY );
		if ( ! in_array( $kind, array( 'student', 'presentation' ), true ) || ( ! $is_owner && ! $is_staff ) || ! wp_verify_nonce( $nonce, 'cywater_meeting_file_' . $attendee_id . '_' . $kind ) ) { status_header( 403 ); exit; }
		$file = self::attendee_file( $attendee_id, $kind );
		$path = trailingslashit( self::private_directory() ) . basename( (string) ( $file['stored'] ?? '' ) );
		if ( ! is_file( $path ) ) { status_header( 404 ); exit; }
		$type = sanitize_mime_type( $file['type'] ?? 'application/octet-stream' );
		$disposition = in_array( $type, array( 'application/pdf', 'image/jpeg', 'image/png' ), true ) ? 'inline' : 'attachment';
		$name = sanitize_file_name( $file['original'] ?? basename( $path ) );
		nocache_headers(); header( 'X-Content-Type-Options: nosniff' ); header( 'Content-Type: ' . $type ); header( 'Content-Length: ' . filesize( $path ) ); header( 'Content-Disposition: ' . $disposition . '; filename="' . rawurlencode( $name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) ); readfile( $path ); exit; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	}

	private static function attendee_file( $attendee_id, $kind ) {
		$meta_key = 'presentation' === $kind ? 'presentation_file' : ( 'student' === $kind ? 'student_proof' : '' );
		if ( ! $meta_key ) { return array(); }
		$file = get_post_meta( absint( $attendee_id ), self::META_PREFIX . $meta_key, true );
		if ( ! is_array( $file ) || empty( $file['stored'] ) || empty( $file['original'] ) ) { return array(); }
		$path = trailingslashit( self::private_directory() ) . basename( (string) $file['stored'] );
		return is_file( $path ) ? $file : array();
	}

	private static function protected_file_url( $attendee_id, $kind ) {
		$attendee_id = absint( $attendee_id );
		$kind        = in_array( $kind, array( 'student', 'presentation' ), true ) ? $kind : 'student';
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=cywater_meeting_proof&kind=' . $kind . '&attendee_id=' . $attendee_id ),
			'cywater_meeting_file_' . $attendee_id . '_' . $kind
		);
	}

	private static function export_columns() {
		return array( 'attendee_id', 'first_name', 'last_name', 'email', 'phone', 'institution', 'country', 'country_code', 'professional_title', 'participant_category', 'participation_plan', 'presentation_title', 'presentation_abstract', 'presentation_file_name', 'accommodation_plan', 'arrival_date', 'departure_date', 'participant_notes', 'membership_kind', 'membership_label', 'fee_period', 'recommended_fee_cny', 'approximate_fee_usd', 'payment_status', 'submitted_at' );
	}

	private static function csv_contents( $rows ) {
		$out = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		$columns = self::export_columns();
		fputcsv( $out, $columns );
		foreach ( $rows as $row ) {
			$snapshot = (array) get_post_meta( $row->ID, self::META_PREFIX . 'snapshot', true );
			$line     = array();
			foreach ( $columns as $column ) {
				$value  = 'attendee_id' === $column ? $row->ID : ( $snapshot[ $column ] ?? '' );
				$line[] = is_array( $value ) ? implode( '|', array_map( 'strval', $value ) ) : $value;
			}
			fputcsv( $out, $line );
		}
		rewind( $out );
		$contents = stream_get_contents( $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return (string) $contents;
	}

	private static function build_export_package( $event_id, $rows ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_unavailable', __( 'The server ZIP extension is unavailable. Use Export CSV and contact the site administrator.', 'cywater-meeting-registration' ) );
		}
		$temp = wp_tempnam( 'cywater-meeting-' . absint( $event_id ) . '-export.zip' );
		if ( ! $temp ) { return new WP_Error( 'export_temp_failed', __( 'The export package could not be created.', 'cywater-meeting-registration' ) ); }
		$zip = new ZipArchive();
		if ( true !== $zip->open( $temp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $temp );
			return new WP_Error( 'export_zip_failed', __( 'The export package could not be opened.', 'cywater-meeting-registration' ) );
		}
		$manifest = array(
			'format'          => 'CYWater meeting registration export',
			'format_version'  => 1,
			'event_id'        => absint( $event_id ),
			'event_title'     => get_the_title( $event_id ),
			'exported_at_utc' => gmdate( 'c' ),
			'attendee_count'  => count( $rows ),
			'payment_notice'  => 'Recommended fees are snapshots only. Payment is external and unverified by this website.',
			'attendees'       => array(),
		);
		$zip->addFromString( 'registrations.csv', self::csv_contents( $rows ) );
		$zip->addFromString( 'README.txt', "CYWater Annual Meeting registration export\n\nregistrations.csv: spreadsheet-ready registration data.\nmanifest.json: package index and file inventory.\nattendees/: one folder per registration with registration.json and protected uploads when submitted.\n\nPayment is external and is not confirmed by this package. Handle all personal information and files as confidential Event records.\n" );
		foreach ( $rows as $row ) {
			$snapshot = (array) get_post_meta( $row->ID, self::META_PREFIX . 'snapshot', true );
			$name     = trim( (string) ( $snapshot['first_name'] ?? '' ) . ' ' . (string) ( $snapshot['last_name'] ?? '' ) );
			$slug     = sanitize_title( $name );
			$folder   = 'attendees/' . sprintf( '%06d-%s', absint( $row->ID ), $slug ?: 'participant' ) . '/';
			$record   = $snapshot;
			$record['attendee_id'] = absint( $row->ID );
			$record['files']       = array();
			foreach ( array( 'presentation' => 'presentation', 'student' => 'student-evidence' ) as $kind => $subfolder ) {
				$file = self::attendee_file( $row->ID, $kind );
				if ( ! $file ) { continue; }
				$original     = sanitize_file_name( $file['original'] );
				$archive_path = $folder . $subfolder . '/' . $original;
				$disk_path    = trailingslashit( self::private_directory() ) . basename( (string) $file['stored'] );
				if ( ! $zip->addFile( $disk_path, $archive_path ) ) { continue; }
				$record['files'][ $kind ] = array( 'name' => $original, 'bytes' => (int) ( $file['bytes'] ?? filesize( $disk_path ) ), 'mime_type' => (string) ( $file['type'] ?? '' ), 'archive_path' => $archive_path );
			}
			$zip->addFromString( $folder . 'registration.json', wp_json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
			$manifest['attendees'][] = array( 'attendee_id' => absint( $row->ID ), 'name' => $name, 'folder' => $folder, 'files' => $record['files'] );
		}
		$zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		if ( ! $zip->close() || ! is_file( $temp ) || 0 === filesize( $temp ) ) {
			wp_delete_file( $temp );
			return new WP_Error( 'export_zip_close_failed', __( 'The export package could not be finalized.', 'cywater-meeting-registration' ) );
		}
		return $temp;
	}

	private static function ticket( $event_id ) {
		if ( ! class_exists( 'Tribe__Tickets__RSVP' ) || ! class_exists( 'Tribe__Tickets__Ticket_Object' ) ) { return new WP_Error( 'event_tickets_unavailable' ); }
		$provider  = Tribe__Tickets__RSVP::get_instance();
		$ticket_id = absint( get_post_meta( $event_id, self::META_PREFIX . 'ticket_id', true ) );
		if ( $ticket_id && get_post( $ticket_id ) ) { return $provider->get_ticket( $event_id, $ticket_id ); }
		if ( ! current_user_can( 'edit_post', $event_id ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) { return new WP_Error( 'ticket_missing' ); }
		$ticket              = new Tribe__Tickets__Ticket_Object();
		$ticket->name        = get_the_title( $event_id ) . ' registration record';
		$ticket->description = 'Internal free RSVP record. Payment is handled externally and is not recorded by this ticket.';
		$ticket->price       = 0;
		$ticket_id = $provider->save_ticket( $event_id, $ticket, array( 'tribe-ticket' => array( 'capacity' => self::DEFAULT_CAPACITY, 'stock' => self::DEFAULT_CAPACITY, 'not_going' => 'no' ) ) );
		if ( ! $ticket_id ) { return new WP_Error( 'ticket_create_failed' ); }
		wp_update_post( array( 'ID' => $ticket_id, 'post_status' => 'private' ) );
		update_post_meta( $event_id, self::META_PREFIX . 'ticket_id', $ticket_id );
		return $provider->get_ticket( $event_id, $ticket_id );
	}

	private static function membership_snapshot( $user_id ) {
		$mapping = (array) get_option( 'cywater_membership_level_ids', array() );
		$active  = class_exists( 'CYWater_Membership_Account_Routing' ) ? CYWater_Membership_Account_Routing::active_individual_level_ids( $user_id ) : array();
		$kind    = 'none'; $label = __( 'No active individual membership', 'cywater-meeting-registration' );
		if ( in_array( absint( $mapping['student'] ?? 0 ), $active, true ) ) { $kind = 'student'; $label = __( 'Active Student membership', 'cywater-meeting-registration' ); }
		if ( in_array( absint( $mapping['professional'] ?? 0 ), $active, true ) ) { $kind = 'professional'; $label = __( 'Active Professional membership', 'cywater-meeting-registration' ); }
		if ( in_array( absint( $mapping['lifetime'] ?? 0 ), $active, true ) ) { $kind = 'lifetime'; $label = __( 'Active Lifetime membership', 'cywater-meeting-registration' ); }
		return array( 'kind' => $kind, 'label' => $label, 'level_ids' => array_map( 'absint', $active ) );
	}

	public static function fee_snapshot( $event_id, $category, $membership ) {
		$rates  = self::rates( $event_id );
		$member = in_array( $membership['kind'] ?? 'none', array( 'student', 'professional', 'lifetime' ), true );
		$key = 'corporate' === $category ? 'corporate' : ( 'invited' === $category ? 'invited' : ( 'student' === $category ? ( $member ? 'student_member' : 'student_nonmember' ) : ( $member ? 'regular_member' : 'regular_nonmember' ) ) );
		$period = self::is_early( $event_id ) ? 'early' : 'standard';
		return array( 'rate_key' => $key, 'period' => $period, 'cny' => absint( $rates[ $key ][ $period . '_cny' ] ?? 0 ), 'usd' => absint( $rates[ $key ][ $period . '_usd' ] ?? 0 ) );
	}

	private static function default_rates() {
		return array(
			'regular_member'    => array( 'early_cny' => 1000, 'standard_cny' => 1200, 'early_usd' => 150, 'standard_usd' => 180 ),
			'regular_nonmember' => array( 'early_cny' => 1600, 'standard_cny' => 1800, 'early_usd' => 240, 'standard_usd' => 270 ),
			'student_member'    => array( 'early_cny' => 500, 'standard_cny' => 700, 'early_usd' => 75, 'standard_usd' => 105 ),
			'student_nonmember' => array( 'early_cny' => 800, 'standard_cny' => 1000, 'early_usd' => 120, 'standard_usd' => 150 ),
			'corporate'         => array( 'early_cny' => 2200, 'standard_cny' => 2600, 'early_usd' => 330, 'standard_usd' => 390 ),
			'invited'           => array( 'early_cny' => 0, 'standard_cny' => 0, 'early_usd' => 0, 'standard_usd' => 0 ),
		);
	}

	private static function rates( $event_id ) {
		$stored = get_post_meta( $event_id, self::META_PREFIX . 'rates', true );
		return is_array( $stored ) ? array_replace_recursive( self::default_rates(), $stored ) : self::default_rates();
	}

	private static function rate_labels() {
		return array( 'regular_member' => __( 'Regular member (faculty or scholar)', 'cywater-meeting-registration' ), 'regular_nonmember' => __( 'Regular non-member', 'cywater-meeting-registration' ), 'student_member' => __( 'Student member', 'cywater-meeting-registration' ), 'student_nonmember' => __( 'Student non-member', 'cywater-meeting-registration' ), 'corporate' => __( 'Corporate representative', 'cywater-meeting-registration' ), 'invited' => __( 'Invited speaker', 'cywater-meeting-registration' ) );
	}

	private static function participant_labels() {
		return array(
			'regular'   => __( 'Faculty or scholar', 'cywater-meeting-registration' ),
			'student'   => __( 'Student', 'cywater-meeting-registration' ),
			'corporate' => __( 'Corporate representative', 'cywater-meeting-registration' ),
			'invited'   => __( 'Invited speaker', 'cywater-meeting-registration' ),
		);
	}

	private static function accommodation_labels() {
		return array(
			'hotel_qr'      => __( 'Longshan Lake Hotel via organizer link', 'cywater-meeting-registration' ),
			'self_arranged' => __( 'Self-arranged accommodation', 'cywater-meeting-registration' ),
			'undecided'     => __( 'Not decided', 'cywater-meeting-registration' ),
		);
	}

	private static function profile( $user_id ) {
		$user = get_userdata( $user_id );
		return array( 'first_name' => $user ? (string) $user->first_name : '', 'last_name' => $user ? (string) $user->last_name : '', 'email' => $user ? (string) $user->user_email : '', 'institution' => (string) get_user_meta( $user_id, 'cyw_institution_name', true ), 'country' => (string) get_user_meta( $user_id, 'cyw_country', true ), 'professional_title' => (string) get_user_meta( $user_id, 'cyw_professional_title', true ), 'career_stage' => sanitize_key( (string) get_user_meta( $user_id, 'cyw_career_stage', true ) ) );
	}

	private static function input( $name, $label, $value, $required = false, $class = '' ) {
		echo '<label class="' . esc_attr( $class ) . '"><span>' . esc_html( $label ) . ( $required ? ' *' : '' ) . '</span><input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" maxlength="200" ' . ( $required ? 'required' : '' ) . '></label>';
	}

	private static function country_select( $value ) {
		$options = class_exists( 'CYWater_Membership_Countries' )
			? CYWater_Membership_Countries::options( true )
			: ( function_exists( 'pmpro_get_countries' ) ? array( '' => __( 'Select a country or region', 'cywater-meeting-registration' ) ) + (array) pmpro_get_countries() : array() );
		$selected = class_exists( 'CYWater_Membership_Countries' ) ? CYWater_Membership_Countries::canonical_code( $value ) : ( isset( $options[ $value ] ) ? $value : '' );
		?>
		<label>
			<span><?php esc_html_e( 'Country or region', 'cywater-meeting-registration' ); ?> *</span>
			<select id="cywater-meeting-country" name="country" required data-cywater-meeting-country>
				<?php foreach ( $options as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $selected, $code ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
			</select>
			<small><?php esc_html_e( 'Start typing to search the complete country and region list.', 'cywater-meeting-registration' ); ?></small>
		</label>
		<?php
	}

	private static function file_input( $name, $label, $accept, $help, $required = false, $presentation_field = false, $proof_field = false ) {
		$id = 'cywater-meeting-' . str_replace( '_', '-', $name );
		?>
		<div class="cywater-meeting__field wide"<?php echo $presentation_field ? ' data-cywater-meeting-title-field hidden' : ''; ?>>
			<span class="cywater-meeting__field-label" id="<?php echo esc_attr( $id ); ?>-title"><?php echo esc_html( $label ); ?><?php echo $required ? ' *' : ''; ?></span>
			<span class="cywater-meeting__file-control">
				<input class="cywater-meeting__file-input" type="file" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" accept="<?php echo esc_attr( $accept ); ?>" aria-labelledby="<?php echo esc_attr( $id ); ?>-title <?php echo esc_attr( $id ); ?>-button" data-cywater-meeting-file-input<?php echo $required ? ' required' : ''; ?><?php echo $proof_field ? ' data-cywater-meeting-proof' : ''; ?>>
				<label class="cywater-meeting__file-button" id="<?php echo esc_attr( $id ); ?>-button" for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Choose File', 'cywater-meeting-registration' ); ?></label>
				<span class="cywater-meeting__file-name" data-cywater-meeting-file-name="<?php echo esc_attr( $id ); ?>" aria-live="polite"><?php esc_html_e( 'No file chosen', 'cywater-meeting-registration' ); ?></span>
			</span>
			<small><?php echo esc_html( $help ); ?></small>
		</div>
		<?php
	}

	private static function send_confirmation_mail( $email, $event_id, $fee ) {
		try {
			return (bool) wp_mail(
				$email,
				__( 'CYWater Annual Meeting registration received', 'cywater-meeting-registration' ),
				sprintf( __( 'Your registration for %1$s has been received. Recommended external payment: %2$s. This email does not confirm payment. Questions: %3$s', 'cywater-meeting-registration' ), get_the_title( $event_id ), self::format_fee( $fee ), self::SUPPORT_EMAIL ),
				array( 'From: CYWater Contact <contact@cywater.org>', 'Reply-To: CYWater Contact <contact@cywater.org>' )
			);
		} catch ( Throwable $error ) {
			error_log( 'CYWater meeting registration confirmation mail failed after the registration was stored: ' . get_class( $error ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}
	}

	private static function queue_confirmation_mail( $attendee_id ) {
		$attendee_id = absint( $attendee_id );
		if ( ! $attendee_id || wp_next_scheduled( 'cywater_meeting_send_confirmation', array( $attendee_id ) ) ) {
			return;
		}
		try {
			wp_schedule_single_event( time() + 5, 'cywater_meeting_send_confirmation', array( $attendee_id ), true );
		} catch ( Throwable $error ) {
			error_log( 'CYWater meeting registration confirmation could not be queued after the registration was stored: ' . get_class( $error ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	public static function send_queued_confirmation( $attendee_id ) {
		$snapshot = (array) get_post_meta( absint( $attendee_id ), self::META_PREFIX . 'snapshot', true );
		$email    = sanitize_email( (string) ( $snapshot['email'] ?? '' ) );
		$event_id = absint( $snapshot['event_id'] ?? 0 );
		if ( ! $email || ! $event_id ) {
			return;
		}
		$fee = array(
			'cny' => absint( $snapshot['recommended_fee_cny'] ?? 0 ),
			'usd' => absint( $snapshot['approximate_fee_usd'] ?? 0 ),
		);
		self::send_confirmation_mail( $email, $event_id, $fee );
	}

	private static function store_proof( $field ) {
		if ( empty( $_FILES[ $field ]['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $_FILES[ $field ]['error'] || (int) $_FILES[ $field ]['size'] > self::MAX_PROOF_BYTES ) { return new WP_Error( 'invalid_proof' ); }
		$name = sanitize_file_name( wp_unslash( $_FILES[ $field ]['name'] ) ); $allowed = array( 'pdf' => 'application/pdf', 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png' ); $check = wp_check_filetype_and_ext( $_FILES[ $field ]['tmp_name'], $name, $allowed );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) || ! in_array( $check['type'], array_values( $allowed ), true ) ) { return new WP_Error( 'invalid_proof' ); }
		$stored = wp_generate_uuid4() . '.' . $check['ext']; $target = trailingslashit( self::private_directory() ) . $stored;
		if ( ! move_uploaded_file( $_FILES[ $field ]['tmp_name'], $target ) ) { return new WP_Error( 'proof_upload_failed' ); }
		chmod( $target, 0640 ); return array( 'stored' => $stored, 'original' => $name, 'type' => $check['type'], 'bytes' => (int) filesize( $target ) );
	}

	private static function store_presentation_file( $field ) {
		if ( empty( $_FILES[ $field ]['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $_FILES[ $field ]['error'] || (int) $_FILES[ $field ]['size'] > self::MAX_REPORT_BYTES ) { return new WP_Error( 'invalid_presentation_file' ); }
		$name = sanitize_file_name( wp_unslash( $_FILES[ $field ]['name'] ) );
		$allowed = array(
			'pdf'      => 'application/pdf',
			'ppt'      => 'application/vnd.ms-powerpoint',
			'pptx'     => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'doc'      => 'application/msword',
			'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);
		$check = wp_check_filetype_and_ext( $_FILES[ $field ]['tmp_name'], $name, $allowed );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) || ! in_array( $check['type'], array_values( $allowed ), true ) ) { return new WP_Error( 'invalid_presentation_file' ); }
		$stored = wp_generate_uuid4() . '.' . $check['ext'];
		$target = trailingslashit( self::private_directory() ) . $stored;
		if ( ! move_uploaded_file( $_FILES[ $field ]['tmp_name'], $target ) ) { return new WP_Error( 'presentation_upload_failed' ); }
		chmod( $target, 0640 );
		return array( 'stored' => $stored, 'original' => $name, 'type' => $check['type'], 'bytes' => (int) filesize( $target ) );
	}

	private static function private_directory() {
		$directory = trailingslashit( WP_CONTENT_DIR ) . 'cywater-private/meeting-registration';
		if ( ! is_dir( $directory ) ) { wp_mkdir_p( $directory ); }
		if ( ! file_exists( trailingslashit( $directory ) . '.htaccess' ) ) { file_put_contents( trailingslashit( $directory ) . '.htaccess', "Require all denied\nDeny from all\n" ); file_put_contents( trailingslashit( $directory ) . 'index.php', "<?php\n// Silence is golden.\n" ); }
		return $directory;
	}

	private static function delete_stored( $file ) { if ( is_array( $file ) && ! empty( $file['stored'] ) ) { $path = trailingslashit( self::private_directory() ) . basename( $file['stored'] ); if ( is_file( $path ) ) { wp_delete_file( $path ); } } }
	public static function delete_proof( $post_id ) { if ( self::ATTENDEE_TYPE === get_post_type( $post_id ) ) { self::delete_stored( get_post_meta( $post_id, self::META_PREFIX . 'student_proof', true ) ); self::delete_stored( get_post_meta( $post_id, self::META_PREFIX . 'presentation_file', true ) ); } }

	private static function existing_attendee( $event_id, $user_id ) {
		$ids = get_posts( array( 'post_type' => self::ATTENDEE_TYPE, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => self::META_PREFIX . 'event_id', 'value' => absint( $event_id ) ), array( 'key' => self::META_PREFIX . 'user_id', 'value' => absint( $user_id ) ) ) ) );
		return $ids ? absint( $ids[0] ) : 0;
	}

	private static function attendees( $event_id ) { return get_posts( array( 'post_type' => self::ATTENDEE_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC', 'meta_key' => self::META_PREFIX . 'event_id', 'meta_value' => absint( $event_id ) ) ); }
	private static function enabled_events() { return get_posts( array( 'post_type' => 'cyw_event', 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC', 'meta_key' => self::META_PREFIX . 'enabled', 'meta_value' => '1' ) ); }
	private static function first_enabled_event() { $events = self::enabled_events(); return $events ? absint( $events[0]->ID ) : 0; }
	private static function is_enabled( $event_id ) { return $event_id && 'cyw_event' === get_post_type( $event_id ) && '1' === (string) get_post_meta( $event_id, self::META_PREFIX . 'enabled', true ); }
	private static function local_timestamp( $value ) { if ( ! $value ) { return 0; } $date = date_create_immutable_from_format( 'Y-m-d H:i:s', $value, wp_timezone() ); return $date ? $date->getTimestamp() : 0; }
	private static function is_early( $event_id ) { $deadline = self::local_timestamp( get_post_meta( $event_id, self::META_PREFIX . 'early_deadline', true ) ); return $deadline && current_datetime()->getTimestamp() <= $deadline; }
	private static function phase( $event_id ) { $now = current_datetime()->getTimestamp(); $open = self::local_timestamp( get_post_meta( $event_id, self::META_PREFIX . 'open_at', true ) ); $close = self::local_timestamp( get_post_meta( $event_id, self::META_PREFIX . 'close_at', true ) ); if ( $open && $now < $open ) { return 'before'; } if ( $close && $now > $close ) { return 'closed'; } return 'open'; }
	private static function format_fee( $fee ) { return 0 === (int) $fee['cny'] ? __( 'Fee waived, subject to organizer verification', 'cywater-meeting-registration' ) : sprintf( __( 'CNY %1$d (approximately USD %2$d)', 'cywater-meeting-registration' ), (int) $fee['cny'], (int) $fee['usd'] ); }
	private static function message( $key ) { $messages = array( 'submitted' => __( 'Your registration was received. The recommended fee remains subject to organizer verification, and payment is external.', 'cywater-meeting-registration' ), 'already' => __( 'This account already has a registration for the meeting.', 'cywater-meeting-registration' ), 'required' => __( 'Complete every required field and provide a presentation title when applicable.', 'cywater-meeting-registration' ), 'proof' => __( 'Students must upload a valid PDF, JPG or PNG evidence file no larger than 5 MB.', 'cywater-meeting-registration' ), 'presentation_file' => __( 'The presentation file must be a PDF, PPT, PPTX, DOC or DOCX no larger than 20 MB.', 'cywater-meeting-registration' ), 'unavailable' => __( 'Registration could not be stored. No payment was attempted. Please contact contact@cywater.org.', 'cywater-meeting-registration' ), 'invalid' => __( 'The registration request was invalid or registration is not currently open.', 'cywater-meeting-registration' ) ); return $messages[ $key ] ?? __( 'The registration request could not be completed.', 'cywater-meeting-registration' ); }
	private static function redirect( $event_id, $message ) { $url = $event_id ? get_permalink( $event_id ) : home_url( '/events/' ); wp_safe_redirect( add_query_arg( 'meeting_registration', sanitize_key( $message ), $url ) . '#meeting-registration' ); exit; }
	private static function login_url( $redirect ) { return class_exists( 'CYWater_Membership_Account_Routing' ) ? CYWater_Membership_Account_Routing::login_url( $redirect ) : wp_login_url( $redirect ); }
	private static function registration_url( $redirect ) { return class_exists( 'CYWater_Membership_Account_Routing' ) ? CYWater_Membership_Account_Routing::registration_url( $redirect ) : add_query_arg( 'redirect_to', $redirect, home_url( '/member-register/' ) ); }

	public static function cli_configure_2026( $args ) {
		$event_id = absint( $args[0] ?? 0 );
		if ( ! $event_id || 'cyw_event' !== get_post_type( $event_id ) ) { WP_CLI::error( 'Pass the existing 2026 cyw_event ID.' ); }
		$content = self::annual_meeting_2026_content();
		wp_update_post( array( 'ID' => $event_id, 'post_title' => 'The 14th CYWater Annual Meeting and High-Level Forum 2026', 'post_excerpt' => 'Join CYWater in Nanjing for the 14th Annual Meeting and a high-level forum on smart prevention and control of extreme water disasters driven by the meteorology–hydrology nexus.', 'post_content' => $content ) );
		foreach ( array( '_cyw_start_date' => '2026-10-16', '_cyw_end_date' => '2026-10-19', '_cyw_date_label' => 'October 16–19, 2026', '_cyw_location' => 'Nanjing University of Information Science and Technology, Nanjing, China', '_cyw_format' => 'In person', '_cyw_attendees' => 'Registration open', '_cyw_status' => 'upcoming', self::META_PREFIX . 'enabled' => '1', self::META_PREFIX . 'open_at' => '2026-08-28 00:00:00', self::META_PREFIX . 'early_deadline' => '2026-09-20 23:59:59', self::META_PREFIX . 'close_at' => '2026-10-15 23:59:59', self::META_PREFIX . 'rates' => self::default_rates() ) as $key => $value ) { update_post_meta( $event_id, $key, $value ); }
		$ticket = self::ticket( $event_id );
		if ( is_wp_error( $ticket ) ) { WP_CLI::error( $ticket->get_error_code() ); }
		WP_CLI::success( 'Configured reusable registration on Event ' . $event_id . ' with private RSVP ticket ' . $ticket->ID . '. No payment configuration was changed.' );
	}

	private static function annual_meeting_2026_content() {
		return <<<'HTML'
<!-- wp:paragraph -->
<p>The 14th Annual Conference of the International Association of Contemporary Young Scholars in Water Sciences (CYWater) will be held together with the High-Level Forum on “Smart Prevention and Control of Extreme Water Disasters Driven by the Meteorology–Hydrology Nexus.” The meeting provides a cross-disciplinary forum for water science, meteorology, hydrology, environmental science, engineering and artificial intelligence.</p>
<!-- /wp:paragraph -->
<!-- wp:heading --><h2>Theme and programme</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>The programme will include six keynote presentations, 28 featured presentations across four parallel sessions, 20 youth flash talks, and approximately 40–50 posters. Scientific exchange will focus on the coupled mechanisms, monitoring, forecasting, early warning, emergency response and risk management of extreme water disasters.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3>Important dates</h3><!-- /wp:heading -->
<!-- wp:list --><ul><li>October 16, 2026: participant check-in</li><li>October 17–18, 2026: academic sessions</li><li>October 19, 2026: departure</li><li>September 20, 2026: early-bird registration deadline</li><li>September 28, 2026: presentation and poster submission deadline</li></ul><!-- /wp:list -->
<!-- wp:heading --><h2>Venue and organizers</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>The meeting will take place at Nanjing University of Information Science and Technology (NUIST), No. 219 Ningliu Road, Nanjing, Jiangsu, China. CYWater is the organizer. Hosts are NUIST, the Key Laboratory of Hydrometeorological Disaster Mechanism and Warning of the Ministry of Water Resources, and the State Key Laboratory of Climate System Prediction and Risk Management.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2>Presentation submission and award</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Participants may indicate an oral presentation, youth flash talk or poster in the registration form below and provide a proposed title. The organizing committee will confirm programme placement separately. Outstanding presentations will be considered for the Outstanding Presentation Award sponsored by <em>npj Hydrosphere</em> and jointly evaluated with CYWater.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2>Registration fees</h2><!-- /wp:heading -->
<!-- wp:table {"className":"cywater-meeting-fees"} --><figure class="wp-block-table cywater-meeting-fees"><table><thead><tr><th>Category</th><th>Early bird through Sep 20</th><th>Standard / on site</th></tr></thead><tbody><tr><td>Regular member (faculty or scholar)</td><td>CNY 1,000 (~USD 150)</td><td>CNY 1,200 (~USD 180)</td></tr><tr><td>Regular non-member</td><td>CNY 1,600 (~USD 240)</td><td>CNY 1,800 (~USD 270)</td></tr><tr><td>Student member</td><td>CNY 500 (~USD 75)</td><td>CNY 700 (~USD 105)</td></tr><tr><td>Student non-member</td><td>CNY 800 (~USD 120)</td><td>CNY 1,000 (~USD 150)</td></tr><tr><td>Corporate representative</td><td>CNY 2,200 (~USD 330)</td><td>CNY 2,600 (~USD 390)</td></tr></tbody></table></figure><!-- /wp:table -->
<!-- wp:paragraph --><p>Invited speakers receive a registration-fee waiver, subject to organizer verification; travel and accommodation are not included. Registration includes conference materials, coffee breaks and lunch. Students must upload valid student identification or current enrollment evidence. Member rates are based on the active CYWater membership attached to the signed-in account.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2>Accommodation</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Longshan Lake Hotel is approximately 2 km from the venue, with meeting shuttle service planned. Address: No. 7 Yaogu Avenue, Jiangbei New Area, Nanjing. Conference rate: CNY 380 per night including breakfast. Hotel contact: Mr. Yao, +86 25 5886 0888. Accommodation is booked and paid separately.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2>Contacts</h2><!-- /wp:heading -->
<!-- wp:list --><ul><li>Yang Jiao: +86 134 6664 3113</li><li>Jinchao Xu: +86 150 5180 2908</li><li>Mengxuan Li: +86 195 3643 2768</li><li>Website registration support: contact@cywater.org</li></ul><!-- /wp:list -->
<!-- wp:paragraph --><p>The detailed programme, presentation instructions and subsequent updates will be published in the second announcement and on the official meeting page.</p><!-- /wp:paragraph -->
HTML;
	}

	public static function cli_qa() {
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( 'staging.cywater.org' !== $site_host ) {
			WP_CLI::error( 'Refusing to run: this self-cleaning probe is restricted to staging.cywater.org.' );
		}

		$checks      = 0;
		$created_ids = array();
		$user_id     = 0;
		$failure     = null;
		$mail_filter = null;
		$package     = '';

		$assert = static function ( $condition, $message ) use ( &$checks ) {
			if ( ! $condition ) {
				throw new RuntimeException( $message );
			}
			++$checks;
		};

		try {
			$rates = self::default_rates();
			foreach ( array( 'regular_member', 'regular_nonmember', 'student_member', 'student_nonmember', 'corporate', 'invited' ) as $key ) {
				$assert( isset( $rates[ $key ]['early_cny'], $rates[ $key ]['standard_usd'] ), 'Rate matrix incomplete.' );
			}
			$assert( str_contains( self::annual_meeting_2026_content(), 'October 16, 2026' ) && str_contains( self::annual_meeting_2026_content(), 'September 20, 2026' ), 'Source content incomplete.' );
			$assert( str_contains( (string) file_get_contents( __FILE__ ), "'payment_status'         => 'external_unverified'" ), 'External-payment boundary failed.' );
			$assert( is_dir( self::private_directory() ), 'Protected directory unavailable.' );
			$assert( class_exists( 'Tribe__Tickets__RSVP' ) && class_exists( 'Tribe__Tickets__Ticket_Object' ), 'Event Tickets RSVP is unavailable.' );

			$marker   = 'cywater-meeting-qa-' . strtolower( wp_generate_password( 8, false, false ) );
			$event_id = wp_insert_post(
				array(
					'post_type'    => 'cyw_event',
					'post_status'  => 'draft',
					'post_title'   => 'CYWater Meeting Registration QA ' . $marker,
					'post_content' => 'Temporary self-cleaning meeting-registration record.',
				),
				true
			);
			$assert( ! is_wp_error( $event_id ) && $event_id > 0, 'Unable to create the temporary Event.' );
			$created_ids[] = (int) $event_id;
			update_post_meta( $event_id, self::META_PREFIX . 'enabled', '1' );
			update_post_meta( $event_id, self::META_PREFIX . 'early_deadline', '2099-09-20 23:59:59' );
			update_post_meta( $event_id, self::META_PREFIX . 'rates', $rates );

			$ticket = self::ticket( $event_id );
			$assert( ! is_wp_error( $ticket ) && $ticket instanceof Tribe__Tickets__Ticket_Object, 'Unable to create the private RSVP relation.' );
			$created_ids[] = (int) $ticket->ID;
			$assert( 'private' === get_post_status( $ticket->ID ), 'The internal RSVP relation must remain private.' );

			$fee = self::fee_snapshot( $event_id, 'student', array( 'kind' => 'student' ) );
			$assert( 'student_member' === $fee['rate_key'] && 500 === $fee['cny'] && 75 === $fee['usd'], 'The membership-aware early student fee is incorrect.' );

			$user_id = wp_insert_user(
				array(
					'user_login' => $marker,
					'user_email' => $marker . '@example.invalid',
					'user_pass'  => wp_generate_password( 28, true, true ),
					'first_name' => 'Meeting',
					'last_name'  => 'QA',
					'role'       => 'subscriber',
				)
			);
			$assert( ! is_wp_error( $user_id ) && $user_id > 0, 'Unable to create the temporary participant.' );

			$provider    = Tribe__Tickets__RSVP::get_instance();
			$attendee_id = $provider->create_attendee_for_ticket(
				$ticket,
				array(
					'full_name'       => 'Meeting QA',
					'email'           => $marker . '@example.invalid',
					'user_id'         => $user_id,
					'attendee_status' => 'going',
					'optout'          => true,
				)
			);
			$assert( is_numeric( $attendee_id ) && $attendee_id > 0, 'Unable to create the temporary attendee.' );
			$created_ids[] = (int) $attendee_id;
			update_post_meta( $attendee_id, self::META_PREFIX . 'event_id', $event_id );
			update_post_meta( $attendee_id, self::META_PREFIX . 'user_id', $user_id );
			update_post_meta( $attendee_id, self::META_PREFIX . 'snapshot', array( 'event_id' => $event_id, 'user_id' => $user_id, 'email' => $marker . '@example.invalid', 'first_name' => 'Meeting', 'last_name' => 'QA', 'country' => 'Hong Kong SAR, China', 'country_code' => 'HK', 'participant_category' => 'student', 'participation_plan' => 'poster', 'presentation_title' => 'Temporary QA presentation', 'presentation_abstract' => 'Temporary protected programme-review content.', 'accommodation_plan' => 'hotel_qr', 'arrival_date' => '2026-10-16', 'departure_date' => '2026-10-19', 'membership_label' => 'Active Student membership', 'payment_status' => 'external_unverified', 'recommended_fee_cny' => 500, 'approximate_fee_usd' => 75 ) );
			$qa_file_name = $marker . '.pdf';
			$qa_file_path = trailingslashit( self::private_directory() ) . $qa_file_name;
			file_put_contents( $qa_file_path, "%PDF-1.4\n% CYWater protected export QA\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			update_post_meta( $attendee_id, self::META_PREFIX . 'presentation_file', array( 'stored' => $qa_file_name, 'original' => 'meeting-qa-poster.pdf', 'type' => 'application/pdf', 'bytes' => filesize( $qa_file_path ) ) );
			$assert( $attendee_id === self::existing_attendee( $event_id, $user_id ), 'Duplicate-registration lookup did not resolve the attendee.' );
			$assert( 1 === count( self::attendees( $event_id ) ), 'The export/report query did not resolve the attendee.' );
			$assert( 'external_unverified' === get_post_meta( $attendee_id, self::META_PREFIX . 'snapshot', true )['payment_status'], 'Payment status was not kept external and unverified.' );
			wp_set_current_user( $user_id );
			$rendered = self::render_module( $event_id );
			$assert( str_contains( $rendered, 'Registration received' ) && str_contains( $rendered, 'External payment' ), 'Post-registration confirmation or external payment handoff is missing.' );
			$assert( str_contains( $rendered, 'Submitted files' ) && str_contains( $rendered, 'meeting-qa-poster.pdf' ) && str_contains( $rendered, 'View or download' ), 'The participant protected-file review is missing.' );
			$assert( str_contains( $rendered, 'Reserve Longshan Lake Hotel' ) && str_contains( $rendered, 'annual-meeting-2026-accommodation-qr.png' ), 'Accommodation handoff is missing.' );
			$previous_post    = $GLOBALS['post'] ?? null;
			$GLOBALS['post'] = get_post( $event_id );
			$assert( true === self::hide_internal_ticket_link( false ), 'The native Event Tickets participant link was not hidden.' );
			$GLOBALS['post'] = $previous_post;
			$package = self::build_export_package( $event_id, self::attendees( $event_id ) );
			$assert( ! is_wp_error( $package ) && is_file( $package ), 'The structured ZIP export could not be created.' );
			$package_zip = new ZipArchive();
			$assert( true === $package_zip->open( $package ), 'The structured ZIP export could not be reopened.' );
			$assert( false !== $package_zip->locateName( 'registrations.csv' ) && false !== $package_zip->locateName( 'manifest.json' ), 'The ZIP package index files are incomplete.' );
			$assert( false !== $package_zip->locateName( 'attendees/' . sprintf( '%06d-meeting-qa', $attendee_id ) . '/registration.json' ) && false !== $package_zip->locateName( 'attendees/' . sprintf( '%06d-meeting-qa', $attendee_id ) . '/presentation/meeting-qa-poster.pdf' ), 'The ZIP participant folder is incomplete.' );
			$package_zip->close();
			wp_delete_file( $package );
			$country_options = class_exists( 'CYWater_Membership_Countries' ) ? CYWater_Membership_Countries::options() : array();
			$assert( 'China (Chinese mainland)' === ( $country_options['CN'] ?? '' ) && 'Hong Kong SAR, China' === ( $country_options['HK'] ?? '' ) && 'Macao SAR, China' === ( $country_options['MO'] ?? '' ) && 'Taiwan, China' === ( $country_options['TW'] ?? '' ), 'Canonical China-region country choices are unavailable.' );
			self::queue_confirmation_mail( $attendee_id );
			$assert( (bool) wp_next_scheduled( 'cywater_meeting_send_confirmation', array( (int) $attendee_id ) ), 'Confirmation email was not separated into its own queued request.' );
			$mail_filter = static function () { throw new RuntimeException( 'Intentional meeting mail-transport QA failure.' ); };
			add_filter( 'pre_wp_mail', $mail_filter );
			$assert( false === self::send_confirmation_mail( $marker . '@example.invalid', $event_id, $fee ), 'A mail-transport exception escaped the notification boundary.' );
			remove_filter( 'pre_wp_mail', $mail_filter );
			$mail_filter = null;
			wp_set_current_user( 0 );
		} catch ( Throwable $error ) {
			$failure = $error;
		} finally {
			if ( is_string( $package ) && $package && is_file( $package ) ) {
				wp_delete_file( $package );
			}
			if ( $mail_filter ) {
				remove_filter( 'pre_wp_mail', $mail_filter );
			}
			foreach ( $created_ids as $created_id ) {
				$timestamp = wp_next_scheduled( 'cywater_meeting_send_confirmation', array( (int) $created_id ) );
				if ( $timestamp ) {
					wp_unschedule_event( $timestamp, 'cywater_meeting_send_confirmation', array( (int) $created_id ) );
				}
			}
			foreach ( array_reverse( array_unique( array_map( 'intval', $created_ids ) ) ) as $post_id ) {
				if ( get_post( $post_id ) ) {
					wp_delete_post( $post_id, true );
				}
			}
			if ( $user_id && get_userdata( $user_id ) ) {
				wp_delete_user( $user_id );
			}
		}

		$remaining = array_filter( $created_ids, static fn( $post_id ) => null !== get_post( $post_id ) );
		if ( $failure ) {
			WP_CLI::error( $failure->getMessage() );
		}
		if ( $remaining || ( $user_id && get_userdata( $user_id ) ) ) {
			WP_CLI::error( 'Self-cleaning probe left temporary records behind.' );
		}
		WP_CLI::success( $checks . ' meeting-registration assertions passed with complete cleanup.' );
	}
}
