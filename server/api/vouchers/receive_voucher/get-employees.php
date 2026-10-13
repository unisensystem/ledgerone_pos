<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
$user_id = $auth['user_id'];
$tenant_id = $auth['tenant_id'];

try {
    $city_id = $_GET['city_id'] ?? '';
    $city_zone_id = $_GET['city_zone_id'] ?? '';
    $area_id = $_GET['area_id'] ?? '';

    if ($city_id || $city_zone_id || $area_id) {
        // Employees don't carry their own city/zone/area — an officer is scoped to a
        // location through the customers assigned to them (customers.associated_sales_officer_id).
        // Only return officers who have at least one customer in the selected location.
        $sql = "
            SELECT DISTINCT e.id, e.employee_id, e.full_name
            FROM employees e
            INNER JOIN customers c ON c.associated_sales_officer_id = e.id AND c.tenant_id = e.tenant_id
            WHERE e.tenant_id = ? AND e.is_active = 1 AND e.current_status = 'active'
        ";
        $params = [$tenant_id];

        if ($area_id) {
            $sql .= " AND c.area_id = ?";
            $params[] = $area_id;
        } elseif ($city_zone_id) {
            $sql .= " AND c.city_zone_id = ?";
            $params[] = $city_zone_id;
        } elseif ($city_id) {
            $sql .= " AND c.city_id = ?";
            $params[] = $city_id;
        }

        $sql .= " ORDER BY e.full_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, employee_id, full_name 
            FROM employees 
            WHERE tenant_id = ? AND is_active = 1 AND current_status = 'active'
            ORDER BY full_name
        ");
        $stmt->execute([$tenant_id]);
    }

    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $employees]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>