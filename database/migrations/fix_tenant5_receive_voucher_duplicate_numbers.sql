-- ============================================================================
-- Fix duplicate receive_voucher.voucher_number values for tenant_id = 5 only.
-- This is UNRELATED to the company-wise scoping removal — it's a separate,
-- pre-existing data issue found only in tenant 5 (66 duplicate groups out of
-- 1602 rows, spanning Jan 2026). voucher_number is never copied into any
-- other table (checked: only receive_voucher/payment_voucher/journal_voucher/
-- transfer_voucher each have their own independent voucher_number column),
-- so renumbering it here is self-contained and safe.
--
-- Only tenant_id = 5 is touched — every other tenant's receive_voucher
-- numbers are already correct and are left completely untouched.
--
-- IMPORTANT — back up first:
--   mysqldump -u <user> -p<pass> <database> --single-transaction --skip-lock-tables \
--     receive_voucher > pre_rv_fix_backup.sql
--
-- NOTE: if you connect with the mysql CLI and see a "Illegal mix of
-- collations" error, add --default-character-set=latin1 to the mysql command.
-- ============================================================================

SET @rn = 0, @prev_key = NULL;
UPDATE receive_voucher rv
JOIN (
  SELECT id,
    @rn := IF(@prev_key = CONCAT(tenant_id, '-', YEAR(created_at)), @rn + 1, 1) AS rn,
    @prev_key := CONCAT(tenant_id, '-', YEAR(created_at)) AS k,
    YEAR(created_at) as yr
  FROM (SELECT id, tenant_id, created_at FROM receive_voucher WHERE tenant_id = 5 ORDER BY tenant_id, YEAR(created_at), created_at, id) x
) r ON rv.id = r.id
SET rv.voucher_number = CONCAT('RV-', r.yr, '-', LPAD(r.rn, 4, '0'));

-- ============================================================================
-- Verification — run after. Should return ZERO rows.
-- ============================================================================
-- SELECT tenant_id, voucher_number, COUNT(*) c FROM receive_voucher
-- WHERE tenant_id = 5 GROUP BY tenant_id, voucher_number HAVING c > 1;
