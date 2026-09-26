<?php
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

// Get company data
require_once '../../../../includes/connection.php';
$company_sql = "SELECT company_name, address, phone, email, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1";
$company_stmt = $pdo->prepare($company_sql);
$company_stmt->execute([$tenant_id]);
$company = $company_stmt->fetch(PDO::FETCH_ASSOC);

// Get user data
$user_sql = "SELECT full_name FROM users WHERE id = ?";
$user_stmt = $pdo->prepare($user_sql);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get filters from URL
$customer_id = $_GET['customer_id'] ?? null;
$product_id = $_GET['product_id'] ?? null;
$item_company_id = $_GET['item_company_id'] ?? null;
$from_date = $_GET['from_date'] ?? null;
$to_date = $_GET['to_date'] ?? null;

if (!$customer_id && !$product_id && !$item_company_id) {
    die('A customer, product, or item company is required to generate this report.');
}

// Look up customer name from DB (never trust a name passed via query string)
$customer_name = null;
if ($customer_id) {
    $customer_sql = "SELECT customer_name FROM customers WHERE id = ? AND tenant_id = ?";
    $customer_stmt = $pdo->prepare($customer_sql);
    $customer_stmt->execute([$customer_id, $tenant_id]);
    $customer_result = $customer_stmt->fetch(PDO::FETCH_ASSOC);
    $customer_name = $customer_result['customer_name'] ?? 'Unknown Customer';
}

