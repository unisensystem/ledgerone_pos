<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Exact same functions as daily-sale-report.php
function getIntArrayParam($arrayName, $singleName = null) {
    $values = [];
    if (isset($_GET[$arrayName])) {
        $values = array_filter(array_map('intval', (array)$_GET[$arrayName]));
    } elseif ($singleName && !empty($_GET[$singleName])) {
        $values = [intval($_GET[$singleName])];
    }
    return array_values(array_unique($values));
}

function getStringArrayParam($arrayName, $singleName = null) {
    $values = [];
    if (isset($_GET[$arrayName])) {
        $values = array_filter(array_map('trim', (array)$_GET[$arrayName]));
    } elseif ($singleName && !empty($_GET[$singleName])) {
        $values = [trim($_GET[$singleName])];
    }
    return array_values(array_unique($values));
}

function addInFilter(&$sql, &$params, $column, $values) {
    if (empty($values)) return;
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $sql .= " AND $column IN ($placeholders)";
    $params = array_merge($params, $values);
}

function addInvoiceTypeFilter(&$sql, &$params, $invoiceTypes) {
    if (empty($invoiceTypes)) return;
    $normalTypes = array_values(array_diff($invoiceTypes, ['OutStation']));
    $conditions = [];
    if (in_array('OutStation', $invoiceTypes, true)) {
        $conditions[] = "c.is_out_station = 1";
    }
    if (!empty($normalTypes)) {
        $placeholders = implode(',', array_fill(0, count($normalTypes), '?'));
        $conditions[] = "si.invoice_type IN ($placeholders)";
        $params = array_merge($params, $normalTypes);
    }
    if (!empty($conditions)) {
        $sql .= " AND (" . implode(' OR ', $conditions) . ")";
    }
}

function addVendorFilter(&$sql, &$params, $vendorIds) {
    if (empty($vendorIds)) return;
    $placeholders = implode(',', array_fill(0, count($vendorIds), '?'));
    $sql .= " AND EXISTS (
        SELECT 1 FROM sale_invoice_items sii_v
        JOIN products p_v ON sii_v.product_id = p_v.id
        WHERE sii_v.sale_invoice_id = si.id AND p_v.vendor_id IN ($placeholders)
    )";
    $params = array_merge($params, $vendorIds);
}

