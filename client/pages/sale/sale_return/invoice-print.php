<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Return - Print</title>
    <style>
        @media print {
            body {
                margin: 0;
            }

            .no-print {
                display: none;
            }
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
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
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

        .supplier-info,
        .invoice-info {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .supplier-info h3,
        .invoice-info h3 {
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
                <img id="companyLogo" src="" alt="Company Logo"
                    style="max-width: 120px; max-height: 80px; margin-bottom: 10px;">
                <h1 id="companyName">Loading...</h1>
                <p id="companyAddress">Loading...</p>
                <p id="companyCityState">Loading...</p>
                <p id="companyPhone">Loading...</p>
                <p id="companyEmail">Loading...</p>
            </div>
            <div class="invoice-details">
                <h2>SALE RETURN</h2>
                <p><strong>Return #:</strong> <span id="invoiceNo">Loading...</span></p>
                <p><strong>Sale Invoice #:</strong> <span id="saleInvoiceNo">-</span></p>
                <p><strong>Date:</strong> <span id="invoiceDate">Loading...</span></p>
            </div>
        </div>

        <div class="invoice-meta">
            <div class="customer-info">
                <h3>Customer Information</h3>
                <p><strong>Name:</strong> <span id="customerName">Loading...</span></p>
                <p><strong>Address:</strong> <span id="customerAddress">Loading...</span></p>
                <p><strong>Phone:</strong> <span id="customerPhone">-</span></p>
                <p><strong>Email:</strong> <span id="customerEmail">-</span></p>
                <p><strong>Previous Balance:</strong> <span id="previousBalance">Loading...</span></p>
            </div>
            <div class="invoice-info">
                <h3>Return Information</h3>
                <p><strong>Branch:</strong> <span id="branchName">Loading...</span></p>
                <p><strong>Currency:</strong> <span id="currency">Loading...</span></p>
                <p><strong>Sales Officer:</strong> <span id="salesOfficer">-</span></p>
                <p><strong>Supplier Man:</strong> <span id="supplierMan">-</span></p>
                <p><strong>Sale Invoice #:</strong> <span id="saleInvoiceNoInfo">-</span></p>
                <p><strong>Remarks:</strong> <span id="remarks">-</span></p>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="4%">#</th>
                    <th width="20%">Product</th>
                    <th width="15%">Quantities</th>
                    <th width="8%" class="text-right">Unit Price</th>
                    <th width="8%" class="text-right">Gross Amt</th>
                    <th width="5%" class="text-right">Disc %</th>
                    <th width="8%" class="text-right">Disc Amt</th>
                    <th width="8%" class="text-right">T.O Amt</th>
                    <th width="5%" class="text-right">TAX %</th>
                    <th width="8%" class="text-right">TAX AMT</th>
                    <th width="5%" class="text-right">FOC Qty</th>
                    <th width="9%" class="text-right">Net Amt</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <tr>
                    <td colspan="14" class="text-center">Loading items...</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background-color: #f5f5f5; font-weight: bold;">
                    <th colspan="3">Totals</th>
                    <th class="text-right" id="totalUnitPrice">0.00</th>
                    <th class="text-right" id="totalGrossAmount">0.00</th>
                    <th></th>
                    <th class="text-right" id="totalDiscountAmountItems">0.00</th>
                    <th class="text-right" id="totalTradeOfferAmountItems">0.00</th>
                    <th></th>
                    <th class="text-right" id="totalGstAmountItems">0.00</th>
                    <th class="text-right" id="totalFocQty">0.00</th>
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
                    <td>Discount:</td>
                    <td class="text-right" id="discountAmount">0.00</td>
                </tr>
                <tr id="extraDiscount1Row" style="display:none;">
                    <td>Extra Disc 1:</td>
                    <td class="text-right" id="extraDiscount1Amt">0.00</td>
                </tr>
                <tr id="extraDiscount2Row" style="display:none;">
                    <td>Extra Disc 2:</td>
                    <td class="text-right" id="extraDiscount2Amt">0.00</td>
                </tr>
                <tr id="shippingFeesRow" style="display:none;">
                    <td>Shipping Fees:</td>
                    <td class="text-right" id="shippingFeesAmt">0.00</td>
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
                <p style="margin-bottom: 50px;"><strong>Customer Signature:</strong></p>
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

        <div
            style="margin-top: 20px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px;">
            <p><em>This is a System Generated Return</em></p>
            <div style="margin-top: 10px;">
                <div>Software by: UNISEN SYSTEMS</div>
                <div>Contact: +92 346 8918711 | +92 335 3789981</div>
                <div>Email: support@unisensystems.com</div>
                <div>www.unisensystems.com</div>
            </div>
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
            document.getElementById('saleInvoiceNo').textContent = invoice.sale_invoice_no || '-';
            document.getElementById('invoiceDate').textContent = new Date(invoice.sale_date).toLocaleDateString();
            document.getElementById('customerName').textContent = `${invoice.customer_code} - ${invoice.customer_name}`;
            
            // Customer address
            let addressText = '';
            if (invoice.customer_address) addressText += invoice.customer_address;
            document.getElementById('customerAddress').textContent = addressText || '-';
            
            // Customer contact
            document.getElementById('customerPhone').textContent = invoice.customer_phone || '-';
            document.getElementById('customerEmail').textContent = invoice.customer_email || '-';
            
            document.getElementById('previousBalance').textContent = invoice.previous_balance;
            const branchText = invoice.parent_branch_name ?
                `${invoice.branch_name} (${invoice.branch_type}) - Parent: ${invoice.parent_branch_name}` :
                `${invoice.branch_name} (${invoice.branch_type})`;
            document.getElementById('branchName').textContent = branchText;
            document.getElementById('currency').textContent = invoice.currency_name;
            document.getElementById('salesOfficer').textContent = invoice.sales_officer_name || '-';
            document.getElementById('supplierMan').textContent = invoice.supplier_man_name || '-';
            document.getElementById('saleInvoiceNoInfo').textContent = invoice.sale_invoice_no || '-';
            document.getElementById('remarks').textContent = invoice.remarks || '-';

            // Set currency symbol for amounts
            const currencySymbol = invoice.currency_symbol || '';

            // Populate items
            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';

            let totalUnitPrice = 0, totalGrossAmount = 0, totalDiscountAmountItems = 0, totalTradeOfferAmountItems = 0, totalGstAmountItems = 0, totalFocQty = 0, totalNetAmountItems = 0;

            items.forEach((item, index) => {
                // Build quantities display from unit_entries
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

                const row = tbody.insertRow();
                row.innerHTML = `
                    <td class="text-center">${index + 1}</td>
                    <td>${item.product_name}</td>
                    <td>${unitQtyDisplay}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.sale_price).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.gross_amount).toFixed(2)}</td>
                    <td class="text-right">${parseFloat(item.discount_percent || 0).toFixed(2)}%</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.discount_amount || 0).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.trade_offer_amount || 0).toFixed(2)}</td>
                    <td class="text-right">${parseFloat(item.tax_percent || 0).toFixed(2)}%</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.tax_amount || 0).toFixed(2)}</td>
                    <td class="text-right">${parseFloat(item.foc_quantity || 0).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol} ${parseFloat(item.net_amount).toFixed(2)}</td>
                `;

                totalUnitPrice += parseFloat(item.sale_price);
                totalGrossAmount += parseFloat(item.gross_amount);
                totalDiscountAmountItems += parseFloat(item.discount_amount || 0);
                totalTradeOfferAmountItems += parseFloat(item.trade_offer_amount || 0);
                totalGstAmountItems += parseFloat(item.tax_amount || 0);
                totalFocQty += parseFloat(item.foc_quantity || 0);
                totalNetAmountItems += parseFloat(item.net_amount);
            });

            // Update totals row
            document.getElementById('totalUnitPrice').textContent = `${currencySymbol} ${totalUnitPrice.toFixed(2)}`;
            document.getElementById('totalGrossAmount').textContent = `${currencySymbol} ${totalGrossAmount.toFixed(2)}`;
            document.getElementById('totalDiscountAmountItems').textContent = `${currencySymbol} ${totalDiscountAmountItems.toFixed(2)}`;
            document.getElementById('totalTradeOfferAmountItems').textContent = `${currencySymbol} ${totalTradeOfferAmountItems.toFixed(2)}`;
            document.getElementById('totalGstAmountItems').textContent = `${currencySymbol} ${totalGstAmountItems.toFixed(2)}`;
            document.getElementById('totalFocQty').textContent = totalFocQty.toFixed(2);
            document.getElementById('totalNetAmountItems').textContent = `${currencySymbol} ${totalNetAmountItems.toFixed(2)}`;

            // Populate totals with currency symbol
            document.getElementById('totalBill').textContent = `${currencySymbol} ${parseFloat(invoice.total_bill).toFixed(2)}`;
            document.getElementById('discountAmount').textContent = `${currencySymbol} ${parseFloat(invoice.total_discount_amount).toFixed(2)}`;

            // Extra Discount 1
            const ed1 = parseFloat(invoice.extra_discount_1_amount || 0);
            const ed1Row = document.getElementById('extraDiscount1Row');
            if (ed1 > 0) { ed1Row.style.display = ''; document.getElementById('extraDiscount1Amt').textContent = `${currencySymbol} ${ed1.toFixed(2)}`; }
            else { ed1Row.style.display = 'none'; }

            // Extra Discount 2
            const ed2 = parseFloat(invoice.extra_discount_2_amount || 0);
            const ed2Row = document.getElementById('extraDiscount2Row');
            if (ed2 > 0) { ed2Row.style.display = ''; document.getElementById('extraDiscount2Amt').textContent = `${currencySymbol} ${ed2.toFixed(2)}`; }
            else { ed2Row.style.display = 'none'; }

            // Shipping Fees
            const shipping = parseFloat(invoice.shipping_fees || 0);
            const shippingRow = document.getElementById('shippingFeesRow');
            if (shipping > 0) { shippingRow.style.display = ''; document.getElementById('shippingFeesAmt').textContent = `${currencySymbol} ${shipping.toFixed(2)}`; }
            else { shippingRow.style.display = 'none'; }

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