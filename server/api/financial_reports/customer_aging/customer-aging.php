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
    $company_id = !empty($_GET['company_id']) ? $_GET['company_id'] : null;
    
    // Get currency symbol
    $stmt = $pdo->prepare("
        SELECT c.symbol 
        FROM tenant_currencies tc 
        JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
        WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
    ");
    $stmt->execute([$tenant_id]);
    $currency = $stmt->fetch();
    $currency_symbol = $currency['symbol'] ?? '$';

    // Calculate aging for each invoice
    $stmt = $pdo->prepare("
        SELECT 
            si.id,
            si.bill_no,
            si.sale_date,
            si.net_amount,
            c.customer_code,
            c.customer_name,
            c.address,
            COALESCE(SUM(rv.amount), 0) as paid_amount,
            DATEDIFF(CURDATE(), si.sale_date) as days_overdue,
            'Sale Invoice' as invoice_type
        FROM sale_invoice si
        JOIN customers c ON si.customer_id = c.id
        LEFT JOIN receive_voucher rv ON rv.bill_no = si.bill_no AND rv.tenant_id = ?
        WHERE si.tenant_id = ? AND si.status = 'Posted'
        " . ($company_id ? " AND si.company_id = ?" : "") . "
        GROUP BY si.id
        HAVING (si.net_amount - paid_amount) > 0 AND DATEDIFF(CURDATE(), si.sale_date) >= 15
        
        UNION ALL
        
        SELECT 
            obi.id,
            obi.invoice_number as bill_no,
            obi.invoice_date as sale_date,
            obi.debit as net_amount,
            c.customer_code,
            c.customer_name,
            c.address,
            COALESCE(SUM(rv.amount), 0) as paid_amount,
            DATEDIFF(CURDATE(), obi.invoice_date) as days_overdue,
            'Opening Balance' as invoice_type
        FROM opening_balance_invoices obi
        JOIN customers c ON obi.customer_id = c.id
        LEFT JOIN receive_voucher rv ON rv.bill_no = obi.invoice_number AND rv.tenant_id = ?
        WHERE obi.tenant_id = ?
        " . ($company_id ? " AND c.company_id = ?" : "") . "
        GROUP BY obi.id
        HAVING (obi.debit - paid_amount) > 0 AND DATEDIFF(CURDATE(), obi.invoice_date) >= 15
        
        ORDER BY days_overdue DESC
    ");
    $params = [$tenant_id, $tenant_id];
    if ($company_id) $params[] = $company_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    if ($company_id) $params[] = $company_id;
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reportData = [];
    $summary = [
        'total_overdue' => 0,
        'bucket_15' => 0,
        'bucket_25' => 0,
        'bucket_45' => 0,
        'count_15' => 0,
        'count_25' => 0,
        'count_45' => 0
    ];

    foreach ($invoices as $index => $invoice) {
        $outstanding = $invoice['net_amount'] - $invoice['paid_amount'];
        $days = (int)$invoice['days_overdue'];
        
        // Determine bucket
        $bucket = '<15';
        if ($days >= 45) {
            $bucket = '45+';
            $summary['bucket_45'] += $outstanding;
            $summary['count_45']++;
        } elseif ($days >= 35) {
            $bucket = '35+';
        } elseif ($days >= 25) {
            $bucket = '25+';
            $summary['bucket_25'] += $outstanding;
            $summary['count_25']++;
        } elseif ($days >= 15) {
            $bucket = '15+';
            $summary['bucket_15'] += $outstanding;
            $summary['count_15']++;
        }

        $summary['total_overdue'] += $outstanding;

        $reportData[] = [
            'id' => $index + 1,
            'customerCode' => $invoice['customer_code'],
            'customerName' => $invoice['customer_name'],
            'address' => $invoice['address'] ?? 'N/A',
            'invoiceNo' => $invoice['bill_no'],
            'invoiceDate' => date('Y-m-d', strtotime($invoice['sale_date'])),
            'type' => $invoice['invoice_type'],
            'amount' => $currency_symbol . number_format($outstanding, 2),
            'daysOverdue' => $days,
            'bucket' => $bucket
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $reportData,
        'summary' => [
            'total_overdue' => $currency_symbol . number_format($summary['total_overdue'], 2),
            'bucket_15' => $currency_symbol . number_format($summary['bucket_15'], 2),
            'bucket_25' => $currency_symbol . number_format($summary['bucket_25'], 2),
            'bucket_45' => $currency_symbol . number_format($summary['bucket_45'], 2),
            'count_15' => $summary['count_15'],
            'count_25' => $summary['count_25'],
            'count_45' => $summary['count_45']
        ],
        'currency_symbol' => $currency_symbol
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}