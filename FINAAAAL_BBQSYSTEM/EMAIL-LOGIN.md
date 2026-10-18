# Email sign-in and completed-order receipts

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
3. Sign in as a manager and open **Email** in the navigation. Enter the sender's
   Google account email and App Password, then click **Connect Gmail and send
   test email**. The backend tests delivery and saves the connection for you.
   No code editing or setup scripts are required. The password is hidden while
   you type and is never returned in the page.
   Opening the Email page while signed out takes you to Staff/Manager Login;
   after a manager signs in, the system returns directly to Email settings.
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

SMTP credentials stay on each server. Connect the sender through **Manager >
Email** again on a new server. `SETUP-EMAIL.bat` is an optional shortcut to open
that page in your browser.
Before moving your current database to another PC, use `BACKUP-DATABASE.bat`.

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

When an order changes to **COMPLETED**, its receipt is queued in the same
database transaction. After the transaction commits, the backend emails an
itemized HTML receipt with a plain-text alternative to the customer's verified
login email. It includes receipt/order numbers, date, quantities, snapshot
prices, total, and payment method. Nothing is emailed on cancellation or before
completion. Old phone-only orders without a verified email are marked skipped.

Failed deliveries keep the order completed and remain queued. Staff queue
polling retries due emails after five minutes, up to five attempts. Managers can
retry them from **Email**. For unattended retries on a hosted server, schedule
`php tools/send-receipts.php` every five minutes. Successful deliveries are not
resent during normal retries. As with SMTP systems generally, a crash after SMTP
acceptance but before saving the result can cause a duplicate on retry.

From the repository root, run:

```powershell
node FINAAAAL_BBQSYSTEM/tests/email-login.mjs E:/xampp/php/php.exe
```

Adjust the PHP path to your XAMPP installation. The runner requires Node.js,
XAMPP's OpenSSL executable, and MySQL permission to create a temporary database.
It uses a local TLS SMTP server, sends no external emails, and removes its
isolated test database and certificate files afterward. A real Gmail inbox
test still requires configuring the sender and using Customer login.
