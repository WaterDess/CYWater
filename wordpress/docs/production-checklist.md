# Production Checklist

## 2026-08-23 Release Audit Decision

**Current decision: public-site build Go; Stripe Live runtime open; final
financial acceptance deliberately deferred.** Public DNS
now points to the clean Hostinger production target, HTTPS is valid, and the
user confirmed the WordPress site on a mobile network. Production data purity,
approved policy publication, archive rewrites, indexing, runtime hardening,
staging password protection, and post-cutover backups pass. Membership cards
and direct checkout now use the production Live gate and association-owned
Stripe account. OAuth, account readiness, and the Live webhook pass presence-
only acceptance; the controlled real payment/refund is still outstanding. Paid
Events remain separately disabled pending their own review. A sealed standard security scan
reported nine validated findings (seven medium, two low). All nine findings are
fixed on staging and production: the Event Tickets global Live-payment gate,
Logo verification/quota/lifecycle gates, protected Forum cover storage and
cleanup, exact Stripe Partner URL allowlisting and lifecycle-safe token rotation,
generic registration collision responses, and verified-email composition for
the member directory.

The 2026-08-23 pre-financial audit additionally closed the remaining XML-RPC
`system.*` surface with CYWater Environment `0.5.6`; Environment `0.5.7` also
removes the core users sitemap so disabled author archives cannot advertise an
Administrator account slug. Production returns HTTP 403 for an XML-RPC POST
while the homepage remains healthy. All eight custom
theme/plugin trees match the reviewed local candidate. Unused inactive
Hostinger AI/onboarding/Reach plugins and three inactive themes were moved to a
dated rollback archive outside the public tree; Twenty Twenty-Five remains as a
fallback theme.

Hostinger File Manager recovered the former Logo Call `0.2.3` and
Environment `0.5.5` trees read-only. Normalized comparison matched all 5/5 and
7/7 files before further work. At the association's direction, Logo Call was
subsequently replaced by independently accepted staging Logo Call `0.3.2`, then
Logo Call `0.4.0` extends submissions through September 30, records the
Student-through-2026 participation reward and two-year Professional reward for
five finalists, and leaves voting unscheduled and undated on the public page
until a separate announcement.
The association subsequently directed that Logo Call be included at launch.
The production-clean profile therefore contains the theme plus Core, Membership,
Partnerships, Logo Call, Forum, Operations, and Environment. Only the reviewed
Logo Event configuration moves to production; staging entries, votes, identities,
and protected files do not. The public information site and Live payment runtime
are active; do not mark end-to-end financial acceptance complete until a real
payment, receipt, balance/payout evidence, webhook, and full refund reconcile.
The final production-clean `0.5.7` bundle is 16,399,216 bytes with SHA-256
`5ee57d80cbbb19659cdbde8c2e590105e5efdb9919121e4cd7847a73e8ce30c7`.

Remaining acceptance and handover checks:

The unchecked items below are follow-up financial, governance, handover,
restore-rehearsal, cross-browser, or future paid-Event operations. They are not
staging/test residue and do not reopen the accepted public-site build baseline.

- [x] Recover and archive the exact active Logo Call `0.2.3` and Environment
      `0.5.5` trees without overwriting staging
- [x] Generate a production-clean manifest that includes accepted Logo Call
      `0.3.0` and contains no secret-like or placeholder files
- [x] Package `cywater-production-purity-audit.php`, a read-only WP-CLI gate for
      staging identities/links, QA content, Sandbox PMPro data, draft policy
      copy, production runtime flags, and Logo Call configuration/data purity
- [x] Package an idempotent production Logo Event setup that installs the
      accepted dates, audiences, reward and Board-finalist flow while refusing
      to proceed if staging submissions or votes exist
- [x] Back up staging, deploy Membership `0.9.1`, Partnerships `0.1.3`, Forum
      `0.4.0`, Operations `0.2.0`, and Environment `0.5.5`
- [x] Confirm Logo Call `0.3.0` is active with the accepted Event configuration,
      while staging submissions, votes, identities, and protected files are absent
