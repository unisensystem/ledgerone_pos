<?php
session_start();
require_once '../../../../includes/connection.php';
require_once 'recovery-sheet-query.php';

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

$filters = [
    'date_from'           => !empty($_GET['date_from'])           ? $_GET['date_from']                : null,
    'date_to'             => !empty($_GET['date_to'])             ? $_GET['date_to']                  : null,
    'recovery_officer_id' => !empty($_GET['recovery_officer_id']) ? (int)$_GET['recovery_officer_id'] : null,
    'country_id'          => !empty($_GET['country_id'])          ? (int)$_GET['country_id']          : null,
    'region_id'           => !empty($_GET['region_id'])           ? (int)$_GET['region_id']           : null,
    'city_id'             => !empty($_GET['city_id'])              ? (int)$_GET['city_id']             : null,
    'city_zone_id'        => !empty($_GET['city_zone_id'])        ? (int)$_GET['city_zone_id']        : null,
    'area_id'             => !empty($_GET['area_id'])              ? (int)$_GET['area_id']             : null,
    'company_id'          => !empty($_GET['company_id'])          ? (int)$_GET['company_id']          : null,
    'customer_id'         => !empty($_GET['customer_id'])         ? (int)$_GET['customer_id']         : null,
];

try {
    $customers = getRecoverySheetRows($pdo, $tenant_id, $filters);

    $total_balance = 0;
    $total_received = 0;
    $total_returns = 0;

    foreach ($customers as &$customer) {
        $total_balance += $customer['current_balance'];
        $total_received += $customer['total_received'];
        $total_returns += $customer['total_returns'];
    }

    echo json_encode([
        'success' => true,
        'data' => $customers,
        'summary' => [
            'total_balance' => $total_balance,
            'total_received' => $total_received,
            'total_returns' => $total_returns
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
