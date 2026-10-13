-- Table: production_expenses
CREATE TABLE `production_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `narration` text DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('Draft','Posted','Cancelled') NOT NULL DEFAULT 'Posted',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_production_order_id` (`production_order_id`),
  KEY `idx_entry_date` (`entry_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: production_expense_items
CREATE TABLE `production_expense_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_id` int(11) NOT NULL,
  `gl_account_id` int(11) NOT NULL,
  `expense_type` enum('Labor','Overhead','Direct Material','Other') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `hours` decimal(10,2) DEFAULT NULL,
  `rate` decimal(15,2) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `payment_status` enum('paid','accrued') NOT NULL DEFAULT 'paid',
  `payment_mode` enum('cash','bank','cheque') DEFAULT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `cheque_no` varchar(50) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expense_id` (`expense_id`),
  KEY `idx_gl_account_id` (`gl_account_id`),
  KEY `idx_vendor_id` (`vendor_id`),
  KEY `idx_payment_status` (`payment_status`),
  CONSTRAINT `fk_expense_items_expense` FOREIGN KEY (`expense_id`) REFERENCES `production_expenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
