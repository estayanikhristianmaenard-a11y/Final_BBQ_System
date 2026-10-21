# Professor presentation checklist

Use separate browser sessions/devices for customer, staff, and manager roles.
Select small quantities from available stock and keep a record of presentation
orders. Automated tests use disposable databases; the manual steps below create
actual records in whichever database the running application uses.

## Before the presentation

- [ ] In Manager > Store, confirm pickup location/contact, daily hours or manual
      ordering status, preparation estimate, and order limits.
      The configured daily schedule is 10:00 AM–10:00 PM, Philippine time.

- [ ] Run `SETUP-DEMO.bat`, or the individual launchers in [DEMO-SETUP.md](DEMO-SETUP.md).
- [ ] Send the email connection test and confirm it arrives in a real inbox.
- [ ] Complete a customer email OTP login; repeat for an authorized staff account.
- [ ] Open Manager > Ordering QR and download or print the current QR.
- [ ] Scan it from a phone using mobile data. Pass any ngrok browser introduction,
      then confirm that the menu loads and its buttons work.
- [ ] Choose Demo payment and try success, failure, and cancellation. No gateway
      account or documents are needed; present it as a simulated workflow.
- [ ] Keep the server PC awake and connected; do not close the tunnel or stop MySQL.

## Customer and cash pickup story

1. Scan the menu QR, read an item description, add food, change a quantity, and
   check the total on a phone. The menu deliberately stays simple for the small catalog.
2. Go to checkout, sign in by email OTP, and confirm the cart survives verification.
3. Enter the pickup details and choose cash. Submit once and show the order reference
   and status. Confirm quantities/total match the receipt and stock reservation.
   Verify a second active order is blocked. On a separate new order, test customer
   cancellation before acceptance and confirm stock is returned once.
4. As staff, accept the order, move it to Preparing, then Ready. Show the customer's
   updated status and grouped notification.
   Confirm automatic updates within about 15 seconds, and demonstrate revising a
   preparation estimate from Orders > Pickup estimate.
5. Attempt completion before collecting cash: it must be blocked. Collect cash,
   record payment as staff, then complete pickup.
6. Show the receipt, receipt-email delivery status, and the manager's collected-sales
   report. Explain any email delivery failure using its actual status.

## Demo payment story

1. Choose **Demo payment** at checkout. Show the **no real money** notice and
   saved total. Staff must be unable to accept the unpaid order.
2. Select **Simulate failed payment**. Show that it stays unpaid, then continue
   demo payment and select **Cancel demo payment**. The order remains unpaid.
3. Retry and select **Simulate successful payment**. Confirm the DEMO label and
   staff acceptance. Repeated success must not duplicate the payment.
   Check the customer's sign-in inbox for the demo receipt immediately after
   payment; it must not say pickup is completed yet.
4. Complete preparation and pickup. Show the receipt's **DEMO PAYMENT** label and
   verify demo totals remain separate from real collected sales.
   Confirm pickup completion does not send a second copy of an already delivered receipt.
5. On another order, demonstrate expiry or customer cancellation and stock release.

The isolated automated suite covers repeated webhooks, concurrent checkout,
overselling prevention, late payment/refund requirements, and payment mismatch
rejection. External webhook redelivery and delayed provider confirmations require
additional provider testing; do not describe local fixtures as live-provider proof.

## Manager and responsive UI

- [ ] Review products, paused selling, physical stock, low-stock indicators, and reports.
- [ ] Check customer, staff, and manager views on desktop and phone widths.
- [ ] Open the mobile Pages menu, follow a link, and check all action buttons remain usable.
- [ ] Confirm customers cannot access operational pages or another customer's order.
- [ ] Check that public requests cannot retrieve config files, SQL backups, or setup tools.

## Evidence to record

Record the date, phone/network, order references, inbox result, simulator outcomes,
receipt status, and report totals. Leave account
secrets out of screenshots. Mark unperformed steps as pending.

As of 2026-10-20, local workflow/security fixtures and public HTTPS access pass.
Check Gmail OTP delivery and scan from a physical phone before presenting.
An external gateway connection is not required for the chosen simulation demo.

## Simplified presentation screens

Use the normal Menu → Cart → Checkout → Payment → Order status flow and the staff Kitchen. Manager Overview and Sales report show essential totals by default. Store hours and the ordering QR stay easy to reach. Expand Advanced settings/reports only if the professor asks about account management, customer analytics, detailed accounting or setup. Product creation, uploads, stock thresholds and order limits remain available in collapsed sections. Demo payment still offers success, failure and cancellation for questions during the defense.

The presentation cleanup preserves all records and safeguards. Updated desktop/mobile previews are in output/playwright/review-audit/index.html; the full validation notes are in UI-REVIEW.md.

## Reliability rehearsal (21 October 2026)

Automated local rehearsal passed 35 browser checks using independent customer
and manager sessions: refresh/HTTP503 checkout draft recovery, offline submission
protection, duplicate-submit blocking, pending button labels, failed product-save
recovery, status reconnection, cash pickup completion and demo-paid delivery
completion. Delivery receipts include the fixed PHP20 fee and address; simulated
payments remain labelled. No real payment or external email was sent.

The disposable-database suite passed 202 assertions. The local TLS SMTP fixture
passed 90 email checks, including receipt delivery, failure/retry and duplicate
prevention. This verifies application behavior, not actual Gmail inbox delivery.

Before presenting, perform the physical-device check:

1. Start the server/public demo link and scan the current QR on a phone.
2. Use a customer phone and a separate manager session. Place one pickup order,
   advance its status, confirm cash only after receiving it and complete pickup.
3. Place a Pasig delivery with the PHP20 fee, address/contact and Demo payment.
   Simulate success, advance the order and confirm delivered.
4. Check the configured Gmail recipient inbox for the receipt, including Spam.
5. Briefly disconnect the customer phone; verify the connection notice and
   automatic status recovery after reconnecting.

