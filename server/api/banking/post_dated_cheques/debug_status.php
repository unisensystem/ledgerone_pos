<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['error' => 'No tenant_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT pdc.id, pdc.cheque_no, pdc.transaction_type, pdc.status, pdc.reference_table, pdc.reference_id, pdc.bank_account_id, ba.account_id as bank_account_ledger_id FROM post_dated_cheques pdc LEFT JOIN bank_accounts ba ON pdc.bank_account_id = ba.id WHERE pdc.tenant_id = ? LIMIT 5");
    $stmt->execute([$tenant_id]);
    $pdcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $pdcs], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
