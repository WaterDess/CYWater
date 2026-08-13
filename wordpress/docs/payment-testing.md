# Payment Testing

## Scope

Use a dedicated Stripe Sandbox and sandbox API keys only. The application safety
gate rejects `sk_live_` and `pk_live_` values unless the runtime is Production
and explicitly sets both `CYWATER_PAYMENT_MODE=live` and
`CYWATER_ALLOW_LIVE_PAYMENTS=true`. CYWater does not store card data.

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
