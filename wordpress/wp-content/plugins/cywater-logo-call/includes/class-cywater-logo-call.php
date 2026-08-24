<?php
/**
 * Event-scoped submission, review and voting workflow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Logo_Call {
	const ENTRY_TYPE = 'cyw_logo_entry';
	const VOTE_META  = '_cywater_logo_vote_';
	const TERMS_VERSION = '2026-08-20-v1';
	const MAX_FILE_BYTES          = 5 * MB_IN_BYTES;
	const MAX_SUBMISSION_BYTES    = 5 * MB_IN_BYTES;
	const MAX_USER_STORAGE_BYTES  = 25 * MB_IN_BYTES;
	const MAX_EVENT_STORAGE_BYTES = 250 * MB_IN_BYTES;
	const MAX_EVENT_SUBMISSIONS   = 50;
	const MAX_TOTAL_STORAGE_BYTES = 1024 * MB_IN_BYTES;
	const STORAGE_LOCK_OPTION     = 'cywater_logo_call_upload_lock';
	const STORAGE_LOCK_TTL        = 60;

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_entry_type' ) );
		add_action( 'add_meta_boxes_cyw_event', array( __CLASS__, 'add_event_box' ) );
		add_action( 'add_meta_boxes_' . self::ENTRY_TYPE, array( __CLASS__, 'add_entry_box' ) );
		add_action( 'save_post_cyw_event', array( __CLASS__, 'save_event' ) );
		add_action( 'save_post_' . self::ENTRY_TYPE, array( __CLASS__, 'save_entry_review' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_event_module' ), 35 );
		add_action( 'cywater_event_before_content', array( __CLASS__, 'render_event_countdown' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_cywater_logo_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_cywater_logo_vote', array( __CLASS__, 'handle_vote' ) );
		add_action( 'admin_post_cywater_logo_asset', array( __CLASS__, 'stream_asset' ) );
		add_action( 'admin_post_nopriv_cywater_logo_asset', array( __CLASS__, 'stream_asset' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_entry_files' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'restore_entry_private' ), 10, 2 );
		add_filter( 'manage_' . self::ENTRY_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::ENTRY_TYPE . '_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'cywater logo-call create-review-event', array( __CLASS__, 'cli_create_review_event' ) );
			WP_CLI::add_command( 'cywater logo-call qa', array( __CLASS__, 'cli_qa' ) );
		}
	}

	public static function activate() {
		self::register_entry_type();
		self::private_directory();
		flush_rewrite_rules();
	}

	public static function register_entry_type() {
		register_post_type(
			self::ENTRY_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Logo submissions', 'cywater-logo-call' ),
					'singular_name' => __( 'Logo submission', 'cywater-logo-call' ),
					'menu_name'     => __( 'Logo submissions', 'cywater-logo-call' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'author' ),
				'capability_type'      => 'post',
				'map_meta_cap'         => true,
			)
		);
	}

	public static function add_event_box() {
		add_meta_box( 'cywater-logo-call', __( 'CYWater Logo Call', 'cywater-logo-call' ), array( __CLASS__, 'render_event_box' ), 'cyw_event', 'normal', 'default' );
	}

	public static function render_event_box( $post ) {
		wp_nonce_field( 'cywater_logo_call_event', 'cywater_logo_call_event_nonce' );
		$fields = array(
			'open_at'    => __( 'Submissions open', 'cywater-logo-call' ),
			'close_at'   => __( 'Submissions close', 'cywater-logo-call' ),
			'vote_open'  => __( 'Voting opens', 'cywater-logo-call' ),
			'vote_close' => __( 'Voting closes', 'cywater-logo-call' ),
		);
		echo '<p><label><input type="checkbox" name="cywater_logo_call_enabled" value="1" ' . checked( get_post_meta( $post->ID, '_cywater_logo_call_enabled', true ), '1', false ) . '> <strong>' . esc_html__( 'Enable the removable Logo Call module on this event', 'cywater-logo-call' ) . '</strong></label></p>';
		echo '<p>' . esc_html__( 'Accounts, memberships, Events and participation permissions remain separate. Choose the audience for each action on this Event; no membership level is changed by these settings.', 'cywater-logo-call' ) . '</p>';
		foreach ( array( 'submit' => __( 'Who may submit', 'cywater-logo-call' ), 'vote' => __( 'Who may vote', 'cywater-logo-call' ) ) as $action => $label ) {
			$policy = CYWater_Logo_Call_Eligibility::policy( $post->ID, $action );
			echo '<fieldset style="margin:1rem 0;padding:0.75rem;border:1px solid #dcdcde"><legend><strong>' . esc_html( $label ) . '</strong></legend>';
			echo '<select name="cywater_logo_call_' . esc_attr( $action ) . '_audience">';
			foreach ( CYWater_Logo_Call_Eligibility::audiences() as $key => $audience_label ) {
				echo '<option value="' . esc_attr( $key ) . '" ' . selected( $policy['audience'], $key, false ) . '>' . esc_html( $audience_label ) . '</option>';
			}
			echo '</select><div style="margin-top:.6rem">';
			foreach ( CYWater_Logo_Call_Eligibility::level_options() as $key => $level_label ) {
				echo '<label style="margin-right:1rem"><input type="checkbox" name="cywater_logo_call_' . esc_attr( $action ) . '_levels[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $policy['levels'], true ), true, false ) . '> ' . esc_html( $level_label ) . '</label>';
			}
			echo '</div><p class="description">' . esc_html__( 'Level choices apply only when “Selected active membership levels” is selected.', 'cywater-logo-call' ) . '</p></fieldset>';
		}
		$reward = (string) get_post_meta( $post->ID, '_cywater_logo_call_reward', true );
		if ( '' === $reward ) {
			$reward = self::default_reward();
		}
		echo '<p><label for="cywater_logo_call_reward"><strong>' . esc_html__( 'Selected-design reward', 'cywater-logo-call' ) . '</strong></label><br><input class="widefat" type="text" id="cywater_logo_call_reward" name="cywater_logo_call_reward" value="' . esc_attr( $reward ) . '"></p>';
		foreach ( $fields as $key => $label ) {
			$value = (string) get_post_meta( $post->ID, '_cywater_logo_call_' . $key, true );
			echo '<p><label for="cywater_logo_call_' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><input type="datetime-local" id="cywater_logo_call_' . esc_attr( $key ) . '" name="cywater_logo_call_' . esc_attr( $key ) . '" value="' . esc_attr( str_replace( ' ', 'T', $value ) ) . '"></p>';
		}
	}

	public static function save_event( $post_id ) {
		if ( ! isset( $_POST['cywater_logo_call_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_logo_call_event_nonce'] ) ), 'cywater_logo_call_event' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_cywater_logo_call_enabled', isset( $_POST['cywater_logo_call_enabled'] ) ? '1' : '0' );
		$audiences = CYWater_Logo_Call_Eligibility::audiences();
		$levels    = CYWater_Logo_Call_Eligibility::level_options();
		foreach ( array( 'submit', 'vote' ) as $action ) {
			$audience = isset( $_POST[ 'cywater_logo_call_' . $action . '_audience' ] ) ? sanitize_key( wp_unslash( $_POST[ 'cywater_logo_call_' . $action . '_audience' ] ) ) : CYWater_Logo_Call_Eligibility::AUDIENCE_REGISTERED;
			if ( ! isset( $audiences[ $audience ] ) ) {
				$audience = CYWater_Logo_Call_Eligibility::AUDIENCE_REGISTERED;
			}
			$selected_levels = isset( $_POST[ 'cywater_logo_call_' . $action . '_levels' ] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST[ 'cywater_logo_call_' . $action . '_levels' ] ) ) : array();
			$selected_levels = array_values( array_intersect( array_keys( $levels ), $selected_levels ) );
			update_post_meta( $post_id, '_cywater_logo_call_' . $action . '_audience', $audience );
			update_post_meta( $post_id, '_cywater_logo_call_' . $action . '_levels', $selected_levels );
		}
		$reward = isset( $_POST['cywater_logo_call_reward'] ) ? sanitize_text_field( wp_unslash( $_POST['cywater_logo_call_reward'] ) ) : self::default_reward();
		update_post_meta( $post_id, '_cywater_logo_call_reward', $reward ?: self::default_reward() );
		foreach ( array( 'open_at', 'close_at', 'vote_open', 'vote_close' ) as $key ) {
			$value = isset( $_POST[ 'cywater_logo_call_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'cywater_logo_call_' . $key ] ) ) : '';
			update_post_meta( $post_id, '_cywater_logo_call_' . $key, str_replace( 'T', ' ', $value ) );
		}
	}

	public static function add_entry_box() {
		add_meta_box( 'cywater-logo-review', __( 'Submission review', 'cywater-logo-call' ), array( __CLASS__, 'render_entry_box' ), self::ENTRY_TYPE, 'normal', 'high' );
	}

	public static function render_entry_box( $post ) {
		wp_nonce_field( 'cywater_logo_review', 'cywater_logo_review_nonce' );
		$status = (string) get_post_meta( $post->ID, '_cywater_logo_status', true );
		$event  = absint( get_post_meta( $post->ID, '_cywater_logo_event_id', true ) );
		$user   = get_userdata( (int) $post->post_author );
		echo '<p><strong>' . esc_html__( 'Event:', 'cywater-logo-call' ) . '</strong> ' . esc_html( get_the_title( $event ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Work number:', 'cywater-logo-call' ) . '</strong> ' . esc_html( self::work_number( $post->ID ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Registered account:', 'cywater-logo-call' ) . '</strong> ' . esc_html( $user ? $user->user_login : __( 'Unavailable', 'cywater-logo-call' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Statement:', 'cywater-logo-call' ) . '</strong><br>' . nl2br( esc_html( (string) get_post_meta( $post->ID, '_cywater_logo_statement', true ) ) ) . '</p>';
		foreach ( array( 'source' => __( 'Submitted design file', 'cywater-logo-call' ) ) as $kind => $label ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=cywater_logo_asset&entry=' . $post->ID . '&kind=' . $kind ), 'cywater_logo_asset_' . $post->ID . '_' . $kind );
			echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></p>';
		}
		echo '<p><label for="cywater_logo_status"><strong>' . esc_html__( 'Review status', 'cywater-logo-call' ) . '</strong></label><br><select id="cywater_logo_status" name="cywater_logo_status">';
		foreach ( self::statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		$reward        = (string) get_post_meta( $event, '_cywater_logo_call_reward', true );
		$reward        = $reward ?: self::default_reward();
		$reward_status = (string) get_post_meta( $post->ID, '_cywater_logo_reward_status', true );
		$reward_status = $reward_status ?: ( 'selected' === $status ? 'pending' : 'not_applicable' );
		echo '<p><strong>' . esc_html__( 'Configured reward:', 'cywater-logo-call' ) . '</strong> ' . esc_html( $reward ) . '</p>';
		echo '<p><label for="cywater_logo_reward_status"><strong>' . esc_html__( 'Reward fulfillment', 'cywater-logo-call' ) . '</strong></label><br><select id="cywater_logo_reward_status" name="cywater_logo_reward_status">';
		foreach ( self::reward_statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $reward_status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p><p>' . esc_html__( 'Shortlisted entries appear in voting. After voting, Governance confirms the finalist group from the highest-ranked eligible designs. Each confirmed finalist earns the finalist reward. The Board-selected official design then enters separate rights, final-file and selected-design reward checks.', 'cywater-logo-call' ) . '</p>';
	}

	public static function save_entry_review( $post_id ) {
		if ( ! isset( $_POST['cywater_logo_review_nonce'], $_POST['cywater_logo_status'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_logo_review_nonce'] ) ), 'cywater_logo_review' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$status = sanitize_key( wp_unslash( $_POST['cywater_logo_status'] ) );
		if ( isset( self::statuses()[ $status ] ) ) {
			update_post_meta( $post_id, '_cywater_logo_status', $status );
			$reward_status = isset( $_POST['cywater_logo_reward_status'] ) ? sanitize_key( wp_unslash( $_POST['cywater_logo_reward_status'] ) ) : '';
			if ( 'selected' !== $status ) {
				$reward_status = 'not_applicable';
			} elseif ( ! isset( self::reward_statuses()[ $reward_status ] ) || 'not_applicable' === $reward_status ) {
				$reward_status = 'pending';
			}
			update_post_meta( $post_id, '_cywater_logo_reward_status', $reward_status );
		}
	}

	public static function enqueue_assets() {
		if ( is_singular( 'cyw_event' ) && self::is_enabled( get_queried_object_id() ) ) {
			wp_enqueue_style( 'cywater-logo-call', CYWATER_LOGO_CALL_URL . 'assets/logo-call.css', array(), CYWATER_LOGO_CALL_VERSION );
			wp_enqueue_script( 'cywater-logo-call', CYWATER_LOGO_CALL_URL . 'assets/logo-call.js', array(), CYWATER_LOGO_CALL_VERSION, true );
		}
	}

	public static function append_event_module( $content ) {
		if ( ! is_singular( 'cyw_event' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$event_id = get_the_ID();
		if ( ! self::is_enabled( $event_id ) ) {
			return $content;
		}
		return $content . self::render_module( $event_id );
	}

	public static function render_event_countdown( $event_id ) {
		$event_id = absint( $event_id );
		if ( self::is_enabled( $event_id ) ) {
			echo self::render_countdown( $event_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	private static function render_countdown( $event_id ) {
		$value     = (string) get_post_meta( $event_id, '_cywater_logo_call_close_at', true );
		$deadline  = self::local_timestamp( $value );
		$remaining = $deadline ? max( 0, $deadline - time() ) : 0;
		if ( ! $deadline ) {
			return '';
		}
		$units = array(
			'days'    => array( (int) floor( $remaining / DAY_IN_SECONDS ), __( 'Days', 'cywater-logo-call' ) ),
			'hours'   => array( (int) floor( ( $remaining % DAY_IN_SECONDS ) / HOUR_IN_SECONDS ), __( 'Hours', 'cywater-logo-call' ) ),
			'minutes' => array( (int) floor( ( $remaining % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS ), __( 'Minutes', 'cywater-logo-call' ) ),
			'seconds' => array( (int) ( $remaining % MINUTE_IN_SECONDS ), __( 'Seconds', 'cywater-logo-call' ) ),
		);
		ob_start();
		?>
		<section class="cywater-logo-call__countdown<?php echo $remaining ? '' : ' is-complete'; ?>" data-cywater-logo-countdown data-deadline="<?php echo esc_attr( wp_date( DATE_ATOM, $deadline ) ); ?>" aria-labelledby="cywater-logo-countdown-title">
			<div class="cywater-logo-call__countdown-copy">
				<p class="cywater-logo-call__countdown-eyebrow"><?php esc_html_e( 'Submission deadline', 'cywater-logo-call' ); ?></p>
				<h2 id="cywater-logo-countdown-title" data-cywater-logo-countdown-title><?php echo esc_html( $remaining ? __( 'Time remaining to submit', 'cywater-logo-call' ) : __( 'Submissions are closed', 'cywater-logo-call' ) ); ?></h2>
				<p><?php echo esc_html( sprintf( __( 'Submit by %s.', 'cywater-logo-call' ), self::date_label( $value ) ) ); ?></p>
			</div>
			<div class="cywater-logo-call__countdown-units" aria-hidden="true">
				<?php foreach ( $units as $key => $unit ) : ?>
					<span class="cywater-logo-call__countdown-unit"><strong data-cywater-logo-countdown-unit="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( sprintf( '%02d', $unit[0] ) ); ?></strong><span><?php echo esc_html( $unit[1] ); ?></span></span>
				<?php endforeach; ?>
			</div>
			<p class="screen-reader-text" data-cywater-logo-countdown-status aria-live="polite"><?php echo esc_html( $remaining ? sprintf( __( '%d days remain before submissions close.', 'cywater-logo-call' ), (int) ceil( $remaining / DAY_IN_SECONDS ) ) : __( 'Submissions are closed.', 'cywater-logo-call' ) ); ?></p>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function render_module( $event_id ) {
		$phase   = self::phase( $event_id );
		$user_id = get_current_user_id();
		$close   = self::date_label( get_post_meta( $event_id, '_cywater_logo_call_close_at', true ) );
		$submitter   = CYWater_Logo_Call_Eligibility::public_label( $event_id, 'submit' );
		$voter       = CYWater_Logo_Call_Eligibility::public_label( $event_id, 'vote' );
		$reward      = (string) get_post_meta( $event_id, '_cywater_logo_call_reward', true );
		$reward      = $reward ?: self::default_reward();
		ob_start();
		?>
		<section class="cywater-logo-call" aria-labelledby="cywater-logo-call-title">
			<p class="cywater-logo-call__eyebrow"><?php esc_html_e( 'Logo design call', 'cywater-logo-call' ); ?></p>
			<h2 id="cywater-logo-call-title"><?php esc_html_e( 'Design the next CYWater logo', 'cywater-logo-call' ); ?></h2>
			<p><?php echo esc_html( sprintf( __( 'Submit one original logo design by %s.', 'cywater-logo-call' ), $close ) ); ?></p>
			<div class="cywater-logo-call__rules"><h3><?php esc_html_e( 'Rules at a glance', 'cywater-logo-call' ); ?></h3><ul>
				<li><?php echo esc_html( sprintf( __( 'One design file per %s.', 'cywater-logo-call' ), $submitter ) ); ?></li>
				<li><?php esc_html_e( 'PNG, JPEG or WebP, maximum 5 MB. Only the logo design itself is required at this stage.', 'cywater-logo-call' ); ?></li>
				<li><?php esc_html_e( 'Participation reward: Student membership through December 31, 2026, applied automatically without shortening or replacing a higher existing benefit.', 'cywater-logo-call' ); ?></li>
				<li><?php esc_html_e( 'Finalist reward: eligible designs confirmed as finalists after voting receive one year of Professional membership.', 'cywater-logo-call' ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Board-selected design reward: %s.', 'cywater-logo-call' ), $reward ) ); ?></li>
				<li><?php esc_html_e( 'Entrants retain non-winning work. Submission grants CYWater a limited license to review and display the entry for this call and voting.', 'cywater-logo-call' ); ?></li>
				<li><?php esc_html_e( 'The Board-selected entrant must complete CYWater’s winning-design rights assignment and provide production-ready scalable or high-resolution files before official use and reward fulfillment.', 'cywater-logo-call' ); ?></li>
			</ul></div>
			<p class="cywater-logo-call__schedule"><?php echo esc_html( sprintf( __( 'After submissions close, a separate voting activity will be held for %s. Its dates, ballot procedure and tie handling will be announced separately, and only eligible designs approved for voting will appear there.', 'cywater-logo-call' ), $voter ) ); ?></p>
			<?php self::render_feedback(); ?>
			<?php if ( 'submission' === $phase ) : ?>
				<?php self::render_submission_form( $event_id, $user_id ); ?>
			<?php elseif ( 'voting' === $phase ) : ?>
				<?php self::render_voting( $event_id, $user_id ); ?>
			<?php elseif ( 'before' === $phase ) : ?>
				<p class="cywater-logo-call__notice"><?php esc_html_e( 'Submissions have not opened yet.', 'cywater-logo-call' ); ?></p>
			<?php elseif ( 'review' === $phase ) : ?>
				<p class="cywater-logo-call__notice"><?php esc_html_e( 'Submissions are closed while shortlisted designs are prepared for voting.', 'cywater-logo-call' ); ?></p>
			<?php elseif ( 'results' === $phase ) : ?>
				<?php self::render_results( $event_id ); ?>
			<?php else : ?>
				<p class="cywater-logo-call__notice"><?php esc_html_e( 'This Logo Call is not currently available.', 'cywater-logo-call' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function render_feedback() {
		$status = isset( $_GET['logo_call'] ) ? sanitize_key( wp_unslash( $_GET['logo_call'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'submitted'      => __( 'Your design was received. CYWater will email you after review.', 'cywater-logo-call' ),
			'voted'          => __( 'Your vote was recorded.', 'cywater-logo-call' ),
			'already'        => __( 'This account has already used its one submission or vote for this event.', 'cywater-logo-call' ),
			'ineligible'     => __( 'This account is not eligible for this action under the Event participation settings.', 'cywater-logo-call' ),
			'closed'         => __( 'This stage is not currently open.', 'cywater-logo-call' ),
			'invalid_file'   => __( 'A required file was missing, too large or not an accepted format.', 'cywater-logo-call' ),
			'storage_limit'  => __( 'This upload would exceed the protected Logo Call storage limit. Please contact membership@cywater.org.', 'cywater-logo-call' ),
			'storage_busy'   => __( 'Another Logo Call upload is being finalized. Please try again in a moment.', 'cywater-logo-call' ),
			'error'          => __( 'The request could not be completed. Please contact membership@cywater.org.', 'cywater-logo-call' ),
		);
		if ( isset( $messages[ $status ] ) ) {
			echo '<p class="cywater-logo-call__notice" role="status">' . esc_html( $messages[ $status ] ) . '</p>';
		}
	}

	private static function render_submission_form( $event_id, $user_id ) {
		if ( ! $user_id ) {
			echo '<p class="cywater-logo-call__notice"><a href="' . esc_url( wp_login_url( get_permalink( $event_id ) ) ) . '">' . esc_html__( 'Sign in to submit a design', 'cywater-logo-call' ) . '</a></p>';
			return;
		}
		if ( ! CYWater_Logo_Call_Eligibility::has_verified_email( $user_id ) ) {
			echo '<p class="cywater-logo-call__notice"><a href="' . esc_url( home_url( '/verify-email/' ) ) . '">' . esc_html__( 'Verify your email address before submitting a design.', 'cywater-logo-call' ) . '</a></p>';
			self::render_submission_fields( $event_id, true );
			return;
		}
		if ( ! CYWater_Logo_Call_Eligibility::can_submit( $event_id, $user_id ) ) {
			echo '<p class="cywater-logo-call__notice">' . esc_html__( 'This account does not match the submission audience selected by the Event administrator.', 'cywater-logo-call' ) . '</p>';
			self::render_submission_fields( $event_id, true );
			return;
		}
		if ( self::existing_entry( $event_id, $user_id ) ) {
			echo '<p class="cywater-logo-call__notice">' . esc_html__( 'Your one design has been received for this event.', 'cywater-logo-call' ) . '</p>';
			return;
		}
		self::render_submission_fields( $event_id, false );
	}

	private static function render_submission_fields( $event_id, $disabled ) {
		$disabled_attr = $disabled ? ' disabled' : '';
		?>
		<form class="cywater-logo-call__form<?php echo $disabled ? ' is-disabled' : ''; ?>" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cywater_logo_submit"><input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>">
			<?php wp_nonce_field( 'cywater_logo_submit_' . $event_id, 'cywater_logo_nonce' ); ?>
			<h3><?php esc_html_e( 'Submission form', 'cywater-logo-call' ); ?></h3>
			<?php self::render_file_field( 'cywater_logo_source', 'cywater_logo_source', __( 'Logo design file', 'cywater-logo-call' ), '.png,.jpg,.jpeg,.webp', $disabled ); ?>
			<p><label for="cywater_logo_statement"><strong><?php esc_html_e( 'Design statement (optional, up to 1,000 characters)', 'cywater-logo-call' ); ?></strong></label><br><textarea id="cywater_logo_statement" name="cywater_logo_statement" maxlength="1000" rows="5"<?php echo $disabled_attr; ?>></textarea></p>
			<p><label for="cywater_logo_legal_name"><strong><?php esc_html_e( 'Entrant legal name', 'cywater-logo-call' ); ?></strong></label><br><input class="input" required type="text" id="cywater_logo_legal_name" name="cywater_logo_legal_name" maxlength="190" autocomplete="name"<?php echo $disabled_attr; ?>></p>
			<p><label><input required type="checkbox" name="cywater_logo_originality" value="1"<?php echo $disabled_attr; ?>> <?php esc_html_e( 'I warrant that I created this work and have authority to submit it.', 'cywater-logo-call' ); ?></label></p>
			<p><label><input required type="checkbox" name="cywater_logo_terms" value="1"<?php echo $disabled_attr; ?>> <?php esc_html_e( 'I agree to the limited review, display and voting license and, if Board-selected, to complete the winning-design rights assignment and final-file handoff before reward fulfillment.', 'cywater-logo-call' ); ?></label></p>
			<button class="button" type="submit"<?php echo $disabled_attr; ?>><?php echo esc_html( $disabled ? __( 'Submission access required', 'cywater-logo-call' ) : __( 'Submit one design', 'cywater-logo-call' ) ); ?></button>
		</form>
		<?php
	}

	private static function render_file_field( $id, $name, $title, $accept, $disabled ) {
		$title_id    = $id . '_title';
		$button_id   = $id . '_button';
		$disabled_attr = $disabled ? ' disabled' : '';
		?>
		<p class="cywater-logo-call__file-field">
			<strong class="cywater-logo-call__file-title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $title ); ?></strong>
			<span class="cywater-logo-call__file-control">
				<input required class="cywater-logo-call__file-input" type="file" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" accept="<?php echo esc_attr( $accept ); ?>" aria-labelledby="<?php echo esc_attr( $title_id . ' ' . $button_id ); ?>" data-cywater-logo-file-input<?php echo $disabled_attr; ?>>
				<label class="cywater-logo-call__file-button" id="<?php echo esc_attr( $button_id ); ?>" for="<?php echo esc_attr( $id ); ?>"<?php echo $disabled ? ' aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Choose File', 'cywater-logo-call' ); ?></label>
				<span class="cywater-logo-call__file-name" data-cywater-logo-file-name="<?php echo esc_attr( $id ); ?>" aria-live="polite"><?php esc_html_e( 'No file chosen', 'cywater-logo-call' ); ?></span>
			</span>
		</p>
		<?php
	}

	private static function render_voting( $event_id, $user_id ) {
		$entries = self::candidates( $event_id );
		if ( ! $entries ) {
			echo '<p class="cywater-logo-call__notice">' . esc_html__( 'No shortlisted designs are available yet.', 'cywater-logo-call' ) . '</p>';
			return;
		}
		$can_vote = $user_id && CYWater_Logo_Call_Eligibility::can_vote( $event_id, $user_id ) && ! get_user_meta( $user_id, self::VOTE_META . $event_id, true );
		if ( ! $user_id ) {
			echo '<p class="cywater-logo-call__notice"><a href="' . esc_url( wp_login_url( get_permalink( $event_id ) ) ) . '">' . esc_html__( 'Sign in to vote', 'cywater-logo-call' ) . '</a></p>';
		} elseif ( ! CYWater_Logo_Call_Eligibility::has_verified_email( $user_id ) ) {
			echo '<p class="cywater-logo-call__notice"><a href="' . esc_url( home_url( '/verify-email/' ) ) . '">' . esc_html__( 'Verify your email address before voting.', 'cywater-logo-call' ) . '</a></p>';
		} elseif ( ! CYWater_Logo_Call_Eligibility::can_vote( $event_id, $user_id ) ) {
			echo '<p class="cywater-logo-call__notice">' . esc_html__( 'This account does not match the voting audience selected by the Event administrator.', 'cywater-logo-call' ) . '</p>';
		} elseif ( ! $can_vote ) {
			echo '<p class="cywater-logo-call__notice">' . esc_html__( 'This account has already cast its final vote.', 'cywater-logo-call' ) . '</p>';
		}
		echo '<form class="cywater-logo-call__vote" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="cywater_logo_vote"><input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
		wp_nonce_field( 'cywater_logo_vote_' . $event_id, 'cywater_logo_vote_nonce' );
		echo '<div class="cywater-logo-call__grid">';
		foreach ( $entries as $entry ) {
			$image = admin_url( 'admin-post.php?action=cywater_logo_asset&entry=' . $entry->ID . '&kind=source' );
			echo '<label class="cywater-logo-call__candidate"><img src="' . esc_url( $image ) . '" alt="' . esc_attr( sprintf( __( 'Shortlisted logo design %s', 'cywater-logo-call' ), self::work_number( $entry->ID ) ) ) . '">';
			if ( $can_vote ) {
				echo '<span><input required type="radio" name="entry_id" value="' . esc_attr( $entry->ID ) . '"> ' . esc_html__( 'Vote for this design', 'cywater-logo-call' ) . '</span>';
			}
			echo '</label>';
		}
		echo '</div>';
		if ( $can_vote ) {
			echo '<button class="button" type="submit">' . esc_html__( 'Cast final vote', 'cywater-logo-call' ) . '</button>';
		}
		echo '</form>';
	}

	private static function render_results( $event_id ) {
		$selected = get_posts( array( 'post_type' => self::ENTRY_TYPE, 'post_status' => 'private', 'numberposts' => 1, 'meta_query' => array( array( 'key' => '_cywater_logo_event_id', 'value' => $event_id ), array( 'key' => '_cywater_logo_status', 'value' => 'selected' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		if ( ! $selected ) {
			echo '<p class="cywater-logo-call__notice">' . esc_html__( 'Voting is closed. Finalist validation and Board selection are in progress.', 'cywater-logo-call' ) . '</p>';
			return;
		}
		$image = admin_url( 'admin-post.php?action=cywater_logo_asset&entry=' . $selected[0]->ID . '&kind=source' );
		echo '<h3>' . esc_html__( 'Selected design', 'cywater-logo-call' ) . '</h3><img class="cywater-logo-call__selected" src="' . esc_url( $image ) . '" alt="' . esc_attr__( 'Selected CYWater logo design', 'cywater-logo-call' ) . '">';
	}

	public static function handle_submit() {
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$user_id  = get_current_user_id();
		if ( ! $user_id || ! isset( $_POST['cywater_logo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_logo_nonce'] ) ), 'cywater_logo_submit_' . $event_id ) ) {
			self::redirect( $event_id, 'error' );
		}
		if ( 'submission' !== self::phase( $event_id ) ) {
			self::redirect( $event_id, 'closed' );
		}
		if ( ! CYWater_Logo_Call_Eligibility::can_submit( $event_id, $user_id ) ) {
			self::redirect( $event_id, 'ineligible' );
		}
		if ( self::existing_entry( $event_id, $user_id ) ) {
			self::redirect( $event_id, 'already' );
		}
		$legal_name = isset( $_POST['cywater_logo_legal_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cywater_logo_legal_name'] ) ) : '';
		if ( empty( $_POST['cywater_logo_terms'] ) || empty( $_POST['cywater_logo_originality'] ) || '' === $legal_name ) {
			self::redirect( $event_id, 'error' );
		}
		$submission_key = '_cywater_logo_entry_' . $event_id;
		if ( ! add_user_meta( $user_id, $submission_key, 'pending', true ) ) {
			self::redirect( $event_id, 'already' );
		}
		$lock_token = self::acquire_storage_lock();
		if ( is_wp_error( $lock_token ) ) {
			delete_user_meta( $user_id, $submission_key );
			self::redirect( $event_id, 'storage_busy' );
		}
		$incoming_bytes = self::request_upload_bytes( array( 'cywater_logo_source' ) );
		$capacity       = self::storage_capacity( $user_id, $event_id, $incoming_bytes );
		if ( is_wp_error( $capacity ) ) {
			self::release_storage_lock( $lock_token );
			delete_user_meta( $user_id, $submission_key );
			self::redirect( $event_id, 'storage_limit' );
		}

		$source = self::store_upload( 'cywater_logo_source', array( 'png' => 'image/png', 'jpg|jpeg' => 'image/jpeg', 'webp' => 'image/webp' ) );
		if ( is_wp_error( $source ) ) {
			self::delete_stored( $source );
			self::release_storage_lock( $lock_token );
			delete_user_meta( $user_id, $submission_key );
			self::redirect( $event_id, 'invalid_file' );
		}

		$entry_id = wp_insert_post( array( 'post_type' => self::ENTRY_TYPE, 'post_status' => 'private', 'post_title' => sprintf( 'Logo submission — %s — %s', get_the_title( $event_id ), wp_date( 'Y-m-d H:i:s' ) ), 'post_author' => $user_id ), true );
		if ( is_wp_error( $entry_id ) ) {
			self::delete_stored( $source );
			self::release_storage_lock( $lock_token );
			delete_user_meta( $user_id, $submission_key );
			self::redirect( $event_id, 'error' );
		}
		update_user_meta( $user_id, $submission_key, (int) $entry_id );
		update_post_meta( $entry_id, '_cywater_logo_event_id', $event_id );
		update_post_meta( $entry_id, '_cywater_logo_status', 'submitted' );
		update_post_meta( $entry_id, '_cywater_logo_statement', isset( $_POST['cywater_logo_statement'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['cywater_logo_statement'] ) ), 0, 1000 ) : '' );
		update_post_meta( $entry_id, '_cywater_logo_source', $source );
		update_post_meta( $entry_id, '_cywater_logo_submitter_name', $legal_name );
		update_post_meta( $entry_id, '_cywater_logo_terms_version', self::TERMS_VERSION );
		update_post_meta( $entry_id, '_cywater_logo_terms_accepted_at', current_time( 'mysql', true ) );
		update_post_meta( $entry_id, '_cywater_logo_work_number', self::work_number( $entry_id ) );
		update_post_meta( $entry_id, '_cywater_logo_rights_status', 'not_applicable' );
		update_post_meta( $entry_id, '_cywater_logo_final_files_status', 'not_requested' );
		$participation_reward = self::grant_participation_reward( $entry_id );
		if ( is_wp_error( $participation_reward ) ) {
			update_post_meta( $entry_id, '_cywater_logo_participation_reward_status', 'manual_required' );
			update_post_meta( $entry_id, '_cywater_logo_participation_reward_note', sanitize_key( $participation_reward->get_error_code() ) );
		}
		self::release_storage_lock( $lock_token );
		$mail_headers = array(
			'From: CYWater Member Programs <membership@cywater.org>',
			'Reply-To: CYWater Membership <membership@cywater.org>',
		);
		wp_mail( wp_get_current_user()->user_email, __( 'CYWater Logo Call submission received', 'cywater-logo-call' ), sprintf( __( 'Your design %s has been received for review. This message does not indicate selection. Please contact membership@cywater.org with questions.', 'cywater-logo-call' ), self::work_number( $entry_id ) ), $mail_headers );
		wp_mail( 'membership@cywater.org', __( 'New CYWater Logo Call submission', 'cywater-logo-call' ), sprintf( __( 'A new submission is ready for review: %s', 'cywater-logo-call' ), admin_url( 'admin.php?page=cywater-logo-reviews&entry=' . $entry_id ) ), $mail_headers );
		self::redirect( $event_id, 'submitted' );
	}

	public static function handle_vote() {
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		$user_id  = get_current_user_id();
		if ( ! $user_id || ! isset( $_POST['cywater_logo_vote_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cywater_logo_vote_nonce'] ) ), 'cywater_logo_vote_' . $event_id ) ) {
			self::redirect( $event_id, 'error' );
		}
		if ( 'voting' !== self::phase( $event_id ) ) {
			self::redirect( $event_id, 'closed' );
		}
		if ( ! CYWater_Logo_Call_Eligibility::can_vote( $event_id, $user_id ) ) {
			self::redirect( $event_id, 'ineligible' );
		}
		if ( get_user_meta( $user_id, self::VOTE_META . $event_id, true ) ) {
			self::redirect( $event_id, 'already' );
		}
		$candidate = get_post( $entry_id );
		$status    = (string) get_post_meta( $entry_id, '_cywater_logo_status', true );
		if ( ! $candidate || self::ENTRY_TYPE !== $candidate->post_type || 'private' !== $candidate->post_status || $event_id !== absint( get_post_meta( $entry_id, '_cywater_logo_event_id', true ) ) || 'shortlisted' !== $status || ! self::public_asset_allowed( $candidate, 'source' ) ) {
			self::redirect( $event_id, 'error' );
		}
		if ( ! add_user_meta( $user_id, self::VOTE_META . $event_id, $entry_id, true ) ) {
			self::redirect( $event_id, 'already' );
		}
		self::redirect( $event_id, 'voted' );
	}

	public static function stream_asset() {
		$entry_id = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0;
		$kind     = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
		$entry    = get_post( $entry_id );
		if ( ! $entry || self::ENTRY_TYPE !== $entry->post_type || 'source' !== $kind ) {
			status_header( 404 ); exit;
		}
		$public = self::public_asset_allowed( $entry, $kind );
		if ( ! $public && ( ! current_user_can( 'edit_post', $entry_id ) || ! check_admin_referer( 'cywater_logo_asset_' . $entry_id . '_' . $kind ) ) ) {
			status_header( 403 ); exit;
		}
		$file = get_post_meta( $entry_id, '_cywater_logo_' . $kind, true );
		$path = trailingslashit( self::private_directory() ) . basename( (string) ( $file['stored'] ?? '' ) );
		if ( ! is_file( $path ) ) {
			status_header( 404 ); exit;
		}
		nocache_headers();
		header( 'Content-Type: ' . sanitize_text_field( $file['type'] ?? 'application/octet-stream' ) );
		header( 'Content-Length: ' . filesize( $path ) );
		$preview = ! $public && isset( $_GET['preview'] ) && '1' === sanitize_key( wp_unslash( $_GET['preview'] ) );
		header( 'Content-Disposition: ' . ( $public || $preview ? 'inline' : 'attachment' ) . '; filename="' . rawurlencode( sanitize_file_name( $file['original'] ?? basename( $path ) ) ) . '"' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	private static function public_asset_allowed( $entry, $kind ) {
		if ( ! $entry || 'source' !== $kind || self::ENTRY_TYPE !== $entry->post_type || 'private' !== $entry->post_status ) {
			return false;
		}

		$event_id = absint( get_post_meta( $entry->ID, '_cywater_logo_event_id', true ) );
		if ( ! self::is_active_event( $event_id ) ) {
			return false;
		}

		$status = (string) get_post_meta( $entry->ID, '_cywater_logo_status', true );
		$phase  = self::phase( $event_id );
		if ( 'voting' === $phase ) {
			return 'shortlisted' === $status;
		}
		if ( 'results' === $phase ) {
			return 'selected' === $status;
		}
		return false;
	}

	private static function store_upload( $field, $allowed ) {
		if ( empty( $_FILES[ $field ]['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $_FILES[ $field ]['error'] || (int) $_FILES[ $field ]['size'] > self::MAX_FILE_BYTES ) {
			return new WP_Error( 'invalid_file' );
		}
		$name  = sanitize_file_name( wp_unslash( $_FILES[ $field ]['name'] ) );
		$check = wp_check_filetype_and_ext( $_FILES[ $field ]['tmp_name'], $name, $allowed );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) || ! in_array( $check['type'], array_values( $allowed ), true ) ) {
			return new WP_Error( 'invalid_file' );
		}
		$stored = wp_generate_uuid4() . '.' . $check['ext'];
		$target = trailingslashit( self::private_directory() ) . $stored;
		if ( ! move_uploaded_file( $_FILES[ $field ]['tmp_name'], $target ) ) {
			return new WP_Error( 'upload_failed' );
		}
		chmod( $target, 0640 );
		return array( 'stored' => $stored, 'original' => $name, 'type' => $check['type'], 'bytes' => (int) filesize( $target ) );
	}

	private static function request_upload_bytes( $fields ) {
		$total = 0;
		foreach ( $fields as $field ) {
			$size = isset( $_FILES[ $field ]['size'] ) ? (int) $_FILES[ $field ]['size'] : 0;
			if ( $size <= 0 || $size > self::MAX_FILE_BYTES ) {
				return self::MAX_SUBMISSION_BYTES + 1;
			}
			$total += $size;
		}
		return $total;
	}

	/**
	 * Bound protected storage at the submission, account, Event and plugin level.
	 * Membership state is deliberately absent from this calculation.
	 *
	 * @return true|WP_Error
	 */
	private static function storage_capacity( $user_id, $event_id, $incoming_bytes ) {
		$user_id        = absint( $user_id );
		$event_id       = absint( $event_id );
		$incoming_bytes = max( 0, (int) $incoming_bytes );
		if ( ! $user_id || ! $event_id || $incoming_bytes <= 0 || $incoming_bytes > self::MAX_SUBMISSION_BYTES ) {
			return new WP_Error( 'storage_limit' );
		}

		if ( self::event_submission_count( $event_id ) >= self::MAX_EVENT_SUBMISSIONS ) {
			return new WP_Error( 'storage_limit' );
		}
		if ( self::stored_bytes( array( 'author' => $user_id ) ) + $incoming_bytes > self::MAX_USER_STORAGE_BYTES ) {
			return new WP_Error( 'storage_limit' );
		}
		if ( self::stored_bytes( array( 'event_id' => $event_id ) ) + $incoming_bytes > self::MAX_EVENT_STORAGE_BYTES ) {
			return new WP_Error( 'storage_limit' );
		}
		if ( self::stored_bytes() + $incoming_bytes > self::MAX_TOTAL_STORAGE_BYTES ) {
			return new WP_Error( 'storage_limit' );
		}

		return true;
	}

	private static function event_submission_count( $event_id ) {
		return count(
			get_posts(
				array(
					'post_type'      => self::ENTRY_TYPE,
					'post_status'    => array( 'private', 'trash' ),
					'posts_per_page' => self::MAX_EVENT_SUBMISSIONS,
					'fields'         => 'ids',
					'meta_key'       => '_cywater_logo_event_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => absint( $event_id ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			)
		);
	}

	private static function stored_bytes( $scope = array() ) {
		$query = array(
			'post_type'   => self::ENTRY_TYPE,
			'post_status' => array( 'private', 'trash' ),
			'numberposts' => -1,
			'fields'      => 'ids',
		);
		if ( ! empty( $scope['author'] ) ) {
			$query['author'] = absint( $scope['author'] );
		}
		if ( ! empty( $scope['event_id'] ) ) {
			$query['meta_key']   = '_cywater_logo_event_id'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query['meta_value'] = absint( $scope['event_id'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		$total = 0;
		foreach ( get_posts( $query ) as $entry_id ) {
			foreach ( array( 'source' ) as $kind ) {
				$file = get_post_meta( $entry_id, '_cywater_logo_' . $kind, true );
				if ( ! is_array( $file ) || empty( $file['stored'] ) ) {
					continue;
				}
				$path  = trailingslashit( self::private_directory() ) . basename( $file['stored'] );
				$total += is_file( $path ) ? (int) filesize( $path ) : max( 0, (int) ( $file['bytes'] ?? 0 ) );
			}
		}
		return $total;
	}

	/** Serialize quota checks and file persistence across concurrent accounts. */
	private static function acquire_storage_lock() {
		$token  = wp_generate_uuid4();
		$record = array(
			'token'   => $token,
			'expires' => time() + self::STORAGE_LOCK_TTL,
		);
		if ( add_option( self::STORAGE_LOCK_OPTION, $record, '', false ) ) {
			return $token;
		}

		$current = get_option( self::STORAGE_LOCK_OPTION, array() );
		if ( is_array( $current ) && ! empty( $current['expires'] ) && (int) $current['expires'] < time() && self::delete_storage_lock_record( $current ) && add_option( self::STORAGE_LOCK_OPTION, $record, '', false ) ) {
			return $token;
		}

		return new WP_Error( 'storage_busy' );
	}

	private static function release_storage_lock( $token ) {
		$current = get_option( self::STORAGE_LOCK_OPTION, array() );
		if ( ! is_array( $current ) || empty( $current['token'] ) || ! hash_equals( (string) $current['token'], (string) $token ) ) {
			return false;
		}
		return self::delete_storage_lock_record( $current );
	}

	/** Delete only the lock record that was actually observed. */
	private static function delete_storage_lock_record( $record ) {
		global $wpdb;
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				self::STORAGE_LOCK_OPTION,
				maybe_serialize( $record )
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $deleted ) {
			wp_cache_delete( self::STORAGE_LOCK_OPTION, 'options' );
		}
		return 1 === (int) $deleted;
	}

	private static function private_directory() {
		$directory = trailingslashit( WP_CONTENT_DIR ) . 'cywater-private/logo-call';
		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}
		$protection = trailingslashit( $directory ) . '.htaccess';
		if ( ! file_exists( $protection ) ) {
			file_put_contents( $protection, "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( trailingslashit( $directory ) . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $directory;
	}

	private static function delete_stored( $file ) {
		if ( is_array( $file ) && ! empty( $file['stored'] ) ) {
			$path = trailingslashit( self::private_directory() ) . basename( $file['stored'] );
			if ( is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
	}

	public static function delete_entry_files( $post_id ) {
		if ( self::ENTRY_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		self::delete_stored( get_post_meta( $post_id, '_cywater_logo_source', true ) );
	}

	/** WordPress restores trashed private posts as drafts; keep submissions private and reviewable. */
	public static function restore_entry_private( $post_id, $previous_status = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( self::ENTRY_TYPE === get_post_type( $post_id ) && 'private' !== get_post_status( $post_id ) ) {
			wp_update_post( array( 'ID' => absint( $post_id ), 'post_status' => 'private' ) );
		}
	}

	private static function phase( $event_id ) {
		if ( ! self::is_active_event( $event_id ) ) {
			return 'closed';
		}
		$now = current_time( 'timestamp' );
		$times = array();
		foreach ( array( 'open_at', 'close_at', 'vote_open', 'vote_close' ) as $key ) {
			$value = get_post_meta( $event_id, '_cywater_logo_call_' . $key, true );
			$times[ $key ] = $value ? self::local_timestamp( $value ) : 0;
		}
		if ( $times['open_at'] && $now < $times['open_at'] ) { return 'before'; }
		if ( $times['close_at'] && $now <= $times['close_at'] ) { return 'submission'; }
		if ( $times['close_at'] && ! $times['vote_open'] ) { return 'review'; }
		if ( $times['vote_open'] && $now < $times['vote_open'] ) { return 'review'; }
		if ( $times['vote_open'] && $times['vote_close'] && $now >= $times['vote_open'] && $now <= $times['vote_close'] ) { return 'voting'; }
		if ( $times['vote_close'] && $now > $times['vote_close'] ) { return 'results'; }
		return 'closed';
	}

	public static function current_phase( $event_id ) {
		return self::phase( absint( $event_id ) );
	}

	private static function is_enabled( $event_id ) {
		return $event_id && 'cyw_event' === get_post_type( $event_id ) && '1' === (string) get_post_meta( $event_id, '_cywater_logo_call_enabled', true );
	}

	private static function is_active_event( $event_id ) {
		return self::is_enabled( $event_id ) && 'publish' === get_post_status( $event_id );
	}

	private static function existing_entry( $event_id, $user_id ) {
		$ids = get_posts( array( 'post_type' => self::ENTRY_TYPE, 'post_status' => array( 'private', 'trash' ), 'author' => $user_id, 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_cywater_logo_event_id', 'meta_value' => $event_id ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		return $ids ? (int) $ids[0] : 0;
	}

	private static function candidates( $event_id ) {
		return get_posts( array( 'post_type' => self::ENTRY_TYPE, 'post_status' => 'private', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'meta_query' => array( array( 'key' => '_cywater_logo_event_id', 'value' => $event_id ), array( 'key' => '_cywater_logo_status', 'value' => 'shortlisted' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	/** @return array<int, WP_Post> */
	public static function entries( $event_id = 0 ) {
		$args = array(
			'post_type'      => self::ENTRY_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $event_id ) {
			$args['meta_key']   = '_cywater_logo_event_id'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = absint( $event_id ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
		return get_posts( $args );
	}

	/** @return array<int, WP_Post> */
	public static function finalists( $event_id ) {
		return get_posts( array( 'post_type' => self::ENTRY_TYPE, 'post_status' => 'private', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'meta_query' => array( array( 'key' => '_cywater_logo_event_id', 'value' => absint( $event_id ) ), array( 'key' => '_cywater_logo_status', 'value' => 'finalist' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	public static function work_number( $entry_id ) {
		$stored = (string) get_post_meta( $entry_id, '_cywater_logo_work_number', true );
		if ( '' !== $stored ) {
			return $stored;
		}
		$event_id = absint( get_post_meta( $entry_id, '_cywater_logo_event_id', true ) );
		$year     = (string) get_post_meta( $event_id, '_cyw_start_date', true );
		$year     = preg_match( '/^(20\d{2})/', $year, $matches ) ? $matches[1] : wp_date( 'Y', strtotime( (string) get_post_field( 'post_date_gmt', $entry_id ) ) ?: time() );
		return sprintf( 'CYW-LOGO-%s-%06d', $year, absint( $entry_id ) );
	}

	public static function vote_count( $entry_id ) {
		$event_id = absint( get_post_meta( $entry_id, '_cywater_logo_event_id', true ) );
		if ( ! $event_id ) {
			return 0;
		}
		return count( get_users( array( 'fields' => 'ids', 'meta_key' => self::VOTE_META . $event_id, 'meta_value' => absint( $entry_id ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	}

	/** Return the protected source file record without exposing a public URL. */
	public static function source_file( $entry_id ) {
		$file = get_post_meta( absint( $entry_id ), '_cywater_logo_source', true );
		return is_array( $file ) ? $file : array();
	}

	public static function protected_source_path( $entry_id ) {
		$file = self::source_file( $entry_id );
		if ( empty( $file['stored'] ) ) {
			return '';
		}
		$path = trailingslashit( self::private_directory() ) . basename( $file['stored'] );
		return is_file( $path ) ? $path : '';
	}

	private static function date_label( $value ) {
		return $value ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T', self::local_timestamp( $value ) ) : __( 'the published deadline', 'cywater-logo-call' );
	}

	private static function default_reward() {
		return __( 'Two years of CYWater Professional membership', 'cywater-logo-call' );
	}

	/**
	 * Apply the 2026 participation award without replacing an active paid term.
	 * A covered Student-or-higher member keeps their existing membership. A
	 * registered non-member receives a zero-cost Student term ending in 2026.
	 * Shorter or ambiguous existing terms are surfaced for administrator review
	 * instead of cancelling a Stripe-linked membership.
	 *
	 * @return true|WP_Error
	 */
	public static function grant_participation_reward( $entry_id ) {
		$entry = get_post( absint( $entry_id ) );
		if ( ! $entry instanceof WP_Post || self::ENTRY_TYPE !== $entry->post_type || ! function_exists( 'pmpro_changeMembershipLevel' ) || ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			return new WP_Error( 'reward_unavailable' );
		}
		$level_ids  = (array) get_option( 'cywater_membership_level_ids', array() );
		$student_id = absint( $level_ids['student'] ?? 0 );
		$individual = array_filter( array_map( 'absint', array( $level_ids['student'] ?? 0, $level_ids['professional'] ?? 0, $level_ids['lifetime'] ?? 0 ) ) );
		$end        = ( new DateTimeImmutable( '2026-12-31 23:59:59', wp_timezone() ) )->getTimestamp();
		if ( ! $student_id || ! $individual ) {
			return new WP_Error( 'reward_level_missing' );
		}

		foreach ( (array) pmpro_getMembershipLevelsForUser( (int) $entry->post_author ) as $level ) {
			if ( ! in_array( (int) $level->id, $individual, true ) ) {
				continue;
			}
			$existing_end = isset( $level->enddate ) ? (int) $level->enddate : 0;
			if ( 0 === $existing_end || $existing_end >= $end ) {
				update_post_meta( $entry->ID, '_cywater_logo_participation_reward_status', 'covered_by_existing_membership' );
				update_post_meta( $entry->ID, '_cywater_logo_participation_reward_end', '2026-12-31 23:59:59' );
				return true;
			}
			return new WP_Error( 'existing_membership_requires_review' );
		}

		$grant = self::complimentary_level( $student_id, (int) $entry->post_author, '2026-12-31 23:59:59' );
		if ( ! $grant || ! pmpro_changeMembershipLevel( $grant, (int) $entry->post_author, 'admin_changed' ) ) {
			return new WP_Error( 'reward_grant_failed' );
		}
		update_post_meta( $entry->ID, '_cywater_logo_participation_reward_status', 'granted' );
		update_post_meta( $entry->ID, '_cywater_logo_participation_reward_level', $student_id );
		update_post_meta( $entry->ID, '_cywater_logo_participation_reward_end', '2026-12-31 23:59:59' );
		return true;
	}

	/** Apply the one-year Professional finalist award when it is safe to do so. */
	public static function grant_finalist_reward( $entry_id ) {
		$entry = get_post( absint( $entry_id ) );
		if ( ! $entry instanceof WP_Post || self::ENTRY_TYPE !== $entry->post_type || ! function_exists( 'pmpro_changeMembershipLevel' ) || ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			return new WP_Error( 'reward_unavailable' );
		}
		$level_ids       = (array) get_option( 'cywater_membership_level_ids', array() );
		$professional_id = absint( $level_ids['professional'] ?? 0 );
		$lifetime_id     = absint( $level_ids['lifetime'] ?? 0 );
		$end             = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+1 year' )->setTime( 23, 59, 59 );
		if ( ! $professional_id ) {
			return new WP_Error( 'reward_level_missing' );
		}

		$active = (array) pmpro_getMembershipLevelsForUser( (int) $entry->post_author );
		foreach ( $active as $level ) {
			if ( $lifetime_id && (int) $level->id === $lifetime_id ) {
				update_post_meta( $entry->ID, '_cywater_logo_finalist_reward_status', 'covered_by_existing_membership' );
				return true;
			}
			if ( (int) $level->id === $professional_id && ( 0 === (int) $level->enddate || (int) $level->enddate >= $end->getTimestamp() ) ) {
				update_post_meta( $entry->ID, '_cywater_logo_finalist_reward_status', 'covered_by_existing_membership' );
				return true;
			}
		}
		$participation_status = (string) get_post_meta( $entry->ID, '_cywater_logo_participation_reward_status', true );
		if ( $active && 'granted' !== $participation_status ) {
			return new WP_Error( 'existing_membership_requires_review' );
		}
		$grant = self::complimentary_level( $professional_id, (int) $entry->post_author, $end->format( 'Y-m-d H:i:s' ) );
		if ( ! $grant || ! pmpro_changeMembershipLevel( $grant, (int) $entry->post_author, 'admin_changed' ) ) {
			return new WP_Error( 'reward_grant_failed' );
		}
		update_post_meta( $entry->ID, '_cywater_logo_finalist_reward_status', 'granted' );
		update_post_meta( $entry->ID, '_cywater_logo_finalist_reward_level', $professional_id );
		update_post_meta( $entry->ID, '_cywater_logo_finalist_reward_end', $end->format( 'Y-m-d H:i:s' ) );
		return true;
	}

	/**
	 * Upgrade the Board-selected entrant to a total two-year Professional award.
	 * The normal path replaces this plugin's one-year finalist grant. Existing
	 * paid terms are never cancelled or shortened; they must first be extended
	 * manually if they do not already cover the full award period.
	 *
	 * @return true|WP_Error
	 */
	public static function grant_selected_reward( $entry_id ) {
		$entry = get_post( absint( $entry_id ) );
		if ( ! $entry instanceof WP_Post || self::ENTRY_TYPE !== $entry->post_type || 'selected' !== get_post_meta( $entry->ID, '_cywater_logo_status', true ) || ! function_exists( 'pmpro_changeMembershipLevel' ) || ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			return new WP_Error( 'reward_unavailable' );
		}
		$level_ids       = (array) get_option( 'cywater_membership_level_ids', array() );
		$professional_id = absint( $level_ids['professional'] ?? 0 );
		$lifetime_id     = absint( $level_ids['lifetime'] ?? 0 );
		$end             = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+2 years' )->setTime( 23, 59, 59 );
		if ( ! $professional_id ) {
			return new WP_Error( 'reward_level_missing' );
		}

		$active = (array) pmpro_getMembershipLevelsForUser( (int) $entry->post_author );
		foreach ( $active as $level ) {
			if ( $lifetime_id && (int) $level->id === $lifetime_id ) {
				update_post_meta( $entry->ID, '_cywater_logo_selected_reward_status', 'covered_by_existing_membership' );
				update_post_meta( $entry->ID, '_cywater_logo_selected_reward_end', 'non_expiring' );
				return true;
			}
			if ( (int) $level->id === $professional_id && ( 0 === (int) $level->enddate || (int) $level->enddate >= $end->getTimestamp() ) ) {
				update_post_meta( $entry->ID, '_cywater_logo_selected_reward_status', 'covered_by_existing_membership' );
				update_post_meta( $entry->ID, '_cywater_logo_selected_reward_end', $end->format( 'Y-m-d H:i:s' ) );
				return true;
			}
		}
		if ( $active && 'granted' !== (string) get_post_meta( $entry->ID, '_cywater_logo_finalist_reward_status', true ) ) {
			return new WP_Error( 'existing_membership_requires_review' );
		}

		$grant = self::complimentary_level( $professional_id, (int) $entry->post_author, $end->format( 'Y-m-d H:i:s' ) );
		if ( ! $grant || ! pmpro_changeMembershipLevel( $grant, (int) $entry->post_author, 'admin_changed' ) ) {
			return new WP_Error( 'reward_grant_failed' );
		}
		update_post_meta( $entry->ID, '_cywater_logo_selected_reward_status', 'granted' );
		update_post_meta( $entry->ID, '_cywater_logo_selected_reward_level', $professional_id );
		update_post_meta( $entry->ID, '_cywater_logo_selected_reward_end', $end->format( 'Y-m-d H:i:s' ) );
		return true;
	}

	/** Build a zero-cost fixed-term PMPro membership payload. */
	private static function complimentary_level( $membership_id, $user_id, $enddate ) {
		return array(
			'user_id'         => absint( $user_id ),
			'membership_id'   => absint( $membership_id ),
			'code_id'         => 0,
			'initial_payment' => '0.00',
			'billing_amount'  => '0.00',
			'cycle_number'    => 0,
			'cycle_period'    => '',
			'billing_limit'   => 0,
			'trial_amount'    => '0.00',
			'trial_limit'     => 0,
			'startdate'       => current_time( 'mysql' ),
			'enddate'         => sanitize_text_field( $enddate ),
		);
	}

	private static function local_timestamp( $value ) {
		$date = date_create_immutable_from_format( 'Y-m-d H:i', (string) $value, wp_timezone() );
		return $date ? $date->getTimestamp() : 0;
	}

	private static function redirect( $event_id, $status ) {
		$url = $event_id ? get_permalink( $event_id ) : home_url( '/events/' );
		wp_safe_redirect( add_query_arg( 'logo_call', sanitize_key( $status ), $url ) . '#cywater-logo-call-title' );
		exit;
	}

	public static function statuses() {
		return array( 'submitted' => __( 'Submitted', 'cywater-logo-call' ), 'shortlisted' => __( 'Approved for voting', 'cywater-logo-call' ), 'finalist' => __( 'Voting finalist', 'cywater-logo-call' ), 'not_selected' => __( 'Not selected', 'cywater-logo-call' ), 'selected' => __( 'Board-selected official design', 'cywater-logo-call' ), 'withdrawn' => __( 'Withdrawn', 'cywater-logo-call' ) );
	}

	public static function reward_statuses() {
		return array(
			'not_applicable' => __( 'Not applicable until selected', 'cywater-logo-call' ),
			'pending'        => __( 'Pending fulfillment', 'cywater-logo-call' ),
			'fulfilled'      => __( 'Fulfilled', 'cywater-logo-call' ),
		);
	}

	public static function columns( $columns ) {
		return array( 'cb' => $columns['cb'], 'title' => __( 'Submission', 'cywater-logo-call' ), 'event' => __( 'Event', 'cywater-logo-call' ), 'member' => __( 'Account', 'cywater-logo-call' ), 'status' => __( 'Status', 'cywater-logo-call' ), 'votes' => __( 'Votes', 'cywater-logo-call' ), 'date' => $columns['date'] );
	}

	public static function column_value( $column, $post_id ) {
		if ( 'event' === $column ) { echo esc_html( get_the_title( absint( get_post_meta( $post_id, '_cywater_logo_event_id', true ) ) ) ); }
		if ( 'member' === $column ) { $user = get_userdata( (int) get_post_field( 'post_author', $post_id ) ); echo esc_html( $user ? $user->user_login : '—' ); }
		if ( 'status' === $column ) { echo esc_html( self::statuses()[ get_post_meta( $post_id, '_cywater_logo_status', true ) ] ?? '—' ); }
		if ( 'votes' === $column ) { $event = absint( get_post_meta( $post_id, '_cywater_logo_event_id', true ) ); $users = get_users( array( 'fields' => 'ids', 'meta_key' => self::VOTE_META . $event, 'meta_value' => $post_id ) ); echo esc_html( count( $users ) ); } // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	}

	public static function cli_create_review_event( $args, $assoc_args ) {
		$existing = get_page_by_path( 'logo-design-call-2026', OBJECT, 'cyw_event' );
		$status = isset( $assoc_args['publish'] ) ? 'publish' : 'draft';
		if ( $existing ) {
			$id     = (int) $existing->ID;
			$status = (string) $existing->post_status;
		} else {
			$id = wp_insert_post( array( 'post_type' => 'cyw_event', 'post_status' => $status, 'post_name' => 'logo-design-call-2026', 'post_title' => 'CYWater Logo Design Call 2026', 'post_content' => '<p>CYWater invites registered users to propose one original association logo. Submissions close September 30, 2026; a separate voting activity follows, and the finalist group advances to Board selection.</p>' ), true );
			if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
		}
		$dates = array( 'open_at' => '2026-08-12 00:00', 'close_at' => '2026-09-30 23:59', 'vote_open' => '', 'vote_close' => '' );
		update_post_meta( $id, '_cywater_logo_call_enabled', '1' );
		if ( ! term_exists( 'member-program', 'cyw_event_type' ) ) { wp_insert_term( 'Member program', 'cyw_event_type', array( 'slug' => 'member-program' ) ); }
		wp_set_object_terms( $id, 'member-program', 'cyw_event_type' );
		foreach ( $dates as $key => $value ) { update_post_meta( $id, '_cywater_logo_call_' . $key, $value ); }
		foreach ( array( 'submit', 'vote' ) as $action ) {
			update_post_meta( $id, '_cywater_logo_call_' . $action . '_audience', CYWater_Logo_Call_Eligibility::AUDIENCE_REGISTERED );
			update_post_meta( $id, '_cywater_logo_call_' . $action . '_levels', array() );
		}
		update_post_meta( $id, '_cywater_logo_call_reward', self::default_reward() );
		update_post_meta( $id, '_cyw_start_date', '2026-08-12' ); update_post_meta( $id, '_cyw_end_date', '2026-09-30' ); update_post_meta( $id, '_cyw_date_label', 'Aug 12–Sep 30, 2026' ); update_post_meta( $id, '_cyw_location', 'Online' ); update_post_meta( $id, '_cyw_format', 'Logo design call' ); update_post_meta( $id, '_cyw_status', 'upcoming' );
		WP_CLI::success( 'Configured ' . $status . ' review event: ' . $id );
	}

	/**
	 * Self-cleaning staging acceptance for event policies and workflow state.
	 */
	public static function cli_qa() {
		if ( ! function_exists( 'pmpro_changeMembershipLevel' ) ) {
			WP_CLI::error( 'PMPro is required.' );
		}
		$level_ids = (array) get_option( 'cywater_membership_level_ids', array() );
		$required  = array( 'student', 'professional', 'lifetime' );
		foreach ( $required as $key ) {
			if ( empty( $level_ids[ $key ] ) ) {
				WP_CLI::error( 'Missing membership level: ' . $key );
			}
		}

		$users = array();
		$posts = array();
		$checks = 0;
		$qa_storage_lock = '';
		try {
			$registered_id = wp_create_user( 'cyw_logo_qa_registered_' . wp_generate_password( 6, false ), wp_generate_password( 24 ), 'cyw-logo-qa-registered-' . wp_generate_password( 6, false ) . '@example.invalid' );
			if ( is_wp_error( $registered_id ) ) { throw new RuntimeException( $registered_id->get_error_message() ); }
			$users['registered'] = (int) $registered_id;
			foreach ( $required as $key ) {
				$user_id = wp_create_user( 'cyw_logo_qa_' . $key . '_' . wp_generate_password( 6, false ), wp_generate_password( 24 ), 'cyw-logo-qa-' . $key . '-' . wp_generate_password( 6, false ) . '@example.invalid' );
				if ( is_wp_error( $user_id ) ) { throw new RuntimeException( $user_id->get_error_message() ); }
				$users[ $key ] = (int) $user_id;
				if ( ! pmpro_changeMembershipLevel( (int) $level_ids[ $key ], $user_id ) ) { throw new RuntimeException( 'Could not assign ' . $key ); }
			}

			$event_id = wp_insert_post( array( 'post_type' => 'cyw_event', 'post_status' => 'draft', 'post_title' => 'CYWater Logo Call QA ' . wp_generate_password( 6, false ) ), true );
			if ( is_wp_error( $event_id ) ) { throw new RuntimeException( $event_id->get_error_message() ); }
			$posts[] = (int) $event_id;
			update_post_meta( $event_id, '_cywater_logo_call_enabled', '1' );
			update_post_meta( $event_id, '_cywater_logo_call_submit_audience', CYWater_Logo_Call_Eligibility::AUDIENCE_REGISTERED );
			update_post_meta( $event_id, '_cywater_logo_call_vote_audience', CYWater_Logo_Call_Eligibility::AUDIENCE_REGISTERED );
			self::qa_assert( ! CYWater_Logo_Call_Eligibility::can_submit( $event_id, $users['registered'] ) && ! CYWater_Logo_Call_Eligibility::can_vote( $event_id, $users['registered'] ), 'Unverified registered account cannot submit or vote' ); ++$checks;
			foreach ( $users as $user_id ) {
				$user = get_userdata( $user_id );
				update_user_meta( $user_id, 'cyw_verified_email', strtolower( $user->user_email ) );
			}
			self::qa_assert( CYWater_Logo_Call_Eligibility::can_submit( $event_id, $users['registered'] ), 'Verified registered non-member may submit when configured' ); ++$checks;
			self::qa_assert( CYWater_Logo_Call_Eligibility::can_vote( $event_id, $users['registered'] ), 'Verified registered non-member may vote when configured' ); ++$checks;
			self::qa_assert( CYWater_Logo_Call_Eligibility::can_submit( $event_id, $users['student'] ) && CYWater_Logo_Call_Eligibility::can_vote( $event_id, $users['student'] ), 'Registered Student may submit and vote when configured' ); ++$checks;
			update_post_meta( $event_id, '_cywater_logo_call_vote_audience', CYWater_Logo_Call_Eligibility::AUDIENCE_SELECTED_LEVELS );
			update_post_meta( $event_id, '_cywater_logo_call_vote_levels', array( 'professional', 'lifetime' ) );
			self::qa_assert( ! CYWater_Logo_Call_Eligibility::can_vote( $event_id, $users['registered'] ) && ! CYWater_Logo_Call_Eligibility::can_vote( $event_id, $users['student'] ), 'Selected-level policy excludes non-member and Student' ); ++$checks;
			self::qa_assert( CYWater_Logo_Call_Eligibility::can_vote( $event_id, $users['professional'] ) && CYWater_Logo_Call_Eligibility::can_vote( $event_id, $users['lifetime'] ), 'Selected-level policy admits Professional and Lifetime' ); ++$checks;
			update_post_meta( $event_id, '_cywater_logo_call_vote_audience', CYWater_Logo_Call_Eligibility::AUDIENCE_REGISTERED );
			update_post_meta( $event_id, '_cywater_logo_call_reward', self::default_reward() );
			self::qa_assert( self::default_reward() === get_post_meta( $event_id, '_cywater_logo_call_reward', true ), 'Reward is stored independently on the Event' ); ++$checks;
			update_post_meta( $event_id, '_cywater_logo_call_open_at', wp_date( 'Y-m-d H:i', current_time( 'timestamp' ) - HOUR_IN_SECONDS ) );
			update_post_meta( $event_id, '_cywater_logo_call_close_at', wp_date( 'Y-m-d H:i', current_time( 'timestamp' ) + HOUR_IN_SECONDS ) );
			self::qa_assert( 'closed' === self::phase( $event_id ), 'Unpublished Event cannot accept participation' ); ++$checks;
			wp_update_post( array( 'ID' => $event_id, 'post_status' => 'publish' ) );
			self::qa_assert( 'submission' === self::phase( $event_id ), 'Submission phase is open' ); ++$checks;
			$countdown = self::render_countdown( $event_id );
			$module    = self::render_module( $event_id );
			self::qa_assert( false !== strpos( $countdown, 'data-cywater-logo-countdown' ) && false !== strpos( $countdown, 'data-deadline=' ), 'Submission deadline renders one server-backed countdown' ); ++$checks;
			self::qa_assert( false === stripos( $module, 'five highest-ranked' ) && false === stripos( $module, 'top-five' ), 'Public submission copy does not announce a fixed finalist count' ); ++$checks;
			self::qa_assert( true === self::storage_capacity( $users['registered'], $event_id, self::MAX_SUBMISSION_BYTES ), 'Empty account and Event accept one bounded submission' ); ++$checks;
			self::qa_assert( is_wp_error( self::storage_capacity( $users['registered'], $event_id, self::MAX_SUBMISSION_BYTES + 1 ) ), 'Submission aggregate limit fails closed' ); ++$checks;
			$lock_result = self::acquire_storage_lock();
			self::qa_assert( ! is_wp_error( $lock_result ), 'First upload acquires the server-side quota lock' ); ++$checks;
			$qa_storage_lock = (string) $lock_result;
			self::qa_assert( is_wp_error( self::acquire_storage_lock() ), 'Concurrent upload cannot bypass the serialized quota check' ); ++$checks;
			self::qa_assert( self::release_storage_lock( $qa_storage_lock ), 'Quota lock is released by its owner' ); ++$checks;
			$qa_storage_lock = '';

			$quota_entry_id = wp_insert_post( array( 'post_type' => self::ENTRY_TYPE, 'post_status' => 'private', 'post_title' => 'Logo QA quota entry', 'post_author' => $users['student'] ), true );
			if ( is_wp_error( $quota_entry_id ) ) { throw new RuntimeException( $quota_entry_id->get_error_message() ); }
			$posts[] = (int) $quota_entry_id;
			update_post_meta( $quota_entry_id, '_cywater_logo_event_id', $event_id );
			update_post_meta( $quota_entry_id, '_cywater_logo_source', array( 'stored' => 'qa-nonexistent', 'bytes' => self::MAX_EVENT_STORAGE_BYTES ) );
			self::qa_assert( is_wp_error( self::storage_capacity( $users['registered'], $event_id, 1 ) ), 'Event-wide protected storage quota rejects another account' ); ++$checks;
			wp_delete_post( $quota_entry_id, true );
			$posts = array_values( array_diff( $posts, array( (int) $quota_entry_id ) ) );

			$entry_id = wp_insert_post( array( 'post_type' => self::ENTRY_TYPE, 'post_status' => 'private', 'post_title' => 'Logo QA entry', 'post_author' => $users['registered'] ), true );
			if ( is_wp_error( $entry_id ) ) { throw new RuntimeException( $entry_id->get_error_message() ); }
			$posts[] = (int) $entry_id;
			update_post_meta( $entry_id, '_cywater_logo_event_id', $event_id );
			update_post_meta( $entry_id, '_cywater_logo_status', 'shortlisted' );
			update_post_meta( $entry_id, '_cywater_logo_work_number', self::work_number( $entry_id ) );
			self::qa_assert( true === self::grant_participation_reward( $entry_id ) && in_array( get_post_meta( $entry_id, '_cywater_logo_participation_reward_status', true ), array( 'granted', 'covered_by_existing_membership' ), true ), 'Participation reward is applied or safely covered' ); ++$checks;
			self::qa_assert( $entry_id === self::existing_entry( $event_id, $users['registered'] ), 'One-entry lookup is enforced' ); ++$checks;
			self::qa_assert( 1 === count( self::candidates( $event_id ) ), 'Shortlist is available to voting' ); ++$checks;
			self::qa_assert( 1 === preg_match( '/^CYW-LOGO-20\d{2}-\d{6}$/', self::work_number( $entry_id ) ), 'Submission receives a stable work number' ); ++$checks;
			self::qa_assert( ! self::public_asset_allowed( get_post( $entry_id ), 'source' ), 'Shortlisted asset is private during submission' ); ++$checks;

			update_post_meta( $event_id, '_cywater_logo_call_close_at', wp_date( 'Y-m-d H:i', current_time( 'timestamp' ) - 2 * HOUR_IN_SECONDS ) );
			delete_post_meta( $event_id, '_cywater_logo_call_vote_open' );
			delete_post_meta( $event_id, '_cywater_logo_call_vote_close' );
			self::qa_assert( 'review' === self::phase( $event_id ), 'Closed submissions wait safely for an unscheduled voting activity' ); ++$checks;
			update_post_meta( $event_id, '_cywater_logo_call_vote_open', wp_date( 'Y-m-d H:i', current_time( 'timestamp' ) - HOUR_IN_SECONDS ) );
			update_post_meta( $event_id, '_cywater_logo_call_vote_close', wp_date( 'Y-m-d H:i', current_time( 'timestamp' ) + HOUR_IN_SECONDS ) );
			self::qa_assert( 'voting' === self::phase( $event_id ), 'Voting phase is open' ); ++$checks;
			self::qa_assert( self::public_asset_allowed( get_post( $entry_id ), 'source' ), 'Approved design is public only during voting' ); ++$checks;
			update_post_meta( $entry_id, '_cywater_logo_status', 'withdrawn' );
			self::qa_assert( ! self::public_asset_allowed( get_post( $entry_id ), 'source' ), 'Withdrawn entry asset is never public' ); ++$checks;
			update_post_meta( $entry_id, '_cywater_logo_status', 'shortlisted' );
			update_post_meta( $entry_id, '_cywater_logo_event_id', 0 );
			self::qa_assert( ! self::public_asset_allowed( get_post( $entry_id ), 'source' ), 'Entry without an active bound Event is never public' ); ++$checks;
			update_post_meta( $entry_id, '_cywater_logo_event_id', $event_id );
			self::qa_assert( add_user_meta( $users['registered'], self::VOTE_META . $event_id, $entry_id, true ), 'Registered non-member first vote is accepted' ); ++$checks;
			self::qa_assert( ! add_user_meta( $users['registered'], self::VOTE_META . $event_id, $entry_id, true ), 'Registered non-member second vote is rejected' ); ++$checks;
			self::qa_assert( 1 === self::vote_count( $entry_id ), 'Vote total is derived from the one-vote user records' ); ++$checks;
			wp_trash_post( $entry_id );
			self::qa_assert( ! self::public_asset_allowed( get_post( $entry_id ), 'source' ), 'Trashed entry asset is not public' ); ++$checks;
			wp_untrash_post( $entry_id );
			self::qa_assert( self::public_asset_allowed( get_post( $entry_id ), 'source' ), 'Restored private shortlist returns only in voting' ); ++$checks;
			update_post_meta( $event_id, '_cywater_logo_call_enabled', '0' );
			self::qa_assert( ! self::public_asset_allowed( get_post( $entry_id ), 'source' ), 'Disabled Event revokes public asset access' ); ++$checks;
			update_post_meta( $event_id, '_cywater_logo_call_enabled', '1' );
			update_post_meta( $event_id, '_cywater_logo_call_vote_close', wp_date( 'Y-m-d H:i', current_time( 'timestamp' ) - HOUR_IN_SECONDS ) );
			self::qa_assert( 'results' === self::phase( $event_id ) && ! self::public_asset_allowed( get_post( $entry_id ), 'source' ), 'Voting asset closes before finalist confirmation' ); ++$checks;
			update_post_meta( $entry_id, '_cywater_logo_status', 'finalist' );
			self::qa_assert( ! self::public_asset_allowed( get_post( $entry_id ), 'source' ) && 1 === count( self::finalists( $event_id ) ), 'Finalist remains private during Board review' ); ++$checks;
			update_post_meta( $entry_id, '_cywater_logo_status', 'selected' );
			self::qa_assert( self::public_asset_allowed( get_post( $entry_id ), 'source' ), 'Only the Board-selected design is public in results phase' ); ++$checks;
			wp_update_post( array( 'ID' => $event_id, 'post_status' => 'draft' ) );
			self::qa_assert( ! self::public_asset_allowed( get_post( $entry_id ), 'source' ), 'Unpublished Event revokes selected asset access' ); ++$checks;
			self::qa_assert( file_exists( trailingslashit( self::private_directory() ) . '.htaccess' ), 'Private upload protection exists' ); ++$checks;

			WP_CLI::success( sprintf( 'Logo Call QA passed %d checks; temporary data will be removed.', $checks ) );
		} catch ( Throwable $error ) {
			WP_CLI::warning( 'Logo Call QA failed: ' . $error->getMessage() );
			throw $error;
		} finally {
			if ( $qa_storage_lock ) { self::release_storage_lock( $qa_storage_lock ); }
			foreach ( array_reverse( $posts ) as $post_id ) { wp_delete_post( $post_id, true ); }
			foreach ( $users as $user_id ) { pmpro_changeMembershipLevel( 0, $user_id ); wp_delete_user( $user_id ); }
		}
	}

	private static function qa_assert( $condition, $message ) {
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
	}
}
