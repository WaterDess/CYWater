# CYWater WordPress Integration

This directory describes the production candidate that is developed on the
`wordpress-integration` branch. The `staging` branch is a promotion snapshot
for code already deployed to and checked against the actual Hostinger staging
environment; it is not an automatic deployment source. The public GitHub Pages
preview remains on `main` and `gh-pages` until the WordPress site passes
acceptance.

## Quick Start

The fastest disposable review environment does not require Docker:

```powershell
npm ci
npm run prepare
npm run playground:start
```

Open `http://127.0.0.1:8890/`. Playground disables outbound mail and does not
activate PMPro because PMPro 3.8.2 uses MySQL-specific membership queries that
are incompatible with Playground's SQLite layer. It is suitable for content,
theme, and custom-plugin review, not member, Stripe webhook, or SMTP acceptance.

The authoritative integration environment uses Docker:

```powershell
docker compose -f wordpress/docker-compose.mailpit.yml up -d
npm run wp:start
npm run wp:cli -- cywater setup
```

- Site: `http://127.0.0.1:8888/`
- WordPress admin: `http://127.0.0.1:8888/wp-admin/`
- Mailpit: `http://127.0.0.1:8025/`
- Default wp-env login: `admin` / `password`

Do not reuse local credentials in staging or production.
Copy `.env.example` to ignored `.env` only when sandbox credentials become
available. `npm run wp:config` generates an ignored runtime wp-env file, so
secrets never enter the tracked `.wp-env.json` or a process command line.

## Document Map

- `module-boundaries.md` - ownership and dependency rules
- `member-workflow.md` - registration, profile, privacy, and status model
- `payment-testing.md` - Stripe sandbox and refund test matrix
- `stripe-live-verification.md` - US nonprofit Live KYC and bank-document packet
- `deployment.md` - development, staging, production, DNS, and release flow
- `staging-next-actions.md` - ordered gates after visual/content staging acceptance
- `hostinger-runtime-config.md` - exact non-secret staging and payment safety switches
- `accounts-required.md` - account ownership and current blockers
- `production-checklist.md` - launch acceptance gates
- `manual-external-handoff.md` - remaining human, policy, license, and cutover work
- `email-copy.md` - approved-content drafts, not active mail overrides

## Current State

Hostinger staging is available at `https://staging.cywater.org/`, and the public
production site is live at `https://cywater.org/`. The 2026-08-21 accepted code
baseline uses the CYWater `0.6.35` theme, CYWater Membership `0.9.4`, CYWater
Partnerships `0.1.5`, CYWater Logo Call `0.3.2`, CYWater Forum `0.4.2`, CYWater
Environment `0.5.5`, CYWater Core `0.6.6`, CYWater Operations `0.2.1`, and Event
Tickets `5.29.1`.

The public production cutover is complete, while paid membership remains
**No-Go** until Stripe Live and the real charge/refund acceptance are complete.
The 2026-08-21 remediation candidate closes all nine validated authorization,
payment-destination, identity-privacy, lifecycle and storage findings. The
account, membership, Partner, Forum, Operations, editor, publishing, policy,
ticketing, lifecycle, invoice, and cleanup suites pass at the application
layer; staging's outer Basic Auth intentionally intercepts anonymous HTTP QA.
Hostinger File Manager recovered the former Logo
Call `0.2.3` and Environment `0.5.5` trees read-only and normalized comparison
matched 5/5 and 7/7 files. The separately reviewed Logo Call `0.3.1` is deployed
on staging and, by the association's later direction, is included in the first
production cutover. The `production-clean` profile includes the theme, Core,
Membership, Partnerships, Logo Call, Forum, Operations, and Environment. It also packages a
read-only `cywater-production-purity-audit.php` gate that must pass on the
production target before DNS cutover. See
`production-checklist.md` for the exact release gates and cleanup order.
PMPro and Stripe
Sandbox are active for staging acceptance; the live-payment gate remains
closed. On 2026-08-16 the association authorized PMPro's Stripe Live OAuth
connection. A presence-only check confirmed the production Connect
configuration without reading credentials, and PMPro's production webhook was
created and verified through Stripe Live as enabled, API-current, and subscribed
to all ten event types required by the installed PMPro version. A read-only Live
account preflight also confirmed that charges and payouts are enabled, identity
details are submitted, and no current account requirement is due. The saved
PMPro checkout environment remains Sandbox, the staging payment mode is
temporarily `test` for explicit checkout/refund acceptance, and no real charge
or refund has been performed. The free PMPro Stripe
integration currently adds a separate 2% PMPro fee. Postmark is connected on
staging, its domain authentication is verified, and the user confirmed a
successful post-rotation test message on 2026-08-02. On 2026-08-03, PMPro's
sender and the WordPress administrator notification address were corrected to
the association-controlled `web@cywater.org` identity. Registration, password
reset, and seven PMPro transaction/lifecycle templates were then Delivered.
PMPro's recurring Action Scheduler jobs are pending normally with zero failed
actions; final copy/legal review and a non-Gmail delivery target remain open.

