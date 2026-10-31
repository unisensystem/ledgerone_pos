<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Thermal Print - Sale Invoice</title>
    <style>
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
               font-family: math;
            font-size: 14px;
            line-height: 1.5;
            color: #000;
            background: #f5f5f5;
            padding: 10px;
            font-weight: bold;
        }
        
        .thermal-receipt {
            width: 80mm;
            max-width: 302px;
            margin: 0 auto;
            background: white;
            padding: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .bold {
            font-weight: bold;
        }
        
        .company-logo {
            max-width: 80px;
            max-height: 60px;
            margin: 0 auto 5px;
            display: block;
        }
        
        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .company-info {
            font-size: 12px;
            margin-bottom: 2px;
            font-weight: bold;
        }
        
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        
        .invoice-header {
            font-size: 14px;
            font-weight: bold;
            margin: 8px 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .items-table {
            width: 100%;
            margin: 8px 0;
            font-size: 12px;
            font-weight: bold;
        }
        
        .items-table th {
            border-bottom: 1px solid #000;
            padding: 4px 0;
            text-align: center;
            font-weight: bold;
        }
        
        .items-table td {
            padding: 4px 0;
            border-bottom: 1px solid #000;
            font-weight: bold;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
            font-size: 13px;
            font-weight: bold;
        }
        
        .total-row.grand-total {
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }
        
        .footer {
            font-size: 11px;
            margin-top: 10px;
            font-weight: bold;
        }
        
        .print-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        
        .print-btn:hover {
            background: #0056b3;
        }
        
        .note-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        
        .note-btn:hover {
            background: #218838;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
            min-height: 300px;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
        }
        
        .modal-body {
            flex: 1;
            margin-bottom: 20px;
        }
        
        .note-textarea {
            width: 100%;
            height: 150px;
            padding: 10px;
            font-size: 14px;
            font-family: 'Courier New', monospace;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: vertical;
        }
        
        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .modal-footer button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-save {
            background-color: #28a745;
            color: white;
        }
        
        .btn-save:hover {
            background-color: #218838;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        
        .btn-clear {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-clear:hover {
            background-color: #c82333;
        }
        
        .notes-section {
            margin-top: 5px;
            font-size: 10px;
            font-weight: bold;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.3;
            display: none;
        }
        
        .notes-section.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="note-btn" onclick="openBrandNoteModal()">Add Brand Note</button>
        <button class="print-btn" onclick="window.print()">Print Receipt</button>
    </div>
    
    <!-- Brand Note Modal -->
    <div id="brandNoteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Add Brand Note</div>
            <div class="modal-body">
                <textarea id="brandNoteTextarea" class="note-textarea" placeholder="Enter your brand note here..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn-clear" onclick="clearBrandNote()">Clear Brand Note</button>
                <button class="btn-cancel" onclick="closeBrandNoteModal()">Cancel</button>
                <button class="btn-save" onclick="saveBrandNote()">Save Brand Note</button>
            </div>
        </div>
    </div>
    
    <div class="thermal-receipt">
        <div class="text-center">
            <div class="company-name" id="companyName">Loading...</div>
            <div class="company-info" id="companyAddress"></div>
            <div class="company-info" id="companyPhone"></div>
        </div>
        
        <div class="divider"></div>
        
        <div class="text-center invoice-header">SALE INVOICE</div>
        
        <div class="info-row" id="rowInvoiceNo">
            <span>Invoice #:</span>
            <span class="bold" id="invoiceNo">Loading...</span>
        </div>
        <div class="info-row" id="rowDate">
            <span>Date:</span>
            <span id="invoiceDate">Loading...</span>
        </div>
        <div class="info-row" id="rowCustomer">
            <span>Customer:</span>
            <span id="customerName">Loading...</span>
        </div>
        <div class="info-row" id="previousBalanceRow" style="display: none;">
            <span>Prev Balance:</span>
            <span id="previousBalance">0.00</span>
        </div>
        <div class="info-row" id="rowBranch">
            <span>Branch:</span>
            <span id="branchName">Loading...</span>
        </div>
        <div class="info-row" id="supplierManRow" style="display: none;">
            <span>Supplier Man:</span>
            <span id="supplierManName">-</span>
        </div>
        
        <div class="divider"></div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 40%; text-align: left;">Item</th>
                    <th style="width: 12%;" class="text-center">Qty</th>
                    <th style="width: 18%;" class="text-center">Price</th>
                    <th style="width: 15%;" class="text-center disc-col">Disc</th>
                    <th style="width: 15%;" class="text-center">Total</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <tr>
                    <td colspan="5" class="text-center">Loading...</td>
                </tr>
            </tbody>
        </table>

        <div id="invoiceRemarksRow" style="display:none;font-size:11px;margin:6px 0;white-space:pre-wrap;overflow-wrap:anywhere;">
            <strong>Details:</strong> <span id="invoiceRemarks"></span>
        </div>
        
        <div class="divider"></div>
        
        <div class="total-row" id="rowSubtotal">
            <span>Subtotal:</span>
            <span id="totalBill">0.00</span>
        </div>
        <div class="total-row" style="display: none;" id="discountRow">
            <span>Discount:</span>
            <span id="discountAmount">0.00</span>
        </div>
        <div class="total-row" style="display: none;" id="gstRow">
            <span>GST:</span>
            <span id="gstAmount">0.00</span>
        </div>
        <div class="total-row grand-total" id="rowTotal">
            <span>TOTAL:</span>
            <span id="netAmount">0.00</span>
        </div>
        <div class="total-row" id="rowReceived">
            <span>Received:</span>
            <span id="amountPaid">0.00</span>
        </div>
        <div class="total-row" id="rowReturned">
            <span>Returned:</span>
            <span id="amountReturned">0.00</span>
        </div>
        <div class="total-row" id="rowBalance">
            <span>Balance:</span>
            <span id="remainingBalance">0.00</span>
        </div>
        
        <div id="notesSection" class="notes-section">
            <div id="notesContent"></div>
        </div>
        
        <div class="divider"></div>
        
        <div class="footer text-center">
            <div id="rowPaymentMethod">Payment Method: <span id="paymentMethod">-</span></div>
            <div id="rowThankYou" style="margin-top: 5px;">Thank you for your business!</div>
            <div style="margin-top: 8px; font-size: 10px; display: none;">Printed By: <span id="printedBy">-</span></div>
            <div id="rowPrintedDate" style="font-size: 10px;">Printed: <span id="printedDate"></span></div>
            <div id="qrCodeSection" style="margin-top: 8px; display: none;">
                <div id="qrCode" style="display: inline-block;"></div>
            </div>
            <div id="rowSoftwareBy" style="margin-top: 10px; border-top: 1px dashed #000; padding-top: 8px; font-size: 10px; line-height: 1.6;">
                <div style="font-weight: bold;">Software by: UNISEN SYSTEMS</div>
                <div>Contact: +92 346 8918711</div>
                <!-- <div>+92 335 3789981</div> -->
                <!-- <div>Email: support@unisensystems.com</div>
                <div>www.unisensystems.com</div> -->
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <script>
        // pos-add.php (Invoice Settings) saves these keys with a "pos_" prefix so its
        // settings don't clash with med_pos_invoice's own copies of the same keys — this
        // page must read them back the same prefixed way, or toggles made in Invoice
        // Settings silently fail to show up here. Keep this list identical to the
        // `sharedKeys` set in client/assets/js/sale/pos_invoice/pos-add.js.
        (function() {
            const prefix = 'pos_';
            const originalGetItem = localStorage.getItem.bind(localStorage);
            const originalSetItem = localStorage.setItem.bind(localStorage);
            const originalRemoveItem = localStorage.removeItem.bind(localStorage);

            const sharedKeys = new Set([
                'enableTaxation', 'enableExtraDiscountItem',
                'enableBatchNo','enableExpiryDate','enableFOC','enableTradeOfferAmount',
                'enableCashDiscountPercent','enableCashDiscountAmount',
                'enableInvoiceCashDiscountPercent','enableInvoiceCashDiscountAmount',
                'enableExtraDiscount1','enableExtraDiscount2','enableShippingFees',
                'enableAmountPaidPaymentMethod','enablePrintQRCode',
                'defaultScheme','salePriceSetting','productFilteringMode',
                'productDisplayMode','customerDisplayMode',
                'totalsLayout','childDisplayMode','printColumnCustomization',
                'pos_print_preference','showSettingsButtons','compactMode',
                'labelTotalBill','labelDiscountAmount','labelShippingFees','labelNetAmount',
                'labelAmountPaid','labelPaymentMethod','labelRemainingBalance',
                'hidePrintCustomerPhone','hidePrintCustomerEmail','hidePrintCustomerAddress',
                'hidePrintPreviousBalance','hidePrintBranch','hidePrintCurrency',
                'hidePrintSalesOfficer','hidePrintBiltyNo','hidePrintTransport',
                'hidePrintRemarks','hidePrintTotalBill','hidePrintNetAmount',
                'hidePrintPaymentMethod','hidePrintAmountPaid','hidePrintRemainingBalance',
                'hidePrintAmountInWords','hidePrintSignatures','hidePrintGeneratedBy',
                'hidePrintGeneratedOn','pos_shortcuts',
                'hideInvoiceTypeStandard','hideDueDateStandard','hideBrandStandard',
                'hideSalesOfficerStandard','hideSupplierManStandard','hideAreaCityStandard',
                'hideSubAccountStandard','hideSaleOrderStandard','hideCurrencyStandard',
                'hideTransportNameStandard','hideBiltyNoStandard','hideProjectStandard',
                'hideContainerStandard','hideRemarksStandard'
            ]);

            localStorage.getItem = function(key) {
                return originalGetItem(sharedKeys.has(key) ? prefix + key : key);
            };
            localStorage.setItem = function(key, value) {
                return originalSetItem(sharedKeys.has(key) ? prefix + key : key, value);
            };
            localStorage.removeItem = function(key) {
                return originalRemoveItem(sharedKeys.has(key) ? prefix + key : key);
            };
        })();

        // ===================== CUSTOMIZE PRINT (set from counter-invoice.php) =====================
        // 'counterPrintCustomize' is NOT in sharedKeys above, so it is read un-prefixed,
        // exactly the same key counter-invoice.php saves. Value = { key: true/false }, false = hide.
        // Keys must match PRINT_GROUPS in counter-invoice.php.
        const PRINT_MAP = {
            logo:           '.company-logo',
            companyName:    '#companyName',
            companyAddress: '#companyAddress',
            companyPhone:   '#companyPhone',
            invoiceTitle:   '.invoice-header',
            invoiceNo:      '#rowInvoiceNo',
            date:           '#rowDate',
            customer:       '#rowCustomer',
            prevBalance:    '#previousBalanceRow',
            branch:         '#rowBranch',
            supplierMan:    '#supplierManRow',
            discCol:        '.disc-col',
            totalsRow:      '.totals-row',
            subtotal:       '#rowSubtotal',
            discount:       '#discountRow',
            gst:            '#gstRow',
            total:          '#rowTotal',
            received:       '#rowReceived',
            returned:       '#rowReturned',
            balance:        '#rowBalance',
            paymentMethod:  '#rowPaymentMethod',
            thankYou:       '#rowThankYou',
            brandNote:      '#notesSection',
            qr:             '#qrCodeSection',
            printedDate:    '#rowPrintedDate',
            softwareBy:     '#rowSoftwareBy'
        };

        // CSS based hiding: also works for elements that are created later
        // (logo, items table totals row, QR code, brand note).
        (function applyPrintCustomization() {
            let saved = {};
            try { saved = JSON.parse(localStorage.getItem('counterPrintCustomize') || '{}') || {}; } catch (e) { saved = {}; }

            const selectors = [];
            Object.keys(PRINT_MAP).forEach(key => {
                if (saved[key] === false) selectors.push(PRINT_MAP[key]);
            });

            if (selectors.length > 0) {
                const style = document.createElement('style');
                style.id = 'printCustomizeStyle';
                style.textContent = selectors.join(',\n') + ' { display: none !important; }';
                document.head.appendChild(style);
            }
        })();
        // =========================================================================================

        // Apply invoice settings
        const enableCashDiscountAmount = localStorage.getItem('enableCashDiscountAmount') === 'true';
        const enableTradeOfferAmount = localStorage.getItem('enableTradeOfferAmount') === 'true';
        const childDisplayMode = localStorage.getItem('childDisplayMode') || 'separate';
        
        // Hide discount column if both are disabled
        if (!enableCashDiscountAmount && !enableTradeOfferAmount) {
            document.querySelectorAll('.disc-col').forEach(el => el.style.display = 'none');
        }
        
        const urlParams = new URLSearchParams(window.location.search);
        const invoiceId = urlParams.get('id');
        let tenantId = null; // Will be set after loading invoice data
        
        if (!invoiceId) {
            alert('Invoice ID is required');
            window.close();
        }
        
        async function loadData() {
            try {
                const [userResponse, invoiceResponse] = await Promise.all([
                    fetch('../../../../server/api/sale/pos_invoice/get-user.php'),
                    fetch(`../../../../server/api/sale/pos_invoice/pos-edit.php?id=${invoiceId}`)
                ]);
                
                const userData = await userResponse.json();
                const invoiceData = await invoiceResponse.json();
                
                if (userData.success) {
                    document.getElementById('printedBy').textContent = userData.user.full_name;
                }
                
                if (invoiceData.success) {
                    // Load company data if company_id exists
                    if (invoiceData.invoice.company_id) {
                        const companyResponse = await fetch(`../../../../server/api/sale/pos_invoice/get-company-by-id.php?id=${invoiceData.invoice.company_id}`);
                        const companyData = await companyResponse.json();
                        if (companyData.success) {
                            populateCompanyData(companyData.company);
                        }
                    } else {
                        // Fallback to tenant's default company
                        const companyResponse = await fetch('../../../../server/api/sale/pos_invoice/get-company.php');
                        const companyData = await companyResponse.json();
                        if (companyData.success) {
                            populateCompanyData(companyData.company);
                        }
                    }
                    populateInvoiceData(invoiceData.invoice, invoiceData.items);
                    // Set tenant ID after loading invoice
                    tenantId = invoiceData.invoice.tenant_id;
                } else {
                    alert('Error loading invoice: ' + invoiceData.message);
                }
            } catch (error) {
                alert('Error loading data: ' + error.message);
            }
        }
        
        function populateCompanyData(company) {
            // Set company logo only if exists
            if (company.logo_url) {
                const logoImg = document.createElement('img');
                logoImg.src = `../../../assets/uploads/company_logo/${company.logo_url}`;
                logoImg.alt = 'Logo';
                logoImg.className = 'company-logo';
                logoImg.style.display = 'block';
                const textCenter = document.querySelector('.text-center');
                textCenter.insertBefore(logoImg, textCenter.firstChild);
            }
            
            document.getElementById('companyName').textContent = company.company_name || company.legal_name || 'Company Name';
            document.getElementById('companyAddress').textContent = company.address || '';
            document.getElementById('companyPhone').textContent = company.phone ? `Tel: ${company.phone}` : '';
        }
        
        const escHtml = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

        function populateInvoiceData(invoice, items) {
            document.getElementById('invoiceNo').textContent = invoice.bill_no;
            document.getElementById('invoiceDate').textContent = new Date(invoice.sale_date).toLocaleDateString();

            // The POS saves the full item breakdown inside remarks as "Walk-in #3 | 1 Pao nimko @ ... | ...".
            // Only the first part (order type / table no.) is printed in Details; the rest is used below
            // to show each item the way the cashier sold it (qty 1, unit name, unit price).
            const remarkParts = String(invoice.remarks || '').split(' | ');
            const remarkHead = remarkParts[0].trim();
            const remarkLines = remarkParts.slice(1);
            if (remarkHead) {
                document.getElementById('invoiceRemarks').textContent = remarkHead;
                document.getElementById('invoiceRemarksRow').style.display = 'block';
            }
            const usedLines = new Set();
            function lineInfo(item) {
                const name = String(item.product_name || '');
                const info = {name: escHtml(name), note: '', qty: parseFloat(item.quantity).toFixed(2), price: parseFloat(item.sale_price).toFixed(2)};
                const net = parseFloat(item.net_amount) || 0;
                for (let i = 0; i < remarkLines.length; i++) {
                    if (usedLines.has(i)) continue;
                    const line = remarkLines[i], at = line.indexOf(' @ ');
                    if (at < 0) continue;
                    const pre = line.slice(0, at);
                    if (!pre.endsWith(' ' + name)) continue;
                    const dm = pre.slice(0, pre.length - name.length - 1).match(/^(\d+(?:\.\d+)?) (.+)$/);
                    if (!dm || dm[2].indexOf(' + ') >= 0) continue; // mixed "1 ctn + 3 piece" lines print as stored
                    let rest = line.slice(at + 3).replace(/ \[Barcode: [^\]]*\]/, '');
                    const ci = rest.indexOf(': ');
                    const note = ci >= 0 ? rest.slice(ci + 2) : '';
                    const pm = (ci >= 0 ? rest.slice(0, ci) : rest).match(/([\d,]*\.?\d+)\s*\/\s*(.+)$/);
                    if (!pm) continue;
                    const qty = parseFloat(dm[1]), price = parseFloat(pm[1].replace(/,/g, ''));
                    if (Math.abs(qty * price - net) > 0.01 * qty + 0.05) continue; // not this item's line
                    usedLines.add(i);
                    info.name = escHtml(name) + ' (' + escHtml(dm[2].trim()) + ')';
                    info.note = escHtml(note);
                    info.qty = String(+qty.toFixed(3));
                    info.price = price.toFixed(2);
                    return info;
                }
                return info;
            }
            // Walk-in bills are posted to the default "Cash Customer" - don't print a customer line for them
            const isWalkIn = (invoice.customer_name || '').trim().toLowerCase() === 'cash customer';
            document.getElementById('customerName').textContent = isWalkIn ? '' : invoice.customer_name;
            if (isWalkIn) document.getElementById('customerName').parentElement.style.display = 'none';
            
            // Show previous balance only if it exists and is non-zero
            const previousBalance = parseFloat(invoice.previous_balance || 0);
            if (previousBalance !== 0) {
                document.getElementById('previousBalanceRow').style.display = 'flex';
                document.getElementById('previousBalance').textContent = `${invoice.currency_symbol || ''}${previousBalance.toFixed(2)}`;
            }
            
            const branchText = invoice.parent_branch_name ? 
                `${invoice.branch_name} - ${invoice.parent_branch_name}` : 
                invoice.branch_name;
            document.getElementById('branchName').textContent = branchText;
            
            // Show supplier man if exists
            if (invoice.supplier_man_id) {
                document.getElementById('supplierManRow').style.display = 'flex';
                const supplierManName = invoice.supplier_man_name || '-';
                document.getElementById('supplierManName').textContent = supplierManName;
            }
            
            const currencySymbol = invoice.currency_symbol || '';
            
            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';
            
            if (childDisplayMode === 'inline') {
                // Group children with their parents
                const itemsById = {};
                const parentItems = [];

                items.forEach(item => {
                    itemsById[item.id] = item;
                    if (item.parent_row_id === null) {
                        item.children = [];
                        parentItems.push(item);
                    }
                });

                // Attach children to parents
                items.forEach(item => {
                    if (item.parent_row_id !== null) {
                        const parent = itemsById[item.parent_row_id];
                        if (parent && parent.children) {
                            parent.children.push(item);
                        }
                    }
                });

                // Render grouped items
                parentItems.forEach((item) => {
                    const row = tbody.insertRow();
                    const li = lineInfo(item);
                    
                    // Build children text
                    let childrenText = '';
                    if (item.children && item.children.length > 0) {
                        childrenText = '<br><span style="font-size: 10px; color: #666;">(' +
                            item.children.map(c => `${c.product_name}: ${parseFloat(c.quantity).toFixed(2)}`).join(' | ') +
                            ')</span>';
                    }
                    const noteText = li.note ? `<br><span style="font-size: 10px;">${li.note}</span>` : '';
                    
                    const discountAmt = parseFloat(item.discount_amount || 0);
                    const tradeOfferAmt = parseFloat(item.trade_offer_amount || 0);
                    const totalDisc = discountAmt + tradeOfferAmt;
                    
                    const discCell = (!enableCashDiscountAmount && !enableTradeOfferAmount) ? '' : `<td class="text-center disc-col">${totalDisc.toFixed(2)}</td>`;
                    
                    row.innerHTML = `
                        <td>${li.name}${noteText}${childrenText}</td>
                        <td class="text-center">${li.qty}</td>
                        <td class="text-center">${li.price}</td>
                        ${discCell}
                        <td class="text-center">${parseFloat(item.net_amount).toFixed(2)}</td>
                    `;
                });
            } else {
                // Separate rows mode (current)
                items.forEach((item) => {
                    const row = tbody.insertRow();
                    
                    const isChild = item.parent_row_id !== null;
                    const indent = isChild ? '<span style="margin-left: 15px; font-size: 11px;">↳ </span>' : '';
                    const textStyle = isChild ? 'font-size: 11px; color: #666;' : '';
                    const li = isChild ? {name: escHtml(item.product_name), note: '', qty: parseFloat(item.quantity).toFixed(2), price: ''} : lineInfo(item);
                    const noteText = li.note ? `<br><span style="font-size: 10px;">${li.note}</span>` : '';
                    
                    const discountAmt = parseFloat(item.discount_amount || 0);
                    const tradeOfferAmt = parseFloat(item.trade_offer_amount || 0);
                    const totalDisc = discountAmt + tradeOfferAmt;
                    
                    const discCell = (!enableCashDiscountAmount && !enableTradeOfferAmount) ? '' : `<td class="text-center disc-col">${isChild ? '-' : totalDisc.toFixed(2)}</td>`;
                    
                    row.innerHTML = `
                        <td style="${textStyle}">${indent}${li.name}${noteText}</td>
                        <td class="text-center">${li.qty}</td>
                        <td class="text-center">${isChild ? '-' : li.price}</td>
                        ${discCell}
                        <td class="text-center">${isChild ? '-' : parseFloat(item.net_amount).toFixed(2)}</td>
                    `;
                });
            }
            
            // Add totals row
            const totalsRow = tbody.insertRow();
            totalsRow.className = 'totals-row';
            totalsRow.style.borderTop = '2px solid #000';
            totalsRow.style.fontWeight = 'bold';
            
            let totalAmount = 0;
            // Count only parent items (quantities are in mixed units, so no total quantity is printed)
            items.forEach(item => {
                if (!item.parent_row_id) {
                    totalAmount += parseFloat(item.net_amount);
                }
            });
            
            const totalsDiscCell = (!enableCashDiscountAmount && !enableTradeOfferAmount) ? '' : `<td class="text-center disc-col"></td>`;
            
            totalsRow.innerHTML = `
                <td>TOTALS</td>
                <td class="text-center"></td>
                <td class="text-center"></td>
                ${totalsDiscCell}
                <td class="text-center">${totalAmount.toFixed(2)}</td>
            `;
            
            document.getElementById('totalBill').textContent = `${currencySymbol}${parseFloat(invoice.total_bill).toFixed(2)}`;
            
            const discountAmt = parseFloat(invoice.total_discount_amount);
            if (discountAmt > 0) {
                document.getElementById('discountRow').style.display = 'flex';
                document.getElementById('discountAmount').textContent = `${currencySymbol}${discountAmt.toFixed(2)}`;
            }
            
            // Calculate total GST from items
            let totalGst = 0;
            items.forEach(item => {
                totalGst += parseFloat(item.gst_amount || 0);
            });
            if (totalGst > 0) {
                document.getElementById('gstRow').style.display = 'flex';
                document.getElementById('gstAmount').textContent = `${currencySymbol}${totalGst.toFixed(2)}`;
            }
            
            document.getElementById('netAmount').textContent = `${currencySymbol}${parseFloat(invoice.net_amount).toFixed(2)}`;
            
            // invoice.amount_paid is the SUM of Receive Voucher amounts — the net money
            // actually retained against this invoice (change given back is excluded from
            // it, see pos-add.php). "Received" on the receipt should show what the
            // customer physically tendered, so it's reconstructed as netCollected +
            // amountReturned; Balance only compares against the net collected amount.
            const netCollected = parseFloat(invoice.amount_paid) || 0;
            const amountReturned = parseFloat(invoice.amount_returned) || 0;
            const amountPaid = netCollected + amountReturned;
            const remainingBalance = parseFloat(invoice.net_amount) - netCollected;

            document.getElementById('amountPaid').textContent = `${currencySymbol}${amountPaid.toFixed(2)}`;
            document.getElementById('amountReturned').textContent = `${currencySymbol}${amountReturned.toFixed(2)}`;
            document.getElementById('remainingBalance').textContent = `${currencySymbol}${remainingBalance.toFixed(2)}`;
            
            document.getElementById('paymentMethod').textContent = invoice.payment_method || '-';
            
            // Generate QR Code if enabled
            setTimeout(() => {
                const enablePrintQRCode = localStorage.getItem('enablePrintQRCode') === 'true';
                const qrCodeDiv = document.getElementById('qrCode');
                if (enablePrintQRCode && typeof QRCode !== 'undefined' && qrCodeDiv && !qrCodeDiv.hasChildNodes()) {
                    document.getElementById('qrCodeSection').style.display = 'block';
                    
                    // Generate secure token
                    const token = CryptoJS.MD5(invoice.bill_no + 'LEDGERONE_SECRET_KEY').toString().substring(0, 16);
                    const verifyUrl = `${window.location.origin}/ledgerone_erp/client/pages/sale/pos_invoice/verify-invoice.php?invoice=${invoice.bill_no}&token=${token}`;
                    
                    new QRCode(qrCodeDiv, {
                        text: verifyUrl,
                        width: 90,
                        height: 90,
                        correctLevel: QRCode.CorrectLevel.H
                    });
                }
            }, 500);
        }
        
        document.getElementById('printedDate').textContent = new Date().toLocaleString();
        
        // Auto print: when opened with &autoprint=1 (POS F10 save), print as soon as the
        // receipt is fully rendered (data, logo, QR code and brand note) - no button click needed.
        const autoPrint = urlParams.get('autoprint') === '1';

        loadData().then(() => {
            if (!autoPrint) return;
            if (document.getElementById('invoiceNo').textContent === 'Loading...') return; // load failed, don't print a blank receipt
            // QR code renders after 500ms and brand note after 1000ms, so wait a little longer than that
            setTimeout(() => {
                const imgs = Array.from(document.images);
                Promise.all(imgs.map(img => img.complete ? Promise.resolve() :
                    new Promise(res => { img.onload = img.onerror = res; })
                )).then(() => {
                    window.onafterprint = () => window.close();
                    window.print();
                });
            }, 1300);
        });
        
        // Generate QR Code after data loads
        window.addEventListener('load', function() {
            const enablePrintQRCode = localStorage.getItem('enablePrintQRCode') === 'true';
            if (enablePrintQRCode && typeof QRCode !== 'undefined') {
                setTimeout(() => {
                    const billNo = document.getElementById('invoiceNo').textContent;
                    if (billNo && billNo !== 'Loading...') {
                        document.getElementById('qrCodeSection').style.display = 'block';
                        QRCode.toCanvas(document.getElementById('qrCode'), `INV-${billNo}`, {
                            width: 100,
                            margin: 1
                        });
                    }
                }, 1000);
            }
        });
        
        // Brand Note Modal Functions
        function openBrandNoteModal() {
            const modal = document.getElementById('brandNoteModal');
            const textarea = document.getElementById('brandNoteTextarea');
            const brandNoteKey = `brand_print_note_${tenantId}`;
            const brandNote = localStorage.getItem(brandNoteKey) || '';
            textarea.value = brandNote;
            modal.classList.add('show');
        }
        
        function closeBrandNoteModal() {
            const modal = document.getElementById('brandNoteModal');
            modal.classList.remove('show');
        }
        
        function saveBrandNote() {
            const textarea = document.getElementById('brandNoteTextarea');
            const noteText = textarea.value.trim();
            const brandNoteKey = `brand_print_note_${tenantId}`;
            
            if (noteText) {
                localStorage.setItem(brandNoteKey, noteText);
                displayBrandNote(noteText);
                closeBrandNoteModal();
                alert('Brand note saved successfully!');
            } else {
                alert('Please enter a note before saving.');
            }
        }
        
        function clearBrandNote() {
            if (confirm('Are you sure you want to clear the brand note? It will be removed from all invoices.')) {
                document.getElementById('brandNoteTextarea').value = '';
                const brandNoteKey = `brand_print_note_${tenantId}`;
                localStorage.removeItem(brandNoteKey);
                const notesSection = document.getElementById('notesSection');
                notesSection.classList.remove('show');
                closeBrandNoteModal();
            }
        }
        
        function displayBrandNote(noteText) {
            if (noteText) {
                const notesSection = document.getElementById('notesSection');
                const notesContent = document.getElementById('notesContent');
                notesContent.textContent = noteText;
                notesSection.classList.add('show');
            }
        }
        
        // Load brand note on page load
        window.addEventListener('load', function() {
            setTimeout(() => {
                if (tenantId) {
                    const brandNoteKey = `brand_print_note_${tenantId}`;
                    const brandNote = localStorage.getItem(brandNoteKey);
                    if (brandNote) {
                        displayBrandNote(brandNote);
                    }
                }
            }, 1000);
        });
        
        // Close modal when clicking outside
        document.getElementById('brandNoteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBrandNoteModal();
            }
        });
    </script>
</body>
</html>