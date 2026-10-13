<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    $basis = $_GET['basis'] ?? 'qty';
    
    if (!$from || !$to) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Period dates required']);
        exit;
    }
    
    // Get all production orders in the period
    $stmt = $pdo->prepare("
        SELECT 
            po.id,
            po.order_no,
            po.order_qty,
            po.start_date,
            po.end_date,
            p.name as product_name,
            u.uom_name
        FROM production_orders po
        INNER JOIN products p ON po.product_id = p.id
        LEFT JOIN uom u ON p.default_unit_id = u.id
        WHERE po.tenant_id = ?
        AND po.status IN ('Completed', 'In Progress', 'Closed')
        AND (
            (po.start_date <= ? AND po.end_date >= ?)
            OR (po.start_date BETWEEN ? AND ?)
            OR (po.end_date BETWEEN ? AND ?)
        )
        ORDER BY po.start_date DESC
    ");
    
    $stmt->execute([$tenant_id, $to, $from, $from, $to, $from, $to]);
    $batches = [];
    
    while ($row = $stmt->fetch()) {
        $allocation_base = 0;
        
        if ($basis === 'qty') {
            $allocation_base = $row['order_qty'];
        } elseif ($basis === 'hours') {
            // Estimate hours from production order dates
            $days = 0;
            if ($row['end_date'] && $row['start_date']) {
                $start = new DateTime($row['start_date']);
                $end = new DateTime($row['end_date']);
                $days = $start->diff($end)->days;
            }
            $allocation_base = max(1, $days * 8); // 8 hours per day
        } else {
            $allocation_base = 1;
        }
        
        $batches[] = [
            'id' => $row['id'],
            'order_no' => $row['order_no'],
            'product_name' => $row['product_name'],
            'qty' => $row['order_qty'],
            'uom' => $row['uom_name'] ?? 'Units',
            'start_date' => $row['start_date'],
            'allocation_base' => $allocation_base
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $batches]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
