-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 31.97.123.46:3306
-- Generation Time: Sep 12, 2026 at 02:03 PM
-- Server version: 10.5.29-MariaDB
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

--
-- Dumping data for table `billing_logs`
--

INSERT INTO `billing_logs` (`id`, `tenant_id`, `subscription_id`, `plan_id`, `billing_cycle_start`, `billing_cycle_end`, `amount_paid`, `amount_due`, `discount_amount`, `tax_amount`, `tax_rate`, `currency`, `payment_method`, `payment_gateway`, `transaction_id`, `processed_by`, `ip_address`, `user_agent`, `notes`, `gateway_response`, `gateway_fee`, `gateway_transaction_url`, `payment_status`, `refund_amount`, `refund_reason`, `refunded_at`, `refunded_by`, `invoice_number`, `receipt_voucher_code`, `is_recorded_in_erp`, `erp_recorded_at`, `erp_recorded_by`, `invoice_url`, `invoice_sent_at`, `receipt_url`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, 2, '2026-02-24', '2026-03-24', 0.00, 0.00, 0.00, 0.00, 0.00, 'PKR', 'bank_transfer', 'manual', 'PENDING-2-1771959549', NULL, NULL, NULL, NULL, NULL, 0.00, NULL, 'pending', 0.00, NULL, NULL, NULL, 'INV-2-1771959549', NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-02-24 18:59:09', NULL),
(2, 42, NULL, 4, '2026-02-28', '2026-03-28', 0.00, 0.00, 0.00, 0.00, 0.00, 'PKR', 'bank_transfer', 'manual', 'PENDING-42-1772294246', NULL, NULL, NULL, NULL, NULL, 0.00, NULL, 'pending', 0.00, NULL, NULL, NULL, 'INV-42-1772294246', NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-02-28 15:57:26', NULL),
(3, 42, NULL, 1, '2026-03-07', '2026-04-07', 0.00, 0.00, 0.00, 0.00, 0.00, 'PKR', '2checkout', '2checkout', 'TXN-42-1772914967', NULL, NULL, NULL, NULL, NULL, 0.00, NULL, 'success', 0.00, NULL, NULL, NULL, 'INV-42-1772914967', NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-03-07 20:22:47', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `id` int(11) NOT NULL,
  `code` char(3) NOT NULL,
  `name` varchar(50) NOT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `main_unit_name` varchar(50) DEFAULT NULL,
  `sub_unit_name` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_base_currency` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`, `main_unit_name`, `sub_unit_name`, `is_active`, `is_base_currency`, `created_at`) VALUES
(1, 'USD', 'US Dollar', '$', 'Dollars', 'Cents', 1, 0, '2025-11-16 22:20:51'),
(2, 'PKR', 'Pakistani Rupee', '₨', 'Rupees', 'Paisa', 1, 1, '2025-11-16 22:20:51'),
(3, 'EUR', 'Euro', '€', 'Euros', 'Cents', 1, 0, '2025-11-16 22:20:51'),
(4, 'GBP', 'British Pound', '£', 'Pounds', 'Pence', 1, 0, '2025-11-16 22:20:51'),
(5, 'AED', 'UAE Dirham', 'د.إ', 'Dirhams', 'Fils', 1, 0, '2025-11-16 22:20:51'),
(6, 'SAR', 'Saudi Riyal', '﷼', 'Riyals', 'Halalas', 1, 0, '2025-11-16 22:20:51'),
(7, 'INR', 'Indian Rupee', '₹', 'Rupees', 'Paise', 1, 0, '2025-11-16 22:20:51'),
(8, 'CNY', 'Chinese Yuan', '¥', 'Yuan', 'Jiao', 1, 0, '2026-03-13 01:43:58'),
(9, 'AFN', 'Afghan Afghani', '؋', 'Afghani', 'Pul', 1, 0, '2026-04-04 15:29:58'),
(10, 'ALL', 'Albanian Lek', 'L', 'Lek', 'Qindarka', 1, 0, '2026-04-04 15:29:58'),
(11, 'AMD', 'Armenian Dram', '֏', 'Dram', 'Luma', 1, 0, '2026-04-04 15:29:58'),
(12, 'ANG', 'Netherlands Antillean Guilder', 'ƒ', 'Guilder', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(13, 'AOA', 'Angolan Kwanza', 'Kz', 'Kwanza', 'Centimos', 1, 0, '2026-04-04 15:29:58'),
(14, 'ARS', 'Argentine Peso', '$', 'Pesos', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(15, 'AUD', 'Australian Dollar', 'A$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(16, 'AWG', 'Aruban Florin', 'ƒ', 'Florin', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(17, 'AZN', 'Azerbaijani Manat', '₼', 'Manat', 'Qapik', 1, 0, '2026-04-04 15:29:58'),
(18, 'BAM', 'Bosnia-Herzegovina Convertible Mark', 'KM', 'Marks', 'Feninga', 1, 0, '2026-04-04 15:29:58'),
(19, 'BBD', 'Barbadian Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(20, 'BDT', 'Bangladeshi Taka', '৳', 'Taka', 'Poisha', 1, 0, '2026-04-04 15:29:58'),
(21, 'BGN', 'Bulgarian Lev', 'лв', 'Lev', 'Stotinki', 1, 0, '2026-04-04 15:29:58'),
(22, 'BHD', 'Bahraini Dinar', '.د.ب', 'Dinars', 'Fils', 1, 0, '2026-04-04 15:29:58'),
(23, 'BIF', 'Burundian Franc', 'Fr', 'Francs', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(24, 'BMD', 'Bermudan Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(25, 'BND', 'Brunei Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(26, 'BOB', 'Bolivian Boliviano', 'Bs.', 'Boliviano', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(27, 'BRL', 'Brazilian Real', 'R$', 'Reais', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(28, 'BSD', 'Bahamian Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(29, 'BTN', 'Bhutanese Ngultrum', 'Nu.', 'Ngultrum', 'Chetrum', 1, 0, '2026-04-04 15:29:58'),
(30, 'BWP', 'Botswanan Pula', 'P', 'Pula', 'Thebe', 1, 0, '2026-04-04 15:29:58'),
(31, 'BYN', 'Belarusian Ruble', 'Br', 'Rubles', 'Kopeks', 1, 0, '2026-04-04 15:29:58'),
(32, 'BZD', 'Belize Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(33, 'CAD', 'Canadian Dollar', 'C$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(34, 'CDF', 'Congolese Franc', 'Fr', 'Francs', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(35, 'CHF', 'Swiss Franc', 'Fr', 'Francs', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(36, 'CLP', 'Chilean Peso', '$', 'Pesos', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(37, 'COP', 'Colombian Peso', '$', 'Pesos', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(38, 'CRC', 'Costa Rican Colón', '₡', 'Colones', 'Centimos', 1, 0, '2026-04-04 15:29:58'),
(39, 'CUP', 'Cuban Peso', '$', 'Pesos', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(40, 'CVE', 'Cape Verdean Escudo', '$', 'Escudos', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(41, 'CZK', 'Czech Koruna', 'Kč', 'Koruny', 'Haleru', 1, 0, '2026-04-04 15:29:58'),
(42, 'DJF', 'Djiboutian Franc', 'Fr', 'Francs', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(43, 'DKK', 'Danish Krone', 'kr', 'Kroner', 'Ore', 1, 0, '2026-04-04 15:29:58'),
(44, 'DOP', 'Dominican Peso', '$', 'Pesos', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(45, 'DZD', 'Algerian Dinar', 'د.ج', 'Dinars', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(46, 'EGP', 'Egyptian Pound', '£', 'Pounds', 'Piastres', 1, 0, '2026-04-04 15:29:58'),
(47, 'ERN', 'Eritrean Nakfa', 'Nfk', 'Nakfa', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(48, 'ETB', 'Ethiopian Birr', 'Br', 'Birr', 'Santim', 1, 0, '2026-04-04 15:29:58'),
(49, 'FJD', 'Fijian Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(50, 'FKP', 'Falkland Islands Pound', '£', 'Pounds', 'Pence', 1, 0, '2026-04-04 15:29:58'),
(51, 'FOK', 'Faroese Króna', 'kr', 'Kronur', 'Oyru', 1, 0, '2026-04-04 15:29:58'),
(52, 'GEL', 'Georgian Lari', '₾', 'Lari', 'Tetri', 1, 0, '2026-04-04 15:29:58'),
(53, 'GGP', 'Guernsey Pound', '£', 'Pounds', 'Pence', 1, 0, '2026-04-04 15:29:58'),
(54, 'GHS', 'Ghanaian Cedi', '₵', 'Cedis', 'Pesewas', 1, 0, '2026-04-04 15:29:58'),
(55, 'GIP', 'Gibraltar Pound', '£', 'Pounds', 'Pence', 1, 0, '2026-04-04 15:29:58'),
(56, 'GMD', 'Gambian Dalasi', 'D', 'Dalasi', 'Butut', 1, 0, '2026-04-04 15:29:58'),
(57, 'GNF', 'Guinean Franc', 'Fr', 'Francs', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(58, 'GTQ', 'Guatemalan Quetzal', 'Q', 'Quetzales', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(59, 'GYD', 'Guyanaese Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(60, 'HKD', 'Hong Kong Dollar', 'HK$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(61, 'HNL', 'Honduran Lempira', 'L', 'Lempiras', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(62, 'HRK', 'Croatian Kuna', 'kn', 'Kuna', 'Lipa', 1, 0, '2026-04-04 15:29:58'),
(63, 'HTG', 'Haitian Gourde', 'G', 'Gourdes', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(64, 'HUF', 'Hungarian Forint', 'Ft', 'Forint', 'Filler', 1, 0, '2026-04-04 15:29:58'),
(65, 'IDR', 'Indonesian Rupiah', 'Rp', 'Rupiah', 'Sen', 1, 0, '2026-04-04 15:29:58'),
(66, 'ILS', 'Israeli New Shekel', '₪', 'Shekels', 'Agorot', 1, 0, '2026-04-04 15:29:58'),
(67, 'IMP', 'Manx Pound', '£', 'Pounds', 'Pence', 1, 0, '2026-04-04 15:29:58'),
(68, 'IQD', 'Iraqi Dinar', 'ع.د', 'Dinars', 'Fils', 1, 0, '2026-04-04 15:29:58'),
(69, 'IRR', 'Iranian Rial', '﷼', 'Rials', 'Dinars', 1, 0, '2026-04-04 15:29:58'),
(70, 'ISK', 'Icelandic Króna', 'kr', 'Kronur', 'Aurar', 1, 0, '2026-04-04 15:29:58'),
(71, 'JEP', 'Jersey Pound', '£', 'Pounds', 'Pence', 1, 0, '2026-04-04 15:29:58'),
(72, 'JMD', 'Jamaican Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(73, 'JOD', 'Jordanian Dinar', 'د.ا', 'Dinars', 'Fils', 1, 0, '2026-04-04 15:29:58'),
(74, 'JPY', 'Japanese Yen', '¥', 'Yen', 'Sen', 1, 0, '2026-04-04 15:29:58'),
(75, 'KES', 'Kenyan Shilling', 'Sh', 'Shillings', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(76, 'KGS', 'Kyrgystani Som', 'с', 'Som', 'Tyiyn', 1, 0, '2026-04-04 15:29:58'),
(77, 'KHR', 'Cambodian Riel', '៛', 'Riels', 'Sen', 1, 0, '2026-04-04 15:29:58'),
(78, 'KID', 'Kiribati Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(79, 'KMF', 'Comorian Franc', 'Fr', 'Francs', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(80, 'KRW', 'South Korean Won', '₩', 'Won', 'Jeon', 1, 0, '2026-04-04 15:29:58'),
(81, 'KWD', 'Kuwaiti Dinar', 'د.ك', 'Dinars', 'Fils', 1, 0, '2026-04-04 15:29:58'),
(82, 'KYD', 'Cayman Islands Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(83, 'KZT', 'Kazakhstani Tenge', '₸', 'Tenge', 'Tiyn', 1, 0, '2026-04-04 15:29:58'),
(84, 'LAK', 'Laotian Kip', '₭', 'Kip', 'Att', 1, 0, '2026-04-04 15:29:58'),
(85, 'LBP', 'Lebanese Pound', '£', 'Pounds', 'Piastres', 1, 0, '2026-04-04 15:29:58'),
(86, 'LKR', 'Sri Lankan Rupee', 'Rs', 'Rupees', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(87, 'LRD', 'Liberian Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(88, 'LSL', 'Lesotho Loti', 'L', 'Maloti', 'Lisente', 1, 0, '2026-04-04 15:29:58'),
(89, 'LYD', 'Libyan Dinar', 'ل.د', 'Dinars', 'Dirhams', 1, 0, '2026-04-04 15:29:58'),
(90, 'MAD', 'Moroccan Dirham', 'د.م.', 'Dirhams', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(91, 'MDL', 'Moldovan Leu', 'L', 'Lei', 'Bani', 1, 0, '2026-04-04 15:29:58'),
(92, 'MGA', 'Malagasy Ariary', 'Ar', 'Ariary', 'Iraimbilanja', 1, 0, '2026-04-04 15:29:58'),
(93, 'MKD', 'Macedonian Denar', 'ден', 'Denari', 'Deni', 1, 0, '2026-04-04 15:29:58'),
(94, 'MMK', 'Myanma Kyat', 'K', 'Kyats', 'Pyas', 1, 0, '2026-04-04 15:29:58'),
(95, 'MNT', 'Mongolian Tugrik', '₮', 'Tugriks', 'Mongo', 1, 0, '2026-04-04 15:29:58'),
(96, 'MOP', 'Macanese Pataca', 'P', 'Patacas', 'Avos', 1, 0, '2026-04-04 15:29:58'),
(97, 'MRU', 'Mauritanian Ouguiya', 'UM', 'Ouguiya', 'Khoums', 1, 0, '2026-04-04 15:29:58'),
(98, 'MUR', 'Mauritian Rupee', '₨', 'Rupees', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(99, 'MVR', 'Maldivian Rufiyaa', '.ރ', 'Rufiyaa', 'Laari', 1, 0, '2026-04-04 15:29:58'),
(100, 'MWK', 'Malawian Kwacha', 'MK', 'Kwacha', 'Tambala', 1, 0, '2026-04-04 15:29:58'),
(101, 'MXN', 'Mexican Peso', '$', 'Pesos', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(102, 'MYR', 'Malaysian Ringgit', 'RM', 'Ringgit', 'Sen', 1, 0, '2026-04-04 15:29:58'),
(103, 'MZN', 'Mozambican Metical', 'MT', 'Meticais', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(104, 'NAD', 'Namibian Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(105, 'NGN', 'Nigerian Naira', '₦', 'Naira', 'Kobo', 1, 0, '2026-04-04 15:29:58'),
(106, 'NIO', 'Nicaraguan Córdoba', 'C$', 'Cordobas', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(107, 'NOK', 'Norwegian Krone', 'kr', 'Kroner', 'Ore', 1, 0, '2026-04-04 15:29:58'),
(108, 'NPR', 'Nepalese Rupee', '₨', 'Rupees', 'Paisa', 1, 0, '2026-04-04 15:29:58'),
(109, 'NZD', 'New Zealand Dollar', 'NZ$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(110, 'OMR', 'Omani Rial', 'ر.ع.', 'Rials', 'Baisa', 1, 0, '2026-04-04 15:29:58'),
(111, 'PAB', 'Panamanian Balboa', 'B/.', 'Balboas', 'Centesimos', 1, 0, '2026-04-04 15:29:58'),
(112, 'PEN', 'Peruvian Nuevo Sol', 'S/', 'Soles', 'Centimos', 1, 0, '2026-04-04 15:29:58'),
(113, 'PGK', 'Papua New Guinean Kina', 'K', 'Kina', 'Toea', 1, 0, '2026-04-04 15:29:58'),
(114, 'PHP', 'Philippine Peso', '₱', 'Pesos', 'Centavos', 1, 0, '2026-04-04 15:29:58'),
(115, 'PLN', 'Polish Zloty', 'zł', 'Zlotych', 'Groszy', 1, 0, '2026-04-04 15:29:58'),
(116, 'PYG', 'Paraguayan Guarani', '₲', 'Guaranies', 'Centimos', 1, 0, '2026-04-04 15:29:58'),
(117, 'QAR', 'Qatari Rial', 'ر.ق', 'Riyals', 'Dirhams', 1, 0, '2026-04-04 15:29:58'),
(118, 'RON', 'Romanian Leu', 'lei', 'Lei', 'Bani', 1, 0, '2026-04-04 15:29:58'),
(119, 'RSD', 'Serbian Dinar', 'дин', 'Dinars', 'Para', 1, 0, '2026-04-04 15:29:58'),
(120, 'RUB', 'Russian Ruble', '₽', 'Rubles', 'Kopeks', 1, 0, '2026-04-04 15:29:58'),
(121, 'RWF', 'Rwandan Franc', 'Fr', 'Francs', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(122, 'SBD', 'Solomon Islands Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(123, 'SCR', 'Seychellois Rupee', '₨', 'Rupees', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(124, 'SDG', 'Sudanese Pound', '£', 'Pounds', 'Piastres', 1, 0, '2026-04-04 15:29:58'),
(125, 'SEK', 'Swedish Krona', 'kr', 'Kronor', 'Ore', 1, 0, '2026-04-04 15:29:58'),
(126, 'SGD', 'Singapore Dollar', 'S$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(127, 'SHP', 'Saint Helena Pound', '£', 'Pounds', 'Pence', 1, 0, '2026-04-04 15:29:58'),
(128, 'SLE', 'Sierra Leonean Leone', 'Le', 'Leones', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(129, 'SLL', 'Sierra Leonean Leone (Old)', 'Le', 'Leones', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(130, 'SOS', 'Somali Shilling', 'Sh', 'Shillings', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(131, 'SRD', 'Surinamese Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(132, 'SSP', 'South Sudanese Pound', '£', 'Pounds', 'Piastres', 1, 0, '2026-04-04 15:29:58'),
(133, 'STN', 'São Tomé and Príncipe Dobra', 'Db', 'Dobras', 'Centimos', 1, 0, '2026-04-04 15:29:58'),
(134, 'SYP', 'Syrian Pound', '£', 'Pounds', 'Piastres', 1, 0, '2026-04-04 15:29:58'),
(135, 'SZL', 'Swazi Lilangeni', 'L', 'Emalangeni', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(136, 'THB', 'Thai Baht', '฿', 'Baht', 'Satang', 1, 0, '2026-04-04 15:29:58'),
(137, 'TJS', 'Tajikistani Somoni', 'ЅМ', 'Somoni', 'Dirams', 1, 0, '2026-04-04 15:29:58'),
(138, 'TMT', 'Turkmenistani Manat', 'm', 'Manat', 'Tennesi', 1, 0, '2026-04-04 15:29:58'),
(139, 'TND', 'Tunisian Dinar', 'د.ت', 'Dinars', 'Millimes', 1, 0, '2026-04-04 15:29:58'),
(140, 'TOP', 'Tongan Paʻanga', 'T$', 'Paanga', 'Seniti', 1, 0, '2026-04-04 15:29:58'),
(141, 'TRY', 'Turkish Lira', '₺', 'Lira', 'Kurus', 1, 0, '2026-04-04 15:29:58'),
(142, 'TTD', 'Trinidad and Tobago Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(143, 'TVD', 'Tuvaluan Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(144, 'TWD', 'New Taiwan Dollar', 'NT$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(145, 'TZS', 'Tanzanian Shilling', 'Sh', 'Shillings', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(146, 'UAH', 'Ukrainian Hryvnia', '₴', 'Hryvnias', 'Kopiyky', 1, 0, '2026-04-04 15:29:58'),
(147, 'UGX', 'Ugandan Shilling', 'Sh', 'Shillings', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(148, 'UYU', 'Uruguayan Peso', '$', 'Pesos', 'Centesimos', 1, 0, '2026-04-04 15:29:58'),
(149, 'UZS', 'Uzbekistan Som', 'so\'m', 'Som', 'Tiyin', 1, 0, '2026-04-04 15:29:58'),
(150, 'VES', 'Venezuelan Bolívar', 'Bs.', 'Bolivares', 'Centimos', 1, 0, '2026-04-04 15:29:58'),
(151, 'VND', 'Vietnamese Dong', '₫', 'Dong', 'Xu', 1, 0, '2026-04-04 15:29:58'),
(152, 'VUV', 'Vanuatu Vatu', 'Vt', 'Vatu', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(153, 'WST', 'Samoan Tala', 'T', 'Tala', 'Sene', 1, 0, '2026-04-04 15:29:58'),
(154, 'XAF', 'Central African CFA Franc', 'Fr', 'Francs', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(155, 'XCD', 'East Caribbean Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(156, 'XDR', 'Special Drawing Rights', 'SDR', 'SDR', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(157, 'XOF', 'West African CFA Franc', 'Fr', 'Francs', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(158, 'XPF', 'CFP Franc', 'Fr', 'Francs', 'Centimes', 1, 0, '2026-04-04 15:29:58'),
(159, 'YER', 'Yemeni Rial', '﷼', 'Rials', 'Fils', 1, 0, '2026-04-04 15:29:58'),
(160, 'ZAR', 'South African Rand', 'R', 'Rand', 'Cents', 1, 0, '2026-04-04 15:29:58'),
(161, 'ZMW', 'Zambian Kwacha', 'ZK', 'Kwacha', 'Ngwee', 1, 0, '2026-04-04 15:29:58'),
(162, 'ZWL', 'Zimbabwean Dollar', '$', 'Dollars', 'Cents', 1, 0, '2026-04-04 15:29:58');

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

--
-- Dumping data for table `currency_exchange_rates`
--

INSERT INTO `currency_exchange_rates` (`id`, `base_currency_code`, `target_currency_code`, `exchange_rate`, `effective_date`, `source`, `is_active`, `created_at`, `updated_at`) VALUES
(12832, 'USD', 'USD', 1.00000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12833, 'USD', 'AED', 3.67250000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12834, 'USD', 'AFN', 64.21100000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12835, 'USD', 'ALL', 82.45710000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12836, 'USD', 'AMD', 369.52130000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12837, 'USD', 'ANG', 1.79000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12838, 'USD', 'AOA', 924.89490000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12839, 'USD', 'ARS', 1460.24980000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12840, 'USD', 'AUD', 1.42790000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12841, 'USD', 'AWG', 1.79000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12842, 'USD', 'AZN', 1.70210000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12843, 'USD', 'BAM', 1.70620000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12844, 'USD', 'BBD', 2.00000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12845, 'USD', 'BDT', 122.68080000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12846, 'USD', 'BGN', 1.70620000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12847, 'USD', 'BHD', 0.37600000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12848, 'USD', 'BIF', 2998.59240000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12849, 'USD', 'BMD', 1.00000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12850, 'USD', 'BND', 1.29200000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12851, 'USD', 'BOB', 6.93180000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12852, 'USD', 'BRL', 5.15290000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12853, 'USD', 'BSD', 1.00000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12854, 'USD', 'BTN', 94.49070000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12855, 'USD', 'BWP', 13.57660000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12856, 'USD', 'BYN', 2.77120000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12857, 'USD', 'BZD', 2.00000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12858, 'USD', 'CAD', 1.41700000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12859, 'USD', 'CDF', 2314.83810000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12860, 'USD', 'CHF', 0.80800000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12861, 'USD', 'CLP', 901.61080000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12862, 'USD', 'CNY', 6.78000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12863, 'USD', 'COP', 3448.95800000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12864, 'USD', 'CRC', 454.38770000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12865, 'USD', 'CUP', 24.00000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12866, 'USD', 'CVE', 96.19170000, '2026-06-22', 'api', 1, '2026-06-22 00:00:13', NULL),
(12867, 'USD', 'CZK', 21.10630000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12868, 'USD', 'DJF', 177.72100000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12869, 'USD', 'DKK', 6.50860000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12870, 'USD', 'DOP', 58.55910000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12871, 'USD', 'DZD', 133.58480000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12872, 'USD', 'EGP', 49.88890000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12873, 'USD', 'ERN', 15.00000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12874, 'USD', 'ETB', 159.70880000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12875, 'USD', 'EUR', 0.87250000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12876, 'USD', 'FJD', 2.22740000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12877, 'USD', 'FKP', 0.75680000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12878, 'USD', 'FOK', 6.50830000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12879, 'USD', 'GBP', 0.75690000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12880, 'USD', 'GEL', 2.66150000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12881, 'USD', 'GGP', 0.75680000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12882, 'USD', 'GHS', 11.26210000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12883, 'USD', 'GIP', 0.75680000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12884, 'USD', 'GMD', 74.45300000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12885, 'USD', 'GNF', 8808.57400000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12886, 'USD', 'GTQ', 7.63590000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12887, 'USD', 'GYD', 210.05230000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12888, 'USD', 'HKD', 7.83730000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12889, 'USD', 'HNL', 26.76730000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12890, 'USD', 'HRK', 6.57290000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12891, 'USD', 'HTG', 131.11800000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12892, 'USD', 'HUF', 307.06420000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12893, 'USD', 'IDR', 17807.79960000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12894, 'USD', 'ILS', 2.96660000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12895, 'USD', 'IMP', 0.75680000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12896, 'USD', 'INR', 94.48890000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12897, 'USD', 'IQD', 1314.26950000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12898, 'USD', 'IRR', 1166184.63020000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12899, 'USD', 'ISK', 125.57310000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12900, 'USD', 'JEP', 0.75680000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12901, 'USD', 'JMD', 158.30470000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12902, 'USD', 'JOD', 0.70900000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12903, 'USD', 'JPY', 161.40050000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12904, 'USD', 'KES', 129.35950000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12905, 'USD', 'KGS', 87.72170000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12906, 'USD', 'KHR', 4054.46670000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12907, 'USD', 'KID', 1.42760000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12908, 'USD', 'KMF', 429.17710000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12909, 'USD', 'KRW', 1531.17780000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12910, 'USD', 'KWD', 0.30810000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12911, 'USD', 'KYD', 0.83330000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12912, 'USD', 'KZT', 487.77840000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12913, 'USD', 'LAK', 22053.58420000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12914, 'USD', 'LBP', 89500.00000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12915, 'USD', 'LKR', 333.51850000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12916, 'USD', 'LRD', 182.20410000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12917, 'USD', 'LSL', 16.46190000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12918, 'USD', 'LYD', 6.39890000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12919, 'USD', 'MAD', 9.31570000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12920, 'USD', 'MDL', 17.46240000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12921, 'USD', 'MGA', 4223.12090000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12922, 'USD', 'MKD', 53.84500000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12923, 'USD', 'MMK', 2104.86290000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12924, 'USD', 'MNT', 3570.27540000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12925, 'USD', 'MOP', 8.07230000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12926, 'USD', 'MRU', 40.19220000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12927, 'USD', 'MUR', 47.84090000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12928, 'USD', 'MVR', 15.45060000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12929, 'USD', 'MWK', 1744.55000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12930, 'USD', 'MXN', 17.34740000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12931, 'USD', 'MYR', 4.13640000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12932, 'USD', 'MZN', 63.83560000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12933, 'USD', 'NAD', 16.46190000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12934, 'USD', 'NGN', 1362.56070000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12935, 'USD', 'NIO', 36.80540000, '2026-06-22', 'api', 1, '2026-06-22 00:00:14', NULL),
(12936, 'USD', 'NOK', 9.70420000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12937, 'USD', 'NPR', 151.18510000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12938, 'USD', 'NZD', 1.74370000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12939, 'USD', 'OMR', 0.38450000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12940, 'USD', 'PAB', 1.00000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12941, 'USD', 'PEN', 3.39010000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12942, 'USD', 'PGK', 4.38950000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12943, 'USD', 'PHP', 60.76430000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12944, 'USD', 'PKR', 278.41520000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12945, 'USD', 'PLN', 3.71440000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12946, 'USD', 'PYG', 6088.64710000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12947, 'USD', 'QAR', 3.64000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12948, 'USD', 'RON', 4.56910000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12949, 'USD', 'RSD', 102.36130000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12950, 'USD', 'RUB', 73.12050000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12951, 'USD', 'RWF', 1470.27660000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12952, 'USD', 'SAR', 3.75000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12953, 'USD', 'SBD', 8.03190000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12954, 'USD', 'SCR', 14.56720000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12955, 'USD', 'SDG', 487.52320000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12956, 'USD', 'SEK', 9.58660000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12957, 'USD', 'SGD', 1.29220000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12958, 'USD', 'SHP', 0.75680000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12959, 'USD', 'SLE', 25.14150000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12960, 'USD', 'SLL', 25141.46410000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12961, 'USD', 'SOS', 573.91250000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12962, 'USD', 'SRD', 37.69350000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12963, 'USD', 'SSP', 4752.74630000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12964, 'USD', 'STN', 21.37300000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12965, 'USD', 'SYP', 112.78970000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12966, 'USD', 'SZL', 16.46190000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12967, 'USD', 'THB', 32.90700000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12968, 'USD', 'TJS', 9.35080000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12969, 'USD', 'TMT', 3.52360000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12970, 'USD', 'TND', 2.94490000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12971, 'USD', 'TOP', 2.38380000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12972, 'USD', 'TRY', 46.46580000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12973, 'USD', 'TTD', 6.79680000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12974, 'USD', 'TVD', 1.42760000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12975, 'USD', 'TWD', 31.69620000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12976, 'USD', 'TZS', 2620.16150000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12977, 'USD', 'UAH', 44.87180000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12978, 'USD', 'UGX', 3656.71480000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12979, 'USD', 'UYU', 40.20240000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12980, 'USD', 'UZS', 12241.13100000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12981, 'USD', 'VES', 612.43320000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12982, 'USD', 'VND', 26262.84670000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12983, 'USD', 'VUV', 118.66230000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12984, 'USD', 'WST', 2.72270000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12985, 'USD', 'XAF', 572.23620000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12986, 'USD', 'XCD', 2.70000000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12987, 'USD', 'XDR', 0.73550000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12988, 'USD', 'XOF', 572.23620000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12989, 'USD', 'XPF', 104.10150000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12990, 'USD', 'YER', 238.54370000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12991, 'USD', 'ZAR', 16.46120000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12992, 'USD', 'ZMW', 17.93850000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL),
(12993, 'USD', 'ZWL', 26.77120000, '2026-06-22', 'api', 1, '2026-06-22 00:00:15', NULL);

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

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `description`, `plan_type`, `monthly_price`, `annual_price`, `lifetime_price`, `billing_cycles_available`, `one_time_duration_months`, `currency`, `max_users`, `max_companies`, `max_storage_mb`, `max_monthly_transactions`, `max_api_calls_per_hour`, `feature_flags`, `is_active`, `is_public`, `is_custom`, `min_commitment_months`, `setup_fee`, `sort_order`, `trial_days`, `created_at`, `updated_at`) VALUES
(1, 'Accounting Only', 'For service businesses & consultants', 'subscription', 1999.00, 21999.00, 0.00, '[\"monthly\", \"annual\"]', NULL, 'PKR', 3, 1, 1024, 1000, 100, '{}', 1, 1, 0, 0, 0.00, 1, 14, '2025-11-16 21:43:23', '2026-03-30 05:45:49'),
(2, 'Retail POS', 'For shops, stores & retail businesses', 'subscription', 2999.00, 34999.00, 0.00, '[\"monthly\", \"annual\"]', NULL, 'PKR', 3, 1, 5120, 1000, 100, '{}', 1, 1, 0, 0, 0.00, 2, 7, '2025-11-16 21:43:23', '2026-03-30 05:45:49'),
(3, 'Business Pro', 'For growing businesses with teams', 'subscription', 4999.00, 54999.00, 0.00, '[\"monthly\", \"annual\"]', NULL, 'PKR', 10, 1, 20480, 1000, 100, '{}', 1, 1, 0, 0, 0.00, 3, 0, '2025-11-16 21:43:23', '2026-03-30 05:45:49'),
(4, 'Distribution', 'Explore our platform with full feature access', 'subscription', 0.00, 0.00, 0.00, '[\"monthly\", \"annual\"]', NULL, 'USD', 3, 1, 512, 1000, 100, '{}', 1, 1, 0, 0, 0.00, 0, 30, '2025-11-16 21:43:23', '2026-03-30 05:45:49'),
(5, 'Lifetime License', 'One-time payment for permanent access', 'lifetime', 0.00, 0.00, 99999.00, NULL, NULL, 'PKR', 10, 1, 51200, 10000, 1000, '{\"all_features\": true}', 1, 1, 0, 0, 0.00, 99, 0, '2026-03-30 05:45:52', NULL);

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

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `tenant_id`, `plan_id`, `previous_plan_id`, `plan_changed_at`, `billing_cycle`, `billing_type`, `auto_renew`, `start_date`, `end_date`, `grace_period_days`, `grace_period_ends_at`, `trial_ends_at`, `next_billing_date`, `last_payment_date`, `payment_failed_count`, `payment_failed_at`, `status`, `canceled_at`, `paused_at`, `resume_at`, `cancellation_reason`, `created_at`, `updated_at`) VALUES
(1, 1, 3, NULL, NULL, 'monthly', 'recurring', 1, '2025-12-02', '4030-11-19', 7, '4030-11-26', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2025-11-17 20:58:55', '2026-03-30 07:41:03'),
(2, 2, 2, NULL, NULL, 'monthly', 'recurring', 1, '2025-12-01', '2035-02-28', 7, '2035-03-07', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2025-12-06 19:12:36', '2026-03-30 05:45:51'),
(4, 4, 4, NULL, NULL, 'monthly', 'recurring', 1, '2025-12-13', '2025-12-15', 7, '2025-12-22', '2025-12-15', NULL, NULL, 0, NULL, 'trialing', NULL, NULL, NULL, NULL, '2025-12-13 00:39:48', '2026-03-30 05:45:51'),
(5, 5, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-01-07', '2400-01-07', 7, '2400-01-14', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-01-06 20:39:46', '2026-03-30 05:45:51'),
(6, 6, 3, NULL, NULL, 'annual', 'recurring', 1, '2026-01-07', '2400-01-07', 7, '2400-01-14', NULL, NULL, NULL, 0, NULL, 'trialing', NULL, NULL, NULL, NULL, '2026-01-07 14:18:41', '2026-03-30 05:45:51'),
(7, 7, 3, NULL, NULL, NULL, 'one_time', 1, '2026-01-08', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-01-07 22:42:54', '2026-04-27 09:51:17'),
(8, 8, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-01-11', '2400-01-11', 7, '2400-01-18', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-01-11 17:50:34', '2026-03-30 05:45:51'),
(9, 9, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-01-12', '2400-01-12', 7, '2400-01-19', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-01-12 11:56:26', '2026-03-30 05:45:51'),
(10, 10, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-01-13', '2400-01-13', 7, '2400-01-20', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-01-13 18:41:23', '2026-03-30 05:45:51'),
(11, 11, 4, NULL, NULL, 'monthly', 'recurring', 1, '2026-01-15', '2026-01-16', 7, '2026-01-23', '2026-01-16', NULL, NULL, 0, NULL, 'trialing', NULL, NULL, NULL, NULL, '2026-01-15 07:13:51', '2026-03-30 05:45:51'),
(12, 12, 4, NULL, NULL, 'monthly', 'recurring', 1, '2026-01-15', '2026-01-16', 7, '2026-01-23', '2026-01-16', NULL, NULL, 0, NULL, 'trialing', NULL, NULL, NULL, NULL, '2026-01-15 07:52:06', '2026-03-30 05:45:51'),
(13, 13, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-01-23', '2400-01-23', 7, '2400-01-30', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-01-23 13:58:09', '2026-03-30 05:45:51'),
(14, 14, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-01-25', '2400-01-25', 7, '2400-02-01', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-01-24 19:28:35', '2026-03-30 05:45:51'),
(15, 31, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-01-31', '2400-01-31', 7, '2400-02-07', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-01-31 14:36:01', '2026-03-30 05:45:51'),
(16, 36, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-02-01', '2026-02-07', 7, '2026-02-14', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-01-31 23:57:32', '2026-03-30 05:45:51'),
(17, 37, 4, NULL, NULL, 'monthly', 'recurring', 1, '2026-02-01', '2400-02-01', 7, '2400-02-08', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-02-01 01:54:17', '2026-03-30 05:45:51'),
(18, 38, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-02-06', '2400-02-06', 7, '2400-02-13', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-02-06 18:43:49', '2026-03-30 05:45:51'),
(20, 40, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-02-16', '2400-02-16', 7, '2400-02-23', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-02-16 11:02:37', '2026-03-30 05:45:51'),
(21, 41, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-02-16', '2400-02-16', 7, '2400-02-23', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-02-16 12:23:44', '2026-03-30 05:45:51'),
(22, 42, 1, NULL, NULL, 'monthly', 'recurring', 1, '2026-03-07', '2026-04-07', 7, '2026-03-13', '2026-03-06', '2026-04-07', NULL, 0, NULL, 'trialing', NULL, NULL, NULL, NULL, '2026-02-28 15:59:50', '2026-03-30 07:41:05'),
(23, 43, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-03-13', '2400-03-13', 7, '2400-03-20', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-03-13 01:11:35', '2026-03-30 05:45:51'),
(24, 44, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-03-27', '2400-03-27', 7, '2400-04-03', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-03-26 20:35:30', '2026-03-30 05:45:51'),
(25, 45, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-03-27', '2400-03-27', 7, '2400-04-03', NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-03-27 09:46:14', '2026-03-30 05:45:51'),
(26, 46, 3, NULL, NULL, NULL, 'one_time', 1, '2026-04-02', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'trialing', NULL, NULL, NULL, NULL, '2026-04-02 11:16:23', NULL),
(27, 47, 3, NULL, NULL, 'monthly', 'recurring', 1, '2026-04-13', '2026-04-23', 0, '2026-04-23', NULL, NULL, NULL, 0, NULL, 'expired', NULL, NULL, NULL, NULL, '2026-04-13 10:03:46', '2026-04-24 12:49:29'),
(28, 48, 3, NULL, NULL, NULL, 'one_time', 1, '2026-05-13', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-05-12 21:58:18', NULL),
(29, 49, 3, NULL, NULL, NULL, 'one_time', 1, '2026-05-25', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-05-25 18:32:11', NULL),
(30, 50, 3, NULL, NULL, NULL, 'one_time', 1, '2026-05-31', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-05-31 08:47:05', NULL),
(31, 51, 3, NULL, NULL, NULL, 'one_time', 0, '2026-06-19', '2026-06-23', 7, NULL, '2026-06-23', NULL, NULL, 0, NULL, 'trialing', NULL, NULL, NULL, NULL, '2026-06-19 17:39:36', NULL),
(32, 52, 3, NULL, NULL, NULL, 'one_time', 1, '2026-06-22', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-06-22 00:21:53', NULL),
(33, 53, 3, NULL, NULL, NULL, 'one_time', 1, '2026-06-22', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-06-22 12:30:53', NULL),
(34, 54, 3, NULL, NULL, NULL, 'one_time', 1, '2026-06-28', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-06-28 11:18:06', NULL),
(35, 55, 3, NULL, NULL, NULL, 'one_time', 1, '2026-07-05', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-07-05 00:58:54', NULL),
(36, 56, 3, NULL, NULL, NULL, 'one_time', 1, '2026-07-21', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-07-21 15:26:24', NULL),
(37, 57, 3, NULL, NULL, NULL, 'one_time', 1, '2026-07-24', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-07-24 15:24:45', NULL),
(38, 58, 3, NULL, NULL, NULL, 'one_time', 1, '2026-08-12', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-08-12 06:42:01', NULL),
(39, 59, 3, NULL, NULL, NULL, 'one_time', 1, '2026-08-12', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'trialing', NULL, NULL, NULL, NULL, '2026-08-12 08:27:07', NULL),
(40, 60, 3, NULL, NULL, NULL, 'one_time', 1, '2026-08-17', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-08-17 19:00:11', NULL),
(41, 63, 3, NULL, NULL, NULL, 'one_time', 1, '2026-08-23', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-08-23 13:50:46', NULL),
(42, 64, 3, NULL, NULL, NULL, 'one_time', 1, '2026-08-26', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-08-26 18:56:39', NULL),
(43, 65, 3, NULL, NULL, NULL, 'one_time', 1, '2026-09-01', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-08-31 20:30:25', NULL),
(44, 66, 3, NULL, NULL, NULL, 'one_time', 1, '2026-09-09', NULL, 7, NULL, NULL, NULL, NULL, 0, NULL, 'active', NULL, NULL, NULL, NULL, '2026-09-09 13:28:01', NULL);

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
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `business_name`, `logo_url`, `contact_email`, `contact_phone`, `country`, `state`, `city`, `postal_code`, `address_line1`, `address_line2`, `status`, `is_suspended`, `suspended_reason`, `suspended_at`, `timezone`, `base_currency`, `language`, `created_at`, `updated_at`, `activated_at`, `trial_ends_at`, `deleted_at`) VALUES
(0, 'System Tenant', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'GMT', 'PKR', 'en', '2025-12-11 17:01:23', '2025-12-11 17:25:13', NULL, NULL, NULL),
(1, 'GB Filling Station', NULL, 'anayathussain@gmail.com', '03127777554', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'GMT', 'PKR', 'en', '2025-11-16 21:45:31', '2026-03-30 07:41:04', NULL, NULL, NULL),
(2, 'Shell Pakistan Limited', NULL, 'generalpublicenquiries-pk@wafi-energy.com', '+92 (21) 111-888-222', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'GMT', 'PKR', 'en', '2025-12-06 19:10:38', '2025-12-13 12:24:50', NULL, NULL, NULL),
(4, 'Demo001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'GMT', 'PKR', 'en', '2025-12-13 00:37:57', '2025-12-13 12:25:04', NULL, NULL, NULL),
(5, 'Faisal Group', NULL, 'mhaji2681@gmail.com', '03353167686', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'GMT', 'PKR', 'en', '2026-01-06 20:37:02', '2026-01-07 14:39:47', NULL, NULL, NULL),
(6, 'CENTRAL SALES CORPORATION', NULL, 'adilghouri@gmail.com', '0213-4551883', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-07 14:11:02', '2026-01-07 14:39:53', NULL, NULL, NULL),
(7, 'Mama Communication', NULL, 'kamilhussain340@gmail.com', '05811550736', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-07 22:41:27', NULL, NULL, NULL, NULL),
(8, 'Insta Group Private Limited', NULL, 'admin@instagroup.com.pk', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-11 17:18:39', NULL, NULL, NULL, NULL),
(9, 'Manahil Traders', NULL, NULL, '03109936166', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-12 11:53:11', NULL, NULL, NULL, NULL),
(10, 'Ameet Kumar Traders', NULL, NULL, '03337360580', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-13 16:40:13', NULL, NULL, NULL, NULL),
(11, 'MikSol', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-15 07:10:00', NULL, NULL, '2026-01-16 15:00:00', NULL),
(12, 'Master Kiryana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-15 07:45:27', NULL, NULL, '2026-01-16 15:00:00', NULL),
(13, 'JAFAR CLOTH HOUSE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-22 23:48:57', NULL, NULL, NULL, NULL),
(14, 'Ahsan Adil General Store', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-24 18:20:52', NULL, NULL, NULL, NULL),
(27, 'Test ABC', NULL, 'biphsycho1@gmail.com', '+923468918711', 'PK', 'Sindh', 'Karachi', '74330', 'Saadi Town Block 1', '', 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-30 20:25:10', NULL, NULL, NULL, NULL),
(28, 'Test ABC1', NULL, 'biphsycho@gmail.com', '+923353789981', 'PK', 'Sindh', 'Karachi', '74330', 'Saadi Town Block 1', '', 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-30 22:20:23', NULL, NULL, NULL, NULL),
(29, 'Test ABC2', NULL, 'zahidsumaira03@gmail.com', '+9236554454445', 'PK', 'Sindh', 'Karachi', '74330', 'Saadi Town Block 1', '', 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-31 11:56:35', NULL, NULL, NULL, NULL),
(30, 'Test ABC3', NULL, 'innova.tech213@gmail.com', '+92545844684864', 'PK', 'Sindh', 'Karachi', '74330', 'Saadi Town Block 1', '', 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-31 12:47:34', NULL, NULL, NULL, NULL),
(31, 'Super Badri', NULL, 'badri@gmail.com', '+923003353277', 'PK', 'Sindh', 'Bhiria City', '-', '-', '', 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-31 14:34:25', NULL, NULL, NULL, NULL),
(32, 'Test ABC4', NULL, 'ghori@gmail.com', '+92518464689748', 'PK', 'Sindh', 'Karachi', '74330', 'Saadi Town Block 1', '', 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-31 15:27:18', NULL, NULL, NULL, NULL),
(33, 'Test ABC5', NULL, 'name@company.com', '+9265164865146', 'PK', 'Sindh', 'Karachi', '74330', 'Saadi Town Block 1', '', 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-31 16:40:57', NULL, NULL, NULL, NULL),
(34, 'Test ABC6', NULL, 'superbadrifoods@gmail.com', '+923353789981', 'PK', 'Sindh', 'Karachi', '74330', 'Saadi Town Block 1', '', 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-31 16:42:52', NULL, NULL, NULL, NULL),
(35, 'Test ABC8', NULL, 'jj@gmail.com', '+924544354565', 'PK', 'Sindh', 'Karachi', '74330', 'Saadi Town Block 1', '', 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-31 17:05:21', NULL, NULL, NULL, NULL),
(36, 'Paksuite Tech', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-01-31 23:29:06', NULL, NULL, NULL, NULL),
(37, 'IMG Marketing', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-02-01 01:53:21', NULL, NULL, NULL, NULL),
(38, ' S.R Builders & Developers', NULL, 'srbuildersdevelopers07@gmail.com', '03182921196', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-02-06 18:39:45', NULL, NULL, NULL, NULL),
(39, 'Rahman Traders', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-02-09 12:18:28', NULL, NULL, NULL, NULL),
(40, 'Z&S Foods', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-02-16 10:45:59', NULL, NULL, NULL, NULL),
(41, 'Final Face', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-02-16 12:06:06', NULL, NULL, NULL, NULL),
(42, 'UNISEN SYSTEMS', NULL, 'demo02@gmail.com', '+923468918711', 'PK', 'Sindh', 'Karachi', '74330', 'Saadi Town Block 1', '', 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-02-28 15:56:54', NULL, NULL, NULL, NULL),
(43, 'Rahman Traders', NULL, NULL, '03454653972', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-03-13 00:58:42', NULL, NULL, NULL, NULL),
(44, 'Heaven Enterprises', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-03-26 20:05:04', NULL, NULL, NULL, NULL),
(45, 'Sunshine Bakers', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-03-27 09:40:10', NULL, NULL, NULL, NULL),
(46, 'Yaseen Jazz Cash Point', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-04-02 10:53:49', NULL, NULL, NULL, NULL),
(47, 'Super Royal Bakery', NULL, 'royalsuper753@gmail.com', '03451290745', 'PK', 'Sindh', 'Karachi', NULL, 'BLOCK 9 BEHIND URDU UNIVERSITY GULSHAN IQBAL KARACHI. \n', NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-04-13 10:02:44', '2026-04-13 10:02:54', NULL, NULL, NULL),
(48, 'Lasers Engravers', NULL, 'fabrication@lasersengraverspvtltd.pk', '03312551094', NULL, 'Sindh', 'Karachi', NULL, 'Plot SA 06 street 18b sector 33/C Korangi', NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-05-12 21:57:08', NULL, NULL, NULL, NULL),
(49, 'ST Traders', NULL, 'benlingbtk@gmail.com', '03218777941', 'PK', 'Sindh', 'Karachi', NULL, 'UBN Trader Tower Main Jinnah Avenue', NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-05-25 18:28:30', '2026-05-25 18:49:50', NULL, NULL, NULL),
(50, 'Qasim Traders', NULL, 'qasim.fayyaz@hotmail.com', '03334243992', 'PK', 'Punjab', 'Lahore', NULL, '5 Chamberlane Road', NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-05-31 08:44:19', '2026-05-31 09:44:27', NULL, NULL, NULL),
(51, 'Demo002', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-06-19 17:37:27', '2026-06-19 19:30:11', NULL, NULL, NULL),
(52, 'Apna Enterprises', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-06-22 00:20:09', '2026-06-22 00:20:29', NULL, NULL, NULL),
(53, 'Al Rehal Aluminium and glass works\n', NULL, 'rehalaluminium.ae@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-06-22 12:30:14', NULL, NULL, NULL, NULL),
(54, 'RODI TRADING L.L.C', NULL, 'ahmedullah364@gmail.com', '0315 8346645', 'PK', 'Balochistan', 'Quetta', NULL, 'Office No:B13, 2nd floor swiss plaza Jinnah Road quetta.', NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-06-28 11:16:09', NULL, NULL, NULL, NULL),
(55, 'NIAZ MOHD METAL SCRAPS TR CO LLC.', NULL, 'niazmetal@gmail.com', '+971-501100583', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-07-04 14:14:12', NULL, NULL, NULL, NULL),
(56, 'Comfort Int', NULL, 'muh1610@gmail.com', '03216777828', 'PK', 'Punjab', 'Lahore', NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-07-21 15:23:40', NULL, NULL, NULL, NULL),
(57, 'Al Aziz Traders', NULL, 'alaziztrader110@gmail.com', '03051244737', NULL, NULL, NULL, NULL, NULL, NULL, 'suspended', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-07-24 15:23:24', '2026-07-25 13:37:59', NULL, NULL, NULL),
(58, 'Asad Traders', NULL, 'asadtraders.160@gmail.com', '0300-0280160', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-08-11 10:47:17', NULL, NULL, NULL, NULL),
(59, 'GH WholeSaler', NULL, 'ghwholesaler786@gmail.com', '0303-3070724', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-08-12 08:26:31', NULL, NULL, NULL, NULL),
(60, 'Unisen Systems', NULL, 'usman@unisensystems.com', '', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-08-17 18:59:30', NULL, NULL, NULL, NULL),
(63, 'Rehman Enterprises', NULL, 'asaxme@gmail.com', ' +92-3086022055', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-08-23 13:49:52', NULL, NULL, NULL, NULL),
(64, 'Multiaxis Engineering', NULL, 'multiaxisengg@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-08-26 18:44:18', NULL, NULL, NULL, NULL),
(65, 'Khattak Traders', NULL, 'khattakfabrics222@gmail.com', '03137212229', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-08-31 20:29:42', NULL, NULL, NULL, NULL),
(66, 'ISMAIL TRADERS', NULL, 'mismailtraders786@gmail.com', '0301-3224590', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, NULL, NULL, 'UTC', 'USD', 'en', '2026-09-09 13:27:18', NULL, NULL, NULL, NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `currency_exchange_rates`
--
ALTER TABLE `currency_exchange_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12994;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

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
