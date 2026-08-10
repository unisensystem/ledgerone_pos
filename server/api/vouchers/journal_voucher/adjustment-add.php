<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) session_start();

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit();
}

// ── Inputs ────────────────────────────────────────────────────────────────────
$adj_mode          = $data['adj_mode']          ?? 'single'; // 'single' | 'cross'
$voucher_date      = $data['voucher_date']      ?? '';
$voucher_number    = $data['voucher_number']    ?? '';
$company_id        = $data['company_id']        ?? null;
$description       = trim($data['description']  ?? '');
$amount            = floatval($data['amount']   ?? 0);

// Party 1
$party_type        = $data['party_type']        ?? 'customer';
$party_id          = intval($data['party_id']   ?? 0);
$party_code        = trim($data['party_code']   ?? '');
$sub_account_id    = !empty($data['sub_account_id'])  ? intval($data['sub_account_id'])  : null;

// Party 2 (cross mode)
$party_type2       = $data['party_type2']       ?? 'customer';
$party_id2         = !empty($data['party_id2'])  ? intval($data['party_id2'])  : null;
$party_code2       = trim($data['party_code2']  ?? '');
$sub_account_id2   = !empty($data['sub_account_id2']) ? intval($data['sub_account_id2']) : null;

// Single mode
$adj_type          = $data['adj_type']          ?? 'debit';
$contra_account_id = !empty($data['contra_account_id']) ? intval($data['contra_account_id']) : null;

// ── Validate ──────────────────────────────────────────────────────────────────
if (!$voucher_date || !$company_id || !$party_id || $amount <= 0 || !$description) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

if ($adj_mode === 'cross') {
    if (!$party_id2) {
        http_response_code(400);
        echo json_encode(['error' => 'Party 2 required for cross-party adjustment']);
        exit();
    }
} else {
    if (!$contra_account_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Contra account required for single-party adjustment']);
        exit();
    }
}

// ── Account IDs ───────────────────────────────────────────────────────────────
// Customer → Trade Debtors  = account_id 2
// Supplier → Trade Creditors = account_id 14
$acct1 = ($party_type  === 'customer') ? 2 : 14;
$acct2 = ($party_type2 === 'customer') ? 2 : 14;

try {
    $pdo->beginTransaction();

    // ── Generate voucher number ───────────────────────────────────────────────
    if (!$voucher_number || $voucher_number === 'Auto-generated') {
        $year = date('Y');
        $stmt = $pdo->prepare(
            "SELECT voucher_number FROM journal_voucher
             WHERE tenant_id = ? AND voucher_number LIKE ?
             ORDER BY CAST(SUBSTRING_INDEX(voucher_number, '-', -1) AS UNSIGNED) DESC LIMIT 1"
        );
        $stmt->execute([$tenant_id, "ADJ-{$year}-%"]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);
        $next = 1;
        if ($last) {
            preg_match('/(\d+)$/', $last['voucher_number'], $m);
            $next = isset($m[1]) ? intval($m[1]) + 1 : 1;
        }
        $voucher_number = "ADJ-{$year}-" . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    // ── Insert journal_voucher ────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        "INSERT INTO journal_voucher
            (tenant_id, company_id, voucher_number, voucher_date, description, total_debit, total_credit, created_by, status, party_type, party_id, adj_mode)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'posted', ?, ?, ?)"
    );
    $stmt->execute([
        $tenant_id, $company_id, $voucher_number, $voucher_date,
        $description, $amount, $amount, $user_id, $party_type, $party_id, $adj_mode
    ]);
    $voucher_id = $pdo->lastInsertId();

    $stmtLine = $pdo->prepare(
        "INSERT INTO journal_voucher_line (tenant_id, voucher_id, account_id, debit, credit)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmtLedger = $pdo->prepare(
        "INSERT INTO accounting_ledger
            (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
         VALUES (?, 'Journal Voucher', 'journal_voucher', ?, ?, ?, ?, ?, ?)"
    );

    if ($adj_mode === 'cross') {
        // adj_type tells which side Party1 goes:
        // 'debit'  => Party1 DR, Party2 CR
        // 'credit' => Party1 CR, Party2 DR
        if ($adj_type === 'debit') {
            $p1_debit = $amount; $p1_credit = 0.00;
            $p2_debit = 0.00;   $p2_credit = $amount;
        } else {
            $p1_debit = 0.00;   $p1_credit = $amount;
            $p2_debit = $amount; $p2_credit = 0.00;
        }

        // journal_voucher_line
        $stmtLine->execute([$tenant_id, $voucher_id, $acct1, $p1_debit, $p1_credit]);
        $stmtLine->execute([$tenant_id, $voucher_id, $acct2, $p2_debit, $p2_credit]);

        // accounting_ledger
        $stmtLedger->execute([$tenant_id, $voucher_id, $acct1, $voucher_date, $description, $p1_debit, $p1_credit]);
        $stmtLedger->execute([$tenant_id, $voucher_id, $acct2, $voucher_date, $description, $p2_debit, $p2_credit]);

        // Store party2 info
        $pdo->prepare("UPDATE journal_voucher SET party_type2 = ?, party_id2 = ? WHERE id = ?")
            ->execute([$party_type2, $party_id2, $voucher_id]);

    } else {
        // ── Single-party ─────────────────────────────────────────────────────
        if ($adj_type === 'debit') {
            $party_debit = $amount; $party_credit = 0.00;
            $contra_debit = 0.00;  $contra_credit = $amount;
        } else {
            $party_debit = 0.00;   $party_credit = $amount;
            $contra_debit = $amount; $contra_credit = 0.00;
        }

        $stmtLine->execute([$tenant_id, $voucher_id, $acct1,           $party_debit,  $party_credit]);
        $stmtLine->execute([$tenant_id, $voucher_id, $contra_account_id, $contra_debit, $contra_credit]);

        $stmtLedger->execute([$tenant_id, $voucher_id, $acct1,           $voucher_date, $description, $party_debit,  $party_credit]);
        $stmtLedger->execute([$tenant_id, $voucher_id, $contra_account_id, $voucher_date, $description, $contra_debit, $contra_credit]);
    }

    $pdo->commit();

    echo json_encode([
        'success'        => true,
        'message'        => 'Adjustment posted successfully',
        'voucher_number' => $voucher_number,
        'voucher_id'     => $voucher_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
