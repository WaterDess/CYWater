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

The preferred first production platform is managed WordPress hosting from
Hostinger, selected at purchase time only after confirming current price,
renewal price, daily backups, on-demand restore, SSL, staging support, SSH/SFTP,
PHP 8.3+, cron, and database access. Total fixed annual platform cost must remain
under USD 1,000. No service is purchased in this branch.

Cloudflare owns authoritative DNS, CDN, baseline WAF/rate limiting, and HTTPS
edge policy. The origin also keeps a valid certificate; SSL mode must be Full
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
