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
$id = $input['id'] ?? null;
$is_cash_flow = isset($input['is_cash_flow']) ? intval($input['is_cash_flow']) : null;

if (!$id || $is_cash_flow === null) {
    http_response_code(400);
    echo json_encode(['error' => 'ID and is_cash_flow are required']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE expense_voucher SET is_cash_flow = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$is_cash_flow, $id, $tenant_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update cash flow']);
}
