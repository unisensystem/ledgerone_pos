<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
$username = $_SESSION['username'] ?? 'System';

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $branch_id     = $data['branch_id']     ?? null;
    $counter_id    = !empty($data['counter_id']) ? intval($data['counter_id']) : null;
    $as_of_date    = $data['as_of_date']    ?? null;
    $opening_amount = $data['opening_amount'] ?? null;
    $currency_id   = $data['currency']      ?? null;
    $company_id    = $data['company_id']    ?? null;

    if (!$branch_id || !$as_of_date || !$opening_amount || !$currency_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // NULL-safe duplicate check: counter_id <=> ? treats NULL = NULL as equal
        $stmt = $pdo->prepare("SELECT id FROM cash_opening WHERE tenant_id = ? AND branch_id = ? AND as_of_date = ? AND counter_id <=> ?");
        $stmt->execute([$tenant_id, $branch_id, $as_of_date, $counter_id]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(409);
            $label = $counter_id ? 'this counter on this date' : 'this branch on this date (branch level)';
            echo json_encode(['error' => "Opening cash already exists for $label"]);
            exit();
        }

        // Insert cash opening record
        $stmt = $pdo->prepare("
            INSERT INTO cash_opening (tenant_id, company_id, branch_id, counter_id, as_of_date, opening_amount, currency_id, entered_by, is_locked)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$tenant_id, $company_id, $branch_id, $counter_id, $as_of_date, $opening_amount, $currency_id, $username]);
        $cash_opening_id = $pdo->lastInsertId();
        
        // Create accounting entries: Debit Cash, Credit Opening Balance Equity
        $stmt = $pdo->prepare("
            INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Debit Cash
        $stmt->execute([
            $tenant_id, 
            'CASH_OPENING', 
            'cash_opening', 
            $cash_opening_id, 
            1, 
            $as_of_date, 
            'Opening Cash In Hand', 
            $opening_amount, 
            0
        ]);
        
        // Credit Opening Balance Equity
        $stmt->execute([
            $tenant_id, 
            'CASH_OPENING', 
            'cash_opening', 
            $cash_opening_id, 
            90, 
            $as_of_date, 
            'Opening Cash In Hand', 
            0, 
            $opening_amount
        ]);
        
        logActivity(
            $pdo, $tenant_id, $user_id, 'create', 'Cash Opening', $cash_opening_id, $as_of_date,
            "Created cash opening (as of $as_of_date): Amount " . number_format($opening_amount, 2),
            null,
            ['as_of_date' => $as_of_date, 'branch_id' => $branch_id, 'counter_id' => $counter_id, 'company_id' => $company_id, 'opening_amount' => $opening_amount, 'currency_id' => $currency_id]
        );

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $cash_opening_id]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save record', 'message' => $e->getMessage(), 'details' => $e->getTraceAsString()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT
                co.id,
                co.as_of_date,
                co.opening_amount,
                co.currency_id,
                co.entered_by,
                co.updated_at,
                b.branch_name,
                c.company_name,
                cur.code   AS currency,
                cur.symbol AS currency_symbol,
                ct.counter_name
            FROM cash_opening co
            LEFT JOIN branches  b   ON b.id  = co.branch_id  AND b.tenant_id  = co.tenant_id
            LEFT JOIN companies c   ON c.id  = co.company_id AND c.tenant_id  = co.tenant_id
            LEFT JOIN ledgerone_public.currencies cur ON cur.id = co.currency_id
            LEFT JOIN counters  ct  ON ct.id = co.counter_id
            WHERE co.tenant_id = ?
            ORDER BY co.as_of_date DESC, co.id DESC
        ");
        $stmt->execute([$tenant_id]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'records' => $records]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch records', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}