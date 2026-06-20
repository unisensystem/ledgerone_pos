<?php
require_once '../../../../includes/connection.php';

if (session_status() == PHP_SESSION_NONE) session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];

    function resp($ok, $data = [], $msg = '') {
        echo json_encode(['success' => $ok, 'message' => $msg] + $data);
        exit;
    }

    try {
        // ── 1. SAVE QUOTATION ─────────────────────────────────
        if ($action === 'save') {
            if (empty($input['customer_id']))  throw new Exception('Customer is required');
            if (empty($input['items']))        throw new Exception('At least one item is required');

            $tid = (int)$GLOBALS['tenant_id'];
            $uid = (int)$GLOBALS['user_id'];

            $st = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(quotation_number, 5) AS UNSIGNED))
                                  FROM quotations WHERE tenant_id=?");
            $st->execute([$tid]);
            $maxNum = (int)$st->fetchColumn();
            $qno    = 'QTN-' . str_pad($maxNum + 1, 4, '0', STR_PAD_LEFT);

            $subtotal = $total_st = $total_ft = 0;
            foreach ($input['items'] as $item) {
                $subtotal += (float)($item['excl_tax']        ?? 0);
                $total_st += (float)($item['sales_tax_amt']   ?? 0);
                $total_ft += (float)($item['further_tax_amt'] ?? 0);
            }
            $grand = $subtotal + $total_st + $total_ft;

            $pdo->beginTransaction();

            $st = $pdo->prepare("INSERT INTO quotations
                (tenant_id, quotation_number, quotation_date, valid_till,
                 customer_id, contact_person, salesman_id, project_id, payment_term_id,
                 terms_conditions, remarks, footer_note, party_type, status,
                 subtotal, total_sales_tax, total_further_tax, grand_total,
                 created_by, updated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?,?,?,?,?,?)");
            $st->execute([
                $tid,
                $qno,
                $input['quotation_date'] ?: date('Y-m-d'),
                $input['valid_till'] ?: null,
                (int)$input['customer_id'],
                $input['contact_person'] ?: null,
                !empty($input['salesman_id'])     ? (int)$input['salesman_id']     : null,
                !empty($input['project_id'])      ? (int)$input['project_id']      : null,
                !empty($input['payment_term_id']) ? (int)$input['payment_term_id'] : null,
                $input['terms_conditions'] ?: null,
                $input['remarks'] ?: null,
                $input['footer_note'] ?: null,
                $input['party_type']  ?: null,
                $subtotal, $total_st, $total_ft, $grand,
                $uid, $uid,
            ]);
            $qid = $pdo->lastInsertId();

            $si = $pdo->prepare("INSERT INTO quotation_items
                (quotation_id, tenant_id, sort_order, product_id, item_code, item_name,
                 description, size, pack_type, quantity, qty_unit_id, unit_id,
                 rate, excl_tax, sales_tax_pct, sales_tax_amt,
                 further_tax_pct, further_tax_amt, incl_tax, line_total)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($input['items'] as $i => $it) {
                $si->execute([
                    $qid, $tid, $i + 1,
                    !empty($it['product_id']) ? (int)$it['product_id'] : null,
                    $it['item_code']   ?? null,
                    $it['item_name']   ?? '',
                    $it['description'] ?? null,
                    $it['size']        ?? null,
                    $it['pack_type']   ?? 'Qty',
                    (float)($it['quantity']        ?? 1),
                    !empty($it['qty_unit_id']) ? (int)$it['qty_unit_id'] : null,
                    !empty($it['unit_id'])     ? (int)$it['unit_id']     : null,
                    (float)($it['rate']            ?? 0),
                    (float)($it['excl_tax']        ?? 0),
                    (float)($it['sales_tax_pct']   ?? 0),
                    (float)($it['sales_tax_amt']   ?? 0),
                    (float)($it['further_tax_pct'] ?? 0),
                    (float)($it['further_tax_amt'] ?? 0),
                    (float)($it['incl_tax']        ?? 0),
                    (float)($it['line_total']      ?? 0),
                ]);
            }

            $pdo->commit();
            resp(true, ['quotation_number' => $qno, 'id' => $qid], 'Quotation saved successfully');
        }

        // ── 2. LOAD CUSTOMERS ─────────────────────────────────
        if ($action === 'customers') {
            $st = $pdo->prepare("SELECT id, customer_code AS code, customer_name AS name,
                                        primary_phone AS phone, address, email
                                  FROM customers WHERE tenant_id=? ORDER BY customer_name");
            $st->execute([$tenant_id]);
            resp(true, ['data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ── 3. LOAD PRODUCTS ──────────────────────────────────
        if ($action === 'products') {
            $st = $pdo->prepare("SELECT id, code, name, description,
                                        trade_price AS rate, sales_tax, further_tax,
                                        default_unit_id AS unit_id
                                  FROM products WHERE tenant_id=? AND is_active=1 ORDER BY name");
            $st->execute([$tenant_id]);
            resp(true, ['data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ── 4. LOAD SALESMEN ──────────────────────────────────
        if ($action === 'salesmen') {
            $st = $pdo->prepare("SELECT id, full_name AS name FROM employees
                                  WHERE tenant_id=? AND current_status='active' AND is_terminated=0
                                  ORDER BY full_name");
            $st->execute([$tenant_id]);
            resp(true, ['data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ── 5. LOAD PAYMENT TERMS ─────────────────────────────
        if ($action === 'payment_terms') {
            $st = $pdo->prepare("SELECT id, term_name FROM payment_terms
                                  WHERE (tenant_id=? OR tenant_id=0) AND is_active=1 ORDER BY id");
            $st->execute([$tenant_id]);
            resp(true, ['data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ── 6. ADD PAYMENT TERM ───────────────────────────────
        if ($action === 'add_term') {
            if (empty($input['term_name'])) throw new Exception('Term name is required');
            $st = $pdo->prepare("INSERT INTO payment_terms (tenant_id, term_name, days) VALUES (?,?,?)");
            $st->execute([$tenant_id, trim($input['term_name']), (int)($input['days'] ?? 0)]);
            resp(true, ['id' => $pdo->lastInsertId()], 'Term added');
        }

        // ── 7. DELETE PAYMENT TERM ────────────────────────────
        if ($action === 'delete_term') {
            $st = $pdo->prepare("DELETE FROM payment_terms WHERE id=? AND tenant_id=?");
            $st->execute([(int)$input['id'], $tenant_id]);
            resp(true, [], 'Term deleted');
        }

        // ── 8. LOAD UNITS ─────────────────────────────────────
        if ($action === 'units') {
            $st = $pdo->prepare("SELECT id, uom_name AS name, uom_name AS symbol
                                  FROM uom WHERE (tenant_id=? OR tenant_id=0) ORDER BY uom_name");
            $st->execute([$tenant_id]);
            resp(true, ['data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ── 9. NEXT QTN NUMBER ────────────────────────────────
        if ($action === 'next_number') {
            $st = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(quotation_number, 5) AS UNSIGNED))
                                  FROM quotations WHERE tenant_id=?");
            $st->execute([$tenant_id]);
            $maxNum = (int)$st->fetchColumn();
            $qno    = 'QTN-' . str_pad($maxNum + 1, 4, '0', STR_PAD_LEFT);
            resp(true, ['number' => $qno]);
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        resp(false, [], $e->getMessage());
    }
    exit;
}
require_once '../../../../includes/dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Quotation - LedgerOne ERP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── RESET & BASE ─────────────────────────────────────────── */
*{margin:0;padding:0;box-sizing:border-box;font-family:"Segoe UI",system-ui,sans-serif}
body{background:#f7f9fc;color:#2f3b4c;line-height:1.5;padding:20px}
.container{max-width:1420px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.08);overflow:hidden}

/* ── HEADER ──────────────────────────────────────────────── */
.header{padding:16px 26px;border-bottom:1px solid #e1e6ee;display:flex;justify-content:space-between;align-items:center;background:#fff;position:sticky;top:0;z-index:100}
.header-left h1{font-size:19px;font-weight:700;color:#0e1a2b;display:flex;align-items:center;gap:9px}
.header-left h1 i{color:#1f7bff;font-size:17px}
.header-left p{color:#6b7280;font-size:12px;margin-top:2px}
.qtn-chip{background:#f0f6ff;border:1.5px solid #c8dbff;border-radius:8px;padding:7px 18px;text-align:center;min-width:130px}
.qtn-chip .no{font-family:"Courier New",monospace;font-size:16px;font-weight:700;color:#1f7bff}
.qtn-chip .lbl{font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.6px;margin-top:1px}

/* ── FORM AREA ────────────────────────────────────────────── */
.form-area{padding:16px 22px;display:flex;flex-direction:column;gap:12px}
.card{background:#fafbfd;border:1px solid #e1e6ee;border-radius:8px;overflow:hidden}
.card-head{padding:9px 14px;background:#f0f3f8;border-bottom:1px solid #e1e6ee;font-size:11.5px;font-weight:700;color:#0e1a2b;text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:7px}
.card-head i{color:#1f7bff;font-size:11px}
.card-body{padding:13px}

/* form grid */
.fg{display:grid;gap:9px}
.fg-2{grid-template-columns:repeat(2,1fr)}
.fg-3{grid-template-columns:repeat(3,1fr)}
.fg-4{grid-template-columns:repeat(4,1fr)}
.fg-5{grid-template-columns:repeat(5,1fr)}
.span2{grid-column:span 2}
.span3{grid-column:span 3}
.span4{grid-column:span 4}

.f{display:flex;flex-direction:column;gap:3px}
.f label{font-size:11px;font-weight:600;color:#5a6472;text-transform:uppercase;letter-spacing:.4px;display:flex;align-items:center;gap:5px}
.req{color:#e34f4f}

input[type=text],input[type=date],input[type=number],select,textarea{
  height:33px;padding:0 10px;border:1.5px solid #d6dbe4;border-radius:6px;
  background:#fff;font-size:12.5px;color:#2f3b4c;
  transition:border-color .15s,box-shadow .15s;font-family:inherit;width:100%}
input:focus,select:focus,textarea:focus{outline:none;border-color:#1f7bff;box-shadow:0 0 0 3px rgba(31,123,255,.1)}
input[readonly]{background:#f2f4f8;color:#8a93a2;border-color:#e0e4ea;cursor:default}
input::placeholder,textarea::placeholder{color:#b8bfc9;font-size:12px}
select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 9px center;padding-right:26px;cursor:pointer}
textarea{height:82px;padding:8px 10px;resize:vertical;font-size:12px;line-height:1.5}
textarea.remarks-ta{height:60px}

.inline-row{display:flex;gap:6px;align-items:flex-end}
.inline-row>*{flex:1}

/* ── SEARCHABLE SELECT ────────────────────────────────────── */
.ss-wrap{position:relative;width:100%}
.ss-display{height:33px;padding:0 10px;border:1.5px solid #d6dbe4;border-radius:6px;background:#fff;font-size:12.5px;color:#2f3b4c;display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:border-color .15s,box-shadow .15s;user-select:none}
.ss-display:hover,.ss-display.open{border-color:#1f7bff;box-shadow:0 0 0 3px rgba(31,123,255,.1)}
.ss-display-text{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ss-display-placeholder{color:#b8bfc9;font-size:12px}
.ss-arrow{font-size:9px;color:#6b7280;transition:transform .15s;flex-shrink:0;margin-left:6px}
.ss-display.open .ss-arrow{transform:rotate(180deg)}
.ss-panel{position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1.5px solid #1f7bff;border-radius:6px;box-shadow:0 6px 20px rgba(0,0,0,.12);z-index:3000;overflow:hidden;display:none}
.ss-panel.open{display:block}
.ss-search-input{width:100%;border:none;border-bottom:1.5px solid #e1e6ee;padding:7px 10px;font-size:12.5px;font-family:inherit;outline:none;background:#f8fafc;height:34px}
.ss-list{max-height:210px;overflow-y:auto}
.ss-opt{padding:8px 10px;font-size:12.5px;color:#2f3b4c;cursor:pointer;transition:background .1s;border-bottom:1px solid #f0f3f8}
.ss-opt:last-child{border-bottom:none}
.ss-opt:hover,.ss-opt.focused{background:#f0f6ff;color:#1f7bff}
.ss-opt.selected{background:#f0f6ff;font-weight:600}
.ss-opt-code{font-family:"Courier New",monospace;font-size:11px;color:#9aa1ae;margin-right:4px}
.ss-no-results{padding:12px 10px;font-size:12px;color:#9aa1ae;text-align:center}

/* ── ROW SEARCHABLE SELECT (fixed-position for table cells) ── */
.rss-wrap{position:relative;width:100%}
.rss-input{height:29px;padding:0 6px;border:1.5px solid #d6dbe4;border-radius:5px;font-size:12px;color:#2f3b4c;font-family:inherit;width:100%;background:#fff;transition:border-color .13s}
.rss-input:focus{outline:none;border-color:#1f7bff;box-shadow:0 0 0 2px rgba(31,123,255,.08)}
.rss-panel{position:fixed;background:#fff;border:1.5px solid #1f7bff;border-radius:6px;box-shadow:0 6px 20px rgba(0,0,0,.15);z-index:9999;display:none;overflow:hidden;min-width:300px}
.rss-panel.open{display:block}
.rss-list{max-height:200px;overflow-y:auto}
.rss-opt{padding:7px 10px;font-size:12px;color:#2f3b4c;cursor:pointer;transition:background .1s;border-bottom:1px solid #f0f3f8;white-space:nowrap}
.rss-opt:last-child{border-bottom:none}
.rss-opt:hover{background:#f0f6ff;color:#1f7bff}
.rss-opt-code{font-family:"Courier New",monospace;font-size:11px;color:#6b7280;margin-right:4px}
.rss-no-results{padding:10px;font-size:12px;color:#9aa1ae;text-align:center}

/* ── SMALL BUTTONS ────────────────────────────────────────── */
.btn-sm{height:33px;padding:0 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:5px;font-family:inherit;white-space:nowrap;transition:background .15s}
.btn-sm.primary{background:#1f7bff;color:#fff}
.btn-sm.primary:hover{background:#1a6cdc}
.btn-sm.outline{background:#fff;border:1.5px solid #d6dbe4;color:#2f3b4c}
.btn-sm.outline:hover{border-color:#1f7bff;color:#1f7bff;background:#f0f6ff}
.btn-sm.danger{background:#fff;border:1.5px solid #fecdd3;color:#e34f4f}
.btn-sm.danger:hover{background:#fef2f2}
.btn-sm.icon-only{width:33px;padding:0;justify-content:center}
.btn-sm:disabled{opacity:.5;cursor:not-allowed}

/* ── ITEMS TABLE ──────────────────────────────────────────── */
.items-wrap{padding:0 22px 16px}
.items-title{font-size:13px;font-weight:700;color:#1f7bff;padding:12px 0 10px;border-bottom:2px solid #1f7bff;margin-bottom:10px;letter-spacing:.3px}
.tbl-scroll{overflow-x:auto;border:1px solid #e1e6ee;border-radius:8px}
table{width:100%;border-collapse:collapse;min-width:1180px;font-size:12px}
thead tr{background:#f0f3f8;border-bottom:2px solid #e1e6ee}
thead th{padding:9px 6px;text-align:left;font-size:10px;font-weight:700;color:#5a6472;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
thead th.r{text-align:right}
tbody tr{border-bottom:1px solid #edf0f4;transition:background .1s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#fafbfd}
tbody td{padding:4px 4px;vertical-align:middle}

.ti{height:29px;padding:0 6px;border:1.5px solid #d6dbe4;border-radius:5px;font-size:12px;color:#2f3b4c;font-family:inherit;width:100%;background:#fff;transition:border-color .13s}
.ti:focus{outline:none;border-color:#1f7bff;box-shadow:0 0 0 2px rgba(31,123,255,.08)}
.ti[readonly]{background:#f2f4f8;color:#8a93a2;cursor:default}
.ts{height:29px;padding:0 20px 0 6px;border:1.5px solid #d6dbe4;border-radius:5px;font-size:12px;color:#2f3b4c;font-family:inherit;width:100%;appearance:none;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='9' height='9' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 5px center;cursor:pointer;transition:border-color .13s}
.ts:focus{outline:none;border-color:#1f7bff}

.qr-cell{display:flex;align-items:center;gap:3px}
.qr-cell .ts.pack{width:68px}
.qr-cell .ti.qty{width:48px}
.qr-cell .ts.qunit{width:72px}

.act-cell{display:flex;gap:3px;justify-content:center}
.bico{width:25px;height:25px;border:none;border-radius:5px;cursor:pointer;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;transition:opacity .15s,transform .1s;font-family:inherit}
.bico:hover{opacity:.8;transform:scale(1.08)}
.bico.add{background:#1f7bff;color:#fff}
.bico.del{background:#e34f4f;color:#fff}

.add-row-btn{display:inline-flex;align-items:center;gap:6px;margin-top:9px;background:none;border:2px dashed #1f7bff;border-radius:6px;padding:6px 16px;color:#1f7bff;font-family:inherit;font-size:12px;font-weight:600;cursor:pointer;transition:background .15s}
.add-row-btn:hover{background:#f0f6ff}

/* totals bar */
.totals-bar{display:flex;justify-content:flex-end;gap:6px;margin-top:10px;flex-wrap:wrap}
.tot-pill{background:#f0f3f8;border:1px solid #e1e6ee;border-radius:7px;padding:6px 14px;text-align:center;min-width:110px}
.tot-pill.grand{background:#1f7bff;border-color:#1f7bff}
.tot-pill .tl{font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px}
.tot-pill.grand .tl{color:rgba(255,255,255,.75)}
.tot-pill .tv{font-size:15px;font-weight:700;color:#0e1a2b;font-family:"Courier New",monospace}
.tot-pill.grand .tv{color:#fff}

/* ── FOOTER ───────────────────────────────────────────────── */
.footer{display:flex;justify-content:flex-end;gap:9px;padding:12px 22px;border-top:1px solid #e1e6ee;background:#fff;position:sticky;bottom:0}
.btn-main{height:37px;padding:0 20px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:7px;font-family:inherit;transition:all .15s}
.btn-main.primary{background:#1f7bff;color:#fff}
.btn-main.primary:hover{background:#1a6cdc}
.btn-main.sec{background:#eff2f7;border:1.5px solid #c9cfda;color:#2f3b4c}
.btn-main.sec:hover{background:#e4e8ef}
.btn-main:disabled{opacity:.5;cursor:not-allowed}

/* ── MODAL ────────────────────────────────────────────────── */
.modal-ov{display:none;position:fixed;inset:0;background:rgba(14,26,43,.45);z-index:9000;align-items:center;justify-content:center;backdrop-filter:blur(2px)}
.modal-ov.show{display:flex;animation:fadeIn .18s ease}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal-box{background:#fff;border-radius:10px;width:92%;max-width:500px;box-shadow:0 8px 32px rgba(0,0,0,.18);overflow:hidden;animation:slideUp .2s ease}
@keyframes slideUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.modal-head{padding:13px 20px;background:#1f7bff;color:#fff;display:flex;justify-content:space-between;align-items:center}
.modal-head h3{font-size:13.5px;font-weight:700;display:flex;align-items:center;gap:8px}
.mclose{background:rgba(255,255,255,.15);border:none;color:#fff;width:27px;height:27px;border-radius:6px;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;transition:background .15s}
.mclose:hover{background:rgba(255,255,255,.25)}
.modal-body{padding:18px 20px}
.modal-foot{padding:11px 20px;border-top:1px solid #e1e6ee;display:flex;justify-content:flex-end;gap:8px}
.terms-list{margin-top:13px;display:flex;flex-direction:column;gap:5px;max-height:200px;overflow-y:auto}
.term-item{display:flex;justify-content:space-between;align-items:center;padding:8px 11px;background:#fafbfd;border:1px solid #e1e6ee;border-radius:6px;font-size:12.5px;color:#2f3b4c}
.del-term{background:none;border:none;color:#e34f4f;cursor:pointer;padding:2px 5px;border-radius:4px;transition:background .13s;font-size:12px}
.del-term:hover{background:#fef2f2}
.empty-msg{text-align:center;color:#9aa1ae;font-size:12px;padding:18px 0}

/* spinner overlay */
.spinner-ov{display:none;position:fixed;inset:0;background:rgba(255,255,255,.55);z-index:9500;align-items:center;justify-content:center}
.spinner-ov.show{display:flex}
.spinner{width:38px;height:38px;border:4px solid #e1e6ee;border-top-color:#1f7bff;border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* toast */
.toast{position:fixed;top:16px;right:20px;padding:10px 18px;border-radius:7px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(0,0,0,.15);z-index:9999;color:#fff;animation:slideUp .22s ease}

.status-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.status-draft{background:#fef9c3;color:#a16207}

@media(max-width:900px){.fg-3,.fg-4,.fg-5{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){body{padding:8px}.fg-3,.fg-4,.fg-5,.fg-2{grid-template-columns:1fr}.header{flex-direction:column;align-items:flex-start;gap:8px}.items-wrap{padding:0 10px 12px}.form-area{padding:12px}}
</style>
</head>
<body>

<div class="container">

  <!-- ══ HEADER ══════════════════════════════════════════════ -->
  <div class="header">
    <div class="header-left">
      <h1><i class="fas fa-file-invoice"></i> New Quotation
        <span class="status-badge status-draft"><i class="fas fa-circle" style="font-size:7px"></i>Draft</span>
      </h1>
      <p>Fill in the details below. Fields marked <span style="color:#e34f4f">*</span> are required.</p>
    </div>
    <div class="qtn-chip">
      <div class="no" id="qtnNumberDisplay">Loading…</div>
      <div class="lbl">Quotation No.</div>
    </div>
  </div>

  <!-- ══ FORM AREA ═══════════════════════════════════════════ -->
  <div class="form-area">

    <!-- Quotation Details -->
    <div class="card">
      <div class="card-head"><i class="fas fa-info-circle"></i>Quotation Details</div>
      <div class="card-body">

        <!-- Row 1: Reference fields -->
        <div class="fg fg-4" style="margin-bottom:9px">
          <div class="f">
            <label>Quotation No</label>
            <input type="text" id="qtnNumber" readonly/>
          </div>
          <div class="f">
            <label>Date <span class="req">*</span></label>
            <input type="date" id="qtnDate"/>
          </div>
          <div class="f">
            <label>Valid Till</label>
            <input type="date" id="validTill"/>
          </div>
          <div class="f">
            <label>Contact Person</label>
            <input type="text" id="contactPerson" placeholder="Person name on quotation"/>
          </div>
        </div>

        <!-- Row 2: Customer, Party Type, Payment, Salesman -->
        <div class="fg fg-4" style="margin-bottom:9px">
          <div class="f">
            <label>Customer <span class="req">*</span>
              <button type="button" class="btn-sm outline icon-only" onclick="openAddCustModal()" title="Quick Add Customer" style="height:20px;width:20px;border-radius:4px;margin-left:4px">
                <i class="fas fa-plus" style="font-size:9px"></i>
              </button>
            </label>
            <!-- Searchable customer select -->
            <div class="ss-wrap" id="customerWrap">
              <input type="hidden" id="customerSel"/>
              <div class="ss-display" id="customerDisplay" onclick="toggleSS('customer')">
                <span class="ss-display-text ss-display-placeholder" id="customerDisplayText">Select Customer</span>
                <i class="fas fa-chevron-down ss-arrow" id="customerArrow"></i>
              </div>
              <div class="ss-panel" id="customerPanel">
                <input type="text" class="ss-search-input" id="customerSearch" placeholder="Type to search customer…" oninput="filterSS('customer', this.value)" autocomplete="off"/>
                <div class="ss-list" id="customerList"></div>
              </div>
            </div>
          </div>
          <div class="f">
            <label>Party Type</label>
            <select id="partyType">
              <option value="">Select Party Type</option>
              <option value="OEM">OEM</option>
              <option value="Vendor">Vendor</option>
              <option value="Customer">Customer</option>
            </select>
          </div>
          <div class="f">
            <label>Terms of Payment
              <button type="button" class="btn-sm outline icon-only" onclick="openTermsModal()" title="Manage Terms" style="height:20px;width:20px;border-radius:4px;margin-left:4px">
                <i class="fas fa-cog" style="font-size:9px"></i>
              </button>
            </label>
            <select id="paymentTermSel">
              <option value="">Select Payment Term</option>
            </select>
          </div>
          <div class="f">
            <label>Salesman</label>
            <select id="salesmanSel">
              <option value="">Select Salesman</option>
            </select>
          </div>
        </div>

        <!-- Row 3: Project -->
        <div class="fg fg-4" style="margin-bottom:9px">
          <div class="f">
            <label>Project</label>
            <select id="projectSel">
              <option value="">Select Project</option>
            </select>
          </div>
        </div>

        <!-- Row 4: Remarks (full width textarea) -->
        <div class="fg">
          <div class="f">
            <label>Remarks</label>
            <textarea id="remarks" class="remarks-ta" placeholder="Any remarks…"></textarea>
          </div>
        </div>

      </div>
    </div>

    <!-- Terms & Conditions -->
    <div class="card">
      <div class="card-head"><i class="fas fa-file-contract"></i>Terms &amp; Conditions</div>
      <div class="card-body" style="padding:9px 13px">
        <textarea id="termsConditions">AVAILABILITY: 05 Days after confirmation with purchase order
VALIDITY: This offer is valid for 5 days thereafter subject to our confirmation
PAYMENT TERMS: 100% Advance
PRICES: Ex-works and exclusive of unloading charges
TAX: Exclusive of all Tax</textarea>
      </div>
    </div>

    <!-- Footer Note -->
    <div class="card">
      <div class="card-head"><i class="fas fa-comment-alt"></i>Footer Note</div>
      <div class="card-body" style="padding:9px 13px">
        <textarea id="footerNote" placeholder="e.g. We thank you for giving us the opportunity of Mechanical Enclosure. We are pleased to submit our best Techno Grade offer as per the given details."></textarea>
      </div>
    </div>

  </div><!-- /form-area -->

  <!-- ══ ITEMS TABLE ════════════════════════════════════════ -->
  <div class="items-wrap">
    <div class="items-title"><i class="fas fa-boxes" style="margin-right:7px"></i>Quotation Items</div>
    <div class="tbl-scroll">
      <table>
        <thead>
          <tr>
            <th style="width:260px">Item (Code | Name)</th>
            <th style="width:140px">Description</th>
            <th style="width:100px">Size</th>
            <th style="width:80px">Qty</th>
            <th style="width:90px">Sale Unit</th>
            <th style="width:82px">Rate</th>
            <th class="r" style="width:90px">Excl. Tax</th>
            <th style="width:72px">ST %</th>
            <th class="r" style="width:88px">ST Amt</th>
            <th style="width:72px">FT %</th>
            <th class="r" style="width:88px">FT Amt</th>
            <th class="r" style="width:95px">Incl. Tax</th>
            <th class="r" style="width:95px">Amount</th>
            <th style="width:62px;text-align:center">Act</th>
          </tr>
        </thead>
        <tbody id="itemsTbody"></tbody>
      </table>
    </div>

    <!-- Totals -->
    <div class="totals-bar">
      <div class="tot-pill">
        <div class="tl">Subtotal</div>
        <div class="tv" id="totSubtotal">0.00</div>
      </div>
      <div class="tot-pill">
        <div class="tl">Sales Tax</div>
        <div class="tv" id="totST">0.00</div>
      </div>
      <div class="tot-pill">
        <div class="tl">Further Tax</div>
        <div class="tv" id="totFT">0.00</div>
      </div>
      <div class="tot-pill grand">
        <div class="tl">Grand Total</div>
        <div class="tv" id="totGrand">0.00</div>
      </div>
    </div>
  </div>

  <!-- ══ FOOTER ══════════════════════════════════════════════ -->
  <div class="footer">
    <button class="btn-main sec" onclick="window.location.href='/client/pages/sale/Quotation/quotation-list.php'">
      <i class="fas fa-list"></i> List
    </button>
    <button class="btn-main primary" id="saveBtn" onclick="saveQuotation()"><i class="fas fa-save"></i> Save</button>
  </div>

</div><!-- /container -->

<!-- ══ SPINNER ═════════════════════════════════════════════ -->
<div class="spinner-ov" id="spinnerOv"><div class="spinner"></div></div>

<!-- ══ TERMS MODAL ════════════════════════════════════════ -->
<div class="modal-ov" id="termsModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3><i class="fas fa-file-invoice-dollar"></i> Payment Terms</h3>
      <button class="mclose" onclick="closeModal('termsModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="f" style="margin-bottom:9px">
        <label>Add New Term</label>
        <div class="inline-row">
          <input type="text" id="newTermInput" placeholder="e.g. Net 45 Days, LC at Sight…" style="height:35px" onkeypress="if(event.key==='Enter')addTerm()"/>
          <button class="btn-sm primary" onclick="addTerm()" style="flex:0 0 auto"><i class="fas fa-plus"></i> Add</button>
        </div>
      </div>
      <div style="font-size:10.5px;color:#6b7280;margin-bottom:6px;font-weight:700;text-transform:uppercase;letter-spacing:.4px">Saved Terms</div>
      <div class="terms-list" id="termsList"></div>
    </div>
    <div class="modal-foot">
      <button class="btn-sm outline" onclick="closeModal('termsModal')">Close</button>
    </div>
  </div>
</div>

<!-- ══ QUICK-ADD CUSTOMER MODAL ════════════════════════════ -->
<div class="modal-ov" id="addCustModal">
  <div class="modal-box" style="max-width:520px">
    <div class="modal-head">
      <h3><i class="fas fa-user-plus"></i> Quick-Add Customer</h3>
      <button class="mclose" onclick="closeModal('addCustModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="fg fg-2" style="gap:11px">
        <div class="f">
          <label>Customer Name <span class="req">*</span></label>
          <input type="text" id="nc_name" placeholder="Full customer name" style="height:35px"/>
        </div>
        <div class="f">
          <label>Primary Phone <span class="req">*</span></label>
          <input type="text" id="nc_phone" placeholder="03XXXXXXXXX" style="height:35px"/>
        </div>
        <div class="f span2">
          <label>Pickup Address</label>
          <input type="text" id="nc_address" placeholder="Customer address" style="height:35px"/>
        </div>
        <div class="f">
          <label>Email</label>
          <input type="email" id="nc_email" placeholder="customer@mail.com" style="height:35px"/>
        </div>
        <div class="f">
          <label>Customer Type</label>
          <select id="nc_type" style="height:35px">
            <option value="account">Account</option>
            <option value="cod">COD</option>
            <option value="walk-in">Walk-in</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-sm outline" onclick="closeModal('addCustModal')">Cancel</button>
      <button class="btn-sm primary" id="saveCustBtn" onclick="saveNewCustomer()"><i class="fas fa-save"></i> Save Customer</button>
    </div>
  </div>
</div>

<!-- ══ JAVASCRIPT ══════════════════════════════════════════ -->
<script>
const API = location.pathname;
let customers = [];
let products  = [];
let units     = [];
let payTerms  = [];
let salesmen  = [];
let rowId = 0;

// ── INIT ─────────────────────────────────────────────────────
async function init() {
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('qtnDate').value = today;
  const plus7 = new Date(); plus7.setDate(plus7.getDate()+7);
  document.getElementById('validTill').value = plus7.toISOString().split('T')[0];

  showSpinner(true);
  await Promise.all([
    loadQtnNumber(), loadCustomers(), loadProducts(),
    loadUnits(), loadPaymentTerms(), loadSalesmen(), loadProjects()
  ]);
  showSpinner(false);
  addRow();
}

// ── API HELPER ────────────────────────────────────────────────
async function api(action, body={}) {
  const r = await fetch(`${API}?action=${action}`, {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body)
  });
  return r.json();
}

// ── LOADERS ───────────────────────────────────────────────────
async function loadQtnNumber() {
  const d = await api('next_number');
  if (d.success) {
    document.getElementById('qtnNumber').value = d.number;
    document.getElementById('qtnNumberDisplay').textContent = d.number;
  }
}

async function loadCustomers() {
  const d = await api('customers');
  if (!d.success) return;
  customers = d.data;
  buildCustomerSS();
}

async function loadProducts() {
  const d = await api('products');
  if (!d.success) return;
  products = d.data;
}

async function loadUnits() {
  const d = await api('units');
  if (!d.success) return;
  units = d.data;
}

async function loadPaymentTerms() {
  const d = await api('payment_terms');
  if (!d.success) return;
  payTerms = d.data;
  rebuildTermsDropdown();
  renderTermsList();
}

async function loadSalesmen() {
  const d = await api('salesmen');
  if (!d.success) return;
  salesmen = d.data;
  const sel = document.getElementById('salesmanSel');
  sel.innerHTML = '<option value="">Select Salesman</option>' +
    salesmen.map(e=>`<option value="${e.id}">${e.name}</option>`).join('');
}

async function loadProjects() {
  try {
    const r = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
    const d = await r.json();
    if (!d.success || !Array.isArray(d.projects)) return;
    const sel = document.getElementById('projectSel');
    sel.innerHTML = '<option value="">Select Project</option>' +
      d.projects.map(p => {
        const label = `${p.project_code} - ${p.project_name}${p.city_name ? ' - ' + p.city_name : ''}`;
        return `<option value="${p.id}">${label}</option>`;
      }).join('');
  } catch (e) { /* projects dropdown optional */ }
}

// ══ SEARCHABLE SELECT (header fields) ════════════════════════

// Build the customer searchable select options
function buildCustomerSS() {
  const list = document.getElementById('customerList');
  list.innerHTML = '';
  if (!customers.length) {
    list.innerHTML = '<div class="ss-no-results">No customers found</div>';
    return;
  }
  customers.forEach(c => {
    const opt = document.createElement('div');
    opt.className = 'ss-opt';
    opt.dataset.id     = c.id;
    opt.dataset.label  = `[${c.code}] ${c.name}`;
    opt.dataset.search = ((c.code || '') + ' ' + (c.name || '')).toLowerCase();
    opt.innerHTML = `<span class="ss-opt-code">[${escHtml(c.code)}]</span> ${escHtml(c.name)}`;
    opt.addEventListener('click', () => selectSS('customer', opt.dataset.id, opt.dataset.label));
    list.appendChild(opt);
  });
  const noRes = document.createElement('div');
  noRes.className = 'ss-no-results';
  noRes.textContent = 'No results found';
  noRes.style.display = 'none';
  list.appendChild(noRes);
}

function toggleSS(key) {
  const panel  = document.getElementById(key+'Panel');
  const display= document.getElementById(key+'Display');
  const isOpen = panel.classList.contains('open');
  closeAllSS();
  if (!isOpen) {
    panel.classList.add('open');
    display.classList.add('open');
    const si = document.getElementById(key+'Search');
    if (si) { si.value=''; filterSS(key,''); si.focus(); }
  }
}

function closeAllSS() {
  document.querySelectorAll('.ss-panel.open').forEach(p=>p.classList.remove('open'));
  document.querySelectorAll('.ss-display.open').forEach(d=>d.classList.remove('open'));
}

function filterSS(key, query) {
  const q = query.toLowerCase();
  let anyVisible = false;
  document.querySelectorAll(`#${key}List .ss-opt`).forEach(opt => {
    const show = opt.dataset.search.includes(q);
    opt.style.display = show ? '' : 'none';
    if (show) anyVisible = true;
  });
  const noRes = document.querySelector(`#${key}List .ss-no-results`);
  if (noRes) noRes.style.display = anyVisible ? 'none' : '';
}

function selectSS(key, value, label) {
  document.getElementById(key + 'Sel').value = value;
  const txt = document.getElementById(key + 'DisplayText');
  txt.textContent = label;
  txt.classList.remove('ss-display-placeholder');
  closeAllSS();
  if (key === 'customer') onCustomerChange();
}

function onCustomerChange() {
  // Customer Code/Address/Phone fields removed; nothing extra to fill
}

// Close searchable selects when clicking outside
document.addEventListener('click', e => {
  if (!e.target.closest('.ss-wrap')) closeAllSS();
});

// ── QUICK-ADD CUSTOMER ────────────────────────────────────────
function openAddCustModal() {
  ['nc_name','nc_phone','nc_address','nc_email'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('nc_type').value='account';
  openModal('addCustModal');
  setTimeout(()=>document.getElementById('nc_name').focus(),200);
}

async function saveNewCustomer() {
  const name  = document.getElementById('nc_name').value.trim();
  const phone = document.getElementById('nc_phone').value.trim();
  if (!name || !phone) { toast('Name and Phone are required','err'); return; }

  const btn = document.getElementById('saveCustBtn');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving…';

  const r = await fetch('../../customers/api/customer-add.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({
      customerName: name,
      primaryPhone: phone,
      address:      document.getElementById('nc_address').value.trim(),
      email:        document.getElementById('nc_email').value.trim(),
      customerType: document.getElementById('nc_type').value,
      companyId: 1
    })
  }).then(r=>r.json()).catch(()=>({success:false,message:'Network error'}));

  btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Save Customer';
  if (!r.success) { toast(r.message||'Save failed','err'); return; }

  toast('Customer added!');
  await loadCustomers();
  if (r.customer_id) {
    const cust = customers.find(c=>c.id==r.customer_id);
    if (cust) selectSS('customer', cust.id, `[${cust.code}] ${cust.name}`);
  }
  closeModal('addCustModal');
}

// ── PAYMENT TERMS ─────────────────────────────────────────────
function openTermsModal() {
  renderTermsList();
  openModal('termsModal');
  setTimeout(()=>document.getElementById('newTermInput').focus(),200);
}

async function addTerm() {
  const inp = document.getElementById('newTermInput');
  const val = inp.value.trim();
  if (!val) { inp.focus(); return; }
  const d = await api('add_term',{term_name:val});
  if (!d.success) { toast(d.message,'err'); return; }
  inp.value='';
  await loadPaymentTerms();
  document.getElementById('paymentTermSel').value = d.id;
}

async function deleteTerm(id) {
  if (!confirm('Delete this term?')) return;
  const d = await api('delete_term',{id});
  if (d.success) await loadPaymentTerms();
}

function rebuildTermsDropdown() {
  const sel = document.getElementById('paymentTermSel');
  const cur = sel.value;
  sel.innerHTML = '<option value="">Select Payment Term</option>' +
    payTerms.map(t=>`<option value="${t.id}">${t.term_name}</option>`).join('');
  sel.value = cur;
}

function renderTermsList() {
  const list = document.getElementById('termsList');
  if (!payTerms.length) {
    list.innerHTML='<div class="empty-msg"><i class="fas fa-inbox" style="font-size:20px;display:block;margin-bottom:6px"></i>No terms yet</div>';
    return;
  }
  list.innerHTML = payTerms.map(t=>`
    <div class="term-item">
      <span>${t.term_name}</span>
      ${t.id>7 ? `<button class="del-term" onclick="deleteTerm(${t.id})"><i class="fas fa-trash-alt"></i></button>` : '<span style="font-size:10px;color:#b0b8c4">Default</span>'}
    </div>`).join('');
}

// ══ ROW SEARCHABLE SELECT (fixed-position for table cells) ════

let activeRSSId = null;

function openRSS(id) {
  if (activeRSSId && activeRSSId !== id) closeRSS(activeRSSId);
  activeRSSId = id;
  const input = document.getElementById(`prod-display-${id}`);
  const panel = document.getElementById(`rss-panel-${id}`);
  if (!input || !panel) return;
  const rect = input.getBoundingClientRect();
  panel.style.top   = (rect.bottom + 2) + 'px';
  panel.style.left  = rect.left         + 'px';
  panel.style.width = Math.max(rect.width, 300) + 'px';
  panel.classList.add('open');
}

function closeRSS(id) {
  document.getElementById(`rss-panel-${id}`)?.classList.remove('open');
  if (activeRSSId === id) activeRSSId = null;
}

function filterRSS(id) {
  const q = (document.getElementById(`prod-display-${id}`)?.value || '').toLowerCase();
  const panel = document.getElementById(`rss-panel-${id}`);
  if (!panel) return;
  let anyVisible = false;
  panel.querySelectorAll('.rss-opt').forEach(opt => {
    const show = opt.dataset.search.includes(q);
    opt.style.display = show ? '' : 'none';
    if (show) anyVisible = true;
  });
  const noRes = panel.querySelector('.rss-no-results');
  if (noRes) noRes.style.display = anyVisible ? 'none' : '';
  openRSS(id);
}

function selectRSS(id, prodId) {
  const prod = products.find(p => p.id == prodId);
  if (!prod) return;
  document.getElementById(`prod-${id}`).value         = prod.id;
  document.getElementById(`prod-display-${id}`).value = `${prod.code} | ${prod.name}`;
  closeRSS(id);
  onProdFill(id, prod);
}

function onProdFill(id, prod) {
  const desc = document.getElementById(`desc-${id}`);
  if (!desc.value) desc.value = prod.description || '';
  document.getElementById(`rate-${id}`).value = prod.rate        || 0;
  document.getElementById(`stp-${id}`).value  = prod.sales_tax   || 0;
  document.getElementById(`ftp-${id}`).value  = prod.further_tax || 0;
  document.getElementById(`unit-${id}`).value = prod.unit_id || '';
  calcRow(id);
}

// Close row SS when clicking outside
document.addEventListener('click', e => {
  if (activeRSSId && !e.target.closest('.rss-wrap') && !e.target.closest('.rss-panel')) {
    closeRSS(activeRSSId);
  }
});
document.addEventListener('scroll', () => {
  if (activeRSSId) {
    const input = document.getElementById(`prod-display-${activeRSSId}`);
    const panel = document.getElementById(`rss-panel-${activeRSSId}`);
    if (input && panel) {
      const rect = input.getBoundingClientRect();
      panel.style.top  = (rect.bottom + 2) + 'px';
      panel.style.left = rect.left         + 'px';
    }
  }
}, true);

// ── ITEMS TABLE ───────────────────────────────────────────────
function buildRSSPanel(id) {
  const panel = document.createElement('div');
  panel.className = 'rss-panel';
  panel.id = `rss-panel-${id}`;

  const list = document.createElement('div');
  list.className = 'rss-list';

  products.forEach(p => {
    const opt = document.createElement('div');
    opt.className = 'rss-opt';
    opt.dataset.search = ((p.code || '') + '|' + (p.name || '')).toLowerCase();
    opt.innerHTML = `<span class="rss-opt-code">${escHtml(p.code)}</span> | ${escHtml(p.name)}`;
    opt.addEventListener('click', () => selectRSS(id, p.id));
    list.appendChild(opt);
  });

  const noRes = document.createElement('div');
  noRes.className = 'rss-no-results';
  noRes.textContent = 'No items found';
  noRes.style.display = products.length ? 'none' : '';
  list.appendChild(noRes);

  panel.appendChild(list);
  document.body.appendChild(panel);
  return panel;
}

function addRow() {
  rowId++;
  const id = rowId;
  const tr = document.createElement('tr');
  tr.id = `row-${id}`;
  tr.innerHTML = `
    <td>
      <div class="rss-wrap">
        <input type="hidden" id="prod-${id}"/>
        <input type="text" class="rss-input" id="prod-display-${id}"
               placeholder="Search item…" autocomplete="off"
               oninput="filterRSS(${id})"
               onfocus="openRSS(${id})"/>
      </div>
    </td>
    <td><input class="ti" id="desc-${id}" type="text" placeholder="Description"/></td>
    <td><input class="ti" id="size-${id}" type="text" placeholder="Size"/></td>
    <td>
      <input class="ti" id="qty-${id}" type="number" value="1" min="0" step="any" oninput="calcRow(${id})" style="text-align:right"/>
    </td>
    <td>
      <select class="ts unit-sel" id="unit-${id}">
        <option value="">Unit</option>
        ${units.map(u=>`<option value="${u.id}">${u.name}</option>`).join('')}
      </select>
    </td>
    <td><input class="ti" id="rate-${id}" type="number" placeholder="0.00" min="0" step="any" oninput="calcRow(${id})"/></td>
    <td><input class="ti" id="excl-${id}" type="number" placeholder="0.00" readonly style="text-align:right"/></td>
    <td><input class="ti" id="stp-${id}" type="number" placeholder="%" min="0" max="100" step="any" oninput="calcRow(${id})" style="width:60px"/></td>
    <td><input class="ti" id="sta-${id}" type="number" placeholder="0.00" readonly style="text-align:right"/></td>
    <td><input class="ti" id="ftp-${id}" type="number" placeholder="%" min="0" max="100" step="any" oninput="calcRow(${id})" style="width:60px"/></td>
    <td><input class="ti" id="fta-${id}" type="number" placeholder="0.00" readonly style="text-align:right"/></td>
    <td><input class="ti" id="incl-${id}" type="number" placeholder="0.00" readonly style="text-align:right"/></td>
    <td><input class="ti" id="amt-${id}"  type="number" placeholder="0.00" readonly style="text-align:right;font-weight:700"/></td>
    <td>
      <div class="act-cell">
        <button class="bico add" onclick="addRow()" title="Add row">+</button>
        <button class="bico del" onclick="removeRow(${id})" title="Remove">−</button>
      </div>
    </td>`;
  document.getElementById('itemsTbody').appendChild(tr);
  buildRSSPanel(id);
  setTimeout(()=>document.getElementById(`prod-display-${id}`)?.focus(), 80);
}

function removeRow(id) {
  const tb = document.getElementById('itemsTbody');
  if (tb.rows.length <= 1) { toast('At least one item is required','warn'); return; }
  document.getElementById(`row-${id}`)?.remove();
  document.getElementById(`rss-panel-${id}`)?.remove();
  updateTotals();
}

function calcRow(id) {
  const qty  = parseFloat(document.getElementById(`qty-${id}`)?.value)  || 0;
  const rate = parseFloat(document.getElementById(`rate-${id}`)?.value) || 0;
  const excl = qty * rate;
  const stp  = parseFloat(document.getElementById(`stp-${id}`)?.value)  || 0;
  const sta  = excl * stp / 100;
  const ftp  = parseFloat(document.getElementById(`ftp-${id}`)?.value)  || 0;
  const fta  = excl * ftp / 100;
  const incl = excl + sta + fta;
  setV(`excl-${id}`, excl.toFixed(2));
  setV(`sta-${id}`,  sta.toFixed(2));
  setV(`fta-${id}`,  fta.toFixed(2));
  setV(`incl-${id}`, incl.toFixed(2));
  setV(`amt-${id}`,  incl.toFixed(2));
  updateTotals();
}

function setV(id, v) { const el=document.getElementById(id); if(el) el.value=v; }

function updateTotals() {
  let sub=0, st=0, ft=0;
  document.querySelectorAll('#itemsTbody tr').forEach(tr => {
    const rid = parseInt(tr.id.replace('row-',''));
    if (!rid) return;
    sub += parseFloat(document.getElementById(`excl-${rid}`)?.value) || 0;
    st  += parseFloat(document.getElementById(`sta-${rid}`)?.value)  || 0;
    ft  += parseFloat(document.getElementById(`fta-${rid}`)?.value)  || 0;
  });
  const grand = sub + st + ft;
  document.getElementById('totSubtotal').textContent = fmt(sub);
  document.getElementById('totST').textContent       = fmt(st);
  document.getElementById('totFT').textContent       = fmt(ft);
  document.getElementById('totGrand').textContent    = fmt(grand);
}

function fmt(n){ return n.toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2}); }

// ── SAVE ──────────────────────────────────────────────────────
async function saveQuotation() {
  const custId = document.getElementById('customerSel').value;
  if (!custId) { toast('Please select a customer','err'); return; }

  const rows = document.querySelectorAll('#itemsTbody tr');
  if (!rows.length) { toast('Add at least one item','err'); return; }

  const items = [];
  let valid = true;
  rows.forEach(tr => {
    if (!valid) return;
    const rid     = parseInt(tr.id.replace('row-',''));
    const prodId  = document.getElementById(`prod-${rid}`)?.value;
    const display = document.getElementById(`prod-display-${rid}`)?.value || '';
    if (!prodId) { valid=false; toast('Select an item in every row','err'); return; }
    const qty  = parseFloat(document.getElementById(`qty-${rid}`)?.value) || 0;
    const rate = parseFloat(document.getElementById(`rate-${rid}`)?.value) || 0;
    if (qty <= 0 || rate <= 0) { valid=false; toast('Quantity and Rate must be > 0','err'); return; }
    const prod = products.find(p=>p.id==prodId);
    items.push({
      product_id:      prodId,
      item_code:       prod?.code  || '',
      item_name:       prod?.name  || display,
      description:     document.getElementById(`desc-${rid}`)?.value || '',
      size:            document.getElementById(`size-${rid}`)?.value || '',
      quantity:        qty,
      unit_id:         document.getElementById(`unit-${rid}`)?.value || null,
      rate,
      excl_tax:        parseFloat(document.getElementById(`excl-${rid}`)?.value) || 0,
      sales_tax_pct:   parseFloat(document.getElementById(`stp-${rid}`)?.value)  || 0,
      sales_tax_amt:   parseFloat(document.getElementById(`sta-${rid}`)?.value)  || 0,
      further_tax_pct: parseFloat(document.getElementById(`ftp-${rid}`)?.value)  || 0,
      further_tax_amt: parseFloat(document.getElementById(`fta-${rid}`)?.value)  || 0,
      incl_tax:        parseFloat(document.getElementById(`incl-${rid}`)?.value) || 0,
      line_total:      parseFloat(document.getElementById(`amt-${rid}`)?.value)  || 0,
    });
  });
  if (!valid || !items.length) return;

  const payload = {
    quotation_date:   document.getElementById('qtnDate').value,
    valid_till:       document.getElementById('validTill').value,
    customer_id:      custId,
    contact_person:   document.getElementById('contactPerson').value,
    party_type:       document.getElementById('partyType').value,
    salesman_id:      document.getElementById('salesmanSel').value,
    project_id:       document.getElementById('projectSel').value || null,
    payment_term_id:  document.getElementById('paymentTermSel').value,
    terms_conditions: document.getElementById('termsConditions').value,
    remarks:          document.getElementById('remarks').value,
    footer_note:      document.getElementById('footerNote').value,
    items,
  };

  const btn = document.getElementById('saveBtn');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving…';
  showSpinner(true);

  const d = await api('save', payload);
  showSpinner(false);
  btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Save';

  if (d.success) {
    toast(`Quotation ${d.quotation_number} saved successfully!`);
    document.getElementById('qtnNumber').value = d.quotation_number;
    document.getElementById('qtnNumberDisplay').textContent = d.quotation_number;
  } else {
    toast(d.message || 'Save failed','err');
  }
}

// ── UI UTILITIES ──────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function showSpinner(v) { document.getElementById('spinnerOv').classList.toggle('show',v); }

function toast(msg, type='ok') {
  const colors = {ok:'#2fbf71', err:'#e34f4f', warn:'#f59e0b'};
  const icons  = {ok:'check-circle', err:'exclamation-circle', warn:'exclamation-triangle'};
  const t = document.createElement('div');
  t.className='toast'; t.style.background=colors[type]||colors.ok;
  t.innerHTML=`<i class="fas fa-${icons[type]||icons.ok}"></i> ${msg}`;
  document.body.appendChild(t);
  setTimeout(()=>{ t.style.opacity='0'; t.style.transition='opacity .4s'; setTimeout(()=>t.remove(),400); },3000);
}

function escHtml(s)  { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escAttr(s)  { return String(s||'').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

document.querySelectorAll('.modal-ov').forEach(ov=>{
  ov.addEventListener('click', e=>{ if(e.target===ov) ov.classList.remove('show'); });
});
document.addEventListener('keydown', e=>{
  if(e.key==='Escape') document.querySelectorAll('.modal-ov.show').forEach(m=>m.classList.remove('show'));
});

// ── TERMS & CONDITIONS — localStorage persistence ─────────────
(function() {
  const ta  = document.getElementById('termsConditions');
  const key = 'qtn_terms_conditions';
  const saved = localStorage.getItem(key);
  if (saved !== null) ta.value = saved;
  ta.addEventListener('input', () => localStorage.setItem(key, ta.value));
})();

// ── FOOTER NOTE — localStorage persistence ────────────────────
(function() {
  const ta  = document.getElementById('footerNote');
  const key = 'qtn_footer_note';
  const saved = localStorage.getItem(key);
  if (saved !== null) ta.value = saved;
  ta.addEventListener('input', () => localStorage.setItem(key, ta.value));
})();

// ── BOOT ─────────────────────────────────────────────────────
init();
</script>
</body>
</html>
