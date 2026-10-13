<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

try {
    require_once '../../../../includes/connection.php';
    
    $stmt = $pdo->query("SELECT * FROM fuelingsys_public.plans WHERE is_active = 1 ORDER BY sort_order ASC");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $plans]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
