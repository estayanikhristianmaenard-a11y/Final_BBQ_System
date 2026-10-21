# Local baseline verification

Baseline started 2026-10-18 (Asia/Manila). This records the current application,
not completion of the planned ordering/payment redesign.

## Local application

URL: http://localhost/Final_BBQ_System/bulkbites-bbq/

XAMPP is installed at `C:\xampp`. PHP 8.2.12 has PDO MySQL, mbstring,
fileinfo, OpenSSL, and cURL available. MariaDB reports 10.4.32. Apache and MySQL
were initially stopped; both were started for verification.

The configured database did not exist. The existing guarded setup tool restored
the bundled snapshot without replacing any populated database. It contains 16
tables, six users, six products, six inventory rows, and one existing order.

Apache uses a temporary additional configuration at
`.tmp/apache-project.conf`, mapping `/Final_BBQ_System` to this checkout.
Access to this alias is restricted to local requests. The installed Apache
configuration was not edited. This is a local review setup, not an ngrok setup.
Normal Apache startup from the control panel will not automatically add this alias.

To restart the same local Apache configuration after stopping it, run:

```powershell
Start-Process -FilePath C:/xampp/apache/bin/httpd.exe `
  -ArgumentList '-c','"Include D:/Systems/Final_BBQ_System/.tmp/apache-project.conf"' `
  -WorkingDirectory C:/xampp/apache/bin -WindowStyle Hidden
```

Do not start a second Apache process while one is already serving the same ports.
The alias file is ignored local configuration; it must be recreated or replaced
with a suitable local alias on another PC.

## Backup and restoration

Executed the existing `tools/backup-database.php`, which saved
`bulkbites-bbq/database/current.sql`. The original snapshot was also copied
to ignored `.tmp/snapshot-before-baseline.sql` before setup.

Verified backup SHA-256:

```text
029def3833c9dccbfb372da5d1182a5c03371808abd9d5d23a8400006c558543
```

The new baseline suite imports that backup into a randomly named disposable
database. All 16 tables matched the source by exact row counts and extended
table data checksums. Source checksums matched again after business tests;
test-created databases were removed. A final rerun also confirmed that source
data still matched the snapshot after web checks. Keep ordering and database
edits paused while running checksum-based comparisons or taking a backup.

## Completed checks

| Check | Result |
| --- | --- |
| Existing first-party PHP files | 58 files passed syntax checks |
| New baseline PHP scripts | Both passed syntax checks |
| Backup restoration and ordering suite | 25 assertions passed; three workflow gaps recorded separately |
| Fresh demo schema import and repeatable migration | Passed |
| Existing login redirect tests | Five checks passed |
| Email OTP and receipt integration | 68 checks passed, 14 messages accepted by a local TLS SMTP fixture, no external emails sent |
| Local HTTP page/access checks | 29 passed, zero failures |
| Playwright browser checks | Homepage loads; ordering CTA opens customer login; mobile screenshots captured |
| Narrow login layout | At 320px viewport width, document width is also 320px |
| Browser console on checked pages | No errors or warnings reported |

Business checks cover checkout totals from database prices, successful stock
deduction, empty-cart replay rejection, insufficient-stock rollback, order
ownership, notifications, allowed/invalid status transitions, completion replay,
receipt queue creation, historical product/price snapshots, and report SQL
reconciliation under the current sales definition.

HTTP checks cover public/login pages, guest redirects, guest API rejection,
customer pages, customer denial from staff/manager routes, manager pages,
manager access to kitchen/staff pages, and manager denial from customer-only
menu. Temporary authenticated sessions used existing records, made no login
bypass changes to application code, and were destroyed afterward.
Configuration and saved SQL snapshot URLs both returned HTTP 403 under Apache.
This is not an exhaustive public deployment security audit.

Local screenshot artifacts (ignored by Git):

- `output/playwright/home-mobile.png` at 390px width.
- `output/playwright/login-mobile.png` at 390px width.

## Blockers and workflow gaps

1. **Real email delivery is not configured on this PC.** `config/mail.php` is
   absent. Fixture tests verify SMTP/OTP logic but do not establish real Gmail
   inbox delivery. The existing manager setup page requires email login itself,
   so first-time sender setup needs a secure bootstrap path or local mail
   configuration before a manager can sign in. Never remove role checks or
   display OTP codes to work around this.
2. **Early cancellation does not restore stock.** A NEW order deducts stock at
   checkout, and cancelling it does not return those quantities. Correct this
   in the upcoming inventory/reservation work, including duplicate-cancel
   protection and audit movements.
3. **GCash is a method label, not a verified payment.** A GCash order can currently
   be accepted without gateway confirmation. Implement separate payment records
   and enforce the planned online-payment gate.
4. **Sales are non-cancelled order totals.** This includes unfinished/unpaid
   orders. Reconciliation passes against the current SQL definition but does not
   establish collected revenue. Separate collected payments, refunds, and
   unpaid orders during payment/report implementation.
5. **Public menu browsing is not implemented.** Guests are redirected to login
   when opening the menu. Move browsing/cart ahead of OTP as planned.

No business-rule fixes or payment schema changes were made during this baseline.
These findings define the work that must precede a public ordering demonstration.

## Repeatable commands

Run from the repository root with MySQL running:

```powershell
& C:/xampp/php/php.exe bulkbites-bbq/tests/baseline.php
& C:/xampp/php/php.exe bulkbites-bbq/tests/schema-import.php
& C:/xampp/php/php.exe bulkbites-bbq/tests/staff-login-redirect.php
node bulkbites-bbq/tests/email-login.mjs C:/xampp/php/php.exe
```

With the local Apache alias running:

```powershell
& C:/xampp/php/php.exe bulkbites-bbq/tests/http-baseline.php
```

