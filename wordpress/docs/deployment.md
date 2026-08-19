# Deployment Plan

## Environments

| Environment | Purpose | Data and integrations |
| --- | --- | --- |
| Development | Local code/content work | Disposable data, Playground or wp-env, Mailpit, Stripe sandbox |
| Staging | Release acceptance | Sanitized test users, real HTTPS, external SMTP sandbox, Stripe sandbox |
| Production | Public service | Real members, approved SMTP, approved Stripe live account |

Production data must never be copied to development without documented
anonymization. Staging and production use separate databases, API keys, webhook
secrets, salts, and administrator accounts.

## Hosting

CYWater now has a Hostinger Business managed WordPress plan with daily backup,
on-demand backup, staging, SSL, SSH/SFTP, PHP 8.3, cron, and database access.
The WordPress integration is deployed at `staging.cywater.org`; production DNS
has not been switched to the WordPress site.

Squarespace Domains currently remains the registrar and authoritative DNS
provider. Google Workspace mail records and the staging host record must be
preserved during every DNS change. Cloudflare remains an optional later
migration and is not a prerequisite for staging acceptance. If Cloudflare is
adopted, the origin must also keep a valid certificate and SSL mode must be Full
(strict), never Flexible.

## Release Flow

```text
feature branch -> pull request -> CI artifact -> staging deploy
-> acceptance + backup -> tagged release -> production deploy -> smoke test
```

Source lives in GitHub. Production files are never edited in the WordPress theme
or plugin editor (`DISALLOW_FILE_EDIT=true`) and never patched manually over
SFTP. The eventual deployment workflow installs the reviewed theme and custom
plugins from a tagged artifact, runs database migrations/setup, and verifies
health before switching traffic. Hosting credentials are GitHub Environment
secrets with production approval protection.

Database and media are backed up by the host and copied periodically to a
separate storage account. A restore is accepted only after it has been performed
on staging and the restored login, content, membership, and media records pass a
smoke test.

## Runtime Configuration

Use host environment variables or guarded `wp-config.php` constants outside the
repository. Required names are listed in `.env.example`. WordPress salts,
database credentials, Stripe keys, webhook secrets, SMTP passwords, and deploy
credentials are never committed or displayed on readiness pages.
