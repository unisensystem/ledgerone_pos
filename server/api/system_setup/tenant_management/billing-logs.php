<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

try {
    require_once '../../../../includes/connection.php';
    
    $stmt = $pdo->query("SELECT b.*, t.business_name as tenant_name 
            FROM fuelingsys_public.billing_logs b 
            JOIN fuelingsys_public.tenants t ON b.tenant_id = t.id 
            ORDER BY b.created_at DESC");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $logs]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}