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

$voucher_id = $_GET['id'] ?? null;

if (!$voucher_id) {
    die('Voucher ID is required');
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
$currency_symbol = $currency['symbol'] ?? '$';

// Get voucher details
$stmt = $pdo->prepare("
    SELECT 
        ev.id, 
        ev.voucher_no, 
        ev.date, 
        ev.description, 
        ev.total_amount, 
        ev.total_items,
        ev.vendor_id,
        ev.type,
        s.supplier_name as vendor_name,
        c.company_name,
        c.legal_name,
        c.address,
        c.city,
        c.state,
        c.country,
        c.zipcode,
        c.phone,
        c.email
    FROM expense_voucher ev
    LEFT JOIN companies c ON ev.company_id = c.id
    LEFT JOIN suppliers s ON ev.vendor_id = s.id
    WHERE ev.id = ? AND ev.tenant_id = ?
");
$stmt->execute([$voucher_id, $tenant_id]);
$voucher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voucher) {
    die('Voucher not found');
}

// If no company from voucher, get first active company as fallback
if (!$voucher['company_name']) {
    $stmt = $pdo->prepare("
        SELECT company_name, legal_name, address, city, state, country, zipcode, phone, email
        FROM companies
        WHERE tenant_id = ? AND is_active = 1
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmt->execute([$tenant_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($company) {
        $voucher = array_merge($voucher, $company);
    }
}

// Get voucher lines
$stmt = $pdo->prepare("
    SELECT 
        evl.id,
        a.name as account_name,
        cc.name as cost_center_name,
        evl.amount,
        pm.name as payment_method_name,
        ba.bank_name,
        ba.account_number,
        evl.cheque_no,
        evl.cheque_date
    FROM expense_voucher_line evl
    INNER JOIN accounts a ON evl.account_id = a.id
    LEFT JOIN cost_centers cc ON evl.cost_center_id = cc.id
    INNER JOIN accounts pm ON evl.payment_method_id = pm.id
    LEFT JOIN bank_accounts ba ON evl.bank_account_id = ba.id
    WHERE evl.voucher_id = ? AND evl.tenant_id = ?
");
$stmt->execute([$voucher_id, $tenant_id]);
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Expense Voucher - <?php echo htmlspecialchars($voucher['voucher_no']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            color: #333;
        }
        
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 14px;
            color: #666;
        }
        
        .voucher-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .info-item {
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
        }
        
        .info-item label {
            font-weight: bold;
            font-size: 12px;
            color: #666;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-item .value {
            font-size: 14px;
            color: #333;
        }
        
        .description {
            grid-column: 1 / -1;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table th {
            background: #333;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
        }
        
        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }
        
        table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .total-row {
            text-align: right;
            padding: 15px 0;
            border-top: 2px solid #333;
            font-size: 16px;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .signature {
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
            font-size: 12px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #1f7bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-button:hover {
            background: #1a6cdc;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">Print</button>
    
    <div class="print-container">
        <div class="header">
            <h1>EXPENSE VOUCHER</h1>
            <?php if ($voucher['company_name']): ?>
                <p><strong><?php echo htmlspecialchars($voucher['company_name']); ?></strong></p>
                <?php if ($voucher['address']): ?>
                    <p style="font-size: 12px;">
                        <?php echo htmlspecialchars($voucher['address']); ?>
                        <?php if ($voucher['city']) echo ', ' . htmlspecialchars($voucher['city']); ?>
                        <?php if ($voucher['state']) echo ', ' . htmlspecialchars($voucher['state']); ?>
                        <?php if ($voucher['zipcode']) echo ' ' . htmlspecialchars($voucher['zipcode']); ?>
                    </p>
                <?php endif; ?>
                <?php if ($voucher['phone'] || $voucher['email']): ?>
                    <p style="font-size: 12px;">
                        <?php if ($voucher['phone']) echo 'Phone: ' . htmlspecialchars($voucher['phone']); ?>
                        <?php if ($voucher['phone'] && $voucher['email']) echo ' | '; ?>
                        <?php if ($voucher['email']) echo 'Email: ' . htmlspecialchars($voucher['email']); ?>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p>LedgerOne ERP</p>
            <?php endif; ?>
        </div>
        
        <div class="voucher-info">
            <div class="info-item">
                <label>Voucher #</label>
                <div class="value"><?php echo htmlspecialchars($voucher['voucher_no']); ?></div>
            </div>
            <div class="info-item">
                <label>Date</label>
                <div class="value"><?php echo date('F d, Y', strtotime($voucher['date'])); ?></div>
            </div>
            <?php if (!empty($voucher['vendor_name'])): ?>
            <div class="info-item">
                <label>Vendor</label>
                <div class="value"><?php echo htmlspecialchars($voucher['vendor_name']); ?></div>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <label>Type</label>
                <div class="value" style="font-weight:bold; color: <?php echo ($voucher['type'] === 'DR') ? '#e34f4f' : '#2fbf71'; ?>">
                    <?php echo ($voucher['type'] === 'DR') ? '- DR' : '+ CR'; ?>
                </div>
            </div>
            <div class="info-item description">
                <label>Description</label>
                <div class="value"><?php echo htmlspecialchars($voucher['description']); ?></div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Expense Account</th>
                    <th>Cost Center</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Bank Account</th>
                    <th>Cheque No</th>
                    <th>Cheque Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                <tr>
                    <td><?php echo htmlspecialchars($line['account_name']); ?></td>
                    <td><?php echo htmlspecialchars($line['cost_center_name'] ?? '-'); ?></td>
                    <td><?php echo $currency_symbol . number_format($line['amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($line['payment_method_name']); ?></td>
                    <td><?php echo $line['bank_name'] ? htmlspecialchars($line['bank_name'] . ' - ' . $line['account_number']) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($line['cheque_no'] ?? '-'); ?></td>
                    <td><?php echo $line['cheque_date'] ? date('M d, Y', strtotime($line['cheque_date'])) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total-row">
            Total Amount: <?php echo $currency_symbol . number_format($voucher['total_amount'], 2); ?>
        </div>
        
        <div class="footer">
            <div class="signature">
                <div class="signature-line">Prepared By</div>
            </div>
            <div class="signature">
                <div class="signature-line">Approved By</div>
            </div>
            <div class="signature">
                <div class="signature-line">Received By</div>
            </div>
        </div>
    </div>
</body>
</html>
