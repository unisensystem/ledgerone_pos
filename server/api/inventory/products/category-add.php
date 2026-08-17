<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    $category_name = $_POST['categoryName'] ?? '';
    
    if (empty($category_name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO categories (tenant_id, category_name) VALUES (?, ?)");
    $stmt->execute([$tenant_id, $category_name]);
    $category_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Category added successfully',
        'category' => [
            'id' => $category_id,
            'name' => $category_name
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}