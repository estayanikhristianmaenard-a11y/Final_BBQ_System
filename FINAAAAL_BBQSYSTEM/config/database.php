<?php
/**
 * BulkBites BBQ — LOCAL XAMPP CONFIGURATION.
 *
 * This file is listed in .gitignore and must NEVER be committed to GitHub.
 * See config.example.php for documentation of every setting.
 */

return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'bulkbites_bbq',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        'name'      => 'BulkBites BBQ',
        'debug'     => false,  // Phase 8 audit: never leak stack traces/SQL to HTTP clients
        'timezone'  => 'Asia/Manila',

        'otp_delivery_mode'           => 'debug',
        'otp_expiry_minutes'          => 5,
        'otp_max_attempts'            => 3,
        'otp_resend_cooldown_seconds' => 60,
        'otp_request_max_per_hour'    => 5,
        'otp_request_ip_max_per_hour' => 10,
        'otp_verify_max_per_hour'     => 10,

        'staff_login_max_per_15min'    => 5,
        'staff_login_ip_max_per_15min' => 10,

        'session_idle' => [
            'CUSTOMER' => 1800,
            'STAFF'    => 28800,
            'MANAGER'  => 28800,
        ],
        'session_absolute' => [
            'CUSTOMER' => 43200,
            'STAFF'    => 86400,
            'MANAGER'  => 86400,
        ],
    ],
];
