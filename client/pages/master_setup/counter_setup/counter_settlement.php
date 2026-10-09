<?php
// Counter Settlement - single page (backend + frontend in one file)
// Opening cash given + cash sales = expected. Received / Adjustment entries reduce it. Remaining = Short.
date_default_timezone_set('Asia/Karachi');
require_once '../../../../includes/connection.php';
if (session_status() == PHP_SESSION_NONE) session_start();

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

/* Opening + sales + received + adjustment for one counter / officer / date */
function getSummary($pdo, $tenant_id, $counter_id, $officer_id, $date) {
    $q = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM counter_cash_entries
                        WHERE tenant_id = ? AND counter_id = ? AND sale_officer_id = ? AND entry_date = ?");
    $q->execute([$tenant_id, $counter_id, $officer_id, $date]);
    $opening = (float)$q->fetchColumn();

    $q = $pdo->prepare("SELECT COALESCE(SUM(rv.amount),0)
                        FROM receive_voucher rv
                        JOIN sale_invoice si ON si.bill_no = rv.bill_no AND si.tenant_id = rv.tenant_id
                        WHERE rv.tenant_id = ? AND rv.payment_method_id = 1 AND rv.counter_id = ?
                          AND (si.sale_officer_id = ? OR si.sale_officer_id IS NULL)
                          AND rv.voucher_date = ?");
    $q->execute([$tenant_id, $counter_id, $officer_id, $date]);
    $sales = (float)$q->fetchColumn();

    $q = $pdo->prepare("SELECT entry_type, COALESCE(SUM(amount),0) AS total FROM counter_settlements
                        WHERE tenant_id = ? AND counter_id = ? AND sale_officer_id = ? AND entry_date = ?
                        GROUP BY entry_type");
    $q->execute([$tenant_id, $counter_id, $officer_id, $date]);
    $received = 0; $adjusted = 0;
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['entry_type'] === 'Received')   $received = (float)$r['total'];
        if ($r['entry_type'] === 'Adjustment') $adjusted = (float)$r['total'];
    }
    $expected = $opening + $sales;
    return [
        'opening'    => $opening,
        'sales'      => $sales,
        'expected'   => $expected,
        'received'   => $received,
        'adjustment' => $adjusted,
        'short'      => $expected - $received - $adjusted   // > 0 short, < 0 excess
    ];
}

/* Validate and normalise the posted entry (shared by save + update) */
function readEntry($pdo, $tenant_id, $in = null) {
    if ($in === null) $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $e = [
        'id'         => intval($in['id'] ?? 0),
        'counter_id' => intval($in['counter_id'] ?? 0),
        'officer_id' => intval($in['sale_officer_id'] ?? 0),
        'date'       => $in['entry_date'] ?? date('Y-m-d'),
        'type'       => $in['entry_type'] ?? 'Received',
        'amount'     => round((float)($in['amount'] ?? 0), 2),
        'remarks'    => trim($in['remarks'] ?? ''),
    ];
    if (!$e['counter_id']) throw new Exception('Counter is required');
    if (!$e['officer_id']) throw new Exception('Sales officer is required');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $e['date'])) throw new Exception('Invalid date');
    if (!in_array($e['type'], ['Received', 'Adjustment'], true)) throw new Exception('Invalid type');
    if ($e['amount'] <= 0) throw new Exception('Amount must be greater than 0');
    if ($e['type'] === 'Adjustment' && $e['remarks'] === '') throw new Exception('Reason is required for adjustment');

    $cs = $pdo->prepare("SELECT company_id, branch_id FROM counters WHERE id = ? AND tenant_id = ?");
    $cs->execute([$e['counter_id'], $tenant_id]);
    $counter = $cs->fetch(PDO::FETCH_ASSOC);
    if (!$counter) throw new Exception('Invalid counter');
    $e['company_id'] = $counter['company_id'];
    $e['branch_id']  = $counter['branch_id'];

    $es = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND tenant_id = ?");
    $es->execute([$e['officer_id'], $tenant_id]);
    if (!$es->fetchColumn()) throw new Exception('Invalid sales officer');
    return $e;
}

