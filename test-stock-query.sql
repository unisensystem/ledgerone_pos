-- ============================================================
-- STOCK TEST QUERIES - ledgerone_tenant
-- Run these one by one in phpMyAdmin or any MySQL client
-- ============================================================

-- ============================================================
-- STEP 1: Check companies list
-- ============================================================
SELECT id, company_name, is_active
FROM companies
WHERE tenant_id = 2
ORDER BY id;

-- ============================================================
-- STEP 2: Check branches and which company they belong to
-- ============================================================
SELECT 
    b.id        AS branch_id,
    b.branch_name,
    b.branch_code,
    b.branch_type,
    b.company_id,
    c.company_name,
    b.is_active
FROM branches b
LEFT JOIN companies c ON b.company_id = c.id
WHERE b.tenant_id = 2
ORDER BY b.company_id, b.id;

-- ============================================================
-- STEP 3: Check stock_ledger — does it have branch_id?
-- (shows first 10 rows to confirm structure)
-- ============================================================
SELECT 
    sl.id,
    sl.product_id,
    sl.branch_id,
    sl.account_id,
    sl.qty_in,
    sl.qty_out,
    sl.transaction_type,
    sl.transaction_date
FROM stock_ledger sl
WHERE sl.tenant_id = 2
LIMIT 10;

-- ============================================================
-- STEP 4: Stock per branch per product
-- (see which branch has how much stock)
-- ============================================================
SELECT 
    p.id            AS product_id,
    p.name          AS product_name,
    b.id            AS branch_id,
    b.branch_name,
    b.company_id,
    c.company_name,
    COALESCE(SUM(
        (sl.qty_in  * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1)) -
        (sl.qty_out * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1))
    ), 0)           AS branch_stock
FROM stock_ledger sl
JOIN products p     ON sl.product_id = p.id
LEFT JOIN branches b ON sl.branch_id = b.id
LEFT JOIN companies c ON b.company_id = c.id
LEFT JOIN uom u     ON sl.unit_id = u.id
LEFT JOIN product_uom_conversions puc ON sl.product_id = puc.product_id AND sl.unit_id = puc.uom_id
WHERE sl.tenant_id = 2
GROUP BY p.id, sl.branch_id
ORDER BY p.name, b.company_id, b.id;

-- ============================================================
-- STEP 5: Stock SUM per company per product
-- (this is what get-product-stock.php now returns)
-- ============================================================
SELECT 
    p.id            AS product_id,
    p.name          AS product_name,
    c.id            AS company_id,
    c.company_name,
    COALESCE(SUM(
        (sl.qty_in  * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1)) -
        (sl.qty_out * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1))
    ), 0)           AS total_company_stock
FROM stock_ledger sl
JOIN products p      ON sl.product_id = p.id
LEFT JOIN branches b ON sl.branch_id = b.id
LEFT JOIN companies c ON b.company_id = c.id
LEFT JOIN uom u      ON sl.unit_id = u.id
LEFT JOIN product_uom_conversions puc ON sl.product_id = puc.product_id AND sl.unit_id = puc.uom_id
WHERE sl.tenant_id = 2
GROUP BY p.id, b.company_id
ORDER BY p.name, c.id;

-- ============================================================
-- STEP 6: VERIFY — compare branch stock vs company total
-- Branch stock SUM should equal company total
-- ============================================================
SELECT 
    c.id            AS company_id,
    c.company_name,
    p.id            AS product_id,
    p.name          AS product_name,
    b.id            AS branch_id,
    b.branch_name,
    COALESCE(SUM(
        (sl.qty_in  * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1)) -
        (sl.qty_out * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1))
    ), 0)           AS branch_stock,
    SUM(COALESCE(SUM(
        (sl.qty_in  * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1)) -
        (sl.qty_out * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1))
    ), 0)) OVER (PARTITION BY p.id, b.company_id) AS company_total_stock
FROM stock_ledger sl
JOIN products p      ON sl.product_id = p.id
LEFT JOIN branches b ON sl.branch_id = b.id
LEFT JOIN companies c ON b.company_id = c.id
LEFT JOIN uom u      ON sl.unit_id = u.id
LEFT JOIN product_uom_conversions puc ON sl.product_id = puc.product_id AND sl.unit_id = puc.uom_id
WHERE sl.tenant_id = 2
GROUP BY p.id, sl.branch_id, b.company_id
ORDER BY c.id, p.name, b.id;

-- ============================================================
-- STEP 7: SIMULATE get-product-stock.php API
-- Replace @product_id and @company_id with actual values
-- from Step 4/5 results above
-- ============================================================
SET @product_id = 1;   -- <-- Step 4 se product_id copy karo
SET @company_id = 1;   -- <-- Step 1 se company_id copy karo
SET @tenant_id  = 2;

SELECT 
    p.inventory_account_id,
    p.default_unit_id,
    base_u.uom_name,
    base_u.id AS base_unit_id
FROM products p
LEFT JOIN uom u ON p.default_unit_id = u.id
LEFT JOIN uom base_u ON COALESCE(u.base_unit_id, u.id) = base_u.id
WHERE p.id = @product_id;

-- Now get the stock (same query as PHP file)
SELECT 
    COALESCE(SUM(
        (sl.qty_in  * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1)) -
        (sl.qty_out * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1))
    ), 0) AS stock
FROM stock_ledger sl
LEFT JOIN uom u ON sl.unit_id = u.id
LEFT JOIN product_uom_conversions puc ON sl.product_id = puc.product_id AND sl.unit_id = puc.uom_id
LEFT JOIN branches b ON sl.branch_id = b.id
WHERE sl.tenant_id = @tenant_id
  AND sl.product_id = @product_id
  AND b.company_id  = @company_id;
-- NOTE: Remove last AND line to get stock across ALL companies

-- ============================================================
-- STEP 8: Quick sanity — branches with NO company_id assigned
-- These branches will be EXCLUDED from company filter
-- Should ideally be 0 rows
-- ============================================================
SELECT 
    b.id, b.branch_name, b.branch_code, b.company_id
FROM branches b
WHERE b.tenant_id = 2
  AND b.company_id IS NULL;

-- ============================================================
-- STEP 9: stock_ledger rows with NO branch_id
-- These will also be excluded from company filter
-- ============================================================
SELECT COUNT(*) AS ledger_rows_without_branch
FROM stock_ledger
WHERE tenant_id = 2
  AND branch_id IS NULL;
