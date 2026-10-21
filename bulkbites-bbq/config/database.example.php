<?php
/**
 * BulkBites BBQ — Configuration TEMPLATE (safe to commit to GitHub).
 *
 * Setup: copy this file to config/database.php and fill in your own values.
 *   config/database.php is listed in .gitignore and must NEVER be pushed.
 *
 * XAMPP default MySQL credentials are root with an empty password.
 */

return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'bulkbites_bbq',
        'user'    => 'root',          // <-- change if your MySQL user differs
        'pass'    => '',              // <-- change if your MySQL user has a password
        'charset' => 'utf8mb4',
    ],

    'app' => [
        'name'      => 'BulkBites BBQ',
        'debug'     => true,          // false in production: hides error details
        'timezone'  => 'Asia/Manila',
        'demo_payments_enabled' => true, // Local payment simulator; no account, API key, or real money.
        'online_reservation_minutes' => 10,
        'cash_reservation_minutes' => 15,

        // Email OTP login for customers, staff, and managers
        // Real SMTP email delivery is configured separately in config/mail.php.
        'otp_expiry_minutes'          => 5,
        'otp_max_attempts'            => 3,
        'otp_resend_cooldown_seconds' => 60,
        'otp_request_max_per_hour'    => 5,   // per email
        'otp_request_ip_max_per_hour' => 10,  // per IP address
        'otp_verify_max_per_hour'     => 10,  // per email

        // Session lifetimes (seconds)
        'session_idle' => [
            'CUSTOMER' => 1800,   // 30 minutes
            'STAFF'    => 28800,  // 8 hours
            'MANAGER'  => 28800,  // 8 hours
        ],
        'session_absolute' => [
            'CUSTOMER' => 43200,  // 12 hours
            'STAFF'    => 86400,  // 24 hours
            'MANAGER'  => 86400,  // 24 hours
        ],
    ],
];
