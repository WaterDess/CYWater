# Manual And External Handoff

The public production site is live; only end-to-end financial acceptance remains
open. Stripe Live is connected and enabled, but it is not yet financially
accepted.
Repository remediation is implemented on staging and production and passes the
real WordPress/MySQL automation suites. The final open production gate is one
controlled real charge, receipt, balance/payout observation and full-refund
reconciliation through the association-owned Stripe Live account.
Hostinger File Manager recovered and archived the former Logo Call `0.2.3` and
Environment `0.5.5` trees before the accepted releases. The current production
baseline is Membership `0.9.6`, Partnerships `0.1.5`, Logo Call `0.4.2`, Forum
`0.6.1`, Operations `0.3.2`, Environment `0.5.7`, Core `0.6.6`, and theme
`0.6.47`. Forum staging QA passed 149 self-cleaning assertions; production
anonymous-route, REST, navigation, file-integrity, and cache checks passed.
See `production-checklist.md` for the
authoritative order.

The items below additionally require an association decision, a named human
identity, a paid license, a second browser/account, or a destructive host
operation. They are not all application-code defects, but each applicable item
remains a launch gate until accepted or explicitly deferred by the association.

## 1. Identity, Recovery, And Staff Access

Decision recorded on 2026-08-03: CYWater will keep one durable full WordPress
Administrator identity owned by the association. On staging its login is
`web@staging.cywater.org` and its notification/recovery email is
`web@cywater.org`. A second full WordPress Administrator is **not** a launch
requirement under this policy.

Remaining actions:

1. Enable MFA on the association-controlled Administrator. Register at least
   two association-held security keys/passkeys or another recovery arrangement
   that does not depend on one person's private phone, and store recovery codes
   in the Board-approved recovery location.
2. Keep `web@cywater.org` as the association-owned platform notification and
   recovery identity. Do not replace it with a private personal mailbox.
3. Where a service supports team/delegated access, staff should still use named
   organization accounts for auditability while `web@cywater.org` remains the
   owner/recovery identity. If several people share one WordPress login, accept
   that WordPress logs cannot reliably distinguish which person acted.
4. If the association later decides that independent lockout recovery is more
   important than the single-Administrator policy, it may add a second named
   Administrator without changing platform ownership.
5. The official PMPro Membership Manager Add On is not installed and is not a
   prerequisite for collecting membership payments. The Administrator currently
   handles membership and order operations, including billing-correction and
   dispute records. If this work is later delegated to non-administrator staff,
   buy the official Add On, install it on staging, assign it to a named staff
   user, and verify that the user can manage members/orders but cannot install
   plugins, edit themes, or manage administrators. Do not create a local clone
   of this licensed role.
6. For News, Events, Awards, Board, and media work, create a named organization
   WordPress user with the built-in **Editor** role. The capability boundary is
   already verified; an actual user cannot be created until the association
   supplies that person's organization email.
7. Contact replies are mailbox work, not WordPress administration. Add named
   organization users as authorized members/delegates of `contact@cywater.org`
   in Google Workspace. Do not give them WordPress Administrator access merely
   to answer mail.
8. Confirm that `accounts@cywater.org` is a real receive-capable Google
   Workspace alias or group, not only an outbound address verified by Postmark.
   Assign at least two association-authorized custodians and test receipt. Keep
   account-support replies routed to `membership@cywater.org`.

Status recorded on 2026-08-03: MFA is **deferred** because CYWater currently
has no association-controlled phone, tablet, security key, or equivalent
managed authenticator. Do not make a private personal device the sole second
factor merely to close the checklist. This remains an accepted security risk
to revisit before production handover or when association-controlled hardware
becomes available.

### Operational role assignment

The launch matrix uses four composable roles. They are not automatically
assigned to a real person, and a title or membership level never grants them.
After a named organization account is supplied, the built-in Administrator may
assign one or more roles under **Users -> CYWater staff access**:

| Role | Permitted launch work | Explicitly excluded |
| --- | --- | --- |
| CYWater Content & Event Editor | Posts/News, media, Events, Awards, Event terms, Logo Call configuration, submit paid Event for approval, open/close registration after approval | Governance approval, Forum moderation, PMPro members/orders, payment settings |
| CYWater Community Moderator | Cross-author Forum editing, take-down/restoration/permanent deletion, taxonomy, replies, and engagement cleanup | Other public content models, Events, membership/payment administration |
| CYWater Program Reviewer | Inspect Logo entries and record eligibility for voting | Confirm finalists, select the official logo, delete submissions or protected files, fulfill rewards, Event/Partner approval, PMPro orders |
| CYWater Governance Approver | Board records, Partner review/approval/payment confirmation, paid-Event approval, confirm a three- or five-entry Logo finalist group and select the official design from that group | Event editing, Logo reward/final-file fulfillment, PMPro membership/order operations, gateway credentials |

