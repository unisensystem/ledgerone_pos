<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['products']) || !is_array($input['products'])) {
        echo json_encode(['success' => false, 'message' => 'No product data provided']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE products SET
            purchase_price  = ?,
            trade_price     = ?,
            wholesale_price = ?,
            mrp             = ?,
            updated_at      = CURRENT_TIMESTAMP
        WHERE id = ? AND tenant_id = ?
    ");

    $updated = 0;
    foreach ($input['products'] as $row) {
        $id = intval($row['id'] ?? 0);
        if (!$id) continue;

        $stmt->execute([
            $row['purchase_price']  ?? 0,
            $row['trade_price']     ?? 0,
            $row['wholesale_price'] ?? 0,
            $row['mrp']             ?? 0,
            $id,
            $tenant_id
        ]);
        $updated++;
    }

    echo json_encode(['success' => true, 'message' => "$updated product(s) updated successfully"]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
