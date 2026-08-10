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
    $dateFrom = $_GET['dateFrom'] ?? null;
    $dateTo = $_GET['dateTo'] ?? null;
    $status = $_GET['status'] ?? null;
    $companyId = $_GET['company_id'] ?? null;

    // Get currency symbol
    $currencyStmt = $pdo->prepare(
        "SELECT c.symbol 
         FROM tenant_currencies tc
         JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
         WHERE tc.tenant_id = ? LIMIT 1"
    );
    $currencyStmt->execute([$tenant_id]);
    $currencyResult = $currencyStmt->fetch(PDO::FETCH_ASSOC);
    $currencySymbol = $currencyResult['symbol'] ?? '$';
    
    $query = "SELECT jv.id, jv.voucher_number, jv.voucher_date, jv.description, 
                     jv.total_debit, jv.total_credit, jv.status, 
                     u.full_name as posted_by, jv.created_at as posted_on,
                     c.company_name
              FROM journal_voucher jv
              LEFT JOIN users u ON jv.created_by = u.id
              LEFT JOIN companies c ON jv.company_id = c.id
              WHERE jv.tenant_id = ?";
    
    $params = [$tenant_id];
    
    if ($dateFrom) {
        $query .= " AND jv.voucher_date >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $query .= " AND jv.voucher_date <= ?";
        $params[] = $dateTo;
    }
    
    if ($companyId) {
        $query .= " AND jv.company_id = ?";
        $params[] = $companyId;
    }
    
    $query .= " ORDER BY jv.voucher_date DESC, jv.id DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get entry lines for each voucher
    foreach ($entries as &$entry) {
        $stmt = $pdo->prepare(
            "SELECT a.name as account, jvl.debit, jvl.credit 
             FROM journal_voucher_line jvl
             JOIN accounts a ON jvl.account_id = a.id
             WHERE jvl.voucher_id = ?"
        );
        $stmt->execute([$entry['id']]);
        $entry['entries'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get stats
    $statsStmt = $pdo->prepare(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END) as posted,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft
         FROM journal_voucher WHERE tenant_id = ?"
    );
    $statsStmt->execute([$tenant_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending'] = 0;
    
    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'stats' => $stats,
        'currencySymbol' => $currencySymbol
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch journal entries: ' . $e->getMessage()]);
}