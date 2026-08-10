-- Add project_id column to receive_voucher table
-- Run this query to add project support to receive vouchers

ALTER TABLE receive_voucher ADD COLUMN IF NOT EXISTS project_id INT NULL DEFAULT NULL AFTER dsr_no;

-- Add foreign key constraint if needed
ALTER TABLE receive_voucher 
ADD CONSTRAINT fk_receive_voucher_project 
FOREIGN KEY (project_id) REFERENCES projects(id) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_receive_voucher_project ON receive_voucher(project_id);

-- Update existing receive_edit.php if needed
-- This ensures edit mode also loads and saves project_id properly
