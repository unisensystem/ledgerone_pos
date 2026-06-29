<?php
session_start();
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

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['id'])) {
        throw new Exception('Invalid input data');
    }
    
    if (empty($input['date']) || empty($input['company_id']) || empty($input['supplier_id']) || empty($input['branch_id']) || empty($input['items'])) {
        throw new Exception('Missing required fields');
    }
    
    $container_id = $input['container_id'] ?? null;
    
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        UPDATE inward_gatepass 
        SET date = ?, company_id = ?, supplier_id = ?, branch_id = ?, container_id = ? 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([
        $input['date'],
        $input['company_id'],
        $input['supplier_id'],
        $input['branch_id'],
        $container_id,
        $input['id'],
        $tenant_id
    ]);
    
    // Check if gatepass exists
    $stmt = $pdo->prepare("SELECT id FROM inward_gatepass WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['id'], $tenant_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Gatepass not found');
    }
    
    // Delete existing items
    $stmt = $pdo->prepare("DELETE FROM inward_gatepass_items WHERE gatepass_id = ? AND tenant_id = ?");
    $stmt->execute([$input['id'], $tenant_id]);
    
    // Insert new items
    $stmt = $pdo->prepare("
        INSERT INTO inward_gatepass_items (tenant_id, gatepass_id, product_id, unit_id, quantity) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($input['items'] as $item) {
        $stmt->execute([
            $tenant_id,
            $input['id'],
            $item['product_id'],
            $item['unit_id'],
            $item['quantity']
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Inward gatepass updated successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
