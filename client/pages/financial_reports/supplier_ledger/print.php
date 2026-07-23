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
require_once '../../../../server/api/financial_reports/supplier_ledger/currency-converter.php';

$stmt = $pdo->prepare("SELECT c.symbol FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_base_currency = 1");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];

$target_currency_id = $_GET['currency_id'] ?? null;
if ($target_currency_id) {
    $stmt = $pdo->prepare("SELECT c.symbol FROM ledgerone_public.currencies c WHERE c.id = ?");
    $stmt->execute([$target_currency_id]);
    $currency = $stmt->fetch();
    if ($currency) {
        $currency_symbol = $currency['symbol'];
    }
}

$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$user_name = $user['full_name'] ?? 'System User';

$supplier_id = $_GET['supplier_id'] ?? null;
$date_from = $_GET['date_from'] ?? null;
$date_to = $_GET['date_to'] ?? null;
$ledger_type = $_GET['ledger_type'] ?? 'summary';
$expanded = $_GET['expanded'] ?? '';
$expandedList = $expanded ? explode(',', $expanded) : [];
$target_currency_id = $_GET['currency_id'] ?? null;
$company_id = $_GET['company_id'] ?? null;

// Fetch company info based on selected company_id
if ($company_id) {
    $stmt = $pdo->prepare("SELECT company_name, address, phone, email, logo_url, timezone FROM companies WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$company_id, $_SESSION['tenant_id']]);
    $company = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT company_name, address, phone, email, logo_url, timezone FROM companies WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['tenant_id']]);
    $company = $stmt->fetch();
}

