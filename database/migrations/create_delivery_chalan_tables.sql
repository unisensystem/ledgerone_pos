-- ============================================================
-- Migration: Create delivery_chalan and delivery_chalan_items
-- ============================================================

CREATE TABLE IF NOT EXISTS `delivery_chalan` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `tenant_id`        INT UNSIGNED    NOT NULL,
    `chalan_no`        VARCHAR(20)     NOT NULL,
    `sale_invoice_id`  INT UNSIGNED    DEFAULT NULL COMMENT 'Reference to sale_invoice.id',
    `delivery_date`    DATE            NOT NULL,
    `customer_id`      INT UNSIGNED    NOT NULL,
    `company_id`       INT UNSIGNED    DEFAULT NULL,
    `branch_id`        INT UNSIGNED    NOT NULL,
    `currency_id`      INT UNSIGNED    DEFAULT NULL,
    `sale_officer_id`  INT UNSIGNED    DEFAULT NULL,
    `supplier_man_id`  INT UNSIGNED    DEFAULT NULL,
    `bilty_no`         VARCHAR(100)    DEFAULT NULL,
    `transport_name`   VARCHAR(200)    DEFAULT NULL,
    `remarks`          TEXT            DEFAULT NULL,
    `status`           ENUM('Posted','Draft') NOT NULL DEFAULT 'Posted',
    `created_by`       INT UNSIGNED    DEFAULT NULL,
    `updated_by`       INT UNSIGNED    DEFAULT NULL,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tenant_chalan_no` (`tenant_id`, `chalan_no`),
    KEY `idx_tenant_customer`  (`tenant_id`, `customer_id`),
    KEY `idx_tenant_branch`    (`tenant_id`, `branch_id`),
    KEY `idx_sale_invoice`     (`sale_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `delivery_chalan_items` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`            INT UNSIGNED NOT NULL,
    `delivery_chalan_id`   INT UNSIGNED NOT NULL,
    `product_id`           INT UNSIGNED NOT NULL,
    `uom_id`               INT UNSIGNED DEFAULT NULL,
    `quantity`             DECIMAL(15,4) NOT NULL DEFAULT 0,
    `created_by`           INT UNSIGNED DEFAULT NULL,
    `updated_by`           INT UNSIGNED DEFAULT NULL,
    `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_chalan_id` (`delivery_chalan_id`),
    KEY `idx_tenant_product` (`tenant_id`, `product_id`),
    CONSTRAINT `fk_dci_chalan` FOREIGN KEY (`delivery_chalan_id`) REFERENCES `delivery_chalan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
