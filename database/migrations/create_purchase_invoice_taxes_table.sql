-- Migration: Create purchase_invoice_taxes table
CREATE TABLE IF NOT EXISTS `purchase_invoice_taxes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `purchase_invoice_id` int(11) NOT NULL,
  `tax_regime_id` int(11) DEFAULT NULL,
  `tax_name` varchar(255) NOT NULL,
  `rate_percentage` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `base_amount` decimal(15,3) NOT NULL DEFAULT 0.000,
  `tax_amount` decimal(15,3) NOT NULL DEFAULT 0.000,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pit_invoice_id` (`purchase_invoice_id`),
  KEY `idx_pit_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_pit_purchase_invoice` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_invoice` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
