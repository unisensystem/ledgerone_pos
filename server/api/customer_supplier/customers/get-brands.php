<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT DISTINCT id, supplier_name as brand_name FROM suppliers WHERE tenant_id = ? AND supplier_name IS NOT NULL AND supplier_name != '' ORDER BY supplier_name");
    $stmt->execute([$tenant_id]);
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'brands' => $brands]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
