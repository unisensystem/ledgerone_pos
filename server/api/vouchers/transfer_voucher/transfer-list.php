<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit(); }

try {
    $page       = max(1, intval($_GET['page']  ?? 1));
    $limit      = max(1, min(100, intval($_GET['limit'] ?? 10)));
    $offset     = ($page - 1) * $limit;
    $date_from  = $_GET['date_from']   ?? '';
    $date_to    = $_GET['date_to']     ?? '';
    $company_id = $_GET['company_id']  ?? '';
    $from_type  = $_GET['from_type']   ?? '';
    $to_type    = $_GET['to_type']     ?? '';
    $search     = $_GET['search']      ?? '';

    $where = ['tv.tenant_id = ?'];
    $params = [$tenant_id];

    if ($date_from)  { $where[] = 'tv.voucher_date >= ?'; $params[] = $date_from; }
    if ($date_to)    { $where[] = 'tv.voucher_date <= ?'; $params[] = $date_to; }
    if ($company_id) { $where[] = 'tv.company_id = ?';   $params[] = $company_id; }
    if ($from_type)  { $where[] = 'tv.from_type = ?';    $params[] = $from_type; }
    if ($to_type)    { $where[] = 'tv.to_type = ?';      $params[] = $to_type; }
    if ($search) {
        $where[] = '(tv.voucher_number LIKE ? OR fc.customer_name LIKE ? OR fs.supplier_name LIKE ? OR tc.customer_name LIKE ? OR ts.supplier_name LIKE ?)';
        $s = '%'.$search.'%';
        array_push($params, $s, $s, $s, $s, $s);
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $joins = "
        LEFT JOIN customers fc ON tv.from_customer_id = fc.id
        LEFT JOIN suppliers fs ON tv.from_supplier_id = fs.id
        LEFT JOIN customers tc ON tv.to_customer_id   = tc.id
        LEFT JOIN suppliers ts ON tv.to_supplier_id   = ts.id
        LEFT JOIN accounts  a  ON tv.payment_method_id = a.id
        LEFT JOIN ledgerone_public.currencies cur ON tv.currency_id = cur.id
        LEFT JOIN companies co ON tv.company_id = co.id
    ";

    // Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM transfer_voucher tv $joins $whereClause");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages   = max(1, ceil($totalRecords / $limit));

    // Data
    $stmt = $pdo->prepare("
        SELECT
            tv.id, tv.voucher_number, tv.voucher_date, tv.amount, tv.slip_no,
            tv.from_type, tv.to_type,
            fc.customer_name AS from_customer_name, fc.customer_code AS from_customer_code,
            fs.supplier_name AS from_supplier_name, fs.supplier_code AS from_supplier_code,
            tc.customer_name AS to_customer_name,   tc.customer_code AS to_customer_code,
            ts.supplier_name AS to_supplier_name,   ts.supplier_code AS to_supplier_code,
            a.name  AS payment_method,
            cur.symbol AS currency_symbol,
            co.company_name
        FROM transfer_voucher tv
        $joins
        $whereClause
        ORDER BY tv.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary
    $sumStmt = $pdo->prepare("
        SELECT a.name AS payment_method, SUM(tv.amount) AS total
        FROM transfer_voucher tv
        $joins
        $whereClause
        GROUP BY tv.payment_method_id, a.name
        ORDER BY total DESC
    ");
    $sumStmt->execute($params);
    $sumRows = $sumStmt->fetchAll(PDO::FETCH_ASSOC);

    $grandTotal = 0;
    $byMethod   = [];
    foreach ($sumRows as $r) {
        $grandTotal += floatval($r['total']);
        $byMethod[] = ['payment_method' => $r['payment_method'] ?? 'Unknown', 'total' => floatval($r['total'])];
    }

    echo json_encode([
        'success' => true,
        'data'    => $vouchers,
        'summary' => ['grand_total' => $grandTotal, 'by_payment_method' => $byMethod],
        'pagination' => [
            'current_page'  => $page,
            'total_pages'   => $totalPages,
            'total_records' => $totalRecords,
            'limit'         => $limit,
            'has_next'      => $page < $totalPages,
            'has_prev'      => $page > 1
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
