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
        $stmt = $pdo->prepare("
            SELECT 
                si.sale_date as date,
                COUNT(si.id) as invoices,
                SUM(si.net_amount) as revenue
            FROM sale_invoice si
            WHERE si.tenant_id = ? AND si.sale_officer_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'
            GROUP BY si.sale_date
            ORDER BY si.sale_date DESC
        ");
        $stmt->execute([$tenant_id, $id, $date_from, $date_to]);
    } elseif ($type === 'supplier_man') {
        $stmt = $pdo->prepare("
            SELECT 
                si.sale_date as date,
                COUNT(si.id) as invoices,
                SUM(si.net_amount) as revenue
            FROM sale_invoice si
            WHERE si.tenant_id = ? AND si.supplier_man_id = ? AND si.sale_date BETWEEN ? AND ? AND si.status = 'Posted'
            GROUP BY si.sale_date
            ORDER BY si.sale_date DESC
        ");
        $stmt->execute([$tenant_id, $id, $date_from, $date_to]);
    } elseif ($type === 'product') {
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
        $stmt = $pdo->prepare("
            SELECT 
                si.sale_date as date,
                SUM(sii.quantity) as volume,
                'Units' as unit,
                SUM(si.net_amount) as revenue
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
