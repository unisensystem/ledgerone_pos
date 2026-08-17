<?php
require_once '../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'next_code') {
        $stmt = $pdo->prepare("SELECT rent_no FROM rent_management WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$tenant_id]);
        $lastCode = $stmt->fetchColumn();
        
        $nextNum = $lastCode && preg_match('/RENT-(\d+)/', $lastCode, $m) ? intval($m[1]) + 1 : 1;
        echo json_encode(['success' => true, 'code' => 'RENT-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT)]);
        exit;
    }
    
    if ($action === 'customers') {
        $stmt = $pdo->prepare("SELECT id, customer_code, customer_name, primary_phone, address FROM customers WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'products') {
        $stmt = $pdo->prepare("SELECT p.id, p.code, p.name, p.default_unit_id, u.uom_name as unit_name FROM products p LEFT JOIN uom u ON p.default_unit_id = u.id WHERE p.tenant_id = ? AND p.is_active = 1");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'accounts') {
        $stmt = $pdo->prepare("SELECT id, name FROM accounts WHERE tenant_id = ? OR tenant_id = 0");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'bank_accounts') {
        $stmt = $pdo->prepare("SELECT id, bank_name, account_number, account_title FROM bank_accounts WHERE tenant_id = ? AND is_active = 1");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'branches') {
        $stmt = $pdo->prepare("SELECT id, branch_code, branch_name FROM branches WHERE tenant_id = ? AND is_active = 1");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT * FROM rent_management WHERE tenant_id = ? ORDER BY created_at DESC");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'view' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM rent_management WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$_GET['id'], $tenant_id]);
        $rent = $stmt->fetch();
        
        if ($rent) {
            $stmt = $pdo->prepare("SELECT * FROM rent_items WHERE rent_id = ?");
            $stmt->execute([$_GET['id']]);
            $rent['items'] = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $rent]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Rent not found']);
        }
        exit;
    }
}

// POST - Issue Rent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = json_decode($_POST['items'], true);
    $rent_id = $_POST['rent_id'] ?? null;
    
    $nic_picture = null;
    $reference_nic_picture = null;
    
    if (isset($_FILES['nic_picture']) && $_FILES['nic_picture']['error'] === 0) {
        $upload_dir = '../../../uploads/rent/nic/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['nic_picture']['name'], PATHINFO_EXTENSION);
        $nic_picture = 'NIC_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        move_uploaded_file($_FILES['nic_picture']['tmp_name'], $upload_dir . $nic_picture);
    }
    
    if (isset($_FILES['reference_nic_picture']) && $_FILES['reference_nic_picture']['error'] === 0) {
        $upload_dir = '../../../uploads/rent/nic/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['reference_nic_picture']['name'], PATHINFO_EXTENSION);
        $reference_nic_picture = 'REF_NIC_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        move_uploaded_file($_FILES['reference_nic_picture']['tmp_name'], $upload_dir . $reference_nic_picture);
    }
    
    try {
        $pdo->beginTransaction();
        
        if ($rent_id) {
            $update_fields = [];
            $update_values = [];
            
            $update_fields[] = "branch_id = ?";
            $update_values[] = $_POST['branch_id'];
            $update_fields[] = "date = ?";
            $update_values[] = $_POST['date'];
            $update_fields[] = "customer_id = ?";
            $update_values[] = $_POST['customer_id'];
            $update_fields[] = "customer_name = ?";
            $update_values[] = $_POST['customer_name'];
            $update_fields[] = "customer_phone = ?";
            $update_values[] = $_POST['customer_phone'];
            $update_fields[] = "customer_address = ?";
            $update_values[] = $_POST['customer_address'];
            $update_fields[] = "nic_no = ?";
            $update_values[] = $_POST['nic_no'];
            
            if ($nic_picture) {
                $update_fields[] = "nic_picture = ?";
                $update_values[] = $nic_picture;
            }
            
            $update_fields[] = "reference_name = ?";
            $update_values[] = $_POST['reference_name'];
            $update_fields[] = "reference_phone = ?";
            $update_values[] = $_POST['reference_phone'];
            $update_fields[] = "reference_nic = ?";
            $update_values[] = $_POST['reference_nic'];
            
            if ($reference_nic_picture) {
                $update_fields[] = "reference_nic_picture = ?";
                $update_values[] = $reference_nic_picture;
            }
            
            $update_fields[] = "rent_type = ?";
            $update_values[] = $_POST['rent_type'];
            $update_fields[] = "rent_rate = ?";
            $update_values[] = $_POST['rent_rate'];
            $update_fields[] = "total_days = ?";
            $update_values[] = $_POST['total_days'];
            $update_fields[] = "total_rent_amount = ?";
            $update_values[] = $_POST['total_rent_amount'];
            $update_fields[] = "issue_date = ?";
            $update_values[] = $_POST['issue_date'];
            $update_fields[] = "expected_return_date = ?";
            $update_values[] = $_POST['expected_return_date'];
            $update_fields[] = "remarks = ?";
            $update_values[] = $_POST['remarks'];
            
            $update_values[] = $rent_id;
            $update_values[] = $tenant_id;
            
            $stmt = $pdo->prepare("UPDATE rent_management SET " . implode(", ", $update_fields) . " WHERE id = ? AND tenant_id = ?");
            $stmt->execute($update_values);
            
            $stmt = $pdo->prepare("DELETE FROM rent_items WHERE rent_id = ?");
            $stmt->execute([$rent_id]);
            
            $stmt = $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'rent_management' AND reference_id = ?");
            $stmt->execute([$rent_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO rent_management (tenant_id, branch_id, rent_no, date, customer_id, customer_name, customer_phone, customer_address, nic_no, nic_picture, reference_name, reference_phone, reference_nic, reference_nic_picture, rent_type, rent_rate, total_days, total_rent_amount, issue_date, expected_return_date, remarks, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Issued', ?)");
            
            $stmt->execute([
                $tenant_id, $_POST['branch_id'], $_POST['rent_no'], $_POST['date'], $_POST['customer_id'], $_POST['customer_name'],
                $_POST['customer_phone'], $_POST['customer_address'], $_POST['nic_no'], $nic_picture,
                $_POST['reference_name'], $_POST['reference_phone'], $_POST['reference_nic'], $reference_nic_picture,
                $_POST['rent_type'], $_POST['rent_rate'], $_POST['total_days'], $_POST['total_rent_amount'],
                $_POST['issue_date'], $_POST['expected_return_date'], $_POST['remarks'], $user_id
            ]);
            
            $rent_id = $pdo->lastInsertId();
        }
        
        $stmt_item = $pdo->prepare("INSERT INTO rent_items (rent_id, product_id, item_code, item_name, description, unit_id, quantity, rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_stock = $pdo->prepare("INSERT INTO stock_ledger (tenant_id, branch_id, product_id, reference_table, reference_id, qty_out, unit_id, unit_cost, transaction_type, transaction_date, created_at) VALUES (?, ?, ?, 'rent_management', ?, ?, ?, ?, 'Rent Out', ?, NOW())");
        
        foreach ($items as $item) {
            $stmt_item->execute([$rent_id, $item['product_id'], $item['item_code'], $item['item_name'], $item['description'], $item['unit_id'], $item['quantity'], $item['rate']]);
            $stmt_stock->execute([$tenant_id, $_POST['branch_id'], $item['product_id'], $rent_id, $item['quantity'], $item['unit_id'], $item['rate'], $_POST['issue_date']]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Rent saved successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// PUT - Return Rent
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM rent_management WHERE id = ?");
        $stmt->execute([$data['rent_id']]);
        $rent = $stmt->fetch();
        
        $total = $rent['total_rent_amount'] + $data['late_charges'] + $data['damage_charges'];
        
        $stmt = $pdo->prepare("UPDATE rent_management SET actual_return_date = ?, late_charges = ?, damage_charges = ?, net_amount = ?, payment_method = ?, bank_account_id = ?, status = 'Returned' WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$data['actual_return_date'], $data['late_charges'], $data['damage_charges'], $total, $data['payment_method'], $data['bank_account_id'], $data['rent_id'], $tenant_id]);
        
        $stmt_stock = $pdo->prepare("INSERT INTO stock_ledger (tenant_id, branch_id, product_id, reference_table, reference_id, qty_in, unit_id, unit_cost, transaction_type, transaction_date, created_at) VALUES (?, ?, ?, 'rent_management', ?, ?, ?, ?, 'Rent Return', ?, NOW())");
        
        foreach ($data['items'] as $item) {
            $stmt = $pdo->prepare("UPDATE rent_items SET condition_after = ? WHERE id = ?");
            $stmt->execute([$item['condition_after'], $item['id']]);
            $stmt_stock->execute([$tenant_id, $rent['branch_id'], $item['product_id'], $data['rent_id'], $item['quantity'], $item['unit_id'], $item['rate'], $data['actual_return_date']]);
        }
        
        $payment_account = 7;
        if ($data['payment_method'] === 'Bank') $payment_account = 5;
        elseif ($data['payment_method'] === 'Online') $payment_account = 148;
        
        $stmt = $pdo->prepare("INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit, created_at) VALUES (?, ?, 'rent_management', ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$tenant_id, 'Rent Return', $data['rent_id'], $payment_account, $data['actual_return_date'], "Rent payment received - {$rent['rent_no']} - {$data['payment_method']}", $total, 0]);
        $stmt->execute([$tenant_id, 'Rent Return', $data['rent_id'], 2, $data['actual_return_date'], "Rent receivable cleared - {$rent['rent_no']}", 0, $total]);
        $stmt->execute([$tenant_id, 'Rent Return', $data['rent_id'], 2, $data['actual_return_date'], "Rent receivable - {$rent['rent_no']}", $total, 0]);
        $stmt->execute([$tenant_id, 'Rent Return', $data['rent_id'], 147, $data['actual_return_date'], "Rent income - {$rent['rent_no']}", 0, $total]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Rent returned successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// DELETE - Delete Rent
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM rent_items WHERE rent_id = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'rent_management' AND reference_id = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'rent_management' AND reference_id = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("DELETE FROM rent_management WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Rent deleted successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
