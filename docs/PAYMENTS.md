# Payment and inventory rules

Implemented 2026-10-19. Payment tracking and reservation rules are in place.
The PayMongo test adapter is now implemented; see [PAYMONGO.md](PAYMONGO.md) for
account configuration and pending external sandbox verification. Real online
payments remain disabled.

## Current behavior

- Cash: submit -> awaiting acceptance -> accepted -> preparing -> ready -> staff
  confirms cash received -> completed. Staff cannot complete an unpaid pickup.
- Online: submit -> pending payment -> trusted backend payment confirmation ->
  awaiting acceptance -> accepted -> preparing -> ready -> completed.
- Online acceptance and preparation are blocked until payment is recorded.
- The kitchen and order queue show payment status separately from preparation.
  Staff receive only actions currently allowed by the server, including after
  polling refreshes. Server-side checks also reject forged status requests.
- GCash checkout is disabled until the gateway is connected. Customers see that
  online checkout is coming next and can choose cash. There is no public route
  that lets a customer or staff member mark an online order paid.

## Stock reservations

Inventory `quantity_available` means available to promise. Checkout deducts
reserved units atomically, so other customers cannot purchase them. It stores
the reservation state and deadline alongside the order.

Default deadlines: 10 minutes for unpaid online orders and 15 minutes for
unaccepted cash orders. Configure `app.online_reservation_minutes` and
`app.cash_reservation_minutes` in local database configuration if needed.
These are initial defaults; confirm suitable durations with shop staff.

Acceptance removes the deadline. Preparing changes the reservation to
committed stock. Unpaid NEW orders that pass their deadline become CANCELLED
with payment status EXPIRED and return reserved units once. Early cancellation
also returns a tracked reservation once, with an inventory adjustment and order
history. Cancellation after cooking begins records WASTED stock and does not
silently return cooked food to availability.

The app sweeps due reservations during authorized page requests, staff polling,
and before checkout. With no traffic, processing waits until the next sweep.
For timely unattended processing, schedule this CLI command every minute using
Windows Task Scheduler (or cron on a hosted server):

```powershell
& C:/xampp/php/php.exe bulkbites-bbq/tools/expire-reservations.php
```

Use an absolute script path and appropriate working directory in the scheduler.
No operating-system scheduled task has been installed as part of this change.

## Payment integrity and refunds

Cash collection is an explicit staff/manager action for READY cash orders. A
unique payment event prevents duplicate collections on retries. It records the
staff member, amount, reference, and time. Confirm actual receipt of the full
amount before using this action.

`record_verified_online_payment()` is an internal integration boundary. The
future gateway adapter must verify provider signatures, environment/mode, and
order identity before invoking it. The function validates the amount/currency,
deduplicates provider references, and keeps test payments separate. No provider
webhook implementation was included in the initial payment-rules phase; the
subsequent test adapter now provides signed-webhook verification and provider
retrieval before calling this boundary. See the PayMongo guide for details.

If payment arrives after expiry or cancellation, record the payment as
REFUND_REQUIRED without reviving the order or taking released stock. A paid
order cancelled by the shop also becomes REFUND_REQUIRED. Different additional
payments for an already paid order require separate reconciliation; they are
not automatically applied.

Managers can open the staff order queue (including cancelled orders), enter a
completed refund reference, and record a full refund. This records a refund
already performed outside the website; it does not send money. Partial refunds
and automated provider refunds are not implemented.

## Reports and historical records

Net collections equal confirmed payment events minus recorded refund events,
using the event date. Money awaiting refund remains collected until a refund
is actually recorded. Reports show gross collected payments, recorded refunds,
unpaid orders, pending refunds, historical unverified amounts, and demo payments.
Order counts use order placement dates; cashflow uses payment/refund dates.
Demo payments and their refunds are excluded from real collections. Test
receipts are explicitly labeled. An unpaid order record is not proof of payment.

Existing orders and receipts are preserved. Historical payment and stock states
are labeled LEGACY_UNVERIFIED and LEGACY; migration does not invent payment
events, change quantities, or infer that old completed orders were paid.
Untracked legacy stock is not automatically returned during cancellation,
because its original deduction cannot be safely assumed. Review such orders
manually. Historical READY cash orders can have actual collection recorded;
historical completed payments remain unverified unless separately reconciled.

## Installation and transfer

The current local database has been upgraded and the bundled snapshot refreshed.
The previous snapshot is retained locally at `.tmp/pre-payment-migration.sql`.
The latest snapshot has 18 tables, including PayMongo checkouts. New database setup applies this migration
automatically; the demo SQL schema also includes the payment structure.

For an existing database on another PC, back it up, then run
`bulkbites-bbq/UPGRADE-PAYMENTS.bat`. The migration adds fields and a payment
event table, preserves existing records, and can be repeated. CLI alternative:

```powershell
& C:/xampp/php/php.exe bulkbites-bbq/tools/migrate-payments.php
```

Do not restore an old-schema database while serving the upgraded code. To roll
back, stop ordering and restore a verified matching code/database backup. The
old snapshot is not a safe rollback once new payments or orders have been made.

## Verification

The isolated baseline suite covers migration repeatability, stock return and
duplicate cancellation, payment-gated acceptance, cash collection, duplicate
payments, wrong amount/currency, late payments, refunds, and demo exclusion.
It also runs two real PHP processes concurrently against the last stock unit
and races expiry against a late payment. Source data checksums remain unchanged
by these tests. Run:

```powershell
& C:/xampp/php/php.exe bulkbites-bbq/tests/baseline.php
& C:/xampp/php/php.exe bulkbites-bbq/tests/schema-import.php
node bulkbites-bbq/tests/email-login.mjs C:/xampp/php/php.exe
& C:/xampp/php/php.exe bulkbites-bbq/tests/http-baseline.php
```

Validation results for this phase: 56 business assertions, 30 HTTP checks,
fresh schema import, 67 PHP syntax checks, and the root-deployment URL regression
check passed. Browser verification used a disposable database: the unpaid online
card offered no acceptance action; confirming cash changed the ready order to
Paid and enabled Complete; completing pickup produced a 20-peso collection in
the manager report. Mobile kitchen screenshot:
`output/playwright/payment-kitchen-mobile.png` (local ignored artifact).
The temporary browser server, database, and authenticated session were removed.
