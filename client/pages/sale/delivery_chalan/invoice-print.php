<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Chalan - Print</title>
    <style>
        /* Delivery Chalan: hide price/amount columns */
        .items-table th[data-col-id=price], .items-table td[data-col-id=price],
        .items-table th[data-col-id=gross], .items-table td[data-col-id=gross],
        .items-table th[data-col-id=disc_pct], .items-table td[data-col-id=disc_pct],
        .items-table th[data-col-id=disc_amt], .items-table td[data-col-id=disc_amt],
        .items-table th[data-col-id=to_amt], .items-table td[data-col-id=to_amt],
        .items-table th[data-col-id=foc], .items-table td[data-col-id=foc],
        .items-table th[data-col-id=net], .items-table td[data-col-id=net],
        .totals-section, #amountInWordsSection { display: none !important; }
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

        .totals-horizontal-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            width: 100%;
        }

        .totals-horizontal-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            width: 100%;
        }

        .totals-horizontal-2 .totals-item,
        .totals-horizontal-3 .totals-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }

        .totals-horizontal-2 .totals-item.total-row,
        .totals-horizontal-3 .totals-item.total-row {
            background: #e8e8e8;
            font-weight: bold;
            border: 2px solid #333;
        }

        .totals-horizontal-2 .totals-item#totalBalance,
        .totals-horizontal-2 .totals-item.totalBalance,
        .totals-horizontal-3 .totals-item#totalBalance,
        .totals-horizontal-3 .totals-item.totalBalance {
            background: #f0f8ff;
            border: 1px solid #b3d9ff;
            color: #1f7bff;
            font-weight: bold;
        }

        .totals-horizontal-2 .totals-item#previousBalance,
        .totals-horizontal-2 .totals-item.previousBalance,
        .totals-horizontal-3 .totals-item#previousBalance,
        .totals-horizontal-3 .totals-item.previousBalance {
            background: #fff8f0;
            border: 1px solid #ffc9a3;
            color: #ff8c42;
            font-weight: bold;
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

        .print-btn.active {
            background: #28a745;
        }

        @media print {
            @page {
                size: var(--page-size);
            }
        }

        /* A5 Optimization */
        body.a5-mode {
            font-size: 8px;
            line-height: 1.1;
        }

        .a5-mode .invoice-container {
            max-width: 550px;
            padding: 10px;
        }

        .a5-mode .invoice-header {
            margin-bottom: 10px;
            padding-bottom: 8px;
        }

        .a5-mode .company-info h1 {
            font-size: 14px;
            margin-bottom: 2px;
        }

        .a5-mode .company-info p {
            font-size: 8px;
            margin-bottom: 1px;
        }

        .a5-mode .company-logo {
            max-width: 60px !important;
            max-height: 40px !important;
            margin-bottom: 4px !important;
        }

        .a5-mode .invoice-details h2 {
            font-size: 12px;
            margin-bottom: 4px;
        }

        .a5-mode .invoice-details p {
            font-size: 8px;
        }

        .a5-mode .invoice-meta {
            gap: 10px;
            margin-bottom: 10px;
        }

        .a5-mode .supplier-info,
        .a5-mode .invoice-info,
        .a5-mode .customer-info {
            padding: 6px;
        }

        .a5-mode .supplier-info h3,
        .a5-mode .invoice-info h3,
        .a5-mode .customer-info h3 {
            font-size: 9px;
            margin-bottom: 3px;
            padding-bottom: 2px;
        }

        .a5-mode .supplier-info p,
        .a5-mode .invoice-info p,
        .a5-mode .customer-info p {
            font-size: 8px;
            line-height: 1.15;
        }

        .a5-mode .items-table {
            margin-bottom: 8px;
        }

        .a5-mode .items-table th,
        .a5-mode .items-table td {
            padding: 3px 2px;
            font-size: 7px;
        }

        .a5-mode .items-table th {
            font-size: 7px;
        }

        .a5-mode .totals-section {
            margin-top: 8px;
        }

        .a5-mode .totals-table td {
            padding: 2px 4px;
            font-size: 8px;
        }

        .a5-mode .totals-horizontal-2 .totals-item,
        .a5-mode .totals-horizontal-3 .totals-item {
            padding: 4px 6px;
            font-size: 8px;
        }

        .a5-mode div[style*="margin-top: 20px"] {
            margin-top: 8px !important;
        }

        .a5-mode div[style*="margin-top: 30px"] {
            margin-top: 10px !important;
        }

        .a5-mode div[style*="padding: 15px"] {
            padding: 6px !important;
        }

        .a5-mode div[style*="padding: 12px"] {
            padding: 5px !important;
        }

        .a5-mode div[style*="font-size: 11px"] {
            font-size: 7px !important;
        }

        .a5-mode div[style*="font-size: 10px"] {
            font-size: 7px !important;
        }

        .a5-mode div[style*="font-size: 13px"] {
            font-size: 8px !important;
        }

        .a5-mode #qrCode {
            transform: scale(0.6);
            transform-origin: center;
        }

        .a5-mode div[style*="margin-bottom: 50px"] {
            margin-bottom: 10px !important;
        }

        .a5-mode div[style*="border-bottom: 1px solid"] {
            margin-bottom: 2px !important;
        }

        .a5-mode div[style*="gap: 50px"] {
            gap: 15px !important;
        }

        .a5-mode span[style*="font-size: 10px"] {
            font-size: 6px !important;
        }
    </style>
</head>

