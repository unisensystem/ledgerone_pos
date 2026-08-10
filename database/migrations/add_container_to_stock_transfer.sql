-- Migration: Add container_id to stock_transfer table
-- Description: Add optional container tracking to stock transfer module

ALTER TABLE `stock_transfer` ADD COLUMN `container_id` INT(11) DEFAULT NULL AFTER `to_location_id`;

ALTER TABLE `stock_transfer` ADD CONSTRAINT `fk_st_container` FOREIGN KEY (`container_id`) REFERENCES `containers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
