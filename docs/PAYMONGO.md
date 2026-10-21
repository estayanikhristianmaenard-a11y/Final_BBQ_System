# PayMongo test checkout

**Optional integration:** the project now uses the built-in
[payment simulator](PAYMENT-DEMO.md) for the professor demonstration. The steps
below are only for connecting an actual PayMongo TEST account later.

Implemented 2026-10-19 using Hosted Checkout creation at
`POST /v2/checkout_sessions` and retrieval at
`GET /v1/checkout_sessions/{id}`. This integration rejects live API keys and
live-mode events. No real account credentials or external sandbox transaction
were used during development; provider requests were tested with local fixtures.

The restricted local demo server uses `http://127.0.0.1:8080/`. The public
launcher starts or reuses it safely; do not start another server on the same port.
See [DEMO-SETUP.md](DEMO-SETUP.md) for the guided wizard and ordering QR.

## Connect your account locally

1. Create/sign in to your PayMongo account and check access to Hosted Checkout.
   The current official quick start lists completed KYC as a prerequisite.
   Find the **secret TEST API key** (`sk_test_...`) in the developer settings.
2. Double-click `bulkbites-bbq/START-PUBLIC-DEMO.bat`. It starts/reuses MySQL,
   the restricted application server, and an authenticated ngrok tunnel. It saves
   the public HTTPS base URL locally. Keep the PC and these processes running.
3. Double-click `bulkbites-bbq/SETUP-PAYMONGO.bat` and enter the secret TEST
   API key in the hidden local prompt. It registers or reuses the matching
   **checkout_session.payment.paid** TEST webhook and saves its signing secret
   and URL atomically. It creates no payments and rejects live keys.
4. Confirm successful setup in the local window and in Manager > Ordering QR.
   If account access or webhook creation fails, check the PayMongo dashboard and
   rerun setup. Configuration remains unchanged after an incomplete response.
5. If using a different host, save its actual HTTPS app base address in Manager >
   Ordering QR before setup. Include the app subfolder if applicable. Credentials
   are saved in ignored `config/paymongo.php`, which is blocked from web access.
   Whenever the public address changes, rerun payment setup and download a new QR.
6. Configure SMTP if not already done, sign in as a customer, select GCash/card
   TEST payment, and submit an order. The server creates a checkout session using
   saved item prices, then redirects to PayMongo. Use PayMongo's e-wallet test
   authorization page or documented test card details, not real card credentials.
7. Test success and failure. Returning to BulkBites does not mark payment paid.
   The signed webhook triggers server-side retrieval and verification. The owner
   can also press **Check payment status** to perform authenticated retrieval.
   Once verified, staff can accept the order. Check that it is labeled TEST and
   excluded from real collected revenue.

For an existing installation on another PC, back up and run
`UPGRADE-PAYMENTS.bat` again after pulling; it now adds `paymongo_checkouts`.
Fresh setup includes the table. The current local database is already upgraded,
and its refreshed snapshot contains 19 tables, including store settings. The prior snapshot is retained
locally at `.tmp/pre-paymongo-migration.sql`.

## What is implemented

- GCash and card test checkout, amount/currency from server price snapshots.
- A durable local creation attempt and one reusable session per order. Repeated
  taps do not create a second payable checkout. An ambiguous creation timeout
  blocks blind recreation and can be recovered by a signed webhook.
- HTTPS redirects restricted to `checkout.paymongo.com`.
- POST-only provider endpoint with HMAC SHA-256 verification over raw bytes,
  constant-time signature comparison, test signature selection, and a five-minute
  timestamp tolerance. It deliberately uses no customer session or CSRF token.
- Server-to-server confirmation checks test mode, persisted session/order
  reference, succeeded intent, paid payment, amount, and PHP currency before
  invoking the transactional payment ledger. Repeated delivery is idempotent.
- Pending, failure, abandoned checkout, delayed webhook, and late-payment flows.
  If a payment arrives after local stock expiry, it requires a refund rather
  than silently taking released stock or accepting an unfulfillable order.
- Owner-only, CSRF-protected checkout retry/payment checking with throttling.
- No live payments, automated refunds, or scanning QR Ph payment codes. QR Ph is
  excluded because PayMongo warns that paying a generated test QR can move money.

## Recovery and limits

If creation is ambiguous, do not delete the attempt or create replacement
payments blindly. Check the provider dashboard for the unique local reference.
If a session ID is available, the server owner can reconcile it safely:

```powershell
& C:/xampp/php/php.exe bulkbites-bbq/tools/reconcile-paymongo.php cs_SESSION_ID
```

Only a provider session matching the saved reference can change a local order.
Unknown sessions on a shared merchant account are acknowledged and ignored.
Transient verification failures return 503 so PayMongo can retry. Bad signatures
return 400; non-POST webhook requests return 405. The endpoint responds 503 while
credentials are absent. Raw payment payloads and credentials are not logged.

Local reservation expiry does not call PayMongo's Expire endpoint. The provider
session may remain active afterward, so late confirmations follow refund handling.
A cancelled browser return also does not cancel or mark the order paid. A public
ngrok URL is required for real webhook delivery; an offline PC cannot receive it.
Keep the server clock correct. Test method availability depends on the account.

## Verification

Run `tests/baseline.php` using XAMPP PHP to exercise the isolated database and
PayMongo fixtures. Tests cover valid/tampered/stale signatures, mode and amount
mismatches, duplicate events, session reuse, unknown creation recovery, malicious
redirect hosts, and cancellation/expiry races. These tests do not establish a
successful external PayMongo account connection. Real sandbox checkout, ngrok
delivery, and enabled account payment methods remain to be verified with your
locally entered credentials.

Verification on 2026-10-20: 102 business/gateway fixture assertions, 76 email and
receipt integration checks, 41 app HTTP checks, 18 restricted demo-router checks,
and 24 demo/payment setup checks passed. Public HTTPS access was verified.
No credentials or webhook have been registered with an external merchant account.

## Official references

- [Hosted Checkout quick start](https://docs.paymongo.com/docs/payment-channels-hosted-checkout-quick-start)
- [Checkout session retrieval](https://docs.paymongo.com/reference/get_checkout_sessions)
- [Webhook signature verification](https://docs.paymongo.com/docs/developer-tools-webhook-setup-management)
- [Webhook event structure](https://docs.paymongo.com/docs/developer-tools-webhooks-events)
- [Payment testing](https://docs.paymongo.com/docs/payment-acceptance-testing)
