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
  "scripts/cywater-staging-partner-qa.php",
  "wordpress/wp-content/plugins/cywater-core/data/seed.json",
  "wordpress/wp-content/themes/cywater/style.css",
  "wordpress/wp-content/themes/cywater/functions.php",
  "wordpress/wp-content/themes/cywater/front-page.php",
  "wordpress/wp-content/themes/cywater/home.php",
  "wordpress/wp-content/themes/cywater/archive-cyw_event.php",
  "wordpress/wp-content/themes/cywater/archive-cyw_award.php",
  "wordpress/wp-content/themes/cywater/template-parts/title-visual.php",
  "wordpress/wp-content/themes/cywater/page-about.php",
  "wordpress/wp-content/themes/cywater/page-board.php",
  "wordpress/wp-content/themes/cywater/page-bylaws.php",
  "wordpress/wp-content/themes/cywater/page-membership.php",
  "wordpress/wp-content/themes/cywater/page-become-a-partner.php",
  "wordpress/wp-content/themes/cywater/page-contact.php",
  "wordpress/wp-content/plugins/cywater-core/cywater-core.php",
  "wordpress/wp-content/plugins/cywater-core/includes/class-cywater-policy-drafts.php",
  "wordpress/wp-content/plugins/cywater-membership/cywater-membership.php",
  "wordpress/wp-content/plugins/cywater-membership/assets/default-avatar.svg",
  "wordpress/wp-content/plugins/cywater-membership/includes/class-cywater-membership-avatars.php",
  "wordpress/wp-content/plugins/cywater-partnerships/cywater-partnerships.php",
  "wordpress/wp-content/plugins/cywater-partnerships/includes/class-cywater-partnerships.php",
  "wordpress/wp-content/plugins/cywater-logo-call/cywater-logo-call.php",
  "wordpress/wp-content/plugins/cywater-logo-call/includes/class-cywater-logo-call.php",
  "wordpress/wp-content/plugins/cywater-logo-call/includes/class-cywater-logo-call-eligibility.php",
  "wordpress/wp-content/plugins/cywater-logo-call/assets/logo-call.css",
  "wordpress/wp-content/plugins/cywater-logo-call/assets/logo-call.js",
  "wordpress/wp-content/plugins/cywater-environment/cywater-environment.php",
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

assert(seed.seedRevision >= 6, "WordPress seed revision must be at least 6.");
assert(seed.generatedFrom === "assets/js/content.js", "Seed source marker is incorrect.");
assert(
  JSON.stringify(seed.articles) === JSON.stringify(registry.ARTICLES),
  "WordPress article seed differs from the static content registry."
);

const expectedEvents = structuredClone(registry.EVENTS);
delete expectedEvents["annual-gathering-2020"];
assert(
  JSON.stringify(seed.events) === JSON.stringify(expectedEvents),
  "WordPress event seed must exclude the misclassified 2020 award ceremony."
);

const expectedAwards = structuredClone(registry.AWARDS);
const expectedAward2020 = expectedAwards.find(({ year }) => String(year) === "2020");
expectedAward2020.ceremony = {
  title: "CYWater Best Paper Award Ceremony — Online 2020",
  date: "December 18, 2020",
  location: "Online",
  image: "gatherings/2020-cover.jpg",
  imageAlt: "Participants in the online 2020 CYWater Best Paper Award Ceremony",
  lead: "The 2020 Best Paper Award Ceremony was held online, with the recognized authors presenting their work.",
};
assert(
  JSON.stringify(seed.awards) === JSON.stringify(expectedAwards),
  "WordPress award seed is missing the normalized 2020 ceremony."
);

const expectedNewsOrder = structuredClone(registry.NEWS_LIST);
expectedNewsOrder.find(({ id }) => id === "bpa-2020-result").alt =
  "Participants in the online 2020 CYWater Best Paper Award Ceremony";
assert(
  JSON.stringify(seed.newsOrder) === JSON.stringify(expectedNewsOrder),
  "WordPress news ordering or award-ceremony description is incorrect."
);

const expectedEventOrder = registry.EVENT_LIST.filter(
  ({ id }) => id !== "annual-gathering-2020"
);
assert(
  JSON.stringify(seed.eventOrder) === JSON.stringify(expectedEventOrder),
  "WordPress event ordering must exclude the 2020 award ceremony."
);
assert(
  JSON.stringify(seed.board) === JSON.stringify([
    { role: "President", personName: "Qiuhong Tang" },
    { role: "President-Elect", personName: "Lifeng Luo" },
    { role: "Treasurer", personName: "Zhenxing Zhang" },
    { role: "Directors-at-Large", personName: "Ming Pan, Chaopeng Shen" },
    { role: "Executive Director", personName: "Vacant (N/A)" },
  ]),
  "WordPress Board seed differs from the confirmed public leadership list."
);
for (const event of Object.values(seed.events)) {
  if (event.image) {
    assert(event.imageAlt, `Missing event image description for ${event.title}.`);
  }
}

