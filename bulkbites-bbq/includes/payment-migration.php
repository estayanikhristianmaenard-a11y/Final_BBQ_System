<?php
/** Explicit CLI migration only. Preserve old records without inventing payments. */
function migrate_payment_inventory(PDO $pdo): void
{
    require_once __DIR__ . '/store-migration.php';
    $columns = $pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_COLUMN);
    $additions = [
        'payment_status' => "ENUM('LEGACY_UNVERIFIED','UNPAID','PENDING','PAID','EXPIRED','REFUND_REQUIRED','REFUNDED') NOT NULL DEFAULT 'LEGACY_UNVERIFIED'",
        'stock_state' => "ENUM('LEGACY','RESERVED','COMMITTED','RELEASED','WASTED') NOT NULL DEFAULT 'LEGACY'",
        'reservation_expires_at' => 'DATETIME NULL',
        'paid_at' => 'DATETIME NULL',
        'paid_by' => 'INT NULL',
        'payment_reference' => 'VARCHAR(128) NULL',
        'payment_is_test' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'refunded_at' => 'DATETIME NULL',
    ];
    foreach ($additions as $name => $definition) {
        if (!in_array($name, $columns, true)) { $pdo->exec('ALTER TABLE orders ADD COLUMN ' . $name . ' ' . $definition); }
    }
    $indexes = $pdo->query('SHOW INDEX FROM orders')->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($indexes, 'Key_name');
    if (!in_array('idx_order_reservation', $names, true)) {
        $pdo->exec('ALTER TABLE orders ADD KEY idx_order_reservation (status, reservation_expires_at)');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_events (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        event_type ENUM('CASH_COLLECTED','ONLINE_VERIFIED','REFUND_RECORDED') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        reference VARCHAR(128) NOT NULL,
        performed_by INT NULL,
        is_test TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_payment_event_reference (reference),
        KEY idx_payment_event_order (order_id),
        CONSTRAINT fk_payment_event_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
        CONSTRAINT fk_payment_event_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS paymongo_checkouts (
        order_id INT NOT NULL PRIMARY KEY,
        reference_number VARCHAR(64) NOT NULL,
        session_id VARCHAR(128) NULL,
        checkout_url VARCHAR(2048) NULL,
        state ENUM('CREATING','READY','UNKNOWN') NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_paymongo_reference (reference_number),
        UNIQUE KEY uq_paymongo_session (session_id),
        CONSTRAINT fk_paymongo_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    migrate_store_ordering($pdo);
    $method=$pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_method'")->fetch(PDO::FETCH_ASSOC);
    if (!str_contains($method['Type'], "'DEMO'")) { $pdo->exec("ALTER TABLE orders MODIFY payment_method ENUM('CASH','GCASH','DEMO') NOT NULL DEFAULT 'CASH'"); }
}
