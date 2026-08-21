<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Return - Print</title>
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
                <h2>PURCHASE RETURN</h2>
                <p><strong>Invoice #:</strong> <span id="invoiceNo">Loading...</span></p>
                <p><strong>Date:</strong> <span id="invoiceDate">Loading...</span></p>
            </div>
        </div>
        
        <div class="invoice-meta">
            <div class="supplier-info">
                <h3>Supplier Information</h3>
                <p><strong>Name:</strong> <span id="supplierName">Loading...</span></p>
                <p><strong>Sub Account:</strong> <span id="subAccountName">-</span></p>
                <p><strong>Purchase Invoice #:</strong> <span id="purchaseInvoiceNo">-</span></p>
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
        <th width="18%">Product</th>
        <th width="10%">Batch No</th>
        <th width="8%">Expiry</th>
        <th width="13%">Quantities</th>
        <th width="9%" class="text-right">Unit Price</th>
        <th width="9%" class="text-right">Gross Amt</th>
        <th width="6%" class="text-right">Disc %</th>
        <th width="9%" class="text-right">Disc Amt</th>
        <th width="6%" class="text-right">TO %</th>
        <th width="9%" class="text-right">TO Amt</th>
        <th width="6%" class="text-right">Tax %</th>
        <th width="9%" class="text-right">Tax Amt</th>
        <th width="6%" class="text-right">FOC Qty</th>
        <th width="11%" class="text-right">Net Amt</th>
    </tr>
</thead>
<tbody id="itemsTableBody">
    <tr>
        <td colspan="15" class="text-center">Loading items...</td>
    </tr>
</tbody>
<tfoot>
    <tr style="background-color: #f5f5f5; font-weight: bold;">
        <th colspan="5">Totals</th>
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
                <tr>
                    <td>Discount (%):</td>
                    <td class="text-right" id="discountPercent">0.00%</td>
                </tr>
                <tr>
                    <td>Discount Amount:</td>
                    <td class="text-right" id="discountAmount">0.00</td>
                </tr>
                <tr>
                    <td>Shipping Fees:</td>
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
            <p><em>This is a System Generated Return</em></p>
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
        
        // Load company, user and invoice data
        async function loadData() {
            try {
                const [companyResponse, userResponse, invoiceResponse] = await Promise.all([
                    fetch('../../../../server/api/purchase/purchase_return/get-company.php'),
                    fetch('../../../../server/api/purchase/purchase_return/get-user.php'),
                    fetch(`../../../../server/api/purchase/purchase_return/return-edit.php?id=${invoiceId}`)
                ]);
                
                const companyData = await companyResponse.json();
                const userData = await userResponse.json();
                const invoiceData = await invoiceResponse.json();
                
                if (invoiceData.success && invoiceData.invoice.company_name) {
                    populateCompanyData({
                        company_name: invoiceData.invoice.company_name,
                        legal_name: invoiceData.invoice.legal_name,
                        email: invoiceData.invoice.email,
                        phone: invoiceData.invoice.phone,
                        address: invoiceData.invoice.address,
                        city: invoiceData.invoice.city,
                        state: invoiceData.invoice.state,
                        zipcode: invoiceData.invoice.zipcode,
                        logo_url: invoiceData.invoice.logo_url
                    });
                } else if (companyData.success) {
                    populateCompanyData(companyData.company);
                }
                
                if (userData.success) {
                    document.getElementById('generatedBy').textContent = userData.user.full_name;
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
            document.getElementById('companyCityState').textContent = `${company.city || ''}, ${company.state || ''} ${company.zipcode || ''}`.trim();
            document.getElementById('companyPhone').textContent = company.phone ? `Phone: ${company.phone}` : '';
            document.getElementById('companyEmail').textContent = company.email ? `Email: ${company.email}` : '';
        
        // Set company logo
        const logoElement = document.getElementById('companyLogo');
        if (company.logo_url && logoElement) {
            logoElement.src = `../../../assets/uploads/company_logo/${company.logo_url}`;
        }
        }
        
        function populateInvoiceData(invoice, items) {
            // Populate header info
            document.getElementById('invoiceNo').textContent = invoice.bill_no;
            document.getElementById('invoiceDate').textContent = new Date(invoice.purchase_date).toLocaleDateString();
            document.getElementById('supplierName').textContent = `${invoice.supplier_code} - ${invoice.supplier_name}`;
            document.getElementById('subAccountName').textContent = invoice.sub_account_name || '-';
            document.getElementById('purchaseInvoiceNo').textContent = invoice.purchase_invoice_no || '-';
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
            
            let totalUnitPrice = 0, totalGrossAmount = 0, totalDiscountAmountItems = 0, totalTradeOfferAmountItems = 0, totalGSTAmountItems = 0, totalFOCQty = 0, totalNetAmountItems = 0;
            
            items.forEach((item, index) => {
                // Build quantities display from unit_entries (same as purchase invoice print)
                const unitMap = {};
                (item.unit_entries || []).forEach(entry => {
                    const qty = parseFloat(entry.quantity);
                    if (qty > 0) {
                        const unitName = entry.uom_name || 'Unit';
                        unitMap[unitName] = (unitMap[unitName] || 0) + qty;
                    }
                });
                let unitQtyDisplay = Object.keys(unitMap).map(name => `${unitMap[name].toFixed(2)} ${name}`).join(', ');
                if (!unitQtyDisplay) unitQtyDisplay = '-';

            const batchNo = item.batch_no || '-';
    const batchExpiry = item.batch_expiry_date 
        ? new Date(item.batch_expiry_date).toLocaleDateString() 
        : '-';

    const row = tbody.insertRow();
    row.innerHTML = `
        <td class="text-center">${index + 1}</td>
        <td>${item.product_name}</td>
        <td class="text-center">${batchNo}</td>
        <td class="text-center">${batchExpiry}</td>
                    <td>${unitQtyDisplay}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.purchase_price).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.gross_amount).toFixed(2)}</td>
                    <td class="text-right">${parseFloat(item.discount_percent).toFixed(2)}%</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.discount_amount).toFixed(2)}</td>
                    <td class="text-right">${parseFloat(item.trade_offer_percent || 0).toFixed(2)}%</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.trade_offer_amount || 0).toFixed(2)}</td>
                    <td class="text-right">${parseFloat(item.tax_percent || 0).toFixed(2)}%</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.tax_amount || 0).toFixed(2)}</td>
                    <td class="text-right">${parseFloat(item.foc_quantity || 0).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.net_amount).toFixed(2)}</td>
                `;

                totalUnitPrice += parseFloat(item.purchase_price);
                totalGrossAmount += parseFloat(item.gross_amount);
                totalDiscountAmountItems += parseFloat(item.discount_amount);
                totalTradeOfferAmountItems += parseFloat(item.trade_offer_amount || 0);
                totalGSTAmountItems += parseFloat(item.tax_amount || 0);
                totalFOCQty += parseFloat(item.foc_quantity || 0);
                totalNetAmountItems += parseFloat(item.net_amount);
            });
            
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
            document.getElementById('shippingFees').textContent = `${currencySymbol} ${parseFloat(invoice.shipping_fees || 0).toFixed(2)}`;
            document.getElementById('netAmount').textContent = `${currencySymbol} ${parseFloat(invoice.net_amount).toFixed(2)}`;
            
            // Convert net amount to words
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
