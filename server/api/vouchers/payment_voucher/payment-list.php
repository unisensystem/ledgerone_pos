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
    // Get payment type parameter
    $payment_type = $_GET['payment_type'] ?? 'supplier';
    
    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;
    
    // Get filter parameters
    $supplier_code = $_GET['supplier_code'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $company_id = $_GET['company_id'] ?? '';
    $project_id = $_GET['project_id'] ?? '';

    // Build WHERE clause
    $whereConditions = [];
    $params = [$tenant_id];

    // Build query based on payment type
    if ($payment_type === 'customer') {
        $whereConditions[] = 'pv.customer_id IS NOT NULL';
        
        if ($supplier_code) {
            $whereConditions[] = 'c.customer_code = ?';
            $params[] = $supplier_code;
        }
        
        if ($date_from) {
            $whereConditions[] = 'pv.voucher_date >= ?';
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $whereConditions[] = 'pv.voucher_date <= ?';
            $params[] = $date_to;
        }
        
        if ($search) {
            $whereConditions[] = '(pv.voucher_number LIKE ? OR c.customer_name LIKE ? OR pv.bill_no LIKE ?)';
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if ($company_id) {
            $whereConditions[] = 'pv.company_id = ?';
            $params[] = $company_id;
        }
        
        if ($project_id) {
            $whereConditions[] = 'pv.project_id = ?';
            $params[] = $project_id;
        }
        
        $whereClause = 'WHERE pv.tenant_id = ? AND ' . implode(' AND ', $whereConditions);
        
        // Get total count for customers
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM payment_voucher pv
            LEFT JOIN customers c ON pv.customer_id = c.id
            $whereClause
        ");
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($totalRecords / $limit);
        
        // Get paginated data for customers
        $stmt = $pdo->prepare("
            SELECT 
                pv.id,
                pv.voucher_number,
                pv.voucher_date,
                pv.amount,
                pv.bill_no,
                pv.company_id,
                pv.project_id,
                c.customer_name as supplier_name,
                c.customer_code as supplier_code,
                a.name as payment_method,
                cur.symbol as currency_symbol,
                p.project_name
            FROM payment_voucher pv
            LEFT JOIN customers c ON pv.customer_id = c.id
            LEFT JOIN accounts a ON pv.payment_method_id = a.id
            LEFT JOIN ledgerone_public.currencies cur ON pv.currency_id = cur.id
            LEFT JOIN projects p ON pv.project_id = p.id
            $whereClause
            ORDER BY pv.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
    } else {
        $whereConditions[] = 'pv.supplier_id IS NOT NULL';
        
        if ($supplier_code) {
            $whereConditions[] = 's.supplier_code = ?';
            $params[] = $supplier_code;
        }
        
        if ($date_from) {
            $whereConditions[] = 'pv.voucher_date >= ?';
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $whereConditions[] = 'pv.voucher_date <= ?';
            $params[] = $date_to;
        }
        
        if ($search) {
            $whereConditions[] = '(pv.voucher_number LIKE ? OR s.supplier_name LIKE ? OR pv.bill_no LIKE ?)';
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if ($company_id) {
            $whereConditions[] = 'pv.company_id = ?';
            $params[] = $company_id;
        }
        
        if ($project_id) {
            $whereConditions[] = 'pv.project_id = ?';
            $params[] = $project_id;
        }
        
        $whereClause = 'WHERE pv.tenant_id = ? AND ' . implode(' AND ', $whereConditions);
        
        // Get total count for suppliers
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM payment_voucher pv
            LEFT JOIN suppliers s ON pv.supplier_id = s.id
            $whereClause
        ");
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($totalRecords / $limit);
        
        // Get paginated data for suppliers
        $stmt = $pdo->prepare("
            SELECT 
                pv.id,
                pv.voucher_number,
                pv.voucher_date,
                pv.amount,
                pv.bill_no,
                pv.company_id,
                pv.project_id,
                s.supplier_name,
                s.supplier_code,
                a.name as payment_method,
                c.symbol as currency_symbol,
                p.project_name
            FROM payment_voucher pv
            LEFT JOIN suppliers s ON pv.supplier_id = s.id
            LEFT JOIN accounts a ON pv.payment_method_id = a.id
            LEFT JOIN ledgerone_public.currencies c ON pv.currency_id = c.id
            LEFT JOIN projects p ON pv.project_id = p.id
            $whereClause
            ORDER BY pv.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
    }
    
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary: total + payment method wise (same filters, no pagination)
    if ($payment_type === 'customer') {
        $summaryStmt = $pdo->prepare("
            SELECT a.name as payment_method, SUM(pv.amount) as total
            FROM payment_voucher pv
            LEFT JOIN customers c ON pv.customer_id = c.id
            LEFT JOIN accounts a ON pv.payment_method_id = a.id
            $whereClause
            GROUP BY pv.payment_method_id, a.name
            ORDER BY total DESC
        ");
    } else {
        $summaryStmt = $pdo->prepare("
            SELECT a.name as payment_method, SUM(pv.amount) as total
            FROM payment_voucher pv
            LEFT JOIN suppliers s ON pv.supplier_id = s.id
            LEFT JOIN accounts a ON pv.payment_method_id = a.id
            $whereClause
            GROUP BY pv.payment_method_id, a.name
            ORDER BY total DESC
        ");
    }
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