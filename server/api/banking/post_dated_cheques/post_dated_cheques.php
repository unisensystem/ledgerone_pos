<?php
session_start();
require_once '../../../../includes/connection.php';

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
    $query = "SELECT 
        pdc.id,
        pdc.cheque_no,
        pdc.amount,
        pdc.cheque_date,
        pdc.status,
        pdc.transaction_type,
        pdc.company_id,
        pdc.bank_account_id,
        COALESCE(c.customer_name, s.supplier_name) as account,
        ba.bank_name,
        co.company_name,
        COALESCE(SUM(rv.amount), 0) as paid_amount
    FROM post_dated_cheques pdc
    LEFT JOIN customers c ON pdc.customer_id = c.id
    LEFT JOIN suppliers s ON pdc.supplier_id = s.id
    LEFT JOIN bank_accounts ba ON pdc.bank_account_id = ba.id
    LEFT JOIN companies co ON pdc.company_id = co.id
    LEFT JOIN receive_voucher rv ON rv.against_pdc_id = pdc.id AND rv.tenant_id = pdc.tenant_id
    WHERE pdc.tenant_id = ?";
    
    $params = [$tenant_id];
    
    if (!empty($_GET['cheque_no'])) {
        $query .= " AND pdc.cheque_no LIKE ?";
        $params[] = '%' . $_GET['cheque_no'] . '%';
    }
    
    if (!empty($_GET['account'])) {
        $query .= " AND (c.customer_name LIKE ? OR s.supplier_name LIKE ?)";
        $search = '%' . $_GET['account'] . '%';
        $params[] = $search;
        $params[] = $search;
    }
    
    if (!empty($_GET['bank'])) {
        $query .= " AND ba.bank_name = ?";
        $params[] = $_GET['bank'];
    }
    
    if (!empty($_GET['status'])) {
        $query .= " AND pdc.status = ?";
        $params[] = $_GET['status'];
    }
    
    if (!empty($_GET['type'])) {
        $query .= " AND pdc.transaction_type = ?";
        $params[] = $_GET['type'];
    }
    
    if (!empty($_GET['min_amount'])) {
        $query .= " AND pdc.amount >= ?";
        $params[] = floatval($_GET['min_amount']);
    }
    
    if (!empty($_GET['max_amount'])) {
        $query .= " AND pdc.amount <= ?";
        $params[] = floatval($_GET['max_amount']);
    }
    
    if (!empty($_GET['date_filter'])) {
        $today = date('Y-m-d');
        switch ($_GET['date_filter']) {
            case 'today':
                $query .= " AND pdc.cheque_date = ?";
                $params[] = $today;
                break;
            case 'week':
                $query .= " AND pdc.cheque_date >= DATE_SUB(?, INTERVAL WEEKDAY(?) DAY)";
                $params[] = $today;
                $params[] = $today;
                break;
            case 'month':
                $query .= " AND MONTH(pdc.cheque_date) = MONTH(?) AND YEAR(pdc.cheque_date) = YEAR(?)";
                $params[] = $today;
                $params[] = $today;
                break;
            case 'quarter':
                $query .= " AND QUARTER(pdc.cheque_date) = QUARTER(?) AND YEAR(pdc.cheque_date) = YEAR(?)";
                $params[] = $today;
                $params[] = $today;
                break;
        }
    }
    
    if (!empty($_GET['company_id'])) {
        $query .= " AND pdc.company_id = ?";
        $params[] = $_GET['company_id'];
    }
    
    $query .= " GROUP BY pdc.id, pdc.cheque_no, pdc.amount, pdc.cheque_date, pdc.status, pdc.transaction_type, pdc.company_id, pdc.bank_account_id, c.customer_name, s.supplier_name, ba.bank_name, co.company_name ORDER BY pdc.cheque_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetchAll();
    
    $pdcs = [];
    foreach ($result as $row) {
        $pdcs[] = [
            'id' => (int)$row['id'],
            'chequeNo' => $row['cheque_no'],
            'account' => $row['account'] ?? 'N/A',
            'bankName' => $row['bank_name'] ?? 'N/A',
            'type' => $row['transaction_type'],
            'amount' => (float)$row['amount'],
            'paidAmount' => (float)$row['paid_amount'],
            'remaining' => (float)$row['amount'] - (float)$row['paid_amount'],
            'date' => $row['cheque_date'],
            'status' => $row['status'],
            'companyId' => $row['company_id'],
            'companyName' => $row['company_name'] ?? 'N/A',
            'bankAccountId' => $row['bank_account_id']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $pdcs]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
