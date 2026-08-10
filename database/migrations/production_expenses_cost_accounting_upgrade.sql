-- =====================================================
-- Production Expenses Cost Accounting Upgrade
-- =====================================================

-- 1. Add missing fields to production_expenses table
ALTER TABLE production_expenses
ADD COLUMN expense_date DATE NOT NULL AFTER expense_number,
ADD COLUMN status ENUM('Draft', 'Pending', 'Posted', 'Reversed') DEFAULT 'Draft' AFTER expense_type,
ADD COLUMN vendor_id INT NULL AFTER production_order_id,
ADD COLUMN cost_center_id INT NULL AFTER vendor_id,
ADD COLUMN expense_category ENUM('Materials', 'Labor', 'Utilities', 'Rent', 'Depreciation', 'Maintenance', 'Other') NULL AFTER expense_type,
ADD COLUMN payment_status ENUM('Unpaid', 'Partial', 'Paid') DEFAULT 'Unpaid' AFTER status,
ADD COLUMN payment_date DATE NULL AFTER payment_status,
ADD COLUMN tax_amount DECIMAL(15,2) DEFAULT 0.00 AFTER total_amount,
ADD COLUMN currency_code VARCHAR(3) DEFAULT 'USD' AFTER tax_amount,
ADD COLUMN journal_entry_id INT NULL AFTER currency_code,
ADD COLUMN approved_by INT NULL AFTER created_by,
ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
ADD COLUMN updated_by INT NULL AFTER approved_at,
ADD COLUMN updated_at DATETIME NULL AFTER updated_by,
ADD COLUMN reversed_by INT NULL AFTER updated_at,
ADD COLUMN reversed_at DATETIME NULL AFTER reversed_by,
ADD COLUMN reversal_reason TEXT NULL AFTER reversed_at,
ADD INDEX idx_expense_date (expense_date),
ADD INDEX idx_status (status),
ADD INDEX idx_vendor (vendor_id),
ADD INDEX idx_journal_entry (journal_entry_id);

-- 2. Add quantity and unit price to production_expense_accounts
ALTER TABLE production_expense_accounts
ADD COLUMN quantity DECIMAL(15,4) DEFAULT 1.0000 AFTER expense_account_id,
ADD COLUMN unit_price DECIMAL(15,2) DEFAULT 0.00 AFTER quantity,
ADD COLUMN description VARCHAR(255) NULL AFTER amount;

