<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

if (!$start_date || !$end_date) {
    echo json_encode(['success' => false, 'message' => 'Start date and end date are required']);
    exit;
}

try {
    // Get all Partner Accounts for this tenant
    $stmt = $pdo->prepare("SELECT id, name FROM accounts WHERE tenant_id = ? AND is_partner_account = 1");
    $stmt->execute([$tenant_id]);
    $partnerAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    foreach ($partnerAccounts as $account) {
        // Net profit contribution of this account for the selected period (credit - debit)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(credit), 0) AS total_credit, COALESCE(SUM(debit), 0) AS total_debit
                                FROM accounting_ledger
                                WHERE account_id = ? AND tenant_id = ? AND date BETWEEN ? AND ?");
        $stmt->execute([$account['id'], $tenant_id, $start_date, $end_date]);
        $ledger = $stmt->fetch(PDO::FETCH_ASSOC);
        $profit = (float)$ledger['total_credit'] - (float)$ledger['total_debit'];

        // Get partners defined for this account
        $stmt = $pdo->prepare("SELECT partner_name, percentage FROM account_partners WHERE account_id = ? AND tenant_id = ?");
        $stmt->execute([$account['id'], $tenant_id]);
        $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $partnerTotalPercentage = 0;
        $partnerRows = [];

        foreach ($partners as $partner) {
            $percentage = (float)$partner['percentage'];
            $partnerTotalPercentage += $percentage;
            $partnerRows[] = [
                'partner_name' => $partner['partner_name'],
                'percentage' => $percentage,
                'profit_share' => round($profit * ($percentage / 100), 2)
            ];
        }

        // Remaining share always goes to Self / Owner (never stored, always calculated)
        $selfPercentage = 100 - $partnerTotalPercentage;
        if ($selfPercentage < 0) $selfPercentage = 0;

        $partnerRows[] = [
            'partner_name' => 'Self / Owner',
            'percentage' => $selfPercentage,
            'profit_share' => round($profit * ($selfPercentage / 100), 2)
        ];

        $result[] = [
            'account_id' => $account['id'],
            'account_name' => $account['name'],
            'total_profit' => round($profit, 2),
            'partners' => $partnerRows
        ];
    }

    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>