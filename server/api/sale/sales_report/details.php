<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? 1;
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? 0;
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

try {
    if ($type === 'product_invoices') {
        // Get invoices for a specific product
        // Row-level (one sale_invoice_items row per line), so batch_no / expiry_date
        // are read directly off sii - no aggregation needed here.
        // sale_invoice_items only has "batch_expiry_date" column (no plain
        // "expiry_date"), so alias it back to expiry_date for the frontend.
        $stmt = $pdo->prepare("
            SELECT 
                si.bill_no,
                si.sale_date,
                COALESCE(c.customer_name, 'Walk-in') as customer_name,
                sii.quantity,
                sii.sale_price,
                sii.batch_no,
                sii.batch_expiry_date as expiry_date,
                sii.net_amount
            FROM sale_invoice_items sii
            JOIN sale_invoice si ON sii.sale_invoice_id = si.id AND si.tenant_id = ?
            LEFT JOIN customers c ON si.customer_id = c.id AND c.tenant_id = ?
            WHERE sii.tenant_id = ? AND sii.product_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'
            ORDER BY si.sale_date DESC
        ");
        $stmt->execute([$tenant_id, $tenant_id, $tenant_id, $id, $date_from, $date_to]);
    } elseif ($type === 'vendor_products') {
        // Get products sold for a specific vendor
        // sale_invoice_items is already joined and grouped per product, so
        // batch/expiry are aggregated the same way as in sales-report.php.
        $stmt = $pdo->prepare("
            SELECT 
                p.name as product_name,
                SUM(sii.quantity) as total_quantity,
                SUM(sii.net_amount) as total_sales,
                GROUP_CONCAT(DISTINCT NULLIF(sii.batch_no,'') SEPARATOR ', ') as batch_no,
                MIN(CASE WHEN sii.batch_expiry_date >= CURDATE() THEN sii.batch_expiry_date END) as nearest_expiry
            FROM sale_invoice_items sii
            JOIN sale_invoice si ON sii.sale_invoice_id = si.id AND si.tenant_id = ?
            JOIN products p ON sii.product_id = p.id AND p.tenant_id = ?
            WHERE sii.tenant_id = ? AND p.vendor_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'
            GROUP BY p.id, p.name
            ORDER BY total_sales DESC
        ");
        $stmt->execute([$tenant_id, $tenant_id, $tenant_id, $id, $date_from, $date_to]);
    } elseif ($type === 'customer_invoices') {
        // Get invoices for a specific customer
        // No item-level join here, so use the "ib" invoice-batch subquery
        // (same pattern as Customer Wise Sales in sales-report.php).
        $stmt = $pdo->prepare("
            SELECT 
                si.bill_no,
                si.sale_date,
                si.total_bill,
                si.total_discount_amount,
                si.net_amount,
                ib.batch_no,
                ib.nearest_expiry
            FROM sale_invoice si
            LEFT JOIN (
                SELECT sii.sale_invoice_id,
                       GROUP_CONCAT(DISTINCT NULLIF(sii.batch_no,'') SEPARATOR ', ') as batch_no,
                       MIN(CASE WHEN sii.batch_expiry_date >= CURDATE() THEN sii.batch_expiry_date END) as nearest_expiry
                FROM sale_invoice_items sii
                WHERE sii.tenant_id = ?
                GROUP BY sii.sale_invoice_id
            ) ib ON ib.sale_invoice_id = si.id
            WHERE si.tenant_id = ? AND si.customer_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'
            ORDER BY si.sale_date DESC
        ");
        $stmt->execute([$tenant_id, $tenant_id, $id, $date_from, $date_to]);
    } elseif ($type === 'invoice_items') {
        // Get items for a specific invoice
        // Row-level, sii already joined for this exact invoice - read batch/expiry directly.
        $stmt = $pdo->prepare("
            SELECT 
                p.name as product_name,
                sii.quantity,
                sii.sale_price,
                sii.batch_no,
                sii.batch_expiry_date as expiry_date,
                sii.discount_amount,
                sii.net_amount
            FROM sale_invoice_items sii
            JOIN products p ON sii.product_id = p.id AND p.tenant_id = ?
            WHERE sii.tenant_id = ? AND sii.sale_invoice_id = ?
            ORDER BY sii.id
        ");
        $stmt->execute([$tenant_id, $tenant_id, $id]);
    } elseif ($type === 'territory_invoices') {
        // Get invoices for a specific territory
        // No item-level join here either, so use the "ib" invoice-batch subquery.
        $country = $_GET['country'] ?? '';
        $region = $_GET['region'] ?? '';
        $city = $_GET['city'] ?? '';
        $zone = $_GET['zone'] ?? '';
        $area = $_GET['area'] ?? '';
        
        $stmt = $pdo->prepare("
            SELECT 
                si.bill_no,
                si.sale_date,
                COALESCE(c.customer_name, 'Walk-in') as customer_name,
                si.net_amount,
                ib.batch_no,
                ib.nearest_expiry
            FROM sale_invoice si
            LEFT JOIN customers c ON si.customer_id = c.id AND c.tenant_id = ?
            LEFT JOIN countries co ON c.country_id = co.id
            LEFT JOIN regions r ON c.region_id = r.id
            LEFT JOIN cities ci ON c.city_id = ci.id
            LEFT JOIN city_zones cz ON c.city_zone_id = cz.id
            LEFT JOIN areas a ON c.area_id = a.id
            LEFT JOIN (
                SELECT sii.sale_invoice_id,
                       GROUP_CONCAT(DISTINCT NULLIF(sii.batch_no,'') SEPARATOR ', ') as batch_no,
                       MIN(CASE WHEN sii.batch_expiry_date >= CURDATE() THEN sii.batch_expiry_date END) as nearest_expiry
                FROM sale_invoice_items sii
                WHERE sii.tenant_id = ?
                GROUP BY sii.sale_invoice_id
            ) ib ON ib.sale_invoice_id = si.id
            WHERE si.tenant_id = ? 
                AND si.sale_date BETWEEN ? AND ? 
                AND si.status = 'Posted'
                AND COALESCE(co.country_name, 'Unknown') = ?
                AND COALESCE(r.region_name, '-') = ?
                AND COALESCE(ci.city_name, '-') = ?
                AND COALESCE(cz.city_zone_name, '-') = ?
                AND COALESCE(a.area_name, '-') = ?
            ORDER BY si.sale_date DESC
        ");
        $stmt->execute([$tenant_id, $tenant_id, $tenant_id, $date_from, $date_to, $country, $region, $city, $zone, $area]);
    } else {
        throw new Exception('Invalid type');
    }
    
    $details = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $details
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>