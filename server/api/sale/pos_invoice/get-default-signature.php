<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$tenant_id || !$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Filter by USER (not just tenant), latest first. Up to 30 recent distinct
    // signatures are fetched so we can skip any whose image file no longer exists
    // on disk, instead of re-attaching a lost file to every new invoice.
    $stmt = $pdo->prepare("
        SELECT signature
        FROM sale_invoice
        WHERE tenant_id = ?
        AND created_by = ?
        AND signature IS NOT NULL
        AND signature != ''
        GROUP BY signature
        ORDER BY MAX(id) DESC
        LIMIT 30
    ");
    $stmt->execute([$tenant_id, $user_id]);

    $uploadDir = __DIR__ . '/../../../../client/assets/uploads/signatures/';
    $signature = null;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $candidate) {
        if (is_file($uploadDir . basename($candidate))) {
            $signature = $candidate;
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'signature' => $signature
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}