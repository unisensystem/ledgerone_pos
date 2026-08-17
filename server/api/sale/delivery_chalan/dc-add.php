<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }

session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Invalid JSON');

    if (empty($input['customerId'])) throw new Exception('Customer is required');
    if (empty($input['branchId']))   throw new Exception('Branch is required');
    if (empty($input['deliveryDate'])) throw new Exception('Delivery Date is required');
    if (empty($input['items']))      throw new Exception('At least one item is required');

    $pdo->beginTransaction();

    // Generate chalan number
    $lastStmt = $pdo->prepare("SELECT chalan_no FROM delivery_chalan WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $lastStmt->execute([$tenant_id]);
    $lastNo = $lastStmt->fetchColumn();
    $newNum = $lastNo ? ((int)substr($lastNo, 3)) + 1 : 1;
    $chalanNo = 'DC-' . str_pad($newNum, 4, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("
        INSERT INTO delivery_chalan (
            tenant_id, chalan_no, sale_invoice_id, delivery_date,
            customer_id, company_id, branch_id, currency_id, project_id,
            sale_officer_id, supplier_man_id, bilty_no, transport_name,
            remarks, status, created_by, updated_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $tenant_id,
        $chalanNo,
        $input['saleInvoiceId'] ?? null,
        $input['deliveryDate'],
        $input['customerId'],
        $input['companyId'] ?? null,
        $input['branchId'],
        $input['currencyId'] ?? null,
        $input['projectId'] ?? null,
        $input['salesOfficerId'] ?? null,
        $input['supplierManId'] ?? null,
        $input['biltyNo'] ?? null,
        $input['transportName'] ?? null,
        $input['remarks'] ?? null,
        $input['status'] ?? 'Posted',
        $user_id,
        $user_id
    ]);
    $chalanId = $pdo->lastInsertId();

    $itemStmt = $pdo->prepare("
        INSERT INTO delivery_chalan_items (
            tenant_id, delivery_chalan_id, product_id, uom_id, quantity, created_by, updated_by
        ) VALUES (?,?,?,?,?,?,?)
    ");

    foreach ($input['items'] as $item) {
        $itemStmt->execute([
            $tenant_id,
            $chalanId,
            $item['productId'],
            $item['uomId'],
            $item['quantity'],
            $user_id,
            $user_id
        ]);
    }

    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Delivery Chalan saved successfully','chalan_id'=>$chalanId,'chalan_no'=>$chalanNo]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
