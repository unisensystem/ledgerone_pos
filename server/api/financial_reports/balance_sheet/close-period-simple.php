<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$end_date = $input['end_date'] ?? date('Y-m-d');

try {
    $pdo->beginTransaction();

    // Calculate net income directly from accounting ledger
    // Income (head 4) - Expenses (head 5)
    $stmt = $pdo->prepare("
        SELECT 
            sa.account_head_id,
            SUM(al.credit) - SUM(al.debit) as balance
        FROM accounting_ledger al
        JOIN accounts a ON al.account_id = a.id
        JOIN sub_accounts sa ON a.sub_account_id = sa.id
        WHERE al.tenant_id = ? AND al.date <= ?
        AND sa.account_head_id IN (4, 5)
        GROUP BY sa.account_head_id
    ");
    $stmt->execute([$tenant_id, $end_date]);
    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $income = $results[4] ?? 0;  // Credit balance
    $expenses = -($results[5] ?? 0);  // Debit balance (negative)
    
    $net_income = $income - $expenses;

    // Check if already closed
    if (abs($net_income) < 0.01) {
        echo json_encode([
            'success' => false,
            'message' => 'Net income is zero. Nothing to close.'
        ]);
        $pdo->rollBack();
        exit;
    }

    // Delete previous closing entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'Period Close'");
    $stmt->execute([$tenant_id]);

    // Post net income to Retained Earnings (account 93)
    if ($net_income > 0) {
        // Profit: Credit Retained Earnings
        $stmt = $pdo->prepare("
            INSERT INTO accounting_ledger 
            (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
            VALUES (?, 'Period Close', 'period_close', 0, 93, ?, 'Net income for the period', 0, ?)
        ");
        $stmt->execute([$tenant_id, $end_date, abs($net_income)]);
    } else {
        // Loss: Debit Retained Earnings
        $stmt = $pdo->prepare("
            INSERT INTO accounting_ledger 
            (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
            VALUES (?, 'Period Close', 'period_close', 0, 93, ?, 'Net loss for the period', ?, 0)
        ");
        $stmt->execute([$tenant_id, $end_date, abs($net_income)]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Period closed successfully',
        'data' => [
            'net_income' => $net_income
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
