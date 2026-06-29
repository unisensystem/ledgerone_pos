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
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $transfer_id = $input['transfer_id'];
    $company_id = $input['company_id'] ?? null;
    $date = $input['date'];
    $from_location_id = $input['from_location_id'];
    $to_location_id = $input['to_location_id'];
    $container_id = $input['container_id'] ?? null;
    $remarks = $input['remarks'] ?? '';
    $items = $input['items'];
    
    if (!$transfer_id || !$date || !$from_location_id || !$to_location_id || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Calculate totals
    $total_amount = 0;
    $total_items = count($items);
    foreach ($items as $item) {
        $total_amount += $item['stock_value'];
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete old stock ledger entries
    $stmt = $pdo->prepare("DELETE FROM stock_ledger WHERE tenant_id = ? AND reference_table = 'stock_transfer' AND reference_id = ?");
    $stmt->execute([$tenant_id, $transfer_id]);
    
    // Delete old items
    $stmt = $pdo->prepare("DELETE FROM stock_transfer_items WHERE tenant_id = ? AND transfer_id = ?");
    $stmt->execute([$tenant_id, $transfer_id]);
    
    // Update stock_transfer
    $stmt = $pdo->prepare(
        "UPDATE stock_transfer 
         SET company_id = ?, date = ?, from_location_id = ?, to_location_id = ?, container_id = ?, remarks = ?, total_amount = ?, total_items = ?
         WHERE tenant_id = ? AND id = ?"
    );
    $stmt->execute([$company_id, $date, $from_location_id, $to_location_id, $container_id, $remarks, $total_amount, $total_items, $tenant_id, $transfer_id]);
    
    // Insert new items
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
        
        // Decrease from From Location
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
        
        // Increase in To Location
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
        'message' => 'Stock transfer updated successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
