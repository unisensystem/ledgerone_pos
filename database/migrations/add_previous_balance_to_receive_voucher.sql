-- Add previous_balance column to receive_voucher table
ALTER TABLE `receive_voucher` 
ADD COLUMN `previous_balance` DECIMAL(18, 2) DEFAULT 0 AFTER `amount`;

-- Create index for performance optimization
CREATE INDEX `idx_receive_voucher_previous_balance` ON `receive_voucher` (`previous_balance`);
