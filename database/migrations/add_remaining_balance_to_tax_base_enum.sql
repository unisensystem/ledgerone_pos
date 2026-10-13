-- Add remaining_balance to tax_base ENUM in tax_regimes
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
        'net_amount',
        'remaining_balance'
    ) NOT NULL;