<body>
    <div class="no-print">
        <button class="print-btn" onclick="window.print()">Print Invoice</button>
        <button class="print-btn" onclick="setPaperSize('A3')" id="btnA3">A3</button>
        <button class="print-btn" onclick="setPaperSize('A4')" id="btnA4">A4</button>
        <button class="print-btn" onclick="setPaperSize('A5')" id="btnA5">A5</button>
    </div>

    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-info">
                <h1 id="companyName">Loading...</h1>
                <p id="companyAddress">Loading...</p>
                <p id="companyCityState">Loading...</p>
                <p id="companyPhone">Loading...</p>
                <p id="companyEmail">Loading...</p>
            </div>
            <div class="invoice-details">
                <h2>DELIVERY CHALAN</h2>
                <p><strong>Invoice #:</strong> <span id="invoiceNo">Loading...</span></p>
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
            </div>
            <div class="invoice-info">
                <h3>Chalan Information</h3>
                <p><strong>Chalan No:</strong> <span id="chalanNo">-</span></p>
                <p><strong>Delivery Date:</strong> <span id="deliveryDatePrint">-</span></p>
                <p><strong>Sale Invoice #:</strong> <span id="saleInvoiceNo">-</span></p>
                <p><strong>Branch:</strong> <span id="branchName">Loading...</span></p>
                <p><strong>Supplier Man:</strong> <span id="supplierMan">-</span></p>
                <p><strong>Bilty No:</strong> <span id="biltyNo">-</span></p>
                <p><strong>Transport:</strong> <span id="transportName">-</span></p>
                <p><strong>Remarks:</strong> <span id="remarks">-</span></p>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr id="itemsTableHeader">
                    <!-- Headers will be populated dynamically -->
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <tr>
                    <td colspan="14" class="text-center">Loading items...</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background-color: #f5f5f5; font-weight: bold;">
                    <!-- Totals row will be built dynamically -->
                </tr>
            </tfoot>
        </table>

        <div class="totals-section">
            <table class="totals-table" id="totalsTable">
                <tr>
                    <td>Total Bill:</td>
                    <td class="text-right" id="totalBill">0.00</td>
                </tr>
                <tr id="discountPercentRow" style="display: none;">
                    <td>Discount (%):</td>
                    <td class="text-right" id="discountPercent">0.00%</td>
                </tr>
                <tr id="discountAmountRow" style="display: none;">
                    <td>Discount Amount:</td>
                    <td class="text-right" id="discountAmount">0.00</td>
                </tr>
                <tr id="extraDiscount1PercentRow" style="display: none;">
                    <td>Extra Discount 1 (%):</td>
                    <td class="text-right" id="extraDiscount1Percent">0.00%</td>
                </tr>
                <tr id="extraDiscount1AmountRow" style="display: none;">
                    <td>Extra Discount 1 Amt:</td>
                    <td class="text-right" id="extraDiscount1Amount">0.00</td>
                </tr>
                <tr id="extraDiscount2PercentRow" style="display: none;">
                    <td>Extra Discount 2 (%):</td>
                    <td class="text-right" id="extraDiscount2Percent">0.00%</td>
                </tr>
                <tr id="extraDiscount2AmountRow" style="display: none;">
                    <td>Extra Discount 2 Amt:</td>
                    <td class="text-right" id="extraDiscount2Amount">0.00</td>
                </tr>
                <tr id="shippingFeesRow" style="display: none;">
                    <td>Shipping Fees:</td>
                    <td class="text-right" id="shippingFees">0.00</td>
                </tr>
                <tr class="total-row">
                    <td>Net Amount:</td>
                    <td class="text-right" id="netAmount">0.00</td>
                </tr>
                <tr>
                    <td>Amount Paid:</td>
                    <td class="text-right" id="amountPaid">0.00</td>
                </tr>
                <tr>
                    <td>Payment Method:</td>
                    <td class="text-right" id="paymentMethod">-</td>
                </tr>
                <tr>
                    <td>Remaining Balance:</td>
                    <td class="text-right" id="remainingBalance">0.00</td>
                </tr>
                <tr style="border-top: 2px solid #ddd; height: 8px;">
                    <td colspan="2"></td>
                </tr>
                <tr style="background-color: #fff8f0; border: 1px solid #ffc9a3;">
                    <td style="font-weight: bold; padding: 10px; color: #ff8c42;">Previous Balance:</td>
                    <td class="text-right" id="previousBalanceAmount" style="font-weight: bold; padding: 10px; color: #ff8c42;">0.00</td>
                </tr>
                <tr style="background-color: #f0f8ff; border: 1px solid #b3d9ff;">
                    <td style="font-weight: bold; padding: 10px; color: #1f7bff;">Total Balance:</td>
                    <td class="text-right" id="totalBalance" style="font-weight: bold; padding: 10px; color: #1f7bff;">0.00</td>
                </tr>
            </table>
        </div>

        <div style="margin-top: 20px; padding: 15px; background-color: #f9f9f9; border-radius: 5px;" id="amountInWordsSection">
            <p><strong>Amount in Words:</strong> <span id="amountInWords">Loading...</span></p>
        </div>

        <div style="margin-top: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 50px;" id="signaturesSection">
            <div>
                <p style="margin-bottom: 50px;"><strong>Customer Signature:</strong></p>
                <div style="border-bottom: 1px solid #333; width: 200px; margin-bottom: 5px;"></div>
                <p style="font-size: 11px; color: #666;" id="signatureDate1">Date: ___________</p>
            </div>
            <div>
                <p style="margin-bottom: 50px;"><strong>Authorized Signature:</strong></p>
                <div style="border-bottom: 1px solid #333; width: 200px; margin-bottom: 5px;"></div>
                <p style="font-size: 11px; color: #666;" id="signatureDate2">Date: ___________</p>
            </div>
        </div>

        <div
            style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 11px; color: #666; display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <p id="generatedBySection"><strong>Generated by:</strong> <span id="generatedBy">Loading...</span></p>
                <p id="generatedOnSection"><strong>Generated on:</strong> <span id="generatedOn"></span></p>
            </div>
            <div id="qrCodeSection" style="display: none;">
                <div id="qrCode" style="display: inline-block;"></div>
                <p style="font-size: 10px; color: #999; margin-top: 3px;">Scan to view and verify invoice</p>
            </div>
        </div>

        <div
            style="margin-top: 20px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px;">
            <p><em>This is a System Generated Invoice</em></p>
            <div style="margin-top: 10px;">
                <div>Software by: UNISEN SYSTEMS</div>
                <div>Contact: +92 346 8918711 | +92 335 3789981</div>
                <div>Email: support@unisensystems.com</div>
                <div>www.unisensystems.com</div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <script>
        // Paper size management
        function setPaperSize(size) {
            localStorage.setItem('invoicePaperSize', size);
            document.documentElement.style.setProperty('--page-size', size);
            
            // Add/remove A5 class
            if (size === 'A5') {
                document.body.classList.add('a5-mode');
            } else {
                document.body.classList.remove('a5-mode');
            }
            
            // Update button states
            document.querySelectorAll('.print-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn' + size).classList.add('active');
        }
        
        // Load saved paper size
        const savedSize = localStorage.getItem('invoicePaperSize') || 'A4';
        setPaperSize(savedSize);
        
        // Default column configuration
        const defaultColumns = [
            { id: 'serial', label: '#', width: '4%' },
            { id: 'product', label: 'Product', width: '20%' },
            { id: 'unit', label: 'Unit', width: '6%' },
            { id: 'pcs', label: 'Pcs', width: '4%' },
            { id: 'ctn', label: 'Ctn', width: '4%' },
            { id: 'dz', label: 'Dz', width: '4%' },
            { id: 'qty', label: 'Qty', width: '6%' },
            { id: 'price', label: 'Unit Price', width: '8%' },
            { id: 'gross', label: 'Gross Amt', width: '8%' },
            { id: 'disc_pct', label: 'Disc %', width: '5%' },
            { id: 'disc_amt', label: 'Disc Amt', width: '8%' },
            { id: 'to_amt', label: 'T.O Amt', width: '8%' },
            { id: 'foc', label: 'FOC Qty', width: '5%' },
            { id: 'net', label: 'Net Amt', width: '9%' }
        ];

        // Load customization
        const saved = localStorage.getItem('printColumnCustomization');
        const columnConfig = saved ? JSON.parse(saved) : defaultColumns;

        // Apply invoice settings to hide/show columns
        const enableCarton = localStorage.getItem('enableCarton') === 'true';
        const enableDozen = localStorage.getItem('enableDozen') === 'true';
        const enableCashDiscountPercent = localStorage.getItem('enableCashDiscountPercent') === 'true';
        const enableCashDiscountAmount = localStorage.getItem('enableCashDiscountAmount') === 'true';
        const enableTradeOfferDiscount = localStorage.getItem('enableTradeOfferDiscount') === 'true';
        const enableTradeOfferAmount = localStorage.getItem('enableTradeOfferAmount') === 'true';
        const enableFOC = localStorage.getItem('enableFOC') === 'true';
        const enableShippingFees    = localStorage.getItem('enableShippingFees')    === 'true';
        const enableExtraDiscount1  = localStorage.getItem('enableExtraDiscount1')  === 'true';
        const enableExtraDiscount2  = localStorage.getItem('enableExtraDiscount2')  === 'true';

        // Get totals layout preference
        const totalsLayout = localStorage.getItem('totalsLayout') || 'vertical';

        // Apply totals layout
        function applyTotalsLayout() {
            const totalsSection = document.querySelector('.totals-section');
            const totalsTable = document.getElementById('totalsTable');

            if (totalsLayout === 'horizontal-2') {
                totalsSection.style.justifyContent = 'stretch';
                totalsTable.style.display = 'none';

                const container = document.createElement('div');
                container.className = 'totals-horizontal-2';
                container.id = 'totalsHorizontal';
                totalsSection.appendChild(container);
            } else if (totalsLayout === 'horizontal-3') {
                totalsSection.style.justifyContent = 'stretch';
                totalsTable.style.display = 'none';

                const container = document.createElement('div');
                container.className = 'totals-horizontal-3';
                container.id = 'totalsHorizontal';
                totalsSection.appendChild(container);
            }
        }

        applyTotalsLayout();

        // Get child display preference
        const childDisplayMode = localStorage.getItem('childDisplayMode') || 'separate';

        // Build table header based on customization
        function buildTableHeader() {
            const headerRow = document.getElementById('itemsTableHeader');
            headerRow.innerHTML = '';

            // First, determine max unit columns needed
            let maxUnitColumns = 0;
            
            // We'll calculate this when we load the data
            // For now, add Serial and Product columns
            const serialCol = columnConfig.find(c => c.id === 'serial');
            if (!serialCol || serialCol.visible !== false) {
                const th = document.createElement('th');
                th.width = '4%';
                th.textContent = '#';
                th.className = 'text-center';
                th.dataset.colId = 'serial';
                headerRow.appendChild(th);
            }

            const productCol = columnConfig.find(c => c.id === 'product');
            if (!productCol || productCol.visible !== false) {
                const th = document.createElement('th');
                th.width = '20%';
                th.textContent = 'Product';
                th.dataset.colId = 'product';
                headerRow.appendChild(th);
            }

            // Unit columns will be added dynamically when data loads
            // Add a placeholder that we'll replace
            const unitPlaceholder = document.createElement('th');
            unitPlaceholder.id = 'unitColumnsPlaceholder';
            unitPlaceholder.style.display = 'none';
            headerRow.appendChild(unitPlaceholder);

            // Add remaining columns
            const remainingCols = ['price', 'gross', 'disc_pct', 'disc_amt', 'to_amt', 'foc', 'net'];
            remainingCols.forEach(colId => {
                const col = columnConfig.find(c => c.id === colId);
                if (col && col.visible === false) return;

                const th = document.createElement('th');
                th.dataset.colId = colId;
                th.className = 'text-right';

                switch(colId) {
                    case 'price': th.textContent = 'Unit Price'; th.width = '8%'; break;
                    case 'gross': th.textContent = 'Gross Amt'; th.width = '8%'; break;
                    case 'disc_pct': th.textContent = 'Disc %'; th.width = '5%'; break;
                    case 'disc_amt': th.textContent = 'Disc Amt'; th.width = '8%'; break;
                    case 'to_amt': th.textContent = 'T.O Amt'; th.width = '8%'; break;
                    case 'foc': th.textContent = 'FOC Qty'; th.width = '5%'; break;
                    case 'net': th.textContent = 'Net Amt'; th.width = '9%'; break;
                }

                // Apply visibility based on settings
                if (colId === 'disc_pct' && !enableCashDiscountPercent) th.style.display = 'none';
                if (colId === 'disc_amt' && !enableCashDiscountAmount) th.style.display = 'none';
                if (colId === 'to_amt' && !enableTradeOfferAmount) th.style.display = 'none';
                if (colId === 'foc' && !enableFOC) th.style.display = 'none';

                headerRow.appendChild(th);
            });
        }

        // Function to add unit columns to header
        function addUnitColumnsToHeader(maxUnitColumns) {
            const headerRow = document.getElementById('itemsTableHeader');
            const placeholder = document.getElementById('unitColumnsPlaceholder');
            
            if (!placeholder) return;

            // Insert unit columns before the placeholder
            for (let i = 0; i < maxUnitColumns; i++) {
                const th = document.createElement('th');
                th.width = '8%';
                th.className = 'text-right';
                th.textContent = `Unit ${i + 1}`;
                th.dataset.colId = `unit_${i}`;
                headerRow.insertBefore(th, placeholder);
            }

            // Remove placeholder
            placeholder.remove();
        }

        // Build totals row based on customization
        function buildTotalsRow(maxUnitColumns) {
            const totalsRow = document.querySelector('.items-table tfoot tr');
            totalsRow.innerHTML = '';

            // Serial column
            const serialCol = columnConfig.find(c => c.id === 'serial');
            if (!serialCol || serialCol.visible !== false) {
                const th = document.createElement('th');
                th.textContent = '';
                totalsRow.appendChild(th);
            }

            // Product column with "Totals" text
            const productCol = columnConfig.find(c => c.id === 'product');
            if (!productCol || productCol.visible !== false) {
                const th = document.createElement('th');
                th.textContent = 'Totals';
                totalsRow.appendChild(th);
            }

            // Sub Category column - empty
            const thSub = document.createElement('th');
            thSub.dataset.colId = 'subcategory';
            totalsRow.appendChild(thSub);

            // Unit columns - empty cells
            for (let i = 0; i < maxUnitColumns; i++) {
                const th = document.createElement('th');
                th.className = 'text-right';
                th.textContent = '';
                totalsRow.appendChild(th);
            }

            // Remaining columns with totals
            const remainingCols = [
                { id: 'price',    label: '', hasTotal: true,  totalId: 'totalUnitPrice' },
                { id: 'gross',    label: '', hasTotal: true,  totalId: 'totalGrossAmount' },
                { id: 'disc_pct', label: '', hasTotal: false },
                { id: 'disc_amt', label: '', hasTotal: true,  totalId: 'totalDiscountAmountItems' },
                { id: 'to_amt',   label: '', hasTotal: true,  totalId: 'totalTradeOfferAmountItems' },
                { id: 'foc',      label: '', hasTotal: true,  totalId: 'totalFocQty' },
                { id: 'net',      label: '', hasTotal: true,  totalId: 'totalNetAmountItems' }
            ];

            remainingCols.forEach(colDef => {
                const col = columnConfig.find(c => c.id === colDef.id);
                if (col && col.visible === false) return;

                const th = document.createElement('th');
                th.className = 'text-right';
                th.dataset.colId = colDef.id;

                if (colDef.hasTotal) {
                    th.id = colDef.totalId;
                    th.textContent = '0.00';
                }

                // Apply visibility
                if (colDef.id === 'disc_pct' && !enableCashDiscountPercent) th.style.display = 'none';
                if (colDef.id === 'disc_amt' && !enableCashDiscountAmount) th.style.display = 'none';
                if (colDef.id === 'to_amt' && !enableTradeOfferAmount) th.style.display = 'none';
                if (colDef.id === 'foc' && !enableFOC) th.style.display = 'none';

                totalsRow.appendChild(th);
            });
        }

        buildTableHeader();
        // Delivery Chalan: rebuild header with #, Product, Sub Category columns
        (function() {
            const hr = document.getElementById('itemsTableHeader');
            hr.innerHTML = '';
            const th1 = document.createElement('th'); th1.width='4%'; th1.textContent='#'; th1.className='text-center'; hr.appendChild(th1);
            const th2 = document.createElement('th'); th2.width='40%'; th2.textContent='Product'; hr.appendChild(th2);
            const th3 = document.createElement('th'); th3.width='20%'; th3.textContent='Sub Category'; th3.dataset.colId='subcategory'; hr.appendChild(th3);
            const ph = document.createElement('th'); ph.id='unitColumnsPlaceholder'; ph.style.display='none'; hr.appendChild(ph);
        })();
        // buildTotalsRow will be called after data loads with maxUnitColumns

        // Apply column visibility
        document.querySelectorAll('.disc-pct-col').forEach(el => el.style.display = enableCashDiscountPercent ? 'table-cell' : 'none');
        document.querySelectorAll('.disc-amt-col').forEach(el => el.style.display = enableCashDiscountAmount ? 'table-cell' : 'none');
        document.querySelectorAll('.to-pct-col').forEach(el => el.style.display = enableTradeOfferDiscount ? 'table-cell' : 'none');
        document.querySelectorAll('.to-amt-col').forEach(el => el.style.display = enableTradeOfferAmount ? 'table-cell' : 'none');
        document.querySelectorAll('.foc-col').forEach(el => el.style.display = enableFOC ? 'table-cell' : 'none');

        // Get invoice ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const invoiceId = urlParams.get('id');

        if (!invoiceId) {
            alert('Invoice ID is required');
            window.close();
        }

        // Fetch customer's closing balance from ledger
        async function fetchCustomerClosingBalance(customerId, companyId) {
            try {
                const params = new URLSearchParams({
                    customer_id: customerId,
                    as_of_date: new Date().toISOString().split('T')[0] // Today's date
                });
                if (companyId) {
                    params.append('company_id', companyId);
                }
                
                const response = await fetch(`../../../../server/api/sale/pos_invoice/get-customer-closing-balance.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    return parseFloat(data.closing_balance);
                } else {
                    console.error('Error fetching closing balance:', data.message);
                    return 0;
                }
            } catch (error) {
                console.error('Error fetching closing balance:', error);
                return 0;
            }
        }

        // Load company, user and invoice data
        async function loadData() {
            try {
                const [userResponse, invoiceResponse] = await Promise.all([
                    fetch('../../../../server/api/sale/delivery_chalan/get-user.php'),
                    fetch(`../../../../server/api/sale/delivery_chalan/pos-edit.php?id=${invoiceId}`)
                ]);

                const userData = await userResponse.json();
                const invoiceData = await invoiceResponse.json();

                if (userData.success) {
                    document.getElementById('generatedBy').textContent = userData.user.full_name;
                }

                if (invoiceData.success) {
                    if (invoiceData.invoice.company_id) {
                        const companyResponse = await fetch(`../../../../server/api/sale/delivery_chalan/get-company-by-id.php?id=${invoiceData.invoice.company_id}`);
                        const companyData = await companyResponse.json();
                        if (companyData.success) {
                            populateCompanyData(companyData.company);
                        }
                    } else {
                        const companyResponse = await fetch('../../../../server/api/sale/delivery_chalan/get-company.php');
                        const companyData = await companyResponse.json();
                        if (companyData.success) {
                            populateCompanyData(companyData.company);
                        }
                    }
                    
                    const closingBalanceData = 0;
                    populateInvoiceData(invoiceData.invoice, invoiceData.items, closingBalanceData);
                } else {
                    alert('Error loading invoice: ' + invoiceData.message);
                }
            } catch (error) {
                alert('Error loading data: ' + error.message);
            }
        }

        async function loadSingleInvoice(id, createNew = false, addPageBreak = false) {
            const invoiceResponse = await fetch(`../../../../server/api/sale/delivery_chalan/pos-edit.php?id=${id}`);
            const invoiceData = await invoiceResponse.json();
            if (!invoiceData.success) return;

            let container;
            if (createNew) {
                container = document.querySelector('.invoice-container').cloneNode(true);
                container.style.display = '';
                if (addPageBreak) container.style.pageBreakAfter = 'always';
                document.body.appendChild(container);
                // Reset dynamic content in cloned container
                container.querySelector('#itemsTableHeader').innerHTML = '';
                container.querySelector('#itemsTableBody').innerHTML = '';
                container.querySelector('.items-table tfoot tr').innerHTML = '';
                const unitPlaceholder = document.createElement('th');
                unitPlaceholder.id = 'unitColumnsPlaceholder';
                unitPlaceholder.style.display = 'none';
                container.querySelector('#itemsTableHeader').appendChild(unitPlaceholder);
            } else {
                container = document.querySelector('.invoice-container');
                container.style.display = '';
            }

            if (invoiceData.invoice.company_id) {
                const companyResponse = await fetch(`../../../../server/api/sale/delivery_chalan/get-company-by-id.php?id=${invoiceData.invoice.company_id}`);
                const companyData = await companyResponse.json();
                if (companyData.success) populateCompanyData(companyData.company, container);
            } else {
                const companyResponse = await fetch('../../../../server/api/sale/delivery_chalan/get-company.php');
                const companyData = await companyResponse.json();
                if (companyData.success) populateCompanyData(companyData.company, container);
            }

            const closingBalanceData = 0;
            populateInvoiceData(invoiceData.invoice, invoiceData.items, closingBalanceData, container);
        }

        function populateCompanyData(company) {
            // Set company logo only if exists
            if (company.logo_url) {
                const logoImg = document.createElement('img');
                logoImg.src = `../../../assets/uploads/company_logo/${company.logo_url}`;
                logoImg.alt = 'Company Logo';
                logoImg.style.maxWidth = '120px';
                logoImg.style.maxHeight = '80px';
                logoImg.style.marginBottom = '10px';
                logoImg.style.display = 'block';
                const companyInfo = document.querySelector('.company-info');
                companyInfo.insertBefore(logoImg, companyInfo.firstChild);
            }

            document.getElementById('companyName').textContent = company.company_name || company.legal_name || 'Company Name';
            document.getElementById('companyAddress').textContent = company.address || '';
            document.getElementById('companyCityState').textContent = `${company.city || ''}, ${company.state || ''} ${company.zipcode || ''}`.trim();
            document.getElementById('companyPhone').textContent = company.phone ? `Phone: ${company.phone}` : '';
            document.getElementById('companyEmail').textContent = company.email ? `Email: ${company.email}` : '';
        }

        function populateInvoiceData(invoice, items, closingBalanceData) {
            // Populate header info
            document.getElementById('invoiceNo').textContent = invoice.bill_no;
            document.getElementById('invoiceNo').textContent = invoice.chalan_no || invoice.bill_no;
            document.getElementById('invoiceDate').textContent = new Date(invoice.delivery_date || invoice.sale_date).toLocaleDateString();
            // Delivery Chalan specific fields
            const chalanNoEl = document.getElementById('chalanNo'); if (chalanNoEl) chalanNoEl.textContent = invoice.chalan_no || invoice.bill_no;
            const deliveryDateEl = document.getElementById('deliveryDatePrint'); if (deliveryDateEl) deliveryDateEl.textContent = new Date(invoice.delivery_date || invoice.sale_date).toLocaleDateString();
            const saleInvoiceNoEl = document.getElementById('saleInvoiceNo'); if (saleInvoiceNoEl) saleInvoiceNoEl.textContent = invoice.sale_invoice_no || '-';

            // Customer address
            let addressText = '';
            if (invoice.customer_address) addressText += invoice.customer_address;
            const customerAddressEl = document.getElementById('customerAddress')?.closest('p');
            if (localStorage.getItem('hidePrintCustomerAddress') === 'true') {
                customerAddressEl.style.display = 'none';
            } else {
                document.getElementById('customerAddress').textContent = addressText || '-';
            }

            // Customer contact
            const customerPhoneEl = document.getElementById('customerPhone')?.closest('p');
            const customerEmailEl = document.getElementById('customerEmail')?.closest('p');
            if (localStorage.getItem('hidePrintCustomerPhone') === 'true') {
                customerPhoneEl.style.display = 'none';
            } else {
                document.getElementById('customerPhone').textContent = invoice.customer_phone || '-';
            }
            if (localStorage.getItem('hidePrintCustomerEmail') === 'true') {
                customerEmailEl.style.display = 'none';
            } else {
                document.getElementById('customerEmail').textContent = invoice.customer_email || '-';
            }

            const previousBalanceEl = document.getElementById('previousBalance');
            if (previousBalanceEl) {
                const prevEl = previousBalanceEl?.closest('p'); if (prevEl) prevEl.style.display = 'none';
            }

            const branchText = invoice.parent_branch_name ?
                `${invoice.branch_name} (${invoice.branch_type}) - Parent: ${invoice.parent_branch_name}` :
                `${invoice.branch_name} (${invoice.branch_type})`;

            const branchEl = document.getElementById('branchName')?.closest('p');
            const currencyEl = document.getElementById('currency')?.closest('p');

            if (localStorage.getItem('hidePrintBranch') === 'true') {
                if (branchEl) branchEl.style.display = 'none';
            } else {
                const branchNameEl = document.getElementById('branchName');
                if (branchNameEl) branchNameEl.textContent = branchText;
            }

            if (currencyEl) {
                if (localStorage.getItem('hidePrintCurrency') === 'true') {
                    currencyEl.style.display = 'none';
                } else {
                    const currencySpan = document.getElementById('currency');
                    if (currencySpan) currencySpan.textContent = invoice.currency_name;
                }
            }

            const salesOfficerEl = document.getElementById('salesOfficer')?.closest('p');
            const supplierManEl = document.getElementById('supplierMan')?.closest('p');
            const biltyNoEl = document.getElementById('biltyNo')?.closest('p');
            const transportNameEl = document.getElementById('transportName')?.closest('p');
            const remarksEl = document.getElementById('remarks')?.closest('p');

            if (salesOfficerEl) {
                if (localStorage.getItem('hidePrintSalesOfficer') === 'true') {
                    salesOfficerEl.style.display = 'none';
                } else {
                    const salesOfficerSpan = document.getElementById('salesOfficer');
                    if (salesOfficerSpan) salesOfficerSpan.textContent = invoice.sales_officer_name || '-';
                }
            }
            
            if (localStorage.getItem('hidePrintSupplierMan') === 'true') {
                supplierManEl.style.display = 'none';
            } else {
                document.getElementById('supplierMan').textContent = invoice.supplier_man_name || '-';
            }
            if (localStorage.getItem('hidePrintBiltyNo') === 'true') {
                biltyNoEl.style.display = 'none';
            } else {
                document.getElementById('biltyNo').textContent = invoice.bilty_no || '-';
            }
            if (localStorage.getItem('hidePrintTransport') === 'true') {
                transportNameEl.style.display = 'none';
            } else {
                document.getElementById('transportName').textContent = invoice.transport_name || '-';
            }
            if (localStorage.getItem('hidePrintRemarks') === 'true') {
                remarksEl.style.display = 'none';
            } else {
                document.getElementById('remarks').textContent = invoice.remarks || '-';
            }

            // Set currency symbol for amounts
            const currencySymbol = invoice.currency_symbol || '';

            // Populate items
            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';

            let totalQty = 0, totalPcs = 0, totalCtn = 0, totalDz = 0, totalUnitPrice = 0, totalGrossAmount = 0, totalDiscountAmountItems = 0, totalTradeOfferAmountItems = 0, totalFocQty = 0, totalNetAmountItems = 0;

            // Group items by product_id to reconstruct rows with multiple units
            const itemsByProduct = {};
            const childItems = [];
            
            items.forEach(item => {
                if (item.parent_row_id === null) {
                    const key = item.product_id;
                    if (!itemsByProduct[key]) {
                        itemsByProduct[key] = {
                            baseItem: item,
                            units: []
                        };
                    }
                    itemsByProduct[key].units.push({
                        uom_name: item.uom_name,
                        quantity: item.quantity
                    });
                } else {
                    childItems.push(item);
                }
            });

            // Calculate max unit columns needed
            let maxUnitColumns = 0;
            Object.values(itemsByProduct).forEach(productGroup => {
                maxUnitColumns = Math.max(maxUnitColumns, productGroup.units.length);
            });

            // Add unit columns to header
            addUnitColumnsToHeader(maxUnitColumns);
            
            // Build totals row with unit columns
            buildTotalsRow(maxUnitColumns);

            // Render items
            let serialNumber = 0;
            Object.values(itemsByProduct).forEach(productGroup => {
                serialNumber++;
                const item = productGroup.baseItem;
                const row = tbody.insertRow();

                // Serial column
                const serialCol = columnConfig.find(c => c.id === 'serial');
                if (!serialCol || serialCol.visible !== false) {
                    const td = row.insertCell();
                    td.className = 'text-center';
                    td.textContent = serialNumber;
                }

                // Product column
                const productCol = columnConfig.find(c => c.id === 'product');
                if (!productCol || productCol.visible !== false) {
                    const td = row.insertCell();
                    td.textContent = item.product_name;
                    
                    // Add children if inline mode
                    if (childDisplayMode === 'inline') {
                        const productChildren = childItems.filter(c => c.parent_row_id === item.id);
                        if (productChildren.length > 0) {
                            td.innerHTML += '<br><span style="font-size: 10px; color: #666;">(' +
                                productChildren.map(c => `${c.product_name}: ${parseFloat(c.quantity).toFixed(2)}`).join(' | ') +
                                ')</span>';
                        }
                    }
                }

                // Sub Category column
                const subCatTd = row.insertCell();
                subCatTd.dataset.colId = 'subcategory';
                subCatTd.textContent = item.subcategory_name || '';

                // Unit columns - show unit name and quantity
                for (let i = 0; i < maxUnitColumns; i++) {
                    const td = row.insertCell();
                    td.className = 'text-right';
                    
                    if (i < productGroup.units.length) {
                        const unit = productGroup.units[i];
                        td.innerHTML = `<div style="font-size: 10px; color: #666; text-align: left; margin-bottom: 2px;">${unit.uom_name}</div><div>${parseFloat(unit.quantity).toFixed(2)}</div>`;
                    } else {
                        td.style.background = '#f8f9fa';
                        td.style.color = '#dee2e6';
                        td.innerHTML = '<span style="opacity: 0.3;">-</span>';
                    }
                }

                // Remaining columns
                const remainingData = []; // Delivery Chalan: no price/amount columns









                remainingData.forEach(colData => {
                    const col = columnConfig.find(c => c.id === colData.id);
                    if (col && col.visible === false) return;
                    if (!colData.visible) return;

                    const td = row.insertCell();
                    td.className = 'text-right';
                    td.textContent = colData.value;
                    
                    if (!colData.visible) {
                        td.style.display = 'none';
                    }
                });

                // Calculate totals (only count first unit to avoid duplication)
                totalQty += productGroup.units.reduce((sum, u) => sum + parseFloat(u.quantity), 0);
                totalPcs += parseFloat(item.piece || 0);
                totalCtn += parseFloat(item.carton || 0);
                totalDz += parseFloat(item.dozen || 0);
                totalUnitPrice += parseFloat(item.sale_price);
                totalGrossAmount += parseFloat(item.gross_amount);
                totalDiscountAmountItems += parseFloat(item.discount_amount || 0);
                totalTradeOfferAmountItems += parseFloat(item.trade_offer_amount || 0);
                totalFocQty += parseFloat(item.foc_quantity || 0);
                totalNetAmountItems += parseFloat(item.net_amount);
            });
            
            // Render child items if separate mode
            if (childDisplayMode !== 'inline') {
                childItems.forEach(item => {
                    const row = tbody.insertRow();
                    const indent = '<span style="margin-left: 20px;">? </span>';
                    const textColor = 'color: #666;';

                    // Serial column
                    const serialCol = columnConfig.find(c => c.id === 'serial');
                    if (!serialCol || serialCol.visible !== false) {
                        const td = row.insertCell();
                        td.className = 'text-center';
                        td.textContent = '';
                    }

                        // Product column
                    const productCol = columnConfig.find(c => c.id === 'product');
                    if (!productCol || productCol.visible !== false) {
                        const td = row.insertCell();
                        td.innerHTML = `<span style="${textColor}">${indent}${item.product_name}</span>`;
                    }

                    // Sub Category column (child)
                    const subCatTdChild = row.insertCell();
                    subCatTdChild.dataset.colId = 'subcategory';
                    subCatTdChild.textContent = item.subcategory_name || '';

                    // Unit columns - show unit name and quantity for child
                    for (let i = 0; i < maxUnitColumns; i++) {
                        const td = row.insertCell();
                        td.className = 'text-right';
                        
                        if (i === 0) {
                            td.innerHTML = `<div style="font-size: 10px; color: #666; text-align: left; margin-bottom: 2px;">${item.uom_name || 'Unit'}</div><div>${parseFloat(item.quantity).toFixed(2)}</div>`;
                        } else {
                            td.style.background = '#f8f9fa';
                            td.style.color = '#dee2e6';
                            td.innerHTML = '<span style="opacity: 0.3;">-</span>';
                        }
                    }

                    // Remaining columns - all dashes for child items
                    const remainingCols = ['price', 'gross', 'disc_pct', 'disc_amt', 'to_amt', 'foc', 'net'];
                    remainingCols.forEach(colId => {
                        const col = columnConfig.find(c => c.id === colId);
                        if (col && col.visible === false) return;

                        const td = row.insertCell();
                        td.className = 'text-right';
                        td.textContent = '-';

                        // Apply visibility
                        if (colId === 'disc_pct' && !enableCashDiscountPercent) td.style.display = 'none';
                        if (colId === 'disc_amt' && !enableCashDiscountAmount) td.style.display = 'none';
                        if (colId === 'to_amt' && !enableTradeOfferAmount) td.style.display = 'none';
                        if (colId === 'foc' && !enableFOC) td.style.display = 'none';
                    });
                });
            }

            // Auto-hide Sub Category column if no item has a subcategory
            const hasSubcategory = items.some(item => item.subcategory_name);
            if (!hasSubcategory) {
                document.querySelectorAll('[data-col-id="subcategory"]').forEach(el => el.style.display = 'none');
            }

            // Update totals row
            const totalQtyEl = document.getElementById('totalQty');
            const totalPcsEl = document.getElementById('totalPcs');
            const totalCtnEl = document.getElementById('totalCtn');
            const totalDzEl = document.getElementById('totalDz');
            const totalUnitPriceEl = document.getElementById('totalUnitPrice');
            const totalGrossAmountEl = document.getElementById('totalGrossAmount');
            const totalDiscountAmountItemsEl = document.getElementById('totalDiscountAmountItems');
            const totalTradeOfferAmountItemsEl = document.getElementById('totalTradeOfferAmountItems');
            const totalFocQtyEl = document.getElementById('totalFocQty');
            const totalNetAmountItemsEl = document.getElementById('totalNetAmountItems');

            if (totalQtyEl) totalQtyEl.textContent = totalQty.toFixed(2);
            if (totalPcsEl) totalPcsEl.textContent = totalPcs.toFixed(2);
            if (totalCtnEl) totalCtnEl.textContent = totalCtn.toFixed(2);
            if (totalDzEl) totalDzEl.textContent = totalDz.toFixed(2);
            if (totalUnitPriceEl) totalUnitPriceEl.textContent = `${currencySymbol} ${totalUnitPrice.toFixed(2)}`;
            if (totalGrossAmountEl) totalGrossAmountEl.textContent = `${currencySymbol} ${totalGrossAmount.toFixed(2)}`;
            if (totalDiscountAmountItemsEl) totalDiscountAmountItemsEl.textContent = `${currencySymbol} ${totalDiscountAmountItems.toFixed(2)}`;
            if (totalTradeOfferAmountItemsEl) totalTradeOfferAmountItemsEl.textContent = `${currencySymbol} ${totalTradeOfferAmountItems.toFixed(2)}`;
            if (totalFocQtyEl) totalFocQtyEl.textContent = totalFocQty.toFixed(2);
            if (totalNetAmountItemsEl) totalNetAmountItemsEl.textContent = `${currencySymbol} ${totalNetAmountItems.toFixed(2)}`;

            // Populate totals with currency symbol
            const enableInvoiceCashDiscountPercent = localStorage.getItem('enableInvoiceCashDiscountPercent') === 'true';
            const enableInvoiceCashDiscountAmount = localStorage.getItem('enableInvoiceCashDiscountAmount') === 'true';

            // Calculate paid and balance from receive voucher
            const amountPaid = parseFloat(invoice.amount_paid) || 0;
            const remainingBalance = parseFloat(invoice.net_amount) - amountPaid;
            
            // Use closing balance from ledger
            const closingBalance = closingBalanceData || 0;
            const netAmount = parseFloat(invoice.net_amount);
            const totalBalance = closingBalance; // Total Balance from customer ledger
            const previousBalance = closingBalance - netAmount; // Total Balance - Current Invoice Amount

            const totalBalanceCalculated = amountPaid - totalBalance;
            const totalsData = [
                { id: 'totalBill', label: localStorage.getItem('labelTotalBill') || 'Total Bill', value: `${currencySymbol} ${parseFloat(invoice.total_bill).toFixed(2)}`, show: localStorage.getItem('hidePrintTotalBill') !== 'true' },
                { id: 'discountPercent', label: 'Discount (%)', value: parseFloat(invoice.total_discount_percent).toFixed(2) + '%', show: enableInvoiceCashDiscountPercent },
                { id: 'discountAmount', label: localStorage.getItem('labelDiscountAmount') || 'Discount Amount', value: `${currencySymbol} ${parseFloat(invoice.total_discount_amount).toFixed(2)}`, show: enableInvoiceCashDiscountAmount },
                { id: 'extraDiscount1Percent', label: 'Extra Discount 1 (%)', value: `${parseFloat(invoice.extra_discount_1_percent || 0).toFixed(2)}%`, show: parseFloat(invoice.extra_discount_1_percent || 0) > 0 },
                { id: 'extraDiscount1Amount',  label: 'Extra Discount 1 Amt', value: `${currencySymbol} ${parseFloat(invoice.extra_discount_1_amount  || 0).toFixed(2)}`, show: parseFloat(invoice.extra_discount_1_amount  || 0) > 0 },
                { id: 'extraDiscount2Percent', label: 'Extra Discount 2 (%)', value: `${parseFloat(invoice.extra_discount_2_percent || 0).toFixed(2)}%`, show: parseFloat(invoice.extra_discount_2_percent || 0) > 0 },
                { id: 'extraDiscount2Amount',  label: 'Extra Discount 2 Amt', value: `${currencySymbol} ${parseFloat(invoice.extra_discount_2_amount  || 0).toFixed(2)}`, show: parseFloat(invoice.extra_discount_2_amount  || 0) > 0 },
                { id: 'shippingFees', label: localStorage.getItem('labelShippingFees') || 'Shipping Fees', value: `${currencySymbol} ${parseFloat(invoice.shipping_fees || 0).toFixed(2)}`, show: enableShippingFees && invoice.shipping_fees },
                { id: 'netAmount', label: localStorage.getItem('labelNetAmount') || 'Net Amount', value: `${currencySymbol} ${parseFloat(invoice.net_amount).toFixed(2)}`, show: localStorage.getItem('hidePrintNetAmount') !== 'true', isTotal: true },
                { id: 'amountPaid', label: localStorage.getItem('labelAmountPaid') || 'Amount Paid', value: `${currencySymbol} ${amountPaid.toFixed(2)}`, show: localStorage.getItem('hidePrintAmountPaid') !== 'true' },
                { id: 'paymentMethod', label: localStorage.getItem('labelPaymentMethod') || 'Payment Method', value: invoice.payment_method || '-', show: localStorage.getItem('hidePrintPaymentMethod') !== 'true' },
                { id: 'remainingBalance', label: localStorage.getItem('labelRemainingBalance') || 'Remaining Balance', value: `${currencySymbol} ${remainingBalance.toFixed(2)}`, show: localStorage.getItem('hidePrintRemainingBalance') !== 'true' },
                { id: 'previousBalance', label: 'Previous Balance', value: `${currencySymbol} ${Math.abs(previousBalance).toFixed(2)} Dr`, show: true, isSpecial: true },
                { id: 'totalBalance', label: 'Total Balance', value: `${currencySymbol} ${Math.abs(totalBalanceCalculated).toFixed(2)} Dr`, show: true, isSpecial: true }
            ];

            if (totalsLayout === 'horizontal-2' || totalsLayout === 'horizontal-3') {
                const container = document.getElementById('totalsHorizontal');
                totalsData.filter(item => item.show).forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'totals-item' + (item.isTotal ? ' total-row' : '') + (item.isSpecial ? ` ${item.id}` : '');
                    div.id = item.id;
                    div.innerHTML = `<span>${item.label}:</span><span>${item.value}</span>`;
                    container.appendChild(div);
                });
            } else {
                document.getElementById('totalBill').textContent = `${currencySymbol} ${parseFloat(invoice.total_bill).toFixed(2)}`;

                if (enableInvoiceCashDiscountPercent) {
                    document.getElementById('discountPercentRow').style.display = '';
                    document.getElementById('discountPercent').textContent = parseFloat(invoice.total_discount_percent).toFixed(2) + '%';
                }

                if (enableInvoiceCashDiscountAmount) {
                    document.getElementById('discountAmountRow').style.display = '';
                    document.getElementById('discountAmount').textContent = `${currencySymbol} ${parseFloat(invoice.total_discount_amount).toFixed(2)}`;
                }

                if (parseFloat(invoice.extra_discount_1_percent || 0) > 0) {
                    document.getElementById('extraDiscount1PercentRow').style.display = '';
                    document.getElementById('extraDiscount1Percent').textContent = parseFloat(invoice.extra_discount_1_percent).toFixed(2) + '%';
                }
                if (parseFloat(invoice.extra_discount_1_amount || 0) > 0) {
                    document.getElementById('extraDiscount1AmountRow').style.display = '';
                    document.getElementById('extraDiscount1Amount').textContent = `${currencySymbol} ${parseFloat(invoice.extra_discount_1_amount).toFixed(2)}`;
                }
                if (parseFloat(invoice.extra_discount_2_percent || 0) > 0) {
                    document.getElementById('extraDiscount2PercentRow').style.display = '';
                    document.getElementById('extraDiscount2Percent').textContent = parseFloat(invoice.extra_discount_2_percent).toFixed(2) + '%';
                }
                if (parseFloat(invoice.extra_discount_2_amount || 0) > 0) {
                    document.getElementById('extraDiscount2AmountRow').style.display = '';
                    document.getElementById('extraDiscount2Amount').textContent = `${currencySymbol} ${parseFloat(invoice.extra_discount_2_amount).toFixed(2)}`;
                }

                if (enableShippingFees && invoice.shipping_fees && parseFloat(invoice.shipping_fees) > 0) {
                    const shippingFeesRow = document.getElementById('shippingFeesRow');
                    shippingFeesRow.style.display = '';
                    shippingFeesRow.querySelector('td:first-child').textContent = localStorage.getItem('labelShippingFees') || 'Shipping Fees';
                    document.getElementById('shippingFees').textContent = `${currencySymbol} ${parseFloat(invoice.shipping_fees).toFixed(2)}`;
                }

                document.getElementById('netAmount').textContent = `${currencySymbol} ${parseFloat(invoice.net_amount).toFixed(2)}`;
                document.getElementById('amountPaid').textContent = `${currencySymbol} ${amountPaid.toFixed(2)}`;
                document.getElementById('paymentMethod').textContent = invoice.payment_method || '-';
                document.getElementById('remainingBalance').textContent = `${currencySymbol} ${remainingBalance.toFixed(2)}`;

                // Set Previous Balance from sale_invoice.previous_balance column
                const invoicePreviousBalance = parseFloat(invoice.previous_balance) || 0;
                const previousBalanceSign = invoicePreviousBalance >= 0 ? 'Dr' : 'Cr';
                document.getElementById('previousBalanceAmount').textContent = `${currencySymbol} ${Math.abs(invoicePreviousBalance).toFixed(2)} ${previousBalanceSign}`;
                
                // Calculate Total Balance with proper accounting sign handling
                // Cr (Credit) is treated as negative, Dr (Debit) is positive
                let previousBalanceForCalculation = Math.abs(invoicePreviousBalance);
                if (previousBalanceSign === 'Cr') {
                    previousBalanceForCalculation = -previousBalanceForCalculation;
                }
                
                // Total Balance = Previous Balance + Remaining Balance
                const totalBalanceValue = previousBalanceForCalculation + remainingBalance;
                const totalBalanceSign = totalBalanceValue >= 0 ? 'Dr' : 'Cr';
                
                document.getElementById('totalBalance').textContent = `${currencySymbol} ${Math.abs(totalBalanceValue).toFixed(2)} ${totalBalanceSign}`;
            }

            // Convert net amount to words
            const netAmountWords = numberToWords(Math.floor(parseFloat(invoice.net_amount)));
            const cents = Math.round((parseFloat(invoice.net_amount) % 1) * 100);
            const centsWords = cents > 0 ? ` and ${numberToWords(cents)} Cents` : '';
            document.getElementById('amountInWords').textContent = `${netAmountWords}${centsWords} Only`;

            // Apply hide settings
            if (localStorage.getItem('hidePrintAmountInWords') === 'true') {
                document.getElementById('amountInWordsSection').style.display = 'none';
            }
            if (localStorage.getItem('hidePrintSignatures') === 'true') {
                document.getElementById('signaturesSection').style.display = 'none';
            }
            if (localStorage.getItem('hidePrintGeneratedBy') === 'true') {
                document.getElementById('generatedBySection').style.display = 'none';
            }
            if (localStorage.getItem('hidePrintGeneratedOn') === 'true') {
                document.getElementById('generatedOnSection').style.display = 'none';
            }

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
                        width: 100,
                        height: 100,
                        correctLevel: QRCode.CorrectLevel.H
                    });
                }
            }, 500);
        }

        // Set generated on date
        document.getElementById('generatedOn').textContent = new Date().toLocaleString();

        // Load data on page load
        loadData();

        // Generate QR Code after data loads
        window.addEventListener('load', function () {
            // QR code is handled inside populateInvoiceData via new QRCode()
        });

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
    </script>
</body>

</html>
