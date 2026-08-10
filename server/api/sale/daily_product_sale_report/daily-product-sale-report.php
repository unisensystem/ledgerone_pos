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

            $sql = "SELECT
                        si.id as invoice_id,
                        si.bill_no,
                        si.sale_date,
                        c.customer_name,
                        COALESCE(b.branch_name, 'No Branch') as branch_name,
                        p.code as product_code,
                        p.name as product_name,
                        sii.quantity,
                        COALESCE(u.uom_name, '') as unit_symbol,
                        sii.sale_price,
                        sii.discount_amount,
                        sii.net_amount
                    FROM sale_invoice_items sii
                    JOIN sale_invoice si ON sii.sale_invoice_id = si.id AND sii.tenant_id = si.tenant_id
                    JOIN products p ON sii.product_id = p.id AND sii.tenant_id = p.tenant_id
                    JOIN customers c ON si.customer_id = c.id
                    LEFT JOIN branches b ON si.branch_id = b.id
                    LEFT JOIN uom u ON sii.uom_id = u.id
                    WHERE sii.tenant_id = ?
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
                $sql .= " AND p.id = ?";
                $params[] = $product_id;
            }

            $sql .= " ORDER BY si.sale_date ASC, si.bill_no ASC, sii.id ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_qty = 0;
            $total_amount = 0;
            foreach ($rows as &$row) {
                $row['quantity'] = (float)$row['quantity'];
                $row['sale_price'] = (float)$row['sale_price'];
                $row['net_amount'] = (float)$row['net_amount'];
                $total_qty += $row['quantity'];
                $total_amount += $row['net_amount'];
            }

            echo json_encode([
                'success' => true,
                'data' => $rows,
                'currency' => $currency_symbol,
                'summary' => [
                    'total_rows' => count($rows),
                    'total_qty' => $total_qty,
                    'total_amount' => $total_amount
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