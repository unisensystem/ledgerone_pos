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

$customer_code = $_GET['customer_code'] ?? '';

if (!$customer_code) {
    echo json_encode(['success' => true, 'data' => []]);
    exit();
}

try {
    // Get company IDs this user has access to (within current tenant)
    $companyStmt = $pdo->prepare("
        SELECT company_id
        FROM user_companies
        WHERE user_id = ? AND tenant_id = ?
    ");
    $companyStmt->execute([$user_id, $tenant_id]);
    $companyIds = $companyStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($companyIds)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }

    $placeholders = implode(',', array_fill(0, count($companyIds), '?'));

    // First get customer ID from customer_code, restricted to user's assigned companies
    $stmt = $pdo->prepare("
        SELECT id FROM customers
        WHERE tenant_id = ? AND customer_code = ? AND company_id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$tenant_id, $customer_code], $companyIds));
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }
    
    // Get bills from sale_invoice
    $stmt = $pdo->prepare("
        SELECT si.bill_no, si.net_amount, si.sale_date, c.symbol as currency_symbol
        FROM sale_invoice si
        LEFT JOIN ledgerone_public.currencies c ON si.currency_id = c.id
        WHERE si.tenant_id = ? AND si.customer_id = ?
    ");
    $stmt->execute([$tenant_id, $customer['id']]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get bills from opening_balance_invoices
    $stmt = $pdo->prepare("
        SELECT obi.invoice_number as bill_no, obi.debit as net_amount, obi.invoice_date as sale_date, c.symbol as currency_symbol
        FROM opening_balance_invoices obi
        LEFT JOIN tenant_currencies tc ON obi.tenant_id = tc.tenant_id AND tc.is_base_currency = 1
        LEFT JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
        WHERE obi.tenant_id = ? AND obi.customer_id = ?
    ");
    $stmt->execute([$tenant_id, $customer['id']]);
    $openingBills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge both arrays
    $allBills = array_merge($bills, $openingBills);
    
    // Sort by date descending
    usort($allBills, function($a, $b) {
        return strtotime($b['sale_date']) - strtotime($a['sale_date']);
    });
    
    echo json_encode(['success' => true, 'data' => $allBills]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
