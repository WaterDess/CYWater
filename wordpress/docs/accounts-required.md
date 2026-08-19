# Accounts Required

No account below should be registered under a developer's personal ownership.
The Board should appoint an owner and at least one recovery administrator.

| Account | Required now | Owner/evidence needed | Current state |
| --- | --- | --- | --- |
| GitHub organization/repository | Yes | Board-controlled owners, 2FA, recovery codes | Existing repository; access review pending |
| Hostinger managed WordPress | Yes | Organization role address, two authorized operators, billing record | Business plan active; staging deployed; account/recovery review pending |
| Domain registrar (`cywater.org`) | Yes | Confirm legal owner, registrar access, renewal contacts, and current DNS authority | Squarespace Domains active; administrator access confirmed; ownership/recovery audit pending |
| Organization email / Google Workspace | Yes | Two named administrators, individual MFA, recovery owners | Business Starter active with two named users and organization role groups |
| Cloudflare | Optional later | Board-controlled account, two admins, API token | Not configured; Squarespace DNS remains authoritative |
| Stripe Sandbox | For payment acceptance | Organization account owner; developer may receive Sandbox role | Credentials not supplied |
| Stripe live account | Before live payments | Legal entity, operating country, identity verification, bank account | Deliberately blocked |
| Transactional email | Before staging email acceptance | Organization owner, domain access, billing authority, sender verification | Postmark connected and domain-authenticated; token rotation and one delivered test user-confirmed; workflow acceptance pending |
| Off-site backup storage | Before production | Organization owner, retention policy | Not configured |

Alipay, WeChat Pay, and UnionPay remain later payment-method projects. Enable
them only after Stripe/processor eligibility, merchant review, currency and
refund behavior, and one-time versus recurring support are confirmed for the
CYWater legal entity.
