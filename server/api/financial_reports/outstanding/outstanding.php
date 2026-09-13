<?php
session_start();
require_once '../../../../includes/connection.php';
require_once 'outstanding-balance.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $type = $_GET['type'] ?? 'report';

    if ($type === 'dsr_list') {
        // Only employees who are actually assigned as a sales officer on at least one customer
        $stmt = $pdo->prepare(
            "SELECT DISTINCT e.id, e.full_name
             FROM employees e
             JOIN customers c ON c.associated_sales_officer_id = e.id
             WHERE c.tenant_id = ? AND c.status = 'ACTIVE'
             ORDER BY e.full_name"
        );
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($type === 'city_list') {
        // Only cities that actually have at least one active customer
        $stmt = $pdo->prepare(
            "SELECT DISTINCT ci.id, ci.city_name
             FROM cities ci
             JOIN customers c ON c.city_id = ci.id
             WHERE ci.tenant_id = ? AND c.status = 'ACTIVE'
             ORDER BY ci.city_name"
        );
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($type === 'area_list') {
        // If a city is selected, only show areas under that city (via city_zones),
        // and only areas that actually have an active customer.
        $city_id = $_GET['city_id'] ?? null;
        $sql = "SELECT DISTINCT a.id, a.area_name
                FROM areas a
                JOIN customers c ON c.area_id = a.id
                WHERE a.tenant_id = ? AND c.status = 'ACTIVE'";
        $params = [$tenant_id];
        if ($city_id) {
            $sql .= " AND a.id IN (
                        SELECT az.id FROM areas az
                        JOIN city_zones cz ON az.city_zone_id = cz.id
                        WHERE cz.city_id = ?
                      )";
            $params[] = $city_id;
        }
        $sql .= " ORDER BY a.area_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ---- Main report ----
    $sales_officer_id = $_GET['sales_officer_id'] ?? null;
    $company_id = $_GET['company_id'] ?? null;
    $from_date = $_GET['from_date'] ?? null;
    $to_date = $_GET['to_date'] ?? date('Y-m-d');
    $customer_name = $_GET['customer_name'] ?? null;
    $city_id = $_GET['city_id'] ?? null;
    $area_id = $_GET['area_id'] ?? null;
    $sort = $_GET['sort'] ?? 'high_to_low';  // ← IMPORTANT: Get sort parameter

    if (!$sales_officer_id) {
        echo json_encode(['success' => false, 'message' => 'DSR (sales officer) is required']);
        exit;
    }

    // Pass sort parameter to function
    $report = buildOutstandingReport(
        $pdo, $tenant_id, $sales_officer_id, $company_id, $from_date, $to_date,
        $customer_name, $city_id, $area_id, $sort
    );
    echo json_encode(array_merge(['success' => true], $report));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}