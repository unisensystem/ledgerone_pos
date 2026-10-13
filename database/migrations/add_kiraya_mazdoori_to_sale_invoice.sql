-- Adds "Kiraya" (freight/transport charge) and "Mazdoori" (labor charge) as
-- extra invoice-level summary amounts, mirroring how shipping_fees already
-- works on sale_invoice.
ALTER TABLE sale_invoice
    ADD COLUMN kiraya DECIMAL(15,3) NULL DEFAULT 0.000 AFTER shipping_fees,
    ADD COLUMN mazdoori DECIMAL(15,3) NULL DEFAULT 0.000 AFTER kiraya;
