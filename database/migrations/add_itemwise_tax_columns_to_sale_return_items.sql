-- ============================================================
-- Migration: Item-wise taxation columns for sale_return_items
-- ============================================================
-- Verified against actual table structure (2026-05-17)
--
-- Actual columns present
-- ──────────────────────
--   id, tenant_id, sale_invoice_id, product_id, uom_id,
--   quantity, sale_price, gross_amount,
--   discount_percent, discount_amount, net_amount,
--   trade_offer_percent, trade_offer_amount,
--   gst_percent, gst_amount,          ← already exist
--   foc_quantity,
--   created_by, updated_by, created_at, updated_at
--
-- Added by this migration
-- ───────────────────────
--   tax_percent      (flexible tax %, replaces hard-coded gst_percent)
--   tax_amount       (calculated tax amount)
--   tax_regime_id    (FK → tax_regimes.id)
--   tax_rate_id      (FK → tax_rates.id)
--   tax_type         (standard_gst | further_tax | advance_income_tax | …)
--   tax_name         (human-readable label from tax_regimes.regime_name)
--   scheme           (pricing scheme: sale_on_tp | less | …)
--   parent_row_id    (child-product self-reference)
--   stock_status     (sellable | damaged — was written to stock_ledger only)
--   piece            (UOM breakdown)
--   carton           (UOM breakdown)
--   dozen            (UOM breakdown)
-- ============================================================


-- ------------------------------------------------------------
-- SECTION 1 : Core flexible-tax columns
--   tax_percent / tax_amount sit right after the legacy
--   gst_amount column so the two pairs stay together logically.
-- ------------------------------------------------------------

ALTER TABLE `sale_return_items`
    ADD COLUMN IF NOT EXISTS `tax_percent` DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT 'Flexible tax percentage (replaces hard-coded gst_percent for new tax regimes)'
        AFTER `gst_amount`,

    ADD COLUMN IF NOT EXISTS `tax_amount`  DECIMAL(15,2) NOT NULL DEFAULT 0.00
        COMMENT 'Calculated tax amount = (gross_amount - discount_amount) × tax_percent / 100'
        AFTER `tax_percent`;


-- ------------------------------------------------------------
-- SECTION 2 : Tax-regime linkage
--   Identifies exactly which regime and rate produced the
--   tax_percent / tax_amount values — enables full audit trail
--   and correct accounting reversal on returns.
-- ------------------------------------------------------------

ALTER TABLE `sale_return_items`
    ADD COLUMN IF NOT EXISTS `tax_regime_id` INT(11) DEFAULT NULL
        COMMENT 'FK → tax_regimes.id – which tax regime was applied to this item'
        AFTER `tax_amount`,

    ADD COLUMN IF NOT EXISTS `tax_rate_id`   INT(11) DEFAULT NULL
        COMMENT 'FK → tax_rates.id – specific rate record used at time of sale'
        AFTER `tax_regime_id`,

    ADD COLUMN IF NOT EXISTS `tax_type`      VARCHAR(50) NOT NULL DEFAULT 'standard_gst'
        COMMENT 'Tax classification: standard_gst | further_tax | advance_income_tax | exempt | zero_rated'
        AFTER `tax_rate_id`,

    ADD COLUMN IF NOT EXISTS `tax_name`      VARCHAR(255) DEFAULT NULL
        COMMENT 'Human-readable label copied from tax_regimes.regime_name at save time'
        AFTER `tax_type`;


-- ------------------------------------------------------------
-- SECTION 3 : Pricing scheme
--   Matches the ENUM on sale_invoice_items. Needed when
--   calculating the correct return value for scheme-priced items.
-- ------------------------------------------------------------

ALTER TABLE `sale_return_items`
    ADD COLUMN IF NOT EXISTS `scheme` ENUM('sale_on_tp','less','less_special','given')
        NOT NULL DEFAULT 'sale_on_tp'
        COMMENT 'Pricing scheme active on the original invoice line'
        AFTER `tax_name`;


-- ------------------------------------------------------------
-- SECTION 4 : Child-product linkage
--   Mirrors parent_row_id on sale_invoice_items.
-- ------------------------------------------------------------

ALTER TABLE `sale_return_items`
    ADD COLUMN IF NOT EXISTS `parent_row_id` INT(11) DEFAULT NULL
        COMMENT 'Self-referencing FK to sale_return_items.id for child/bundle products'
        AFTER `scheme`;


-- ------------------------------------------------------------
-- SECTION 5 : Stock return condition
--   return-add.php writes stock_status to stock_ledger but
--   never persisted it on the item row. This fixes that gap.
-- ------------------------------------------------------------

ALTER TABLE `sale_return_items`
    ADD COLUMN IF NOT EXISTS `stock_status` ENUM('sellable','damaged')
        NOT NULL DEFAULT 'sellable'
        COMMENT 'Condition of returned stock for warehouse re-entry'
        AFTER `foc_quantity`;


-- ------------------------------------------------------------
-- SECTION 6 : UOM breakdown columns
--   Mirrors piece / carton / dozen on sale_invoice_items for
--   consistent multi-unit quantity reporting across both tables.
-- ------------------------------------------------------------

ALTER TABLE `sale_return_items`
    ADD COLUMN IF NOT EXISTS `piece`  DECIMAL(15,4) DEFAULT NULL
        COMMENT 'Quantity expressed in pieces'
        AFTER `stock_status`,

    ADD COLUMN IF NOT EXISTS `carton` DECIMAL(15,4) DEFAULT NULL
        COMMENT 'Quantity expressed in cartons'
        AFTER `piece`,

    ADD COLUMN IF NOT EXISTS `dozen`  DECIMAL(15,4) DEFAULT NULL
        COMMENT 'Quantity expressed in dozens'
        AFTER `carton`;


-- ------------------------------------------------------------
-- SECTION 7 : Indexes
-- ------------------------------------------------------------

CREATE INDEX IF NOT EXISTS `idx_sri_tax_regime_id`
    ON `sale_return_items` (`tax_regime_id`);

CREATE INDEX IF NOT EXISTS `idx_sri_tax_rate_id`
    ON `sale_return_items` (`tax_rate_id`);

CREATE INDEX IF NOT EXISTS `idx_sri_parent_row_id`
    ON `sale_return_items` (`parent_row_id`);

CREATE INDEX IF NOT EXISTS `idx_sri_stock_status`
    ON `sale_return_items` (`stock_status`);


-- ------------------------------------------------------------
-- SECTION 8 : Optional foreign-key constraints
--   Uncomment after confirming InnoDB engine + matching charset.
-- ------------------------------------------------------------

-- ALTER TABLE `sale_return_items`
--     ADD CONSTRAINT `fk_sri_tax_regime`
--         FOREIGN KEY (`tax_regime_id`) REFERENCES `tax_regimes` (`id`)
--         ON DELETE SET NULL ON UPDATE CASCADE;

-- ALTER TABLE `sale_return_items`
--     ADD CONSTRAINT `fk_sri_tax_rate`
--         FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates` (`id`)
--         ON DELETE SET NULL ON UPDATE CASCADE;

-- ALTER TABLE `sale_return_items`
--     ADD CONSTRAINT `fk_sri_parent_row`
--         FOREIGN KEY (`parent_row_id`) REFERENCES `sale_return_items` (`id`)
--         ON DELETE SET NULL ON UPDATE CASCADE;
