-- Migration: widen suppliers.salesman_id from INT(11) to VARCHAR(255)
-- Run once, then delete this file.

-- 1. Drop FK constraint (ignore error if it doesn't exist)
ALTER TABLE suppliers DROP FOREIGN KEY fk_suppliers_salesman;

-- 2. Drop the associated index (ignore error if it doesn't exist)
ALTER TABLE suppliers DROP INDEX fk_suppliers_salesman;

-- 3. Widen the column
ALTER TABLE suppliers MODIFY COLUMN salesman_id VARCHAR(255) DEFAULT NULL;

-- 4. Drop old triggers (compiled against the old INT schema)
DROP TRIGGER IF EXISTS trg_suppliers_balance_insert;
DROP TRIGGER IF EXISTS trg_suppliers_balance_update;

-- 5. Recreate triggers compiled against the new VARCHAR(255) schema
DELIMITER //

CREATE TRIGGER trg_suppliers_balance_insert
BEFORE INSERT ON suppliers FOR EACH ROW
BEGIN
    SET NEW.current_balance = COALESCE(NEW.opening_debit_amount, 0) - COALESCE(NEW.opening_credit_amount, 0);
    IF NEW.is_blacklisted = TRUE THEN
        SET NEW.status = 'BLACKLISTED';
    END IF;
END//

CREATE TRIGGER trg_suppliers_balance_update
BEFORE UPDATE ON suppliers FOR EACH ROW
BEGIN
    SET NEW.current_balance = COALESCE(NEW.opening_debit_amount, 0) - COALESCE(NEW.opening_credit_amount, 0);
    IF NEW.is_blacklisted = TRUE THEN
        SET NEW.status = 'BLACKLISTED';
    ELSEIF NEW.is_blacklisted = FALSE AND OLD.status = 'BLACKLISTED' THEN
        SET NEW.status = 'ACTIVE';
    END IF;
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END//

DELIMITER ;

-- 6. Verify
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'suppliers'
  AND COLUMN_NAME = 'salesman_id';
