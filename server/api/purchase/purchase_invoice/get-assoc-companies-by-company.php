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
    // Return all distinct associated companies (no company filter)
    $stmt = $pdo->prepare("
        SELECT DISTINCT sac.company_id, c.company_name
        FROM supplier_associated_companies sac
        JOIN companies c ON c.id = sac.company_id
        WHERE sac.tenant_id = ?
        ORDER BY c.company_name
    ");
    $stmt->execute([$tenant_id]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'companies' => $companies]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
