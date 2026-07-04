<?php
require_once '../../../../includes/connection.php';
session_start();
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$container_id = $_GET['container_id'] ?? null;
$date_from = $_GET['date_from'] ?? date('Y-01-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

try {
    // Get all containers
    $stmt = $pdo->prepare("SELECT id, container_code, container_name, is_active, created_at FROM containers WHERE tenant_id = ? ORDER BY container_code");
    $stmt->execute([$tenant_id]);
    $containers = $stmt->fetchAll();

    if (!$container_id) {
        echo json_encode(['success' => true, 'containers' => $containers]);
        exit;
    }

    // Get container info
    $stmt = $pdo->prepare("SELECT * FROM containers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$container_id, $tenant_id]);
    $container = $stmt->fetch();

    // Timeline Events - Combined from all sources
    $timeline_events = [];

    // Inbound events
    $stmt = $pdo->prepare("
        SELECT ig.id, ig.date AS event_date, 'Inward' AS event_type, ig.gatepass_code AS document_number,
               COALESCE(SUM(igi.quantity), 0) AS quantity, NULL AS amount, 'completed' AS status,
               s.supplier_name AS from_location, COALESCE(b.branch_name, '—') AS to_location
        FROM inward_gatepass ig
        LEFT JOIN inward_gatepass_items igi ON igi.gatepass_id = ig.id
        LEFT JOIN suppliers s ON s.id = ig.supplier_id
        LEFT JOIN branches b ON b.id = ig.branch_id
        WHERE ig.container_id = ? AND ig.tenant_id = ? AND ig.date BETWEEN ? AND ?
        GROUP BY ig.id, ig.date, ig.gatepass_code, s.supplier_name, b.branch_name
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $inbound_events = $stmt->fetchAll();
    $timeline_events = array_merge($timeline_events, $inbound_events);

    // Outbound events
    $stmt = $pdo->prepare("
        SELECT og.id, og.date AS event_date, 'Outward' AS event_type, og.gatepass_code AS document_number,
               COALESCE(SUM(ogi.quantity), 0) AS quantity, NULL AS amount, 'completed' AS status,
               COALESCE(b.branch_name, '—') AS from_location, COALESCE(c.customer_name, '—') AS to_location
        FROM outward_gatepass og
        LEFT JOIN outward_gatepass_items ogi ON ogi.gatepass_id = og.id
        LEFT JOIN customers c ON c.id = og.supplier_id
        LEFT JOIN branches b ON b.id = og.branch_id
        WHERE og.container_id = ? AND og.tenant_id = ? AND og.date BETWEEN ? AND ?
        GROUP BY og.id, og.date, og.gatepass_code, b.branch_name, c.customer_name
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $outbound_events = $stmt->fetchAll();
    $timeline_events = array_merge($timeline_events, $outbound_events);

    // Transfer events
    $stmt = $pdo->prepare("
        SELECT st.id, st.date AS event_date, 'Transfer' AS event_type, st.transfer_code AS document_number,
               COALESCE(SUM(sti.qty), 0) AS quantity, SUM(sti.stock_value) AS amount, 'completed' AS status,
               COALESCE(b1.branch_name, '—') AS from_location, 
               COALESCE(b2.branch_name, '—') AS to_location
        FROM stock_transfer st
        LEFT JOIN stock_transfer_items sti ON sti.transfer_id = st.id
        LEFT JOIN branches b1 ON b1.id = st.from_location_id
        LEFT JOIN branches b2 ON b2.id = st.to_location_id
        WHERE st.container_id = ? AND st.tenant_id = ? AND st.date BETWEEN ? AND ?
        GROUP BY st.id, st.date, st.transfer_code, b1.branch_name, b2.branch_name
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $transfer_events = $stmt->fetchAll();
    $timeline_events = array_merge($timeline_events, $transfer_events);

    // Sales events
    $stmt = $pdo->prepare("
        SELECT si.id, si.sale_date AS event_date, 'Sale' AS event_type, si.bill_no AS document_number,
               COALESCE(SUM(sii.quantity), 0) AS quantity, si.net_amount AS amount, 'Completed' AS status,
               'Warehouse' AS from_location, c.customer_name AS to_location
        FROM sale_invoice si
        LEFT JOIN sale_invoice_items sii ON sii.sale_invoice_id = si.id
        LEFT JOIN customers c ON c.id = si.customer_id
        WHERE si.container_id = ? AND si.tenant_id = ? AND si.sale_date BETWEEN ? AND ?
        GROUP BY si.id, si.sale_date, si.bill_no, si.net_amount, c.customer_name
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $sale_events = $stmt->fetchAll();
    $timeline_events = array_merge($timeline_events, $sale_events);

    // Purchase events
    $stmt = $pdo->prepare("
        SELECT pi.id, pi.purchase_date AS event_date, 'Purchase' AS event_type, pi.bill_no AS document_number,
               COALESCE(SUM(pii.quantity), 0) AS quantity, pi.net_amount AS amount, 'Completed' AS status,
               s.supplier_name AS from_location, 'Warehouse' AS to_location
        FROM purchase_invoice pi
        LEFT JOIN purchase_invoice_items pii ON pii.purchase_invoice_id = pi.id
        LEFT JOIN suppliers s ON s.id = pi.supplier_id
        WHERE pi.container_id = ? AND pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?
        GROUP BY pi.id, pi.purchase_date, pi.bill_no, pi.net_amount, s.supplier_name
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $purchase_events = $stmt->fetchAll();
    $timeline_events = array_merge($timeline_events, $purchase_events);

    // Inbound gatepasses
    $stmt = $pdo->prepare("
        SELECT ig.id, ig.gatepass_code, ig.date, s.supplier_name, COALESCE(b.branch_name, '—') AS branch_name,
               COUNT(DISTINCT igi.product_id) AS item_count, COALESCE(SUM(igi.quantity), 0) AS total_qty,
<<<<<<< Updated upstream
               'completed' AS status
=======
               'completed' AS status, GROUP_CONCAT(DISTINCT igi.engine_no SEPARATOR ', ') AS engine_nos,
               GROUP_CONCAT(DISTINCT igi.chassis_no SEPARATOR ', ') AS chassis_nos
>>>>>>> Stashed changes
        FROM inward_gatepass ig
        LEFT JOIN inward_gatepass_items igi ON igi.gatepass_id = ig.id
        LEFT JOIN suppliers s ON s.id = ig.supplier_id
        LEFT JOIN branches b ON b.id = ig.branch_id
        WHERE ig.container_id = ? AND ig.tenant_id = ? AND ig.date BETWEEN ? AND ?
        GROUP BY ig.id, ig.gatepass_code, ig.date, s.supplier_name, b.branch_name
        ORDER BY ig.date DESC
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $inbound_gatepass = $stmt->fetchAll();

    // Outbound gatepasses
    $stmt = $pdo->prepare("
        SELECT og.id, og.gatepass_code, og.date, c.customer_name, COALESCE(b.branch_name, '—') AS branch_name,
               COUNT(DISTINCT ogi.product_id) AS item_count, COALESCE(SUM(ogi.quantity), 0) AS total_qty,
<<<<<<< Updated upstream
               'completed' AS status
=======
               'completed' AS status, GROUP_CONCAT(DISTINCT ogi.engine_no SEPARATOR ', ') AS engine_nos,
               GROUP_CONCAT(DISTINCT ogi.chassis_no SEPARATOR ', ') AS chassis_nos
>>>>>>> Stashed changes
        FROM outward_gatepass og
        LEFT JOIN outward_gatepass_items ogi ON ogi.gatepass_id = og.id
        LEFT JOIN customers c ON c.id = og.supplier_id
        LEFT JOIN branches b ON b.id = og.branch_id
        WHERE og.container_id = ? AND og.tenant_id = ? AND og.date BETWEEN ? AND ?
        GROUP BY og.id, og.gatepass_code, og.date, c.customer_name, b.branch_name
        ORDER BY og.date DESC
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $outbound_gatepass = $stmt->fetchAll();

    // Stock transfers
    $stmt = $pdo->prepare("
        SELECT st.id, st.transfer_code, st.date, 
               COALESCE(b1.branch_name, '—') AS from_location, 
               COALESCE(b2.branch_name, '—') AS to_location,
               COUNT(DISTINCT sti.product_id) AS item_count, COALESCE(SUM(sti.qty), 0) AS total_items,
<<<<<<< Updated upstream
               SUM(sti.stock_value) AS total_amount
=======
               SUM(sti.stock_value) AS total_amount, GROUP_CONCAT(DISTINCT sti.engine_no SEPARATOR ', ') AS engine_nos,
               GROUP_CONCAT(DISTINCT sti.chassis_no SEPARATOR ', ') AS chassis_nos
>>>>>>> Stashed changes
        FROM stock_transfer st
        LEFT JOIN stock_transfer_items sti ON sti.transfer_id = st.id
        LEFT JOIN branches b1 ON b1.id = st.from_location_id
        LEFT JOIN branches b2 ON b2.id = st.to_location_id
        WHERE st.container_id = ? AND st.tenant_id = ? AND st.date BETWEEN ? AND ?
        GROUP BY st.id, st.transfer_code, st.date, b1.branch_name, b2.branch_name
        ORDER BY st.date DESC
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $stock_transfers = $stmt->fetchAll();

    // Sale invoices
    $stmt = $pdo->prepare("
        SELECT si.id, si.bill_no, si.sale_date, c.customer_name,
               COUNT(DISTINCT sii.product_id) AS item_count, COALESCE(SUM(sii.quantity), 0) AS total_qty,
<<<<<<< Updated upstream
               si.net_amount, si.status
=======
               si.net_amount, si.status, GROUP_CONCAT(DISTINCT sii.engine_no SEPARATOR ', ') AS engine_nos,
               GROUP_CONCAT(DISTINCT sii.chassis_no SEPARATOR ', ') AS chassis_nos
>>>>>>> Stashed changes
        FROM sale_invoice si
        LEFT JOIN sale_invoice_items sii ON sii.sale_invoice_id = si.id
        LEFT JOIN customers c ON c.id = si.customer_id
        WHERE si.container_id = ? AND si.tenant_id = ? AND si.sale_date BETWEEN ? AND ?
        GROUP BY si.id, si.bill_no, si.sale_date, c.customer_name, si.net_amount, si.status
        ORDER BY si.sale_date DESC
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $sale_invoices = $stmt->fetchAll();

    // Purchase invoices
    $stmt = $pdo->prepare("
        SELECT pi.id, pi.bill_no, pi.purchase_date, s.supplier_name,
               COUNT(DISTINCT pii.product_id) AS item_count, COALESCE(SUM(pii.quantity), 0) AS total_qty,
<<<<<<< Updated upstream
               pi.net_amount, pi.status
=======
               pi.net_amount, pi.status, GROUP_CONCAT(DISTINCT pii.engine_no SEPARATOR ', ') AS engine_nos,
               GROUP_CONCAT(DISTINCT pii.chassis_no SEPARATOR ', ') AS chassis_nos
>>>>>>> Stashed changes
        FROM purchase_invoice pi
        LEFT JOIN purchase_invoice_items pii ON pii.purchase_invoice_id = pi.id
        LEFT JOIN suppliers s ON s.id = pi.supplier_id
        WHERE pi.container_id = ? AND pi.tenant_id = ? AND pi.purchase_date BETWEEN ? AND ?
        GROUP BY pi.id, pi.bill_no, pi.purchase_date, s.supplier_name, pi.net_amount, pi.status
        ORDER BY pi.purchase_date DESC
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $purchase_invoices = $stmt->fetchAll();

    // Receive vouchers
    $stmt = $pdo->prepare("
        SELECT rv.id, rv.voucher_number, rv.voucher_date, c.customer_name, rv.description, rv.amount
        FROM receive_voucher rv
        LEFT JOIN customers c ON c.id = rv.customer_id
        WHERE rv.container_id = ? AND rv.tenant_id = ? AND rv.voucher_date BETWEEN ? AND ?
        ORDER BY rv.voucher_date DESC
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $receive_vouchers = $stmt->fetchAll();

    // Payment vouchers
    $stmt = $pdo->prepare("
        SELECT pv.id, pv.voucher_number, pv.voucher_date, s.supplier_name, pv.description, pv.amount
        FROM payment_voucher pv
        LEFT JOIN suppliers s ON s.id = pv.supplier_id
        WHERE pv.container_id = ? AND pv.tenant_id = ? AND pv.voucher_date BETWEEN ? AND ?
        ORDER BY pv.voucher_date DESC
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $payment_vouchers = $stmt->fetchAll();

    // Expenses
    $stmt = $pdo->prepare("
        SELECT ev.id, ev.voucher_no, ev.date, ev.description, ev.total_amount
        FROM expense_voucher ev
        WHERE ev.container_id = ? AND ev.tenant_id = ? AND ev.date BETWEEN ? AND ?
        ORDER BY ev.date DESC
    ");
    $stmt->execute([$container_id, $tenant_id, $date_from, $date_to]);
    $expenses = $stmt->fetchAll();

    // Timeline for chart
    $stmt = $pdo->prepare("
        SELECT 'Inward' AS event_type, COUNT(*) AS count FROM inward_gatepass WHERE container_id = ? AND tenant_id = ? AND date BETWEEN ? AND ?
        UNION ALL
        SELECT 'Outward' AS event_type, COUNT(*) AS count FROM outward_gatepass WHERE container_id = ? AND tenant_id = ? AND date BETWEEN ? AND ?
        UNION ALL
        SELECT 'Transfer' AS event_type, COUNT(*) AS count FROM stock_transfer WHERE container_id = ? AND tenant_id = ? AND date BETWEEN ? AND ?
        UNION ALL
        SELECT 'Sales' AS event_type, COUNT(*) AS count FROM sale_invoice WHERE container_id = ? AND tenant_id = ? AND sale_date BETWEEN ? AND ?
        UNION ALL
        SELECT 'Purchases' AS event_type, COUNT(*) AS count FROM purchase_invoice WHERE container_id = ? AND tenant_id = ? AND purchase_date BETWEEN ? AND ?
    ");
    $stmt->execute([
        $container_id, $tenant_id, $date_from, $date_to,
        $container_id, $tenant_id, $date_from, $date_to,
        $container_id, $tenant_id, $date_from, $date_to,
        $container_id, $tenant_id, $date_from, $date_to,
        $container_id, $tenant_id, $date_from, $date_to
    ]);
    $timeline = $stmt->fetchAll();

    // Calculate totals
    $total_inbound_qty = array_sum(array_column($inbound_gatepass, 'total_qty'));
    $total_outbound_qty = array_sum(array_column($outbound_gatepass, 'total_qty'));
    $total_transfers = count($stock_transfers);
    $total_sales = array_sum(array_column($sale_invoices, 'net_amount'));
    $total_purchases = array_sum(array_column($purchase_invoices, 'net_amount'));
    $total_expenses = array_sum(array_column($expenses, 'total_amount'));
    $net_pl = $total_sales - $total_purchases - $total_expenses;

    echo json_encode([
        'success' => true,
        'containers' => $containers,
        'container' => $container,
        'timeline_events' => $timeline_events,
        'inbound_gatepass' => $inbound_gatepass,
        'outbound_gatepass' => $outbound_gatepass,
        'stock_transfers' => $stock_transfers,
        'sale_invoices' => $sale_invoices,
        'purchase_invoices' => $purchase_invoices,
        'receive_vouchers' => $receive_vouchers,
        'payment_vouchers' => $payment_vouchers,
        'expenses' => $expenses,
        'timeline' => $timeline,
        'summary' => [
            'total_inbound_qty' => $total_inbound_qty,
            'total_outbound_qty' => $total_outbound_qty,
            'total_transfers' => $total_transfers,
            'total_sales' => $total_sales,
            'total_purchases' => $total_purchases,
            'total_expenses' => $total_expenses,
            'net_pl' => $net_pl,
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
