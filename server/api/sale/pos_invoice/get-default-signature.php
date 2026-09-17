<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;  // ← YAHI IMPORTANT!

if (!$tenant_id || !$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // ✅ Filter by USER not just TENANT
    $stmt = $pdo->prepare("
        SELECT signature
        FROM sale_invoice
        WHERE tenant_id = ? 
        AND created_by = ?     
        AND signature IS NOT NULL 
        AND signature != ''
        ORDER BY id DESC
        LIMIT 1
    ");
    
    $stmt->execute([$tenant_id, $user_id]);  // ← Dono parameter
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
