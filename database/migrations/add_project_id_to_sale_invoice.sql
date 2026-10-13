ALTER TABLE `sale_invoice`
ADD COLUMN `project_id` INT DEFAULT NULL AFTER `transport_name`,
ADD CONSTRAINT `fk_sale_invoice_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL;
