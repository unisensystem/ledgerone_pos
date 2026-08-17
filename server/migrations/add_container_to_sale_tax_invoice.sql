-- Add container_id column to sale_invoice table
ALTER TABLE sale_invoice 
ADD COLUMN container_id INT NULL AFTER branch_id,
ADD CONSTRAINT fk_sale_invoice_container 
FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE SET NULL;
