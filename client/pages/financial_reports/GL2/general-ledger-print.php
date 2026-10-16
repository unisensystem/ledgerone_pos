<?php
require_once '../../../../includes/connection.php';
if (session_status() == PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo 'Unauthorized - Please login again';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>General Ledger - Print</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; margin: 0; padding: 16px; }
        .hdr { text-align: center; margin-bottom: 12px; }
        .hdr h1 { margin: 0; font-size: 18px; }
        .hdr h2 { margin: 2px 0; font-size: 14px; font-weight: normal; }
        .hdr .meta { font-size: 11px; color: #444; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 3px 5px; vertical-align: top; }
        th { background: #e5e7eb; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .3px; }
        tbody tr:nth-child(even) td { background: #f9fafb; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .num { text-align: right; white-space: nowrap; }
        tr.total td { font-weight: bold; background: #f3f3f3; }
        tr.open td { font-style: italic; background: #fafafa; }
        .toolbar { text-align: right; margin-bottom: 10px; }
        .toolbar button { padding: 7px 18px; cursor: pointer; background: #2563eb; color: #fff; border: 0; border-radius: 6px; font-size: 13px; }
        .foot { margin-top: 14px; font-size: 10px; color: #555; text-align: right; }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
            @page { size: A4 landscape; margin: 10mm; }
        }
    </style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Print</button></div>
<div class="hdr">
    <h1>LedgerOne ERP</h1>
    <h2 id="title">General Ledger</h2>
    <div class="meta" id="meta"></div>
</div>
<div id="out">Loading...</div>
<div class="foot" id="foot"></div>

<script>
const params = new URLSearchParams(location.search);
const VIEW = params.get('view') === 'summary' ? 'summary' : 'detail';

const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt = n => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const drcr = n => n >= 0 ? 'Dr' : 'Cr';
const balTxt = v => fmt(Math.abs(v)) + ' ' + drcr(v);

function summaryHtml(data) {
    let tD = 0, tC = 0, tO = 0, tCl = 0;
    const rows = data.map(a => {
        tD += a.total_debit; tC += a.total_credit; tO += a.opening; tCl += a.closing;
        return `<tr><td>${a.id}</td><td>${esc(a.name)}</td><td>${esc(a.head)}${a.sub ? ' / ' + esc(a.sub) : ''}</td>
            <td class="num">${balTxt(a.opening)}</td>
            <td class="num">${fmt(a.total_debit)}</td><td class="num">${fmt(a.total_credit)}</td>
            <td class="num">${balTxt(a.closing)}</td></tr>`;
    }).join('');
    return `<table><thead><tr><th>ID</th><th>Account Name</th><th>Group</th><th class="num">Opening</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Closing</th></tr></thead>
        <tbody>${rows}</tbody>
        <tfoot><tr class="total"><td colspan="3">Total (${data.length} accounts)</td>
        <td class="num">${balTxt(tO)}</td><td class="num">${fmt(tD)}</td><td class="num">${fmt(tC)}</td>
        <td class="num">${balTxt(tCl)}</td></tr></tfoot></table>`;
}

function detailHtml(data) {
    const all = [];
    data.forEach(a => a.rows.forEach(r => all.push({ ...r, acc: a.name, accId: a.id })));
    all.sort((x, y) => x.date < y.date ? -1 : x.date > y.date ? 1 : x.id - y.id);

    const single = data.length === 1;
    let bal = single ? data[0].opening : 0;
    let tD = 0, tC = 0;
    let body = '';
    if (single) {
        body += `<tr class="open"><td></td><td></td><td></td><td>Opening Balance</td><td></td><td></td><td class="num">${balTxt(bal)}</td></tr>`;
    }
    all.forEach(r => {
        tD += r.debit; tC += r.credit; bal += r.debit - r.credit;
        body += `<tr><td>${esc(r.date)}</td><td>${esc(r.voucher)}</td><td>${esc(r.acc)} (#${r.accId})</td><td>${esc(r.desc || r.type)}</td>
            <td class="num">${r.debit > 0 ? fmt(r.debit) : ''}</td><td class="num">${r.credit > 0 ? fmt(r.credit) : ''}</td>
            ${single ? `<td class="num">${balTxt(bal)}</td>` : ''}</tr>`;
    });
    return `<table>
        <thead><tr><th>Date</th><th>Voucher</th><th>Account</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th>${single ? '<th class="num">Balance</th>' : ''}</tr></thead>
        <tbody>${body}</tbody>
        <tfoot><tr class="total"><td colspan="4">Totals (${all.length} entries)</td><td class="num">${fmt(tD)}</td><td class="num">${fmt(tC)}</td>${single ? `<td class="num">${balTxt(bal)}</td>` : ''}</tr></tfoot>
    </table>`;
}

(async function () {
    try {
        const q = new URLSearchParams(params);
        q.delete('view');
        q.set('action', 'data');
        const res = await fetch('general-ledger.php?' + q.toString());
        const j = await res.json();
        if (!j.success) { $('out').textContent = j.message || 'Error'; return; }

        $('title').textContent = VIEW === 'summary' ? 'General Ledger - Summary' : 'General Ledger';
        const from = params.get('from'), to = params.get('to');
        let meta = 'Period: ' + (from || 'Beginning') + '  to  ' + (to || 'Today');
        if (params.get('account_id')) meta += '  |  Account ID: ' + params.get('account_id');
        if (params.get('type')) meta += '  |  Type: ' + params.get('type');
        if (params.get('q')) meta += '  |  Search: ' + params.get('q');
        if (params.get('no_jv') === '1') meta += '  |  Without Journal Voucher';
        if (params.get('no_tv') === '1') meta += '  |  Without Transfer Voucher';
        $('meta').textContent = meta;
        $('foot').textContent = 'Printed on ' + new Date().toLocaleString();

        if (!j.data.length) { $('out').textContent = 'No data found for selected filters'; return; }
        $('out').innerHTML = VIEW === 'summary' ? summaryHtml(j.data) : detailHtml(j.data);
        setTimeout(() => window.print(), 400);
    } catch (e) {
        console.error(e);
        $('out').textContent = 'Error loading ledger';
    }
})();
</script>
</body>
</html>