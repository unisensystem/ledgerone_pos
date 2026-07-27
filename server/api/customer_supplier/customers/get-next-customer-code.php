<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id   = $_SESSION['user_id']   ?? null;

if (!$tenant_id || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$company_id = $_GET['company_id'] ?? null;
if (!$company_id) {
    echo json_encode(['success' => false, 'message' => 'company_id is required']);
    exit;
}

try {
    // Get last customer_code for this specific company under this tenant
    $stmt = $pdo->prepare("SELECT customer_code FROM customers WHERE tenant_id = ? AND company_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tenant_id, (int)$company_id]);
    $last = $stmt->fetch();

    if ($last) {
        $lastNum = (int)substr($last['customer_code'], 5);
        $nextNum = $lastNum + 1;
    } else {
        $nextNum = 1;
    }

    $customer_code = 'CUST-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    echo json_encode(['success' => true, 'customer_code' => $customer_code]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
