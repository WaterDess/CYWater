# Payment Testing

## Scope

Use the dedicated Stripe Sandbox for ordinary development and regression
testing. A production Live transaction is permitted only for an explicitly
authorized, controlled financial-acceptance run after the application safety
gate is open. The gate rejects `sk_live_` and `pk_live_` values unless the
runtime is Production and explicitly sets both
`CYWATER_PAYMENT_MODE=live` and `CYWATER_ALLOW_LIVE_PAYMENTS=true`. CYWater does
not store card data.

PMPro owns checkout, Stripe objects, webhook verification, orders, receipts, and
membership activation. The custom plugins supply membership policy and guard the
environment; they do not fork PMPro's transaction engine.

## Configuration

Inject these values outside Git:

```text
CYWATER_PAYMENT_MODE=test
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Create the webhook through PMPro's Stripe settings and restrict it to the event
types PMPro documents for the installed version. Use an HTTPS staging URL or the
Stripe CLI when testing callbacks; localhost alone is not reachable by Stripe.

## Live Connection Readiness

On 2026-08-16 the association authorized PMPro's Stripe Live OAuth connection.
A staging-restricted, presence-only probe confirmed the Live Connect
configuration without reading or printing credentials. PMPro's production
webhook was then created through PMPro's own Stripe gateway and read back from
Stripe Live as present, enabled, current for the installed PMPro Stripe API
version, and subscribed to all ten event types required by that version. The
same read-only preflight confirmed that the Live account is reachable, charges
and payouts are enabled, identity details are submitted, and there is no
currently due account requirement.

On 2026-08-23 production was changed to `CYWATER_PAYMENT_MODE=live` with
`CYWATER_ALLOW_LIVE_PAYMENTS=true`; WordPress reports production, the Live gate
open, PMPro gateway Stripe, and saved environment Live. The current Live account
is reachable with charges and payouts enabled, details submitted, and no due
requirement. Its PMPro webhook is present, enabled, API-current, and subscribed
to all ten required events. Student `$20`, Professional `$50`, and Lifetime
`$700` checkout routes return HTTPS 200 and allow signup, while the Sandbox
fixture is absent from production. No production charge or refund has yet been
performed. Final financial acceptance remains one controlled real payment,
receipt, balance/payout and webhook reconciliation followed by a full refund.
The free PMPro Stripe integration also adds a separate 2% PMPro fee unless a
qualifying premium PMPro license is activated.

On 2026-08-23 an isolated production-only `Live Payment Acceptance Test` level
was opened at `https://cywater.org/membership-checkout/?level=5`. It charges
USD `$0.50` once, expires after one day, has no recurring amount, is stored in
its own PMPro level group, is absent from the public Membership cards, and is
excluded from `cywater_membership_level_ids`; it therefore grants no Student,
Professional, Lifetime, Forum, directory, or other CYWater member benefit. The
tracked WP-CLI helper `scripts/cywater-production-live-payment-fixture.php`
fails closed outside the production domain/runtime, reports non-secret
aggregate acceptance state, and can close new signup without deleting the
order/refund audit trail. Immediately before payment it reported zero orders
and zero active entitlements. The participant must personally enter and submit
payment details. After one successful charge, reconcile the PMPro order,
receipt, Postmark delivery, Stripe balance/payment, and required webhooks; then
obtain action-time confirmation, issue a full refund through the authoritative
PMPro/Stripe order path, verify the fixture entitlement alone is removed, and
close the fixture.

The first post-switch public check found that LiteSpeed still served the
pre-Live Membership page with disabled payment actions. WordPress and LiteSpeed
caches were purged; both the canonical and cache-busted page now show the three
standard checkout actions, keep the acceptance fixture private from the card
grid, and route a logged-out fixture request through the normal sign-in gate.

The manual acceptance fixture is the staging-only `Sandbox Payment Test` PMPro
level. It charges `$0.50` once, expires after one day, lives in a separate level
group, and is excluded from every Student/Professional/Lifetime benefit and
directory allowlist. `$0.50` is Stripe's minimum USD charge; do not reduce it to
`$0.10`. Complete it only with Stripe test payment data. After success, verify
the PMPro order and `checkout.session.completed` webhook before issuing a full
Sandbox refund and confirming that only the fixture entitlement is removed.

On 2026-08-20 that complete manual path passed. The `$0.50` order reached
`success`, stored a Stripe Checkout Session and payment reference, received
`checkout.session.completed`, and created no subscription. The fixture remained
independent of the account's Student membership. After explicit approval, the
full Sandbox refund changed the order to `refunded`; `charge.refunded` was
received, the CYWater full-refund hook removed only the fixture entitlement, and
Student remained active.

The Live connection and production webhook were verified with one-use,
staging-restricted administrative probes. Those temporary helpers are not part
of the repository. They did not change the saved checkout environment or print
account identifiers, keys, webhook identifiers, or webhook secrets.

## Acceptance Matrix

| Scenario | Expected result |
| --- | --- |
| Successful card payment | One successful order; membership becomes Active; confirmation and receipt captured in Mailpit |
| Declined card | Failed order retained for audit; membership is not Active |
| 3DS/authentication | Checkout resumes after authentication; only confirmed payment activates membership |
| Checkout cancelled | No paid order and no Active membership |
| Duplicate submit | At most one successful order and one membership transition |
| Duplicate webhook | Re-delivery does not create another order or extend membership twice |
| Invalid webhook signature | HTTP rejection; no order or membership mutation |
| Full refund | Order becomes Refunded; cancel only its membership level and active renewal subscription unless a later successful order funds the same entitlement |
| Partial refund | Amount recorded; no automatic entitlement cancellation; administrator review required |
| Annual expiry | Student and Professional expire one full year after their successful payment date; Lifetime does not expire |
| Renewal | One new order and a new end date one year after the renewal payment date; no duplicate membership row |

Record the Stripe event ID, PMPro order ID, user ID, before/after membership
state, captured email, and result for every test. Never paste secret keys into
the evidence document.

## Receipts And Invoices

PMPro owns the current membership-order document. A signed-in member opens an
order from **Account > Orders** and views the protected
`/membership-order/` page backed by one `[pmpro_invoice]` shortcode. This page
and the corresponding PMPro email are a membership order receipt. They are not
automatically a jurisdiction-specific tax invoice, and CYWater must not promise
tax deductibility before its legal/tax status and receipt wording are approved.

Stripe Sandbox can also create test Billing invoices, but test invoices do not
move real funds and are not valid production documents. The current PMPro
Stripe Checkout integration does not automatically create a separate Stripe
Billing Invoice for every membership order. Do not add a parallel invoice
engine merely to duplicate PMPro orders. If the association later requires
Stripe Invoicing, first approve invoice numbering, issuer/legal name, address,
tax wording, currency, payment terms, refunds/credit notes, and whether an
invoice represents membership dues or a separate conference registration.

On 2026-08-09 the staging invoice acceptance verified that the protected PMPro
order page is published, contains exactly one invoice shortcode, resolves
through PMPro, remains in Sandbox mode, and has completed/refunded Sandbox order
evidence available for receipt rendering. The check created no order and
printed no member or payment identifier.

Official references:

- https://www.paidmembershipspro.com/documentation/frontend-pages/membership-invoice/
- https://docs.stripe.com/get-started/use-cases/invoices
- https://docs.stripe.com/invoicing/integration/testing

Stripe sandbox reference: https://docs.stripe.com/sandboxes
