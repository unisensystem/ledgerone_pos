<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

$user_id = $_SESSION['user_id'] ?? 1;
$tenant_id = $_SESSION['tenant_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    $product_id = $data['product_id'] ?? null;
    $bom_id = $data['bom_id'] ?? null;
    $branch_id = $data['branch_id'] ?? null;
    $machine_id = $data['machine_id'] ?? null;
    $order_qty = $data['order_qty'] ?? null;
    $start_date = !empty($data['start_date']) ? $data['start_date'] : null;
    $end_date = !empty($data['end_date']) ? $data['end_date'] : null;
    $materials = $data['materials'] ?? [];
    
    if (!$id || !$product_id || !$bom_id || !$branch_id || !$order_qty || empty($materials)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    if ($order_qty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order quantity must be greater than 0']);
        exit;
    }
    
    try {
        // Snapshot old header + materials for the Activity Log (before mutation)
        $stOld = $pdo->prepare("SELECT order_no, product_id, bom_id, branch_id, machine_id, order_qty, start_date, end_date, status FROM production_orders WHERE id = ? AND tenant_id = ?");
        $stOld->execute([$id, $tenant_id]);
        $oldOrder = $stOld->fetch(PDO::FETCH_ASSOC);
        if (!$oldOrder) throw new Exception('Production order not found');

        $stOldMat = $pdo->prepare("SELECT material_id, required_qty, uom_id FROM production_order_materials WHERE production_order_id = ? ORDER BY id");
        $stOldMat->execute([$id]);
        $oldMaterials = $stOldMat->fetchAll(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE production_orders SET product_id = ?, bom_id = ?, branch_id = ?, machine_id = ?, order_qty = ?, start_date = ?, end_date = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$product_id, $bom_id, $branch_id, $machine_id, $order_qty, $start_date, $end_date, $id, $tenant_id]);

        $stmt = $pdo->prepare("DELETE FROM production_order_materials WHERE production_order_id = ?");
        $stmt->execute([$id]);

        $insertStmt = $pdo->prepare("INSERT INTO production_order_materials (production_order_id, material_id, required_qty, uom_id, status) VALUES (?, ?, ?, ?, 'Pending')");
        foreach ($materials as $mat) {
            $insertStmt->execute([$id, $mat['material_id'], $mat['required_qty'], $mat['uom_id']]);
        }

        $newOrder = ['order_no' => $oldOrder['order_no'], 'product_id' => $product_id, 'bom_id' => $bom_id, 'branch_id' => $branch_id, 'machine_id' => $machine_id, 'order_qty' => $order_qty, 'start_date' => $start_date, 'end_date' => $end_date, 'status' => $oldOrder['status']];
        $changeDescriptions = buildChangeDescription($oldOrder, $newOrder, [
            'product_id' => 'Product', 'bom_id' => 'BOM', 'branch_id' => 'Branch', 'machine_id' => 'Machine',
            'order_qty' => 'Order Qty', 'start_date' => 'Start Date', 'end_date' => 'End Date'
        ]);
        $newMaterialsForCompare = array_map(fn($m) => ['material_id' => $m['material_id'], 'required_qty' => (float)$m['required_qty'], 'uom_id' => $m['uom_id']], $materials);
        if (itemsChanged($oldMaterials, $newMaterialsForCompare, ['material_id', 'required_qty', 'uom_id'])) {
            $changeDescriptions[] = 'Materials updated';
        }
        $editDescription = !empty($changeDescriptions)
            ? "Updated production order \"{$oldOrder['order_no']}\": " . implode('; ', $changeDescriptions)
            : "Updated production order \"{$oldOrder['order_no']}\" - no tracked field changes";
        logActivity($pdo, $tenant_id, $user_id, 'update', 'Production Order', $id, $oldOrder['order_no'], $editDescription, $oldOrder, $newOrder);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);