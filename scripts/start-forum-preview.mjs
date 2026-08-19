/**
 * Disposable forum preview.
 *
 * Starts WordPress on Playground with the real theme and plugins, seeds a few
 * forum articles with images, and creates two accounts so the whole authorship
 * flow can be walked through by hand:
 *
 *   - a member with dues paid, who can publish immediately
 *   - a member without dues, who meets the join gate
 *
 * Everything is thrown away when the process stops. The sign-in details are
 * fixed, local-only fixtures for this disposable site — see the note beside
 * them — and are verified against WordPress before they are printed.
 *
 * Playground runs on SQLite, so PMPro's membership queries do not work and
 * active membership is answered by a local stub. Real membership behaviour is
 * verified on staging.
 */

import { runCLI } from "@wp-playground/cli";
import { randomBytes } from "node:crypto";

const port = Number(process.env.CYWATER_FORUM_PORT || 8890);

const mounts = [
  ["./wordpress/wp-content/themes/cywater", "/wordpress/wp-content/themes/cywater"],
  ["./wordpress/wp-content/plugins/cywater-core", "/wordpress/wp-content/plugins/cywater-core"],
  ["./wordpress/wp-content/plugins/cywater-membership", "/wordpress/wp-content/plugins/cywater-membership"],
  ["./wordpress/wp-content/plugins/cywater-partnerships", "/wordpress/wp-content/plugins/cywater-partnerships"],
  ["./wordpress/wp-content/plugins/cywater-logo-call", "/wordpress/wp-content/plugins/cywater-logo-call"],
  ["./wordpress/wp-content/plugins/cywater-environment", "/wordpress/wp-content/plugins/cywater-environment"],
  ["./wordpress/wp-content/plugins/cywater-forum", "/wordpress/wp-content/plugins/cywater-forum"],
  ["./wordpress/wp-content/plugins/cywater-operations", "/wordpress/wp-content/plugins/cywater-operations"],
  ["./wordpress/runtime/vendor/paid-memberships-pro", "/wordpress/wp-content/plugins/paid-memberships-pro"],
].map(([hostPath, vfsPath]) => ({ hostPath, vfsPath }));

/*
 * Fixed, deliberately obvious sign-in details.
 *
 * These are not secrets and must never become any. They exist only inside a
 * Playground site that listens on 127.0.0.1, holds no real data, and is deleted
 * when this process stops — the same basis on which the docs already publish
 * wp-env's `admin` / `password`. Randomising them per run only produced a
 * failure mode where a password printed by one instance was typed into another.
 *
 * Set CYWATER_FORUM_RANDOM_PASSWORDS=1 to generate throwaway values instead.
 * Nothing here is ever used against staging or production: this script starts a
 * local Playground and cannot reach any other environment.
 */
const useRandom = process.env.CYWATER_FORUM_RANDOM_PASSWORDS === "1";
const password = (label) => (useRandom ? `${label}-${randomBytes(6).toString("hex")}` : `preview-${label}`);
const authorPassword = password("author");
const candidatePassword = password("candidate");

console.log("Starting the CYWater forum preview. This takes a moment on first run.\n");

const server = await runCLI({
  command: "server",
  port,
  php: "8.3",
  wp: "7.0.2",
  "mount-before-install": mounts,
  blueprint: "./wordpress/blueprint.json",
});

async function php(code) {
  const result = await server.playground.run({ code: `<?php\nrequire '/wordpress/wp-load.php';\n${code}` });
  if (result.exitCode !== 0) {
    throw new Error(`Seeding failed: ${result.errors}\n${result.text}`);
  }
  return result.text;
}

