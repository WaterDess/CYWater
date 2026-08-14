# Module Boundaries

The dependency direction is deliberately one-way:

```text
CYWater theme (presentation)
        |
        v
CYWater Core (public content models)        CYWater Forum
        +------------------------------+    (member-authored articles)
        |                              |            |
        v                              v            |
Event Tickets                 CYWater Membership <--+
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
| `plugins/cywater-forum` | Forum article type, categories/topics, author endorsement, forum authorship roles, discussion scoping, forum policy parameters | Membership state, payment, secrets, core content types, theme layout |
| `plugins/cywater-environment` | Environment reads, Mailpit routing, test/live safety gates, readiness report, conservative response headers | Membership rules, content rendering, full CSP policy |
| Paid Memberships Pro | Registration, orders, membership activation, renewal/expiry mechanics, Stripe gateway/webhook | CYWater content and visual design |

## Rules

1. Custom post types stay in a plugin, so content survives a theme change.
   Association-published content models — News, Events, Awards, Board roles —
   stay in `cywater-core`. The forum is the one deliberate exception to keeping
   them all in one module: it is member-published rather than
   association-published, it carries its own authorship, endorsement, and
   discussion policy, and folding that into Core would blur that module's
   "public content models" ownership. The dependency stays one-way — the forum
   reads membership and account-verification state, and neither Core nor
   Membership knows the forum exists.
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
11. Comments exist only on forum articles. No other post type may open
    discussion, and the restriction is enforced by filter rather than by
    convention.
12. Author archives stay closed by default. `cywater-environment` owns that
    protection and exposes `cywater_public_author_archive_allowed`; a module may
    open one account that has actually published, never the archive wholesale.
13. The forum's AI seam is declared and dormant. No outbound call may appear in
    it until the association approves the feature; `npm run validate` enforces
    this. When implemented, the reaction renders client-side only and must fail
    silently, leaving no visible trace of the feature.
14. Forum authorship policy lives in versioned defaults plus one administrator
    option. Publishing prerequisites are evaluated per request, not frozen into
    a role, so a lapsed membership stops new publishing without destroying an
    author's drafts or existing articles.
