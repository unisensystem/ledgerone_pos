<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'all';
$company_id = $_GET['company_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Print</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
        .print-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .print-header h1 { font-size: 24px; margin-bottom: 5px; }
        .print-header .subtitle { font-size: 14px; color: #666; }
        .report-info { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 12px; }
        .summary-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .summary-card { border: 1px solid #ddd; padding: 15px; text-align: center; }
        .summary-card .label { font-size: 11px; color: #666; margin-bottom: 5px; }
        .summary-card .value { font-size: 20px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 11px; }
        table caption { font-size: 14px; font-weight: bold; text-align: left; margin-bottom: 10px; padding: 8px; background: #f5f5f5; }
        th { background: #333; color: white; padding: 8px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        tbody tr:nth-child(even) { background: #f9f9f9; }
        .footer { text-align: center; font-size: 10px; color: #666; margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
        }
        .print-btn { padding: 10px 20px; background: #1f7bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
        .print-btn:hover { background: #1a6cdc; }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print Report</button>
    
    <div class="print-header">
        <h1>LedgerOne ERP - Sales Report</h1>
        <div class="subtitle">Comprehensive Sales Analysis</div>
    </div>
    
    <div class="report-info">
        <div><strong>Period:</strong> <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></div>
        <div><strong>Report Type:</strong> <?php echo ucfirst($report_type); ?></div>
        <?php if ($company_id): 
            $company_stmt = $pdo->prepare("SELECT company_name FROM companies WHERE id = ? AND tenant_id = ?");
            $company_stmt->execute([$company_id, $_SESSION['tenant_id']]);
            $company_data = $company_stmt->fetch();
        ?>
        <div><strong>Company:</strong> <?php echo $company_data['company_name'] ?? 'All Companies'; ?></div>
        <?php endif; ?>
        <div><strong>Generated:</strong> <?php echo date('M d, Y h:i A'); ?></div>
    </div>
    
    <div class="summary-cards">
        <div class="summary-card">
            <div class="label">Total Sales</div>
            <div class="value" id="total-sales"><?php echo $currency_symbol; ?>0</div>
        </div>
        <div class="summary-card">
            <div class="label">Total Customers</div>
            <div class="value" id="total-customers">0</div>
        </div>
        <div class="summary-card">
            <div class="label">Total Transactions</div>
            <div class="value" id="total-transactions">0</div>
        </div>
        <div class="summary-card">
            <div class="label">Average Sale</div>
            <div class="value" id="avg-sale"><?php echo $currency_symbol; ?>0</div>
        </div>
    </div>
    
    <div id="report-tables"></div>
    
    <div class="footer">
        <p>LedgerOne ERP Sales Report • Confidential • Page generated on <?php echo date('F d, Y'); ?></p>
    </div>
    
    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
        const dateFrom = '<?php echo $date_from; ?>';
        const dateTo = '<?php echo $date_to; ?>';
        const reportType = '<?php echo $report_type; ?>';
        const companyId = '<?php echo $company_id; ?>';
        
        async function loadReportData() {
            try {
                let url = `../../../../server/api/sale/sales_report/sales-report.php?date_from=${dateFrom}&date_to=${dateTo}&report_type=${reportType}`;
                if (companyId) url += `&company_id=${companyId}`;
                
                const response = await fetch(url);
                const result = await response.json();
                
                if (result.success) {
                    populateReport(result.data);
                }
            } catch (error) {
                console.error('Error loading report:', error);
            }
        }
        
        function populateReport(data) {
            // Summary
            const totalSales = (data.officer_sales || []).reduce((sum, item) => sum + parseFloat(item.total_sales || 0), 0);
            const totalCustomers = (data.customer_sales || []).length;
            const totalTransactions = (data.branch_sales || []).reduce((sum, item) => sum + parseFloat(item.transactions || 0), 0);
            const avgSale = totalTransactions > 0 ? totalSales / totalTransactions : 0;
            
            document.getElementById('total-sales').textContent = CURRENCY_SYMBOL + totalSales.toLocaleString();
            document.getElementById('total-customers').textContent = totalCustomers.toLocaleString();
            document.getElementById('total-transactions').textContent = totalTransactions.toLocaleString();
            document.getElementById('avg-sale').textContent = CURRENCY_SYMBOL + avgSale.toLocaleString(undefined, {maximumFractionDigits: 2});
            
            const container = document.getElementById('report-tables');
            let html = '';
            
            // Officer Sales
            if (reportType === 'all' || reportType === 'officer') {
                html += '<table><caption>Sales Officer Wise Sales</caption><thead><tr><th>Employee ID</th><th>Officer Name</th><th>Total Invoices</th><th>Total Sales</th><th>Status</th></tr></thead><tbody>';
                (data.officer_sales || []).forEach(item => {
                    html += `<tr><td>${item.employee_id}</td><td>${item.officer_name}</td><td>${parseFloat(item.total_invoices || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_sales || 0).toLocaleString()}</td><td>${item.status}</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            // Supplier Man Sales
            if (reportType === 'all' || reportType === 'supplier_man') {
                html += '<table><caption>Supplier Man Wise Sales</caption><thead><tr><th>Employee ID</th><th>Supplier Man Name</th><th>Total Invoices</th><th>Total Sales</th><th>Status</th></tr></thead><tbody>';
                (data.supplier_man_sales || []).forEach(item => {
                    html += `<tr><td>${item.employee_id}</td><td>${item.supplier_man_name}</td><td>${parseFloat(item.total_invoices || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_sales || 0).toLocaleString()}</td><td>${item.status}</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            // Product Sales
            if (reportType === 'all' || reportType === 'product') {
                html += '<table><caption>Product Wise Sales</caption><thead><tr><th>Product ID</th><th>Product Name</th><th>Category</th><th>Quantity Sold</th><th>Revenue</th></tr></thead><tbody>';
                (data.product_sales || []).forEach(item => {
                    const indent = (item.parent_row_id || item.parent_product_id) ? 'padding-left: 20px;' : '';
                    const prefix = (item.parent_row_id || item.parent_product_id) ? '→ ' : '';
                    html += `<tr><td>${item.product_id_display}</td><td style="${indent}">${prefix}${item.product_name}</td><td>${item.category}</td><td>${parseFloat(item.quantity_sold || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.revenue || 0).toLocaleString()}</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            // Branch Sales
            if (reportType === 'all' || reportType === 'branch') {
                html += '<table><caption>Branch Wise Sales</caption><thead><tr><th>Branch ID</th><th>Branch Name</th><th>Total Sales</th><th>Transactions</th><th>Avg Sale</th></tr></thead><tbody>';
                (data.branch_sales || []).forEach(item => {
                    html += `<tr><td>${item.branch_id_display}</td><td>${item.branch_name}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_sales || 0).toLocaleString()}</td><td>${parseFloat(item.transactions || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.avg_sale || 0).toLocaleString()}</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            // Category Sales
            if (reportType === 'all' || reportType === 'category') {
                const total = (data.categories || []).reduce((sum, item) => sum + parseFloat(item.total || 0), 0);
                html += '<table><caption>Category Wise Sales</caption><thead><tr><th>Category</th><th>Total Revenue</th><th>Percentage</th></tr></thead><tbody>';
                (data.categories || []).forEach(item => {
                    const percentage = total > 0 ? ((parseFloat(item.total || 0) / total) * 100).toFixed(1) : 0;
                    html += `<tr><td>${item.category}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total || 0).toLocaleString()}</td><td>${percentage}%</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            // Territory Sales
            if (reportType === 'all' || reportType === 'territory') {
                html += '<table><caption>Territory Wise Sales</caption><thead><tr><th>Country</th><th>Region</th><th>City</th><th>Zone</th><th>Area</th><th>Total Invoices</th><th>Total Sales</th></tr></thead><tbody>';
                (data.territory_sales || []).forEach(item => {
                    html += `<tr><td>${item.country}</td><td>${item.region}</td><td>${item.city}</td><td>${item.zone}</td><td>${item.area}</td><td>${parseFloat(item.total_invoices || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_sales || 0).toLocaleString()}</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            // Invoice Sales
            if (reportType === 'all' || reportType === 'invoice') {
                html += '<table><caption>Invoice Wise Sales</caption><thead><tr><th>Invoice #</th><th>Date</th><th>Customer</th><th>Sales Officer</th><th>Total Bill</th><th>Discount</th><th>Net Amount</th></tr></thead><tbody>';
                (data.invoice_sales || []).forEach(item => {
                    html += `<tr><td>${item.bill_no}</td><td>${item.sale_date}</td><td>${item.customer_name}</td><td>${item.officer_name}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_bill || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_discount_amount || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.net_amount || 0).toLocaleString()}</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            // Vendor Sales
            if (reportType === 'all' || reportType === 'vendor') {
                html += '<table><caption>Vendor Wise Sales</caption><thead><tr><th>Vendor ID</th><th>Vendor Name</th><th>Total Invoices</th><th>Total Quantity</th><th>Total Sales</th></tr></thead><tbody>';
                (data.vendor_sales || []).forEach(item => {
                    html += `<tr><td>${item.vendor_id_display}</td><td>${item.vendor_name}</td><td>${parseFloat(item.total_invoices || 0).toLocaleString()}</td><td>${parseFloat(item.total_quantity || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_sales || 0).toLocaleString()}</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            // Customer Sales
            if (reportType === 'all' || reportType === 'customer') {
                html += '<table><caption>Customer Wise Sales</caption><thead><tr><th>Customer ID</th><th>Customer Name</th><th>Total Invoices</th><th>Total Sales</th></tr></thead><tbody>';
                (data.customer_sales || []).forEach(item => {
                    html += `<tr><td>${item.customer_id_display}</td><td>${item.customer_name}</td><td>${parseFloat(item.total_invoices || 0).toLocaleString()}</td><td>${CURRENCY_SYMBOL}${parseFloat(item.total_sales || 0).toLocaleString()}</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            container.innerHTML = html;
        }
        
        loadReportData();
    </script>
</body>
</html>
