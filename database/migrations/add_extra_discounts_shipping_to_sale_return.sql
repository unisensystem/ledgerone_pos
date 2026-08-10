-- Add Extra Discount 1, Extra Discount 2, and Shipping Fees to sale_return table
ALTER TABLE sale_return
    ADD COLUMN IF NOT EXISTS extra_discount_1_amount DECIMAL(15,4) NOT NULL DEFAULT 0.0000 AFTER total_discount_amount,
    ADD COLUMN IF NOT EXISTS extra_discount_2_amount DECIMAL(15,4) NOT NULL DEFAULT 0.0000 AFTER extra_discount_1_amount,
    ADD COLUMN IF NOT EXISTS shipping_fees           DECIMAL(15,4) NOT NULL DEFAULT 0.0000 AFTER extra_discount_2_amount;
