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
| `themes/cywater` | Templates, public CSS, editor-canvas visual parity, images, navigation presentation | Content types, payment state, secrets |
| `plugins/cywater-core` | News import, Events, Awards, Board roles, editorial metadata and native editor panels | Checkout, member profiles, public-site CSS |
| Event Tickets | Tickets and RSVPs attached only to `cyw_event`, capacity, attendees, event-order state | Membership levels, PMPro orders, CYWater content types, duplicated event records |
| `plugins/cywater-membership` | PMPro levels, professional profile fields, privacy opt-in, directory, read-only admin projection | Stripe SDK, webhook endpoint, theme layout, duplicate member/order storage |
| `plugins/cywater-partnerships` | Institutional expressions of interest, private applicant status, Board/MOU workflow, approved external payment-link handoff | Individual membership, PMPro orders, Stripe credentials, automatic Board approval |
| `plugins/cywater-logo-call` | Event-scoped submissions and votes, protected design files, immutable work numbers, entrant acceptance and finalist/selection/handoff state | Theme/navigation, account or membership state, payment state, automatic rights transfer or reward grant |
| `plugins/cywater-forum` | Forum articles and taxonomies, verified active-member direct publication and own-content lifecycle, likes, opaque privacy-scoped author pages, Account activity projection, Forum-specific comments and moderator boundaries | Membership state, PMPro orders, unrelated WordPress comments, broad site administration |
| `plugins/cywater-operations` | Composable operational roles, minimal role/workflow audit, paid-Event terms approval and registration-readiness gate, sole Logo review UI and audited ZIP/CSV export | Logo submission/vote ownership, protected-file storage, PMPro membership/order administration, Stripe/Event Tickets transactions, event refunds, policy approval outside WordPress |
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
    Operations `0.1.11` supplies the deployed Event Tickets `5.29.1` paid-provider
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
    The `$0.50` `Sandbox Payment Test` level is only a staging acceptance
    fixture owned by `cywater-membership` and PMPro. It stays in a separate
    level group, grants no membership benefits, and is unavailable unless the
    environment is staging, PMPro is in Sandbox, and the CYWater payment mode
    is `test`.
14. CYWater Operations provides four deliberately broad, composable launch
    bundles rather than one role per screen:

    - **Content & Event Editor** owns Posts/News, media, Events, Awards, Event
      terms, Logo Call configuration, submission for paid-Event approval, and
      opening/closing registration only after approval;
    - **Community Moderator** owns Forum articles, Forum taxonomy, and comment
      moderation;
    - **Program Reviewer** inspects Logo Call entries and records voting
      eligibility, without confirming finalists, selecting the official logo,
      fulfilling rewards, or creating/deleting submissions and protected files;
    - **Governance Approver** owns Board records, Partner review/approval/payment
      confirmation, approval of submitted paid-Event terms, confirmation of the
      three Logo finalists, and official selection from those three. Final-file,
      rights-assignment and reward fulfillment remain Administrator-only.

    The bundles may be assigned together to a named organization account, but
    they remain separate from the account itself. Only a built-in Administrator
    may assign or remove these roles. No operational bundle can install plugins,
    edit themes, manage Administrators, configure payment credentials, or manage
    PMPro memberships/orders.
    Assignment is performed one account at a time under **Users -> CYWater staff
    access**. Search may locate an account by name, login, or email; the default
    view contains only already-authorized staff. The Users list provides badges
    and compact Account/Member record/Staff access/More actions, while the
    Dashboard work-area widget and WordPress menu expose only capability-
    authorized modules to delegated staff. A Community Moderator therefore sees
    Dashboard, Media, Forum, Comments, and Profile, not Users, Events,
    Memberships, plugins, themes, payments, or site settings. Administrators
    retain all routes but their sidebar is grouped into content,
    community/program, membership/account, and site-system sections. These are
    administration surfaces for the same audited bundles, not a second
    authorization system; hidden navigation never grants or revokes authority.
15. The editorial workspace preserves the same ownership boundaries. The theme
    may style Gutenberg so the content canvas resembles the public page. Core
    exposes its existing News, Event, and Award metadata through REST-backed
    document panels. Operations may reposition an authorized module's existing
    controls but must not copy or resave that module's data. Public page
    visibility, Event registration, Logo submission/voting audiences, and paid-
    Event approval are separate decisions. Public CYWater editorial types do
    not use PMPro content restriction or per-post LiteSpeed tuning.
16. A paid Event follows one explicit state machine:
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
17. The operations audit intentionally stores only actor/subject/object IDs,
    action, prior/new state, timestamp, and a short machine reason. It must not
    duplicate names, email addresses, application notes, content, payment data,
    or credentials.

## Verified Staging Boundary

### 2026-08-20 profile and Logo workflow acceptance

