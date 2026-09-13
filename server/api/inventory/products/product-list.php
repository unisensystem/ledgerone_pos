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

try {
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';
    $vendor = $_GET['vendor'] ?? '';
    $uom_filter = $_GET['uom_filter'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));

// Allowed page sizes — whitelist taake koi arbitrary/negative value na bhej sake
$allowedLimits = [10, 20, 30, 40, 50, 60, 70, 80, 100];
$limit = (int)($_GET['per_page'] ?? 10);
if (!in_array($limit, $allowedLimits, true)) {
    $limit = 10;
}

$offset = ($page - 1) * $limit;

    // Base-unit conversion factor for stock ledger rows — same logic used in
    // stock-position.php's "Show in Base Units" mode. Without this, qty_in/qty_out
    // from rows recorded in a non-base unit (Carton, Dozen, etc.) get summed as-is,
    // producing a wrong current_stock (e.g. 6 instead of the correct base-unit 74).
    $stockConversionFactor = "COALESCE(CASE WHEN slu.base_unit_id IS NOT NULL AND slu.unit_scope = 'universal' THEN slu.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor ELSE 1 END, 1)";

   $sql = "
    SELECT p.id, p.code, p.name, p.product_type, p.mrp, p.trade_price, p.wholesale_price, p.is_active,
           p.parent_product_id, p.qr_code, p.barcode, p.company_id, p.uom_type,
p.purchase_price, lb.batch_no, lb.expiry_date AS batch_expiry_date,
           c.category_name, sc.subcategory_name, comp.company_name, s.supplier_name,
           u.uom_name as default_unit_name, ug.group_name as uom_group_name,
           COALESCE(ROUND(SUM(sl.qty_in * {$stockConversionFactor}) - SUM(sl.qty_out * {$stockConversionFactor}), 2), 0) as current_stock,
           CASE WHEN COUNT(sl.id) = 0 THEN 1 ELSE 0 END as no_stock_records,
           curr.symbol as currency_symbol
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
    LEFT JOIN companies comp ON p.company_id = comp.id
    LEFT JOIN suppliers s ON p.vendor_id = s.id
    LEFT JOIN uom u ON p.default_unit_id = u.id
    LEFT JOIN uom_groups ug ON p.uom_group_id = ug.id
    LEFT JOIN stock_ledger sl ON p.id = sl.product_id AND sl.tenant_id = p.tenant_id
    LEFT JOIN uom slu ON sl.unit_id = slu.id
    LEFT JOIN product_uom_conversions puc ON p.id = puc.product_id AND sl.unit_id = puc.uom_id
    LEFT JOIN tenant_currencies tc ON p.tenant_id = tc.tenant_id AND tc.is_base_currency = 1
    LEFT JOIN ledgerone_public.currencies curr ON tc.currency_id = curr.id
  LEFT JOIN (
    SELECT so1.product_id, so1.batch_no, so1.expiry_date
    FROM stock_opening so1
    INNER JOIN (
        SELECT product_id, MAX(id) AS max_id FROM stock_opening GROUP BY product_id
    ) so2 ON so1.product_id = so2.product_id AND so1.id = so2.max_id
) lb ON lb.product_id = p.id
    WHERE p.tenant_id = ?
";
    
    $params = [$tenant_id];
    
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($category) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category;
    }
    
    if ($type) {
        $sql .= " AND p.product_type = ?";
        $params[] = $type;
    }
    
    if ($status) {
        $active = ($status === 'active') ? 1 : 0;
        $sql .= " AND p.is_active = ?";
        $params[] = $active;
    }
    
    if ($uom_filter) {
        $sql .= " AND p.uom_type = ?";
        $params[] = $uom_filter;
    }
    
    if ($vendor) {
        $sql .= " AND p.vendor_id = ?";
        $params[] = $vendor;
    }
    
    $sql .= " GROUP BY p.id ORDER BY COALESCE(p.parent_product_id, p.id), p.parent_product_id IS NOT NULL, p.created_at DESC";
    
    // Get total count
    $countSql = "SELECT COUNT(DISTINCT p.id) as total FROM products p
                 LEFT JOIN categories c ON p.category_id = c.id
                 LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
                 WHERE p.tenant_id = ?";
    
    $countParams = [$tenant_id];
    if ($search) {
        $countSql .= " AND (p.name LIKE ? OR p.code LIKE ?)";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
    }
    if ($category) {
        $countSql .= " AND p.category_id = ?";
        $countParams[] = $category;
    }
    if ($type) {
        $countSql .= " AND p.product_type = ?";
        $countParams[] = $type;
    }
    if ($status) {
        $active = ($status === 'active') ? 1 : 0;
        $countSql .= " AND p.is_active = ?";
        $countParams[] = $active;
    }
    if ($uom_filter) {
        $countSql .= " AND p.uom_type = ?";
        $countParams[] = $uom_filter;
    }
    if ($vendor) {
        $countSql .= " AND p.vendor_id = ?";
        $countParams[] = $vendor;
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalRecords = $countStmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Add pagination to main query
    $sql .= " LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'products' => $products,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'per_page' => $limit
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}