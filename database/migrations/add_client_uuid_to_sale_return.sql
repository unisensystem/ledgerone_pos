-- Idempotent-sync support for LedgerOne Mobile's Sale Return feature — same
-- reasoning as add_client_uuid_to_sale_order.sql. Without this, a retried
-- offline sync of a return that actually succeeded server-side (but whose
-- response the app never saw) would create a genuine duplicate return —
-- restocking the same items twice. NULL for every web-created row — MySQL's
-- unique index allows multiple NULLs, so this has zero effect on the
-- existing web flow (return-add.php, counter-return.php).
ALTER TABLE sale_return
    ADD COLUMN client_uuid VARCHAR(36) NULL AFTER bill_no,
    ADD UNIQUE KEY uk_tenant_client_uuid (tenant_id, client_uuid);
