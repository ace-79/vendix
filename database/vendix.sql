
-- Database: `sales_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `old_value` json DEFAULT NULL,
  `new_value` json DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_user_id` (`user_id`),
  KEY `idx_logs_action` (`action_type`),
  KEY `idx_logs_entity` (`entity_type`,`entity_id`),
  KEY `idx_logs_created_at` (`created_at`)
) ENGINE=MyISAM AUTO_INCREMENT=301 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `phone`, `email`) VALUES
(1, 'Ahmed Benali', '0600076501', 'ahmed@mail.com'),
(2, 'Sara El Amrani', '0600000002', 'sara@mail.com'),
(3, 'Youssef Karim', '0600000003', 'youssefkarim@gmail.com'),
(4, 'Fatima Zahra', '0600000004', 'fatima@mail.com'),
(9, 'zakaria test', '0708807293', 'zakaria@gmail.com'),
(10, 'nabil nickname', '0610202938', 'nabil@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `paid_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `sku` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `barcode` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `min_stock` int NOT NULL DEFAULT '0',
  `supplier_id` int DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `image_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `sku`, `barcode`, `price`, `cost_price`, `stock`, `min_stock`, `supplier_id`, `category`, `image_url`, `status`) VALUES
(1, 'Laptop HP', 'SKU-00001', 'BC-0000137', 5000.00, 3000.00, 23, 10, NULL, 'Electronics', '/Vendix/assets/images/uploads/products/laptop_hp.jfif', 'active'),
(2, 'Mouse Logitech', 'SKU-00002', 'BC-0000271', 150.00, 80.00, 16, 10, NULL, 'Accessories', '/Vendix/assets/images/uploads/products/mouse.jfif', 'active'),
(3, 'Keyboard RGB', 'SKU-00003', 'BC-0000360', 300.00, 180.00, 32, 5, NULL, 'Accessories', '/Vendix/assets/images/uploads/products/keyboard_rgb.jfif', 'active'),
(4, 'USB Flash 64GB', 'SKU-00004', 'BC-0000487', 120.00, 70.00, 27, 10, NULL, 'Storage', '/Vendix/assets/images/uploads/products/USB 3 0 Flash .jfif', 'active'),
(5, 'Smartphone Samsung', 'SKU-00005', 'BC-0000542', 2000.00, 1200.00, 7, 2, NULL, 'Electronics', '/Vendix/assets/images/uploads/products/Samsung galaxy ultra.jfif', 'active'),
(7, 'Iphone 17pro max', 'SKU-00007', 'BC-0000757', 8500.00, 4000.00, 21, 17, NULL, 'Electronics', '/Vendix/assets/images/uploads/products/iphone_17_pro_max_20260508_183658_1fdf5159.jfif', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `supplier_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `order_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `expected_date` date DEFAULT NULL,
  `received_date` datetime DEFAULT NULL,
  `status` enum('draft','ordered','partially_received','received','cancelled') COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `total_cost` decimal(10,2) DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_general_ci,
  `reference_number` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity_ordered` int NOT NULL,
  `quantity_received` int DEFAULT '0',
  `unit_cost` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `permission_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `is_allowed` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_perm` (`role_name`,`permission_key`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_name`, `permission_key`, `is_allowed`) VALUES
(1, 'manager', 'view_dashboard', 1),
(2, 'manager', 'view_pos', 0),
(3, 'manager', 'view_sales', 0),
(4, 'manager', 'view_products', 1),
(5, 'manager', 'view_customers', 1),
(6, 'manager', 'view_reports', 1),
(7, 'manager', 'view_logs', 0),
(8, 'manager', 'manage_users', 0),
(9, 'manager', 'manage_settings', 1),
(10, 'cashier', 'view_dashboard', 1),
(11, 'cashier', 'view_pos', 1),
(12, 'cashier', 'view_sales', 1),
(13, 'cashier', 'view_products', 1),
(14, 'cashier', 'view_customers', 0),
(15, 'cashier', 'view_reports', 0),
(16, 'cashier', 'view_logs', 0),
(17, 'cashier', 'manage_users', 0),
(18, 'cashier', 'manage_settings', 1),
(19, 'manager', 'view_stock', 1),
(20, 'manager', 'adjust_stock', 0),
(21, 'manager', 'manage_suppliers', 1),
(22, 'manager', 'view_purchase_orders', 0),
(23, 'manager', 'create_purchase_orders', 0),
(24, 'manager', 'receive_purchase_orders', 0),
(25, 'cashier', 'view_stock', 0),
(26, 'cashier', 'adjust_stock', 0),
(27, 'cashier', 'manage_suppliers', 0),
(28, 'cashier', 'view_purchase_orders', 0),
(29, 'cashier', 'create_purchase_orders', 0),
(30, 'cashier', 'receive_purchase_orders', 0),
(31, 'inventory', 'view_dashboard', 1),
(32, 'inventory', 'view_pos', 0),
(33, 'inventory', 'view_sales', 0),
(34, 'inventory', 'view_products', 1),
(35, 'inventory', 'view_stock', 1),
(36, 'inventory', 'adjust_stock', 1),
(37, 'inventory', 'manage_suppliers', 0),
(38, 'inventory', 'view_purchase_orders', 1),
(39, 'inventory', 'create_purchase_orders', 1),
(40, 'inventory', 'receive_purchase_orders', 1),
(41, 'inventory', 'view_customers', 0),
(42, 'inventory', 'view_reports', 0),
(43, 'inventory', 'view_logs', 0),
(44, 'inventory', 'manage_users', 0),
(45, 'inventory', 'manage_settings', 1);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
CREATE TABLE IF NOT EXISTS `sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `sale_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `payment_status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_general_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'app_name', 'Vendix', '2026-05-04 17:18:49'),
(2, 'address', '123 Business St, City', '2026-05-04 17:18:49'),
(3, 'phone', '555-0123', '2026-05-04 17:18:49'),
(4, 'email', 'contact@vendix.com', '2026-05-04 17:18:49'),
(5, 'currency', '$', '2026-05-04 17:18:49'),
(6, 'auto_email_invoices', '1', '2026-05-04 19:13:02'),
(7, 'smtp_host', 'smtp.gmail.com', '2026-05-04 19:24:15'),
(8, 'smtp_port', '587', '2026-05-04 19:24:20'),
(9, 'smtp_user', 'example@gmail.com', '2026-05-04 19:24:32'),
(10, 'smtp_pass', 'app-password', '2026-05-04 19:29:48');

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

DROP TABLE IF EXISTS `stock_adjustments`;
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `adjustment_type` enum('damage','theft','count_correction','return','other') COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` int NOT NULL,
  `reason` text COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `movement_type` enum('sale','purchase','adjustment','return','cancel_restore') COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` int NOT NULL,
  `stock_before` int NOT NULL,
  `stock_after` int NOT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `movement_type` (`movement_type`),
  KEY `reference` (`reference_type`,`reference_id`),
  KEY `created_at` (`created_at`),
  KEY `sm_user_fk` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `contact_person` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `status`, `created_at`) VALUES
