# Member Workflow

## Registration

Registration is account-first and email-verified. It asks for a username,
email address, and password before membership checkout. The five fields needed
for membership statistics and regional programming are collected during the
membership step:

- institution or employer
- country or region of institution
- institution type
- current title or role
- career stage

Student, Professional, and Lifetime are separate PMPro membership levels.
Institutional Partner is not a member level and does not create a member account
or directory profile. Photo, ORCID iD, and research interests are completed
later from the member profile.

The account state is explicit:

1. Registration creates a private WordPress Subscriber and signs that browser
   in, but the email remains unverified.
2. WordPress sends a one-time link from
   `CYWater Accounts <accounts@cywater.org>` through the existing Postmark
   transport. Replies go to `membership@cywater.org`.
3. The link expires after 24 hours and is deleted after its first successful
   use. Replaying the same link is rejected.
4. An unverified account may sign in and request another link, but it cannot
   enter PMPro checkout, activate a membership, or publish a directory profile.
   Resends are limited to one per minute.
5. Changing the account email invalidates the previous verification and sends
   a new link to the replacement address.

The deployment migration marks accounts that existed before this gate as
verified for their current stored email. New accounts must always complete the
link flow. Verification tokens are stored only as salted hashes; plaintext
tokens appear only in the member's email link and are never written to Git,
documentation, logs, or administrator screens.

## Privacy

Profiles are private by default. A member must opt into the public directory and
then select each field that may appear. Changing the master switch off removes
the entire listing without deleting profile data. Public output is restricted
to PMPro members whose actual end date has not passed, and email is never an
available public field. The end-date check is immediate and does not wait for
the scheduled PMPro expiration callback.

## State Model

```text
Started checkout
  -> payment failed/cancelled: no Active membership
  -> payment confirmed: Active

Active
  -> annual end date reached: Expired
  -> member/admin cancellation: Cancelled
  -> approved renewal payment: Active with new end date
  -> full refund of current/latest order: Refunded; matching level and renewal subscription cancelled
  -> refund of an older order with a later successful renewal: newer entitlement remains Active
```

PMPro owns the order and membership state. CYWater profile metadata does not
activate a membership and Stripe metadata does not become a second member
database.

## Institutional Partnership Workflow

Partner is an organization relationship, not individual membership. The public
membership page presents the `$1,000/year` contribution under **Sponsors and
partners**, outside the membership-card grid. **Become Our Partner** opens
`/become-a-partner/`, where an organization submits an expression of interest
without creating an account, invoice, partnership, or payment obligation.

The WordPress Administrator reviews **Partner applications** using these stages:

1. Submitted
2. Board review
3. MOU pending
4. Approved to pay
5. Declined
6. Payment received

The applicant receives a private status link. Its token is stored only as a
salted hash and rotates whenever review status or payment instructions change.
No payment action is shown before **Approved to pay**. After Board approval and
MOU completion, an administrator may add an association-controlled HTTPS Stripe
invoice or payment link. Logo/name/link recognition on the website follows the
approved MOU and must not imply product endorsement.

The historical PMPro Partner level remains in place for existing records but
public signup is disabled, direct checkout redirects to the partnership guide,
and the level is excluded from the public member directory. It must not be used
for new partner applications.

## Administrator Operations

Administrators start from **Users** in WordPress. The `CYWater record` row
action opens the user's existing profile, where the read-only CYWater section
shows account creation and last-sign-in time, email-verification state,
required-profile completion, directory privacy, active membership and expiry,
and any account-closure request. The Users table also exposes CYWater account
and membership columns and filters for verification required, closure cooling
off, and closure review due. Authorized links open the same person in
**Memberships > Members** and **Memberships > Orders**.

Operational navigation is:

- **Users**: WordPress identity, email, role, profile/privacy summary,
  verification, last sign-in, and closure request.
- **Memberships > Members**: level, membership status, start/end dates, and
  member export.
- **Memberships > Orders**: dues payments, processor references, refunds, and
  order status.
- **Memberships > Subscriptions**: renewal subscriptions and recurring state.
- **Events**: open the event and its Event Tickets attendee report for RSVP,
  capacity, and event-registration records.
- **Stripe**: payment-method and processor event details; it is not the member
  profile database.
- **Postmark Activity**: transactional-mail delivery evidence; it is not the
  account or membership database.

