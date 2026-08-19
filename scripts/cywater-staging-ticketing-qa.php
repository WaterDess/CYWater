<?php
/**
 * Self-cleaning Event Tickets integration probe for CYWater staging.
 *
 * Run only through WP-CLI:
 *   wp eval-file scripts/cywater-staging-ticketing-qa.php
 *
 * The probe creates a CYWater event, a free RSVP ticket, and one test
 * attendee. Every record is force-deleted in finally. Set
 * CYWATER_TICKETING_QA_EMAIL=1 to exercise the real transactional-mail path
 * with the association web address; the default never sends mail.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
if ( 'staging.cywater.org' !== $site_host ) {
	WP_CLI::error( 'Refusing to run: this ticketing probe is restricted to staging.cywater.org.' );
}

$created_ids = array();
$keep_fixture = '1' === getenv( 'CYWATER_TICKETING_QA_KEEP' );
$send_email   = '1' === getenv( 'CYWATER_TICKETING_QA_EMAIL' );
$results     = array(
	'site'        => $site_host,
	'integration' => array(),
	'permissions' => array(),
	'lifecycle'   => array(),
);

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

try {
	$assert( class_exists( 'Tribe__Tickets__RSVP' ), 'Event Tickets RSVP provider is unavailable.' );
	$assert( class_exists( 'Tribe__Tickets__Ticket_Object' ), 'Event Tickets ticket object is unavailable.' );

	$supported_types = array_values( apply_filters( 'tribe_tickets_post_types', array( 'page', 'tribe_events' ) ) );
	$assert( array( 'cyw_event' ) === $supported_types, 'Ticketing is not isolated to cyw_event.' );
	$results['integration']['supported_post_types'] = $supported_types;

	$editor = get_role( 'editor' );
	$assert( $editor instanceof WP_Role, 'The built-in Editor role is unavailable.' );
	foreach ( array( 'edit_posts', 'edit_others_posts', 'publish_posts', 'delete_others_posts', 'upload_files' ) as $capability ) {
		$assert( $editor->has_cap( $capability ), "Editor is missing {$capability}." );
	}
	foreach ( array( 'manage_options', 'install_plugins', 'promote_users' ) as $capability ) {
		$assert( ! $editor->has_cap( $capability ), "Editor unexpectedly has {$capability}." );
	}
	$results['permissions']['editor'] = 'content-management-only: pass';

	$event_id = wp_insert_post(
		array(
			'post_type'    => 'cyw_event',
			'post_status'  => 'publish',
			'post_title'   => 'CYWater ticketing QA event',
			'post_content' => 'Temporary ticketing acceptance record. It must be deleted automatically.',
			'post_excerpt' => 'Temporary ticketing QA record.',
		),
		true
	);
	$assert( ! is_wp_error( $event_id ), 'Unable to create the draft event.' );
	$created_ids[] = (int) $event_id;

	$provider = Tribe__Tickets__RSVP::get_instance();
	$ticket   = new Tribe__Tickets__Ticket_Object(
		array(
			'name'             => 'CYWater QA RSVP',
			'description'      => 'Temporary free RSVP used for staging acceptance.',
			'show_description' => true,
			'price'            => 0,
			'capacity'         => 3,
		)
	);
	$ticket_id = $provider->save_ticket(
		$event_id,
		$ticket,
		array(
			'tribe-ticket' => array(
				'capacity' => 3,
				'stock'    => 3,
			),
		)
	);
	$assert( is_numeric( $ticket_id ) && $ticket_id > 0, 'Unable to create the RSVP ticket.' );
	$created_ids[] = (int) $ticket_id;

	$saved_ticket = $provider->get_ticket( $event_id, $ticket_id );
	$assert( $saved_ticket instanceof Tribe__Tickets__Ticket_Object, 'Saved RSVP ticket is unreadable.' );
	$assert( 3 === (int) $saved_ticket->capacity, 'RSVP capacity did not persist.' );

	$attendee_id = $provider->create_attendee_for_ticket(
		$saved_ticket,
		array(
			'full_name'       => 'CYWater Ticketing QA',
			'email'           => $send_email ? 'web@cywater.org' : 'ticketing-qa@example.invalid',
			'attendee_status' => 'going',
			'optout'          => true,
		)
	);
	$assert( is_numeric( $attendee_id ) && $attendee_id > 0, 'Unable to create the RSVP attendee.' );
	$created_ids[] = (int) $attendee_id;

	$attendees = $provider->get_attendees_by_id( $event_id );
	$assert( 1 === count( $attendees ), 'The event attendee report did not return the QA attendee.' );
	if ( $send_email ) {
		$rsvp_email = tribe( \TEC\Tickets\Emails\Email\RSVP::class );
		$assert( tec_tickets_emails_is_enabled(), 'Event Tickets email system is disabled.' );
		$assert( $rsvp_email->is_enabled(), 'Event Tickets RSVP confirmation template is disabled.' );
		$mail_failure = '';
		$mail_trace   = array( 'wp_mail_called' => false );
		add_filter(
			'tec_tickets_emails_dispatcher_to',
			static function ( $value ) use ( &$mail_trace ) {
				$mail_trace['to'] = null === $value ? 'null' : 'set';
				return $value;
			}
		);
		add_filter(
			'tec_tickets_emails_dispatcher_subject',
			static function ( $value ) use ( &$mail_trace ) {
				$mail_trace['subject'] = null === $value ? 'null' : 'set';
				return $value;
			}
		);
		add_filter(
			'tec_tickets_emails_dispatcher_content',
			static function ( $value ) use ( &$mail_trace ) {
				$mail_trace['content'] = null === $value ? 'null' : 'set';
				return $value;
			}
		);
		add_filter(
			'pre_wp_mail',
			static function ( $return ) use ( &$mail_trace ) {
				$mail_trace['wp_mail_called'] = true;
				return $return;
			}
		);
		add_action(
			'wp_mail_failed',
			static function ( $error ) use ( &$mail_failure ) {
				if ( $error instanceof WP_Error ) {
					$mail_failure = $error->get_error_message();
				}
			}
		);
		$order_id = (string) get_post_meta( $attendee_id, $provider->order_key, true );
		$assert( '' !== $order_id, 'The RSVP attendee is missing its order ID.' );
		$assert( 1 === count( $provider->get_attendees_by_order_id( $order_id ) ), 'The RSVP order does not resolve to its attendee.' );
		$assert( empty( $attendees[0]['ticket_sent'] ), 'The new RSVP attendee was already marked as emailed.' );
		$provider->send_tickets_email( $order_id, $event_id );
		$assert(
			(bool) get_post_meta( $attendee_id, Tribe__Tickets__RSVP::ATTENDEE_TICKET_SENT, true ),
			'Event Tickets confirmation mail was rejected: ' . ( $mail_failure ?: wp_json_encode( $mail_trace ) )
		);
		$results['lifecycle']['confirmation_email'] = 'accepted by the configured WordPress mail transport: pass';
	}

	$response = wp_remote_get(
		get_permalink( $event_id ),
		array(
			'timeout'     => 15,
			'redirection' => 0,
		)
	);
	$assert( ! is_wp_error( $response ), 'The ticketed event page request failed.' );
	$assert( 200 === (int) wp_remote_retrieve_response_code( $response ), 'The ticketed event page did not return HTTP 200.' );
	$body = (string) wp_remote_retrieve_body( $response );
	$assert( false !== strpos( $body, 'CYWater QA RSVP' ), 'The RSVP ticket is missing from the event page.' );
	$assert( false !== strpos( $body, 'tribe-tickets' ), 'Event Tickets frontend markup is missing.' );
	if ( $keep_fixture ) {
		$results['fixture'] = array(
			'event_id'    => (int) $event_id,
			'ticket_id'   => (int) $ticket_id,
			'attendee_id' => (int) $attendee_id,
			'url'         => get_permalink( $event_id ),
		);
	}
	$results['lifecycle'] = array_merge(
		$results['lifecycle'],
		array(
			'attendee_report' => 'pass',
			'event_page'      => 'HTTP 200 with ticket form: pass',
			'rsvp_capacity'   => 'pass',
			'rsvp_ticket'     => 'pass',
		)
	);
} catch ( Throwable $error ) {
	WP_CLI::warning( $error->getMessage() );
} finally {
	if ( ! $keep_fixture || empty( $results['lifecycle'] ) ) {
		foreach ( array_reverse( array_unique( array_map( 'intval', $created_ids ) ) ) as $post_id ) {
			if ( get_post( $post_id ) ) {
				wp_delete_post( $post_id, true );
			}
		}
	}

	$remaining = array_values(
		array_filter(
			$created_ids,
			static function ( $post_id ) {
				return null !== get_post( $post_id );
			}
		)
	);
	$results['cleanup'] = empty( $remaining ) ? 'pass' : ( $keep_fixture ? 'deferred-for-browser-qa' : 'failed' );

	WP_CLI::log( wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	if ( ( ! $keep_fixture && ! empty( $remaining ) ) || empty( $results['lifecycle'] ) ) {
		WP_CLI::error( 'CYWater ticketing acceptance failed.' );
	}
	WP_CLI::success( 'CYWater ticketing acceptance passed.' );
}
