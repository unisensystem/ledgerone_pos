-- Update sale_invoice table: percent and amount columns to 3 decimal places
ALTER TABLE sale_invoice
    MODIFY COLUMN previous_balance        decimal(15,3) DEFAULT 0.000,
    MODIFY COLUMN total_discount_percent  decimal(5,3)  DEFAULT 0.000,
    MODIFY COLUMN withholding_tax_percent decimal(5,3)  DEFAULT 0.000,
    MODIFY COLUMN withholding_tax_amount  decimal(15,3) DEFAULT 0.000;

-- Update sale_invoice_items table: percent and foc columns to 3 decimal places
ALTER TABLE sale_invoice_items
    MODIFY COLUMN discount_percent    decimal(5,3)  DEFAULT 0.000,
    MODIFY COLUMN trade_offer_percent decimal(10,3) DEFAULT 0.000,
    MODIFY COLUMN gst_percent         decimal(10,3) DEFAULT 0.000,
    MODIFY COLUMN gst_amount          decimal(15,3) DEFAULT 0.000,
    MODIFY COLUMN tax_percent         decimal(10,3) DEFAULT 0.000,
    MODIFY COLUMN foc_quantity        decimal(15,3) DEFAULT 0.000;
