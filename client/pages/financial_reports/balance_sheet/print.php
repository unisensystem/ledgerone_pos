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

try {
    $stmt = $pdo->prepare("
        SELECT c.symbol 
        FROM tenant_currencies tc 
        JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
        WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
    ");
    $stmt->execute([$_SESSION['tenant_id']]);
    $currency = $stmt->fetch();
    $currency_symbol = $currency['symbol'] ?? '$';

    // Get company info
    $stmt = $pdo->prepare("SELECT company_name, legal_name, email, phone, address, city, state, country, zipcode FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$_SESSION['tenant_id']]);
    $company = $stmt->fetch();
    
    if (!$company) {
        $company = ['company_name' => 'Company Name', 'address' => '', 'phone' => '', 'email' => ''];
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    $currency_symbol = '$';
    $company = ['company_name' => 'Company Name', 'address' => '', 'phone' => '', 'email' => ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Sheet - Print</title>
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
            padding-bottom: 15px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .company-details {
            font-size: 12px;
            color: #555;
            margin-bottom: 10px;
        }
        
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .report-date {
            font-size: 14px;
            color: #555;
        }
        
        .balance-sheet-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .column {
            border: 1px solid #ddd;
        }
        
        .column-header {
            background-color: #f5f5f5;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
            border-bottom: 2px solid #000;
        }
        
        .section {
            padding: 10px;
        }
        
        .section-title {
            font-weight: bold;
            font-size: 14px;
            margin: 10px 0 5px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #ddd;
        }
        
        .account-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 13px;
        }
        
        .account-total {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-weight: bold;
            border-top: 2px solid #000;
            margin-top: 10px;
        }
        
        .account-grand-total {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            font-weight: bold;
            font-size: 14px;
            background-color: #f5f5f5;
            border-top: 3px double #000;
            margin-top: 10px;
        }
        
        .amount {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        
        .print-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 11px;
            color: #777;
            text-align: center;
        }
        
        .no-print {
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            margin-right: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background-color: #1f7bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .balance-sheet-grid {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
        <button class="btn btn-secondary" onclick="window.close()">
            <i class="fas fa-times"></i> Close
        </button>
    </div>

    <div class="print-header">
        <div class="company-name"><?php echo htmlspecialchars($company['name'] ?? 'Company Name'); ?></div>
        <div class="company-details">
            <?php if ($company['address']) echo htmlspecialchars($company['address']) . ' | '; ?>
            <?php if ($company['phone']) echo 'Tel: ' . htmlspecialchars($company['phone']) . ' | '; ?>
            <?php if ($company['email']) echo 'Email: ' . htmlspecialchars($company['email']); ?>
        </div>
        <div class="report-title">Balance Sheet</div>
        <div class="report-date">As of <span id="report-date"><?php echo date('F j, Y'); ?></span></div>
    </div>

    <div class="balance-sheet-grid">
        <div class="column">
            <div class="column-header">Assets</div>
            <div class="section" id="assets-section">
                <!-- Assets will be populated by JavaScript -->
            </div>
            <div class="account-grand-total">
                <div>TOTAL ASSETS</div>
                <div class="amount" id="total-assets">0.00</div>
            </div>
        </div>

        <div class="column">
            <div class="column-header">Liabilities & Equity</div>
            <div class="section" id="liabilities-section">
                <!-- Liabilities will be populated by JavaScript -->
            </div>
            <div class="section" id="equity-section">
                <div class="section-title">Equity</div>
                <!-- Equity will be populated by JavaScript -->
            </div>
            <div class="account-grand-total">
                <div>TOTAL LIABILITIES & EQUITY</div>
                <div class="amount" id="total-liabilities-equity">0.00</div>
            </div>
        </div>
    </div>

    <div class="print-footer">
        Printed on <?php echo date('F j, Y \a\t g:i A'); ?> | LedgerOne ERP
    </div>

    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
        
        function formatCurrency(value) {
            return CURRENCY_SYMBOL + new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value);
        }

        // Load balance sheet data
        fetch('../../../../server/api/financial_reports/balance_sheet/balance-sheet.php')
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    renderBalanceSheet(result.data);
                }
            });

        function renderBalanceSheet(data) {
            const assetsSection = document.getElementById('assets-section');
            const liabilitiesSection = document.getElementById('liabilities-section');
            const equitySection = document.getElementById('equity-section');
            
            let totalAssets = 0;
            let totalLiabilities = 0;
            let totalEquity = 0;

            // Render Assets
            if (data['1']) {
                for (const [subId, subData] of Object.entries(data['1'])) {
                    const sectionDiv = document.createElement('div');
                    sectionDiv.innerHTML = `<div class="section-title">${subData.name}</div>`;
                    
                    subData.accounts.forEach(account => {
                        totalAssets += parseFloat(account.balance);
                        sectionDiv.innerHTML += `
                            <div class="account-row">
                                <div>${account.name}</div>
                                <div class="amount">${formatCurrency(account.balance)}</div>
                            </div>
                        `;
                    });
                    
                    assetsSection.appendChild(sectionDiv);
                }
            }

            // Render Liabilities
            if (data['2']) {
                for (const [subId, subData] of Object.entries(data['2'])) {
                    const sectionDiv = document.createElement('div');
                    sectionDiv.innerHTML = `<div class="section-title">${subData.name}</div>`;
                    
                    subData.accounts.forEach(account => {
                        totalLiabilities += parseFloat(account.balance);
                        sectionDiv.innerHTML += `
                            <div class="account-row">
                                <div>${account.name}</div>
                                <div class="amount">${formatCurrency(account.balance)}</div>
                            </div>
                        `;
                    });
                    
                    liabilitiesSection.appendChild(sectionDiv);
                }
                
                liabilitiesSection.innerHTML += `
                    <div class="account-total">
                        <div>TOTAL LIABILITIES</div>
                        <div class="amount">${formatCurrency(totalLiabilities)}</div>
                    </div>
                `;
            }

            // Render Equity
            if (data['3']) {
                for (const [subId, subData] of Object.entries(data['3'])) {
                    subData.accounts.forEach(account => {
                        totalEquity += parseFloat(account.balance);
                        equitySection.innerHTML += `
                            <div class="account-row">
                                <div>${account.name}</div>
                                <div class="amount">${formatCurrency(account.balance)}</div>
                            </div>
                        `;
                    });
                }
                
                equitySection.innerHTML += `
                    <div class="account-total">
                        <div>TOTAL EQUITY</div>
                        <div class="amount">${formatCurrency(totalEquity)}</div>
                    </div>
                `;
            }

            // Update totals
            document.getElementById('total-assets').textContent = formatCurrency(totalAssets);
            document.getElementById('total-liabilities-equity').textContent = formatCurrency(totalLiabilities + totalEquity);
        }
    </script>
</body>
</html>
