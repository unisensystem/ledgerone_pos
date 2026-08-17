<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get form data from POST
    $input = $_POST;
    
    // Validate required fields
    $required = ['bankName', 'accountNumber', 'accountType', 'currency', 'branchName', 'branchCity', 'branchState', 'asOfDate'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("$field is required");
        }
    }

    // Validate currency_id belongs to this tenant
    $stmt = $pdo->prepare("SELECT id FROM tenant_currencies WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['currency'], $tenant_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid currency selected');
    }
    
    // Handle file upload
    $bankLogoPath = null;
    if (isset($_FILES['bankLogo']) && $_FILES['bankLogo']['error'] === UPLOAD_ERR_OK) {
        try {
            $baseDir = realpath(__DIR__ . '/../../../../');
            if (!$baseDir) {
                throw new Exception('Could not determine base directory');
            }
            $uploadDir = $baseDir . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'bank_logo' . DIRECTORY_SEPARATOR;
            
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    throw new Exception('Could not create upload directory');
                }
            }
            
            if (!is_writable($uploadDir)) {
                chmod($uploadDir, 0777);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['bankLogo']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
            }
            
            if ($_FILES['bankLogo']['size'] > 2 * 1024 * 1024) {
                throw new Exception('File size must be less than 2MB');
            }
            
            $uniqueFilename = uniqid('bank_logo_', true) . '.' . $fileExtension;
            $uploadPath = $uploadDir . $uniqueFilename;
            
            if (!move_uploaded_file($_FILES['bankLogo']['tmp_name'], $uploadPath)) {
                throw new Exception('move_uploaded_file failed');
            }
            
            $bankLogoPath = 'client/assets/uploads/bank_logo/' . $uniqueFilename;
        } catch (Exception $uploadError) {
            // Log error but don't fail the entire request
            error_log('Bank logo upload error: ' . $uploadError->getMessage());
            $bankLogoPath = null;
        }
    }
    
    $pdo->beginTransaction();
    
    // Insert into accounts table first
    $accountName = $input['bankName'] . ' - ' . $input['accountNumber'];
    $openingBalance = floatval($input['openingBalance'] ?? 0.00);
    
    $stmt = $pdo->prepare("
        INSERT INTO accounts (tenant_id, sub_account_id, name, debit, credit)
        VALUES (?, 75, ?, ?, ?)
    ");
    
    if ($input['balanceType'] === 'credit') {
        $stmt->execute([$tenant_id, $accountName, 0.00, $openingBalance]);
    } else {
        $stmt->execute([$tenant_id, $accountName, $openingBalance, 0.00]);
    }
    
    $accountId = $pdo->lastInsertId();
    
    // Insert into bank_accounts with account_id
    $stmt = $pdo->prepare("
        INSERT INTO bank_accounts (
            tenant_id, bank_name, is_mfb, mfb_name, account_number, account_type, 
            currency_id, branch_name, branch_code, branch_city, branch_state, 
            branch_address, account_title, iban, swift_code, contact_person, 
            contact_number, email, notes, balance_type, opening_balance, 
            as_of_date, is_active, account_id, bank_logo_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $tenant_id,
        $input['bankName'],
        $input['isMfb'] === 'true' ? 1 : 0,
        $input['mfbName'] ?? null,
        $input['accountNumber'],
        $input['accountType'],
        intval($input['currency']),
        $input['branchName'],
        $input['branchCode'] ?? null,
        $input['branchCity'],
        $input['branchState'],
        $input['branchAddress'] ?? null,
        $input['accountTitle'] ?? null,
        $input['iban'] ?? null,
        $input['swiftCode'] ?? null,
        $input['contactPerson'] ?? null,
        $input['contactNumber'] ?? null,
        $input['email'] ?? null,
        $input['notes'] ?? null,
        $input['balanceType'] ?? 'debit',
        $input['openingBalance'] ?? 0.00,
        $input['asOfDate'],
        $input['isActive'] === 'true' ? 1 : 0,
        $accountId,
        $bankLogoPath
    ]);
    
    $bankAccountId = $pdo->lastInsertId();
    // Insert into accounting_ledger table if opening balance exists
    if ($openingBalance > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
            VALUES (?, 'opening_balance', 'bank_accounts', ?, ?, ?, ?, ?, ?)
        ");
        
        // Bank account entry
        if ($input['balanceType'] === 'credit') {
            $stmt->execute([$tenant_id, $bankAccountId, $accountId, $input['asOfDate'], 'Opening Balance - ' . $accountName, 0.00, $openingBalance]);
        } else {
            $stmt->execute([$tenant_id, $bankAccountId, $accountId, $input['asOfDate'], 'Opening Balance - ' . $accountName, $openingBalance, 0.00]);
        }
        
        // Opening Balance Equity entry (opposite side)
        if ($input['balanceType'] === 'credit') {
            $stmt->execute([$tenant_id, $bankAccountId, 90, $input['asOfDate'], 'Opening Balance - ' . $accountName, $openingBalance, 0.00]);
        } else {
            $stmt->execute([$tenant_id, $bankAccountId, 90, $input['asOfDate'], 'Opening Balance - ' . $accountName, 0.00, $openingBalance]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Bank account created successfully', 'id' => $bankAccountId]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}