<?php
require_once '../../../../includes/connection.php';
require_once 'leave-helpers.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $employeeId = $_GET['employee_id'] ?? '';
    $year = (int) ($_GET['year'] ?? date('Y'));
    if (!$employeeId) throw new Exception('employee_id is required');

    // One row per active leave type for this tenant, LEFT JOINed to whatever
    // balance row exists (a type with zero activity this year still shows
    // up, with everything at 0, rather than silently disappearing).
    $stmt = $pdo->prepare("
        SELECT lt.id AS leave_type_id, lt.name, lt.code, lt.is_paid, lt.track_balance, lt.max_days_per_year,
               COALESCE(elb.opening_balance, 0) AS opening_balance,
               COALESCE(elb.allocated, 0) AS allocated,
               COALESCE(elb.carry_forward, 0) AS carry_forward,
               COALESCE(elb.used, 0) AS used,
               COALESCE(elb.pending, 0) AS pending,
               COALESCE(elb.adjustment, 0) AS adjustment,
               (COALESCE(elb.opening_balance,0) + COALESCE(elb.allocated,0) + COALESCE(elb.carry_forward,0) + COALESCE(elb.adjustment,0) - COALESCE(elb.used,0) - COALESCE(elb.pending,0)) AS remaining
        FROM leave_types lt
        LEFT JOIN employee_leave_balances elb ON elb.leave_type_id = lt.id AND elb.tenant_id = lt.tenant_id AND elb.employee_id = ? AND elb.year = ?
        WHERE lt.tenant_id = ? AND lt.is_active = 1
        ORDER BY lt.display_order, lt.name
    ");
    $stmt->execute([$employeeId, $year, $tenant_id]);
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $leaveTypeId = $_GET['leave_type_id'] ?? null;
    $txnSql = "
        SELECT t.*, u.full_name AS created_by_name
        FROM employee_leave_balance_transactions t
        LEFT JOIN users u ON u.id = t.created_by
        WHERE t.tenant_id = ? AND t.employee_id = ?
    ";
    $txnParams = [$tenant_id, $employeeId];
    if ($leaveTypeId) { $txnSql .= " AND t.leave_type_id = ?"; $txnParams[] = $leaveTypeId; }
    $txnSql .= " ORDER BY t.transaction_date DESC, t.id DESC LIMIT 200";

    $stmt = $pdo->prepare($txnSql);
    $stmt->execute($txnParams);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'balances' => $balances, 'transactions' => $transactions, 'year' => $year]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
