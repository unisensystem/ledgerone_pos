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
$voucher_date      = $data['voucher_date']      ?? '';
$voucher_number    = $data['voucher_number']    ?? '';
$company_id        = $data['company_id']        ?? null;
$party_type        = $data['party_type']        ?? 'customer'; // 'customer' | 'supplier'
$party_id          = intval($data['party_id']   ?? 0);
$party_code        = trim($data['party_code']   ?? '');
$sub_account_id    = !empty($data['sub_account_id']) ? intval($data['sub_account_id']) : null;
$adj_type          = $data['adj_type']          ?? 'debit';   // 'debit' | 'credit'
$contra_account_id = intval($data['contra_account_id'] ?? 0);
$amount            = floatval($data['amount']   ?? 0);
$description       = trim($data['description']  ?? '');

// ── Validate ──────────────────────────────────────────────────────────────────
if (!$voucher_date || !$company_id || !$party_id || !$contra_account_id || $amount <= 0 || !$description) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

// ── Party account ID ──────────────────────────────────────────────────────────
// Customer  → Trade Debtors  = account_id 2
// Supplier  → Trade Creditors = account_id 14
$party_account_id = ($party_type === 'customer') ? 2 : 14;

// ── Determine debit/credit sides ──────────────────────────────────────────────
//
// Customer adj_type = 'debit'  → DR party account (2),  CR contra
// Customer adj_type = 'credit' → CR party account (2),  DR contra
//
// Supplier adj_type = 'debit'  → DR party account (14), CR contra  (reduces payable)
// Supplier adj_type = 'credit' → CR party account (14), DR contra  (increases payable)
//
// Both cases: same logic — adj_type tells which side the PARTY account goes on.

if ($adj_type === 'debit') {
    $party_debit  = $amount;
    $party_credit = 0.00;
    $contra_debit  = 0.00;
    $contra_credit = $amount;
} else {
    $party_debit  = 0.00;
    $party_credit = $amount;
    $contra_debit  = $amount;
    $contra_credit = 0.00;
}

try {
    $pdo->beginTransaction();

    // ── Generate voucher number if not provided ───────────────────────────────
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

    // ── Insert into journal_voucher ───────────────────────────────────────────
    $stmt = $pdo->prepare(
        "INSERT INTO journal_voucher
            (tenant_id, company_id, voucher_number, voucher_date, description, total_debit, total_credit, created_by, status, party_type, party_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'posted', ?, ?)"
    );
    $stmt->execute([
        $tenant_id, $company_id, $voucher_number, $voucher_date,
        $description, $amount, $amount, $user_id, $party_type, $party_id
    ]);
    $voucher_id = $pdo->lastInsertId();

    // ── Insert journal_voucher_line rows ──────────────────────────────────────
    $stmtLine = $pdo->prepare(
        "INSERT INTO journal_voucher_line (tenant_id, voucher_id, account_id, debit, credit)
         VALUES (?, ?, ?, ?, ?)"
    );
    // Party line
    $stmtLine->execute([$tenant_id, $voucher_id, $party_account_id, $party_debit, $party_credit]);
    // Contra line
    $stmtLine->execute([$tenant_id, $voucher_id, $contra_account_id, $contra_debit, $contra_credit]);

    // ── Insert into accounting_ledger ─────────────────────────────────────────
    $stmtLedger = $pdo->prepare(
        "INSERT INTO accounting_ledger
            (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
         VALUES (?, 'Journal Voucher', 'journal_voucher', ?, ?, ?, ?, ?, ?)"
    );
    // Party ledger entry
    $stmtLedger->execute([$tenant_id, $voucher_id, $party_account_id, $voucher_date, $description, $party_debit, $party_credit]);
    // Contra ledger entry
    $stmtLedger->execute([$tenant_id, $voucher_id, $contra_account_id, $voucher_date, $description, $contra_debit, $contra_credit]);

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
    echo json_encode([
        'error'   => 'Database error',
        'message' => $e->getMessage()
    ]);
}
