# Staging Next Actions

The visual and content baseline is accepted at `staging.cywater.org`. Keep
production traffic, live payment, and production mail disabled while completing
the following gates in order.

## 1. Access And Recovery

- WordPress uses one association-owned full Administrator: staging login
  `web@staging.cywater.org`, with `web@cywater.org` as its durable notification
  and recovery email. The association decided on 2026-08-03 that a second full
  WordPress Administrator is not a launch requirement.
- Enable MFA on that Administrator with association-controlled security
  keys/passkeys and recovery codes that do not depend on one person's private
  device. Where external services support team/delegated access, staff use
  named organization accounts while `web@cywater.org` remains owner/recovery.
- Keep at least two authorized custodians for the Workspace group, password
  manager, MFA devices, and recovery material even though WordPress has one
  full Administrator identity.
- MFA is deferred as of 2026-08-03 because no association-controlled phone,
  tablet, security key, or managed authenticator exists. Do not bind the sole
  recovery factor to a private device; retain this as an accepted security risk
  for production handover.
- Store recovery codes in the Board-approved recovery location, not in Git.
- Malformed inactive CYWater/PMPro upload directories were removed on
  2026-08-03 after their inactive status and exact staging paths were verified;
  active plugin directories were not touched.

## 2. Staging Protection

- Apply and verify the guarded constants in `hostinger-runtime-config.md`.
- Enable Hostinger staging access protection while editorial review is private.
- Keep search indexing disabled on staging.
- Keep `CYWATER_PAYMENT_MODE=disabled` and the live payment gate closed until
  the Stripe Sandbox webhook passes acceptance.
- Disable the built-in WordPress theme and plugin editors in runtime
  configuration before production.

Verified on 2026-08-03: staging indexing is disabled, public core registration
is disabled, the default role is Subscriber, the file editor is disabled,
debug output is hidden, `wp-config.php` is mode `600`, directory listing is
denied, anonymous REST user enumeration and author archives return 404, and the
conservative HTTPS/security headers are live. XML-RPC publishing and
authentication methods are unavailable.
Hostinger staging access protection remains an account-level release gate.

## 3. Transactional Email

- Follow the SMTP separation and secret-handling rules in
  `hostinger-runtime-config.md`.
- Postmark is the selected organization-owned transactional provider. Its
  domain authentication is verified, and token rotation plus one delivered
  post-rotation test were user-confirmed on 2026-08-02; do not record the token.
- Preserve Google Workspace MX records and review SPF, DKIM, Return-Path, and
  DMARC alignment before completing this gate.
- Route WordPress password reset, registration, receipt, failure, renewal, and
  expiry messages through that provider on staging.
- Registration and password-reset requests plus PMPro checkout, refund,
  recurring-failure, renewal-invoice, cancellation, expiration-warning, and
  expiration templates were Delivered through Postmark on 2026-08-03 after
  correcting the PMPro sender to `CYWater <web@cywater.org>`. PMPro's recurring
  quarter-hourly, hourly, and daily Action Scheduler jobs are pending normally
  with zero failed actions. Final approved production copy and a non-Gmail
  delivery target remain open.
- Test delivery to at least Gmail and one non-Gmail mailbox. Record message IDs
  and results without recording credentials.

## 4. Stripe Sandbox

- Follow the Sandbox-only runtime gate in `hostinger-runtime-config.md`.
- Create or confirm an organization-owned Stripe account and give the developer
  an individual Sandbox role.
- Configure PMPro with sandbox credentials outside Git and create the webhook
  against the HTTPS staging endpoint.
- Run every scenario in `payment-testing.md`, including duplicate webhook,
  refund, expiry, and renewal behavior.
- Open the custom payment gate only for sandbox testing. Do not enable live mode.

Verified on 2026-08-03: Sandbox connection/webhook, success, cancellation,
decline, full refund with matching-membership revocation, duplicate webhook,
renewal/recurring failure, and expiry paths passed. Live mode remains closed.

