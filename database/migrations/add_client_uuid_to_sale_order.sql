-- Idempotent-sync support for LedgerOne Mobile. The app generates one UUID
-- per order created offline and sends it with every create/retry. order-add.php
-- checks for an existing row with this tenant_id + client_uuid before inserting,
-- so a retried sync (e.g. the app got no response but the order actually posted)
-- can never create a duplicate order. NULL for every web-created row — MySQL's
-- unique index allows multiple NULLs, so this has zero effect on the existing
-- web flow.
ALTER TABLE sale_order
    ADD COLUMN client_uuid VARCHAR(36) NULL AFTER bill_no,
    ADD UNIQUE KEY uk_tenant_client_uuid (tenant_id, client_uuid);