On 2026-08-21 Membership `0.9.3`, Logo Call `0.3.1`, and Forum `0.4.1`
were backed up and atomically deployed to both staging and production. Their
32-file candidate manifest is
`22cd3c166c141db50854dc64cc7487b5889e3658cb62aa777a47b634bb0f8633`.
Staging membership-term, account-security, Logo Call, and Forum acceptance
passed 36, 69, 28, and 141 checks respectively; all messages were intercepted
where a workflow could send, and every fixture was removed. The production
homepage, Membership, Forum, Logo Call Event, and native WordPress login return
through LiteSpeed successfully. A corrected presence-only check against the
plugin's JSON settings confirms that production Postmark has a saved Server API
Token, Message Stream `outbound`, Sender Email `web@cywater.org`, logs enabled,
and Force Sender Email/HTML/open/link tracking off. Production delivery remains
enabled after the user selected **Enabled** and one controlled account-route
message (`CYW-MAIL-20260820183111`) returned Postmark ErrorCode `0` / `OK` and
was visibly received in Gmail from `CYWater Accounts <accounts@cywater.org>` at
`web@cywater.org`. The route supplied `membership@cywater.org` as Reply-To, the
test changed no account, membership, order, or payment data, and no second test
was sent. `CYWATER_MAIL_TRANSPORT` is now `smtp`; staging delivery is also
enabled. The remaining mail-acceptance matrix includes a non-Gmail recipient,
PMPro billing and membership-lifecycle templates, and Event RSVP.
The regenerated clean handoff bundle is
`dist/cywater-wordpress-production-clean-0.5.5.zip` (16,390,844 bytes), with
SHA-256
`7a718c62d0de6369959165a45ad9bf4b43d4e60149e9c07363a24154c47fc26e`.

On 2026-08-20 Professional dues were corrected to `$50/year`. Membership
`0.8.5` adds a `$0.50` one-time staging-only Sandbox checkout fixture in its own
PMPro level group. It expires after one day, grants no member benefits, cannot
replace a real individual membership, and disappears unless the runtime is
staging with PMPro Sandbox and `CYWATER_PAYMENT_MODE=test`. Theme `0.6.31`
renders its fourth card responsively. Membership QA passed 24 assertions, Forum
regression QA passed 134, and the active Membership 11/11 and theme 94/94 files
match local byte-for-byte.

Theme `0.6.31` synchronizes the Upcoming Events carousel shadow with the same
560 ms slide and scale transition: the arriving card fades into its final
shadow while the departing card fades out, instead of applying the shadow only
after the carousel has settled. Isolated live-Chrome sampling confirmed the
continuous shadow interpolation through the transition.

The first manual `$0.50` Checkout completed successfully. PMPro stored a
successful Stripe Sandbox order, Checkout Session and payment reference, and
received `checkout.session.completed` without creating a subscription. The test
level coexisted with the account's Student membership. After explicit approval,
the full Sandbox refund set the order to `refunded`; `charge.refunded` arrived,
only the test entitlement was removed, and Student remained active.

Dedicated Hostinger SSH access from the Lenovo workstation was established and
verified with public-key authentication on 2026-08-02. Maintenance access must
use `45.130.228.213:65002` with the dedicated local identity; Hostinger
does not use port 22 for this plan. hPanel reported SSH `ACTIVE` and the
explicit-key connection succeeded again on 2026-08-20. A live WP-CLI check
confirmed WordPress `7.0.2`, PHP CLI `8.3.30`, the active CYWater `0.6.2` theme,
CYWater Membership `0.8.2`, CYWater Partnerships `0.1.0`, CYWater Environment `0.5.4`, and CYWater Core
`0.5.5`. Five malformed inactive CYWater/PMPro upload directories were removed
after exact-path and inactive-status verification; active components were not
removed and the plugin-list warnings cleared.

After the `0.6.5` profile fix, all four custom code trees were redeployed from
the Lenovo workspace to staging. A post-deploy SHA-256 comparison covered 104
files and found zero missing, extra, or different files. All custom PHP files
passed syntax checks, and the home, membership, sign-in, registration, account,
profile, and logged-out checkout routes passed the expected HTTP smoke results.

Theme `0.6.0` connects the four membership cards to PMPro checkout and removes
the incorrect article bullet/indentation rules from PMPro order and account
lists. The accepted typography, palette, imagery, motion, and responsive system
are otherwise unchanged. Real-browser desktop/mobile checks passed for the
public account flow, and an authenticated administrator session verified the
Account, Member Profile, and user-editor member-record surfaces without
horizontal overflow.

On 2026-08-10 CYWater Core `0.5.4` and theme `0.6.0` moved the 2020 online Best
Paper Award Ceremony out of Annual Gathering and into the 2020 Award record.
The exact duplicate Event was moved to trash, not permanently deleted. The
Events archive now reuses the existing title/year tile language whenever a
verified photograph is unavailable, so the 2026 meeting displays `Annual
Meeting` above `2026`. GitHub Pages was not modified.

On 2026-08-10 theme `0.6.2` placed the complete legal association name in the
home Hero, rendered the English mission heading as two deliberate desktop
lines at a smaller display size, and reduced the decorative water-drop outline
to match the tighter composition. The same release limits the PMPro Account
avatar to `48px` and restyles the native Member Profile file selector with the
existing CYWater control tokens without replacing its accessible upload
behavior. Core `0.5.5` carries the updated home identity for repeatable setup.
Live HTML, cache-busted CSS, PHP syntax and the precise PMPro selectors passed;
both available browser-control surfaces timed out, so screenshot-level and
authenticated visual review remain human acceptance checks.

On 2026-08-10 the confirmed Board list was written to the existing editable
Board-role records and enabled for public display: Qiuhong Tang (President),
Lifeng Luo (President-Elect), Zhenxing Zhang (Treasurer), Ming Pan and Chaopeng
Shen (Directors-at-Large), and `Vacant (N/A)` for Executive Director. No
affiliations, terms, biographies, photographs, or contact details were inferred.
The public staging route was verified to contain each record exactly once; the
GitHub Pages original was not modified.

