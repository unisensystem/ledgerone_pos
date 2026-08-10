<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$branch_id = $_GET['branch_id'] ?? null;
$company_id = !empty($_GET['company_id']) ? $_GET['company_id'] : null;
$customer_id = !empty($_GET['customer_id']) ? $_GET['customer_id'] : null;
$product_id = !empty($_GET['product_id']) ? $_GET['product_id'] : null;
$invoice_id = !empty($_GET['invoice_id']) ? $_GET['invoice_id'] : null;

if (!$start_date || !$end_date) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Start date and end date are required']);
    exit;
}

try {
    // Build branch filter
    $branch_condition = '';
    $branch_params = [];
    if ($branch_id && $branch_id !== 'all') {
        $branch_condition = ' AND (b.id = ? OR b.parent_branch_id = ?)';
        $branch_params = [$branch_id, $branch_id];
    }

    // ==================== ITEM-WISE PROFIT & LOSS ====================
    
    // Get sales data from sale_invoice_items
    $sql = "
        SELECT 
            p.id as product_id,
            p.name as product_name,
            SUM(sii.quantity) as qty_sold,
            SUM(sii.net_amount) as revenue
        FROM sale_invoice si
        JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
        JOIN products p ON sii.product_id = p.id
        JOIN branches b ON si.branch_id = b.id
        WHERE si.tenant_id = ?
        AND si.sale_date BETWEEN ? AND ?
        AND p.product_type IN ('physical', 'service')
        $branch_condition
        " . ($company_id ? " AND si.company_id = ?" : "") . "
        " . ($customer_id ? " AND si.customer_id = ?" : "") . "
        " . ($product_id ? " AND p.id = ?" : "") . "
        " . ($invoice_id ? " AND si.id = ?" : "") . "
        GROUP BY p.id, p.name
        ORDER BY revenue DESC
    ";
    
    $params = array_merge([$tenant_id, $start_date, $end_date], $branch_params);
    if ($company_id) $params[] = $company_id;
    if ($customer_id) $params[] = $customer_id;
    if ($product_id) $params[] = $product_id;
    if ($invoice_id) $params[] = $invoice_id;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get sales from station_daily_usage (fuel products)
    $sql_fuel = "
        SELECT 
            p.id as product_id,
            p.name as product_name,
            SUM(sdu.total_dispensed) as qty_sold,
            SUM(sdu.total_revenue) as revenue
        FROM station_daily_usage sdu
        JOIN products p ON sdu.product_id = p.id
        JOIN branches b ON sdu.branch_id = b.id
        WHERE sdu.tenant_id = ?
        AND sdu.usage_date BETWEEN ? AND ?
        $branch_condition
        " . ($product_id ? " AND p.id = ?" : "") . "
        GROUP BY p.id, p.name
    ";
    
    $params_fuel = array_merge([$tenant_id, $start_date, $end_date], $branch_params);
    if ($product_id) $params_fuel[] = $product_id;
    
    $stmt = $pdo->prepare($sql_fuel);
    $stmt->execute($params_fuel);
    $fuel_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merge sales items
    $all_sales = array_merge($sales_items, $fuel_items);

    // Calculate COGS for each product
    $itemwise_data = [];
    foreach ($all_sales as $item) {
        // Get average cost including opening stock
        $stock_opening_filter = $company_id ? " AND so.product_id IN (SELECT id FROM products WHERE company_id = ?)" : "";
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(
                    (
                        COALESCE(SUM(pii.net_amount), 0) + 
                        COALESCE((SELECT SUM(so.opening_qty * so.opening_price) 
                                  FROM stock_opening so
                                  WHERE so.product_id = ? 
                                  AND so.tenant_id = ?
                                  $stock_opening_filter), 0)
                    ) / NULLIF(
                        COALESCE(SUM(pii.quantity), 0) + 
                        COALESCE((SELECT SUM(so.opening_qty) 
                                  FROM stock_opening so
                                  WHERE so.product_id = ? 
                                  AND so.tenant_id = ?
                                  $stock_opening_filter), 0)
                    , 0), 0
                ) as avg_cost
            FROM purchase_invoice pi
            JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
            WHERE pii.product_id = ?
            AND pi.tenant_id = ?
            AND pi.purchase_date <= ?
            " . ($company_id ? " AND pi.company_id = ?" : "") . "
        ");
        
        $params_cogs = [$item['product_id'], $tenant_id];
        if ($company_id) $params_cogs[] = $company_id;
        $params_cogs[] = $item['product_id'];
        $params_cogs[] = $tenant_id;
        if ($company_id) $params_cogs[] = $company_id;
        $params_cogs[] = $item['product_id'];
        $params_cogs[] = $tenant_id;
        $params_cogs[] = $end_date;
        if ($company_id) $params_cogs[] = $company_id;
        
        $stmt->execute($params_cogs);
        $cost_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $avg_cost = (float)$cost_data['avg_cost'];
        $qty_sold = (float)$item['qty_sold'];
        $cogs = $avg_cost * $qty_sold;
        
        $itemwise_data[] = [
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'],
            'qty_sold' => $qty_sold,
            'revenue' => (float)$item['revenue'],
            'cogs' => $cogs,
            'profit' => (float)$item['revenue'] - $cogs
        ];
    }

    // ==================== CUSTOMER-WISE PROFIT & LOSS ====================
    
    // Get customer sales summary
    $sql = "
        SELECT 
            c.id as customer_id,
            c.customer_name as customer_name,
            COUNT(DISTINCT si.id) as invoice_count,
            SUM(si.net_amount) as revenue
        FROM sale_invoice si
        JOIN customers c ON si.customer_id = c.id
        JOIN branches b ON si.branch_id = b.id
        WHERE si.tenant_id = ?
        AND si.sale_date BETWEEN ? AND ?
        $branch_condition
        " . ($company_id ? " AND si.company_id = ?" : "") . "
        " . ($customer_id ? " AND c.id = ?" : "") . "
        GROUP BY c.id, c.customer_name
        ORDER BY revenue DESC
    ";
    
    $params = array_merge([$tenant_id, $start_date, $end_date], $branch_params);
    if ($company_id) $params[] = $company_id;
    if ($customer_id) $params[] = $customer_id;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customer_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate COGS for each customer
    $customerwise_data = [];
    foreach ($customer_sales as $customer) {
        // Get all products sold to this customer
        $sql = "
            SELECT 
                sii.product_id,
                SUM(sii.quantity) as qty_sold
            FROM sale_invoice si
            JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
            JOIN branches b ON si.branch_id = b.id
            WHERE si.tenant_id = ?
            AND si.customer_id = ?
            AND si.sale_date BETWEEN ? AND ?
            $branch_condition
            " . ($company_id ? " AND si.company_id = ?" : "") . "
            GROUP BY sii.product_id
        ";
        
        $params = array_merge([$tenant_id, $customer['customer_id'], $start_date, $end_date], $branch_params);
        if ($company_id) $params[] = $company_id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $customer_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_cogs = 0;
        foreach ($customer_products as $prod) {
            // Get average cost
            $stock_opening_filter = $company_id ? " AND so.product_id IN (SELECT id FROM products WHERE company_id = ?)" : "";
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(
                        (
                            COALESCE(SUM(pii.net_amount), 0) + 
                            COALESCE((SELECT SUM(so.opening_qty * so.opening_price) 
                                      FROM stock_opening so
                                      WHERE so.product_id = ? 
                                      AND so.tenant_id = ?
                                      $stock_opening_filter), 0)
                        ) / NULLIF(
                            COALESCE(SUM(pii.quantity), 0) + 
                            COALESCE((SELECT SUM(so.opening_qty) 
                                      FROM stock_opening so
                                      WHERE so.product_id = ? 
                                      AND so.tenant_id = ?
                                      $stock_opening_filter), 0)
                        , 0), 0
                    ) as avg_cost
                FROM purchase_invoice pi
                JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
                WHERE pii.product_id = ?
                AND pi.tenant_id = ?
                AND pi.purchase_date <= ?
                " . ($company_id ? " AND pi.company_id = ?" : "") . "
            ");
            
            $params_cogs = [$prod['product_id'], $tenant_id];
            if ($company_id) $params_cogs[] = $company_id;
            $params_cogs[] = $prod['product_id'];
            $params_cogs[] = $tenant_id;
            if ($company_id) $params_cogs[] = $company_id;
            $params_cogs[] = $prod['product_id'];
            $params_cogs[] = $tenant_id;
            $params_cogs[] = $end_date;
            if ($company_id) $params_cogs[] = $company_id;
            
            $stmt->execute($params_cogs);
            $cost_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $avg_cost = (float)$cost_data['avg_cost'];
            $qty_sold = (float)$prod['qty_sold'];
            $total_cogs += $avg_cost * $qty_sold;
        }

        $customerwise_data[] = [
            'customer_id' => $customer['customer_id'],
            'customer_name' => $customer['customer_name'],
            'invoice_count' => $customer['invoice_count'],
            'revenue' => (float)$customer['revenue'],
            'cogs' => $total_cogs,
            'profit' => (float)$customer['revenue'] - $total_cogs
        ];
    }

    // ==================== INVOICE-WISE PROFIT & LOSS ====================
    
    // Get all invoices with revenue
    $sql = "SELECT 
            si.id as invoice_id,
            si.bill_no,
            si.sale_date,
            c.customer_name,
            si.net_amount as revenue
        FROM sale_invoice si
        JOIN customers c ON si.customer_id = c.id
        JOIN branches b ON si.branch_id = b.id
        WHERE si.tenant_id = ?
        AND si.sale_date BETWEEN ? AND ?
        $branch_condition
        " . ($company_id ? " AND si.company_id = ?" : "") . "
        " . ($customer_id ? " AND si.customer_id = ?" : "") . "
        " . ($invoice_id ? " AND si.id = ?" : "") . "
        ORDER BY si.sale_date DESC, si.bill_no DESC
    ";
    
    $params = array_merge([$tenant_id, $start_date, $end_date], $branch_params);
    if ($company_id) $params[] = $company_id;
    if ($customer_id) $params[] = $customer_id;
    if ($invoice_id) $params[] = $invoice_id;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate COGS for each invoice
    $invoicewise_data = [];
    foreach ($invoices as $invoice) {
        // Get all products in this invoice
        $sql = "SELECT 
                sii.product_id,
                SUM(sii.quantity) as qty_sold
            FROM sale_invoice_items sii
            WHERE sii.sale_invoice_id = ?
            AND sii.tenant_id = ?
            " . ($product_id ? " AND sii.product_id = ?" : "") . "
            GROUP BY sii.product_id
        ";
        
        $params = [$invoice['invoice_id'], $tenant_id];
        if ($product_id) $params[] = $product_id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $invoice_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_cogs = 0;
        foreach ($invoice_products as $prod) {
            // Get average cost
            $stock_opening_filter = $company_id ? " AND so.product_id IN (SELECT id FROM products WHERE company_id = ?)" : "";
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(
                        (
                            COALESCE(SUM(pii.net_amount), 0) + 
                            COALESCE((SELECT SUM(so.opening_qty * so.opening_price) 
                                      FROM stock_opening so
                                      WHERE so.product_id = ? 
                                      AND so.tenant_id = ?
                                      $stock_opening_filter), 0)
                        ) / NULLIF(
                            COALESCE(SUM(pii.quantity), 0) + 
                            COALESCE((SELECT SUM(so.opening_qty) 
                                      FROM stock_opening so
                                      WHERE so.product_id = ? 
                                      AND so.tenant_id = ?
                                      $stock_opening_filter), 0)
                        , 0), 0
                    ) as avg_cost
                FROM purchase_invoice pi
                JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
                WHERE pii.product_id = ?
                AND pi.tenant_id = ?
                AND pi.purchase_date <= ?
                " . ($company_id ? " AND pi.company_id = ?" : "") . "
            ");
            
            $params_cogs = [$prod['product_id'], $tenant_id];
            if ($company_id) $params_cogs[] = $company_id;
            $params_cogs[] = $prod['product_id'];
            $params_cogs[] = $tenant_id;
            if ($company_id) $params_cogs[] = $company_id;
            $params_cogs[] = $prod['product_id'];
            $params_cogs[] = $tenant_id;
            $params_cogs[] = $invoice['sale_date'];
            if ($company_id) $params_cogs[] = $company_id;
            
            $stmt->execute($params_cogs);
            $cost_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $avg_cost = (float)$cost_data['avg_cost'];
            $qty_sold = (float)$prod['qty_sold'];
            $total_cogs += $avg_cost * $qty_sold;
        }

        $invoicewise_data[] = [
            'invoice_id' => $invoice['invoice_id'],
            'bill_no' => $invoice['bill_no'],
            'sale_date' => $invoice['sale_date'],
            'customer_name' => $invoice['customer_name'],
            'revenue' => (float)$invoice['revenue'],
            'cogs' => $total_cogs,
            'profit' => (float)$invoice['revenue'] - $total_cogs
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'itemwise' => $itemwise_data,
            'customerwise' => $customerwise_data,
            'invoicewise' => $invoicewise_data
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
