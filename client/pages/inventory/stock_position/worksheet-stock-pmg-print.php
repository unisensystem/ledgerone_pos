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
$from_date = $_GET['from_date'] ?? null;
$to_date = $_GET['to_date'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Petrol Tank Stock Report - Print View</title>
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
        
        .print-btn {
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .signature-section {
            margin-top: 50px;
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
        }
        
        .type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            margin-right: 5px;
        }
        
        .type-in {
            background-color: rgba(47, 191, 113, 0.2);
            color: #2fbf71;
        }
        
        .type-out {
            background-color: rgba(227, 79, 79, 0.2);
            color: #e34f4f;
        }
        
        .type-bbf {
            background-color: rgba(31, 123, 255, 0.2);
            color: #1f7bff;
        }
        
        .in-amount {
            color: #2fbf71;
            font-weight: 600;
        }
        
        .out-amount {
            color: #e34f4f;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print Report</button>
    
    <div class="header">
        <?php if ($company['logo_url']): ?>
            <img src="../../../assets/uploads/company_logo/<?= htmlspecialchars($company['logo_url']) ?>" alt="Company Logo" style="max-height: 60px; margin-bottom: 10px;">
        <?php endif; ?>
        <div class="company-name"><?= $company['company_name'] ?? 'FuelingSys ERP' ?></div>
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
        <div class="report-title">Petrol Tank Stock Report</div>
        <div class="report-date">Generated on: <?= date('Y-m-d H:i:s') ?></div>
        <div class="report-date">Generated by: <?= $user['full_name'] ?? 'System User' ?></div>
    </div>
    
    <div class="filters">
        <strong>Report Period:</strong>
        <?= $from_date && $to_date ? date('M d, Y', strtotime($from_date)) . ' to ' . date('M d, Y', strtotime($to_date)) : 'All Dates' ?>
    </div>
    
    <table id="reportTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Details (Supplier/Customer)</th>
                <th>IN (Liters)</th>
                <th>OUT (Liters)</th>
                <th>Balance</th>
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
        const PRODUCT_ID = 4;
        
        async function loadPrintData() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                let url = `${API_BASE}?action=ledger&product_id=${PRODUCT_ID}`;
                
                if (urlParams.get('from_date')) url += `&from_date=${urlParams.get('from_date')}`;
                if (urlParams.get('to_date')) url += `&to_date=${urlParams.get('to_date')}`;
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    updatePrintTable(data);
                }
            } catch (error) {
                console.error('Error loading print data:', error);
            }
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }
        
        function updatePrintTable(result) {
            const tbody = document.querySelector('#reportTable tbody');
            let rows = [];
            
            // Add BBF row if exists
            if (result.bbf && result.bbf > 0) {
                const urlParams = new URLSearchParams(window.location.search);
                const fromDate = urlParams.get('from_date');
                rows.push(`
                    <tr>
                        <td>${formatDate(fromDate)}</td>
                        <td><span class="type-badge type-bbf">BBF</span>Balance Brought Forward</td>
                        <td>-</td>
                        <td>-</td>
                        <td>${parseFloat(result.bbf).toFixed(2)} L</td>
                    </tr>
                `);
            }
            
            // Add transaction rows
            let totalIn = 0;
            let totalOut = 0;
            
            result.data.forEach(entry => {
                const details = entry.party_name ? entry.party_name : entry.transaction_type;
                const isIn = entry.qty_in > 0;
                const badge = isIn ? '<span class="type-badge type-in">IN</span>' : '<span class="type-badge type-out">OUT</span>';
                const inAmount = isIn ? `<span class="in-amount">${parseFloat(entry.qty_in).toFixed(2)} L</span>` : '-';
                const outAmount = !isIn ? `<span class="out-amount">${parseFloat(entry.qty_out).toFixed(2)} L</span>` : '-';
                
                if (isIn) totalIn += parseFloat(entry.qty_in);
                else totalOut += parseFloat(entry.qty_out);
                
                rows.push(`
                    <tr>
                        <td>${formatDate(entry.transaction_date)}</td>
                        <td>${badge}${details}</td>
                        <td>${inAmount}</td>
                        <td>${outAmount}</td>
                        <td>${parseFloat(entry.balance).toFixed(2)} L</td>
                    </tr>
                `);
            });
            
            // Add totals row
            const finalBalance = result.data.length > 0 ? parseFloat(result.data[result.data.length - 1].balance) : (result.bbf || 0);
            rows.push(`
                <tr class="totals-row">
                    <td colspan="2">Total (${result.data.length} transactions)</td>
                    <td>${totalIn.toFixed(2)} L</td>
                    <td>${totalOut.toFixed(2)} L</td>
                    <td>${finalBalance.toFixed(2)} L</td>
                </tr>
            `);
            
            tbody.innerHTML = rows.join('');
        }
        
        document.addEventListener('DOMContentLoaded', loadPrintData);
    </script>
</body>
</html>
