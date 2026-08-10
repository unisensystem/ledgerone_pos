<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thermal Print - Sale Return</title>
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
            font-size: 11px;
            line-height: 1.3;
            color: #000;
            background: #f5f5f5;
            padding: 10px;
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
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .company-info {
            font-size: 10px;
            margin-bottom: 2px;
        }
        
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        
        .invoice-header {
            font-size: 12px;
            font-weight: bold;
            margin: 8px 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 10px;
        }
        
        .items-table {
            width: 100%;
            margin: 8px 0;
            font-size: 10px;
        }
        
        .items-table th {
            border-bottom: 1px solid #000;
            padding: 3px 0;
            text-align: left;
        }
        
        .items-table td {
            padding: 3px 0;
            border-bottom: 1px dotted #ccc;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
            font-size: 11px;
        }
        
        .total-row.grand-total {
            font-size: 12px;
            font-weight: bold;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }
        
        .footer {
            font-size: 9px;
            margin-top: 10px;
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
    </style>
</head>
<body>
    <div class="no-print">
        <button class="print-btn" onclick="window.print()">Print Receipt</button>
    </div>
    
    <div class="thermal-receipt">
        <div class="text-center">
            <img id="companyLogo" src="" alt="Logo" class="company-logo" style="display: none;">
            <div class="company-name" id="companyName">Loading...</div>
            <div class="company-info" id="companyAddress"></div>
            <div class="company-info" id="companyPhone"></div>
        </div>
        
        <div class="divider"></div>
        
        <div class="text-center invoice-header">SALE RETURN</div>
        
        <div class="info-row">
            <span>Return #:</span>
            <span class="bold" id="invoiceNo">Loading...</span>
        </div>
        <div class="info-row">
            <span>Sale Invoice #:</span>
            <span id="saleInvoiceNo">-</span>
        </div>
        <div class="info-row">
            <span>Date:</span>
            <span id="invoiceDate">Loading...</span>
        </div>
        <div class="info-row">
            <span>Customer:</span>
            <span id="customerName">Loading...</span>
        </div>
        <div class="info-row">
            <span>Branch:</span>
            <span id="branchName">Loading...</span>
        </div>
        
        <div class="divider"></div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 40%;">Item</th>
                    <th style="width: 12%;" class="text-right">Qty</th>
                    <th style="width: 18%;" class="text-right">Price</th>
                    <th style="width: 15%;" class="text-right">Disc</th>
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
        <div class="total-row">
            <span>Discount:</span>
            <span id="discountAmount">0.00</span>
        </div>
        <div class="total-row" id="extraDiscount1Row" style="display:none;">
            <span>Extra Disc 1:</span>
            <span id="extraDiscount1Amt">0.00</span>
        </div>
        <div class="total-row" id="extraDiscount2Row" style="display:none;">
            <span>Extra Disc 2:</span>
            <span id="extraDiscount2Amt">0.00</span>
        </div>
        <div class="total-row" id="shippingFeesRow" style="display:none;">
            <span>Shipping:</span>
            <span id="shippingFeesAmt">0.00</span>
        </div>
        <div class="total-row grand-total">
            <span>TOTAL:</span>
            <span id="netAmount">0.00</span>
        </div>
        <div class="total-row">
            <span>Refunded:</span>
            <span id="amountPaid">0.00</span>
        </div>
        <div class="total-row">
            <span>Balance:</span>
            <span id="remainingBalance">0.00</span>
        </div>
        
        <div class="divider"></div>
        
        <div class="footer text-center">
            <div>Payment Method: <span id="paymentMethod">-</span></div>
            <div style="margin-top: 5px;">Thank you for your business!</div>
            <div style="margin-top: 8px; font-size: 8px;">Printed By: <span id="printedBy">-</span></div>
            <div style="font-size: 8px;">Printed: <span id="printedDate"></span></div>
            <div style="margin-top: 10px; border-top: 1px dashed #000; padding-top: 8px; font-size: 8px;">
                <div>Software by: UNISEN SYSTEMS</div>
                <div>Contact: +92 346 8918711 | +92 335 3789981</div><div>Email: support@unisensystems.com</div>
                <div>www.unisensystems.com</div>
            </div>
        </div>
    </div>
    
    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const invoiceId = urlParams.get('id');
        
        if (!invoiceId) {
            alert('Invoice ID is required');
            window.close();
        }
        
        async function loadData() {
            try {
                const [companyResponse, userResponse, invoiceResponse] = await Promise.all([
                    fetch('../../../../server/api/sale/sale_return/get-company.php'),
                    fetch('../../../../server/api/sale/sale_return/get-user.php'),
                    fetch(`../../../../server/api/sale/sale_return/return-edit.php?id=${invoiceId}`)
                ]);
                
                const companyData = await companyResponse.json();
                const userData = await userResponse.json();
                const invoiceData = await invoiceResponse.json();
                
                if (invoiceData.success && invoiceData.invoice.company_name) {
                    populateCompanyData({
                        company_name: invoiceData.invoice.company_name,
                        legal_name: invoiceData.invoice.legal_name,
                        email: invoiceData.invoice.company_email,
                        phone: invoiceData.invoice.company_phone,
                        address: invoiceData.invoice.company_address,
                        city: invoiceData.invoice.company_city,
                        state: invoiceData.invoice.company_state,
                        zipcode: invoiceData.invoice.company_zipcode,
                        logo_url: invoiceData.invoice.company_logo
                    });
                } else if (companyData.success) {
                    populateCompanyData(companyData.company);
                }
                
                if (userData.success) {
                    document.getElementById('printedBy').textContent = userData.user.full_name;
                }
                
                if (invoiceData.success) {
                    populateInvoiceData(invoiceData.invoice, invoiceData.items);
                } else {
                    alert('Error loading invoice: ' + invoiceData.message);
                }
            } catch (error) {
                alert('Error loading data: ' + error.message);
            }
        }
        
        function populateCompanyData(company) {
            document.getElementById('companyName').textContent = company.company_name || company.legal_name || 'Company Name';
            document.getElementById('companyAddress').textContent = company.address || '';
            document.getElementById('companyPhone').textContent = company.phone ? `Tel: ${company.phone}` : '';
            
            const logoElement = document.getElementById('companyLogo');
            if (company.logo_url && logoElement) {
                logoElement.src = `../../../assets/uploads/company_logo/${company.logo_url}`;
                logoElement.style.display = 'block';
            }
        }
        
        function populateInvoiceData(invoice, items) {
            document.getElementById('invoiceNo').textContent = invoice.bill_no;
            document.getElementById('saleInvoiceNo').textContent = invoice.sale_invoice_no || '-';
            document.getElementById('invoiceDate').textContent = new Date(invoice.sale_date).toLocaleDateString();
            document.getElementById('customerName').textContent = invoice.customer_name;
            
            const branchText = invoice.parent_branch_name ? 
                `${invoice.branch_name} - ${invoice.parent_branch_name}` : 
                invoice.branch_name;
            document.getElementById('branchName').textContent = branchText;
            
            const currencySymbol = invoice.currency_symbol || '';
            
            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';
            
            items.forEach((item) => {
                // Calculate total quantity from unit_entries
                let totalQtyDisplay = '';
                if (item.unit_entries && item.unit_entries.length > 0) {
                    const qtyParts = item.unit_entries.map(entry => 
                        `${parseFloat(entry.quantity).toFixed(0)} ${entry.uom_name}`
                    );
                    totalQtyDisplay = qtyParts.join(' + ');
                } else {
                    totalQtyDisplay = '0';
                }
                
                const row = tbody.insertRow();
                const discountAmt = parseFloat(item.discount_amount || 0);
                const tradeOfferAmt = parseFloat(item.trade_offer_amount || 0);
                const totalDisc = discountAmt + tradeOfferAmt;
                
                row.innerHTML = `
                    <td>${item.product_name}</td>
                    <td class="text-right" style="font-size: 9px;">${totalQtyDisplay}</td>
                    <td class="text-right">${currencySymbol}${parseFloat(item.sale_price).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol}${totalDisc.toFixed(2)}</td>
                    <td class="text-right">${currencySymbol}${parseFloat(item.net_amount).toFixed(2)}</td>
                `;
            });
            
            document.getElementById('totalBill').textContent = `${currencySymbol}${parseFloat(invoice.total_bill).toFixed(2)}`;
            document.getElementById('discountAmount').textContent = `${currencySymbol}${parseFloat(invoice.total_discount_amount).toFixed(2)}`;

            // Extra Discount 1
            const ed1 = parseFloat(invoice.extra_discount_1_amount || 0);
            const ed1Row = document.getElementById('extraDiscount1Row');
            if (ed1 > 0) { ed1Row.style.display = ''; document.getElementById('extraDiscount1Amt').textContent = `${currencySymbol}${ed1.toFixed(2)}`; }
            else { ed1Row.style.display = 'none'; }

            // Extra Discount 2
            const ed2 = parseFloat(invoice.extra_discount_2_amount || 0);
            const ed2Row = document.getElementById('extraDiscount2Row');
            if (ed2 > 0) { ed2Row.style.display = ''; document.getElementById('extraDiscount2Amt').textContent = `${currencySymbol}${ed2.toFixed(2)}`; }
            else { ed2Row.style.display = 'none'; }

            // Shipping Fees
            const shipping = parseFloat(invoice.shipping_fees || 0);
            const shippingRow = document.getElementById('shippingFeesRow');
            if (shipping > 0) { shippingRow.style.display = ''; document.getElementById('shippingFeesAmt').textContent = `${currencySymbol}${shipping.toFixed(2)}`; }
            else { shippingRow.style.display = 'none'; }
            
            document.getElementById('netAmount').textContent = `${currencySymbol}${parseFloat(invoice.net_amount).toFixed(2)}`;
            
            // Calculate paid and balance
            const amountPaid = parseFloat(invoice.amount_refunded) || 0;
            const remainingBalance = parseFloat(invoice.net_amount) - amountPaid;
            
            document.getElementById('amountPaid').textContent = `${currencySymbol}${amountPaid.toFixed(2)}`;
            document.getElementById('remainingBalance').textContent = `${currencySymbol}${remainingBalance.toFixed(2)}`;
            
            document.getElementById('paymentMethod').textContent = invoice.payment_method || '-';
        }
        
        document.getElementById('printedDate').textContent = new Date().toLocaleString();
        
        loadData();
    </script>
</body>
</html>
