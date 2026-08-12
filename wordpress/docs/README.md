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

Hostinger staging is available at `https://staging.cywater.org/`. The accepted
baseline uses the CYWater `0.6.4` theme, CYWater Membership `0.8.2`, CYWater
Partnerships `0.1.1`, CYWater Logo Call `0.1.1`, CYWater Environment `0.5.4`, CYWater Core `0.5.7`, and Event Tickets `5.29.1`. PMPro and Stripe
Sandbox are active for staging acceptance; the live-payment gate remains
closed and no production Stripe credential is configured. Postmark is connected on
staging, its domain authentication is verified, and the user confirmed a
successful post-rotation test message on 2026-08-02. On 2026-08-03, PMPro's
sender and the WordPress administrator notification address were corrected to
the association-controlled `web@cywater.org` identity. Registration, password
reset, and seven PMPro transaction/lifecycle templates were then Delivered.
PMPro's recurring Action Scheduler jobs are pending normally with zero failed
actions; final copy/legal review and a non-Gmail delivery target remain open.

Dedicated Hostinger SSH access from the Lenovo workstation was established and
verified with public-key authentication on 2026-08-02. A live WP-CLI check
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

CYWater Membership `0.8.2` implements the current account-first path: logged-out
checkout redirects to sign-in, sign-in links to `/member-register/`, and a new
account must complete a 24-hour one-time email-verification link before returning
to the originally selected checkout. Verification mail uses
`CYWater Accounts <accounts@cywater.org>` with replies routed to
`membership@cywater.org`; resend is limited to once per minute, link replay is
rejected, and an email change invalidates the earlier verification. General
WordPress registration remains disabled. Existing staging accounts were
backfilled for their current stored email during setup. A staging-only,
self-cleaning test passed 37 checks covering sender/reply identity, hashed token
issuance, one-time verification, replay rejection, email-change invalidation,
closure cooling-off/review state, checkout blocking, request withdrawal,
hourly mail limits, session revocation, WordPress privacy export/erasure,
last-sign-in recording, and administrator-record/list rendering. Intercepted test mail
was not sent and the temporary user was removed. A separate real verification
message was accepted by the configured WordPress/Postmark transport and its
temporary user was removed; final Workspace inbox delivery remains a human
check. Stripe Sandbox was connected
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
`0.5.4` to the existing `cyw_event` model only. It does not create a second
Events editor. A staging-only, self-cleaning acceptance probe created a
temporary event, a free RSVP with capacity three, and one attendee; the public
ticket form, capacity, attendee report, Editor content boundary, and cleanup
all passed. A one-shot RSVP confirmation was accepted by the configured
WordPress/Postmark transport and marked sent by Event Tickets; inbox/Postmark
Activity confirmation remains a human check. Theme `0.6.0` scopes the third-party form to the existing body font,
ink/teal palette, spacing, borders, and button language without changing the
accepted public design. The temporary records were removed.

Paid event checkout is intentionally still closed. Event Tickets must be
connected separately to the association's existing Stripe Sandbox because its
event orders and attendee/seat entitlements are independent from PMPro
membership orders. The free plugin also adds its current application fee to
Stripe transactions; the association must accept that fee or buy Event Tickets
Plus before paid-event launch. After connection, refund and duplicate-webhook
acceptance must prove that a full event refund cancels only the matching
registration and never an unrelated membership.

On 2026-08-12 CYWater Core `0.5.7` created four non-destructive WordPress page
drafts for Board review: Privacy Notice, Terms of Use, Billing/Cancellation/
Refund, and Data Retention/Account Closure. Staging publishes them only as
direct-link review surfaces with a not-approved notice and `noindex`,
`nofollow`, and `noarchive`; they are not in navigation. Production creation
remains draft-only. Later editorial work is preserved
because setup creates only missing slugs. The association confirmed that its
Stripe settlement bank account is present and that the named representative is
authorized; no bank, birth-date, home-address, tax-ID, or credential value is
stored in the repository.

CYWater Logo Call `0.1.1` is an independent removable plugin attached only to
the enabled `CYWater Logo Design Call 2026` staging event. The review schedule
accepts one set per active Student, Professional, or Lifetime member from
August 12 through September 12, 2026; the set contains an original logo and a
full-association-name lockup, each capped at 5 MB. Professional and Lifetime
members have one final vote; Student members may submit but cannot vote. Files
are stored outside public uploads behind direct-access denial, and only a
shortlisted lockup is streamable for voting. No cash prize or membership
upgrade is promised. Non-selected rights remain with the entrant, while
permanent use of a selected design requires a separate Board-approved written
assignment or license. An 11-check self-cleaning staging QA passed membership
gates, one-entry/one-vote enforcement, phases, shortlisting, and protected
storage; temporary users and entries were removed.

Theme `0.6.3` renders the Call under a separate `Member programs` Event section.
The form shows its fields plus an eligibility explanation when unavailable; an
eligible member receives a local lockup preview after choosing an image, before
anything is uploaded. At the user's explicit direction, staging user `grups`
was assigned Lifetime on 2026-08-12. Verification found no expiry, zero PMPro
orders, and both submission and voting eligibility; no payment record, invoice,
or receipt was fabricated.

The staging administrator's notification email was corrected from the invalid
staging-only address to `web@cywater.org` on 2026-08-03 and verified through
WP-CLI. Membership acceptance tests must use a separate logged-out test member
instead of purchasing a membership while signed in as the administrator.

The original paid-checkout and refund events reached PMPro's mail layer but its
email log recorded both member and administrator messages as failed because
PMPro still used Hostinger's default unverified sender. After setting the PMPro
sender name/email to `CYWater <web@cywater.org>`, an actual account-registration
mail and password-reset mail were Delivered through Postmark. PMPro paid
checkout, refund, recurring payment failure, renewal invoice, cancellation,
expiration warning, and expiration templates were also Delivered. The dated
expiration probe included December 31, 2026. These probes verify transport and
template rendering; scheduler-driven renewal/expiration acceptance, approved
production copy/legal footer, and delivery to a non-Gmail provider remain open.

The protected PMPro membership-order page is the current Sandbox receipt
surface; it is not presented as an approved tax invoice. On 2026-08-09 a
staging-only invoice QA passed six checks for the published route, exactly one
PMPro invoice shortcode, Sandbox isolation, PMPro URL resolution, and existing
completed/refunded order evidence. It created no order and exposed no member or
payment identifier. The local receipt sample uses the full organization name,
`International Association of Contemporary Young Scholars in Water Sciences`,
in its header. A separate Stripe Billing Invoice workflow is not enabled.

CYWater Environment `0.5.4` is deployed on staging. It preserves configured
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
