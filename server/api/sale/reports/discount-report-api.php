<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Handle filter data request
if (isset($_GET['type']) && $_GET['type'] === 'filters') {
    try {
        $customers = $pdo->query("SELECT id, customer_code, customer_name FROM customers WHERE tenant_id = '$tenant_id' ORDER BY customer_name")->fetchAll(PDO::FETCH_ASSOC);
        $products = $pdo->query("SELECT id, code, name FROM products WHERE tenant_id = '$tenant_id' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $branches = $pdo->query("SELECT id, branch_name FROM branches WHERE tenant_id = '$tenant_id' ORDER BY branch_name")->fetchAll(PDO::FETCH_ASSOC);
        // Salesman filter is backed by employees (linked via sale_invoice.sale_officer_id)
        $salesmen = $pdo->query("SELECT id, full_name FROM employees WHERE tenant_id = '$tenant_id' AND is_active = 1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        // Supplier filter is backed by suppliers (linked via products.vendor_id)
        $suppliers = $pdo->query("SELECT id, supplier_name FROM suppliers WHERE tenant_id = '$tenant_id' AND status = 'ACTIVE' ORDER BY supplier_name")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'filters' => [
                'customers' => $customers,
                'products' => $products,
                'branches' => $branches,
                'salesmen' => $salesmen,
                'suppliers' => $suppliers
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

try {
    // Get filter parameters
    $startDate = $_GET['startDate'] ?? null;
    $endDate = $_GET['endDate'] ?? null;
    $customerId = $_GET['customerId'] ?? null;
    $productId = $_GET['productId'] ?? null;
    $branchId = $_GET['branchId'] ?? null;
    $salesmanId = $_GET['salesmanId'] ?? null;
    $supplierId = $_GET['supplierId'] ?? null;
    $minDiscount = isset($_GET['minDiscount']) ? floatval($_GET['minDiscount']) : null;
    $maxDiscount = isset($_GET['maxDiscount']) ? floatval($_GET['maxDiscount']) : null;

    // Build query - JOIN with ledgerone_public.currencies
    $query = "
        SELECT 
            si.id as invoice_id,
            si.bill_no,
            si.sale_date,
            c.customer_code,
            c.customer_name,
            p.code as product_code,
            p.name as product_name,
            COALESCE(e.full_name, 'Unassigned') as salesman_name,
            s.supplier_name,
            sii.quantity,
            sii.sale_price,
            sii.gross_amount,
            sii.discount_percent,
            sii.discount_amount,
            sii.extra_discount_amount,
            sii.tax_percent,
            sii.tax_amount,
            sii.net_amount,
            cur.code as currency_code,
            cur.symbol as currency_symbol,
            b.branch_name,
            COALESCE(
                ROUND(
                    (sii.discount_amount / 
                    NULLIF(sii.gross_amount, 0)) * 100, 3
                ), 0
            ) as calculated_disc_percent,
            COALESCE(
                ROUND(
                    (sii.extra_discount_amount / 
                    NULLIF((sii.gross_amount - sii.discount_amount), 0)) * 100, 3
                ), 0
            ) as calculated_extra_disc_percent,
            COALESCE(sii.discount_percent, 0) as item_discount_percent
        FROM sale_invoice si
        JOIN customers c ON si.customer_id = c.id
        JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
        JOIN products p ON sii.product_id = p.id
        JOIN ledgerone_public.currencies cur ON si.currency_id = cur.id
        JOIN branches b ON si.branch_id = b.id
        LEFT JOIN employees e ON si.sale_officer_id = e.id AND e.tenant_id = si.tenant_id
        LEFT JOIN suppliers s ON p.vendor_id = s.id AND s.tenant_id = si.tenant_id
        WHERE si.tenant_id = ?
        AND si.status = 'Posted'
    ";

    $params = [$tenant_id];

    // Add filters
    if ($startDate) {
        $query .= " AND si.sale_date >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $query .= " AND si.sale_date <= ?";
        $params[] = $endDate;
    }
    if ($customerId) {
        $query .= " AND si.customer_id = ?";
        $params[] = $customerId;
    }
    if ($productId) {
        $query .= " AND sii.product_id = ?";
        $params[] = $productId;
    }
    if ($branchId) {
        $query .= " AND si.branch_id = ?";
        $params[] = $branchId;
    }
    if ($salesmanId) {
        $query .= " AND si.sale_officer_id = ?";
        $params[] = $salesmanId;
    }
    if ($supplierId) {
        $query .= " AND p.vendor_id = ?";
        $params[] = $supplierId;
    }

    $query .= " ORDER BY si.sale_date DESC, si.bill_no DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Apply discount filters on fetched data
    $filtered = array_filter($data, function($row) use ($minDiscount, $maxDiscount) {
        $totalDisc = floatval($row['calculated_disc_percent'] ?? 0);
        
        if ($minDiscount !== null && $totalDisc < $minDiscount) {
            return false;
        }
        if ($maxDiscount !== null && $totalDisc > $maxDiscount) {
            return false;
        }
        return true;
    });

    // Calculate totals
    $totals = [
        'gross_amount' => 0,
        'discount_amount' => 0,
        'extra_discount_amount' => 0,
        'tax_amount' => 0,
        'net_amount' => 0,
        'count' => count($filtered)
    ];

    foreach ($filtered as $row) {
        $totals['gross_amount'] += floatval($row['gross_amount']);
        $totals['discount_amount'] += floatval($row['discount_amount']);
        $totals['extra_discount_amount'] += floatval($row['extra_discount_amount']);
        $totals['tax_amount'] += floatval($row['tax_amount']);
        $totals['net_amount'] += floatval($row['net_amount']);
    }

    echo json_encode([
        'success' => true,
        'data' => array_values($filtered),
        'totals' => $totals
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>