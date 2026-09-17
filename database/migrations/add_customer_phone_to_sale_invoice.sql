-- ============================================================================
-- Adds a "Phone No" field to the POS Sale Invoice (client/pages/sale/pos_invoice)
-- entry form — auto-filled from the selected customer's Primary Phone
-- (customers.primary_phone), editable, and saved with the invoice.
--
-- Safe to run multiple times (checks before altering).
-- ============================================================================

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_invoice' AND COLUMN_NAME = 'customer_phone');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE sale_invoice ADD COLUMN customer_phone VARCHAR(20) NULL AFTER transport_name', 'SELECT ''customer_phone already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
