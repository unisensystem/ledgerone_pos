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
    $category_id = $_GET['category_id'] ?? null;
    
    $sql = "
        SELECT s.id, s.subcategory_name as name, c.category_name 
        FROM subcategories s
        LEFT JOIN categories c ON s.category_id = c.id
        WHERE s.tenant_id = ?
    ";
    
    $params = [$tenant_id];
    
    if ($category_id) {
        $sql .= " AND s.category_id = ?";
        $params[] = $category_id;
    }
    
    $sql .= " ORDER BY s.subcategory_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $subcategories]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
