<?php
require_once '../../../../includes/connection.php';
session_start();
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, supplier_code, supplier_name
        FROM suppliers
        WHERE tenant_id = ? AND status = 'active'
        ORDER BY supplier_name ASC
    ");
    $stmt->execute([$tenant_id]);
    echo json_encode(['success' => true, 'suppliers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
