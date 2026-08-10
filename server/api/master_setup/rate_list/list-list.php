<?php
session_start();
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

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            rl.id,
            rl.list_code,
            rl.list_name,
            rl.created_at,
            COUNT(DISTINCT rli.customer_id) as customer_count,
            COUNT(DISTINCT rli.product_id) as item_count
        FROM rate_list rl
        LEFT JOIN rate_list_items rli ON rl.id = rli.rate_list_id
        WHERE rl.tenant_id = ?
        GROUP BY rl.id, rl.list_code, rl.list_name, rl.created_at
        ORDER BY rl.id DESC
    ");
    $stmt->execute([$tenant_id]);
    $rateLists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get customers for each rate list
    foreach ($rateLists as &$rateList) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.id, c.customer_name
            FROM rate_list_items rli
            JOIN customers c ON rli.customer_id = c.id
            WHERE rli.rate_list_id = ?
            LIMIT 3
        ");
        $stmt->execute([$rateList['id']]);
        $rateList['customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'rateLists' => $rateLists
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
