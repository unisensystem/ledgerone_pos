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
    if ($type === 'officer') {
        // No item-level join here, so use the "ib" invoice-batch subquery
        // (same pattern as Sales Officer Wise Sales in sales-report.php), then
        // re-aggregate across invoices that share the same sale_date.
        // sale_invoice_items only has "batch_expiry_date" (no plain "expiry_date").
        $stmt = $pdo->prepare("
            SELECT 
                si.sale_date as date,
                COUNT(si.id) as invoices,
                SUM(si.net_amount) as revenue,
                GROUP_CONCAT(DISTINCT ib.batch_no SEPARATOR ', ') as batch_no,
                MIN(ib.nearest_batch_expiry) as nearest_batch_expiry
            FROM sale_invoice si
            LEFT JOIN (
                SELECT sii.sale_invoice_id,
                       GROUP_CONCAT(DISTINCT NULLIF(sii.batch_no,'') SEPARATOR ', ') as batch_no,
                       MIN(CASE WHEN sii.batch_expiry_date >= CURDATE() THEN sii.batch_expiry_date END) as nearest_batch_expiry
                FROM sale_invoice_items sii
                WHERE sii.tenant_id = ?
                GROUP BY sii.sale_invoice_id
            ) ib ON ib.sale_invoice_id = si.id
            WHERE si.tenant_id = ? AND si.sale_officer_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'
            GROUP BY si.sale_date
            ORDER BY si.sale_date DESC
        ");
        $stmt->execute([$tenant_id, $tenant_id, $id, $date_from, $date_to]);
    } elseif ($type === 'supplier_man') {
        // Same pattern as officer above.
        $stmt = $pdo->prepare("
            SELECT 
                si.sale_date as date,
                COUNT(si.id) as invoices,
                SUM(si.net_amount) as revenue,
                GROUP_CONCAT(DISTINCT ib.batch_no SEPARATOR ', ') as batch_no,
                MIN(ib.nearest_batch_expiry) as nearest_batch_expiry
            FROM sale_invoice si
            LEFT JOIN (
                SELECT sii.sale_invoice_id,
                       GROUP_CONCAT(DISTINCT NULLIF(sii.batch_no,'') SEPARATOR ', ') as batch_no,
                       MIN(CASE WHEN sii.batch_expiry_date >= CURDATE() THEN sii.batch_expiry_date END) as nearest_batch_expiry
                FROM sale_invoice_items sii
                WHERE sii.tenant_id = ?
                GROUP BY sii.sale_invoice_id
            ) ib ON ib.sale_invoice_id = si.id
            WHERE si.tenant_id = ? AND si.supplier_man_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'
            GROUP BY si.sale_date
            ORDER BY si.sale_date DESC
        ");
        $stmt->execute([$tenant_id, $tenant_id, $id, $date_from, $date_to]);
    } elseif ($type === 'product') {
        // Product breakdown table has no Batch/Expiry columns on the frontend,
        // so left as-is (unchanged).
        $stmt = $pdo->prepare("
            SELECT 
                si.sale_date as date,
                SUM(sii.quantity) as volume,
                SUM(sii.net_amount) as revenue
            FROM sale_invoice_items sii
            JOIN sale_invoice si ON sii.sale_invoice_id = si.id AND si.tenant_id = ?
            WHERE sii.tenant_id = ? AND sii.product_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'
            GROUP BY si.sale_date
            ORDER BY si.sale_date DESC
        ");
        $stmt->execute([$tenant_id, $tenant_id, $id, $date_from, $date_to]);
    } elseif ($type === 'branch') {
        // sale_invoice_items is already joined here, so batch/expiry can be
        // aggregated directly per date - no extra params needed.
        $stmt = $pdo->prepare("
            SELECT 
                si.sale_date as date,
                SUM(sii.quantity) as volume,
                'Units' as unit,
                SUM(si.net_amount) as revenue,
                GROUP_CONCAT(DISTINCT NULLIF(sii.batch_no,'') SEPARATOR ', ') as batch_no,
                MIN(CASE WHEN sii.batch_expiry_date >= CURDATE() THEN sii.batch_expiry_date END) as nearest_batch_expiry
            FROM sale_invoice si
            JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id AND sii.tenant_id = ?
            LEFT JOIN branches b ON si.branch_id = b.id
            WHERE si.tenant_id = ? 
                AND COALESCE(b.parent_branch_id, si.branch_id) = ? 
                AND si.sale_date BETWEEN ? AND ?
                AND si.status = 'Posted'
            GROUP BY si.sale_date
            ORDER BY si.sale_date DESC
        ");
        $stmt->execute([$tenant_id, $tenant_id, $id, $date_from, $date_to]);
    } else {
        throw new Exception('Invalid type');
    }
    
    $breakdown = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $breakdown
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>