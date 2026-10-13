<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Invoice</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .status {
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .status.verified {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.invalid {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.loading {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .status-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .invoice-details {
            display: none;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
        }
        .detail-value {
            color: #333;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="status loading" id="statusBox">
            <div class="status-icon">⏳</div>
            <h2>Verifying Invoice...</h2>
            <p>Please wait while we verify the invoice with our system.</p>
        </div>
        
        <div class="invoice-details" id="invoiceDetails">
            <h3 style="margin-bottom: 20px; color: #333;">Invoice Details</h3>
            <div class="detail-row">
                <span class="detail-label">Invoice Number:</span>
                <span class="detail-value" id="invoiceNo">-</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value" id="invoiceDate">-</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Customer:</span>
                <span class="detail-value" id="customerName">-</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Total Amount:</span>
                <span class="detail-value" id="totalAmount">-</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value" id="invoiceStatus">-</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Branch:</span>
                <span class="detail-value" id="branchName">-</span>
            </div>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const invoiceNo = urlParams.get('invoice');
        const token = urlParams.get('token');

        if (!invoiceNo || !token) {
            showError('Invalid verification link');
        } else {
            verifyInvoice(invoiceNo, token);
        }

        async function verifyInvoice(invoiceNo, token) {
            try {
                const response = await fetch(`../../../../server/api/sale/pos_invoice/verify-invoice.php?invoice=${invoiceNo}&token=${token}`);
                const data = await response.json();

                if (data.success && data.invoice) {
                    showVerified(data.invoice);
                } else {
                    showError(data.message || 'Invoice not found or invalid');
                }
            } catch (error) {
                showError('Error verifying invoice: ' + error.message);
            }
        }

        function showVerified(invoice) {
            const statusBox = document.getElementById('statusBox');
            statusBox.className = 'status verified';
            statusBox.innerHTML = `
                <div class="status-icon">✓</div>
                <h2>Invoice Verified</h2>
                <p>This invoice is authentic and exists in our system.</p>
            `;

            document.getElementById('invoiceNo').textContent = invoice.bill_no;
            document.getElementById('invoiceDate').textContent = new Date(invoice.sale_date).toLocaleDateString();
            document.getElementById('customerName').textContent = invoice.customer_name;
            document.getElementById('totalAmount').textContent = `${invoice.currency_symbol} ${parseFloat(invoice.net_amount).toFixed(2)}`;
            document.getElementById('invoiceStatus').textContent = invoice.status;
            document.getElementById('branchName').textContent = invoice.branch_name;

            document.getElementById('invoiceDetails').style.display = 'block';
        }

        function showError(message) {
            const statusBox = document.getElementById('statusBox');
            statusBox.className = 'status invalid';
            statusBox.innerHTML = `
                <div class="status-icon">✗</div>
                <h2>Verification Failed</h2>
                <p>${message}</p>
            `;
        }
    </script>
</body>
</html>
