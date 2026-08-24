# CYWater Project Knowledgebase

Keep `AGENT.md`, `AGENTS.md`, and `claude.md` identical whenever project
direction changes.

A Codex client notice that `prompt_cache_retention` is unsupported by the
selected model is an agent/runtime compatibility warning, not a CYWater project
failure or task blocker. Do not stop project work because of this notice. Omit
the unsupported request parameter when that setting is under local control;
otherwise continue the task and report the client issue separately.

## Remote SSH Context

- "联想笔记本", "Lenovo laptop", or "本机" in contrast to the Tsinghua server
  means the Windows laptop reached through SSH alias `lenovo-laptop`.
- Compatible aliases are `laptop-fadk2rhp` and `laptop-tailscale`.
- The Tailscale endpoint is `Lenovo@100.97.75.3:22`; expected host name is
  `LAPTOP-FADK2RHP`.
- Expected ED25519 host key fingerprint:
  `SHA256:X8AVCx8SYtOh8g5aeZvtUO99ZGaGeOFKiXvE0jxizQg`.
- Non-interactive ED25519 authentication is configured. From the Tsinghua
  server, use `ssh lenovo-laptop <command>` for unattended commands.
- For transfers back to the Lenovo laptop, use `scp`, `sftp`, or an SSH data
  stream. If no destination is given, discover the real Desktop with
  `[Environment]::GetFolderPath('Desktop')` and create a clearly named folder.
- Read-only check source and destination before transfer. Afterwards verify file
  count and total bytes; compare SHA-256 for important deliverables. Do not
  overwrite same-name files without explicit permission.
- Never put passwords, private keys, tokens, or other secrets in commands,
  logs, documentation, or project files.
- Hostinger maintenance access must use a dedicated per-workstation SSH key;
  never share or copy its private key. Keep the public key only while that
  workstation remains authorized for CYWater operations, and revoke or rotate
  it during handover, device retirement or loss, personnel changes, or whenever
  ongoing maintenance access is no longer required. Include this review in the
  production handover, but do not remove a still-required maintenance key merely
  because the site has launched.

## Project Identity

CYWater is a static website preview for the International Association of
Contemporary Young Scholars in Water Sciences. It began as a bilingual visual
prototype and is now an English-only, source-backed public preview.

- Live site: https://waterdess.github.io/CYWater/
- Official organization domain: `cywater.org`. Registrar access, legal
  ownership records, renewal responsibility, and DNS handover remain to be
  verified before migration.
- Repository: https://github.com/WaterDess/CYWater
- `main` is the working branch.
- GitHub Pages serves `gh-pages`.
- The original prototype is preserved at `v0.1-prototype` (`e4b5a01`). Never
  move, replace, or publish over this tag.

Publish after local verification:

```powershell
git push origin main
git push origin main:gh-pages
```

Never force-push unless the user explicitly requests it.

## Technical Shape

- Plain static HTML, CSS, and JavaScript
- No bundler, framework, or npm dependency
- Shared header/footer in `assets/js/layout.js`
- Shared interaction and reveal behavior in `assets/js/main.js`
- Structured articles, events, and awards in `assets/js/content.js`
- GitHub Pages hosting

Local preview:

```powershell
python -m http.server 8000
```

Open `http://127.0.0.1:8000/`.

## Local Planning Library

`local/` is an ignored, workstation-only research and planning archive. Never
publish it or store credentials in it. Hosting and WordPress migration planning
starts at `local/hosting/README.md`; that index links to the following notes:

- `01-decision-and-cost.md` - hosting choice, capability gates, budget, and open
  decisions
- `02-wordpress-architecture.md` - theme/plugin boundaries, content models, and
  migration approach
- `03-membership-payments.md` - PMPro, Stripe, membership terms, refunds, and
  transactional email
- `04-environments-deployment.md` - development/staging/production, Cloudflare,
  secrets, deployment, and rollback
- `05-launch-operations-handover.md` - launch QA, backup restore, operations,
  account ownership, and handover
- `06-local-tooling.md` - ignored local runtime inventory, versions, checksums,
  and workstation capability limits
- `07-organization-email.md` - official mailbox, domain identity, role addresses,
  recovery governance, and WordPress/SMTP separation
- `official-sources.md` - official links whose pricing and capabilities must be
  reverified before purchase or implementation

These documents are planning records, not the current implementation. Before
work involving hosting, WordPress, membership, payment, DNS, SMTP, deployment,
or handover, read the index and relevant note first. Keep the current static
site as the source of truth until a WordPress replacement passes acceptance.

## WordPress Integration Track

Production preparation lives on the separate `wordpress-integration` branch.
The `staging` branch records only WordPress snapshots that have already been
deployed to and verified against the actual Hostinger staging environment. It
is not a GitHub Pages source and does not imply that the server is synchronized;
verify the live staging version before advancing it.
The root static site remains the source for GitHub Pages; do not publish the
WordPress branch to `main` or `gh-pages` before staging acceptance.

- Start with `wordpress/docs/README.md` for runtime and document links.
- `themes/cywater` owns presentation only.
- `plugins/cywater-core` owns public content models and initial import.
- `plugins/cywater-membership` owns PMPro levels, profile fields, privacy, and
  the opt-in directory.
- `plugins/cywater-partnerships` owns institutional expressions of interest,
  Board/MOU review state, private applicant status links, and approved payment
  handoff. A partner is not an individual member.
- `plugins/cywater-environment` owns runtime configuration, local mail routing,
  readiness checks, and payment safety gates.
- Every new or modified public form control—including file inputs, buttons,
  selects, checkboxes, text fields, and textareas—must reuse the established
  CYWater design tokens and interaction language. Do not expose an unstyled
  browser-default or third-party control when an accepted CYWater equivalent
  already exists; verify desktop, mobile, focus, hover, disabled, and error
  states on staging before calling the surface complete.
- Paid Memberships Pro owns accounts, orders, membership state, and Stripe
  gateway/webhook behavior. Do not implement a parallel transaction engine.
- Playground on port `8890` is a disposable content/theme review environment;
  it does not activate PMPro because PMPro 3.8.2 has MySQL-specific queries that
  are incompatible with Playground SQLite. `wp-env` on port `8888`, MySQL,
  Mailpit, and an HTTPS Stripe Sandbox callback are required for complete
  integration acceptance.
- Normal setup runs preserve imported WordPress posts and metadata. Force import
  is destructive and requires a database backup plus explicit approval.
- Production Postmark and Stripe Live are now authorized only through the
  scoped runtime controls recorded in the current production snapshot below.
  Real charges remain user-submitted actions, and refunds require action-time
  confirmation. Bank details, credentials, and other secrets remain outside
  Git.

### Temporary Integration Snapshot (2026-07-23)

- The production-preparation snapshot is commit `fda08d5` on
  `wordpress-integration`; CI passed and produced a review artifact.
- This is not a GitHub Pages frontend release. `main` and `gh-pages` remain the
  static public preview, and `v0.1-prototype` remains unchanged.
- The snapshot contains the custom WordPress theme plus separate
  `cywater-core`, `cywater-membership`, and `cywater-environment` plugins.
- Content import, editable public records, PMPro level/profile/privacy policy,
  Stripe Sandbox safety gates, Mailpit routing, CI, and handover documents are
  prepared. Real PMPro/MySQL, Stripe webhook, SMTP, backup, and visual-browser
  acceptance still require staging services and organization-owned accounts.
- Playground on the Tsinghua server is reachable only from that host unless a
  deliberate secure remote-access route is configured. Do not present its
  `127.0.0.1` URL as a Lenovo-laptop preview. No Playground service is expected
  to remain running after the review task.

### Temporary Staging Snapshot (2026-08-19)

- Hostinger staging is available at `https://staging.cywater.org/` with the
  CYWater `0.6.31` theme, CYWater Membership `0.8.6`, CYWater Partnerships
  `0.1.3`, CYWater Logo Call `0.2.3`, CYWater Forum `0.4.0`, CYWater
  Environment `0.5.5`, CYWater Core `0.6.5`, CYWater Operations `0.1.13`, and
  Event Tickets `5.29.1`.
- On 2026-08-20 the non-Logo production-clean candidate was atomically deployed
  to staging after a fresh database and complete custom-code backup at
  `/home/u111638297/cywater-release-backups/production-clean-predeploy-20260820T011800Z`.
  All seven deployed theme/plugin manifests match the reviewed artifact.
  Account-security (45), membership-term (24), Forum (140), Operations (402),
  editor (38), publishing (21), and policy (42) assertions passed; Partner,
  ticketing, lifecycle, invoice, and cleanup suites also passed. Logo Call
  remains unchanged at `0.2.3` on staging and is omitted from the production-
  clean artifact. The Forum staging-preview article was moved to trash after
  the backup. Production remains No-Go until the production-data purity gate,
  policy/email approval, final browser/multi-network checks, restore rehearsal,
  DNS/SSL, and controlled payment gates are complete.
- Dedicated Hostinger SSH access from the Lenovo workstation was established
  and independently verified with public-key authentication on 2026-08-02.
  The private key remains local and must never be copied into the repository.
  Hostinger uses the non-default endpoint `45.130.228.213:65002` for user
  `u111638297`; port 22 times out and must not be used as a health signal. On
  2026-08-20 hPanel reported SSH `ACTIVE`, and an explicit
  `cywater-hostinger-ed25519` identity connection on port 65002 succeeded.
