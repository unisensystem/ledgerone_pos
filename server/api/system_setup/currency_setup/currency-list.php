<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json; charset=utf-8');
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
    // Get base currency code for tenant
    $stmt = $pdo->prepare("
        SELECT c.code as base_code
        FROM tenant_currencies tc 
        JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
        WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
    ");
    $stmt->execute([$tenant_id]);
    $base_currency = $stmt->fetch();
    $base_code = $base_currency['base_code'] ?? 'USD';
    
    // Get currencies with exchange rates
    $stmt = $pdo->prepare("
        SELECT tc.id, tc.currency_id, c.code, c.name, c.symbol, tc.is_base_currency, tc.is_active,
               COALESCE(er.exchange_rate, 1.0000) as exchange_rate
        FROM tenant_currencies tc 
        JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
        LEFT JOIN (
            SELECT target_currency_code, exchange_rate,
                   ROW_NUMBER() OVER (PARTITION BY target_currency_code ORDER BY effective_date DESC) as rn
            FROM ledgerone_public.currency_exchange_rates 
            WHERE base_currency_code = ? AND is_active = 1
        ) er ON c.code = er.target_currency_code AND er.rn = 1
        WHERE tc.tenant_id = ? 
        ORDER BY tc.is_base_currency DESC, c.name
    ");
    $stmt->execute([$base_code, $tenant_id]);
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($currencies as &$currency) {
        if (isset($currency['symbol_hex'])) {
            $currency['symbol'] = hex2bin($currency['symbol_hex']);
            unset($currency['symbol_hex']);
        }
    }
    
    echo json_encode(['success' => true, 'data' => $currencies], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Currency list error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}