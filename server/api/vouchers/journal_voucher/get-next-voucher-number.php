<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $year   = date('Y');
    $prefix = strtoupper(preg_replace('/[^A-Z]/', '', $_GET['prefix'] ?? 'JV'));
    if (!in_array($prefix, ['JV', 'ADJ'])) $prefix = 'JV';
    $nextNumber = 1;

    $stmt = $pdo->prepare(
        "SELECT voucher_number FROM journal_voucher
         WHERE tenant_id = ? AND voucher_number LIKE ?
         ORDER BY CAST(SUBSTRING_INDEX(voucher_number, '-', -1) AS UNSIGNED) DESC LIMIT 1"
    );
    $stmt->execute([$tenant_id, "{$prefix}-{$year}-%"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        preg_match('/(\d+)$/', $result['voucher_number'], $matches);
        $lastNumber = isset($matches[1]) ? intval($matches[1]) : 0;
        $nextNumber = $lastNumber + 1;
    }

    $nextVoucherNumber = "{$prefix}-{$year}-" . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

    echo json_encode([
        'success'       => true,
        'voucherNumber' => $nextVoucherNumber
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate voucher number: ' . $e->getMessage()]);
}
