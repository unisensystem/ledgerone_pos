-- Migration: Add is_out_station column to customers table
-- Date: 2025

ALTER TABLE `customers`
ADD COLUMN `is_out_station` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_blacklisted`;
