-- READ-ONLY verification — does not change any data.
-- 1) Confirms all 10 tables now have the tenant_id column.
-- 2) Shows row counts per table (total / backfilled / still-orphaned).

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME = 'tenant_id'
  AND TABLE_NAME IN (
    'bom_materials','completed_products','production_completion_materials',
    'production_order_materials','work_in_progress_items','production_expense_items',
    'product_uom_conversions','uom_group_units','production_wastage_items','rent_items'
  )
ORDER BY TABLE_NAME;

SELECT 'bom_materials' AS table_name, COUNT(*) AS total_rows,
    SUM(tenant_id IS NOT NULL) AS backfilled, SUM(tenant_id IS NULL) AS still_orphaned_no_parent
FROM bom_materials
UNION ALL
SELECT 'completed_products', COUNT(*), SUM(tenant_id IS NOT NULL), SUM(tenant_id IS NULL)
FROM completed_products
UNION ALL
SELECT 'production_completion_materials', COUNT(*), SUM(tenant_id IS NOT NULL), SUM(tenant_id IS NULL)
FROM production_completion_materials
UNION ALL
SELECT 'production_order_materials', COUNT(*), SUM(tenant_id IS NOT NULL), SUM(tenant_id IS NULL)
FROM production_order_materials
UNION ALL
SELECT 'work_in_progress_items', COUNT(*), SUM(tenant_id IS NOT NULL), SUM(tenant_id IS NULL)
FROM work_in_progress_items
UNION ALL
SELECT 'production_expense_items', COUNT(*), SUM(tenant_id IS NOT NULL), SUM(tenant_id IS NULL)
FROM production_expense_items
UNION ALL
SELECT 'product_uom_conversions', COUNT(*), SUM(tenant_id IS NOT NULL), SUM(tenant_id IS NULL)
FROM product_uom_conversions
UNION ALL
SELECT 'uom_group_units', COUNT(*), SUM(tenant_id IS NOT NULL), SUM(tenant_id IS NULL)
FROM uom_group_units
UNION ALL
SELECT 'production_wastage_items', COUNT(*), SUM(tenant_id IS NOT NULL), SUM(tenant_id IS NULL)
FROM production_wastage_items
UNION ALL
SELECT 'rent_items', COUNT(*), SUM(tenant_id IS NOT NULL), SUM(tenant_id IS NULL)
FROM rent_items;
