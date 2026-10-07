<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/posting_lock.php';
require_once '../../../../includes/activity-logger.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
$user_id = $auth['user_id'];
$tenant_id = $auth['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get form data
    $voucher_date = $_POST['voucher_date'] ?? '';
    $lockCheck = checkPostingDateAllowed($pdo, $tenant_id, 'receive_voucher', $voucher_date);

if (!$lockCheck['allowed']) {
    echo json_encode(['success' => false, 'error' => $lockCheck['message']]);
    exit();
}  $receive_type = $_POST['receive_type'] ?? 'customer';
    $customer_code = $_POST['customer_code'] ?? '';
    $previous_balance = (!empty($_POST['previous_balance']) && $_POST['previous_balance'] !== 'null') ? floatval($_POST['previous_balance']) : 0;
    $sub_account_id = (!empty($_POST['sub_account_id']) && $_POST['sub_account_id'] !== 'null') ? intval($_POST['sub_account_id']) : null;
    $bill_no = (!empty($_POST['bill_no']) && $_POST['bill_no'] !== 'null') ? $_POST['bill_no'] : null;
    $dsr_no = (!empty($_POST['dsr_no']) && $_POST['dsr_no'] !== 'null') ? $_POST['dsr_no'] : null;
    $project_id = (!empty($_POST['project_id']) && $_POST['project_id'] !== 'null') ? intval($_POST['project_id']) : null;
    $container_id = (!empty($_POST['container_id']) && $_POST['container_id'] !== 'null') ? intval($_POST['container_id']) : null;
    $adjust_amount = (!empty($_POST['adjust_amount']) && $_POST['adjust_amount'] !== 'null') ? floatval($_POST['adjust_amount']) : 0;
    $amount = $_POST['amount'] ?? 0;
    $currency_id = $_POST['currency_id'] ?? '';
    $payment_method_id = $_POST['payment_method_id'] ?? '';
    // Map Cash account (id=7 in dropdown) to id=1 for storage
    if ($payment_method_id == 7) $payment_method_id = 1;
    $bank_account_id = (!empty($_POST['bank_account']) && $_POST['bank_account'] !== 'null') ? intval($_POST['bank_account']) : null;
    $cheque_date = (!empty($_POST['cheque_date']) && $_POST['cheque_date'] !== 'null') ? $_POST['cheque_date'] : null;
    $cheque_no = (!empty($_POST['cheque_no']) && $_POST['cheque_no'] !== 'null') ? $_POST['cheque_no'] : null;
    $description = !empty($_POST['description']) ? $_POST['description'] : null;
    $attachment = null;
    $recovery_officer_id = (!empty($_POST['recovery_officer']) && $_POST['recovery_officer'] !== 'null') ? $_POST['recovery_officer'] : null;
    $against_pdc_id = (!empty($_POST['against_pdc_id']) && $_POST['against_pdc_id'] !== 'null') ? intval($_POST['against_pdc_id']) : null;

    // Look up the customer/supplier by code within the tenant.
    // The entity's own company_id is the source of truth and is used for the voucher.
    if ($receive_type === 'supplier') {
        $stmt = $pdo->prepare("
            SELECT id, company_id FROM suppliers
            WHERE tenant_id = ? AND supplier_code = ?
        ");
        $stmt->execute([$tenant_id, $customer_code]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            echo json_encode(['success' => false, 'error' => 'Supplier not found']);
            exit();
        }
    } else {
        $stmt = $pdo->prepare("
            SELECT id, company_id FROM customers
            WHERE tenant_id = ? AND customer_code = ?
        ");
        $stmt->execute([$tenant_id, $customer_code]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            echo json_encode(['success' => false, 'error' => 'Customer not found']);
            exit();
        }
    }

    // Company is derived from the entity, not from the form's Company dropdown
    $company_id = $customer['company_id'];

    // Idempotent sync: see sale_order's order-add.php for the full
    // rationale. A retried mobile sync with the same client_uuid gets back
    // the already-created voucher instead of a duplicate.
    $client_uuid = (!empty($_POST['client_uuid']) && $_POST['client_uuid'] !== 'null') ? trim($_POST['client_uuid']) : null;
    if ($client_uuid !== null) {
        $existingStmt = $pdo->prepare("SELECT id, voucher_number FROM receive_voucher WHERE tenant_id = ? AND client_uuid = ?");
        $existingStmt->execute([$tenant_id, $client_uuid]);
        $existingVoucher = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingVoucher) {
            echo json_encode([
                'success' => true,
                'message' => 'Voucher already synced',
                'voucher_id' => $existingVoucher['id'],
                'voucher_number' => $existingVoucher['voucher_number'],
                'already_synced' => true,
            ]);
            exit();
        }
    }

    // Generate sequential voucher number. This whole sequence (lookup through
    // the ledger inserts below) runs inside one transaction with FOR UPDATE
    // on the lookup, so a second concurrent request (e.g. two synced mobile
    // vouchers landing at once) blocks here instead of reading the same
    // "last number" and colliding on voucher_number — and so the voucher
    // row + ledger entries can never be only partially written.
    $pdo->beginTransaction();
    $currentYear = date('Y');
    $stmt = $pdo->prepare("SELECT voucher_number FROM receive_voucher WHERE tenant_id = ? AND voucher_number LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$tenant_id, 'RV-' . $currentYear . '-%']);
    $lastVoucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastVoucher) {
        // Extract number from last voucher (e.g., RV-2024-0001 -> 1)
        preg_match('/RV-\d{4}-(\d+)/', $lastVoucher['voucher_number'], $matches);
        $nextNumber = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $nextNumber = 1;
    }
    
    $voucher_number = 'RV-' . $currentYear . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $allowedTypes = ['png', 'jpg', 'jpeg', 'gif', 'pdf', 'webp', 'avif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Validate file size
        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'error' => 'File size exceeds 5MB limit']);
            exit();
        }
        
        // Validate file type
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type']);
            exit();
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = '../../../../client/assets/uploads/receive_voucher/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $fileName = uniqid() . '_' . time();
        
        if ($fileExt === 'pdf') {
            // Keep PDF as is
            $finalFileName = $fileName . '.pdf';
            $uploadPath = $uploadDir . $finalFileName;
            move_uploaded_file($file['tmp_name'], $uploadPath);
        } else {
            // Convert to WebP
            $finalFileName = $fileName . '.webp';
            $uploadPath = $uploadDir . $finalFileName;
            
            // Create image from uploaded file. Suppressed (@) deliberately —
            // a malformed/corrupt upload makes these emit PHP warnings
            // straight into the response body, which breaks EVERY client's
            // JSON parsing (not just a clean error message) since the
            // warnings print before the eventual json_encode() below. The
            // already-correct null check right after this switch handles
            // the failure case with a real JSON error either way.
            switch ($fileExt) {
                case 'jpg':
                case 'jpeg':
                    $image = @imagecreatefromjpeg($file['tmp_name']);
                    break;
                case 'png':
                    $image = @imagecreatefrompng($file['tmp_name']);
                    break;
                case 'gif':
                    $image = @imagecreatefromgif($file['tmp_name']);
                    break;
                case 'webp':
                    $image = @imagecreatefromwebp($file['tmp_name']);
                    break;
                case 'avif':
                    $image = @imagecreatefromavif($file['tmp_name']);
                    break;
            }
            
            if ($image) {
                imagewebp($image, $uploadPath, 80);
                imagedestroy($image);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to process image']);
                exit();
            }
        }
        
        $attachment = $finalFileName;
    }

    if ($receive_type === 'supplier') {
        $stmt = $pdo->prepare("
            INSERT INTO receive_voucher (
                tenant_id, company_id, voucher_number, voucher_date, supplier_id, sub_account_id, bill_no, dsr_no, project_id, container_id,
                amount, adjust_amount, previous_balance, currency_id, payment_method_id, bank_account_id, cheque_date, cheque_no, description, attachment,
                recovery_officer_id, against_pdc_id, client_uuid, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO receive_voucher (
                tenant_id, company_id, voucher_number, voucher_date, customer_id, sub_account_id, bill_no, dsr_no, project_id, container_id,
                amount, adjust_amount, previous_balance, currency_id, payment_method_id, bank_account_id, cheque_date, cheque_no, description, attachment,
                recovery_officer_id, against_pdc_id, client_uuid, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    }

    $stmt->execute([
        $tenant_id, $company_id, $voucher_number, $voucher_date, $customer['id'], $sub_account_id, $bill_no, $dsr_no, $project_id, $container_id,
        $amount, $adjust_amount, $previous_balance, $currency_id, $payment_method_id, $bank_account_id, $cheque_date, $cheque_no, $description, $attachment,
        $recovery_officer_id, $against_pdc_id, $client_uuid, $user_id, $user_id
    ]);

    $voucher_id = $pdo->lastInsertId();

    // Insert post-dated cheque if payment method is 6
    if ($payment_method_id == 6) {
        if ($receive_type === 'supplier') {
            $stmt = $pdo->prepare("
                INSERT INTO post_dated_cheques (
                    tenant_id, company_id, cheque_no, bank_account_id, transaction_type, supplier_id,
                    amount, cheque_date, reference_id, reference_table
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO post_dated_cheques (
                    tenant_id, company_id, cheque_no, bank_account_id, transaction_type, customer_id,
                    amount, cheque_date, reference_id, reference_table
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }

        $stmt->execute([
            $tenant_id, $company_id, $cheque_no, $bank_account_id, 'Received', $customer['id'],
            $amount, $cheque_date, $voucher_id, 'receive_voucher'
        ]);
    }
    
    // Determine debit account ID
    if ($payment_method_id == 6) {
        $debit_account_id = 78;
    } elseif ($payment_method_id == 1) {
        $debit_account_id = 1;
    } else {
        $stmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$bank_account_id, $tenant_id]);
        $bank_account = $stmt->fetch(PDO::FETCH_ASSOC);
        $debit_account_id = $bank_account['account_id'];
    }
    
    // Insert accounting ledger entries
     // Insert accounting ledger entries
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Actual cash/bank/cheque jo mila
    $stmt->execute([
        $tenant_id, 'Receive Voucher', 'receive_voucher', $voucher_id, $debit_account_id,
        $voucher_date, $description, $amount, 0.00
    ]);

    // Adjust amount — Adjustment account (id 406) mein debit
if ($adjust_amount > 0) {
    $stmt->execute([
        $tenant_id, 'Receive Voucher', 'receive_voucher', $voucher_id, 406,
        $voucher_date, $description, $adjust_amount, 0.00
    ]);
}
    // Customer's Trade Debtors (id 2) is credited (reduces what they owe), or
    // for a supplier receipt, Trade Creditors (id 14) is credited (increases what we owe them)
    $credit_account_id = $receive_type === 'supplier' ? 14 : 2;
    $stmt->execute([
        $tenant_id, 'Receive Voucher', 'receive_voucher', $voucher_id, $credit_account_id,
        $voucher_date, $description, 0.00, $amount + $adjust_amount
    ]);

    logActivity(
        $pdo, $tenant_id, $user_id, 'create', 'Receive Voucher', $voucher_id, $voucher_number,
        "Created receive voucher \"$voucher_number\" (amount " . number_format($amount, 2) . ")",
        null,
        [
            'voucher_number' => $voucher_number, 'voucher_date' => $voucher_date, 'receive_type' => $receive_type, 'customer_id' => $customer['id'],
            'company_id' => $company_id, 'bill_no' => $bill_no, 'amount' => $amount, 'adjust_amount' => $adjust_amount,
            'currency_id' => $currency_id, 'payment_method_id' => $payment_method_id, 'bank_account_id' => $bank_account_id,
            'cheque_no' => $cheque_no, 'description' => $description
        ]
    );

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Receive voucher created successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
