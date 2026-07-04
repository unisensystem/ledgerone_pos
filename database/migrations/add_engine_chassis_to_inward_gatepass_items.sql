-- Migration: Add engine_no and chassis_no to inward_gatepass_items table
-- Description: Add engine number and chassis number fields for item tracking in inward gatepass

ALTER TABLE `inward_gatepass_items` ADD COLUMN `engine_no` VARCHAR(100) NULL AFTER `quantity`;
ALTER TABLE `inward_gatepass_items` ADD COLUMN `chassis_no` VARCHAR(100) NULL AFTER `engine_no`;

CREATE INDEX idx_inward_gatepass_items_engine ON inward_gatepass_items(engine_no);
CREATE INDEX idx_inward_gatepass_items_chassis ON inward_gatepass_items(chassis_no);
