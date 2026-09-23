-- =====================================================================
-- Combined migration: adds tenant_id to the 10 tables that were missing
-- it (bom_materials, completed_products, production_completion_materials,
-- production_order_materials, work_in_progress_items,
-- production_expense_items, product_uom_conversions, uom_group_units,
-- production_wastage_items, rent_items), and backfills existing rows
-- from each table's parent record.
--
-- Safe to re-run in full: every ALTER is skipped if the column already
-- exists, and every backfill UPDATE only ever touches rows that are
-- still NULL. Running this twice (or on a database some of these were
-- already applied to) does nothing destructive.
--
-- Backfill logic: each UPDATE ... INNER JOIN ... SET child.tenant_id =
-- parent.tenant_id has NO tenant filter — it processes every row for
-- every tenant in one pass, each row picking up its own tenant_id from
-- its own parent record. No manual per-tenant step is needed.
--
-- NOTE: a portion of rows in several of these tables may reference a
-- parent record that no longer exists (parent was deleted without its
-- children being cleaned up — a pre-existing, unrelated data-integrity
-- gap, not caused by this migration). Those rows cannot have their
-- tenant determined safely and are intentionally left with
-- tenant_id = NULL. This matches their current behavior (silently
-- excluded from tenant Backup/Restore/Format), so nothing regresses —
-- they just stay excluded for a documented reason instead of a schema
-- bug. The final verification query below reports how many rows fall
-- into this category per table ("still_orphaned_no_parent"); this is
-- expected and not a failure of the migration.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. bom_materials  (parent: bill_of_materials via bom_id)
-- ---------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bom_materials' AND COLUMN_NAME = 'tenant_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE bom_materials ADD COLUMN tenant_id INT NULL AFTER id, ADD INDEX idx_bom_materials_tenant_id (tenant_id)',
    'SELECT "tenant_id already exists on bom_materials, skipping ALTER" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE bom_materials bm
INNER JOIN bill_of_materials b ON bm.bom_id = b.id
SET bm.tenant_id = b.tenant_id
WHERE bm.tenant_id IS NULL;


-- ---------------------------------------------------------------------
-- 2. completed_products  (parent: production_completions via production_completion_id)
-- ---------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'completed_products' AND COLUMN_NAME = 'tenant_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE completed_products ADD COLUMN tenant_id INT NULL AFTER id, ADD INDEX idx_completed_products_tenant_id (tenant_id)',
    'SELECT "tenant_id already exists on completed_products, skipping ALTER" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE completed_products cp
INNER JOIN production_completions pc ON cp.production_completion_id = pc.id
SET cp.tenant_id = pc.tenant_id
WHERE cp.tenant_id IS NULL;


-- ---------------------------------------------------------------------
-- 3. production_completion_materials  (parent: production_completions via production_completion_id)
-- ---------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'production_completion_materials' AND COLUMN_NAME = 'tenant_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE production_completion_materials ADD COLUMN tenant_id INT NULL AFTER id, ADD INDEX idx_pcm_tenant_id (tenant_id)',
    'SELECT "tenant_id already exists on production_completion_materials, skipping ALTER" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE production_completion_materials pcm
INNER JOIN production_completions pc ON pcm.production_completion_id = pc.id
SET pcm.tenant_id = pc.tenant_id
WHERE pcm.tenant_id IS NULL;


-- ---------------------------------------------------------------------
-- 4. production_order_materials  (parent: production_orders via production_order_id)
-- ---------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'production_order_materials' AND COLUMN_NAME = 'tenant_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE production_order_materials ADD COLUMN tenant_id INT NULL AFTER id, ADD INDEX idx_pom_tenant_id (tenant_id)',
    'SELECT "tenant_id already exists on production_order_materials, skipping ALTER" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE production_order_materials pom
INNER JOIN production_orders po ON pom.production_order_id = po.id
SET pom.tenant_id = po.tenant_id
WHERE pom.tenant_id IS NULL;


