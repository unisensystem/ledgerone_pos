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
    $input = json_decode(file_get_contents('php://input'), true);
    
    $currency_id = $input['currency_id'] ?? '';
    $is_base_currency = $input['is_base_currency'] ?? false;
    
    if (empty($currency_id)) {
        echo json_encode(['success' => false, 'message' => 'Currency is required']);
        exit;
    }
    
    // Validate currency exists
    $stmt = $pdo->prepare("SELECT id FROM ledgerone_public.currencies WHERE id = ? AND is_active = 1");
    $stmt->execute([$currency_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid currency']);
        exit;
    }
    
    // Check if already exists for tenant
    $stmt = $pdo->prepare("SELECT id FROM tenant_currencies WHERE tenant_id = ? AND currency_id = ?");
    $stmt->execute([$tenant_id, $currency_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Currency already exists for this tenant']);
        exit;
    }
    
    // Insert tenant currency
    $stmt = $pdo->prepare("INSERT INTO tenant_currencies (tenant_id, currency_id, is_base_currency, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$tenant_id, $currency_id, $is_base_currency ? 1 : 0, $user_id]);
    echo json_encode(['success' => true, 'message' => 'Currency added successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Currency add error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}