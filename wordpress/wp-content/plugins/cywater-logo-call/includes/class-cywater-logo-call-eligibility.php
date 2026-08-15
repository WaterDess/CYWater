<?php
/**
 * Event-scoped participation policy.
 *
 * WordPress owns accounts and PMPro remains the sole membership authority.
 * This adapter only decides whether an existing account matches an Event's
 * independently configured submission or voting audience.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Logo_Call_Eligibility {
	const AUDIENCE_REGISTERED        = 'registered';
	const AUDIENCE_ACTIVE_INDIVIDUAL = 'active_individual';
	const AUDIENCE_SELECTED_LEVELS   = 'selected_levels';

	/**
	 * @return array<string,string>
	 */
	public static function audiences() {
		return array(
			self::AUDIENCE_REGISTERED        => __( 'All registered users', 'cywater-logo-call' ),
			self::AUDIENCE_ACTIVE_INDIVIDUAL => __( 'All active individual members', 'cywater-logo-call' ),
			self::AUDIENCE_SELECTED_LEVELS   => __( 'Selected active membership levels', 'cywater-logo-call' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function level_options() {
		return array(
			'student'      => __( 'Student', 'cywater-logo-call' ),
			'professional' => __( 'Professional', 'cywater-logo-call' ),
			'lifetime'     => __( 'Lifetime', 'cywater-logo-call' ),
		);
	}

	/**
	 * @return array{audience:string,levels:array<int,string>}
	 */
	public static function policy( $event_id, $action ) {
		$action    = in_array( $action, array( 'submit', 'vote' ), true ) ? $action : 'submit';
		$audiences = self::audiences();
		$audience  = sanitize_key( (string) get_post_meta( absint( $event_id ), '_cywater_logo_call_' . $action . '_audience', true ) );
		if ( ! isset( $audiences[ $audience ] ) ) {
			$audience = self::AUDIENCE_REGISTERED;
		}

		$levels = array_map( 'sanitize_key', (array) get_post_meta( absint( $event_id ), '_cywater_logo_call_' . $action . '_levels', true ) );
		$levels = array_values( array_intersect( array_keys( self::level_options() ), $levels ) );

		return array(
			'audience' => $audience,
			'levels'   => $levels,
		);
	}

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

	public static function can( $action, $event_id, $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return false;
		}

		$policy = self::policy( $event_id, $action );
		if ( self::AUDIENCE_REGISTERED === $policy['audience'] ) {
			return true;
		}

		$active = array_values( self::active_levels( $user_id ) );
		if ( self::AUDIENCE_ACTIVE_INDIVIDUAL === $policy['audience'] ) {
			return ! empty( $active );
		}

		return ! empty( $policy['levels'] ) && (bool) array_intersect( $policy['levels'], $active );
	}

	public static function can_submit( $event_id, $user_id ) {
		return self::can( 'submit', $event_id, $user_id );
	}

	public static function can_vote( $event_id, $user_id ) {
		return self::can( 'vote', $event_id, $user_id );
	}

	public static function public_label( $event_id, $action ) {
		$policy = self::policy( $event_id, $action );
		if ( self::AUDIENCE_REGISTERED === $policy['audience'] ) {
			return __( 'registered user', 'cywater-logo-call' );
		}
		if ( self::AUDIENCE_ACTIVE_INDIVIDUAL === $policy['audience'] ) {
			return __( 'active Student, Professional or Lifetime member', 'cywater-logo-call' );
		}

		$labels = array_intersect_key( self::level_options(), array_flip( $policy['levels'] ) );
		return $labels
			? sprintf( __( 'active %s member', 'cywater-logo-call' ), implode( __( ' or ', 'cywater-logo-call' ), $labels ) )
			: __( 'user authorized by the event administrator', 'cywater-logo-call' );
	}
}
