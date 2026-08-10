<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
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

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['invoice_id'], $input['rate'], $input['closing_reading'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$totalCash = $input['total_cash'] ?? 0;
$revenueSplits = $input['revenue_splits'] ?? [];

try {
    $pdo->beginTransaction();
    
    // Get timezone from companies table
    $tzStmt = $pdo->prepare("SELECT timezone FROM companies WHERE tenant_id = ? LIMIT 1");
    $tzStmt->execute([$tenant_id]);
    $tzResult = $tzStmt->fetch(PDO::FETCH_ASSOC);
    $timezone = $tzResult['timezone'];
    date_default_timezone_set($timezone);
    
    // Get invoice data
    $stmt = $pdo->prepare("SELECT * FROM station_daily_usage WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['invoice_id'], $tenant_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        throw new Exception('Invoice not found');
    }
    
    $totalDispensed = $input['closing_reading'] - $invoice['opening_reading'];
    $totalRevenue = $totalDispensed * $input['rate'];
    
    // Check stock availability
    $stockStmt = $pdo->prepare("SELECT SUM(qty_in - qty_out) as total_stock FROM stock_ledger WHERE tenant_id = ? AND branch_id = ? AND product_id = ? AND unit_id = ?");
    $stockStmt->execute([$tenant_id, $invoice['branch_id'], $invoice['product_id'], $invoice['unit_id']]);
    $stockResult = $stockStmt->fetch(PDO::FETCH_ASSOC);
    $totalStock = $stockResult['total_stock'] ?? 0;
    $newStock = $totalStock - $totalDispensed;
    
    if ($newStock < 0) {
        throw new Exception('Insufficient stock. Available: ' . $totalStock . ', Required: ' . $totalDispensed);
    }
    
    // Update station_daily_usage
    $stmt = $pdo->prepare("UPDATE station_daily_usage SET rate = ?, closing_reading = ?, total_revenue = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['rate'], $input['closing_reading'], $totalRevenue, $input['invoice_id'], $tenant_id]);
    
    // Insert into stock_ledger
    $stmt = $pdo->prepare("
        INSERT INTO stock_ledger 
        (tenant_id, branch_id, product_id, reference_table, reference_id, qty_out, unit_cost, unit_id, transaction_type, transaction_date)
        VALUES (?, ?, ?, 'station_daily_usage', ?, ?, ?, ?, 'Meter Reading', ?)
    ");
    $stmt->execute([$tenant_id, $invoice['branch_id'], $invoice['product_id'], $input['invoice_id'], $totalDispensed, $input['rate'], $invoice['unit_id'], $invoice['usage_date']]);
    
    // Insert into accounting_ledger
    $entries = [
        ['account_id' => 2, 'debit' => $totalRevenue, 'credit' => 0, 'description' => 'Trade Debtors - Fuel Sales'],
        ['account_id' => 9, 'debit' => 0, 'credit' => $totalRevenue, 'description' => 'Sales Revenue - Fuel Sales'],
        ['account_id' => 1, 'debit' => $totalCash, 'credit' => 0, 'description' => 'Cash - Fuel Sales Collection'],
        ['account_id' => 2, 'debit' => 0, 'credit' => $totalCash, 'description' => 'Trade Debtors - Payment Received']
    ];
    
    // Add revenue split entries
    if (!empty($revenueSplits)) {
        foreach ($revenueSplits as $split) {
            $entries[] = ['account_id' => $split['account_id'], 'debit' => $split['amount'], 'credit' => 0, 'description' => 'Fuel Sales Collection'];
            $entries[] = ['account_id' => 2, 'debit' => 0, 'credit' => $split['amount'], 'description' => 'Trade Debtors - Payment Received'];
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
        VALUES (?, 'Fuel Sales', 'station_daily_usage', ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($entries as $entry) {
        $stmt->execute([$tenant_id, $input['invoice_id'], $entry['account_id'], $invoice['usage_date'], $entry['description'], $entry['debit'], $entry['credit']]);
    }
    
    // Insert revenue splits into revenue_split table
    $revenueSplitStmt = $pdo->prepare("
        INSERT INTO revenue_split (tenant_id, station_daily_usage_id, account_id, amount)
        VALUES (?, ?, ?, ?)
    ");
    
    if (!empty($revenueSplits)) {
        foreach ($revenueSplits as $split) {
            $revenueSplitStmt->execute([$tenant_id, $input['invoice_id'], $split['account_id'], $split['amount']]);
        }
    }
    
    // Insert total cash with account_id = 1
    if ($totalCash > 0) {
        $revenueSplitStmt->execute([$tenant_id, $input['invoice_id'], 1, $totalCash]);
    }
    
    // Create next invoice with closing reading as opening reading
    $nextDate = date('Y-m-d', strtotime($invoice['usage_date'] . ' +1 day'));
    $nextStmt = $pdo->prepare("
        INSERT INTO station_daily_usage 
        (tenant_id, station_id, branch_id, product_id, unit_id, usage_date, opening_reading, recorded_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $nextStmt->execute([
        $tenant_id,
        $invoice['station_id'],
        $invoice['branch_id'],
        $invoice['product_id'],
        $invoice['unit_id'],
        $nextDate,
        $input['closing_reading'],
        $user_id
    ]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Invoice completed successfully']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>