<?php
// Returns the most recently used signature filename for this tenant, so a new
// invoice can auto-load it as the default — the user only needs to upload a
// signature once; every invoice after that reuses it until they upload a
// different one (which then becomes the new "latest" for future invoices).
// Signatures whose image file is missing from disk are skipped.
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT signature
        FROM sale_invoice
        WHERE tenant_id = ? AND signature IS NOT NULL AND signature <> ''
        GROUP BY signature
        ORDER BY MAX(id) DESC
        LIMIT 30
    ");
    $stmt->execute([$tenant_id]);

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