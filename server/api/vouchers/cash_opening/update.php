<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
$username = $_SESSION['username'] ?? 'System';

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id'] ?? null;
$opening_amount = $data['opening_amount'] ?? null;

if (!$id || !$opening_amount) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Get the current record to find the as_of_date
    $stmt = $pdo->prepare("SELECT as_of_date, opening_amount FROM cash_opening WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Record not found']);
        exit();
    }
    
    $old_amount = $record['opening_amount'];
    $as_of_date = $record['as_of_date'];
    $amount_diff = $opening_amount - $old_amount;
    
    // Update cash opening record
    $stmt = $pdo->prepare("UPDATE cash_opening SET opening_amount = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$opening_amount, $id, $tenant_id]);
    
    // Update accounting ledger entries
    // Delete old entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'cash_opening' AND reference_id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);
    
    // Insert new entries
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Debit Cash
    $stmt->execute([
        $tenant_id, 
        'CASH_OPENING', 
        'cash_opening', 
        $id, 
        1, 
        $as_of_date, 
        'Opening Cash In Hand', 
        $opening_amount, 
        0
    ]);
    
    // Credit Opening Balance Equity
    $stmt->execute([
        $tenant_id, 
        'CASH_OPENING', 
        'cash_opening', 
        $id, 
        90, 
        $as_of_date, 
        'Opening Cash In Hand', 
        0, 
        $opening_amount
    ]);
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update record', 'message' => $e->getMessage()]);
}
