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
  ["./wordpress/wp-content/plugins/cywater-operations", "/wordpress/wp-content/plugins/cywater-operations"],
  ["./wordpress/wp-content/plugins/cywater-forum", "/wordpress/wp-content/plugins/cywater-forum"],
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
  for (const route of ["/forum/", "/forum-workspace/", "/", "/about/", "/board/", "/bylaws/", "/news/", "/events/", "/awards/", "/membership/", "/contact/", "/members/"]) {
    const response = await fetch(new URL(route, server.serverUrl));
    assert.equal(response.status, 200, `${route} should return HTTP 200`);
    const html = await response.text();
    assert.doesNotMatch(html, /Fatal error|Parse error|Warning:/, `${route} should not expose a PHP error`);
    if (route === "/events/") {
      assert.match(html, /CYWater Annual Meeting 2026/, "Events archive must publish the upcoming 2026 meeting");
      assert.equal((html.match(/class="event-archive-row"/g) || []).length, 18, "Events archive must render all standard Event rows");
      assert.match(html, /id="annual-meetings-title"/, "Events archive must retain the Annual Meetings section");
      assert.match(html, /id="annual-gathering-title"/, "Events archive must retain the Annual Gathering section");
    }
    if (route === "/news/") {
      assert.match(html, /Verified opportunities for the water-science community/, "News hero copy must match the static preview");
      assert.match(html, /id="opportunities-title"/, "News must retain the Opportunities section");
      assert.match(html, /id="spotlights-title"/, "News must retain the Spotlights section");
      assert.equal((html.match(/class="news-feature"/g) || []).length, 1, "News must render one featured spotlight");
      assert.equal((html.match(/class="news-item"/g) || []).length, 15, "News must render every remaining published Post as a row");
      assert.match(html, /Hello world/i, "A normal published Post must remain visible without importer metadata");
      assert.doesNotMatch(html, /class="news-row"/, "News must not use the obsolete generic archive row");
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
    if (route === "/forum/") {
      assert.match(html, /CYWater Forum\./, "Forum archive must retain its public member-writing introduction");
      assert.match(html, /Sign in/, "Signed-out Forum visitors must receive a front-end sign-in action");
    }
    if (route === "/forum-workspace/") {
      assert.match(html, /Write for the CYWater Forum\./, "Forum workspace must use the dedicated front-end template");
      assert.match(html, /Submission unavailable/, "Signed-out Forum workspace must explain its eligibility gate");
      assert.doesNotMatch(html, /wp-admin\/post-new\.php/, "Forum workspace must not send ordinary members to the WordPress editor");
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
  assert.equal(Number(report.event_count), 18);
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

  const fallbackResponse = await fetch(new URL("/news/", server.serverUrl));
  const fallbackHtml = await fallbackResponse.text();
  assert.equal((fallbackHtml.match(/class="news-feature"/g) || []).length, 1, "News fallback must retain its featured story");
  assert.equal((fallbackHtml.match(/class="news-item"/g) || []).length, 15, "News fallback must render every remaining published Post");
  assert.match(fallbackHtml, /Hello world/i, "News fallback must retain a normal published Post without importer metadata");

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

  const upgradedNewsHtml = await (await fetch(new URL("/news/", server.serverUrl))).text();
  assert.equal((upgradedNewsHtml.match(/class="news-feature"/g) || []).length, 1, "Upgraded News must retain its featured story");
  assert.equal((upgradedNewsHtml.match(/class="news-item"/g) || []).length, 15, "Upgraded News must render every remaining published Post");
  const upgradedAwardsHtml = await (await fetch(new URL("/awards/", server.serverUrl))).text();
  assert.match(upgradedAwardsHtml, /Outstanding Papers/, "Upgraded Awards must render restored Outstanding Paper records");
  assert.match(upgradedAwardsHtml, /10\.1073\/pnas\.2421046122/, "Upgraded Awards must render restored DOI data");
  assert.match(upgradedAwardsHtml, /Read award announcement/, "Upgraded Awards must render restored announcement links");

  result = await server.playground.run({
    code: `<?php
define( 'CYWATER_FORUM_COVER_QA', true );
require '/wordpress/wp-load.php';
try {
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/file.php';
$post_id = wp_insert_post(
    array(
        'post_type' => CYWater_Forum_Content::POST_TYPE,
        'post_status' => 'draft',
        'post_author' => 1,
        'post_title' => 'Protected cover runtime QA',
        'post_content' => 'Temporary runtime QA record.',
    ),
    true
);
if ( is_wp_error( $post_id ) ) {
    throw new RuntimeException( $post_id->get_error_message() );
}
$source = wp_tempnam( 'forum-cover-runtime.png' );
file_put_contents( $source, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' ) );
$cover = CYWater_Forum_Covers::import_file_for_qa( $source, 'forum-cover-runtime.png', 1 );
if ( is_wp_error( $cover ) ) {
    throw new RuntimeException( $cover->get_error_message() );
}
$attached = CYWater_Forum_Covers::replace( $post_id, $cover, 1 );
if ( is_wp_error( $attached ) ) {
    throw new RuntimeException( $attached->get_error_message() );
}
$record = CYWater_Forum_Covers::get( $post_id );
$stored_path = CYWater_Forum_Covers::path_for_qa( $record );
$native_thumbnail = get_post_thumbnail_id( $post_id );
$url = CYWater_Forum_Covers::url( $post_id );
$query = array();
parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
$draft_anonymous = CYWater_Forum_Covers::can_stream( $post_id, 0, '' );
$draft_owner = CYWater_Forum_Covers::can_stream( $post_id, 1, (string) ( $query['_wpnonce'] ?? '' ) );
$referenced_file_preserved = ( CYWater_Forum_Covers::discard( $record ) === null && file_exists( $stored_path ) );
wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
$published_anonymous = CYWater_Forum_Covers::can_stream( $post_id, 0, '' );
wp_trash_post( $post_id );
$trashed_anonymous = CYWater_Forum_Covers::can_stream( $post_id, 0, '' );
wp_delete_post( $post_id, true );
$deleted_file = ! file_exists( $stored_path );
wp_delete_file( $source );
echo wp_json_encode(
    array(
        'stored' => file_exists( dirname( $stored_path ) ),
		'referenced_file_preserved' => $referenced_file_preserved,
        'native_thumbnail' => $native_thumbnail,
        'draft_anonymous' => $draft_anonymous,
        'draft_owner' => $draft_owner,
        'published_anonymous' => $published_anonymous,
        'trashed_anonymous' => $trashed_anonymous,
        'deleted_file' => $deleted_file,
    )
);
} catch ( Throwable $error ) {
    echo wp_json_encode( array( 'runtime_error' => get_class( $error ) . ': ' . $error->getMessage() ) );
}`,
  });
  assert.equal(result.exitCode, 0, result.errors);
  const forumCover = JSON.parse(result.text);
  assert.equal(forumCover.runtime_error, undefined, forumCover.runtime_error);
  assert.equal(forumCover.stored, true, "Protected Forum cover directory must exist");
  assert.equal(forumCover.referenced_file_preserved, true, "Cleanup must not delete a still-referenced Forum cover");
  assert.equal(Number(forumCover.native_thumbnail), 0, "Forum covers must not use public Media attachments");
  assert.equal(forumCover.draft_anonymous, false, "Anonymous visitors must not stream draft Forum covers");
  assert.equal(forumCover.draft_owner, true, "The owner must be able to preview a nonce-bound draft cover");
  assert.equal(forumCover.published_anonymous, true, "Published Forum covers must be publicly streamable");
  assert.equal(forumCover.trashed_anonymous, false, "Taking down an article must revoke anonymous cover access");
  assert.equal(forumCover.deleted_file, true, "Deleting an article must remove its protected cover file");

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
  assert.deepEqual(Object.keys(membership.configured_level_ids).sort(), ["lifetime", "professional", "student"]);
  if (Object.keys(membership.levels).length) {
    assert.deepEqual(
      Object.fromEntries(Object.entries(membership.levels).map(([name, data]) => [name, data.price])),
      { Student: 20, Professional: 50, Lifetime: 700 },
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
