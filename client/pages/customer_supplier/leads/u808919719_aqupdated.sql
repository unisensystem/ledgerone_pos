-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 08, 2025 at 03:41 PM
-- Server version: 10.11.10-MariaDB
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u808919719_aqupdated`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`u808919719_aqupdated`@`127.0.0.1` PROCEDURE `trg_purchase_invoice_stock_insert_logic` (IN `p_invoice_id` INT, IN `p_warehouse_id` INT, IN `p_date` DATE, IN `p_bill_no` INT)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id INT;
    DECLARE v_quantity DECIMAL(15,3);
    DECLARE v_unit_cost DECIMAL(15,3);
    DECLARE v_warehouse_id INT;
    DECLARE v_prev_balance DECIMAL(15,3);
    DECLARE v_inventory_account_id INT;
    
    DECLARE item_cursor CURSOR FOR
        SELECT pii.product_id, 
               (COALESCE(pii.ctn, 0) * COALESCE(pii.packing, 1) + COALESCE(pii.doz, 0) * 12 + COALESCE(pii.pcs, 0)) as qty,
               COALESCE(pii.pprice, 0) as cost,
               COALESCE(p_warehouse_id, 0) as warehouse,
               COALESCE(p.inventory_account_id, 32) as inv_account
        FROM purchase_item_invoice pii
        JOIN products p ON pii.product_id = p.id
        WHERE pii.invoice_id = p_invoice_id
        AND (COALESCE(pii.ctn, 0) * COALESCE(pii.packing, 1) + COALESCE(pii.doz, 0) * 12 + COALESCE(pii.pcs, 0)) > 0;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN item_cursor;
    
    item_loop: LOOP
        FETCH item_cursor INTO v_product_id, v_quantity, v_unit_cost, v_warehouse_id, v_inventory_account_id;
        IF done THEN
            LEAVE item_loop;
        END IF;
        
        SELECT COALESCE(balance_qty, 0) INTO v_prev_balance
        FROM stock_ledger 
        WHERE product_id = v_product_id AND warehouse_id = v_warehouse_id
        ORDER BY id DESC LIMIT 1;
        
        INSERT INTO stock_ledger (
            transaction_type, reference_table, reference_id, product_id, 
            warehouse_id, account_id, qty_in, balance_qty, unit_cost, total_cost, transaction_date
        ) VALUES (
            'Purchase Invoice', 'purchase_invoice', p_invoice_id, v_product_id,
            v_warehouse_id, (SELECT COALESCE(inventory_account_id, 32) FROM products WHERE id = v_product_id), v_quantity, (v_prev_balance + v_quantity), v_unit_cost,
            (v_quantity * v_unit_cost), p_date
        );
        
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Purchase Invoice', 'purchase_invoice', p_invoice_id, v_inventory_account_id,
            p_date,
            CONCAT('Purchase - Product ID: ', v_product_id, ' - Invoice: ', p_bill_no),
            (v_quantity * v_unit_cost), 0
        );
        
    END LOOP;
    
    CLOSE item_cursor;
END$$

CREATE DEFINER=`u808919719_aqupdated`@`127.0.0.1` PROCEDURE `trg_purchase_return_stock_insert_logic` (IN `p_invoice_id` INT, IN `p_warehouse_id` INT, IN `p_date` DATE, IN `p_bill_no` INT)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id INT;
    DECLARE v_quantity DECIMAL(15,3);
    DECLARE v_unit_cost DECIMAL(15,3);
    DECLARE v_warehouse_id INT;
    DECLARE v_prev_balance DECIMAL(15,3);
    DECLARE v_inventory_account_id INT;
    
    DECLARE item_cursor CURSOR FOR
        SELECT piri.product_id, 
               (COALESCE(piri.ctn, 0) * COALESCE(piri.packing, 1) + COALESCE(piri.doz, 0) * 12 + COALESCE(piri.pcs, 0)) as qty,
               COALESCE(piri.pprice, 0) as cost,
               COALESCE(p_warehouse_id, 1) as warehouse,
               COALESCE(p.inventory_account_id, 32) as inv_account
        FROM purchase_invoice_return_items piri
        JOIN products p ON piri.product_id = p.id
        WHERE piri.invoice_id = p_invoice_id
        AND (COALESCE(piri.ctn, 0) * COALESCE(piri.packing, 1) + COALESCE(piri.doz, 0) * 12 + COALESCE(piri.pcs, 0)) > 0
        AND p.inventory_account_id IN (32, 33, 34, 36, 37);
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN item_cursor;
    
    item_loop: LOOP
        FETCH item_cursor INTO v_product_id, v_quantity, v_unit_cost, v_warehouse_id, v_inventory_account_id;
        IF done THEN
            LEAVE item_loop;
        END IF;
        
        SELECT COALESCE(balance_qty, 0) INTO v_prev_balance
        FROM stock_ledger 
        WHERE product_id = v_product_id AND warehouse_id = v_warehouse_id
        ORDER BY id DESC LIMIT 1;
        
        INSERT INTO stock_ledger (
            transaction_type, reference_table, reference_id, product_id, 
            warehouse_id, qty_out, balance_qty, unit_cost, total_cost, transaction_date
        ) VALUES (
            'Purchase Return', 'purchase_invoice_return', p_invoice_id, v_product_id,
            v_warehouse_id, v_quantity, (v_prev_balance - v_quantity), v_unit_cost,
            (v_quantity * v_unit_cost), p_date
        );
        
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Purchase Return', 'purchase_invoice_return', p_invoice_id, v_inventory_account_id,
            p_date,
            CONCAT('Purchase Return - Product ID: ', v_product_id, ' - Return: ', p_bill_no),
            0, (v_quantity * v_unit_cost)
        );
        
    END LOOP;
    
    CLOSE item_cursor;
END$$

CREATE DEFINER=`u808919719_aqupdated`@`127.0.0.1` PROCEDURE `trg_sale_invoice_stock_insert_logic` (IN `p_invoice_id` INT, IN `p_warehouse_id` INT, IN `p_date` DATE, IN `p_bill_no` INT)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id INT;
    DECLARE v_quantity DECIMAL(15,3);
    DECLARE v_unit_cost DECIMAL(15,3);
    DECLARE v_warehouse_id INT;
    DECLARE v_prev_balance DECIMAL(15,3);
    DECLARE v_inventory_account_id INT;
    
    DECLARE item_cursor CURSOR FOR
        SELECT ii.product_id, 
               (COALESCE(ii.ctn, 0) * COALESCE(ii.packing, 1) + COALESCE(ii.doz, 0) * 12 + COALESCE(ii.pcs, 0)) as qty,
               COALESCE(ii.tp, 0) as cost,
               COALESCE(p_warehouse_id, 1) as warehouse,
               COALESCE(p.inventory_account_id, 32) as inv_account
        FROM invoice_items ii
        JOIN products p ON ii.product_id = p.id
        WHERE ii.invoice_id = p_invoice_id
        AND (COALESCE(ii.ctn, 0) * COALESCE(ii.packing, 1) + COALESCE(ii.doz, 0) * 12 + COALESCE(ii.pcs, 0)) > 0
        AND p.inventory_account_id IN (32, 33, 34, 36, 37);
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN item_cursor;
    
    item_loop: LOOP
        FETCH item_cursor INTO v_product_id, v_quantity, v_unit_cost, v_warehouse_id, v_inventory_account_id;
        IF done THEN
            LEAVE item_loop;
        END IF;
        
        SELECT COALESCE(balance_qty, 0) INTO v_prev_balance
        FROM stock_ledger 
        WHERE product_id = v_product_id AND warehouse_id = v_warehouse_id
        ORDER BY id DESC LIMIT 1;
        
        INSERT INTO stock_ledger (
            transaction_type, reference_table, reference_id, product_id, 
            warehouse_id, account_id, qty_out, balance_qty, unit_cost, total_cost, transaction_date
        ) VALUES (
            'Sales Invoice', 'sale_invoice', p_invoice_id, v_product_id,
            v_warehouse_id, v_inventory_account_id, v_quantity, (v_prev_balance - v_quantity), v_unit_cost,
            (v_quantity * v_unit_cost), p_date
        );
        
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sales Invoice', 'sale_invoice', p_invoice_id, v_inventory_account_id,
            p_date,
            CONCAT('Sale - Product ID: ', v_product_id, ' - Invoice: ', p_bill_no),
            0, (v_quantity * v_unit_cost)
        );
        
    END LOOP;
    
    CLOSE item_cursor;
END$$

CREATE DEFINER=`u808919719_aqupdated`@`127.0.0.1` PROCEDURE `trg_sale_return_stock_insert_logic` (IN `p_invoice_id` INT, IN `p_warehouse_id` INT, IN `p_date` DATE, IN `p_bill_no` INT)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id INT;
    DECLARE v_quantity DECIMAL(15,3);
    DECLARE v_unit_cost DECIMAL(15,3);
    DECLARE v_warehouse_id INT;
    DECLARE v_prev_balance DECIMAL(15,3);
    DECLARE v_inventory_account_id INT;
    
    DECLARE item_cursor CURSOR FOR
        SELECT srii.product_id, 
               (COALESCE(srii.ctn, 0) * COALESCE(srii.packing, 1) + COALESCE(srii.doz, 0) * 12 + COALESCE(srii.pcs, 0)) as qty,
               COALESCE(srii.tp, 0) as cost,
               COALESCE(p_warehouse_id, 1) as warehouse,
               COALESCE(p.inventory_account_id, 32) as inv_account
        FROM salereturn_invoice_items srii
        JOIN products p ON srii.product_id = p.id
        WHERE srii.invoice_id = p_invoice_id
        AND (COALESCE(srii.ctn, 0) * COALESCE(srii.packing, 1) + COALESCE(srii.doz, 0) * 12 + COALESCE(srii.pcs, 0)) > 0
        AND p.inventory_account_id IN (32, 33, 34, 36, 37);
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN item_cursor;
    
    item_loop: LOOP
        FETCH item_cursor INTO v_product_id, v_quantity, v_unit_cost, v_warehouse_id, v_inventory_account_id;
        IF done THEN
            LEAVE item_loop;
        END IF;
        
        SELECT COALESCE(balance_qty, 0) INTO v_prev_balance
        FROM stock_ledger 
        WHERE product_id = v_product_id AND warehouse_id = v_warehouse_id
        ORDER BY id DESC LIMIT 1;
        
        INSERT INTO stock_ledger (
            transaction_type, reference_table, reference_id, product_id, 
            warehouse_id, qty_in, balance_qty, unit_cost, total_cost, transaction_date
        ) VALUES (
            'Sales Return', 'salereturn_invoice', p_invoice_id, v_product_id,
            v_warehouse_id, v_quantity, (v_prev_balance + v_quantity), v_unit_cost,
            (v_quantity * v_unit_cost), p_date
        );
        
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sales Return', 'salereturn_invoice', p_invoice_id, v_inventory_account_id,
            p_date,
            CONCAT('Sales Return - Product ID: ', v_product_id, ' - Return: ', p_bill_no),
            (v_quantity * v_unit_cost), 0
        );
        
    END LOOP;
    
    CLOSE item_cursor;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `accounting_ledger`
--

CREATE TABLE `accounting_ledger` (
  `id` int(11) NOT NULL,
  `transaction_type` varchar(50) NOT NULL,
  `reference_table` varchar(100) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `production_order_id` int(11) DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `description` text DEFAULT NULL,
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accounting_ledger`
--

INSERT INTO `accounting_ledger` (`id`, `transaction_type`, `reference_table`, `reference_id`, `production_order_id`, `account_id`, `date`, `description`, `debit`, `credit`, `created_at`) VALUES
(2125, 'Purchase Invoice', 'purchase_invoice', 20, NULL, 19, '2025-08-05', 'Purchase Invoice - 1 - Olivia', 9320.00, 0.00, '2025-08-05 14:11:09'),
(2126, 'Purchase Invoice', 'purchase_invoice', 20, NULL, 19, '2025-08-05', 'Purchase Invoice - 1 - Olivia', 0.00, 9320.00, '2025-08-05 14:11:09'),
(2127, 'Purchase Invoice', 'purchase_invoice', 21, NULL, 19, '2025-08-05', 'Purchase Invoice - 2 - Olivia', 5596.25, 0.00, '2025-08-05 15:38:39'),
(2128, 'Purchase Invoice', 'purchase_invoice', 21, NULL, 19, '2025-08-05', 'Purchase Invoice - 2 - Olivia', 0.00, 5596.25, '2025-08-05 15:38:39'),
(2145, 'Receive Voucher', 'receive_voucher', 1, NULL, 5, '2025-08-05', 'Payment received - RV-250805-001', 500.00, 0.00, '2025-08-05 19:50:02'),
(2146, 'Receive Voucher', 'receive_voucher', 1, NULL, 2, '2025-08-05', 'Payment received - RV-250805-001', 0.00, 500.00, '2025-08-05 19:50:02'),
(2147, 'Receive Voucher', 'receive_voucher', 2, NULL, 7, '2025-08-05', 'Payment received - RV-250805-002', 1800.00, 0.00, '2025-08-05 19:53:16'),
(2148, 'Receive Voucher', 'receive_voucher', 2, NULL, 2, '2025-08-05', 'Payment received - RV-250805-002', 0.00, 1800.00, '2025-08-05 19:53:16'),
(2149, 'Receive Voucher', 'receive_voucher', 3, NULL, 7, '2025-08-05', 'Payment received - RV-250805-003', 1500.00, 0.00, '2025-08-05 19:54:49'),
(2150, 'Receive Voucher', 'receive_voucher', 3, NULL, 2, '2025-08-05', 'Payment received - RV-250805-003', 0.00, 1500.00, '2025-08-05 19:54:49'),
(2151, 'Receive Voucher', 'receive_voucher', 4, NULL, 7, '2025-08-05', 'Payment received - RV-250805-004', 4100.00, 0.00, '2025-08-05 19:56:41'),
(2152, 'Receive Voucher', 'receive_voucher', 4, NULL, 2, '2025-08-05', 'Payment received - RV-250805-004', 0.00, 4100.00, '2025-08-05 19:56:41'),
(2153, 'Receive Voucher', 'receive_voucher', 5, NULL, 7, '2025-08-05', 'Payment received - RV-250805-005', 600.00, 0.00, '2025-08-05 19:57:01'),
(2154, 'Receive Voucher', 'receive_voucher', 5, NULL, 2, '2025-08-05', 'Payment received - RV-250805-005', 0.00, 600.00, '2025-08-05 19:57:01'),
(2171, 'Sale Invoice', 'sale_invoice', 9, NULL, 2, '2025-08-06', 'Sale Invoice #0 - InnovaTech', 56640.00, 0.00, '2025-08-06 07:49:36'),
(2172, 'Sale Invoice', 'sale_invoice', 9, NULL, 9, '2025-08-06', 'Sale Invoice #0 - InnovaTech', 0.00, 56640.00, '2025-08-06 07:49:36'),
(2173, 'Sale Invoice', 'sale_invoice', 10, NULL, 2, '2025-08-06', 'Sale Invoice #0 - InnovaTech', 56640.00, 0.00, '2025-08-06 07:49:41'),
(2174, 'Sale Invoice', 'sale_invoice', 10, NULL, 9, '2025-08-06', 'Sale Invoice #0 - InnovaTech', 0.00, 56640.00, '2025-08-06 07:49:41'),
(2183, 'Opening Balance', 'balance_invoice_entries', 406, NULL, 2, '2025-08-01', 'Opening Balance - Inv: 222', 4000.00, 0.00, '2025-08-15 03:40:02'),
(2184, 'Opening Balance', 'balance_invoice_entries', 406, NULL, 1, '2025-08-01', 'Opening Balance - Inv: 222', 0.00, 4000.00, '2025-08-15 03:40:02'),
(2185, 'Sale Invoice', 'sale_invoice', 11, NULL, 2, '2025-08-15', 'Sale Invoice #1 - Asif G/s', 12000.00, 0.00, '2025-08-15 11:01:02'),
(2186, 'Sale Invoice', 'sale_invoice', 11, NULL, 9, '2025-08-15', 'Sale Invoice #1 - Asif G/s', 0.00, 12000.00, '2025-08-15 11:01:02'),
(2187, 'Vendor Opening Balance', 'vendors', 60, NULL, 1, '0000-00-00', 'Opening Liability - Rasid &amp; Sons', 12000.00, 0.00, '2025-08-15 11:05:56'),
(2188, 'Vendor Opening Balance', 'vendors', 60, NULL, 14, '0000-00-00', 'Opening Liability - Rasid &amp; Sons', 0.00, 12000.00, '2025-08-15 11:05:56'),
(2189, 'Purchase Invoice', 'purchase_invoice', 22, NULL, 15, '2025-08-15', 'Purchase Invoice - 3 - Rasid &amp; Sons', 4513.50, 0.00, '2025-08-15 11:44:00'),
(2190, 'Purchase Invoice', 'purchase_invoice', 22, NULL, 60, '2025-08-15', 'Purchase Invoice - 3 - Rasid &amp; Sons', 0.00, 4513.50, '2025-08-15 11:44:00'),
(2191, 'Vendor Opening Balance', 'vendors', 63, NULL, 24, '0000-00-00', 'Opening Advance - Aziz Ullah', 501001.00, 0.00, '2025-08-18 08:23:29'),
(2192, 'Vendor Opening Balance', 'vendors', 63, NULL, 1, '0000-00-00', 'Opening Advance - Aziz Ullah', 0.00, 501001.00, '2025-08-18 08:23:29'),
(2203, 'Expense Voucher', 'expense_voucher_lines', 1, NULL, 15, '2025-08-19', 'EV-EV-2025-0001 Line 1', 100.00, 0.00, '2025-08-19 07:26:27'),
(2204, 'Expense Voucher', 'expense_voucher_lines', 1, NULL, 7, '2025-08-19', 'EV-EV-2025-0001 Line 1', 0.00, 100.00, '2025-08-19 07:26:27'),
(2205, 'Sale Invoice', 'sale_invoice', 12, NULL, 2, '2025-07-25', 'Sale Invoice #0 - Wasim G/S', 14308.00, 0.00, '2025-08-19 22:01:03'),
(2206, 'Sale Invoice', 'sale_invoice', 12, NULL, 9, '2025-07-25', 'Sale Invoice #0 - Wasim G/S', 0.00, 14600.00, '2025-08-19 22:01:03'),
(2207, 'Sale Invoice', 'sale_invoice', 12, NULL, 16, '2025-07-25', 'Discount on Invoice #0 - Wasim G/S', 292.00, 0.00, '2025-08-19 22:01:03'),
(2208, 'Sale Invoice', 'sale_invoice', 13, NULL, 2, '2025-07-25', 'Sale Invoice #0 - Wasim G/S', 14308.00, 0.00, '2025-08-19 22:06:32'),
(2209, 'Sale Invoice', 'sale_invoice', 13, NULL, 9, '2025-07-25', 'Sale Invoice #0 - Wasim G/S', 0.00, 14600.00, '2025-08-19 22:06:32'),
(2210, 'Sale Invoice', 'sale_invoice', 13, NULL, 16, '2025-07-25', 'Discount on Invoice #0 - Wasim G/S', 292.00, 0.00, '2025-08-19 22:06:32'),
(2211, 'Sale Invoice', 'sale_invoice', 14, NULL, 2, '2025-07-25', 'Sale Invoice #0 - Wasim G/S', 14308.00, 0.00, '2025-08-19 22:10:19'),
(2212, 'Sale Invoice', 'sale_invoice', 14, NULL, 9, '2025-07-25', 'Sale Invoice #0 - Wasim G/S', 0.00, 14600.00, '2025-08-19 22:10:19'),
(2213, 'Sale Invoice', 'sale_invoice', 14, NULL, 16, '2025-07-25', 'Discount on Invoice #0 - Wasim G/S', 292.00, 0.00, '2025-08-19 22:10:19'),
(2214, 'Sale Invoice', 'sale_invoice', 15, NULL, 2, '2025-07-25', 'Sale Invoice #0 - Wasim G/S', 14308.00, 0.00, '2025-08-19 22:12:38'),
(2215, 'Sale Invoice', 'sale_invoice', 15, NULL, 9, '2025-07-25', 'Sale Invoice #0 - Wasim G/S', 0.00, 14600.00, '2025-08-19 22:12:38'),
(2216, 'Sale Invoice', 'sale_invoice', 15, NULL, 16, '2025-07-25', 'Discount on Invoice #0 - Wasim G/S', 292.00, 0.00, '2025-08-19 22:12:38'),
(2219, 'Sale Invoice', 'sale_invoice', 17, NULL, 2, '2025-08-20', 'Sale Invoice #2 - Abdul Qudus Bhai', 90000.00, 0.00, '2025-08-20 07:28:31'),
(2220, 'Sale Invoice', 'sale_invoice', 17, NULL, 9, '2025-08-20', 'Sale Invoice #2 - Abdul Qudus Bhai', 0.00, 90000.00, '2025-08-20 07:28:31'),
(2221, 'Sale Invoice', 'sale_invoice', 18, NULL, 2, '2025-08-20', 'Sale Invoice #3 - InnovaTech', 10000.00, 0.00, '2025-08-20 07:49:40'),
(2222, 'Sale Invoice', 'sale_invoice', 18, NULL, 9, '2025-08-20', 'Sale Invoice #3 - InnovaTech', 0.00, 10000.00, '2025-08-20 07:49:40'),
(2225, 'Purchase Invoice', 'purchase_invoice', 24, NULL, 15, '2025-08-20', 'Purchase Invoice - 4 - HAJI SHAHNAWAZ MEMON', 40000.00, 0.00, '2025-08-20 07:58:39'),
(2226, 'Purchase Invoice', 'purchase_invoice', 24, NULL, 62, '2025-08-20', 'Purchase Invoice - 4 - HAJI SHAHNAWAZ MEMON', 0.00, 40000.00, '2025-08-20 07:58:39'),
(2227, 'Purchase Invoice', 'purchase_invoice', 25, NULL, 15, '2025-08-20', 'Purchase Invoice - 5 - HAJI SHAHNAWAZ MEMON', 4000.00, 0.00, '2025-08-20 08:01:17'),
(2228, 'Purchase Invoice', 'purchase_invoice', 25, NULL, 62, '2025-08-20', 'Purchase Invoice - 5 - HAJI SHAHNAWAZ MEMON', 0.00, 4000.00, '2025-08-20 08:01:17'),
(2229, 'Purchase Invoice', 'purchase_invoice', 26, NULL, 15, '2025-08-20', 'Purchase Invoice - 6 - HAJI SHAHNAWAZ MEMON', 444.00, 0.00, '2025-08-20 08:02:17'),
(2230, 'Purchase Invoice', 'purchase_invoice', 26, NULL, 62, '2025-08-20', 'Purchase Invoice - 6 - HAJI SHAHNAWAZ MEMON', 0.00, 444.00, '2025-08-20 08:02:17'),
(2231, 'Sale Invoice', 'sale_invoice', 19, NULL, 2, '2025-08-20', 'Sale Invoice #4 - Abdul Qudus Bhai', 3000.00, 0.00, '2025-08-20 08:28:07'),
(2232, 'Sale Invoice', 'sale_invoice', 19, NULL, 9, '2025-08-20', 'Sale Invoice #4 - Abdul Qudus Bhai', 0.00, 3000.00, '2025-08-20 08:28:07'),
(2233, 'Receive Voucher', 'receive_voucher', 7, NULL, 7, '2025-08-20', 'Payment received - RV-250820-001', 540000.00, 0.00, '2025-08-20 08:50:22'),
(2234, 'Receive Voucher', 'receive_voucher', 7, NULL, 2, '2025-08-20', 'Payment received - RV-250820-001', 0.00, 540000.00, '2025-08-20 08:50:22'),
(2237, 'Payroll Entry', 'payroll_entries', 6, NULL, 25, '2025-08-20', 'Payroll - Zahid Ghori - PY-20250820-5373', 50000.00, 0.00, '2025-08-20 11:57:01'),
(2238, 'Payroll Entry', 'payroll_entries', 6, NULL, 28, '2025-08-20', 'Payroll - Zahid Ghori - PY-20250820-5373', 0.00, 50000.00, '2025-08-20 11:57:01'),
(2239, 'Receive Voucher', 'receive_voucher', 9, NULL, 5, '2025-08-20', 'Payment received - RV-250820-002', 126.00, 0.00, '2025-08-20 13:39:15'),
(2240, 'Receive Voucher', 'receive_voucher', 9, NULL, 2, '2025-08-20', 'Payment received - RV-250820-002', 0.00, 126.00, '2025-08-20 13:39:15'),
(2241, 'Sale Invoice', 'sale_invoice', 20, NULL, 2, '2025-07-25', 'Sale Invoice #0 - Wasim G/S', 14308.00, 0.00, '2025-09-08 07:11:25'),
(2242, 'Sale Invoice', 'sale_invoice', 20, NULL, 9, '2025-07-25', 'Sale Invoice #0 - Wasim G/S', 0.00, 14600.00, '2025-09-08 07:11:25'),
(2243, 'Sale Invoice', 'sale_invoice', 20, NULL, 16, '2025-07-25', 'Discount on Invoice #0 - Wasim G/S', 292.00, 0.00, '2025-09-08 07:11:25');

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `sub_account_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `sub_account_id`, `name`, `debit`, `credit`) VALUES
(1, 74, 'Cash', 0.00, 0.00),
(2, 76, 'Trade Debtors', 0.00, 0.00),
(3, 75, 'Bank Al Habib Limited', 0.00, 0.00),
(5, 1, 'Bank', 0.00, 0.00),
(6, 1, 'Cheque', 0.00, 0.00),
(7, 1, 'Cash', 0.00, 0.00),
(8, 1, 'Online', 0.00, 0.00),
(9, 77, 'Sales Revenue', 0.00, 0.00),
(10, 77, 'Service Revenue', 0.00, 0.00),
(11, 78, 'Interest Income', 0.00, 0.00),
(12, 78, 'Other Income', 0.00, 0.00),
(13, 79, 'Customer Advances', 0.00, 0.00),
(14, 80, 'Trade Creditors', 0.00, 0.00),
(15, 24, 'Electricity', 0.00, 0.00),
(16, 24, 'Gas', 0.00, 0.00),
(17, 24, 'Water', 0.00, 0.00),
(18, 81, 'Sales Returns & Allowances', 0.00, 0.00),
(19, 82, 'Purchases', 0.00, 0.00),
(20, 83, 'Purchase Returns & Allowances', 0.00, 0.00),
(24, 74, 'Vendor Advances', 0.00, 0.00),
(25, 76, 'Salaries Expense', 0.00, 0.00),
(26, 76, 'Bonus Expense', 0.00, 0.00),
(27, 76, 'Employer Contributions', 0.00, 0.00),
(28, 87, 'Salaries Payable', 0.00, 0.00),
(29, 87, 'Employee Tax Payable', 0.00, 0.00),
(30, 87, 'Social Security Payable', 0.00, 0.00),
(31, 74, 'Advances & Loans to Employees', 0.00, 0.00),
(32, 88, 'General Inventory', 0.00, 0.00),
(33, 88, 'Trading Inventory', 0.00, 0.00),
(34, 88, 'Raw Materials', 0.00, 0.00),
(35, 88, 'WIP - Internal', 0.00, 0.00),
(36, 88, 'Finished Goods', 0.00, 0.00),
(37, 88, 'Packing Materials', 0.00, 0.00),
(42, 91, 'Raw Materials Consumed', 0.00, 0.00),
(43, 91, 'Direct Labor Wages', 0.00, 0.00),
(44, 91, 'Job Work / Subcontracting', 0.00, 0.00),
(45, 92, 'Indirect Materials (Lubricant)', 0.00, 0.00),
(46, 92, 'Indirect Labor (Supervisor)', 0.00, 0.00),
(47, 93, 'Factory Rent', 0.00, 0.00),
(48, 93, 'Factory Electricity', 0.00, 0.00),
(49, 93, 'Machine Depreciation', 0.00, 0.00),
(54, 88, 'WIP - Laundry', 0.00, 0.00),
(55, 88, 'WIP - Press', 0.00, 0.00),
(56, 88, 'WIP - Cutting', 0.00, 0.00),
(57, 88, 'Inventory Transfer Account', 0.00, 0.00),
(58, 94, 'Rent Expense', 0.00, 0.00),
(59, 94, 'Utility', 0.00, 0.00),
(60, 96, 'Salaries Expense', 0.00, 0.00),
(61, 96, 'Staff Benefits Expense', 0.00, 0.00),
(62, 95, 'Advertising Expense', 0.00, 0.00),
(63, 95, 'Sales Commission Expense', 0.00, 0.00),
(64, 97, 'Bank Charges', 0.00, 0.00),
(65, 97, 'Interest Expense', 0.00, 0.00),
(66, 96, 'Staff Training & Development', 0.00, 0.00),
(67, 96, 'Staff Recruitment Expense', 0.00, 0.00),
(68, 94, 'Office & Administrative', 0.00, 0.00),
(69, 94, 'Professional & Compliance', 0.00, 0.00),
(70, 94, 'Depreciation & Amortization', 0.00, 0.00),
(71, 94, 'Other Admin', 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `accounts_head`
--

CREATE TABLE `accounts_head` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts_head`
--

INSERT INTO `accounts_head` (`id`, `name`) VALUES
(1, 'Assets'),
(2, 'Liabilities'),
(3, 'Equity'),
(4, 'Income'),
(5, 'Expenses');

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `area_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `status` enum('Present','Absent','Late') NOT NULL DEFAULT 'Present',
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `balance_invoice_entries`
--

CREATE TABLE `balance_invoice_entries` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `sales_officer` varchar(100) NOT NULL,
  `inv_no` varchar(50) NOT NULL,
  `opening` decimal(10,2) DEFAULT NULL,
  `credit` decimal(10,2) DEFAULT 0.00,
  `invoice_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `balance_invoice_entries`
--

INSERT INTO `balance_invoice_entries` (`id`, `customer_id`, `sales_officer`, `inv_no`, `opening`, `credit`, `invoice_date`, `created_at`) VALUES
(406, 23, 'Zahid', '222', 4000.00, 0.00, '2025-08-01', '2025-08-15 03:40:02');

--
-- Triggers `balance_invoice_entries`
--
DELIMITER $$
CREATE TRIGGER `trg_after_balance_invoice_entries_delete` AFTER DELETE ON `balance_invoice_entries` FOR EACH ROW BEGIN
    -- Remove all related ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'balance_invoice_entries' AND reference_id = OLD.id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_balance_invoice_entries_insert` AFTER INSERT ON `balance_invoice_entries` FOR EACH ROW BEGIN
    -- Handle opening balance if > 0
    IF NEW.opening > 0 THEN
        -- Debit: Trade Debtors (customer owes money)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Opening Balance', 'balance_invoice_entries', NEW.id, 2, -- Trade Debtors
            NEW.invoice_date,
            CONCAT('Opening Balance - Inv: ', NEW.inv_no),
            NEW.opening, 0
        );
        
        -- Credit: Capital Account (balancing entry)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Opening Balance', 'balance_invoice_entries', NEW.id, 1, -- Capital account
            NEW.invoice_date,
            CONCAT('Opening Balance - Inv: ', NEW.inv_no),
            0, NEW.opening
        );
    END IF;
    
    -- Handle credit balance if > 0
    IF NEW.credit > 0 THEN
        -- Debit: Capital Account
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Opening Balance', 'balance_invoice_entries', NEW.id, 1,
            NEW.invoice_date,
            CONCAT('Opening Credit - Inv: ', NEW.inv_no),
            NEW.credit, 0
        );
        
        -- Credit: Trade Debtors (customer has credit)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Opening Balance', 'balance_invoice_entries', NEW.id, 2, -- Trade Debtors
            NEW.invoice_date,
            CONCAT('Opening Credit - Inv: ', NEW.inv_no),
            0, NEW.credit
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_balance_invoice_entries_update` AFTER UPDATE ON `balance_invoice_entries` FOR EACH ROW BEGIN
    -- Delete old ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'balance_invoice_entries' AND reference_id = NEW.id;
    
    -- Handle opening balance if > 0
    IF NEW.opening > 0 THEN
        -- Debit: Trade Debtors
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Opening Balance', 'balance_invoice_entries', NEW.id, 2,
            NEW.invoice_date,
            CONCAT('Opening Balance - Inv: ', NEW.inv_no),
            NEW.opening, 0
        );
        
        -- Credit: Capital Account
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Opening Balance', 'balance_invoice_entries', NEW.id, 1,
            NEW.invoice_date,
            CONCAT('Opening Balance - Inv: ', NEW.inv_no),
            0, NEW.opening
        );
    END IF;
    
    -- Handle credit balance if > 0
    IF NEW.credit > 0 THEN
        -- Debit: Capital Account
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Opening Balance', 'balance_invoice_entries', NEW.id, 1,
            NEW.invoice_date,
            CONCAT('Opening Credit - Inv: ', NEW.inv_no),
            NEW.credit, 0
        );
        
        -- Credit: Trade Debtors
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Opening Balance', 'balance_invoice_entries', NEW.id, 2,
            NEW.invoice_date,
            CONCAT('Opening Credit - Inv: ', NEW.inv_no),
            0, NEW.credit
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `bank_name`, `is_mfb`, `mfb_name`, `account_number`, `account_type`, `currency`, `branch_name`, `branch_code`, `branch_city`, `branch_state`, `branch_address`, `account_title`, `iban`, `swift_code`, `contact_person`, `contact_number`, `email`, `bank_logo_path`, `notes`, `balance_type`, `opening_balance`, `debit_amount`, `credit_amount`, `as_of_date`, `created_at`, `updated_at`, `is_active`, `account_id`) VALUES
(1, 'Bank AL Habib ', 0, NULL, '0981005596017', 'current', 'PKR', 'N/F', '1147', 'N.Feroze', 'Unknown', 'N.Feroze', 'M/S Badri Traders ', '', '', 'Ab.Ghaffar Rajput ', '03003353277', '', NULL, NULL, 'debit', 0.00, 0.00, 0.00, '2025-08-01', '2025-06-16 11:38:36', '2025-08-01 14:25:30', 1, 3);

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
-- Table structure for table `bank_info`
--

CREATE TABLE `bank_info` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `branch_code` varchar(50) DEFAULT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_title` varchar(255) NOT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `swift_code` varchar(50) DEFAULT NULL,
  `bank_address` text DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `debit` int(11) DEFAULT NULL,
  `credit` int(11) DEFAULT NULL,
  `account_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_info`
--

INSERT INTO `bank_info` (`id`, `bank_name`, `branch_name`, `branch_code`, `account_number`, `account_title`, `iban`, `swift_code`, `bank_address`, `contact_person`, `contact_number`, `email`, `created_at`, `updated_at`, `debit`, `credit`, `account_id`) VALUES
(5, 'Habib Metro', 'Jodia Bazar', '', '122321342343', '23423423423432', '', '', '', '', '', '', '2025-08-20 09:25:55', '2025-08-20 09:25:55', 600000, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `batchsetup`
--

CREATE TABLE `batchsetup` (
  `id` int(11) NOT NULL,
  `batchid` varchar(50) DEFAULT NULL,
  `batchno` varchar(50) DEFAULT NULL,
  `mfgdate` date DEFAULT NULL,
  `expdate` date DEFAULT NULL,
  `itemname` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bill_material`
--

