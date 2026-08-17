<?php
ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../../includes/connection.php';

ob_end_clean();

$user_id = $_SESSION['user_id'] ?? 1;
$tenant_id = $_SESSION['tenant_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    try {
        if ($action === 'next_wastage_no') {
            $stmt = $pdo->prepare("SELECT wastage_no FROM production_wastage WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$tenant_id]);
            $lastNo = $stmt->fetchColumn();

            if ($lastNo && preg_match('/WE-(\d+)/', $lastNo, $matches)) {
                $nextNum = intval($matches[1]) + 1;
            } else {
                $nextNum = 1;
            }

            echo json_encode(['success' => true, 'wastage_no' => 'WE-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT)]);
            exit;
        }

        if ($action === 'production_orders') {
            $stmt = $pdo->prepare("
                SELECT po.id, po.order_no, po.status, p.name as product_name
                FROM production_orders po
                JOIN products p ON po.product_id = p.id
                WHERE po.tenant_id = ?
                AND po.status IN ('In Progress', 'Completed')
                ORDER BY po.created_at DESC
            ");
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($action === 'load_materials' && isset($_GET['po_id'])) {
            $poId = $_GET['po_id'];

            // Finished good from the production order
            $fgStmt = $pdo->prepare("
                SELECT
                    po.product_id,
                    po.order_qty,
                    p.code as product_code,
                    p.name as product_name,
                    COALESCE(u.uom_name, 'N/A') as uom_name,
                    p.default_unit_id as uom_id
                FROM production_orders po
                JOIN products p ON po.product_id = p.id
                LEFT JOIN uom u ON p.default_unit_id = u.id
                WHERE po.id = ? AND po.tenant_id = ?
            ");
            $fgStmt->execute([$poId, $tenant_id]);
            $finishedGood = $fgStmt->fetch(PDO::FETCH_ASSOC);

            // Raw materials
            $matStmt = $pdo->prepare("
                SELECT
                    pom.material_id,
                    pom.required_qty as ordered_qty,
                    pom.uom_id,
                    p.code as material_code,
                    p.name as material_name,
                    COALESCE(u.uom_name, 'N/A') as uom_name
                FROM production_order_materials pom
                JOIN products p ON pom.material_id = p.id
                LEFT JOIN uom u ON pom.uom_id = u.id
                WHERE pom.production_order_id = ?
            ");
            $matStmt->execute([$poId]);

            echo json_encode([
                'success'       => true,
                'finished_good' => $finishedGood,
                'materials'     => $matStmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $wastage_no          = $data['wastage_no'] ?? null;
    $production_order_id = $data['production_order_id'] ?? null;
    $wastage_date        = $data['wastage_date'] ?? null;
    $wastage_type        = $data['wastage_type'] ?? 'quantity';
    $remarks             = $data['remarks'] ?? null;
    $items               = $data['items'] ?? [];

    // Finished good wastage (optional)
    $fg_product_id    = $data['fg_product_id'] ?? null;
    $fg_uom_id        = $data['fg_uom_id'] ?? null;
    $fg_wastage_input = isset($data['fg_wastage_input']) ? floatval($data['fg_wastage_input']) : 0;
    $fg_wastage_qty   = isset($data['fg_wastage_qty'])   ? floatval($data['fg_wastage_qty'])   : 0;

    if (!$wastage_no || !$production_order_id || !$wastage_date) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    if (empty($items) && $fg_wastage_qty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please enter wastage for at least one material or the finished good']);
        exit;
    }

    if (!in_array($wastage_type, ['percentage', 'quantity'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid wastage type']);
        exit;
    }

    // Get branch_id and product info from the production order
    $poStmt = $pdo->prepare("SELECT branch_id FROM production_orders WHERE id = ? AND tenant_id = ?");
    $poStmt->execute([$production_order_id, $tenant_id]);
    $po = $poStmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Production order not found']);
        exit;
    }

    $branch_id = $po['branch_id'];

    try {
        $pdo->beginTransaction();

        // Insert wastage header (with FG columns)
        $stmt = $pdo->prepare("
            INSERT INTO production_wastage
            (tenant_id, wastage_no, production_order_id, wastage_date, wastage_type,
             fg_wastage_type, fg_wastage_input, fg_wastage_qty, remarks, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenant_id,
            $wastage_no,
            $production_order_id,
            $wastage_date,
            $wastage_type,
            $fg_wastage_qty > 0 ? $wastage_type : null,
            $fg_wastage_qty > 0 ? $fg_wastage_input : null,
            $fg_wastage_qty > 0 ? $fg_wastage_qty : null,
            $remarks,
            $user_id
        ]);
        $wastage_id = $pdo->lastInsertId();

        // Raw material items + stock movements (account 116)
        if (!empty($items)) {
            $itemStmt = $pdo->prepare("
                INSERT INTO production_wastage_items
                (wastage_id, material_id, uom_id, ordered_qty, wastage_input, wastage_qty)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stockStmt = $pdo->prepare("
                INSERT INTO stock_ledger
                (tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                 qty_out, unit_cost, unit_id, transaction_type, transaction_date, created_at)
                VALUES (?, 116, ?, ?, 'production_wastage', ?, ?, 0, ?, 'WASTAGE', ?, NOW())
            ");

            foreach ($items as $item) {
                $wastage_qty = floatval($item['wastage_qty'] ?? 0);
                if ($wastage_qty <= 0) continue;

                $itemStmt->execute([
                    $wastage_id,
                    $item['material_id'],
                    $item['uom_id'],
                    $item['ordered_qty'],
                    $item['wastage_input'],
                    $wastage_qty
                ]);

                $stockStmt->execute([
                    $tenant_id,
                    $branch_id,
                    $item['material_id'],
                    $wastage_id,
                    $wastage_qty,
                    $item['uom_id'],
                    $wastage_date
                ]);
            }
        }

        // Finished good wastage stock movement (account 117)
        if ($fg_wastage_qty > 0 && $fg_product_id && $fg_uom_id) {
            $fgStockStmt = $pdo->prepare("
                INSERT INTO stock_ledger
                (tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                 qty_out, unit_cost, unit_id, transaction_type, transaction_date, created_at)
                VALUES (?, 117, ?, ?, 'production_wastage', ?, ?, 0, ?, 'WASTAGE_FG', ?, NOW())
            ");
            $fgStockStmt->execute([
                $tenant_id,
                $branch_id,
                $fg_product_id,
                $wastage_id,
                $fg_wastage_qty,
                $fg_uom_id,
                $wastage_date
            ]);
        }

        // Accounting Ledger Entries
        $glStmt = $pdo->prepare("
            INSERT INTO accounting_ledger
            (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit, created_at)
            VALUES (?, 'wastage', 'production_wastage', ?, ?, ?, ?, ?, ?, NOW())
        ");

        // Raw material wastage: Dr. Wastage/Loss Account (account 116 out = loss from WIP)
        foreach ($items as $item) {
            $wastage_qty = floatval($item['wastage_qty'] ?? 0);
            if ($wastage_qty <= 0) continue;

            // Get unit cost from stock_ledger
            $costStmt = $pdo->prepare("SELECT unit_cost FROM stock_ledger WHERE tenant_id = ? AND product_id = ? AND unit_id = ? AND qty_in > 0 ORDER BY transaction_date DESC LIMIT 1");
            $costStmt->execute([$tenant_id, $item['material_id'], $item['uom_id']]);
            $unitCost = $costStmt->fetchColumn() ?: 0;
            $totalCost = $wastage_qty * $unitCost;

            if ($totalCost > 0) {
                // Debit: Wastage/Loss (account 116 - WIP reduced)
                $glStmt->execute([$tenant_id, $wastage_id, 116, $wastage_date, 'Raw material wastage: ' . $wastage_no, $totalCost, 0]);
                // Credit: WIP Inventory (account 116)
                $glStmt->execute([$tenant_id, $wastage_id, 116, $wastage_date, 'Raw material wastage: ' . $wastage_no, 0, $totalCost]);
            }
        }

        // Finished good wastage: Dr. Loss, Cr. Finished Goods (account 117)
        if ($fg_wastage_qty > 0 && $fg_product_id && $fg_uom_id) {
            $fgCostStmt = $pdo->prepare("SELECT unit_cost FROM stock_ledger WHERE tenant_id = ? AND product_id = ? AND unit_id = ? AND qty_in > 0 ORDER BY transaction_date DESC LIMIT 1");
            $fgCostStmt->execute([$tenant_id, $fg_product_id, $fg_uom_id]);
            $fgUnitCost = $fgCostStmt->fetchColumn() ?: 0;
            $fgTotalCost = $fg_wastage_qty * $fgUnitCost;

            if ($fgTotalCost > 0) {
                // Debit: Wastage/Loss
                $glStmt->execute([$tenant_id, $wastage_id, 117, $wastage_date, 'Finished good wastage: ' . $wastage_no, $fgTotalCost, 0]);
                // Credit: Finished Goods Inventory (account 117)
                $glStmt->execute([$tenant_id, $wastage_id, 117, $wastage_date, 'Finished good wastage: ' . $wastage_no, 0, $fgTotalCost]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Wastage entry saved successfully', 'wastage_id' => $wastage_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
