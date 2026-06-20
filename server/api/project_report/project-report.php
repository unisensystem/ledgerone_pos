<?php
require_once '../../../includes/connection.php';
session_start();
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { 
    http_response_code(401); 
    echo json_encode(['success' => false, 'message' => 'Unauthorized - No tenant_id in session']); 
    exit; 
}

$project_id = $_GET['project_id'] ?? null;
$date_from  = $_GET['date_from']  ?? date('Y-01-01');
$date_to    = $_GET['date_to']    ?? date('Y-m-d');

try {

    // ── Projects dropdown list ───────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT p.id, p.project_code, p.project_name, p.start_date, p.end_date, p.status,
               p.description, co.company_name, ci.city_name,
               pm.full_name AS manager_name, tl.full_name AS leader_name,
               (SELECT COUNT(*) FROM project_team_members ptm WHERE ptm.project_id = p.id AND ptm.tenant_id = p.tenant_id) AS team_count
        FROM projects p
        LEFT JOIN companies co  ON co.id = p.company_id
        LEFT JOIN cities ci     ON ci.id = p.city_id
        LEFT JOIN employees pm  ON pm.id = p.project_manager_id
        LEFT JOIN employees tl  ON tl.id = p.team_leader_id
        WHERE p.tenant_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$tenant_id]);
    $projects = $stmt->fetchAll();

    if (!$project_id) {
        echo json_encode(['success' => true, 'projects' => $projects]);
        exit;
    }

    // ── Active project info ──────────────────────────────────────────────────
    $proj = null;
    foreach ($projects as $p) { if ($p['id'] == $project_id) { $proj = $p; break; } }

    // ── Team members ─────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT e.employee_id AS employee_code, e.full_name, p.position_code AS designation, e.phone_number AS phone, ptm.added_at
        FROM project_team_members ptm
        JOIN employees e ON e.id = ptm.employee_id
        LEFT JOIN positions p ON p.id = e.position_id
        WHERE ptm.project_id = ? AND ptm.tenant_id = ?
    ");
    $stmt->execute([$project_id, $tenant_id]);
    $team = $stmt->fetchAll();

    // ── Customers linked ─────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT customer_code, customer_name, primary_phone, current_balance, status
        FROM customers WHERE project_id = ? AND tenant_id = ?
    ");
    $stmt->execute([$project_id, $tenant_id]);
    $customers = $stmt->fetchAll();

    // ── Suppliers linked ─────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT supplier_code, supplier_name, primary_phone, current_balance, status
        FROM suppliers WHERE project_id = ? AND tenant_id = ?
    ");
    $stmt->execute([$project_id, $tenant_id]);
    $suppliers = $stmt->fetchAll();

    // ── Quotations ───────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT q.quotation_number, q.quotation_date, q.valid_till, q.grand_total, q.status,
               q.subtotal, q.total_sales_tax, q.party_type,
               c.customer_name, e.full_name AS salesman_name,
               COUNT(qi.id) AS item_count
        FROM quotations q
        LEFT JOIN customers c ON c.id = q.customer_id
        LEFT JOIN employees e ON e.id = q.salesman_id
        LEFT JOIN quotation_items qi ON qi.quotation_id = q.id
        WHERE q.project_id = ? AND q.tenant_id = ?
          AND (q.is_deleted IS NULL OR q.is_deleted = 0)
          AND q.quotation_date BETWEEN ? AND ?
        GROUP BY q.id
        ORDER BY q.id DESC
    ");
    $stmt->execute([$project_id, $tenant_id, $date_from, $date_to]);
    $quotations = $stmt->fetchAll();

    // ── Sale Invoices ────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT si.id, si.bill_no, si.sale_date, si.total_bill, si.net_amount,
               si.total_discount_amount, si.status,
               c.customer_name, e.full_name AS officer_name,
               COUNT(sii.id) AS item_count,
               SUM(sii.quantity) AS total_qty
        FROM sale_invoice si
        LEFT JOIN customers c  ON c.id = si.customer_id
        LEFT JOIN employees e  ON e.id = si.sale_officer_id
        LEFT JOIN sale_invoice_items sii ON sii.sale_invoice_id = si.id
        WHERE si.project_id = ? AND si.tenant_id = ?
          AND si.sale_date BETWEEN ? AND ?
        GROUP BY si.id
        ORDER BY si.id DESC
    ");
    $stmt->execute([$project_id, $tenant_id, $date_from, $date_to]);
    $sale_invoices = $stmt->fetchAll();

    // ── Sale Returns ─────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT sr.bill_no, sr.sale_date, sr.net_amount, sr.total_bill,
               sr.total_discount_amount, c.customer_name
        FROM sale_return sr
        LEFT JOIN customers c ON c.id = sr.customer_id
        WHERE sr.project_id = ? AND sr.tenant_id = ?
          AND sr.sale_date BETWEEN ? AND ?
        ORDER BY sr.id DESC
    ");
    $stmt->execute([$project_id, $tenant_id, $date_from, $date_to]);
    $sale_returns = $stmt->fetchAll();

    // ── Purchase Invoices ────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT pi.id, pi.bill_no, pi.purchase_date, pi.total_bill, pi.net_amount,
               s.supplier_name,
               COUNT(pii.id) AS item_count,
               SUM(pii.quantity) AS total_qty
        FROM purchase_invoice pi
        LEFT JOIN suppliers s ON s.id = pi.supplier_id
        LEFT JOIN purchase_invoice_items pii ON pii.purchase_invoice_id = pi.id
        WHERE pi.project_id = ? AND pi.tenant_id = ?
          AND pi.purchase_date BETWEEN ? AND ?
        GROUP BY pi.id
        ORDER BY pi.id DESC
    ");
    $stmt->execute([$project_id, $tenant_id, $date_from, $date_to]);
    $purchase_invoices = $stmt->fetchAll();

    // ── Purchase Returns ─────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT pr.bill_no, pr.purchase_date, pr.net_amount, pr.total_bill,
               s.supplier_name
        FROM purchase_return pr
        LEFT JOIN suppliers s ON s.id = pr.supplier_id
        WHERE pr.project_id = ? AND pr.tenant_id = ?
          AND pr.purchase_date BETWEEN ? AND ?
        ORDER BY pr.id DESC
    ");
    $stmt->execute([$project_id, $tenant_id, $date_from, $date_to]);
    $purchase_returns = $stmt->fetchAll();

    // ── Receive Vouchers (payments received from customers) ──────────────────
    $stmt = $pdo->prepare("
        SELECT rv.voucher_number, rv.voucher_date, rv.amount, rv.description,
               c.customer_name
        FROM receive_voucher rv
        LEFT JOIN customers c ON c.id = rv.customer_id
        WHERE rv.project_id = ? AND rv.tenant_id = ?
          AND rv.voucher_date BETWEEN ? AND ?
        ORDER BY rv.id DESC
    ");
    $stmt->execute([$project_id, $tenant_id, $date_from, $date_to]);
    $receive_vouchers = $stmt->fetchAll();

    // ── Payment Vouchers (payments made to suppliers) ────────────────────────
    $stmt = $pdo->prepare("
        SELECT pv.voucher_number, pv.voucher_date, pv.amount, pv.description,
               s.supplier_name
        FROM payment_voucher pv
        LEFT JOIN suppliers s ON s.id = pv.supplier_id
        WHERE pv.project_id = ? AND pv.tenant_id = ?
          AND pv.voucher_date BETWEEN ? AND ?
        ORDER BY pv.id DESC
    ");
    $stmt->execute([$project_id, $tenant_id, $date_from, $date_to]);
    $payment_vouchers = $stmt->fetchAll();

    // ── Expense Vouchers ─────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT voucher_no, date, description, total_amount
        FROM expense_voucher
        WHERE project_id = ? AND tenant_id = ?
          AND date BETWEEN ? AND ?
        ORDER BY id DESC
    ");
    $stmt->execute([$project_id, $tenant_id, $date_from, $date_to]);
    $expenses = $stmt->fetchAll();

    // ── Monthly Sales Trend ──────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(sale_date,'%Y-%m') AS mon,
               SUM(net_amount) AS sales,
               COUNT(*) AS invoices
        FROM sale_invoice
        WHERE project_id = ? AND tenant_id = ?
          AND sale_date BETWEEN ? AND ?
        GROUP BY mon ORDER BY mon
    ");
    $stmt->execute([$project_id, $tenant_id, $date_from, $date_to]);
    $trend = $stmt->fetchAll();

    // ── KPI Summary ──────────────────────────────────────────────────────────
    $total_sales      = array_sum(array_column($sale_invoices,    'net_amount'));
    $total_purchase   = array_sum(array_column($purchase_invoices,'net_amount'));
    $total_sale_ret   = array_sum(array_column($sale_returns,     'net_amount'));
    $total_purch_ret  = array_sum(array_column($purchase_returns, 'net_amount'));
    $total_received   = array_sum(array_column($receive_vouchers, 'amount'));
    $total_paid       = array_sum(array_column($payment_vouchers, 'amount'));
    $total_expenses   = array_sum(array_column($expenses,         'total_amount'));
    $net_profit       = ($total_sales - $total_sale_ret) - ($total_purchase - $total_purch_ret) - $total_expenses;

    echo json_encode([
        'success'          => true,
        'projects'         => $projects,
        'project'          => $proj,
        'team'             => $team,
        'customers'        => $customers,
        'suppliers'        => $suppliers,
        'quotations'       => $quotations,
        'sale_invoices'    => $sale_invoices,
        'sale_returns'     => $sale_returns,
        'purchase_invoices'=> $purchase_invoices,
        'purchase_returns' => $purchase_returns,
        'receive_vouchers' => $receive_vouchers,
        'payment_vouchers' => $payment_vouchers,
        'expenses'         => $expenses,
        'trend'            => $trend,
        'summary' => [
            'total_sales'      => $total_sales,
            'total_purchase'   => $total_purchase,
            'total_sale_ret'   => $total_sale_ret,
            'total_purch_ret'  => $total_purch_ret,
            'total_received'   => $total_received,
            'total_paid'       => $total_paid,
            'total_expenses'   => $total_expenses,
            'net_profit'       => $net_profit,
            'sale_count'       => count($sale_invoices),
            'purchase_count'   => count($purchase_invoices),
            'quotation_count'  => count($quotations),
            'customer_count'   => count($customers),
            'supplier_count'   => count($suppliers),
            'expense_count'    => count($expenses),
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
