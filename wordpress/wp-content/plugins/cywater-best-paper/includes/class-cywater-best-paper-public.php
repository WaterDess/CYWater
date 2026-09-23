<?php
/** Applicant-facing Best Paper forms. Private records remain service-owned. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Best_Paper_Public {
	public static function register() {
		add_filter( 'the_content', array( __CLASS__, 'append_module' ), 38 );
		add_shortcode( 'cywater_best_paper', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'cywater_best_paper_preview', array( __CLASS__, 'preview_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'template_redirect', array( __CLASS__, 'protect_personal_response' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'exclude_preview_search' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'exclude_preview_sitemap' ), 10, 2 );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'exclude_preview_menu' ) );
		add_action( 'admin_post_cywater_best_paper_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_cywater_best_paper_submit', array( __CLASS__, 'handle_logged_out_submit' ) );
	}

	private static function may_render( $award_id ) {
		$post = get_post( $award_id );
		return $post && 'cyw_award' === $post->post_type && ( 'publish' === $post->post_status || current_user_can( 'manage_options' ) );
	}

	public static function enqueue_assets() {
		$post = get_post();
		if ( ! $post || ( ! is_singular( 'cyw_award' ) && ! has_shortcode( $post->post_content, 'cywater_best_paper' ) && ! has_shortcode( $post->post_content, 'cywater_best_paper_preview' ) ) ) {
			return;
		}
		self::assets();
	}

	private static function assets() {
		$base = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/';
		$version = defined( 'CYWATER_BEST_PAPER_VERSION' ) ? CYWATER_BEST_PAPER_VERSION : '0.1.0';
		wp_enqueue_style( 'cywater-best-paper', $base . 'best-paper.css', array(), $version );
		wp_enqueue_script( 'cywater-best-paper', $base . 'best-paper.js', array(), $version, true );
	}

	public static function protect_personal_response() {
		$post = get_post();
		if ( ! $post || ! is_singular() ) {
			return;
		}
		$config = 'cyw_award' === $post->post_type ? CYWater_Best_Paper::config( $post->ID ) : array();
		// The public open/closed state also changes at cycle boundaries. Never
		// cache an enabled module, including its anonymous sign-in gate.
		$is_preview = has_shortcode( $post->post_content, 'cywater_best_paper_preview' );
		if ( ! empty( $config['enabled'] ) || has_shortcode( $post->post_content, 'cywater_best_paper' ) || $is_preview ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			nocache_headers();
			do_action( 'litespeed_control_set_nocache', 'CYWater Best Paper application state' );
		}
		if ( $is_preview ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		}
	}

	/** Public by direct URL, but not promoted in browse/search surfaces. */
	private static function preview_page_ids() {
		global $wpdb;
		return array_map( 'absint', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", '_cyw_bp_preview_marker', 'interactive-preview-20260923' ) ) );
	}

	public static function exclude_preview_search( $query ) {
		if ( ! is_admin() && $query->is_search() ) {
			$query->set( 'post__not_in', array_values( array_unique( array_merge( (array) $query->get( 'post__not_in' ), self::preview_page_ids() ) ) ) );
		}
	}

	public static function exclude_preview_sitemap( $args, $post_type ) {
		if ( 'page' === $post_type ) {
			$args['post__not_in'] = array_values( array_unique( array_merge( $args['post__not_in'] ?? array(), self::preview_page_ids() ) ) );
		}
		return $args;
	}

	public static function exclude_preview_menu( $items ) {
		$ids = self::preview_page_ids();
		return array_values( array_filter( $items, static function ( $item ) use ( $ids ) {
			return 'page' !== $item->object || ! in_array( (int) $item->object_id, $ids, true );
		} ) );
	}

	public static function append_module( $content ) {
		if ( doing_filter( 'get_the_excerpt' ) || is_admin() || ! is_singular( 'cyw_award' ) || ! in_the_loop() || ! is_main_query() || has_shortcode( $content, 'cywater_best_paper' ) ) {
			return $content;
		}
		$award_id = get_the_ID();
		// WordPress expands shortcodes at priority 11, before this priority-38
		// filter. Inspect the stored source as well as our rendered section so a
		// deliberately positioned shortcode (or a repeated filter) stays singular.
		$source = (string) get_post_field( 'post_content', $award_id );
		$rendered_id = 'id="cywater-best-paper-' . absint( $award_id ) . '"';
		if ( has_shortcode( $source, 'cywater_best_paper' ) || false !== strpos( $content, $rendered_id ) ) {
			return $content;
		}
		$config = CYWater_Best_Paper::config( $award_id );
		return empty( $config['enabled'] ) ? $content : $content . self::render( $award_id );
	}

	public static function shortcode( $attributes ) {
		// Excerpts must not render private forms or consume the one-time notice
		// that belongs to the full application page.
		if ( doing_filter( 'get_the_excerpt' ) ) {
			return '';
		}
		$attributes = shortcode_atts( array( 'award_id' => 0 ), $attributes, 'cywater_best_paper' );
		$award_id = absint( $attributes['award_id'] );
		return $award_id ? self::render( $award_id ) : '';
	}

	/** A direct-link preview of public Award information, never an intake route. */
	public static function preview_shortcode( $attributes ) {
		if ( doing_filter( 'get_the_excerpt' ) ) {
			return '';
		}
		$attributes = shortcode_atts( array( 'award_id' => 0 ), $attributes, 'cywater_best_paper_preview' );
		return self::render( absint( $attributes['award_id'] ), true );
	}

	/** A direct, permission-checked renderer is also available for staging QA. */
	public static function render( $award_id, $interactive_preview = false ) {
		$award_id = absint( $award_id );
		if ( ! self::may_render( $award_id ) ) {
			return '';
		}
		$config = CYWater_Best_Paper::config( $award_id );
		if ( empty( $config['enabled'] ) && ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		self::assets();
		$phase = CYWater_Best_Paper::phase( $award_id );
		// A preview uses only account defaults, never saved applications or their
		// one-time feedback. It remains non-submitting even when real intake opens.
		$application = ! $interactive_preview && is_user_logged_in() ? CYWater_Best_Paper::own_application( $award_id, get_current_user_id() ) : null;
		$flash = $interactive_preview ? null : self::consume_flash( $award_id );
		$preview = $interactive_preview || ( current_user_can( 'manage_options' ) && ( empty( $config['enabled'] ) || 'draft' === $phase ) );
		$open = ! $interactive_preview && 'applications' === $phase && 'publish' === get_post_status( $award_id );
		ob_start();
		?>
		<section class="cywater-best-paper" id="cywater-best-paper-<?php echo esc_attr( $award_id ); ?>" aria-labelledby="cywater-best-paper-heading-<?php echo esc_attr( $award_id ); ?>">
			<p class="cywater-best-paper__eyebrow">Best Paper Award</p>
			<h2 id="cywater-best-paper-heading-<?php echo esc_attr( $award_id ); ?>"><?php echo esc_html( $application ? 'Your application' : 'Apply for this award' ); ?></h2>
			<p>Submit your paper and CV for the annual CYWater Best Paper Award. Receiving an application does not confirm eligibility or an award; the committee reviews eligible applications and confirms the final results.</p>
			<?php if ( $preview ) : ?>
				<?php if ( $interactive_preview ) : ?>
					<div class="cywater-best-paper__notice" role="note"><strong>Preview only — no application will be submitted.</strong><p>No sign-in is required to view or try this form. If you are signed in, your own account details are filled where available. Nothing entered or selected here is uploaded or saved. The Submit application button is disabled.</p></div>
				<?php else : ?>
				<div class="cywater-best-paper__notice" role="note"><strong>Administrator preview — applications are not open.</strong><p>The form below is a read-only preview. No application can be submitted from this preview.</p></div>
				<?php endif; ?>
			<?php endif; ?>
			<?php self::render_schedule( $config, $phase ); ?>
			<div class="cywater-best-paper__rules">
				<h3>Before you apply</h3>
				<ul>
					<li>You must be 35 years old or younger on the day you first submit this application. Later edits do not change the age assessment date.</li>
					<li>Your paper must have been formally published online within the 12-calendar-month period ending on this award's application deadline. Acceptance dates and print issue dates are not used.</li>
					<li>Previous recipients of either the Best Paper Award or the Outstanding Paper Award are not eligible to apply again.</li>
					<li>Submit one paper per applicant for this award year. The same paper is treated as one application, even if it has multiple authors or is submitted more than once.</li>
				</ul>
				<p><strong>Selection criterion:</strong> Outstanding contributions to water sciences.</p>
				<p>One Best Paper Award is planned. The number of Outstanding Paper Awards is determined by the committee. Applications and uploaded documents are private, not public website media.</p>
			</div>
			<?php if ( $flash ) : ?>
				<div class="cywater-best-paper__notice <?php echo empty( $flash['success'] ) ? 'is-error' : 'is-success'; ?>" role="<?php echo empty( $flash['success'] ) ? 'alert' : 'status'; ?>" tabindex="-1" data-cywater-best-paper-notice>
					<?php echo esc_html( $flash['message'] ); ?>
				</div>
			<?php endif; ?>
			<?php if ( $application ) { self::render_confirmation( $application, $phase ); } ?>
			<?php if ( ! is_user_logged_in() && $open ) : ?>
				<div class="cywater-best-paper__gate"><h3>Sign in to apply</h3><p>Your CYWater account keeps your application and files available to you. A paid membership is not required for this application.</p><a class="btn btn-primary" href="<?php echo esc_url( wp_login_url( get_permalink( $award_id ) . '#cywater-best-paper-' . $award_id ) ); ?>">Sign in to apply</a></div>
			<?php elseif ( $open || $preview ) : ?>
				<?php self::render_form( $award_id, $application, $flash, $preview, $interactive_preview ); ?>
			<?php elseif ( ! $application ) : ?>
				<div class="cywater-best-paper__gate"><h3><?php echo esc_html( in_array( $phase, array( 'draft', 'not_open' ), true ) ? 'Applications are not open yet' : 'Applications are closed' ); ?></h3><p><?php echo esc_html( in_array( $phase, array( 'draft', 'not_open' ), true ) ? 'The confirmed opening date and deadline will be published here before applications open.' : 'New applications and changes are no longer accepted for this award round.' ); ?></p></div>
			<?php endif; ?>
			<p class="cywater-best-paper__support">For application support, <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">contact CYWater</a>.</p>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function render_schedule( $config, $phase ) {
		$timezone = ! empty( $config['timezone'] ) ? $config['timezone'] : wp_timezone_string();
		?>
		<div class="cywater-best-paper__schedule">
			<?php if ( empty( $config['open_at'] ) || empty( $config['close_at'] ) ) : ?>
				<h3>Planned timeline</h3><p>Call for applications in October · Applications close in November · Results before the AGU meeting in December.</p><p class="cywater-best-paper__help">Exact dates and the committee will be confirmed before the call opens.</p>
			<?php else : ?>
				<dl><div><dt>Applications open</dt><dd><?php echo esc_html( self::date_label( $config['open_at'], $timezone ) ); ?></dd></div><div><dt>Application deadline</dt><dd><?php echo esc_html( self::date_label( $config['close_at'], $timezone ) ); ?></dd></div></dl>
				<p class="cywater-best-paper__help">All deadline times use <?php echo esc_html( $timezone ); ?>. <?php echo 'applications' === $phase ? 'Applications are open.' : 'Applications are not currently open.'; ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function date_label( $value, $timezone ) {
		try {
			$date = new DateTimeImmutable( $value, new DateTimeZone( $timezone ) );
			return $date->format( 'F j, Y, g:i a' );
		} catch ( Exception $error ) {
			return 'To be confirmed';
		}
	}

	private static function render_confirmation( $application, $phase ) {
		$record = is_array( $application['record'] ?? null ) ? $application['record'] : array();
		$files = is_array( $application['files'] ?? null ) ? $application['files'] : array();
		$status = in_array( $phase, array( 'review', 'decision' ), true ) ? 'Under review' : 'Application received';
		$eligibility_labels = array( 'eligible' => 'Eligibility confirmed', 'ineligible' => 'Not eligible', 'needs_changes' => 'Changes requested' );
		if ( isset( $eligibility_labels[ $application['status'] ?? '' ] ) ) {
			$status = $eligibility_labels[ $application['status'] ];
		}
		if ( 'announced' === $phase && ! empty( $application['decision'] ) ) {
			$labels = array( 'best' => 'Best Paper Award', 'best_paper' => 'Best Paper Award', 'outstanding' => 'Outstanding Paper Award', 'outstanding_paper' => 'Outstanding Paper Award', 'not_selected' => 'Not selected for an award', 'none' => 'Not selected for an award' );
			$status = $labels[ $application['decision'] ] ?? 'Review completed';
		}
		?>
		<div class="cywater-best-paper__confirmation">
			<p class="cywater-best-paper__eyebrow"><?php echo esc_html( $status ); ?></p>
			<h3><?php echo esc_html( $record['title'] ?? '' ); ?></h3>
			<dl>
				<?php
				$summary = array(
					'Application reference' => 'BP-' . absint( $application['id'] ),
					'Applicant' => trim( ( $record['first_name'] ?? '' ) . ' ' . ( $record['last_name'] ?? '' ) ),
					'Institution' => $record['institution'] ?? '',
					'Email' => $record['email'] ?? '',
					'Journal' => $record['journal'] ?? '',
					'Online publication date' => $record['online_date'] ?? '',
					'DOI' => ! empty( $record['doi'] ) ? $record['doi'] : 'Not supplied',
					'First submitted' => $record['submitted_at'] ?? $application['created_at'] ?? '',
				);
				foreach ( $summary as $label => $value ) : ?>
					<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
			<h4>Your submitted files</h4><p class="cywater-best-paper__help">You can download the documents saved with this application. They are available only to you and authorized award staff.</p>
			<ul class="cywater-best-paper__files">
				<?php foreach ( array( 'paper' => 'Paper PDF', 'cv' => 'CV PDF' ) as $kind => $label ) : ?>
					<?php if ( empty( $files[ $kind ] ) ) { continue; } ?>
					<li><span><strong><?php echo esc_html( $label ); ?></strong><small><?php echo esc_html( $files[ $kind ]['original'] ?? $label ); ?><?php if ( ! empty( $files[ $kind ]['bytes'] ) ) { echo ' · ' . esc_html( size_format( $files[ $kind ]['bytes'] ) ); } ?></small></span><a class="btn btn-outline" href="<?php echo esc_url( CYWater_Best_Paper::download_url( $application['id'], $kind ) ); ?>">Download<span class="screen-reader-text"> <?php echo esc_html( $label ); ?></span></a></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( 'applications' !== $phase ) : ?><p class="cywater-best-paper__help">The application is now read-only. You can still review and download your submitted documents.</p><?php endif; ?>
		</div>
		<?php
	}

	private static function render_form( $award_id, $application, $flash, $preview, $interactive_preview = false ) {
		$user = wp_get_current_user();
		$read_only = $preview && ! $interactive_preview;
		// Reuse Membership's existing profile field; never infer legal name parts
		// from a username/display name or copy private birth dates from elsewhere.
		$institution = get_user_meta( $user->ID, 'cyw_institution_name', true );
		$values = array(
			'first_name'  => $user->first_name,
			'last_name'   => $user->last_name,
			'email'       => $user->user_email,
			'institution' => is_scalar( $institution ) ? sanitize_text_field( (string) $institution ) : '',
			'dob'         => '',
			'title'       => '',
			'journal'     => '',
			'doi'         => '',
			'online_date' => '',
		);
		// Profile data is only a starting point. A saved application, then a
		// failed submission's values, take precedence over later profile changes.
		if ( $application && is_array( $application['record'] ?? null ) ) {
			$values = array_merge( $values, $application['record'] );
		}
		if ( ! empty( $flash['values'] ) && is_array( $flash['values'] ) ) {
			$values = array_merge( $values, $flash['values'] );
		}
		if ( $application ) {
			$values['dob'] = $application['record']['dob'] ?? '';
		}
		?>
		<?php if ( $application ) : ?><h3 class="cywater-best-paper__edit-heading">Update your application</h3><p>Before the deadline, you can correct the details below or replace a document. Leaving a file field empty keeps the file already submitted. Your account profile is not changed.</p><?php endif; ?>
		<?php if ( $interactive_preview ) : ?>
		<!-- Deliberately not a form: Enter and disabled JavaScript cannot submit. -->
		<div class="cywater-best-paper__form" data-cywater-best-paper-form data-cywater-best-paper-preview>
		<?php else : ?>
		<form class="cywater-best-paper__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data" data-cywater-best-paper-form>
			<input type="hidden" name="action" value="cywater_best_paper_submit"><input type="hidden" name="award_id" value="<?php echo esc_attr( $award_id ); ?>">
			<?php wp_nonce_field( 'cywater_best_paper_submit_' . $award_id, 'cywater_best_paper_nonce' ); ?>
		<?php endif; ?>
			<fieldset <?php disabled( $read_only ); ?>>
				<legend>Applicant information</legend>
				<?php if ( ! $application ) : ?><p class="cywater-best-paper__help">Your name, email and institution are filled from your CYWater account where available. Please check these details before submitting. Changes here apply only to this application and do not change your account profile.</p><?php endif; ?>
				<div class="cywater-best-paper__grid">
					<?php self::field( $award_id, 'first_name', 'First name', $values['first_name'], 'text', true, 'given-name' ); ?>
					<?php self::field( $award_id, 'last_name', 'Last name', $values['last_name'], 'text', true, 'family-name' ); ?>
					<?php self::field( $award_id, 'email', 'Email', $values['email'], 'email', true, 'email' ); ?>
					<?php self::field( $award_id, 'institution', 'Institution', $values['institution'], 'text', true, 'organization' ); ?>
					<div class="cywater-best-paper__field wide"><label for="cywater-bp-<?php echo esc_attr( $award_id ); ?>-dob">Date of birth <span aria-hidden="true">*</span></label><input type="date" id="cywater-bp-<?php echo esc_attr( $award_id ); ?>-dob" name="dob" value="<?php echo esc_attr( $values['dob'] ); ?>" required <?php echo $application ? 'readonly' : ''; ?> aria-describedby="cywater-bp-<?php echo esc_attr( $award_id ); ?>-dob-help"><small id="cywater-bp-<?php echo esc_attr( $award_id ); ?>-dob-help">Used privately to verify your age on first submission. It is not published and cannot be changed through a later edit. Contact CYWater if a correction is needed.</small></div>
				</div>
			</fieldset>
			<fieldset <?php disabled( $read_only ); ?>>
				<legend>Paper details</legend>
				<div class="cywater-best-paper__grid">
					<?php self::field( $award_id, 'title', 'Paper title', $values['title'], 'text', true, 'off', true ); ?>
					<?php self::field( $award_id, 'journal', 'Journal', $values['journal'], 'text', true ); ?>
					<?php self::field( $award_id, 'online_date', 'First formal online publication date', $values['online_date'], 'date', true ); ?>
					<div class="cywater-best-paper__field wide"><label for="cywater-bp-<?php echo esc_attr( $award_id ); ?>-doi">DOI <span class="cywater-best-paper__optional">(if available)</span></label><input type="text" id="cywater-bp-<?php echo esc_attr( $award_id ); ?>-doi" name="doi" value="<?php echo esc_attr( $values['doi'] ); ?>" maxlength="255" placeholder="10.1234/example" aria-describedby="cywater-bp-<?php echo esc_attr( $award_id ); ?>-doi-help"><small id="cywater-bp-<?php echo esc_attr( $award_id ); ?>-doi-help">Enter the DOI or its https://doi.org/ link. If no DOI is available, leave this field blank; the paper title is still checked for duplicate applications.</small></div>
				</div>
			</fieldset>
			<fieldset <?php disabled( $read_only ); ?>>
				<legend>Application documents</legend>
				<p class="cywater-best-paper__help">Upload the paper and your current CV as PDF files, up to 20 MB each. Files are stored privately. Do not include unnecessary personal identification documents.</p>
				<?php foreach ( array( 'paper' => 'Paper PDF', 'cv' => 'CV PDF' ) as $kind => $label ) { self::file_field( $award_id, $kind, $label, $application['files'][ $kind ] ?? null ); } ?>
			</fieldset>
			<fieldset <?php disabled( $read_only ); ?>>
				<legend>Declarations</legend>
				<label class="cywater-best-paper__consent"><input type="checkbox" name="no_prior_award" value="yes" required <?php checked( ! empty( $values['no_prior_award'] ) ); ?>><span>I confirm that I have never received either the CYWater Best Paper Award or the Outstanding Paper Award. <span aria-hidden="true">*</span></span></label>
				<label class="cywater-best-paper__consent"><input type="checkbox" name="eligibility" value="yes" required <?php checked( ! empty( $values['eligibility'] ) ); ?>><span>I confirm that the information and files are accurate, that I meet the stated eligibility requirements, and that this paper is submitted only once for this award round. I agree to authorized award staff and committee members accessing these materials for the selection process. <span aria-hidden="true">*</span></span></label>
				<div class="cywater-best-paper__actions"><button type="<?php echo $interactive_preview ? 'button' : 'submit'; ?>" class="btn btn-primary" <?php disabled( $preview ); ?>><?php echo $application ? 'Save application changes' : 'Submit application'; ?></button></div>
				<p class="cywater-best-paper__help"><?php echo $interactive_preview ? 'Preview only. These fields and local file selections are not saved or uploaded.' : 'Your saved application and files will appear here after successful submission. Please review the confirmation before leaving this page.'; ?></p>
			</fieldset>
		<?php if ( $interactive_preview ) : ?></div><?php else : ?></form><?php endif; ?>
		<?php
	}

	private static function field( $award_id, $name, $label, $value, $type = 'text', $required = false, $autocomplete = 'off', $wide = false ) {
		$id = 'cywater-bp-' . $award_id . '-' . $name;
		?>
		<div class="cywater-best-paper__field<?php echo $wide ? ' wide' : ''; ?>"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?><?php if ( $required ) : ?> <span aria-hidden="true">*</span><?php endif; ?></label><input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>" value="<?php echo esc_attr( $value ); ?>" autocomplete="<?php echo esc_attr( $autocomplete ); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo 'text' === $type ? 'maxlength="500"' : ''; ?>></div>
		<?php
	}

	private static function file_field( $award_id, $kind, $label, $existing ) {
		$id = 'cywater-bp-' . $award_id . '-' . $kind;
		?>
		<div class="cywater-best-paper__field cywater-best-paper__file-field">
			<span id="<?php echo esc_attr( $id ); ?>-label" class="cywater-best-paper__file-title"><?php echo esc_html( $label ); ?><?php if ( ! $existing ) : ?> <span aria-hidden="true">*</span><?php endif; ?></span>
			<div class="cywater-best-paper__file-control">
				<input class="cywater-best-paper__file-input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $kind ); ?>" type="file" accept="application/pdf,.pdf" <?php echo ! $existing ? 'required' : ''; ?> aria-labelledby="<?php echo esc_attr( $id ); ?>-label" aria-describedby="<?php echo esc_attr( $id ); ?>-name <?php echo esc_attr( $id ); ?>-help" data-cywater-best-paper-file>
				<label class="cywater-best-paper__file-button" for="<?php echo esc_attr( $id ); ?>">Choose File</label>
				<span class="cywater-best-paper__file-name" id="<?php echo esc_attr( $id ); ?>-name" aria-live="polite" data-cywater-best-paper-filename><?php echo esc_html( $existing ? 'No replacement selected' : 'No file chosen' ); ?></span>
			</div>
			<small id="<?php echo esc_attr( $id ); ?>-help"><?php echo esc_html( $existing ? 'Current file: ' . ( $existing['original'] ?? $label ) . '. Leave unchanged to keep this file.' : 'PDF only · Maximum 20 MB' ); ?></small>
		</div>
		<?php
	}

	private static function return_url( $award_id ) {
		$url = get_permalink( $award_id );
		if ( 'publish' !== get_post_status( $award_id ) && current_user_can( 'manage_options' ) ) {
			$url = get_preview_post_link( $award_id );
		}
		return $url ? $url : home_url( '/awards/' );
	}

	public static function handle_logged_out_submit() {
		$award_id = isset( $_POST['award_id'] ) && is_scalar( $_POST['award_id'] ) ? absint( $_POST['award_id'] ) : 0;
		$return = $award_id && 'cyw_award' === get_post_type( $award_id ) && 'publish' === get_post_status( $award_id ) ? get_permalink( $award_id ) : home_url( '/awards/' );
		wp_safe_redirect( wp_login_url( $return ), 303 );
		exit;
	}

	public static function handle_submit() {
		if ( ! is_user_logged_in() ) {
			self::handle_logged_out_submit();
		}
		$award_id = isset( $_POST['award_id'] ) && is_scalar( $_POST['award_id'] ) ? absint( $_POST['award_id'] ) : 0;
		if ( ! self::may_render( $award_id ) ) {
			wp_safe_redirect( home_url( '/awards/' ), 303 );
			exit;
		}
		$input = array();
		foreach ( array( 'first_name', 'last_name', 'email', 'institution', 'dob', 'title', 'journal', 'doi', 'online_date', 'no_prior_award', 'eligibility' ) as $field ) {
			$input[ $field ] = isset( $_POST[ $field ] ) && is_scalar( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		$nonce = isset( $_POST['cywater_best_paper_nonce'] ) && is_scalar( $_POST['cywater_best_paper_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cywater_best_paper_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cywater_best_paper_submit_' . $award_id ) ) {
			$result = new WP_Error( 'expired_session' );
		} else {
			try {
				$result = CYWater_Best_Paper::submit( $award_id, $input, $_FILES, get_current_user_id() ); // Service validates uploads and rechecks ownership, dates and eligibility.
			} catch ( Throwable $error ) {
				$result = new WP_Error( 'unexpected_error' );
			}
		}
		$success = ! is_wp_error( $result ) && absint( $result ) > 0;
		$flash = array( 'success' => $success, 'message' => $success ? 'Your application has been saved. Please review the details and submitted files below.' : self::error_message( is_wp_error( $result ) ? $result->get_error_code() : '' ) );
		if ( ! $success ) {
			$flash['values'] = $input;
		}
		$token = wp_generate_password( 24, false, false );
		set_transient( 'cyw_bp_flash_' . get_current_user_id() . '_' . $award_id . '_' . $token, $flash, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'bp_notice', $token, self::return_url( $award_id ) ) . '#cywater-best-paper-' . $award_id, 303 );
		exit;
	}

	private static function consume_flash( $award_id ) {
		$token = isset( $_GET['bp_notice'] ) && is_scalar( $_GET['bp_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['bp_notice'] ) ) : '';
		if ( ! is_user_logged_in() || ! preg_match( '/^[a-zA-Z0-9]{24}$/', $token ) ) {
			return null;
		}
		$key = 'cyw_bp_flash_' . get_current_user_id() . '_' . $award_id . '_' . $token;
		$flash = get_transient( $key );
		delete_transient( $key );
		return is_array( $flash ) ? $flash : null;
	}

	/** Never expose database, mail transport, filesystem, or exception details. */
	private static function error_message( $code ) {
		$code = preg_replace( '/^cywater_bp_/', '', $code );
		$messages = array(
			'expired_session' => 'Your form session has expired. Review the saved details and submit again. Please select your files again if needed.',
			'closed' => 'Applications are not currently open. No changes were saved.',
			'not_open' => 'Applications are not currently open. No changes were saved.',
			'age' => 'Applicants must be 35 years old or younger on their first submission date. Check the date of birth.',
			'online_date' => 'Check the online publication date. It must fall within the 12-calendar-month window ending on the application deadline.',
			'duplicate' => 'This paper is already associated with an application. Review your existing application or contact CYWater for assistance.',
			'prior_award' => 'Previous Best Paper or Outstanding Paper Award recipients are not eligible to apply again.',
			'declarations' => 'Please review and confirm both required declarations.',
			'eligibility' => 'Please review and confirm both required declarations.',
			'birthdate' => 'The date of birth cannot be changed after first submission. Contact CYWater if it needs correction.',
			'email' => 'Please enter a valid contact email address.',
			'required' => 'Please complete all required applicant and paper details.',
			'doi' => 'Enter a valid DOI or leave the DOI field blank if none is available.',
			'file' => 'Please upload both your paper PDF and CV PDF. Each file must be no larger than 20 MB.',
			'file_type' => 'Only genuine PDF documents up to 20 MB each are accepted. Please choose the files again.',
			'upload' => 'A file upload did not complete. Please choose your PDF files again and retry.',
			'busy' => 'Another request for this award is being processed. Please wait briefly and try again.',
		);
		return $messages[ $code ] ?? 'Your application could not be saved. Check the required fields, eligibility dates and PDF uploads (maximum 20 MB each), then try again. Your previously saved application, if any, remains available above. Please select new files again before resubmitting.';
	}
}