(1, 'TechParts Morocco', 'Ahmed', '0600112233', 'info@techparts.ma', '', 'active', '2026-05-05 21:27:11'),
(2, 'VenTech', 'zakaria', '0712345678', 'example@gmail.com', '', 'active', '2026-05-08 21:59:20');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('active','blocked') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `last_seen` datetime DEFAULT NULL,
  `force_logout` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `status`, `last_seen`, `force_logout`) VALUES
(1, 'admin', '$2y$10$qWaQNSeTBLYsCjwckUeJ8u7r3jUl9GGcflFtlsEZO6nJosUyzMDby', 'Admin', 'active', '2026-05-08 23:05:25', 0),
(2, 'seller3', '$2y$10$Yz3//NpqjisVAcQJCpPHBO3QZ1KtFQt6mE4JcMf4mjv.I/SiJjH.q', 'cashier', 'active', NULL, 0),
(3, 'manager1', '$2y$10$eAzrwvPNM8OsUj7oXztyw.578Zzzch3hXYz6e9VlugaZWmzuaitX2', 'Manager', 'active', NULL, 0),
(5, 'seller1', '$2y$10$c5KNbbIoHIr9w/53USh1quTKwe2tjY/fXWtUkWdisCX5NfCDN6OkS', 'cashier', 'active', '2026-05-06 22:28:59', 0),
(6, 'manager2', '$2y$10$O4.SJv/e7UzRi6zNe1.WweSGh1z9SRMe8G8OKHDrIQbhOKeRbW7ES', 'inventory', 'active', NULL, 0),
(9, 'seller2', '$2y$10$Qrp0W4Bgjd80rbaILHuCtuUY56nknUbjOf8P7EbE/Z6tcjFMsDM8K', 'cashier', 'active', NULL, 1),
(10, 'manager4', '$2y$10$y4MrlZ8ELyMjrcVnXosqKutoQp5mihUI9EZMvHzbA3I1HJCeFqqU.', 'inventory', 'active', NULL, 0),
(11, 'manager3', '$2y$10$Rps0KlkAhIQxA3RMqEWLi.topH6F8YBvAh9C7aimm1QrsSID.rDae', 'manager', 'active', NULL, 0);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `po_supplier_fk` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `po_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `poi_po_fk` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `poi_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD CONSTRAINT `sa_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `sa_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `sm_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `sm_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