CREATE TABLE `bill_material` (
  `id` int(11) NOT NULL,
  `job_no` int(11) NOT NULL,
  `bom_type` varchar(255) DEFAULT NULL,
  `bom_conversion` varchar(255) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_code` varchar(255) DEFAULT NULL,
  `production_of` varchar(255) DEFAULT NULL,
  `production_type` varchar(255) DEFAULT NULL,
  `sfg_consumption` varchar(255) DEFAULT NULL,
  `standard_loss` varchar(255) DEFAULT NULL,
  `production_unit` varchar(50) DEFAULT NULL,
  `output_size` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `labour_cost` decimal(10,2) DEFAULT NULL,
  `factory_overheads` decimal(10,2) DEFAULT NULL,
  `machine_cost` decimal(10,2) DEFAULT NULL,
  `by_product` varchar(255) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bom`
--

CREATE TABLE `bom` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `version` varchar(50) DEFAULT '1.0',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bom`
--

INSERT INTO `bom` (`id`, `product_id`, `version`, `is_active`, `created_at`) VALUES
(3, 1, '1.0', 1, '2025-08-04 22:13:20'),
(4, 8, '1.0', 1, '2025-08-20 09:17:49');

-- --------------------------------------------------------

--
-- Table structure for table `bom_items`
--

CREATE TABLE `bom_items` (
  `id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `raw_material_id` int(11) NOT NULL,
  `qty_required` decimal(15,3) NOT NULL,
  `uom` varchar(50) DEFAULT NULL,
  `wastage_percent` decimal(5,2) DEFAULT NULL,
  `wastage_fixed_qty` decimal(15,3) DEFAULT NULL,
  `wastage_calculation_method` enum('percentage','fixed','unknown') DEFAULT 'unknown',
  `wastage_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bom_items`
--

INSERT INTO `bom_items` (`id`, `bom_id`, `raw_material_id`, `qty_required`, `uom`, `wastage_percent`, `wastage_fixed_qty`, `wastage_calculation_method`, `wastage_notes`) VALUES
(15, 3, 2, 5.000, 'Pcs', NULL, NULL, 'unknown', NULL),
(16, 3, 3, 20.000, 'Pcs', NULL, NULL, 'unknown', NULL),
(17, 3, 4, 2.000, 'L', NULL, NULL, 'unknown', NULL),
(18, 3, 5, 1.000, 'Pcs', NULL, NULL, 'unknown', NULL),
(19, 3, 6, 3.000, 'L', NULL, NULL, 'unknown', NULL),
(20, 3, 7, 4.000, 'Pcs', NULL, NULL, 'unknown', NULL),
(21, 4, 3, 99.000, 'Pcs', NULL, NULL, 'unknown', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `branch_name` varchar(255) NOT NULL,
  `country_code` varchar(10) NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `currency` varchar(10) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `ntn_numbers` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(3, 'kiryana 111');

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` int(11) NOT NULL,
  `city_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cities`
--

INSERT INTO `cities` (`id`, `city_name`) VALUES
(1, 'Karachi');

-- --------------------------------------------------------

--
-- Table structure for table `city`
--

CREATE TABLE `city` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `city`
--

INSERT INTO `city` (`id`, `name`) VALUES
(1, 'Karachi');

-- --------------------------------------------------------

--
-- Table structure for table `company`
--

CREATE TABLE `company` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `address_line1` varchar(100) NOT NULL,
  `address_line2` varchar(100) DEFAULT NULL,
  `city` varchar(50) NOT NULL,
  `state` varchar(50) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country` varchar(50) NOT NULL,
  `phone_1` varchar(20) NOT NULL,
  `phone_2` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_settings`
--

INSERT INTO `company_settings` (`id`, `company_name`, `company_logo`, `address_line1`, `address_line2`, `city`, `state`, `postal_code`, `country`, `phone_1`, `phone_2`, `email`, `website`, `tax_id`, `registration_number`, `created_at`, `updated_at`) VALUES
(1, 'AQ International', 'company_logo_6896e44bc6058.jpg', 'ADD # Plot AB-33 , ST -17/2 , Bhangoria Town , Karachi  ', '', 'Karachi', 'Sindh ', '74400', 'Pakistan', '03102020199', '', 'info@aq-international.com', 'https://www.aq-international.com', '17-00-3951-128-15', '3951128-5', '2025-07-17 12:02:10', '2025-08-19 21:42:17'),
(2, 'Techno Tech Traders', 'uploads/company_logo/company_logo_6878e6979fa2c.jpg', '  ', '', 'Karachi', 'England', '74400', 'Pakistan', '2075552000', '', '', '', '', '', '2025-07-17 12:02:10', '2025-08-09 06:03:02'),
(3, 'accura', 'uploads/company_logo/company_logo_6878e6c9da74e.png', 'xyz', '22', 'karachi3', 'karachi33', '0000', 'Pakistan', '03411308801', '', '', '', '', '', '2025-07-17 12:04:25', '2025-07-17 12:04:25'),
(4, '3D Tech', 'uploads/company_logo/company_logo_68848606d857e.jpg', 'FB Area', 'Karachi', 'Karachi', 'Sindh ', '78698', 'Pakistan', '09090909090', '', 'asif@ymail.com', 'http://www.ymail.com', '123012301231230123', '12123123123', '2025-07-26 07:37:51', '2025-07-26 07:38:46');

-- --------------------------------------------------------

--
-- Table structure for table `completion_packing_materials`
--

CREATE TABLE `completion_packing_materials` (
  `id` int(11) NOT NULL,
  `completion_item_id` int(11) NOT NULL,
  `packing_id` int(11) NOT NULL,
  `qty_used` decimal(15,3) NOT NULL,
  `machine_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `connections`
--

CREATE TABLE `connections` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `connected_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `costing_type`
--

CREATE TABLE `costing_type` (
  `id` int(11) NOT NULL,
  `costing_type` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cost_accounting_ledger`
--

CREATE TABLE `cost_accounting_ledger` (
  `id` int(11) NOT NULL,
  `transaction_type` enum('Material Issue','Labor','Overhead Allocation','Adjustment') NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `cost_center_id` int(11) DEFAULT NULL,
  `expense_type` enum('Direct','Indirect') NOT NULL,
  `expense_account_id` int(11) NOT NULL,
  `reference_table` varchar(100) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cost_centers`
--

CREATE TABLE `cost_centers` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cost_centers`
--

INSERT INTO `cost_centers` (`id`, `account_id`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(7, 58, 'Shop#1', NULL, 1, '2025-08-19 18:01:46', '2025-08-19 18:01:46'),
(8, 58, 'Shop#2', NULL, 1, '2025-08-19 18:03:27', '2025-08-19 18:03:27'),
(9, 58, 'Shop#3', NULL, 1, '2025-08-19 18:03:45', '2025-08-19 18:03:45'),
(10, 58, 'Warehouse', NULL, 1, '2025-08-19 18:04:00', '2025-08-19 18:04:00'),
(11, 68, 'Office Supplies', NULL, 1, '2025-08-19 18:04:57', '2025-08-19 18:04:57'),
(12, 68, 'Printing & Stationery', NULL, 1, '2025-08-19 18:05:07', '2025-08-19 18:05:07'),
(13, 68, 'Telephone & Internet Expense', NULL, 1, '2025-08-19 18:05:15', '2025-08-19 18:05:15'),
(14, 68, 'Postage & Courier Expense', NULL, 1, '2025-08-19 18:05:25', '2025-08-19 18:05:25'),
(15, 68, 'Repairs & Maintenance', NULL, 1, '2025-08-19 18:05:37', '2025-08-19 18:05:37'),
(16, 68, 'Cleaning & Janitorial Expense', NULL, 1, '2025-08-19 18:05:59', '2025-08-19 18:05:59'),
(17, 68, 'Security Expense', NULL, 1, '2025-08-19 18:06:05', '2025-08-19 18:06:05'),
(18, 69, 'Audit Fees', NULL, 1, '2025-08-19 18:06:40', '2025-08-19 18:06:40'),
(19, 69, 'Legal Fees', NULL, 1, '2025-08-19 18:06:52', '2025-08-19 18:06:52'),
(20, 69, 'Consulting/Professional Services Fees', NULL, 1, '2025-08-19 18:07:00', '2025-08-19 18:07:00'),
(21, 69, 'License & Permits Expense', NULL, 1, '2025-08-19 18:07:09', '2025-08-19 18:07:09'),
(22, 70, 'Depreciation – Office Equipment', NULL, 1, '2025-08-19 18:07:33', '2025-08-19 18:07:33'),
(23, 70, 'Depreciation – Furniture & Fixtures', NULL, 1, '2025-08-19 18:07:40', '2025-08-19 18:07:40'),
(24, 70, 'Amortization of Intangibles', NULL, 1, '2025-08-19 18:07:48', '2025-08-19 18:07:48'),
(25, 59, 'Electricity', NULL, 1, '2025-08-19 18:08:12', '2025-08-19 18:08:12'),
(26, 59, 'Water', NULL, 1, '2025-08-19 18:08:19', '2025-08-19 18:08:19'),
(27, 59, 'Gas', NULL, 1, '2025-08-19 18:08:24', '2025-08-19 18:08:24'),
(28, 71, 'Insurance Expense', NULL, 1, '2025-08-19 18:08:39', '2025-08-19 18:08:39'),
(29, 71, 'Travel & Entertainment', NULL, 1, '2025-08-19 18:08:50', '2025-08-19 18:08:50'),
(30, 71, 'Subscriptions & Memberships', NULL, 1, '2025-08-19 18:08:59', '2025-08-19 18:08:59'),
(31, 71, 'IT Expenses', NULL, 1, '2025-08-19 18:09:11', '2025-08-19 18:09:11'),
(32, 71, 'Miscellaneous General Expense', NULL, 1, '2025-08-19 18:09:20', '2025-08-19 18:09:20'),
(33, 62, 'Social Media Marketing', NULL, 1, '2025-08-19 18:10:32', '2025-08-19 18:10:32');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `customer_vendor_type` enum('Customer','Vendor','Both') NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `business_name` varchar(100) DEFAULT NULL,
  `owner_name` varchar(100) DEFAULT NULL,
  `cnic_no` varchar(15) DEFAULT NULL,
  `primary_cell_no` varchar(20) DEFAULT NULL,
  `secondary_cell_no` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `register_sales_tax` enum('yes','no') DEFAULT 'no',
  `whole_sale` enum('yes','no') DEFAULT 'no',
  `black_list` tinyint(1) DEFAULT 0,
  `sales_tax_no` varchar(50) DEFAULT NULL,
  `ntn_no` varchar(50) DEFAULT NULL,
  `credit_limit` decimal(10,2) DEFAULT 0.00,
  `credit_period` int(11) DEFAULT 0,
  `discount` decimal(5,2) DEFAULT 0.00,
  `alt` decimal(5,2) DEFAULT 0.00,
  `filer` tinyint(1) DEFAULT 0,
  `invoice_number` varchar(50) DEFAULT NULL,
  `opening_amount` decimal(10,2) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `account_id` int(11) NOT NULL DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `customer_vendor_type`, `customer_name`, `business_name`, `owner_name`, `cnic_no`, `primary_cell_no`, `secondary_cell_no`, `address`, `email`, `zone_id`, `area_id`, `register_sales_tax`, `whole_sale`, `black_list`, `sales_tax_no`, `ntn_no`, `credit_limit`, `credit_period`, `discount`, `alt`, `filer`, `invoice_number`, `opening_amount`, `invoice_date`, `created_at`, `account_id`) VALUES
(20, 'Customer', 'InnovaTech', 'InnovaTech', '', '', '03342616587', '', 'Saadi Town Block 1', '', NULL, NULL, 'no', 'no', 0, '', '', 0.00, 0, 0.00, 0.00, 0, '', 0.00, '0000-00-00', '2025-08-04 21:59:11', 2),
(23, 'Customer', 'Asif G/s', 'Asif G/s', '', '', '03468918711', '', 'Ali block Bahria Town Karachi', '', 8, NULL, 'no', 'yes', 0, '', '', 15000.00, 0, 0.00, 0.00, 0, '222', 0.00, '2025-08-01', '2025-08-15 03:40:02', 2),
(24, 'Customer', 'WAZEER ALI', 'WAZEER ALI', '', '', '', '', 'SHAHI BAZA', '', NULL, NULL, 'no', 'yes', 0, '', '', 0.00, 0, 0.00, 0.00, 0, '', 0.00, '0000-00-00', '2025-08-15 11:52:47', 2),
(25, 'Customer', 'WAZEER ALI', 'WAZEER ALI', '', '', '', '', 'SHAHI BAZA', '', NULL, NULL, 'no', 'yes', 0, '', '', 0.00, 0, 0.00, 0.00, 0, '', 0.00, '0000-00-00', '2025-08-15 11:54:34', 2),
(26, 'Customer', 'Abdul Qudus Bhai', 'Abdul Qudus Bhai', 'Abdul Qudus', '465811082046999', '03215987841', '032165498765133', 'Shershah', 'aqb@gmail.com', 7, NULL, 'yes', 'yes', 0, '2412415-25412', '21546231659', 5000000.00, 0, 0.00, 0.00, 1, '', 0.00, '0000-00-00', '2025-08-18 08:14:58', 2),
(27, 'Customer', 'Art', 'Art', 'Abdul Rashid', '42111-1245145-1', '12345678901', '12345678901', 'KHhi', 'art@gmail.com', 8, NULL, 'yes', 'yes', 0, '12-34-5678-123-45', '1234567', 200000.00, 0, 10.00, 0.00, 1, '', 0.00, '0000-00-00', '2025-08-26 07:55:21', 2),
(28, 'Customer', 'Lahore Commission Corpo', 'Lahore Commission Corpo', 'Arif Lahori', '', '', '', 'Lahor', 'ari@gmail.com', 7, NULL, 'yes', 'yes', 0, '17-00-3951-128-15', '1234567', 0.00, 0, 0.00, 0.00, 1, '', 0.00, '0000-00-00', '2025-09-02 08:01:01', 2),
(29, 'Customer', 'PSL', 'PSL', 'Pakistan', '12345-1213456-1', '+021-0000000', '', 'khi', 'psl@gmail.com', 8, NULL, 'no', 'no', 0, '17-00-3951-128-15', '1234567', 50000.00, 0, 0.00, 0.00, 0, '', 0.00, '0000-00-00', '2025-09-02 08:02:29', 2),
(30, 'Both', 'Hello World', 'Hello World', 'World', '12345-1234567-1', '02132578411', '', 'Khi', 'HE@gmail.com', 8, NULL, 'yes', 'yes', 0, '12-34-3211-211-11', '1234567', 0.00, 0, 0.00, 0.00, 1, '', 0.00, '0000-00-00', '2025-09-02 08:08:07', 2),
(31, 'Customer', 'AQ International', 'AQ International', '', '', '', '', 'Plot AB-33, ST-17/2, Bhangoria Town', 'info@aq-international.com', NULL, NULL, 'no', 'no', 0, '', '', 0.00, 0, 0.00, 0.00, 0, '', 0.00, '0000-00-00', '2025-09-02 08:10:04', 2);

-- --------------------------------------------------------

--
-- Table structure for table `customers_details`
--

CREATE TABLE `customers_details` (
  `id` int(11) NOT NULL,
  `customer_id` varchar(15) NOT NULL,
  `business_name` varchar(100) NOT NULL,
  `address` varchar(100) NOT NULL,
  `primary_cell_no` varchar(15) NOT NULL,
  `secondary_cell_no` varchar(15) DEFAULT NULL,
  `owner_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `zone` varchar(50) DEFAULT NULL,
  `area` varchar(50) DEFAULT NULL,
  `register_sales_tax` enum('yes','no') DEFAULT NULL,
  `whole_sale` enum('yes','no') DEFAULT NULL,
  `black_list` tinyint(1) DEFAULT 0,
  `sales_tax_no` varchar(20) DEFAULT NULL,
  `ntn_no` varchar(20) DEFAULT NULL,
  `cnic_no` varchar(20) DEFAULT NULL,
  `credit_limit` decimal(10,2) DEFAULT NULL,
  `credit_period` int(11) DEFAULT NULL,
  `discount` decimal(5,2) DEFAULT NULL,
  `alt` decimal(5,2) DEFAULT NULL,
  `filer` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `opening` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers_details`
--

INSERT INTO `customers_details` (`id`, `customer_id`, `business_name`, `address`, `primary_cell_no`, `secondary_cell_no`, `owner_name`, `email`, `zone`, `area`, `register_sales_tax`, `whole_sale`, `black_list`, `sales_tax_no`, `ntn_no`, `cnic_no`, `credit_limit`, `credit_period`, `discount`, `alt`, `filer`, `created_at`, `opening`) VALUES
(0, '2', 'Shah Traders', 'Sukkur', '03001234567', '', '', '', 'Zone B', 'Sindh', 'yes', 'yes', 0, '', '', '', 0.00, 0, 0.00, 0.00, 0, '2025-05-27 17:59:39', 0.00),
(0, '3', 'Ali iron', 'Hyderabad', '03001234567', '', '', '', 'Zone B', 'Sindh', 'no', 'no', 0, '', '', '', 0.00, 0, 0.00, 0.00, 0, '2025-05-27 18:00:50', 0.00),
(0, '4', 'Kafif Works', 'RYK', '', '', '', '', 'Zone B', '', 'no', 'no', 0, '', '', '', 0.00, 0, 0.00, 0.00, 0, '2025-05-27 18:01:36', 0.00),
(0, '5', 'Kathore Poultry Service', 'Toll Plaza', '03208383156', '', '', '', '', '', 'no', 'no', 0, '', '', '', 0.00, 0, 0.00, 0.00, 0, '2025-06-09 11:08:40', 250000.00),
(0, '6', 'Bakhtawar Traders', 'Mulan', '03019295073', '', 'Arslan', 'arslannaeem6811@gmail.com', 'Zone A', '', 'no', 'yes', 0, '', '', '', 0.00, 0, 0.00, 0.00, 0, '2025-06-09 16:58:27', 0.00),
(0, '7', 'Ali K/S fazabade', 'Basti ShorKot Sui Gas', '09065893582', '26550558', 'Ali', '', 'Zone A', '', 'no', 'no', 0, '', '', '0391802293.501', 0.00, 0, 0.00, 0.00, 0, '2025-06-10 10:06:05', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `delivery_challan`
--

CREATE TABLE `delivery_challan` (
  `id` int(11) NOT NULL,
  `serialNo` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `poNumber` varchar(50) NOT NULL,
  `poDate` date NOT NULL,
  `deliveryChallanNo` varchar(50) NOT NULL,
  `customerCode` varchar(50) NOT NULL,
  `customerName` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `itemCode` text NOT NULL,
  `description` text NOT NULL,
  `quantity` text NOT NULL,
  `unit` text NOT NULL,
  `packing` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_challan`
--

INSERT INTO `delivery_challan` (`id`, `serialNo`, `date`, `poNumber`, `poDate`, `deliveryChallanNo`, `customerCode`, `customerName`, `address`, `telephone`, `itemCode`, `description`, `quantity`, `unit`, `packing`, `created_at`, `updated_at`) VALUES
(10, '0001', '2025-06-30', '2', '2025-06-30', '123456', '20', 'Hamza Tariq', 'Peshawar', '03077777888', '[\"13\"]', '[\"Product 13\"]', '[\"\"]', '[\"PCS\"]', '[\"\"]', '2025-06-30 01:44:55', '2025-07-17 16:27:34'),
(11, '0002', '2025-06-30', '5', '2025-06-30', '123456', '16', 'Usman Javed', 'Islamabad', '03032222333', '[\"20\",\"16\"]', '[\"20\",\"16\"]', '[\"4\",\"\"]', '[\"Pack\",\"Pcs\"]', '[\"24\",\"\"]', '2025-06-30 01:45:31', '2025-07-17 16:27:39'),
(12, '0003', '2025-06-30', '9', '2025-06-30', '123456', '12', 'Imran hashmis', 'Siyal Morri', '66262626222', '[\"15\",\"15\"]', '[\"15\",\"15\"]', '[\"\",\"\"]', '[\"1\",\"1\"]', '[\"20\",\"20\"]', '2025-06-30 02:00:39', '2025-07-24 15:03:11'),
(15, '', '0000-00-00', '', '0000-00-00', '', '', '', '', '', '[\"20\",\"\",\"\"]', '[\"20\",\"\",\"\"]', '[\"\",\"\",\"\"]', '[\"20\",\"\",\"\"]', '[\"\",\"\",\"\"]', '2025-07-24 15:24:59', '2025-07-24 15:24:59'),
(16, '', '0000-00-00', '', '0000-00-00', '', '', '', '', '', '[\"20\",\"19\",\"\"]', '[\"20\",\"19\",\"\"]', '[\"\",\"\",\"\"]', '[\"20\",\"19\",\"\"]', '[\"\",\"\",\"\"]', '2025-07-25 04:50:18', '2025-07-25 04:50:18');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_challans`
--

CREATE TABLE `delivery_challans` (
  `challan_id` int(11) NOT NULL,
  `serial_no` varchar(50) NOT NULL,
  `challan_date` date NOT NULL,
  `po_number` varchar(100) NOT NULL,
  `po_date` date NOT NULL,
  `job_no` varchar(100) NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_address` text NOT NULL,
  `customer_telephone` varchar(50) NOT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_challans`
--

INSERT INTO `delivery_challans` (`challan_id`, `serial_no`, `challan_date`, `po_number`, `po_date`, `job_no`, `customer_code`, `customer_name`, `customer_address`, `customer_telephone`, `items`, `created_at`) VALUES
(8, 'DC-0001', '2025-09-08', 'PO002', '2025-09-08', 'JOB002', '26', '26', 'Shershah', '03215987841', '[{\"item_code\":\"11\",\"item_name\":\"11\",\"description\":\"\",\"quantity\":\"\",\"unit\":\"1\",\"packing_detail\":\"\"},{\"item_code\":\"1\",\"item_name\":\"1\",\"description\":\"\",\"quantity\":\"1\",\"unit\":\"1\",\"packing_detail\":\"6\"}]', '2025-09-08 07:09:30');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `dept_name` varchar(50) NOT NULL,
  `dept_code` varchar(10) DEFAULT NULL,
  `parent_dept_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `manager_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `dept_name`, `dept_code`, `parent_dept_id`, `description`, `created_at`, `updated_at`, `manager_id`) VALUES
(1, 'Sales', '', NULL, NULL, '2025-05-29 09:43:30', '2025-05-29 09:43:30', NULL),
(2, 'Arslan', '', 1, NULL, '2025-06-10 10:36:08', '2025-06-10 10:36:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `education_levels`
--

CREATE TABLE `education_levels` (
  `id` int(11) NOT NULL,
  `level_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `commission_type` varchar(50) NOT NULL,
  `designation` varchar(500) NOT NULL,
  `cnic` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone_primary` varchar(20) DEFAULT NULL,
  `phone_secondary` varchar(20) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `place_of_birth` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `marital_status` enum('Single','Married','Divorced','Widowed') DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `caste` varchar(50) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `employment_type` enum('Full-time','Part-time','Contract','Temporary') DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Active','Inactive','On Leave','Terminated') DEFAULT 'Active',
  `reporting_manager_id` int(11) DEFAULT NULL,
  `work_location` varchar(100) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `id_expiry_date` date DEFAULT NULL,
  `passport_number` varchar(50) DEFAULT NULL,
  `passport_expiry` date DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_address` text DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `has_diseases` tinyint(1) DEFAULT 0,
  `disease_details` text DEFAULT NULL,
  `has_disability` tinyint(1) DEFAULT 0,
  `disability_details` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `languages` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `hired_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `photo` varchar(255) DEFAULT NULL,
  `opening` int(11) DEFAULT NULL,
  `credit` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_id`, `first_name`, `last_name`, `commission_type`, `designation`, `cnic`, `email`, `phone_primary`, `phone_secondary`, `photo_path`, `date_of_birth`, `place_of_birth`, `gender`, `marital_status`, `religion`, `caste`, `nationality`, `address`, `city`, `state`, `postal_code`, `country`, `department_id`, `position`, `employment_type`, `hire_date`, `end_date`, `status`, `reporting_manager_id`, `work_location`, `barcode`, `id_expiry_date`, `passport_number`, `passport_expiry`, `emergency_contact_name`, `emergency_contact_relationship`, `emergency_contact_phone`, `emergency_contact_address`, `blood_group`, `has_diseases`, `disease_details`, `has_disability`, `disability_details`, `skills`, `languages`, `remarks`, `hired_by`, `created_at`, `updated_at`, `photo`, `opening`, `credit`) VALUES
(35, 'SLS253029', 'Zahid', 'Ghori', '', 'Sales Officer', '12345-1234567-1', 'innova.tech213@gmail.com', '', '', NULL, NULL, '', 'Male', '', 'Islam', '', 'Pakistan', '', 'Karachi', 'Sindh', '', 'Pakistan', 1, 'Sales Officer', 'Full-time', NULL, NULL, 'Active', NULL, '', 'SLS253029', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 0, '', 0, '', '', '                                    placeholder=\"List known languages\">', '', NULL, '2025-08-08 17:39:18', '2025-08-08 17:39:18', NULL, NULL, NULL);

--
-- Triggers `employees`
--
DELIMITER $$
CREATE TRIGGER `trg_after_employees_delete` AFTER DELETE ON `employees` FOR EACH ROW BEGIN
    -- Remove all related opening balance ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'employees' AND reference_id = OLD.id 
    AND transaction_type = 'Employee Opening Balance';
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_employees_insert` AFTER INSERT ON `employees` FOR EACH ROW BEGIN
    -- Handle opening balance (advance to employee)
    IF NEW.opening > 0 THEN
        -- Debit: Advances & Loans to Employees (asset)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Employee Opening Balance', 'employees', NEW.id, 31, -- Advances & Loans to Employees
            CURDATE(),
            CONCAT('Opening Advance - ', NEW.first_name, ' ', COALESCE(NEW.last_name, '')),
            NEW.opening, 0
        );
        
        -- Credit: Capital Account (balancing entry)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Employee Opening Balance', 'employees', NEW.id, 1, -- Capital account
            CURDATE(),
            CONCAT('Opening Advance - ', NEW.first_name, ' ', COALESCE(NEW.last_name, '')),
            0, NEW.opening
        );
    END IF;
    
    -- Handle credit balance (unpaid salary)
    IF NEW.credit > 0 THEN
        -- Debit: Capital Account
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Employee Opening Balance', 'employees', NEW.id, 1,
            CURDATE(),
            CONCAT('Opening Salary Due - ', NEW.first_name, ' ', COALESCE(NEW.last_name, '')),
            NEW.credit, 0
        );
        
        -- Credit: Salaries Payable (liability)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Employee Opening Balance', 'employees', NEW.id, 28, -- Salaries Payable
            CURDATE(),
            CONCAT('Opening Salary Due - ', NEW.first_name, ' ', COALESCE(NEW.last_name, '')),
            0, NEW.credit
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_employees_update` AFTER UPDATE ON `employees` FOR EACH ROW BEGIN
    -- Only process if opening balances changed
    IF OLD.opening != NEW.opening OR OLD.credit != NEW.credit THEN
        -- Delete old ledger entries
        DELETE FROM accounting_ledger 
        WHERE reference_table = 'employees' AND reference_id = NEW.id 
        AND transaction_type = 'Employee Opening Balance';
        
        -- Handle opening balance (advance to employee)
        IF NEW.opening > 0 THEN
            -- Debit: Advances & Loans to Employees
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Employee Opening Balance', 'employees', NEW.id, 31,
                CURDATE(),
                CONCAT('Opening Advance - ', NEW.first_name, ' ', COALESCE(NEW.last_name, '')),
                NEW.opening, 0
            );
            
            -- Credit: Capital Account
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Employee Opening Balance', 'employees', NEW.id, 1,
                CURDATE(),
                CONCAT('Opening Advance - ', NEW.first_name, ' ', COALESCE(NEW.last_name, '')),
                0, NEW.opening
            );
        END IF;
        
        -- Handle credit balance (unpaid salary)
        IF NEW.credit > 0 THEN
            -- Debit: Capital Account
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Employee Opening Balance', 'employees', NEW.id, 1,
                CURDATE(),
                CONCAT('Opening Salary Due - ', NEW.first_name, ' ', COALESCE(NEW.last_name, '')),
                NEW.credit, 0
            );
            
            -- Credit: Salaries Payable
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Employee Opening Balance', 'employees', NEW.id, 28,
                CURDATE(),
                CONCAT('Opening Salary Due - ', NEW.first_name, ' ', COALESCE(NEW.last_name, '')),
                0, NEW.credit
            );
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_number` varchar(50) DEFAULT NULL,
  `document_path` varchar(255) NOT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_education`
--

CREATE TABLE `employee_education` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `degree_title` varchar(100) NOT NULL,
  `major` varchar(100) DEFAULT NULL,
  `institute` varchar(200) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `grade_or_cgpa` varchar(20) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_leaves`
--

CREATE TABLE `employee_leaves` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type` enum('Annual','Sick','Casual','Unpaid','Other') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_skills`
--

CREATE TABLE `employee_skills` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `proficiency_level` enum('Beginner','Intermediate','Advanced','Expert') NOT NULL,
  `years_of_experience` decimal(4,1) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_voucher`
--

CREATE TABLE `expense_voucher` (
  `id` int(11) NOT NULL,
  `voucher_number` varchar(50) NOT NULL,
  `voucher_date` date NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','posted','cancelled') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_voucher`
--

INSERT INTO `expense_voucher` (`id`, `voucher_number`, `voucher_date`, `reference`, `description`, `total_amount`, `status`, `created_by`, `approved_by`, `created_at`, `updated_at`) VALUES
(1, 'EV-2025-0001', '2025-08-19', '', '', 100.00, 'posted', 1, NULL, '2025-08-19 07:26:27', '2025-08-19 07:26:27'),
(2, 'EV-2025-2026', '2025-08-20', '', '', 110000.00, 'draft', 1, NULL, '2025-08-20 09:05:02', '2025-08-20 09:05:02');

--
-- Triggers `expense_voucher`
--
DELIMITER $$
CREATE TRIGGER `trg_after_expense_voucher_delete` AFTER DELETE ON `expense_voucher` FOR EACH ROW BEGIN
    -- Remove all related ledger entries (lines will be deleted by CASCADE)
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'expense_voucher_lines' 
    AND reference_id IN (
        SELECT id FROM expense_voucher_lines WHERE expense_voucher_id = OLD.id
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_expense_voucher_update` AFTER UPDATE ON `expense_voucher` FOR EACH ROW BEGIN
    -- Handle status changes
    IF OLD.status != NEW.status THEN
        IF NEW.status = 'posted' THEN
            -- Create ledger entries for all lines when posting
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            )
            SELECT 
                'Expense Voucher', 'expense_voucher_lines', evl.id, evl.account_id,
                NEW.voucher_date,
                CONCAT('EV-', NEW.voucher_number, ' Line ', evl.line_number,
                       CASE WHEN evl.description IS NOT NULL THEN CONCAT(' - ', evl.description) ELSE '' END),
                evl.expense_amount, 0
            FROM expense_voucher_lines evl
            WHERE evl.expense_voucher_id = NEW.id
            AND evl.expense_amount > 0;
            
            -- Create credit entries for payment methods
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            )
            SELECT 
                'Expense Voucher', 'expense_voucher_lines', evl.id, 
                CASE 
                    WHEN evl.bank_account_id IS NOT NULL THEN (
                        SELECT account_id FROM bank_accounts WHERE id = evl.bank_account_id
                    )
                    ELSE evl.mode_of_payment
                END,
                NEW.voucher_date,
                CONCAT('EV-', NEW.voucher_number, ' Line ', evl.line_number,
                       CASE WHEN evl.description IS NOT NULL THEN CONCAT(' - ', evl.description) ELSE '' END),
                0, evl.expense_amount
            FROM expense_voucher_lines evl
            WHERE evl.expense_voucher_id = NEW.id
            AND evl.expense_amount > 0;
            
        ELSEIF OLD.status = 'posted' AND NEW.status != 'posted' THEN
            -- Remove ledger entries when unposting
            DELETE al FROM accounting_ledger al
            INNER JOIN expense_voucher_lines evl ON al.reference_id = evl.id
            WHERE al.reference_table = 'expense_voucher_lines' 
            AND evl.expense_voucher_id = NEW.id;
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_before_expense_voucher_insert` BEFORE INSERT ON `expense_voucher` FOR EACH ROW BEGIN
    -- Generate voucher number if not provided
    IF NEW.voucher_number IS NULL OR NEW.voucher_number = '' THEN
        SET @next_num = (SELECT COALESCE(MAX(CAST(SUBSTRING(voucher_number, 4) AS UNSIGNED)), 0) + 1 
                        FROM expense_voucher 
                        WHERE voucher_number LIKE CONCAT('EV-', YEAR(NEW.voucher_date), '%'));
        SET NEW.voucher_number = CONCAT('EV-', YEAR(NEW.voucher_date), '-', LPAD(@next_num, 4, '0'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `expense_vouchers`
--

CREATE TABLE `expense_vouchers` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `account_id` int(11) NOT NULL,
  `payment_mode` varchar(100) NOT NULL,
  `bank` varchar(100) DEFAULT NULL,
  `slip_check_no` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_voucher_lines`
--

CREATE TABLE `expense_voucher_lines` (
  `id` int(11) NOT NULL,
  `expense_voucher_id` int(11) NOT NULL,
  `line_number` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `sub_account_id` int(11) DEFAULT NULL,
  `account_name_id` int(11) DEFAULT NULL,
  `cost_center_id` int(11) DEFAULT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `mode_of_payment` varchar(255) DEFAULT NULL,
  `expense_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `slip_check_no` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_voucher_lines`
--

INSERT INTO `expense_voucher_lines` (`id`, `expense_voucher_id`, `line_number`, `account_id`, `sub_account_id`, `account_name_id`, `cost_center_id`, `bank_account_id`, `mode_of_payment`, `expense_amount`, `slip_check_no`, `description`) VALUES
(1, 1, 1, 15, NULL, NULL, NULL, 1, '5', 400.00, NULL, NULL),
(2, 3, 1, 59, NULL, NULL, 25, NULL, '7', 1000.00, NULL, NULL),
(3, 2, 1, 58, NULL, NULL, 10, NULL, '7', 90000.00, NULL, NULL),
(4, 2, 2, 62, NULL, NULL, 33, 1, '5', 20000.00, NULL, NULL);

--
-- Triggers `expense_voucher_lines`
--
DELIMITER $$
CREATE TRIGGER `trg_after_expense_lines_delete` AFTER DELETE ON `expense_voucher_lines` FOR EACH ROW BEGIN
    -- Remove related ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'expense_voucher_lines' AND reference_id = OLD.id;
    
    -- Update total amount in expense voucher header
    UPDATE expense_voucher 
    SET total_amount = (
        SELECT COALESCE(SUM(expense_amount), 0)
        FROM expense_voucher_lines 
        WHERE expense_voucher_id = OLD.expense_voucher_id
    )
    WHERE id = OLD.expense_voucher_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_expense_lines_insert` AFTER INSERT ON `expense_voucher_lines` FOR EACH ROW BEGIN
    DECLARE v_voucher_date DATE;
    DECLARE v_voucher_number VARCHAR(50);
    DECLARE v_status VARCHAR(20);
    
    -- Get voucher details
    SELECT voucher_date, voucher_number, status 
    INTO v_voucher_date, v_voucher_number, v_status
    FROM expense_voucher 
    WHERE id = NEW.expense_voucher_id;
    
    -- Only create ledger entries for posted vouchers
    IF v_status = 'posted' THEN
        -- Debit: Expense Account (increasing expense)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Expense Voucher', 'expense_voucher_lines', NEW.id, NEW.account_id,
            v_voucher_date, 
            CONCAT('EV-', v_voucher_number, ' Line ', NEW.line_number, 
                   CASE WHEN NEW.description IS NOT NULL THEN CONCAT(' - ', NEW.description) ELSE '' END),
            NEW.expense_amount, 0
        );
        
        -- Credit: Payment Method Account (cash/bank account being reduced)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Expense Voucher', 'expense_voucher_lines', NEW.id, 
            CASE 
                WHEN NEW.bank_account_id IS NOT NULL THEN (
                    SELECT account_id FROM bank_accounts WHERE id = NEW.bank_account_id
                )
                ELSE NEW.mode_of_payment
            END,
            v_voucher_date,
            CONCAT('EV-', v_voucher_number, ' Line ', NEW.line_number,
                   CASE WHEN NEW.description IS NOT NULL THEN CONCAT(' - ', NEW.description) ELSE '' END),
            0, NEW.expense_amount
        );
    END IF;
    
    -- Update total amount in expense voucher header
    UPDATE expense_voucher 
    SET total_amount = (
        SELECT COALESCE(SUM(expense_amount), 0)
        FROM expense_voucher_lines 
        WHERE expense_voucher_id = NEW.expense_voucher_id
    )
    WHERE id = NEW.expense_voucher_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_expense_lines_update` AFTER UPDATE ON `expense_voucher_lines` FOR EACH ROW BEGIN
    DECLARE v_voucher_date DATE;
    DECLARE v_voucher_number VARCHAR(50);
    DECLARE v_status VARCHAR(20);
    
    -- Get voucher details
    SELECT voucher_date, voucher_number, status 
    INTO v_voucher_date, v_voucher_number, v_status
    FROM expense_voucher 
    WHERE id = NEW.expense_voucher_id;
    
    -- Delete old ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'expense_voucher_lines' AND reference_id = NEW.id;
    
    -- Only create new ledger entries for posted vouchers
    IF v_status = 'posted' THEN
        -- Debit: Expense Account (increasing expense)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Expense Voucher', 'expense_voucher_lines', NEW.id, NEW.account_id,
            v_voucher_date,
            CONCAT('EV-', v_voucher_number, ' Line ', NEW.line_number,
                   CASE WHEN NEW.description IS NOT NULL THEN CONCAT(' - ', NEW.description) ELSE '' END),
            NEW.expense_amount, 0
        );
        
        -- Credit: Payment Method Account (cash/bank account being reduced)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Expense Voucher', 'expense_voucher_lines', NEW.id, 
            CASE 
                WHEN NEW.bank_account_id IS NOT NULL THEN (
                    SELECT account_id FROM bank_accounts WHERE id = NEW.bank_account_id
                )
                ELSE NEW.mode_of_payment
            END,
            v_voucher_date,
            CONCAT('EV-', v_voucher_number, ' Line ', NEW.line_number,
                   CASE WHEN NEW.description IS NOT NULL THEN CONCAT(' - ', NEW.description) ELSE '' END),
            0, NEW.expense_amount
        );
    END IF;
    
    -- Update total amount in expense voucher header
    UPDATE expense_voucher 
    SET total_amount = (
        SELECT COALESCE(SUM(expense_amount), 0)
        FROM expense_voucher_lines 
        WHERE expense_voucher_id = NEW.expense_voucher_id
    )
    WHERE id = NEW.expense_voucher_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `finished_product`
--

CREATE TABLE `finished_product` (
  `id` int(11) NOT NULL,
  `production_assembly_id` int(11) NOT NULL,
  `job_no` varchar(50) NOT NULL,
  `product_code` varchar(50) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `production_unit` varchar(50) NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `followups`
--

CREATE TABLE `followups` (
  `id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `followup_date` date NOT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `account_manager` varchar(100) DEFAULT NULL,
  `job_no` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `followups`
--

INSERT INTO `followups` (`id`, `lead_id`, `followup_date`, `event_type`, `account_manager`, `job_no`, `remarks`, `created_at`) VALUES
(3, 2, '2025-06-12', 'WhatsApp', 'Ayesha Mir', 'JOB-002', 'Sent plot map and payment terms.', '2025-06-23 05:16:23'),
(4, 2, '2025-06-13', 'Meeting', 'Ayesha Mir', 'JOB-002', 'Met at office, showed documents.', '2025-06-23 05:16:23'),
(5, 3, '2025-06-13', 'Call', 'Kamran Siddiqui', 'JOB-003', 'Discussed selling options.', '2025-06-23 05:16:23'),
(6, 3, '2025-06-14', 'Site Visit', 'Kamran Siddiqui', 'JOB-003', 'Visited house with team.', '2025-06-23 05:16:23'),
(7, 4, '2025-06-14', 'Meeting', 'Rida Khan', 'JOB-004', 'Client wants 3-bedroom apartment.', '2025-06-23 05:16:23'),
(8, 4, '2025-06-15', 'Follow-up Call', 'Rida Khan', 'JOB-004', 'Confirmed budget range.', '2025-06-23 05:16:23'),
(9, 5, '2025-06-15', 'Call', 'Imran Bashir', 'JOB-005', 'Discussed documentation process.', '2025-06-23 05:16:23'),
(10, 5, '2025-06-16', 'WhatsApp', 'Imran Bashir', 'JOB-005', 'Shared location pin.', '2025-06-23 05:16:23'),
(11, 6, '2025-06-16', 'Call', 'Fariha Zahid', 'JOB-006', 'Looking for urgent rental.', '2025-06-23 05:16:23'),
(12, 6, '2025-06-17', 'Visit', 'Fariha Zahid', 'JOB-006', 'Visited two houses.', '2025-06-23 05:16:23'),
(13, 7, '2025-06-17', 'Email', 'Bilal Arif', 'JOB-007', 'Sent brochure.', '2025-06-23 05:16:23'),
(14, 7, '2025-06-18', 'Call', 'Bilal Arif', 'JOB-007', 'Client prefers top floor.', '2025-06-23 05:16:23'),
(15, 8, '2025-06-18', 'Meeting', 'Zara Sheikh', 'JOB-008', 'Discussed development timeline.', '2025-06-23 05:16:23'),
(16, 8, '2025-06-19', 'Call', 'Zara Sheikh', 'JOB-008', 'Client will visit next week.', '2025-06-23 05:16:23'),
(17, 9, '2025-06-19', 'Call', 'Junaid Anwar', 'JOB-009', 'Wants fast transaction.', '2025-06-23 05:16:23'),
(18, 9, '2025-06-20', 'Site Visit', 'Junaid Anwar', 'JOB-009', 'Inspection completed.', '2025-06-23 05:16:23'),
(19, 10, '2025-06-20', 'WhatsApp', 'Rabia Malik', 'JOB-010', 'Shared rent agreement draft.', '2025-06-23 05:16:23'),
(20, 10, '2025-06-21', 'Call', 'Rabia Malik', 'JOB-010', 'Client wants to negotiate rent.', '2025-06-23 05:16:23'),
(21, 1, '2025-06-05', 'Call', 'John', '1002', 'Good job', '2025-06-23 05:18:42'),
(26, 1, '2025-08-20', 'Call', 'John', '1001', NULL, '2025-08-20 07:47:14'),
(27, 4, '2025-08-26', 'Call', 'John', '1002', NULL, '2025-08-26 07:42:47'),
(28, 4, '2025-08-26', 'Call', 'John', '1002', NULL, '2025-08-26 07:43:14'),
(29, 6, '2025-09-02', 'Call', 'John', '1001', NULL, '2025-09-02 07:50:27'),
(30, 3, '2025-09-08', 'Call', 'John', '1001', NULL, '2025-09-08 06:25:48'),
(31, 3, '2025-09-08', 'Call', 'Jane', '1002', NULL, '2025-09-08 06:26:13'),
(32, 7, '2025-09-08', 'Call', 'John', '1002', NULL, '2025-09-08 06:27:57');

-- --------------------------------------------------------

--
-- Table structure for table `gate_passes`
--

CREATE TABLE `gate_passes` (
  `id` int(11) NOT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `gatepass_date` date DEFAULT NULL,
  `purchase_order_no` varchar(255) DEFAULT NULL,
  `po_date` date DEFAULT NULL,
  `delivery_challan_no` varchar(255) DEFAULT NULL,
  `challan_date` date DEFAULT NULL,
  `job_description` text DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `vendor_address` text DEFAULT NULL,
  `vendor_phone` varchar(50) DEFAULT NULL,
  `inventory_location` varchar(255) DEFAULT NULL,
  `supply_time` varchar(255) DEFAULT NULL,
  `vehicle_description` text DEFAULT NULL,
  `driver_name` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gate_passes`
--

INSERT INTO `gate_passes` (`id`, `serial_no`, `gatepass_date`, `purchase_order_no`, `po_date`, `delivery_challan_no`, `challan_date`, `job_description`, `vendor_id`, `vendor_address`, `vendor_phone`, `inventory_location`, `supply_time`, `vehicle_description`, `driver_name`, `remarks`) VALUES
(1, 'GP-001', '2025-07-20', 'PO-101', '2025-07-15', 'DC-501', '2025-07-18', 'Delivery of electrical items', 1, 'Karachi, Pakistan', '0312-1234567', 'Warehouse A', '10:00 AM', 'Suzuki Bolan - White', 'Ali Khan', 'Urgent delivery'),
(2, 'GP-002', '2025-07-21', 'PO-102', '2025-07-16', 'DC-502', '2025-07-19', 'Supply of stationery', 2, 'Lahore, Pakistan', '0321-9876543', 'Main Office', '11:30 AM', 'Toyota Hiace - Silver', 'Bilal Ahmed', 'Handle with care'),
(3, 'GP-003', '2025-07-22', 'PO-103', '2025-07-17', 'DC-503', '2025-07-20', 'Computer peripherals delivery', 3, 'Islamabad, Pakistan', '0300-1112233', 'IT Department', '01:00 PM', 'Honda BR-V - Black', 'Usman Tariq', 'Double check items'),
(4, 'GP-004', '2025-07-23', 'PO-104', '2025-07-18', 'DC-504', '2025-07-21', 'Tools and machinery', 4, 'Faisalabad, Pakistan', '0345-7766554', 'Workshop', '02:15 PM', 'Truck - Blue', 'Shahbaz Ali', 'Safety equipment included'),
(5, 'GP-005', '2025-07-24', 'PO-105', '2025-07-19', 'DC-505', '2025-07-22', 'Furniture delivery', 5, 'Multan, Pakistan', '0333-8899000', 'Admin Block', '03:45 PM', 'Pickup - White', 'Fahad Iqbal', 'Fragile items'),
(6, NULL, '2025-07-01', '', '2025-07-22', '', '2025-07-21', '', 12, 'North karachi', '03063929876', 'Main Store', '', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `gate_pass_items`
--

CREATE TABLE `gate_pass_items` (
  `id` int(11) NOT NULL,
  `gatepass_id` int(11) DEFAULT NULL,
  `product_code` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `packing` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gate_pass_items`
--

INSERT INTO `gate_pass_items` (`id`, `gatepass_id`, `product_code`, `description`, `quantity`, `unit`, `packing`) VALUES
(1, 1, 'EL-101', 'LED Bulbs 12W', 100.00, 'pcs', 'Box of 20'),
(2, 1, 'EL-102', 'Switch Boards', 50.00, 'pcs', 'Box of 10'),
(3, 2, 'ST-201', 'A4 Paper Ream', 200.00, 'reams', 'Carton of 5'),
(4, 3, 'CP-301', 'Wireless Mouse', 75.00, 'pcs', 'Box of 25'),
(5, 4, 'TM-401', 'Drill Machine', 10.00, 'pcs', 'Box of 1'),
(6, 5, 'FR-501', 'Office Chairs', 20.00, 'pcs', 'Plastic wrapped'),
(7, 5, 'FR-502', 'Wooden Tables', 10.00, 'pcs', 'Bubble wrap'),
(8, 2, 'ST-202', 'Markers Set', 30.00, 'sets', 'Box of 15'),
(9, 3, 'CP-302', 'Keyboards', 60.00, 'pcs', 'Box of 20'),
(10, 4, 'TM-402', 'Wrench Set', 15.00, 'sets', 'Box of 5'),
(11, 6, '19', 'Popup', 2.00, 'Pcs', '24'),
(12, 6, '20', 'Bleach Small', 6.00, 'Jar', '6');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `location_from` varchar(100) NOT NULL,
  `location_to` varchar(100) DEFAULT NULL,
  `adjustment_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quality` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `rate` decimal(15,2) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `bill_no` varchar(50) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `customer_id` varchar(20) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `primary_cell_no` varchar(20) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `transport` varchar(100) DEFAULT NULL,
  `warehouse` varchar(100) DEFAULT NULL,
  `salesman` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_bill` decimal(12,2) DEFAULT NULL,
  `global_discount` decimal(10,2) DEFAULT NULL,
  `freight` decimal(10,2) DEFAULT NULL,
  `final_amount` decimal(12,2) DEFAULT NULL,
  `received` decimal(12,2) DEFAULT NULL,
  `balance` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `packing` int(11) DEFAULT NULL,
  `ctn` int(11) DEFAULT NULL,
  `doz` int(11) DEFAULT NULL,
  `pcs` int(11) DEFAULT NULL,
  `fu` int(11) DEFAULT NULL,
  `tp` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `whole_sale_price` decimal(10,2) DEFAULT NULL,
  `disc_percentage` decimal(5,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `discount_rs` decimal(10,2) DEFAULT NULL,
  `net_value` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `qty` decimal(10,2) DEFAULT 1.00,
  `sales_tax_rate` decimal(5,2) DEFAULT 0.00,
  `sales_tax_amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `product_name`, `unit`, `description`, `packing`, `ctn`, `doz`, `pcs`, `fu`, `tp`, `amount`, `whole_sale_price`, `disc_percentage`, `discount`, `discount_rs`, `net_value`, `created_at`, `qty`, `sales_tax_rate`, `sales_tax_amount`) VALUES
(37, 9, 1, 'Wooden Office Chair', 'Pcs', '', NULL, NULL, NULL, NULL, NULL, 12000.00, 48000.00, NULL, 0.00, 0.00, NULL, 56640.00, '2025-08-06 07:49:36', 4.00, 0.00, 0.00),
(38, 10, 1, 'Wooden Office Chair', 'Pcs', '', NULL, NULL, NULL, NULL, NULL, 12000.00, 48000.00, NULL, 0.00, 0.00, NULL, 56640.00, '2025-08-06 07:49:41', 4.00, 0.00, 0.00),
(39, 11, 1, 'Wooden Office Chair', 'Pcs', NULL, 0, 0, 0, 1, 0, 12000.00, 12000.00, 12000.00, 0.00, 0.00, 0.00, 12000.00, '2025-08-15 11:01:02', 1.00, 0.00, 0.00),
(40, 12, 19, 'Popup', '0', NULL, NULL, NULL, NULL, 3, NULL, 200.00, 14400.00, NULL, NULL, NULL, NULL, 14400.00, '2025-08-19 22:01:03', 1.00, 0.00, 0.00),
(41, 12, 20, 'Bleach Small', '0', NULL, NULL, NULL, NULL, 2, NULL, 100.00, 200.00, NULL, NULL, NULL, NULL, 200.00, '2025-08-19 22:01:03', 1.00, 0.00, 0.00),
(42, 13, 19, 'Popup', '0', NULL, NULL, NULL, NULL, 3, NULL, 200.00, 14400.00, NULL, NULL, NULL, NULL, 14400.00, '2025-08-19 22:06:32', 1.00, 0.00, 0.00),
(43, 13, 20, 'Bleach Small', '0', NULL, NULL, NULL, NULL, 2, NULL, 100.00, 200.00, NULL, NULL, NULL, NULL, 200.00, '2025-08-19 22:06:32', 1.00, 0.00, 0.00),
(44, 14, 19, 'Popup', '0', NULL, NULL, NULL, NULL, 3, NULL, 200.00, 14400.00, NULL, NULL, NULL, NULL, 14400.00, '2025-08-19 22:10:19', 1.00, 0.00, 0.00),
(45, 14, 20, 'Bleach Small', '0', NULL, NULL, NULL, NULL, 2, NULL, 100.00, 200.00, NULL, NULL, NULL, NULL, 200.00, '2025-08-19 22:10:19', 1.00, 0.00, 0.00),
(46, 15, 19, 'Popup', '0', NULL, NULL, NULL, NULL, 3, NULL, 200.00, 14400.00, NULL, NULL, NULL, NULL, 14400.00, '2025-08-19 22:12:38', 1.00, 0.00, 0.00),
(47, 15, 20, 'Bleach Small', '0', NULL, NULL, NULL, NULL, 2, NULL, 100.00, 200.00, NULL, NULL, NULL, NULL, 200.00, '2025-08-19 22:12:38', 1.00, 0.00, 0.00),
(49, 17, 7, 'Plastic Legs Caps', 'Pcs', NULL, 0, 0, 0, 100, 0, 1000.00, 90000.00, 1000.00, 10.00, 0.00, 0.00, 90000.00, '2025-08-20 07:28:31', 1.00, 0.00, 0.00),
(50, 18, 9, 'Testing', 'Pcs', NULL, 24, 0, 0, 20, 0, 500.00, 10000.00, 500.00, 0.00, 0.00, 0.00, 10000.00, '2025-08-20 07:49:40', 1.00, 0.00, 0.00),
(51, 19, 9, 'Testing', 'Pcs', NULL, 24, 0, 0, 6, 0, 500.00, 3000.00, 500.00, 0.00, 0.00, 0.00, 3000.00, '2025-08-20 08:28:07', 1.00, 0.00, 0.00),
(52, 20, 19, 'Popup', '0', NULL, NULL, NULL, NULL, 3, NULL, 200.00, 14400.00, NULL, NULL, NULL, NULL, 14400.00, '2025-09-08 07:11:25', 1.00, 0.00, 0.00),
(53, 20, 20, 'Bleach Small', '0', NULL, NULL, NULL, NULL, 2, NULL, 100.00, 200.00, NULL, NULL, NULL, NULL, 200.00, '2025-09-08 07:11:25', 1.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `journal_voucher`
--

CREATE TABLE `journal_voucher` (
  `id` int(11) NOT NULL,
  `voucher_number` varchar(50) NOT NULL,
  `voucher_date` date NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','posted','cancelled') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `journal_voucher`
--
DELIMITER $$
CREATE TRIGGER `trg_after_journal_voucher_delete` AFTER DELETE ON `journal_voucher` FOR EACH ROW BEGIN
    -- Remove all related ledger entries (lines will be deleted by CASCADE)
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'journal_voucher_lines' 
    AND reference_id IN (
        SELECT id FROM journal_voucher_lines WHERE journal_voucher_id = OLD.id
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_journal_voucher_update` AFTER UPDATE ON `journal_voucher` FOR EACH ROW BEGIN
    -- Handle status changes
    IF OLD.status != NEW.status THEN
        IF NEW.status = 'posted' THEN
            -- Validate double entry balance before posting
            SET @total_debits = (SELECT COALESCE(SUM(debit_amount), 0) FROM journal_voucher_lines WHERE journal_voucher_id = NEW.id);
            SET @total_credits = (SELECT COALESCE(SUM(credit_amount), 0) FROM journal_voucher_lines WHERE journal_voucher_id = NEW.id);
            
            IF @total_debits != @total_credits THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal Voucher debits and credits must be equal before posting';
            END IF;
            
            -- Create ledger entries for all lines when posting
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            )
            SELECT 
                'Journal Voucher', 'journal_voucher_lines', jvl.id, jvl.account_id,
                NEW.voucher_date,
                CONCAT('JV-', NEW.voucher_number, ' Line ', jvl.line_number,
                       CASE WHEN jvl.description IS NOT NULL THEN CONCAT(' - ', jvl.description) ELSE '' END),
                jvl.debit_amount, jvl.credit_amount
            FROM journal_voucher_lines jvl
            WHERE jvl.journal_voucher_id = NEW.id
            AND (jvl.debit_amount > 0 OR jvl.credit_amount > 0);
            
        ELSEIF OLD.status = 'posted' AND NEW.status != 'posted' THEN
            -- Remove ledger entries when unposting
            DELETE al FROM accounting_ledger al
            INNER JOIN journal_voucher_lines jvl ON al.reference_id = jvl.id
            WHERE al.reference_table = 'journal_voucher_lines' 
            AND jvl.journal_voucher_id = NEW.id;
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_before_journal_voucher_insert` BEFORE INSERT ON `journal_voucher` FOR EACH ROW BEGIN
    -- Generate voucher number if not provided
    IF NEW.voucher_number IS NULL OR NEW.voucher_number = '' THEN
        SET @next_num = (SELECT COALESCE(MAX(CAST(SUBSTRING(voucher_number, 4) AS UNSIGNED)), 0) + 1 
                        FROM journal_voucher 
                        WHERE voucher_number LIKE CONCAT('JV-', YEAR(NEW.voucher_date), '%'));
        SET NEW.voucher_number = CONCAT('JV-', YEAR(NEW.voucher_date), '-', LPAD(@next_num, 4, '0'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `journal_voucher_lines`
--

CREATE TABLE `journal_voucher_lines` (
  `id` int(11) NOT NULL,
  `journal_voucher_id` int(11) NOT NULL,
  `line_number` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `sub_account_id` int(11) DEFAULT NULL,
  `account_name_id` int(11) DEFAULT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `mode_of_payment` varchar(255) DEFAULT NULL,
  `debit_amount` decimal(15,2) DEFAULT 0.00,
  `credit_amount` decimal(15,2) DEFAULT 0.00,
  `slip_check_no` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `journal_voucher_lines`
--
DELIMITER $$
CREATE TRIGGER `trg_after_journal_lines_delete` AFTER DELETE ON `journal_voucher_lines` FOR EACH ROW BEGIN
    -- Remove related ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'journal_voucher_lines' AND reference_id = OLD.id;
    
    -- Update total amount in journal voucher header
    UPDATE journal_voucher 
    SET total_amount = (
        SELECT GREATEST(
            COALESCE(SUM(debit_amount), 0),
            COALESCE(SUM(credit_amount), 0)
        )
        FROM journal_voucher_lines 
        WHERE journal_voucher_id = OLD.journal_voucher_id
    )
    WHERE id = OLD.journal_voucher_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_journal_lines_insert` AFTER INSERT ON `journal_voucher_lines` FOR EACH ROW BEGIN
    DECLARE v_voucher_date DATE;
    DECLARE v_voucher_number VARCHAR(50);
    DECLARE v_status VARCHAR(20);
    
    -- Get voucher details
    SELECT voucher_date, voucher_number, status 
    INTO v_voucher_date, v_voucher_number, v_status
    FROM journal_voucher 
    WHERE id = NEW.journal_voucher_id;
    
    -- Only create ledger entries for posted vouchers
    IF v_status = 'posted' THEN
        -- Insert debit entry if debit_amount > 0
        IF NEW.debit_amount > 0 THEN
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Journal Voucher', 'journal_voucher_lines', NEW.id, NEW.account_id,
                v_voucher_date, 
                CONCAT('JV-', v_voucher_number, ' Line ', NEW.line_number, 
                       CASE WHEN NEW.description IS NOT NULL THEN CONCAT(' - ', NEW.description) ELSE '' END),
                NEW.debit_amount, 0
            );
        END IF;
        
        -- Insert credit entry if credit_amount > 0
        IF NEW.credit_amount > 0 THEN
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Journal Voucher', 'journal_voucher_lines', NEW.id, NEW.account_id,
                v_voucher_date,
                CONCAT('JV-', v_voucher_number, ' Line ', NEW.line_number,
                       CASE WHEN NEW.description IS NOT NULL THEN CONCAT(' - ', NEW.description) ELSE '' END),
                0, NEW.credit_amount
            );
        END IF;
    END IF;
    
    -- Update total amount in journal voucher header
    UPDATE journal_voucher 
    SET total_amount = (
        SELECT GREATEST(
            COALESCE(SUM(debit_amount), 0),
            COALESCE(SUM(credit_amount), 0)
        )
        FROM journal_voucher_lines 
        WHERE journal_voucher_id = NEW.journal_voucher_id
    )
    WHERE id = NEW.journal_voucher_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_journal_lines_update` AFTER UPDATE ON `journal_voucher_lines` FOR EACH ROW BEGIN
    DECLARE v_voucher_date DATE;
    DECLARE v_voucher_number VARCHAR(50);
    DECLARE v_status VARCHAR(20);
    
    -- Get voucher details
    SELECT voucher_date, voucher_number, status 
    INTO v_voucher_date, v_voucher_number, v_status
    FROM journal_voucher 
    WHERE id = NEW.journal_voucher_id;
    
    -- Delete old ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'journal_voucher_lines' AND reference_id = NEW.id;
    
    -- Only create new ledger entries for posted vouchers
    IF v_status = 'posted' THEN
        -- Insert debit entry if debit_amount > 0
        IF NEW.debit_amount > 0 THEN
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Journal Voucher', 'journal_voucher_lines', NEW.id, NEW.account_id,
                v_voucher_date,
                CONCAT('JV-', v_voucher_number, ' Line ', NEW.line_number,
                       CASE WHEN NEW.description IS NOT NULL THEN CONCAT(' - ', NEW.description) ELSE '' END),
                NEW.debit_amount, 0
            );
        END IF;
        
        -- Insert credit entry if credit_amount > 0
        IF NEW.credit_amount > 0 THEN
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Journal Voucher', 'journal_voucher_lines', NEW.id, NEW.account_id,
                v_voucher_date,
                CONCAT('JV-', v_voucher_number, ' Line ', NEW.line_number,
                       CASE WHEN NEW.description IS NOT NULL THEN CONCAT(' - ', NEW.description) ELSE '' END),
                0, NEW.credit_amount
            );
        END IF;
    END IF;
    
    -- Update total amount in journal voucher header
    UPDATE journal_voucher 
    SET total_amount = (
        SELECT GREATEST(
            COALESCE(SUM(debit_amount), 0),
            COALESCE(SUM(credit_amount), 0)
        )
        FROM journal_voucher_lines 
        WHERE journal_voucher_id = NEW.journal_voucher_id
    )
    WHERE id = NEW.journal_voucher_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `lead_code` varchar(20) NOT NULL,
  `lead_source` enum('Website','Referral','Social Media','WhatsApp','Other') NOT NULL,
  `lead_date` datetime NOT NULL,
  `contact_name` varchar(100) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `service_type` varchar(50) NOT NULL,
  `lead_status` enum('New','Contacted','Qualified','Proposal Sent') NOT NULL DEFAULT 'New',
  `priority` enum('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `lead_code`, `lead_source`, `lead_date`, `contact_name`, `company_name`, `email`, `whatsapp`, `service_type`, `lead_status`, `priority`, `created_at`, `updated_at`) VALUES
(1, 'LD-2024', 'Website', '2025-08-19 00:00:00', 'Usman Ghori', 'InnovaTech', 'innova.tech213@gmail.com', '+923468918711', 'Social Media Marketing', 'Contacted', 'Medium', '2025-08-19 21:00:26', '2025-08-19 21:00:26'),
(3, 'LD-7191', 'Referral', '2025-08-26 00:00:00', 'Muhammad Zahid Khan', 'Zahid Corp', 'zc@gmail.com', '+92346891871111', 'SEO', 'Contacted', 'High', '2025-08-26 07:36:12', '2025-08-26 07:36:12'),
(4, 'LD-9954', 'Referral', '2025-08-26 00:00:00', 'xyz', 'xyz', 'xyz@gmail.com', '+923468918711', 'Social Media Marketing', 'New', 'High', '2025-08-26 07:40:59', '2025-08-26 07:40:59'),
(6, 'LD-2787', 'WhatsApp', '2025-09-02 00:00:00', 'Multan', 'Sultan Co', 'Sultan@gmail.com', '+9232112345678', 'Social Media Marketing', 'New', 'High', '2025-09-02 07:46:54', '2025-09-02 07:46:54'),
(7, 'LD-6280', 'Website', '2025-09-08 00:00:00', 'Iqbal Ansari', 'AWAM Ansari', 'ansari@gmail.com', '', 'SEO', 'New', 'Medium', '2025-09-08 06:27:33', '2025-09-08 06:27:33');

-- --------------------------------------------------------

--
-- Table structure for table `machines`
--

CREATE TABLE `machines` (
  `id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Maintenance','Inactive') DEFAULT 'Active',
  `capacity` decimal(15,3) DEFAULT NULL,
  `capacity_unit` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `machines`
--

INSERT INTO `machines` (`id`, `warehouse_id`, `name`, `code`, `description`, `status`, `capacity`, `capacity_unit`) VALUES
(2, 4, 'Production Machine#1', 'PM1', NULL, 'Active', 100.000, 'Kg'),
(3, 4, 'Production Machine#2', 'PM2', NULL, 'Active', 10.000, 'Kg'),
(4, 4, 'Production Machine#3', 'PM3', NULL, 'Active', 120.000, 'Kg'),
(5, 5, 'Hybrid Machine#1', 'HM1', NULL, 'Active', 90.000, 'Kg'),
(6, 5, 'Mixer Machine#1', 'MM1', NULL, 'Active', 50.000, 'L');

-- --------------------------------------------------------

--
-- Table structure for table `machine_types`
--

CREATE TABLE `machine_types` (
  `machine_type_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `material_bom`
--

CREATE TABLE `material_bom` (
  `id` int(11) NOT NULL,
  `bom_type` varchar(100) NOT NULL,
  `bom_column` varchar(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `material_issue_items`
--

CREATE TABLE `material_issue_items` (
  `id` int(11) NOT NULL,
  `note_id` int(11) NOT NULL,
  `item_code` int(11) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_issue_items`
--

INSERT INTO `material_issue_items` (`id`, `note_id`, `item_code`, `item_name`, `description`, `quantity`, `unit`, `rate`, `amount`) VALUES
(4, 2, 14, 'Kohar Rs 10 Wala ', '', 1.00, 'Sirf', 7.00, 7.00);

-- --------------------------------------------------------

--
-- Table structure for table `material_issue_notes`
--

CREATE TABLE `material_issue_notes` (
  `id` int(11) NOT NULL,
  `serial_number` int(11) NOT NULL,
  `date` date NOT NULL,
  `inventory_location` varchar(100) DEFAULT NULL,
  `requisition_no` varchar(100) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `job_id` int(11) DEFAULT NULL,
  `debit_account_number` varchar(100) DEFAULT NULL,
  `debit_account_description` text DEFAULT NULL,
  `credit_account_number` varchar(100) DEFAULT NULL,
  `credit_account_description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_issue_notes`
--

INSERT INTO `material_issue_notes` (`id`, `serial_number`, `date`, `inventory_location`, `requisition_no`, `employee_id`, `job_id`, `debit_account_number`, `debit_account_description`, `credit_account_number`, `credit_account_description`, `created_at`) VALUES
(2, 5, '2025-07-01', '3', '54', 3, 201, '201', '201', '201', 'sales', '2025-07-01 10:48:14');

-- --------------------------------------------------------

--
-- Table structure for table `material_item`
--

CREATE TABLE `material_item` (
  `id` int(11) NOT NULL,
  `job_no` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `process` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `material_unit`
--

CREATE TABLE `material_unit` (
  `id` int(11) NOT NULL,
  `unit` varchar(50) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_voucher`
--

CREATE TABLE `payment_voucher` (
  `id` int(11) NOT NULL,
  `voucher_number` varchar(50) NOT NULL,
  `voucher_date` date NOT NULL,
  `payee_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `bill_no` varchar(100) DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','paid','cancelled') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_voucher`
--

INSERT INTO `payment_voucher` (`id`, `voucher_number`, `voucher_date`, `payee_account_id`, `amount`, `payment_method_id`, `bank_account_id`, `vendor_id`, `bill_no`, `reference`, `description`, `status`, `created_by`, `approved_by`, `created_at`, `updated_at`) VALUES
(2, 'PV-250819-001', '2025-08-19', 14, 5000.00, 7, NULL, 60, '1', '', '', 'pending', 1, NULL, '2025-08-19 16:30:51', '2025-08-19 16:30:51'),
(6, 'PV-250819-002', '2025-08-19', 14, 100.00, 7, NULL, 60, '2', '', '', 'pending', 1, NULL, '2025-08-19 16:56:38', '2025-08-19 16:56:38'),
(7, 'PV-250819-003', '2025-08-19', 14, 2000.00, 7, NULL, 60, '1', '', '', 'pending', 1, NULL, '2025-08-19 16:57:46', '2025-08-19 16:57:46');

--
-- Triggers `payment_voucher`
--
DELIMITER $$
CREATE TRIGGER `trg_after_payment_voucher_delete` AFTER DELETE ON `payment_voucher` FOR EACH ROW BEGIN
    -- Remove all related ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'payment_voucher' AND reference_id = OLD.id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_payment_voucher_insert` AFTER INSERT ON `payment_voucher` FOR EACH ROW BEGIN
    -- Credit: Payee Account (the account being paid)
    INSERT INTO accounting_ledger (
        transaction_type, reference_table, reference_id, account_id, date, description, debit, credit
    ) VALUES (
        'Payment Voucher', 'payment_voucher', NEW.id, NEW.payee_account_id, NEW.voucher_date, 
        CONCAT('Payment made - ', NEW.voucher_number), 0, NEW.amount
    );
    
    -- Debit: Payment Method Account (cash/bank account being used)
    INSERT INTO accounting_ledger (
        transaction_type, reference_table, reference_id, account_id, date, description, debit, credit
    ) VALUES (
        'Payment Voucher', 'payment_voucher', NEW.id, NEW.payment_method_id, NEW.voucher_date, 
        CONCAT('Payment made - ', NEW.voucher_number), NEW.amount, 0
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_payment_voucher_update` AFTER UPDATE ON `payment_voucher` FOR EACH ROW BEGIN
    -- Delete old ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'payment_voucher' AND reference_id = NEW.id;
    
    -- Insert new ledger entries with updated values
    -- Credit: Payee Account
    INSERT INTO accounting_ledger (
        transaction_type, reference_table, reference_id, account_id, date, description, debit, credit
    ) VALUES (
        'Payment Voucher', 'payment_voucher', NEW.id, NEW.payee_account_id, NEW.voucher_date, 
        CONCAT('Payment made - ', NEW.voucher_number), 0, NEW.amount
    );
    
    -- Debit: Payment Method Account
    INSERT INTO accounting_ledger (
        transaction_type, reference_table, reference_id, account_id, date, description, debit, credit
    ) VALUES (
        'Payment Voucher', 'payment_voucher', NEW.id, NEW.payment_method_id, NEW.voucher_date, 
        CONCAT('Payment made - ', NEW.voucher_number), NEW.amount, 0
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_entries`
--

CREATE TABLE `payroll_entries` (
  `id` int(11) NOT NULL,
  `payroll_id` varchar(255) NOT NULL,
  `payroll_date` date NOT NULL,
  `employee_id` int(11) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `total_sales` decimal(10,2) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `total_recovery` decimal(10,2) DEFAULT NULL,
  `recovery_rate` decimal(5,2) DEFAULT NULL,
  `payment_mode` varchar(50) NOT NULL,
  `bank_account` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_entries`
--

INSERT INTO `payroll_entries` (`id`, `payroll_id`, `payroll_date`, `employee_id`, `employee_name`, `payment_type`, `total_sales`, `commission_rate`, `total_recovery`, `recovery_rate`, `payment_mode`, `bank_account`, `description`, `amount`, `created_at`) VALUES
(1, 'PY-20250720-9882', '2025-07-20', 2, 'Fatima Raza', 'Advance', NULL, NULL, NULL, NULL, 'Bank Transfer', 'SBI - XXXX5678', 'gggg', 111.00, '2025-07-20 11:01:54'),
(2, 'PY-20250720-7945', '2025-07-20', 2, 'Fatima Raza', 'Advance', NULL, NULL, NULL, NULL, 'Bank Transfer', 'ICICI - XXXX9012', 'aaaaa', 5000.00, '2025-07-20 11:02:23'),
(3, 'PY-20250720-7945', '2025-07-20', 9, '\r\n                                    Usman Iqbal                                ', 'Daily Wages', NULL, 5.00, NULL, 2.00, 'Online Payment', 'SBI - XXXX5678', 'bbbb', 5000.00, '2025-07-20 11:02:23'),
(4, 'PY-20250720-9693', '2025-07-16', 4, 'Zara Siddiqui', 'Advance Return', NULL, NULL, NULL, NULL, 'Cheque', 'SBI - XXXX5678', 'aaaaa', 100.00, '2025-07-20 11:20:17'),
(5, 'PY-20250720-7854', '2025-07-20', 4, '\r\n                                    Zara Siddiqui                                ', 'Advance', NULL, 5.00, NULL, 2.00, 'Cash', 'SBI - XXXX5678', 'aasasasa', 10000.00, '2025-07-20 11:21:12'),
(6, 'PY-20250820-5373', '2025-08-20', 35, 'Zahid Ghori', 'Advance', NULL, NULL, NULL, NULL, 'Cash', '', 'advance salary', 50000.00, '2025-08-20 11:57:01');

--
-- Triggers `payroll_entries`
--
DELIMITER $$
CREATE TRIGGER `trg_after_payroll_entries_delete` AFTER DELETE ON `payroll_entries` FOR EACH ROW BEGIN
    -- Remove all related ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'payroll_entries' AND reference_id = OLD.id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_payroll_entries_insert` AFTER INSERT ON `payroll_entries` FOR EACH ROW BEGIN
    -- Only create ledger entries if amount > 0
    IF NEW.amount > 0 THEN
        -- Debit: Salaries Expense (increasing expense)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Payroll Entry', 'payroll_entries', NEW.id, 25, -- Salaries Expense
            NEW.payroll_date,
            CONCAT('Payroll - ', NEW.employee_name, ' - ', NEW.payroll_id),
            NEW.amount, 0
        );
        
        -- Credit: Salaries Payable (increasing liability)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Payroll Entry', 'payroll_entries', NEW.id, 28, -- Salaries Payable
            NEW.payroll_date,
            CONCAT('Payroll - ', NEW.employee_name, ' - ', NEW.payroll_id),
            0, NEW.amount
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_payroll_entries_update` AFTER UPDATE ON `payroll_entries` FOR EACH ROW BEGIN
    -- Delete old ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'payroll_entries' AND reference_id = NEW.id;
    
    -- Insert new ledger entries with updated values
    IF NEW.amount > 0 THEN
        -- Debit: Salaries Expense
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Payroll Entry', 'payroll_entries', NEW.id, 25,
            NEW.payroll_date,
            CONCAT('Payroll - ', NEW.employee_name, ' - ', NEW.payroll_id),
            NEW.amount, 0
        );
        
        -- Credit: Salaries Payable
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Payroll Entry', 'payroll_entries', NEW.id, 28,
            NEW.payroll_date,
            CONCAT('Payroll - ', NEW.employee_name, ' - ', NEW.payroll_id),
            0, NEW.amount
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `post_dated_cheques`
--

CREATE TABLE `post_dated_cheques` (
  `id` int(11) NOT NULL,
  `customer_first_name` varchar(100) DEFAULT NULL,
  `customer_last_name` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `deposit_bank` varchar(100) DEFAULT NULL,
  `deposit_date` date DEFAULT NULL,
  `cheque_bank` varchar(100) DEFAULT NULL,
  `cheque_no` varchar(50) DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `status` enum('Pending','Cleared') DEFAULT 'Pending',
  `source_table` enum('payment_voucher','receive_voucher','journal_voucher') DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_dated_cheques`
--

INSERT INTO `post_dated_cheques` (`id`, `customer_first_name`, `customer_last_name`, `address`, `deposit_bank`, `deposit_date`, `cheque_bank`, `cheque_no`, `received_date`, `cheque_date`, `amount`, `status`, `source_table`, `source_id`) VALUES
(1, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(2, 'Nida', 'Rauf', 'Sialkot, Punjab', 'HDFC', '0000-00-00', 'MCB', 'CHK-98765', '2025-06-27', '2025-06-27', 5000.00, '', 'journal_voucher', 0),
(3, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(4, 'Nida', 'Rauf', 'Sialkot, Punjab', 'HDFC', '0000-00-00', 'MCB', 'CHK-98765', '2025-06-27', '2025-06-27', 5000.00, '', 'journal_voucher', 0),
(5, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(6, 'Nida', 'Rauf', 'Sialkot, Punjab', 'HDFC', '0000-00-00', 'MCB', 'CHK-98765', '2025-06-27', '2025-06-27', 5000.00, '', 'journal_voucher', 0),
(7, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(8, 'Nida', 'Rauf', 'Sialkot, Punjab', 'HDFC', '2025-07-17', 'MCB', 'CHK-98765', '2025-06-27', '2025-06-27', 5000.00, '', 'journal_voucher', 0),
(9, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(10, 'Tariq', 'Mehmood', 'Faisalabad, Punjab', 'HDFC', '0000-00-00', '', '', '2025-06-20', '0000-00-00', 2000.00, '', 'payment_voucher', 0),
(11, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(12, 'Tariq', 'Mehmood', 'Faisalabad, Punjab', 'HDFC', '0000-00-00', '', '', '2025-06-20', '0000-00-00', 2000.00, '', 'payment_voucher', 0),
(13, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(14, 'Tariq', 'Mehmood', 'Faisalabad, Punjab', 'HDFC', '0000-00-00', '', '', '2025-06-20', '0000-00-00', 2000.00, '', 'payment_voucher', 0),
(15, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(16, 'Tariq', 'Mehmood', 'Faisalabad, Punjab', 'HDFC', '0000-00-00', '', '', '2025-06-20', '0000-00-00', 2000.00, '', 'payment_voucher', 0),
(17, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(18, 'Tariq', 'Mehmood', 'Faisalabad, Punjab', 'HDFC', '0000-00-00', '', '', '2025-06-20', '0000-00-00', 2000.00, 'Pending', 'payment_voucher', 0),
(19, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(20, 'Tariq', 'Mehmood', 'Faisalabad, Punjab', 'HDFC', '0000-00-00', '', '', '2025-06-20', '0000-00-00', 2000.00, 'Pending', 'payment_voucher', 0),
(21, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(22, 'Tariq', 'Mehmood', 'Faisalabad, Punjab', 'HDFC', '0000-00-00', '', '', '2025-06-20', '0000-00-00', 2000.00, 'Pending', 'payment_voucher', 0),
(23, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(24, 'Tariq', 'Mehmood', 'Faisalabad, Punjab', 'HDFC', '2025-07-03', '', '', '2025-06-20', '0000-00-00', 2000.00, '', 'payment_voucher', 0),
(25, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '2025-07-18', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(26, 'Tariq', 'Mehmood', 'Faisalabad, Punjab', 'HDFC', '0000-00-00', '', '', '2025-06-20', '0000-00-00', 2000.00, 'Pending', 'payment_voucher', 0),
(27, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(28, 'Tariq', 'Mehmood', 'Faisalabad, Punjab', 'HDFC', '0000-00-00', '', '', '2025-06-20', '0000-00-00', 2000.00, 'Pending', 'payment_voucher', 0),
(29, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(30, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(31, 'Bilal', 'Hussain', 'Islamabad, Capital Territory', 'HDFC', '0000-00-00', 'UBL', 'CHK-56789', '2025-06-29', '2025-06-29', 15000.00, 'Pending', 'payment_voucher', 0),
(32, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, 'Pending', 'receive_voucher', 0),
(33, 'Bilal', 'Hussain', 'Islamabad, Capital Territory', 'HDFC', '0000-00-00', 'UBL', 'CHK-56789', '2025-06-29', '2025-06-29', 15000.00, 'Pending', 'payment_voucher', 0),
(34, 'Ayesha', 'Khalid', 'Hyderabad, Sindh', 'HDFC', '0000-00-00', 'HBL', 'CHK-12345', '2025-06-25', '2025-06-25', 10000.00, '', 'receive_voucher', 0);

-- --------------------------------------------------------

--
-- Table structure for table `privileges`
--

CREATE TABLE `privileges` (
  `id` int(11) NOT NULL,
  `privilege_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `process_component`
--

CREATE TABLE `process_component` (
  `id` int(11) NOT NULL,
  `production_assembly_id` int(11) NOT NULL,
  `job_no` varchar(50) NOT NULL,
  `product_id` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_assembly`
--

CREATE TABLE `production_assembly` (
  `id` int(11) NOT NULL,
  `serial_no` int(11) NOT NULL,
  `job_no` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `inventory_location` varchar(100) NOT NULL,
  `employee_reference` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `input_account_to_credited` varchar(100) DEFAULT NULL,
  `P_Outsource_to_party` varchar(100) DEFAULT NULL,
  `labour_cost` decimal(10,2) DEFAULT 0.00,
  `factory_overheads` decimal(10,2) DEFAULT 0.00,
  `material_cost` decimal(10,2) DEFAULT 0.00,
  `outsourcingCharges` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `total_amount_process_components` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_completions`
--

CREATE TABLE `production_completions` (
  `id` int(11) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `machine_id` int(11) DEFAULT NULL,
  `completion_date` date NOT NULL,
  `total_cost` decimal(15,3) DEFAULT 0.000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_completions`
--

INSERT INTO `production_completions` (`id`, `production_order_id`, `warehouse_id`, `machine_id`, `completion_date`, `total_cost`, `created_at`) VALUES
(5, 2, 5, NULL, '2025-08-05', 0.000, '2025-08-05 14:40:23');

--
-- Triggers `production_completions`
--
DELIMITER $$
CREATE TRIGGER `tr_production_completions_after_delete` AFTER DELETE ON `production_completions` FOR EACH ROW BEGIN
    DECLARE total_completed DECIMAL(15,3) DEFAULT 0;
    DECLARE order_qty DECIMAL(15,3) DEFAULT 0;
    
    -- Get the order quantity
    SELECT po.order_qty INTO order_qty
    FROM production_orders po
    WHERE po.id = OLD.production_order_id;
    
    -- Calculate remaining completed quantity from stock_ledger
    SELECT COALESCE(SUM(sl.qty_in), 0) INTO total_completed
    FROM stock_ledger sl
    WHERE sl.production_order_id = OLD.production_order_id
    AND sl.transaction_type = 'Production Completion';
    
    -- Update production_orders status based on remaining completion
    IF total_completed >= order_qty THEN
        UPDATE production_orders 
        SET status = 'Completed'
        WHERE id = OLD.production_order_id;
    ELSEIF total_completed > 0 THEN
        UPDATE production_orders 
        SET status = 'In Progress'
        WHERE id = OLD.production_order_id;
    ELSE
        UPDATE production_orders 
        SET status = 'Planned'
        WHERE id = OLD.production_order_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_production_completions_after_insert` AFTER INSERT ON `production_completions` FOR EACH ROW BEGIN
    DECLARE total_completed DECIMAL(15,3) DEFAULT 0;
    DECLARE order_qty DECIMAL(15,3) DEFAULT 0;
    
    -- Get the order quantity
    SELECT po.order_qty INTO order_qty
    FROM production_orders po
    WHERE po.id = NEW.production_order_id;
    
    -- Calculate total completed quantity from stock_ledger
    SELECT COALESCE(SUM(sl.qty_in), 0) INTO total_completed
    FROM stock_ledger sl
    WHERE sl.production_order_id = NEW.production_order_id
    AND sl.transaction_type = 'Production Completion';
    
    -- Update production_orders status based on completion
    IF total_completed >= order_qty THEN
        UPDATE production_orders 
        SET status = 'Completed'
        WHERE id = NEW.production_order_id;
    ELSEIF total_completed > 0 THEN
        UPDATE production_orders 
        SET status = 'In Progress'
        WHERE id = NEW.production_order_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_production_completions_after_update` AFTER UPDATE ON `production_completions` FOR EACH ROW BEGIN
    DECLARE total_completed DECIMAL(15,3) DEFAULT 0;
    DECLARE order_qty DECIMAL(15,3) DEFAULT 0;
    
    -- Handle both old and new production_order_id in case it changed
    -- Update old production order if changed
    IF OLD.production_order_id != NEW.production_order_id THEN
        -- Get old order quantity
        SELECT po.order_qty INTO order_qty
        FROM production_orders po
        WHERE po.id = OLD.production_order_id;
        
        -- Calculate total completed for old order
        SELECT COALESCE(SUM(sl.qty_in), 0) INTO total_completed
        FROM stock_ledger sl
        WHERE sl.production_order_id = OLD.production_order_id
        AND sl.transaction_type = 'Production Completion';
        
        -- Update old production order status
        IF total_completed >= order_qty THEN
            UPDATE production_orders 
            SET status = 'Completed'
            WHERE id = OLD.production_order_id;
        ELSEIF total_completed > 0 THEN
            UPDATE production_orders 
            SET status = 'In Progress'
            WHERE id = OLD.production_order_id;
        ELSE
            UPDATE production_orders 
            SET status = 'Planned'
            WHERE id = OLD.production_order_id;
        END IF;
    END IF;
    
    -- Update new/current production order
    -- Get the order quantity
    SELECT po.order_qty INTO order_qty
    FROM production_orders po
    WHERE po.id = NEW.production_order_id;
    
    -- Calculate total completed quantity from stock_ledger
    SELECT COALESCE(SUM(sl.qty_in), 0) INTO total_completed
    FROM stock_ledger sl
    WHERE sl.production_order_id = NEW.production_order_id
    AND sl.transaction_type = 'Production Completion';
    
    -- Update production_orders status based on completion
    IF total_completed >= order_qty THEN
        UPDATE production_orders 
        SET status = 'Completed'
        WHERE id = NEW.production_order_id;
    ELSEIF total_completed > 0 THEN
        UPDATE production_orders 
        SET status = 'In Progress'
        WHERE id = NEW.production_order_id;
    ELSE
        UPDATE production_orders 
        SET status = 'Planned'
        WHERE id = NEW.production_order_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `production_completion_items`
--

CREATE TABLE `production_completion_items` (
  `id` int(11) NOT NULL,
  `production_completion_id` int(11) NOT NULL,
  `finished_good_id` int(11) NOT NULL,
  `qty_completed` decimal(15,3) NOT NULL,
  `unit_cost` decimal(15,3) DEFAULT 0.000,
  `packing_id` int(11) DEFAULT NULL,
  `packing_qty` int(11) DEFAULT 1,
  `wastage_qty` decimal(15,3) DEFAULT 0.000,
  `wastage_cost` decimal(15,3) DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_completion_items`
--

INSERT INTO `production_completion_items` (`id`, `production_completion_id`, `finished_good_id`, `qty_completed`, `unit_cost`, `packing_id`, `packing_qty`, `wastage_qty`, `wastage_cost`) VALUES
(8, 5, 1, 1.000, 0.000, NULL, 1, 0.000, 0.000);

--
-- Triggers `production_completion_items`
--
DELIMITER $$
CREATE TRIGGER `tr_production_completion_items_after_delete` AFTER DELETE ON `production_completion_items` FOR EACH ROW BEGIN
    -- Delete related stock ledger entries
    DELETE FROM stock_ledger 
    WHERE reference_table = 'production_completion_items' 
    AND reference_id = OLD.id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_production_completion_items_after_insert` AFTER INSERT ON `production_completion_items` FOR EACH ROW BEGIN
    DECLARE v_warehouse_id INT;
    DECLARE v_machine_id INT;
    DECLARE v_production_order_id INT;
    DECLARE v_completion_date DATE;
    DECLARE v_base_qty DECIMAL(15,3);
    DECLARE v_unit_conversion DECIMAL(10,3) DEFAULT 1.000;
    
    -- Get completion details
    SELECT pc.warehouse_id, pc.machine_id, pc.production_order_id, pc.completion_date
    INTO v_warehouse_id, v_machine_id, v_production_order_id, v_completion_date
    FROM production_completions pc
    WHERE pc.id = NEW.production_completion_id;
    
    -- Get unit conversion if packing is used
    IF NEW.packing_id IS NOT NULL THEN
        SELECT pp.unit_conversion INTO v_unit_conversion
        FROM product_packings pp
        WHERE pp.id = NEW.packing_id;
    END IF;
    
    -- Calculate base quantity
    SET v_base_qty = NEW.qty_completed * v_unit_conversion;
    
    -- Insert Finished Goods entry (Debit - qty_in)
    INSERT INTO stock_ledger (
        product_id, product_packing_id, warehouse_id, machine_id, production_order_id, account_id,
        reference_table, reference_id, qty_in, base_qty_in,
        unit_conversion, unit_cost, total_cost, transaction_type, transaction_date
    ) VALUES (
        NEW.finished_good_id, NEW.packing_id, v_warehouse_id, v_machine_id, v_production_order_id, 
        CASE WHEN NEW.packing_id IS NOT NULL THEN 37 ELSE 36 END,
        'production_completion_items', NEW.id, NEW.qty_completed, v_base_qty,
        v_unit_conversion, NEW.unit_cost, (NEW.qty_completed * NEW.unit_cost), 'Production Completion', v_completion_date
    );
    
    -- Insert WIP entry (Credit - qty_out) if wastage exists
    IF NEW.wastage_qty > 0 THEN
        INSERT INTO stock_ledger (
            product_id, warehouse_id, machine_id, production_order_id, account_id,
            reference_table, reference_id, qty_out, base_qty_out,
            unit_conversion, unit_cost, total_cost, transaction_type, transaction_date
        ) VALUES (
            NEW.finished_good_id, v_warehouse_id, v_machine_id, v_production_order_id, 35,
            'production_completion_items', NEW.id, NEW.wastage_qty, (NEW.wastage_qty * v_unit_conversion),
            v_unit_conversion, NEW.unit_cost, NEW.wastage_cost, 'Production Completion', v_completion_date
        );
    END IF;
    
    -- Credit WIP for raw materials consumed
    INSERT INTO stock_ledger (
        product_id, warehouse_id, machine_id, production_order_id, account_id,
        reference_table, reference_id, qty_out, base_qty_out,
        unit_conversion, unit_cost, total_cost, transaction_type, transaction_date
    )
    SELECT 
        bi.raw_material_id, v_warehouse_id, v_machine_id, v_production_order_id, 35,
        'production_completion_items', NEW.id, 
        (bi.qty_required * v_base_qty), (bi.qty_required * v_base_qty),
        1.000, 0, 0, 'Production Completion', v_completion_date
    FROM production_orders po
    JOIN bom b ON po.product_id = b.product_id AND b.is_active = 1
    JOIN bom_items bi ON b.id = bi.bom_id
    WHERE po.id = v_production_order_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_production_completion_items_after_update` AFTER UPDATE ON `production_completion_items` FOR EACH ROW BEGIN
    DECLARE v_warehouse_id INT;
    DECLARE v_machine_id INT;
    DECLARE v_production_order_id INT;
    DECLARE v_completion_date DATE;
    DECLARE v_base_qty DECIMAL(15,3);
    DECLARE v_unit_conversion DECIMAL(10,3) DEFAULT 1.000;
    
    -- Delete old stock ledger entries
    DELETE FROM stock_ledger 
    WHERE reference_table = 'production_completion_items' 
    AND reference_id = OLD.id;
    
    -- Get completion details
    SELECT pc.warehouse_id, pc.machine_id, pc.production_order_id, pc.completion_date
    INTO v_warehouse_id, v_machine_id, v_production_order_id, v_completion_date
    FROM production_completions pc
    WHERE pc.id = NEW.production_completion_id;
    
    -- Get unit conversion if packing is used
    IF NEW.packing_id IS NOT NULL THEN
        SELECT pp.unit_conversion INTO v_unit_conversion
        FROM product_packings pp
        WHERE pp.id = NEW.packing_id;
    END IF;
    
    -- Calculate base quantity
    SET v_base_qty = NEW.qty_completed * v_unit_conversion;
    
    -- Insert new Finished Goods entry (Debit - qty_in)
    INSERT INTO stock_ledger (
        product_id, product_packing_id, warehouse_id, machine_id, production_order_id, account_id,
        reference_table, reference_id, qty_in, base_qty_in,
        unit_conversion, unit_cost, total_cost, transaction_type, transaction_date
    ) VALUES (
        NEW.finished_good_id, NEW.packing_id, v_warehouse_id, v_machine_id, v_production_order_id,
        CASE WHEN NEW.packing_id IS NOT NULL THEN 37 ELSE 36 END,
        'production_completion_items', NEW.id, NEW.qty_completed, v_base_qty,
        v_unit_conversion, NEW.unit_cost, (NEW.qty_completed * NEW.unit_cost), 'Production Completion', v_completion_date
    );
    
    -- Insert new WIP entry (Credit - qty_out) if wastage exists
    IF NEW.wastage_qty > 0 THEN
        INSERT INTO stock_ledger (
            product_id, warehouse_id, machine_id, production_order_id, account_id,
            reference_table, reference_id, qty_out, base_qty_out,
            unit_conversion, unit_cost, total_cost, transaction_type, transaction_date
        ) VALUES (
            NEW.finished_good_id, v_warehouse_id, v_machine_id, v_production_order_id, 35,
            'production_completion_items', NEW.id, NEW.wastage_qty, (NEW.wastage_qty * v_unit_conversion),
            v_unit_conversion, NEW.unit_cost, NEW.wastage_cost, 'Production Completion', v_completion_date
        );
    END IF;
    
    -- Credit WIP for raw materials consumed
    INSERT INTO stock_ledger (
        product_id, warehouse_id, machine_id, production_order_id, account_id,
        reference_table, reference_id, qty_out, base_qty_out,
        unit_conversion, unit_cost, total_cost, transaction_type, transaction_date
    )
    SELECT 
        bi.raw_material_id, v_warehouse_id, v_machine_id, v_production_order_id, 35,
        'production_completion_items', NEW.id, 
        (bi.qty_required * v_base_qty), (bi.qty_required * v_base_qty),
        1.000, 0, 0, 'Production Completion', v_completion_date
    FROM production_orders po
    JOIN bom b ON po.product_id = b.product_id AND b.is_active = 1
    JOIN bom_items bi ON b.id = bi.bom_id
    WHERE po.id = v_production_order_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `production_expenses`
--

CREATE TABLE `production_expenses` (
  `id` int(11) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `expense_account_id` int(11) NOT NULL,
  `expense_type` enum('Direct','Indirect') DEFAULT 'Direct',
  `amount` decimal(15,2) NOT NULL,
  `reference_table` varchar(100) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_labor_timesheet`
--

CREATE TABLE `production_labor_timesheet` (
  `id` int(11) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `worker_id` int(11) DEFAULT NULL,
  `hours_worked` decimal(10,2) NOT NULL,
  `hourly_rate` decimal(15,2) NOT NULL,
  `labor_cost` decimal(15,2) GENERATED ALWAYS AS (`hours_worked` * `hourly_rate`) STORED,
  `work_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_orders`
--

CREATE TABLE `production_orders` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `machine_id` int(11) DEFAULT NULL,
  `order_qty` decimal(15,3) NOT NULL,
  `status` enum('Planned','In Progress','Completed','Cancelled') DEFAULT 'Planned',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_orders`
--

INSERT INTO `production_orders` (`id`, `product_id`, `bom_id`, `warehouse_id`, `machine_id`, `order_qty`, `status`, `start_date`, `end_date`, `created_at`) VALUES
(2, 1, 3, 5, 5, 1.000, 'In Progress', '2025-08-05', '2025-08-11', '2025-08-04 22:45:29');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `packing` int(11) DEFAULT NULL,
  `description` varchar(800) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `sub_category` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `ctn` varchar(255) DEFAULT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `qrcode` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `purchase` int(11) DEFAULT NULL,
  `pprice` decimal(10,2) DEFAULT NULL,
  `opprice` decimal(10,2) DEFAULT NULL,
  `tp` decimal(10,2) DEFAULT NULL,
  `wholesaleprice` decimal(10,2) DEFAULT NULL,
  `openingqty` int(11) DEFAULT NULL,
  `required_qty` int(11) DEFAULT 0,
  `discount` int(11) DEFAULT NULL,
  `tax` varchar(255) DEFAULT NULL,
  `saletax` int(11) DEFAULT NULL,
  `furthertax` int(11) DEFAULT NULL,
  `ati` int(11) DEFAULT NULL,
  `costingtype` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `mrp` int(11) DEFAULT NULL,
  `max_stock_lvl` int(11) DEFAULT NULL,
  `inventory_account_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `unit`, `packing`, `description`, `category`, `sub_category`, `company`, `ctn`, `barcode`, `qrcode`, `image`, `active`, `purchase`, `pprice`, `opprice`, `tp`, `wholesaleprice`, `openingqty`, `required_qty`, `discount`, `tax`, `saletax`, `furthertax`, `ati`, `costingtype`, `created_at`, `mrp`, `max_stock_lvl`, `inventory_account_id`) VALUES
(1, 'Wooden Office Chair', '1', 6, '', '', '', 'Aziz Ullah', NULL, '', '', NULL, 1, NULL, 1000.00, 1000.00, 5000.00, 0.00, 100, 80, 0, NULL, 0, 0, 0, NULL, '2025-08-04 22:04:51', 12000, NULL, 36),
(2, 'Wooden Plank (Pine Wood)', '1', 4, '', '', '', 'Aziz Ullah', NULL, '', '', NULL, 1, NULL, 800.00, 800.00, 950.00, 0.00, 60, 25, 0, NULL, 0, 0, 0, NULL, '2025-08-04 22:06:09', 0, NULL, 34),
(3, 'Steel Screws', 'Pcs', 0, '', '', NULL, 'Select Company', NULL, '', '', NULL, 1, NULL, 5.00, 0.00, 0.00, 0.00, 0, 0, 0, 'Select Type', 0, 0, 0, NULL, '2025-08-04 22:07:01', 0, NULL, 34),
(4, 'Wood Glue (Strong Bond)', 'L', 0, '', '', NULL, 'Select Company', NULL, '', '', NULL, 1, NULL, 500.00, 0.00, 0.00, 0.00, 0, 0, 0, 'Select Type', 0, 0, 0, NULL, '2025-08-04 22:07:39', 0, NULL, 34),
(5, 'Cushion Foam (Seat)', 'Pcs', 0, '', '', NULL, 'Select Company', NULL, '', '', NULL, 1, NULL, 1200.00, 0.00, 0.00, 0.00, 0, 0, 0, 'Select Type', 0, 0, 0, NULL, '2025-08-04 22:08:20', 0, NULL, 34),
(6, 'Paint / Polish (Brown)', 'L', 0, '', '', NULL, 'Select Company', NULL, '', '', NULL, 1, NULL, 1000.00, 0.00, 0.00, 0.00, 0, 0, 0, 'Select Type', 0, 0, 0, NULL, '2025-08-04 22:08:57', 0, NULL, 34),
(7, 'Plastic Legs Caps', 'Pcs', 0, '', '', NULL, 'Select Company', NULL, '', '', NULL, 1, NULL, 5.00, 0.00, 0.00, 0.00, 0, 0, 0, 'Select Type', 0, 0, 0, NULL, '2025-08-04 22:09:30', 0, NULL, 34),
(8, 'Test', 'Kg', 12, '', '', NULL, 'Select Company', NULL, '', '', NULL, 1, NULL, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 'Select Type', 0, 0, 0, NULL, '2025-08-05 22:11:36', 0, NULL, 36),
(9, 'Testing', 'Pcs', 24, '', '', NULL, 'Select Company', NULL, '', '', NULL, 1, NULL, 4.00, 5.00, 500.00, 450.00, 100, 0, 0, 'Select Type', 0, 0, 0, NULL, '2025-08-15 10:58:18', 600, NULL, 33),
(10, 'Sugar', 'Pcs', 25, '', '', NULL, 'Select Company', NULL, '', '', NULL, 1, NULL, 5.00, 6.00, 100.00, 60.00, 120, 0, 0, 'Select Type', 0, 0, 0, NULL, '2025-08-15 11:30:05', 150, NULL, 33),
(11, 'Black Ink', '1', 0, '', 'kiryana 111', '', 'Aziz Ullah', NULL, '2990350135478918', 'QRmfar40ekK2G', '20250908063925_1748266431213.jpeg', 1, NULL, 100.00, 0.00, 0.00, 0.00, 0, 0, 0, NULL, 0, 0, 0, NULL, '2025-09-08 06:39:25', 0, NULL, 32);

-- --------------------------------------------------------

--
-- Table structure for table `product_packings`
--

CREATE TABLE `product_packings` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `packing_name` varchar(100) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `ctn_pcs` int(11) DEFAULT NULL,
  `base_item_weight` decimal(10,3) DEFAULT 0.000,
  `packing_weight` decimal(10,3) DEFAULT 0.000,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `barcode` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property_types`
--

CREATE TABLE `property_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_types`
--

INSERT INTO `property_types` (`id`, `name`) VALUES
(2, 'House'),
(1, 'Plot');

-- --------------------------------------------------------

--
-- Table structure for table `purchase`
--

CREATE TABLE `purchase` (
  `id` int(11) NOT NULL,
  `serial_no` varchar(50) DEFAULT NULL,
  `vendor_invoice_no` varchar(100) DEFAULT NULL,
  `purchase_order_no` varchar(100) DEFAULT NULL,
  `terms_payment` varchar(50) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `vendor_id` varchar(50) DEFAULT NULL,
  `vendor_name` varchar(100) DEFAULT NULL,
  `vendor_address` text DEFAULT NULL,
  `vendor_phone` varchar(50) DEFAULT NULL,
  `product_data` longtext DEFAULT NULL,
  `grand_total` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase`
--

INSERT INTO `purchase` (`id`, `serial_no`, `vendor_invoice_no`, `purchase_order_no`, `terms_payment`, `invoice_date`, `vendor_id`, `vendor_name`, `vendor_address`, `vendor_phone`, `product_data`, `grand_total`, `created_at`) VALUES
(1, 'PO-687233d1148ad', '', '', '', '2025-07-12', '7', '7', '', '03063929876', '[{\"code\":\"11\",\"name\":\"Naaz Atta 15\",\"description\":\"good product\",\"unit\":\"Kg\",\"quantity\":\"3\",\"rate\":\"970.00\",\"value_excl\":\"2910.00\",\"tax\":\"200\",\"tax_amt\":\"5820.00\",\"value_incl\":\"8730.00\"}]', 0.00, '2025-07-12 10:07:26'),
(2, 'PO-6872341636e4d', '1234', '1234', 'Credit', '2025-07-12', '7', '7', '', '03063929876', '[{\"code\":\"11\",\"name\":\"Naaz Atta 15\",\"description\":\"good product\",\"unit\":\"Kg\",\"quantity\":\"3\",\"rate\":\"970.00\",\"value_excl\":\"2910.00\",\"tax\":\"200\",\"tax_amt\":\"5820.00\",\"value_incl\":\"8730.00\"}]', 0.00, '2025-07-12 10:08:44'),
(3, 'PO-687238141513c', '', '', '', '2025-07-12', '7', 'Hanzala Siddiqui', '', '03063929876', '[{\"code\":\"11\",\"name\":\"Naaz Atta 15\",\"description\":\"good product\",\"unit\":\"Kg\",\"quantity\":\"2\",\"rate\":\"970.00\",\"value_excl\":\"1940.00\",\"tax\":\"200\",\"tax_amt\":\"3880.00\",\"value_incl\":\"5820.00\"}]', 0.00, '2025-07-12 10:25:33'),
(4, 'PO-4', '', '', '', '2025-07-23', '14', 'Imran hashmi', 'North karachi', '03063929876', '[{\"code\":\"13\",\"name\":\"Atta \",\"description\":\"\",\"unit\":\"Kg\",\"quantity\":\"\",\"rate\":\"955.00\",\"value_excl\":\"0.00\",\"tax\":\"0\",\"tax_amt\":\"0.00\",\"value_incl\":\"0.00\"}]', 0.00, '2025-07-23 17:35:10'),
(5, 'PO-4', '', '', '', '2025-07-23', '14', 'Imran hashmi', 'North karachi', '03063929876', '[{\"code\":\"13\",\"name\":\"Atta \",\"description\":\"\",\"unit\":\"Kg\",\"quantity\":\"\",\"rate\":\"955.00\",\"value_excl\":\"0.00\",\"tax\":\"0\",\"tax_amt\":\"0.00\",\"value_incl\":\"0.00\"}]', 0.00, '2025-07-23 17:42:19');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `purchase_number` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_invoice`
--

CREATE TABLE `purchase_invoice` (
  `id` int(11) NOT NULL,
  `bill_no` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `transport_id` int(11) DEFAULT NULL,
  `sale_man_id` int(100) DEFAULT NULL,
  `bilty_no` int(11) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `cell_no` varchar(20) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `purchase_invoice_no` int(11) DEFAULT NULL,
  `inv_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_bill` decimal(10,2) DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `cash_paid` decimal(10,2) DEFAULT NULL,
  `net_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `vendor_name` varchar(255) DEFAULT NULL,
  `account_id` int(11) NOT NULL DEFAULT 19,
  `is_tax` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_invoice`
--

INSERT INTO `purchase_invoice` (`id`, `bill_no`, `vendor_id`, `transport_id`, `sale_man_id`, `bilty_no`, `warehouse_id`, `address`, `cell_no`, `date`, `purchase_invoice_no`, `inv_date`, `remarks`, `total_bill`, `discount_percentage`, `discount_amount`, `cash_paid`, `net_amount`, `created_at`, `vendor_name`, `account_id`, `is_tax`) VALUES
(20, 1, 60, 0, 0, 0, 0, '', '', '2025-08-05', 0, '2025-08-05', '', 9320.00, 0.00, 0.00, 0.00, 9320.00, '2025-08-05 14:11:09', 'Olivia', 19, 0),
(21, 2, 60, 0, 0, 0, 0, '', '', '2025-08-05', 0, '2025-08-05', '', 5596.25, 0.00, 0.00, 0.00, 5596.25, '2025-08-05 15:38:39', 'Olivia', 19, 1),
(22, 3, 60, 0, 0, 52, 0, '', '03326521545', '2025-08-15', 25, '2025-08-15', '', 4513.50, 0.00, 0.00, 0.00, 4513.50, '2025-08-15 11:44:00', 'Rasid &amp; Sons', 19, 0),
(24, 4, 62, 0, 0, 0, 0, '', '', '2025-08-20', 0, '2025-08-20', '', 40000.00, 0.00, 0.00, 0.00, 40000.00, '2025-08-20 07:58:39', 'HAJI SHAHNAWAZ MEMON', 19, 0),
(25, 5, 62, 0, 0, 0, 0, '', '', '2025-08-20', 0, '2025-08-20', '', 4000.00, 0.00, 0.00, 0.00, 4000.00, '2025-08-20 08:01:17', 'HAJI SHAHNAWAZ MEMON', 19, 0),
(26, 6, 62, 0, 0, 0, 0, '', '', '2025-08-20', 0, '2025-08-20', '', 444.00, 0.00, 0.00, 0.00, 444.00, '2025-08-20 08:02:17', 'HAJI SHAHNAWAZ MEMON', 19, 0);

--
-- Triggers `purchase_invoice`
--
DELIMITER $$
CREATE TRIGGER `trg_after_purchase_invoice_delete` AFTER DELETE ON `purchase_invoice` FOR EACH ROW BEGIN
    -- Remove all related ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'purchase_invoice' AND reference_id = OLD.id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_purchase_invoice_insert` AFTER INSERT ON `purchase_invoice` FOR EACH ROW BEGIN
    -- Only create ledger entries if net_amount > 0
    IF NEW.net_amount > 0 THEN
        -- Debit: Purchases Account (increasing inventory/expense)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Purchase Invoice', 'purchase_invoice', NEW.id, 15, -- Purchases account
            NEW.date, 
            CONCAT('Purchase Invoice - ', NEW.bill_no, 
                   CASE WHEN NEW.vendor_name IS NOT NULL THEN CONCAT(' - ', NEW.vendor_name) ELSE '' END),
            NEW.net_amount, 0
        );
        
        -- Credit: Trade Creditors/Vendor Account (increasing liability)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Purchase Invoice', 'purchase_invoice', NEW.id, 
            COALESCE(NEW.vendor_id, 14), -- Use vendor_id or default to Trade Creditors (14)
            NEW.date,
            CONCAT('Purchase Invoice - ', NEW.bill_no,
                   CASE WHEN NEW.vendor_name IS NOT NULL THEN CONCAT(' - ', NEW.vendor_name) ELSE '' END),
            0, NEW.net_amount
        );
        
        -- Handle cash payment if any
        IF NEW.cash_paid > 0 THEN
            -- Debit: Trade Creditors (reducing liability)
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Purchase Invoice', 'purchase_invoice', NEW.id, 
                COALESCE(NEW.vendor_id, 14),
                NEW.date,
                CONCAT('Cash Payment - Invoice ', NEW.bill_no),
                NEW.cash_paid, 0
            );
            
            -- Credit: Cash Account (reducing cash)
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Purchase Invoice', 'purchase_invoice', NEW.id, 1, -- Cash account
                NEW.date,
                CONCAT('Cash Payment - Invoice ', NEW.bill_no),
                0, NEW.cash_paid
            );
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_purchase_invoice_update` AFTER UPDATE ON `purchase_invoice` FOR EACH ROW BEGIN
    -- Delete old ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'purchase_invoice' AND reference_id = NEW.id;
    
    -- Insert new ledger entries with updated values
    IF NEW.net_amount > 0 THEN
        -- Debit: Purchases Account
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Purchase Invoice', 'purchase_invoice', NEW.id, 15,
            NEW.date,
            CONCAT('Purchase Invoice - ', NEW.bill_no,
                   CASE WHEN NEW.vendor_name IS NOT NULL THEN CONCAT(' - ', NEW.vendor_name) ELSE '' END),
            NEW.net_amount, 0
        );
        
        -- Credit: Trade Creditors/Vendor Account
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Purchase Invoice', 'purchase_invoice', NEW.id, 
            COALESCE(NEW.vendor_id, 14),
            NEW.date,
            CONCAT('Purchase Invoice - ', NEW.bill_no,
                   CASE WHEN NEW.vendor_name IS NOT NULL THEN CONCAT(' - ', NEW.vendor_name) ELSE '' END),
            0, NEW.net_amount
        );
        
        -- Handle cash payment if any
        IF NEW.cash_paid > 0 THEN
            -- Debit: Trade Creditors
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Purchase Invoice', 'purchase_invoice', NEW.id, 
                COALESCE(NEW.vendor_id, 14),
                NEW.date,
                CONCAT('Cash Payment - Invoice ', NEW.bill_no),
                NEW.cash_paid, 0
            );
            
            -- Credit: Cash Account
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Purchase Invoice', 'purchase_invoice', NEW.id, 1,
                NEW.date,
                CONCAT('Cash Payment - Invoice ', NEW.bill_no),
                0, NEW.cash_paid
            );
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_purchase_invoice_stock_delete` AFTER DELETE ON `purchase_invoice` FOR EACH ROW BEGIN
    DELETE FROM stock_ledger 
    WHERE reference_table = 'purchase_invoice' AND reference_id = OLD.id;
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'purchase_invoice' AND reference_id = OLD.id 
    AND account_id IN (32, 33, 34, 36, 37);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_purchase_invoice_stock_insert` AFTER INSERT ON `purchase_invoice` FOR EACH ROW BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id INT;
    DECLARE v_quantity DECIMAL(15,3);
    DECLARE v_unit_cost DECIMAL(15,3);
    DECLARE v_warehouse_id INT;
    DECLARE v_prev_balance DECIMAL(15,3);
    DECLARE v_inventory_account_id INT;
    
    DECLARE item_cursor CURSOR FOR
        SELECT pii.product_id, 
               (COALESCE(pii.ctn, 0) * COALESCE(pii.packing, 1) + COALESCE(pii.doz, 0) * 12 + COALESCE(pii.pcs, 0)) as qty,
               COALESCE(pii.pprice, 0) as cost,
               COALESCE(NEW.warehouse_id, 0) as warehouse,
               COALESCE(p.inventory_account_id, 32) as inv_account
        FROM purchase_item_invoice pii
        JOIN products p ON pii.product_id = p.id
        WHERE pii.invoice_id = NEW.id
        AND (COALESCE(pii.ctn, 0) * COALESCE(pii.packing, 1) + COALESCE(pii.doz, 0) * 12 + COALESCE(pii.pcs, 0)) > 0;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN item_cursor;
    
    item_loop: LOOP
        FETCH item_cursor INTO v_product_id, v_quantity, v_unit_cost, v_warehouse_id, v_inventory_account_id;
        IF done THEN
            LEAVE item_loop;
        END IF;
        
        SELECT COALESCE(balance_qty, 0) INTO v_prev_balance
        FROM stock_ledger 
        WHERE product_id = v_product_id AND warehouse_id = v_warehouse_id
        ORDER BY id DESC LIMIT 1;
        
        INSERT INTO stock_ledger (
            transaction_type, reference_table, reference_id, product_id, 
            warehouse_id, account_id, qty_in, balance_qty, unit_cost, total_cost, transaction_date
        ) VALUES (
            'Purchase Invoice', 'purchase_invoice', NEW.id, v_product_id,
            v_warehouse_id, (SELECT COALESCE(inventory_account_id, 32) FROM products WHERE id = v_product_id), v_quantity, (v_prev_balance + v_quantity), v_unit_cost, 
            (v_quantity * v_unit_cost), NEW.date
        );
        
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Purchase Invoice', 'purchase_invoice', NEW.id, v_inventory_account_id,
            NEW.date,
            CONCAT('Purchase - Product ID: ', v_product_id, ' - Invoice: ', NEW.bill_no),
            (v_quantity * v_unit_cost), 0
        );
        
    END LOOP;
    
    CLOSE item_cursor;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_purchase_invoice_stock_update` AFTER UPDATE ON `purchase_invoice` FOR EACH ROW BEGIN
    DELETE FROM stock_ledger 
    WHERE reference_table = 'purchase_invoice' AND reference_id = NEW.id;
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'purchase_invoice' AND reference_id = NEW.id 
    AND account_id IN (32, 33, 34, 36, 37);
    
    CALL trg_purchase_invoice_stock_insert_logic(NEW.id, NEW.warehouse_id, NEW.date, NEW.bill_no);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_invoice_return`
--

CREATE TABLE `purchase_invoice_return` (
  `id` int(11) NOT NULL,
  `bill_no` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `transport_id` int(11) DEFAULT NULL,
  `sale_man_id` int(11) DEFAULT NULL,
  `bilty_no` varchar(255) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `cell_no` varchar(20) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `purchase_invoice_no` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_bill` decimal(10,2) DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `cash_paid` decimal(10,2) DEFAULT NULL,
  `net_amount` decimal(10,2) DEFAULT NULL,
  `previous_balance` decimal(10,2) DEFAULT NULL,
  `selected_bill_no` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `account_id` int(11) NOT NULL DEFAULT 20
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `purchase_invoice_return`
--
DELIMITER $$
CREATE TRIGGER `trg_after_purchase_invoice_return_delete` AFTER DELETE ON `purchase_invoice_return` FOR EACH ROW BEGIN
    -- Remove all related ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'purchase_invoice_return' AND reference_id = OLD.id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_purchase_invoice_return_insert` AFTER INSERT ON `purchase_invoice_return` FOR EACH ROW BEGIN
    -- Only create ledger entries if net_amount > 0
    IF NEW.net_amount > 0 THEN
        -- Credit: Purchase Return Account (reducing purchase expense)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Purchase Return', 'purchase_invoice_return', NEW.id, 20, -- Purchase Return account
            NEW.date, 
            CONCAT('Purchase Return - ', NEW.bill_no, 
                   CASE WHEN NEW.purchase_invoice_no IS NOT NULL THEN CONCAT(' - Inv: ', NEW.purchase_invoice_no) ELSE '' END),
            0, NEW.net_amount
        );
        
        -- Debit: Trade Creditors/Vendor Account (reducing liability)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Purchase Return', 'purchase_invoice_return', NEW.id, 
            COALESCE(NEW.vendor_id, 14), -- Use vendor_id or default to Trade Creditors (14)
            NEW.date,
            CONCAT('Purchase Return - ', NEW.bill_no,
                   CASE WHEN NEW.purchase_invoice_no IS NOT NULL THEN CONCAT(' - Inv: ', NEW.purchase_invoice_no) ELSE '' END),
            NEW.net_amount, 0
        );
        
        -- Handle cash refund if any
        IF NEW.cash_paid > 0 THEN
            -- Debit: Cash Account (cash received as refund)
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Purchase Return', 'purchase_invoice_return', NEW.id, 1, -- Cash account
                NEW.date,
                CONCAT('Cash Refund - Return ', NEW.bill_no),
                NEW.cash_paid, 0
            );
            
            -- Credit: Trade Creditors (reducing vendor balance)
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Purchase Return', 'purchase_invoice_return', NEW.id, 
                COALESCE(NEW.vendor_id, 14),
                NEW.date,
                CONCAT('Cash Refund - Return ', NEW.bill_no),
                0, NEW.cash_paid
            );
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_purchase_invoice_return_update` AFTER UPDATE ON `purchase_invoice_return` FOR EACH ROW BEGIN
    -- Delete old ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'purchase_invoice_return' AND reference_id = NEW.id;
    
    -- Insert new ledger entries with updated values
    IF NEW.net_amount > 0 THEN
        -- Credit: Purchase Return Account
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Purchase Return', 'purchase_invoice_return', NEW.id, 20,
            NEW.date,
            CONCAT('Purchase Return - ', NEW.bill_no,
                   CASE WHEN NEW.purchase_invoice_no IS NOT NULL THEN CONCAT(' - Inv: ', NEW.purchase_invoice_no) ELSE '' END),
            0, NEW.net_amount
        );
        
        -- Debit: Trade Creditors/Vendor Account
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Purchase Return', 'purchase_invoice_return', NEW.id, 
            COALESCE(NEW.vendor_id, 14),
            NEW.date,
            CONCAT('Purchase Return - ', NEW.bill_no,
                   CASE WHEN NEW.purchase_invoice_no IS NOT NULL THEN CONCAT(' - Inv: ', NEW.purchase_invoice_no) ELSE '' END),
            NEW.net_amount, 0
        );
        
        -- Handle cash refund if any
        IF NEW.cash_paid > 0 THEN
            -- Debit: Cash Account
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Purchase Return', 'purchase_invoice_return', NEW.id, 1,
                NEW.date,
                CONCAT('Cash Refund - Return ', NEW.bill_no),
                NEW.cash_paid, 0
            );
            
            -- Credit: Trade Creditors
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Purchase Return', 'purchase_invoice_return', NEW.id, 
                COALESCE(NEW.vendor_id, 14),
                NEW.date,
                CONCAT('Cash Refund - Return ', NEW.bill_no),
                0, NEW.cash_paid
            );
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_purchase_return_stock_delete` AFTER DELETE ON `purchase_invoice_return` FOR EACH ROW BEGIN
    DELETE FROM stock_ledger 
    WHERE reference_table = 'purchase_invoice_return' AND reference_id = OLD.id;
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'purchase_invoice_return' AND reference_id = OLD.id 
    AND account_id IN (32, 33, 34, 36, 37);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_purchase_return_stock_insert` AFTER INSERT ON `purchase_invoice_return` FOR EACH ROW BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id INT;
    DECLARE v_quantity DECIMAL(15,3);
    DECLARE v_unit_cost DECIMAL(15,3);
    DECLARE v_warehouse_id INT;
    DECLARE v_prev_balance DECIMAL(15,3);
    DECLARE v_inventory_account_id INT;
    
    DECLARE item_cursor CURSOR FOR
        SELECT piri.product_id, 
               (COALESCE(piri.ctn, 0) * COALESCE(piri.packing, 1) + COALESCE(piri.doz, 0) * 12 + COALESCE(piri.pcs, 0)) as qty,
               COALESCE(piri.pprice, 0) as cost,
               COALESCE(NEW.warehouse_id, 1) as warehouse,
               COALESCE(p.inventory_account_id, 32) as inv_account
        FROM purchase_invoice_return_items piri
        JOIN products p ON piri.product_id = p.id
        WHERE piri.invoice_id = NEW.id
        AND (COALESCE(piri.ctn, 0) * COALESCE(piri.packing, 1) + COALESCE(piri.doz, 0) * 12 + COALESCE(piri.pcs, 0)) > 0
        AND p.inventory_account_id IN (32, 33, 34, 36, 37);
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN item_cursor;
    
    item_loop: LOOP
        FETCH item_cursor INTO v_product_id, v_quantity, v_unit_cost, v_warehouse_id, v_inventory_account_id;
        IF done THEN
            LEAVE item_loop;
        END IF;
        
        SELECT COALESCE(balance_qty, 0) INTO v_prev_balance
        FROM stock_ledger 
        WHERE product_id = v_product_id AND warehouse_id = v_warehouse_id
        ORDER BY id DESC LIMIT 1;
        
        INSERT INTO stock_ledger (
            transaction_type, reference_table, reference_id, product_id, 
            warehouse_id, qty_out, balance_qty, unit_cost, total_cost, transaction_date
        ) VALUES (
            'Purchase Return', 'purchase_invoice_return', NEW.id, v_product_id,
            v_warehouse_id, v_quantity, (v_prev_balance - v_quantity), v_unit_cost, 
            (v_quantity * v_unit_cost), NEW.date
        );
        
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Purchase Return', 'purchase_invoice_return', NEW.id, v_inventory_account_id,
            NEW.date,
            CONCAT('Purchase Return - Product ID: ', v_product_id, ' - Return: ', NEW.bill_no),
            0, (v_quantity * v_unit_cost)
        );
        
    END LOOP;
    
    CLOSE item_cursor;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_purchase_return_stock_update` AFTER UPDATE ON `purchase_invoice_return` FOR EACH ROW BEGIN
    DELETE FROM stock_ledger 
    WHERE reference_table = 'purchase_invoice_return' AND reference_id = NEW.id;
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'purchase_invoice_return' AND reference_id = NEW.id 
    AND account_id IN (32, 33, 34, 36, 37);
    
    CALL trg_purchase_return_stock_insert_logic(NEW.id, NEW.warehouse_id, NEW.date, NEW.bill_no);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_invoice_return_items`
--

CREATE TABLE `purchase_invoice_return_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `packing` varchar(100) DEFAULT NULL,
  `ctn` int(11) DEFAULT NULL,
  `doz` int(11) DEFAULT NULL,
  `pcs` int(11) DEFAULT NULL,
  `pprice` int(100) DEFAULT NULL,
  `fu` int(11) DEFAULT NULL,
  `disc_percentage` decimal(5,2) DEFAULT NULL,
  `discount_rs` decimal(10,2) DEFAULT NULL,
  `net_value` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_item_invoice`
--

CREATE TABLE `purchase_item_invoice` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `packing` decimal(10,2) DEFAULT NULL,
  `ctn` int(11) DEFAULT NULL,
  `doz` int(11) DEFAULT NULL,
  `pcs` int(11) DEFAULT NULL,
  `pprice` int(100) DEFAULT NULL,
  `fu` int(11) DEFAULT NULL,
  `disc_percentage` decimal(5,2) DEFAULT NULL,
  `discount_rs` decimal(10,2) DEFAULT NULL,
  `net_value` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sales_tax_percentage` decimal(5,2) DEFAULT 0.00,
  `sales_tax_amt` decimal(10,2) DEFAULT 0.00,
  `further_tax_percentage` decimal(5,2) DEFAULT 0.00,
  `further_tax_amt` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_item_invoice`
--

INSERT INTO `purchase_item_invoice` (`id`, `invoice_id`, `product_id`, `product_name`, `description`, `unit`, `packing`, `ctn`, `doz`, `pcs`, `pprice`, `fu`, `disc_percentage`, `discount_rs`, `net_value`, `created_at`, `sales_tax_percentage`, `sales_tax_amt`, `further_tax_percentage`, `further_tax_amt`) VALUES
(119, 20, 2, 'Wooden Plank (Pine Wood)', '', 'Pcs', 0.00, 0, 0, 5, 800, NULL, 0.00, 0.00, 4000.00, '2025-08-05 14:11:09', 0.00, 0.00, 0.00, 0.00),
(120, 20, 3, 'Steel Screws', '', 'Pcs', 0.00, 0, 0, 20, 5, NULL, 0.00, 0.00, 100.00, '2025-08-05 14:11:09', 0.00, 0.00, 0.00, 0.00),
(121, 20, 4, 'Wood Glue (Strong Bond)', '', 'L', 0.00, 0, 0, 2, 500, NULL, 0.00, 0.00, 1000.00, '2025-08-05 14:11:09', 0.00, 0.00, 0.00, 0.00),
(122, 20, 5, 'Cushion Foam (Seat)', '', 'Pcs', 0.00, 0, 0, 1, 1200, NULL, 0.00, 0.00, 1200.00, '2025-08-05 14:11:09', 0.00, 0.00, 0.00, 0.00),
(123, 20, 6, 'Paint / Polish (Brown)', '', 'L', 0.00, 0, 0, 3, 1000, NULL, 0.00, 0.00, 3000.00, '2025-08-05 14:11:09', 0.00, 0.00, 0.00, 0.00),
(124, 20, 7, 'Plastic Legs Caps', '', 'Pcs', 0.00, 0, 0, 4, 5, NULL, 0.00, 0.00, 20.00, '2025-08-05 14:11:09', 0.00, 0.00, 0.00, 0.00),
(125, 21, 2, 'Wooden Plank (Pine Wood)', '', 'Pcs', 0.00, 0, 0, 2, 800, NULL, 0.00, 0.00, 1936.00, '2025-08-05 15:38:39', NULL, NULL, NULL, NULL),
(126, 21, 3, 'Steel Screws', '', 'Pcs', 0.00, 0, 0, 5, 5, NULL, 0.00, 0.00, 30.25, '2025-08-05 15:38:39', NULL, NULL, NULL, NULL),
(127, 21, 4, 'Wood Glue (Strong Bond)', '', 'L', 0.00, 0, 0, 6, 500, NULL, 0.00, 0.00, 3630.00, '2025-08-05 15:38:39', NULL, NULL, NULL, NULL),
(128, 22, 10, 'Sugar', '', 'Pcs', 25.00, 1, 0, 0, 177, NULL, 0.00, 0.00, 4513.50, '2025-08-15 11:44:00', 0.00, 0.00, 0.00, 0.00),
(130, 24, 9, 'Testing', '', 'Pcs', 24.00, 0, 0, 100, 400, NULL, 0.00, 0.00, 40000.00, '2025-08-20 07:58:39', 0.00, 0.00, 0.00, 0.00),
(131, 25, 9, 'Testing', '', 'Pcs', 24.00, 0, 0, 10, 400, NULL, 0.00, 0.00, 4000.00, '2025-08-20 08:01:17', 0.00, 0.00, 0.00, 0.00),
(132, 26, 9, 'Testing', '', 'Pcs', 24.00, 0, 0, 111, 4, NULL, 0.00, 0.00, 444.00, '2025-08-20 08:02:17', 0.00, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `serial_no` varchar(50) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `customer_order_ref` varchar(100) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `job_no` varchar(100) DEFAULT NULL,
  `employee_ref` varchar(100) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `customer_code` varchar(50) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `opening_amount` decimal(10,2) DEFAULT NULL,
  `cnic_no` varchar(20) DEFAULT NULL,
  `gst_reg_no` varchar(50) DEFAULT NULL,
  `ntn` varchar(50) DEFAULT NULL,
  `items_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items_data`)),
  `total_quantity` decimal(12,2) DEFAULT NULL,
  `total_value_excl` decimal(12,2) DEFAULT NULL,
  `total_gst_amount` decimal(12,2) DEFAULT NULL,
  `total_value_incl` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `serial_no`, `order_date`, `customer_order_ref`, `payment_terms`, `job_no`, `employee_ref`, `delivery_date`, `customer_code`, `customer_name`, `address`, `telephone`, `opening_amount`, `cnic_no`, `gst_reg_no`, `ntn`, `items_data`, `total_quantity`, `total_value_excl`, `total_gst_amount`, `total_value_incl`, `created_at`) VALUES
(2, '', '2025-06-04', 'cs', 'Pay pal', 'Job #1 - Road Repair', 'Ali', '2025-06-11', '12', '12', 'Siyal Morri', '66262626222', 3131331.00, '42101-1234567-8', '33131', '32', '[{\"code\":\"21\",\"name\":\"Green Tea 100g\",\"desc\":\"\",\"qty\":\"1\",\"unit\":\"Box\",\"rate\":\"220.00\",\"valExcl\":\"220.00\",\"gstRate\":\"8\",\"gstAmt\":\"17.60\",\"valIncl\":\"237.60\"},{\"code\":\"17\",\"name\":\"Almond Biscuits\",\"desc\":\"\",\"qty\":\"1\",\"unit\":\"Box\",\"rate\":\"120.00\",\"valExcl\":\"120.00\",\"gstRate\":\"17\",\"gstAmt\":\"20.40\",\"valIncl\":\"140.40\"}]', 2.00, 340.00, 38.00, 378.00, '2025-06-27 11:05:30'),
(4, '', '2025-06-04', 'cs', 'Pay pal', 'Job #1 - Road Repair', NULL, '2025-06-16', '14', '14', 'Lahore, Punjab', '03001234567', 5000.00, '35202-1234567-1', '13113', '3131', '[{\"code\":\"21\",\"name\":\"Green Tea 100g\",\"desc\":\"\",\"qty\":\"1\",\"unit\":\"Box\",\"rate\":\"220.00\",\"valExcl\":\"220.00\",\"gstRate\":\"8\",\"gstAmt\":\"17.60\",\"valIncl\":\"237.60\"},{\"code\":\"17\",\"name\":\"Almond Biscuits\",\"desc\":\"\",\"qty\":\"1\",\"unit\":\"Box\",\"rate\":\"120.00\",\"valExcl\":\"120.00\",\"gstRate\":\"17\",\"gstAmt\":\"20.40\",\"valIncl\":\"140.40\"}]', 2.00, 340.00, 38.00, 378.00, '2025-06-27 11:07:53'),
(5, 'SO-1001', '2023-01-15', 'CUST-REF-001', 'Net 30', 'Job #1 - Road Repair', 'Ali', '2023-02-15', 'C001', 'ABC Construction', '123 Main St, Lahore', '03001234567', 50000.00, '35202-1234567-8', 'GST123456789', 'NTN1234567', '[{\"code\":\"P001\",\"name\":\"Cement\",\"desc\":\"50kg Bag\",\"qty\":100,\"unit\":\"Bag\",\"rate\":1050,\"valExcl\":105000,\"gstRate\":17,\"gstAmt\":17850,\"valIncl\":122850}]', 100.00, 105000.00, 17850.00, 122850.00, '2025-06-27 14:47:48'),
(6, 'SO-1002', '2023-01-18', 'CUST-REF-002', 'Cash', 'Job #2 - Building Setup', 'Ahmed', '2023-01-25', 'C002', 'XYZ Builders', '456 Mall Rd, Karachi', '03007654321', 25000.00, '35202-7654321-9', 'GST987654321', 'NTN7654321', '[{\"code\":\"P002\",\"name\":\"Steel Bars\",\"desc\":\"12mm\",\"qty\":50,\"unit\":\"Ton\",\"rate\":220000,\"valExcl\":11000000,\"gstRate\":17,\"gstAmt\":1870000,\"valIncl\":12870000}]', 50.00, 11000000.00, 1870000.00, 12870000.00, '2025-06-27 14:47:48'),
(7, 'SO-1003', '2023-02-01', 'CUST-REF-003', 'Net 15', 'Job #1 - Road Repair', 'Ali', '2023-02-10', 'C003', 'City Developers', '789 Park Ave, Islamabad', '03451234567', 75000.00, '35202-1122334-5', 'GST112233445', 'NTN1122334', '[{\"code\":\"P003\",\"name\":\"Bricks\",\"desc\":\"Red Bricks\",\"qty\":5000,\"unit\":\"Piece\",\"rate\":12,\"valExcl\":60000,\"gstRate\":17,\"gstAmt\":10200,\"valIncl\":70200}]', 5000.00, 60000.00, 10200.00, 70200.00, '2025-06-27 14:47:48'),
(8, 'SO-1004', '2023-02-05', 'CUST-REF-004', 'Advance 50%', 'Job #3 - Bridge Construction', 'Ahmed', '2023-03-15', 'C004', 'National Contractors', '321 Civic Center, Faisalabad', '03331234567', 100000.00, '35202-4455667-8', 'GST445566778', 'NTN4455667', '[{\"code\":\"P004\",\"name\":\"Sand\",\"desc\":\"Coarse\",\"qty\":20,\"unit\":\"Truck\",\"rate\":35000,\"valExcl\":700000,\"gstRate\":17,\"gstAmt\":119000,\"valIncl\":819000}]', 20.00, 700000.00, 119000.00, 819000.00, '2025-06-27 14:47:48'),
(9, 'SO-1005', '2023-02-10', 'CUST-REF-005', 'Net 30', 'Job #2 - Building Setup', 'Ali', '2023-02-28', 'C005', 'Metro Builders', '654 Commercial Area, Rawalpindi', '03111234567', 30000.00, '35202-9988776-5', 'GST998877665', 'NTN9988776', '[{\"code\":\"P005\",\"name\":\"Tiles\",\"desc\":\"Ceramic 2x2\",\"qty\":200,\"unit\":\"Box\",\"rate\":2500,\"valExcl\":500000,\"gstRate\":17,\"gstAmt\":85000,\"valIncl\":585000}]', 200.00, 500000.00, 85000.00, 585000.00, '2025-06-27 14:47:48'),
(10, 'SO-1006', '2023-02-15', 'CUST-REF-006', 'Cash', 'Job #1 - Road Repair', 'Ahmed', '2023-02-20', 'C006', 'Punjab Construction', '987 Main Blvd, Multan', '03009876543', 45000.00, '35202-5544332-1', 'GST554433221', 'NTN5544332', '[{\"code\":\"P006\",\"name\":\"Paint\",\"desc\":\"White\",\"qty\":100,\"unit\":\"Gallon\",\"rate\":1200,\"valExcl\":120000,\"gstRate\":17,\"gstAmt\":20400,\"valIncl\":140400}]', 100.00, 120000.00, 20400.00, 140400.00, '2025-06-27 14:47:48'),
(11, 'SO-1007', '2023-02-20', 'CUST-REF-007', 'Net 15', 'Job #4 - Office Renovation', 'Ali', '2023-03-10', 'C007', 'Elite Developers', '258 Business St, Peshawar', '03459876543', 60000.00, '35202-6677889-0', 'GST667788990', 'NTN6677889', '[{\"code\":\"P007\",\"name\":\"Marble\",\"desc\":\"Italian White\",\"qty\":30,\"unit\":\"Slab\",\"rate\":8000,\"valExcl\":240000,\"gstRate\":17,\"gstAmt\":40800,\"valIncl\":280800}]', 30.00, 240000.00, 40800.00, 280800.00, '2025-06-27 14:47:48'),
(15, '', '0000-00-00', '', '', NULL, NULL, '0000-00-00', '15', '15', 'North karachi', '03063929876', 0.00, '31313-1313131-3', '', '', '0', 1.00, 300.00, 0.00, 300.00, '2025-07-24 11:53:08');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `rate` decimal(15,2) NOT NULL DEFAULT 0.00,
  `value_excl` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_percentage` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `value_incl` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requisitions`
--

CREATE TABLE `purchase_requisitions` (
  `id` int(11) NOT NULL,
  `serial_number` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `required_by_date` date DEFAULT NULL,
  `employee_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `job_description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_requisitions`
--

INSERT INTO `purchase_requisitions` (`id`, `serial_number`, `date`, `required_by_date`, `employee_id`, `warehouse_id`, `job_description`, `created_at`) VALUES
(3, '1', '2025-08-20', '2025-08-27', 35, 1, '122', '2025-08-20 08:10:03');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requisition_items`
--

CREATE TABLE `purchase_requisition_items` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `suggested_supplier` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_requisition_items`
--

INSERT INTO `purchase_requisition_items` (`id`, `requisition_id`, `product_id`, `description`, `quantity`, `unit`, `suggested_supplier`, `created_at`) VALUES
(7, 3, 9, 'Testing', 900, 'Pcs', 'HAJI SHAHNAWAZ MEMON', '2025-08-20 08:11:27');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_returns`
--

CREATE TABLE `purchase_returns` (
  `id` int(11) NOT NULL,
  `return_number` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_tax`
--

CREATE TABLE `purchase_tax` (
  `id` int(11) NOT NULL,
  `serial_no` varchar(50) NOT NULL,
  `vendor_invoice_no` varchar(100) DEFAULT NULL,
  `purchase_order_no` varchar(100) DEFAULT NULL,
  `terms_payment` enum('Cash','Credit','Bank Transfer') DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `vendor_id` varchar(50) DEFAULT NULL,
  `vendor_name` varchar(255) DEFAULT NULL,
  `vendor_address` text DEFAULT NULL,
  `vendor_phone` varchar(50) DEFAULT NULL,
  `total_quantity` decimal(10,2) DEFAULT 0.00,
  `total_value_excl` decimal(12,2) DEFAULT 0.00,
  `total_tax_amount` decimal(12,2) DEFAULT 0.00,
  `grand_total` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `status` enum('Draft','Confirmed','Cancelled') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_tax`
--

INSERT INTO `purchase_tax` (`id`, `serial_no`, `vendor_invoice_no`, `purchase_order_no`, `terms_payment`, `invoice_date`, `vendor_id`, `vendor_name`, `vendor_address`, `vendor_phone`, `total_quantity`, `total_value_excl`, `total_tax_amount`, `grand_total`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PO-1', '', '', '', '2025-07-24', '12', 'Hafiz Sumama', 'North karachi', '03063929876', 10.00, 1500.00, 0.00, 1500.00, NULL, 'Draft', '2025-07-24 07:13:46', '2025-07-24 07:13:46');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_tax_items`
--

CREATE TABLE `purchase_tax_items` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `value_excl` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_percentage` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `value_incl` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_tax_items`
--

INSERT INTO `purchase_tax_items` (`id`, `purchase_order_id`, `item_code`, `item_name`, `description`, `unit`, `quantity`, `rate`, `value_excl`, `tax_percentage`, `tax_amount`, `value_incl`, `created_at`, `updated_at`) VALUES
(1, 1, '19', 'Popup', '4*4', 'Pcs', 10.00, 150.00, 1500.00, 0.00, 0.00, 1500.00, '2025-07-24 07:13:46', '2025-07-24 07:13:46');

--
-- Triggers `purchase_tax_items`
--
DELIMITER $$
CREATE TRIGGER `update_purchase_tax_totals_after_delete` AFTER DELETE ON `purchase_tax_items` FOR EACH ROW BEGIN
    UPDATE `purchase_tax` SET
        `total_quantity` = (
            SELECT COALESCE(SUM(`quantity`), 0) 
            FROM `purchase_tax_items` 
            WHERE `purchase_order_id` = OLD.`purchase_order_id`
        ),
        `total_value_excl` = (
            SELECT COALESCE(SUM(`value_excl`), 0) 
            FROM `purchase_tax_items` 
            WHERE `purchase_order_id` = OLD.`purchase_order_id`
        ),
        `total_tax_amount` = (
            SELECT COALESCE(SUM(`tax_amount`), 0) 
            FROM `purchase_tax_items` 
            WHERE `purchase_order_id` = OLD.`purchase_order_id`
        ),
        `grand_total` = (
            SELECT COALESCE(SUM(`value_incl`), 0) 
            FROM `purchase_tax_items` 
            WHERE `purchase_order_id` = OLD.`purchase_order_id`
        ),
        `updated_at` = CURRENT_TIMESTAMP
    WHERE `id` = OLD.`purchase_order_id`;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_purchase_tax_totals_after_insert` AFTER INSERT ON `purchase_tax_items` FOR EACH ROW BEGIN
    UPDATE `purchase_tax` SET
        `total_quantity` = (
            SELECT COALESCE(SUM(`quantity`), 0) 
            FROM `purchase_tax_items` 
            WHERE `purchase_order_id` = NEW.`purchase_order_id`
        ),
        `total_value_excl` = (
            SELECT COALESCE(SUM(`value_excl`), 0) 
            FROM `purchase_tax_items` 
            WHERE `purchase_order_id` = NEW.`purchase_order_id`
        ),
        `total_tax_amount` = (
            SELECT COALESCE(SUM(`tax_amount`), 0) 
            FROM `purchase_tax_items` 
            WHERE `purchase_order_id` = NEW.`purchase_order_id`
        ),
        `grand_total` = (
            SELECT COALESCE(SUM(`value_incl`), 0) 
            FROM `purchase_tax_items` 
            WHERE `purchase_order_id` = NEW.`purchase_order_id`
        ),
        `updated_at` = CURRENT_TIMESTAMP
    WHERE `id` = NEW.`purchase_order_id`;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_purchase_tax_totals_after_update` AFTER UPDATE ON `purchase_tax_items` FOR EACH ROW BEGIN
    UPDATE `purchase_tax` SET
        `total_quantity` = (
            SELECT COALESCE(SUM(`quantity`), 0) 
            FROM `purchase_tax_items` 
            WHERE `purchase_order_id` = NEW.`purchase_order_id`
        ),
        `total_value_excl` = (
            SELECT COALESCE(SUM(`value_excl`), 0) 
            FROM `purchase_tax_items` 
            WHERE `purchase_order_id` = NEW.`purchase_order_id`
        ),
        `total_tax_amount` = (
            SELECT COALESCE(SUM(`tax_amount`), 0) 
            FROM `purchase_tax_items` 
            WHERE `purchase_order_id` = NEW.`purchase_order_id`
        ),
        `grand_total` = (
            SELECT COALESCE(SUM(`value_incl`), 0) 
            FROM `purchase_tax_items` 
            WHERE `purchase_order_id` = NEW.`purchase_order_id`
        ),
        `updated_at` = CURRENT_TIMESTAMP
    WHERE `id` = NEW.`purchase_order_id`;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `quotation_entries`
--

CREATE TABLE `quotation_entries` (
  `id` int(11) NOT NULL,
  `quotation_no` varchar(50) DEFAULT NULL,
  `quotation_date` date DEFAULT NULL,
  `valid_till` date DEFAULT NULL,
  `payment_terms` varchar(50) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `salesman` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_bill` decimal(10,2) DEFAULT NULL,
  `discount_percent` decimal(5,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `cash_paid` decimal(10,2) DEFAULT NULL,
  `net_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotation_entries`
--

INSERT INTO `quotation_entries` (`id`, `quotation_no`, `quotation_date`, `valid_till`, `payment_terms`, `contact_person`, `customer_id`, `customer_name`, `address`, `phone`, `salesman`, `remarks`, `total_bill`, `discount_percent`, `discount_amount`, `cash_paid`, `net_amount`, `created_at`) VALUES
(10, 'QTN-1', '2025-07-25', '2025-07-25', 'Net 30', '0', 18, 'Wasim G/S', 'Gulshan Karachi', '03338975626', 'Arif Khan', 'Very Humble Person', 14600.00, 2.00, 292.00, 0.00, 14308.00, '2025-07-25 22:37:51'),
(11, 'QTN-2', '2025-07-30', '2025-07-30', '', '0', 19, 'Getz Pharam', 'Lahore', '', 'Arif Khan', '', 600.00, 2.00, 12.00, 0.00, 588.00, '2025-07-30 07:55:49'),
(12, 'QTN-3', '2025-08-18', '2025-08-18', 'COD', 'Asif', 26, 'Abdul Qudus Bhai', 'Shershah', '03215987841', 'Zahid Ghori', '', 0.00, 0.00, 0.00, 0.00, 0.00, '2025-08-18 08:26:46'),
(13, 'QTN-3', '2025-08-18', '2025-08-18', 'COD', 'Asif', 26, 'Abdul Qudus Bhai', 'Shershah', '03215987841', 'Zahid Ghori', '', 0.00, 0.00, 0.00, 0.00, 0.00, '2025-08-18 08:27:24'),
(14, 'QTN-4', '2025-08-19', '2025-08-19', '', '', 20, 'InnovaTech', 'Saadi Town Block 1', '03342616587', '', '', 59000.00, 0.00, 0.00, 0.00, 59000.00, '2025-08-19 21:26:50'),
(15, 'QTN-5', '2025-08-19', '2025-08-19', '', '', 23, 'Asif G/s', 'Ali block Bahria Town Karachi', '03468918711', '', '', 0.00, 0.00, 0.00, 0.00, 0.00, '2025-08-19 21:34:11'),
(16, 'QTN-6', '2025-08-19', '2025-08-19', '', '', 24, 'WAZEER ALI', 'SHAHI BAZA', '', '', '', 2950.00, 0.00, 0.00, 0.00, 2950.00, '2025-08-19 21:36:32'),
(17, 'QTN-7', '2025-08-19', '2025-08-19', '', '', 24, 'WAZEER ALI', 'SHAHI BAZA', '', '', '', 0.00, 0.00, 0.00, 0.00, 0.00, '2025-08-19 21:38:04'),
(18, 'QTN-8', '2025-09-02', '2025-09-02', '10 Days', 'Ansari', 30, 'Hello World', 'Khi', '02132578411', 'Zahid Ghori', '', 59000.00, 0.00, 0.00, 0.00, 59000.00, '2025-09-02 08:17:46'),
(19, 'QTN-8', '2025-09-02', '2025-09-02', '10 Days', 'Ansari', 30, 'Hello World', 'Khi', '02132578411', 'Zahid Ghori', '', 59000.00, 0.00, 0.00, 0.00, 59000.00, '2025-09-02 08:18:16'),
(20, 'QTN-9', '2025-09-02', '2025-09-02', '', '', 29, 'PSL', 'khi', '+021-0000000', 'Zahid Ghori', '', 2950.00, 0.00, 0.00, 0.00, 2950.00, '2025-09-02 09:35:00'),
(21, 'QTN-10', '2025-09-08', '2025-09-08', 'Cash', 'Abid', 26, 'Abdul Qudus Bhai', 'Shershah', '03215987841', 'Zahid Ghori', '', 0.00, 0.00, 0.00, 0.00, 0.00, '2025-09-08 06:45:23');

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `id` int(11) NOT NULL,
  `quotation_id` int(11) DEFAULT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `quantity_unit` varchar(20) DEFAULT NULL,
  `unit_type` varchar(20) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `value_excl_tax` decimal(10,2) DEFAULT NULL,
  `sales_tax_percent` decimal(5,2) DEFAULT NULL,
  `sales_tax_amt` decimal(10,2) DEFAULT NULL,
  `further_tax_percent` decimal(5,2) DEFAULT NULL,
  `further_tax_amt` decimal(10,2) DEFAULT NULL,
  `value_incl_tax` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `item_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotation_items`
--

INSERT INTO `quotation_items` (`id`, `quotation_id`, `item_code`, `description`, `quantity`, `quantity_unit`, `unit_type`, `rate`, `value_excl_tax`, `sales_tax_percent`, `sales_tax_amt`, `further_tax_percent`, `further_tax_amt`, `value_incl_tax`, `created_at`, `item_description`) VALUES
(8, 10, '19', 'Popup', 3.00, '0', 'Pcs', 200.00, 14400.00, 0.00, 0.00, 0.00, 0.00, 14400.00, '2025-07-25 22:37:51', ''),
(9, 10, '20', 'Bleach Small', 2.00, '0', '', 100.00, 200.00, 0.00, 0.00, 0.00, 0.00, 200.00, '2025-07-25 22:37:51', ''),
(10, 11, '19', 'Popup', 3.00, '0', 'Pcs', 200.00, 600.00, 0.00, 0.00, 0.00, 0.00, 600.00, '2025-07-30 07:55:49', ''),
(11, 12, '8', 'Test', 120.00, 'pcs', 'Kg', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-08-18 08:26:46', 'TEst Item'),
(12, 13, '8', 'Test', 120.00, 'pcs', 'Kg', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-08-18 08:27:24', 'TEst Item'),
(13, 14, '10', 'Sugar', 20.00, 'ctn', 'Pcs', 100.00, 50000.00, 18.00, 9000.00, 0.00, 0.00, 59000.00, '2025-08-19 21:26:50', ''),
(14, 15, '8', 'Test', 2.00, 'ctn', 'Kg', 0.00, 0.00, 18.00, 0.00, 0.00, 0.00, 0.00, '2025-08-19 21:34:11', ''),
(15, 16, '10', 'Sugar', 1.00, 'ctn', 'Pcs', 100.00, 2500.00, 18.00, 450.00, 0.00, 0.00, 2950.00, '2025-08-19 21:36:32', ''),
(16, 17, '8', 'Test', 2.00, 'ctn', 'Kg', 0.00, 0.00, 18.00, 0.00, 0.00, 0.00, 0.00, '2025-08-19 21:38:04', ''),
(17, 18, '9', 'Testing', 100.00, 'pcs', 'Pcs', 500.00, 50000.00, 18.00, 9000.00, 0.00, 0.00, 59000.00, '2025-09-02 08:17:46', ''),
(21, 19, '9', 'Testing', 2.00, 'pcs', 'Pcs', 500.00, 50000.00, 18.00, 9000.00, 0.00, 0.00, 59000.00, '2025-09-02 09:31:48', ''),
(23, 20, '10', 'Sugar', 30.00, 'pcs', 'Pcs', 100.00, 2500.00, 18.00, 450.00, 0.00, 0.00, 2950.00, '2025-09-04 18:24:23', ''),
(25, 21, '3', 'Black Ink', 0.00, 'ctn', 'Pcs', 10.00, 0.00, 18.00, 0.00, 0.00, 0.00, 0.00, '2025-09-08 07:07:11', '');

-- --------------------------------------------------------

--
-- Table structure for table `receive_voucher`
--

CREATE TABLE `receive_voucher` (
  `id` int(11) NOT NULL,
  `voucher_number` varchar(50) NOT NULL,
  `voucher_date` date NOT NULL,
  `payer_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `receipt_method_id` int(11) NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `recovery_officer_id` int(11) DEFAULT NULL,
  `bill_no` varchar(100) DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','received','cancelled') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `receive_voucher`
--

INSERT INTO `receive_voucher` (`id`, `voucher_number`, `voucher_date`, `payer_account_id`, `amount`, `receipt_method_id`, `bank_account_id`, `customer_id`, `recovery_officer_id`, `bill_no`, `reference`, `description`, `status`, `created_by`, `approved_by`, `created_at`, `updated_at`) VALUES
(1, 'RV-250805-001', '2025-08-05', 2, 500.00, 5, 1, 20, NULL, '1', '', '', 'pending', 1, NULL, '2025-08-05 19:50:02', '2025-08-05 19:50:02'),
(2, 'RV-250805-002', '2025-08-05', 2, 1800.00, 7, NULL, 20, NULL, '1', '', '', 'pending', 1, NULL, '2025-08-05 19:53:16', '2025-08-05 19:53:16'),
(3, 'RV-250805-003', '2025-08-05', 2, 1500.00, 7, NULL, 20, NULL, '1', '', '', 'pending', 1, NULL, '2025-08-05 19:54:49', '2025-08-05 19:54:49'),
(4, 'RV-250805-004', '2025-08-05', 2, 4100.00, 7, NULL, 20, NULL, '1', '', '', 'pending', 1, NULL, '2025-08-05 19:56:41', '2025-08-05 19:56:41'),
(5, 'RV-250805-005', '2025-08-05', 2, 600.00, 7, NULL, 20, NULL, '1', '', '', 'pending', 1, NULL, '2025-08-05 19:57:01', '2025-08-05 19:57:01'),
(7, 'RV-250820-001', '2025-08-20', 2, 540000.00, 7, NULL, 26, 35, '2', '', '', 'pending', 1, NULL, '2025-08-20 08:50:22', '2025-08-20 08:50:22'),
(9, 'RV-250820-002', '2025-08-20', 2, 126.00, 5, 1, 20, 35, '3', '', '', 'pending', 1, NULL, '2025-08-20 13:39:15', '2025-08-20 13:39:15');

--
-- Triggers `receive_voucher`
--
DELIMITER $$
CREATE TRIGGER `trg_after_receive_voucher_delete` AFTER DELETE ON `receive_voucher` FOR EACH ROW BEGIN
    -- Remove all related ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'receive_voucher' AND reference_id = OLD.id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_receive_voucher_insert` AFTER INSERT ON `receive_voucher` FOR EACH ROW BEGIN
    -- Debit: Receipt Method Account (cash/bank account receiving money)
    INSERT INTO accounting_ledger (
        transaction_type, reference_table, reference_id, account_id, date, description, debit, credit
    ) VALUES (
        'Receive Voucher', 'receive_voucher', NEW.id, NEW.receipt_method_id, NEW.voucher_date, 
        CONCAT('Payment received - ', NEW.voucher_number), NEW.amount, 0
    );
    
    -- Credit: Trade Debtors (id 2) when receiving from customers
    INSERT INTO accounting_ledger (
        transaction_type, reference_table, reference_id, account_id, date, description, debit, credit
    ) VALUES (
        'Receive Voucher', 'receive_voucher', NEW.id, COALESCE(NEW.payer_account_id, 2), NEW.voucher_date, 
        CONCAT('Payment received - ', NEW.voucher_number), 0, NEW.amount
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_receive_voucher_update` AFTER UPDATE ON `receive_voucher` FOR EACH ROW BEGIN
    -- Delete old ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'receive_voucher' AND reference_id = NEW.id;
    
    -- Insert new ledger entries with updated values
    -- Debit: Receipt Method Account (cash/bank account receiving money)
    INSERT INTO accounting_ledger (
        transaction_type, reference_table, reference_id, account_id, date, description, debit, credit
    ) VALUES (
        'Receive Voucher', 'receive_voucher', NEW.id, NEW.receipt_method_id, NEW.voucher_date, 
        CONCAT('Payment received - ', NEW.voucher_number), NEW.amount, 0
    );
    
    -- Credit: Trade Debtors (id 2) when receiving from customers
    INSERT INTO accounting_ledger (
        transaction_type, reference_table, reference_id, account_id, date, description, debit, credit
    ) VALUES (
        'Receive Voucher', 'receive_voucher', NEW.id, COALESCE(NEW.payer_account_id, 2), NEW.voucher_date, 
        CONCAT('Payment received - ', NEW.voucher_number), 0, NEW.amount
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `receive_voucher_journal`
--

CREATE TABLE `receive_voucher_journal` (
  `id` int(11) NOT NULL,
  `receive_voucher_id` int(11) NOT NULL,
  `customer_id` varchar(50) NOT NULL,
  `debit_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `credit_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `receive_voucher_journal`
--

INSERT INTO `receive_voucher_journal` (`id`, `receive_voucher_id`, `customer_id`, `debit_amount`, `credit_amount`, `created_at`) VALUES
(13, 4, '2', 2000.00, 0.00, '2025-07-19 10:32:24'),
(14, 4, '1', 0.00, 2000.00, '2025-07-19 10:32:24'),
(81, 4, '12', 43483.00, 0.00, '2025-07-22 14:51:11'),
(82, 4, '1', 0.00, 43483.00, '2025-07-22 14:51:11'),
(83, 5, '12', 122.00, 0.00, '2025-07-22 15:00:54'),
(84, 5, '1', 0.00, 122.00, '2025-07-22 15:00:54'),
(85, 6, '12', 2635.00, 0.00, '2025-07-22 15:04:47'),
(86, 6, '1', 0.00, 2635.00, '2025-07-22 15:04:47'),
(87, 7, '12', 78687.00, 0.00, '2025-07-22 15:20:08'),
(88, 7, '12', 0.00, 78687.00, '2025-07-22 15:20:08'),
(89, 8, '13', 122.00, 0.00, '2025-07-22 15:25:01'),
(90, 8, '13', 0.00, 122.00, '2025-07-22 15:25:01'),
(91, 9, '12', 1232.00, 0.00, '2025-07-22 15:31:26'),
(92, 9, '12', 0.00, 1232.00, '2025-07-22 15:31:26'),
(93, 10, '12', 1334.00, 0.00, '2025-07-22 15:34:12'),
(94, 10, '12', 0.00, 1334.00, '2025-07-22 15:34:12'),
(95, 11, '12', 24335.00, 0.00, '2025-07-22 15:36:26'),
(96, 11, '12', 0.00, 24335.00, '2025-07-22 15:36:26');

-- --------------------------------------------------------

--
-- Table structure for table `relations`
--

CREATE TABLE `relations` (
  `id` int(11) NOT NULL,
  `relation_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `relations`
--

INSERT INTO `relations` (`id`, `relation_name`) VALUES
(1, 'Do'),
(3, 'So'),
(2, 'Wo'),
(6, 'xyz');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(2, 'Manager', '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(3, 'User', '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(4, 'AM', '2025-08-20 11:43:42', '2025-08-20 11:43:42');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `category` enum('Setup','Entry','Reports') NOT NULL,
  `form_name` varchar(100) NOT NULL,
  `sub_permission` enum('add_record','edit','delete') DEFAULT NULL,
  `allowed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `category`, `form_name`, `sub_permission`, `allowed`, `created_at`, `updated_at`) VALUES
(34, 2, 'Entry', 'purchase_invoice', NULL, 1, '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(35, 2, 'Entry', 'purchase_invoice', 'add_record', 1, '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(36, 2, 'Entry', 'purchase_invoice', 'edit', 1, '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(37, 2, 'Entry', 'purchase_invoice', 'delete', 1, '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(38, 2, 'Entry', 'sale_invoice', NULL, 1, '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(39, 2, 'Entry', 'sale_invoice', 'add_record', 1, '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(40, 2, 'Entry', 'sale_invoice', 'edit', 1, '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(41, 2, 'Entry', 'sale_invoice', 'delete', 1, '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(42, 2, 'Reports', 'daily_sale', NULL, 1, '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(43, 2, 'Reports', 'recovery_sheet', NULL, 1, '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(44, 2, 'Reports', 'sale_report', NULL, 1, '2025-04-29 15:05:01', '2025-04-29 15:05:01'),
(1064, 3, 'Setup', 'company_setup', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1065, 3, 'Setup', 'company_setup', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1066, 3, 'Setup', 'company_setup', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1067, 3, 'Setup', 'company_setup', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1068, 3, 'Setup', 'chart_of_accounts', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1069, 3, 'Setup', 'chart_of_accounts', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1070, 3, 'Setup', 'chart_of_accounts', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1071, 3, 'Setup', 'chart_of_accounts', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1072, 3, 'Setup', 'bank_setup', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1073, 3, 'Setup', 'bank_setup', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1074, 3, 'Setup', 'bank_setup', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1075, 3, 'Setup', 'bank_setup', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1076, 3, 'Setup', 'customer_setup', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1077, 3, 'Setup', 'customer_setup', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1078, 3, 'Setup', 'customer_setup', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1079, 3, 'Setup', 'customer_setup', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1080, 3, 'Setup', 'transport_setup', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1081, 3, 'Setup', 'transport_setup', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1082, 3, 'Setup', 'transport_setup', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1083, 3, 'Setup', 'transport_setup', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1084, 3, 'Setup', 'warehouse_setup', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1085, 3, 'Setup', 'warehouse_setup', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1086, 3, 'Setup', 'warehouse_setup', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1087, 3, 'Setup', 'warehouse_setup', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1088, 3, 'Setup', 'zone_setup', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1089, 3, 'Setup', 'zone_setup', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1090, 3, 'Setup', 'zone_setup', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1091, 3, 'Setup', 'zone_setup', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1092, 3, 'Setup', 'item_setup', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1093, 3, 'Setup', 'item_setup', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1094, 3, 'Setup', 'item_setup', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1095, 3, 'Setup', 'item_setup', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1096, 3, 'Setup', 'item_category', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1097, 3, 'Setup', 'item_category', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1098, 3, 'Setup', 'item_category', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1099, 3, 'Setup', 'item_category', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1100, 3, 'Setup', 'item_subcategory', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1101, 3, 'Setup', 'item_subcategory', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1102, 3, 'Setup', 'item_subcategory', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1103, 3, 'Setup', 'item_subcategory', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1104, 3, 'Setup', 'vendor_setup', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1105, 3, 'Setup', 'vendor_setup', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1106, 3, 'Setup', 'vendor_setup', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1107, 3, 'Setup', 'vendor_setup', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1108, 3, 'Setup', 'batch_setup', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1109, 3, 'Setup', 'batch_setup', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1110, 3, 'Setup', 'batch_setup', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1111, 3, 'Setup', 'batch_setup', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1112, 3, 'Setup', 'branch', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1113, 3, 'Setup', 'branch', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1114, 3, 'Setup', 'branch', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1115, 3, 'Setup', 'branch', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1116, 3, 'Entry', 'sale_invoice', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1117, 3, 'Entry', 'sale_invoice', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1118, 3, 'Entry', 'sale_invoice', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1119, 3, 'Entry', 'sale_invoice', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1120, 3, 'Entry', 'sale_return', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1121, 3, 'Entry', 'sale_return', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1122, 3, 'Entry', 'sale_return', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1123, 3, 'Entry', 'sale_return', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1124, 3, 'Entry', 'pos_bill', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1125, 3, 'Entry', 'pos_bill', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1126, 3, 'Entry', 'pos_bill', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1127, 3, 'Entry', 'pos_bill', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1128, 3, 'Entry', 'purchase_invoice', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1129, 3, 'Entry', 'purchase_invoice', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1130, 3, 'Entry', 'purchase_invoice', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1131, 3, 'Entry', 'purchase_invoice', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1132, 3, 'Entry', 'purchase_return', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1133, 3, 'Entry', 'purchase_return', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1134, 3, 'Entry', 'purchase_return', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1135, 3, 'Entry', 'purchase_return', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1136, 3, 'Entry', 'receive_voucher', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1137, 3, 'Entry', 'receive_voucher', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1138, 3, 'Entry', 'receive_voucher', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1139, 3, 'Entry', 'receive_voucher', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1140, 3, 'Entry', 'receive_voucher2', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1141, 3, 'Entry', 'receive_voucher2', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1142, 3, 'Entry', 'receive_voucher2', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1143, 3, 'Entry', 'receive_voucher2', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1144, 3, 'Entry', 'payment_voucher', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1145, 3, 'Entry', 'payment_voucher', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1146, 3, 'Entry', 'payment_voucher', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1147, 3, 'Entry', 'payment_voucher', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1148, 3, 'Entry', 'journal_voucher', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1149, 3, 'Entry', 'journal_voucher', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1150, 3, 'Entry', 'journal_voucher', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1151, 3, 'Entry', 'journal_voucher', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1152, 3, 'Entry', 'expense_voucher', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1153, 3, 'Entry', 'expense_voucher', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1154, 3, 'Entry', 'expense_voucher', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1155, 3, 'Entry', 'expense_voucher', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1156, 3, 'Entry', 'employee_entry', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1157, 3, 'Entry', 'employee_entry', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1158, 3, 'Entry', 'employee_entry', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1159, 3, 'Entry', 'employee_entry', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1160, 3, 'Entry', 'production', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1161, 3, 'Entry', 'production', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1162, 3, 'Entry', 'production', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1163, 3, 'Entry', 'production', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1164, 3, 'Entry', 'bom', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1165, 3, 'Entry', 'bom', 'add_record', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1166, 3, 'Entry', 'bom', 'edit', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1167, 3, 'Entry', 'bom', 'delete', 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1168, 3, 'Reports', 'recovery_sheet', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1169, 3, 'Reports', 'daily_sale', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1170, 3, 'Reports', 'sale_report', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1171, 3, 'Reports', 'purchase_report', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1172, 3, 'Reports', 'stock_report', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1173, 3, 'Reports', 'customer_ledger', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1174, 3, 'Reports', 'customer_individual', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1175, 3, 'Reports', 'vendor_ledger', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1176, 3, 'Reports', 'vendor_individual', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1177, 3, 'Reports', 'cashbook', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1178, 3, 'Reports', 'income_statement', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1179, 3, 'Reports', 'trial_balance', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1180, 3, 'Reports', 'customer_aging', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1181, 3, 'Reports', 'seller_aging', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1182, 3, 'Reports', 'balance_sheet', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1183, 3, 'Reports', 'short_item_list', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1184, 3, 'Reports', 'vendor_aging', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1185, 3, 'Reports', 'daybook', NULL, 1, '2025-06-04 22:39:43', '2025-06-04 22:39:43'),
(1186, 1, 'Setup', 'company_setup', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1187, 1, 'Setup', 'company_setup', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1188, 1, 'Setup', 'company_setup', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1189, 1, 'Setup', 'company_setup', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1190, 1, 'Setup', 'chart_of_accounts', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1191, 1, 'Setup', 'chart_of_accounts', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1192, 1, 'Setup', 'chart_of_accounts', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1193, 1, 'Setup', 'chart_of_accounts', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1194, 1, 'Setup', 'bank_setup', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1195, 1, 'Setup', 'bank_setup', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1196, 1, 'Setup', 'bank_setup', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1197, 1, 'Setup', 'bank_setup', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1198, 1, 'Setup', 'customer_setup', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1199, 1, 'Setup', 'customer_setup', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1200, 1, 'Setup', 'customer_setup', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1201, 1, 'Setup', 'customer_setup', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1202, 1, 'Setup', 'transport_setup', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1203, 1, 'Setup', 'transport_setup', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1204, 1, 'Setup', 'transport_setup', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1205, 1, 'Setup', 'transport_setup', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1206, 1, 'Setup', 'warehouse_setup', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1207, 1, 'Setup', 'warehouse_setup', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1208, 1, 'Setup', 'warehouse_setup', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1209, 1, 'Setup', 'warehouse_setup', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1210, 1, 'Setup', 'zone_setup', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1211, 1, 'Setup', 'zone_setup', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1212, 1, 'Setup', 'zone_setup', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1213, 1, 'Setup', 'zone_setup', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1214, 1, 'Setup', 'item_setup', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1215, 1, 'Setup', 'item_setup', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1216, 1, 'Setup', 'item_setup', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1217, 1, 'Setup', 'item_setup', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1218, 1, 'Setup', 'item_category', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1219, 1, 'Setup', 'item_category', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1220, 1, 'Setup', 'item_category', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1221, 1, 'Setup', 'item_category', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1222, 1, 'Setup', 'item_subcategory', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1223, 1, 'Setup', 'item_subcategory', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1224, 1, 'Setup', 'item_subcategory', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1225, 1, 'Setup', 'item_subcategory', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1226, 1, 'Setup', 'vendor_setup', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1227, 1, 'Setup', 'vendor_setup', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1228, 1, 'Setup', 'vendor_setup', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1229, 1, 'Setup', 'vendor_setup', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1230, 1, 'Setup', 'batch_setup', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1231, 1, 'Setup', 'batch_setup', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1232, 1, 'Setup', 'batch_setup', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1233, 1, 'Setup', 'batch_setup', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1234, 1, 'Setup', 'branch', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1235, 1, 'Setup', 'branch', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1236, 1, 'Setup', 'branch', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1237, 1, 'Setup', 'branch', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1238, 1, 'Entry', 'sale_invoice', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1239, 1, 'Entry', 'sale_invoice', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1240, 1, 'Entry', 'sale_invoice', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1241, 1, 'Entry', 'sale_invoice', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1242, 1, 'Entry', 'sale_return', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1243, 1, 'Entry', 'sale_return', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1244, 1, 'Entry', 'sale_return', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1245, 1, 'Entry', 'sale_return', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1246, 1, 'Entry', 'pos_bill', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1247, 1, 'Entry', 'pos_bill', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1248, 1, 'Entry', 'pos_bill', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1249, 1, 'Entry', 'pos_bill', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1250, 1, 'Entry', 'purchase_invoice', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1251, 1, 'Entry', 'purchase_invoice', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1252, 1, 'Entry', 'purchase_invoice', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1253, 1, 'Entry', 'purchase_invoice', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1254, 1, 'Entry', 'purchase_return', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1255, 1, 'Entry', 'purchase_return', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1256, 1, 'Entry', 'purchase_return', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1257, 1, 'Entry', 'purchase_return', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1258, 1, 'Entry', 'receive_voucher', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1259, 1, 'Entry', 'receive_voucher', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1260, 1, 'Entry', 'receive_voucher', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1261, 1, 'Entry', 'receive_voucher', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1262, 1, 'Entry', 'receive_voucher2', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1263, 1, 'Entry', 'receive_voucher2', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1264, 1, 'Entry', 'receive_voucher2', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1265, 1, 'Entry', 'receive_voucher2', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1266, 1, 'Entry', 'payment_voucher', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1267, 1, 'Entry', 'payment_voucher', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1268, 1, 'Entry', 'payment_voucher', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1269, 1, 'Entry', 'payment_voucher', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1270, 1, 'Entry', 'journal_voucher', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1271, 1, 'Entry', 'journal_voucher', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1272, 1, 'Entry', 'journal_voucher', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1273, 1, 'Entry', 'journal_voucher', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1274, 1, 'Entry', 'expense_voucher', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1275, 1, 'Entry', 'expense_voucher', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1276, 1, 'Entry', 'expense_voucher', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1277, 1, 'Entry', 'expense_voucher', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1278, 1, 'Entry', 'employee_entry', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1279, 1, 'Entry', 'employee_entry', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1280, 1, 'Entry', 'employee_entry', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1281, 1, 'Entry', 'employee_entry', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1282, 1, 'Entry', 'production', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1283, 1, 'Entry', 'production', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1284, 1, 'Entry', 'production', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1285, 1, 'Entry', 'production', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1286, 1, 'Entry', 'bom', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1287, 1, 'Entry', 'bom', 'add_record', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1288, 1, 'Entry', 'bom', 'edit', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1289, 1, 'Entry', 'bom', 'delete', 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1290, 1, 'Reports', 'recovery_sheet', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1291, 1, 'Reports', 'daily_sale', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1292, 1, 'Reports', 'sale_report', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1293, 1, 'Reports', 'purchase_report', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1294, 1, 'Reports', 'stock_report', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1295, 1, 'Reports', 'customer_ledger', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1296, 1, 'Reports', 'customer_individual', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1297, 1, 'Reports', 'vendor_ledger', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1298, 1, 'Reports', 'vendor_individual', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1299, 1, 'Reports', 'cashbook', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1300, 1, 'Reports', 'income_statement', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1301, 1, 'Reports', 'trial_balance', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1302, 1, 'Reports', 'customer_aging', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1303, 1, 'Reports', 'seller_aging', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1304, 1, 'Reports', 'balance_sheet', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1305, 1, 'Reports', 'short_item_list', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1306, 1, 'Reports', 'vendor_aging', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51'),
(1307, 1, 'Reports', 'daybook', NULL, 1, '2025-06-05 17:28:51', '2025-06-05 17:28:51');

-- --------------------------------------------------------

--
-- Table structure for table `salary_components`
--

CREATE TABLE `salary_components` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `house_rent` decimal(10,2) DEFAULT NULL,
  `medical_allowance` decimal(10,2) DEFAULT NULL,
  `transport_allowance` decimal(10,2) DEFAULT NULL,
  `meal_allowance` decimal(10,2) DEFAULT NULL,
  `phone_allowance` decimal(10,2) DEFAULT NULL,
  `other_allowances` decimal(10,2) DEFAULT NULL,
  `monthly_bonus` decimal(10,2) DEFAULT 0.00,
  `quarterly_bonus` decimal(10,2) DEFAULT 0.00,
  `yearly_bonus` decimal(10,2) DEFAULT 0.00,
  `sales_commission_percentage` decimal(5,2) DEFAULT 0.00,
  `housing_allowance` decimal(10,2) DEFAULT 0.00,
  `gross_salary` decimal(10,2) DEFAULT NULL,
  `effective_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salary_components`
--

INSERT INTO `salary_components` (`id`, `employee_id`, `basic_salary`, `house_rent`, `medical_allowance`, `transport_allowance`, `meal_allowance`, `phone_allowance`, `other_allowances`, `monthly_bonus`, `quarterly_bonus`, `yearly_bonus`, `sales_commission_percentage`, `housing_allowance`, `gross_salary`, `effective_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES
(0, 5, 35000.00, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '0000-00-00', NULL, 'Active', '2025-07-23 08:35:21', '2025-07-23 08:35:21'),
(0, 35, 80000.00, NULL, 0.00, 0.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, '0000-00-00', NULL, 'Active', '2025-08-08 17:39:18', '2025-08-08 17:39:18');

-- --------------------------------------------------------

--
-- Table structure for table `salereturn_invoice`
--

CREATE TABLE `salereturn_invoice` (
  `id` int(11) NOT NULL,
  `bill_no` int(11) NOT NULL,
  `customer_id` varchar(50) DEFAULT NULL,
  `business_name` varchar(255) DEFAULT NULL,
  `transport_id` int(11) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `sale_man_id` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `cell_no` varchar(20) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_bill` decimal(10,2) DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `cash_paid` decimal(10,2) DEFAULT NULL,
  `net_amount` decimal(10,2) DEFAULT NULL,
  `previous_balance` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `selected_bill_no` int(11) DEFAULT NULL,
  `account_id` int(11) NOT NULL DEFAULT 18
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `salereturn_invoice`
--
DELIMITER $$
CREATE TRIGGER `trg_after_salereturn_invoice_delete` AFTER DELETE ON `salereturn_invoice` FOR EACH ROW BEGIN
    -- Remove all related ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'salereturn_invoice' AND reference_id = OLD.id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_salereturn_invoice_insert` AFTER INSERT ON `salereturn_invoice` FOR EACH ROW BEGIN
    -- Only create ledger entries if net_amount > 0
    IF NEW.net_amount > 0 THEN
        -- Debit: Sales Return Account (increasing return expense)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sale Return', 'salereturn_invoice', NEW.id, 18, -- Sales Return account
            NEW.date, 
            CONCAT('Sale Return - ', NEW.bill_no, 
                   CASE WHEN NEW.business_name IS NOT NULL THEN CONCAT(' - ', NEW.business_name) ELSE '' END),
            NEW.net_amount, 0
        );
        
        -- Credit: Trade Debtors/Customer Account (reducing receivable)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sale Return', 'salereturn_invoice', NEW.id, 
            COALESCE(NEW.customer_id, 2), -- Use customer_id or default to Trade Debtors (2)
            NEW.date,
            CONCAT('Sale Return - ', NEW.bill_no,
                   CASE WHEN NEW.business_name IS NOT NULL THEN CONCAT(' - ', NEW.business_name) ELSE '' END),
            0, NEW.net_amount
        );
        
        -- Handle cash refund if any
        IF NEW.cash_paid > 0 THEN
            -- Debit: Cash Account (cash refunded)
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Sale Return', 'salereturn_invoice', NEW.id, 1, -- Cash account
                NEW.date,
                CONCAT('Cash Refund - Return ', NEW.bill_no),
                NEW.cash_paid, 0
            );
            
            -- Credit: Trade Debtors (reducing customer balance)
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Sale Return', 'salereturn_invoice', NEW.id, 
                COALESCE(NEW.customer_id, 2),
                NEW.date,
                CONCAT('Cash Refund - Return ', NEW.bill_no),
                0, NEW.cash_paid
            );
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_salereturn_invoice_update` AFTER UPDATE ON `salereturn_invoice` FOR EACH ROW BEGIN
    -- Delete old ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'salereturn_invoice' AND reference_id = NEW.id;
    
    -- Insert new ledger entries with updated values
    IF NEW.net_amount > 0 THEN
        -- Debit: Sales Return Account
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sale Return', 'salereturn_invoice', NEW.id, 18,
            NEW.date,
            CONCAT('Sale Return - ', NEW.bill_no,
                   CASE WHEN NEW.business_name IS NOT NULL THEN CONCAT(' - ', NEW.business_name) ELSE '' END),
            NEW.net_amount, 0
        );
        
        -- Credit: Trade Debtors/Customer Account
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sale Return', 'salereturn_invoice', NEW.id, 
            COALESCE(NEW.customer_id, 2),
            NEW.date,
            CONCAT('Sale Return - ', NEW.bill_no,
                   CASE WHEN NEW.business_name IS NOT NULL THEN CONCAT(' - ', NEW.business_name) ELSE '' END),
            0, NEW.net_amount
        );
        
        -- Handle cash refund if any
        IF NEW.cash_paid > 0 THEN
            -- Debit: Cash Account
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Sale Return', 'salereturn_invoice', NEW.id, 1,
                NEW.date,
                CONCAT('Cash Refund - Return ', NEW.bill_no),
                NEW.cash_paid, 0
            );
            
            -- Credit: Trade Debtors
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Sale Return', 'salereturn_invoice', NEW.id, 
                COALESCE(NEW.customer_id, 2),
                NEW.date,
                CONCAT('Cash Refund - Return ', NEW.bill_no),
                0, NEW.cash_paid
            );
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_sale_return_stock_delete` AFTER DELETE ON `salereturn_invoice` FOR EACH ROW BEGIN
    DELETE FROM stock_ledger 
    WHERE reference_table = 'salereturn_invoice' AND reference_id = OLD.id;
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'salereturn_invoice' AND reference_id = OLD.id 
    AND account_id IN (32, 33, 34, 36, 37);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_sale_return_stock_insert` AFTER INSERT ON `salereturn_invoice` FOR EACH ROW BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id INT;
    DECLARE v_quantity DECIMAL(15,3);
    DECLARE v_unit_cost DECIMAL(15,3);
    DECLARE v_warehouse_id INT;
    DECLARE v_prev_balance DECIMAL(15,3);
    DECLARE v_inventory_account_id INT;
    
    DECLARE item_cursor CURSOR FOR
        SELECT srii.product_id, 
               (COALESCE(srii.ctn, 0) * COALESCE(srii.packing, 1) + COALESCE(srii.doz, 0) * 12 + COALESCE(srii.pcs, 0)) as qty,
               COALESCE(srii.tp, 0) as cost,
               COALESCE(NEW.warehouse_id, 1) as warehouse,
               COALESCE(p.inventory_account_id, 32) as inv_account
        FROM salereturn_invoice_items srii
        JOIN products p ON srii.product_id = p.id
        WHERE srii.invoice_id = NEW.id
        AND (COALESCE(srii.ctn, 0) * COALESCE(srii.packing, 1) + COALESCE(srii.doz, 0) * 12 + COALESCE(srii.pcs, 0)) > 0
        AND p.inventory_account_id IN (32, 33, 34, 36, 37);
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN item_cursor;
    
    item_loop: LOOP
        FETCH item_cursor INTO v_product_id, v_quantity, v_unit_cost, v_warehouse_id, v_inventory_account_id;
        IF done THEN
            LEAVE item_loop;
        END IF;
        
        SELECT COALESCE(balance_qty, 0) INTO v_prev_balance
        FROM stock_ledger 
        WHERE product_id = v_product_id AND warehouse_id = v_warehouse_id
        ORDER BY id DESC LIMIT 1;
        
        INSERT INTO stock_ledger (
            transaction_type, reference_table, reference_id, product_id, 
            warehouse_id, qty_in, balance_qty, unit_cost, total_cost, transaction_date
        ) VALUES (
            'Sales Return', 'salereturn_invoice', NEW.id, v_product_id,
            v_warehouse_id, v_quantity, (v_prev_balance + v_quantity), v_unit_cost, 
            (v_quantity * v_unit_cost), NEW.date
        );
        
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sales Return', 'salereturn_invoice', NEW.id, v_inventory_account_id,
            NEW.date,
            CONCAT('Sales Return - Product ID: ', v_product_id, ' - Return: ', NEW.bill_no),
            (v_quantity * v_unit_cost), 0
        );
        
    END LOOP;
    
    CLOSE item_cursor;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_sale_return_stock_update` AFTER UPDATE ON `salereturn_invoice` FOR EACH ROW BEGIN
    DELETE FROM stock_ledger 
    WHERE reference_table = 'salereturn_invoice' AND reference_id = NEW.id;
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'salereturn_invoice' AND reference_id = NEW.id 
    AND account_id IN (32, 33, 34, 36, 37);
    
    CALL trg_sale_return_stock_insert_logic(NEW.id, NEW.warehouse_id, NEW.date, NEW.bill_no);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `salereturn_invoice_items`
--

CREATE TABLE `salereturn_invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `packing` varchar(100) DEFAULT NULL,
  `ctn` int(11) DEFAULT NULL,
  `doz` int(11) DEFAULT NULL,
  `pcs` int(11) DEFAULT NULL,
  `fu` int(11) DEFAULT NULL,
  `tp` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `whole_sale_price` decimal(10,2) DEFAULT NULL,
  `disc_percentage` decimal(5,2) DEFAULT NULL,
  `discount_rs` decimal(10,2) DEFAULT NULL,
  `net_value` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_orders`
--

CREATE TABLE `sales_orders` (
  `id` int(11) NOT NULL,
  `serial_no` varchar(50) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `customer_order_ref` varchar(100) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `job_no` varchar(100) DEFAULT NULL,
  `employee_ref` varchar(100) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `customer_code` varchar(50) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `opening_amount` decimal(10,2) DEFAULT NULL,
  `cnic_no` varchar(20) DEFAULT NULL,
  `gst_reg_no` varchar(50) DEFAULT NULL,
  `ntn` varchar(50) DEFAULT NULL,
  `items_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items_data`)),
  `total_quantity` decimal(12,2) DEFAULT NULL,
  `total_value_excl` decimal(12,2) DEFAULT NULL,
  `total_gst_amount` decimal(12,2) DEFAULT NULL,
  `total_value_incl` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_orders`
--

INSERT INTO `sales_orders` (`id`, `serial_no`, `order_date`, `customer_order_ref`, `payment_terms`, `job_no`, `employee_ref`, `delivery_date`, `customer_code`, `customer_name`, `address`, `telephone`, `opening_amount`, `cnic_no`, `gst_reg_no`, `ntn`, `items_data`, `total_quantity`, `total_value_excl`, `total_gst_amount`, `total_value_incl`, `created_at`, `discount_percentage`, `discount_amount`, `net_amount`) VALUES
(19, 'QTN-1', '2025-07-25', '', 'Net 30', '', '', '0000-00-00', '18', 'Wasim G/S', 'Gulshan Karachi', '03338975626', 0.00, '', '', '', '0', 5.00, 14600.00, 0.00, 14600.00, '2025-07-25 22:39:51', 2.00, 292.00, 14308.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales_order_items`
--

CREATE TABLE `sales_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(50) DEFAULT NULL,
  `rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `value_excl` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gst_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gst_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `value_incl` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_order_items`
--

INSERT INTO `sales_order_items` (`id`, `order_id`, `item_code`, `item_name`, `description`, `quantity`, `unit`, `rate`, `value_excl`, `gst_percentage`, `gst_amount`, `value_incl`, `created_at`, `updated_at`) VALUES
(2, 19, '19', 'Popup', 'Popup', 3.00, '0', 200.00, 14400.00, 0.00, 0.00, 14400.00, '2025-07-25 22:39:51', '2025-07-25 22:39:51'),
(3, 19, '20', 'Bleach Small', 'Bleach Small', 2.00, '0', 100.00, 200.00, 0.00, 0.00, 200.00, '2025-07-25 22:39:51', '2025-07-25 22:39:51');

-- --------------------------------------------------------

--
-- Table structure for table `sales_tax`
--

CREATE TABLE `sales_tax` (
  `id` int(11) NOT NULL,
  `bill_no` varchar(50) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `customer_id` varchar(20) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `primary_cell_no` varchar(20) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `transport` varchar(100) DEFAULT NULL,
  `warehouse` varchar(100) DEFAULT NULL,
  `salesman` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_bill` decimal(12,2) DEFAULT NULL,
  `global_discount` decimal(10,2) DEFAULT NULL,
  `freight` decimal(10,2) DEFAULT NULL,
  `final_amount` decimal(12,2) DEFAULT NULL,
  `received` decimal(12,2) DEFAULT NULL,
  `balance` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_tax_type`
--

CREATE TABLE `sales_tax_type` (
  `id` int(11) NOT NULL,
  `tax_type` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_invoice`
--

CREATE TABLE `sale_invoice` (
  `id` int(11) NOT NULL,
  `bill_no` int(11) NOT NULL,
  `customer_id` varchar(255) DEFAULT NULL,
  `business_name` varchar(100) DEFAULT NULL,
  `transport_id` int(11) NOT NULL,
  `dc_no` varchar(255) DEFAULT NULL,
  `warehouse_id` int(11) NOT NULL,
  `sale_man_id` int(11) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `cell_no` varchar(20) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_bill` decimal(10,2) DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `cash_paid` decimal(10,2) DEFAULT NULL,
  `cash_return` decimal(10,0) DEFAULT NULL,
  `net_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `account_id` int(11) NOT NULL DEFAULT 9,
  `is_tax` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_invoice`
--

INSERT INTO `sale_invoice` (`id`, `bill_no`, `customer_id`, `business_name`, `transport_id`, `dc_no`, `warehouse_id`, `sale_man_id`, `address`, `cell_no`, `zone`, `date`, `area`, `remarks`, `total_bill`, `discount_percentage`, `discount_amount`, `cash_paid`, `cash_return`, `net_amount`, `created_at`, `account_id`, `is_tax`) VALUES
(9, 0, '20', 'InnovaTech', 0, NULL, 0, 0, NULL, '03342616587', '', '2025-08-06', '', '', 56640.00, NULL, 0.00, 0.00, NULL, 56640.00, '2025-08-06 07:49:36', 9, 1),
(10, 0, '20', 'InnovaTech', 0, NULL, 0, 0, NULL, '03342616587', '', '2025-08-06', '', '', 56640.00, NULL, 0.00, 0.00, NULL, 56640.00, '2025-08-06 07:49:41', 9, 1),
(11, 1, '23', 'Asif G/s', 0, '', 0, 0, '', '', '', '2025-08-15', '', '', 12000.00, 0.00, 0.00, 0.00, NULL, 12000.00, '2025-08-15 11:01:02', 9, 0),
(12, 0, '18', 'Wasim G/S', 1, NULL, 1, 1, 'Gulshan Karachi', '03338975626', '', '2025-07-25', '', '', 14600.00, 2.00, 292.00, 0.00, NULL, 14308.00, '2025-08-19 22:01:03', 9, 0),
(13, 0, '18', 'Wasim G/S', 1, NULL, 1, 1, 'Gulshan Karachi', '03338975626', '', '2025-07-25', '', '', 14600.00, 2.00, 292.00, 0.00, NULL, 14308.00, '2025-08-19 22:06:32', 9, 0),
(14, 0, '18', 'Wasim G/S', 1, NULL, 1, 1, 'Gulshan Karachi', '03338975626', '', '2025-07-25', '', '', 14600.00, 2.00, 292.00, 0.00, NULL, 14308.00, '2025-08-19 22:10:19', 9, 0),
(15, 0, '18', 'Wasim G/S', 1, NULL, 1, 1, 'Gulshan Karachi', '03338975626', '', '2025-07-25', '', '', 14600.00, 2.00, 292.00, 0.00, NULL, 14308.00, '2025-08-19 22:12:38', 9, 0),
(17, 2, '26', 'Abdul Qudus Bhai', 0, '', 0, 0, 'Shershah', '03215987841', '', '2025-08-20', '', '', 90000.00, 0.00, 0.00, 0.00, NULL, 90000.00, '2025-08-20 07:28:31', 9, 0),
(18, 3, '20', 'InnovaTech', 0, '', 0, 35, 'Saadi Town Block 1', '03342616587', '', '2025-08-20', '', '', 10000.00, 0.00, 0.00, 0.00, NULL, 10000.00, '2025-08-20 07:49:40', 9, 0),
(19, 4, '26', 'Abdul Qudus Bhai', 0, '', 0, 35, 'Shershah', '03215987841', '', '2025-08-20', '', '', 3000.00, 0.00, 0.00, 0.00, NULL, 3000.00, '2025-08-20 08:28:07', 9, 0),
(20, 0, '18', 'Wasim G/S', 1, NULL, 1, 1, 'Gulshan Karachi', '03338975626', '', '2025-07-25', '', '', 14600.00, 2.00, 292.00, 0.00, NULL, 14308.00, '2025-09-08 07:11:25', 9, 0);

--
-- Triggers `sale_invoice`
--
DELIMITER $$
CREATE TRIGGER `trg_after_sale_invoice_delete` AFTER DELETE ON `sale_invoice` FOR EACH ROW BEGIN
    -- Remove all related ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'sale_invoice' AND reference_id = OLD.id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_sale_invoice_insert` AFTER INSERT ON `sale_invoice` FOR EACH ROW BEGIN
    -- Debit: Trade Debtors (Customer Account - increases receivables)
    -- Account ID 2 is typically Trade Debtors based on the voucher patterns
    INSERT INTO accounting_ledger (
        transaction_type, reference_table, reference_id, account_id, 
        date, description, debit, credit
    ) VALUES (
        'Sale Invoice', 'sale_invoice', NEW.id, 2, 
        NEW.date, 
        CONCAT('Sale Invoice #', NEW.bill_no, ' - ', COALESCE(NEW.business_name, 'Customer')),
        NEW.net_amount, 0
    );
    
    -- Credit: Sales Account (increases revenue)
    -- Using the account_id from sale_invoice table (default 9)
    INSERT INTO accounting_ledger (
        transaction_type, reference_table, reference_id, account_id, 
        date, description, debit, credit
    ) VALUES (
        'Sale Invoice', 'sale_invoice', NEW.id, NEW.account_id,
        NEW.date,
        CONCAT('Sale Invoice #', NEW.bill_no, ' - ', COALESCE(NEW.business_name, 'Customer')),
        0, NEW.total_bill
    );
    
    -- Handle discount if applicable (Credit: Discount Allowed Account)
    IF NEW.discount_amount > 0 THEN
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sale Invoice', 'sale_invoice', NEW.id, 16, -- Assuming 16 is Discount Allowed
            NEW.date,
            CONCAT('Discount on Invoice #', NEW.bill_no, ' - ', COALESCE(NEW.business_name, 'Customer')),
            NEW.discount_amount, 0
        );
    END IF;
    
    -- Handle cash payment if applicable
    IF NEW.cash_paid > 0 THEN
        -- Credit: Trade Debtors (reduces receivables)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sale Invoice', 'sale_invoice', NEW.id, 2,
            NEW.date,
            CONCAT('Cash received on Invoice #', NEW.bill_no, ' - ', COALESCE(NEW.business_name, 'Customer')),
            0, NEW.cash_paid
        );
        
        -- Debit: Cash Account (increases cash)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sale Invoice', 'sale_invoice', NEW.id, 1, -- Assuming 1 is Cash Account
            NEW.date,
            CONCAT('Cash received on Invoice #', NEW.bill_no, ' - ', COALESCE(NEW.business_name, 'Customer')),
            NEW.cash_paid, 0
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_sale_invoice_update` AFTER UPDATE ON `sale_invoice` FOR EACH ROW BEGIN
    -- Delete old ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'sale_invoice' AND reference_id = NEW.id;
    
    -- Insert new ledger entries with updated values
    -- Debit: Trade Debtors (Customer Account)
    INSERT INTO accounting_ledger (
        transaction_type, reference_table, reference_id, account_id, 
        date, description, debit, credit
    ) VALUES (
        'Sale Invoice', 'sale_invoice', NEW.id, 2,
        NEW.date,
        CONCAT('Sale Invoice #', NEW.bill_no, ' - ', COALESCE(NEW.business_name, 'Customer')),
        NEW.net_amount, 0
    );
    
    -- Credit: Sales Account
    INSERT INTO accounting_ledger (
        transaction_type, reference_table, reference_id, account_id, 
        date, description, debit, credit
    ) VALUES (
        'Sale Invoice', 'sale_invoice', NEW.id, NEW.account_id,
        NEW.date,
        CONCAT('Sale Invoice #', NEW.bill_no, ' - ', COALESCE(NEW.business_name, 'Customer')),
        0, NEW.total_bill
    );
    
    -- Handle discount if applicable
    IF NEW.discount_amount > 0 THEN
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sale Invoice', 'sale_invoice', NEW.id, 16,
            NEW.date,
            CONCAT('Discount on Invoice #', NEW.bill_no, ' - ', COALESCE(NEW.business_name, 'Customer')),
            NEW.discount_amount, 0
        );
    END IF;
    
    -- Handle cash payment if applicable
    IF NEW.cash_paid > 0 THEN
        -- Credit: Trade Debtors
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sale Invoice', 'sale_invoice', NEW.id, 2,
            NEW.date,
            CONCAT('Cash received on Invoice #', NEW.bill_no, ' - ', COALESCE(NEW.business_name, 'Customer')),
            0, NEW.cash_paid
        );
        
        -- Debit: Cash Account
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sale Invoice', 'sale_invoice', NEW.id, 1,
            NEW.date,
            CONCAT('Cash received on Invoice #', NEW.bill_no, ' - ', COALESCE(NEW.business_name, 'Customer')),
            NEW.cash_paid, 0
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_sale_invoice_stock_delete` AFTER DELETE ON `sale_invoice` FOR EACH ROW BEGIN
    DELETE FROM stock_ledger 
    WHERE reference_table = 'sale_invoice' AND reference_id = OLD.id;
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'sale_invoice' AND reference_id = OLD.id 
    AND account_id IN (32, 33, 34, 36, 37);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_sale_invoice_stock_insert` AFTER INSERT ON `sale_invoice` FOR EACH ROW BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id INT;
    DECLARE v_quantity DECIMAL(15,3);
    DECLARE v_unit_cost DECIMAL(15,3);
    DECLARE v_warehouse_id INT;
    DECLARE v_prev_balance DECIMAL(15,3);
    DECLARE v_inventory_account_id INT;
    
    DECLARE item_cursor CURSOR FOR
        SELECT ii.product_id, 
               (COALESCE(ii.ctn, 0) * COALESCE(ii.packing, 1) + COALESCE(ii.doz, 0) * 12 + COALESCE(ii.pcs, 0)) as qty,
               COALESCE(ii.tp, 0) as cost,
               COALESCE(NEW.warehouse_id, 1) as warehouse,
               COALESCE(p.inventory_account_id, 32) as inv_account
        FROM invoice_items ii
        JOIN products p ON ii.product_id = p.id
        WHERE ii.invoice_id = NEW.id
        AND (COALESCE(ii.ctn, 0) * COALESCE(ii.packing, 1) + COALESCE(ii.doz, 0) * 12 + COALESCE(ii.pcs, 0)) > 0
        AND p.inventory_account_id IN (32, 33, 34, 36, 37);
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN item_cursor;
    
    item_loop: LOOP
        FETCH item_cursor INTO v_product_id, v_quantity, v_unit_cost, v_warehouse_id, v_inventory_account_id;
        IF done THEN
            LEAVE item_loop;
        END IF;
        
        SELECT COALESCE(balance_qty, 0) INTO v_prev_balance
        FROM stock_ledger 
        WHERE product_id = v_product_id AND warehouse_id = v_warehouse_id
        ORDER BY id DESC LIMIT 1;
        
        INSERT INTO stock_ledger (
            transaction_type, reference_table, reference_id, product_id, 
            warehouse_id, account_id, qty_out, balance_qty, unit_cost, total_cost, transaction_date
        ) VALUES (
            'Sales Invoice', 'sale_invoice', NEW.id, v_product_id,
            v_warehouse_id, v_inventory_account_id, v_quantity, (v_prev_balance - v_quantity), v_unit_cost, 
            (v_quantity * v_unit_cost), NEW.date
        );
        
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Sales Invoice', 'sale_invoice', NEW.id, v_inventory_account_id,
            NEW.date,
            CONCAT('Sale - Product ID: ', v_product_id, ' - Invoice: ', NEW.bill_no),
            0, (v_quantity * v_unit_cost)
        );
        
    END LOOP;
    
    CLOSE item_cursor;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_sale_invoice_stock_update` AFTER UPDATE ON `sale_invoice` FOR EACH ROW BEGIN
    DELETE FROM stock_ledger 
    WHERE reference_table = 'sale_invoice' AND reference_id = NEW.id;
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'sale_invoice' AND reference_id = NEW.id 
    AND account_id IN (32, 33, 34, 36, 37);
    
    CALL trg_sale_invoice_stock_insert_logic(NEW.id, NEW.warehouse_id, NEW.date, NEW.bill_no);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `sale_tax_items`
--

CREATE TABLE `sale_tax_items` (
  `id` int(11) NOT NULL,
  `sales_tax_id` int(11) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `qty` decimal(10,2) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `disc_percent` decimal(5,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `value_excl` decimal(10,2) DEFAULT NULL,
  `sales_tax` decimal(5,2) DEFAULT NULL,
  `sales_tax_amt` decimal(10,2) DEFAULT NULL,
  `value_incl` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_types`
--

CREATE TABLE `service_types` (
  `id` int(11) NOT NULL,
  `service_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_types`
--

INSERT INTO `service_types` (`id`, `service_name`, `is_active`, `created_at`) VALUES
(1, 'Web Development', 0, '2025-08-19 20:12:40'),
(2, 'SEO', 1, '2025-08-19 20:12:40'),
(3, 'Social Media Marketing', 1, '2025-08-19 20:12:40'),
(4, 'Test', 1, '2025-08-19 21:00:48');

-- --------------------------------------------------------

--
-- Table structure for table `stock_ledger`
--

CREATE TABLE `stock_ledger` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `product_packing_id` int(11) DEFAULT NULL,
  `warehouse_id` int(11) NOT NULL,
  `machine_id` int(11) DEFAULT NULL,
  `batch_no` varchar(100) DEFAULT NULL,
  `lot_no` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `mfg_date` date DEFAULT NULL,
  `production_order_id` int(11) DEFAULT NULL,
  `reference_table` varchar(100) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `qty_in` decimal(15,3) DEFAULT 0.000,
  `qty_out` decimal(15,3) DEFAULT 0.000,
  `base_qty_in` decimal(15,3) DEFAULT 0.000,
  `base_qty_out` decimal(15,3) DEFAULT 0.000,
  `balance_qty` decimal(15,3) DEFAULT 0.000,
  `base_balance_qty` decimal(15,3) DEFAULT 0.000,
  `unit_conversion` decimal(10,3) DEFAULT 1.000,
  `unit_cost` decimal(15,4) DEFAULT 0.0000,
  `total_cost` decimal(15,4) DEFAULT 0.0000,
  `transaction_type` enum('Purchase Invoice','Purchase Return','Sales Invoice','Sales Return','Production Issue','Production Completion','Stock Transfer','Adjustment','Opening Stock') NOT NULL,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_ledger`
--

INSERT INTO `stock_ledger` (`id`, `product_id`, `account_id`, `product_packing_id`, `warehouse_id`, `machine_id`, `batch_no`, `lot_no`, `expiry_date`, `mfg_date`, `production_order_id`, `reference_table`, `reference_id`, `qty_in`, `qty_out`, `base_qty_in`, `base_qty_out`, `balance_qty`, `base_balance_qty`, `unit_conversion`, `unit_cost`, `total_cost`, `transaction_type`, `transaction_date`, `created_at`) VALUES
(104, 2, 34, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 20, 5.000, 0.000, 0.000, 0.000, 5.000, 0.000, 1.000, 800.0000, 4000.0000, 'Purchase Invoice', '2025-08-05', '2025-08-05 14:11:09'),
(105, 3, 34, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 20, 20.000, 0.000, 0.000, 0.000, 20.000, 0.000, 1.000, 5.0000, 100.0000, 'Purchase Invoice', '2025-08-05', '2025-08-05 14:11:09'),
(106, 4, 34, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 20, 2.000, 0.000, 0.000, 0.000, 2.000, 0.000, 1.000, 500.0000, 1000.0000, 'Purchase Invoice', '2025-08-05', '2025-08-05 14:11:09'),
(107, 5, 34, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 20, 1.000, 0.000, 0.000, 0.000, 1.000, 0.000, 1.000, 1200.0000, 1200.0000, 'Purchase Invoice', '2025-08-05', '2025-08-05 14:11:09'),
(108, 6, 34, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 20, 3.000, 0.000, 0.000, 0.000, 3.000, 0.000, 1.000, 1000.0000, 3000.0000, 'Purchase Invoice', '2025-08-05', '2025-08-05 14:11:09'),
(109, 7, 34, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 20, 4.000, 0.000, 0.000, 0.000, 4.000, 0.000, 1.000, 5.0000, 20.0000, 'Purchase Invoice', '2025-08-05', '2025-08-05 14:11:09'),
(123, 5, 34, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'wip_issue_items', 39, 0.000, 1.000, 0.000, 0.000, -1.000, 0.000, 1.000, 1200.0000, 1200.0000, 'Production Issue', '2025-08-05', '2025-08-05 14:31:32'),
(124, 5, 35, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'wip_issue_items', 39, 1.000, 0.000, 0.000, 0.000, 1.000, 0.000, 1.000, 1200.0000, 1200.0000, 'Production Issue', '2025-08-05', '2025-08-05 14:31:32'),
(125, 6, 34, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'wip_issue_items', 40, 0.000, 3.000, 0.000, 0.000, -3.000, 0.000, 1.000, 1000.0000, 3000.0000, 'Production Issue', '2025-08-05', '2025-08-05 14:31:32'),
(126, 6, 35, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'wip_issue_items', 40, 3.000, 0.000, 0.000, 0.000, 3.000, 0.000, 1.000, 1000.0000, 3000.0000, 'Production Issue', '2025-08-05', '2025-08-05 14:31:32'),
(127, 7, 34, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'wip_issue_items', 41, 0.000, 4.000, 0.000, 0.000, -4.000, 0.000, 1.000, 5.0000, 20.0000, 'Production Issue', '2025-08-05', '2025-08-05 14:31:32'),
(128, 7, 35, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'wip_issue_items', 41, 4.000, 0.000, 0.000, 0.000, 4.000, 0.000, 1.000, 5.0000, 20.0000, 'Production Issue', '2025-08-05', '2025-08-05 14:31:32'),
(129, 3, 34, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'wip_issue_items', 42, 0.000, 20.000, 0.000, 0.000, -20.000, 0.000, 1.000, 5.0000, 100.0000, 'Production Issue', '2025-08-05', '2025-08-05 14:31:32'),
(130, 3, 35, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'wip_issue_items', 42, 20.000, 0.000, 0.000, 0.000, 20.000, 0.000, 1.000, 5.0000, 100.0000, 'Production Issue', '2025-08-05', '2025-08-05 14:31:32'),
(131, 4, 34, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'wip_issue_items', 43, 0.000, 2.000, 0.000, 0.000, -2.000, 0.000, 1.000, 500.0000, 1000.0000, 'Production Issue', '2025-08-05', '2025-08-05 14:31:32'),
(132, 4, 35, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'wip_issue_items', 43, 2.000, 0.000, 0.000, 0.000, 2.000, 0.000, 1.000, 500.0000, 1000.0000, 'Production Issue', '2025-08-05', '2025-08-05 14:31:32'),
(133, 2, 34, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'wip_issue_items', 44, 0.000, 5.000, 0.000, 0.000, -5.000, 0.000, 1.000, 800.0000, 4000.0000, 'Production Issue', '2025-08-05', '2025-08-05 14:31:32'),
(134, 2, 35, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'wip_issue_items', 44, 5.000, 0.000, 0.000, 0.000, 5.000, 0.000, 1.000, 800.0000, 4000.0000, 'Production Issue', '2025-08-05', '2025-08-05 14:31:32'),
(135, 1, 36, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'production_completion_items', 8, 1.000, 0.000, 1.000, 0.000, 0.000, 0.000, 1.000, 0.0000, 0.0000, 'Production Completion', '2025-08-05', '2025-08-05 14:40:23'),
(136, 2, 35, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'production_completion_items', 8, 0.000, 5.000, 0.000, 5.000, 0.000, 0.000, 1.000, 0.0000, 0.0000, 'Production Completion', '2025-08-05', '2025-08-05 14:40:23'),
(137, 3, 35, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'production_completion_items', 8, 0.000, 20.000, 0.000, 20.000, 0.000, 0.000, 1.000, 0.0000, 0.0000, 'Production Completion', '2025-08-05', '2025-08-05 14:40:23'),
(138, 4, 35, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'production_completion_items', 8, 0.000, 2.000, 0.000, 2.000, 0.000, 0.000, 1.000, 0.0000, 0.0000, 'Production Completion', '2025-08-05', '2025-08-05 14:40:23'),
(139, 5, 35, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'production_completion_items', 8, 0.000, 1.000, 0.000, 1.000, 0.000, 0.000, 1.000, 0.0000, 0.0000, 'Production Completion', '2025-08-05', '2025-08-05 14:40:23'),
(140, 6, 35, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'production_completion_items', 8, 0.000, 3.000, 0.000, 3.000, 0.000, 0.000, 1.000, 0.0000, 0.0000, 'Production Completion', '2025-08-05', '2025-08-05 14:40:23'),
(141, 7, 35, NULL, 5, NULL, NULL, NULL, NULL, NULL, 2, 'production_completion_items', 8, 0.000, 4.000, 0.000, 4.000, 0.000, 0.000, 1.000, 0.0000, 0.0000, 'Production Completion', '2025-08-05', '2025-08-05 14:40:23'),
(143, 2, 34, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 21, 2.000, 0.000, 0.000, 0.000, 7.000, 0.000, 1.000, 800.0000, 1600.0000, 'Purchase Invoice', '2025-08-05', '2025-08-05 15:38:39'),
(144, 3, 34, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 21, 5.000, 0.000, 0.000, 0.000, 25.000, 0.000, 1.000, 5.0000, 25.0000, 'Purchase Invoice', '2025-08-05', '2025-08-05 15:38:39'),
(145, 4, 34, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 21, 6.000, 0.000, 0.000, 0.000, 8.000, 0.000, 1.000, 500.0000, 3000.0000, 'Purchase Invoice', '2025-08-05', '2025-08-05 15:38:39'),
(151, 1, 36, NULL, 4, NULL, NULL, NULL, NULL, NULL, NULL, 'sale_invoice', 11, 0.000, 1.000, 0.000, 1.000, 0.000, 0.000, 1.000, 12000.0000, 12000.0000, 'Sales Invoice', '2025-08-15', '2025-08-15 11:01:02'),
(152, 10, 33, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 22, 25.000, 0.000, 0.000, 0.000, 25.000, 0.000, 1.000, 177.0000, 4425.0000, 'Purchase Invoice', '2025-08-15', '2025-08-15 11:44:00'),
(162, 7, 34, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'sale_invoice', 17, 0.000, 100.000, 0.000, 100.000, 0.000, -104.000, 1.000, 1000.0000, 100000.0000, 'Sales Invoice', '2025-08-20', '2025-08-20 07:28:31'),
(163, 9, 33, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'sale_invoice', 18, 0.000, 20.000, 0.000, 20.000, 0.000, -20.000, 1.000, 500.0000, 10000.0000, 'Sales Invoice', '2025-08-20', '2025-08-20 07:49:40'),
(165, 9, 33, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 24, 100.000, 0.000, 0.000, 0.000, 100.000, 0.000, 1.000, 400.0000, 40000.0000, 'Purchase Invoice', '2025-08-20', '2025-08-20 07:58:39'),
(166, 9, 33, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 25, 10.000, 0.000, 0.000, 0.000, 110.000, 0.000, 1.000, 400.0000, 4000.0000, 'Purchase Invoice', '2025-08-20', '2025-08-20 08:01:17'),
(167, 9, 33, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'purchase_invoice', 26, 111.000, 0.000, 0.000, 0.000, 221.000, 0.000, 1.000, 4.0000, 444.0000, 'Purchase Invoice', '2025-08-20', '2025-08-20 08:02:17'),
(168, 9, 33, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'sale_invoice', 19, 0.000, 6.000, 0.000, 6.000, 0.000, -26.000, 1.000, 500.0000, 3000.0000, 'Sales Invoice', '2025-08-20', '2025-08-20 08:28:07');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfers`
--

CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `from_warehouse_id` int(11) NOT NULL,
  `to_warehouse_id` int(11) NOT NULL,
  `qty` decimal(15,3) NOT NULL,
  `transfer_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_wastage`
--

CREATE TABLE `stock_wastage` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `qty` decimal(15,3) NOT NULL,
  `wastage_type` enum('Normal','Abnormal') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subcategories`
--

CREATE TABLE `subcategories` (
  `id` int(11) NOT NULL,
  `subcategory` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subcategories`
--

INSERT INTO `subcategories` (`id`, `subcategory`, `category`) VALUES
(3, 'Bleach', 'Cream'),
(2, 'Box', 'Tissue'),
(1, 'Roll', 'Tissue'),
(4, 'Whitning', 'Cream'),
(5, 'kiryana', 'kiryana');

-- --------------------------------------------------------

--
-- Table structure for table `sub_accounts`
--

CREATE TABLE `sub_accounts` (
  `id` int(11) NOT NULL,
  `account_head_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sub_accounts`
--

INSERT INTO `sub_accounts` (`id`, `account_head_id`, `name`, `parent_id`) VALUES
(1, 1, 'Narration', NULL),
(2, 1, 'Prepaid Rent', NULL),
(3, 1, 'Land & Building', NULL),
(4, 1, 'Office Equipment', NULL),
(5, 1, 'Furniture & Fixtures', NULL),
(6, 1, 'Vehicles', NULL),
(7, 1, 'Goodwill', NULL),
(8, 1, 'Software License', NULL),
(9, 1, 'Patents', NULL),
(10, 2, 'Short-term Loan', NULL),
(11, 2, 'Bank Loan', NULL),
(12, 2, 'Lease Payable', NULL),
(13, 2, 'Bonds Payable', NULL),
(14, 3, 'Capital', NULL),
(15, 3, 'Drawings', NULL),
(16, 3, 'Retained Earnings', NULL),
(17, 3, 'Reserves', NULL),
(18, 4, 'Sales Revenue', NULL),
(19, 4, 'Service Income', NULL),
(20, 4, 'Consultancy Income', NULL),
(21, 4, 'Interest Income', NULL),
(22, 4, 'Rent Received', NULL),
(23, 4, 'Gain on Sale of Asset', NULL),
(24, 5, 'Utility', NULL),
(25, 5, 'Rent Expense', NULL),
(26, 5, 'Interest Expense', NULL),
(27, 5, 'Loss on Asset Disposal', NULL),
(28, 5, 'Depreciation', NULL),
(29, 5, 'Amortization', NULL),
(30, 1, 'Other Receivable', NULL),
(31, 2, 'Other Payable', NULL),
(33, 1, 'Liquid Asset', NULL),
(74, 1, 'Current Assets', NULL),
(75, NULL, 'Bank Account', 74),
(76, NULL, 'Accounts Receivable', 74),
(77, 4, 'Operating Revenue', NULL),
(78, 4, 'Non-Operating Revenue', NULL),
(79, 2, 'Current Liabilities', NULL),
(80, NULL, 'Accounts Payable', 79),
(81, 4, 'Operating Revenue (Contra)', NULL),
(82, 5, 'Cost of Goods Sold', NULL),
(83, 5, 'COGS (Contra)', NULL),
(84, NULL, 'Inventory', 74),
(85, 5, 'Operating Expense', NULL),
(86, NULL, 'Payroll Expense', 85),
(87, NULL, 'Payroll Liabilities', 79),
(88, NULL, 'Inventory', 74),
(90, 5, 'Manufacturing Expenses', NULL),
(91, NULL, 'Direct Expenses', 90),
(92, NULL, 'Indirect Expenses', 90),
(93, NULL, 'Factory Overheads', 92),
(94, 1, 'Goodwill Land FMV', 7);

-- --------------------------------------------------------

--
-- Table structure for table `taxes`
--

CREATE TABLE `taxes` (
  `id` int(11) NOT NULL,
  `tax_name` varchar(100) NOT NULL,
  `tax_percent` decimal(5,2) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `taxes`
--

INSERT INTO `taxes` (`id`, `tax_name`, `tax_percent`, `status`, `created_at`) VALUES
(1, 'Sales Tax', 18.00, 'Active', '2025-08-01 20:50:44'),
(2, 'Further Tax', 3.00, 'Active', '2025-08-05 14:57:17');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_types`
--

CREATE TABLE `transaction_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaction_types`
--

INSERT INTO `transaction_types` (`id`, `name`) VALUES
(1, 'Buy'),
(3, 'Rent'),
(2, 'Sell');

-- --------------------------------------------------------

--
-- Table structure for table `transport`
--

CREATE TABLE `transport` (
  `id` int(11) NOT NULL,
  `transport_name` varchar(255) NOT NULL,
  `transporter_name` varchar(255) NOT NULL,
  `primary_cell_no` varchar(16) NOT NULL,
  `secondary_cell_no` varchar(20) DEFAULT NULL,
  `address` text NOT NULL,
  `city_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transport`
--

INSERT INTO `transport` (`id`, `transport_name`, `transporter_name`, `primary_cell_no`, `secondary_cell_no`, `address`, `city_id`) VALUES
(1, 'Malik Goods', 'hanif', '+92300767654', NULL, '', 1),
(2, 'M. Younus Cargo', 'Zubair', '', NULL, 'Tower', 1),
(3, 'Qamar Zaman Goods', 'Qurban', '+923219876543', NULL, '', 1),
(4, 'qw', 'sad', '324', '342', 'asdfas', 1);

-- --------------------------------------------------------

--
-- Table structure for table `unit`
--

CREATE TABLE `unit` (
  `id` int(11) NOT NULL,
  `unit_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unit`
--

INSERT INTO `unit` (`id`, `unit_name`) VALUES
(1, 'Pcs'),
(2, 'Nos'),
(3, 'Kg'),
(4, 'g'),
(5, 't'),
(6, 'lb'),
(7, 'oz'),
(9, 'm'),
(10, 'cm'),
(11, 'mm'),
(12, 'in'),
(13, 'ft'),
(14, 'L'),
(15, 'ml'),
(16, 'gal'),
(17, 'bbl'),
(18, 'box'),
(19, 'ctn'),
(20, 'case'),
(21, 'pack'),
(22, 'bag'),
(23, 'sack'),
(24, 'barrel');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'user',
  `created_at` datetime DEFAULT current_timestamp(),
  `privileges` longtext DEFAULT NULL,
  `blocked` tinyint(1) DEFAULT 0,
  `last_activity` timestamp NULL DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `privileges`, `blocked`, `last_activity`, `email`, `is_active`, `first_name`, `last_name`, `role_id`, `is_admin`) VALUES
(0, 'InnovaTech', '$2y$10$EVqi7dElOBVXvLstj4ZFOuWJvyUH4TfeW6kQtXjsEZCLMW/LtoB62', 'user', '2025-05-01 17:35:08', NULL, 0, NULL, 'innova.tech213@gmail.com', 1, 'zahid', 'Ghori', 1, 0),
(1, 'admin', '$2y$10$rfRRH3bw1pLQ12vM6h3m7OMKX4aOlHzxWba9jXv//PtdMrBZ8RvQO', 'admin', '2025-03-08 17:09:31', NULL, 0, '2025-08-26 06:10:38', 'admin@gmail.com', 1, 'Admin', NULL, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `vandor_id` int(11) NOT NULL,
  `customer_vendor_type` enum('Customer','Vendor','Both') NOT NULL,
  `vandor_name` varchar(100) NOT NULL,
  `business_name` varchar(100) DEFAULT NULL,
  `cnic_no` varchar(15) DEFAULT NULL,
  `primary_cell_no` varchar(20) DEFAULT NULL,
  `secondary_cell_no` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `zone` varchar(50) DEFAULT NULL,
  `area` varchar(50) DEFAULT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `register_sales_tax` enum('yes','no') DEFAULT 'no',
  `whole_sale` enum('yes','no') DEFAULT 'no',
  `black_list` tinyint(1) DEFAULT 0,
  `sales_tax_no` varchar(50) DEFAULT NULL,
  `ntn_no` varchar(50) DEFAULT NULL,
  `credit_limit` decimal(10,2) DEFAULT 0.00,
  `credit_period` int(11) DEFAULT 0,
  `discount` decimal(5,2) DEFAULT 0.00,
  `Debit` decimal(10,2) DEFAULT 0.00,
  `Credit` decimal(10,2) DEFAULT 0.00,
  `alt` decimal(5,2) DEFAULT 0.00,
  `filer` tinyint(1) DEFAULT 0,
  `P_CellNo` varchar(20) DEFAULT NULL,
  `S_CellNo` varchar(20) DEFAULT NULL,
  `register` enum('yes','no') DEFAULT 'no',
  `NTH_NO` varchar(50) DEFAULT NULL,
  `Sales` varchar(50) DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `opening_amount` decimal(10,2) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `debit_amount` decimal(10,2) DEFAULT NULL,
  `credit_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `account_id` int(11) NOT NULL DEFAULT 14
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`vandor_id`, `customer_vendor_type`, `vandor_name`, `business_name`, `cnic_no`, `primary_cell_no`, `secondary_cell_no`, `address`, `email`, `zone`, `area`, `zone_id`, `area_id`, `register_sales_tax`, `whole_sale`, `black_list`, `sales_tax_no`, `ntn_no`, `credit_limit`, `credit_period`, `discount`, `Debit`, `Credit`, `alt`, `filer`, `P_CellNo`, `S_CellNo`, `register`, `NTH_NO`, `Sales`, `invoice_number`, `opening_amount`, `invoice_date`, `debit_amount`, `credit_amount`, `created_at`, `account_id`) VALUES
(60, 'Vendor', 'Rasid &amp; Sons', 'Rasid &amp; Sons', '', '03326521545', '', 'Near Tamoria Tahna North Nazimabad', '', '', '', NULL, NULL, 'no', 'no', 0, '', '', 0.00, 0, 0.00, 0.00, 12000.00, 0.00, 0, '03326521545', '', 'no', '', '', '', 0.00, '0000-00-00', 0.00, 12000.00, '2025-08-15 11:05:56', 14),
(62, 'Vendor', 'HAJI SHAHNAWAZ MEMON', 'HAJI SHAHNAWAZ MEMON', '', '', '', 'Ali block Bahria Town Karachi', 'innova.tech213@gmail.com', '', '', NULL, NULL, 'no', 'no', 0, '', '', 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0, '', '', 'no', '', '', '', 0.00, '0000-00-00', 0.00, 0.00, '2025-08-15 11:46:14', 14),
(63, 'Vendor', 'Aziz Ullah', 'Aziz Ullah', '', '', '', 'North Karachi.', 'aziz@gmail.com', '', '', NULL, NULL, 'no', 'no', 0, '4556567786754623452314231423', '1223344556', 0.00, 0, 10.00, 501001.00, 0.00, 0.00, 0, '', '', 'no', '1223344556', '4556567786754623452314231423', '', 0.00, '0000-00-00', 501001.00, 0.00, '2025-08-18 08:23:29', 14),
(64, 'Vendor', 'akt', 'akt', '', '', '', 'hi', '', '', '', NULL, NULL, 'no', 'yes', 0, '', '', 0.00, 0, 10.00, 0.00, 0.00, 0.00, 0, '', '', 'no', '', '', '', 0.00, '0000-00-00', 0.00, 0.00, '2025-08-26 07:57:38', 14),
(65, 'Both', 'Hello World', 'Hello World', '12345-1234567-1', '02132578411', '', 'Khi', 'HE@gmail.com', 'Gulshan E Jauhar', '', NULL, NULL, 'yes', 'yes', 0, '12-34-3211-211-11', '1234567', 0.00, 0, 10.00, 0.00, 0.00, 0.00, 1, '02132578411', '', 'yes', '1234567', '12-34-3211-211-11', '', 0.00, '0000-00-00', 0.00, 0.00, '2025-09-02 08:08:07', 14);

--
-- Triggers `vendors`
--
DELIMITER $$
CREATE TRIGGER `trg_after_vendors_delete` AFTER DELETE ON `vendors` FOR EACH ROW BEGIN
    -- Remove all related opening balance ledger entries
    DELETE FROM accounting_ledger 
    WHERE reference_table = 'vendors' AND reference_id = OLD.vandor_id 
    AND transaction_type = 'Vendor Opening Balance';
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_vendors_insert` AFTER INSERT ON `vendors` FOR EACH ROW BEGIN
    -- Handle debit opening balance (vendor advance/prepayment)
    IF NEW.Debit > 0 THEN
        -- Debit: Vendor Advances (asset - we paid in advance)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Vendor Opening Balance', 'vendors', NEW.vandor_id, 24, -- Vendor Advances
            COALESCE(NEW.invoice_date, CURDATE()),
            CONCAT('Opening Advance - ', NEW.vandor_name),
            NEW.Debit, 0
        );
        
        -- Credit: Capital Account (balancing entry)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Vendor Opening Balance', 'vendors', NEW.vandor_id, 1, -- Capital account
            COALESCE(NEW.invoice_date, CURDATE()),
            CONCAT('Opening Advance - ', NEW.vandor_name),
            0, NEW.Debit
        );
    END IF;
    
    -- Handle credit opening balance (we owe vendor)
    IF NEW.Credit > 0 THEN
        -- Debit: Capital Account
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Vendor Opening Balance', 'vendors', NEW.vandor_id, 1,
            COALESCE(NEW.invoice_date, CURDATE()),
            CONCAT('Opening Liability - ', NEW.vandor_name),
            NEW.Credit, 0
        );
        
        -- Credit: Trade Creditors (liability - we owe vendor)
        INSERT INTO accounting_ledger (
            transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (
            'Vendor Opening Balance', 'vendors', NEW.vandor_id, 14, -- Trade Creditors
            COALESCE(NEW.invoice_date, CURDATE()),
            CONCAT('Opening Liability - ', NEW.vandor_name),
            0, NEW.Credit
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_after_vendors_update` AFTER UPDATE ON `vendors` FOR EACH ROW BEGIN
    -- Only process if opening balances changed
    IF OLD.Debit != NEW.Debit OR OLD.Credit != NEW.Credit THEN
        -- Delete old ledger entries
        DELETE FROM accounting_ledger 
        WHERE reference_table = 'vendors' AND reference_id = NEW.vandor_id 
        AND transaction_type = 'Vendor Opening Balance';
        
        -- Handle debit opening balance
        IF NEW.Debit > 0 THEN
            -- Debit: Vendor Advances
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Vendor Opening Balance', 'vendors', NEW.vandor_id, 24,
                COALESCE(NEW.invoice_date, CURDATE()),
                CONCAT('Opening Advance - ', NEW.vandor_name),
                NEW.Debit, 0
            );
            
            -- Credit: Capital Account
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Vendor Opening Balance', 'vendors', NEW.vandor_id, 1,
                COALESCE(NEW.invoice_date, CURDATE()),
                CONCAT('Opening Advance - ', NEW.vandor_name),
                0, NEW.Debit
            );
        END IF;
        
        -- Handle credit opening balance
        IF NEW.Credit > 0 THEN
            -- Debit: Capital Account
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Vendor Opening Balance', 'vendors', NEW.vandor_id, 1,
                COALESCE(NEW.invoice_date, CURDATE()),
                CONCAT('Opening Liability - ', NEW.vandor_name),
                NEW.Credit, 0
            );
            
            -- Credit: Trade Creditors
            INSERT INTO accounting_ledger (
                transaction_type, reference_table, reference_id, account_id, 
                date, description, debit, credit
            ) VALUES (
                'Vendor Opening Balance', 'vendors', NEW.vandor_id, 14,
                COALESCE(NEW.invoice_date, CURDATE()),
                CONCAT('Opening Liability - ', NEW.vandor_name),
                0, NEW.Credit
            );
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse`
--

CREATE TABLE `warehouse` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `code` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouse`
--

INSERT INTO `warehouse` (`id`, `name`, `date`, `code`) VALUES
(1, 'Main Warehouse', '0000-00-00', ''),
(4, 'Main Gulshan Warehouse', '2025-08-04', 'UID-2025-001'),
(5, 'Secondary Malir Warehouse', '2025-08-04', 'UID-2025-002');

-- --------------------------------------------------------

--
-- Table structure for table `wip_issues`
--

CREATE TABLE `wip_issues` (
  `id` int(11) NOT NULL,
  `production_order_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `parent_wip_id` int(11) DEFAULT NULL,
  `machine_id` int(11) DEFAULT NULL,
  `is_addon` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wip_issues`
--

INSERT INTO `wip_issues` (`id`, `production_order_id`, `warehouse_id`, `issue_date`, `created_at`, `parent_wip_id`, `machine_id`, `is_addon`) VALUES
(14, 2, 5, '2025-08-05', '2025-08-05 14:31:32', NULL, 5, 0);

--
-- Triggers `wip_issues`
--
DELIMITER $$
CREATE TRIGGER `wip_issues_after_delete` AFTER DELETE ON `wip_issues` FOR EACH ROW BEGIN
    -- Check if this was the last WIP issue for the production order
    IF (SELECT COUNT(*) FROM wip_issues WHERE production_order_id = OLD.production_order_id) = 0 THEN
        UPDATE production_orders 
        SET status = 'Planned', 
            start_date = NULL 
        WHERE id = OLD.production_order_id 
        AND status = 'In Progress';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `wip_issues_after_insert` AFTER INSERT ON `wip_issues` FOR EACH ROW BEGIN
    UPDATE production_orders 
    SET status = 'In Progress',
        start_date = CASE 
            WHEN start_date IS NULL THEN NEW.issue_date 
            ELSE start_date 
        END
    WHERE id = NEW.production_order_id 
    AND status = 'Planned';
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `wip_issues_after_update` AFTER UPDATE ON `wip_issues` FOR EACH ROW BEGIN
    -- If production_order_id changed, update both old and new production orders
    IF OLD.production_order_id != NEW.production_order_id THEN
        -- Update old production order status if no more WIP issues exist
        IF (SELECT COUNT(*) FROM wip_issues WHERE production_order_id = OLD.production_order_id) = 0 THEN
            UPDATE production_orders 
            SET status = 'Planned', start_date = NULL 
            WHERE id = OLD.production_order_id;
        END IF;
        
        -- Update new production order to In Progress
        UPDATE production_orders 
        SET status = 'In Progress',
            start_date = CASE 
                WHEN start_date IS NULL THEN NEW.issue_date 
                ELSE start_date 
            END
        WHERE id = NEW.production_order_id 
        AND status = 'Planned';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `wip_issue_items`
--

CREATE TABLE `wip_issue_items` (
  `id` int(11) NOT NULL,
  `wip_issue_id` int(11) NOT NULL,
  `raw_material_id` int(11) NOT NULL,
  `qty_issued` decimal(15,3) NOT NULL,
  `unit_cost` decimal(15,3) DEFAULT 0.000,
  `machine_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wip_issue_items`
--

INSERT INTO `wip_issue_items` (`id`, `wip_issue_id`, `raw_material_id`, `qty_issued`, `unit_cost`, `machine_id`) VALUES
(39, 14, 5, 1.000, 1200.000, 5),
(40, 14, 6, 3.000, 1000.000, 5),
(41, 14, 7, 4.000, 5.000, 5),
(42, 14, 3, 20.000, 5.000, 5),
(43, 14, 4, 2.000, 500.000, 5),
(44, 14, 2, 5.000, 800.000, 5);

--
-- Triggers `wip_issue_items`
--
DELIMITER $$
CREATE TRIGGER `wip_issue_items_after_delete` AFTER DELETE ON `wip_issue_items` FOR EACH ROW BEGIN
    -- Delete related stock ledger entries
    DELETE FROM stock_ledger 
    WHERE reference_table = 'wip_issue_items' AND reference_id = OLD.id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `wip_issue_items_after_insert` AFTER INSERT ON `wip_issue_items` FOR EACH ROW BEGIN
    DECLARE v_warehouse_id INT;
    DECLARE v_issue_date DATE;
    DECLARE v_production_order_id INT;
    
    -- Get warehouse_id, issue_date, and production_order_id from wip_issues
    SELECT wi.warehouse_id, wi.issue_date, wi.production_order_id 
    INTO v_warehouse_id, v_issue_date, v_production_order_id
    FROM wip_issues wi WHERE wi.id = NEW.wip_issue_id;
    
    -- Raw Materials Credit (qty_out)
    INSERT INTO stock_ledger (
        product_id, warehouse_id, machine_id, production_order_id, account_id, reference_table, reference_id,
        qty_out, balance_qty, unit_cost, total_cost,
        transaction_type, transaction_date
    ) VALUES (
        NEW.raw_material_id, v_warehouse_id, NEW.machine_id, v_production_order_id, 34, 'wip_issue_items', NEW.id,
        NEW.qty_issued, -NEW.qty_issued, NEW.unit_cost, (NEW.qty_issued * NEW.unit_cost),
        'Production Issue', v_issue_date
    );
    
    -- WIP Debit (qty_in) - using raw material for WIP
    INSERT INTO stock_ledger (
        product_id, warehouse_id, machine_id, production_order_id, account_id, reference_table, reference_id,
        qty_in, balance_qty, unit_cost, total_cost,
        transaction_type, transaction_date
    ) VALUES (
        NEW.raw_material_id, v_warehouse_id, NEW.machine_id, v_production_order_id, 35, 'wip_issue_items', NEW.id,
        NEW.qty_issued, NEW.qty_issued, NEW.unit_cost, (NEW.qty_issued * NEW.unit_cost),
        'Production Issue', v_issue_date
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `wip_issue_items_after_update` AFTER UPDATE ON `wip_issue_items` FOR EACH ROW BEGIN
    DECLARE v_warehouse_id INT;
    DECLARE v_issue_date DATE;
    DECLARE v_production_order_id INT;
    
    -- Get warehouse_id, issue_date, and production_order_id from wip_issues
    SELECT wi.warehouse_id, wi.issue_date, wi.production_order_id 
    INTO v_warehouse_id, v_issue_date, v_production_order_id
    FROM wip_issues wi WHERE wi.id = NEW.wip_issue_id;
    
    -- Delete old stock ledger entries
    DELETE FROM stock_ledger 
    WHERE reference_table = 'wip_issue_items' AND reference_id = OLD.id;
    
    -- Insert new Raw Materials Credit entry
    INSERT INTO stock_ledger (
        product_id, warehouse_id, machine_id, production_order_id, account_id, reference_table, reference_id,
        qty_out, balance_qty, unit_cost, total_cost,
        transaction_type, transaction_date
    ) VALUES (
        NEW.raw_material_id, v_warehouse_id, NEW.machine_id, v_production_order_id, 34, 'wip_issue_items', NEW.id,
        NEW.qty_issued, -NEW.qty_issued, NEW.unit_cost, (NEW.qty_issued * NEW.unit_cost),
        'Production Issue', v_issue_date
    );
    
    -- Insert new WIP Debit entry
    INSERT INTO stock_ledger (
        product_id, warehouse_id, machine_id, production_order_id, account_id, reference_table, reference_id,
        qty_in, balance_qty, unit_cost, total_cost,
        transaction_type, transaction_date
    ) VALUES (
        NEW.raw_material_id, v_warehouse_id, NEW.machine_id, v_production_order_id, 35, 'wip_issue_items', NEW.id,
        NEW.qty_issued, NEW.qty_issued, NEW.unit_cost, (NEW.qty_issued * NEW.unit_cost),
        'Production Issue', v_issue_date
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `wip_labor_entries`
--

CREATE TABLE `wip_labor_entries` (
  `id` int(11) NOT NULL,
  `wip_issue_id` int(11) NOT NULL,
  `worker_id` int(11) DEFAULT NULL,
  `hours_worked` decimal(10,2) NOT NULL,
  `hourly_rate` decimal(15,2) NOT NULL,
  `labor_cost` decimal(15,2) GENERATED ALWAYS AS (`hours_worked` * `hourly_rate`) STORED,
  `work_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `employee_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wip_overhead_allocations`
--

CREATE TABLE `wip_overhead_allocations` (
  `id` int(11) NOT NULL,
  `wip_issue_id` int(11) NOT NULL,
  `overhead_account_id` int(11) NOT NULL,
  `allocation_basis` enum('Machine Hours','Labor Hours','Units Produced','Manual') DEFAULT 'Manual',
  `allocation_value` decimal(15,2) NOT NULL,
  `allocated_cost` decimal(15,2) NOT NULL,
  `allocation_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zones`
--

CREATE TABLE `zones` (
  `id` int(11) NOT NULL,
  `city_id` int(11) DEFAULT NULL,
  `zone_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `zones`
--

INSERT INTO `zones` (`id`, `city_id`, `zone_name`) VALUES
(7, 1, 'Gulshan E Iqbal'),
(8, 1, 'Gulshan E Jauhar');

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
  ADD KEY `idx_acc_prod_order` (`production_order_id`);

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sub_account` (`sub_account_id`);

--
-- Indexes for table `accounts_head`
--
ALTER TABLE `accounts_head`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `zone_id` (`zone_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`employee_id`,`attendance_date`);

--
-- Indexes for table `balance_invoice_entries`
--
ALTER TABLE `balance_invoice_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_balance_invoice_entries_customer` (`customer_id`),
  ADD KEY `idx_balance_invoice_entries_date` (`invoice_date`);

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bank_name` (`bank_name`,`account_number`);

--
-- Indexes for table `bank_info`
--
ALTER TABLE `bank_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `batchsetup`
--
ALTER TABLE `batchsetup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bill_material`
--
ALTER TABLE `bill_material`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bom`
--
ALTER TABLE `bom`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bom_product` (`product_id`);

--
-- Indexes for table `bom_items`
--
ALTER TABLE `bom_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bom_items_rm` (`raw_material_id`),
  ADD KEY `idx_bom_items` (`bom_id`,`raw_material_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `city`
--
ALTER TABLE `city`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `company`
--
ALTER TABLE `company`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name_unique` (`name`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `completion_packing_materials`
--
ALTER TABLE `completion_packing_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `completion_item_id` (`completion_item_id`),
  ADD KEY `packing_id` (`packing_id`),
  ADD KEY `machine_id` (`machine_id`);

--
-- Indexes for table `connections`
--
ALTER TABLE `connections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `costing_type`
--
ALTER TABLE `costing_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cost_accounting_ledger`
--
ALTER TABLE `cost_accounting_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_production_order` (`production_order_id`),
  ADD KEY `idx_expense_account` (`expense_account_id`);

--
-- Indexes for table `cost_centers`
--
ALTER TABLE `cost_centers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_name` (`account_id`,`name`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD KEY `fk_customers_zone` (`zone_id`),
  ADD KEY `fk_customers_area` (`area_id`);

--
-- Indexes for table `customers_details`
--
ALTER TABLE `customers_details`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`);

--
-- Indexes for table `delivery_challan`
--
ALTER TABLE `delivery_challan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `delivery_challans`
--
ALTER TABLE `delivery_challans`
  ADD PRIMARY KEY (`challan_id`),
  ADD UNIQUE KEY `serial_no` (`serial_no`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_department_parent` (`parent_dept_id`),
  ADD KEY `fk_department_manager` (`manager_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `idx_id` (`id`),
  ADD KEY `idx_employees_opening` (`opening`,`credit`);

--
-- Indexes for table `expense_voucher`
--
ALTER TABLE `expense_voucher`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_number` (`voucher_number`),
  ADD KEY `voucher_date` (`voucher_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_expense_voucher_date` (`voucher_date`),
  ADD KEY `idx_expense_voucher_status` (`status`);

--
-- Indexes for table `expense_vouchers`
--
ALTER TABLE `expense_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `expense_voucher_lines`
--
ALTER TABLE `expense_voucher_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expense_voucher_id` (`expense_voucher_id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `line_number` (`line_number`),
  ADD KEY `fk_expense_lines_sub_account` (`sub_account_id`),
  ADD KEY `fk_expense_lines_bank_account` (`bank_account_id`),
  ADD KEY `idx_expense_lines_voucher_account` (`expense_voucher_id`,`account_id`),
  ADD KEY `idx_expense_lines_amount` (`expense_amount`),
  ADD KEY `cost_center_id` (`cost_center_id`);

--
-- Indexes for table `finished_product`
--
ALTER TABLE `finished_product`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_assembly_id` (`production_assembly_id`);

--
-- Indexes for table `followups`
--
ALTER TABLE `followups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_id` (`lead_id`);

--
-- Indexes for table `gate_passes`
--
ALTER TABLE `gate_passes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gate_pass_items`
--
ALTER TABLE `gate_pass_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gatepass_id` (`gatepass_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_id` (`inventory_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `journal_voucher`
--
ALTER TABLE `journal_voucher`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_number` (`voucher_number`),
  ADD KEY `voucher_date` (`voucher_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_journal_voucher_date` (`voucher_date`),
  ADD KEY `idx_journal_voucher_status` (`status`);

--
-- Indexes for table `journal_voucher_lines`
--
ALTER TABLE `journal_voucher_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `journal_voucher_id` (`journal_voucher_id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `line_number` (`line_number`),
  ADD KEY `fk_journal_lines_sub_account` (`sub_account_id`),
  ADD KEY `fk_journal_lines_bank_account` (`bank_account_id`),
  ADD KEY `idx_journal_lines_voucher_account` (`journal_voucher_id`,`account_id`),
  ADD KEY `idx_journal_lines_amounts` (`debit_amount`,`credit_amount`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lead_code` (`lead_code`),
  ADD KEY `idx_lead_date` (`lead_date`),
  ADD KEY `idx_lead_status` (`lead_status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_service_type` (`service_type`);

--
-- Indexes for table `machines`
--
ALTER TABLE `machines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- Indexes for table `machine_types`
--
ALTER TABLE `machine_types`
  ADD PRIMARY KEY (`machine_type_id`);

--
-- Indexes for table `material_bom`
--
ALTER TABLE `material_bom`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `material_issue_items`
--
ALTER TABLE `material_issue_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `note_id` (`note_id`);

--
-- Indexes for table `material_issue_notes`
--
ALTER TABLE `material_issue_notes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `material_item`
--
ALTER TABLE `material_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_no` (`job_no`),
  ADD KEY `fk_product` (`product_id`);

--
-- Indexes for table `material_unit`
--
ALTER TABLE `material_unit`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unit` (`unit`);

--
-- Indexes for table `payment_voucher`
--
ALTER TABLE `payment_voucher`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_number` (`voucher_number`),
  ADD KEY `payee_account_id` (`payee_account_id`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_payment_voucher_vendor` (`vendor_id`);

--
-- Indexes for table `payroll_entries`
--
ALTER TABLE `payroll_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payroll_entries_employee` (`employee_id`),
  ADD KEY `idx_payroll_entries_date` (`payroll_date`);

--
-- Indexes for table `post_dated_cheques`
--
ALTER TABLE `post_dated_cheques`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `process_component`
--
ALTER TABLE `process_component`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_assembly_id` (`production_assembly_id`);

--
-- Indexes for table `production_assembly`
--
ALTER TABLE `production_assembly`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_no` (`serial_no`);

--
-- Indexes for table `production_completions`
--
ALTER TABLE `production_completions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_prod_complete_wh` (`warehouse_id`),
  ADD KEY `idx_prod_complete_order` (`production_order_id`),
  ADD KEY `machine_id` (`machine_id`);

--
-- Indexes for table `production_completion_items`
--
ALTER TABLE `production_completion_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_prod_complete_fg` (`finished_good_id`),
  ADD KEY `idx_prod_complete_item` (`production_completion_id`,`finished_good_id`);

--
-- Indexes for table `production_expenses`
--
ALTER TABLE `production_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prod_exp_order` (`production_order_id`),
  ADD KEY `fk_prod_exp_account` (`expense_account_id`);

--
-- Indexes for table `production_labor_timesheet`
--
ALTER TABLE `production_labor_timesheet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_production_order` (`production_order_id`);

--
-- Indexes for table `production_orders`
--
ALTER TABLE `production_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_prod_order_bom` (`bom_id`),
  ADD KEY `idx_prod_order_product` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_products_inventory_account` (`inventory_account_id`);

--
-- Indexes for table `product_packings`
--
ALTER TABLE `product_packings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_packings_product` (`product_id`),
  ADD KEY `idx_product_packings_unit` (`unit_id`);

--
-- Indexes for table `property_types`
--
ALTER TABLE `property_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `purchase`
--
ALTER TABLE `purchase`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_invoice`
--
ALTER TABLE `purchase_invoice`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_invoice_vendor` (`vendor_id`),
  ADD KEY `idx_purchase_invoice_date` (`date`);

--
-- Indexes for table `purchase_invoice_return`
--
ALTER TABLE `purchase_invoice_return`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_invoice_return_vendor` (`vendor_id`),
  ADD KEY `idx_purchase_invoice_return_date` (`date`);

--
-- Indexes for table `purchase_invoice_return_items`
--
ALTER TABLE `purchase_invoice_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `purchase_item_invoice`
--
ALTER TABLE `purchase_item_invoice`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_order_id` (`purchase_order_id`),
  ADD KEY `idx_item_code` (`item_code`);

--
-- Indexes for table `purchase_requisitions`
--
ALTER TABLE `purchase_requisitions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- Indexes for table `purchase_requisition_items`
--
ALTER TABLE `purchase_requisition_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requisition_id` (`requisition_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `purchase_tax`
--
ALTER TABLE `purchase_tax`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_no` (`serial_no`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_invoice_date` (`invoice_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_vendor_name` (`vendor_name`),
  ADD KEY `idx_purchase_order_no` (`purchase_order_no`),
  ADD KEY `idx_vendor_invoice_no` (`vendor_invoice_no`),
  ADD KEY `idx_grand_total` (`grand_total`);

--
-- Indexes for table `purchase_tax_items`
--
ALTER TABLE `purchase_tax_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_order_id` (`purchase_order_id`),
  ADD KEY `idx_item_code` (`item_code`),
  ADD KEY `idx_item_name` (`item_name`),
  ADD KEY `idx_quantity` (`quantity`),
  ADD KEY `idx_rate` (`rate`),
  ADD KEY `idx_value_incl` (`value_incl`),
  ADD KEY `idx_tax_percentage` (`tax_percentage`);

--
-- Indexes for table `quotation_entries`
--
ALTER TABLE `quotation_entries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_quotation` (`quotation_id`);

--
-- Indexes for table `receive_voucher`
--
ALTER TABLE `receive_voucher`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_number` (`voucher_number`),
  ADD KEY `payer_account_id` (`payer_account_id`),
  ADD KEY `receipt_method_id` (`receipt_method_id`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_receive_voucher_customer` (`customer_id`),
  ADD KEY `fk_receive_voucher_recovery_officer` (`recovery_officer_id`);

--
-- Indexes for table `receive_voucher_journal`
--
ALTER TABLE `receive_voucher_journal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receive_voucher_id` (`receive_voucher_id`);

--
-- Indexes for table `relations`
--
ALTER TABLE `relations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `relation_name` (`relation_name`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_permission` (`role_id`,`category`,`form_name`,`sub_permission`),
  ADD KEY `idx_role_form` (`role_id`,`category`,`form_name`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_allowed` (`allowed`);

--
-- Indexes for table `salereturn_invoice`
--
ALTER TABLE `salereturn_invoice`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_salereturn_invoice_customer` (`customer_id`),
  ADD KEY `idx_salereturn_invoice_date` (`date`);

--
-- Indexes for table `salereturn_invoice_items`
--
ALTER TABLE `salereturn_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- Indexes for table `sales_tax`
--
ALTER TABLE `sales_tax`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales_tax_type`
--
ALTER TABLE `sales_tax_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sale_invoice`
--
ALTER TABLE `sale_invoice`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale_invoice_date` (`date`),
  ADD KEY `idx_sale_invoice_customer` (`customer_id`);

--
-- Indexes for table `sale_tax_items`
--
ALTER TABLE `sale_tax_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_tax_id` (`sales_tax_id`);

--
-- Indexes for table `service_types`
--
ALTER TABLE `service_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_name` (`service_name`);

--
-- Indexes for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sl_packing` (`product_packing_id`),
  ADD KEY `fk_sl_warehouse` (`warehouse_id`),
  ADD KEY `idx_product_warehouse` (`product_id`,`warehouse_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_reference` (`reference_table`,`reference_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`);

--
-- Indexes for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `from_warehouse_id` (`from_warehouse_id`),
  ADD KEY `to_warehouse_id` (`to_warehouse_id`);

--
-- Indexes for table `stock_wastage`
--
ALTER TABLE `stock_wastage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- Indexes for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sub_accounts`
--
ALTER TABLE `sub_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_head_id` (`account_head_id`),
  ADD KEY `idx_account_head_id` (`account_head_id`),
  ADD KEY `idx_parent_id` (`parent_id`);

--
-- Indexes for table `taxes`
--
ALTER TABLE `taxes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transaction_types`
--
ALTER TABLE `transaction_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `transport`
--
ALTER TABLE `transport`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `primary_cell_no` (`primary_cell_no`),
  ADD UNIQUE KEY `unique_primary_cell_no` (`primary_cell_no`),
  ADD UNIQUE KEY `secondary_cell_no` (`secondary_cell_no`),
  ADD UNIQUE KEY `unique_secondary_cell_no` (`secondary_cell_no`),
  ADD KEY `city_id` (`city_id`);

--
-- Indexes for table `unit`
--
ALTER TABLE `unit`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`vandor_id`),
  ADD KEY `idx_customer_vendor_type` (`customer_vendor_type`),
  ADD KEY `idx_vandor_name` (`vandor_name`),
  ADD KEY `idx_business_name` (`business_name`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_primary_cell_no` (`primary_cell_no`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `zone_id` (`zone_id`),
  ADD KEY `idx_vendors_area_id` (`area_id`),
  ADD KEY `idx_vendors_opening` (`Debit`,`Credit`),
  ADD KEY `idx_vendors_date` (`invoice_date`);

--
-- Indexes for table `warehouse`
--
ALTER TABLE `warehouse`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wip_issues`
--
ALTER TABLE `wip_issues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_wip_issue_wh` (`warehouse_id`),
  ADD KEY `idx_wip_issue_order` (`production_order_id`),
  ADD KEY `parent_wip_id` (`parent_wip_id`),
  ADD KEY `machine_id` (`machine_id`);

--
-- Indexes for table `wip_issue_items`
--
ALTER TABLE `wip_issue_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_wip_items_rm` (`raw_material_id`),
  ADD KEY `idx_wip_items` (`wip_issue_id`,`raw_material_id`),
  ADD KEY `machine_id` (`machine_id`);

--
-- Indexes for table `wip_labor_entries`
--
ALTER TABLE `wip_labor_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wip_issue` (`wip_issue_id`),
  ADD KEY `idx_employee_id` (`employee_id`);

--
-- Indexes for table `wip_overhead_allocations`
--
ALTER TABLE `wip_overhead_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wip_issue` (`wip_issue_id`),
  ADD KEY `idx_overhead_account` (`overhead_account_id`);

--
-- Indexes for table `zones`
--
ALTER TABLE `zones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `city_id` (`city_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounting_ledger`
--
ALTER TABLE `accounting_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2244;

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `accounts_head`
--
ALTER TABLE `accounts_head`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `balance_invoice_entries`
--
ALTER TABLE `balance_invoice_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=407;

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bank_info`
--
ALTER TABLE `bank_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `batchsetup`
--
ALTER TABLE `batchsetup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bill_material`
--
ALTER TABLE `bill_material`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bom`
--
ALTER TABLE `bom`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bom_items`
--
ALTER TABLE `bom_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `city`
--
ALTER TABLE `city`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `company`
--
ALTER TABLE `company`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_settings`
--
ALTER TABLE `company_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `completion_packing_materials`
--
ALTER TABLE `completion_packing_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `connections`
--
ALTER TABLE `connections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `costing_type`
--
ALTER TABLE `costing_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cost_accounting_ledger`
--
ALTER TABLE `cost_accounting_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cost_centers`
--
ALTER TABLE `cost_centers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `delivery_challan`
--
ALTER TABLE `delivery_challan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `delivery_challans`
--
ALTER TABLE `delivery_challans`
  MODIFY `challan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `expense_voucher`
--
ALTER TABLE `expense_voucher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `expense_vouchers`
--
ALTER TABLE `expense_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `expense_voucher_lines`
--
ALTER TABLE `expense_voucher_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `finished_product`
--
ALTER TABLE `finished_product`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `followups`
--
ALTER TABLE `followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `gate_passes`
--
ALTER TABLE `gate_passes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `gate_pass_items`
--
ALTER TABLE `gate_pass_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `journal_voucher`
--
ALTER TABLE `journal_voucher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_voucher_lines`
--
ALTER TABLE `journal_voucher_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `machines`
--
ALTER TABLE `machines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `machine_types`
--
ALTER TABLE `machine_types`
  MODIFY `machine_type_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `material_bom`
--
ALTER TABLE `material_bom`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `material_issue_items`
--
ALTER TABLE `material_issue_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `material_issue_notes`
--
ALTER TABLE `material_issue_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `material_item`
--
ALTER TABLE `material_item`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `material_unit`
--
ALTER TABLE `material_unit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_voucher`
--
ALTER TABLE `payment_voucher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payroll_entries`
--
ALTER TABLE `payroll_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `post_dated_cheques`
--
ALTER TABLE `post_dated_cheques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `process_component`
--
ALTER TABLE `process_component`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_assembly`
--
ALTER TABLE `production_assembly`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_completions`
--
ALTER TABLE `production_completions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `production_completion_items`
--
ALTER TABLE `production_completion_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `production_expenses`
--
ALTER TABLE `production_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `production_labor_timesheet`
--
ALTER TABLE `production_labor_timesheet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_orders`
--
ALTER TABLE `production_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `product_packings`
--
ALTER TABLE `product_packings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `property_types`
--
ALTER TABLE `property_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase`
--
ALTER TABLE `purchase`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `purchase_invoice`
--
ALTER TABLE `purchase_invoice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `purchase_invoice_return`
--
ALTER TABLE `purchase_invoice_return`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_invoice_return_items`
--
ALTER TABLE `purchase_invoice_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `purchase_item_invoice`
--
ALTER TABLE `purchase_item_invoice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_requisitions`
--
ALTER TABLE `purchase_requisitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `purchase_requisition_items`
--
ALTER TABLE `purchase_requisition_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `purchase_tax`
--
ALTER TABLE `purchase_tax`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchase_tax_items`
--
ALTER TABLE `purchase_tax_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quotation_entries`
--
ALTER TABLE `quotation_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `receive_voucher`
--
ALTER TABLE `receive_voucher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `receive_voucher_journal`
--
ALTER TABLE `receive_voucher_journal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `relations`
--
ALTER TABLE `relations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1308;

--
-- AUTO_INCREMENT for table `salereturn_invoice`
--
ALTER TABLE `salereturn_invoice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salereturn_invoice_items`
--
ALTER TABLE `salereturn_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_orders`
--
ALTER TABLE `sales_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sales_tax`
--
ALTER TABLE `sales_tax`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sales_tax_type`
--
ALTER TABLE `sales_tax_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_invoice`
--
ALTER TABLE `sale_invoice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `sale_tax_items`
--
ALTER TABLE `sale_tax_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `service_types`
--
ALTER TABLE `service_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=169;

--
-- AUTO_INCREMENT for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_wastage`
--
ALTER TABLE `stock_wastage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sub_accounts`
--
ALTER TABLE `sub_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `taxes`
--
ALTER TABLE `taxes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `transaction_types`
--
ALTER TABLE `transaction_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transport`
--
ALTER TABLE `transport`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `unit`
--
ALTER TABLE `unit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `vandor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `warehouse`
--
ALTER TABLE `warehouse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `wip_issues`
--
ALTER TABLE `wip_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `wip_issue_items`
--
ALTER TABLE `wip_issue_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `wip_labor_entries`
--
ALTER TABLE `wip_labor_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wip_overhead_allocations`
--
ALTER TABLE `wip_overhead_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zones`
--
ALTER TABLE `zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounting_ledger`
--
ALTER TABLE `accounting_ledger`
  ADD CONSTRAINT `accounting_ledger_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_acc_production_order` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`);

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_sub_account` FOREIGN KEY (`sub_account_id`) REFERENCES `sub_accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `areas`
--
ALTER TABLE `areas`
  ADD CONSTRAINT `areas_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`);

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `balance_invoice_entries`
--
ALTER TABLE `balance_invoice_entries`
  ADD CONSTRAINT `balance_invoice_entries_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bom`
--
ALTER TABLE `bom`
  ADD CONSTRAINT `fk_bom_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `bom_items`
--
ALTER TABLE `bom_items`
  ADD CONSTRAINT `fk_bom_items_bom` FOREIGN KEY (`bom_id`) REFERENCES `bom` (`id`),
  ADD CONSTRAINT `fk_bom_items_rm` FOREIGN KEY (`raw_material_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `completion_packing_materials`
--
ALTER TABLE `completion_packing_materials`
  ADD CONSTRAINT `completion_packing_materials_ibfk_1` FOREIGN KEY (`completion_item_id`) REFERENCES `production_completion_items` (`id`),
  ADD CONSTRAINT `completion_packing_materials_ibfk_2` FOREIGN KEY (`packing_id`) REFERENCES `product_packings` (`id`),
  ADD CONSTRAINT `completion_packing_materials_ibfk_3` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`);

--
-- Constraints for table `connections`
--
ALTER TABLE `connections`
  ADD CONSTRAINT `connections_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cost_centers`
--
ALTER TABLE `cost_centers`
  ADD CONSTRAINT `fk_cost_centers_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customers_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `expense_vouchers`
--
ALTER TABLE `expense_vouchers`
  ADD CONSTRAINT `expense_vouchers_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`);

--
-- Constraints for table `expense_voucher_lines`
--
ALTER TABLE `expense_voucher_lines`
  ADD CONSTRAINT `fk_expense_lines_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_expense_lines_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `fk_expense_lines_cost_center` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_expense_lines_sub_account` FOREIGN KEY (`sub_account_id`) REFERENCES `sub_accounts` (`id`),
  ADD CONSTRAINT `fk_expense_lines_voucher` FOREIGN KEY (`expense_voucher_id`) REFERENCES `expense_voucher` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gate_pass_items`
--
ALTER TABLE `gate_pass_items`
  ADD CONSTRAINT `gate_pass_items_ibfk_1` FOREIGN KEY (`gatepass_id`) REFERENCES `gate_passes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `sale_invoice` (`id`);

--
-- Constraints for table `journal_voucher_lines`
--
ALTER TABLE `journal_voucher_lines`
  ADD CONSTRAINT `fk_journal_lines_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_journal_lines_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `fk_journal_lines_sub_account` FOREIGN KEY (`sub_account_id`) REFERENCES `sub_accounts` (`id`),
  ADD CONSTRAINT `fk_journal_lines_voucher` FOREIGN KEY (`journal_voucher_id`) REFERENCES `journal_voucher` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `machines`
--
ALTER TABLE `machines`
  ADD CONSTRAINT `machines_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouse` (`id`);

--
-- Constraints for table `payment_voucher`
--
ALTER TABLE `payment_voucher`
  ADD CONSTRAINT `fk_payment_voucher_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `fk_payment_voucher_payee` FOREIGN KEY (`payee_account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_payment_voucher_payment_method` FOREIGN KEY (`payment_method_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_payment_voucher_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vandor_id`);

--
-- Constraints for table `production_completions`
--
ALTER TABLE `production_completions`
  ADD CONSTRAINT `fk_prod_complete_order` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`),
  ADD CONSTRAINT `fk_prod_complete_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouse` (`id`),
  ADD CONSTRAINT `production_completions_ibfk_1` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`);

--
-- Constraints for table `production_completion_items`
--
ALTER TABLE `production_completion_items`
  ADD CONSTRAINT `fk_prod_complete_fg` FOREIGN KEY (`finished_good_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_prod_complete_item` FOREIGN KEY (`production_completion_id`) REFERENCES `production_completions` (`id`);

--
-- Constraints for table `production_expenses`
--
ALTER TABLE `production_expenses`
  ADD CONSTRAINT `fk_prod_exp_account` FOREIGN KEY (`expense_account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_prod_exp_order` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`);

--
-- Constraints for table `production_orders`
--
ALTER TABLE `production_orders`
  ADD CONSTRAINT `fk_prod_order_bom` FOREIGN KEY (`bom_id`) REFERENCES `bom` (`id`),
  ADD CONSTRAINT `fk_prod_order_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_inventory_account` FOREIGN KEY (`inventory_account_id`) REFERENCES `accounts` (`id`);

--
-- Constraints for table `product_packings`
--
ALTER TABLE `product_packings`
  ADD CONSTRAINT `fk_product_packings_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_product_packings_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`);

--
-- Constraints for table `purchase_invoice_return_items`
--
ALTER TABLE `purchase_invoice_return_items`
  ADD CONSTRAINT `purchase_invoice_return_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `purchase_invoice_return` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_requisitions`
--
ALTER TABLE `purchase_requisitions`
  ADD CONSTRAINT `purchase_requisitions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `purchase_requisitions_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouse` (`id`);

--
-- Constraints for table `purchase_requisition_items`
--
ALTER TABLE `purchase_requisition_items`
  ADD CONSTRAINT `purchase_requisition_items_ibfk_1` FOREIGN KEY (`requisition_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_requisition_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `purchase_tax_items`
--
ALTER TABLE `purchase_tax_items`
  ADD CONSTRAINT `fk_purchase_tax_items_purchase_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_tax` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD CONSTRAINT `fk_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotation_entries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `receive_voucher`
--
ALTER TABLE `receive_voucher`
  ADD CONSTRAINT `fk_receive_voucher_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `fk_receive_voucher_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `fk_receive_voucher_payer` FOREIGN KEY (`payer_account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_receive_voucher_receipt_method` FOREIGN KEY (`receipt_method_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_receive_voucher_recovery_officer` FOREIGN KEY (`recovery_officer_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salereturn_invoice_items`
--
ALTER TABLE `salereturn_invoice_items`
  ADD CONSTRAINT `salereturn_invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `salereturn_invoice` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  ADD CONSTRAINT `sales_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sale_tax_items`
--
ALTER TABLE `sale_tax_items`
  ADD CONSTRAINT `sale_tax_items_ibfk_1` FOREIGN KEY (`sales_tax_id`) REFERENCES `sales_tax` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_ledger`
--
ALTER TABLE `stock_ledger`
  ADD CONSTRAINT `fk_sl_packing` FOREIGN KEY (`product_packing_id`) REFERENCES `product_packings` (`id`),
  ADD CONSTRAINT `fk_sl_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_sl_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouse` (`id`);

--
-- Constraints for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD CONSTRAINT `stock_transfers_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_2` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouse` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_3` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouse` (`id`);

--
-- Constraints for table `stock_wastage`
--
ALTER TABLE `stock_wastage`
  ADD CONSTRAINT `stock_wastage_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_wastage_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouse` (`id`);

--
-- Constraints for table `sub_accounts`
--
ALTER TABLE `sub_accounts`
  ADD CONSTRAINT `fk_sub_accounts_parent` FOREIGN KEY (`parent_id`) REFERENCES `sub_accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sub_accounts_ibfk_1` FOREIGN KEY (`account_head_id`) REFERENCES `accounts_head` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transport`
--
ALTER TABLE `transport`
  ADD CONSTRAINT `transport_ibfk_1` FOREIGN KEY (`city_id`) REFERENCES `city` (`id`);

--
-- Constraints for table `vendors`
--
ALTER TABLE `vendors`
  ADD CONSTRAINT `fk_vendors_area_id` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `vendors_zone_fk` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `wip_issues`
--
ALTER TABLE `wip_issues`
  ADD CONSTRAINT `fk_wip_issue_order` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`),
  ADD CONSTRAINT `fk_wip_issue_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouse` (`id`),
  ADD CONSTRAINT `wip_issues_ibfk_1` FOREIGN KEY (`parent_wip_id`) REFERENCES `wip_issues` (`id`),
  ADD CONSTRAINT `wip_issues_ibfk_2` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`);

--
-- Constraints for table `wip_issue_items`
--
ALTER TABLE `wip_issue_items`
  ADD CONSTRAINT `fk_wip_items_issue` FOREIGN KEY (`wip_issue_id`) REFERENCES `wip_issues` (`id`),
  ADD CONSTRAINT `fk_wip_items_rm` FOREIGN KEY (`raw_material_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `wip_issue_items_ibfk_1` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`);

--
-- Constraints for table `zones`
--
ALTER TABLE `zones`
  ADD CONSTRAINT `zones_ibfk_1` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
