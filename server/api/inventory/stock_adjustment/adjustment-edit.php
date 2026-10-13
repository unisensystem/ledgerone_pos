<?php
require_once '../../../../includes/connection.php';
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
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $adjustment_id = $data['adjustment_id'];
    $branch_id = $data['branch_id'];
    $company_id = $data['company_id'] ?? null;
    $adjustment_type = $data['adjustment_type'];
    $main_reason = $data['main_reason'];
    $primary_reason = $data['primary_reason'];
    $secondary_reason = $data['secondary_reason'];
    $remarks = $data['remarks'] ?? null;
    $accounting_treatment = $data['accounting_treatment'];
    $items = $data['items'];
    $date = date('Y-m-d');
    
    // Calculate totals
    $total_amount = 0;
    $total_items = count($items);
    foreach ($items as $item) {
        $total_amount += $item['gross'];
    }
    
    // Grab old values before anything changes, for the Activity Log diff
    $stmt = $pdo->prepare("SELECT adjustment_code, branch_id, company_id, adjustment_type, main_reason, primary_reason, secondary_reason, remarks, total_amount, total_items FROM stock_adjustment WHERE tenant_id = ? AND id = ?");
    $stmt->execute([$tenant_id, $adjustment_id]);
    $oldAdjustment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$oldAdjustment) {
        throw new Exception('Adjustment not found');
    }
    $stmt = $pdo->prepare("SELECT product_id, unit_id, qty, rate FROM stock_adjustment_items WHERE tenant_id = ? AND adjustment_id = ? ORDER BY product_id, unit_id, id");
    $stmt->execute([$tenant_id, $adjustment_id]);
    $oldItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    // Delete old entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND reference_table = 'stock_adjustment' AND reference_id = ?");
    $stmt->execute([$tenant_id, $adjustment_id]);
    
    $stmt = $pdo->prepare("DELETE FROM stock_ledger WHERE tenant_id = ? AND reference_table = 'stock_adjustment' AND reference_id = ?");
    $stmt->execute([$tenant_id, $adjustment_id]);
    
    $stmt = $pdo->prepare("DELETE FROM stock_adjustment_items WHERE tenant_id = ? AND adjustment_id = ?");
    $stmt->execute([$tenant_id, $adjustment_id]);
    
    // Update stock adjustment
    $stmt = $pdo->prepare("
        UPDATE stock_adjustment 
        SET branch_id = ?, company_id = ?, adjustment_type = ?, main_reason = ?, primary_reason = ?, secondary_reason = ?, remarks = ?, total_amount = ?, total_items = ?
        WHERE tenant_id = ? AND id = ?
    ");
    $stmt->execute([
        $branch_id,
        $company_id,
        $adjustment_type,
        $main_reason,
        $primary_reason,
        $secondary_reason,
        $remarks,
        $total_amount,
        $total_items,
        $tenant_id,
        $adjustment_id
    ]);
    
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
    
    // Get adjustment code for description
    $stmt = $pdo->prepare("SELECT adjustment_code FROM stock_adjustment WHERE id = ?");
    $stmt->execute([$adjustment_id]);
    $adjustment_code = $stmt->fetchColumn();
    
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
    
    $newAdjustment = [
        'adjustment_code' => $oldAdjustment['adjustment_code'],
        'branch_id' => $branch_id,
        'company_id' => $company_id,
        // adjustment_type is stored as an ENUM('Increase','Decrease') - MySQL matches ENUM
        // values case-insensitively on write but always reads back the label's defined
        // casing, so normalize here to match what $oldAdjustment (fresh from the DB) has,
        // otherwise every edit falsely shows "Increase -> increase" even when unchanged.
        'adjustment_type' => ucfirst(strtolower($adjustment_type)),
        'main_reason' => $main_reason,
        'primary_reason' => $primary_reason,
        'secondary_reason' => $secondary_reason,
        'remarks' => $remarks,
        'total_amount' => $total_amount,
        'total_items' => $total_items
    ];
    $changeDescriptions = buildChangeDescription($oldAdjustment, $newAdjustment, [
        'branch_id' => 'Branch',
        'company_id' => 'Company',
        'adjustment_type' => 'Adjustment Type',
        'main_reason' => 'Main Reason',
        'primary_reason' => 'Primary Reason',
        'secondary_reason' => 'Secondary Reason',
        'remarks' => 'Remarks',
        'total_amount' => 'Total Amount'
    ]);
    // Old items (DB) use 'qty', new items (submitted) use 'quantity' - normalize to a
    // common key before comparing.
    $newItemsNormalized = array_map(function ($item) {
        return ['product_id' => $item['product_id'], 'unit_id' => $item['unit_id'] ?? null, 'qty' => $item['quantity'], 'rate' => $item['rate']];
    }, $items);
    if (itemsChanged($oldItems, $newItemsNormalized, ['product_id', 'unit_id', 'qty', 'rate'])) {
        $changeDescriptions[] = 'Items updated';
    }
    $editDescription = !empty($changeDescriptions)
        ? "Updated stock adjustment \"{$oldAdjustment['adjustment_code']}\": " . implode('; ', $changeDescriptions)
        : "Updated stock adjustment \"{$oldAdjustment['adjustment_code']}\" - no tracked field changes";
    logActivity($pdo, $tenant_id, $user_id, 'update', 'Stock Adjustment', $adjustment_id, $oldAdjustment['adjustment_code'], $editDescription, $oldAdjustment, $newAdjustment);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Stock adjustment updated successfully'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error updating adjustment: ' . $e->getMessage()
    ]);
}