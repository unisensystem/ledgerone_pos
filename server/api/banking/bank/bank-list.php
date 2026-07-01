<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

try {
    $stmt = $pdo->prepare("
        SELECT ba.id, ba.bank_name, ba.account_number, ba.account_title, ba.account_type, ba.is_mfb, 
               ba.mfb_name, ba.currency_id, c.code as currency_code, c.name as currency_name, c.symbol as currency_symbol,
               ba.branch_name, ba.branch_city, ba.branch_state, 
               ba.opening_balance, ba.debit_amount, ba.credit_amount, ba.balance_type, 
               ba.is_active, ba.created_at, ba.bank_logo_path, ba.iban, ba.swift_code, 
               ba.contact_person, ba.contact_number, ba.email, ba.branch_code
        FROM bank_accounts ba
        LEFT JOIN tenant_currencies tc ON ba.currency_id = tc.id
        LEFT JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
        WHERE ba.tenant_id = ? 
        ORDER BY ba.created_at DESC
    ");
    
    $stmt->execute([$tenant_id]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate current balance and format data
    $totalBalance = 0;
    $activeCount = 0;
    $mfbCount = 0;
    
    foreach ($accounts as &$account) {
        $balance = $account['opening_balance'] + $account['debit_amount'] - $account['credit_amount'];
        if ($account['balance_type'] === 'credit') {
            $balance = -$balance;
        }
        $account['balance'] = $balance;
        $account['status'] = $account['is_active'] ? 'active' : 'inactive';
        
        // Calculate stats
        $totalBalance += $balance;
        if ($account['is_active']) $activeCount++;
        if ($account['is_mfb']) $mfbCount++;
    }
    
    $stats = [
        'total_accounts' => count($accounts),
        'active_accounts' => $activeCount,
        'total_balance' => $totalBalance,
        'mfb_accounts' => $mfbCount,
        'base_currency_code' => $accounts[0]['currency_code'] ?? 'PKR',
        'base_currency_symbol' => $accounts[0]['currency_symbol'] ?? '₨'
    ];
    
    echo json_encode(['success' => true, 'data' => $accounts, 'stats' => $stats]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}