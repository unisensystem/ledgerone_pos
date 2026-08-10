-- Add container_id column to purchase_invoice table
ALTER TABLE purchase_invoice 
ADD COLUMN container_id INT NULL AFTER branch_id,
ADD CONSTRAINT fk_purchase_invoice_container 
FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE SET NULL;
