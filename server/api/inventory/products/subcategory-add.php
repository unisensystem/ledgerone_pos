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
    $category_id = $_POST['parentCategory'] ?? '';
    $subcategory_name = $_POST['subcategoryName'] ?? '';
    
    if (empty($category_id) || empty($subcategory_name)) {
        echo json_encode(['success' => false, 'message' => 'Parent category and subcategory name are required']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO subcategories (tenant_id, category_id, subcategory_name) VALUES (?, ?, ?)");
    $stmt->execute([$tenant_id, $category_id, $subcategory_name]);
    $subcategory_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Subcategory added successfully',
        'subcategory' => [
            'id' => $subcategory_id,
            'name' => $subcategory_name,
            'category_id' => $category_id
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}