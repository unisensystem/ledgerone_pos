-- Persists the "Invoice Settings" modal (pos_invoice / med_pos_invoice) to the
-- database per user, instead of only browser localStorage — so a user's chosen
-- settings (default scheme, sale price, display modes, item/summary toggles,
-- print options, etc.) follow them across browsers/machines instead of having
-- to be re-set every time they open the invoice page somewhere new.
--
-- One row per (tenant_id, user_id, page_type): page_type is 'pos' or 'med'
-- since the two invoice pages keep independent settings (matching the
-- pos_/med_ localStorage key prefixing already used client-side).
-- settings_json holds the full flat key/value map of every setting key.

SET @table_exists = (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_invoice_settings'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE user_invoice_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        user_id INT NOT NULL,
        page_type VARCHAR(10) NOT NULL,
        settings_json LONGTEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_tenant_user_page (tenant_id, user_id, page_type),
        INDEX idx_tenant_id (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci',
    'SELECT "user_invoice_settings already exists" AS notice'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
