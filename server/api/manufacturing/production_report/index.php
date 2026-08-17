<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
require_once '../../../../includes/connection.php';
ob_end_clean();

$user_id   = $_SESSION['user_id']   ?? 1;
$tenant_id = $_SESSION['tenant_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action  = $_GET['action'] ?? '';
$filters = [
    'date_from'  => $_GET['date_from']  ?? '',
    'date_to'    => $_GET['date_to']    ?? '',
    'branch_id'  => $_GET['branch_id']  ?? '',
    'product_id' => $_GET['product_id'] ?? '',
    'status'     => $_GET['status']     ?? '',
];

function buildPoFilter(array $filters, array &$params): string {
    $w = '';
    if (!empty($filters['date_from'])) {
        $w .= ' AND po.created_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $w .= ' AND po.created_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    if (!empty($filters['branch_id'])) {
        $w .= ' AND po.branch_id = ?';
        $params[] = $filters['branch_id'];
    }
    if (!empty($filters['product_id'])) {
        $w .= ' AND po.product_id = ?';
        $params[] = $filters['product_id'];
    }
    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        $statuses = array_filter(array_map('trim', explode(',', $filters['status'])));
        if (!empty($statuses)) {
            $ph = implode(',', array_fill(0, count($statuses), '?'));
            $w .= " AND po.status IN ($ph)";
            foreach ($statuses as $s) $params[] = $s;
        }
    }
    return $w;
}

try {
    switch ($action) {

        // ── Dropdown data for filter bar ────────────────────────────────────
        case 'filters_data': {
            $brStmt = $pdo->prepare("SELECT id, branch_name FROM branches WHERE tenant_id = ? AND is_active = 1 ORDER BY branch_name");
            $brStmt->execute([$tenant_id]);

            $prStmt = $pdo->prepare("
                SELECT DISTINCT p.id, p.code, p.name
                FROM production_orders po
                JOIN products p ON po.product_id = p.id
                WHERE po.tenant_id = ?
                ORDER BY p.name
            ");
            $prStmt->execute([$tenant_id]);

            echo json_encode([
                'success'  => true,
                'branches' => $brStmt->fetchAll(PDO::FETCH_ASSOC),
                'products' => $prStmt->fetchAll(PDO::FETCH_ASSOC),
            ]);
            break;
        }

        // ── Overview: KPI cards ─────────────────────────────────────────────
        case 'overview_kpis': {
            $p = [$tenant_id]; $f = buildPoFilter($filters, $p);
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_orders, COALESCE(SUM(order_qty),0) as total_ordered,
                    SUM(CASE WHEN status='Completed'   THEN 1 ELSE 0 END) as cnt_completed,
                    SUM(CASE WHEN status='In Progress' THEN 1 ELSE 0 END) as cnt_in_progress,
                    SUM(CASE WHEN status='Planned'     THEN 1 ELSE 0 END) as cnt_planned,
                    SUM(CASE WHEN status='Cancelled'   THEN 1 ELSE 0 END) as cnt_cancelled
                FROM production_orders po WHERE po.tenant_id = ? $f
            ");
            $stmt->execute($p);
            $orders = $stmt->fetch(PDO::FETCH_ASSOC);

            $p2 = [$tenant_id]; $f2 = buildPoFilter($filters, $p2);
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(cp.completed_qty),0)
                FROM completed_products cp
                JOIN production_completions pc ON cp.production_completion_id = pc.id
                JOIN production_orders po ON pc.production_order_id = po.id
                WHERE po.tenant_id = ? $f2
            ");
            $stmt->execute($p2);
            $produced = $stmt->fetchColumn();

            $p3 = [$tenant_id]; $f3 = buildPoFilter($filters, $p3);
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(pwi.wastage_qty),0)
                FROM production_wastage_items pwi
                JOIN production_wastage pw ON pwi.wastage_id = pw.id
                JOIN production_orders po ON pw.production_order_id = po.id
                WHERE po.tenant_id = ? $f3
            ");
            $stmt->execute($p3);
            $rawWastage = $stmt->fetchColumn();

            $p4 = [$tenant_id]; $f4 = buildPoFilter($filters, $p4);
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(pw.fg_wastage_qty),0)
                FROM production_wastage pw
                JOIN production_orders po ON pw.production_order_id = po.id
                WHERE po.tenant_id = ? $f4 AND pw.fg_wastage_qty IS NOT NULL
            ");
            $stmt->execute($p4);
            $fgWastage = $stmt->fetchColumn();

            $p5 = [$tenant_id]; $f5 = buildPoFilter($filters, $p5);
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(wp.total_cost),0)
                FROM work_in_progress wp
                JOIN production_orders po ON wp.production_order_id = po.id
                WHERE po.tenant_id = ? $f5
            ");
            $stmt->execute($p5);
            $matCost = $stmt->fetchColumn();

            $p6 = [$tenant_id]; $f6 = buildPoFilter($filters, $p6);
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(pe.total_amount),0)
                FROM production_expenses pe
                JOIN production_orders po ON pe.production_order_id = po.id
                WHERE po.tenant_id = ? $f6 AND pe.status = 'Posted'
            ");
            $stmt->execute($p6);
            $ovhCost = $stmt->fetchColumn();

            $p7 = [$tenant_id]; $f7 = buildPoFilter($filters, $p7);
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(cp.completed_qty * p.trade_price),0)
                FROM completed_products cp
                JOIN production_completions pc ON cp.production_completion_id = pc.id
                JOIN production_orders po ON pc.production_order_id = po.id
                JOIN products p ON cp.product_id = p.id
                WHERE po.tenant_id = ? $f7
            ");
            $stmt->execute($p7);
            $prodValue = $stmt->fetchColumn();

            echo json_encode(['success' => true, 'data' => [
                'total_orders'         => (int)$orders['total_orders'],
                'cnt_completed'        => (int)$orders['cnt_completed'],
                'cnt_in_progress'      => (int)$orders['cnt_in_progress'],
                'cnt_planned'          => (int)$orders['cnt_planned'],
                'cnt_cancelled'        => (int)$orders['cnt_cancelled'],
                'total_ordered'        => (float)$orders['total_ordered'],
                'total_produced'       => (float)$produced,
                'raw_wastage'          => (float)$rawWastage,
                'fg_wastage'           => (float)$fgWastage,
                'material_cost'        => (float)$matCost,
                'overhead_cost'        => (float)$ovhCost,
                'total_cost'           => (float)$matCost + (float)$ovhCost,
                'total_production_value' => (float)$prodValue,
            ]]);
            break;
        }

        // ── Overview: status bar chart ──────────────────────────────────────
        case 'overview_chart_status': {
            $p = [$tenant_id]; $f = buildPoFilter($filters, $p);
            $stmt = $pdo->prepare("
                SELECT status, COUNT(*) as count
                FROM production_orders po WHERE po.tenant_id = ? $f
                GROUP BY status
            ");
            $stmt->execute($p);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        // ── Overview: trend line chart ──────────────────────────────────────
        case 'overview_chart_trend': {
            $period = $_GET['period'] ?? 'week';
            if ($period === 'day') {
                $grp = "DATE(pc.complete_date)";
                $lbl = "DATE(pc.complete_date)";
            } elseif ($period === 'month') {
                $grp = "DATE_FORMAT(pc.complete_date,'%Y-%m')";
                $lbl = "DATE_FORMAT(pc.complete_date,'%Y-%m')";
            } else {
                $grp = "YEARWEEK(pc.complete_date,1)";
                $lbl = "DATE_FORMAT(MIN(pc.complete_date),'%Y-%m-%d')";
            }
            $p = [$tenant_id]; $f = buildPoFilter($filters, $p);
            $stmt = $pdo->prepare("
                SELECT $lbl as period_label, $grp as period_key,
                    SUM(cp.completed_qty) as units_produced,
                    COUNT(DISTINCT pc.production_order_id) as orders_count
                FROM completed_products cp
                JOIN production_completions pc ON cp.production_completion_id = pc.id
                JOIN production_orders po ON pc.production_order_id = po.id
                WHERE po.tenant_id = ? $f
                GROUP BY $grp ORDER BY $grp
            ");
            $stmt->execute($p);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        // ── Overview: PO table ──────────────────────────────────────────────
        case 'overview_table': {
            $p = [$tenant_id]; $f = buildPoFilter($filters, $p);
            $stmt = $pdo->prepare("
                SELECT
                    po.id, po.order_no, po.order_qty, po.status,
                    po.start_date, po.end_date, po.created_at,
                    p.name as product_name, b.branch_name,
                    (SELECT COALESCE(SUM(cp.completed_qty),0)
                     FROM production_completions pc2
                     JOIN completed_products cp ON cp.production_completion_id = pc2.id
                     WHERE pc2.production_order_id = po.id) as completed_qty,
                    (SELECT COALESCE(SUM(pwi.wastage_qty),0)
                     FROM production_wastage pw2
                     JOIN production_wastage_items pwi ON pwi.wastage_id = pw2.id
                     WHERE pw2.production_order_id = po.id) as raw_wastage,
                    (SELECT COALESCE(SUM(pw3.fg_wastage_qty),0)
                     FROM production_wastage pw3
                     WHERE pw3.production_order_id = po.id AND pw3.fg_wastage_qty IS NOT NULL) as fg_wastage,
                    (SELECT COALESCE(SUM(wp2.total_cost),0)
                     FROM work_in_progress wp2
                     WHERE wp2.production_order_id = po.id) as material_cost,
                    (SELECT COALESCE(SUM(pe2.total_amount),0)
                     FROM production_expenses pe2
                     WHERE pe2.production_order_id = po.id AND pe2.status='Posted') as overhead_cost
                FROM production_orders po
                JOIN products p ON po.product_id = p.id
                JOIN branches b ON po.branch_id = b.id
                WHERE po.tenant_id = ? $f
                ORDER BY po.created_at DESC
            ");
            $stmt->execute($p);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        // ── Material Usage ──────────────────────────────────────────────────
        case 'material_usage': {
            $p = [$tenant_id]; $f = buildPoFilter($filters, $p);
            $stmt = $pdo->prepare("
                SELECT
                    p.id as material_id, p.code as material_code, p.name as material_name,
                    COALESCE(u.uom_name,'N/A') as uom_name,
                    SUM(pom.required_qty) as required_qty,
                    COALESCE(SUM(iss.issued_qty),0) as issued_qty,
                    COALESCE(SUM(wst.wasted_qty),0) as wasted_qty,
                    COUNT(DISTINCT po.id) as po_count
                FROM production_order_materials pom
                JOIN production_orders po ON pom.production_order_id = po.id
                JOIN products p ON pom.material_id = p.id
                LEFT JOIN uom u ON pom.uom_id = u.id
                LEFT JOIN (
                    SELECT wpi.material_id, wp.production_order_id, SUM(wpi.issue_qty) as issued_qty
                    FROM work_in_progress_items wpi
                    JOIN work_in_progress wp ON wpi.work_in_progress_id = wp.id
                    GROUP BY wpi.material_id, wp.production_order_id
                ) iss ON iss.material_id = pom.material_id AND iss.production_order_id = po.id
                LEFT JOIN (
                    SELECT pwi.material_id, pw.production_order_id, SUM(pwi.wastage_qty) as wasted_qty
                    FROM production_wastage_items pwi
                    JOIN production_wastage pw ON pwi.wastage_id = pw.id
                    GROUP BY pwi.material_id, pw.production_order_id
                ) wst ON wst.material_id = pom.material_id AND wst.production_order_id = po.id
                WHERE po.tenant_id = ? $f
                GROUP BY p.id, p.code, p.name, u.uom_name
                ORDER BY required_qty DESC
            ");
            $stmt->execute($p);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        // ── Finished Goods ──────────────────────────────────────────────────
        case 'finished_goods': {
            $p = [$tenant_id]; $f = buildPoFilter($filters, $p);
            $stmt = $pdo->prepare("
                SELECT
                    p.id as product_id, p.code as product_code, p.name as product_name,
                    COUNT(DISTINCT po.id) as order_count,
                    SUM(po.order_qty) as total_ordered,
                    COALESCE(SUM(prod.completed_qty),0) as total_produced,
                    COALESCE(SUM(fgw.fg_wastage_qty),0) as fg_wastage_qty
                FROM production_orders po
                JOIN products p ON po.product_id = p.id
                LEFT JOIN (
                    SELECT pc.production_order_id, SUM(cp.completed_qty) as completed_qty
                    FROM production_completions pc
                    JOIN completed_products cp ON cp.production_completion_id = pc.id
                    GROUP BY pc.production_order_id
                ) prod ON prod.production_order_id = po.id
                LEFT JOIN (
                    SELECT production_order_id, SUM(fg_wastage_qty) as fg_wastage_qty
                    FROM production_wastage WHERE fg_wastage_qty IS NOT NULL
                    GROUP BY production_order_id
                ) fgw ON fgw.production_order_id = po.id
                WHERE po.tenant_id = ? $f
                GROUP BY p.id, p.code, p.name
                ORDER BY total_ordered DESC
            ");
            $stmt->execute($p);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        // ── Cost Breakdown ──────────────────────────────────────────────────
        case 'cost_breakdown': {
            $p = [$tenant_id]; $f = buildPoFilter($filters, $p);
            $stmt = $pdo->prepare("
                SELECT
                    po.id, po.order_no, p.name as product_name, b.branch_name,
                    COALESCE(mat.material_cost,0) as material_cost,
                    COALESCE(ovh.overhead_cost,0) as overhead_cost,
                    COALESCE(prod.units_produced,0) as units_produced
                FROM production_orders po
                JOIN products p ON po.product_id = p.id
                JOIN branches b ON po.branch_id = b.id
                LEFT JOIN (
                    SELECT production_order_id, SUM(total_cost) as material_cost
                    FROM work_in_progress GROUP BY production_order_id
                ) mat ON mat.production_order_id = po.id
                LEFT JOIN (
                    SELECT production_order_id, SUM(total_amount) as overhead_cost
                    FROM production_expenses WHERE status='Posted' GROUP BY production_order_id
                ) ovh ON ovh.production_order_id = po.id
                LEFT JOIN (
                    SELECT pc.production_order_id, SUM(cp.completed_qty) as units_produced
                    FROM production_completions pc
                    JOIN completed_products cp ON cp.production_completion_id = pc.id
                    GROUP BY pc.production_order_id
                ) prod ON prod.production_order_id = po.id
                WHERE po.tenant_id = ? $f
                HAVING (material_cost + overhead_cost) > 0
                ORDER BY (material_cost + overhead_cost) DESC
            ");
            $stmt->execute($p);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        // ── PO Drill-down ───────────────────────────────────────────────────
        case 'po_detail': {
            $poId = (int)($_GET['id'] ?? 0);
            if (!$poId) { echo json_encode(['success'=>false,'message'=>'Missing PO ID']); break; }

            $stmt = $pdo->prepare("
                SELECT po.*, p.name as product_name, p.code as product_code,
                       b.branch_name, m.machine_name, bom.bom_code
                FROM production_orders po
                JOIN products p ON po.product_id = p.id
                JOIN branches b ON po.branch_id = b.id
                LEFT JOIN machines m ON po.machine_id = m.id
                LEFT JOIN bill_of_materials bom ON po.bom_id = bom.id
                WHERE po.id = ? AND po.tenant_id = ?
            ");
            $stmt->execute([$poId, $tenant_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) { echo json_encode(['success'=>false,'message'=>'Order not found']); break; }

            $stmt = $pdo->prepare("
                SELECT pc.completion_no, pc.complete_date, SUM(cp.completed_qty) as completed_qty, pc.total_cost
                FROM production_completions pc
                JOIN completed_products cp ON cp.production_completion_id = pc.id
                WHERE pc.production_order_id = ?
                GROUP BY pc.id ORDER BY pc.complete_date
            ");
            $stmt->execute([$poId]);
            $completions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                SELECT p.code as material_code, p.name as material_name,
                       COALESCE(u.uom_name,'N/A') as uom_name, pom.required_qty,
                       COALESCE((
                           SELECT SUM(wpi.issue_qty)
                           FROM work_in_progress_items wpi
                           JOIN work_in_progress wp ON wpi.work_in_progress_id = wp.id
                           WHERE wp.production_order_id = ? AND wpi.material_id = pom.material_id
                       ),0) as issued_qty,
                       COALESCE((
                           SELECT SUM(pwi.wastage_qty)
                           FROM production_wastage_items pwi
                           JOIN production_wastage pw ON pwi.wastage_id = pw.id
                           WHERE pw.production_order_id = ? AND pwi.material_id = pom.material_id
                       ),0) as wasted_qty
                FROM production_order_materials pom
                JOIN products p ON pom.material_id = p.id
                LEFT JOIN uom u ON pom.uom_id = u.id
                WHERE pom.production_order_id = ?
            ");
            $stmt->execute([$poId, $poId, $poId]);
            $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                SELECT pw.wastage_no, pw.wastage_date, pw.wastage_type,
                       pw.fg_wastage_qty, pw.fg_wastage_input, pw.fg_wastage_type,
                       COALESCE((SELECT SUM(pwi.wastage_qty) FROM production_wastage_items pwi WHERE pwi.wastage_id=pw.id),0) as raw_wastage_total
                FROM production_wastage pw
                WHERE pw.production_order_id = ? AND pw.tenant_id = ?
                ORDER BY pw.wastage_date
            ");
            $stmt->execute([$poId, $tenant_id]);
            $wastage = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $matCost = $pdo->prepare("SELECT COALESCE(SUM(total_cost),0) FROM work_in_progress WHERE production_order_id=?");
            $matCost->execute([$poId]);
            $mc = (float)$matCost->fetchColumn();

            $ovhCost = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM production_expenses WHERE production_order_id=? AND status='Posted'");
            $ovhCost->execute([$poId]);
            $oc = (float)$ovhCost->fetchColumn();

            $totalProduced = array_sum(array_column($completions, 'completed_qty'));

            echo json_encode(['success' => true, 'data' => [
                'order'       => $order,
                'completions' => $completions,
                'materials'   => $materials,
                'wastage'     => $wastage,
                'costs'       => [
                    'material_cost'  => $mc,
                    'overhead_cost'  => $oc,
                    'total_cost'     => $mc + $oc,
                    'units_produced' => (float)$totalProduced,
                    'cost_per_unit'  => $totalProduced > 0 ? ($mc + $oc) / $totalProduced : 0,
                ],
            ]]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
