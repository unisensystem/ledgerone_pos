-- Add Finished Good wastage columns to production_wastage
-- Run this against your tenant database

ALTER TABLE `production_wastage`
  ADD COLUMN `fg_wastage_type`  ENUM('percentage','quantity') DEFAULT NULL AFTER `wastage_type`,
  ADD COLUMN `fg_wastage_input` DECIMAL(10,4)                DEFAULT NULL AFTER `fg_wastage_type`,
  ADD COLUMN `fg_wastage_qty`   DECIMAL(10,2)                DEFAULT NULL AFTER `fg_wastage_input`;
