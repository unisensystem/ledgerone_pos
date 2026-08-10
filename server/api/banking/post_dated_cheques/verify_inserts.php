<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['error' => 'No tenant_id']);
    exit;
}

// Get recent accounting_ledger entries for PDC
$stmt = $pdo->prepare("SELECT * FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'PDC' ORDER BY id DESC LIMIT 10");
$stmt->execute([$tenant_id]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'entries' => $entries], JSON_PRETTY_PRINT);