// Membership stub. Playground cannot run PMPro's MySQL-specific membership
// queries, so the gate is answered from user meta instead. Real membership is
// tested on staging.
await server.playground.mkdir("/wordpress/wp-content/mu-plugins");
await server.playground.writeFile(
  "/wordpress/wp-content/mu-plugins/preview-membership-stub.php",
  `<?php
/*
 * Playground's background update run writes /wordpress/.maintenance, and this
 * virtual filesystem then fails to read it back, so the whole site answers
 * "Briefly unavailable for scheduled maintenance" or a fatal until restarted.
 * Updates are meaningless in a disposable environment. DISALLOW_FILE_MODS in
 * the blueprint stops the upgrader; this clears any file already left behind.
 */
add_filter( 'automatic_updater_disabled', '__return_true' );
if ( file_exists( ABSPATH . '.maintenance' ) ) {
    @unlink( ABSPATH . '.maintenance' );
}

if ( ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
    function pmpro_hasMembershipLevel( $levels = null, $user_id = null ) {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        return (bool) get_user_meta( $user_id, 'preview_active_membership', true );
    }
}

// Capture endorsement mail so the link can be followed without a mail server.
add_filter( 'wp_mail', function ( $atts ) {
    file_put_contents(
        WP_CONTENT_DIR . '/preview-mail.log',
        "To: {$atts['to']}\\nSubject: {$atts['subject']}\\n{$atts['message']}\\n\\n---\\n\\n",
        FILE_APPEND
    );
    return $atts;
} );
`
);

