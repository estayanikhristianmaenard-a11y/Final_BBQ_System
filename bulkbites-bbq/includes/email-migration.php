<?php
/** Idempotent, non-destructive upgrade, called by CLI setup/migration only. */
function migrate_email_login(PDO $pdo): void
{
    $columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('email', $columns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email VARCHAR(254) DEFAULT NULL, ADD UNIQUE KEY uq_users_email (email)');
    }
    $pdo->exec("ALTER TABLE users MODIFY phone VARCHAR(20) NULL COMMENT 'Staff login or optional legacy customer contact'");
    $pdo->exec('CREATE TABLE IF NOT EXISTS email_otp_records (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(254) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        max_attempts INT NOT NULL DEFAULT 3,
        expires_at DATETIME NOT NULL,
        consumed_at DATETIME DEFAULT NULL,
        resend_after DATETIME NOT NULL,
        request_ip VARCHAR(45) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_email_otp_email_id (email, id),
        KEY idx_email_otp_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('ALTER TABLE customers MODIFY email VARCHAR(254) DEFAULT NULL');
    $otpColumns = $pdo->query('SHOW COLUMNS FROM email_otp_records')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('purpose', $otpColumns, true)) {
        $pdo->exec("ALTER TABLE email_otp_records ADD COLUMN purpose ENUM('CUSTOMER','STAFF') NOT NULL DEFAULT 'CUSTOMER',
            ADD KEY idx_email_otp_purpose (email, purpose, id)");
    }
    $schema = $pdo->query('SHOW CREATE TABLE users')->fetch(PDO::FETCH_NUM)[1];
    if (str_contains($schema, 'chk_users_passwd')) {
        $drop = stripos($pdo->getAttribute(PDO::ATTR_SERVER_VERSION), 'MariaDB') !== false ? 'DROP CONSTRAINT' : 'DROP CHECK';
        $pdo->exec('ALTER TABLE users ' . $drop . ' chk_users_passwd');
    }
}

/** These are the only initial personnel accounts. Later additions are manager-controlled. */
function default_manager_accounts(): array
{
    return [
        'estayani_khristianmaenard@plpasig.edu.ph' => 'Khristian Maenard Estayani',
        'casas_williamjake@plpasig.edu.ph' => 'William Jake Casas',
        'magtapat_jullianmatthew@plpasig.edu.ph' => 'Jullian Matthew Magtapat',
    ];
}

function provision_default_managers(PDO $pdo): void
{
    $check = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $check->execute(['email_staff_defaults_v1']);
    if ($check->fetchColumn() !== false) return;
    $pdo->beginTransaction();
    try {
        // Preserve foreign keys, receipts, and the users who handled old orders.
        $pdo->exec("UPDATE users SET is_active = 0, password_hash = NULL WHERE role IN ('STAFF','MANAGER')");
        foreach (default_manager_accounts() as $email => $name) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $id = $stmt->fetchColumn();
            if ($id) {
                $pdo->prepare("UPDATE users SET full_name = ?, role = 'MANAGER', is_active = 1, password_hash = NULL WHERE id = ?")
                    ->execute([$name, $id]);
            } else {
                $pdo->prepare("INSERT INTO users (full_name, email, phone, role, password_hash) VALUES (?, ?, NULL, 'MANAGER', NULL)")
                    ->execute([$name, $email]);
            }
        }
        $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)')->execute(['email_staff_defaults_v1', '1']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function migrate_receipt_emails(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS receipt_email_deliveries (
        order_id INT NOT NULL PRIMARY KEY,
        recipient VARCHAR(254) DEFAULT NULL,
        status ENUM('PENDING','SENT','FAILED','SKIPPED') NOT NULL DEFAULT 'PENDING',
        attempts INT NOT NULL DEFAULT 0,
        last_error VARCHAR(40) DEFAULT NULL,
        next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        sent_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_receipt_email_pending (status, next_attempt_at),
        CONSTRAINT fk_receipt_email_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
