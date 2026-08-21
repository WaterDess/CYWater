import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { PHP } from "@php-wasm/universal";
import { loadNodeRuntime } from "@php-wasm/node";

const configSource = readFileSync(
  new URL("../wordpress/wp-content/plugins/cywater-environment/includes/class-cywater-config.php", import.meta.url),
  "utf8"
);
const adapterSource = readFileSync(
  new URL("../wordpress/wp-content/plugins/cywater-operations/includes/class-cywater-event-tickets-paid-adapter.php", import.meta.url),
  "utf8"
);

const phpSource = (source) => Buffer.from(source.replace(/^<\?php\s*/, ""), "utf8").toString("base64");

const scenarios = [
  {
    name: "staging-live-authorized",
    environment: "staging",
    mode: "live",
    approved: true,
    expected: false,
  },
  {
    name: "production-disabled-authorized",
    environment: "production",
    mode: "disabled",
    approved: true,
    expected: false,
  },
  {
    name: "production-live-not-authorized",
    environment: "production",
    mode: "live",
    approved: false,
    expected: false,
  },
  {
    name: "production-live-authorized",
    environment: "production",
    mode: "live",
    approved: true,
    expected: true,
  },
  {
    name: "missing-environment-plugin",
    environment: "production",
    mode: "live",
    approved: true,
    omitConfig: true,
    expected: false,
  },
];

async function exerciseScenario(scenario) {
  const php = new PHP(
    await loadNodeRuntime("8.3", {
      emscriptenOptions: { processId: scenarios.indexOf(scenario) + 1 },
    })
  );
  const configEval = scenario.omitConfig
    ? ""
    : `eval( base64_decode( '${phpSource(configSource)}' ) );`;
  const code = `<?php
define( 'ABSPATH', '/' );
define( 'CYWATER_PAYMENT_MODE', ${JSON.stringify(scenario.mode)} );
define( 'CYWATER_ALLOW_LIVE_PAYMENTS', ${scenario.approved ? "true" : "false"} );

function wp_get_environment_type() { return ${JSON.stringify(scenario.environment)}; }
function __( $message, $domain = '' ) { return $message; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\\-]/', '', (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/\\\\' ); }
function get_post_type( $post_id ) { return 7001 === (int) $post_id ? 'cyw_event' : ''; }
function get_post_status( $post_id ) { return in_array( (int) $post_id, array( 1001, 1002, 7001, 9001 ), true ) ? 'publish' : false; }
function get_posts( $args ) { return array( 9001 ); }
function tribe_tickets_get_event_ids( $ticket_id ) { return 9001 === (int) $ticket_id ? array( 7001 ) : array(); }
function tec_tickets_commerce_is_enabled() { return true; }
function wp_http_validate_url( $url ) { return false !== filter_var( $url, FILTER_VALIDATE_URL ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }

final class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = null ) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

final class WP_REST_Request {
    private $method;
    private $route;
    public function __construct( $method, $route ) { $this->method = $method; $this->route = $route; }
    public function get_method() { return $this->method; }
    public function get_route() { return $this->route; }
}

final class Tribe__Tickets__Main { const VERSION = '5.29.1'; }

eval( 'namespace TEC\\Tickets\\Commerce {
    class Cart { public function get_items_in_cart() { return array( array( "ticket_id" => 9001, "quantity" => 1 ) ); } }
    class Ticket {
        const POSTTYPE = "tec_tc_ticket";
        public $ID = 9001;
        public $price = "25.00";
        public function get_ticket( $ticket_id ) { return new self(); }
        public function managing_stock() { return true; }
        public function capacity() { return 50; }
        public function available() { return 25; }
        public function is_in_stock() { return true; }
        public function start_date( $utc = false ) { return new \\DateTimeImmutable( "2026-01-01T00:00:00+00:00" ); }
        public function end_date( $utc = false ) { return new \\DateTimeImmutable( "2027-12-31T23:59:59+00:00" ); }
        public function date_in_range() { return true; }
    }
    class Checkout { public function get_page_id() { return 1001; } public function get_url() { return "https://example.test/checkout/"; } }
    class Success { public function get_page_id() { return 1002; } public function get_url() { return "https://example.test/success/"; } }
}
namespace TEC\\Tickets\\Commerce\\Utils {
    class Currency { public function get_currency_code() { return "USD"; } }
}
namespace TEC\\Tickets\\Commerce\\Gateways\\Stripe {
    class Gateway {
        public static function is_enabled() { return true; }
        public static function is_connected() { return true; }
        public static function is_active() { return true; }
        public static function is_test_mode() { return false; }
    }
}' );

function tribe( $service ) {
    switch ( $service ) {
        case 'TEC\\Tickets\\Commerce\\Cart': return new TEC\\Tickets\\Commerce\\Cart();
        case 'TEC\\Tickets\\Commerce\\Ticket': return new TEC\\Tickets\\Commerce\\Ticket();
        case 'TEC\\Tickets\\Commerce\\Checkout': return new TEC\\Tickets\\Commerce\\Checkout();
        case 'TEC\\Tickets\\Commerce\\Success': return new TEC\\Tickets\\Commerce\\Success();
        case 'TEC\\Tickets\\Commerce\\Utils\\Currency': return new TEC\\Tickets\\Commerce\\Utils\\Currency();
        case 'tickets.handler':
            return new class { public function is_ticket_readable( $ticket_id ) { return 9001 === (int) $ticket_id; } };
    }
    return null;
}

final class CYWater_Paid_Event_Approval {
    public static function is_paid( $event_id ) { return 7001 === (int) $event_id; }
    public static function sanitize_currency( $currency ) { return 'USD' === strtoupper( (string) $currency ) ? 'USD' : ''; }
    public static function sanitize_amount( $amount ) { return '25.00' === (string) $amount ? '25.00' : ''; }
    public static function adapter_snapshot( $event_id ) {
        return array( 'provider' => 'event_tickets_commerce_stripe', 'ticket_id' => '9001' );
    }
    public static function is_payment_ready( $event_id ) { return 7001 === (int) $event_id; }
}

${configEval}
eval( base64_decode( '${phpSource(adapterSource)}' ) );

$adapter_reflection = new ReflectionClass( 'CYWater_Event_Tickets_Paid_Adapter' );
$live_method = $adapter_reflection->getMethod( 'live_payments_allowed' );
$purchase_method = $adapter_reflection->getMethod( 'validate_purchase' );

$adapter_gate = $live_method->invoke( null );
$config_gate = class_exists( 'CYWater_Config' ) ? CYWater_Config::live_payments_allowed() : null;
$snapshot = CYWater_Event_Tickets_Paid_Adapter::adapter_snapshot( null, 7001 );
$purchase = $purchase_method->invoke( null, 9001, 1 );
$cart_input = array( 'tickets' => array( array( 'ticket_id' => 9001, 'quantity' => 1 ) ) );
$cart_output = CYWater_Event_Tickets_Paid_Adapter::guard_cart_preparation( $cart_input );
$skip = CYWater_Event_Tickets_Paid_Adapter::skip_unready_checkout_item( false, array( 'ticket_id' => 9001, 'quantity' => 1 ) );
$request = new WP_REST_Request( 'POST', '/tribe/tickets/v1/commerce/stripe/order' );
$rest = CYWater_Event_Tickets_Paid_Adapter::guard_stripe_order_request( null, null, $request );

echo json_encode(
    array(
        'config_gate' => $config_gate,
        'adapter_gate' => $adapter_gate,
        'snapshot_error' => is_wp_error( $snapshot ) ? $snapshot->get_error_code() : null,
        'snapshot_ready' => is_array( $snapshot ) && true === ( $snapshot['checkout_ready'] ?? false ) && true === ( $snapshot['live_mode'] ?? false ),
        'purchase_error' => is_wp_error( $purchase ) ? $purchase->get_error_code() : null,
        'purchase_ready' => true === $purchase,
        'cart_preserved' => $cart_input === $cart_output,
        'cart_count' => count( $cart_output['tickets'] ?? array() ),
        'skip' => $skip,
        'rest_error' => is_wp_error( $rest ) ? $rest->get_error_code() : null,
        'rest_status' => is_wp_error( $rest ) ? (int) ( $rest->get_error_data()['status'] ?? 0 ) : null,
        'rest_allowed' => null === $rest,
    )
);`;

  const output = await php.runStream({ code });
  const stdout = await output.stdoutText;
  const stderr = await output.stderrText;
  const exitCode = await output.exitCode;
  assert.equal(exitCode, 0, `${scenario.name}: PHP harness failed: ${stderr}`);
  assert.equal(stderr, "", `${scenario.name}: PHP harness emitted stderr`);
  return JSON.parse(stdout);
}