CYWater Membership `0.8.5` implements the current account-first path: logged-out
checkout redirects to sign-in, sign-in links to `/member-register/`, and a new
account must complete a 24-hour one-time email-verification link before returning
to the originally selected checkout. Verification mail uses
`CYWater Accounts <accounts@cywater.org>` with replies routed to
`membership@cywater.org`. Member-facing verification and account-closure
confirmation messages use a reusable, email-client-safe CYWater HTML template
with the full legal association name, a clear primary action, a visible fallback
URL, a security notice, and a standard Member Services footer. A plain-text
alternative remains available when HTML is blocked; the standard footer carries
the public association address, support mailbox, and website. Internal administrator
notices remain concise and do not copy sensitive records into the branded
template. Resend is limited to once per minute, link replay is rejected, and an
email change invalidates the earlier verification. General
WordPress registration remains disabled. Existing staging accounts were
backfilled for their current stored email during setup. A staging-only,
self-cleaning test passed 40 checks covering sender/reply identity, HTML and
plain-text structure, the full legal name and primary action, hashed token
issuance, one-time verification, replay rejection, email-change invalidation,
closure cooling-off/review state, checkout blocking, request withdrawal,
hourly mail limits, session revocation, WordPress privacy export/erasure,
last-sign-in recording, and administrator-record/list rendering. Intercepted test mail
was not sent and the temporary user was removed. A separate real verification
visual-verification message was accepted by the configured WordPress/Postmark
transport for `contact@cywater.org`; its temporary user was removed, so that
message's verification link is intentionally unusable. Final inbox rendering
remains a human check. Rolling annual Student and Professional terms also passed a separate
nine-check staging QA: each full-price payment starts one complete year from its
payment date, with no proration or calendar-year boundary, while Lifetime stays
non-expiring. Stripe Sandbox was connected
through PMPro on 2026-08-02. A Student `$20` Sandbox payment and the resulting
`checkout.session.completed` webhook were verified on 2026-08-03. The Sandbox
configuration reports card, Apple Pay, Google Pay/Link, Alipay, and WeChat Pay
enabled and available; Stripe dynamically displays only methods eligible for a
particular buyer and checkout. The verified USD Checkout displayed card, Apple
Pay, Google Pay, and Alipay, but Stripe did not display WeChat Pay. A cancelled
Alipay checkout left the PMPro order in `token` state and granted no membership.
Stripe's official Sandbox decline method returned `card_declined` /
`generic_decline`, with no successful PMPro order or active membership; the
hosted-Checkout UI click remains open because browser control timed out. A full
Sandbox refund changed the successful order to `refunded`. CYWater Membership
`0.6.2` now revokes the matching membership level and its active renewal
subscription, while a later successful order for the same user and level
protects the newer entitlement. The historical refunded Student test order was
reconciled from one active level to zero; a second execution was idempotent.
An HTTP replay of the original successful event returned 200, was ignored as
already processed, and created no duplicate order or membership. The recurring
renewal/expiry queue is healthy; a manual hosted-Checkout decline click remains
optional because the authoritative Sandbox decline result was already verified.

CYWater Membership `0.8.2` also provides a read-only `CYWater member record` in the
WordPress user editor. It summarizes required-profile completion and directory
privacy, account creation/last sign-in, email verification, active level and
expiry, and account-closure requests. The Users list adds account/membership
columns and filters for verification required, closure cooling-off, and closure
review due. A closure request starts a seven-day request-based cooling-off
period, pauses new membership checkout, and never uses inactivity or automatic
deletion. Members may sign out other devices and an administrator may revoke
another account's sessions. CYWater profile/privacy metadata participates in
WordPress core personal-data export/erasure without deleting the WordPress
identity, closure request, PMPro orders/refunds, memberships, or event records.
It links an authorized administrator to
the matching PMPro Members and Orders views and to Events for the matching
attendee report. The view stores no duplicate account, membership, order,
event-registration, or payment data. An authenticated administrator check verified the refunded test
member has all five required fields, remains private, has no active membership,
and exposes the intended PMPro Members/Orders links.

CYWater Membership `0.8.2` supplies a local HTTPS default avatar for WordPress
avatar surfaces, including the logged-in admin bar, so an external Gravatar
failure cannot leave a broken image. A valid uploaded photo takes priority for
the current user; another member's photo is used only when that member has opted
into the public directory and exposed the profile-photo field. Staging
`get_avatar_url()` and `get_avatar()` returned the local SVG, whose HTTPS request
returned HTTP 200 with `image/svg+xml`.

On 2026-08-10 institutional Partner was separated from individual membership.
The membership page now has three PMPro cards (Student, Professional, and
Lifetime); Partner appears only under `Sponsors and partners` with the action
`Become Our Partner`. CYWater Partnerships `0.1.0` creates the published
`/become-a-partner/` guide and a private expression-of-interest workflow with
Submitted, Board review, MOU pending, Approved to pay, Declined, and Payment
received stages. Applicant status URLs use a rotating token stored only as a
salted hash. No payment link renders before approval; after Board/MOU approval,
an administrator may attach an association-controlled HTTPS Stripe invoice or
payment link. The historical PMPro Partner level and its records were preserved,
but signup is disabled, direct checkout redirects to the guide, and it no longer
qualifies a profile for the member directory. A self-cleaning staging QA passed
application creation, token storage, pre-approval payment denial, approved link
visibility, invalid-token rejection, and cleanup. Live HTML and HTTP redirects
passed; screenshot-level browser QA timed out and remains open.

On 2026-08-12 theme `0.6.4` and CYWater Partnerships `0.1.1` replaced the
detached annual-contribution card on the Partner guide with one editorial
recognition block. A thin divider introduces a responsive `$1,000 per year`
information row and its Board/MOU condition; narrow screens stack the two
parts. Existing typography, color, spacing, and motion tokens are unchanged.
Live HTTP, version, and DOM markers passed; browser screenshot control timed out.

CYWater Partnerships `0.1.2` is now deployed on staging. Its self-cleaning
Partner workflow QA passed all 17 checks; no temporary application or related QA
record was retained. Board and MOU decisions remain human governance work.

