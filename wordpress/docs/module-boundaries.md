# Module Boundaries

The dependency direction is deliberately one-way:

```text
CYWater theme (presentation)
        |
        v
CYWater Core (public content models)
        |
        v
CYWater Operations (roles, audit, approval gates)
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
| `plugins/cywater-logo-call` | Temporary event-scoped submissions, protected design files, shortlisting, configurable submission/voting audiences, reward-fulfillment status | Theme/navigation, account or membership state, payment state, permanent IP transfer |
| `plugins/cywater-forum` | Forum articles and taxonomies, verified-member author workflow, Forum-specific comment and moderation boundaries | Membership state, PMPro orders, unrelated WordPress comments, broad site administration |
| `plugins/cywater-operations` | Composable operational roles, minimal role/workflow audit, paid-Event terms approval and registration-readiness gate | PMPro membership/order administration, Stripe/Event Tickets transactions, event refunds, policy approval outside WordPress |
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
   conference/event registrations. A membership payment correction, chargeback,
   processor reversal, or other legally required adjustment affects only the
   matching membership and renewal. An Event refund cancels only the matching
   attendee/seat. Neither path may call the other's entitlement logic.
10. Free RSVP and paid event tickets share the same event record. CYWater
    Operations `0.1.3` supplies the deployed Event Tickets `5.29.1` paid-provider
    adapter plus fail-closed gates at ticket UI, cart preparation/processing,
    checkout request, and final Stripe order REST creation. Paid checkout still
    stays closed while Tickets Commerce, its Stripe gateway, checkout/success
    pages, and a real paid ticket are unconfigured, and until the complete
    success/decline/cancel/refund/duplicate/capacity/email matrix passes.
11. Partner is not a member. New institutional partner applications live only
    in `cywater-partnerships`; no payment link may render before Board approval
    and MOU completion. The historical PMPro Partner level is retained only for
    existing records with signup disabled.
12. Logo Call is a removable event module, not a permanent theme feature.
    WordPress accounts, PMPro membership state, Events, and participation
    permissions remain separate. Each enabled `cyw_event` independently chooses
    whether submission and voting are open to all registered users, all active
    individual members, or selected active membership levels. The module reads
    PMPro only when a membership-based rule is selected, stores no duplicate
    account/member/order data, and never changes membership as a side effect of
    a permission decision. Deactivation removes the UI without deleting the
    event or review records. Selection alone does not transfer intellectual
    property or grant the configured reward; both rights and reward fulfillment
    remain explicit administrator-reviewed steps. These temporary Event rules
    must not become the authority for Board elections or other governance votes;
    governance eligibility belongs in a separately approved governance module.
13. There is no local membership-refund approver or cloned Membership Manager
    role. The Administrator handles PMPro membership/order operations and
    billing-correction records until the association chooses to delegate that
    work. The official PMPro Membership Manager Add On is not installed, does
    not block payment collection, and is required only for a later
    non-administrator delegation; do not reproduce it locally.
14. CYWater Operations provides four deliberately broad, composable launch
    bundles rather than one role per screen:

    - **Content & Event Editor** owns Posts/News, media, Events, Awards, Event
      terms, Logo Call configuration, submission for paid-Event approval, and
      opening/closing registration only after approval;
    - **Community Moderator** owns Forum articles, Forum taxonomy, and comment
      moderation;
    - **Program Reviewer** uses one review bundle to inspect/update Logo Call
      entries and record shortlisting/reward fulfillment, without a separate
      fulfillment capability, without creating/deleting submissions or
      protected files, and without granting membership automatically;
    - **Governance Approver** owns Board records, Partner review/approval/payment
      confirmation, and approval of submitted paid-Event terms.

    The bundles may be assigned together to a named organization account, but
    they remain separate from the account itself. Only a built-in Administrator
    may assign or remove these roles. No operational bundle can install plugins,
    edit themes, manage Administrators, configure payment credentials, or manage
    PMPro memberships/orders.
15. A paid Event follows one explicit state machine:
    `Draft -> Terms complete -> Pending approval -> Approved -> Registration
    open -> Closed`. The Event editor supplies fee/currency, cancellation
    deadline, refund, transfer, capacity/wait-list, and cancellation/
    postponement/format-change terms. A Governance Approver approves the
    submitted fingerprint; that capability is absent from the Content & Event
    Editor role. Because the role bundles are composable, production role
    assignment must keep the submitting editor and approver on different named
    accounts whenever independent approval is required. The editor can open
    registration only while that approval is current. A material change after
    submission or approval invalidates the approval and closes the readiness
    gate. Event Tickets and Stripe still own registrations, orders, charges,
    refunds, and webhooks. The deployed Event Tickets adapter consumes a
    structured readiness snapshot and compares the authoritative ticket fee and
    currency with the current approval. Missing or exceptional adapter state,
    unreadable or mismatched ticket configuration, a false checkout-ready
    result, a changed/missing approval fingerprint, or a failed strict-audit
    write must all fail closed before any UI, cart, checkout, or final Stripe
    order creation proceeds. A visible `Registration open` state alone is never
    sufficient.
16. The operations audit intentionally stores only actor/subject/object IDs,
    action, prior/new state, timestamp, and a short machine reason. It must not
    duplicate names, email addresses, application notes, content, payment data,
    or credentials.

## Verified Staging Boundary

CYWater Core `0.6.1`, CYWater Partnerships `0.1.2`, CYWater Operations `0.1.3`,
and CYWater Forum `0.1.2` are deployed on Hostinger staging. The self-cleaning
Operations suite passed 367 assertions; the four policy review surfaces passed
42 checks, Partner workflow 17 checks, account flow 37 checks, rolling
membership terms 9 checks, and the free-RSVP and content lifecycle probes
passed. The separate real WordPress/MySQL Forum suite passed 77/77 checks across
publication, verified-member eligibility, first-reply moderation, role
boundaries, public visibility, trash/untrash protection, moderator restoration,
and cleanup. Two test messages were intercepted and every temporary user,
article, comment, PMPro row, and mail intercept was removed. These results
verify the documented role, approval, audit, policy-draft, account,
membership-term, Partner, free-ticket, content, and Forum boundaries on staging;
they do not assert that production is deployed.