- [x] Run `wp eval-file cywater-production-purity-audit.php` on the production
      target after data cleanup and require every check to pass before DNS cutover
- [x] Run account-security, membership-term, Partner, Forum, Operations,
      editor, publishing, policy, ticketing, payment/refund, lifecycle, invoice,
      and cleanup verification on actual staging
      - passed 69 account-security, 24 membership-term, 141 Forum, 431
        Operations, 38 editor, 21 publishing, and 42 policy assertions; Partner,
        ticketing, lifecycle, and invoice suites also passed with complete
        fixture cleanup
- [ ] Complete the full-site final interactive accessibility, responsive, and
      sustained multi-network HTTP pass. Focused authenticated Chrome acceptance
      already passed Member Profile, the Logo Event, and the sole Logo reviews
      screen; unauthenticated HTTP acceptance passed registration and Logo copy.
- [ ] Upgrade/review Event Tickets beyond `5.29.1` and re-pin its fail-closed
      adapter before any paid Event; paid Events remain disabled meanwhile
- [x] Move obsolete local release/diagnostic artifacts into the ignored,
      recoverable `local/obsolete/2026-08-20/` archive; keep staging/production
      rollback evidence until the post-launch stability window ends
- [x] Permanently remove the exact trashed Forum staging-preview placeholder
      (post 322) after backup and verify that the production purity gate no
      longer reports a test/placeholder post
- [x] Switch authoritative DNS to Hostinger without changing nameservers or
      Google Workspace/Postmark records; verify the apex, `www`, HTTPS and a
      second-network mobile load
- [x] Password-protect only the staging directory and confirm anonymous HTTP
      401 while production remains public
- [x] Remove the hard-coded production `noindex`, refresh Events/Awards/Forum
      rewrites, harden runtime constants, purge caches, and rerun the 22-check
      production purity audit
- [x] Keep public membership checkout fail-closed while payment mode is
      disabled; cards expose no checkout URL and direct level checkout returns
      to Membership

## Prepared In This Branch

- [x] Static GitHub Pages preview remains separate from WordPress work
- [x] Custom theme preserves the current CYWater visual system; theme `0.6.20`
      matches actual staging exactly across 93/93 manifest files
- [x] News, Event, and Award editors use the CYWater canvas and focused native
      document panels; the self-cleaning 29-check staging QA verifies real REST
      metadata persistence, panel ownership, paid-workflow preservation, and
      complete temporary Event cleanup
- [x] News, Events, Awards, Board roles, and pages are editable in WordPress
- [x] Initial import protects later editorial changes by default
- [x] PMPro membership levels and direct checkout links are defined
- [x] Essential registration fields and optional post-registration profile exist
- [x] New accounts require a 24-hour one-time email-verification link before
      membership checkout; replay, resend throttling, and email-change
      invalidation pass the 37-check staging account QA
- [ ] Confirm one real verification message in an association Workspace inbox;
      WordPress/Postmark transport acceptance is already verified
- [x] Account page exposes a reviewed closure request and withdrawal path;
      paid/history-bearing accounts are not automatically erased
- [x] Account closure uses a seven-day request-based cooling-off period, pauses
      new membership checkout, and enters an administrator review queue without
      inactivity-based or automatic account deletion
- [x] Members can sign out other devices; administrators can revoke another
      account's sessions and filter Users by verification/closure state
- [x] CYWater profile/privacy data participates in WordPress personal-data
      export and erasure while account, PMPro, refund, and event records remain
      explicitly retained for policy review
- [x] Registration and verification delivery have bounded hourly limits without
      storing a reusable client IP address
- [x] Public member profile is explicit opt-in with per-field controls
- [x] Payment mode and live-key safety gates are isolated from membership logic
- [x] Local Mailpit routing is isolated from production SMTP
- [x] CI validates Node dependencies, JSON, JavaScript, and PHP syntax
- [x] Hostinger Business staging environment is deployed over HTTPS
- [x] Hostinger daily backup and on-demand backup are available
- [x] Google Workspace is active with named users and organization role groups
- [x] Public staging routes and intentional legacy redirects pass smoke tests
- [x] News, Events, Awards, and Board roles pass staging database
      create/update/trash/delete acceptance with automatic QA cleanup
