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

$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;
$salesOfficerId = $_GET['sales_officer_id'] ?? null;
$customerId = $_GET['customer_id'] ?? null;
$distributionId = $_GET['distribution_id'] ?? null;
$status = $_GET['status'] ?? null;
$includeZeroBalance = $_GET['include_zero_balance'] ?? '0';
$companyId = $_GET['company_id'] ?? null;
$cityId = $_GET['city_id'] ?? null;
$areaId = $_GET['area_id'] ?? null;

try {
    // Build WHERE conditions
    $params = [];
    
    $siWhere = "si.status = 'Posted' AND si.tenant_id = ?";
    $obiWhere = "obi.tenant_id = ?";
    
    // Add parameters in order they appear in query
    
    // 1. sale_return tenant_id
    $params[] = $tenant_id;
    
    // 1a. sale_return company_id (if provided)
    if ($companyId) {
        $params[] = $companyId;
    }
    
    // 2. receive_voucher tenant_id (for sale_invoice)
    $params[] = $tenant_id;
    
    // 2a. receive_voucher company_id (if provided)
    if ($companyId) {
        $params[] = $companyId;
    }
    
    // 3. sale_invoice WHERE conditions
    $params[] = $tenant_id;
    
    if ($companyId) {
        $siWhere .= " AND si.company_id = ?";
        $params[] = $companyId;
    }
    
    if ($dateFrom) {
        $siWhere .= " AND si.sale_date >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $siWhere .= " AND si.sale_date <= ?";
        $params[] = $dateTo;
    }
    
    if ($salesOfficerId) {
        $siWhere .= " AND si.sale_officer_id = ?";
        $params[] = $salesOfficerId;
    }
    
    if ($customerId) {
        $siWhere .= " AND si.customer_id = ?";
        $params[] = $customerId;
    }
    
    if ($distributionId) {
        $siWhere .= " AND EXISTS (
            SELECT 1 FROM sale_invoice_items sii
            INNER JOIN products p ON sii.product_id = p.id
            WHERE sii.sale_invoice_id = si.id AND p.vendor_id = ?
        )";
        $params[] = $distributionId;
    }

    if ($cityId) {
        $siWhere .= " AND c.city_id = ?";
        $params[] = $cityId;
    }

    if ($areaId) {
        $siWhere .= " AND c.area_id = ?";
        $params[] = $areaId;
    }

    // 4. receive_voucher tenant_id (for opening_balance_invoices)
    $params[] = $tenant_id;
    
    // 4a. receive_voucher company_id (if provided)
    if ($companyId) {
        $params[] = $companyId;
    }
    
    // 5. opening_balance_invoices WHERE conditions
    $params[] = $tenant_id;
    
    if ($companyId) {
        $obiWhere .= " AND c.company_id = ?";
        $params[] = $companyId;
    }
    
    if ($dateFrom) {
        $obiWhere .= " AND obi.invoice_date >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $obiWhere .= " AND obi.invoice_date <= ?";
        $params[] = $dateTo;
    }
    
    if ($salesOfficerId) {
        $obiWhere .= " AND obi.employee_id = ?";
        $params[] = $salesOfficerId;
    }
    
    if ($customerId) {
        $obiWhere .= " AND obi.customer_id = ?";
        $params[] = $customerId;
    }
    
    if ($distributionId) {
        $obiWhere .= " AND obi.distribution_id = ?";
        $params[] = $distributionId;
    }

    if ($cityId) {
        $obiWhere .= " AND c.city_id = ?";
        $params[] = $cityId;
    }

    if ($areaId) {
        $obiWhere .= " AND c.area_id = ?";
        $params[] = $areaId;
    }

    // 6. payment_voucher WHERE conditions
    $params[] = $tenant_id;
    if ($companyId) $params[] = $companyId;
    if ($dateFrom) $params[] = $dateFrom;
    if ($dateTo) $params[] = $dateTo;
    if ($salesOfficerId) $params[] = $salesOfficerId;
    if ($customerId) $params[] = $customerId;
    if ($cityId) $params[] = $cityId;
    if ($areaId) $params[] = $areaId;

    // Build query for sales invoices with recoveries
    $query = "(
        SELECT 
            e.id as officer_id,
            e.full_name as officer_name,
            si.id as invoice_id,
            si.bill_no,
            si.sale_date as invoice_date,
            si.customer_id,
            c.customer_name,
            si.branch_id as distribution_id,
            si.net_amount as bill_amount,
            COALESCE(sr.net_amount, 0) as return_amount,
            COALESCE(rv.total_recovery, 0) as total_recovery,
            (si.net_amount - COALESCE(sr.net_amount, 0) - COALESCE(rv.total_recovery, 0)) as remaining_balance,
            DATEDIFF(CURDATE(), si.sale_date) as overdue_days
        FROM sale_invoice si
        INNER JOIN employees e ON si.sale_officer_id = e.id
        INNER JOIN customers c ON si.customer_id = c.id
        LEFT JOIN (
            SELECT sale_invoice_no, SUM(net_amount) as net_amount
            FROM sale_return
            WHERE status = 'Posted' AND tenant_id = ?" . ($companyId ? " AND company_id = ?" : "") . "
            GROUP BY sale_invoice_no
        ) sr ON si.id = sr.sale_invoice_no
        LEFT JOIN (
            SELECT bill_no, SUM(amount) as total_recovery
            FROM receive_voucher
            WHERE tenant_id = ?" . ($companyId ? " AND company_id = ?" : "") . "
            GROUP BY bill_no
        ) rv ON si.bill_no = rv.bill_no
        WHERE {$siWhere}
    )
    UNION ALL
    (
        SELECT 
            e.id as officer_id,
            e.full_name as officer_name,
            obi.id as invoice_id,
            obi.invoice_number as bill_no,
            obi.invoice_date,
            obi.customer_id,
            c.customer_name,
            obi.distribution_id,
            obi.debit as bill_amount,
            0 as return_amount,
            COALESCE(rv.total_recovery, 0) as total_recovery,
            (obi.debit - COALESCE(rv.total_recovery, 0)) as remaining_balance,
            DATEDIFF(CURDATE(), obi.invoice_date) as overdue_days
        FROM opening_balance_invoices obi
        INNER JOIN employees e ON obi.employee_id = e.id
        INNER JOIN customers c ON obi.customer_id = c.id
        LEFT JOIN (
            SELECT bill_no, SUM(amount) as total_recovery
            FROM receive_voucher
            WHERE tenant_id = ?" . ($companyId ? " AND company_id = ?" : "") . "
            GROUP BY bill_no
        ) rv ON obi.invoice_number = rv.bill_no
        WHERE {$obiWhere}
    )
    UNION ALL
    (
        -- Payment Voucher to Customer (an advance/charge paid out to the customer
        -- debits Trade Debtors, increasing what the customer owes — same
        -- treatment as customer-aging.php / recovery-sheet-query.php. Since
        -- payment_voucher carries no sales-officer column of its own, it is
        -- attributed to the customer's own associated_sales_officer_id, the
        -- same fallback the customer record itself carries elsewhere.
        SELECT
            e.id as officer_id,
            e.full_name as officer_name,
            pv.id as invoice_id,
            pv.voucher_number as bill_no,
            pv.voucher_date as invoice_date,
            pv.customer_id,
            c.customer_name,
            NULL as distribution_id,
            pv.amount as bill_amount,
            0 as return_amount,
            0 as total_recovery,
            pv.amount as remaining_balance,
            DATEDIFF(CURDATE(), pv.voucher_date) as overdue_days
        FROM payment_voucher pv
        INNER JOIN customers c ON pv.customer_id = c.id
        INNER JOIN employees e ON c.associated_sales_officer_id = e.id
        WHERE pv.tenant_id = ?" . ($companyId ? " AND pv.company_id = ?" : "") . "
        " . ($dateFrom ? " AND pv.voucher_date >= ?" : "") . "
        " . ($dateTo ? " AND pv.voucher_date <= ?" : "") . "
        " . ($salesOfficerId ? " AND c.associated_sales_officer_id = ?" : "") . "
        " . ($customerId ? " AND pv.customer_id = ?" : "") . "
        " . ($cityId ? " AND c.city_id = ?" : "") . "
        " . ($areaId ? " AND c.area_id = ?" : "") . "
    )
    ORDER BY officer_name, invoice_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by sales officer
    $groupedData = [];
    foreach ($results as $row) {
        $officerId = $row['officer_id'];
        
        if (!isset($groupedData[$officerId])) {
            $groupedData[$officerId] = [
                'salesOfficer' => $row['officer_name'],
                'officerId' => 'so' . $officerId,
                'totalBills' => 0,
                'totalBillAmount' => 0,
                'totalRecovery' => 0,
                'totalRemaining' => 0,
                'transactions' => []
            ];
        }
        
        $remainingBalance = $row['remaining_balance'];
        $transactionStatus = 'overdue';
        
        if ($remainingBalance <= 0) {
            $transactionStatus = 'recovered';
        } elseif ($row['total_recovery'] > 0) {
            $transactionStatus = 'partial';
        }
        
        // Apply status filter
        if ($status && $status !== $transactionStatus) {
            continue;
        }
        
        // Exclude zero balance invoices by default
        if ($includeZeroBalance !== '1' && $remainingBalance <= 0) {
            continue;
        }
        
        $transaction = [
            'id' => $row['invoice_id'],
            'billDate' => $row['invoice_date'],
            'overdueDays' => max(0, $row['overdue_days']),
            'billNo' => $row['bill_no'],
            'returnAmount' => floatval($row['return_amount']),
            'customerName' => $row['customer_name'],
            'distribution' => 'Branch ' . $row['distribution_id'],
            'billAmount' => floatval($row['bill_amount']),
            'totalRecovery' => floatval($row['total_recovery']),
            'remainingBalance' => floatval($remainingBalance),
            'status' => $transactionStatus
        ];
        
        $groupedData[$officerId]['transactions'][] = $transaction;
        $groupedData[$officerId]['totalBills']++;
        $groupedData[$officerId]['totalBillAmount'] += floatval($row['bill_amount']);
        $groupedData[$officerId]['totalRecovery'] += floatval($row['total_recovery']);
        $groupedData[$officerId]['totalRemaining'] += floatval($remainingBalance);
    }
    
    // Convert to indexed array
    $response = array_values($groupedData);
    
    echo json_encode([
        'success' => true,
        'data' => $response
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching data: ' . $e->getMessage()
    ]);
}
