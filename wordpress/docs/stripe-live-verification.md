# Stripe Live Verification Packet

This checklist is for the association's eventual United States Stripe Live
account. It contains no credentials or identity numbers. Never add an EIN,
SSN/ITIN, bank number, identity document, password, API key, or recovery code
to Git, chat, screenshots, or this file.

## Preflight

- Confirm that the Stripe account country is **United States** before entering
  live onboarding data. The country follows the legal entity and payout bank,
  not the current operator's location or browser locale.
- Use the exact legal name and EIN shown on IRS/entity documents. Do not shorten
  or translate the legal name.
- Select the actual legal structure shown by the Illinois and IRS records. Do
  not claim federal tax-exempt status unless CYWater has the IRS determination.
- Keep `web@cywater.org` as the association-controlled Stripe owner/recovery
  identity and `billing@cywater.org` for billing notices. Stripe must still
  verify a named natural person as the authorized representative.
- If an existing live account was created for the wrong country, stop and ask
  Stripe Support whether a new correctly based account is required. Do not
  submit mismatched US documents to a non-US account.

## Association Information

Prepare the following exactly as shown on official records:

- full legal entity name;
- entity type and Illinois incorporation/registration details;
- EIN;
- physical US business/operating address (not a PO box or private mailbox);
- US business phone number;
- `https://cywater.org/` and proof that the association controls the domain;
- description of membership dues and any future event/registration services;
- public support email/phone, refund contact, and statement descriptor;
- expected payment volume, typical amount, currencies, and customer locations.

The repository's mailing address and organization name are reference data only.
They must be checked against the current legal documents and Stripe's physical
business-address rule before use.

## Authorized Representative

Select one person with significant management responsibility or formal
authority to act for CYWater. Stripe can request:

- full legal name, date of birth, residential address, phone, and email;
- officer/director title and relationship to the association;
- SSN/ITIN information for a US representative, or passport/national ID for a
  non-US representative;
- color government-issued photo ID and proof of residential address;
- evidence of authorization or control if Stripe cannot verify the relationship.

For a nonprofit, Stripe generally does not require 25%-owner information, but
it still requires a natural person who exercises significant control. The
representative's KYC record does not transfer Stripe ownership or payouts away
from the association.

## Documents To Have Ready

- Illinois articles/certificate of incorporation and current registration;
- IRS EIN confirmation, normally CP 575 or Letter 147C/other accepted EIN letter;
- IRS 501(c) determination letter, if one has actually been issued;
- current bylaws and Board authorization if Stripe requests relationship proof;
- proof of the physical business address;
- representative's current identity/address documents;
- association bank statement, voided check, or bank letter showing the exact
  legal entity/DBA name and account details.

Upload identity and entity documents only through Stripe Dashboard. Use the
original accepted scan/photo format; do not email documents or submit
screenshots of documents.

## Association Bank Account

- The payout account must belong to CYWater under the same legal entity name or
  registered DBA shown in Stripe.
- Prepare routing/account numbers only for direct entry in Stripe Dashboard.
- If Stripe requests ownership verification, upload a current association bank
  statement, voided/cancelled check, or bank letter.
- Do not use a representative's personal bank account, even temporarily.

## Website Readiness Before Submission

Stripe may review the website. Before requesting Live activation, the public
site should clearly show:

- the association's legal/public identity and contact channel;
- membership levels, prices, billing period, and what the member receives;
- refund/cancellation policy, privacy notice, and terms;
- secure HTTPS checkout reached from the same domain;
- no claims that unapproved paid-event registration is already available.

## Current Open Inputs

Evidence reviewed on 2026-08-03: the Illinois Certificate of Good Standing
confirms the full legal entity name, domestic-corporation status, and current
good standing. An EIN was provided in conversation but is intentionally not
stored here; an IRS EIN confirmation document has not yet been reviewed. The
Champaign address was supplied but has not yet been confirmed as Stripe's
required physical business/operating address rather than only a mailing,
registered-agent, or private-mailbox address.

- [x] Exact legal entity name checked against the Illinois certificate
- [ ] EIN confirmation document available
- [ ] Federal tax-exempt/501(c) status confirmed or explicitly marked absent
- [ ] Physical US business address accepted for Stripe purposes
- [ ] Active US business phone available
- [ ] Authorized representative selected and Board-authorized
- [ ] Association-owned payout bank account available under the matching name
- [ ] Privacy, terms, refund, cancellation, and recurring-billing text approved
- [ ] Live website/legal pages ready for Stripe review
- [ ] Stripe Dashboard account country confirmed as United States

Only after these boxes are satisfied should Live onboarding be submitted and
PMPro's Live Stripe connection be configured. Sandbox remains isolated until
then.
