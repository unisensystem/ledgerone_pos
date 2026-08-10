-- Migration: Add invoice_type and due_date columns to sale_invoice
-- Date: 2026-05-12

ALTER TABLE `sale_invoice`
    ADD COLUMN `invoice_type` ENUM('Cash', 'Credit') NOT NULL DEFAULT 'Cash' AFTER `amount_paid_auto_fill`,
    ADD COLUMN `due_date` DATE NULL DEFAULT NULL AFTER `invoice_type`;
