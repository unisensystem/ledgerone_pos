<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Get company_id from companies table
$stmt = $pdo->prepare("SELECT id FROM companies WHERE tenant_id = ? LIMIT 1");
$stmt->execute([$tenant_id]);
$company_id = $stmt->fetchColumn();

if (!$company_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Company not found']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['branch_name']) || empty($input['branch_type']) || empty($input['company_id'])) {
        throw new Exception('Branch name, type and company are required');
    }
    
    // Generate branch code if not provided
    $branch_code = $input['branch_code'] ?? null;
    if (empty($branch_code)) {
        $type_prefix = strtoupper(substr($input['branch_type'], 0, 2));
        $stmt = $pdo->prepare("SELECT COUNT(*) + 1 as next_num FROM branches WHERE tenant_id = ? AND branch_type = ?");
        $stmt->execute([$tenant_id, $input['branch_type']]);
        $next_num = $stmt->fetchColumn();
        $branch_code = $type_prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
    }
    
    // Insert branch
    $sql = "INSERT INTO branches (
        tenant_id, company_id, parent_branch_id, branch_code, branch_name, branch_type,
        address, country, state, city, zipcode, phone, email, manager_id,
        is_active, allows_sales, allows_inventory, is_default
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $tenant_id,
        $input['company_id'],
        $input['parent_branch_id'] ?: null,
        $branch_code,
        $input['branch_name'],
        $input['branch_type'],
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
        $input['is_default'] ?? 0
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Branch created successfully',
            'branch_id' => $pdo->lastInsertId(),
            'branch_code' => $branch_code
        ]);
    } else {
        throw new Exception('Failed to create branch');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}