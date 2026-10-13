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
    // Get verified balances from Balance Sheet
    $cash = 164394;
    $bank = 27590;
    $trade_debtors = 23589982;
    $inventory = 1540566.21;
    $trade_creditors = 25136791;
    $retained_earnings = 44044; // From P&L before closing
    
    $total_assets = $cash + $bank + $trade_debtors + $inventory;
    $total_liabilities = $trade_creditors;
    
    // Assets = Liabilities + Equity
    // Equity = Opening Balance Equity + Retained Earnings
    // So: Opening Balance Equity = Assets - Liabilities - Retained Earnings
    
    $required_opening_equity = $total_assets - $total_liabilities - $retained_earnings;
    
    // Get current Opening Balance Equity
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance
        FROM accounting_ledger
        WHERE tenant_id = ? AND account_id = 90
    ");
    $stmt->execute([$tenant_id]);
    $current_opening_equity = $stmt->fetch()['balance'];
    
    $adjustment_needed = $required_opening_equity - $current_opening_equity;
    
    echo json_encode([
        'success' => true,
        'calculation' => [
            'total_assets' => $total_assets,
            'total_liabilities' => $total_liabilities,
            'retained_earnings' => $retained_earnings,
            'required_opening_equity' => $required_opening_equity,
            'current_opening_equity' => $current_opening_equity,
            'adjustment_needed' => $adjustment_needed
        ],
        'message' => 'Adjustment of ' . number_format($adjustment_needed, 2) . ' needed in Opening Balance Equity'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
