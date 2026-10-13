-- Migration: Add container_id to inward_gatepass table
-- Description: Add optional container tracking to inward gatepass module

ALTER TABLE `inward_gatepass` ADD COLUMN `container_id` INT(11) DEFAULT NULL AFTER `branch_id`;

ALTER TABLE `inward_gatepass` ADD CONSTRAINT `fk_igp_container` FOREIGN KEY (`container_id`) REFERENCES `containers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
