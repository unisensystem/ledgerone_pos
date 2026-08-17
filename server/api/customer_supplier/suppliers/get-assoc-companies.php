<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$supplier_id = $_GET['supplier_id'] ?? null;
if (!$supplier_id) {
    echo json_encode(['success' => false, 'message' => 'supplier_id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT sac.company_id, c.company_name
        FROM supplier_associated_companies sac
        JOIN companies c ON c.id = sac.company_id
        WHERE sac.tenant_id = ? AND sac.supplier_id = ?
        ORDER BY c.company_name
    ");
    $stmt->execute([$tenant_id, $supplier_id]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'companies' => $companies]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
