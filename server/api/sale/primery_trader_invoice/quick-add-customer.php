<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['customerName'])) {
        throw new Exception('Customer name is required');
    }
    if (empty($input['companyId'])) {
        throw new Exception('Company is required');
    }

    // Auto-generate customer code
    $stmt = $pdo->prepare("SELECT customer_code FROM customers WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tenant_id]);
    $lastCustomer = $stmt->fetch();

    if ($lastCustomer && preg_match('/CUST-(\d+)$/', $lastCustomer['customer_code'], $m)) {
        $nextNum = (int)$m[1] + 1;
    } else {
        $stmt2 = $pdo->prepare("SELECT COUNT(*) as cnt FROM customers WHERE tenant_id = ?");
        $stmt2->execute([$tenant_id]);
        $nextNum = (int)$stmt2->fetch()['cnt'] + 1;
    }
    $customer_code = 'CUST-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

    // Ensure unique code
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE tenant_id = ? AND customer_code = ?");
    $stmt->execute([$tenant_id, $customer_code]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(customer_code, 6) AS UNSIGNED)), 0) + 1 as next FROM customers WHERE tenant_id = ? AND customer_code LIKE 'CUST-%'");
        $stmt->execute([$tenant_id]);
        $nextNum = (int)$stmt->fetch()['next'];
        $customer_code = 'CUST-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }

    // Get new ID
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 as new_id FROM customers");
    $stmt->execute();
    $new_id = (int)$stmt->fetch()['new_id'];

    $company_id     = (int)$input['companyId'];
    $customer_name  = trim($input['customerName']);
    $area_id        = !empty($input['areaId'])       ? (int)$input['areaId']       : null;
    $city_id        = !empty($input['cityId'])        ? (int)$input['cityId']        : null;
    $city_zone_id   = !empty($input['cityZoneId'])    ? (int)$input['cityZoneId']    : null;
    $region_id      = !empty($input['regionId'])      ? (int)$input['regionId']      : null;
    $country_id     = !empty($input['countryId'])     ? (int)$input['countryId']     : null;
    $sales_officer_id = !empty($input['salesOfficerId']) ? (int)$input['salesOfficerId'] : null;

    $sql = "INSERT INTO customers (
        id, tenant_id, company_id, customer_code, customer_name,
        country_id, region_id, city_id, city_zone_id, area_id,
        associated_sales_officer_id,
        advance_income_tax_percentage, default_discount_percentage,
        opening_debit_amount, opening_credit_amount, credit_limit, credit_period_limit_days,
        is_sales_tax_registered, is_filer, is_wholesaler, is_blacklisted, is_out_station,
        created_by, updated_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $new_id, $tenant_id, $company_id, $customer_code, $customer_name,
        $country_id, $region_id, $city_id, $city_zone_id, $area_id,
        $sales_officer_id,
        $user_id, $user_id
    ]);

    echo json_encode([
        'success' => true,
        'customer' => [
            'id'                          => $new_id,
            'customer_code'               => $customer_code,
            'customer_name'               => $customer_name,
            'address'                     => null,
            'current_balance'             => 0,
            'default_discount_percentage' => 0,
            'associated_sales_officer_id' => $sales_officer_id,
            'supplier_man_id'             => null,
            'credit_limit'                => 0,
            'advance_income_tax_percentage' => 0,
            'city_id'                     => $city_id,
            'area_id'                     => $area_id,
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
