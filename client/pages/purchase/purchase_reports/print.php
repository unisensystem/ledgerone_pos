<?php
require_once '../../../../includes/connection.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

// Get currency symbol
$stmt = $pdo->prepare("SELECT c.symbol FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_base_currency = 1");
$stmt->execute([$tenant_id]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'] ?? '$';

$report_type = $_GET['type'] ?? 'item';
$date_range = $_GET['date_range'] ?? 'last30';
$company_id = $_GET['company_id'] ?? null;
$project_id = $_GET['project_id'] ?? null;
$supplier_id = $_GET['supplier_id'] ?? null;
$search_term = $_GET['search'] ?? '';

// Display labels for the applied filters (sent from the list page along with the ids)
$company_name = $_GET['company_name'] ?? '';
$project_name = $_GET['project_name'] ?? '';
$supplier_name = $_GET['supplier_name'] ?? '';

$has_filters = $company_name !== '' || $project_name !== '' || $supplier_name !== '' || $search_term !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Report - Print</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            padding: 24px;
            color: #2b2b2b;
            font-size: 13px;
            line-height: 1.5;
        }

        .print-header {
            text-align: center;
            margin-bottom: 24px;
            border-bottom: 2px solid #333;
            padding-bottom: 16px;
        }

        .print-header h1 {
            font-size: 24px;
            margin-bottom: 4px;
        }

        .print-header p {
            font-size: 14px;
            color: #666;
        }

        .report-info {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 12px;
            background: #f7f9fc;
            border: 1px solid #e1e6ee;
            border-radius: 4px;
            padding: 10px 14px;
        }

        .report-info div {
            flex: 1;
        }

        .report-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }

        .filter-chip {
            display: inline-block;
            font-size: 11px;
            background: #eef1f7;
            border: 1px solid #d7deea;
            border-radius: 12px;
            padding: 4px 10px;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 9px;
            text-align: left;
            font-size: 12px;
        }

        th {
            background-color: #eef1f7;
            font-weight: bold;
        }

        #reportTable > tbody > tr:nth-child(4n+1) {
            background-color: #fafbfd;
        }

        /* Nested breakdown styling */
        .breakdown-wrap {
            padding: 0 !important;
        }

        .breakdown-inner {
            padding: 10px 10px 10px 26px;
            background-color: #fafbfd;
        }

        .breakdown-inner h4 {
            font-size: 11px;
            color: #555;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .breakdown-inner table {
            margin-bottom: 12px;
            background: #fff;
        }

        .breakdown-inner table th,
        .breakdown-inner table td {
            font-size: 11px;
            padding: 7px;
        }

        .breakdown-inner table th {
            background-color: #eef1f7;
        }

        .subcat-block, .invoice-block {
            margin-bottom: 12px;
            border: 1px solid #e1e6ee;
            border-radius: 4px;
            padding: 10px;
            background: #fff;
        }

        .subcat-block-header, .invoice-block-header {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e1e6ee;
        }

        .expiring-soon {
            color: #d64545;
            font-weight: 600;
        }

        .print-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }

            .subcat-block, .invoice-block {
                break-inside: avoid;
            }

            @page {
                margin: 1cm;
            }
        }
    </style>
