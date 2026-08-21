# Hostinger Runtime Configuration

This document records the non-secret runtime switches for CYWater. Do not put
passwords, API keys, webhook secrets, recovery codes, or payment credentials in
Git, screenshots, support chats, or release archives.

## File Locations

Hostinger currently stores the WordPress configuration files at:

- Staging: `domains/cywater.org/public_html/staging/wp-config.php`
- Production candidate: `domains/cywater.org/public_html/wp-config.php`

Create an on-demand backup before editing either file. Add guarded constants
above the `/* That's all, stop editing! */` line. Never copy staging secrets into
production or production secrets into staging.

## Accepted Staging Baseline

Use these non-secret values while staging is limited to content, visual,
security, and account-flow review:

```php
if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
	define( 'WP_ENVIRONMENT_TYPE', 'staging' );
}
if ( ! defined( 'CYWATER_PAYMENT_MODE' ) ) {
	define( 'CYWATER_PAYMENT_MODE', 'disabled' );
}
if ( ! defined( 'CYWATER_ALLOW_LIVE_PAYMENTS' ) ) {
	define( 'CYWATER_ALLOW_LIVE_PAYMENTS', false );
}
if ( ! defined( 'CYWATER_MAIL_TRANSPORT' ) ) {
	define( 'CYWATER_MAIL_TRANSPORT', 'disabled' );
}
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}
```

After saving, open **Tools > CYWater readiness**. The expected values are:

- WordPress environment: `staging`
- Payment mode: `disabled`
- Live payment gate: `closed`
- PMPro active: `yes`
- Stripe keys: `missing`
- Mail transport: `disabled`
- WordPress file editor: `disabled`

`DISALLOW_FILE_EDIT` removes the built-in theme and plugin source editors. It
does not prevent versioned ZIP uploads through the normal update workflow.
Staging also defines `WP_DEBUG_DISPLAY=false`; diagnostics must not be rendered
to public visitors.

CYWater Environment `0.5.4` adds conservative response headers without defining
a full Content Security Policy: HSTS, `nosniff`, same-origin framing,
strict-origin referrer handling, and disabled camera, microphone, and
geolocation. The host remains responsible for the final production CSP and any
server-level header policy.

Version `0.5.4` also removes anonymous REST user enumeration and public author
archives, removes Pingback/generator hints, and disables XML-RPC publishing and
authentication methods. Keep `wp-config.php` at mode `600` on the current
Hostinger account unless Hostinger changes the PHP ownership model.

## Transactional Postmark Phase

Google Workspace continues to receive human mail for `cywater.org`. Postmark is
the transactional transport for WordPress account, membership, billing, program,
and contact messages. Do not replace the Google Workspace MX records: Postmark
sends mail, while the Workspace role addresses remain the human reply and
support destinations.

Install and configure the Postmark transport plugin through its protected
settings or Hostinger secret facility. The CYWater environment plugin does not
implement the production mail transport itself. After the provider is
configured, change only the readiness marker:

```php
define( 'CYWATER_MAIL_TRANSPORT', 'smtp' );
```

Keep all transport credentials outside Git. Test Gmail and a non-Gmail
recipient before enabling production mail.

Configure the ActiveCampaign Postmark plugin with Message Stream `outbound` and
the verified `web@cywater.org` **Sender Email** as the platform fallback for a
message that has no more specific CYWater identity. Leave **Force Sender Email**
off. Turning it on would overwrite the explicit `From` selected by the account,
membership, billing, program, and contact routes below. The Server API Token is
a secret and must remain only in the protected plugin/host setting.

Use the following functional identities:

| Identity | Responsibility |
| --- | --- |
| `accounts@cywater.org` | Account verification and security, password reset, password/email-change notices, and account-closure confirmation. Member replies go to `membership@cywater.org`. |
| `membership@cywater.org` | Membership state and lifecycle, member support, Forum, Logo Call, and other member-program messages. |
| `billing@cywater.org` | Paid checkout, orders and receipts, recurring charges, payment failure/action, billing corrections, and refunds. |
| `contact@cywater.org` | Public contact, Partnership, free Event/RSVP, and media inquiries. |
| `web@cywater.org` | WordPress/Hostinger/Postmark/Stripe platform ownership, service recovery, technical alerts, and the Postmark fallback only. |

