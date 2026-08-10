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

$company_id = $_GET['company_id'] ?? null;

// Get company data
if ($company_id) {
    $stmt = $pdo->prepare("SELECT company_name, address, phone, email FROM companies WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$company_id, $_SESSION['tenant_id']]);
    $company = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT company_name, address, phone, email FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$_SESSION['tenant_id']]);
    $company = $stmt->fetch();
}

// Get user full name
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$user_name = $user['full_name'] ?? 'System User';

// Get currency symbol
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Flow Report - Print View</title>
    <style>
        @media print {
            @page { margin: 0.5in; }
            body { -webkit-print-color-adjust: exact; }
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .report-info {
            font-size: 11px;
            color: #666;
        }
        
        .balances {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
        
        .balance-item {
            text-align: center;
        }
        
        .balance-label {
            font-size: 10px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .balance-value {
            font-size: 14px;
            font-weight: bold;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }
        
        .text-right { text-align: right; }
        .positive { color: #2fbf71; font-weight: bold; }
        .negative { color: #e34f4f; font-weight: bold; }
        
        .totals-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        
        .no-print {
            display: block;
            text-align: center;
            margin: 20px 0;
        }
        
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; margin-right: 10px;">Print Report</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px;">Close</button>
    </div>

    <div class="header">
        <div class="company-name"><?php echo htmlspecialchars($company['company_name'] ?? 'FuelingSys ERP'); ?></div>
        <?php if ($company['address']): ?>
            <div style="font-size: 12px; margin-bottom: 5px;"><?php echo htmlspecialchars($company['address']); ?></div>
        <?php endif; ?>
        <?php if ($company['phone'] || $company['email']): ?>
            <div style="font-size: 11px; margin-bottom: 10px;">
                <?php if ($company['phone']): ?>Phone: <?php echo htmlspecialchars($company['phone']); ?><?php endif; ?>
                <?php if ($company['phone'] && $company['email']): ?> | <?php endif; ?>
                <?php if ($company['email']): ?>Email: <?php echo htmlspecialchars($company['email']); ?><?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="report-title">Cash Flow Report</div>
        <div class="report-info">
            Generated on: <?php echo date('F j, Y \a\t g:i A'); ?><br>
            Generated by: <?php echo htmlspecialchars($user_name); ?><br>
            Report Period: <span id="reportPeriod">This Month</span>
        </div>
    </div>

    <div class="balances">
        <div class="balance-item">
            <div class="balance-label">Opening Balance</div>
            <div class="balance-value" id="openingBalance"><?php echo $currency_symbol; ?> 0.00</div>
        </div>
        <div class="balance-item">
            <div class="balance-label">Total Inflow</div>
            <div class="balance-value" id="totalInflowBalance"><?php echo $currency_symbol; ?> 0.00</div>
        </div>
        <div class="balance-item">
            <div class="balance-label">Total Outflow</div>
            <div class="balance-value" id="totalOutflowBalance"><?php echo $currency_symbol; ?> 0.00</div>
        </div>
        <div class="balance-item">
            <div class="balance-label">Closing Balance</div>
            <div class="balance-value" id="closingBalance"><?php echo $currency_symbol; ?> 0.00</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Account</th>
                <th>Method</th>
                <th>Bank Account</th>
                <th>Inflow</th>
                <th>Outflow</th>
                <th>Cash Balance</th>
                <th>Bank Balance</th>
            </tr>
        </thead>
        <tbody id="tableBody">
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td colspan="5"><strong>TOTALS</strong></td>
                <td class="text-right positive" id="totalInflow"><strong><?php echo $currency_symbol; ?> 0.00</strong></td>
                <td class="text-right negative" id="totalOutflow"><strong><?php echo $currency_symbol; ?> 0.00</strong></td>
                <td class="text-right" id="finalCashBalance"><strong><?php echo $currency_symbol; ?> 0.00</strong></td>
                <td class="text-right" id="finalBankBalance"><strong><?php echo $currency_symbol; ?> 0.00</strong></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>This report was generated by LedgerOne ERP System</p>
    </div>

    <script>
        let currencySymbol = '<?php echo $currency_symbol; ?>';
        
        function formatCurrency(amount) {
            return currencySymbol + ' ' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        async function loadData() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const params = new URLSearchParams({
                    date_range: urlParams.get('date_range') || 'month',
                    from_date: urlParams.get('from_date') || '',
                    to_date: urlParams.get('to_date') || '',
                    description: urlParams.get('description') || '',
                    account: urlParams.get('account') || '',
                    method: urlParams.get('method') || 'all',
                    bank_account: urlParams.get('bank_account') || 'all',
                    type: urlParams.get('type') || 'all',
                    page: 1,
                    limit: 1000
                });
                
                if (urlParams.get('company_id')) {
                    params.append('company_id', urlParams.get('company_id'));
                }
                
                const response = await fetch(`../../../../server/api/financial_reports/cash_flow/cash-flow.php?${params}`);
                const result = await response.json();
                
                if (result.success) {
                    populateTable(result.data);
                    updateBalances(result.balances);
                    updateTotals(result.totals);
                    updateReportPeriod(urlParams.get('date_range'));
                }
            } catch (error) {
                console.error('Error loading data:', error);
            }
        }
        
        function populateTable(data) {
            const tableBody = document.getElementById('tableBody');
            tableBody.innerHTML = '';
            
            data.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${formatDate(item.date)}</td>
                    <td>${item.description}</td>
                    <td>${item.account}</td>
                    <td>${item.method}</td>
                    <td>${item.bank_account}</td>
                    <td class="text-right ${item.inflow > 0 ? 'positive' : ''}">
                        ${item.inflow > 0 ? formatCurrency(item.inflow) : '-'}
                    </td>
                    <td class="text-right ${item.outflow > 0 ? 'negative' : ''}">
                        ${item.outflow > 0 ? formatCurrency(item.outflow) : '-'}
                    </td>
                    <td class="text-right">${formatCurrency(item.cash_balance)}</td>
                    <td class="text-right">${formatCurrency(item.bank_balance)}</td>
                `;
                tableBody.appendChild(row);
            });
        }
        
        function updateBalances(balances) {
            if (balances) {
                document.getElementById('openingBalance').textContent = formatCurrency(balances.opening_balance);
                document.getElementById('totalInflowBalance').textContent = formatCurrency(balances.total_inflow);
                document.getElementById('totalOutflowBalance').textContent = formatCurrency(balances.total_outflow);
                document.getElementById('closingBalance').textContent = formatCurrency(balances.closing_balance);
            }
        }
        
        function updateTotals(totals) {
            if (totals) {
                document.getElementById('totalInflow').innerHTML = `<strong>${formatCurrency(totals.inflow)}</strong>`;
                document.getElementById('totalOutflow').innerHTML = `<strong>${formatCurrency(totals.outflow)}</strong>`;
                document.getElementById('finalCashBalance').innerHTML = `<strong>${formatCurrency(totals.cash_balance)}</strong>`;
                document.getElementById('finalBankBalance').innerHTML = `<strong>${formatCurrency(totals.bank_balance)}</strong>`;
            }
        }
        
        function updateReportPeriod(dateRange) {
            const periods = {
                'all': 'All Dates',
                'today': 'Today',
                'week': 'This Week',
                'month': 'This Month',
                'quarter': 'This Quarter',
                'year': 'This Year',
                'custom': 'Custom Range'
            };
            document.getElementById('reportPeriod').textContent = periods[dateRange] || 'This Month';
        }
        
        document.addEventListener('DOMContentLoaded', loadData);
    </script>
</body>
</html>