CYWater Membership `0.6.5` also checks the actual membership end date before
rendering an opted-in directory profile, so an expired member is hidden even
before PMPro's queued expiration callback changes the stored status. Staging
probes passed opt-in/private/expired visibility, field allowlisting, email
exclusion, ORCID output, expiration scheduling and callback behavior, and
administrator/editor/subscriber capability boundaries. The PMPro Membership
Manager role is not installed; the official Premium Add On and a valid license
remain an external access-control gate.

CYWater Membership `0.6.5` fixes the optional profile-photo field contract with
PMPro: allowed extensions are passed as the required comma-separated string and
the maximum size is passed in megabytes. This removed the authenticated profile
page's PHP fatal error. Live HTML now renders the file input with
`.jpg,.jpeg,.png,.webp`, a 2 MB server-side limit, all privacy controls, and no
critical-error message on desktop or 375px mobile. A one-shot PMPro validation
probe accepted a valid small PNG, rejected a PNG over 2 MB with
`pmpro_upload_file_size_error`, and rejected GIF with
`pmpro_upload_file_type_error`; all temporary files were removed.

Event Tickets `5.29.1` is active on staging and is filtered by CYWater Core
`0.6.1` to the existing `cyw_event` model only. It does not create a second
Events editor. A staging-only, self-cleaning acceptance probe created a
temporary event, a free RSVP with capacity three, and one attendee; the public
ticket form, capacity, attendee report, Editor content boundary, and cleanup
all passed. A one-shot RSVP confirmation was accepted by the configured
WordPress/Postmark transport and marked sent by Event Tickets; inbox/Postmark
Activity confirmation remains a human check. Theme `0.6.0` scopes the third-party form to the existing body font,
ink/teal palette, spacing, borders, and button language without changing the
accepted public design. The temporary records were removed.

CYWater Operations `0.1.11` now supplies the Event Tickets `5.29.1` paid-provider
adapter and fail-closed gates at the ticket UI, cart preparation/processing,
checkout request, and final Stripe order REST endpoint. The adapter consumes the
current approved terms/readiness snapshot; a missing or exceptional adapter,
unreadable or mismatched fee/currency configuration, stale approval fingerprint,
or failed strict audit keeps checkout closed.

Paid event checkout is intentionally still closed in the actual staging
configuration. Tickets Commerce and its Stripe gateway are not enabled or
connected, checkout and success pages are not configured, and no real paid
ticket exists. These event orders and attendee/seat entitlements remain
independent from PMPro membership orders. Before the first paid Event, configure
those Event Tickets surfaces in Stripe Sandbox, create a real paid ticket, and
pass success, decline, buyer cancellation, capacity, duplicate-webhook, full
refund, and confirmation-email acceptance. The free plugin also adds its current
application fee to Stripe transactions; the association must accept that fee or
buy Event Tickets Plus before paid-event launch. A full event refund must cancel
only the matching registration and never an unrelated membership.

On 2026-08-12 CYWater Core created four non-destructive WordPress page
drafts for Board review: Privacy Notice, Terms of Use, Billing/Cancellation/
Refund, and Data Retention/Account Closure. Staging publishes them only as
direct-link review surfaces with a not-approved notice and `noindex`,
`nofollow`, and `noarchive`; they are not in navigation. Production creation
remains draft-only. Later editorial work is preserved
because setup creates only missing slugs. The association confirmed that its
Stripe settlement bank account is present and that the named representative is
authorized; no bank, birth-date, home-address, tax-ID, or credential value is
stored in the repository.

On 2026-08-18 the Board-review direction for the four policy drafts was reduced
to a conservative initial rule:
**All membership sales are final and non-refundable.** Cancelling renewal
stops only future charges and does not refund or credit the current membership
term. Duplicate charges, technical
errors, unauthorized or fraudulent payments, chargebacks, processor reversals,
and non-waivable statutory rights remain billing-correction or dispute paths;
they are not ordinary or discretionary membership refunds. If a corrected or
reversed membership payment no longer funds an entitlement, only that order's
matching membership and renewal may be removed. Membership and Event charges
remain separate: an Event refund affects only its matching registration or
program entitlement and never an unrelated membership. Paid Event registration
remains closed unless that Event publishes its own cancellation, refund, fee,
transfer, capacity, and change terms. There is no routine membership-refund
approval role. These are still unapproved Board-review drafts, not effective
policies. CYWater Core `0.6.1` is deployed on staging, and a 42-check policy QA
passed the four review surfaces, their direct-link/noindex safeguards, and the
strict draft wording without making any policy effective. Board and legal
review remain required.

CYWater Operations `0.2.0` is deployed on staging as the minimal launch
framework.
It keeps permissions separate from user identity through four composable roles:
**Content & Event Editor**, **Community Moderator**, **Program Reviewer**, and
**Governance Approver**. Only a built-in Administrator can assign them. The
first role edits public content and prepares paid Events; the second moderates
Forum articles and comments; the third inspects Logo Call entries and records
voting eligibility only; and the fourth handles Board records, Partner workflow,
paid-Event approval, confirmation of the three Logo finalists, and selection of
the official design from those three. Only an Administrator records accepted
rights, accepted final production files, and reward fulfillment. None manages
PMPro members/orders, payment
credentials, plugins, themes, or Administrators. Role changes and workflow
transitions use a minimal ID/state audit that stores no copied content, email,
application notes, payment data, or credentials.

Paid Events use the explicit sequence `Draft -> Terms complete -> Pending
approval -> Approved -> Registration open -> Closed`. An editor must provide
fee/currency plus public cancellation deadline, refund, transfer, capacity/
wait-list, and cancellation/postponement/format-change terms before submission.
A Governance Approver approves the submitted fingerprint; the approval
capability is not included in the Content & Event Editor role. For operational
independence, production assignments should place those two bundles on
different named accounts. The editor can open registration only while that
approval remains current. Any material change invalidates the approval and
closes the readiness gate. This is an approval boundary only: Event Tickets and
Stripe continue to own attendee, order, charge, refund, and webhook state. The
workflow state does not expose checkout by itself. The deployed Event Tickets
adapter consumes the readiness result and fails closed when the adapter, ticket
configuration, current approval, or matching terms fingerprint is unavailable.
Its UI, cart, checkout, and final Stripe REST enforcement are deployed, but paid
Event checkout remains disabled because Tickets Commerce, its Stripe gateway,
the checkout/success pages, and a real paid ticket are not configured.

