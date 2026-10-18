-- =====================================================================================
-- BulkBites BBQ — Digital Ordering and Business Management System
-- FILE:    database.sql   (Phase 1 — Database)
-- ENGINE:  InnoDB   CHARSET: utf8mb4   COLLATE: utf8mb4_unicode_ci   MONEY: DECIMAL(10,2)
-- IMPORT:  phpMyAdmin -> Import -> select this file -> Go
--          (or: mysql -u root < database.sql)
--
-- RE-IMPORT IS SAFE: all tables are dropped and recreated in reverse dependency order.
-- =====================================================================================
--
-- RELATIONSHIP MAP (see README/ARCHITECTURE for details)
--
--   users          1 -- 1  customers        (only rows with role = 'CUSTOMER')
--   users          1 -- n  orders           (customer_id, handled_by)
--   users          1 -- n  notifications    (user_id)
--   users          1 -- n  order_status_history (changed_by)
--   users          1 -- n  inventory        (updated_by)
--   users          1 -- n  inventory_movements (performed_by)
--   categories     1 -- n  products
--   products       1 -- 1  inventory        (stock lives ONLY here, not on products)
--   products       1 -- n  order_items      (name/price snapshotted into order_items)
--   products       1 -- n  inventory_movements
--   orders         1 -- n  order_items
--   orders         1 -- 1  receipts         (one receipt per completed checkout)
--   orders         1 -- n  order_status_history
--   orders         1 -- n  notifications
--   orders         1 -- n  inventory_movements (reference_order_id)
--   otp_records, rate_limits, settings: standalone (no FKs by design)
--
-- DELETE RULES: financial/history records are preserved (RESTRICT), reference links
--   to removable rows become NULL (SET NULL), owned detail rows cascade (CASCADE).
--   Accounts are deactivated (is_active = 0), not deleted — orders RESTRICT deletion.
-- =====================================================================================

CREATE DATABASE IF NOT EXISTS `bulkbites_bbq`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `bulkbites_bbq`;

-- Drop in reverse dependency order (children first) so re-import always succeeds
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `rate_limits`;
DROP TABLE IF EXISTS `inventory_movements`;
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `receipt_email_deliveries`;
DROP TABLE IF EXISTS `otp_records`;
DROP TABLE IF EXISTS `email_otp_records`;
DROP TABLE IF EXISTS `receipts`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================================
-- 1. USERS — single authentication/role table for CUSTOMER, STAFF, MANAGER
--    Customers log in with email + OTP (password_hash NULL).
--    Staff/Manager log in with an authorized email + one-time emailed code.
-- =====================================================================================
CREATE TABLE `users` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `full_name`     VARCHAR(100) NOT NULL,
  `phone`         VARCHAR(20) DEFAULT NULL COMMENT 'Staff login or optional legacy customer contact',
  `email`         VARCHAR(254) DEFAULT NULL COMMENT 'Customer email login',
  `role`          ENUM('CUSTOMER','STAFF','MANAGER') NOT NULL DEFAULT 'CUSTOMER',
  `password_hash` VARCHAR(255) DEFAULT NULL COMMENT 'password_hash() bcrypt; NULL for OTP-only customers',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = deactivated, logged out on next request',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  CONSTRAINT `chk_users_phone`   CHECK (`phone` REGEXP '^09[0-9]{9}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 2. CUSTOMERS — 1:1 profile extension of users where role = 'CUSTOMER'
