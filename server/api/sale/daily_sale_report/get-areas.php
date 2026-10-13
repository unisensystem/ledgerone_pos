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
    $city_zone_ids = [];
    if (isset($_GET['city_zone_ids'])) {
        $city_zone_ids = array_filter(array_map('intval', (array)$_GET['city_zone_ids']));
    } elseif (!empty($_GET['city_zone_id'])) {
        $city_zone_ids = [intval($_GET['city_zone_id'])];
    }

    $city_ids = [];
    if (isset($_GET['city_ids'])) {
        $city_ids = array_filter(array_map('intval', (array)$_GET['city_ids']));
    } elseif (!empty($_GET['city_id'])) {
        $city_ids = [intval($_GET['city_id'])];
    }

    $sql = "SELECT DISTINCT a.id, a.area_name
            FROM areas a
            LEFT JOIN city_zones cz ON a.city_zone_id = cz.id
            WHERE a.tenant_id = ?";
    $params = [$tenant_id];

    if (!empty($city_zone_ids)) {
        $placeholders = implode(',', array_fill(0, count($city_zone_ids), '?'));
        $sql .= " AND a.city_zone_id IN ($placeholders)";
        $params = array_merge($params, $city_zone_ids);
    } elseif (!empty($city_ids)) {
        $placeholders = implode(',', array_fill(0, count($city_ids), '?'));
        $sql .= " AND cz.city_id IN ($placeholders)";
        $params = array_merge($params, $city_ids);
    }

    $sql .= " ORDER BY a.area_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'areas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
