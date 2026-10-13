<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

if (!$tenant_id || !$start_date || !$end_date) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Test 1: Check sale_invoice data
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sale_invoice WHERE tenant_id = ? AND sale_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $start_date, $end_date]);
    $sale_invoice_count = $stmt->fetch()['count'];
    
    // Test 2: Check accounting_ledger expenses
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM accounting_ledger al
        JOIN accounts a ON al.account_id = a.id
        JOIN sub_accounts sa ON a.sub_account_id = sa.id
        WHERE al.tenant_id = ?
        AND al.date BETWEEN ? AND ?
        AND sa.account_head_id = 5
        AND al.account_id != 19
    ");
    $stmt->execute([$tenant_id, $start_date, $end_date]);
    $expense_count = $stmt->fetch()['count'];
    
    // Test 3: Check station_daily_usage
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM station_daily_usage WHERE tenant_id = ? AND usage_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $start_date, $end_date]);
    $fuel_count = $stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'sale_invoice_count' => $sale_invoice_count,
            'expense_ledger_count' => $expense_count,
            'fuel_sales_count' => $fuel_count,
            'date_range' => "$start_date to $end_date",
            'tenant_id' => $tenant_id
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
