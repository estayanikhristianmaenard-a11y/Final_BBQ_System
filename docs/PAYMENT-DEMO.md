# Payment simulator for the professor demonstration

The default checkout offers **Cash** and **Demo payment**. The demo option is a
local simulation, not an integration with GCash, a bank, or another gateway.
It requires no payment account, documents, API keys, real card details, or money.

1. Customer selects Demo payment and places a pickup order.
2. The demo checkout shows the saved order total and three outcomes.
3. **Simulate successful payment** records one simulated payment. Staff may then
   accept the order, prepare it, mark it Ready, and complete pickup. A demo
   receipt is emailed after payment confirmation, before preparation begins.
4. **Simulate failed payment** leaves the order unpaid and lets the customer retry.
5. **Cancel demo payment** closes that payment attempt and leaves the order
   unpaid. The customer can retry or cancel the order separately.

Failed/cancelled attempts do not restart the reservation deadline. Expired orders
release reserved stock and cannot be paid through the simulator. Customer order
cancellation keeps the existing stock-release and refund-required rules.

Every demo order uses the separate `DEMO` payment method and is flagged as test
data before payment. Simulator actions cannot confirm cash or PayMongo orders.
Successful requests use the stored total, never an amount from the browser.
Requests require customer ownership, POST, CSRF validation, and throttling.
Repeated or simultaneous success requests create exactly one payment event.

Order screens and receipts identify **DEMO PAYMENT — no real money**. Receipt
emails use **Demo total**. Demo events are excluded from real collections and
pending real-money cashflow. They remain visible in order counts and demo-payment
totals so the presentation can show what occurred.

## Setup and configuration

The simulator is enabled by default for this demonstration project. The local
`config/database.php` can explicitly set `app.demo_payments_enabled` to `false`
to disable it. The committed database configuration example documents this option.
No PayMongo setup is required. `SETUP-DEMO.bat` now guides only email and public
access setup; Manager > Ordering QR reports simulator readiness.

The existing `UPGRADE-PAYMENTS.bat` adds `DEMO` to the orders payment-method enum
without changing existing orders. Fresh setup and the current database snapshot
include the upgrade. A pre-upgrade snapshot is retained locally under
`.tmp/pre-demo-payment-upgrade.sql`.

The PayMongo adapter and its isolated tests remain available for optional future
provider integration and previously created provider orders. They are separate
from this simulator. When presenting, describe the feature as a **simulated
payment workflow**, not a verified third-party gateway connection.

## Verification

The isolated baseline covers simulator success/failure/cancellation, ownership,
disabled mode, cash/provider separation, expiration, receipts, real-sales
separation, duplicate success, and concurrent success-versus-cancellation.
No external payments or real customer orders are needed for these tests.

Verified: 160 baseline assertions, 78 local TLS email checks, 53 HTTP checks,
18 restricted-router checks, and 24 setup checks passed. The browser flow covers
all three payment outcomes through staff acceptance, preparation, pickup, and a
demo receipt. Screenshots at 1440px, 390px, and 320px are saved locally under
`output/playwright/payment-simulator/`.
