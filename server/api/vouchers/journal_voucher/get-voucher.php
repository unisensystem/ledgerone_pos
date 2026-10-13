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

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Voucher ID required']);
    exit();
}

try {
    $stmt = $pdo->prepare(
        "SELECT jv.id, jv.voucher_number, jv.voucher_date, jv.description
         FROM journal_voucher jv
         WHERE jv.id = ? AND jv.tenant_id = ?"
    );
    $stmt->execute([$id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$voucher) {
        http_response_code(404);
        echo json_encode(['error' => 'Voucher not found']);
        exit();
    }
    
    // Get entry lines
    $stmt = $pdo->prepare(
        "SELECT jvl.account_id, a.name as account_name, jvl.debit, jvl.credit
         FROM journal_voucher_line jvl
         JOIN accounts a ON jvl.account_id = a.id
         WHERE jvl.voucher_id = ?"
    );
    $stmt->execute([$id]);
    $voucher['entries'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'voucher' => $voucher]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch voucher: ' . $e->getMessage()]);
}
