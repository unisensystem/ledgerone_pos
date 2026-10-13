<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

try {
    // Every active product that has a parent product (e.g. ATTA 10KG -> ATTA)
    $stmt = $pdo->prepare("
        SELECT c.id, c.code, c.name, c.parent_product_id,
               p.name AS parent_name, u.uom_name AS unit_name
        FROM products c
        INNER JOIN products p ON p.id = c.parent_product_id AND p.tenant_id = c.tenant_id
        LEFT JOIN uom u ON u.id = c.default_unit_id
        WHERE c.tenant_id = ? AND c.is_active = 1
        ORDER BY p.name, c.name
    ");
    $stmt->execute([$tenant_id]);

    echo json_encode(['success' => true, 'products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}