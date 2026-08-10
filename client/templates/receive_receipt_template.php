<?php
// This file handles receive voucher receipt generation and styling
function getPaymentModeName($mode) {
    switch ($mode) {
        case 1:
            return 'Cash';
        case 2:
            return 'Bank';
        case 3:
            return 'Online';
        default:
            return 'Unknown';
    }
}

function generateReceiveReceipt($voucherData) {
    try {
        // Set default values for potentially missing fields
        $payment_mode = isset($voucherData['payment_mode_name']) ? getPaymentModeName($voucherData['payment_mode_name']) : 'N/A';
        $voucherData['account_head_name'] = isset($voucherData['account_head_name']) ? $voucherData['account_head_name'] : 'N/A';
        $voucherData['sub_account_name'] = isset($voucherData['sub_account_name']) ? $voucherData['sub_account_name'] : 'N/A';
        $voucherData['account_name'] = isset($voucherData['account_name']) ? $voucherData['account_name'] : 'N/A';
        $debit_amount = isset($voucherData['debit_amount']) ? number_format($voucherData['debit_amount'], 2) : '0.00';
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Receive Voucher Receipt</title>
            <style>
                @media print {
                    body {
                        margin: 0;
                        padding: 0;
                        font-family: Arial, sans-serif;
                    }
                    .no-print {
                        display: none;
                    }
                }
                .receipt-container {
                    max-width: 800px;
                    margin: 20px auto;
                    padding: 20px;
                    border: 2px solid #333;
                    font-family: Arial, sans-serif;
                    position: relative;
                    overflow: hidden;
                }
                .receipt-header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #333;
                    padding-bottom: 20px;
                }
                .company-name {
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .company-details {
                    font-size: 14px;
                    color: #555;
                }
                .receipt-title {
                    font-size: 20px;
                    font-weight: bold;
                    text-align: center;
                    margin: 20px 0;
                    text-transform: uppercase;
                    color: #333;
                }
                .voucher-details {
                    margin-bottom: 30px;
                    border-bottom: 1px solid #ddd;
                    padding-bottom: 20px;
                }
                .detail-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 10px;
                    font-size: 14px;
                }
                .detail-label {
                    font-weight: bold;
                    color: #555;
                    width: 150px;
                }
                .detail-value {
                    flex: 1;
                    text-align: left;
                    padding-left: 20px;
                }
                .amount-section {
                    margin: 20px 0;
                    padding: 15px;
                    background-color: #f8f9fa;
                    border-radius: 5px;
                }
                .amount-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 10px;
                    font-size: 16px;
                }
                .amount-label {
                    font-weight: bold;
                    color: #333;
                }
                .signature-section {
                    margin-top: 50px;
                    display: flex;
                    justify-content: space-between;
                }
                .signature-box {
                    text-align: center;
                    width: 200px;
                }
                .signature-line {
                    border-top: 1px solid #333;
                    margin-top: 50px;
                    margin-bottom: 10px;
                }
                .action-buttons {
                    text-align: center;
                    margin: 20px 0;
                }
                .action-buttons button {
                    margin: 0 5px;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 16px;
                    transition: all 0.3s ease;
                }
                .print-button {
                    background-color: #3498db;
                    color: white;
                }
                .print-button:hover {
                    background-color: #2980b9;
                }
                .pdf-button {
                    background-color: #e74c3c;
                    color: white;
                }
                .pdf-button:hover {
                    background-color: #c0392b;
                }
                .xml-button {
                    background-color: #2ecc71;
                    color: white;
                }
                .xml-button:hover {
                    background-color: #27ae60;
                }
                .watermark {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) rotate(-45deg);
                    font-size: 100px;
                    opacity: 0.1;
                    color: #000;
                    pointer-events: none;
                    z-index: -1;
                }
            </style>
        </head>
        <body>
            <div class="receipt-container">
                <div class="watermark">RECEIVED</div>
                <div class="receipt-header">
                    <div class="company-name">Your Company Name</div>
                    <div class="company-details">
                        123 Business Street, City, Country<br>
                        Phone: +1234567890 | Email: info@company.com<br>
                        Tax ID: 12345678
                    </div>
                </div>

                <div class="receipt-title">Receive Voucher Receipt</div>

                <div class="voucher-details">
                    <div class="detail-row">
                        <span class="detail-label">Voucher No:</span>
                        <span class="detail-value"><?= htmlspecialchars($voucherData['id']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value"><?= htmlspecialchars($voucherData['date']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Account Head:</span>
                        <span class="detail-value"><?= htmlspecialchars($voucherData['account_head_name']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Sub Account:</span>
                        <span class="detail-value"><?= htmlspecialchars($voucherData['sub_account_name']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Account Name:</span>
                        <span class="detail-value"><?= htmlspecialchars($voucherData['account_name']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Mode:</span>
                        <span class="detail-value"><?= htmlspecialchars($payment_mode) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Description:</span>
                        <span class="detail-value"><?= htmlspecialchars($voucherData['description']) ?></span>
                    </div>
                </div>

                <div class="amount-section">
                    <div class="amount-row">
                        <span class="amount-label">Amount Received:</span>
                        <span class="amount-value">Rs. <?= $debit_amount ?></span>
                    </div>
                </div>

                <div class="signature-section">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div>Prepared By</div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div>Approved By</div>
                    </div>
                </div>

                <div class="action-buttons no-print">
                    <button class="print-button" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="pdf-button" onclick="window.location.href='download_receipt.php?id=<?= $voucherData['id'] ?>&format=pdf'">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </button>
                    <button class="xml-button" onclick="window.location.href='download_receipt.php?id=<?= $voucherData['id'] ?>&format=xml'">
                        <i class="fas fa-file-code"></i> Download XML
                    </button>
                </div>
            </div>

            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        </body>
        </html>
        <?php
        return ob_get_clean();
    } catch (Exception $e) {
        error_log("Error in generateReceiveReceipt: " . $e->getMessage());
        error_log("Data received: " . print_r($voucherData, true));
        throw $e;
    }
}
?>
