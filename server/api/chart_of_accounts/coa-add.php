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
        $account_partners = [];
        
        // Get account heads
        $stmt = $pdo->prepare("SELECT id, name, tenant_id FROM accounts_head WHERE tenant_id = ? OR tenant_id = 0");
        $stmt->execute([$tenant_id]);
        $account_heads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get sub accounts
        $stmt = $pdo->prepare("SELECT id, account_head_id, name, parent_id, tenant_id FROM sub_accounts WHERE tenant_id = ? OR tenant_id = 0 AND id != 1");
        $stmt->execute([$tenant_id]);
        $sub_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get accounts (posting accounts + partner accounts)
        $stmt = $pdo->prepare("SELECT id, sub_account_id, name, debit, credit, tenant_id, is_partner_account FROM accounts WHERE tenant_id = ? OR tenant_id = 0 AND sub_account_id != 1");
        $stmt->execute([$tenant_id]);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get partner splits for Partner Accounts
        $stmt = $pdo->prepare("SELECT ap.id, ap.account_id, ap.partner_name, ap.percentage
                                FROM account_partners ap
                                INNER JOIN accounts a ON ap.account_id = a.id
                                WHERE a.tenant_id = ? OR a.tenant_id = 0");
        $stmt->execute([$tenant_id]);
        $account_partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'accountHeads' => $account_heads,
                'subAccounts' => $sub_accounts,
                'accounts' => $accounts,
                'accountPartners' => $account_partners
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

            case 'partner':
                $sub_account_id = $input['sub_account_id'] ?? 0;
                $name = $input['name'] ?? '';
                $debit = $input['debit'] ?? 0;
                $credit = $input['credit'] ?? 0;
                $partners = $input['partners'] ?? [];

                if (empty($name) || !$sub_account_id) {
                    echo json_encode(['success' => false, 'message' => 'Name and sub-account are required']);
                    return;
                }

                $totalPercentage = 0;
                foreach ($partners as $partner) {
                    $totalPercentage += (float)($partner['percentage'] ?? 0);
                }

                if ($totalPercentage > 100) {
                    echo json_encode(['success' => false, 'message' => 'Total partner percentage cannot exceed 100%']);
                    return;
                }

                $pdo->beginTransaction();

                try {
                    $stmt = $pdo->prepare("INSERT INTO accounts (tenant_id, sub_account_id, name, debit, credit, is_partner_account) VALUES (?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$tenant_id, $sub_account_id, $name, $debit, $credit]);
                    $account_id = $pdo->lastInsertId();

                    // Save partner splits (remaining % is always calculated as Self/Owner, never stored)
                    foreach ($partners as $partner) {
                        $partner_name = trim($partner['name'] ?? '');
                        $percentage = (float)($partner['percentage'] ?? 0);
                        if ($partner_name === '' || $percentage <= 0) continue;

                        $stmt = $pdo->prepare("INSERT INTO account_partners (tenant_id, account_id, partner_name, percentage) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$tenant_id, $account_id, $partner_name, $percentage]);
                    }

                    // Create double-entry ledger entries if opening balance > 0 (same as posting account)
                    if ($debit > 0 || $credit > 0) {
                        $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, 'Opening Balance', 'accounts', ?, ?, CURDATE(), 'Opening Balance', ?, ?)");

                        // Primary entry for the new account
                        $stmt->execute([$tenant_id, $account_id, $account_id, $debit, $credit]);

                        // Contra entry against Opening Balance Equity (account_id = 90)
                        $stmt->execute([$tenant_id, $account_id, 90, $credit, $debit]);
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Partner account created successfully', 'id' => $account_id]);
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

            case 'partner':
                $sub_account_id = $input['sub_account_id'] ?? 0;
                $name = $input['name'] ?? '';
                $debit = $input['debit'] ?? 0;
                $credit = $input['credit'] ?? 0;
                $partners = $input['partners'] ?? [];

                if (empty($name) || !$sub_account_id) {
                    echo json_encode(['success' => false, 'message' => 'Name and sub-account are required']);
                    return;
                }

                $totalPercentage = 0;
                foreach ($partners as $partner) {
                    $totalPercentage += (float)($partner['percentage'] ?? 0);
                }

                if ($totalPercentage > 100) {
                    echo json_encode(['success' => false, 'message' => 'Total partner percentage cannot exceed 100%']);
                    return;
                }

                $pdo->beginTransaction();

                try {
                    $stmt = $pdo->prepare("UPDATE accounts SET sub_account_id = ?, name = ?, debit = ?, credit = ? WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$sub_account_id, $name, $debit, $credit, $id, $tenant_id]);

                    // Replace partner splits with the latest list from the form
                    $stmt = $pdo->prepare("DELETE FROM account_partners WHERE account_id = ? AND tenant_id = ?");
                    $stmt->execute([$id, $tenant_id]);

                    foreach ($partners as $partner) {
                        $partner_name = trim($partner['name'] ?? '');
                        $percentage = (float)($partner['percentage'] ?? 0);
                        if ($partner_name === '' || $percentage <= 0) continue;

                        $stmt = $pdo->prepare("INSERT INTO account_partners (tenant_id, account_id, partner_name, percentage) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$tenant_id, $id, $partner_name, $percentage]);
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Partner account updated successfully']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;

            case 'convert_to_partner':
                // Turns an existing Posting Account into a Partner Account WITHOUT
                // changing its id, name, or balance - so existing ledger entries /
                // references to this account_id stay intact.
                $partners = $input['partners'] ?? [];

                $totalPercentage = 0;
                foreach ($partners as $partner) {
                    $totalPercentage += (float)($partner['percentage'] ?? 0);
                }

                if ($totalPercentage > 100) {
                    echo json_encode(['success' => false, 'message' => 'Total partner percentage cannot exceed 100%']);
                    return;
                }

                $pdo->beginTransaction();

                try {
                    $stmt = $pdo->prepare("UPDATE accounts SET is_partner_account = 1 WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$id, $tenant_id]);

                    // Remove any old splits (in case it's being re-converted) and save the new ones
                    $stmt = $pdo->prepare("DELETE FROM account_partners WHERE account_id = ? AND tenant_id = ?");
                    $stmt->execute([$id, $tenant_id]);

                    foreach ($partners as $partner) {
                        $partner_name = trim($partner['name'] ?? '');
                        $percentage = (float)($partner['percentage'] ?? 0);
                        if ($partner_name === '' || $percentage <= 0) continue;

                        $stmt = $pdo->prepare("INSERT INTO account_partners (tenant_id, account_id, partner_name, percentage) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$tenant_id, $id, $partner_name, $percentage]);
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Account converted to Partner Account successfully']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
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
                    // Delete partner splits if this was a Partner Account (no-op otherwise)
                    $stmt = $pdo->prepare("DELETE FROM account_partners WHERE account_id = ? AND tenant_id = ?");
                    $stmt->execute([$id, $tenant_id]);

                    // Delete all ledger entries that reference this account as account_id (all transaction types)
                    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE account_id = ? AND tenant_id = ?");
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