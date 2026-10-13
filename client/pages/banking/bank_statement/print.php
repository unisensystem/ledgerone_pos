<?php
session_start();
if (!($_SESSION['user_id'] ?? null)) {
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Statement - Print</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            .items-table tr { page-break-inside: avoid; }
            .statement-header, .statement-meta, .summary-row { page-break-after: avoid; }
            thead { display: table-header-group; }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; color: #333; background: white; }
        .statement-container { max-width: 900px; margin: 20px auto; padding: 20px; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .statement-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .bank-identity h1 { font-size: 22px; color: #333; margin-bottom: 5px; }
        .bank-identity p { color: #666; margin-top: 2px; }
        .statement-title { text-align: right; }
        .statement-title h2 { font-size: 20px; color: #666; margin-bottom: 10px; }
        .statement-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 20px; }
        .meta-box { padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .meta-box h3 { font-size: 13px; margin-bottom: 10px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .meta-box p { margin-top: 4px; }
        .summary-row { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .summary-box { flex: 1; min-width: 150px; border: 1px solid #ddd; border-radius: 5px; padding: 10px 14px; }
        .summary-box .label { font-size: 11px; color: #777; text-transform: uppercase; }
        .summary-box .value { font-size: 16px; font-weight: bold; color: #333; margin-top: 4px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-table th { background-color: #f5f5f5; font-weight: bold; font-size: 11px; }
        .items-table td { font-size: 11px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .positive { color: #2fbf71; font-weight: 600; }
        .negative { color: #e34f4f; font-weight: 600; }
        .totals-row td { font-weight: bold; background-color: #f5f5f5; }
        .print-btn { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-bottom: 20px; margin-right: 10px; }
        .print-btn:hover { background: #0056b3; }
        .footer-note { margin-top: 20px; font-size: 10px; color: #999; text-align: center; }
        .tx-count-line { font-size: 11px; color: #777; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="print-btn" onclick="window.print()">Print Statement</button>
        <button class="print-btn" id="downloadPdfBtn" style="background:#28a745;">&#8595; Download PDF</button>
    </div>

    <div class="statement-container" id="statementContainer">
        <div class="statement-header">
            <div class="bank-identity" style="display:flex; align-items:center; gap:12px;">
                <div id="logoWrapper"></div>
                <div>
                    <h1 id="bankName">Loading...</h1>
                    <p id="accountTitle"></p>
                    <p id="companyName"></p>
                    <p id="statusLine"></p>
                </div>
            </div>
            <div class="statement-title">
                <h2>BANK STATEMENT</h2>
                <p><strong>Period:</strong> <span id="statementPeriod">Loading...</span></p>
                <p><strong>Ref:</strong> <span id="statementRef">-</span></p>
                <p><strong>Generated:</strong> <span id="generatedDate"></span> by <span id="generatedBy">-</span></p>
            </div>
        </div>

        <div class="statement-meta">
            <div class="meta-box">
                <h3>Account Details</h3>
                <p><strong>Account #:</strong> <span id="accountNumber">-</span></p>
                <p><strong>Account Type:</strong> <span id="accountType">-</span></p>
                <p><strong>IBAN:</strong> <span id="ibanNo">-</span></p>
                <p><strong>Opening Balance As Of:</strong> <span id="asOfDate">-</span></p>
            </div>
            <div class="meta-box">
                <h3>Bank &amp; Branch Details</h3>
                <p><strong>Branch:</strong> <span id="branchName">-</span></p>
                <p><strong>Branch Address:</strong> <span id="branchAddress">-</span></p>
                <p><strong>SWIFT Code:</strong> <span id="swiftCode">-</span></p>
                <p><strong>Currency:</strong> <span id="currencyCode">-</span></p>
                <p><strong>Contact:</strong> <span id="contactInfo">-</span></p>
            </div>
        </div>

        <div class="summary-row">
            <div class="summary-box">
                <div class="label">Opening Balance</div>
                <div class="value" id="sumOpening">0.00</div>
            </div>
            <div class="summary-box">
                <div class="label">Total Deposits</div>
                <div class="value positive" id="sumDeposits">0.00</div>
            </div>
            <div class="summary-box">
                <div class="label">Total Withdrawals</div>
                <div class="value negative" id="sumWithdrawals">0.00</div>
            </div>
            <div class="summary-box">
                <div class="label">Closing Balance</div>
                <div class="value" id="sumClosing">0.00</div>
            </div>
            <div class="summary-box">
                <div class="label">Net Change</div>
                <div class="value" id="sumNetChange">0.00</div>
            </div>
        </div>
        <p class="tx-count-line" id="txCountLine"></p>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Voucher #</th>
                    <th class="text-right">Deposit</th>
                    <th class="text-right">Withdrawal</th>
                    <th class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody id="statementBody">
                <tr><td colspan="6" class="text-center">Loading...</td></tr>
            </tbody>
            <tfoot>
                <tr class="totals-row">
                    <td colspan="3">TOTALS</td>
                    <td class="text-right" id="footDeposits">0.00</td>
                    <td class="text-right" id="footWithdrawals">0.00</td>
                    <td class="text-right" id="footClosing">0.00</td>
                </tr>
            </tfoot>
        </table>

        <p class="footer-note">This is a system-generated bank statement from LedgerOne ERP.</p>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        function escHtml(v) {
            return String(v ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
        }

        function formatMoney(n) {
            const num = parseFloat(n) || 0;
            return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatDate(d) {
            if (!d) return '';
            const dt = new Date(d);
            if (isNaN(dt.getTime())) return d;
            return dt.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        const params = new URLSearchParams(window.location.search);

        async function loadStatement() {
            if (!params.get('bank_account_id')) {
                document.getElementById('statementBody').innerHTML = '<tr><td colspan="6" class="text-center">No bank account specified.</td></tr>';
                return;
            }

            try {
                const res = await fetch(`../../../../server/api/banking/bank_statement/bank-statement.php?${params.toString()}`);
                const data = await res.json();

                if (!data.success) {
                    document.getElementById('statementBody').innerHTML = `<tr><td colspan="6" class="text-center">${escHtml(data.message || 'Failed to load statement')}</td></tr>`;
                    return;
                }

                const bank = data.bank;
                const symbol = bank.currency_symbol || '';

                document.getElementById('bankName').textContent = bank.is_mfb && bank.mfb_name
                    ? `${bank.bank_name} (${bank.mfb_name})`
                    : (bank.bank_name || 'Bank');
                document.getElementById('accountTitle').textContent = bank.account_title || '';
                document.getElementById('companyName').textContent = bank.company_name || '';
                document.getElementById('statusLine').textContent = bank.is_active ? 'Status: Active' : 'Status: Inactive';
                document.getElementById('accountNumber').textContent = bank.account_number || '-';
                document.getElementById('accountType').textContent = bank.account_type
                    ? bank.account_type.charAt(0).toUpperCase() + bank.account_type.slice(1)
                    : '-';
                document.getElementById('ibanNo').textContent = bank.iban || '-';
                document.getElementById('branchName').textContent = bank.branch_name || '-';
                document.getElementById('swiftCode').textContent = bank.swift_code || '-';
                document.getElementById('currencyCode').textContent = bank.currency_code
                    ? `${bank.currency_code}${bank.currency_name ? ' - ' + bank.currency_name : ''}`
                    : '-';

                // Optional rows — hide the whole line rather than printing an empty "-"
                const setOptionalRow = (id, value) => {
                    const el = document.getElementById(id);
                    if (value) {
                        el.textContent = value;
                        el.closest('p').style.display = '';
                    } else {
                        el.closest('p').style.display = 'none';
                    }
                };
                setOptionalRow('asOfDate', bank.as_of_date ? formatDate(bank.as_of_date) : '');
                const branchAddressParts = [bank.branch_address, bank.branch_city, bank.branch_state].filter(Boolean);
                setOptionalRow('branchAddress', branchAddressParts.join(', '));
                const contactParts = [bank.contact_person, bank.contact_number, bank.email].filter(Boolean);
                setOptionalRow('contactInfo', contactParts.join(' / '));

                document.getElementById('statementRef').textContent = data.statement_ref || '-';
                document.getElementById('generatedBy').textContent = data.generated_by || '-';

                if (bank.bank_logo_path) {
                    const filename = bank.bank_logo_path.split('/').pop();
                    const img = document.createElement('img');
                    img.src = '../../../assets/uploads/bank_logo/' + filename;
                    img.alt = 'Bank Logo';
                    img.style.width = '50px';
                    img.style.height = '50px';
                    img.style.objectFit = 'contain';
                    document.getElementById('logoWrapper').appendChild(img);
                }

                const fromLabel = data.from_date ? formatDate(data.from_date) : 'Beginning';
                document.getElementById('statementPeriod').textContent = `${fromLabel} to ${formatDate(data.to_date)}`;
                document.getElementById('generatedDate').textContent = formatDate(new Date());

                const sumOpeningEl = document.getElementById('sumOpening');
                sumOpeningEl.textContent = `${symbol} ${formatMoney(Math.abs(data.opening_balance))}${data.opening_balance < 0 ? ' DR' : ''}`;
                sumOpeningEl.style.color = data.opening_balance < 0 ? '#e34f4f' : '';
                document.getElementById('sumDeposits').textContent = `${symbol} ${formatMoney(data.total_deposits)}`;
                document.getElementById('sumWithdrawals').textContent = `${symbol} ${formatMoney(data.total_withdrawals)}`;
                const sumClosingEl = document.getElementById('sumClosing');
                sumClosingEl.textContent = `${symbol} ${formatMoney(Math.abs(data.closing_balance))}${data.closing_balance < 0 ? ' DR' : ''}`;
                sumClosingEl.style.color = data.closing_balance < 0 ? '#e34f4f' : '';
                const netChangeEl = document.getElementById('sumNetChange');
                netChangeEl.textContent = `${data.net_change >= 0 ? '+' : '-'}${symbol} ${formatMoney(Math.abs(data.net_change))}`;
                netChangeEl.style.color = data.net_change >= 0 ? '#2fbf71' : '#e34f4f';
                document.getElementById('txCountLine').textContent =
                    `${data.transaction_count} transactions in this period (${data.deposit_count} deposits, ${data.withdrawal_count} withdrawals)`;

                document.getElementById('footDeposits').textContent = formatMoney(data.total_deposits);
                document.getElementById('footWithdrawals').textContent = formatMoney(data.total_withdrawals);
                document.getElementById('footClosing').textContent = `${formatMoney(Math.abs(data.closing_balance))}${data.closing_balance < 0 ? ' DR' : ''}`;

                const tbody = document.getElementById('statementBody');
                let rowsHtml = '';

                if (data.from_date) {
                    rowsHtml += `
                        <tr style="background-color:#f9f9f9;">
                            <td>${escHtml(formatDate(data.from_date))}</td>
                            <td colspan="4"><em>Opening Balance</em></td>
                            <td class="text-right${data.opening_balance < 0 ? ' negative' : ''}"><strong>${formatMoney(Math.abs(data.opening_balance))}${data.opening_balance < 0 ? ' DR' : ''}</strong></td>
                        </tr>
                    `;
                }

                if (!data.transactions.length) {
                    rowsHtml += '<tr><td colspan="6" class="text-center">No transactions found for this period.</td></tr>';
                } else {
                    rowsHtml += data.transactions.map(tx => `
                        <tr>
                            <td>${escHtml(formatDate(tx.date))}</td>
                            <td>${escHtml(tx.description)}</td>
                            <td>${escHtml(tx.voucher_number || '-')}</td>
                            <td class="text-right ${tx.deposit > 0 ? 'positive' : ''}">${tx.deposit > 0 ? formatMoney(tx.deposit) : '-'}</td>
                            <td class="text-right ${tx.withdrawal > 0 ? 'negative' : ''}">${tx.withdrawal > 0 ? formatMoney(tx.withdrawal) : '-'}</td>
                            <td class="text-right${tx.balance < 0 ? ' negative' : ''}"><strong>${formatMoney(Math.abs(tx.balance))}${tx.balance < 0 ? ' DR' : ''}</strong></td>
                        </tr>
                    `).join('');
                }

                tbody.innerHTML = rowsHtml;
            } catch (e) {
                document.getElementById('statementBody').innerHTML = `<tr><td colspan="6" class="text-center">Error loading statement: ${escHtml(e.message)}</td></tr>`;
            }
        }

        document.getElementById('downloadPdfBtn').addEventListener('click', () => {
            const el = document.getElementById('statementContainer');
            const bankName = document.getElementById('bankName').textContent.replace(/[^a-z0-9]/gi, '_');
            html2pdf().set({
                margin: 10,
                filename: `Bank_Statement_${bankName}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'avoid-all'] }
            }).from(el).save();
        });

        loadStatement();
    </script>
</body>
</html>
