-- Add shipping_fees_type column to purchase_invoice table
ALTER TABLE purchase_invoice 
ADD COLUMN shipping_fees_type ENUM('add', 'subtract') DEFAULT 'add' AFTER shipping_fees;

-- Update existing records to have 'add' as default
UPDATE purchase_invoice SET shipping_fees_type = 'add' WHERE shipping_fees_type IS NULL;
