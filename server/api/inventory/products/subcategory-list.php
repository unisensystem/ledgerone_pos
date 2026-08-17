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
    $category_id = $_GET['category_id'] ?? '';
    
    $sql = "SELECT id, subcategory_name FROM subcategories WHERE tenant_id = ? OR tenant_id = 0";
    $params = [$tenant_id];
    
    if ($category_id) {
        $sql .= " AND category_id = ?";
        $params[] = $category_id;
    }
    
    $sql .= " ORDER BY subcategory_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'subcategories' => $subcategories]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}