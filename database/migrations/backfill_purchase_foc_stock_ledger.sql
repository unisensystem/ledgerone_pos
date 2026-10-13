-- Backfills the missing "Purchase Invoice - FOC" stock_ledger entries for
-- every purchase_invoice_items row with foc_quantity > 0 that was saved
-- before the purchase-add.php / purchase-edit.php fix (which previously
-- saved the FOC quantity on the item row but never entered it into
-- stock_ledger, so it never counted towards Stock Position).
--
-- SAFETY:
--   - This script only INSERTs into stock_ledger. It never UPDATEs or
--     DELETEs any existing row in any table.
--   - It is idempotent: the NOT EXISTS check means running it twice (or on
--     a database that's already partially fixed) will not create duplicates.
--   - It runs across ALL tenants in one pass (the bug was not tenant-specific).
--   - For an item that produced multiple purchase_invoice_items rows (e.g.
--     Carton + Piece unit entries on one line), the app always duplicates
--     the same foc_quantity onto every row for that line — this script
--     picks exactly ONE representative row per (invoice, product) via
--     MIN(id), so it creates exactly one FOC stock_ledger entry per line,
--     matching what the app itself does.
--
-- RECOMMENDED: run the SELECT preview first (query 1) and eyeball the rows
-- it would insert before running the INSERT (query 2).

-- 1) PREVIEW — run this first, review the output, nothing is written yet.
SELECT
    pi.tenant_id,
    p.inventory_account_id AS account_id,
    pi.branch_id,
    pii.product_id,
    pii.purchase_invoice_id AS reference_id,
    pii.foc_quantity AS qty_in,
    CASE
        WHEN u.is_base_unit = 1 THEN u.id
        ELSE COALESCE(u.base_unit_id, u.id, pii.uom_id)
    END AS unit_id,
    pii.batch_no,
    pii.batch_expiry_date AS expiry_date,
    pi.purchase_date AS transaction_date
FROM purchase_invoice_items pii
JOIN purchase_invoice pi ON pii.purchase_invoice_id = pi.id
JOIN products p ON pii.product_id = p.id
LEFT JOIN uom u ON pii.uom_id = u.id
WHERE pii.foc_quantity > 0
  AND p.inventory_account_id IS NOT NULL
  AND pii.id = (
        SELECT MIN(pii2.id) FROM purchase_invoice_items pii2
        WHERE pii2.purchase_invoice_id = pii.purchase_invoice_id
          AND pii2.product_id = pii.product_id
          AND pii2.foc_quantity > 0
  )
  AND NOT EXISTS (
        SELECT 1 FROM stock_ledger sl
        WHERE sl.reference_table = 'purchase_invoice'
          AND sl.reference_id = pii.purchase_invoice_id
          AND sl.product_id = pii.product_id
          AND sl.transaction_type = 'Purchase Invoice - FOC'
  );

-- 2) ACTUAL BACKFILL — only run after checking the preview above looks right.
INSERT INTO stock_ledger (
    tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
    qty_in, unit_cost, unit_id, batch_no, expiry_date, transaction_type, transaction_date
)
SELECT
    pi.tenant_id,
    p.inventory_account_id,
    pi.branch_id,
    pii.product_id,
    'purchase_invoice',
    pii.purchase_invoice_id,
    pii.foc_quantity,
    0,
    CASE
        WHEN u.is_base_unit = 1 THEN u.id
        ELSE COALESCE(u.base_unit_id, u.id, pii.uom_id)
    END,
    pii.batch_no,
    pii.batch_expiry_date,
    'Purchase Invoice - FOC',
    pi.purchase_date
FROM purchase_invoice_items pii
JOIN purchase_invoice pi ON pii.purchase_invoice_id = pi.id
JOIN products p ON pii.product_id = p.id
LEFT JOIN uom u ON pii.uom_id = u.id
WHERE pii.foc_quantity > 0
  AND p.inventory_account_id IS NOT NULL
  AND pii.id = (
        SELECT MIN(pii2.id) FROM purchase_invoice_items pii2
        WHERE pii2.purchase_invoice_id = pii.purchase_invoice_id
          AND pii2.product_id = pii.product_id
          AND pii2.foc_quantity > 0
  )
  AND NOT EXISTS (
        SELECT 1 FROM stock_ledger sl
        WHERE sl.reference_table = 'purchase_invoice'
          AND sl.reference_id = pii.purchase_invoice_id
          AND sl.product_id = pii.product_id
          AND sl.transaction_type = 'Purchase Invoice - FOC'
  );

-- 3) VERIFY — after running the INSERT, this should return 0 rows.
SELECT pii.purchase_invoice_id, pi.tenant_id, pii.foc_quantity
FROM purchase_invoice_items pii
JOIN purchase_invoice pi ON pii.purchase_invoice_id = pi.id
WHERE pii.foc_quantity > 0
  AND NOT EXISTS (
        SELECT 1 FROM stock_ledger sl
        WHERE sl.reference_table = 'purchase_invoice'
          AND sl.reference_id = pii.purchase_invoice_id
          AND sl.product_id = pii.product_id
          AND sl.transaction_type = 'Purchase Invoice - FOC'
  );
