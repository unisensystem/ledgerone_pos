-- Migration: Create sale_invoice_taxes table
-- Stores invoice-level tax breakdowns (taxes applied at the invoice summary level)

CREATE TABLE IF NOT EXISTS `sale_invoice_taxes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `sale_invoice_id` int(11) NOT NULL,
  `tax_regime_id` int(11) DEFAULT NULL,
  `tax_rate_id` int(11) DEFAULT NULL,
  `tax_name` varchar(255) NOT NULL,
  `rate_percentage` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `base_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sit_sale_invoice_id` (`sale_invoice_id`),
  KEY `idx_sit_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sit_sale_invoice` FOREIGN KEY (`sale_invoice_id`) REFERENCES `sale_invoice` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
