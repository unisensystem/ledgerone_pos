<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT dc.*,
                   c.customer_name, c.customer_code,
                   co.company_name,
                   b.branch_name, b.branch_code, b.branch_type,
                   pb.branch_name  AS parent_branch_name,
                   cur.symbol      AS currency_symbol,
                   cur.code        AS currency_code,
                   cur.name        AS currency_name,
                   e.id            AS sales_officer_id,
                   e.full_name     AS sales_officer_name,
                   sm.id           AS supplier_man_id,
                   sm.full_name    AS supplier_man_name,
                   si.bill_no      AS sale_invoice_no
            FROM delivery_chalan dc
            LEFT JOIN customers  c   ON dc.customer_id     = c.id
            LEFT JOIN companies  co  ON dc.company_id      = co.id
            LEFT JOIN branches   b   ON dc.branch_id       = b.id
            LEFT JOIN branches   pb  ON b.parent_branch_id = pb.id
            LEFT JOIN ledgerone_public.currencies cur ON dc.currency_id = cur.id
            LEFT JOIN employees  e   ON dc.sale_officer_id = e.id
            LEFT JOIN employees  sm  ON dc.supplier_man_id = sm.id
            LEFT JOIN sale_invoice si ON dc.sale_invoice_id = si.id
            WHERE dc.id = ? AND dc.tenant_id = ?
        ");
        $stmt->execute([$id, $tenant_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Delivery Chalan not found']);
            exit;
        }

        // Map fields so JS (loadInvoiceData) can populate them
        $invoice['sale_date']  = $invoice['delivery_date'];
        $invoice['bill_no']    = $invoice['chalan_no'];

        $itemStmt = $pdo->prepare("
            SELECT dci.*,
                   p.name AS product_name,
                   p.code AS product_code,
                   1      AS stock_affects,
                   1      AS invoice_affects,
                   u.uom_name,
                   sc.subcategory_name,
                   0            AS sale_price,
                   0            AS gross_amount,
                   0            AS discount_percent,
                   0            AS discount_amount,
                   0            AS trade_offer_amount,
                   0            AS tax_percent,
                   0            AS tax_amount,
                   0            AS foc_quantity,
                   0            AS net_amount,
                   NULL         AS parent_row_id,
                   'sale_on_tp' AS scheme
            FROM delivery_chalan_items dci
            LEFT JOIN products      p  ON dci.product_id    = p.id
            LEFT JOIN uom           u  ON dci.uom_id        = u.id
            LEFT JOIN subcategories sc ON p.subcategory_id  = sc.id
            WHERE dci.delivery_chalan_id = ? AND dci.tenant_id = ?
            ORDER BY dci.id
        ");
        $itemStmt->execute([$id, $tenant_id]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'invoice' => $invoice, 'items' => $items]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── PUT ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    $id = $input['invoice_id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $deliveryDate = $input['deliveryDate'] ?? $input['saleDate'] ?? date('Y-m-d');

        $pdo->prepare("
            UPDATE delivery_chalan SET
                sale_invoice_id = ?,
                delivery_date   = ?,
                customer_id     = ?,
                company_id      = ?,
                branch_id       = ?,
                currency_id     = ?,
                sale_officer_id = ?,
                supplier_man_id = ?,
                bilty_no        = ?,
                transport_name  = ?,
                remarks         = ?,
                status          = ?,
                updated_by      = ?
            WHERE id = ? AND tenant_id = ?
        ")->execute([
            $input['saleInvoiceId']  ?? $input['saleOrderId'] ?? null,
            $deliveryDate,
            $input['customerId'],
            $input['companyId']      ?? null,
            $input['branchId'],
            $input['currencyId']     ?? null,
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
        echo json_encode(['success' => true, 'message' => 'Delivery Chalan updated successfully', 'invoice_id' => $id]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
