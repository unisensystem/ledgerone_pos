-- Add project_id column to purchase_return table
ALTER TABLE purchase_return 
ADD COLUMN project_id INT NULL AFTER sub_account_id,
ADD FOREIGN KEY (project_id) REFERENCES projects(id);

-- Add project_id column to purchase_tax_invoice table (if not already added)
ALTER TABLE purchase_tax_invoice 
ADD COLUMN project_id INT NULL AFTER sub_account_id,
ADD FOREIGN KEY (project_id) REFERENCES projects(id);

-- Add project_id column to purchase_order table (if not already added)
ALTER TABLE purchase_order 
ADD COLUMN project_id INT NULL AFTER currency_id,
ADD FOREIGN KEY (project_id) REFERENCES projects(id);
