<?php
function migrate_store_ordering(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS store_settings (
        id TINYINT NOT NULL PRIMARY KEY,
        ordering_status ENUM('OPEN','PAUSED','CLOSED') NOT NULL DEFAULT 'OPEN',
        use_hours TINYINT NOT NULL DEFAULT 1,
        opens_at TIME NULL DEFAULT '10:00:00', closes_at TIME NULL DEFAULT '22:00:00',
        prep_minutes INT NOT NULL DEFAULT 20,
        max_active_orders INT NOT NULL DEFAULT 1,
        cooldown_seconds INT NOT NULL DEFAULT 60,
        max_item_qty INT NOT NULL DEFAULT 250,
        max_order_qty INT NOT NULL DEFAULT 1500,
        pickup_location VARCHAR(200) NOT NULL DEFAULT 'Greensite Compound, Sta. Lucia, Pasig City',
        contact_details VARCHAR(120) NOT NULL DEFAULT '',
        updated_by INT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec('INSERT IGNORE INTO store_settings (id) VALUES (1)');
    $storeColumns=$pdo->query('SHOW COLUMNS FROM store_settings')->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('delivery_area',$storeColumns,true)) { $pdo->exec("ALTER TABLE store_settings ADD COLUMN delivery_area VARCHAR(200) NOT NULL DEFAULT ''"); }
    $columns=$pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_COLUMN);
    foreach(['pickup_name'=>'VARCHAR(80) NULL','pickup_contact'=>'VARCHAR(30) NULL','pickup_location'=>'VARCHAR(200) NULL','estimated_ready_at'=>'DATETIME NULL','fulfillment_type'=>"ENUM('PICKUP','DELIVERY') NOT NULL DEFAULT 'PICKUP'",'delivery_fee'=>'DECIMAL(10,2) NOT NULL DEFAULT 0.00','delivery_address'=>'VARCHAR(500) NULL'] as $name=>$definition) {
        if(!in_array($name,$columns,true)) { $pdo->exec('ALTER TABLE orders ADD COLUMN ' . $name . ' ' . $definition); }
    }
    $indexes=$pdo->query('SHOW INDEX FROM orders')->fetchAll(PDO::FETCH_ASSOC);
    if(!in_array('idx_customer_order_limits',array_column($indexes,'Key_name'),true)) { $pdo->exec('ALTER TABLE orders ADD KEY idx_customer_order_limits (customer_id,status,created_at)'); }
}
