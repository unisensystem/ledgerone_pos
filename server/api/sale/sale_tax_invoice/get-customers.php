<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $company_id = isset($_GET['company_id']) && $_GET['company_id'] !== '' ? (int)$_GET['company_id'] : null;

    $sql = "
        SELECT 
            c.id, c.customer_code, c.customer_name, c.current_balance, c.address, 
            c.city_id, c.area_id,
            c.default_discount_percentage, c.credit_limit, c.associated_sales_officer_id, 
            c.supplier_man_id, c.is_sales_tax_registered, c.is_filer, 
            c.advance_income_tax_percentage, c.ntn, c.strn,
            e.employee_id as supplier_man_employee_id,
            e.full_name as supplier_man_name
        FROM customers c
        LEFT JOIN employees e ON c.supplier_man_id = e.id
        WHERE (c.tenant_id = ? OR c.tenant_id = 0) AND c.status = 'ACTIVE'
    ";
    $params = [$tenant_id];

    if ($company_id) {
        $sql .= " AND c.company_id = ?";
        $params[] = $company_id;
    }

    $sql .= " ORDER BY c.customer_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'customers' => $customers]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}