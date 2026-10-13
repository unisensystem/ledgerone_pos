<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';

// Get currency symbol
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$tenant_id]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];

// Get user full name
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$user_full_name = $user['full_name'] ?? 'User';

// Get filters from URL
$startDate = $_GET['startDate'] ?? null;
$endDate = $_GET['endDate'] ?? null;
$accountNumber = $_GET['accountNumber'] ?? '';
$transactionType = $_GET['transactionType'] ?? '';
$minAmount = $_GET['minAmount'] ?? null;
$maxAmount = $_GET['maxAmount'] ?? null;
$searchText = $_GET['searchText'] ?? '';
$company_id = $_GET['company_id'] ?? null;

$company_filter = ($company_id ? " AND (si.company_id = ? OR pi.company_id = ? OR rv.company_id = ? OR pv.company_id = ? OR ev.company_id = ? OR jv.company_id = ?)" : "");

// Fetch data
$query = "SELECT 
    al.id,
    al.date,
    a.sub_account_id as account_number,
    a.name as account_name,
    al.description,
    CONCAT(al.reference_table, '-', al.reference_id) as reference,
    al.debit,
    al.credit
FROM accounting_ledger al
JOIN accounts a ON al.account_id = a.id
LEFT JOIN sale_invoice si ON al.reference_table = 'sale_invoice' AND al.reference_id = si.id
LEFT JOIN purchase_invoice pi ON al.reference_table = 'purchase_invoice' AND al.reference_id = pi.id
LEFT JOIN receive_voucher rv ON al.reference_table = 'receive_voucher' AND al.reference_id = rv.id
LEFT JOIN payment_voucher pv ON al.reference_table = 'payment_voucher' AND al.reference_id = pv.id
LEFT JOIN expense_voucher ev ON al.reference_table = 'expense_voucher' AND al.reference_id = ev.id
LEFT JOIN journal_voucher jv ON al.reference_table = 'journal_voucher' AND al.reference_id = jv.id
WHERE al.tenant_id = ?{$company_filter}";

$params = [$tenant_id];
if ($company_id) {
    $params[] = $company_id;
    $params[] = $company_id;
    $params[] = $company_id;
    $params[] = $company_id;
    $params[] = $company_id;
    $params[] = $company_id;
}

if ($startDate) {
    $query .= " AND al.date >= ?";
    $params[] = $startDate;
}

if ($endDate) {
    $query .= " AND al.date <= ?";
    $params[] = $endDate;
}

if ($accountNumber) {
    $query .= " AND a.sub_account_id = ?";
    $params[] = $accountNumber;
}

if ($transactionType === 'debit') {
    $query .= " AND al.debit > 0";
} elseif ($transactionType === 'credit') {
    $query .= " AND al.credit > 0";
}

if ($minAmount) {
    $query .= " AND (al.debit >= ? OR al.credit >= ?)";
    $params[] = $minAmount;
    $params[] = $minAmount;
}

if ($maxAmount) {
    $query .= " AND (al.debit <= ? OR al.credit <= ?)";
    $params[] = $maxAmount;
    $params[] = $maxAmount;
}

if ($searchText) {
    $query .= " AND (al.description LIKE ? OR a.name LIKE ?)";
    $params[] = "%$searchText%";
    $params[] = "%$searchText%";
}

$query .= " ORDER BY al.date, al.id";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDebits = 0;
$totalCredits = 0;
$accountBalances = [];

foreach ($entries as &$entry) {
    $totalDebits += $entry['debit'];
    $totalCredits += $entry['credit'];
    
    $accountKey = $entry['account_number'];
    if (!isset($accountBalances[$accountKey])) {
        $accountBalances[$accountKey] = 0;
    }
    $accountBalances[$accountKey] += ($entry['debit'] - $entry['credit']);
    $entry['balance'] = $accountBalances[$accountKey];
}

// Get company info and timezone
$stmt = $pdo->prepare("SELECT company_name, legal_name, address, phone, email, logo_url, timezone FROM companies WHERE tenant_id = ? LIMIT 1");
$stmt->execute([$tenant_id]);
$company = $stmt->fetch();
$company_name = $company['company_name'] ?? 'LedgerOne ERP';
$legal_name = $company['legal_name'] ?? '';
$address = $company['address'] ?? '';
$phone = $company['phone'] ?? '';
$email = $company['email'] ?? '';
$logo_url = $company['logo_url'] ?? '';
$timezone = $company['timezone'] ?? 'UTC';
date_default_timezone_set($timezone);