const seeded = await php(`
$author = wp_insert_user( array(
    'user_login'   => 'forum.author',
    'user_email'   => 'author@example.org',
    'user_pass'    => ${JSON.stringify(authorPassword)},
    'display_name' => 'Dr Wen Li',
    'first_name'   => 'Wen',
    'last_name'    => 'Li',
    'description'  => 'Catchment hydrology and flood forecasting. Writes here about modelling choices that did not work.',
    'role'         => 'subscriber',
) );
update_user_meta( $author, 'preview_active_membership', 1 );
update_user_meta( $author, 'cyw_verified_email', 'author@example.org' );
CYWater_Forum_Endorsement::admin_grant( $author, 1 );

$candidate = wp_insert_user( array(
    'user_login'   => 'forum.candidate',
    'user_email'   => 'candidate@example.org',
    'user_pass'    => ${JSON.stringify(candidatePassword)},
    'display_name' => 'Amara Okafor',
    'description'  => 'Groundwater recharge in semi-arid basins.',
    'role'         => 'subscriber',
) );
// Deliberately NOT given a membership: with endorsement switched off, unpaid
// dues are the gate a visitor actually meets, so this account demonstrates it.
update_user_meta( $candidate, 'cyw_verified_email', 'candidate@example.org' );

function cyw_preview_image( $post_id, $filename ) {
    $source = '/wordpress/wp-content/themes/cywater/assets/img/photos/' . $filename;
    if ( ! file_exists( $source ) ) {
        return;
    }
    $upload = wp_upload_dir();
    $target = trailingslashit( $upload['path'] ) . $filename;
    if ( ! @copy( $source, $target ) ) {
        return;
    }
    $type  = wp_check_filetype( $filename, null );
    $attId = wp_insert_attachment( array(
        'guid'           => trailingslashit( $upload['url'] ) . $filename,
        'post_mime_type' => $type['type'],
        'post_title'     => sanitize_file_name( $filename ),
        'post_status'    => 'inherit',
    ), $target, $post_id );
    if ( is_wp_error( $attId ) || ! $attId ) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $meta = wp_generate_attachment_metadata( $attId, $target );
    if ( $meta ) {
        wp_update_attachment_metadata( $attId, $meta );
    }
    set_post_thumbnail( $post_id, $attId );
}

$categories = array();
foreach ( get_terms( array( 'taxonomy' => 'cyw_forum_category', 'hide_empty' => false ) ) as $term ) {
    $categories[ $term->slug ] = $term->term_id;
}

$articles = array(
    array(
        'title'   => 'What I got wrong about rainfall-runoff lag times',
        'cat'     => 'research-notes',
        'topics'  => array( 'hydrology', 'modelling', 'catchments' ),
        'image'   => 'water-river.jpg',
        'excerpt' => 'Three seasons of catchment data, and the lag time I had assumed was a constant turned out to be nothing of the sort.',
        'body'    => '<p>When I started this work I treated catchment lag time as a fixed property, something you calibrate once and carry forward. Three seasons of data later, that assumption looks indefensible.</p><h2>What the data actually showed</h2><p>Across the eleven gauged sub-catchments, lag time varied by more than a factor of two between wet and dry antecedent conditions. The variation was systematic, not noise, and it tracked soil moisture closely enough that ignoring it produced consistent early bias in every forecast we issued.</p><h2>What I would do differently</h2><p>I would make antecedent wetness an explicit input rather than something the calibration quietly absorbs. It costs one more state variable and removes an entire class of error.</p><p>I am posting this partly because the failed version is more instructive than the tidy one, and papers rarely have room for it.</p>',
    ),
    array(
        'title'   => 'Reviewing for the first time: what nobody tells you',
        'cat'     => 'career',
        'topics'  => array( 'peer-review', 'early-career' ),
        'image'   => 'books-library.jpg',
        'excerpt' => 'A short account of my first year as a reviewer, and the habits I wish I had started with.',
        'body'    => '<p>My first review took eleven hours and produced two pages of notes the authors could do nothing with. My tenth took two hours and was, I think, genuinely useful. The difference was not expertise.</p><h2>Separate the two questions</h2><p>Is the work sound, and is the work interesting? These are different judgements and conflating them is where most unhelpful reviews come from. A sound but narrow paper deserves a different response from an exciting but shaky one.</p><h2>Write the summary first</h2><p>If you cannot state in three sentences what the paper claims and how it supports the claim, you are not ready to comment on the details.</p>',
    ),
    array(
        'title'   => 'Open data is not the same as usable data',
        'cat'     => 'perspectives',
        'topics'  => array( 'data', 'policy', 'open-science' ),
        'image'   => 'water-aerial.jpg',
        'excerpt' => 'Releasing a dataset and making it possible for somebody else to use it are separate pieces of work, and only one of them gets funded.',
        'body'    => '<p>Every funder now asks for a data management plan. Very few ask whether anyone outside the originating group has ever successfully loaded the resulting files.</p><h2>The gap</h2><p>A dataset is usable when a competent stranger can answer a question with it without contacting the authors. By that standard a large share of published hydrological data is technically open and practically closed: undocumented column names, silent unit changes partway through a record, missing-value codes that vary by year.</p><h2>A modest proposal</h2><p>Before release, hand the archive to a colleague in another group with one specific question and no explanation. Whatever they ask you is the documentation you are missing.</p>',
    ),
);

$created = array();
foreach ( $articles as $index => $article ) {
    $post_id = wp_insert_post( array(
        'post_type'    => 'cyw_forum_post',
        'post_status'  => 'publish',
        'post_title'   => $article['title'],
        'post_content' => $article['body'],
        'post_excerpt' => $article['excerpt'],
        'post_author'  => 0 === $index % 2 ? $author : $candidate,
        'post_date'    => gmdate( 'Y-m-d H:i:s', time() - ( $index + 1 ) * DAY_IN_SECONDS ),
    ) );
    if ( isset( $categories[ $article['cat'] ] ) ) {
        wp_set_object_terms( $post_id, array( $categories[ $article['cat'] ] ), 'cyw_forum_category' );
    }
    wp_set_object_terms( $post_id, $article['topics'], 'cyw_forum_topic' );
    cyw_preview_image( $post_id, $article['image'] );
    $created[] = $post_id;
}

// One approved reply and one still awaiting moderation, so both states show.
$approved = wp_new_comment( array(
    'comment_post_ID'      => $created[0],
    'comment_content'      => 'Did the relationship hold in the two smallest sub-catchments? Those are usually where storage assumptions break down for me.',
    'user_id'              => $candidate,
    'comment_author'       => 'Amara Okafor',
    'comment_author_email' => 'candidate@example.org',
), true );
if ( ! is_wp_error( $approved ) ) {
    wp_set_comment_status( $approved, 'approve' );
}

wp_new_comment( array(
    'comment_post_ID'      => $created[0],
    'comment_content'      => 'Seconding the question above, and curious whether you tried a nonlinear storage term.',
    'user_id'              => $author,
    'comment_author'       => 'Dr Wen Li',
    'comment_author_email' => 'author@example.org',
), true );

// Prove the printed credentials actually work rather than assuming they do.
$signin = array();
foreach ( array( 'forum.author' => ${JSON.stringify(authorPassword)}, 'forum.candidate' => ${JSON.stringify(candidatePassword)} ) as $login => $pass ) {
    $user = get_user_by( 'login', $login );
    $auth = wp_authenticate( $login, $pass );
    $signin[ $login ] = array(
        'exists'  => (bool) $user,
        'hash_ok' => $user ? wp_check_password( $pass, $user->user_pass, $user->ID ) : false,
        'auth_ok' => ! is_wp_error( $auth ),
        'error'   => is_wp_error( $auth ) ? $auth->get_error_code() : '',
    );
}

echo wp_json_encode( array(
    'author_id'    => $author,
    'candidate_id' => $candidate,
    'signin'       => $signin,
    'articles'     => count( $created ),
    'author_can'   => CYWater_Forum_Roles::can_publish( $author ),
    'cand_blocked' => CYWater_Forum_Roles::publish_blockers( $candidate ),
    // Resolved, not assumed: PMPro is inactive here so /member-login/ does not
    // exist, and printing it would send the reader to a 404.
    'login_url'    => function_exists( 'cywater_login_url' ) ? cywater_login_url() : wp_login_url(),
    'forum_url'    => get_post_type_archive_link( 'cyw_forum_post' ),
    'endorse_url'  => CYWater_Forum_Endorsement::page_url(),
) );`);

