-- Migration: Add invoice-level tax base options
-- Extends tax_base ENUM to support invoice-level calculation variables:
--   total_bill            = Total Bill (sum of all line items)
--   value_excl_sales_tax  = Total Bill minus Discount Amount
--   net_amount            = Net Amount after all deductions

ALTER TABLE tax_regimes
    MODIFY COLUMN tax_base ENUM(
        'trade_price',
        'mrp',
        'import_value',
        'turnover',
        'fixed_amount',
        'gross_receipt',
        'total_bill',
        'value_excl_sales_tax',
        'net_amount'
    ) NOT NULL;
