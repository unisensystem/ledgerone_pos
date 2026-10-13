<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$city_zone_id = $_GET['city_zone_id'] ?? null;

if (!$tenant_id || !$city_zone_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT cz.city_id, c.region_id, r.country_id
        FROM city_zones cz
        JOIN cities c ON cz.city_id = c.id
        JOIN regions r ON c.region_id = r.id
        WHERE cz.id = ? AND cz.tenant_id = ?
    ");
    $stmt->execute([$city_zone_id, $tenant_id]);
    $zone = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($zone) {
        echo json_encode(['success' => true, 'hierarchy' => [
            'country_id' => $zone['country_id'],
            'region_id' => $zone['region_id'],
            'city_id' => $zone['city_id'],
            'city_zone_id' => $city_zone_id
        ]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'City zone not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
