<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$date_from    = !empty($_GET['date_from'])    ? $_GET['date_from']              : null;
$date_to      = !empty($_GET['date_to'])      ? $_GET['date_to']                : null;
$salesman_id  = !empty($_GET['salesman_id'])  ? (int)$_GET['salesman_id']       : null;
$country_id   = !empty($_GET['country_id'])   ? (int)$_GET['country_id']        : null;
$region_id    = !empty($_GET['region_id'])    ? (int)$_GET['region_id']         : null;
$city_id      = !empty($_GET['city_id'])      ? (int)$_GET['city_id']           : null;
$city_zone_id = !empty($_GET['city_zone_id']) ? (int)$_GET['city_zone_id']      : null;
$area_id      = !empty($_GET['area_id'])      ? (int)$_GET['area_id']           : null;
$company_id   = !empty($_GET['company_id'])   ? (int)$_GET['company_id']        : null;

try {
    $inner = "
        SELECT
            COALESCE(e.full_name, 'Unassigned') AS salesman_name,
            e.id                                AS salesman_id,
            c.customer_name,
            c.address,
            c.primary_phone,
            obi.invoice_number                  AS bill_no,
            obi.invoice_date,
            DATEDIFF(CURDATE(), obi.invoice_date) AS days_overdue,
            obi.debit - COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.bill_no = obi.invoice_number
                  AND rv.customer_id = c.id
                  AND rv.tenant_id = ?
            ), 0)                               AS current_balance,
            COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.customer_id = c.id
                  AND rv.tenant_id = ?
                " . ($date_from ? " AND rv.voucher_date >= ?" : "") . "
                " . ($date_to   ? " AND rv.voucher_date <= ?" : "") . "
            ), 0)                               AS total_received,
            COALESCE((
                SELECT SUM(sr.net_amount)
                FROM sale_return sr
                WHERE sr.customer_id = c.id
                  AND sr.tenant_id = ?
                  AND sr.status = 'Posted'
                " . ($date_from ? " AND sr.sale_date >= ?" : "") . "
                " . ($date_to   ? " AND sr.sale_date <= ?" : "") . "
            ), 0)                               AS total_returns,
            COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.customer_id = c.id
                  AND rv.tenant_id = ?
                  AND (rv.bill_no IS NULL OR rv.bill_no = '' OR rv.bill_no = 'null')
            ), 0)                               AS unallocated_received,
            COALESCE((
                SELECT SUM(sr.net_amount)
                FROM sale_return sr
                WHERE sr.customer_id = c.id
                  AND sr.tenant_id = ?
                  AND sr.status = 'Posted'
                  AND (sr.sale_invoice_no IS NULL OR sr.sale_invoice_no = 0)
            ), 0)                               AS unallocated_returns
        FROM opening_balance_invoices obi
        JOIN customers c ON obi.customer_id = c.id
        LEFT JOIN employees e ON obi.employee_id = e.id
        WHERE obi.tenant_id = ?
          AND c.status = 'ACTIVE'
          AND obi.debit > 0
        " . ($salesman_id  ? " AND obi.employee_id = ?"    : "") . "
        " . ($company_id   ? " AND c.company_id = ?"       : "") . "
        " . ($country_id   ? " AND c.country_id = ?"       : "") . "
        " . ($region_id    ? " AND c.region_id = ?"        : "") . "
        " . ($city_id      ? " AND c.city_id = ?"          : "") . "
        " . ($city_zone_id ? " AND c.city_zone_id = ?"     : "") . "
        " . ($area_id      ? " AND c.area_id = ?"          : "") . "

        UNION ALL

        SELECT
            COALESCE(e.full_name, 'Unassigned') AS salesman_name,
            e.id                                AS salesman_id,
            c.customer_name,
            c.address,
            c.primary_phone,
            si.bill_no,
            si.sale_date                        AS invoice_date,
            DATEDIFF(CURDATE(), si.sale_date)   AS days_overdue,
            si.net_amount - COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.bill_no = si.bill_no
                  AND rv.customer_id = c.id
                  AND rv.tenant_id = ?
            ), 0) - COALESCE((
                SELECT SUM(sr.net_amount)
                FROM sale_return sr
                WHERE sr.sale_invoice_no = si.id
                  AND sr.tenant_id = ?
                  AND sr.status = 'Posted'
            ), 0)                               AS current_balance,
            COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.customer_id = c.id
                  AND rv.tenant_id = ?
                " . ($date_from ? " AND rv.voucher_date >= ?" : "") . "
                " . ($date_to   ? " AND rv.voucher_date <= ?" : "") . "
            ), 0)                               AS total_received,
            COALESCE((
                SELECT SUM(sr.net_amount)
                FROM sale_return sr
                WHERE sr.customer_id = c.id
                  AND sr.tenant_id = ?
                  AND sr.status = 'Posted'
                " . ($date_from ? " AND sr.sale_date >= ?" : "") . "
                " . ($date_to   ? " AND sr.sale_date <= ?" : "") . "
            ), 0)                               AS total_returns,
            COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.customer_id = c.id
                  AND rv.tenant_id = ?
                  AND (rv.bill_no IS NULL OR rv.bill_no = '' OR rv.bill_no = 'null')
            ), 0)                               AS unallocated_received,
            COALESCE((
                SELECT SUM(sr.net_amount)
                FROM sale_return sr
                WHERE sr.customer_id = c.id
                  AND sr.tenant_id = ?
                  AND sr.status = 'Posted'
                  AND (sr.sale_invoice_no IS NULL OR sr.sale_invoice_no = 0)
            ), 0)                               AS unallocated_returns
        FROM sale_invoice si
        JOIN customers c ON si.customer_id = c.id
        LEFT JOIN employees e ON si.sale_officer_id = e.id
        WHERE si.tenant_id = ?
          AND c.status = 'ACTIVE'
          AND si.status = 'Posted'
          AND si.net_amount > 0
        " . ($salesman_id  ? " AND si.sale_officer_id = ?"  : "") . "
        " . ($date_from    ? " AND si.sale_date >= ?"       : "") . "
        " . ($date_to      ? " AND si.sale_date <= ?"       : "") . "
        " . ($company_id   ? " AND si.company_id = ?"       : "") . "
        " . ($country_id   ? " AND c.country_id = ?"        : "") . "
        " . ($region_id    ? " AND c.region_id = ?"         : "") . "
        " . ($city_id      ? " AND c.city_id = ?"           : "") . "
        " . ($city_zone_id ? " AND c.city_zone_id = ?"      : "") . "
        " . ($area_id      ? " AND c.area_id = ?"           : "") . "
    ";

    $query = "SELECT * FROM ($inner) AS sr_data WHERE current_balance > 0 ORDER BY salesman_name, customer_name, invoice_date";

    // UNION Part 1 params (opening_balance_invoices)
    $params   = [$tenant_id];               // balance subquery: rv.tenant_id
    $params[] = $tenant_id;                 // received subquery: rv.tenant_id
    if ($date_from) $params[] = $date_from;
    if ($date_to)   $params[] = $date_to;
    $params[] = $tenant_id;                 // returns subquery: sr.tenant_id
    if ($date_from) $params[] = $date_from;
    if ($date_to)   $params[] = $date_to;
    $params[] = $tenant_id;                 // unallocated_received subquery
    $params[] = $tenant_id;                 // unallocated_returns subquery
    $params[] = $tenant_id;                 // WHERE obi.tenant_id
    if ($salesman_id)  $params[] = $salesman_id;
    if ($company_id)   $params[] = $company_id;
    if ($country_id)   $params[] = $country_id;
    if ($region_id)    $params[] = $region_id;
    if ($city_id)      $params[] = $city_id;
    if ($city_zone_id) $params[] = $city_zone_id;
    if ($area_id)      $params[] = $area_id;

    // UNION Part 2 params (sale_invoice)
    $params[] = $tenant_id;                 // balance subquery: rv.tenant_id
    $params[] = $tenant_id;                 // balance subquery: sr.tenant_id
    $params[] = $tenant_id;                 // received subquery: rv.tenant_id
    if ($date_from) $params[] = $date_from;
    if ($date_to)   $params[] = $date_to;
    $params[] = $tenant_id;                 // returns subquery: sr.tenant_id
    if ($date_from) $params[] = $date_from;
    if ($date_to)   $params[] = $date_to;
    $params[] = $tenant_id;                 // unallocated_received subquery
    $params[] = $tenant_id;                 // unallocated_returns subquery
    $params[] = $tenant_id;                 // WHERE si.tenant_id
    if ($salesman_id)  $params[] = $salesman_id;
    if ($date_from)    $params[] = $date_from;
    if ($date_to)      $params[] = $date_to;
    if ($company_id)   $params[] = $company_id;
    if ($country_id)   $params[] = $country_id;
    if ($region_id)    $params[] = $region_id;
    if ($city_id)      $params[] = $city_id;
    if ($city_zone_id) $params[] = $city_zone_id;
    if ($area_id)      $params[] = $area_id;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_balance  = 0;
    $total_received = 0;
    $total_returns  = 0;
    foreach ($rows as $row) {
        $total_balance  += $row['current_balance'];
        $total_received += $row['total_received'];
        $total_returns  += $row['total_returns'];
    }

    echo json_encode([
        'success' => true,
        'data'    => $rows,
        'summary' => [
            'total_balance'  => $total_balance,
            'total_received' => $total_received,
            'total_returns'  => $total_returns
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
