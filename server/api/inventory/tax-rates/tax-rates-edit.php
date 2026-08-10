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
    $tax_rate_id = $_POST['id'] ?? '';
    
    if (empty($tax_rate_id)) {
        echo json_encode(['success' => false, 'message' => 'Tax rate ID is required']);
        exit;
    }
    
    // Verify tax rate exists and belongs to tenant
    $stmt = $pdo->prepare("SELECT tenant_id FROM tax_rates WHERE id = ?");
    $stmt->execute([$tax_rate_id]);
    $taxRate = $stmt->fetch();
    
    if (!$taxRate) {
        echo json_encode(['success' => false, 'message' => 'Tax rate not found']);
        exit;
    }
    
    // Check if it's a system record
    if ($taxRate['tenant_id'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot edit system tax rates']);
        exit;
    }
    
    // Check if it belongs to current tenant
    if ($taxRate['tenant_id'] != $tenant_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    // Get POST data
    $tax_authority = $_POST['tax_authority'] ?? '';
    $tax_type = $_POST['tax_type'] ?? '';
    $transaction_type = $_POST['transaction_type'] ?? '';
    $legal_section = $_POST['legal_section'] ?? '';
    $finance_act_year = $_POST['finance_act_year'] ?? date('Y');
    $tax_name = $_POST['tax_name'] ?? '';
    $customer_type_id = $_POST['customer_type_id'] ?? null;
    if (!empty($customer_type_id)) {
        $customer_type_id = (int)$customer_type_id;
    } else {
        $customer_type_id = null;
    }
    $party_type = $_POST['party_type'] ?? '';
    $is_filer = isset($_POST['is_filer']) ? (int)$_POST['is_filer'] : 0;
    $rate_percentage = floatval($_POST['rate_percentage'] ?? 0);
    $threshold_min = !empty($_POST['threshold_min']) ? floatval($_POST['threshold_min']) : null;
    $threshold_max = !empty($_POST['threshold_max']) ? floatval($_POST['threshold_max']) : null;
    // Tax regime is now required
    $tax_regime_id = $_POST['tax_regime_id'] ?? '';
    if (empty($tax_regime_id)) {
        echo json_encode(['success' => false, 'message' => 'Tax regime is required']);
        exit;
    }
    $tax_regime_id = (int)$tax_regime_id;
    $is_adjustable = isset($_POST['is_adjustable']) ? (int)$_POST['is_adjustable'] : 1;
    $is_refundable = isset($_POST['is_refundable']) ? (int)$_POST['is_refundable'] : 1;
    $is_final_tax = isset($_POST['is_final_tax']) ? (int)$_POST['is_final_tax'] : 0;
    $deducted_by = $_POST['deducted_by'] ?? 'seller';
    $description = $_POST['description'] ?? '';
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    $effective_from = $_POST['effective_from'] ?? date('Y-m-d');
    $effective_to = !empty($_POST['effective_to']) ? $_POST['effective_to'] : null;
    $currency = $_POST['currency'] ?? 'PKR';
    
    // Validate required fields
    if (empty($tax_name) || empty($tax_type) || empty($transaction_type)) {
        echo json_encode(['success' => false, 'message' => 'Tax name, tax type, and transaction type are required']);
        exit;
    }
    
    // Update tax rate
    $stmt = $pdo->prepare("
        UPDATE tax_rates SET
            tax_authority = ?, tax_type = ?, transaction_type = ?, legal_section = ?, finance_act_year = ?,
            tax_name = ?, customer_type_id = ?, party_type = ?, is_filer = ?, rate_percentage = ?,
            threshold_min = ?, threshold_max = ?, tax_regime_id = ?, is_adjustable = ?,
            is_refundable = ?, is_final_tax = ?, deducted_by = ?, description = ?,
            is_active = ?, effective_from = ?, effective_to = ?, currency = ?, updated_by = ?, updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    
    $stmt->execute([
        $tax_authority, $tax_type, $transaction_type, $legal_section, $finance_act_year,
        $tax_name, $customer_type_id, $party_type, $is_filer, $rate_percentage,
        $threshold_min, $threshold_max, $tax_regime_id, $is_adjustable,
        $is_refundable, $is_final_tax, $deducted_by, $description,
        $is_active, $effective_from, $effective_to, $currency, $user_id, $tax_rate_id, $tenant_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tax rate updated successfully'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
