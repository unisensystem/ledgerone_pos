-- Update sale invoice items table to support 3 decimal places
ALTER TABLE sale_invoice_items 
MODIFY COLUMN sale_price decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN gross_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN discount_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN sales_tax decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN trade_offer_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN net_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN tax_amount decimal(15,3) DEFAULT 0.00;

-- Update sale invoice table
ALTER TABLE sale_invoices 
MODIFY COLUMN total_bill decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN total_discount_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN total_tax_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN net_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN shipping_fees decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN extra_discount_1_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN extra_discount_2_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN amount_paid decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN remaining_balance decimal(15,3) DEFAULT 0.00;

-- Update sale order items table
ALTER TABLE sale_order_items 
MODIFY COLUMN sale_price decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN gross_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN discount_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN sales_tax decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN trade_offer_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN net_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN tax_amount decimal(15,3) DEFAULT 0.00;

-- Update sale order table
ALTER TABLE sale_orders 
MODIFY COLUMN total_bill decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN total_discount_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN total_tax_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN net_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN shipping_fees decimal(15,3) DEFAULT 0.00;

-- Update sale return items table
ALTER TABLE sale_return_items 
MODIFY COLUMN sale_price decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN gross_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN discount_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN sales_tax decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN trade_offer_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN net_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN tax_amount decimal(15,3) DEFAULT 0.00;

-- Update sale return table
ALTER TABLE sale_returns 
MODIFY COLUMN total_bill decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN total_discount_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN total_tax_amount decimal(15,3) DEFAULT 0.00,
MODIFY COLUMN net_amount decimal(15,3) DEFAULT 0.00;