- [x] Theme `0.6.22` and Core `0.6.4` keep ordinary published News, Event,
      Award and Page records visible without importer-only order/source fields;
      the 21-check public-route QA passes twice with complete fixture cleanup
- [x] Core `0.6.4` uses the native Featured image as the explicit News/Event/
      Award cover, preserves the no-image text tile, and labels Event placement
      as Upcoming top carousel or Past category archive; 10 read-only checks pass
- [x] Theme `0.6.22` uses the Event cover on archive/carousel cards without
      automatically duplicating it above the Event story; body images remain
      deliberate Gutenberg content
- [x] Core `0.6.5` and theme `0.6.23` make Archive the real default placement,
      require an explicit Upcoming choice for the carousel, order Upcoming
      before Archive within each category, and sort both groups by Event start
      time; the 38-check editor QA and live Events-page order pass on staging
- [x] Theme `0.6.25` preserves the compact centered Event-marker geometry while
      the old marker contracts and the new marker expands with the same 560 ms
      duration/easing as the Upcoming card window; staging editor QA passes and
      the 93-file theme tree matches local
- [x] Theme `0.6.26` removes the staging-only Membership registration notice,
      describes the verified-member Forum submission and moderation path in
      the benefits grid, and starts every Membership FAQ collapsed; public-page
      assertions pass and the 93-file theme tree matches local
- [x] Event Tickets is isolated to the existing Events model; free RSVP,
      capacity, attendee reporting, public form output, Editor content boundary,
      confirmation-mail handoff, and automatic QA cleanup pass
- [x] Rolling Student and Professional one-year terms pass the separate
      nine-check staging QA; Lifetime remains non-expiring
- [x] CYWater Partnerships `0.1.3` passes its self-cleaning staging QA;
      actual Board and MOU decisions remain human governance work
- [x] CYWater Core `0.6.1` policy review surfaces pass 42 read-only checks while
      remaining explicitly unapproved and ineffective pending Board/legal review
- [x] Temporary account/profile/privacy/membership activation/cancellation/
      deletion lifecycle passes with automatic QA cleanup
- [x] Anonymous user enumeration and author archives are closed; directory
      indexing is denied and staging configuration permissions are restricted
- [x] CYWater Operations `0.2.0` deploys the minimal four-role operations matrix
      without an ordinary membership-refund/finance role or a local PMPro
      Membership Manager imitation; its self-cleaning staging QA passes 431
      assertions

## Must Pass Before Staging Approval

- [ ] Board approves legal entity, operating country, prices, rolling annual
      term policy, recurring billing policy, refund policy, privacy
      notice, terms, and retention policy
      - on 2026-08-21 the association approved and published the four initial
        Privacy, Terms, Billing/Refund, and Data Retention policies effective
        August 21, 2026; the launch policy keeps automatic renewal disabled
      - legal-entity, operating-country, tax treatment, and any later recurring
        billing approval remain separate governance confirmations
      - proposed rule is **All membership sales are final and non-refundable.**
        Duplicate/technical/unauthorized/fraud corrections,
        chargebacks, processor reversals, and non-waivable statutory rights are
        handled as billing disputes or corrections, not routine refunds; Board
        approval is still required
      - every paid Event still needs its own approved refund/cancellation terms,
        and an Event refund affects only its matching registration or seat
