<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: ../../auth/login.html'); exit(); }
require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("SELECT c.symbol FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_base_currency = 1");
$stmt->execute([$_SESSION['tenant_id']]);
$cur = $stmt->fetch();
$sym = $cur['symbol'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Counter Cash Flow</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #F7F9FC; color: #2F3B4C; }
        .container { padding: 24px; max-width: 1400px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .page-title { font-size: 22px; font-weight: 700; color: #0E1A2B; }
        .page-subtitle { font-size: 13px; color: #6B7280; margin-top: 3px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
        .btn-primary { background: #1F7BFF; color: white; }
        .btn-primary:hover { background: #1A6CDC; }
        .btn-secondary { background: white; color: #2F3B4C; border: 1px solid #E1E6EE; }
        .btn-secondary:hover { background: #F7F9FC; }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.07); padding: 20px; margin-bottom: 20px; }
        .card-title { font-size: 14px; font-weight: 600; margin-bottom: 16px; color: #0E1A2B; }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-label { font-size: 12px; font-weight: 500; color: #6B7280; }
        .filter-input { height: 36px; padding: 0 10px; border: 1px solid #E1E6EE; border-radius: 6px; font-size: 13px; }
        #customDateGroup { display: none; }
        /* Summary cards */
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
        .summary-card { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.07); padding: 18px 20px; }
        .summary-label { font-size: 12px; color: #6B7280; font-weight: 500; margin-bottom: 6px; }
        .summary-value { font-size: 22px; font-weight: 700; color: #0E1A2B; }
        .summary-card.green .summary-value { color: #2FBF71; }
        .summary-card.blue  .summary-value { color: #1F7BFF; }
        .summary-card.amber .summary-value { color: #E8B23F; }
        /* Counter summary grid */
        .counter-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .counter-card { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.07); padding: 16px; border-left: 4px solid #1F7BFF; cursor: pointer; transition: box-shadow .15s; }
        .counter-card:hover { box-shadow: 0 4px 12px rgba(31,123,255,.15); }
        .counter-card-name { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
        .counter-card-amount { font-size: 20px; font-weight: 700; color: #2FBF71; }
        .counter-card-label { font-size: 11px; color: #6B7280; margin-top: 2px; }
        /* Table */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { padding: 10px 12px; text-align: left; background: #F7F9FC; border-bottom: 1px solid #E1E6EE; font-weight: 600; color: #6B7280; font-size: 11px; text-transform: uppercase; white-space: nowrap; }
        td { padding: 10px 12px; border-bottom: 1px solid #E1E6EE; }
        tr:hover td { background: #F7F9FC; }
        .amount-cell { text-align: right; font-weight: 500; }
        .pagination { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 16px; }
        .page-btn { min-width: 32px; height: 32px; border-radius: 4px; border: 1px solid #E1E6EE; background: white; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; }
        .page-btn.active { background: #1F7BFF; color: white; border-color: #1F7BFF; }
        .page-btn:hover:not(.active) { background: #F7F9FC; }
        .empty-state { text-align: center; padding: 48px 20px; color: #6B7280; }
        #loadingOverlay { display: none; position: fixed; inset: 0; background: rgba(255,255,255,.7); z-index: 500; align-items: center; justify-content: center; font-size: 15px; color: #1F7BFF; }
        #loadingOverlay.show { display: flex; }
        @media print {
            .header .actions, .card.filters-card, .pagination { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body>
<div id="loadingOverlay" class="loadingOverlay"><i class="fas fa-spinner fa-spin" style="margin-right:8px"></i> Loading...</div>

<div class="container">
    <div class="header">
        <div>
            <div class="page-title"><i class="fas fa-cash-register" style="color:#1F7BFF;margin-right:8px"></i>Counter Cash Flow</div>
            <div class="page-subtitle">Cash inflow per POS counter — cash payments only</div>
        </div>
        <div class="actions" style="display:flex;gap:8px">
            <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filters-card">
        <div class="card-title"><i class="fas fa-filter"></i> Filters</div>
        <div class="filters-grid">
            <div class="filter-group">
                <label class="filter-label">Branch</label>
                <select class="filter-input" id="branchFilter">
                    <option value="">All Branches</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Counter</label>
                <select class="filter-input" id="counterFilter">
                    <option value="">All Counters</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Date Range</label>
                <select class="filter-input" id="dateRange">
                    <option value="today" selected>Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <div class="filter-group" id="customDateGroup" style="display:none">
                <label class="filter-label">From</label>
                <input type="date" class="filter-input" id="fromDate">
            </div>
            <div class="filter-group" id="customDateGroupTo" style="display:none">
                <label class="filter-label">To</label>
                <input type="date" class="filter-input" id="toDate">
            </div>
            <div class="filter-group" style="justify-content:flex-end">
                <label class="filter-label">&nbsp;</label>
                <button class="btn btn-primary" id="applyBtn"><i class="fas fa-search"></i> Apply</button>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">Opening Cash</div>
            <div class="summary-value" id="summaryOpening"><?= $sym ?> 0.00</div>
        </div>
        <div class="summary-card green">
            <div class="summary-label">Cash In (Sales)</div>
            <div class="summary-value" id="summaryCashIn"><?= $sym ?> 0.00</div>
        </div>
        <div class="summary-card blue">
            <div class="summary-label">Closing Cash</div>
            <div class="summary-value" id="summaryClosing"><?= $sym ?> 0.00</div>
        </div>
        <div class="summary-card amber">
            <div class="summary-label">Transactions</div>
            <div class="summary-value" id="summaryCount">0</div>
        </div>
    </div>

    <!-- Per-counter summary (shown when no specific counter selected) -->
    <div id="counterSummarySection">
        <div style="font-size:14px;font-weight:600;margin-bottom:12px;color:#0E1A2B;">Cash In by Counter</div>
        <div class="counter-grid" id="counterCards"></div>
    </div>

    <!-- Transactions -->
    <div class="card">
        <div class="card-title"><i class="fas fa-list"></i> Cash Transactions</div>
        <div id="tableWrap">
            <div class="empty-state"><i class="fas fa-filter" style="font-size:24px;margin-bottom:8px;display:block"></i>Apply filters to see transactions</div>
        </div>
        <div class="pagination" id="pagination"></div>
    </div>
</div>

<script>
const SYM = '<?= addslashes($sym) ?>';
let currentPage = 1;
let currentFilters = {};

function fmt(n) {
    return SYM + ' ' + parseFloat(n || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

async function loadBranches() {
    const res = await fetch('../../../../server/api/master_setup/branch_setup/branch-list.php');
    const data = await res.json();
    if (data.success) {
        const branches = data.branches || data.data || [];
        const sel = document.getElementById('branchFilter');
        branches.forEach(b => {
            const o = document.createElement('option');
            o.value = b.id;
            o.textContent = b.branch_name;
            sel.appendChild(o);
        });
    }
}

document.getElementById('branchFilter').addEventListener('change', async function() {
    const branchId = this.value;
    const counterSel = document.getElementById('counterFilter');
    counterSel.innerHTML = '<option value="">All Counters</option>';
    if (!branchId) return;
    const res = await fetch(`../../../../server/api/sale/pos_invoice/get-counters.php?branch_id=${branchId}`);
    const data = await res.json();
    if (data.success) {
        data.counters.forEach(c => {
            const o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.counter_name;
            counterSel.appendChild(o);
        });
    }
});

document.getElementById('dateRange').addEventListener('change', function() {
    const isCustom = this.value === 'custom';
    document.getElementById('customDateGroup').style.display   = isCustom ? 'flex' : 'none';
    document.getElementById('customDateGroupTo').style.display = isCustom ? 'flex' : 'none';
});

document.getElementById('applyBtn').addEventListener('click', () => { currentPage = 1; loadReport(); });

async function loadReport() {
    const branch    = document.getElementById('branchFilter').value;
    const counter   = document.getElementById('counterFilter').value;
    const dateRange = document.getElementById('dateRange').value;
    const fromDate  = document.getElementById('fromDate').value;
    const toDate    = document.getElementById('toDate').value;

    currentFilters = { branch, counter, dateRange, fromDate, toDate };

    let url = `../../../../server/api/financial_reports/counter_cashflow/counter-cashflow.php?date_range=${dateRange}&page=${currentPage}&limit=50`;
    if (branch)   url += `&branch_id=${branch}`;
    if (counter)  url += `&counter_id=${counter}`;
    if (fromDate) url += `&from_date=${fromDate}`;
    if (toDate)   url += `&to_date=${toDate}`;

    document.getElementById('loadingOverlay').classList.add('show');
    try {
        const res  = await fetch(url);
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        // Summary cards
        document.getElementById('summaryOpening').textContent = fmt(data.opening_balance);
        document.getElementById('summaryCashIn').textContent  = fmt(data.total_cash_in);
        document.getElementById('summaryClosing').textContent = fmt(data.closing_balance);
        document.getElementById('summaryCount').textContent   = data.total_rows;

        // Per-counter cards
        const counterSection = document.getElementById('counterSummarySection');
        const counterCards   = document.getElementById('counterCards');
        if (data.counter_summary && data.counter_summary.length > 0) {
            counterSection.style.display = 'block';
            counterCards.innerHTML = data.counter_summary.map(c => `
                <div class="counter-card" onclick="filterByCounter(${c.id})">
                    <div class="counter-card-name"><i class="fas fa-cash-register" style="color:#1F7BFF;margin-right:6px"></i>${c.counter_name}</div>
                    <div class="counter-card-amount">${fmt(parseFloat(c.opening_given || 0) + parseFloat(c.cash_in || 0))}</div>
                    <div class="counter-card-label">Opening ${fmt(c.opening_given)} + Sales ${fmt(c.cash_in)}</div>
                </div>
            `).join('');
        } else {
            counterSection.style.display = counter ? 'none' : 'block';
            counterCards.innerHTML = '<div style="color:#6B7280;font-size:13px">No counters configured for this branch</div>';
        }

        // Transactions table
        renderTable(data.transactions);
        renderPagination(data.total_rows, data.page, data.limit);

    } catch (e) {
        document.getElementById('tableWrap').innerHTML = `<div class="empty-state" style="color:#E34F4F"><i class="fas fa-exclamation-circle" style="display:block;font-size:24px;margin-bottom:8px"></i>${e.message}</div>`;
    } finally {
        document.getElementById('loadingOverlay').classList.remove('show');
    }
}

function filterByCounter(counterId) {
    document.getElementById('counterFilter').value = counterId;
    currentPage = 1;
    loadReport();
}

function renderTable(rows) {
    const wrap = document.getElementById('tableWrap');
    if (!rows || rows.length === 0) {
        wrap.innerHTML = '<div class="empty-state"><i class="fas fa-inbox" style="font-size:28px;display:block;margin-bottom:8px"></i>No cash transactions found for the selected period</div>';
        return;
    }
    wrap.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Voucher No</th>
                    <th>Invoice No</th>
                    <th>Customer</th>
                    <th>Sales Officer</th>
                    <th>Counter</th>
                    <th style="text-align:right">Amount</th>
                </tr>
            </thead>
            <tbody>
                ${rows.map((r, i) => `
                    <tr>
                        <td>${(currentPage - 1) * 50 + i + 1}</td>
                        <td>${r.txn_date}</td>
                        <td>${r.reference}</td>
                        <td>${r.bill_no || '-'}</td>
                        <td>${r.customer}</td>
                        <td>${r.sale_officer || '<span style="color:#9CA3AF">-</span>'}</td>
                        <td>${r.counter_name || '<span style="color:#9CA3AF">Unassigned</span>'}</td>
                        <td class="amount-cell">${fmt(r.amount)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderPagination(total, page, limit) {
    const pages = Math.ceil(total / limit);
    const pag = document.getElementById('pagination');
    if (pages <= 1) { pag.innerHTML = ''; return; }
    let html = '';
    if (page > 1)  html += `<button class="page-btn" onclick="goPage(${page - 1})"><i class="fas fa-chevron-left"></i></button>`;
    for (let p = Math.max(1, page - 2); p <= Math.min(pages, page + 2); p++) {
        html += `<button class="page-btn ${p === page ? 'active' : ''}" onclick="goPage(${p})">${p}</button>`;
    }
    if (page < pages) html += `<button class="page-btn" onclick="goPage(${page + 1})"><i class="fas fa-chevron-right"></i></button>`;
    pag.innerHTML = html;
}

function goPage(p) { currentPage = p; loadReport(); }

loadBranches().then(() => loadReport());
</script>
</body>
</html>