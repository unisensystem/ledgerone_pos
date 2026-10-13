-- Migration: Add scheme column to sale_invoice_items table
-- Purpose: Store the scheme type (sale_on_tp, less, less_special, given) for each invoice item

ALTER TABLE `sale_invoice_items` 
ADD COLUMN `scheme` ENUM('sale_on_tp', 'less', 'less_special', 'given') DEFAULT 'sale_on_tp' 
AFTER `trade_offer_amount`;
