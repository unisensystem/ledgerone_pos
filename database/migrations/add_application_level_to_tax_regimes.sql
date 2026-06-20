-- Add application_level column to tax_regimes
-- Distinguishes whether a regime applies per item or at invoice level (on totals)

ALTER TABLE tax_regimes
ADD COLUMN application_level ENUM('item', 'invoice', 'both') NOT NULL DEFAULT 'item'
AFTER applies_at_stage;

-- Regimes with invoice-level tax_bases are invoice-level by nature
UPDATE tax_regimes
SET application_level = 'invoice'
WHERE tax_base IN ('total_bill', 'value_excl_sales_tax', 'net_amount');
