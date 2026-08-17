-- Add unit_id column to stock_opening table
ALTER TABLE stock_opening 
ADD COLUMN unit_id INT NULL AFTER opening_price,
ADD CONSTRAINT fk_stock_opening_unit FOREIGN KEY (unit_id) REFERENCES uom(id) ON DELETE SET NULL;
