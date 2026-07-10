<?php
require_once '../../../includes/dashboard.php';
require_once '../../../includes/connection.php';
require_once '../../../includes/permissions.php';
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user_id and tenant_id from session
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    // Redirect to login if no user_id or tenant_id in session
    header('Location: ../auth/login.html');
    exit();
}

// Get role_id from user_roles table
$stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$user_id, $tenant_id]);
$role_id = $stmt->fetchColumn();

// Check Dashboard permission
$hasPermission = hasPermission($pdo, $role_id, 'Dashboard', 'dashboard', $tenant_id);

// Get date filters
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;
$hasDateFilter = $dateFrom && $dateTo;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/dashboard/dashboard.css">
    <style>
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .dashboard-actions {
                flex-direction: column;
                gap: 0.75rem;
                width: 100%;
            }

            .dashboard-actions input,
            .dashboard-actions button {
                width: 100%;
                padding: 0.75rem 1rem;
                border-radius: 0.5rem;
                font-size: 1rem;
            }

            .dashboard-actions input {
                border: 1px solid #d1d5db;
            }

            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .grid {
                grid-template-columns: 1fr !important;
                gap: 1rem;
            }

            .card.mobile-full {
                width: 100%;
            }

            .table-container {
                overflow-x: auto;
            }

            .table-container table {
                min-width: 300px;
                font-size: 0.875rem;
            }

            .chart-container {
                height: 200px;
                width: 100%;
            }

            .form-actions {
                flex-direction: column;
                gap: 0.75rem;
            }

            .form-actions button {
                width: 100%;
                padding: 0.875rem 1rem;
                font-size: 1rem;
                border-radius: 0.5rem;
            }

            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 1rem;
            }

            .kpi-card {
                padding: 1rem;
            }

            .card {
                padding: 1rem;
            }

            .table-container table th,
            .table-container table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.75rem;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }
        }

        .quick-actions {
            margin: 2rem 0;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            padding: 1.5rem 1rem;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
            text-align: center;
        }

        .action-btn:hover {
            border-color: var(--accent);
            background: #f0f9ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }

        .action-btn i {
            font-size: 1.75rem;
            color: var(--accent);
        }

        .action-btn span {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .ledger-search-box {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border: 2px solid #e2e8f0;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .ledger-input {
            flex: 1;
            min-width: 250px;
            height: 44px;
            padding: 0 1rem;
            border: 1.5px solid #d6dbe4;
            border-radius: 0.5rem;
            font-size: 14px;
        }

        .ledger-radio-group {
            display: flex;
            gap: 1.5rem;
        }

        .ledger-radio-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 14px;
            cursor: pointer;
        }

        .date-buckets {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .date-bucket-btn {
            padding: 0.5rem 0.75rem;
            background: white;
            border: 1.5px solid #d6dbe4;
            border-radius: 0.5rem;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            color: #334155;
            font-weight: 500;
        }

        .date-bucket-btn:hover {
            border-color: var(--primary);
            background: #f0f9ff;
            color: var(--primary);
        }

        .date-bucket-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .ledger-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .ledger-modal.show {
            display: flex;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .ledger-modal-content {
            background: white;
            width: 95%;
            max-width: 1400px;
            height: 90vh;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .ledger-modal-header {
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, #1f7bff 0%, #1559b8 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .ledger-modal-header h3 {
            margin: 0;
            font-size: 1.375rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: white !important;
        }

        .ledger-modal-header h3::before {
            content: '📊';
            font-size: 1.5rem;
        }

        #ledgerModalTitle {
            color: white !important;
            margin: 0;
            font-size: 1.375rem;
            font-weight: 600;
        }

        .ledger-modal-close {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
            line-height: 1;
        }

        .ledger-modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .ledger-modal-close:active {
            transform: scale(0.95);
        }

        .ledger-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
            background: #f8fafc;
        }

        .ledger-modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .ledger-modal-body::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .ledger-modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .ledger-modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .ledger-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .ledger-summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .ledger-summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #3b82f6, #1d4ed8);
        }

        .ledger-summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .ledger-summary-label {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 600;
        }

        .ledger-summary-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .ledger-summary-value.positive {
            color: #16a34a;
        }

        .ledger-summary-value.negative {
            color: #dc2626;
        }

        .ledger-table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .ledger-table {
            width: 100%;
            border-collapse: collapse;
        }

        .ledger-table thead {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 2px solid #e2e8f0;
        }

        .ledger-table th {
            padding: 1rem 1.25rem;
            text-align: left;
            font-weight: 700;
            font-size: 12px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            white-space: nowrap;
        }

        .ledger-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
            vertical-align: middle;
        }

        .ledger-table tbody tr {
            transition: background 0.15s;
        }

        .ledger-table tbody tr:hover {
            background: #f8fafc;
        }

        .ledger-table tbody tr:last-child td {
            border-bottom: none;
        }

        .ledger-table tbody tr.opening-row {
            background: linear-gradient(90deg, #eff6ff 0%, #dbeafe 100%);
            font-weight: 600;
        }

        .ledger-table tbody tr.sub-header {
            background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 100%);
            font-weight: 600;
        }

        .ledger-table tbody tr.sub-total {
            background: linear-gradient(90deg, #fef3c7 0%, #fde68a 100%);
            font-weight: 600;
        }

        .ledger-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 5rem 2rem;
            color: #64748b;
        }

        .ledger-loading i {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            animation: spin 1s linear infinite;
            color: #3b82f6;
        }

        .ledger-loading p {
            font-size: 1rem;
            font-weight: 500;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .ledger-error {
            padding: 4rem 2rem;
            text-align: center;
            color: #dc2626;
        }

        .ledger-error i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .ledger-error p {
            font-size: 1rem;
            font-weight: 500;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-dr {
            background: #dcfce7;
            color: #166534;
        }

        .badge-cr {
            background: #fee2e2;
            color: #991b1b;
        }

        .bank-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .bank-modal.show {
            display: flex;
            animation: fadeIn 0.2s ease;
        }

        .bank-modal-content {
            background: white;
            width: 95%;
            max-width: 1400px;
            height: 90vh;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: slideUp 0.3s ease;
        }

        .bank-modal-header {
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, #1f7bff 0%, #1559b8 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .bank-modal-header h3 {
            margin: 0;
            font-size: 1.375rem;
            font-weight: 600;
            color: white !important;
        }

        .bank-modal-close {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .bank-modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .bank-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
            background: #f8fafc;
        }

        .bank-accounts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .bank-account-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .bank-account-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(31, 123, 255, 0.15);
        }

        .bank-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 8px;
            background: #f8fafc;
            padding: 8px;
        }

        .bank-info h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1.125rem;
            color: var(--heading);
        }

        .bank-info p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--subtext);
        }

        .bank-statement-view {
            display: none;
        }

        .bank-statement-view.active {
            display: block;
        }

        .statement-header {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e2e8f0;
        }

        .statement-filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .statement-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .statement-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .statement-table th {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: #475569;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
        }

        .statement-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }

        .statement-table tbody tr:hover {
            background: #f8fafc;
        }

        @media (max-width: 768px) {
            .bank-accounts-grid {
                grid-template-columns: 1fr;
            }

            .statement-filters {
                flex-direction: column;
                width: 100%;
            }

            .statement-filters select,
            .statement-filters input,
            .statement-filters button {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .ledger-search-box {
                flex-direction: column;
                align-items: stretch;
            }

            .ledger-input {
                width: 100%;
            }

            .ledger-radio-group {
                justify-content: center;
            }

            .ledger-modal-content {
                width: 100%;
                height: 100vh;
                border-radius: 0;
            }

            .ledger-summary {
                grid-template-columns: repeat(2, 1fr);
            }

            .ledger-table-container {
                overflow-x: auto;
            }

            .ledger-table {
                min-width: 600px;
            }
        }
    </style>
</head>

<body>
    <?php if (!$hasPermission): ?>
        <div class="container"
            style="display: flex; justify-content: center; align-items: center; min-height: 100vh; text-align: center;">
            <div>
                <h1 style="color: var(--danger); font-size: 3rem; margin-bottom: 1rem;">⚠️</h1>
                <h2 style="color: var(--dark); margin-bottom: 0.5rem;">Not Authorized</h2>
                <p style="color: #64748b;">You don't have permission to access the Dashboard.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="container">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div>
                    <h1>LedgerOne Dashboard</h1>
                    <p>Welcome back! Here's your business overview.</p>
                </div>
                <div class="dashboard-actions">
                    <input type="date" id="dateFrom" value="<?= $dateFrom ?? date('Y-m-d') ?>" class="btn btn-secondary">
                    <input type="date" id="dateTo" value="<?= $dateTo ?? date('Y-m-d') ?>" class="btn btn-secondary">
                    <button class="btn btn-primary" onclick="filterDashboard()">Filter</button>
                    <button class="btn btn-secondary" onclick="clearFilters()">Clear</button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-value" id="kpi-units-sold">Loading...</div>
                    <div class="kpi-label">Products Sold <?= $hasDateFilter ? 'Selected Period' : 'All Time' ?></div>
                    <div class="kpi-change" id="kpi-units-change">
                        <span>Calculating...</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value" id="kpi-revenue">Loading...</div>
                    <div class="kpi-label">Revenue <?= $hasDateFilter ? 'Selected Period' : 'All Time' ?></div>
                    <div class="kpi-change" id="kpi-revenue-change">
                        <span>Calculating...</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value" id="kpi-purchases">Loading...</div>
                    <div class="kpi-label">Purchases <?= $hasDateFilter ? 'Selected Period' : 'All Time' ?></div>
                    <div class="kpi-change" id="kpi-purchases-change">
                        <span>Calculating...</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value" id="kpi-stock-value">Loading...</div>
                    <div class="kpi-label">Stock Valuation</div>
                    <div class="kpi-change" id="kpi-stock-change">
                        <span>Calculating...</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value" id="kpi-receivables">Loading...</div>
                    <div class="kpi-label">Accounts Receivable</div>
                    <div class="kpi-change">
                        <span>Total outstanding from customers</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value" id="kpi-payables">Loading...</div>
                    <div class="kpi-label">Accounts Payable</div>
                    <div class="kpi-change">
                        <span>Total outstanding to suppliers</span>
                    </div>
                </div>
            </div>

            <!-- Quick Ledger Access -->
            <div class="quick-actions">
                <h2 class="section-title">Quick Ledger Access</h2>
                <div class="ledger-search-box">
                    <input type="text" id="ledgerSearch" placeholder="Enter Customer/Supplier/Account Name or Code..." class="ledger-input">
                    <div class="ledger-radio-group">
                        <label><input type="radio" name="ledgerType" value="customer" checked> Customer</label>
                        <label><input type="radio" name="ledgerType" value="supplier"> Supplier</label>
                        <label><input type="radio" name="ledgerType" value="account"> Account (GL)</label>
                    </div>
                    <div class="date-buckets">
                        <button class="date-bucket-btn" onclick="setDateRange(7)">7D</button>
                        <button class="date-bucket-btn" onclick="setDateRange(14)">14D</button>
                        <button class="date-bucket-btn" onclick="setDateRange(30)">30D</button>
                        <button class="date-bucket-btn" onclick="setDateRange(90)">Quarter</button>
                        <button class="date-bucket-btn" onclick="setDateRange(365)">Year</button>
                        <button class="date-bucket-btn active" onclick="setDateRange(0)">All</button>
                    </div>
                    <input type="date" id="ledgerDateFrom" class="ledger-input" style="flex: 0 0 auto; min-width: 150px;" placeholder="From Date">
                    <input type="date" id="ledgerDateTo" class="ledger-input" style="flex: 0 0 auto; min-width: 150px;" placeholder="To Date">
                    <button class="btn btn-primary" onclick="openLedger()">Open Ledger</button>
                </div>
            </div>

             <!-- Keyboard Shortcuts -->
            <div class="quick-actions">
                <h2 class="section-title">Keyboard Shortcuts</h2>
                <div class="ledger-search-box" style="flex-direction: column; align-items: stretch;">
                    <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center; width:100%;">
                        <select id="shortcutPageSelect" class="ledger-input" style="flex:1; min-width:220px;">
                            <option value="">Select Page...</option>
                            <option value="sale-invoice" data-label="Sale Invoice">Sale Invoice</option>
                            <option value="purchase-invoice" data-label="Purchase Invoice">Purchase Invoice</option>
                            <option value="receive-voucher" data-label="Receive Voucher (RV)">Receive Voucher (RV)</option>
                            <option value="payment-voucher" data-label="Payment Voucher (PV)">Payment Voucher (PV)</option>
                            <option value="expense-voucher" data-label="Expense Voucher (EV)">Expense Voucher (EV)</option>
                            <option value="customer-ledger" data-label="Customer Ledger">Customer Ledger</option>
                            <option value="supplier-ledger" data-label="Supplier Ledger">Supplier Ledger</option>
                            <option value="pos-invoice" data-label="POS Invoice">POS Invoice</option>
                            <option value="new-product" data-label="Product List">Product List</option>
                            <option value="new-customer" data-label="New Customer">New Customer</option>
                            <option value="new-supplier" data-label="New Supplier">New Supplier</option>
                        </select>
                        <input type="text" id="shortcutKeyInput" class="ledger-input"
                            style="flex:0 0 220px; text-align:center; cursor:pointer; background:#f8fafc;"
                            placeholder="Click here, then press keys" readonly>
                        <button class="btn btn-primary" onclick="saveShortcut()">Save Shortcut</button>
                    </div>
                    <div id="shortcutsList" style="display:flex; flex-wrap:wrap; gap:0.6rem; margin-top:1rem;">
                        <!-- filled by JS -->
                    </div>
                </div>
            </div>

            <!-- Quick Actions Section -->
            <div class="quick-actions">
                <h2 class="section-title">Quick Actions</h2>
                <div class="actions-grid">
                    <a href="../sale/pos_invoice/counter-invoice.php" class="action-btn">
                        <i class="fas fa-cash-register"></i>
                        <span>New POS Invoice</span>
                    </a>
                    <a href="../purchase/purchase_invoice/purchase-add.php" class="action-btn">
                        <i class="fas fa-shopping-cart"></i>
                        <span>New Purchase</span>
                    </a>
                    <a href="../vouchers/receive_voucher/receive-add.php" class="action-btn">
                        <i class="fas fa-money-check-alt"></i>
                        <span>New Receive Voucher</span>
                    </a>
                    <a href="../vouchers/expense_voucher/expense-add.php" class="action-btn">
                        <i class="fas fa-receipt"></i>
                        <span>Record Expenses</span>
                    </a>
                    <a href="../financial_reports/cash_flow/cash-flow.php" class="action-btn">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Cash Flow Report</span>
                    </a>
                    <a href="#" class="action-btn" onclick="openBankStatementModal(); return false;">
                        <i class="fas fa-university"></i>
                        <span>Bank Statement</span>
                    </a>
                </div>
            </div>

            <!-- Charts and Data Section -->
            <div class="grid" style="grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <!-- Sales Chart -->
                <div class="card">
                    <h3 class="card-title">Sales Trend</h3>
                    <div id="fuelSalesChart"></div>
                </div>

                <!-- Receivables vs Payables Chart -->
                <div class="card">
                    <h3 class="card-title">Receivables vs Payables</h3>
                    <div id="accountsChart"></div>
                </div>

                <!-- Revenue vs Expenses Chart -->
                <div class="card">
                    <h3 class="card-title">Revenue vs Expenses</h3>
                    <div id="revenueExpenseChart"></div>
                </div>

                <!-- Cash Flow Chart -->
                <div class="card">
                    <h3 class="card-title">Cash Flow (Last 7 Days)</h3>
                    <div id="cashFlowChart"></div>
                </div>

                <!-- Product-wise Sales -->
                <div class="card">
                    <h3 class="card-title">Top Products</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($hasDateFilter) {
                                    $stmt = $pdo->prepare("
                                    SELECT p.name, 
                                           COALESCE(SUM(sii.quantity), 0) as total_qty,
                                           COALESCE(SUM(sii.net_amount), 0) as total_revenue
                                    FROM products p
                                    LEFT JOIN sale_invoice_items sii ON p.id = sii.product_id
                                    LEFT JOIN sale_invoice si ON sii.sale_invoice_id = si.id AND si.tenant_id = ? AND DATE(si.sale_date) BETWEEN ? AND ?
                                    WHERE p.tenant_id = ? AND p.parent_product_id IS NULL
                                    GROUP BY p.id, p.name
                                    HAVING total_qty > 0
                                    ORDER BY total_revenue DESC
                                    LIMIT 5
                                ");
                                    $stmt->execute([$tenant_id, $dateFrom, $dateTo, $tenant_id]);
                                } else {
                                    $stmt = $pdo->prepare("
                                    SELECT p.name, 
                                           COALESCE(SUM(sii.quantity), 0) as total_qty,
                                           COALESCE(SUM(sii.net_amount), 0) as total_revenue
                                    FROM products p
                                    LEFT JOIN sale_invoice_items sii ON p.id = sii.product_id
                                    LEFT JOIN sale_invoice si ON sii.sale_invoice_id = si.id AND si.tenant_id = ?
                                    WHERE p.tenant_id = ? AND p.parent_product_id IS NULL
                                    GROUP BY p.id, p.name
                                    HAVING total_qty > 0
                                    ORDER BY total_revenue DESC
                                    LIMIT 5
                                ");
                                    $stmt->execute([$tenant_id, $tenant_id]);
                                }
                                $productSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($productSales as $product):
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['name']) ?></td>
                                        <td><?= number_format($product['total_qty'], 0) ?></td>
                                        <td>Rs <?= number_format($product['total_revenue'], 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Customers -->
                <div class="card">
                    <h3 class="card-title">Top Customers</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Orders</th>
                                    <th>Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT c.customer_name, COUNT(si.id) as order_count, COALESCE(SUM(si.net_amount), 0) as total_sales
                                    FROM customers c
                                    LEFT JOIN sale_invoice si ON c.id = si.customer_id AND si.tenant_id = ?";
                                $params = [$tenant_id];
                                if ($hasDateFilter) {
                                    $sql .= " AND DATE(si.sale_date) BETWEEN ? AND ?";
                                    $params[] = $dateFrom;
                                    $params[] = $dateTo;
                                }
                                $sql .= " WHERE c.tenant_id = ? GROUP BY c.id, c.customer_name HAVING total_sales > 0 ORDER BY total_sales DESC LIMIT 5";
                                $params[] = $tenant_id;
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute($params);
                                $topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($topCustomers as $customer): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($customer['customer_name']) ?></td>
                                        <td><?= $customer['order_count'] ?></td>
                                        <td>Rs <?= number_format($customer['total_sales'], 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Branch-wise Sales -->
                <div class="card">
                    <h3 class="card-title">Branch Sales</h3>
                    <div id="branchSalesChart"></div>
                </div>

                <!-- Territory-wise Sales -->
                <div class="card">
                    <h3 class="card-title">Territory Sales</h3>
                    <div id="territorySalesChart"></div>
                </div>

                <!-- Product Inventory -->
                <div class="card">
                    <h3 class="card-title">Inventory Status</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $pdo->prepare("
                                SELECT p.name, 
                                       COALESCE(SUM(sl.qty_in - sl.qty_out), 0) as current_stock,
                                       COALESCE(p.min_stock_level, 0) as min_stock
                                FROM products p
                                LEFT JOIN stock_ledger sl ON p.id = sl.product_id
                                WHERE p.tenant_id = ? AND p.parent_product_id IS NULL
                                GROUP BY p.id, p.name, p.min_stock_level
                                ORDER BY current_stock ASC
                                LIMIT 5
                            ");
                                $stmt->execute([$tenant_id]);
                                $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($inventory as $item):
                                    $stock = (float) $item['current_stock'];
                                    $minStock = (float) $item['min_stock'];

                                    if ($stock <= 0) {
                                        $status = 'Out';
                                        $color = 'var(--danger)';
                                    } elseif ($minStock > 0 && $stock <= $minStock) {
                                        $status = 'Low';
                                        $color = 'var(--warning)';
                                    } else {
                                        $status = 'OK';
                                        $color = 'var(--success)';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['name']) ?></td>
                                        <td><?= number_format($stock, 0) ?></td>
                                        <td style="color: <?= $color ?>;"><?= $status ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mini Balance Sheet -->
                <div class="card">
                    <h3 class="card-title">Balance Sheet</h3>
                    <div class="table-container">
                        <table id="balanceSheetTable">
                            <thead>
                                <tr>
                                    <th>Assets</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="assetsBody">
                                <tr>
                                    <td colspan="2" style="text-align: center; color: #64748b;">Loading...</td>
                                </tr>
                            </tbody>
                            <thead>
                                <tr>
                                    <th>Liabilities & Equity</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="liabilitiesBody">
                                <tr>
                                    <td colspan="2" style="text-align: center; color: #64748b;">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bank Statement Modal -->
    <div id="bankStatementModal" class="bank-modal">
        <div class="bank-modal-content">
            <div class="bank-modal-header">
                <h3 id="bankModalTitle">Select Bank Account</h3>
                <button class="bank-modal-close" onclick="closeBankStatementModal()">&times;</button>
            </div>
            <div class="bank-modal-body">
                <!-- Bank Accounts Selection -->
                <div id="bankAccountsView" class="bank-accounts-grid"></div>
                
                <!-- Bank Statement View -->
                <div id="bankStatementView" class="bank-statement-view">
                    <div class="statement-header">
                        <button class="btn btn-secondary" onclick="backToBankSelection()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <div class="statement-filters">
                            <select id="statementDateRange" class="form-input" style="width: auto;">
                                <option value="week">Last 7 Days</option>
                                <option value="month" selected>Last Month</option>
                                <option value="quarter">Last Quarter</option>
                                <option value="year">Last Year</option>
                                <option value="custom">Custom Range</option>
                            </select>
                            <input type="date" id="statementFromDate" class="form-input" style="width: auto; display: none;">
                            <input type="date" id="statementToDate" class="form-input" style="width: auto; display: none;">
                            <button class="btn btn-primary" onclick="loadBankStatement()">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                    </div>
                    
                    <div id="statementSummary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;"></div>
                    
                    <div class="statement-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Reference</th>
                                    <th class="text-right">Debit</th>
                                    <th class="text-right">Credit</th>
                                    <th class="text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody id="statementTableBody">
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 3rem; color: #64748b;">
                                        Select a bank account to view statement
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($hasPermission): ?>
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        <script>
            const dateFrom = '<?= $dateFrom ?? '' ?>';
            const dateTo = '<?= $dateTo ?? '' ?>';
            const hasDateFilter = <?= $hasDateFilter ? 'true' : 'false' ?>;

            // Load KPI data from APIs
            async function loadKPIs() {
                try {
                    // Stock Valuation from stock-position API
                    const stockRes = await fetch('../../../server/api/inventory/stock_position/stock-position.php?action=position');
                    const stockData = await stockRes.json();
                    if (stockData.success && Array.isArray(stockData.data)) {
                        const totalValue = stockData.data.reduce((sum, item) => sum + parseFloat(item.stock_value || 0), 0);
                        document.getElementById('kpi-stock-value').textContent = 'Rs ' + totalValue.toLocaleString('en-PK', {maximumFractionDigits: 0});
                    }

                    // Units Sold and Revenue from sale-reports API
                    let saleUrl = '../../../server/api/sale/sales_report/sales-report.php';
                    if (hasDateFilter) {
                        saleUrl += `?date_from=${dateFrom}&date_to=${dateTo}`;
                    }
                    const saleRes = await fetch(saleUrl);
                    const saleData = await saleRes.json();
                    if (saleData.success && saleData.data && saleData.data.product_sales) {
                        const totalUnits = saleData.data.product_sales.reduce((sum, item) => sum + parseFloat(item.quantity_sold || 0), 0);
                        const totalRevenue = saleData.data.product_sales.reduce((sum, item) => sum + parseFloat(item.revenue || 0), 0);
                        document.getElementById('kpi-units-sold').textContent = totalUnits.toLocaleString('en-PK', {maximumFractionDigits: 0}) + ' Units';
                        document.getElementById('kpi-revenue').textContent = 'Rs ' + totalRevenue.toLocaleString('en-PK', {maximumFractionDigits: 0});
                    }

                    // Purchases from purchase-reports API
                    let purchaseUrl = '../../../server/api/purchase/purchase_reports/purchase-reports.php?type=invoice';
                    if (hasDateFilter) {
                        purchaseUrl += `&date_range=${dateFrom}_${dateTo}`;
                    }
                    const purchaseRes = await fetch(purchaseUrl);
                    const purchaseData = await purchaseRes.json();
                    if (purchaseData.success && purchaseData.summary && purchaseData.summary.invoice) {
                        const totalPurchases = parseFloat(purchaseData.summary.invoice.total_invoice_value || 0);
                        document.getElementById('kpi-purchases').textContent = 'Rs ' + totalPurchases.toLocaleString('en-PK', {maximumFractionDigits: 0});
                    }

                    // Accounts Receivable from customer-ledger API
                    const customerRes = await fetch('../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=summary');
                    const customerData = await customerRes.json();
                    if (customerData.success && Array.isArray(customerData.data)) {
                        const totalReceivables = customerData.data.reduce((sum, item) => sum + parseFloat(item.closing_balance || 0), 0);
                        document.getElementById('kpi-receivables').textContent = 'Rs ' + totalReceivables.toLocaleString('en-PK', {maximumFractionDigits: 0});
                    }

                    // Accounts Payable from supplier-ledger API
                    const supplierRes = await fetch('../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?type=summary');
                    const supplierData = await supplierRes.json();
                    if (supplierData.success && Array.isArray(supplierData.data)) {
                        const totalPayables = supplierData.data.reduce((sum, item) => sum + parseFloat(item.closing_balance || 0), 0);
                        document.getElementById('kpi-payables').textContent = 'Rs ' + Math.abs(totalPayables).toLocaleString('en-PK', {maximumFractionDigits: 0});
                    }
                } catch (error) {
                    console.error('Error loading KPIs:', error);
                }
            }

            // Load KPIs on page load
            loadKPIs();

            // Sales Trend Chart
            <?php
            $chartData = [];
            $chartLabels = [];
            if ($hasDateFilter) {
                $startDate = new DateTime($dateFrom);
                $endDate = new DateTime($dateTo);
                while ($startDate <= $endDate) {
                    $date = $startDate->format('Y-m-d');
                    $chartLabels[] = $startDate->format('M j');
                    $stmt = $pdo->prepare("SELECT COALESCE(SUM(sii.quantity), 0) FROM sale_invoice si JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id WHERE si.tenant_id = ? AND DATE(si.sale_date) = ? AND sii.parent_row_id IS NULL");
                    $stmt->execute([$tenant_id, $date]);
                    $chartData[] = (float) $stmt->fetchColumn();
                    $startDate->add(new DateInterval('P1D'));
                }
            } else {
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $chartLabels[] = date('M j', strtotime("-$i days"));
                    $stmt = $pdo->prepare("SELECT COALESCE(SUM(sii.quantity), 0) FROM sale_invoice si JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id WHERE si.tenant_id = ? AND DATE(si.sale_date) = ? AND sii.parent_row_id IS NULL");
                    $stmt->execute([$tenant_id, $date]);
                    $chartData[] = (float) $stmt->fetchColumn();
                }
            }
            ?>
            new ApexCharts(document.querySelector('#fuelSalesChart'), {
                chart: { type: 'area', height: 200, toolbar: { show: false } },
                series: [{ name: 'Sales', data: <?= json_encode($chartData) ?> }],
                xaxis: { categories: <?= json_encode($chartLabels) ?> },
                colors: ['#3b82f6'],
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } }
            }).render();

            // Receivables vs Payables
            <?php
            $stmt = $pdo->prepare("SELECT id, opening_debit_amount, opening_credit_amount FROM customers WHERE tenant_id = ? AND status = 'ACTIVE'");
            $stmt->execute([$tenant_id]);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $accountsReceivable = 0;
            foreach ($customers as $customer) {
                $opening = $customer['opening_debit_amount'] - $customer['opening_credit_amount'];
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM sale_invoice WHERE tenant_id = ? AND customer_id = ?");
                $stmt->execute([$tenant_id, $customer['id']]);
                $invoices = $stmt->fetchColumn();
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM receive_voucher WHERE tenant_id = ? AND customer_id = ?");
                $stmt->execute([$tenant_id, $customer['id']]);
                $payments = $stmt->fetchColumn();
                $accountsReceivable += $opening + $invoices - $payments;
            }

            $stmt = $pdo->prepare("SELECT id, opening_debit_amount, opening_credit_amount FROM suppliers WHERE tenant_id = ? AND status = 'ACTIVE'");
            $stmt->execute([$tenant_id]);
            $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $accountsPayable = 0;
            foreach ($suppliers as $supplier) {
                $opening = $supplier['opening_debit_amount'] - $supplier['opening_credit_amount'];
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM purchase_invoice WHERE tenant_id = ? AND supplier_id = ?");
                $stmt->execute([$tenant_id, $supplier['id']]);
                $invoices = $stmt->fetchColumn();
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payment_voucher WHERE tenant_id = ? AND supplier_id = ?");
                $stmt->execute([$tenant_id, $supplier['id']]);
                $payments = $stmt->fetchColumn();
                $accountsPayable += $opening - $invoices + $payments;
            }
            ?>
            new ApexCharts(document.querySelector('#accountsChart'), {
                chart: { type: 'donut', height: 200 },
                series: [<?= $accountsReceivable ?>, <?= abs($accountsPayable) ?>],
                labels: ['Receivable', 'Payable'],
                colors: ['#22c55e', '#ef4444'],
                legend: { position: 'bottom' }
            }).render();

            // Revenue vs Expenses
            <?php
            $revenueData = [];
            $expenseData = [];
            $revenueExpenseLabels = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $revenueExpenseLabels[] = date('M j', strtotime("-$i days"));
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_bill), 0) FROM sale_invoice WHERE tenant_id = ? AND DATE(sale_date) = ?");
                $stmt->execute([$tenant_id, $date]);
                $revenueData[] = (float) $stmt->fetchColumn();
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) FROM purchase_invoice WHERE tenant_id = ? AND DATE(purchase_date) = ?");
                $stmt->execute([$tenant_id, $date]);
                $expenseData[] = (float) $stmt->fetchColumn();
            }
            ?>
            new ApexCharts(document.querySelector('#revenueExpenseChart'), {
                chart: { type: 'bar', height: 200, toolbar: { show: false } },
                series: [{ name: 'Revenue', data: <?= json_encode($revenueData) ?> }, { name: 'Expenses', data: <?= json_encode($expenseData) ?> }],
                xaxis: { categories: <?= json_encode($revenueExpenseLabels) ?> },
                colors: ['#22c55e', '#ef4444'],
                dataLabels: { enabled: false },
                plotOptions: { bar: { columnWidth: '60%' } }
            }).render();

            // Cash Flow
            <?php
            $cashInData = [];
            $cashOutData = [];
            $cashFlowLabels = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $cashFlowLabels[] = date('M j', strtotime("-$i days"));
                
                // Cash In = All debits to Cash/Bank accounts from accounting_ledger
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(al.debit), 0) 
                    FROM accounting_ledger al
                    JOIN accounts a ON al.account_id = a.id
                    WHERE al.tenant_id = ? 
                      AND DATE(al.date) = ?
                      AND (a.id = 1 OR a.sub_account_id = '75')
                      AND al.debit > 0
                ");
                $stmt->execute([$tenant_id, $date]);
                $cashInData[] = (float) $stmt->fetchColumn();
                
                // Cash Out = All credits to Cash/Bank accounts from accounting_ledger
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(al.credit), 0)
                    FROM accounting_ledger al
                    JOIN accounts a ON al.account_id = a.id
                    WHERE al.tenant_id = ? 
                      AND DATE(al.date) = ?
                      AND (a.id = 1 OR a.sub_account_id = '75')
                      AND al.credit > 0
                ");
                $stmt->execute([$tenant_id, $date]);
                $cashOutData[] = (float) $stmt->fetchColumn();
            }
            ?>
            new ApexCharts(document.querySelector('#cashFlowChart'), {
                chart: { type: 'line', height: 200, toolbar: { show: false } },
                series: [{ name: 'Cash In', data: <?= json_encode($cashInData) ?> }, { name: 'Cash Out', data: <?= json_encode($cashOutData) ?> }],
                xaxis: { categories: <?= json_encode($cashFlowLabels) ?> },
                colors: ['#22c55e', '#ef4444'],
                stroke: { curve: 'smooth', width: 2 }
            }).render();

            function filterDashboard() {
                const dateFrom = document.getElementById('dateFrom').value;
                const dateTo = document.getElementById('dateTo').value;

                if (dateFrom && dateTo) {
                    window.location.href = `dashboard.php?date_from=${dateFrom}&date_to=${dateTo}`;
                }
            }

            function clearFilters() {
                window.location.href = 'dashboard.php';
            }

            async function openLedger() {
                const search = document.getElementById('ledgerSearch').value.trim();
                const type = document.querySelector('input[name="ledgerType"]:checked').value;

                if (!search) {
                    alert('Please enter a ' + (type === 'account' ? 'account' : type) + ' name or code');
                    return;
                }

                try {
                    let apiUrl, searchField;
                    
                    if (type === 'customer') {
                        apiUrl = '../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=customers';
                        searchField = 'customer';
                    } else if (type === 'supplier') {
                        apiUrl = '../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?type=distributions';
                        searchField = 'supplier';
                    } else if (type === 'account') {
                        apiUrl = '../../../server/api/vouchers/journal_voucher/get-accounts.php';
                        searchField = 'account';
                    }

                    const response = await fetch(apiUrl);
                    const data = await response.json();

                    if (data.success) {
                        let dataArray = type === 'account' ? data.accounts : data.data;
                        
                        if (dataArray && dataArray.length > 0) {
                            const searchLower = search.toLowerCase();
                            let found;
                            
                            if (type === 'account') {
                                found = dataArray.find(item => {
                                    const name = (item.name || '').toLowerCase();
                                    return name.includes(searchLower);
                                });
                            } else {
                                found = dataArray.find(item => {
                                    const name = (item.customer_name || item.supplier_name || '').toLowerCase();
                                    const code = (item.customer_code || item.supplier_code || '').toLowerCase();
                                    return name.includes(searchLower) || code.includes(searchLower);
                                });
                            }

                            if (found) {
                                let id, name;
                                
                                if (type === 'account') {
                                    id = found.sub_account_id || found.id;
                                    name = found.name;
                                    console.log('Account found:', found);
                                    console.log('Using sub_account_id:', id);
                                } else {
                                    id = found.id;
                                    name = found.customer_name || found.supplier_name;
                                }
                                
                                loadLedgerModal(id, name, type);
                            } else {
                                alert('No ' + searchField + ' found with that name or code');
                            }
                        } else {
                            alert('No ' + searchField + 's found');
                        }
                    } else {
                        alert('Error loading data');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error loading ledger');
                }
            }

            async function loadLedgerModal(id, name, type) {
                const modal = document.getElementById('ledgerModal');
                const modalTitle = document.getElementById('ledgerModalTitle');
                const modalBody = document.getElementById('ledgerModalBody');
                const dateFrom = document.getElementById('ledgerDateFrom').value;
                const dateTo = document.getElementById('ledgerDateTo').value;

                let titleText;
                if (type === 'customer') {
                    titleText = 'Customer Ledger - ' + name;
                } else if (type === 'supplier') {
                    titleText = 'Supplier Ledger - ' + name;
                } else if (type === 'account') {
                    titleText = 'General Ledger - ' + name;
                }
                
                if (dateFrom && dateTo) {
                    titleText += ` (${dateFrom} to ${dateTo})`;
                }
                modalTitle.textContent = titleText;
                
                modalBody.innerHTML = '<div class="ledger-loading"><i class="fas fa-spinner fa-spin"></i><p>Loading ledger data...</p></div>';
                modal.classList.add('show');

                try {
                    let apiUrl;
                    
                    if (type === 'customer') {
                        apiUrl = `../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=detailed&customer_id=${id}`;
                    } else if (type === 'supplier') {
                        apiUrl = `../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?type=detailed&supplier_id=${id}`;
                    } else if (type === 'account') {
                        apiUrl = `../../../server/api/financial_reports/general_ledger/general-ledger.php?accountNumber=${id}`;
                    }
                    
                    if (dateFrom && dateTo) {
                        if (type === 'account') {
                            apiUrl += `&startDate=${dateFrom}&endDate=${dateTo}`;
                        } else {
                            apiUrl += `&from_date=${dateFrom}&to_date=${dateTo}`;
                        }
                    }

                    console.log('Fetching from:', apiUrl);
                    const response = await fetch(apiUrl);
                    const result = await response.json();
                    console.log('API Response:', result);

                    if (result.success) {
                        if (type === 'account') {
                            // GL API returns different structure
                            renderGLData(result.entries, result.summary, name);
                        } else {
                            console.log('Rendering data:', result.data.length, 'transactions');
                            console.log('Opening balance:', result.opening_balance);
                            renderLedgerData(result.data, result.opening_balance || 0, type);
                        }
                    } else {
                        console.error('API returned error:', result);
                        modalBody.innerHTML = '<div class="ledger-error"><i class="fas fa-exclamation-circle"></i><p>' + (result.message || 'Failed to load ledger data') + '</p></div>';
                    }
                } catch (error) {
                    console.error('Error loading ledger:', error);
                    modalBody.innerHTML = '<div class="ledger-error"><i class="fas fa-exclamation-circle"></i><p>Error loading ledger data: ' + error.message + '</p></div>';
                }
            }

            function renderLedgerData(transactions, openingBalance, type) {
                const modalBody = document.getElementById('ledgerModalBody');

                if (!transactions || transactions.length === 0) {
                    modalBody.innerHTML = '<div class="ledger-error"><i class="fas fa-info-circle"></i><p>No transactions found for this period</p></div>';
                    return;
                }

                let totalDebit = 0;
                let totalCredit = 0;

                transactions.forEach(txn => {
                    if (txn.type !== 'opening_balance' && txn.type !== 'sub_account_header' && txn.type !== 'sub_account_total') {
                        totalDebit += parseFloat(txn.debit || 0);
                        totalCredit += parseFloat(txn.credit || 0);
                    }
                });

                const closingBalance = (openingBalance || 0) + totalDebit - totalCredit;

                let html = `
                    <div class="ledger-summary">
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Opening Balance</div>
                            <div class="ledger-summary-value ${openingBalance >= 0 ? 'positive' : 'negative'}">
                                Rs ${Math.abs(openingBalance).toLocaleString('en-PK', {maximumFractionDigits: 2})}
                                <span class="badge ${openingBalance >= 0 ? 'badge-dr' : 'badge-cr'}" style="margin-left: 0.5rem; font-size: 0.75rem;">
                                    ${openingBalance >= 0 ? 'Dr' : 'Cr'}
                                </span>
                            </div>
                        </div>
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Total Debit</div>
                            <div class="ledger-summary-value">Rs ${totalDebit.toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                        </div>
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Total Credit</div>
                            <div class="ledger-summary-value">Rs ${totalCredit.toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                        </div>
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Closing Balance</div>
                            <div class="ledger-summary-value ${closingBalance >= 0 ? 'positive' : 'negative'}">
                                Rs ${Math.abs(closingBalance).toLocaleString('en-PK', {maximumFractionDigits: 2})}
                                <span class="badge ${closingBalance >= 0 ? 'badge-dr' : 'badge-cr'}" style="margin-left: 0.5rem; font-size: 0.75rem;">
                                    ${closingBalance >= 0 ? 'Dr' : 'Cr'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="ledger-table-container">
                        <table class="ledger-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Reference</th>
                                    <th class="text-right">Debit</th>
                                    <th class="text-right">Credit</th>
                                    <th class="text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                transactions.forEach(txn => {
                    if (txn.type === 'sub_account_header') {
                        html += `
                            <tr class="sub-header">
                                <td colspan="6" style="padding: 0.875rem 1.25rem;">
                                    <i class="fas fa-folder-open" style="margin-right: 0.5rem; color: #3b82f6;"></i>
                                    ${txn.sub_account_name}
                                </td>
                            </tr>
                        `;
                    } else if (txn.type === 'sub_account_total') {
                        html += `
                            <tr class="sub-total">
                                <td colspan="3" style="font-weight: 700;">Sub Account Total</td>
                                <td class="text-right" style="font-weight: 700;">Rs ${parseFloat(txn.total_debit || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</td>
                                <td class="text-right" style="font-weight: 700;">Rs ${parseFloat(txn.total_credit || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</td>
                                <td class="text-right" style="font-weight: 700;">Rs ${parseFloat(txn.running_balance || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</td>
                            </tr>
                        `;
                    } else {
                        const debit = parseFloat(txn.debit || 0);
                        const credit = parseFloat(txn.credit || 0);
                        const balance = parseFloat(txn.running_balance || 0);
                        const isOpening = txn.type === 'opening_balance';
                        const rowClass = isOpening ? ' class="opening-row"' : '';

                        html += `
                            <tr${rowClass}>
                                <td>${txn.date || '-'}</td>
                                <td>${txn.description || '-'}</td>
                                <td>${txn.reference || '-'}</td>
                                <td class="text-right">${debit > 0 ? 'Rs ' + debit.toLocaleString('en-PK', {maximumFractionDigits: 2}) : '-'}</td>
                                <td class="text-right">${credit > 0 ? 'Rs ' + credit.toLocaleString('en-PK', {maximumFractionDigits: 2}) : '-'}</td>
                                <td class="text-right" style="font-weight: 600;">
                                    Rs ${Math.abs(balance).toLocaleString('en-PK', {maximumFractionDigits: 2})}
                                    <span class="badge ${balance >= 0 ? 'badge-dr' : 'badge-cr'}" style="margin-left: 0.5rem;">
                                        ${balance >= 0 ? 'Dr' : 'Cr'}
                                    </span>
                                </td>
                            </tr>
                        `;
                    }
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                modalBody.innerHTML = html;
            }

            function renderGLData(entries, summary, accountName) {
                const modalBody = document.getElementById('ledgerModalBody');

                if (!entries || entries.length === 0) {
                    modalBody.innerHTML = '<div class="ledger-error"><i class="fas fa-info-circle"></i><p>No transactions found for this account</p></div>';
                    return;
                }

                let html = `
                    <div class="ledger-summary">
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Total Debit</div>
                            <div class="ledger-summary-value">Rs ${parseFloat(summary.totalDebits || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                        </div>
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Total Credit</div>
                            <div class="ledger-summary-value">Rs ${parseFloat(summary.totalCredits || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                        </div>
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Transaction Count</div>
                            <div class="ledger-summary-value">${summary.transactionCount || 0}</div>
                        </div>
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Balance Status</div>
                            <div class="ledger-summary-value ${summary.isBalanced ? 'positive' : 'negative'}">
                                ${summary.isBalanced ? 'Balanced ✓' : 'Unbalanced'}
                            </div>
                        </div>
                    </div>

                    <div class="ledger-table-container">
                        <table class="ledger-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Reference</th>
                                    <th class="text-right">Debit</th>
                                    <th class="text-right">Credit</th>
                                    <th class="text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                entries.forEach(entry => {
                    const debit = parseFloat(entry.debit || 0);
                    const credit = parseFloat(entry.credit || 0);
                    const balance = parseFloat(entry.balance || 0);

                    html += `
                        <tr>
                            <td>${entry.date || '-'}</td>
                            <td>${entry.description || '-'}</td>
                            <td>${entry.reference || '-'}</td>
                            <td class="text-right">${debit > 0 ? 'Rs ' + debit.toLocaleString('en-PK', {maximumFractionDigits: 2}) : '-'}</td>
                            <td class="text-right">${credit > 0 ? 'Rs ' + credit.toLocaleString('en-PK', {maximumFractionDigits: 2}) : '-'}</td>
                            <td class="text-right" style="font-weight: 600;">
                                Rs ${Math.abs(balance).toLocaleString('en-PK', {maximumFractionDigits: 2})}
                                <span class="badge ${balance >= 0 ? 'badge-dr' : 'badge-cr'}" style="margin-left: 0.5rem;">
                                    ${balance >= 0 ? 'Dr' : 'Cr'}
                                </span>
                            </td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                modalBody.innerHTML = html;
            }

            function closeLedgerModal() {
                const modal = document.getElementById('ledgerModal');
                modal.classList.remove('show');
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeLedgerModal();
                }
            });

            document.getElementById('ledgerModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeLedgerModal();
                }
            });

            document.getElementById('ledgerSearch')?.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    openLedger();
                }
            });

            function setDateRange(days) {
                const dateFrom = document.getElementById('ledgerDateFrom');
                const dateTo = document.getElementById('ledgerDateTo');
                const today = new Date();
                
                // Remove active class from all buttons
                document.querySelectorAll('.date-bucket-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                event.target.classList.add('active');
                
                if (days === 0) {
                    // All time
                    dateFrom.value = '';
                    dateTo.value = '';
                } else {
                    // Calculate date range
                    const fromDate = new Date();
                    fromDate.setDate(today.getDate() - days);
                    
                    dateFrom.value = fromDate.toISOString().split('T')[0];
                    dateTo.value = today.toISOString().split('T')[0];
                }
            }

            // Clear bucket selection when manually changing dates
            document.getElementById('ledgerDateFrom')?.addEventListener('change', function() {
                document.querySelectorAll('.date-bucket-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
            });

            document.getElementById('ledgerDateTo')?.addEventListener('change', function() {
                document.querySelectorAll('.date-bucket-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
            });

            // Load Balance Sheet
            fetch('../../../server/api/financial_reports/balance_sheet/balance-sheet.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const bs = data.data;
                        let totalAssets = 0, totalLiabilities = 0, totalEquity = 0;

                        let assetsHTML = '';
                        if (bs[1]) {
                            Object.values(bs[1]).forEach(sub => {
                                sub.accounts.forEach(acc => {
                                    assetsHTML += `<tr><td>${acc.name}</td><td>Rs ${acc.balance.toLocaleString()}</td></tr>`;
                                    totalAssets += acc.balance;
                                });
                            });
                        }
                        assetsHTML += `<tr style="font-weight: 600; background: #f0f9ff;"><td>Total Assets</td><td>Rs ${totalAssets.toLocaleString()}</td></tr>`;

                        let liabilitiesHTML = '';
                        if (bs[2]) {
                            Object.values(bs[2]).forEach(sub => {
                                sub.accounts.forEach(acc => {
                                    liabilitiesHTML += `<tr><td>${acc.name}</td><td>Rs ${acc.balance.toLocaleString()}</td></tr>`;
                                    totalLiabilities += acc.balance;
                                });
                            });
                        }
                        liabilitiesHTML += `<tr style="font-weight: 600; background: #fef2f2;"><td>Total Liabilities</td><td>Rs ${totalLiabilities.toLocaleString()}</td></tr>`;

                        if (bs[3]) {
                            Object.values(bs[3]).forEach(sub => {
                                sub.accounts.forEach(acc => {
                                    liabilitiesHTML += `<tr><td>${acc.name}</td><td>Rs ${acc.balance.toLocaleString()}</td></tr>`;
                                    totalEquity += acc.balance;
                                });
                            });
                        }
                        liabilitiesHTML += `<tr style="font-weight: 600; background: #f0fdf4;"><td>Total Equity</td><td>Rs ${totalEquity.toLocaleString()}</td></tr>`;

                        document.getElementById('assetsBody').innerHTML = assetsHTML;
                        document.getElementById('liabilitiesBody').innerHTML = liabilitiesHTML;
                    }
                })
                .catch(error => console.error('Balance sheet error:', error));

            // Branch-wise Sales Chart
            <?php
            $stmt = $pdo->prepare("
            SELECT 
                COALESCE(pb.branch_name, b.branch_name) as branch_name,
                COALESCE(SUM(si.net_amount), 0) as total_sales 
            FROM branches b 
            LEFT JOIN branches pb ON b.parent_branch_id = pb.id
            LEFT JOIN sale_invoice si ON b.id = si.branch_id AND si.tenant_id = ?
            WHERE b.tenant_id = ?
            GROUP BY COALESCE(b.parent_branch_id, b.id), COALESCE(pb.branch_name, b.branch_name)
            ORDER BY total_sales DESC 
            LIMIT 10
        ");
            $stmt->execute([$tenant_id, $tenant_id]);
            $branchSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $branchLabels = array_column($branchSales, 'branch_name');
            $branchData = array_column($branchSales, 'total_sales');
            ?>
            new ApexCharts(document.querySelector('#branchSalesChart'), {
                chart: { type: 'bar', height: 200, toolbar: { show: false } },
                series: [{ name: 'Sales', data: <?= json_encode($branchData) ?> }],
                xaxis: { categories: <?= json_encode($branchLabels) ?> },
                colors: ['#3b82f6'],
                plotOptions: { bar: { horizontal: true, barHeight: '70%' } },
                dataLabels: { enabled: false }
            }).render();

            // Territory-wise Sales Chart
            <?php
            $stmt = $pdo->prepare("
            SELECT 
                COALESCE(r.region_name, 'Unknown') as territory,
                COALESCE(SUM(si.net_amount), 0) as total_sales 
            FROM sale_invoice si
            LEFT JOIN customers c ON si.customer_id = c.id
            LEFT JOIN regions r ON c.region_id = r.id
            WHERE si.tenant_id = ?
            GROUP BY r.id, r.region_name
            ORDER BY total_sales DESC 
            LIMIT 10
        ");
            $stmt->execute([$tenant_id]);
            $territorySales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $territoryLabels = array_column($territorySales, 'territory');
            $territoryData = array_column($territorySales, 'total_sales');
            ?>
            new ApexCharts(document.querySelector('#territorySalesChart'), {
                chart: { type: 'bar', height: 200, toolbar: { show: false } },
                series: [{ name: 'Sales', data: <?= json_encode($territoryData) ?> }],
                xaxis: { categories: <?= json_encode($territoryLabels) ?> },
                colors: ['#22c55e'],
                plotOptions: { bar: { horizontal: true, barHeight: '70%' } },
                dataLabels: { enabled: false }
            }).render();

            // Bank Statement Functions
            let selectedBankAccount = null;

            async function openBankStatementModal() {
                const modal = document.getElementById('bankStatementModal');
                modal.classList.add('show');
                await loadBankAccounts();
            }

            function closeBankStatementModal() {
                const modal = document.getElementById('bankStatementModal');
                modal.classList.remove('show');
                selectedBankAccount = null;
                document.getElementById('bankAccountsView').style.display = 'grid';
                document.getElementById('bankStatementView').classList.remove('active');
            }

            async function loadBankAccounts() {
                const container = document.getElementById('bankAccountsView');
                container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: #64748b;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i><p>Loading bank accounts...</p></div>';

                try {
                    const response = await fetch('../../../server/api/financial_reports/cash_flow/filter-options.php');
                    const result = await response.json();

                    if (result.success && result.bank_accounts && result.bank_accounts.length > 0) {
                        let html = '';
                        result.bank_accounts.forEach(bank => {
                            const logoPath = bank.bank_logo_path 
                                ? `../../assets/uploads/bank_logo/${bank.bank_logo_path}` 
                                : '../../assets/uploads/bank_logo/default-bank.png';
                            
                            html += `
                                <div class="bank-account-card" onclick="selectBankAccount(${bank.id}, '${bank.bank_name.replace(/'/g, "\\\'")}')">
                                    <img src="${logoPath}" alt="${bank.bank_name}" class="bank-logo" onerror="this.src='../../assets/uploads/bank_logo/default-bank.png'">
                                    <div class="bank-info">
                                        <h4>${bank.bank_name}</h4>
                                        <p style="font-weight: 500; color: var(--body); margin-bottom: 0.25rem;">${bank.account_title || 'N/A'}</p>
                                        <p style="font-size: 0.8rem;">${bank.account_number || 'N/A'}</p>
                                    </div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: #64748b;"><i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem;"></i><p>No bank accounts found</p></div>';
                    }
                } catch (error) {
                    console.error('Error loading bank accounts:', error);
                    container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: #dc2626;"><i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 1rem;"></i><p>Error loading bank accounts</p></div>';
                }
            }

            function selectBankAccount(bankId, bankName) {
                selectedBankAccount = { id: bankId, name: bankName };
                document.getElementById('bankModalTitle').textContent = `Bank Statement - ${bankName}`;
                document.getElementById('bankAccountsView').style.display = 'none';
                document.getElementById('bankStatementView').classList.add('active');
                loadBankStatement();
            }

            function backToBankSelection() {
                document.getElementById('bankModalTitle').textContent = 'Select Bank Account';
                document.getElementById('bankAccountsView').style.display = 'grid';
                document.getElementById('bankStatementView').classList.remove('active');
                selectedBankAccount = null;
            }

            async function loadBankStatement() {
                if (!selectedBankAccount) return;

                const tableBody = document.getElementById('statementTableBody');
                const summaryDiv = document.getElementById('statementSummary');
                
                tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 3rem; color: #64748b;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i><br>Loading statement...</td></tr>';
                summaryDiv.innerHTML = '';

                try {
                    const dateRange = document.getElementById('statementDateRange').value;
                    const fromDate = document.getElementById('statementFromDate').value;
                    const toDate = document.getElementById('statementToDate').value;

                    let url = `../../../server/api/financial_reports/cash_flow/cash-flow.php?bank_account=${selectedBankAccount.id}&date_range=${dateRange}`;
                    
                    if (dateRange === 'custom' && fromDate && toDate) {
                        url += `&from_date=${fromDate}&to_date=${toDate}`;
                    }

                    const response = await fetch(url);
                    const result = await response.json();

                    if (result.success && result.data) {
                        // Render summary
                        const balances = result.balances || {};
                        summaryDiv.innerHTML = `
                            <div class="ledger-summary-card">
                                <div class="ledger-summary-label">Opening Balance</div>
                                <div class="ledger-summary-value">Rs ${(balances.opening_balance || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                            </div>
                            <div class="ledger-summary-card">
                                <div class="ledger-summary-label">Total Inflow</div>
                                <div class="ledger-summary-value positive">Rs ${(balances.total_inflow || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                            </div>
                            <div class="ledger-summary-card">
                                <div class="ledger-summary-label">Total Outflow</div>
                                <div class="ledger-summary-value negative">Rs ${(balances.total_outflow || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                            </div>
                            <div class="ledger-summary-card">
                                <div class="ledger-summary-label">Closing Balance</div>
                                <div class="ledger-summary-value">Rs ${(balances.closing_balance || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                            </div>
                        `;

                        // Render transactions
                        if (result.data.length > 0) {
                            let html = '';
                            result.data.forEach(txn => {
                                html += `
                                    <tr>
                                        <td>${txn.date || '-'}</td>
                                        <td>${txn.description || '-'}</td>
                                        <td>${txn.reference || '-'}</td>
                                        <td class="text-right">${txn.inflow > 0 ? 'Rs ' + parseFloat(txn.inflow).toLocaleString('en-PK', {maximumFractionDigits: 2}) : '-'}</td>
                                        <td class="text-right">${txn.outflow > 0 ? 'Rs ' + parseFloat(txn.outflow).toLocaleString('en-PK', {maximumFractionDigits: 2}) : '-'}</td>
                                        <td class="text-right" style="font-weight: 600;">Rs ${parseFloat(txn.bank_balance || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</td>
                                    </tr>
                                `;
                            });
                            tableBody.innerHTML = html;
                        } else {
                            tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 3rem; color: #64748b;">No transactions found for this period</td></tr>';
                        }
                    } else {
                        tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 3rem; color: #dc2626;">Error loading statement</td></tr>';
                    }
                } catch (error) {
                    console.error('Error loading bank statement:', error);
                    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 3rem; color: #dc2626;">Error loading statement</td></tr>';
                }
            }

            // Toggle custom date inputs for bank statement
            document.getElementById('statementDateRange')?.addEventListener('change', function() {
                const fromDate = document.getElementById('statementFromDate');
                const toDate = document.getElementById('statementToDate');
                
                if (this.value === 'custom') {
                    fromDate.style.display = 'block';
                    toDate.style.display = 'block';
                } else {
                    fromDate.style.display = 'none';
                    toDate.style.display = 'none';
                }
            });

            // Close modal on escape or outside click
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeBankStatementModal();
                }
            });

           document.getElementById('bankStatementModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeBankStatementModal();
                }
            });

            // ---------- Keyboard Shortcuts (management UI) ----------
            let capturedCombo = '';

            const shortcutKeyInput = document.getElementById('shortcutKeyInput');
            shortcutKeyInput?.addEventListener('keydown', function (e) {
                e.preventDefault();
                if (['Control', 'Alt', 'Shift', 'Meta'].includes(e.key)) return;

                const parts = [];
                if (e.ctrlKey) parts.push('Ctrl');
                if (e.altKey) parts.push('Alt');
                if (e.shiftKey) parts.push('Shift');
                parts.push(e.key.length === 1 ? e.key.toUpperCase() : e.key);

                capturedCombo = parts.join('+');
                shortcutKeyInput.value = capturedCombo;
            });

            async function saveShortcut() {
                const select = document.getElementById('shortcutPageSelect');
                const pageKey = select.value;
                const pageLabel = select.options[select.selectedIndex]?.dataset.label || '';

                if (!pageKey) { alert('Pehle koi page select karein'); return; }
                if (!capturedCombo) { alert('Key box par click karke shortcut key press karein'); return; }

                try {
                    const res = await fetch('../../../server/api/shortcuts/shortcuts.php?action=save', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ key_combo: capturedCombo, page_key: pageKey, page_label: pageLabel })
                    });
                    const data = await res.json();
                    if (data.success) {
                        capturedCombo = '';
                        shortcutKeyInput.value = '';
                        select.value = '';
                        loadShortcutsUI();
                    } else {
                        alert(data.message || 'Save failed');
                    }
                } catch (err) {
                    console.error(err);
                    alert('Error saving shortcut');
                }
            }

            async function deleteShortcut(id) {
                try {
                    await fetch('../../../server/api/shortcuts/shortcuts.php?action=delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    loadShortcutsUI();
                } catch (err) {
                    console.error(err);
                }
            }

            async function loadShortcutsUI() {
                const list = document.getElementById('shortcutsList');
                if (!list) return;
                try {
                    const res = await fetch('../../../server/api/shortcuts/shortcuts.php?action=list');
                    const data = await res.json();
                    if (data.success && data.data.length) {
                        list.innerHTML = data.data.map(s => `
                            <span style="display:flex; align-items:center; gap:0.5rem; background:white; border:1.5px solid #d6dbe4; border-radius:0.5rem; padding:0.4rem 0.75rem; font-size:13px;">
                                <strong style="color:var(--primary);">${s.key_combo}</strong> → ${s.page_label}
                                <i class="fas fa-times" style="cursor:pointer; color:#94a3b8;" onclick="deleteShortcut(${s.id})"></i>
                            </span>
                        `).join('');
                    } else {
                        list.innerHTML = '<span style="color:#94a3b8; font-size:13px;">Set a shortcut.</span>';
                    }
                } catch (err) {
                    console.error(err);
                }
            }

            loadShortcutsUI();
        </script>
    


        <script src="../../assets/js/dashboard/dashboard-main.js"></script>
    <?php endif; ?>

    <!-- Ledger Modal -->
    <div class="ledger-modal" id="ledgerModal">
        <div class="ledger-modal-content">
            <div class="ledger-modal-header">
                <h3 id="ledgerModalTitle">Ledger</h3>
                <button class="ledger-modal-close" onclick="closeLedgerModal()">&times;</button>
            </div>
            <div class="ledger-modal-body" id="ledgerModalBody">
                <div class="ledger-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading...</p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>