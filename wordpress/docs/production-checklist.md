# Production Checklist

## Prepared In This Branch

- [x] Static GitHub Pages preview remains separate from WordPress work
- [x] Custom theme preserves the current CYWater visual system
- [x] News, Events, Awards, Board roles, and pages are editable in WordPress
- [x] Initial import protects later editorial changes by default
- [x] PMPro membership levels and direct checkout links are defined
- [x] Essential registration fields and optional post-registration profile exist
- [x] New accounts require a 24-hour one-time email-verification link before
      membership checkout; replay, resend throttling, and email-change
      invalidation pass staging server-side QA
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
- [x] Temporary account/profile/privacy/membership activation/cancellation/
      deletion lifecycle passes with automatic QA cleanup
- [x] Anonymous user enumeration and author archives are closed; directory
      indexing is denied and staging configuration permissions are restricted
- [x] Forum is an independent plugin using core posts, taxonomies, comments,
      revisions, media, privacy tools, and PMPro state rather than a parallel
      database; real-PMPro publishing and comment lifecycle QA passes on staging

## Must Pass Before Staging Approval

- [ ] Board approves legal entity, operating country, prices, rolling annual
      term policy, recurring billing policy, refund policy, privacy
      notice, terms, and retention policy
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
- [ ] Editor, membership manager, administrator, and ordinary member permissions pass
      - Administrator/editor/member boundaries pass; the licensed PMPro
        Membership Manager Add On remains open if that limited staff role is
        required. The association-approved single WordPress Administrator
        still needs MFA and organization-controlled recovery material; a second
        full Administrator is not required. MFA is explicitly deferred because
        no association-controlled authenticator device currently exists.
- [ ] Desktop/mobile visual, accessibility, form, overflow, and browser tests pass
      - Real-browser desktop and 375px mobile checks pass for home,
        membership, sign-in, registration, and the logged-out checkout gate.
        Authenticated Chrome checks also pass for Account, Member Profile,
        profile-photo/privacy controls, and the administrator member record.
        A second browser engine remains open.
- [ ] Backup restore is performed and verified on staging
- [ ] Paid event checkout is connected to the existing Stripe Sandbox and its
      success/decline/cancel/refund/duplicate/capacity/email matrix passes
- [ ] Event Tickets free 2% application fee is accepted, or Event Tickets Plus
      is purchased, before charging an event fee
- [ ] Board approves Forum authorship, endorsement, moderation, retention, and
      mail identity; a named Editor processes the real moderation queue
- [ ] One Forum endorsement/authorisation message is Delivered through Postmark
      to an association-controlled inbox, and the staging preview article is
      removed before production content opens

## Must Pass Before Production

- [ ] Legal entity and bank account pass Stripe live verification
- [ ] Production domain ownership and DNS change window are approved
- [ ] Production secrets are installed through the host/GitHub environment
- [ ] Small live payment and refund are reconciled end to end
- [ ] Monitoring, incident contacts, rollback artifact, and handover document are signed
- [ ] GitHub Pages transition/redirect decision is approved

See `manual-external-handoff.md` for the exact human and external procedures
behind the remaining unchecked gates.
