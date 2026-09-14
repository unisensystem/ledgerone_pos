-- Adds a structured weight (numeric value + unit) to products.
-- Replaces the old free-text `weight` field referenced by product-add.php's
-- server API, which was never actually backed by a real column (that INSERT
-- was failing silently against products.weight, which never existed).
ALTER TABLE products
    ADD COLUMN weight_value DECIMAL(15,4) NULL DEFAULT NULL AFTER description,
    ADD COLUMN weight_unit VARCHAR(20) NULL DEFAULT NULL AFTER weight_value;
