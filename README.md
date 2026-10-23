# BulkBites BBQ Ordering System

A simple web-based BBQ ordering system: customers scan a menu QR, choose food,
place a pickup or delivery order, and follow its preparation status. Staff manage orders;
managers maintain products, stock, accounts, and sales reports.

Built with HTML, CSS, vanilla JavaScript, PHP, and MySQL using XAMPP.
Pickup is free. Delivery within Pasig has a fixed ₱20 fee and requires an address and contact number. Staff arrange delivery manually; live GPS tracking is outside the scope.

Navigation is focused on each role's main task:

- Customers: Menu, Cart, My Orders, and a bell for recent updates.
- Staff: Kitchen and Orders (Active orders / History). Kitchen includes queue counts, ordering controls, and low-stock notices.
- Managers: Dashboard, Orders, Products & Stock, Reports, and Settings.

Products, prices, availability, and stock use one manager list. Settings shows
Store and Ordering QR; Email, staff accounts, customer directory and categories
are under Advanced settings. Product creation/uploads, advanced order limits,
stock thresholds and detailed analytics are collapsed for the presentation.
Reports default to today's essentials; detailed figures and date filters remain expandable.
Old dashboard, inventory, and notification URLs remain compatible through redirects.

Menu and cart actions update in place with JavaScript: minus/plus controls,
server-calculated totals, and a small confirmation popup keep customers at their
current position. Ordinary forms remain available without JavaScript. Manager
Dashboard shows three daily figures above the live kitchen queue; detailed analytics
are kept on Reports.

Application source: **`bulkbites-bbq/`**. See the
[project structure](docs/ARCHITECTURE.md) for file responsibilities.

## Setup and guides

Payments for the presentation use the built-in [payment simulator](docs/PAYMENT-DEMO.md).
Choose **Demo payment** at checkout; no PayMongo account, documents, or API keys
are required. All simulated payments and receipts are clearly marked.

Pickup names, store hours/Open/Closed control, order limits, preparation estimates,
automatic status checks, and customer cancellation are implemented. Configure the
shop under **Manager > Store**. See [pickup ordering controls](docs/STORE-ORDERING.md).
Staff can use **Open ordering / Close ordering** from their dashboard or Kitchen
Display. Manager Open/Close actions override daily hours, including for testing outside
10 AM-10 PM. Use **Use automatic hours** to resume the schedule. Staff Open follows
the schedule. Existing orders stay accessible in either mode.
Existing installations should back up and run `bulkbites-bbq/UPGRADE-PAYMENTS.bat`
to add the store/pickup schema.

Current improvement plan: [workflow](docs/WORKFLOW.md).
Guided public demo setup: double-click `bulkbites-bbq/SETUP-DEMO.bat`.
See [startup and QR instructions](docs/DEMO-SETUP.md) and the
[professor presentation checklist](docs/DEMO-CHECKLIST.md).
Staff and managers sign in with their authorized email and password. Existing
accounts can use **Set or reset password** to verify their email once and choose
a password. Managers can also set passwords under **Settings > Advanced > Staff accounts**.
New personnel accounts include a password. Customer checkout still uses email verification.

Customer UI: public menu and guest cart are available at the app's root URL.
Email verification happens at checkout and preserves the cart. The first UI pass
includes responsive layouts, food illustrations, and a floating mobile cart link.
See [UI design notes](docs/UI.md).
Latest complete screenshot review and interactive grill: [UI review](docs/UI-REVIEW.md).
Local verification results and test commands: [baseline report](docs/BASELINE.md).
Current desktop/mobile previews: [screenshot gallery](output/playwright/review-audit/index.html).
Payment/stock behavior and existing-database upgrade: [payment rules](docs/PAYMENTS.md).
PayMongo sandbox configuration and public demo startup: [PayMongo setup](docs/PAYMONGO.md).
Existing installations must back up and run `bulkbites-bbq/UPGRADE-PAYMENTS.bat`
after pulling the payment changes. New database setup includes this migration.

The application uses PHP and MySQL in XAMPP. A copy of the source PC's database
is included in `bulkbites-bbq/database/current.sql`. Git transfers this saved
snapshot; MySQL continues to store each running PC's database separately.