$company_name = $company['company_name'] ?? 'LedgerOne ERP';
$company_address = $company['address'] ?? '';
$company_phone = $company['phone'] ?? '';
$company_email = $company['email'] ?? '';
$company_logo = $company['logo_url'] ?? '';
$company_timezone = $company['timezone'] ?? 'UTC';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Ledger Report - Print</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .company-name { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        .company-logo { max-height: 80px; margin-bottom: 10px; }
        .report-title { font-size: 18px; margin-bottom: 20px; margin-top: 20px; }
        .report-info { margin-bottom: 20px; }
        .report-info div { margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .text-right { text-align: right; }
        .totals-row { font-weight: bold; background-color: #f5f5f5; }
        .balance-positive { color: #2fbf71; }
        .balance-negative { color: #e34f4f; }
        .pdc-pending { color: #e8b23f; font-style: italic; }
        .print-btn { margin-bottom: 20px; padding: 10px 20px; background: #1f7bff; color: white; border: none; cursor: pointer; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
        .signatures { display: flex; justify-content: space-between; margin-top: 50px; margin-bottom: 30px; }
        .signature-box { width: 45%; text-align: center; }
        .signature-line { border-bottom: 1px solid #000; margin-bottom: 5px; height: 40px; }
    </style>
</head>
<body>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <button class="print-btn no-print" onclick="window.print()">Print Report</button>
    <button class="print-btn no-print" style="background:#28a745;" onclick="downloadAsPdf()">Download PDF</button>
    
    <div class="header">
        <?php if ($company_logo): ?><img src="../../../assets/uploads/company_logo/<?php echo $company_logo; ?>" alt="Company Logo" class="company-logo"><?php endif; ?>
        <div class="company-name"><?php echo $company_name; ?></div>
        <?php if ($company_address): ?><div><?php echo $company_address; ?></div><?php endif; ?>
        <?php if ($company_phone): ?><div>Phone: <?php echo $company_phone; ?></div><?php endif; ?>
        <?php if ($company_email): ?><div>Email: <?php echo $company_email; ?></div><?php endif; ?>
        <div class="report-title">Supplier Ledger Report (<?php echo ucfirst($ledger_type); ?>)</div>
    </div>
    
    <div class="report-info">
        <div><strong>Generated On:</strong> <?php if ($company_timezone) { date_default_timezone_set($company_timezone); } echo date('Y-m-d h:i:s A'); ?></div>
        <div><strong>Generated By:</strong> <?php echo $user_name; ?></div>
        <?php if ($date_from && $date_to): ?>
        <div><strong>Period:</strong> <?php echo $date_from; ?> to <?php echo $date_to; ?></div>
        <?php endif; ?>
    </div>

    <div id="report-content"></div>
    
    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line"></div>
            <p><strong>Company Representative</strong></p>
            <p>Signature</p>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <p><strong>Supplier Acknowledgment</strong></p>
            <p>Signature</p>
        </div>
    </div>
    
    <div class="footer">
        <p><i>This is a System Generated Ledger Report</i></p>
    </div>

    <script>
        function downloadAsPdf() {
            const ledgerType = '<?php echo $ledger_type; ?>';
            html2pdf().set({
                margin: 0.3,
                filename: `supplier-ledger-${ledgerType}-<?php echo date('Y-m-d'); ?>.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            }).from(document.body).save();
        }

        const isPdfMode = new URLSearchParams(window.location.search).get('pdf') === '1';
        if (isPdfMode) {
            window.addEventListener('invoiceLoaded', function() {
                setTimeout(() => {
                    html2pdf().set({
                        margin: 0.3,
                        filename: `supplier-ledger-<?php echo $ledger_type; ?>-<?php echo date('Y-m-d'); ?>.pdf`,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2, useCORS: true },
                        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
                    }).from(document.body).save().then(() => window.close());
                }, 500);
            });
        }
    </script>
    <script type="module">
        const currencySymbol = '<?php echo $currency_symbol; ?>';
        const expandedList = <?php echo json_encode($expandedList); ?>;
        const params = new URLSearchParams({
            type: '<?php echo $ledger_type; ?>',
            <?php if ($supplier_id): ?>supplier_id: '<?php echo $supplier_id; ?>',<?php endif; ?>
            <?php if ($date_from): ?>from_date: '<?php echo $date_from; ?>',<?php endif; ?>
            <?php if ($date_to): ?>to_date: '<?php echo $date_to; ?>',<?php endif; ?>
            <?php if ($company_id): ?>company_id: '<?php echo $company_id; ?>',<?php endif; ?>
            <?php if ($target_currency_id): ?>currency_id: '<?php echo $target_currency_id; ?>'<?php endif; ?>
        });

        function numberToWords(num) {
            const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
            const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
            const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            const thousands = ['', 'Thousand', 'Million', 'Billion'];
            
            if (num === 0) return 'Zero';
            
            const parts = num.toFixed(2).split('.');
            const wholePart = parseInt(parts[0]);
            const decimalPart = parseInt(parts[1]);
            
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
            
            let result = '';
            let tempNum = wholePart;
            let thousandIndex = 0;
            
            if (tempNum === 0) {
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
            
            if (decimalPart > 0) {
                result += ' and ' + convertHundreds(decimalPart) + 'Cents';
            }
            
            return result.trim();
        }

        (async () => {
            const response = await fetch(`../../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?${params}`);
            const result = await response.json();
            if (result.success) {
                if ('<?php echo $ledger_type; ?>' === 'detailed') {
                    await renderDetailedReport(result.data, result.sub_accounts, result.opening_balance);
                } else {
                    renderSummaryReport(result.data);
                }
            }
        })();

        function renderSummaryReport(data) {
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Supplier Name</th>
                            <th>Opening Balance</th>
                            <th>Debit (Dr)</th>
                            <th>Credit (Cr)</th>
                            <th>Closing Balance</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            let totalOpening = 0, totalDebit = 0, totalCredit = 0, totalClosing = 0;
            
            data.forEach(row => {
                const opening = parseFloat(row.opening_balance) || 0;
                const debit = parseFloat(row.total_debit) || 0;
                const credit = parseFloat(row.total_credit) || 0;
                const closing = parseFloat(row.closing_balance) || 0;
                
                totalOpening += opening;
                totalDebit += debit;
                totalCredit += credit;
                totalClosing += closing;
                
                html += `
                    <tr>
                        <td>${row.supplier_name}</td>
                        <td class="${opening >= 0 ? 'balance-positive' : 'balance-negative'}">${currencySymbol}${Math.abs(opening).toFixed(2)} ${opening >= 0 ? 'Dr' : 'Cr'}</td>
                        <td>${currencySymbol}${debit.toFixed(2)}</td>
                        <td>${currencySymbol}${credit.toFixed(2)}</td>
                        <td class="${closing >= 0 ? 'balance-positive' : 'balance-negative'}">${currencySymbol}${Math.abs(closing).toFixed(2)} ${closing >= 0 ? 'Dr' : 'Cr'}</td>
                    </tr>
                `;
            });
            
            html += `
                    <tr class="totals-row">
                        <td><strong>Totals</strong></td>
                        <td><strong>${currencySymbol}${Math.abs(totalOpening).toFixed(2)} ${totalOpening >= 0 ? 'Dr' : 'Cr'}</strong></td>
                        <td><strong>${currencySymbol}${totalDebit.toFixed(2)}</strong></td>
                        <td><strong>${currencySymbol}${totalCredit.toFixed(2)}</strong></td>
                        <td><strong>${currencySymbol}${Math.abs(totalClosing).toFixed(2)} ${totalClosing >= 0 ? 'Dr' : 'Cr'}</strong></td>
                    </tr>
                </tbody>
            </table>
            <p><strong>Amount in Words:</strong> ${numberToWords(Math.abs(totalClosing))} ${totalClosing >= 0 ? 'Debit' : 'Credit'} Only</p>
            `;
            
            document.getElementById('report-content').innerHTML = html;
            window.dispatchEvent(new Event('invoiceLoaded'));
        }

        async function fetchAndRenderItems(type, id) {
            try {
                const response = await fetch(`../../../../server/api/financial_reports/supplier_ledger/get-items.php?type=${type}&id=${id}`);
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    let itemsHtml = '';
                    result.data.forEach(item => {
                        itemsHtml += `
                            <tr style="background: #f9fafb; font-size: 12px;">
                                <td></td>
                                <td style="padding-left: 30px;">→ ${item.product_name} (${item.quantity} ${item.uom_name})</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        `;
                    });
                    return itemsHtml;
                }
            } catch (error) {
                console.error('Error loading items:', error);
            }
            return '';
        }

        async function renderDetailedReport(groupedData, subAccounts, openingBalance) {
            const openingLabel = '<?php echo $date_from; ?>' ? 'Soft Opening Balance' : 'Opening Balance';
            
            // Get supplier info if available
            <?php if ($supplier_id): ?>
            fetch(`../../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?type=suppliers`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        const supplier = result.data.find(s => s.id == '<?php echo $supplier_id; ?>');
                        if (supplier) {
                            document.getElementById('report-content').innerHTML = `
                                <div style="margin-bottom: 20px; padding: 10px; background: #f5f5f5; border-radius: 5px;">
                                    <strong>Supplier:</strong> ${supplier.supplier_code} - ${supplier.supplier_name}
                                </div>
                            ` + document.getElementById('report-content').innerHTML;
                        }
                    }
                });
            <?php endif; ?>
            
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Debit (Dr)</th>
                            <th>Credit (Cr)</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>${'<?php echo $date_from; ?>' || 'Opening Balance'}</td>
                            <td>${openingLabel}</td>
                            <td>OB001</td>
                            <td></td>
                            <td></td>
                            <td class="${openingBalance >= 0 ? 'balance-positive' : 'balance-negative'}">${currencySymbol}${Math.abs(openingBalance).toFixed(2)} ${openingBalance >= 0 ? 'Dr' : 'Cr'}</td>
                        </tr>
            `;
            
            let totalDebit = 0, totalCredit = 0;
            
            // Render transactions without sub account first
            if (groupedData[null] || groupedData[''] || groupedData[undefined]) {
                const transactions = groupedData[null] || groupedData[''] || groupedData[undefined];
                for (const row of transactions) {
                    const debit = parseFloat(row.debit) || 0;
                    const credit = parseFloat(row.credit) || 0;
                    const balance = parseFloat(row.running_balance) || 0;
                    const isPDC = row.pdc_status !== undefined;
                    const isNonApprovedPDC = isPDC && row.pdc_status !== 'Approved';
                    
                    if (!isNonApprovedPDC) {
                        totalDebit += debit;
                    }
                    totalCredit += credit;
                    
                    html += `
                        <tr ${isNonApprovedPDC ? 'class="pdc-pending"' : ''}>
                            <td>${row.date}</td>
                            <td>${row.description}</td>
                            <td>${row.reference}</td>
                            <td>${debit > 0 ? currencySymbol + debit.toFixed(2) : ''}</td>
                            <td>${credit > 0 ? currencySymbol + credit.toFixed(2) : ''}</td>
                            <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${currencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</td>
                        </tr>
                    `;
                    
                    if (row.invoice_id || row.return_id) {
                        const itemType = row.invoice_id ? 'invoice' : 'return';
                        const itemId = row.invoice_id || row.return_id;
                        const key = `${itemType}_${itemId}`;
                        if (expandedList.includes(key)) {
                            html += await fetchAndRenderItems(itemType, itemId);
                        }
                    }
                }
            }
            
            // Render sub accounts and their transactions
            for (const subAccount of subAccounts) {
                const subAccountOpening = (parseFloat(subAccount.debit) || 0) - (parseFloat(subAccount.credit) || 0);
                const subAccountTransactions = groupedData[subAccount.id];
                
                html += `
                    <tr style="background: #e3f2fd; font-weight: 600;">
                        <td colspan="6"><strong>Sub Account: ${subAccount.sub_account_name}</strong></td>
                    </tr>
                `;
                
                if (subAccountOpening !== 0) {
                    html += `
                        <tr>
                            <td>${'<?php echo $date_from; ?>' || 'Opening Balance'}</td>
                            <td>${openingLabel}</td>
                            <td>OB-SUB</td>
                            <td></td>
                            <td></td>
                            <td class="${subAccountOpening >= 0 ? 'balance-positive' : 'balance-negative'}">${currencySymbol}${Math.abs(subAccountOpening).toFixed(2)} ${subAccountOpening >= 0 ? 'Dr' : 'Cr'}</td>
                        </tr>
                    `;
                }
                
                if (subAccountTransactions && subAccountTransactions.length > 0) {
                    
                    for (const row of subAccountTransactions) {
                        const debit = parseFloat(row.debit) || 0;
                        const credit = parseFloat(row.credit) || 0;
                        const balance = parseFloat(row.running_balance) || 0;
                        const isPDC = row.pdc_status !== undefined;
                        const isNonApprovedPDC = isPDC && row.pdc_status !== 'Approved';
                        
                        if (!isNonApprovedPDC) {
                            totalDebit += debit;
                        }
                        totalCredit += credit;
                        
                        html += `
                            <tr ${isNonApprovedPDC ? 'class="pdc-pending"' : ''}>
                                <td>${row.date}</td>
                                <td>${row.description}</td>
                                <td>${row.reference}</td>
                                <td>${debit > 0 ? currencySymbol + debit.toFixed(2) : ''}</td>
                                <td>${credit > 0 ? currencySymbol + credit.toFixed(2) : ''}</td>
                                <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${currencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</td>
                            </tr>
                        `;
                        
                        if (row.invoice_id || row.return_id) {
                            const itemType = row.invoice_id ? 'invoice' : 'return';
                            const itemId = row.invoice_id || row.return_id;
                            const key = `${itemType}_${itemId}`;
                            if (expandedList.includes(key)) {
                                html += await fetchAndRenderItems(itemType, itemId);
                            }
                        }
                    }
                }
            }
            
            const finalBalance = openingBalance - totalCredit + totalDebit;
            html += `
                    <tr class="totals-row">
                        <td><strong>Totals</strong></td>
                        <td></td>
                        <td></td>
                        <td><strong>${currencySymbol}${totalDebit.toFixed(2)}</strong></td>
                        <td><strong>${currencySymbol}${totalCredit.toFixed(2)}</strong></td>
                        <td><strong>${currencySymbol}${Math.abs(finalBalance).toFixed(2)} ${finalBalance >= 0 ? 'Dr' : 'Cr'}</strong></td>
                    </tr>
                </tbody>
            </table>
            <p><strong>Amount in Words:</strong> ${numberToWords(Math.abs(finalBalance))} ${finalBalance >= 0 ? 'Debit' : 'Credit'} Only</p>
            `;
            
            document.getElementById('report-content').innerHTML = html;
            window.dispatchEvent(new Event('invoiceLoaded'));
        }
    </script>
</body>
</html>