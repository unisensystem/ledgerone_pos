<?php
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

$report_type = $_GET['type'] ?? 'item';
$date_range = $_GET['date_range'] ?? 'last30';
$company_id = $_GET['company_id'] ?? null;
$project_id = $_GET['project_id'] ?? null;
$supplier_id = $_GET['supplier_id'] ?? null;

// Calculate date range
$end_date = date('Y-m-d');
if (strpos($date_range, '_') !== false) {
    // Custom date range
    list($start_date, $end_date) = explode('_', $date_range);
} else {
    switch ($date_range) {
        case 'last7':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'last90':
            $start_date = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'ytd':
            $start_date = date('Y-01-01');
            break;
        default:
            $start_date = date('Y-m-d', strtotime('-30 days'));
    }
}

// Get currency symbol
$stmt = $pdo->prepare("SELECT c.symbol FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_base_currency = 1");
$stmt->execute([$tenant_id]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'] ?? '$';

// Helper to format a batch no / expiry date pair consistently everywhere
function formatBatchNo($batch_no) {
    return $batch_no ?: '-';
}
function formatExpiryDate($expiry_date) {
    return $expiry_date ? date('Y-m-d', strtotime($expiry_date)) : '-';
}

try {
    $data = [];
    $summary = [];
    
    $company_filter = $company_id ? " AND pi.company_id = ?" : "";
    $project_filter = $project_id ? " AND pi.project_id = ?" : "";
    $supplier_filter = $supplier_id ? " AND pi.supplier_id = ?" : "";
    
    // Get summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT p.id) as items_purchased,
            SUM(pii.quantity) as total_quantity,
            AVG(pii.purchase_price) as avg_unit_price,
            SUM(pii.net_amount) as total_value
        FROM purchase_invoice_items pii
        JOIN purchase_invoice pi ON pii.purchase_invoice_id = pi.id
        JOIN products p ON pii.product_id = p.id
        WHERE pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?{$company_filter}{$project_filter}{$supplier_filter}
    ");
    $params = [$tenant_id, $start_date, $end_date];
    if ($company_id) $params[] = $company_id;
    if ($project_id) $params[] = $project_id;
    if ($supplier_id) $params[] = $supplier_id;
    $stmt->execute($params);
    $summary['item'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT pi.id) as total_invoices,
            AVG(pi.net_amount) as avg_invoice_value,
            COUNT(DISTINCT pi.id) as pending_invoices,
            SUM(pi.net_amount) as total_invoice_value
        FROM purchase_invoice pi
        WHERE pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?{$company_filter}
    ");
    $params = [$tenant_id, $start_date, $end_date];
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $summary['invoice'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT COALESCE(c.id, 0)) as categories,
            (SELECT COALESCE(c2.category_name, 'Uncategorized')
             FROM purchase_invoice_items pii2
             JOIN purchase_invoice pi2 ON pii2.purchase_invoice_id = pi2.id
             JOIN products p2 ON pii2.product_id = p2.id
             LEFT JOIN categories c2 ON p2.category_id = c2.id
             WHERE pi2.tenant_id = ? AND pi2.purchase_date BETWEEN ? AND ?
             GROUP BY c2.id, c2.category_name
             ORDER BY SUM(pii2.net_amount) DESC
             LIMIT 1) as top_category,
            (SELECT SUM(pii3.net_amount)
             FROM purchase_invoice_items pii3
             JOIN purchase_invoice pi3 ON pii3.purchase_invoice_id = pi3.id
             JOIN products p3 ON pii3.product_id = p3.id
             LEFT JOIN categories c3 ON p3.category_id = c3.id
             WHERE pi3.tenant_id = ? AND pi3.purchase_date BETWEEN ? AND ?
             GROUP BY c3.id
             ORDER BY SUM(pii3.net_amount) DESC
             LIMIT 1) as category_spend,
            (SELECT (SUM(pii4.net_amount) / (SELECT SUM(net_amount) FROM purchase_invoice WHERE tenant_id = ? AND purchase_date BETWEEN ? AND ?) * 100)
             FROM purchase_invoice_items pii4
             JOIN purchase_invoice pi4 ON pii4.purchase_invoice_id = pi4.id
             JOIN products p4 ON pii4.product_id = p4.id
             LEFT JOIN categories c4 ON p4.category_id = c4.id
             WHERE pi4.tenant_id = ? AND pi4.purchase_date BETWEEN ? AND ?
             GROUP BY c4.id
             ORDER BY SUM(pii4.net_amount) DESC
             LIMIT 1) as category_percent
        FROM purchase_invoice_items pii
        JOIN purchase_invoice pi ON pii.purchase_invoice_id = pi.id
        JOIN products p ON pii.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $start_date, $end_date, $tenant_id, $start_date, $end_date, $tenant_id, $start_date, $end_date, $tenant_id, $start_date, $end_date, $tenant_id, $start_date, $end_date]);
    $summary['category'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT s.id) as active_suppliers,
            (SELECT s2.supplier_name
             FROM purchase_invoice pi2
             JOIN suppliers s2 ON pi2.supplier_id = s2.id
             WHERE pi2.tenant_id = ? AND pi2.purchase_date BETWEEN ? AND ?
             GROUP BY s2.id, s2.supplier_name
             ORDER BY SUM(pi2.net_amount) DESC
             LIMIT 1) as top_supplier,
            (SELECT SUM(pi3.net_amount)
             FROM purchase_invoice pi3
             JOIN suppliers s3 ON pi3.supplier_id = s3.id
             WHERE pi3.tenant_id = ? AND pi3.purchase_date BETWEEN ? AND ?
             GROUP BY s3.id
             ORDER BY SUM(pi3.net_amount) DESC
             LIMIT 1) as supplier_spend,
            (SELECT (SUM(pi4.net_amount) / (SELECT SUM(net_amount) FROM purchase_invoice WHERE tenant_id = ? AND purchase_date BETWEEN ? AND ?) * 100)
             FROM purchase_invoice pi4
             JOIN suppliers s4 ON pi4.supplier_id = s4.id
             WHERE pi4.tenant_id = ? AND pi4.purchase_date BETWEEN ? AND ?
             GROUP BY s4.id
             ORDER BY SUM(pi4.net_amount) DESC
             LIMIT 1) as supplier_percent
        FROM purchase_invoice pi
        JOIN suppliers s ON pi.supplier_id = s.id
        WHERE pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $start_date, $end_date, $tenant_id, $start_date, $end_date, $tenant_id, $start_date, $end_date, $tenant_id, $start_date, $end_date, $tenant_id, $start_date, $end_date]);
    $summary['supplier'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    switch ($report_type) {
        case 'item':
            $stmt = $pdo->prepare("
    SELECT 
        p.id as product_id,
        p.code as id,
        p.name,
        COALESCE(c.category_name, 'Uncategorized') as category,
        GROUP_CONCAT(DISTINCT NULLIF(pii.batch_no,'') SEPARATOR ', ') as batch_no,
        MIN(CASE WHEN pii.batch_expiry_date >= CURDATE() THEN pii.batch_expiry_date END) as nearest_expiry,
        SUM(pii.quantity) as quantity,
        AVG(pii.purchase_price) as unit_price,
        SUM(pii.net_amount) as total,
        s.supplier_name as supplier
    FROM purchase_invoice_items pii
    JOIN purchase_invoice pi ON pii.purchase_invoice_id = pi.id
    JOIN products p ON pii.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    JOIN suppliers s ON pi.supplier_id = s.id
    WHERE pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?{$company_filter}{$project_filter}{$supplier_filter}
    GROUP BY p.id, p.code, p.name, c.category_name, s.supplier_name
    ORDER BY total DESC
");
            $params = [$tenant_id, $start_date, $end_date];
            if ($company_id) $params[] = $company_id;
            if ($project_id) $params[] = $project_id;
            if ($supplier_id) $params[] = $supplier_id;
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                // Get invoices for this product (now including batch no + expiry per invoice line)
                $invoiceStmt = $pdo->prepare("
                    SELECT 
                        pi.bill_no,
                        pi.purchase_date,
                        pii.quantity,
                        pii.purchase_price,
                        pii.net_amount,
                        pii.batch_no,
                        pii.batch_expiry_date,
                        s.supplier_name
                    FROM purchase_invoice_items pii
                    JOIN purchase_invoice pi ON pii.purchase_invoice_id = pi.id
                    JOIN suppliers s ON pi.supplier_id = s.id
                    WHERE pii.product_id = ? AND pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?
                    ORDER BY pi.purchase_date DESC
                ");
                $invoiceStmt->execute([$row['product_id'], $tenant_id, $start_date, $end_date]);
                $invoices = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $invoicesBreakdown = [];
                foreach ($invoices as $invoice) {
                    $invoicesBreakdown[] = [
                        'billNo' => $invoice['bill_no'],
                        'date' => date('Y-m-d', strtotime($invoice['purchase_date'])),
                        'quantity' => number_format($invoice['quantity'], 2),
                        'quantityRaw' => (float)$invoice['quantity'],
                        'price' => $currency_symbol . number_format($invoice['purchase_price'], 2),
                        'priceRaw' => (float)$invoice['purchase_price'],
                        'total' => $currency_symbol . number_format($invoice['net_amount'], 2),
                        'totalRaw' => (float)$invoice['net_amount'],
                        'batchNo' => formatBatchNo($invoice['batch_no']),
                        'expiryDate' => formatExpiryDate($invoice['batch_expiry_date']),
                        'supplier' => $invoice['supplier_name']
                    ];
                }
                
              $data[] = [
    'id' => $row['id'],
    'name' => $row['name'],
    'category' => $row['category'],
    'batchNo' => $row['batch_no'] ?: '-',
    'expiryDate' => $row['nearest_expiry'] ? date('Y-m-d', strtotime($row['nearest_expiry'])) : '-',
    'quantity' => number_format($row['quantity'], 2),
    'quantityRaw' => (float)$row['quantity'],
    'unitPrice' => $currency_symbol . number_format($row['unit_price'], 2),
    'unitPriceRaw' => (float)$row['unit_price'],
    'total' => $currency_symbol . number_format($row['total'], 2),
    'totalRaw' => (float)$row['total'],
    'supplier' => $row['supplier'],
    'invoices' => $invoicesBreakdown
];
            }
            break;
            
        case 'invoice':
            $stmt = $pdo->prepare("
                SELECT 
                    pi.id as invoice_id,
                    pi.bill_no as id,
                    pi.bill_no as name,
                    'Mixed' as category,
                    COUNT(pii.id) as items,
                    pi.purchase_date as due_date,
                    pi.net_amount as total,
                    s.supplier_name as supplier,
                    GROUP_CONCAT(DISTINCT NULLIF(pii.batch_no,'') SEPARATOR ', ') as batch_no,
                    MIN(CASE WHEN pii.batch_expiry_date >= CURDATE() THEN pii.batch_expiry_date END) as nearest_expiry
                FROM purchase_invoice pi
                JOIN suppliers s ON pi.supplier_id = s.id
                LEFT JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
                WHERE pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?{$company_filter}{$project_filter}{$supplier_filter}
                GROUP BY pi.id, pi.bill_no, pi.purchase_date, pi.net_amount, s.supplier_name
                ORDER BY pi.purchase_date DESC
            ");
            $params = [$tenant_id, $start_date, $end_date];
            if ($company_id) $params[] = $company_id;
            if ($project_id) $params[] = $project_id;
            if ($supplier_id) $params[] = $supplier_id;
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                // Get items for this invoice (with batch no + expiry per item)
                $itemStmt = $pdo->prepare("
                    SELECT 
                        p.name as product_name,
                        pii.quantity,
                        pii.purchase_price,
                        pii.net_amount,
                        pii.batch_no,
                        pii.batch_expiry_date
                    FROM purchase_invoice_items pii
                    JOIN products p ON pii.product_id = p.id
                    WHERE pii.purchase_invoice_id = ?
                ");
                $itemStmt->execute([$row['invoice_id']]);
                $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $itemsBreakdown = [];
                foreach ($items as $item) {
                    $itemsBreakdown[] = [
                        'product' => $item['product_name'],
                        'quantity' => number_format($item['quantity'], 2),
                        'quantityRaw' => (float)$item['quantity'],
                        'price' => $currency_symbol . number_format($item['purchase_price'], 2),
                        'priceRaw' => (float)$item['purchase_price'],
                        'total' => $currency_symbol . number_format($item['net_amount'], 2),
                        'totalRaw' => (float)$item['net_amount'],
                        'batchNo' => formatBatchNo($item['batch_no']),
                        'expiryDate' => formatExpiryDate($item['batch_expiry_date'])
                    ];
                }
                
                $data[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'batchNo' => $row['batch_no'] ?: '-',
                    'expiryDate' => $row['nearest_expiry'] ? date('Y-m-d', strtotime($row['nearest_expiry'])) : '-',
                    'quantity' => $row['items'] . ' items',
                    'itemsCount' => (int)$row['items'],
                    'dueDate' => date('Y-m-d', strtotime($row['due_date'])),
                    'total' => $currency_symbol . number_format($row['total'], 2),
                    'totalRaw' => (float)$row['total'],
                    'supplier' => $row['supplier'],
                    'items' => $itemsBreakdown
                ];
            }
            break;
            
        case 'category':
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(c.id, 0) as category_id,
                    COALESCE(c.id, 0) as id,
                    COALESCE(c.category_name, 'Uncategorized') as name,
                    COALESCE(c.category_name, 'Uncategorized') as category,
                    SUM(pii.quantity) as quantity,
                    AVG(pii.purchase_price) as unit_price,
                    SUM(pii.net_amount) as total,
                    'Multiple' as supplier,
                    GROUP_CONCAT(DISTINCT NULLIF(pii.batch_no,'') SEPARATOR ', ') as batch_no,
                    MIN(CASE WHEN pii.batch_expiry_date >= CURDATE() THEN pii.batch_expiry_date END) as nearest_expiry
                FROM purchase_invoice_items pii
                JOIN purchase_invoice pi ON pii.purchase_invoice_id = pi.id
                JOIN products p ON pii.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?{$company_filter}
                GROUP BY c.id, c.category_name
                ORDER BY total DESC
            ");
            $params = [$tenant_id, $start_date, $end_date];
            if ($company_id) $params[] = $company_id;
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                // Get subcategories for this category
                $subcatStmt = $pdo->prepare("
                    SELECT 
                        COALESCE(sc.id, 0) as subcategory_id,
                        COALESCE(sc.subcategory_name, 'Uncategorized') as subcategory_name,
                        SUM(pii.quantity) as quantity,
                        AVG(pii.purchase_price) as unit_price,
                        SUM(pii.net_amount) as total,
                        GROUP_CONCAT(DISTINCT NULLIF(pii.batch_no,'') SEPARATOR ', ') as batch_no,
                        MIN(CASE WHEN pii.batch_expiry_date >= CURDATE() THEN pii.batch_expiry_date END) as nearest_expiry
                    FROM purchase_invoice_items pii
                    JOIN purchase_invoice pi ON pii.purchase_invoice_id = pi.id
                    JOIN products p ON pii.product_id = p.id
                    LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
                    WHERE pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?
                    AND (p.category_id = ? OR (p.category_id IS NULL AND ? = 0))
                    GROUP BY sc.id, sc.subcategory_name
                    ORDER BY total DESC
                ");
                $subcatStmt->execute([$tenant_id, $start_date, $end_date, $row['category_id'], $row['category_id']]);
                $subcategories = $subcatStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $subcategoriesBreakdown = [];
                foreach ($subcategories as $subcat) {
                    // Get invoices for this subcategory
                    $invoiceStmt = $pdo->prepare("
                        SELECT DISTINCT
                            pi.id as invoice_id,
                            pi.bill_no,
                            pi.purchase_date,
                            pi.net_amount
                        FROM purchase_invoice pi
                        JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
                        JOIN products p ON pii.product_id = p.id
                        WHERE pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?
                        AND (p.subcategory_id = ? OR (p.subcategory_id IS NULL AND ? = 0))
                        ORDER BY pi.purchase_date DESC
                    ");
                    $invoiceStmt->execute([$tenant_id, $start_date, $end_date, $subcat['subcategory_id'], $subcat['subcategory_id']]);
                    $invoices = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $invoicesBreakdown = [];
                    foreach ($invoices as $invoice) {
                        // Get items for this invoice in this subcategory (with batch no + expiry)
                        $itemStmt = $pdo->prepare("
                            SELECT 
                                p.name as product_name,
                                pii.quantity,
                                pii.purchase_price,
                                pii.net_amount,
                                pii.batch_no,
                                pii.batch_expiry_date
                            FROM purchase_invoice_items pii
                            JOIN products p ON pii.product_id = p.id
                            WHERE pii.purchase_invoice_id = ?
                            AND (p.subcategory_id = ? OR (p.subcategory_id IS NULL AND ? = 0))
                        ");
                        $itemStmt->execute([$invoice['invoice_id'], $subcat['subcategory_id'], $subcat['subcategory_id']]);
                        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $itemsBreakdown = [];
                        foreach ($items as $item) {
                            $itemsBreakdown[] = [
                                'product' => $item['product_name'],
                                'quantity' => number_format($item['quantity'], 2),
                                'quantityRaw' => (float)$item['quantity'],
                                'price' => $currency_symbol . number_format($item['purchase_price'], 2),
                                'priceRaw' => (float)$item['purchase_price'],
                                'total' => $currency_symbol . number_format($item['net_amount'], 2),
                                'totalRaw' => (float)$item['net_amount'],
                                'batchNo' => formatBatchNo($item['batch_no']),
                                'expiryDate' => formatExpiryDate($item['batch_expiry_date'])
                            ];
                        }
                        
                        if (!empty($itemsBreakdown)) {
                            $invoicesBreakdown[] = [
                                'billNo' => $invoice['bill_no'],
                                'date' => date('Y-m-d', strtotime($invoice['purchase_date'])),
                                'total' => $currency_symbol . number_format($invoice['net_amount'], 2),
                                'totalRaw' => (float)$invoice['net_amount'],
                                'items' => $itemsBreakdown
                            ];
                        }
                    }
                    
                    if (!empty($invoicesBreakdown)) {
                        $subcategoriesBreakdown[] = [
                            'name' => $subcat['subcategory_name'],
                            'quantity' => number_format($subcat['quantity'], 0),
                            'quantityRaw' => (float)$subcat['quantity'],
                            'unitPrice' => $currency_symbol . number_format($subcat['unit_price'], 2),
                            'unitPriceRaw' => (float)$subcat['unit_price'],
                            'total' => $currency_symbol . number_format($subcat['total'], 2),
                            'totalRaw' => (float)$subcat['total'],
                            'batchNo' => formatBatchNo($subcat['batch_no']),
                            'expiryDate' => formatExpiryDate($subcat['nearest_expiry']),
                            'invoices' => $invoicesBreakdown
                        ];
                    }
                }
                
                $data[] = [
                    'id' => 'CAT-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'quantity' => number_format($row['quantity'], 0),
                    'quantityRaw' => (float)$row['quantity'],
                    'unitPrice' => $currency_symbol . number_format($row['unit_price'], 2),
                    'unitPriceRaw' => (float)$row['unit_price'],
                    'total' => $currency_symbol . number_format($row['total'], 2),
                    'totalRaw' => (float)$row['total'],
                    'supplier' => $row['supplier'],
                    'batchNo' => $row['batch_no'] ?: '-',
                    'expiryDate' => $row['nearest_expiry'] ? date('Y-m-d', strtotime($row['nearest_expiry'])) : '-',
                    'subcategories' => $subcategoriesBreakdown
                ];
            }
            break;
            
        case 'supplier':
            $stmt = $pdo->prepare("
                SELECT 
                    s.id as supplier_id,
                    s.supplier_code as id,
                    s.supplier_name as name,
                    'Multiple' as category,
                    SUM(pii.quantity) as quantity,
                    AVG(pii.purchase_price) as unit_price,
                    SUM(pi.net_amount) as total,
                    s.supplier_name as supplier,
                    COALESCE(s.email, 'N/A') as contact,
                    GROUP_CONCAT(DISTINCT NULLIF(pii.batch_no,'') SEPARATOR ', ') as batch_no,
                    MIN(CASE WHEN pii.batch_expiry_date >= CURDATE() THEN pii.batch_expiry_date END) as nearest_expiry
                FROM purchase_invoice pi
                JOIN suppliers s ON pi.supplier_id = s.id
                LEFT JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
                WHERE pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?{$company_filter}
                GROUP BY s.id, s.supplier_code, s.supplier_name, s.email
                ORDER BY total DESC
            ");
            $params = [$tenant_id, $start_date, $end_date];
            if ($company_id) $params[] = $company_id;
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                // Get invoices for this supplier
                $invoiceStmt = $pdo->prepare("
                    SELECT 
                        pi.id as invoice_id,
                        pi.bill_no,
                        pi.purchase_date,
                        pi.net_amount
                    FROM purchase_invoice pi
                    WHERE pi.supplier_id = ? AND pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?
                    ORDER BY pi.purchase_date DESC
                ");
                $invoiceStmt->execute([$row['supplier_id'], $tenant_id, $start_date, $end_date]);
                $invoices = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $invoicesBreakdown = [];
                foreach ($invoices as $invoice) {
                    // Get items for each invoice (with batch no + expiry)
                    $itemStmt = $pdo->prepare("
                        SELECT 
                            p.name as product_name,
                            pii.quantity,
                            pii.purchase_price,
                            pii.net_amount,
                            pii.batch_no,
                            pii.batch_expiry_date
                        FROM purchase_invoice_items pii
                        JOIN products p ON pii.product_id = p.id
                        WHERE pii.purchase_invoice_id = ?
                    ");
                    $itemStmt->execute([$invoice['invoice_id']]);
                    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $itemsBreakdown = [];
                    foreach ($items as $item) {
                        $itemsBreakdown[] = [
                            'product' => $item['product_name'],
                            'quantity' => number_format($item['quantity'], 2),
                            'quantityRaw' => (float)$item['quantity'],
                            'price' => $currency_symbol . number_format($item['purchase_price'], 2),
                            'priceRaw' => (float)$item['purchase_price'],
                            'total' => $currency_symbol . number_format($item['net_amount'], 2),
                            'totalRaw' => (float)$item['net_amount'],
                            'batchNo' => formatBatchNo($item['batch_no']),
                            'expiryDate' => formatExpiryDate($item['batch_expiry_date'])
                        ];
                    }
                    
                    $invoicesBreakdown[] = [
                        'billNo' => $invoice['bill_no'],
                        'date' => date('Y-m-d', strtotime($invoice['purchase_date'])),
                        'total' => $currency_symbol . number_format($invoice['net_amount'], 2),
                        'totalRaw' => (float)$invoice['net_amount'],
                        'items' => $itemsBreakdown
                    ];
                }
                
                $data[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'quantity' => number_format($row['quantity'], 0),
                    'quantityRaw' => (float)$row['quantity'],
                    'unitPrice' => $currency_symbol . number_format($row['unit_price'], 2),
                    'unitPriceRaw' => (float)$row['unit_price'],
                    'total' => $currency_symbol . number_format($row['total'], 2),
                    'totalRaw' => (float)$row['total'],
                    'supplier' => $row['supplier'],
                    'contact' => $row['contact'],
                    'batchNo' => $row['batch_no'] ?: '-',
                    'expiryDate' => $row['nearest_expiry'] ? date('Y-m-d', strtotime($row['nearest_expiry'])) : '-',
                    'invoices' => $invoicesBreakdown
                ];
            }
            break;
    }
    
    echo json_encode(['success' => true, 'data' => $data, 'summary' => $summary, 'currency' => $currency_symbol]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error fetching report data', 'error' => $e->getMessage()]);
}