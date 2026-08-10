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
require_once '../../../../includes/encryption.php';
$stmt = $pdo->prepare("SELECT c.symbol FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_base_currency = 1");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];

$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$user_name = $user['full_name'] ?? 'System User';

$customer_id = $_GET['customer_id'] ?? null;
$customer_id_encrypted = $_GET['customer_code'] ?? null;
$customer_id_display = $customer_id_encrypted ? decryptCustomerCode(urldecode($customer_id_encrypted)) : '';
$date_from = $_GET['date_from'] ?? null;
$date_to = $_GET['date_to'] ?? null;
$ledger_type = $_GET['ledger_type'] ?? 'summary';
$distribution_id = $_GET['distribution_id'] ?? null;
$sub_account_id = $_GET['sub_account_id'] ?? null;
$expanded_rows = $_GET['expanded_rows'] ?? '';
$company_id = $_GET['company_id'] ?? null;

// Get company info based on selected company_id
if ($company_id) {
    $stmt = $pdo->prepare("SELECT company_name, address, phone, email, logo_url, timezone FROM companies WHERE tenant_id = ? AND id = ? LIMIT 1");
    $stmt->execute([$_SESSION['tenant_id'], $company_id]);
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
    <title>Customer Ledger Report - Print</title>
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
        .print-btn { margin-bottom: 20px; padding: 10px 20px; background: #1f7bff; color: white; border: none; cursor: pointer; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
        .items-table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 12px; }
        .items-table th { background: #f9f9f9; padding: 6px 8px; text-align: left; font-weight: 600; }
        .items-table td { padding: 6px 8px; }
        .items-row { background: #fafafa; }
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
        <div class="report-title">Customer Ledger Report (<?php echo ucfirst($ledger_type); ?>)</div>
    </div>
    
    <div class="report-info">
        <div><strong>Generated On:</strong> <?php if($company_timezone) date_default_timezone_set($company_timezone); echo date('Y-m-d h:i:s A'); ?></div>
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
            <p><strong>Customer Acknowledgment</strong></p>
            <p>Signature</p>
        </div>
    </div>
    
    <div class="footer">
        <p><i>This is a System Generated Ledger Report</i></p>
    </div>

    <script>
        function downloadAsPdf() {
            const element = document.body;
            const ledgerType = '<?php echo $ledger_type; ?>';
            const filename = `customer-ledger-${ledgerType}-<?php echo date('Y-m-d'); ?>.pdf`;
            html2pdf().set({
                margin: 0.3,
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            }).from(element).save();
        }

        const isPdfMode = new URLSearchParams(window.location.search).get('pdf') === '1';
        if (isPdfMode) {
            window.addEventListener('invoiceLoaded', function() {
                setTimeout(() => {
                    const element = document.body;
                    const ledgerType = '<?php echo $ledger_type; ?>';
                    html2pdf().set({
                        margin: 0.3,
                        filename: `customer-ledger-${ledgerType}-<?php echo date('Y-m-d'); ?>.pdf`,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2, useCORS: true },
                        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
                    }).from(element).save().then(() => window.close());
                }, 500);
            });
        }

        const currencySymbol = '<?php echo $currency_symbol; ?>';
        const expandedRows = '<?php echo $expanded_rows; ?>'.split(',').filter(r => r);
        const params = new URLSearchParams();
        params.append('type', '<?php echo $ledger_type; ?>');
        <?php if ($customer_id): ?>params.append('customer_id', '<?php echo $customer_id; ?>');<?php endif; ?>
        <?php if ($date_from): ?>params.append('from_date', '<?php echo $date_from; ?>');<?php endif; ?>
        <?php if ($date_to): ?>params.append('to_date', '<?php echo $date_to; ?>');<?php endif; ?>
        <?php if ($distribution_id): ?>params.append('distribution_id', '<?php echo $distribution_id; ?>');<?php endif; ?>
        <?php if ($sub_account_id): ?>params.append('sub_account_id', '<?php echo $sub_account_id; ?>');<?php endif; ?>
        <?php if ($company_id): ?>params.append('company_id', '<?php echo $company_id; ?>');<?php endif; ?>

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

        fetch(`../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?${params}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    if ('<?php echo $ledger_type; ?>' === 'detailed') {
                        renderDetailedReport(result.data, result.opening_balance);
                    } else {
                        renderSummaryReport(result.data);
                    }
                }
            });

        function renderSummaryReport(data) {
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Customer Name</th>
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
                        <td>${row.customer_name}</td>
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

        async function renderDetailedReport(data, openingBalance) {
            const openingLabel = '<?php echo $date_from; ?>' ? 'Soft Opening Balance' : 'Opening Balance';
            
            // Get customer info if available
            <?php if ($customer_id): ?>
            try {
                const customerResponse = await fetch(`../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=customers`);
                const customerResult = await customerResponse.json();
                if (customerResult.success) {
                    const customer = customerResult.data.find(c => c.id == '<?php echo $customer_id; ?>');
                    if (customer) {
                        const customerInfo = `
                            <div style="margin-bottom: 20px; padding: 10px; background: #f5f5f5; border-radius: 5px;">
                                <strong>Customer:</strong> ${customer.customer_code} - ${customer.customer_name}
                            </div>
                        `;
                        document.getElementById('report-content').insertAdjacentHTML('afterbegin', customerInfo);
                    }
                }
            } catch (error) {
                console.error('Error loading customer:', error);
            }
            <?php endif; ?>
            
            // Get detailed data
            const detailedUrl = '../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?type=detailed&customer_id=<?php echo $customer_id; ?><?php if ($date_from): ?>&from_date=<?php echo $date_from; ?><?php endif; ?><?php if ($date_to): ?>&to_date=<?php echo $date_to; ?><?php endif; ?><?php if ($distribution_id): ?>&distribution_id=<?php echo $distribution_id; ?><?php endif; ?><?php if ($sub_account_id): ?>&sub_account_id=<?php echo $sub_account_id; ?><?php endif; ?><?php if ($company_id): ?>&company_id=<?php echo $company_id; ?><?php endif; ?>';
            const detailedResponse = await fetch(detailedUrl);
            const result = await detailedResponse.json();
            if (result.success) {
                data = result.data;
                openingBalance = result.opening_balance;
                renderDetailedContent();
            }
            
            function renderDetailedContent() {
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
            `;
            
            let totalDebit = 0, totalCredit = 0;
            let rowIndex = 0;
            
            data.forEach(row => {
                if (row.type === 'opening_balance') {
                    const balance = parseFloat(row.running_balance) || 0;
                    html += `
                        <tr>
                            <td>${row.date}</td>
                            <td>${row.description}</td>
                            <td>${row.reference}</td>
                            <td></td>
                            <td></td>
                            <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${currencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</td>
                        </tr>
                    `;
                } else if (row.type === 'sub_account_header') {
                    html += `
                        <tr style="background: #f5f5f5; font-weight: 600;">
                            <td colspan="6" style="padding: 12px 16px;">
                                ${row.sub_account_name}
                            </td>
                        </tr>
                    `;
                } else if (row.type === 'sub_account_total') {
                    const balance = parseFloat(row.running_balance) || 0;
                    html += `
                        <tr style="background: #f9f9f9; font-weight: 600; border-top: 2px solid #ccc;">
                            <td colspan="3" style="text-align: right; padding: 12px 16px;">
                                <strong>${row.sub_account_name} Total:</strong>
                            </td>
                            <td><strong>${currencySymbol}${parseFloat(row.total_debit).toFixed(2)}</strong></td>
                            <td><strong>${currencySymbol}${parseFloat(row.total_credit).toFixed(2)}</strong></td>
                            <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}"><strong>${currencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</strong></td>
                        </tr>
                    `;
                } else {
                    const debit = parseFloat(row.debit) || 0;
                    const credit = parseFloat(row.credit) || 0;
                    const balance = parseFloat(row.running_balance) || 0;
                    const pdcDisplay = parseFloat(row.pdc_display) || 0;
                    
                    if (row.type !== 'opening_balance' && row.type !== 'sub_account_header' && row.type !== 'sub_account_total') {
                        totalDebit += debit;
                        totalCredit += credit;
                    }
                    
                    const creditDisplay = credit > 0 ? currencySymbol + credit.toFixed(2) : 
                                          (pdcDisplay > 0 ? `(${currencySymbol}${pdcDisplay.toFixed(2)})` : '');
                    
                    html += `
                        <tr>
                            <td>${row.date}</td>
                            <td>${row.description}</td>
                            <td>${row.reference}</td>
                            <td>${debit > 0 ? currencySymbol + debit.toFixed(2) : ''}</td>
                            <td>${creditDisplay}</td>
                            <td class="${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${currencySymbol}${Math.abs(balance).toFixed(2)} ${balance >= 0 ? 'Dr' : 'Cr'}</td>
                        </tr>
                    `;
                    
                    if ((row.type === 'invoice' || row.type === 'return') && row.items && row.items.length > 0 && expandedRows.includes(rowIndex.toString())) {
                        html += `
                            <tr class="items-row">
                                <td colspan="6">
                                    <table class="items-table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${row.items.map(item => `
                                                <tr>
                                                    <td>${item.product_name}</td>
                                                    <td>${parseFloat(item.quantity).toFixed(2)} ${item.uom_name || ''}</td>
                                                    <td>${currencySymbol}${parseFloat(item.sale_price).toFixed(2)}</td>
                                                    <td>${currencySymbol}${parseFloat(item.net_amount).toFixed(2)}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        `;
                    }
                    
                    rowIndex++;
                }
            });
            
            const finalBalance = openingBalance + totalDebit - totalCredit;
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
            
            document.getElementById('report-content').insertAdjacentHTML('beforeend', html);
            window.dispatchEvent(new Event('invoiceLoaded'));
            }
        }
    </script>
</body>
</html>