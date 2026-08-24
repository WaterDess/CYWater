<?php
/**
 * Forum policy storage and the administrator settings screen.
 *
 * Values are read from the versioned defaults file and overridden by a single
 * stored option. No secret is read or written here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Forum_Settings {
	private const OPTION = 'cywater_forum_settings';

	/**
	 * Cached merged settings for the current request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $cache = null;

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		// Drop the per-request cache when the option changes, so anything that
		// reads a setting later in the same request sees the saved value.
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush_cache' ) );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		static $defaults = null;
		if ( null === $defaults ) {
			$defaults = (array) require CYWATER_FORUM_DIR . 'includes/defaults.php';
		}
		return $defaults;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$defaults = self::defaults();
			$stored   = self::is_locked() ? array() : (array) get_option( self::OPTION, array() );
			// Unknown stored keys are discarded so a renamed parameter cannot
			// linger and silently shadow a new default.
			self::$cache = array_merge( $defaults, array_intersect_key( $stored, $defaults ) );
		}
		return self::$cache;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	public static function is_enabled( $key ) {
		return (bool) self::get( $key );
	}

	public static function get_int( $key ) {
		return (int) self::get( $key );
	}

	public static function get_string( $key ) {
		return (string) self::get( $key );
	}

	/**
	 * Deploy-only mode. When the runtime defines this constant the settings
	 * screen becomes read-only and the versioned defaults are authoritative.
	 */
	public static function is_locked() {
		return defined( 'CYWATER_FORUM_LOCK_SETTINGS' ) && CYWATER_FORUM_LOCK_SETTINGS;
	}

	public static function flush_cache() {
		self::$cache = null;
	}

	public static function register_setting() {
		register_setting(
			'cywater_forum',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * @param mixed $input
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		if ( self::is_locked() ) {
			add_settings_error( self::OPTION, 'cywater_forum_locked', __( 'Forum policy is locked to the deployed defaults. No change was saved.', 'cywater-forum' ) );
			return (array) get_option( self::OPTION, array() );
		}

		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$clean    = array();

		foreach ( $defaults as $key => $default ) {
			if ( is_bool( $default ) ) {
				// An unchecked box is absent from the POST body entirely.
				$clean[ $key ] = ! empty( $input[ $key ] );
				continue;
			}
			if ( is_int( $default ) ) {
				$clean[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : $default;
				continue;
			}
			$clean[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $input[ $key ] ) ) : $default;
		}

		// A zero here would let an unendorsed stranger publish, and a zero
		// there would make nobody able to endorse. Neither is a policy the
		// settings screen should be able to express.
		$clean['endorsements_required']         = max( 1, $clean['endorsements_required'] );
		$clean['endorsement_articles_required'] = max( 1, $clean['endorsement_articles_required'] );
		$clean['endorsement_token_ttl_hours']   = max( 1, $clean['endorsement_token_ttl_hours'] );
		$clean['endorsement_requests_per_day']  = max( 1, $clean['endorsement_requests_per_day'] );
		$allowed_moderation_modes = array( 'auto', 'first', 'all' );
		if ( ! in_array( $clean['comments_moderation_mode'], $allowed_moderation_modes, true ) ) {
			$clean['comments_moderation_mode'] = 'auto';
		}

		return $clean;
	}

	public static function add_settings_page() {
		add_options_page(
			__( 'CYWater Forum', 'cywater-forum' ),
			__( 'CYWater Forum', 'cywater-forum' ),
			'manage_options',
			'cywater-forum',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$values   = self::all();
		$disabled = self::is_locked() ? ' disabled' : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CYWater Forum', 'cywater-forum' ); ?></h1>
			<?php if ( self::is_locked() ) : ?>
				<div class="notice notice-info"><p><?php esc_html_e( 'Policy is locked to the deployed defaults by CYWATER_FORUM_LOCK_SETTINGS. These values are read-only.', 'cywater-forum' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Article submission and discussion policy. Defaults are versioned in the plugin; values saved here override them.', 'cywater-forum' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'cywater_forum' ); ?>

				<h2><?php esc_html_e( 'Article participation', 'cywater-forum' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::checkbox_row( 'membership_required', __( 'Require active individual membership', 'cywater-forum' ), __( 'Only a verified account with an active Student, Professional or Lifetime membership may publish a Forum article.', 'cywater-forum' ), $values, $disabled );
					self::checkbox_row( 'endorsements_enabled', __( 'Enable legacy endorsement workflow', 'cywater-forum' ), __( 'Currently paused. When disabled, no invitation request or link is processed; historical records and implementation are retained.', 'cywater-forum' ), $values, $disabled );
					if ( ! empty( $values['endorsements_enabled'] ) ) {
						self::number_row( 'endorsement_articles_required', __( 'Articles required to endorse', 'cywater-forum' ), __( 'Published forum articles an author needs before they may endorse someone else.', 'cywater-forum' ), $values, $disabled );
						self::number_row( 'endorsements_required', __( 'Endorsements required to participate', 'cywater-forum' ), __( 'Endorsements a member must collect before publishing an article when the legacy endorsement workflow is enabled.', 'cywater-forum' ), $values, $disabled );
						self::checkbox_row( 'admin_override', __( 'Administrator override', 'cywater-forum' ), __( 'Allow an administrator to grant or revoke legacy endorsement status from the user editor.', 'cywater-forum' ), $values, $disabled );
						self::number_row( 'endorsement_requests_per_day', __( 'Endorsement requests per day', 'cywater-forum' ), __( 'Requests one candidate may send in 24 hours.', 'cywater-forum' ), $values, $disabled );
						self::number_row( 'endorsement_token_ttl_hours', __( 'Endorsement link lifetime (hours)', 'cywater-forum' ), __( 'How long an endorsement link stays valid. It is single-use regardless.', 'cywater-forum' ), $values, $disabled );
					}
					?>
				</table>

				<h2><?php esc_html_e( 'Discussion', 'cywater-forum' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::checkbox_row( 'comments_enabled', __( 'Enable questions and replies', 'cywater-forum' ), __( 'Global switch. Individual articles also carry the standard WordPress discussion checkbox, and both must be open.', 'cywater-forum' ), $values, $disabled );
					self::checkbox_row( 'comments_require_membership', __( 'Members only', 'cywater-forum' ), __( 'Restrict replies to signed-in members with an active membership.', 'cywater-forum' ), $values, $disabled );
					self::select_row(
						'comments_moderation_mode',
						__( 'Eligible member replies', 'cywater-forum' ),
						array(
							'auto'  => __( 'Publish immediately', 'cywater-forum' ),
							'first' => __( 'Hold the first reply', 'cywater-forum' ),
							'all'   => __( 'Hold every reply', 'cywater-forum' ),
						),
						__( 'The current policy publishes replies immediately after membership and email-verification checks.', 'cywater-forum' ),
						$values,
						$disabled
					);
					?>
				</table>

				<h2><?php esc_html_e( 'AI', 'cywater-forum' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Not implemented. These settings exist so the configuration contract is stable; no AI provider is connected and nothing is generated or sent anywhere.', 'cywater-forum' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					self::checkbox_row( 'ai_reaction_enabled', __( 'Per-viewer reactions', 'cywater-forum' ), __( 'When a provider is connected, show each viewer a labelled AI perspective under an article. Absent, failed, or over-budget responses render nothing at all.', 'cywater-forum' ), $values, $disabled );
					self::number_row( 'ai_daily_token_budget', __( 'Daily token budget', 'cywater-forum' ), __( 'Zero means unlimited. Above zero, the panel silently stops appearing once the ceiling is reached.', 'cywater-forum' ), $values, $disabled );
					self::number_row( 'ai_reaction_cache_minutes', __( 'Reaction cache (minutes)', 'cywater-forum' ), __( 'How long one viewer’s reaction is reused before another call would be made.', 'cywater-forum' ), $values, $disabled );
					self::checkbox_row( 'ai_comment_review_enabled', __( 'AI review of held replies', 'cywater-forum' ), __( 'When a provider is connected, let an AI pass look at a reply still awaiting moderation after the delay below. It can recommend approval; it never publishes on its own.', 'cywater-forum' ), $values, $disabled );
					self::number_row( 'ai_comment_review_delay_hours', __( 'Held-reply review delay (hours)', 'cywater-forum' ), __( 'How long a reply waits for a human before the review pass considers it.', 'cywater-forum' ), $values, $disabled );
					?>
				</table>

				<?php if ( ! self::is_locked() ) { submit_button(); } ?>
			</form>
		</div>
		<?php
	}

	private static function field_name( $key ) {
		return self::OPTION . '[' . $key . ']';
	}

	private static function checkbox_row( $key, $label, $description, $values, $disabled ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::field_name( $key ) ); ?>" value="1" <?php checked( ! empty( $values[ $key ] ) ); ?><?php echo esc_attr( $disabled ); ?> />
					<?php echo esc_html( $description ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	private static function number_row( $key, $label, $description, $values, $disabled ) {
		?>
		<tr>
			<th scope="row"><label for="cywater-forum-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="number" min="0" step="1" class="small-text" id="cywater-forum-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::field_name( $key ) ); ?>" value="<?php echo esc_attr( (string) ( $values[ $key ] ?? 0 ) ); ?>"<?php echo esc_attr( $disabled ); ?> />
				<p class="description"><?php echo esc_html( $description ); ?></p>
			</td>
		</tr>
		<?php
	}

	private static function select_row( $key, $label, $options, $description, $values, $disabled ) {
		?>
		<tr>
			<th scope="row"><label for="cywater-forum-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="cywater-forum-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::field_name( $key ) ); ?>"<?php echo esc_attr( $disabled ); ?>>
					<?php foreach ( $options as $value => $option_label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $values[ $key ] ?? '' ), $value ); ?>><?php echo esc_html( $option_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php echo esc_html( $description ); ?></p>
			</td>
		</tr>
		<?php
	}
}
