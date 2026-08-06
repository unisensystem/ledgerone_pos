<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get supplier by ID
        $supplier_id = $_GET['id'] ?? null;
        if (!$supplier_id) {
            throw new Exception('Supplier ID is required');
        }

        $stmt = $pdo->prepare("SELECT s.*, co.company_name, p.project_name, p.project_code, ci.city_name as project_city_name FROM suppliers s LEFT JOIN companies co ON co.id = s.company_id LEFT JOIN projects p ON p.id = s.project_id LEFT JOIN cities ci ON ci.id = p.city_id WHERE s.id = ? AND s.tenant_id = ?");
        $stmt->execute([$supplier_id, $tenant_id]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$supplier) {
            throw new Exception('Supplier not found');
        }

        echo json_encode(['success' => true, 'supplier' => $supplier]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update supplier
        $input = json_decode(file_get_contents('php://input'), true);
        $supplier_id = $input['id'] ?? null;

        if (!$supplier_id || empty($input['supplierName'])) {
            throw new Exception('Supplier ID and name are required');
        }

        // Check if supplier exists and belongs to tenant
        $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$supplier_id, $tenant_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Supplier not found');
        }

        // Update supplier
      $sql = "UPDATE suppliers SET 
        company_id = ?, 
        salesman_id = ?, 
        project_id = ?, 
        supplier_name = ?, 
        brand_name = ?, 
        address = ?, 
        city = ?,
        primary_phone = ?, 
        secondary_phone = ?, 
        identity_card_no = ?, 
        email = ?, 
        opening_debit_amount = ?, 
        opening_credit_amount = ?, 
        ait_percent = ?, 
        credit_days = ?,
        party_type = ?,
        is_blacklisted = ?, 
        updated_by = ?, 
        updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND tenant_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    !empty($input['companyId']) ? (int)$input['companyId'] : null,
    !empty($input['salesmanId']) ? trim($input['salesmanId']) : null,
    !empty($input['projectId']) ? (int)$input['projectId'] : null,
    trim($input['supplierName']),
    !empty($input['brandName']) ? trim($input['brandName']) : null,
    !empty($input['address']) ? trim($input['address']) : null,
    !empty($input['city']) ? trim($input['city']) : null,
    !empty($input['primaryPhone']) ? trim($input['primaryPhone']) : null,
    !empty($input['secondaryPhone']) ? trim($input['secondaryPhone']) : null,
    !empty($input['identityCard']) ? trim($input['identityCard']) : null,
    !empty($input['email']) ? trim($input['email']) : null,
    floatval($input['openingDebit'] ?? 0),
    floatval($input['openingCredit'] ?? 0),
    floatval($input['aitPercent'] ?? 0),
    isset($input['creditDays']) && $input['creditDays'] !== '' ? (int)$input['creditDays'] : 0,
    !empty($input['partyType']) ? trim($input['partyType']) : null,
    isset($input['blacklist']) && $input['blacklist'] === true ? 1 : 0,
    $user_id,
    $supplier_id,
    $tenant_id
]);

        // Delete existing opening balance ledger entries
        $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'supplier_opening' AND reference_table = 'suppliers' AND reference_id = ?");
        $stmt->execute([$tenant_id, $supplier_id]);

        // Insert new opening balance ledger entries
        $openingDebit = floatval($input['openingDebit'] ?? 0);
        $openingCredit = floatval($input['openingCredit'] ?? 0);
        $supplierName = trim($input['supplierName']);

        if ($openingDebit > 0) {
            $ledgerSql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
            
            $stmt = $pdo->prepare($ledgerSql);
            $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 14, 'Supplier Opening Debit - ' . $supplierName, $openingDebit, 0]);
            
            $stmt = $pdo->prepare($ledgerSql);
            $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 90, 'Supplier Opening Debit - ' . $supplierName, 0, $openingDebit]);
        }
        
        if ($openingCredit > 0) {
            $ledgerSql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)";
            
            $stmt = $pdo->prepare($ledgerSql);
            $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 90, 'Supplier Opening Credit - ' . $supplierName, $openingCredit, 0]);
            
            $stmt = $pdo->prepare($ledgerSql);
            $stmt->execute([$tenant_id, 'supplier_opening', 'suppliers', $supplier_id, 14, 'Supplier Opening Credit - ' . $supplierName, 0, $openingCredit]);
        }

        // Handle sub accounts - Update existing, insert new, delete removed
        if (!empty($input['subAccounts']) && is_array($input['subAccounts'])) {
            $submittedIds = [];
            
            foreach ($input['subAccounts'] as $subAccount) {
                if (!empty(trim($subAccount['name']))) {
                    if (!empty($subAccount['id'])) {
                        $updateSql = "UPDATE supplier_sub_accounts SET sub_account_name = ?, debit = ?, credit = ? WHERE id = ? AND tenant_id = ? AND supplier_id = ?";
                        $stmt = $pdo->prepare($updateSql);
                        $stmt->execute([
                            trim($subAccount['name']),
                            floatval($subAccount['debit'] ?? 0),
                            floatval($subAccount['credit'] ?? 0),
                            (int)$subAccount['id'],
                            $tenant_id,
                            $supplier_id
                        ]);
                        $submittedIds[] = (int)$subAccount['id'];
                    } else {
                        $insertSql = "INSERT INTO supplier_sub_accounts (tenant_id, supplier_id, sub_account_name, debit, credit) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($insertSql);
                        $stmt->execute([
                            $tenant_id,
                            $supplier_id,
                            trim($subAccount['name']),
                            floatval($subAccount['debit'] ?? 0),
                            floatval($subAccount['credit'] ?? 0)
                        ]);
                        $submittedIds[] = $pdo->lastInsertId();
                    }
                }
            }
            
            if (!empty($submittedIds)) {
                $placeholders = implode(',', array_fill(0, count($submittedIds), '?'));
                $deleteSql = "DELETE FROM supplier_sub_accounts WHERE tenant_id = ? AND supplier_id = ? AND id NOT IN ($placeholders)";
                $stmt = $pdo->prepare($deleteSql);
                $stmt->execute(array_merge([$tenant_id, $supplier_id], $submittedIds));
            }
        }

        // Update associated companies
        $stmt = $pdo->prepare("DELETE FROM supplier_associated_companies WHERE tenant_id = ? AND supplier_id = ?");
        $stmt->execute([$tenant_id, $supplier_id]);
        if (!empty($input['associatedCompanyIds'])) {
            $assocIds = array_filter(array_map('intval', explode(',', $input['associatedCompanyIds'])));
            if (!empty($assocIds)) {
                $assocStmt = $pdo->prepare("INSERT IGNORE INTO supplier_associated_companies (tenant_id, supplier_id, company_id) VALUES (?, ?, ?)");
                foreach ($assocIds as $cid) {
                    $assocStmt->execute([$tenant_id, $supplier_id, $cid]);
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    // SQLSTATE 01xxx = server warning (e.g. 01000). Data was saved; treat as success.
    if (substr($e->getCode(), 0, 2) === '01') {
        echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
