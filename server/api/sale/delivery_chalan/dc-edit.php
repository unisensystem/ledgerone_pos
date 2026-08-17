<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Invalid JSON');

    $id = $input['chalan_id'] ?? null;
    if (!$id) throw new Exception('Chalan ID is required');

    if (empty($input['customerId']))   throw new Exception('Customer is required');
    if (empty($input['branchId']))     throw new Exception('Branch is required');
    if (empty($input['deliveryDate'])) throw new Exception('Delivery Date is required');
    if (empty($input['items']))        throw new Exception('At least one item is required');

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE delivery_chalan SET
            sale_invoice_id = ?,
            delivery_date   = ?,
            customer_id     = ?,
            company_id      = ?,
            branch_id       = ?,
            currency_id     = ?,
            project_id      = ?,
            sale_officer_id = ?,
            supplier_man_id = ?,
            bilty_no        = ?,
            transport_name  = ?,
            remarks         = ?,
            status          = ?,
            updated_by      = ?
        WHERE id = ? AND tenant_id = ?
    ")->execute([
        $input['saleInvoiceId']  ?? null,
        $input['deliveryDate'],
        $input['customerId'],
        $input['companyId']      ?? null,
        $input['branchId'],
        $input['currencyId']     ?? null,
        $input['projectId']      ?? null,
        $input['salesOfficerId'] ?? null,
        $input['supplierManId']  ?? null,
        $input['biltyNo']        ?? null,
        $input['transportName']  ?? null,
        $input['remarks']        ?? null,
        $input['status']         ?? 'Posted',
        $user_id,
        $id,
        $tenant_id
    ]);

    // Delete old items and re-insert
    $pdo->prepare("DELETE FROM delivery_chalan_items WHERE delivery_chalan_id = ? AND tenant_id = ?")->execute([$id, $tenant_id]);

    $itemStmt = $pdo->prepare("
        INSERT INTO delivery_chalan_items
            (tenant_id, delivery_chalan_id, product_id, uom_id, quantity, created_by, updated_by)
        VALUES (?,?,?,?,?,?,?)
    ");
    foreach ($input['items'] as $item) {
        if (empty($item['productId'])) continue;
        $itemStmt->execute([
            $tenant_id, $id,
            $item['productId'],
            $item['uomId'],
            $item['quantity'],
            $user_id, $user_id
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Delivery Chalan updated successfully', 'chalan_id' => $id]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
