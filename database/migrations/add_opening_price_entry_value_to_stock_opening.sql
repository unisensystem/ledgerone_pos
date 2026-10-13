-- Stores the exact raw value the user typed for Opening Price (e.g. Carton price 2000),
-- before it was converted down to per-Piece for the `opening_price` column - so
-- re-opening the product for edit shows the original number back instead of
-- re-dividing an already-converted value a second time.
ALTER TABLE stock_opening ADD COLUMN opening_price_entry_value DECIMAL(15,3) NULL DEFAULT NULL AFTER expiry_date;
