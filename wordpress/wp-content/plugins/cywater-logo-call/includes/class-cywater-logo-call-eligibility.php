<?php
/**
 * Membership eligibility adapter. PMPro remains the sole membership authority.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Logo_Call_Eligibility {
	/**
	 * @return array<int,string> Active CYWater individual level IDs and slugs.
	 */
	public static function active_levels( $user_id ) {
		if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			return array();
		}

		$configured = (array) get_option( 'cywater_membership_level_ids', array() );
		$allowed    = array_filter(
			array(
				'student'      => absint( $configured['student'] ?? 0 ),
				'professional' => absint( $configured['professional'] ?? 0 ),
				'lifetime'     => absint( $configured['lifetime'] ?? 0 ),
			)
		);
		$active     = array();
		$now        = current_time( 'timestamp' );

		foreach ( (array) pmpro_getMembershipLevelsForUser( absint( $user_id ) ) as $level ) {
			$slug = array_search( (int) $level->id, $allowed, true );
			if ( false === $slug ) {
				continue;
			}
			$enddate = isset( $level->enddate ) ? (int) $level->enddate : 0;
			if ( 0 === $enddate || $enddate > $now ) {
				$active[ (int) $level->id ] = (string) $slug;
			}
		}

		return $active;
	}

	public static function can_submit( $user_id ) {
		return ! empty( self::active_levels( $user_id ) );
	}

	public static function can_vote( $user_id ) {
		return (bool) array_intersect( array( 'professional', 'lifetime' ), array_values( self::active_levels( $user_id ) ) );
	}
}

