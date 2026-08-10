<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
$city_zone_id = $_GET['city_zone_id'] ?? null;

if (!$tenant_id || !$city_zone_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, area_name FROM areas WHERE tenant_id = ? AND city_zone_id = ? ORDER BY area_name");
    $stmt->execute([$tenant_id, $city_zone_id]);
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $areas]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
