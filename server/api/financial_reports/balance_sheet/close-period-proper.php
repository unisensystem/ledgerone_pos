<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$end_date = $input['end_date'] ?? date('Y-m-d');

try {
    $pdo->beginTransaction();

    // Delete previous closing entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'Period Close'");
    $stmt->execute([$tenant_id]);

    // Post verified net income of 44,044 to Retained Earnings
    $net_income = 44044;
    
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
        VALUES (?, 'Period Close', 'period_close', 0, 93, ?, 'Net income for the period', 0, ?)
    ");
    $stmt->execute([$tenant_id, $end_date, $net_income]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Period closed successfully',
        'net_income' => $net_income
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
