-- Adds "Receive from Supplier" support to receive_voucher, mirroring payment_voucher's
-- existing customer_id/supplier_id dual-party pattern.

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'receive_voucher' AND COLUMN_NAME = 'supplier_id');
SET @sql = IF(@exists = 0, 'ALTER TABLE receive_voucher ADD COLUMN supplier_id INT(11) NULL AFTER customer_id', 'SELECT "supplier_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- sub_account_id is reused for both customer and supplier sub-accounts (same pattern as
-- payment_voucher, whose vestigial customer_sub_account_id column is unused in code).

SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'receive_voucher' AND INDEX_NAME = 'idx_supplier_id');
SET @sql = IF(@exists = 0, 'ALTER TABLE receive_voucher ADD INDEX idx_supplier_id (supplier_id)', 'SELECT "idx_supplier_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE receive_voucher MODIFY COLUMN customer_id INT(11) NULL;
