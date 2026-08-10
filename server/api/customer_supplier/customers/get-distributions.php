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
    // Fetch distributions (suppliers) for opening balance invoices
    $stmt = $pdo->prepare("
        SELECT id, supplier_code, supplier_name, supplier_type 
        FROM suppliers 
        WHERE tenant_id = ? AND status = 'ACTIVE' AND is_blacklisted = 0
        ORDER BY supplier_code, supplier_name
    ");
    $stmt->execute([$tenant_id]);
    $distributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'distributions' => $distributions]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
