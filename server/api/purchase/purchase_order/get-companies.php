<?php
require_once '../../../../includes/connection.php';
session_start();
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

try {
    $stmt = $pdo->prepare("
        SELECT id, company_name
        FROM companies
        WHERE tenant_id = ? AND is_active = 1
        ORDER BY company_name ASC
    ");
    $stmt->execute([$tenant_id]);
    echo json_encode(['success' => true, 'companies' => $stmt->fetchAll()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