try {
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = max(1, (int)($_GET['limit'] ?? 10));
    $offset = ($page - 1) * $limit;

    // Exact same param names as daily-sale-report.php
    $date_from         = $_GET['date_from'] ?? null;
    $date_to           = $_GET['date_to']   ?? null;
    $search            = trim($_GET['search'] ?? '');
    $invoice_types     = getStringArrayParam('invoice_types', 'invoice_type');
    $sales_officer_ids = getIntArrayParam('sales_officer_ids', 'sales_officer_id');
    $supplier_man_ids  = getIntArrayParam('supplier_man_ids',  'supplier_man_id');
    $vendor_ids        = getIntArrayParam('vendor_ids',        'vendor_id');
    $company_ids       = getIntArrayParam('company_ids',       'company_id');
    $city_ids          = getIntArrayParam('city_ids',          'city_id');
    $city_zone_ids     = getIntArrayParam('city_zone_ids',     'city_zone_id');
    $area_ids          = getIntArrayParam('area_ids',          'area_id');
    $customer_ids      = getIntArrayParam('customer_ids',      'customer_id');
    $project_id        = !empty($_GET['project_id']) ? (int)$_GET['project_id'] : null;

    $sql = "SELECT si.id, si.bill_no, si.sale_date,
                   c.customer_name, co.company_name,
                   COUNT(DISTINCT sii.id) as item_count,
                   si.net_amount,
                   cur.symbol as currency_symbol,
                   u.full_name as created_by_name,
                   so.full_name as sales_officer_name,
                   CASE
                       WHEN dc_counts.dc_count IS NULL OR dc_counts.dc_count = 0 THEN 'No DC'
                       WHEN dc_counts.partial_count > 0 THEN 'Partial'
                       ELSE 'Fulfilled'
                   END as dc_status
            FROM sale_invoice si
            LEFT JOIN customers c   ON si.customer_id = c.id
            LEFT JOIN companies co  ON si.company_id  = co.id
            LEFT JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
            LEFT JOIN ledgerone_public.currencies cur ON si.currency_id = cur.id
            LEFT JOIN users u ON si.created_by = u.id
            LEFT JOIN employees so ON si.sale_officer_id = so.id
            LEFT JOIN (
                SELECT
                    sii2.sale_invoice_id,
                    COUNT(DISTINCT dc_hdr.id) as dc_count,
                    SUM(
                        CASE WHEN COALESCE(del.total_delivered, 0) < sii2.quantity THEN 1 ELSE 0 END
                    ) as partial_count
                FROM sale_invoice_items sii2
                LEFT JOIN (
                    SELECT dci.product_id, dci.uom_id, dc2.sale_invoice_id, dc2.tenant_id,
                           SUM(dci.quantity) as total_delivered
                    FROM delivery_chalan_items dci
                    JOIN delivery_chalan dc2 ON dci.delivery_chalan_id = dc2.id
                    GROUP BY dci.product_id, dci.uom_id, dc2.sale_invoice_id, dc2.tenant_id
                ) del ON del.product_id = sii2.product_id
                      AND del.uom_id = sii2.uom_id
                      AND del.sale_invoice_id = sii2.sale_invoice_id
                LEFT JOIN delivery_chalan dc_hdr ON dc_hdr.sale_invoice_id = sii2.sale_invoice_id
                GROUP BY sii2.sale_invoice_id
            ) dc_counts ON dc_counts.sale_invoice_id = si.id
            WHERE si.tenant_id = ? AND si.status = 'Posted' AND si.invoice_source = 'trade'";

    $params = [$tenant_id];

    if ($date_from) { $sql .= " AND si.sale_date >= ?"; $params[] = $date_from; }
    if ($date_to)   { $sql .= " AND si.sale_date <= ?"; $params[] = $date_to; }
    if ($search !== '') {
        $sql .= " AND (si.bill_no LIKE ? OR c.customer_name LIKE ? OR co.company_name LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }

    addInFilter($sql, $params, 'si.sale_officer_id', $sales_officer_ids);
    addInFilter($sql, $params, 'si.supplier_man_id',  $supplier_man_ids);
    addInFilter($sql, $params, 'si.company_id',       $company_ids);
    addInFilter($sql, $params, 'si.customer_id',      $customer_ids);
    addInFilter($sql, $params, 'c.city_id',           $city_ids);
    addInFilter($sql, $params, 'c.city_zone_id',      $city_zone_ids);
    addInFilter($sql, $params, 'c.area_id',           $area_ids);
    addInvoiceTypeFilter($sql, $params, $invoice_types);
    addVendorFilter($sql, $params, $vendor_ids);

    if ($project_id) { $sql .= " AND si.project_id = ?"; $params[] = $project_id; }

    // Count query
    $countSql = "SELECT COUNT(DISTINCT si.id)
                 FROM sale_invoice si
                 LEFT JOIN customers c  ON si.customer_id = c.id
                 LEFT JOIN companies co ON si.company_id  = co.id
                 WHERE si.tenant_id = ? AND si.status = 'Posted' AND si.invoice_source = 'trade'";
    $countParams = [$tenant_id];

    if ($date_from) { $countSql .= " AND si.sale_date >= ?"; $countParams[] = $date_from; }
    if ($date_to)   { $countSql .= " AND si.sale_date <= ?"; $countParams[] = $date_to; }
    if ($search !== '') {
        $countSql .= " AND (si.bill_no LIKE ? OR c.customer_name LIKE ? OR co.company_name LIKE ?)";
        $countParams[] = "%$search%"; $countParams[] = "%$search%"; $countParams[] = "%$search%";
    }
    addInFilter($countSql, $countParams, 'si.sale_officer_id', $sales_officer_ids);
    addInFilter($countSql, $countParams, 'si.supplier_man_id',  $supplier_man_ids);
    addInFilter($countSql, $countParams, 'si.company_id',       $company_ids);
    addInFilter($countSql, $countParams, 'si.customer_id',      $customer_ids);
    addInFilter($countSql, $countParams, 'c.city_id',           $city_ids);
    addInFilter($countSql, $countParams, 'c.city_zone_id',      $city_zone_ids);
    addInFilter($countSql, $countParams, 'c.area_id',           $area_ids);
    addInvoiceTypeFilter($countSql, $countParams, $invoice_types);
    addVendorFilter($countSql, $countParams, $vendor_ids);
    if ($project_id) { $countSql .= " AND si.project_id = ?"; $countParams[] = $project_id; }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = (int)$countStmt->fetchColumn();

    $sql .= " GROUP BY si.id ORDER BY si.sale_date DESC, si.id DESC LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'    => true,
        'invoices'   => $invoices,
        'pagination' => [
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int)ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
