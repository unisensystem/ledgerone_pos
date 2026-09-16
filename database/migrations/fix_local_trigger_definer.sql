-- ============================================================================
-- LOCAL DEV FIX ONLY — do not run on production.
--
-- The local restored copy of ledgerone_tenant has 6 triggers whose DEFINER is
-- `ceo_usman`@`202.47.35.3` — a MySQL user account that does not exist on
-- this local server (confirmed: SELECT * FROM mysql.user WHERE User =
-- 'ceo_usman' returns nothing). Any INSERT/UPDATE that fires one of these
-- triggers fails with:
--   SQLSTATE[HY000]: General error: 1449 The user specified as a definer
--   ('ceo_usman'@'202.47.35.3') does not exist
-- This breaks customer add/edit, supplier add/edit, bank account balance
-- adjustments, and journal voucher totals — not just one page.
--
-- Production presumably already has the ceo_usman account and these triggers
-- work fine there; this migration only re-points DEFINER to CURRENT_USER (the
-- account actually running this script) so the same restored dump works on
-- any local/dev machine. The trigger BODIES are copied byte-for-byte from
-- `SHOW CREATE TRIGGER` — no business logic changed, only DEFINER.
-- ============================================================================

DROP TRIGGER IF EXISTS trg_customers_balance_insert;
DELIMITER $$
CREATE DEFINER = CURRENT_USER TRIGGER trg_customers_balance_insert BEFORE INSERT ON customers FOR EACH ROW BEGIN
    SET NEW.current_balance = COALESCE(NEW.opening_debit_amount, 0) - COALESCE(NEW.opening_credit_amount, 0);
    IF NEW.is_blacklisted = TRUE THEN
        SET NEW.status = 'BLACKLISTED';
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_customers_balance_update;
DELIMITER $$
CREATE DEFINER = CURRENT_USER TRIGGER trg_customers_balance_update BEFORE UPDATE ON customers FOR EACH ROW BEGIN
    SET NEW.current_balance = COALESCE(NEW.opening_debit_amount, 0) - COALESCE(NEW.opening_credit_amount, 0);
    IF NEW.is_blacklisted = TRUE THEN
        SET NEW.status = 'BLACKLISTED';
    ELSEIF NEW.is_blacklisted = FALSE AND OLD.status = 'BLACKLISTED' THEN
        SET NEW.status = 'ACTIVE';
    END IF;
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_suppliers_balance_insert;
DELIMITER $$
CREATE DEFINER = CURRENT_USER TRIGGER trg_suppliers_balance_insert BEFORE INSERT ON suppliers FOR EACH ROW BEGIN
    SET NEW.current_balance = COALESCE(NEW.opening_debit_amount, 0) - COALESCE(NEW.opening_credit_amount, 0);
    IF NEW.is_blacklisted = TRUE THEN
        SET NEW.status = 'BLACKLISTED';
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_suppliers_balance_update;
DELIMITER $$
CREATE DEFINER = CURRENT_USER TRIGGER trg_suppliers_balance_update BEFORE UPDATE ON suppliers FOR EACH ROW BEGIN
    SET NEW.current_balance = COALESCE(NEW.opening_debit_amount, 0) - COALESCE(NEW.opening_credit_amount, 0);
    IF NEW.is_blacklisted = TRUE THEN
        SET NEW.status = 'BLACKLISTED';
    ELSEIF NEW.is_blacklisted = FALSE AND OLD.status = 'BLACKLISTED' THEN
        SET NEW.status = 'ACTIVE';
    END IF;
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_after_bank_account_update;
DELIMITER $$
CREATE DEFINER = CURRENT_USER TRIGGER trg_after_bank_account_update AFTER UPDATE ON bank_accounts FOR EACH ROW BEGIN
    IF OLD.debit_amount != NEW.debit_amount OR OLD.credit_amount != NEW.credit_amount THEN
        SET @old_balance = OLD.debit_amount - OLD.credit_amount;
        SET @new_balance = NEW.debit_amount - NEW.credit_amount;
        SET @balance_change = @new_balance - @old_balance;
        IF ABS(@balance_change) > 0.01 THEN
            IF @balance_change > 0 THEN
                INSERT INTO accounting_ledger (transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
                VALUES ('Bank Balance Adjustment', 'bank_accounts', NEW.id, NEW.account_id, CURDATE(), CONCAT('Balance adjustment - ', NEW.bank_name), ABS(@balance_change), 0);
            ELSE
                INSERT INTO accounting_ledger (transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
                VALUES ('Bank Balance Adjustment', 'bank_accounts', NEW.id, NEW.account_id, CURDATE(), CONCAT('Balance adjustment - ', NEW.bank_name), 0, ABS(@balance_change));
            END IF;
        END IF;
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS sync_voucher_totals;
DELIMITER $$
CREATE DEFINER = CURRENT_USER TRIGGER sync_voucher_totals AFTER INSERT ON journal_voucher_line FOR EACH ROW BEGIN
    UPDATE journal_voucher jv
    SET jv.total_debit = (
        SELECT COALESCE(SUM(debit), 0) FROM journal_voucher_line jvl WHERE jvl.voucher_id = NEW.voucher_id
    ),
    jv.total_credit = (
        SELECT COALESCE(SUM(credit), 0) FROM journal_voucher_line jvl WHERE jvl.voucher_id = NEW.voucher_id
    )
    WHERE jv.id = NEW.voucher_id;
END$$
DELIMITER ;