/* ===================== BACKEND (AJAX) ===================== */
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if (!$user_id || !$tenant_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $action = $_GET['action'];

    try {
        if ($action === 'init') {
            $c = $pdo->prepare("SELECT c.id, c.counter_name, b.branch_name FROM counters c
                                LEFT JOIN branches b ON b.id = c.branch_id
                                WHERE c.tenant_id = ? AND c.is_active = 1 ORDER BY b.branch_name, c.counter_name");
            $c->execute([$tenant_id]);
            $e = $pdo->prepare("SELECT id, employee_id, full_name FROM employees
                                WHERE tenant_id = ? AND current_status = 'active' AND is_terminated = 0 ORDER BY full_name");
            $e->execute([$tenant_id]);
            echo json_encode(['success' => true, 'counters' => $c->fetchAll(PDO::FETCH_ASSOC), 'officers' => $e->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($action === 'summary') {
            $counter_id = intval($_GET['counter_id'] ?? 0);
            $officer_id = intval($_GET['sale_officer_id'] ?? 0);
            $date       = $_GET['date'] ?? date('Y-m-d');
            if (!$counter_id || !$officer_id) {
                echo json_encode(['success' => true, 'summary' => null]);
                exit;
            }
            echo json_encode(['success' => true, 'summary' => getSummary($pdo, $tenant_id, $counter_id, $officer_id, $date)]);
            exit;
        }

        if ($action === 'list') {
            $date       = !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
            $counter_id = !empty($_GET['counter_id']) ? intval($_GET['counter_id']) : null;
            $officer_id = !empty($_GET['sale_officer_id']) ? intval($_GET['sale_officer_id']) : null;

            $where  = ['s.tenant_id = ?', 's.entry_date = ?'];
            $params = [$tenant_id, $date];
            if ($counter_id) { $where[] = 's.counter_id = ?';      $params[] = $counter_id; }
            if ($officer_id) { $where[] = 's.sale_officer_id = ?'; $params[] = $officer_id; }

            $stmt = $pdo->prepare("
                SELECT s.id, s.counter_id, s.sale_officer_id, s.entry_date, s.entry_type, s.amount, s.remarks,
                       c.counter_name, e.full_name AS officer_name, e.employee_id AS officer_code
                FROM counter_settlements s
                LEFT JOIN counters  c ON c.id = s.counter_id
                LEFT JOIN employees e ON e.id = s.sale_officer_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY s.id DESC");
            $stmt->execute($params);
            echo json_encode(['success' => true, 'entries' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($action === 'save' || $action === 'save_multi' || $action === 'update' || $action === 'delete') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
        }

        if ($action === 'save') {
            $e = readEntry($pdo, $tenant_id);
            $stmt = $pdo->prepare("INSERT INTO counter_settlements
                (tenant_id, company_id, branch_id, counter_id, sale_officer_id, entry_date, entry_type, amount, remarks, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $e['company_id'], $e['branch_id'], $e['counter_id'], $e['officer_id'],
                $e['date'], $e['type'], $e['amount'], $e['remarks'] !== '' ? $e['remarks'] : null, $user_id]);
            echo json_encode(['success' => true, 'message' => $e['type'] . ' saved']);
            exit;
        }

        // One click: Received + Adjustment saved together (each becomes its own row)
        if ($action === 'save_multi') {
            $in  = json_decode(file_get_contents('php://input'), true) ?: [];
            $rec = round((float)($in['received'] ?? 0), 2);
            $adj = round((float)($in['adjustment'] ?? 0), 2);
            if ($rec <= 0 && $adj <= 0) throw new Exception('Enter received amount or adjustment amount');

            $rows = [];
            if ($rec > 0) $rows[] = readEntry($pdo, $tenant_id, array_merge($in, ['entry_type' => 'Received',   'amount' => $rec]));
            if ($adj > 0) $rows[] = readEntry($pdo, $tenant_id, array_merge($in, ['entry_type' => 'Adjustment', 'amount' => $adj]));

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO counter_settlements
                (tenant_id, company_id, branch_id, counter_id, sale_officer_id, entry_date, entry_type, amount, remarks, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($rows as $e) {
                $stmt->execute([$tenant_id, $e['company_id'], $e['branch_id'], $e['counter_id'], $e['officer_id'],
                    $e['date'], $e['type'], $e['amount'], $e['remarks'] !== '' ? $e['remarks'] : null, $user_id]);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Settlement saved']);
            exit;
        }

        if ($action === 'update') {
            $e = readEntry($pdo, $tenant_id);
            if (!$e['id']) throw new Exception('Invalid entry');
            $stmt = $pdo->prepare("UPDATE counter_settlements
                SET company_id = ?, branch_id = ?, counter_id = ?, sale_officer_id = ?, entry_date = ?,
                    entry_type = ?, amount = ?, remarks = ?
                WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$e['company_id'], $e['branch_id'], $e['counter_id'], $e['officer_id'], $e['date'],
                $e['type'], $e['amount'], $e['remarks'] !== '' ? $e['remarks'] : null, $e['id'], $tenant_id]);
            echo json_encode(['success' => true, 'message' => 'Entry updated']);
            exit;
        }

        if ($action === 'delete') {
            $in = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = intval($in['id'] ?? 0);
            if (!$id) throw new Exception('Invalid entry');
            $stmt = $pdo->prepare("DELETE FROM counter_settlements WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            echo json_encode(['success' => true, 'message' => 'Entry deleted']);
            exit;
        }

        throw new Exception('Unknown action');

    } catch (Exception $ex) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        if (http_response_code() < 400) http_response_code(400);
        echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
    }
    exit;
}

/* ===================== FRONTEND ===================== */
require_once '../../../../includes/dashboard.php';
if (!$user_id) { header('Location: ../../auth/login.html'); exit(); }
$stmt = $pdo->prepare("SELECT c.symbol FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_base_currency = 1");
$stmt->execute([$tenant_id]);
$sym = ($stmt->fetch()['symbol'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Counter Settlement</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #F7F9FC; color: #2F3B4C; }
        .page-wrap { padding: 24px; max-width: 1200px; margin: 0 auto; }
        .page-title { font-size: 22px; font-weight: 700; color: #0E1A2B; }
        .page-subtitle { font-size: 13px; color: #6B7280; margin: 2px 0 24px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.07); padding: 20px; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; align-items: end; }
        .form-label { display: block; font-size: 12px; font-weight: 500; color: #6B7280; margin-bottom: 5px; }
        .form-input { width: 100%; height: 38px; padding: 0 10px; border: 1px solid #E1E6EE; border-radius: 6px; font-size: 13px; background: white; }
        .form-input:focus { outline: none; border-color: #1F7BFF; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; padding: 0 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
        .btn-primary { background: #1F7BFF; color: white; }
        .btn-primary:hover { background: #1A6CDC; }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; }
        .btn-outline { background: white; color: #2F3B4C; border: 1px solid #E1E6EE; }
        .btn-outline:hover { background: #F7F9FC; }
        .btn-danger { background: #E34F4F; color: white; }
        .btn-sm { height: 28px; padding: 0 10px; font-size: 12px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .summary-card { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.07); padding: 16px 18px; }
        .summary-label { font-size: 12px; color: #6B7280; font-weight: 500; margin-bottom: 6px; }
        .summary-value { font-size: 20px; font-weight: 700; color: #0E1A2B; }
        .summary-card.green .summary-value { color: #2FBF71; }
        .summary-card.blue .summary-value { color: #1F7BFF; }
        .summary-card.amber .summary-value { color: #E8B23F; }
        .summary-card.red .summary-value { color: #E34F4F; }
        .summary-hint { font-size: 11px; color: #6B7280; margin-top: 4px; }
        .table-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px; }
        .table-title { font-size: 15px; font-weight: 600; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th { padding: 10px 12px; text-align: left; background: #F7F9FC; border-bottom: 1px solid #E1E6EE; font-weight: 600; color: #6B7280; font-size: 11px; text-transform: uppercase; }
        .table td { padding: 10px 12px; border-bottom: 1px solid #E1E6EE; }
        .table tr:hover td { background: #F7F9FC; }
        .table .num { text-align: right; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; }
        .badge-received { background: #D1FAE5; color: #065F46; }
        .badge-adjustment { background: #FEF3C7; color: #92400E; }
        .empty { text-align: center; padding: 40px; color: #6B7280; }
        .btn-amber { background: #E8B23F; color: #fff; }
        .btn-amber:hover { background: #D19F32; }
        .card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .card-title { font-size: 15px; font-weight: 600; }
        .summary-card.clickable { cursor: pointer; }
        .summary-card.clickable:hover { box-shadow: 0 4px 12px rgba(232,178,63,.25); }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1000; align-items: center; justify-content: center; padding: 16px; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: white; border-radius: 10px; width: 440px; max-width: 100%; padding: 24px; box-shadow: 0 8px 32px rgba(0,0,0,.18); }
        .modal-title { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
        .modal-sub { font-size: 12px; color: #6B7280; margin-bottom: 16px; }
        .modal-row { margin-bottom: 14px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
        .toast { position: fixed; bottom: 24px; right: 24px; background: #1F7BFF; color: white; padding: 12px 20px; border-radius: 8px; font-size: 13px; z-index: 9999; opacity: 0; transition: opacity .3s; }
        .toast.show { opacity: 1; }
        .toast.error { background: #E34F4F; }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="page-title"><i class="fas fa-hand-holding-dollar" style="color:#1F7BFF;margin-right:8px"></i>Counter Settlement</div>
    <div class="page-subtitle">Receive cash back from the sales officer, or record an adjustment (expenses taken from cash)</div>

    <!-- Selection -->
    <div class="card">
        <div class="form-grid">
            <div>
                <label class="form-label">Counter *</label>
                <select class="form-input" id="counterId"><option value="">Select Counter</option></select>
            </div>
            <div>
                <label class="form-label">Sales Officer *</label>
                <select class="form-input" id="officerId"><option value="">Select Sales Officer</option></select>
            </div>
            <div>
                <label class="form-label">Date *</label>
                <input type="date" class="form-input" id="entryDate">
            </div>
        </div>
    </div>

    <!-- Summary for selected counter / officer / date -->
    <div class="summary-grid" id="summaryGrid">
        <div class="summary-card"><div class="summary-label">Opening Cash Given</div><div class="summary-value" id="sOpening">-</div></div>
        <div class="summary-card green"><div class="summary-label">Cash Sales</div><div class="summary-value" id="sSales">-</div></div>
        <div class="summary-card blue"><div class="summary-label">Total To Receive</div><div class="summary-value" id="sExpected">-</div></div>
        <div class="summary-card green"><div class="summary-label">Received</div><div class="summary-value" id="sReceived">-</div></div>
        <div class="summary-card amber clickable" onclick="document.getElementById('aAmount').focus()" title="Click to enter adjustment"><div class="summary-label">Adjustments</div><div class="summary-value" id="sAdjust">-</div></div>
        <div class="summary-card" id="shortCard"><div class="summary-label" id="shortLabel">Short Cash</div><div class="summary-value" id="sShort">-</div><div class="summary-hint" id="shortHint"></div></div>
    </div>

    <!-- Settlement entry: Received + Adjustment in ONE form -->
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-hand-holding-dollar" style="color:#2FBF71;margin-right:6px"></i>Settle Cash</div></div>
        <div class="form-grid">
            <div>
                <label class="form-label">Received Amount</label>
                <input type="number" class="form-input" id="rAmount" min="0" step="0.01" placeholder="0.00">
            </div>
            <div>
                <label class="form-label">Adjustment Amount (expense / advance)</label>
                <input type="number" class="form-input" id="aAmount" min="0" step="0.01" placeholder="0.00">
            </div>
            <div>
                <label class="form-label" id="remarksLabel">Remarks / Reason</label>
                <input type="text" class="form-input" id="remarks" maxlength="255" placeholder="Reason is required if adjustment is entered">
            </div>
            <div>
                <input type="hidden" id="editId">
                <input type="hidden" id="editType">
                <button class="btn btn-primary" id="saveBtn"><i class="fas fa-save"></i> Save</button>
                <button class="btn btn-outline" id="cancelEditBtn" style="display:none;margin-left:6px">Cancel</button>
            </div>
        </div>
        <div class="summary-hint" id="afterHint" style="margin-top:10px;font-size:12px"></div>
    </div>

    <!-- Entries -->
    <div class="card">
        <div class="card-head"><div class="card-title">Settlement Entries (selected date)</div></div>
        <table class="table">
            <thead>
                <tr><th>#</th><th>Date</th><th>Counter</th><th>Sales Officer</th><th>Type</th><th class="num">Amount</th><th>Remarks / Reason</th><th>Action</th></tr>
            </thead>
            <tbody id="tableBody"><tr><td colspan="8" class="empty">Loading...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const API = 'counter_settlement.php';
const SYM = '<?= addslashes($sym) ?>';
let entriesCache = [];
let lastPending = 0;
const $ = id => document.getElementById(id);

function todayStr() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 10);
}
function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
function fmt(n) {
    return (SYM ? SYM + ' ' : '') + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function showToast(msg, isError = false) {
    const t = $('toast');
    t.textContent = msg;
    t.className = 'toast show' + (isError ? ' error' : '');
    setTimeout(() => t.classList.remove('show'), 3000);
}

async function loadInit() {
    const res = await fetch(API + '?action=init');
    const data = await res.json();
    if (!data.success) { showToast(data.message, true); return; }
    $('counterId').innerHTML = '<option value="">Select Counter</option>' + data.counters.map(c =>
        `<option value="${c.id}">${esc(c.counter_name)}${c.branch_name ? ' - ' + esc(c.branch_name) : ''}</option>`).join('');
    $('officerId').innerHTML = '<option value="">Select Sales Officer</option>' + data.officers.map(o =>
        `<option value="${o.id}">${esc(o.full_name)} (${esc(o.employee_id)})</option>`).join('');
}

function setSummary(s) {
    const ids = ['sOpening','sSales','sExpected','sReceived','sAdjust','sShort'];
    if (!s) {
        ids.forEach(id => $(id).textContent = '-');
        $('shortCard').className = 'summary-card';
        $('shortLabel').textContent = 'Short Cash';
        $('shortHint').textContent = 'Select counter and sales officer';
        lastPending = 0;
        return;
    }
    $('sOpening').textContent  = fmt(s.opening);
    $('sSales').textContent    = fmt(s.sales);
    $('sExpected').textContent = fmt(s.expected);
    $('sReceived').textContent = fmt(s.received);
    $('sAdjust').textContent   = fmt(s.adjustment);
    const short = parseFloat(s.short);
    lastPending = short > 0 ? short : 0;
    if (short > 0.004) {
        $('shortCard').className = 'summary-card red';
        $('shortLabel').textContent = 'Short Cash (Pending)';
        $('sShort').textContent = fmt(short);
        $('shortHint').textContent = 'Receive it, or enter an Adjustment to clear';
    } else if (short < -0.004) {
        $('shortCard').className = 'summary-card amber';
        $('shortLabel').textContent = 'Excess Cash';
        $('sShort').textContent = fmt(-short);
        $('shortHint').textContent = 'Received more than expected';
    } else {
        $('shortCard').className = 'summary-card green';
        $('shortLabel').textContent = 'Short Cash';
        $('sShort').textContent = fmt(0);
        $('shortHint').textContent = 'Fully settled';
    }
}

async function loadSummary() {
    const q = new URLSearchParams({ action: 'summary', counter_id: $('counterId').value, sale_officer_id: $('officerId').value, date: $('entryDate').value });
    const res = await fetch(API + '?' + q);
    const data = await res.json();
    setSummary(data.success ? data.summary : null);
    if (!$('editId').value && lastPending > 0) {
        $('rAmount').value = lastPending.toFixed(2);   // pre-fill pending amount
    }
    updateAfterHint();
}

async function loadEntries() {
    const q = new URLSearchParams({ action: 'list', date: $('entryDate').value || todayStr(), counter_id: $('counterId').value, sale_officer_id: $('officerId').value });
    const res = await fetch(API + '?' + q);
    const data = await res.json();
    const body = $('tableBody');
    entriesCache = (data.success && data.entries) ? data.entries : [];
    if (!entriesCache.length) {
        body.innerHTML = '<tr><td colspan="8" class="empty">No entries found</td></tr>';
        return;
    }
    body.innerHTML = entriesCache.map((r, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${esc(r.entry_date)}</td>
            <td><strong>${esc(r.counter_name || '-')}</strong></td>
            <td>${esc(r.officer_name || '-')} <span style="color:#6B7280">(${esc(r.officer_code || '')})</span></td>
            <td><span class="badge ${r.entry_type === 'Received' ? 'badge-received' : 'badge-adjustment'}">${esc(r.entry_type)}</span></td>
            <td class="num">${fmt(r.amount)}</td>
            <td>${esc(r.remarks || '')}</td>
            <td style="white-space:nowrap">
                <button class="btn btn-outline btn-sm" onclick="editEntry(${r.id})"><i class="fas fa-edit"></i> Edit</button>
                <button class="btn btn-danger btn-sm" style="margin-left:6px" onclick="deleteEntry(${r.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`).join('');
}

function refreshAll() { loadSummary(); loadEntries(); }

/* Live hint: what will remain after this save */
function updateAfterHint() {
    const el = $('afterHint');
    if ($('editId').value || !$('counterId').value || !$('officerId').value) { el.textContent = ''; return; }
    const rec = parseFloat($('rAmount').value) || 0;
    const adj = parseFloat($('aAmount').value) || 0;
    const left = lastPending - rec - adj;
    if (Math.abs(left) < 0.005)      { el.textContent = 'After saving: fully settled, no short cash'; el.style.color = '#2FBF71'; }
    else if (left > 0)               { el.textContent = 'After saving: Short Cash ' + fmt(left); el.style.color = '#E34F4F'; }
    else                             { el.textContent = 'After saving: Excess Cash ' + fmt(-left); el.style.color = '#E8B23F'; }
}
['rAmount', 'aAmount'].forEach(id => $(id).addEventListener('input', updateAfterHint));

function resetForm() {
    $('editId').value = '';
    $('editType').value = '';
    $('rAmount').value = '';
    $('aAmount').value = '';
    $('rAmount').disabled = false;
    $('aAmount').disabled = false;
    $('remarks').value = '';
    $('saveBtn').innerHTML = '<i class="fas fa-save"></i> Save';
    $('cancelEditBtn').style.display = 'none';
}

$('saveBtn').addEventListener('click', async () => {
    const editId = $('editId').value;
    const base = {
        counter_id: $('counterId').value,
        sale_officer_id: $('officerId').value,
        entry_date: $('entryDate').value,
        remarks: $('remarks').value.trim()
    };
    const rec = parseFloat($('rAmount').value) || 0;
    const adj = parseFloat($('aAmount').value) || 0;

    if (!base.counter_id) return showToast('Please select a counter', true);
    if (!base.sale_officer_id) return showToast('Please select a sales officer', true);
    if (!base.entry_date) return showToast('Please select a date', true);

    let url, payload;
    if (editId) {                                   // editing one existing row
        const type = $('editType').value;
        const amt = type === 'Adjustment' ? adj : rec;
        if (!(amt > 0)) return showToast('Enter a valid amount', true);
        if (type === 'Adjustment' && !base.remarks) return showToast('Reason is required for adjustment', true);
        url = API + '?action=update';
        payload = { ...base, id: editId, entry_type: type, amount: amt };
    } else {                                        // new: Received + Adjustment together
        if (!(rec > 0) && !(adj > 0)) return showToast('Enter received amount or adjustment amount', true);
        if (adj > 0 && !base.remarks) return showToast('Reason is required for adjustment', true);
        url = API + '?action=save_multi';
        payload = { ...base, received: rec, adjustment: adj };
    }

    const btn = $('saveBtn');
    btn.disabled = true;
    try {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const data = await res.json();
        showToast(data.message, !data.success);
        if (data.success) { resetForm(); refreshAll(); }
    } catch (e) {
        showToast('Network error', true);
    } finally {
        btn.disabled = false;
    }
});

function editEntry(id) {
    const r = entriesCache.find(x => x.id == id);
    if (!r) return;
    $('counterId').value = r.counter_id;
    $('officerId').value = r.sale_officer_id;
    $('entryDate').value = r.entry_date;
    $('editId').value = r.id;
    $('editType').value = r.entry_type;
    const isAdj = r.entry_type === 'Adjustment';
    $('rAmount').value = isAdj ? '' : r.amount;
    $('aAmount').value = isAdj ? r.amount : '';
    $('rAmount').disabled = isAdj;      // only the amount of this row can be edited
    $('aAmount').disabled = !isAdj;
    $('remarks').value = r.remarks || '';
    $('saveBtn').innerHTML = '<i class="fas fa-save"></i> Update ' + r.entry_type;
    $('cancelEditBtn').style.display = 'inline-flex';
    $('afterHint').textContent = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    refreshAll();
}

$('cancelEditBtn').addEventListener('click', () => { resetForm(); refreshAll(); });

async function deleteEntry(id) {
    if (!confirm('Delete this entry?')) return;
    const res = await fetch(API + '?action=delete', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id })
    });
    const data = await res.json();
    showToast(data.message, !data.success);
    if (data.success) refreshAll();
}

['counterId', 'officerId', 'entryDate'].forEach(id => $(id).addEventListener('change', () => {
    if (!$('editId').value) { $('rAmount').value = ''; $('aAmount').value = ''; }
    refreshAll();
}));

$('entryDate').value = todayStr();
loadInit().then(() => { setSummary(null); loadEntries(); });
</script>
</body>
</html>