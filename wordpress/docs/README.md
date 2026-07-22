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
- `accounts-required.md` - account ownership and current blockers
- `production-checklist.md` - launch acceptance gates
- `email-copy.md` - approved-content drafts, not active mail overrides

## Current State

The theme and three CYWater plugins run locally with WordPress 7.0.2 and PHP
8.3. PMPro 3.8.2 is version-locked in the wp-env configuration and prepared in
an ignored vendor directory; its full runtime requires wp-env/MySQL. No live
Stripe key, bank account, production SMTP credential, domain credential, or
hosting credential is present.
