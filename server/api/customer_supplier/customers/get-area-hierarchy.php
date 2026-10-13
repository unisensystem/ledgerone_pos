<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$area_id = $_GET['area_id'] ?? null;

if (!$tenant_id || !$area_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id as area_id,
            a.area_name,
            a.city_zone_id,
            cz.city_zone_name,
            cz.city_id,
            c.city_name,
            c.region_id,
            r.region_name,
            r.country_id,
            co.country_name
        FROM areas a
        JOIN city_zones cz ON a.city_zone_id = cz.id
        JOIN cities c ON cz.city_id = c.id
        JOIN regions r ON c.region_id = r.id
        JOIN countries co ON r.country_id = co.id
        WHERE a.id = ? AND a.tenant_id = ?
    ");
    $stmt->execute([$area_id, $tenant_id]);
    $hierarchy = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($hierarchy) {
        echo json_encode(['success' => true, 'hierarchy' => $hierarchy]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Area not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
