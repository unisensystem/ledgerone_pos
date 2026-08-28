-- Adds a flag + cross-link so a single real-world party can be registered
-- as both a Customer and a Supplier in one go, from the new combined
-- "Customer + Supplier (Both)" entry form.
ALTER TABLE customers ADD COLUMN is_both TINYINT(1) NOT NULL DEFAULT 0 AFTER is_out_station;
ALTER TABLE customers ADD COLUMN linked_supplier_id INT(11) NULL DEFAULT NULL AFTER is_both;

ALTER TABLE suppliers ADD COLUMN is_both TINYINT(1) NOT NULL DEFAULT 0 AFTER is_blacklisted;
ALTER TABLE suppliers ADD COLUMN linked_customer_id INT(11) NULL DEFAULT NULL AFTER is_both;
