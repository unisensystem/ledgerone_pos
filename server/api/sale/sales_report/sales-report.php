<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? 1;
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'all';
$company_id = $_GET['company_id'] ?? null;

try {
    // Summary Cards
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COALESCE(SUM(sdu.total_revenue), 0) FROM station_daily_usage sdu WHERE sdu.tenant_id = ? AND sdu.usage_date BETWEEN ? AND ?) +
            (SELECT COALESCE(SUM(sii.net_amount), 0) 
             FROM sale_invoice_items sii
             JOIN sale_invoice si ON sii.sale_invoice_id = si.id
             JOIN products p ON sii.product_id = p.id
             WHERE sii.tenant_id = ? AND si.sale_date BETWEEN ? AND ?" . ($company_id ? " AND si.company_id = ?" : "") . "
                AND (p.subcategory_id IS NULL OR p.subcategory_id NOT IN (12, 13, 14))
            ) as total_sales
    ");
    $params = [$tenant_id, $date_from, $date_to, $tenant_id, $date_from, $date_to];
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $summary = ['total_sales' => $stmt->fetchColumn()];


    // Sales Officer Wise Sales
    $stmt = $pdo->prepare("
        SELECT 
            si.sale_officer_id,
            CONCAT('SO-', LPAD(si.sale_officer_id, 3, '0')) as officer_id,
            COALESCE(e.full_name, 'Unknown') as officer_name,
            COALESCE(e.employee_id, '-') as employee_id,
            COUNT(DISTINCT si.id) as total_invoices,
            SUM(si.net_amount) as total_sales,
            'Active' as status
        FROM sale_invoice si
        LEFT JOIN employees e ON si.sale_officer_id = e.id AND e.tenant_id = ?
        WHERE si.tenant_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'" . ($company_id ? " AND si.company_id = ?" : "") . "
        GROUP BY si.sale_officer_id, e.full_name, e.employee_id
        ORDER BY total_sales DESC
    ");
    $params = [$tenant_id, $tenant_id, $date_from, $date_to];
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $officer_sales = $stmt->fetchAll();

    // Product Wise Sales
    $stmt = $pdo->prepare("
        SELECT 
            current.product_id,
            CONCAT('PROD-', LPAD(current.product_id, 3, '0')) as product_id_display,
            COALESCE(p.name, 'Unknown') as product_name,
            COALESCE(pc.category_name, ppc.category_name, 'Uncategorized') as category,
            current.quantity_sold,
            current.revenue,
            CASE 
                WHEN previous.revenue > 0 THEN ROUND(((current.revenue - previous.revenue) / previous.revenue) * 100, 1)
                ELSE NULL
            END as growth,
            p.parent_product_id,
            current.parent_row_id
        FROM (
            SELECT sii.product_id, sii.parent_row_id, SUM(sii.quantity) as quantity_sold, SUM(sii.net_amount) as revenue
            FROM sale_invoice_items sii
            JOIN sale_invoice si ON sii.sale_invoice_id = si.id AND si.tenant_id = ?
            WHERE sii.tenant_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'" . ($company_id ? " AND si.company_id = ?" : "") . "
            GROUP BY sii.product_id, sii.parent_row_id
        ) current
        LEFT JOIN products p ON current.product_id = p.id
        LEFT JOIN categories pc ON p.category_id = pc.id
        LEFT JOIN products pp ON p.parent_product_id = pp.id
        LEFT JOIN categories ppc ON pp.category_id = ppc.id
        LEFT JOIN (
            SELECT sii.product_id, SUM(sii.net_amount) as revenue
            FROM sale_invoice_items sii
            JOIN sale_invoice si ON sii.sale_invoice_id = si.id AND si.tenant_id = ?
            WHERE sii.tenant_id = ? AND si.sale_date BETWEEN DATE_SUB(?, INTERVAL DATEDIFF(?, ?) DAY) AND DATE_SUB(?, INTERVAL 1 DAY) AND si.status = 'Posted'" . ($company_id ? " AND si.company_id = ?" : "") . "
            GROUP BY sii.product_id
        ) previous ON current.product_id = previous.product_id
        ORDER BY COALESCE(p.parent_product_id, current.product_id), p.parent_product_id IS NULL DESC, current.revenue DESC
    ");
    $params = [$tenant_id, $tenant_id, $date_from, $date_to];
    if ($company_id) $params[] = $company_id;
    $params = array_merge($params, [$tenant_id, $tenant_id, $date_from, $date_to, $date_from, $date_from]);
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $product_sales = $stmt->fetchAll();

    // Sales Trend (Last 6 Months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(si.sale_date, '%b') as month,
            SUM(si.net_amount) as total
        FROM sale_invoice si
        WHERE si.tenant_id = ? AND si.sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND si.status = 'Posted'" . ($company_id ? " AND si.company_id = ?" : "") . "
        GROUP BY DATE_FORMAT(si.sale_date, '%Y-%m')
        ORDER BY si.sale_date
    ");
    $params = [$tenant_id];
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $trend = $stmt->fetchAll();

    // Category Distribution
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(pc.category_name, 'Uncategorized') as category,
            SUM(sii.quantity) as total_volume,
            SUM(sii.net_amount) as total
        FROM sale_invoice_items sii
        JOIN sale_invoice si ON sii.sale_invoice_id = si.id AND si.tenant_id = ?
        LEFT JOIN products p ON sii.product_id = p.id
        LEFT JOIN categories pc ON p.category_id = pc.id
        WHERE sii.tenant_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'" . ($company_id ? " AND si.company_id = ?" : "") . "
        GROUP BY pc.category_name
    ");
    $params = [$tenant_id, $tenant_id, $date_from, $date_to];
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $categories = $stmt->fetchAll();

    // Branch Wise Sales
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(b.parent_branch_id, si.branch_id) as branch_id,
            CONCAT('BR-', LPAD(COALESCE(b.parent_branch_id, si.branch_id), 3, '0')) as branch_id_display,
            COALESCE(pb.branch_name, b.branch_name, 'Unknown') as branch_name,
            SUM(sii.quantity) as total_volume,
            SUM(si.net_amount) as total_sales,
            COUNT(DISTINCT si.id) as transactions,
            CASE 
                WHEN COUNT(DISTINCT si.id) > 0 THEN SUM(si.net_amount) / COUNT(DISTINCT si.id)
                ELSE 0
            END as avg_sale
        FROM sale_invoice si
        JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id AND sii.tenant_id = ?
        LEFT JOIN branches b ON si.branch_id = b.id
        LEFT JOIN branches pb ON b.parent_branch_id = pb.id
        WHERE si.tenant_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'" . ($company_id ? " AND si.company_id = ?" : "") . "
        GROUP BY COALESCE(b.parent_branch_id, si.branch_id), COALESCE(pb.branch_name, b.branch_name)
        ORDER BY total_sales DESC
    ");
    $params = [$tenant_id, $tenant_id, $date_from, $date_to];
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $branch_sales = $stmt->fetchAll();

    // Territory Wise Sales
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(co.country_name, 'Unknown') as country,
            COALESCE(r.region_name, '-') as region,
            COALESCE(ci.city_name, '-') as city,
            COALESCE(cz.city_zone_name, '-') as zone,
            COALESCE(a.area_name, '-') as area,
            COUNT(DISTINCT si.id) as total_invoices,
            SUM(si.net_amount) as total_sales
        FROM sale_invoice si
        LEFT JOIN customers c ON si.customer_id = c.id AND c.tenant_id = ?
        LEFT JOIN countries co ON c.country_id = co.id
        LEFT JOIN regions r ON c.region_id = r.id
        LEFT JOIN cities ci ON c.city_id = ci.id
        LEFT JOIN city_zones cz ON c.city_zone_id = cz.id
        LEFT JOIN areas a ON c.area_id = a.id
        WHERE si.tenant_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'" . ($company_id ? " AND si.company_id = ?" : "") . "
        GROUP BY co.country_name, r.region_name, ci.city_name, cz.city_zone_name, a.area_name
        ORDER BY total_sales DESC
    ");
    $params = [$tenant_id, $tenant_id, $date_from, $date_to];
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $territory_sales = $stmt->fetchAll();

    // Invoice Wise Sales
    $stmt = $pdo->prepare("
        SELECT 
            si.id,
            si.bill_no,
            si.sale_date,
            COALESCE(c.customer_name, 'Walk-in') as customer_name,
            COALESCE(e.full_name, '-') as officer_name,
            si.total_bill,
            si.total_discount_amount,
            si.net_amount,
            si.status
        FROM sale_invoice si
        LEFT JOIN customers c ON si.customer_id = c.id AND c.tenant_id = ?
        LEFT JOIN employees e ON si.sale_officer_id = e.id AND e.tenant_id = ?
        WHERE si.tenant_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'" . ($company_id ? " AND si.company_id = ?" : "") . "
        ORDER BY si.sale_date DESC, si.id DESC
    ");
    $params = [$tenant_id, $tenant_id, $tenant_id, $date_from, $date_to];
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $invoice_sales = $stmt->fetchAll();

    // Vendor Wise Sales
    $stmt = $pdo->prepare("
        SELECT 
            p.vendor_id,
            CONCAT('VEN-', LPAD(p.vendor_id, 3, '0')) as vendor_id_display,
            COALESCE(s.supplier_name, 'Unknown') as vendor_name,
            COUNT(DISTINCT si.id) as total_invoices,
            SUM(sii.quantity) as total_quantity,
            SUM(sii.net_amount) as total_sales
        FROM sale_invoice_items sii
        JOIN sale_invoice si ON sii.sale_invoice_id = si.id AND si.tenant_id = ?
        JOIN products p ON sii.product_id = p.id AND p.tenant_id = ?
        LEFT JOIN suppliers s ON p.vendor_id = s.id AND s.tenant_id = ?
        WHERE sii.tenant_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'" . ($company_id ? " AND si.company_id = ?" : "") . "
        GROUP BY p.vendor_id, s.supplier_name
        ORDER BY total_sales DESC
    ");
    $params = [$tenant_id, $tenant_id, $tenant_id, $tenant_id, $date_from, $date_to];
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $vendor_sales = $stmt->fetchAll();

    // Customer Wise Sales
    $stmt = $pdo->prepare("
        SELECT 
            si.customer_id,
            CONCAT('CUST-', LPAD(si.customer_id, 3, '0')) as customer_id_display,
            COALESCE(c.customer_name, 'Walk-in') as customer_name,
            COUNT(DISTINCT si.id) as total_invoices,
            SUM(si.net_amount) as total_sales
        FROM sale_invoice si
        LEFT JOIN customers c ON si.customer_id = c.id AND c.tenant_id = ?
        WHERE si.tenant_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'" . ($company_id ? " AND si.company_id = ?" : "") . "
        GROUP BY si.customer_id, c.customer_name
        ORDER BY total_sales DESC
    ");
    $params = [$tenant_id, $tenant_id, $date_from, $date_to];
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $customer_sales = $stmt->fetchAll();

    // Supplier Man Wise Sales
    $stmt = $pdo->prepare("
        SELECT 
            si.supplier_man_id,
            CONCAT('SM-', LPAD(si.supplier_man_id, 3, '0')) as supplier_man_id_display,
            COALESCE(e.full_name, 'Unknown') as supplier_man_name,
            COALESCE(e.employee_id, '-') as employee_id,
            COUNT(DISTINCT si.id) as total_invoices,
            SUM(si.net_amount) as total_sales,
            'Active' as status
        FROM sale_invoice si
        LEFT JOIN employees e ON si.supplier_man_id = e.id AND e.tenant_id = ?
        WHERE si.tenant_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted' AND si.supplier_man_id IS NOT NULL" . ($company_id ? " AND si.company_id = ?" : "") . "
        GROUP BY si.supplier_man_id, e.full_name, e.employee_id
        ORDER BY total_sales DESC
    ");
    $params = [$tenant_id, $tenant_id, $date_from, $date_to];
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $supplier_man_sales = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'summary' => $summary,
            'officer_sales' => $officer_sales,
            'product_sales' => $product_sales,
            'branch_sales' => $branch_sales,
            'territory_sales' => $territory_sales,
            'invoice_sales' => $invoice_sales,
            'vendor_sales' => $vendor_sales,
            'customer_sales' => $customer_sales,
            'supplier_man_sales' => $supplier_man_sales,
            'trend' => $trend,
            'categories' => $categories
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>