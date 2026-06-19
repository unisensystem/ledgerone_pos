-- ========================================
-- Database Migration: Add Project Column
-- ========================================
-- This script adds project_id column to tables where project data will be saved
-- Run these queries in order

-- 1. ADD PROJECT_ID TO CUSTOMERS TABLE
-- =====================================
ALTER TABLE customers 
ADD COLUMN project_id INT UNSIGNED NULL AFTER supplier_man_id,
ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

-- 2. ADD PROJECT_ID TO SALE_INVOICE TABLE (IF NOT EXISTS)
-- ==========================================================
-- Note: This column should already exist in most installations
-- If it doesn't exist, run this:
ALTER TABLE sale_invoice 
ADD COLUMN project_id INT UNSIGNED NULL AFTER transport_name,
ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

-- 3. ADD PROJECT_ID TO DELIVERY_CHALAN TABLE
-- ============================================
ALTER TABLE delivery_chalan 
ADD COLUMN project_id INT UNSIGNED NULL AFTER transport_name,
ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

-- 4. VERIFY COLUMNS WERE ADDED
-- =============================
-- Customers table
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'customers' AND COLUMN_NAME = 'project_id';

-- Sale Invoice table
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'sale_invoice' AND COLUMN_NAME = 'project_id';

-- Delivery Chalan table
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'delivery_chalan' AND COLUMN_NAME = 'project_id';

-- 5. CREATE INDEX FOR BETTER QUERY PERFORMANCE
-- =============================================
-- For Customers
ALTER TABLE customers ADD INDEX idx_project_id (project_id);

-- For Sale Invoice
ALTER TABLE sale_invoice ADD INDEX idx_project_id (project_id);

-- For Delivery Chalan
ALTER TABLE delivery_chalan ADD INDEX idx_project_id (project_id);

-- ========================================
-- STATUS: All project columns added ✅
-- ========================================
-- The following will now save project_id:
-- 1. Customer Add Form
-- 2. POS Counter Invoice
-- 3. Delivery Chalan

-- You can verify the data is being saved by running:
-- SELECT id, customer_name, project_id FROM customers WHERE project_id IS NOT NULL LIMIT 10;
-- SELECT bill_no, project_id FROM sale_invoice WHERE project_id IS NOT NULL LIMIT 10;
-- SELECT chalan_no, project_id FROM delivery_chalan WHERE project_id IS NOT NULL LIMIT 10;
