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
        // Get all customer types (system + tenant)
        $stmt = $pdo->prepare("SELECT * FROM customer_types WHERE tenant_id = 0 OR tenant_id = ? ORDER BY type_name");
        $stmt->execute([$tenant_id]);
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'types' => $types]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new customer type
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['type_name'])) {
            throw new Exception('Type name is required');
        }
        
        // Check duplicate
        $stmt = $pdo->prepare("SELECT id FROM customer_types WHERE type_name = ? AND (tenant_id = 0 OR tenant_id = ?)");
        $stmt->execute([trim($input['type_name']), $tenant_id]);
        if ($stmt->fetch()) {
            throw new Exception('Customer type already exists');
        }
        
        $stmt = $pdo->prepare("INSERT INTO customer_types (tenant_id, type_name, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$tenant_id, trim($input['type_name']), $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Customer type added successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update customer type
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['id']) || empty($input['type_name'])) {
            throw new Exception('ID and type name are required');
        }
        
        // Check if system locked
        $stmt = $pdo->prepare("SELECT tenant_id FROM customer_types WHERE id = ?");
        $stmt->execute([$input['id']]);
        $type = $stmt->fetch();
        
        if (!$type) {
            throw new Exception('Customer type not found');
        }
        
        if ($type['tenant_id'] == 0) {
            throw new Exception('System customer types cannot be edited');
        }
        
        if ($type['tenant_id'] != $tenant_id) {
            throw new Exception('Unauthorized');
        }
        
        // Check duplicate
        $stmt = $pdo->prepare("SELECT id FROM customer_types WHERE type_name = ? AND id != ? AND (tenant_id = 0 OR tenant_id = ?)");
        $stmt->execute([trim($input['type_name']), $input['id'], $tenant_id]);
        if ($stmt->fetch()) {
            throw new Exception('Customer type already exists');
        }
        
        $stmt = $pdo->prepare("UPDATE customer_types SET type_name = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?");
        $stmt->execute([trim($input['type_name']), $user_id, $input['id'], $tenant_id]);
        
        echo json_encode(['success' => true, 'message' => 'Customer type updated successfully']);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete customer type
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['id'])) {
            throw new Exception('ID is required');
        }
        
        // Check if system locked
        $stmt = $pdo->prepare("SELECT tenant_id FROM customer_types WHERE id = ?");
        $stmt->execute([$input['id']]);
        $type = $stmt->fetch();
        
        if (!$type) {
            throw new Exception('Customer type not found');
        }
        
        if ($type['tenant_id'] == 0) {
            throw new Exception('System customer types cannot be deleted');
        }
        
        if ($type['tenant_id'] != $tenant_id) {
            throw new Exception('Unauthorized');
        }
        
        // Check if in use
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customers WHERE customer_type_id = ? AND tenant_id = ?");
        $stmt->execute([$input['id'], $tenant_id]);
        if ($stmt->fetch()['count'] > 0) {
            throw new Exception('Cannot delete customer type that is in use');
        }
        
        $stmt = $pdo->prepare("DELETE FROM customer_types WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$input['id'], $tenant_id]);
        
        echo json_encode(['success' => true, 'message' => 'Customer type deleted successfully']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
