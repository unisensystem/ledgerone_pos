<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

$user_id = $_SESSION['user_id'] ?? 1;
$tenant_id = $_SESSION['tenant_id'] ?? 1;

// GET - Fetch UOMs or base units
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.uom_name,
                u.uom_type,
                u.base_unit_id,
                u.conversion_factor,
                u.is_base_unit,
                b.uom_name as base_unit_name
            FROM uom u
            LEFT JOIN uom b ON u.base_unit_id = b.id
            WHERE u.tenant_id = ?
            ORDER BY u.uom_type, u.is_base_unit DESC, u.uom_name
        ");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'view' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.uom_name,
                u.uom_type,
                u.base_unit_id,
                u.conversion_factor,
                u.is_base_unit,
                b.uom_name as base_unit_name
            FROM uom u
            LEFT JOIN uom b ON u.base_unit_id = b.id
            WHERE u.id = ? AND u.tenant_id = ?
        ");
        $stmt->execute([$_GET['id'], $tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
        exit;
    }
    
    if ($action === 'base_units' && isset($_GET['type'])) {
        $stmt = $pdo->prepare("SELECT id, uom_name FROM uom WHERE tenant_id = ? AND uom_type = ? AND is_base_unit = 1");
        $stmt->execute([$tenant_id, $_GET['type']]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// POST - Create or Update UOM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    $uom_name = $data['uom_name'] ?? null;
    $uom_type = $data['uom_type'] ?? null;
    $is_base_unit = $data['is_base_unit'] ?? 0;
    $base_unit_id = $data['base_unit_id'] ?? null;
    $conversion_factor = $data['conversion_factor'] ?? 1;
    
    // Debug log
    error_log("UOM Data: " . json_encode($data));
    error_log("uom_type value: " . $uom_type);
    
    if (!$uom_name || !$uom_type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Business Rule 1: If base unit, set conversion to 1 and base_unit_id to NULL
    if ($is_base_unit == 1) {
        $base_unit_id = null;
        $conversion_factor = 1;
    } else {
        // Business Rule 2: Derived unit must have base unit
        if (!$base_unit_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Derived unit must have a base unit']);
            exit;
        }
    }
    
    // Business Rule 3: Conversion factor must be > 0
    if ($conversion_factor <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Conversion factor must be greater than 0']);
        exit;
    }
    
    try {
        // Business Rule 4: Only ONE base unit per type (except when updating same record)
        if ($is_base_unit == 1) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM uom WHERE tenant_id = ? AND uom_type = ? AND is_base_unit = 1" . ($id ? " AND id != ?" : ""));
            if ($id) {
                $checkStmt->execute([$tenant_id, $uom_type, $id]);
            } else {
                $checkStmt->execute([$tenant_id, $uom_type]);
            }
            
            if ($checkStmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'A base unit already exists for this type']);
                exit;
            }
        }
        
        if ($id) {
            // Update
            $stmt = $pdo->prepare("SELECT uom_name, uom_type, base_unit_id, conversion_factor, is_base_unit FROM uom WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            $oldUom = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$oldUom) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'UOM not found']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE uom SET uom_name = ?, uom_type = ?, base_unit_id = ?, conversion_factor = ?, is_base_unit = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$uom_name, $uom_type, $base_unit_id, $conversion_factor, $is_base_unit, $id, $tenant_id]);

            $newUom = ['uom_name' => $uom_name, 'uom_type' => $uom_type, 'base_unit_id' => $base_unit_id, 'conversion_factor' => $conversion_factor, 'is_base_unit' => $is_base_unit];
            $changeDescriptions = buildChangeDescription($oldUom, $newUom, [
                'uom_name' => 'Name', 'uom_type' => 'Type', 'base_unit_id' => 'Base Unit', 'conversion_factor' => 'Conversion Factor', 'is_base_unit' => 'Is Base Unit'
            ]);
            $editDescription = !empty($changeDescriptions)
                ? "Updated unit of measurement \"$uom_name\": " . implode('; ', $changeDescriptions)
                : "Updated unit of measurement \"$uom_name\" - no tracked field changes";
            logActivity($pdo, $tenant_id, $user_id, 'update', 'Unit of Measurement', $id, $uom_name, $editDescription, $oldUom, $newUom);

            echo json_encode(['success' => true, 'message' => 'UOM updated successfully']);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO uom (tenant_id, uom_name, uom_type, base_unit_id, conversion_factor, is_base_unit) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $uom_name, $uom_type, $base_unit_id, $conversion_factor, $is_base_unit]);
            $newUomId = $pdo->lastInsertId();

            logActivity(
                $pdo, $tenant_id, $user_id, 'create', 'Unit of Measurement', $newUomId, $uom_name,
                "Created unit of measurement \"$uom_name\" ($uom_type" . ($is_base_unit ? ', base unit' : ", conversion $conversion_factor") . ")",
                null,
                ['uom_name' => $uom_name, 'uom_type' => $uom_type, 'base_unit_id' => $base_unit_id, 'conversion_factor' => $conversion_factor, 'is_base_unit' => $is_base_unit]
            );

            echo json_encode(['success' => true, 'message' => 'UOM added successfully', 'id' => $newUomId]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// DELETE - Delete UOM
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing UOM ID']);
        exit;
    }
    
    try {
        // Check if UOM is used as base unit for other UOMs
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM uom WHERE base_unit_id = ?");
        $checkStmt->execute([$id]);
        
        if ($checkStmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Cannot delete: This UOM is used as base unit for other units']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM uom WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);
        $uomRow = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("DELETE FROM uom WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);

        if ($uomRow) {
            logActivity(
                $pdo, $tenant_id, $user_id, 'delete', 'Unit of Measurement', $id, $uomRow['uom_name'],
                "Deleted unit of measurement \"{$uomRow['uom_name']}\"",
                $uomRow, null
            );
        }

        echo json_encode(['success' => true, 'message' => 'UOM deleted successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
