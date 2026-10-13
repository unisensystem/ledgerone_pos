<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

if (session_status() == PHP_SESSION_NONE) session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit;
}

// ── AJAX actions ─────────────────────────────────────────────
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

        // ── UPDATE QUOTATION ──────────────────────────────────
        if ($action === 'update') {
            if (empty($input['quotation_id'])) throw new Exception('Quotation ID is required');
            if (empty($input['customer_id']))  throw new Exception('Customer is required');
            if (empty($input['items']))        throw new Exception('At least one item is required');

            $qid = (int)$input['quotation_id'];

            // Guard: confirm it exists, belongs to this tenant, and is editable
            $stChk = $pdo->prepare("SELECT * FROM quotations
                                     WHERE id=? AND tenant_id=? AND is_deleted=0");
            $stChk->execute([$qid, $tid]);
            $existing = $stChk->fetch(PDO::FETCH_ASSOC);
            if (!$existing) throw new Exception('Quotation not found');
            if ($existing['status'] === 'converted') throw new Exception('Cannot edit a converted quotation');

            // Snapshot old items for the Activity Log (table gets deleted+reinserted below)
            $stOldItems = $pdo->prepare("SELECT product_id, item_code, item_name, description, size, quantity, unit_id, rate, tax_pct, tax_amt, line_total
                                          FROM quotation_items WHERE quotation_id=? AND tenant_id=? ORDER BY sort_order");
            $stOldItems->execute([$qid, $tid]);
            $oldItems = $stOldItems->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals for database
            $itemsSubtotalWithTax = 0;
            foreach ($input['items'] as $item) {
                $qty = (float)($item['quantity'] ?? 1);
                $rate = (float)($item['rate'] ?? 0);
                $lineTotal = (float)($item['line_total'] ?? 0);
                $itemsSubtotalWithTax += $lineTotal;
            }
            
            $discountPct = (float)($input['discount_pct'] ?? 0);
            $discountAmt = $itemsSubtotalWithTax * $discountPct / 100;
            $afterDiscount = $itemsSubtotalWithTax - $discountAmt;
            
            $invoiceTaxPct = (float)($input['tax_pct'] ?? 0);
            $invoiceTaxAmt = $afterDiscount * $invoiceTaxPct / 100;
            
            $grandTotal = $afterDiscount + $invoiceTaxAmt;

            $pdo->beginTransaction();

            // Update header
            $stU = $pdo->prepare("UPDATE quotations SET
                quotation_date   = ?,
                valid_till       = ?,
                customer_id      = ?,
                contact_person   = ?,
                salesman_id      = ?,
                project_id       = ?,
                subject          = ?,
                payment_term_id  = ?,
                terms_conditions = ?,
                remarks          = ?,
                footer_note      = ?,
                party_type       = ?,
                currency_id      = ?,
                subtotal         = ?,
                discount_pct     = ?,
                discount_amt     = ?,
                invoice_tax_pct  = ?,
                invoice_tax_amt  = ?,
                grand_total      = ?,
                updated_by       = ?,
                updated_at       = NOW()
                WHERE id=? AND tenant_id=?");
            $stU->execute([
                $input['quotation_date'] ?: date('Y-m-d'),
                $input['valid_till']     ?: null,
                (int)$input['customer_id'],
                $input['contact_person'] ?: null,
                !empty($input['salesman_id'])     ? (int)$input['salesman_id']     : null,
                !empty($input['project_id'])      ? (int)$input['project_id']      : null,
                $input['subject']          ?: null,
                !empty($input['payment_term_id']) ? (int)$input['payment_term_id'] : null,
                $input['terms_conditions'] ?: null,
                $input['remarks']          ?: null,
                $input['footer_note']      ?: null,
                $input['party_type']       ?: null,
                !empty($input['currency_id']) ? (int)$input['currency_id'] : null,
                $itemsSubtotalWithTax, $discountPct, $discountAmt, $invoiceTaxPct, $invoiceTaxAmt, $grandTotal,
                $uid,
                $qid, $tid,
            ]);

            // Replace items: delete old, insert fresh
            $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id=? AND tenant_id=?")
                ->execute([$qid, $tid]);

            $si = $pdo->prepare("INSERT INTO quotation_items
                (quotation_id, tenant_id, sort_order, product_id, item_code, item_name,
                 description, size, quantity, unit_id, rate, tax_pct, tax_amt, line_total)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($input['items'] as $i => $it) {
                $si->execute([
                    $qid, $tid, $i + 1,
                    !empty($it['product_id']) ? (int)$it['product_id'] : null,
                    $it['item_code']   ?? null,
                    $it['item_name']   ?? '',
                    $it['description'] ?? null,
                    $it['size']        ?? null,
                    (float)($it['quantity'] ?? 1),
                    !empty($it['unit_id']) ? (int)$it['unit_id'] : null,
                    (float)($it['rate']     ?? 0),
                    (float)($it['tax_pct']  ?? 0),
                    (float)($it['tax_amt']  ?? 0),
                    (float)($it['line_total'] ?? 0),
                ]);
            }

            $newValues = [
                'quotation_date' => $input['quotation_date'] ?: date('Y-m-d'),
                'valid_till' => $input['valid_till'] ?: null,
                'customer_id' => (int)$input['customer_id'],
                'contact_person' => $input['contact_person'] ?: null,
                'salesman_id' => !empty($input['salesman_id']) ? (int)$input['salesman_id'] : null,
                'project_id' => !empty($input['project_id']) ? (int)$input['project_id'] : null,
                'subject' => $input['subject'] ?: null,
                'payment_term_id' => !empty($input['payment_term_id']) ? (int)$input['payment_term_id'] : null,
                'terms_conditions' => $input['terms_conditions'] ?: null,
                'remarks' => $input['remarks'] ?: null,
                'footer_note' => $input['footer_note'] ?: null,
                'party_type' => $input['party_type'] ?: null,
                'currency_id' => !empty($input['currency_id']) ? (int)$input['currency_id'] : null,
                'discount_pct' => $discountPct,
                'invoice_tax_pct' => $invoiceTaxPct,
                'grand_total' => $grandTotal,
            ];
            $changeDescriptions = buildChangeDescription($existing, $newValues, [
                'quotation_date' => 'Date', 'valid_till' => 'Valid Till', 'customer_id' => 'Customer',
                'contact_person' => 'Contact Person', 'salesman_id' => 'Salesman', 'project_id' => 'Project',
                'subject' => 'Subject', 'payment_term_id' => 'Payment Term', 'terms_conditions' => 'Terms & Conditions',
                'remarks' => 'Remarks', 'footer_note' => 'Footer Note', 'party_type' => 'Party Type',
                'currency_id' => 'Currency', 'discount_pct' => 'Discount %', 'invoice_tax_pct' => 'Tax %',
                'grand_total' => 'Grand Total'
            ]);
            $newItemsForCompare = array_map(function($it) {
                return [
                    'product_id' => $it['product_id'] ?? null, 'item_code' => $it['item_code'] ?? null,
                    'item_name' => $it['item_name'] ?? '', 'description' => $it['description'] ?? null,
                    'size' => $it['size'] ?? null, 'quantity' => (float)($it['quantity'] ?? 1),
                    'unit_id' => $it['unit_id'] ?? null, 'rate' => (float)($it['rate'] ?? 0),
                    'tax_pct' => (float)($it['tax_pct'] ?? 0), 'tax_amt' => (float)($it['tax_amt'] ?? 0),
                    'line_total' => (float)($it['line_total'] ?? 0),
                ];
            }, $input['items']);
            if (itemsChanged($oldItems, $newItemsForCompare, ['product_id','item_code','item_name','description','size','quantity','unit_id','rate','tax_pct','tax_amt','line_total'])) {
                $changeDescriptions[] = 'Item lines updated';
            }
            $editDescription = !empty($changeDescriptions)
                ? "Updated quotation \"{$existing['quotation_number']}\": " . implode('; ', $changeDescriptions)
                : "Updated quotation \"{$existing['quotation_number']}\" - no tracked field changes";
            logActivity($pdo, $tid, $uid, 'update', 'Quotation', $qid, $existing['quotation_number'], $editDescription, $existing, $newValues);

            $pdo->commit();
            resp(true, [], 'Quotation updated successfully');
        }

        // ── CUSTOMERS ─────────────────────────────────────────
        if ($action === 'customers') {
            $st = $pdo->prepare("SELECT id, customer_code AS code, customer_name AS name,
                                        primary_phone AS phone, address, email
                                  FROM customers WHERE tenant_id=? ORDER BY customer_name");
            $st->execute([$tid]);
            resp(true, ['data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ── PRODUCTS ──────────────────────────────────────────
        if ($action === 'products') {
            $st = $pdo->prepare("SELECT id, code, name, description,
                                        trade_price AS rate, sales_tax, further_tax,
                                        default_unit_id AS unit_id
                                  FROM products WHERE tenant_id=? AND is_active=1 ORDER BY name");
            $st->execute([$tid]);
            resp(true, ['data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ── SALESMEN ──────────────────────────────────────────
        if ($action === 'salesmen') {
            $st = $pdo->prepare("SELECT id, full_name AS name FROM employees
                                  WHERE tenant_id=? AND current_status='active' AND is_terminated=0
                                  ORDER BY full_name");
            $st->execute([$tid]);
            resp(true, ['data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ── PAYMENT TERMS ─────────────────────────────────────
        if ($action === 'payment_terms') {
            $st = $pdo->prepare("SELECT id, term_name FROM payment_terms
                                  WHERE (tenant_id=? OR tenant_id=0) AND is_active=1 ORDER BY id");
            $st->execute([$tid]);
            resp(true, ['data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ── ADD PAYMENT TERM ──────────────────────────────────
        if ($action === 'add_term') {
            if (empty($input['term_name'])) throw new Exception('Term name is required');
            $st = $pdo->prepare("INSERT INTO payment_terms (tenant_id, term_name, days) VALUES (?,?,?)");
            $st->execute([$tid, trim($input['term_name']), (int)($input['days'] ?? 0)]);
            resp(true, ['id' => $pdo->lastInsertId()], 'Term added');
        }

        // ── DELETE PAYMENT TERM ───────────────────────────────
        if ($action === 'delete_term') {
            $st = $pdo->prepare("DELETE FROM payment_terms WHERE id=? AND tenant_id=?");
            $st->execute([(int)$input['id'], $tid]);
            resp(true, [], 'Term deleted');
        }

        // ── CURRENCIES ────────────────────────────────────────
        if ($action === 'currencies') {
            $st = $pdo->prepare("
                SELECT tc.currency_id, c.name, c.symbol, c.code
                FROM tenant_currencies tc
                JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
                WHERE tc.tenant_id = ?
                ORDER BY c.name
            ");
            $st->execute([$tid]);
            resp(true, ['data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ── UNITS ─────────────────────────────────────────────
        if ($action === 'units') {
            $st = $pdo->prepare("SELECT id, uom_name AS name, uom_name AS symbol
                                  FROM uom WHERE (tenant_id=? OR tenant_id=0) ORDER BY uom_name");
            $st->execute([$tid]);
            resp(true, ['data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        resp(false, [], $e->getMessage());
    }
    exit;
}

// ── Load existing quotation for the page ─────────────────────
$qid = (int)($_GET['id'] ?? 0);
if (!$qid) { header('Location: quotation-list.php'); exit; }

$stQ = $pdo->prepare("SELECT q.*, c.customer_name, c.customer_code
                       FROM quotations q
                       LEFT JOIN customers c ON c.id = q.customer_id AND c.tenant_id = q.tenant_id
                       WHERE q.id=? AND q.tenant_id=? AND q.is_deleted=0");
$stQ->execute([$qid, $tenant_id]);
$quotation = $stQ->fetch(PDO::FETCH_ASSOC);
if (!$quotation) { header('Location: quotation-list.php'); exit; }
if ($quotation['status'] === 'converted') {
    header('Location: quotation-list.php?err=converted'); exit;
}

$stI = $pdo->prepare("SELECT * FROM quotation_items
                       WHERE quotation_id=? AND tenant_id=? ORDER BY sort_order");
$stI->execute([$qid, $tenant_id]);
$existingItems = $stI->fetchAll(PDO::FETCH_ASSOC);

require_once '../../../../includes/dashboard.php';

// Status badge config
$statusBadgeMap = [
    'draft'     => ['label'=>'Draft',     'class'=>'status-draft'],
    'sent'      => ['label'=>'Sent',      'class'=>'status-sent'],
    'approved'  => ['label'=>'Approved',  'class'=>'status-approved'],
    'rejected'  => ['label'=>'Rejected',  'class'=>'status-rejected'],
    'cancelled' => ['label'=>'Cancelled', 'class'=>'status-cancelled'],
];
$badge = $statusBadgeMap[$quotation['status']] ?? ['label'=>ucfirst($quotation['status']),'class'=>'status-draft'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Edit Quotation <?= htmlspecialchars($quotation['quotation_number']) ?> - LedgerOne ERP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── RESET & BASE ─────────────────────────────────────────── */
*{margin:0;padding:0;box-sizing:border-box;font-family:"Segoe UI",system-ui,sans-serif}
body{background:#f7f9fc;color:#2f3b4c;line-height:1.5;padding:20px}
.container{max-width:1420px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.08);overflow:hidden}

/* ── HEADER ──────────────────────────────────────────────── */
.header{padding:16px 26px;border-bottom:1px solid #e1e6ee;display:flex;justify-content:space-between;align-items:center;background:#fff;position:sticky;top:0;z-index:100}
.header-left h1{font-size:19px;font-weight:700;color:#0e1a2b;display:flex;align-items:center;gap:9px}
.header-left h1 i{color:#f59e0b;font-size:17px}
.header-left p{color:#6b7280;font-size:12px;margin-top:2px}
.qtn-chip{background:#fff8ed;border:1.5px solid #fde68a;border-radius:8px;padding:7px 18px;text-align:center;min-width:130px}
.qtn-chip .no{font-family:"Courier New",monospace;font-size:16px;font-weight:700;color:#d97706}
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

/* ── ROW SEARCHABLE SELECT ────────────────────────────────── */
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
.btn-sm.icon-only{width:33px;padding:0;justify-content:center}
.btn-sm:disabled{opacity:.5;cursor:not-allowed}

/* ── ITEMS TABLE ──────────────────────────────────────────── */
.items-wrap{padding:0 22px 16px}
.items-title{font-size:13px;font-weight:700;color:#1f7bff;padding:12px 0 10px;border-bottom:2px solid #1f7bff;margin-bottom:10px;letter-spacing:.3px}
.tbl-scroll{overflow-x:auto;border:1px solid #e1e6ee;border-radius:8px}
table{width:100%;border-collapse:collapse;min-width:1100px;font-size:12px}
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

.act-cell{display:flex;gap:3px;justify-content:center}
.bico{width:25px;height:25px;border:none;border-radius:5px;cursor:pointer;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;transition:opacity .15s,transform .1s;font-family:inherit}
.bico:hover{opacity:.8;transform:scale(1.08)}
.bico.add{background:#1f7bff;color:#fff}
.bico.del{background:#e34f4f;color:#fff}

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
.btn-main.primary{background:#f59e0b;color:#fff}
.btn-main.primary:hover{background:#d97706}
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

/* status badges */
.status-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.status-draft    {background:#fef9c3;color:#a16207}
.status-sent     {background:#e0f2fe;color:#0369a1}
.status-approved {background:#dcfce7;color:#166534}
.status-rejected {background:#fee2e2;color:#991b1b}
.status-cancelled{background:#f1f5f9;color:#475569}

@media(max-width:900px){.fg-3,.fg-4,.fg-5{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){body{padding:8px}.fg-3,.fg-4,.fg-5,.fg-2{grid-template-columns:1fr}.header{flex-direction:column;align-items:flex-start;gap:8px}.items-wrap{padding:0 10px 12px}.form-area{padding:12px}}
</style>
</head>
<body>

<!-- Inject server-side data for JS pre-population -->
<script>
const editData = {
  quotation: <?= json_encode($quotation, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>,
  items:     <?= json_encode($existingItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>
};
</script>

<div class="container">

  <!-- ══ HEADER ══════════════════════════════════════════════ -->
  <div class="header">
    <div class="header-left">
      <h1>
        <i class="fas fa-file-edit"></i> Edit Quotation
        <span class="status-badge <?= $badge['class'] ?>">
          <i class="fas fa-circle" style="font-size:7px"></i><?= $badge['label'] ?>
        </span>
      </h1>
      <p>Editing <strong><?= htmlspecialchars($quotation['quotation_number']) ?></strong> — changes will overwrite existing data.</p>
    </div>
    <div class="qtn-chip">
      <div class="no"><?= htmlspecialchars($quotation['quotation_number']) ?></div>
      <div class="lbl">Quotation No.</div>
    </div>
  </div>

  <!-- ══ FORM AREA ═══════════════════════════════════════════ -->
  <div class="form-area">

    <!-- Quotation Details -->
    <div class="card">
      <div class="card-head"><i class="fas fa-info-circle"></i>Quotation Details</div>
      <div class="card-body">

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

        <div class="fg fg-4" style="margin-bottom:9px">
          <div class="f">
            <label>Customer <span class="req">*</span></label>
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

        <!-- Currency, Project + Subject row -->
        <div class="fg fg-4" style="margin-bottom:9px">
          <div class="f">
            <label>Currency</label>
            <select id="currencySel">
              <option value="">Select Currency</option>
            </select>
          </div>
          <div class="f">
            <label>Project</label>
            <select id="projectSel">
              <option value="">Select Project</option>
            </select>
          </div>
          <div class="f span2">
            <label>Subject</label>
            <input type="text" id="subject" placeholder="e.g. Supply of Electrical Equipment"/>
          </div>
        </div>

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
        <textarea id="termsConditions"></textarea>
      </div>
    </div>

    <!-- Footer Note -->
    <div class="card">
      <div class="card-head"><i class="fas fa-comment-alt"></i>Footer Note</div>
      <div class="card-body" style="padding:9px 13px">
        <textarea id="footerNote" placeholder="e.g. We thank you for giving us the opportunity…"></textarea>
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
            <th class="r" style="width:95px">Rate</th>
            <th style="width:72px">Tax %</th>
            <th class="r" style="width:88px">Tax Amt</th>
            <th class="r" style="width:95px">Amount</th>
            <th style="width:62px;text-align:center">Act</th>
          </tr>
        </thead>
        <tbody id="itemsTbody"></tbody>
      </table>
    </div>

    <div class="totals-bar">
      <div class="tot-pill">
        <div class="tl">Subtotal</div>
        <div class="tv" id="totSubtotal">0.00</div>
      </div>
      <div class="tot-pill">
        <div class="tl">Discount %</div>
        <input type="number" id="discountPct" min="0" max="100" step="any" oninput="updateTotals()" placeholder="0" style="width:60px;height:24px;padding:0 6px;border:1px solid #d6dbe4;border-radius:4px;font-size:12px;text-align:center"/>
      </div>
      <div class="tot-pill">
        <div class="tl">Discount Amt</div>
        <div class="tv" id="totDiscount">0.00</div>
      </div>
      <div class="tot-pill">
        <div class="tl">Tax %</div>
        <input type="number" id="taxPct" min="0" max="100" step="any" oninput="updateTotals()" placeholder="0" style="width:60px;height:24px;padding:0 6px;border:1px solid #d6dbe4;border-radius:4px;font-size:12px;text-align:center"/>
      </div>
      <div class="tot-pill">
        <div class="tl">Tax Amt</div>
        <div class="tv" id="totTax">0.00</div>
      </div>
      <div class="tot-pill grand">
        <div class="tl">Grand Total</div>
        <div class="tv" id="totGrand">0.00</div>
      </div>
    </div>
  </div>

  <!-- ══ FOOTER ══════════════════════════════════════════════ -->
  <div class="footer">
    <button class="btn-main sec" onclick="window.location.href='quotation-list.php'">
      <i class="fas fa-list"></i> List
    </button>
    <button class="btn-main primary" id="saveBtn" onclick="updateQuotation()">
      <i class="fas fa-save"></i> Update
    </button>
  </div>

</div><!-- /container -->

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
          <input type="text" id="newTermInput" placeholder="e.g. Net 45 Days…" style="height:35px" onkeypress="if(event.key==='Enter')addTerm()"/>
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

<!-- ══ JAVASCRIPT ══════════════════════════════════════════ -->
<script>
const API = location.pathname;
let customers  = [];
let products   = [];
let units      = [];
let payTerms   = [];
let salesmen   = [];
let currencies = [];
let rowId      = 0;

// ── INIT ─────────────────────────────────────────────────────
async function init() {
  showSpinner(true);
  await Promise.all([
    loadCustomers(), loadProducts(),
    loadUnits(), loadPaymentTerms(), loadSalesmen(), loadProjects(), loadCurrencies()
  ]);
  populateForm();
  showSpinner(false);
}

// ── API HELPER ────────────────────────────────────────────────
async function api(action, body = {}) {
  const r = await fetch(`${API}?action=${action}`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return r.json();
}

// ── LOADERS ───────────────────────────────────────────────────
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
    salesmen.map(e => `<option value="${e.id}">${e.name}</option>`).join('');
}

async function loadCurrencies() {
  const d = await api('currencies');
  if (!d.success) return;
  currencies = d.data;
  const sel = document.getElementById('currencySel');
  sel.innerHTML = '<option value="">Select Currency</option>' +
    currencies.map(c =>
      `<option value="${c.currency_id}" data-symbol="${escHtml(c.symbol)}">${c.name} (${c.symbol}) - ${c.code}</option>`
    ).join('');
}

async function loadProjects() {
  const r = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
  const d = await r.json();
  if (!d.success || !Array.isArray(d.projects)) return;
  const sel = document.getElementById('projectSel');
  sel.innerHTML = '<option value="">Select Project</option>' +
    d.projects.map(p => {
      const label = `${p.project_code} - ${p.project_name}${p.city_name ? ' - ' + p.city_name : ''}`;
      return `<option value="${p.id}">${label}</option>`;
    }).join('');
}

// ── PRE-POPULATE FORM FROM editData ──────────────────────────
function populateForm() {
  const q = editData.quotation;

  document.getElementById('qtnNumber').value        = q.quotation_number;
  document.getElementById('qtnDate').value           = q.quotation_date || '';
  document.getElementById('validTill').value         = q.valid_till     || '';
  document.getElementById('contactPerson').value     = q.contact_person || '';
  document.getElementById('partyType').value         = q.party_type     || '';
  document.getElementById('salesmanSel').value       = q.salesman_id    || '';
  document.getElementById('projectSel').value         = q.project_id     || '';
  document.getElementById('subject').value            = q.subject        || '';
  document.getElementById('paymentTermSel').value    = q.payment_term_id|| '';
  document.getElementById('termsConditions').value   = q.terms_conditions|| '';
  document.getElementById('remarks').value           = q.remarks        || '';
  document.getElementById('footerNote').value        = q.footer_note    || '';
  document.getElementById('discountPct').value       = q.discount_pct    || 0;
  document.getElementById('taxPct').value            = q.invoice_tax_pct || 0;
  if (q.currency_id) document.getElementById('currencySel').value = q.currency_id;

  // Pre-select customer in searchable dropdown
  if (q.customer_id) {
    const cust = customers.find(c => c.id == q.customer_id);
    if (cust) {
      selectSS('customer', cust.id, `[${cust.code}] ${cust.name}`);
    } else {
      // Customer loaded but not found in list (edge case) — show stored name
      document.getElementById('customerSel').value = q.customer_id;
      const txt = document.getElementById('customerDisplayText');
      txt.textContent = q.customer_name || `Customer #${q.customer_id}`;
      txt.classList.remove('ss-display-placeholder');
    }
  }

  // Pre-populate items (skip auto-focus)
  editData.items.forEach(item => addRowWithData(item));

  // If no items came back, add one blank row
  if (!editData.items.length) addRow();
}

// ══ SEARCHABLE SELECT ════════════════════════════════════════

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
  const panel   = document.getElementById(key + 'Panel');
  const display = document.getElementById(key + 'Display');
  const isOpen  = panel.classList.contains('open');
  closeAllSS();
  if (!isOpen) {
    panel.classList.add('open');
    display.classList.add('open');
    const si = document.getElementById(key + 'Search');
    if (si) { si.value = ''; filterSS(key, ''); si.focus(); }
  }
}

function closeAllSS() {
  document.querySelectorAll('.ss-panel.open').forEach(p => p.classList.remove('open'));
  document.querySelectorAll('.ss-display.open').forEach(d => d.classList.remove('open'));
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
}

document.addEventListener('click', e => {
  if (!e.target.closest('.ss-wrap')) closeAllSS();
});

// ── PAYMENT TERMS ─────────────────────────────────────────────
function openTermsModal() {
  renderTermsList();
  openModal('termsModal');
  setTimeout(() => document.getElementById('newTermInput').focus(), 200);
}

async function addTerm() {
  const inp = document.getElementById('newTermInput');
  const val = inp.value.trim();
  if (!val) { inp.focus(); return; }
  const d = await api('add_term', { term_name: val });
  if (!d.success) { toast(d.message, 'err'); return; }
  inp.value = '';
  await loadPaymentTerms();
  document.getElementById('paymentTermSel').value = d.id;
}

async function deleteTerm(id) {
  if (!confirm('Delete this term?')) return;
  const d = await api('delete_term', { id });
  if (d.success) await loadPaymentTerms();
}

function rebuildTermsDropdown() {
  const sel = document.getElementById('paymentTermSel');
  const cur = sel.value;
  sel.innerHTML = '<option value="">Select Payment Term</option>' +
    payTerms.map(t => `<option value="${t.id}">${t.term_name}</option>`).join('');
  sel.value = cur;
}

function renderTermsList() {
  const list = document.getElementById('termsList');
  if (!payTerms.length) {
    list.innerHTML = '<div class="empty-msg"><i class="fas fa-inbox" style="font-size:20px;display:block;margin-bottom:6px"></i>No terms yet</div>';
    return;
  }
  list.innerHTML = payTerms.map(t => `
    <div class="term-item">
      <span>${t.term_name}</span>
      ${t.id > 7 ? `<button class="del-term" onclick="deleteTerm(${t.id})"><i class="fas fa-trash-alt"></i></button>` : '<span style="font-size:10px;color:#b0b8c4">Default</span>'}
    </div>`).join('');
}

// ══ ROW SEARCHABLE SELECT ════════════════════════════════════

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
  const q     = (document.getElementById(`prod-display-${id}`)?.value || '').toLowerCase();
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
  document.getElementById(`rate-${id}`).value = prod.rate || 0;
  document.getElementById(`tax-pct-${id}`).value = prod.sales_tax || 0;
  document.getElementById(`unit-${id}`).value = prod.unit_id || '';
  calcRow(id);
}

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

function addRow(autoFocus = true) {
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
               oninput="filterRSS(${id})" onfocus="openRSS(${id})"/>
      </div>
    </td>
    <td><input class="ti" id="desc-${id}" type="text" placeholder="Description"/></td>
    <td><input class="ti" id="size-${id}" type="text" placeholder="Size"/></td>
    <td><input class="ti" id="qty-${id}" type="number" value="1" min="0" step="any" oninput="calcRow(${id})" style="text-align:right"/></td>
    <td>
      <select class="ts" id="unit-${id}">
        <option value="">Unit</option>
        ${units.map(u => `<option value="${u.id}">${u.name}</option>`).join('')}
      </select>
    </td>
    <td><input class="ti" id="rate-${id}" type="number" placeholder="0.00" min="0" step="any" oninput="calcRow(${id})" style="text-align:right"/></td>
    <td><input class="ti" id="tax-pct-${id}" type="number" placeholder="%" min="0" max="100" step="any" oninput="calcRow(${id})" style="width:60px"/></td>
    <td><input class="ti" id="tax-amt-${id}" type="number" placeholder="0.00" readonly style="text-align:right"/></td>
    <td><input class="ti" id="amt-${id}"  type="number" placeholder="0.00" readonly style="text-align:right;font-weight:700"/></td>
    <td>
      <div class="act-cell">
        <button class="bico add" onclick="addRow()" title="Add row">+</button>
        <button class="bico del" onclick="removeRow(${id})" title="Remove">−</button>
      </div>
    </td>`;
  document.getElementById('itemsTbody').appendChild(tr);
  buildRSSPanel(id);
  if (autoFocus) setTimeout(() => document.getElementById(`prod-display-${id}`)?.focus(), 80);
}

// Pre-populate a row with existing item data
function addRowWithData(item) {
  addRow(false); // no auto-focus when bulk-loading
  const id = rowId;

  // Product display
  if (item.product_id) {
    document.getElementById(`prod-${id}`).value = item.product_id;
    const displayText = item.item_code
      ? `${item.item_code} | ${item.item_name}`
      : (item.item_name || '');
    document.getElementById(`prod-display-${id}`).value = displayText;
  }

  document.getElementById(`desc-${id}`).value  = item.description    || '';
  document.getElementById(`size-${id}`).value  = item.size           || '';
  document.getElementById(`qty-${id}`).value   = item.quantity        || 1;
  document.getElementById(`unit-${id}`).value  = item.unit_id         || '';
  document.getElementById(`rate-${id}`).value  = item.rate            || 0;
  document.getElementById(`tax-pct-${id}`).value = item.tax_pct       || 0;

  // Set computed readonly fields directly
  setV(`tax-amt-${id}`, parseFloat(item.tax_amt || 0).toFixed(2));
  setV(`amt-${id}`,     parseFloat(item.line_total || 0).toFixed(2));

  updateTotals();
}

function removeRow(id) {
  const tb = document.getElementById('itemsTbody');
  if (tb.rows.length <= 1) { toast('At least one item is required', 'warn'); return; }
  document.getElementById(`row-${id}`)?.remove();
  document.getElementById(`rss-panel-${id}`)?.remove();
  updateTotals();
}

function calcRow(id) {
  const qty     = parseFloat(document.getElementById(`qty-${id}`)?.value)     || 0;
  const rate    = parseFloat(document.getElementById(`rate-${id}`)?.value)    || 0;
  const taxPct  = parseFloat(document.getElementById(`tax-pct-${id}`)?.value) || 0;
  
  const subtotal = qty * rate;
  const taxAmt   = subtotal * taxPct / 100;
  const total    = subtotal + taxAmt;
  
  setV(`tax-amt-${id}`, taxAmt.toFixed(2));
  setV(`amt-${id}`, total.toFixed(2));
  updateTotals();
}

function setV(id, v) { const el = document.getElementById(id); if (el) el.value = v; }

function updateTotals() {
  // Calculate items with their taxes
  let itemsSubtotalWithTax = 0;  // Subtotal including item-level taxes
  
  document.querySelectorAll('#itemsTbody tr').forEach(tr => {
    const rid = parseInt(tr.id.replace('row-',''));
    if (!rid) return;
    const amt = parseFloat(document.getElementById(`amt-${rid}`)?.value) || 0;  // This is qty*rate + tax
    itemsSubtotalWithTax += amt;
  });
  
  // Discount on subtotal (before invoice-level tax)
  const discountPct = parseFloat(document.getElementById('discountPct')?.value) || 0;
  const discountAmt = itemsSubtotalWithTax * discountPct / 100;
  const afterDiscount = itemsSubtotalWithTax - discountAmt;
  
  // Invoice-level tax on after-discount amount
  const invoiceTaxPct = parseFloat(document.getElementById('taxPct')?.value) || 0;
  const invoiceTaxAmt = afterDiscount * invoiceTaxPct / 100;
  
  // Grand total = after discount + invoice tax
  const grandTotal = afterDiscount + invoiceTaxAmt;
  
  document.getElementById('totSubtotal').textContent = fmt(itemsSubtotalWithTax);
  document.getElementById('totDiscount').textContent = fmt(discountAmt);
  document.getElementById('totTax').textContent = fmt(invoiceTaxAmt);
  document.getElementById('totGrand').textContent = fmt(grandTotal);
}

function fmt(n) { return n.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

// ── UPDATE ────────────────────────────────────────────────────
async function updateQuotation() {
  const custId = document.getElementById('customerSel').value;
  if (!custId) { toast('Please select a customer', 'err'); return; }

  const rows = document.querySelectorAll('#itemsTbody tr');
  if (!rows.length) { toast('Add at least one item', 'err'); return; }

  const items = [];
  let valid = true;
  rows.forEach(tr => {
    if (!valid) return;
    const rid    = parseInt(tr.id.replace('row-', ''));
    const prodId = document.getElementById(`prod-${rid}`)?.value;
    if (!prodId) { valid = false; toast('Select an item in every row', 'err'); return; }
    const qty  = parseFloat(document.getElementById(`qty-${rid}`)?.value)  || 0;
    const rate = parseFloat(document.getElementById(`rate-${rid}`)?.value) || 0;
    if (qty <= 0 || rate <= 0) { valid = false; toast('Quantity and Rate must be > 0', 'err'); return; }
    const prod = products.find(p => p.id == prodId);
    const taxPct  = parseFloat(document.getElementById(`tax-pct-${rid}`)?.value)  || 0;
    const taxAmt  = parseFloat(document.getElementById(`tax-amt-${rid}`)?.value)  || 0;
    const amt     = parseFloat(document.getElementById(`amt-${rid}`)?.value)      || 0;
    items.push({
      product_id:      prodId,
      item_code:       prod?.code  || '',
      item_name:       prod?.name  || document.getElementById(`prod-display-${rid}`)?.value || '',
      description:     document.getElementById(`desc-${rid}`)?.value  || '',
      size:            document.getElementById(`size-${rid}`)?.value  || '',
      quantity:        qty,
      unit_id:         document.getElementById(`unit-${rid}`)?.value  || null,
      rate,
      tax_pct:         taxPct,
      tax_amt:         taxAmt,
      line_total:      amt,
    });
  });
  if (!valid || !items.length) return;

  const payload = {
    quotation_id:     editData.quotation.id,
    quotation_date:   document.getElementById('qtnDate').value,
    valid_till:       document.getElementById('validTill').value,
    customer_id:      custId,
    contact_person:   document.getElementById('contactPerson').value,
    party_type:       document.getElementById('partyType').value,
    salesman_id:      document.getElementById('salesmanSel').value,
    project_id:       document.getElementById('projectSel').value || null,
    subject:          document.getElementById('subject').value || null,
    payment_term_id:  document.getElementById('paymentTermSel').value,
    terms_conditions: document.getElementById('termsConditions').value,
    remarks:          document.getElementById('remarks').value,
    footer_note:      document.getElementById('footerNote').value,
    currency_id:      document.getElementById('currencySel').value || null,
    discount_pct:     parseFloat(document.getElementById('discountPct').value) || 0,
    tax_pct:          parseFloat(document.getElementById('taxPct').value) || 0,
    items,
  };

  const btn = document.getElementById('saveBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating…';
  showSpinner(true);

  const d = await api('update', payload);
  showSpinner(false);
  btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Update';

  if (d.success) {
    toast('Quotation updated successfully!');
  } else {
    toast(d.message || 'Update failed', 'err');
  }
}

// ── UI UTILITIES ──────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function showSpinner(v) { document.getElementById('spinnerOv').classList.toggle('show', v); }

function toast(msg, type = 'ok') {
  const colors = { ok: '#2fbf71', err: '#e34f4f', warn: '#f59e0b' };
  const icons  = { ok: 'check-circle', err: 'exclamation-circle', warn: 'exclamation-triangle' };
  const t = document.createElement('div');
  t.className = 'toast'; t.style.background = colors[type] || colors.ok;
  t.innerHTML = `<i class="fas fa-${icons[type] || icons.ok}"></i> ${msg}`;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .4s'; setTimeout(() => t.remove(), 400); }, 3000);
}

function escHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

document.querySelectorAll('.modal-ov').forEach(ov => {
  ov.addEventListener('click', e => { if (e.target === ov) ov.classList.remove('show'); });
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-ov.show').forEach(m => m.classList.remove('show'));
});

// ── BOOT ─────────────────────────────────────────────────────
init();
</script>
</body>
</html>
