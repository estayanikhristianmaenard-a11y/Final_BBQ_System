# BulkBites BBQ project structure

BulkBites is a simple BBQ ordering website. The stack is PHP, PDO/MySQL,
HTML, CSS and vanilla JavaScript. XAMPP supplies the local runtime. There is
no frontend build step or Node.js application server.

The customer flow is **menu QR -> menu -> cart -> email verification -> checkout
-> preparation status -> pickup or delivery**. Pickup is free; delivery within
Pasig costs a fixed PHP20 and requires an address and contact number. Staff
arrange the handoff manually; rider accounts and live GPS are outside scope.
Cash and the clearly labelled local payment simulator are the presentation
paths. The optional PayMongo TEST adapter remains available for later use.

Manager Overview and Kitchen now share `includes/operations-dashboard.php`.
Managers enter through `manager/dashboard.php`; staff use `staff/kitchen.php`.
The old Kitchen URL redirects managers to Dashboard. One kitchen API poll updates
queue, ordering state and stock notices; daily financial totals are returned only
to managers. Reports retain detailed sales analysis.

## Files and responsibilities

```text
README.md                  Project overview and installation
docs/                      Setup guides, workflow, and verification reports
bulkbites-bbq/              Application source and Windows launchers
  index.php                Public menu entry
  login.php                Customer email sign-in form
  logout.php               CSRF-protected sign-out handler
  auth/                    Staff sign-in, OTP request/verification/resend
  customer/                Menu, cart, checkout, payment, orders, receipts
  staff/                   Order queue, kitchen board, stock, notifications
  manager/                 Products, stock, users, reports, email, QR, store settings
  api/                     Order actions, notifications, signed payment webhook
  includes/                Shared server-side business rules and page components
  config/                  Example configuration and ignored local credentials
  database/                Fresh-install SQL and transferable database snapshot
  assets/                  CSS, JavaScript, illustrations, local QR library
  vendor/phpmailer/        Bundled SMTP dependency and its license
  uploads/                 Runtime product images, when present
  logs/                    Local runtime logs
  tools/                   CLI setup, backup, migration, maintenance, demo helpers
  tests/                   Isolated integration and access checks
output/                    Ignored generated QR and browser review artifacts
.tmp/                      Ignored local tooling, process IDs, and temporary files
```

## Keep the implementation small

Customer sign-in lives in `login.php`; sign-out lives in `logout.php`.
There are no duplicate `auth/login.php` or `auth/logout.php` implementations.
Shared checkout, payment, stock, and notification logic belongs in `includes/`
so pages and API actions use the same business rules. OTP request and resend
handlers remain separate because they perform different session checks.

`config/database.example.php`, `mail.example.php`, `paymongo.example.php`, and
`demo.example.php` are templates. Their local `.php` counterparts contain the
running PC's settings and stay out of Git. The examples are not duplicate live
configuration. Database setup creates the local database config when absent.

`database/schema.sql` contains the original fresh-install schema and seed menu;
schema tests apply the current migrations afterward. `database/current.sql` is
the saved database snapshot used to transfer existing records. They serve
different purposes. Use `SETUP-DATABASE.bat` for the documented snapshot-based
installation; never import a snapshot over a populated database.

Separate `.htaccess` files protect private directories on Apache. Although
their contents can match, each must remain in its own directory. The restricted
PHP demo router enforces its own allowlist because PHP's development server
does not read Apache rules. Third-party licenses remain with their libraries.

## Start and maintain the app

- `SETUP-DATABASE.bat`: install the saved database into an empty target.
- `BACKUP-DATABASE.bat`: refresh the transferable snapshot.
- `SETUP-DEMO.bat`: guide email, public access, and optional TEST payment setup.
- `START-PUBLIC-DEMO.bat` / `STOP-PUBLIC-DEMO.bat`: manage public demo access.
- `START-DEMO.bat`: optional local-only foreground server.
- `SETUP-EMAIL.bat` / `SETUP-PAYMONGO.bat`: configure either service separately.
- `UPGRADE-EMAIL-LOGIN.bat` / `UPGRADE-PAYMENTS.bat`: preserve existing databases
  while applying their respective upgrades.

These small launchers delegate to `tools/`; they do not duplicate the setup
implementations. PHP and PowerShell helpers have different responsibilities:
PHP validates and saves app configuration, while PowerShell supplies hidden
credential prompts and manages Windows processes.

## Guides

- [Business workflow and scope](WORKFLOW.md)
- [Store settings and pickup-ordering controls](STORE-ORDERING.md)
- [Email setup](EMAIL-SETUP.md)
- [Public demo and QR setup](DEMO-SETUP.md)
- [PayMongo TEST setup](PAYMONGO.md)
- [Default payment simulator](PAYMENT-DEMO.md)
- [Payment and stock rules](PAYMENTS.md)
- [Presentation checklist](DEMO-CHECKLIST.md)
- [UI design](UI.md), [review history](UI-REVIEW.md), and
  [current screenshots](../output/playwright/review-audit/index.html)
- [Automated verification](BASELINE.md)

## File cleanup on 2026-10-20

Renamed the application directory from `FINAAAAL_BBQSYSTEM` to `bulkbites-bbq`.
Moved the database template under `config/`, the original schema under
`database/`, and email instructions under `docs/`. Replaced the outdated Phase 0
architecture plan with this guide. Removed unused password-toggle JavaScript
and styles, and consolidated the login/logout entry files. Updated code,
launchers, documentation, tests, and ignored local-config paths accordingly.
The workspace parent folder remains unchanged so the current IDE checkout
continues to work. Database records, snapshots, and third-party assets are preserved.

Current validation: 161 ordering/payment/store assertions, 90 local TLS email
checks, 53 HTTP checks, 18 router checks, and 24 setup checks passed. Fresh schema
import and sign-in redirect checks also passed. All 91 first-party PHP files
passed syntax checks; local JavaScript and PowerShell syntax checks passed.
The checked include/document links resolve. The running public menu returned
HTTP 200 after restart. The configured Gmail sender needs real inbox verification;
the local payment simulator requires no PayMongo account connection.