- The theme and all three CYWater plugin trees were fully redeployed from the
  Lenovo workspace after the profile and environment fixes. A 104-file SHA-256 comparison
  found zero missing, extra, or different staging files; all custom PHP files
  and the key account-flow HTTP routes passed post-deploy checks.
- A live WP-CLI check confirmed WordPress `7.0.4` and PHP CLI `8.3.30` on
  staging. Five malformed inactive CYWater/PMPro upload directories were
  removed on 2026-08-03 after exact-path and inactive-status verification;
  active plugin directories were untouched and plugin-list warnings cleared.
- Theme `0.6.2` connects the Student, Professional, and Lifetime cards to their
  PMPro checkout levels and prevents PMPro order/account lists from inheriting
  the long-form article bullet and indentation rules. Partner is no longer a
  membership card or public PMPro checkout.
  The accepted typography, palette, imagery, motion, and responsive system are
  otherwise unchanged. It also fixes the observed 375px home-page overflow and
  gives the mobile menu and Membership FAQ standard accessible control
  relationships. Real-browser desktop/mobile checks passed for home,
  Membership, sign-in, registration, and the logged-out checkout gate before
  the `0.5.7` typography-neutral wrapper follow-up, whose live HTML/CSS was
  verified structurally; authenticated cross-browser verification remains open.
- Theme `0.6.2` also presents the full association name in the home Hero, keeps
  its English mission headline to two deliberate desktop lines at a reduced
  display size, and proportionally reduces the decorative water-drop outline.
  It scopes the PMPro Account avatar to its requested `48px` size and styles
  the native Member Profile file control with existing CYWater button tokens,
  without changing avatar or upload behavior. Live HTML, asset versions, PHP
  syntax, and CSS selectors passed; screenshot-level browser control timed out,
  so final authenticated visual review remains open.
- On 2026-08-10 the 2020 online Best Paper Award record was corrected from an
  Annual Gathering to `Best Paper Award Ceremony` and rendered under the 2020
  Award. The exact misclassified Event was moved to the WordPress trash rather
  than permanently deleted. Events without verified photographs now use one
  reusable two-line title/year visual, matching the accepted News/Award tile
  language. The GitHub Pages original was not changed.
- On 2026-08-10 the association confirmed the public Board records: President
  Qiuhong Tang; President-Elect Lifeng Luo; Treasurer Zhenxing Zhang;
  Directors-at-Large Ming Pan and Chaopeng Shen; Executive Director vacant.
  Staging stores these in the existing editable Board-role model with public
  display enabled. Affiliations and terms remain blank because they were not
  supplied. The GitHub Pages original was not changed.
- On 2026-08-10 institutional Partner was separated from individual membership.
  The public `Become Our Partner` action now opens `Guide to Becoming a Partner`
  and accepts only an expression of interest. CYWater Partnerships `0.1.0`
  stores a private application with `Submitted`, `Board review`, `MOU pending`,
  `Approved to pay`, `Declined`, and `Payment received` states. A salted-hash
  access link shows the applicant only their own status; an HTTPS payment link
  appears only after approval. The historical PMPro Partner level was preserved
  but public signup was disabled, direct level-4 checkout redirects to the guide,
  and Partner records are excluded from the member directory. A self-cleaning
  staging QA passed application, token, pre-approval payment denial, approved
  payment visibility, invalid-token rejection, and cleanup checks. Screenshot-
  level browser QA timed out; live HTML, HTTP redirects, PHP lint, and desktop/
  mobile CSS structure were verified instead.
- On 2026-08-12 four editable policy pages were created and published on
  staging solely as direct-link Board review surfaces:
  Privacy Notice, Terms of Use, Billing/Cancellation/Refund, and Data Retention/
  Account Closure. Each is marked `Draft for Board Review — Not approved or in
  effect`, is absent from navigation, carries `noindex`, `nofollow`, and
  `noarchive`, and is preserved from later setup overwrites. Production setup
  still creates missing policy pages as drafts. CYWater confirmed that its Stripe settlement bank account is
  present and its named representative is association-authorized; do not record
  bank, birth-date, home-address, tax-ID, or credential values in Git.
- On 2026-08-18 CYWater Core `0.6.1` revised all four policy review surfaces for
  a conservative initial launch. The proposed membership rule is: **All
  membership sales are final and non-refundable.** Cancelling renewal stops
  future charges only and does not refund or credit the current term. Duplicate,
  technical, unauthorized or fraudulent transactions, chargebacks, processor
  reversals, and non-waivable rights are handled as billing corrections or
  disputes rather than ordinary membership refunds. Membership and Event orders
  remain separate, and every paid Event must publish its own cancellation and
  refund terms before registration can open. The 42-check staging policy QA
  passed, but these pages remain noindex, absent from navigation, and explicitly
  not approved or in effect until Board and legal review.
- CYWater Operations `0.1.12` is deployed with four composable operational role
  bundles: Content & Event Editor, Community Moderator, Program Reviewer, and
  Governance Approver. There is no ordinary membership-refund role. Program
  Reviewer uses one review workflow to record Logo Call shortlist and reward-
  fulfillment state but cannot create, publish, or delete submissions or
  protected files; the real reward grant remains an Administrator action. The
  paid-Event adapter and its UI, cart, checkout, and final Stripe REST gates fail
  closed on missing or invalid readiness, configuration, approval fingerprint,
  or strict audit. Its self-cleaning staging QA passed 402 assertions. This does
  not assign a real staff role.
  The Administrator assigns access one account at a time under **Users ->
  CYWater staff access**: the default directory shows only already-authorized
  staff, search locates any account by name/login/email, the account editor
  exposes identity and base-role context, and the Users list shows access badges
  plus a direct management action. The Users row now keeps Account, Member
  record and Staff access visible and preserves low-frequency nonce-bearing
  actions inside an accessible More menu. Delegated staff use the capability-
  filtered menu or Dashboard **CYWater work areas** widget; a Community
  Moderator sees only Dashboard, Media, Forum, Comments and Profile. The full
  Administrator retains every route but the sidebar is grouped into Content,
  Community & programs, Membership & accounts, and a collapsed Site system
  section. The irrelevant Posts count and duplicate PMPro membership-level
  column are removed while CYWater's authoritative account, membership, Forum,
  staff-access and WordPress role columns remain. Operations `0.1.11` also corrects the Governance
  Approver Dashboard shortcut so **Partner applications** opens the registered
  `cyw_partner_app` work area instead of the retired route slug. The staging
  list/search UI was visually checked. Version `0.1.9` makes each Administrator
  and delegated-staff work-area heading a server-rendered fallback, so Content,
  Community & programs, Membership & accounts, Site system, and My work remain
  visible when the optional collapse script is delayed or unavailable. The
  script now also initializes after an already-fired DOM-ready event, and the
  version bump invalidates the prior seven-day static asset URL. Version
  `0.1.10` additionally attaches the critical menu-group and Dashboard work-area
  styles inline to WordPress' required core admin stylesheet. Version `0.1.11`
  also inlines the grouping and collapse script into the required core admin
  script, eliminating a separate navigation request. Fresh and no-cache
  sessions therefore use the same grouping and collapse logic whenever the
  admin page itself loads. Version `0.1.12` confines the removable Logo Call
  participation panel to the Event that is already enabled as the Logo Call
  host. Ordinary Events neither display nor accept those exceptional settings;
  the enabled host can still be disabled without changing Logo Call data
  ownership. The 402-check suite passed, and
  independent marker queries returned zero temporary Operations users, posts,
  or audit rows. All 13 active Operations files match the local candidate with
  manifest SHA-256 `4e79fd88b7ddf1baf9f1f38ebfcf2993b933692003fdfd6d08fe31cab4c56564`.
  A full database export and the complete pre-release `0.1.11` plugin tree are
  retained under
  `/home/u111638297/cywater-release-backups/operations-0.1.11-pre-0.1.12-20260819T045140Z`.
  Hostinger disables the PHP process functions required by `wp db export`, so
  the database backup used `mysqldump` without exposing configuration values.
  Unused Hostinger AI Assistant, Easy Onboarding, and Reach plugins are inactive
  but retained for reversible recovery; their obsolete AI-theme cron was removed.
- On 2026-08-19 Core `0.6.2`, Operations `0.1.11`, and theme `0.6.20`
  introduced a focused CYWater content workspace on staging. The Gutenberg
  canvas now uses the public Fraunces/Inter typography, warm-paper palette,
  article width, image, quotation, link, and button language. News, Event, and
  Award facts use native document-sidebar panels backed by their existing post
  metadata; no parallel content store was added. Public CYWater records do not
  expose the unrelated LiteSpeed per-post panel or PMPro content restriction.
  Event page visibility remains public and separate from ticket, submission,
  voting, and payment eligibility. The removable Logo module keeps ownership of
  its data but its submit/vote audiences now appear in a compact Event sidebar
  panel. Advanced paid-ticket readiness remains a separate capability-gated
  workflow. Browser checks confirmed the News and Event layouts and the current
  Logo audiences; a self-cleaning 29-check REST/meta-box QA created and removed
  one temporary Event. The 396-check Operations suite also passed with complete
  cleanup. All active Core 11/11, Operations 13/13, and theme 93/93 files match
  the local candidates, with manifest SHA-256 values
  `d41841c84b36acb8b8bbccd6dd571f8b6130a902f94f19a56209ba23731fa5bb`,
  `59ea6dbbf28c499d88ef7aac86d217f868cd530de10fc8e25aa229615184b4e7`, and
  `cffb06d79a970a01cace0f213ee96004fe882b9250f2d9edfafb5109e0b0e7b3`.
  The database and three pre-release code trees are retained under
  `/home/u111638297/cywater-release-backups/editor-workspace-20260819T005000`.
