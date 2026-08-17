<?php
require_once '../../../../includes/dashboard.php';
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    // Redirect to login if no user_id in session
    header('Location: ../../auth/login.html');
    exit();
}

// Get base currency symbol
require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'] ?? '$';

// Get user full name and employee_id
$userStmt = $pdo->prepare("SELECT full_name, employee_id FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch();
$user_full_name = $user['full_name'] ?? 'Unknown';
$user_employee_id = $user['employee_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - POS Invoice</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Light Theme Colors */
            --primary: #1F7BFF;
            --primary-hover: #1A6CDC;
            --primary-active: #1559B8;

            --surface-0: #FFFFFF;
            --surface-1: #F7F9FC;
            --surface-2: #EFF2F7;

            --heading: #0E1A2B;
            --body: #2F3B4C;
            --subtext: #6B7280;

            --border-default: #E1E6EE;
            --border-strong: #C9CFDA;

            --success: #2FBF71;
            --warning: #E8B23F;
            --error: #E34F4F;
            --stock-low: #FF6B6B;
            --stock-ok: #2FBF71;
            --stock-high: #4D96FF;
        }

        /* Counter Mode - Dark Theme with Bold, Large Fonts */
        /* Scoped to .invoice-container to not affect dashboard */
        body.counter-mode {
            background-color: #0f1419;
        }

        body.counter-mode .invoice-container {
            --primary: #00d4ff;
            --primary-hover: #00b8e6;
            --primary-active: #009cc7;
            --surface-0: #1a1f2e;
            --surface-1: #0f1419;
            --surface-2: #252b3b;
            --heading: #ffffff;
            --body: #e8eaed;
            --subtext: #b0b8c1;
            --border-default: #3a4556;
            --border-strong: #4a5568;
            --success: #00ff88;
            --warning: #ffd700;
            --error: #ff5555;
            --stock-low: #ff6b6b;
            --stock-ok: #00ff88;
            --stock-high: #00d4ff;
        }

        /* Counter Mode - Base Typography */
        body.counter-mode .invoice-container * {
            font-size: 15px;
            font-weight: 600;
        }

        /* Counter Mode - Headings */
        body.counter-mode .invoice-container h1,
        body.counter-mode .invoice-container h2,
        body.counter-mode .invoice-container h3,
        body.counter-mode .invoice-container h4,
        body.counter-mode .invoice-container h5,
        body.counter-mode .invoice-container h6 {
            color: var(--heading);
            font-weight: 800;
        }

        body.counter-mode .invoice-container h3 {
            font-size: 18px;
        }

        /* Counter Mode - Cards & Panels */
        body.counter-mode .invoice-container .invoice-header,
        body.counter-mode .invoice-container .stock-panel,
        body.counter-mode .invoice-container .customer-panel,
        body.counter-mode .invoice-container .items-container,
        body.counter-mode .invoice-container .summary-panel,
        body.counter-mode .invoice-container .payment-panel,
        body.counter-mode .invoice-container .quick-entry {
            background: var(--surface-0);
            border: 1px solid var(--border-default);
        }

        /* Counter Mode - Table Headers */
        body.counter-mode .invoice-container .items-header,
        body.counter-mode .invoice-container .items-footer {
            background: var(--surface-2);
            color: var(--heading);
        }

        body.counter-mode .invoice-container .col-header {
            font-size: 13px;
            font-weight: 800;
            color: var(--heading);
        }

        /* Counter Mode - Table Rows */
        body.counter-mode .invoice-container .item-row {
            border-bottom: 1px solid var(--border-default);
            color: var(--body);
        }

        body.counter-mode .invoice-container .item-row:hover {
            background: var(--surface-2);
        }

        body.counter-mode .invoice-container .item-row input {
            height: 32px;
            font-size: 14px;
            font-weight: 700;
            background: var(--surface-1);
            border: 1px solid var(--border-default);
            color: var(--body);
        }

        body.counter-mode .invoice-container .item-row .readonly {
            background: var(--surface-2);
            color: var(--heading);
        }

        /* Counter Mode - Labels */
        body.counter-mode .invoice-container .header-label,
        body.counter-mode .invoice-container .form-label,
        body.counter-mode .invoice-container .summary-label,
        body.counter-mode .invoice-container .stock-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--subtext);
        }

        /* Counter Mode - Values */
        body.counter-mode .invoice-container .header-value,
        body.counter-mode .invoice-container .summary-value {
            font-size: 16px;
            font-weight: 800;
            color: var(--heading);
        }

        body.counter-mode .invoice-container .invoice-no {
            font-size: 18px;
            font-weight: 900;
            color: var(--primary);
        }

        body.counter-mode .invoice-container .total-amount {
            font-size: 20px;
            font-weight: 900;
            color: var(--primary);
        }

        /* Counter Mode - Inputs & Selects */
        body.counter-mode .invoice-container input,
        body.counter-mode .invoice-container select {
            height: 38px;
            font-size: 15px;
            font-weight: 600;
            background: var(--surface-1);
            border: 1px solid var(--border-default);
            color: var(--body) !important;
        }

        body.counter-mode .invoice-container input:focus,
        body.counter-mode .invoice-container select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(0, 212, 255, 0.2);
        }

        body.counter-mode .invoice-container input::placeholder {
            color: var(--subtext);
        }

        /* Counter Mode - Buttons */
        body.counter-mode .invoice-container .btn {
            height: 38px;
            font-size: 14px;
            font-weight: 700;
        }

        body.counter-mode .invoice-container .btn-primary {
            background: var(--primary);
            color: var(--surface-0);
        }

        body.counter-mode .invoice-container .btn-primary:hover {
            background: var(--primary-hover);
        }

        body.counter-mode .invoice-container .btn-secondary {
            background: var(--surface-2);
            color: var(--body);
            border: 1px solid var(--border-default);
        }

        body.counter-mode .invoice-container .btn-secondary:hover {
            background: var(--surface-1);
        }

        body.counter-mode .invoice-container .btn-success {
            background: var(--success);
            color: var(--surface-0);
        }

        body.counter-mode .invoice-container .btn-danger {
            background: var(--error);
            color: white;
        }

        /* Counter Mode - Dropdowns (positioned outside container) */
        body.counter-mode #branchOptions,
        body.counter-mode #companyOptions,
        body.counter-mode #productSuggestions {
            background: #1a1f2e !important;
            border: 1px solid #3a4556 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4) !important;
        }

        body.counter-mode .suggestion-item,
        body.counter-mode .dropdown-option {
            background: #1a1f2e !important;
            color: #e8eaed !important;
            border-bottom: 1px solid #3a4556 !important;
            padding: 12px 14px !important;
            cursor: pointer;
            font-size: 15px !important;
            font-weight: 600 !important;
            line-height: 1.5 !important;
        }

        body.counter-mode .suggestion-item strong {
            color: #ffffff !important;
            font-size: 16px !important;
            font-weight: 700 !important;
        }

        body.counter-mode .suggestion-item small {
            color: #b0b8c1 !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            display: block !important;
            margin-top: 4px !important;
        }

        body.counter-mode .suggestion-item:hover,
        body.counter-mode .dropdown-option:hover {
            background: #252b3b !important;
        }

        body.counter-mode .suggestion-item:hover strong {
            color: #00d4ff !important;
        }

        body.counter-mode .suggestion-item:hover small {
            color: #e8eaed !important;
        }

        body.counter-mode .suggestion-item.selected,
        body.counter-mode .dropdown-option.selected {
            background: #00d4ff !important;
            color: #0f1419 !important;
            font-weight: 700 !important;
        }

        body.counter-mode .suggestion-item.selected strong,
        body.counter-mode .suggestion-item.selected small {
            color: #0f1419 !important;
        }

        /* Counter Mode - Branch Dropdown Options (direct div children) */
        body.counter-mode #branchOptions > div,
        body.counter-mode #companyOptions > div {
            background: #1a1f2e !important;
            color: #e8eaed !important;
            border-bottom: 1px solid #3a4556 !important;
            padding: 12px 14px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            line-height: 1.5 !important;
            cursor: pointer !important;
        }

        body.counter-mode #branchOptions > div:hover,
        body.counter-mode #companyOptions > div:hover {
            background: #252b3b !important;
            color: #ffffff !important;
        }

        body.counter-mode #branchOptions > div.selected,
        body.counter-mode #companyOptions > div.selected {
            background: #00d4ff !important;
            color: #0f1419 !important;
        }

        /* Light mode branch options */
        body:not(.counter-mode) #branchOptions > div,
        body:not(.counter-mode) #companyOptions > div {
            background: white !important;
            color: #2f3b4c !important;
        }

        body:not(.counter-mode) #branchOptions > div:hover,
        body:not(.counter-mode) #companyOptions > div:hover {
            background: #f7f9fc !important;
        }

        /* Counter Mode - Stock Items */
        body.counter-mode .invoice-container .stock-item {
            color: var(--body);
        }

        body.counter-mode .invoice-container .stock-name {
            color: var(--body);
        }

        body.counter-mode .invoice-container .stock-qty {
            font-weight: 700;
        }

        /* Counter Mode - Modals */
        body.counter-mode .modal-content {
            background: var(--surface-0);
            border: 1px solid var(--border-default);
        }

        body.counter-mode .modal-content h3 {
            color: var(--heading);
        }

        body.counter-mode .modal-content table {
            background: var(--surface-0);
        }

        body.counter-mode .modal-content thead tr {
            background: var(--surface-2);
        }

        body.counter-mode .modal-content th,
        body.counter-mode .modal-content td {
            color: var(--body);
            border-bottom: 1px solid var(--border-default);
        }

        body.counter-mode .modal-content th {
            color: var(--heading);
            font-weight: 700;
        }

        /* Counter Mode - Key Hint */
        body.counter-mode .key-hint {
            background: rgba(26, 31, 46, 0.95);
            color: var(--body);
            border: 1px solid var(--border-default);
        }

        body.counter-mode .key-hint strong {
            color: var(--primary);
        }

        /* Counter Mode - Scrollbar */
        body.counter-mode .invoice-container ::-webkit-scrollbar-track {
            background: var(--surface-2);
        }

        body.counter-mode .invoice-container ::-webkit-scrollbar-thumb {
            background: var(--border-strong);
        }

        body.counter-mode .invoice-container ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 13px;
        }

        body {
            background-color: var(--surface-1);
            color: var(--body);
            padding: 8px;
            overflow-x: hidden;
        }

        /* Ultra Compact Layout */
        .invoice-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 8px;
            max-height: calc(100vh - 20px);
        }

        /* Left Panel - Invoice Details & Quick Actions */
        .left-panel {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* Invoice Header - Super Compact */
        .invoice-header {
            background: white;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .header-item {
            display: flex;
            flex-direction: column;
        }

        .header-label {
            font-size: 11px;
            color: var(--subtext);
            font-weight: 500;
            margin-bottom: 2px;
        }

        .header-value {
            font-weight: 600;
            color: var(--heading);
            font-size: 13px;
        }

        .invoice-no {
            font-size: 14px;
            color: var(--primary);
            font-weight: 700;
        }

        /* Stock Status Panel */
        .stock-panel {
            background: white;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            max-height: 150px;
            overflow-y: auto;
        }

        .stock-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid var(--border-default);
        }

        .stock-title {
            font-weight: 600;
            color: var(--heading);
            font-size: 12px;
        }

        .stock-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .stock-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 3px 0;
        }

        .stock-name {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stock-qty {
            font-weight: 600;
            margin-left: 8px;
            min-width: 40px;
            text-align: right;
        }

        .stock-low {
            color: var(--stock-low);
        }

        .stock-ok {
            color: var(--stock-ok);
        }

        .stock-high {
            color: var(--stock-high);
        }

        /* Customer & Payment Panel */
        .customer-panel {
            background: white;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group-micro {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 11px;
            color: var(--subtext);
            font-weight: 500;
            margin-bottom: 2px;
        }

        input,
        select {
            height: 32px;
            padding: 0 8px;
            border: 1px solid var(--border-default);
            border-radius: 4px;
            background: white;
            font-size: 13px;
            width: 100%;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(31, 123, 255, 0.1);
        }

        /* Right Panel - Items & Summary */
        .right-panel {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* Items Table - Ultra Compact */
        .items-container {
            background: white;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .items-header {
            padding: 8px 12px;
            background: var(--surface-1);
            border-bottom: 1px solid var(--border-default);
            display: grid;
            grid-template-columns: 30px 120px 60px 60px 70px 70px 70px 70px 80px 40px;
            gap: 4px;
            align-items: center;
        }

        .items-body {
            overflow-y: auto;
            max-height: calc(100vh - 350px);
            flex: 1;
            min-height: 200px;
        }

        .item-row {
            padding: 6px 12px;
            border-bottom: 1px solid var(--border-default);
            display: grid;
            grid-template-columns: 30px 120px 60px 60px 70px 70px 70px 70px 80px 40px;
            gap: 4px;
            align-items: center;
            transition: background 0.2s;
        }

        .item-row:hover {
            background: #F0F6FF;
        }

        .item-row input {
            height: 28px;
            padding: 0 4px;
            border: 1px solid var(--border-default);
            border-radius: 3px;
            font-size: 12px;
        }

        .item-row .readonly {
            background: var(--surface-2);
            border: none;
            padding: 4px;
            text-align: right;
            font-weight: 500;
        }

        .col-header {
            font-weight: 600;
            color: var(--heading);
            font-size: 11px;
            white-space: nowrap;
        }

        /* Quick Product Entry */
        .quick-entry {
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: grid;
            grid-template-columns: 1fr 80px 80px 40px;
            gap: 8px;
            align-items: end;
        }

        /* Invoice Summary - Micro */
        .summary-panel {
            background: white;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }

        .summary-item {
            display: flex;
            flex-direction: column;
        }

        .summary-label {
            font-size: 11px;
            color: var(--subtext);
            margin-bottom: 2px;
        }

        .summary-value {
            font-weight: 600;
            color: var(--heading);
        }

        .total-amount {
            color: var(--primary);
            font-size: 14px;
        }

        /* Payment Section */
        .payment-panel {
            background: white;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            align-items: end;
        }

        /* Action Buttons - Ultra Compact */
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 4px;
        }

        .btn {
            height: 32px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            padding: 0 8px;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-secondary {
            background: var(--surface-2);
            color: var(--heading);
            border: 1px solid var(--border-default);
        }

        .btn-secondary:hover {
            background: #E4E8EF;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn-micro {
            height: 24px;
            width: 24px;
            padding: 0;
            border-radius: 3px;
        }

        /* Status Indicators */
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 4px;
        }

        .status-low {
            background: var(--stock-low);
        }

        .status-ok {
            background: var(--stock-ok);
        }

        .status-high {
            background: var(--stock-high);
        }

        /* Keyboard Shortcut Hints */
        .key-hint {
            position: fixed;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 11px;
            z-index: 1000;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .invoice-container {
                grid-template-columns: 280px 1fr;
            }

            .items-header,
            .item-row {
                grid-template-columns: 30px 100px 50px 50px 60px 60px 60px 60px 70px 40px;
            }

            .action-buttons {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 900px) {
            .invoice-container {
                grid-template-columns: 1fr;
                max-height: none;
            }

            .items-body {
                max-height: 300px;
            }

            .summary-panel,
            .payment-panel {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {

            .items-header,
            .item-row {
                grid-template-columns: 30px 80px 45px 45px 55px 55px 55px 55px 65px 35px;
                font-size: 11px;
            }

            .col-header {
                font-size: 10px;
            }

            .action-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Utility Classes */
        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: 600;
        }

        .w-full {
            width: 100%;
        }

        .hidden {
            display: none;
        }

        .suggestion-item.selected {
            background: var(--surface-1) !important;
        }
        
        .suggestion-item {
            background: white;
        }
        
        .selected-draft {
            background: var(--surface-1) !important;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--surface-2);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-default);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--border-strong);
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        <!-- Left Panel -->
        <div class="left-panel">
            <!-- Theme Toggle -->
            <div style="background: white; padding: 8px 12px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); margin-bottom: 8px;">
                <button class="btn btn-secondary" id="themeToggleBtn" style="width: 100%; height: 32px;">
                    <i class="fas fa-adjust"></i> <span id="themeToggleText">Dark Mode</span>
                </button>
            </div>

            <!-- Invoice Header -->
            <div class="invoice-header">
                <div class="header-item">
                    <div class="header-label">INVOICE NO</div>
                    <div class="header-value invoice-no" id="invoiceNo">POS-2024-00123</div>
                </div>
                <div class="header-item">
                    <div class="header-label">DATE & TIME</div>
                    <div class="header-value" id="currentDateTime">2024-01-23 14:30:45</div>
                </div>
                <div class="header-item">
                    <div class="header-label">COMPANY</div>
                    <div style="position: relative;">
                        <input type="text" id="companySearch" placeholder="Search company..." autocomplete="off" style="width: 100%; height: 32px; padding: 0 8px; border: 1px solid var(--border-default); border-radius: 4px; background: white; font-size: 13px; font-weight: 600; color: var(--body);">
                        <div id="companyOptions" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid var(--border-default); border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 8px rgba(0,0,0,0.1);"></div>
                        <input type="hidden" id="company">
                    </div>
                </div>
                <div class="header-item">
                    <div class="header-label">BRANCH</div>
                    <div style="position: relative;">
                        <input type="text" id="branchSearch" placeholder="Search branch..." autocomplete="off" style="width: 100%; height: 32px; padding: 0 8px; border: 1px solid var(--border-default); border-radius: 4px; background: white; font-size: 13px; font-weight: 600; color: var(--body);">
                        <div id="branchOptions" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid var(--border-default); border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 8px rgba(0,0,0,0.1);"></div>
                        <input type="hidden" id="branch">
                    </div>
                </div>
                <div class="header-item">
                    <div class="header-label">OPERATOR</div>
                    <div class="header-value"><?php echo htmlspecialchars($user_full_name); ?></div>
                </div>
            </div>

            <!-- Real-time Stock Status -->
            <div class="stock-panel">
                <div class="stock-header">
                    <div class="stock-title">
                        <i class="fas fa-boxes" style="margin-right: 4px; font-size: 11px;"></i>
                        REAL-TIME STOCK
                    </div>
                    <div style="font-size: 11px; color: var(--subtext)">Updated: <span
                            id="stockUpdateTime">14:30:45</span></div>
                </div>
                <div class="stock-list" id="stockList">
                    <!-- Stock items will be populated here -->
                </div>
            </div>

            <!-- Customer & Payment -->
            <div class="customer-panel">
                <div class="form-group-micro">
                    <div class="form-label">CUSTOMER</div>
                    <div style="display: flex; gap: 4px;">
                        <select id="customer" class="font-bold" style="flex: 1;">
                            <option value="">Loading...</option>
                        </select>
                        <button class="btn btn-primary btn-micro" onclick="openCustomerForm()" title="Add New Customer" style="height: 32px; width: 32px;">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div style="font-size: 10px; color: var(--subtext); margin-top: 2px;">F2: Change Customer</div>
                </div>

                <div class="form-group-micro">
                    <div class="form-label">SUPPLIER MAN</div>
                    <select id="supplierMan" class="font-bold">
                        <option value="">Select Supplier Man</option>
                    </select>
                </div>

                <div class="form-group-micro">
                    <div class="form-label">PAYMENT METHOD</div>
                    <select id="paymentMethod">
                        <option value="cash" selected>Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>
                
                <div class="form-group-micro">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 8px;">
                        <input type="checkbox" id="autoAddChildren" style="width: auto;">
                        <span style="font-size: 11px;">Auto-add child products</span>
                    </label>
                </div>

                <div class="form-group-micro" id="bankAccountContainer" style="display: none;">
                    <div class="form-label">BANK ACCOUNT</div>
                    <select id="bankAccount">
                        <option value="">Loading...</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <div class="form-group-micro">
                        <div class="form-label">AMOUNT RECEIVED</div>
                        <input type="number" id="amountReceived" value="0" step="0.01">
                    </div>
                    <div class="form-group-micro">
                        <div class="form-label">AMOUNT RETURNED</div>
                        <input type="number" id="amountReturned" value="0" step="0.01">
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="right-panel">
            <!-- Quick Product Entry -->
            <div class="quick-entry">
                <div class="form-group-micro" style="position: relative; flex: 1;">
                    <div class="form-label">QUICK ADD PRODUCT (ENTER to add)</div>
                    <div style="display: flex; gap: 4px;">
                        <input type="text" id="quickProduct" placeholder="Scan barcode or type product name..." autofocus autocomplete="off" style="flex: 1;">
                        <button class="btn btn-primary btn-micro" onclick="openProductForm()" title="Add New Product" style="height: 32px; width: 32px;">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div id="productSuggestions" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid var(--border-default); border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 8px rgba(0,0,0,0.1);"></div>
                </div>
                <div class="form-group-micro">
                    <div class="form-label">QTY</div>
                    <input type="number" id="quickQty" value="1" min="1" step="1">
                </div>
                <button class="btn btn-primary btn-micro" id="quickAddBtn" title="Add Item (ENTER)">
                    <i class="fas fa-plus"></i>
                </button>
            </div>

            <!-- Items Table -->
            <div class="items-container">
                <div class="items-header">
                    <div class="col-header text-center">#</div>
                    <div class="col-header">PRODUCT</div>
                    <div class="col-header">UNIT</div>
                    <div class="col-header text-right">QTY</div>
                    <div class="col-header text-right">PRICE</div>
                    <div class="col-header text-right">GROSS</div>
                    <div class="col-header text-right">DISC%</div>
                    <div class="col-header text-right">GST%</div>
                    <div class="col-header text-right">NET</div>
                    <div class="col-header text-center">ACT</div>
                </div>
                <div class="items-body" id="itemsBody">
                    <!-- Items will be added here -->
                </div>
                <div class="items-footer" style="padding: 8px 12px; background: var(--surface-2); border-top: 2px solid var(--border-strong); display: grid; grid-template-columns: 30px 120px 60px 60px 70px 70px 70px 70px 80px 40px; gap: 4px; align-items: center; font-weight: 600;">
                    <div></div>
                    <div>TOTALS</div>
                    <div></div>
                    <div class="text-right" id="totalQty">0.00</div>
                    <div></div>
                    <div class="text-right" id="totalGross">0.00</div>
                    <div></div>
                    <div></div>
                    <div class="text-right" id="totalNet">0.00</div>
                    <div></div>
                </div>
            </div>

            <!-- Invoice Summary -->
            <div class="summary-panel">
                <div class="summary-item">
                    <div class="summary-label">TOTAL BILL</div>
                    <div class="summary-value" id="totalBill">$0.00</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">DISCOUNT</div>
                    <div class="summary-value" id="totalDiscount">$0.00</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">GST</div>
                    <div class="summary-value" id="totalGst">$0.00</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">NET AMOUNT</div>
                    <div class="summary-value total-amount" id="netAmount">$0.00</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">RECEIVED</div>
                    <div class="summary-value" id="summaryReceived">$0.00</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">RETURNED</div>
                    <div class="summary-value" id="summaryReturned">$0.00</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">BALANCE</div>
                    <div class="summary-value" id="balanceAmount">$0.00</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">ITEMS</div>
                    <div class="summary-value" id="itemCount">0</div>
                </div>
            </div>

            <!-- Payment Section -->
            <div class="payment-panel">
                <div class="form-group-micro">
                    <div class="form-label">INVOICE DISC %</div>
                    <input type="number" id="invoiceDiscountPercent" value="0" min="0" max="100" step="0.1">
                </div>
                <div class="form-group-micro">
                    <div class="form-label">INVOICE DISC AMT</div>
                    <input type="number" id="invoiceDiscountAmount" value="0" step="0.01">
                </div>
                <div class="form-group-micro">
                    <div class="form-label">CURRENCY</div>
                    <select id="currency">
                        <option value="<?php echo $currency['symbol']; ?>" selected><?php echo $currency['symbol']; ?></option>
                    </select>
                </div>
                <button class="btn btn-success" id="quickPayBtn" style="height: 32px;">
                    <i class="fas fa-bolt"></i> QUICK PAY (F9)
                </button>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn btn-primary" id="saveInvoiceBtn">
                    <i class="fas fa-save"></i> SAVE (F10)
                </button>
                <button class="btn btn-secondary" id="saveDraftBtn">
                    <i class="fas fa-file"></i> SAVE AS DRAFT
                </button>
                <button class="btn btn-secondary" id="shortcutSettingsBtn">
                    <i class="fas fa-keyboard"></i> SHORTCUTS
                </button>
                <button class="btn btn-secondary" id="returnBtn">
                    <i class="fas fa-file-invoice-dollar"></i> RETURN
                </button>
                <button class="btn btn-secondary" id="clearBtn">
                    <i class="fas fa-trash-alt"></i> CLEAR (F12)
                </button>
                <button class="btn btn-secondary" id="draftsBtn">
                    <i class="fas fa-history"></i> DRAFT INVOICES
                </button>
                <button class="btn btn-danger">
                    <i class="fas fa-times"></i> CANCEL
                </button>
            </div>
        </div>
    </div>

<!-- Shortcut Settings Modal -->
    <div class="modal" id="shortcutSettingsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; padding: 24px; border-radius: 8px; max-width: 700px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <h3 style="margin-bottom: 16px; color: var(--heading);">Keyboard Shortcuts</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Focus Product:</label>
                    <input type="text" id="shortcutProduct" value="F1" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Focus Customer:</label>
                    <input type="text" id="shortcutCustomer" value="F2" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Focus Branch:</label>
                    <input type="text" id="shortcutBranch" value="F3" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Focus Quantity:</label>
                    <input type="text" id="shortcutQty" value="F4" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Quick Pay:</label>
                    <input type="text" id="shortcutQuickPay" value="F9" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Save Invoice:</label>
                    <input type="text" id="shortcutSave" value="F10" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Save As Draft:</label>
                    <input type="text" id="shortcutDraft" value="F8" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Clear Invoice:</label>
                    <input type="text" id="shortcutClear" value="F12" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Open Return:</label>
                    <input type="text" id="shortcutReturn" value="F7" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Next Field:</label>
                    <input type="text" id="shortcutNextField" value="Tab" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Previous Field:</label>
                    <input type="text" id="shortcutPrevField" value="Shift+Tab" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
            </div>
            <div style="margin-top: 16px; display: flex; gap: 8px; justify-content: flex-end;">
                <button class="btn btn-secondary" id="resetShortcutsBtn">Reset to Default</button>
                <button class="btn btn-secondary" id="closeShortcutSettingsBtn">Close</button>
                <button class="btn btn-primary" id="saveShortcutSettingsBtn">Save</button>
            </div>
        </div>
    </div>

    <!-- Sales Return Modal -->
    <div class="modal" id="returnModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; padding: 0; border-radius: 8px; max-width: 95%; width: 95%; height: 90vh; display: flex; flex-direction: column;">
            <div style="padding: 16px; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: var(--heading);">Sales Return</h3>
                <button class="btn btn-secondary btn-sm" id="closeReturnBtn" style="padding: 4px 12px;">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <iframe id="returnIframe" style="flex: 1; border: none; width: 100%;"></iframe>
        </div>
    </div>

    <!-- Customer Form Modal -->
    <div class="modal" id="customerModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; padding: 0; border-radius: 8px; max-width: 95%; width: 95%; height: 90vh; display: flex; flex-direction: column;">
            <div style="padding: 16px; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: var(--heading);">Add Customer</h3>
                <button class="btn btn-secondary btn-sm" id="closeCustomerBtn" style="padding: 4px 12px;">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <iframe id="customerIframe" style="flex: 1; border: none; width: 100%;"></iframe>
        </div>
    </div>

    <!-- Product Form Modal -->
    <div class="modal" id="productModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; padding: 0; border-radius: 8px; max-width: 95%; width: 95%; height: 90vh; display: flex; flex-direction: column;">
            <div style="padding: 16px; border-bottom: 1px solid var(--border-default); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: var(--heading);">Add Product</h3>
                <button class="btn btn-secondary btn-sm" id="closeProductBtn" style="padding: 4px 12px;">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <iframe id="productIframe" style="flex: 1; border: none; width: 100%;"></iframe>
        </div>
    </div>

    <!-- Draft Invoices Modal -->
    <div class="modal" id="draftsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; padding: 24px; border-radius: 8px; max-width: 900px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <h3 style="margin-bottom: 16px; color: var(--heading);">Draft Invoices</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: var(--surface-2);">
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid var(--border-default);">Invoice No</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid var(--border-default);">Date</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid var(--border-default);">Customer</th>
                            <th style="padding: 8px; text-align: right; border-bottom: 1px solid var(--border-default);">Amount</th>
                            <th style="padding: 8px; text-align: center; border-bottom: 1px solid var(--border-default);">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="draftsTableBody">
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 16px; display: flex; gap: 8px; justify-content: flex-end;">
                <button class="btn btn-secondary" id="closeDraftsBtn">Close</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/sale/pos_invoice/counter-invoice.js?v=<?php echo time(); ?>"></script>
    <script>
        const userEmployeeId = <?php echo json_encode($user_employee_id); ?>;
        let stockData = [];

        document.addEventListener('DOMContentLoaded', async function () {
            // Load saved theme
            const savedTheme = localStorage.getItem('counter_invoice_theme') || 'light';
            applyTheme(savedTheme);

            // Theme toggle
            document.getElementById('themeToggleBtn').addEventListener('click', function() {
                const currentTheme = document.body.classList.contains('counter-mode') ? 'counter' : 'light';
                const newTheme = currentTheme === 'light' ? 'counter' : 'light';
                applyTheme(newTheme);
                localStorage.setItem('counter_invoice_theme', newTheme);
            });

            await loadStockData();
            updateStockDisplay();
            setInterval(() => loadStockData(), 30000);

            // Quick Pay
            document.getElementById('quickPayBtn').addEventListener('click', quickPay);

            // Save Invoice
            document.getElementById('saveInvoiceBtn').addEventListener('click', saveInvoice);

            // Update calculations on input
            document.getElementById('amountReceived').addEventListener('input', updateSummary);
            document.getElementById('amountReturned').addEventListener('input', updateSummary);
            document.getElementById('invoiceDiscountPercent').addEventListener('input', updateInvoiceDiscount);
            document.getElementById('invoiceDiscountAmount').addEventListener('input', updateInvoiceDiscount);

            // Keyboard shortcuts
            document.addEventListener('keydown', function (e) {
                switch (e.key) {
                    case 'F2':
                        e.preventDefault();
                        document.getElementById('customer').focus();
                        break;
                    case 'F8':
                        e.preventDefault();
                        printInvoice();
                        break;
                    case 'F9':
                        e.preventDefault();
                        quickPay();
                        break;
                    case 'F10':
                        e.preventDefault();
                        saveInvoice();
                        break;
                    case 'F12':
                        e.preventDefault();
                        if (confirm('Clear current invoice?')) {
                            currentInvoice.items = [];
                            renderItems();
                            updateSummary();
                            document.getElementById('invoiceDiscountPercent').value = 0;
                            document.getElementById('invoiceDiscountAmount').value = 0;
                            document.getElementById('amountReceived').value = 0;
                            document.getElementById('amountReturned').value = 0;
                        }
                        break;
                }
            });
            
            document.getElementById('closeReturnBtn').addEventListener('click', function() {
                document.getElementById('returnModal').style.display = 'none';
                document.getElementById('returnIframe').src = '';
            });
            
            document.getElementById('closeCustomerBtn').addEventListener('click', function() {
                document.getElementById('customerModal').style.display = 'none';
                document.getElementById('customerIframe').src = '';
            });
            
            document.getElementById('closeProductBtn').addEventListener('click', function() {
                document.getElementById('productModal').style.display = 'none';
                document.getElementById('productIframe').src = '';
            });
        });
        
        function openCustomerForm() {
            document.getElementById('customerIframe').src = '../../customer_supplier/customers/customer-add.php';
            document.getElementById('customerModal').style.display = 'flex';
        }
        
        function openProductForm() {
            document.getElementById('productIframe').src = '../../inventory/products/product-add.php';
            document.getElementById('productModal').style.display = 'flex';
        }
        
        window.addEventListener('message', function(e) {
            if (e.data === 'customerAdded') {
                loadCustomers();
                document.getElementById('customerModal').style.display = 'none';
                document.getElementById('customerIframe').src = '';
            } else if (e.data === 'productAdded') {
                loadProducts();
                document.getElementById('productModal').style.display = 'none';
                document.getElementById('productIframe').src = '';
            }
        });

        async function loadStockData() {
            const branchId = currentInvoice.branch;
            if (!branchId) return;
            
            const products = await Promise.all(productsData.slice(0, 8).map(async (product) => {
                const response = await fetch(`../../../../server/api/sale/pos_invoice/get-product-stock.php?product_id=${product.id}&branch_id=${branchId}`);
                const data = await response.json();
                return {
                    id: product.id,
                    name: product.name,
                    qty: data.success ? Math.floor(data.stock) : 0,
                    min: 5,
                    max: 100
                };
            }));
            stockData = products;
            updateStockDisplay();
        }

        function updateStockDisplay() {
            const stockList = document.getElementById('stockList');
            stockList.innerHTML = '';

            stockData.forEach(item => {
                const stockItem = document.createElement('div');
                stockItem.className = 'stock-item';

                let statusClass = 'stock-ok';
                if (item.qty < item.min) {
                    statusClass = 'stock-low';
                } else if (item.qty > item.max * 0.8) {
                    statusClass = 'stock-high';
                }

                stockItem.innerHTML = `
                    <div class="stock-name">
                        <span class="status-indicator ${statusClass.replace('stock-', 'status-')}"></span>
                        ${item.name}
                    </div>
                    <div class="stock-qty ${statusClass}">${item.qty}</div>
                `;

                stockList.appendChild(stockItem);
            });
        }

        function applyTheme(theme) {
            const themeToggleText = document.getElementById('themeToggleText');
            if (theme === 'counter') {
                document.body.classList.add('counter-mode');
                if (themeToggleText) themeToggleText.textContent = 'Light Mode';
            } else {
                document.body.classList.remove('counter-mode');
                if (themeToggleText) themeToggleText.textContent = 'Dark Mode';
            }
        }


    </script>
</body>

</html>