-- 3. Create journal_entries table (if not exists)
CREATE TABLE IF NOT EXISTS journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    entry_number VARCHAR(50) NOT NULL,
    entry_date DATE NOT NULL,
    entry_type ENUM('Standard', 'Adjusting', 'Closing', 'Reversing') DEFAULT 'Standard',
    reference_type VARCHAR(50) NULL COMMENT 'production_expense, production_order, etc',
    reference_id INT NULL,
    description TEXT NULL,
    total_debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_credit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status ENUM('Draft', 'Posted', 'Reversed') DEFAULT 'Draft',
    posted_by INT NULL,
    posted_at DATETIME NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_entry_date (entry_date),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_entry_number (tenant_id, entry_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create journal_entry_lines table (if not exists)
CREATE TABLE IF NOT EXISTS journal_entry_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(15,2) DEFAULT 0.00,
    credit DECIMAL(15,2) DEFAULT 0.00,
    description TEXT NULL,
    cost_center_id INT NULL,
    line_number INT NOT NULL,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    INDEX idx_account (account_id),
    INDEX idx_cost_center (cost_center_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Create cost_centers table (if not exists)
CREATE TABLE IF NOT EXISTS cost_centers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    UNIQUE KEY unique_code (tenant_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Create expense_allocation_rules table
CREATE TABLE IF NOT EXISTS expense_allocation_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    rule_name VARCHAR(100) NOT NULL,
    expense_type ENUM('Direct', 'Indirect') NOT NULL,
    allocation_basis ENUM('Labor_Hours', 'Machine_Hours', 'Units_Produced', 'Direct_Labor_Cost', 'Equal') NOT NULL,
    source_account_id INT NOT NULL COMMENT 'Expense account to allocate from',
    target_account_id INT NOT NULL COMMENT 'WIP or overhead account to allocate to',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_expense_type (expense_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Create expense_allocations table (tracks actual allocations)
CREATE TABLE IF NOT EXISTS expense_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    production_expense_id INT NOT NULL,
    production_order_id INT NOT NULL,
    allocation_rule_id INT NULL,
    allocated_amount DECIMAL(15,2) NOT NULL,
    allocation_percentage DECIMAL(5,2) NULL,
    allocation_basis_value DECIMAL(15,4) NULL COMMENT 'Hours, units, etc',
    journal_entry_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_expense_id) REFERENCES production_expenses(id) ON DELETE CASCADE,
    INDEX idx_production_order (production_order_id),
    INDEX idx_journal_entry (journal_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Create vendors table (if not exists)
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    vendor_code VARCHAR(20) NOT NULL,
    vendor_name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100) NULL,
    email VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    payment_terms VARCHAR(50) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    UNIQUE KEY unique_vendor_code (tenant_id, vendor_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Add WIP account tracking to production_orders (if not exists)
ALTER TABLE production_orders
ADD COLUMN IF NOT EXISTS wip_account_id INT NULL COMMENT 'Work in Progress Inventory Account',
ADD COLUMN IF NOT EXISTS accumulated_cost DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Total accumulated costs';

-- 10. Create system_accounts configuration table
CREATE TABLE IF NOT EXISTS system_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    account_type VARCHAR(50) NOT NULL COMMENT 'WIP_INVENTORY, FINISHED_GOODS, MANUFACTURING_OVERHEAD, COGS, etc',
    account_id INT NOT NULL,
    is_default TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_account_type (account_type),
    UNIQUE KEY unique_account_type (tenant_id, account_type, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Insert default system accounts (adjust account_id based on your chart of accounts)
-- Note: You need to update these IDs based on your actual account structure
INSERT INTO system_accounts (tenant_id, account_type, account_id, is_default) VALUES
(1, 'WIP_INVENTORY', 1, 1),  -- Replace with actual WIP Inventory account ID
(1, 'FINISHED_GOODS', 2, 1),  -- Replace with actual Finished Goods account ID
(1, 'MANUFACTURING_OVERHEAD', 3, 1),  -- Replace with actual Manufacturing Overhead account ID
(1, 'COGS', 4, 1),  -- Replace with actual COGS account ID
(1, 'ACCOUNTS_PAYABLE', 5, 1)  -- Replace with actual AP account ID
ON DUPLICATE KEY UPDATE account_id = VALUES(account_id);

-- 12. Create audit log for expense changes
CREATE TABLE IF NOT EXISTS production_expense_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_expense_id INT NOT NULL,
    action ENUM('Created', 'Updated', 'Posted', 'Approved', 'Reversed', 'Deleted') NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    changed_by INT NOT NULL,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    INDEX idx_expense (production_expense_id),
    INDEX idx_action (action),
    INDEX idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Add foreign key constraints
ALTER TABLE production_expenses
ADD CONSTRAINT fk_expense_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_expense_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_expense_journal FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL;

-- 14. Update existing records to have default values
UPDATE production_expenses 
SET expense_date = DATE(created_at),
    status = 'Posted',  -- Assume existing expenses are posted
    currency_code = 'USD'
WHERE expense_date IS NULL;

-- 15. Create view for expense summary with accounting status
CREATE OR REPLACE VIEW v_production_expense_summary AS
SELECT 
    pe.*,
    po.order_no,
    p.name as product_name,
    v.vendor_name,
    cc.name as cost_center_name,
    je.entry_number as journal_entry_number,
    je.status as journal_status,
    u1.username as created_by_name,
    u2.username as approved_by_name,
    (pe.total_amount + pe.tax_amount) as gross_amount,
    CASE 
        WHEN pe.status = 'Posted' THEN 'Accounted'
        WHEN pe.status = 'Draft' THEN 'Not Accounted'
        ELSE 'Pending'
    END as accounting_status
FROM production_expenses pe
JOIN production_orders po ON pe.production_order_id = po.id
JOIN products p ON po.product_id = p.id
LEFT JOIN vendors v ON pe.vendor_id = v.id
LEFT JOIN cost_centers cc ON pe.cost_center_id = cc.id
LEFT JOIN journal_entries je ON pe.journal_entry_id = je.id
LEFT JOIN users u1 ON pe.created_by = u1.id
LEFT JOIN users u2 ON pe.approved_by = u2.id;

-- =====================================================
-- End of Migration
-- =====================================================
