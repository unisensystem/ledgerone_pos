-- Migration: Add footer_note and party_type to quotations
-- Run once against your database

ALTER TABLE `quotations`
  ADD COLUMN `footer_note` TEXT DEFAULT NULL COMMENT 'Closing/footer message printed on quotation' AFTER `remarks`,
  ADD COLUMN `party_type`  ENUM('OEM','Vendor','Customer') DEFAULT NULL COMMENT 'Type of party receiving the quotation' AFTER `footer_note`;
