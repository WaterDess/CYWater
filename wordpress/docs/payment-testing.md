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
| Full refund | Order records refund; membership behavior follows the approved refund policy |
| Partial refund | Amount recorded; no automatic status change unless policy says otherwise |
| Annual expiry | Student, Professional, and Partner expire at the configured calendar-year boundary |
| Renewal | One new order and one new end date; no duplicate membership row |

Record the Stripe event ID, PMPro order ID, user ID, before/after membership
state, captured email, and result for every test. Never paste secret keys into
the evidence document.

Stripe sandbox reference: https://docs.stripe.com/sandboxes
