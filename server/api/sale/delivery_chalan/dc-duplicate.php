<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $source_id         = (int)($input['chalan_id']     ?? 0);
    $override_date     = !empty($input['delivery_date']) ? $input['delivery_date'] : null;
    $override_customer = !empty($input['customer_id'])   ? (int)$input['customer_id'] : null;

    if (!$source_id) throw new Exception('chalan_id is required');

    // Fetch source chalan
    $stmt = $pdo->prepare("SELECT * FROM delivery_chalan WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$source_id, $tenant_id]);
    $chalan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$chalan) throw new Exception('Source chalan not found');

    // Fetch source items
    $itemsStmt = $pdo->prepare("SELECT * FROM delivery_chalan_items WHERE delivery_chalan_id = ? AND tenant_id = ? ORDER BY id");
    $itemsStmt->execute([$source_id, $tenant_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $final_date     = $override_date     ?? date('Y-m-d');
    $final_customer = $override_customer ?? $chalan['customer_id'];

    $pdo->beginTransaction();

    // Generate chalan_no inside transaction with lock
    $lastStmt = $pdo->prepare("SELECT chalan_no FROM delivery_chalan WHERE tenant_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $lastStmt->execute([$tenant_id]);
    $last = $lastStmt->fetchColumn();
    $newNum   = $last ? ((int)substr($last, 3)) + 1 : 1;
    $newChalanNo = 'DC-' . str_pad($newNum, 4, '0', STR_PAD_LEFT);

    // Insert duplicate chalan — sale_invoice_id intentionally null (new chalan is independent)
    $ins = $pdo->prepare("
        INSERT INTO delivery_chalan (
            tenant_id, chalan_no, sale_invoice_id, delivery_date,
            customer_id, company_id, branch_id, currency_id, project_id,
            sale_officer_id, supplier_man_id, bilty_no, transport_name,
            remarks, status, created_by, updated_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $ins->execute([
        $tenant_id,
        $newChalanNo,
        null,
        $final_date,
        $final_customer,
        $chalan['company_id'],
        $chalan['branch_id'],
        $chalan['currency_id'],
        $chalan['project_id'],
        $chalan['sale_officer_id'],
        $chalan['supplier_man_id'],
        $chalan['bilty_no'],
        $chalan['transport_name'],
        $chalan['remarks'],
        $chalan['status'] ?? 'Posted',
        $user_id,
        $user_id
    ]);
    $newChalanId = $pdo->lastInsertId();

    // Insert duplicate items
    $itemIns = $pdo->prepare("
        INSERT INTO delivery_chalan_items (
            tenant_id, delivery_chalan_id, product_id, uom_id, quantity, created_by, updated_by
        ) VALUES (?,?,?,?,?,?,?)
    ");
    foreach ($items as $item) {
        $itemIns->execute([
            $tenant_id,
            $newChalanId,
            $item['product_id'],
            $item['uom_id'],
            $item['quantity'],
            $user_id,
            $user_id
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success'   => true,
        'message'   => 'Chalan duplicated successfully',
        'chalan_id' => $newChalanId,
        'chalan_no' => $newChalanNo
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
