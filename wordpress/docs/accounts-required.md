# Accounts Required

No account below should be registered under a developer's personal ownership.
The Board should appoint an owner and at least one recovery administrator.

| Account | Required now | Owner/evidence needed | Current state |
| --- | --- | --- | --- |
| GitHub organization/repository | Yes | Board-controlled owners, 2FA, recovery codes | Existing repository; access review pending |
| Hostinger managed WordPress | Before staging | Organization billing authority and payment method | Blocked by payment authority |
| Domain registrar | Before DNS cutover | Confirm legal owner and renewal contacts | Access/ownership to confirm |
| Cloudflare | Before staging | Board-controlled account, two admins, API token | Not configured |
| Stripe Sandbox | For payment acceptance | Organization account owner; developer may receive Sandbox role | Credentials not supplied |
| Stripe live account | Before live payments | Legal entity, operating country, identity verification, bank account | Deliberately blocked |
| Transactional SMTP | Before staging email acceptance | Domain access, billing authority, sender verification | Not configured; Mailpit used locally |
| Off-site backup storage | Before production | Organization owner, retention policy | Not configured |

Alipay, WeChat Pay, and UnionPay remain later payment-method projects. Enable
them only after Stripe/processor eligibility, merchant review, currency and
refund behavior, and one-time versus recurring support are confirmed for the
CYWater legal entity.
