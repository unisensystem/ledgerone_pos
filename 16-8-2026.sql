
ALTER TABLE stock_opening
    ADD COLUMN batch_no VARCHAR(100) NULL AFTER opening_price,
    ADD COLUMN batch_expiry_date DATE NULL AFTER batch_no;

ALTER TABLE stock_ledger
    ADD COLUMN batch_no VARCHAR(100) NULL AFTER unit_cost,
    ADD COLUMN batch_expiry_date DATE NULL AFTER batch_no;


ALTER TABLE sale_invoice_items
    ADD COLUMN batch_no VARCHAR(100) NULL AFTER uom_id,
    ADD COLUMN batch_expiry_date DATE NULL AFTER batch_no;

ALTER TABLE `customers`
  ADD COLUMN `license_no` VARCHAR(100) NULL AFTER `identity_card_no`;
 
ALTER TABLE `sale_invoice`
  ADD COLUMN `ship_to` TEXT NULL AFTER `remarks`;