const avatarProvider = await readFile(
  path.join(root, "wordpress", "wp-content", "plugins", "cywater-membership", "includes", "class-cywater-membership-avatars.php"),
  "utf8"
);
assertMarkers(
  avatarProvider,
  ["pre_get_avatar_data", "assets/default-avatar.svg", "cyw_profile_photo", "cyw_profile_public"],
  "CYWater avatar provider"
);

const partnershipProvider = await readFile(
  path.join(root, "wordpress", "wp-content", "plugins", "cywater-partnerships", "includes", "class-cywater-partnerships.php"),
  "utf8"
);
assertMarkers(
  partnershipProvider,
  [
    "cywater_partner_application",
    "Guide to Becoming a Partner",
    "board_review",
    "mou_pending",
    "Approved payment URL",
    "block_legacy_partner_checkout",
    "wp_privacy_personal_data_exporters",
    "partner-contribution-amount",
    "Annual contribution after approval",
  ],
  "CYWater partnership workflow"
);

const logoCallProvider = await readFile(
  path.join(root, "wordpress", "wp-content", "plugins", "cywater-logo-call", "includes", "class-cywater-logo-call.php"),
  "utf8"
);
const logoCallEligibility = await readFile(
  path.join(root, "wordpress", "wp-content", "plugins", "cywater-logo-call", "includes", "class-cywater-logo-call-eligibility.php"),
  "utf8"
);
assertMarkers(
  `${logoCallProvider}\n${logoCallEligibility}`,
  [
    "_cywater_logo_call_enabled",
    "CYWater_Logo_Call_Eligibility::can_submit",
    "CYWater_Logo_Call_Eligibility::can_vote",
    "All registered users",
    "Selected active membership levels",
    "Two years of CYWater Professional membership",
    "permanent use of a selected design requires a separate written rights agreement",
    "data-cywater-logo-preview-input",
    "Registered-user voting is a later phase",
    "member-program",
    "cywater-private/logo-call",
  ],
  "CYWater removable Logo Call workflow"
);

const policyDraftProvider = await readFile(
  path.join(root, "wordpress", "wp-content", "plugins", "cywater-core", "includes", "class-cywater-policy-drafts.php"),
  "utf8"
);
assertMarkers(
  policyDraftProvider,
  [
    "Draft for Board Review — Not approved or in effect.",
    "billing-cancellation-refund-policy-draft",
    "data-retention-account-closure-policy-draft",
    "Selection does not itself transfer ownership",
    "wp_robots",
    "noarchive",
  ],
  "CYWater policy drafts"
);

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
  wordpressEventArchive: await readFile(
    path.join(root, "wordpress", "wp-content", "themes", "cywater", "archive-cyw_event.php"),
    "utf8"
  ),
  wordpressTitleVisual: await readFile(
    path.join(root, "wordpress", "wp-content", "themes", "cywater", "template-parts", "title-visual.php"),
    "utf8"
  ),
};

assertMarkers(
  parityFiles.wordpressMembership,
  ["Sponsors and partners", "Become Our Partner", "No payment is requested until Board approval and MOU completion."],
  "WordPress partnership presentation"
);
assert(
  !parityFiles.wordpressMembership.includes("Join as Partner"),
  "WordPress membership template must not expose the former direct Partner checkout."
);

assertMarkers(
  parityFiles.wordpressEventArchive,
  ["wp_get_post_terms", "template-parts/title-visual", "'year'       => $year", "Member Programs", "member-programs"],
  "WordPress event archive"
);
assertMarkers(
  parityFiles.wordpressTitleVisual,
  ['class="title-visual"', 'class="title-visual-year"'],
  "Reusable title visual"
);
assertMarkers(
  parityFiles.wordpressPagesCss,
  [".title-visual {", ".title-visual strong {", ".title-visual-year {"],
  "WordPress title visual styling"
);

const boardMarkers = [
  "Board of Directors.",
  "Board composition",
  "Committee framework",
  "Awards Committee",
  "Tellers Committee",
];
assertMarkers(parityFiles.staticBoard, [...boardMarkers, "Leadership update in progress."], "Static Board page");
assertMarkers(parityFiles.wordpressBoard, boardMarkers, "WordPress Board template");
assertMarkers(parityFiles.wordpressBoard, ["Current Board leadership."], "WordPress Board status");

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
assertMarkers(
  parityFiles.wordpressPagesCss,
  [
    ".prose ul:not(.pmpro_list) li",
    ".prose ul:not(.pmpro_list) li::before",
    ".latest-head { flex-direction: column; align-items: flex-start; }",
  ],
  "WordPress-specific page stylesheet"
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

console.log(
  `Validated ${required.length} required files, ${seed.newsOrder.length} news records, ` +
    `${seed.eventOrder.length} events, ${seed.awards.length} award years, and scanned ` +
    `${filesToScan.length} text files for credential patterns.`
);
