<?php
// Returns the most recently used signature filename for this tenant, so a new
// invoice can auto-load it as the default — the user only needs to upload a
// signature once; every invoice after that reuses it until they upload a
// different one (which then becomes the new "latest" for future invoices).
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
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$tenant_id]);
    $signature = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'signature' => $signature ?: null
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}