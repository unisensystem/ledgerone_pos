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

// Get date filters — default to the current month (not just today), so a
// fresh page load shows "this month's" figures instead of a single day, and
// the date inputs' displayed values match what's actually being shown.
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$hasDateFilter = true;

// Get tenant's base currency symbol (falls back to Rs if none is set)
$stmt = $pdo->prepare("
    SELECT c.symbol
    FROM tenant_currencies tc
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
    LIMIT 1
");
$stmt->execute([$tenant_id]);
$currencySymbol = $stmt->fetchColumn();
if (!$currencySymbol) {
    $currencySymbol = 'Rs';
}

// Revenue & Purchases KPIs — Gross (Total Bill, before invoice-level discount/
// shipping/tax) vs Net (Net Amount, the final total) summed directly from the
// invoice header tables, so these match exactly what a single invoice's own
// "Total Bill" / "Net Amount" fields show (e.g. pos-add.php?edit=...).
if ($hasDateFilter) {
    $revStmt = $pdo->prepare("SELECT COALESCE(SUM(total_bill), 0) as gross, COALESCE(SUM(net_amount), 0) as net FROM sale_invoice WHERE tenant_id = ? AND sale_date BETWEEN ? AND ?");
    $revStmt->execute([$tenant_id, $dateFrom, $dateTo]);
} else {
    $revStmt = $pdo->prepare("SELECT COALESCE(SUM(total_bill), 0) as gross, COALESCE(SUM(net_amount), 0) as net FROM sale_invoice WHERE tenant_id = ?");
    $revStmt->execute([$tenant_id]);
}
$revenueTotals = $revStmt->fetch(PDO::FETCH_ASSOC);
$revenueGross = (float) $revenueTotals['gross'];
$revenueNet = (float) $revenueTotals['net'];

if ($hasDateFilter) {
    $purStmt = $pdo->prepare("SELECT COALESCE(SUM(total_bill), 0) as gross, COALESCE(SUM(net_amount), 0) as net FROM purchase_invoice WHERE tenant_id = ? AND purchase_date BETWEEN ? AND ?");
    $purStmt->execute([$tenant_id, $dateFrom, $dateTo]);
} else {
    $purStmt = $pdo->prepare("SELECT COALESCE(SUM(total_bill), 0) as gross, COALESCE(SUM(net_amount), 0) as net FROM purchase_invoice WHERE tenant_id = ?");
    $purStmt->execute([$tenant_id]);
}
$purchasesTotals = $purStmt->fetch(PDO::FETCH_ASSOC);
$purchasesGross = (float) $purchasesTotals['gross'];
$purchasesNet = (float) $purchasesTotals['net'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/dashboard/dashboard.css?v=<?php echo time(); ?>">
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
            min-width: 113px;
            height: 44px;
            padding: 0 1rem;
            border: 1.5px solid #d6dbe4;
            border-radius: 0.5rem;
            font-size: 14px;
        }

        /* Professional custom dropdown styling for the "Select Page" shortcut picker
           (a native <select> otherwise renders with the browser's own plain look,
           inconsistent with the rest of this styled UI). */
        select.ledger-input {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: #fff;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3e%3cpath d='M1 1.5L6 6.5L11 1.5' stroke='%2364748b' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' fill='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.9rem center;
            padding-right: 2.5rem;
            color: #1e293b;
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        select.ledger-input:hover {
            border-color: #aab3c2;
        }

        select.ledger-input:focus {
            outline: none;
            border-color: var(--primary, #1f7bff);
            box-shadow: 0 0 0 3px rgba(31, 123, 255, 0.15);
        }

        select.ledger-input option {
            color: #1e293b;
            padding: 0.5rem;
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
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
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
            content: '??';
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
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
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

        .todo-trigger-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #dc2626;
            color: white;
            font-size: 11px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }

        .todo-modal {
            display: none !important;
            position: fixed !important;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.6);
            z-index: 99999 !important;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            margin: 0;
            padding: 0;
        }

        .todo-modal.show {
            display: flex !important;
            animation: fadeIn 0.2s ease;
        }

        .todo-modal-content {
            background: white;
            width: 95%;
            max-width: 1000px;
            max-height: 85vh;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: slideUp 0.3s ease;
        }

        .todo-modal-header {
            padding: 1.25rem 1.5rem;
            background: linear-gradient(135deg, #1f7bff 0%, #1559b8 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .todo-modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: white !important;
        }

        .todo-modal-close {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .todo-modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .todo-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            background: #f8fafc;
        }

        .todo-add-form {
            display: grid;
            grid-template-columns: 1.2fr 1.8fr 140px 110px auto;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            align-items: start;
        }

        .todo-add-form textarea {
            resize: vertical;
            min-height: 44px;
            padding: 0.6rem 1rem;
            border: 1.5px solid #d6dbe4;
            border-radius: 0.5rem;
            font-size: 14px;
            font-family: inherit;
        }

        .todo-add-form .btn {
            height: 44px;
            white-space: nowrap;
        }

        .todo-table-wrap {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .todo-table {
            width: 100%;
            border-collapse: collapse;
        }

        .todo-table thead {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 2px solid #e2e8f0;
        }

        .todo-table th {
            padding: 0.85rem 1rem;
            text-align: left;
            font-weight: 700;
            font-size: 11px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            white-space: nowrap;
        }

        .todo-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #334155;
            vertical-align: top;
        }

        .todo-table tbody tr:last-child td {
            border-bottom: none;
        }

        .todo-table tbody tr:hover {
            background: #f8fafc;
        }

        .todo-table tbody tr.overdue {
            background: #fef2f2;
        }

        .todo-table tbody tr.done .todo-heading {
            text-decoration: line-through;
            color: #94a3b8;
        }

        .todo-heading {
            font-weight: 600;
            color: #0f172a;
        }

        .todo-desc {
            color: #64748b;
            font-size: 12.5px;
            max-width: 260px;
            word-break: break-word;
        }

        .todo-day-tag {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
        }

        .todo-overdue-tag {
            display: inline-block;
            margin-top: 0.25rem;
            font-size: 10px;
            font-weight: 700;
            color: #dc2626;
            background: #fee2e2;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            text-transform: uppercase;
        }

        .todo-delete {
            cursor: pointer;
            color: #cbd5e1;
            font-size: 15px;
        }

        .todo-delete:hover {
            color: #dc2626;
        }

        .todo-empty {
            text-align: center;
            color: #94a3b8;
            padding: 2.5rem 1rem;
            font-size: 14px;
        }

        .todo-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #dc2626;
            color: white;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            z-index: 100000;
            max-width: 320px;
            font-size: 14px;
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            animation: slideUp 0.3s ease;
        }

        .todo-toast i {
            font-size: 1.25rem;
            margin-top: 2px;
        }

        @media (max-width: 768px) {
            .todo-add-form {
                grid-template-columns: 1fr;
            }

            .todo-modal-content {
                width: 100%;
                height: 100vh;
                max-height: 100vh;
                border-radius: 0;
            }

            .todo-table-wrap {
                overflow-x: auto;
            }

            .todo-table {
                min-width: 650px;
            }
        }
    </style>
</head>

<body>
    <?php if (!$hasPermission): ?>
        <div class="container"
            style="display: flex; justify-content: center; align-items: center; min-height: 100vh; text-align: center;">
            <div>
                <h1 style="color: var(--danger); font-size: 3rem; margin-bottom: 1rem;">??</h1>
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
                    <input type="date" id="dateFrom" value="<?= $dateFrom ?>" class="btn btn-secondary">
                    <input type="date" id="dateTo" value="<?= $dateTo ?>" class="btn btn-secondary">
                    <button class="btn btn-primary" onclick="filterDashboard()">Filter</button>
                    <button class="btn btn-secondary" onclick="clearFilters()">Clear</button>
                </div>
            </div>

            <!-- Global Date Lock Widget (collapsible, tucked into right edge) -->
            <div id="dateLockWidgetWrap" style="position: fixed; top: 60px; right: 0; z-index: 9999; display: flex; align-items: center; font-family: sans-serif; font-size: 13px;">

                <!-- Sliding panel -->
                <div id="dateLockPanel" style="display: flex; align-items: center; gap: 6px; background: #fff; padding: 6px 10px; border-radius: 8px 0 0 8px; box-shadow: -2px 2px 6px rgba(0,0,0,0.15);
         max-width: 0; overflow: hidden; opacity: 0; transition: max-width 0.25s ease, opacity 0.2s ease; white-space: nowrap;">
                    <input type="date" id="globalLockDate" style="border: 1px solid #ccc; border-radius: 4px; padding: 4px 6px; font-size: 13px;">
                    <button type="button" id="globalDateLockBtn" style="border: none; background: transparent; cursor: pointer; font-size: 16px; padding: 4px;">
                        <i class="fas fa-lock-open" id="globalDateLockIcon" style="color: #888;"></i>
                    </button>
                </div>

                <!-- Always-visible tab handle -->
                <button type="button" id="dateLockToggleTab" title="Date Lock" style="border: none; cursor: pointer; background: #fff; box-shadow: -2px 2px 6px rgba(0,0,0,0.15); border-radius: 8px 0 0 8px; padding: 8px 6px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-lock" id="dateLockTabIcon" style="color: #888; font-size: 14px;"></i>
                </button>
            </div>
            <!-- Global Search -->
            <div class="quick-actions" style="position: relative;">
                <div class="ledger-search-box" style="position: relative;">
                    <input type="text" id="globalSearchInput" class="ledger-input"
                        placeholder="Search invoice number, voucher, amount... (e.g. INV-001 or 400)"
                        autocomplete="off" style="font-size: 15px;">
                </div>
                <div id="globalSearchResults" style="
                    display:none; position:absolute; top:100%; left:0; right:0;
                    background:white; border:1.5px solid #d6dbe4; border-radius:0.5rem;
                    margin-top:0.5rem; max-height:400px; overflow-y:auto; z-index:500;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.12);">
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
                    <div class="kpi-value-row">
                        <span class="kpi-value kpi-value-compact"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($revenueGross, 0) ?></span>
                        <span class="kpi-value-tag">Gross</span>
                    </div>
                    <div class="kpi-gross-net-divider"></div>
                    <div class="kpi-value-row">
                        <span class="kpi-value kpi-value-compact"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($revenueNet, 0) ?></span>
                        <span class="kpi-value-tag">Net</span>
                    </div>
                    <div class="kpi-label">Revenue <?= $hasDateFilter ? 'Selected Period' : 'All Time' ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value-row">
                        <span class="kpi-value kpi-value-compact"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($purchasesGross, 0) ?></span>
                        <span class="kpi-value-tag">Gross</span>
                    </div>
                    <div class="kpi-gross-net-divider"></div>
                    <div class="kpi-value-row">
                        <span class="kpi-value kpi-value-compact"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($purchasesNet, 0) ?></span>
                        <span class="kpi-value-tag">Net</span>
                    </div>
                    <div class="kpi-label">Purchases <?= $hasDateFilter ? 'Selected Period' : 'All Time' ?></div>
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

                            <!-- Lists -->
                            <option value="sale-invoice" data-label="Sale Invoice List">Sale Invoice </option>
                            <option value="med-sale-invoice" data-label="Med Sale Invoice List">Med Sale Invoice </option>
                            <option value="sale-tax-invoice" data-label="Sale Tax Invoice List">Sale Tax Invoice </option>
                            <option value="purchase-invoice" data-label="Purchase Invoice List">Purchase Invoice </option>
                            <option value="receive-voucher" data-label="Receive Voucher List">Receive Voucher </option>
                            <option value="payment-voucher" data-label="Payment Voucher List">Payment Voucher </option>
                            <option value="expense-voucher" data-label="Expense Voucher List">Expense Voucher </option>
                            <option value="customer-ledger" data-label="Customer Ledger">Customer Ledger</option>
                            <option value="supplier-ledger" data-label="Supplier Ledger">Supplier Ledger</option>
                            <option value="pos-invoice" data-label="POS Invoice">POS Invoice</option>
                            <option value="traders-sale-invoice" data-label="Traders Sale Invoice">Traders Sale Invoice</option>
                            <option value="new-product" data-label="Product List">Product </option>
                            <option value="new-customer" data-label="New Customer List">New Customer List</option>
                            <option value="new-supplier" data-label="New Supplier List">New Supplier List</option>

                            <!-- Entries -->
                            <option value="sale-invoice-entry" data-label="Sale Invoice Entry">Sale Invoice Entry</option>
                            <option value="purchase-invoice-entry" data-label="Purchase Invoice Entry">Purchase Invoice Entry</option>
                            <option value="rv-entry" data-label="Receive Voucher Entry">Receive Voucher Entry</option>
                            <option value="pv-entry" data-label="Payment Voucher Entry">Payment Voucher Entry</option>
                            <option value="delivery-challan-entry" data-label="Delivery Challan Entry">Delivery Challan Entry</option>
                            <option value="new-customer-entry" data-label="New Customer Entry">New Customer Entry</option>
                            <option value="new-supplier-entry" data-label="New Supplier Entry">New Supplier Entry</option>
                            <option value="new-item-entry" data-label="New Item Entry">New Item Entry</option>
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
                    <a href="#" class="action-btn" style="position: relative;" onclick="openTodoModal(); return false;">
                        <i class="fas fa-list-check"></i>
                        <span>Todo List</span>
                        <span id="todoBadge" class="todo-trigger-badge" style="display:none;">0</span>
                    </a>

                    <a href="../settings/posting_lock/posting-lock.php" class="action-btn">
                        <i class="fas fa-lock"></i>
                        <span>Posting Lock</span>
                    </a>


                </div>
            </div>

            <!-- ✅ SAHI: bahar hai grid ke -->
            <div id="leaveApprovalsCard" class="card" style="display:none; margin-bottom:1rem; border-left:4px solid #e8b23f;">
                <h3 class="card-title" style="display:flex; align-items:center; gap:0.5rem;">
                    <i class="fas fa-user-clock" style="color:#e8b23f;"></i>
                    Leave Approvals Pending
                    <span id="leaveApprovalsCount" class="todo-trigger-badge" style="position:static;">0</span>
                </h3>
                <div id="leaveApprovalsList"></div>
            </div>

            <!-- Audit & Activity Analytics -->
            <div class="quick-actions">
                <h2 class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Audit & Activity Analytics</span>
                    <a href="../system_setup/admin_panel/activity-center.php" style="font-size: 13px; font-weight: 600; color: var(--primary); text-decoration: none; white-space: nowrap;">View Full Audit <i class="fas fa-arrow-right"></i></a>
                </h2>

                <?php
                // Critical = a deletion in a module where losing a record has real business/financial
                // impact (invoices, payments, stock, user/permission changes) - not every delete
                // counts (e.g. deleting an unused tax rate isn't "critical").
                $criticalModules = [
                    'Sale Invoice',
                    'Sale Tax Invoice',
                    'Meter Invoice',
                    'Purchase Invoice',
                    'Purchase Tax Invoice',
                    'Payment Voucher',
                    'Receive Voucher',
                    'Journal Voucher',
                    'Expense Voucher',
                    'Stock Adjustment',
                    'User',
                    'Role',
                    'Role Permission',
                    'Bank Account',
                    'Post Dated Cheque',
                ];
                $criticalPlaceholders = implode(',', array_fill(0, count($criticalModules), '?'));

                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM login_attempts WHERE tenant_id = ? AND attempt_status = 'success' AND DATE(attempted_at) = CURDATE()");
                $stmt->execute([$tenant_id]);
                $activeUsersToday = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE tenant_id = ? AND attempt_status = 'success' AND DATE(attempted_at) = CURDATE()");
                $stmt->execute([$tenant_id]);
                $totalLoginsToday = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT action, COUNT(*) AS cnt FROM activity_logs WHERE tenant_id = ? AND DATE(created_at) = CURDATE() GROUP BY action");
                $stmt->execute([$tenant_id]);
                $actionCountsToday = ['create' => 0, 'update' => 0, 'delete' => 0];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $actionCountsToday[$row['action']] = (int) $row['cnt'];
                }

                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM activity_logs
                    WHERE tenant_id = ? AND action = 'delete' AND DATE(created_at) = CURDATE() AND module IN ($criticalPlaceholders)
                ");
                $stmt->execute(array_merge([$tenant_id], $criticalModules));
                $criticalToday = (int) $stmt->fetchColumn();

                // 7-day trend
                $trendLabels = [];
                $trendData = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $trendLabels[] = date('D', strtotime("-$i days"));
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE tenant_id = ? AND DATE(created_at) = ?");
                    $stmt->execute([$tenant_id, $date]);
                    $trendData[] = (int) $stmt->fetchColumn();
                }

                // Module-wise activity, last 30 days, top 5
                $stmt = $pdo->prepare("
                    SELECT module, COUNT(*) AS cnt
                    FROM activity_logs
                    WHERE tenant_id = ? AND created_at >= (NOW() - INTERVAL 30 DAY)
                    GROUP BY module ORDER BY cnt DESC LIMIT 5
                ");
                $stmt->execute([$tenant_id]);
                $moduleBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $moduleMax = $moduleBreakdown ? max(array_column($moduleBreakdown, 'cnt')) : 1;

                // Most active users, last 30 days, top 4
                $stmt = $pdo->prepare("
                    SELECT user_name, COUNT(*) AS cnt
                    FROM activity_logs
                    WHERE tenant_id = ? AND created_at >= (NOW() - INTERVAL 30 DAY)
                    GROUP BY user_name ORDER BY cnt DESC LIMIT 4
                ");
                $stmt->execute([$tenant_id]);
                $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $topUserMax = $topUsers ? max(array_column($topUsers, 'cnt')) : 1;

                // Anomaly check: today's deletions vs the trailing 7-day average (excluding today) -
                // only surfaces when there's genuinely something to flag, not a permanent banner.
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) / 7 FROM activity_logs
                    WHERE tenant_id = ? AND action = 'delete' AND created_at >= (NOW() - INTERVAL 7 DAY) AND DATE(created_at) < CURDATE()
                ");
                $stmt->execute([$tenant_id]);
                $avgDailyDeletions = (float) $stmt->fetchColumn();
                $deletionsToday = $actionCountsToday['delete'];
                $showAnomaly = $deletionsToday >= 5 && $deletionsToday > (2 * max($avgDailyDeletions, 1));
                ?>

                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-value"><?= $activeUsersToday ?></div>
                        <div class="kpi-label">Active Users Today</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value"><?= $totalLoginsToday ?></div>
                        <div class="kpi-label">Total Logins Today</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value"><?= $actionCountsToday['create'] ?></div>
                        <div class="kpi-label">Records Created Today</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value"><?= $actionCountsToday['update'] ?></div>
                        <div class="kpi-label">Records Updated Today</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value"><?= $actionCountsToday['delete'] ?></div>
                        <div class="kpi-label">Records Deleted Today</div>
                    </div>
                    <div class="kpi-card" style="<?= $criticalToday > 0 ? 'border-left: 4px solid var(--error);' : '' ?>">
                        <div class="kpi-value" style="<?= $criticalToday > 0 ? 'color: var(--error);' : '' ?>"><?= $criticalToday ?></div>
                        <div class="kpi-label">Critical Activities Today</div>
                    </div>
                </div>

                <?php if ($showAnomaly): ?>
                    <div style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 0.75rem; padding: 0.9rem 1.25rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem; color: #991b1b; font-size: 14px;">
                        <i class="fas fa-triangle-exclamation" style="font-size: 1.1rem;"></i>
                        <span><strong>Attention:</strong> <?= $deletionsToday ?> records were deleted today, well above the recent daily average (~<?= round($avgDailyDeletions) ?>). <a href="../system_setup/admin_panel/activity-center.php?view=deleted-records" style="color: #991b1b; font-weight: 600;">Review deleted records &rarr;</a></span>
                    </div>
                <?php endif; ?>

                <div class="grid" style="grid-template-columns: 1.3fr 1fr; gap: 1rem;">
                    <div class="card">
                        <h3 class="card-title">Activity Trend (Last 7 Days)</h3>
                        <div id="activityTrendChart"></div>
                    </div>

                    <div class="card">
                        <h3 class="card-title">Module Activity (30 Days)</h3>
                        <?php if (empty($moduleBreakdown)): ?>
                            <p style="text-align: center; color: #64748b; padding: 1.5rem 0; margin: 0;">No activity yet</p>
                            <?php else: foreach ($moduleBreakdown as $mb):
                                $pct = max(4, round(($mb['cnt'] / $moduleMax) * 100));
                            ?>
                                <div style="margin-bottom: 0.65rem;">
                                    <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: #334155; margin-bottom: 3px;">
                                        <span><?= htmlspecialchars($mb['module']) ?></span>
                                        <span style="color: #94a3b8;"><?= $mb['cnt'] ?></span>
                                    </div>
                                    <div style="background: #f1f5f9; border-radius: 4px; height: 8px; overflow: hidden;">
                                        <div style="background: var(--primary); height: 100%; width: <?= $pct ?>%; border-radius: 4px;"></div>
                                    </div>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>
                </div>

                <div class="card" style="margin-top: 1rem;">
                    <h3 class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
                        Most Active Users (30 Days)
                        <a href="../system_setup/admin_panel/activity-center.php?view=user-activity" style="font-size: 12px; font-weight: 600; color: var(--primary); text-decoration: none; white-space: nowrap;">View All <i class="fas fa-arrow-right"></i></a>
                    </h3>
                    <?php if (empty($topUsers)): ?>
                        <p style="text-align: center; color: #64748b; padding: 1rem 0; margin: 0;">No activity yet</p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <?php foreach ($topUsers as $u): $pct = max(6, round(($u['cnt'] / $topUserMax) * 100)); ?>
                                <div>
                                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                                        <span style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars($u['user_name']) ?></span>
                                        <span style="color: #94a3b8;"><?= $u['cnt'] ?></span>
                                    </div>
                                    <div style="background: #f1f5f9; border-radius: 4px; height: 6px; overflow: hidden;">
                                        <div style="background: #3b82f6; height: 100%; width: <?= $pct ?>%; border-radius: 4px;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
                                        <td><?= htmlspecialchars($currencySymbol) ?> <?= number_format($product['total_revenue'], 0) ?></td>
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
                                        <td><?= htmlspecialchars($currencySymbol) ?> <?= number_format($customer['total_sales'], 0) ?></td>
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

                <!-- Recent Activity -->
                <div class="card" style="grid-column: 1 / -1;">
                    <h3 class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
                        Recent Activity
                        <a href="../system_setup/admin_panel/activity-center.php" style="font-size: 12px; font-weight: 600; color: var(--primary); text-decoration: none; white-space: nowrap;">View All <i class="fas fa-arrow-right"></i></a>
                    </h3>
                    <?php
                    // module != 'System' excludes this feature's own admin housekeeping entries
                    // (bulk log purges) - noise on a business-overview dashboard, not "what
                    // happened in my business today".
                    $stmt = $pdo->prepare("
                        SELECT user_name, action, module, record_reference, record_id, description,
                               DATE_FORMAT(created_at, '%h:%i %p') AS time_label,
                               DATE_FORMAT(created_at, '%Y-%m-%d') AS date_label
                        FROM activity_logs
                        WHERE tenant_id = ? AND module != 'System'
                        ORDER BY created_at DESC, id DESC
                        LIMIT 6
                    ");
                    $stmt->execute([$tenant_id]);
                    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $actionMeta = [
                        'create' => ['fa-plus', '#2fbf71', '#eafff3', 'created'],
                        'update' => ['fa-pen', '#e8b23f', '#fff8e8', 'updated'],
                        'delete' => ['fa-trash', '#e34f4f', '#fdeeee', 'deleted'],
                    ];
                    $todayLabel = date('Y-m-d');
                    ?>
                    <?php if (empty($recentActivity)): ?>
                        <p style="text-align: center; color: #64748b; padding: 1.5rem 0; margin: 0;">No recent activity</p>
                    <?php else: ?>
                        <div>
                            <?php foreach ($recentActivity as $act):
                                [$icon, $iconColor, $iconBg, $verb] = $actionMeta[$act['action']] ?? ['fa-circle', '#64748b', '#f1f5f9', $act['action']];
                                $whenLabel = $act['date_label'] === $todayLabel ? $act['time_label'] : $act['date_label'];
                                $recordLabel = $act['record_reference'] ?: $act['record_id'];
                            ?>
                                <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;">
                                    <div style="flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%; background: <?= $iconBg ?>; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas <?= $icon ?>" style="color: <?= $iconColor ?>; font-size: 13px;"></i>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 14px; color: #0f172a;">
                                            <span style="font-weight: 600;"><?= htmlspecialchars($act['user_name']) ?></span>
                                            <span style="color: #64748b;"><?= htmlspecialchars($verb) ?></span>
                                            <?= htmlspecialchars($act['module']) ?><?= $recordLabel ? ' <span style="color: #94a3b8;">&middot; ' . htmlspecialchars($recordLabel) . '</span>' : '' ?>
                                        </div>
                                        <div style="font-size: 12px; color: #94a3b8; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($act['description']) ?></div>
                                    </div>
                                    <div style="flex-shrink: 0; font-size: 12px; color: #94a3b8; white-space: nowrap;"><?= htmlspecialchars($whenLabel) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Todo List Modal -->
    <div id="todoModal" class="todo-modal">
        <div class="todo-modal-content" onclick="event.stopPropagation()">
            <div class="todo-modal-header">
                <h3><i class="fas fa-list-check"></i> Todo List</h3>
                <button class="todo-modal-close" onclick="closeTodoModal()">&times;</button>
            </div>
            <div class="todo-modal-body">
                <div class="todo-add-form">
                    <input type="text" id="todoHeading" class="ledger-input" placeholder="Heading (e.g. Follow up on invoice)">
                    <textarea id="todoDescription" placeholder="Description (optional)"></textarea>
                    <input type="date" id="todoDueDate" class="ledger-input">
                    <input type="time" id="todoDueTime" class="ledger-input">
                    <button class="btn btn-primary" onclick="addTodo()"><i class="fas fa-plus"></i> Add</button>
                </div>

                <div style="display:flex; justify-content:flex-end; margin-bottom:0.75rem;">
                    <button class="btn btn-secondary" onclick="clearTodos()"><i class="fas fa-trash-alt"></i> Clear All</button>
                </div>

                <div class="todo-table-wrap">
                    <table class="todo-table">
                        <thead>
                            <tr>
                                <th style="width:40px;"></th>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Heading</th>
                                <th>Description</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="todoTableBody">
                            <tr>
                                <td colspan="7" class="todo-empty">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

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
            const currencySymbol = '<?= addslashes($currencySymbol) ?>';

            // Load KPI data from APIs
            async function loadKPIs() {
                try {
                    // Stock Valuation - use the same `stats` aggregate the Stock Position page's
                    // own "Total Stock Value" card reads (client/assets/js/inventory/stock_position/stock-position.js
                    // -> loadDashboardStats()), NOT `action=position`. `position` computes each row's
                    // value via a more complex proportional unit-distribution formula built for the
                    // detailed multi-unit table, and applies an extra `account_id IS NOT NULL` filter
                    // that `stats` doesn't - summing its rows client-side silently drifts from the
                    // real total instead of matching it.
                    const stockRes = await fetch('../../../server/api/inventory/stock_position/stock-position.php?action=stats');
                    const stockData = await stockRes.json();
                    if (stockData.success && stockData.data) {
                        const totalValue = parseFloat(stockData.data.total_value || 0);
                        document.getElementById('kpi-stock-value').textContent = currencySymbol + ' ' + totalValue.toLocaleString('en-PK', {
                            maximumFractionDigits: 0
                        });
                    }

                    // Units Sold from sale-reports API (Revenue Gross/Net is rendered
                    // server-side above, straight from sale_invoice.total_bill/net_amount)
                    let saleUrl = '../../../server/api/sale/sales_report/sales-report.php';
                    if (hasDateFilter) {
                        saleUrl += `?date_from=${dateFrom}&date_to=${dateTo}`;
                    }
                    const saleRes = await fetch(saleUrl);
                    const saleData = await saleRes.json();
                    if (saleData.success && saleData.data && saleData.data.product_sales) {
                        const totalUnits = saleData.data.product_sales.reduce((sum, item) => sum + parseFloat(item.quantity_sold || 0), 0);
                        document.getElementById('kpi-units-sold').textContent = totalUnits.toLocaleString('en-PK', {
                            maximumFractionDigits: 0
                        }) + ' Units';
                    }


                    // Accounts Receivable from customer-ledger API
                    const customerRes = await fetch('../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=summary');
                    const customerData = await customerRes.json();
                    if (customerData.success && Array.isArray(customerData.data)) {
                        const totalReceivables = customerData.data.reduce((sum, item) => sum + parseFloat(item.closing_balance || 0), 0);
                        document.getElementById('kpi-receivables').textContent = currencySymbol + ' ' + totalReceivables.toLocaleString('en-PK', {
                            maximumFractionDigits: 0
                        });
                    }

                    // Accounts Payable from supplier-ledger API
                    const supplierRes = await fetch('../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?type=summary');
                    const supplierData = await supplierRes.json();
                    if (supplierData.success && Array.isArray(supplierData.data)) {
                        const totalPayables = supplierData.data.reduce((sum, item) => sum + parseFloat(item.closing_balance || 0), 0);
                        document.getElementById('kpi-payables').textContent = currencySymbol + ' ' + Math.abs(totalPayables).toLocaleString('en-PK', {
                            maximumFractionDigits: 0
                        });
                    }
                } catch (error) {
                    console.error('Error loading KPIs:', error);
                }
            }

            // Load KPIs on page load
            loadKPIs();

            // Sales Trend Chart
            <?php
            // Now that the default range is a full month instead of always
            // "last 7 days" (hasDateFilter is always true — see top of file),
            // the old day-by-day loop below would run ~30 separate queries
            // per page load instead of 7. One grouped query + zero-filling
            // the missing days gives identical results for a fraction of
            // the DB round trips, for a range of any length.
            $chartData = [];
            $chartLabels = [];
            $dateMap = [];
            $cursor = new DateTime($dateFrom);
            $endDate = new DateTime($dateTo);
            while ($cursor <= $endDate) {
                $dateMap[$cursor->format('Y-m-d')] = ['label' => $cursor->format('M j'), 'qty' => 0.0];
                $cursor->add(new DateInterval('P1D'));
            }

            $stmt = $pdo->prepare("
                SELECT DATE(si.sale_date) as sale_day, COALESCE(SUM(sii.quantity), 0) as qty
                FROM sale_invoice si
                JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
                WHERE si.tenant_id = ? AND DATE(si.sale_date) BETWEEN ? AND ? AND sii.parent_row_id IS NULL
                GROUP BY DATE(si.sale_date)
            ");
            $stmt->execute([$tenant_id, $dateFrom, $dateTo]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (isset($dateMap[$row['sale_day']])) {
                    $dateMap[$row['sale_day']]['qty'] = (float) $row['qty'];
                }
            }

            foreach ($dateMap as $day) {
                $chartLabels[] = $day['label'];
                $chartData[] = $day['qty'];
            }
            ?>
            new ApexCharts(document.querySelector('#fuelSalesChart'), {
                chart: {
                    type: 'area',
                    height: 200,
                    toolbar: {
                        show: false
                    }
                },
                series: [{
                    name: 'Sales',
                    data: <?= json_encode($chartData) ?>
                }],
                xaxis: {
                    categories: <?= json_encode($chartLabels) ?>
                },
                colors: ['#3b82f6'],
                stroke: {
                    curve: 'smooth',
                    width: 2
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.4,
                        opacityTo: 0.1
                    }
                }
            }).render();

            // Activity Trend (Audit & Activity Analytics)
            new ApexCharts(document.querySelector('#activityTrendChart'), {
                chart: {
                    type: 'bar',
                    height: 200,
                    toolbar: {
                        show: false
                    }
                },
                series: [{
                    name: 'Activities',
                    data: <?= json_encode($trendData) ?>
                }],
                xaxis: {
                    categories: <?= json_encode($trendLabels) ?>
                },
                colors: ['#1f7bff'],
                dataLabels: {
                    enabled: false
                },
                plotOptions: {
                    bar: {
                        columnWidth: '55%',
                        borderRadius: 3
                    }
                }
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
                chart: {
                    type: 'donut',
                    height: 200
                },
                series: [<?= $accountsReceivable ?>, <?= abs($accountsPayable) ?>],
                labels: ['Receivable', 'Payable'],
                colors: ['#22c55e', '#ef4444'],
                legend: {
                    position: 'bottom'
                }
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
                chart: {
                    type: 'bar',
                    height: 200,
                    toolbar: {
                        show: false
                    }
                },
                series: [{
                    name: 'Revenue',
                    data: <?= json_encode($revenueData) ?>
                }, {
                    name: 'Expenses',
                    data: <?= json_encode($expenseData) ?>
                }],
                xaxis: {
                    categories: <?= json_encode($revenueExpenseLabels) ?>
                },
                colors: ['#22c55e', '#ef4444'],
                dataLabels: {
                    enabled: false
                },
                plotOptions: {
                    bar: {
                        columnWidth: '60%'
                    }
                }
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
                chart: {
                    type: 'line',
                    height: 200,
                    toolbar: {
                        show: false
                    }
                },
                series: [{
                    name: 'Cash In',
                    data: <?= json_encode($cashInData) ?>
                }, {
                    name: 'Cash Out',
                    data: <?= json_encode($cashOutData) ?>
                }],
                xaxis: {
                    categories: <?= json_encode($cashFlowLabels) ?>
                },
                colors: ['#22c55e', '#ef4444'],
                stroke: {
                    curve: 'smooth',
                    width: 2
                }
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
                                ${currencySymbol} ${Math.abs(openingBalance).toLocaleString('en-PK', {maximumFractionDigits: 2})}
                                <span class="badge ${openingBalance >= 0 ? 'badge-dr' : 'badge-cr'}" style="margin-left: 0.5rem; font-size: 0.75rem;">
                                    ${openingBalance >= 0 ? 'Dr' : 'Cr'}
                                </span>
                            </div>
                        </div>
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Total Debit</div>
                            <div class="ledger-summary-value">${currencySymbol} ${totalDebit.toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                        </div>
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Total Credit</div>
                            <div class="ledger-summary-value">${currencySymbol} ${totalCredit.toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                        </div>
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Closing Balance</div>
                            <div class="ledger-summary-value ${closingBalance >= 0 ? 'positive' : 'negative'}">
                                ${currencySymbol} ${Math.abs(closingBalance).toLocaleString('en-PK', {maximumFractionDigits: 2})}
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
                                <td class="text-right" style="font-weight: 700;">${currencySymbol} ${parseFloat(txn.total_debit || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</td>
                                <td class="text-right" style="font-weight: 700;">${currencySymbol} ${parseFloat(txn.total_credit || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</td>
                                <td class="text-right" style="font-weight: 700;">${currencySymbol} ${parseFloat(txn.running_balance || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</td>
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
                                <td class="text-right">${debit > 0 ? currencySymbol + ' ' + debit.toLocaleString('en-PK', {maximumFractionDigits: 2}) : '-'}</td>
                                <td class="text-right">${credit > 0 ? currencySymbol + ' ' + credit.toLocaleString('en-PK', {maximumFractionDigits: 2}) : '-'}</td>
                                <td class="text-right" style="font-weight: 600;">
                                    ${currencySymbol} ${Math.abs(balance).toLocaleString('en-PK', {maximumFractionDigits: 2})}
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
                            <div class="ledger-summary-value">${currencySymbol} ${parseFloat(summary.totalDebits || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                        </div>
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Total Credit</div>
                            <div class="ledger-summary-value">${currencySymbol} ${parseFloat(summary.totalCredits || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                        </div>
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Transaction Count</div>
                            <div class="ledger-summary-value">${summary.transactionCount || 0}</div>
                        </div>
                        <div class="ledger-summary-card">
                            <div class="ledger-summary-label">Balance Status</div>
                            <div class="ledger-summary-value ${summary.isBalanced ? 'positive' : 'negative'}">
                                ${summary.isBalanced ? 'Balanced ?' : 'Unbalanced'}
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
                            <td class="text-right">${debit > 0 ? currencySymbol + ' ' + debit.toLocaleString('en-PK', {maximumFractionDigits: 2}) : '-'}</td>
                            <td class="text-right">${credit > 0 ? currencySymbol + ' ' + credit.toLocaleString('en-PK', {maximumFractionDigits: 2}) : '-'}</td>
                            <td class="text-right" style="font-weight: 600;">
                                ${currencySymbol} ${Math.abs(balance).toLocaleString('en-PK', {maximumFractionDigits: 2})}
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
                chart: {
                    type: 'bar',
                    height: 200,
                    toolbar: {
                        show: false
                    }
                },
                series: [{
                    name: 'Sales',
                    data: <?= json_encode($branchData) ?>
                }],
                xaxis: {
                    categories: <?= json_encode($branchLabels) ?>
                },
                colors: ['#3b82f6'],
                plotOptions: {
                    bar: {
                        horizontal: true,
                        barHeight: '70%'
                    }
                },
                dataLabels: {
                    enabled: false
                }
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
                chart: {
                    type: 'bar',
                    height: 200,
                    toolbar: {
                        show: false
                    }
                },
                series: [{
                    name: 'Sales',
                    data: <?= json_encode($territoryData) ?>
                }],
                xaxis: {
                    categories: <?= json_encode($territoryLabels) ?>
                },
                colors: ['#22c55e'],
                plotOptions: {
                    bar: {
                        horizontal: true,
                        barHeight: '70%'
                    }
                },
                dataLabels: {
                    enabled: false
                }
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
                            const logoPath = bank.bank_logo_path ?
                                `../../assets/uploads/bank_logo/${bank.bank_logo_path}` :
                                '../../assets/uploads/bank_logo/default-bank.png';

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
                selectedBankAccount = {
                    id: bankId,
                    name: bankName
                };
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
                                <div class="ledger-summary-value">${currencySymbol} ${(balances.opening_balance || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                            </div>
                            <div class="ledger-summary-card">
                                <div class="ledger-summary-label">Total Inflow</div>
                                <div class="ledger-summary-value positive">${currencySymbol} ${(balances.total_inflow || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                            </div>
                            <div class="ledger-summary-card">
                                <div class="ledger-summary-label">Total Outflow</div>
                                <div class="ledger-summary-value negative">${currencySymbol} ${(balances.total_outflow || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
                            </div>
                            <div class="ledger-summary-card">
                                <div class="ledger-summary-label">Closing Balance</div>
                                <div class="ledger-summary-value">${currencySymbol} ${(balances.closing_balance || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</div>
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
                                        <td class="text-right">${txn.inflow > 0 ? currencySymbol + ' ' + parseFloat(txn.inflow).toLocaleString('en-PK', {maximumFractionDigits: 2}) : '-'}</td>
                                        <td class="text-right">${txn.outflow > 0 ? currencySymbol + ' ' + parseFloat(txn.outflow).toLocaleString('en-PK', {maximumFractionDigits: 2}) : '-'}</td>
                                        <td class="text-right" style="font-weight: 600;">${currencySymbol} ${parseFloat(txn.bank_balance || 0).toLocaleString('en-PK', {maximumFractionDigits: 2})}</td>
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
            shortcutKeyInput?.addEventListener('keydown', function(e) {
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

                if (!pageKey) {
                    alert('Pehle koi page select karein');
                    return;
                }
                if (!capturedCombo) {
                    alert('Key box par click karke shortcut key press karein');
                    return;
                }

                try {
                    const res = await fetch('../../../server/api/shortcuts/shortcuts.php?action=save', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            key_combo: capturedCombo,
                            page_key: pageKey,
                            page_label: pageLabel
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        capturedCombo = '';
                        shortcutKeyInput.value = '';
                        select.value = '';
                        loadShortcutsUI();
                        if (window.DashboardCore) await window.DashboardCore.loadShortcuts(); // ?? ye line add karo
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
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id
                        })
                    });
                    loadShortcutsUI();
                    if (window.DashboardCore) await window.DashboardCore.loadShortcuts(); // ?? ye line add karo
                } catch (err) {
                    console.error(err);
                }
            }

            // ---------- Global Search ----------
            let searchDebounceTimer;
            const globalSearchInput = document.getElementById('globalSearchInput');
            const globalSearchResults = document.getElementById('globalSearchResults');

            globalSearchInput?.addEventListener('input', function() {
                clearTimeout(searchDebounceTimer);
                const q = this.value.trim();

                if (q.length < 1) {
                    globalSearchResults.style.display = 'none';
                    return;
                }

                searchDebounceTimer = setTimeout(() => runGlobalSearch(q), 300);
            });

            async function runGlobalSearch(q) {
                globalSearchResults.style.display = 'block';
                globalSearchResults.innerHTML = '<div style="padding:1rem; color:#64748b; font-size:13px;"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';

                try {
                    const res = await fetch('../../../server/api/global_search/global-search.php?q=' + encodeURIComponent(q));
                    const data = await res.json();

                    if (!data.success || !data.data.length) {
                        globalSearchResults.innerHTML = '<div style="padding:1rem; color:#64748b; font-size:13px;">No result found</div>';
                        return;
                    }

                    globalSearchResults.innerHTML = data.data.map(item => `
                        <div class="search-result-item" onclick="window.location.href='${item.url}'" style="
                            display:flex; justify-content:space-between; align-items:center;
                            padding:0.75rem 1rem; cursor:pointer; border-bottom:1px solid #f1f5f9;
                            transition: background 0.15s;"
                            onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                            <div>
                                <div style="font-size:11px; color:#3b82f6; font-weight:600; text-transform:uppercase;">${item.module}</div>
                                <div style="font-size:14px; color:#0f172a; font-weight:600;">${item.number}${item.party ? ' � ' + item.party : ''}</div>
                                ${item.date ? `<div style="font-size:12px; color:#94a3b8;">${item.date}</div>` : ''}
                            </div>
                            <div style="font-size:14px; font-weight:700; color:#16a34a;">
                                ${item.amount !== null ? currencySymbol + ' ' + parseFloat(item.amount).toLocaleString('en-PK') : ''}
                            </div>
                        </div>
                    `).join('');
                } catch (err) {
                    console.error(err);
                    globalSearchResults.innerHTML = '<div style="padding:1rem; color:#dc2626; font-size:13px;">Error loading results</div>';
                }
            }

            // Close dropdown on outside click
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#globalSearchInput') && !e.target.closest('#globalSearchResults')) {
                    globalSearchResults.style.display = 'none';
                }
            });

            async function loadShortcutsUI() {
                const list = document.getElementById('shortcutsList');
                if (!list) return;
                try {
                    const res = await fetch('../../../server/api/shortcuts/shortcuts.php?action=list');
                    const data = await res.json();
                    if (data.success && data.data.length) {
                        list.innerHTML = data.data.map(s => `
                            <span style="display:flex; align-items:center; gap:0.5rem; background:white; border:1.5px solid #d6dbe4; border-radius:0.5rem; padding:0.4rem 0.75rem; font-size:13px;">
                                <strong style="color:var(--primary);">${s.key_combo}</strong> ? ${s.page_label}
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
            let todoNotifiedIds = new Set();

            function escapeHtml(str) {
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }

            function openTodoModal() {
                document.getElementById('todoModal').classList.add('show');
                loadTodos();
            }

            function closeTodoModal() {
                document.getElementById('todoModal').classList.remove('show');
            }

            document.getElementById('todoModal')?.addEventListener('click', function() {
                closeTodoModal();
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeTodoModal();
            });

            function isOverdue(dueDate, dueTime, isDone) {
                if (!dueDate || isDone) return false;
                const now = new Date();
                const target = new Date(dueDate + 'T' + (dueTime || '23:59:59'));
                return target < now;
            }

            async function loadTodos() {
                const tbody = document.getElementById('todoTableBody');
                try {
                    const res = await fetch('../../../server/api/todos/todos.php?action=list');
                    const data = await res.json();
                    if (!data.success) return;

                    const todos = data.data;
                    const overdueCount = todos.filter(t => isOverdue(t.due_date, t.due_time, t.is_done)).length;
                    updateTodoBadge(overdueCount);
                    checkOverdueNotifications(todos);

                    if (!todos.length) {
                        tbody.innerHTML = '<tr><td colspan="7" class="todo-empty"><i class="fas fa-clipboard-check" style="font-size:1.5rem; display:block; margin-bottom:0.5rem;"></i>No tasks yet. Add one above.</td></tr>';
                        return;
                    }

                    tbody.innerHTML = todos.map(t => {
                        const overdue = isOverdue(t.due_date, t.due_time, t.is_done);
                        let dateLabel = '-',
                            dayLabel = '-';
                        if (t.due_date) {
                            const d = new Date(t.due_date + 'T00:00:00');
                            dateLabel = d.toLocaleDateString('en-PK', {
                                day: 'numeric',
                                month: 'short',
                                year: 'numeric'
                            });
                            dayLabel = d.toLocaleDateString('en-PK', {
                                weekday: 'short'
                            });
                        }
                        const timeLabel = t.due_time ? t.due_time.slice(0, 5) : '-';

                        return `
                <tr class="${t.is_done == 1 ? 'done' : ''} ${overdue ? 'overdue' : ''}" data-id="${t.id}">
                    <td><input type="checkbox" class="todo-checkbox" ${t.is_done == 1 ? 'checked' : ''} onchange="toggleTodo(${t.id})"></td>
                    <td>${dateLabel}</td>
                    <td><span class="todo-day-tag">${dayLabel}</span></td>
                    <td>${timeLabel}</td>
                    <td>
                        <div class="todo-heading">${escapeHtml(t.heading)}</div>
                        ${overdue ? '<span class="todo-overdue-tag">Overdue</span>' : ''}
                    </td>
                    <td class="todo-desc">${t.description ? escapeHtml(t.description) : '-'}</td>
                    <td><i class="fas fa-times todo-delete" onclick="deleteTodo(${t.id})"></i></td>
                </tr>
            `;
                    }).join('');
                } catch (err) {
                    console.error(err);
                    tbody.innerHTML = '<tr><td colspan="7" class="todo-empty" style="color:#dc2626;">Failed to load todo list</td></tr>';
                }
            }

            function updateTodoBadge(count) {
                const badge = document.getElementById('todoBadge');
                if (!badge) return;
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }

            function checkOverdueNotifications(todos) {
                todos.forEach(t => {
                    if (isOverdue(t.due_date, t.due_time, t.is_done) && !todoNotifiedIds.has(t.id)) {
                        todoNotifiedIds.add(t.id);
                        showTodoToast(t);
                    }
                });
            }

            function showTodoToast(todo) {
                const toast = document.createElement('div');
                toast.className = 'todo-toast';
                toast.innerHTML = `
        <i class="fas fa-triangle-exclamation"></i>
        <div>
            <div style="font-weight:700; margin-bottom:0.25rem;">Task overdue</div>
            <div>${escapeHtml(todo.heading)}</div>
        </div>
    `;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 6000);
            }

            async function addTodo() {
                const headingInput = document.getElementById('todoHeading');
                const descInput = document.getElementById('todoDescription');
                const dateInput = document.getElementById('todoDueDate');
                const timeInput = document.getElementById('todoDueTime');

                const heading = headingInput.value.trim();
                if (!heading) return;

                try {
                    const res = await fetch('../../../server/api/todos/todos.php?action=add', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            heading,
                            description: descInput.value.trim(),
                            due_date: dateInput.value || null,
                            due_time: timeInput.value || null
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        headingInput.value = '';
                        descInput.value = '';
                        dateInput.value = '';
                        timeInput.value = '';
                        loadTodos();
                    } else {
                        alert(data.message || 'Could not add task');
                    }
                } catch (err) {
                    console.error(err);
                }
            }

            async function toggleTodo(id) {
                try {
                    await fetch('../../../server/api/todos/todos.php?action=toggle', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id
                        })
                    });
                    loadTodos();
                } catch (err) {
                    console.error(err);
                }
            }

            async function deleteTodo(id) {
                try {
                    await fetch('../../../server/api/todos/todos.php?action=delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id
                        })
                    });
                    loadTodos();
                } catch (err) {
                    console.error(err);
                }
            }

            async function clearTodos() {
                if (!confirm('Clear the entire todo list? This cannot be undone.')) return;
                try {
                    await fetch('../../../server/api/todos/todos.php?action=clear', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    });
                    loadTodos();
                } catch (err) {
                    console.error(err);
                }
            }

            loadTodos();
            setInterval(loadTodos, 5 * 60 * 1000);
            loadShortcutsUI();
        </script>



        <script src="../../assets/js/dashboard/dashboard-main.js"></script>

        <script>
            (function() {
                const DATE_KEY = 'global_locked_date';
                const FLAG_KEY = 'global_date_locked';

                function init() {
                    const wrap = document.getElementById('dateLockWidgetWrap');
                    const panel = document.getElementById('dateLockPanel');
                    const toggle = document.getElementById('dateLockToggleTab');
                    const tabIcon = document.getElementById('dateLockTabIcon');
                    const dateInput = document.getElementById('globalLockDate');
                    const lockBtn = document.getElementById('globalDateLockBtn');
                    const lockIcon = document.getElementById('globalDateLockIcon');
                    if (!wrap || !panel || !toggle || !dateInput || !lockBtn || !lockIcon) return;

                    const isLocked = localStorage.getItem(FLAG_KEY) === 'true';
                    const savedDate = localStorage.getItem(DATE_KEY);

                    function renderLocked() {
                        dateInput.value = savedDate;
                        dateInput.disabled = true;
                        lockIcon.className = 'fas fa-lock';
                        lockIcon.style.color = '#e74c3c';
                        lockBtn.title = 'Click to unlock date';
                        tabIcon.className = 'fas fa-lock';
                        tabIcon.style.color = '#e74c3c';
                    }

                    function renderUnlocked() {
                        if (!dateInput.value) dateInput.value = new Date().toISOString().split('T')[0];
                        dateInput.disabled = false;
                        lockIcon.className = 'fas fa-lock-open';
                        lockIcon.style.color = '#888';
                        lockBtn.title = 'Select a date and click to lock it for all forms';
                        tabIcon.className = 'fas fa-lock-open';
                        tabIcon.style.color = '#888';
                    }

                    if (isLocked && savedDate) renderLocked();
                    else renderUnlocked();

                    // Toggle open/close panel
                    function openPanel() {
                        panel.style.maxWidth = '220px';
                        panel.style.opacity = '1';
                    }

                    function closePanel() {
                        panel.style.maxWidth = '0';
                        panel.style.opacity = '0';
                    }

                    toggle.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const isOpen = panel.style.maxWidth && panel.style.maxWidth !== '0px';
                        if (isOpen) closePanel();
                        else openPanel();
                    });

                    // Click outside closes the panel
                    document.addEventListener('click', function(e) {
                        if (!wrap.contains(e.target)) closePanel();
                    });

                    lockBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const currentlyLocked = localStorage.getItem(FLAG_KEY) === 'true';

                        if (currentlyLocked) {
                            // UNLOCK
                            localStorage.removeItem(FLAG_KEY);
                            localStorage.removeItem(DATE_KEY);

                            renderUnlocked();

                            window.dispatchEvent(new CustomEvent('global-date-lock-changed', {
                                detail: {
                                    locked: false,
                                    date: new Date().toISOString().split('T')[0]
                                }
                            }));

                        } else {
                            // LOCK
                            if (!dateInput.value) {
                                alert('Please select a date first.');
                                return;
                            }

                            const lockedDate = dateInput.value;

                            localStorage.setItem(DATE_KEY, lockedDate);
                            localStorage.setItem(FLAG_KEY, 'true');

                            renderLocked();

                            window.dispatchEvent(new CustomEvent('global-date-lock-changed', {
                                detail: {
                                    locked: true,
                                    date: lockedDate
                                }
                            }));
                        }
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }
            })();


            const LEAVE_API = '../../../server/api/hrm/leave/leave-request.php';

            async function loadLeaveApprovals() {
                const card = document.getElementById('leaveApprovalsCard');
                const list = document.getElementById('leaveApprovalsList');
                if (!card) return;
                try {
                    const res = await fetch(LEAVE_API + '?action=pending_excess');
                    const data = await res.json();
                    if (!data.success || !data.data.length) {
                        card.style.display = 'none';
                        return;
                    }

                    document.getElementById('leaveApprovalsCount').textContent = data.data.length;
               list.innerHTML = data.data.map(r => {
    const isOvertime = r.ot_hours !== null && r.ot_hours !== undefined;
    
    if (isOvertime) {
        const otAmount = parseFloat(r.ot_amount || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 });
        return `
            <div style="padding:1rem 0; border-bottom:1px solid #f1f5f9;">
                <div style="display:flex; flex-wrap:wrap; gap:0.5rem 1.5rem; margin-bottom:6px;">
                    <span style="font-size:14px; font-weight:700; color:#0f172a;">${escapeHtml(r.employee_name || r.employee_id)}</span>
                    <span style="font-size:13px; background:#eff6ff; color:#1d4ed8; padding:2px 10px; border-radius:20px; font-weight:600;">Overtime</span>
                    <span style="font-size:13px; color:#475569;"><i class="fas fa-calendar" style="color:#94a3b8;"></i> ${escapeHtml(r.start_date)}</span>
                    <span style="font-size:13px; color:#475569;"><i class="fas fa-clock" style="color:#94a3b8;"></i> ${parseFloat(r.ot_hours)} hrs @ ${parseFloat(r.ot_rate_multiplier)}x</span>
                    <span style="font-size:13px; font-weight:700; color:#16a34a;"><i class="fas fa-coins" style="color:#94a3b8;"></i> ${currencySymbol} ${otAmount}</span>
                </div>
                <div style="font-size:12.5px; color:#64748b; margin-bottom:10px; background:#f8fafc; padding:6px 10px; border-radius:6px; border-left:3px solid #e2e8f0;">
                    <i class="fas fa-comment-alt" style="color:#94a3b8;"></i> <strong>Reason:</strong> ${escapeHtml(r.reason || '—')}
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <button class="btn btn-primary" onclick="approveLeave(${r.id})" style="font-size:13px; padding:6px 16px;">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button class="btn btn-secondary" onclick="rejectLeave(${r.id})" style="font-size:13px; padding:6px 16px;">
                        <i class="fas fa-times"></i> Reject
                    </button>
                </div>
            </div>`;
    }

    const totalDays  = parseFloat(r.total_days  || 0);
    const paidDays   = parseFloat(r.paid_days   || 0);
    const unpaidDays = parseFloat(r.unpaid_days || 0);
    const dailyRate  = parseFloat(r.daily_rate  || 0);
    const paidAmount   = (paidDays   * dailyRate).toLocaleString('en-PK', { maximumFractionDigits: 0 });
    const unpaidAmount = (unpaidDays * dailyRate).toLocaleString('en-PK', { maximumFractionDigits: 0 });
    const isPendingExcess = parseFloat(r.excess_days || 0) > 0;

    const paymentBadge = unpaidDays >= totalDays
        ? `<span style="background:#fee2e2; color:#dc2626; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:700;">Fully Unpaid</span>`
        : unpaidDays > 0
            ? `<span style="background:#fef3c7; color:#b45309; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:700;">Split — ${paidDays} Paid / ${unpaidDays} Unpaid</span>`
            : `<span style="background:#dcfce7; color:#16a34a; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:700;">Fully Paid</span>`;

    const buttons = isPendingExcess
        ? `<div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
               <label style="font-size:12px; color:#64748b; font-weight:600;">Paid Days:</label>
               <input type="number" min="0" max="${totalDays}" step="0.5"
                      value="${paidDays}" id="paidInput-${r.id}"
                      style="width:70px; padding:4px 8px; border:1.5px solid #d6dbe4; border-radius:6px; font-size:13px;">
               <span style="font-size:12px; color:#64748b;">of ${totalDays} days (rest unpaid)</span>
               <button class="btn btn-primary" onclick="resolveLeaveExcess(${r.id}, ${totalDays})" style="font-size:13px; padding:6px 16px;">
                   <i class="fas fa-check"></i> Save Decision
               </button>
           </div>`
        : `<div style="display:flex; gap:0.5rem;">
               <button class="btn btn-primary" onclick="approveLeave(${r.id})" style="font-size:13px; padding:6px 16px;">
                   <i class="fas fa-check"></i> Approve
               </button>
               <button class="btn btn-secondary" onclick="rejectLeave(${r.id})" style="font-size:13px; padding:6px 16px;">
                   <i class="fas fa-times"></i> Reject
               </button>
           </div>`;

    return `
        <div style="padding:1rem 0; border-bottom:1px solid #f1f5f9;">
            <!-- Row 1: Name + Type + Date range + Payment badge -->
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem 1.5rem; align-items:center; margin-bottom:8px;">
                <span style="font-size:14px; font-weight:700; color:#0f172a;">${escapeHtml(r.employee_name || r.employee_id)}</span>
                <span style="font-size:13px; background:#eff6ff; color:#1d4ed8; padding:2px 10px; border-radius:20px; font-weight:600;">${escapeHtml(r.type_name || 'Leave')}</span>
                <span style="font-size:13px; color:#475569;"><i class="fas fa-calendar" style="color:#94a3b8;"></i> ${escapeHtml(r.start_date)} → ${escapeHtml(r.end_date || r.start_date)}</span>
                <span style="font-size:13px; color:#475569; font-weight:600;"><i class="fas fa-sun" style="color:#94a3b8;"></i> ${totalDays} day${totalDays !== 1 ? 's' : ''}</span>
                ${paymentBadge}
            </div>

            <!-- Row 2: Paid / Unpaid breakdown -->
            <div style="display:flex; flex-wrap:wrap; gap:0.75rem; margin-bottom:8px;">
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:6px 14px; font-size:12.5px;">
                    <span style="color:#64748b;">Paid:</span>
                    <strong style="color:#16a34a; margin-left:4px;">${paidDays} days</strong>
                    <span style="color:#94a3b8; margin-left:4px;">(${currencySymbol} ${paidAmount})</span>
                </div>
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:6px 14px; font-size:12.5px;">
                    <span style="color:#64748b;">Unpaid:</span>
                    <strong style="color:#dc2626; margin-left:4px;">${unpaidDays} days</strong>
                    <span style="color:#94a3b8; margin-left:4px;">(${currencySymbol} ${unpaidAmount})</span>
                </div>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:6px 14px; font-size:12.5px;">
                    <span style="color:#64748b;">Daily Rate:</span>
                    <strong style="color:#334155; margin-left:4px;">${currencySymbol} ${dailyRate.toLocaleString('en-PK', { maximumFractionDigits: 0 })}</strong>
                </div>
            </div>

            <!-- Row 3: Reason -->
            <div style="font-size:12.5px; color:#64748b; margin-bottom:10px; background:#f8fafc; padding:7px 12px; border-radius:6px; border-left:3px solid #e2e8f0;">
                <i class="fas fa-comment-alt" style="color:#94a3b8; margin-right:4px;"></i>
                <strong>Reason:</strong> ${escapeHtml(r.reason || '—')}
                ${isPendingExcess ? `<span style="margin-left:12px; color:#dc2626; font-weight:700;"><i class="fas fa-triangle-exclamation"></i> ${parseFloat(r.excess_days)} excess days — requires decision</span>` : ''}
            </div>

            <!-- Row 4: Action buttons -->
            ${buttons}
        </div>`;
}).join('');
                    card.style.display = 'block';
                } catch (err) {
                    console.error('Leave approvals error:', err);
                }
            }

         async function resolveLeaveExcess(id, totalDays) {
    const input = document.getElementById('paidInput-' + id);
    const paidDays = parseFloat(input.value);

    if (isNaN(paidDays) || paidDays < 0 || paidDays > totalDays) {
        alert(`Paid days must be between 0 and ${totalDays}`);
        return;
    }
    const unpaidDays = Math.round((totalDays - paidDays) * 100) / 100;
    if (!confirm(`Confirm: ${paidDays} Paid days + ${unpaidDays} Unpaid days?`)) return;

                try {
                    const res = await fetch(LEAVE_API, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'resolve_excess',
                            id,
                            paid_days: paidDays
                        })
                    });
                    const data = await res.json();
                    if (!data.success) {
                        alert(data.message || 'Could not save decision');
                        return;
                    }
                    loadLeaveApprovals();
                } catch (err) {
                    console.error(err);
                }
            }


            async function approveLeave(id) {
    if (!confirm('Are you sure you want to approve this leave request?')) return;
                try {
                    const res = await fetch(LEAVE_API, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'update_status',
                            id,
                            status: 'approved'
                        })
                    });
                    const data = await res.json();
                    if (!data.success) {
                        alert(data.message || 'Error');
                        return;
                    }
                    loadLeaveApprovals();
                } catch (err) {
                    console.error(err);
                }
            }

            async function rejectLeave(id) {
    if (!confirm('Are you sure you want to reject this leave request?')) return;
                try {
                    const res = await fetch(LEAVE_API, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'update_status',
                            id,
                            status: 'rejected'
                        })
                    });
                    const data = await res.json();
                    if (!data.success) {
                        alert(data.message || 'Error');
                        return;
                    }
                    loadLeaveApprovals();
                } catch (err) {
                    console.error(err);
                }
            }


            loadLeaveApprovals();
            setInterval(loadLeaveApprovals, 5 * 60 * 1000);
        </script>
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