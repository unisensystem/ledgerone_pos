<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['invoice_id'], $input['date'], $input['opening_reading'], $input['rate'], $input['closing_reading'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get invoice data
    $stmt = $pdo->prepare("SELECT * FROM station_daily_usage WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['invoice_id'], $tenant_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        throw new Exception('Invoice not found');
    }
    
    $totalDispensed = $input['closing_reading'] - $input['opening_reading'];
    $totalRevenue = $totalDispensed * $input['rate'];
    
    // Check stock availability
    $stockStmt = $pdo->prepare("SELECT SUM(qty_in - qty_out) as total_stock FROM stock_ledger WHERE tenant_id = ? AND branch_id = ? AND product_id = ? AND unit_id = ?");
    $stockStmt->execute([$tenant_id, $invoice['branch_id'], $invoice['product_id'], $invoice['unit_id']]);
    $stockResult = $stockStmt->fetch(PDO::FETCH_ASSOC);
    $totalStock = $stockResult['total_stock'] ?? 0;
    
    // Add back the previously dispensed quantity
    if ($invoice['total_dispensed']) {
        $totalStock += $invoice['total_dispensed'];
    }
    
    $newStock = $totalStock - $totalDispensed;
    
    if ($newStock < 0) {
        throw new Exception('Insufficient stock. Available: ' . $totalStock . ', Required: ' . $totalDispensed);
    }
    
    // Delete old stock and accounting entries
    $stmt = $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'station_daily_usage' AND reference_id = ? AND tenant_id = ?");
    $stmt->execute([$input['invoice_id'], $tenant_id]);
    
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'station_daily_usage' AND reference_id = ? AND tenant_id = ?");
    $stmt->execute([$input['invoice_id'], $tenant_id]);
    
    // Update station_daily_usage
    $stmt = $pdo->prepare("
        UPDATE station_daily_usage 
        SET usage_date = ?, opening_reading = ?, rate = ?, closing_reading = ?, total_revenue = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$input['date'], $input['opening_reading'], $input['rate'], $input['closing_reading'], $totalRevenue, $input['invoice_id'], $tenant_id]);
    
    // Insert new stock_ledger entry
    $stmt = $pdo->prepare("
        INSERT INTO stock_ledger 
        (tenant_id, branch_id, product_id, reference_table, reference_id, qty_out, unit_cost, unit_id, transaction_type, transaction_date)
        VALUES (?, ?, ?, 'station_daily_usage', ?, ?, ?, ?, 'Meter Reading', ?)
    ");
    $stmt->execute([$tenant_id, $invoice['branch_id'], $invoice['product_id'], $input['invoice_id'], $totalDispensed, $input['rate'], $invoice['unit_id'], $invoice['usage_date']]);
    
    // Insert new accounting_ledger entries
    $entries = [
        ['account_id' => 2, 'debit' => $totalRevenue, 'credit' => 0, 'description' => 'Trade Debtors - Fuel Sales'],
        ['account_id' => 9, 'debit' => 0, 'credit' => $totalRevenue, 'description' => 'Sales Revenue - Fuel Sales'],
        ['account_id' => 1, 'debit' => $totalRevenue, 'credit' => 0, 'description' => 'Cash - Fuel Sales Collection'],
        ['account_id' => 2, 'debit' => 0, 'credit' => $totalRevenue, 'description' => 'Trade Debtors - Payment Received']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
        VALUES (?, 'Fuel Sales', 'station_daily_usage', ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($entries as $entry) {
        $stmt->execute([$tenant_id, $input['invoice_id'], $entry['account_id'], $invoice['usage_date'], $entry['description'], $entry['debit'], $entry['credit']]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Invoice updated successfully']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>