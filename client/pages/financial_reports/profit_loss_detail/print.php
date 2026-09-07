<?php
session_start();
require_once '../../../../includes/connection.php';

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

// Get company info
$stmt = $pdo->prepare("
    SELECT company_name, legal_name, address, city, state, country, phone, email, logo_url
    FROM companies 
    WHERE tenant_id = ? AND is_active = 1 
    LIMIT 1
");
$stmt->execute([$tenant_id]);
$company = $stmt->fetch();

// Get currency symbol
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$tenant_id]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];

// Get parameters from URL
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$report_type = $_GET['report_type'] ?? 'itemwise';
$data = $_GET['data'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit & Loss Detail Report - Print</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            color: #000;
        }

        .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }

        .company-logo {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 10px;
        }

        .company-logo {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 12px;
            color: #333;
            line-height: 1.6;
        }

        .report-title {
            font-size: 20px;
            font-weight: bold;
            margin: 20px 0 10px 0;
            text-align: center;
        }

        .report-period {
            text-align: center;
            font-size: 14px;
            margin-bottom: 20px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
        }

        th {
            background-color: #f0f0f0;
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
        }

        td {
            padding: 8px 10px;
            border: 1px solid #ddd;
        }

        .text-right {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            background-color: #f5f5f5;
        }

        .positive {
            color: #2fbf71;
        }

        .negative {
            color: #e34f4f;
        }

        .partner-note {
            font-size: 11px;
            color: #555;
            margin-bottom: 10px;
        }

        .print-footer {
            margin-top: 40px;
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

            @page {
                margin: 15mm;
            }
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #1f7bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .print-btn:hover {
            background-color: #1a6cdc;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Report
    </button>

    <div class="print-header">
        <div class="company-name"><?php echo htmlspecialchars($company['company_name']); ?></div>
        
        <div class="company-details">
            <?php if ($company['address']): ?>
                <?php echo htmlspecialchars($company['address']); ?>,
            <?php endif; ?>
            <?php if ($company['city']): ?>
                <?php echo htmlspecialchars($company['city']); ?>,
            <?php endif; ?>
            <?php if ($company['state']): ?>
                <?php echo htmlspecialchars($company['state']); ?>,
            <?php endif; ?>
            <?php if ($company['country']): ?>
                <?php echo htmlspecialchars($company['country']); ?>
            <?php endif; ?>
            <br>
            <?php if ($company['phone']): ?>
                Phone: <?php echo htmlspecialchars($company['phone']); ?>
            <?php endif; ?>
            <?php if ($company['email']): ?>
                | Email: <?php echo htmlspecialchars($company['email']); ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="report-title">
        <?php 
        if ($report_type === 'itemwise') {
            echo 'Item-wise Profit & Loss Report';
        } elseif ($report_type === 'categorywise') {
            echo 'Category-wise Profit & Loss Report';
        } elseif ($report_type === 'customerwise') {
            echo 'Customer-wise Profit & Loss Report';
        } elseif ($report_type === 'supplierwise') {
            echo 'Supplier-wise Profit & Loss Report';
        } elseif ($report_type === 'partnerwise') {
            echo 'Partner-wise Profit Distribution Report';
        } else {
            echo 'Invoice-wise Profit & Loss Report';
        }
        ?>
    </div>

    <div class="report-period">
        Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>
    </div>

    <div id="reportContent"></div>

    <div class="print-footer">
        <p>Generated on <?php echo date('F d, Y h:i A'); ?> | LedgerOne ERP System</p>
    </div>

    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
        const reportType = '<?php echo $report_type; ?>';
        const reportData = <?php echo $data ? $data : '[]'; ?>;

        function formatCurrency(amount) {
            return CURRENCY_SYMBOL + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function renderReport() {
            const container = document.getElementById('reportContent');
            let html = '';

            if (reportType === 'itemwise') {
                html = `
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">Qty Sold</th>
                                <th class="text-right">Revenue</th>
                                <th class="text-right">COGS</th>
                                <th class="text-right">Gross Profit</th>
                                <th class="text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                let totalRevenue = 0, totalCOGS = 0, totalProfit = 0;

                reportData.forEach(item => {
                    const revenue = parseFloat(item.revenue);
                    const cogs = parseFloat(item.cogs);
                    const profit = revenue - cogs;
                    const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;

                    totalRevenue += revenue;
                    totalCOGS += cogs;
                    totalProfit += profit;

                    html += `
                        <tr>
                            <td>${item.product_name}</td>
                            <td class="text-right">${parseFloat(item.qty_sold).toFixed(2)}</td>
                            <td class="text-right">${formatCurrency(revenue)}</td>
                            <td class="text-right">${formatCurrency(cogs)}</td>
                            <td class="text-right ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td>
                            <td class="text-right">${margin}%</td>
                        </tr>
                    `;
                });

                const totalMargin = totalRevenue > 0 ? ((totalProfit / totalRevenue) * 100).toFixed(2) : 0;
                html += `
                        <tr class="total-row">
                            <td><strong>TOTAL</strong></td>
                            <td class="text-right"></td>
                            <td class="text-right">${formatCurrency(totalRevenue)}</td>
                            <td class="text-right">${formatCurrency(totalCOGS)}</td>
                            <td class="text-right ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td>
                            <td class="text-right">${totalMargin}%</td>
                        </tr>
                    </tbody>
                </table>
                `;

            } else if (reportType === 'categorywise') {
                html = `
                    <table>
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-right">Products</th>
                                <th class="text-right">Revenue</th>
                                <th class="text-right">COGS</th>
                                <th class="text-right">Gross Profit</th>
                                <th class="text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                let totalRevenue = 0, totalCOGS = 0, totalProfit = 0;

                reportData.forEach(category => {
                    const revenue = parseFloat(category.revenue);
                    const cogs = parseFloat(category.cogs);
                    const profit = revenue - cogs;
                    const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;

                    totalRevenue += revenue;
                    totalCOGS += cogs;
                    totalProfit += profit;

                    html += `
                        <tr>
                            <td>${category.category_name || 'Uncategorized'}</td>
                            <td class="text-right">${category.product_count}</td>
                            <td class="text-right">${formatCurrency(revenue)}</td>
                            <td class="text-right">${formatCurrency(cogs)}</td>
                            <td class="text-right ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td>
                            <td class="text-right">${margin}%</td>
                        </tr>
                    `;
                });

                const totalMargin = totalRevenue > 0 ? ((totalProfit / totalRevenue) * 100).toFixed(2) : 0;
                html += `
                        <tr class="total-row">
                            <td><strong>TOTAL</strong></td>
                            <td class="text-right"></td>
                            <td class="text-right">${formatCurrency(totalRevenue)}</td>
                            <td class="text-right">${formatCurrency(totalCOGS)}</td>
                            <td class="text-right ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td>
                            <td class="text-right">${totalMargin}%</td>
                        </tr>
                    </tbody>
                </table>
                `;

            } else if (reportType === 'customerwise') {
                html = `
                    <table>
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th class="text-right">Invoices</th>
                                <th class="text-right">Revenue</th>
                                <th class="text-right">COGS</th>
                                <th class="text-right">Gross Profit</th>
                                <th class="text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                let totalRevenue = 0, totalCOGS = 0, totalProfit = 0;

                reportData.forEach(customer => {
                    const revenue = parseFloat(customer.revenue);
                    const cogs = parseFloat(customer.cogs);
                    const profit = revenue - cogs;
                    const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;

                    totalRevenue += revenue;
                    totalCOGS += cogs;
                    totalProfit += profit;

                    html += `
                        <tr>
                            <td>${customer.customer_name}</td>
                            <td class="text-right">${customer.invoice_count}</td>
                            <td class="text-right">${formatCurrency(revenue)}</td>
                            <td class="text-right">${formatCurrency(cogs)}</td>
                            <td class="text-right ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td>
                            <td class="text-right">${margin}%</td>
                        </tr>
                    `;
                });

                const totalMargin = totalRevenue > 0 ? ((totalProfit / totalRevenue) * 100).toFixed(2) : 0;
                html += `
                        <tr class="total-row">
                            <td><strong>TOTAL</strong></td>
                            <td class="text-right"></td>
                            <td class="text-right">${formatCurrency(totalRevenue)}</td>
                            <td class="text-right">${formatCurrency(totalCOGS)}</td>
                            <td class="text-right ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td>
                            <td class="text-right">${totalMargin}%</td>
                        </tr>
                    </tbody>
                </table>
                `;

            } else if (reportType === 'supplierwise') {
                html = `
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Supplier</th>
                                <th class="text-right">Purchases</th>
                                <th class="text-right">Revenue</th>
                                <th class="text-right">COGS</th>
                                <th class="text-right">Gross Profit</th>
                                <th class="text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                let totalRevenue = 0, totalCOGS = 0, totalProfit = 0;

                reportData.forEach(supplier => {
                    const revenue = parseFloat(supplier.revenue);
                    const cogs = parseFloat(supplier.cogs);
                    const profit = revenue - cogs;
                    const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : '—';

                    totalRevenue += revenue;
                    totalCOGS += cogs;
                    totalProfit += profit;

                    html += `
                        <tr>
                            <td>${supplier.supplier_code || '—'}</td>
                            <td>${supplier.supplier_name}</td>
                            <td class="text-right">${supplier.purchase_count}</td>
                            <td class="text-right">${formatCurrency(revenue)}</td>
                            <td class="text-right">${formatCurrency(cogs)}</td>
                            <td class="text-right ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td>
                            <td class="text-right">${margin}${margin !== '—' ? '%' : ''}</td>
                        </tr>
                    `;
                });

                const totalMargin = totalRevenue > 0 ? ((totalProfit / totalRevenue) * 100).toFixed(2) : '0.00';
                html += `
                        <tr class="total-row">
                            <td colspan="2"><strong>TOTAL</strong></td>
                            <td class="text-right"></td>
                            <td class="text-right">${formatCurrency(totalRevenue)}</td>
                            <td class="text-right">${formatCurrency(totalCOGS)}</td>
                            <td class="text-right ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td>
                            <td class="text-right">${totalMargin}%</td>
                        </tr>
                    </tbody>
                </table>
                `;

            } else if (reportType === 'partnerwise') {
                const partners = reportData.partners || [];
                const totalProfit = parseFloat(reportData.totalProfit) || 0;

                html = `
                    <div class="partner-note">
                        Distribution is calculated on the Gross Profit of the selected period (Revenue &minus; COGS).
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Partner (Capital Account)</th>
                                <th class="text-right">Share %</th>
                                <th class="text-right">Profit Share</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                let totalPercentage = 0;
                let totalDistributed = 0;

                partners.forEach(partner => {
                    const percentage = parseFloat(partner.percentage) || 0;
                    const share = totalProfit * (percentage / 100);

                    totalPercentage += percentage;
                    totalDistributed += share;

                    html += `
                        <tr>
                            <td>${partner.account_name}</td>
                            <td class="text-right">${percentage.toFixed(2)}%</td>
                            <td class="text-right ${share >= 0 ? 'positive' : 'negative'}">${formatCurrency(share)}</td>
                        </tr>
                    `;
                });

                const ownerPercentage = 100 - totalPercentage;
                const ownerShare = totalProfit - totalDistributed;

                html += `
                        <tr class="total-row">
                            <td><strong>Remaining / Owner's Share</strong></td>
                            <td class="text-right">${ownerPercentage.toFixed(2)}%</td>
                            <td class="text-right ${ownerShare >= 0 ? 'positive' : 'negative'}">${formatCurrency(ownerShare)}</td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>TOTAL PROFIT</strong></td>
                            <td class="text-right">100.00%</td>
                            <td class="text-right ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td>
                        </tr>
                    </tbody>
                </table>
                `;

            } else if (reportType === 'invoicewise') {
                html = `
                    <table>
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th class="text-right">Revenue</th>
                                <th class="text-right">COGS</th>
                                <th class="text-right">Gross Profit</th>
                                <th class="text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                let totalRevenue = 0, totalCOGS = 0, totalProfit = 0;

                reportData.forEach(invoice => {
                    const revenue = parseFloat(invoice.revenue);
                    const cogs = parseFloat(invoice.cogs);
                    const profit = revenue - cogs;
                    const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;

                    totalRevenue += revenue;
                    totalCOGS += cogs;
                    totalProfit += profit;

                    const invoiceDate = new Date(invoice.sale_date);
                    const dateStr = invoiceDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

                    html += `
                        <tr>
                            <td>${invoice.bill_no}</td>
                            <td>${dateStr}</td>
                            <td>${invoice.customer_name}</td>
                            <td class="text-right">${formatCurrency(revenue)}</td>
                            <td class="text-right">${formatCurrency(cogs)}</td>
                            <td class="text-right ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td>
                            <td class="text-right">${margin}%</td>
                        </tr>
                    `;
                });

                const totalMargin = totalRevenue > 0 ? ((totalProfit / totalRevenue) * 100).toFixed(2) : 0;
                html += `
                        <tr class="total-row">
                            <td colspan="3"><strong>TOTAL</strong></td>
                            <td class="text-right">${formatCurrency(totalRevenue)}</td>
                            <td class="text-right">${formatCurrency(totalCOGS)}</td>
                            <td class="text-right ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td>
                            <td class="text-right">${totalMargin}%</td>
                        </tr>
                    </tbody>
                </table>
                `;
            }

            container.innerHTML = html;
        }

        renderReport();

        // Load and display company logo
        <?php if ($company['logo_url']): ?>
        const logoImg = document.createElement('img');
        logoImg.src = '../../../assets/uploads/company_logo/<?php echo $company['logo_url']; ?>';
        logoImg.alt = 'Company Logo';
        logoImg.className = 'company-logo';
        const printHeader = document.querySelector('.print-header');
        printHeader.insertBefore(logoImg, printHeader.firstChild);
        <?php endif; ?>
    </script>
</body>
</html>