<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $data = [
        'countries' => [],
        'regions' => [],
        'cities' => [],
        'zones' => [],
        'areas' => []
    ];

    // Get countries
    $stmt = $pdo->prepare("SELECT id, country_name FROM countries WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    while ($row = $stmt->fetch()) {
        $data['countries'][] = ['id' => $row['id'], 'name' => $row['country_name'], 'code' => ''];
    }

    // Get regions
    $stmt = $pdo->prepare("SELECT id, country_id, region_name FROM regions WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    while ($row = $stmt->fetch()) {
        $data['regions'][] = ['id' => $row['id'], 'name' => $row['region_name'], 'countryId' => $row['country_id']];
    }

    // Get cities
    $stmt = $pdo->prepare("SELECT id, region_id, city_name FROM cities WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    while ($row = $stmt->fetch()) {
        $data['cities'][] = ['id' => $row['id'], 'name' => $row['city_name'], 'regionId' => $row['region_id']];
    }

    // Get zones
    $stmt = $pdo->prepare("SELECT id, city_id, city_zone_name FROM city_zones WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    while ($row = $stmt->fetch()) {
        $data['zones'][] = ['id' => $row['id'], 'name' => $row['city_zone_name'], 'cityId' => $row['city_id']];
    }

    // Get areas
    $stmt = $pdo->prepare("SELECT id, city_zone_id, area_name FROM areas WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    while ($row = $stmt->fetch()) {
        $data['areas'][] = ['id' => $row['id'], 'name' => $row['area_name'], 'zoneId' => $row['city_zone_id']];
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}