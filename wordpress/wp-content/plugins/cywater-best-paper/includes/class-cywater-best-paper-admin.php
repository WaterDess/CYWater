<?php
/**
 * Award-scoped Best Paper coordination and review workspace.
 *
 * Application ownership, eligibility, transitions and private-file authorization
 * remain in CYWater_Best_Paper. This class is only an authenticated admin UI.
 */
defined( 'ABSPATH' ) || exit;

final class CYWater_Best_Paper_Admin {

	const PAGE = 'cywater-best-paper';

	public static function register() {
		add_action( 'init', array( __CLASS__, 'scoped_reviewer_admin_access' ), 19 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 1001 );
		add_action( 'admin_post_cywater_best_paper_admin', array( __CLASS__, 'handle' ) );
	}

	/** Exempt this one committee page, never another administration route. */
	public static function scoped_reviewer_admin_access() {
		if ( ! is_admin() || ! is_user_logged_in() || ! current_user_can( 'read' ) || CYWater_Best_Paper::can_manage() ) {
			return;
		}
		$script = wp_basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ?? '' ) ) );
		$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
		if ( 'admin.php' !== $script || self::PAGE !== $page ) {
			return;
		}
		$award_id = absint( $_GET['award_id'] ?? 0 );
		if ( $award_id ? ! self::can_access_award( $award_id ) : ! self::available_awards() ) {
			return;
		}
		remove_action( 'init', array( 'CYWater_Forum_Workspace', 'deny_nonstaff_admin' ), 20 );
	}

	public static function menu() {
		if ( ! self::available_awards() ) {
			return;
		}
		if ( CYWater_Best_Paper::can_manage() ) {
			add_submenu_page( 'edit.php?post_type=cyw_award', 'Best Paper workflow', 'Best Paper workflow', 'read', self::PAGE, array( __CLASS__, 'page' ) );
			return;
		}
		// Register after Operations has filtered its own operational menu. The
		// committee assignment grants no broad role or other wp-admin access.
		add_menu_page( 'Best Paper workflow', 'Best Paper reviews', 'read', self::PAGE, array( __CLASS__, 'page' ), 'dashicons-awards', 31 );
		global $menu;
		$has_work_heading = false;
		foreach ( (array) $menu as $item ) {
			if ( false !== strpos( (string) ( $item[4] ?? '' ), 'cywater-menu-group-work' ) ) {
				$has_work_heading = true;
			}
		}
		foreach ( $menu as &$item ) {
			if ( self::PAGE === ( $item[2] ?? '' ) ) {
				$item[4] = trim( (string) ( $item[4] ?? '' ) . ' cywater-menu-group-work' . ( $has_work_heading ? '' : ' cywater-menu-group-start' ) );
			}
		}
		unset( $item );
	}

	private static function available_awards() {
		$awards = get_posts( array( 'post_type' => 'cyw_award', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => false ) );
		if ( CYWater_Best_Paper::can_manage() ) {
			return $awards;
		}
		return array_values( array_filter( $awards, static function ( $award ) {
			return CYWater_Best_Paper::is_reviewer( $award->ID );
		} ) );
	}

	private static function can_access_award( $award_id ) {
		return 'cyw_award' === get_post_type( $award_id ) && ( CYWater_Best_Paper::can_manage() || CYWater_Best_Paper::is_reviewer( $award_id ) );
	}

	private static function url( $award_id = 0, $application_id = 0 ) {
		$args = array( 'page' => self::PAGE );
		if ( CYWater_Best_Paper::can_manage() ) {
			$args['post_type'] = 'cyw_award';
		}
		if ( $award_id ) {
			$args['award_id'] = absint( $award_id );
		}
		if ( $application_id ) {
			$args['application_id'] = absint( $application_id );
		}
		return add_query_arg( $args, admin_url( CYWater_Best_Paper::can_manage() ? 'edit.php' : 'admin.php' ) );
	}

	private static function form_start( $operation, $award_id, $application_id = 0 ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="cywater_best_paper_admin">';
		echo '<input type="hidden" name="operation" value="' . esc_attr( $operation ) . '">';
		echo '<input type="hidden" name="award_id" value="' . esc_attr( $award_id ) . '">';
		if ( $application_id ) {
			echo '<input type="hidden" name="application_id" value="' . esc_attr( $application_id ) . '">';
		}
		wp_nonce_field( 'cywater_best_paper_admin_' . $award_id );
	}

	public static function page() {
		if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			wp_die( 'You cannot access this workspace.', '', array( 'response' => 403 ) );
		}
		$awards = self::available_awards();
		$award_id = isset( $_GET['award_id'] ) ? absint( $_GET['award_id'] ) : ( $awards ? (int) $awards[0]->ID : 0 );
		if ( $award_id && ! self::can_access_award( $award_id ) ) {
			wp_die( 'You cannot access this award.', '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap"><h1>Best Paper workflow</h1>';
		self::notice();
		echo '<p>Private application, eligibility and review workspace. Awards archives and News announcements remain separate public records.</p>';
		if ( ! $awards ) {
			echo '<p>No annual award is available to your account.</p></div>';
			return;
		}
		echo '<form method="get" action="' . esc_url( admin_url( CYWater_Best_Paper::can_manage() ? 'edit.php' : 'admin.php' ) ) . '">';
		if ( CYWater_Best_Paper::can_manage() ) {
			echo '<input type="hidden" name="post_type" value="cyw_award">';
		}
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><label for="cyw-bp-award">Annual award </label><select name="award_id" id="cyw-bp-award">';
		foreach ( $awards as $award ) {
			echo '<option value="' . esc_attr( $award->ID ) . '" ' . selected( $award_id, $award->ID, false ) . '>' . esc_html( get_the_title( $award ) ) . '</option>';
		}
		echo '</select> <button class="button" type="submit">Open award</button></form>';
		$config = CYWater_Best_Paper::config( $award_id );
		if ( CYWater_Best_Paper::can_manage() ) {
			self::configuration( $award_id, $config );
		} else {
			echo '<p><strong>Phase:</strong> ' . esc_html( self::phase_label( $config['status'] ?? 'draft' ) ) . '. Only authorized applications and protected files are available. Other reviewers\' scores remain private until the decision phase; conflicts continue to restrict access.</p>';
		}
		$application_id = isset( $_GET['application_id'] ) ? absint( $_GET['application_id'] ) : 0;
		if ( $application_id ) {
			$app = CYWater_Best_Paper::get_application( $application_id );
			if ( ! $app || is_wp_error( $app ) || (int) $app['award_id'] !== $award_id || ! CYWater_Best_Paper::can_view_application( $app ) ) {
				wp_die( 'This application is not available to your account.', '', array( 'response' => 403 ) );
			}
			self::application( $app, $config );
		} else {
			self::application_list( $award_id, $config );
		}
		echo '</div>';
	}

	private static function phase_label( $phase ) {
		return array( 'draft' => 'Draft / closed', 'applications' => 'Accepting applications', 'review' => 'Review', 'decision' => 'Committee decision', 'announced' => 'Results announced' )[ $phase ] ?? $phase;
	}

	private static function configuration( $award_id, $config ) {
		$preview_url = 'publish' === get_post_status( $award_id ) ? get_permalink( $award_id ) : get_preview_post_link( $award_id );
		echo '<p><a class="button" href="' . esc_url( $preview_url ) . '" target="_blank" rel="noopener">Preview application page</a> <a class="button" href="' . esc_url( get_edit_post_link( $award_id ) ) . '">Edit public Award content</a></p>';
		echo '<details><summary><strong>Annual workflow settings and setup guide</strong></summary>';
		echo '<p>Prepare this year\'s call in October, close applications in November, and announce results before the December AGU meeting. These are planning months, not configured deadlines. Coordinate review meetings proactively; Hong Yang is the contact for award-process questions. No Chair or account access is assigned by this note.</p>';
		echo '<ol><li>Choose an annual Award record and confirm its public English call, dates, Chair and reviewers.</li><li>Enable this module and select the applications phase only when the call and exact dates are approved.</li><li>Verify eligibility after submission; assign qualified applications to configured reviewers.</li><li>Move explicitly to review, then committee decision. Scores and z-scores inform discussion but do not select winners.</li><li>Record one Best Paper and the committee\'s Outstanding Paper selections. Verify recipients and the public Award / News content before marking results announced.</li><li>Arrange ceremony, certificates and presentation files separately. No message, payment or AGU upload is sent by saving these settings.</li></ol>';
		self::form_start( 'config', $award_id );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">Application module</th><td><label><input type="checkbox" name="enabled" value="1" ' . checked( ! empty( $config['enabled'] ), true, false ) . '> Enable for this annual award</label><p class="description">Disabled or incomplete settings keep applications closed.</p></td></tr>';
		echo '<tr><th scope="row"><label for="cyw-bp-phase">Workflow phase</label></th><td><select name="status" id="cyw-bp-phase">';
		foreach ( array( 'draft', 'applications', 'review', 'decision', 'announced' ) as $phase ) {
			echo '<option value="' . esc_attr( $phase ) . '" ' . selected( $config['status'] ?? 'draft', $phase, false ) . '>' . esc_html( self::phase_label( $phase ) ) . '</option>';
		}
		echo '</select><p class="description">Phases are changed by a coordinator, not by score rank or an automatic publication job.</p></td></tr>';
		foreach ( array( 'open_at' => 'Applications open', 'close_at' => 'Application deadline', 'review_deadline' => 'Review deadline' ) as $key => $label ) {
			$value = str_replace( ' ', 'T', substr( (string) ( $config[ $key ] ?? '' ), 0, 16 ) );
			echo '<tr><th scope="row"><label for="cyw-bp-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input type="datetime-local" id="cyw-bp-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '"><p class="description">Timezone: ' . esc_html( $config['timezone'] ?? wp_timezone_string() ) . '. Exact dates must be confirmed before opening.</p></td></tr>';
		}
		echo '<tr><th scope="row"><label for="cyw-bp-chair">Chair account ID</label></th><td><input type="number" min="0" name="chair_id" id="cyw-bp-chair" value="' . esc_attr( $config['chair_id'] ?? 0 ) . '"><p class="description">Use an existing WordPress account ID; 0 means unassigned. This records the Chair and scoped committee access, not administrator permission. Add the Chair to the reviewer list separately if they will score papers.</p></td></tr>';
		$ids = array_map( 'absint', (array) ( $config['reviewer_ids'] ?? array() ) );
		echo '<tr><th scope="row"><label for="cyw-bp-reviewers">Reviewer account IDs</label></th><td><input class="regular-text" name="reviewer_ids" id="cyw-bp-reviewers" value="' . esc_attr( implode( ', ', $ids ) ) . '"><p class="description">Comma-separated existing account IDs. Assignment is scoped to this annual award, not a global role grant.</p>';
		foreach ( array_unique( array_merge( $ids, array( absint( $config['chair_id'] ?? 0 ) ) ) ) as $user_id ) {
			if ( $user_id ) {
				echo '<div>' . esc_html( self::user_label( $user_id ) ) . '</div>';
			}
		}
		echo '</td></tr>';
		foreach ( array( 'prize' => 'Confirmed prize / award notes', 'ceremony' => 'Ceremony and AGU preparation notes' ) as $key => $label ) {
			echo '<tr><th scope="row"><label for="cyw-bp-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><textarea class="large-text" rows="3" name="' . esc_attr( $key ) . '" id="cyw-bp-' . esc_attr( $key ) . '">' . esc_textarea( $config[ $key ] ?? '' ) . '</textarea></td></tr>';
		}
		echo '<tr><th scope="row">Announcement confirmation</th><td><label><input type="checkbox" name="confirm_announcement" value="1"> I confirm the committee decisions and separately prepared public announcement are approved.</label><p class="description">Required when selecting Results announced. This setting does not silently rewrite Award archives or publish a News post.</p></td></tr>';
		echo '</tbody></table>';
		submit_button( 'Save annual workflow settings' );
		echo '</form></details>';
	}

	private static function user_label( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name . ' (#' . $user_id . ')' : 'Account #' . $user_id . ' (unavailable)';
	}

	private static function application_list( $award_id, $config ) {
		$apps = CYWater_Best_Paper::applications( $award_id );
		if ( is_wp_error( $apps ) ) {
			echo '<p>' . esc_html( $apps->get_error_message() ) . '</p>';
			return;
		}
		echo '<h2>Applications</h2>';
		if ( CYWater_Best_Paper::can_manage() ) {
			echo '<p>Exports contain private applicant materials. Share only with authorized committee members. Birth dates and storage paths are excluded.</p>';
			self::form_start( 'export', $award_id );
			echo '<p><label for="cyw-bp-export-scope">Application CSV / ZIP scope </label><select id="cyw-bp-export-scope" name="scope"><option value="eligible">Eligible applications only</option><option value="all">All applications</option></select></p><p class="description">Use Eligible applications only for committee materials. The separate private review CSV includes all review records.</p>';
			echo '<p><button class="button" name="format" value="csv" type="submit">Export application CSV</button> <button class="button" name="format" value="zip" type="submit">Export structured application ZIP</button> <button class="button" name="format" value="reviews" type="submit">Export private review CSV</button></p></form>';
		}
		echo '<table class="widefat striped"><thead><tr><th>Application</th><th>Paper</th><th>Eligibility</th><th>Review progress</th><th>Decision</th><th>Submitted</th></tr></thead><tbody>';
		$visible = 0;
		foreach ( $apps as $app ) {
			if ( ! CYWater_Best_Paper::can_view_application( $app ) ) {
				continue;
			}
			++$visible;
			$record = (array) $app['record'];
			$reviews = self::review_rows( $app['id'] );
			$submitted = count( array_filter( $reviews, static function ( $row ) { return 'submitted' === ( $row['status'] ?? '' ); } ) );
			echo '<tr><td><a href="' . esc_url( self::url( $award_id, $app['id'] ) ) . '"><strong>#' . esc_html( $app['id'] ) . ' ' . esc_html( trim( ( $record['first_name'] ?? '' ) . ' ' . ( $record['last_name'] ?? '' ) ) ) . '</strong></a><br>' . esc_html( $record['institution'] ?? '' ) . '</td><td>' . esc_html( $record['title'] ?? '' ) . '<br><small>' . esc_html( $record['journal'] ?? '' ) . '</small></td><td>' . esc_html( self::status_label( $app['status'] ) ) . '</td><td>' . esc_html( $submitted . ' / ' . count( $reviews ) ) . ' visible reviews submitted</td><td>' . esc_html( self::decision_visible( $config ) ? self::decision_label( $app['decision'] ?? 'none' ) : 'Committee decision pending' ) . '</td><td>' . esc_html( $record['submitted_at'] ?? $app['created_at'] ?? '' ) . '</td></tr>';
		}
		if ( ! $visible ) {
			echo '<tr><td colspan="6">No applications are available to your account for this award.</td></tr>';
		}
		echo '</tbody></table>';
		if ( self::decision_visible( $config ) ) {
			self::summary_table( $award_id, $apps );
		}
	}

	private static function summary_table( $award_id, $apps ) {
		$summary = CYWater_Best_Paper::score_summary( $award_id );
		if ( ! is_array( $summary ) || ! $summary ) {
			return;
		}
		echo '<h3>Committee scoring summary</h3><p>Read this alongside original reviews and the committee discussion. Incomplete coverage or an unavailable z-score is not a zero. Rows remain in application order; no winner is selected automatically.</p><table class="widefat striped"><thead><tr><th>Application</th><th>Submitted scores</th><th>Raw mean</th><th>Mean z-score</th><th>Committee decision</th></tr></thead><tbody>';
		foreach ( $apps as $app ) {
			if ( ! CYWater_Best_Paper::can_view_application( $app ) || ! isset( $summary[ $app['id'] ] ) ) {
				continue;
			}
			$row = $summary[ $app['id'] ];
			echo '<tr><td><a href="' . esc_url( self::url( $award_id, $app['id'] ) ) . '">#' . esc_html( $app['id'] ) . ' ' . esc_html( $app['record']['title'] ?? '' ) . '</a></td><td>' . esc_html( $row['count'] ?? 0 ) . '</td><td>' . esc_html( self::number( $row['mean'] ?? null ) ) . '</td><td>' . esc_html( self::number( $row['z_mean'] ?? null ) ) . '</td><td>' . esc_html( self::decision_label( $app['decision'] ?? 'none' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function review_rows( $application_id ) {
		$rows = CYWater_Best_Paper::reviews( $application_id );
		return is_wp_error( $rows ) || ! is_array( $rows ) ? array() : $rows;
	}

	private static function decision_visible( $config ) {
		return CYWater_Best_Paper::can_manage() || in_array( $config['status'] ?? 'draft', array( 'decision', 'announced' ), true );
	}

	private static function status_label( $status ) {
		return array( 'submitted' => 'Submitted — awaiting verification', 'eligible' => 'Eligible', 'ineligible' => 'Ineligible', 'needs_changes' => 'Changes requested' )[ $status ] ?? $status;
	}

	private static function decision_label( $decision ) {
		return array( 'none' => 'Not selected', 'best' => 'Best Paper', 'outstanding' => 'Outstanding Paper' )[ $decision ] ?? $decision;
	}

	private static function application( $app, $config ) {
		$record = (array) $app['record'];
		$award_id = (int) $app['award_id'];
		$id = (int) $app['id'];
		echo '<p><a href="' . esc_url( self::url( $award_id ) ) . '">&larr; All available applications</a></p><h2>Application #' . esc_html( $id ) . '</h2>';
		echo '<table class="widefat"><tbody>';
		$fields = array( 'first_name' => 'First name', 'last_name' => 'Last name', 'institution' => 'Institution', 'title' => 'Paper title', 'journal' => 'Journal', 'doi' => 'DOI', 'online_date' => 'First online publication date' );
		if ( CYWater_Best_Paper::can_manage() ) {
			$fields = array_merge( $fields, array( 'email' => 'Applicant email', 'age_at_submission' => 'Age at first submission', 'submitted_at' => 'First submission', 'version' => 'Application version' ) );
		}
		foreach ( $fields as $key => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $record[ $key ] ?? '' ) . '</td></tr>';
		}
		echo '<tr><th scope="row">Eligibility</th><td>' . esc_html( self::status_label( $app['status'] ) ) . '</td></tr>';
		if ( CYWater_Best_Paper::can_manage() ) {
			echo '<tr><th scope="row">Prior-award declaration</th><td>' . ( ! empty( $record['no_prior_award'] ) ? 'Applicant declares no prior Best Paper or Outstanding Paper award. Staff verification is still required.' : 'No valid declaration recorded.' ) . '</td></tr>';
		}
		echo '<tr><th scope="row">Protected files</th><td>';
		foreach ( array( 'paper' => 'Paper PDF', 'cv' => 'CV PDF' ) as $kind => $label ) {
			$file = $app['files'][ $kind ] ?? array();
			if ( ! $file ) {
				echo '<p>' . esc_html( $label ) . ': not uploaded</p>';
				continue;
			}
			echo '<p><a href="' . esc_url( CYWater_Best_Paper::download_url( $id, $kind ) ) . '">' . esc_html( $label . ': ' . ( $file['original'] ?? 'PDF' ) ) . '</a> <span class="description">(' . esc_html( size_format( (int) ( $file['bytes'] ?? 0 ) ) ) . ')</span></p>';
		}
		echo '</td></tr></tbody></table>';
		if ( CYWater_Best_Paper::can_manage() ) {
			self::management( $app, $config );
		}
		self::review_panel( $app, $config );
	}

	private static function management( $app, $config ) {
		$id = (int) $app['id'];
		$award_id = (int) $app['award_id'];
		echo '<h3>Eligibility and committee decision</h3><p>Check the actual paper, applicant identity and historical winners. One paper is one application. Automatic checks do not replace eligibility verification.</p>';
		self::form_start( 'staff_update', $award_id, $id );
		echo '<p><label for="cyw-bp-eligibility">Eligibility </label><select name="status" id="cyw-bp-eligibility">';
		foreach ( array( 'submitted', 'eligible', 'ineligible', 'needs_changes' ) as $status ) {
			echo '<option value="' . esc_attr( $status ) . '" ' . selected( $app['status'], $status, false ) . '>' . esc_html( self::status_label( $status ) ) . '</option>';
		}
		echo '</select></p><p><label for="cyw-bp-decision">Committee decision </label><select name="decision" id="cyw-bp-decision">';
		foreach ( array( 'none', 'best', 'outstanding' ) as $decision ) {
			echo '<option value="' . esc_attr( $decision ) . '" ' . selected( $app['decision'] ?? 'none', $decision, false ) . '>' . esc_html( self::decision_label( $decision ) ) . '</option>';
		}
		echo '</select></p><p class="description">The committee decides the result. At most one Best Paper is permitted. Outstanding Paper count is not fixed. Selecting a result does not publish News, send mail, make a payment or rewrite the public award archive.</p><p><label for="cyw-bp-staff-note">Eligibility / decision note</label><br><textarea class="large-text" rows="3" name="note" id="cyw-bp-staff-note">' . esc_textarea( $app['record']['staff_note'] ?? '' ) . '</textarea></p><p class="description">Explain missing documents, ineligibility or the committee\'s decision. Changes are retained in the application audit history.</p>';
		submit_button( 'Save eligibility / decision', 'secondary' );
		echo '</form><h3>Assign a reviewer</h3>';
		self::form_start( 'assign', $award_id, $id );
		echo '<p><label for="cyw-bp-assign">Configured reviewer </label><select name="reviewer_id" id="cyw-bp-assign"><option value="">Select reviewer</option>';
		foreach ( (array) ( $config['reviewer_ids'] ?? array() ) as $user_id ) {
			echo '<option value="' . esc_attr( absint( $user_id ) ) . '">' . esc_html( self::user_label( absint( $user_id ) ) ) . '</option>';
		}
		echo '</select> <button class="button" type="submit">Assign reviewer</button></p><p class="description">Agree conflicts of interest before assignment. Reviewers can declare a conflict and recuse; recusal must not be counted as a zero score.</p></form>';
	}

	private static function review_panel( $app, $config ) {
		$id = (int) $app['id'];
		$rows = self::review_rows( $id );
		$own = null;
		foreach ( $rows as $row ) {
			if ( (int) $row['reviewer_id'] === get_current_user_id() ) {
				$own = $row;
			}
		}
		echo '<h3>Review</h3><p>Score out of 10; decimals are allowed. Use the approved review criteria. Missing or recused reviews are not zero scores. Standardized scores use each reviewer\'s sample standard deviation and are advisory, not automatic awards.</p>';
		$deadline_passed = false;
		if ( ! empty( $config['review_deadline'] ) ) {
			try {
				$deadline = new DateTimeImmutable( $config['review_deadline'], new DateTimeZone( $config['timezone'] ?? wp_timezone_string() ) );
				$deadline_passed = time() > $deadline->getTimestamp();
			} catch ( Exception $error ) {
				$deadline_passed = true;
			}
		}
		if ( $own && 'review' === CYWater_Best_Paper::phase( $app['award_id'] ) && ! $deadline_passed && 'eligible' === $app['status'] && 'recused' !== $own['status'] ) {
			self::form_start( 'review', $app['award_id'], $id );
			echo '<p><label for="cyw-bp-score">Your score (0–10) </label><input type="number" min="0" max="10" step="0.01" id="cyw-bp-score" name="score" value="' . esc_attr( $own['score'] ?? '' ) . '"></p><p><label for="cyw-bp-comments">Your review comments</label><br><textarea class="large-text" rows="5" id="cyw-bp-comments" name="comments">' . esc_textarea( $own['comments'] ?? '' ) . '</textarea></p><p><button class="button button-primary" name="review_action" value="submit" type="submit">Save my review</button> <button class="button" name="review_action" value="recuse" type="submit">Declare conflict and recuse</button></p><p class="description">Use the comments field to explain a conflict. Recusal blocks further access to the application\'s protected materials.</p></form>';
		} elseif ( $own && 'recused' === $own['status'] ) {
			echo '<p>You are recused from this application.</p>';
		} elseif ( $deadline_passed && 'review' === ( $config['status'] ?? '' ) ) {
			echo '<p><strong>The review deadline has passed.</strong> Existing reviews are retained and are read-only.</p>';
		} else {
			echo '<p>Scoring is available only to an assigned reviewer during the configured review phase and before the review deadline.</p>';
		}
		echo '<table class="widefat striped"><thead><tr><th>Reviewer</th><th>Status</th><th>Score</th><th>Comments</th><th>Updated</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( self::user_label( $row['reviewer_id'] ) ) . '</td><td>' . esc_html( $row['status'] ) . '</td><td>' . esc_html( 'submitted' === $row['status'] ? (string) $row['score'] : '—' ) . '</td><td>' . nl2br( esc_html( $row['comments'] ?? '' ) ) . '</td><td>' . esc_html( $row['updated_at'] ?? '' ) . '</td></tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="5">No visible reviews.</td></tr>';
		}
		echo '</tbody></table>';
		if ( self::decision_visible( $config ) ) {
			$summary = CYWater_Best_Paper::score_summary( $app['award_id'] );
			if ( ! is_wp_error( $summary ) && isset( $summary[ $id ] ) ) {
				$item = $summary[ $id ];
				echo '<p><strong>Scoring summary:</strong> ' . esc_html( $item['count'] ?? 0 ) . ' submitted reviews; mean score ' . esc_html( self::number( $item['mean'] ?? null ) ) . '; mean z-score ' . esc_html( self::number( $item['z_mean'] ?? null ) ) . '.</p><p class="description">A z-score is unavailable where a reviewer has fewer than two scores or zero score variance. Raw scores remain authoritative records.</p>';
			}
		}
	}

	private static function number( $value ) {
		return null === $value || ! is_numeric( $value ) ? 'N/A' : number_format_i18n( (float) $value, 3 );
	}

	public static function handle() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			wp_die( 'This action requires an authenticated form submission.', '', array( 'response' => 403 ) );
		}
		$award_id = absint( $_POST['award_id'] ?? 0 );
		if ( ! self::can_access_award( $award_id ) ) {
			wp_die( 'You cannot access this award.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cywater_best_paper_admin_' . $award_id );
		$operation = sanitize_key( wp_unslash( $_POST['operation'] ?? '' ) );
		$id = absint( $_POST['application_id'] ?? 0 );
		if ( ! in_array( $operation, array( 'config', 'export', 'staff_update', 'assign', 'review' ), true ) ) {
			wp_die( 'Unknown operation.', '', array( 'response' => 400 ) );
		}
		if ( 'review' !== $operation && ! CYWater_Best_Paper::can_manage() ) {
			wp_die( 'Only a workflow administrator can perform this action.', '', array( 'response' => 403 ) );
		}
		if ( in_array( $operation, array( 'staff_update', 'assign', 'review' ), true ) ) {
			$app = CYWater_Best_Paper::get_application( $id );
			if ( ! $app || is_wp_error( $app ) || (int) $app['award_id'] !== $award_id || ! CYWater_Best_Paper::can_view_application( $app ) ) {
				wp_die( 'This application is not available to your account.', '', array( 'response' => 403 ) );
			}
		}
		$result = true;
		if ( 'config' === $operation ) {
			$config = CYWater_Best_Paper::config( $award_id );
			$config['enabled'] = ! empty( $_POST['enabled'] );
			foreach ( array( 'status', 'open_at', 'close_at', 'review_deadline' ) as $key ) {
				$config[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
			}
			$config['chair_id'] = absint( $_POST['chair_id'] ?? 0 );
			$config['reviewer_ids'] = array_values( array_unique( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', sanitize_text_field( wp_unslash( $_POST['reviewer_ids'] ?? '' ) ) ) ) ) ) );
			foreach ( array( 'prize', 'ceremony' ) as $key ) {
				$config[ $key ] = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ?? '' ) );
			}
			$config['confirm_results'] = ! empty( $_POST['confirm_announcement'] );
			if ( 'announced' === $config['status'] && ! $config['confirm_results'] ) {
				$result = new WP_Error( 'confirmation', 'Confirm approved committee decisions and the public announcement before marking results announced.' );
			} else {
				$result = CYWater_Best_Paper::save_config( $award_id, $config );
			}
		} elseif ( 'staff_update' === $operation ) {
			$result = CYWater_Best_Paper::staff_update( $id, array( 'status' => sanitize_key( wp_unslash( $_POST['status'] ?? '' ) ), 'decision' => sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) ), 'note' => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ) ) );
		} elseif ( 'assign' === $operation ) {
			$result = CYWater_Best_Paper::assign( $id, absint( $_POST['reviewer_id'] ?? 0 ) );
		} elseif ( 'review' === $operation ) {
			$recuse = 'recuse' === sanitize_key( wp_unslash( $_POST['review_action'] ?? '' ) );
			$result = CYWater_Best_Paper::review( $id, sanitize_text_field( wp_unslash( $_POST['score'] ?? '' ) ), sanitize_textarea_field( wp_unslash( $_POST['comments'] ?? '' ) ), $recuse );
		} elseif ( 'export' === $operation ) {
			$result = self::export( $award_id, sanitize_key( wp_unslash( $_POST['format'] ?? '' ) ), sanitize_key( wp_unslash( $_POST['scope'] ?? 'eligible' ) ) );
		}
		$message = is_wp_error( $result ) ? $result->get_error_message() : 'Saved. No external notification, payment or publication was sent.';
		set_transient( 'cyw_bp_admin_notice_' . get_current_user_id(), array( 'error' => is_wp_error( $result ), 'message' => $message ), MINUTE_IN_SECONDS );
		wp_safe_redirect( self::url( $award_id, 'review' === $operation && ! empty( $recuse ) ? 0 : $id ) );
		exit;
	}

	private static function notice() {
		$key = 'cyw_bp_admin_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( $notice ) {
			delete_transient( $key );
			echo '<div class="notice ' . ( ! empty( $notice['error'] ) ? 'notice-error' : 'notice-success' ) . '"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
	}

	/** Spreadsheet-safe text; keep untrusted applicant formulas inert. */
	private static function csv_cell( $value ) {
		$value = str_replace( array( "\r", "\n", "\t", "\0" ), ' ', (string) $value );
		return preg_match( '/^[\s]*[=+\-@]/u', $value ) ? "'" . $value : $value;
	}

	private static function export_record( $app ) {
		$record = (array) $app['record'];
		$out = array( 'application_id' => (int) $app['id'], 'award_id' => (int) $app['award_id'], 'status' => $app['status'], 'decision' => $app['decision'] ?? 'none' );
		foreach ( array( 'first_name', 'last_name', 'institution', 'email', 'title', 'journal', 'doi', 'online_date', 'age_at_submission', 'no_prior_award', 'submitted_at', 'version' ) as $key ) {
			$out[ $key ] = $record[ $key ] ?? '';
		}
		$out['files'] = array();
		foreach ( array( 'paper', 'cv' ) as $kind ) {
			$file = $app['files'][ $kind ] ?? array();
			if ( $file ) {
				$out['files'][ $kind ] = array_intersect_key( $file, array_flip( array( 'original', 'bytes', 'mime', 'sha256' ) ) );
			}
		}
		return $out;
	}

	private static function csv( $apps ) {
		$stream = fopen( 'php://temp', 'w+' );
		if ( false === $stream ) {
			return new WP_Error( 'csv_stream', 'Could not prepare the CSV export.' );
		}
		$headers = array( 'application_id', 'award_id', 'status', 'decision', 'first_name', 'last_name', 'institution', 'email', 'title', 'journal', 'doi', 'online_date', 'age_at_submission', 'no_prior_award', 'submitted_at', 'version', 'paper_filename', 'cv_filename' );
		fwrite( $stream, "\xEF\xBB\xBF" );
		fputcsv( $stream, $headers, ',', '"', '' );
		foreach ( $apps as $app ) {
			$record = self::export_record( $app );
			$record['paper_filename'] = $app['files']['paper']['original'] ?? '';
			$record['cv_filename'] = $app['files']['cv']['original'] ?? '';
			$row = array();
			foreach ( $headers as $key ) {
				$row[] = self::csv_cell( $record[ $key ] ?? '' );
			}
			fputcsv( $stream, $row, ',', '"', '' );
		}
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		fclose( $stream );
		return $csv;
	}

	private static function export( $award_id, $format, $scope = 'all' ) {
		if ( ! CYWater_Best_Paper::can_manage() || ! in_array( $format, array( 'csv', 'zip', 'reviews' ), true ) ) {
			return new WP_Error( 'export_permission', 'This export is not available.' );
		}
		$apps = self::export_applications( $award_id, 'reviews' === $format ? 'all' : $scope );
		if ( is_wp_error( $apps ) ) {
			return $apps;
		}
		$csv = 'reviews' === $format ? self::review_csv( $award_id, $apps ) : self::csv( $apps );
		if ( is_wp_error( $csv ) ) {
			return $csv;
		}
		$name = 'cywater-best-paper-' . absint( $award_id ) . '-' . gmdate( 'Ymd-His' );
		if ( in_array( $format, array( 'csv', 'reviews' ), true ) ) {
			self::download_headers( $name . ( 'reviews' === $format ? '-private-reviews' : '-' . $scope ) . '.csv', 'text/csv; charset=UTF-8' );
			echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV-escaped download, not HTML.
			exit;
		}
		$path = self::build_package( $award_id, $scope );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		try {
			self::download_headers( $name . '-' . $scope . '.zip', 'application/zip' );
			header( 'Content-Length: ' . filesize( $path ) );
			readfile( $path );
		} finally {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}
		exit;
	}

	private static function export_applications( $award_id, $scope ) {
		if ( ! CYWater_Best_Paper::can_manage() || 'cyw_award' !== get_post_type( absint( $award_id ) ) || ! in_array( $scope, array( 'all', 'eligible' ), true ) ) {
			return new WP_Error( 'export_scope', 'Choose an available annual award and export scope.' );
		}
		$apps = CYWater_Best_Paper::applications( $award_id );
		if ( is_wp_error( $apps ) || 'all' === $scope ) {
			return $apps;
		}
		return array_values( array_filter( $apps, static function ( $app ) { return 'eligible' === $app['status']; } ) );
	}

	/**
	 * Build a manager-only package without emitting HTTP headers or adding routes.
	 * The caller owns the successful private temporary path and MUST unlink it.
	 * Failure removes every temporary artifact before returning WP_Error.
	 *
	 * @return string|WP_Error Verified private temporary ZIP path or a safe error.
	 */
	public static function build_package( $award_id, $scope = 'all' ) {
		$award_id = absint( $award_id );
		$apps = self::export_applications( $award_id, $scope );
		if ( is_wp_error( $apps ) ) {
			return $apps;
		}
		$csv = self::csv( $apps );
		if ( is_wp_error( $csv ) ) {
			return $csv;
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_unavailable', 'ZIP support is unavailable on this server. CSV export remains available.' );
		}
		$directory = CYWater_Best_Paper::private_directory();
		if ( is_wp_error( $directory ) ) {
			return new WP_Error( 'private_export', 'Protected storage is unavailable; no package was created.' );
		}
		$temp = tempnam( $directory, 'bp-export-' );
		if ( ! $temp || realpath( dirname( $temp ) ) !== realpath( $directory ) ) {
			if ( $temp && is_file( $temp ) ) {
				unlink( $temp );
			}
			return new WP_Error( 'export_temp', 'Could not prepare a protected export file.' );
		}
		chmod( $temp, 0600 );
		$zip = new ZipArchive();
		$opened = false;
		$complete = false;
		try {
			if ( true !== $zip->open( $temp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				throw new RuntimeException( 'Could not create the export archive.' );
			}
			$opened = true;
			$manifest = array( 'award_id' => $award_id, 'award_title' => get_the_title( $award_id ), 'exported_at_utc' => gmdate( 'c' ), 'scope' => $scope, 'application_count' => count( $apps ), 'contains_private_materials' => true, 'birth_dates_omitted' => true, 'applications' => array() );
			if ( ! $zip->addFromString( 'applications.csv', $csv ) || ! $zip->addFromString( 'README.txt', "CYWater Best Paper protected application package\n\nShare only with authorized award staff.\napplications.csv: application index; no birth dates or storage paths.\nmanifest.json: export index and file checksums.\napplications/application-ID/: application.json plus original uploaded paper and CV.\nFiles retain their uploaded contents and original filenames after safe filename normalization.\nEligibility and decisions reflect the export time. Scores are not included in this application package.\nA declaration or submitted application does not mean eligibility approval or an award.\n" ) ) {
				throw new RuntimeException( 'Could not write the export index.' );
			}
			foreach ( $apps as $app ) {
				$record = self::export_record( $app );
				$folder = 'applications/application-' . absint( $app['id'] ) . '/';
				foreach ( array( 'paper', 'cv' ) as $kind ) {
					if ( empty( $app['files'][ $kind ] ) ) {
						continue;
					}
					$path = CYWater_Best_Paper::file_path( $app, $kind );
					if ( is_wp_error( $path ) || ! is_string( $path ) || ! is_readable( $path ) ) {
						throw new RuntimeException( 'A protected file is unavailable. The incomplete package was discarded.' );
					}
					$original = sanitize_file_name( wp_basename( $app['files'][ $kind ]['original'] ?? $kind . '.pdf' ) );
					$entry = $folder . $kind . '/' . ( $original ?: $kind . '.pdf' );
					if ( ! $zip->addFile( $path, $entry ) ) {
						throw new RuntimeException( 'Could not add a protected file. The incomplete package was discarded.' );
					}
					$record['files'][ $kind ]['archive_path'] = $entry;
				}
				if ( ! $zip->addFromString( $folder . 'application.json', wp_json_encode( $record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) {
					throw new RuntimeException( 'Could not write an application record.' );
				}
				$manifest['applications'][] = array( 'application_id' => (int) $app['id'], 'record' => $folder . 'application.json', 'files' => $record['files'] );
			}
			if ( ! $zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) || ! $zip->close() ) {
				throw new RuntimeException( 'Could not finish the export archive.' );
			}
			$opened = false;
			$complete = true;
			return $temp;
		} catch ( Throwable $error ) {
			return new WP_Error( 'export_failed', 'The protected ZIP could not be completed. No incomplete package was delivered. Please check file availability and server storage.' );
		} finally {
			if ( $opened ) {
				$zip->close();
			}
			if ( ! $complete && is_file( $temp ) ) {
				unlink( $temp );
			}
		}
	}

	/** Separate from the applicant-material package to avoid accidental score sharing. */
	private static function review_csv( $award_id, $apps ) {
		if ( ! CYWater_Best_Paper::can_manage() ) {
			return new WP_Error( 'review_export_permission', 'Only a workflow administrator may export review records.' );
		}
		$stream = fopen( 'php://temp', 'w+' );
		if ( false === $stream ) {
			return new WP_Error( 'csv_stream', 'Could not prepare the review export.' );
		}
		$summary = CYWater_Best_Paper::score_summary( $award_id );
		fwrite( $stream, "\xEF\xBB\xBF" );
		fputcsv( $stream, array( 'award_id', 'application_id', 'title', 'eligibility', 'reviewer_id', 'reviewer', 'review_status', 'score', 'comments', 'updated_at_utc', 'submitted_score_count', 'application_mean', 'application_z_mean', 'decision' ), ',', '"', '' );
		foreach ( $apps as $app ) {
			$score = $summary[ $app['id'] ] ?? array();
			$reviews = self::review_rows( $app['id'] );
			if ( ! $reviews ) {
				$reviews = array( array( 'status' => 'not_assigned' ) );
			}
			foreach ( $reviews as $review ) {
				$row = array( $award_id, $app['id'], $app['record']['title'] ?? '', $app['status'], $review['reviewer_id'] ?? '', isset( $review['reviewer_id'] ) ? self::user_label( $review['reviewer_id'] ) : '', $review['status'], 'submitted' === $review['status'] ? ( $review['score'] ?? '' ) : '', $review['comments'] ?? '', $review['updated_at'] ?? '', $score['count'] ?? 0, $score['mean'] ?? 'N/A', $score['z_mean'] ?? 'N/A', $app['decision'] ?? 'none' );
				fputcsv( $stream, array_map( array( __CLASS__, 'csv_cell' ), $row ), ',', '"', '' );
			}
		}
		rewind( $stream );
		$result = stream_get_contents( $stream );
		fclose( $stream );
		return $result;
	}

	private static function download_headers( $filename, $type ) {
		nocache_headers();
		header( 'Content-Type: ' . $type );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	}
}
