<?php
// Path convention: server/api/sales/daily_product_sale_report/daily-product-sale-report.php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

try {
    // Base currency symbol (same pattern as stock-position.php)
    $currency_sql = "SELECT c.symbol FROM tenant_currencies tc 
                     JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
                     WHERE tc.tenant_id = ? AND tc.is_base_currency = 1";
    $currency_stmt = $pdo->prepare($currency_sql);
    $currency_stmt->execute([$tenant_id]);
    $currency = $currency_stmt->fetch(PDO::FETCH_ASSOC);
    $currency_symbol = $currency['symbol'] ?? '$';

    $action = $_GET['action'] ?? 'report';

    switch ($action) {

        case 'report':
            // No filter selected by default = show ALL sales (all dates, all customers/branches/products)
            $from_date = $_GET['from_date'] ?? null;   // null = no lower date bound
            $to_date = $_GET['to_date'] ?? null;       // null = no upper date bound
            $customer_id = $_GET['customer_id'] ?? null;   // null/empty = ALL customers
            $branch_id = $_GET['branch_id'] ?? null;       // null/empty = ALL branches
            $product_id = $_GET['product_id'] ?? null;     // null/empty = ALL products

            // --- Pass 1: one row per sale (invoice header), like a sale register ---
            $sql = "SELECT
                        si.id AS invoice_id,
                        si.bill_no,
                        si.sale_date,
                        si.bilty_no,
                        si.invoice_type,
                        si.net_amount,
                        c.customer_name,
                        c.primary_phone,
                        c.strn,
                        COALESCE(b.branch_name, 'No Branch') AS branch_name,
                        COALESCE((
                            SELECT SUM(rv.amount) FROM receive_voucher rv
                            WHERE rv.tenant_id = si.tenant_id AND rv.bill_no = si.bill_no
                        ), 0) AS paid_amount,
                        COALESCE((
                            SELECT SUM(sit.tax_amount) FROM sale_invoice_taxes sit
                            WHERE sit.tenant_id = si.tenant_id AND sit.sale_invoice_id = si.id
                        ), 0) AS invoice_level_tax
                    FROM sale_invoice si
                    JOIN customers c ON si.customer_id = c.id
                    LEFT JOIN branches b ON si.branch_id = b.id
                    WHERE si.tenant_id = ?
                      AND si.status = 'Posted'";

            $params = [$tenant_id];

            if ($from_date) {
                $sql .= " AND si.sale_date >= ?";
                $params[] = $from_date;
            }
            if ($to_date) {
                $sql .= " AND si.sale_date <= ?";
                $params[] = $to_date;
            }
            if ($customer_id) {
                $sql .= " AND si.customer_id = ?";
                $params[] = $customer_id;
            }
            if ($branch_id) {
                $sql .= " AND si.branch_id = ?";
                $params[] = $branch_id;
            }
            if ($product_id) {
                $sql .= " AND EXISTS (SELECT 1 FROM sale_invoice_items sii WHERE sii.sale_invoice_id = si.id AND sii.tenant_id = si.tenant_id AND sii.product_id = ?)";
                $params[] = $product_id;
            }

            $sql .= " ORDER BY si.sale_date DESC, si.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $invoiceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $invoices = [];
            $invoiceIds = [];
            foreach ($invoiceRows as $row) {
                $netAmount = (float)$row['net_amount'];
                $paidAmount = (float)$row['paid_amount'];
                $balance = round($netAmount - $paidAmount, 2);

                if ($balance <= 0.001) {
                    $status = 'Paid';
                } elseif ($paidAmount > 0) {
                    $status = 'Partial';
                } else {
                    $status = 'Unpaid';
                }

                $invoices[$row['invoice_id']] = [
                    'invoice_id' => (int)$row['invoice_id'],
                    'bill_no' => $row['bill_no'],
                    'sale_date' => $row['sale_date'],
                    'ref_no' => $row['bilty_no'],
                    'customer_name' => $row['customer_name'],
                    'phone' => $row['primary_phone'],
                    'tax_no' => $row['strn'],
                    'branch_name' => $row['branch_name'],
                    'payment_type' => $row['invoice_type'],
                    'status' => $status,
                    'total_amount' => $netAmount,
                    'paid_amount' => $paidAmount,
                    'balance_amount' => $balance,
                    'invoice_level_tax' => (float)$row['invoice_level_tax'],
                    'items' => []
                ];
                $invoiceIds[] = (int)$row['invoice_id'];
            }

            // --- Pass 2: items for every matched invoice, nested under it ---
            $totalQty = 0;
            $totalAmount = 0;

            if (!empty($invoiceIds)) {
                $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
                $itemSql = "SELECT
                        sii.sale_invoice_id,
                        p.code AS product_code,
                        p.name AS product_name,
                        sii.batch_no,
                        sii.quantity,
                        COALESCE(u.uom_name, '') AS unit_symbol,
                        sii.sale_price,
                        sii.discount_percent,
                        sii.gross_amount,
                        sii.discount_amount,
                        sii.trade_offer_amount,
                        sii.tax_amount
                    FROM sale_invoice_items sii
                    JOIN products p ON sii.product_id = p.id AND sii.tenant_id = p.tenant_id
                    LEFT JOIN uom u ON sii.uom_id = u.id
                    WHERE sii.tenant_id = ? AND sii.sale_invoice_id IN ($placeholders)
                    ORDER BY sii.sale_invoice_id ASC, sii.id ASC";

                $itemStmt = $pdo->prepare($itemSql);
                $itemStmt->execute(array_merge([$tenant_id], $invoiceIds));
                $itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($itemRows as $item) {
                    $invId = $item['sale_invoice_id'];
                    if (!isset($invoices[$invId])) continue;

                    $qty = (float)$item['quantity'];
                    // Amount = gross less discounts, before tax (tax is shown once per invoice, like the target report)
                    $amount = (float)$item['gross_amount'] - (float)$item['discount_amount'] - (float)$item['trade_offer_amount'];

                    $invoices[$invId]['items'][] = [
                        'product_code' => $item['product_code'],
                        'product_name' => $item['product_name'],
                        'batch_no' => $item['batch_no'],
                        'quantity' => $qty,
                        'unit_symbol' => $item['unit_symbol'],
                        'sale_price' => (float)$item['sale_price'],
                        'discount_percent' => (float)$item['discount_percent'],
                        'amount' => $amount,
                        'tax_amount' => (float)$item['tax_amount']
                    ];

                    $invoices[$invId]['item_tax_total'] = ($invoices[$invId]['item_tax_total'] ?? 0) + (float)$item['tax_amount'];
                    $invoices[$invId]['sub_total'] = ($invoices[$invId]['sub_total'] ?? 0) + $amount;

                    $totalQty += $qty;
                }
            }

            // Finalize per-invoice Sub Total / Tax / Round off
            foreach ($invoices as &$inv) {
                $subTotal = round($inv['sub_total'] ?? 0, 2);
                $tax = round(($inv['item_tax_total'] ?? 0) + $inv['invoice_level_tax'], 2);
                $inv['sub_total'] = $subTotal;
                $inv['tax'] = $tax;
                $inv['round_off'] = round($inv['total_amount'] - ($subTotal + $tax), 2);
                unset($inv['item_tax_total'], $inv['invoice_level_tax']);
                $totalAmount += $inv['total_amount'];
            }
            unset($inv);

            echo json_encode([
                'success' => true,
                'data' => array_values($invoices),
                'currency' => $currency_symbol,
                'summary' => [
                    'total_invoices' => count($invoices),
                    'total_qty' => $totalQty,
                    'total_amount' => $totalAmount
                ]
            ]);
            break;

        case 'customers':
            $sql = "SELECT id, customer_name FROM customers WHERE tenant_id = ? AND status = 'ACTIVE' ORDER BY customer_name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'branches':
            $sql = "SELECT b.id, b.branch_name
                    FROM user_branches ub
                    JOIN branches b ON ub.branch_id = b.id
                    WHERE ub.tenant_id = ? AND ub.user_id = ? AND b.is_active = 1
                    ORDER BY b.branch_name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id, $user_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'products':
            $sql = "SELECT id, code, name FROM products WHERE tenant_id = ? AND is_active = 1 AND product_type = 'physical' ORDER BY name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log('Daily Sale Report API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}