Event Tickets registrations may be guest registrations and therefore cannot
always be joined safely to a WordPress user by user ID. Administrators review
them from the matching event attendee report rather than relying on a fabricated
all-activity timeline.

The records remain separated by authority:

- WordPress owns login identity, email, and role.
- WordPress user metadata owns professional fields and privacy choices.
- PMPro owns membership status, expiration, orders, and refunds.
- Stripe owns payment-method and processor details.
- Postmark owns delivery activity for transactional email.

The administrator view is an index across those records, not another member
database. PMPro Members remains the membership export surface.

The official PMPro Membership Manager role is supplied by a separate Premium
Add On and is not installed on staging. Until the association provides a valid
license and installs that Add On, only administrators may operate membership
records; editors and ordinary members remain denied.

## Account Lifecycle

- Public members use `/member-register/`, `/member-login/`, and `/account/` as
  the canonical registration, sign-in, password-recovery, and account surfaces.
  WordPress still authenticates the credentials and PMPro still owns the member
  account; these routes select the public presentation rather than creating a
  second identity system.
- Front-end sign-out keeps WordPress' nonced logout action but always returns to
  `/member-login/?loggedout=true`, where the public page confirms that the
  session ended. An explicit caller-owned redirect and an administration
  sign-out remain authoritative.
- A direct signed-out `/wp-admin/` request keeps WordPress' native staff login.
  A signed-in ordinary member receives HTTP 403, while Administrators and
  explicitly assigned CYWater Operations staff retain their normal admin
  access. Membership never grants Forum or WordPress administration.
- Login, registration, account, billing, and password-reset views are excluded
  from full-page caching because their content and redirects vary by login
  state. An already signed-in member who opens `/member-login/` returns to the
  public Account page.
- Registration creates a Subscriber account that is private by default, signs
  it in, sends email verification, and returns to the selected checkout only
  after verification succeeds.
- Sign-out ends only the browser session. The Account page exposes the normal
  PMPro/WordPress sign-out action.
- Membership cancellation removes membership access and future renewal without
  deleting the WordPress identity or its accounting history.
- A full refund revokes only the entitlement funded by that order. A later
  successful order protects the newer entitlement.
- WordPress administrators may delete disposable, spam, or unpaid accounts.
  Paid-account deletion/anonymization is not automatic and must follow the
  Board-approved retention policy so required order records are preserved.
- The Account page exposes **Request account closure**. A request records a
  timestamp, starts a seven-day cooling-off period, pauses new membership
  checkout, notifies `membership@cywater.org`, confirms receipt to the member,
  and may be withdrawn before processing. Seven days are measured from the
  request, never from the member's last login. At the end of the period the
  request moves to the administrator's **Closure: review due** filter; it does
  not automatically delete the account. The administrator reviews membership,
  event, refund, and accounting records before deletion or anonymization.
- Members may sign out every other browser/device while keeping their current
  session. An administrator may revoke all sessions for another account from
  its CYWater record.
- CYWater professional/profile/privacy metadata participates in WordPress
  **Tools > Export Personal Data** and **Tools > Erase Personal Data**. The
  eraser removes CYWater profile and privacy metadata, verification tokens, and
  last-sign-in metadata. It deliberately retains the WordPress identity,
  closure request, PMPro records, refunds, memberships, and event records for
  separate review; WordPress core's erasure tool does not itself delete the
  user account.
- Verification delivery is limited to five messages per account per hour, in
  addition to the one-minute resend interval. Anonymous registration forms are
  limited to twenty valid-nonce submissions per network address per hour. The
  transient limiter stores only a salted hash of the address and expires after
  one hour.

`scripts/cywater-staging-lifecycle-qa.php` provides a staging-only, self-cleaning
test of account creation/deletion, required profile persistence, privacy
opt-in/opt-out, membership activation/cancellation, and content CRUD. Real
Stripe payment and refund acceptance remains the PMPro/Stripe boundary test in
`payment-testing.md`.

## Rolling Annual-Term Policy

Each successful Student or Professional payment purchases one full year of
membership beginning on that payment date. The price is never prorated.
Lifetime has no expiration. A later annual payment starts a new one-year term
from that later successful payment date; it does not retain the old calendar-
year boundary. Annual renewal remains manual until the Board explicitly
approves recurring billing terms. A full refund revokes only the entitlement
funded by the refunded order under the documented refund rules.