-- ---------------------------------------------------------------------
-- 5. work_in_progress_items  (parent: work_in_progress via work_in_progress_id)
-- ---------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_in_progress_items' AND COLUMN_NAME = 'tenant_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE work_in_progress_items ADD COLUMN tenant_id INT NULL AFTER id, ADD INDEX idx_wipi_tenant_id (tenant_id)',
    'SELECT "tenant_id already exists on work_in_progress_items, skipping ALTER" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE work_in_progress_items wpi
INNER JOIN work_in_progress wp ON wpi.work_in_progress_id = wp.id
SET wpi.tenant_id = wp.tenant_id
WHERE wpi.tenant_id IS NULL;


-- ---------------------------------------------------------------------
-- 6. production_expense_items  (parent: production_expenses via expense_id)
-- ---------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'production_expense_items' AND COLUMN_NAME = 'tenant_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE production_expense_items ADD COLUMN tenant_id INT NULL AFTER id, ADD INDEX idx_pei_tenant_id (tenant_id)',
    'SELECT "tenant_id already exists on production_expense_items, skipping ALTER" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE production_expense_items pei
INNER JOIN production_expenses pe ON pei.expense_id = pe.id
SET pei.tenant_id = pe.tenant_id
WHERE pei.tenant_id IS NULL;


-- ---------------------------------------------------------------------
-- 7. product_uom_conversions  (parent: products via product_id)
-- ---------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_uom_conversions' AND COLUMN_NAME = 'tenant_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE product_uom_conversions ADD COLUMN tenant_id INT NULL AFTER id, ADD INDEX idx_puc_tenant_id (tenant_id)',
    'SELECT "tenant_id already exists on product_uom_conversions, skipping ALTER" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE product_uom_conversions puc
INNER JOIN products p ON puc.product_id = p.id
SET puc.tenant_id = p.tenant_id
WHERE puc.tenant_id IS NULL;


-- ---------------------------------------------------------------------
-- 8. uom_group_units  (parent: uom_groups via uom_group_id)
-- ---------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uom_group_units' AND COLUMN_NAME = 'tenant_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE uom_group_units ADD COLUMN tenant_id INT NULL AFTER id, ADD INDEX idx_ugu_tenant_id (tenant_id)',
    'SELECT "tenant_id already exists on uom_group_units, skipping ALTER" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE uom_group_units ugu
INNER JOIN uom_groups ug ON ugu.uom_group_id = ug.id
SET ugu.tenant_id = ug.tenant_id
WHERE ugu.tenant_id IS NULL;


-- ---------------------------------------------------------------------
-- 9. production_wastage_items  (parent: production_wastage via wastage_id)
-- ---------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'production_wastage_items' AND COLUMN_NAME = 'tenant_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE production_wastage_items ADD COLUMN tenant_id INT NULL AFTER id, ADD INDEX idx_pwi_tenant_id (tenant_id)',
    'SELECT "tenant_id already exists on production_wastage_items, skipping ALTER" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE production_wastage_items pwi
INNER JOIN production_wastage pw ON pwi.wastage_id = pw.id
SET pwi.tenant_id = pw.tenant_id
WHERE pwi.tenant_id IS NULL;


-- ---------------------------------------------------------------------
-- 10. rent_items  (parent: rent_management via rent_id)
-- ---------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rent_items' AND COLUMN_NAME = 'tenant_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE rent_items ADD COLUMN tenant_id INT NULL AFTER id, ADD INDEX idx_ri_tenant_id (tenant_id)',
    'SELECT "tenant_id already exists on rent_items, skipping ALTER" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE rent_items ri
INNER JOIN rent_management rm ON ri.rent_id = rm.id
SET ri.tenant_id = rm.tenant_id
WHERE ri.tenant_id IS NULL;


-- =====================================================================
-- Final verification — one combined result for all 10 tables.
-- "still_orphaned_no_parent" > 0 for any table just means some of its
-- rows point at an already-deleted parent record (see NOTE above); it
-- is expected and not a sign anything went wrong.
-- =====================================================================
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
