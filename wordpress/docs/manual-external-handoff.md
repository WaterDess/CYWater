# Manual And External Handoff

All engineering work that can be completed safely from the repository and the
current staging access is complete. The items below require an association
decision, a named human identity, a paid license, a second browser/account, or
a destructive host operation. They are not application-code defects.

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
5. If non-administrator membership staff are required, buy the official PMPro
   Membership Manager Add On, install it on staging, assign it to a named staff
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

Status recorded on 2026-08-03: MFA is **deferred** because CYWater currently
has no association-controlled phone, tablet, security key, or equivalent
managed authenticator. Do not make a private personal device the sole second
factor merely to close the checklist. This remains an accepted security risk
to revisit before production handover or when association-controlled hardware
becomes available.

## 2. Final Mail Acceptance

1. Approve the legal footer and production wording in every template listed in
   `email-copy.md`.
2. Use an association-controlled non-Gmail recipient and request one password
   reset plus one Sandbox membership receipt and one event RSVP confirmation.
3. Confirm both messages in the recipient inbox and confirm **Delivered** in
   Postmark Activity. Record message IDs and dates, never the Server API Token.

## 3. Policy Decisions

The Board must approve the privacy notice, terms, refund policy, recurring
billing policy, rolling annual-term rule, data-retention rule, and
account-erasure process. Membership cancellation, a full refund, sign-out, and
account erasure are intentionally different operations:

- sign-out ends only the browser session;
- cancellation removes future membership access but preserves the account and
  financial record;
- a full membership refund cancels only the membership funded by that order;
- deletion/anonymization of a paid account must follow the approved retention
  policy and must not silently destroy required accounting records.

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

The reusable framework is installed: Event Tickets `5.29.1` attaches tickets,
capacity, attendees, and event-order state only to the existing `cyw_event`
record. A free RSVP lifecycle passed on staging and left no QA data. Paid
checkout remains closed because connecting this second Stripe integration is an
external account authorization, not a code task.

Before opening the first paid event, provide or approve:

- the event and currency;
- attendee classes and exact prices;
- member eligibility and discount rules;
- early/standard deadlines, capacity, and wait-list behavior;
- required attendee/abstract fields;
- cancellation, transfer, refund, tax, and invoice rules.

Then, while signed into the association's existing Stripe Sandbox, open
WordPress **Tickets → Settings → Payments**, connect Stripe in test mode, and
leave Live disabled. Do not paste or disclose PMPro API keys. Run the paid-event
matrix: success, decline, buyer cancellation, capacity exhaustion, duplicate
webhook, full refund, and confirmation email. The Event Tickets order and
attendee report must agree after each case, and a full refund must cancel only
the matching registration/seat.

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
3. Deploy the exact accepted theme/plugin artifacts; install live secrets only
   through the host environment, never Git or chat.
4. Perform one small live payment and full refund, reconcile Stripe, PMPro,
   Postmark, and the association bank record, and confirm entitlement removal.
5. Monitor errors, email, webhooks, and payment activity during the change
   window. Roll back the artifact and DNS according to `deployment.md` if the
   acceptance check fails.
6. Keep the GitHub Pages original unchanged until the association separately
   approves its transition or redirect plan.
