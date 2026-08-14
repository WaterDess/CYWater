import { access, readFile, readdir } from "node:fs/promises";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const required = [
  ".wp-env.json",
  "wordpress/blueprint.json",
  "scripts/build-wp-env-config.mjs",
  "scripts/prepare-wordpress-vendor.mjs",
  "scripts/test-playground.mjs",
  "scripts/cywater-staging-ticketing-qa.php",
  "wordpress/wp-content/plugins/cywater-core/data/seed.json",
  "wordpress/wp-content/themes/cywater/style.css",
  "wordpress/wp-content/themes/cywater/functions.php",
  "wordpress/wp-content/themes/cywater/front-page.php",
  "wordpress/wp-content/themes/cywater/home.php",
  "wordpress/wp-content/themes/cywater/archive-cyw_event.php",
  "wordpress/wp-content/themes/cywater/archive-cyw_award.php",
  "wordpress/wp-content/themes/cywater/page-about.php",
  "wordpress/wp-content/themes/cywater/page-board.php",
  "wordpress/wp-content/themes/cywater/page-bylaws.php",
  "wordpress/wp-content/themes/cywater/page-membership.php",
  "wordpress/wp-content/themes/cywater/page-contact.php",
  "wordpress/wp-content/plugins/cywater-core/cywater-core.php",
  "wordpress/wp-content/plugins/cywater-membership/cywater-membership.php",
  "wordpress/wp-content/plugins/cywater-environment/cywater-environment.php",
  "wordpress/wp-content/plugins/cywater-forum/cywater-forum.php",
  "wordpress/wp-content/plugins/cywater-forum/includes/defaults.php",
  "wordpress/wp-content/plugins/cywater-forum/includes/class-cywater-forum-settings.php",
  "wordpress/wp-content/plugins/cywater-forum/includes/class-cywater-forum-content.php",
  "wordpress/wp-content/plugins/cywater-forum/includes/class-cywater-forum-roles.php",
  "wordpress/wp-content/plugins/cywater-forum/includes/class-cywater-forum-endorsement.php",
  "wordpress/wp-content/plugins/cywater-forum/includes/class-cywater-forum-comments.php",
  "wordpress/wp-content/plugins/cywater-forum/includes/class-cywater-forum-ai.php",
  "wordpress/wp-content/themes/cywater/archive-cyw_forum_post.php",
  "wordpress/wp-content/themes/cywater/single-cyw_forum_post.php",
  "wordpress/wp-content/themes/cywater/comments.php",
  "wordpress/wp-content/themes/cywater/author.php",
];

for (const relativePath of required) {
  await access(path.join(root, relativePath));
}

const forbidden = [
  /(?:sk|rk|pk)_(?:live|test)_[A-Za-z0-9]{8,}/,
  /whsec_[A-Za-z0-9]{8,}/,
];
const textExtensions = new Set([".example", ".js", ".json", ".md", ".mjs", ".php", ".yaml", ".yml"]);

async function findTextFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    const absolute = path.join(directory, entry.name);
    if (entry.isDirectory()) files.push(...(await findTextFiles(absolute)));
    else if (textExtensions.has(path.extname(entry.name))) files.push(absolute);
  }
  return files;
}

const filesToScan = [
  path.join(root, ".env.example"),
  path.join(root, "package.json"),
  path.join(root, "wordpress", "blueprint.json"),
  path.join(root, "wordpress", "docker-compose.mailpit.yml"),
  ...(await findTextFiles(path.join(root, "scripts"))),
  ...(await findTextFiles(path.join(root, "wordpress", "docs"))),
  ...(await findTextFiles(path.join(root, "wordpress", "wp-content"))),
];
for (const file of filesToScan) {
  const contents = await readFile(file, "utf8");
  for (const pattern of forbidden) {
    if (pattern.test(contents)) {
      throw new Error(`Secret-like value found in ${path.relative(root, file)}.`);
    }
  }
}

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function normalizeNewlines(contents) {
  return contents.replace(/\r\n?/g, "\n");
}

function assertMarkers(contents, markers, label) {
  const normalizedContents = normalizeNewlines(contents);
  for (const marker of markers) {
    const normalizedMarker = normalizeNewlines(marker);
    assert(normalizedContents.includes(normalizedMarker), `${label} is missing required marker: ${marker}`);
  }
}

const sourcePath = path.join(root, "assets", "js", "content.js");
const source = await readFile(sourcePath, "utf8");
const sandbox = { window: {} };
vm.runInNewContext(source, sandbox, { filename: sourcePath, timeout: 5_000 });
const registry = sandbox.window.CYWaterContent;
const seed = JSON.parse(
  await readFile(
    path.join(root, "wordpress", "wp-content", "plugins", "cywater-core", "data", "seed.json"),
    "utf8"
  )
);

