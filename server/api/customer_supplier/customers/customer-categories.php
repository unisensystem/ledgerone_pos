<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all customer categories (system + tenant)
        $stmt = $pdo->prepare("SELECT * FROM customer_categories WHERE tenant_id = 0 OR tenant_id = ? ORDER BY category_name");
        $stmt->execute([$tenant_id]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'categories' => $categories]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new customer category
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['category_name'])) {
            throw new Exception('Category name is required');
        }
        
        // Check duplicate
        $stmt = $pdo->prepare("SELECT id FROM customer_categories WHERE category_name = ? AND (tenant_id = 0 OR tenant_id = ?)");
        $stmt->execute([trim($input['category_name']), $tenant_id]);
        if ($stmt->fetch()) {
            throw new Exception('Customer category already exists');
        }
        
        $stmt = $pdo->prepare("INSERT INTO customer_categories (tenant_id, category_name, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$tenant_id, trim($input['category_name']), $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Customer category added successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update customer category
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['id']) || empty($input['category_name'])) {
            throw new Exception('ID and category name are required');
        }
        
        // Check if system locked
        $stmt = $pdo->prepare("SELECT tenant_id FROM customer_categories WHERE id = ?");
        $stmt->execute([$input['id']]);
        $category = $stmt->fetch();
        
        if (!$category) {
            throw new Exception('Customer category not found');
        }
        
        if ($category['tenant_id'] == 0) {
            throw new Exception('System customer categories cannot be edited');
        }
        
        if ($category['tenant_id'] != $tenant_id) {
            throw new Exception('Unauthorized');
        }
        
        // Check duplicate
        $stmt = $pdo->prepare("SELECT id FROM customer_categories WHERE category_name = ? AND id != ? AND (tenant_id = 0 OR tenant_id = ?)");
        $stmt->execute([trim($input['category_name']), $input['id'], $tenant_id]);
        if ($stmt->fetch()) {
            throw new Exception('Customer category already exists');
        }
        
        $stmt = $pdo->prepare("UPDATE customer_categories SET category_name = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?");
        $stmt->execute([trim($input['category_name']), $user_id, $input['id'], $tenant_id]);
        
        echo json_encode(['success' => true, 'message' => 'Customer category updated successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete customer category
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['id'])) {
            throw new Exception('ID is required');
        }
        
        // Check if system locked
        $stmt = $pdo->prepare("SELECT tenant_id FROM customer_categories WHERE id = ?");
        $stmt->execute([$input['id']]);
        $category = $stmt->fetch();
        
        if (!$category) {
            throw new Exception('Customer category not found');
        }
        
        if ($category['tenant_id'] == 0) {
            throw new Exception('System customer categories cannot be deleted');
        }
        
        if ($category['tenant_id'] != $tenant_id) {
            throw new Exception('Unauthorized');
        }
        
        // Check if in use
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customers WHERE customer_category_id = ? AND tenant_id = ?");
        $stmt->execute([$input['id'], $tenant_id]);
        if ($stmt->fetch()['count'] > 0) {
            throw new Exception('Cannot delete customer category that is in use');
        }
        
        $stmt = $pdo->prepare("DELETE FROM customer_categories WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$input['id'], $tenant_id]);
        
        echo json_encode(['success' => true, 'message' => 'Customer category deleted successfully']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
