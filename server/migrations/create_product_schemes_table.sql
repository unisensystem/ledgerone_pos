-- Migration: Create product_schemes table for promotional schemes
-- This table stores promotional schemes for products with support for both Default Unit and UOM Group modes

CREATE TABLE IF NOT EXISTS `product_schemes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `promo_qty` int(11) NOT NULL DEFAULT 0 COMMENT 'Free items (FOC) when customer buys promo_qty',
  `bonus_qty` int(11) NOT NULL DEFAULT 0 COMMENT 'Additional bonus items',
  `to_qty` int(11) NOT NULL DEFAULT 0 COMMENT 'Trade Offer - Minimum quantity condition',
  `to_rs` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Trade Offer - Discount amount in rupees',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_scheme` (`tenant_id`, `product_id`, `unit_id`, `promo_qty`, `to_qty`),
  KEY `idx_product_unit` (`product_id`, `unit_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_product_schemes_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_schemes_unit` FOREIGN KEY (`unit_id`) REFERENCES `uom` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_schemes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example data:
-- For Default Unit mode (single unit per product):
-- INSERT INTO product_schemes (tenant_id, product_id, unit_id, promo_qty, bonus_qty, to_qty, to_rs)
-- VALUES (1, 5, 9, 10, 2, 0, 0);  -- Buy 10 Pcs, get 2 free

-- For UOM Group mode (multiple units per product):
-- INSERT INTO product_schemes (tenant_id, product_id, unit_id, promo_qty, bonus_qty, to_qty, to_rs)
-- VALUES (1, 5, 9, 10, 2, 0, 0);   -- Pcs: Buy 10, get 2 free
-- INSERT INTO product_schemes (tenant_id, product_id, unit_id, promo_qty, bonus_qty, to_qty, to_rs)
-- VALUES (1, 5, 16, 1, 0, 5, 50);  -- Ctn: Buy 5 Ctn, get ₹50 discount
