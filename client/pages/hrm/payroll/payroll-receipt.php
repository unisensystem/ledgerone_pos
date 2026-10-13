<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../../includes/connection.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

$payroll_id = $_GET['id'] ?? null;
$employee_id = $_GET['employee_id'] ?? null;

if (!$payroll_id) {
    die('Invalid payroll ID');
}

try {

// Get currency symbol
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$tenant_id]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'] ?? '$';

// Get payroll entry
$stmt = $pdo->prepare("SELECT * FROM payroll_entries WHERE id = ? AND tenant_id = ?");
$stmt->execute([$payroll_id, $tenant_id]);
$payroll = $stmt->fetch();

if (!$payroll) {
    die('Payroll record not found');
}

// Get payroll items
if ($employee_id) {
    $stmt = $pdo->prepare("
        SELECT pei.*, e.full_name as employee_name, e.phone_number, e.email,
               a.name as payment_method, ba.bank_name, ba.account_number
        FROM payroll_entries_items pei
        LEFT JOIN employees e ON pei.employee_id COLLATE utf8mb4_unicode_ci = e.employee_id COLLATE utf8mb4_unicode_ci AND e.tenant_id = ?
        LEFT JOIN accounts a ON pei.payment_method_id = a.id
        LEFT JOIN bank_accounts ba ON pei.bank_account_id = ba.id
        WHERE pei.payroll_id = ? AND pei.tenant_id = ? AND pei.employee_id = ?
    ");
    $stmt->execute([$tenant_id, $payroll_id, $tenant_id, $employee_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT pei.*, e.full_name as employee_name, e.phone_number, e.email,
               a.name as payment_method, ba.bank_name, ba.account_number
        FROM payroll_entries_items pei
        LEFT JOIN employees e ON pei.employee_id COLLATE utf8mb4_unicode_ci = e.employee_id COLLATE utf8mb4_unicode_ci AND e.tenant_id = ?
        LEFT JOIN accounts a ON pei.payment_method_id = a.id
        LEFT JOIN bank_accounts ba ON pei.bank_account_id = ba.id
        WHERE pei.payroll_id = ? AND pei.tenant_id = ?
    ");
    $stmt->execute([$tenant_id, $payroll_id, $tenant_id]);
}
$items = $stmt->fetchAll();

if (empty($items)) {
    die('No payroll items found');
}

// Get company details
$stmt = $pdo->prepare("SELECT company_name, address, phone, email, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$tenant_id]);
$company = $stmt->fetch();

// Get user details
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Receipt - <?php echo htmlspecialchars($payroll['payroll_code']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 3px solid #1f7bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-logo {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 15px;
        }
        
        .company-name {
            font-size: 28px;
            font-weight: bold;
            color: #0e1a2b;
            margin-bottom: 5px;
        }
        
        .company-details {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.6;
        }
        
        .receipt-title {
            font-size: 24px;
            font-weight: bold;
            color: #1f7bff;
            margin: 20px 0;
            text-align: center;
        }
        
        .receipt-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-section {
            padding: 15px;
            background-color: #f7f9fc;
            border-radius: 8px;
        }
        
        .info-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 14px;
            color: #0e1a2b;
            font-weight: 500;
        }
        
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        
        .details-table th {
            background-color: #f7f9fc;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            color: #0e1a2b;
            border-bottom: 2px solid #e1e6ee;
        }
        
        .details-table td {
            padding: 12px;
            border-bottom: 1px solid #e1e6ee;
            font-size: 14px;
            color: #2f3b4c;
        }
        
        .total-section {
            text-align: right;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e1e6ee;
        }
        
        .total-label {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        
        .total-amount {
            font-size: 28px;
            font-weight: bold;
            color: #1f7bff;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e1e6ee;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background-color: #1f7bff;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        
        .print-button:hover {
            background-color: #1a6cdc;
        }
        
        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            
            .receipt-container {
                box-shadow: none;
                padding: 20px;
            }
            
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">
        <i class="fas fa-print"></i> Print Receipt
    </button>
    
    <div class="receipt-container">
        <div class="receipt-header">
            <?php if (!empty($company['logo_url'])): ?>
                <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($company['logo_url']); ?>" alt="Company Logo" class="company-logo">
            <?php endif; ?>
            <div class="company-name"><?php echo htmlspecialchars($company['company_name'] ?? 'Company Name'); ?></div>
            <div class="company-details">
                <?php echo htmlspecialchars($company['address'] ?? ''); ?><br>
                Phone: <?php echo htmlspecialchars($company['phone'] ?? ''); ?> | Email: <?php echo htmlspecialchars($company['email'] ?? ''); ?>
            </div>
        </div>
        
        <div class="receipt-title">PAYROLL RECEIPT</div>
        
        <div class="receipt-info">
            <div class="info-section">
                <div class="info-label">Receipt No.</div>
                <div class="info-value"><?php echo htmlspecialchars($payroll['payroll_code']); ?></div>
            </div>
            <div class="info-section">
                <div class="info-label">Date</div>
                <div class="info-value"><?php echo date('F d, Y', strtotime($payroll['payroll_date'])); ?></div>
            </div>
            <div class="info-section">
                <div class="info-label">Employee ID</div>
                <div class="info-value"><?php echo htmlspecialchars($items[0]['employee_id']); ?></div>
            </div>
            <div class="info-section">
                <div class="info-label">Employee Name</div>
                <div class="info-value"><?php echo htmlspecialchars($items[0]['employee_name']); ?></div>
            </div>
        </div>
        
        <table class="details-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Payment Method</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['description'] ?: $item['type']); ?></td>
                    <td><?php echo htmlspecialchars($item['type']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($item['payment_method']); ?>
                        <?php if ($item['bank_name']): ?>
                            <br><small><?php echo htmlspecialchars($item['bank_name'] . ' - ' . $item['account_number']); ?></small>
                        <?php endif; ?>
                        <?php if ($item['cheque_date']): ?>
                            <br><small>Cheque Date: <?php echo date('M d, Y', strtotime($item['cheque_date'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right; font-weight: 600;">
                        <?php
                            $display_amount = in_array(strtolower($item['type']), ['deduction', 'advance return', 'loan repayment']) ? -$item['amount'] : $item['amount'];
                            echo $currency_symbol . number_format($display_amount, 2);
                        ?>
                    </td>
                </tr>
                <?php
                    // If this line was built from real attendance/leave (via "Calculate Suggested"
                    // on the payroll form), show exactly how the final amount was reached — full
                    // salary earned, what was deducted and why, so it never reads as "we only gave
                    // 3500" when the real story is "salary is 5000, 1500 was deducted for absence,
                    // 3500 was actually paid out". Manual items (Advance, Bonus, etc.) have no
                    // breakdown data and simply don't get this box.
                    $hasBreakdown = $item['gross_amount'] !== null && $item['deduction_amount'] !== null;
                    if ($hasBreakdown):
                ?>
                <tr>
                    <td colspan="4" style="padding: 0;">
                        <div style="margin: 4px 0 12px; padding: 12px 16px; background: #f7f9fc; border: 1px dashed #c9cfda; border-radius: 6px; font-size: 12px; color: #2f3b4c;">
                            <div style="font-weight: 600; margin-bottom: 6px; color: #0e1a2b;">How this amount was calculated</div>
                            <div style="display: flex; justify-content: space-between; padding: 2px 0;">
                                <span>Gross Salary</span>
                                <span><?php echo $currency_symbol . number_format($item['gross_amount'], 2); ?></span>
                            </div>
                            <?php if ((float) $item['deduction_amount'] > 0): ?>
                            <div style="display: flex; justify-content: space-between; padding: 2px 0; color: #991b1b;">
                                <span>Deduction
                                    <?php if ($item['daily_rate'] !== null): ?>
                                        <small style="color: #6b7280;">(@ <?php echo $currency_symbol . number_format($item['daily_rate'], 2); ?>/day)</small>
                                    <?php endif; ?>
                                </span>
                                <span>- <?php echo $currency_symbol . number_format($item['deduction_amount'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; padding: 4px 0 0; margin-top: 4px; border-top: 1px solid #e1e6ee; font-weight: 700; color: #166534;">
                                <span>Net Salary Paid</span>
                                <span><?php echo $currency_symbol . number_format($item['amount'], 2); ?></span>
                            </div>
                            <?php if ($item['present_days'] !== null): ?>
                            <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e1e6ee; color: #6b7280; font-size: 11px;">
                                Present: <?php echo number_format($item['present_days'], 1); ?> day(s)
                                &nbsp;·&nbsp; Paid Leave: <?php echo number_format($item['paid_leave_days'], 1); ?> day(s)
                                &nbsp;·&nbsp; Unpaid Leave: <?php echo number_format($item['unpaid_leave_days'], 1); ?> day(s)
                                &nbsp;·&nbsp; Absent: <?php echo number_format($item['absent_days'], 1); ?> day(s)
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-section">
            <div class="total-label">Total Amount</div>
            <div class="total-amount"><?php 
                $total = 0;
                foreach ($items as $item) {
                    $total += (in_array(strtolower($item['type']), ['deduction', 'advance return', 'loan repayment']) ? -$item['amount'] : $item['amount']);
                }
                echo $currency_symbol . number_format($total, 2); 
            ?></div>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated receipt and does not require a signature.</p>
            <p>Generated on <?php echo date('F d, Y h:i A'); ?> by <?php echo htmlspecialchars($user['full_name'] ?? 'System'); ?></p>
        </div>
    </div>
</body>
</html>
