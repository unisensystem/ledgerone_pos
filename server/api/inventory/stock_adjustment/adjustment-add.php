<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/posting_lock.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $adjustment_code = $data['adjustment_code'];
    $branch_id = $data['branch_id'];
    $company_id = $data['company_id'] ?? null;
    $adjustment_type = $data['adjustment_type'];
    $main_reason = $data['main_reason'];
    $primary_reason = $data['primary_reason'];
    $secondary_reason = $data['secondary_reason'];
    $remarks = $data['remarks'] ?? null;
    $accounting_treatment = $data['accounting_treatment'];
    $items = $data['items'];
    $date = $data['date'] ?? date('Y-m-d');

    // Posting lock check
    $lockCheck = checkPostingDateAllowed($pdo, $tenant_id, 'stock_adjustment', $date);
    if (!$lockCheck['allowed']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $lockCheck['message']]);
        exit();
    }
    
    // Calculate totals
    $total_amount = 0;
    $total_items = count($items);
    foreach ($items as $item) {
        $total_amount += $item['gross'];
    }
    
    $pdo->beginTransaction();
    
    // Insert stock adjustment
    $stmt = $pdo->prepare("
        INSERT INTO stock_adjustment 
        (tenant_id, company_id, adjustment_code, date, branch_id, adjustment_type, main_reason, primary_reason, secondary_reason, remarks, total_amount, total_items)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $tenant_id,
        $company_id,
        $adjustment_code,
        $date,
        $branch_id,
        $adjustment_type,
        $main_reason,
        $primary_reason,
        $secondary_reason,
        $remarks,
        $total_amount,
        $total_items
    ]);
    
    $adjustment_id = $pdo->lastInsertId();
    
    // Insert stock adjustment items
    $stmt = $pdo->prepare("
        INSERT INTO stock_adjustment_items 
        (tenant_id, adjustment_id, product_id, unit_id, qty, rate, stock_value)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($items as $item) {
        $stmt->execute([
            $tenant_id,
            $adjustment_id,
            $item['product_id'],
            $item['unit_id'] ?? null,
            $item['quantity'],
            $item['rate'],
            $item['gross']
        ]);
    }
    
    // Insert into stock_ledger
    $stock_status = ($main_reason === 'Damage/Spoilage') ? 'damaged' : 'sellable';
    
    $stmt = $pdo->prepare("
        INSERT INTO stock_ledger 
        (tenant_id, account_id, branch_id, product_id, reference_table, reference_id, stock_status, qty_in, qty_out, unit_cost, unit_id, transaction_type, transaction_date)
        VALUES (?, ?, ?, ?, 'stock_adjustment', ?, ?, ?, ?, ?, ?, 'Stock Adjustment', ?)
    ");
    
    foreach ($items as $item) {
        $qty_in = ($adjustment_type === 'increase') ? $item['quantity'] : 0;
        $qty_out = ($adjustment_type === 'decrease') ? $item['quantity'] : 0;
        
        // Get inventory_account_id from products
        $accountStmt = $pdo->prepare("SELECT inventory_account_id FROM products WHERE id = ?");
        $accountStmt->execute([$item['product_id']]);
        $account_id = $accountStmt->fetchColumn();
        
        $stmt->execute([
            $tenant_id,
            $account_id,
            $branch_id,
            $item['product_id'],
            $adjustment_id,
            $stock_status,
            $qty_in,
            $qty_out,
            $item['rate'],
            $item['unit_id'] ?? null,
            $date
        ]);
    }
    
    // Insert into accounting_ledger
    $description = "Stock Adjustment - $adjustment_code - $main_reason - $primary_reason";
    
    foreach ($items as $item) {
        $accountStmt = $pdo->prepare("SELECT inventory_account_id FROM products WHERE id = ?");
        $accountStmt->execute([$item['product_id']]);
        $inventory_account_id = $accountStmt->fetchColumn();
        
        // Debit entry
        $stmt = $pdo->prepare("
            INSERT INTO accounting_ledger 
            (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
            VALUES (?, 'Adjustment Entry', 'stock_adjustment', ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([
            $tenant_id,
            $adjustment_id,
            $accounting_treatment['debit'],
            $date,
            $description,
            $item['gross']
        ]);
        
        // Credit entry
        $stmt = $pdo->prepare("
            INSERT INTO accounting_ledger 
            (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
            VALUES (?, 'Adjustment Entry', 'stock_adjustment', ?, ?, ?, ?, 0, ?)
        ");
        $stmt->execute([
            $tenant_id,
            $adjustment_id,
            $inventory_account_id,
            $date,
            $description,
            $item['gross']
        ]);
    }
    
    logActivity(
        $pdo, $tenant_id, $user_id, 'create', 'Stock Adjustment', $adjustment_id, $adjustment_code,
        "Created stock adjustment \"$adjustment_code\" ($adjustment_type, $main_reason, $total_items item(s), total " . number_format($total_amount, 2) . ")",
        null,
        [
            'adjustment_code' => $adjustment_code, 'date' => $date, 'company_id' => $company_id, 'branch_id' => $branch_id,
            'adjustment_type' => $adjustment_type, 'main_reason' => $main_reason, 'primary_reason' => $primary_reason,
            'secondary_reason' => $secondary_reason, 'remarks' => $remarks, 'total_amount' => $total_amount,
            'total_items' => $total_items, 'items' => $items
        ]
    );

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Stock adjustment posted successfully',
        'adjustment_id' => $adjustment_id
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error posting adjustment: ' . $e->getMessage()
    ]);
}