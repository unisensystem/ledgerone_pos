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
            border: 2px solid #333;
        }
        
        .totals-table td {
            padding: 5px 10px;
            border: none;
            border-bottom: 1px solid #ddd;
        }
        
        .totals-table tr:last-child td {
            border-bottom: none;
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
                <h2>PURCHASE TAX INVOICE</h2>
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
                    <th width="8%">Unit</th>
                    <th width="8%" class="text-right">Qty</th>
                    <th width="10%" class="text-right">RP Unit Price</th>
                    <th width="10%" class="text-right">TP Unit Price</th>
                    <th width="10%" class="text-right">RP Total Value</th>
                    <th width="10%" class="text-right">TP Total Value</th>
                    <th width="7%" class="text-right">Disc %</th>
                    <th width="10%" class="text-right">Disc Amt</th>
                    <th width="10%" class="text-right">Sales Tax</th>
                    <th width="10%" class="text-right">TP Amount</th>
                    <th width="12%" class="text-right">Net Amt</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <tr>
                    <td colspan="13" class="text-center">Loading items...</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background-color: #f5f5f5; font-weight: bold;">
                    <th colspan="3">Totals</th>
                    <th class="text-right" id="totalQty">0.00</th>
                    <th class="text-right" id="totalRPUnitPrice">0.00</th>
                    <th class="text-right" id="totalTPUnitPrice">0.00</th>
                    <th class="text-right" id="totalRPValue">0.00</th>
                    <th class="text-right" id="totalTPValue">0.00</th>
                    <th></th>
                    <th class="text-right" id="totalDiscountAmount">0.00</th>
                    <th class="text-right" id="totalSalesTax">0.00</th>
                    <th class="text-right" id="totalTPAmount">0.00</th>
                    <th class="text-right" id="totalNetAmount">0.00</th>
                </tr>
            </tfoot>
        </table>
        
        <div class="totals-section">
            <table class="totals-table" style="border: 2px solid #333;">
                <tr>
                    <td>RP Excl. Total:</td>
                    <td class="text-right" id="summaryRPTotal">0.00</td>
                </tr>
                <tr>
                    <td>TP Excl. Total:</td>
                    <td class="text-right" id="summaryTPTotal">0.00</td>
                </tr>
                <tr>
                    <td>Discount Total:</td>
                    <td class="text-right" id="summaryDiscountTotal">0.00</td>
                </tr>
                <tr>
                    <td>Sales Tax:</td>
                    <td class="text-right" id="summaryTotalSalesTax">0.00</td>
                </tr>
                <tr>
                    <td>Total Amount:</td>
                    <td class="text-right" id="totalBill">0.00</td>
                </tr>
                <tr>
                    <td>Advance Tax (<span id="advanceTaxPercent">0</span>%):</td>
                    <td class="text-right" id="advanceTax">0.00</td>
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
        function applyInvoiceSettings() {
            const enableInlineCashDiscount = localStorage.getItem('enableInlineCashDiscount') === 'true';
            const enableInlineCashDiscountAmount = localStorage.getItem('enableInlineCashDiscountAmount') === 'true';
            const enableTradeOffer = localStorage.getItem('enableTradeOffer') === 'true';
            const enableTradeOfferAmount = localStorage.getItem('enableTradeOfferAmount') === 'true';
            const enableFOC = localStorage.getItem('enableFOC') === 'true';
            const enableTaxation = localStorage.getItem('enableTaxation') === 'true';
            const enableInvoiceCashDiscount = localStorage.getItem('enableInvoiceCashDiscount') === 'true';
            const enableInvoiceCashDiscountAmount = localStorage.getItem('enableInvoiceCashDiscountAmount') === 'true';
            const enableShippingFees = localStorage.getItem('enableShippingFees') === 'true';
            
            // Hide/show table headers
            const headers = document.querySelectorAll('.items-table thead th');
            headers[6].style.display = enableInlineCashDiscount ? '' : 'none'; // Disc %
            headers[7].style.display = enableInlineCashDiscountAmount ? '' : 'none'; // Disc Amt
            headers[8].style.display = enableTradeOffer ? '' : 'none'; // TO %
            headers[9].style.display = enableTradeOfferAmount ? '' : 'none'; // TO Amt
            headers[10].style.display = enableTaxation ? '' : 'none'; // GST %
            headers[11].style.display = enableTaxation ? '' : 'none'; // GST Amt
            headers[12].style.display = enableFOC ? '' : 'none'; // FOC Qty
            
            // Hide/show footer cells
            const footerCells = document.querySelectorAll('.items-table tfoot th');
            footerCells[4].style.display = enableInlineCashDiscount ? '' : 'none';
            footerCells[5].style.display = enableInlineCashDiscountAmount ? '' : 'none';
            footerCells[6].style.display = enableTradeOffer ? '' : 'none';
            footerCells[7].style.display = enableTradeOfferAmount ? '' : 'none';
            footerCells[8].style.display = enableTaxation ? '' : 'none';
            footerCells[9].style.display = enableTaxation ? '' : 'none';
            footerCells[10].style.display = enableFOC ? '' : 'none';
            
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
            document.getElementById('projectName').textContent = invoice.project_code ? `${invoice.project_code} - ${invoice.project_name}` : '-';
            document.getElementById('currency').textContent = invoice.currency_name;
            document.getElementById('remarks').textContent = invoice.remarks || '-';
            
            // Set currency symbol for amounts
            const currencySymbol = invoice.currency_symbol || '';
            
            // Populate items
            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';
            
            let totalQty = 0, totalRPUnitPrice = 0, totalTPUnitPrice = 0, totalRPValue = 0, totalTPValue = 0;
            let totalDiscountAmount = 0, totalSalesTax = 0, totalTPAmount = 0, totalNetAmount = 0;
            
            items.forEach((item, index) => {
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td class="text-center">${index + 1}</td>
                    <td>${item.product_name}</td>
                    <td>Unit</td>
                    <td class="text-right">${parseFloat(item.quantity).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.rp_unit_price || 0).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.tp_unit_price || 0).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.rp_total_value || 0).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.tp_total_value || 0).toFixed(2)}</td>
                    <td class="text-right">${parseFloat(item.discount_percent || 0).toFixed(2)}%</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.discount_amount || 0).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.sales_tax || 0).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.tp_amount || 0).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.net_amount).toFixed(2)}</td>
                `;
                
                totalQty += parseFloat(item.quantity);
                totalRPUnitPrice += parseFloat(item.rp_unit_price || 0);
                totalTPUnitPrice += parseFloat(item.tp_unit_price || 0);
                totalRPValue += parseFloat(item.rp_total_value || 0);
                totalTPValue += parseFloat(item.tp_total_value || 0);
                totalDiscountAmount += parseFloat(item.discount_amount || 0);
                totalSalesTax += parseFloat(item.sales_tax || 0);
                totalTPAmount += parseFloat(item.tp_amount || 0);
                totalNetAmount += parseFloat(item.net_amount);
            });
            
            document.getElementById('totalQty').textContent = totalQty.toFixed(2);
            document.getElementById('totalRPUnitPrice').textContent = `${currencySymbol} ${totalRPUnitPrice.toFixed(2)}`;
            document.getElementById('totalTPUnitPrice').textContent = `${currencySymbol} ${totalTPUnitPrice.toFixed(2)}`;
            document.getElementById('totalRPValue').textContent = `${currencySymbol} ${totalRPValue.toFixed(2)}`;
            document.getElementById('totalTPValue').textContent = `${currencySymbol} ${totalTPValue.toFixed(2)}`;
            document.getElementById('totalDiscountAmount').textContent = `${currencySymbol} ${totalDiscountAmount.toFixed(2)}`;
            document.getElementById('totalSalesTax').textContent = `${currencySymbol} ${totalSalesTax.toFixed(2)}`;
            document.getElementById('totalTPAmount').textContent = `${currencySymbol} ${totalTPAmount.toFixed(2)}`;
            document.getElementById('totalNetAmount').textContent = `${currencySymbol} ${totalNetAmount.toFixed(2)}`;
            
            document.getElementById('summaryRPTotal').textContent = `${currencySymbol} ${totalRPValue.toFixed(2)}`;
            document.getElementById('summaryTPTotal').textContent = `${currencySymbol} ${totalTPValue.toFixed(2)}`;
            document.getElementById('summaryDiscountTotal').textContent = `${currencySymbol} ${totalDiscountAmount.toFixed(2)}`;
            document.getElementById('summaryTotalSalesTax').textContent = `${currencySymbol} ${totalSalesTax.toFixed(2)}`;
            document.getElementById('totalBill').textContent = `${currencySymbol} ${totalNetAmount.toFixed(2)}`;
            
            const advanceTaxPercent = parseFloat(invoice.advance_tax_percent || 0);
            const advanceTax = parseFloat(invoice.advance_tax || 0);
            document.getElementById('advanceTaxPercent').textContent = advanceTaxPercent.toFixed(2);
            document.getElementById('advanceTax').textContent = `${currencySymbol} ${advanceTax.toFixed(2)}`;
            document.getElementById('netAmount').textContent = `${currencySymbol} ${parseFloat(invoice.net_amount).toFixed(2)}`;
            
            const netAmountWords = numberToWords(Math.floor(parseFloat(invoice.net_amount)));
            const cents = Math.round((parseFloat(invoice.net_amount) % 1) * 100);
            const centsWords = cents > 0 ? ` and ${numberToWords(cents)} Cents` : '';
            document.getElementById('amountInWords').textContent = `${netAmountWords}${centsWords} Only`;
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