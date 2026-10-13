<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// GET — fetch a single unit's full data, used by the Edit Unit modal to
// populate its fields. Previously missing entirely, so the edit form only
// ever showed whatever was already sitting in the fields (defaults / the
// last-used values) instead of the unit actually being edited.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $unit_id = $_GET['id'] ?? '';
    if (empty($unit_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unit ID is required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, uom_name, uom_type, unit_scope, is_base_unit, base_unit_id, conversion_factor
            FROM uom
            WHERE id = ? AND (tenant_id = ? OR tenant_id = 0)
        ");
        $stmt->execute([$unit_id, $tenant_id]);
        $unit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$unit) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Unit not found']);
            exit;
        }

        echo json_encode(['success' => true, 'unit' => $unit]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $unit_id = $_POST['id'] ?? '';
    $uom_name = $_POST['unitName'] ?? '';
    $uom_type = $_POST['unitType'] ?? 'count';
    $unit_scope = $_POST['unitScope'] ?? 'universal';
    $is_base_unit = (isset($_POST['isBaseUnit']) && $_POST['isBaseUnit'] === 'on') ? 1 : 0;
    $base_unit_id = $_POST['baseUnit'] ?? null;
    $conversion_factor = $_POST['conversionFactor'] ?? 1;
    
    if (empty($unit_id) || empty($uom_name)) {
        echo json_encode(['success' => false, 'message' => 'Unit ID and name are required']);
        exit;
    }
    
    // Verify unit belongs to tenant
    $stmt = $pdo->prepare("SELECT id FROM uom WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$unit_id, $tenant_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Unit not found']);
        exit;
    }
    
    // For per_product scope, don't force is_base_unit to 0
    if ($unit_scope === 'per_product') {
        if ($is_base_unit) {
            $base_unit_id = null;
        }
        $conversion_factor = 1;
    }
    
    $stmt = $pdo->prepare("
        UPDATE uom SET uom_name = ?, uom_type = ?, base_unit_id = ?, conversion_factor = ?, is_base_unit = ?, unit_scope = ?
        WHERE id = ? AND tenant_id = ? AND tenant_id != 0
    ");
    
    $base_unit_param = ($is_base_unit || empty($base_unit_id)) ? null : $base_unit_id;
    
    $stmt->execute([$uom_name, $uom_type, $base_unit_param, $conversion_factor, $is_base_unit, $unit_scope, $unit_id, $tenant_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Unit updated successfully',
        'unit' => [
            'id' => $unit_id,
            'name' => $uom_name,
            'type' => $uom_type
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}