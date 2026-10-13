-- Add Extra Discount 1 and Extra Discount 2 fields to sale_invoice table
ALTER TABLE sale_invoice
    ADD COLUMN extra_discount_1_percent DECIMAL(10,4) NOT NULL DEFAULT 0.0000 AFTER total_discount_amount,
    ADD COLUMN extra_discount_1_amount  DECIMAL(15,4) NOT NULL DEFAULT 0.0000 AFTER extra_discount_1_percent,
    ADD COLUMN extra_discount_2_percent DECIMAL(10,4) NOT NULL DEFAULT 0.0000 AFTER extra_discount_1_amount,
    ADD COLUMN extra_discount_2_amount  DECIMAL(15,4) NOT NULL DEFAULT 0.0000 AFTER extra_discount_2_percent;
