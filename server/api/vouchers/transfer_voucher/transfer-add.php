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

try {
    // ── Generate voucher number ──────────────────────────────────────────────
    $currentYear = date('Y');
    $stmt = $pdo->prepare("SELECT voucher_number FROM transfer_voucher WHERE tenant_id = ? AND voucher_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tenant_id, 'TV-' . $currentYear . '-%']);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($last) {
        preg_match('/TV-\d{4}-(\d+)/', $last['voucher_number'], $m);
        $next = isset($m[1]) ? intval($m[1]) + 1 : 1;
    } else {
        $next = 1;
    }
    $voucher_number = 'TV-' . $currentYear . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);

    // ── Input ────────────────────────────────────────────────────────────────
    $voucher_date      = $_POST['voucher_date']      ?? '';
    $company_id        = $_POST['company_id']        ?? null;
    $currency_id       = $_POST['currency_id']       ?? null;
    $amount            = floatval($_POST['amount']   ?? 0);
    $payment_method_id = $_POST['payment_method_id'] ?? null;
    $bank_account_id   = !empty($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : null;
    $cheque_no         = !empty($_POST['cheque_no'])        ? $_POST['cheque_no']        : null;
    $cheque_date       = !empty($_POST['cheque_date'])      ? $_POST['cheque_date']      : null;
    $slip_no           = !empty($_POST['slip_no'])          ? $_POST['slip_no']          : null;
    $description       = !empty($_POST['description'])      ? $_POST['description']      : null;

    // Handle file upload
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $allowedTypes = ['png', 'jpg', 'jpeg', 'gif', 'pdf', 'webp', 'avif'];
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'File size exceeds 5MB limit']); exit();
        }
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type']); exit();
        }
        $uploadDir = '../../../../client/assets/uploads/transfer_voucher/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $fileName = uniqid() . '_' . time();
        if ($fileExt === 'pdf') {
            $finalFileName = $fileName . '.pdf';
            move_uploaded_file($file['tmp_name'], $uploadDir . $finalFileName);
        } else {
            $finalFileName = $fileName . '.webp';
            $imgFns = ['jpg'=>'imagecreatefromjpeg','jpeg'=>'imagecreatefromjpeg','png'=>'imagecreatefrompng','gif'=>'imagecreatefromgif','webp'=>'imagecreatefromwebp','avif'=>'imagecreatefromavif'];
            $image = ($imgFns[$fileExt] ?? null) ? $imgFns[$fileExt]($file['tmp_name']) : null;
            if ($image) { imagewebp($image, $uploadDir . $finalFileName, 80); imagedestroy($image); }
            else { echo json_encode(['success' => false, 'error' => 'Failed to process image']); exit(); }
        }
        $attachment = $finalFileName;
    }

    $from_type           = $_POST['from_type']           ?? 'customer';
    $from_party_code     = trim($_POST['from_party_code'] ?? '');
    $from_sub_account_id = !empty($_POST['from_sub_account_id']) ? intval($_POST['from_sub_account_id']) : null;

    $to_type           = $_POST['to_type']             ?? 'customer';
    $to_party_code     = trim($_POST['to_party_code']  ?? '');
    $to_sub_account_id = !empty($_POST['to_sub_account_id']) ? intval($_POST['to_sub_account_id']) : null;

    // ── Validate ─────────────────────────────────────────────────────────────
    if (!$voucher_date || !$company_id || !$currency_id || $amount <= 0 || !$payment_method_id) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }
    if (!$from_party_code || !$to_party_code) {
        echo json_encode(['success' => false, 'error' => 'From and To party are required']);
        exit();
    }
    if ($from_type === $to_type && $from_party_code === $to_party_code) {
        echo json_encode(['success' => false, 'error' => 'From and To party cannot be the same']);
        exit();
    }

    // ── Resolve FROM party ID ─────────────────────────────────────────────────
    if ($from_type === 'customer') {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE tenant_id = ? AND customer_code = ?");
    } else {
        $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE tenant_id = ? AND supplier_code = ?");
    }
    $stmt->execute([$tenant_id, $from_party_code]);
    $fromEntity = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fromEntity) {
        echo json_encode(['success' => false, 'error' => "From party '{$from_party_code}' not found"]);
        exit();
    }
    $from_id = $fromEntity['id'];

    // ── Resolve TO party ID ───────────────────────────────────────────────────
    if ($to_type === 'customer') {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE tenant_id = ? AND customer_code = ?");
    } else {
        $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE tenant_id = ? AND supplier_code = ?");
    }
    $stmt->execute([$tenant_id, $to_party_code]);
    $toEntity = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$toEntity) {
        echo json_encode(['success' => false, 'error' => "To party '{$to_party_code}' not found"]);
        exit();
    }
    $to_id = $toEntity['id'];

    // ── Resolve payment method account ID (same logic as receive/payment voucher)
    // payment_method_id = 6 → PDC (Cheque)
    // payment_method_id = 7 → Cash (account_id = 1)
    // anything else       → Bank account's account_id
    if ($payment_method_id == 6) {
        // Post-dated cheque — use PDC Receivable account (78, same as receive voucher)
        $payment_account_id = 78;
    } elseif ($payment_method_id == 7) {
        // Cash
        $payment_account_id = 1;
    } else {
        // Bank transfer — get account_id from bank_accounts table
        if ($bank_account_id) {
            $stmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$bank_account_id, $tenant_id]);
            $bankRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $payment_account_id = $bankRow['account_id'] ?? 1;
        } else {
            $payment_account_id = 1;
        }
    }

    // ── Account IDs for parties ───────────────────────────────────────────────
    // Customer → Trade Debtors  = account_id 2
    // Supplier → Trade Creditors = account_id 14
    $from_account_id = ($from_type === 'customer') ? 2 : 14;
    $to_account_id   = ($to_type   === 'customer') ? 2 : 14;

    // ── Begin transaction ─────────────────────────────────────────────────────
    $pdo->beginTransaction();

    // ── Insert transfer_voucher row ───────────────────────────────────────────
    $from_customer_id = ($from_type === 'customer') ? $from_id : null;
    $from_supplier_id = ($from_type === 'supplier') ? $from_id : null;
    $to_customer_id   = ($to_type   === 'customer') ? $to_id   : null;
    $to_supplier_id   = ($to_type   === 'supplier') ? $to_id   : null;

    $stmt = $pdo->prepare("
        INSERT INTO transfer_voucher (
            tenant_id, company_id, voucher_number, voucher_date,
            from_type, from_customer_id, from_supplier_id, from_sub_account_id,
            to_type,   to_customer_id,   to_supplier_id,   to_sub_account_id,
            amount, currency_id, payment_method_id, bank_account_id,
            cheque_no, cheque_date, slip_no, attachment, description,
            created_by, updated_by
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?
        )
    ");
    $stmt->execute([
        $tenant_id, $company_id, $voucher_number, $voucher_date,
        $from_type, $from_customer_id, $from_supplier_id, $from_sub_account_id,
        $to_type,   $to_customer_id,   $to_supplier_id,   $to_sub_account_id,
        $amount, $currency_id, $payment_method_id, $bank_account_id,
        $cheque_no, $cheque_date, $slip_no, $attachment, $description,
        $user_id, $user_id
    ]);
    $voucher_id = $pdo->lastInsertId();

    // ── Post-dated cheque record if method = 6 ────────────────────────────────
    // We record it against FROM party (they gave the cheque)
    if ($payment_method_id == 6) {
        if ($from_type === 'customer') {
            $stmt = $pdo->prepare("
                INSERT INTO post_dated_cheques (
                    tenant_id, company_id, cheque_no, bank_account_id, transaction_type,
                    customer_id, amount, cheque_date, reference_id, reference_table
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id, $company_id, $cheque_no, $bank_account_id, 'Transfer',
                $from_id, $amount, $cheque_date, $voucher_id, 'transfer_voucher'
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO post_dated_cheques (
                    tenant_id, company_id, cheque_no, bank_account_id, transaction_type,
                    supplier_id, amount, cheque_date, reference_id, reference_table
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id, $company_id, $cheque_no, $bank_account_id, 'Transfer',
                $from_id, $amount, $cheque_date, $voucher_id, 'transfer_voucher'
            ]);
        }
    }

    // ── Accounting Ledger Entries ─────────────────────────────────────────────
    //
    // This transfer is: FROM party paid TO party via cash/bank/cheque
    //
    // The accounting logic mirrors receive + payment vouchers combined:
    //
    // Step 1 — FROM party's balance REDUCES (they paid out)
    //   Same as Payment Voucher:
    //   DEBIT  → FROM party account (Trade Debtors/Creditors)  [reduces their receivable/payable]
    //   CREDIT → Payment method account (Cash/Bank/PDC)        [money left our hands]
    //
    // Step 2 — TO party's balance INCREASES (they received)
    //   Same as Receive Voucher:
    //   DEBIT  → Payment method account (Cash/Bank/PDC)        [money came in]
    //   CREDIT → TO party account (Trade Debtors/Creditors)    [their balance goes up]
    //
    // Net effect on payment_account_id = 0 (debit and credit cancel out)
    // Net effect: FROM party credited, TO party debited — clean transfer

    $ledger = $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, debit, credit
        ) VALUES (?, 'Transfer Voucher', 'transfer_voucher', ?, ?, ?, ?, ?, ?)
    ");

    $desc = $description ?? "Transfer: {$from_party_code} to {$to_party_code}";

    // Entry 1: FROM party balance reduces (they paid out)
    // Customer = Debit normal → Credit to reduce | Supplier = Credit normal → Debit to reduce
    if ($from_type === 'customer') {
        $ledger->execute([$tenant_id, $voucher_id, $from_account_id, $voucher_date, $desc, 0.00, $amount]); // CR customer
    } else {
        $ledger->execute([$tenant_id, $voucher_id, $from_account_id, $voucher_date, $desc, $amount, 0.00]); // DR supplier
    }

    // Entry 2: CREDIT payment method account (money left via cash/bank/cheque)
    $ledger->execute([$tenant_id, $voucher_id, $payment_account_id, $voucher_date, $desc, 0.00, $amount]);

    // Entry 3: DEBIT payment method account (money arrived via cash/bank/cheque)
    $ledger->execute([$tenant_id, $voucher_id, $payment_account_id, $voucher_date, $desc, $amount, 0.00]);

    // Entry 4: TO party balance increases (they received)
    // Customer = Debit normal → Debit to increase | Supplier = Credit normal → Credit to increase
    if ($to_type === 'customer') {
        $ledger->execute([$tenant_id, $voucher_id, $to_account_id, $voucher_date, $desc, $amount, 0.00]); // DR customer
    } else {
        $ledger->execute([$tenant_id, $voucher_id, $to_account_id, $voucher_date, $desc, 0.00, $amount]); // CR supplier
    }

    $pdo->commit();

    echo json_encode([
        'success'        => true,
        'message'        => 'Transfer voucher created successfully',
        'voucher_number' => $voucher_number,
        'voucher_id'     => $voucher_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error',
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine()
    ]);
}
?>
