<?php
// ============================================================
//  quotation-list.php  ·  Dynamic List + Convert to SO
//  Place at: /your-project/modules/sales/quotation-list.php
//  Requires: ../../../../includes/connection.php  ($pdo)
//            ../../../../includes/dashboard.php
// ============================================================
require_once '../../../../includes/connection.php';

if (session_status() == PHP_SESSION_NONE) session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit;
}
$initialSearch = $_GET['search'] ?? '';
// ════════════════════════════════════════════════════════════
//  AJAX — JSON actions
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];

    function resp($ok, $data = [], $msg = '') {
        echo json_encode(['success' => $ok, 'message' => $msg] + $data);
        exit;
    }

    $tid = (int)$tenant_id;
    $uid = (int)$user_id;

    try {

        // ── 1. LIST QUOTATIONS ─────────────────────────────
        if ($action === 'list') {
            $where  = ['q.tenant_id = ?'];
            $params = [$tid];

            if (!empty($input['qtn_no'])) {
                $where[]  = 'q.quotation_number LIKE ?';
                $params[] = '%' . $input['qtn_no'] . '%';
            }
            if (!empty($input['customer'])) {
                $where[]  = 'c.customer_name LIKE ?';
                $params[] = '%' . $input['customer'] . '%';
            }
            if (!empty($input['status'])) {
                $where[]  = 'q.status = ?';
                $params[] = $input['status'];
            }
            if (!empty($input['date_from'])) {
                $where[]  = 'q.quotation_date >= ?';
                $params[] = $input['date_from'];
            }
            if (!empty($input['date_to'])) {
                $where[]  = 'q.quotation_date <= ?';
                $params[] = $input['date_to'];
            }

            $whereStr = implode(' AND ', $where);

            // Total count
            $stCount = $pdo->prepare("
                SELECT COUNT(*) FROM quotations q
                LEFT JOIN customers c ON c.id = q.customer_id AND c.tenant_id = q.tenant_id
                WHERE {$whereStr} AND q.is_deleted = 0");
            $stCount->execute($params);
            $total = (int)$stCount->fetchColumn();

            // Pagination
            $page     = max(1, (int)($input['page'] ?? 1));
            $perPage  = max(1, min(100, (int)($input['per_page'] ?? 15)));
            $offset   = ($page - 1) * $perPage;

           
           $st = $pdo->prepare("
    SELECT
        q.id,
        q.quotation_number,
        q.quotation_date,
        q.valid_till,
        q.status,
        q.grand_total,
        q.subtotal,
        q.total_sales_tax,
        q.total_further_tax,
        q.remarks,
        c.customer_name,
        c.customer_code,
        CONCAT(e.full_name) AS salesman_name
    FROM quotations q
    LEFT JOIN customers c ON c.id = q.customer_id AND c.tenant_id = q.tenant_id
    LEFT JOIN employees e ON e.id = q.salesman_id AND e.tenant_id = q.tenant_id
    WHERE {$whereStr} AND q.is_deleted = 0
    ORDER BY q.id DESC
    LIMIT ? OFFSET ?
");

$index = 1;

foreach ($params as $param) {
    $st->bindValue($index++, $param);
}

$st->bindValue($index++, (int)$perPage, PDO::PARAM_INT);
$st->bindValue($index++, (int)$offset, PDO::PARAM_INT);

$st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            resp(true, ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
        }

        // ── 2. CONVERT TO SALE ORDER ──────────────────────
        if ($action === 'convert_to_so') {
            if (empty($input['quotation_id'])) throw new Exception('Quotation ID is required');

            $qid = (int)$input['quotation_id'];

            // Load quotation header
           $stQ = $pdo->prepare("
    SELECT q.*, c.id AS cust_id
    FROM quotations q
    LEFT JOIN customers c ON c.id = q.customer_id AND c.tenant_id = q.tenant_id
    WHERE q.id = ? AND q.tenant_id = ? AND q.is_deleted = 0
");
            $stQ->execute([$qid, $tid]);
            $q = $stQ->fetch(PDO::FETCH_ASSOC);
            if (!$q) throw new Exception('Quotation not found');
            if ($q['status'] === 'converted') throw new Exception('Already converted to a Sale Order');
            if ($q['status'] === 'rejected')  throw new Exception('Cannot convert a rejected quotation');

            // Load quotation items
            $stI = $pdo->prepare("
                SELECT * FROM quotation_items
                WHERE quotation_id = ? AND tenant_id = ?
                ORDER BY sort_order");
            $stI->execute([$qid, $tid]);
            $items = $stI->fetchAll(PDO::FETCH_ASSOC);
            if (empty($items)) throw new Exception('Quotation has no items');

            // Generate SO number  (SO-XXXX)
            $stLast = $pdo->prepare("
                SELECT bill_no FROM sale_order
                WHERE tenant_id = ?
                ORDER BY id DESC LIMIT 1");
            $stLast->execute([$tid]);
            $lastSO = $stLast->fetchColumn();
            $soNum  = $lastSO ? ((int)preg_replace('/\D/', '', $lastSO)) + 1 : 1;
            $soNo   = 'SO-' . str_pad($soNum, 4, '0', STR_PAD_LEFT);

            // Resolve required FK defaults
            // currency_id — use tenant default or first available
           $stCurr = $pdo->prepare("SELECT id FROM currencies ORDER BY id LIMIT 1");
$stCurr->execute();
$currId = (int)($stCurr->fetchColumn() ?: 1);

            // branch_id — use first branch for this tenant
            $stBranch = $pdo->prepare("SELECT id FROM branches WHERE tenant_id = ? LIMIT 1");
            $stBranch->execute([$tid]);
            $branchId = (int)($stBranch->fetchColumn() ?: 1);

            // default UOM (needed for sale_order_items.uom_id which is NOT NULL)
            $stUom = $pdo->prepare("SELECT id FROM uom WHERE tenant_id = ? OR tenant_id = 0 ORDER BY id LIMIT 1");
            $stUom->execute([$tid]);
            $defUomId = (int)($stUom->fetchColumn() ?: 1);

            $pdo->beginTransaction();

            // Insert sale_order header
            $stSO = $pdo->prepare("
                INSERT INTO sale_order
                    (tenant_id, currency_id, bill_no, sale_date,
                     customer_id, branch_id,
                     total_bill, total_discount_percent, total_discount_amount,
                     net_amount, total_gst_amount, total_gst_percent,
                     remarks, status,
                     sale_officer_id, created_by, updated_by)
                VALUES (?,?,?,?,?,?,?,0,0,?,?,0,?,'Draft',?,?,?)");
            $stSO->execute([
                $tid,
                $currId,
                $soNo,
                $q['quotation_date'],
                (int)$q['customer_id'],
                $branchId,
                (float)$q['grand_total'],
                (float)$q['grand_total'],
                (float)$q['total_sales_tax'],
                $q['remarks'] ?: '',
                !empty($q['salesman_id']) ? (int)$q['salesman_id'] : null,
                $uid,
                $uid,
            ]);
            $soId = (int)$pdo->lastInsertId();

            // Insert sale_order_items
            $stItem = $pdo->prepare("
                INSERT INTO sale_order_items
                    (tenant_id, sale_invoice_id, product_id, uom_id,
                     quantity, sale_price, gross_amount,
                     discount_percent, discount_amount, net_amount,
                     gst_percent, gst_amount,
                     trade_offer_percent, trade_offer_amount,
                     foc_quantity, piece, carton, dozen,
                     created_by, updated_by)
                VALUES (?,?,?,?,?,?,?,0,0,?,?,?,0,0,0,0,0,0,?,?)");

            foreach ($items as $item) {
                $pid     = !empty($item['product_id']) ? (int)$item['product_id'] : null;
                if (!$pid) continue; // skip items with no product mapped

                $uomId   = !empty($item['unit_id']) ? (int)$item['unit_id'] : $defUomId;
                $qty     = (float)$item['quantity'];
                $price   = (float)$item['rate'];
                $gross   = (float)$item['excl_tax'];
                $net     = (float)$item['line_total'];
                $gstPct  = (float)$item['sales_tax_pct'];
                $gstAmt  = (float)$item['sales_tax_amt'];

                $stItem->execute([
                    $tid, $soId, $pid, $uomId,
                    $qty, $price, $gross,
                    $net,
                    $gstPct, $gstAmt,
                    $uid, $uid,
                ]);
            }

            // Update quotation status → converted, store SO reference
           $stUpd = $pdo->prepare("
    UPDATE quotations
    SET status = 'converted', updated_by = ?, updated_at = NOW()
    WHERE id = ? AND tenant_id = ?");
$stUpd->execute([$uid, $qid, $tid]);

            $pdo->commit();
            resp(true, ['so_number' => $soNo, 'so_id' => $soId], "Quotation converted to Sale Order {$soNo}");
        }

        // ── 3. REJECT QUOTATION ───────────────────────────
        if ($action === 'reject') {
            if (empty($input['quotation_id'])) throw new Exception('Quotation ID required');
            $qid = (int)$input['quotation_id'];
            $stR = $pdo->prepare("
                UPDATE quotations SET status='rejected', updated_by=?, updated_at=NOW()
                WHERE id=? AND tenant_id=? AND status NOT IN ('converted','rejected') AND is_deleted=0");
            $stR->execute([$uid, $qid, $tid]);
            if (!$stR->rowCount()) throw new Exception('Cannot reject — already converted or rejected');
            resp(true, [], 'Quotation rejected');
        }

        // ── 4. DELETE QUOTATION ───────────────────────────
        if ($action === 'delete') {
            if (empty($input['quotation_id'])) throw new Exception('Quotation ID required');
            $qid = (int)$input['quotation_id'];
            $stD = $pdo->prepare("
                UPDATE quotations SET is_deleted=1, updated_by=?, updated_at=NOW()
                WHERE id=? AND tenant_id=? AND status != 'converted'");
            $stD->execute([$uid, $qid, $tid]);
            if (!$stD->rowCount()) throw new Exception('Cannot delete a converted quotation');
            resp(true, [], 'Quotation deleted');
        }

        // ── 5. STATS FOR SUMMARY CARDS ────────────────────
        if ($action === 'stats') {
            $st = $pdo->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(status = 'draft')     AS draft,
                    SUM(status = 'pending')   AS pending,
                    SUM(status = 'approved')  AS approved,
                    SUM(status = 'converted') AS converted,
                    SUM(status = 'rejected')  AS rejected,
                    COALESCE(SUM(grand_total), 0) AS total_value
                FROM quotations
                WHERE tenant_id = ? AND is_deleted = 0");
            $st->execute([$tid]);
            resp(true, ['data' => $st->fetch(PDO::FETCH_ASSOC)]);
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        resp(false, [], $e->getMessage());
    }
    exit;
}

require_once '../../../../includes/dashboard.php';

// Load base currency
$stCur = $pdo->prepare("
    SELECT c.code
    FROM tenant_currencies tc
    JOIN ledgerone_public.currencies c ON c.id = tc.currency_id
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
    LIMIT 1
");
$stCur->execute([$tenant_id]);
$baseCur = $stCur->fetchColumn() ?: 'PKR';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Quotation List – LedgerOne ERP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── RESET & BASE ─────────────────────────────────────────── */
*{margin:0;padding:0;box-sizing:border-box;font-family:"Segoe UI",system-ui,sans-serif}
body{background:#f7f9fc;color:#2f3b4c;line-height:1.5;padding:20px}
.container{max-width:1420px;margin:0 auto;display:flex;flex-direction:column;gap:18px}

/* ── PAGE HEADER ── */
.page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.page-header h1{font-size:21px;font-weight:700;color:#0e1a2b;display:flex;align-items:center;gap:10px}
.page-header h1 i{color:#1f7bff;font-size:19px}
.header-actions{display:flex;gap:9px;flex-wrap:wrap}

/* ── STAT CARDS ── */
.stats-row{display:grid;grid-template-columns:repeat(6,1fr);gap:10px}
.stat-card{background:#fff;border:1px solid #e1e6ee;border-radius:9px;padding:13px 16px;display:flex;flex-direction:column;gap:3px;box-shadow:0 1px 4px rgba(0,0,0,.05);cursor:pointer;transition:all .15s}
.stat-card:hover{border-color:#1f7bff;transform:translateY(-1px);box-shadow:0 4px 12px rgba(31,123,255,.12)}
.stat-card.active-filter{border-color:#1f7bff;background:#f0f6ff}
.stat-card .sc-label{font-size:10.5px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px}
.stat-card .sc-value{font-size:22px;font-weight:800;color:#0e1a2b;font-family:"Courier New",monospace}
.stat-card .sc-sub{font-size:10.5px;color:#9aa1ae}
.sc-total  .sc-value{color:#1f7bff}
.sc-draft  .sc-value{color:#64748b}
.sc-pending .sc-value{color:#d97706}
.sc-approved .sc-value{color:#059669}
.sc-converted .sc-value{color:#2563eb}
.sc-rejected .sc-value{color:#dc2626}

/* ── BUTTONS ── */
.btn{height:37px;padding:0 17px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:7px;font-family:inherit;transition:all .15s;white-space:nowrap}
.btn-primary{background:#1f7bff;color:#fff}
.btn-primary:hover{background:#1a6cdc}
.btn-secondary{background:#eff2f7;border:1.5px solid #c9cfda;color:#2f3b4c}
.btn-secondary:hover{background:#e4e8ef}
.btn:disabled{opacity:.5;cursor:not-allowed}

/* ── FILTER CARD ── */
.filter-card{background:#fff;border:1px solid #e1e6ee;border-radius:9px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.filter-head{padding:10px 17px;background:#f0f3f8;border-bottom:1px solid #e1e6ee;font-size:11.5px;font-weight:700;color:#0e1a2b;text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:7px}
.filter-head i{color:#1f7bff}
.filter-body{padding:14px 17px;display:flex;gap:11px;align-items:flex-end;flex-wrap:wrap}
.fg{display:flex;flex-direction:column;gap:4px;flex:1;min-width:140px}
.fg label{font-size:10.5px;font-weight:700;color:#5a6472;text-transform:uppercase;letter-spacing:.4px}
.fg input,.fg select{height:33px;padding:0 10px;border:1.5px solid #d6dbe4;border-radius:6px;background:#fff;font-size:12.5px;color:#2f3b4c;font-family:inherit;transition:border-color .15s,box-shadow .15s;width:100%}
.fg input:focus,.fg select:focus{outline:none;border-color:#1f7bff;box-shadow:0 0 0 3px rgba(31,123,255,.1)}
.fg input::placeholder{color:#b8bfc9;font-size:12px}
.fg select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 9px center;padding-right:26px;cursor:pointer}
.filter-actions{display:flex;gap:7px;align-items:flex-end}

/* ── TABLE CARD ── */
.table-card{background:#fff;border:1px solid #e1e6ee;border-radius:9px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.table-meta{padding:11px 17px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e1e6ee;flex-wrap:wrap;gap:8px}
.table-meta .count{font-size:12.5px;color:#6b7280;font-weight:500}
.table-meta .count span{color:#0e1a2b;font-weight:700}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px;min-width:920px}
thead tr{background:#1a1f2e}
thead th{padding:11px 13px;text-align:left;font-size:11px;font-weight:700;color:#fff;white-space:nowrap;letter-spacing:.3px}
thead th.r{text-align:right}
tbody tr{border-bottom:1px solid #edf0f4;transition:background .1s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#f7f9fc}
tbody td{padding:10px 13px;color:#2f3b4c;vertical-align:middle}
.id-col{color:#9aa1ae;font-size:12px;font-weight:600}
.qtn-col{font-weight:700;color:#0e1a2b;font-size:13.5px}
.so-ref{font-size:11px;color:#2563eb;font-weight:600;margin-top:2px}
.amt-col{font-weight:600;color:#0e1a2b;text-align:right}
.date-col{font-size:12.5px;color:#5a6472}

/* ── BADGES ── */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:capitalize;letter-spacing:.2px}
.b-draft    {background:#f1f5f9;color:#475569;border:1px solid #cbd5e1}
.b-pending  {background:#fff8e6;color:#b45309;border:1px solid #fde68a}
.b-approved {background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7}
.b-rejected {background:#fef2f2;color:#991b1b;border:1px solid #fca5a5}
.b-converted{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}

/* ── ACTION BUTTONS ── */
.act-group{display:flex;gap:4px;flex-wrap:wrap;align-items:center}
.ab{height:27px;padding:0 9px;border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:4px;font-family:inherit;transition:opacity .13s,transform .1s;white-space:nowrap}
.ab:hover:not(:disabled){opacity:.85;transform:translateY(-1px)}
.ab:disabled{opacity:.38;cursor:not-allowed;transform:none!important}
.ab-view   {background:#0ea5e9;color:#fff}
.ab-edit   {background:#f59e0b;color:#fff}
.ab-print  {background:#6b7280;color:#fff}
.ab-convert{background:#10b981;color:#fff}
.ab-reject {background:#ef4444;color:#fff}
.ab-delete {background:#fff;border:1.5px solid #e34f4f;color:#e34f4f}
.ab-delete:hover{background:#fef2f2}

/* ── EMPTY STATE ── */
.empty-state{padding:56px 24px;text-align:center;color:#9aa1ae}
.empty-state i{font-size:42px;margin-bottom:14px;display:block;color:#d1d5db}
.empty-state p{font-size:14px;margin-bottom:5px;color:#6b7280;font-weight:500}
.empty-state small{font-size:12.5px}

/* ── PAGINATION ── */
.pagination{display:flex;justify-content:space-between;align-items:center;padding:11px 17px;border-top:1px solid #e1e6ee;flex-wrap:wrap;gap:10px}
.page-info{font-size:12px;color:#6b7280}
.page-btns{display:flex;gap:4px}
.pg-btn{width:31px;height:31px;border-radius:6px;border:1.5px solid #e1e6ee;background:#fff;font-size:12.5px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#2f3b4c;transition:all .14s;font-family:inherit}
.pg-btn:hover{border-color:#1f7bff;color:#1f7bff;background:#f0f6ff}
.pg-btn.active{background:#1f7bff;border-color:#1f7bff;color:#fff}
.pg-btn:disabled{opacity:.38;cursor:default}

/* ── MODALS ── */
.modal-ov{display:none;position:fixed;inset:0;background:rgba(14,26,43,.45);z-index:9000;align-items:center;justify-content:center;backdrop-filter:blur(2px)}
.modal-ov.show{display:flex;animation:fadeIn .18s ease}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal-box{background:#fff;border-radius:10px;width:92%;max-width:460px;box-shadow:0 8px 32px rgba(0,0,0,.2);overflow:hidden;animation:slideUp .2s ease}
.modal-box.lg{max-width:560px}
@keyframes slideUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.modal-head{padding:13px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e1e6ee}
.modal-head h3{font-size:14px;font-weight:700;color:#0e1a2b;display:flex;align-items:center;gap:8px}
.modal-head h3 i{font-size:13px}
.mclose{background:none;border:none;color:#9aa1ae;font-size:17px;cursor:pointer;width:27px;height:27px;border-radius:5px;display:flex;align-items:center;justify-content:center;transition:background .13s}
.mclose:hover{background:#f0f3f8;color:#2f3b4c}
.modal-body{padding:20px}
.modal-footer{padding:11px 20px;border-top:1px solid #e1e6ee;display:flex;justify-content:flex-end;gap:8px}

/* Convert SO modal specific */
.so-preview{background:#f0f6ff;border:1px solid #c8dbff;border-radius:8px;padding:14px 16px;margin-bottom:14px}
.so-preview .sp-row{display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid #dbeafe;font-size:12.5px}
.so-preview .sp-row:last-child{border-bottom:none}
.so-preview .sp-lbl{color:#5a6472;font-weight:600}
.so-preview .sp-val{color:#0e1a2b;font-weight:700}
.info-box{display:flex;gap:9px;align-items:flex-start;background:#fffbeb;border:1px solid #fde68a;border-radius:7px;padding:11px 14px;font-size:12.5px;color:#92400e;margin-top:10px}
.info-box i{margin-top:2px;flex-shrink:0}

/* Convert success modal */
.success-icon{width:60px;height:60px;background:#ecfdf5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;border:2px solid #6ee7b7}
.success-icon i{font-size:26px;color:#059669}

/* spinner */
.spinner-ov{display:none;position:fixed;inset:0;background:rgba(255,255,255,.5);z-index:9500;align-items:center;justify-content:center}
.spinner-ov.show{display:flex}
.spinner{width:36px;height:36px;border:4px solid #e1e6ee;border-top-color:#1f7bff;border-radius:50%;animation:spin .65s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* toast */
.toast{position:fixed;top:16px;right:20px;padding:10px 18px;border-radius:7px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(0,0,0,.15);z-index:9999;color:#fff;animation:slideUp .2s ease}
.t-success{background:#2fbf71}
.t-error  {background:#e34f4f}
.t-info   {background:#1f7bff}

.loading-row td{text-align:center;padding:30px;color:#9aa1ae;font-size:13px}
.skeleton{display:inline-block;background:linear-gradient(90deg,#f0f3f8 25%,#e8ebf0 50%,#f0f3f8 75%);background-size:200% 100%;animation:shimmer 1.2s infinite;border-radius:4px;height:12px;width:80%}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

@media(max-width:900px){.stats-row{grid-template-columns:repeat(3,1fr)}}
@media(max-width:600px){body{padding:10px}.stats-row{grid-template-columns:repeat(2,1fr)}.page-header{flex-direction:column;align-items:flex-start}.filter-body{flex-direction:column}.fg{min-width:100%}.filter-actions .btn{flex:1;justify-content:center}}
</style>
</head>
<body>

<div class="container">

  <!-- ══ PAGE HEADER ══════════════════════════════════════ -->
  <div class="page-header">
    <h1><i class="fas fa-file-invoice"></i> Quotation List</h1>
    <div class="header-actions">
      <button class="btn btn-secondary" onclick="window.print()">
        <i class="fas fa-print"></i> Print List
      </button>
      <button class="btn btn-primary" onclick="location.href='quotation-add.php'">
        <i class="fas fa-plus"></i> New Quotation
      </button>
    </div>
  </div>

  <!-- ══ STAT CARDS ═══════════════════════════════════════ -->
  <div class="stats-row" id="statsRow">
    <?php foreach(['total','draft','pending','approved','converted','rejected'] as $s): ?>
    <div class="stat-card sc-<?=$s?>" onclick="filterByStatus('<?=$s?>')" id="sc-<?=$s?>">
      <div class="sc-label"><?=ucfirst($s)?></div>
      <div class="sc-value" id="sv-<?=$s?>">–</div>
      <div class="sc-sub" id="ss-<?=$s?>"><?=$s==='total'?'Total Value':ucfirst($s).' Qtns'?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ══ FILTER CARD ══════════════════════════════════════ -->
  <div class="filter-card">
    <div class="filter-head"><i class="fas fa-filter"></i> Filter Quotations</div>
    <div class="filter-body">
      <div class="fg">
        <label>Quotation No.</label>
<input type="text" id="fQtn" placeholder="e.g. QTN-0023" oninput="debouncedFetch()" value="<?= htmlspecialchars($initialSearch) ?>"/>
      </div>
      <div class="fg">
        <label>Customer Name</label>
        <input type="text" id="fCustomer" placeholder="Customer…" oninput="debouncedFetch()"/>
      </div>
      <div class="fg">
        <label>Date From</label>
        <input type="date" id="fDateFrom" onchange="fetchList()"/>
      </div>
      <div class="fg">
        <label>Date To</label>
        <input type="date" id="fDateTo" onchange="fetchList()"/>
      </div>
      <div class="fg">
        <label>Status</label>
        <select id="fStatus" onchange="fetchList()">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="converted">Converted to SO</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      <div class="filter-actions">
        <button class="btn btn-primary" onclick="fetchList()"><i class="fas fa-search"></i> Search</button>
        <button class="btn btn-secondary" onclick="clearFilters()"><i class="fas fa-times"></i> Clear</button>
      </div>
    </div>
  </div>

  <!-- ══ TABLE CARD ═══════════════════════════════════════ -->
  <div class="table-card">
    <div class="table-meta">
      <div class="count">Showing <span id="showingCount">0</span> of <span id="totalCount">0</span> quotations</div>
      <div style="font-size:12px;color:#6b7280;display:flex;align-items:center;gap:5px">
        <i class="fas fa-circle" style="color:#1f7bff;font-size:7px"></i>
        Last updated: <span id="lastUpdated">–</span>
      </div>
    </div>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:45px">#</th>
            <th style="width:115px">Quotation No.</th>
            <th>Customer</th>
            <th style="width:118px">Date</th>
            <th style="width:118px">Valid Till</th>
            <th class="r" style="width:120px">Grand Total</th>
            <th style="width:115px">Status</th>
            <th style="width:305px">Actions</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <tr class="loading-row"><td colspan="8"><span class="skeleton"></span></td></tr>
        </tbody>
      </table>
    </div>

    <div id="emptyState" class="empty-state" style="display:none">
      <i class="fas fa-file-search"></i>
      <p>No quotations found</p>
      <small>Try adjusting filters or create a new quotation.</small>
    </div>

    <div class="pagination" id="paginationWrap" style="display:none">
      <div class="page-info" id="pageInfo"></div>
      <div class="page-btns" id="pageBtns"></div>
    </div>
  </div>

</div><!-- /container -->

<!-- ══ SPINNER ═════════════════════════════════════════════ -->
<div class="spinner-ov" id="spinnerOv"><div class="spinner"></div></div>

<!-- ══ CONVERT TO SO — CONFIRM MODAL ══════════════════════ -->
<div class="modal-ov" id="convertModal">
  <div class="modal-box lg">
    <div class="modal-head" style="border-bottom-color:#d1fae5">
      <h3 style="color:#065f46"><i class="fas fa-exchange-alt" style="color:#10b981"></i> Convert to Sale Order</h3>
      <button class="mclose" onclick="closeModal('convertModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="so-preview" id="soPreview">
        <div class="sp-row"><span class="sp-lbl">Quotation No.</span><span class="sp-val" id="cp_qno">–</span></div>
        <div class="sp-row"><span class="sp-lbl">Customer</span><span class="sp-val" id="cp_cust">–</span></div>
        <div class="sp-row"><span class="sp-lbl">Quotation Date</span><span class="sp-val" id="cp_date">–</span></div>
        <div class="sp-row"><span class="sp-lbl">Grand Total</span><span class="sp-val" id="cp_total">–</span></div>
        <div class="sp-row"><span class="sp-lbl">Salesman</span><span class="sp-val" id="cp_salesman">–</span></div>
      </div>
      <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <div>This will create a new <strong>Sale Order (Draft)</strong> from this quotation and mark the quotation as <strong>Converted</strong>. This action cannot be undone.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('convertModal')">Cancel</button>
      <button class="btn btn-primary" id="confirmConvertBtn" onclick="confirmConvert()" style="background:#10b981">
        <i class="fas fa-check"></i> Confirm & Convert
      </button>
    </div>
  </div>
</div>

<!-- ══ CONVERT SUCCESS MODAL ══════════════════════════════ -->
<div class="modal-ov" id="successModal">
  <div class="modal-box" style="max-width:400px;text-align:center">
    <div class="modal-body" style="padding:30px 24px">
      <div class="success-icon"><i class="fas fa-check"></i></div>
      <h3 style="font-size:17px;font-weight:700;color:#0e1a2b;margin-bottom:6px">Converted Successfully!</h3>
      <p style="font-size:13px;color:#6b7280;margin-bottom:16px">The quotation has been converted to a Sale Order.</p>
      <div style="background:#f0f6ff;border:1px solid #c8dbff;border-radius:8px;padding:12px;margin-bottom:20px">
        <div style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Sale Order Number</div>
        <div style="font-size:22px;font-weight:800;color:#1f7bff;font-family:'Courier New',monospace" id="successSONo">–</div>
      </div>
      <div style="display:flex;gap:9px;justify-content:center">
        <button class="btn btn-secondary" onclick="closeModal('successModal')">Close</button>
        <button class="btn btn-primary" id="viewSOBtn" onclick="goToSO()"><i class="fas fa-external-link-alt"></i> View Sale Order</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ DELETE CONFIRM MODAL ═══════════════════════════════ -->
<div class="modal-ov" id="deleteModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3><i class="fas fa-exclamation-triangle" style="color:#e34f4f"></i> Confirm Delete</h3>
      <button class="mclose" onclick="closeModal('deleteModal')">&times;</button>
    </div>
    <div class="modal-body">
      Are you sure you want to delete quotation <strong id="deleteQtnRef" style="color:#1f7bff"></strong>?
      <br><br>
      <span style="color:#e34f4f;font-size:13px"><i class="fas fa-info-circle"></i> This action cannot be undone.</span>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
      <button class="btn" style="background:#e34f4f;color:#fff" onclick="confirmDelete()">
        <i class="fas fa-trash-alt"></i> Delete
      </button>
    </div>
  </div>
</div>

<!-- ══ REJECT CONFIRM MODAL ═══════════════════════════════ -->
<div class="modal-ov" id="rejectModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3><i class="fas fa-ban" style="color:#ef4444"></i> Reject Quotation</h3>
      <button class="mclose" onclick="closeModal('rejectModal')">&times;</button>
    </div>
    <div class="modal-body">
      Are you sure you want to reject <strong id="rejectQtnRef" style="color:#1f7bff"></strong>?
      <br><br>
      <span style="color:#e34f4f;font-size:13px"><i class="fas fa-info-circle"></i> A rejected quotation cannot be converted to a Sale Order.</span>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
      <button class="btn" style="background:#ef4444;color:#fff" onclick="confirmReject()">
        <i class="fas fa-ban"></i> Reject
      </button>
    </div>
  </div>
</div>

<!-- ══ JAVASCRIPT ══════════════════════════════════════════ -->
<script>
const API = location.pathname;
const PAGE_SIZE = 15;
const BASE_CUR = <?= json_encode($baseCur) ?>;

let currentPage  = 1;
let totalRecords = 0;
let pendingDeleteId  = null;
let pendingRejectId  = null;
let pendingConvertId = null;
let lastSOId = null;
let debounceTimer = null;

// ── FORMAT ────────────────────────────────────────────────
function fmt(n){ return parseFloat(n||0).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2}); }

function fmtDate(d){ if(!d) return '–'; const [y,m,dy]=d.split('-'); return `${dy}/${m}/${y}`; }

// ── BADGE MAP ─────────────────────────────────────────────
const badgeMap = {
  draft:     '<span class="badge b-draft"><i class="fas fa-pencil-alt" style="font-size:8px"></i> Draft</span>',
  pending:   '<span class="badge b-pending"><i class="fas fa-clock" style="font-size:8px"></i> Pending</span>',
  approved:  '<span class="badge b-approved"><i class="fas fa-check-circle" style="font-size:8px"></i> Approved</span>',
  rejected:  '<span class="badge b-rejected"><i class="fas fa-times-circle" style="font-size:8px"></i> Rejected</span>',
  converted: '<span class="badge b-converted"><i class="fas fa-exchange-alt" style="font-size:8px"></i> Converted</span>',
};

// ── API ───────────────────────────────────────────────────
async function api(action, body={}) {
  const r = await fetch(`${API}?action=${action}`, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body)
  });
  return r.json();
}

// ── LOAD STATS ────────────────────────────────────────────
async function loadStats() {
  const d = await api('stats');
  if (!d.success) return;
  const s = d.data;
  document.getElementById('sv-total').textContent    = s.total    || 0;
  document.getElementById('sv-draft').textContent    = s.draft    || 0;
  document.getElementById('sv-pending').textContent  = s.pending  || 0;
  document.getElementById('sv-approved').textContent = s.approved || 0;
  document.getElementById('sv-converted').textContent= s.converted|| 0;
  document.getElementById('sv-rejected').textContent = s.rejected || 0;
  document.getElementById('ss-total').textContent    = BASE_CUR + ' ' + fmt(s.total_value);
}

// ── FILTER BY STATUS (stat card click) ───────────────────
function filterByStatus(status) {
  document.querySelectorAll('.stat-card').forEach(c=>c.classList.remove('active-filter'));
  if (status === 'total') {
    document.getElementById('fStatus').value = '';
  } else {
    document.getElementById('fStatus').value = status;
    document.getElementById('sc-'+status)?.classList.add('active-filter');
  }
  currentPage = 1;
  fetchList();
}

// ── FETCH LIST ────────────────────────────────────────────
async function fetchList() {
  showSpinner(true);
  const body = {
    qtn_no:    document.getElementById('fQtn').value.trim(),
    customer:  document.getElementById('fCustomer').value.trim(),
    date_from: document.getElementById('fDateFrom').value,
    date_to:   document.getElementById('fDateTo').value,
    status:    document.getElementById('fStatus').value,
    page:      currentPage,
    per_page:  PAGE_SIZE,
  };
  const d = await api('list', body);
  showSpinner(false);
  if (!d.success) { toast(d.message||'Load failed','error'); return; }
  totalRecords = d.total;
  renderTable(d.data);
  renderPagination();
  document.getElementById('showingCount').textContent = d.data.length;
  document.getElementById('totalCount').textContent   = d.total;
  document.getElementById('lastUpdated').textContent  = new Date().toLocaleString('en-PK',{dateStyle:'medium',timeStyle:'short'});
}

function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(()=>{ currentPage=1; fetchList(); }, 420);
}

function clearFilters() {
  ['fQtn','fCustomer','fDateFrom','fDateTo'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('fStatus').value='';
  document.querySelectorAll('.stat-card').forEach(c=>c.classList.remove('active-filter'));
  currentPage=1; fetchList();
}

// ── RENDER TABLE ──────────────────────────────────────────
function renderTable(rows) {
  const tbody  = document.getElementById('tableBody');
  const empty  = document.getElementById('emptyState');
  const pagWrap= document.getElementById('paginationWrap');

  if (!rows || !rows.length) {
    tbody.innerHTML='';
    empty.style.display='block';
    pagWrap.style.display='none';
    return;
  }
  empty.style.display='none';
  pagWrap.style.display='flex';

  tbody.innerHTML = rows.map(r => {
    const isConverted = r.status === 'converted';
    const isRejected  = r.status === 'rejected';
    const cantConvert = isConverted || isRejected;
    const cantReject  = isConverted || isRejected;
    const cantDelete  = isConverted;

    return `<tr>
      <td class="id-col">${r.id}</td>
      <td class="qtn-col">
        ${r.quotation_number}
        ${r.so_reference ? `<div class="so-ref"><i class="fas fa-link" style="font-size:9px"></i> ${r.so_reference}</div>` : ''}
      </td>
      <td>
        <div style="font-weight:600;font-size:13px">${escHtml(r.customer_name||'–')}</div>
        <div style="font-size:11px;color:#9aa1ae">${escHtml(r.customer_code||'')}</div>
      </td>
      <td class="date-col">${fmtDate(r.quotation_date)}</td>
      <td class="date-col">${fmtDate(r.valid_till)}</td>
      <td class="amt-col">${BASE_CUR} ${fmt(r.grand_total)}</td>
      <td>${badgeMap[r.status] || r.status}</td>
      <td>
        <div class="act-group">
          <button class="ab ab-view"    onclick="doView(${r.id})"><i class="fas fa-eye"></i> View</button>
          <button class="ab ab-edit"    onclick="doEdit(${r.id})" ${isConverted?'disabled':''} title="${isConverted?'Cannot edit a converted quotation':'Edit'}"><i class="fas fa-edit"></i> Edit</button>
          <button class="ab ab-print"   onclick="doPrint(${r.id})"><i class="fas fa-print"></i> Print</button>
          <button class="ab ab-print"   onclick="doPrint2(${r.id})" style="background:#7c3aed"><i class="fas fa-print"></i> Print 2</button>
          <button class="ab ab-convert" onclick="openConvertModal(${r.id})" ${cantConvert?'disabled':''} title="${isConverted?'Already converted':isRejected?'Cannot convert rejected quotation':'Convert to Sale Order'}">
            <i class="fas fa-exchange-alt"></i> ${isConverted?'Converted':'Convert SO'}
          </button>
          <button class="ab ab-reject"  onclick="openRejectModal(${r.id},'${escHtml(r.quotation_number)}')" ${cantReject?'disabled':''}>
            <i class="fas fa-ban"></i> Reject
          </button>
          <button class="ab ab-delete"  onclick="openDeleteModal(${r.id},'${escHtml(r.quotation_number)}')" ${cantDelete?'disabled':''} title="${cantDelete?'Cannot delete a converted quotation':'Delete'}">
            <i class="fas fa-trash-alt"></i>
          </button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── PAGINATION ────────────────────────────────────────────
function renderPagination() {
  const totalPages = Math.ceil(totalRecords / PAGE_SIZE);
  const info = document.getElementById('pageInfo');
  const btns = document.getElementById('pageBtns');
  const start = (currentPage-1)*PAGE_SIZE+1;
  const end   = Math.min(currentPage*PAGE_SIZE, totalRecords);
  info.textContent = `Showing ${start}–${end} of ${totalRecords} results`;

  let html = `<button class="pg-btn" onclick="goPage(${currentPage-1})" ${currentPage===1?'disabled':''}><i class="fas fa-chevron-left" style="font-size:9px"></i></button>`;
  for (let i=1;i<=totalPages;i++) {
    if (totalPages<=7 || i===1 || i===totalPages || Math.abs(i-currentPage)<=1) {
      html+=`<button class="pg-btn ${i===currentPage?'active':''}" onclick="goPage(${i})">${i}</button>`;
    } else if (Math.abs(i-currentPage)===2) {
      html+=`<button class="pg-btn" style="cursor:default;border:none">…</button>`;
    }
  }
  html+=`<button class="pg-btn" onclick="goPage(${currentPage+1})" ${currentPage===totalPages?'disabled':''}><i class="fas fa-chevron-right" style="font-size:9px"></i></button>`;
  btns.innerHTML=html;
}

function goPage(p) {
  const total=Math.ceil(totalRecords/PAGE_SIZE);
  if (p<1||p>total) return;
  currentPage=p; fetchList();
}

// ── ACTION: VIEW / EDIT / PRINT ───────────────────────────
function doView(id)   { location.href=`quotation-view.php?id=${id}`; }
function doEdit(id)   { location.href=`quotation-edit.php?id=${id}`; }
function doPrint(id)  { window.open(`quotation-print.php?id=${id}`,'_blank'); }
function doPrint2(id) { window.open(`quotation-print-v2.php?id=${id}`,'_blank'); }

// ── ACTION: CONVERT TO SO ─────────────────────────────────
let pendingConvertData = null;

function openConvertModal(id) {
  // find row data from DOM (we already rendered it)
  // re-fetch from current list data? We'll cache last response.
  const row = lastRows?.find(r=>r.id==id);
  if (!row) { toast('Row data not found, please refresh','error'); return; }

  pendingConvertId = id;
  document.getElementById('cp_qno').textContent     = row.quotation_number || '–';
  document.getElementById('cp_cust').textContent    = row.customer_name    || '–';
  document.getElementById('cp_date').textContent    = fmtDate(row.quotation_date);
  document.getElementById('cp_total').textContent   = BASE_CUR + ' ' + fmt(row.grand_total);
  document.getElementById('cp_salesman').textContent= row.salesman_name    || 'N/A';
  openModal('convertModal');
}

async function confirmConvert() {
  if (!pendingConvertId) return;
  const btn = document.getElementById('confirmConvertBtn');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Converting…';
  showSpinner(true);

  const d = await api('convert_to_so', { quotation_id: pendingConvertId });
  showSpinner(false);
  btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> Confirm & Convert';

  if (!d.success) { toast(d.message||'Convert failed','error'); return; }

  closeModal('convertModal');
  lastSOId = d.so_id;
  document.getElementById('successSONo').textContent = d.so_number;
  openModal('successModal');
  pendingConvertId = null;
  fetchList();
  loadStats();
}

function goToSO() {
  if (lastSOId) location.href = `../sale-orders/sale-order-view.php?id=${lastSOId}`;
location.href = '/client/pages/sale/sale_order/order-list.php';}

// ── ACTION: REJECT ────────────────────────────────────────
function openRejectModal(id, qno) {
  pendingRejectId = id;
  document.getElementById('rejectQtnRef').textContent = qno;
  openModal('rejectModal');
}

async function confirmReject() {
  if (!pendingRejectId) return;
  showSpinner(true);
  const d = await api('reject', { quotation_id: pendingRejectId });
  showSpinner(false);
  closeModal('rejectModal');
  if (!d.success) { toast(d.message||'Reject failed','error'); return; }
  toast('Quotation rejected');
  pendingRejectId = null;
  fetchList(); loadStats();
}

// ── ACTION: DELETE ────────────────────────────────────────
function openDeleteModal(id, qno) {
  pendingDeleteId = id;
  document.getElementById('deleteQtnRef').textContent = qno;
  openModal('deleteModal');
}

async function confirmDelete() {
  if (!pendingDeleteId) return;
  showSpinner(true);
  const d = await api('delete', { quotation_id: pendingDeleteId });
  showSpinner(false);
  closeModal('deleteModal');
  if (!d.success) { toast(d.message||'Delete failed','error'); return; }
  toast('Quotation deleted','success');
  pendingDeleteId = null;
  fetchList(); loadStats();
}

// ── MODAL HELPERS ─────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-ov').forEach(ov=>{
  ov.addEventListener('click',e=>{ if(e.target===ov) ov.classList.remove('show'); });
});
document.addEventListener('keydown',e=>{ if(e.key==='Escape') document.querySelectorAll('.modal-ov.show').forEach(m=>m.classList.remove('show')); });

// ── SPINNER / TOAST ───────────────────────────────────────
function showSpinner(v){ document.getElementById('spinnerOv').classList.toggle('show',v); }

function toast(msg,type='success') {
  const colors = {success:'t-success', error:'t-error', info:'t-info'};
  const icons  = {success:'check-circle', error:'times-circle', info:'info-circle'};
  const t = document.createElement('div');
  t.className=`toast ${colors[type]||'t-success'}`;
  t.innerHTML=`<i class="fas fa-${icons[type]||'check-circle'}"></i> ${msg}`;
  document.body.appendChild(t);
  setTimeout(()=>{ t.style.opacity='0'; t.style.transition='opacity .4s'; setTimeout(()=>t.remove(),400); }, 3000);
}

// Cache last rows for modal data lookup
let lastRows = [];
const _origRender = renderTable;

// ── BOOT ─────────────────────────────────────────────────
(async()=>{
  // Patch renderTable to cache rows
  window.renderTable = function(rows){ lastRows=rows||[]; _origRender(rows); };
  await Promise.all([loadStats(), fetchList()]);
})();
</script>
</body>
</html>