The database tests require permission to create/drop disposable databases.
The HTTP suite creates temporary local PHP sessions and assumes CLI and Apache
share XAMPP's session storage. It only requests the documented local URL.

## Remaining validation

Real inbox delivery, full browser checkout after OTP, physical phone testing,
authenticated mobile layouts, concurrent two-process checkout races, ngrok
access, PayMongo integration, refunds, and the redesigned responsive interface
are not yet verified. The current baseline does not claim production readiness.

## Email setup follow-up: 2026-10-19

The first-time sender setup loop is fixed with a local CLI bootstrap, launched
by `bulkbites-bbq/SETUP-EMAIL.bat`. It discovers XAMPP PHP, prompts for the
App Password with hidden input, and passes credentials through stdin. It needs
no manager session or database connection. A verified SMTP test must succeed
before settings are saved; failures preserve existing settings. A file lock
serializes setup, and a configured sender cannot be replaced through bootstrap.
Subsequent changes remain in the manager-only Email page.

Validation: 74 email/login/receipt integration checks passed against the local
TLS SMTP fixture (15 fixture messages, zero external emails). The HTTP suite now
passes 30 checks, including HTTP 403 for the CLI setup endpoint. PHP syntax and
PowerShell parser checks passed. The actual PowerShell launcher discovered PHP
and rejected invalid fixture input with exit code 1 before contacting Gmail.

`config/mail.php` is still absent: no real sender credentials were supplied or
saved during testing. The server owner must run the launcher locally and enter
their sender email and Google App Password, then verify a real manager/customer
login code and completed-order receipt. Stock/payment/report gaps above remain.

## Payment and stock follow-up: 2026-10-19

The three business-rule gaps recorded in the original baseline are addressed
for new tracked orders. See [PAYMENTS.md](PAYMENTS.md) for behavior, migration,
legacy handling, expiry scheduling, refunds, and the gateway integration boundary.
The local additive migration is applied; the refreshed snapshot contains 17
tables. Historical orders and stock quantities were preserved. Old unverified
payments remain excluded from confirmed revenue.

Current checks: 56 baseline/business assertions passed, including real
two-process races for last-unit checkout and late payment versus expiry;
74 local TLS email/login/receipt checks passed; 30 HTTP access/page checks
passed; fresh schema import and repeatable migration passed. Tests use isolated
databases and leave source data unchanged. First-time real inbox setup is still
pending sender credentials. No PayMongo credentials, webhook, or payment gateway
connection has been configured; customer online checkout is disabled for now.

## PayMongo adapter follow-up: 2026-10-19

Test-mode checkout creation, owner reconciliation, and the signature-verified
provider webhook adapter are implemented. Source schema and snapshot now have
18 tables. Credentials remain absent, so online checkout stays disabled. See
[PAYMONGO.md](PAYMONGO.md) for exact setup and external verification steps.

The extended fixture suite passed 95 assertions. Email/receipt checks remain
74 passing, existing HTTP checks are now 32 passing, and the restricted demo
router passed 14 checks. Tests send no external payments or email. The local
demo server runs on loopback port 8080; ngrok installation, authentication,
public exposure, merchant webhook registration, and a real sandbox payment
have not been completed.

## Full verification and visual consistency follow-up: 2026-10-21

Current suites pass: 181 business assertions with zero workflow gaps and source database checksums unchanged; 90 local TLS email/login assertions (18 accepted fixture messages, zero external email); 61 HTTP/access checks; 24 demo setup checks; 18 demo router checks; 12 login/asset redirect checks. Fresh schema/idempotent migration and a fresh backup/restore with matching row counts/checksums also passed.

The isolated browser test exercised actual OTP delivery/verification, guest-cart preservation, demo payment, receipt email, staff preparation and pickup completion. Cash pickup required recorded collection before completion. Full-flow checks: 20 passing. Reconnect/320px/QR checks: 13 passing. Final color/notification/403/HTTPS checks: 11 passing. The full screenshot pass covered 39 views at desktop/mobile widths (78 PNGs; 235 automated checks), with six additional narrow mobile captures. See UI-REVIEW.md and output/playwright/consistency/index.html.

Expiry and receipt workers ran on the disposable app. Background scheduling, physical-phone testing, live public-tunnel access and real Gmail inbox delivery remain deployment/manual checks. Source credentials were not copied into browser fixtures; no external mail or real payment was sent. Current tests do not claim that an unattended scheduler or permanent production deployment is configured.

## Cart interaction follow-up: 2026-10-21

In-place cart changes passed 33 browser checks and 65 HTTP checks. Business checks passed all 181 assertions using a fresh temporary snapshot, with source table checksums unchanged. A saved SQL snapshot that has not been refreshed since live changes will fail the suite's initial backup-equality check; this is an outdated-backup signal, not permission to replace the live database. Refresh the project backup before the demo.

The CLI baseline accepts BB_TEST_SNAPSHOT as an optional alternate snapshot path; it still restores into a disposable database and checks the running source for changes. Revenue assertions now compare the test payment/refund deltas with the initial collection amount, so existing sales do not cause false failures. Temporary SQL exports were removed after validation. No live data or stored project snapshot was changed.

### Delivery checks (21 October 2026)

Fresh-export baseline: 202 assertions passed, source data checksums unchanged.
Delivery cases cover invalid fields without stock/cart changes, free pickup,
fixed fee despite spoofed totals, receipt and email fee/address details, cash
completion guards, demo totals, exact online payment amount, refunds and hosted
checkout fee line items. HTTP baseline: 65 checks with zero failures. Fresh SQL
schema plus idempotent upgrades passed. Use `BB_TEST_SNAPSHOT` for a private fresh
export while the shared snapshot remains older than the running database.
