<?php
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';
$company_sql = "SELECT company_name, address, phone, email, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1";
$company_stmt = $pdo->prepare($company_sql);
$company_stmt->execute([$tenant_id]);
$company = $company_stmt->fetch(PDO::FETCH_ASSOC);

$user_sql = "SELECT full_name FROM users WHERE id = ?";
$user_stmt = $pdo->prepare($user_sql);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$company_id = $_GET['company_id'] ?? null;
$branch_id = $_GET['branch_id'] ?? null;
$product_id = $_GET['product_id'] ?? null;
$from_date = $_GET['from_date'] ?? null;
$to_date = $_GET['to_date'] ?? null;
$batch_no = $_GET['batch_no'] ?? null;
$expiry_date = $_GET['expiry_date'] ?? null;

// Resolve supplier name for the header
$supplier_name = 'Selected Supplier';
if ($company_id) {
    $sup_sql = "SELECT company_name FROM companies WHERE id = ? AND tenant_id = ?";
    $sup_stmt = $pdo->prepare($sup_sql);
    $sup_stmt->execute([$company_id, $tenant_id]);
    $sup_result = $sup_stmt->fetch(PDO::FETCH_ASSOC);
    if ($sup_result) {
        $supplier_name = $sup_result['company_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company-wise Stock Ledger - Print View</title>
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
        <div class="report-title">Company-wise Stock Ledger Report</div>
        <div class="report-date">Generated on: <?= date('Y-m-d H:i:s') ?></div>
        <div class="report-date">Generated by: <?= $user['full_name'] ?? 'System User' ?></div>
    </div>

    <div class="filters">
        <strong>Filters Applied:</strong>
        Supplier: <?= htmlspecialchars($supplier_name) ?> |
        Branch: <?= $branch_id ? 'Selected' : 'All Branches' ?> |
        Product: <?= $product_id ? 'Selected' : 'All Products' ?> |
        Batch No: <?= $batch_no ? htmlspecialchars($batch_no) : 'All' ?> |
        Expiry Date: <?= $expiry_date ? htmlspecialchars($expiry_date) : 'All' ?> |
        Period: <?= $from_date && $to_date ? date('M d, Y', strtotime($from_date)) . ' to ' . date('M d, Y', strtotime($to_date)) : 'All Dates' ?>
    </div>

    <table id="reportTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Invoice #</th>
                <th>Product</th>
                <th>Batch No</th>
                <th>Expiry Date</th>
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
        let currentRows = [];
        let currentCurrency = '$';

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

        // Running balance per product-branch, same logic as the main page's company-ledger table
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

        function fmtQty(val, unit) {
            return `${parseFloat(val || 0).toFixed(2)} ${unit || 'L'}`;
        }

        async function loadPrintData() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const companyId = urlParams.get('company_id');
                if (!companyId) {
                    document.querySelector('#reportTable tbody').innerHTML =
                        '<tr><td colspan="12" style="text-align:center;">No supplier selected.</td></tr>';
                    return;
                }

                let url = `${API_BASE}?action=company-ledger&company_id=${companyId}`;
                if (urlParams.get('branch_id')) url += `&branch_id=${urlParams.get('branch_id')}`;
                if (urlParams.get('product_id')) url += `&product_id=${urlParams.get('product_id')}`;
                if (urlParams.get('from_date')) url += `&from_date=${urlParams.get('from_date')}`;
                if (urlParams.get('to_date')) url += `&to_date=${urlParams.get('to_date')}`;

                const response = await fetch(url);
                const data = await response.json();
                if (!data.success) return;

                const batchFilter = urlParams.get('batch_no');
                const expiryFilter = urlParams.get('expiry_date');
                const filtered = filterByBatchExpiry(data.data, batchFilter, expiryFilter);
                currentRows = computeRunningBalances(filtered);
                currentCurrency = data.currency || '$';
                renderPrintTable(currentRows, currentCurrency);
            } catch (error) {
                console.error('Error loading company ledger print data:', error);
            }
        }

        function renderPrintTable(rows, currency = '$') {
            const tbody = document.querySelector('#reportTable tbody');

            const bodyHtml = rows.map(entry => `
                <tr>
                    <td>${entry.transaction_date}</td>
                    <td>${entry.invoice_number || '-'}</td>
                    <td>${entry.product_name}</td>
                    <td>${entry.batch_no || '-'}</td>
                    <td>${entry.batch_expiry_date || '-'}</td>
                    <td>${entry.branch_name}</td>
                    <td>${entry.transaction_type}</td>
                    <td>${entry.qty_in > 0 ? fmtQty(entry.qty_in, entry.unit_symbol) : '-'}</td>
                    <td>${entry.qty_out > 0 ? fmtQty(entry.qty_out, entry.unit_symbol) : '-'}</td>
                    <td>${fmtQty(entry.balance, entry.unit_symbol)}</td>
                    <td>${currency}${parseFloat(entry.unit_cost).toFixed(2)}</td>
                    <td>${currency}${parseFloat(entry.value).toFixed(2)}</td>
                </tr>`).join('');

            const totalQtyIn = rows.reduce((s, e) => s + parseFloat(e.qty_in || 0), 0);
            const totalQtyOut = rows.reduce((s, e) => s + parseFloat(e.qty_out || 0), 0);
            const totalValue = rows.reduce((s, e) => s + parseFloat(e.value || 0), 0);

            const totalsHtml = `
                <tr class="totals-row">
                    <td colspan="7">Total (${rows.length} transactions)</td>
                    <td>${totalQtyIn.toFixed(2)} ${rows[0]?.unit_symbol || 'L'}</td>
                    <td>${totalQtyOut.toFixed(2)} ${rows[0]?.unit_symbol || 'L'}</td>
                    <td>-</td>
                    <td>-</td>
                    <td>${currency}${totalValue.toFixed(2)}</td>
                </tr>`;

            tbody.innerHTML = bodyHtml + totalsHtml;
        }

        function exportToExcel() {
            if (typeof XLSX === 'undefined') {
                alert('Excel export library failed to load. Please check your internet connection and reload the page.');
                return;
            }
            if (!currentRows || currentRows.length === 0) {
                alert('No data available to export.');
                return;
            }

            const rows = currentRows.map(e => ({
                'Date': e.transaction_date,
                'Invoice #': e.invoice_number || '-',
                'Product': e.product_name,
                'Batch No': e.batch_no || '-',
                'Expiry Date': e.batch_expiry_date || '-',
                'Branch': e.branch_name,
                'Transaction': e.transaction_type,
                'Qty In': e.qty_in > 0 ? fmtQty(e.qty_in, e.unit_symbol) : '-',
                'Qty Out': e.qty_out > 0 ? fmtQty(e.qty_out, e.unit_symbol) : '-',
                'Balance': fmtQty(e.balance, e.unit_symbol),
                'Unit Cost': parseFloat(e.unit_cost).toFixed(2),
                'Value': parseFloat(e.value).toFixed(2)
            }));

            const worksheet = XLSX.utils.json_to_sheet(rows);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Company Ledger');
            XLSX.writeFile(workbook, `supplier_ledger_${new Date().toISOString().slice(0, 10)}.xlsx`);
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadPrintData();
        });
    </script>
</body>
</html>
