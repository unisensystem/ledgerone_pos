-- Wastage Entry Tables
-- Run this against your tenant database

CREATE TABLE `production_wastage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `wastage_no` varchar(50) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `wastage_date` date NOT NULL,
  `wastage_type` enum('percentage','quantity') NOT NULL DEFAULT 'quantity',
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `production_order_id` (`production_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `production_wastage_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wastage_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `uom_id` int(11) NOT NULL,
  `ordered_qty` decimal(10,2) NOT NULL DEFAULT 0.00,
  `wastage_input` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `wastage_qty` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `wastage_id` (`wastage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
