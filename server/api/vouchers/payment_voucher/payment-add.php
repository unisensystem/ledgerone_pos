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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Company is mandatory
    $company_id = $_POST['company_id'] ?? null;
    if (empty($company_id)) {
        echo json_encode(['success' => false, 'error' => 'Company is required']);
        exit();
    }

    // Generate sequential voucher number
    $stmt = $pdo->prepare("SELECT voucher_number FROM payment_voucher WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tenant_id]);
    $lastVoucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastVoucher) {
        // Extract number from last voucher (e.g., PV-2024-0001 -> 1)
        preg_match('/PV-\d{4}-(\d+)/', $lastVoucher['voucher_number'], $matches);
        $nextNumber = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $nextNumber = 1;
    }
    
    $voucher_number = 'PV-' . date('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    
    // Get form data
    $payment_type = $_POST['payment_type'] ?? 'supplier';
    $voucher_date = $_POST['voucher_date'] ?? '';
    $supplier_code = $_POST['supplier_code'] ?? '';
    $sub_account_id = !empty($_POST['sub_account_id']) ? $_POST['sub_account_id'] : null;
    $bill_no = !empty($_POST['bill_no']) ? $_POST['bill_no'] : null;
    $amount = $_POST['amount'] ?? 0;
    $currency_id = $_POST['currency_id'] ?? '';
    $payment_method_id = $_POST['payment_method_id'] ?? '';
    $bank_account_id = !empty($_POST['bank_account']) ? $_POST['bank_account'] : null;
    $cheque_no = !empty($_POST['cheque_no']) ? $_POST['cheque_no'] : null;
    $cheque_date = !empty($_POST['cheque_date']) ? $_POST['cheque_date'] : null;
    $description = !empty($_POST['description']) ? $_POST['description'] : null;
    $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $container_id = !empty($_POST['container_id']) ? $_POST['container_id'] : null;
    $expense_voucher_ref_id = !empty($_POST['expense_voucher_ref_id']) ? $_POST['expense_voucher_ref_id'] : null;
    $attachment = null;

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
        $uploadDir = '../../../../client/assets/uploads/payment_voucher/';
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
            
            // Create image from uploaded file
            switch ($fileExt) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($file['tmp_name']);
                    break;
                case 'png':
                    $image = imagecreatefrompng($file['tmp_name']);
                    break;
                case 'gif':
                    $image = imagecreatefromgif($file['tmp_name']);
                    break;
                case 'webp':
                    $image = imagecreatefromwebp($file['tmp_name']);
                    break;
                case 'avif':
                    $image = imagecreatefromavif($file['tmp_name']);
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

    // Get supplier/customer ID from code
    if ($payment_type === 'customer') {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE tenant_id = ? AND customer_code = ?");
        $stmt->execute([$tenant_id, $supplier_code]);
        $entity = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entity) {
            echo json_encode(['success' => false, 'error' => 'Customer not found']);
            exit();
        }
    } else {
        $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE tenant_id = ? AND supplier_code = ?");
        $stmt->execute([$tenant_id, $supplier_code]);
        $entity = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entity) {
            echo json_encode(['success' => false, 'error' => 'Supplier not found']);
            exit();
        }
    }

    // Insert payment voucher
    if ($payment_type === 'customer') {
        $stmt = $pdo->prepare("
            INSERT INTO payment_voucher (
                tenant_id, company_id, voucher_number, voucher_date, customer_id, sub_account_id, bill_no, 
                amount, currency_id, payment_method_id, bank_account_id, cheque_no, cheque_date, description, attachment, project_id, container_id, expense_voucher_ref_id,
                created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO payment_voucher (
                tenant_id, company_id, voucher_number, voucher_date, supplier_id, sub_account_id, bill_no, 
                amount, currency_id, payment_method_id, bank_account_id, cheque_no, cheque_date, description, attachment, project_id, container_id, expense_voucher_ref_id,
                created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    }
    
    $stmt->execute([
        $tenant_id, $company_id, $voucher_number, $voucher_date, $entity['id'], $sub_account_id, $bill_no,
        $amount, $currency_id, $payment_method_id, $bank_account_id, $cheque_no, $cheque_date, $description, $attachment, $project_id, $container_id, $expense_voucher_ref_id,
        $user_id, $user_id
    ]);
    
    $voucher_id = $pdo->lastInsertId();
    
    // Insert post-dated cheque if payment method is 6
    if ($payment_method_id == 6) {
        if ($payment_type === 'customer') {
            $stmt = $pdo->prepare("
                INSERT INTO post_dated_cheques (
                    tenant_id, company_id, cheque_no, bank_account_id, transaction_type, customer_id,
                    amount, cheque_date, reference_id, reference_table
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO post_dated_cheques (
                    tenant_id, company_id, cheque_no, bank_account_id, transaction_type, supplier_id,
                    amount, cheque_date, reference_id, reference_table
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }
        
        $stmt->execute([
            $tenant_id, $company_id, $cheque_no, $bank_account_id, 'Payment', $entity['id'],
            $amount, $cheque_date, $voucher_id, 'payment_voucher'
        ]);
    }
    
    // Determine debit and credit account IDs
    if ($payment_method_id == 6) {
        $credit_account_id = 79; // Post Dated Cheques Receivable
        $debit_account_id = $payment_type === 'customer' ? 2 : 14; // Trade Debtors or Trade Creditors
    } else {
        $debit_account_id = $payment_type === 'customer' ? 2 : 14; // Trade Debtors or Trade Creditors
        if ($payment_method_id == 7) {
            $credit_account_id = 1; // Cash
        } else {
            if ($bank_account_id) {
                $stmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$bank_account_id, $tenant_id]);
                $bank_account = $stmt->fetch(PDO::FETCH_ASSOC);
                $credit_account_id = $bank_account['account_id'] ?? 1;
            } else {
                $credit_account_id = 1; // Default to Cash if no bank account
            }
        }
    }
    
    // Insert accounting ledger entries
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id, account_id, 
            date, description, debit, credit
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Debit entry
    $stmt->execute([
        $tenant_id, 'Payment Voucher', 'payment_voucher', $voucher_id, $debit_account_id,
        $voucher_date, $description, $amount, 0.00
    ]);
    
    // Credit entry
    $stmt->execute([
        $tenant_id, 'Payment Voucher', 'payment_voucher', $voucher_id, $credit_account_id,
        $voucher_date, $description, 0.00, $amount
    ]);

    echo json_encode(['success' => true, 'message' => 'Payment voucher created successfully']);

} catch (Exception $e) {
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