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
    $query = $_GET['q'] ?? '';
    
    $searchTerm = '%' . $query . '%';

    if (empty($query)) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.code, p.name, p.default_unit_id, p.product_type, p.uom_type, p.uom_group_id,
                   p.price_entry_unit_id,
                   u.uom_name as unit_name
            FROM products p
            LEFT JOIN uom u ON p.default_unit_id = u.id
            WHERE p.tenant_id = ? AND p.is_active = 1
            ORDER BY p.name
            LIMIT 50
        ");
        $stmt->execute([$tenant_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.id, p.code, p.name, p.default_unit_id, p.product_type, p.uom_type, p.uom_group_id,
                   p.price_entry_unit_id,
                   u.uom_name as unit_name
            FROM products p
            LEFT JOIN uom u ON p.default_unit_id = u.id
            WHERE p.tenant_id = ?
                AND (p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)
                AND p.is_active = 1
            ORDER BY p.name
            LIMIT 50
        ");
        $stmt->execute([$tenant_id, $searchTerm, $searchTerm, $searchTerm]);
    }
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'products' => $products]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
