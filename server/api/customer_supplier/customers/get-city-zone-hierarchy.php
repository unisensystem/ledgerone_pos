<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$city_id = $_GET['city_id'] ?? null;

if (!$tenant_id || !$city_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT c.region_id, r.country_id
        FROM cities c
        JOIN regions r ON c.region_id = r.id
        WHERE c.id = ? AND c.tenant_id = ?
    ");
    $stmt->execute([$city_id, $tenant_id]);
    $city = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($city) {
        echo json_encode(['success' => true, 'hierarchy' => [
            'country_id' => $city['country_id'],
            'region_id' => $city['region_id'],
            'city_id' => $city_id
        ]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'City not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
