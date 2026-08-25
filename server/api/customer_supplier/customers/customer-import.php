<?php
require_once '../../../../includes/connection.php';
require_once '../../../../vendor/autoload.php'; // composer require phpoffice/phpspreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

try {
    $company_id = isset($_POST['companyId']) ? (int)$_POST['companyId'] : 0;
    if (!$company_id) {
        throw new Exception('Company is required');
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $tmpPath = $_FILES['file']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        throw new Exception('Only .xlsx, .xls or .csv files are allowed');
    }

    // ---- Read the file ----
    $spreadsheet = IOFactory::load($tmpPath);
    $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

    // Expected header order (row 1):
    // Customer Name | Shopkeeper Name | Address | Primary Phone | Secondary Phone | Email | Opening Debit | Opening Credit
    array_shift($rows); // drop header row

    if (empty($rows)) {
        throw new Exception('File has no data rows');
    }

    // ---- Preload existing customers for this tenant (for duplicate check) ----
    $stmt = $pdo->prepare("SELECT LOWER(customer_name) AS cn, primary_phone FROM customers WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $existing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['cn'] . '|' . ($row['primary_phone'] ?? '')] = true;
    }

    $pdo->beginTransaction();

    // ---- Get starting customer_code (locks last row so concurrent imports/adds don't collide) ----
    $stmt = $pdo->prepare("SELECT customer_code FROM customers WHERE tenant_id = ? AND company_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
$stmt->execute([$tenant_id, $company_id]);
    $lastCustomer = $stmt->fetch();
    $nextCodeNum = $lastCustomer ? ((int)substr($lastCustomer['customer_code'], 5)) + 1 : 1;

    // ---- Get starting id (global across tenants, matches existing add-customer logic) ----
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 as new_id FROM customers FOR UPDATE");
    $stmt->execute();
    $nextId = (int)$stmt->fetch()['new_id'];

    $insertSql = "INSERT INTO customers
        (id, tenant_id, company_id, customer_code, customer_name, shop_name, address,
         primary_phone, secondary_phone, email, opening_debit_amount, opening_credit_amount,
         current_balance, is_blacklisted, is_out_station, status, created_by, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 'ACTIVE', ?, ?)";
    $insertStmt = $pdo->prepare($insertSql);

    $ledgerSql = "INSERT INTO accounting_ledger
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
        VALUES (?, 'customer_opening', 'customers', ?, ?, CURDATE(), ?, ?, ?)";
    $ledgerStmt = $pdo->prepare($ledgerSql);

    $inserted = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $i => $r) {
        $rowNum = $i + 2; // +2 because header row was row 1 and array is 0-indexed

        $customerName = trim((string)($r[0] ?? ''));
        if ($customerName === '') {
            continue; // silently skip fully blank rows
        }

        $shopName        = trim((string)($r[1] ?? '')) ?: null;
        $address         = trim((string)($r[2] ?? '')) ?: null;
        $primaryPhone    = trim((string)($r[3] ?? '')) ?: null;
        $secondaryPhone  = trim((string)($r[4] ?? '')) ?: null;
        $email           = trim((string)($r[5] ?? '')) ?: null;
        $openingDebit    = isset($r[6]) && $r[6] !== '' ? (float)$r[6] : 0.0;
        $openingCredit   = isset($r[7]) && $r[7] !== '' ? (float)$r[7] : 0.0;

        // Basic validation
        if (strlen($customerName) > 255) {
            $errors[] = ['row' => $rowNum, 'message' => 'Customer name too long'];
            continue;
        }
        if ($primaryPhone && !preg_match('/^[0-9]{1,15}$/', $primaryPhone)) {
            $errors[] = ['row' => $rowNum, 'message' => 'Invalid primary phone (numbers only, max 15 digits)'];
            continue;
        }
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['row' => $rowNum, 'message' => 'Invalid email address'];
            continue;
        }

        // Duplicate check: same name + same phone already exists for this tenant
        $dupKey = strtolower($customerName) . '|' . ($primaryPhone ?? '');
        if (isset($existing[$dupKey])) {
            $skipped++;
            continue;
        }

        $customerCode = 'CUST-' . str_pad($nextCodeNum, 5, '0', STR_PAD_LEFT);
        $currentBalance = $openingDebit - $openingCredit;

        try {
            $insertStmt->execute([
                $nextId,
                $tenant_id,
                $company_id,
                $customerCode,
                $customerName,
                $shopName,
                $address,
                $primaryPhone,
                $secondaryPhone,
                $email,
                $openingDebit,
                $openingCredit,
                $currentBalance,
                $user_id,
                $user_id
            ]);

            // Opening balance ledger entries (Trade Debtors = account 2, Opening Balance Equity = account 90)
            if ($openingDebit > 0) {
                $ledgerStmt->execute([$tenant_id, $nextId, 2, 'Opening balance (import)', $openingDebit, 0]);
                $ledgerStmt->execute([$tenant_id, $nextId, 90, 'Opening balance (import)', 0, $openingDebit]);
            }
            if ($openingCredit > 0) {
                $ledgerStmt->execute([$tenant_id, $nextId, 90, 'Opening balance (import)', $openingCredit, 0]);
                $ledgerStmt->execute([$tenant_id, $nextId, 2, 'Opening balance (import)', 0, $openingCredit]);
            }

            $existing[$dupKey] = true; // prevent duplicates within the same file too
            $inserted++;
            $nextId++;
            $nextCodeNum++;
        } catch (Exception $rowEx) {
            $errors[] = ['row' => $rowNum, 'message' => $rowEx->getMessage()];
        }
    }

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'errors'   => $errors
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}