Only a built-in Administrator may assign or remove these roles. The operational
audit records identifiers, role/workflow states, time, action, and a short reason
only; it intentionally excludes names, email, content, application notes,
payment data, and credentials. CYWater Operations `0.2.0` is deployed, and its
self-cleaning staging QA passed all 431 role, workflow, strict-audit, navigation, and
fail-closed checks. This did not assign a real staff user. CYWater Forum `0.2.0`
and theme `0.6.19` were then atomically deployed and kept active; the separate
real WordPress/MySQL self-cleaning QA passed 100 assertions for paused legacy
endorsements, verified active-individual-member submission, member draft/pending
only access, moderator publication, immediate eligible-member replies,
non-member/unverified denial, role/REST boundaries, visibility, trash/untrash,
Logo Call separation and cleanup. It intercepted three verification messages
and removed all temporary users, articles, comments, PMPro rows and mail
intercepts. The pre-deploy database, plugin/theme archives and live rollback
trees are retained at
`/home/u111638297/cywater-release-backups/forum-0.2.0-20260818T195836`.
Before assigning a real account, verify the named user's exact screens and
listed work; do not assign broad roles merely to make a screen visible. Before
production cutover, delete or replace the staging Forum preview placeholder
article; do not publish it as association content.

To grant access, search by the staff member's name, login, or email, select
**Manage access** for exactly one account, confirm the displayed username,
email, user ID, and base WordPress role, select only the needed operational
bundle or bundles, and save. The default directory intentionally shows only
accounts that already have operational access; it does not render every member
as a large checkbox matrix. The standard Users list also exposes access badges
and the same per-account management action. After saving, ask the staff member
to sign in and use **Dashboard -> CYWater work areas** or the capability-filtered
left menu. The full Administrator's menu remains broad by design. Removing all
bundles returns the account to its non-operational WordPress role, and every
assignment change remains subject to the strict audit.

Program review deliberately has no independent reward-fulfillment capability.
The same review bundle may record shortlist and fulfillment status, but it may
not fabricate or delete a participant's submission or protected files and it
never grants or changes PMPro membership automatically.

## 2. Final Mail Acceptance

Base production transport and the Accounts identity route passed on 2026-08-21:
the single controlled message `CYW-MAIL-20260820183111` returned Postmark
ErrorCode `0` / `OK` and was visibly received in Gmail from
`CYWater Accounts <accounts@cywater.org>` at `web@cywater.org`. The application
supplied `membership@cywater.org` as Reply-To. It changed no account,
membership, order, or payment data. Production Postmark and the
`CYWATER_MAIL_TRANSPORT=smtp` readiness marker are enabled. The remaining steps
below complete the wider template and recipient matrix.

1. In ActiveCampaign Postmark, set Message Stream to `outbound` and Sender Email
   to verified `web@cywater.org` as the platform fallback. Keep **Force Sender
   Email** off; otherwise Postmark will erase the functional `From` identities
   selected by CYWater.
2. Verify the production responsibility split: `accounts@cywater.org` for
   account verification/security and password or account changes (Reply-To
   `membership@cywater.org`); `membership@cywater.org` for membership lifecycle,
   member support, Forum, Logo Call and member programs; `billing@cywater.org`
   for paid checkout, orders/receipts, recurring payments, failures/actions and
   refunds; `contact@cywater.org` for public contact, Partnership, free
   Event/RSVP and media; and `web@cywater.org` only for platform
   ownership/recovery, technical/admin alerts and fallback transport.
3. Confirm that PMPro routes paid/billing templates through
   `billing@cywater.org` and free checkout plus membership
   change/cancellation/expiration templates through `membership@cywater.org`.
   It must not use one uniform PMPro sender.
4. Approve the legal footer and production wording in every template listed in
   `email-copy.md`.
5. Complete the remaining matrix with an association-controlled non-Gmail
   recipient: request one password reset, one free membership lifecycle
   message, one Sandbox paid-membership receipt, and one event RSVP
   confirmation.
