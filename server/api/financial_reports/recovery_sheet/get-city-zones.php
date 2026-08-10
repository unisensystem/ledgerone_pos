<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
$city_id = $_GET['city_id'] ?? null;

if (!$tenant_id || !$city_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, city_zone_name FROM city_zones WHERE tenant_id = ? AND city_id = ? ORDER BY city_zone_name");
    $stmt->execute([$tenant_id, $city_id]);
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $zones]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
