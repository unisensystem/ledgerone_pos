-- The "Enter Trade Price / MRP By [Unit]" feature (tenant_feature_flags.carton_price_entry)
-- already stores the raw originally-typed value for Trade Price and MRP in
-- trade_price_entry_value / mrp_entry_value, dividing the actual trade_price/mrp
-- columns down to the per-base-unit rate. Purchase Price never got the same
-- treatment, so a product bought in whole packs (e.g. a 50kg bag) priced via this
-- feature had no way to remember the raw per-pack purchase cost for re-editing.
ALTER TABLE products
  ADD COLUMN purchase_price_entry_value DECIMAL(15,3) NULL AFTER mrp_entry_value;
