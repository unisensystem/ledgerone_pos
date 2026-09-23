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
$company_sql = "SELECT company_name, address, phone, email, logo_url, inventory_valuation_method FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1";
$company_stmt = $pdo->prepare($company_sql);
$company_stmt->execute([$tenant_id]);
$company = $company_stmt->fetch(PDO::FETCH_ASSOC);

// Get user data
$user_sql = "SELECT full_name FROM users WHERE id = ?";
$user_stmt = $pdo->prepare($user_sql);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get filters from URL
$branch_id = $_GET['branch_id'] ?? null;
$product_id = $_GET['product_id'] ?? null;
$inventory_type_id = $_GET['inventory_type_id'] ?? null;
$stock_status = $_GET['stock_status'] ?? null;
$status = $_GET['status'] ?? null;
$stock_level = $_GET['stock_level'] ?? null;
$from_date = $_GET['from_date'] ?? null;
$to_date = $_GET['to_date'] ?? null;
$valuation_method = $_GET['valuation_method'] ?? 'AVCO';
// 'type' now drives which report is printed: position | item-ledger | branch-ledger | detailed-ledger
$report_type = $_GET['type'] ?? 'position';
$batch_no = $_GET['batch_no'] ?? null;
$expiry_date = $_GET['expiry_date'] ?? null;

// Get inventory type name if provided
$inventory_type_name = 'All Inventory Types';
if ($inventory_type_id) {
    $type_sql = "SELECT name FROM accounts WHERE id = ? AND tenant_id = ?";
    $type_stmt = $pdo->prepare($type_sql);
    $type_stmt->execute([$inventory_type_id, $tenant_id]);
    $type_result = $type_stmt->fetch(PDO::FETCH_ASSOC);
    if ($type_result) {
        $inventory_type_name = $type_result['name'];
    }
}

// Per-report-type configuration: title + table columns.
// Columns marked 'batch' => true are the ones the user was missing (Batch No / Expiry Date).
$report_config = [
    'position' => [
        'title' => 'Stock Position Report',
        'columns' => [
            ['key' => 'product',        'label' => 'Product'],
            ['key' => 'branch',         'label' => 'Branch'],
            ['key' => 'opening',        'label' => 'Opening Balance'],
            ['key' => 'qty_in',         'label' => 'Total Qty In'],
            ['key' => 'qty_out',        'label' => 'Total Qty Out'],
            ['key' => 'current_stock',  'label' => 'Current Stock'],
            ['key' => 'unit_cost',      'label' => 'Unit Cost'],
            ['key' => 'stock_value',    'label' => 'Stock Value'],
            ['key' => 'status',         'label' => 'Status'],
        ],
    ],
    'item-ledger' => [
    'title' => 'Item-wise Stock Ledger Report',
    'columns' => [
        ['key' => 'date',        'label' => 'Date'],
        ['key' => 'invoice',     'label' => 'Invoice #'],
        ['key' => 'name',        'label' => 'Name'],
        ['key' => 'item_company','label' => 'Item Company'],
        ['key' => 'company',     'label' => 'Company'],
        ['key' => 'customer',    'label' => 'Customer'],
        ['key' => 'transaction', 'label' => 'Transaction'],
        ['key' => 'batch_no',    'label' => 'Batch No'],
        ['key' => 'expiry',      'label' => 'Expiry Date'],
        ['key' => 'qty_in',      'label' => 'Qty In'],
        ['key' => 'qty_out',     'label' => 'Qty Out'],
        ['key' => 'balance',     'label' => 'Balance'],
        ['key' => 'unit_cost',   'label' => 'Unit Cost'],
        ['key' => 'value',       'label' => 'Value'],
    ],
],
    'branch-ledger' => [
        'title' => 'Branch-wise Stock Ledger Report',
        'columns' => [
            ['key' => 'date',        'label' => 'Date'],
            ['key' => 'product',     'label' => 'Product'],
            ['key' => 'item_company','label' => 'Item Company'],
            ['key' => 'transaction', 'label' => 'Transaction'],
            ['key' => 'qty_in',      'label' => 'Qty In'],
            ['key' => 'qty_out',     'label' => 'Qty Out'],
            ['key' => 'balance',     'label' => 'Balance'],
            ['key' => 'unit_cost',   'label' => 'Unit Cost'],
            ['key' => 'value',       'label' => 'Value'],
        ],
    ],
    'detailed-ledger' => [
        'title' => 'Detailed Stock Ledger Report',
        'columns' => [
            ['key' => 'date',        'label' => 'Date'],
            ['key' => 'product',     'label' => 'Product'],
            ['key' => 'item_company','label' => 'Item Company'],
            ['key' => 'branch',      'label' => 'Branch'],
            ['key' => 'transaction', 'label' => 'Transaction'],
            ['key' => 'batch_no',    'label' => 'Batch No'],
            ['key' => 'expiry',      'label' => 'Expiry Date'],
            ['key' => 'qty_in',      'label' => 'Qty In'],
            ['key' => 'qty_out',     'label' => 'Qty Out'],
            ['key' => 'balance',     'label' => 'Balance'],
            ['key' => 'unit_cost',   'label' => 'Unit Cost'],
            ['key' => 'value',       'label' => 'Value'],
        ],
    ],
];