- On 2026-08-19 theme `0.6.21` and Core `0.6.3` corrected public
  publishing visibility on staging. News now lists every published WordPress
  Post by publication date instead of requiring import-only
  `_cyw_news_order` or `_cyw_source_id` metadata; published post ID 447
  (`Test`) is visible on `/news/`. Event and Award main queries no longer
  require presentation metadata to exist. The Awards yearbook retains year
  ordering when supplied and visibly flags an incomplete published record
  instead of dropping it. Board ordering metadata is likewise optional, while
  the existing `confirmed_public` governance gate remains mandatory for a
  person's name. Ordinary published Pages remain directly queryable but are
  added to the fixed association navigation only by deliberate editorial
  change. Forum moderator publication, Partner private application status, Logo
  shortlist/selection, ticket readiness, membership and payment gates remain
  unchanged. A self-cleaning publishing QA passed 21 assertions twice for a
  metadata-free News post, Event, Award and ordinary Page; editor QA passed 29,
  Operations passed 402, Forum passed 100, Partner passed 17, and Logo Call
  passed 13 with all temporary records,
  users, comments, membership rows and intercepted messages removed. Active
  Core 11/11 and theme 93/93 files are byte-identical to local, with manifest
  SHA-256 values
  `735dee36adb16e5569c7f14d90a44fc2d245bf2a0a21500b7de34c88673c8e94`
  and
  `7dd70613d2c1890ffc809a44a71370be7ede20129d89d14cd45d7a0bf930bb32`.
  The pre-release files are retained under
  `/home/u111638297/cywater-release-backups/publishing-visibility-20260819T023500`;
  Hostinger still prevents a fresh WP-CLI database export, and this release
  performs no persistent database migration.
- On 2026-08-19 the user traced the intermittent Lenovo-side HTTP 429 failures
  to the local Magic Ring VPN route. They are no longer treated as a Hostinger
  incident, a WordPress defect, or a reason to pause unrelated staging work.
  A direct staging media REST probe returned HTTP 201 JSON and removed its
  temporary attachment, user and application password; prior authenticated
  draft REST and static-asset checks also passed with complete cleanup. Keep
  the sanitized historical evidence under
  `output/hostinger-429-major-incident-20260819.md`, but reopen provider
  investigation only if the failure reproduces with the VPN disabled. Ordinary
  direct-network and final multi-network connectivity remain launch acceptance
  checks. Staging retains the reversible `CONCATENATE_SCRIPTS=false` setting so
  the admin does not depend on one concatenated core asset response; its prior
  configuration remains backed up under
  `/home/u111638297/cywater-release-backups/admin-assets-config-20260819T020500Z`.
- CYWater Core `0.6.4` keeps WordPress' native Featured image as the single
  cover-image source and labels it explicitly for News, Events and Awards; no
  parallel image field or automatic first-content-image fallback was added.
  Selecting a cover uses it on the corresponding public listing/card, while
  leaving it empty preserves the existing text-tile fallback. Event placement
  now reads `Upcoming — top carousel` or `Past — category archive`. Upcoming
  Events feed the upper carousel and intentionally remain visible in their
  lower subject category as well. Ten read-only staging checks passed and found
  two published Upcoming Events. The three changed Core files match local
  SHA-256 values, and the rollback tree is retained under
  `/home/u111638297/cywater-release-backups/core-0.6.4-20260819T110508`.
- Theme `0.6.22` keeps the Event cover image as archive/card presentation data
  and no longer inserts it automatically above the Event story. Editors may
  still place any image deliberately in the Gutenberg body. The explicit
  `Event placement` field remains authoritative: `Upcoming — top carousel`
  feeds the upper carousel, while the Event also remains in its lower subject
  category. Staging verified zero automatic `event-photo` wrappers on the
  Event detail, one occurrence of the selected cover on the Events archive,
  matching SHA-256 values for all three deployed files, and PHP syntax success.
  The full pre-release theme is retained under
  `/home/u111638297/cywater-release-backups/event-cover-0.6.22-20260819T043426Z`.
- CYWater Core `0.6.5` and theme `0.6.23` make the Event editor and archive
  ordering explicit. An untouched placement is Archive; only an explicit
  `Upcoming — top carousel and category list` choice adds the Event to the
  carousel, and it intentionally remains in its subject category. Within every
  category Upcoming records appear before Archive records. Upcoming sorts by
  Event start time ascending, Archive sorts by Event start time descending, and
  missing dates remain last in their placement group. Optional Format is only a
  public badge override and falls back to `Event`; the internal source URL is
  retained but removed from the daily editor panel. Carousel pagination changes
  state when the card transition starts instead of after it finishes. The live
  Events page confirmed the expected order, the self-cleaning staging editor QA
  passed 38 checks, zero temporary Events remained, and all active Core 11/11
  and theme 93/93 files match local. The database and pre-release trees are
  retained under
  `/home/u111638297/cywater-release-backups/event-ordering-20260819T131538`.
- Theme `0.6.25` supersedes the unaccepted `0.6.24` pagination overlay, which
  changed the accepted indicator spacing. The compact centered flex geometry is
  restored exactly: inactive markers are 17 px, the active marker is 30 px, and
  the gap is 6 px. The old marker now contracts while the new marker expands and
  changes color with the same 560 ms duration and easing as the Event window;
  both start together. Reduced-motion behavior remains intact. The self-cleaning
  editor QA passed 38 checks, zero temporary Events remained, and all active
  theme 93/93 files match local by SHA-256. The pre-release database and theme
  are retained under `/home/u111638297/cywater-release-backups/event-indicator-alignment-20260819T154140`.
- Theme `0.6.26` removes the staging-only Membership registration notice,
  replaces the obsolete Newsletter archive benefit with the actual verified-
  member Forum submission and moderation path, and starts every Membership FAQ
  collapsed. Checkout links and membership rules are unchanged. Public-page
  assertions passed, all active theme 93/93 files match local by SHA-256, and
  the database plus pre-release theme are retained under
  `/home/u111638297/cywater-release-backups/membership-page-0.6.26-20260819T160632`.
- CYWater Forum `0.2.0` and theme `0.6.19` were atomically deployed to staging
  after a database plus full plugin/theme backup. Invitations and endorsements
  are paused without deleting their code, page, or historical metadata. A
  verified active Student, Professional, or Lifetime member may save a draft or
  submit it for review but cannot publish or edit published Forum articles;
  Community Moderators publish, take down, and restore them. Eligible member
  replies publish immediately. Non-members, unverified accounts, and ordinary
  registered accounts cannot post or reply. Built-in Editor, REST mutation, and
  restoration protections remain. The real WordPress/MySQL self-cleaning QA
  passed 100 assertions, intercepted three verification messages, and removed
  all temporary users, articles, comments, PMPro rows, and mail intercepts.
  Independent marker queries returned zero temporary users, posts, or comments.
  The active Forum 10/10 files and theme 92/92 files match the local candidates;
  their manifest SHA-256 values are
  `7c29a44f24c551338836524238e920417b47679de82ccfa04032a4d8366821b6` and
  `efacb24c6557ad42a51c6bbe9ded4e79b12f6eb959403cbee1f1c1df3942afaa`.
  The database, archives, and live rollback trees are retained at
  `/home/u111638297/cywater-release-backups/forum-0.2.0-20260818T195836`.
  The staging preview placeholder article must be deleted or replaced before
  production cutover.
- CYWater Forum `0.3.2` adds the front-end `/forum-workspace/` for eligible
  members to create and revise their own draft or pending articles without
  entering WordPress administration. It does not repurpose `/wp-admin/` as a
  workspace route: a signed-in account without an Administrator or assigned
  CYWater Operations role now receives WordPress' direct HTTP 403 permission
  denial at the requested admin URL, with no redirect or specialized login
  destination. Administrator and assigned staff access, front-end submission,
  infrastructure endpoints, PMPro checkout returns, REST gates, and publication
  ownership are unchanged. The staging Forum suite passed 134 assertions with
  complete cleanup, and all active Forum 11/11 files match local with manifest
  SHA-256 `7fd4bac189da75cf4fc0d37eec02125fc2be00875b7fde558435e7cb04499af9`.
  The pre-release database and full plugin tree are retained under
  `/home/u111638297/cywater-release-backups/forum-admin-denial-0.3.2-20260819T183205`.
  A 2026-08-20 read-only check against the active Student account used for the
  Sandbox payment confirmed the separation directly: the verified member may
  submit an article for review and reply, but cannot publish, edit other Forum
  articles, manage Forum terms, moderate comments, access `/wp-admin/`, or gain
  any Operations staff role. The Community Moderator bundle separately retains
  publication, cross-author editing and comment-moderation capabilities. The
  admin denial is based on Administrator/Operations roles, not membership.
