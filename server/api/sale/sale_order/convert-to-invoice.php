<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? null;

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get sale order data
    $orderStmt = $pdo->prepare("SELECT * FROM sale_order WHERE id = ? AND tenant_id = ?");
    $orderStmt->execute([$order_id, $tenant_id]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Sale order not found');
    }

    // Generate invoice bill number
    $billStmt = $pdo->prepare("SELECT bill_no FROM sale_invoice WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $billStmt->execute([$tenant_id]);
    $lastBill = $billStmt->fetchColumn();

    if ($lastBill) {
        $lastNumber = (int) substr($lastBill, 4);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    $billNo = 'SAL-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

    // Insert into sale_invoice
    $invoiceStmt = $pdo->prepare("
        INSERT INTO sale_invoice (
            tenant_id, company_id, currency_id, bill_no, sale_date, customer_id, branch_id,
            previous_balance, sale_officer_id, bilty_no, transport_name, total_bill,
            total_discount_percent, total_discount_amount, total_gst_percent, total_gst_amount,
            shipping_fees, net_amount, remarks, status, sale_order_id, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Posted', ?, ?, ?)
    ");

    $invoiceStmt->execute([
        $tenant_id,
        $order['company_id'],
        $order['currency_id'],
        $billNo,
        $order['sale_date'],
        $order['customer_id'],
        $order['branch_id'],
        $order['previous_balance'],
        $order['sale_officer_id'],
        $order['bilty_no'],
        $order['transport_name'],
        $order['total_bill'],
        $order['total_discount_percent'],
        $order['total_discount_amount'],
        0.00,
        0.00,
        0.00,
        $order['net_amount'],
        $order['remarks'],
        $order_id,
        $user_id,
        $user_id
    ]);

    $invoice_id = $pdo->lastInsertId();

    // Get sale order items with product stock_affects flag
    $itemsStmt = $pdo->prepare("
        SELECT soi.*, p.stock_affects, p.invoice_affects, p.purchase_price 
        FROM sale_order_items soi
        LEFT JOIN products p ON soi.product_id = p.id
        WHERE soi.sale_invoice_id = ? AND soi.tenant_id = ?
    ");
    $itemsStmt->execute([$order_id, $tenant_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Insert into sale_invoice_items
    $itemInsertStmt = $pdo->prepare("
        INSERT INTO sale_invoice_items (
            tenant_id, sale_invoice_id, product_id, uom_id, quantity, sale_price,
            gross_amount, discount_percent, discount_amount, trade_offer_percent,
            trade_offer_amount, gst_percent, gst_amount, foc_quantity, net_amount,
            parent_row_id, piece, carton, dozen, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Prepare stock ledger statement
    $stockStmt = $pdo->prepare("
        INSERT INTO stock_ledger (
            tenant_id, product_id, branch_id, transaction_type, reference_table, reference_id,
            qty_out, unit_cost, transaction_date, unit_id, account_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Prepare accounting ledger statement
    $accountingStmt = $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id, account_id,
            date, description, debit, credit
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $itemInsertStmt->execute([
            $tenant_id,
            $invoice_id,
            $item['product_id'],
            $item['uom_id'],
            $item['quantity'],
            $item['sale_price'],
            $item['gross_amount'],
            $item['discount_percent'],
            $item['discount_amount'],
            $item['trade_offer_percent'],
            $item['trade_offer_amount'],
            $item['gst_percent'],
            $item['gst_amount'],
            $item['foc_quantity'],
            $item['net_amount'],
            $item['parent_row_id'],
            $item['piece'],
            $item['carton'],
            $item['dozen'],
            $user_id,
            $user_id
        ]);

        // Insert stock ledger entry (only for items that affect stock)
        if ($item['stock_affects']) {
            // Get weighted average cost from stock ledger
            $avgCostStmt = $pdo->prepare("
                SELECT 
                    CASE 
                        WHEN SUM(qty_in - qty_out) > 0 
                        THEN SUM((qty_in - qty_out) * unit_cost) / SUM(qty_in - qty_out)
                        ELSE 0
                    END as avg_cost
                FROM stock_ledger
                WHERE tenant_id = ? AND product_id = ? AND branch_id = ?
            ");
            $avgCostStmt->execute([$tenant_id, $item['product_id'], $order['branch_id']]);
            $avgCost = $avgCostStmt->fetchColumn() ?: $item['purchase_price'];
            
            $stockStmt->execute([
                $tenant_id,
                $item['product_id'],
                $order['branch_id'],
                'sale_invoice',
                'sale_invoice',
                $invoice_id,
                $item['quantity'],
                $avgCost,
                $order['sale_date'],
                $item['uom_id'],
                33
            ]);
        }

        // Debit: Accounts Receivable
        $accountingStmt->execute([
            $tenant_id,
            'sale_invoice',
            'sale_invoice',
            $invoice_id,
            33, // Accounts Receivable
            $order['sale_date'],
            "Sale Invoice {$billNo} - {$item['product_id']}",
            $item['net_amount'],
            0
        ]);

        // Credit: Sales Revenue
        $accountingStmt->execute([
            $tenant_id,
            'sale_invoice',
            'sale_invoice',
            $invoice_id,
            33, // Sales Revenue
            $order['sale_date'],
            "Sale Invoice {$billNo} - {$item['product_id']}",
            0,
            $item['net_amount']
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sale order converted to invoice successfully',
        'invoice_id' => $invoice_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
