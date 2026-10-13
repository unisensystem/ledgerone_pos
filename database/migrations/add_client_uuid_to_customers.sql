-- Idempotent-sync support for LedgerOne Mobile's offline Add Customer flow.
-- The app generates one UUID per customer created offline and sends it with
-- every create/retry. customer-add.php checks for an existing row with this
-- tenant_id + client_uuid before inserting, so a retried sync can never
-- create a duplicate customer. NULL for every web-created row — MySQL's
-- unique index allows multiple NULLs, so this has zero effect on the
-- existing web flow.
ALTER TABLE customers
    ADD COLUMN client_uuid VARCHAR(36) NULL AFTER customer_code,
    ADD UNIQUE KEY uk_tenant_client_uuid (tenant_id, client_uuid);