## Before committing and pushing on the source PC

1. Start MySQL in XAMPP. Pause ordering and database edits while taking the backup.
2. Double-click `bulkbites-bbq/BACKUP-DATABASE.bat`.
3. Confirm it reports that `database/current.sql` was saved.
4. Commit and push the changed snapshot together with your application changes.
   Include any product images in `bulkbites-bbq/uploads` as well.

The snapshot includes the current accounts, password hashes, customers, orders,
inventory, and all other database tables. Use a private repository for real customer
records. Refresh the snapshot whenever you want newer records to travel with a pull.

## On a new Windows PC

1. Install XAMPP with PHP 8.x, enabling `pdo_mysql`, `mbstring`, and `fileinfo`.
2. Clone the repository into `<xampp>\htdocs\Final_BBQ_System`.
3. Start MySQL in the XAMPP Control Panel.
4. Check `bulkbites-bbq/config/database.php` for that PC's database settings.
   The local defaults are host `localhost`, database `bulkbites_bbq`, user `root`,
   and an empty password. If the file is absent, setup copies `config/database.example.php`.
5. Double-click `bulkbites-bbq/SETUP-DATABASE.bat`.
6. Start Apache and open
   `http://localhost/Final_BBQ_System/bulkbites-bbq/`.

The setup creates the database and restores the saved records. It refuses to import
when the target database already has tables, preserving that PC's existing data.
If Apache uses a different port or the repository has a different folder name,
adjust the browser URL accordingly.

For command-line use, run these with XAMPP's PHP executable:

```powershell
& C:\xampp\php\php.exe bulkbites-bbq/tools/backup-database.php
& C:\xampp\php\php.exe bulkbites-bbq/tools/setup-database.php
```

Change `C:\xampp` to your installation path. For a separate test database, setup
also accepts `--database=bulkbites_transfer_test`. This does not change the app's
configured database.

## Later pulls and login

A later pull updates the saved snapshot file. It does not replace an existing PC's
database or combine orders created independently on two PCs. Sharing changing data
between PCs requires a shared database or a hosted application.

All accounts use an email address and a real emailed one-time code. On a new
server, double-click **bulkbites-bbq/SETUP-EMAIL.bat** before signing in.
Enter the sender email and Google App Password locally; password entry is
hidden. Setup tests delivery before saving the ignored mail configuration.
Afterward, sign in as a manager and use **Settings > Email** to manage the sender.
See [email login setup](docs/EMAIL-SETUP.md) for App Password instructions.
Existing installations must run `bulkbites-bbq/UPGRADE-EMAIL-LOGIN.bat` after
pulling these changes. New database setup applies the upgrade automatically.
The three listed school email addresses are the initial managers; old staff
accounts are disabled. Managers add additional personnel from **Staff**.
Customers receive an itemized receipt email after payment is confirmed. For cash,
staff records payment at pickup. Demo receipts are clearly labeled; pickup
completion does not resend an already delivered receipt. Ordering hours are
**10:00 AM–10:00 PM daily**, shown on the menu and footer.

`bulkbites-bbq/database/schema.sql` is the original demo schema and seed data. Importing
it resets the tables and does not transfer the source PC's current records. Use the
bundled snapshot and setup script to transfer the current system.

## Current verification and private archives

Run the maintained fresh-database regression check from the project root:

```powershell
C:\xampp\php\php.exe bulkbites-bbq/tests/current-baseline.php
```

It exports the current database into a disposable test snapshot, runs the
baseline checks and removes that snapshot. It does not replace live data.
The simplification pass passed 202 backend assertions and 36 browser checks;
38 refreshed screenshots cover desktop/mobile pages and order/product windows.

Private history is kept under ignored `archives/`: verified ZIP archives hold
old screenshots, temporary review scripts and obsolete SVG/UI documents.
`archives/backups/` contains a fresh SQL backup with a SHA256 checksum. Keep
these archives private; development history and SQL exports may contain local
configuration or customer data. The demo still uses `.tmp/bin/ngrok.exe` and
`.tmp/public-demo`; do not delete the whole `.tmp` directory.