if (!isset($report_config[$report_type])) {
    $report_type = 'position';
}
$config = $report_config[$report_type];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['title']) ?> - Print View</title>
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
            margin-top: 0%;
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
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button class="print-btn" onclick="window.print()">Print Report</button>
        <button class="excel-btn" onclick="exportToExcel()">Export to Excel</button>
    </div>

    <div class="header">
        <?php if ($company['logo_url']): ?>
            <img src="../../../assets/uploads/company_logo/<?= htmlspecialchars($company['logo_url']) ?>" alt="Company Logo" style="max-height: 60px; margin-bottom: 10px;">
        <?php endif; ?>
        <div class="company-name"><?= $company['company_name'] ?? 'LedgerOne ERP' ?></div>
        <?php if ($company['address']): ?>
            <div style="font-size: 12px; margin-bottom: 5px;"><?= htmlspecialchars($company['address']) ?></div>
        <?php endif; ?>
        <?php if ($company['phone'] || $company['email']): ?>
            <div style="font-size: 12px; margin-bottom: 10px;">
                <?= $company['phone'] ? 'Phone: ' . htmlspecialchars($company['phone']) : '' ?>
                <?= $company['phone'] && $company['email'] ? ' | ' : '' ?>
                <?= $company['email'] ? 'Email: ' . htmlspecialchars($company['email']) : '' ?>
            </div>
        <?php endif; ?>
        <div class="report-title"><?= htmlspecialchars($config['title']) ?></div>
        <div class="report-date">Generated on: <?= date('Y-m-d H:i:s') ?></div>
        <div class="report-date">Generated by: <?= $user['full_name'] ?? 'System User' ?></div>
    </div>

    <div class="filters">
        <strong>Filters Applied:</strong>
        Branch: <?= $branch_id ? 'Selected' : 'All Branches' ?> |
        Product: <?= $product_id ? 'Selected' : 'All Products' ?> |
        <?php if ($report_type === 'position'): ?>
        Inventory Type: <?= htmlspecialchars($inventory_type_name) ?> |
        Stock Status: <?= $stock_status ? ucfirst($stock_status) : 'All' ?> |
        Status: <?= $status ?: 'All Statuses' ?> |
        Stock Level: <?= $stock_level === 'zero-or-less' ? 'Stock = 0 or Less' : ($stock_level === 'greater-than-zero' ? 'Stock > 0' : 'All Levels') ?> |
        <?php endif; ?>
        <?php if ($report_type === 'item-ledger' || $report_type === 'detailed-ledger'): ?>
        Batch No: <?= $batch_no ? htmlspecialchars($batch_no) : 'All' ?> |
        Expiry Date: <?= $expiry_date ? htmlspecialchars($expiry_date) : 'All' ?> |
        <?php endif; ?>
        Period: <?= $from_date && $to_date ? date('M d, Y', strtotime($from_date)) . ' to ' . date('M d, Y', strtotime($to_date)) : 'All Dates' ?>
        <?php if ($report_type === 'position'): ?>
        | Valuation Method: <?= $valuation_method === 'TRADE_PRICE' ? 'Market Value - Trade Price' : $valuation_method ?>
        <?php endif; ?>
    </div>

    <?php if ($report_type === 'position' && $valuation_method === 'TRADE_PRICE'): ?>
    <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-left: 4px solid #f39c12; padding: 16px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(243, 156, 18, 0.15);">
        <div style="display: flex; align-items: start; gap: 12px;">
            <div style="color: #f39c12; font-size: 20px; margin-top: 2px;">ℹ️</div>
            <div>
                <div style="font-weight: 600; color: #856404; margin-bottom: 4px; font-size: 14px;">Market Value Estimation</div>
                <div style="color: #856404; font-size: 13px; line-height: 1.6;">
                    This report shows estimated market value based on trade price. It is not an accounting valuation and should not be used for financial reporting.
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <table id="reportTable">
        <thead>
            <tr>
                <?php foreach ($config['columns'] as $col): ?>
                    <th class="col-<?= $col['key'] ?>"><?= htmlspecialchars($col['label']) ?></th>
                <?php endforeach; ?>
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
        const REPORT_TYPE = <?= json_encode($report_type) ?>;
        const COLUMN_KEYS = <?= json_encode(array_column($config['columns'], 'key')) ?>;

        // Holds the data + currency currently rendered, so Export to Excel
        // exports exactly what's shown on screen (same filters, same columns).
        let currentRows = [];
        let currentCurrency = '$';

        function numberToWords(num) {
            const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
            const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
            const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            const thousands = ['', 'Thousand', 'Million', 'Billion'];

            if (num === 0) return 'Zero';

            function convertHundreds(n) {
                let result = '';
                if (n >= 100) {
                    result += ones[Math.floor(n / 100)] + ' Hundred ';
                    n %= 100;
                }
                if (n >= 20) {
                    result += tens[Math.floor(n / 10)] + ' ';
                    n %= 10;
                } else if (n >= 10) {
                    result += teens[n - 10] + ' ';
                    n = 0;
                }
                if (n > 0) {
                    result += ones[n] + ' ';
                }
                return result;
            }

            const dollars = Math.floor(num);
            const cents = Math.round((num - dollars) * 100);

            let result = '';
            let tempNum = dollars;
            let thousandIndex = 0;

            if (dollars === 0) {
                result = 'Zero';
            } else {
                while (tempNum > 0) {
                    if (tempNum % 1000 !== 0) {
                        result = convertHundreds(tempNum % 1000) + thousands[thousandIndex] + ' ' + result;
                    }
                    tempNum = Math.floor(tempNum / 1000);
                    thousandIndex++;
                }
            }

            result = result.trim();

            if (cents > 0) {
                result += ' and ' + convertHundreds(cents).trim() + ' Cents';
            }

            return result;
        }

        // Same batch/expiry filtering logic used on the main Stock Management page
        function filterByBatchExpiry(entries, batchFilter, expiryFilter) {
            const batch = (batchFilter || '').trim().toLowerCase();
            return entries.filter(entry => {
                if (batch) {
                    const entryBatch = String(entry.batch_no || '').toLowerCase();
                    if (!entryBatch.includes(batch)) return false;
                }
                if (expiryFilter) {
                    const entryExpiry = String(entry.batch_expiry_date || '').slice(0, 10);
                    if (entryExpiry !== expiryFilter) return false;
                }
                return true;
            });
        }

        // Running balance per product-branch, mirrors the main page's computeRunningBalances()
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

        async function loadPrintData() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const batchFilter = urlParams.get('batch_no');
                const expiryFilter = urlParams.get('expiry_date');
                let rows = [];
                let currency = '$';

                if (REPORT_TYPE === 'position') {
                    let url = `${API_BASE}?action=position`;
                    if (urlParams.get('branch_id')) url += `&branch_id=${urlParams.get('branch_id')}`;
                    if (urlParams.get('product_id')) url += `&product_id=${urlParams.get('product_id')}`;
                    if (urlParams.get('inventory_type_id')) url += `&inventory_type_id=${urlParams.get('inventory_type_id')}`;
                    if (urlParams.get('stock_status')) url += `&stock_status=${urlParams.get('stock_status')}`;
                    if (urlParams.get('status')) url += `&status=${urlParams.get('status')}`;
                    if (urlParams.get('from_date')) url += `&from_date=${urlParams.get('from_date')}`;
                    if (urlParams.get('to_date')) url += `&to_date=${urlParams.get('to_date')}`;
                    if (urlParams.get('valuation_method')) url += `&valuation_method=${urlParams.get('valuation_method')}`;
                    if (urlParams.get('item_company_id')) url += `&item_company_id=${urlParams.get('item_company_id')}`;

                    const response = await fetch(url);
                    const data = await response.json();
                    if (!data.success) return;
                    rows = data.data;
                    currency = data.currency || '$';

                    const stockLevel = urlParams.get('stock_level');
                    if (stockLevel === 'zero-or-less') {
                        rows = rows.filter(pos => parseFloat(pos.current_stock) <= 0);
                    } else if (stockLevel === 'greater-than-zero') {
                        rows = rows.filter(pos => parseFloat(pos.current_stock) > 0);
                    }
              } else if (REPORT_TYPE === 'item-ledger') {
    let url = `${API_BASE}?action=ledger`;
    if (urlParams.get('product_id'))  url += `&product_id=${urlParams.get('product_id')}`;
    if (urlParams.get('vendor_id'))   url += `&vendor_id=${urlParams.get('vendor_id')}`;
    if (urlParams.get('item_company_id')) url += `&item_company_id=${urlParams.get('item_company_id')}`;
    if (urlParams.get('customer_id')) url += `&customer_id=${urlParams.get('customer_id')}`;
    if (urlParams.get('from_date'))   url += `&from_date=${urlParams.get('from_date')}`;
    if (urlParams.get('to_date'))     url += `&to_date=${urlParams.get('to_date')}`;

    const response = await fetch(url);
    const data = await response.json();
    if (!data.success) return;
    rows = filterByBatchExpiry(data.data, batchFilter, expiryFilter);
    currency = data.currency || '$';
} else if (REPORT_TYPE === 'branch-ledger') {
                    let url = `${API_BASE}?action=branch-ledger`;
                    if (urlParams.get('branch_id')) url += `&branch_id=${urlParams.get('branch_id')}`;
                    if (urlParams.get('from_date')) url += `&from_date=${urlParams.get('from_date')}`;
                    if (urlParams.get('to_date')) url += `&to_date=${urlParams.get('to_date')}`;
                    if (urlParams.get('item_company_id')) url += `&item_company_id=${urlParams.get('item_company_id')}`;

                    const response = await fetch(url);
                    const data = await response.json();
                    if (!data.success) return;
                    rows = data.data;
                    currency = data.currency || '$';
                } else if (REPORT_TYPE === 'detailed-ledger') {
                    let url = `${API_BASE}?action=detailed-ledger`;
                    if (urlParams.get('branch_id')) url += `&branch_id=${urlParams.get('branch_id')}`;
                    if (urlParams.get('product_id')) url += `&product_id=${urlParams.get('product_id')}`;
                    if (urlParams.get('item_company_id')) url += `&item_company_id=${urlParams.get('item_company_id')}`;
                    if (urlParams.get('from_date')) url += `&from_date=${urlParams.get('from_date')}`;
                    if (urlParams.get('to_date')) url += `&to_date=${urlParams.get('to_date')}`;

                    const response = await fetch(url);
                    const data = await response.json();
                    if (!data.success) return;
                    const filtered = filterByBatchExpiry(data.data, batchFilter, expiryFilter);
                    rows = computeRunningBalances(filtered);
                    currency = data.currency || '$';
                }

                currentRows = rows;
                currentCurrency = currency;
                renderPrintTable(rows, currency);
            } catch (error) {
                console.error('Error loading print data:', error);
            }
        }

        function fmtQty(val, unit) {
            return `${parseFloat(val || 0).toFixed(2)} ${unit || 'L'}`;
        }

        function statusLabel(status) {
            return (status || '').replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        function renderPositionRows(rows) {
            return rows.map(pos => {
                const isChild = pos.parent_product_id;
                const productDisplay = isChild ? `<span style="margin-left: 20px; color: #6b7280;">↳ ${pos.product_name}</span>` : pos.product_name;
                return `
                <tr style="${isChild ? 'background-color: #fafbfd;' : ''}">
                    <td>${productDisplay}</td>
                    <td>${pos.branch_display}</td>
                    <td>${fmtQty(pos.opening_balance, pos.unit_symbol)}</td>
                    <td>${fmtQty(pos.total_qty_in, pos.unit_symbol)}</td>
                    <td>${fmtQty(pos.total_qty_out, pos.unit_symbol)}</td>
                    <td>${fmtQty(pos.current_stock, pos.unit_symbol)}</td>
                    <td>${currentCurrency}${parseFloat(pos.unit_cost).toFixed(2)}</td>
                    <td>${currentCurrency}${parseFloat(pos.stock_value).toFixed(2)}</td>
                    <td>${statusLabel(pos.status)}</td>
                </tr>`;
            }).join('');
        }

       function renderItemLedgerRows(rows) {
    return rows.map(entry => `
        <tr>
            <td>${entry.transaction_date}</td>
            <td>${entry.invoice_number || '-'}</td>
            <td>${entry.product_name || '-'}</td>
            <td>${entry.item_company_name || '-'}</td>
            <td>${entry.company_name || '-'}</td>
            <td>${entry.customer_name || '-'}</td>
            <td>${entry.transaction_type}</td>
            <td>${entry.batch_no || '-'}</td>
            <td>${entry.batch_expiry_date || '-'}</td>
            <td>${entry.qty_in > 0 ? fmtQty(entry.qty_in, entry.unit_symbol) : '-'}</td>
            <td>${entry.qty_out > 0 ? fmtQty(entry.qty_out, entry.unit_symbol) : '-'}</td>
            <td>${fmtQty(entry.balance, entry.unit_symbol)}</td>
            <td>${currentCurrency}${parseFloat(entry.unit_cost).toFixed(2)}</td>
            <td>${currentCurrency}${parseFloat(entry.value).toFixed(2)}</td>
        </tr>`).join('');
}

        function renderBranchLedgerRows(rows) {
            return rows.map(entry => `
                <tr>
                    <td>${entry.transaction_date}</td>
                    <td>${entry.product_name}</td>
                    <td>${entry.item_company_name || '-'}</td>
                    <td>${entry.transaction_type}</td>
                    <td>${entry.qty_in > 0 ? fmtQty(entry.qty_in, entry.unit_symbol) : '-'}</td>
                    <td>${entry.qty_out > 0 ? fmtQty(entry.qty_out, entry.unit_symbol) : '-'}</td>
                    <td>-</td>
                    <td>${currentCurrency}${parseFloat(entry.unit_cost).toFixed(2)}</td>
                    <td>${currentCurrency}${((parseFloat(entry.qty_in || 0) - parseFloat(entry.qty_out || 0)) * parseFloat(entry.unit_cost)).toFixed(2)}</td>
                </tr>`).join('');
        }

        function renderDetailedLedgerRows(rows) {
            return rows.map(entry => `
                <tr>
                    <td>${entry.transaction_date}</td>
                    <td>${entry.product_name}</td>
                    <td>${entry.item_company_name || '-'}</td>
                    <td>${entry.branch_name}</td>
                    <td>${entry.transaction_type}</td>
                    <td>${entry.batch_no || '-'}</td>
                    <td>${entry.batch_expiry_date || '-'}</td>
                    <td>${entry.qty_in > 0 ? fmtQty(entry.qty_in, entry.unit_symbol) : '-'}</td>
                    <td>${entry.qty_out > 0 ? fmtQty(entry.qty_out, entry.unit_symbol) : '-'}</td>
                    <td>${fmtQty(entry.balance, entry.unit_symbol)}</td>
                    <td>${currentCurrency}${parseFloat(entry.unit_cost).toFixed(2)}</td>
                    <td>${currentCurrency}${parseFloat(entry.value).toFixed(2)}</td>
                </tr>`).join('');
        }

        function renderPrintTable(rows, currency = '$') {
            const tbody = document.querySelector('#reportTable tbody');
            const colCount = COLUMN_KEYS.length;
            let bodyHtml = '';
            let totalsHtml = '';
            let wordsHtml = '';

            if (REPORT_TYPE === 'position') {
                bodyHtml = renderPositionRows(rows);

                const totalValue = rows.reduce((sum, pos) => !pos.parent_product_id ? sum + parseFloat(pos.stock_value) : sum, 0);
                const totalEntries = rows.filter(pos => !pos.parent_product_id).length;
                const unitTotals = rows.reduce((totals, pos) => {
                    if (!pos.parent_product_id) {
                        const unit = pos.unit_symbol || 'L';
                        totals[unit] = (totals[unit] || 0) + parseFloat(pos.current_stock);
                    }
                    return totals;
                }, {});
                const unitTotalsText = Object.entries(unitTotals).map(([u, t]) => `${t.toFixed(2)} ${u}`).join(', ');

                totalsHtml = `
                    <tr class="totals-row">
                        <td colspan="2">Total (${totalEntries} entries)</td>
                        <td>-</td><td>-</td><td>-</td>
                        <td>${unitTotalsText}</td>
                        <td>-</td>
                        <td>${currency}${totalValue.toFixed(2)}</td>
                        <td></td>
                    </tr>`;

                if (totalValue > 0) {
                    wordsHtml = `<tr><td colspan="${colCount}" style="text-align: center; font-style: italic; padding: 10px;">Amount in Words: ${numberToWords(totalValue)} Only</td></tr>`;
                }
          } else if (REPORT_TYPE === 'item-ledger') {
    bodyHtml = renderItemLedgerRows(rows);
    const totalQtyIn = rows.reduce((s, e) => s + parseFloat(e.qty_in || 0), 0);
    const totalQtyOut = rows.reduce((s, e) => s + parseFloat(e.qty_out || 0), 0);
    const finalBalance = rows.length ? parseFloat(rows[rows.length - 1].balance) : 0;
    const finalValue = rows.length ? parseFloat(rows[rows.length - 1].value) : 0;
    const unit = rows[0]?.unit_symbol || 'L';
    totalsHtml = `
        <tr class="totals-row">
            <td colspan="9">Total (${rows.length} transactions)</td>
            <td>${totalQtyIn.toFixed(2)} ${unit}</td>
            <td>${totalQtyOut.toFixed(2)} ${unit}</td>
            <td>${finalBalance.toFixed(2)} ${unit}</td>
            <td>-</td>
            <td>${currency}${finalValue.toFixed(2)}</td>
        </tr>`;
} else if (REPORT_TYPE === 'branch-ledger') {
                bodyHtml = renderBranchLedgerRows(rows);
                const totalQtyIn = rows.reduce((s, e) => s + parseFloat(e.qty_in || 0), 0);
                const totalQtyOut = rows.reduce((s, e) => s + parseFloat(e.qty_out || 0), 0);
                const totalValue = rows.reduce((s, e) => s + (parseFloat(e.qty_in || 0) - parseFloat(e.qty_out || 0)) * parseFloat(e.unit_cost), 0);
                const unit = rows[0]?.unit_symbol || 'L';
                totalsHtml = `
                    <tr class="totals-row">
                        <td colspan="4">Total (${rows.length} transactions)</td>
                        <td>${totalQtyIn.toFixed(2)} ${unit}</td>
                        <td>${totalQtyOut.toFixed(2)} ${unit}</td>
                        <td>${(totalQtyIn - totalQtyOut).toFixed(2)} ${unit}</td>
                        <td>-</td>
                        <td>${currency}${totalValue.toFixed(2)}</td>
                    </tr>`;
            } else if (REPORT_TYPE === 'detailed-ledger') {
                bodyHtml = renderDetailedLedgerRows(rows);
                const totalQtyIn = rows.reduce((s, e) => s + parseFloat(e.qty_in || 0), 0);
                const totalQtyOut = rows.reduce((s, e) => s + parseFloat(e.qty_out || 0), 0);
                const balances = {};
                rows.forEach(e => { const k = `${e.product_name}-${e.branch_name}`; balances[k] = e.balance; });
                const totalBalance = Object.values(balances).reduce((s, b) => s + b, 0);
                const finalValues = {};
                rows.forEach(e => { const k = `${e.product_name}-${e.branch_name}`; finalValues[k] = e.value; });
                const totalValue = Object.values(finalValues).reduce((s, v) => s + v, 0);
                const unit = rows[0]?.unit_symbol || 'L';
                totalsHtml = `
                    <tr class="totals-row">
                        <td colspan="7">Total (${rows.length} transactions)</td>
                        <td>${totalQtyIn.toFixed(2)} ${unit}</td>
                        <td>${totalQtyOut.toFixed(2)} ${unit}</td>
                        <td>${totalBalance.toFixed(2)} ${unit}</td>
                        <td>-</td>
                        <td>${currency}${totalValue.toFixed(2)}</td>
                    </tr>`;
            }

            tbody.innerHTML = bodyHtml + totalsHtml + wordsHtml;
        }

        // Export to Excel — mirrors exactly what's shown in the print preview
        function exportToExcel() {
            if (typeof XLSX === 'undefined') {
                alert('Excel export library failed to load. Please check your internet connection and reload the page.');
                return;
            }
            if (!currentRows || currentRows.length === 0) {
                alert('No data available to export.');
                return;
            }

            let rows = [];
            if (REPORT_TYPE === 'position') {
                rows = currentRows.map(pos => ({
                    'Product': pos.product_name,
                    'Branch': pos.branch_display,
                    'Opening Balance': fmtQty(pos.opening_balance, pos.unit_symbol),
                    'Total Qty In': fmtQty(pos.total_qty_in, pos.unit_symbol),
                    'Total Qty Out': fmtQty(pos.total_qty_out, pos.unit_symbol),
                    'Current Stock': fmtQty(pos.current_stock, pos.unit_symbol),
                    'Unit Cost': parseFloat(pos.unit_cost).toFixed(2),
                    'Stock Value': parseFloat(pos.stock_value).toFixed(2),
                    'Status': statusLabel(pos.status)
                }));
            } else if (REPORT_TYPE === 'item-ledger') {
                rows = currentRows.map(e => ({
                    'Date': e.transaction_date,
                    'Transaction': e.transaction_type,
                    'Batch No': e.batch_no || '-',
                    'Expiry Date': e.batch_expiry_date || '-',
                    'Qty In': e.qty_in > 0 ? fmtQty(e.qty_in, e.unit_symbol) : '-',
                    'Qty Out': e.qty_out > 0 ? fmtQty(e.qty_out, e.unit_symbol) : '-',
                    'Balance': fmtQty(e.balance, e.unit_symbol),
                    'Unit Cost': parseFloat(e.unit_cost).toFixed(2),
                    'Value': parseFloat(e.value).toFixed(2)
                }));
            } else if (REPORT_TYPE === 'branch-ledger') {
                rows = currentRows.map(e => ({
                    'Date': e.transaction_date,
                    'Product': e.product_name,
                    'Transaction': e.transaction_type,
                    'Qty In': e.qty_in > 0 ? fmtQty(e.qty_in, e.unit_symbol) : '-',
                    'Qty Out': e.qty_out > 0 ? fmtQty(e.qty_out, e.unit_symbol) : '-',
                    'Unit Cost': parseFloat(e.unit_cost).toFixed(2),
                    'Value': ((parseFloat(e.qty_in || 0) - parseFloat(e.qty_out || 0)) * parseFloat(e.unit_cost)).toFixed(2)
                }));
            } else if (REPORT_TYPE === 'detailed-ledger') {
                rows = currentRows.map(e => ({
                    'Date': e.transaction_date,
                    'Product': e.product_name,
                    'Branch': e.branch_name,
                    'Transaction': e.transaction_type,
                    'Batch No': e.batch_no || '-',
                    'Expiry Date': e.batch_expiry_date || '-',
                    'Qty In': e.qty_in > 0 ? fmtQty(e.qty_in, e.unit_symbol) : '-',
                    'Qty Out': e.qty_out > 0 ? fmtQty(e.qty_out, e.unit_symbol) : '-',
                    'Balance': fmtQty(e.balance, e.unit_symbol),
                    'Unit Cost': parseFloat(e.unit_cost).toFixed(2),
                    'Value': parseFloat(e.value).toFixed(2)
                }));
            }

            const worksheet = XLSX.utils.json_to_sheet(rows);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, REPORT_TYPE);
            XLSX.writeFile(workbook, `${REPORT_TYPE}_${new Date().toISOString().slice(0, 10)}.xlsx`);
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadPrintData();
        });
    </script>
</body>
</html>
