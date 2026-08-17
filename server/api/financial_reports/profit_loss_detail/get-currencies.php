<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name,
            c.code,
            c.symbol,
            tc.is_base_currency as is_base
        FROM tenant_currencies tc
        JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
        WHERE tc.tenant_id = ?
        ORDER BY tc.is_base_currency DESC, c.name
    ");
    $stmt->execute([$tenant_id]);
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $currencies]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