6. Confirm every message in the recipient inbox and as **Delivered** in Postmark
   Activity. Verify the expected `From` and `Reply-To`, and record message IDs
   and dates, never the Server API Token.

## 3. Policy Decisions

The Board must approve the privacy notice, terms, billing/refund policy, recurring
billing policy, rolling annual-term rule, data-retention rule, and
account-erasure process. Membership cancellation, a billing correction or
payment reversal, an Event refund, sign-out, and account erasure are
intentionally different operations:

- sign-out ends only the browser session;
- cancellation stops a future renewal or ends access under the applicable
  membership terms but preserves the account and financial record;
- the proposed launch rule is:
  **All membership sales are final and non-refundable.** There is no proration,
  unused-time credit, ordinary refund, or discretionary exception;
- duplicate charges, technical errors, unauthorized or fraudulent payments,
  chargebacks, processor reversals, and non-waivable statutory rights remain
  billing-correction or dispute paths rather than ordinary membership refunds;
- when a corrected or reversed membership payment no longer funds an
  entitlement, only the membership funded by that order and its matching
  renewal may be removed; the adjustment does not refund or cancel an Event
  order;
- an approved event refund cancels only its registration or program
  entitlement and does not cancel individual membership;
- deletion/anonymization of a paid account must follow the approved retention
  policy and must not silently destroy required accounting records.

There is no routine membership-refund approval workflow or refund-approver
role. The Administrator records and reconciles duplicate/technical corrections,
unauthorized or fraudulent payment reports, chargebacks, processor reversals,
and legally required remedies; this authority does not permit discretionary
membership refunds. Each paid Event must separately publish its cancellation
deadline, refund schedule, non-refundable fees, transfer/substitution rule,
capacity rule, and response to cancellation, postponement, or format changes.
If those terms are absent, paid registration must remain closed. Automatic
membership renewal also remains disabled unless the Board approves recurring
billing language and checkout records affirmative consent.

CYWater Core `0.6.1` and all four direct-link review surfaces are deployed. A
42-check staging QA passed their review-only/noindex safeguards and current
draft wording. This is technical validation only; the pages remain explicitly
not approved or in effect until Board and legal review.

Engineering now treats an account-closure request as a seven-day cooling-off
period measured from the request timestamp, not from inactivity. New membership
checkout is paused during the request and the member may withdraw it. At day
seven the request becomes due for administrator review; no scheduled job
automatically deletes an account. The Board still must approve which unpaid
accounts may be deleted, which paid/event records must be anonymized or
retained, the retention duration, and how retained backups are handled.

## 4. Institutional Partner Approval And Payment

The public application and approval gate are implemented. A partner is not a
member: it submits an expression of interest, and the WordPress Administrator
tracks Board review and MOU completion under **Partner applications**. No
payment action is available before **Approved to pay**.

CYWater Partnerships `0.1.2` is deployed, and its self-cleaning staging QA
passed all 17 Partner workflow checks. No temporary application was retained;
actual Board/MOU decisions and settlement confirmation remain human work.

For each approved organization:

1. Confirm the Board decision and completed MOU outside WordPress.
2. Create an association-controlled HTTPS Stripe invoice or payment link only
   after Stripe Live is approved. Do not paste API keys or credentials.
3. Set the WordPress application to **Approved to pay** and paste that URL into
   **Approved payment URL**. WordPress emails the applicant a new private status
   link containing the payment action.
4. After authoritative Stripe settlement, mark the application **Payment
   received** and publish any agreed name/logo/link recognition under the MOU.
5. Apply the Board-approved retention and erasure policy to application, MOU,
   recognition, and accounting records.

The historical PMPro Partner level is preserved only for existing records and
cannot accept new public signups.

## 5. Paid Event Or Conference Registration

The reusable ticket framework is installed: Event Tickets `5.29.1` attaches
tickets, capacity, attendees, and event-order state only to the existing
`cyw_event` record. A free RSVP lifecycle passed on staging and left no QA data.
CYWater Operations `0.2.0`, its paid-provider adapter, and fail-closed ticket UI,
cart, checkout-request, and final Stripe REST gates are deployed and included in
the 431-check Operations QA.

Paid checkout nevertheless remains closed in the actual staging configuration:
Tickets Commerce is disabled, its Stripe gateway is not enabled or connected,
checkout and success pages are not configured, and there is no real paid ticket.

