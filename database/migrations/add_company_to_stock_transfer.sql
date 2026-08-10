ALTER TABLE stock_transfer ADD COLUMN company_id INT NULL AFTER tenant_id;
CREATE INDEX idx_stock_transfer_company ON stock_transfer(company_id);
