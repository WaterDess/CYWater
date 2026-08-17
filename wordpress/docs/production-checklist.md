# Production Checklist

## Prepared In This Branch

- [x] Static GitHub Pages preview remains separate from WordPress work
- [x] Custom theme preserves the current CYWater visual system; theme `0.6.18`
      matches actual staging exactly across 92/92 manifest files
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
- [x] Event Tickets is isolated to the existing Events model; free RSVP,
      capacity, attendee reporting, public form output, Editor content boundary,
      confirmation-mail handoff, and automatic QA cleanup pass
- [x] Rolling Student and Professional one-year terms pass the separate
      nine-check staging QA; Lifetime remains non-expiring
- [x] CYWater Partnerships `0.1.2` passes its 17-check self-cleaning staging QA;
      actual Board and MOU decisions remain human governance work
- [x] CYWater Core `0.6.1` policy review surfaces pass 42 read-only checks while
      remaining explicitly unapproved and ineffective pending Board/legal review
- [x] Temporary account/profile/privacy/membership activation/cancellation/
      deletion lifecycle passes with automatic QA cleanup
- [x] Anonymous user enumeration and author archives are closed; directory
      indexing is denied and staging configuration permissions are restricted
- [x] CYWater Operations `0.1.3` deploys the minimal four-role operations matrix
      without an ordinary membership-refund/finance role or a local PMPro
      Membership Manager imitation; its self-cleaning staging QA passes 367
      assertions

## Must Pass Before Staging Approval

- [ ] Board approves legal entity, operating country, prices, rolling annual
      term policy, recurring billing policy, refund policy, privacy
      notice, terms, and retention policy
      - conservative Board-review drafts exist for all four policy
        surfaces; they remain marked not approved or in effect
      - proposed rule is **All membership sales are final and non-refundable.**
        Duplicate/technical/unauthorized/fraud corrections,
        chargebacks, processor reversals, and non-waivable statutory rights are
        handled as billing disputes or corrections, not routine refunds; Board
        approval is still required
      - every paid Event still needs its own approved refund/cancellation terms,
        and an Event refund affects only its matching registration or seat
- [x] Managed host plan and renewal price fit the budget and required features
- [ ] Staging access controls, strict HTTPS, SMTP sandbox, and off-site backup exist
- [x] Stripe Sandbox connection and PMPro webhook are configured outside Git
- [x] Successful, failed, cancelled, duplicate, refund, expiry, and renewal tests pass
- [x] Protected PMPro Sandbox order/receipt surface passes invoice QA; production
      tax/invoice wording and any separate Stripe Billing workflow remain policy
      decisions rather than duplicated transaction code
- [ ] Registration, password reset, receipt, failure, welcome, expiry, and renewal
      emails pass content and deliverability review
      - Transport and Postmark delivery passed on 2026-08-03; automatic
        recurring scheduling is healthy with zero failed actions. Final
        legal/footer copy and a non-Gmail delivery target remain open.
- [ ] Editor, Administrator, and ordinary member permissions pass; any later
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
- [x] CYWater Operations role and audit QA passes on actual staging (367
      self-cleaning assertions)
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
- [x] CYWater Forum `0.1.2` is active on staging and its real WordPress/MySQL
      self-cleaning QA passes 77/77 checks
      - publication and verified-member gates, first-reply moderation,
        Community Moderator and built-in role boundaries, public visibility,
        trash/untrash safeguards, and moderator restoration are covered
      - two test messages were intercepted; temporary users, articles, comments,
        PMPro rows, and mail intercepts were removed
- [ ] Delete or replace the Forum staging preview placeholder article before
      production cutover
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

- [ ] Legal entity and bank account pass Stripe live verification
- [ ] Production domain ownership and DNS change window are approved
- [ ] Production secrets are installed through the host/GitHub environment
- [ ] Small live payment and refund are reconciled end to end
- [ ] Monitoring, incident contacts, rollback artifact, and handover document are signed
- [ ] GitHub Pages transition/redirect decision is approved

See `manual-external-handoff.md` for the exact human and external procedures
behind the remaining unchecked gates.