- CYWater Logo Call `0.2.3` is an independent removable plugin attached only to
  the enabled 2026 Logo Design Call event. Submissions run August 12 through
  September 12, 2026: one original/logo-lockup set (5 MB per file) per
  registered user, and every registered user has one final vote. Accounts,
  membership levels, Events, and participation permissions remain separate.
  Each Event independently configures submission and voting for all registered
  users, all active individual members, or selected active membership levels;
  permission checks never mutate membership state. Protected files live outside
  public uploads; only shortlisted lockups can render during voting. The
  selected-design reward is two years of Professional membership, tracked as a
  separate administrator fulfillment item rather than granted automatically.
  Permanent use still requires a separate Board-approved written assignment or
  license. A self-cleaning staging QA covers both registered-user and
  membership-based policies and removes its temporary users and entries.
  Disabling the plugin removes its UI without changing the theme, navigation,
  event, or retained review records. Theme `0.6.3` gives
  this non-conference Event a separate `Member programs` archive section.
  The submission form remains visible with an eligibility explanation when
  disabled, and an eligible member receives a browser-local full-name-lockup
  preview before submitting; no file leaves the browser until submission.
- Theme `0.6.6` makes the Logo Call's core content and submission module visible
  without waiting for scroll-reveal animation. Its event summary now reads only
  stored editorial content, so plugin-appended submission copy is not duplicated
  or truncated into the lead. Logo Call file inputs, textarea, submit action,
  focus, hover, and disabled states reuse the accepted CYWater form controls.
  Anonymous live HTML had zero reveal markers and one invitation paragraph;
  an authenticated, read-only Lifetime-user render exposed both required file
  fields, local preview, and enabled submit action without uploading a file.
- On 2026-08-12, at the user's explicit direction, staging user `grups` was
  assigned the highest individual level, Lifetime. The server verified level
  ID 3, no end date, zero PMPro orders, and Logo Call submit/vote eligibility.
  No Stripe payment, PMPro order, invoice, or receipt was fabricated. Partner
  was not assigned because institutional Partner is not a membership level.
- Theme `0.6.4` and CYWater Partnerships `0.1.1` remove the visually detached
  annual-contribution card from `Guide to Becoming a Partner`. Partnership
  recognition is now one editorial block with a thin divider and a responsive
  `$1,000 per year` information row beside the Board/MOU condition. It reuses
  the accepted typography, spacing, line, and color tokens and becomes a simple
  vertical flow on narrow screens. Live HTTP, version, and DOM markers passed;
  screenshot-level browser control timed out, so final visual review remains open.
- CYWater Partnerships `0.1.2` is deployed. Its self-cleaning staging QA passed
  all 17 checks and retained no temporary application; actual Board and MOU
  decisions remain human governance work.
- Theme `0.6.5` reorganizes the Events archive into Upcoming, Annual Meetings,
  Annual Gathering, and Member Programs. A sticky desktop category navigator
  becomes a compact horizontal navigator on narrow screens. Upcoming events use
  a restrained multi-card horizontal carousel with touch scrolling and scroll
  snapping, while each record remains in its
  canonical category. This makes the removable Logo Design Call discoverable
  without hard-coding it into permanent primary navigation. Live HTML, asset,
  mobile CSS, PHP syntax, cache, and no-secret marker checks passed; browser
  screenshot control timed out, so final human visual review remains open.
- Theme `0.6.7` compacts the Upcoming cards so the archive shows as many events
  per row as the viewport permits. Previous/next controls now sit at the
  vertical sides of the carousel rather than in the section heading, and a
  responsive page indicator below the cards shows the current group. One-page
  layouts retain one active indicator and disable both arrows; narrower layouts
  automatically expose additional groups without duplicating Event records.
- Theme `0.6.8` replaces the multi-card Upcoming strip with one editorial
  feature carousel: the current Event is the only fully presented card, while
  the previous and next Events appear as dimmed side previews. Side-overlay
  chevrons, one indicator per Event, 6.5-second rotation, hover/focus pause,
  keyboard arrows, touch swipes, and reduced-motion handling follow the
  interaction structure requested from the Steam reference while retaining
  CYWater typography, color, spacing, radius, and Event data ownership.
- Theme `0.6.19` was the verified Forum release baseline. All 92 local and staging files
  are byte-identical, with manifest SHA-256
  `efacb24c6557ad42a51c6bbe9ded4e79b12f6eb959403cbee1f1c1df3942afaa`. The
  release changes only Forum participation copy and actions; it preserves the
  typography, palette, spacing, motion, and overall visual system. This does not
  mean production is deployed.
- A read-only post-deploy check found existing source drift outside Forum:
  staging Logo Call `0.2.3` and Environment `0.5.5` differ from the clean local
  `0.2.0` and `0.5.4` trees. Forum deployment did not touch either plugin. The
  Logo directory kept its earlier modification time, both participation
  audiences remain `registered`, and Forum QA confirmed a registered non-member
  can still submit and vote. Reconcile the deployed source before any future
  Logo or Environment deployment; do not overwrite staging from the older local
  tree.
- Stripe Sandbox was connected through PMPro on 2026-08-02. A server-side
  presence-only check confirmed the Sandbox Connect values without reading or
  exposing them, and PMPro's own status check reports the Sandbox webhook as
  enabled. A Student `$20` Sandbox payment and its
  `checkout.session.completed` webhook were verified on 2026-08-03. Cancellation
  was verified to leave the PMPro order in `token` state with no membership.
  Stripe's official Sandbox decline method returned `card_declined` /
  `generic_decline`, and the WordPress account retained no successful order or
  active membership; hosted-Checkout UI submission remains open because both
  available browser-control paths timed out. A full Sandbox refund changed the
  successful PMPro order to `refunded`. CYWater Membership `0.6.2` now revokes
  the exact refunded membership level and its active renewal subscription; it
  preserves the entitlement when a later successful order funds the same user
  and level. The historical refunded Student test order was reconciled from one
  active level to zero, and a second execution made no further change.
  Replaying the original successful event to
  the staging webhook returned HTTP 200, used PMPro's already-processed ignore
  path, and created no duplicate order or membership. The Sandbox
  payment-method configuration reports cards, Apple Pay, Google Pay/Link,
  Alipay, and WeChat Pay enabled and available; the verified USD Checkout
  displayed card, Apple Pay, Google Pay, and Alipay but not WeChat Pay. Stripe
  dynamically filters methods by checkout eligibility. Live payment remains
  disabled.
- On 2026-08-16 PMPro's Stripe Live OAuth connection was authorized for the
  association's CYWater Stripe account. A staging-restricted, presence-only
  check confirmed the Live Connect configuration without reading or exposing
  credentials. PMPro's production webhook was created through its Stripe
  gateway and read back from Stripe Live as present, enabled, current for the
  installed PMPro Stripe API version, and subscribed to all ten event types
  required by that version. The same read-only Live account preflight confirmed
  that the account is reachable, charges and payouts are enabled, identity
  details are submitted, and no current account requirement is due. The saved
  PMPro checkout environment remains `sandbox`; `WP_ENVIRONMENT_TYPE` remains `staging`,
  `CYWATER_PAYMENT_MODE` is temporarily `test` for the explicit small-value
  Sandbox checkout acceptance, and
  `CYWATER_ALLOW_LIVE_PAYMENTS` remains false. No real charge or refund was
  performed. The free PMPro Stripe integration currently adds a separate 2%
  PMPro fee; activating a qualifying premium PMPro license removes that fee.
- CYWater Membership `0.8.5` implements rolling annual Student and Professional
  terms: every successful full-price payment starts a new one-year term on its
  payment date, with no proration or December 31 boundary. Lifetime remains
  non-expiring. Staging had no active Student or Professional membership to
  migrate when this policy changed on 2026-08-13. A separate self-cleaning
  nine-check staging QA passed this rolling-term behavior.
- On 2026-08-20 Professional dues were corrected to `$50/year`. A staging-only
  `$0.50` one-time `Sandbox Payment Test` level was added in a separate PMPro
  level group for checkout, webhook, order and refund acceptance. It expires
  after one day, grants no CYWater membership benefits, and cannot replace an
  existing Student, Professional or Lifetime level. Its public card and signup
  are available only when WordPress is staging, PMPro is in Sandbox, and
  `CYWATER_PAYMENT_MODE=test`; the production/live-payment gate remains closed.
  Membership QA passed 24 assertions and the Forum regression suite passed 134
  assertions with complete cleanup. Desktop and mobile browser checks passed,
  including a single-column 375px layout. The active Membership 11/11 and theme
  94/94 files match local with manifest SHA-256 values
  `16e51889297fd621904314a2727a5cb8bcfd075ef09deb674986fa327f645275` and
  `e04637db49a4e4e6daa9e700a2486dc5d7d47f9e8ecfc6baffc72e1f15ca7c2d`.
  The database/config/full pre-release trees are retained under
  `/home/u111638297/cywater-release-backups/membership-sandbox-test-20260820T061037`,
  and the mobile-fix theme tree under
  `/home/u111638297/cywater-release-backups/membership-mobile-0.6.30-20260820T062133`.
  The first manual `$0.50` Checkout then completed successfully: PMPro recorded
  a successful Sandbox Stripe order, a Checkout Session and payment reference,
  and the `checkout.session.completed` webhook without creating a subscription.
  The test level coexisted with the account's Student level. After explicit user
  approval, a full Sandbox refund changed the order to `refunded`, the
  `charge.refunded` webhook arrived, the refund hook removed only the test
  entitlement, and Student remained active.
