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
        $stmt = $pdo->prepare("
            SELECT 
                si.bill_no,
                si.sale_date,
                COALESCE(c.customer_name, 'Walk-in') as customer_name,
                sii.quantity,
                sii.sale_price,
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
        $stmt = $pdo->prepare("
            SELECT 
                p.name as product_name,
                SUM(sii.quantity) as total_quantity,
                SUM(sii.net_amount) as total_sales
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
        $stmt = $pdo->prepare("
            SELECT 
                si.bill_no,
                si.sale_date,
                si.total_bill,
                si.total_discount_amount,
                si.net_amount
            FROM sale_invoice si
            WHERE si.tenant_id = ? AND si.customer_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'
            ORDER BY si.sale_date DESC
        ");
        $stmt->execute([$tenant_id, $id, $date_from, $date_to]);
    } elseif ($type === 'invoice_items') {
        // Get items for a specific invoice
        $stmt = $pdo->prepare("
            SELECT 
                p.name as product_name,
                sii.quantity,
                sii.sale_price,
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
                si.net_amount
            FROM sale_invoice si
            LEFT JOIN customers c ON si.customer_id = c.id AND c.tenant_id = ?
            LEFT JOIN countries co ON c.country_id = co.id
            LEFT JOIN regions r ON c.region_id = r.id
            LEFT JOIN cities ci ON c.city_id = ci.id
            LEFT JOIN city_zones cz ON c.city_zone_id = cz.id
            LEFT JOIN areas a ON c.area_id = a.id
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
        $stmt->execute([$tenant_id, $tenant_id, $date_from, $date_to, $country, $region, $city, $zone, $area]);
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
