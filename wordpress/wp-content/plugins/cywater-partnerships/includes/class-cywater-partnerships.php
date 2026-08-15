<?php
/**
 * Institutional partnership application workflow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Partnerships {
	const POST_TYPE      = 'cyw_partner_app';
	const META_PREFIX    = '_cyw_partner_';
	const SETUP_VERSION  = '0.1.0';
	const RATE_LIMIT_MAX = 5;

	private static $stages = array(
		'submitted'    => 'Submitted',
		'board_review' => 'Board review',
		'mou_pending'  => 'MOU pending',
		'approved'     => 'Approved to pay',
		'declined'     => 'Declined',
		'paid'         => 'Payment received',
	);

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 5 );
		add_action( 'init', array( __CLASS__, 'maybe_setup' ), 20 );
		add_shortcode( 'cywater_partner_application', array( __CLASS__, 'application_shortcode' ) );
		add_action( 'admin_post_nopriv_cywater_submit_partner_application', array( __CLASS__, 'handle_submission' ) );
		add_action( 'admin_post_cywater_submit_partner_application', array( __CLASS__, 'handle_submission' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_review' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'admin_column' ), 10, 2 );
		add_action( 'wp', array( __CLASS__, 'block_legacy_partner_checkout' ), -100 );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	public static function activate() {
		self::register_post_type();
		self::setup_page();
		update_option( 'cywater_partnerships_setup_version', self::SETUP_VERSION );
		flush_rewrite_rules();
	}

	public static function maybe_setup() {
		if ( self::SETUP_VERSION === get_option( 'cywater_partnerships_setup_version' ) ) {
			return;
		}
		self::setup_page();
		update_option( 'cywater_partnerships_setup_version', self::SETUP_VERSION );
	}

	public static function register_post_type() {
		$administrator_caps = array(
			'edit_post'              => 'manage_options',
			'read_post'              => 'manage_options',
			'delete_post'            => 'manage_options',
			'edit_posts'             => 'manage_options',
			'edit_others_posts'      => 'manage_options',
			'publish_posts'          => 'manage_options',
			'read_private_posts'     => 'manage_options',
			'delete_posts'           => 'manage_options',
			'delete_private_posts'   => 'manage_options',
			'delete_published_posts' => 'manage_options',
			'delete_others_posts'    => 'manage_options',
			'edit_private_posts'     => 'manage_options',
			'edit_published_posts'   => 'manage_options',
			'create_posts'           => 'do_not_allow',
		);
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Partner applications', 'cywater-partnerships' ),
					'singular_name' => __( 'Partner application', 'cywater-partnerships' ),
					'menu_name'     => __( 'Partner applications', 'cywater-partnerships' ),
					'edit_item'     => __( 'Review partner application', 'cywater-partnerships' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-groups',
				'supports'            => array( 'title' ),
				'capabilities'        => $administrator_caps,
				'map_meta_cap'        => false,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
			)
		);
	}

	private static function setup_page() {
		$page = get_page_by_path( 'become-a-partner' );
		if ( $page ) {
			if ( ! has_shortcode( $page->post_content, 'cywater_partner_application' ) ) {
				wp_update_post(
					array(
						'ID'           => $page->ID,
						'post_content' => rtrim( $page->post_content ) . "\n[cywater_partner_application]",
					)
				);
			}
			update_option( 'cywater_partner_guide_page_id', (int) $page->ID );
			return (int) $page->ID;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Guide to Becoming a Partner',
				'post_name'    => 'become-a-partner',
				'post_content' => '[cywater_partner_application]',
			)
		);
		if ( ! is_wp_error( $page_id ) ) {
			update_option( 'cywater_partner_guide_page_id', absint( $page_id ) );
		}
		return is_wp_error( $page_id ) ? 0 : absint( $page_id );
	}

	public static function application_shortcode() {
		if ( isset( $_GET['partner_application'], $_GET['access_key'] ) ) {
			return self::status_view(
				absint( $_GET['partner_application'] ),
				sanitize_text_field( wp_unslash( $_GET['access_key'] ) )
			);
		}

		$error = isset( $_GET['partner_error'] ) ? sanitize_key( wp_unslash( $_GET['partner_error'] ) ) : '';
		ob_start();
		?>
		<div class="partner-guide">
			<div class="partner-guide-intro">
				<p class="lead">CYWater partners are institutions and organizations that support water-science research, education, conferences, and early-career development. Partnership is separate from individual membership.</p>
			</div>
			<div class="partner-guide-steps grid grid-3">
				<article class="card"><div class="card-body"><span class="eyebrow">Step 1</span><h2 class="card-title">Submit an expression of interest</h2><p>Tell CYWater about your organization, its work, and the partnership you would like to explore.</p></div></article>
				<article class="card"><div class="card-body"><span class="eyebrow">Step 2</span><h2 class="card-title">Board and MOU review</h2><p>The Board reviews mission fit, recognition arrangements, scope, term, and the proposed memorandum of understanding.</p></div></article>
				<article class="card"><div class="card-body"><span class="eyebrow">Step 3</span><h2 class="card-title">Approved contribution</h2><p>Only after approval and MOU completion will CYWater issue the organization an authorized payment link or invoice.</p></div></article>
			</div>
			<div class="partner-guide-details">
				<h2>Partnership recognition</h2>
				<p>Approved partners may be acknowledged on the CYWater website and in relevant association materials, with the organization name, logo, and link presented under the agreed MOU. Recognition does not imply endorsement of products or services.</p>
				<div class="partner-contribution" aria-label="Current annual partnership contribution">
					<div><span class="eyebrow">Annual contribution after approval</span><p class="partner-contribution-amount"><strong>$1,000</strong><span>per year</span></p></div>
					<p>CYWater requests the contribution only after Board approval and completion of the partnership MOU.</p>
				</div>
			</div>
			<?php if ( $error ) : ?>
				<div class="pmpro_message pmpro_error" role="alert">We could not submit the application. Please review all required fields and try again.</div>
			<?php endif; ?>
			<form class="partner-application-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cywater_submit_partner_application">
				<?php wp_nonce_field( 'cywater_submit_partner_application' ); ?>
				<div class="partner-honeypot" aria-hidden="true"><label>Company name <input type="text" name="company_name" tabindex="-1" autocomplete="off"></label></div>
				<div class="section-head"><span class="eyebrow">Expression of interest</span><h2>Tell us about your organization.</h2><p>Submitting this form does not create a membership, partnership, invoice, or payment obligation.</p></div>
				<div class="partner-form-grid">
					<label>Organization name <span aria-hidden="true">*</span><input class="input" type="text" name="organization" maxlength="180" required></label>
					<label>Organization type <span aria-hidden="true">*</span><select class="select" name="organization_type" required><option value="">Select one</option><option value="university">University</option><option value="research-institute">Research institute</option><option value="industry">Industry</option><option value="professional-society">Professional society</option><option value="foundation">Foundation</option><option value="other">Other organization</option></select></label>
					<label>Organization website <input class="input" type="url" name="website" maxlength="240" placeholder="https://"></label>
					<label>Country or region <span aria-hidden="true">*</span><input class="input" type="text" name="country" maxlength="120" required></label>
					<label>Contact name <span aria-hidden="true">*</span><input class="input" type="text" name="contact_name" maxlength="160" required></label>
					<label>Contact email <span aria-hidden="true">*</span><input class="input" type="email" name="contact_email" maxlength="190" required></label>
				</div>
				<label>Partnership interests and proposed contribution <span aria-hidden="true">*</span><textarea class="textarea" name="interests" rows="6" maxlength="3000" required></textarea></label>
				<label class="partner-consent"><input type="checkbox" name="consent" value="1" required> I confirm that I am authorized to submit this inquiry and consent to CYWater using these details to review and respond to the proposed partnership.</label>
				<button class="btn btn-accent" type="submit">Submit partnership interest</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function handle_submission() {
		check_admin_referer( 'cywater_submit_partner_application' );
		if ( ! empty( $_POST['company_name'] ) || ! self::rate_limit_available() ) {
			self::redirect_with_error( 'limited' );
		}

		$data = array(
			'organization'      => sanitize_text_field( wp_unslash( $_POST['organization'] ?? '' ) ),
			'organization_type' => sanitize_key( wp_unslash( $_POST['organization_type'] ?? '' ) ),
			'website'           => esc_url_raw( wp_unslash( $_POST['website'] ?? '' ), array( 'http', 'https' ) ),
			'country'           => sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) ),
			'contact_name'      => sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) ),
			'contact_email'     => sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ),
			'interests'         => sanitize_textarea_field( wp_unslash( $_POST['interests'] ?? '' ) ),
			'consent'           => ! empty( $_POST['consent'] ),
		);
		$result = self::create_application( $data, true );
		if ( is_wp_error( $result ) ) {
			self::redirect_with_error( $result->get_error_code() );
		}
		self::increment_rate_limit();
		wp_safe_redirect( self::status_url( $result['id'], $result['token'] ) );
		exit;
	}

	public static function create_application( $data, $send_mail = true ) {
		$required = array( 'organization', 'organization_type', 'country', 'contact_name', 'contact_email', 'interests' );
		foreach ( $required as $key ) {
			if ( empty( $data[ $key ] ) ) {
				return new WP_Error( 'required', __( 'Required application information is missing.', 'cywater-partnerships' ) );
			}
		}
		if ( ! is_email( $data['contact_email'] ) || empty( $data['consent'] ) ) {
			return new WP_Error( 'invalid', __( 'The email address or consent is invalid.', 'cywater-partnerships' ) );
		}
		if ( ! array_key_exists( $data['organization_type'], self::organization_types() ) ) {
			return new WP_Error( 'invalid_type', __( 'The organization type is invalid.', 'cywater-partnerships' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => sanitize_text_field( $data['organization'] ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$fields = array( 'organization', 'organization_type', 'website', 'country', 'contact_name', 'contact_email', 'interests' );
		foreach ( $fields as $key ) {
			update_post_meta( $post_id, self::META_PREFIX . $key, $data[ $key ] ?? '' );
		}
		update_post_meta( $post_id, self::META_PREFIX . 'consent_at', current_time( 'mysql', true ) );
		update_post_meta( $post_id, self::META_PREFIX . 'stage', 'submitted' );
		$token = self::rotate_access_token( $post_id );
		if ( $send_mail ) {
			self::send_submission_mail( $post_id, $token );
		}
		return array( 'id' => (int) $post_id, 'token' => $token );
	}

	private static function status_view( $post_id, $token ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type || ! self::valid_access_token( $post_id, $token ) ) {
			return '<div class="pmpro_message pmpro_error" role="alert">This partnership application link is invalid or has been replaced. Please use the newest link sent by CYWater.</div>';
		}
		$stage       = get_post_meta( $post_id, self::META_PREFIX . 'stage', true ) ?: 'submitted';
		$payment_url = get_post_meta( $post_id, self::META_PREFIX . 'payment_url', true );
		$reference   = self::reference( $post_id );
		$messages    = array(
			'submitted'    => 'Your expression of interest has been received. No payment is due.',
			'board_review' => 'The Board is reviewing the proposed partnership. No payment is due.',
			'mou_pending'  => 'The proposed memorandum of understanding is being reviewed. No payment is due.',
			'approved'     => 'The Board and MOU review are complete. Use the authorized payment link below if one has been issued.',
			'declined'     => 'CYWater is unable to proceed with this proposed partnership at this time.',
			'paid'         => 'CYWater has recorded the approved partnership contribution as received.',
		);
		ob_start();
		?>
		<div class="partner-status card">
			<div class="card-body">
				<span class="eyebrow">Application <?php echo esc_html( $reference ); ?></span>
				<h1><?php echo esc_html( self::$stages[ $stage ] ?? self::$stages['submitted'] ); ?></h1>
				<p class="lead"><?php echo esc_html( $messages[ $stage ] ?? $messages['submitted'] ); ?></p>
				<p><strong>Organization:</strong> <?php echo esc_html( get_post_meta( $post_id, self::META_PREFIX . 'organization', true ) ); ?></p>
				<?php if ( 'approved' === $stage && $payment_url && wp_http_validate_url( $payment_url ) ) : ?>
					<p><a class="btn btn-accent" href="<?php echo esc_url( $payment_url ); ?>" rel="nofollow">Pay approved partnership contribution</a></p>
				<?php elseif ( 'approved' === $stage ) : ?>
					<p>Payment instructions are being prepared. CYWater will send a new secure link when they are available.</p>
				<?php endif; ?>
				<p class="partner-status-help">Questions about this application may be sent to <a href="mailto:contact@cywater.org">contact@cywater.org</a>.</p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function add_meta_box() {
		add_meta_box( 'cywater-partner-review', __( 'Board and MOU review', 'cywater-partnerships' ), array( __CLASS__, 'review_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public static function review_meta_box( $post ) {
		wp_nonce_field( 'cywater_partner_review', 'cywater_partner_review_nonce' );
		$stage = get_post_meta( $post->ID, self::META_PREFIX . 'stage', true ) ?: 'submitted';
		?>
		<table class="form-table" role="presentation"><tbody>
		<?php foreach ( array( 'organization', 'organization_type', 'website', 'country', 'contact_name', 'contact_email', 'interests', 'consent_at' ) as $key ) : ?>
			<tr><th><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></th><td><?php echo nl2br( esc_html( get_post_meta( $post->ID, self::META_PREFIX . $key, true ) ) ); ?></td></tr>
		<?php endforeach; ?>
		<tr><th><label for="cywater-partner-stage">Review stage</label></th><td><select id="cywater-partner-stage" name="cywater_partner_stage"><?php foreach ( self::$stages as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $stage, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
		<tr><th><label for="cywater-partner-payment-url">Approved payment URL</label></th><td><input class="regular-text" id="cywater-partner-payment-url" type="url" name="cywater_partner_payment_url" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_PREFIX . 'payment_url', true ) ); ?>"><p class="description">Use an association-controlled HTTPS Stripe invoice or payment link. It is removed unless the stage is Approved to pay or Payment received.</p></td></tr>
		<tr><th><label for="cywater-partner-notes">Internal review notes</label></th><td><textarea class="large-text" rows="6" id="cywater-partner-notes" name="cywater_partner_notes"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_PREFIX . 'notes', true ) ); ?></textarea><p class="description">Administrator-only Board/MOU notes. Do not paste credentials or unnecessary personal information.</p></td></tr>
		</tbody></table>
		<?php
	}

	public static function save_review( $post_id, $post ) {
		if ( ! isset( $_POST['cywater_partner_review_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_partner_review_nonce'] ) ), 'cywater_partner_review' ) || ! current_user_can( 'manage_options' ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$old_stage = get_post_meta( $post_id, self::META_PREFIX . 'stage', true ) ?: 'submitted';
		$old_url   = get_post_meta( $post_id, self::META_PREFIX . 'payment_url', true );
		$stage     = sanitize_key( wp_unslash( $_POST['cywater_partner_stage'] ?? 'submitted' ) );
		if ( ! isset( self::$stages[ $stage ] ) ) {
			$stage = 'submitted';
		}
		$payment_url = esc_url_raw( wp_unslash( $_POST['cywater_partner_payment_url'] ?? '' ), array( 'https' ) );
		if ( ! in_array( $stage, array( 'approved', 'paid' ), true ) ) {
			$payment_url = '';
		}
		update_post_meta( $post_id, self::META_PREFIX . 'stage', $stage );
		update_post_meta( $post_id, self::META_PREFIX . 'payment_url', $payment_url );
		update_post_meta( $post_id, self::META_PREFIX . 'notes', sanitize_textarea_field( wp_unslash( $_POST['cywater_partner_notes'] ?? '' ) ) );
		if ( $stage !== $old_stage || $payment_url !== $old_url ) {
			$token = self::rotate_access_token( $post_id );
			self::send_status_mail( $post_id, $token );
		}
	}

	public static function block_legacy_partner_checkout() {
		if ( is_admin() || ! isset( $_GET['level'] ) ) {
			return;
		}
		$checkout_page_id = absint( get_option( 'pmpro_checkout_page_id' ) );
		$checkout_path    = $checkout_page_id ? wp_parse_url( get_permalink( $checkout_page_id ), PHP_URL_PATH ) : '';
		$request_path     = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		if ( ! $checkout_path || untrailingslashit( $checkout_path ) !== untrailingslashit( (string) $request_path ) ) {
			return;
		}
		$level_id = absint( $_GET['level'] );
		$level    = function_exists( 'pmpro_getLevel' ) ? pmpro_getLevel( $level_id ) : null;
		if ( $level && 'partner' === sanitize_key( $level->name ) ) {
			wp_safe_redirect( add_query_arg( 'partner-checkout', 'review-required', self::guide_url() ) );
			exit;
		}
	}

	public static function admin_columns( $columns ) {
		return array(
			'cb'            => $columns['cb'] ?? '<input type="checkbox">',
			'title'         => __( 'Organization', 'cywater-partnerships' ),
			'partner_stage' => __( 'Review stage', 'cywater-partnerships' ),
			'partner_name'  => __( 'Contact', 'cywater-partnerships' ),
			'partner_email' => __( 'Email', 'cywater-partnerships' ),
			'date'          => __( 'Submitted', 'cywater-partnerships' ),
		);
	}

	public static function admin_column( $column, $post_id ) {
		if ( 'partner_stage' === $column ) {
			$stage = get_post_meta( $post_id, self::META_PREFIX . 'stage', true ) ?: 'submitted';
			echo esc_html( self::$stages[ $stage ] ?? $stage );
		} elseif ( 'partner_name' === $column ) {
			echo esc_html( get_post_meta( $post_id, self::META_PREFIX . 'contact_name', true ) );
		} elseif ( 'partner_email' === $column ) {
			echo esc_html( get_post_meta( $post_id, self::META_PREFIX . 'contact_email', true ) );
		}
	}

	public static function register_exporter( $exporters ) {
		$exporters['cywater-partnerships'] = array(
			'exporter_friendly_name' => __( 'CYWater partnership applications', 'cywater-partnerships' ),
			'callback'               => array( __CLASS__, 'export_personal_data' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['cywater-partnerships'] = array(
			'eraser_friendly_name' => __( 'CYWater partnership applications', 'cywater-partnerships' ),
			'callback'             => array( __CLASS__, 'erase_personal_data' ),
		);
		return $erasers;
	}

	public static function export_personal_data( $email_address, $page = 1 ) {
		if ( 1 !== absint( $page ) ) {
			return array( 'data' => array(), 'done' => true );
		}
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 100,
				'meta_key'       => self::META_PREFIX . 'contact_email',
				'meta_value'     => sanitize_email( $email_address ),
			)
		);
		$data = array();
		foreach ( $posts as $post ) {
			$item = array();
			foreach ( array( 'organization', 'organization_type', 'website', 'country', 'contact_name', 'contact_email', 'interests', 'consent_at', 'stage' ) as $key ) {
				$item[] = array( 'name' => ucwords( str_replace( '_', ' ', $key ) ), 'value' => (string) get_post_meta( $post->ID, self::META_PREFIX . $key, true ) );
			}
			$data[] = array( 'group_id' => 'cywater-partnerships', 'group_label' => __( 'CYWater partnership applications', 'cywater-partnerships' ), 'item_id' => 'cywater-partner-' . $post->ID, 'data' => $item );
		}
		return array( 'data' => $data, 'done' => true );
	}

	public static function erase_personal_data( $email_address, $page = 1 ) {
		return array(
			'items_removed'  => false,
			'items_retained' => true,
			'messages'       => array( __( 'Partnership applications, Board/MOU review records, and associated payment records require administrator review before deletion or anonymization.', 'cywater-partnerships' ) ),
			'done'           => true,
		);
	}

	private static function organization_types() {
		return array(
			'university'           => 'University',
			'research-institute'   => 'Research institute',
			'industry'             => 'Industry',
			'professional-society' => 'Professional society',
			'foundation'           => 'Foundation',
			'other'                => 'Other organization',
		);
	}

	private static function rotate_access_token( $post_id ) {
		$token = bin2hex( random_bytes( 24 ) );
		update_post_meta( $post_id, self::META_PREFIX . 'access_hash', self::token_hash( $token ) );
		return $token;
	}

	private static function valid_access_token( $post_id, $token ) {
		$stored = (string) get_post_meta( $post_id, self::META_PREFIX . 'access_hash', true );
		return $stored && $token && hash_equals( $stored, self::token_hash( $token ) );
	}

	private static function token_hash( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	private static function send_submission_mail( $post_id, $token ) {
		$email        = get_post_meta( $post_id, self::META_PREFIX . 'contact_email', true );
		$organization = get_post_meta( $post_id, self::META_PREFIX . 'organization', true );
		$reference    = self::reference( $post_id );
		$url          = self::status_url( $post_id, $token );
		$headers      = array( 'From: CYWater Partnerships <contact@cywater.org>', 'Reply-To: CYWater Contact <contact@cywater.org>' );
		wp_mail( $email, 'CYWater partnership interest received — ' . $reference, "Thank you for contacting CYWater about an institutional partnership for {$organization}.\n\nYour expression of interest has been received. It does not create a membership, partnership, invoice, or payment obligation. No payment is due while the Board and MOU review are pending.\n\nCheck the application status:\n{$url}\n", $headers );
		wp_mail( 'contact@cywater.org', 'New CYWater partner application — ' . $reference, "A new institutional partnership application was submitted by {$organization}.\n\nReview it in WordPress:\n" . admin_url( 'post.php?post=' . $post_id . '&action=edit' ), $headers );
	}

	private static function send_status_mail( $post_id, $token ) {
		$email     = get_post_meta( $post_id, self::META_PREFIX . 'contact_email', true );
		$stage     = get_post_meta( $post_id, self::META_PREFIX . 'stage', true ) ?: 'submitted';
		$reference = self::reference( $post_id );
		$url       = self::status_url( $post_id, $token );
		$headers   = array( 'From: CYWater Partnerships <contact@cywater.org>', 'Reply-To: CYWater Contact <contact@cywater.org>' );
		wp_mail( $email, 'CYWater partnership application update — ' . $reference, "The application status is now: " . ( self::$stages[ $stage ] ?? $stage ) . ".\n\nUse this newest secure link to review the status and, only if approved, any authorized payment instructions:\n{$url}\n", $headers );
	}

	private static function guide_url() {
		$page_id = absint( get_option( 'cywater_partner_guide_page_id' ) );
		return $page_id ? get_permalink( $page_id ) : home_url( '/become-a-partner/' );
	}

	private static function status_url( $post_id, $token ) {
		return add_query_arg( array( 'partner_application' => absint( $post_id ), 'access_key' => $token ), self::guide_url() );
	}

	private static function reference( $post_id ) {
		return 'CYW-P-' . str_pad( (string) absint( $post_id ), 6, '0', STR_PAD_LEFT );
	}

	private static function rate_limit_key() {
		$address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
		return 'cyw_partner_rate_' . substr( hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ), 0, 32 );
	}

	private static function rate_limit_available() {
		return absint( get_transient( self::rate_limit_key() ) ) < self::RATE_LIMIT_MAX;
	}

	private static function increment_rate_limit() {
		$key = self::rate_limit_key();
		set_transient( $key, absint( get_transient( $key ) ) + 1, HOUR_IN_SECONDS );
	}

	private static function redirect_with_error( $code ) {
		wp_safe_redirect( add_query_arg( 'partner_error', sanitize_key( $code ), self::guide_url() ) );
		exit;
	}
}
