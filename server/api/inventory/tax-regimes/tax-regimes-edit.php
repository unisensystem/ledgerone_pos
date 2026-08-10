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
    $regime_id = $_POST['id'] ?? '';
    
    if (empty($regime_id)) {
        echo json_encode(['success' => false, 'message' => 'Tax regime ID is required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT tenant_id FROM tax_regimes WHERE id = ?");
    $stmt->execute([$regime_id]);
    $taxRegime = $stmt->fetch();
    
    if (!$taxRegime) {
        echo json_encode(['success' => false, 'message' => 'Tax regime not found']);
        exit;
    }
    
    if ($taxRegime['tenant_id'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot edit system tax regimes']);
        exit;
    }
    
    if ($taxRegime['tenant_id'] != $tenant_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $regime_name = $_POST['regime_name'] ?? '';
    $regime_code = $_POST['regime_code'] ?? '';
    $country_id = !empty($_POST['country_id']) ? (int)$_POST['country_id'] : null;
    $tax_authority = $_POST['tax_authority'] ?? '';
    $tax_base = $_POST['tax_base'] ?? '';
    $legal_reference = $_POST['legal_reference'] ?? '';
    $finance_act_year = $_POST['finance_act_year'] ?? date('Y');
    $formula_template = $_POST['formula_template'] ?? '';
    $applies_at_stage = $_POST['applies_at_stage'] ?? 'all';
    $application_level = $_POST['application_level'] ?? 'item';
    $formula_steps = '[]';
    $formula_variables = '[]';
    $is_tax_inclusive = isset($_POST['is_tax_inclusive']) ? (int)$_POST['is_tax_inclusive'] : 0;
    $is_single_stage = isset($_POST['is_single_stage']) ? (int)$_POST['is_single_stage'] : 0;
    $downstream_exempt = isset($_POST['downstream_exempt']) ? (int)$_POST['downstream_exempt'] : 0;
    $is_adjustable = isset($_POST['is_adjustable']) ? (int)$_POST['is_adjustable'] : 1;
    $is_refundable = isset($_POST['is_refundable']) ? (int)$_POST['is_refundable'] : 0;
    $is_final_tax = isset($_POST['is_final_tax']) ? (int)$_POST['is_final_tax'] : 0;
    $description = $_POST['description'] ?? '';
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    $effective_from = $_POST['effective_from'] ?? date('Y-m-d');
    $effective_to = !empty($_POST['effective_to']) ? $_POST['effective_to'] : null;
    
    if (empty($regime_name) || empty($regime_code) || empty($tax_authority) || empty($tax_base) || empty($formula_template)) {
        echo json_encode(['success' => false, 'message' => 'Regime name, code, authority, tax base, and formula template are required']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        UPDATE tax_regimes SET
            regime_name = ?, regime_code = ?, country_id = ?, tax_authority = ?, tax_base = ?, legal_reference = ?,
            finance_act_year = ?, formula_template = ?, formula_steps = ?, formula_variables = ?, applies_at_stage = ?, application_level = ?,
            is_tax_inclusive = ?, is_single_stage = ?, downstream_exempt = ?, is_adjustable = ?,
            is_refundable = ?, is_final_tax = ?, description = ?, is_active = ?,
            effective_from = ?, effective_to = ?, updated_by = ?, updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    
    $stmt->execute([
        $regime_name, $regime_code, $country_id, $tax_authority, $tax_base, $legal_reference,
        $finance_act_year, $formula_template, $formula_steps, $formula_variables, $applies_at_stage, $application_level,
        $is_tax_inclusive, $is_single_stage, $downstream_exempt, $is_adjustable,
        $is_refundable, $is_final_tax, $description, $is_active,
        $effective_from, $effective_to, $user_id, $regime_id, $tenant_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tax regime updated successfully'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