- [x] Managed host plan and renewal price fit the budget and required features
- [ ] Staging access controls, strict HTTPS, SMTP sandbox, and off-site backup exist
- [ ] Hostinger/LiteSpeed external connectivity passes without HTTP 429 or
      connection timeout over a sustained multi-network browser probe
      - the earlier Lenovo-side 429 was traced by the user to the local Magic
        Ring VPN route and is no longer an active Hostinger incident or a reason
        to pause unrelated staging work; reopen provider investigation only if
        it reproduces with the VPN disabled
      - final acceptance still requires normal direct-network Chrome navigation
        plus a sustained second-network check before production cutover
      - staging temporarily uses `CONCATENATE_SCRIPTS=false` so one failed
        `load-styles.php` response cannot remove the entire admin layout; 15/15
        key static-style probes plus 402 Operations and 29 editor QA checks
        passed; this remains a reversible admin asset-resilience setting
- [x] Stripe Sandbox connection and PMPro webhook are configured outside Git
- [x] Successful, failed, cancelled, duplicate, refund, expiry, and renewal tests pass
- [x] Production Stripe Live OAuth, gate, account readiness, checkout
      reachability, and the current-account webhook pass presence-only checks
- [ ] One controlled real payment, receipt, Stripe balance/payout evidence,
      webhook delivery, and full refund reconcile end to end
      - this acceptance was deliberately deferred on 2026-08-23; the isolated
        USD `$0.50` level had zero orders and zero entitlements, was first
        closed, then completely removed together with its empty group
      - the stale pre-Live Membership page cache was purged after discovery;
        the uncached and canonical public page now expose the Student,
        Professional, and Lifetime checkout actions, while the former fixture
        URL now returns to Membership and no test card or level remains
      - when financial acceptance resumes, recreate an isolated fixture with
        the tracked fail-closed helper; the payer must personally submit the
        payment, and after reconciliation obtain action-time refund
        confirmation,
        issue the full refund, verify only the fixture entitlement is removed,
        and remove the empty fixture after retaining any required audit trail
- [x] Protected PMPro Sandbox order/receipt surface passes invoice QA; production
      tax/invoice wording and any separate Stripe Billing workflow remain policy
      decisions rather than duplicated transaction code
- [ ] Registration, password reset, receipt, failure, welcome, expiry, and renewal
      emails pass content and deliverability review
      - Transport and Postmark delivery passed on 2026-08-03; automatic
        recurring scheduling is healthy with zero failed actions. Final
        legal/footer copy and a non-Gmail delivery target remain open.
- [x] Editor, Administrator, and ordinary member permissions pass; any later
      non-administrator membership delegation uses the official PMPro role
      - Administrator/editor/member boundaries pass. The official PMPro
        Membership Manager Add On is not installed and does not block payment
        collection. The Administrator currently handles membership operations
        and billing-correction records; there is no routine membership-refund
        approver role. Install the official Add On only if membership work is
        later delegated to non-administrator staff, and do not build a local
        imitation. The association-approved single WordPress Administrator
        still needs MFA and organization-controlled recovery material; a second
        full Administrator is not required. MFA is explicitly deferred because
        no association-controlled authenticator device currently exists.
- [x] CYWater Operations role and audit QA passes on actual staging (402
      self-cleaning assertions)
      - the Administrator-facing **Users -> CYWater staff access** workflow uses
        name/login/email search, a one-account editor, visible email and base
        role, operational-access badges, and 20-result pagination rather than an
        all-member checkbox matrix
      - the removable Logo Call participation controls appear only on the Event
        already enabled as the Logo Call host; ordinary Events neither display
        nor accept that exceptional program configuration
      - delegated staff use the capability-filtered left menu or Dashboard
        **CYWater work areas** links; a Community Moderator sees only Dashboard,
        Media, Forum, Comments, and Profile
      - the full Administrator retains every route, but daily content,
        community/program, membership/account, and low-frequency site-system
        entries are visually separated; Site system is collapsed by default
      - group headings have a server-rendered fallback and do not disappear when
      the optional collapse script is delayed or unavailable; version `0.1.11`
      inlines both critical group/work-area styles and the menu interaction into
      required WordPress core admin assets, eliminating separate navigation loads
      - the Governance Approver Dashboard shortcut opens the registered
        **Partner applications** work area; the retired route slug is absent
      - only a built-in Administrator can assign/remove the four composable
        roles; no real staff account is assigned until a named organization
        account is supplied
      - Content & Event Editor, Community Moderator, Program Reviewer, and
        Governance Approver can perform only their documented work and cannot
        install plugins/themes, manage Administrators, configure payment
        credentials, or manage PMPro memberships/orders
      - Program Reviewer uses one review bundle for entry review, shortlisting,
        and recording reward fulfillment; no separate fulfillment capability
        exists, and the role cannot create/delete participant submissions or
        protected files
      - production assignments keep the paid-Event submitting editor and
        approver on different named accounts whenever independent approval is
        required; assigning both composable bundles to one account removes that
        human separation
      - role and workflow audit rows contain IDs/states/time/action/reason only,
        with no copied names, email, content, application notes, payment data,
        or credentials
