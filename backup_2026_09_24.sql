-- Tenant-scoped Database Backup for Tenant ID: 2
-- Generated: 2026-09-24 13:29:53
-- NOTE: This backup contains DATA ROWS ONLY for this tenant.
-- It intentionally does NOT drop or recreate any tables, and does
-- NOT include shared/global tables that other tenants also use.
-- It must be restored into a database that already has the schema.


-- Table: accounting_ledger
INSERT INTO `accounting_ledger` (`id`, `tenant_id`, `transaction_type`, `reference_table`, `reference_id`, `account_id`, `date`, `description`, `debit`, `credit`, `created_at`) VALUES
('171002', '2', 'opening_balance', 'employees', '245', '31', '2026-09-23', 'Opening balance - Raheel', '3000.00', '0.00', '2026-09-23 05:24:40'),
('171003', '2', 'opening_balance', 'employees', '245', '90', '2026-09-23', 'Opening balance - Raheel', '0.00', '3000.00', '2026-09-23 05:24:40'),
('171004', '2', 'opening_balance', 'employees', '246', '31', '2026-09-23', 'Opening balance - Wasim', '15000.00', '0.00', '2026-09-23 05:27:34'),
('171005', '2', 'opening_balance', 'employees', '246', '90', '2026-09-23', 'Opening balance - Wasim', '0.00', '15000.00', '2026-09-23 05:27:34'),
('171064', '2', 'supplier_opening', 'suppliers', '461', '90', '2026-09-23', 'Supplier Opening Credit - Able', '100000.00', '0.00', '2026-09-23 06:19:52'),
('171065', '2', 'supplier_opening', 'suppliers', '461', '14', '2026-09-23', 'Supplier Opening Credit - Able', '0.00', '100000.00', '2026-09-23 06:19:52'),
('171381', '2', 'Purchase Invoice', 'purchase_invoice', '1957', '19', '2026-09-23', 'Purchase Invoice - PUR-0001', '2000.00', '0.00', '2026-09-23 12:33:08'),
('171382', '2', 'Purchase Invoice', 'purchase_invoice', '1957', '14', '2026-09-23', 'Purchase Invoice - PUR-0001', '0.00', '2000.00', '2026-09-23 12:33:08'),
('171887', '2', 'supplier_opening', 'suppliers', '465', '90', '2026-09-23', 'Supplier Opening Credit - Khan Pvt Ltd', '150000.00', '0.00', '2026-09-23 14:41:30'),
('171888', '2', 'supplier_opening', 'suppliers', '465', '14', '2026-09-23', 'Supplier Opening Credit - Khan Pvt Ltd', '0.00', '150000.00', '2026-09-23 14:41:30'),
('172037', '2', 'Sale Invoice', 'sale_invoice', '18764', '2', '2026-09-23', 'Sale Invoice - SAL-0002', '600.00', '0.00', '2026-09-23 16:41:09'),
('172038', '2', 'Sale Invoice', 'sale_invoice', '18764', '9', '2026-09-23', 'Sale Invoice - SAL-0002', '0.00', '600.00', '2026-09-23 16:41:09'),
('172049', '2', 'Receive Voucher', 'receive_voucher', '17469', '1', '2026-09-24', 'null', '100.00', '0.00', '2026-09-23 16:49:15'),
('172050', '2', 'Receive Voucher', 'receive_voucher', '17469', '2', '2026-09-24', 'null', '0.00', '100.00', '2026-09-23 16:49:15'),
('172051', '2', 'Payment Voucher', 'payment_voucher', '1811', '14', '2026-09-23', NULL, '400.00', '0.00', '2026-09-23 16:49:39'),
('172052', '2', 'Payment Voucher', 'payment_voucher', '1811', '1', '2026-09-23', NULL, '0.00', '400.00', '2026-09-23 16:49:39'),
('172171', '2', 'customer_opening', 'opening_balance_invoices', '13054', '2', '2026-09-04', 'Opening invoice: 965 - Distribution: N/A - Sales Officer: Wasim', '4000.00', '0.00', '2026-09-23 19:43:44'),
('172172', '2', 'customer_opening', 'opening_balance_invoices', '13054', '90', '2026-09-04', 'Opening invoice: 965 - Distribution: N/A - Sales Officer: Wasim', '0.00', '4000.00', '2026-09-23 19:43:44'),
('172177', '2', 'Sale Invoice', 'sale_invoice', '18769', '2', '2026-09-23', 'Sale Invoice - SAL-0003', '11749.00', '0.00', '2026-09-23 19:48:30'),
('172178', '2', 'Sale Invoice', 'sale_invoice', '18769', '9', '2026-09-23', 'Sale Invoice - SAL-0003', '0.00', '11749.00', '2026-09-23 19:48:30'),
('172179', '2', 'Sale Invoice', 'sale_invoice', '18770', '2', '2026-09-23', 'Sale Invoice - SAL-0004', '2010.00', '0.00', '2026-09-23 20:14:28'),
('172180', '2', 'Sale Invoice', 'sale_invoice', '18770', '9', '2026-09-23', 'Sale Invoice - SAL-0004', '0.00', '2010.00', '2026-09-23 20:14:28'),
('172181', '2', 'CASH_OPENING', 'cash_opening', '93', '1', '2026-09-21', 'Opening Cash In Hand', '60000.00', '0.00', '2026-09-23 20:31:27'),
('172182', '2', 'CASH_OPENING', 'cash_opening', '93', '90', '2026-09-21', 'Opening Cash In Hand', '0.00', '60000.00', '2026-09-23 20:31:27'),
('172189', '2', 'opening_balance', 'bank_accounts', '145', '458', '2026-09-24', 'Opening Balance - Meezan  - 965321-85', '10000.00', '0.00', '2026-09-24 04:01:06'),
('172190', '2', 'opening_balance', 'bank_accounts', '145', '90', '2026-09-24', 'Opening Balance - Meezan  - 965321-85', '0.00', '10000.00', '2026-09-24 04:01:06'),
('172191', '2', 'opening_balance', 'bank_accounts', '146', '459', '2026-09-24', 'Opening Balance - Jazz Cash - 03348545874', '3000.00', '0.00', '2026-09-24 04:03:29'),
('172192', '2', 'opening_balance', 'bank_accounts', '146', '90', '2026-09-24', 'Opening Balance - Jazz Cash - 03348545874', '0.00', '3000.00', '2026-09-24 04:03:29'),
('172193', '2', 'Sale Invoice', 'sale_invoice', '18771', '2', '2026-09-24', 'Sale Invoice - SAL-0005', '392.00', '0.00', '2026-09-24 04:15:00'),
('172194', '2', 'Sale Invoice', 'sale_invoice', '18771', '9', '2026-09-24', 'Sale Invoice - SAL-0005', '0.00', '392.00', '2026-09-24 04:15:00'),
('172365', '2', 'Purchase Invoice', 'purchase_invoice', '1969', '19', '2026-09-03', 'Purchase Invoice - PUR-0002', '35890.00', '0.00', '2026-09-24 04:44:40'),
('172366', '2', 'Purchase Invoice', 'purchase_invoice', '1969', '110', '2026-09-03', 'Purchase Invoice - PUR-0002', '500.00', '0.00', '2026-09-24 04:44:40'),
('172367', '2', 'Purchase Invoice', 'purchase_invoice', '1969', '14', '2026-09-03', 'Purchase Invoice - PUR-0002', '0.00', '35890.00', '2026-09-24 04:44:40'),
('172414', '2', 'expense_voucher', 'expense_voucher', '1835', '461', '2026-09-22', '', '200.00', '0.00', '2026-09-24 04:52:13'),
('172415', '2', 'expense_voucher', 'expense_voucher', '1835', '1', '2026-09-22', '', '0.00', '200.00', '2026-09-24 04:52:13'),
('172416', '2', 'expense_voucher', 'expense_voucher', '1835', '461', '2026-09-22', '', '400.00', '0.00', '2026-09-24 04:52:13'),
('172417', '2', 'expense_voucher', 'expense_voucher', '1835', '1', '2026-09-22', '', '0.00', '400.00', '2026-09-24 04:52:13'),
('172420', '2', 'expense_voucher', 'expense_voucher', '1836', '59', '2026-09-24', '', '6000.00', '0.00', '2026-09-24 04:55:56'),
('172421', '2', 'expense_voucher', 'expense_voucher', '1836', '1', '2026-09-24', '', '0.00', '6000.00', '2026-09-24 04:55:56'),
('172422', '2', 'expense_voucher', 'expense_voucher', '1837', '461', '2026-09-24', '', '300.00', '0.00', '2026-09-24 04:56:19'),
('172423', '2', 'expense_voucher', 'expense_voucher', '1837', '1', '2026-09-24', '', '0.00', '300.00', '2026-09-24 04:56:19'),
('172424', '2', 'Sale Return', 'sale_return', '1324', '18', '2026-09-24', 'Sale Return - SR-0001', '436.50', '0.00', '2026-09-24 05:00:37'),
('172425', '2', 'Sale Return', 'sale_return', '1324', '2', '2026-09-24', 'Sale Return - SR-0001', '0.00', '436.50', '2026-09-24 05:00:37'),
('172766', '2', 'wip_issue', 'work_in_progress', '3688', '116', '2026-09-24', 'Material issued to WIP: WIP-0001', '1800.00', '0.00', '2026-09-24 08:32:36'),
('172767', '2', 'wip_issue', 'work_in_progress', '3688', '115', '2026-09-24', 'Material issued to WIP: WIP-0001', '0.00', '1800.00', '2026-09-24 08:32:36'),
('172768', '2', 'wip_issue', 'work_in_progress', '3688', '116', '2026-09-24', 'Material issued to WIP: WIP-0001', '1800.00', '0.00', '2026-09-24 08:32:36'),
('172769', '2', 'wip_issue', 'work_in_progress', '3688', '115', '2026-09-24', 'Material issued to WIP: WIP-0001', '0.00', '1800.00', '2026-09-24 08:32:36'),
('172776', '2', 'production_expense', 'production_expenses', '1806', '152', '2026-09-24', 'Production expense', '20.00', '0.00', '2026-09-24 08:35:11'),
('172777', '2', 'production_expense', 'production_expenses', '1806', '1', '2026-09-24', 'Production expense payment', '0.00', '20.00', '2026-09-24 08:35:11'),
('172778', '2', 'production_expense', 'production_expenses', '1806', '147', '2026-09-24', 'Production expense', '1200.00', '0.00', '2026-09-24 08:35:11'),
('172779', '2', 'production_expense', 'production_expenses', '1806', '1', '2026-09-24', 'Production expense payment', '0.00', '1200.00', '2026-09-24 08:35:11'),
('172780', '2', 'production_completion', 'production_completions', '3631', '117', '2026-09-24', 'Production completed: PC-0001', '4820.00', '0.00', '2026-09-24 08:35:29'),
('172781', '2', 'production_completion', 'production_completions', '3631', '116', '2026-09-24', 'Production completed: PC-0001', '0.00', '4820.00', '2026-09-24 08:35:29'),
('172943', '2', 'Receive Voucher', 'receive_voucher', '17621', '1', '2026-09-24', 'null', '700.00', '0.00', '2026-09-24 11:35:11'),
('172944', '2', 'Receive Voucher', 'receive_voucher', '17621', '2', '2026-09-24', 'null', '0.00', '700.00', '2026-09-24 11:35:11'),
('172945', '2', 'Receive Voucher', 'receive_voucher', '17504', '78', '2026-09-24', 'null', '300.00', '0.00', '2026-09-24 11:35:21'),
('172946', '2', 'Receive Voucher', 'receive_voucher', '17504', '2', '2026-09-24', 'null', '0.00', '300.00', '2026-09-24 11:35:21'),
('172972', '2', 'Sale Invoice', 'sale_invoice', '18877', '2', '2026-09-24', 'Sale Invoice - SAL-0006', '790.00', '0.00', '2026-09-24 11:55:27'),
('172973', '2', 'Sale Invoice', 'sale_invoice', '18877', '9', '2026-09-24', 'Sale Invoice - SAL-0006', '0.00', '790.00', '2026-09-24 11:55:27'),
('173400', '2', 'Sale Invoice', 'sale_invoice', '18892', '2', '2026-09-24', 'Sale Invoice - SAL-0007', '389.94', '0.00', '2026-09-24 13:05:22'),
('173401', '2', 'Sale Invoice', 'sale_invoice', '18892', '9', '2026-09-24', 'Sale Invoice - SAL-0007', '0.00', '389.94', '2026-09-24 13:05:22'),
('173444', '2', 'Stock Adjustment', 'stock_ledger', '0', '33', '2026-09-24', 'Inventory sync adjustment', '95170.00', '0.00', '2026-09-24 13:22:36'),
('173445', '2', 'Stock Adjustment', 'stock_ledger', '0', '90', '2026-09-24', 'Inventory sync adjustment', '0.00', '95170.00', '2026-09-24 13:22:36'),
('173456', '2', 'Purchase Invoice', 'purchase_invoice', '1985', '19', '2026-09-24', 'Purchase Invoice - PUR-0003', '900.00', '0.00', '2026-09-24 13:28:57'),
('173457', '2', 'Purchase Invoice', 'purchase_invoice', '1985', '14', '2026-09-24', 'Purchase Invoice - PUR-0003', '0.00', '900.00', '2026-09-24 13:28:57');

-- Table: accounts
INSERT INTO `accounts` (`id`, `tenant_id`, `sub_account_id`, `name`, `debit`, `credit`, `is_partner_account`) VALUES
('113', '2', '75', 'Meezan Bank Limited - 16010102157918', '0.00', '0.00', '0'),
('114', '2', '75', 'Allied Bank Limited - 68138665132', '0.00', '0.00', '0'),
('137', '2', '74', 'AIT Receivable', '0.00', '0.00', '0'),
('140', '2', '94', 'Test', '0.00', '0.00', '0'),
('141', '2', NULL, 'Carolla Car', '1300000.00', '0.00', '0'),
('142', '2', '112', 'Carolla Car Av-985', '1400000.00', '0.00', '0'),
('177', '2', '75', 'bsbb - 28138', '0.00', '0.00', '0'),
('216', '2', '75', 'HBL - 012-6535895-8', '0.00', '0.00', '0'),
('217', '2', '75', 'Meezan - 9865-553233', '0.00', '0.00', '0'),
('285', '2', '75', 'UBL - 96885-854', '300000.00', '0.00', '0'),
('287', '2', '101', 'Zahid', '0.00', '30000.00', '0'),
('296', '2', '1', 'Rectify', '0.00', '0.00', '0'),
('326', '2', '75', 'Meezan - 236942-456', '70000.00', '0.00', '0'),
('386', '2', '75', 'Meezan  - 126595-895', '30000.00', '0.00', '0'),
('390', '2', '100', 'Khan', '15000.00', '0.00', '0'),
('403', '2', '75', 'MCB - 62541', '50000.00', '0.00', '0'),
('418', '2', '133', 'Zahid Ghori', '0.00', '0.00', '0'),
('421', '2', '75', 'Meezan - 9854652-854', '25000.00', '0.00', '0'),
('447', '2', '14', 'Wazir ghori', '30000.00', '0.00', '0'),
('448', '2', '14', 'Ateeb', '40000.00', '0.00', '0'),
('458', '2', '75', 'Meezan  - 965321-85', '10000.00', '0.00', '0'),
('459', '2', '75', 'Jazz Cash - 03348545874', '3000.00', '0.00', '0'),
('461', '2', '94', 'Office Expenses', '0.00', '0.00', '0');

