<?php
/** Copy to mail.php in this directory. Never commit mail.php or share its password. */
return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls', // 'tls' for STARTTLS/587, 'ssl' for implicit TLS/465
    'username' => 'your-address@gmail.com',
    'password' => '', // Gmail App Password, NOT your normal Gmail password
    'from_email' => 'your-address@gmail.com',
    'from_name' => 'BulkBites BBQ',
];
