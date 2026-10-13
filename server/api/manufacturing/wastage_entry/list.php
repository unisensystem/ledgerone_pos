<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

$user_id = $_SESSION['user_id'] ?? 1;
$tenant_id = $_SESSION['tenant_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';

    try {
        if ($action === 'list') {
            $stmt = $pdo->prepare("
                SELECT
                    pw.id,
                    pw.wastage_no,
                    pw.wastage_date,
                    pw.wastage_type,
                    pw.remarks,
                    pw.created_at,
                    po.order_no,
                    p.name as product_name
                FROM production_wastage pw
                JOIN production_orders po ON pw.production_order_id = po.id
                JOIN products p ON po.product_id = p.id
                WHERE pw.tenant_id = ?
                ORDER BY pw.created_at DESC
            ");
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($action === 'view' && isset($_GET['id'])) {
            $stmt = $pdo->prepare("
                SELECT
                    pw.*,
                    po.order_no,
                    po.order_qty,
                    p.name as product_name,
                    b.branch_name
                FROM production_wastage pw
                JOIN production_orders po ON pw.production_order_id = po.id
                JOIN products p ON po.product_id = p.id
                JOIN branches b ON po.branch_id = b.id
                WHERE pw.id = ? AND pw.tenant_id = ?
            ");
            $stmt->execute([$_GET['id'], $tenant_id]);
            $wastage = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($wastage) {
                $itemStmt = $pdo->prepare("
                    SELECT
                        pwi.*,
                        p.code as material_code,
                        p.name as material_name,
                        COALESCE(u.uom_name, 'N/A') as uom_name
                    FROM production_wastage_items pwi
                    JOIN products p ON pwi.material_id = p.id
                    LEFT JOIN uom u ON pwi.uom_id = u.id
                    WHERE pwi.wastage_id = ?
                    ORDER BY pwi.id
                ");
                $itemStmt->execute([$_GET['id']]);
                $wastage['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode(['success' => true, 'data' => $wastage]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing wastage ID']);
        exit;
    }

    try {
        // Verify ownership (grab full row for the Activity Log)
        $check = $pdo->prepare("SELECT * FROM production_wastage WHERE id = ? AND tenant_id = ?");
        $check->execute([$id, $tenant_id]);
        $wastageRow = $check->fetch(PDO::FETCH_ASSOC);
        if (!$wastageRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Record not found']);
            exit;
        }

        $pdo->beginTransaction();

        // Reverse stock_ledger entries
        $stmt = $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'production_wastage' AND reference_id = ?");
        $stmt->execute([$id]);

        // Delete accounting_ledger entries
        $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'production_wastage' AND reference_id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);

        $stmt = $pdo->prepare("DELETE FROM production_wastage_items WHERE wastage_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM production_wastage WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);

        logActivity(
            $pdo, $tenant_id, $user_id, 'delete', 'Production Wastage', $id, $wastageRow['wastage_no'],
            "Deleted wastage entry \"{$wastageRow['wastage_no']}\"",
            $wastageRow, null
        );

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Wastage entry deleted successfully']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