A self-cleaning staging QA passed all 402 Operations checks across role
assignment boundaries, workflow transitions, strict audit behavior, Partner and
Logo review boundaries, and paid-Event fail-closed enforcement. It created no
permanent staff assignment. That suite did not stand in for Forum acceptance.

Administrator role assignment now uses **Users -> CYWater staff access** rather
than an all-users permission matrix. The directory lists only accounts that
already have operational access by default; an Administrator can search all
accounts by name, login, or email, open one account, review its visible identity
and base role, and save only the required bundle or bundles. The normal Users
list also shows operational-access badges. Its hover actions are grouped as
**Account**, **Member record**, **Staff access**, and **More**; the overflow keeps
WordPress/PMPro nonce-bearing actions intact. The Dashboard exposes a **CYWater
work areas** widget containing only the modules the current account can use.
Delegated staff see only capability-authorized work areas; a Community Moderator
sees Dashboard, Media, Forum, Comments, and Profile. The Administrator retains
all routes but the sidebar is grouped into Content, Community & programs,
Membership & accounts, and a low-frequency Site system section that is collapsed
by default. The irrelevant Posts count and duplicate PMPro membership-level
column are removed, leaving the authoritative CYWater account, membership,
Forum and staff-access summaries plus the WordPress role. Operations `0.1.11`
corrects the Governance Approver Dashboard
shortcut so **Partner applications** opens the registered `cyw_partner_app`
work area rather than a retired route slug. Version `0.1.9` gives every menu
group a server-rendered fallback heading and treats JavaScript as an optional
collapse enhancement; it also initializes correctly when DOM ready has already
fired. Version `0.1.10` attaches the critical group-heading and Dashboard
work-area styles inline to WordPress' required core admin stylesheet. Version
`0.1.11` also inlines the grouping and collapse script into WordPress' required
core admin script, eliminating the separate navigation request. Fresh and
no-cache sessions therefore use the same menu logic whenever the admin page
itself loads. Version `0.1.12` confines the removable Logo Call participation
panel to the Event that is already enabled as the Logo Call host. Ordinary
Events neither display nor accept that exceptional program configuration; the
enabled host can still disable the module. The 402-check suite passed after
deployment, independent marker queries found no temporary Operations users,
Events, or audit rows, and all 13 active files match the candidate manifest
`4e79fd88b7ddf1baf9f1f38ebfcf2993b933692003fdfd6d08fe31cab4c56564`. A full
database export and the complete pre-release `0.1.11` plugin tree are retained
under
`/home/u111638297/cywater-release-backups/operations-0.1.11-pre-0.1.12-20260819T045140Z`.
Hostinger disables the PHP process functions required by `wp db export`, so the
database backup used `mysqldump` without exposing configuration values.
Hostinger AI Assistant, Easy Onboarding, and Reach are inactive because the
custom site does not use those onboarding/AI/marketing surfaces; their source
directories remain available for reversible recovery.

On 2026-08-19 the user traced the intermittent Lenovo-side HTTP 429 failures to
the local Magic Ring VPN route. They are no longer treated as a Hostinger
incident, a WordPress defect, or a reason to pause unrelated staging work. A
direct staging media REST probe returned HTTP 201 JSON and removed its temporary
attachment, user and application password; earlier authenticated draft REST and
static-asset checks also passed with complete cleanup. The sanitized historical
evidence remains at `output/hostinger-429-major-incident-20260819.md`, but a
provider investigation should be reopened only if the failure reproduces with
the VPN disabled. Direct-network and final multi-network connectivity remain
ordinary launch acceptance checks.

As a reversible staging-only containment on 2026-08-19, WordPress now defines
`CONCATENATE_SCRIPTS=false`. The raw-admin failure was traced to the single
554,001-byte `wp-admin/load-styles.php` response used by the default admin
loader: when that response is rejected, the authenticated HTML and CYWater's
server-rendered menu headings remain but WordPress' entire admin layout becomes
an unstyled list. With concatenation disabled, authenticated Dashboard and
editor HTML contain no `load-styles.php` or `load-scripts.php` URL and reference
the normal individual core assets instead. Three external rounds against the
five essential admin styles returned 15/15 HTTP 200 responses; Operations QA
passed 402 checks, editor REST/meta QA passed 29 checks, and independent cleanup
queries found zero temporary users, posts or comments. The prior `wp-config.php`
is retained under
`/home/u111638297/cywater-release-backups/admin-assets-config-20260819T020500Z`.
An additional authenticated REST probe from the Lenovo network created a
temporary draft with HTTP 201 JSON and updated it with HTTP 200 JSON; its exact
post and temporary application-password user were deleted, and independent
queries returned zero matching residue. This setting remains a reversible admin
asset-resilience measure, not a workaround for an active Hostinger incident.
Authenticated visual revalidation remains required.

CYWater Forum `0.2.0` and theme `0.6.19` were atomically deployed to staging on
2026-08-18 after a database plus full plugin/theme backup. Invitations and
endorsements are paused: their code, page and historical metadata remain, but no
request handler or form is active. A verified account with an active Student,
Professional or Lifetime membership receives draft/submission access lazily;
the durable author role retains record ownership but loses its edit primitive
at request time when eligibility lapses. Members cannot publish or edit a
published article. A Community Moderator reviews, publishes, takes down and
restores Forum records. Eligible member replies publish immediately; ordinary
registered, inactive, non-member and unverified accounts cannot post or reply.

