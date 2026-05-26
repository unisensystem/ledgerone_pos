<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $city_ids = [];
    if (isset($_GET['city_ids'])) {
        $city_ids = array_filter(array_map('intval', (array)$_GET['city_ids']));
    } elseif (!empty($_GET['city_id'])) {
        $city_ids = [intval($_GET['city_id'])];
    }

    $sql = "SELECT id, city_zone_name FROM city_zones WHERE tenant_id = ?";
    $params = [$tenant_id];

    if (!empty($city_ids)) {
        $placeholders = implode(',', array_fill(0, count($city_ids), '?'));
        $sql .= " AND city_id IN ($placeholders)";
        $params = array_merge($params, $city_ids);
    }

    $sql .= " ORDER BY city_zone_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'city_zones' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
