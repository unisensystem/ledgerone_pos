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

$startDate = $_GET['startDate'] ?? null;
$endDate = $_GET['endDate'] ?? null;
$accountNumber = $_GET['accountNumber'] ?? '';
$transactionType = $_GET['transactionType'] ?? '';
$minAmount = $_GET['minAmount'] ?? null;
$maxAmount = $_GET['maxAmount'] ?? null;
$searchText = trim($_GET['searchText'] ?? '');
$company_id = $_GET['company_id'] ?? null;

try {
    // Account id 110 ("Freight Inward") and 107 ("Input Tax Receivable") are deliberately excluded from this report per request.
    $company_filter = ($company_id ? " AND (si.company_id = ? OR pi.company_id = ? OR rv.company_id = ? OR pv.company_id = ? OR ev.company_id = ? OR jv.company_id = ? OR tv.company_id = ?)" : "");
    
    $query = "SELECT 
        al.id,
        al.date,
        al.account_id,
        a.sub_account_id as account_number,
        a.name as account_name,
        al.description,
        CONCAT(al.reference_table, '-', al.reference_id) as reference,
        al.debit,
        al.credit
    FROM accounting_ledger al
    JOIN accounts a ON al.account_id = a.id
    LEFT JOIN sale_invoice si ON al.reference_table = 'sale_invoice' AND al.reference_id = si.id
    LEFT JOIN purchase_invoice pi ON al.reference_table = 'purchase_invoice' AND al.reference_id = pi.id
    LEFT JOIN receive_voucher rv ON al.reference_table = 'receive_voucher' AND al.reference_id = rv.id
    LEFT JOIN payment_voucher pv ON al.reference_table = 'payment_voucher' AND al.reference_id = pv.id
    LEFT JOIN expense_voucher ev ON al.reference_table = 'expense_voucher' AND al.reference_id = ev.id
    LEFT JOIN journal_voucher jv ON al.reference_table = 'journal_voucher' AND al.reference_id = jv.id
    LEFT JOIN transfer_voucher tv ON al.reference_table = 'transfer_voucher' AND al.reference_id = tv.id
    WHERE al.tenant_id = ? AND al.account_id NOT IN (110, 107){$company_filter}";
    
    $params = [$tenant_id];
    if ($company_id) {
        $params[] = $company_id;
        $params[] = $company_id;
        $params[] = $company_id;
        $params[] = $company_id;
        $params[] = $company_id;
        $params[] = $company_id;
        $params[] = $company_id;
    }
    
    if ($startDate) {
        $query .= " AND al.date >= ?";
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $query .= " AND al.date <= ?";
        $params[] = $endDate;
    }
    
    if ($accountNumber) {
        $query .= " AND (a.sub_account_id = ? OR a.id = ?)";
        $params[] = $accountNumber;
        $params[] = $accountNumber;
    }
    
    if ($transactionType === 'debit') {
        $query .= " AND al.debit > 0";
    } elseif ($transactionType === 'credit') {
        $query .= " AND al.credit > 0";
    }
    
    if ($minAmount) {
        $query .= " AND (al.debit >= ? OR al.credit >= ?)";
        $params[] = $minAmount;
        $params[] = $minAmount;
    }
    
    if ($maxAmount) {
        $query .= " AND (al.debit <= ? OR al.credit <= ?)";
        $params[] = $maxAmount;
        $params[] = $maxAmount;
    }
    
    // Global search: date, account number, account name, description, reference, debit, credit
    if ($searchText !== '') {
        $query .= " AND (
            al.description LIKE ?
            OR a.name LIKE ?
            OR a.sub_account_id LIKE ?
            OR CONCAT(al.reference_table, '-', al.reference_id) LIKE ?
            OR CAST(al.date AS CHAR) LIKE ?
            OR CAST(al.debit AS CHAR) LIKE ?
            OR CAST(al.credit AS CHAR) LIKE ?
        )";
        $like = "%$searchText%";
        $amountLike = '%' . str_replace(',', '', $searchText) . '%'; // so "1,500" matches 1500.00
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $amountLike;
        $params[] = $amountLike;
    }
    
    $query .= " ORDER BY al.date, al.id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalDebits = 0;
    $totalCredits = 0;
    $accountBalances = [];
    
    foreach ($entries as &$entry) {
        $totalDebits += $entry['debit'];
        $totalCredits += $entry['credit'];
        
        // Use account_id (unique) as key — sub_account_id can collide across different accounts
        $accountKey = $entry['account_id'];
        if (!isset($accountBalances[$accountKey])) {
            $accountBalances[$accountKey] = 0;
        }
        $accountBalances[$accountKey] += ($entry['debit'] - $entry['credit']);
        $entry['balance'] = $accountBalances[$accountKey];
    }
    
    $uniqueAccounts = count($accountBalances);
    
    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'summary' => [
            'totalDebits' => $totalDebits,
            'totalCredits' => $totalCredits,
            'transactionCount' => count($entries),
            'activeAccounts' => $uniqueAccounts,
            'isBalanced' => abs($totalDebits - $totalCredits) < 0.01
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}