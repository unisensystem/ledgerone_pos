<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit(); }

$voucher_id = $_GET['id'] ?? null;
if (!$voucher_id) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit(); }

try {
    $stmt = $pdo->prepare("
        SELECT
            tv.*,
            fc.customer_name AS from_customer_name, fc.customer_code AS from_customer_code,
            fs.supplier_name AS from_supplier_name, fs.supplier_code AS from_supplier_code,
            tc.customer_name AS to_customer_name,   tc.customer_code AS to_customer_code,
            ts.supplier_name AS to_supplier_name,   ts.supplier_code AS to_supplier_code,
            a.name AS payment_method_name,
            cur.symbol AS currency_symbol
        FROM transfer_voucher tv
        LEFT JOIN customers fc ON tv.from_customer_id = fc.id
        LEFT JOIN suppliers fs ON tv.from_supplier_id = fs.id
        LEFT JOIN customers tc ON tv.to_customer_id   = tc.id
        LEFT JOIN suppliers ts ON tv.to_supplier_id   = ts.id
        LEFT JOIN accounts  a  ON tv.payment_method_id = a.id
        LEFT JOIN ledgerone_public.currencies cur ON tv.currency_id = cur.id
        WHERE tv.id = ? AND tv.tenant_id = ?
    ");
    $stmt->execute([$voucher_id, $tenant_id]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$v) { echo json_encode(['success' => false, 'error' => 'Not found']); exit(); }

    $v['from_display'] = $v['from_type'] === 'customer'
        ? ($v['from_customer_code'] . ' - ' . $v['from_customer_name'])
        : ($v['from_supplier_code'] . ' - ' . $v['from_supplier_name']);
    $v['to_display'] = $v['to_type'] === 'customer'
        ? ($v['to_customer_code'] . ' - ' . $v['to_customer_name'])
        : ($v['to_supplier_code'] . ' - ' . $v['to_supplier_name']);

    echo json_encode(['success' => true, 'data' => $v]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
