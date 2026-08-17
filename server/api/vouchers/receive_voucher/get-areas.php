<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, area_name
        FROM areas
        WHERE tenant_id = ?
        ORDER BY area_name
    ");
    $stmt->execute([$tenant_id]);
    $areas = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $areas
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
