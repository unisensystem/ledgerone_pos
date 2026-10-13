-- Update purchase invoice items table to support 3 decimal places
ALTER TABLE purchase_invoice_items 
MODIFY COLUMN purchase_price decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN gross_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN discount_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN sales_tax decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN tp_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN net_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN trade_offer_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN gst_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN rp_unit_price decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN tp_unit_price decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN rp_total_value decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN tp_total_value decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN tax_amount decimal(15,3) DEFAULT 0.00;

-- Update purchase invoice table
ALTER TABLE purchase_invoices 
MODIFY COLUMN total_bill decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN total_discount_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN total_tax_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN net_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN shipping_fees decimal(15,3) DEFAULT 0.00;

-- Update purchase order items table
ALTER TABLE purchase_order_items 
MODIFY COLUMN purchase_price decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN gross_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN discount_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN sales_tax decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN tp_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN net_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN trade_offer_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN gst_amount decimal(15,3) DEFAULT 0.00;

-- Update purchase order table
ALTER TABLE purchase_orders 
MODIFY COLUMN total_bill decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN total_discount_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN total_tax_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN net_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN shipping_fees decimal(15,3) DEFAULT 0.00;
