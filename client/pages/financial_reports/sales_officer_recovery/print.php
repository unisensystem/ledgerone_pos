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

// Get user name
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$user_name = $user['full_name'] ?? 'Unknown User';

// Get company info
$stmt = $pdo->prepare("SELECT company_name, address, phone, email, timezone, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$_SESSION['tenant_id']]);
$company = $stmt->fetch();
$company_name = $company['company_name'] ?? 'LedgerOne ERP';
$company_address = $company['address'] ?? '';
$company_phone = $company['phone'] ?? '';
$company_email = $company['email'] ?? '';
$company_timezone = $company['timezone'] ?? 'UTC';
$company_logo = $company['logo_url'] ?? '';

// Set timezone
date_default_timezone_set($company_timezone);

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$salesOfficerId = $_GET['sales_officer_id'] ?? '';
$customerId = $_GET['customer_id'] ?? '';
$distributionId = $_GET['distribution_id'] ?? '';
$status = $_GET['status'] ?? '';
$includeZeroBalance = $_GET['include_zero_balance'] ?? '0';
$companyId = $_GET['company_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Officer Recovery Report - Print</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            background: #fff;
        }
        
        .print-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .print-header img {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 10px;
        }
        
        .print-header h1 {
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .print-header p {
            font-size: 11px;
            color: #666;
        }
        
        .filter-info {
            margin-bottom: 15px;
            padding: 10px;
            background: #f5f5f5;
            border: 1px solid #ddd;
        }
        
        .filter-info h3 {
            font-size: 13px;
            margin-bottom: 8px;
        }
        
        .filter-info p {
            font-size: 11px;
            margin: 3px 0;
        }
        
        .summary-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .summary-box {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        
        .summary-box .label {
            font-size: 10px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .summary-box .value {
            font-size: 14px;
            font-weight: bold;
        }
        
        .officer-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .officer-header {
            background: #333;
            color: #fff;
            padding: 10px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .officer-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            margin-bottom: 10px;
        }
        
        .officer-stat {
            text-align: center;
        }
        
        .officer-stat .label {
            font-size: 10px;
            color: #666;
        }
        
        .officer-stat .value {
            font-size: 12px;
            font-weight: bold;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table th {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
        }
        
        table td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            font-size: 11px;
        }
        
        table tr:nth-child(even) {
            background: #fafafa;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .badge-overdue {
            background: #fee;
            color: #c00;
        }
        
        .badge-recovered {
            background: #efe;
            color: #0a0;
        }
        
        .badge-pending {
            background: #ffc;
            color: #c90;
        }
        
        .print-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .print-container {
                padding: 10px;
            }
            
            .no-print {
                display: none;
            }
            
            .officer-section {
                page-break-inside: avoid;
            }
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #1f7bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-btn:hover {
            background: #1a6cdc;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    
    <div class="print-container">
        <div class="print-header">
            <?php if ($company_logo): ?>
                <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($company_logo); ?>" alt="Company Logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($company_name); ?></h1>
            <?php if ($company_address): ?>
                <p><?php echo htmlspecialchars($company_address); ?></p>
            <?php endif; ?>
            <?php if ($company_phone || $company_email): ?>
                <p>
                    <?php if ($company_phone): ?>Phone: <?php echo htmlspecialchars($company_phone); ?><?php endif; ?>
                    <?php if ($company_phone && $company_email): ?> | <?php endif; ?>
                    <?php if ($company_email): ?>Email: <?php echo htmlspecialchars($company_email); ?><?php endif; ?>
                </p>
            <?php endif; ?>
            <p style="margin-top: 10px; font-weight: bold;">Sales Officer-wise Recovery Report</p>
            <p><strong>Generated By:</strong> <?php echo htmlspecialchars($user_name); ?> | <strong>Generated On:</strong> <?php echo date('d M Y, h:i A'); ?></p>
        </div>
        
        <div class="filter-info">
            <h3>Report Filters</h3>
            <?php if ($dateFrom): ?>
                <p><strong>Date From:</strong> <?php echo date('d M Y', strtotime($dateFrom)); ?></p>
            <?php endif; ?>
            <?php if ($dateTo): ?>
                <p><strong>Date To:</strong> <?php echo date('d M Y', strtotime($dateTo)); ?></p>
            <?php endif; ?>
            <?php if (!$dateFrom && !$dateTo): ?>
                <p><strong>Date Range:</strong> All Dates</p>
            <?php endif; ?>
        </div>
        
        <div class="summary-section" id="summary-section"></div>
        
        <div id="report-content"></div>
        
        <div class="print-footer">
            <p>LedgerOne ERP • Sales Officer Recovery Report • This is a computer-generated document</p>
        </div>
    </div>

    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
        const API_URL = '../../../../server/api/financial_reports/sales_officer_recovery/sales-officer-recovery.php';
        
        function formatCurrency(amount) {
            return CURRENCY_SYMBOL + ' ' + amount.toLocaleString('en-IN');
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        }
        
        function getStatusBadge(status) {
            switch (status) {
                case 'overdue':
                    return '<span class="badge badge-overdue">Overdue</span>';
                case 'recovered':
                    return '<span class="badge badge-recovered">Recovered</span>';
                case 'partial':
                    return '<span class="badge badge-pending">Partial</span>';
                default:
                    return '<span class="badge">Unknown</span>';
            }
        }
        
        async function fetchAndRenderReport() {
            const params = new URLSearchParams(window.location.search);
            
            try {
                const response = await fetch(`${API_URL}?${params.toString()}`);
                const result = await response.json();
                
                if (result.success) {
                    renderSummary(result.data);
                    renderReport(result.data);
                    
                    // Update company info if company_id is provided
                    const companyId = params.get('company_id');
                    if (companyId) {
                        await updateCompanyInfo(companyId);
                    }
                } else {
                    document.getElementById('report-content').innerHTML = '<p>Error loading report data</p>';
                }
            } catch (error) {
                document.getElementById('report-content').innerHTML = '<p>Failed to load report data</p>';
            }
        }
        
        async function updateCompanyInfo(companyId) {
            try {
                const response = await fetch('../../../../server/api/companies/get-companies.php');
                const result = await response.json();
                
                if (result.success && result.data) {
                    const company = result.data.find(c => c.id == companyId);
                    if (company) {
                        document.querySelector('.print-header h1').textContent = company.company_name;
                    }
                }
            } catch (error) {
                console.error('Error fetching company info:', error);
            }
        }
        
        function renderSummary(data) {
            const totalOfficers = data.length;
            const totalBills = data.reduce((sum, officer) => sum + officer.totalBills, 0);
            const totalRecovery = data.reduce((sum, officer) => sum + officer.totalRecovery, 0);
            const totalRemaining = data.reduce((sum, officer) => sum + officer.totalRemaining, 0);
            
            document.getElementById('summary-section').innerHTML = `
                <div class="summary-box">
                    <div class="label">Total Sales Officers</div>
                    <div class="value">${totalOfficers}</div>
                </div>
                <div class="summary-box">
                    <div class="label">Total Bills</div>
                    <div class="value">${totalBills}</div>
                </div>
                <div class="summary-box">
                    <div class="label">Total Recovered</div>
                    <div class="value">${formatCurrency(totalRecovery)}</div>
                </div>
                <div class="summary-box">
                    <div class="label">Remaining Balance</div>
                    <div class="value">${formatCurrency(totalRemaining)}</div>
                </div>
            `;
        }
        
        function renderReport(data) {
            let html = '';
            let serialNo = 1;
            
            data.forEach(officer => {
                html += `
                    <div class="officer-section">
                        <div class="officer-header">${officer.salesOfficer} - ${officer.totalBills} Bills</div>
                        <div class="officer-stats">
                            <div class="officer-stat">
                                <div class="label">Total Billed</div>
                                <div class="value">${formatCurrency(officer.totalBillAmount)}</div>
                            </div>
                            <div class="officer-stat">
                                <div class="label">Recovered</div>
                                <div class="value">${formatCurrency(officer.totalRecovery)}</div>
                            </div>
                            <div class="officer-stat">
                                <div class="label">Balance</div>
                                <div class="value">${formatCurrency(officer.totalRemaining)}</div>
                            </div>
                            <div class="officer-stat">
                                <div class="label">Recovery %</div>
                                <div class="value">${Math.round((officer.totalRecovery / officer.totalBillAmount) * 100)}%</div>
                            </div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>S#</th>
                                    <th>Bill Date</th>
                                    <th>Overdue</th>
                                    <th>Bill No</th>
                                    <th>Customer</th>
                                    <th class="text-right">Bill Amount</th>
                                    <th class="text-right">Return</th>
                                    <th class="text-right">Recovery</th>
                                    <th class="text-right">Balance</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                officer.transactions.forEach(txn => {
                    html += `
                        <tr>
                            <td>${serialNo++}</td>
                            <td>${formatDate(txn.billDate)}</td>
                            <td>${txn.overdueDays > 0 ? txn.overdueDays + ' days' : '-'}</td>
                            <td>${txn.billNo}</td>
                            <td>${txn.customerName}</td>
                            <td class="text-right">${formatCurrency(txn.billAmount)}</td>
                            <td class="text-right">${formatCurrency(txn.returnAmount)}</td>
                            <td class="text-right">${formatCurrency(txn.totalRecovery)}</td>
                            <td class="text-right">${formatCurrency(txn.remainingBalance)}</td>
                            <td class="text-center">${getStatusBadge(txn.status)}</td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            });
            
            document.getElementById('report-content').innerHTML = html;
        }
        
        fetchAndRenderReport();
    </script>
</body>
</html>
