<?php
header('Content-Type: application/json');

session_start();
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$tenant_id) {
        throw new Exception('Tenant ID not found in session');
    }

    if (!$data || !isset($data['id'])) {
        throw new Exception('Missing required field: id');
    }

    $id = $data['id'];
    $project_id = $data['project_id'] ?? null;
    $product_id = $data['product_id'] ?? null;
    $rate = $data['rate'] ?? null;

    // Verify this rate belongs to current tenant
    $verifyQuery = "SELECT id, project_id, product_id, rate FROM project_product_rates WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($verifyQuery);
    $stmt->execute([$id, $tenant_id]);
    $existing = $stmt->fetch();
    
    if (!$existing) {
        throw new Exception('Rate not found or you do not have permission to update it');
    }

    // Use existing values if not provided
    $new_project_id = $project_id ?? $existing['project_id'];
    $new_product_id = $product_id ?? $existing['product_id'];

    // Check for duplicate if project or product is being changed
    if ($new_project_id != $existing['project_id'] || $new_product_id != $existing['product_id']) {
        $checkQuery = "SELECT id FROM project_product_rates WHERE project_id = ? AND product_id = ? AND tenant_id = ? AND id != ?";
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([$new_project_id, $new_product_id, $tenant_id, $id]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception('A rate already exists for this project-product combination');
        }
    }

    // Build dynamic update query
    $updateFields = [];
    $params = [];

    if ($project_id !== null) {
        $updateFields[] = 'project_id = ?';
        $params[] = $project_id;
    }

    if ($product_id !== null) {
        $updateFields[] = 'product_id = ?';
        $params[] = $product_id;
    }

    if ($rate !== null) {
        if (!is_numeric($rate) || $rate < 0) {
            throw new Exception('Invalid rate value');
        }
        $updateFields[] = 'rate = ?';
        $params[] = (float)$rate;
    }

    if (empty($updateFields)) {
        throw new Exception('No fields to update');
    }

    // Add updated_at and id to params
    $updateFields[] = 'updated_at = NOW()';
    $params[] = $id;
    $params[] = $tenant_id;

    $query = "UPDATE project_product_rates SET " . implode(', ', $updateFields) . " WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($query);
    
    if ($stmt->execute($params)) {
        $newValues = ['project_id' => $new_project_id, 'product_id' => $new_product_id, 'rate' => $rate !== null ? (float)$rate : (float)$existing['rate']];
        $changeDescriptions = buildChangeDescription($existing, $newValues, [
            'project_id' => 'Project', 'product_id' => 'Product', 'rate' => 'Rate'
        ]);
        $editDescription = !empty($changeDescriptions)
            ? "Updated project product rate (Project #{$existing['project_id']} / Product #{$existing['product_id']}): " . implode('; ', $changeDescriptions)
            : "Updated project product rate (Project #{$existing['project_id']} / Product #{$existing['product_id']}) - no tracked field changes";
        logActivity($pdo, $tenant_id, $user_id, 'update', 'Project Product Rate', $id, "Project #{$existing['project_id']} / Product #{$existing['product_id']}", $editDescription, $existing, $newValues);

        echo json_encode([
            'success' => true,
            'message' => 'Rate updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update rate');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
