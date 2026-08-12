# Module Boundaries

The dependency direction is deliberately one-way:

```text
CYWater theme (presentation)
        |
        v
CYWater Core (public content models)
        +------------------------------+
        |                              |
        v                              v
Event Tickets                 CYWater Membership
(event registration)          (profile/privacy policy)
        |                              |
        |                              v
        |                     Paid Memberships Pro
        |                     (membership state/orders)
        +---------------+--------------+
                        |
                        v
              CYWater Environment
              (configuration/safety)
                        |
                        v
              Stripe / SMTP / host
```

## Ownership

| Module | Owns | Must not own |
| --- | --- | --- |
| `themes/cywater` | Templates, CSS, images, navigation presentation | Content types, payment state, secrets |
| `plugins/cywater-core` | News import, Events, Awards, Board roles, editorial metadata | Checkout, member profiles, CSS |
| Event Tickets | Tickets and RSVPs attached only to `cyw_event`, capacity, attendees, event-order state | Membership levels, PMPro orders, CYWater content types, duplicated event records |
| `plugins/cywater-membership` | PMPro levels, professional profile fields, privacy opt-in, directory, read-only admin projection | Stripe SDK, webhook endpoint, theme layout, duplicate member/order storage |
| `plugins/cywater-partnerships` | Institutional expressions of interest, private applicant status, Board/MOU workflow, approved external payment-link handoff | Individual membership, PMPro orders, Stripe credentials, automatic Board approval |
| `plugins/cywater-logo-call` | Temporary event-scoped submissions, protected design files, shortlisting, eligible-member vote | Theme/navigation, membership state, payment state, permanent IP transfer |
| `plugins/cywater-environment` | Environment reads, Mailpit routing, test/live safety gates, readiness report, conservative response headers | Membership rules, content rendering, full CSP policy |
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
7. The CYWater administrator record reads WordPress and PMPro data in place. It
   must not persist a second copy of identity, membership, order, or payment
   state.
8. Event Tickets is filtered to the existing `cyw_event` post type. Do not
   enable its Page/Post surfaces or install a second event-content model.
9. PMPro remains authoritative only for membership dues. Event Tickets owns
   conference/event registrations. A membership refund revokes the matching
   membership; an event refund cancels only the matching attendee/seat. Neither
   path may call the other's entitlement logic.
10. Free RSVP and paid event tickets share the same event record, but paid
    checkout stays disabled until the association's existing Stripe Sandbox is
    connected to Event Tickets and the refund/duplicate-webhook matrix passes.
11. Partner is not a member. New institutional partner applications live only
    in `cywater-partnerships`; no payment link may render before Board approval
    and MOU completion. The historical PMPro Partner level is retained only for
    existing records with signup disabled.
12. Logo Call is a removable event module, not a permanent theme feature. It
    reads current PMPro levels, stores no duplicate member/order data, and
    appears only on an explicitly enabled `cyw_event`. Deactivation removes the
    UI without deleting the event or review records. Selection alone does not
    transfer intellectual property; permanent use requires a separate written
    Board-approved assignment or license.