The real WordPress/MySQL self-cleaning QA passed 100 assertions covering these
gates, built-in Editor and REST bypasses, comment moderation isolation, public
visibility, trash/untrash safeguards, Logo Call separation and cleanup. Three
verification messages were intercepted before transport; all temporary users,
posts, comments and tracked PMPro membership rows were removed. Independent
marker queries then returned zero temporary users, posts and comments. The
active Forum 10/10 files and theme 92/92 files are byte-identical to the local
candidates, with manifest SHA-256 values
`7c29a44f24c551338836524238e920417b47679de82ccfa04032a4d8366821b6`
and `efacb24c6557ad42a51c6bbe9ded4e79b12f6eb959403cbee1f1c1df3942afaa`.
The pre-deploy database, archives and live rollback trees are retained at
`/home/u111638297/cywater-release-backups/forum-0.2.0-20260818T195836`.
The staging preview placeholder article remains review-only content and must be
deleted or replaced before production cutover.

CYWater Forum `0.3.2` adds the front-end `/forum-workspace/` for eligible
members' own draft and pending submissions while keeping WordPress
administration a separate staff surface. A signed-in account without an
Administrator or assigned CYWater Operations role receives a direct HTTP 403 at
the requested `/wp-admin/` route. Forum does not redirect that request or
specialize WordPress login destinations. The 134-assertion staging suite passed
with complete cleanup, and the active 11-file plugin matches local at manifest
SHA-256 `7fd4bac189da75cf4fc0d37eec02125fc2be00875b7fde558435e7cb04499af9`.
The pre-release database and plugin tree are retained under
`/home/u111638297/cywater-release-backups/forum-admin-denial-0.3.2-20260819T183205`.

A 2026-08-20 read-only check against that same active Student account confirmed
that member participation and staff management are independent. The verified
member could submit for review and reply, but could not publish, edit others'
Forum articles, manage Forum terms, moderate replies, access `/wp-admin/`, or
hold an Operations staff role. The Community Moderator bundle separately held
the three management capability families. `/wp-admin/` eligibility is based on
Administrator or explicitly assigned Operations roles, never membership.

The earlier read-only source-drift item is resolved. Staging and the reviewed
workspace now use Logo Call `0.3.0` and Environment `0.5.5`; Logo Call remains
an independently removable module and is included in the first production-clean
artifact without staging entries, votes, identities, or protected files.

There is deliberately no ordinary membership-refund or finance role. Under the
proposed final/non-refundable membership policy, only the Administrator handles
PMPro membership/order records and exceptional billing-correction or dispute
reconciliation. The official PMPro Membership Manager Add On is not installed
and does not block launch; it is needed only if this work is later delegated to
a non-Administrator. CYWater must not create a local imitation of that licensed
role.

CYWater Logo Call `0.3.0` is the deployed staging plugin attached only to the
enabled `CYWater Logo Design Call 2026` staging Event. It accepts one PNG, JPEG
or WebP logo file of at most 5 MB per eligible account; no separate lockup is
requested during the open call. Submission grants only a limited review,
display and voting license, so non-winning rights remain with the entrant. A
separate vote opens after submissions close. Governance confirms exactly three
highest-ranked eligible finalists, resolving only a tie that crosses the third-
place boundary, and the Board selects the official logo from those finalists.
The selected entrant must complete a documented winning-design assignment and
deliver accepted scalable or high-resolution production files before an
Administrator may record the two-year Professional-membership reward as
fulfilled. Protected files stay outside public uploads. The sole operational
entry is **Logo reviews**, which shows protected thumbnails, stable work
numbers, account/verified-email/profile context, vote counts and handoff state;
it provides a permission-gated ZIP plus CSV export. The self-cleaning staging
suite passed 28 Logo assertions and removed every temporary user and entry.

Theme `0.6.5` renders the Call under a separate `Member Programs` Event section.
The form shows its fields plus an eligibility explanation when unavailable and
uses one CYWater-styled file chooser; no file leaves the browser until
submission. The Events archive
now opens with an `Upcoming` horizontal card
carousel and a category navigator for Upcoming, Annual Meetings, Annual
Gathering, and Member Programs. Upcoming records also remain in their canonical
category, so the carousel is discovery rather than a duplicate content model.
The Logo Call detail page does not defer its explanatory content or submission
module behind scroll-reveal animation. Its event summary is generated from the
stored editorial content before plugin modules are appended, preventing the
submission copy from appearing twice. File inputs, textarea, submit button,
focus, hover, and disabled states reuse the accepted CYWater form language.
Theme `0.6.7` keeps that same Event model but makes the Upcoming presentation
more compact: cards use a smaller, concise layout; responsive previous/next
controls sit at the two vertical sides of the card row; and a page indicator
below the row tracks the current responsive group. If all Upcoming records fit,
one active indicator remains and both arrows are disabled.
Theme `0.6.8` supersedes the multi-card presentation with a single featured
Event and dimmed previous/next side previews. Its arrows overlay the two sides,
its indicators map one-to-one to Upcoming Event records, and it rotates every
6.5 seconds unless hover, keyboard focus, document visibility, or reduced-motion
preferences pause it. Keyboard arrows and touch swipes select the same records;
no Event data is copied into a separate slider model.
Theme `0.6.19` was the verified Forum release baseline. A read-only manifest comparison
found all 92 local and staging files present and byte-identical, with manifest
SHA-256
`efacb24c6557ad42a51c6bbe9ded4e79b12f6eb959403cbee1f1c1df3942afaa`.
The release changes only Forum participation copy and actions and preserves the
accepted visual system. This is staging verification, not a production release.
Theme `0.6.20`, CYWater Core `0.6.2`, and CYWater Operations `0.1.11` supersede
that Forum-only baseline on staging with the CYWater content workspace. The
block-editor canvas now mirrors the public typography, warm-paper palette,
content width, images, links, quotations, and buttons. Existing News, Event,
and Award metadata appears in native document-sidebar panels; custom Event and
Award types now explicitly support REST-backed custom fields so these controls
persist through Gutenberg. LiteSpeed per-post tuning and PMPro content
restriction are removed from these public editorial screens. Event visibility
remains public and independent of program, ticket, voting, and payment rules.
The separately owned Logo Call controls for who may submit and vote appear in a
compact Event sidebar panel without copying their data or changing their current
`registered` audiences. Advanced paid-event readiness and ticketing remain
separate capability-gated workflows below the Event canvas.

