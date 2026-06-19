-- Add project_id column to purchase_order table
ALTER TABLE purchase_order 
ADD COLUMN project_id INT UNSIGNED NULL AFTER branch_id,
ADD CONSTRAINT fk_purchase_order_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;