- [x] CYWater Forum `0.2.0` is active on staging and its real WordPress/MySQL
      self-cleaning QA passes 100 assertions
      - invitations/endorsements are paused without deleting their code, page or
        historical records
      - verified active Student/Professional/Lifetime members may submit only
        draft/pending articles; Community Moderators alone publish and restore
      - eligible-member replies publish immediately; non-members, ordinary
        registered accounts and unverified accounts cannot post or reply
      - built-in Editor, REST mutation, public visibility and trash/untrash
        boundaries remain covered; registered-user Logo Call eligibility is
        independently unchanged
      - three test messages were intercepted; temporary users, articles,
        comments, PMPro rows and mail intercepts were removed
- [x] Remove the Forum staging preview placeholder from public view before
      production cutover
      - post `322` was moved to `trash` after the predeploy backup; the
        production clone must omit it permanently so the purity gate passes
- [x] Paid-Event approval and fail-closed integration gates pass staging QA
      before any paid registration
      - `Draft -> Terms complete -> Pending approval -> Approved ->
        Registration open -> Closed` is enforced
      - an editor cannot approve their own submission through the editor role;
        incomplete terms cannot be submitted or opened
      - a material change after submission/approval invalidates approval and
        closes the readiness gate; only a current approval can open checkout
      - the deployed Event Tickets `5.29.1` adapter consumes a structured
        readiness snapshot; UI, cart preparation/processing, checkout request,
        and final Stripe order REST creation all fail closed on absent or
        exceptional adapter state, unreadable or mismatched fee/currency,
        false checkout readiness, changed/missing approval fingerprint, or a
        failed strict-audit write
      - actual paid Event checkout remains disabled because Tickets Commerce,
        its Stripe gateway, checkout/success pages, and a real paid ticket are
        not yet configured
      - Event Tickets/Stripe remain the only attendee/order/charge/refund/
        webhook authorities
- [ ] Desktop/mobile visual, accessibility, form, overflow, and browser tests pass
      - Real-browser desktop and 375px mobile checks pass for home,
        membership, sign-in, registration, and the logged-out checkout gate.
        Authenticated Chrome checks also pass for Account, Member Profile,
        profile-photo/privacy controls, and the administrator member record.
        A second browser engine remains open.
- [ ] Backup restore is performed and verified on staging
- [ ] Before the first paid Event, enable Tickets Commerce, configure its
      checkout/success pages and association Stripe Sandbox connection, create a
      real paid ticket, and pass the success/decline/cancel/refund/duplicate/
      capacity/email matrix
- [ ] Event Tickets free 2% application fee is accepted, or Event Tickets Plus
      is purchased, before charging an event fee

## Must Pass Before Production

- [x] Legal entity and bank account pass Stripe live verification
- [x] Production domain ownership and DNS change window are approved
- [x] Production secrets are installed through the host environment and remain
      outside Git
- [ ] Small live payment and refund are reconciled end to end
- [ ] Monitoring, incident contacts, rollback artifact, and handover document are signed
- [ ] GitHub Pages transition/redirect decision is approved

See `manual-external-handoff.md` for the exact human and external procedures
behind the remaining unchecked gates.
