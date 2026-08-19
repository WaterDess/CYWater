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

## Transactional SMTP Phase

Google Workspace continues to receive human mail for `cywater.org`. A separate
transactional provider should send WordPress password resets, membership
messages, and receipts from an approved sender subdomain. Do not replace the
Google Workspace MX records.

Install and configure the selected SMTP plugin/provider through its protected
settings or Hostinger secret facility. The CYWater environment plugin does not
implement production SMTP itself. After the provider is configured, change only
the readiness marker:

```php
define( 'CYWATER_MAIL_TRANSPORT', 'smtp' );
```

Keep all SMTP credentials outside Git. Test Gmail and a non-Gmail recipient
before enabling membership mail.

PMPro must use `CYWater` as its sender name and `web@cywater.org` as its sender
email. The WordPress administrator notification email also uses
`web@cywater.org`. These are non-secret association identities; do not replace
them with a Hostinger default sender or a nonexistent staging-only mailbox.

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
