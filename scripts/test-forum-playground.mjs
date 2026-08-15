/**
 * Forum acceptance test on WP Playground.
 *
 * Runs the real plugin against a real WordPress install: registration, the
 * publishing gate under each blocker, the full endorsement round trip including
 * replay, discussion scoping and moderation, the narrowed author archive, and
 * the dormant AI endpoint.
 *
 * Playground uses SQLite and does not activate PMPro's membership queries, so
 * membership state is stubbed through the documented filter rather than
 * pretended. Every assertion that depends on real PMPro state is marked in the
 * output and still has to pass on staging.
 */

import assert from "node:assert/strict";
import { runCLI } from "@wp-playground/cli";

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
  port: 8892,
  php: "8.3",
  wp: "7.0.2",
  debug: true,
  "mount-before-install": mounts,
  blueprint: "./wordpress/blueprint.json",
});

const results = [];
function record(name, detail) {
  results.push({ name, detail });
  console.log(`  ok  ${name}${detail ? ` — ${detail}` : ""}`);
}

async function php(code) {
  const result = await server.playground.run({ code: `<?php\nrequire '/wordpress/wp-load.php';\n${code}` });
  assert.equal(result.exitCode, 0, `PHP failed: ${result.errors}\n${result.text}`);
  return result.text;
}

async function json(code) {
  const text = await php(code);
  try {
    return JSON.parse(text);
  } catch {
    throw new Error(`Expected JSON, got: ${text}`);
  }
}

