<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get payment methods (accounts)
    $stmt = $pdo->prepare("SELECT id, name FROM accounts WHERE (tenant_id = ? OR tenant_id = 0) AND sub_account_id = 1 ORDER BY name");
    $stmt->execute([$tenant_id]);
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get bank accounts
    $stmt = $pdo->prepare("SELECT id, bank_name, account_number, account_title, bank_logo_path FROM bank_accounts WHERE tenant_id = ? AND is_active = 1 ORDER BY bank_name");
    $stmt->execute([$tenant_id]);
    $bank_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'methods' => $methods,
        'bank_accounts' => $bank_accounts
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}