Before opening the first paid event, provide or approve:

- the event and currency;
- attendee classes and exact prices;
- member eligibility and discount rules;
- early/standard deadlines, capacity, and wait-list behavior;
- required attendee/abstract fields;
- cancellation, transfer, refund, tax, and invoice rules.

The operational approval gate then follows this sequence:

1. A Content & Event Editor saves the Event as **Draft**, supplies every
   required public term, and marks it **Terms complete**.
2. The editor submits the exact terms fingerprint as **Pending approval**.
3. A different named account holding Governance Approver either returns it for
   revision or records **Approved**. The software keeps the approval capability
   out of the Content & Event Editor role, but because role bundles are
   composable, the Administrator must not combine both bundles on the same
   production account when independent approval is required.
4. The editor may then set the workflow state to **Registration open**, but
   that state alone does not expose checkout. The deployed Event Tickets adapter
   must consume a current, matching readiness snapshot before it renders or
   accepts paid registration.
5. The editor closes registration as **Closed**. Any material edit after
   submission or approval invalidates the fingerprint and closes the readiness
   gate until the terms are resubmitted and reapproved.

This workflow does not process money. Event Tickets and Stripe remain the
authorities for attendee, order, charge, refund, and webhook state. It also does
not create a membership refund path: the Board-review membership rule remains
**All membership sales are final and non-refundable.**

The deployed adapter fails closed when it is absent, throws an error, cannot
read authoritative ticket configuration, sees fee/currency mismatch, receives a
false readiness result, sees a changed/missing approval fingerprint, or cannot
commit the required strict audit. Its enforcement covers presentation, cart,
checkout, and the final Stripe order REST request. Do not infer payment
readiness merely from a visible **Registration open** state.

Then enable Tickets Commerce, configure its checkout and success pages, and,
while signed into the association's existing Stripe Sandbox, open WordPress
**Tickets → Settings → Payments**, connect Stripe in test mode, and leave Live
disabled. Do not paste or disclose PMPro API keys. Create one real paid ticket
with matching fee/currency and run the paid-event matrix: success, decline,
buyer cancellation, capacity exhaustion, duplicate webhook, full refund, and
confirmation email. The Event Tickets order and attendee report must agree after
each case, and a full refund must cancel only the matching registration/seat.

The free plugin adds a 2% application fee to Stripe transactions. Before paid
event launch, the association must either accept that fee or purchase Event
Tickets Plus. Plus is not required for the current free RSVP framework.

## 6. Host Restore And Cross-Browser Acceptance

1. In hPanel, create an on-demand staging backup.
2. Restore it into a separate staging clone, not over production.
3. Verify administrator sign-in, media, one Event, one News post, all PMPro
   levels, one member profile, and one order; then remove the temporary clone.
4. In a second browser engine (Firefox or Edge), verify home, membership,
   registration, sign-in, Account, Member Profile, checkout redirect, and one
   mobile viewport. Record only pass/fail and screenshots without credentials.
5. Enable Hostinger staging access protection while private review continues.

## 7. Stripe Live And Production Cutover

Prepare and complete the non-secret checklist in
`stripe-live-verification.md` before connecting Live mode.

1. Complete Stripe legal verification with a named authorized representative,
   while keeping platform ownership, recovery, and the payout bank account
   association-controlled. Use `billing@cywater.org` for billing notices.
2. Approve a DNS change window and create a fresh production backup.
3. Restore/deploy into the production target, remove or resolve every staging
   fixture and run `wp eval-file cywater-production-purity-audit.php`. It is
   read-only and must pass before DNS cutover.
4. Deploy the exact accepted theme/plugin artifacts; install live secrets only
   through the host environment, never Git or chat.
   The accepted staging theme is `0.6.19`; a 92/92-file local/staging manifest
   comparison was byte-identical with SHA-256
   `efacb24c6557ad42a51c6bbe9ded4e79b12f6eb959403cbee1f1c1df3942afaa`.
   This staging match is not evidence that production has already been updated.
5. Perform one small live payment and full refund, reconcile Stripe, PMPro,
   Postmark, and the association bank record, and confirm entitlement removal.
6. Monitor errors, email, webhooks, and payment activity during the change
   window. Roll back the artifact and DNS according to `deployment.md` if the
   acceptance check fails.
7. Keep the GitHub Pages original unchanged until the association separately
   approves its transition or redirect plan.
