<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Invoice - Print 2</title>
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
            grid-template-columns: 90px 1fr;
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
            color: #b30000;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .company-block p { font-size: 10.5px; color: #333; }
        .reg-numbers { display: none; }
        .reg-numbers div span.lbl { font-weight: bold; }

        /* Hidden meta-grid rows (Print 2 only): visibility:hidden (not display:none)
           so the 3-column grid cell is still reserved — removing them outright would
           reflow every field after into the wrong row/column. */
        .meta-grid .row.meta-hidden { visibility: hidden; }

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
        .meta-grid .filer-badge {
            grid-column: 3;
            text-align: right;
            font-weight: bold;
            font-size: 11px;
            color: #b30000;
        }

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

        .group-header-row td {
            background: #e8e8e8;
            font-weight: bold;
            font-size: 10.5px;
            border: 1px solid #999;
        }
        .group-total-row td {
            font-weight: bold;
            background: #f7f7f7;
        }
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
        .summary-box .left-col { font-weight: bold; color: #b30000; }
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
            color: #b30000;
            border-top: 1px dashed #999;
            padding-top: 4px;
            margin-top: 4px;
        }
        .page-info { font-size: 10px; color: #666; grid-column: 1; }

        /* ===== WARRANTY / FOOTER ===== */
        .warranty-section {
            margin-top: 18px;
            font-size: 9.5px;
            color: #333;
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }
        .warranty-section .text-block { flex: 1; }
        .warranty-section .sign-block { width: 180px; text-align: center; }
        .warranty-section .sign-block img { max-width: 160px; max-height: 50px; }
        .warranty-section .company-sign-name {
            font-weight: bold; font-size: 10px; margin-top: 4px; color: #b30000;
        }

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

        <div class="invoice-title-bar">Estimate</div>

        <!-- Meta info -->
        <div class="meta-grid">
            <div class="row"><span class="lbl">Invoice #:</span> <span id="invoiceNo">Loading...</span></div>
            <div class="row"><span class="lbl">Invoice Date:</span> <span id="invoiceDate">Loading...</span></div>
            <div class="filer-badge" id="filerBadge"></div>

            <div class="row"><span class="lbl">Sale Order #:</span> <span id="saleOrderNo">-</span></div>
            <div class="row"><span class="lbl">Customer:</span> <span id="customerName">Loading...</span></div>
            <div class="row"><span class="lbl">License #:</span> <span id="customerLicense">-</span></div>

            <div class="row"><span class="lbl">Due Date:</span> <span id="dueDateVal">-</span></div>
            <div class="row"><span class="lbl">Region:</span> <span id="customerArea">-</span></div>
            <div class="row meta-hidden"><span class="lbl">NTN #:</span> <span id="customerNtn">-</span></div>

            <div class="row meta-hidden"><span class="lbl">Booking Man:</span> <span id="supplierMan">-</span></div>
            <div class="row"><span class="lbl">Address:</span> <span id="customerAddress">Loading...</span></div>
            <div class="row meta-hidden"><span class="lbl">GST #:</span> <span id="customerGst">-</span></div>

            <div class="row meta-hidden"><span class="lbl">Delivery Man:</span> <span id="salesOfficer">-</span></div>
            <div class="row"><span class="lbl">Phone:</span> <span id="customerPhone">-</span></div>
            <div class="row meta-hidden"><span class="lbl">CNIC #:</span> <span id="customerCnic">-</span></div>

            <div class="row"><span class="lbl">Remarks:</span> <span id="remarks">-</span></div>
            <div class="row"><span class="lbl">Ship To:</span> <span id="shipTo">-</span></div>
            <div class="row"><span class="lbl">User:</span> <span id="generatedBy">-</span></div>

            <div class="row"></div>
            <div class="row"></div>
            <div class="row"><span class="lbl">Gen. Date:</span> <span id="generatedOn"></span></div>
        </div>

        <!-- Items table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:3%;">Sr.</th>
                    <th style="width:18%; text-align:left;">Item Name</th>
                    <th style="width:7%;">Batch No.</th>
                    <th style="width:7%;">Expiry Date</th>
                    <th style="width:6%;">Quantity</th>
                    <th style="width:6%;">Bonus</th>
                    <th style="width:6%;">Rate</th>
                    <th style="width:8%;">Gross</th>
                    <th style="width:5%;">Disc %</th>
                    <th style="width:7%;">Disc Amount</th>
                    <th style="width:6%;">Extra Disc</th>
                    <th style="width:6%;">Sale Tax</th>
                    <th style="width:6%;">Add Tax</th>
                    <th style="width:8%;">Net Amount</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <tr><td colspan="14" class="text-center">Loading items...</td></tr>
            </tbody>
        </table>

        <!-- Summary box -->
        <div class="summary-box">
            <div class="page-info" id="pageInfo">Page 1 of 1</div>
            <div></div>

            <div class="left-col">This Page Total:</div>
            <div class="right-col" id="thisPageTotal">0.00</div>

            <div class="left-col">Previous Pages Total:</div>
            <div class="right-col" id="prevPagesTotal">----------------</div>

            <div class="left-col">Gross Amount:</div>
            <div class="right-col" id="summaryGrossAmount">0.00</div>

            <div class="left-col">Discount Amount:</div>
            <div class="right-col" id="summaryDiscountAmount">0.00</div>

            <div class="left-col" id="whTaxLabel">Advance WHTax:</div>
            <div class="right-col" id="summaryWhTax">0.00</div>

            <div class="net-total-row">
                <span>Net Total:</span>
                <span class="val" id="summaryNetTotal">0.00</span>
            </div>

            <div class="amount-words-row" id="amountInWords">--</div>
        </div>

        <!-- Warranty / signature -->
        <div class="warranty-section">
            <div class="text-block">
                <p id="warrantyText" style="margin-bottom:6px;"></p>
                <p><strong>Note:</strong></p>
                <p>1 - This warranty does not apply to unani, homeopathic, bio-chemic, herbal, nutraceutical and general items including syringes, medical disposables if any mentioned in this invoice.</p>
                <p>2 - For Near Expiry Items we must be informed 6 months prior to expiry.</p>
                <p>3 - Ensure that you have received the stock according to printed quantities and values.</p>
                <p>4 - In case of any discrepancy, please inform us immediately otherwise we don't accept any responsibility afterward.</p>
            </div>
            <div class="sign-block">
                <div id="signatureImgWrap"></div>
                <div class="company-sign-name" id="companySignName"></div>
            </div>
        </div>

        <div class="print-footer">
            <div>Software Develop By: <span id="footerSoftware">Your Company</span></div>
            <div id="qrCodeSection" style="display:none;"><div id="qrCode"></div></div>
            <div>Printed By: <span id="printedByFooter"></span> | <span id="printedOnFooter"></span></div>
        </div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
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

        async function loadData() {
            try {
                const [userResponse, invoiceResponse] = await Promise.all([
                    fetch('../../../../server/api/sale/pos_invoice/get-user.php'),
                    fetch(`../../../../server/api/sale/pos_invoice/pos-edit.php?id=${invoiceId}`)
                ]);
                const userData = await userResponse.json();
                const invoiceData = await invoiceResponse.json();

                if (userData.success) {
                    document.getElementById('generatedBy').textContent = userData.user.full_name;
                    document.getElementById('printedByFooter').textContent = userData.user.full_name;
                }

                if (invoiceData.success) {
                    let companyData;
                    if (invoiceData.invoice.company_id) {
                        const r = await fetch(`../../../../server/api/sale/pos_invoice/get-company-by-id.php?id=${invoiceData.invoice.company_id}`);
                        companyData = await r.json();
                    } else {
                        const r = await fetch('../../../../server/api/sale/pos_invoice/get-company.php');
                        companyData = await r.json();
                    }
                    if (companyData.success) populateCompanyData(companyData.company);
                    populateInvoiceData(invoiceData.invoice, invoiceData.items);
                } else {
                    alert('Error loading invoice: ' + invoiceData.message);
                }
            } catch (error) {
                alert('Error loading data: ' + error.message);
            }
        }

        function populateCompanyData(company) {
            if (company.logo_url) {
                const img = document.createElement('img');
                img.src = `../../../assets/uploads/company_logo/${company.logo_url}`;
                document.getElementById('companyLogoWrapper').appendChild(img);
            }
            document.getElementById('companyName').textContent = company.company_name || company.legal_name || 'Company Name';
            const addrParts = [company.address, company.city, company.state].filter(Boolean).join(', ');
            document.getElementById('companyAddress').textContent = addrParts;
            document.getElementById('companyPhone').textContent = company.phone ? `Phone# ${company.phone}` : '';
            document.getElementById('companyEmail').textContent = company.email ? `E-Mail: ${company.email}` : '';
            // These fields may not exist on your companies table yet - falls back to '-' if missing
            document.getElementById('companyStn').textContent = company.stn_number || '-';
            document.getElementById('companyNtn').textContent = company.ntn_number || '-';
            document.getElementById('companyNic').textContent = company.nic_number || '-';
            document.getElementById('companySignName').textContent = company.company_name || '';
            document.getElementById('footerSoftware').textContent = company.company_name || '';
        }

        function fmt(n) {
            return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function fmtDate(d) {
            if (!d) return '-';
            const dt = new Date(d);
            if (isNaN(dt)) return '-';
            return dt.toLocaleDateString('en-GB').replace(/\//g, '/');
        }

        function populateInvoiceData(invoice, items) {
            document.getElementById('invoiceNo').textContent = invoice.bill_no;
            document.getElementById('invoiceDate').textContent = fmtDate(invoice.sale_date);
            document.getElementById('customerName').textContent = `${invoice.customer_code || ''} / ${invoice.customer_name}`.replace(/^\s*\/\s*/, '');
            document.getElementById('customerArea').textContent = invoice.area_name || '-';
            document.getElementById('customerAddress').textContent = invoice.customer_address || '-';
            document.getElementById('customerPhone').textContent = invoice.customer_phone || '-';
            document.getElementById('supplierMan').textContent = invoice.supplier_man_name || '-';
            document.getElementById('salesOfficer').textContent = invoice.sales_officer_name || '-';
            document.getElementById('remarks').textContent = invoice.remarks || '-';
            document.getElementById('dueDateVal').textContent = invoice.due_date ? fmtDate(invoice.due_date) : '-';
            document.getElementById('generatedOn').textContent = fmtDate(new Date());
            document.getElementById('printedOnFooter').textContent = new Date().toLocaleString();

            // Customer registration numbers - only shown if your customers table has these columns
            document.getElementById('customerLicense').textContent = invoice.customer_license_no || '-';
            document.getElementById('customerNtn').textContent = invoice.customer_ntn || '-';
            document.getElementById('customerGst').textContent = invoice.customer_gst || '-';
            document.getElementById('customerCnic').textContent = invoice.customer_cnic || '-';
            document.getElementById('shipTo').textContent = invoice.ship_to || '-';
            document.getElementById('saleOrderNo').textContent = invoice.sale_order_no || '-';

            if (invoice.customer_is_filer === 1 || invoice.customer_is_filer === '1') {
                document.getElementById('filerBadge').textContent = 'FILER';
            }

            const currencySymbol = invoice.currency_symbol || '';

            // Group parent items by product
            const itemsByProduct = {};
            items.filter(i => !i.parent_row_id).forEach(item => {
                const key = item.product_id;
                if (!itemsByProduct[key]) {
                    itemsByProduct[key] = { baseItem: item, totalQty: 0 };
                }
                itemsByProduct[key].totalQty += parseFloat(item.quantity);
            });

            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';

            let serialNumber = 0;
            let grpQty = 0, grpBonus = 0, grpGross = 0, grpDiscAmt = 0, grpExtraDisc = 0, grpTax = 0, grpAddTax = 0, grpNet = 0;
            let grandQty = 0, grandBonus = 0, grandGross = 0, grandDiscAmt = 0, grandExtraDisc = 0, grandTax = 0, grandAddTax = 0, grandNet = 0;

            const brandLabel = invoice.brand_code
                ? `${invoice.brand_code} / ${(invoice.brand_name || '').toUpperCase()}`
                : (invoice.brand_name ? invoice.brand_name.toUpperCase() : null);

            if (brandLabel) {
                const grpRow = tbody.insertRow();
                grpRow.className = 'group-header-row';
                grpRow.innerHTML = `<td colspan="14" style="text-align:left;">${brandLabel}</td>`;
            }

            Object.values(itemsByProduct).forEach(group => {
                serialNumber++;
                const item = group.baseItem;
                const qty = group.totalQty;
                const bonus = parseFloat(item.foc_quantity || 0);
                const rate = parseFloat(item.sale_price || 0);
                const gross = parseFloat(item.gross_amount || 0);
                const discPct = parseFloat(item.discount_percent || 0);
                const discAmt = parseFloat(item.discount_amount || 0);
                const extraDisc = parseFloat(item.extra_discount_amount || 0);
                const tax = parseFloat(item.tax_amount || 0);
                // "Add Tax" = per-item GST amount, separate from Sale Tax (previously hardcoded '-')
                const addTax = parseFloat(item.gst_amount || 0);
                const net = parseFloat(item.net_amount || 0);

                const row = tbody.insertRow();
                row.innerHTML = `
                    <td class="text-center">${serialNumber}</td>
                    <td style="text-align:left;">${item.product_code ? item.product_code + '/' : ''}${item.product_name}</td>
                    <td class="text-center">${item.batch_no || '-'}</td>
                    <td class="text-center">${item.expiry_date ? fmtDate(item.expiry_date) : '-'}</td>
                    <td class="text-right">${qty ? qty.toFixed(0) : '-'}</td>
                    <td class="text-right">${bonus ? bonus.toFixed(0) : '-'}</td>
                    <td class="text-right">${rate ? fmt(rate) : '-'}</td>
                    <td class="text-right">${gross ? fmt(gross) : '-'}</td>
                    <td class="text-right">${discPct ? discPct.toFixed(2) : '0.00'}</td>
                    <td class="text-right">${discAmt ? fmt(discAmt) : '-'}</td>
                    <td class="text-right">${extraDisc ? fmt(extraDisc) : '-'}</td>
                    <td class="text-right">${tax ? fmt(tax) : '0'}</td>
                    <td class="text-right">${addTax ? fmt(addTax) : '-'}</td>
                    <td class="text-right">${fmt(net)}</td>
                `;

                grpQty += qty; grpBonus += bonus; grpGross += gross; grpDiscAmt += discAmt; grpExtraDisc += extraDisc; grpTax += tax; grpAddTax += addTax; grpNet += net;
                grandQty += qty; grandBonus += bonus; grandGross += gross; grandDiscAmt += discAmt; grandExtraDisc += extraDisc; grandTax += tax; grandAddTax += addTax; grandNet += net;
            });

            if (brandLabel) {
                const subRow = tbody.insertRow();
                subRow.className = 'group-total-row';
                subRow.innerHTML = `
                    <td colspan="2" style="text-align:left;">Total For ${brandLabel} :</td>
                    <td></td><td></td>
                    <td class="text-right">${grpQty.toFixed(0)}</td>
                    <td class="text-right">${grpBonus.toFixed(0)}</td>
                    <td></td>
                    <td class="text-right">${fmt(grpGross)}</td>
                    <td></td>
                    <td class="text-right">${fmt(grpDiscAmt)}</td>
                    <td class="text-right">${fmt(grpExtraDisc)}</td>
                    <td class="text-right">${fmt(grpTax)}</td>
                    <td class="text-right">${fmt(grpAddTax)}</td>
                    <td class="text-right">${fmt(grpNet)}</td>
                `;
            }

            const grandRow = tbody.insertRow();
            grandRow.className = 'grand-total-row';
            grandRow.innerHTML = `
                <td colspan="2" style="text-align:left;">Total Items: ${serialNumber} &nbsp;&nbsp; Total For Invoice ${invoice.bill_no} :</td>
                <td></td><td></td>
                <td class="text-right">${grandQty.toFixed(0)}</td>
                <td class="text-right">${grandBonus.toFixed(0)}</td>
                <td></td>
                <td class="text-right">${fmt(grandGross)}</td>
                <td></td>
                <td class="text-right">${fmt(grandDiscAmt)}</td>
                <td class="text-right">${fmt(grandExtraDisc)}</td>
                <td class="text-right">${fmt(grandTax)}</td>
                <td class="text-right">${fmt(grandAddTax)}</td>
                <td class="text-right">${fmt(grandNet)}</td>
            `;

            // ===== Summary box =====
            const grossAmount = grandGross;
            const discountAmount = grandDiscAmt; // item-level discount total (matches sample's "Discount Amount")
            const whTaxAmount = parseFloat(invoice.withholding_tax_amount || 0);
            const whTaxPercent = parseFloat(invoice.withholding_tax_percent || 0);

            // NOTE: Sample invoice ADDS advance withholding tax to net total
            // (Advance Income Tax collected FROM buyer under Sec 236H).
            // This differs from this system's internal accounting, which currently
            // SUBTRACTS withholding tax from net receivable. Confirm with your
            // accountant which treatment is correct for your business before relying
            // on this for tax filing - this print view intentionally mirrors the
            // sample's "add" behavior for display purposes only.
            const afterDiscount = grossAmount - discountAmount;
            const netTotalDisplay = afterDiscount + whTaxAmount;

            document.getElementById('thisPageTotal').textContent = `${currencySymbol} ${fmt(grandNet)}`;
            document.getElementById('summaryGrossAmount').textContent = `${currencySymbol} ${fmt(grossAmount)}`;
            document.getElementById('summaryDiscountAmount').textContent = `${currencySymbol} ${fmt(discountAmount)}`;
            document.getElementById('whTaxLabel').textContent = whTaxPercent
                ? `Advance WHTax @${whTaxPercent}%:`
                : 'Advance WHTax:';
            document.getElementById('summaryWhTax').textContent = `${currencySymbol} ${fmt(whTaxAmount)}`;
            document.getElementById('summaryNetTotal').textContent = `${currencySymbol} ${fmt(netTotalDisplay)}`;

            // Amount in words
            const currencyUnitMap = {
                'USD': ['Dollars', 'Cents'], 'PKR': ['Rupees', 'Paisa'], 'INR': ['Rupees', 'Paisa']
            };
            const invCurrCode = (invoice.currency_code || 'PKR').toUpperCase();
            const [majorUnit] = currencyUnitMap[invCurrCode] || ['Units'];
            const major = Math.round(netTotalDisplay);
            document.getElementById('amountInWords').textContent =
                `--${majorUnit} ${numberToWords(major)} Only.`;

            document.getElementById('warrantyText').textContent =
                `I ${invoice.generated_by_full_name || document.getElementById('generatedBy').textContent} being a person resident in Pakistan carrying on business at ` +
                `${invoice.company_address || ''}, under the name of ${invoice.company_name_for_warranty || document.getElementById('companyName').textContent} ` +
                `a distributor / wholesaler / authorized agent of the drugs on this invoice, do hereby give this warranty ` +
                `that the drugs invoiced as sold by us, do not contravene in any way the provision of Section 23 of the Drug Act 1976.`;

            // QR code (optional, kept from previous version)
            setTimeout(() => {
                const enablePrintQRCode = localStorage.getItem('enablePrintQRCode') === 'true';
                const qrCodeDiv = document.getElementById('qrCode');
                if (enablePrintQRCode && typeof QRCode !== 'undefined' && qrCodeDiv && !qrCodeDiv.hasChildNodes()) {
                    document.getElementById('qrCodeSection').style.display = 'block';
                    const token = CryptoJS.MD5(invoice.bill_no + 'LEDGERONE_SECRET_KEY').toString().substring(0, 16);
                    const verifyUrl = `${window.location.origin}/ledgerone_erp/client/pages/sale/pos_invoice/verify-invoice.php?invoice=${invoice.bill_no}&token=${token}`;
                    new QRCode(qrCodeDiv, { text: verifyUrl, width: 70, height: 70, correctLevel: QRCode.CorrectLevel.H });
                }
            }, 400);

            window.dispatchEvent(new Event('invoiceLoaded'));
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
                filename: `Invoice-${invoiceNo}.pdf`,
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

        loadData();
    </script>
</body>

</html>