import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { runCLI } from "@wp-playground/cli";

const corePluginHeader = readFileSync(
  new URL("../wordpress/wp-content/plugins/cywater-core/cywater-core.php", import.meta.url),
  "utf8"
);
const corePluginVersion = corePluginHeader.match(/^\s*\*\s*Version:\s*([^\s*]+)\s*$/m)?.[1];
assert.ok(corePluginVersion, "CYWater Core must declare a plugin version");

const mounts = [
  ["./wordpress/wp-content/themes/cywater", "/wordpress/wp-content/themes/cywater"],
  ["./wordpress/wp-content/plugins/cywater-core", "/wordpress/wp-content/plugins/cywater-core"],
  ["./wordpress/wp-content/plugins/cywater-membership", "/wordpress/wp-content/plugins/cywater-membership"],
  ["./wordpress/wp-content/plugins/cywater-partnerships", "/wordpress/wp-content/plugins/cywater-partnerships"],
  ["./wordpress/wp-content/plugins/cywater-logo-call", "/wordpress/wp-content/plugins/cywater-logo-call"],
  ["./wordpress/wp-content/plugins/cywater-environment", "/wordpress/wp-content/plugins/cywater-environment"],
  ["./wordpress/wp-content/plugins/cywater-forum", "/wordpress/wp-content/plugins/cywater-forum"],
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

/**
 * Two Playground-only transients, neither of which says anything about the
 * site: the worker pool answers 503 when momentarily exhausted, and its virtual
 * filesystem intermittently fails to read `/wordpress/.maintenance`, which
 * WordPress reports as a fatal before any theme code runs.
 *
 * Both are retried. Every other status and every other PHP error still fails on
 * the first attempt, so a genuinely broken page cannot hide behind this.
 */
const MAINTENANCE_RACE = /Failed opening required '.*\.maintenance'/;

async function fetchPage(url, attempts = 5) {
  let last;
  let lastHtml = "";
  for (let attempt = 0; attempt < attempts; attempt++) {
    last = await fetch(url);
    lastHtml = await last.text();
    if (last.status !== 503 && ! MAINTENANCE_RACE.test(lastHtml)) {
      break;
    }
    await new Promise((resolve) => setTimeout(resolve, 500 * (attempt + 1)));
  }
  // The body is already consumed, so hand it back alongside the response.
  return { status: last.status, html: lastHtml };
}

try {
  for (const route of ["/", "/about/", "/board/", "/bylaws/", "/news/", "/events/", "/awards/", "/membership/", "/contact/", "/members/"]) {
    const response = await fetchPage(new URL(route, server.serverUrl));
    assert.equal(response.status, 200, `${route} should return HTTP 200`);
    const html = response.html;
    assert.doesNotMatch(html, /Fatal error|Parse error|Warning:/, `${route} should not expose a PHP error`);
    if (route === "/events/") {
      assert.match(html, /CYWater Annual Meeting 2026/, "Events archive must publish the upcoming 2026 meeting");
      assert.equal((html.match(/class="event-archive-row"/g) || []).length, 19, "Events archive must render all verified event rows");
      assert.match(html, /id="annual-meetings-title"/, "Events archive must retain the Annual Meetings section");
      assert.match(html, /id="annual-gathering-title"/, "Events archive must retain the Annual Gathering section");
    }
    if (route === "/news/") {
      assert.match(html, /Verified opportunities for the water-science community/, "News hero copy must match the static preview");
      assert.match(html, /id="opportunities-title"/, "News must retain the Opportunities section");
      assert.match(html, /id="spotlights-title"/, "News must retain the Spotlights section");
      assert.equal((html.match(/class="news-feature"/g) || []).length, 1, "News must render one featured spotlight");
      assert.equal((html.match(/class="news-item"/g) || []).length, 14, "News must render all remaining spotlights as rows");
      assert.doesNotMatch(html, /class="news-row"|Hello world/i, "News must not use the obsolete generic archive row or default post");
    }
    if (route === "/awards/") {
      assert.match(html, /Recognizing early-career research/, "Awards must retain its eligibility introduction");
      assert.equal((html.match(/class="award-year"/g) || []).length, 14, "Awards must render the complete 2012-2025 yearbook");
      assert.match(html, /Outstanding Papers/, "Awards must include Outstanding Paper records");
      assert.match(html, /10\.1073\/pnas\.2421046122/, "Awards must retain verified DOI data");
      assert.doesNotMatch(html, /class="award-grid/, "Awards must not use the obsolete card grid");
    }
    if (route === "/membership/") {
      assert.equal((html.match(/<article class="tier\b/g) || []).length, 3, "Membership must render three individual membership tiers");
      assert.match(html, /Become Our Partner/, "Membership must route institutions to the separate Partner application");
      assert.match(html, /class="table fee-table"/, "Membership must retain the conference fee matrix");
      assert.match(html, /membership-partner-head/, "Membership partners must use the centered shared section heading");
      assert.match(html, /membership-partner-copy/, "Membership partner copy must retain its constrained centered layout");
      assert.match(html, /membership-fees/, "Conference fees must retain its dedicated shared-layout hook");
    }
    if (route === "/board/") {
      assert.match(html, /Board of Directors\./, "Board hero must match the static preview");
      assert.match(html, /Board composition/, "Board must retain the composition section");
      assert.match(html, /Committee framework/, "Board must retain the committee framework");
      assert.equal((html.match(/class="role-card"/g) || []).length, 5, "Board must render all five governance roles");
      for (const committee of ["Awards Committee", "Scientific and Technical Committee", "Nomination Committee", "Tellers Committee"]) {
        assert.match(html, new RegExp(committee), `Board must render ${committee}`);
      }
    }
    if (route === "/bylaws/") {
      assert.match(html, /<h4>Contents<\/h4>/, "Bylaws must retain its table of contents");
      assert.match(html, /Download bylaws \(\.docx\)/, "Bylaws must retain the source-document action");
      assert.equal((html.match(/class="article-num">ARTICLE [IVX]+/g) || []).length, 9, "Bylaws must render all nine article headings");
      assert.match(html, /ARTICLE IX/, "Bylaws must include the final article");
    }
  }

  assert.match(readFileSync("wordpress/wp-content/themes/cywater/single.php", "utf8"), /article-hero/, "News detail must retain its dedicated layout");
  assert.match(readFileSync("wordpress/wp-content/themes/cywater/single-cyw_event.php", "utf8"), /event-hero/, "Event detail must retain its dedicated layout");

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
		'news_order_meta' => (bool) get_post_meta( get_posts( array( 'post_type' => 'post', 'meta_key' => '_cyw_source_id', 'meta_value' => 'news:bpa-2025-result', 'fields' => 'ids', 'posts_per_page' => 1 ) )[0], '_cyw_news_order', true ),
		'award_record_meta' => (bool) get_post_meta( get_posts( array( 'post_type' => 'cyw_award', 'meta_key' => '_cyw_source_id', 'meta_value' => 'award:2025', 'fields' => 'ids', 'posts_per_page' => 1 ) )[0], '_cyw_award_record', true ),
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
  assert.equal(Number(report.event_count), 19);
  assert.equal(Number(report.award_count), 14);
  assert.equal(Number(report.news_count), 16);
  assert.equal(report.news_order_meta, true, "Normal setup must add missing News ordering metadata");
  assert.equal(report.award_record_meta, true, "Normal setup must add missing structured Award metadata");

  result = await server.playground.run({
    code: `<?php
require '/wordpress/wp-load.php';
$news_id = get_posts( array( 'post_type' => 'post', 'meta_key' => '_cyw_source_id', 'meta_value' => 'news:bpa-2025-result', 'fields' => 'ids', 'posts_per_page' => 1 ) )[0];
$award_id = get_posts( array( 'post_type' => 'cyw_award', 'meta_key' => '_cyw_source_id', 'meta_value' => 'award:2025', 'fields' => 'ids', 'posts_per_page' => 1 ) )[0];
wp_update_post( array( 'ID' => $news_id, 'post_title' => 'Upgrade preservation test' ) );
$news_ids = get_posts(
    array(
        'post_type' => 'post',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            array(
                'key' => '_cyw_source_id',
                'value' => 'news:',
                'compare' => 'LIKE',
            ),
        ),
    )
);
foreach ( $news_ids as $imported_news_id ) {
    delete_post_meta( $imported_news_id, '_cyw_news_order' );
}
delete_post_meta( $award_id, '_cyw_award_record' );
delete_post_meta( $award_id, '_cyw_article_id' );
update_option( 'cywater_core_setup_version', '0.2.0' );
echo 'prepared';`,
  });
  assert.equal(result.exitCode, 0, result.errors);
  assert.equal(result.text, "prepared");

  const fallbackHtml = (await fetchPage(new URL("/news/", server.serverUrl))).html;
  assert.equal((fallbackHtml.match(/class="news-feature"/g) || []).length, 1, "News fallback must retain its featured story");
  assert.equal((fallbackHtml.match(/class="news-item"/g) || []).length, 14, "News fallback must render every imported story");
  assert.doesNotMatch(fallbackHtml, /Hello world/i, "News fallback must exclude unrelated posts");

  result = await server.playground.run({
    code: `<?php
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );
$news_id = get_posts( array( 'post_type' => 'post', 'meta_key' => '_cyw_source_id', 'meta_value' => 'news:bpa-2025-result', 'fields' => 'ids', 'posts_per_page' => 1 ) )[0];
$award_id = get_posts( array( 'post_type' => 'cyw_award', 'meta_key' => '_cyw_source_id', 'meta_value' => 'award:2025', 'fields' => 'ids', 'posts_per_page' => 1 ) )[0];
cywater_core_maybe_upgrade();
echo wp_json_encode(
    array(
        'setup_version' => get_option( 'cywater_core_setup_version' ),
        'title_preserved' => 'Upgrade preservation test' === get_the_title( $news_id ),
        'news_order_restored' => metadata_exists( 'post', $news_id, '_cyw_news_order' ),
        'award_record_restored' => metadata_exists( 'post', $award_id, '_cyw_award_record' ),
        'article_id_restored' => metadata_exists( 'post', $award_id, '_cyw_article_id' ),
    )
);`,
  });
  assert.equal(result.exitCode, 0, result.errors);
  const upgrade = JSON.parse(result.text);
  assert.equal(upgrade.setup_version, corePluginVersion, "Automatic upgrade must record the completed version");
  assert.equal(upgrade.title_preserved, true, "Automatic upgrade must preserve editorial content");
  assert.equal(upgrade.news_order_restored, true, "Automatic upgrade must restore missing News metadata");
  assert.equal(upgrade.award_record_restored, true, "Automatic upgrade must restore missing Award records");
  assert.equal(upgrade.article_id_restored, true, "Automatic upgrade must restore Award announcement links");

  const upgradedNewsHtml = (await fetchPage(new URL("/news/", server.serverUrl))).html;
  assert.equal((upgradedNewsHtml.match(/class="news-feature"/g) || []).length, 1, "Upgraded News must retain its featured story");
  assert.equal((upgradedNewsHtml.match(/class="news-item"/g) || []).length, 14, "Upgraded News must render every imported story");
  const upgradedAwardsHtml = (await fetchPage(new URL("/awards/", server.serverUrl))).html;
  assert.match(upgradedAwardsHtml, /Outstanding Papers/, "Upgraded Awards must render restored Outstanding Paper records");
  assert.match(upgradedAwardsHtml, /10\.1073\/pnas\.2421046122/, "Upgraded Awards must render restored DOI data");
  assert.match(upgradedAwardsHtml, /Read award announcement/, "Upgraded Awards must render restored announcement links");

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