-- Table: activity_logs
INSERT INTO `activity_logs` (`id`, `tenant_id`, `user_id`, `user_name`, `action`, `module`, `record_id`, `record_reference`, `description`, `old_values`, `new_values`, `ip_address`, `created_at`) VALUES
('7044', '2', '69', 'Zahid Ghori', 'delete', 'User', '136', 'branch2@gmail.com', 'Deleted user \"Branch2\" (branch2@gmail.com)', '{\"full_name\":\"Branch2\",\"email\":\"branch2@gmail.com\",\"phone\":\"\",\"employee_id\":null,\"is_active\":1}', NULL, '172.68.249.154', '2026-09-22 19:29:19'),
('7045', '2', '69', 'Zahid Ghori', 'delete', 'User', '135', 'branch1@gmail.com', 'Deleted user \"Branch1\" (branch1@gmail.com)', '{\"full_name\":\"Branch1\",\"email\":\"branch1@gmail.com\",\"phone\":\"\",\"employee_id\":null,\"is_active\":1}', NULL, '172.68.249.154', '2026-09-22 19:29:22'),
('7046', '2', '69', 'Zahid Ghori', 'create', 'Company', '89', 'COMP-001', 'Created company \"InnovaTech\" (COMP-001)', NULL, '{\"company_code\":\"COMP-001\",\"company_name\":\"InnovaTech\",\"email\":\"innova.tech213@gmail.com\",\"phone\":\"+923353789981\"}', '172.68.249.154', '2026-09-22 19:43:49'),
('7047', '2', '69', 'Zahid Ghori', 'update', 'Company', '89', 'COMP-001', 'Updated company \"InnovaTech\" (COMP-001): Logo updated', '{\"company_code\":\"COMP-001\",\"company_name\":\"InnovaTech\",\"email\":\"innova.tech213@gmail.com\",\"phone\":\"+923353789981\",\"mobile_no\":\"\",\"website\":\"\",\"address\":\"Ali block Loop road Bahria Town Karachi\",\"city\":\"Karachi\",\"is_active\":1}', '{\"company_code\":\"COMP-001\",\"company_name\":\"InnovaTech\",\"email\":\"innova.tech213@gmail.com\",\"phone\":\"+923353789981\",\"mobile_no\":\"\",\"website\":\"\",\"address\":\"Ali block Loop road Bahria Town Karachi\",\"city\":\"Karachi\",\"is_active\":1}', '172.68.249.154', '2026-09-22 19:47:17'),
('7048', '2', '69', 'Zahid Ghori', 'create', 'Territory', '75', 'Pakistan', 'Created country \"Pakistan\"', NULL, '{\"type\":\"country\",\"name\":\"Pakistan\"}', '172.68.249.154', '2026-09-22 19:51:32'),
('7049', '2', '69', 'Zahid Ghori', 'create', 'Territory', '87', 'Sindh', 'Created region \"Sindh\"', NULL, '{\"type\":\"region\",\"name\":\"Sindh\",\"country_id\":\"75\"}', '172.68.249.154', '2026-09-22 19:51:46'),
('7050', '2', '69', 'Zahid Ghori', 'create', 'Territory', '88', 'Punjab', 'Created region \"Punjab\"', NULL, '{\"type\":\"region\",\"name\":\"Punjab\",\"country_id\":\"75\"}', '172.68.249.154', '2026-09-22 19:51:57'),
('7051', '2', '69', 'Zahid Ghori', 'create', 'Territory', '232', 'Karachi', 'Created city \"Karachi\"', NULL, '{\"type\":\"city\",\"name\":\"Karachi\",\"region_id\":\"87\"}', '172.68.249.154', '2026-09-22 19:52:10'),
('7052', '2', '69', 'Zahid Ghori', 'create', 'Territory', '233', 'Hyderabad', 'Created city \"Hyderabad\"', NULL, '{\"type\":\"city\",\"name\":\"Hyderabad\",\"region_id\":\"87\"}', '172.68.249.154', '2026-09-22 19:52:22'),
('7053', '2', '69', 'Zahid Ghori', 'create', 'Territory', '234', 'Lahore', 'Created city \"Lahore\"', NULL, '{\"type\":\"city\",\"name\":\"Lahore\",\"region_id\":\"88\"}', '172.68.249.154', '2026-09-22 19:52:33'),
('7054', '2', '69', 'Zahid Ghori', 'create', 'Territory', '235', 'Multan', 'Created city \"Multan\"', NULL, '{\"type\":\"city\",\"name\":\"Multan\",\"region_id\":\"88\"}', '172.68.249.154', '2026-09-22 19:52:43'),
('7055', '2', '69', 'Zahid Ghori', 'create', 'Territory', '140', 'Gulshan-E-Iqbal', 'Created zone \"Gulshan-E-Iqbal\"', NULL, '{\"type\":\"zone\",\"name\":\"Gulshan-E-Iqbal\",\"city_id\":\"232\"}', '172.68.249.154', '2026-09-22 19:53:16'),
('7056', '2', '69', 'Zahid Ghori', 'create', 'Territory', '141', 'Gulistan-E-Johr', 'Created zone \"Gulistan-E-Johr\"', NULL, '{\"type\":\"zone\",\"name\":\"Gulistan-E-Johr\",\"city_id\":\"232\"}', '172.68.249.154', '2026-09-22 19:53:49'),
('7057', '2', '69', 'Zahid Ghori', 'create', 'Territory', '142', 'Latifabad', 'Created zone \"Latifabad\"', NULL, '{\"type\":\"zone\",\"name\":\"Latifabad\",\"city_id\":\"233\"}', '172.68.249.154', '2026-09-22 19:54:09'),
('7058', '2', '69', 'Zahid Ghori', 'create', 'Territory', '143', 'Gulberg', 'Created zone \"Gulberg\"', NULL, '{\"type\":\"zone\",\"name\":\"Gulberg\",\"city_id\":\"234\"}', '172.68.249.154', '2026-09-22 19:55:43'),
('7059', '2', '69', 'Zahid Ghori', 'create', 'Territory', '144', 'DHA', 'Created zone \"DHA\"', NULL, '{\"type\":\"zone\",\"name\":\"DHA\",\"city_id\":\"234\"}', '172.68.249.154', '2026-09-22 19:56:18'),
('7060', '2', '69', 'Zahid Ghori', 'create', 'Territory', '145', 'Purana Shujabat Road Bazar', 'Created zone \"Purana Shujabat Road Bazar\"', NULL, '{\"type\":\"zone\",\"name\":\"Purana Shujabat Road Bazar\",\"city_id\":\"235\"}', '172.68.249.154', '2026-09-22 19:57:53'),
('7061', '2', '69', 'Zahid Ghori', 'create', 'Territory', '142', '13D', 'Created area \"13D\"', NULL, '{\"type\":\"area\",\"name\":\"13D\",\"city_zone_id\":\"140\"}', '172.68.249.154', '2026-09-22 19:58:34'),
('7062', '2', '69', 'Zahid Ghori', 'create', 'Territory', '143', 'Disco Bakery', 'Created area \"Disco Bakery\"', NULL, '{\"type\":\"area\",\"name\":\"Disco Bakery\",\"city_zone_id\":\"140\"}', '172.68.249.154', '2026-09-22 19:58:52'),
('7063', '2', '69', 'Zahid Ghori', 'create', 'Territory', '144', 'Munawar Chowrangi', 'Created area \"Munawar Chowrangi\"', NULL, '{\"type\":\"area\",\"name\":\"Munawar Chowrangi\",\"city_zone_id\":\"141\"}', '172.68.249.154', '2026-09-22 19:59:12'),
('7064', '2', '69', 'Zahid Ghori', 'update', 'Company', '89', 'COMP-001', 'Updated company \"InnovaTech\" (COMP-001) - no tracked field changes', '{\"company_code\":\"COMP-001\",\"company_name\":\"InnovaTech\",\"email\":\"innova.tech213@gmail.com\",\"phone\":\"+923353789981\",\"mobile_no\":\"\",\"website\":\"\",\"address\":\"Ali block Loop road Bahria Town Karachi\",\"city\":\"Karachi\",\"is_active\":1}', '{\"company_code\":\"COMP-001\",\"company_name\":\"InnovaTech\",\"email\":\"innova.tech213@gmail.com\",\"phone\":\"+923353789981\",\"mobile_no\":\"\",\"website\":\"\",\"address\":\"Ali block Loop road Bahria Town Karachi\",\"city\":\"Karachi\",\"is_active\":1}', '172.68.249.154', '2026-09-22 20:00:29'),
('7065', '2', '69', 'Zahid Ghori', 'create', 'Currency', '80', 'PKR', 'Added currency \"Pakistani Rupee\" (PKR) as base currency', NULL, '{\"currency_id\":\"2\",\"currency_code\":\"PKR\",\"is_base_currency\":1}', '172.68.249.154', '2026-09-22 20:00:51'),
('7066', '2', '69', 'Zahid Ghori', 'create', 'Branch', '190', 'HE-001', 'Created branch \"Main Branch\" (HE-001)', NULL, '{\"branch_code\":\"HE-001\",\"branch_name\":\"Main Branch\",\"branch_type\":\"head_office\",\"company_id\":\"89\",\"is_active\":1}', '172.68.249.154', '2026-09-22 20:01:44'),
('7067', '2', '69', 'Zahid Ghori', 'create', 'Branch', '191', 'WA-001', 'Created branch \"Ware House No 1\" (WA-001)', NULL, '{\"branch_code\":\"WA-001\",\"branch_name\":\"Ware House No 1\",\"branch_type\":\"warehouse\",\"company_id\":\"89\",\"is_active\":1}', '172.68.249.154', '2026-09-22 20:02:17'),
('7068', '2', '69', 'Zahid Ghori', 'create', 'Branch', '192', 'WA-002', 'Created branch \"Ware House No 2\" (WA-002)', NULL, '{\"branch_code\":\"WA-002\",\"branch_name\":\"Ware House No 2\",\"branch_type\":\"warehouse\",\"company_id\":\"89\",\"is_active\":1}', '172.68.249.154', '2026-09-22 20:03:00'),
('7069', '2', '69', 'Zahid Ghori', 'update', 'Role Permission', '1', 'Super Admin', 'Updated 4 permission(s) for role \"Super Admin\"', '{\"permission_count\":233}', '{\"changed_count\":4}', '172.68.249.154', '2026-09-22 20:05:09'),
('7070', '2', '69', 'Zahid Ghori', 'create', 'Role Permission', '1', 'Super Admin', 'Created 2 permission(s) for role \"Super Admin\"', NULL, '{\"role_id\":\"1\",\"permission_count\":2}', '172.68.249.154', '2026-09-22 20:05:09'),
('7071', '2', '69', 'Zahid Ghori', 'update', 'User', '69', 'zahid@unisensystems.com', 'Updated user \"Zahid Ghori\" (zahid@unisensystems.com): Password changed', '{\"full_name\":\"Zahid Ghori\",\"email\":\"zahid@unisensystems.com\",\"is_active\":1,\"employee_id\":null}', '{\"full_name\":\"Zahid Ghori\",\"email\":\"zahid@unisensystems.com\",\"is_active\":1,\"employee_id\":null}', '172.68.249.154', '2026-09-22 20:05:30'),
('7072', '2', '69', 'Zahid Ghori', 'update', 'User', '69', 'zahid@unisensystems.com', 'Reassigned role for user \"Zahid Ghori\" (zahid@unisensystems.com) - no tracked field changes', '{\"role\":\"Super Admin\"}', '{\"role\":\"Super Admin\"}', '172.68.249.154', '2026-09-22 20:05:30'),
('7073', '2', '69', 'Zahid Ghori', 'update', 'User', '69', 'zahid@unisensystems.com', 'Updated branch access for user \"Zahid Ghori\" (zahid@unisensystems.com): [] → [191,192]', '{\"branch_ids\":[]}', '{\"branch_ids\":[191,192]}', '172.68.249.154', '2026-09-22 20:05:30'),
('7074', '2', '69', 'Zahid Ghori', 'create', 'Role', '29', 'User', 'Created role \"User\"', NULL, '{\"name\":\"User\",\"description\":\"User1\"}', '172.68.249.154', '2026-09-22 20:05:58'),
('7075', '2', '69', 'Zahid Ghori', 'create', 'User', '146', 'Branch1@gmail.com', 'Created user \"Asif\" (Branch1@gmail.com)', NULL, '{\"full_name\":\"Asif\",\"email\":\"Branch1@gmail.com\",\"phone\":\"\",\"employee_id\":null}', '172.68.249.154', '2026-09-22 20:07:40'),
('7076', '2', '69', 'Zahid Ghori', 'update', 'User', '146', 'Branch1@gmail.com', 'Assigned role to user \"Asif\" (Branch1@gmail.com): (none) → User', '{\"role\":null}', '{\"role\":\"User\"}', '172.68.249.154', '2026-09-22 20:07:41'),
('7077', '2', '69', 'Zahid Ghori', 'update', 'Role Permission', '5', 'User', 'Updated 4 permission(s) for role \"User\"', '{\"permission_count\":117}', '{\"changed_count\":4}', '172.68.249.154', '2026-09-22 20:08:51'),
('7078', '2', '69', 'Zahid Ghori', 'create', 'Role Permission', '5', 'User', 'Created 3 permission(s) for role \"User\"', NULL, '{\"role_id\":\"5\",\"permission_count\":3}', '172.68.249.154', '2026-09-22 20:08:51'),
('7079', '2', '69', 'Zahid Ghori', 'create', 'Role', '30', 'User2', 'Created role \"User2\"', NULL, '{\"name\":\"User2\",\"description\":\"\"}', '172.68.249.154', '2026-09-22 20:09:11'),
('7080', '2', '69', 'Zahid Ghori', 'create', 'Role Permission', '30', 'User2', 'Created 8 permission(s) for role \"User2\"', NULL, '{\"role_id\":\"30\",\"permission_count\":8}', '172.68.249.154', '2026-09-22 20:10:04'),
('7081', '2', '69', 'Zahid Ghori', 'create', 'User', '147', 'Branch2', 'Created user \"Luqman\" (Branch2)', NULL, '{\"full_name\":\"Luqman\",\"email\":\"Branch2\",\"phone\":\"\",\"employee_id\":null}', '172.68.249.154', '2026-09-22 20:10:55'),
('7082', '2', '69', 'Zahid Ghori', 'update', 'User', '147', 'Branch2', 'Assigned role to user \"Luqman\" (Branch2): (none) → User2', '{\"role\":null}', '{\"role\":\"User2\"}', '172.68.249.154', '2026-09-22 20:10:56'),
('7083', '2', '69', 'Zahid Ghori', 'update', 'User', '147', 'Branch2', 'Updated user \"Luqman\" (Branch2): Password changed', '{\"full_name\":\"Luqman\",\"email\":\"Branch2\",\"is_active\":1,\"employee_id\":null}', '{\"full_name\":\"Luqman\",\"email\":\"Branch2\",\"is_active\":1,\"employee_id\":null}', '172.68.249.154', '2026-09-22 20:11:13'),
('7084', '2', '69', 'Zahid Ghori', 'update', 'User', '147', 'Branch2', 'Reassigned role for user \"Luqman\" (Branch2) - no tracked field changes', '{\"role\":\"User2\"}', '{\"role\":\"User2\"}', '172.68.249.154', '2026-09-22 20:11:13'),
('7085', '2', '69', 'Zahid Ghori', 'update', 'User', '147', 'Branch2', 'Updated branch access for user \"Luqman\" (Branch2): [] → [192]', '{\"branch_ids\":[]}', '{\"branch_ids\":[192]}', '172.68.249.154', '2026-09-22 20:11:13'),
('7086', '2', '69', 'Zahid Ghori', 'update', 'User', '146', 'Branch1@gmail.com', 'Updated user \"Asif\" (Branch1@gmail.com): Password changed', '{\"full_name\":\"Asif\",\"email\":\"Branch1@gmail.com\",\"is_active\":1,\"employee_id\":null}', '{\"full_name\":\"Asif\",\"email\":\"Branch1@gmail.com\",\"is_active\":1,\"employee_id\":null}', '172.68.249.154', '2026-09-22 20:11:30'),
('7087', '2', '69', 'Zahid Ghori', 'update', 'User', '146', 'Branch1@gmail.com', 'Reassigned role for user \"Asif\" (Branch1@gmail.com) - no tracked field changes', '{\"role\":\"User\"}', '{\"role\":\"User\"}', '172.68.249.154', '2026-09-22 20:11:30'),
('7088', '2', '69', 'Zahid Ghori', 'update', 'User', '146', 'Branch1@gmail.com', 'Updated branch access for user \"Asif\" (Branch1@gmail.com): [] → [191]', '{\"branch_ids\":[]}', '{\"branch_ids\":[191]}', '172.68.249.154', '2026-09-22 20:11:30'),
('7125', '2', '69', 'Zahid Ghori', 'create', 'Tax Regime', '34', 'AIT', 'Created tax regime \"Advance Income Tax\" (AIT)', NULL, '{\"regime_name\":\"Advance Income Tax\",\"regime_code\":\"AIT\",\"tax_authority\":\"FBR\",\"tax_base\":\"trade_price\",\"is_active\":1}', '172.68.249.154', '2026-09-23 04:30:33'),
('7128', '2', '69', 'Zahid Ghori', 'create', 'Tax Regime', '35', 'ST', 'Created tax regime \"Sales Tax\" (ST)', NULL, '{\"regime_name\":\"Sales Tax\",\"regime_code\":\"ST\",\"tax_authority\":\"FBR\",\"tax_base\":\"value_excl_sales_tax\",\"is_active\":1}', '172.68.249.154', '2026-09-23 04:31:34'),
('7131', '2', '69', 'Zahid Ghori', 'create', 'Tax Rate', '57', 'AIT - Filer', 'Created tax rate \"AIT - Filer\" (0.5%, advance_tax)', NULL, '{\"tax_name\":\"AIT - Filer\",\"tax_type\":\"advance_tax\",\"transaction_type\":\"sale\",\"rate_percentage\":0.5,\"tax_regime_id\":34,\"is_active\":1}', '172.68.249.154', '2026-09-23 04:33:00'),
('7133', '2', '69', 'Zahid Ghori', 'create', 'Tax Rate', '58', 'AIT - Non-Filer', 'Created tax rate \"AIT - Non-Filer\" (2.5%, advance_tax)', NULL, '{\"tax_name\":\"AIT - Non-Filer\",\"tax_type\":\"advance_tax\",\"transaction_type\":\"sale\",\"rate_percentage\":2.5,\"tax_regime_id\":34,\"is_active\":1}', '172.68.249.154', '2026-09-23 04:34:00'),
('7136', '2', '69', 'Zahid Ghori', 'create', 'Tax Rate', '59', 'Sales Tax Sale', 'Created tax rate \"Sales Tax Sale\" (18%, sales_tax)', NULL, '{\"tax_name\":\"Sales Tax Sale\",\"tax_type\":\"sales_tax\",\"transaction_type\":\"sale\",\"rate_percentage\":18,\"tax_regime_id\":35,\"is_active\":1}', '172.68.249.154', '2026-09-23 04:35:20'),
('7138', '2', '69', 'Zahid Ghori', 'create', 'Tax Rate', '60', 'Sales Tax Purchase', 'Created tax rate \"Sales Tax Purchase\" (18%, sales_tax)', NULL, '{\"tax_name\":\"Sales Tax Purchase\",\"tax_type\":\"sales_tax\",\"transaction_type\":\"purchase\",\"rate_percentage\":18,\"tax_regime_id\":35,\"is_active\":1}', '172.68.249.154', '2026-09-23 04:36:27'),
('7225', '2', '69', 'Zahid Ghori', 'create', 'Employee', '244', 'SALES-0001', 'Created employee \"dfgd\" (SALES-0001), base salary 25,000.00', NULL, '{\"employee_id\":\"SALES-0001\",\"full_name\":\"dfgd\",\"department_id\":\"5\",\"position_id\":\"7\",\"base_salary\":\"25000\",\"employment_type\":\"contract\"}', '172.68.249.154', '2026-09-23 05:22:59'),
('7226', '2', '69', 'Zahid Ghori', 'delete', 'Employee', '244', 'SALES-0001', 'Deleted employee \"dfgd\" (SALES-0001)', '{\"id\":244,\"tenant_id\":2,\"company_id\":null,\"employee_id\":\"SALES-0001\",\"full_name\":\"dfgd\",\"date_of_birth\":\"2001-09-23\",\"gender\":\"female\",\"nationality\":\"\",\"marital_status\":\"\",\"email\":null,\"personal_email\":null,\"phone_number\":\"\",\"emergency_contact_name\":\"\",\"emergency_contact_phone\":null,\"address\":\"\",\"city\":null,\"state\":null,\"postal_code\":null,\"country\":null,\"profile_photo_url\":null,\"department_id\":5,\"position_id\":7,\"employment_type\":\"contract\",\"employee_category\":\"\",\"hire_date\":\"2026-09-23\",\"probation_end_date\":null,\"work_location\":\"\",\"shift_timing\":\"09:00-17:00\",\"reporting_manager_id\":null,\"work_hours_per_week\":null,\"work_days_per_week\":null,\"current_status\":\"active\",\"status_effective_date\":\"2026-09-23\",\"status_change_reason\":null,\"leave_balance_paid\":21,\"leave_balance_sick\":10,\"leave_balance_unpaid\":0,\"current_leave_id\":null,\"is_suspended\":0,\"suspension_start_date\":null,\"suspension_end_date\":null,\"suspension_type\":null,\"suspension_reason\":null,\"is_terminated\":0,\"termination_date\":null,\"termination_type\":null,\"termination_reason\":null,\"notice_period\":null,\"exit_interview_notes\":null,\"final_settlement_processed\":0,\"gratuity_paid\":0,\"base_salary\":\"25000.00\",\"salary_currency\":\"USD\",\"salary_frequency\":\"daily\",\"housing_allowance\":\"0.00\",\"transport_allowance\":\"0.00\",\"medical_allowance\":\"0.00\",\"meal_allowance\":\"0.00\",\"communication_allowance\":\"0.00\",\"education_allowance\":\"0.00\",\"travel_allowance\":\"0.00\",\"other_allowance\":\"0.00\",\"annual_bonus_percentage\":\"0.00\",\"provident_fund_percentage\":\"0.00\",\"gratuity_eligibility\":0,\"gratuity_eligibility_after_years\":5,\"health_insurance\":1,\"life_insurance\":0,\"paid_time_off\":1,\"employment_agreement_url\":null,\"id_proof_url\":null,\"resume_url\":null,\"certificates_url\":null,\"medical_certificate_url\":null,\"notes\":\"\",\"employee_notes\":null,\"hr_notes\":null,\"created_at\":\"2026-09-23 05:22:59\",\"created_by\":69,\"updated_at\":\"2026-09-23 05:22:59\",\"updated_by\":null,\"is_active\":1,\"deleted_at\":null,\"deleted_by\":null,\"leave_reset_period\":\"yearly\",\"next_reset_date\":\"2027-01-01\",\"carry_forward_allowed\":0,\"overtime_rate_per_hour\":\"0.00\",\"overtime_rate\":\"0.00\",\"overtime_rate_type\":\"per_hour\"}', NULL, '172.68.249.154', '2026-09-23 05:23:08'),
('7228', '2', '69', 'Zahid Ghori', 'create', 'Employee', '245', 'SALES-0001', 'Created employee \"Raheel\" (SALES-0001), base salary 40,000.00', NULL, '{\"employee_id\":\"SALES-0001\",\"full_name\":\"Raheel\",\"department_id\":\"5\",\"position_id\":\"7\",\"base_salary\":\"40000\",\"employment_type\":\"full-time\"}', '104.22.175.142', '2026-09-23 05:24:40'),
('7229', '2', '69', 'Zahid Ghori', 'create', 'Employee', '246', 'SALES-0002', 'Created employee \"Wasim\" (SALES-0002), base salary 60,000.00', NULL, '{\"employee_id\":\"SALES-0002\",\"full_name\":\"Wasim\",\"department_id\":\"5\",\"position_id\":\"7\",\"base_salary\":\"60000\",\"employment_type\":\"full-time\"}', '104.22.175.142', '2026-09-23 05:27:34'),
('7232', '2', '69', 'Zahid Ghori', 'create', 'Position', '77', 'IT Officer', 'Created position \"IT Officer\" (IT O)', NULL, '{\"position_title\":\"IT Officer\",\"position_code\":\"IT O\",\"department_id\":\"1\"}', '172.68.249.154', '2026-09-23 05:36:17'),
('7233', '2', '69', 'Zahid Ghori', 'create', 'Position', '79', 'IT Manager', 'Created position \"IT Manager\" (IT M)', NULL, '{\"position_title\":\"IT Manager\",\"position_code\":\"IT M\",\"department_id\":\"1\"}', '172.68.249.154', '2026-09-23 05:37:26'),
('7234', '2', '69', 'Zahid Ghori', 'create', 'Position', '81', 'Suplier Man', 'Created position \"Suplier Man\" (SUPL)', NULL, '{\"position_title\":\"Suplier Man\",\"position_code\":\"SUPL\",\"department_id\":\"5\"}', '104.22.175.142', '2026-09-23 05:38:34'),
('7235', '2', '69', 'Zahid Ghori', 'create', 'Employee', '247', 'SALES-0003', 'Created employee \"Naseer\" (SALES-0003), base salary 30,000.00', NULL, '{\"employee_id\":\"SALES-0003\",\"full_name\":\"Naseer\",\"department_id\":\"5\",\"position_id\":\"81\",\"base_salary\":\"30000\",\"employment_type\":\"full-time\"}', '104.22.175.142', '2026-09-23 05:39:51'),
('7270', '2', '69', 'Zahid Ghori', 'create', 'Supplier', '461', 'SUPP-00001', 'Created supplier \"Able\" (SUPP-00001)', NULL, '{\"supplier_code\":\"SUPP-00001\",\"supplier_name\":\"Able\",\"primary_phone\":\"03349696859\",\"company_id\":89}', '172.68.249.154', '2026-09-23 06:19:52'),
('7273', '2', '69', 'Zahid Ghori', 'create', 'Supplier', '462', 'SUPP-00002', 'Created supplier \"Shahid & Sons\" (SUPP-00002)', NULL, '{\"supplier_code\":\"SUPP-00002\",\"supplier_name\":\"Shahid & Sons\",\"primary_phone\":\"03324124125\",\"company_id\":89}', '104.22.175.142', '2026-09-23 06:21:50'),
('7329', '2', '69', 'Zahid Ghori', 'create', 'Supplier', '463', 'SUPP-00003', 'Created supplier \"Abc\" (SUPP-00003)', NULL, '{\"supplier_code\":\"SUPP-00003\",\"supplier_name\":\"Abc\",\"primary_phone\":null,\"company_id\":89}', '104.22.175.142', '2026-09-23 08:44:23'),
('7345', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '63', 'SALES-0001 - 2026-09-23', 'Recorded attendance for employee \"SALES-0001\" on 2026-09-23: 14:41 - 14:41 (status: late)', NULL, '{\"employee_id\":\"SALES-0001\",\"attendance_date\":\"2026-09-23\",\"check_in_time\":\"2026-09-23 14:41:00\",\"check_out_time\":\"2026-09-23 14:41:00\",\"status\":\"late\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 09:41:38'),
('7346', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '63', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 09:41:56'),
('7347', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '64', 'SALES-0001 - 2026-09-23', 'Recorded attendance for employee \"SALES-0001\" on 2026-09-23: 14:42 - 17:00 (status: late)', NULL, '{\"employee_id\":\"SALES-0001\",\"attendance_date\":\"2026-09-23\",\"check_in_time\":\"2026-09-23 14:42:00\",\"check_out_time\":\"2026-09-23 17:00:00\",\"status\":\"late\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 09:42:04'),
('7348', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '65', 'SALES-0001 - 2026-09-23', 'Recorded attendance for employee \"SALES-0001\" on 2026-09-23: 14:42 - 17:00 (status: late)', NULL, '{\"employee_id\":\"SALES-0001\",\"attendance_date\":\"2026-09-23\",\"check_in_time\":\"2026-09-23 14:42:00\",\"check_out_time\":\"2026-09-23 17:00:00\",\"status\":\"late\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 09:42:04'),
('7349', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '65', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 09:42:23'),
('7350', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '64', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 09:42:26'),
('7351', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '66', 'SALES-0003 - 2026-09-23', 'Recorded attendance for employee \"SALES-0003\" on 2026-09-23: 14:42 - 17:00 (status: late)', NULL, '{\"employee_id\":\"SALES-0003\",\"attendance_date\":\"2026-09-23\",\"check_in_time\":\"2026-09-23 14:42:00\",\"check_out_time\":\"2026-09-23 17:00:00\",\"status\":\"late\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 09:42:54'),
('7352', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '67', 'SALES-0003 - 2026-09-23', 'Recorded attendance for employee \"SALES-0003\" on 2026-09-23: 14:42 - 17:00 (status: late)', NULL, '{\"employee_id\":\"SALES-0003\",\"attendance_date\":\"2026-09-23\",\"check_in_time\":\"2026-09-23 14:42:00\",\"check_out_time\":\"2026-09-23 17:00:00\",\"status\":\"late\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 09:42:54'),
('7353', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '67', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 09:43:13'),
('7354', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '66', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 09:43:15'),
('7355', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '68', 'SALES-0001 - 2026-09-23', 'Recorded attendance for employee \"SALES-0001\" on 2026-09-23: 09:00 - 17:00 (status: present)', NULL, '{\"employee_id\":\"SALES-0001\",\"attendance_date\":\"2026-09-23\",\"check_in_time\":\"2026-09-23 09:00:00\",\"check_out_time\":\"2026-09-23 17:00:00\",\"status\":\"present\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 09:44:44'),
('7357', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '69', 'SALES-0001 - 2026-09-01', 'Recorded attendance for employee \"SALES-0001\" on 2026-09-01: 10:00 - 17:00 (status: late)', NULL, '{\"employee_id\":\"SALES-0001\",\"attendance_date\":\"2026-09-01\",\"check_in_time\":\"2026-09-01 10:00:00\",\"check_out_time\":\"2026-09-01 17:00:00\",\"status\":\"late\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 09:47:06'),
('7358', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '70', 'SALES-0001 - 2026-09-02', 'Recorded attendance for employee \"SALES-0001\" on 2026-09-02: 10:00 - 17:00 (status: late)', NULL, '{\"employee_id\":\"SALES-0001\",\"attendance_date\":\"2026-09-02\",\"check_in_time\":\"2026-09-02 10:00:00\",\"check_out_time\":\"2026-09-02 17:00:00\",\"status\":\"late\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 09:47:40'),
('7359', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '71', 'SALES-0001 - 2026-09-03', 'Recorded attendance for employee \"SALES-0001\" on 2026-09-03: 10:00 - 17:00 (status: late)', NULL, '{\"employee_id\":\"SALES-0001\",\"attendance_date\":\"2026-09-03\",\"check_in_time\":\"2026-09-03 10:00:00\",\"check_out_time\":\"2026-09-03 17:00:00\",\"status\":\"late\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 09:51:34'),
('7361', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '72', 'SALES-0001 - 2026-09-04', 'Recorded attendance for employee \"SALES-0001\" on 2026-09-04: 10:00 - 17:00 (status: late)', NULL, '{\"employee_id\":\"SALES-0001\",\"attendance_date\":\"2026-09-04\",\"check_in_time\":\"2026-09-04 10:00:00\",\"check_out_time\":\"2026-09-04 17:00:00\",\"status\":\"late\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 09:53:23'),
('7371', '2', '69', 'Zahid Ghori', 'create', 'Product', '7900', 'PROD-000001', 'Created product \"Test\" (PROD-000001)', NULL, '{\"code\":\"PROD-000001\",\"name\":\"Test\",\"product_type\":\"physical\",\"uom_type\":\"unit\",\"company_id\":\"89\",\"default_unit_id\":\"9\",\"uom_group_id\":null,\"product_conversion_factor\":null,\"category_id\":null,\"subcategory_id\":null,\"inventory_account_id\":\"33\",\"vendor_id\":\"462\",\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":\"9999\",\"barcode\":\"8654\",\"purchase_price\":\"100\",\"trade_price\":\"200\",\"wholesale_price\":\"170\",\"mrp\":\"300\",\"default_discount\":0,\"trade_offer_discount\":0,\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":null,\"min_stock_level\":0,\"max_stock_level\":0,\"manufacturing_date\":null,\"expiry_date\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '104.22.175.142', '2026-09-23 10:09:41'),
('7372', '2', '69', 'Zahid Ghori', 'update', 'Product', '7900', 'PROD-000001', 'Updated product \"Test\" (PROD-000001): Category: (empty) → 311', '{\"code\":\"PROD-000001\",\"name\":\"Test\",\"product_type\":\"physical\",\"uom_type\":\"unit\",\"company_id\":89,\"default_unit_id\":9,\"uom_group_id\":null,\"product_conversion_factor\":null,\"category_id\":null,\"subcategory_id\":null,\"inventory_account_id\":33,\"vendor_id\":462,\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":\"9999\",\"barcode\":\"8654\",\"purchase_price\":\"100.000\",\"trade_price\":\"200.000\",\"wholesale_price\":\"170.000\",\"mrp\":\"300.000\",\"price_entry_unit_id\":null,\"trade_price_entry_value\":null,\"mrp_entry_value\":null,\"default_discount\":\"0.00\",\"trade_offer_discount\":\"0.00\",\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":null,\"min_stock_level\":\"0.000\",\"max_stock_level\":\"0.000\",\"manufacturing_date\":null,\"expiry_date\":null,\"photo\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '{\"code\":\"PROD-000001\",\"name\":\"Test\",\"product_type\":\"physical\",\"uom_type\":\"unit\",\"company_id\":\"89\",\"default_unit_id\":\"9\",\"uom_group_id\":null,\"product_conversion_factor\":null,\"category_id\":\"311\",\"subcategory_id\":null,\"inventory_account_id\":\"33\",\"vendor_id\":\"462\",\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":\"9999\",\"barcode\":\"8654\",\"purchase_price\":\"100.000\",\"trade_price\":\"200.000\",\"wholesale_price\":\"170.000\",\"mrp\":\"300.000\",\"price_entry_unit_id\":null,\"trade_price_entry_value\":null,\"mrp_entry_value\":null,\"default_discount\":\"0.00\",\"trade_offer_discount\":0,\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":null,\"min_stock_level\":\"0.000\",\"max_stock_level\":\"0.000\",\"manufacturing_date\":null,\"expiry_date\":null,\"photo\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '104.22.175.142', '2026-09-23 10:10:30'),
('7373', '2', '69', 'Zahid Ghori', 'create', 'Product', '7901', 'PROD-000002', 'Created product \"Test2\" (PROD-000002)', NULL, '{\"code\":\"PROD-000002\",\"name\":\"Test2\",\"product_type\":\"physical\",\"uom_type\":\"group\",\"company_id\":\"89\",\"default_unit_id\":null,\"uom_group_id\":\"1\",\"product_conversion_factor\":null,\"category_id\":\"311\",\"subcategory_id\":null,\"inventory_account_id\":\"33\",\"vendor_id\":null,\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":null,\"barcode\":null,\"purchase_price\":\"300\",\"trade_price\":\"450\",\"wholesale_price\":\"400\",\"mrp\":\"500\",\"default_discount\":0,\"trade_offer_discount\":0,\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":null,\"min_stock_level\":0,\"max_stock_level\":0,\"manufacturing_date\":null,\"expiry_date\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '104.22.175.142', '2026-09-23 10:17:32'),
('7394', '2', '69', 'Zahid Ghori', 'create', 'Purchase Order', '355', 'PO-0001', 'Created purchase order \"PO-0001\" (1 item(s), net 3,000.00)', NULL, '{\"bill_no\":\"PO-0001\",\"purchase_date\":\"2026-09-23\",\"supplier_id\":\"462\",\"branch_id\":\"191\",\"company_id\":\"89\",\"currency_id\":\"2\",\"project_id\":null,\"total_bill\":3000,\"total_discount_percent\":0,\"total_discount_amount\":0,\"net_amount\":3000,\"supplier_invoice_no\":null,\"bilty_no\":null,\"transport_name\":null,\"remarks\":\"\",\"items\":[{\"productId\":\"7900\",\"unitEntries\":[{\"uomId\":\"9\",\"quantity\":30}],\"purchasePrice\":100,\"grossAmount\":3000,\"discountPercent\":0,\"discountAmount\":0,\"tradeOfferPercent\":0,\"tradeOfferAmount\":0,\"gstPercent\":0,\"gstAmount\":0,\"focQty\":0,\"netAmount\":3000}]}', '104.22.175.142', '2026-09-23 12:13:33'),
('7400', '2', '69', 'Zahid Ghori', 'create', 'Purchase Invoice', '1957', 'PUR-0001', 'Created purchase invoice \"PUR-0001\" (1 item(s), net 2,000.00)', NULL, '{\"bill_no\":\"PUR-0001\",\"purchase_date\":\"2026-09-23\",\"supplier_id\":\"462\",\"branch_id\":\"191\",\"company_id\":\"89\",\"currency_id\":\"2\",\"container_id\":null,\"total_bill\":2000,\"total_discount_percent\":0,\"total_discount_amount\":0,\"total_tax_amount\":0,\"shipping_fees\":0,\"net_amount\":2000,\"supplier_invoice_no\":\"325\",\"purchase_order_id\":\"355\",\"bilty_no\":\"7856\",\"transport_name\":\"Baba Fareed\",\"remarks\":\"\",\"items\":[{\"productId\":\"7900\",\"size\":\"\",\"batchNo\":null,\"batchExpiryDate\":\"2026-09-23\",\"unitEntries\":[{\"uomId\":\"9\",\"quantity\":20}],\"purchasePrice\":100,\"grossAmount\":2000,\"discountPercent\":0,\"discountAmount\":0,\"extraDiscountPercent\":0,\"tradeOfferPercent\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":2000}]}', '104.22.175.142', '2026-09-23 12:33:08'),
('7446', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '68', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 13:14:14'),
('7459', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '73', 'SALES-0001 - 2026-09-20', 'Recorded attendance for employee \"SALES-0001\" on 2026-09-20: 09:00 - 17:00 (status: present)', NULL, '{\"employee_id\":\"SALES-0001\",\"attendance_date\":\"2026-09-20\",\"check_in_time\":\"2026-09-20 09:00:00\",\"check_out_time\":\"2026-09-20 17:00:00\",\"status\":\"present\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 13:22:46'),
('7461', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '69', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 13:23:17'),
('7462', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '70', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 13:23:19'),
('7463', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '71', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 13:23:22'),
('7464', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '72', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 13:23:25'),
('7465', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '74', 'SALES-0001 - 2026-09-21', 'Recorded attendance for employee \"SALES-0001\" on 2026-09-21: 09:00 - 17:00 (status: present)', NULL, '{\"employee_id\":\"SALES-0001\",\"attendance_date\":\"2026-09-21\",\"check_in_time\":\"2026-09-21 09:00:00\",\"check_out_time\":\"2026-09-21 17:00:00\",\"status\":\"present\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 13:24:02'),
('7466', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '75', 'SALES-0001 - 2026-09-22', 'Recorded attendance for employee \"SALES-0001\" on 2026-09-22: 09:00 - 17:00 (status: present)', NULL, '{\"employee_id\":\"SALES-0001\",\"attendance_date\":\"2026-09-22\",\"check_in_time\":\"2026-09-22 09:00:00\",\"check_out_time\":\"2026-09-22 17:00:00\",\"status\":\"present\",\"notes\":\"\"}', '104.22.175.142', '2026-09-23 13:24:19'),
('7467', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '73', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 13:24:38'),
('7468', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '74', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 13:24:39'),
('7469', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '75', 'Attendance record deleted', NULL, NULL, NULL, '104.22.175.142', '2026-09-23 13:24:41'),
('7478', '2', '69', 'Zahid Ghori', 'create', 'Supplier', '465', 'SUPP-00004', 'Created supplier \"Brand Name\" (SUPP-00004)', NULL, '{\"supplier_code\":\"SUPP-00004\",\"supplier_name\":\"Brand Name\",\"primary_phone\":null,\"company_id\":89}', '104.22.175.142', '2026-09-23 14:04:44'),
('7479', '2', '69', 'Zahid Ghori', 'delete', 'Supplier', '465', 'SUPP-00004', 'Deleted supplier \"Brand Name\" (SUPP-00004)', '{\"id\":465,\"tenant_id\":2,\"company_id\":89,\"salesman_id\":null,\"supplier_category_id\":null,\"project_id\":null,\"supplier_code\":\"SUPP-00004\",\"supplier_type\":\"Manufacturer\",\"supplier_name\":\"Brand Name\",\"brand_name\":\"Supplier\",\"address\":null,\"city\":null,\"primary_phone\":null,\"secondary_phone\":null,\"identity_card_no\":null,\"email\":null,\"country_id\":null,\"region_id\":null,\"city_id\":null,\"city_zone_id\":null,\"area_id\":null,\"opening_debit_amount\":\"0.00\",\"opening_credit_amount\":\"0.00\",\"ait_percent\":\"0.00\",\"current_balance\":\"0.00\",\"is_blacklisted\":0,\"is_both\":0,\"linked_customer_id\":null,\"status\":\"ACTIVE\",\"created_at\":\"2026-09-23 14:04:44\",\"updated_at\":\"2026-09-23 14:04:44\",\"created_by\":69,\"updated_by\":69,\"party_type\":null,\"credit_days\":0}', NULL, '104.22.175.142', '2026-09-23 14:30:41'),
('7480', '2', '69', 'Zahid Ghori', 'delete', 'Supplier', '463', 'SUPP-00003', 'Deleted supplier \"Abc\" (SUPP-00003)', '{\"id\":463,\"tenant_id\":2,\"company_id\":89,\"salesman_id\":null,\"supplier_category_id\":null,\"project_id\":null,\"supplier_code\":\"SUPP-00003\",\"supplier_type\":\"Manufacturer\",\"supplier_name\":\"Abc\",\"brand_name\":\"zahid\",\"address\":null,\"city\":null,\"primary_phone\":null,\"secondary_phone\":null,\"identity_card_no\":null,\"email\":null,\"country_id\":null,\"region_id\":null,\"city_id\":null,\"city_zone_id\":null,\"area_id\":null,\"opening_debit_amount\":\"0.00\",\"opening_credit_amount\":\"0.00\",\"ait_percent\":\"0.00\",\"current_balance\":\"0.00\",\"is_blacklisted\":0,\"is_both\":0,\"linked_customer_id\":null,\"status\":\"ACTIVE\",\"created_at\":\"2026-09-23 08:44:23\",\"updated_at\":\"2026-09-23 08:44:23\",\"created_by\":69,\"updated_by\":69,\"party_type\":null,\"credit_days\":0}', NULL, '104.22.175.142', '2026-09-23 14:31:22'),
('7481', '2', '69', 'Zahid Ghori', 'update', 'Supplier', '462', 'SUPP-00002', 'Updated supplier \"Shahid & Sons\" (SUPP-00002): Credit Days: 0 → 25', '{\"supplier_code\":\"SUPP-00002\",\"supplier_name\":\"Shahid & Sons\",\"brand_name\":null,\"address\":\"55C Lakshmi Chock\",\"primary_phone\":\"03324124125\",\"secondary_phone\":null,\"email\":null,\"credit_days\":0,\"is_blacklisted\":0}', '{\"supplier_code\":\"SUPP-00002\",\"supplier_name\":\"Shahid & Sons\",\"brand_name\":null,\"address\":\"55C Lakshmi Chock\",\"primary_phone\":\"03324124125\",\"secondary_phone\":null,\"email\":null,\"credit_days\":25,\"is_blacklisted\":0}', '104.22.175.142', '2026-09-23 14:31:50'),
('7482', '2', '69', 'Zahid Ghori', 'create', 'Supplier', '465', 'SUPP-00003', 'Created supplier \"Khan Pvt Ltd\" (SUPP-00003)', NULL, '{\"supplier_code\":\"SUPP-00003\",\"supplier_name\":\"Khan Pvt Ltd\",\"primary_phone\":\"03369696854\",\"company_id\":89}', '104.22.175.142', '2026-09-23 14:41:30'),
('7483', '2', '69', 'Zahid Ghori', 'create', 'Purchase Invoice', '1966', 'PUR-0002', 'Created purchase invoice \"PUR-0002\" (2 item(s), net 29,160.16)', NULL, '{\"bill_no\":\"PUR-0002\",\"purchase_date\":\"2026-09-06\",\"supplier_id\":\"465\",\"branch_id\":\"191\",\"company_id\":\"89\",\"currency_id\":\"2\",\"container_id\":null,\"total_bill\":25600,\"total_discount_percent\":2,\"total_discount_amount\":288,\"total_tax_amount\":0,\"shipping_fees\":600,\"net_amount\":29160.16,\"supplier_invoice_no\":\"1112\",\"purchase_order_id\":null,\"bilty_no\":\"5545\",\"transport_name\":\"Super Mama\",\"remarks\":\"\",\"items\":[{\"productId\":\"7901\",\"size\":\"\",\"batchNo\":null,\"batchExpiryDate\":\"2026-09-23\",\"unitEntries\":[{\"uomId\":\"16\",\"quantity\":3}],\"purchasePrice\":300,\"grossAmount\":21600,\"discountPercent\":0,\"discountAmount\":0,\"extraDiscountPercent\":0,\"tradeOfferPercent\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":21600},{\"productId\":\"7900\",\"size\":\"\",\"batchNo\":null,\"batchExpiryDate\":\"2026-09-23\",\"unitEntries\":[{\"uomId\":\"9\",\"quantity\":40}],\"purchasePrice\":100,\"grossAmount\":4000,\"discountPercent\":0,\"discountAmount\":0,\"extraDiscountPercent\":0,\"tradeOfferPercent\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":4000}]}', '104.22.175.142', '2026-09-23 14:45:09'),
('7488', '2', '69', 'Zahid Ghori', 'create', 'Product', '7908', 'PROD-000003', 'Created product \"Sample\" (PROD-000003)', NULL, '{\"code\":\"PROD-000003\",\"name\":\"Sample\",\"product_type\":\"physical\",\"uom_type\":\"unit\",\"company_id\":\"89\",\"default_unit_id\":\"9\",\"uom_group_id\":null,\"product_conversion_factor\":null,\"category_id\":null,\"subcategory_id\":null,\"inventory_account_id\":\"33\",\"vendor_id\":null,\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":null,\"barcode\":null,\"purchase_price\":0,\"trade_price\":0,\"wholesale_price\":0,\"mrp\":0,\"default_discount\":0,\"trade_offer_discount\":0,\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":null,\"min_stock_level\":0,\"max_stock_level\":0,\"manufacturing_date\":null,\"expiry_date\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '104.22.175.142', '2026-09-23 15:39:52'),
('7489', '2', '69', 'Zahid Ghori', 'delete', 'Product', '7908', 'PROD-000003', 'Deleted product \"Sample\" (PROD-000003)', '{\"id\":7908,\"code\":\"PROD-000003\",\"name\":\"Sample\"}', NULL, '104.22.175.142', '2026-09-23 15:42:33'),
('7490', '2', '69', 'Zahid Ghori', 'create', 'Product', '7909', 'PROD-000003', 'Created product \"Sample\" (PROD-000003)', NULL, '{\"code\":\"PROD-000003\",\"name\":\"Sample\",\"product_type\":\"physical\",\"uom_type\":\"unit\",\"company_id\":\"89\",\"default_unit_id\":\"9\",\"uom_group_id\":null,\"product_conversion_factor\":null,\"category_id\":null,\"subcategory_id\":null,\"inventory_account_id\":\"33\",\"vendor_id\":null,\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":null,\"barcode\":null,\"purchase_price\":\"100\",\"trade_price\":\"200\",\"wholesale_price\":0,\"mrp\":0,\"default_discount\":0,\"trade_offer_discount\":0,\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":null,\"min_stock_level\":0,\"max_stock_level\":0,\"manufacturing_date\":null,\"expiry_date\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '104.22.175.142', '2026-09-23 15:44:25'),
('7491', '2', '69', 'Zahid Ghori', 'create', 'Sale Invoice', '18758', 'SAL-0001', 'Created sale invoice \"SAL-0001\" (1 item(s), net 400.00)', NULL, '{\"bill_no\":\"SAL-0001\",\"sale_date\":\"2026-09-23\",\"customer_id\":\"1\",\"branch_id\":\"191\",\"company_id\":\"89\",\"currency_id\":\"2\",\"sale_officer_id\":null,\"supplier_man_id\":null,\"brand_id\":null,\"sale_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"customer_phone\":null,\"total_bill\":400,\"total_discount_percent\":0,\"total_discount_amount\":0,\"shipping_fees\":0,\"net_amount\":400,\"invoice_type\":\"Cash\",\"due_date\":null,\"remarks\":\"\",\"status\":\"Posted\",\"amount_paid\":400,\"items\":[{\"productId\":\"7909\",\"uomId\":\"9\",\"quantity\":2,\"piece\":2,\"carton\":0,\"dozen\":0,\"salePrice\":200,\"grossAmount\":400,\"discountPercent\":0,\"discountAmount\":0,\"extraDiscountAmount\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":400,\"parentRowId\":null,\"stockAffects\":1,\"invoiceAffects\":1,\"scheme\":\"sale_on_tp\",\"batchNo\":null,\"batchExpiryDate\":null}]}', '104.22.175.142', '2026-09-23 15:45:40'),
('7506', '2', '69', 'Zahid Ghori', 'create', 'Customer', '13050', 'CUST-00001', 'Created customer \"Zahid Ghori\" (CUST-00001)', NULL, '{\"customer_code\":\"CUST-00001\",\"customer_name\":\"Zahid Ghori\",\"primary_phone\":null,\"company_id\":89}', '104.22.175.142', '2026-09-23 16:28:45'),
('7507', '2', '69', 'Zahid Ghori', 'update', 'Customer', '13050', 'CUST-00001', 'Updated customer \"Zahid G/S\" (CUST-00001): Name: Zahid Ghori → Zahid G/S; Shop Name: (empty) → zahid; Address: (empty) → Street 4 13D Gulshan; Primary Phone: (empty) → 03357541254; Credit Limit: 0.00 → 25000', '{\"company_id\":89,\"customer_code\":\"CUST-00001\",\"customer_name\":\"Zahid Ghori\",\"shop_name\":null,\"address\":null,\"primary_phone\":null,\"secondary_phone\":null,\"email\":null,\"credit_limit\":\"0.00\",\"is_blacklisted\":0}', '{\"company_id\":89,\"customer_code\":\"CUST-00001\",\"customer_name\":\"Zahid G\\/S\",\"shop_name\":\"zahid\",\"address\":\"Street 4 13D Gulshan\",\"primary_phone\":\"03357541254\",\"secondary_phone\":null,\"email\":null,\"credit_limit\":25000,\"is_blacklisted\":0}', '104.22.175.142', '2026-09-23 16:31:46'),
('7515', '2', '69', 'Zahid Ghori', 'create', 'Sale Invoice', '18764', 'SAL-0002', 'Created sale invoice \"SAL-0002\" (1 item(s), net 600.00)', NULL, '{\"bill_no\":\"SAL-0002\",\"sale_date\":\"2026-09-23\",\"customer_id\":\"13050\",\"branch_id\":\"191\",\"company_id\":\"89\",\"currency_id\":\"2\",\"sale_officer_id\":\"245\",\"supplier_man_id\":null,\"brand_id\":null,\"sale_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"customer_phone\":\"03357541254\",\"total_bill\":600,\"total_discount_percent\":0,\"total_discount_amount\":0,\"shipping_fees\":0,\"net_amount\":600,\"invoice_type\":\"Credit\",\"due_date\":null,\"remarks\":\"\",\"status\":\"Posted\",\"amount_paid\":0,\"items\":[{\"productId\":\"7900\",\"uomId\":\"9\",\"quantity\":3,\"piece\":3,\"carton\":0,\"dozen\":0,\"salePrice\":200,\"grossAmount\":600,\"discountPercent\":0,\"discountAmount\":0,\"extraDiscountAmount\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":600,\"parentRowId\":null,\"stockAffects\":1,\"invoiceAffects\":1,\"scheme\":\"sale_on_tp\",\"batchNo\":null,\"batchExpiryDate\":null}]}', '104.22.175.142', '2026-09-23 16:41:09'),
('7516', '2', '69', 'Zahid Ghori', 'update', 'Sale Invoice', '18758', 'SAL-0001', 'Updated sale invoice \"SAL-0001\": Sale Date: 2026-09-23 → 2026-09-22', '{\"bill_no\":\"SAL-0001\",\"currency_id\":2,\"sale_date\":\"2026-09-23\",\"customer_id\":1,\"sub_account_id\":null,\"company_id\":89,\"branch_id\":191,\"previous_balance\":\"0.000\",\"sale_officer_id\":null,\"supplier_man_id\":null,\"brand_id\":null,\"sale_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"customer_phone\":null,\"total_bill\":\"400.000\",\"total_discount_percent\":\"0.000\",\"total_discount_amount\":\"0.000\",\"extra_discount_1_percent\":\"0.0000\",\"extra_discount_1_amount\":\"0.000\",\"extra_discount_2_percent\":\"0.0000\",\"extra_discount_2_amount\":\"0.000\",\"net_amount\":\"400.000\",\"remarks\":\"\",\"ship_to\":null,\"status\":\"Posted\",\"project_id\":null,\"container_id\":null}', '{\"bill_no\":\"SAL-0001\",\"currency_id\":\"2\",\"sale_date\":\"2026-09-22\",\"customer_id\":\"1\",\"sub_account_id\":null,\"company_id\":\"89\",\"branch_id\":\"191\",\"previous_balance\":0,\"sale_officer_id\":null,\"supplier_man_id\":null,\"brand_id\":null,\"sale_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"customer_phone\":null,\"total_bill\":400,\"total_discount_percent\":0,\"total_discount_amount\":0,\"extra_discount_1_percent\":0,\"extra_discount_1_amount\":0,\"extra_discount_2_percent\":0,\"extra_discount_2_amount\":0,\"net_amount\":400,\"remarks\":\"\",\"ship_to\":null,\"status\":\"Posted\",\"project_id\":null,\"container_id\":null}', '104.22.175.142', '2026-09-23 16:41:37'),
('7521', '2', '69', 'Zahid Ghori', 'create', 'Receive Voucher', '17469', 'RV-2026-0002', 'Created receive voucher \"RV-2026-0002\" (amount 100.00)', NULL, '{\"voucher_number\":\"RV-2026-0002\",\"voucher_date\":\"2026-09-24\",\"customer_id\":13050,\"company_id\":89,\"bill_no\":\"SAL-0002\",\"amount\":\"100\",\"adjust_amount\":0,\"currency_id\":\"2\",\"payment_method_id\":1,\"bank_account_id\":null,\"cheque_no\":null,\"description\":\"null\"}', '104.22.175.142', '2026-09-23 16:49:15'),
('7522', '2', '69', 'Zahid Ghori', 'create', 'Payment Voucher', '1811', 'PV-2026-0001', 'Created payment voucher \"PV-2026-0001\" (amount 400.00)', NULL, '{\"voucher_number\":\"PV-2026-0001\",\"voucher_date\":\"2026-09-23\",\"payment_type\":\"supplier\",\"entity_id\":462,\"company_id\":\"89\",\"bill_no\":\"PUR-0001\",\"amount\":\"400\",\"currency_id\":\"2\",\"payment_method_id\":\"7\",\"bank_account_id\":null,\"cheque_no\":null,\"description\":null}', '104.22.175.142', '2026-09-23 16:49:39'),
('7523', '2', '69', 'Zahid Ghori', 'create', 'Expense Voucher', '1832', 'EXP-2026-00001', 'Created expense voucher \"EXP-2026-00001\" (1 entr(y/ies), total 50.00)', NULL, '{\"voucher_no\":\"EXP-2026-00001\",\"date\":\"2026-09-23\",\"company_id\":\"89\",\"description\":\"\",\"total_amount\":50,\"total_items\":1,\"project_id\":null,\"vendor_id\":null,\"type\":\"CR\",\"is_cash_flow\":1,\"entries\":[{\"expense_account_id\":\"58\",\"cost_center_id\":null,\"amount\":50,\"payment_method_id\":\"7\",\"bank_account_id\":null,\"cheque_no\":null,\"cheque_date\":null}]}', '104.22.175.142', '2026-09-23 16:50:05'),
('7545', '2', '69', 'Zahid Ghori', 'create', 'Customer', '13053', 'CUST-00002', 'Created customer \"Rustam Bakers\" (CUST-00002)', NULL, '{\"customer_code\":\"CUST-00002\",\"customer_name\":\"Rustam Bakers\",\"primary_phone\":\"03214521452\",\"company_id\":89}', '104.22.175.142', '2026-09-23 19:18:15'),
('7546', '2', '69', 'Zahid Ghori', 'create', 'Customer', '13054', 'CUST-00003', 'Created customer \"Wasim Medical Store\" (CUST-00003)', NULL, '{\"customer_code\":\"CUST-00003\",\"customer_name\":\"Wasim Medical Store\",\"primary_phone\":\"03357854785\",\"company_id\":89}', '104.22.175.142', '2026-09-23 19:40:22'),
('7547', '2', '69', 'Zahid Ghori', 'delete', 'Sale Invoice', '18758', 'SAL-0001', 'Deleted sale invoice \"SAL-0001\"', '{\"bill_no\":\"SAL-0001\",\"sale_date\":\"2026-09-22\",\"customer_id\":1,\"branch_id\":191,\"net_amount\":\"400.000\",\"status\":\"Posted\"}', NULL, '104.22.175.142', '2026-09-23 19:44:17'),
('7550', '2', '69', 'Zahid Ghori', 'create', 'Sale Invoice', '18769', 'SAL-0003', 'Created sale invoice \"SAL-0003\" (3 item(s), net 11,749.00)', NULL, '{\"bill_no\":\"SAL-0003\",\"sale_date\":\"2026-09-23\",\"customer_id\":\"13053\",\"branch_id\":\"191\",\"company_id\":\"89\",\"currency_id\":\"2\",\"sale_officer_id\":\"245\",\"supplier_man_id\":null,\"brand_id\":null,\"sale_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"customer_phone\":\"03214521452\",\"total_bill\":11749,\"total_discount_percent\":0,\"total_discount_amount\":0,\"shipping_fees\":0,\"net_amount\":11749,\"invoice_type\":\"Credit\",\"due_date\":null,\"remarks\":\"\",\"status\":\"Posted\",\"amount_paid\":0,\"items\":[{\"productId\":\"7901\",\"uomId\":\"9\",\"quantity\":2,\"piece\":2,\"carton\":0,\"dozen\":0,\"salePrice\":450,\"grossAmount\":11700,\"discountPercent\":3,\"discountAmount\":351,\"extraDiscountAmount\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":1,\"netAmount\":11349,\"parentRowId\":null,\"stockAffects\":1,\"invoiceAffects\":1,\"scheme\":\"given\",\"batchNo\":null,\"batchExpiryDate\":null},{\"productId\":\"7901\",\"uomId\":\"16\",\"quantity\":1,\"piece\":0,\"carton\":1,\"dozen\":0,\"salePrice\":450,\"grossAmount\":0,\"discountPercent\":0,\"discountAmount\":0,\"extraDiscountAmount\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":0,\"parentRowId\":null,\"stockAffects\":1,\"invoiceAffects\":1,\"scheme\":\"given\",\"batchNo\":null,\"batchExpiryDate\":null},{\"productId\":\"7909\",\"uomId\":\"9\",\"quantity\":2,\"piece\":2,\"carton\":0,\"dozen\":0,\"salePrice\":200,\"grossAmount\":400,\"discountPercent\":0,\"discountAmount\":0,\"extraDiscountAmount\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":400,\"parentRowId\":null,\"stockAffects\":1,\"invoiceAffects\":1,\"scheme\":\"sale_on_tp\",\"batchNo\":null,\"batchExpiryDate\":null}]}', '104.22.175.142', '2026-09-23 19:48:30'),
('7551', '2', '69', 'Zahid Ghori', 'update', 'Product', '7909', 'PROD-000003', 'Updated product \"Sample\" (PROD-000003): Sales Tax Type: (empty) → 34', '{\"code\":\"PROD-000003\",\"name\":\"Sample\",\"product_type\":\"physical\",\"uom_type\":\"unit\",\"company_id\":89,\"item_company_id\":3,\"default_unit_id\":9,\"uom_group_id\":null,\"product_conversion_factor\":null,\"category_id\":null,\"subcategory_id\":null,\"inventory_account_id\":33,\"vendor_id\":null,\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":null,\"barcode\":null,\"purchase_price\":\"100.000\",\"trade_price\":\"200.000\",\"wholesale_price\":\"0.000\",\"mrp\":\"0.000\",\"price_entry_unit_id\":null,\"trade_price_entry_value\":null,\"mrp_entry_value\":null,\"default_discount\":\"0.00\",\"trade_offer_discount\":\"0.00\",\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":null,\"min_stock_level\":\"0.000\",\"max_stock_level\":\"0.000\",\"manufacturing_date\":null,\"expiry_date\":null,\"photo\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '{\"code\":\"PROD-000003\",\"name\":\"Sample\",\"product_type\":\"physical\",\"uom_type\":\"unit\",\"company_id\":\"89\",\"item_company_id\":\"3\",\"default_unit_id\":\"9\",\"uom_group_id\":null,\"product_conversion_factor\":null,\"category_id\":null,\"subcategory_id\":null,\"inventory_account_id\":\"33\",\"vendor_id\":null,\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":null,\"barcode\":null,\"purchase_price\":\"100.000\",\"trade_price\":\"200.000\",\"wholesale_price\":\"0.000\",\"mrp\":\"0.000\",\"price_entry_unit_id\":null,\"trade_price_entry_value\":null,\"mrp_entry_value\":null,\"default_discount\":\"0.00\",\"trade_offer_discount\":0,\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":\"34\",\"min_stock_level\":\"0.000\",\"max_stock_level\":\"0.000\",\"manufacturing_date\":null,\"expiry_date\":null,\"photo\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '104.22.175.142', '2026-09-23 20:09:13'),
('7552', '2', '69', 'Zahid Ghori', 'create', 'Sale Tax Invoice', '18770', 'SAL-0004', 'Created sale tax invoice \"SAL-0004\", net amount 2,010.00', NULL, '{\"bill_no\":\"SAL-0004\",\"customer_id\":\"13053\",\"sale_date\":\"2026-09-23\",\"net_amount\":2010,\"status\":\"Posted\"}', '104.22.175.142', '2026-09-23 20:14:28'),
('7553', '2', '69', 'Zahid Ghori', 'create', 'Attendance', '76', 'SALES-0003 - 2026-09-01', 'Recorded attendance for employee \"SALES-0003\" on 2026-09-01: 09:00 - 17:00 (status: present)', NULL, '{\"employee_id\":\"SALES-0003\",\"attendance_date\":\"2026-09-01\",\"check_in_time\":\"2026-09-01 09:00:00\",\"check_out_time\":\"2026-09-01 17:00:00\",\"status\":\"present\",\"notes\":\"\"}', '172.70.201.144', '2026-09-23 20:28:09'),
('7554', '2', '69', 'Zahid Ghori', 'create', 'Cash Opening', '93', '2026-09-21', 'Created cash opening (as of 2026-09-21): Amount 60,000.00', NULL, '{\"as_of_date\":\"2026-09-21\",\"branch_id\":\"190\",\"company_id\":\"89\",\"opening_amount\":\"60000\",\"currency_id\":\"2\"}', '104.22.175.142', '2026-09-23 20:31:27'),
('7555', '2', '69', 'Zahid Ghori', 'delete', 'Attendance', '76', 'Attendance record deleted', NULL, NULL, NULL, '172.70.201.144', '2026-09-23 20:37:11'),
('7560', '2', '69', 'Zahid Ghori', 'create', 'Bank Account', '145', 'Meezan  - 965321-85', 'Created bank account \"Meezan  - 965321-85\", opening balance 10,000.00', NULL, '{\"bank_name\":\"Meezan \",\"account_number\":\"965321-85\",\"account_type\":\"savings\",\"opening_balance\":10000,\"balance_type\":\"debit\"}', '172.68.249.154', '2026-09-24 04:01:06'),
('7561', '2', '69', 'Zahid Ghori', 'create', 'Bank Account', '146', 'Jazz Cash - 03348545874', 'Created bank account \"Jazz Cash - 03348545874\", opening balance 3,000.00', NULL, '{\"bank_name\":\"Jazz Cash\",\"account_number\":\"03348545874\",\"account_type\":\"mfb_account\",\"opening_balance\":3000,\"balance_type\":\"debit\"}', '172.68.249.154', '2026-09-24 04:03:29'),
('7562', '2', '69', 'Zahid Ghori', 'create', 'Sale Invoice', '18771', 'SAL-0005', 'Created sale invoice \"SAL-0005\" (1 item(s), net 392.00)', NULL, '{\"bill_no\":\"SAL-0005\",\"sale_date\":\"2026-09-24\",\"customer_id\":\"13054\",\"branch_id\":\"191\",\"company_id\":\"89\",\"currency_id\":\"2\",\"sale_officer_id\":\"246\",\"supplier_man_id\":null,\"brand_id\":null,\"sale_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"customer_phone\":\"03357854785\",\"total_bill\":392,\"total_discount_percent\":0,\"total_discount_amount\":0,\"shipping_fees\":0,\"net_amount\":392,\"invoice_type\":\"Credit\",\"due_date\":null,\"remarks\":\"\",\"status\":\"Posted\",\"amount_paid\":0,\"items\":[{\"productId\":\"7900\",\"uomId\":\"9\",\"quantity\":2,\"piece\":2,\"carton\":0,\"dozen\":0,\"salePrice\":200,\"grossAmount\":400,\"discountPercent\":2,\"discountAmount\":8,\"extraDiscountAmount\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":392,\"parentRowId\":null,\"stockAffects\":1,\"invoiceAffects\":1,\"scheme\":\"sale_on_tp\",\"batchNo\":null,\"batchExpiryDate\":null}]}', '172.68.249.154', '2026-09-24 04:15:00'),
('7589', '2', '69', 'Zahid Ghori', 'create', 'Receive Voucher', '17504', 'RV-2026-0003', 'Created receive voucher \"RV-2026-0003\" (amount 300.00)', NULL, '{\"voucher_number\":\"RV-2026-0003\",\"voucher_date\":\"2026-09-24\",\"customer_id\":13053,\"company_id\":89,\"bill_no\":\"SAL-0003\",\"amount\":\"300\",\"adjust_amount\":0,\"currency_id\":\"2\",\"payment_method_id\":\"6\",\"bank_account_id\":145,\"cheque_no\":\"9652188-ABL\",\"description\":\"null\"}', '172.68.249.154', '2026-09-24 04:18:16'),
('7590', '2', '69', 'Zahid Ghori', 'create', 'Project', '36', 'PRJ-0001', 'Created project \"DB Box\" (PRJ-0001)', NULL, '{\"project_code\":\"PRJ-0001\",\"project_name\":\"DB Box\",\"company_id\":1,\"status\":\"active\"}', '172.68.249.154', '2026-09-24 04:23:44'),
('7591', '2', '69', 'Zahid Ghori', 'delete', 'Chart of Accounts', '437', 'Ghori Petty Cash', 'Deleted account \"Ghori Petty Cash\"', '{\"id\":437,\"tenant_id\":2,\"sub_account_id\":74,\"name\":\"Ghori Petty Cash\",\"debit\":\"4000.00\",\"credit\":\"0.00\",\"is_partner_account\":0}', NULL, '172.68.249.154', '2026-09-24 04:24:36'),
('7609', '2', '69', 'Zahid Ghori', 'update', 'Inventory Sync', '2', '2026-09-24', 'Synced inventory ledger as of 2026-09-24: calculated value 59,500.00, previous balance 0.00, adjustment 59,500.00', '{\"previous_balance\":\"0.00\"}', '{\"end_date\":\"2026-09-24\",\"inventory_value\":\"59500.00000000000000000\",\"adjustment\":59500}', '172.68.249.154', '2026-09-24 04:24:59'),
('7610', '2', '69', 'Zahid Ghori', 'update', 'Purchase Invoice', '1966', 'PUR-0002', 'Updated purchase invoice \"PUR-0002\": Net Amount: 29160.160 → 24712', '{\"bill_no\":\"PUR-0002\",\"company_id\":89,\"currency_id\":2,\"purchase_date\":\"2026-09-06\",\"supplier_id\":465,\"branch_id\":191,\"container_id\":null,\"previous_balance\":\"-150000.000\",\"total_bill\":\"25600.000\",\"total_discount_percent\":\"2.000\",\"total_discount_amount\":\"288.000\",\"total_tax_percent\":\"0.000\",\"total_tax_amount\":\"0.000\",\"shipping_fees\":\"600.000\",\"net_amount\":\"29160.160\",\"supplier_invoice_no\":\"1112\",\"purchase_order_id\":null,\"bilty_no\":\"5545\",\"transport_name\":\"Super Mama\",\"remarks\":\"\",\"project_id\":null}', '{\"bill_no\":\"PUR-0002\",\"company_id\":\"89\",\"currency_id\":\"2\",\"purchase_date\":\"2026-09-06\",\"supplier_id\":\"465\",\"branch_id\":\"191\",\"container_id\":null,\"previous_balance\":-150000,\"total_bill\":25600,\"total_discount_percent\":2,\"total_discount_amount\":288,\"total_tax_percent\":0,\"total_tax_amount\":0,\"shipping_fees\":600,\"net_amount\":24712,\"supplier_invoice_no\":\"1112\",\"purchase_order_id\":null,\"bilty_no\":\"5545\",\"transport_name\":\"Super Mama\",\"remarks\":\"\",\"project_id\":null}', '172.68.249.154', '2026-09-24 04:37:55'),
('7611', '2', '69', 'Zahid Ghori', 'update', 'Inventory Sync', '2', '2026-09-24', 'Synced inventory ledger as of 2026-09-24: calculated value 59,500.00, previous balance 0.00, adjustment 59,500.00', '{\"previous_balance\":\"0.00\"}', '{\"end_date\":\"2026-09-24\",\"inventory_value\":\"59500.00000000000000000\",\"adjustment\":59500}', '172.68.249.154', '2026-09-24 04:38:41'),
('7647', '2', '69', 'Zahid Ghori', 'update', 'Purchase Invoice', '1966', 'PUR-0002', 'Updated purchase invoice \"PUR-0002\" - no tracked field changes', '{\"bill_no\":\"PUR-0002\",\"company_id\":89,\"currency_id\":2,\"purchase_date\":\"2026-09-06\",\"supplier_id\":465,\"branch_id\":191,\"container_id\":null,\"previous_balance\":\"-150000.000\",\"total_bill\":\"25600.000\",\"total_discount_percent\":\"2.000\",\"total_discount_amount\":\"288.000\",\"total_tax_percent\":\"0.000\",\"total_tax_amount\":\"0.000\",\"shipping_fees\":\"600.000\",\"net_amount\":\"24712.000\",\"supplier_invoice_no\":\"1112\",\"purchase_order_id\":null,\"bilty_no\":\"5545\",\"transport_name\":\"Super Mama\",\"remarks\":\"\",\"project_id\":null}', '{\"bill_no\":\"PUR-0002\",\"company_id\":\"89\",\"currency_id\":\"2\",\"purchase_date\":\"2026-09-06\",\"supplier_id\":\"465\",\"branch_id\":\"191\",\"container_id\":null,\"previous_balance\":-150000,\"total_bill\":25600,\"total_discount_percent\":2,\"total_discount_amount\":288,\"total_tax_percent\":0,\"total_tax_amount\":0,\"shipping_fees\":600,\"net_amount\":24712,\"supplier_invoice_no\":\"1112\",\"purchase_order_id\":null,\"bilty_no\":\"5545\",\"transport_name\":\"Super Mama\",\"remarks\":\"\",\"project_id\":null}', '172.68.249.154', '2026-09-24 04:40:44'),
('7649', '2', '69', 'Zahid Ghori', 'delete', 'Purchase Invoice', '1966', 'PUR-0002', 'Deleted purchase invoice \"PUR-0002\"', '{\"bill_no\":\"PUR-0002\",\"purchase_date\":\"2026-09-06\",\"supplier_id\":465,\"branch_id\":191,\"net_amount\":\"24712.000\"}', NULL, '172.68.249.154', '2026-09-24 04:42:09'),
('7650', '2', '69', 'Zahid Ghori', 'create', 'Purchase Invoice', '1969', 'PUR-0002', 'Created purchase invoice \"PUR-0002\" (2 item(s), net 35,890.00)', NULL, '{\"bill_no\":\"PUR-0002\",\"purchase_date\":\"2026-09-03\",\"supplier_id\":\"461\",\"branch_id\":\"191\",\"company_id\":\"89\",\"currency_id\":\"2\",\"container_id\":null,\"total_bill\":36390,\"total_discount_percent\":0,\"total_discount_amount\":0,\"total_tax_amount\":0,\"shipping_fees\":500,\"net_amount\":35890,\"supplier_invoice_no\":\"69655\",\"purchase_order_id\":null,\"bilty_no\":\"2512\",\"transport_name\":null,\"remarks\":\"\",\"items\":[{\"productId\":\"7901\",\"size\":\"\",\"batchNo\":null,\"batchExpiryDate\":\"2026-09-24\",\"unitEntries\":[{\"uomId\":\"16\",\"quantity\":5}],\"purchasePrice\":300,\"grossAmount\":36000,\"discountPercent\":3,\"discountAmount\":1080,\"extraDiscountPercent\":0,\"tradeOfferPercent\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":34920},{\"productId\":\"7900\",\"size\":\"\",\"batchNo\":null,\"batchExpiryDate\":\"2026-09-24\",\"unitEntries\":[{\"uomId\":\"9\",\"quantity\":15}],\"purchasePrice\":100,\"grossAmount\":1500,\"discountPercent\":2,\"discountAmount\":30,\"extraDiscountPercent\":0,\"tradeOfferPercent\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":1470}]}', '172.68.249.154', '2026-09-24 04:44:40'),
('7652', '2', '69', 'Zahid Ghori', 'update', 'Inventory Sync', '2', '2026-09-24', 'Synced inventory ledger as of 2026-09-24: calculated value 71,400.00, previous balance 0.00, adjustment 71,400.00', '{\"previous_balance\":\"0.00\"}', '{\"end_date\":\"2026-09-24\",\"inventory_value\":\"71400.00000000000000000\",\"adjustment\":71400}', '172.68.249.154', '2026-09-24 04:45:20'),
('7654', '2', '69', 'Zahid Ghori', 'delete', 'Chart of Accounts', '460', 'Tea', 'Deleted account \"Tea\"', '{\"id\":460,\"tenant_id\":2,\"sub_account_id\":94,\"name\":\"Tea\",\"debit\":\"0.00\",\"credit\":\"0.00\",\"is_partner_account\":0}', NULL, '172.68.249.154', '2026-09-24 04:48:13'),
('7675', '2', '69', 'Zahid Ghori', 'create', 'Expense Voucher', '1835', 'EXP-2026-00002', 'Created expense voucher \"EXP-2026-00002\" (2 entr(y/ies), total 600.00)', NULL, '{\"voucher_no\":\"EXP-2026-00002\",\"date\":\"2026-09-22\",\"company_id\":\"89\",\"description\":\"\",\"total_amount\":600,\"total_items\":2,\"project_id\":null,\"vendor_id\":null,\"type\":\"CR\",\"is_cash_flow\":1,\"entries\":[{\"expense_account_id\":\"461\",\"cost_center_id\":\"227\",\"amount\":200,\"payment_method_id\":\"7\",\"bank_account_id\":null,\"cheque_no\":null,\"cheque_date\":null},{\"expense_account_id\":\"461\",\"cost_center_id\":\"228\",\"amount\":400,\"payment_method_id\":\"7\",\"bank_account_id\":null,\"cheque_no\":null,\"cheque_date\":null}]}', '172.68.249.154', '2026-09-24 04:52:13'),
('7676', '2', '69', 'Zahid Ghori', 'delete', 'Expense Voucher', '1832', 'EXP-2026-00001', 'Deleted expense voucher \"EXP-2026-00001\"', '{\"voucher_no\":\"EXP-2026-00001\",\"date\":\"2026-09-23\",\"description\":\"\",\"total_amount\":\"50.00\"}', NULL, '172.68.249.154', '2026-09-24 04:52:31'),
('7678', '2', '69', 'Zahid Ghori', 'create', 'Expense Voucher', '1836', 'EXP-2026-00003', 'Created expense voucher \"EXP-2026-00003\" (1 entr(y/ies), total 6,000.00)', NULL, '{\"voucher_no\":\"EXP-2026-00003\",\"date\":\"2026-09-24\",\"company_id\":\"89\",\"description\":\"\",\"total_amount\":6000,\"total_items\":1,\"project_id\":null,\"vendor_id\":null,\"type\":\"CR\",\"is_cash_flow\":1,\"entries\":[{\"expense_account_id\":\"59\",\"cost_center_id\":\"229\",\"amount\":6000,\"payment_method_id\":\"7\",\"bank_account_id\":null,\"cheque_no\":null,\"cheque_date\":null}]}', '172.68.249.154', '2026-09-24 04:55:56'),
('7679', '2', '69', 'Zahid Ghori', 'create', 'Expense Voucher', '1837', 'EXP-2026-00004', 'Created expense voucher \"EXP-2026-00004\" (1 entr(y/ies), total 300.00)', NULL, '{\"voucher_no\":\"EXP-2026-00004\",\"date\":\"2026-09-24\",\"company_id\":\"89\",\"description\":\"\",\"total_amount\":300,\"total_items\":1,\"project_id\":null,\"vendor_id\":null,\"type\":\"CR\",\"is_cash_flow\":1,\"entries\":[{\"expense_account_id\":\"461\",\"cost_center_id\":\"227\",\"amount\":300,\"payment_method_id\":\"7\",\"bank_account_id\":null,\"cheque_no\":null,\"cheque_date\":null}]}', '172.68.249.154', '2026-09-24 04:56:19'),
('7680', '2', '69', 'Zahid Ghori', 'create', 'Sale Return', '1324', 'SR-0001', 'Created sale return \"SR-0001\", net amount 436.50', NULL, '{\"bill_no\":\"SR-0001\",\"customer_id\":13053,\"sale_date\":\"2026-09-24\",\"net_amount\":436.5,\"status\":\"Posted\"}', '172.68.249.154', '2026-09-24 05:00:37'),
('7822', '2', '69', 'Zahid Ghori', 'create', 'Product', '7912', 'PROD-000004', 'Created product \"A\" (PROD-000004)', NULL, '{\"code\":\"PROD-000004\",\"name\":\"A\",\"product_type\":\"physical\",\"uom_type\":\"unit\",\"company_id\":\"89\",\"default_unit_id\":\"12\",\"uom_group_id\":null,\"product_conversion_factor\":null,\"category_id\":null,\"subcategory_id\":null,\"inventory_account_id\":\"115\",\"vendor_id\":null,\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":null,\"barcode\":null,\"purchase_price\":\"60\",\"trade_price\":0,\"wholesale_price\":0,\"mrp\":0,\"default_discount\":0,\"trade_offer_discount\":0,\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":null,\"min_stock_level\":0,\"max_stock_level\":0,\"manufacturing_date\":null,\"expiry_date\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '104.22.175.142', '2026-09-24 08:24:46'),
('7823', '2', '69', 'Zahid Ghori', 'create', 'Product', '7913', 'PROD-000005', 'Created product \"B\" (PROD-000005)', NULL, '{\"code\":\"PROD-000005\",\"name\":\"B\",\"product_type\":\"physical\",\"uom_type\":\"unit\",\"company_id\":\"89\",\"default_unit_id\":\"9\",\"uom_group_id\":null,\"product_conversion_factor\":null,\"category_id\":null,\"subcategory_id\":null,\"inventory_account_id\":\"33\",\"vendor_id\":null,\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":null,\"barcode\":null,\"purchase_price\":\"90\",\"trade_price\":0,\"wholesale_price\":0,\"mrp\":0,\"default_discount\":0,\"trade_offer_discount\":0,\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":null,\"min_stock_level\":0,\"max_stock_level\":0,\"manufacturing_date\":null,\"expiry_date\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '104.22.175.142', '2026-09-24 08:25:36'),
('7824', '2', '69', 'Zahid Ghori', 'create', 'Product', '7914', 'PROD-000006', 'Created product \"C\" (PROD-000006)', NULL, '{\"code\":\"PROD-000006\",\"name\":\"C\",\"product_type\":\"physical\",\"uom_type\":\"unit\",\"company_id\":\"89\",\"default_unit_id\":\"9\",\"uom_group_id\":null,\"product_conversion_factor\":null,\"category_id\":null,\"subcategory_id\":null,\"inventory_account_id\":\"117\",\"vendor_id\":null,\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":null,\"barcode\":null,\"purchase_price\":0,\"trade_price\":\"220\",\"wholesale_price\":\"200\",\"mrp\":\"300\",\"default_discount\":0,\"trade_offer_discount\":0,\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":null,\"min_stock_level\":0,\"max_stock_level\":0,\"manufacturing_date\":null,\"expiry_date\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '104.22.175.142', '2026-09-24 08:26:35'),
('7825', '2', '69', 'Zahid Ghori', 'update', 'Product', '7913', 'PROD-000005', 'Updated product \"B\" (PROD-000005): Inventory Account: 33 → 115', '{\"code\":\"PROD-000005\",\"name\":\"B\",\"product_type\":\"physical\",\"uom_type\":\"unit\",\"company_id\":89,\"item_company_id\":null,\"default_unit_id\":9,\"uom_group_id\":null,\"product_conversion_factor\":null,\"category_id\":null,\"subcategory_id\":null,\"inventory_account_id\":33,\"vendor_id\":null,\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":null,\"barcode\":null,\"purchase_price\":\"90.000\",\"trade_price\":\"0.000\",\"wholesale_price\":\"0.000\",\"mrp\":\"0.000\",\"price_entry_unit_id\":null,\"trade_price_entry_value\":null,\"mrp_entry_value\":null,\"default_discount\":\"0.00\",\"trade_offer_discount\":\"0.00\",\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":null,\"min_stock_level\":\"0.000\",\"max_stock_level\":\"0.000\",\"manufacturing_date\":null,\"expiry_date\":null,\"photo\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '{\"code\":\"PROD-000005\",\"name\":\"B\",\"product_type\":\"physical\",\"uom_type\":\"unit\",\"company_id\":\"89\",\"item_company_id\":null,\"default_unit_id\":\"9\",\"uom_group_id\":null,\"product_conversion_factor\":null,\"category_id\":null,\"subcategory_id\":null,\"inventory_account_id\":\"115\",\"vendor_id\":null,\"parent_product_id\":null,\"description\":\"\",\"weight_value\":null,\"weight_unit\":null,\"qr_code\":null,\"barcode\":null,\"purchase_price\":\"90.000\",\"trade_price\":\"0.000\",\"wholesale_price\":\"0.000\",\"mrp\":\"0.000\",\"price_entry_unit_id\":null,\"trade_price_entry_value\":null,\"mrp_entry_value\":null,\"default_discount\":\"0.00\",\"trade_offer_discount\":0,\"default_foc\":0,\"carton_conversion\":0,\"tax_regime_id\":null,\"min_stock_level\":\"0.000\",\"max_stock_level\":\"0.000\",\"manufacturing_date\":null,\"expiry_date\":null,\"photo\":null,\"is_active\":1,\"stock_affects\":1,\"invoice_affects\":1}', '104.22.175.142', '2026-09-24 08:28:20'),
('7826', '2', '69', 'Zahid Ghori', 'create', 'Bill of Materials', '314', 'BOM-0001', 'Created BOM \"BOM-0001\" (v1.0) with 2 material(s)', NULL, '{\"bom_code\":\"BOM-0001\",\"finished_good_id\":7914,\"version\":\"1.0\",\"is_active\":1,\"bom_base_qty\":1}', '104.22.175.142', '2026-09-24 08:28:56'),
('7828', '2', '69', 'Zahid Ghori', 'create', 'Machine', '11', 'MCH-0001', 'Created machine \"Machine 1\" (MCH-0001)', NULL, '{\"code\":\"MCH-0001\",\"machine_name\":\"Machine 1\",\"branch_id\":\"191\",\"capacity_speed\":\"\",\"is_active\":1}', '104.22.175.142', '2026-09-24 08:30:20'),
('7829', '2', '69', 'Zahid Ghori', 'create', 'Production Order', '3812', 'PO-0001', 'Created production order \"PO-0001\", qty 10', NULL, '{\"order_no\":\"PO-0001\",\"product_id\":7914,\"bom_id\":314,\"branch_id\":\"191\",\"machine_id\":\"11\",\"order_qty\":\"10\",\"status\":\"Planned\"}', '104.22.175.142', '2026-09-24 08:31:56'),
('7830', '2', '69', 'Zahid Ghori', 'create', 'WIP Management', '3688', 'WIP-0001', 'Issued 2 material(s) to WIP \"WIP-0001\", total cost 3,600.00', NULL, '{\"wip_number\":\"WIP-0001\",\"production_order_id\":\"3812\",\"branch_id\":191,\"machine_id\":11,\"total_cost\":3600}', '104.22.175.142', '2026-09-24 08:32:36'),
('7831', '2', '69', 'Zahid Ghori', 'create', 'WIP Management', '3689', 'WIP-0002', 'Issued 2 material(s) to WIP \"WIP-0002\", total cost 3,600.00', NULL, '{\"wip_number\":\"WIP-0002\",\"production_order_id\":\"3812\",\"branch_id\":191,\"machine_id\":11,\"total_cost\":3600}', '104.22.175.142', '2026-09-24 08:32:36'),
('7832', '2', '69', 'Zahid Ghori', 'delete', 'WIP Management', '3689', 'WIP-0002', 'Deleted WIP \"WIP-0002\", total cost 3,600.00', '{\"id\":3689,\"tenant_id\":2,\"production_order_id\":3812,\"branch_id\":191,\"machine_id\":11,\"issue_date\":\"2026-09-24\",\"total_items\":2,\"total_cost\":\"3600.00\",\"created_by\":69,\"created_at\":\"2026-09-24 08:32:36\",\"wip_number\":\"WIP-0002\"}', NULL, '104.22.175.142', '2026-09-24 08:33:02'),
('7835', '2', '69', 'Zahid Ghori', 'create', 'Production Expense', '1806', 'Order #3812', 'Created production expense for order #3812 (direct), total 1,220.00', NULL, '{\"production_order_id\":\"3812\",\"entry_date\":\"2026-09-24\",\"total_amount\":1220,\"allocation_method\":\"direct\"}', '104.22.175.142', '2026-09-24 08:35:11'),
('7836', '2', '69', 'Zahid Ghori', 'create', 'Production Completion', '3631', 'PC-0001', 'Created production completion \"PC-0001\" for order #3812: 1 product(s), qty 10, cost 4,820.00', NULL, '{\"completion_no\":\"PC-0001\",\"production_order_id\":\"3812\",\"branch_id\":191,\"complete_date\":\"2026-09-24\",\"total_quantity\":10,\"total_cost\":4820}', '104.22.175.142', '2026-09-24 08:35:29'),
('7845', '2', '69', 'Zahid Ghori', 'create', 'Purchase Invoice', '1977', 'PUR-0003', 'Created purchase invoice \"PUR-0003\" (1 item(s), net 1,180.00)', NULL, '{\"bill_no\":\"PUR-0003\",\"purchase_date\":\"2026-09-24\",\"supplier_id\":\"465\",\"branch_id\":\"191\",\"company_id\":\"89\",\"currency_id\":\"2\",\"container_id\":null,\"total_bill\":1000,\"total_discount_percent\":0,\"total_discount_amount\":0,\"total_tax_amount\":0,\"shipping_fees\":0,\"net_amount\":1180,\"supplier_invoice_no\":null,\"purchase_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"remarks\":\"\",\"items\":[{\"productId\":\"7909\",\"size\":\"\",\"batchNo\":null,\"batchExpiryDate\":\"2026-09-24\",\"unitEntries\":[{\"uomId\":\"9\",\"quantity\":10}],\"purchasePrice\":100,\"grossAmount\":1000,\"discountPercent\":0,\"discountAmount\":0,\"extraDiscountPercent\":0,\"tradeOfferPercent\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":1000}]}', '104.22.175.142', '2026-09-24 09:16:00'),
('7846', '2', '69', 'Zahid Ghori', 'update', 'Inventory Sync', '2', '2026-09-24', 'Synced inventory ledger as of 2026-09-24: calculated value 95,870.00, previous balance 0.00, adjustment 95,870.00', '{\"previous_balance\":\"0.00\"}', '{\"end_date\":\"2026-09-24\",\"inventory_value\":\"95870.00000000000000000\",\"adjustment\":95870}', '104.22.175.142', '2026-09-24 09:16:08'),
('7849', '2', '69', 'Zahid Ghori', 'update', 'Purchase Invoice', '1977', 'PUR-0003', 'Updated purchase invoice \"PUR-0003\": Net Amount: 1180.000 → 1000', '{\"bill_no\":\"PUR-0003\",\"company_id\":89,\"currency_id\":2,\"purchase_date\":\"2026-09-24\",\"supplier_id\":465,\"branch_id\":191,\"container_id\":null,\"previous_balance\":\"-150000.000\",\"total_bill\":\"1000.000\",\"total_discount_percent\":\"0.000\",\"total_discount_amount\":\"0.000\",\"total_tax_percent\":\"0.000\",\"total_tax_amount\":\"0.000\",\"shipping_fees\":\"0.000\",\"net_amount\":\"1180.000\",\"supplier_invoice_no\":null,\"purchase_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"remarks\":\"\",\"project_id\":null}', '{\"bill_no\":\"PUR-0003\",\"company_id\":\"89\",\"currency_id\":\"2\",\"purchase_date\":\"2026-09-24\",\"supplier_id\":\"465\",\"branch_id\":\"191\",\"container_id\":null,\"previous_balance\":-150000,\"total_bill\":1000,\"total_discount_percent\":0,\"total_discount_amount\":0,\"total_tax_percent\":0,\"total_tax_amount\":0,\"shipping_fees\":0,\"net_amount\":1000,\"supplier_invoice_no\":null,\"purchase_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"remarks\":\"\",\"project_id\":null}', '104.22.175.142', '2026-09-24 09:18:48'),
('7850', '2', '69', 'Zahid Ghori', 'create', 'Customer', '13061', 'CUST-00004', 'Created customer \"aaa\" (CUST-00004)', NULL, '{\"customer_code\":\"CUST-00004\",\"customer_name\":\"aaa\",\"primary_phone\":null,\"company_id\":89}', '104.22.175.142', '2026-09-24 09:20:39'),
('7851', '2', '69', 'Zahid Ghori', 'delete', 'Customer', '13061', 'CUST-00004', 'Deleted customer \"aaa\" (CUST-00004)', '{\"id\":13061,\"tenant_id\":2,\"company_id\":89,\"customer_code\":\"CUST-00004\",\"customer_name\":\"aaa\",\"shop_name\":null,\"address\":null,\"po_box_no\":null,\"primary_phone\":null,\"secondary_phone\":null,\"identity_card_no\":null,\"license_no\":null,\"email\":null,\"opening_debit_amount\":\"0.00\",\"opening_credit_amount\":\"0.00\",\"current_balance\":\"0.00\",\"is_blacklisted\":0,\"is_out_station\":0,\"is_both\":0,\"linked_supplier_id\":null,\"status\":\"ACTIVE\",\"created_at\":\"2026-09-24 09:20:39\",\"updated_at\":\"2026-09-24 09:20:39\",\"created_by\":69,\"updated_by\":69,\"country_id\":75,\"region_id\":87,\"city_id\":232,\"city_zone_id\":140,\"area_id\":143,\"is_sales_tax_registered\":0,\"strn\":null,\"is_filer\":0,\"ntn\":null,\"advance_income_tax_percentage\":\"0.00\",\"default_discount_percentage\":\"0.00\",\"credit_limit\":\"0.00\",\"credit_period_limit_days\":0,\"is_wholesaler\":0,\"associated_sales_officer_id\":null,\"customer_type_id\":null,\"customer_category_id\":null,\"supplier_man_id\":null,\"project_id\":null,\"brand_id\":null}', NULL, '104.22.175.142', '2026-09-24 09:21:30'),
('7852', '2', '69', 'Zahid Ghori', 'create', 'Receive Voucher', '17621', 'RV-2026-0004', 'Created receive voucher \"RV-2026-0004\" (amount 700.00)', NULL, '{\"voucher_number\":\"RV-2026-0004\",\"voucher_date\":\"2026-09-24\",\"customer_id\":13054,\"company_id\":89,\"bill_no\":\"SAL-0005\",\"amount\":\"700\",\"adjust_amount\":0,\"currency_id\":\"2\",\"payment_method_id\":1,\"bank_account_id\":null,\"cheque_no\":null,\"description\":\"null\"}', '104.22.175.142', '2026-09-24 09:23:13'),
('7877', '2', '69', 'Zahid Ghori', 'update', 'Receive Voucher', '17621', 'RV-2026-0004', 'Updated receive voucher \"RV-2026-0004\" - no tracked field changes', '{\"voucher_number\":\"RV-2026-0004\",\"customer_id\":13054,\"company_id\":89,\"voucher_date\":\"2026-09-24\",\"bill_no\":\"SAL-0005\",\"dsr_no\":null,\"amount\":\"700.00\",\"adjust_amount\":\"0.00\",\"currency_id\":2,\"payment_method_id\":1,\"bank_account_id\":null,\"cheque_no\":null,\"description\":\"null\"}', '{\"voucher_number\":\"RV-2026-0004\",\"customer_id\":13054,\"company_id\":\"89\",\"voucher_date\":\"2026-09-24\",\"bill_no\":\"SAL-0005\",\"dsr_no\":null,\"amount\":700,\"adjust_amount\":0,\"currency_id\":\"2\",\"payment_method_id\":1,\"bank_account_id\":null,\"cheque_no\":null,\"description\":\"null\"}', '172.70.201.144', '2026-09-24 11:35:11'),
('7878', '2', '69', 'Zahid Ghori', 'update', 'Receive Voucher', '17504', 'RV-2026-0003', 'Updated receive voucher \"RV-2026-0003\" - no tracked field changes', '{\"voucher_number\":\"RV-2026-0003\",\"customer_id\":13053,\"company_id\":89,\"voucher_date\":\"2026-09-24\",\"bill_no\":\"SAL-0003\",\"dsr_no\":null,\"amount\":\"300.00\",\"adjust_amount\":\"0.00\",\"currency_id\":2,\"payment_method_id\":6,\"bank_account_id\":145,\"cheque_no\":\"9652188-ABL\",\"description\":\"null\"}', '{\"voucher_number\":\"RV-2026-0003\",\"customer_id\":13053,\"company_id\":\"89\",\"voucher_date\":\"2026-09-24\",\"bill_no\":\"SAL-0003\",\"dsr_no\":null,\"amount\":300,\"adjust_amount\":0,\"currency_id\":\"2\",\"payment_method_id\":\"6\",\"bank_account_id\":145,\"cheque_no\":\"9652188-ABL\",\"description\":\"null\"}', '172.70.201.144', '2026-09-24 11:35:21'),
('7882', '2', '69', 'Zahid Ghori', 'delete', 'Purchase Invoice', '1977', 'PUR-0003', 'Deleted purchase invoice \"PUR-0003\"', '{\"bill_no\":\"PUR-0003\",\"purchase_date\":\"2026-09-24\",\"supplier_id\":465,\"branch_id\":191,\"net_amount\":\"1000.000\"}', NULL, '104.22.175.142', '2026-09-24 11:41:23'),
('7883', '2', '69', 'Zahid Ghori', 'create', 'Purchase Invoice', '1985', 'PUR-0003', 'Created purchase invoice \"PUR-0003\" (1 item(s), net 1,062.00)', NULL, '{\"bill_no\":\"PUR-0003\",\"purchase_date\":\"2026-09-24\",\"supplier_id\":\"465\",\"branch_id\":\"191\",\"company_id\":\"89\",\"currency_id\":\"2\",\"container_id\":null,\"total_bill\":900,\"total_discount_percent\":0,\"total_discount_amount\":0,\"total_tax_amount\":0,\"shipping_fees\":0,\"net_amount\":1062,\"supplier_invoice_no\":null,\"purchase_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"remarks\":\"\",\"items\":[{\"productId\":\"7912\",\"size\":\"\",\"batchNo\":null,\"batchExpiryDate\":\"2026-09-24\",\"unitEntries\":[{\"uomId\":\"12\",\"quantity\":15}],\"purchasePrice\":60,\"grossAmount\":900,\"discountPercent\":0,\"discountAmount\":0,\"extraDiscountPercent\":0,\"tradeOfferPercent\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":900}]}', '104.22.175.142', '2026-09-24 11:41:58'),
('7894', '2', '69', 'Zahid Ghori', 'create', 'Sale Invoice', '18877', 'SAL-0006', 'Created sale invoice \"SAL-0006\" (2 item(s), net 790.00)', NULL, '{\"bill_no\":\"SAL-0006\",\"sale_date\":\"2026-09-24\",\"customer_id\":\"13050\",\"branch_id\":\"191\",\"company_id\":\"89\",\"currency_id\":\"2\",\"sale_officer_id\":\"245\",\"supplier_man_id\":null,\"brand_id\":null,\"sale_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"customer_phone\":\"03357541254\",\"total_bill\":790,\"total_discount_percent\":0,\"total_discount_amount\":0,\"shipping_fees\":0,\"net_amount\":790,\"invoice_type\":\"Credit\",\"due_date\":null,\"remarks\":\"\",\"status\":\"Posted\",\"amount_paid\":0,\"items\":[{\"productId\":\"7900\",\"uomId\":\"9\",\"quantity\":3,\"piece\":3,\"carton\":0,\"dozen\":0,\"salePrice\":200,\"grossAmount\":600,\"discountPercent\":1,\"discountAmount\":6,\"extraDiscountAmount\":0,\"tradeOfferAmount\":0,\"taxPercent\":0,\"taxAmount\":0,\"focQty\":0,\"netAmount\":594,\"parentRowId\":null,\"stockAffects\":1,\"invoiceAffects\":1,\"scheme\":\"sale_on_tp\",\"batchNo\":null,\"batchExpiryDate\":null},{\"productId\":\"7909\",\"uomId\":\"9\",\"quantity\":1,\"piece\":1,\"carton\":0,\"dozen\":0,\"salePrice\":200,\"grossAmount\":200,\"discountPercent\":2,\"discountAmount\":4,\"extraDiscountAmount\":0,\"tradeOfferAmount\":0,\"taxPercent\":2.5,\"taxAmount\":5,\"focQty\":0,\"netAmount\":196,\"parentRowId\":null,\"stockAffects\":1,\"invoiceAffects\":1,\"scheme\":\"sale_on_tp\",\"batchNo\":null,\"batchExpiryDate\":null}]}', '104.22.175.142', '2026-09-24 11:55:27'),
('8013', '2', '69', 'Zahid Ghori', 'create', 'Sale Invoice', '18892', 'SAL-0007', 'Created sale invoice \"SAL-0007\" (1 item(s), net 389.94)', NULL, '{\"bill_no\":\"SAL-0007\",\"sale_date\":\"2026-09-24\",\"customer_id\":\"13053\",\"branch_id\":\"191\",\"company_id\":\"89\",\"currency_id\":\"2\",\"sale_officer_id\":\"245\",\"supplier_man_id\":null,\"brand_id\":null,\"sale_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"customer_phone\":null,\"total_bill\":389.94,\"total_discount_percent\":0,\"total_discount_amount\":0,\"shipping_fees\":0,\"net_amount\":389.94,\"invoice_type\":\"Credit\",\"due_date\":null,\"remarks\":\"\",\"status\":\"Posted\",\"amount_paid\":0,\"items\":[{\"productId\":\"7909\",\"uomId\":\"9\",\"quantity\":2,\"piece\":2,\"carton\":0,\"dozen\":0,\"salePrice\":200,\"grossAmount\":400,\"discountPercent\":3,\"discountAmount\":12,\"extraDiscountAmount\":0,\"tradeOfferAmount\":0,\"taxPercent\":0.5,\"taxAmount\":1.94,\"focQty\":0,\"netAmount\":389.94,\"parentRowId\":null,\"stockAffects\":1,\"invoiceAffects\":1,\"scheme\":\"sale_on_tp\",\"batchNo\":null,\"batchExpiryDate\":null}]}', '104.22.175.142', '2026-09-24 13:05:22'),
('8040', '2', '69', 'Zahid Ghori', 'update', 'Inventory Sync', '2', '2026-09-24', 'Synced inventory ledger as of 2026-09-24: calculated value 95,170.00, previous balance 0.00, adjustment 95,170.00', '{\"previous_balance\":\"0.00\"}', '{\"end_date\":\"2026-09-24\",\"inventory_value\":\"95170.00000000000000000\",\"adjustment\":95170}', '104.22.175.142', '2026-09-24 13:22:36'),
('8046', '2', '69', 'Zahid Ghori', 'update', 'Purchase Invoice', '1985', 'PUR-0003', 'Updated purchase invoice \"PUR-0003\": Net Amount: 1062.000 → 900', '{\"bill_no\":\"PUR-0003\",\"company_id\":89,\"currency_id\":2,\"purchase_date\":\"2026-09-24\",\"supplier_id\":465,\"branch_id\":191,\"container_id\":null,\"previous_balance\":\"-150000.000\",\"total_bill\":\"900.000\",\"total_discount_percent\":\"0.000\",\"total_discount_amount\":\"0.000\",\"total_tax_percent\":\"0.000\",\"total_tax_amount\":\"0.000\",\"shipping_fees\":\"0.000\",\"net_amount\":\"1062.000\",\"supplier_invoice_no\":null,\"purchase_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"remarks\":\"\",\"project_id\":null}', '{\"bill_no\":\"PUR-0003\",\"company_id\":\"89\",\"currency_id\":\"2\",\"purchase_date\":\"2026-09-24\",\"supplier_id\":\"465\",\"branch_id\":\"191\",\"container_id\":null,\"previous_balance\":-150000,\"total_bill\":900,\"total_discount_percent\":0,\"total_discount_amount\":0,\"total_tax_percent\":0,\"total_tax_amount\":0,\"shipping_fees\":0,\"net_amount\":900,\"supplier_invoice_no\":null,\"purchase_order_id\":null,\"bilty_no\":null,\"transport_name\":null,\"remarks\":\"\",\"project_id\":null}', '104.22.175.142', '2026-09-24 13:28:57');

-- Table: areas
INSERT INTO `areas` (`id`, `tenant_id`, `city_zone_id`, `area_name`) VALUES
('142', '2', '140', '13D'),
('143', '2', '140', 'Disco Bakery'),
('144', '2', '141', 'Munawar Chowrangi');

-- Table: bank_accounts
INSERT INTO `bank_accounts` (`id`, `tenant_id`, `company_id`, `bank_name`, `is_mfb`, `mfb_name`, `account_number`, `account_type`, `currency_id`, `branch_name`, `branch_code`, `branch_city`, `branch_state`, `branch_address`, `account_title`, `iban`, `swift_code`, `contact_person`, `contact_number`, `email`, `bank_logo_path`, `notes`, `balance_type`, `opening_balance`, `debit_amount`, `credit_amount`, `as_of_date`, `created_at`, `updated_at`, `is_active`, `account_id`, `currency`) VALUES
('145', '2', NULL, 'Meezan ', '0', '', '965321-85', 'savings', '80', 'Saadi Town', '004', 'Karachi', 'Sindh', 'Saadi Town 4 Karachi', 'Asif Ali', '', '', 'Zahid Ghori', '03353789981', 'innova.tech213@gmail.com', NULL, '', 'debit', '10000.00', '0.00', '0.00', '2026-09-24', '2026-09-24 04:01:06', '2026-09-24 04:01:06', '1', '458', 'PKR'),
('146', '2', NULL, 'Jazz Cash', '1', 'Jazz Cash', '03348545874', 'mfb_account', '80', 'Jazz Cash', '', 'Karachi', 'Sindh', '', 'zahid', '', '', 'Zahid Ghori', '03353789981', 'innova.tech213@gmail.com', NULL, '', 'debit', '3000.00', '0.00', '0.00', '2026-09-24', '2026-09-24 04:03:29', '2026-09-24 04:03:29', '1', '459', 'PKR');

-- Table: bill_of_materials
INSERT INTO `bill_of_materials` (`id`, `tenant_id`, `bom_code`, `finished_good_id`, `version`, `bom_type`, `is_active`, `bom_base_qty`, `batch_locked`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
('314', '2', 'BOM-0001', '7914', '1.0', 'BOM 1', '1', '1.00', '0', '', '69', '2026-09-24 08:28:56', NULL);

-- Table: bom_materials
INSERT INTO `bom_materials` (`id`, `tenant_id`, `bom_id`, `raw_material_id`, `quantity`, `unit_id`) VALUES
('3007', '2', '314', '7912', '10.00', '12'),
('3008', '2', '314', '7913', '4.00', '9');

-- Table: branches
INSERT INTO `branches` (`id`, `tenant_id`, `company_id`, `parent_branch_id`, `branch_code`, `branch_name`, `branch_type`, `address`, `country`, `state`, `city`, `zipcode`, `phone`, `email`, `manager_id`, `is_active`, `allows_sales`, `allows_inventory`, `is_default`, `created_at`, `updated_at`) VALUES
('190', '2', '89', NULL, 'HE-001', 'Main Branch', 'head_office', '', '', '', '', '', '', '', NULL, '1', '1', '1', '0', '2026-09-22 20:01:44', NULL),
('191', '2', '89', '190', 'WA-001', 'Ware House No 1', 'warehouse', '', '', '', '', '', '', '', NULL, '1', '1', '1', '0', '2026-09-22 20:02:17', NULL),
('192', '2', '89', '190', 'WA-002', 'Ware House No 2', 'warehouse', '', '', '', '', '', '', '', NULL, '1', '1', '1', '0', '2026-09-22 20:03:00', NULL);

-- Table: cash_opening
INSERT INTO `cash_opening` (`id`, `tenant_id`, `company_id`, `branch_id`, `currency_id`, `as_of_date`, `opening_amount`, `is_locked`, `entered_by`, `entered_at`, `updated_at`) VALUES
('93', '2', '89', '190', '2', '2026-09-21', '60000.00', '1', 'System', '2026-09-23 20:31:27', '2026-09-23 20:31:27');

-- Table: categories
INSERT INTO `categories` (`id`, `tenant_id`, `category_name`) VALUES
('311', '2', 'Tissue');

-- Table: cities
INSERT INTO `cities` (`id`, `tenant_id`, `region_id`, `city_name`) VALUES
('232', '2', '87', 'Karachi'),
('233', '2', '87', 'Hyderabad'),
('234', '2', '88', 'Lahore'),
('235', '2', '88', 'Multan');

-- Table: city_zones
INSERT INTO `city_zones` (`id`, `tenant_id`, `city_id`, `city_zone_name`) VALUES
('140', '2', '232', 'Gulshan-E-Iqbal'),
('141', '2', '232', 'Gulistan-E-Johr'),
('142', '2', '233', 'Latifabad'),
('143', '2', '234', 'Gulberg'),
('144', '2', '234', 'DHA'),
('145', '2', '235', 'Purana Shujabat Road Bazar');

-- Table: companies
INSERT INTO `companies` (`id`, `tenant_id`, `company_code`, `company_name`, `legal_name`, `email`, `phone`, `mobile_no`, `website`, `address`, `country`, `country_id`, `state`, `city`, `zipcode`, `po_box_no`, `industry_type`, `registration_number`, `tax_identification_number`, `sales_tax_number`, `logo_url`, `language_code`, `timezone`, `currency_code`, `inventory_valuation_method`, `is_active`, `created_at`, `updated_at`, `slogan`) VALUES
('89', '2', 'COMP-001', 'InnovaTech', '', 'innova.tech213@gmail.com', '+923353789981', '', '', 'Ali block Loop road Bahria Town Karachi', 'Pakistan', '75', 'Sindh', 'Karachi', '75340', '', '', '', '', '', 'logo_6ab2db44b8308.webp', 'en', 'Asia/Karachi', 'USD', 'AVCO', '1', '2026-09-22 19:43:49', '2026-09-22 20:00:29', NULL);

-- Table: completed_products
INSERT INTO `completed_products` (`id`, `tenant_id`, `production_completion_id`, `product_id`, `planned_qty`, `remaining_qty`, `completed_qty`, `uom_id`, `unit_cost`, `total_cost`, `created_at`, `costing_method`, `material_cost`, `overhead_cost`, `cost_adjusted`, `adjusted_at`) VALUES
('3655', '2', '3631', '7914', '10.00', '10.00', '10.00', '9', '482.00', '4820.00', '2026-09-24 08:35:29', 'actual', '0.0000', '0.0000', '0', NULL);

-- Table: cost_centers
INSERT INTO `cost_centers` (`id`, `tenant_id`, `account_id`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
('227', '2', '461', 'Tea', NULL, '1', '2026-09-24 04:50:22', '2026-09-24 04:50:22'),
('228', '2', '461', 'Lunch', NULL, '1', '2026-09-24 04:50:39', '2026-09-24 04:50:39'),
('229', '2', '59', 'Electricity', NULL, '1', '2026-09-24 04:55:31', '2026-09-24 04:55:31');

-- Table: countries
INSERT INTO `countries` (`id`, `tenant_id`, `country_name`) VALUES
('75', '2', 'Pakistan');

-- Table: customer_categories
INSERT INTO `customer_categories` (`id`, `tenant_id`, `category_name`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
('58', '2', 'Kyriana', '69', '2026-09-23 16:27:35', NULL, '2026-09-23 16:27:35'),
('59', '2', 'Medical Store', '69', '2026-09-23 16:27:40', NULL, '2026-09-23 16:27:40'),
('60', '2', 'General Store', '69', '2026-09-23 16:27:45', NULL, '2026-09-23 16:27:45'),
('61', '2', 'Bakery', '69', '2026-09-23 16:27:52', NULL, '2026-09-23 16:27:52');

-- Table: customers
INSERT INTO `customers` (`id`, `tenant_id`, `company_id`, `customer_code`, `customer_name`, `shop_name`, `address`, `po_box_no`, `primary_phone`, `secondary_phone`, `identity_card_no`, `license_no`, `email`, `opening_debit_amount`, `opening_credit_amount`, `current_balance`, `is_blacklisted`, `is_out_station`, `is_both`, `linked_supplier_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`, `country_id`, `region_id`, `city_id`, `city_zone_id`, `area_id`, `is_sales_tax_registered`, `strn`, `is_filer`, `ntn`, `advance_income_tax_percentage`, `default_discount_percentage`, `credit_limit`, `credit_period_limit_days`, `is_wholesaler`, `associated_sales_officer_id`, `customer_type_id`, `customer_category_id`, `supplier_man_id`, `project_id`, `brand_id`) VALUES
('13050', '2', '89', 'CUST-00001', 'Zahid G/S', 'zahid', 'Street 4 13D Gulshan', NULL, '03357541254', NULL, NULL, NULL, NULL, '0.00', '0.00', '0.00', '0', '0', '0', NULL, 'ACTIVE', '2026-09-23 16:28:45', '2026-09-23 16:31:46', '69', '69', '75', '87', '232', '140', '142', '0', NULL, '0', NULL, '0.00', '0.00', '25000.00', '10', '0', '245', '1', '60', NULL, NULL, NULL),
('13053', '2', '89', 'CUST-00002', 'Rustam Bakers', NULL, '88c', NULL, '03214521452', NULL, NULL, NULL, NULL, '0.00', '0.00', '0.00', '0', '0', '0', NULL, 'ACTIVE', '2026-09-23 19:18:15', '2026-09-23 19:18:15', '69', '69', '75', '87', '232', '140', '142', '1', NULL, '1', '87876-98', '0.00', '0.00', '0.00', '0', '0', '245', '1', '61', NULL, NULL, NULL),
('13054', '2', '89', 'CUST-00003', 'Wasim Medical Store', NULL, 'Main Bazar', NULL, '03357854785', NULL, NULL, NULL, NULL, '4000.00', '0.00', '4000.00', '0', '0', '0', NULL, 'ACTIVE', '2026-09-23 19:40:22', '2026-09-23 19:43:44', '69', '69', '75', '87', '232', '141', '144', '0', NULL, '0', NULL, '0.00', '0.00', '0.00', '0', '0', '246', '2', '59', NULL, NULL, NULL);

-- Table: employees
INSERT INTO `employees` (`id`, `tenant_id`, `company_id`, `employee_id`, `full_name`, `date_of_birth`, `gender`, `nationality`, `marital_status`, `email`, `personal_email`, `phone_number`, `emergency_contact_name`, `emergency_contact_phone`, `address`, `city`, `state`, `postal_code`, `country`, `profile_photo_url`, `department_id`, `position_id`, `employment_type`, `employee_category`, `hire_date`, `probation_end_date`, `work_location`, `shift_timing`, `reporting_manager_id`, `work_hours_per_week`, `work_days_per_week`, `current_status`, `status_effective_date`, `status_change_reason`, `leave_balance_paid`, `leave_balance_sick`, `leave_balance_unpaid`, `current_leave_id`, `is_suspended`, `suspension_start_date`, `suspension_end_date`, `suspension_type`, `suspension_reason`, `is_terminated`, `termination_date`, `termination_type`, `termination_reason`, `notice_period`, `exit_interview_notes`, `final_settlement_processed`, `gratuity_paid`, `base_salary`, `salary_currency`, `salary_frequency`, `housing_allowance`, `transport_allowance`, `medical_allowance`, `meal_allowance`, `communication_allowance`, `education_allowance`, `travel_allowance`, `other_allowance`, `annual_bonus_percentage`, `provident_fund_percentage`, `gratuity_eligibility`, `gratuity_eligibility_after_years`, `health_insurance`, `life_insurance`, `paid_time_off`, `employment_agreement_url`, `id_proof_url`, `resume_url`, `certificates_url`, `medical_certificate_url`, `notes`, `employee_notes`, `hr_notes`, `created_at`, `created_by`, `updated_at`, `updated_by`, `is_active`, `deleted_at`, `deleted_by`, `leave_reset_period`, `next_reset_date`, `carry_forward_allowed`, `overtime_rate_per_hour`, `overtime_rate`, `overtime_rate_type`) VALUES
('245', '2', NULL, 'SALES-0001', 'Raheel', '2001-09-23', 'male', '', 'married', NULL, NULL, '', '', NULL, '', NULL, NULL, NULL, NULL, NULL, '5', '7', 'full-time', '', '2026-09-23', NULL, '', '09:00-17:00', NULL, NULL, NULL, 'active', '2026-09-23', NULL, '21', '10', '0', NULL, '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, '0', '0', '40000.00', 'USD', 'monthly', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0', '5', '1', '0', '1', NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, '2026-09-23 05:24:40', '69', '2026-09-23 05:24:40', NULL, '1', NULL, NULL, 'yearly', '2027-01-01', '0', '0.00', '100.00', 'per_hour'),
('246', '2', NULL, 'SALES-0002', 'Wasim', '2005-10-23', 'male', '', 'married', NULL, NULL, '', '', NULL, '', NULL, NULL, NULL, NULL, NULL, '5', '7', 'full-time', '', '2026-09-23', NULL, '', '09:00-17:00', NULL, NULL, NULL, 'active', '2026-09-23', NULL, '21', '10', '0', NULL, '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, '0', '0', '60000.00', 'USD', 'monthly', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0', '5', '1', '0', '1', NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, '2026-09-23 05:27:34', '69', '2026-09-23 05:27:34', NULL, '1', NULL, NULL, 'yearly', '2027-01-01', '0', '0.00', '300.00', 'per_day'),
('247', '2', NULL, 'SALES-0003', 'Naseer', '2001-09-23', 'male', '', '', NULL, NULL, '', '', NULL, '', NULL, NULL, NULL, NULL, NULL, '5', '81', 'full-time', '', '2026-09-23', NULL, '', '09:00-17:00', NULL, NULL, NULL, 'active', '2026-09-23', NULL, '21', '10', '0', NULL, '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, '0', '0', '30000.00', 'USD', 'monthly', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0', '5', '1', '0', '1', NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, '2026-09-23 05:39:51', '69', '2026-09-23 05:39:51', NULL, '1', NULL, NULL, 'yearly', '2027-01-01', '0', '0.00', '50.00', 'per_hour');

-- Table: expense_voucher
INSERT INTO `expense_voucher` (`id`, `tenant_id`, `company_id`, `project_id`, `container_id`, `vendor_id`, `type`, `voucher_no`, `date`, `description`, `total_amount`, `total_items`, `is_cash_flow`) VALUES
('1835', '2', '89', NULL, NULL, NULL, 'CR', 'EXP-2026-00002', '2026-09-22', '', '600.00', '2', '1'),
('1836', '2', '89', NULL, NULL, NULL, 'CR', 'EXP-2026-00003', '2026-09-24', '', '6000.00', '1', '1'),
('1837', '2', '89', NULL, NULL, NULL, 'CR', 'EXP-2026-00004', '2026-09-24', '', '300.00', '1', '1');

-- Table: expense_voucher_line
INSERT INTO `expense_voucher_line` (`id`, `tenant_id`, `voucher_id`, `account_id`, `cost_center_id`, `amount`, `payment_method_id`, `bank_account_id`, `cheque_date`, `cheque_no`) VALUES
('3615', '2', '1835', '461', '227', '200.00', '7', NULL, NULL, NULL),
('3616', '2', '1835', '461', '228', '400.00', '7', NULL, NULL, NULL),
('3617', '2', '1836', '59', '229', '6000.00', '7', NULL, NULL, NULL),
('3618', '2', '1837', '461', '227', '300.00', '7', NULL, NULL, NULL);

-- Table: item_companies
INSERT INTO `item_companies` (`id`, `tenant_id`, `company_name`, `created_at`) VALUES
('1', '2', 'Nestle', '2026-09-23 15:39:09'),
('2', '2', 'Panadol', '2026-09-23 15:39:40'),
('3', '2', 'Abc', '2026-09-23 15:43:59');

-- Table: leave_types
INSERT INTO `leave_types` (`id`, `tenant_id`, `name`, `code`, `is_paid`, `is_active`, `requires_approval`, `allow_carry_forward`, `max_carry_forward_days`, `max_days_per_year`, `track_balance`, `notes`, `display_order`, `created_at`, `created_by`, `updated_at`, `icon`, `kind`, `quota_days_per_year`, `is_builtin`, `slug`) VALUES
('127', '2', 'Annual Leave', NULL, '1', '1', '1', '0', NULL, NULL, '1', NULL, '0', '2026-09-23 06:30:13', '69', '2026-09-23 06:30:13', 'fa-sun', 'range', NULL, '1', 'annual'),
('128', '2', 'Casual Leave', NULL, '1', '1', '1', '0', NULL, NULL, '1', NULL, '0', '2026-09-23 06:30:13', '69', '2026-09-23 06:30:13', 'fa-umbrella-beach', 'range', NULL, '1', 'casual'),
('129', '2', 'Sick Leave', NULL, '1', '1', '1', '0', NULL, NULL, '1', NULL, '0', '2026-09-23 06:30:13', '69', '2026-09-23 06:30:13', 'fa-briefcase-medical', 'range', NULL, '1', 'sick'),
('130', '2', 'Over Time', NULL, '1', '1', '1', '0', NULL, NULL, '1', NULL, '0', '2026-09-23 06:30:13', '69', '2026-09-23 06:30:13', 'fa-clock', 'overtime', NULL, '1', 'ot');

-- Table: login_attempts
INSERT INTO `login_attempts` (`id`, `tenant_id`, `username`, `email`, `user_id`, `ip_address`, `user_agent`, `session_id`, `attempt_status`, `failure_reason`, `country_code`, `is_suspicious`, `suspicious_reason`, `attempted_at`) VALUES
('10270', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:a513:88de:5194:1e7d', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-22 19:46:55'),
('10275', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:a513:88de:5194:1e7d', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 04:25:12'),
('10281', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:a513:88de:5194:1e7d', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 05:22:18'),
('10285', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:a513:88de:5194:1e7d', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 06:29:50'),
('10288', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:a513:88de:5194:1e7d', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 06:56:45'),
('10289', '2', 'zahid@unisensystems.com', NULL, '69', '202.47.35.3', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 06:58:08'),
('10291', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:98c0:ca99:9b64:2606', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 07:12:24'),
('10302', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:98c0:ca99:9b64:2606', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 08:39:34'),
('10305', '2', 'zahid@unisensystems.com', NULL, '69', '202.47.35.3', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 09:27:21'),
('10307', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:1d3f:a18e:a0d4:5b8a', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 09:45:57'),
('10321', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:1d3f:a18e:a0d4:5b8a', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 12:01:59'),
('10332', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:1d3f:a18e:a0d4:5b8a', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 13:13:01'),
('10337', '2', 'zahid@unisensystems.com', NULL, '69', '103.31.107.100', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 14:04:15'),
('10344', '2', 'zahid@unisensystems.com', NULL, '69', '103.125.177.19', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 15:41:57'),
('10349', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:1d3f:a18e:a0d4:5b8a', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 16:26:21'),
('10355', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:1d3f:a18e:a0d4:5b8a', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 17:00:46'),
('10356', '2', 'zahid@unisensystems.com', NULL, '69', '202.47.35.3', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 17:52:34'),
('10361', '2', 'zahid@unisensystems.com', NULL, '69', '103.125.177.19', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-23 19:40:54'),
('10367', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:1d3f:a18e:a0d4:5b8a', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-24 03:51:35'),
('10392', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:80a3:6dee:f9f:f37a', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-24 08:23:07'),
('10407', '2', 'zahid@unisensystems.com', NULL, '69', '103.125.177.19', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-24 11:34:48'),
('10408', '2', 'zahid@unisensystems.com', NULL, '69', '202.47.40.159', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-24 11:37:04'),
('10416', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:80a3:6dee:f9f:f37a', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-24 13:02:44'),
('10419', '2', 'zahid@unisensystems.com', NULL, '69', '103.31.107.100', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-24 13:21:32'),
('10422', '2', 'zahid@unisensystems.com', NULL, '69', '103.31.107.100', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-24 13:27:25'),
('10425', '2', 'zahid@unisensystems.com', NULL, '69', '2400:adc1:19d:6b00:80a3:6dee:f9f:f37a', NULL, NULL, 'success', NULL, NULL, '0', NULL, '2026-09-24 13:29:52');

-- Table: machines
INSERT INTO `machines` (`id`, `tenant_id`, `code`, `machine_name`, `branch_id`, `capacity_speed`, `notes`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
('11', '2', 'MCH-0001', 'Machine 1', '191', '', '', '1', '69', '2026-09-24 08:30:20', NULL);

-- Table: opening_balance_invoices
INSERT INTO `opening_balance_invoices` (`id`, `tenant_id`, `customer_id`, `distribution_id`, `employee_id`, `invoice_number`, `debit`, `credit`, `invoice_date`) VALUES
('4664', '2', '13054', '1', '246', '965', '4000.00', '0.00', '2026-09-04');

-- Table: payment_voucher
INSERT INTO `payment_voucher` (`id`, `tenant_id`, `company_id`, `currency_id`, `voucher_number`, `voucher_date`, `supplier_id`, `bill_no`, `amount`, `payment_method_id`, `bank_account_id`, `cheque_date`, `attachment`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `sub_account_id`, `customer_id`, `customer_sub_account_id`, `cheque_no`, `non_cash`, `project_id`, `container_id`, `expense_voucher_ref_id`) VALUES
('1811', '2', '89', '2', 'PV-2026-0001', '2026-09-23', '462', 'PUR-0001', '400.00', '7', NULL, NULL, NULL, NULL, '69', '69', '2026-09-23 16:49:39', '2026-09-23 16:49:39', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL);

-- Table: positions
INSERT INTO `positions` (`id`, `tenant_id`, `position_code`, `position_title`, `department_id`, `job_description`, `min_salary`, `max_salary`, `is_active`, `created_at`) VALUES
('77', '2', 'IT O', 'IT Officer', '1', NULL, NULL, NULL, '1', '2026-09-23 05:36:17'),
('79', '2', 'IT M', 'IT Manager', '1', NULL, NULL, NULL, '1', '2026-09-23 05:37:26'),
('81', '2', 'SUPL', 'Suplier Man', '5', NULL, NULL, NULL, '1', '2026-09-23 05:38:34');

-- Table: post_dated_cheques
INSERT INTO `post_dated_cheques` (`id`, `tenant_id`, `company_id`, `cheque_no`, `account_id`, `bank_account_id`, `transaction_type`, `customer_id`, `supplier_id`, `amount`, `cheque_date`, `status`, `created_at`, `updated_at`, `reference_id`, `reference_table`) VALUES
('160', '2', '89', '9652188-ABL', NULL, '145', 'Received', '13053', NULL, '300.00', '2026-09-26', 'Pending', '2026-09-24 11:35:21', '2026-09-24 11:35:21', '17504', 'receive_voucher');

-- Table: product_schemes
INSERT INTO `product_schemes` (`id`, `tenant_id`, `product_id`, `unit_id`, `promo_qty`, `bonus_qty`, `to_qty`, `to_rs`, `created_at`, `updated_at`) VALUES
('107', '2', '7901', '16', '1', '1', '0', '0.00', '2026-09-23 10:17:32', '2026-09-23 10:17:32'),
('108', '2', '7901', '10', '2', '1', '0', '0.00', '2026-09-23 10:17:32', '2026-09-23 10:17:32'),
('109', '2', '7901', '9', '8', '1', '0', '0.00', '2026-09-23 10:17:32', '2026-09-23 10:17:32');

-- Table: product_uom_conversions
INSERT INTO `product_uom_conversions` (`id`, `tenant_id`, `product_id`, `uom_id`, `conversion_factor`, `created_at`, `updated_at`) VALUES
('1125', '2', '7901', '16', '24.000000', '2026-09-23 10:17:32', '2026-09-23 16:24:33');

-- Table: production_completion_materials
INSERT INTO `production_completion_materials` (`id`, `tenant_id`, `production_completion_id`, `material_id`, `consumed_qty`, `uom_id`, `unit_cost`, `total_cost`, `created_at`) VALUES
('21985', '2', '3631', '7912', '30.0000', '12', '60.0000', '1800.0000', '2026-09-24 08:35:29'),
('21986', '2', '3631', '7913', '20.0000', '9', '90.0000', '1800.0000', '2026-09-24 08:35:29');

-- Table: production_completions
INSERT INTO `production_completions` (`id`, `tenant_id`, `completion_no`, `production_order_id`, `branch_id`, `machine_id`, `complete_date`, `total_products`, `total_quantity`, `total_cost`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
('3631', '2', 'PC-0001', '3812', '191', '11', '2026-09-24', '1', '10.00', '4820.00', 'Completed', '69', '2026-09-24 08:35:29', NULL);

-- Table: production_expense_items
INSERT INTO `production_expense_items` (`id`, `tenant_id`, `expense_id`, `gl_account_id`, `expense_type`, `description`, `hours`, `rate`, `amount`, `vendor_id`, `payment_status`, `payment_mode`, `bank_account_id`, `cheque_no`, `cheque_date`, `created_at`) VALUES
('1806', '2', '1806', '152', NULL, 'Indirect Expenses → Factory Overheads → Factory Electricity', NULL, NULL, '20.00', NULL, 'paid', 'cash', NULL, NULL, NULL, '2026-09-24 08:35:11'),
('1807', '2', '1806', '147', NULL, 'Manufacturing Expenses → Direct Expenses → Direct Labor Wages', '40.00', '30.00', '1200.00', NULL, 'paid', 'cash', NULL, NULL, NULL, '2026-09-24 08:35:11');

-- Table: production_expenses
INSERT INTO `production_expenses` (`id`, `tenant_id`, `production_order_id`, `entry_date`, `narration`, `total_amount`, `status`, `created_by`, `created_at`, `updated_at`, `allocation_method`, `per_unit_cost`) VALUES
('1806', '2', '3812', '2026-09-24', '', '1220.00', 'Posted', '69', '2026-09-24 08:35:11', '2026-09-24 08:35:11', 'direct', '0.0000');

-- Table: production_order_materials
INSERT INTO `production_order_materials` (`id`, `tenant_id`, `production_order_id`, `material_id`, `required_qty`, `issued_qty`, `uom_id`, `status`) VALUES
('24274', '2', '3812', '7912', '100.00', '30.00', '12', 'Issued'),
('24275', '2', '3812', '7913', '40.00', '20.00', '9', 'Issued');

-- Table: production_orders
INSERT INTO `production_orders` (`id`, `tenant_id`, `order_no`, `product_id`, `bom_id`, `bom_version`, `branch_id`, `machine_id`, `order_qty`, `status`, `start_date`, `end_date`, `created_by`, `created_at`, `updated_at`) VALUES
('3812', '2', 'PO-0001', '7914', '314', '1.0', '191', '11', '10.00', 'Completed', '2026-09-24', '2026-09-30', '69', '2026-09-24 08:31:56', '2026-09-24 08:35:29');

-- Table: products
INSERT INTO `products` (`id`, `tenant_id`, `company_id`, `item_company_id`, `code`, `name`, `product_type`, `default_unit_id`, `uom_group_id`, `uom_type`, `category_id`, `subcategory_id`, `description`, `weight_value`, `weight_unit`, `weight`, `qr_code`, `barcode`, `photo`, `purchase_price`, `mrp`, `price_entry_unit_id`, `trade_price_entry_value`, `mrp_entry_value`, `default_discount`, `min_stock_level`, `max_stock_level`, `manufacturing_date`, `expiry_date`, `batch_no`, `is_active`, `created_by`, `created_at`, `updated_at`, `inventory_account_id`, `vendor_id`, `trade_price`, `trade_offer_discount`, `default_foc`, `carton_conversion`, `tax_regime_id`, `hs_code`, `wholesale_price`, `parent_product_id`, `stock_affects`, `invoice_affects`, `ctn_trade_price`, `box_trade_price`, `pcs_trade_offer_amount`, `ctn_trade_offer_amount`, `box_trade_offer_amount`, `box_conversion`, `tax_regime`, `sales_tax`, `sales_tax_type`, `further_tax`, `product_conversion_factor`) VALUES
('7900', '2', '89', NULL, 'PROD-000001', 'Test', 'physical', '9', NULL, 'unit', '311', NULL, '', NULL, NULL, NULL, '9999', '8654', NULL, '100.000', '300.000', NULL, NULL, NULL, '0.00', '0.000', '0.000', NULL, NULL, NULL, '1', '69', '2026-09-23 10:09:41', '2026-09-23 10:10:30', '33', '462', '200.000', '0.00', '0', '0', NULL, NULL, '170.000', NULL, '1', '1', '0.00', '0.00', '0.00', '0.00', '0.00', '0', 'standard', NULL, NULL, NULL, NULL),
('7901', '2', '89', NULL, 'PROD-000002', 'Test2', 'physical', NULL, '1', 'group', '311', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '300.000', '500.000', NULL, NULL, NULL, '0.00', '0.000', '0.000', NULL, NULL, NULL, '1', '69', '2026-09-23 10:17:32', '2026-09-23 10:17:32', '33', NULL, '450.000', '0.00', '0', '0', NULL, NULL, '400.000', NULL, '1', '1', '0.00', '0.00', '0.00', '0.00', '0.00', '0', 'standard', NULL, NULL, NULL, NULL),
('7909', '2', '89', '3', 'PROD-000003', 'Sample', 'physical', '9', NULL, 'unit', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '100.000', '0.000', NULL, NULL, NULL, '0.00', '0.000', '0.000', NULL, NULL, NULL, '1', '69', '2026-09-23 15:44:25', '2026-09-23 20:09:13', '33', NULL, '200.000', '0.00', '0', '0', '34', NULL, '0.000', NULL, '1', '1', '0.00', '0.00', '0.00', '0.00', '0.00', '0', 'standard', NULL, NULL, NULL, NULL),
('7912', '2', '89', NULL, 'PROD-000004', 'A', 'physical', '12', NULL, 'unit', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '60.000', '0.000', NULL, NULL, NULL, '0.00', '0.000', '0.000', NULL, NULL, NULL, '1', '69', '2026-09-24 08:24:46', '2026-09-24 08:24:46', '115', NULL, '0.000', '0.00', '0', '0', NULL, NULL, '0.000', NULL, '1', '1', '0.00', '0.00', '0.00', '0.00', '0.00', '0', 'standard', NULL, NULL, NULL, NULL),
('7913', '2', '89', NULL, 'PROD-000005', 'B', 'physical', '9', NULL, 'unit', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '90.000', '0.000', NULL, NULL, NULL, '0.00', '0.000', '0.000', NULL, NULL, NULL, '1', '69', '2026-09-24 08:25:36', '2026-09-24 08:28:20', '115', NULL, '0.000', '0.00', '0', '0', NULL, NULL, '0.000', NULL, '1', '1', '0.00', '0.00', '0.00', '0.00', '0.00', '0', 'standard', NULL, NULL, NULL, NULL),
('7914', '2', '89', NULL, 'PROD-000006', 'C', 'physical', '9', NULL, 'unit', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, '0.000', '300.000', NULL, NULL, NULL, '0.00', '0.000', '0.000', NULL, NULL, NULL, '1', '69', '2026-09-24 08:26:35', '2026-09-24 08:26:35', '117', NULL, '220.000', '0.00', '0', '0', NULL, NULL, '200.000', NULL, '1', '1', '0.00', '0.00', '0.00', '0.00', '0.00', '0', 'standard', NULL, NULL, NULL, NULL);

-- Table: project_team_members
INSERT INTO `project_team_members` (`id`, `project_id`, `employee_id`, `tenant_id`, `added_at`) VALUES
('102', '36', '247', '2', '2026-09-24 04:23:44'),
('103', '36', '245', '2', '2026-09-24 04:23:44');

-- Table: projects
INSERT INTO `projects` (`id`, `tenant_id`, `company_id`, `project_code`, `project_name`, `city_id`, `start_date`, `end_date`, `project_manager_id`, `team_leader_id`, `status`, `description`, `created_at`, `updated_at`) VALUES
('36', '2', '1', 'PRJ-0001', 'DB Box', '234', '2026-09-24', '2026-09-27', '245', NULL, 'active', '', '2026-09-24 04:23:44', '2026-09-24 04:23:44');

-- Table: purchase_invoice
INSERT INTO `purchase_invoice` (`id`, `tenant_id`, `company_id`, `currency_id`, `bill_no`, `purchase_date`, `supplier_id`, `branch_id`, `container_id`, `previous_balance`, `total_bill`, `total_discount_percent`, `total_discount_amount`, `net_amount`, `total_tax_percent`, `total_tax_amount`, `is_tax`, `remarks`, `rp_total`, `tp_total`, `discount_total`, `sales_tax_total`, `advance_tax_percent`, `advance_tax`, `created_by`, `updated_by`, `created_at`, `updated_at`, `supplier_invoice_no`, `supplier_invoice_date`, `bilty_no`, `total_gst_percent`, `total_gst_amount`, `shipping_fees`, `shipping_fees_type`, `transport_name`, `purchase_order_id`, `rpo_no`, `truck_no`, `delivered_at`, `payment_term_id`, `sub_account_id`, `status`, `aiq_status`, `rate_type`, `brokery_rate_type`, `brokery_kg_basis`, `brokery_pct_mode`, `brokery_rate`, `brokery_amount`, `brokery_amount_sign`, `brokery_tax_percent`, `brokery_tax_amount`, `brokery_tax_amount_sign`, `wt_charges`, `wt_charges_sign`, `freight`, `freight_sign`, `m_sukri`, `m_sukri_sign`, `broken_percent`, `broken_amount`, `broken_amount_sign`, `bardana`, `bardana_sign`, `phone_charges`, `phone_charges_sign`, `filling_charges`, `filling_charges_sign`, `total_charges`, `project_id`, `assoc_company_id`, `engine_no`) VALUES
('1957', '2', '89', '2', 'PUR-0001', '2026-09-23', '462', '191', NULL, '0.000', '2000.000', '0.000', '0.000', '2000.000', '0.000', '0.000', '0', '', '0.000', '0.000', '0.000', '0.000', '0.000', '0.000', '69', '69', '2026-09-23 12:33:08', '2026-09-23 12:33:08', '325', '2026-09-23', '7856', '0.000', '0.000', '0.000', 'add', 'Baba Fareed', '355', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, 'net', '0', '0.0000', '0.0000', '+', '0.0000', '0.0000', '+', '0.0000', '+', '0.0000', '+', '0.0000', '+', '0.0000', '0.0000', '+', '0.0000', '+', '0.0000', '+', '0.0000', '+', '0.0000', NULL, NULL, NULL),
('1969', '2', '89', '2', 'PUR-0002', '2026-09-03', '461', '191', NULL, '-100000.000', '36390.000', '0.000', '0.000', '35890.000', '0.000', '0.000', '0', '', '0.000', '0.000', '0.000', '0.000', '0.000', '0.000', '69', '69', '2026-09-24 04:44:40', '2026-09-24 04:44:40', '69655', '2026-09-01', '2512', '0.000', '0.000', '500.000', 'subtract', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, 'net', '0', '0.0000', '0.0000', '+', '0.0000', '0.0000', '+', '0.0000', '+', '0.0000', '+', '0.0000', '+', '0.0000', '0.0000', '+', '0.0000', '+', '0.0000', '+', '0.0000', '+', '0.0000', NULL, NULL, NULL),
('1985', '2', '89', '2', 'PUR-0003', '2026-09-24', '465', '191', NULL, '-150000.000', '900.000', '0.000', '0.000', '900.000', '0.000', '0.000', '0', '', '0.000', '0.000', '0.000', '0.000', '0.000', '0.000', '69', '69', '2026-09-24 11:41:58', '2026-09-24 13:28:57', NULL, NULL, NULL, '0.000', '0.000', '0.000', 'add', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, 'net', '0', '0.0000', '0.0000', '+', '0.0000', '0.0000', '+', '0.0000', '+', '0.0000', '+', '0.0000', '+', '0.0000', '0.0000', '+', '0.0000', '+', '0.0000', '+', '0.0000', '+', '0.0000', NULL, NULL, NULL);

-- Table: purchase_invoice_items
INSERT INTO `purchase_invoice_items` (`id`, `tenant_id`, `purchase_invoice_id`, `product_id`, `uom_id`, `quantity`, `rp_unit_price`, `tp_unit_price`, `rp_total_value`, `tp_total_value`, `purchase_price`, `gross_amount`, `discount_percent`, `discount_amount`, `extra_discount_percent`, `sales_tax`, `tp_amount`, `net_amount`, `is_tax`, `created_by`, `updated_by`, `created_at`, `updated_at`, `vehicle_no`, `trade_offer_percent`, `trade_offer_amount`, `gst_percent`, `gst_amount`, `foc_quantity`, `size`, `batch_no`, `batch_expiry_date`, `piece`, `chassis_no`, `motor_no`, `colour`, `carton`, `dozen`, `parent_row_id`, `item_status`, `quantity_received`, `quantity_pending`, `tax_percent`, `tax_amount`, `bag`, `total_kg`, `cut_kg_percent`, `cut_kg`, `al_kg_percent`, `al_kg`, `net_kg`, `al_rate_cut`, `net_rate`, `engine_no`) VALUES
('8769', '2', '1957', '7900', '9', '20.000', '0.000', '0.000', '0.000', '0.000', '100.000', '2000.000', '0.000', '0.000', '0.0000', '0.000', '0.000', '2000.000', '0', '69', '69', '2026-09-23 12:33:08', '2026-09-23 12:33:08', NULL, '0.000', '0.000', '0.000', '0.000', '0', '', NULL, '2026-09-23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'received', '0.000', '0.000', '0.000', '0.000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', NULL),
('8788', '2', '1969', '7901', '16', '5.000', '0.000', '0.000', '0.000', '0.000', '300.000', '36000.000', '3.000', '1080.000', '0.0000', '0.000', '0.000', '34920.000', '0', '69', '69', '2026-09-24 04:44:40', '2026-09-24 04:44:40', NULL, '0.000', '0.000', '0.000', '0.000', '0', '', NULL, '2026-09-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'received', '0.000', '0.000', '0.000', '0.000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', NULL),
('8789', '2', '1969', '7900', '9', '15.000', '0.000', '0.000', '0.000', '0.000', '100.000', '1500.000', '2.000', '30.000', '0.0000', '0.000', '0.000', '1470.000', '0', '69', '69', '2026-09-24 04:44:40', '2026-09-24 04:44:40', NULL, '0.000', '0.000', '0.000', '0.000', '0', '', NULL, '2026-09-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'received', '0.000', '0.000', '0.000', '0.000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', NULL),
('8834', '2', '1985', '7912', '12', '15.000', '0.000', '0.000', '0.000', '0.000', '60.000', '900.000', '0.000', '0.000', '0.0000', '0.000', '0.000', '900.000', '0', '69', '69', '2026-09-24 13:28:57', '2026-09-24 13:28:57', NULL, '0.000', '0.000', '0.000', '0.000', '0', '', NULL, '2026-09-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'received', '0.000', '0.000', '0.000', '0.000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', '0.0000', NULL);

-- Table: purchase_invoice_taxes
INSERT INTO `purchase_invoice_taxes` (`id`, `tenant_id`, `purchase_invoice_id`, `tax_regime_id`, `tax_rate_id`, `tax_name`, `rate_percentage`, `base_amount`, `tax_amount`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
('9', '2', '1985', '35', NULL, 'Sales Tax', '18.0000', '900.000', '162.000', '69', '69', '2026-09-24 11:41:58', '2026-09-24 11:41:58');

-- Table: purchase_order
INSERT INTO `purchase_order` (`id`, `tenant_id`, `company_id`, `currency_id`, `bill_no`, `rpo_no`, `purchase_date`, `supplier_id`, `branch_id`, `previous_balance`, `total_bill`, `total_discount_percent`, `total_discount_amount`, `net_amount`, `remarks`, `created_by`, `updated_by`, `created_at`, `updated_at`, `supplier_invoice_no`, `supplier_invoice_date`, `bilty_no`, `total_gst_percent`, `total_gst_amount`, `shipping_fees`, `transport_name`, `truck_no`, `sub_account_id`, `last_date`, `broker`, `mill_name`, `moist`, `damage`, `under_mill`, `broken`, `chakki`, `payment_term_id`, `project_id`) VALUES
('355', '2', '89', '2', 'PO-0001', NULL, '2026-09-23', '462', '191', '0.00', '3000.00', '0.00', '0.00', '3000.00', '', '69', '69', '2026-09-23 12:13:33', '2026-09-23 12:13:33', NULL, NULL, NULL, '0.00', '0.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- Table: purchase_order_items
INSERT INTO `purchase_order_items` (`id`, `tenant_id`, `purchase_invoice_id`, `product_id`, `uom_id`, `quantity`, `purchase_price`, `gross_amount`, `discount_percent`, `discount_amount`, `net_amount`, `created_by`, `updated_by`, `created_at`, `updated_at`, `vehicle_no`, `trade_offer_percent`, `trade_offer_amount`, `gst_percent`, `gst_amount`, `foc_quantity`, `parent_row_id`, `piece`, `carton`, `dozen`) VALUES
('493', '2', '355', '7900', '9', '30.000', '100.0000', '3000.00', '0.00', '0.00', '3000.00', '69', '69', '2026-09-23 12:13:33', '2026-09-23 12:13:33', NULL, '0.00', '0.00', '0.00', '0.00', '0', NULL, NULL, NULL, NULL);

-- Table: receive_voucher
INSERT INTO `receive_voucher` (`id`, `tenant_id`, `company_id`, `currency_id`, `voucher_number`, `voucher_date`, `customer_id`, `bill_no`, `amount`, `adjust_amount`, `previous_balance`, `payment_method_id`, `bank_account_id`, `cheque_date`, `attachment`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `recovery_officer_id`, `against_pdc_id`, `sub_account_id`, `dsr_no`, `project_id`, `container_id`, `cheque_no`) VALUES
('17469', '2', '89', '2', 'RV-2026-0002', '2026-09-24', '13050', 'SAL-0002', '100.00', '0.00', '600.00', '1', NULL, NULL, NULL, 'null', '69', '69', '2026-09-23 16:49:15', '2026-09-23 16:49:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('17504', '2', '89', '2', 'RV-2026-0003', '2026-09-24', '13053', 'SAL-0003', '300.00', '0.00', '13759.00', '6', '145', '2026-09-26', NULL, 'null', '69', '69', '2026-09-24 04:18:16', '2026-09-24 04:18:16', NULL, NULL, NULL, NULL, NULL, NULL, '9652188-ABL'),
('17621', '2', '89', '2', 'RV-2026-0004', '2026-09-24', '13054', 'SAL-0005', '700.00', '0.00', '4392.00', '1', NULL, NULL, NULL, 'null', '69', '69', '2026-09-24 09:23:13', '2026-09-24 09:23:13', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- Table: regions
INSERT INTO `regions` (`id`, `tenant_id`, `country_id`, `region_name`) VALUES
('87', '2', '75', 'Sindh'),
('88', '2', '75', 'Punjab');

-- Table: role_permissions
INSERT INTO `role_permissions` (`id`, `tenant_id`, `role_id`, `category`, `form_name`, `sub_permission`, `allowed`, `created_at`, `updated_at`) VALUES
('1342', '2', '1', 'Banking', 'New Bank', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('21', '2', '1', 'Banking', 'New Bank', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1344', '2', '1', 'Banking', 'New Bank', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1343', '2', '1', 'Banking', 'New Bank', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1341', '2', '1', 'Banking', 'New Bank', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('22', '2', '1', 'Banking', 'Post Dated Cheques (PDCs)', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('23', '2', '1', 'Chart of Accounts', 'Chart of Accounts', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 21:00:14'),
('3585', '2', '1', 'Customer / Supplier', 'Leads', 'Add', '1', '2026-05-12 18:14:09', '2026-08-11 14:01:29'),
('3587', '2', '1', 'Customer / Supplier', 'Leads', 'Delete', '1', '2026-05-12 18:14:09', '2026-08-11 14:01:29'),
('3586', '2', '1', 'Customer / Supplier', 'Leads', 'Edit', '1', '2026-05-12 18:14:09', '2026-08-11 14:01:29'),
('3584', '2', '1', 'Customer / Supplier', 'Leads', 'View', '1', '2026-05-12 18:14:09', '2026-08-11 14:01:29'),
('39', '2', '1', 'Customer / Supplier', 'New Customer', 'Add', '1', '2026-01-03 17:37:23', '2026-02-27 04:30:05'),
('1', '2', '1', 'Customer / Supplier', 'New Customer', 'Coming Soon', '1', '2025-12-25 23:36:02', '2025-12-26 10:52:50'),
('1300', '2', '1', 'Customer / Supplier', 'New Customer', 'Delete', '1', '2026-01-24 15:22:06', '2026-03-08 01:12:06'),
('40', '2', '1', 'Customer / Supplier', 'New Customer', 'Edit', '1', '2026-01-03 17:37:23', '2026-03-08 01:12:06'),
('2736', '2', '1', 'Customer / Supplier', 'New Customer', 'View', '1', '2026-03-08 01:12:06', '2026-03-08 01:12:06'),
('6606', '2', '1', 'Customer / Supplier', 'New Customer + Supplier (Both)', 'Add', '1', '2026-08-29 08:44:33', '2026-08-29 08:44:33'),
('6608', '2', '1', 'Customer / Supplier', 'New Customer + Supplier (Both)', 'Delete', '1', '2026-08-29 08:44:33', '2026-08-29 08:44:33'),
('6607', '2', '1', 'Customer / Supplier', 'New Customer + Supplier (Both)', 'Edit', '1', '2026-08-29 08:44:33', '2026-08-29 08:44:33'),
('6605', '2', '1', 'Customer / Supplier', 'New Customer + Supplier (Both)', 'View', '1', '2026-08-29 08:44:33', '2026-08-29 08:44:33'),
('41', '2', '1', 'Customer / Supplier', 'New Supplier', 'Add', '1', '2026-01-03 20:12:56', '2026-01-03 20:12:56'),
('42', '2', '1', 'Customer / Supplier', 'New Supplier', 'Delete', '1', '2026-01-03 20:12:56', '2026-01-03 20:12:56'),
('1301', '2', '1', 'Customer / Supplier', 'New Supplier', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('2737', '2', '1', 'Customer / Supplier', 'New Supplier', 'View', '1', '2026-03-08 01:12:06', '2026-03-08 01:12:06'),
('4', '2', '1', 'Dashboard', 'Dashboard', 'Coming Soon', '1', '2025-12-26 11:22:32', '2026-05-12 12:30:40'),
('26', '2', '1', 'Financial Reports', 'Balance Sheet', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('6609', '2', '1', 'Financial Reports', 'Both (Customer + Supplier) Ledger', 'Coming Soon', '1', '2026-08-29 12:59:46', '2026-08-29 12:59:46'),
('28', '2', '1', 'Financial Reports', 'Cash Flow', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('4678', '2', '1', 'Financial Reports', 'Container Tracking', 'Coming Soon', '1', '2026-06-24 17:22:34', '2026-06-24 17:22:34'),
('53', '2', '1', 'Financial Reports', 'Customer Aging', 'Coming Soon', '1', '2026-01-05 12:14:12', '2026-01-05 12:14:12'),
('5', '2', '1', 'Financial Reports', 'Customer Ledger', 'Coming Soon', '1', '2025-12-26 11:58:39', '2025-12-26 11:58:39'),
('7154', '2', '1', 'Financial Reports', 'Customer Summary Division Report (CSDR)', 'Coming Soon', '1', '2026-09-02 15:40:09', '2026-09-02 15:40:09'),
('2758', '2', '1', 'Financial Reports', 'Financial Summary', 'Coming Soon', '1', '2026-03-10 19:03:43', '2026-03-10 19:03:43'),
('24', '2', '1', 'Financial Reports', 'General Ledger', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('5703', '2', '1', 'Financial Reports', 'Our Standing', 'Coming Soon', '1', '2026-08-11 02:07:29', '2026-08-11 02:07:29'),
('1345', '2', '1', 'Financial Reports', 'Pending DSR Sheet', 'Coming Soon', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('27', '2', '1', 'Financial Reports', 'Profit & Loss Statement', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('4650', '2', '1', 'Financial Reports', 'Project Tracking', 'Coming Soon', '1', '2026-06-22 18:17:43', '2026-06-22 18:17:43'),
('55', '2', '1', 'Financial Reports', 'Recovery Sheet', 'Coming Soon', '1', '2026-01-05 12:14:12', '2026-01-05 12:14:12'),
('66', '2', '1', 'Financial Reports', 'Sales Officer-wise Recovery', 'Coming Soon', '1', '2026-01-06 18:04:53', '2026-01-06 18:04:53'),
('56', '2', '1', 'Financial Reports', 'Supplier Aging', 'Coming Soon', '1', '2026-01-05 12:32:04', '2026-01-05 12:32:04'),
('6', '2', '1', 'Financial Reports', 'Supplier Ledger', 'Coming Soon', '1', '2025-12-26 11:58:39', '2025-12-26 11:58:39'),
('25', '2', '1', 'Financial Reports', 'Trial Balance', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('54', '2', '1', 'Financial Reports', 'Vendor Aging', 'Coming Soon', '1', '2026-01-05 12:14:12', '2026-01-05 12:14:12'),
('8', '2', '1', 'HRM', 'Employees Ledger', 'Coming Soon', '1', '2025-12-26 11:58:39', '2025-12-26 11:58:39'),
('7877', '2', '1', 'HRM', 'Leave Management', 'Add', '1', '2026-09-16 15:58:04', '2026-09-16 15:58:04'),
('7879', '2', '1', 'HRM', 'Leave Management', 'Delete', '1', '2026-09-16 15:58:04', '2026-09-16 15:58:04'),
('7878', '2', '1', 'HRM', 'Leave Management', 'Edit', '1', '2026-09-16 15:58:04', '2026-09-16 15:58:04'),
('7876', '2', '1', 'HRM', 'Leave Management', 'View', '1', '2026-09-16 15:58:04', '2026-09-16 15:58:04'),
('1347', '2', '1', 'HRM', 'New Employee', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('29', '2', '1', 'HRM', 'New Employee', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1349', '2', '1', 'HRM', 'New Employee', 'Delete', '1', '2026-01-24 15:22:06', '2026-02-27 18:08:31'),
('1348', '2', '1', 'HRM', 'New Employee', 'Edit', '1', '2026-01-24 15:22:06', '2026-02-27 18:08:31'),
('1346', '2', '1', 'HRM', 'New Employee', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1355', '2', '1', 'HRM', 'New Payroll', 'Add', '1', '2026-01-24 15:22:06', '2026-02-27 18:58:44'),
('30', '2', '1', 'HRM', 'New Payroll', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1357', '2', '1', 'HRM', 'New Payroll', 'Delete', '1', '2026-01-24 15:22:06', '2026-02-27 19:10:59'),
('1356', '2', '1', 'HRM', 'New Payroll', 'Edit', '1', '2026-01-24 15:22:06', '2026-03-08 01:12:06'),
('1354', '2', '1', 'HRM', 'New Payroll', 'View', '1', '2026-01-24 15:22:06', '2026-03-08 01:12:06'),
('1351', '2', '1', 'HRM', 'Record Attendance', 'Add', '1', '2026-01-24 15:22:06', '2026-03-08 01:12:06'),
('7', '2', '1', 'HRM', 'Record Attendance', 'Coming Soon', '1', '2025-12-26 11:58:39', '2025-12-26 11:58:39'),
('1353', '2', '1', 'HRM', 'Record Attendance', 'Delete', '1', '2026-01-24 15:22:06', '2026-03-08 01:12:06'),
('1352', '2', '1', 'HRM', 'Record Attendance', 'Edit', '1', '2026-01-24 15:22:06', '2026-03-08 01:12:06'),
('1350', '2', '1', 'HRM', 'Record Attendance', 'View', '1', '2026-01-24 15:22:06', '2026-03-08 01:12:06'),
('4675', '2', '1', 'Inventory', 'Container Setup', 'Add', '1', '2026-06-24 17:22:34', '2026-08-15 13:40:37'),
('4677', '2', '1', 'Inventory', 'Container Setup', 'Delete', '1', '2026-06-24 17:22:34', '2026-08-15 13:40:37'),
('4676', '2', '1', 'Inventory', 'Container Setup', 'Edit', '1', '2026-06-24 17:22:34', '2026-08-15 13:40:37'),
('4674', '2', '1', 'Inventory', 'Container Setup', 'View', '1', '2026-06-24 17:22:34', '2026-08-15 13:40:37'),
('2250', '2', '1', 'Inventory', 'Inward Gatepass', 'Add', '1', '2026-02-11 23:02:57', '2026-02-11 23:02:57'),
('2252', '2', '1', 'Inventory', 'Inward Gatepass', 'Delete', '1', '2026-02-11 23:02:57', '2026-02-11 23:02:57'),
('2251', '2', '1', 'Inventory', 'Inward Gatepass', 'Edit', '1', '2026-02-11 23:02:57', '2026-02-11 23:02:57'),
('2249', '2', '1', 'Inventory', 'Inward Gatepass', 'View', '1', '2026-02-11 23:02:57', '2026-02-11 23:02:57'),
('1310', '2', '1', 'Inventory', 'New Product', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('13', '2', '1', 'Inventory', 'New Product', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1312', '2', '1', 'Inventory', 'New Product', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1311', '2', '1', 'Inventory', 'New Product', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1309', '2', '1', 'Inventory', 'New Product', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('2254', '2', '1', 'Inventory', 'Outward Gatepass', 'Add', '1', '2026-02-11 23:02:57', '2026-02-11 23:02:57'),
('2256', '2', '1', 'Inventory', 'Outward Gatepass', 'Delete', '1', '2026-02-11 23:02:57', '2026-02-11 23:02:57'),
('2255', '2', '1', 'Inventory', 'Outward Gatepass', 'Edit', '1', '2026-02-11 23:02:57', '2026-02-11 23:02:57'),
('2253', '2', '1', 'Inventory', 'Outward Gatepass', 'View', '1', '2026-02-11 23:02:57', '2026-02-11 23:02:57'),
('1314', '2', '1', 'Inventory', 'Stock Adjustment', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('14', '2', '1', 'Inventory', 'Stock Adjustment', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1316', '2', '1', 'Inventory', 'Stock Adjustment', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1315', '2', '1', 'Inventory', 'Stock Adjustment', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1313', '2', '1', 'Inventory', 'Stock Adjustment', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('16', '2', '1', 'Inventory', 'Stock Position', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1318', '2', '1', 'Inventory', 'Stock Transfer', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('15', '2', '1', 'Inventory', 'Stock Transfer', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1320', '2', '1', 'Inventory', 'Stock Transfer', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1319', '2', '1', 'Inventory', 'Stock Transfer', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1317', '2', '1', 'Inventory', 'Stock Transfer', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('2711', '2', '1', 'Manufacturing', 'Bill of Materials (BOM)', 'Coming Soon', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2717', '2', '1', 'Manufacturing', 'Machine Setup', 'Add', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2719', '2', '1', 'Manufacturing', 'Machine Setup', 'Delete', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2718', '2', '1', 'Manufacturing', 'Machine Setup', 'Edit', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2716', '2', '1', 'Manufacturing', 'Machine Setup', 'View', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2729', '2', '1', 'Manufacturing', 'Production Completion', 'Add', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2731', '2', '1', 'Manufacturing', 'Production Completion', 'Delete', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2730', '2', '1', 'Manufacturing', 'Production Completion', 'Edit', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2728', '2', '1', 'Manufacturing', 'Production Completion', 'View', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2733', '2', '1', 'Manufacturing', 'Production Expenses', 'Add', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2735', '2', '1', 'Manufacturing', 'Production Expenses', 'Delete', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2734', '2', '1', 'Manufacturing', 'Production Expenses', 'Edit', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2732', '2', '1', 'Manufacturing', 'Production Expenses', 'View', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2721', '2', '1', 'Manufacturing', 'Production Order', 'Add', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2723', '2', '1', 'Manufacturing', 'Production Order', 'Delete', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2722', '2', '1', 'Manufacturing', 'Production Order', 'Edit', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2720', '2', '1', 'Manufacturing', 'Production Order', 'View', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('3534', '2', '1', 'Manufacturing', 'Production Report', 'Coming Soon', '1', '2026-04-28 12:47:23', '2026-07-31 15:40:59'),
('3533', '2', '1', 'Manufacturing', 'Production Wastage', 'Coming Soon', '1', '2026-04-28 12:47:23', '2026-07-31 15:40:59'),
('2713', '2', '1', 'Manufacturing', 'Unit Measurement', 'Add', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2715', '2', '1', 'Manufacturing', 'Unit Measurement', 'Delete', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2714', '2', '1', 'Manufacturing', 'Unit Measurement', 'Edit', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2712', '2', '1', 'Manufacturing', 'Unit Measurement', 'View', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2725', '2', '1', 'Manufacturing', 'WIP Management', 'Add', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2727', '2', '1', 'Manufacturing', 'WIP Management', 'Delete', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2726', '2', '1', 'Manufacturing', 'WIP Management', 'Edit', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('2724', '2', '1', 'Manufacturing', 'WIP Management', 'View', '1', '2026-03-08 01:09:56', '2026-07-31 15:40:59'),
('1359', '2', '1', 'Master Setup', 'Branch Setup', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('31', '2', '1', 'Master Setup', 'Branch Setup', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1361', '2', '1', 'Master Setup', 'Branch Setup', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1360', '2', '1', 'Master Setup', 'Branch Setup', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1358', '2', '1', 'Master Setup', 'Branch Setup', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('4142', '2', '1', 'Master Setup', 'Broken Allowance Chart', 'Coming Soon', '1', '2026-06-09 13:59:35', '2026-06-09 13:59:35'),
('32', '2', '1', 'Master Setup', 'Fueling Stations Setup', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('4652', '2', '1', 'Master Setup', 'Project Setup', 'Add', '1', '2026-06-22 18:18:23', '2026-06-22 18:18:23'),
('4654', '2', '1', 'Master Setup', 'Project Setup', 'Delete', '1', '2026-06-22 18:18:23', '2026-06-22 18:18:23'),
('4653', '2', '1', 'Master Setup', 'Project Setup', 'Edit', '1', '2026-06-22 18:18:23', '2026-06-22 18:18:23'),
('4651', '2', '1', 'Master Setup', 'Project Setup', 'View', '1', '2026-06-22 18:18:23', '2026-06-22 18:18:23'),
('1364', '2', '1', 'Master Setup', 'Rate List Setup', 'Add', '1', '2026-01-24 15:22:06', '2026-06-22 04:33:07'),
('1366', '2', '1', 'Master Setup', 'Rate List Setup', 'Delete', '1', '2026-01-24 15:22:06', '2026-06-22 04:33:07'),
('1365', '2', '1', 'Master Setup', 'Rate List Setup', 'Edit', '1', '2026-01-24 15:22:06', '2026-06-22 04:33:07'),
('1363', '2', '1', 'Master Setup', 'Rate List Setup', 'View', '1', '2026-01-24 15:22:06', '2026-06-22 04:33:07'),
('3538', '2', '1', 'Master Setup', 'Tax Rates Setup', 'Add', '1', '2026-05-06 04:04:24', '2026-05-06 04:04:24'),
('3750', '2', '1', 'Master Setup', 'Tax Rates Setup', 'Coming Soon', '1', '2026-05-24 07:37:10', '2026-08-15 13:40:37'),
('3544', '2', '1', 'Master Setup', 'Tax Rates Setup', 'Delete', '1', '2026-05-06 04:12:00', '2026-05-06 04:12:00'),
('3539', '2', '1', 'Master Setup', 'Tax Rates Setup', 'Edit', '1', '2026-05-06 04:04:24', '2026-05-06 04:04:24'),
('3537', '2', '1', 'Master Setup', 'Tax Rates Setup', 'View', '1', '2026-05-06 04:04:24', '2026-05-06 04:04:24'),
('3541', '2', '1', 'Master Setup', 'Tax Regimes Setup', 'Add', '1', '2026-05-06 04:04:24', '2026-05-06 04:04:24'),
('3751', '2', '1', 'Master Setup', 'Tax Regimes Setup', 'Coming Soon', '1', '2026-05-24 07:37:10', '2026-08-15 13:40:37'),
('3543', '2', '1', 'Master Setup', 'Tax Regimes Setup', 'Delete', '1', '2026-05-06 04:04:24', '2026-05-06 04:04:24'),
('3542', '2', '1', 'Master Setup', 'Tax Regimes Setup', 'Edit', '1', '2026-05-06 04:04:24', '2026-05-06 04:04:24'),
('3540', '2', '1', 'Master Setup', 'Tax Regimes Setup', 'View', '1', '2026-05-06 04:04:24', '2026-05-06 04:04:24'),
('63', '2', '1', 'Master Setup', 'Territory Setup', 'Add', '1', '2026-01-06 17:07:22', '2026-01-06 17:07:22'),
('65', '2', '1', 'Master Setup', 'Territory Setup', 'Delete', '1', '2026-01-06 17:07:22', '2026-01-06 17:07:22'),
('64', '2', '1', 'Master Setup', 'Territory Setup', 'Edit', '1', '2026-01-06 17:07:22', '2026-01-06 17:07:22'),
('1362', '2', '1', 'Master Setup', 'Territory Setup', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1305', '2', '1', 'Purchase', 'New Purchase', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('11', '2', '1', 'Purchase', 'New Purchase', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1307', '2', '1', 'Purchase', 'New Purchase', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('8140', '2', '1', 'Purchase', 'New Purchase', 'Duplicate', '1', '2026-09-22 20:05:09', '2026-09-22 20:05:09'),
('1306', '2', '1', 'Purchase', 'New Purchase', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1304', '2', '1', 'Purchase', 'New Purchase', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('4668', '2', '1', 'Purchase', 'Project Product Rates', 'Coming Soon', '1', '2026-06-23 15:43:41', '2026-06-23 15:43:41'),
('60', '2', '1', 'Purchase', 'Purchase Order', 'Add', '1', '2026-01-06 12:12:53', '2026-01-06 12:12:53'),
('62', '2', '1', 'Purchase', 'Purchase Order', 'Delete', '1', '2026-01-06 12:12:53', '2026-01-06 12:12:53'),
('61', '2', '1', 'Purchase', 'Purchase Order', 'Edit', '1', '2026-01-06 12:12:53', '2026-01-06 12:12:53'),
('1303', '2', '1', 'Purchase', 'Purchase Order', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('12', '2', '1', 'Purchase', 'Purchase Reports', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('49', '2', '1', 'Purchase', 'Purchase Return', 'Add', '1', '2026-01-04 18:39:39', '2026-01-04 18:39:39'),
('51', '2', '1', 'Purchase', 'Purchase Return', 'Delete', '1', '2026-01-04 18:39:39', '2026-01-04 18:39:39'),
('50', '2', '1', 'Purchase', 'Purchase Return', 'Edit', '1', '2026-01-04 18:39:39', '2026-01-04 18:39:39'),
('1308', '2', '1', 'Purchase', 'Purchase Return', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('2674', '2', '1', 'Purchase', 'Purchase Tax Invoice', 'Add', '0', '2026-03-03 13:04:03', '2026-09-03 11:06:16'),
('2676', '2', '1', 'Purchase', 'Purchase Tax Invoice', 'Delete', '0', '2026-03-03 13:04:03', '2026-05-24 07:34:40'),
('2675', '2', '1', 'Purchase', 'Purchase Tax Invoice', 'Edit', '0', '2026-03-03 13:04:03', '2026-05-24 07:34:40'),
('2673', '2', '1', 'Purchase', 'Purchase Tax Invoice', 'View', '0', '2026-03-03 13:04:03', '2026-09-03 11:06:16'),
('5639', '2', '1', 'Purchase', 'Record Purchase Order', 'Add', '1', '2026-07-31 05:46:54', '2026-09-22 20:05:09'),
('5641', '2', '1', 'Purchase', 'Record Purchase Order', 'Delete', '1', '2026-07-31 05:46:54', '2026-09-22 20:05:09'),
('5640', '2', '1', 'Purchase', 'Record Purchase Order', 'Edit', '1', '2026-07-31 05:46:54', '2026-09-22 20:05:09'),
('5638', '2', '1', 'Purchase', 'Record Purchase Order', 'View', '1', '2026-07-31 05:46:54', '2026-09-22 20:05:09'),
('2751', '2', '1', 'Rent Management', 'Issue Rent', 'Add', '0', '2026-03-10 00:53:53', '2026-04-25 13:45:38'),
('2753', '2', '1', 'Rent Management', 'Issue Rent', 'Delete', '0', '2026-03-10 00:53:53', '2026-04-25 13:45:38'),
('2752', '2', '1', 'Rent Management', 'Issue Rent', 'Edit', '0', '2026-03-10 00:53:53', '2026-04-25 13:45:38'),
('2750', '2', '1', 'Rent Management', 'Issue Rent', 'View', '0', '2026-03-10 00:53:53', '2026-04-25 13:45:38'),
('2755', '2', '1', 'Rent Management', 'Rent List', 'Add', '0', '2026-03-10 00:53:53', '2026-04-25 13:45:38'),
('2757', '2', '1', 'Rent Management', 'Rent List', 'Delete', '0', '2026-03-10 00:53:53', '2026-04-25 13:45:38'),
('2756', '2', '1', 'Rent Management', 'Rent List', 'Edit', '0', '2026-03-10 00:53:53', '2026-04-25 13:45:38'),
('2754', '2', '1', 'Rent Management', 'Rent List', 'View', '0', '2026-03-10 00:53:53', '2026-04-25 13:45:38'),
('3589', '2', '1', 'Sale', 'Create Quotation', 'Add', '1', '2026-05-12 18:14:09', '2026-05-12 18:14:09'),
('3591', '2', '1', 'Sale', 'Create Quotation', 'Delete', '1', '2026-05-12 18:14:09', '2026-05-12 18:14:09'),
('3590', '2', '1', 'Sale', 'Create Quotation', 'Edit', '1', '2026-05-12 18:14:09', '2026-05-12 18:14:09'),
('3588', '2', '1', 'Sale', 'Create Quotation', 'View', '1', '2026-05-12 18:14:09', '2026-05-12 18:14:09'),
('5766', '2', '1', 'Sale', 'Daily Product Sale', 'Coming Soon', '1', '2026-08-11 02:07:29', '2026-08-11 02:07:29'),
('52', '2', '1', 'Sale', 'Daily Sale Report', 'Coming Soon', '1', '2026-01-05 12:14:12', '2026-01-05 12:14:12'),
('6424', '2', '1', 'Sale', 'Daily Sales Report (DSR) - Multiple Unit', 'Coming Soon', '1', '2026-08-28 19:13:27', '2026-08-28 19:13:27'),
('7867', '2', '1', 'Sale', 'Discount Report', 'Coming Soon', '1', '2026-09-15 13:05:13', '2026-09-15 13:05:13'),
('3593', '2', '1', 'Sale', 'Issue Delivery Challan', 'Add', '1', '2026-05-12 18:14:09', '2026-05-12 18:14:09'),
('3595', '2', '1', 'Sale', 'Issue Delivery Challan', 'Delete', '1', '2026-05-12 18:14:09', '2026-05-12 18:14:09'),
('3594', '2', '1', 'Sale', 'Issue Delivery Challan', 'Edit', '1', '2026-05-12 18:14:09', '2026-05-12 18:14:09'),
('3592', '2', '1', 'Sale', 'Issue Delivery Challan', 'View', '1', '2026-05-12 18:14:09', '2026-05-12 18:14:09'),
('6145', '2', '1', 'Sale', 'Med Sale Invoice', 'Add', '1', '2026-08-17 13:33:58', '2026-08-17 13:33:58'),
('6147', '2', '1', 'Sale', 'Med Sale Invoice', 'Delete', '1', '2026-08-17 13:33:58', '2026-08-17 13:33:58'),
('6146', '2', '1', 'Sale', 'Med Sale Invoice', 'Edit', '1', '2026-08-17 13:33:58', '2026-08-17 13:33:58'),
('6144', '2', '1', 'Sale', 'Med Sale Invoice', 'View', '1', '2026-08-17 13:33:58', '2026-08-17 13:33:58'),
('2018', '2', '1', 'Sale', 'Meter Invoice', 'Add', '1', '2026-02-04 11:14:46', '2026-08-15 13:40:37'),
('2', '2', '1', 'Sale', 'Meter Invoice', 'Coming Soon', '1', '2025-12-25 23:36:02', '2025-12-26 10:52:58'),
('2740', '2', '1', 'Sale', 'Meter Invoice', 'Delete', '1', '2026-03-08 01:12:06', '2026-08-15 13:40:37'),
('2739', '2', '1', 'Sale', 'Meter Invoice', 'Edit', '1', '2026-03-08 01:12:06', '2026-08-15 13:40:37'),
('2017', '2', '1', 'Sale', 'Meter Invoice', 'View', '1', '2026-02-04 11:14:46', '2026-08-15 13:40:37'),
('43', '2', '1', 'Sale', 'POS Invoice', 'Add', '1', '2026-01-03 22:19:06', '2026-01-03 22:19:06'),
('45', '2', '1', 'Sale', 'POS Invoice', 'Delete', '1', '2026-01-03 22:19:06', '2026-01-03 22:19:06'),
('44', '2', '1', 'Sale', 'POS Invoice', 'Edit', '1', '2026-01-03 22:19:06', '2026-01-03 22:19:06'),
('2741', '2', '1', 'Sale', 'POS Invoice', 'View', '1', '2026-03-08 01:12:06', '2026-03-08 01:12:06'),
('1172', '2', '1', 'Sale', 'Sale Invoice', 'Add', '1', '2026-01-24 01:40:50', '2026-03-08 01:12:06'),
('1174', '2', '1', 'Sale', 'Sale Invoice', 'Delete', '1', '2026-01-24 01:40:50', '2026-03-08 01:12:06'),
('1173', '2', '1', 'Sale', 'Sale Invoice', 'Edit', '1', '2026-01-24 01:40:50', '2026-03-08 01:12:06'),
('1171', '2', '1', 'Sale', 'Sale Invoice', 'View', '1', '2026-01-24 01:40:50', '2026-03-08 01:12:06'),
('57', '2', '1', 'Sale', 'Sale Order', 'Add', '1', '2026-01-06 05:20:47', '2026-01-06 05:20:47'),
('59', '2', '1', 'Sale', 'Sale Order', 'Delete', '1', '2026-01-06 05:20:47', '2026-01-06 05:20:47'),
('58', '2', '1', 'Sale', 'Sale Order', 'Edit', '1', '2026-01-06 05:20:47', '2026-01-06 05:20:47'),
('2738', '2', '1', 'Sale', 'Sale Order', 'View', '1', '2026-03-08 01:12:06', '2026-03-08 01:12:06'),
('3', '2', '1', 'Sale', 'Sale Reports', 'Coming Soon', '1', '2025-12-25 23:36:02', '2025-12-26 10:53:06'),
('46', '2', '1', 'Sale', 'Sale Return', 'Add', '1', '2026-01-04 18:39:39', '2026-01-04 18:39:39'),
('48', '2', '1', 'Sale', 'Sale Return', 'Delete', '1', '2026-01-04 18:39:39', '2026-01-04 18:39:39'),
('47', '2', '1', 'Sale', 'Sale Return', 'Edit', '1', '2026-01-04 18:39:39', '2026-01-04 18:39:39'),
('1302', '2', '1', 'Sale', 'Sale Return', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('3747', '2', '1', 'Sale', 'Sale Tax Invoice', 'Add', '1', '2026-05-18 05:28:41', '2026-05-18 05:28:41'),
('3749', '2', '1', 'Sale', 'Sale Tax Invoice', 'Delete', '1', '2026-05-18 05:28:41', '2026-05-18 05:28:41'),
('8139', '2', '1', 'Sale', 'Sale Tax Invoice', 'Duplicate', '1', '2026-09-22 20:05:09', '2026-09-22 20:05:09'),
('3748', '2', '1', 'Sale', 'Sale Tax Invoice', 'Edit', '1', '2026-05-18 05:28:41', '2026-05-18 05:28:41'),
('3746', '2', '1', 'Sale', 'Sale Tax Invoice', 'View', '1', '2026-05-18 05:28:41', '2026-05-18 05:28:41'),
('7705', '2', '1', 'Sale', 'Traders Sale Invoice', 'Add', '1', '2026-09-15 05:32:39', '2026-09-15 05:32:39'),
('7707', '2', '1', 'Sale', 'Traders Sale Invoice', 'Delete', '1', '2026-09-15 05:32:39', '2026-09-15 05:32:39'),
('7706', '2', '1', 'Sale', 'Traders Sale Invoice', 'Edit', '1', '2026-09-15 05:32:39', '2026-09-15 05:32:39'),
('7704', '2', '1', 'Sale', 'Traders Sale Invoice', 'View', '1', '2026-09-15 05:32:39', '2026-09-15 05:32:39'),
('1368', '2', '1', 'System Setup', 'Admin Panel', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('34', '2', '1', 'System Setup', 'Admin Panel', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1370', '2', '1', 'System Setup', 'Admin Panel', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1369', '2', '1', 'System Setup', 'Admin Panel', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1367', '2', '1', 'System Setup', 'Admin Panel', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('33', '2', '1', 'System Setup', 'Company Profile', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1372', '2', '1', 'System Setup', 'Currency Setup', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('35', '2', '1', 'System Setup', 'Currency Setup', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1374', '2', '1', 'System Setup', 'Currency Setup', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1373', '2', '1', 'System Setup', 'Currency Setup', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1371', '2', '1', 'System Setup', 'Currency Setup', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('36', '2', '1', 'System Setup', 'Software Info', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1338', '2', '1', 'Vouchers', 'Cash Opening', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('20', '2', '1', 'Vouchers', 'Cash Opening', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1340', '2', '1', 'Vouchers', 'Cash Opening', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1339', '2', '1', 'Vouchers', 'Cash Opening', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1337', '2', '1', 'Vouchers', 'Cash Opening', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1330', '2', '1', 'Vouchers', 'Expense Voucher', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('37', '2', '1', 'Vouchers', 'Expense Voucher', 'Coming Soon', '1', '2025-12-27 13:27:41', '2025-12-27 13:27:41'),
('1332', '2', '1', 'Vouchers', 'Expense Voucher', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1331', '2', '1', 'Vouchers', 'Expense Voucher', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1329', '2', '1', 'Vouchers', 'Expense Voucher', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1334', '2', '1', 'Vouchers', 'Journal Entry', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('19', '2', '1', 'Vouchers', 'Journal Entry', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1336', '2', '1', 'Vouchers', 'Journal Entry', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1335', '2', '1', 'Vouchers', 'Journal Entry', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1333', '2', '1', 'Vouchers', 'Journal Entry', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1326', '2', '1', 'Vouchers', 'Payment Voucher', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('18', '2', '1', 'Vouchers', 'Payment Voucher', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1328', '2', '1', 'Vouchers', 'Payment Voucher', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1327', '2', '1', 'Vouchers', 'Payment Voucher', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1325', '2', '1', 'Vouchers', 'Payment Voucher', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1322', '2', '1', 'Vouchers', 'Receive Voucher', 'Add', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('17', '2', '1', 'Vouchers', 'Receive Voucher', 'Coming Soon', '1', '2025-12-26 18:32:45', '2025-12-26 18:32:45'),
('1324', '2', '1', 'Vouchers', 'Receive Voucher', 'Delete', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1323', '2', '1', 'Vouchers', 'Receive Voucher', 'Edit', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('1321', '2', '1', 'Vouchers', 'Receive Voucher', 'View', '1', '2026-01-24 15:22:06', '2026-01-24 15:22:06'),
('5007', '2', '1', 'Vouchers', 'Transfer Voucher', 'Add', '1', '2026-07-09 12:54:23', '2026-07-09 12:54:23'),
('5009', '2', '1', 'Vouchers', 'Transfer Voucher', 'Delete', '1', '2026-07-09 12:54:23', '2026-07-09 12:54:23'),
('5008', '2', '1', 'Vouchers', 'Transfer Voucher', 'Edit', '1', '2026-07-09 12:54:23', '2026-07-09 12:54:23'),
('5006', '2', '1', 'Vouchers', 'Transfer Voucher', 'View', '1', '2026-07-09 12:54:23', '2026-07-09 12:54:23'),
('2536', '2', '2', 'HRM', 'New Employee', 'Add', '1', '2026-02-27 11:49:24', '2026-02-27 11:49:24'),
('2538', '2', '2', 'HRM', 'New Employee', 'Delete', '1', '2026-02-27 11:49:24', '2026-02-27 11:49:24'),
('2537', '2', '2', 'HRM', 'New Employee', 'Edit', '1', '2026-02-27 11:49:24', '2026-02-27 11:49:24'),
('6406', '2', '4', 'Customer / Supplier', 'New Customer', 'Add', '1', '2026-08-23 04:32:02', '2026-08-23 04:32:02'),
('6408', '2', '4', 'Customer / Supplier', 'New Customer', 'Delete', '1', '2026-08-23 04:32:02', '2026-08-23 04:32:02'),
('6407', '2', '4', 'Customer / Supplier', 'New Customer', 'Edit', '1', '2026-08-23 04:32:02', '2026-08-23 04:32:02'),
('6405', '2', '4', 'Customer / Supplier', 'New Customer', 'View', '1', '2026-08-23 04:32:02', '2026-08-23 04:32:02'),
('6410', '2', '4', 'Customer / Supplier', 'New Supplier', 'Add', '1', '2026-08-23 04:32:02', '2026-08-23 04:32:02'),
('6412', '2', '4', 'Customer / Supplier', 'New Supplier', 'Delete', '1', '2026-08-23 04:32:02', '2026-08-23 04:32:02'),
('6411', '2', '4', 'Customer / Supplier', 'New Supplier', 'Edit', '1', '2026-08-23 04:32:02', '2026-08-23 04:32:02'),
('6409', '2', '4', 'Customer / Supplier', 'New Supplier', 'View', '1', '2026-08-23 04:32:02', '2026-08-23 04:32:02'),
('6404', '2', '4', 'Dashboard', 'Dashboard', 'Coming Soon', '1', '2026-08-23 04:32:02', '2026-08-23 04:32:02'),
('6139', '2', '5', 'Banking', 'New Bank', 'Add', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6141', '2', '5', 'Banking', 'New Bank', 'Delete', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6140', '2', '5', 'Banking', 'New Bank', 'Edit', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6138', '2', '5', 'Banking', 'New Bank', 'View', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6142', '2', '5', 'Banking', 'Post Dated Cheques (PDCs)', 'Coming Soon', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6143', '2', '5', 'Chart of Accounts', 'Chart of Accounts', 'Coming Soon', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('8142', '2', '5', 'Customer / Supplier', 'Leads', 'Add', '1', '2026-09-22 20:08:51', '2026-09-22 20:08:51'),
('8143', '2', '5', 'Customer / Supplier', 'Leads', 'Edit', '1', '2026-09-22 20:08:51', '2026-09-22 20:08:51'),
('8141', '2', '5', 'Customer / Supplier', 'Leads', 'View', '1', '2026-09-22 20:08:51', '2026-09-22 20:08:51'),
('3509', '2', '5', 'Customer / Supplier', 'New Customer', 'Add', '1', '2026-04-25 17:09:23', '2026-09-22 20:08:51'),
('3511', '2', '5', 'Customer / Supplier', 'New Customer', 'Delete', '0', '2026-04-25 17:09:23', '2026-09-05 17:55:25'),
('3510', '2', '5', 'Customer / Supplier', 'New Customer', 'Edit', '0', '2026-04-25 17:09:23', '2026-09-05 17:55:25'),
('3508', '2', '5', 'Customer / Supplier', 'New Customer', 'View', '1', '2026-04-25 17:09:23', '2026-09-22 20:08:51'),
('5598', '2', '5', 'Customer / Supplier', 'New Supplier', 'Add', '0', '2026-07-27 20:00:39', '2026-09-05 17:55:25'),
('5600', '2', '5', 'Customer / Supplier', 'New Supplier', 'Delete', '0', '2026-07-27 20:00:39', '2026-09-05 17:55:25'),
('5599', '2', '5', 'Customer / Supplier', 'New Supplier', 'Edit', '0', '2026-07-27 20:00:39', '2026-09-05 17:55:25'),
('5597', '2', '5', 'Customer / Supplier', 'New Supplier', 'View', '0', '2026-07-27 20:00:39', '2026-09-05 17:55:25'),
('3532', '2', '5', 'Dashboard', 'Dashboard', 'Coming Soon', '0', '2026-04-25 17:10:10', '2026-08-26 11:45:52'),
('5631', '2', '5', 'Financial Reports', 'Cash Flow', 'Coming Soon', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5627', '2', '5', 'Financial Reports', 'Customer Aging', 'Coming Soon', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5625', '2', '5', 'Financial Reports', 'Customer Ledger', 'Coming Soon', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('7189', '2', '5', 'Financial Reports', 'Customer Summary Division Report (CSDR)', 'Coming Soon', '1', '2026-09-02 15:40:09', '2026-09-02 15:40:09'),
('5632', '2', '5', 'Financial Reports', 'Financial Summary', 'Coming Soon', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5624', '2', '5', 'Financial Reports', 'General Ledger', 'Coming Soon', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5742', '2', '5', 'Financial Reports', 'Our Standing', 'Coming Soon', '1', '2026-08-11 02:07:29', '2026-08-11 02:07:29'),
('5629', '2', '5', 'Financial Reports', 'Recovery Sheet', 'Coming Soon', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5630', '2', '5', 'Financial Reports', 'Sales Officer-wise Recovery', 'Coming Soon', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5628', '2', '5', 'Financial Reports', 'Supplier Aging', 'Coming Soon', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5626', '2', '5', 'Financial Reports', 'Supplier Ledger', 'Coming Soon', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('2533', '2', '5', 'HRM', 'New Employee', 'Add', '1', '2026-02-27 11:48:55', '2026-02-27 11:48:55'),
('2535', '2', '5', 'HRM', 'New Employee', 'Delete', '1', '2026-02-27 11:48:55', '2026-02-27 11:48:55'),
('2534', '2', '5', 'HRM', 'New Employee', 'Edit', '1', '2026-02-27 11:48:55', '2026-02-27 11:48:55'),
('5633', '2', '5', 'HRM', 'New Employee', 'View', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('6119', '2', '5', 'Inventory', 'Inward Gatepass', 'Add', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6121', '2', '5', 'Inventory', 'Inward Gatepass', 'Delete', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6120', '2', '5', 'Inventory', 'Inward Gatepass', 'Edit', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6118', '2', '5', 'Inventory', 'Inward Gatepass', 'View', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('3525', '2', '5', 'Inventory', 'New Product', 'Add', '1', '2026-04-25 17:09:23', '2026-04-25 17:09:23'),
('3527', '2', '5', 'Inventory', 'New Product', 'Delete', '1', '2026-04-25 17:09:23', '2026-04-25 17:09:23'),
('3526', '2', '5', 'Inventory', 'New Product', 'Edit', '1', '2026-04-25 17:09:23', '2026-04-25 17:09:23'),
('3524', '2', '5', 'Inventory', 'New Product', 'View', '1', '2026-04-25 17:09:23', '2026-04-25 17:09:23'),
('6123', '2', '5', 'Inventory', 'Outward Gatepass', 'Add', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6125', '2', '5', 'Inventory', 'Outward Gatepass', 'Delete', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6124', '2', '5', 'Inventory', 'Outward Gatepass', 'Edit', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6122', '2', '5', 'Inventory', 'Outward Gatepass', 'View', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6110', '2', '5', 'Inventory', 'Stock Adjustment', 'Add', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6112', '2', '5', 'Inventory', 'Stock Adjustment', 'Delete', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6111', '2', '5', 'Inventory', 'Stock Adjustment', 'Edit', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6109', '2', '5', 'Inventory', 'Stock Adjustment', 'View', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6117', '2', '5', 'Inventory', 'Stock Position', 'Coming Soon', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6114', '2', '5', 'Inventory', 'Stock Transfer', 'Add', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6116', '2', '5', 'Inventory', 'Stock Transfer', 'Delete', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6115', '2', '5', 'Inventory', 'Stock Transfer', 'Edit', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6113', '2', '5', 'Inventory', 'Stock Transfer', 'View', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('5635', '2', '5', 'Master Setup', 'Territory Setup', 'Add', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5637', '2', '5', 'Master Setup', 'Territory Setup', 'Delete', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5636', '2', '5', 'Master Setup', 'Territory Setup', 'Edit', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5634', '2', '5', 'Master Setup', 'Territory Setup', 'View', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5608', '2', '5', 'Purchase', 'New Purchase', 'Add', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5610', '2', '5', 'Purchase', 'New Purchase', 'Delete', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5609', '2', '5', 'Purchase', 'New Purchase', 'Edit', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5607', '2', '5', 'Purchase', 'New Purchase', 'View', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5611', '2', '5', 'Purchase', 'Purchase Reports', 'Coming Soon', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5613', '2', '5', 'Purchase', 'Purchase Return', 'Add', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5615', '2', '5', 'Purchase', 'Purchase Return', 'Delete', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5614', '2', '5', 'Purchase', 'Purchase Return', 'Edit', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5612', '2', '5', 'Purchase', 'Purchase Return', 'View', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5809', '2', '5', 'Sale', 'Daily Product Sale', 'Coming Soon', '0', '2026-08-11 02:07:29', '2026-09-05 17:55:25'),
('5606', '2', '5', 'Sale', 'Daily Sale Report', 'Coming Soon', '0', '2026-07-27 20:00:39', '2026-09-05 17:55:25'),
('5602', '2', '5', 'Sale', 'Issue Delivery Challan', 'Add', '1', '2026-07-27 20:00:39', '2026-09-22 20:08:51'),
('5604', '2', '5', 'Sale', 'Issue Delivery Challan', 'Delete', '0', '2026-07-27 20:00:39', '2026-09-17 18:52:56'),
('5603', '2', '5', 'Sale', 'Issue Delivery Challan', 'Edit', '0', '2026-07-27 20:00:39', '2026-09-17 18:52:56'),
('5601', '2', '5', 'Sale', 'Issue Delivery Challan', 'View', '1', '2026-07-27 20:00:39', '2026-09-22 20:08:51'),
('6225', '2', '5', 'Sale', 'Med Sale Invoice', 'Add', '1', '2026-08-17 13:33:58', '2026-08-17 13:33:58'),
('6227', '2', '5', 'Sale', 'Med Sale Invoice', 'Delete', '0', '2026-08-17 13:33:58', '2026-08-26 11:45:52'),
('6226', '2', '5', 'Sale', 'Med Sale Invoice', 'Edit', '0', '2026-08-17 13:33:58', '2026-09-17 18:52:56'),
('6224', '2', '5', 'Sale', 'Med Sale Invoice', 'View', '1', '2026-08-17 13:33:58', '2026-08-17 13:33:58'),
('3517', '2', '5', 'Sale', 'POS Invoice', 'Add', '1', '2026-04-25 17:09:23', '2026-09-17 18:57:20'),
('3519', '2', '5', 'Sale', 'POS Invoice', 'Delete', '0', '2026-04-25 17:09:23', '2026-09-17 18:52:56'),
('3518', '2', '5', 'Sale', 'POS Invoice', 'Edit', '0', '2026-04-25 17:09:23', '2026-08-26 11:45:52'),
('3516', '2', '5', 'Sale', 'POS Invoice', 'View', '1', '2026-04-25 17:09:23', '2026-09-12 01:31:00'),
('3513', '2', '5', 'Sale', 'Sale Invoice', 'Add', '1', '2026-04-25 17:09:23', '2026-09-17 18:57:20'),
('3515', '2', '5', 'Sale', 'Sale Invoice', 'Delete', '0', '2026-04-25 17:09:23', '2026-08-26 11:45:52'),
('3514', '2', '5', 'Sale', 'Sale Invoice', 'Edit', '0', '2026-04-25 17:09:23', '2026-09-17 18:52:56'),
('3512', '2', '5', 'Sale', 'Sale Invoice', 'View', '1', '2026-04-25 17:09:23', '2026-04-25 17:09:23'),
('5605', '2', '5', 'Sale', 'Sale Reports', 'Coming Soon', '0', '2026-07-27 20:00:39', '2026-09-05 17:55:25'),
('3521', '2', '5', 'Sale', 'Sale Return', 'Add', '1', '2026-04-25 17:09:23', '2026-04-25 17:09:23'),
('3523', '2', '5', 'Sale', 'Sale Return', 'Delete', '1', '2026-04-25 17:09:23', '2026-04-25 17:09:23'),
('3522', '2', '5', 'Sale', 'Sale Return', 'Edit', '1', '2026-04-25 17:09:23', '2026-04-25 17:09:23'),
('3520', '2', '5', 'Sale', 'Sale Return', 'View', '1', '2026-04-25 17:09:23', '2026-04-25 17:09:23'),
('6106', '2', '5', 'Sale', 'Sale Tax Invoice', 'Add', '1', '2026-08-16 19:06:27', '2026-09-17 18:57:20'),
('6108', '2', '5', 'Sale', 'Sale Tax Invoice', 'Delete', '0', '2026-08-16 19:06:27', '2026-08-26 11:45:52'),
('6107', '2', '5', 'Sale', 'Sale Tax Invoice', 'Edit', '0', '2026-08-16 19:06:27', '2026-08-26 11:45:52'),
('6105', '2', '5', 'Sale', 'Sale Tax Invoice', 'View', '1', '2026-08-16 19:06:27', '2026-09-12 01:31:00'),
('7938', '2', '5', 'Sale', 'Traders Sale Invoice', 'Add', '1', '2026-09-17 18:57:20', '2026-09-17 18:57:20'),
('7937', '2', '5', 'Sale', 'Traders Sale Invoice', 'View', '1', '2026-09-17 18:57:20', '2026-09-17 18:57:20'),
('6135', '2', '5', 'Vouchers', 'Cash Opening', 'Add', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6137', '2', '5', 'Vouchers', 'Cash Opening', 'Delete', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6136', '2', '5', 'Vouchers', 'Cash Opening', 'Edit', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6134', '2', '5', 'Vouchers', 'Cash Opening', 'View', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('5621', '2', '5', 'Vouchers', 'Expense Voucher', 'Add', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5623', '2', '5', 'Vouchers', 'Expense Voucher', 'Delete', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5622', '2', '5', 'Vouchers', 'Expense Voucher', 'Edit', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5620', '2', '5', 'Vouchers', 'Expense Voucher', 'View', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('6131', '2', '5', 'Vouchers', 'Journal Entry', 'Add', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6133', '2', '5', 'Vouchers', 'Journal Entry', 'Delete', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6132', '2', '5', 'Vouchers', 'Journal Entry', 'Edit', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6130', '2', '5', 'Vouchers', 'Journal Entry', 'View', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('5617', '2', '5', 'Vouchers', 'Payment Voucher', 'Add', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('5619', '2', '5', 'Vouchers', 'Payment Voucher', 'Delete', '0', '2026-07-27 20:00:39', '2026-08-26 11:45:52'),
('5618', '2', '5', 'Vouchers', 'Payment Voucher', 'Edit', '0', '2026-07-27 20:00:39', '2026-08-26 11:45:52'),
('5616', '2', '5', 'Vouchers', 'Payment Voucher', 'View', '1', '2026-07-27 20:00:39', '2026-07-27 20:00:39'),
('3529', '2', '5', 'Vouchers', 'Receive Voucher', 'Add', '1', '2026-04-25 17:09:23', '2026-04-25 17:09:23'),
('3531', '2', '5', 'Vouchers', 'Receive Voucher', 'Delete', '0', '2026-04-25 17:09:23', '2026-08-26 11:45:52'),
('3530', '2', '5', 'Vouchers', 'Receive Voucher', 'Edit', '0', '2026-04-25 17:09:23', '2026-08-26 11:45:52'),
('3528', '2', '5', 'Vouchers', 'Receive Voucher', 'View', '1', '2026-04-25 17:09:23', '2026-04-25 17:09:23'),
('6127', '2', '5', 'Vouchers', 'Transfer Voucher', 'Add', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6129', '2', '5', 'Vouchers', 'Transfer Voucher', 'Delete', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6128', '2', '5', 'Vouchers', 'Transfer Voucher', 'Edit', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('6126', '2', '5', 'Vouchers', 'Transfer Voucher', 'View', '1', '2026-08-16 19:06:27', '2026-08-16 19:06:27'),
('7410', '2', '28', 'Customer / Supplier', 'Leads', 'Add', '1', '2026-09-07 17:48:49', '2026-09-07 17:48:49'),
('7411', '2', '28', 'Customer / Supplier', 'Leads', 'Edit', '1', '2026-09-07 17:48:49', '2026-09-07 17:48:49'),
('7407', '2', '28', 'Customer / Supplier', 'New Customer', 'Add', '1', '2026-09-07 17:48:49', '2026-09-07 17:48:49'),
('7406', '2', '28', 'Customer / Supplier', 'New Customer', 'View', '1', '2026-09-07 17:48:49', '2026-09-07 17:48:49'),
('7409', '2', '28', 'Customer / Supplier', 'New Supplier', 'Add', '0', '2026-09-07 17:48:49', '2026-09-12 01:20:03'),
('7408', '2', '28', 'Customer / Supplier', 'New Supplier', 'View', '0', '2026-09-07 17:48:49', '2026-09-12 01:20:03'),
('7565', '2', '28', 'Inventory', 'Stock Position', 'Coming Soon', '1', '2026-09-12 01:20:03', '2026-09-12 01:20:03'),
('7564', '2', '28', 'Sale', 'POS Invoice', 'Add', '1', '2026-09-12 01:20:03', '2026-09-12 01:20:03'),
('7563', '2', '28', 'Sale', 'POS Invoice', 'View', '1', '2026-09-12 01:20:03', '2026-09-12 01:20:03'),
('7562', '2', '28', 'Sale', 'Sale Invoice', 'Add', '1', '2026-09-12 01:20:03', '2026-09-12 01:20:03'),
('7561', '2', '28', 'Sale', 'Sale Invoice', 'View', '1', '2026-09-12 01:20:03', '2026-09-12 01:20:03'),
('7413', '2', '28', 'Sale', 'Sale Order', 'Add', '1', '2026-09-07 17:48:49', '2026-09-07 17:48:49'),
('7412', '2', '28', 'Sale', 'Sale Order', 'View', '1', '2026-09-07 17:48:49', '2026-09-07 17:48:49'),
('8146', '2', '30', 'Financial Reports', 'Both (Customer + Supplier) Ledger', 'Coming Soon', '1', '2026-09-22 20:10:04', '2026-09-22 20:10:04'),
('8147', '2', '30', 'Financial Reports', 'Customer Aging', 'Coming Soon', '1', '2026-09-22 20:10:04', '2026-09-22 20:10:04'),
('8144', '2', '30', 'Financial Reports', 'Customer Ledger', 'Coming Soon', '1', '2026-09-22 20:10:04', '2026-09-22 20:10:04'),
('8150', '2', '30', 'Financial Reports', 'Customer Summary Division Report (CSDR)', 'Coming Soon', '1', '2026-09-22 20:10:04', '2026-09-22 20:10:04'),
('8149', '2', '30', 'Financial Reports', 'Recovery Sheet', 'Coming Soon', '1', '2026-09-22 20:10:04', '2026-09-22 20:10:04'),
('8151', '2', '30', 'Financial Reports', 'Sales Officer-wise Recovery', 'Coming Soon', '1', '2026-09-22 20:10:04', '2026-09-22 20:10:04'),
('8148', '2', '30', 'Financial Reports', 'Supplier Aging', 'Coming Soon', '1', '2026-09-22 20:10:04', '2026-09-22 20:10:04'),
('8145', '2', '30', 'Financial Reports', 'Supplier Ledger', 'Coming Soon', '1', '2026-09-22 20:10:04', '2026-09-22 20:10:04');

-- Table: roles
INSERT INTO `roles` (`id`, `tenant_id`, `name`, `description`, `is_system_role`, `is_active`, `created_at`, `updated_at`) VALUES
('29', '2', 'User', 'User1', '0', '1', '2026-09-22 20:05:58', NULL),
('30', '2', 'User2', '', '0', '1', '2026-09-22 20:09:11', NULL);

-- Table: sale_invoice
INSERT INTO `sale_invoice` (`id`, `tenant_id`, `company_id`, `currency_id`, `bill_no`, `sale_date`, `customer_id`, `branch_id`, `container_id`, `previous_balance`, `total_bill`, `total_discount_percent`, `total_discount_amount`, `extra_discount_1_percent`, `extra_discount_1_amount`, `extra_discount_2_percent`, `extra_discount_2_amount`, `net_amount`, `remarks`, `signature`, `ship_to`, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`, `sale_officer_id`, `bilty_no`, `rate_type`, `brokery_rate_type`, `brokery_kg_basis`, `brokery_pct_mode`, `brokery_rate`, `brokery_amount`, `brokery_tax_percent`, `brokery_tax_amount`, `wt_charges`, `wt_charges_sign`, `freight`, `freight_sign`, `m_sukri`, `m_sukri_sign`, `broken_percent`, `broken_amount`, `broken_amount_sign`, `brokery_amount_sign`, `brokery_tax_amount_sign`, `bardana`, `bardana_sign`, `phone_charges`, `phone_charges_sign`, `filling_charges`, `filling_charges_sign`, `total_charges`, `transport_name`, `customer_phone`, `total_gst_percent`, `total_gst_amount`, `shipping_fees`, `kiraya`, `mazdoori`, `sale_order_id`, `rpo_no`, `truck_no`, `delivered_from`, `payment_term_id`, `sub_account_id`, `amount_returned`, `withholding_tax_percent`, `withholding_tax_amount`, `supplier_man_id`, `project_id`, `amount_paid_auto_fill`, `invoice_type`, `due_date`, `brand_id`, `engine_no`, `invoice_status`) VALUES
('18764', '2', '89', '2', 'SAL-0002', '2026-09-23', '13050', '191', NULL, '0.000', '600.000', '0.000', '0.000', '0.0000', '0.000', '0.0000', '0.000', '600.000', '', NULL, NULL, '69', '69', '2026-09-23 16:41:09', '2026-09-23 16:41:09', 'Posted', '245', NULL, NULL, NULL, 'net', '0', '0.00', '0.000', '0.00', '0.000', '0.00', '+', '0.00', '+', '0.00', '+', '0.0000', '0.0000', '+', '+', '+', '0.00', '+', '0.00', '+', '0.00', '+', '0.00', NULL, '03357541254', '0.00', '0.000', '0.000', '0.000', '0.000', NULL, NULL, NULL, NULL, NULL, NULL, '0.000', '0.000', '0.000', NULL, NULL, 'no', 'Credit', NULL, NULL, NULL, 'pending'),
('18769', '2', '89', '2', 'SAL-0003', '2026-09-23', '13053', '191', NULL, '0.000', '11749.000', '0.000', '0.000', '0.0000', '0.000', '0.0000', '0.000', '11749.000', '', NULL, NULL, '69', '69', '2026-09-23 19:48:30', '2026-09-23 19:48:30', 'Posted', '245', NULL, NULL, NULL, 'net', '0', '0.00', '0.000', '0.00', '0.000', '0.00', '+', '0.00', '+', '0.00', '+', '0.0000', '0.0000', '+', '+', '+', '0.00', '+', '0.00', '+', '0.00', '+', '0.00', NULL, '03214521452', '0.00', '0.000', '0.000', '0.000', '0.000', NULL, NULL, NULL, NULL, NULL, NULL, '0.000', '0.000', '0.000', NULL, NULL, 'no', 'Credit', NULL, NULL, NULL, 'pending'),
('18770', '2', '89', '2', 'SAL-0004', '2026-09-23', '13053', '191', NULL, '11749.000', '2010.000', '0.000', '0.000', '0.0000', '0.000', '0.0000', '0.000', '2010.000', '', NULL, NULL, '69', '69', '2026-09-23 20:14:28', '2026-09-23 20:14:28', 'Posted', '245', NULL, NULL, NULL, 'net', '0', '0.00', '0.000', '0.00', '0.000', '0.00', '+', '0.00', '+', '0.00', '+', '0.0000', '0.0000', '+', '+', '+', '0.00', '+', '0.00', '+', '0.00', '+', '0.00', NULL, NULL, '0.00', '0.000', '0.000', '0.000', '0.000', NULL, NULL, NULL, NULL, NULL, NULL, '0.000', '0.000', '0.000', NULL, NULL, 'no', 'Credit', NULL, NULL, NULL, 'pending'),
('18771', '2', '89', '2', 'SAL-0005', '2026-09-24', '13054', '191', NULL, '4000.000', '392.000', '0.000', '0.000', '0.0000', '0.000', '0.0000', '0.000', '392.000', '', NULL, NULL, '69', '69', '2026-09-24 04:15:00', '2026-09-24 04:15:00', 'Posted', '246', NULL, NULL, NULL, 'net', '0', '0.00', '0.000', '0.00', '0.000', '0.00', '+', '0.00', '+', '0.00', '+', '0.0000', '0.0000', '+', '+', '+', '0.00', '+', '0.00', '+', '0.00', '+', '0.00', NULL, '03357854785', '0.00', '0.000', '0.000', '0.000', '0.000', NULL, NULL, NULL, NULL, NULL, NULL, '0.000', '0.000', '0.000', NULL, NULL, 'no', 'Credit', NULL, NULL, NULL, 'pending'),
('18877', '2', '89', '2', 'SAL-0006', '2026-09-24', '13050', '191', NULL, '500.000', '790.000', '0.000', '0.000', '0.0000', '0.000', '0.0000', '0.000', '790.000', '', NULL, NULL, '69', '69', '2026-09-24 11:55:27', '2026-09-24 11:55:27', 'Posted', '245', NULL, NULL, NULL, 'net', '0', '0.00', '0.000', '0.00', '0.000', '0.00', '+', '0.00', '+', '0.00', '+', '0.0000', '0.0000', '+', '+', '+', '0.00', '+', '0.00', '+', '0.00', '+', '0.00', NULL, '03357541254', '0.00', '0.000', '0.000', '0.000', '0.000', NULL, NULL, NULL, NULL, NULL, NULL, '0.000', '0.000', '0.000', NULL, NULL, 'no', 'Credit', NULL, NULL, NULL, 'pending'),
('18892', '2', '89', '2', 'SAL-0007', '2026-09-24', '13053', '191', NULL, '13322.500', '389.940', '0.000', '0.000', '0.0000', '0.000', '0.0000', '0.000', '389.940', '', NULL, NULL, '69', '69', '2026-09-24 13:05:22', '2026-09-24 13:05:22', 'Posted', '245', NULL, NULL, NULL, 'net', '0', '0.00', '0.000', '0.00', '0.000', '0.00', '+', '0.00', '+', '0.00', '+', '0.0000', '0.0000', '+', '+', '+', '0.00', '+', '0.00', '+', '0.00', '+', '0.00', NULL, NULL, '0.00', '0.000', '0.000', '0.000', '0.000', NULL, NULL, NULL, NULL, NULL, NULL, '0.000', '0.000', '0.000', NULL, NULL, 'no', 'Credit', NULL, NULL, NULL, 'pending');

-- Table: sale_invoice_items
INSERT INTO `sale_invoice_items` (`id`, `tenant_id`, `sale_invoice_id`, `product_id`, `uom_id`, `batch_no`, `batch_expiry_date`, `bag`, `total_kg`, `cut_kg_percent`, `cut_kg`, `al_kg_percent`, `al_kg`, `net_kg`, `al_rate_cut`, `net_rate`, `quantity`, `sale_price`, `gross_amount`, `discount_percent`, `discount_amount`, `net_amount`, `created_by`, `updated_by`, `created_at`, `updated_at`, `trade_offer_percent`, `trade_offer_amount`, `scheme`, `size`, `gst_percent`, `gst_amount`, `tax_percent`, `tax_amount`, `tax_type`, `foc_quantity`, `parent_row_id`, `carton`, `dozen`, `piece`, `chassis_no`, `motor_no`, `colour`, `engine_no`, `expiry_date`, `extra_discount_amount`) VALUES
('53760', '2', '18764', '7900', '9', NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '3.000', '200.000', '600.000', '0.00', '0.000', '600.000', '69', '69', '2026-09-23 16:41:09', '2026-09-23 16:41:09', '0.00', '0.000', 'sale_on_tp', NULL, '0.00', '0.000', '0.00', '0.000', 'standard_gst', '0', NULL, '0', '0', '3', NULL, NULL, NULL, NULL, NULL, '0.000'),
('53770', '2', '18769', '7901', '9', NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2.000', '450.000', '11700.000', '3.00', '351.000', '11349.000', '69', '69', '2026-09-23 19:48:30', '2026-09-23 19:48:30', '0.00', '0.000', 'given', NULL, '0.00', '0.000', '0.00', '0.000', 'standard_gst', '1', NULL, '0', '0', '2', NULL, NULL, NULL, NULL, NULL, '0.000'),
('53771', '2', '18769', '7901', '16', NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '1.000', '450.000', '0.000', '0.00', '0.000', '0.000', '69', '69', '2026-09-23 19:48:30', '2026-09-23 19:48:30', '0.00', '0.000', 'given', NULL, '0.00', '0.000', '0.00', '0.000', 'standard_gst', '0', NULL, '1', '0', '0', NULL, NULL, NULL, NULL, NULL, '0.000'),
('53772', '2', '18769', '7909', '9', NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2.000', '200.000', '400.000', '0.00', '0.000', '400.000', '69', '69', '2026-09-23 19:48:30', '2026-09-23 19:48:30', '0.00', '0.000', 'sale_on_tp', NULL, '0.00', '0.000', '0.00', '0.000', 'standard_gst', '0', NULL, '0', '0', '2', NULL, NULL, NULL, NULL, NULL, '0.000'),
('53773', '2', '18770', '7909', '9', NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '10.000', '200.000', '2000.000', '0.00', '0.000', '2010.000', '69', '69', '2026-09-23 20:14:28', '2026-09-23 20:14:28', '0.00', '0.000', 'sale_on_tp', NULL, '0.00', '0.000', '0.50', '10.000', 'standard_gst', '0', NULL, '0', '0', '0', NULL, NULL, NULL, NULL, NULL, '0.000'),
('53774', '2', '18771', '7900', '9', NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2.000', '200.000', '400.000', '2.00', '8.000', '392.000', '69', '69', '2026-09-24 04:15:00', '2026-09-24 04:15:00', '0.00', '0.000', 'sale_on_tp', NULL, '0.00', '0.000', '0.00', '0.000', 'standard_gst', '0', NULL, '0', '0', '2', NULL, NULL, NULL, NULL, NULL, '0.000'),
('54124', '2', '18877', '7900', '9', NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '3.000', '200.000', '600.000', '1.00', '6.000', '594.000', '69', '69', '2026-09-24 11:55:27', '2026-09-24 11:55:27', '0.00', '0.000', 'sale_on_tp', NULL, '0.00', '0.000', '0.00', '0.000', 'standard_gst', '0', NULL, '0', '0', '3', NULL, NULL, NULL, NULL, NULL, '0.000'),
('54125', '2', '18877', '7909', '9', NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '1.000', '200.000', '200.000', '2.00', '4.000', '196.000', '69', '69', '2026-09-24 11:55:27', '2026-09-24 11:55:27', '0.00', '0.000', 'sale_on_tp', NULL, '0.00', '0.000', '2.50', '5.000', 'standard_gst', '0', NULL, '0', '0', '1', NULL, NULL, NULL, NULL, NULL, '0.000'),
('54211', '2', '18892', '7909', '9', NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '2.000', '200.000', '400.000', '3.00', '12.000', '389.940', '69', '69', '2026-09-24 13:05:22', '2026-09-24 13:05:22', '0.00', '0.000', 'sale_on_tp', NULL, '0.00', '0.000', '0.50', '1.940', 'standard_gst', '0', NULL, '0', '0', '2', NULL, NULL, NULL, NULL, NULL, '0.000');

-- Table: sale_return
INSERT INTO `sale_return` (`id`, `tenant_id`, `company_id`, `currency_id`, `bill_no`, `sale_date`, `customer_id`, `customer_phone`, `branch_id`, `previous_balance`, `total_bill`, `total_discount_percent`, `total_discount_amount`, `extra_discount_1_amount`, `extra_discount_2_amount`, `net_amount`, `remarks`, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`, `sale_officer_id`, `total_gst_percent`, `total_gst_amount`, `shipping_fees`, `sale_invoice_no`, `amount_refunded`, `payment_method`, `bank_account_id`, `sub_account_id`, `supplier_man_id`, `project_id`) VALUES
('1324', '2', '89', '2', 'SR-0001', '2026-09-24', '13053', '03214521452', '191', '0.00', '436.50', '0.00', '0.00', '0.0000', '0.0000', '436.50', NULL, '69', '69', '2026-09-24 05:00:37', '2026-09-24 05:00:37', 'Posted', '245', '0.00', '0.00', '0.00', '18769', '0.00', 'Cash', NULL, NULL, NULL, NULL);

-- Table: sale_return_items
INSERT INTO `sale_return_items` (`id`, `tenant_id`, `sale_invoice_id`, `product_id`, `uom_id`, `batch_no`, `expiry_date`, `quantity`, `sale_price`, `gross_amount`, `discount_percent`, `discount_amount`, `net_amount`, `created_by`, `updated_by`, `created_at`, `updated_at`, `trade_offer_percent`, `trade_offer_amount`, `gst_percent`, `gst_amount`, `tax_percent`, `tax_amount`, `tax_regime_id`, `tax_rate_id`, `tax_type`, `tax_name`, `scheme`, `parent_row_id`, `foc_quantity`, `stock_status`) VALUES
('2558', '2', '1324', '7901', '9', NULL, NULL, '1.000', '450.0000', '450.00', '3.00', '13.50', '436.50', '69', '69', '2026-09-24 05:00:37', '2026-09-24 05:00:37', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', NULL, NULL, 'standard_gst', NULL, 'sale_on_tp', NULL, '1', 'sellable');

-- Table: stock_ledger
INSERT INTO `stock_ledger` (`id`, `tenant_id`, `account_id`, `branch_id`, `product_id`, `reference_table`, `reference_id`, `stock_status`, `qty_in`, `qty_out`, `unit_cost`, `batch_no`, `batch_expiry_date`, `unit_id`, `transaction_type`, `transaction_date`, `created_at`, `vendor_id`, `expiry_date`) VALUES
('140831', '2', '33', '191', '7900', 'stock_opening', '5500', 'sellable', '20.000', '0.000', '100.0000', NULL, NULL, '9', 'Opening Stock', '2026-09-23', '2026-09-23 10:10:30', NULL, NULL),
('140833', '2', '33', '191', '7901', 'stock_opening', '5501', 'sellable', '3.000', '0.000', '300.0000', NULL, NULL, '16', 'Opening Stock', '2026-09-23', '2026-09-23 10:17:32', NULL, NULL),
('140834', '2', '33', '191', '7901', 'stock_opening', '5502', 'sellable', '3.000', '0.000', '300.0000', NULL, NULL, '10', 'Opening Stock', '2026-09-23', '2026-09-23 10:17:32', NULL, NULL),
('140835', '2', '33', '191', '7901', 'stock_opening', '5503', 'sellable', '11.000', '0.000', '300.0000', NULL, NULL, '9', 'Opening Stock', '2026-09-23', '2026-09-23 10:17:32', NULL, NULL),
('140929', '2', '33', '191', '7900', 'purchase_invoice', '1957', 'sellable', '20.000', '0.000', '100.0000', NULL, NULL, '9', 'Purchase Invoice', '2026-09-23', '2026-09-23 12:33:08', NULL, '2026-09-23'),
('141576', '2', '33', '191', '7900', 'sale_invoice', '18764', 'sellable', '0.000', '3.000', '100.0000', NULL, NULL, '9', 'Sale Invoice', '2026-09-23', '2026-09-23 16:41:09', NULL, NULL),
('141586', '2', '33', '191', '7901', 'sale_invoice', '18769', 'sellable', '0.000', '2.000', '300.0000', NULL, NULL, '9', 'Sale Invoice', '2026-09-23', '2026-09-23 19:48:30', NULL, NULL),
('141587', '2', '33', '191', '7901', 'sale_invoice', '18769', 'sellable', '0.000', '1.000', '0.0000', NULL, NULL, '9', 'Sale Invoice - FOC', '2026-09-23', '2026-09-23 19:48:30', NULL, NULL),
('141588', '2', '33', '191', '7901', 'sale_invoice', '18769', 'sellable', '0.000', '1.000', '300.0000', NULL, NULL, '16', 'Sale Invoice', '2026-09-23', '2026-09-23 19:48:30', NULL, NULL),
('141589', '2', '33', '191', '7909', 'sale_invoice', '18769', 'sellable', '0.000', '2.000', '100.0000', NULL, NULL, '9', 'Sale Invoice', '2026-09-23', '2026-09-23 19:48:30', NULL, NULL),
('141590', '2', '33', '191', '7909', 'stock_opening', '5511', 'sellable', '40.000', '0.000', '100.0000', NULL, NULL, '9', 'Opening Stock', '2026-09-23', '2026-09-23 20:09:13', NULL, NULL),
('141591', '2', '33', '191', '7909', 'sale_invoice', '18770', 'sellable', '0.000', '10.000', '100.0000', NULL, NULL, '9', 'Sale Invoice', '2026-09-23', '2026-09-23 20:14:28', NULL, NULL),
('141595', '2', '33', '191', '7900', 'sale_invoice', '18771', 'sellable', '0.000', '2.000', '100.0000', NULL, NULL, '9', 'Sale Invoice', '2026-09-24', '2026-09-24 04:15:00', NULL, NULL),
('141604', '2', '33', '191', '7901', 'purchase_invoice', '1969', 'sellable', '5.000', '0.000', '300.0000', NULL, NULL, '16', 'Purchase Invoice', '2026-09-03', '2026-09-24 04:44:40', NULL, '2026-09-24'),
('141605', '2', '33', '191', '7900', 'purchase_invoice', '1969', 'sellable', '15.000', '0.000', '100.0000', NULL, NULL, '9', 'Purchase Invoice', '2026-09-03', '2026-09-24 04:44:40', NULL, '2026-09-24'),
('141621', '2', '33', '191', '7901', 'sale_return', '1324', 'sellable', '1.000', '0.000', '450.0000', NULL, NULL, '9', 'Sale Return', '2026-09-24', '2026-09-24 05:00:37', NULL, NULL),
('141622', '2', '33', '191', '7901', 'sale_return', '1324', 'sellable', '1.000', '0.000', '0.0000', NULL, NULL, '9', 'Sale Return - FOC', '2026-09-24', '2026-09-24 05:00:37', NULL, NULL),
('141976', '2', '115', '191', '7912', 'stock_opening', '5513', 'sellable', '150.000', '0.000', '60.0000', NULL, NULL, '12', 'Opening Stock', '2026-09-24', '2026-09-24 08:24:46', NULL, NULL),
('141983', '2', '115', '191', '7913', 'stock_opening', '5515', 'sellable', '200.000', '0.000', '90.0000', NULL, NULL, '9', 'Opening Stock', '2026-09-24', '2026-09-24 08:28:20', NULL, NULL),
('141986', '2', '115', '191', '7912', 'work_in_progress', '3688', 'sellable', '0.000', '30.000', '60.0000', NULL, NULL, '12', 'WIP_ISSUE', '2026-09-24', '2026-09-24 08:32:36', NULL, NULL),
('141987', '2', '116', '191', '7912', 'work_in_progress', '3688', 'sellable', '30.000', '0.000', '60.0000', NULL, NULL, '12', 'WIP_RECEIVE', '2026-09-24', '2026-09-24 08:32:36', NULL, NULL),
('141988', '2', '115', '191', '7913', 'work_in_progress', '3688', 'sellable', '0.000', '20.000', '90.0000', NULL, NULL, '9', 'WIP_ISSUE', '2026-09-24', '2026-09-24 08:32:36', NULL, NULL),
('141989', '2', '116', '191', '7913', 'work_in_progress', '3688', 'sellable', '20.000', '0.000', '90.0000', NULL, NULL, '9', 'WIP_RECEIVE', '2026-09-24', '2026-09-24 08:32:36', NULL, NULL),
('141996', '2', '116', '191', '7912', 'production_completions', '3631', 'sellable', '0.000', '30.000', '60.0000', NULL, NULL, '12', 'WIP_OUT', '2026-09-24', '2026-09-24 08:35:29', NULL, NULL),
('141997', '2', '116', '191', '7913', 'production_completions', '3631', 'sellable', '0.000', '20.000', '90.0000', NULL, NULL, '9', 'WIP_OUT', '2026-09-24', '2026-09-24 08:35:29', NULL, NULL),
('141998', '2', '117', '191', '7914', 'production_completions', '3631', 'sellable', '10.000', '0.000', '482.0000', NULL, NULL, '9', 'PRODUCTION_RECEIVE', '2026-09-24', '2026-09-24 08:35:29', NULL, NULL),
('142161', '2', '33', '191', '7900', 'sale_invoice', '18877', 'sellable', '0.000', '3.000', '100.0000', NULL, NULL, '9', 'Sale Invoice', '2026-09-24', '2026-09-24 11:55:27', NULL, NULL),
('142162', '2', '33', '191', '7909', 'sale_invoice', '18877', 'sellable', '0.000', '1.000', '100.0000', NULL, NULL, '9', 'Sale Invoice', '2026-09-24', '2026-09-24 11:55:27', NULL, NULL),
('142568', '2', '33', '191', '7909', 'sale_invoice', '18892', 'sellable', '0.000', '2.000', '100.0000', NULL, NULL, '9', 'Sale Invoice', '2026-09-24', '2026-09-24 13:05:22', NULL, NULL),
('142598', '2', '115', '191', '7912', 'purchase_invoice', '1985', 'sellable', '15.000', '0.000', '60.0000', NULL, NULL, '12', 'Purchase Invoice', '2026-09-24', '2026-09-24 13:28:57', NULL, '2026-09-24');

-- Table: stock_opening
INSERT INTO `stock_opening` (`id`, `product_id`, `tenant_id`, `branch_id`, `opening_qty`, `opening_price`, `batch_no`, `unit_id`, `created_at`, `vendor_id`, `expiry_date`, `opening_price_entry_value`) VALUES
('5500', '7900', '2', '191', '20.000', '100.0000', NULL, '9', '2026-09-23 10:10:30', NULL, NULL, NULL),
('5501', '7901', '2', '191', '3.000', '300.0000', NULL, '16', '2026-09-23 10:17:32', NULL, NULL, NULL),
('5502', '7901', '2', '191', '3.000', '300.0000', NULL, '10', '2026-09-23 10:17:32', NULL, NULL, NULL),
('5503', '7901', '2', '191', '11.000', '300.0000', NULL, '9', '2026-09-23 10:17:32', NULL, NULL, NULL),
('5511', '7909', '2', '191', '40.000', '100.0000', NULL, '9', '2026-09-23 20:09:13', NULL, NULL, NULL),
('5513', '7912', '2', '191', '150.000', '60.0000', NULL, '12', '2026-09-24 08:24:46', NULL, NULL, NULL),
('5515', '7913', '2', '191', '200.000', '90.0000', NULL, '9', '2026-09-24 08:28:20', NULL, NULL, NULL);

-- Table: sub_accounts
INSERT INTO `sub_accounts` (`id`, `tenant_id`, `account_head_id`, `name`, `parent_id`) VALUES
('107', '2', '5', 'Freight Expense', NULL),
('112', '2', '1', 'Fixed Assets', NULL),
('132', '2', '1', 'Intangible Asset', NULL),
('133', '2', '1', 'Goodwill', '132');

-- Table: supplier_categories
INSERT INTO `supplier_categories` (`id`, `tenant_id`, `category_name`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
('15', '2', 'Tissue', '69', '69', '2026-09-23 06:13:32', '2026-09-23 06:13:32'),
('16', '2', 'General', '69', '69', '2026-09-23 06:13:46', '2026-09-23 06:13:46');

-- Table: suppliers
INSERT INTO `suppliers` (`id`, `tenant_id`, `company_id`, `salesman_id`, `supplier_category_id`, `project_id`, `supplier_code`, `supplier_type`, `supplier_name`, `brand_name`, `address`, `city`, `primary_phone`, `secondary_phone`, `identity_card_no`, `email`, `country_id`, `region_id`, `city_id`, `city_zone_id`, `area_id`, `opening_debit_amount`, `opening_credit_amount`, `ait_percent`, `current_balance`, `is_blacklisted`, `is_both`, `linked_customer_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`, `party_type`, `credit_days`) VALUES
('461', '2', '89', NULL, NULL, NULL, 'SUPP-00001', 'Manufacturer', 'Able', NULL, 'A-44, Street 3, DHA 4', 'Karachi', '03349696859', NULL, NULL, 'a@gmail.com', NULL, NULL, NULL, NULL, NULL, '0.00', '100000.00', '0.00', '-100000.00', '0', '0', NULL, 'ACTIVE', '2026-09-23 06:19:52', '2026-09-23 06:19:52', '69', '69', 'unregistered', '15'),
('462', '2', '89', NULL, NULL, NULL, 'SUPP-00002', 'Manufacturer', 'Shahid & Sons', NULL, '55C Lakshmi Chock', 'Lahore', '03324124125', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0', '0', NULL, 'ACTIVE', '2026-09-23 06:21:50', '2026-09-23 14:31:50', '69', '69', NULL, '25'),
('465', '2', '89', NULL, NULL, NULL, 'SUPP-00003', 'Manufacturer', 'Khan Pvt Ltd', 'Wazir', 'Lane 4 Street 2 Behind LFC Multan', 'Multan', '03369696854', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0.00', '150000.00', '0.00', '-150000.00', '0', '0', NULL, 'ACTIVE', '2026-09-23 14:41:30', '2026-09-23 14:41:30', '69', '69', 'registered_company', '10');

-- Table: tax_rates
INSERT INTO `tax_rates` (`id`, `tenant_id`, `tax_authority`, `tax_type`, `transaction_type`, `legal_section`, `finance_act_year`, `tax_name`, `applicable_to`, `party_type`, `is_filer`, `rate_percentage`, `threshold_min`, `threshold_max`, `tax_regime_id`, `is_adjustable`, `is_refundable`, `is_final_tax`, `deducted_by`, `debit_account_id`, `credit_account_id`, `description`, `is_active`, `effective_from`, `effective_to`, `currency`, `created_at`, `created_by`, `updated_at`, `updated_by`, `customer_type_id`) VALUES
('57', '2', 'FBR', 'advance_tax', 'sale', '', '2024', 'AIT - Filer', 'all', 'registered_company', '1', '0.5000', NULL, NULL, '34', '1', '1', '0', 'seller', NULL, NULL, '', '1', '2026-09-23', NULL, 'PKR', '2026-09-23 04:33:00', '69', NULL, NULL, NULL),
('58', '2', 'FBR', 'advance_tax', 'sale', '', '2024', 'AIT - Non-Filer', 'all', 'unregistered', '0', '2.5000', NULL, NULL, '34', '1', '1', '0', 'seller', NULL, NULL, '', '1', '2026-09-23', NULL, 'PKR', '2026-09-23 04:34:00', '69', NULL, NULL, NULL),
('59', '2', 'FBR', 'sales_tax', 'sale', '', '2024', 'Sales Tax Sale', 'all', 'registered_company', '0', '18.0000', NULL, NULL, '35', '1', '1', '0', 'seller', NULL, NULL, '', '1', '2026-09-23', NULL, 'PKR', '2026-09-23 04:35:20', '69', NULL, NULL, NULL),
('60', '2', 'FBR', 'sales_tax', 'purchase', '', '2024', 'Sales Tax Purchase', 'all', 'registered_company', '0', '18.0000', NULL, NULL, '35', '1', '1', '0', 'buyer', NULL, NULL, '', '1', '2026-09-23', NULL, 'PKR', '2026-09-23 04:36:27', '69', NULL, NULL, NULL);

-- Table: tax_regimes
INSERT INTO `tax_regimes` (`id`, `tenant_id`, `regime_code`, `country_id`, `regime_name`, `legal_reference`, `finance_act_year`, `tax_authority`, `tax_base`, `formula_template`, `formula_steps`, `formula_variables`, `is_tax_inclusive`, `is_single_stage`, `applies_at_stage`, `downstream_exempt`, `is_adjustable`, `is_refundable`, `is_final_tax`, `description`, `is_active`, `effective_from`, `effective_to`, `created_at`, `created_by`, `updated_at`, `updated_by`, `application_level`) VALUES
('34', '2', 'AIT', '75', 'Advance Income Tax', '', '2024', 'FBR', 'trade_price', '{trade_price} * {rate} / 100', '[]', '[]', '0', '0', 'all', '0', '1', '0', '0', '', '1', '2026-09-23', NULL, '2026-09-23 04:30:33', '69', NULL, NULL, 'item'),
('35', '2', 'ST', '75', 'Sales Tax', '', '2024', 'FBR', 'value_excl_sales_tax', '{total_bill} - {Discount} * rate / 100', '[]', '[]', '0', '0', 'all', '0', '1', '0', '0', '', '1', '2026-09-23', NULL, '2026-09-23 04:31:34', '69', NULL, NULL, 'invoice');

-- Table: tenant_currencies
INSERT INTO `tenant_currencies` (`id`, `tenant_id`, `currency_id`, `is_base_currency`, `is_active`, `created_at`, `updated_at`, `created_by`) VALUES
('80', '2', '2', '1', '1', '2026-09-22 20:00:51', '2026-09-22 20:00:51', '69');

-- Table: user_branches
INSERT INTO `user_branches` (`id`, `tenant_id`, `user_id`, `branch_id`, `created_at`) VALUES
('155', '2', '69', '191', '2026-09-22 20:05:30'),
('156', '2', '69', '192', '2026-09-22 20:05:30'),
('157', '2', '147', '192', '2026-09-22 20:11:13'),
('158', '2', '146', '191', '2026-09-22 20:11:30');

-- Table: user_roles
INSERT INTO `user_roles` (`id`, `tenant_id`, `user_id`, `role_id`, `assigned_by`, `assigned_at`, `expires_at`, `is_active`) VALUES
('266', '2', '2', '1', '69', '2026-09-17 19:31:23', NULL, '1'),
('271', '2', '69', '1', '69', '2026-09-22 20:05:30', NULL, '1'),
('275', '2', '146', '29', '69', '2026-09-22 20:11:30', NULL, '1'),
('274', '2', '147', '30', '69', '2026-09-22 20:11:13', NULL, '1');

-- Table: users
INSERT INTO `users` (`id`, `tenant_id`, `full_name`, `email`, `password_hash`, `phone`, `profile_picture`, `language_code`, `timezone`, `is_active`, `is_email_verified`, `last_login_at`, `password_changed_at`, `created_at`, `updated_at`, `employee_id`) VALUES
('2', '2', 'Zahid Ghori', 'ghorisoft@gmail.com', '$2y$12$WPqTji66CXtm6jwmMk/dieQy//Hk8gc69ovgyoXnKxQiOXcnSVuuq', '(+92) 3468918711', NULL, 'en', 'GMT', '1', '1', '2026-09-17 19:41:21', NULL, '2025-12-06 19:22:23', '2026-09-17 19:41:21', NULL),
('69', '2', 'Zahid Ghori', 'zahid@unisensystems.com', '$2y$12$vq2usvGL/s/93GivdG2Wo.pVcmbk2CcACVvncu89tZQhEy5FAOguS', NULL, NULL, 'en', 'Asia/Karachi', '1', '0', '2026-09-24 13:29:52', NULL, '2026-05-30 16:54:13', '2026-09-24 13:29:52', NULL),
('146', '2', 'Asif', 'Branch1@gmail.com', '$2y$12$2HDFhnhi8EnbMQT1z/995eO3qhwd1UUVfwQAjPSWJwbg.ilLLFNQ2', '', NULL, 'en', 'Asia/Karachi', '1', '0', NULL, NULL, '2026-09-22 20:07:40', '2026-09-22 20:11:30', NULL),
('147', '2', 'Luqman', 'Branch2', '$2y$12$ZuUH0ckYgZ.k6bgyokCGhec2M1v1hUKdHr.EDDP0Ym5tHt433..2K', '', NULL, 'en', 'Asia/Karachi', '1', '0', NULL, NULL, '2026-09-22 20:10:55', '2026-09-22 20:11:13', NULL);

-- Table: work_in_progress
INSERT INTO `work_in_progress` (`id`, `tenant_id`, `production_order_id`, `branch_id`, `machine_id`, `issue_date`, `total_items`, `total_cost`, `created_by`, `created_at`, `wip_number`) VALUES
('3688', '2', '3812', '191', '11', '2026-09-24', '2', '3600.00', '69', '2026-09-24 08:32:36', 'WIP-0001');

-- Table: work_in_progress_items
INSERT INTO `work_in_progress_items` (`id`, `tenant_id`, `work_in_progress_id`, `material_id`, `required_qty`, `issued_qty`, `available_qty`, `issue_qty`, `uom_id`, `unit_cost`, `total_cost`) VALUES
('22310', '2', '3688', '7912', '100.00', '0.00', '150.00', '30.00', '12', '60.00', '1800.00'),
('22311', '2', '3688', '7913', '40.00', '0.00', '200.00', '20.00', '9', '90.00', '1800.00');