try {
  /* ------------------------------------------------------------------
   * 0. Membership stub
   *
   * Playground runs on SQLite and PMPro's membership queries are MySQL
   * specific, so `pmpro_hasMembershipLevel` is stubbed as a must-use plugin.
   * It has to be a file rather than an inline definition because every request
   * is a fresh PHP process. Membership state is therefore NOT proven here; it
   * is proven on staging, where PMPro is real.
   * ---------------------------------------------------------------- */
  await server.playground.mkdir("/wordpress/wp-content/mu-plugins");
  await server.playground.writeFile(
    "/wordpress/wp-content/mu-plugins/test-membership-stub.php",
    `<?php
// See start-forum-preview.mjs: Playground's background update run leaves an
// unreadable /wordpress/.maintenance behind and takes the site down with it.
add_filter( 'automatic_updater_disabled', '__return_true' );
if ( file_exists( ABSPATH . '.maintenance' ) ) {
    @unlink( ABSPATH . '.maintenance' );
}

if ( ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
    function pmpro_hasMembershipLevel( $levels = null, $user_id = null ) {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        return (bool) get_user_meta( $user_id, 'test_active_membership', true );
    }
}

// Core's flood protection is IP based and every insertion in this harness
// shares one address. It stays enabled in every real environment.
add_filter( 'wp_is_comment_flood', '__return_false', 99 );
`
  );
  record("membership stub installed", "SQLite substitute for PMPro, re-tested on staging");

  /* ------------------------------------------------------------------
   * 1. Registration
   * ---------------------------------------------------------------- */
  const registered = await json(`
$out = array(
    'post_type'   => post_type_exists( 'cyw_forum_post' ),
    'category'    => taxonomy_exists( 'cyw_forum_category' ),
    'topic'       => taxonomy_exists( 'cyw_forum_topic' ),
    'role'        => (bool) get_role( 'cyw_forum_author' ),
    'seeded'      => (int) wp_count_terms( array( 'taxonomy' => 'cyw_forum_category', 'hide_empty' => false ) ),
    'page'        => (bool) get_page_by_path( 'forum-endorsement' ),
    'settings'    => CYWater_Forum_Settings::all(),
);
echo wp_json_encode( $out );`);

  assert.equal(registered.post_type, true, "cyw_forum_post must register");
  assert.equal(registered.category, true, "cyw_forum_category must register");
  assert.equal(registered.topic, true, "cyw_forum_topic must register");
  assert.equal(registered.role, true, "cyw_forum_author role must be installed by setup");
  assert.equal(registered.seeded, 4, "Four editorial categories must be seeded");
  assert.equal(registered.page, true, "The endorsement page must be created by setup");
  assert.equal(registered.settings.endorsements_required, 1);
  assert.equal(registered.settings.endorsement_articles_required, 1);
  assert.equal(registered.settings.admin_override, true);
  assert.equal(registered.settings.membership_required, true);
  record("content model, role, page, and seeded categories install", `${registered.seeded} categories`);
  record("approved policy defaults load", "1 article to endorse, 1 endorsement, override on, membership required");

  /* ------------------------------------------------------------------
   * 2. Users and the publishing gate, blocker by blocker
   * ---------------------------------------------------------------- */
  const gate = await json(`
$author_id = wp_insert_user( array( 'user_login' => 'established', 'user_email' => 'established@example.org', 'user_pass' => wp_generate_password(), 'display_name' => 'Established Author', 'role' => 'subscriber' ) );
$cand_id   = wp_insert_user( array( 'user_login' => 'candidate', 'user_email' => 'candidate@example.org', 'user_pass' => wp_generate_password(), 'display_name' => 'New Candidate', 'role' => 'subscriber' ) );

$stages = array();
$stages['bare'] = CYWater_Forum_Roles::publish_blockers( $cand_id );

update_user_meta( $cand_id, 'test_active_membership', 1 );
$stages['with_membership'] = CYWater_Forum_Roles::publish_blockers( $cand_id );

update_user_meta( $cand_id, 'cyw_verified_email', 'candidate@example.org' );
$stages['with_verified_email'] = CYWater_Forum_Roles::publish_blockers( $cand_id );

$stages['cap_before'] = user_can( $cand_id, 'publish_cyw_forum_posts' );
$stages['can_draft']  = user_can( $cand_id, 'edit_cyw_forum_posts' );
$stages['admin_can']  = user_can( 1, 'publish_cyw_forum_posts' );

echo wp_json_encode( array( 'author_id' => $author_id, 'candidate_id' => $cand_id, 'stages' => $stages ) );`);

  assert.deepEqual(
    gate.stages.bare.sort(),
    ["email_unverified", "membership_inactive", "not_endorsed"],
    "A bare account must be blocked for all three reasons"
  );
  assert.deepEqual(gate.stages.with_membership.sort(), ["email_unverified", "not_endorsed"]);
  assert.deepEqual(gate.stages.with_verified_email, ["not_endorsed"]);
  assert.equal(gate.stages.cap_before, false, "Publishing must be denied without an endorsement");
  assert.equal(gate.stages.admin_can, true, "Staff must bypass the endorsement gate");
  record("publishing gate reports each blocker independently", "membership, email, endorsement");
  record("unendorsed member is denied publish_cyw_forum_posts; administrator is not");

  const authorId = gate.author_id;
  const candidateId = gate.candidate_id;

  /* ------------------------------------------------------------------
   * 3. Endorser qualification
   * ---------------------------------------------------------------- */
  const qualification = await json(`
$before = CYWater_Forum_Endorsement::is_qualified_endorser( ${authorId} );

// Authorise and publish one article, which is what makes an author eligible.
CYWater_Forum_Endorsement::admin_grant( ${authorId}, 1 );
$after_grant_no_article = CYWater_Forum_Endorsement::is_qualified_endorser( ${authorId} );

$post_id = wp_insert_post( array(
    'post_type'    => 'cyw_forum_post',
    'post_status'  => 'publish',
    'post_title'   => 'Rainfall-runoff modelling notes',
    'post_content' => "<p>A first look at catchment response times.</p>",
    'post_excerpt' => 'A first look at catchment response times.',
    'post_author'  => ${authorId},
) );
$terms = get_terms( array( 'taxonomy' => 'cyw_forum_category', 'hide_empty' => false ) );
wp_set_object_terms( $post_id, array( $terms[0]->term_id ), 'cyw_forum_category' );
wp_set_object_terms( $post_id, array( 'hydrology', 'modelling' ), 'cyw_forum_topic' );

echo wp_json_encode( array(
    'before'                 => $before,
    'after_grant_no_article' => $after_grant_no_article,
    'after_article'          => CYWater_Forum_Endorsement::is_qualified_endorser( ${authorId} ),
    'published_count'        => CYWater_Forum_Content::published_count( ${authorId} ),
    'post_id'                => $post_id,
    'permalink'              => get_permalink( $post_id ),
    'category_link'          => get_term_link( $terms[0] ),
) );`);

  assert.equal(qualification.before, false, "An unendorsed account with no articles cannot endorse");
  assert.equal(qualification.after_grant_no_article, false, "An endorsed account with no articles still cannot endorse");
  assert.equal(qualification.after_article, true, "One published article qualifies an endorsed author");
  assert.equal(qualification.published_count, 1);
  record("endorser qualification requires endorsement AND a published article");

  /* ------------------------------------------------------------------
   * 4. Endorsement round trip, through the real mail path
   * ---------------------------------------------------------------- */
  const endorsement = await json(`
// Capture the outgoing message so the token comes from the real mail body
// rather than being manufactured by the test.
$GLOBALS['captured'] = array();
add_filter( 'wp_mail', function ( $atts ) { $GLOBALS['captured'][] = $atts; return $atts; } );

$unknown = CYWater_Forum_Endorsement::request_endorsement( ${candidateId}, 'nobody@example.org' );
$mails_after_unknown = count( $GLOBALS['captured'] );

$self = CYWater_Forum_Endorsement::request_endorsement( ${candidateId}, 'candidate@example.org' );

$sent = CYWater_Forum_Endorsement::request_endorsement( ${candidateId}, 'established@example.org' );

$token = '';
foreach ( $GLOBALS['captured'] as $mail ) {
    if ( preg_match( '/token=([A-Za-z0-9]+)/', (string) $mail['message'], $m ) ) {
        $token = $m[1];
    }
}

$wrong_account = CYWater_Forum_Endorsement::redeem( ${candidateId}, ${authorId}, $token, ${candidateId} );
$still_pending = ! CYWater_Forum_Endorsement::is_endorsed( ${candidateId} );

$redeemed = CYWater_Forum_Endorsement::redeem( ${candidateId}, ${authorId}, $token, ${authorId} );
$replay   = CYWater_Forum_Endorsement::redeem( ${candidateId}, ${authorId}, $token, ${authorId} );

$user = get_user_by( 'id', ${candidateId} );

echo wp_json_encode( array(
    'unknown_address'     => $unknown,
    'mails_for_unknown'   => $mails_after_unknown,
    'self_request'        => $self,
    'sent'                => $sent,
    'token_found'         => '' !== $token,
    'wrong_account'       => $wrong_account,
    'unspent_after_wrong' => $still_pending,
    'redeemed'            => $redeemed,
    'replay'              => $replay,
    'is_endorsed'         => CYWater_Forum_Endorsement::is_endorsed( ${candidateId} ),
    'roles'               => array_values( $user->roles ),
    'blockers'            => CYWater_Forum_Roles::publish_blockers( ${candidateId} ),
    'can_publish_cap'     => user_can( ${candidateId}, 'publish_cyw_forum_posts' ),
    'records'             => CYWater_Forum_Endorsement::endorsements( ${candidateId} ),
) );`);

  assert.equal(endorsement.unknown_address, "sent", "An unknown address must return the same result as a real one");
  assert.equal(endorsement.mails_for_unknown, 0, "No mail may be sent for an unknown address");
  assert.equal(endorsement.self_request, "self", "Self-endorsement must be refused");
  assert.equal(endorsement.sent, "sent");
  assert.equal(endorsement.token_found, true, "A single-use token must reach the endorser by email");
  assert.equal(endorsement.wrong_account, "wrong_account", "A link opened by the wrong account must be refused");
  assert.equal(endorsement.unspent_after_wrong, true, "A refused attempt must not spend the token");
  assert.equal(endorsement.redeemed, "endorsed");
  assert.equal(endorsement.replay, "invalid", "Replaying a spent link must be rejected");
  assert.equal(endorsement.is_endorsed, true);
  assert.ok(endorsement.roles.includes("cyw_forum_author"), "Endorsement must grant the author role");
  assert.deepEqual(endorsement.blockers, [], "An endorsed, current, verified member must be able to publish");
  assert.equal(endorsement.can_publish_cap, true);
  assert.equal(endorsement.records.length, 1);
  assert.equal(Number(endorsement.records[0].endorser), authorId);
  record("endorsement enumeration-safe: unknown address sends no mail, same response");
  record("endorsement round trip via real mail body", "wrong account refused, token unspent, replay rejected");
  record("role granted and publishing unblocked after endorsement");

  /* ------------------------------------------------------------------
   * 5. Lapsed membership stops new publishing without destroying anything
   * ---------------------------------------------------------------- */
  const lapse = await json(`
$candidate_post = wp_insert_post( array(
    'post_type'   => 'cyw_forum_post',
    'post_status' => 'publish',
    'post_title'  => 'Notes from a first field season',
    'post_content'=> '<p>What I would do differently next time.</p>',
    'post_author' => ${candidateId},
) );

delete_user_meta( ${candidateId}, 'test_active_membership' );
$user = get_user_by( 'id', ${candidateId} );

echo wp_json_encode( array(
    'blockers'       => CYWater_Forum_Roles::publish_blockers( ${candidateId} ),
    'cap'            => user_can( ${candidateId}, 'publish_cyw_forum_posts' ),
    'can_still_draft'=> user_can( ${candidateId}, 'edit_cyw_forum_posts' ),
    'keeps_role'     => in_array( 'cyw_forum_author', (array) $user->roles, true ),
    'article_live'   => 'publish' === get_post_status( $candidate_post ),
    'post_id'        => $candidate_post,
    'permalink'      => get_permalink( $candidate_post ),
) );`);

  assert.deepEqual(lapse.blockers, ["membership_inactive"]);
  assert.equal(lapse.cap, false, "A lapsed member must not publish anything new");
  assert.equal(lapse.can_still_draft, true, "A lapsed member must keep drafting");
  assert.equal(lapse.keeps_role, true, "A lapsed member must keep the author role");
  assert.equal(lapse.article_live, true, "Already published articles must stay published");
  record("lapsed membership blocks new publishing only", "role, drafts, and live articles untouched");

  /* ------------------------------------------------------------------
   * 6. Discussion scoping and moderation
   * ---------------------------------------------------------------- */
  const forumPostId = qualification.post_id;
  const discussion = await json(`
update_user_meta( ${candidateId}, 'test_active_membership', 1 );
$news = get_posts( array( 'post_type' => 'post', 'posts_per_page' => 1, 'fields' => 'ids' ) );

$first = wp_new_comment( array(
    'comment_post_ID' => ${forumPostId},
    'comment_content' => 'Which catchments did you use?',
    'user_id'         => ${candidateId},
    'comment_author'  => 'New Candidate',
    'comment_author_email' => 'candidate@example.org',
), true );

// Still held while the first one is only pending: auto-approval requires a
// previously *approved* reply, not merely a previous one.
$second_while_pending = wp_new_comment( array(
    'comment_post_ID' => ${forumPostId},
    'comment_content' => 'Still waiting on the first one.',
    'user_id'         => ${candidateId},
    'comment_author'  => 'New Candidate',
    'comment_author_email' => 'candidate@example.org',
), true );

// A moderator approves the first reply, which is what unlocks the member.
wp_set_comment_status( $first, 'approve' );

$second = wp_new_comment( array(
    'comment_post_ID' => ${forumPostId},
    'comment_content' => 'Following up on the response times.',
    'user_id'         => ${candidateId},
    'comment_author'  => 'New Candidate',
    'comment_author_email' => 'candidate@example.org',
), true );

$non_member = wp_insert_user( array( 'user_login' => 'outsider', 'user_email' => 'outsider@example.org', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );

// A non-member and an anonymous submission must both be refused as errors
// rather than killing the request.
$refused_non_member = wp_new_comment( array(
    'comment_post_ID' => ${forumPostId},
    'comment_content' => 'Let me in.',
    'user_id'         => $non_member,
    'comment_author'  => 'Outsider',
    'comment_author_email' => 'outsider@example.org',
), true );

$refused_anonymous = wp_new_comment( array(
    'comment_post_ID' => ${forumPostId},
    'comment_content' => 'Anonymous reply.',
    'comment_author'  => 'Nobody',
    'comment_author_email' => 'nobody@example.org',
), true );

// The same anonymous submission on a News post is not the forum's business.
$news_untouched = $news ? comments_open( $news[0] ) : null;

echo wp_json_encode( array(
    'refused_non_member' => is_wp_error( $refused_non_member ) ? $refused_non_member->get_error_code() : 'created',
    'refused_anonymous'  => is_wp_error( $refused_anonymous ) ? $refused_anonymous->get_error_code() : 'created',
    'news_untouched'     => $news_untouched,
    'forum_open'        => comments_open( ${forumPostId} ),
    'news_open'         => $news ? comments_open( $news[0] ) : null,
    'first_approved'    => (string) get_comment( $first )->comment_approved,
    'second_while_pending' => (string) get_comment( $second_while_pending )->comment_approved,
    'second_approved'   => (string) get_comment( $second )->comment_approved,
    'member_may'        => CYWater_Forum_Comments::may_comment( ${candidateId} ),
    'non_member_may'    => CYWater_Forum_Comments::may_comment( $non_member ),
    'signed_out_may'    => CYWater_Forum_Comments::may_comment( 0 ),
    'default_forum'     => get_default_comment_status( 'cyw_forum_post' ),
    'default_news'      => get_default_comment_status( 'post' ),
) );`);

  assert.equal(discussion.forum_open, true, "Discussion must be open on forum articles");
  assert.equal(discussion.news_open, false, "News must stay closed to comments");
  assert.equal(discussion.first_approved, "1", "The moderator's approval must stick");
  assert.equal(discussion.second_while_pending, "0", "Replies stay held while nothing is approved yet");
  assert.equal(discussion.second_approved, "1", "Replies auto-approve once an earlier one was approved");
  assert.equal(discussion.member_may, true);
  assert.equal(discussion.non_member_may, false, "A non-member must not be able to reply");
  assert.equal(discussion.signed_out_may, false);
  assert.equal(
    discussion.refused_non_member,
    "cywater_forum_membership_required",
    "A non-member's reply must be refused as a recoverable error"
  );
  assert.equal(
    discussion.refused_anonymous,
    "cywater_forum_signin_required",
    "An anonymous reply must be refused as a recoverable error"
  );
  assert.equal(discussion.default_forum, "open");
  assert.equal(discussion.default_news, "closed");
  record("discussion is scoped to forum articles", "News stays closed");
  record("moderation holds replies until a moderator approves one, then auto-approves");
  record("replies refused for non-members and signed-out visitors");

  /* ------------------------------------------------------------------
   * 7. Author archive narrowing
   * ---------------------------------------------------------------- */
  const archive = await json(`
$published = get_user_by( 'id', ${authorId} );
$silent    = get_user_by( 'login', 'outsider' );
echo wp_json_encode( array(
    'published_allowed' => apply_filters( 'cywater_public_author_archive_allowed', false, ${authorId} ),
    'silent_allowed'    => apply_filters( 'cywater_public_author_archive_allowed', false, $silent->ID ),
    // The setup administrator owns every imported News post, so a naive
    // "has published something" rule would open an operations account.
    'staff_allowed'     => apply_filters( 'cywater_public_author_archive_allowed', false, 1 ),
    'published_url'     => get_author_posts_url( ${authorId} ),
    'silent_url'        => get_author_posts_url( $silent->ID ),
) );`);

  assert.equal(archive.published_allowed, true, "An author who has published must get an archive");
  assert.equal(archive.silent_allowed, false, "An account that has published nothing must stay 404");
  assert.equal(archive.staff_allowed, false, "Staff must never get a public author archive");
  record("author archive opens per account, enumeration stays closed");

  // ?author=N is the enumeration vector: redirect_canonical turns it into
  // /author/<user_nicename>/, which leaks the login-derived slug.
  const probes = [];
  for (const id of [1, 2, 3]) {
    const raw = await fetch(new URL(`/?author=${id}`, server.serverUrl), { redirect: "manual" });
    const location = raw.headers.get("location") || "";
    // The property that matters is that no login-derived slug is handed out,
    // whether directly or via a canonical redirect.
    assert.doesNotMatch(
      location,
      /\/author\//,
      `?author=${id} must not disclose a username-bearing URL (got ${location})`
    );
    const final = await fetch(new URL(`/?author=${id}`, server.serverUrl));
    assert.equal(final.status, 404, `?author=${id} must end in a 404`);
    probes.push(`${id}:${raw.status}->${final.status}`);
  }
  record("numeric ?author= probing discloses no username", probes.join(" "));

  // Ticking and unticking the administrator override must not strip a role that
  // peer endorsement still justifies.
  const override = await json(`
$before = in_array( 'cyw_forum_author', (array) get_user_by( 'id', ${candidateId} )->roles, true );
CYWater_Forum_Endorsement::admin_grant( ${candidateId}, 1 );
CYWater_Forum_Endorsement::admin_revoke( ${candidateId} );
$after = in_array( 'cyw_forum_author', (array) get_user_by( 'id', ${candidateId} )->roles, true );
echo wp_json_encode( array(
    'before'      => $before,
    'after'       => $after,
    'is_endorsed' => CYWater_Forum_Endorsement::is_endorsed( ${candidateId} ),
) );`);
  assert.equal(override.before, true);
  assert.equal(override.is_endorsed, true, "The peer endorsement must survive the override toggle");
  assert.equal(override.after, true, "Withdrawing an override must not strip an earned author role");
  record("administrator override toggle preserves an earned endorsement");

  /* ------------------------------------------------------------------
   * 8. Front end
   * ---------------------------------------------------------------- */
  const pageChecks = [
    ["/forum/", 200],
    [new URL(qualification.permalink).pathname, 200],
    [new URL(qualification.category_link).pathname, 200],
    ["/forum/topic/hydrology/", 200],
    ["/forum-endorsement/", 200],
    [new URL(archive.published_url).pathname, 200],
    [new URL(archive.silent_url).pathname, 404],
  ];

  for (const [route, expected] of pageChecks) {
    const response = await fetch(new URL(route, server.serverUrl));
    const html = await response.text();
    assert.equal(response.status, expected, `${route} should return HTTP ${expected}`);
    assert.doesNotMatch(html, /Fatal error|Parse error|Warning:/, `${route} must not expose a PHP error`);
    if (expected === 200) {
      assert.doesNotMatch(html, /AI-generated perspective/, `${route} must not render an AI panel while the seam is dormant`);
    }
  }
  record("forum, article, category, topic, endorsement, and author routes render", "silent account 404s");

  const forumHtml = await (await fetch(new URL("/forum/", server.serverUrl))).text();
  assert.match(forumHtml, /Rainfall-runoff modelling notes/, "The archive must list the published article");
  assert.match(forumHtml, /Established Author/, "The archive must carry the byline");
  assert.match(forumHtml, /class="card forum-card"/, "The archive must use the forum card");

  const articleHtml = await (await fetch(new URL(new URL(qualification.permalink).pathname, server.serverUrl))).text();
  assert.match(articleHtml, /catchment response times/, "The article body must render");
  assert.match(articleHtml, /Which catchments did you use\?|Following up on the response times/, "Approved discussion must render");
  assert.match(articleHtml, /forum-comments/, "The discussion section must render");
  assert.match(articleHtml, /hydrology/, "Topics must render on the article");

  const navHtml = await (await fetch(new URL("/", server.serverUrl))).text();
  assert.match(navHtml, /href="[^"]*\/forum\/"/, "The primary navigation must link to the forum");
  record("home navigation, archive listing, article body, topics, and replies all render");

  /* ------------------------------------------------------------------
   * 8b. Follow every link the forum actually renders.
   *
   * Checking a hand-written list of routes is what let a dead /member-login/
   * link ship: the page it lived on returned 200, so nothing noticed. Crawl
   * what the pages really link to instead of what the test author remembered.
   * ---------------------------------------------------------------- */
  const origin = new URL(server.serverUrl).origin;
  const seedPages = [
    "/",
    "/forum/",
    new URL(qualification.permalink).pathname,
    new URL(qualification.category_link).pathname,
    "/forum/topic/hydrology/",
    "/forum-endorsement/",
    new URL(archive.published_url).pathname,
  ];

  const checked = new Map();
  const broken = [];

  for (const page of seedPages) {
    const html = await (await fetch(new URL(page, server.serverUrl))).text();
    const hrefs = [...html.matchAll(/href="([^"#]+)"/g)].map((m) => m[1].replace(/&amp;/g, "&"));

    for (const href of hrefs) {
      let target;
      try {
        target = new URL(href, origin);
      } catch {
        continue;
      }
      // Only our own pages, and skip wp-admin (needs a session) and assets.
      if (target.origin !== origin) continue;
      if (/^\/wp-(admin|login|json|content|includes)/.test(target.pathname)) continue;
      if (/\.(css|js|png|jpe?g|svg|webp|ico|docx|xml)$/i.test(target.pathname)) continue;

      const key = target.pathname + target.search;
      if (checked.has(key)) continue;

      const status = (await fetch(new URL(key, server.serverUrl))).status;
      checked.set(key, status);
      if (status >= 400) {
        broken.push(`${key} -> ${status} (linked from ${page})`);
      }
    }
  }

  assert.ok(checked.size >= 15, `Crawl should have covered the site, only saw ${checked.size} links`);
  assert.deepEqual(broken, [], `Forum pages link to dead URLs:\n  ${broken.join("\n  ")}`);
  record("every internal link rendered by the forum resolves", `${checked.size} unique URLs crawled`);

  /* ------------------------------------------------------------------
   * 8c. Administrator and member-facing screens actually render.
   *
   * These were written but never executed. A fatal in the settings screen or
   * the user-profile panel would only have shown up the first time somebody
   * opened it.
   * ---------------------------------------------------------------- */
  const screens = await json(`
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
$out = array();

$render = function ( callable $callback ) {
    ob_start();
    try {
        $callback();
        $html = (string) ob_get_clean();
        return array( 'ok' => true, 'length' => strlen( $html ), 'html' => $html );
    } catch ( Throwable $e ) {
        ob_end_clean();
        return array( 'ok' => false, 'error' => $e->getMessage() );
    }
};

wp_set_current_user( 1 );
$out['settings'] = $render( array( 'CYWater_Forum_Settings', 'render_settings_page' ) );
$out['user_panel'] = $render( function () {
    CYWater_Forum_Admin::render_user_section( get_user_by( 'id', ${candidateId} ) );
} );
$out['users_column'] = CYWater_Forum_Admin::render_users_column( '', 'cyw_forum', ${candidateId} );
$out['users_columns'] = array_key_exists( 'cyw_forum', CYWater_Forum_Admin::add_users_column( array() ) );

// The endorsement form in each of its three states.
$out['shortcode_admin'] = $render( function () { echo CYWater_Forum_Endorsement::endorsement_shortcode(); } );
wp_set_current_user( ${candidateId} );
$out['shortcode_candidate'] = $render( function () { echo CYWater_Forum_Endorsement::endorsement_shortcode(); } );
wp_set_current_user( 0 );
$out['shortcode_logged_out'] = $render( function () { echo CYWater_Forum_Endorsement::endorsement_shortcode(); } );

echo wp_json_encode( $out );`);

  for (const [name, result] of Object.entries(screens)) {
    if (result && typeof result === "object" && "ok" in result) {
      assert.equal(result.ok, true, `${name} must render without throwing: ${result.error ?? ""}`);
      assert.ok(result.length > 0, `${name} must render something`);
      assert.doesNotMatch(
        result.html,
        /Fatal error|Parse error|Warning:|Notice:/,
        `${name} must not emit a PHP diagnostic`
      );
    }
  }
  assert.match(screens.settings.html, /Endorsements required to publish/, "Settings must expose the endorsement policy");
  assert.match(screens.settings.html, /not implemented/i, "Settings must say the AI section is not implemented");
  assert.match(screens.user_panel.html, /CYWater forum authorship/, "The user editor must show the authorship record");
  assert.equal(screens.users_columns, true, "The Users list must offer the forum column");
  assert.ok(String(screens.users_column).length > 0, "The Users list column must render a value");
  assert.match(screens.shortcode_logged_out.html, /Sign in/, "Signed-out visitors must be offered sign-in");
  record("settings screen, user-editor panel, users column, and all endorsement states render");

  /* ------------------------------------------------------------------
   * 8d. Administrator deletion actually works and cleans up after itself.
   *
   * Custom capabilities are easy to get wrong in a way that only shows up when
   * somebody tries to remove something: a missing delete_* primitive leaves an
   * administrator staring at a post they cannot bin.
   * ---------------------------------------------------------------- */
  const deletion = await json(`
wp_set_current_user( 1 );

$post_id = wp_insert_post( array(
    'post_type'    => 'cyw_forum_post',
    'post_status'  => 'publish',
    'post_title'   => 'Temporary article for deletion QA',
    'post_content' => '<p>Created by the test and removed again.</p>',
    'post_author'  => ${candidateId},
) );
$terms = get_terms( array( 'taxonomy' => 'cyw_forum_category', 'hide_empty' => false ) );
wp_set_object_terms( $post_id, array( $terms[0]->term_id ), 'cyw_forum_category' );

$comment_id = wp_new_comment( array(
    'comment_post_ID'      => $post_id,
    'comment_content'      => 'Temporary reply for deletion QA.',
    'user_id'              => ${candidateId},
    'comment_author'       => 'New Candidate',
    'comment_author_email' => 'candidate@example.org',
), true );
$comment_id = is_wp_error( $comment_id ) ? 0 : $comment_id;

$caps = array(
    'admin_delete_post'  => current_user_can( 'delete_post', $post_id ),
    'admin_edit_post'    => current_user_can( 'edit_post', $post_id ),
    'author_delete_own'  => user_can( ${candidateId}, 'delete_post', $post_id ),
    'other_delete_other' => user_can( ${authorId}, 'delete_post', $post_id ),
);

// Trash, confirm, restore, then delete permanently.
wp_trash_post( $post_id );
$trashed = get_post_status( $post_id );
wp_untrash_post( $post_id );
$untrashed = get_post_status( $post_id );
$untrashed_title = get_the_title( $post_id );

$comment_trashed = $comment_id ? (bool) wp_trash_comment( $comment_id ) : null;
$comment_gone_after_trash = $comment_id ? ( '1' !== (string) get_comment( $comment_id )->comment_approved ) : null;
if ( $comment_id ) {
    wp_delete_comment( $comment_id, true );
}
$comment_deleted = $comment_id ? ( null === get_comment( $comment_id ) ) : null;

wp_delete_post( $post_id, true );
$post_deleted = ( null === get_post( $post_id ) );

// Deleting the article must take its comments with it, not orphan them.
$orphans = get_comments( array( 'post_id' => $post_id, 'count' => true ) );

echo wp_json_encode( array(
    'caps'                     => $caps,
    'trashed'                  => $trashed,
    'untrashed'                => $untrashed,
    'comment_trashed'          => $comment_trashed,
    'comment_unapproved'       => $comment_gone_after_trash,
    'comment_deleted'          => $comment_deleted,
    'post_deleted'             => $post_deleted,
    'orphan_comments'          => (int) $orphans,
    'archive_count_after'      => (int) wp_count_posts( 'cyw_forum_post' )->publish,
) );`);

  assert.equal(deletion.caps.admin_delete_post, true, "An administrator must be able to delete a forum article");
  assert.equal(deletion.caps.admin_edit_post, true, "An administrator must be able to edit a forum article");
  assert.equal(deletion.caps.author_delete_own, true, "An author must be able to delete their own article");
  assert.equal(deletion.caps.other_delete_other, false, "An author must not be able to delete someone else's article");
  assert.equal(deletion.trashed, "trash", "Trashing must move the article to the bin");
  assert.equal(
    deletion.untrashed,
    "publish",
    "Restoring a published article must republish it, not silently leave it as a draft"
  );
  assert.equal(deletion.comment_trashed, true, "An administrator must be able to trash a reply");
  assert.equal(deletion.comment_unapproved, true, "A trashed reply must stop being approved");
  assert.equal(deletion.comment_deleted, true, "An administrator must be able to delete a reply outright");
  assert.equal(deletion.post_deleted, true, "Permanent deletion must remove the article");
  assert.equal(deletion.orphan_comments, 0, "Deleting an article must not orphan its replies");
  record("administrator can trash, restore, and permanently delete articles and replies");

  // The round trip that actually matters to a reader: an article that is binned
  // and restored must reappear on the public archive, not disappear silently.
  const restoreCheck = await json(`
wp_set_current_user( 1 );
$post_id = wp_insert_post( array(
    'post_type'    => 'cyw_forum_post',
    'post_status'  => 'publish',
    'post_title'   => 'Restore round trip QA article',
    'post_content' => '<p>Binned and brought back.</p>',
    'post_author'  => ${candidateId},
) );
echo wp_json_encode( array( 'post_id' => $post_id, 'url' => get_permalink( $post_id ) ) );`);

  const beforeTrash = await (await fetch(new URL("/forum/", server.serverUrl))).text();
  assert.match(beforeTrash, /Restore round trip QA article/, "The article must be listed before trashing");

  await php(`wp_set_current_user( 1 ); wp_trash_post( ${restoreCheck.post_id} ); echo 'trashed';`);
  const whileTrashed = await (await fetch(new URL("/forum/", server.serverUrl))).text();
  assert.doesNotMatch(whileTrashed, /Restore round trip QA article/, "A trashed article must leave the archive");

  await php(`wp_set_current_user( 1 ); wp_untrash_post( ${restoreCheck.post_id} ); echo 'restored';`);
  const afterRestore = await (await fetch(new URL("/forum/", server.serverUrl))).text();
  assert.match(
    afterRestore,
    /Restore round trip QA article/,
    "A restored article must come back to the archive rather than vanish as a draft"
  );
  const restoredPage = await fetch(new URL(new URL(restoreCheck.url).pathname, server.serverUrl));
  assert.equal(restoredPage.status, 200, "The restored article's own page must be public again");

  await php(`wp_set_current_user( 1 ); wp_delete_post( ${restoreCheck.post_id}, true ); echo 'cleaned';`);
  record("trash and restore round trip returns the article to the public archive");

  // The public surfaces must not still be advertising the deleted article.
  const archiveAfterDelete = await (await fetch(new URL("/forum/", server.serverUrl))).text();
  assert.doesNotMatch(
    archiveAfterDelete,
    /Temporary article for deletion QA/,
    "A deleted article must disappear from the archive"
  );
  record("deleted article leaves no trace on the public archive");

  /* ------------------------------------------------------------------
   * 9. The AI seam stays dormant
   * ---------------------------------------------------------------- */
  const aiDisabled = await fetch(new URL(`/wp-json/cywater/v1/forum-reaction/${forumPostId}`, server.serverUrl));
  assert.equal(aiDisabled.status, 204, "The reaction endpoint must answer 204 while disabled");

  await php(`
$settings = get_option( 'cywater_forum_settings', array() );
$settings = array_merge( CYWater_Forum_Settings::defaults(), $settings );
$settings['ai_reaction_enabled'] = true;
update_option( 'cywater_forum_settings', $settings );
echo 'enabled';`);

  const aiEnabled = await fetch(new URL(`/wp-json/cywater/v1/forum-reaction/${forumPostId}`, server.serverUrl));
  assert.equal(aiEnabled.status, 204, "With no provider attached the endpoint must still answer 204");

  const articleWithAiOn = await (
    await fetch(new URL(new URL(qualification.permalink).pathname, server.serverUrl))
  ).text();
  assert.doesNotMatch(articleWithAiOn, /AI-generated perspective/, "No AI panel may render without a provider");
  assert.doesNotMatch(articleWithAiOn, /forum-reaction/, "No reaction markup may leak into the cached page");
  record("AI endpoint answers 204 enabled and disabled; page renders identically with no trace");

  const missingPost = await fetch(new URL("/wp-json/cywater/v1/forum-reaction/999999", server.serverUrl));
  assert.equal(missingPost.status, 204, "An unknown article must answer 204 rather than an error");
  record("AI endpoint fails closed for an unknown article");

  /* ------------------------------------------------------------------
   * 10. Cleanup
   * ---------------------------------------------------------------- */
  const cleanup = await json(`
$settings = get_option( 'cywater_forum_settings', array() );
$settings['ai_reaction_enabled'] = false;
update_option( 'cywater_forum_settings', $settings );
echo wp_json_encode( array( 'ai_off' => ! CYWater_Forum_Settings::is_enabled( 'ai_reaction_enabled' ) ) );`);
  assert.equal(cleanup.ai_off, true);

  console.log(`\n${results.length} forum checks passed.`);
  console.log(
    "\nNot covered here (SQLite/Playground limits, must pass on staging): real PMPro\n" +
      "membership state, real Postmark delivery, and browser-rendered layout."
  );
} finally {
  await server[Symbol.asyncDispose]();
}