Authenticated browser checks passed the News and Event layouts. A self-cleaning
29-check staging QA verified REST field registration, Administrator REST update
permission, real metadata write/readback, sidebar ownership, preserved paid-
Event workflow, and removal of the temporary Event. The 396-check Operations QA
also passed with complete cleanup. Active Core 11/11, Operations 13/13, and
theme 93/93 files match local, with manifest SHA-256 values
`d41841c84b36acb8b8bbccd6dd571f8b6130a902f94f19a56209ba23731fa5bb`,
`59ea6dbbf28c499d88ef7aac86d217f868cd530de10fc8e25aa229615184b4e7`, and
`cffb06d79a970a01cace0f213ee96004fe882b9250f2d9edfafb5109e0b0e7b3`.
The database, archives, and live rollback trees are retained at
`/home/u111638297/cywater-release-backups/editor-workspace-20260819T005000`.

Theme `0.6.21` and CYWater Core `0.6.3` make public publishing state the
authoritative visibility condition. `/news/` now lists every published
WordPress Post by publication date and does not require importer-only order or
source metadata; the ordinary published staging post ID 447 (`Test`) is visible
there. Event and Award main queries no longer exclude records that lack a
presentation field. The Awards yearbook still sorts supplied years newest first
and renders an incomplete published record as `Year pending` rather than hiding
it. Board order is optional, but the existing explicit `confirmed_public` gate
continues to control whether a person's name is exposed. Publishing a normal
Page makes its permalink public; adding it to CYWater's fixed association
navigation remains a separate deliberate editorial change.

This does not weaken workflow boundaries: Forum articles still require
Community Moderator publication, Partner applications remain private token-
scoped records, Logo submissions remain private and only shortlisted/selected
entries enter their approved public phase, and Event ticket/payment readiness
remains independent of Event page visibility. A staging-only self-cleaning
publishing QA passed 21 assertions twice across metadata-free temporary News,
Event and Award records plus an ordinary Page. Editor QA passed 29, Operations
passed 396, Forum passed 100, Partner passed 17, and Logo Call passed 13; all
temporary records, users, comments,
membership rows and intercepted messages were removed. Active Core 11/11 and
theme 93/93 files match local with zero differences and manifest SHA-256 values
`735dee36adb16e5569c7f14d90a44fc2d245bf2a0a21500b7de34c88673c8e94` and
`7dd70613d2c1890ffc809a44a71370be7ede20129d89d14cd45d7a0bf930bb32`.
The code rollback tree is retained at
`/home/u111638297/cywater-release-backups/publishing-visibility-20260819T023500`.
Hostinger still blocks a fresh WP-CLI database export; this change performs no
persistent database migration and its QA fixtures self-delete.

CYWater Core `0.6.4` keeps the native WordPress Featured image as the single
cover-image source. The editor now calls it **News cover image**, **Event cover
image**, or **Award cover image** according to the record being edited. Selecting
one uses it on the corresponding public list/card; leaving it empty preserves
the existing text-tile fallback. No duplicate cover-image field and no automatic
first-content-image fallback were added. The Event placement selector now states
`Upcoming — top carousel` and `Past — category archive`. Upcoming Events feed
the upper carousel and intentionally remain visible in their lower subject
category. Ten read-only staging checks passed and found two published Upcoming
Events. The three changed Core files are byte-identical to local, and the
rollback tree is retained under
`/home/u111638297/cywater-release-backups/core-0.6.4-20260819T110508`.

Theme `0.6.22` keeps the Event cover image as archive/card presentation data
and no longer inserts it automatically above the Event story. Editors may
still place any image deliberately in the Gutenberg body. The explicit
`Event placement` field remains authoritative: `Upcoming — top carousel`
feeds the upper carousel, while the Event also remains in its lower subject
category. Staging verified zero automatic `event-photo` wrappers on the Event
detail, one occurrence of the selected cover on the Events archive, matching
SHA-256 values for all three deployed files, and PHP syntax success. The full
pre-release theme is retained under
`/home/u111638297/cywater-release-backups/event-cover-0.6.22-20260819T043426Z`.

CYWater Core `0.6.5` and theme `0.6.23` make Event placement and list order
explicit. Archive is the real default when the editor does not choose a value;
only `Upcoming — top carousel and category list` adds a record to the carousel.
Upcoming remains in its subject category and appears above Archive there.
Upcoming sorts by Event start time ascending, Archive sorts by Event start time
descending, and missing dates remain last within their placement group. Format
is an optional public badge override and falls back to `Event`; the internal
source URL remains stored/importable but is no longer part of the daily Event
sidebar. The carousel indicator changes state at transition start. The live
Events page confirmed the expected category order, the self-cleaning staging
editor QA passed 38 checks with zero temporary Events, and active Core 11/11 and
theme 93/93 files are byte-identical to local. The database and rollback trees
are retained under
`/home/u111638297/cywater-release-backups/event-ordering-20260819T131538`.

