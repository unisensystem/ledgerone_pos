-- Add Engine No and Chassis No columns to purchase_invoice_items table
ALTER TABLE purchase_invoice_items 
ADD COLUMN engine_no VARCHAR(100) NULL AFTER quantity,
ADD COLUMN chassis_no VARCHAR(100) NULL AFTER engine_no,
ADD INDEX idx_engine_no (engine_no),
ADD INDEX idx_chassis_no (chassis_no);
