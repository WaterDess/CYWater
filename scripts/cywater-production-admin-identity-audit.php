<?php
/** Read-only production audit for explicit WordPress account identity labels. */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$assertions = array();
$assert     = static function ( $condition, $message ) use ( &$assertions ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$assertions[] = $message;
};

try {
	$assert( 'production' === wp_get_environment_type(), 'WordPress environment is production' );
	$assert( 'cywater.org' === strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ), 'Home URL is the CYWater production host' );
	$assert( defined( 'CYWATER_MEMBERSHIP_VERSION' ) && '0.9.14' === CYWATER_MEMBERSHIP_VERSION, 'Membership identity presentation version is active' );
	$assert( defined( 'CYWATER_OPERATIONS_VERSION' ) && '0.3.5' === CYWATER_OPERATIONS_VERSION, 'Operations Users-table ordering version is active' );

	$columns = CYWater_Membership_Admin::add_user_columns(
		array(
			'username' => 'Username',
			'name'     => 'Name',
			'email'    => 'Email',
		)
	);
	$assert( 'Public display name' === (string) ( $columns['name'] ?? '' ), 'The display_name column has an explicit public-display label' );
	$assert( 'First / last name' === (string) ( $columns['cywater_personal_name'] ?? '' ), 'The standard first_name and last_name metadata have a dedicated column' );

	$sample = get_users( array( 'number' => 1, 'fields' => 'all' ) );
	$assert( ! empty( $sample ) && $sample[0] instanceof WP_User, 'A read-only sample account is available' );
	$html = CYWater_Membership_Admin::render_user_column( '', 'cywater_personal_name', $sample[0]->ID );
	$assert( false !== strpos( $html, 'First:' ) && false !== strpos( $html, 'Last:' ), 'The name-parts column reads both existing metadata fields' );
	$owner = get_user_by( 'email', 'web@cywater.org' );
	$assert( $owner instanceof WP_User && CYWater_Membership_Fields::is_platform_owner_account( $owner ), 'The platform Owner account is identified by its association-controlled email' );
	$owner_html = CYWater_Membership_Admin::render_user_column( '', 'cywater_personal_name', $owner->ID );
	$assert( false !== strpos( $owner_html, 'Owner account' ) && false !== strpos( $owner_html, 'not required' ), 'The Users table does not mislabel the platform Owner as an incomplete personal profile' );

	WP_CLI::success( sprintf( 'CYWater production administrator identity audit passed %d read-only assertions. No user record was changed.', count( $assertions ) ) );
} catch ( Throwable $error ) {
	WP_CLI::error( $error->getMessage() );
}
