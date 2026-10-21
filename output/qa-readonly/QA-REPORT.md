# BulkBites BBQ QA report ? 21 October 2026

The QA review was read-only for application code and live business records.
All browser actions that changed orders, payments, stock, categories, accounts or
settings used a disposable application/database with anonymized sample data.
Real email and payment credentials were excluded from that copy.

After the user separately approved **only the limit change**, live limits were
updated from 10 per item / 50 total to **250 per item / 1,500 total**. A private
SQL backup and SHA256 were saved first. Other store settings were preserved.
Only these approved files changed:

- `bulkbites-bbq/database/current.sql`
- `bulkbites-bbq/includes/store-migration.php`
- `bulkbites-bbq/includes/store.php`
- `bulkbites-bbq/manager/store.php`
- `bulkbites-bbq/tests/store-cases.php`

189 original source/documentation files were fingerprinted. Before approval,
there were zero changes. After the limit update, there were **zero changes
outside the approved limit scope**. No QA finding was fixed.

## Results

| Check | Result |
|---|---|
| Latest backend suite, including 250/251 and 1500/1501 boundaries | 215 passed |
| Local TLS SMTP / OTP / receipt suite | 90 passed; 18 local fixture messages; no external email |
| HTTP authorization / CSRF / redirects | 65 passed |
| Browser workflow checks | 114 passed |
| Page captures | 75 views: 25 pages at 320/390/1440px |
| Page overflow, broken images, PHP/page errors | None found in captures |
| PHP syntax | 110 files passed |
| Application JavaScript syntax | Passed |
| Existing public ngrok menu | HTTPS response 200 with BulkBites page content at test time |

Browser coverage includes menu/cart quantity controls, remove/clear confirmation,
checkout recovery after refresh/HTTP503, offline submission, duplicate-submit
prevention, pickup and delivery completion, demo payment, receipts, live manager
collections and queue changes, Open/Close, staff permissions, product uploads,
stock corrections, selling/menu visibility, category/account controls, store
settings, report filters, customer search, notifications and actual QR decoding.

The initial fresh-backup comparison failed once; the unchanged rerun passed all
208 original assertions. Its cause was not established. Test navigation/selector
issues were corrected only in temporary test helpers; the final manager checks
passed 47/47 with no section errors. The later approved-limit suite passed 215.

## Findings left unchanged

### QA-01 ? History changes the meaning of today's filter (medium)

1. Use an order placed yesterday but completed today.
2. Open Dashboard > Completed today: that order appears.
3. Click History while the Today filter is retained.

Observed: the order disappears. Completed today filters the completion event;
History with period=TODAY filters the placement date. Clicking the tab therefore
changes the date basis and can make today's completed work appear missing.
Source: `bulkbites-bbq/staff/orders.php:22`.
Evidence: `history-completed-today.png`, `history-after-tab.png`,
`browser-edges-final.log`.
Recommendation: choose and consistently apply the intended history date basis,
or make the distinction explicit. No change applied.

### QA-02 ? Category hiding does not prevent saved-cart purchases (policy/behavior)

1. Add a stocked product to the cart.
2. Manager hides its category.
3. The product disappears from the menu.
4. The existing cart can still be checked out successfully.

Observed: category visibility is checked by the menu, but cart/checkout check
product activity and stock without the category's visibility. Decide whether
Hide is meant only to hide discovery or also stop sales. Until that policy is
changed, use product Pause ordering to stop sales.
Sources: `bulkbites-bbq/includes/cart.php:46` and
`bulkbites-bbq/includes/checkout.php:87`.
Evidence: `hidden-category-checkout.png`, `browser-edges-final.log`.
No change applied.

## Not independently verified

- Real Gmail inbox delivery, spam-folder behavior and the actual sender account.
- Physical phone camera scans, device-specific keyboards, and mobile-data quality.
- Real PayMongo provider/webhook behavior; this project uses the demo simulator.
- Physical printing and long-duration/load testing at presentation scale.
- A complete no-JavaScript walkthrough or every browser/assistive technology.
- Fresh machine installation / actual public-demo start-stop cycle: the existing
  public menu was checked without interrupting the active tunnel.

A database/service interruption occurred after the main QA checks. MySQL was
started for the explicitly approved limit update. Final boundary testing and
fixture cleanup then succeeded. The disposable QA database, server and test
sessions were removed. Live orders, payments and stock were not QA fixtures.

## Evidence

- [Screenshot gallery](index.html)
- `source-before.json`, `source-integrity.json` and
  `source-integrity-after-approved-limits.json`
- `backend-recheck.log`, `approved-limits-tests.log`, `email.log`, `http.log`
- `browser-admin-final.log`, `browser-cart.log`, `browser-reliability.log`,
  `browser-completion.log`, `browser-dashboard.log`, `browser-pages.log`
- `approved-limits-result.log`: only the approved live setting values changed.
