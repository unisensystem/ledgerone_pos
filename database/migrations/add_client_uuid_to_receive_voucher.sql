-- Idempotent-sync support for LedgerOne Mobile, same pattern as
-- add_client_uuid_to_sale_order.sql. NULL for every web-created row.
ALTER TABLE receive_voucher
    ADD COLUMN client_uuid VARCHAR(36) NULL AFTER voucher_number,
    ADD UNIQUE KEY uk_tenant_client_uuid (tenant_id, client_uuid);
