-- Add bom_base_qty and batch_locked columns to bill_of_materials table

ALTER TABLE bill_of_materials 
ADD COLUMN bom_base_qty DECIMAL(10,2) DEFAULT 1.00 AFTER is_active,
ADD COLUMN batch_locked TINYINT(1) DEFAULT 0 AFTER bom_base_qty;
