<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    // Get last adjustment code for this tenant
    $stmt = $pdo->prepare("
        SELECT adjustment_code 
        FROM stock_adjustment 
        WHERE tenant_id = ?
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$tenant_id]);
    $lastCode = $stmt->fetchColumn();
    
    if ($lastCode) {
        // Extract number and increment
        preg_match('/ADJ-(\d+)/', $lastCode, $matches);
        $nextNumber = intval($matches[1]) + 1;
    } else {
        $nextNumber = 1;
    }
    
    $newCode = sprintf("ADJ-%04d", $nextNumber);
    
    echo json_encode([
        'success' => true,
        'adjustment_code' => $newCode
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error generating code: ' . $e->getMessage()
    ]);
}
