<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grocery Purchase Invoice - Print</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.35;
            color: #222;
            background: white;
        }

        .invoice-container {
            max-width: 950px;
            margin: 20px auto;
            padding: 18px;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #999;
        }

        /* ===== HEADER ===== */
        .top-header {
            display: grid;
            grid-template-columns: 90px 1fr 220px;
            gap: 10px;
            align-items: start;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
            margin-bottom: 6px;
        }
        .top-header .logo-box {
            width: 85px; height: 65px;
            border: 1px solid #999;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }
        .top-header .logo-box img { max-width: 100%; max-height: 100%; }
        .company-block { text-align: center; }
        .company-block h1 {
            font-size: 19px;
            color: #1f4fb3;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .company-block p { font-size: 10.5px; color: #333; }
        .reg-numbers { font-size: 10px; text-align: right; line-height: 1.5; }
        .reg-numbers div span.lbl { font-weight: bold; }

        .invoice-title-bar {
            text-align: center;
            border: 1px solid #333;
            width: 220px;
            margin: 4px auto 8px;
            padding: 3px 0;
            font-weight: bold;
            font-size: 13px;
        }

        /* ===== META INFO GRID ===== */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 2px 20px;
            font-size: 10.5px;
            border-bottom: 1px solid #333;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .meta-grid .row { display: flex; gap: 4px; }
        .meta-grid .row .lbl { font-weight: bold; white-space: nowrap; }

        /* ===== ITEMS TABLE ===== */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .items-table th, .items-table td {
            border: 1px solid #999;
            padding: 3px 4px;
        }
        .items-table thead th {
            background: #f0f0f0;
            font-weight: bold;
            font-size: 10px;
            text-align: center;
        }
        .items-table td { font-size: 10.5px; }
        .items-table .text-right { text-align: right; }
        .items-table .text-center { text-align: center; }

        .grand-total-row td {
            font-weight: bold;
            background: #eee;
            border-top: 2px solid #333;
        }

        /* ===== SUMMARY BOX ===== */
        .summary-box {
            border: 1px solid #333;
            padding: 8px 12px;
            margin-top: 6px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 20px;
            font-size: 11px;
        }
        .summary-box .left-col { font-weight: bold; color: #1f4fb3; }
        .summary-box .right-col { text-align: right; }
        .summary-box .net-total-row {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #333;
            padding-top: 4px;
            margin-top: 4px;
            font-weight: bold;
            font-size: 12px;
        }
        .net-total-row .val { color: #1f4fb3; }
        .amount-words-row {
            grid-column: 1 / -1;
            font-style: italic;
            font-weight: bold;
            color: #1f4fb3;
            border-top: 1px dashed #999;
            padding-top: 4px;
            margin-top: 4px;
        }

        /* ===== SIGNATURE / FOOTER ===== */
        .warranty-section {
            margin-top: 24px;
            font-size: 9.5px;
            color: #333;
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }
        .warranty-section .sign-block { width: 200px; text-align: center; }
        .warranty-section .sign-block .sign-line {
            border-bottom: 1px solid #333;
            width: 180px;
            margin: 40px auto 4px;
        }
        .warranty-section .sign-label { font-weight: bold; font-size: 10px; }

        .print-footer {
            margin-top: 14px;
            padding-top: 6px;
            border-top: 1px solid #ccc;
            font-size: 9px;
            color: #777;
            display: flex;
            justify-content: space-between;
        }

        .print-btn {
            background: #007bff; color: white; border: none;
            padding: 8px 16px; border-radius: 4px; cursor: pointer; margin: 0 4px 12px 0;
            font-size: 13px;
        }
        .print-btn:hover { background: #0056b3; }
        .print-btn.active { background: #28a745; }

        @media print {
            @page { size: var(--page-size); margin: 8mm; }
        }

        body.a5-mode { font-size: 9px; }
        body.a5-mode .invoice-container { max-width: 650px; padding: 10px; }
        body.a6-mode { font-size: 7px; }
        body.a6-mode .invoice-container { max-width: 400px; padding: 6px; }
    </style>
</head>

<body>
    <div class="no-print">
        <button class="print-btn" onclick="window.print()">Print Invoice</button>
        <button class="print-btn" onclick="setPaperSize('A4')" id="btnA4">A4</button>
        <button class="print-btn" onclick="setPaperSize('A5')" id="btnA5">A5</button>
        <button class="print-btn" onclick="setPaperSize('A6')" id="btnA6">A6</button>
        <button class="print-btn" id="downloadPdfBtn" onclick="downloadAsPdf()" style="background:#28a745;">&#8595; Download PDF</button>
    </div>

    <div class="invoice-container" id="invoiceContainer">

        <!-- Header -->
        <div class="top-header">
            <div class="logo-box" id="companyLogoWrapper"></div>
            <div class="company-block">
                <h1 id="companyName">Loading...</h1>
                <p id="companyAddress">Loading...</p>
                <p><span id="companyPhone"></span> <span id="companyEmail"></span></p>
            </div>
            <div class="reg-numbers">
                <div><span class="lbl">STN:</span> <span id="companyStn">-</span></div>
                <div><span class="lbl">NTN:</span> <span id="companyNtn">-</span></div>
                <div><span class="lbl">NIC:</span> <span id="companyNic">-</span></div>
            </div>
        </div>

        <div class="invoice-title-bar">Grocery Purchase Invoice</div>

        <!-- Meta info -->
        <div class="meta-grid">
            <div class="row"><span class="lbl">Invoice #:</span> <span id="invoiceNo">Loading...</span></div>
            <div class="row"><span class="lbl">Invoice Date:</span> <span id="invoiceDate">Loading...</span></div>
            <div class="row"><span class="lbl">Currency:</span> <span id="currency">-</span></div>

            <div class="row"><span class="lbl">Supplier:</span> <span id="supplierName">Loading...</span></div>
            <div class="row"><span class="lbl">Sub Account:</span> <span id="subAccountName">-</span></div>
            <div class="row"><span class="lbl">Purchase Order #:</span> <span id="purchaseOrderNo">-</span></div>

            <div class="row"><span class="lbl">Supplier Inv #:</span> <span id="supplierInvoiceNo">-</span></div>
            <div class="row"><span class="lbl">Supplier Inv Date:</span> <span id="supplierInvoiceDate">-</span></div>
            <div class="row"><span class="lbl">Previous Balance:</span> <span id="previousBalance">-</span></div>

            <div class="row"><span class="lbl">Branch:</span> <span id="branchName">Loading...</span></div>
            <div class="row"><span class="lbl">Project:</span> <span id="projectName">-</span></div>
            <div class="row"><span class="lbl">Bilty No:</span> <span id="biltyNo">-</span></div>

            <div class="row"><span class="lbl">Transport:</span> <span id="transportName">-</span></div>
            <div class="row"><span class="lbl">Remarks:</span> <span id="remarks">-</span></div>
            <div class="row"><span class="lbl">Gen. By:</span> <span id="generatedBy">-</span></div>

            <div class="row"></div>
            <div class="row"></div>
            <div class="row"><span class="lbl">Gen. Date:</span> <span id="generatedOn"></span></div>
        </div>

        <!-- Items table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:3%;">Sr.</th>
                    <th style="width:17%; text-align:left;">Item Name</th>
                    <th style="width:6%;" data-col="size">Size</th>
                    <th style="width:7%;" data-col="batch">Batch No.</th>
                    <th style="width:7%;" data-col="expiry">Expiry Date</th>
                    <th style="width:10%;">Quantity</th>
                    <th style="width:7%;">Rate</th>
                    <th style="width:8%;">Gross</th>
                    <th style="width:5%;" data-col="disc_pct">Disc %</th>
                    <th style="width:7%;" data-col="disc_amt">Disc Amount</th>
                    <th style="width:5%;" data-col="to_pct">TO %</th>
                    <th style="width:7%;" data-col="to_amt">TO Amount</th>
                    <th style="width:5%;" data-col="tax_pct">Tax %</th>
                    <th style="width:6%;" data-col="tax_amt">Tax Amt</th>
                    <th style="width:6%;" data-col="foc">FOC Qty</th>
                    <th style="width:8%;">Net Amount</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <tr><td colspan="16" class="text-center">Loading items...</td></tr>
            </tbody>
        </table>

        <!-- Summary box -->
        <div class="summary-box" id="summaryBox">
            <div class="left-col">Gross Amount:</div>
            <div class="right-col" id="summaryGrossAmount">0.000</div>

            <div class="left-col">Discount Amount:</div>
            <div class="right-col" id="summaryDiscountAmount">0.000</div>

            <div class="left-col" id="shippingFeesLabelRow" style="display:none;">Shipping Fees:</div>
            <div class="right-col" id="summaryShippingFees" style="display:none;">0.000</div>

            <div id="invoiceTaxesAnchor" style="display:none;"></div>

            <div class="net-total-row">
                <span>Net Total:</span>
                <span class="val" id="summaryNetTotal">0.000</span>
            </div>

            <div class="amount-words-row" id="amountInWords">--</div>
        </div>

        <!-- Signatures -->
        <div class="warranty-section">
            <div class="sign-block">
                <div class="sign-line"></div>
                <div class="sign-label">Supplier Signature</div>
            </div>
            <div class="sign-block">
                <div class="sign-line"></div>
                <div class="sign-label">Authorized Signature</div>
            </div>
        </div>

        <div class="print-footer">
            <div>Software Develop By: <span id="footerSoftware">Your Company</span></div>
            <div>Printed By: <span id="printedByFooter"></span> | <span id="printedOnFooter"></span></div>
        </div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function setPaperSize(size) {
            localStorage.setItem('invoicePaperSize', size);
            document.documentElement.style.setProperty('--page-size', size);
            document.body.classList.remove('a5-mode', 'a6-mode');
            if (size === 'A5') document.body.classList.add('a5-mode');
            if (size === 'A6') document.body.classList.add('a6-mode');
            document.querySelectorAll('.print-btn').forEach(btn => btn.classList.remove('active'));
            const btn = document.getElementById('btn' + size);
            if (btn) btn.classList.add('active');
        }
        setPaperSize(localStorage.getItem('invoicePaperSize') || 'A4');

        const urlParams = new URLSearchParams(window.location.search);
        const invoiceId = urlParams.get('id');
        if (!invoiceId) { alert('Invoice ID is required'); window.close(); }

        const enableSize                     = localStorage.getItem('enableSize') === 'true';
        const enableInlineCashDiscount       = localStorage.getItem('enableInlineCashDiscount') === 'true';
        const enableInlineCashDiscountAmount = localStorage.getItem('enableInlineCashDiscountAmount') === 'true';
        const enableTradeOffer               = localStorage.getItem('enableTradeOffer') === 'true';
        const enableTradeOfferAmount         = localStorage.getItem('enableTradeOfferAmount') === 'true';
        const enableFOC                      = localStorage.getItem('enableFOC') === 'true';
        const enableTaxation                 = localStorage.getItem('enableTaxation') === 'true';
        const enableShippingFees             = localStorage.getItem('enableShippingFees') === 'true';

        function setColVisible(col, visible) {
            document.querySelectorAll(`[data-col="${col}"]`).forEach(el => {
                el.style.display = visible ? '' : 'none';
            });
        }

        setColVisible('size',     enableSize);
        setColVisible('batch',    true);
        setColVisible('expiry',   true);
        setColVisible('disc_pct', enableInlineCashDiscount);
        setColVisible('disc_amt', enableInlineCashDiscountAmount);
        setColVisible('to_pct',   enableTradeOffer);
        setColVisible('to_amt',   enableTradeOfferAmount);
        setColVisible('tax_pct',  enableTaxation);
        setColVisible('tax_amt',  enableTaxation);
        setColVisible('foc',      enableFOC);

        function fmt(n) {
            return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
        }
        function fmtDate(d) {
            if (!d) return '-';
            const dt = new Date(d);
            if (isNaN(dt)) return '-';
            return dt.toLocaleDateString('en-GB');
        }

        async function loadData() {
            try {
                const [userRes, invoiceRes] = await Promise.all([
                    fetch('../../../../server/api/purchase/grocery_purchase_invoice/get-user.php'),
                    fetch(`../../../../server/api/purchase/grocery_purchase_invoice/purchase-edit.php?id=${invoiceId}`)
                ]);
                const userData    = await userRes.json();
                const invoiceData = await invoiceRes.json();

                if (userData.success) {
                    document.getElementById('generatedBy').textContent = userData.user.full_name;
                    document.getElementById('printedByFooter').textContent = userData.user.full_name;
                }

                if (invoiceData.success) {
                    populateCompanyData(invoiceData.invoice);
                    populateInvoiceData(invoiceData.invoice, invoiceData.items, invoiceData.invoice_taxes || []);
                } else {
                    alert('Error loading invoice: ' + invoiceData.message);
                }
            } catch (e) {
                alert('Error loading data: ' + e.message);
            }
        }

        function populateCompanyData(invoice) {
            if (invoice.logo_url) {
                const img = document.createElement('img');
                img.src = `../../../assets/uploads/company_logo/${invoice.logo_url}`;
                document.getElementById('companyLogoWrapper').appendChild(img);
            }
            document.getElementById('companyName').textContent = invoice.company_name || invoice.legal_name || 'Company Name';
            const addrParts = [invoice.company_address, invoice.company_city, invoice.company_state].filter(Boolean).join(', ');
            document.getElementById('companyAddress').textContent = addrParts;
            document.getElementById('companyPhone').textContent = invoice.company_phone ? `Phone# ${invoice.company_phone}` : '';
            document.getElementById('companyEmail').textContent = invoice.company_email ? `E-Mail: ${invoice.company_email}` : '';
            document.getElementById('companyStn').textContent = invoice.stn_number || '-';
            document.getElementById('companyNtn').textContent = invoice.ntn_number || '-';
            document.getElementById('companyNic').textContent = invoice.nic_number || '-';
            document.getElementById('footerSoftware').textContent = invoice.company_name || '';
        }

        function populateInvoiceData(invoice, items, invoiceTaxes) {
            document.getElementById('invoiceNo').textContent   = invoice.bill_no;
            document.getElementById('invoiceDate').textContent = fmtDate(invoice.purchase_date);
            document.getElementById('currency').textContent    = invoice.currency_name || invoice.currency_code || '-';
            document.getElementById('supplierName').textContent = `${invoice.supplier_code || ''} - ${invoice.supplier_name || ''}`.replace(/^\s*-\s*/, '');
            document.getElementById('subAccountName').textContent = invoice.sub_account_name || '-';
            document.getElementById('purchaseOrderNo').textContent = invoice.purchase_order_bill_no || '-';
            document.getElementById('supplierInvoiceNo').textContent = invoice.supplier_invoice_no || '-';
            document.getElementById('supplierInvoiceDate').textContent = invoice.supplier_invoice_date ? fmtDate(invoice.supplier_invoice_date) : '-';
            document.getElementById('previousBalance').textContent = invoice.previous_balance || '-';
            const branchText = invoice.parent_branch_name
                ? `${invoice.parent_branch_name} > ${invoice.branch_code || ''} ${invoice.branch_name} (${invoice.branch_type})`
                : `${invoice.branch_code || ''} ${invoice.branch_name} (${invoice.branch_type})`;
            document.getElementById('branchName').textContent = branchText;
            document.getElementById('projectName').textContent = invoice.project_name ? `${invoice.project_code} - ${invoice.project_name}` : '-';
            document.getElementById('biltyNo').textContent = invoice.bilty_no || '-';
            document.getElementById('transportName').textContent = invoice.transport_name || '-';
            document.getElementById('remarks').textContent = invoice.remarks || '-';
            document.getElementById('generatedOn').textContent = fmtDate(new Date());
            document.getElementById('printedOnFooter').textContent = new Date().toLocaleString();

            const currencySymbol = invoice.currency_symbol || '';
            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';

            let serialNumber = 0;
            let grandGross = 0, grandDisc = 0, grandTO = 0, grandTax = 0, grandFOC = 0, grandNet = 0;

            items.forEach(item => {
                serialNumber++;

                const unitMap = {};
                (item.unit_entries || []).forEach(e => {
                    const qty = parseFloat(e.quantity);
                    if (qty > 0) {
                        const u = e.uom_name || 'Unit';
                        unitMap[u] = (unitMap[u] || 0) + qty;
                    }
                });
                const unitQtyDisplay = Object.entries(unitMap).map(([u, q]) => `${q.toFixed(3)} ${u}`).join(', ') || '-';

                const gross = parseFloat(item.gross_amount || 0);
                const discAmt = parseFloat(item.discount_amount || 0);
                const discPct = parseFloat(item.discount_percent || 0);
                const toAmt = parseFloat(item.trade_offer_amount || 0);
                const toPct = parseFloat(item.trade_offer_percent || 0);
                const taxAmt = parseFloat(item.tax_amount || 0);
                const taxPct = parseFloat(item.tax_percent || 0);
                const foc = parseFloat(item.foc_quantity || 0);
                const net = parseFloat(item.net_amount || 0);
                const rate = parseFloat(item.purchase_price || 0);

                const row = tbody.insertRow();
                row.innerHTML = `
                    <td class="text-center">${serialNumber}</td>
                    <td style="text-align:left;">${item.product_code ? item.product_code + '/' : ''}${item.product_name}</td>
                    <td class="text-center" data-col="size" style="display:${enableSize ? '' : 'none'}">${item.size || '-'}</td>
                    <td class="text-center" data-col="batch">${item.batch_no || '-'}</td>
                    <td class="text-center" data-col="expiry">${item.batch_expiry_date ? fmtDate(item.batch_expiry_date) : '-'}</td>
                    <td class="text-right">${unitQtyDisplay}</td>
                    <td class="text-right">${currencySymbol} ${fmt(rate)}</td>
                    <td class="text-right">${currencySymbol} ${fmt(gross)}</td>
                    <td class="text-right" data-col="disc_pct" style="display:${enableInlineCashDiscount ? '' : 'none'}">${discPct.toFixed(2)}%</td>
                    <td class="text-right" data-col="disc_amt" style="display:${enableInlineCashDiscountAmount ? '' : 'none'}">${currencySymbol} ${fmt(discAmt)}</td>
                    <td class="text-right" data-col="to_pct" style="display:${enableTradeOffer ? '' : 'none'}">${toPct.toFixed(2)}%</td>
                    <td class="text-right" data-col="to_amt" style="display:${enableTradeOfferAmount ? '' : 'none'}">${currencySymbol} ${fmt(toAmt)}</td>
                    <td class="text-right" data-col="tax_pct" style="display:${enableTaxation ? '' : 'none'}">${taxPct.toFixed(2)}%</td>
                    <td class="text-right" data-col="tax_amt" style="display:${enableTaxation ? '' : 'none'}">${currencySymbol} ${fmt(taxAmt)}</td>
                    <td class="text-right" data-col="foc" style="display:${enableFOC ? '' : 'none'}">${foc ? foc.toFixed(3) : '-'}</td>
                    <td class="text-right">${currencySymbol} ${fmt(net)}</td>
                `;

                grandGross += gross; grandDisc += discAmt; grandTO += toAmt; grandTax += taxAmt; grandFOC += foc; grandNet += net;
            });

            const grandRow = tbody.insertRow();
            grandRow.className = 'grand-total-row';
            grandRow.innerHTML = `
                <td colspan="2" style="text-align:left;">Total Items: ${serialNumber} &nbsp;&nbsp; Total For Invoice ${invoice.bill_no} :</td>
                <td data-col="size" style="display:${enableSize ? '' : 'none'}"></td>
                <td data-col="batch"></td>
                <td data-col="expiry"></td>
                <td></td>
                <td></td>
                <td class="text-right">${currencySymbol} ${fmt(grandGross)}</td>
                <td data-col="disc_pct" style="display:${enableInlineCashDiscount ? '' : 'none'}"></td>
                <td class="text-right" data-col="disc_amt" style="display:${enableInlineCashDiscountAmount ? '' : 'none'}">${currencySymbol} ${fmt(grandDisc)}</td>
                <td data-col="to_pct" style="display:${enableTradeOffer ? '' : 'none'}"></td>
                <td class="text-right" data-col="to_amt" style="display:${enableTradeOfferAmount ? '' : 'none'}">${currencySymbol} ${fmt(grandTO)}</td>
                <td data-col="tax_pct" style="display:${enableTaxation ? '' : 'none'}"></td>
                <td class="text-right" data-col="tax_amt" style="display:${enableTaxation ? '' : 'none'}">${currencySymbol} ${fmt(grandTax)}</td>
                <td class="text-right" data-col="foc" style="display:${enableFOC ? '' : 'none'}">${grandFOC.toFixed(3)}</td>
                <td class="text-right">${currencySymbol} ${fmt(grandNet)}</td>
            `;

            // ===== Summary box =====
            document.getElementById('summaryGrossAmount').textContent = `${currencySymbol} ${fmt(invoice.total_bill)}`;
            document.getElementById('summaryDiscountAmount').textContent = `${currencySymbol} ${fmt(invoice.total_discount_amount)}`;

            if (enableShippingFees && parseFloat(invoice.shipping_fees || 0) > 0) {
                const sym = (invoice.shipping_fees_type || 'add') === 'add' ? '+' : '-';
                document.getElementById('shippingFeesLabelRow').style.display = '';
                document.getElementById('shippingFeesLabelRow').textContent = `Shipping Fees (${sym}):`;
                document.getElementById('summaryShippingFees').style.display = '';
                document.getElementById('summaryShippingFees').textContent = `${currencySymbol} ${fmt(invoice.shipping_fees)}`;
            }

            // Invoice-level taxes
            const anchor = document.getElementById('invoiceTaxesAnchor');
            document.querySelectorAll('.dynamic-tax-row').forEach(r => r.remove());
            if (invoiceTaxes && invoiceTaxes.length > 0) {
                invoiceTaxes.forEach(tax => {
                    const taxAmt = parseFloat(tax.tax_amount || 0);
                    if (taxAmt === 0) return;
                    const left = document.createElement('div');
                    left.className = 'left-col dynamic-tax-row';
                    left.textContent = `${tax.tax_name} (${parseFloat(tax.rate_percentage).toFixed(2)}%):`;
                    const right = document.createElement('div');
                    right.className = 'right-col dynamic-tax-row';
                    right.textContent = `${currencySymbol} ${fmt(taxAmt)}`;
                    anchor.parentNode.insertBefore(left, anchor);
                    anchor.parentNode.insertBefore(right, anchor);
                });
            }

            document.getElementById('summaryNetTotal').textContent = `${currencySymbol} ${fmt(invoice.net_amount)}`;

            // Amount in words
            const currencyUnitMap = {
                'USD': ['Dollars', 'Cents'], 'PKR': ['Rupees', 'Paisa'], 'INR': ['Rupees', 'Paisa'],
                'AED': ['Dirhams', 'Fils'], 'SAR': ['Riyals', 'Halalah']
            };
            const invCurrCode = (invoice.currency_code || 'PKR').toUpperCase();
            const [majorUnit, minorUnit] = currencyUnitMap[invCurrCode] || ['Units', 'Subunits'];
            const netMajor = Math.floor(parseFloat(invoice.net_amount));
            const netMinor = Math.round((parseFloat(invoice.net_amount) % 1) * 100);
            let words = `${invCurrCode} ${numberToWords(netMajor)} ${majorUnit}`;
            if (netMinor > 0) words += ` and ${numberToWords(netMinor)} ${minorUnit}`;
            words += ' Only.';
            document.getElementById('amountInWords').textContent = words;

            window.dispatchEvent(new Event('invoiceLoaded'));
        }

        function numberToWords(num) {
            const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
            const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
            const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            const thousands = ['', 'Thousand', 'Million', 'Billion'];
            if (num === 0) return 'Zero';
            function convertHundreds(n) {
                let result = '';
                if (n >= 100) { result += ones[Math.floor(n / 100)] + ' Hundred '; n %= 100; }
                if (n >= 20) { result += tens[Math.floor(n / 10)] + ' '; n %= 10; }
                else if (n >= 10) { result += teens[n - 10] + ' '; n = 0; }
                if (n > 0) result += ones[n] + ' ';
                return result;
            }
            let result = '', thousandIndex = 0;
            while (num > 0) {
                if (num % 1000 !== 0) result = convertHundreds(num % 1000) + thousands[thousandIndex] + ' ' + result;
                num = Math.floor(num / 1000);
                thousandIndex++;
            }
            return result.trim();
        }

        async function downloadAsPdf() {
            const btn = document.getElementById('downloadPdfBtn');
            btn.disabled = true;
            btn.textContent = 'Generating...';
            document.querySelectorAll('.no-print').forEach(el => el.style.setProperty('display', 'none', 'important'));

            const invoiceNo = document.getElementById('invoiceNo').textContent || 'Invoice';
            const paperSize = localStorage.getItem('invoicePaperSize') || 'A4';
            const sizeMap = { A4: 'a4', A5: 'a5', A6: [105, 148] };
            const format = sizeMap[paperSize] || 'a4';

            await html2pdf().set({
                margin: 8,
                filename: `Purchase-Invoice-${invoiceNo}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, allowTaint: true, logging: false },
                jsPDF: { unit: 'mm', format: format, orientation: 'portrait' }
            }).from(document.getElementById('invoiceContainer')).save();

            document.querySelectorAll('.no-print').forEach(el => el.style.removeProperty('display'));
            btn.disabled = false;
            btn.textContent = '\u2193 Download PDF';
        }

        if (new URLSearchParams(window.location.search).get('pdf') === '1') {
            window.addEventListener('invoiceLoaded', async () => {
                await new Promise(r => setTimeout(r, 300));
                await downloadAsPdf();
                window.close();
            });
        }

        loadData();
    </script>
</body>
</html>