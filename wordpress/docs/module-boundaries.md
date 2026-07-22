# Module Boundaries

The dependency direction is deliberately one-way:

```text
CYWater theme (presentation)
        |
        v
CYWater Core (public content models)

CYWater Membership (profile, privacy, level policy)
        |
        v
Paid Memberships Pro (accounts, orders, membership state)
        |
        v
CYWater Environment (configuration and safety gates)
        |
        v
Stripe / SMTP / host environment
```

## Ownership

| Module | Owns | Must not own |
| --- | --- | --- |
| `themes/cywater` | Templates, CSS, images, navigation presentation | Content types, payment state, secrets |
| `plugins/cywater-core` | News import, Events, Awards, Board roles, editorial metadata | Checkout, member profiles, CSS |
| `plugins/cywater-membership` | PMPro levels, professional profile fields, privacy opt-in, directory | Stripe SDK, webhook endpoint, theme layout |
| `plugins/cywater-environment` | Environment reads, Mailpit routing, test/live safety gates, readiness report | Membership rules, content rendering |
| Paid Memberships Pro | Registration, orders, membership activation, renewal/expiry mechanics, Stripe gateway/webhook | CYWater content and visual design |

## Rules

1. Custom post types stay in `cywater-core`, so content survives a theme change.
2. The theme calls public WordPress or PMPro APIs only; it never writes payment
   or membership records.
3. CYWater does not implement a second Stripe webhook handler. PMPro remains the
   transaction authority and its behavior is tested at the boundary.
4. Secrets are injected by the runtime. `.env` files are local only and are not
   loaded or committed by application code.
5. Seed import creates initial content. Normal reruns preserve all existing
   imported posts and metadata. `wp cywater setup --force-import` is a destructive
   maintenance operation and requires a database backup plus explicit approval.
6. The public member directory is opt-in twice: a master profile switch and a
   per-field allowlist. Email addresses are never rendered.