## 5. Membership Acceptance

- Verify Student, Professional, and Lifetime levels, prices, expiry,
  registration fields, profile privacy controls, and opt-in directory behavior.
- Verify the separate Partner expression-of-interest, Administrator-only
  Board/MOU stages, private status link, and approved-only payment handoff.
- Test administrator, membership manager, editor, member, expired member, and
  anonymous-user permissions.
- Administrator, editor, ordinary-member, active/private/expired-directory,
  field allowlist, email exclusion, and expiration action boundaries passed on
  2026-08-03. The Membership Manager role remains unavailable until the
  official licensed PMPro Premium Add On is installed.
- Confirm the approved refund, renewal, calendar-year, privacy, and retention
  policies before treating the workflow as release-ready.

## 6. Event Registration

- Event Tickets `5.29.1` is active and isolated to the existing `cyw_event`
  content type. Free RSVP, capacity, attendee reporting, public form output,
  Editor content capabilities, confirmation-mail handoff, and automatic QA
  cleanup passed on staging. Event Tickets marked the one-shot confirmation as
  sent through the configured WordPress/Postmark transport; confirm it in the
  recipient inbox and Postmark Activity before final mail approval.
- Use Event Tickets for conference/event registrations and PMPro only for
  membership dues. Do not copy orders, attendees, or membership records into a
  parallel custom database.
- Connect Event Tickets to the association's existing Stripe Sandbox through
  its own Payments settings. Do not reuse or expose PMPro secrets manually.
- After connection, test paid success, decline, cancellation, full refund,
  duplicate webhook, capacity exhaustion, and email. A full refund must cancel
  only the matching attendee/seat.
- Decide whether to accept the free plugin's 2% application fee on Stripe
  transactions or purchase Event Tickets Plus before paid-event launch.

## 7. Release And Recovery

- Deploy only versioned theme/plugin archives built from Git.
- Run desktop and mobile visual checks after every release.
- Restore a backup into staging and verify login, content, media, membership
  levels, and custom fields.
- Record the accepted artifact hashes before any production-domain change.

Theme `0.5.8` is live. Real-browser desktop and 375px mobile checks passed for
the home, membership, sign-in, and registration paths at `0.5.6`; the `0.5.7`
follow-up only neutralizes the semantic FAQ heading wrapper and was verified in
the live HTML/CSS. The logged-out checkout gate redirects to sign-in, the
observed mobile home overflow is fixed, and the mobile menu/FAQ controls have
explicit accessible relationships. Authenticated cross-browser review and a
real staging restore remain open.

An authenticated Chrome session also verified Account, Member Profile, and the
administrator's read-only CYWater member record on 2026-08-03. That review found
and fixed a PMPro file-field contract error in CYWater Membership `0.6.5`;
desktop and 375px mobile now render the profile-photo control and all privacy
settings without a critical error or horizontal page overflow. Server-side
validation accepts a small PNG and rejects both a PNG over 2 MB and a GIF with
the expected PMPro error codes; the one-shot files were removed. A second
browser engine remains open for cross-browser coverage.

The Lenovo workspace and staging custom-code trees were synchronized again
after the profile and environment fixes. A 104-file SHA-256 comparison across the theme and three
CYWater plugins found zero missing, extra, or different files; post-deploy PHP
lint and route smoke checks passed.

The staging-only lifecycle QA then passed create/update/trash/delete for News,
Events, Awards, and Board roles, plus temporary Subscriber creation/deletion,
required profile persistence, directory opt-in/opt-out, PMPro level
activation/cancellation, and recorded full-refund entitlement removal. All QA
records were removed. Remaining manual or external procedures are consolidated
in `manual-external-handoff.md`.

The staging-only Event Tickets probe passed the isolated `cyw_event` mapping,
free RSVP capacity, attendee report, public ticket form, Editor content
boundary, and cleanup. Screenshot-level ticket-form review remains open because
the browser connection timed out; no temporary ticketing records remain.
