-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 31.97.123.46:3306
-- Generation Time: Apr 06, 2026 at 03:44 PM
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
-- Database: `ledgerone_tenant`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounting_ledger`
--

CREATE TABLE `accounting_ledger` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `transaction_type` varchar(50) NOT NULL,
  `reference_table` varchar(100) NOT NULL,
  `reference_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `description` text DEFAULT NULL,
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `sub_account_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts_head`
--

CREATE TABLE `accounts_head` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `city_zone_id` int(11) NOT NULL,
  `area_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `check_in_time` datetime NOT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `bank_name` varchar(100) NOT NULL,
  `is_mfb` tinyint(1) DEFAULT 0,
  `mfb_name` varchar(50) DEFAULT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_type` enum('current','savings','checking','credit_card','loan','mfb_account','branchless','other') NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'PKR',
  `branch_name` varchar(100) NOT NULL,
  `branch_code` varchar(20) DEFAULT NULL,
  `branch_city` varchar(50) NOT NULL,
  `branch_state` varchar(50) NOT NULL,
  `branch_address` text DEFAULT NULL,
  `account_title` varchar(100) DEFAULT NULL,
  `iban` varchar(34) DEFAULT NULL,
  `swift_code` varchar(11) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `bank_logo_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `balance_type` enum('debit','credit') DEFAULT 'debit',
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `debit_amount` decimal(15,2) DEFAULT 0.00,
  `credit_amount` decimal(15,2) DEFAULT 0.00,
  `as_of_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `account_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `bank_accounts`
--
DELIMITER $$
CREATE TRIGGER `trg_after_bank_account_update` AFTER UPDATE ON `bank_accounts` FOR EACH ROW BEGIN
    -- Only process if balance fields changed
    IF OLD.debit_amount != NEW.debit_amount OR OLD.credit_amount != NEW.credit_amount THEN
        -- Calculate balance change
        SET @old_balance = OLD.debit_amount - OLD.credit_amount;
        SET @new_balance = NEW.debit_amount - NEW.credit_amount;
        SET @balance_change = @new_balance - @old_balance;
        
        -- Only create entry if there's a significant change
        IF ABS(@balance_change) > 0.01 THEN
            IF @balance_change > 0 THEN
                -- Debit Bank Account
                INSERT INTO accounting_ledger (transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
                VALUES ('Bank Balance Adjustment', 'bank_accounts', NEW.id, NEW.account_id, CURDATE(), CONCAT('Balance adjustment - ', NEW.bank_name), ABS(@balance_change), 0);
            ELSE
                -- Credit Bank Account
                INSERT INTO accounting_ledger (transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
                VALUES ('Bank Balance Adjustment', 'bank_accounts', NEW.id, NEW.account_id, CURDATE(), CONCAT('Balance adjustment - ', NEW.bank_name), 0, ABS(@balance_change));
            END IF;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `bill_of_materials`
--

CREATE TABLE `bill_of_materials` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `bom_code` varchar(50) NOT NULL,
  `finished_good_id` int(11) NOT NULL,
  `version` varchar(20) DEFAULT '1.0',
  `is_active` tinyint(1) DEFAULT 1,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bom_materials`
--

