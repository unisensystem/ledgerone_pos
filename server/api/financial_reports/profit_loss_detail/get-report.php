<?php
session_start();
require_once '../../../../includes/connection.php';
require_once 'currency-converter.php';

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
$category_id = !empty($_GET['category_id']) ? $_GET['category_id'] : null;
$customer_id = !empty($_GET['customer_id']) ? $_GET['customer_id'] : null;
$product_id = !empty($_GET['product_id']) ? $_GET['product_id'] : null;
$invoice_id = !empty($_GET['invoice_id']) ? $_GET['invoice_id'] : null;
$target_currency_id = $_GET['currency_id'] ?? null;

if (!$start_date || !$end_date) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Start date and end date are required']);
    exit;
}

try {
    // Initialize currency converter
    $converter = new CurrencyConverter($pdo, $tenant_id);
    
    // Get base currency
    $stmt = $pdo->prepare("SELECT currency_id FROM tenant_currencies WHERE tenant_id = ? AND is_base_currency = 1");
    $stmt->execute([$tenant_id]);
    $baseCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
    $base_currency_id = $baseCurrency['currency_id'];
    
    // If no target currency specified, use base currency
    if (!$target_currency_id) {
        $target_currency_id = $base_currency_id;
    }
    
    // Build branch filter
    $branch_condition = '';
    $branch_params = [];
    if ($branch_id && $branch_id !== 'all') {
        $branch_condition = ' AND (b.id = ? OR b.parent_branch_id = ?)';
        $branch_params = [$branch_id, $branch_id];
    }

    // ==================== ITEM-WISE PROFIT & LOSS ====================
    
    // Get sales data from sale_invoice_items with currency
    $sql = "
        SELECT 
            p.id as product_id,
            p.name as product_name,
            sii.quantity as qty_sold,
            sii.net_amount as revenue,
            si.currency_id
        FROM sale_invoice si
        JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
        JOIN products p ON sii.product_id = p.id
        JOIN branches b ON si.branch_id = b.id
        WHERE si.tenant_id = ?
        AND si.sale_date BETWEEN ? AND ?
        AND p.product_type IN ('physical', 'service')
        $branch_condition
        " . ($company_id ? " AND si.company_id = ?" : "") . "
        " . ($category_id ? " AND p.category_id = ?" : "") . "
        " . ($customer_id ? " AND si.customer_id = ?" : "") . "
        " . ($product_id ? " AND p.id = ?" : "") . "
        " . ($invoice_id ? " AND si.id = ?" : "") . "
    ";
    
    $params = array_merge([$tenant_id, $start_date, $end_date], $branch_params);
    if ($company_id) $params[] = $company_id;
    if ($category_id) $params[] = $category_id;
    if ($customer_id) $params[] = $customer_id;
    if ($product_id) $params[] = $product_id;
    if ($invoice_id) $params[] = $invoice_id;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales_items_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group and convert sales items
    $sales_items = [];
    foreach ($sales_items_raw as $row) {
        $product_id_key = $row['product_id'];
        $revenue = $row['revenue'];
        $qty = $row['qty_sold'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        
        if ($from_currency != $target_currency_id) {
            $revenue = $converter->convert($revenue, $from_currency, $target_currency_id);
        }
        
        if (!isset($sales_items[$product_id_key])) {
            $sales_items[$product_id_key] = [
                'product_id' => $product_id_key,
                'product_name' => $row['product_name'],
                'qty_sold' => 0,
                'revenue' => 0
            ];
        }
        $sales_items[$product_id_key]['qty_sold'] += $qty;
        $sales_items[$product_id_key]['revenue'] += $revenue;
    }
    $sales_items = array_values($sales_items);

    // Get sales from station_daily_usage (fuel products) - base currency
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
        " . ($category_id ? " AND p.category_id = ?" : "") . "
        GROUP BY p.id, p.name
    ";
    
    $params_fuel = array_merge([$tenant_id, $start_date, $end_date], $branch_params);
    if ($product_id) $params_fuel[] = $product_id;
    if ($category_id) $params_fuel[] = $category_id;
    
    $stmt = $pdo->prepare($sql_fuel);
    $stmt->execute($params_fuel);
    $fuel_items_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert fuel items (base currency)
    $fuel_items = [];
    foreach ($fuel_items_raw as $row) {
        $revenue = $row['revenue'];
        if ($base_currency_id != $target_currency_id) {
            $revenue = $converter->convert($revenue, $base_currency_id, $target_currency_id);
        }
        $fuel_items[] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'qty_sold' => $row['qty_sold'],
            'revenue' => $revenue
        ];
    }

    // Merge sales items
    $all_sales = array_merge($sales_items, $fuel_items);

    // Calculate COGS for each product with currency conversion
    $itemwise_data = [];
    foreach ($all_sales as $item) {
        // Get purchase data with currency
        $stmt = $pdo->prepare("
            SELECT 
                pii.net_amount,
                pii.quantity,
                pi.currency_id
            FROM purchase_invoice pi
            JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
            WHERE pii.product_id = ?
            AND pi.tenant_id = ?
            AND pi.purchase_date <= ?
            " . ($company_id ? " AND pi.company_id = ?" : "") . "
        ");
        
        $params_cogs = [$item['product_id'], $tenant_id, $end_date];
        if ($company_id) $params_cogs[] = $company_id;
        
        $stmt->execute($params_cogs);
        $purchase_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_cost = 0;
        $total_qty = 0;
        
        foreach ($purchase_data as $purchase) {
            $cost = $purchase['net_amount'];
            $from_currency = $purchase['currency_id'] ?? $base_currency_id;
            if ($from_currency != $target_currency_id) {
                $cost = $converter->convert($cost, $from_currency, $target_currency_id);
            }
            $total_cost += $cost;
            $total_qty += $purchase['quantity'];
        }

        // Add stock-in from Stock Adjustments (increase type) - base currency
        $stmt = $pdo->prepare("
            SELECT sai.qty, sai.rate
            FROM stock_adjustment_items sai
            JOIN stock_adjustment sa ON sa.id = sai.adjustment_id
            WHERE sai.product_id = ?
            AND sai.tenant_id = ?
            AND sa.adjustment_type = 'increase'
            AND sa.date <= ?
            " . ($company_id ? " AND sa.company_id = ?" : "") . "
        ");
        $params_adj = [$item['product_id'], $tenant_id, $end_date];
        if ($company_id) $params_adj[] = $company_id;
        $stmt->execute($params_adj);
        $adjustment_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($adjustment_data as $adj) {
            $adj_cost = $adj['rate'] * $adj['qty'];
            if ($base_currency_id != $target_currency_id) {
                $adj_cost = $converter->convert($adj_cost, $base_currency_id, $target_currency_id);
            }
            $total_cost += $adj_cost;
            $total_qty += $adj['qty'];
        }
        
        // Add opening stock (base currency)
        $stock_opening_filter = $company_id ? " AND so.product_id IN (SELECT id FROM products WHERE company_id = ?)" : "";
        $stmt = $pdo->prepare("
            SELECT SUM(so.opening_qty * so.opening_price) as opening_cost, SUM(so.opening_qty) as opening_qty
            FROM stock_opening so
            WHERE so.product_id = ? 
            AND so.tenant_id = ?
            $stock_opening_filter
        ");
        $params_opening = [$item['product_id'], $tenant_id];
        if ($company_id) $params_opening[] = $company_id;
        $stmt->execute($params_opening);
        $opening_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($opening_data) {
            $opening_cost = $opening_data['opening_cost'] ?? 0;
            $opening_qty = $opening_data['opening_qty'] ?? 0;
            
            if ($base_currency_id != $target_currency_id) {
                $opening_cost = $converter->convert($opening_cost, $base_currency_id, $target_currency_id);
            }
            
            $total_cost += $opening_cost;
            $total_qty += $opening_qty;
        }
        
        $avg_cost = $total_qty > 0 ? $total_cost / $total_qty : 0;
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

    // ==================== CATEGORY-WISE PROFIT & LOSS ====================
    
    $categorywise_data = [];
    $category_map = [];
    
    foreach ($itemwise_data as $item) {
        $stmt = $pdo->prepare("SELECT c.id, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
        $stmt->execute([$item['product_id']]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $category_id = $cat['id'] ?? 0;
        $category_name = $cat['category_name'] ?? 'Uncategorized';
        
        if (!isset($category_map[$category_id])) {
            $category_map[$category_id] = [
                'category_id' => $category_id,
                'category_name' => $category_name,
                'product_count' => 0,
                'revenue' => 0,
                'cogs' => 0,
                'profit' => 0
            ];
        }
        
        $category_map[$category_id]['product_count']++;
        $category_map[$category_id]['revenue'] += $item['revenue'];
        $category_map[$category_id]['cogs'] += $item['cogs'];
        $category_map[$category_id]['profit'] += $item['profit'];
    }
    
    $categorywise_data = array_values($category_map);

    // ==================== CUSTOMER-WISE PROFIT & LOSS ====================
    
    // Get customer sales summary with currency
    $sql = "
        SELECT 
            c.id as customer_id,
            c.customer_name as customer_name,
            si.id as invoice_id,
            si.net_amount as revenue,
            si.currency_id
        FROM sale_invoice si
        JOIN customers c ON si.customer_id = c.id
        JOIN branches b ON si.branch_id = b.id
        WHERE si.tenant_id = ?
        AND si.sale_date BETWEEN ? AND ?
        $branch_condition
        " . ($company_id ? " AND si.company_id = ?" : "") . "
        " . ($customer_id ? " AND c.id = ?" : "") . "
    ";
    
    $params = array_merge([$tenant_id, $start_date, $end_date], $branch_params);
    if ($company_id) $params[] = $company_id;
    if ($customer_id) $params[] = $customer_id;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customer_sales_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group and convert customer sales
    $customer_sales = [];
    foreach ($customer_sales_raw as $row) {
        $customer_id_key = $row['customer_id'];
        $revenue = $row['revenue'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        
        if ($from_currency != $target_currency_id) {
            $revenue = $converter->convert($revenue, $from_currency, $target_currency_id);
        }
        
        if (!isset($customer_sales[$customer_id_key])) {
            $customer_sales[$customer_id_key] = [
                'customer_id' => $customer_id_key,
                'customer_name' => $row['customer_name'],
                'invoice_count' => 0,
                'revenue' => 0
            ];
        }
        $customer_sales[$customer_id_key]['invoice_count']++;
        $customer_sales[$customer_id_key]['revenue'] += $revenue;
    }
    $customer_sales = array_values($customer_sales);

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
            // Get purchase data with currency
            $stmt = $pdo->prepare("
                SELECT 
                    pii.net_amount,
                    pii.quantity,
                    pi.currency_id
                FROM purchase_invoice pi
                JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
                WHERE pii.product_id = ?
                AND pi.tenant_id = ?
                AND pi.purchase_date <= ?
                " . ($company_id ? " AND pi.company_id = ?" : "") . "
            ");
            
            $params_cogs = [$prod['product_id'], $tenant_id, $end_date];
            if ($company_id) $params_cogs[] = $company_id;
            
            $stmt->execute($params_cogs);
            $purchase_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total_cost = 0;
            $total_qty = 0;
            
            foreach ($purchase_data as $purchase) {
                $cost = $purchase['net_amount'];
                $from_currency = $purchase['currency_id'] ?? $base_currency_id;
                if ($from_currency != $target_currency_id) {
                    $cost = $converter->convert($cost, $from_currency, $target_currency_id);
                }
                $total_cost += $cost;
                $total_qty += $purchase['quantity'];
            }

            // Add stock-in from Stock Adjustments (increase type) - base currency
            $stmt2 = $pdo->prepare("
                SELECT sai.qty, sai.rate
                FROM stock_adjustment_items sai
                JOIN stock_adjustment sa ON sa.id = sai.adjustment_id
                WHERE sai.product_id = ?
                AND sai.tenant_id = ?
                AND sa.adjustment_type = 'increase'
                AND sa.date <= ?
                " . ($company_id ? " AND sa.company_id = ?" : "") . "
            ");
            $params_adj = [$prod['product_id'], $tenant_id, $end_date];
            if ($company_id) $params_adj[] = $company_id;
            $stmt2->execute($params_adj);
            $adjustment_data = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            foreach ($adjustment_data as $adj) {
                $adj_cost = $adj['rate'] * $adj['qty'];
                if ($base_currency_id != $target_currency_id) {
                    $adj_cost = $converter->convert($adj_cost, $base_currency_id, $target_currency_id);
                }
                $total_cost += $adj_cost;
                $total_qty += $adj['qty'];
            }
            
            // Add opening stock
            $stock_opening_filter = $company_id ? " AND so.product_id IN (SELECT id FROM products WHERE company_id = ?)" : "";
            $stmt = $pdo->prepare("
                SELECT SUM(so.opening_qty * so.opening_price) as opening_cost, SUM(so.opening_qty) as opening_qty
                FROM stock_opening so
                WHERE so.product_id = ? 
                AND so.tenant_id = ?
                $stock_opening_filter
            ");
            $params_opening = [$prod['product_id'], $tenant_id];
            if ($company_id) $params_opening[] = $company_id;
            $stmt->execute($params_opening);
            $opening_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($opening_data) {
                $opening_cost = $opening_data['opening_cost'] ?? 0;
                $opening_qty = $opening_data['opening_qty'] ?? 0;
                
                if ($base_currency_id != $target_currency_id) {
                    $opening_cost = $converter->convert($opening_cost, $base_currency_id, $target_currency_id);
                }
                
                $total_cost += $opening_cost;
                $total_qty += $opening_qty;
            }
            
            $avg_cost = $total_qty > 0 ? $total_cost / $total_qty : 0;
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
    
    // Get all invoices with revenue and currency
    $sql = "SELECT 
            si.id as invoice_id,
            si.bill_no,
            si.sale_date,
            c.customer_name,
            si.net_amount as revenue,
            si.currency_id
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
    $invoices_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert invoice revenue
    $invoices = [];
    foreach ($invoices_raw as $row) {
        $revenue = $row['revenue'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $revenue = $converter->convert($revenue, $from_currency, $target_currency_id);
        }
        $invoices[] = [
            'invoice_id' => $row['invoice_id'],
            'bill_no' => $row['bill_no'],
            'sale_date' => $row['sale_date'],
            'customer_name' => $row['customer_name'],
            'revenue' => $revenue
        ];
    }

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
            // Get purchase data with currency
            $stmt = $pdo->prepare("
                SELECT 
                    pii.net_amount,
                    pii.quantity,
                    pi.currency_id
                FROM purchase_invoice pi
                JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
                WHERE pii.product_id = ?
                AND pi.tenant_id = ?
                AND pi.purchase_date <= ?
                " . ($company_id ? " AND pi.company_id = ?" : "") . "
            ");
            
            $params_cogs = [$prod['product_id'], $tenant_id, $invoice['sale_date']];
            if ($company_id) $params_cogs[] = $company_id;
            
            $stmt->execute($params_cogs);
            $purchase_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total_cost = 0;
            $total_qty = 0;
            
            foreach ($purchase_data as $purchase) {
                $cost = $purchase['net_amount'];
                $from_currency = $purchase['currency_id'] ?? $base_currency_id;
                if ($from_currency != $target_currency_id) {
                    $cost = $converter->convert($cost, $from_currency, $target_currency_id);
                }
                $total_cost += $cost;
                $total_qty += $purchase['quantity'];
            }

            // Add stock-in from Stock Adjustments (increase type) - base currency
            $stmt2 = $pdo->prepare("
                SELECT sai.qty, sai.rate
                FROM stock_adjustment_items sai
                JOIN stock_adjustment sa ON sa.id = sai.adjustment_id
                WHERE sai.product_id = ?
                AND sai.tenant_id = ?
                AND sa.adjustment_type = 'increase'
                AND sa.date <= ?
                " . ($company_id ? " AND sa.company_id = ?" : "") . "
            ");
            $params_adj = [$prod['product_id'], $tenant_id, $invoice['sale_date']];
            if ($company_id) $params_adj[] = $company_id;
            $stmt2->execute($params_adj);
            $adjustment_data = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            foreach ($adjustment_data as $adj) {
                $adj_cost = $adj['rate'] * $adj['qty'];
                if ($base_currency_id != $target_currency_id) {
                    $adj_cost = $converter->convert($adj_cost, $base_currency_id, $target_currency_id);
                }
                $total_cost += $adj_cost;
                $total_qty += $adj['qty'];
            }
            
            // Add opening stock
            $stock_opening_filter = $company_id ? " AND so.product_id IN (SELECT id FROM products WHERE company_id = ?)" : "";
            $stmt = $pdo->prepare("
                SELECT SUM(so.opening_qty * so.opening_price) as opening_cost, SUM(so.opening_qty) as opening_qty
                FROM stock_opening so
                WHERE so.product_id = ? 
                AND so.tenant_id = ?
                $stock_opening_filter
            ");
            $params_opening = [$prod['product_id'], $tenant_id];
            if ($company_id) $params_opening[] = $company_id;
            $stmt->execute($params_opening);
            $opening_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($opening_data) {
                $opening_cost = $opening_data['opening_cost'] ?? 0;
                $opening_qty = $opening_data['opening_qty'] ?? 0;
                
                if ($base_currency_id != $target_currency_id) {
                    $opening_cost = $converter->convert($opening_cost, $base_currency_id, $target_currency_id);
                }
                
                $total_cost += $opening_cost;
                $total_qty += $opening_qty;
            }
            
            $avg_cost = $total_qty > 0 ? $total_cost / $total_qty : 0;
            $qty_sold = (float)$prod['qty_sold'];
            $total_cogs += $avg_cost * $qty_sold;
        }

        $invoicewise_data[] = [
            'invoice_id' => $invoice['invoice_id'],
            'bill_no' => $invoice['bill_no'],
            'sale_date' => $invoice['sale_date'],
            'customer_name' => $invoice['customer_name'],
            'revenue' => $invoice['revenue'],
            'cogs' => $total_cogs,
            'profit' => $invoice['revenue'] - $total_cogs
        ];
    }

    // ==================== SUPPLIER-WISE PROFIT & LOSS ====================

    // Build a lookup: product_id => itemwise row (revenue, cogs, qty_sold)
    $itemwise_by_product = [];
    foreach ($itemwise_data as $item) {
        $itemwise_by_product[$item['product_id']] = $item;
    }

    // Get all suppliers who have products sold in this period
    // via purchase_invoice supplier link OR products.vendor_id
    $sql = "
        SELECT DISTINCT
            s.id as supplier_id,
            s.supplier_name,
            s.supplier_code
        FROM suppliers s
        WHERE s.tenant_id = ?
        AND s.is_blacklisted = 0
        AND (
            EXISTS (
                SELECT 1 FROM purchase_invoice pi
                JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
                WHERE pi.supplier_id = s.id
                AND pi.tenant_id = ?
                " . ($company_id ? " AND pi.company_id = ?" : "") . "
            )
            OR
            EXISTS (
                SELECT 1 FROM products p
                WHERE p.vendor_id = s.id
                AND p.tenant_id = ?
                AND p.is_active = 1
            )
        )
        ORDER BY s.supplier_name
    ";

    $params_sup = [$tenant_id, $tenant_id];
    if ($company_id) $params_sup[] = $company_id;
    $params_sup[] = $tenant_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_sup);
    $all_suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter by supplier if specified
    if (!empty($_GET['supplier_id'])) {
        $filter_supplier_id = $_GET['supplier_id'];
        $all_suppliers = array_filter($all_suppliers, fn($s) => $s['supplier_id'] == $filter_supplier_id);
        $all_suppliers = array_values($all_suppliers);
    }

    $supplierwise_data = [];

    foreach ($all_suppliers as $supplier) {
        $sup_id = $supplier['supplier_id'];

        // Get all product IDs linked to this supplier:
        // 1. via purchase_invoice items
        $stmt = $pdo->prepare("
            SELECT DISTINCT pii.product_id
            FROM purchase_invoice pi
            JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
            WHERE pi.supplier_id = ?
            AND pi.tenant_id = ?
            " . ($company_id ? " AND pi.company_id = ?" : "") . "
            " . (!empty($_GET['product_id']) ? " AND pii.product_id = ?" : "") . "
        ");
        $params_pid = [$sup_id, $tenant_id];
        if ($company_id) $params_pid[] = $company_id;
        if (!empty($_GET['product_id'])) $params_pid[] = $_GET['product_id'];
        $stmt->execute($params_pid);
        $supplier_product_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'product_id');

        // 2. via products.vendor_id
        $stmt = $pdo->prepare("
            SELECT id FROM products
            WHERE vendor_id = ?
            AND tenant_id = ?
            AND is_active = 1
            " . (!empty($_GET['product_id']) ? " AND id = ?" : "") . "
        ");
        $params_vp = [$sup_id, $tenant_id];
        if (!empty($_GET['product_id'])) $params_vp[] = $_GET['product_id'];
        $stmt->execute($params_vp);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $vp) {
            if (!in_array($vp['id'], $supplier_product_ids)) {
                $supplier_product_ids[] = $vp['id'];
            }
        }

        // Count distinct purchase invoices in date range
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT pi.id) as cnt
            FROM purchase_invoice pi
            WHERE pi.supplier_id = ?
            AND pi.tenant_id = ?
            AND pi.purchase_date BETWEEN ? AND ?
            " . ($branch_id && $branch_id !== 'all' ? " AND (pi.branch_id = ? OR pi.branch_id IN (SELECT id FROM branches WHERE parent_branch_id = ?))" : "") . "
            " . ($company_id ? " AND pi.company_id = ?" : "") . "
        ");
        $params_cnt = [$sup_id, $tenant_id, $start_date, $end_date];
        if ($branch_id && $branch_id !== 'all') {
            $params_cnt[] = $branch_id;
            $params_cnt[] = $branch_id;
        }
        if ($company_id) $params_cnt[] = $company_id;
        $stmt->execute($params_cnt);
        $purchase_count = (int)$stmt->fetchColumn();

        // --- COGS & REVENUE: aggregate from already-calculated itemwise_data ---
        // This ensures supplier COGS = weighted avg cost × qty sold (same as item-wise)
        $total_revenue = 0;
        $total_cogs    = 0;

        foreach ($supplier_product_ids as $pid) {
            if (isset($itemwise_by_product[$pid])) {
                $total_revenue += $itemwise_by_product[$pid]['revenue'];
                $total_cogs    += $itemwise_by_product[$pid]['cogs'];
            }
        }

        // Only show suppliers where products have been sold in this period
        if ($total_revenue == 0) continue;

        $supplierwise_data[] = [
            'supplier_id'    => $sup_id,
            'supplier_name'  => $supplier['supplier_name'],
            'supplier_code'  => $supplier['supplier_code'],
            'purchase_count' => $purchase_count,
            'revenue'        => $total_revenue,
            'cogs'           => $total_cogs,
            'profit'         => $total_revenue - $total_cogs
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'itemwise'     => $itemwise_data,
            'categorywise' => $categorywise_data,
            'customerwise' => $customerwise_data,
            'invoicewise'  => $invoicewise_data,
            'supplierwise' => $supplierwise_data
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}