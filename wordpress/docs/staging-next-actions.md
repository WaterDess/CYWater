# Staging Next Actions

The visual and content baseline is accepted at `staging.cywater.org`. Keep
production traffic, live payment, and production mail disabled while completing
the following gates in order.

## 1. Access And Recovery

- Confirm Hostinger, Squarespace Domains, Google Workspace, WordPress, GitHub,
  and Stripe each have two organization-authorized administrators.
- Each human uses a named account and individual MFA. Role addresses such as
  `web@`, `billing@`, and `membership@` remain groups or notification targets,
  not shared human login credentials.
- Store recovery codes in the Board-approved recovery location, not in Git.
- Remove superseded CYWater theme/plugin copies after an on-demand backup.

## 2. Staging Protection

- Apply and verify the guarded constants in `hostinger-runtime-config.md`.
- Enable Hostinger staging access protection while editorial review is private.
- Keep search indexing disabled on staging.
- Keep `CYWATER_PAYMENT_MODE=disabled` and the live payment gate closed until
  the Stripe Sandbox webhook passes acceptance.
- Disable the built-in WordPress theme and plugin editors in runtime
  configuration before production.

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

## 5. Membership Acceptance

- Verify Student, Professional, Lifetime, and Partner levels, prices, expiry,
  registration fields, profile privacy controls, and opt-in directory behavior.
- Test administrator, membership manager, editor, member, expired member, and
  anonymous-user permissions.
- Confirm the approved refund, renewal, calendar-year, privacy, and retention
  policies before treating the workflow as release-ready.

## 6. Release And Recovery

- Deploy only versioned theme/plugin archives built from Git.
- Run desktop and mobile visual checks after every release.
- Restore a backup into staging and verify login, content, media, membership
  levels, and custom fields.
- Record the accepted artifact hashes before any production-domain change.
