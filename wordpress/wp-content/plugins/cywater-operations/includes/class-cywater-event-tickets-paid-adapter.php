<?php
/**
 * Fail-closed Event Tickets Commerce adapter for paid CYWater Events.
 *
 * This adapter intentionally targets the reviewed Event Tickets 5.29.1 API.
 * A future Event Tickets version must be reviewed before this class will allow
 * a paid Event to proceed. Free RSVP uses Event Tickets' separate RSVP module
 * and never passes through the Commerce-only hooks below.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CYWater_Event_Tickets_Paid_Adapter {
	private const TESTED_EVENT_TICKETS_VERSION = '5.29.1';
	private const PROVIDER                    = 'event_tickets_commerce_stripe';
	private const RELATION_META_KEY            = '_tec_tickets_commerce_event';
	private const STRIPE_ORDER_ROUTE           = '/tribe/tickets/v1/commerce/stripe/order';

	/** Register only read/gate hooks; this class never enables Commerce. */
	public static function register() {
		// Run before QA/test adapters so a deliberately supplied later snapshot
		// can still exercise the generic governance workflow in isolation.
		add_filter( 'cywater_operations_paid_event_adapter_snapshot', array( __CLASS__, 'adapter_snapshot' ), 5, 2 );

		add_filter( 'tec_tickets_commerce_is_ticket_restricted', array( __CLASS__, 'restrict_ticket' ), 20, 4 );
		add_filter( 'tec_tickets_commerce_cart_prepare_data', array( __CLASS__, 'guard_cart_preparation' ), PHP_INT_MAX );
		add_filter( 'tec_tickets_checkout_should_skip_item', array( __CLASS__, 'skip_unready_checkout_item' ), PHP_INT_MAX, 2 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'guard_stripe_order_request' ), 5, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
	}

	/**
	 * Build the deterministic Event Tickets snapshot used by governance.
	 *
	 * Version 1 supports exactly one published Commerce ticket per paid Event.
	 * This is deliberate: unknown, multiple, unlimited, inactive, test-mode or
	 * otherwise ambiguous configurations remain closed rather than guessed.
	 *
	 * @param mixed $snapshot Existing filter value.
	 * @param int   $event_id CYWater Event ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function adapter_snapshot( $snapshot, $event_id ) {
		$event_id = absint( $event_id );
		if ( ! $event_id || 'cyw_event' !== get_post_type( $event_id ) || ! CYWater_Paid_Event_Approval::is_paid( $event_id ) ) {
			return $snapshot;
		}

		try {
			$runtime = self::runtime_preflight();
			if ( is_wp_error( $runtime ) ) {
				return $runtime;
			}

			$ticket = self::event_ticket_configuration( $event_id );
			if ( is_wp_error( $ticket ) ) {
				return $ticket;
			}

			if ( $ticket['currency'] !== $runtime['currency'] ) {
				return self::error( 'cywater_event_tickets_currency_invalid' );
			}

			return array(
				'provider'        => self::PROVIDER,
				'ticket_id'       => (string) $ticket['ticket_id'],
				'enabled'         => true,
				'amount'          => $ticket['amount'],
				'currency'        => $ticket['currency'],
				'capacity'        => $ticket['capacity'],
				'checkout_ready'  => true,
				'sale_start'      => $ticket['sale_start'],
				'sale_end'        => $ticket['sale_end'],
				'checkout_page_id' => $runtime['checkout_page_id'],
				'success_page_id' => $runtime['success_page_id'],
				'live_mode'       => true,
			);
		} catch ( Throwable $throwable ) {
			// Never expose exception text or vendor data to the public/admin UI.
			return self::error( 'cywater_event_tickets_runtime_error' );
		}
	}

	/** Restrict a Commerce ticket unless its server-derived Event is ready. */
	public static function restrict_ticket( $restricted, $event_id, $ticket_id, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( $restricted ) {
			return true;
		}

		$event_id = absint( $event_id );
		$resolved = self::event_id_for_ticket( $ticket_id );
		if ( is_wp_error( $resolved ) ) {
			return true;
		}
		if ( 'cyw_event' !== get_post_type( $resolved ) ) {
			return true;
		}
		if ( $event_id && $event_id !== $resolved ) {
			return true;
		}

		return is_wp_error( self::validate_purchase( $ticket_id, 1 ) );
	}

	/**
	 * Remove the entire Commerce cart payload when any item is not ready.
	 * Event Tickets performs its normal validation after this filter; this is an
	 * additional governance gate, not a replacement cart or transaction engine.
	 *
	 * @param mixed $data Commerce cart preparation data.
	 * @return mixed
	 */
	public static function guard_cart_preparation( $data ) {
		if ( ! is_array( $data ) || ! array_key_exists( 'tickets', $data ) ) {
			return array();
		}
		if ( ! is_array( $data['tickets'] ) || ! $data['tickets'] ) {
			return array();
		}

		foreach ( $data['tickets'] as $item ) {
			$ticket_id = self::cart_item_ticket_id( $item );
			$quantity  = self::cart_item_quantity( $item );
			if ( ! $ticket_id || ! $quantity || is_wp_error( self::validate_purchase( $ticket_id, $quantity ) ) ) {
				return array();
			}
		}

		return $data;
	}

	/** Skip any checkout item whose exact server-side ticket cannot be approved. */
	public static function skip_unready_checkout_item( $should_skip, $item ) {
		if ( $should_skip ) {
			return true;
		}
		$ticket_id = self::cart_item_ticket_id( $item );
		if ( ! $ticket_id ) {
			// This filter is an Event Tickets Commerce item filter. The reviewed
			// 5.29.1 shape contains ticket_id; an unknown future shape is closed.
			return true;
		}
		$quantity = self::cart_item_quantity( $item );
		if ( ! $quantity ) {
			return true;
		}
		return is_wp_error( self::validate_purchase( $ticket_id, $quantity ) );
	}

	/**
	 * Final server-side gate for Event Tickets' Stripe order endpoint.
	 *
	 * Request parameters (including any event_id) are deliberately ignored. The
	 * ticket IDs are read from Event Tickets' server-side cart and each ticket's
	 * Event is resolved from the ticket relation maintained by Event Tickets.
	 */
	public static function guard_stripe_order_request( $response, $handler, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( null !== $response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		if ( 'POST' !== strtoupper( $request->get_method() ) || self::STRIPE_ORDER_ROUTE !== untrailingslashit( $request->get_route() ) ) {
			return $response;
		}

		try {
			if ( ! self::live_payments_allowed() ) {
				return self::rest_error();
			}
			if ( ! class_exists( 'TEC\\Tickets\\Commerce\\Cart' ) || ! function_exists( 'tribe' ) ) {
				return self::rest_error();
			}
			$cart = tribe( 'TEC\\Tickets\\Commerce\\Cart' );
			if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_items_in_cart' ) ) {
				return self::rest_error();
			}
			$items = $cart->get_items_in_cart();
			if ( ! is_array( $items ) || ! $items ) {
				return self::rest_error();
			}

			$seen = array();
			foreach ( $items as $item ) {
				$ticket_id = self::cart_item_ticket_id( $item );
				$quantity  = self::cart_item_quantity( $item );
				if ( ! $ticket_id || ! $quantity || isset( $seen[ $ticket_id ] ) ) {
					return self::rest_error();
				}
				$seen[ $ticket_id ] = true;
				if ( is_wp_error( self::validate_purchase( $ticket_id, $quantity ) ) ) {
					return self::rest_error();
				}
			}
		} catch ( Throwable $throwable ) {
			return self::rest_error();
		}

		return $response;
	}

	/** Show a fixed, non-sensitive reason while editing a paid Event. */
	public static function admin_notice() {
		if ( ! current_user_can( 'edit_cyw_events' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		$post_id = absint( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $screen || 'post' !== $screen->base || 'cyw_event' !== $screen->post_type || ! $post_id || ! CYWater_Paid_Event_Approval::is_paid( $post_id ) ) {
			return;
		}

		$result = CYWater_Paid_Event_Approval::adapter_snapshot( $post_id );
		if ( ! is_wp_error( $result ) ) {
			return;
		}
		$message = self::admin_error_message( $result->get_error_code() );
		?>
		<div class="notice notice-warning"><p><strong><?php esc_html_e( 'Paid Event gate closed:', 'cywater-operations' ); ?></strong> <?php echo esc_html( $message ); ?></p></div>
		<?php
	}

	/** @return array<string, mixed>|WP_Error */
	private static function runtime_preflight() {
		if ( ! self::live_payments_allowed() ) {
			return self::error( 'cywater_event_tickets_live_not_allowed' );
		}
		$required_classes = array(
			'Tribe__Tickets__Main',
			'TEC\\Tickets\\Commerce\\Ticket',
			'TEC\\Tickets\\Commerce\\Checkout',
			'TEC\\Tickets\\Commerce\\Success',
			'TEC\\Tickets\\Commerce\\Utils\\Currency',
			'TEC\\Tickets\\Commerce\\Gateways\\Stripe\\Gateway',
		);
		foreach ( $required_classes as $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				return self::error( 'cywater_event_tickets_unavailable' );
			}
		}
		if ( ! defined( 'Tribe__Tickets__Main::VERSION' ) || self::TESTED_EVENT_TICKETS_VERSION !== (string) constant( 'Tribe__Tickets__Main::VERSION' ) ) {
			return self::error( 'cywater_event_tickets_version_unreviewed' );
		}
		if ( ! function_exists( 'tribe' ) || ! function_exists( 'tec_tickets_commerce_is_enabled' ) || ! tec_tickets_commerce_is_enabled() ) {
			return self::error( 'cywater_event_tickets_commerce_disabled' );
		}

		$checkout = tribe( 'TEC\\Tickets\\Commerce\\Checkout' );
		$success  = tribe( 'TEC\\Tickets\\Commerce\\Success' );
		if ( ! is_object( $checkout ) || ! is_object( $success ) || ! method_exists( $checkout, 'get_page_id' ) || ! method_exists( $checkout, 'get_url' ) || ! method_exists( $success, 'get_page_id' ) || ! method_exists( $success, 'get_url' ) ) {
			return self::error( 'cywater_event_tickets_pages_invalid' );
		}
		$checkout_page_id = absint( $checkout->get_page_id() );
		$success_page_id  = absint( $success->get_page_id() );
		$checkout_url     = (string) $checkout->get_url();
		$success_url      = (string) $success->get_url();
		if ( ! $checkout_page_id || ! $success_page_id || $checkout_page_id === $success_page_id || 'publish' !== get_post_status( $checkout_page_id ) || 'publish' !== get_post_status( $success_page_id ) || ! self::is_https_url( $checkout_url ) || ! self::is_https_url( $success_url ) ) {
			return self::error( 'cywater_event_tickets_pages_invalid' );
		}

		$gateway_class = 'TEC\\Tickets\\Commerce\\Gateways\\Stripe\\Gateway';
		foreach ( array( 'is_enabled', 'is_connected', 'is_active', 'is_test_mode' ) as $method ) {
			if ( ! is_callable( array( $gateway_class, $method ) ) ) {
				return self::error( 'cywater_event_tickets_stripe_invalid' );
			}
		}
		if ( ! call_user_func( array( $gateway_class, 'is_enabled' ) ) ) {
			return self::error( 'cywater_event_tickets_stripe_disabled' );
		}
		if ( ! call_user_func( array( $gateway_class, 'is_connected' ) ) ) {
			return self::error( 'cywater_event_tickets_stripe_disconnected' );
		}
		// Event Tickets itself excludes an inactive gateway from checkout even
		// when it is enabled and connected (for example, when the account or
		// currency is not currently eligible). Governance must use that same
		// availability boundary instead of approving a checkout that cannot run.
		if ( ! call_user_func( array( $gateway_class, 'is_active' ) ) ) {
			return self::error( 'cywater_event_tickets_stripe_inactive' );
		}
		if ( call_user_func( array( $gateway_class, 'is_test_mode' ) ) ) {
			return self::error( 'cywater_event_tickets_stripe_test_mode' );
		}

		$currency_service = tribe( 'TEC\\Tickets\\Commerce\\Utils\\Currency' );
		if ( ! is_object( $currency_service ) || ! method_exists( $currency_service, 'get_currency_code' ) ) {
			return self::error( 'cywater_event_tickets_currency_invalid' );
		}
		$currency = CYWater_Paid_Event_Approval::sanitize_currency( $currency_service->get_currency_code() );
		if ( ! $currency ) {
			return self::error( 'cywater_event_tickets_currency_invalid' );
		}

		return array(
			'currency'         => $currency,
			'checkout_page_id' => $checkout_page_id,
			'success_page_id'  => $success_page_id,
		);
	}

	/** @return array<string, mixed>|WP_Error */
	private static function event_ticket_configuration( $event_id ) {
		if ( 'publish' !== get_post_status( $event_id ) ) {
			return self::error( 'cywater_event_tickets_event_unreadable' );
		}

		$ticket_class = 'TEC\\Tickets\\Commerce\\Ticket';
		if ( ! defined( $ticket_class . '::POSTTYPE' ) ) {
			return self::error( 'cywater_event_tickets_unavailable' );
		}
		$ticket_post_type = (string) constant( $ticket_class . '::POSTTYPE' );
		if ( ! $ticket_post_type ) {
			return self::error( 'cywater_event_tickets_unavailable' );
		}

		$ticket_ids = get_posts(
			array(
				'post_type'              => $ticket_post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 2,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => self::RELATION_META_KEY,
						'value'   => (string) absint( $event_id ),
						'compare' => '=',
					),
				),
			)
		);
		$ticket_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ticket_ids ) ) ) );
		if ( 1 !== count( $ticket_ids ) ) {
			return self::error( 'cywater_event_tickets_ticket_count' );
		}
		$ticket_id = $ticket_ids[0];
		$resolved  = self::event_id_for_ticket( $ticket_id );
		if ( is_wp_error( $resolved ) || (int) $resolved !== (int) $event_id ) {
			return self::error( 'cywater_event_tickets_relation_invalid' );
		}

		$repository = tribe( $ticket_class );
		if ( ! is_object( $repository ) || ! method_exists( $repository, 'get_ticket' ) ) {
			return self::error( 'cywater_event_tickets_unavailable' );
		}
		$ticket = $repository->get_ticket( $ticket_id );
		if ( ! is_object( $ticket ) || absint( $ticket->ID ?? 0 ) !== $ticket_id ) {
			return self::error( 'cywater_event_tickets_ticket_invalid' );
		}
		$handler = tribe( 'tickets.handler' );
		if ( ! is_object( $handler ) || ! method_exists( $handler, 'is_ticket_readable' ) || true !== $handler->is_ticket_readable( $ticket_id ) ) {
			return self::error( 'cywater_event_tickets_ticket_unreadable' );
		}

		$raw_price = isset( $ticket->price ) && is_scalar( $ticket->price ) ? trim( (string) $ticket->price ) : '';
		if ( ! preg_match( '/^\d+(?:\.\d+)?$/', $raw_price ) || ! is_finite( (float) $raw_price ) ) {
			return self::error( 'cywater_event_tickets_price_invalid' );
		}
		$amount = CYWater_Paid_Event_Approval::sanitize_amount( $raw_price );
		if ( ! $amount ) {
			return self::error( 'cywater_event_tickets_price_invalid' );
		}

		foreach ( array( 'managing_stock', 'capacity', 'available', 'is_in_stock', 'start_date', 'end_date', 'date_in_range' ) as $method ) {
			if ( ! method_exists( $ticket, $method ) ) {
				return self::error( 'cywater_event_tickets_ticket_invalid' );
			}
		}
		if ( true !== $ticket->managing_stock() ) {
			return self::error( 'cywater_event_tickets_capacity_invalid' );
		}
		$capacity = self::positive_integer( $ticket->capacity() );
		$available = self::nonnegative_integer( $ticket->available() );
		if ( ! $capacity || false === $available || $available > $capacity ) {
			return self::error( 'cywater_event_tickets_capacity_invalid' );
		}
		if ( 0 === $available || ! $ticket->is_in_stock() ) {
			return self::error( 'cywater_event_tickets_sold_out' );
		}

		$start = $ticket->start_date( false );
		$end   = $ticket->end_date( false );
		if ( ! $start instanceof DateTimeInterface || ! $end instanceof DateTimeInterface || $start->getTimestamp() >= $end->getTimestamp() || ! $ticket->date_in_range() ) {
			return self::error( 'cywater_event_tickets_sales_period_invalid' );
		}

		$runtime = self::runtime_currency_only();
		if ( is_wp_error( $runtime ) ) {
			return $runtime;
		}

		return array(
			'ticket_id'  => $ticket_id,
			'amount'     => $amount,
			'currency'   => $runtime,
			'capacity'   => $capacity,
			'available'  => $available,
			'sale_start' => $start->format( DATE_ATOM ),
			'sale_end'   => $end->format( DATE_ATOM ),
		);
	}

	/** Resolve the Event only from Event Tickets' server-maintained relation. */
	private static function event_id_for_ticket( $ticket_id ) {
		$ticket_id = absint( $ticket_id );
		if ( ! $ticket_id || ! function_exists( 'tribe_tickets_get_event_ids' ) ) {
			return self::error( 'cywater_event_tickets_relation_invalid' );
		}
		$event_ids = tribe_tickets_get_event_ids( $ticket_id );
		$event_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $event_ids ) ) ) );
		if ( 1 !== count( $event_ids ) ) {
			return self::error( 'cywater_event_tickets_relation_invalid' );
		}
		return $event_ids[0];
	}

	/** @return true|WP_Error */
	private static function validate_purchase( $ticket_id, $quantity ) {
		if ( ! self::live_payments_allowed() ) {
			return self::error( 'cywater_event_tickets_live_not_allowed' );
		}
		$ticket_id = absint( $ticket_id );
		$quantity  = self::positive_integer( $quantity );
		if ( ! $ticket_id || ! $quantity ) {
			return self::error( 'cywater_event_tickets_cart_invalid' );
		}
		$event_id = self::event_id_for_ticket( $ticket_id );
		if ( is_wp_error( $event_id ) || 'cyw_event' !== get_post_type( $event_id ) || ! CYWater_Paid_Event_Approval::is_paid( $event_id ) ) {
			return self::error( 'cywater_event_tickets_relation_invalid' );
		}

		$snapshot = CYWater_Paid_Event_Approval::adapter_snapshot( $event_id );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		if ( self::PROVIDER !== ( $snapshot['provider'] ?? '' ) || (string) $ticket_id !== (string) ( $snapshot['ticket_id'] ?? '' ) || ! CYWater_Paid_Event_Approval::is_payment_ready( $event_id ) ) {
			return self::error( 'cywater_event_tickets_event_not_ready' );
		}

		$configuration = self::event_ticket_configuration( $event_id );
		if ( is_wp_error( $configuration ) || (int) $configuration['ticket_id'] !== $ticket_id || $quantity > (int) $configuration['available'] ) {
			return self::error( 'cywater_event_tickets_capacity_invalid' );
		}
		return true;
	}

	/** Return current Commerce currency without making any configuration change. */
	private static function runtime_currency_only() {
		$class = 'TEC\\Tickets\\Commerce\\Utils\\Currency';
		if ( ! class_exists( $class ) || ! function_exists( 'tribe' ) ) {
			return self::error( 'cywater_event_tickets_currency_invalid' );
		}
		$service = tribe( $class );
		if ( ! is_object( $service ) || ! method_exists( $service, 'get_currency_code' ) ) {
			return self::error( 'cywater_event_tickets_currency_invalid' );
		}
		$currency = CYWater_Paid_Event_Approval::sanitize_currency( $service->get_currency_code() );
		return $currency ?: self::error( 'cywater_event_tickets_currency_invalid' );
	}

	private static function cart_item_ticket_id( $item ) {
		if ( is_array( $item ) && array_key_exists( 'ticket_id', $item ) ) {
			return absint( $item['ticket_id'] );
		}
		if ( is_object( $item ) && isset( $item->ticket_id ) ) {
			return absint( $item->ticket_id );
		}
		return 0;
	}

	private static function cart_item_quantity( $item ) {
		if ( is_array( $item ) && array_key_exists( 'quantity', $item ) ) {
			return self::positive_integer( $item['quantity'] );
		}
		if ( is_object( $item ) && isset( $item->quantity ) ) {
			return self::positive_integer( $item->quantity );
		}
		return 0;
	}

	/** Strict positive integer parser; no partial numeric strings are accepted. */
	private static function positive_integer( $value ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^[1-9]\d*$/', (string) $value ) ) {
			return 0;
		}
		$value = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		return false === $value ? 0 : (int) $value;
	}

	/** Strict non-negative integer parser. */
	private static function nonnegative_integer( $value ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^\d+$/', (string) $value ) ) {
			return false;
		}
		$value = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
		return false === $value ? false : (int) $value;
	}

	private static function is_https_url( $url ) {
		return is_string( $url ) && wp_http_validate_url( $url ) && 'https' === strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	}

	/**
	 * Compose Event Tickets with CYWater's one authoritative Live-payment gate.
	 * Missing or older Environment code is closed rather than guessed.
	 */
	private static function live_payments_allowed() {
		return class_exists( 'CYWater_Config' )
			&& is_callable( array( 'CYWater_Config', 'live_payments_allowed' ) )
			&& true === CYWater_Config::live_payments_allowed();
	}

	private static function rest_error() {
		return new WP_Error(
			'cywater_paid_event_checkout_closed',
			__( 'Paid Event checkout is not available. No order was created.', 'cywater-operations' ),
			array( 'status' => 403 )
		);
	}

	private static function error( $code ) {
		return new WP_Error( sanitize_key( $code ), __( 'Paid Event checkout is not ready.', 'cywater-operations' ) );
	}

	private static function admin_error_message( $code ) {
		$messages = array(
			'cywater_event_tickets_live_not_allowed'            => __( 'Live Event payments require the production environment, Live payment mode, and the explicit CYWater Live-payment authorization.', 'cywater-operations' ),
			'adapter_terms_mismatch'                         => __( 'The ticket amount or currency does not match the Event terms.', 'cywater-operations' ),
			'cywater_event_tickets_ticket_count'             => __( 'Create exactly one published Event Tickets Commerce ticket for this paid Event.', 'cywater-operations' ),
			'cywater_event_tickets_capacity_invalid'         => __( 'Use a finite managed ticket capacity greater than zero and keep enough capacity for the requested cart.', 'cywater-operations' ),
			'cywater_event_tickets_sold_out'                 => __( 'The managed ticket capacity has no remaining availability.', 'cywater-operations' ),
			'cywater_event_tickets_sales_period_invalid'     => __( 'Set a readable ticket sales start and end time and keep the current time inside that period.', 'cywater-operations' ),
			'cywater_event_tickets_price_invalid'            => __( 'Set one readable positive Commerce ticket price.', 'cywater-operations' ),
			'cywater_event_tickets_event_unreadable'          => __( 'Publish the CYWater Event before opening paid registration.', 'cywater-operations' ),
			'cywater_event_tickets_ticket_unreadable'         => __( 'Publish one publicly readable Event Tickets Commerce ticket for this Event.', 'cywater-operations' ),
			'cywater_event_tickets_currency_invalid'         => __( 'Set one valid three-letter Commerce currency matching the Event terms.', 'cywater-operations' ),
			'cywater_event_tickets_pages_invalid'            => __( 'Publish distinct HTTPS Commerce checkout and success pages.', 'cywater-operations' ),
			'cywater_event_tickets_commerce_disabled'        => __( 'Event Tickets Commerce is not enabled.', 'cywater-operations' ),
			'cywater_event_tickets_stripe_disabled'          => __( 'The Event Tickets Stripe gateway is not enabled.', 'cywater-operations' ),
			'cywater_event_tickets_stripe_disconnected'      => __( 'The Event Tickets Stripe gateway is not connected.', 'cywater-operations' ),
			'cywater_event_tickets_stripe_inactive'          => __( 'The Event Tickets Stripe gateway is not currently available at checkout.', 'cywater-operations' ),
			'cywater_event_tickets_stripe_test_mode'         => __( 'The Event Tickets Stripe gateway is still in test mode.', 'cywater-operations' ),
			'cywater_event_tickets_version_unreviewed'       => __( 'The installed Event Tickets version has not been reviewed for paid CYWater Events.', 'cywater-operations' ),
		);
		return $messages[ $code ] ?? __( 'The reviewed Event Tickets Commerce configuration is unavailable or incomplete.', 'cywater-operations' );
	}
}
