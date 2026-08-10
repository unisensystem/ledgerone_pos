-- =====================================================
-- Customer Types Database Migration
-- =====================================================

-- Step 1: Create customer_types table
CREATE TABLE IF NOT EXISTS `customer_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) NOT NULL DEFAULT 0 COMMENT '0 = System, >0 = Tenant specific',
  `type_name` VARCHAR(100) NOT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `updated_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_type_per_tenant` (`tenant_id`, `type_name`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Insert default system customer types (tenant_id = 0)
INSERT INTO `customer_types` (`id`, `tenant_id`, `type_name`, `created_by`, `updated_by`) VALUES
(1, 0, 'Retail', NULL, NULL),
(2, 0, 'Wholesale', NULL, NULL),
(3, 0, 'Distributor', NULL, NULL),
(4, 0, 'Corporate', NULL, NULL),
(5, 0, 'Government', NULL, NULL),
(6, 0, 'Individual', NULL, NULL);

-- Step 3: Add customer_type_id column to customers table (if not exists)
ALTER TABLE `customers` 
ADD COLUMN `customer_type_id` INT(11) DEFAULT NULL AFTER `company_id`,
ADD KEY `idx_customer_type_id` (`customer_type_id`);

-- Step 4: Add foreign key constraint (optional - for data integrity)
ALTER TABLE `customers`
ADD CONSTRAINT `fk_customers_customer_type` 
FOREIGN KEY (`customer_type_id`) 
REFERENCES `customer_types` (`id`) 
ON DELETE SET NULL 
ON UPDATE CASCADE;

-- =====================================================
-- Verification Queries
-- =====================================================

-- Check if customer_types table exists
SELECT TABLE_NAME 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'customer_types';

-- Check system customer types
SELECT * FROM customer_types WHERE tenant_id = 0;

-- Check if customer_type_id column exists in customers table
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'customers'
AND COLUMN_NAME = 'customer_type_id';

-- =====================================================
-- Rollback Queries (if needed)
-- =====================================================

-- Remove foreign key constraint
-- ALTER TABLE `customers` DROP FOREIGN KEY `fk_customers_customer_type`;

-- Remove customer_type_id column from customers table
-- ALTER TABLE `customers` DROP COLUMN `customer_type_id`;

-- Drop customer_types table
-- DROP TABLE IF EXISTS `customer_types`;
