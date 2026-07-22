# Member Workflow

## Registration

Registration asks only for account/payment details and the five fields needed
for membership statistics and regional programming:

- institution or employer
- country or region of institution
- institution type
- current title or role
- career stage

Student, Professional, Lifetime, and Partner are separate PMPro membership
levels. Photo, ORCID iD, and research interests are completed later from the
member profile.

## Privacy

Profiles are private by default. A member must opt into the public directory and
then select each field that may appear. Changing the master switch off removes
the entire listing without deleting profile data. Public output is restricted
to active PMPro members, and email is never an available public field.

## State Model

```text
Started checkout
  -> payment failed/cancelled: no Active membership
  -> payment confirmed: Active

Active
  -> annual end date reached: Expired
  -> member/admin cancellation: Cancelled
  -> approved renewal payment: Active with new end date
  -> refund: policy-dependent review, then Active or Cancelled
```

PMPro owns the order and membership state. CYWater profile metadata does not
activate a membership and Stripe metadata does not become a second member
database.

## Calendar-Year Policy

Student, Professional, and Partner levels expire on December 31. Lifetime has
no expiration. The current local setup charges the full annual amount at any
join date; proration, a late-year grace period, and refund-driven cancellation
remain Board decisions and are launch blockers. Annual renewal is manual until
the Board explicitly approves recurring billing terms.
