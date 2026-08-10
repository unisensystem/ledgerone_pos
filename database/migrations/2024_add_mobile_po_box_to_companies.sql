-- Migration: Add mobile_no and po_box_no to Companies Table
ALTER TABLE `companies` ADD COLUMN IF NOT EXISTS `mobile_no` VARCHAR(30) NULL AFTER `phone`;
ALTER TABLE `companies` ADD COLUMN IF NOT EXISTS `po_box_no` VARCHAR(50) NULL AFTER `zipcode`;
