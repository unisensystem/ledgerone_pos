-- Production Expenses Improvements Migration
-- Run this to add essential fields to production expenses

-- Add cost centers table
CREATE TABLE IF NOT EXISTS cost_centers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_code (tenant_id, code)
);

-- Add essential fields to production_expenses
ALTER TABLE production_expenses
ADD COLUMN IF NOT EXISTS status ENUM('Draft', 'Approved', 'Posted') DEFAULT 'Draft' AFTER expense_number,
ADD COLUMN IF NOT EXISTS expense_date DATE NOT NULL DEFAULT (CURRENT_DATE) AFTER status,
ADD COLUMN IF NOT EXISTS posting_date DATE NULL AFTER expense_date,
ADD COLUMN IF NOT EXISTS cost_center_id INT NULL AFTER production_order_id,
ADD COLUMN IF NOT EXISTS approved_by INT NULL AFTER notes,
ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL AFTER approved_by,
ADD COLUMN IF NOT EXISTS posted_by INT NULL AFTER approved_at,
ADD COLUMN IF NOT EXISTS posted_at TIMESTAMP NULL AFTER posted_by;

-- Add cost_element_category to production_expense_accounts
ALTER TABLE production_expense_accounts
ADD COLUMN IF NOT EXISTS cost_element_category ENUM('Material', 'Labor', 'Machine', 'Overhead', 'Quality', 'Other') DEFAULT 'Other' AFTER expense_account_id,
ADD COLUMN IF NOT EXISTS quantity DECIMAL(10,2) NULL AFTER cost_element_category,
ADD COLUMN IF NOT EXISTS unit_price DECIMAL(10,2) NULL AFTER quantity,
ADD COLUMN IF NOT EXISTS description VARCHAR(255) NULL AFTER amount;

-- Insert default cost centers
INSERT INTO cost_centers (tenant_id, code, name, department) VALUES
(1, 'CC-PROD-01', 'Production Department', 'Manufacturing'),
(1, 'CC-MAINT-01', 'Maintenance Department', 'Support'),
(1, 'CC-QC-01', 'Quality Control', 'Quality'),
(1, 'CC-WARE-01', 'Warehouse', 'Logistics')
ON DUPLICATE KEY UPDATE name=name;

-- Add indexes for better performance
ALTER TABLE production_expenses
ADD INDEX idx_status (status),
ADD INDEX idx_expense_date (expense_date),
ADD INDEX idx_cost_center (cost_center_id);
