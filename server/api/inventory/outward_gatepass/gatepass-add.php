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
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    if (empty($input['date']) || empty($input['company_id']) || empty($input['supplier_id']) || empty($input['branch_id']) || empty($input['items'])) {
        throw new Exception('Missing required fields');
    }
    
    $container_id = $input['container_id'] ?? null;
    
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT gatepass_code FROM outward_gatepass WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tenant_id]);
    $lastCode = $stmt->fetchColumn();
    
    if ($lastCode) {
        $lastNum = intval(substr($lastCode, strrpos($lastCode, '-') + 1));
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }
    $gatepass_code = 'OGP-' . date('Y') . '-' . str_pad($newNum, 5, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("
        INSERT INTO outward_gatepass (tenant_id, company_id, gatepass_code, date, supplier_id, branch_id, container_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $tenant_id,
        $input['company_id'],
        $gatepass_code,
        $input['date'],
        $input['supplier_id'],
        $input['branch_id'],
        $container_id
    ]);
    
    $gatepass_id = $pdo->lastInsertId();
    
    // Insert outward_gatepass_items
    $stmt = $pdo->prepare("
        INSERT INTO outward_gatepass_items (tenant_id, gatepass_id, product_id, unit_id, quantity) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($input['items'] as $item) {
        $stmt->execute([
            $tenant_id,
            $gatepass_id,
            $item['product_id'],
            $item['unit_id'],
            $item['quantity']
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Outward gatepass created successfully',
        'gatepass_code' => $gatepass_code,
        'gatepass_id' => $gatepass_id
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}