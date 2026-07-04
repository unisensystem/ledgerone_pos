<?php
require_once '../../../../includes/connection.php';

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
    $input = json_decode(file_get_contents('php://input'), true);
    
    $company_id = $input['company_id'] ?? null;
    $date = $input['date'];
    $from_location_id = $input['from_location_id'];
    $to_location_id = $input['to_location_id'];
    $container_id = $input['container_id'] ?? null;
    $remarks = $input['remarks'] ?? '';
    $items = $input['items'];
    
    // Validate
    if (!$date || !$from_location_id || !$to_location_id || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Generate transfer code
    $stmt = $pdo->prepare("SELECT transfer_code FROM stock_transfer WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tenant_id]);
    $lastCode = $stmt->fetchColumn();
    
    if ($lastCode) {
        preg_match('/TRF-(\d+)/', $lastCode, $matches);
        $nextNum = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $nextNum = 1;
    }
    $transfer_code = 'TRF-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    
    // Calculate totals
    $total_amount = 0;
    $total_items = count($items);
    foreach ($items as $item) {
        $total_amount += $item['stock_value'];
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Insert stock_transfer
    $stmt = $pdo->prepare(
        "INSERT INTO stock_transfer (tenant_id, company_id, transfer_code, date, from_location_id, to_location_id, container_id, remarks, total_amount, total_items) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$tenant_id, $company_id, $transfer_code, $date, $from_location_id, $to_location_id, $container_id, $remarks, $total_amount, $total_items]);
    $transfer_id = $pdo->lastInsertId();
    
    // Insert stock_transfer_items
    $stmt = $pdo->prepare(
        "INSERT INTO stock_transfer_items (tenant_id, transfer_id, product_id, qty, rate, stock_value) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    
    $ledgerStmt = $pdo->prepare(
        "INSERT INTO stock_ledger (tenant_id, account_id, branch_id, product_id, reference_table, reference_id, qty_in, qty_out, unit_cost, unit_id, transaction_type, transaction_date) 
         VALUES (?, ?, ?, ?, 'stock_transfer', ?, ?, ?, ?, ?, 'Stock Transfer', ?)"
    );
    
    $productStmt = $pdo->prepare("SELECT inventory_account_id FROM products WHERE id = ?");
    
    foreach ($items as $item) {
        $stmt->execute([
            $tenant_id,
            $transfer_id,
            $item['product_id'],
            $item['quantity'],
            $item['rate'],
            $item['stock_value']
        ]);
        
        $productStmt->execute([$item['product_id']]);
        $account_id = $productStmt->fetchColumn();
        
        // Decrease from From Location (qty_out)
        $ledgerStmt->execute([
            $tenant_id,
            $account_id,
            $from_location_id,
            $item['product_id'],
            $transfer_id,
            0,
            $item['quantity'],
            $item['rate'],
            $item['unit_id'],
            $date
        ]);
        
        // Increase in To Location (qty_in)
        $ledgerStmt->execute([
            $tenant_id,
            $account_id,
            $to_location_id,
            $item['product_id'],
            $transfer_id,
            $item['quantity'],
            0,
            $item['rate'],
            $item['unit_id'],
            $date
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Stock transfer posted successfully',
        'transfer_code' => $transfer_code,
        'transfer_id' => $transfer_id
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