assert(seed.seedRevision >= 3, "WordPress seed revision must be at least 3.");
assert(seed.generatedFrom === "assets/js/content.js", "Seed source marker is incorrect.");
assert(
  JSON.stringify(seed.articles) === JSON.stringify(registry.ARTICLES),
  "WordPress article seed differs from the static content registry."
);
assert(
  JSON.stringify(seed.events) === JSON.stringify(registry.EVENTS),
  "WordPress event seed differs from the static content registry."
);
assert(
  JSON.stringify(seed.awards) === JSON.stringify(registry.AWARDS),
  "WordPress award seed differs from the static content registry."
);
assert(
  JSON.stringify(seed.newsOrder) === JSON.stringify(registry.NEWS_LIST),
  "WordPress news ordering differs from the static site."
);
assert(
  JSON.stringify(seed.eventOrder) === JSON.stringify(registry.EVENT_LIST),
  "WordPress event ordering differs from the static site."
);
for (const event of Object.values(seed.events)) {
  if (event.image) {
    assert(event.imageAlt, `Missing event image description for ${event.title}.`);
  }
}

for (const key of ["home", "about", "board", "bylaws", "membership", "contact"]) {
  assert(seed.pages?.[key], `Missing editable page seed: ${key}.`);
}

const parityFiles = {
  staticBoard: await readFile(path.join(root, "about", "board.html"), "utf8"),
  staticBylaws: await readFile(path.join(root, "about", "bylaws.html"), "utf8"),
  staticMembership: await readFile(path.join(root, "membership", "index.html"), "utf8"),
  wordpressBoard: await readFile(
    path.join(root, "wordpress", "wp-content", "themes", "cywater", "page-board.php"),
    "utf8"
  ),
  wordpressBylaws: await readFile(
    path.join(root, "wordpress", "wp-content", "themes", "cywater", "page-bylaws.php"),
    "utf8"
  ),
  wordpressMembership: await readFile(
    path.join(root, "wordpress", "wp-content", "themes", "cywater", "page-membership.php"),
    "utf8"
  ),
  staticPagesCss: await readFile(path.join(root, "assets", "css", "pages.css"), "utf8"),
  wordpressPagesCss: await readFile(
    path.join(root, "wordpress", "wp-content", "themes", "cywater", "assets", "css", "pages.css"),
    "utf8"
  ),
  // Theme-owned and never overwritten by assets:sync, unlike the mirrored
  // stylesheets above.
  wordpressCss: await readFile(
    path.join(root, "wordpress", "wp-content", "themes", "cywater", "wordpress.css"),
    "utf8"
  ),
};

const boardMarkers = [
  "Board of Directors.",
  "Leadership update in progress.",
  "Board composition",
  "Committee framework",
  "Awards Committee",
  "Tellers Committee",
];
assertMarkers(parityFiles.staticBoard, boardMarkers, "Static Board page");
assertMarkers(parityFiles.wordpressBoard, boardMarkers, "WordPress Board template");

assertMarkers(
  parityFiles.staticBylaws,
  ["CYWater Bylaws", "ARTICLE I", "ARTICLE IX", "Download bylaws (.docx)", 'data-target="a9"'],
  "Static Bylaws page"
);
assertMarkers(
  parityFiles.wordpressBylaws,
  ["CYWater Bylaws", "Download bylaws (.docx)", "data-target", "the_content()"],
  "WordPress Bylaws template"
);
assert(
  !/<div class="prose"\s+data-reveal>/.test(parityFiles.staticBylaws),
  "The static Bylaws prose must render immediately instead of waiting for a whole-document reveal."
);
assert(
  !/<div class="prose entry-content"\s+data-reveal>/.test(parityFiles.wordpressBylaws),
  "The WordPress Bylaws prose must render immediately instead of waiting for a whole-document reveal."
);
assertMarkers(seed.pages.bylaws.content, ["ARTICLE I", "ARTICLE IX", "Merger or Dissolution"], "Bylaws seed");

const membershipMarkers = [
  "membership-partners",
  "membership-partner-copy",
  "membership-fees",
  "section-head center",
  "fee-table",
  "Professional Member",
  "Student Member",
  "Nonmember",
];
assertMarkers(parityFiles.staticMembership, membershipMarkers, "Static Membership page");
assertMarkers(parityFiles.wordpressMembership, membershipMarkers, "WordPress Membership template");
assertMarkers(
  parityFiles.staticPagesCss,
  [
    ".membership-partner-copy",
    ".membership-fees .table-wrap",
    ".fee-table thead th:not(:first-child)",
    ".fee-table thead th,\n.fee-table tbody th {\n  font-family: var(--font-display);\n  font-size: var(--fs-md);",
  ],
  "Static page stylesheet"
);
// These rules must live in the theme-owned stylesheet. Asserting them against
// the mirrored pages.css is what let `npm run prepare` silently delete them.
assertMarkers(
  parityFiles.wordpressCss,
  [".prose ul.pmpro_list li", ".prose ul.pmpro_list li::before", ".latest-head {"],
  "WordPress-specific theme stylesheet"
);
assert(
  parityFiles.wordpressPagesCss === parityFiles.staticPagesCss,
  "The theme's mirrored pages.css must be byte-identical to the static site's. " +
    "WordPress-only rules belong in wordpress.css, which assets:sync does not overwrite."
);

