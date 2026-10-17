# Payment Sandbox

![CI](https://github.com/sarahhghazali/payment-sandbox/actions/workflows/ci.yml/badge.svg)

A Laravel sandbox for the part of a payment integration that actually goes
wrong: **the callback**. It contains a merchant application and a fake payment
gateway, so every awkward case — a forged callback, a replay, a duplicate, a
late failure — can be triggered on purpose and verified by tests.

![An order marked paid, with the callback audit trail below it](docs/order.png)

## Why this exists

The happy path of a payment integration is easy. The money is lost in the edge
cases:

| Case | What happens without protection | Guard here |
|---|---|---|
| Forged callback | Anyone who knows an order reference can mark it paid | HMAC-SHA256 signature, compared with `hash_equals` |
| Tampered amount | Customer pays 1.00 for a 100.00 order | Signature covers the amount; it is also compared to the order |
| Replayed callback | An old captured callback pays a new order | Signed timestamp, rejected after 5 minutes |
| Duplicate delivery | Gateways retry; the order is paid twice | Unique `event_id` + row lock, applied exactly once |
| Out-of-order delivery | A late "failed" unpays a successful order | Paid and failed are final states |
| Wrong currency | 100 IDR settles a 100 MYR order | Currency compared before applying |

Every one of those is covered by a test, so the protections cannot quietly
disappear in a refactor.

## How it works

```
Merchant                        Sandbox gateway
--------                        ---------------
POST /orders
  creates order (pending)  ──▶  creates payment session
  redirect to hosted page  ──▶  GET /gateway/pay/{ref}
                                   customer picks an outcome
                                   queued job signs + POSTs the callback
POST /callbacks/payment   ◀──
  verify signature
  check freshness, amount, currency
  insert payment_event (unique event_id)
  apply once, then 200  ──────▶  delivery acknowledged, retries stop
```

The merchant answers **200 for duplicates too**. A gateway retries anything
that is not 2xx, so answering 4xx to a callback you already handled means hours
of pointless retries.

Amounts are integer cents everywhere. `0.1 + 0.2 !== 0.3` in binary floating
point, and money must be exact.

## Running it

With Docker (nginx + php-fpm + MySQL + Redis + a queue worker):

```bash
make up                      # http://localhost:8000
make fresh                   # demo data
```

Or locally with SQLite:

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve            # terminal 1
php artisan queue:work       # terminal 2 — callbacks are delivered by the queue
```

Then open <http://localhost:8000>, create an order, and choose an outcome on
the gateway page. Tick **send the callback three times** to watch idempotency
work: three deliveries, three `200`s, one payment event.

![The merchant checkout page listing recent orders](docs/checkout.png)

## Testing the attacks yourself

```bash
# Forged callback — no signature
curl -X POST http://localhost:8000/callbacks/payment \
  -H 'Content-Type: application/json' \
  -d '{"order_reference":"ORD-...","amount_cents":4990,"currency":"MYR","status":"success"}'
# → 401 {"outcome":"invalid_signature"}  and the order stays pending
```

The response always names the decision: `applied`, `duplicate`,
`order_already_final`, `invalid_signature`, `expired`, `unknown_order`,
`amount_mismatch`, `currency_mismatch`. Every callback is stored in
`payment_events`, rejected ones included — that audit trail is what answers
"why was my order not marked paid?".

## Layout

```
app/
├── Payments/
│   ├── Signature.php             HMAC signing, canonical form, constant-time compare
│   ├── ApplyPaymentCallback.php  all the rules, in one testable place
│   ├── GatewaySandbox.php        the fake gateway: sessions and signed callbacks
│   ├── OrderStatus.php           pending / paid / failed, with final-state logic
│   └── CallbackOutcome.php       every decision, with its HTTP status
├── Jobs/DeliverCallback.php      delivery with retries and backoff (10s → 10m)
├── Http/Controllers/             thin controllers
└── Models/                       Order, PaymentEvent, GatewayPayment, CallbackDelivery
tests/
├── Unit/SignatureTest.php        canonical form, tampering, bad secrets
└── Feature/
    ├── PaymentCallbackTest.php   the attack and edge cases above
    └── CheckoutFlowTest.php      end-to-end, including retry on a 4xx
```

## Development

```bash
make check     # pint + phpstan (level 6) + tests
php artisan test
```

CI runs formatting, static analysis and tests on every push, then builds the
Docker image.

## Notes and limits

- The gateway lives inside the same application so the project runs with one
  command. In a real integration it is a separate service and the merchant
  calls it over HTTPS; the callback here is a genuine HTTP request either way.
- The shared secret is a config value. In production it belongs in a secret
  manager, per merchant, and rotated.
- There is no authentication on the merchant pages: it is a sandbox, not a shop.

## Ideas for next steps

- Refunds and partial captures, which have their own idempotency problems
- A reconciliation command comparing a settlement file against orders
- Replay a stored callback from the UI, for support teams
