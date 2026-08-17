<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get all CASH_OPENING entries
    $stmt = $pdo->prepare("
        SELECT 
            id,
            account_id,
            transaction_type,
            reference_table,
            reference_id,
            date,
            description,
            debit,
            credit
        FROM accounting_ledger
        WHERE tenant_id = ? AND transaction_type = 'CASH_OPENING'
        ORDER BY id
    ");
    $stmt->execute([$tenant_id]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get account names
    foreach ($entries as &$entry) {
        $stmt = $pdo->prepare("SELECT name FROM accounts WHERE id = ?");
        $stmt->execute([$entry['account_id']]);
        $entry['account_name'] = $stmt->fetch()['name'] ?? 'Unknown';
    }
    
    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'total_entries' => count($entries)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
