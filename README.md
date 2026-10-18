# BulkBites BBQ: moving the system to another PC

The application uses PHP and MySQL in XAMPP. A copy of the source PC's database
is included in `FINAAAAL_BBQSYSTEM/database/current.sql`. Git transfers this saved
snapshot; MySQL continues to store each running PC's database separately.

## Before committing and pushing on the source PC

1. Start MySQL in XAMPP. Pause ordering and database edits while taking the backup.
2. Double-click `FINAAAAL_BBQSYSTEM/BACKUP-DATABASE.bat`.
3. Confirm it reports that `database/current.sql` was saved.
4. Commit and push the changed snapshot together with your application changes.
   Include any product images in `FINAAAAL_BBQSYSTEM/uploads` as well.

The snapshot includes the current accounts, password hashes, customers, orders,
inventory, and all other database tables. Use a private repository for real customer
records. Refresh the snapshot whenever you want newer records to travel with a pull.

## On a new Windows PC

1. Install XAMPP with PHP 8.x, enabling `pdo_mysql`, `mbstring`, and `fileinfo`.
2. Clone the repository into `<xampp>\htdocs\Final_BBQ_System`.
3. Start MySQL in the XAMPP Control Panel.
4. Check `FINAAAAL_BBQSYSTEM/config/database.php` for that PC's database settings.
   The local defaults are host `localhost`, database `bulkbites_bbq`, user `root`,
   and an empty password. If the file is absent, setup copies `config.example.php`.
5. Double-click `FINAAAAL_BBQSYSTEM/SETUP-DATABASE.bat`.
6. Start Apache and open
   `http://localhost/Final_BBQ_System/FINAAAAL_BBQSYSTEM/`.

The setup creates the database and restores the saved records. It refuses to import
when the target database already has tables, preserving that PC's existing data.
If Apache uses a different port or the repository has a different folder name,
adjust the browser URL accordingly.

For command-line use, run these with XAMPP's PHP executable:

```powershell
& C:\xampp\php\php.exe FINAAAAL_BBQSYSTEM/tools/backup-database.php
& C:\xampp\php\php.exe FINAAAAL_BBQSYSTEM/tools/setup-database.php
```

Change `C:\xampp` to your installation path. For a separate test database, setup
also accepts `--database=bulkbites_transfer_test`. This does not change the app's
configured database.

## Later pulls and login

A later pull updates the saved snapshot file. It does not replace an existing PC's
database or combine orders created independently on two PCs. Sharing changing data
between PCs requires a shared database or a hosted application.

All accounts use an email address and a real emailed one-time code. Sign in as a
manager and open **Email** to connect the Gmail sender once. The backend tests
and saves the connection, then sends customer codes automatically; no code
editing or setup script is required.
See [email login setup](FINAAAAL_BBQSYSTEM/EMAIL-LOGIN.md) for App Password instructions.
Existing installations must run `FINAAAAL_BBQSYSTEM/UPGRADE-EMAIL-LOGIN.bat` after
pulling these changes. New database setup applies the upgrade automatically.
The three listed school email addresses are the initial managers; old staff
accounts are disabled. Managers add additional personnel from **Staff**.
Customers receive an itemized receipt email when their order is completed.

`FINAAAAL_BBQSYSTEM/database.sql` is the original demo schema and seed data. Importing
it resets the tables and does not transfer the source PC's current records. Use the
bundled snapshot and setup script to transfer the current system.
