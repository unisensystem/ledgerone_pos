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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: white;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .company-info h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .invoice-details {
            text-align: right;
        }
        
        .invoice-details h2 {
            font-size: 20px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .invoice-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .supplier-info, .invoice-info {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .supplier-info h3, .invoice-info h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 11px;
        }
        
        .items-table td {
            font-size: 11px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .totals-table {
            width: 300px;
        }
        
        .totals-table td {
            padding: 5px 10px;
            border: none;
        }
        
        .totals-table .total-row {
            font-weight: bold;
            border-top: 2px solid #333;
        }
        
        .print-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .print-btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="print-btn" onclick="window.print()">Print Invoice</button>
    </div>
    
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-info">
                <img id="companyLogo" src="" alt="Company Logo" style="max-width: 120px; max-height: 80px; margin-bottom: 10px;">
                <h1 id="companyName">Loading...</h1>
                <p id="companyAddress">Loading...</p>
                <p id="companyCityState">Loading...</p>
                <p id="companyPhone">Loading...</p>
                <p id="companyEmail">Loading...</p>
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
                <p><strong>Previous Balance:</strong> <span id="previousBalance">Loading...</span></p>
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
                    <th width="20%">Product</th>
                    <th width="10%">Engine No</th>
                    <th width="10%">Chassis No</th>
                    <th width="7%" id="printSizeHeader">Size</th>
                    <th width="13%">Quantities</th>
                    <th width="10%" class="text-right">Trade Price</th>
                    <th width="10%" class="text-right">Gross Amt</th>
                    <th width="7%" class="text-right">Disc %</th>
                    <th width="10%" class="text-right">Disc Amt</th>
                    <th width="7%" class="text-right">TO %</th>
                    <th width="10%" class="text-right">TO Amt</th>
                    <th width="5%" class="text-right">Tax %</th>
                    <th width="10%" class="text-right">Tax Amt</th>
                    <th width="7%" class="text-right">FOC Qty</th>
                    <th width="12%" class="text-right">Net Amt</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <tr>
                    <td colspan="8" class="text-center">Loading items...</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background-color: #f5f5f5; font-weight: bold;">
                    <th colspan="3">Totals</th>
                    <th class="text-right" id="totalUnitPrice">0.00</th>
                    <th class="text-right" id="totalGrossAmount">0.00</th>
                    <th></th>
                    <th class="text-right" id="totalDiscountAmountItems">0.00</th>
                    <th></th>
                    <th class="text-right" id="totalTradeOfferAmountItems">0.00</th>
                    <th></th>
                    <th class="text-right" id="totalGSTAmountItems">0.00</th>
                    <th class="text-right" id="totalFOCQty">0.00</th>
                    <th class="text-right" id="totalNetAmountItems">0.00</th>
                </tr>
            </tfoot>
        </table>
        
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td>Total Bill:</td>
                    <td class="text-right" id="totalBill">0.00</td>
                </tr>
                <tr id="discountPercentRow">
                    <td>Discount (%):</td>
                    <td class="text-right" id="discountPercent">0.00%</td>
                </tr>
                <tr id="discountAmountRow">
                    <td>Discount Amount:</td>
                    <td class="text-right" id="discountAmount">0.00</td>
                </tr>
                <tr id="gstPercentRow">
                    <td>Tax (%):</td>
                    <td class="text-right" id="gstPercent">0.00%</td>
                </tr>
                <tr id="gstAmountRow">
                    <td>Tax Amount:</td>
                    <td class="text-right" id="gstAmount">0.00</td>
                </tr>
                <tr id="shippingFeesRow">
                    <td><span id="shippingFeesLabel">Shipping Fees:</span></td>
                    <td class="text-right" id="shippingFees">0.00</td>
                </tr>
                <tr class="total-row">
                    <td>Net Amount:</td>
                    <td class="text-right" id="netAmount">0.00</td>
                </tr>
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
        // Get invoice ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const invoiceId = urlParams.get('id');
        
        if (!invoiceId) {
            alert('Invoice ID is required');
            window.close();
        }
        
        // Apply invoice settings to hide/show columns
        applyInvoiceSettings();
        function applyInvoiceSettings() {
            const enableSize = localStorage.getItem('enableSize') === 'true';
            const enableEngineNo = localStorage.getItem('enableEngineNo') === 'true';
            const enableChassisNo = localStorage.getItem('enableChassisNo') === 'true';
            const enableInlineCashDiscount = localStorage.getItem('enableInlineCashDiscount') === 'true';
            const enableInlineCashDiscountAmount = localStorage.getItem('enableInlineCashDiscountAmount') === 'true';
            const enableTradeOffer = localStorage.getItem('enableTradeOffer') === 'true';
            const enableTradeOfferAmount = localStorage.getItem('enableTradeOfferAmount') === 'true';
            const enableFOC = localStorage.getItem('enableFOC') === 'true';
            const enableTaxation = localStorage.getItem('enableTaxation') === 'true';
            const enableInvoiceCashDiscount = localStorage.getItem('enableInvoiceCashDiscount') === 'true';
            const enableInvoiceCashDiscountAmount = localStorage.getItem('enableInvoiceCashDiscountAmount') === 'true';
            const enableShippingFees = localStorage.getItem('enableShippingFees') === 'true';
            
            // Hide/show table headers (0=#, 1=Product, 2=EngineNo, 3=ChassisNo, 4=Size, 5=Quantities, 6=TradePrice, 7=GrossAmt, 8=Disc%, 9=DiscAmt, 10=TO%, 11=TOAmt, 12=Tax%, 13=TaxAmt, 14=FOC, 15=NetAmt)
            const headers = document.querySelectorAll('.items-table thead th');
            headers[2].style.display = enableEngineNo ? '' : 'none'; // Engine No
            headers[3].style.display = enableChassisNo ? '' : 'none'; // Chassis No
            headers[4].style.display = enableSize ? '' : 'none'; // Size
            headers[8].style.display = enableInlineCashDiscount ? '' : 'none'; // Disc %
            headers[9].style.display = enableInlineCashDiscountAmount ? '' : 'none'; // Disc Amt
            headers[10].style.display = enableTradeOffer ? '' : 'none'; // TO %
            headers[11].style.display = enableTradeOfferAmount ? '' : 'none'; // TO Amt
            headers[12].style.display = enableTaxation ? '' : 'none'; // GST %
            headers[13].style.display = enableTaxation ? '' : 'none'; // GST Amt
            headers[14].style.display = enableFOC ? '' : 'none'; // FOC Qty
            
            // Hide/show footer cells by ID
            const totalUnitPrice = document.getElementById('totalUnitPrice');
            const totalGrossAmount = document.getElementById('totalGrossAmount');
            const totalDiscountAmountItems = document.getElementById('totalDiscountAmountItems');
            const totalTradeOfferAmountItems = document.getElementById('totalTradeOfferAmountItems');
            const totalGSTAmountItems = document.getElementById('totalGSTAmountItems');
            const totalFOCQty = document.getElementById('totalFOCQty');
            const totalNetAmountItems = document.getElementById('totalNetAmountItems');
            
            if (totalUnitPrice) totalUnitPrice.parentElement.style.display = enableEngineNo ? '' : 'none';
            if (totalGrossAmount) totalGrossAmount.parentElement.style.display = enableChassisNo ? '' : 'none';
            if (totalDiscountAmountItems) totalDiscountAmountItems.parentElement.style.display = enableInlineCashDiscount ? '' : 'none';
            if (totalTradeOfferAmountItems) totalTradeOfferAmountItems.parentElement.style.display = enableTradeOfferAmount ? '' : 'none';
            if (totalGSTAmountItems) totalGSTAmountItems.parentElement.style.display = enableTaxation ? '' : 'none';
            if (totalFOCQty) totalFOCQty.parentElement.style.display = enableFOC ? '' : 'none';
            
            // Hide/show totals section rows
            document.getElementById('discountPercentRow').style.display = enableInvoiceCashDiscount ? '' : 'none';
            document.getElementById('discountAmountRow').style.display = enableInvoiceCashDiscountAmount ? '' : 'none';
            document.getElementById('gstPercentRow').style.display = enableTaxation ? '' : 'none';
            document.getElementById('gstAmountRow').style.display = enableTaxation ? '' : 'none';
            document.getElementById('shippingFeesRow').style.display = enableShippingFees ? '' : 'none';
        }
        
        // Load company, user and invoice data
        async function loadData() {
            try {
                const [userResponse, invoiceResponse] = await Promise.all([
                    fetch('../../../../server/api/purchase/purchase_invoice/get-user.php'),
                    fetch(`../../../../server/api/purchase/purchase_invoice/purchase-edit.php?id=${invoiceId}`)
                ]);
                
                const userData = await userResponse.json();
                const invoiceData = await invoiceResponse.json();
                
                if (userData.success) {
                    document.getElementById('generatedBy').textContent = userData.user.full_name;
                }
                
                if (invoiceData.success) {
                    populateCompanyData(invoiceData.invoice);
                    populateInvoiceData(invoiceData.invoice, invoiceData.items);
                } else {
                    alert('Error loading invoice: ' + invoiceData.message);
                }
            } catch (error) {
                alert('Error loading data: ' + error.message);
            }
        }
        
        function populateCompanyData(invoice) {
            document.getElementById('companyName').textContent = invoice.company_name || invoice.legal_name || 'Company Name';
            document.getElementById('companyAddress').textContent = invoice.company_address || '';
            document.getElementById('companyCityState').textContent = `${invoice.company_city || ''}, ${invoice.company_state || ''} ${invoice.company_zipcode || ''}`.trim();
            document.getElementById('companyPhone').textContent = invoice.company_phone ? `Phone: ${invoice.company_phone}` : '';
            document.getElementById('companyEmail').textContent = invoice.company_email ? `Email: ${invoice.company_email}` : '';
        
        // Set company logo
        const logoElement = document.getElementById('companyLogo');
        if (invoice.logo_url && logoElement) {
            logoElement.src = `../../../assets/uploads/company_logo/${invoice.logo_url}`;
        }
        }
        
        function populateInvoiceData(invoice, items) {
            // Populate header info
            document.getElementById('invoiceNo').textContent = invoice.bill_no;
            document.getElementById('invoiceDate').textContent = new Date(invoice.purchase_date).toLocaleDateString();
            document.getElementById('supplierName').textContent = `${invoice.supplier_code} - ${invoice.supplier_name}`;
            document.getElementById('subAccountName').textContent = invoice.sub_account_name || '-';
            document.getElementById('supplierInvoiceNo').textContent = invoice.supplier_invoice_no || '-';
            document.getElementById('supplierInvoiceDate').textContent = invoice.supplier_invoice_date ? new Date(invoice.supplier_invoice_date).toLocaleDateString() : '-';
            document.getElementById('biltyNo').textContent = invoice.bilty_no || '-';
            document.getElementById('transportName').textContent = invoice.transport_name || '-';
            document.getElementById('previousBalance').textContent = invoice.previous_balance;
            const branchText = invoice.parent_branch_name ? 
                `${invoice.branch_name} (${invoice.branch_type}) - Parent: ${invoice.parent_branch_name}` : 
                `${invoice.branch_name} (${invoice.branch_type})`;
            document.getElementById('branchName').textContent = branchText;
            document.getElementById('projectName').textContent = invoice.project_name ? `${invoice.project_code} - ${invoice.project_name}` : '-';
            document.getElementById('currency').textContent = invoice.currency_name;
            document.getElementById('remarks').textContent = invoice.remarks || '-';
            
            // Set currency symbol for amounts
            const currencySymbol = invoice.currency_symbol || '';
            
            // Populate items
            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';
            
            applyInvoiceSettings();
            
            let totalUnitPrice = 0, totalGrossAmount = 0, totalDiscountAmountItems = 0, totalTradeOfferAmountItems = 0, totalGSTAmountItems = 0, totalFOCQty = 0, totalNetAmountItems = 0;
            
            const enableSize = localStorage.getItem('enableSize') === 'true';
            const enableInlineCashDiscount = localStorage.getItem('enableInlineCashDiscount') === 'true';
            const enableInlineCashDiscountAmount = localStorage.getItem('enableInlineCashDiscountAmount') === 'true';
            const enableTradeOffer = localStorage.getItem('enableTradeOffer') === 'true';
            const enableTradeOfferAmount = localStorage.getItem('enableTradeOfferAmount') === 'true';
            const enableFOC = localStorage.getItem('enableFOC') === 'true';
            const enableTaxation = localStorage.getItem('enableTaxation') === 'true';
            
            items.forEach((item, index) => {
                // Build unit quantities display
                const unitMap = {};
                item.unit_entries.forEach(entry => {
                    const qty = parseFloat(entry.quantity);
                    if (qty > 0) {
                        const unitName = entry.uom_name || 'Unit';
                        if (unitMap[unitName]) {
                            unitMap[unitName] += qty;
                        } else {
                            unitMap[unitName] = qty;
                        }
                    }
                });
                
                let unitQtyDisplay = '';
                Object.keys(unitMap).forEach(unitName => {
                    if (unitQtyDisplay) unitQtyDisplay += ', ';
                    unitQtyDisplay += `${unitMap[unitName].toFixed(2)} ${unitName}`;
                });
                
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td class="text-center">${index + 1}</td>
                    <td>${item.product_name}</td>
                    <td>${item.engine_no || '-'}</td>
                    <td>${item.chassis_no || '-'}</td>
                    <td style="display: ${enableSize ? '' : 'none'}">${item.size || '-'}</td>
                    <td>${unitQtyDisplay || '-'}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.purchase_price).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.gross_amount).toFixed(2)}</td>
                    <td class="text-right" style="display: ${enableInlineCashDiscount ? '' : 'none'}">${parseFloat(item.discount_percent).toFixed(2)}%</td>
                    <td class="text-right" style="display: ${enableInlineCashDiscountAmount ? '' : 'none'}">${currencySymbol} ${parseFloat(item.discount_amount).toFixed(2)}</td>
                    <td class="text-right" style="display: ${enableTradeOffer ? '' : 'none'}">${parseFloat(item.trade_offer_percent || 0).toFixed(2)}%</td>
                    <td class="text-right" style="display: ${enableTradeOfferAmount ? '' : 'none'}">${currencySymbol} ${parseFloat(item.trade_offer_amount || 0).toFixed(2)}</td>
                    <td class="text-right" style="display: ${enableTaxation ? '' : 'none'}">${parseFloat(item.tax_percent || 0).toFixed(2)}%</td>
                    <td class="text-right" style="display: ${enableTaxation ? '' : 'none'}">${currencySymbol} ${parseFloat(item.tax_amount || 0).toFixed(2)}</td>
                    <td class="text-right" style="display: ${enableFOC ? '' : 'none'}">${parseFloat(item.foc_quantity || 0).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.net_amount).toFixed(2)}</td>
                `;
                
                // Calculate totals
                totalUnitPrice += parseFloat(item.purchase_price);
                totalGrossAmount += parseFloat(item.gross_amount);
                totalDiscountAmountItems += parseFloat(item.discount_amount);
                totalTradeOfferAmountItems += parseFloat(item.trade_offer_amount || 0);
                totalGSTAmountItems += parseFloat(item.tax_amount || 0);
                totalFOCQty += parseFloat(item.foc_quantity || 0);
                totalNetAmountItems += parseFloat(item.net_amount);
            });
            
            // Update totals row
            document.getElementById('totalUnitPrice').textContent = `${currencySymbol} ${totalUnitPrice.toFixed(2)}`;
            document.getElementById('totalGrossAmount').textContent = `${currencySymbol} ${totalGrossAmount.toFixed(2)}`;
            document.getElementById('totalDiscountAmountItems').textContent = `${currencySymbol} ${totalDiscountAmountItems.toFixed(2)}`;
            document.getElementById('totalTradeOfferAmountItems').textContent = `${currencySymbol} ${totalTradeOfferAmountItems.toFixed(2)}`;
            document.getElementById('totalGSTAmountItems').textContent = `${currencySymbol} ${totalGSTAmountItems.toFixed(2)}`;
            document.getElementById('totalFOCQty').textContent = totalFOCQty.toFixed(2);
            document.getElementById('totalNetAmountItems').textContent = `${currencySymbol} ${totalNetAmountItems.toFixed(2)}`;
            
            // Populate totals with currency symbol
            document.getElementById('totalBill').textContent = `${currencySymbol} ${parseFloat(invoice.total_bill).toFixed(2)}`;
            document.getElementById('discountPercent').textContent = parseFloat(invoice.total_discount_percent).toFixed(2) + '%';
            document.getElementById('discountAmount').textContent = `${currencySymbol} ${parseFloat(invoice.total_discount_amount).toFixed(2)}`;
            document.getElementById('gstPercent').textContent = parseFloat(invoice.total_tax_percent || 0).toFixed(2) + '%';
            document.getElementById('gstAmount').textContent = `${currencySymbol} ${parseFloat(invoice.total_tax_amount || 0).toFixed(2)}`;
            
            // Display shipping fees with +/- indicator
            const shippingFeesType = invoice.shipping_fees_type || 'add';
            const shippingFeesSymbol = shippingFeesType === 'add' ? '+' : '-';
            document.getElementById('shippingFeesLabel').textContent = `Shipping Fees (${shippingFeesSymbol}):`;
            document.getElementById('shippingFees').textContent = `${currencySymbol} ${parseFloat(invoice.shipping_fees || 0).toFixed(2)}`;
            
            document.getElementById('netAmount').textContent = `${currencySymbol} ${parseFloat(invoice.net_amount).toFixed(2)}`;
            
            // Convert net amount to words with multi-currency support
            const currencyUnitMap = {
                'USD': ['Dollars','Cents'],   'EUR': ['Euros','Cents'],
                'GBP': ['Pounds','Pence'],    'PKR': ['Rupees','Paisa'],
                'INR': ['Rupees','Paisa'],    'AED': ['Dirhams','Fils'],
                'SAR': ['Riyals','Halalah'],  'OMR': ['Rials','Baisa'],
                'QAR': ['Riyals','Dirhams'],  'KWD': ['Dinars','Fils'],
                'BHD': ['Dinars','Fils'],     'JPY': ['Yen','Sen'],
                'CNY': ['Yuan','Fen'],        'CAD': ['Dollars','Cents'],
                'AUD': ['Dollars','Cents'],   'CHF': ['Francs','Rappen'],
                'TRY': ['Lira','Kurus'],      'BDT': ['Taka','Paisa'],
                'MYR': ['Ringgit','Sen'],     'SGD': ['Dollars','Cents'],
                'ZAR': ['Rand','Cents'],      'NGN': ['Naira','Kobo'],
                'EGP': ['Pounds','Piastres'], 'NPR': ['Rupees','Paisa'],
            };
            const invCurrCode = (invoice.currency_code || 'PKR').toUpperCase();
            const [majorUnit, minorUnit] = currencyUnitMap[invCurrCode] || ['Units', 'Subunits'];
            const netMajor = Math.floor(parseFloat(invoice.net_amount));
            const netMinor = Math.round((parseFloat(invoice.net_amount) % 1) * 100);
            let amountInWordsStr = `${invCurrCode} ${numberToWords(netMajor)} ${majorUnit}`;
            if (netMinor > 0) amountInWordsStr += ` and ${numberToWords(netMinor)} ${minorUnit}`;
            amountInWordsStr += ' Only';
            document.getElementById('amountInWords').textContent = amountInWordsStr;
        }
        
        // Set generated on date
        document.getElementById('generatedOn').textContent = new Date().toLocaleString();
        
        // Function to convert number to words
        function numberToWords(num) {
            const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
            const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
            const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            const thousands = ['', 'Thousand', 'Million', 'Billion'];
            
            if (num === 0) return 'Zero';
            
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
            let thousandIndex = 0;
            
            while (num > 0) {
                if (num % 1000 !== 0) {
                    result = convertHundreds(num % 1000) + thousands[thousandIndex] + ' ' + result;
                }
                num = Math.floor(num / 1000);
                thousandIndex++;
            }
            
            return result.trim();
        }
        
        // Load data on page load
        loadData();
    </script>
</body>
</html>