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
    $unit_id = $_POST['id'] ?? '';
    
    if (empty($unit_id)) {
        echo json_encode(['success' => false, 'message' => 'Unit ID is required']);
        exit;
    }
    
    // Verify unit belongs to tenant
    $stmt = $pdo->prepare("SELECT id FROM uom WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$unit_id, $tenant_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Unit not found']);
        exit;
    }
    
    // Delete the unit
    $stmt = $pdo->prepare("DELETE FROM uom WHERE id = ? AND tenant_id = ? AND tenant_id != 0");
    $stmt->execute([$unit_id, $tenant_id]);
    
    echo json_encode(['success' => true, 'message' => 'Unit deleted successfully']);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}