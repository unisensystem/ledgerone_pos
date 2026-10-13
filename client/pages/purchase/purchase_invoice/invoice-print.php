<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Invoice - Print</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; color: #333; background: white; }
        .invoice-container { max-width: 800px; margin: 20px auto; padding: 20px; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .invoice-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .company-info h1 { font-size: 24px; color: #333; margin-bottom: 5px; }
        .invoice-details { text-align: right; }
        .invoice-details h2 { font-size: 20px; color: #666; margin-bottom: 10px; }
        .invoice-details h2 { font-size: 20px; color: #666; margin-bottom: 10px; }
        .invoice-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .supplier-info, .invoice-info { padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .supplier-info h3, .invoice-info h3 { font-size: 14px; margin-bottom: 10px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-table th { background-color: #f5f5f5; font-weight: bold; font-size: 11px; }
        .items-table td { font-size: 11px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals-section { display: flex; justify-content: flex-end; margin-top: 20px; }
        .totals-table { width: 300px; }
        .totals-table td { padding: 5px 10px; border: none; }
        .totals-table .total-row { font-weight: bold; border-top: 2px solid #333; }
        .print-btn { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
        .print-btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="print-btn" onclick="window.print()">Print Invoice</button>
        <button class="print-btn" id="downloadPdfBtn" onclick="downloadAsPdf()" style="background:#28a745;">&#8595; Download PDF</button>
    </div>

    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-info" style="display:flex; align-items:center; gap:15px;">
                <div id="companyLogoWrapper"></div>
                <div>
                    <h1 id="companyName">Loading...</h1>
                    <p id="companyAddress"></p>
                    <p id="companyCityState"></p>
                    <p id="companyPhone"></p>
                    <p id="companyEmail"></p>
                </div>
            </div>
            <div class="invoice-details">
                <h2>PURCHASE INVOICE</h2>
                <p><strong>Invoice #:</strong> <span id="invoiceNo">Loading...</span></p>
                <p><strong>Date:</strong> <span id="invoiceDate">Loading...</span></p>
            </div>
        </div>

        <div class="invoice-meta">
            <div class="supplier-info">
                <h3>Supplier Information</h3>
                <p><strong>Name:</strong> <span id="supplierName">Loading...</span></p>
                <p><strong>Sub Account:</strong> <span id="subAccountName">-</span></p>
                <p><strong>Supplier Invoice No:</strong> <span id="supplierInvoiceNo">-</span></p>
                <p><strong>Supplier Invoice Date:</strong> <span id="supplierInvoiceDate">-</span></p>
                <p><strong>Bilty No:</strong> <span id="biltyNo">-</span></p>
                <p><strong>Transport Name:</strong> <span id="transportName">-</span></p>
                <p><strong>Previous Balance:</strong> <span id="previousBalance">-</span></p>
            </div>
            <div class="invoice-info">
                <h3>Invoice Information</h3>
                <p><strong>Branch:</strong> <span id="branchName">Loading...</span></p>
                <p><strong>Project:</strong> <span id="projectName">-</span></p>
                <p><strong>Currency:</strong> <span id="currency">Loading...</span></p>
                <p><strong>Remarks:</strong> <span id="remarks">-</span></p>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="25%">Product</th>
                    <th width="7%" data-col="size">Size</th>
                    <th width="13%">Quantities</th>
                    <th width="10%" class="text-right">Trade Price</th>
                    <th width="10%" class="text-right">Gross Amt</th>
                    <th width="7%" class="text-right" data-col="disc_pct">Disc %</th>
                    <th width="10%" class="text-right" data-col="disc_amt">Disc Amt</th>
                    <th width="7%" class="text-right" data-col="extra_disc_pct">Extra Disc %</th>
                    <th width="7%" class="text-right" data-col="to_pct">TO %</th>
                    <th width="10%" class="text-right" data-col="to_amt">TO Amt</th>
                    <th width="5%" class="text-right" data-col="tax_pct">Tax %</th>
                    <th width="10%" class="text-right" data-col="tax_amt">Tax Amt</th>
                    <th width="7%" class="text-right" data-col="foc">FOC Qty</th>
                    <th width="12%" class="text-right">Net Amt</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <tr><td colspan="14" class="text-center">Loading items...</td></tr>
            </tbody>
            <tfoot>
                <tr style="background-color: #f5f5f5; font-weight: bold;">
                    <th></th>
                    <th>Totals</th>
                    <th data-col="size"></th>
                    <th></th>
                    <th class="text-right" id="totalUnitPrice">0.000</th>
                    <th class="text-right" id="totalGrossAmount">0.000</th>
                    <th data-col="disc_pct"></th>
                    <th class="text-right" data-col="disc_amt" id="totalDiscountAmountItems">0.000</th>
                    <th data-col="extra_disc_pct"></th>
                    <th data-col="to_pct"></th>
                    <th class="text-right" data-col="to_amt" id="totalTradeOfferAmountItems">0.000</th>
                    <th data-col="tax_pct"></th>
                    <th class="text-right" data-col="tax_amt" id="totalGSTAmountItems">0.000</th>
                    <th class="text-right" data-col="foc" id="totalFOCQty">0.000</th>
                    <th class="text-right" id="totalNetAmountItems">0.000</th>
                </tr>
            </tfoot>
        </table>

        <div class="totals-section">
            <table class="totals-table">
                <tbody>
                    <tr>
                        <td>Total Bill:</td>
                        <td class="text-right" id="totalBill">0.000</td>
                    </tr>
                    <tr id="discountPercentRow" style="display:none;">
                        <td>Discount (%):</td>
                        <td class="text-right" id="discountPercent">0.000%</td>
                    </tr>
                    <tr id="discountAmountRow" style="display:none;">
                        <td>Discount Amount:</td>
                        <td class="text-right" id="discountAmount">0.000</td>
                    </tr>
                    <tr id="shippingFeesRow" style="display:none;">
                        <td><span id="shippingFeesLabel">Shipping Fees:</span></td>
                        <td class="text-right" id="shippingFees">0.000</td>
                    </tr>
                    <tr id="invoiceTaxesAnchor" style="display:none;"></tr>
                    <tr class="total-row">
                        <td>Net Amount:</td>
                        <td class="text-right" id="netAmount">0.000</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px; padding: 15px; background-color: #f9f9f9; border-radius: 5px;">
            <p><strong>Amount in Words:</strong> <span id="amountInWords">Loading...</span></p>
        </div>

        <div style="margin-top: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 50px;">
            <div>
                <p style="margin-bottom: 50px;"><strong>Supplier Signature:</strong></p>
                <div style="border-bottom: 1px solid #333; width: 200px; margin-bottom: 5px;"></div>
                <p style="font-size: 11px; color: #666;">Date: ___________</p>
            </div>
            <div>
                <p style="margin-bottom: 50px;"><strong>Authorized Signature:</strong></p>
                <div style="border-bottom: 1px solid #333; width: 200px; margin-bottom: 5px;"></div>
                <p style="font-size: 11px; color: #666;">Date: ___________</p>
            </div>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 11px; color: #666;">
            <p><strong>Generated by:</strong> <span id="generatedBy">Loading...</span></p>
            <p><strong>Generated on:</strong> <span id="generatedOn"></span></p>
        </div>

        <div style="margin-top: 20px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px;">
            <p><em>This is a System Generated Invoice</em></p>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const invoiceId = urlParams.get('id');
        if (!invoiceId) { alert('Invoice ID is required'); window.close(); }

        const enableSize                    = localStorage.getItem('enableSize') === 'true';
        const enableInlineCashDiscount      = localStorage.getItem('enableInlineCashDiscount') === 'true';
        const enableInlineCashDiscountAmount= localStorage.getItem('enableInlineCashDiscountAmount') === 'true';
        const enableExtraDiscount           = localStorage.getItem('enableExtraDiscount') === 'true';
        const enableTradeOffer              = localStorage.getItem('enableTradeOffer') === 'true';
        const enableTradeOfferAmount        = localStorage.getItem('enableTradeOfferAmount') === 'true';
        const enableFOC                     = localStorage.getItem('enableFOC') === 'true';
        const enableTaxation                = localStorage.getItem('enableTaxation') === 'true';
        const enableInvoiceCashDiscount     = localStorage.getItem('enableInvoiceCashDiscount') === 'true';
        const enableInvoiceCashDiscountAmount = localStorage.getItem('enableInvoiceCashDiscountAmount') === 'true';
        const enableShippingFees            = localStorage.getItem('enableShippingFees') === 'true';

        // Hide columns by data-col attribute in both thead and tfoot
        function setColVisible(col, visible) {
            document.querySelectorAll(`[data-col="${col}"]`).forEach(el => {
                el.style.display = visible ? '' : 'none';
            });
        }

        setColVisible('size',     enableSize);
        setColVisible('disc_pct', enableInlineCashDiscount);
        setColVisible('disc_amt', enableInlineCashDiscountAmount);
        setColVisible('extra_disc_pct', enableExtraDiscount);
        setColVisible('to_pct',   enableTradeOffer);
        setColVisible('to_amt',   enableTradeOfferAmount);
        setColVisible('tax_pct',  enableTaxation);
        setColVisible('tax_amt',  enableTaxation);
        setColVisible('foc',      enableFOC);

        async function loadData() {
            try {
                const [userRes, invoiceRes] = await Promise.all([
                    fetch('../../../../server/api/purchase/purchase_invoice/get-user.php'),
                    fetch(`../../../../server/api/purchase/purchase_invoice/purchase-edit.php?id=${invoiceId}`)
                ]);
                const userData    = await userRes.json();
                const invoiceData = await invoiceRes.json();

                if (userData.success) {
                    document.getElementById('generatedBy').textContent = userData.user.full_name;
                }
                if (invoiceData.success) {
                    populateCompanyData(invoiceData.invoice);
                    populateInvoiceData(invoiceData.invoice, invoiceData.items, invoiceData.invoice_taxes || [], invoiceData.children || []);
                } else {
                    alert('Error loading invoice: ' + invoiceData.message);
                }
            } catch (e) {
                alert('Error loading data: ' + e.message);
            }
        }

        function populateCompanyData(invoice) {
            document.getElementById('companyName').textContent = invoice.company_name || invoice.legal_name || 'Company Name';
            document.getElementById('companyAddress').textContent = invoice.company_address || '';
            document.getElementById('companyCityState').textContent = `${invoice.company_city || ''}, ${invoice.company_state || ''} ${invoice.company_zipcode || ''}`.trim();
            document.getElementById('companyPhone').textContent = invoice.company_phone ? `Phone: ${invoice.company_phone}` : '';
            document.getElementById('companyEmail').textContent = invoice.company_email ? `Email: ${invoice.company_email}` : '';
            if (invoice.logo_url) {
                const logoImg = document.createElement('img');
                logoImg.src = `../../../assets/uploads/company_logo/${invoice.logo_url}`;
                logoImg.alt = 'Company Logo';
                logoImg.style.maxWidth = '120px';
                logoImg.style.maxHeight = '80px';
                document.getElementById('companyLogoWrapper').appendChild(logoImg);
            }
        }

        function populateInvoiceData(invoice, items, invoiceTaxes, children = []) {
            document.getElementById('invoiceNo').textContent   = invoice.bill_no;
            document.getElementById('invoiceDate').textContent = new Date(invoice.purchase_date).toLocaleDateString();
            document.getElementById('supplierName').textContent = `${invoice.supplier_code} - ${invoice.supplier_name}`;
            document.getElementById('subAccountName').textContent    = invoice.sub_account_name || '-';
            document.getElementById('supplierInvoiceNo').textContent = invoice.supplier_invoice_no || '-';
            document.getElementById('supplierInvoiceDate').textContent = invoice.supplier_invoice_date ? new Date(invoice.supplier_invoice_date).toLocaleDateString() : '-';
            document.getElementById('biltyNo').textContent        = invoice.bilty_no || '-';
            document.getElementById('transportName').textContent  = invoice.transport_name || '-';
            document.getElementById('previousBalance').textContent = invoice.previous_balance || '-';
            const branchText = invoice.parent_branch_name
                ? `${invoice.branch_name} (${invoice.branch_type}) - Parent: ${invoice.parent_branch_name}`
                : `${invoice.branch_name} (${invoice.branch_type})`;
            document.getElementById('branchName').textContent  = branchText;
            document.getElementById('projectName').textContent = invoice.project_name ? `${invoice.project_code} - ${invoice.project_name}` : '-';
            document.getElementById('currency').textContent    = invoice.currency_name;
            document.getElementById('remarks').textContent     = invoice.remarks || '-';

            const currencySymbol = invoice.currency_symbol || '';
            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';

            let totalUnitPrice = 0, totalGross = 0, totalDisc = 0, totalTO = 0, totalGST = 0, totalFOC = 0, totalNet = 0;

            items.forEach((item, index) => {
                const unitMap = {};
                item.unit_entries.forEach(e => {
                    const qty = parseFloat(e.quantity);
                    if (qty > 0) {
                        const u = e.uom_name || 'Unit';
                        unitMap[u] = (unitMap[u] || 0) + qty;
                    }
                });
                const unitQtyDisplay = Object.entries(unitMap).map(([u, q]) => `${q.toFixed(3)} ${u}`).join(', ') || '-';

                // Product Variants (see "Select Product Variants" on the invoice form) are
                // saved as child rows against this parent row — they carry no amount of
                // their own (the parent's gross/net already covers the full quantity), so
                // they're shown here as a breakdown note under the parent product rather
                // than as separate priced line items, to avoid double-counting totals.
                const itemVariants = children.filter(c => c.parent_row_id == item.id);
                const variantsHtml = itemVariants.length > 0
                    ? `<div style="margin-top: 5px; padding-left: 8px; border-left: 2px solid #ddd;">
                        <div style="font-size: 9px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Variants</div>
                        ${itemVariants.map(c => `
                            <div style="display: flex; justify-content: space-between; gap: 10px; font-size: 10px; padding: 1px 0;">
                                <span style="font-weight: 700; color: #333;">${c.product_name}</span>
                                <span style="color: #777; white-space: nowrap;">Qty: ${parseFloat(c.quantity).toFixed(0)} &bull; ${currencySymbol} ${parseFloat(c.purchase_price).toFixed(3)}</span>
                            </div>
                        `).join('')}
                    </div>`
                    : '';

                const row = tbody.insertRow();
                row.innerHTML = `
                    <td class="text-center">${index + 1}</td>
                    <td><span style="font-weight: 700;">${item.product_name}</span>${variantsHtml}</td>
                    <td data-col="size" style="display:${enableSize ? '' : 'none'}">${item.size || '-'}</td>
                    <td>${unitQtyDisplay}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.purchase_price).toFixed(3)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.gross_amount).toFixed(3)}</td>
                    <td class="text-right" data-col="disc_pct" style="display:${enableInlineCashDiscount ? '' : 'none'}">${parseFloat(item.discount_percent || 0).toFixed(3)}%</td>
                    <td class="text-right" data-col="disc_amt" style="display:${enableInlineCashDiscountAmount ? '' : 'none'}">${currencySymbol} ${parseFloat(item.discount_amount || 0).toFixed(3)}</td>
                    <td class="text-right" data-col="extra_disc_pct" style="display:${enableExtraDiscount ? '' : 'none'}">${parseFloat(item.extra_discount_percent || 0).toFixed(3)}%</td>
                    <td class="text-right" data-col="to_pct" style="display:${enableTradeOffer ? '' : 'none'}">${parseFloat(item.trade_offer_percent || 0).toFixed(3)}%</td>
                    <td class="text-right" data-col="to_amt" style="display:${enableTradeOfferAmount ? '' : 'none'}">${currencySymbol} ${parseFloat(item.trade_offer_amount || 0).toFixed(3)}</td>
                    <td class="text-right" data-col="tax_pct" style="display:${enableTaxation ? '' : 'none'}">${parseFloat(item.tax_percent || 0).toFixed(3)}%</td>
                    <td class="text-right" data-col="tax_amt" style="display:${enableTaxation ? '' : 'none'}">${currencySymbol} ${parseFloat(item.tax_amount || 0).toFixed(3)}</td>
                    <td class="text-right" data-col="foc" style="display:${enableFOC ? '' : 'none'}">${parseFloat(item.foc_quantity || 0).toFixed(3)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.net_amount).toFixed(3)}</td>
                `;

                totalUnitPrice += parseFloat(item.purchase_price);
                totalGross     += parseFloat(item.gross_amount);
                totalDisc      += parseFloat(item.discount_amount || 0);
                totalTO        += parseFloat(item.trade_offer_amount || 0);
                totalGST       += parseFloat(item.tax_amount || 0);
                totalFOC       += parseFloat(item.foc_quantity || 0);
                totalNet       += parseFloat(item.net_amount);
            });

            document.getElementById('totalUnitPrice').textContent           = `${currencySymbol} ${totalUnitPrice.toFixed(3)}`;
            document.getElementById('totalGrossAmount').textContent         = `${currencySymbol} ${totalGross.toFixed(3)}`;
            document.getElementById('totalDiscountAmountItems').textContent = `${currencySymbol} ${totalDisc.toFixed(3)}`;
            document.getElementById('totalTradeOfferAmountItems').textContent = `${currencySymbol} ${totalTO.toFixed(3)}`;
            document.getElementById('totalGSTAmountItems').textContent      = `${currencySymbol} ${totalGST.toFixed(3)}`;
            document.getElementById('totalFOCQty').textContent              = totalFOC.toFixed(3);
            document.getElementById('totalNetAmountItems').textContent      = `${currencySymbol} ${totalNet.toFixed(3)}`;

            // Totals section
            document.getElementById('totalBill').textContent = `${currencySymbol} ${parseFloat(invoice.total_bill).toFixed(3)}`;

            if (enableInvoiceCashDiscount && parseFloat(invoice.total_discount_percent) > 0) {
                document.getElementById('discountPercentRow').style.display = '';
                document.getElementById('discountPercent').textContent = parseFloat(invoice.total_discount_percent).toFixed(3) + '%';
            }
            if (enableInvoiceCashDiscountAmount && parseFloat(invoice.total_discount_amount) > 0) {
                document.getElementById('discountAmountRow').style.display = '';
                document.getElementById('discountAmount').textContent = `${currencySymbol} ${parseFloat(invoice.total_discount_amount).toFixed(3)}`;
            }
            if (enableShippingFees && parseFloat(invoice.shipping_fees || 0) > 0) {
                const sym = (invoice.shipping_fees_type || 'add') === 'add' ? '+' : '-';
                document.getElementById('shippingFeesRow').style.display = '';
                document.getElementById('shippingFeesLabel').textContent = `Shipping Fees (${sym}):`;
                document.getElementById('shippingFees').textContent = `${currencySymbol} ${parseFloat(invoice.shipping_fees).toFixed(3)}`;
            }

            // Invoice-level taxes
            const anchor = document.getElementById('invoiceTaxesAnchor');
            document.querySelectorAll('.dynamic-tax-row').forEach(r => r.remove());
            if (invoiceTaxes && invoiceTaxes.length > 0) {
                invoiceTaxes.forEach(tax => {
                    const taxAmt = parseFloat(tax.tax_amount || 0);
                    if (taxAmt === 0) return;
                    const tr = document.createElement('tr');
                    tr.className = 'dynamic-tax-row';
                    tr.innerHTML = `<td>${tax.tax_name} (${parseFloat(tax.rate_percentage).toFixed(3)}%):</td><td class="text-right">${currencySymbol} ${taxAmt.toFixed(3)}</td>`;
                    anchor.parentNode.insertBefore(tr, anchor);
                });
            }

            document.getElementById('netAmount').textContent = `${currencySymbol} ${parseFloat(invoice.net_amount).toFixed(3)}`;

            // Amount in words
            const currencyUnitMap = {
                'USD':['Dollars','Cents'],'EUR':['Euros','Cents'],'GBP':['Pounds','Pence'],
                'PKR':['Rupees','Paisa'],'INR':['Rupees','Paisa'],'AED':['Dirhams','Fils'],
                'SAR':['Riyals','Halalah'],'OMR':['Rials','Baisa'],'QAR':['Riyals','Dirhams'],
                'KWD':['Dinars','Fils'],'BHD':['Dinars','Fils'],'JPY':['Yen','Sen'],
                'CNY':['Yuan','Fen'],'CAD':['Dollars','Cents'],'AUD':['Dollars','Cents'],
                'CHF':['Francs','Rappen'],'TRY':['Lira','Kurus'],'BDT':['Taka','Paisa'],
                'MYR':['Ringgit','Sen'],'SGD':['Dollars','Cents'],'ZAR':['Rand','Cents'],
                'NGN':['Naira','Kobo'],'EGP':['Pounds','Piastres'],'NPR':['Rupees','Paisa'],
            };
            const code = (invoice.currency_code || 'PKR').toUpperCase();
            const [maj, min] = currencyUnitMap[code] || ['Units','Subunits'];
            const netMajor = Math.floor(parseFloat(invoice.net_amount));
            const netMinor = Math.round((parseFloat(invoice.net_amount) % 1) * 100);
            let words = `${code} ${numberToWords(netMajor)} ${maj}`;
            if (netMinor > 0) words += ` and ${numberToWords(netMinor)} ${min}`;
            words += ' Only';
            document.getElementById('amountInWords').textContent = words;

            // Hide info rows with no data
            document.querySelectorAll('.supplier-info p, .invoice-info p').forEach(p => {
                const span = p.querySelector('span');
                if (span && (!span.textContent.trim() || span.textContent.trim() === '-')) p.style.display = 'none';
            });

            // Auto-hide table columns where all values are zero/empty
            hideEmptyTableColumns();

            window.dispatchEvent(new Event('invoiceLoaded'));
        }

        function hideEmptyTableColumns() {
            const cols = ['disc_pct','disc_amt','extra_disc_pct','to_pct','to_amt','tax_pct','tax_amt','foc','size'];
            const tbody = document.getElementById('itemsTableBody');
            const rows = Array.from(tbody.rows);

            cols.forEach(col => {
                const headerEl = document.querySelector(`.items-table thead [data-col="${col}"]`);
                if (!headerEl || headerEl.style.display === 'none') return;

                const bodyCells = rows.map(r => r.querySelector(`[data-col="${col}"]`)).filter(Boolean);
                if (!bodyCells.length) return;

                const allEmpty = bodyCells.every(td => {
                    const t = td.textContent.trim();
                    return !t || t === '-' || t === '0.000' || t === '0.000%' || /^[^\d]*\s*0\.000$/.test(t);
                });

                if (allEmpty) {
                    setColVisible(col, false);
                }
            });
        }

        function numberToWords(num) {
            const ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine'];
            const teens = ['Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
            const tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
            const thousands = ['','Thousand','Million','Billion'];
            if (num === 0) return 'Zero';
            function convertHundreds(n) {
                let r = '';
                if (n >= 100) { r += ones[Math.floor(n/100)] + ' Hundred '; n %= 100; }
                if (n >= 20)  { r += tens[Math.floor(n/10)] + ' '; n %= 10; }
                else if (n >= 10) { r += teens[n-10] + ' '; n = 0; }
                if (n > 0) r += ones[n] + ' ';
                return r;
            }
            let result = '', ti = 0;
            while (num > 0) {
                if (num % 1000 !== 0) result = convertHundreds(num % 1000) + thousands[ti] + ' ' + result;
                num = Math.floor(num / 1000); ti++;
            }
            return result.trim();
        }

        async function downloadAsPdf() {
            const btn = document.getElementById('downloadPdfBtn');
            btn.disabled = true;
            btn.textContent = 'Generating...';
            document.querySelectorAll('.no-print').forEach(el => el.style.setProperty('display', 'none', 'important'));
            const invoiceNo = document.getElementById('invoiceNo').textContent || 'Invoice';
            await html2pdf().set({
                margin: 8,
                filename: `Invoice-${invoiceNo}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, allowTaint: true, logging: false },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            }).from(document.querySelector('.invoice-container')).save();
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

        document.getElementById('generatedOn').textContent = new Date().toLocaleString();
        loadData();
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</body>
</html>
