<?php
/**
 * Setup orchestration for CLI and wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Setup {
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_cywater_run_setup', array( __CLASS__, 'handle_admin_setup' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'cywater setup', array( __CLASS__, 'cli_setup' ) );
		}
	}

	public static function run( $force_import = false ) {
		$importer = new CYWater_Importer( '', $force_import );
		$report   = $importer->run();
		$pages    = $report['pages'];

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', absint( $pages['home'] ?? 0 ) );
		update_option( 'page_for_posts', absint( $pages['news'] ?? 0 ) );
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( 'timezone_string', 'America/Chicago' );
		update_option( 'blogname', 'CYWater' );
		update_option( 'blogdescription', 'International Association of Contemporary Young Scholars in Water Sciences' );

		do_action( 'cywater_after_core_setup', $report );
		flush_rewrite_rules();
		update_option( 'cywater_core_setup_version', CYWATER_CORE_VERSION );
		return $report;
	}

	public static function admin_menu() {
		add_management_page( 'CYWater setup', 'CYWater setup', 'manage_options', 'cywater-setup', array( __CLASS__, 'render_admin_page' ) );
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>CYWater setup</h1>
			<p>This imports verified static content, creates required pages, and asks installed CYWater modules to create their own local configuration. Existing imported posts and their metadata are preserved so later setup runs cannot overwrite editorial changes.</p>
			<p><strong>Current setup version:</strong> <?php echo esc_html( get_option( 'cywater_core_setup_version', 'Not run' ) ); ?></p>
			<?php if ( isset( $_GET['cywater_setup'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><div class="notice notice-success"><p>CYWater setup completed.</p></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cywater_run_setup">
				<?php wp_nonce_field( 'cywater_run_setup' ); ?>
				<?php submit_button( 'Run or update local setup' ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_admin_setup() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run CYWater setup.', 'cywater-core' ) );
		}
		check_admin_referer( 'cywater_run_setup' );
		self::run();
		wp_safe_redirect( add_query_arg( 'cywater_setup', 'done', admin_url( 'tools.php?page=cywater-setup' ) ) );
		exit;
	}

	public static function cli_setup( $args, $assoc_args ) {
		try {
			$force  = isset( $assoc_args['force-import'] );
			$report = self::run( $force );
			WP_CLI::success( 'CYWater setup complete: ' . wp_json_encode( $report ) );
		} catch ( Throwable $error ) {
			WP_CLI::error( $error->getMessage() );
		}
	}
}
