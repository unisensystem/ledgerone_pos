<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

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
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// GET - Fetch BOM list or single BOM
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';

    try {
        if ($action === 'list') {
            $stmt = $pdo->prepare("
                SELECT
                    bom.id,
                    bom.bom_code,
                    bom.version,
                    bom.is_active,
                    bom.bom_base_qty,
                    bom.batch_locked,
                    bom.remarks,
                    bom.updated_at,
                    p.code as fg_code,
                    p.name as fg_name,
                    (SELECT COUNT(*) FROM bom_materials WHERE bom_id = bom.id) as material_count
                FROM bill_of_materials bom
                JOIN products p ON bom.finished_good_id = p.id
                WHERE bom.tenant_id = ?
                ORDER BY bom.updated_at DESC
            ");
            $stmt->execute([$tenant_id]);
            $boms = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $boms]);
            exit;
        }

        if ($action === 'view' && isset($_GET['id'])) {
            $stmt = $pdo->prepare("
                SELECT
                    bom.id,
                    bom.bom_code,
                    bom.version,
                    bom.is_active,
                    bom.bom_base_qty,
                    bom.batch_locked,
                    bom.remarks,
                    bom.created_at,
                    bom.updated_at,
                    p.code as fg_code,
                    p.name as fg_name,
                    bom.finished_good_id
                FROM bill_of_materials bom
                JOIN products p ON bom.finished_good_id = p.id
                WHERE bom.id = ? AND bom.tenant_id = ?
            ");
            $stmt->execute([$_GET['id'], $tenant_id]);
            $bom = $stmt->fetch();

            if ($bom) {
                $stmt = $pdo->prepare("
                    SELECT
                        bm.id,
                        bm.quantity,
                        bm.raw_material_id,
                        bm.unit_id,
                        p.code as material_code,
                        p.name as material_name,
                        u.uom_name as unit_name
                    FROM bom_materials bm
                    JOIN products p ON bm.raw_material_id = p.id
                    JOIN uom u ON bm.unit_id = u.id
                    WHERE bm.bom_id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $bom['materials'] = $stmt->fetchAll();
            }

            echo json_encode(['success' => true, 'data' => $bom]);
            exit;
        }

        if ($action === 'finished_goods') {
            $stmt = $pdo->prepare("SELECT id, code, name FROM products WHERE tenant_id = ? AND inventory_account_id = 117 AND is_active = 1");
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// PUT - Update BOM
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $bom_id = $data['id'] ?? null;
    $version = $data['version'] ?? null;
    $is_active = $data['is_active'] ?? 0;
    $bom_base_qty = $data['bom_base_qty'] ?? 1;
    $batch_locked = $data['batch_locked'] ?? 0;
    $materials = $data['materials'] ?? [];

    if (!$bom_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing BOM ID']);
        exit;
    }

    try {
        // Snapshot old header + materials for the Activity Log (before mutation)
        $stOld = $pdo->prepare("SELECT bom_code, version, is_active, bom_base_qty, batch_locked, remarks FROM bill_of_materials WHERE id = ? AND tenant_id = ?");
        $stOld->execute([$bom_id, $tenant_id]);
        $oldBom = $stOld->fetch(PDO::FETCH_ASSOC);
        if (!$oldBom) throw new Exception('BOM not found');

        $stOldMat = $pdo->prepare("SELECT raw_material_id, quantity, unit_id FROM bom_materials WHERE bom_id = ? ORDER BY id");
        $stOldMat->execute([$bom_id]);
        $oldMaterials = $stOldMat->fetchAll(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE bill_of_materials SET version = ?, is_active = ?, bom_base_qty = ?, batch_locked = ?, remarks = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$version, $is_active, $bom_base_qty, $batch_locked, $data['remarks'] ?? null, $bom_id, $tenant_id]);

        $stmt = $pdo->prepare("DELETE FROM bom_materials WHERE bom_id = ?");
        $stmt->execute([$bom_id]);

        $stmt = $pdo->prepare("INSERT INTO bom_materials (bom_id, raw_material_id, quantity, unit_id) VALUES (?, ?, ?, ?)");
        foreach ($materials as $material) {
            $stmt->execute([$bom_id, $material['raw_material_id'], $material['quantity'], $material['unit_id']]);
        }

        $newBom = ['bom_code' => $oldBom['bom_code'], 'version' => $version, 'is_active' => $is_active, 'bom_base_qty' => $bom_base_qty, 'batch_locked' => $batch_locked, 'remarks' => $data['remarks'] ?? null];
        $changeDescriptions = buildChangeDescription($oldBom, $newBom, [
            'version' => 'Version', 'is_active' => 'Active', 'bom_base_qty' => 'Base Qty', 'batch_locked' => 'Batch Locked', 'remarks' => 'Remarks'
        ]);
        $newMaterialsForCompare = array_map(fn($m) => ['raw_material_id' => $m['raw_material_id'], 'quantity' => (float)$m['quantity'], 'unit_id' => $m['unit_id']], $materials);
        if (itemsChanged($oldMaterials, $newMaterialsForCompare, ['raw_material_id', 'quantity', 'unit_id'])) {
            $changeDescriptions[] = 'Materials updated';
        }
        $editDescription = !empty($changeDescriptions)
            ? "Updated BOM \"{$oldBom['bom_code']}\": " . implode('; ', $changeDescriptions)
            : "Updated BOM \"{$oldBom['bom_code']}\" - no tracked field changes";
        logActivity($pdo, $tenant_id, $user_id, 'update', 'Bill of Materials', $bom_id, $oldBom['bom_code'], $editDescription, $oldBom, $newBom);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'BOM updated successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// DELETE - Delete BOM
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing BOM ID']);
        exit;
    }
    
    try {
        // Grab full row before deletion, for the Activity Log
        $stOld = $pdo->prepare("SELECT * FROM bill_of_materials WHERE id = ? AND tenant_id = ?");
        $stOld->execute([$id, $tenant_id]);
        $bomRow = $stOld->fetch(PDO::FETCH_ASSOC);
        if (!$bomRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'BOM not found']);
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM bom_materials WHERE bom_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM bill_of_materials WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);

        logActivity(
            $pdo, $tenant_id, $user_id, 'delete', 'Bill of Materials', $id, $bomRow['bom_code'],
            "Deleted BOM \"{$bomRow['bom_code']}\"",
            $bomRow, null
        );

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'BOM deleted successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