</head>
<body>
    <div class="print-header">
        <h1>Purchase Report</h1>
        <p id="reportTitle">Loading...</p>
    </div>

    <div class="report-info">
        <div>
            <strong>Report Type:</strong> <span id="reportType"></span>
        </div>
        <div>
            <strong>Date Range:</strong> <span id="dateRange"></span>
        </div>
        <div>
            <strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>

    <?php if ($has_filters): ?>
    <div class="report-filters">
        <?php if ($company_name !== ''): ?>
            <span class="filter-chip">Company: <?php echo htmlspecialchars($company_name); ?></span>
        <?php endif; ?>
        <?php if ($project_name !== ''): ?>
            <span class="filter-chip">Project: <?php echo htmlspecialchars($project_name); ?></span>
        <?php endif; ?>
        <?php if ($supplier_name !== ''): ?>
            <span class="filter-chip">Supplier: <?php echo htmlspecialchars($supplier_name); ?></span>
        <?php endif; ?>
        <?php if ($search_term !== ''): ?>
            <span class="filter-chip">Search: "<?php echo htmlspecialchars($search_term); ?>"</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <table id="reportTable">
        <thead id="tableHead"></thead>
        <tbody id="tableBody"></tbody>
    </table>

    <div class="print-footer">
        <p>Generated by FuelingSys ERP - Purchase Reports Module</p>
    </div>

    <script>
        const reportType = '<?php echo $report_type; ?>';
        const dateRange = '<?php echo $date_range; ?>';
        const companyId = '<?php echo $company_id; ?>';
        const projectId = '<?php echo $project_id; ?>';
        const supplierId = '<?php echo $supplier_id; ?>';
        const searchTerm = '<?php echo addslashes($search_term); ?>';
        const currencySymbol = '<?php echo $currency_symbol; ?>';

        const tableHeaders = {
            item: ["Item ID", "Item Name", "Category", "Batch No", "Expiry Date", "Quantity", "Unit Price", "Total Value", "Supplier"],
            invoice: ["Invoice ID", "Invoice Name", "Category", "Batch No", "Expiry Date", "Items", "Due Date", "Total Value", "Supplier"],
            category: ["Category ID", "Category Name", "Type", "Batch No", "Expiry Date", "Items", "Avg. Price", "Total Value", "Suppliers"],
            supplier: ["Supplier ID", "Supplier Name", "Batch No", "Expiry Date", "Items", "Avg. Price", "Total Value", "Primary Contact"]
        };

        const reportTitles = {
            item: 'Item-wise Purchase Report',
            invoice: 'Invoice-wise Purchase Report',
            category: 'Category-wise Purchase Report',
            supplier: 'Supplier-wise Purchase Report'
        };

        document.getElementById('reportTitle').textContent = reportTitles[reportType];
        document.getElementById('reportType').textContent = reportTitles[reportType];
        document.getElementById('dateRange').textContent = dateRange;

        // Same search filter the list page applies, so print shows exactly what's filtered on screen
        function applySearchFilter(data, term) {
            if (!term) return data;
            const t = term.toLowerCase();
            return data.filter(item => Object.keys(item).some(key => {
                const val = item[key];
                return typeof val === 'string' && val.toLowerCase().includes(t);
            }));
        }

        // Fetch and display data
        let url = `../../../../server/api/purchase/purchase_reports/purchase-reports.php?type=${reportType}&date_range=${dateRange}`;
        if (companyId && companyId !== 'null') url += `&company_id=${companyId}`;
        if (projectId && projectId !== 'null') url += `&project_id=${projectId}`;
        if (supplierId && supplierId !== 'null') url += `&supplier_id=${supplierId}`;

        fetch(url)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const filtered = applySearchFilter(result.data, searchTerm);
                    renderTable(filtered);
                    setTimeout(() => window.print(), 500);
                } else {
                    document.getElementById('tableBody').innerHTML =
                        '<tr><td colspan="9" style="text-align:center; padding:20px;">Failed to load report data</td></tr>';
                }
            })
            .catch(() => {
                document.getElementById('tableBody').innerHTML =
                    '<tr><td colspan="9" style="text-align:center; padding:20px;">Error loading report data</td></tr>';
            });

        // Highlight expiry date if within 30 days
        function renderExpiryCell(expiryDate) {
            if (!expiryDate || expiryDate === '-') return '-';
            const isExpiringSoon = (new Date(expiryDate) - new Date()) / (1000 * 60 * 60 * 24) <= 30;
            return isExpiringSoon
                ? `<span class="expiring-soon">${expiryDate}</span>`
                : expiryDate;
        }

        function renderTable(data) {
            const tableHead = document.getElementById('tableHead');
            const tableBody = document.getElementById('tableBody');
            const colCount = tableHeaders[reportType].length;

            // Render headers
            const headerRow = document.createElement('tr');
            tableHeaders[reportType].forEach(header => {
                const th = document.createElement('th');
                th.textContent = header;
                headerRow.appendChild(th);
            });
            tableHead.appendChild(headerRow);

            if (!data || data.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="${colCount}" style="text-align:center; padding:20px;">No data available</td></tr>`;
                return;
            }

            // Render data rows + nested breakdown rows
            data.forEach(item => {
                const row = document.createElement('tr');
                let cells = [];

                if (reportType === 'item') {
                    cells = [item.id, item.name, item.category, item.batchNo || '-', renderExpiryCell(item.expiryDate), item.quantity, item.unitPrice, item.total, item.supplier];
                } else if (reportType === 'invoice') {
                    cells = [item.id, item.name, item.category, item.batchNo || '-', renderExpiryCell(item.expiryDate), item.quantity, item.dueDate, item.total, item.supplier];
                } else if (reportType === 'category') {
                    cells = [item.id, item.name, item.category, item.batchNo || '-', renderExpiryCell(item.expiryDate), item.quantity, item.unitPrice, item.total, item.supplier];
                } else if (reportType === 'supplier') {
                    cells = [item.id, item.name, item.batchNo || '-', renderExpiryCell(item.expiryDate), item.quantity, item.unitPrice, item.total, item.contact];
                }

                cells.forEach(cellContent => {
                    const td = document.createElement('td');
                    td.innerHTML = cellContent;
                    row.appendChild(td);
                });

                tableBody.appendChild(row);

                // Append nested breakdown row right after, always expanded
                const breakdownHtml = buildBreakdownHtml(item, colCount);
                if (breakdownHtml) {
                    const breakdownRow = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = colCount;
                    td.className = 'breakdown-wrap';
                    td.innerHTML = breakdownHtml;
                    breakdownRow.appendChild(td);
                    tableBody.appendChild(breakdownRow);
                }
            });
        }

        function buildBreakdownHtml(item, colCount) {
            if (reportType === 'item') {
                return buildInvoicesTable(item.invoices, 'Purchase Invoices');
            } else if (reportType === 'invoice') {
                return buildItemsTable(item.items, 'Invoice Items Breakdown');
            } else if (reportType === 'supplier') {
                return buildSupplierInvoicesBlock(item.invoices);
            } else if (reportType === 'category') {
                return buildCategorySubcategoriesBlock(item.subcategories);
            }
            return '';
        }

        // item report: flat invoices table
        function buildInvoicesTable(invoices, title) {
            if (!invoices || invoices.length === 0) return '';
            let rows = invoices.map(inv => `
                <tr>
                    <td>${inv.billNo}</td>
                    <td>${inv.date}</td>
                    <td>${inv.batchNo || '-'}</td>
                    <td>${renderExpiryCell(inv.expiryDate)}</td>
                    <td>${inv.quantity}</td>
                    <td>${inv.price}</td>
                    <td>${inv.total}</td>
                    <td>${inv.supplier}</td>
                </tr>
            `).join('');

            return `
                <div class="breakdown-inner">
                    <h4>${title}</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Invoice No</th><th>Date</th><th>Batch No</th><th>Expiry Date</th>
                                <th>Quantity</th><th>Unit Price</th><th>Total</th><th>Supplier</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;
        }

        // invoice report: flat items table
        function buildItemsTable(items, title) {
            if (!items || items.length === 0) return '';
            let rows = items.map(it => `
                <tr>
                    <td>${it.product}</td>
                    <td>${it.batchNo || '-'}</td>
                    <td>${renderExpiryCell(it.expiryDate)}</td>
                    <td>${it.quantity}</td>
                    <td>${it.price}</td>
                    <td>${it.total}</td>
                </tr>
            `).join('');

            return `
                <div class="breakdown-inner">
                    <h4>${title}</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th><th>Batch No</th><th>Expiry Date</th>
                                <th>Quantity</th><th>Unit Price</th><th>Total</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;
        }

        // supplier report: invoices, each with its own items table
        function buildSupplierInvoicesBlock(invoices) {
            if (!invoices || invoices.length === 0) return '';
            let blocks = invoices.map(inv => `
                <div class="invoice-block">
                    <div class="invoice-block-header">
                        <span>Invoice: ${inv.billNo}</span>
                        <span>Date: ${inv.date}</span>
                        <span>Total: ${inv.total}</span>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th><th>Batch No</th><th>Expiry Date</th>
                                <th>Quantity</th><th>Unit Price</th><th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${(inv.items || []).map(it => `
                                <tr>
                                    <td>${it.product}</td>
                                    <td>${it.batchNo || '-'}</td>
                                    <td>${renderExpiryCell(it.expiryDate)}</td>
                                    <td>${it.quantity}</td>
                                    <td>${it.price}</td>
                                    <td>${it.total}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `).join('');

            return `
                <div class="breakdown-inner">
                    <h4>Supplier Invoices</h4>
                    ${blocks}
                </div>
            `;
        }

        // category report: subcategories, each with invoices, each with items
        function buildCategorySubcategoriesBlock(subcategories) {
            if (!subcategories || subcategories.length === 0) return '';
            let blocks = subcategories.map(sub => `
                <div class="subcat-block">
                    <div class="subcat-block-header">
                        <span>Subcategory: ${sub.name}</span>
                        <span>Batch No: ${sub.batchNo || '-'}</span>
                        <span>Expiry: ${renderExpiryCell(sub.expiryDate)}</span>
                        <span>Quantity: ${sub.quantity}</span>
                        <span>Total: ${sub.total}</span>
                    </div>
                    ${(sub.invoices || []).map(inv => `
                        <div class="invoice-block">
                            <div class="invoice-block-header">
                                <span>Invoice: ${inv.billNo}</span>
                                <span>Date: ${inv.date}</span>
                                <span>Total: ${inv.total}</span>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product</th><th>Batch No</th><th>Expiry Date</th>
                                        <th>Quantity</th><th>Unit Price</th><th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${(inv.items || []).map(it => `
                                        <tr>
                                            <td>${it.product}</td>
                                            <td>${it.batchNo || '-'}</td>
                                            <td>${renderExpiryCell(it.expiryDate)}</td>
                                            <td>${it.quantity}</td>
                                            <td>${it.price}</td>
                                            <td>${it.total}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `).join('')}
                </div>
            `).join('');

            return `
                <div class="breakdown-inner">
                    <h4>Subcategories</h4>
                    ${blocks}
                </div>
            `;
        }
    </script>
</body>
</html>