- Theme `0.6.31` synchronizes the Upcoming Events carousel shadow with its
  existing 560 ms slide and scale transition. The arriving card now fades into
  the final shadow while the departing card fades out, removing the visible
  post-transition shadow pop. An isolated live-Chrome sample confirmed
  continuous old-card fade-out and new-card fade-in at transition start, about
  180 ms, about 360 ms, and settled state. The release artifact, PHP lint,
  structure validation, 38-check editor QA and staging deployment passed; the
  previous live tree is retained under `/home/u111638297/cywater-release-backups/event-shadow-0.6.31-predeploy-20260820T021000Z`.
- On 2026-08-20 a standard repository security audit produced a **No-Go**
  decision for production cutover until a new candidate passes real Hostinger
  staging acceptance. Seven validated findings are remediated in local
  candidates: Membership `0.8.6`, Partnerships `0.1.3`, Forum `0.4.0`, and
  Operations `0.1.13`. These changes add generic registration-collision
  responses, verified-email composition for public member-directory opt-in,
  exact Stripe-host validation and private-status-token revocation, protected
  Forum cover storage with quota/lifecycle cleanup, and a global fail-closed
  Live-payment gate for Event Tickets. Local structure validation, PHP 8.3
  WordPress Playground lifecycle testing, and `npm audit` pass. Hostinger File
  Manager recovered and archived the exact active Logo Call `0.2.3` and
  Environment `0.5.5` trees; normalized comparison matched 5/5 and 7/7 files.
  At the user's direction, Logo Call is deferred for a later independent update.
  The production-clean profile omits that plugin and requires it to be inactive
  on production while preserving its staging source and data. The generated
  profile contains the theme plus Core, Membership, Partnerships, Forum,
  Operations, and Environment, records the exclusion in its manifest, and
  contains no secret-like or placeholder files. Obsolete local release and
  diagnostic artifacts were moved into the ignored, recoverable
  `local/obsolete/2026-08-20/` archive.
- The same plugin retains the current account-first path: a
  logged-out checkout redirects to the PMPro sign-in page; that page links to a
  dedicated `/member-register/` account form. New accounts sign in but must
  complete a 24-hour one-time email-verification link before checkout. Mail uses
  `CYWater Accounts <accounts@cywater.org>` with replies to
  `membership@cywater.org`. Member-facing verification and account-closure
  confirmation messages use a reusable, email-client-safe official CYWater HTML
  template with the full legal association name, a clear primary action, visible
  fallback URL, security notice, and standard Member Services footer carrying
  the public association address, support mailbox, and website; a
  plain-text alternative remains available. Internal administrator notices stay
  concise and do not copy sensitive records into the branded template. Resends
  are rate-limited, replay is rejected, and an email change invalidates the prior
  verification. Existing accounts were
  backfilled for their current stored address during setup. General WordPress
  registration stays disabled, and checkout no longer creates an account
  inline. Verification delivery is capped at five messages per account per hour
  and valid-nonce registration submissions at twenty per salted network hash per
  hour. A staging-only, self-cleaning test passed 40 verification, sender,
  HTML/plain-text structure, legal-name/action, replay, email-change, closure,
  session, privacy-tool, sign-in-record, and
  administrator-view/list checks; its mail was
  intercepted and its disposable user removed. A separate visual-verification
  message was accepted by the configured WordPress/Postmark transport for
  `contact@cywater.org`; its disposable user was removed, so its link is
  intentionally unusable. Final inbox rendering remains a human check.
- CYWater Membership `0.8.2` adds a read-only administrator record on each
  WordPress user profile. It summarizes account creation/last sign-in, email
  verification, required-profile completion, directory privacy, active level
  and expiry, and account-closure requests. It links to PMPro Members and Orders
  plus Events attendee reports. The Users list exposes account/membership
  columns and verification/closure filters. The Account page offers a reviewed
  closure request and withdrawal path; a request starts a seven-day cooling-off
  period measured from the request, pauses new membership checkout, and then
  enters administrator review. It never uses inactivity or automatically erases active membership,
  event registrations, refunds, or legally required records. No account,
  membership, order, event-registration, or payment data is copied into a
  second database. Members can sign out other devices, administrators can
  revoke another account's sessions, and CYWater profile/privacy metadata is
  integrated with WordPress core export/erasure while identity and transaction
  records remain retained for policy review.
- CYWater Membership `0.8.2` supplies a local HTTPS default avatar for
  WordPress avatar surfaces, including the logged-in admin bar, so an external
  Gravatar failure cannot leave a broken image. The current user's valid
  uploaded profile photo takes priority; another member's photo is used only
  when that member has opted into the public directory and explicitly exposed
  the profile-photo field. Staging `get_avatar_url()` and `get_avatar()` returned
  the local SVG, whose HTTPS request returned HTTP 200 with `image/svg+xml`.
- The protected PMPro membership-order page is the Sandbox receipt surface, not
  an approved tax invoice. A six-check staging invoice QA passed route,
  shortcode, Sandbox, PMPro URL, and completed/refunded-order evidence checks
  without creating an order or exposing identifiers. A separate Stripe Billing
  Invoice workflow remains disabled.
- On 2026-08-03, staging PMPro mail failures were traced to the Hostinger
  default sender and the invalid staging-only administrator address. PMPro's
  sender and the WordPress administrator notification address now use the
  association-controlled `web@cywater.org` identity. An actual registration
  and password-reset request were Delivered through Postmark. PMPro checkout,
  refund, recurring-failure, renewal-invoice, cancellation, expiration-warning,
  and expiration templates were also Delivered after the correction; a dated
  expiration probe contained the December 31, 2026 end date. PMPro's recurring
  quarter-hourly, hourly, and daily Action Scheduler tasks are pending normally
  with zero failed actions. Final approved copy/legal footer and a non-Gmail
  delivery target remain open.
- CYWater Membership `0.6.5` excludes a directory profile as soon as its actual
  membership end date passes, even before PMPro's queued expiration action has
  changed the stored status. Staging probes passed active opt-in, private and
  expired exclusion, field allowlisting, email exclusion, ORCID rendering,
  expiration scheduling/callback, and administrator/editor/subscriber
  capability boundaries. PMPro's separate Membership Manager role is not
  installed and is not a launch blocker while the built-in Administrator handles
  memberships and orders. Install the official Premium Add On only if that work
  is later delegated to non-administrator staff; do not create a drifting local
  imitation of the role.
- CYWater Membership `0.6.5` also corrects the PMPro profile-photo field
  contract: allowed extensions use PMPro's comma-separated format and the 2 MB
  limit is expressed in megabytes. This removed the authenticated Member
  Profile fatal error; desktop and 375px mobile checks now render the upload
  field and all privacy controls with no horizontal page overflow. A one-shot
  PMPro validation probe accepted a valid small PNG and rejected both a PNG
  over 2 MB and a GIF with the expected error codes; all test files were removed.
- Staging now defines `DISALLOW_FILE_EDIT=true` and `WP_DEBUG_DISPLAY=false`.
  CYWater Environment `0.5.5` preserves configured SMTP identities unless the
  explicit local Mailpit mode is active and sends conservative HSTS, nosniff,
  same-origin framing, referrer, and camera/microphone/geolocation policy
  headers while removing the PHP version header. HTTPS, staging environment,
  indexing disabled, public registration disabled, subscriber default role,
  and PMPro's recurring Action Scheduler all passed. Anonymous REST user
  enumeration and author archives now return 404, XML-RPC publishing/authentication
  methods are unavailable, directory indexing is denied, and staging
  `wp-config.php` is mode `600`. WordPress has one association-owned full
  Administrator: staging login `web@staging.cywater.org`, with
  `web@cywater.org` as notification/recovery email. On 2026-08-03 the
  association decided that a second full WordPress Administrator is not a
  launch requirement. MFA and association-controlled recovery material for the
  existing Administrator remain open. On 2026-08-03 MFA was explicitly
  deferred because no association-controlled phone, tablet, security key, or
  managed authenticator exists; do not make a private device the sole factor.
- Event Tickets `5.29.1` is active and CYWater Core `0.6.1` limits it to the
  existing `cyw_event` content model. A staging-only probe passed free RSVP,
  capacity, attendee reporting, public form output, Editor content boundaries,
  confirmation-mail handoff to the configured WordPress/Postmark transport,
  and automatic cleanup. Theme `0.6.0` scopes its form to the accepted design
  tokens without changing the public visual system. CYWater Operations `0.1.11`
  deploys the Event Tickets paid-provider adapter plus fail-closed gates at the
  ticket UI, cart preparation/processing, checkout request, and final Stripe
  order REST endpoint. Actual paid checkout remains closed because Tickets
  Commerce and its Stripe gateway are not enabled or connected, checkout and
  success pages are not configured, and no real paid ticket exists. Before the
  first paid Event, configure those surfaces in Stripe Sandbox and pass the
  success/decline/cancel/refund/duplicate/capacity/email matrix. An Event refund
  must cancel only its matching registration and must never invoke PMPro
  membership revocation.
- The staging-only lifecycle QA passed real create/update/trash/delete operations
  for News, Events, Awards, and Board roles, plus temporary Subscriber
  create/delete, profile/privacy persistence, membership activation/cancellation,
  and recorded full-refund entitlement removal. All QA records were cleaned.
