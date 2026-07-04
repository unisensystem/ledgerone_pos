-- Migration: Add engine_no and chassis_no to outward_gatepass_items table
-- Description: Add engine number and chassis number fields for item tracking in outward gatepass

ALTER TABLE `outward_gatepass_items` ADD COLUMN `engine_no` VARCHAR(100) NULL AFTER `quantity`;
ALTER TABLE `outward_gatepass_items` ADD COLUMN `chassis_no` VARCHAR(100) NULL AFTER `engine_no`;

CREATE INDEX idx_outward_gatepass_items_engine ON outward_gatepass_items(engine_no);
CREATE INDEX idx_outward_gatepass_items_chassis ON outward_gatepass_items(chassis_no);
