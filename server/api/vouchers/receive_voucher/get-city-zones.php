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
    $city_id = $_GET['city_id'] ?? '';

    $sql = "
        SELECT id, city_zone_name
        FROM city_zones
        WHERE tenant_id = ?
    ";
    $params = [$tenant_id];

    if ($city_id) {
        $sql .= " AND city_id = ?";
        $params[] = $city_id;
    }

    $sql .= " ORDER BY city_zone_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $zones = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $zones
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}