- The public home, News, Events, Awards, Contact, Board, and Bylaws routes match
  the accepted static baseline. `/about/board/` and `/about/bylaws/` are
  intentional 301 compatibility redirects; `/hello-world/` returns 404.
- PMPro and Stripe Sandbox are active for staging acceptance. Stripe Live
  Connect and its production webhook are configured, but the saved checkout
  environment remains Sandbox and the live-payment gate remains closed.
- Hostinger backups and a staging environment exist. Continue to use normal,
  revision-aware setup only; force import remains destructive and requires an
  explicit backup plus user approval.
- Remaining release gates are external/account-level or destructive acceptance:
  MFA/recovery for the existing association-owned WordPress Administrator,
  final policy/legal copy, one non-Gmail delivery target,
  authenticated cross-browser review, Hostinger access protection, and a real
  backup restore rehearsal. The Forum staging preview placeholder article must
  also be deleted or replaced before production cutover.
  Exact human/external procedures are in
  `wordpress/docs/manual-external-handoff.md`.

### Organization Email Identity

- Do not make one person's private Gmail address or phone the sole owner or
  recovery path for the domain, hosting, DNS, WordPress, payment, or mail
  services.
- The confirmed organization domain is `cywater.org` and is the root identity.
  Prefer Google Workspace
  for Nonprofits if CYWater's US `501(c)(3)` eligibility is formally confirmed;
  otherwise use a paid organization workspace after billing authority exists.
- Where a service supports delegated access, give staff named organization
  accounts and individual MFA while the durable service owner remains `web@`. Use
  role addresses such as `admin@`, `billing@`, `web@`, and `contact@` as groups
  or shared inboxes with at least two authorized recipients; do not share one
  password among multiple people.
- WordPress Administration Email is a notification destination, not a mailbox
  service. Human mailboxes, transactional SMTP, and DNS records are separate
  systems. Mailpit is local test capture only and never an official mailbox.
- Maintain at least two organization-authorized custodians for the password
  manager, MFA devices, and recovery material. This does not require a second
  full WordPress Administrator under the 2026-08-03 association decision.

### Durable Web-Service Identity

- `web@cywater.org` is the durable, association-owned identity for registering,
  owning, recovering, and receiving operational notices for Hostinger,
  WordPress platform administration, Postmark, membership infrastructure, and
  related web services. It must remain stable through personnel changes.
- Do not replace `web@cywater.org` with a named person's address merely because
  that person performs setup or testing. Where a vendor supports team members
  or delegated access, invite named organization accounts for daily work and
  auditability while the vendor account's owner, recovery, and notification
  identity remains `web@cywater.org`.
- `web@cywater.org` may be a Google Group or shared role address and therefore
  need not have a Google sign-in password. Vendor accounts registered with it
  use the vendor's own authentication; verification and recovery mail is
  delivered to authorized Group members.
- Human administrators use named organization accounts and individual MFA.
  When a person leaves, remove their delegated access and Group membership
  without changing the vendor account's organization-owned identity.
- Keep at least two custodians for `web@cywater.org` and store vendor
  credentials and recovery codes in an organization-controlled password
  manager. Never share a human Google Workspace password.
- Use `billing@cywater.org` for invoices and renewal notices,
  `membership@cywater.org` for member support, and `contact@cywater.org` for
  public correspondence. These may be secondary contacts, but
  `web@cywater.org` remains the web-platform service identity.
- Services with legal identity requirements, such as Stripe KYC, still require
  a named authorized representative. That compliance record does not change
  the policy that platform ownership, recovery, and operational notices should
  remain controlled by the association wherever the service permits it.

### Hosted Staging Snapshot (2026-08-02)

- Hostinger Business hosting is active. `staging.cywater.org` is the acceptance
  environment. Manual and daily backups exist, and a staging clone has been
  created.
- The custom WordPress theme and CYWater plugins are installed. GitHub Pages
  remains the visual and content source of truth until WordPress staging passes
  final acceptance.
- Google Workspace is active with named human users and role groups `web@`,
  `billing@`, `contact@`, and `membership@`.
- Paid Memberships Pro is active with Student `$20/year`, Professional
  `$50/year`, and Lifetime `$700`. The historical Partner level is retained
  with public signup disabled because institutional partnership is a separate
  workflow. Staging also exposes a non-benefit `$0.50` Sandbox-only test level
  while payment mode is `test`; live payment remains disabled.
- Postmark is approved. `cywater.org` DKIM and Return-Path are verified, and the
  WordPress Postmark plugin has delivered a test email successfully.
- A password-reset test was previously addressed to
  `web@staging.cywater.org`. On 2026-08-03, the staging administrator's email
  was corrected to the association-owned `web@cywater.org` and verified through
  WP-CLI. The staging-only username may remain for environment identification;
  do not replace the notification address with a named person's address.
- A Postmark server token was visible in earlier setup screenshots. The user
  confirmed on 2026-08-02 that it was revoked and replaced, WordPress was
  updated, and a post-rotation test message was delivered. This confirmation
  was not independently reverified during the local repository cleanup. Never
  store the replacement token in Git, chat, screenshots, or documentation.
- Remaining acceptance work includes transactional email workflows, remaining
  Stripe Sandbox/PMPro payment scenarios, member profile,
  privacy and directory behavior, MFA and least privilege, backup restoration,
  mobile, security and performance QA, and production cutover after
  organization approvals.

## Current Site Structure

- `index.html` - home
- `about/index.html` - association purpose and history
- `about/board.html` - static GitHub Pages governance reference; names remain
  unconfirmed there because the original was not changed
- `about/bylaws.html` - full nine-article Bylaws and document download
- `membership/index.html` - eligibility, dues, partnerships, and conference fees
- `membership/dashboard.html` - explicitly non-functional member mockup
- `events/index.html` - Annual Meetings and Annual Gathering
- `events/detail.html` - event renderer using `?id=...`
- `awards/index.html` - Young Scientist Best Paper Award yearbook
- `news/index.html` - Opportunities and Spotlights
- `news/article.html` - article renderer using `?id=...`
- `contact/index.html` - verified mailing address; email pending confirmation
- `assets/img/events/` - supplied Annual Meeting group photographs
- `assets/img/gatherings/` - selected Annual Gathering and virtual award photos
- `assets/docs/CYWater-Bylaws.docx` - supplied Bylaws source document
- `local/` - ignored research archive; never publish unless explicitly requested

The former Journal page and all translation dictionaries, Chinese UI copy,
language controls, and language-state logic have been removed from the current
site. Do not reintroduce them unless the user changes direction.

## Source Authority

Current content is governed by the supplied `CYWaterWebsite0716.docx` and
`CYWater Bylaws.docx`, supplemented by verified material in `local/` and
official CYWater records.

Key requirements:

- Mission and governance language should follow the supplied Bylaws.
- Board names confirmed for WordPress publication on 2026-08-10: President
  Qiuhong Tang; President-Elect Lifeng Luo; Treasurer Zhenxing Zhang;
  Directors-at-Large Ming Pan and Chaopeng Shen; Executive Director vacant.
- The 2026 Annual Meeting is in Nanjing, China, October 16-18; registration is
  expected to open in August.
- Events are separated into Annual Meetings and the Annual Gathering.
- Annual Gathering records currently include 2013, 2017, 2022, and 2024. The
  2020 online record is a Best Paper Award Ceremony and belongs under Awards.
- Awards replace Journal in navigation and cover records from 2012 onward.
- News is separated into Opportunities and Spotlights.
- Student and Professional membership each run for one full year from the
  successful payment date, without proration. Lifetime remains non-expiring;
  the supplied dues/fee schedules otherwise remain authoritative.
- Mailing address: `202 E. Green St. Suite 2, Champaign, IL 61820, USA`.
- The confirmed public contact email is `contact@cywater.org`. Use
  `membership@cywater.org` for member support and `billing@cywater.org` for
  billing, renewals, and invoices.
- On 2026-08-14 the WordPress Contact page and its editable contact-email field
  were updated on staging to these confirmed addresses; the former
  `To be confirmed` and channel-verification copy was removed.

Annual Gathering photo sources supplied by a CYWater teacher:

- 2024, Washington, DC, December 10:
  `https://photos.app.goo.gl/gnubxgdkfTdNHvGK7`
- 2022, Chicago, December 14:
  `https://photos.app.goo.gl/MNr5KDLTTJk17d8H8`
- 2020 virtual Best Paper Award gathering, December 18:
  `https://photos.app.goo.gl/ypBGHyJY6tbPfUYz8`
- The 2017 New Orleans dinner photograph is stored as
  `assets/img/gatherings/2017-dinner.png`.

Homepage photography should favor real CYWater images with clear subjects and
reliable landscape crops. Wider documentary sets may be used in detail-page
galleries even when they are not suitable as homepage cards.

People shown in active site photography must come from verified CYWater source
material and must correspond to the event or story where they appear. When no
matching photo exists, use the title-based `news-visual` treatment instead of a
stock photograph of unidentified people. The visual should not label itself as
a placeholder. Title-based visuals follow the Annual Meeting year-tile language:
use the warm paper surface, centered teal Fraunces text, and only the concise
story label plus year. Do not introduce dark pseudo-covers, decorative frames,
tech-style linework, or redundant CYWater branding inside the tile.

The News index currently uses verified CYWater photography for Annual Meetings
2020-2025, the 2011 founding story, and the 2020 virtual Best Paper Award. Other
award/COP27 entries use title-based visuals until matching source photos are
available. Events use the supplied year-matched Annual Meeting and Annual
Gathering archives.

