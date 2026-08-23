<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $recovery_officer_id = $_GET['recovery_officer_id'] ?? '';
    $city_id = $_GET['city_id'] ?? '';
    $city_zone_id = $_GET['city_zone_id'] ?? '';
    $area_id = $_GET['area_id'] ?? '';

    $sql = "
        SELECT customer_code, customer_name, id
        FROM customers
        WHERE tenant_id = ? AND status = 'ACTIVE'
    ";
    $params = [$tenant_id];

    if ($recovery_officer_id) {
        $sql .= " AND associated_sales_officer_id = ?";
        $params[] = $recovery_officer_id;
    }

    if ($area_id) {
        $sql .= " AND area_id = ?";
        $params[] = $area_id;
    } elseif ($city_zone_id) {
        $sql .= " AND city_zone_id = ?";
        $params[] = $city_zone_id;
    } elseif ($city_id) {
        $sql .= " AND city_id = ?";
        $params[] = $city_id;
    }

    $sql .= " ORDER BY customer_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $customers]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>