-- Adds a per-item "Extra Discount" amount column for med_pos_invoice's item table,
-- applied after the normal discount and before trade offer/tax in net amount calc.
ALTER TABLE sale_invoice_items
    ADD COLUMN extra_discount_amount DECIMAL(15,3) NULL DEFAULT 0.000 AFTER discount_amount;
