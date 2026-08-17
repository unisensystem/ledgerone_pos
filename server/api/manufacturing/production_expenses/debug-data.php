<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['error' => 'No tenant_id']);
    exit;
}

try {
    // Check completed_products
    $stmt1 = $pdo->prepare("SELECT COUNT(*) as count FROM completed_products cp 
                            INNER JOIN production_completions pc ON cp.production_completion_id = pc.id 
                            WHERE pc.tenant_id = ?");
    $stmt1->execute([$tenant_id]);
    $completed = $stmt1->fetch();
    
    // Check production_expenses
    $stmt2 = $pdo->prepare("SELECT COUNT(*) as count FROM production_expenses WHERE tenant_id = ?");
    $stmt2->execute([$tenant_id]);
    $expenses = $stmt2->fetch();
    
    // Check production_orders
    $stmt3 = $pdo->prepare("SELECT COUNT(*) as count FROM production_orders WHERE tenant_id = ?");
    $stmt3->execute([$tenant_id]);
    $orders = $stmt3->fetch();
    
    // Sample completed product
    $stmt4 = $pdo->prepare("SELECT cp.*, pc.complete_date, po.order_no, p.name 
                            FROM completed_products cp 
                            INNER JOIN production_completions pc ON cp.production_completion_id = pc.id 
                            INNER JOIN production_orders po ON pc.production_order_id = po.id
                            INNER JOIN products p ON cp.product_id = p.id
                            WHERE pc.tenant_id = ? LIMIT 1");
    $stmt4->execute([$tenant_id]);
    $sample = $stmt4->fetch();
    
    echo json_encode([
        'completed_products_count' => $completed['count'],
        'production_expenses_count' => $expenses['count'],
        'production_orders_count' => $orders['count'],
        'sample_completed_product' => $sample
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