function formatCurrency($amount, $symbol) {
    return $symbol . number_format($amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Ledger Report - Print</title>
    <style>
        @media print {
            @page {
                margin: 0.5in;
            }
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
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            background: #fff;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .company-name {
            font-size: 20pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .report-title {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .report-period {
            font-size: 10pt;
            color: #333;
        }
        
        .summary {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            padding: 15px;
            background: #f5f5f5;
            border: 1px solid #ddd;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-label {
            font-size: 9pt;
            color: #666;
            margin-bottom: 3px;
        }
        
        .summary-value {
            font-size: 14pt;
            font-weight: bold;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th {
            background: #333;
            color: #fff;
            font-weight: bold;
            font-size: 9pt;
            text-align: left;
            padding: 8px 6px;
            border: 1px solid #000;
        }
        
        td {
            padding: 6px;
            border: 1px solid #ddd;
            font-size: 9pt;
        }
        
        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        tfoot td {
            background: #e8e8e8;
            font-weight: bold;
            border: 1px solid #000;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            text-align: center;
            color: #666;
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #1f7bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-btn:hover {
            background: #1a6cdc;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print Report</button>
    
    <div class="container">
        <div class="header">
            <?php if ($logo_url): ?>
            <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($logo_url); ?>" alt="Company Logo" style="max-width: 150px; max-height: 80px; margin-bottom: 10px;">
            <?php endif; ?>
            <div class="company-name"><?php echo htmlspecialchars($company_name); ?></div>
            <?php if ($legal_name): ?>
            <div style="font-size: 10pt; color: #666;"><?php echo htmlspecialchars($legal_name); ?></div>
            <?php endif; ?>
            <?php if ($address || $phone || $email): ?>
            <div style="font-size: 9pt; color: #666; margin-top: 5px;">
                <?php echo htmlspecialchars($address); ?>
                <?php if ($phone) echo ' | ' . htmlspecialchars($phone); ?>
                <?php if ($email) echo ' | ' . htmlspecialchars($email); ?>
            </div>
            <?php endif; ?>
            <div class="report-title" style="margin-top: 10px;">General Ledger Report</div>
            <div class="report-period">
                Period: <?php echo $startDate ? date('M d, Y', strtotime($startDate)) : 'All'; ?> - 
                <?php echo $endDate ? date('M d, Y', strtotime($endDate)) : 'All'; ?>
            </div>
        </div>
        
        <div class="summary">
            <div class="summary-item">
                <div class="summary-label">Total Debits</div>
                <div class="summary-value"><?php echo formatCurrency($totalDebits, $currency_symbol); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Credits</div>
                <div class="summary-value"><?php echo formatCurrency($totalCredits, $currency_symbol); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Transactions</div>
                <div class="summary-value"><?php echo count($entries); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Status</div>
                <div class="summary-value"><?php echo abs($totalDebits - $totalCredits) < 0.01 ? 'Balanced ✓' : 'Unbalanced'; ?></div>
            </div>
        </div>
        
        <?php if ($accountNumber): ?>
        <div style="margin: 20px 0; padding: 10px; background: #f5f5f5; border: 1px solid #ddd;">
            <strong>Account #:</strong> <?php echo htmlspecialchars($accountNumber); ?> | 
            <strong>Account Name:</strong> <?php echo htmlspecialchars($entries[0]['account_name'] ?? 'N/A'); ?>
        </div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <?php if (!$accountNumber): ?>
                    <th>Account #</th>
                    <th>Account Name</th>
                    <?php endif; ?>
                    <th>Description</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                    <th class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><?php echo date('Y-m-d', strtotime($entry['date'])); ?></td>
                    <?php if (!$accountNumber): ?>
                    <td><?php echo $entry['account_number'] ?: 'N/A'; ?></td>
                    <td><?php echo htmlspecialchars($entry['account_name']); ?></td>
                    <?php endif; ?>
                    <td><?php echo htmlspecialchars($entry['description'] ?: ''); ?></td>
                    <td class="text-right"><?php echo $entry['debit'] > 0 ? formatCurrency($entry['debit'], $currency_symbol) : ''; ?></td>
                    <td class="text-right"><?php echo $entry['credit'] > 0 ? formatCurrency($entry['credit'], $currency_symbol) : ''; ?></td>
                    <td class="text-right">
                        <?php echo ($entry['balance'] >= 0 ? 'Dr ' : 'Cr ') . formatCurrency(abs($entry['balance']), $currency_symbol); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="<?php echo $accountNumber ? '2' : '4'; ?>" class="text-right">Period Totals:</td>
                    <td class="text-right"><?php echo formatCurrency($totalDebits, $currency_symbol); ?></td>
                    <td class="text-right"><?php echo formatCurrency($totalCredits, $currency_symbol); ?></td>
                    <td class="text-center">
                        <?php echo abs($totalDebits - $totalCredits) < 0.01 ? 'BALANCED ✓' : 'UNBALANCED'; ?>
                    </td>
                </tr>
            </tfoot>
        </table>
        
        <div style="display: flex; justify-content: space-around; margin-top: 50px; margin-bottom: 30px;">
            <div style="text-align: center; min-width: 200px;">
                <div style="border-top: 2px solid #000; padding-top: 5px; margin-top: 60px;">
                    <strong>Prepared By</strong><br>
                    <span style="font-size: 9pt;"><?php echo htmlspecialchars($user_full_name); ?></span>
                </div>
            </div>
            <div style="text-align: center; min-width: 200px;">
                <div style="border-top: 2px solid #000; padding-top: 5px; margin-top: 60px;">
                    <strong>Reviewed By</strong>
                </div>
            </div>
            <div style="text-align: center; min-width: 200px;">
                <div style="border-top: 2px solid #000; padding-top: 5px; margin-top: 60px;">
                    <strong>Approved By</strong>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>This General Ledger report follows GAAP (Generally Accepted Accounting Principles) standards.</p>
            <p>Report generated on <?php echo date('F d, Y \a\t g:i A'); ?> | Generated by: <?php echo htmlspecialchars($user_full_name); ?> | LedgerOne ERP Accounting Module</p>
        </div>
    </div>
</body>
</html>
