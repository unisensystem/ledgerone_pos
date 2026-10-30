-- Create product-specific selling units, pack conversions, prices, and barcodes.
CREATE TABLE IF NOT EXISTS `product_sub_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `label` varchar(100) NOT NULL,
  `qty_in_base` decimal(18,6) NOT NULL,
  `price` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `barcode` varchar(100) DEFAULT NULL,
  `is_custom` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_psu_tenant_product` (`tenant_id`, `product_id`),
  KEY `idx_psu_tenant_barcode` (`tenant_id`, `barcode`),
  CONSTRAINT `fk_psu_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
