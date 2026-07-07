<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit(); }
if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT','POST'])) { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit(); }

$voucher_id = $_GET['id'] ?? null;
if (!$voucher_id) { http_response_code(400); echo json_encode(['error' => 'Voucher ID required']); exit(); }

try {
    // Support both multipart/form-data (POST) and JSON (PUT)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = $_POST;
    } else {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    $voucher_date      = $input['voucher_date']      ?? '';
    $company_id        = $input['company_id']        ?? null;
    $currency_id       = $input['currency_id']       ?? null;
    $amount            = floatval($input['amount']   ?? 0);
    $payment_method_id = $input['payment_method_id'] ?? null;
    $bank_account_id   = !empty($input['bank_account_id']) ? $input['bank_account_id'] : null;
    $cheque_no         = !empty($input['cheque_no'])        ? $input['cheque_no']        : null;
    $cheque_date       = !empty($input['cheque_date'])      ? $input['cheque_date']      : null;
    $slip_no           = !empty($input['slip_no'])          ? $input['slip_no']          : null;
    $description       = !empty($input['description'])      ? $input['description']      : null;

    $attachment = null;
    $keep_attachment = $input['keep_attachment'] ?? null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $allowedTypes = ['png','jpg','jpeg','gif','pdf','webp','avif'];
        if ($file['size'] > 5 * 1024 * 1024) { echo json_encode(['success'=>false,'error'=>'File size exceeds 5MB']); exit(); }
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedTypes)) { echo json_encode(['success'=>false,'error'=>'Invalid file type']); exit(); }
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
            else { echo json_encode(['success'=>false,'error'=>'Failed to process image']); exit(); }
        }
        $attachment = $finalFileName;
    } else {
        $attachment = $keep_attachment; // keep existing
    }

    // Get current voucher
    $stmt = $pdo->prepare("SELECT payment_method_id, from_type, to_type FROM transfer_voucher WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$voucher_id, $tenant_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) { echo json_encode(['success' => false, 'error' => 'Voucher not found']); exit(); }

    $pdo->beginTransaction();

    // Update voucher
    $stmt = $pdo->prepare("
        UPDATE transfer_voucher
        SET voucher_date = ?, company_id = ?, currency_id = ?, amount = ?,
            payment_method_id = ?, bank_account_id = ?, cheque_no = ?,
            cheque_date = ?, slip_no = ?, attachment = ?, description = ?, updated_by = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([
        $voucher_date, $company_id, $currency_id, $amount,
        $payment_method_id, $bank_account_id, $cheque_no,
        $cheque_date, $slip_no, $attachment, $description, $user_id,
        $voucher_id, $tenant_id
    ]);

    // Update PDC if was cheque
    if ($current['payment_method_id'] == 6) {
        $stmt = $pdo->prepare("UPDATE post_dated_cheques SET amount = ?, cheque_date = ? WHERE tenant_id = ? AND reference_table = 'transfer_voucher' AND reference_id = ?");
        $stmt->execute([$amount, $cheque_date, $tenant_id, $voucher_id]);
    }

    // Resolve new payment account ID
    if ($payment_method_id == 6) {
        $payment_account_id = 78;
    } elseif ($payment_method_id == 7) {
        $payment_account_id = 1;
    } else {
        if ($bank_account_id) {
            $stmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$bank_account_id, $tenant_id]);
            $bankRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $payment_account_id = $bankRow['account_id'] ?? 1;
        } else {
            $payment_account_id = 1;
        }
    }

    $from_account_id = ($current['from_type'] === 'customer') ? 2 : 14;
    $to_account_id   = ($current['to_type']   === 'customer') ? 2 : 14;

    // Delete old ledger entries and re-insert fresh
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND reference_table = 'transfer_voucher' AND reference_id = ?");
    $stmt->execute([$tenant_id, $voucher_id]);

    // Fix ledger entries with correct DR/CR per party type
    $from_type = $current['from_type'];
    $to_type   = $current['to_type'];
    $desc = $description ?? 'Transfer Voucher';
    $ledger = $pdo->prepare("
        INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
        VALUES (?, 'Transfer Voucher', 'transfer_voucher', ?, ?, ?, ?, ?, ?)
    ");
    if ($from_type === 'customer') {
        $ledger->execute([$tenant_id, $voucher_id, $from_account_id, $voucher_date, $desc, 0.00, $amount]);
    } else {
        $ledger->execute([$tenant_id, $voucher_id, $from_account_id, $voucher_date, $desc, $amount, 0.00]);
    }
    $ledger->execute([$tenant_id, $voucher_id, $payment_account_id, $voucher_date, $desc, 0.00,   $amount]);
    $ledger->execute([$tenant_id, $voucher_id, $payment_account_id, $voucher_date, $desc, $amount, 0.00]);
    if ($to_type === 'customer') {
        $ledger->execute([$tenant_id, $voucher_id, $to_account_id, $voucher_date, $desc, $amount, 0.00]);
    } else {
        $ledger->execute([$tenant_id, $voucher_id, $to_account_id, $voucher_date, $desc, 0.00, $amount]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Transfer voucher updated successfully']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
