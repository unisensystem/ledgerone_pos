<?php
/**
 * LedgerOne ERP - General Ledger
 * API: general-ledger.php?action=meta | action=data
 * data filters: from, to, account_id, head_id, type, q, hide_zero, no_jv, no_tv
 */

// ───────────────────────── BACKEND ─────────────────────────
if (isset($_GET['action'])) {
    require_once '../../../../includes/connection.php';
    if (session_status() == PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json');

    $user_id   = $_SESSION['user_id']   ?? null;
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    if (!$user_id || !$tenant_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
        exit;
    }

    try {
        $action = $_GET['action'];

        $accSql = "SELECT a.id, a.name, sa.name AS sub_name, sa.account_head_id AS head_id, ah.name AS head_name
                   FROM accounts a
                   LEFT JOIN sub_accounts sa ON sa.id = a.sub_account_id
                   LEFT JOIN accounts_head ah ON ah.id = sa.account_head_id
                   WHERE a.tenant_id = ? OR a.tenant_id = 0
                   ORDER BY a.id";
        $stmt = $pdo->prepare($accSql);
        $stmt->execute([$tenant_id]);
        $accountRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($action === 'meta') {
            $heads = $pdo->prepare("SELECT id, name FROM accounts_head WHERE tenant_id = ? OR tenant_id = 0 ORDER BY id");
            $heads->execute([$tenant_id]);

            $types = $pdo->prepare("SELECT DISTINCT transaction_type FROM accounting_ledger WHERE tenant_id = ? AND reference_table IN ('accounts','transfer_voucher','journal_voucher') ORDER BY transaction_type");
            $types->execute([$tenant_id]);

            echo json_encode([
                'success' => true,
                'accounts' => $accountRows,
                'heads' => $heads->fetchAll(PDO::FETCH_ASSOC),
                'types' => $types->fetchAll(PDO::FETCH_COLUMN)
            ]);
            exit;
        }

        if ($action === 'data') {
            $isDate = function ($d) { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d); };
            $from = $_GET['from'] ?? '';
            $to   = $_GET['to']   ?? '';
            if ($from && !$isDate($from)) $from = '';
            if ($to   && !$isDate($to))   $to   = '';
            $accountId = intval($_GET['account_id'] ?? 0);
            $headId    = intval($_GET['head_id'] ?? 0);
            $type      = trim($_GET['type'] ?? '');
            $q         = trim($_GET['q'] ?? '');
            $hideZero  = ($_GET['hide_zero'] ?? '1') === '1';
            $noJv      = ($_GET['no_jv'] ?? '0') === '1'; // without journal voucher
            $noTv      = ($_GET['no_tv'] ?? '0') === '1'; // without transfer voucher

            $accMap = [];
            foreach ($accountRows as $r) $accMap[$r['id']] = $r;

            // Sirf COA (Opening Balance) + Transfer Voucher + Journal Voucher ki entries
            // Common filters (opening + period dono par lagte hain)
            $where  = " l.tenant_id = ? AND l.reference_table IN ('accounts','transfer_voucher','journal_voucher') ";
            $params = [$tenant_id];
            if ($accountId) { $where .= " AND l.account_id = ? "; $params[] = $accountId; }
            if ($type !== '') { $where .= " AND l.transaction_type = ? "; $params[] = $type; }
            if ($noJv) $where .= " AND (l.reference_table IS NULL OR l.reference_table <> 'journal_voucher') ";
            if ($noTv) $where .= " AND (l.reference_table IS NULL OR l.reference_table <> 'transfer_voucher') ";

            // Opening balances
            $opening = [];
            if ($from) {
                $stmt = $pdo->prepare("SELECT l.account_id, SUM(l.debit) AS d, SUM(l.credit) AS c
                                       FROM accounting_ledger l
                                       WHERE $where AND l.date < ?
                                       GROUP BY l.account_id");
                $stmt->execute(array_merge($params, [$from]));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $opening[$r['account_id']] = (float)$r['d'] - (float)$r['c'];
                }
            }

            // Period entries
            $rowWhere  = $where;
            $rowParams = $params;
            if ($from) { $rowWhere .= " AND l.date >= ? "; $rowParams[] = $from; }
            if ($to)   { $rowWhere .= " AND l.date <= ? "; $rowParams[] = $to; }
            if ($q !== '') { $rowWhere .= " AND l.description LIKE ? "; $rowParams[] = '%' . $q . '%'; }

            $stmt = $pdo->prepare("SELECT l.id, l.account_id, l.date, l.transaction_type, l.reference_table, l.reference_id,
                                          l.description, l.debit, l.credit,
                                          tv.voucher_number AS tv_no, jv.voucher_number AS jv_no
                                   FROM accounting_ledger l
                                   LEFT JOIN transfer_voucher tv ON l.reference_table = 'transfer_voucher' AND tv.id = l.reference_id
                                   LEFT JOIN journal_voucher  jv ON l.reference_table = 'journal_voucher'  AND jv.id = l.reference_id
                                   WHERE $rowWhere
                                   ORDER BY l.date ASC, l.id ASC");
            $stmt->execute($rowParams);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $byAccount = [];
            foreach ($entries as $e) {
                $voucher = $e['tv_no'] ?: ($e['jv_no'] ?: ($e['transaction_type'] . ' #' . $e['reference_id']));
                $byAccount[$e['account_id']][] = [
                    'id'      => (int)$e['id'],
                    'date'    => $e['date'],
                    'type'    => $e['transaction_type'],
                    'voucher' => $voucher,
                    'desc'    => $e['description'],
                    'debit'   => (float)$e['debit'],
                    'credit'  => (float)$e['credit']
                ];
            }

            $ids = array_unique(array_merge(array_keys($byAccount), array_keys($opening)));
            if (!$hideZero && !$accountId && $q === '' && $type === '') {
                $ids = array_unique(array_merge($ids, array_keys($accMap)));
            }
            if ($accountId) $ids = [$accountId];
            sort($ids, SORT_NUMERIC);

            $result = [];
            foreach ($ids as $id) {
                $info = $accMap[$id] ?? null;
                if ($headId && (!$info || intval($info['head_id']) !== $headId)) continue;

                $rows = $byAccount[$id] ?? [];
                $op   = $opening[$id] ?? 0.0;
                $td = 0; $tc = 0;
                foreach ($rows as $r) { $td += $r['debit']; $tc += $r['credit']; }

                if ($hideZero && !count($rows) && abs($op) < 0.005) continue;

                $result[] = [
                    'id'       => (int)$id,
                    'name'     => $info['name'] ?? ('Account #' . $id),
                    'head'     => $info['head_name'] ?? '',
                    'sub'      => $info['sub_name'] ?? '',
                    'opening'  => round($op, 2),
                    'total_debit'  => round($td, 2),
                    'total_credit' => round($tc, 2),
                    'closing'  => round($op + $td - $tc, 2),
                    'rows'     => $rows
                ];
            }

            echo json_encode(['success' => true, 'data' => $result, 'filters' => ['from' => $from, 'to' => $to]]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ───────────────────────── FRONTEND ─────────────────────────
require_once '../../../../includes/dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - General Ledger</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gl-page { --gl-primary:#2563eb; --gl-primary-dark:#1d4ed8; --gl-primary-soft:#eff6ff; --gl-border:#e2e8f0; --gl-sub:#64748b; --gl-bg:#f8fafc; --gl-text:#0f172a; padding:20px; color:var(--gl-text); }
        .gl-page h1 { font-size:22px; margin:0 0 16px; font-weight:700; display:flex; align-items:center; gap:10px; }
        .gl-page h1 i { background:var(--gl-primary-soft); color:var(--gl-primary); width:38px; height:38px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; font-size:17px; }

        .gl-card { background:#fff; border:1px solid var(--gl-border); border-radius:12px; padding:18px; margin-bottom:16px; box-shadow:0 1px 3px rgba(15,23,42,.05); }

        /* Filters */
        .gl-card-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; flex-wrap:wrap; }
        .gl-card-title { font-size:13px; font-weight:700; color:var(--gl-text); display:flex; align-items:center; gap:8px; }
        .gl-card-title i { color:var(--gl-primary); }
        .gl-filters { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; align-items:end; }
        .gl-field label { display:block; font-size:11px; font-weight:600; color:var(--gl-sub); margin-bottom:5px; text-transform:uppercase; letter-spacing:.4px; }
        .gl-field input, .gl-field select { width:100%; padding:9px 11px; border:1px solid var(--gl-border); border-radius:8px; font-size:14px; box-sizing:border-box; background:#fff; color:var(--gl-text); transition:border-color .15s, box-shadow .15s; }
        .gl-field input:hover, .gl-field select:hover { border-color:#cbd5e1; }
        .gl-field input:focus, .gl-field select:focus { outline:none; border-color:var(--gl-primary); box-shadow:0 0 0 3px rgba(37,99,235,.15); }

        /* Switches */
        .gl-opts { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; padding-bottom:16px; border-bottom:1px dashed var(--gl-border); }
        .gl-switch { display:inline-flex; align-items:center; gap:10px; cursor:pointer; user-select:none; font-size:13px; font-weight:500; padding:8px 14px 8px 10px; border:1px solid var(--gl-border); border-radius:999px; background:#fff; transition:all .15s; }
        .gl-switch:hover { border-color:#cbd5e1; background:var(--gl-bg); }
        .gl-switch input { position:absolute; opacity:0; width:0; height:0; }
        .gl-switch .track { width:36px; height:20px; border-radius:999px; background:#cbd5e1; position:relative; transition:background .2s; flex-shrink:0; }
        .gl-switch .track::after { content:''; position:absolute; top:2px; left:2px; width:16px; height:16px; border-radius:50%; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.3); transition:transform .2s; }
        .gl-switch input:checked + .track { background:var(--gl-primary); }
        .gl-switch input:checked + .track::after { transform:translateX(16px); }
        .gl-switch input:focus-visible + .track { box-shadow:0 0 0 3px rgba(37,99,235,.25); }
        .gl-switch:has(input:checked) { border-color:var(--gl-primary); background:var(--gl-primary-soft); color:var(--gl-primary-dark); }

        /* Buttons */
        .gl-actions { display:flex; gap:8px; flex-wrap:nowrap; justify-content:flex-end; margin-left:auto; }
        .gl-btn { white-space:nowrap; }
        .gl-btn.excel { background:#16a34a; color:#fff; border-color:#16a34a; }
        .gl-btn.excel:hover { background:#15803d; border-color:#15803d; }
        .gl-btn { padding:9px 16px; border-radius:8px; border:1px solid var(--gl-border); background:#fff; color:var(--gl-text); cursor:pointer; font-size:14px; font-weight:500; display:inline-flex; align-items:center; gap:7px; transition:all .15s; }
        .gl-btn:hover { background:var(--gl-bg); border-color:#cbd5e1; transform:translateY(-1px); box-shadow:0 2px 6px rgba(15,23,42,.08); }
        .gl-btn:active { transform:translateY(0); box-shadow:none; }
        .gl-btn.primary { background:var(--gl-primary); color:#fff; border-color:var(--gl-primary); }
        .gl-btn.primary:hover { background:var(--gl-primary-dark); border-color:var(--gl-primary-dark); }

        /* Tabs (segmented) */
        .gl-tabs { display:inline-flex; gap:4px; padding:4px; background:#e2e8f0; border-radius:10px; margin-bottom:14px; }
        .gl-tab { padding:8px 20px; border-radius:7px; border:none; background:transparent; color:var(--gl-sub); cursor:pointer; font-size:14px; font-weight:600; transition:all .15s; }
        .gl-tab:hover { color:var(--gl-text); }
        .gl-tab.active { background:#fff; color:var(--gl-primary); box-shadow:0 1px 3px rgba(15,23,42,.15); }

        /* Table */
        .gl-card.gl-scroll { padding:0; overflow-x:auto; }
        table.gl-table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
        .gl-table th, .gl-table td { padding:10px 12px; vertical-align:top; border-bottom:1px solid #eef2f6; }
        .gl-table thead th { background:#f1f5f9; color:var(--gl-sub); font-size:11px; text-transform:uppercase; letter-spacing:.5px; font-weight:700; text-align:left; border-bottom:2px solid var(--gl-border); white-space:nowrap; }
        .gl-table td.num, .gl-table th.num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .gl-table tbody tr:nth-child(even) td { background:#fafbfd; }
        .gl-table tbody tr:hover td { background:var(--gl-primary-soft); }
        .gl-table tr.open td { background:#fef9c3 !important; font-style:italic; color:#713f12; }
        .gl-table tfoot td { font-weight:700; background:#f1f5f9; border-top:2px solid var(--gl-border); border-bottom:none; }
        .gl-table .acc-id { display:inline-block; background:#e0e7ff; color:#3730a3; border-radius:6px; padding:1px 6px; font-size:11px; margin-left:4px; }
        .gl-table .v-chip { display:inline-block; background:var(--gl-bg); border:1px solid var(--gl-border); border-radius:6px; padding:1px 7px; font-size:12px; white-space:nowrap; }
        .gl-table .amt-dr { color:#047857; font-weight:600; }
        .gl-table .amt-cr { color:#b91c1c; font-weight:600; }
        .dr { color:#047857; font-weight:600; } .cr { color:#b91c1c; font-weight:600; }
        .gl-empty { text-align:center; padding:40px 20px; color:var(--gl-sub); font-size:14px; }
        .gl-scroll { overflow-x:auto; }
    </style>
</head>
<body>
<div class="gl-page">
    <h1><i class="fas fa-book" style="color:var(--gl-primary);margin-right:10px;"></i>General Ledger</h1>

    <div class="gl-card">
        <div class="gl-card-head">
            <div class="gl-card-title"><i class="fas fa-sliders-h"></i> Filters</div>
            <div class="gl-actions">
                <button class="gl-btn primary" id="btnApply"><i class="fas fa-filter"></i> Apply</button>
                <button class="gl-btn" id="btnReset"><i class="fas fa-undo"></i> Reset</button>
                <button class="gl-btn" id="btnPrint"><i class="fas fa-print"></i> Print</button>
                <button class="gl-btn excel" id="btnExcel"><i class="fas fa-file-excel"></i> Export to Excel</button>
            </div>
        </div>
        <div class="gl-opts">
            <label class="gl-switch"><input type="checkbox" id="fNoJv"><span class="track"></span> Without Journal Voucher</label>
            <label class="gl-switch"><input type="checkbox" id="fNoTv"><span class="track"></span> Without Transfer Voucher</label>
            <label class="gl-switch"><input type="checkbox" id="fHideZero" checked><span class="track"></span> Only accounts with activity</label>
        </div>
        <div class="gl-filters">
            <div class="gl-field"><label>From Date</label><input type="date" id="fFrom"></div>
            <div class="gl-field"><label>To Date</label><input type="date" id="fTo"></div>
            <div class="gl-field">
                <label>Account (search)</label>
                <input type="text" id="fAccount" list="accountList" placeholder="All accounts" autocomplete="off">
                <datalist id="accountList"></datalist>
            </div>
            <div class="gl-field"><label>Account Head</label><select id="fHead"><option value="">All</option></select></div>
            <div class="gl-field"><label>Transaction Type</label><select id="fType"><option value="">All</option></select></div>
            <div class="gl-field"><label>Description contains</label><input type="text" id="fQ" placeholder="search..."></div>
        </div>
    </div>

    <div class="gl-tabs">
        <button class="gl-tab active" data-view="detail"><i class="fas fa-list"></i> Ledger</button>
        <button class="gl-tab" data-view="summary"><i class="fas fa-chart-bar"></i> Summary</button>
    </div>

    <div id="glOutput"><div class="gl-empty">Loading...</div></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
const API = 'general-ledger.php';
let META = { accounts: [], heads: [], types: [] };
let DATA = [];
let VIEW = 'detail';

const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt = n => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const drcr = n => n >= 0 ? 'Dr' : 'Cr';

function accountLabel(a) { return a.id + ' - ' + a.name; }

function getAccountId() {
    const v = $('fAccount').value.trim();
    if (!v) return '';
    const hit = META.accounts.find(a => accountLabel(a) === v);
    return hit ? hit.id : '';
}

function buildQuery() {
    const p = new URLSearchParams();
    if ($('fFrom').value) p.set('from', $('fFrom').value);
    if ($('fTo').value) p.set('to', $('fTo').value);
    const acc = getAccountId();
    if (acc) p.set('account_id', acc);
    if ($('fHead').value) p.set('head_id', $('fHead').value);
    if ($('fType').value) p.set('type', $('fType').value);
    if ($('fQ').value.trim()) p.set('q', $('fQ').value.trim());
    p.set('hide_zero', $('fHideZero').checked ? '1' : '0');
    if ($('fNoJv').checked) p.set('no_jv', '1');
    if ($('fNoTv').checked) p.set('no_tv', '1');
    return p;
}

async function loadMeta() {
    const res = await fetch(API + '?action=meta');
    const j = await res.json();
    if (!j.success) { alert(j.message || 'Failed to load'); return; }
    META = j;
    $('accountList').innerHTML = j.accounts.map(a => `<option value="${esc(accountLabel(a))}"></option>`).join('');
    $('fHead').innerHTML = '<option value="">All</option>' + j.heads.map(h => `<option value="${h.id}">${esc(h.name)}</option>`).join('');
    $('fType').innerHTML = '<option value="">All</option>' + j.types.map(t => `<option value="${esc(t)}">${esc(t)}</option>`).join('');
}

async function loadData() {
    $('glOutput').innerHTML = '<div class="gl-empty">Loading...</div>';
    try {
        const res = await fetch(API + '?action=data&' + buildQuery().toString());
        const j = await res.json();
        if (!j.success) { $('glOutput').innerHTML = `<div class="gl-empty">${esc(j.message)}</div>`; return; }
        DATA = j.data;
        render();
    } catch (e) {
        console.error(e);
        $('glOutput').innerHTML = '<div class="gl-empty">Error loading ledger</div>';
    }
}

// ---------- Summary ----------
function renderSummary() {
    if (!DATA.length) return '<div class="gl-card gl-empty">No data found for selected filters</div>';
    let tD = 0, tC = 0, tO = 0, tCl = 0;
    const rows = DATA.map(a => {
        tD += a.total_debit; tC += a.total_credit; tO += a.opening; tCl += a.closing;
        return `<tr>
            <td>${a.id}</td>
            <td>${esc(a.name)}</td>
            <td>${esc(a.head)}${a.sub ? ' / ' + esc(a.sub) : ''}</td>
            <td class="num">${fmt(Math.abs(a.opening))} ${drcr(a.opening)}</td>
            <td class="num">${fmt(a.total_debit)}</td>
            <td class="num">${fmt(a.total_credit)}</td>
            <td class="num ${a.closing >= 0 ? 'dr' : 'cr'}">${fmt(Math.abs(a.closing))} ${drcr(a.closing)}</td>
        </tr>`;
    }).join('');
    return `<div class="gl-card gl-scroll"><table class="gl-table">
        <thead><tr><th>ID</th><th>Account Name</th><th>Group</th><th class="num">Opening</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Closing</th></tr></thead>
        <tbody>${rows}</tbody>
        <tfoot><tr class="total"><td colspan="3">Total (${DATA.length} accounts)</td>
            <td class="num">${fmt(Math.abs(tO))} ${drcr(tO)}</td>
            <td class="num">${fmt(tD)}</td><td class="num">${fmt(tC)}</td>
            <td class="num">${fmt(Math.abs(tCl))} ${drcr(tCl)}</td></tr></tfoot>
    </table></div>`;
}

// ---------- Detail: ek hi flat table ----------
function renderDetail() {
    // sari entries ek list mein, date wise
    const all = [];
    DATA.forEach(a => a.rows.forEach(r => all.push({ ...r, acc: a.name, accId: a.id })));
    all.sort((x, y) => x.date < y.date ? -1 : x.date > y.date ? 1 : x.id - y.id);

    const single = DATA.length === 1;            // ek account => running balance
    const balTxt = v => fmt(Math.abs(v)) + ' ' + drcr(v);
    const balCls = v => v >= 0 ? 'dr' : 'cr';

    if (!all.length && !(single && Math.abs(DATA[0].opening) >= 0.005)) {
        return '<div class="gl-card gl-empty">No data found for selected filters</div>';
    }

    let bal = single ? DATA[0].opening : 0;
    let tD = 0, tC = 0;
    const cols = single ? 7 : 6;
    let body = '';
    if (single) {
        body += `<tr class="open"><td></td><td></td><td></td><td>Opening Balance</td><td></td><td></td>
            <td class="num ${balCls(bal)}">${balTxt(bal)}</td></tr>`;
    }
    all.forEach(r => {
        tD += r.debit; tC += r.credit; bal += r.debit - r.credit;
        body += `<tr>
            <td>${esc(r.date)}</td>
            <td><span class="v-chip">${esc(r.voucher)}</span></td>
            <td>${esc(r.acc)}<span class="acc-id">#${r.accId}</span></td>
            <td>${esc(r.desc || r.type)}</td>
            <td class="num amt-dr">${r.debit > 0 ? fmt(r.debit) : ''}</td>
            <td class="num amt-cr">${r.credit > 0 ? fmt(r.credit) : ''}</td>
            ${single ? `<td class="num ${balCls(bal)}">${balTxt(bal)}</td>` : ''}
        </tr>`;
    });

    return `<div class="gl-card gl-scroll"><table class="gl-table">
        <thead><tr><th>Date</th><th>Voucher</th><th>Account</th><th>Description</th>
            <th class="num">Debit</th><th class="num">Credit</th>${single ? '<th class="num">Balance</th>' : ''}</tr></thead>
        <tbody>${body}</tbody>
        <tfoot><tr class="total"><td colspan="4">Totals (${all.length} entries)</td>
            <td class="num amt-dr">${fmt(tD)}</td><td class="num amt-cr">${fmt(tC)}</td>
            ${single ? `<td class="num ${balCls(bal)}">${balTxt(bal)}</td>` : ''}</tr></tfoot>
    </table></div>`;
}

function render() {
    $('glOutput').innerHTML = VIEW === 'summary' ? renderSummary() : renderDetail();
}

// ---------- Events ----------
document.querySelectorAll('.gl-tab').forEach(t => t.addEventListener('click', () => {
    document.querySelectorAll('.gl-tab').forEach(x => x.classList.remove('active'));
    t.classList.add('active');
    VIEW = t.dataset.view;
    render();
}));

$('btnApply').addEventListener('click', loadData);
$('btnReset').addEventListener('click', () => {
    ['fFrom', 'fTo', 'fAccount', 'fHead', 'fType', 'fQ'].forEach(id => $(id).value = '');
    $('fHideZero').checked = true;
    $('fNoJv').checked = false;
    $('fNoTv').checked = false;
    loadData();
});
$('btnPrint').addEventListener('click', () => {
    const p = buildQuery();
    p.set('view', VIEW);
    window.open('general-ledger-print.php?' + p.toString(), '_blank');
});

// ---------- Export to Excel ----------
function exportExcel() {
    if (typeof XLSX === 'undefined') { alert('Excel library load nahi hui, internet check karein.'); return; }
    if (!DATA.length) { alert('No data to export'); return; }
    const sgn = v => Math.round(Math.abs(v) * 100) / 100 + ' ' + drcr(v);
    let aoa, name;

    if (VIEW === 'summary') {
        name = 'Summary';
        aoa = [['ID', 'Account Name', 'Group', 'Opening', 'Debit', 'Credit', 'Closing']];
        let tD = 0, tC = 0, tO = 0, tCl = 0;
        DATA.forEach(a => {
            tD += a.total_debit; tC += a.total_credit; tO += a.opening; tCl += a.closing;
            aoa.push([a.id, a.name, a.head + (a.sub ? ' / ' + a.sub : ''), sgn(a.opening), a.total_debit, a.total_credit, sgn(a.closing)]);
        });
        aoa.push(['', 'Total (' + DATA.length + ' accounts)', '', sgn(tO), Math.round(tD * 100) / 100, Math.round(tC * 100) / 100, sgn(tCl)]);
    } else {
        name = 'General Ledger';
        const all = [];
        DATA.forEach(a => a.rows.forEach(r => all.push({ ...r, acc: a.name, accId: a.id })));
        all.sort((x, y) => x.date < y.date ? -1 : x.date > y.date ? 1 : x.id - y.id);
        const single = DATA.length === 1;
        let bal = single ? DATA[0].opening : 0, tD = 0, tC = 0;
        const head = ['Date', 'Voucher', 'Account ID', 'Account', 'Description', 'Debit', 'Credit'];
        if (single) head.push('Balance');
        aoa = [head];
        if (single) aoa.push(['', '', '', '', 'Opening Balance', '', '', sgn(bal)]);
        all.forEach(r => {
            tD += r.debit; tC += r.credit; bal += r.debit - r.credit;
            const row = [r.date, r.voucher, r.accId, r.acc, r.desc || r.type, r.debit || '', r.credit || ''];
            if (single) row.push(sgn(bal));
            aoa.push(row);
        });
        const tot = ['', '', '', '', 'Totals (' + all.length + ' entries)', Math.round(tD * 100) / 100, Math.round(tC * 100) / 100];
        if (single) tot.push(sgn(bal));
        aoa.push(tot);
    }

    const ws = XLSX.utils.aoa_to_sheet(aoa);
    ws['!cols'] = aoa[0].map((_, i) => ({ wch: Math.min(45, Math.max(10, ...aoa.map(r => String(r[i] ?? '').length))) }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, name);
    const d = new Date(), pad = n => String(n).padStart(2, '0');
    XLSX.writeFile(wb, `General-Ledger-${VIEW}-${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}.xlsx`);
}
$('btnExcel').addEventListener('click', exportExcel);

['fFrom', 'fTo', 'fHead', 'fType', 'fNoJv', 'fNoTv', 'fHideZero'].forEach(id => $(id).addEventListener('change', loadData));
$('fAccount').addEventListener('change', loadData);
$('fQ').addEventListener('keydown', e => { if (e.key === 'Enter') loadData(); });

// Init: default = current month
(function init() {
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    $('fFrom').value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
    $('fTo').value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
    loadMeta().then(loadData);
})();
</script>
</body>
</html>