Membership `0.9.1`, Logo Call `0.3.0`, and Operations `0.2.0` supersede the
historical Logo/Profile evidence below on staging. Membership owns the complete
canonical country/region list and required professional fields; its searchable
combobox always posts a validated country code and does not create a parallel
profile store. Membership `0.9.1` also applies the registration minimum of 12
characters to PMPro's authenticated Change Password form and WordPress' lost-
password reset validation. Logo Call owns one protected initial design file, immutable work
number and entrant acceptance record. Program Reviewer may decide voting
eligibility only. Governance Approver confirms the three ranked finalists and
selects the Board's official design from those three. Only an Administrator may
record the later rights assignment, production-file acceptance and reward
fulfillment. Operations owns the sole Logo review UI and its audited ZIP/CSV
export but does not own submissions, votes, membership state or protected-file
storage. The module is included in the first production-clean release while
staging submissions, votes, identities, and protected files remain excluded.

The verified boundary below is historical staging evidence, not approval of
the 2026-08-20 remediation candidate. That candidate additionally composes paid
Event checkout with `CYWater_Config::live_payments_allowed()`, constrains Partner
payment handoffs to exact Stripe hosts, revokes Partner bearer access outside
the active private lifecycle, requires current email verification for public
directory rendering, and moves Forum covers out of public Media storage into a
lifecycle-aware protected stream with quotas and cleanup. None of these changes
grants Forum management to a member: member submission and Community Moderator
administration remain independent capabilities.

Hostinger File Manager recovered and archived the active Logo Call `0.2.3` and
Environment `0.5.5` source trees; normalized comparison matched 5/5 and 7/7
files. The production-clean build uses reconciled Environment `0.5.5` and the
accepted Logo Call `0.3.0`. Production activates the plugin with the reviewed
Event configuration but starts with no staging entries or votes. Its staging source and retained
review data are not deleted by this release decision.

CYWater Core `0.6.5`, CYWater Partnerships `0.1.2`, CYWater Operations `0.1.12`,
and CYWater Forum `0.3.2` are deployed on Hostinger staging. The self-cleaning
Operations suite passed 402 assertions; the four policy review surfaces passed
42 checks, the editor workspace passed 29 self-cleaning checks, Partner
workflow 17 checks, account flow 37 checks, rolling
membership terms 9 checks, and the free-RSVP and content lifecycle probes
passed. The separate real WordPress/MySQL Forum suite passed 100 assertions for
paused non-destructive endorsements, verified active-individual-member
submission, member draft/pending-only access, moderator-only publication,
immediate eligible-member replies, non-member/unverified denial, REST and
built-in Editor boundaries, staff-only restore, unchanged registered-user Logo
Call eligibility, and complete fixture cleanup. Three test messages were
intercepted and every temporary user, article, comment, PMPro row, and mail
intercept was removed. Its pre-deploy database/plugin/theme backup and live
rollback trees are retained under
`/home/u111638297/cywater-release-backups/forum-0.2.0-20260818T195836`. These results
verify the documented role, approval, audit, policy-draft, account,
membership-term, Partner, free-ticket, content, and Forum boundaries on staging;
they do not assert that production is deployed.

The Forum front-end workspace is an intentional member submission surface, not
an alternate destination for WordPress administration. A signed-in account
without an Administrator or assigned CYWater Operations role receives a direct
HTTP 403 at the requested `/wp-admin/` URL; Forum does not redirect the request
or override its login destination. Administrator and assigned staff access plus
the infrastructure endpoints required by front-end forms remain available.

Public visibility is deliberately separate from presentation metadata and from
workflow approval. A published News Post, Event, Award record, or ordinary Page
must remain queryable without migration-only order/source fields; missing
display facts may affect ordering or show an incomplete-state label but must not
silently erase a published record. Board person names still require explicit
`confirmed_public` governance approval. Forum publication, Partner private
status access, Logo shortlist/selection, paid-Event readiness, membership and
payment eligibility remain capability or workflow gates and are not converted
into ordinary publish-state behavior. Theme `0.6.22` and Core `0.6.4` passed a
21-assertion self-cleaning staging visibility QA for News, Event, Award and Page
publication; the 29-check editor, 396-check Operations, and 100-check Forum
suites plus the 17-check Partner and 13-check Logo Call suites also passed with
complete cleanup.

Cover presentation remains native and separate from editorial body content.
WordPress Featured image is the single cover source for News, Event and Award
list/card surfaces; no parallel image record and no implicit first-body-image
rule exists. On Event details, the cover is not inserted automatically above
the editorial body; images intentionally placed in Gutenberg remain visible.
Missing covers use the existing text tile. Event placement is an explicit
`_cyw_status` presentation fact: Archive is the missing-value default, while an
explicit Upcoming value feeds the upper carousel and remains in its lower
subject category. Within each category Upcoming records precede Archive;
Upcoming sorts by start time ascending and Archive by start time descending.
Core `0.6.5` and theme `0.6.25` passed the 38-check self-cleaning editor QA with
zero temporary Events and matched the active staging file trees exactly. The
Upcoming carousel keeps one-to-one Event pagination in the theme: the compact
centered marker geometry remains unchanged while the old active marker contracts
and the new one expands with the same 560 ms transition as the Event window.
The content model and placement metadata remain unchanged.
