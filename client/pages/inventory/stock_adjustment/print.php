<?php
require_once '../../../../includes/connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

$adjustment_id = $_GET['id'] ?? null;

if (!$adjustment_id) {
    header('Location: adjustment-list.php');
    exit();
}

// Get adjustment details
$stmt = $pdo->prepare("
    SELECT 
        sa.id,
        sa.adjustment_code,
        sa.date,
        b.branch_name,
        sa.adjustment_type,
        sa.main_reason,
        sa.primary_reason,
        sa.secondary_reason,
        sa.remarks,
        sa.total_amount,
        sa.total_items,
        c.company_name
    FROM stock_adjustment sa
    JOIN branches b ON sa.branch_id = b.id
    LEFT JOIN companies c ON sa.company_id = c.id
    WHERE sa.tenant_id = ? AND sa.id = ?
");
$stmt->execute([$tenant_id, $adjustment_id]);
$adjustment = $stmt->fetch();

if (!$adjustment) {
    header('Location: adjustment-list.php');
    exit();
}

// Get adjustment items
$stmt = $pdo->prepare("
    SELECT 
        p.code as product_code,
        p.name as product_name,
        sai.qty,
        sai.rate,
        sai.stock_value
    FROM stock_adjustment_items sai
    JOIN products p ON sai.product_id = p.id
    WHERE sai.tenant_id = ? AND sai.adjustment_id = ?
");
$stmt->execute([$tenant_id, $adjustment_id]);
$items = $stmt->fetchAll();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Adjustment - <?php echo $adjustment['adjustment_code']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            color: #333;
        }
        
        .print-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .header h2 {
            font-size: 18px;
            color: #666;
            font-weight: normal;
        }
        
        .details-section {
            margin-bottom: 30px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
        }
        
        .detail-label {
            font-weight: bold;
            width: 150px;
            color: #555;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .type-increase {
            background-color: #d4edda;
            color: #155724;
        }
        
        .type-decrease {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .items-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th {
            background-color: #f5f5f5;
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
        }
        
        .items-table td {
            padding: 10px 12px;
            border: 1px solid #ddd;
        }
        
        .items-table tr:nth-child(even) {
            background-color: #fafafa;
        }
        
        .text-right {
            text-align: right;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #f0f0f0 !important;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        .no-print {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .btn-print {
            background-color: #1f7bff;
            color: white;
            padding: 10px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-print:hover {
            background-color: #1a6cdc;
        }
        
        @media print {
            body {
                padding: 20px;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="no-print">
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Document
            </button>
        </div>
        
        <div class="header">
            <h1>Stock Adjustment</h1>
            <h2><?php echo $adjustment['adjustment_code']; ?></h2>
        </div>
        
        <div class="details-section">
            <h3 class="section-title">Adjustment Details</h3>
            <div class="details-grid">
                <div class="detail-item">
                    <span class="detail-label">Adjustment Code:</span>
                    <span class="detail-value"><?php echo $adjustment['adjustment_code']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value"><?php echo date('F d, Y', strtotime($adjustment['date'])); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Branch:</span>
                    <span class="detail-value"><?php echo $adjustment['branch_name']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Company:</span>
                    <span class="detail-value"><?php echo $adjustment['company_name'] ?? '-'; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Type:</span>
                    <span class="detail-value">
                        <span class="type-badge <?php echo $adjustment['adjustment_type'] == 'Increase' ? 'type-increase' : 'type-decrease'; ?>">
                            <?php echo $adjustment['adjustment_type']; ?>
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Main Reason:</span>
                    <span class="detail-value"><?php echo $adjustment['main_reason']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Primary Reason:</span>
                    <span class="detail-value"><?php echo $adjustment['primary_reason']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Secondary Reason:</span>
                    <span class="detail-value"><?php echo $adjustment['secondary_reason']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Total Items:</span>
                    <span class="detail-value"><?php echo $adjustment['total_items']; ?></span>
                </div>
            </div>
            
            <?php if ($adjustment['remarks']): ?>
            <div class="detail-item">
                <span class="detail-label">Remarks:</span>
                <span class="detail-value"><?php echo nl2br(htmlspecialchars($adjustment['remarks'])); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="items-section">
            <h3 class="section-title">Adjustment Items</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th width="5%">S#</th>
                        <th width="20%">Product Code</th>
                        <th width="35%">Product Name</th>
                        <th width="15%" class="text-right">Quantity</th>
                        <th width="15%" class="text-right">Rate (<?php echo $currency_symbol; ?>)</th>
                        <th width="15%" class="text-right">Amount (<?php echo $currency_symbol; ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($items as $item): 
                    ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo $item['product_code']; ?></td>
                        <td><?php echo $item['product_name']; ?></td>
                        <td class="text-right"><?php echo number_format($item['qty'], 2); ?></td>
                        <td class="text-right"><?php echo number_format($item['rate'], 2); ?></td>
                        <td class="text-right"><?php echo number_format($item['stock_value'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="5" class="text-right">Total Amount:</td>
                        <td class="text-right"><?php echo $currency_symbol . number_format($adjustment['total_amount'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="footer">
            <p>Printed on <?php echo date('F d, Y h:i A'); ?></p>
            <p>LedgerOne ERP - Stock Adjustment Report</p>
        </div>
    </div>
</body>
</html>
