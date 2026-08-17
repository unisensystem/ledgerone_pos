<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost($input);
        break;
    case 'PUT':
        handlePut($input);
        break;
    case 'DELETE':
        handleDelete($input);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function handleGet() {
    global $pdo, $tenant_id;
    
    try {
        // Debug: Check tenant_id
        error_log("Tenant ID: " . $tenant_id);
        
        $account_heads = [];
        $sub_accounts = [];
        $accounts = [];
        
        // Get account heads
        $stmt = $pdo->prepare("SELECT id, name, tenant_id FROM accounts_head WHERE tenant_id = ? OR tenant_id = 0");
        $stmt->execute([$tenant_id]);
        $account_heads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get sub accounts
        $stmt = $pdo->prepare("SELECT id, account_head_id, name, parent_id, tenant_id FROM sub_accounts WHERE tenant_id = ? OR tenant_id = 0 AND id != 1");
        $stmt->execute([$tenant_id]);
        $sub_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get accounts
        $stmt = $pdo->prepare("SELECT id, sub_account_id, name, debit, credit, tenant_id FROM accounts WHERE tenant_id = ? OR tenant_id = 0 AND sub_account_id != 1");
        $stmt->execute([$tenant_id]);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'accountHeads' => $account_heads,
                'subAccounts' => $sub_accounts,
                'accounts' => $accounts
            ],
            'debug' => [
                'tenant_id' => $tenant_id
            ]
        ]);
    } catch (Exception $e) {
        error_log("Error in handleGet: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'debug' => ['tenant_id' => $tenant_id]
        ]);
    }
}

function handlePost($input) {
    global $pdo, $tenant_id;
    
    $type = $input['type'] ?? '';
    
    try {
        switch ($type) {
            case 'head':
                $name = $input['name'] ?? '';
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Name is required']);
                    return;
                }
                
                $stmt = $pdo->prepare("INSERT INTO accounts_head (tenant_id, name) VALUES (?, ?)");
                $stmt->execute([$tenant_id, $name]);
                
                echo json_encode(['success' => true, 'message' => 'Account head created successfully', 'id' => $pdo->lastInsertId()]);
                break;
                
            case 'sub':
                $account_head_id = $input['account_head_id'] ?? 0;
                $name = $input['name'] ?? '';
                $parent_id = $input['parent_id'] ?? null;
                
                if (empty($name) || !$account_head_id) {
                    echo json_encode(['success' => false, 'message' => 'Name and account head are required']);
                    return;
                }
                
                $stmt = $pdo->prepare("INSERT INTO sub_accounts (tenant_id, account_head_id, name, parent_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$tenant_id, $account_head_id, $name, $parent_id]);
                
                echo json_encode(['success' => true, 'message' => 'Sub-account created successfully', 'id' => $pdo->lastInsertId()]);
                break;
                
            case 'account':
                $sub_account_id = $input['sub_account_id'] ?? 0;
                $name = $input['name'] ?? '';
                $debit = $input['debit'] ?? 0;
                $credit = $input['credit'] ?? 0;
                
                if (empty($name) || !$sub_account_id) {
                    echo json_encode(['success' => false, 'message' => 'Name and sub-account are required']);
                    return;
                }
                
                $pdo->beginTransaction();
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO accounts (tenant_id, sub_account_id, name, debit, credit) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$tenant_id, $sub_account_id, $name, $debit, $credit]);
                    $account_id = $pdo->lastInsertId();
                    
                    // Create double-entry ledger entries if opening balance > 0
                    if ($debit > 0 || $credit > 0) {
                        $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'Opening Balance', 'accounts', ?, ?, CURDATE(), 'Opening Balance', ?, ?)");

                        // Primary entry for the new account
                        $stmt->execute([$tenant_id, $account_id, $account_id, $debit, $credit]);

                        // Contra entry against Opening Balance Equity (account_id = 90)
                        $stmt->execute([$tenant_id, $account_id, 90, $credit, $debit]);
                    }
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Account created successfully', 'id' => $account_id]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid type']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error creating record: ' . $e->getMessage()]);
    }
}

function handlePut($input) {
    global $pdo, $tenant_id;
    
    $type = $input['type'] ?? '';
    $id = $input['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    try {
        switch ($type) {
            case 'head':
                $name = $input['name'] ?? '';
                if (empty($name)) {
                    echo json_encode(['success' => false, 'message' => 'Name is required']);
                    return;
                }
                
                $stmt = $pdo->prepare("UPDATE accounts_head SET name = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$name, $id, $tenant_id]);
                
                echo json_encode(['success' => true, 'message' => 'Account head updated successfully']);
                break;
                
            case 'sub':
                $account_head_id = $input['account_head_id'] ?? 0;
                $name = $input['name'] ?? '';
                $parent_id = $input['parent_id'] ?? null;
                
                if (empty($name) || !$account_head_id) {
                    echo json_encode(['success' => false, 'message' => 'Name and account head are required']);
                    return;
                }
                
                $stmt = $pdo->prepare("UPDATE sub_accounts SET account_head_id = ?, name = ?, parent_id = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$account_head_id, $name, $parent_id, $id, $tenant_id]);
                
                echo json_encode(['success' => true, 'message' => 'Sub-account updated successfully']);
                break;
                
            case 'account':
                $sub_account_id = $input['sub_account_id'] ?? 0;
                $name = $input['name'] ?? '';
                $debit = $input['debit'] ?? 0;
                $credit = $input['credit'] ?? 0;
                
                if (empty($name) || !$sub_account_id) {
                    echo json_encode(['success' => false, 'message' => 'Name and sub-account are required']);
                    return;
                }
                
                $stmt = $pdo->prepare("UPDATE accounts SET sub_account_id = ?, name = ?, debit = ?, credit = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$sub_account_id, $name, $debit, $credit, $id, $tenant_id]);
                
                echo json_encode(['success' => true, 'message' => 'Account updated successfully']);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid type']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating record: ' . $e->getMessage()]);
    }
}

function handleDelete($input) {
    global $pdo, $tenant_id;
    
    $type = $input['type'] ?? '';
    $id = $input['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    try {
        switch ($type) {
            case 'head':
                $stmt = $pdo->prepare("DELETE FROM accounts_head WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                echo json_encode(['success' => true, 'message' => 'Account head deleted successfully']);
                break;
                
            case 'sub':
                $stmt = $pdo->prepare("DELETE FROM sub_accounts WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                echo json_encode(['success' => true, 'message' => 'Sub-account deleted successfully']);
                break;
                
            case 'account':
                $pdo->beginTransaction();
                
                try {
                    // Delete all ledger entries for this account (primary + contra) by reference
                    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'accounts' AND reference_id = ? AND tenant_id = ?");
                    $stmt->execute([$id, $tenant_id]);

                    // Delete account
                    $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$id, $tenant_id]);

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid type']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting record: ' . $e->getMessage()]);
    }
}
?>