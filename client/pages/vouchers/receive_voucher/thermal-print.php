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
    header('Location: receive-list.php');
    exit();
}

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
    $amount    = round($amount, 2);
    $intPart   = intval($amount);
    $decPart   = round(($amount - $intPart) * 100);

    $words = numberToWords($intPart) . ' ' . ($main_unit ?: 'Only');
    if ($decPart > 0) {
        $words .= ' and ' . numberToWords($decPart) . ' ' . ($sub_unit ?: 'Cents');
    }
    return $words . ' Only';
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            rv.voucher_number,
            rv.voucher_date,
            rv.amount,
            rv.previous_balance,
            rv.bill_no,
            rv.description,
            rv.cheque_date,
            rv.cheque_no,
            rv.attachment,
            COALESCE(cu.customer_name, su.supplier_name) as customer_name,
            COALESCE(cu.customer_code, su.supplier_code) as customer_code,
            COALESCE(cu.address, su.address) as customer_address,
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
            co.logo_url,
            p.project_code,
            p.project_name
        FROM receive_voucher rv
        LEFT JOIN customers cu ON rv.customer_id = cu.id
        LEFT JOIN suppliers su ON rv.supplier_id = su.id
        LEFT JOIN accounts a ON rv.payment_method_id = a.id
        LEFT JOIN ledgerone_public.currencies c ON rv.currency_id = c.id
        LEFT JOIN bank_accounts ba ON rv.bank_account_id = ba.id
        LEFT JOIN companies co ON rv.company_id = co.id
        LEFT JOIN projects p ON rv.project_id = p.id
        WHERE rv.id = ? AND rv.tenant_id = ?
    ");
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        header('Location: receive-list.php');
        exit();
    }

    // Fallback to first active company if no company on the voucher
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

    $company = [
        'company_name' => $voucher['company_name'],
        'address' => $voucher['address'],
        'phone' => $voucher['phone'],
        'logo_url' => $voucher['logo_url']
    ];
} catch (Exception $e) {
    header('Location: receive-list.php');
    exit();
}

// Printed-by user's name
$printed_by = '';
try {
    $userStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $printed_by = $userStmt->fetchColumn() ?: '';
} catch (Exception $e) {
    $printed_by = '';
}

$outstandingBalance = $voucher['previous_balance'] - $voucher['amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thermal Print - Receive Voucher <?php echo htmlspecialchars($voucher['voucher_number']); ?></title>
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

        .voucher-header {
            font-size: 14px;
            font-weight: bold;
            margin: 8px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .info-row .label {
            white-space: nowrap;
        }

        .info-row .value {
            text-align: right;
            word-break: break-word;
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

        .amount-words {
            margin-top: 6px;
            font-size: 11px;
            font-weight: bold;
            font-style: italic;
        }

        .footer {
            font-size: 11px;
            margin-top: 10px;
            font-weight: bold;
        }

        .signature-line {
            margin-top: 25px;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 3px;
            width: 70%;
            margin-left: auto;
            margin-right: auto;
            font-size: 10px;
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
            <?php if (!empty($company['logo_url'])): ?>
            <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($company['logo_url']); ?>" alt="Logo" class="company-logo">
            <?php endif; ?>
            <div class="company-name"><?php echo htmlspecialchars($company['company_name'] ?: 'LedgerOne ERP'); ?></div>
            <?php if (!empty($company['address'])): ?>
            <div class="company-info"><?php echo htmlspecialchars($company['address']); ?></div>
            <?php endif; ?>
            <?php if (!empty($company['phone'])): ?>
            <div class="company-info">Tel: <?php echo htmlspecialchars($company['phone']); ?></div>
            <?php endif; ?>
        </div>

        <div class="divider"></div>

        <div class="text-center voucher-header">RECEIVE VOUCHER</div>

        <div class="info-row">
            <span class="label">Voucher #:</span>
            <span class="value"><?php echo htmlspecialchars($voucher['voucher_number']); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Date:</span>
            <span class="value"><?php echo date('d-M-Y', strtotime($voucher['voucher_date'])); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Received From:</span>
            <span class="value"><?php echo htmlspecialchars(($voucher['customer_code'] ?: 'N/A') . ' - ' . ($voucher['customer_name'] ?: 'N/A')); ?></span>
        </div>
        <?php if (!empty($voucher['customer_address'])): ?>
        <div class="info-row">
            <span class="label">Address:</span>
            <span class="value"><?php echo htmlspecialchars($voucher['customer_address']); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($voucher['bill_no'])): ?>
        <div class="info-row">
            <span class="label">Bill No:</span>
            <span class="value"><?php echo htmlspecialchars($voucher['bill_no']); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($voucher['project_code'])): ?>
        <div class="info-row">
            <span class="label">Project:</span>
            <span class="value"><?php echo htmlspecialchars($voucher['project_code'] . ' - ' . $voucher['project_name']); ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row">
            <span class="label">Payment Method:</span>
            <span class="value"><?php echo htmlspecialchars($voucher['payment_method']); ?></span>
        </div>
        <?php if (!empty($voucher['bank_name'])): ?>
        <div class="info-row">
            <span class="label">Bank Account:</span>
            <span class="value"><?php echo htmlspecialchars($voucher['bank_name'] . ' - ' . $voucher['account_number']); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($voucher['cheque_date']) && $voucher['cheque_date'] !== '0000-00-00'): ?>
        <div class="info-row">
            <span class="label">Cheque Date:</span>
            <span class="value"><?php echo date('d-M-Y', strtotime($voucher['cheque_date'])); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($voucher['cheque_no'])): ?>
        <div class="info-row">
            <span class="label">Cheque No:</span>
            <span class="value"><?php echo htmlspecialchars($voucher['cheque_no']); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($voucher['description'])): ?>
        <div class="info-row">
            <span class="label">Description:</span>
            <span class="value"><?php echo htmlspecialchars($voucher['description']); ?></span>
        </div>
        <?php endif; ?>

        <div class="divider"></div>

        <div class="total-row">
            <span>Prv. Balance:</span>
            <span><?php echo htmlspecialchars($voucher['currency_symbol']) . number_format($voucher['previous_balance'], 0); ?></span>
        </div>
        <div class="total-row grand-total">
            <span>Amount Received:</span>
            <span><?php echo htmlspecialchars($voucher['currency_symbol']) . number_format($voucher['amount'], 0); ?></span>
        </div>
        <div class="total-row">
            <span>Outstanding:</span>
            <span><?php echo htmlspecialchars($voucher['currency_symbol']) . number_format($outstandingBalance, 0); ?></span>
        </div>

        <div class="amount-words">
            <?php echo htmlspecialchars(amountInWords($voucher['amount'], $voucher['currency_main_unit'], $voucher['currency_sub_unit'])); ?>
        </div>

        <div class="divider"></div>

        <div class="footer text-center">
            <div>Thank you!</div>
            <div style="margin-top: 8px; font-size: 10px;">Printed By: <?php echo htmlspecialchars($printed_by ?: '-'); ?></div>
            <div style="font-size: 10px;">Printed: <?php echo date('d-M-Y h:i A'); ?></div>

            <div class="signature-line">
                Received By
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
</body>
</html>