<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thermal Print - Sale Invoice</title>
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
            font-family: 'Courier New', monospace;
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
            text-align: center;
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
            border-collapse: collapse;
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
            text-align: center;
        }

        /* Gap between the Ctn/Dz/Pcs numbers and the Item name column */
        .item-col {
            padding-left: 10px !important;
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

        .terms-note {
            margin-top: 8px;
            font-size: 10px;
            text-align: justify;
            direction: rtl;
            line-height: 1.6;
        }

        .signature-line {
            margin-top: 25px;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 3px;
            width: 60%;
            margin-left: auto;
            margin-right: auto;
            font-size: 10px;
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

        <div class="info-row">
            <span>Invoice #:</span>
            <span class="bold" id="invoiceNo">Loading...</span>
        </div>
        <div class="info-row">
            <span>Date:</span>
            <span id="invoiceDate">Loading...</span>
        </div>
        <div class="info-row" id="dueDateRow" >
            <span>Due Date:</span>
            <span id="dueDate">-</span>
        </div>
        <div class="info-row">
            <span>Customer:</span>
            <span id="customerName">Loading...</span>
        </div>
        <div class="info-row">
            <span>Address:</span>
            <span id="customerAddress">-</span>
        </div>
        <div class="info-row">
            <span>Sales Man:</span>
            <span id="salesManName">-</span>
        </div>
        <div class="info-row" id="deliveryManRow" >
            <span>Delivery Man:</span>
            <span id="deliveryManName">-</span>
        </div>

        <div class="divider"></div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;" class="text-right ctn-col" id="ctnHeader">Ctn</th>
                    <th style="width: 10%;" class="text-right dz-col" id="dzHeader">Dz</th>
                    <th style="width: 10%;" class="text-right pc-col" id="pcsHeader">Pcs</th>
                    <th class="item-col">Item</th>
                    <th style="width: 15%;" class="text-right">Rate</th>
                    <th style="width: 11%;" class="text-right disc-col">Disc</th>
                    <th style="width: 14%;" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <tr>
                    <td colspan="7" class="text-center">Loading...</td>
                </tr>
            </tbody>
        </table>

        <div class="divider"></div>

        <div class="total-row" id="discountRow">
            <span>Discount:</span>
            <span id="discountAmount">0</span>
        </div>
        <div class="total-row" style="display: none;" id="gstRow">
            <span>GST:</span>
            <span id="gstAmount">0</span>
        </div>
        <div class="total-row" id="advTaxRow">
            <span>Adv. Tax:</span>
            <span id="advTaxAmount">0</span>
        </div>
        <div class="total-row grand-total">
            <span>Net Bill:</span>
            <span id="netAmount">0</span>
        </div>
        <div class="total-row">
            <span>Cash Received:</span>
            <span id="amountPaid">0</span>
        </div>
        <div class="total-row" id="previousBalanceRow">
            <span>Prv. Balance:</span>
            <span id="previousBalance">0</span>
        </div>
        <div class="total-row grand-total">
            <span>Payable Amount:</span>
            <span id="payableAmount">0</span>
        </div>

        <div id="notesSection" class="notes-section">
            <div id="notesContent"></div>
        </div>

        <div class="divider"></div>

        <div class="footer text-center">
            <div>Payment Method: <span id="paymentMethod">-</span></div>
            <div style="margin-top: 5px;">Thank you for your business!</div>
            <div style="margin-top: 8px; font-size: 10px;">Printed By: <span id="printedBy">-</span></div>
            <div style="font-size: 10px;">Printed: <span id="printedDate"></span></div>
            <div id="qrCodeSection" style="margin-top: 8px; display: none;">
                <div id="qrCode" style="display: inline-block;"></div>
            </div>

            <div class="terms-note" id="termsNote">
                اس انوائس کی ادائیگی مقررہ تاریخ تک مکمل کرنا لازمی ہے، بصورت دیگر آئندہ سپلائی متاثر ہو سکتی ہے۔
            </div>

            <div class="signature-line">
                Signature of Customer's
            </div>

            <!-- <div style="margin-top: 10px; border-top: 1px dashed #000; padding-top: 8px; font-size: 10px; line-height: 1.6;">
                <div style="font-weight: bold;">Software by: UNISEN SYSTEMS</div>
                <div>Contact: +92 346 8918711</div>
                <div>+92 335 3789981</div>
                <div>Email: support@unisensystems.com</div>
                <div>www.unisensystems.com</div>
            </div> -->
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <script>
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

        // Format a Ctn/Dz/Pcs cell value: show '-' when null/undefined/0.
        // No decimals ever - always rounded to a whole number.
        function formatUnitCell(val) {
            const num = parseFloat(val);
            if (val === null || val === undefined || isNaN(num) || num === 0) {
                return '-';
            }
            return Math.round(num).toString();
        }

        // Format a money amount: always a whole number, never any decimals.
        function formatMoney(val) {
            const num = parseFloat(val);
            if (val === null || val === undefined || isNaN(num)) {
                return '0';
            }
            return Math.round(num).toString();
        }

        // Same unit IDs used app-wide for Sale Invoice (9=Piece, 10=Dozen, 16=Carton)
        function uomHeaderLabel(uoms, unitId, fallback) {
            const unit = uoms.find(u => Number(u.id) === unitId);
            if (!unit || !unit.uom_name) return fallback;
            const match = unit.uom_name.match(/\(([^)]+)\)/);
            return match ? match[1] : unit.uom_name;
        }

        function applyUomHeaders(uoms) {
            document.getElementById('ctnHeader').textContent = uomHeaderLabel(uoms, 16, 'Ctn');
            document.getElementById('dzHeader').textContent = uomHeaderLabel(uoms, 10, 'Dz');
            document.getElementById('pcsHeader').textContent = uomHeaderLabel(uoms, 9, 'Pcs');
        }

        // sale_tax_invoice never populates the carton/dozen/piece columns on
        // sale_invoice_items (pos-add.js always sends them as 0) - instead each
        // unit split (Carton/Dozen/Piece) the user entered is saved as its OWN
        // item row via uom_id + quantity. So route each row's quantity into the
        // right Ctn/Dz/Pc bucket by its uom_id (same ids as applyUomHeaders above).
        function getUnitBucket(uomId) {
            const id = Number(uomId);
            if (id === 16) return 'carton';
            if (id === 10) return 'dozen';
            return 'piece';
        }

        // Merge the per-unit split rows of the same product+batch back into one
        // printed line (mirrors the grouping pos-add.js does when reloading for Edit).
        function groupItemsForPrint(items) {
            const groups = [];
            const groupsByKey = {};
            items.forEach(item => {
                if (item.parent_row_id) return;
                const key = item.product_id + '_' + (item.batch_no || '');
                let group = groupsByKey[key];
                if (!group) {
                    group = {
                        ids: [],
                        product_name: item.product_name,
                        sale_price: item.sale_price,
                        carton: 0,
                        dozen: 0,
                        piece: 0,
                        gross_amount: 0,
                        discount_amount: 0,
                        trade_offer_amount: 0,
                        net_amount: 0
                    };
                    groupsByKey[key] = group;
                    groups.push(group);
                }
                group.ids.push(item.id);
                group[getUnitBucket(item.uom_id)] += parseFloat(item.quantity || 0);
                group.gross_amount += parseFloat(item.gross_amount || 0);
                group.discount_amount += parseFloat(item.discount_amount || 0);
                group.trade_offer_amount += parseFloat(item.trade_offer_amount || 0);
                group.net_amount += parseFloat(item.net_amount || 0);
            });
            return groups;
        }

        async function loadData() {
            try {
                const [userResponse, invoiceResponse, uomResponse] = await Promise.all([
                    fetch('../../../../server/api/sale/sale_tax_invoice/get-user.php'),
                    fetch(`../../../../server/api/sale/sale_tax_invoice/pos-edit.php?id=${invoiceId}`),
                    fetch('../../../../server/api/sale/sale_tax_invoice/get-uom.php')
                ]);

                const userData = await userResponse.json();
                const invoiceData = await invoiceResponse.json();
                const uomData = await uomResponse.json();

                if (userData.success) {
                    document.getElementById('printedBy').textContent = userData.user.full_name;
                }

                if (uomData.success) {
                    applyUomHeaders(uomData.uoms);
                }

                if (invoiceData.success) {
                    // Load company data if company_id exists
                    if (invoiceData.invoice.company_id) {
                        const companyResponse = await fetch(`../../../../server/api/sale/sale_tax_invoice/get-company-by-id.php?id=${invoiceData.invoice.company_id}`);
                        const companyData = await companyResponse.json();
                        if (companyData.success) {
                            populateCompanyData(companyData.company);
                        }
                    } else {
                        // Fallback to tenant's default company
                        const companyResponse = await fetch('../../../../server/api/sale/sale_tax_invoice/get-company.php');
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

        function populateInvoiceData(invoice, items) {
            document.getElementById('invoiceNo').textContent = invoice.bill_no;
            document.getElementById('invoiceDate').textContent = new Date(invoice.sale_date).toLocaleDateString();
            document.getElementById('customerName').textContent = invoice.customer_name;
            document.getElementById('customerAddress').textContent = invoice.customer_address || '-';
            document.getElementById('salesManName').textContent = invoice.sales_officer_name || 'CASH COUNTER';

            // Show delivery man (supplier_man) only if exists
            if (invoice.supplier_man_id) {
                document.getElementById('deliveryManRow').style.display = 'flex';
                document.getElementById('deliveryManName').textContent = invoice.supplier_man_name || '-';
            }

            // Show due date only if it exists (currently often NULL).
            // Note: the Urdu terms note stays generic and never includes the due date.
            if (invoice.due_date) {
                document.getElementById('dueDateRow').style.display = 'flex';
                const dueDateFormatted = new Date(invoice.due_date).toLocaleDateString();
                document.getElementById('dueDate').textContent = dueDateFormatted;
            }

            // Previous balance - always shown now (even if 0), like FoxPro
            const previousBalance = parseFloat(invoice.previous_balance || 0);
            document.getElementById('previousBalance').textContent = `${formatMoney(previousBalance)}`;

            const currencySymbol = '';

            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';

            // Merge each product's Carton/Dozen/Piece split rows back into one line
            const groups = groupItemsForPrint(items);

            // Map each raw (non-grouped) row id -> its children, so a group can pull
            // in the children attached to any of its underlying split-unit rows
            const childrenByParentId = {};
            items.forEach(item => {
                if (item.parent_row_id !== null && item.parent_row_id !== undefined) {
                    if (!childrenByParentId[item.parent_row_id]) {
                        childrenByParentId[item.parent_row_id] = [];
                    }
                    childrenByParentId[item.parent_row_id].push(item);
                }
            });
            const childrenOf = (group) => group.ids.reduce((acc, id) => acc.concat(childrenByParentId[id] || []), []);

            if (childDisplayMode === 'inline') {
                // Render grouped items with children inline
                groups.forEach((group) => {
                    const row = tbody.insertRow();

                    const children = childrenOf(group);
                    let childrenText = '';
                    if (children.length > 0) {
                        childrenText = '<br><span style="font-size: 10px; color: #666;">(' +
                            children.map(c => `${c.product_name}: ${formatMoney(c.quantity)}`).join(' | ') +
                            ')</span>';
                    }

                    const totalDisc = group.discount_amount + group.trade_offer_amount;

                    const discCell = (!enableCashDiscountAmount && !enableTradeOfferAmount) ? '' : `<td class="text-right disc-col">${currencySymbol + formatMoney(totalDisc)}</td>`;

                    row.innerHTML = `
                        <td class="text-right ctn-col">${formatUnitCell(group.carton)}</td>
                        <td class="text-right dz-col">${formatUnitCell(group.dozen)}</td>
                        <td class="text-right pc-col">${formatUnitCell(group.piece)}</td>
                        <td class="item-col">${group.product_name}${childrenText}</td>
                        <td class="text-right">${currencySymbol + formatMoney(group.sale_price)}</td>
                        ${discCell}
                        <td class="text-right">${currencySymbol + formatMoney(group.net_amount)}</td>
                    `;
                });
            } else {
                // Separate rows mode: one merged row per product, followed by its child rows
                groups.forEach((group) => {
                    const row = tbody.insertRow();

                    const totalDisc = group.discount_amount + group.trade_offer_amount;

                    const discCell = (!enableCashDiscountAmount && !enableTradeOfferAmount) ? '' : `<td class="text-right disc-col">${currencySymbol + formatMoney(totalDisc)}</td>`;

                    row.innerHTML = `
                        <td class="text-right ctn-col">${formatUnitCell(group.carton)}</td>
                        <td class="text-right dz-col">${formatUnitCell(group.dozen)}</td>
                        <td class="text-right pc-col">${formatUnitCell(group.piece)}</td>
                        <td class="item-col">${group.product_name}</td>
                        <td class="text-right">${currencySymbol + formatMoney(group.sale_price)}</td>
                        ${discCell}
                        <td class="text-right">${currencySymbol + formatMoney(group.net_amount)}</td>
                    `;

                    childrenOf(group).forEach((child) => {
                        const childRow = tbody.insertRow();
                        const childDiscCell = (!enableCashDiscountAmount && !enableTradeOfferAmount) ? '' : `<td class="text-right disc-col">-</td>`;
                        childRow.innerHTML = `
                            <td class="text-right ctn-col">-</td>
                            <td class="text-right dz-col">-</td>
                            <td class="text-right pc-col">-</td>
                            <td class="item-col" style="font-size: 11px; color: #666;"><span style="margin-left: 15px;">? </span>${child.product_name}</td>
                            <td class="text-right">-</td>
                            ${childDiscCell}
                            <td class="text-right">-</td>
                        `;
                    });
                });
            }

            // Add totals row
            const totalsRow = tbody.insertRow();
            totalsRow.style.borderTop = '2px solid #000';
            totalsRow.style.fontWeight = 'bold';

            let totalCarton = 0;
            let totalDozen = 0;
            let totalPiece = 0;
            let totalAmount = 0;

            groups.forEach(group => {
                totalCarton += group.carton;
                totalDozen  += group.dozen;
                totalPiece  += group.piece;
                totalAmount += group.net_amount;
            });

            const totalsDiscCell = (!enableCashDiscountAmount && !enableTradeOfferAmount) ? '' : `<td class="text-right disc-col"></td>`;

            totalsRow.innerHTML = `
                <td class="text-right ctn-col">${totalCarton > 0 ? Math.round(totalCarton).toString() : '-'}</td>
                <td class="text-right dz-col">${totalDozen > 0 ? Math.round(totalDozen).toString() : '-'}</td>
                <td class="text-right pc-col">${totalPiece > 0 ? Math.round(totalPiece).toString() : '-'}</td>
                <td class="item-col">TOTALS</td>
                <td class="text-right"></td>
                ${totalsDiscCell}
                <td class="text-right">${currencySymbol}${formatMoney(totalAmount)}</td>
            `;

            // Hide a Ctn/Dz/Pc column entirely when none of this invoice's items use that unit
            if (totalCarton <= 0) document.querySelectorAll('.ctn-col').forEach(el => el.style.display = 'none');
            if (totalDozen <= 0) document.querySelectorAll('.dz-col').forEach(el => el.style.display = 'none');
            if (totalPiece <= 0) document.querySelectorAll('.pc-col').forEach(el => el.style.display = 'none');

            // Discount - sum of item-level Discount % amounts + Trade Offer amounts
            // across all items, plus any invoice-level discount entered separately.
            // (Previously this only read invoice.total_discount_amount, which stays 0
            // whenever discount is applied per-item instead of at the invoice level —
            // that's why Discount always printed as 0.)
            let itemsDiscountTotal = 0;
            groups.forEach(group => {
                itemsDiscountTotal += group.discount_amount + group.trade_offer_amount;
            });
            const invoiceLevelDiscount = parseFloat(invoice.total_discount_amount || 0);
            const discountAmt = itemsDiscountTotal + invoiceLevelDiscount;
            document.getElementById('discountAmount').textContent = `${currencySymbol}${formatMoney(discountAmt)}`;

            // Calculate total GST from items
            let totalGst = 0;
            items.forEach(item => {
                totalGst += parseFloat(item.gst_amount || 0);
            });
            if (totalGst > 0) {
                document.getElementById('gstRow').style.display = 'flex';
                document.getElementById('gstAmount').textContent = `${currencySymbol}${formatMoney(totalGst)}`;
            }

            // Adv. Tax - total of each item line's own tax_amount (item-level tax)
            let totalItemTax = 0;
            items.forEach(item => {
                totalItemTax += parseFloat(item.tax_amount || 0);
            });
            document.getElementById('advTaxAmount').textContent = `${currencySymbol}${formatMoney(totalItemTax)}`;

            document.getElementById('netAmount').textContent = `${currencySymbol}${formatMoney(invoice.net_amount)}`;

            // Calculate received and balance (Returned not shown/used on Sale Invoice print)
            const amountPaid = parseFloat(invoice.amount_paid) || 0;
            const amountReturned = parseFloat(invoice.amount_returned) || 0;
            const remainingBalance = parseFloat(invoice.net_amount) - amountPaid + amountReturned;
            const payableAmount = previousBalance + remainingBalance;

            document.getElementById('amountPaid').textContent = `${currencySymbol}${formatMoney(amountPaid)}`;
            document.getElementById('payableAmount').textContent = `${currencySymbol}${formatMoney(payableAmount)}`;

            document.getElementById('paymentMethod').textContent = invoice.payment_method || '-';

            // Generate QR Code if enabled
            setTimeout(() => {
                const enablePrintQRCode = localStorage.getItem('enablePrintQRCode') === 'true';
                const qrCodeDiv = document.getElementById('qrCode');
                if (enablePrintQRCode && typeof QRCode !== 'undefined' && qrCodeDiv && !qrCodeDiv.hasChildNodes()) {
                    document.getElementById('qrCodeSection').style.display = 'block';

                    // Generate secure token
                    const token = CryptoJS.MD5(invoice.bill_no + 'LEDGERONE_SECRET_KEY').toString().substring(0, 16);
                    const verifyUrl = `${window.location.origin}/ledgerone_erp/client/pages/sale/sale_tax_invoice/verify-invoice.php?invoice=${invoice.bill_no}&token=${token}`;

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

        loadData();

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