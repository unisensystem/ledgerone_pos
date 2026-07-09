<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT tc.currency_id, c.code, c.name, c.symbol
        FROM tenant_currencies tc 
        JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
        WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
    ");
    $stmt->execute([$tenant_id]);
    $row = $stmt->fetch();
    
    if ($row) {
        echo json_encode(['success' => true, 'currency_id' => $row['currency_id'], 'code' => $row['code'], 'name' => $row['name'], 'symbol' => $row['symbol']]);
    } else {
        echo json_encode(['success' => false, 'currency_id' => 2, 'code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch currency', 'message' => $e->getMessage()]);
}