PMPro is not assigned one uniform sender. With
`pmpro_only_filter_pmpro_emails=1`, paid checkout/order/receipt, recurring
payment, payment-failure/action, card-expiry, scheduled-cancellation, invoice,
and refund templates use `billing@cywater.org`; free checkout, membership
change/cancellation, expiration and expiration-warning templates use
`membership@cywater.org`. Unknown future PMPro templates fail to the safer
membership identity until reviewed. Administrator copies retain their intended
recipient; the template category changes only their sender/reply identity.

The installed PMPro template routing is exact:

- Billing: `billing`, `billing_admin`, `billing_failure`,
  `billing_failure_admin`, `cancel_on_next_payment_date`,
  `cancel_on_next_payment_date_admin`, `checkout_check`,
  `checkout_check_admin`, `checkout_paid`, `checkout_paid_admin`,
  `credit_card_expiring`, `invoice`, `membership_recurring`, `payment_action`,
  `payment_action_admin`, `refund`, and `refund_admin`.
- Membership: `admin_change`, `admin_change_admin`, `cancel`, `cancel_admin`,
  `checkout_free`, `checkout_free_admin`, `membership_expired`, and
  `membership_expiring`.

The WordPress administrator notification address remains `web@cywater.org`.
Before production mail is accepted, confirm in Google Workspace that
`accounts@cywater.org` exists as a receive-capable alias or group, is not merely
a Postmark-verified outbound identity, and has at least two authorized
association custodians. These are non-secret association identities; do not
replace them with a Hostinger default sender or a nonexistent staging-only
mailbox.

At the corrected 2026-08-21 post-deploy presence-only check, production
Postmark had a saved Server API Token, Message Stream `outbound`, Sender Email
`web@cywater.org`, logs enabled, and Force Sender Email/HTML/open/link tracking
off. Production **Enabled** remained off pending the controlled delivery test;
the user then enabled it. One controlled account-route message
(`CYW-MAIL-20260820183111`) returned Postmark ErrorCode `0` / `OK` and was
visibly received in Gmail from `CYWater Accounts <accounts@cywater.org>` at
`web@cywater.org`; the application supplied `membership@cywater.org` as the
reply destination. It changed no account, membership, order, or payment data,
and no second test was sent. The production readiness marker is now
`CYWATER_MAIL_TRANSPORT=smtp`; the pre-change `wp-config.php` is retained under
`/home/u111638297/cywater-release-backups/postmark-production-enable-20260820T183420Z`.
Staging remains enabled with the same non-secret flags. Never copy the token
into a task, screenshot, shell command, log, or Git.

## Stripe Sandbox Phase

Only after the organization-owned Stripe Sandbox account and individual staff
roles exist, set the following outside Git:

```php
define( 'CYWATER_PAYMENT_MODE', 'test' );
define( 'CYWATER_ALLOW_LIVE_PAYMENTS', false );
define( 'STRIPE_PUBLISHABLE_KEY', 'REPLACE_WITH_SANDBOX_PUBLISHABLE_KEY' );
define( 'STRIPE_SECRET_KEY', 'REPLACE_WITH_SANDBOX_SECRET_KEY' );
define( 'STRIPE_WEBHOOK_SECRET', 'REPLACE_WITH_SANDBOX_WEBHOOK_SECRET' );
```

The values above are labels only, not credentials. Enter real Sandbox values
directly in the protected runtime configuration and never send them in a
screenshot. The live-payment gate must remain `closed` throughout Sandbox
acceptance.

On 2026-08-03 staging was verified in Sandbox mode through PMPro's protected
Stripe Connect settings. Credential presence and webhook health were checked
without reading or printing their values; the live-payment gate remains closed.
Do not copy Sandbox values into production. Remove obsolete Sandbox credentials
and revoke access grants during production handover, while retaining only the
organization-approved integrations still required for regression testing.

## Production Gate

Changing `WP_ENVIRONMENT_TYPE` to `production` does not authorize live payment.
Live Stripe mode additionally requires an explicit release decision, completed
Stripe verification, approved policies, a successful staging test matrix, and
`CYWATER_ALLOW_LIVE_PAYMENTS=true`. Do not add live values during the current
stage.
