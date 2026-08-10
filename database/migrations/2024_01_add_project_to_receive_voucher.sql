-- Migration: Add Project Support to Receive Voucher
-- Date: 2024
-- Description: Adds project_id column and relationships to receive_voucher table

-- Step 1: Add project_id column to receive_voucher table
ALTER TABLE `receive_voucher` 
ADD COLUMN `project_id` INT NULL DEFAULT NULL AFTER `dsr_no`;

-- Step 2: Add foreign key constraint to projects table
ALTER TABLE `receive_voucher` 
ADD CONSTRAINT `fk_receive_voucher_project_id` 
FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) 
ON DELETE SET NULL 
ON UPDATE CASCADE;

-- Step 3: Create index for performance optimization
CREATE INDEX `idx_receive_voucher_project_id` ON `receive_voucher` (`project_id`);

-- Step 4: Create composite index for common queries
CREATE INDEX `idx_receive_voucher_tenant_project` ON `receive_voucher` (`tenant_id`, `project_id`);

-- Step 5: Verify the column was added
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'receive_voucher' AND COLUMN_NAME = 'project_id';
