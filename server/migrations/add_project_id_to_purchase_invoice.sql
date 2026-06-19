-- Add project_id column to purchase_invoice table
ALTER TABLE purchase_invoice 
ADD COLUMN project_id INT NULL AFTER sub_account_id,
ADD FOREIGN KEY (project_id) REFERENCES projects(id);

-- If you need to add it to purchase_order table as well (for order-add.php)
ALTER TABLE purchase_order 
ADD COLUMN project_id INT NULL AFTER currency_id,
ADD FOREIGN KEY (project_id) REFERENCES projects(id);
