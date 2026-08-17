<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id']) || empty($input['branch_name']) || empty($input['branch_type']) || empty($input['company_id'])) {
        throw new Exception('Branch ID, name, type and company are required');
    }
    
    $sql = "UPDATE branches SET 
        branch_name = ?, branch_type = ?, company_id = ?, parent_branch_id = ?, 
        address = ?, country = ?, state = ?, city = ?, zipcode = ?, 
        phone = ?, email = ?, manager_id = ?, 
        is_active = ?, allows_sales = ?, allows_inventory = ?
        WHERE id = ? AND tenant_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $input['branch_name'],
        $input['branch_type'],
        $input['company_id'],
        $input['parent_branch_id'] ?: null,
        $input['address'] ?? null,
        $input['country'] ?? null,
        $input['state'] ?? null,
        $input['city'] ?? null,
        $input['zipcode'] ?? null,
        $input['phone'] ?? null,
        $input['email'] ?? null,
        $input['manager_id'] ?: null,
        $input['is_active'] ?? 1,
        $input['allows_sales'] ?? 1,
        $input['allows_inventory'] ?? 1,
        $input['id'],
        $tenant_id
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Branch updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update branch');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}