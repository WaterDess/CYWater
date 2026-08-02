# CYWater WordPress Integration

This directory describes the production candidate that is developed on the
`wordpress-integration` branch. The public GitHub Pages preview remains on
`main` and `gh-pages` until the WordPress site passes acceptance.

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
- `deployment.md` - development, staging, production, DNS, and release flow
- `staging-next-actions.md` - ordered gates after visual/content staging acceptance
- `hostinger-runtime-config.md` - exact non-secret staging and payment safety switches
- `accounts-required.md` - account ownership and current blockers
- `production-checklist.md` - launch acceptance gates
- `email-copy.md` - approved-content drafts, not active mail overrides

## Current State

Hostinger staging is available at `https://staging.cywater.org/`. The accepted
baseline uses the CYWater `0.5.2` theme and the `0.5.1` releases of
`cywater-core`, `cywater-membership`, and `cywater-environment`. PMPro is active,
but payment remains disabled, the live-payment gate remains closed, and Stripe
and production SMTP credentials are not configured. Postmark is connected on
staging, its domain authentication is verified, and the user confirmed a
successful post-rotation test message on 2026-08-02. Full transactional email
workflow acceptance remains open.

The next environment-plugin release is `0.5.2`. It preserves configured SMTP
sender names outside local Mailpit and reports whether the WordPress file editor
is disabled. It does not change the accepted public design or content baseline.

The repository contains no live Stripe key, bank credential, production SMTP
credential, domain credential, or hosting credential. GitHub Pages remains the
visual and content reference until staging completes security, mail, Stripe
Sandbox, membership-flow, mobile, and restore acceptance.
