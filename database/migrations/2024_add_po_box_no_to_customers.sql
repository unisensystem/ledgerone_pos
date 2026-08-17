-- Migration: Add po_box_no to customers table
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `po_box_no` VARCHAR(50) NULL AFTER `address`;
