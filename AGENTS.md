# CYWater Project Knowledgebase

Keep `AGENT.md`, `AGENTS.md`, and `claude.md` identical whenever project
direction changes.

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
- No live payment, personal bank account, hosting purchase, or production SMTP
  is authorized at this stage. All credentials remain outside Git.

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

### Temporary Staging Snapshot (2026-08-16)

- Hostinger staging is available at `https://staging.cywater.org/` with the
  CYWater `0.6.13` theme, CYWater Membership `0.8.3`, CYWater Partnerships
  `0.1.1`, CYWater Logo Call `0.2.0`, CYWater Environment `0.5.5`, CYWater
  Forum `0.1.0`, CYWater Core `0.5.8`, and Event Tickets `5.29.1`.
- Dedicated Hostinger SSH access from the Lenovo workstation was established
  and independently verified with public-key authentication on 2026-08-02.
  The private key remains local and must never be copied into the repository.
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
- CYWater Logo Call `0.2.0` is an independent removable plugin attached only to
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
- Theme `0.6.12` uses a stable three-slot Upcoming track instead of rebuilding
  a two-record preview clone on every change. It removes the initial category-
  navigation shift, keeps the initial carousel frame static, and makes the left
  and right controls animate their corresponding previews into the centre. The
  shorter card moves the visual divider left to give editorial details more
  room; its mobile media no longer overflows its card. Forum cards, bylines,
  filters, and discussion surfaces explicitly reuse the accepted global paper,
  white, line, radius, type, spacing, focus, and hover tokens. Local desktop,
  375px, initial-state, left/right-transition, and overflow checks passed; live
  staging HTTP/DOM and asset checks passed after deployment, while live in-app
  screenshot control timed out.
- Theme `0.6.13` replaces the three-slot Upcoming track with one five-slot
  circular buffer. A prepared off-screen card on each side now moves into the
  newly exposed preview position during the same transform, eliminating the
  delayed right-side appearance without duplicating Event records. A disposable
  three-Event browser fixture passed static-entry, transition-midframe, repeated
  forward wrap, reverse motion, indicator, and 375px no-overflow checks. Forum
  article topics are non-interactive metadata labels on detail pages, while
  archive filters remain navigable; the Reply textarea now uses the shared
  CYWater large radius and teal focus treatment.
- On 2026-08-16 theme `0.6.13`, CYWater Environment `0.5.5`, and the independent
  CYWater Forum `0.1.0` were deployed to Hostinger staging from the reviewed
  integration branch. Forum articles use core WordPress posts, taxonomies,
  media, revisions, comments, privacy tools, and the existing PMPro membership
  state; no parallel content, discussion, member, or payment database exists.
  The entire article card links to its canonical article. A hard-staging,
  self-cleaning MySQL/PMPro QA passed membership, publishing, first-reply
  moderation, later-reply approval, nonmember refusal, Forum mail-template
  generation, public HTTP routing, and cleanup. The archive and clearly labelled
  staging preview article return HTTP 200; the dormant AI endpoint returns 204
  with both AI switches disabled. Board policy approval, one real Forum message
  through Postmark, a named Editor moderation pass, authenticated second-browser
  review, and removal of the staging preview article remain production gates.
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
- CYWater Membership `0.8.3` implements rolling annual Student and Professional
  terms: every successful full-price payment starts a new one-year term on its
  payment date, with no proration or December 31 boundary. Lifetime remains
  non-expiring. Staging had no active Student or Professional membership to
  migrate when this policy changed on 2026-08-13.
- The same plugin retains the current account-first path: a
  logged-out checkout redirects to the PMPro sign-in page; that page links to a
  dedicated `/member-register/` account form. New accounts sign in but must
  complete a 24-hour one-time email-verification link before checkout. Mail uses
  `CYWater Accounts <accounts@cywater.org>` with replies to
  `membership@cywater.org`; resends are rate-limited, replay is rejected, and an
  email change invalidates the prior verification. Existing accounts were
  backfilled for their current stored address during setup. General WordPress
  registration stays disabled, and checkout no longer creates an account
  inline. Verification delivery is capped at five messages per account per hour
  and valid-nonce registration submissions at twenty per salted network hash per
  hour. A staging-only, self-cleaning test passed 37 verification, sender,
  replay, email-change, closure, session, privacy-tool, sign-in-record, and
  administrator-view/list checks; its mail was
  intercepted and its disposable user removed. A separate real message was
  accepted by the configured WordPress/Postmark transport, but Workspace inbox
  receipt remains a human check.
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
  installed; its official Premium Add On and valid license remain an external
  access-control gate. Do not create a drifting local imitation of that role.
- CYWater Membership `0.6.5` also corrects the PMPro profile-photo field
  contract: allowed extensions use PMPro's comma-separated format and the 2 MB
  limit is expressed in megabytes. This removed the authenticated Member
  Profile fatal error; desktop and 375px mobile checks now render the upload
  field and all privacy controls with no horizontal page overflow. A one-shot
  PMPro validation probe accepted a valid small PNG and rejected both a PNG
  over 2 MB and a GIF with the expected error codes; all test files were removed.
- Staging now defines `DISALLOW_FILE_EDIT=true` and `WP_DEBUG_DISPLAY=false`.
  CYWater Environment `0.5.4` preserves configured SMTP identities unless the
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
- Event Tickets `5.29.1` is active and CYWater Core `0.5.4` limits it to the
  existing `cyw_event` content model. A staging-only probe passed free RSVP,
  capacity, attendee reporting, public form output, Editor content boundaries,
  confirmation-mail handoff to the configured WordPress/Postmark transport,
  and automatic cleanup. Theme `0.6.0` scopes its form to the accepted design
  tokens without changing the public visual system. Paid event checkout remains
  disabled until Event Tickets is separately connected to the association's
  existing Stripe Sandbox and its success/decline/cancel/refund/duplicate/
  capacity/email matrix passes. An event refund must cancel only its matching
  registration and must never invoke PMPro membership revocation.
- The staging-only lifecycle QA passed real create/update/trash/delete operations
  for News, Events, Awards, and Board roles, plus temporary Subscriber
  create/delete, profile/privacy persistence, membership activation/cancellation,
  and recorded full-refund entitlement removal. All QA records were cleaned.
- The public home, News, Events, Awards, Contact, Board, and Bylaws routes match
  the accepted static baseline. `/about/board/` and `/about/bylaws/` are
  intentional 301 compatibility redirects; `/hello-world/` returns 404.
- PMPro and Stripe Sandbox are active for staging acceptance. The live-payment
  gate is closed and no production Stripe credential is configured.
- Hostinger backups and a staging environment exist. Continue to use normal,
  revision-aware setup only; force import remains destructive and requires an
  explicit backup plus user approval.
- Remaining release gates are external/account-level or destructive acceptance:
  MFA/recovery for the existing association-owned WordPress Administrator, the
  licensed Membership Manager role if required, final policy/legal copy, one non-Gmail delivery target,
  authenticated cross-browser review, Hostinger access protection, and a real
  backup restore rehearsal.
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
  `$70/year`, Lifetime `$700`, and Partner `$1,000/year`. Live payment remains
  disabled.
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

Membership dues use three individual cards adapted from the original prototype:
Student `$20/year`, Professional `$70/year`, and Lifetime `$700`. Display them
in ascending-price order, with Professional marked as the Standard option in the
second position. Each card has its own direct Join action; there is no separate
selection-summary step. No card is selected on initial load. Professional uses
the teal accent action; the other individual plans use restrained outline
actions that turn teal on hover or activation.

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
