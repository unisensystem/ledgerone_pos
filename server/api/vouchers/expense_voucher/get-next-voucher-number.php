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

try {
    $stmt = $pdo->prepare("SELECT voucher_no FROM expense_voucher WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tenant_id]);
    $lastVoucher = $stmt->fetchColumn();
    
    if ($lastVoucher) {
        // Extract number from voucher (e.g., EXP-2024-00001 -> 1)
        preg_match('/(\d+)$/', $lastVoucher, $matches);
        $lastNumber = isset($matches[1]) ? intval($matches[1]) : 0;
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }
    
    $year = date('Y');
    $voucherNo = sprintf('EXP-%s-%05d', $year, $nextNumber);
    
    echo json_encode(['success' => true, 'voucher_no' => $voucherNo]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate voucher number']);
}
