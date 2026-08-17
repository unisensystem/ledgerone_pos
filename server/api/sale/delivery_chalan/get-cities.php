<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Fetch all cities for this tenant
    $cityStmt = $pdo->prepare(
        "SELECT id, city_name FROM cities WHERE tenant_id = ? ORDER BY city_name"
    );
    $cityStmt->execute([$tenant_id]);
    $cities = $cityStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all areas joined with their city (via city_zones)
    $areaStmt = $pdo->prepare("
        SELECT a.id AS area_id, a.area_name, c.id AS city_id, c.city_name
        FROM areas a
        JOIN city_zones cz ON cz.id = a.city_zone_id AND cz.tenant_id = a.tenant_id
        JOIN cities c     ON c.id  = cz.city_id     AND c.tenant_id  = a.tenant_id
        WHERE a.tenant_id = ?
        ORDER BY c.city_name, a.area_name
    ");
    $areaStmt->execute([$tenant_id]);
    $areas = $areaStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'cities' => $cities, 'areas' => $areas]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
