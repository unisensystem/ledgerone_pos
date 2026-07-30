<?php
session_start();
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? '';

try {
    switch ($type) {
        case 'companies':
            $stmt = $pdo->prepare("SELECT id, company_name FROM companies WHERE tenant_id = ? ORDER BY company_name");
            $stmt->execute([$tenant_id]);
            break;

        case 'countries':
            $stmt = $pdo->prepare("SELECT id, country_name FROM countries WHERE tenant_id = ? ORDER BY country_name");
            $stmt->execute([$tenant_id]);
            break;

        case 'regions':
            if (empty($_GET['country_id'])) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id, region_name FROM regions WHERE tenant_id = ? AND country_id = ? ORDER BY region_name");
            $stmt->execute([$tenant_id, $_GET['country_id']]);
            break;

        case 'cities':
            if (empty($_GET['region_id'])) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id, city_name FROM cities WHERE tenant_id = ? AND region_id = ? ORDER BY city_name");
            $stmt->execute([$tenant_id, $_GET['region_id']]);
            break;

        case 'zones':
            if (empty($_GET['city_id'])) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id, city_zone_name FROM city_zones WHERE tenant_id = ? AND city_id = ? ORDER BY city_zone_name");
            $stmt->execute([$tenant_id, $_GET['city_id']]);
            break;

        case 'areas':
    if (empty($_GET['zone_id'])) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, area_name FROM areas WHERE tenant_id = ? AND city_zone_id = ? ORDER BY area_name");
    $stmt->execute([$tenant_id, $_GET['zone_id']]);
    break;

        case 'customers':
            $q = '%' . ($_GET['q'] ?? '') . '%';
            $stmt = $pdo->prepare("
                SELECT id, customer_code, customer_name
                FROM customers
                WHERE tenant_id = ? AND (customer_code LIKE ? OR customer_name LIKE ?)
                ORDER BY customer_name
                LIMIT 20
            ");
            $stmt->execute([$tenant_id, $q, $q]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            exit;
    }

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