- On 2026-08-20 CYWater Membership `0.9.1`, Logo Call `0.3.0`, and Operations
  `0.2.0` were deployed to staging after the complete database and plugin backup
  at `/home/u111638297/cywater-release-backups/profile-logo-workflow-20260820T143926Z`.
  Registration and Member Profile require first/last name plus institution,
  canonical country/region, institution type, current role and career stage;
  About yourself remains optional. Country/region is a keyboard-accessible
  searchable single-select backed by PMPro's complete canonical list, and the
  server rejects free-text or unknown values. Membership also applies the same
  12-character minimum to PMPro's authenticated Change Password form and the
  WordPress lost-password reset. The account-security suite passed 69 checks,
  including wrong-current-password, short-password, old-password invalidation,
  reset-message, tampered/one-time reset-key and new-login cases. Logo Call
  accepts one protected PNG/JPEG/WebP design of at most 5
  MB, grants only the review/display/voting license for entries, produces three
  ranked finalists after voting, and reserves the official choice among those
  three to the Board. Selected-design rights assignment plus accepted scalable
  or high-resolution files must be recorded before an Administrator may fulfill
  the reward. **Logo reviews** is the sole backend entry and exposes protected
  thumbnails, work number, entrant identity/email/profile context, votes,
  handoff states and audited ZIP/CSV export. Program Reviewer controls voting
  eligibility only; Governance confirms finalists and the official design;
  reward/final-file fulfillment remains Administrator-only. Self-cleaning Logo,
  Operations and Forum suites passed 28, 431 and 141 assertions with no fixture
  residue. The staging Event's obsolete two-file lockup paragraph was replaced
  with the one-file, separate-vote, three-finalist and Board-selection flow.
  Logo Call remains an independently removable plugin. The association later
  directed that accepted `0.3.0` and the reviewed Event configuration be included
  in the first production-clean cutover, without staging entries, votes,
  identities, or protected files. Production membership payments are intended
  to use Stripe Live only after HTTPS, Live webhook/readiness, policy, and an
  explicitly authorized small-value payment/refund acceptance all pass.

- On 2026-08-21 public DNS was switched from Squarespace to Hostinger with
  `cywater.org A 45.130.228.213` and `www CNAME cywater.org`; Google Workspace
  and Postmark DNS records were preserved. Authority and public resolvers return
  the new target, direct HTTPS is valid, `www` redirects to the apex, and the
  user confirmed the WordPress site over a mobile network. The former four-hour
  Squarespace answer may remain in individual VPN/router caches until its TTL
  expires. Hostinger directory password protection now guards only
  `public_html/staging` and returns HTTP 401 without credentials; production is
  not password-protected. The predeploy backup remains at
  `/home/u111638297/cywater-release-backups/production-predeploy-20260820T155410Z`;
  a fresh post-DNS database/runtime/code backup is at
  `/home/u111638297/cywater-release-backups/post-dns-pre-live-20260821T005914Z`.
- Production now runs theme `0.6.34`, Core `0.6.6`, Membership `0.9.3`,
  Partnerships `0.1.3`, Logo Call `0.3.1`, Forum `0.4.1`, Operations
  `0.2.0`, Environment `0.5.5`, PMPro `3.8.2`, and Postmark `1.19.1`.
  Production rewrite rules were refreshed so Events, Awards and Forum archives
  return 200. Theme `0.6.33` removed the hard-coded review `noindex`; WordPress
  now owns robots output, so production is indexable while staging remains
  noindex. Runtime config explicitly sets production, disables the file editor
  and debug display, and keeps mail/payment disabled and the Live-payment gate
  closed. Theme `0.6.34` and Membership `0.9.3` fail closed while payments are
  disabled: the three public cards show `Payments opening shortly`, and direct
  checkout redirects to Membership. On 2026-08-21 the three role-routing
  candidates were backed up and atomically deployed to staging and production.
  Membership, account-security, Logo Call and Forum staging suites passed 36,
  69, 28 and 141 checks respectively, intercepted all test mail and removed all
  fixtures. The staging and production rollback backups are
  `/home/u111638297/cywater-release-backups/email-routing-staging-predeploy-20260820T180258Z`
  and
  `/home/u111638297/cywater-release-backups/email-routing-production-predeploy-20260820T181044Z`.
  The regenerated clean handoff bundle is
  `dist/cywater-wordpress-production-clean-0.5.5.zip` (16,390,844 bytes),
  SHA-256
  `7a718c62d0de6369959165a45ad9bf4b43d4e60149e9c07363a24154c47fc26e`;
  it contains Membership `0.9.3`, Logo Call `0.3.1`, Forum `0.4.1`, and the
  new role-based mail router.
  PMPro paid/order/renewal/refund templates now use `billing@cywater.org`; free
  checkout and membership lifecycle templates use `membership@cywater.org`;
  native account security uses `accounts@cywater.org` with replies to
  `membership@cywater.org`. Logo Call and the paused Forum endorsement flow use
  `membership@cywater.org`. The PMPro fallback is
  `CYWater Membership <membership@cywater.org>`, and
  `pmpro_only_filter_pmpro_emails=1` prevents it from replacing unrelated
  WordPress/plugin identities; the WordPress administrator and platform owner
  remain `web@cywater.org`. A corrected post-deploy check against Postmark's
  JSON settings confirms that production has a saved Server API Token, Message
  Stream `outbound`, Sender Email `web@cywater.org`, logs enabled, and Force
  Sender Email/HTML/open/link tracking off. The user then enabled production
  Postmark and one controlled account-route message
  (`CYW-MAIL-20260820183111`) was accepted with Postmark ErrorCode `0` / `OK`
  and visibly received in Gmail from
  `CYWater Accounts <accounts@cywater.org>` at `web@cywater.org`; the router
  supplied `membership@cywater.org` as the reply destination. The message
  explicitly changed no account, membership, order, or payment data, and no
  second test was sent. The production readiness marker is now
  `CYWATER_MAIL_TRANSPORT=smtp`; the pre-change configuration is retained at
  `/home/u111638297/cywater-release-backups/postmark-production-enable-20260820T183420Z`.
  Staging Postmark remains enabled with the same non-secret flags. Stripe
  Live OAuth/webhook and the controlled real
  `$0.50` payment/refund remain pending. The production purity gate passes 22
  checks, with one Administrator, zero PMPro orders, zero Logo entries/votes and
  zero failed Action Scheduler tasks.

Membership dues use three individual cards adapted from the original prototype:
Student `$20/year`, Professional `$50/year`, and Lifetime `$700`. Display them
in ascending-price order, with Professional marked as the Standard option in the
second position. Each card has its own direct Join action; there is no separate
selection-summary step. No card is selected on initial load. Professional uses
the teal accent action; the other individual plans use restrained outline
actions that turn teal on hover or activation.

Only staging Sandbox acceptance may append the fourth `$0.50` `Payment test`
card. It is not a public membership product and must disappear outside the
staging/test-mode gate.

Institutional Partner is not an individual membership and must not appear in
the membership-card grid or public PMPro level list. Present the current
`$1,000/year` contribution under `Sponsors and partners` with the action
`Become Our Partner`. That action opens `Guide to Becoming a Partner` and an
expression-of-interest form. Payment is forbidden until Board approval and MOU
completion; only then may an administrator attach an association-controlled
HTTPS Stripe invoice or payment link to the private application status page.
Approved website/logo recognition follows the MOU and must not imply product
endorsement.

Conference fees use a four-column matrix with separate Abstract, Early, and
Standard fee columns. Build it from the established `.table-wrap` and `.table`
components and keep one value per cell. Its column header uses the established
Fraunces display face on a light teal surface; the registration-type row labels
use the same display face, while all fee values remain in the Inter body face.
Do not collapse Early and Standard prices into one unstructured cell.

## Visual Consistency Contract

Visual consistency is a release requirement, not a discretionary polish pass.
New or revised UI must look native to the surrounding page and to the site as a
whole.

- Reuse existing layout and component classes before adding page-specific CSS.
  A new visual treatment is justified only when the current system cannot
  express the content or interaction correctly.
- Use `var(--font-display)` only for established display roles such as page and
  section headings and approved data-table column or row headers. Body copy,
  navigation, buttons, data values, and supporting text use `var(--font-body)`
  through existing component rules.
- Use the shared `--fs-*`, `--sp-*`, line-height, color, border, radius, shadow,
  and container tokens. Do not introduce arbitrary font sizes, line spacing,
  padding, widths, or one-off colors to make one section look independently
  designed.
- Match adjacent sections' container width, section padding, heading spacing,
  text measure, and alignment unless the content has a documented functional
  reason to differ.
- Data tables use `.table-wrap` and `.table`, semantic row/column headers, and
  one value per cell. Data values always use the body typography. The site's
  display family may be used for a complete column-header row and its associated
  row-header labels when that hierarchy is applied consistently. Do not apply
  marketing-card styling or compressed inline labels inside data grids.
- Before publishing, compare the changed section with the sections immediately
  before and after it at desktop and mobile widths. Check typeface, type scale,
  line height, wrapping, spacing rhythm, borders, alignment, and overflow.
- When deployed HTML depends on changed CSS or JavaScript, bump the affected
  page's asset-version query in the same commit. Verify the live HTML and live
  asset both contain the new version; a successful Git push alone is not visual
  QA.
