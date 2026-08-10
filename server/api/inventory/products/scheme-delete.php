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
    $scheme_id = $_POST['id'] ?? '';

    if (empty($scheme_id)) {
        echo json_encode(['success' => false, 'message' => 'Scheme ID is required']);
        exit;
    }

    // Verify scheme belongs to tenant
    $stmt = $pdo->prepare("SELECT id FROM product_schemes WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$scheme_id, $tenant_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Scheme not found']);
        exit;
    }

    // Delete the scheme
    $stmt = $pdo->prepare("DELETE FROM product_schemes WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$scheme_id, $tenant_id]);

    echo json_encode(['success' => true, 'message' => 'Scheme deleted successfully']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
