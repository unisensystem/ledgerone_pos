-- ============================================================================
-- Remove company-wise scoping: renumber customer/supplier codes and invoice/
-- return bill numbers to be sequential PER TENANT ONLY (not per company),
-- across every tenant in the system. This matches the corresponding backend
-- code change that stopped scoping these numbers (and access control) by
-- company_id.
--
-- IMPORTANT — run this only after backing up the affected tables:
--   mysqldump -u <user> -p<pass> <database> --single-transaction --skip-lock-tables \
--     customers suppliers purchase_invoice sale_invoice purchase_return sale_return \
--     payment_voucher receive_voucher > pre_renumber_backup.sql
--
-- Verified locally: 0 duplicate codes per tenant after running, sequences are
-- gap-free per tenant, no application code depends on payment_voucher.bill_no /
-- receive_voucher.bill_no being kept in sync (those are display-only text
-- fields, not foreign keys — intentionally left untouched by this migration).
-- ============================================================================

-- 0. Drop old company-scoped unique constraints FIRST — otherwise the
--    renumbering below transiently collides with numbers already used by a
--    different company under the same tenant before it gets its own new number.
ALTER TABLE customers DROP INDEX uk_customers_tenant_company_code;
ALTER TABLE suppliers DROP INDEX uk_suppliers_tenant_company_code;
ALTER TABLE purchase_invoice DROP INDEX uk_tenant_company_bill_no;
ALTER TABLE sale_invoice DROP INDEX uk_tenant_company_bill_no;
ALTER TABLE purchase_return DROP INDEX uk_tenant_company_bill_no;
ALTER TABLE sale_return DROP INDEX uk_tenant_company_bill_no;

-- 1. customers.customer_code -> CUST-#####
SET @rn = 0, @prev_tenant = NULL;
UPDATE customers c
JOIN (
  SELECT id,
    @rn := IF(@prev_tenant = tenant_id, @rn + 1, 1) AS rn,
    @prev_tenant := tenant_id AS t
  FROM (SELECT id, tenant_id FROM customers ORDER BY tenant_id, created_at, id) x
) r ON c.id = r.id
SET c.customer_code = CONCAT('CUST-', LPAD(r.rn, 5, '0'));

-- 2. suppliers.supplier_code -> SUPP-#####
SET @rn = 0, @prev_tenant = NULL;
UPDATE suppliers s
JOIN (
  SELECT id,
    @rn := IF(@prev_tenant = tenant_id, @rn + 1, 1) AS rn,
    @prev_tenant := tenant_id AS t
  FROM (SELECT id, tenant_id FROM suppliers ORDER BY tenant_id, created_at, id) x
) r ON s.id = r.id
SET s.supplier_code = CONCAT('SUPP-', LPAD(r.rn, 5, '0'));

-- 3. purchase_invoice.bill_no -> PUR-####
SET @rn = 0, @prev_tenant = NULL;
UPDATE purchase_invoice p
JOIN (
  SELECT id,
    @rn := IF(@prev_tenant = tenant_id, @rn + 1, 1) AS rn,
    @prev_tenant := tenant_id AS t
  FROM (SELECT id, tenant_id FROM purchase_invoice ORDER BY tenant_id, created_at, id) x
) r ON p.id = r.id
SET p.bill_no = CONCAT('PUR-', LPAD(r.rn, 4, '0'));

-- 4. sale_invoice.bill_no -> SAL-#### (shared by pos_invoice + sale_tax_invoice)
SET @rn = 0, @prev_tenant = NULL;
UPDATE sale_invoice si
JOIN (
  SELECT id,
    @rn := IF(@prev_tenant = tenant_id, @rn + 1, 1) AS rn,
    @prev_tenant := tenant_id AS t
  FROM (SELECT id, tenant_id FROM sale_invoice ORDER BY tenant_id, created_at, id) x
) r ON si.id = r.id
SET si.bill_no = CONCAT('SAL-', LPAD(r.rn, 4, '0'));

-- 5. purchase_return.bill_no -> PRN-####
SET @rn = 0, @prev_tenant = NULL;
UPDATE purchase_return pr
JOIN (
  SELECT id,
    @rn := IF(@prev_tenant = tenant_id, @rn + 1, 1) AS rn,
    @prev_tenant := tenant_id AS t
  FROM (SELECT id, tenant_id FROM purchase_return ORDER BY tenant_id, created_at, id) x
) r ON pr.id = r.id
SET pr.bill_no = CONCAT('PRN-', LPAD(r.rn, 4, '0'));

-- 6. sale_return.bill_no -> SR-####
SET @rn = 0, @prev_tenant = NULL;
UPDATE sale_return sr
JOIN (
  SELECT id,
    @rn := IF(@prev_tenant = tenant_id, @rn + 1, 1) AS rn,
    @prev_tenant := tenant_id AS t
  FROM (SELECT id, tenant_id FROM sale_return ORDER BY tenant_id, created_at, id) x
) r ON sr.id = r.id
SET sr.bill_no = CONCAT('SR-', LPAD(r.rn, 4, '0'));

-- 7. Add the new tenant-wide unique constraints (prevents this from ever
--    regressing back to company-scoped duplicates)
ALTER TABLE customers ADD UNIQUE KEY uk_customers_tenant_code (tenant_id, customer_code);
ALTER TABLE suppliers ADD UNIQUE KEY uk_suppliers_tenant_code (tenant_id, supplier_code);
ALTER TABLE purchase_invoice ADD UNIQUE KEY uk_purchase_invoice_tenant_bill_no (tenant_id, bill_no);
ALTER TABLE sale_invoice ADD UNIQUE KEY uk_sale_invoice_tenant_bill_no (tenant_id, bill_no);
ALTER TABLE purchase_return ADD UNIQUE KEY uk_purchase_return_tenant_bill_no (tenant_id, bill_no);
ALTER TABLE sale_return ADD UNIQUE KEY uk_sale_return_tenant_bill_no (tenant_id, bill_no);

-- ============================================================================
-- Verification queries — run these after the migration to confirm success.
-- Every query below should return 0 rows / 0 count.
-- ============================================================================
-- SELECT tenant_id, customer_code, COUNT(*) c FROM customers GROUP BY tenant_id, customer_code HAVING c > 1;
-- SELECT tenant_id, supplier_code, COUNT(*) c FROM suppliers GROUP BY tenant_id, supplier_code HAVING c > 1;
-- SELECT tenant_id, bill_no, COUNT(*) c FROM purchase_invoice GROUP BY tenant_id, bill_no HAVING c > 1;
-- SELECT tenant_id, bill_no, COUNT(*) c FROM sale_invoice GROUP BY tenant_id, bill_no HAVING c > 1;
-- SELECT tenant_id, bill_no, COUNT(*) c FROM purchase_return GROUP BY tenant_id, bill_no HAVING c > 1;
-- SELECT tenant_id, bill_no, COUNT(*) c FROM sale_return GROUP BY tenant_id, bill_no HAVING c > 1;