- If real-browser visual QA is unavailable, stay within established components
  and tokens, perform structural and live-asset checks, and explicitly report
  the missing screenshot-level verification. Do not compensate by inventing a
  new visual language.

## Product Boundaries

The site should feel like a serious international scientific association:
quiet, restrained, readable, historically grounded, and easy to maintain.
Preserve the ink/teal/paper design system, full-width header, centered primary
navigation, constrained page content, local real photography, deliberate type
scale, reveal-on-scroll behavior, and responsive mobile drawer.

GitHub Pages is public. `noindex, nofollow` and `robots.txt` reduce crawler
discovery but do not provide authentication. Never describe this preview as
private or access-controlled.

On the public GitHub Pages preview, payments, membership accounts, sign-in,
registrations, receipts, and dashboard data are mock-only. The WordPress track
may process isolated test data in local/staging environments, but must never
describe sandbox activity as a real transaction.

### Production Release Snapshot (2026-08-21)

- The reviewed WordPress production candidate is live at `https://cywater.org/`
  with theme `0.6.42`, Membership `0.9.6`, Logo Call `0.3.2`, Forum `0.4.2`,
  Partnerships `0.1.5`, and Operations `0.2.1`. The complete predeploy database
  and code trees are retained at
  `/home/u111638297/cywater-release-backups/production-release-predeploy-20260821T132711`.
- The member-login page now stores only `[cywater_member_login]` and renders one
  form. Mobile navigation exposes Sign in/Account and Join CYWater together at
  the top of the drawer instead of below the full navigation list; signed-out
  visitors go directly to `/member-login/`. The header displays `CYWater`
  beside the water-drop mark and uses the same mark as the fallback browser
  icon. The Board page no longer exposes internal publication or future
  committee-appointment notes.
- On 2026-08-24 Membership `0.9.6` and theme `0.6.42` centralized account
  routing without replacing WordPress authentication or PMPro account
  ownership. Public registration, sign-in, password recovery, Account,
  checkout returns, and front-end sign-out stay on the managed member pages;
  sign-out returns to `/member-login/?loggedout=true`. A direct `/wp-admin/`
  request retains WordPress' native staff login, while an authenticated
  ordinary member still receives a direct HTTP 403 and gains no Forum or
  Operations role. Login, registration, account and billing pages now opt out
  of full-page caching. Staging account-security QA passed 77 checks; Forum
  retained 132 passing application/storage assertions, with its 11 anonymous
  HTTP probes intercepted only by staging Basic Auth. Production passed the
  public route/form/notice checks and authenticated read-only checks for the
  member 403 and login-to-Account redirect. Stripe remained `live` throughout.
  Complete pre-release rollback trees are retained under
  `/home/u111638297/cywater-release-backups/account-routing-0.9.5-production-pre-20260824T084500Z`
  and
  `/home/u111638297/cywater-release-backups/account-routing-membership-0.9.6-production-pre-20260824T091500Z`.
- Theme `0.6.41` makes the WordPress custom Logo the one brand source for the
  header, footer and browser icon. With no custom Logo it uses the bundled mark
  and a 4 KB early-discovered favicon derivative; it preloads the header mark
  and suppresses WordPress' unrelated Site Icon output on public, login, and
  administration surfaces. Replacing the Logo once therefore updates every
  page and the favicon without page-specific edits. Home, Forum, Board,
  Membership and member-login returned one icon, shortcut icon, Logo preload,
  header Logo and footer Logo after the production cache purge; the native
  WordPress login and administration hooks also returned one icon pair with the
  later core Site Icon hook removed. The complete predeploy `0.6.38` production
  theme is retained under
  `/home/u111638297/cywater-release-backups/brand-logo-production-predeploy-20260821T174923Z/cywater-live-before-switch`.
  The immediately preceding complete `0.6.39` theme is retained under
  `/home/u111638297/cywater-release-backups/mobile-nav-production-predeploy-20260821T180553Z/cywater-active-at-switch`.
- The production security candidate closes all nine findings from scan
  `9f14cbb6-41bb-4a9a-83f7-8ee8a6714ff1`. Staging acceptance passed Membership
  43, Logo 34, Partner lifecycle, and Operations 435 assertions. Forum passed
  132 application/storage assertions; its remaining 11 HTTP checks were
  intercepted by the deliberate outer staging Basic Auth and are not evidence
  of an application failure. The auditable fix record is under
  `local/security/2026-08-20-codex-security-scan/artifacts/fix_report.md`.
- Postmark remains enabled with its stored key, `web@cywater.org` fallback and
  Force From disabled so scoped Accounts, Membership, Billing and Contact
  identities remain authoritative. No secrets are stored in Git.
- On 2026-08-23 the association opened real membership charging. Production
  reports payment mode `live`, the authoritative Live-payment gate open, PMPro
  gateway `stripe` with saved environment `live`, and the association-owned
  Live Connect account reachable with charges and payouts enabled, identity
  details submitted, and no currently due requirement. After the user selected
  the current `CY Water` account, PMPro's Live webhook was created/repaired and
  read back as present, enabled, API-current, and subscribed to all ten required
  event types. Student `$20`, Professional `$50`, and Lifetime `$700` HTTPS
  checkout routes return 200 and allow signup; the Sandbox-only fixture is
  absent from production. No real charge or refund has yet been performed, so
  do not describe the end-to-end financial acceptance as complete until one
  controlled real payment, receipt, Stripe balance/payout evidence, webhook,
  and full refund are reconciled.
- On 2026-08-23 an isolated production-only `Live Payment Acceptance Test`
  level was briefly opened for the final financial acceptance. The association
  then deliberately deferred the real charge/refund. The fixture still had
  zero orders and zero entitlements, so it was closed and completely removed
  together with its empty PMPro group; its former direct URL now returns to the
  normal Membership page. The tracked WP-CLI helper
  `scripts/cywater-production-live-payment-fixture.php` is restricted to the
  production domain/runtime and can recreate the same non-benefit USD `$0.50`
  fixture later. No production test payment level or checkout remains now.
- The first 2026-08-23 post-switch public check found a stale pre-Live
  Membership page in LiteSpeed cache. WordPress and LiteSpeed caches were
  purged. The canonical public page now shows Student, Professional, and
  Lifetime checkout actions, keeps the Live acceptance fixture out of the card
  grid, and routes logged-out fixture access through the normal sign-in gate.
- On 2026-08-23 the production pre-financial audit upgraded CYWater Environment
  to `0.5.7`. It rejects the entire unused XML-RPC endpoint with HTTP 403 and
  removes the core users sitemap so disabled author archives do not advertise
  an Administrator account slug;
  the prior filters had removed application methods but still exposed the
  `system.*` discovery and multicall surface. The fix passed staging PHP/runtime
  checks before production deployment. All eight custom theme/plugin trees
  match the local candidate file-for-file. Inactive Hostinger AI Assistant,
  Easy Onboarding, Reach, Hostinger AI Theme, Twenty Twenty-Four, and Twenty
  Twenty-Three were moved from the public code tree to the recoverable archive
  `/home/u111638297/cywater-release-backups/production-inactive-code-cleanup-20260823T115154`.
  Twenty Twenty-Five remains installed as the fallback theme.
- Logo Call `0.3.3` keeps submissions open through September 30, 2026. Voting
  dates are neither scheduled in Event metadata nor shown publicly; the page
  states only that a separate voting activity follows, and the workflow waits
  safely in review after submissions close until voting is configured. Staging
  QA passed 35 self-cleaning checks before production deployment. Production
  purity passes 22 read-only checks, and a repeatable HTTP audit checked 81
  sitemap and key routes with zero staging, Sandbox, temporary-host,
  local-development, QA-identity, or private payment-fixture residue hits.
  The final production-clean `0.5.7` bundle is 16,399,216 bytes with SHA-256
  `5ee57d80cbbb19659cdbde8c2e590105e5efdb9919121e4cd7847a73e8ce30c7`.

## Editing Guidance

- Keep content IDs and query-string links in sync with `assets/js/content.js`.
- Put global tokens in `assets/css/base.css`, reusable components in
  `assets/css/components.css`, and page/responsive rules in
  `assets/css/pages.css`.
- Use local deployment-safe images under `assets/img/`; do not add remote image
  dependencies or restore active placeholders.
- Avoid broad rewrites unless the user asks. Preserve the existing visual and
  interaction language.
- Do not publish ignored `local/` archives.

## QA Checklist

- Run `node --check` on every changed JavaScript file.
- Check all local links, scripts, stylesheets, images, and query IDs.
- Confirm there are no i18n hooks, Chinese UI strings, Journal links, or active
  placeholder images.
- Inspect desktop and mobile layouts, horizontal overflow, image rendering,
  navigation state, and scroll reveals in a real browser when available.
- Run `git diff --check` and confirm the prototype tag still resolves to
  `e4b5a01ff40abc7938fde11bfe8e6084f60844a3` before publishing.

## Do Not Do Without Approval

- Migrate to React, Vue, Next, or another framework
- Add a backend or build tooling to `main`/`gh-pages`
- Replace the visual system wholesale
- Reintroduce bilingual support
- Publish or change Board names without explicit association confirmation, or
  publish an unverified contact email
- Remove `.nojekyll`
- Delete user-created research/source files
- Move the `v0.1-prototype` tag
- Force-push
