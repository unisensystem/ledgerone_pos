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
    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;
    
    // Get filter parameters
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';
    $recovery_officer_id = $_GET['recovery_officer_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';

    // Build WHERE clause
    $where = ['rv.tenant_id = ?'];
    $params = [$tenant_id];

    if ($date_from) {
        $where[] = 'rv.voucher_date >= ?';
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where[] = 'rv.voucher_date <= ?';
        $params[] = $date_to;
    }
    
    if ($customer_id) {
        $where[] = 'rv.customer_id = ?';
        $params[] = $customer_id;
    }
    
    if ($recovery_officer_id) {
        $where[] = 'rv.recovery_officer_id = ?';
        $params[] = $recovery_officer_id;
    }
    
    $company_id = $_GET['company_id'] ?? '';
    if ($company_id) {
        $where[] = 'rv.company_id = ?';
        $params[] = $company_id;
    }
    
    $project_id = $_GET['project_id'] ?? '';
    if ($project_id) {
        $where[] = 'rv.project_id = ?';
        $params[] = $project_id;
    }

    $area_id = $_GET['area_id'] ?? '';
    if ($area_id) {
        $where[] = 'cu.area_id = ?';
        $params[] = $area_id;
    }

    $city_id = $_GET['city_id'] ?? '';
    if ($city_id) {
        $where[] = 'cu.city_id = ?';
        $params[] = $city_id;
    }

    $voucher_number = $_GET['voucher_number'] ?? '';
    if ($voucher_number) {
        $where[] = 'rv.voucher_number LIKE ?';
        $params[] = '%' . $voucher_number . '%';
    }

    if ($search) {
        $where[] = '(rv.voucher_number LIKE ? OR cu.customer_name LIKE ? OR rv.bill_no LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Get total count
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM receive_voucher rv
        LEFT JOIN customers cu ON rv.customer_id = cu.id
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Get paginated data
    $stmt = $pdo->prepare("
        SELECT 
            rv.id,
            rv.voucher_number,
            rv.voucher_date,
            rv.amount,
            rv.bill_no,
            rv.payment_method_id,
            rv.bank_account_id,
            rv.cheque_date,
            rv.cheque_no,
            rv.recovery_officer_id,
            rv.company_id,
            rv.project_id,
            cu.customer_name,
            cu.customer_code,
            ar.area_name,
            ci.city_name,
            a.name as payment_method,
            c.symbol as currency_symbol,
            e.full_name as recovery_officer,
            p.project_code,
            p.project_name
        FROM receive_voucher rv
        LEFT JOIN customers cu ON rv.customer_id = cu.id
        LEFT JOIN areas ar ON cu.area_id = ar.id
        LEFT JOIN cities ci ON cu.city_id = ci.id
        LEFT JOIN accounts a ON rv.payment_method_id = a.id
        LEFT JOIN ledgerone_public.currencies c ON rv.currency_id = c.id
        LEFT JOIN employees e ON rv.recovery_officer_id = e.id
        LEFT JOIN projects p ON rv.project_id = p.id
        WHERE $whereClause
        ORDER BY rv.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Summary: total + payment method wise (same filters)
    $summaryStmt = $pdo->prepare("
        SELECT 
            a.name as payment_method,
            SUM(rv.amount) as total
        FROM receive_voucher rv
        LEFT JOIN customers cu ON rv.customer_id = cu.id
        LEFT JOIN accounts a ON rv.payment_method_id = a.id
        WHERE $whereClause
        GROUP BY rv.payment_method_id, a.name
        ORDER BY total DESC
    ");
    $summaryStmt->execute($params);
    $summaryRows = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

    $grandTotal = 0;
    $paymentSummary = [];
    foreach ($summaryRows as $row) {
        $grandTotal += floatval($row['total']);
        $paymentSummary[] = [
            'payment_method' => $row['payment_method'] ?? 'Unknown',
            'total' => floatval($row['total'])
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $vouchers,
        'summary' => [
            'grand_total' => $grandTotal,
            'by_payment_method' => $paymentSummary
        ],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'limit' => $limit,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
