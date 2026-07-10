<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit(); }

try {
    $stmt = $pdo->prepare("SELECT id, supplier_name FROM suppliers WHERE tenant_id = ? ORDER BY supplier_name");
    $stmt->execute([$tenant_id]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch vendors']);
}
