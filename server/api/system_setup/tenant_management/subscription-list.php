<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

try {
    require_once '../../../../includes/connection.php';
    
    $stmt = $pdo->query("SELECT s.*, t.business_name as tenant_name, p.name as plan_name 
            FROM fuelingsys_public.subscriptions s 
            JOIN fuelingsys_public.tenants t ON s.tenant_id = t.id 
            JOIN fuelingsys_public.plans p ON s.plan_id = p.id 
            ORDER BY s.created_at DESC");
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $subscriptions]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
