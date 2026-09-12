<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

$user_id = $_SESSION['user_id'] ?? 1;
$tenant_id = $_SESSION['tenant_id'] ?? 1;

// GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT 
                po.id,
                po.order_no,
                po.order_qty,
                po.status,
                po.start_date,
                po.end_date,
                po.bom_version,
                p.code as product_code,
                p.name as product_name,
                b.branch_name,
                bom.bom_code
            FROM production_orders po
            JOIN products p ON po.product_id = p.id
            JOIN branches b ON po.branch_id = b.id
            JOIN bill_of_materials bom ON po.bom_id = bom.id
            WHERE po.tenant_id = ?
            ORDER BY po.created_at DESC
        ");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'view' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT 
                po.*,
                p.code as product_code,
                p.name as product_name,
                b.branch_name,
                bom.bom_code,
                bom.bom_type,
                m.code as machine_code,
                m.machine_name
            FROM production_orders po
            JOIN products p ON po.product_id = p.id
            JOIN branches b ON po.branch_id = b.id
            JOIN bill_of_materials bom ON po.bom_id = bom.id
            LEFT JOIN machines m ON po.machine_id = m.id
            WHERE po.id = ? AND po.tenant_id = ?
        ");
        $stmt->execute([$_GET['id'], $tenant_id]);
        $order = $stmt->fetch();
        
        if ($order) {
            $stmt = $pdo->prepare("
                SELECT 
                    pom.id,
                    pom.production_order_id,
                    pom.material_id,
                    pom.required_qty,
                    pom.issued_qty,
                    pom.uom_id,
                    pom.status,
                    p.code as material_code,
                    p.name as material_name,
                    COALESCE(u.uom_name, 'N/A') as unit_name
                FROM production_order_materials pom
                JOIN products p ON pom.material_id = p.id
                LEFT JOIN uom u ON pom.uom_id = u.id
                WHERE pom.production_order_id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $order['materials'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'data' => $order]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// PUT - Update Status
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    $status = $data['status'] ?? null;
    
    if (!$id || !$status) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT order_no, status as old_status FROM production_orders WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);
        $orderRow = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("UPDATE production_orders SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$status, $id, $tenant_id]);

        if ($orderRow) {
            $editDescription = $orderRow['old_status'] !== $status
                ? "Updated production order \"{$orderRow['order_no']}\" status: {$orderRow['old_status']} \u{2192} $status"
                : "Updated production order \"{$orderRow['order_no']}\" - no tracked field changes";
            logActivity($pdo, $tenant_id, $user_id, 'update', 'Production Order', $id, $orderRow['order_no'], $editDescription, ['status' => $orderRow['old_status']], ['status' => $status]);
        }

        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing order ID']);
        exit;
    }
    
    try {
        $stOld = $pdo->prepare("SELECT * FROM production_orders WHERE id = ? AND tenant_id = ?");
        $stOld->execute([$id, $tenant_id]);
        $orderRow = $stOld->fetch(PDO::FETCH_ASSOC);
        if (!$orderRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Production order not found']);
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM production_order_materials WHERE production_order_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM production_orders WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);

        logActivity(
            $pdo, $tenant_id, $user_id, 'delete', 'Production Order', $id, $orderRow['order_no'],
            "Deleted production order \"{$orderRow['order_no']}\"",
            $orderRow, null
        );

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Order deleted successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
