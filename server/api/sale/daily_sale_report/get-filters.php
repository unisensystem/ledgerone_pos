<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    // Get sales officers (employees)
    $stmt = $pdo->prepare("SELECT id, full_name FROM employees WHERE tenant_id = ? AND is_active = 1 ORDER BY full_name");
    $stmt->execute([$tenant_id]);
    $salesOfficers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get supplier men (employees)
    $stmt = $pdo->prepare("SELECT id, full_name FROM employees WHERE tenant_id = ? AND is_active = 1 ORDER BY full_name");
    $stmt->execute([$tenant_id]);
    $supplierMen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get vendors (suppliers)
    $stmt = $pdo->prepare("SELECT id, supplier_name FROM suppliers WHERE tenant_id = ? AND status = 'ACTIVE' ORDER BY supplier_name");
    $stmt->execute([$tenant_id]);
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'salesOfficers' => $salesOfficers,
        'supplierMen' => $supplierMen,
        'vendors' => $vendors
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