// The theme's main.js is theme-owned and must keep its accessibility behaviour.
// It was silently reverted once by assets:sync copying the static file over it.
const themeMainJs = await readFile(
  path.join(root, "wordpress", "wp-content", "themes", "cywater", "main.js"),
  "utf8"
);
assertMarkers(
  themeMainJs,
  ["closeMobileNav", 'event.key !== "Escape"', 'querySelector(".faq-a")?.setAttribute("aria-hidden"'],
  "WordPress theme main.js"
);
assert(
  !/setAttribute\("role", "button"\)/.test(themeMainJs),
  "The theme emits real <button> FAQ controls, so it must not carry the static site's div shims. " +
    "This file has been overwritten by assets:sync."
);

const award2025 = seed.awards.find((award) => String(award.year) === "2025");
const award2024 = seed.awards.find((award) => String(award.year) === "2024");
assert(award2025?.outstanding?.length === 2, "The 2025 Outstanding Papers are incomplete.");
assert(award2024?.outstanding?.length === 2, "The 2024 Outstanding Papers are incomplete.");

const forbiddenLegacy = ["trans-menu", "trans-contacts", "trans-newsletter"];
const themeFiles = await findTextFiles(path.join(root, "wordpress", "wp-content", "themes", "cywater"));
for (const file of themeFiles) {
  const contents = await readFile(file, "utf8");
  for (const marker of forbiddenLegacy) {
    assert(!contents.includes(marker), `Legacy translation marker ${marker} remains in ${path.relative(root, file)}.`);
  }
}

const themeImageEntries = await readdir(
  path.join(root, "wordpress", "wp-content", "themes", "cywater", "assets", "img")
);

const ticketingIntegration = await readFile(
  path.join(root, "wordpress", "wp-content", "plugins", "cywater-core", "includes", "class-cywater-content-types.php"),
  "utf8"
);
assertMarkers(
  ticketingIntegration,
  ["tribe_tickets_post_types", "return array( 'cyw_event' );"],
  "CYWater Event Tickets integration"
);
assert(
  !themeImageEntries.includes("placeholders"),
  "Unused legacy placeholder assets must not be included in the WordPress theme."
);

const forumDirectory = path.join(root, "wordpress", "wp-content", "plugins", "cywater-forum");

// The AI reaction is declared but not implemented. Keep it that way until the
// association approves the feature: nothing in the seam may call out to a
// provider, and the endpoint must keep answering 204 by default.
const forumAi = await readFile(path.join(forumDirectory, "includes", "class-cywater-forum-ai.php"), "utf8");
for (const outbound of ["wp_remote_", "curl_", "file_get_contents(", "fsockopen"]) {
  assert(
    !forumAi.includes(outbound),
    `The forum AI seam must stay dormant, but it contains an outbound call: ${outbound}.`
  );
}
assertMarkers(
  forumAi,
  ["new WP_REST_Response( null, 204 )", "cywater_forum_ai_reaction"],
  "Forum AI seam"
);

// Discussion must stay scoped to forum articles. News, Events, Awards, and
// Board roles have never had comments and must not acquire them by accident.
const forumComments = await readFile(
  path.join(forumDirectory, "includes", "class-cywater-forum-comments.php"),
  "utf8"
);
assertMarkers(
  forumComments,
  ["CYWater_Forum_Content::POST_TYPE !== get_post_type( $post_id )", "'comments_open'"],
  "Forum discussion scoping"
);

// Author archives are opened one account at a time. A blanket allow would undo
// the account-enumeration protection in cywater-environment.
const publicSurface = await readFile(
  path.join(root, "wordpress", "wp-content", "plugins", "cywater-environment", "includes", "class-cywater-public-surface.php"),
  "utf8"
);
assertMarkers(publicSurface, ["cywater_public_author_archive_allowed"], "Author archive gate");
const forumContent = await readFile(
  path.join(forumDirectory, "includes", "class-cywater-forum-content.php"),
  "utf8"
);
assertMarkers(
  forumContent,
  ["cywater_public_author_archive_allowed", "return self::published_count( $author_id ) > 0;"],
  "Forum author archive opt-in"
);

console.log(
  `Validated ${required.length} required files, ${seed.newsOrder.length} news records, ` +
    `${seed.eventOrder.length} events, ${seed.awards.length} award years, and scanned ` +
    `${filesToScan.length} text files for credential patterns.`
);
