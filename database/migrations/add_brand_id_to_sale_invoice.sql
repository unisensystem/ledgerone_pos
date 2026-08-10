-- Add brand_id column to sale_invoice table
ALTER TABLE sale_invoice ADD COLUMN brand_id INT NULL AFTER supplier_man_id;

-- Add foreign key constraint
ALTER TABLE sale_invoice ADD CONSTRAINT fk_sale_invoice_brand 
FOREIGN KEY (brand_id) REFERENCES suppliers(id) ON DELETE SET NULL;

-- Create index for better query performance
CREATE INDEX idx_sale_invoice_brand_id ON sale_invoice(brand_id);
