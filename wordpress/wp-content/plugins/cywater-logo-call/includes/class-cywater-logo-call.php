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

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_entry_type' ) );
		add_action( 'add_meta_boxes_cyw_event', array( __CLASS__, 'add_event_box' ) );
		add_action( 'add_meta_boxes_' . self::ENTRY_TYPE, array( __CLASS__, 'add_entry_box' ) );
		add_action( 'save_post_cyw_event', array( __CLASS__, 'save_event' ) );
		add_action( 'save_post_' . self::ENTRY_TYPE, array( __CLASS__, 'save_entry_review' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_event_module' ), 35 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_cywater_logo_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_cywater_logo_vote', array( __CLASS__, 'handle_vote' ) );
		add_action( 'admin_post_cywater_logo_asset', array( __CLASS__, 'stream_asset' ) );
		add_action( 'admin_post_nopriv_cywater_logo_asset', array( __CLASS__, 'stream_asset' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_entry_files' ) );
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
				'show_in_menu'        => 'edit.php?post_type=cyw_event',
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
		echo '<p><strong>' . esc_html__( 'Registered account:', 'cywater-logo-call' ) . '</strong> ' . esc_html( $user ? $user->user_login : __( 'Unavailable', 'cywater-logo-call' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Statement:', 'cywater-logo-call' ) . '</strong><br>' . nl2br( esc_html( (string) get_post_meta( $post->ID, '_cywater_logo_statement', true ) ) ) . '</p>';
		foreach ( array( 'source' => __( 'Original design file', 'cywater-logo-call' ), 'lockup' => __( 'Full-name lockup', 'cywater-logo-call' ) ) as $kind => $label ) {
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
		echo '</select></p><p>' . esc_html__( 'Only shortlisted and selected entries are displayed for voting. Selection records the configured reward as due; an administrator must separately complete the rights agreement and membership fulfillment.', 'cywater-logo-call' ) . '</p>';
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

	private static function render_module( $event_id ) {
		$phase   = self::phase( $event_id );
		$user_id = get_current_user_id();
		$close   = self::date_label( get_post_meta( $event_id, '_cywater_logo_call_close_at', true ) );
		$vote_open  = self::date_label( get_post_meta( $event_id, '_cywater_logo_call_vote_open', true ) );
		$vote_close = self::date_label( get_post_meta( $event_id, '_cywater_logo_call_vote_close', true ) );
		$submitter   = CYWater_Logo_Call_Eligibility::public_label( $event_id, 'submit' );
		$voter       = CYWater_Logo_Call_Eligibility::public_label( $event_id, 'vote' );
		$reward      = (string) get_post_meta( $event_id, '_cywater_logo_call_reward', true );
		$reward      = $reward ?: self::default_reward();
		ob_start();
		?>
		<section class="cywater-logo-call" aria-labelledby="cywater-logo-call-title">
			<p class="cywater-logo-call__eyebrow"><?php esc_html_e( 'Logo design call', 'cywater-logo-call' ); ?></p>
			<h2 id="cywater-logo-call-title"><?php esc_html_e( 'Design the next CYWater logo', 'cywater-logo-call' ); ?></h2>
			<p><?php echo esc_html( sprintf( __( 'Submit one original logo set by %s. The set must include the logo itself and a version paired with “International Association of Contemporary Young Scholars in Water Sciences”.', 'cywater-logo-call' ), $close ) ); ?></p>
			<div class="cywater-logo-call__rules"><h3><?php esc_html_e( 'Rules at a glance', 'cywater-logo-call' ); ?></h3><ul>
				<li><?php echo esc_html( sprintf( __( 'One submission set per %s.', 'cywater-logo-call' ), $submitter ) ); ?></li>
				<li><?php esc_html_e( 'Original file: PDF, PNG, JPEG or WebP; full-name preview: PNG, JPEG or WebP. Maximum 5 MB per file.', 'cywater-logo-call' ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Selected-design reward: %s.', 'cywater-logo-call' ), $reward ) ); ?></li>
				<li><?php esc_html_e( 'Entrants warrant originality. CYWater receives review/display permission; permanent use of a selected design requires a separate written rights agreement.', 'cywater-logo-call' ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'During voting, each %s has one final vote.', 'cywater-logo-call' ), $voter ) ); ?></li>
			</ul></div>
			<p class="cywater-logo-call__schedule"><?php echo esc_html( sprintf( __( 'Registered-user voting is a later phase, from %1$s to %2$s. Only designs shortlisted by the administrators appear there.', 'cywater-logo-call' ), $vote_open, $vote_close ) ); ?></p>
			<?php self::render_feedback(); ?>
			<?php if ( 'submission' === $phase ) : ?>
				<?php self::render_submission_form( $event_id, $user_id ); ?>
			<?php elseif ( 'voting' === $phase ) : ?>
				<?php self::render_voting( $event_id, $user_id ); ?>
			<?php elseif ( 'before' === $phase ) : ?>
				<p class="cywater-logo-call__notice"><?php esc_html_e( 'Submissions have not opened yet.', 'cywater-logo-call' ); ?></p>
			<?php else : ?>
				<?php self::render_results( $event_id ); ?>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function render_feedback() {
		$status = isset( $_GET['logo_call'] ) ? sanitize_key( wp_unslash( $_GET['logo_call'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'submitted'      => __( 'Your submission set was received. CYWater will email you after review.', 'cywater-logo-call' ),
			'voted'          => __( 'Your vote was recorded.', 'cywater-logo-call' ),
			'already'        => __( 'This account has already used its one submission or vote for this event.', 'cywater-logo-call' ),
			'ineligible'     => __( 'This account is not eligible for this action under the Event participation settings.', 'cywater-logo-call' ),
			'closed'         => __( 'This stage is not currently open.', 'cywater-logo-call' ),
			'invalid_file'   => __( 'A required file was missing, too large or not an accepted format.', 'cywater-logo-call' ),
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
		if ( ! CYWater_Logo_Call_Eligibility::can_submit( $event_id, $user_id ) ) {
			echo '<p class="cywater-logo-call__notice">' . esc_html__( 'This account does not match the submission audience selected by the Event administrator.', 'cywater-logo-call' ) . '</p>';
			self::render_submission_fields( $event_id, true );
			return;
		}
		if ( self::existing_entry( $event_id, $user_id ) ) {
			echo '<p class="cywater-logo-call__notice">' . esc_html__( 'Your one submission set has been received for this event.', 'cywater-logo-call' ) . '</p>';
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
			<p><label for="cywater_logo_source"><strong><?php esc_html_e( 'Original logo file', 'cywater-logo-call' ); ?></strong></label><br><input required type="file" id="cywater_logo_source" name="cywater_logo_source" accept=".pdf,.png,.jpg,.jpeg,.webp"<?php echo $disabled_attr; ?>></p>
			<p><label for="cywater_logo_lockup"><strong><?php esc_html_e( 'Logo with the full CYWater association name', 'cywater-logo-call' ); ?></strong></label><br><input required type="file" id="cywater_logo_lockup" name="cywater_logo_lockup" accept=".png,.jpg,.jpeg,.webp" data-cywater-logo-preview-input<?php echo $disabled_attr; ?>></p>
			<figure class="cywater-logo-call__preview" data-cywater-logo-preview hidden><img alt=""><figcaption><?php esc_html_e( 'Local preview of the full-name effect image. Nothing is uploaded until you submit.', 'cywater-logo-call' ); ?></figcaption></figure>
			<p><label for="cywater_logo_statement"><strong><?php esc_html_e( 'Design statement (optional, up to 1,000 characters)', 'cywater-logo-call' ); ?></strong></label><br><textarea id="cywater_logo_statement" name="cywater_logo_statement" maxlength="1000" rows="5"<?php echo $disabled_attr; ?>></textarea></p>
			<p><label><input required type="checkbox" name="cywater_logo_terms" value="1"<?php echo $disabled_attr; ?>> <?php esc_html_e( 'I warrant that this is original work and agree to the review, display and selected-design rights process stated above.', 'cywater-logo-call' ); ?></label></p>
			<button class="button" type="submit"<?php echo $disabled_attr; ?>><?php echo esc_html( $disabled ? __( 'Submission access required', 'cywater-logo-call' ) : __( 'Submit one logo set', 'cywater-logo-call' ) ); ?></button>
		</form>
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
		} elseif ( ! CYWater_Logo_Call_Eligibility::can_vote( $event_id, $user_id ) ) {
			echo '<p class="cywater-logo-call__notice">' . esc_html__( 'This account does not match the voting audience selected by the Event administrator.', 'cywater-logo-call' ) . '</p>';
		} elseif ( ! $can_vote ) {
			echo '<p class="cywater-logo-call__notice">' . esc_html__( 'This account has already cast its final vote.', 'cywater-logo-call' ) . '</p>';
		}
		echo '<form class="cywater-logo-call__vote" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="cywater_logo_vote"><input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
		wp_nonce_field( 'cywater_logo_vote_' . $event_id, 'cywater_logo_vote_nonce' );
		echo '<div class="cywater-logo-call__grid">';
		foreach ( $entries as $entry ) {
			$image = admin_url( 'admin-post.php?action=cywater_logo_asset&entry=' . $entry->ID . '&kind=lockup' );
			echo '<label class="cywater-logo-call__candidate"><img src="' . esc_url( $image ) . '" alt="' . esc_attr( sprintf( __( 'Shortlisted logo design %d', 'cywater-logo-call' ), $entry->ID ) ) . '">';
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
			echo '<p class="cywater-logo-call__notice">' . esc_html__( 'The call is closed. Results will be announced after Board review and completion of the selected-design rights agreement.', 'cywater-logo-call' ) . '</p>';
			return;
		}
		$image = admin_url( 'admin-post.php?action=cywater_logo_asset&entry=' . $selected[0]->ID . '&kind=lockup' );
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
		if ( empty( $_POST['cywater_logo_terms'] ) ) {
			self::redirect( $event_id, 'error' );
		}
		$submission_key = '_cywater_logo_entry_' . $event_id;
		if ( ! add_user_meta( $user_id, $submission_key, 'pending', true ) ) {
			self::redirect( $event_id, 'already' );
		}

		$source = self::store_upload( 'cywater_logo_source', array( 'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg|jpeg' => 'image/jpeg', 'webp' => 'image/webp' ) );
		$lockup = self::store_upload( 'cywater_logo_lockup', array( 'png' => 'image/png', 'jpg|jpeg' => 'image/jpeg', 'webp' => 'image/webp' ) );
		if ( is_wp_error( $source ) || is_wp_error( $lockup ) ) {
			self::delete_stored( $source );
			self::delete_stored( $lockup );
			delete_user_meta( $user_id, $submission_key );
			self::redirect( $event_id, 'invalid_file' );
		}

		$entry_id = wp_insert_post( array( 'post_type' => self::ENTRY_TYPE, 'post_status' => 'private', 'post_title' => sprintf( 'Logo submission — %s — %s', get_the_title( $event_id ), wp_date( 'Y-m-d H:i:s' ) ), 'post_author' => $user_id ), true );
		if ( is_wp_error( $entry_id ) ) {
			self::delete_stored( $source );
			self::delete_stored( $lockup );
			delete_user_meta( $user_id, $submission_key );
			self::redirect( $event_id, 'error' );
		}
		update_user_meta( $user_id, $submission_key, (int) $entry_id );
		update_post_meta( $entry_id, '_cywater_logo_event_id', $event_id );
		update_post_meta( $entry_id, '_cywater_logo_status', 'submitted' );
		update_post_meta( $entry_id, '_cywater_logo_statement', isset( $_POST['cywater_logo_statement'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['cywater_logo_statement'] ) ), 0, 1000 ) : '' );
		update_post_meta( $entry_id, '_cywater_logo_source', $source );
		update_post_meta( $entry_id, '_cywater_logo_lockup', $lockup );
		wp_mail( wp_get_current_user()->user_email, __( 'CYWater Logo Call submission received', 'cywater-logo-call' ), __( 'Your one logo submission set has been received for review. This message does not indicate selection. Please contact membership@cywater.org with questions.', 'cywater-logo-call' ) );
		wp_mail( 'membership@cywater.org', __( 'New CYWater Logo Call submission', 'cywater-logo-call' ), sprintf( __( 'A new submission is ready for administrator review: %s', 'cywater-logo-call' ), admin_url( 'post.php?post=' . $entry_id . '&action=edit' ) ) );
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
		if ( ! $candidate || self::ENTRY_TYPE !== $candidate->post_type || $event_id !== absint( get_post_meta( $entry_id, '_cywater_logo_event_id', true ) ) || ! in_array( $status, array( 'shortlisted', 'selected' ), true ) ) {
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
		if ( ! $entry || self::ENTRY_TYPE !== $entry->post_type || ! in_array( $kind, array( 'source', 'lockup' ), true ) ) {
			status_header( 404 ); exit;
		}
		$public = 'lockup' === $kind && in_array( get_post_meta( $entry_id, '_cywater_logo_status', true ), array( 'shortlisted', 'selected' ), true );
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
		header( 'Content-Disposition: ' . ( $public ? 'inline' : 'attachment' ) . '; filename="' . rawurlencode( sanitize_file_name( $file['original'] ?? basename( $path ) ) ) . '"' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	private static function store_upload( $field, $allowed ) {
		if ( empty( $_FILES[ $field ]['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $_FILES[ $field ]['error'] || (int) $_FILES[ $field ]['size'] > 5 * MB_IN_BYTES ) {
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
		return array( 'stored' => $stored, 'original' => $name, 'type' => $check['type'] );
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
		self::delete_stored( get_post_meta( $post_id, '_cywater_logo_lockup', true ) );
	}

	private static function phase( $event_id ) {
		if ( ! self::is_enabled( $event_id ) ) {
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
		if ( $times['vote_open'] && $times['vote_close'] && $now >= $times['vote_open'] && $now <= $times['vote_close'] ) { return 'voting'; }
		return 'closed';
	}

	private static function is_enabled( $event_id ) {
		return $event_id && 'cyw_event' === get_post_type( $event_id ) && '1' === (string) get_post_meta( $event_id, '_cywater_logo_call_enabled', true );
	}

	private static function existing_entry( $event_id, $user_id ) {
		$ids = get_posts( array( 'post_type' => self::ENTRY_TYPE, 'post_status' => array( 'private', 'trash' ), 'author' => $user_id, 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_cywater_logo_event_id', 'meta_value' => $event_id ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		return $ids ? (int) $ids[0] : 0;
	}

	private static function candidates( $event_id ) {
		return get_posts( array( 'post_type' => self::ENTRY_TYPE, 'post_status' => 'private', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'meta_query' => array( array( 'key' => '_cywater_logo_event_id', 'value' => $event_id ), array( 'key' => '_cywater_logo_status', 'value' => array( 'shortlisted', 'selected' ), 'compare' => 'IN' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	private static function date_label( $value ) {
		return $value ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T', self::local_timestamp( $value ) ) : __( 'the published deadline', 'cywater-logo-call' );
	}

	private static function default_reward() {
		return __( 'Two years of CYWater Professional membership', 'cywater-logo-call' );
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

	private static function statuses() {
		return array( 'submitted' => __( 'Submitted', 'cywater-logo-call' ), 'shortlisted' => __( 'Shortlisted for voting', 'cywater-logo-call' ), 'not_selected' => __( 'Not selected', 'cywater-logo-call' ), 'selected' => __( 'Selected — rights agreement and reward fulfillment tracked separately', 'cywater-logo-call' ), 'withdrawn' => __( 'Withdrawn', 'cywater-logo-call' ) );
	}

	private static function reward_statuses() {
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
			$id = wp_insert_post( array( 'post_type' => 'cyw_event', 'post_status' => $status, 'post_name' => 'logo-design-call-2026', 'post_title' => 'CYWater Logo Design Call 2026', 'post_content' => '<p>CYWater invites registered users to propose an original association logo and a companion lockup showing the association’s full legal name. This staging event hosts the removable submission and voting module below.</p>' ), true );
			if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
		}
		$dates = array( 'open_at' => '2026-08-12 00:00', 'close_at' => '2026-09-12 23:59', 'vote_open' => '2026-09-14 00:00', 'vote_close' => '2026-09-21 23:59' );
		update_post_meta( $id, '_cywater_logo_call_enabled', '1' );
		if ( ! term_exists( 'member-program', 'cyw_event_type' ) ) { wp_insert_term( 'Member program', 'cyw_event_type', array( 'slug' => 'member-program' ) ); }
		wp_set_object_terms( $id, 'member-program', 'cyw_event_type' );
		foreach ( $dates as $key => $value ) { update_post_meta( $id, '_cywater_logo_call_' . $key, $value ); }
		foreach ( array( 'submit', 'vote' ) as $action ) {
			update_post_meta( $id, '_cywater_logo_call_' . $action . '_audience', CYWater_Logo_Call_Eligibility::AUDIENCE_REGISTERED );
			update_post_meta( $id, '_cywater_logo_call_' . $action . '_levels', array() );
		}
		update_post_meta( $id, '_cywater_logo_call_reward', self::default_reward() );
		update_post_meta( $id, '_cyw_start_date', '2026-08-12' ); update_post_meta( $id, '_cyw_end_date', '2026-09-21' ); update_post_meta( $id, '_cyw_date_label', 'Aug 12–Sep 21, 2026' ); update_post_meta( $id, '_cyw_location', 'Online' ); update_post_meta( $id, '_cyw_format', 'Logo design call' ); update_post_meta( $id, '_cyw_status', 'upcoming' );
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
			self::qa_assert( CYWater_Logo_Call_Eligibility::can_submit( $event_id, $users['registered'] ), 'Registered non-member may submit when configured' ); ++$checks;
			self::qa_assert( CYWater_Logo_Call_Eligibility::can_vote( $event_id, $users['registered'] ), 'Registered non-member may vote when configured' ); ++$checks;
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
			self::qa_assert( 'submission' === self::phase( $event_id ), 'Submission phase is open' ); ++$checks;

			$entry_id = wp_insert_post( array( 'post_type' => self::ENTRY_TYPE, 'post_status' => 'private', 'post_title' => 'Logo QA entry', 'post_author' => $users['student'] ), true );
			if ( is_wp_error( $entry_id ) ) { throw new RuntimeException( $entry_id->get_error_message() ); }
			$posts[] = (int) $entry_id;
			update_post_meta( $entry_id, '_cywater_logo_event_id', $event_id );
			update_post_meta( $entry_id, '_cywater_logo_status', 'shortlisted' );
			self::qa_assert( $entry_id === self::existing_entry( $event_id, $users['student'] ), 'One-entry lookup is enforced' ); ++$checks;
			self::qa_assert( 1 === count( self::candidates( $event_id ) ), 'Shortlist is available to voting' ); ++$checks;

			update_post_meta( $event_id, '_cywater_logo_call_close_at', wp_date( 'Y-m-d H:i', current_time( 'timestamp' ) - 2 * HOUR_IN_SECONDS ) );
			update_post_meta( $event_id, '_cywater_logo_call_vote_open', wp_date( 'Y-m-d H:i', current_time( 'timestamp' ) - HOUR_IN_SECONDS ) );
			update_post_meta( $event_id, '_cywater_logo_call_vote_close', wp_date( 'Y-m-d H:i', current_time( 'timestamp' ) + HOUR_IN_SECONDS ) );
			self::qa_assert( 'voting' === self::phase( $event_id ), 'Voting phase is open' ); ++$checks;
			self::qa_assert( add_user_meta( $users['registered'], self::VOTE_META . $event_id, $entry_id, true ), 'Registered non-member first vote is accepted' ); ++$checks;
			self::qa_assert( ! add_user_meta( $users['registered'], self::VOTE_META . $event_id, $entry_id, true ), 'Registered non-member second vote is rejected' ); ++$checks;
			self::qa_assert( file_exists( trailingslashit( self::private_directory() ) . '.htaccess' ), 'Private upload protection exists' ); ++$checks;

			WP_CLI::success( sprintf( 'Logo Call QA passed %d checks; temporary data will be removed.', $checks ) );
		} catch ( Throwable $error ) {
			WP_CLI::warning( 'Logo Call QA failed: ' . $error->getMessage() );
			throw $error;
		} finally {
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
