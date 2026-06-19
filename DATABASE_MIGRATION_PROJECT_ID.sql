-- ============================================================================
-- DATABASE MIGRATION: Add project_id Column to Purchase Tables
-- ============================================================================
-- This migration adds project_id column to support project tracking in:
-- 1. purchase_invoice (Regular Purchase Invoice)
-- 2. purchase_tax_invoice (Purchase Tax Invoice)
-- 3. purchase_order (Purchase Order)
-- 4. purchase_return (Purchase Return)
-- ============================================================================

-- 1. Add project_id to purchase_invoice table
ALTER TABLE purchase_invoice 
ADD COLUMN project_id INT NULL AFTER sub_account_id;

-- Add foreign key constraint for purchase_invoice
ALTER TABLE purchase_invoice
ADD CONSTRAINT fk_purchase_invoice_project 
FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

-- ============================================================================

-- 2. Add project_id to purchase_tax_invoice table
ALTER TABLE purchase_tax_invoice 
ADD COLUMN project_id INT NULL AFTER sub_account_id;

-- Add foreign key constraint for purchase_tax_invoice
ALTER TABLE purchase_tax_invoice
ADD CONSTRAINT fk_purchase_tax_invoice_project 
FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

-- ============================================================================

-- 3. Add project_id to purchase_order table
ALTER TABLE purchase_order 
ADD COLUMN project_id INT NULL AFTER currency_id;

-- Add foreign key constraint for purchase_order
ALTER TABLE purchase_order
ADD CONSTRAINT fk_purchase_order_project 
FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

-- ============================================================================

-- 4. Add project_id to purchase_return table
ALTER TABLE purchase_return 
ADD COLUMN project_id INT NULL AFTER sub_account_id;

-- Add foreign key constraint for purchase_return
ALTER TABLE purchase_return
ADD CONSTRAINT fk_purchase_return_project 
FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

-- ============================================================================
-- Verification Queries
-- ============================================================================

-- Verify project_id columns were added
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() 
AND COLUMN_NAME = 'project_id'
AND TABLE_NAME IN ('purchase_invoice', 'purchase_tax_invoice', 'purchase_order', 'purchase_return')
ORDER BY TABLE_NAME;

-- Verify foreign key constraints
SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'project_id'
AND REFERENCED_TABLE_NAME = 'projects'
ORDER BY TABLE_NAME;