for (const scenario of scenarios) {
  const result = await exerciseScenario(scenario);
  assert.equal(result.adapter_gate, scenario.expected, `${scenario.name}: adapter gate result`);
  if (scenario.omitConfig) {
    assert.equal(result.config_gate, null, `${scenario.name}: Environment class must be absent`);
  } else {
    assert.equal(result.config_gate, scenario.expected, `${scenario.name}: authoritative Environment gate result`);
  }

  if (scenario.expected) {
    assert.equal(result.snapshot_error, null, `${scenario.name}: runtime preflight should pass`);
    assert.equal(result.snapshot_ready, true, `${scenario.name}: reviewed live snapshot should be accepted`);
    assert.equal(result.purchase_error, null, `${scenario.name}: defense-in-depth purchase validation should pass`);
    assert.equal(result.purchase_ready, true, `${scenario.name}: purchase should reach ready state`);
    assert.equal(result.cart_preserved, true, `${scenario.name}: ready cart should remain intact`);
    assert.equal(result.skip, false, `${scenario.name}: ready checkout item should not be skipped`);
    assert.equal(result.rest_error, null, `${scenario.name}: ready Stripe order route should not be rejected`);
    assert.equal(result.rest_allowed, true, `${scenario.name}: ready Stripe order route should continue to Event Tickets`);
  } else {
    assert.equal(result.snapshot_error, "cywater_event_tickets_live_not_allowed", `${scenario.name}: runtime preflight must fail at the Live gate`);
    assert.equal(result.snapshot_ready, false, `${scenario.name}: no ready snapshot may escape`);
    assert.equal(result.purchase_error, "cywater_event_tickets_live_not_allowed", `${scenario.name}: purchase validation must fail at the Live gate`);
    assert.equal(result.purchase_ready, false, `${scenario.name}: purchase must remain closed`);
    assert.equal(result.cart_count, 0, `${scenario.name}: cart must be removed`);
    assert.equal(result.skip, true, `${scenario.name}: checkout item must be skipped`);
    assert.equal(result.rest_error, "cywater_paid_event_checkout_closed", `${scenario.name}: Stripe order route must fail closed`);
    assert.equal(result.rest_status, 403, `${scenario.name}: Stripe order route must return HTTP 403`);
    assert.equal(result.rest_allowed, false, `${scenario.name}: Stripe order route must not reach Event Tickets`);
  }
}

console.log(`CYWater paid-Event Live gate QA passed ${scenarios.length} isolated runtime scenarios.`);
