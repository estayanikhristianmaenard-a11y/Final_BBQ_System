-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: bulkbites_bbq
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Chicken',1,1,'2026-10-07 22:20:20','2026-10-07 22:20:20'),(2,'Pork',2,1,'2026-10-07 22:20:20','2026-10-07 22:20:20'),(3,'Hotdog',3,1,'2026-10-07 22:20:20','2026-10-07 22:20:20');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `user_id` int(11) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL COMMENT 'Name shown on receipts',
  `email` varchar(254) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL COMMENT 'Barangay/address for bulk orders',
  `remarks` text DEFAULT NULL COMMENT 'Manager notes',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (136,NULL,NULL,NULL,NULL,'2026-10-14 13:06:43','2026-10-14 13:06:43');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_otp_records`
--

DROP TABLE IF EXISTS `email_otp_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_otp_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(254) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `resend_after` datetime NOT NULL,
  `request_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `purpose` enum('CUSTOMER','STAFF') NOT NULL DEFAULT 'CUSTOMER',
  PRIMARY KEY (`id`),
  KEY `idx_email_otp_email_id` (`email`,`id`),
  KEY `idx_email_otp_expiry` (`expires_at`),
  KEY `idx_email_otp_purpose` (`email`,`purpose`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_otp_records`
--

LOCK TABLES `email_otp_records` WRITE;
/*!40000 ALTER TABLE `email_otp_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_otp_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory`
--

DROP TABLE IF EXISTS `inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory` (
  `product_id` int(11) NOT NULL,
  `quantity_available` int(11) NOT NULL DEFAULT 0 COMMENT 'Current stock (sticks/pcs)',
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `low_stock_threshold` int(11) NOT NULL DEFAULT 10,
  `is_available` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Manual sold-out toggle',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User who last changed stock',
  `last_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`),
  KEY `idx_inventory_updated_by` (`updated_by`),
  CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_inventory_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_inventory_qty` CHECK (`quantity_available` >= 0),
  CONSTRAINT `chk_inventory_threshold` CHECK (`low_stock_threshold` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory`
--

LOCK TABLES `inventory` WRITE;
/*!40000 ALTER TABLE `inventory` DISABLE KEYS */;
INSERT INTO `inventory` VALUES (1,94,'pcs',10,1,NULL,'2026-10-14 13:08:49'),(2,89,'pcs',10,1,NULL,'2026-10-14 13:08:49'),(3,98,'pcs',10,1,NULL,'2026-10-14 13:08:49'),(4,48,'pcs',10,1,NULL,'2026-10-14 13:08:49'),(5,100,'pcs',10,0,1,'2026-10-14 12:52:43'),(6,94,'pcs',10,1,NULL,'2026-10-14 13:08:49');
/*!40000 ALTER TABLE `inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_movements`
--

DROP TABLE IF EXISTS `inventory_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('ORDER_DEDUCT','RESTOCK','ADJUSTMENT','WASTE') NOT NULL,
  `quantity_change` int(11) NOT NULL COMMENT 'Positive = add, negative = deduct',
  `reference_order_id` int(11) DEFAULT NULL COMMENT 'Set for checkout deductions',
  `performed_by` int(11) DEFAULT NULL COMMENT 'NULL = system (checkout)',
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_moves_product` (`product_id`,`created_at`),
  KEY `idx_moves_order` (`reference_order_id`),
  KEY `idx_moves_performed_by` (`performed_by`),
  CONSTRAINT `fk_moves_order` FOREIGN KEY (`reference_order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_moves_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_moves_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_moves_qty` CHECK (`quantity_change` <> 0)
) ENGINE=InnoDB AUTO_INCREMENT=198 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_movements`
--

LOCK TABLES `inventory_movements` WRITE;
/*!40000 ALTER TABLE `inventory_movements` DISABLE KEYS */;
INSERT INTO `inventory_movements` VALUES (193,2,'ORDER_DEDUCT',-11,127,NULL,'Order BB-261014-0001','2026-10-14 13:08:49'),(194,1,'ORDER_DEDUCT',-6,127,NULL,'Order BB-261014-0001','2026-10-14 13:08:49'),(195,6,'ORDER_DEDUCT',-6,127,NULL,'Order BB-261014-0001','2026-10-14 13:08:49'),(196,4,'ORDER_DEDUCT',-52,127,NULL,'Order BB-261014-0001','2026-10-14 13:08:49'),(197,3,'ORDER_DEDUCT',-2,127,NULL,'Order BB-261014-0001','2026-10-14 13:08:49');
/*!40000 ALTER TABLE `inventory_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Recipient',
  `order_id` int(11) DEFAULT NULL,
  `type` enum('NEW_ORDER','ORDER_STATUS','LOW_STOCK','GENERAL') NOT NULL DEFAULT 'GENERAL',
  `title` varchar(120) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`,`is_read`,`created_at`),
  KEY `idx_notifications_order` (`order_id`),
  CONSTRAINT `fk_notifications_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=445 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (438,136,127,'NEW_ORDER','Order BB-261014-0001 placed','Thanks! We received your order BB-261014-0001 (₱1,734.00, CASH). Watch this page for status updates.',0,'2026-10-14 13:08:49'),(439,1,127,'NEW_ORDER','New order BB-261014-0001','5 line(s) · ₱1,734.00 · CASH',0,'2026-10-14 13:08:49'),(440,2,127,'NEW_ORDER','New order BB-261014-0001','5 line(s) · ₱1,734.00 · CASH',0,'2026-10-14 13:08:49'),(441,136,127,'ORDER_STATUS','Order BB-261014-0001 — Accepted','Your order BB-261014-0001 is now Accepted.',0,'2026-10-14 13:10:02'),(442,136,127,'ORDER_STATUS','Order BB-261014-0001 — Preparing','Your order BB-261014-0001 is now Preparing.',0,'2026-10-14 13:10:03'),(443,136,127,'ORDER_STATUS','Order BB-261014-0001 — Ready','Your order BB-261014-0001 is now Ready.',0,'2026-10-14 13:11:03'),(444,136,127,'ORDER_STATUS','Order BB-261014-0001 — Completed','Your order BB-261014-0001 is now Completed.',0,'2026-10-14 13:11:04');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL COMMENT 'Snapshot',
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Snapshot of products.price at order time',
  `quantity` int(11) NOT NULL,
  `line_total` decimal(10,2) NOT NULL COMMENT 'unit_price * quantity',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_order_items_qty` CHECK (`quantity` > 0),
  CONSTRAINT `chk_order_items_price` CHECK (`unit_price` >= 0 and `line_total` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=177 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (172,127,2,'Isaw Manok',20.00,11,220.00,'2026-10-14 13:08:49'),(173,127,1,'BBQ',30.00,6,180.00,'2026-10-14 13:08:49'),(174,127,6,'Hotdog',25.00,6,150.00,'2026-10-14 13:08:49'),(175,127,4,'Tenga Baboy',22.00,52,1144.00,'2026-10-14 13:08:49'),(176,127,3,'Bituka Baboy',20.00,2,40.00,'2026-10-14 13:08:49');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_status_history`
--

DROP TABLE IF EXISTS `order_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL COMMENT 'NULL on order creation',
  `new_status` varchar(20) NOT NULL,
  `changed_by` int(11) DEFAULT NULL COMMENT 'NULL = system (checkout)',
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_history_order` (`order_id`,`created_at`),
  KEY `idx_history_changed_by` (`changed_by`),
  CONSTRAINT `fk_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=232 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_status_history`
--

LOCK TABLES `order_status_history` WRITE;
/*!40000 ALTER TABLE `order_status_history` DISABLE KEYS */;
INSERT INTO `order_status_history` VALUES (227,127,NULL,'NEW',NULL,'Order placed','2026-10-14 13:08:49'),(228,127,'NEW','ACCEPTED',2,'','2026-10-14 13:10:02'),(229,127,'ACCEPTED','PREPARING',2,'','2026-10-14 13:10:03'),(230,127,'PREPARING','READY',2,'','2026-10-14 13:11:03'),(231,127,'READY','COMPLETED',2,'','2026-10-14 13:11:04');
/*!40000 ALTER TABLE `order_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `status` enum('NEW','ACCEPTED','PREPARING','READY','COMPLETED','CANCELLED') NOT NULL DEFAULT 'NEW',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('CASH','GCASH') NOT NULL DEFAULT 'CASH',
  `notes` varchar(255) DEFAULT NULL COMMENT 'Customer instructions',
  `handled_by` int(11) DEFAULT NULL COMMENT 'Staff who accepted/completed the order',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_number` (`order_number`),
  KEY `idx_orders_customer` (`customer_id`,`created_at`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_created` (`created_at`),
  KEY `fk_orders_handled_by` (`handled_by`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_handled_by` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_orders_totals` CHECK (`subtotal` >= 0 and `total` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (127,'BB-261014-0001',136,'COMPLETED',1734.00,1734.00,'CASH','',2,'2026-10-14 13:08:49','2026-10-14 13:11:04');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_records`
--

DROP TABLE IF EXISTS `otp_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otp_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `purpose` enum('LOGIN') NOT NULL DEFAULT 'LOGIN',
  `otp_hash` varchar(255) NOT NULL COMMENT 'password_hash(code) — never plaintext',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 3,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL COMMENT 'Set on successful verification (single-use)',
  `resend_after` datetime NOT NULL COMMENT 'Cooldown gate before next request',
  `request_ip` varchar(45) DEFAULT NULL COMMENT 'Rate limiting / audit',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otp_phone_created` (`phone`,`created_at`),
  KEY `idx_otp_expires` (`expires_at`),
  CONSTRAINT `chk_otp_attempts` CHECK (`attempts` >= 0 and `max_attempts` >= 1)
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_records`
--

LOCK TABLES `otp_records` WRITE;
/*!40000 ALTER TABLE `otp_records` DISABLE KEYS */;
INSERT INTO `otp_records` VALUES (152,'09384255525','LOGIN','$2y$10$bxtqBdrKZdO1DikW94JRYeFSGnOvsoZIGKx2I2FIpB3Baa0ImTe5a',1,3,'2026-10-14 13:11:21','2026-10-14 13:06:43','2026-10-14 13:07:21','::1','2026-10-14 13:06:21'),(153,'09384255525','LOGIN','$2y$10$Qz5wMjn3ptI8ZNkjMavdRekXLyXhDgKhgLMQcQh7itorszcgP9D/W',1,3,'2026-10-14 13:12:27','2026-10-14 13:07:31','2026-10-14 13:08:27','::1','2026-10-14 13:07:27'),(154,'09384255525','LOGIN','$2y$10$IZU6Kn6SW.R7UADQeKbB7OZGUqnSrXlZIVVg2WFSltQNN.fxX2ya.',1,3,'2026-10-14 13:15:18','2026-10-14 13:10:22','2026-10-14 13:11:18','::1','2026-10-14 13:10:18');
/*!40000 ALTER TABLE `otp_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL COMMENT 'Authoritative selling price in PHP',
  `image` varchar(255) DEFAULT NULL COMMENT 'Path under assets/images or uploads',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = hidden and unorderable',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_products_category_active` (`category_id`,`is_active`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_products_price` CHECK (`price` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,2,'BBQ','Marinated and grilled barbecue served fresh and suitable as a meal or snack.',30.00,'assets/images/products/bbq.svg',1,'2026-10-07 22:20:20','2026-10-07 23:03:09'),(2,1,'Isaw Manok','Grilled chicken intestines prepared in the traditional Filipino ihaw-ihaw style.',20.00,'assets/images/products/isaw-manok.svg',1,'2026-10-07 22:20:20','2026-10-07 23:03:09'),(3,2,'Bituka Baboy','Grilled pork intestines prepared and seasoned for a flavorful street-food experience.',20.00,'assets/images/products/bituka-baboy.svg',1,'2026-10-07 22:20:20','2026-10-07 23:03:09'),(4,2,'Tenga Baboy','Grilled pork ears served as an affordable Filipino grilled-food option.',22.00,'assets/images/products/tenga-baboy.svg',1,'2026-10-07 22:20:20','2026-10-07 23:03:09'),(5,1,'Balunan','Grilled chicken gizzard prepared and served fresh.',20.00,'assets/images/products/balunan.svg',1,'2026-10-07 22:20:20','2026-10-09 11:04:53'),(6,3,'Hotdog','Grilled hotdog served as a convenient and affordable snack.',25.00,'assets/images/products/hotdog.svg',1,'2026-10-07 22:20:20','2026-10-09 11:04:47');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(40) NOT NULL COMMENT 'e.g. otp_request, otp_verify, staff_login',
  `identifier` varchar(64) NOT NULL COMMENT 'phone number or IP address',
  `window_start` datetime NOT NULL COMMENT 'Start of the fixed window',
  `attempts` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_limits` (`action`,`identifier`,`window_start`)
) ENGINE=InnoDB AUTO_INCREMENT=964 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limits`
--

LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
INSERT INTO `rate_limits` VALUES (931,'staff_login','ph:09170000001','2026-10-14 12:45:00',1,'2026-10-14 12:51:01'),(932,'staff_login','ip:::1','2026-10-14 12:45:00',3,'2026-10-14 12:51:01'),(933,'staff_login','ph:09170000002','2026-10-14 12:45:00',2,'2026-10-14 12:54:15'),(937,'otp_request','ph:09384255525','2026-10-14 13:00:00',3,'2026-10-14 13:06:21'),(938,'otp_request','ip:::1','2026-10-14 13:00:00',3,'2026-10-14 13:06:21'),(939,'otp_verify','ph:09384255525','2026-10-14 13:00:00',3,'2026-10-14 13:06:43'),(943,'staff_login','ph:09170000001','2026-10-14 13:00:00',3,'2026-10-14 13:09:31'),(944,'staff_login','ip:::1','2026-10-14 13:00:00',6,'2026-10-14 13:09:31'),(947,'staff_login','ph:09170000002','2026-10-14 13:00:00',3,'2026-10-14 13:09:56'),(958,'staff_login','ph:09170000001','2026-10-18 15:45:00',1,'2026-10-18 15:48:18'),(959,'staff_login','ip:::1','2026-10-18 15:45:00',1,'2026-10-18 15:48:18'),(960,'email_setup','manager:1','2026-10-18 15:00:00',2,'2026-10-18 15:50:24'),(961,'email_setup','ip:::1','2026-10-18 15:00:00',2,'2026-10-18 15:50:24');
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receipt_email_deliveries`
--

DROP TABLE IF EXISTS `receipt_email_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipt_email_deliveries` (
  `order_id` int(11) NOT NULL,
  `recipient` varchar(254) DEFAULT NULL,
  `status` enum('PENDING','SENT','FAILED','SKIPPED') NOT NULL DEFAULT 'PENDING',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `last_error` varchar(40) DEFAULT NULL,
  `next_attempt_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`order_id`),
  KEY `idx_receipt_email_pending` (`status`,`next_attempt_at`),
  CONSTRAINT `fk_receipt_email_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receipt_email_deliveries`
--

LOCK TABLES `receipt_email_deliveries` WRITE;
/*!40000 ALTER TABLE `receipt_email_deliveries` DISABLE KEYS */;
/*!40000 ALTER TABLE `receipt_email_deliveries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receipts`
--

DROP TABLE IF EXISTS `receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `receipt_number` varchar(20) NOT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `payment_method` varchar(10) NOT NULL DEFAULT 'CASH',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipts_order` (`order_id`),
  UNIQUE KEY `uq_receipts_number` (`receipt_number`),
  CONSTRAINT `fk_receipts_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_receipts_total` CHECK (`total` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receipts`
--

LOCK TABLES `receipts` WRITE;
/*!40000 ALTER TABLE `receipts` DISABLE KEYS */;
INSERT INTO `receipts` VALUES (109,127,'RC-261014-0001','2026-10-14 13:08:49',1734.00,'CASH');
/*!40000 ALTER TABLE `receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('email_staff_defaults_v1','1','2026-10-18 15:58:47'),('otp_delivery_mode','debug','2026-10-07 22:20:20'),('otp_expiry_minutes','5','2026-10-07 22:20:20'),('otp_max_attempts','3','2026-10-07 22:20:20'),('otp_resend_cooldown','60','2026-10-07 22:20:20'),('store_location','Greensite Compound, Sta. Lucia, Pasig City','2026-10-07 22:20:20'),('store_name','BulkBites BBQ','2026-10-07 22:20:20');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL COMMENT 'Staff login or optional legacy customer contact',
  `role` enum('CUSTOMER','STAFF','MANAGER') NOT NULL DEFAULT 'CUSTOMER',
  `password_hash` varchar(255) DEFAULT NULL COMMENT 'password_hash() bcrypt; NULL for OTP-only customers',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = deactivated, logged out on next request',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email` varchar(254) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  CONSTRAINT `chk_users_phone` CHECK (`phone` regexp '^09[0-9]{9}$')
) ENGINE=InnoDB AUTO_INCREMENT=140 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'BulkBites Manager','09170000001','MANAGER',NULL,0,'2026-10-07 22:20:20','2026-10-18 15:58:47',NULL),(2,'BulkBites Staff','09170000002','STAFF',NULL,0,'2026-10-07 22:20:20','2026-10-18 15:58:47',NULL),(136,'Customer 5525','09384255525','CUSTOMER',NULL,1,'2026-10-14 13:06:43','2026-10-14 13:06:43',NULL),(137,'Khristian Maenard Estayani',NULL,'MANAGER',NULL,1,'2026-10-18 15:58:47','2026-10-18 15:58:47','estayani_khristianmaenard@plpasig.edu.ph'),(138,'William Jake Casas',NULL,'MANAGER',NULL,1,'2026-10-18 15:58:47','2026-10-18 15:58:47','casas_williamjake@plpasig.edu.ph'),(139,'Jullian Matthew Magtapat',NULL,'MANAGER',NULL,1,'2026-10-18 15:58:47','2026-10-18 15:58:47','magtapat_jullianmatthew@plpasig.edu.ph');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
