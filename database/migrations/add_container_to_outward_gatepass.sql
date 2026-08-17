-- Migration: Add container_id to outward_gatepass table
-- Description: Add optional container tracking to outward gatepass module

ALTER TABLE `outward_gatepass` ADD COLUMN `container_id` INT(11) DEFAULT NULL AFTER `branch_id`;

ALTER TABLE `outward_gatepass` ADD CONSTRAINT `fk_ogp_container` FOREIGN KEY (`container_id`) REFERENCES `containers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
