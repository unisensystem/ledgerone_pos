-- ============================================================================
-- Verification script for remove_company_wise_scoping_renumber_all_tenants.sql
--
-- Run this AFTER the migration. It checks all 6 renumbered entities, across
-- EVERY tenant in the system, for any leftover duplicate code within the
-- same tenant.
--
-- Every table reference below is fully qualified with the `ledgerone_tenant.`
-- database prefix on purpose, so it doesn't matter which database phpMyAdmin
-- (or your mysql client) currently has "selected" — these queries always hit
-- the right place regardless of that UI state.
--
-- HOW TO READ THE RESULT:
--   - Query 1: if it returns ZERO ROWS, everything is 100% correct — no
--     tenant has a duplicate code anywhere.
--   - Query 2: should return exactly 6 rows (one per table).
--   - Query 3: for every row, min_number should be 1 and max_number should
--     equal total_rows.
-- ============================================================================

-- --- Query 1: duplicate check ---
SELECT 'customers' AS table_name, tenant_id, customer_code AS duplicated_code, COUNT(*) AS how_many
FROM ledgerone_tenant.customers
GROUP BY tenant_id, customer_code
HAVING how_many > 1

UNION ALL

SELECT 'suppliers', tenant_id, supplier_code, COUNT(*)
FROM ledgerone_tenant.suppliers
GROUP BY tenant_id, supplier_code
HAVING COUNT(*) > 1

UNION ALL

SELECT 'purchase_invoice', tenant_id, bill_no, COUNT(*)
FROM ledgerone_tenant.purchase_invoice
GROUP BY tenant_id, bill_no
HAVING COUNT(*) > 1

UNION ALL

SELECT 'sale_invoice', tenant_id, bill_no, COUNT(*)
FROM ledgerone_tenant.sale_invoice
GROUP BY tenant_id, bill_no
HAVING COUNT(*) > 1

UNION ALL

SELECT 'purchase_return', tenant_id, bill_no, COUNT(*)
FROM ledgerone_tenant.purchase_return
GROUP BY tenant_id, bill_no
HAVING COUNT(*) > 1

UNION ALL

SELECT 'sale_return', tenant_id, bill_no, COUNT(*)
FROM ledgerone_tenant.sale_return
GROUP BY tenant_id, bill_no
HAVING COUNT(*) > 1

ORDER BY table_name, tenant_id;

-- --- Query 2: confirm the new tenant-wide UNIQUE constraints exist ---
SELECT TABLE_NAME, CONSTRAINT_NAME
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = 'ledgerone_tenant'
  AND CONSTRAINT_NAME IN (
    'uk_customers_tenant_code',
    'uk_suppliers_tenant_code',
    'uk_purchase_invoice_tenant_bill_no',
    'uk_sale_invoice_tenant_bill_no',
    'uk_purchase_return_tenant_bill_no',
    'uk_sale_return_tenant_bill_no'
  )
ORDER BY TABLE_NAME;

-- --- Query 3: per-tenant summary (numbers should run 1..N, no gaps) ---
SELECT
  'customers' AS table_name, tenant_id, COUNT(*) AS total_rows,
  MIN(CAST(SUBSTRING(customer_code, 6) AS UNSIGNED)) AS min_number,
  MAX(CAST(SUBSTRING(customer_code, 6) AS UNSIGNED)) AS max_number
FROM ledgerone_tenant.customers GROUP BY tenant_id

UNION ALL

SELECT 'suppliers', tenant_id, COUNT(*),
  MIN(CAST(SUBSTRING(supplier_code, 6) AS UNSIGNED)),
  MAX(CAST(SUBSTRING(supplier_code, 6) AS UNSIGNED))
FROM ledgerone_tenant.suppliers GROUP BY tenant_id

UNION ALL

SELECT 'purchase_invoice', tenant_id, COUNT(*),
  MIN(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED)),
  MAX(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED))
FROM ledgerone_tenant.purchase_invoice GROUP BY tenant_id

UNION ALL

SELECT 'sale_invoice', tenant_id, COUNT(*),
  MIN(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED)),
  MAX(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED))
FROM ledgerone_tenant.sale_invoice GROUP BY tenant_id

UNION ALL

SELECT 'purchase_return', tenant_id, COUNT(*),
  MIN(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED)),
  MAX(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED))
FROM ledgerone_tenant.purchase_return GROUP BY tenant_id

UNION ALL

SELECT 'sale_return', tenant_id, COUNT(*),
  MIN(CAST(SUBSTRING(bill_no, 4) AS UNSIGNED)),
  MAX(CAST(SUBSTRING(bill_no, 4) AS UNSIGNED))
FROM ledgerone_tenant.sale_return GROUP BY tenant_id

ORDER BY table_name, tenant_id;
