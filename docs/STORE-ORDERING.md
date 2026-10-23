# Simple pickup-ordering controls

Implemented 2026-10-20. The website remains a BBQ pickup system. No delivery,
rider accounts, or GPS service is required.

## Manager setup

Open **Manager > Store** to set:

- **Open, Paused, or Closed** for new orders. Existing orders can still be prepared
  and collected when new ordering is paused or closed.
- An optional daily opening/closing schedule in Philippine time. Overnight hours
  work across midnight. Closing time itself is outside the ordering window.
  Manual Paused/Closed overrides the schedule. Daily hours are enabled at
  **10:00 AM–10:00 PM**, as specified by the shop, and shown on the menu/footer.
- Pickup location and optional contact instructions displayed to customers.
- Default preparation estimate: **20 minutes after acceptance**, editable for the shop.
- Active-order limit: **1 per customer**, reorder cooldown: **60 seconds**,
  quantity caps: **20 of each item, 50 pieces total**. Managers can adjust them.

Open is the initial ordering setting, subject to the daily hours. The preparation estimate
is a configurable starting value, not a verified business promise. Enter actual
pickup contact details; no phone number is invented by setup.

## Staff Open/Close buttons

Staff dashboard and Kitchen Display both show **Open ordering** and **Close
ordering**. These buttons change only the new-ordering status and record the
staff account in `updated_by`. Staff cannot edit hours, quantities, or other
manager settings through this action. Deactivated staff and customers are denied;
requests require POST and a valid CSRF token.

Closing stops new checkout; the menu, existing order tracking, preparation, and
pickup stay available. Closed remains set until staff or a manager reopens it.
Staff Open follows the daily 10 AM-10 PM schedule. Managers can use the same
button to open outside operating hours for testing. Manager Close also takes
effect during business hours. The dashboard labels this as a manual override;
it remains until another Open/Close action, **Use automatic hours**, or a Store
settings save. **Use automatic hours** resumes the configured daily schedule.
Configured hours and order limits are preserved. Saving Store settings clears
the manual override. Existing orders remain available while ordering is closed.

The override is stored in the existing settings table; no schema upgrade is needed.


Verified staff controls with 170 isolated baseline assertions and 57 HTTP access
checks. A disposable browser app confirmed customer menu closure/reopening and
both dashboard/kitchen actions at desktop, 390px, and 320px widths. Screenshots
are retained locally in `output/playwright/staff-ordering/`.

## Customer flow

Checkout requires a pickup name and accepts an optional contact number. Details
are checked on the server. The order snapshots the name, contact, and pickup
location; changing the shop location later does not change a new order's saved
pickup address. Existing orders retain their historical records.

The checkout transaction locks the account before counting active orders and
checking the latest submission. Separate devices/sessions cannot race past the
active-order cap. Stock is locked afterward, and validation failures preserve the
cart and make no partial order or stock change. Limits apply to both cash and
online orders. Cancelled orders still count toward the submission cooldown.
Account limits reduce abuse; multiple accounts are still possible, so staff
review and payment rules remain necessary.

After acceptance, the customer sees an estimated ready time. Staff can change it
from **Orders > Pickup estimate**, entering minutes from now. Changes are recorded
in the order history and customer notifications. An overdue estimate does not
mark food Ready; the customer is told to wait for the shop's Ready update.

The order detail page checks an owner-only status endpoint every 15 seconds.
It reloads when status, payment, estimate, or history changes, while avoiding
automatic reload during a focused form action. Hidden tabs pause checks;
network failures retry and show guidance. **Refresh status** works manually,
including without JavaScript. Polling does not create a payment or trust a
provider return URL as proof of payment.

## Cancellation and payment

Customers can cancel their own **New** order before staff accepts it. A row lock
resolves cancellation racing with acceptance: only the first permitted action
succeeds. Reserved stock is released exactly once and the action is audited.

Paid cancellation becomes **Refund required**. The shop must arrange the refund
and record its confirmation through the existing manager flow; clicking Cancel
does not send money. A later provider payment for an already cancelled order
continues through the existing late-payment/refund rules. After acceptance,
customers contact staff rather than cancelling through the website.

## Installation and verification

Payment receipts now email after confirmed payment (cash when staff records
collection), without waiting for pickup completion. Successful receipt delivery
is not repeated at completion. The latest verification includes 161 baseline
assertions and 90 local TLS email checks, with no external test emails sent.

The existing `UPGRADE-PAYMENTS.bat` includes this additive upgrade. It adds the
singleton `store_settings` table and pickup/estimate columns without replacing
orders or changing existing stock. Fresh database setup also applies it. The
transferable snapshot now includes the upgraded schema; a pre-upgrade snapshot
is retained locally under `.tmp/pre-store-ordering-upgrade.sql`.

Run the existing baseline, schema-import, HTTP, and email tests with XAMPP PHP.
The baseline includes dedicated store cases for hours, invalid settings,
authorization, limits, pickup validation, estimates, cancellation, refunds,
cross-session checkout races, and cancellation-versus-acceptance races. Test
orders are created only in disposable databases.

Verification completed: 139 baseline assertions, 76 local TLS email checks,
50 HTTP checks, 18 restricted-router checks, and 24 setup checks passed. Fresh
schema import and repeated migration passed. A real browser against an isolated
app/database verified customer cancellation, owner-only polling, automatic
acceptance/estimate updates, and manager store closure. Checkout/order/store
screens were reviewed at desktop and phone sizes, including the store form at
320px. Local screenshots are in `output/playwright/pickup-essentials/`.

Gmail setup is described in [EMAIL-SETUP.md](EMAIL-SETUP.md). Payments now use the
[local simulator](PAYMENT-DEMO.md), without a PayMongo account or external checkout.
