-- Migration: Add cross-party adjustment support to journal_voucher
-- Run this SQL in your database

ALTER TABLE journal_voucher
    ADD COLUMN IF NOT EXISTS adj_mode VARCHAR(10) DEFAULT 'single' COMMENT 'single or cross',
    ADD COLUMN IF NOT EXISTS party_type2 VARCHAR(20) DEFAULT NULL COMMENT 'customer or supplier (cross-party)',
    ADD COLUMN IF NOT EXISTS party_id2 INT DEFAULT NULL COMMENT 'Party 2 ID (cross-party)';
