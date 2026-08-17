-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 31.97.123.46:3306
-- Generation Time: Apr 06, 2026 at 03:43 PM
-- Server version: 10.5.27-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ledgerone_public`
--

-- --------------------------------------------------------

--
-- Table structure for table `billing_logs`
--

CREATE TABLE `billing_logs` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `plan_id` int(11) NOT NULL,
  `billing_cycle_start` date DEFAULT NULL,
  `billing_cycle_end` date DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `amount_due` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `payment_method` varchar(50) NOT NULL,
  `payment_gateway` varchar(50) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `gateway_fee` decimal(10,2) DEFAULT 0.00,
  `gateway_transaction_url` varchar(500) DEFAULT NULL,
  `payment_status` enum('pending','success','failed','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
  `refund_amount` decimal(10,2) DEFAULT 0.00,
  `refund_reason` text DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `refunded_by` int(11) DEFAULT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `receipt_voucher_code` varchar(50) DEFAULT NULL,
  `is_recorded_in_erp` tinyint(1) DEFAULT 0,
  `erp_recorded_at` timestamp NULL DEFAULT NULL,
  `erp_recorded_by` int(11) DEFAULT NULL,
  `invoice_url` varchar(500) DEFAULT NULL,
  `invoice_sent_at` timestamp NULL DEFAULT NULL,
  `receipt_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `id` int(11) NOT NULL,
  `code` char(3) NOT NULL,
  `name` varchar(50) NOT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_base_currency` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `currencies`
--
DELIMITER $$
CREATE TRIGGER `enforce_single_base_currency_insert` BEFORE INSERT ON `currencies` FOR EACH ROW BEGIN
    IF NEW.is_base_currency = 1 THEN
        UPDATE currencies SET is_base_currency = 0 WHERE is_base_currency = 1;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `enforce_single_base_currency_update` BEFORE UPDATE ON `currencies` FOR EACH ROW BEGIN
    IF NEW.is_base_currency = 1 AND OLD.is_base_currency = 0 THEN
        UPDATE currencies SET is_base_currency = 0 WHERE id != NEW.id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `currency_exchange_rates`
--

CREATE TABLE `currency_exchange_rates` (
  `id` int(11) NOT NULL,
  `base_currency_code` char(3) NOT NULL,
  `target_currency_code` char(3) NOT NULL,
  `exchange_rate` decimal(18,8) NOT NULL,
  `effective_date` date NOT NULL,
  `source` varchar(50) DEFAULT 'api',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `plan_type` enum('subscription','one_time','lifetime') NOT NULL DEFAULT 'subscription',
  `monthly_price` decimal(10,2) DEFAULT 0.00,
  `annual_price` decimal(10,2) DEFAULT 0.00,
  `lifetime_price` decimal(10,2) DEFAULT 0.00,
  `billing_cycles_available` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`billing_cycles_available`)),
  `one_time_duration_months` int(11) DEFAULT NULL,
  `currency` char(3) DEFAULT 'USD',
  `max_users` int(11) DEFAULT 5,
  `max_companies` int(11) DEFAULT 1,
  `max_storage_mb` int(11) DEFAULT 500,
  `max_monthly_transactions` int(11) DEFAULT 1000,
  `max_api_calls_per_hour` int(11) DEFAULT 100,
  `feature_flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`feature_flags`)),
  `is_active` tinyint(1) DEFAULT 1,
  `is_public` tinyint(1) DEFAULT 1,
  `is_custom` tinyint(1) DEFAULT 0,
  `min_commitment_months` int(11) DEFAULT 0,
  `setup_fee` decimal(10,2) DEFAULT 0.00,
  `sort_order` int(11) DEFAULT 0,
  `trial_days` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `previous_plan_id` int(11) DEFAULT NULL,
  `plan_changed_at` timestamp NULL DEFAULT NULL,
  `billing_cycle` enum('monthly','annual') DEFAULT NULL,
  `billing_type` enum('recurring','one_time','lifetime') NOT NULL DEFAULT 'recurring',
  `auto_renew` tinyint(1) DEFAULT 1,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `grace_period_days` int(11) DEFAULT 7,
  `grace_period_ends_at` date DEFAULT NULL,
  `trial_ends_at` date DEFAULT NULL,
  `next_billing_date` date DEFAULT NULL,
  `last_payment_date` date DEFAULT NULL,
  `payment_failed_count` int(11) DEFAULT 0,
  `payment_failed_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','trialing','past_due','canceled','expired','paused','lifetime') NOT NULL DEFAULT 'trialing',
  `canceled_at` timestamp NULL DEFAULT NULL,
  `paused_at` timestamp NULL DEFAULT NULL,
  `resume_at` date DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `country` varchar(2) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `status` enum('pending','active','suspended','deactivated') DEFAULT 'pending',
  `is_suspended` tinyint(1) DEFAULT 0,
  `suspended_reason` text DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `timezone` varchar(50) DEFAULT 'UTC',
  `base_currency` char(3) DEFAULT 'USD',
  `language` varchar(10) DEFAULT 'en',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `activated_at` timestamp NULL DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `billing_logs`
--
ALTER TABLE `billing_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_invoice_number` (`invoice_number`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `fk_billing_logs_plan` (`plan_id`),
  ADD KEY `idx_tenant_payment_status` (`tenant_id`,`payment_status`,`created_at`),
  ADD KEY `idx_subscription_id` (`subscription_id`),
  ADD KEY `idx_refunded_at` (`refunded_at`),
  ADD KEY `idx_invoice_sent` (`invoice_sent_at`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_receipt_voucher_code` (`receipt_voucher_code`),
  ADD KEY `idx_erp_reconciliation` (`is_recorded_in_erp`,`erp_recorded_at`),
  ADD KEY `idx_tenant_status_date` (`tenant_id`,`payment_status`,`created_at`),
  ADD KEY `idx_gateway_status` (`payment_gateway`,`payment_status`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `currency_exchange_rates`
--
ALTER TABLE `currency_exchange_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rate_per_day` (`base_currency_code`,`target_currency_code`,`effective_date`),
  ADD KEY `idx_effective_date` (`effective_date`),
  ADD KEY `idx_currency_pair` (`base_currency_code`,`target_currency_code`),
  ADD KEY `target_currency_code` (`target_currency_code`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_sort_order` (`sort_order`),
  ADD KEY `idx_is_public` (`is_public`),
  ADD KEY `idx_plan_type` (`plan_type`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_active_subscription` (`tenant_id`,`status`),
  ADD KEY `idx_tenant_status` (`tenant_id`,`status`),
  ADD KEY `idx_end_date` (`end_date`),
  ADD KEY `idx_next_billing` (`next_billing_date`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `idx_tenant_status_active` (`tenant_id`,`status`,`end_date`),
  ADD KEY `idx_billing_type` (`billing_type`),
  ADD KEY `idx_grace_period` (`grace_period_ends_at`),
  ADD KEY `idx_payment_failed` (`payment_failed_count`,`payment_failed_at`),
  ADD KEY `fk_subscriptions_previous_plan` (`previous_plan_id`),
  ADD KEY `idx_trial_expiry` (`trial_ends_at`,`status`),
  ADD KEY `idx_active_by_date` (`status`,`end_date`,`tenant_id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_contact_email` (`contact_email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_trial_ends` (`trial_ends_at`),
  ADD KEY `idx_deleted_at` (`deleted_at`),
  ADD KEY `idx_contact_email` (`contact_email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `billing_logs`
--
ALTER TABLE `billing_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currency_exchange_rates`
--
ALTER TABLE `currency_exchange_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `billing_logs`
--
ALTER TABLE `billing_logs`
  ADD CONSTRAINT `fk_billing_logs_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`),
  ADD CONSTRAINT `fk_billing_logs_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_billing_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `currency_exchange_rates`
--
ALTER TABLE `currency_exchange_rates`
  ADD CONSTRAINT `currency_exchange_rates_ibfk_1` FOREIGN KEY (`base_currency_code`) REFERENCES `currencies` (`code`),
  ADD CONSTRAINT `currency_exchange_rates_ibfk_2` FOREIGN KEY (`target_currency_code`) REFERENCES `currencies` (`code`);

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `fk_subscriptions_previous_plan` FOREIGN KEY (`previous_plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;