Theme `0.6.25` supersedes the unaccepted `0.6.24` active-indicator overlay,
which changed the accepted pagination spacing. It restores the compact centered
flex geometry exactly: inactive markers are 17 px, the active marker is 30 px,
and the gap is 6 px. At transition start the old marker contracts while the new
marker expands and changes color, using the same 560 ms duration and easing as
the upper Event window. Reduced-motion behavior remains intact. The 38-check
editor QA passed with zero temporary Events, and all active theme 93/93 files
match local by SHA-256. The pre-release database and theme are retained under
`/home/u111638297/cywater-release-backups/event-indicator-alignment-20260819T154140`.

Theme `0.6.26` removes the staging-only Membership registration notice,
replaces the obsolete Newsletter archive benefit with the actual verified-
member Forum submission and moderation path, and starts every Membership FAQ
collapsed. Checkout links and membership rules are unchanged. Public-page
assertions passed, all active theme 93/93 files match local by SHA-256, and the
database plus pre-release theme are retained under
`/home/u111638297/cywater-release-backups/membership-page-0.6.26-20260819T160632`.

At the user's explicit direction, staging user `grups`
was assigned Lifetime on 2026-08-12. Verification found no expiry, zero PMPro
orders, and both submission and voting eligibility; no payment record, invoice,
or receipt was fabricated.

The staging administrator's notification email was corrected from the invalid
staging-only address to `web@cywater.org` on 2026-08-03 and verified through
WP-CLI. Membership acceptance tests must use a separate logged-out test member
instead of purchasing a membership while signed in as the administrator.

The original paid-checkout and refund events reached PMPro's mail layer but its
email log recorded both member and administrator messages as failed because
PMPro still used Hostinger's default unverified sender. At that staging
acceptance point, setting the PMPro sender to `CYWater <web@cywater.org>` allowed
an actual account-registration mail and password-reset mail to be Delivered
through Postmark. PMPro paid
checkout, refund, recurring payment failure, renewal invoice, cancellation,
expiration warning, and expiration templates were also Delivered. The dated
expiration probe included December 31, 2026. These probes verify transport and
template rendering; scheduler-driven renewal/expiration acceptance, approved
production copy/legal footer, and delivery to a non-Gmail provider remain open.

For production, identities are separated by function. Account verification,
password reset, password/email changes, and account-closure confirmation use
`CYWater Accounts <accounts@cywater.org>` with replies to
`membership@cywater.org`. Membership state/lifecycle, member support, Forum,
Logo Call, and other member-program mail use `membership@cywater.org`. Paid
checkout, orders/receipts, recurring payment, failure/action, and refund mail
use `billing@cywater.org`. Public contact, Partnership, free Event/RSVP, and
media mail use `contact@cywater.org`. The WordPress administrator,
service-ownership/recovery, and technical-alert identity remains
`web@cywater.org`.

PMPro keeps `pmpro_only_filter_pmpro_emails=1` but no longer uses one uniform
sender: paid and billing templates route through `billing@cywater.org`, while
free checkout and membership change/cancellation/expiration templates route
through `membership@cywater.org`; unknown future PMPro templates default to the
membership identity until reviewed. Postmark uses verified
`web@cywater.org` only as its platform fallback Sender Email. **Force Sender
Email must remain off** so Postmark does not overwrite these explicit role
identities. Google Workspace must separately confirm that
`accounts@cywater.org` is a receive-capable alias or group with authorized
custodians; Postmark outbound verification does not create that mailbox.

The protected PMPro membership-order page is the current Sandbox receipt
surface; it is not presented as an approved tax invoice. On 2026-08-09 a
staging-only invoice QA passed six checks for the published route, exactly one
PMPro invoice shortcode, Sandbox isolation, PMPro URL resolution, and existing
completed/refunded order evidence. It created no order and exposed no member or
payment identifier. The local receipt sample uses the full organization name,
`International Association of Contemporary Young Scholars in Water Sciences`,
in its header. A separate Stripe Billing Invoice workflow is not enabled.

CYWater Environment `0.5.5` is deployed on staging. It preserves configured
SMTP sender names outside explicit local Mailpit mode, reports whether the
WordPress file editor is disabled, removes the PHP version header, and sends
HSTS, nosniff, same-origin framing, strict-origin referrer, and restricted
camera/microphone/geolocation headers. `DISALLOW_FILE_EDIT` is enabled and
`WP_DEBUG_DISPLAY` is disabled. Anonymous REST user enumeration and author
archives now return 404; only XML-RPC's three inert introspection methods remain,
and publishing/authentication methods are unavailable. Directory indexing is
denied and staging `wp-config.php` permissions are `600`. No public design or
content changed. Staging has one association-owned full Administrator: login
`web@staging.cywater.org`, notification/recovery email `web@cywater.org`. The
association decided on 2026-08-03 that a second full WordPress Administrator is
not a launch requirement. MFA and association-controlled recovery material for
the existing Administrator remain open.

The reusable `scripts/cywater-staging-lifecycle-qa.php` acceptance test is
hard-guarded to `staging.cywater.org`. On 2026-08-03 it passed real database
create, update, trash, and permanent-delete operations for News, Events,
Awards, and Board roles. It also passed temporary Subscriber creation, required
profile persistence, private-by-default directory controls, PMPro membership
activation/cancellation, account deletion, and full-refund entitlement evidence.
Its temporary posts and user were removed automatically.

The reusable `scripts/cywater-staging-ticketing-qa.php` test is also hard-
guarded to staging. It verifies the single `cyw_event` integration, built-in
Editor capability boundary, RSVP inventory, attendee reporting, optional real
confirmation-mail dispatch, public form, and automatic cleanup. Browser screenshot-level review of the ticket form
remains open because both in-app attempts timed out; server-rendered HTML and
HTTP acceptance passed.

The remaining human/external steps and exact procedures are listed in
`manual-external-handoff.md`. The repository contains no live Stripe key, bank credential, production SMTP
credential, domain credential, or hosting credential. GitHub Pages remains the
visual and content reference until staging completes security, mail, Stripe
Sandbox, membership-flow, mobile, and restore acceptance.
