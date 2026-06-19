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

$voucher_id = $_GET['id'] ?? null;

if (!$voucher_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Voucher ID is required']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            pv.*,
            s.supplier_name,
            s.supplier_code,
            c.customer_name,
            c.customer_code,
            p.project_code,
            p.project_name
        FROM payment_voucher pv
        LEFT JOIN suppliers s ON pv.supplier_id = s.id
        LEFT JOIN customers c ON pv.customer_id = c.id
        LEFT JOIN projects p ON pv.project_id = p.id
        WHERE pv.id = ? AND pv.tenant_id = ?
    ");
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($voucher) {
        echo json_encode(['success' => true, 'data' => $voucher]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Voucher not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