// Look up product name from DB (never trust a name passed via query string)
$product_name = null;
if ($product_id) {
    $product_sql = "SELECT name FROM products WHERE id = ? AND tenant_id = ?";
    $product_stmt = $pdo->prepare($product_sql);
    $product_stmt->execute([$product_id, $tenant_id]);
    $product_result = $product_stmt->fetch(PDO::FETCH_ASSOC);
    $product_name = $product_result['name'] ?? 'Unknown Product';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer-wise Stock Ledger - Print View</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }

        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .report-title {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .report-date {
            font-size: 12px;
            color: #666;
        }

        .filters {
            margin-bottom: 20px;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }

        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .totals-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }

        .toolbar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .print-btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .excel-btn {
            padding: 10px 20px;
            background: #1e7e34;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }

        .signature-box {
            width: 180px;
            text-align: center;
            font-size: 12px;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            height: 60px;
            margin-bottom: 8px;
            display: flex;
            align-items: flex-end;
        }

        .signature-box div:last-child {
            font-weight: bold;
            margin-top: 5px;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #888;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button class="print-btn" onclick="window.print()">Print Report</button>
        <button class="excel-btn" onclick="exportToExcel()">Export to Excel</button>
    </div>

    <div class="header">
        <?php if (!empty($company['logo_url'])): ?>
            <img src="../../../assets/uploads/company_logo/<?= htmlspecialchars($company['logo_url']) ?>" alt="Company Logo" style="max-height: 60px; margin-bottom: 10px;">
        <?php endif; ?>
        <div class="company-name"><?= htmlspecialchars($company['company_name'] ?? 'LedgerOne ERP') ?></div>
        <?php if (!empty($company['address'])): ?>
            <div style="font-size: 12px; margin-bottom: 5px;"><?= htmlspecialchars($company['address']) ?></div>
        <?php endif; ?>
        <?php if (!empty($company['phone']) || !empty($company['email'])): ?>
            <div style="font-size: 12px; margin-bottom: 10px;">
                <?= $company['phone'] ? 'Phone: ' . htmlspecialchars($company['phone']) : '' ?>
                <?= ($company['phone'] && $company['email']) ? ' | ' : '' ?>
                <?= $company['email'] ? 'Email: ' . htmlspecialchars($company['email']) : '' ?>
            </div>
        <?php endif; ?>
        <div class="report-title">Customer-wise Stock Ledger</div>
        <div class="report-date">Generated on: <?= date('Y-m-d H:i:s') ?></div>
        <div class="report-date">Generated by: <?= htmlspecialchars($user['full_name'] ?? 'System User') ?></div>
    </div>

    <div class="filters">
        <strong>Filters Applied:</strong>
        <?php if ($customer_name): ?>
            Customer: <?= htmlspecialchars($customer_name) ?> |
        <?php endif; ?>
        <?php if ($product_name): ?>
            Product: <?= htmlspecialchars($product_name) ?> |
        <?php endif; ?>
        Period: <?= ($from_date && $to_date) ? date('M d, Y', strtotime($from_date)) . ' to ' . date('M d, Y', strtotime($to_date)) : 'All Dates' ?>
    </div>

    <table id="reportTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Product</th>
                <th>Item Company</th>
                <th>Branch</th>
                <th>Transaction</th>
                <th>Qty In</th>
                <th>Qty Out</th>
                <th>Balance</th>
                <th>Unit Cost</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Prepared By</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Reviewed By</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Approved By</div>
        </div>
    </div>

    <script>
        const API_BASE = '../../../../server/api/inventory/stock_position/stock-position.php';

        // Holds exactly what's rendered on screen, so Export to Excel matches the preview
        let currentEntries = [];
        let currentCurrency = '$';
        const ROUND_OFF_ENABLED = new URLSearchParams(window.location.search).get('round_off') === '1';

        function formatAmount(value) {
            const amount = Number(value) || 0;
            return ROUND_OFF_ENABLED
                ? Math.round(amount).toLocaleString()
                : amount.toLocaleString(undefined, { maximumFractionDigits: 20 });
        }

        function formatQuantity(value, unit) {
            return `${formatAmount(value)} ${unit || 'L'}`;
        }

        async function loadPrintData() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const customerId = urlParams.get('customer_id');
                const productId = urlParams.get('product_id');

                if (!customerId && !productId) {
                    document.querySelector('#reportTable tbody').innerHTML =
                        '<tr><td colspan="10" class="no-data">No customer or product specified.</td></tr>';
                    return;
                }

                let url = `${API_BASE}?action=customer-ledger`;
                if (customerId) url += `&customer_id=${customerId}`;
                if (productId) url += `&product_id=${productId}`;
                const itemCompanyId = urlParams.get('item_company_id');
                if (itemCompanyId) url += `&item_company_id=${itemCompanyId}`;
                if (urlParams.get('from_date')) url += `&from_date=${urlParams.get('from_date')}`;
                if (urlParams.get('to_date')) url += `&to_date=${urlParams.get('to_date')}`;

                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    currentCurrency = data.currency || '$';
                    currentEntries = computeRunningBalances(data.data || []);
                    updatePrintTable(currentEntries, currentCurrency);
                } else {
                    document.querySelector('#reportTable tbody').innerHTML =
                        `<tr><td colspan="10" class="no-data">${data.message || 'Failed to load data.'}</td></tr>`;
                }
            } catch (error) {
                console.error('Error loading print data:', error);
                document.querySelector('#reportTable tbody').innerHTML =
                    '<tr><td colspan="10" class="no-data">Error loading data.</td></tr>';
            }
        }

        // Running balance per product-branch combination (same logic used on the main dashboard)
        function computeRunningBalances(entries) {
            const balances = {};
            return entries.map(entry => {
                const key = `${entry.product_name}-${entry.branch_name}`;
                if (!balances[key]) balances[key] = 0;
                balances[key] += parseFloat(entry.qty_in || 0) - parseFloat(entry.qty_out || 0);
                return {
                    ...entry,
                    balance: balances[key],
                    value: balances[key] * parseFloat(entry.unit_cost || 0)
                };
            });
        }

        function updatePrintTable(entries, currency = '$') {
            const tbody = document.querySelector('#reportTable tbody');

            if (!entries || entries.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="no-data">No transactions found for the selected filters.</td></tr>';
                return;
            }

            const totalQtyIn = entries.reduce((sum, e) => sum + parseFloat(e.qty_in || 0), 0);
            const totalQtyOut = entries.reduce((sum, e) => sum + parseFloat(e.qty_out || 0), 0);
            const totalValue = entries.reduce((sum, e) => sum + parseFloat(e.value || 0), 0);
            const unit = entries[0]?.unit_symbol || 'L';

            tbody.innerHTML = entries.map(entry => `
                <tr>
                    <td>${entry.transaction_date}</td>
                    <td>${entry.product_name}</td>
                    <td>${entry.item_company_name || '-'}</td>
                    <td>${entry.branch_name}</td>
                    <td>${entry.transaction_type}</td>
                    <td>${entry.qty_in > 0 ? formatQuantity(entry.qty_in, entry.unit_symbol) : '-'}</td>
                    <td>${entry.qty_out > 0 ? formatQuantity(entry.qty_out, entry.unit_symbol) : '-'}</td>
                    <td>${formatQuantity(entry.balance, entry.unit_symbol)}</td>
                    <td>${currency}${formatAmount(entry.unit_cost)}</td>
                    <td>${currency}${formatAmount(entry.value)}</td>
                </tr>
            `).join('') + `
                <tr class="totals-row">
                    <td colspan="5">Total (${entries.length} transactions)</td>
                    <td>${formatQuantity(totalQtyIn, unit)}</td>
                    <td>${formatQuantity(totalQtyOut, unit)}</td>
                    <td>-</td>
                    <td>-</td>
                    <td>${currency}${formatAmount(totalValue)}</td>
                </tr>
            `;
        }

        // Export to Excel — mirrors exactly what's shown in the preview (SheetJS)
        function exportToExcel() {
            if (typeof XLSX === 'undefined') {
                alert('Excel export library failed to load. Please check your internet connection and reload the page.');
                return;
            }

            if (!currentEntries || currentEntries.length === 0) {
                alert('No data available to export.');
                return;
            }

            const rows = currentEntries.map(entry => ({
                'Date': entry.transaction_date,
                'Product': entry.product_name,
                'Item Company': entry.item_company_name || '-',
                'Branch': entry.branch_name,
                'Transaction': entry.transaction_type,
                'Qty In': entry.qty_in > 0 ? formatQuantity(entry.qty_in, entry.unit_symbol) : '-',
                'Qty Out': entry.qty_out > 0 ? formatQuantity(entry.qty_out, entry.unit_symbol) : '-',
                'Balance': formatQuantity(entry.balance, entry.unit_symbol),
                'Unit Cost': formatAmount(entry.unit_cost),
                'Value': formatAmount(entry.value)
            }));

            const worksheet = XLSX.utils.json_to_sheet(rows);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Customer Ledger');
            XLSX.writeFile(workbook, `customer_ledger_${new Date().toISOString().slice(0, 10)}.xlsx`);
        }

        document.addEventListener('DOMContentLoaded', loadPrintData);
    </script>
</body>
</html>
