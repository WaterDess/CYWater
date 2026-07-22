import assert from "node:assert/strict";
import { runCLI } from "@wp-playground/cli";

const mounts = [
  ["./wordpress/wp-content/themes/cywater", "/wordpress/wp-content/themes/cywater"],
  ["./wordpress/wp-content/plugins/cywater-core", "/wordpress/wp-content/plugins/cywater-core"],
  ["./wordpress/wp-content/plugins/cywater-membership", "/wordpress/wp-content/plugins/cywater-membership"],
  ["./wordpress/wp-content/plugins/cywater-environment", "/wordpress/wp-content/plugins/cywater-environment"],
  ["./wordpress/runtime/vendor/paid-memberships-pro", "/wordpress/wp-content/plugins/paid-memberships-pro"],
].map(([hostPath, vfsPath]) => ({ hostPath, vfsPath }));

const server = await runCLI({
  command: "server",
  port: 8891,
  php: "8.3",
  wp: "7.0.2",
  debug: true,
  "mount-before-install": mounts,
  blueprint: "./wordpress/blueprint.json",
});

try {
  for (const route of ["/", "/about/", "/news/", "/events/", "/awards/", "/membership/", "/contact/", "/members/"]) {
    const response = await fetch(new URL(route, server.serverUrl));
    assert.equal(response.status, 200, `${route} should return HTTP 200`);
    const html = await response.text();
    assert.doesNotMatch(html, /Fatal error|Parse error|Warning:/, `${route} should not expose a PHP error`);
  }

  let result = await server.playground.run({
    code: `<?php
require '/wordpress/wp-load.php';
$about = get_page_by_path( 'about' );
wp_update_post( array( 'ID' => $about->ID, 'post_title' => 'Editorial preservation test' ) );
CYWater_Setup::run();
$preserved = 'Editorial preservation test' === get_the_title( $about->ID );
CYWater_Setup::run( true );
$restored = 'Advancing water sciences, empowering young scholars.' === get_the_title( $about->ID );
$bylaws = get_page_by_path( 'bylaws' );
$contact = get_page_by_path( 'contact' );
echo wp_json_encode(
    array(
        'preserved' => $preserved,
        'force_restored' => $restored,
        'gateway_environment' => get_option( 'pmpro_gateway_environment' ),
        'live_gate' => CYWater_Config::live_payments_allowed(),
        'members_page' => (bool) get_page_by_path( 'members' ),
		'bylaws_articles' => substr_count( $bylaws->post_content, 'ARTICLE ' ),
		'contact_address' => get_post_meta( $contact->ID, '_cyw_mailing_address', true ),
        'event_count' => wp_count_posts( 'cyw_event' )->publish,
        'award_count' => wp_count_posts( 'cyw_award' )->publish,
        'news_count' => wp_count_posts( 'post' )->publish,
    )
);`,
  });

  assert.equal(result.exitCode, 0, result.errors);
  const report = JSON.parse(result.text);
  assert.equal(report.preserved, true, "Normal setup must preserve editorial changes");
  assert.equal(report.force_restored, true, "Explicit force import must restore seed content");
  assert.equal(report.gateway_environment, "sandbox", "Local payment gateway must remain sandboxed");
  assert.equal(report.live_gate, false, "Local live-payment gate must remain closed");
  assert.equal(report.members_page, true, "Member directory page must exist");
  assert.equal(Number(report.bylaws_articles), 9, "All nine Bylaws articles must be seeded");
  assert.match(report.contact_address, /202 E\. Green St\./, "Verified mailing address must be editable page metadata");
  assert.equal(Number(report.event_count), 18);
  assert.equal(Number(report.award_count), 14);
  assert.equal(Number(report.news_count), 16);

  result = await server.playground.run({
    code: `<?php
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$error = activate_plugin( 'paid-memberships-pro/paid-memberships-pro.php' );
if ( is_wp_error( $error ) ) {
    throw new RuntimeException( $error->get_error_message() );
}
echo 'activated';`,
  });
  assert.equal(result.exitCode, 0, result.errors);
  assert.equal(result.text, "activated");

  result = await server.playground.run({
    code: `<?php
require '/wordpress/wp-load.php';
global $wpdb;
$setup = CYWater_Membership_Setup::setup();
$levels = array();
foreach ( pmpro_getAllLevels( true, true ) as $level ) {
    $levels[ $level->name ] = array(
        'price' => (float) $level->initial_payment,
        'expires' => (int) $level->expiration_number,
    );
}
echo wp_json_encode(
    array(
        'status' => $setup['status'],
        'configured_level_ids' => $setup['levels'],
        'levels' => $levels,
        'database' => get_class( $wpdb ),
        'gateway' => get_option( 'pmpro_gateway' ),
        'gateway_environment' => get_option( 'pmpro_gateway_environment' ),
        'payment_flow' => get_option( 'pmpro_stripe_payment_flow' ),
        'currency' => get_option( 'pmpro_currency' ),
    )
);`,
  });
  assert.equal(result.exitCode, 0, result.errors);
  const membership = JSON.parse(result.text);
  assert.equal(membership.status, "ready");
  assert.deepEqual(Object.keys(membership.configured_level_ids).sort(), ["lifetime", "partner", "professional", "student"]);
  if (Object.keys(membership.levels).length) {
    assert.deepEqual(
      Object.fromEntries(Object.entries(membership.levels).map(([name, data]) => [name, data.price])),
      { Student: 20, Professional: 70, Lifetime: 700, Partner: 1000 },
    );
    assert.equal(membership.levels.Lifetime.expires, 0);
    assert.equal(membership.levels.Student.expires, 1);
  } else {
    assert.match(membership.database, /SQLite/i, "Empty PMPro levels are tolerated only on Playground SQLite");
  }
  assert.equal(membership.gateway, "stripe");
  assert.equal(membership.gateway_environment, "sandbox");
  assert.equal(membership.payment_flow, "checkout");
  assert.equal(membership.currency, "USD");
  console.log(JSON.stringify({ content: report, membership }, null, 2));
} finally {
  await server[Symbol.asyncDispose]();
}
