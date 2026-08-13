<?php
/**
 * Self-cleaning staging QA for rolling annual CYWater membership terms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

if ( wp_get_environment_type() !== 'staging' ) {
	fwrite( STDERR, "This QA may run only in staging.\n" );
	exit( 1 );
}

$failures = array();
$checks   = 0;

$check = static function ( $condition, $message ) use ( &$failures, &$checks ) {
	++$checks;
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$ids          = (array) get_option( 'cywater_membership_level_ids', array() );
$student_id   = absint( $ids['student'] ?? 0 );
$professional = absint( $ids['professional'] ?? 0 );
$lifetime     = absint( $ids['lifetime'] ?? 0 );

$check( $student_id > 0 && $professional > 0 && $lifetime > 0, 'Expected CYWater membership levels were not found.' );

foreach ( array( $student_id, $professional ) as $level_id ) {
	$level = pmpro_getLevel( $level_id );
	$check( (float) $level->billing_amount === 0.0, 'Annual level must not create a recurring charge.' );
	$check( (int) $level->expiration_number === 1 && $level->expiration_period === 'Year', 'Annual level must be configured for one year.' );
}

$student_level = pmpro_getLevel( $student_id );
$expected      = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+1 year' )->format( 'Y-m-d' );
$start         = CYWater_Membership_Setup::rolling_annual_start( "'1999-01-01 00:00:00'", 0, $student_level );
$actual        = CYWater_Membership_Setup::rolling_annual_end( '2000-12-31 23:59:59', 0, $student_level, "'1999-01-01 00:00:00'" );
$check( trim( $start, "'" ) === current_time( 'mysql' ), 'Annual checkout did not start on the payment date.' );
$check( substr( $actual, 0, 10 ) === $expected, 'Annual checkout did not end one year after the payment date.' );
$check( substr( $actual, 11 ) === '23:59:59', 'Annual checkout did not end at the end of its final day.' );

$lifetime_level = pmpro_getLevel( $lifetime );
$unchanged      = CYWater_Membership_Setup::rolling_annual_end( 'NULL', 0, $lifetime_level, "'1999-01-01 00:00:00'" );
$check( $unchanged === 'NULL', 'Lifetime expiration was changed.' );

if ( $failures ) {
	echo wp_json_encode( array( 'status' => 'failed', 'checks' => $checks, 'failures' => $failures ), JSON_PRETTY_PRINT );
	exit( 1 );
}

echo wp_json_encode( array( 'status' => 'passed', 'checks' => $checks ), JSON_PRETTY_PRINT );
