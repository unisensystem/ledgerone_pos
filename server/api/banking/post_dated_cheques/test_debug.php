<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['error' => 'No tenant_id']);
    exit;
}

// Get a pending PDC
$stmt = $pdo->prepare("SELECT pdc.id, pdc.cheque_no, pdc.transaction_type, pdc.status, pdc.amount, pdc.cheque_date, pdc.account_id, pdc.reference_table, pdc.reference_id, pdc.bank_account_id, ba.account_id as bank_account_ledger_id 
FROM post_dated_cheques pdc 
LEFT JOIN bank_accounts ba ON pdc.bank_account_id = ba.id 
WHERE pdc.tenant_id = ? 
LIMIT 1");
$stmt->execute([$tenant_id]);
$pdc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pdc) {
    echo json_encode(['error' => 'No PDC found']);
    exit;
}

$debug = [
    'pdc_data' => $pdc,
    'transaction_type' => $pdc['transaction_type'],
    'transaction_type_match_received' => ($pdc['transaction_type'] === 'Received'),
    'bank_account_id' => $pdc['bank_account_id'],
    'bank_account_ledger_id' => $pdc['bank_account_ledger_id'],
    'reference_table' => $pdc['reference_table'],
    'reference_id' => $pdc['reference_id'],
    'amount' => $pdc['amount']
];

echo json_encode($debug, JSON_PRETTY_PRINT);