const report = JSON.parse(seeded);
const url = server.serverUrl.replace(/\/$/, "");

// Never advertise a credential that has not been checked.
for (const [login, state] of Object.entries(report.signin)) {
  if (!state.exists || !state.hash_ok || !state.auth_ok) {
    console.error(
      `\nCredential check FAILED for ${login}: ${JSON.stringify(state)}\n` +
        "Refusing to print sign-in details that do not work."
    );
    await server[Symbol.asyncDispose]();
    process.exit(1);
  }
}

console.log(`
────────────────────────────────────────────────────────────────────────
  CYWater forum preview is running

  Site            ${url}/
  Forum           ${report.forum_url}
  Admin           ${url}/wp-admin/

  Sign in         ${report.login_url}

  Endorsed author — can publish straight away
    username      forum.author
    password      ${authorPassword}

  Member without dues — sees the "Join to write" path
    username      forum.candidate
    password      ${candidatePassword}

  WordPress administrator
    username      admin
    password      password

  Seeded ${report.articles} articles, one approved reply, one held for moderation.
  Candidate is blocked by: ${report.cand_blocked.join(", ") || "nothing"}

  To write an article: sign in as forum.author, then open
  ${url}/wp-admin/post-new.php?post_type=cyw_forum_post

  Endorsement is off for launch, so publishing needs dues and a verified email
  only. To exercise the endorsement machinery anyway, turn "Require endorsement"
  on under Settings > CYWater Forum, then sign in as forum.candidate and open
  ${report.endorse_url} and enter author@example.org. No mail server
  runs here, so the message is appended to wp-content/preview-mail.log
  inside the disposable site — the console below prints it for you.

  These sign-in details are fixed and identical on every run, so a password
  from one preview always works in another. They are local-only fixtures for
  this disposable site and are never valid anywhere else.

  Everything is discarded when you stop this process. Press Ctrl+C to stop.
────────────────────────────────────────────────────────────────────────
`);

// Surface captured mail so an endorsement link can be followed by hand.
let lastMailSize = 0;
setInterval(async () => {
  try {
    const log = await server.playground.readFileAsText("/wordpress/wp-content/preview-mail.log");
    if (log.length > lastMailSize) {
      console.log("\n─── captured mail ───\n" + log.slice(lastMailSize).trim() + "\n");
      lastMailSize = log.length;
    }
  } catch {
    // No mail yet.
  }
}, 3000).unref?.();

process.on("SIGINT", async () => {
  console.log("\nStopping the preview.");
  await server[Symbol.asyncDispose]();
  process.exit(0);
});
