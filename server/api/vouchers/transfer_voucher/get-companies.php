<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { http_response_code(401); echo json_encode(['success' => false]); exit(); }
try {
    $stmt = $pdo->prepare("SELECT id, company_name FROM companies WHERE tenant_id = ? AND is_active = 1 ORDER BY company_name");
    $stmt->execute([$tenant_id]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
