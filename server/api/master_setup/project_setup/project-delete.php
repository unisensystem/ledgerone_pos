<?php
require_once '../../../../includes/connection.php';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'message' => 'Project ID required']); exit; }

try {
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Project not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
