<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
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

    // Snapshot old header + items for the Activity Log (before mutation)
    $stOld = $pdo->prepare("SELECT chalan_no, sale_invoice_id, delivery_date, customer_id, company_id, branch_id, currency_id, project_id, sale_officer_id, supplier_man_id, bilty_no, transport_name, remarks, status FROM delivery_chalan WHERE id = ? AND tenant_id = ?");
    $stOld->execute([$id, $tenant_id]);
    $oldChalan = $stOld->fetch(PDO::FETCH_ASSOC);
    if (!$oldChalan) throw new Exception('Delivery Chalan not found');

    $stOldItems = $pdo->prepare("SELECT product_id, uom_id, quantity FROM delivery_chalan_items WHERE delivery_chalan_id = ? AND tenant_id = ? ORDER BY id");
    $stOldItems->execute([$id, $tenant_id]);
    $oldItems = $stOldItems->fetchAll(PDO::FETCH_ASSOC);

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

    $newChalan = [
        'chalan_no' => $oldChalan['chalan_no'],
        'sale_invoice_id' => $input['saleInvoiceId'] ?? null,
        'delivery_date' => $input['deliveryDate'],
        'customer_id' => $input['customerId'],
        'company_id' => $input['companyId'] ?? null,
        'branch_id' => $input['branchId'],
        'currency_id' => $input['currencyId'] ?? null,
        'project_id' => $input['projectId'] ?? null,
        'sale_officer_id' => $input['salesOfficerId'] ?? null,
        'supplier_man_id' => $input['supplierManId'] ?? null,
        'bilty_no' => $input['biltyNo'] ?? null,
        'transport_name' => $input['transportName'] ?? null,
        'remarks' => $input['remarks'] ?? null,
        'status' => $input['status'] ?? 'Posted',
    ];
    $changeDescriptions = buildChangeDescription($oldChalan, $newChalan, [
        'sale_invoice_id' => 'Sale Invoice', 'delivery_date' => 'Delivery Date', 'customer_id' => 'Customer',
        'company_id' => 'Company', 'branch_id' => 'Branch', 'currency_id' => 'Currency', 'project_id' => 'Project',
        'sale_officer_id' => 'Sales Officer', 'supplier_man_id' => 'Supplier Man', 'bilty_no' => 'Bilty No',
        'transport_name' => 'Transport Name', 'remarks' => 'Remarks', 'status' => 'Status'
    ]);
    $newItemsForCompare = array_values(array_filter(array_map(function($it) {
        if (empty($it['productId'])) return null;
        return ['product_id' => $it['productId'], 'uom_id' => $it['uomId'], 'quantity' => (float)$it['quantity']];
    }, $input['items'])));
    if (itemsChanged($oldItems, $newItemsForCompare, ['product_id', 'uom_id', 'quantity'])) {
        $changeDescriptions[] = 'Item lines updated';
    }
    $editDescription = !empty($changeDescriptions)
        ? "Updated delivery chalan \"{$oldChalan['chalan_no']}\": " . implode('; ', $changeDescriptions)
        : "Updated delivery chalan \"{$oldChalan['chalan_no']}\" - no tracked field changes";
    logActivity($pdo, $tenant_id, $user_id, 'update', 'Delivery Chalan', $id, $oldChalan['chalan_no'], $editDescription, $oldChalan, $newChalan);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Delivery Chalan updated successfully', 'chalan_id' => $id]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
