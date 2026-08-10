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
            text-align: left;
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
        
        <div class="info-row">
            <span>Invoice #:</span>
            <span class="bold" id="invoiceNo">Loading...</span>
        </div>
        <div class="info-row">
            <span>Date:</span>
            <span id="invoiceDate">Loading...</span>
        </div>
        <div class="info-row">
            <span>Customer:</span>
            <span id="customerName">Loading...</span>
        </div>
        <div class="info-row" id="previousBalanceRow" style="display: none;">
            <span>Prev Balance:</span>
            <span id="previousBalance">0.00</span>
        </div>
        <div class="info-row">
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
                    <th style="width: 40%;">Item</th>
                    <th style="width: 12%;" class="text-right">Qty</th>
                    <th style="width: 18%;" class="text-right">Price</th>
                    <th style="width: 15%;" class="text-right disc-col">Disc</th>
                    <th style="width: 15%;" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <tr>
                    <td colspan="5" class="text-center">Loading...</td>
                </tr>
            </tbody>
        </table>
        
        <div class="divider"></div>
        
        <div class="total-row">
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
        <div class="total-row grand-total">
            <span>TOTAL:</span>
            <span id="netAmount">0.00</span>
        </div>
        <div class="total-row">
            <span>Received:</span>
            <span id="amountPaid">0.00</span>
        </div>
        <div class="total-row">
            <span>Returned:</span>
            <span id="amountReturned">0.00</span>
        </div>
        <div class="total-row">
            <span>Balance:</span>
            <span id="remainingBalance">0.00</span>
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
            <div style="margin-top: 10px; border-top: 1px dashed #000; padding-top: 8px; font-size: 10px; line-height: 1.6;">
                <div style="font-weight: bold;">Software by: UNISEN SYSTEMS</div>
                <div>Contact: +92 346 8918711</div>
                <div>+92 335 3789981</div>
                <div>Email: support@unisensystems.com</div>
                <div>www.unisensystems.com</div>
            </div>
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
        
        function populateInvoiceData(invoice, items) {
            document.getElementById('invoiceNo').textContent = invoice.bill_no;
            document.getElementById('invoiceDate').textContent = new Date(invoice.sale_date).toLocaleDateString();
            document.getElementById('customerName').textContent = invoice.customer_name;
            
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
                    
                    // Build children text
                    let childrenText = '';
                    if (item.children && item.children.length > 0) {
                        childrenText = '<br><span style="font-size: 10px; color: #666;">(' +
                            item.children.map(c => `${c.product_name}: ${parseFloat(c.quantity).toFixed(2)}`).join(' | ') +
                            ')</span>';
                    }
                    
                    const discountAmt = parseFloat(item.discount_amount || 0);
                    const tradeOfferAmt = parseFloat(item.trade_offer_amount || 0);
                    const totalDisc = discountAmt + tradeOfferAmt;
                    
                    const discCell = (!enableCashDiscountAmount && !enableTradeOfferAmount) ? '' : `<td class="text-right disc-col">${currencySymbol + totalDisc.toFixed(2)}</td>`;
                    
                    row.innerHTML = `
                        <td>${item.product_name}${childrenText}</td>
                        <td class="text-right">${parseFloat(item.quantity).toFixed(2)}</td>
                        <td class="text-right">${currencySymbol + parseFloat(item.sale_price).toFixed(2)}</td>
                        ${discCell}
                        <td class="text-right">${currencySymbol + parseFloat(item.net_amount).toFixed(2)}</td>
                    `;
                });
            } else {
                // Separate rows mode (current)
                items.forEach((item) => {
                    const row = tbody.insertRow();
                    
                    const isChild = item.parent_row_id !== null;
                    const indent = isChild ? '<span style="margin-left: 15px; font-size: 11px;">? </span>' : '';
                    const textStyle = isChild ? 'font-size: 11px; color: #666;' : '';
                    
                    const discountAmt = parseFloat(item.discount_amount || 0);
                    const tradeOfferAmt = parseFloat(item.trade_offer_amount || 0);
                    const totalDisc = discountAmt + tradeOfferAmt;
                    
                    const discCell = (!enableCashDiscountAmount && !enableTradeOfferAmount) ? '' : `<td class="text-right disc-col">${isChild ? '-' : currencySymbol + totalDisc.toFixed(2)}</td>`;
                    
                    row.innerHTML = `
                        <td style="${textStyle}">${indent}${item.product_name}</td>
                        <td class="text-right">${parseFloat(item.quantity).toFixed(2)}</td>
                        <td class="text-right">${isChild ? '-' : currencySymbol + parseFloat(item.sale_price).toFixed(2)}</td>
                        ${discCell}
                        <td class="text-right">${isChild ? '-' : currencySymbol + parseFloat(item.net_amount).toFixed(2)}</td>
                    `;
                });
            }
            
            // Add totals row
            const totalsRow = tbody.insertRow();
            totalsRow.style.borderTop = '2px solid #000';
            totalsRow.style.fontWeight = 'bold';
            
            let totalQty = 0;
            let totalAmount = 0;
            
            if (childDisplayMode === 'inline') {
                // Count only parent items
                items.forEach(item => {
                    if (!item.parent_row_id) {
                        totalQty += parseFloat(item.quantity);
                        totalAmount += parseFloat(item.net_amount);
                    }
                });
            } else {
                // Count only parent items
                items.forEach(item => {
                    if (!item.parent_row_id) {
                        totalQty += parseFloat(item.quantity);
                        totalAmount += parseFloat(item.net_amount);
                    }
                });
            }
            
            const totalsDiscCell = (!enableCashDiscountAmount && !enableTradeOfferAmount) ? '' : `<td class="text-right disc-col"></td>`;
            
            totalsRow.innerHTML = `
                <td>TOTALS</td>
                <td class="text-right">${totalQty.toFixed(2)}</td>
                <td class="text-right"></td>
                ${totalsDiscCell}
                <td class="text-right">${currencySymbol}${totalAmount.toFixed(2)}</td>
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
            
            // Calculate received, returned and balance
            const amountPaid = parseFloat(invoice.amount_paid) || 0;
            const amountReturned = parseFloat(invoice.amount_returned) || 0;
            const remainingBalance = parseFloat(invoice.net_amount) - amountPaid + amountReturned;
            
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
