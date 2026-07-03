<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

$voucher_id = $_GET['id'] ?? null;
if (!$voucher_id) {
    header('Location: payment-list.php');
    exit();
}

// Fetch voucher data
require_once '../../../../includes/connection.php';

// Function to convert number to words
function numberToWords($number) {
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
             'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if ($number == 0) return 'Zero';
    if ($number < 20) return $ones[$number];
    if ($number < 100) return $tens[intval($number / 10)] . ($number % 10 ? ' ' . $ones[$number % 10] : '');
    if ($number < 1000) return $ones[intval($number / 100)] . ' Hundred' . ($number % 100 ? ' ' . numberToWords($number % 100) : '');
    if ($number < 1000000) return numberToWords(intval($number / 1000)) . ' Thousand' . ($number % 1000 ? ' ' . numberToWords($number % 1000) : '');
    if ($number < 1000000000) return numberToWords(intval($number / 1000000)) . ' Million' . ($number % 1000000 ? ' ' . numberToWords($number % 1000000) : '');
    return numberToWords(intval($number / 1000000000)) . ' Billion' . ($number % 1000000000 ? ' ' . numberToWords($number % 1000000000) : '');
}

function amountInWords($amount, $main_unit, $sub_unit) {
    $amount   = round($amount, 2);
    $intPart  = intval($amount);
    $decPart  = round(($amount - $intPart) * 100);

    $words = numberToWords($intPart) . ' ' . ($main_unit ?: 'Only');
    if ($decPart > 0) {
        $words .= ' and ' . numberToWords($decPart) . ' ' . ($sub_unit ?: 'Cents');
    }
    return $words . ' Only';
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            pv.voucher_number,
            pv.voucher_date,
            pv.amount,
            pv.bill_no,
            pv.description,
            pv.cheque_no,
            pv.cheque_date,
            pv.attachment,
            s.supplier_name,
            s.supplier_code,
            s.address as supplier_address,
            a.name as payment_method,
            c.name as currency_name,
            c.symbol as currency_symbol,
            c.main_unit_name as currency_main_unit,
            c.sub_unit_name as currency_sub_unit,
            ba.bank_name,
            ba.account_number,
            co.company_name,
            co.legal_name,
            co.email,
            co.phone,
            co.address,
            co.city,
            co.state,
            co.zipcode,
            co.logo_url
        FROM payment_voucher pv
        LEFT JOIN suppliers s ON pv.supplier_id = s.id
        LEFT JOIN accounts a ON pv.payment_method_id = a.id
        LEFT JOIN ledgerone_public.currencies c ON pv.currency_id = c.id
        LEFT JOIN bank_accounts ba ON pv.bank_account_id = ba.id
        LEFT JOIN companies co ON pv.company_id = co.id
        WHERE pv.id = ? AND pv.tenant_id = ?
    ");
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$voucher) {
        header('Location: payment-list.php');
        exit();
    }
    
    // Fallback to first active company if no company in voucher
    if (empty($voucher['company_name'])) {
        $stmt = $pdo->prepare("SELECT company_name, address, phone, email, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$tenant_id]);
        $fallback = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fallback) {
            $voucher['company_name'] = $fallback['company_name'];
            $voucher['address'] = $fallback['address'];
            $voucher['phone'] = $fallback['phone'];
            $voucher['email'] = $fallback['email'];
            $voucher['logo_url'] = $fallback['logo_url'];
        }
    }
    
    // Keep company data for backward compatibility
    $company = [
        'company_name' => $voucher['company_name'],
        'address' => $voucher['address'],
        'phone' => $voucher['phone'],
        'email' => $voucher['email'],
        'logo_url' => $voucher['logo_url']
    ];
} catch (Exception $e) {
    header('Location: payment-list.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Voucher - <?php echo htmlspecialchars($voucher['voucher_number']); ?></title>
    <style>
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
        
        .print-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .document-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .voucher-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .info-section {
            flex: 1;
        }
        
        .info-row {
            margin-bottom: 8px;
        }
        
        .label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .details-table th,
        .details-table td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        
        .details-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        .amount-section {
            margin-top: 20px;
            text-align: right;
        }
        
        .total-amount {
            font-size: 16px;
            font-weight: bold;
            border: 2px solid #333;
            padding: 10px;
            display: inline-block;
            margin-top: 10px;
        }
        
        .signatures {
            margin-top: 40px;
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
            padding-top: 5px;
        }
        
        @media print {
            body {
                margin: 0;
            }
            
            .print-container {
                margin: 0;
                border: none;
                box-shadow: none;
            }
            
            .no-print {
                display: none;
            }
        }
        
        .print-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .attachment-section {
            margin: 20px 0;
            page-break-inside: avoid;
        }
        
        .attachment-section h3 {
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .attachment-section img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            margin: 10px 0;
        }
        
        .attachment-section iframe {
            width: 100%;
            height: 600px;
            border: 1px solid #ddd;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="print-container">
        <button class="print-btn no-print" onclick="window.print()">Print Voucher</button>
        
        <div class="header">
            <?php if ($company['logo_url']): ?>
            <div style="margin-bottom: 10px;">
                <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($company['logo_url']); ?>" alt="Company Logo" style="max-height: 80px; max-width: 200px;">
            </div>
            <?php endif; ?>
            <div class="company-name"><?php echo htmlspecialchars($company['company_name'] ?? 'LedgerOne ERP'); ?></div>
            <?php if ($company['address']): ?>
            <div style="font-size: 12px; margin: 5px 0;"><?php echo htmlspecialchars($company['address']); ?></div>
            <?php endif; ?>
            <?php if ($company['phone'] || $company['email']): ?>
            <div style="font-size: 12px; margin: 5px 0;">
                <?php if ($company['phone']): ?>Phone: <?php echo htmlspecialchars($company['phone']); ?><?php endif; ?>
                <?php if ($company['phone'] && $company['email']): ?> | <?php endif; ?>
                <?php if ($company['email']): ?>Email: <?php echo htmlspecialchars($company['email']); ?><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="document-title">PAYMENT VOUCHER</div>
        </div>
        
        <div class="voucher-info">
            <div class="info-section">
                <div class="info-row">
                    <span class="label">Voucher No:</span>
                    <?php echo htmlspecialchars($voucher['voucher_number']); ?>
                </div>
                <div class="info-row">
                    <span class="label">Date:</span>
                    <?php echo date('d-M-Y', strtotime($voucher['voucher_date'])); ?>
                </div>
            </div>
            <div class="info-section">
                <div class="info-row">
                    <span class="label">Payment Method:</span>
                    <?php echo htmlspecialchars($voucher['payment_method']); ?>
                </div>
                <div class="info-row">
                    <span class="label">Currency:</span>
                    <?php echo htmlspecialchars($voucher['currency_name']); ?>
                </div>
            </div>
        </div>
        
        <table class="details-table">
            <tr>
                <th>Pay To</th>
                <td><?php echo htmlspecialchars(($voucher['supplier_code'] ?: 'N/A') . ' - ' . ($voucher['supplier_name'] ?: 'N/A')); ?></td>
            </tr>
            <?php if ($voucher['supplier_address']): ?>
            <tr>
                <th>Address</th>
                <td><?php echo htmlspecialchars($voucher['supplier_address']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['bill_no']): ?>
            <tr>
                <th>Bill No</th>
                <td><?php echo htmlspecialchars($voucher['bill_no']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['bank_name']): ?>
            <tr>
                <th>Bank Account</th>
                <td><?php echo htmlspecialchars($voucher['bank_name'] . ' - ' . $voucher['account_number']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['cheque_no']): ?>
            <tr>
                <th>Cheque No</th>
                <td><?php echo htmlspecialchars($voucher['cheque_no']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['cheque_date'] && $voucher['cheque_date'] !== '0000-00-00'): ?>
            <tr>
                <th>Cheque Date</th>
                <td><?php echo date('d-M-Y', strtotime($voucher['cheque_date'])); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['attachment']): ?>
            <tr>
                <th>Attachment</th>
                <td><?php echo htmlspecialchars($voucher['attachment']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['description']): ?>
            <tr>
                <th>Description</th>
                <td><?php echo htmlspecialchars($voucher['description'] ?: 'N/A'); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <?php if ($voucher['attachment']): ?>
        <div class="attachment-section">
            <h3>Attachment:</h3>
            <?php 
            $attachment_path = "../../../assets/uploads/payment_voucher/" . $voucher['attachment'];
            $file_extension = strtolower(pathinfo($voucher['attachment'], PATHINFO_EXTENSION));
            
            if ($file_extension === 'pdf'): ?>
                <iframe src="<?php echo $attachment_path; ?>" width="100%" height="600px" style="border: 1px solid #ddd; margin: 10px 0;"></iframe>
            <?php else: ?>
                <img src="<?php echo $attachment_path; ?>" alt="Attachment" style="max-width: 100%; height: auto; border: 1px solid #ddd; margin: 10px 0;">
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="amount-section">
            <div class="total-amount">
                Total Amount: <?php echo $voucher['currency_symbol'] . number_format($voucher['amount'], 2); ?>
            </div>
            <div style="margin-top: 10px; font-style: italic;">
                Amount in Words: <?php echo amountInWords($voucher['amount'], $voucher['currency_main_unit'], $voucher['currency_sub_unit']); ?>
            </div>
        </div>
        
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line">Prepared By</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Approved By</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Received By</div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 10px;">
            <i>This is a System Generated Voucher</i>
        </div>
    </div>
</body>
</html>