--    Credentials stay in users; this table only holds customer-specific profile data.
-- =====================================================================================
CREATE TABLE `customers` (
  `user_id`      INT NOT NULL,
  `display_name` VARCHAR(100) DEFAULT NULL COMMENT 'Name shown on receipts',
  `email`        VARCHAR(254) DEFAULT NULL,
  `address`      VARCHAR(255) DEFAULT NULL COMMENT 'Barangay/address for bulk orders',
  `remarks`      TEXT DEFAULT NULL COMMENT 'Manager notes',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 3. CATEGORIES — product grouping (Chicken, Pork, Hotdog, ...)
-- =====================================================================================
CREATE TABLE `categories` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(60) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 4. PRODUCTS — authoritative NAME and PRICE (never trust browser prices)
--    Products are DEACTIVATED (is_active = 0), not deleted, once they have orders.
-- =====================================================================================
CREATE TABLE `products` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `category_id` INT NOT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `price`       DECIMAL(10,2) NOT NULL COMMENT 'Authoritative selling price in PHP',
  `image`       VARCHAR(255) DEFAULT NULL COMMENT 'Path under assets/images or uploads',
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = hidden and unorderable',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_category_active` (`category_id`, `is_active`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_products_price` CHECK (`price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 5. INVENTORY — 1:1 with products; the ONLY place stock numbers live.
--    Status is derived, never stored:
--      OUT OF STOCK = quantity_available = 0 OR is_available = 0
--      LOW STOCK    = 0 < quantity_available <= low_stock_threshold
--      IN STOCK     = quantity_available > low_stock_threshold
-- =====================================================================================
CREATE TABLE `inventory` (
  `product_id`          INT NOT NULL,
  `quantity_available`  INT NOT NULL DEFAULT 0 COMMENT 'Current stock (sticks/pcs)',
  `unit`                VARCHAR(20) NOT NULL DEFAULT 'pcs',
  `low_stock_threshold` INT NOT NULL DEFAULT 10,
  `is_available`        TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Manual sold-out toggle',
  `updated_by`          INT DEFAULT NULL COMMENT 'User who last changed stock',
  `last_updated`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  KEY `idx_inventory_updated_by` (`updated_by`),
  CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_inventory_updated_by` FOREIGN KEY (`updated_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_inventory_qty`       CHECK (`quantity_available` >= 0),
  CONSTRAINT `chk_inventory_threshold` CHECK (`low_stock_threshold` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 6. ORDERS — one row per checkout. Totals computed SERVER-SIDE from products.price.
--    order_number format (generated in PHP at commit time): BB-YYMMDD-NNNN
-- =====================================================================================
CREATE TABLE `orders` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `order_number`   VARCHAR(20) NOT NULL,
  `customer_id`    INT NOT NULL,
  `status`         ENUM('NEW','ACCEPTED','PREPARING','READY','COMPLETED','CANCELLED') NOT NULL DEFAULT 'NEW',
  `subtotal`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('CASH','GCASH') NOT NULL DEFAULT 'CASH',
  `notes`          VARCHAR(255) DEFAULT NULL COMMENT 'Customer instructions',
  `handled_by`     INT DEFAULT NULL COMMENT 'Staff who accepted/completed the order',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_number` (`order_number`),
  KEY `idx_orders_customer` (`customer_id`, `created_at`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_created` (`created_at`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`)
    REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_handled_by` FOREIGN KEY (`handled_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_orders_totals` CHECK (`subtotal` >= 0 AND `total` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 7. ORDER_ITEMS — line items with SNAPSHOT of product name + price at order time,
--    so old receipts stay correct even after a price/product change.
-- =====================================================================================
CREATE TABLE `order_items` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `order_id`     INT NOT NULL,
  `product_id`   INT NOT NULL,
  `product_name` VARCHAR(100) NOT NULL COMMENT 'Snapshot',
  `unit_price`   DECIMAL(10,2) NOT NULL COMMENT 'Snapshot of products.price at order time',
  `quantity`     INT NOT NULL,
  `line_total`   DECIMAL(10,2) NOT NULL COMMENT 'unit_price * quantity',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_order_items_qty`   CHECK (`quantity` > 0),
  CONSTRAINT `chk_order_items_price` CHECK (`unit_price` >= 0 AND `line_total` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 8. RECEIPTS — exactly one per order (1:1), created inside the checkout transaction.
--    receipt_number format: RC-YYMMDD-NNNN
-- =====================================================================================
CREATE TABLE `receipts` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `order_id`       INT NOT NULL,
  `receipt_number` VARCHAR(20) NOT NULL,
  `issued_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total`          DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(10) NOT NULL DEFAULT 'CASH',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipts_order` (`order_id`),
  UNIQUE KEY `uq_receipts_number` (`receipt_number`),
  CONSTRAINT `fk_receipts_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_receipts_total` CHECK (`total` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 9. OTP_RECORDS — short-lived login codes. Deliberately NO foreign key to users:
--    the code is issued BEFORE the customer account exists and must survive
--    account deletion. Stores only a HASH (password_hash of the 6-digit code).
--    Rules enforced by PHP (Phase 2): 5-minute expiry, 3 attempts, 60-second
--    resend cooldown, per-phone/per-IP hourly caps.
-- =====================================================================================
CREATE TABLE `otp_records` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `phone`        VARCHAR(20) NOT NULL,
  `purpose`      ENUM('LOGIN') NOT NULL DEFAULT 'LOGIN',
  `otp_hash`     VARCHAR(255) NOT NULL COMMENT 'password_hash(code) — never plaintext',
  `attempts`     INT NOT NULL DEFAULT 0,
  `max_attempts` INT NOT NULL DEFAULT 3,
  `expires_at`   DATETIME NOT NULL,
  `consumed_at`  DATETIME DEFAULT NULL COMMENT 'Set on successful verification (single-use)',
  `resend_after` DATETIME NOT NULL COMMENT 'Cooldown gate before next request',
  `request_ip`   VARCHAR(45) DEFAULT NULL COMMENT 'Rate limiting / audit',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_phone_created` (`phone`, `created_at`),
  KEY `idx_otp_expires` (`expires_at`),
  CONSTRAINT `chk_otp_attempts` CHECK (`attempts` >= 0 AND `max_attempts` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- Email sign-in records. Legacy SMS records above are retained only for old data.
-- =====================================================================================
CREATE TABLE `email_otp_records` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(254) NOT NULL,
  `purpose` ENUM('CUSTOMER','STAFF') NOT NULL DEFAULT 'CUSTOMER',
  `otp_hash` VARCHAR(255) NOT NULL,
  `attempts` INT NOT NULL DEFAULT 0,
  `max_attempts` INT NOT NULL DEFAULT 3,
  `expires_at` DATETIME NOT NULL,
  `consumed_at` DATETIME DEFAULT NULL,
  `resend_after` DATETIME NOT NULL,
  `request_ip` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_email_otp_email_id` (`email`, `id`),
  KEY `idx_email_otp_purpose` (`email`, `purpose`, `id`),
  KEY `idx_email_otp_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 10. NOTIFICATIONS — one row per recipient (customer + each active staff/manager).
--     user_id is always set; is_read tracks per-user read state.
-- =====================================================================================
CREATE TABLE `notifications` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `user_id`    INT NOT NULL COMMENT 'Recipient',
  `order_id`   INT DEFAULT NULL,
  `type`       ENUM('NEW_ORDER','ORDER_STATUS','LOW_STOCK','GENERAL') NOT NULL DEFAULT 'GENERAL',
  `title`      VARCHAR(120) NOT NULL,
  `message`    VARCHAR(255) NOT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`, `is_read`, `created_at`),
  KEY `idx_notifications_order` (`order_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 11. ORDER_STATUS_HISTORY — audit trail of every status transition.
--     old_status/new_status are VARCHAR so history survives future ENUM changes.
-- =====================================================================================
CREATE TABLE `order_status_history` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `order_id`   INT NOT NULL,
  `old_status` VARCHAR(20) DEFAULT NULL COMMENT 'NULL on order creation',
  `new_status` VARCHAR(20) NOT NULL,
  `changed_by` INT DEFAULT NULL COMMENT 'NULL = system (checkout)',
  `remarks`    VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_history_order` (`order_id`, `created_at`),
  KEY `idx_history_changed_by` (`changed_by`),
  CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_history_changed_by` FOREIGN KEY (`changed_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 12. INVENTORY_MOVEMENTS — stock audit trail (why the number changed).
--     ORDER_DEDUCT rows are written by checkout; RESTOCK/ADJUSTMENT/WASTE by staff/manager.
-- =====================================================================================
CREATE TABLE `inventory_movements` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `product_id`        INT NOT NULL,
  `movement_type`     ENUM('ORDER_DEDUCT','RESTOCK','ADJUSTMENT','WASTE') NOT NULL,
  `quantity_change`   INT NOT NULL COMMENT 'Positive = add, negative = deduct',
  `reference_order_id` INT DEFAULT NULL COMMENT 'Set for checkout deductions',
  `performed_by`      INT DEFAULT NULL COMMENT 'NULL = system (checkout)',
  `remarks`           VARCHAR(255) DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_moves_product` (`product_id`, `created_at`),
  KEY `idx_moves_order` (`reference_order_id`),
  KEY `idx_moves_performed_by` (`performed_by`),
  CONSTRAINT `fk_moves_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_moves_order` FOREIGN KEY (`reference_order_id`)
    REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_moves_performed_by` FOREIGN KEY (`performed_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_moves_qty` CHECK (`quantity_change` <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 13. RATE_LIMITS — generic server-side throttle for OTP requests and login attempts.
--     UNIQUE(action, identifier, window_start) makes counting safe and idempotent.
-- =====================================================================================
CREATE TABLE `rate_limits` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `action`       VARCHAR(40) NOT NULL COMMENT 'e.g. otp_request, otp_verify, staff_login',
  `identifier`   VARCHAR(64) NOT NULL COMMENT 'phone number or IP address',
  `window_start` DATETIME NOT NULL COMMENT 'Start of the fixed window',
  `attempts`     INT NOT NULL DEFAULT 1,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_limits` (`action`, `identifier`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- 14. SETTINGS — key/value application settings (store info, OTP mode, thresholds).
-- =====================================================================================
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(64) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
-- SEED DATA — Initial BulkBites BBQ products, stock, accounts, and settings
-- =====================================================================================

-- Categories (explicit IDs so product references are deterministic)
INSERT INTO `categories` (`id`, `name`, `sort_order`, `is_active`) VALUES
  (1, 'Chicken', 1, 1),
  (2, 'Pork',    2, 1),
  (3, 'Hotdog',  3, 1);

-- Products — prices exactly as stated in the business proposal
INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `image`, `is_active`) VALUES
  (1, 2, 'BBQ',         'Marinated and grilled barbecue served fresh and suitable as a meal or snack.',            30.00, NULL, 1),
  (2, 1, 'Isaw Manok',  'Grilled chicken intestines prepared in the traditional Filipino ihaw-ihaw style.',        20.00, NULL, 1),
  (3, 2, 'Bituka Baboy','Grilled pork intestines prepared and seasoned for a flavorful street-food experience.',  20.00, NULL, 1),
  (4, 2, 'Tenga Baboy', 'Grilled pork ears served as an affordable Filipino grilled-food option.',                22.00, NULL, 1),
  (5, 1, 'Balunan',     'Grilled chicken gizzard prepared and served fresh.',                                     20.00, NULL, 1),
  (6, 3, 'Hotdog',      'Grilled hotdog served as a convenient and affordable snack.',                            25.00, NULL, 1);

-- Inventory — 1 row per product (100 pcs each, low-stock threshold 10)
INSERT INTO `inventory` (`product_id`, `quantity_available`, `unit`, `low_stock_threshold`, `is_available`) VALUES
  (1, 100, 'pcs', 10, 1),
  (2, 100, 'pcs', 10, 1),
  (3, 100, 'pcs', 10, 1),
  (4, 100, 'pcs', 10, 1),
  (5, 100, 'pcs', 10, 1),
  (6, 100, 'pcs', 10, 1);

-- Only these three initial managers; additional personnel are added by a manager.
INSERT INTO `users` (`full_name`, `email`, `role`, `password_hash`, `is_active`) VALUES
  ('Khristian Maenard Estayani', 'estayani_khristianmaenard@plpasig.edu.ph', 'MANAGER', NULL, 1),
  ('William Jake Casas', 'casas_williamjake@plpasig.edu.ph', 'MANAGER', NULL, 1),
  ('Jullian Matthew Magtapat', 'magtapat_jullianmatthew@plpasig.edu.ph', 'MANAGER', NULL, 1);

CREATE TABLE `receipt_email_deliveries` (
  `order_id` INT NOT NULL PRIMARY KEY,
  `recipient` VARCHAR(254) DEFAULT NULL,
  `status` ENUM('PENDING','SENT','FAILED','SKIPPED') NOT NULL DEFAULT 'PENDING',
  `attempts` INT NOT NULL DEFAULT 0,
  `last_error` VARCHAR(40) DEFAULT NULL,
  `next_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_receipt_email_pending` (`status`, `next_attempt_at`),
  CONSTRAINT `fk_receipt_email_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application settings. Sender credentials are private and stored separately.
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('store_name',          'BulkBites BBQ'),
  ('store_location',      'Greensite Compound, Sta. Lucia, Pasig City'),
  ('email_staff_defaults_v1', '1'),
  ('otp_expiry_minutes',  '5'),
  ('otp_max_attempts',    '3'),
  ('otp_resend_cooldown', '60');

-- =====================================================================================
-- END OF FILE
-- =====================================================================================
