# Email sign-in and payment receipts

## Email appearance

OTP and payment receipt emails share the website palette: charcoal
`#242824`, ember `#BC4015`, gold `#F4AC3D`, paper `#FBFBF8`, and border `#DFE3DB`.
The header uses the same flame symbol as the website with a text brand name;
there are no remote images, tracking pixels, or web-font dependencies.

The OTP email highlights the six-digit code and keeps expiry, one-use, and
do-not-share instructions. Its inbox preview does not include the code. The
receipt shows saved pickup details, item quantities/prices, payment method,
and a total. TEST receipts retain their warning and use the label Test total.
Both include a plain-text alternative. They share `includes/email-template.php`
to keep the branding consistent without duplicating the layout.

Sample-only HTML/PNG previews are generated locally under
`output/playwright/email-templates/`. Desktop and phone browser previews fit
900px, 390px, and 320px widths. The 90 local TLS email checks and 161 isolated
ordering assertions pass. Rendering in an actual inbox still depends on the
email client; browser screenshots are previews, not inbox screenshots.

## Sign-in behavior

Customers enter their email address, receive a six-digit code, and enter it as
their one-time password. The code expires after five minutes, works once, and
allows three incorrect attempts. Resends have a 60-second cooldown. Codes are
hashed in MySQL and are never displayed in the browser or written to logs.
Staff and managers also use their email and an emailed one-time code. No login
uses a permanent password. The three initial managers are:

- estayani_khristianmaenard@plpasig.edu.ph
- casas_williamjake@plpasig.edu.ph
- magtapat_jullianmatthew@plpasig.edu.ph

Only these personnel accounts are enabled initially. Old staff/manager accounts
are deactivated without deleting order history. Managers may add more email
accounts from **Staff**, with either STAFF or MANAGER access. Customer sign-in
accepts any valid email address; it never creates privileged accounts.

## Enable Gmail delivery on this PC

1. Enable [2-Step Verification](https://myaccount.google.com/security) on the
   Gmail account that will send the store's emails.
2. Create a [Google App Password](https://myaccount.google.com/apppasswords)
   named BulkBites BBQ. Google provides 16 letters. Your usual Google password
   will not work. Some school/work accounts restrict App Passwords; use an
   eligible account or ask its administrator.
3. For first-time setup, double-click **SETUP-EMAIL.bat** on the server PC.
   Enter the sender's Google account email and App Password in the local
   terminal. The password is hidden. The tool tests delivery before saving
   `config/mail.php` and never puts credentials in command-line arguments.
   No manager login, Apache, or database connection is needed for this step.
   Internet access and XAMPP's PHP with OpenSSL are required.
   A failed test leaves existing settings unchanged. Repeated setup refuses
   to replace an already configured sender. A setup already in progress blocks
   a second setup from running at the same time.
4. Start Apache and MySQL, open Customer login, and enter an email address
   whose inbox you can access. Check the inbox or Spam and enter the code.

The sender and customer can use the same Gmail address for your first test.
Gmail requires one-time authorization of a sender account. After connecting,
the backend generates and sends all customer login emails automatically.
The app reports a delivery failure if Gmail rejects the message; it never
falls back to displaying a pretend email code.

For other SMTP services, copy `config/mail.example.php` to `config/mail.php`
and set the host, username, password, sender address, port and encryption.
Supported encryption: `tls` (STARTTLS, usually 587) and `ssl` (usually 465).

## Existing installations and new PCs

Run `UPGRADE-EMAIL-LOGIN.bat` once on any existing database after pulling this
change. It adds email login support without resetting accounts or orders.
`SETUP-DATABASE.bat` also applies this upgrade when importing on a new PC.
This workspace's database has already been upgraded.

Old phone-only customers and their orders remain in the database. Signing in
with an email creates a separate account; no historical account is silently
merged based on an unverified contact address.

SMTP credentials stay on each server. Run **SETUP-EMAIL.bat** on each new server
before signing in. After setup, sign in as a manager and use **Email** to test
delivery or change the sender. Opening that page while signed out takes you to
Staff/Manager Login and returns a manager to Email after verification.
Before moving your current database to another PC, use `BACKUP-DATABASE.bat`.

For a PowerShell terminal, first-time setup is also available as:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File bulkbites-bbq/tools/setup-email.ps1
```

The launcher searches PATH, parent folders, and common C:/D:/E: XAMPP locations
for PHP. The bootstrap is CLI-only; requesting `tools/setup-email.php` over HTTP
returns 403. It creates no accounts or authenticated sessions and does not
relax the manager page's access checks. Do not paste App Passwords into chat,
commit them, or pass them as terminal command arguments.

## Troubleshooting

- Missing App Password option: check 2-Step Verification and account policy.
- Gmail rejects sign-in: create a new App Password and run setup again.
- The manager connection test reports separate messages for rejected sign-in,
  network failure, TLS certificate failure, and Gmail sending limits. Raw SMTP
  responses and credentials are never displayed to customers or logged.
- Connection or certificate errors: check internet access, firewall access to
  `smtp.gmail.com:587`, and XAMPP's `openssl.cafile` CA bundle configuration.
  TLS certificate verification is enabled; do not disable it.
- No message: check Spam and the sender's Sent folder. After the cooldown,
  request another code; only the newest code is accepted.

Delivery uses [PHPMailer 7.1.1](https://github.com/PHPMailer/PHPMailer/tree/v7.1.1).
Gmail configuration follows [Google's SMTP guidance](https://support.google.com/a/answer/176600)
and [App Password instructions](https://support.google.com/accounts/answer/185833).

## Developer verification

When a payment is confirmed, its receipt is queued in the same database
transaction. After the transaction commits, the backend emails an
itemized HTML receipt with a plain-text alternative to the customer's verified
login email. It includes receipt/order numbers, date, quantities, snapshot
prices, total, and payment method. Demo payments send a clearly labeled demo
receipt immediately; cash receipts send when staff records cash received.
The receipt does not claim pickup is complete while an order is still New,
Accepted, Preparing, or Ready. Failed/cancelled payment attempts send no receipt.
Old phone-only orders without a verified email are marked skipped. Completing
pickup does not resend an already delivered receipt.

Failed deliveries preserve the confirmed payment and remain queued. Staff queue
polling retries due emails after five minutes, up to five attempts. Managers can
retry them from **Email**. For unattended retries on a hosted server, schedule
`php tools/send-receipts.php` every five minutes. Successful deliveries are not
resent during normal retries. As with SMTP systems generally, a crash after SMTP
acceptance but before saving the result can cause a duplicate on retry.

From the repository root, run:

```powershell
node bulkbites-bbq/tests/email-login.mjs E:/xampp/php/php.exe
```

Adjust the PHP path to your XAMPP installation. The runner requires Node.js,
XAMPP's OpenSSL executable, and MySQL permission to create a temporary database.
It uses a local TLS SMTP server, sends no external emails, and removes its
isolated test database and certificate files afterward. A real Gmail inbox
test still requires configuring the sender and using Customer login.
