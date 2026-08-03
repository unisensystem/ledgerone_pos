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
    // Get company IDs this user has access to (within current tenant)
    $companyStmt = $pdo->prepare("
        SELECT company_id
        FROM user_companies
        WHERE user_id = ? AND tenant_id = ?
    ");
    $companyStmt->execute([$user_id, $tenant_id]);
    $allowedCompanyIds = $companyStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($allowedCompanyIds)) {
        http_response_code(404);
        echo json_encode(['error' => 'Voucher not found']);
        exit();
    }

    // Verify voucher belongs to an allowed company before updating
    $checkStmt = $pdo->prepare("SELECT company_id FROM expense_voucher WHERE id = ? AND tenant_id = ?");
    $checkStmt->execute([$id, $tenant_id]);
    $voucher = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher || !in_array($voucher['company_id'], $allowedCompanyIds)) {
        http_response_code(404);
        echo json_encode(['error' => 'Voucher not found']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE expense_voucher SET is_cash_flow = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$is_cash_flow, $id, $tenant_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update cash flow']);
}