<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || $tenant_id === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$account_id = $input['account_id'] ?? null;
$name = $input['name'] ?? null;

if (!$account_id || !$name) {
    http_response_code(400);
    echo json_encode(['error' => 'Account ID and name are required']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO cost_centers (tenant_id, account_id, name, is_active) VALUES (?, ?, ?, 1)");
    $stmt->execute([$tenant_id, $account_id, $name]);
    
    echo json_encode(['success' => true, 'message' => 'Cost center added successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to add cost center: ' . $e->getMessage()]);
}
