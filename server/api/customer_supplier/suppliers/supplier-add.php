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
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['supplierName'])) {
        throw new Exception('Supplier name is required');
    }
    
    if (empty($input['companyId'])) {
        throw new Exception('Company is required');
    }
    
    $pdo->beginTransaction();

    // Generate supplier code per-company with lock to prevent duplicates
    $stmt = $pdo->prepare("SELECT supplier_code FROM suppliers WHERE tenant_id = ? AND company_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$tenant_id, (int)$input['companyId']]);
    $lastSupplier = $stmt->fetch();

    if ($lastSupplier) {
        $lastNum = (int)substr($lastSupplier['supplier_code'], 5);
        $nextNum = $lastNum + 1;
    } else {
        $nextNum = 1;
    }

    $supplier_code = 'SUPP-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    
    // Prepare data
    $data = [
        'tenant_id' => $tenant_id,
        'company_id' => (int)$input['companyId'],
        'salesman_id' => !empty($input['salesmanId']) ? trim($input['salesmanId']) : null,
        'project_id' => !empty($input['projectId']) ? (int)$input['projectId'] : null,
        'supplier_code' => $supplier_code,
        'supplier_name' => trim($input['supplierName']),
        'address' => !empty($input['address']) ? trim($input['address']) : null,
        'brand_name' => !empty($input['brandName']) ? trim($input['brandName']) : null,
        'primary_phone' => !empty($input['primaryPhone']) ? trim($input['primaryPhone']) : null,
        'secondary_phone' => !empty($input['secondaryPhone']) ? trim($input['secondaryPhone']) : null,
        'identity_card_no' => !empty($input['identityCard']) ? trim($input['identityCard']) : null,
        'email' => !empty($input['email']) ? trim($input['email']) : null,
        'opening_debit_amount' => floatval($input['openingDebit'] ?? 0),
        'opening_credit_amount' => floatval($input['openingCredit'] ?? 0),
        'ait_percent' => floatval($input['aitPercent'] ?? 0),
        'party_type' => !empty($input['partyType']) ? trim($input['partyType']) : null,
        'is_blacklisted' => isset($input['blacklist']) && $input['blacklist'] === true ? 1 : 0,
        'created_by' => $user_id,
        'updated_by' => $user_id
    ];
    
    // Generate ID - get max ID across all tenants to avoid conflicts
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 as new_id FROM suppliers FOR UPDATE");
    $stmt->execute();
    $new_id = $stmt->fetch()['new_id'];
    $data['id'] = $new_id;
    
    // Insert supplier
    $sql = "INSERT INTO suppliers (id, tenant_id, company_id, salesman_id, project_id, supplier_code, supplier_name, brand_name, address, primary_phone, secondary_phone, identity_card_no, email, opening_debit_amount, opening_credit_amount, ait_percent, party_type, is_blacklisted, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['id'],
        $data['tenant_id'],
        $data['company_id'],
        $data['salesman_id'],
        $data['project_id'],
        $data['supplier_code'],
        $data['supplier_name'],
        $data['brand_name'],
        $data['address'],
        $data['primary_phone'],
        $data['secondary_phone'],
        $data['identity_card_no'],
        $data['email'],
        $data['opening_debit_amount'],
        $data['opening_credit_amount'],
        $data['ait_percent'],
        $data['party_type'],
        $data['is_blacklisted'],
        $data['created_by'],
        $data['updated_by']
    ]);
    
    // Insert accounting ledger entries for opening balances
    if ($data['opening_debit_amount'] > 0) {
        // Trade Creditors (14) = Debit, Opening Balance Equity (90) = Credit
        $ledgerSql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
        
        $stmt = $pdo->prepare($ledgerSql);
        $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $new_id, 14, 'Supplier Opening Debit - ' . $data['supplier_name'], $data['opening_debit_amount'], 0]);
        
        $stmt = $pdo->prepare($ledgerSql);
        $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $new_id, 90, 'Supplier Opening Debit - ' . $data['supplier_name'], 0, $data['opening_debit_amount']]);
    }
    
    if ($data['opening_credit_amount'] > 0) {
        // Opening Balance Equity (90) = Debit, Trade Creditors (14) = Credit
        $ledgerSql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
        
        $stmt = $pdo->prepare($ledgerSql);
        $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $new_id, 90, 'Supplier Opening Credit - ' . $data['supplier_name'], $data['opening_credit_amount'], 0]);
        
        $stmt = $pdo->prepare($ledgerSql);
        $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $new_id, 14, 'Supplier Opening Credit - ' . $data['supplier_name'], 0, $data['opening_credit_amount']]);
    }
    
    // Insert sub accounts
    if (!empty($input['subAccounts']) && is_array($input['subAccounts'])) {
        $subAccountSql = "INSERT INTO supplier_sub_accounts (tenant_id, supplier_id, sub_account_name, debit, credit) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($subAccountSql);
        
        foreach ($input['subAccounts'] as $subAccount) {
            if (!empty(trim($subAccount['name']))) {
                $stmt->execute([
                    $tenant_id, 
                    $new_id, 
                    trim($subAccount['name']),
                    floatval($subAccount['debit'] ?? 0),
                    floatval($subAccount['credit'] ?? 0)
                ]);
            }
        }
    }
    
    // Insert associated companies
    if (!empty($input['associatedCompanyIds'])) {
        $assocIds = array_filter(array_map('intval', explode(',', $input['associatedCompanyIds'])));
        if (!empty($assocIds)) {
            $assocSql = "INSERT INTO supplier_associated_companies (tenant_id, supplier_id, company_id) VALUES (?, ?, ?)";
            $assocStmt = $pdo->prepare($assocSql);
            foreach ($assocIds as $cid) {
                $assocStmt->execute([$tenant_id, $new_id, $cid]);
            }
        }
    }
    
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Supplier created successfully',
        'supplier_code' => $supplier_code,
        'supplier_id' => $new_id
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>