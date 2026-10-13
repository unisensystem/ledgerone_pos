<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$product_id = $_GET['product_id'] ?? null;
$company_id = $_GET['company_id'] ?? null;

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'product_id is required']);
    exit;
}

try {
    // Product-wise only — supplier/customer ka koi filter nahi.
    // parent_row_id IS NULL => variant/child rows skip (unki price 0 hoti hai)
    $sql = "
        SELECT
            pi.purchase_date,
            pii.purchase_price,
            pii.quantity,
            s.supplier_name,
            COALESCE(c.symbol, '') AS currency_symbol
        FROM purchase_invoice_items pii
        JOIN purchase_invoice pi ON pi.id = pii.purchase_invoice_id
        LEFT JOIN suppliers  s ON s.id = pi.supplier_id
LEFT JOIN ledgerone_public.currencies c ON c.id = pi.currency_id
        WHERE pii.tenant_id = ?
          AND pii.product_id = ?
          AND pii.parent_row_id IS NULL
          AND pii.purchase_price > 0
    ";
    $params = [$tenant_id, $product_id];

    if ($company_id) {
        $sql .= " AND pi.company_id = ? ";
        $params[] = $company_id;
    }

    $sql .= " ORDER BY pi.purchase_date DESC, pi.id DESC LIMIT 3";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'history' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}