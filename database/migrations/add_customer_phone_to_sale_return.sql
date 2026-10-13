-- Adds a customer_phone snapshot column to sale_return, mirroring the same
-- field already added to sale_invoice, so the Counter Sale Return page can
-- store the phone number captured at return time.
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sale_return'
    AND COLUMN_NAME = 'customer_phone'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE sale_return ADD COLUMN customer_phone VARCHAR(30) NULL AFTER customer_id',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