CREATE TABLE `bom_materials` (
  `id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `raw_material_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `parent_branch_id` int(11) DEFAULT NULL,
  `branch_code` varchar(50) NOT NULL,
  `branch_name` varchar(255) NOT NULL,
  `branch_type` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `zipcode` varchar(20) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `allows_sales` tinyint(1) DEFAULT 1,
  `allows_inventory` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cash_opening`
--

CREATE TABLE `cash_opening` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `as_of_date` date NOT NULL COMMENT 'The date for which opening cash is recorded',
  `opening_amount` decimal(15,2) NOT NULL COMMENT 'Opening cash amount',
  `currency` varchar(3) DEFAULT 'USD' COMMENT 'Currency code (USD, EUR, etc.)',
  `is_locked` tinyint(1) DEFAULT 0 COMMENT 'If TRUE, form should be locked',
  `entered_by` varchar(100) NOT NULL DEFAULT 'System' COMMENT 'User who created the record',
  `entered_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the record was created',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores opening cash in hand records';

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `region_id` int(11) NOT NULL,
  `city_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `city_zones`
--

CREATE TABLE `city_zones` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  `city_zone_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_code` varchar(50) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `zipcode` varchar(20) DEFAULT NULL,
  `industry_type` varchar(100) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `tax_identification_number` varchar(100) DEFAULT NULL,
  `sales_tax_number` varchar(100) DEFAULT NULL,
  `logo_url` text DEFAULT NULL,
  `language_code` varchar(10) DEFAULT 'en',
  `timezone` varchar(50) DEFAULT 'UTC',
  `currency_code` char(3) DEFAULT 'USD',
  `inventory_valuation_method` enum('FIFO','LIFO','AVCO','SPECIFIC_IDENTIFICATION') DEFAULT 'FIFO',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `completed_products`
--

CREATE TABLE `completed_products` (
  `id` int(11) NOT NULL,
  `production_completion_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `planned_qty` decimal(15,2) NOT NULL,
  `remaining_qty` decimal(15,2) NOT NULL,
  `completed_qty` decimal(15,2) NOT NULL,
  `uom_id` int(11) DEFAULT NULL,
  `unit_cost` decimal(15,2) NOT NULL,
  `total_cost` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cost_centers`
--

CREATE TABLE `cost_centers` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `country_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `credit_purchase_history`
--

CREATE TABLE `credit_purchase_history` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `integration_type` enum('whatsapp','sms','email') NOT NULL,
  `credits_purchased` int(11) NOT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `customer_code` varchar(20) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `primary_phone` varchar(15) DEFAULT NULL,
  `secondary_phone` varchar(15) DEFAULT NULL,
  `identity_card_no` varchar(15) DEFAULT NULL,
  `email` varchar(300) DEFAULT NULL,
  `opening_debit_amount` decimal(15,2) DEFAULT 0.00,
  `opening_credit_amount` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `is_blacklisted` tinyint(1) DEFAULT 0,
  `status` varchar(20) DEFAULT 'ACTIVE' CHECK (`status` in ('ACTIVE','INACTIVE','SUSPENDED','BLACKLISTED')),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `country_id` int(11) DEFAULT NULL,
  `region_id` int(11) DEFAULT NULL,
  `city_id` int(11) DEFAULT NULL,
  `city_zone_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `is_sales_tax_registered` tinyint(1) DEFAULT 0,
  `strn` varchar(50) DEFAULT NULL,
  `is_filer` tinyint(1) DEFAULT 0,
  `ntn` varchar(50) DEFAULT NULL,
  `advance_income_tax_percentage` decimal(10,2) DEFAULT 0.00,
  `default_discount_percentage` decimal(10,2) DEFAULT 0.00,
  `credit_limit` decimal(10,2) DEFAULT 0.00,
  `credit_period_limit_days` int(11) DEFAULT NULL,
  `is_wholesaler` tinyint(1) DEFAULT 0,
  `associated_sales_officer_id` int(11) DEFAULT NULL,
  `customer_type_id` int(11) DEFAULT NULL,
  `supplier_man_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `customers`
--
DELIMITER $$
CREATE TRIGGER `trg_customers_balance_insert` BEFORE INSERT ON `customers` FOR EACH ROW BEGIN
    -- Calculate current balance based on debit and credit amounts
    SET NEW.current_balance = COALESCE(NEW.opening_debit_amount, 0) - COALESCE(NEW.opening_credit_amount, 0);
    
    -- Auto-set status based on blacklist flag
    IF NEW.is_blacklisted = TRUE THEN
        SET NEW.status = 'BLACKLISTED';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_customers_balance_update` BEFORE UPDATE ON `customers` FOR EACH ROW BEGIN
    -- Calculate current balance based on debit and credit amounts
    SET NEW.current_balance = COALESCE(NEW.opening_debit_amount, 0) - COALESCE(NEW.opening_credit_amount, 0);
    
    -- Auto-update status based on blacklist flag
    IF NEW.is_blacklisted = TRUE THEN
        SET NEW.status = 'BLACKLISTED';
    ELSEIF NEW.is_blacklisted = FALSE AND OLD.status = 'BLACKLISTED' THEN
        SET NEW.status = 'ACTIVE';
    END IF;
    
    -- Update timestamp
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `customer_entry`
--

CREATE TABLE `customer_entry` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `phone_number` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_sub_accounts`
--

CREATE TABLE `customer_sub_accounts` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `sub_account_name` varchar(255) NOT NULL,
  `debit` decimal(10,2) DEFAULT 0.00,
  `credit` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_types`
--

CREATE TABLE `customer_types` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL DEFAULT 0 COMMENT '0 = System, >0 = Tenant specific',
  `type_name` varchar(100) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `department_code` varchar(10) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `department_head_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `parent_department_id` int(11) DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_integration`
--

CREATE TABLE `email_integration` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `from_email` varchar(255) DEFAULT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `encryption` varchar(10) DEFAULT NULL,
  `enabled_modules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`enabled_modules`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `employee_id` varchar(50) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `marital_status` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `personal_email` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `profile_photo_url` text DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `employment_type` varchar(20) NOT NULL,
  `employee_category` varchar(20) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `probation_end_date` date DEFAULT NULL,
  `work_location` varchar(20) DEFAULT NULL,
  `shift_timing` varchar(30) DEFAULT NULL,
  `reporting_manager_id` int(11) DEFAULT NULL,
  `work_hours_per_week` decimal(5,2) DEFAULT NULL,
  `work_days_per_week` int(11) DEFAULT NULL,
  `current_status` varchar(20) NOT NULL DEFAULT 'active',
  `status_effective_date` date DEFAULT curdate(),
  `status_change_reason` text DEFAULT NULL,
  `leave_balance_paid` int(11) DEFAULT 0,
  `leave_balance_sick` int(11) DEFAULT 0,
  `leave_balance_unpaid` int(11) DEFAULT 0,
  `current_leave_id` int(11) DEFAULT NULL,
  `is_suspended` tinyint(1) DEFAULT 0,
  `suspension_start_date` date DEFAULT NULL,
  `suspension_end_date` date DEFAULT NULL,
  `suspension_type` varchar(30) DEFAULT NULL,
  `suspension_reason` text DEFAULT NULL,
  `is_terminated` tinyint(1) DEFAULT 0,
  `termination_date` date DEFAULT NULL,
  `termination_type` varchar(30) DEFAULT NULL,
  `termination_reason` text DEFAULT NULL,
  `notice_period` varchar(20) DEFAULT NULL,
  `exit_interview_notes` text DEFAULT NULL,
  `final_settlement_processed` tinyint(1) DEFAULT 0,
  `gratuity_paid` tinyint(1) DEFAULT 0,
  `base_salary` decimal(12,2) NOT NULL,
  `salary_currency` varchar(3) DEFAULT 'USD',
  `salary_frequency` varchar(20) NOT NULL,
  `housing_allowance` decimal(10,2) DEFAULT 0.00,
  `transport_allowance` decimal(10,2) DEFAULT 0.00,
  `medical_allowance` decimal(10,2) DEFAULT 0.00,
  `meal_allowance` decimal(10,2) DEFAULT 0.00,
  `communication_allowance` decimal(10,2) DEFAULT 0.00,
  `education_allowance` decimal(10,2) DEFAULT 0.00,
  `travel_allowance` decimal(10,2) DEFAULT 0.00,
  `other_allowance` decimal(10,2) DEFAULT 0.00,
  `annual_bonus_percentage` decimal(5,2) DEFAULT 0.00,
  `provident_fund_percentage` decimal(5,2) DEFAULT 0.00,
  `gratuity_eligibility` tinyint(1) DEFAULT 0,
  `gratuity_eligibility_after_years` int(11) DEFAULT 5,
  `health_insurance` tinyint(1) DEFAULT 0,
  `life_insurance` tinyint(1) DEFAULT 0,
  `paid_time_off` tinyint(1) DEFAULT 1,
  `employment_agreement_url` text DEFAULT NULL,
  `id_proof_url` text DEFAULT NULL,
  `resume_url` text DEFAULT NULL,
  `certificates_url` text DEFAULT NULL,
  `medical_certificate_url` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `employee_notes` text DEFAULT NULL,
  `hr_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `leave_reset_period` varchar(20) DEFAULT 'yearly',
  `next_reset_date` date DEFAULT NULL,
  `carry_forward_allowed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_voucher`
--

CREATE TABLE `expense_voucher` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `voucher_no` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `description` text DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_items` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_voucher_line`
--

CREATE TABLE `expense_voucher_line` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `cost_center_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method_id` int(11) NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `cheque_no` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `followups`
--

CREATE TABLE `followups` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `followup_date` date NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fueling_stations`
--

CREATE TABLE `fueling_stations` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `station_code` varchar(50) NOT NULL,
  `station_name` varchar(100) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `fuel_type_id` int(11) NOT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `flow_rate` decimal(6,2) DEFAULT NULL,
  `status` enum('active','inactive','maintenance','out_of_service') DEFAULT 'active',
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `location_description` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `current_price` decimal(8,3) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `total_dispensed` decimal(12,2) DEFAULT 0.00,
  `total_transactions` int(11) DEFAULT 0,
  `uptime_percentage` decimal(5,2) DEFAULT 100.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hs_code_tax_rates`
--

CREATE TABLE `hs_code_tax_rates` (
  `id` int(11) NOT NULL,
  `hs_code` varchar(20) NOT NULL,
  `hs_code_description` varchar(300) NOT NULL,
  `schedule` enum('third_schedule','fifth_schedule','sixth_schedule','eighth_schedule','standard') NOT NULL DEFAULT 'standard',
  `gst_rate` decimal(8,4) NOT NULL DEFAULT 18.0000,
  `rate_basis` enum('ad_valorem','specific','combined') NOT NULL DEFAULT 'ad_valorem',
  `specific_rate_amount` decimal(10,4) DEFAULT NULL,
  `specific_rate_unit` varchar(20) DEFAULT NULL,
  `customs_duty_rate` decimal(8,4) DEFAULT NULL,
  `additional_customs_duty` decimal(8,4) DEFAULT NULL,
  `fed_rate` decimal(8,4) DEFAULT NULL,
  `fed_type` enum('ad_valorem','specific','none') DEFAULT 'none',
  `fed_specific_amount` decimal(10,4) DEFAULT NULL,
  `legal_reference` varchar(200) DEFAULT NULL,
  `sro_reference` varchar(100) DEFAULT NULL,
  `finance_act_year` year(4) NOT NULL DEFAULT 2024,
  `input_tax_claimable` tinyint(1) NOT NULL DEFAULT 1,
  `is_refundable` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `integration_credits`
--

CREATE TABLE `integration_credits` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `integration_type` enum('whatsapp','sms','email') NOT NULL,
  `credits` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `integration_message_log`
--

CREATE TABLE `integration_message_log` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `integration_type` enum('whatsapp','sms','email') NOT NULL,
  `module_name` varchar(50) DEFAULT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','sent','delivered','failed','read') DEFAULT 'pending',
  `credits_used` int(11) DEFAULT 1,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inward_gatepass`
--

CREATE TABLE `inward_gatepass` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `gatepass_code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inward_gatepass_items`
--

CREATE TABLE `inward_gatepass_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `gatepass_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ip_security`
--

CREATE TABLE `ip_security` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `ip_hash` varchar(64) NOT NULL,
  `is_blocked` tinyint(1) DEFAULT 0,
  `block_reason` enum('too_many_failures','suspicious_activity','manual_block') DEFAULT NULL,
  `blocked_at` timestamp NULL DEFAULT NULL,
  `blocked_until` timestamp NULL DEFAULT NULL,
  `blocked_by` int(11) DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `successful_logins` int(11) DEFAULT 0,
  `last_attempt_at` timestamp NULL DEFAULT NULL,
  `country_code` varchar(2) DEFAULT NULL,
  `country_name` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_voucher`
--

CREATE TABLE `journal_voucher` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `voucher_number` varchar(50) NOT NULL,
  `voucher_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `total_debit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('draft','posted') NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Table structure for table `journal_voucher_line`
--

CREATE TABLE `journal_voucher_line` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `debit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=DYNAMIC;

--
-- Triggers `journal_voucher_line`
--
DELIMITER $$
CREATE TRIGGER `sync_voucher_totals` AFTER INSERT ON `journal_voucher_line` FOR EACH ROW BEGIN
    -- Single update with subquery
    UPDATE journal_voucher jv
    SET jv.total_debit = (
        SELECT COALESCE(SUM(debit), 0)
        FROM journal_voucher_line jvl
        WHERE jvl.voucher_id = NEW.voucher_id
    ),
    jv.total_credit = (
        SELECT COALESCE(SUM(credit), 0)
        FROM journal_voucher_line jvl
        WHERE jvl.voucher_id = NEW.voucher_id
    )
    WHERE jv.id = NEW.voucher_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `lead_code` varchar(255) NOT NULL,
  `lead_source` varchar(50) NOT NULL,
  `lead_date` date NOT NULL,
  `lead_status` varchar(50) NOT NULL,
  `contact_name` varchar(255) NOT NULL,
  `business_name` varchar(255) DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `email` varchar(255) DEFAULT NULL,
  `primary_cell_no` varchar(20) DEFAULT NULL,
  `secondary_cell_no` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `interested_service_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_records`
--

CREATE TABLE `leave_records` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `leave_type` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `contact_during_leave` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `attempt_status` enum('success','failed','locked','2fa_required') NOT NULL,
  `failure_reason` enum('invalid_password','user_not_found','account_locked','2fa_failed','ip_blocked') DEFAULT NULL,
  `country_code` varchar(2) DEFAULT NULL,
  `is_suspicious` tinyint(1) DEFAULT 0,
  `suspicious_reason` varchar(255) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `machines`
--

CREATE TABLE `machines` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `machine_name` varchar(255) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `capacity_speed` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `opening_balance_invoices`
--

CREATE TABLE `opening_balance_invoices` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `distribution_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `debit` decimal(10,2) NOT NULL,
  `invoice_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `outward_gatepass`
--

CREATE TABLE `outward_gatepass` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `gatepass_code` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `outward_gatepass_items`
--

CREATE TABLE `outward_gatepass_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `gatepass_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_voucher`
--

CREATE TABLE `payment_voucher` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `currency_id` int(11) NOT NULL,
  `voucher_number` varchar(50) NOT NULL,
  `voucher_date` date NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `bill_no` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sub_account_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_sub_account_id` int(11) DEFAULT NULL,
  `cheque_no` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_entries`
--

CREATE TABLE `payroll_entries` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `payroll_code` varchar(50) NOT NULL COMMENT 'Unique payroll identifier e.g. PR202310001',
  `payroll_date` date NOT NULL COMMENT 'Date when payroll was created',
  `total_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Total amount of all items',
  `notes` text DEFAULT NULL COMMENT 'Additional notes or comments',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_entries_items`
--

CREATE TABLE `payroll_entries_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL COMMENT 'Reference to payroll_entries.id',
  `employee_id` varchar(20) NOT NULL COMMENT 'Employee identifier',
  `type` enum('Salary','Salary Adjustment','Advance','Advance Return','Commission','Bonus','Allowance','Daily Wages') NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL COMMENT 'Bank account details',
  `cheque_date` date DEFAULT NULL COMMENT 'Date on cheque if payment method is cheque',
  `description` text DEFAULT NULL COMMENT 'Description of payroll item',
  `amount` decimal(15,2) NOT NULL COMMENT 'Amount for this payroll item',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `position_code` varchar(10) NOT NULL,
  `position_title` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `job_description` text DEFAULT NULL,
  `min_salary` decimal(12,2) DEFAULT NULL,
  `max_salary` decimal(12,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `post_dated_cheques`
--

CREATE TABLE `post_dated_cheques` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `cheque_no` varchar(255) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `bank_account_id` int(11) NOT NULL,
  `transaction_type` enum('Received','Payment','Expense') DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL CHECK (`amount` > 0),
  `cheque_date` date NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reference_id` int(11) NOT NULL,
  `reference_table` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_completions`
--

CREATE TABLE `production_completions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `completion_no` varchar(50) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `machine_id` int(11) DEFAULT NULL,
  `complete_date` date NOT NULL,
  `total_products` int(11) NOT NULL,
  `total_quantity` decimal(15,2) NOT NULL,
  `total_cost` decimal(15,2) NOT NULL,
  `status` varchar(50) DEFAULT 'Completed',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_expenses`
--

CREATE TABLE `production_expenses` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `expense_number` varchar(50) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `expense_type` enum('Direct','Indirect') DEFAULT 'Direct',
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference_table` varchar(100) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `production_order_total_cost` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_expense_accounts`
--

CREATE TABLE `production_expense_accounts` (
  `id` int(11) NOT NULL,
  `production_expense_id` int(11) NOT NULL,
  `expense_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_orders`
--

CREATE TABLE `production_orders` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `order_no` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `bom_version` varchar(20) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `machine_id` int(11) DEFAULT NULL,
  `order_qty` decimal(10,2) NOT NULL,
  `status` varchar(20) DEFAULT 'Planned',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_order_materials`
--

CREATE TABLE `production_order_materials` (
  `id` int(11) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `required_qty` decimal(10,2) NOT NULL,
  `issued_qty` decimal(10,2) DEFAULT 0.00,
  `uom_id` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `product_type` enum('physical','service') NOT NULL DEFAULT 'physical',
  `default_unit_id` int(11) DEFAULT NULL,
  `uom_group_id` int(11) DEFAULT NULL,
  `uom_type` enum('unit','group') DEFAULT 'unit',
  `category_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `purchase_price` decimal(15,4) DEFAULT 0.0000,
  `mrp` decimal(15,4) DEFAULT 0.0000,
  `default_discount` decimal(5,2) DEFAULT 0.00,
  `min_stock_level` decimal(15,3) DEFAULT 0.000,
  `max_stock_level` decimal(15,3) DEFAULT 0.000,
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `inventory_account_id` int(11) NOT NULL DEFAULT 33,
  `vendor_id` int(11) DEFAULT NULL,
  `trade_price` decimal(10,2) DEFAULT 0.00,
  `trade_offer_discount` decimal(10,2) DEFAULT 0.00,
  `default_foc` int(11) DEFAULT NULL,
  `carton_conversion` int(11) DEFAULT NULL,
  `tax_regime_id` int(11) DEFAULT NULL,
  `hs_code` varchar(20) DEFAULT NULL,
  `wholesale_price` decimal(10,2) DEFAULT 0.00,
  `parent_product_id` int(11) DEFAULT NULL,
  `stock_affects` tinyint(1) DEFAULT 1,
  `invoice_affects` tinyint(1) DEFAULT 1,
  `ctn_trade_price` decimal(10,2) DEFAULT 0.00,
  `box_trade_price` decimal(10,2) DEFAULT 0.00,
  `pcs_trade_offer_amount` decimal(10,2) DEFAULT 0.00,
  `ctn_trade_offer_amount` decimal(10,2) DEFAULT 0.00,
  `box_trade_offer_amount` decimal(10,2) DEFAULT 0.00,
  `box_conversion` int(11) DEFAULT 0,
  `tax_regime` enum('standard','third_schedule','mrp_tax_exclusive','zero_rated','exempt','fixed_tax') NOT NULL DEFAULT 'standard',
  `sales_tax` varchar(50) DEFAULT NULL,
  `sales_tax_type` varchar(50) DEFAULT NULL,
  `further_tax` int(11) DEFAULT NULL,
  `product_conversion_factor` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_uom_conversions`
--

CREATE TABLE `product_uom_conversions` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `uom_id` int(11) NOT NULL,
  `conversion_factor` decimal(15,6) NOT NULL DEFAULT 1.000000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_invoice`
--

CREATE TABLE `purchase_invoice` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `currency_id` int(11) NOT NULL,
  `bill_no` varchar(50) NOT NULL,
  `purchase_date` date NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `total_bill` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_discount_percent` decimal(5,2) DEFAULT 0.00,
  `total_discount_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_tax` tinyint(1) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `rp_total` decimal(15,2) DEFAULT 0.00,
  `tp_total` decimal(15,2) DEFAULT 0.00,
  `discount_total` decimal(15,2) DEFAULT 0.00,
  `sales_tax_total` decimal(15,2) DEFAULT 0.00,
  `advance_tax_percent` decimal(5,2) DEFAULT 0.00,
  `advance_tax` decimal(15,2) DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `supplier_invoice_no` varchar(50) DEFAULT NULL,
  `supplier_invoice_date` date DEFAULT NULL,
  `bilty_no` varchar(50) DEFAULT NULL,
  `total_gst_percent` decimal(5,2) DEFAULT 0.00,
  `total_gst_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_fees` decimal(10,2) DEFAULT 0.00,
  `shipping_fees_type` enum('add','subtract') DEFAULT 'add',
  `transport_name` varchar(50) DEFAULT NULL,
  `purchase_order_id` int(11) DEFAULT NULL,
  `sub_account_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_invoice_items`
--

CREATE TABLE `purchase_invoice_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `purchase_invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `uom_id` int(11) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `rp_unit_price` decimal(15,2) DEFAULT 0.00,
  `tp_unit_price` decimal(15,2) DEFAULT 0.00,
  `rp_total_value` decimal(15,2) DEFAULT 0.00,
  `tp_total_value` decimal(15,2) DEFAULT 0.00,
  `purchase_price` decimal(15,2) DEFAULT 0.00,
  `gross_amount` decimal(15,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `sales_tax` decimal(15,2) DEFAULT 0.00,
  `tp_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `is_tax` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vehicle_no` int(11) DEFAULT NULL,
  `trade_offer_percent` decimal(5,2) DEFAULT 0.00,
  `trade_offer_amount` decimal(15,2) DEFAULT 0.00,
  `gst_percent` decimal(5,2) DEFAULT 0.00,
  `gst_amount` decimal(15,2) DEFAULT 0.00,
  `foc_quantity` int(11) DEFAULT NULL,
  `piece` int(11) DEFAULT NULL,
  `carton` int(11) DEFAULT NULL,
  `dozen` int(11) DEFAULT NULL,
  `parent_row_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order`
--

CREATE TABLE `purchase_order` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `currency_id` int(11) NOT NULL,
  `bill_no` varchar(50) NOT NULL,
  `purchase_date` date NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `total_bill` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_discount_percent` decimal(5,2) DEFAULT 0.00,
  `total_discount_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `supplier_invoice_no` int(11) DEFAULT NULL,
  `supplier_invoice_date` date DEFAULT NULL,
  `bilty_no` varchar(50) DEFAULT NULL,
  `total_gst_percent` decimal(5,2) DEFAULT 0.00,
  `total_gst_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_fees` decimal(10,2) DEFAULT 0.00,
  `transport_name` varchar(50) DEFAULT NULL,
  `sub_account_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `purchase_invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `uom_id` int(11) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `purchase_price` decimal(15,4) NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vehicle_no` int(11) DEFAULT NULL,
  `trade_offer_percent` decimal(5,2) DEFAULT 0.00,
  `trade_offer_amount` decimal(15,2) DEFAULT 0.00,
  `gst_percent` decimal(5,2) DEFAULT 0.00,
  `gst_amount` decimal(15,2) DEFAULT 0.00,
  `foc_quantity` int(11) DEFAULT NULL,
  `parent_row_id` int(11) DEFAULT NULL,
  `piece` int(11) DEFAULT NULL,
  `carton` int(11) DEFAULT NULL,
  `dozen` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_return`
--

CREATE TABLE `purchase_return` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `currency_id` int(11) NOT NULL,
  `bill_no` varchar(50) NOT NULL,
  `purchase_date` date NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `total_bill` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_discount_percent` decimal(5,2) DEFAULT 0.00,
  `total_discount_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `supplier_invoice_no` int(11) DEFAULT NULL,
  `supplier_invoice_date` date DEFAULT NULL,
  `bilty_no` varchar(50) DEFAULT NULL,
  `total_gst_percent` decimal(5,2) DEFAULT 0.00,
  `total_gst_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_fees` decimal(10,2) DEFAULT 0.00,
  `transport_name` varchar(50) DEFAULT NULL,
  `purchase_invoice_no` int(11) DEFAULT NULL,
  `sub_account_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_return_items`
--

CREATE TABLE `purchase_return_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `purchase_invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `uom_id` int(11) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `purchase_price` decimal(15,4) NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vehicle_no` int(11) DEFAULT NULL,
  `trade_offer_percent` decimal(5,2) DEFAULT 0.00,
  `trade_offer_amount` decimal(15,2) DEFAULT 0.00,
  `gst_percent` decimal(5,2) DEFAULT 0.00,
  `gst_amount` decimal(15,2) DEFAULT 0.00,
  `foc_quantity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rate_list`
--

CREATE TABLE `rate_list` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `list_code` varchar(255) NOT NULL,
  `list_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rate_list_items`
--

CREATE TABLE `rate_list_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `rate_list_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `foc_quantity` int(11) DEFAULT 0,
  `trade_offer` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receive_voucher`
--

CREATE TABLE `receive_voucher` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `currency_id` int(11) NOT NULL,
  `voucher_number` varchar(50) NOT NULL,
  `voucher_date` date NOT NULL,
  `customer_id` int(11) NOT NULL,
  `bill_no` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `recovery_officer_id` int(11) DEFAULT NULL,
  `sub_account_id` int(11) DEFAULT NULL,
  `dsr_no` varchar(255) DEFAULT NULL,
  `cheque_no` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `regions`
--

CREATE TABLE `regions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  `region_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rent_items`
--

CREATE TABLE `rent_items` (
  `id` int(11) NOT NULL,
  `rent_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `rate` decimal(10,2) DEFAULT 0.00,
  `condition_before` varchar(255) DEFAULT NULL,
  `condition_after` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rent_management`
--

CREATE TABLE `rent_management` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `rent_no` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `nic_no` varchar(50) DEFAULT NULL,
  `nic_picture` varchar(255) DEFAULT NULL,
  `reference_name` varchar(255) DEFAULT NULL,
  `reference_phone` varchar(50) DEFAULT NULL,
  `reference_nic` varchar(50) DEFAULT NULL,
  `reference_nic_picture` varchar(255) DEFAULT NULL,
  `rent_type` enum('Daily','Weekly','Monthly') NOT NULL,
  `rent_rate` decimal(15,2) NOT NULL,
  `total_days` int(11) NOT NULL,
  `total_rent_amount` decimal(15,2) NOT NULL,
  `issue_date` date NOT NULL,
  `expected_return_date` date NOT NULL,
  `actual_return_date` date DEFAULT NULL,
  `late_charges` decimal(15,2) DEFAULT 0.00,
  `damage_charges` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `payment_account_id` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('Issued','Returned') DEFAULT 'Issued',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `currency_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `revenue_split`
--

CREATE TABLE `revenue_split` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `station_daily_usage_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_system_role` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `form_name` varchar(100) NOT NULL,
  `sub_permission` varchar(50) DEFAULT NULL,
  `allowed` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_invoice`
--

CREATE TABLE `sale_invoice` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `currency_id` int(11) NOT NULL,
  `bill_no` varchar(50) NOT NULL,
  `sale_date` date NOT NULL,
  `customer_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `total_bill` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_discount_percent` decimal(5,2) DEFAULT 0.00,
  `total_discount_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Draft','Posted') NOT NULL DEFAULT 'Posted',
  `sale_officer_id` int(11) DEFAULT NULL,
  `bilty_no` int(11) DEFAULT NULL,
  `transport_name` varchar(255) DEFAULT NULL,
  `total_gst_percent` decimal(5,2) DEFAULT 0.00,
  `total_gst_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_fees` decimal(10,2) DEFAULT 0.00,
  `sale_order_id` int(11) DEFAULT NULL,
  `sub_account_id` int(11) DEFAULT NULL,
  `amount_returned` decimal(10,2) DEFAULT 0.00,
  `withholding_tax_percent` decimal(5,2) DEFAULT 0.00,
  `withholding_tax_amount` decimal(15,2) DEFAULT 0.00,
  `supplier_man_id` int(11) DEFAULT NULL,
  `amount_paid_auto_fill` enum('yes','no') DEFAULT 'yes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_invoice_items`
--

CREATE TABLE `sale_invoice_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `sale_invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `uom_id` int(11) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `sale_price` decimal(15,4) NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `trade_offer_percent` decimal(10,2) DEFAULT 0.00,
  `trade_offer_amount` decimal(10,2) DEFAULT 0.00,
  `gst_percent` decimal(10,2) DEFAULT 0.00,
  `gst_amount` decimal(10,2) DEFAULT 0.00,
  `foc_quantity` int(11) DEFAULT NULL,
  `parent_row_id` int(11) DEFAULT NULL,
  `carton` int(11) DEFAULT NULL,
  `dozen` int(11) DEFAULT NULL,
  `piece` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_order`
--

CREATE TABLE `sale_order` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `currency_id` int(11) NOT NULL,
  `bill_no` varchar(50) NOT NULL,
  `sale_date` date NOT NULL,
  `customer_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `total_bill` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_discount_percent` decimal(5,2) DEFAULT 0.00,
  `total_discount_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Draft','Posted') NOT NULL DEFAULT 'Posted',
  `sale_officer_id` int(11) DEFAULT NULL,
  `supplier_man_id` int(11) DEFAULT NULL,
  `bilty_no` int(11) DEFAULT NULL,
  `transport_name` varchar(255) DEFAULT NULL,
  `total_gst_percent` decimal(5,2) DEFAULT 0.00,
  `total_gst_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_fees` decimal(10,2) DEFAULT 0.00,
  `sub_account_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_order_items`
--

CREATE TABLE `sale_order_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `sale_invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `uom_id` int(11) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `sale_price` decimal(15,4) NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `trade_offer_percent` decimal(10,2) DEFAULT 0.00,
  `trade_offer_amount` decimal(10,2) DEFAULT 0.00,
  `gst_percent` decimal(10,2) DEFAULT 0.00,
  `gst_amount` decimal(10,2) DEFAULT 0.00,
  `foc_quantity` int(11) DEFAULT NULL,
  `parent_row_id` int(11) DEFAULT NULL,
  `piece` int(11) DEFAULT NULL,
  `carton` int(11) DEFAULT NULL,
  `dozen` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_return`
--

CREATE TABLE `sale_return` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `currency_id` int(11) NOT NULL,
  `bill_no` varchar(50) NOT NULL,
  `sale_date` date NOT NULL,
  `customer_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `total_bill` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_discount_percent` decimal(5,2) DEFAULT 0.00,
  `total_discount_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Draft','Posted') NOT NULL DEFAULT 'Posted',
  `sale_officer_id` int(11) DEFAULT NULL,
  `total_gst_percent` decimal(5,2) DEFAULT 0.00,
  `total_gst_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_fees` decimal(10,2) DEFAULT 0.00,
  `sale_invoice_no` int(11) DEFAULT NULL,
  `amount_refunded` decimal(15,2) DEFAULT 0.00,
  `payment_method` enum('Cash','Bank Transfer') DEFAULT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `sub_account_id` int(11) DEFAULT NULL,
  `supplier_man_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_return_items`
--

CREATE TABLE `sale_return_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `sale_invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `uom_id` int(11) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `sale_price` decimal(15,4) NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `trade_offer_percent` decimal(10,2) DEFAULT 0.00,
  `trade_offer_amount` decimal(10,2) DEFAULT 0.00,
  `gst_percent` decimal(10,2) DEFAULT 0.00,
  `gst_amount` decimal(10,2) DEFAULT 0.00,
  `foc_quantity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_integration`
--

CREATE TABLE `sms_integration` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `provider` varchar(50) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `sender_id` varchar(20) DEFAULT NULL,
  `enabled_modules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`enabled_modules`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `station_daily_usage`
--

CREATE TABLE `station_daily_usage` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `station_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `rate` decimal(10,3) DEFAULT NULL,
  `usage_date` date NOT NULL,
  `opening_reading` decimal(12,2) NOT NULL,
  `closing_reading` decimal(12,2) DEFAULT NULL,
  `total_dispensed` decimal(10,2) GENERATED ALWAYS AS (`closing_reading` - `opening_reading`) STORED,
  `total_transactions` int(11) DEFAULT 0,
  `total_revenue` decimal(12,2) DEFAULT 0.00,
  `recorded_by` int(11) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `station_maintenance_history`
--

CREATE TABLE `station_maintenance_history` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `station_id` int(11) NOT NULL,
  `maintenance_type` enum('routine','repair','calibration','inspection') NOT NULL,
  `maintenance_date` date NOT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `parts_replaced` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `downtime_hours` decimal(5,2) DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustment`
--

CREATE TABLE `stock_adjustment` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `adjustment_code` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `branch_id` int(11) NOT NULL,
  `adjustment_type` enum('Increase','Decrease') NOT NULL,
  `main_reason` varchar(255) NOT NULL,
  `primary_reason` varchar(255) NOT NULL,
  `secondary_reason` varchar(255) NOT NULL,
  `remarks` text DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_items` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustment_items`
--

CREATE TABLE `stock_adjustment_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `adjustment_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_value` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_ledger`
--

CREATE TABLE `stock_ledger` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL DEFAULT 33,
  `branch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `reference_table` varchar(100) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `stock_status` enum('sellable','damaged') NOT NULL DEFAULT 'sellable',
  `qty_in` decimal(15,3) DEFAULT 0.000,
  `qty_out` decimal(15,3) DEFAULT 0.000,
  `unit_cost` decimal(15,4) DEFAULT 0.0000,
  `unit_id` int(11) NOT NULL,
  `transaction_type` varchar(50) NOT NULL,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_opening`
--

CREATE TABLE `stock_opening` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `opening_qty` decimal(15,3) DEFAULT 0.000,
  `opening_price` decimal(15,4) DEFAULT 0.0000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfer`
--

CREATE TABLE `stock_transfer` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `transfer_code` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `from_location_id` int(11) NOT NULL,
  `to_location_id` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_items` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfer_items`
--

CREATE TABLE `stock_transfer_items` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_value` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `storage_locations`
--

CREATE TABLE `storage_locations` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `location_code` varchar(50) NOT NULL,
  `location_name` varchar(255) NOT NULL,
  `location_type` enum('shed','zone','rack','shelf','bin','pallet','room') DEFAULT 'shed',
  `parent_location_id` int(11) DEFAULT NULL,
  `capacity` decimal(15,4) DEFAULT NULL,
  `temperature_zone` enum('ambient','cool','cold','frozen','hazardous','controlled') DEFAULT 'ambient',
  `is_active` tinyint(1) DEFAULT 1,
  `length` decimal(8,2) DEFAULT NULL,
  `width` decimal(8,2) DEFAULT NULL,
  `height` decimal(8,2) DEFAULT NULL,
  `volume_capacity` decimal(10,2) DEFAULT NULL,
  `requires_special_access` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subcategories`
--

CREATE TABLE `subcategories` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `subcategory_name` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sub_accounts`
--

CREATE TABLE `sub_accounts` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `account_head_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `salesman_id` int(11) DEFAULT NULL,
  `supplier_code` varchar(20) NOT NULL,
  `supplier_type` varchar(50) DEFAULT 'Manufacturer',
  `supplier_name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `primary_phone` varchar(15) DEFAULT NULL,
  `secondary_phone` varchar(15) DEFAULT NULL,
  `identity_card_no` varchar(15) DEFAULT NULL,
  `email` varchar(300) DEFAULT NULL,
  `opening_debit_amount` decimal(15,2) DEFAULT 0.00,
  `opening_credit_amount` decimal(15,2) DEFAULT 0.00,
  `ait_percent` decimal(5,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `is_blacklisted` tinyint(1) DEFAULT 0,
  `status` varchar(20) DEFAULT 'ACTIVE' CHECK (`status` in ('ACTIVE','INACTIVE','SUSPENDED','BLACKLISTED')),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `suppliers`
--
DELIMITER $$
CREATE TRIGGER `trg_suppliers_balance_insert` BEFORE INSERT ON `suppliers` FOR EACH ROW BEGIN
    -- Calculate current balance based on debit and credit amounts
    SET NEW.current_balance = COALESCE(NEW.opening_debit_amount, 0) - COALESCE(NEW.opening_credit_amount, 0);
    
    -- Auto-set status based on blacklist flag
    IF NEW.is_blacklisted = TRUE THEN
        SET NEW.status = 'BLACKLISTED';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_suppliers_balance_update` BEFORE UPDATE ON `suppliers` FOR EACH ROW BEGIN
    -- Calculate current balance based on debit and credit amounts
    SET NEW.current_balance = COALESCE(NEW.opening_debit_amount, 0) - COALESCE(NEW.opening_credit_amount, 0);
    
    -- Auto-update status based on blacklist flag
    IF NEW.is_blacklisted = TRUE THEN
        SET NEW.status = 'BLACKLISTED';
    ELSEIF NEW.is_blacklisted = FALSE AND OLD.status = 'BLACKLISTED' THEN
        SET NEW.status = 'ACTIVE';
    END IF;
    
    -- Update timestamp
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_sub_accounts`
--

CREATE TABLE `supplier_sub_accounts` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `sub_account_name` varchar(255) NOT NULL,
  `debit` decimal(10,2) DEFAULT 0.00,
  `credit` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suspension_records`
--

CREATE TABLE `suspension_records` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `suspension_type` varchar(30) NOT NULL,
  `reason` text NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tax_rates`
--

CREATE TABLE `tax_rates` (
  `id` int(11) NOT NULL,
  `tax_authority` enum('FBR','SRB','PRA','KPRA','BRA','OTHER') NOT NULL DEFAULT 'FBR',
  `tax_type` enum('sales_tax','further_tax','advance_tax','wht','income_tax','minimum_tax','fed','value_addition_tax','customs_duty','other') NOT NULL,
  `transaction_type` enum('sale','purchase','import','export','payment','all') NOT NULL DEFAULT 'all',
  `legal_section` varchar(80) NOT NULL,
  `finance_act_year` year(4) NOT NULL DEFAULT 2024,
  `tax_name` varchar(150) NOT NULL,
  `applicable_to` enum('manufacturer','distributor','wholesaler','retailer','importer_commercial','importer_industrial','exporter','all') NOT NULL DEFAULT 'all',
  `party_type` enum('registered_company','registered_individual','unregistered','government','large_aop','small_company','ngo','non_resident','exempt','all') NOT NULL DEFAULT 'all',
  `is_filer` tinyint(1) DEFAULT NULL,
  `rate_percentage` decimal(8,4) NOT NULL,
  `threshold_min` decimal(15,2) DEFAULT NULL,
  `threshold_max` decimal(15,2) DEFAULT NULL,
  `tax_regime_id` int(11) NOT NULL,
  `is_adjustable` tinyint(1) NOT NULL DEFAULT 1,
  `is_refundable` tinyint(1) NOT NULL DEFAULT 0,
  `is_final_tax` tinyint(1) NOT NULL DEFAULT 0,
  `deducted_by` enum('seller','buyer','customs','both','not_applicable') NOT NULL DEFAULT 'seller',
  `debit_account_id` int(11) DEFAULT NULL,
  `credit_account_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'PKR',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tax_regimes`
--

CREATE TABLE `tax_regimes` (
  `id` int(11) NOT NULL,
  `regime_code` varchar(50) NOT NULL,
  `regime_name` varchar(150) NOT NULL,
  `legal_reference` varchar(150) DEFAULT NULL,
  `finance_act_year` year(4) NOT NULL DEFAULT 2024,
  `tax_authority` enum('FBR','SRB','PRA','KPRA','BRA','OTHER') NOT NULL DEFAULT 'FBR',
  `tax_base` enum('trade_price','mrp','import_value','turnover','fixed_amount','gross_receipt') NOT NULL DEFAULT 'trade_price',
  `formula_template` varchar(300) NOT NULL,
  `formula_steps` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`formula_steps`)),
  `formula_variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`formula_variables`)),
  `is_tax_inclusive` tinyint(1) NOT NULL DEFAULT 0,
  `is_single_stage` tinyint(1) NOT NULL DEFAULT 0,
  `applies_at_stage` enum('manufacturer','importer','distributor','retailer','all') NOT NULL DEFAULT 'all',
  `downstream_exempt` tinyint(1) NOT NULL DEFAULT 0,
  `is_adjustable` tinyint(1) NOT NULL DEFAULT 1,
  `is_refundable` tinyint(1) NOT NULL DEFAULT 0,
  `is_final_tax` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tenant_currencies`
--

CREATE TABLE `tenant_currencies` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `currency_id` int(11) NOT NULL,
  `is_base_currency` tinyint(4) DEFAULT 0,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `termination_records`
--

CREATE TABLE `termination_records` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `termination_date` date NOT NULL,
  `termination_type` varchar(30) NOT NULL,
  `notice_period` varchar(20) NOT NULL,
  `reason` text NOT NULL,
  `exit_interview_notes` text DEFAULT NULL,
  `final_pay_processed` tinyint(1) DEFAULT 0,
  `gratuity_processed` tinyint(1) DEFAULT 0,
  `assets_returned` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `unified_logs`
--

CREATE TABLE `unified_logs` (
  `id` bigint(20) NOT NULL,
  `log_type` enum('activity','audit','security','system') NOT NULL,
  `severity` enum('debug','info','warning','error','critical') DEFAULT 'info',
  `event_category` enum('authentication','data_change','user_action','system','permission','billing') NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `ip_hash` varchar(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `user_agent_hash` varchar(64) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '{}' CHECK (json_valid(`event_data`)),
  `description` text DEFAULT NULL,
  `is_security_event` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uom`
--

CREATE TABLE `uom` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `uom_name` varchar(50) NOT NULL,
  `uom_type` varchar(50) NOT NULL,
  `base_unit_id` int(11) DEFAULT NULL,
  `conversion_factor` decimal(18,6) NOT NULL DEFAULT 1.000000,
  `is_base_unit` tinyint(1) NOT NULL DEFAULT 0,
  `unit_scope` enum('per_product','universal') NOT NULL DEFAULT 'universal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uom_groups`
--

CREATE TABLE `uom_groups` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uom_group_units`
--

CREATE TABLE `uom_group_units` (
  `id` int(11) NOT NULL,
  `uom_group_id` int(11) NOT NULL,
  `uom_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` text NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_picture` text DEFAULT NULL,
  `language_code` varchar(10) DEFAULT 'en',
  `timezone` varchar(50) DEFAULT 'UTC',
  `is_active` tinyint(1) DEFAULT 1,
  `is_email_verified` tinyint(1) DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `employee_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_permission_overrides`
--

CREATE TABLE `user_permission_overrides` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  `override_type` enum('grant','deny') NOT NULL,
  `reason` text DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `session_token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_integration`
--

CREATE TABLE `whatsapp_integration` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `is_connected` tinyint(1) DEFAULT 0,
  `qr_code` text DEFAULT NULL,
  `qr_expires_at` datetime DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `enabled_modules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`enabled_modules`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_in_progress`
--

CREATE TABLE `work_in_progress` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `machine_id` int(11) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `total_items` int(11) DEFAULT 0,
  `total_cost` decimal(15,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `wip_number` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_in_progress_items`
--

CREATE TABLE `work_in_progress_items` (
  `id` int(11) NOT NULL,
  `work_in_progress_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `required_qty` decimal(15,2) DEFAULT 0.00,
  `issued_qty` decimal(15,2) DEFAULT 0.00,
  `available_qty` decimal(15,2) DEFAULT 0.00,
  `issue_qty` decimal(15,2) NOT NULL,
  `uom_id` int(11) DEFAULT NULL,
  `unit_cost` decimal(15,2) DEFAULT 0.00,
  `total_cost` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounting_ledger`
--
ALTER TABLE `accounting_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `idx_accounting_ledger_reference` (`reference_table`,`reference_id`),
  ADD KEY `idx_accounting_ledger_account_date` (`account_id`,`date`),
  ADD KEY `idx_tenant_id` (`tenant_id`);

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sub_account` (`sub_account_id`),
  ADD KEY `fk_tenant_id` (`tenant_id`);

--
-- Indexes for table `accounts_head`
--
ALTER TABLE `accounts_head`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_accounts_head_tenant` (`tenant_id`);

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_areas_tenant` (`tenant_id`),
  ADD KEY `idx_areas_city_zone` (`city_zone_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_attendance_date` (`attendance_date`),
  ADD KEY `idx_employee_date` (`employee_id`,`attendance_date`);

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bank_name` (`bank_name`,`account_number`,`tenant_id`) USING BTREE,
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `bill_of_materials`
--
ALTER TABLE `bill_of_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `finished_good_id` (`finished_good_id`);

--
-- Indexes for table `bom_materials`
--
ALTER TABLE `bom_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bom_id` (`bom_id`),
  ADD KEY `raw_material_id` (`raw_material_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_branch_code` (`tenant_id`,`company_id`,`branch_code`),
  ADD UNIQUE KEY `unique_branch_name` (`tenant_id`,`company_id`,`branch_name`),
  ADD KEY `idx_company` (`tenant_id`,`company_id`),
  ADD KEY `idx_branch_type` (`branch_type`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `parent_branch_id` (`parent_branch_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `cash_opening`
--
ALTER TABLE `cash_opening`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_opening_per_date` (`as_of_date`,`tenant_id`,`branch_id`),
  ADD KEY `idx_as_of_date` (`as_of_date`),
  ADD KEY `idx_is_locked` (`is_locked`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tenant` (`tenant_id`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cities_tenant` (`tenant_id`),
  ADD KEY `idx_cities_region` (`region_id`);

--
-- Indexes for table `city_zones`
--
ALTER TABLE `city_zones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_city_zones_tenant` (`tenant_id`),
  ADD KEY `idx_city_zones_city` (`city_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_company_tenant` (`tenant_id`,`company_name`),
  ADD UNIQUE KEY `uq_company_code` (`company_code`,`tenant_id`) USING BTREE,
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_company_code` (`company_code`),
  ADD KEY `idx_industry_type` (`industry_type`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `completed_products`
--
ALTER TABLE `completed_products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cost_centers`
--
ALTER TABLE `cost_centers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_name` (`account_id`,`name`,`tenant_id`) USING BTREE,
  ADD KEY `is_active` (`is_active`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_countries_tenant` (`tenant_id`);

--
-- Indexes for table `credit_purchase_history`
--
ALTER TABLE `credit_purchase_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_customers_tenant_code` (`tenant_id`,`customer_code`),
  ADD KEY `fk_customers_updated_by` (`updated_by`),
  ADD KEY `idx_customers_tenant_id` (`tenant_id`),
  ADD KEY `idx_customers_customer_code` (`customer_code`),
  ADD KEY `idx_customers_customer_name` (`customer_name`),
  ADD KEY `idx_customers_primary_phone` (`primary_phone`),
  ADD KEY `idx_customers_email` (`email`),
  ADD KEY `idx_customers_status` (`status`),
  ADD KEY `idx_customers_is_blacklisted` (`is_blacklisted`),
  ADD KEY `idx_customers_current_balance` (`current_balance`),
  ADD KEY `idx_customers_created_at` (`created_at`),
  ADD KEY `idx_customers_updated_at` (`updated_at`),
  ADD KEY `idx_customers_created_by` (`created_by`),
  ADD KEY `idx_customers_tenant_status` (`tenant_id`,`status`),
  ADD KEY `idx_customers_tenant_blacklisted` (`tenant_id`,`is_blacklisted`),
  ADD KEY `idx_customers_tenant_balance` (`tenant_id`,`current_balance`),
  ADD KEY `idx_customers_tenant_phone` (`tenant_id`,`primary_phone`),
  ADD KEY `idx_customers_tenant_email` (`tenant_id`,`email`),
  ADD KEY `idx_company_id` (`company_id`);
ALTER TABLE `customers` ADD FULLTEXT KEY `idx_customers_name_address_ft` (`customer_name`,`address`);

--
-- Indexes for table `customer_entry`
--
ALTER TABLE `customer_entry`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_sub_accounts`
--
ALTER TABLE `customer_sub_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_customer_sub_account` (`tenant_id`,`customer_id`,`sub_account_name`),
  ADD KEY `idx_customer_sub_accounts_tenant` (`tenant_id`),
  ADD KEY `idx_customer_sub_accounts_customer` (`customer_id`);

--
-- Indexes for table `customer_types`
--
ALTER TABLE `customer_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_type_per_tenant` (`tenant_id`,`type_name`),
  ADD KEY `idx_tenant_id` (`tenant_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_code` (`department_code`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `department_head_id` (`department_head_id`),
  ADD KEY `parent_department_id` (`parent_department_id`),
  ADD KEY `idx_departments_department_code` (`department_code`),
  ADD KEY `idx_departments_is_active` (`is_active`);

--
-- Indexes for table `email_integration`
--
ALTER TABLE `email_integration`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant` (`tenant_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`,`tenant_id`) USING BTREE,
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `position_id` (`position_id`),
  ADD KEY `reporting_manager_id` (`reporting_manager_id`),
  ADD KEY `idx_employees_employee_id` (`employee_id`),
  ADD KEY `idx_employees_email` (`email`),
  ADD KEY `idx_employees_department_id` (`department_id`),
  ADD KEY `idx_employees_current_status` (`current_status`),
  ADD KEY `idx_employees_employment_type` (`employment_type`),
  ADD KEY `idx_employees_hire_date` (`hire_date`),
  ADD KEY `idx_employees_full_name` (`full_name`),
  ADD KEY `idx_employees_is_active` (`is_active`),
  ADD KEY `fk_employees_created_by` (`created_by`),
  ADD KEY `fk_employees_updated_by` (`updated_by`),
  ADD KEY `fk_employees_deleted_by` (`deleted_by`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `expense_voucher`
--
ALTER TABLE `expense_voucher`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id` (`tenant_id`,`voucher_no`),
  ADD KEY `idx_ev_tenant` (`tenant_id`),
  ADD KEY `idx_ev_voucher_no` (`voucher_no`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `expense_voucher_line`
--
ALTER TABLE `expense_voucher_line`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_evl_tenant` (`tenant_id`),
  ADD KEY `idx_evl_voucher` (`voucher_id`),
  ADD KEY `idx_evl_account` (`account_id`),
  ADD KEY `idx_evl_cost_center` (`cost_center_id`),
  ADD KEY `idx_evl_bank_account` (`bank_account_id`),
  ADD KEY `fk_evl_method` (`payment_method_id`);

--
-- Indexes for table `followups`
--
ALTER TABLE `followups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_lead` (`lead_id`),
  ADD KEY `idx_followup_date` (`followup_date`);

--
-- Indexes for table `fueling_stations`
--
ALTER TABLE `fueling_stations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_branch_pump` (`branch_id`,`station_name`),
  ADD UNIQUE KEY `station_code` (`station_code`,`tenant_id`) USING BTREE,
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_station_branch` (`branch_id`),
  ADD KEY `idx_station_fuel_type` (`fuel_type_id`),
  ADD KEY `idx_station_status` (`status`),
  ADD KEY `idx_station_installation_date` (`installation_date`),
  ADD KEY `idx_station_maintenance_date` (`next_maintenance_date`);

--
-- Indexes for table `hs_code_tax_rates`
--
ALTER TABLE `hs_code_tax_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hs_code_year` (`hs_code`,`finance_act_year`),
  ADD KEY `idx_hs_schedule` (`schedule`,`is_active`),
  ADD KEY `idx_hs_code` (`hs_code`),
  ADD KEY `idx_finance_act` (`finance_act_year`);

--
-- Indexes for table `integration_credits`
--
ALTER TABLE `integration_credits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant_integration` (`tenant_id`,`integration_type`);

--
-- Indexes for table `integration_message_log`
--
ALTER TABLE `integration_message_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_integration` (`tenant_id`,`integration_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_company_messages` (`tenant_id`,`company_id`,`integration_type`);

--
-- Indexes for table `inward_gatepass`
--
ALTER TABLE `inward_gatepass`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_gatepass_code` (`tenant_id`,`gatepass_code`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `inward_gatepass_items`
--
ALTER TABLE `inward_gatepass_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_gatepass_id` (`gatepass_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `ip_security`
--
ALTER TABLE `ip_security`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant_ip` (`tenant_id`,`ip_address`),
  ADD KEY `idx_is_blocked` (`is_blocked`),
  ADD KEY `idx_blocked_until` (`blocked_until`),
  ADD KEY `idx_ip_hash` (`ip_hash`),
  ADD KEY `blocked_by` (`blocked_by`);

--
-- Indexes for table `journal_voucher`
--
ALTER TABLE `journal_voucher`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_number` (`voucher_number`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `journal_voucher_line`
--
ALTER TABLE `journal_voucher_line`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_voucher_account` (`voucher_id`,`account_id`),
  ADD KEY `idx_line_debit` (`debit`),
  ADD KEY `idx_line_credit` (`credit`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_lead_code_tenant` (`tenant_id`,`lead_code`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_lead_status` (`lead_status`),
  ADD KEY `idx_lead_date` (`lead_date`);

--
-- Indexes for table `leave_records`
--
ALTER TABLE `leave_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_leave` (`employee_id`),
  ADD KEY `idx_leave_dates` (`start_date`,`end_date`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_username` (`tenant_id`,`username`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_attempted_at` (`attempted_at`),
  ADD KEY `idx_status` (`attempt_status`),
  ADD KEY `idx_suspicious` (`is_suspicious`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `machines`
--
ALTER TABLE `machines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `opening_balance_invoices`
--
ALTER TABLE `opening_balance_invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_distribution_id` (`distribution_id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_invoice_date` (`invoice_date`);

--
-- Indexes for table `outward_gatepass`
--
ALTER TABLE `outward_gatepass`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_gatepass_code` (`tenant_id`,`gatepass_code`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `outward_gatepass_items`
--
ALTER TABLE `outward_gatepass_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `fk_outward_gatepass` (`gatepass_id`);

--
-- Indexes for table `payment_voucher`
--
ALTER TABLE `payment_voucher`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_voucher_number` (`voucher_number`),
  ADD KEY `idx_voucher_date` (`voucher_date`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_payment_method_id` (`payment_method_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `fk_payment_voucher_updated_by` (`updated_by`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `payroll_entries`
--
ALTER TABLE `payroll_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payroll_code` (`payroll_code`),
  ADD KEY `idx_payroll_code` (`payroll_code`),
  ADD KEY `idx_payroll_date` (`payroll_date`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `payroll_entries_items`
--
ALTER TABLE `payroll_entries_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `idx_payroll_id` (`payroll_id`),
  ADD KEY `idx_employee_code` (`employee_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_payment_method` (`payment_method_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `position_code` (`position_code`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `idx_positions_position_code` (`position_code`),
  ADD KEY `idx_positions_department_id` (`department_id`),
  ADD KEY `idx_positions_is_active` (`is_active`);

--
-- Indexes for table `post_dated_cheques`
--
ALTER TABLE `post_dated_cheques`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cheque_no` (`tenant_id`,`cheque_no`),
  ADD KEY `fk_pdc_account` (`account_id`),
  ADD KEY `fk_pdc_bank_account` (`bank_account_id`),
  ADD KEY `fk_pdc_customer` (`customer_id`),
  ADD KEY `fk_pdc_supplier` (`supplier_id`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `production_completions`
--
ALTER TABLE `production_completions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `production_expenses`
--
ALTER TABLE `production_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `production_order_id` (`production_order_id`),
  ADD KEY `expense_number` (`expense_number`);

--
-- Indexes for table `production_expense_accounts`
--
ALTER TABLE `production_expense_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_expense_id` (`production_expense_id`),
  ADD KEY `expense_account_id` (`expense_account_id`);

--
-- Indexes for table `production_orders`
--
ALTER TABLE `production_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `bom_id` (`bom_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `production_order_materials`
--
ALTER TABLE `production_order_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_order_id` (`production_order_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_product_type` (`product_type`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `fk_products_default_unit_id` (`default_unit_id`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_tax_regime` (`tax_regime_id`),
  ADD KEY `uom_group_id` (`uom_group_id`);

--
-- Indexes for table `product_uom_conversions`
--
ALTER TABLE `product_uom_conversions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_uom` (`product_id`,`uom_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_uom_id` (`uom_id`);

--
-- Indexes for table `purchase_invoice`
--
ALTER TABLE `purchase_invoice`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenant_bill_no` (`tenant_id`,`bill_no`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_purchase_date` (`purchase_date`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_tenant_date_status` (`tenant_id`,`purchase_date`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `purchase_invoice_items`
--
ALTER TABLE `purchase_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_purchase_invoice_id` (`purchase_invoice_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_uom_id` (`uom_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_tenant_invoice` (`tenant_id`,`purchase_invoice_id`),
  ADD KEY `idx_tenant_product` (`tenant_id`,`product_id`);

--
-- Indexes for table `purchase_order`
--
ALTER TABLE `purchase_order`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenant_bill_no` (`tenant_id`,`bill_no`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_purchase_date` (`purchase_date`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_tenant_date_status` (`tenant_id`,`purchase_date`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_purchase_invoice_id` (`purchase_invoice_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_uom_id` (`uom_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_tenant_invoice` (`tenant_id`,`purchase_invoice_id`),
  ADD KEY `idx_tenant_product` (`tenant_id`,`product_id`);

--
-- Indexes for table `purchase_return`
--
ALTER TABLE `purchase_return`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenant_bill_no` (`tenant_id`,`bill_no`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_purchase_date` (`purchase_date`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_tenant_date_status` (`tenant_id`,`purchase_date`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_purchase_invoice_id` (`purchase_invoice_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_uom_id` (`uom_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_tenant_invoice` (`tenant_id`,`purchase_invoice_id`),
  ADD KEY `idx_tenant_product` (`tenant_id`,`product_id`);

--
-- Indexes for table `rate_list`
--
ALTER TABLE `rate_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rate_list_tenant_code` (`tenant_id`,`list_code`);

--
-- Indexes for table `rate_list_items`
--
ALTER TABLE `rate_list_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rate_list` (`rate_list_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_customer` (`customer_id`);

--
-- Indexes for table `receive_voucher`
--
ALTER TABLE `receive_voucher`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_voucher_number` (`voucher_number`),
  ADD KEY `idx_voucher_date` (`voucher_date`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_payment_method_id` (`payment_method_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `fk_receive_voucher_updated_by` (`updated_by`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `regions`
--
ALTER TABLE `regions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_regions_tenant` (`tenant_id`),
  ADD KEY `idx_regions_country` (`country_id`);

--
-- Indexes for table `rent_items`
--
ALTER TABLE `rent_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rent_id` (`rent_id`);

--
-- Indexes for table `rent_management`
--
ALTER TABLE `rent_management`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_rent_branch` (`branch_id`);

--
-- Indexes for table `revenue_split`
--
ALTER TABLE `revenue_split`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_revenue_split_tenant` (`tenant_id`),
  ADD KEY `idx_revenue_split_station_usage` (`station_daily_usage_id`),
  ADD KEY `idx_revenue_split_account` (`account_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant_role` (`tenant_id`,`name`),
  ADD KEY `idx_tenant_active` (`tenant_id`,`is_active`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_permission` (`tenant_id`,`role_id`,`category`,`form_name`,`sub_permission`),
  ADD KEY `idx_role_form` (`role_id`,`category`,`form_name`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_allowed` (`allowed`);

--
-- Indexes for table `sale_invoice`
--
ALTER TABLE `sale_invoice`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenant_bill_no` (`tenant_id`,`bill_no`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_sale_date` (`sale_date`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_tenant_date_status` (`tenant_id`,`sale_date`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `sale_invoice_items`
--
ALTER TABLE `sale_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_sale_invoice_id` (`sale_invoice_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_uom_id` (`uom_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_tenant_invoice` (`tenant_id`,`sale_invoice_id`),
  ADD KEY `idx_tenant_product` (`tenant_id`,`product_id`);

--
-- Indexes for table `sale_order`
--
ALTER TABLE `sale_order`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenant_bill_no` (`tenant_id`,`bill_no`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_sale_date` (`sale_date`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_tenant_date_status` (`tenant_id`,`sale_date`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `fk_sale_order_supplier_man` (`supplier_man_id`);

--
-- Indexes for table `sale_order_items`
--
ALTER TABLE `sale_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_sale_invoice_id` (`sale_invoice_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_uom_id` (`uom_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_tenant_invoice` (`tenant_id`,`sale_invoice_id`),
  ADD KEY `idx_tenant_product` (`tenant_id`,`product_id`);

--
-- Indexes for table `sale_return`
--
ALTER TABLE `sale_return`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenant_bill_no` (`tenant_id`,`bill_no`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_sale_date` (`sale_date`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_tenant_date_status` (`tenant_id`,`sale_date`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_sale_invoice_id` (`sale_invoice_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_uom_id` (`uom_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_tenant_invoice` (`tenant_id`,`sale_invoice_id`),
  ADD KEY `idx_tenant_product` (`tenant_id`,`product_id`);

--
-- Indexes for table `sms_integration`
--
ALTER TABLE `sms_integration`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant` (`tenant_id`);

--
-- Indexes for table `station_daily_usage`
--
ALTER TABLE `station_daily_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_station_date` (`station_id`,`usage_date`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `idx_usage_date` (`usage_date`),
  ADD KEY `idx_usage_station` (`station_id`),
  ADD KEY `fk_station_daily_usage_branch` (`branch_id`),
  ADD KEY `fk_station_daily_usage_product` (`product_id`),
  ADD KEY `fk_station_daily_usage_unit` (`unit_id`);

--
-- Indexes for table `station_maintenance_history`
--
ALTER TABLE `station_maintenance_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `idx_maintenance_station` (`station_id`),
  ADD KEY `idx_maintenance_date` (`maintenance_date`),
  ADD KEY `idx_maintenance_type` (`maintenance_type`);

--
-- Indexes for table `stock_adjustment`
--
ALTER TABLE `stock_adjustment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sa_tenant` (`tenant_id`),
  ADD KEY `idx_sa_branch` (`branch_id`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `stock_adjustment_items`
--
ALTER TABLE `stock_adjustment_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sai_adjustment` (`adjustment_id`),
  ADD KEY `idx_sai_product` (`product_id`),
  ADD KEY `idx_sai_tenant` (`tenant_id`);

--
-- Indexes for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stock_ledger_tenant` (`tenant_id`),
  ADD KEY `fk_stock_ledger_branch` (`branch_id`),
  ADD KEY `fk_stock_ledger_product` (`product_id`),
  ADD KEY `idx_stock_ledger_tenant_branch` (`tenant_id`,`branch_id`),
  ADD KEY `idx_stock_ledger_product_transaction` (`product_id`,`transaction_type`),
  ADD KEY `idx_stock_ledger_dates` (`transaction_date`,`created_at`),
  ADD KEY `idx_stock_ledger_reference` (`reference_table`,`reference_id`),
  ADD KEY `idx_stock_ledger_transaction_type` (`transaction_type`),
  ADD KEY `idx_stock_ledger_tenant_product` (`tenant_id`,`product_id`),
  ADD KEY `idx_stock_ledger_branch_product` (`branch_id`,`product_id`),
  ADD KEY `idx_stock_ledger_balance_query` (`tenant_id`,`branch_id`,`product_id`,`transaction_date`,`created_at`);

--
-- Indexes for table `stock_opening`
--
ALTER TABLE `stock_opening`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stock_opening_product` (`product_id`),
  ADD KEY `fk_stock_opening_branch` (`branch_id`),
  ADD KEY `fk_stock_opening_tenant` (`tenant_id`),
  ADD KEY `idx_stock_opening_product_branch` (`product_id`,`branch_id`),
  ADD KEY `idx_stock_opening_tenant_branch` (`tenant_id`,`branch_id`),
  ADD KEY `idx_stock_opening_created_at` (`created_at`),
  ADD KEY `idx_stock_opening_tenant_product_date` (`tenant_id`,`product_id`,`created_at`);

--
-- Indexes for table `stock_transfer`
--
ALTER TABLE `stock_transfer`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_st_tenant` (`tenant_id`),
  ADD KEY `idx_st_transfer_code` (`transfer_code`),
  ADD KEY `idx_st_date` (`date`),
  ADD KEY `idx_st_from_location` (`from_location_id`),
  ADD KEY `idx_st_to_location` (`to_location_id`),
  ADD KEY `idx_company_id` (`company_id`);

--
-- Indexes for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sti_tenant` (`tenant_id`),
  ADD KEY `idx_sti_transfer` (`transfer_id`),
  ADD KEY `idx_sti_product` (`product_id`);

--
-- Indexes for table `storage_locations`
--
ALTER TABLE `storage_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_location_code` (`tenant_id`,`company_id`,`branch_id`,`location_code`),
  ADD KEY `idx_branch` (`tenant_id`,`company_id`,`branch_id`),
  ADD KEY `idx_location_type` (`location_type`),
  ADD KEY `idx_temperature` (`temperature_zone`),
  ADD KEY `idx_parent` (`parent_location_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tenant` (`tenant_id`),
  ADD KEY `fk_category` (`category_id`);

--
-- Indexes for table `sub_accounts`
--
ALTER TABLE `sub_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_head_id` (`account_head_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_account_head_id` (`account_head_id`),
  ADD KEY `idx_parent_id` (`parent_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_suppliers_tenant_code` (`tenant_id`,`supplier_code`),
  ADD KEY `fk_suppliers_updated_by` (`updated_by`),
  ADD KEY `idx_suppliers_tenant_id` (`tenant_id`),
  ADD KEY `idx_suppliers_supplier_code` (`supplier_code`),
  ADD KEY `idx_suppliers_supplier_name` (`supplier_name`),
  ADD KEY `idx_suppliers_primary_phone` (`primary_phone`),
  ADD KEY `idx_suppliers_email` (`email`),
  ADD KEY `idx_suppliers_status` (`status`),
  ADD KEY `idx_suppliers_is_blacklisted` (`is_blacklisted`),
  ADD KEY `idx_suppliers_current_balance` (`current_balance`),
  ADD KEY `idx_suppliers_created_at` (`created_at`),
  ADD KEY `idx_suppliers_updated_at` (`updated_at`),
  ADD KEY `idx_suppliers_created_by` (`created_by`),
  ADD KEY `idx_suppliers_tenant_status` (`tenant_id`,`status`),
  ADD KEY `idx_suppliers_tenant_blacklisted` (`tenant_id`,`is_blacklisted`),
  ADD KEY `idx_suppliers_tenant_balance` (`tenant_id`,`current_balance`),
  ADD KEY `idx_suppliers_tenant_phone` (`tenant_id`,`primary_phone`),
  ADD KEY `idx_suppliers_tenant_email` (`tenant_id`,`email`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_suppliers_supplier_type` (`supplier_type`),
  ADD KEY `fk_suppliers_salesman` (`salesman_id`);
ALTER TABLE `suppliers` ADD FULLTEXT KEY `idx_suppliers_name_address_ft` (`supplier_name`,`address`);

--
-- Indexes for table `supplier_sub_accounts`
--
ALTER TABLE `supplier_sub_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_supplier_sub_account` (`tenant_id`,`supplier_id`,`sub_account_name`),
  ADD KEY `idx_supplier_sub_accounts_tenant` (`tenant_id`),
  ADD KEY `idx_supplier_sub_accounts_supplier` (`supplier_id`);

--
-- Indexes for table `suspension_records`
--
ALTER TABLE `suspension_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_suspension` (`employee_id`);

--
-- Indexes for table `tax_rates`
--
ALTER TABLE `tax_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tax_lookup` (`tax_authority`,`tax_type`,`transaction_type`,`applicable_to`,`party_type`,`is_filer`,`is_active`),
  ADD KEY `idx_effective_dates` (`effective_from`,`effective_to`),
  ADD KEY `idx_legal_section` (`legal_section`),
  ADD KEY `idx_finance_act` (`finance_act_year`),
  ADD KEY `fk_regime_id` (`tax_regime_id`);

--
-- Indexes for table `tax_regimes`
--
ALTER TABLE `tax_regimes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_regime_code` (`regime_code`),
  ADD KEY `idx_regime_lookup` (`tax_authority`,`tax_base`,`is_active`);

--
-- Indexes for table `tenant_currencies`
--
ALTER TABLE `tenant_currencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenant_currency` (`tenant_id`,`currency_id`),
  ADD KEY `fk_tenant_currencies_created_by` (`created_by`),
  ADD KEY `idx_tenant_currencies_tenant_id` (`tenant_id`),
  ADD KEY `idx_tenant_currencies_currency_id` (`currency_id`),
  ADD KEY `idx_tenant_currencies_is_base` (`is_base_currency`),
  ADD KEY `idx_tenant_currencies_is_active` (`is_active`),
  ADD KEY `idx_tenant_currencies_tenant_active` (`tenant_id`,`is_active`);

--
-- Indexes for table `termination_records`
--
ALTER TABLE `termination_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_termination` (`employee_id`);

--
-- Indexes for table `unified_logs`
--
ALTER TABLE `unified_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_date` (`tenant_id`,`created_at`),
  ADD KEY `idx_log_type_category` (`log_type`,`event_category`,`created_at`),
  ADD KEY `idx_user_activity` (`user_id`,`created_at`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_security_events` (`is_security_event`,`created_at`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_severity` (`severity`),
  ADD KEY `idx_ip_hash` (`ip_hash`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `uom`
--
ALTER TABLE `uom`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_base_unit` (`base_unit_id`),
  ADD KEY `fk_tenant` (`tenant_id`);

--
-- Indexes for table `uom_groups`
--
ALTER TABLE `uom_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `uom_group_units`
--
ALTER TABLE `uom_group_units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uom_group_id` (`uom_group_id`),
  ADD KEY `uom_id` (`uom_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant_email` (`tenant_id`,`email`),
  ADD KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_last_login` (`last_login_at`);

--
-- Indexes for table `user_permission_overrides`
--
ALTER TABLE `user_permission_overrides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_permission_override` (`tenant_id`,`user_id`,`company_id`,`permission_key`),
  ADD KEY `idx_user_company` (`tenant_id`,`user_id`,`company_id`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `permission_key` (`permission_key`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_company` (`tenant_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_session_token` (`session_token`),
  ADD KEY `idx_user_tenant` (`user_id`,`tenant_id`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `whatsapp_integration`
--
ALTER TABLE `whatsapp_integration`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant` (`tenant_id`);

--
-- Indexes for table `work_in_progress`
--
ALTER TABLE `work_in_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_order_id` (`production_order_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `machine_id` (`machine_id`);

--
-- Indexes for table `work_in_progress_items`
--
ALTER TABLE `work_in_progress_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_in_progress_id` (`work_in_progress_id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `uom_id` (`uom_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounting_ledger`
--
ALTER TABLE `accounting_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accounts_head`
--
ALTER TABLE `accounts_head`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bill_of_materials`
--
ALTER TABLE `bill_of_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bom_materials`
--
ALTER TABLE `bom_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cash_opening`
--
ALTER TABLE `cash_opening`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `city_zones`
--
ALTER TABLE `city_zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `completed_products`
--
ALTER TABLE `completed_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cost_centers`
--
ALTER TABLE `cost_centers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `credit_purchase_history`
--
ALTER TABLE `credit_purchase_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_entry`
--
ALTER TABLE `customer_entry`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_sub_accounts`
--
ALTER TABLE `customer_sub_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_types`
--
ALTER TABLE `customer_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_integration`
--
ALTER TABLE `email_integration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_voucher`
--
ALTER TABLE `expense_voucher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_voucher_line`
--
ALTER TABLE `expense_voucher_line`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `followups`
--
ALTER TABLE `followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fueling_stations`
--
ALTER TABLE `fueling_stations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hs_code_tax_rates`
--
ALTER TABLE `hs_code_tax_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `integration_credits`
--
ALTER TABLE `integration_credits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `integration_message_log`
--
ALTER TABLE `integration_message_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inward_gatepass`
--
ALTER TABLE `inward_gatepass`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inward_gatepass_items`
--
ALTER TABLE `inward_gatepass_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ip_security`
--
ALTER TABLE `ip_security`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_voucher`
--
ALTER TABLE `journal_voucher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_voucher_line`
--
ALTER TABLE `journal_voucher_line`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_records`
--
ALTER TABLE `leave_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `machines`
--
ALTER TABLE `machines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `opening_balance_invoices`
--
ALTER TABLE `opening_balance_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `outward_gatepass`
--
ALTER TABLE `outward_gatepass`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `outward_gatepass_items`
--
ALTER TABLE `outward_gatepass_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_voucher`
--
ALTER TABLE `payment_voucher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_entries`
--
ALTER TABLE `payroll_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_entries_items`
--
ALTER TABLE `payroll_entries_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `post_dated_cheques`
--
ALTER TABLE `post_dated_cheques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_completions`
--
ALTER TABLE `production_completions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_expenses`
--
ALTER TABLE `production_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_expense_accounts`
--
ALTER TABLE `production_expense_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_orders`
--
ALTER TABLE `production_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_order_materials`
--
ALTER TABLE `production_order_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_uom_conversions`
--
ALTER TABLE `product_uom_conversions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_invoice`
--
ALTER TABLE `purchase_invoice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_invoice_items`
--
ALTER TABLE `purchase_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order`
--
ALTER TABLE `purchase_order`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_return`
--
ALTER TABLE `purchase_return`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_list`
--
ALTER TABLE `rate_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_list_items`
--
ALTER TABLE `rate_list_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `receive_voucher`
--
ALTER TABLE `receive_voucher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `regions`
--
ALTER TABLE `regions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rent_items`
--
ALTER TABLE `rent_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rent_management`
--
ALTER TABLE `rent_management`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `revenue_split`
--
ALTER TABLE `revenue_split`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_invoice`
--
ALTER TABLE `sale_invoice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_invoice_items`
--
ALTER TABLE `sale_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_order`
--
ALTER TABLE `sale_order`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_order_items`
--
ALTER TABLE `sale_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_return`
--
ALTER TABLE `sale_return`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_integration`
--
ALTER TABLE `sms_integration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `station_daily_usage`
--
ALTER TABLE `station_daily_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `station_maintenance_history`
--
ALTER TABLE `station_maintenance_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_adjustment`
--
ALTER TABLE `stock_adjustment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_adjustment_items`
--
ALTER TABLE `stock_adjustment_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_opening`
--
ALTER TABLE `stock_opening`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_transfer`
--
ALTER TABLE `stock_transfer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `storage_locations`
--
ALTER TABLE `storage_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sub_accounts`
--
ALTER TABLE `sub_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_sub_accounts`
--
ALTER TABLE `supplier_sub_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suspension_records`
--
ALTER TABLE `suspension_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_rates`
--
ALTER TABLE `tax_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_regimes`
--
ALTER TABLE `tax_regimes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenant_currencies`
--
ALTER TABLE `tenant_currencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `termination_records`
--
ALTER TABLE `termination_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `unified_logs`
--
ALTER TABLE `unified_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uom`
--
ALTER TABLE `uom`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uom_groups`
--
ALTER TABLE `uom_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uom_group_units`
--
ALTER TABLE `uom_group_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_permission_overrides`
--
ALTER TABLE `user_permission_overrides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_integration`
--
ALTER TABLE `whatsapp_integration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_in_progress`
--
ALTER TABLE `work_in_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_in_progress_items`
--
ALTER TABLE `work_in_progress_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounting_ledger`
--
ALTER TABLE `accounting_ledger`
  ADD CONSTRAINT `accounting_ledger_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_accounting_ledger_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_sub_account` FOREIGN KEY (`sub_account_id`) REFERENCES `sub_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `accounts_head`
--
ALTER TABLE `accounts_head`
  ADD CONSTRAINT `fk_accounts_head_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `areas`
--
ALTER TABLE `areas`
  ADD CONSTRAINT `fk_areas_city_zone` FOREIGN KEY (`city_zone_id`) REFERENCES `city_zones` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `branches_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `branches_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `branches_ibfk_3` FOREIGN KEY (`parent_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `branches_ibfk_4` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cities`
--
ALTER TABLE `cities`
  ADD CONSTRAINT `fk_cities_region` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `city_zones`
--
ALTER TABLE `city_zones`
  ADD CONSTRAINT `fk_city_zones_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cost_centers`
--
ALTER TABLE `cost_centers`
  ADD CONSTRAINT `fk_cost_centers_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customers_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `customer_sub_accounts`
--
ALTER TABLE `customer_sub_accounts`
  ADD CONSTRAINT `customer_sub_accounts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `customer_sub_accounts_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`);

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `departments_ibfk_2` FOREIGN KEY (`department_head_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `departments_ibfk_3` FOREIGN KEY (`parent_department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employees_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `employees_ibfk_4` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`),
  ADD CONSTRAINT `fk_employees_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_employees_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_employees_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `expense_voucher`
--
ALTER TABLE `expense_voucher`
  ADD CONSTRAINT `fk_ev_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `expense_voucher_line`
--
ALTER TABLE `expense_voucher_line`
  ADD CONSTRAINT `fk_evl_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evl_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evl_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evl_method` FOREIGN KEY (`payment_method_id`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evl_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `expense_voucher` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `followups`
--
ALTER TABLE `followups`
  ADD CONSTRAINT `fk_followups_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fueling_stations`
--
ALTER TABLE `fueling_stations`
  ADD CONSTRAINT `fueling_stations_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fueling_stations_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`),
  ADD CONSTRAINT `fueling_stations_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fueling_stations_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `inward_gatepass_items`
--
ALTER TABLE `inward_gatepass_items`
  ADD CONSTRAINT `fk_gatepass` FOREIGN KEY (`gatepass_id`) REFERENCES `inward_gatepass` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ip_security`
--
ALTER TABLE `ip_security`
  ADD CONSTRAINT `ip_security_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ip_security_ibfk_2` FOREIGN KEY (`blocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `journal_voucher_line`
--
ALTER TABLE `journal_voucher_line`
  ADD CONSTRAINT `journal_voucher_line_ibfk_1` FOREIGN KEY (`voucher_id`) REFERENCES `journal_voucher` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD CONSTRAINT `login_attempts_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `login_attempts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `outward_gatepass_items`
--
ALTER TABLE `outward_gatepass_items`
  ADD CONSTRAINT `fk_outward_gatepass` FOREIGN KEY (`gatepass_id`) REFERENCES `outward_gatepass` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payment_voucher`
--
ALTER TABLE `payment_voucher`
  ADD CONSTRAINT `fk_payment_voucher_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_payment_voucher_payment_method` FOREIGN KEY (`payment_method_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_payment_voucher_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `fk_payment_voucher_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`),
  ADD CONSTRAINT `fk_payment_voucher_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payroll_entries_items`
--
ALTER TABLE `payroll_entries_items`
  ADD CONSTRAINT `payroll_entries_items_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll_entries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_entries_items_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_entries_items_ibfk_3` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `positions_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `post_dated_cheques`
--
ALTER TABLE `post_dated_cheques`
  ADD CONSTRAINT `fk_pdc_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_pdc_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `fk_pdc_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_pdc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`),
  ADD CONSTRAINT `fk_pdc_vendor` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_products_default_unit_id` FOREIGN KEY (`default_unit_id`) REFERENCES `uom` (`id`),
  ADD CONSTRAINT `fk_products_tax_regime` FOREIGN KEY (`tax_regime_id`) REFERENCES `tax_regimes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_products_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_uom_conversions`
--
ALTER TABLE `product_uom_conversions`
  ADD CONSTRAINT `fk_product_uom_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_product_uom_uom` FOREIGN KEY (`uom_id`) REFERENCES `uom` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_invoice`
--
ALTER TABLE `purchase_invoice`
  ADD CONSTRAINT `fk_purchase_invoice_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_purchase_invoice_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_purchase_invoice_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `fk_purchase_invoice_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_purchase_invoice_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_invoice_items`
--
ALTER TABLE `purchase_invoice_items`
  ADD CONSTRAINT `fk_purchase_invoice_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_purchase_invoice_items_invoice` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_invoice` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_purchase_invoice_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_purchase_invoice_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_purchase_invoice_items_uom` FOREIGN KEY (`uom_id`) REFERENCES `uom` (`id`),
  ADD CONSTRAINT `fk_purchase_invoice_items_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_order`
--
ALTER TABLE `purchase_order`
  ADD CONSTRAINT `fk_purchase_order_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_purchase_order_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_purchase_order_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `fk_purchase_order_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_purchase_order_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `fk_purchase_order_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_purchase_order_items_invoice` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_order` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_purchase_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_purchase_order_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_purchase_order_items_uom` FOREIGN KEY (`uom_id`) REFERENCES `uom` (`id`),
  ADD CONSTRAINT `fk_purchase_order_items_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_return`
--
ALTER TABLE `purchase_return`
  ADD CONSTRAINT `fk_purchase_return_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_purchase_return_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_purchase_return_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `fk_purchase_return_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_purchase_return_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  ADD CONSTRAINT `fk_purchase_return_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_purchase_return_items_invoice` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_return` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_purchase_return_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_purchase_return_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_purchase_return_items_uom` FOREIGN KEY (`uom_id`) REFERENCES `uom` (`id`),
  ADD CONSTRAINT `fk_purchase_return_items_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `rate_list_items`
--
ALTER TABLE `rate_list_items`
  ADD CONSTRAINT `fk_rate_list_items_rate_list` FOREIGN KEY (`rate_list_id`) REFERENCES `rate_list` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `receive_voucher`
--
ALTER TABLE `receive_voucher`
  ADD CONSTRAINT `fk_receive_voucher_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_receive_voucher_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_receive_voucher_payment_method` FOREIGN KEY (`payment_method_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_receive_voucher_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`),
  ADD CONSTRAINT `fk_receive_voucher_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `regions`
--
ALTER TABLE `regions`
  ADD CONSTRAINT `fk_regions_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rent_items`
--
ALTER TABLE `rent_items`
  ADD CONSTRAINT `rent_items_ibfk_1` FOREIGN KEY (`rent_id`) REFERENCES `rent_management` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rent_management`
--
ALTER TABLE `rent_management`
  ADD CONSTRAINT `fk_rent_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `revenue_split`
--
ALTER TABLE `revenue_split`
  ADD CONSTRAINT `fk_rs_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rs_station_daily_usage` FOREIGN KEY (`station_daily_usage_id`) REFERENCES `station_daily_usage` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sale_invoice`
--
ALTER TABLE `sale_invoice`
  ADD CONSTRAINT `fk_sale_invoice_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_sale_invoice_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_sale_invoice_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_sale_invoice_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sale_invoice_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_invoice_items`
--
ALTER TABLE `sale_invoice_items`
  ADD CONSTRAINT `fk_sale_invoice_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_sale_invoice_items_invoice` FOREIGN KEY (`sale_invoice_id`) REFERENCES `sale_invoice` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sale_invoice_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_sale_invoice_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sale_invoice_items_uom` FOREIGN KEY (`uom_id`) REFERENCES `uom` (`id`),
  ADD CONSTRAINT `fk_sale_invoice_items_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_order`
--
ALTER TABLE `sale_order`
  ADD CONSTRAINT `fk_sale_order_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_sale_order_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_sale_order_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_sale_order_supplier_man` FOREIGN KEY (`supplier_man_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sale_order_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sale_order_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_order_items`
--
ALTER TABLE `sale_order_items`
  ADD CONSTRAINT `fk_sale_order_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_sale_order_items_invoice` FOREIGN KEY (`sale_invoice_id`) REFERENCES `sale_order` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sale_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_sale_order_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sale_order_items_uom` FOREIGN KEY (`uom_id`) REFERENCES `uom` (`id`),
  ADD CONSTRAINT `fk_sale_order_items_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_return`
--
ALTER TABLE `sale_return`
  ADD CONSTRAINT `fk_sale_return_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_sale_return_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_sale_return_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_sale_return_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sale_return_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
  ADD CONSTRAINT `fk_sale_return_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_sale_return_items_invoice` FOREIGN KEY (`sale_invoice_id`) REFERENCES `sale_return` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sale_return_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_sale_return_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sale_return_items_uom` FOREIGN KEY (`uom_id`) REFERENCES `uom` (`id`),
  ADD CONSTRAINT `fk_sale_return_items_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `station_daily_usage`
--
ALTER TABLE `station_daily_usage`
  ADD CONSTRAINT `fk_station_daily_usage_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_station_daily_usage_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_station_daily_usage_unit` FOREIGN KEY (`unit_id`) REFERENCES `uom` (`id`),
  ADD CONSTRAINT `station_daily_usage_ibfk_1` FOREIGN KEY (`station_id`) REFERENCES `fueling_stations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `station_daily_usage_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `station_daily_usage_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `station_maintenance_history`
--
ALTER TABLE `station_maintenance_history`
  ADD CONSTRAINT `station_maintenance_history_ibfk_1` FOREIGN KEY (`station_id`) REFERENCES `fueling_stations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `station_maintenance_history_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `station_maintenance_history_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_adjustment`
--
ALTER TABLE `stock_adjustment`
  ADD CONSTRAINT `fk_sa_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_sa_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`);

--
-- Constraints for table `stock_adjustment_items`
--
ALTER TABLE `stock_adjustment_items`
  ADD CONSTRAINT `fk_sai_adjustment` FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustment` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sai_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_sai_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`);

--
-- Constraints for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD CONSTRAINT `fk_stock_ledger_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_ledger_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_ledger_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `stock_opening`
--
ALTER TABLE `stock_opening`
  ADD CONSTRAINT `fk_stock_opening_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_opening_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_opening_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_transfer`
--
ALTER TABLE `stock_transfer`
  ADD CONSTRAINT `fk_st_from_location` FOREIGN KEY (`from_location_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_st_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_st_to_location` FOREIGN KEY (`to_location_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  ADD CONSTRAINT `fk_sti_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sti_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sti_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `storage_locations`
--
ALTER TABLE `storage_locations`
  ADD CONSTRAINT `storage_locations_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `storage_locations_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `storage_locations_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `storage_locations_ibfk_4` FOREIGN KEY (`parent_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sub_accounts`
--
ALTER TABLE `sub_accounts`
  ADD CONSTRAINT `fk_sub_accounts_parent` FOREIGN KEY (`parent_id`) REFERENCES `sub_accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sub_accounts_ibfk_1` FOREIGN KEY (`account_head_id`) REFERENCES `accounts_head` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sub_accounts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `fk_suppliers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_suppliers_salesman` FOREIGN KEY (`salesman_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_suppliers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_suppliers_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `supplier_sub_accounts`
--
ALTER TABLE `supplier_sub_accounts`
  ADD CONSTRAINT `supplier_sub_accounts_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `supplier_sub_accounts_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`);

--
-- Constraints for table `tax_rates`
--
ALTER TABLE `tax_rates`
  ADD CONSTRAINT `fk_tax_rate_regime` FOREIGN KEY (`tax_regime_id`) REFERENCES `tax_regimes` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `tenant_currencies`
--
ALTER TABLE `tenant_currencies`
  ADD CONSTRAINT `fk_tenant_currencies_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tenant_currencies_currency` FOREIGN KEY (`currency_id`) REFERENCES `ledgerone_public`.`currencies` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tenant_currencies_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `unified_logs`
--
ALTER TABLE `unified_logs`
  ADD CONSTRAINT `unified_logs_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `unified_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `uom`
--
ALTER TABLE `uom`
  ADD CONSTRAINT `fk_base_unit` FOREIGN KEY (`base_unit_id`) REFERENCES `uom` (`id`),
  ADD CONSTRAINT `fk_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`);

--
-- Constraints for table `uom_group_units`
--
ALTER TABLE `uom_group_units`
  ADD CONSTRAINT `uom_group_units_ibfk_1` FOREIGN KEY (`uom_group_id`) REFERENCES `uom_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `uom_group_units_ibfk_2` FOREIGN KEY (`uom_id`) REFERENCES `uom` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_permission_overrides`
--
ALTER TABLE `user_permission_overrides`
  ADD CONSTRAINT `user_permission_overrides_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permission_overrides_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permission_overrides_ibfk_3` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permission_overrides_ibfk_4` FOREIGN KEY (`permission_key`) REFERENCES `ledgerone_public`.`permissions` (`permission_key`),
  ADD CONSTRAINT `user_permission_overrides_ibfk_5` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_4` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_5` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_sessions_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_in_progress`
--
ALTER TABLE `work_in_progress`
  ADD CONSTRAINT `work_in_progress_ibfk_1` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`),
  ADD CONSTRAINT `work_in_progress_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `work_in_progress_ibfk_3` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`);

--
-- Constraints for table `work_in_progress_items`
--
ALTER TABLE `work_in_progress_items`
  ADD CONSTRAINT `work_in_progress_items_ibfk_1` FOREIGN KEY (`work_in_progress_id`) REFERENCES `work_in_progress` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `work_in_progress_items_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `work_in_progress_items_ibfk_3` FOREIGN KEY (`uom_id`) REFERENCES `uom` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;