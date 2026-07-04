-- Migration: Add engine_no and chassis_no to stock_transfer_items table
-- Description: Add engine number and chassis number fields for item tracking

ALTER TABLE `stock_transfer_items` ADD COLUMN `engine_no` VARCHAR(100) NULL AFTER `stock_value`;
ALTER TABLE `stock_transfer_items` ADD COLUMN `chassis_no` VARCHAR(100) NULL AFTER `engine_no`;

CREATE INDEX idx_stock_transfer_items_engine ON stock_transfer_items(engine_no);
CREATE INDEX idx_stock_transfer_items_chassis ON stock_transfer_items(chassis_no);
