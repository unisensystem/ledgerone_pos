<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $type = $_GET['type'] ?? null;
    $id = $_GET['id'] ?? null;

    if (!$type || !$id) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }

    if ($type === 'invoice') {
        $stmt = $pdo->prepare("
            SELECT pii.quantity, p.name as product_name, u.uom_name
            FROM purchase_invoice_items pii
            JOIN products p ON pii.product_id = p.id
            JOIN uom u ON pii.uom_id = u.id
            WHERE pii.purchase_invoice_id = ? AND pii.tenant_id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT pri.quantity, p.name as product_name, u.uom_name
            FROM purchase_return_items pri
            JOIN products p ON pri.product_id = p.id
            JOIN uom u ON pri.uom_id = u.id
            WHERE pri.purchase_invoice_id = ? AND pri.tenant_id = ?
        ");
    }

    $stmt->execute([$id, $tenant_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $items]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
