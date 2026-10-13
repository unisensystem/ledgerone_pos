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
    $product_ids = $_POST['productIds'] ?? [];
    $apply_to_all = isset($_POST['applyToAll']) && $_POST['applyToAll'] === '1';
    $uom_type = $_POST['uomType'] ?? 'unit';
    $default_unit_id = !empty($_POST['defaultUnit']) ? $_POST['defaultUnit'] : null;
    $uom_group_id = !empty($_POST['uomGroup']) ? $_POST['uomGroup'] : null;
    
    // Validate input
    if (!$apply_to_all && (empty($product_ids) || !is_array($product_ids))) {
        echo json_encode(['success' => false, 'message' => 'No products selected']);
        exit;
    }
    
    if ($uom_type === 'unit' && empty($default_unit_id)) {
        echo json_encode(['success' => false, 'message' => 'Default unit is required']);
        exit;
    }
    
    if ($uom_type === 'group' && empty($uom_group_id)) {
        echo json_encode(['success' => false, 'message' => 'UOM group is required']);
        exit;
    }
    
    // Prepare update query based on UOM type
    if ($uom_type === 'unit') {
        if ($apply_to_all) {
            // Apply to all products of this tenant
            $stmt = $pdo->prepare("
                UPDATE products 
                SET uom_type = 'unit', default_unit_id = ?, uom_group_id = NULL, updated_at = CURRENT_TIMESTAMP
                WHERE tenant_id = ?
            ");
            $stmt->execute([$default_unit_id, $tenant_id]);
            $updated_count = $stmt->rowCount();
        } else {
            // Apply to selected products only
            $stmt = $pdo->prepare("
                UPDATE products 
                SET uom_type = 'unit', default_unit_id = ?, uom_group_id = NULL, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND tenant_id = ?
            ");
            
            $updated_count = 0;
            foreach ($product_ids as $product_id) {
                $stmt->execute([$default_unit_id, $product_id, $tenant_id]);
                $updated_count += $stmt->rowCount();
            }
        }
    } else {
        if ($apply_to_all) {
            // Apply to all products of this tenant
            $stmt = $pdo->prepare("
                UPDATE products 
                SET uom_type = 'group', uom_group_id = ?, default_unit_id = NULL, updated_at = CURRENT_TIMESTAMP
                WHERE tenant_id = ?
            ");
            $stmt->execute([$uom_group_id, $tenant_id]);
            $updated_count = $stmt->rowCount();
        } else {
            // Apply to selected products only
            $stmt = $pdo->prepare("
                UPDATE products 
                SET uom_type = 'group', uom_group_id = ?, default_unit_id = NULL, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND tenant_id = ?
            ");
            
            $updated_count = 0;
            foreach ($product_ids as $product_id) {
                $stmt->execute([$uom_group_id, $product_id, $tenant_id]);
                $updated_count += $stmt->rowCount();
            }
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "UOM assigned to {$updated_count} product(s) successfully",
        'updated_count' => $updated_count
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
