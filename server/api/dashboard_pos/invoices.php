<?php
// server/api/dashboard_pos/invoices.php
//   GET  ?date_from=&date_to=&q=   -> bills list (default: aaj ke)
//   POST {"id":123}                -> bill delete (stock + accounts reverse)
require_once '../../../includes/connection.php';
require_once '../../../includes/posting_lock.php';
require_once '../../../includes/activity-logger.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $from = $_GET['date_from'] ?? date('Y-m-d');
        $to = $_GET['date_to'] ?? date('Y-m-d');
        $q = trim($_GET['q'] ?? '');
        $sql = "
            SELECT si.id, si.bill_no, si.sale_date, si.net_amount, si.amount_paid, si.status, si.created_by, si.payment_method,
                   c.customer_name, si.customer_phone,
                   (SELECT COUNT(*) FROM sale_invoice_items sii WHERE sii.sale_invoice_id = si.id) AS item_count
            FROM sale_invoice si
            LEFT JOIN customers c ON c.id = si.customer_id
            WHERE si.tenant_id = ? AND si.sale_date BETWEEN ? AND ?";
        $args = [$tenant_id, $from, $to];
        if ($q !== '') { $sql .= " AND (si.bill_no LIKE ? OR c.customer_name LIKE ?)"; $args[] = "%$q%"; $args[] = "%$q%"; }
        $sql .= " ORDER BY si.id DESC LIMIT 100";
        $s = $pdo->prepare($sql);
        $s->execute($args);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) $r['can_delete'] = ((int) $r['created_by'] === (int) $user_id);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit; }

    $in = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($in['id'] ?? 0);
    if (!$id) throw new Exception('Invoice id required');

    $pdo->beginTransaction();
    $s = $pdo->prepare("SELECT id, bill_no, sale_date, net_amount, created_by FROM sale_invoice WHERE id = ? AND tenant_id = ? FOR UPDATE");
    $s->execute([$id, $tenant_id]);
    $inv = $s->fetch(PDO::FETCH_ASSOC);
    if (!$inv) throw new Exception('Invoice not found');
    // Cashier safety: sirf wohi user delete kar sakta hai jis ne bill banaya. Admin/role rule chahiye to yahan hasPermission() lagayein.
    if ((int) $inv['created_by'] !== (int) $user_id) throw new Exception('Sirf bill banane wala user hi delete kar sakta hai');

    $lock = checkPostingDateAllowed($pdo, $tenant_id, 'sale_invoice', $inv['sale_date']);
    if (!$lock['allowed']) throw new Exception($lock['message']);

    // Is bill ke receive vouchers (pos-add.php inhe bill_no se jorta hai)
    $s = $pdo->prepare("SELECT id FROM receive_voucher WHERE tenant_id = ? AND bill_no = ?");
    $s->execute([$tenant_id, $inv['bill_no']]);
    $rvIds = $s->fetchAll(PDO::FETCH_COLUMN);
    if ($rvIds) {
        $in_ = implode(',', array_fill(0, count($rvIds), '?'));
        $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND reference_table = 'receive_voucher' AND reference_id IN ($in_)")
            ->execute(array_merge([$tenant_id], $rvIds));
        $pdo->prepare("DELETE FROM receive_voucher WHERE tenant_id = ? AND id IN ($in_)")
            ->execute(array_merge([$tenant_id], $rvIds));
    }
    $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND reference_table = 'sale_invoice' AND reference_id = ?")->execute([$tenant_id, $id]);
    $pdo->prepare("DELETE FROM stock_ledger WHERE tenant_id = ? AND reference_table = 'sale_invoice' AND reference_id = ?")->execute([$tenant_id, $id]);
    $pdo->prepare("DELETE FROM sale_invoice_items WHERE tenant_id = ? AND sale_invoice_id = ?")->execute([$tenant_id, $id]);
    $pdo->prepare("DELETE FROM sale_invoice WHERE tenant_id = ? AND id = ?")->execute([$tenant_id, $id]);

    logActivity($pdo, $tenant_id, $user_id, 'delete', 'Sale Invoice', $id, $inv['bill_no'],
        "Deleted sale invoice \"{$inv['bill_no']}\" from dashboard POS (net " . number_format($inv['net_amount'], 2) . ")", $inv, null);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Bill deleted']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}