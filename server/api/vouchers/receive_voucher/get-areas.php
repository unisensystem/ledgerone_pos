<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $city_zone_id = $_GET['city_zone_id'] ?? '';

    $sql = "
        SELECT id, area_name
        FROM areas
        WHERE tenant_id = ?
    ";
    $params = [$tenant_id];

    if ($city_zone_id) {
        $sql .= " AND city_zone_id = ?";
        $params[] = $city_zone_id;
    }

    $sql .= " ORDER BY area_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $areas = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $areas
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}