<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
$region_id = $_GET['region_id'] ?? null;

if (!$tenant_id || !$region_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, city_name FROM cities WHERE tenant_id = ? AND region_id = ? ORDER BY city_name");
    $stmt->execute([$tenant_id, $region_id]);
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $cities]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
