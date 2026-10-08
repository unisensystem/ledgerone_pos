<?php
// Counter Cash - single page (backend + frontend in one file)
require_once '../../../../includes/connection.php';
if (session_status() == PHP_SESSION_NONE) session_start();

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

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
        // ---- Dropdown data: active counters + sales officers ----
        if ($action === 'init') {
            $c = $pdo->prepare("
                SELECT c.id, c.counter_name, b.branch_name
                FROM counters c
                LEFT JOIN branches b ON b.id = c.branch_id
                WHERE c.tenant_id = ? AND c.is_active = 1
                ORDER BY b.branch_name, c.counter_name
            ");
            $c->execute([$tenant_id]);

            $e = $pdo->prepare("
                SELECT id, employee_id, full_name
                FROM employees
                WHERE tenant_id = ? AND current_status = 'active' AND is_terminated = 0
                ORDER BY full_name
            ");
            $e->execute([$tenant_id]);

            echo json_encode([
                'success'  => true,
                'counters' => $c->fetchAll(PDO::FETCH_ASSOC),
                'officers' => $e->fetchAll(PDO::FETCH_ASSOC)
            ]);
            exit;
        }

        // ---- List entries ----
        if ($action === 'list') {
            $date       = !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
            $counter_id = !empty($_GET['counter_id']) ? intval($_GET['counter_id']) : null;
            $officer_id = !empty($_GET['sale_officer_id']) ? intval($_GET['sale_officer_id']) : null;

            $where  = ['x.tenant_id = ?', 'x.entry_date = ?'];
            $params = [$tenant_id, $date];
            if ($counter_id) { $where[] = 'x.counter_id = ?';      $params[] = $counter_id; }
            if ($officer_id) { $where[] = 'x.sale_officer_id = ?'; $params[] = $officer_id; }

            $stmt = $pdo->prepare("
                SELECT x.id, x.entry_date, x.payment_type, x.amount, x.remarks, x.created_at,
                       c.counter_name, e.full_name AS officer_name, e.employee_id AS officer_code
                FROM counter_cash_entries x
                LEFT JOIN counters  c ON c.id = x.counter_id
                LEFT JOIN employees e ON e.id = x.sale_officer_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY x.id DESC
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total = 0;
            foreach ($rows as $r) $total += (float)$r['amount'];

            echo json_encode(['success' => true, 'entries' => $rows, 'total' => $total]);
            exit;
        }

        // ---- Save entry ----
        if ($action === 'save') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $in = json_decode(file_get_contents('php://input'), true) ?: [];

            $counter_id = intval($in['counter_id'] ?? 0);
            $officer_id = intval($in['sale_officer_id'] ?? 0);
            $date       = $in['entry_date'] ?? date('Y-m-d');
            $amount     = round((float)($in['amount'] ?? 0), 2);
            $remarks    = trim($in['remarks'] ?? '');

            if (!$counter_id)  throw new Exception('Counter is required');
            if (!$officer_id)  throw new Exception('Sales officer is required');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new Exception('Invalid date');
            if ($amount <= 0)  throw new Exception('Cash amount must be greater than 0');

            // Counter must belong to this tenant
            $cs = $pdo->prepare("SELECT company_id, branch_id FROM counters WHERE id = ? AND tenant_id = ? AND is_active = 1");
            $cs->execute([$counter_id, $tenant_id]);
            $counter = $cs->fetch(PDO::FETCH_ASSOC);
            if (!$counter) throw new Exception('Invalid counter');

            // Officer must belong to this tenant
            $es = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND tenant_id = ?");
            $es->execute([$officer_id, $tenant_id]);
            if (!$es->fetchColumn()) throw new Exception('Invalid sales officer');

            $stmt = $pdo->prepare("
                INSERT INTO counter_cash_entries
                    (tenant_id, company_id, branch_id, counter_id, sale_officer_id, entry_date, payment_type, amount, remarks, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'Cash', ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id, $counter['company_id'], $counter['branch_id'],
                $counter_id, $officer_id, $date, $amount,
                $remarks !== '' ? $remarks : null, $user_id
            ]);

            echo json_encode(['success' => true, 'message' => 'Cash entry saved', 'id' => $pdo->lastInsertId()]);
            exit;
        }

        // ---- Delete entry ----
        if ($action === 'delete') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                throw new Exception('Method not allowed');
            }
            $in = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = intval($in['id'] ?? 0);
            if (!$id) throw new Exception('Invalid entry');

            $stmt = $pdo->prepare("DELETE FROM counter_cash_entries WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);

            echo json_encode(['success' => true, 'message' => 'Entry deleted']);
            exit;
        }

        throw new Exception('Unknown action');

    } catch (Exception $e) {
        if (http_response_code() < 400) http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/* ===================== FRONTEND ===================== */
require_once '../../../../includes/dashboard.php';
if (!$user_id) { header('Location: ../../auth/login.html'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Counter Cash</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #F7F9FC; color: #2F3B4C; }
        .page-wrap { padding: 24px; max-width: 1200px; margin: 0 auto; }
        .page-title { font-size: 22px; font-weight: 700; color: #0E1A2B; }
        .page-subtitle { font-size: 13px; color: #6B7280; margin: 2px 0 24px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.07); padding: 20px; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; align-items: end; }
        .form-label { display: block; font-size: 12px; font-weight: 500; color: #6B7280; margin-bottom: 5px; }
        .form-input { width: 100%; height: 38px; padding: 0 10px; border: 1px solid #E1E6EE; border-radius: 6px; font-size: 13px; background: white; }
        .form-input:focus { outline: none; border-color: #1F7BFF; }
        .form-input[readonly] { background: #F7F9FC; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; padding: 0 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
        .btn-primary { background: #1F7BFF; color: white; }
        .btn-primary:hover { background: #1A6CDC; }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; }
        .btn-danger { background: #E34F4F; color: white; height: 28px; padding: 0 10px; font-size: 12px; }
        .table-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px; }
        .table-title { font-size: 15px; font-weight: 600; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; }
        .filters .form-input { width: 170px; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th { padding: 10px 12px; text-align: left; background: #F7F9FC; border-bottom: 1px solid #E1E6EE; font-weight: 600; color: #6B7280; font-size: 11px; text-transform: uppercase; }
        .table td { padding: 10px 12px; border-bottom: 1px solid #E1E6EE; }
        .table tr:hover td { background: #F7F9FC; }
        .table .num { text-align: right; }
        .table tfoot td { font-weight: 700; background: #F7F9FC; border-bottom: none; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; background: #D1FAE5; color: #065F46; }
        .empty { text-align: center; padding: 40px; color: #6B7280; }
        .toast { position: fixed; bottom: 24px; right: 24px; background: #1F7BFF; color: white; padding: 12px 20px; border-radius: 8px; font-size: 13px; z-index: 9999; opacity: 0; transition: opacity .3s; }
        .toast.show { opacity: 1; }
        .toast.error { background: #E34F4F; }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="page-title">Counter Cash</div>
    <div class="page-subtitle">Select counter, sales officer and date to record cash</div>

    <!-- Entry form -->
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
            <div>
                <label class="form-label">Payment Type</label>
                <input type="text" class="form-input" id="paymentType" value="Cash" readonly>
            </div>
            <div>
                <label class="form-label">Cash Amount *</label>
                <input type="number" class="form-input" id="amount" min="0" step="0.01" placeholder="0.00">
            </div>
            <div>
                <label class="form-label">Remarks</label>
                <input type="text" class="form-input" id="remarks" maxlength="255" placeholder="Optional">
            </div>
            <div>
                <button class="btn btn-primary" id="saveBtn"><i class="fas fa-save"></i> Save Cash</button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="table-head">
            <div class="table-title">Cash Entries</div>
            <div class="filters">
                <input type="date" class="form-input" id="fDate">
                <select class="form-input" id="fCounter"><option value="">All Counters</option></select>
                <select class="form-input" id="fOfficer"><option value="">All Officers</option></select>
            </div>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th><th>Date</th><th>Counter</th><th>Sales Officer</th>
                    <th>Type</th><th class="num">Amount</th><th>Remarks</th><th>Action</th>
                </tr>
            </thead>
            <tbody id="tableBody"><tr><td colspan="8" class="empty">Loading...</td></tr></tbody>
            <tfoot id="tableFoot"></tfoot>
        </table>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const API = 'counter_cash.php';
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
    return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

    const cOpts = data.counters.map(c =>
        `<option value="${c.id}">${esc(c.counter_name)}${c.branch_name ? ' - ' + esc(c.branch_name) : ''}</option>`).join('');
    const oOpts = data.officers.map(o =>
        `<option value="${o.id}">${esc(o.full_name)} (${esc(o.employee_id)})</option>`).join('');

    $('counterId').innerHTML = '<option value="">Select Counter</option>' + cOpts;
    $('fCounter').innerHTML  = '<option value="">All Counters</option>' + cOpts;
    $('officerId').innerHTML = '<option value="">Select Sales Officer</option>' + oOpts;
    $('fOfficer').innerHTML  = '<option value="">All Officers</option>' + oOpts;
}

async function loadEntries() {
    const q = new URLSearchParams({
        action: 'list',
        date: $('fDate').value || todayStr(),
        counter_id: $('fCounter').value,
        sale_officer_id: $('fOfficer').value
    });
    const res = await fetch(API + '?' + q.toString());
    const data = await res.json();
    const body = $('tableBody'), foot = $('tableFoot');

    if (!data.success || !data.entries.length) {
        body.innerHTML = '<tr><td colspan="8" class="empty">No cash entries found</td></tr>';
        foot.innerHTML = '';
        return;
    }
    body.innerHTML = data.entries.map((r, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${esc(r.entry_date)}</td>
            <td><strong>${esc(r.counter_name || '-')}</strong></td>
            <td>${esc(r.officer_name || '-')} <span style="color:#6B7280">(${esc(r.officer_code || '')})</span></td>
            <td><span class="badge">${esc(r.payment_type)}</span></td>
            <td class="num">${fmt(r.amount)}</td>
            <td>${esc(r.remarks || '')}</td>
            <td><button class="btn btn-danger" onclick="deleteEntry(${r.id})"><i class="fas fa-trash"></i></button></td>
        </tr>`).join('');
    foot.innerHTML = `<tr><td colspan="5" class="num">Total Cash</td><td class="num">${fmt(data.total)}</td><td colspan="2"></td></tr>`;
}

$('saveBtn').addEventListener('click', async () => {
    const payload = {
        counter_id: $('counterId').value,
        sale_officer_id: $('officerId').value,
        entry_date: $('entryDate').value,
        amount: $('amount').value,
        remarks: $('remarks').value.trim()
    };
    if (!payload.counter_id) return showToast('Please select a counter', true);
    if (!payload.sale_officer_id) return showToast('Please select a sales officer', true);
    if (!payload.entry_date) return showToast('Please select a date', true);
    if (!(parseFloat(payload.amount) > 0)) return showToast('Enter a valid cash amount', true);

    const btn = $('saveBtn');
    btn.disabled = true;
    try {
        const res = await fetch(API + '?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        showToast(data.message, !data.success);
        if (data.success) {
            $('amount').value = '';
            $('remarks').value = '';
            $('fDate').value = payload.entry_date;   // show the date just saved
            loadEntries();
        }
    } catch (e) {
        showToast('Network error', true);
    } finally {
        btn.disabled = false;
    }
});

async function deleteEntry(id) {
    if (!confirm('Delete this cash entry?')) return;
    const res = await fetch(API + '?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    const data = await res.json();
    showToast(data.message, !data.success);
    if (data.success) loadEntries();
}

['fDate', 'fCounter', 'fOfficer'].forEach(id => $(id).addEventListener('change', loadEntries));

$('entryDate').value = todayStr();
$('fDate').value = todayStr();
loadInit().then(loadEntries);
</script>
</body>
</html>