<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: ../../auth/login.html'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Counter Setup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #F7F9FC; color: #2F3B4C; }
        .page-wrap { padding: 24px; max-width: 1200px; margin: 0 auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 22px; font-weight: 700; color: #0E1A2B; }
        .page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.07); padding: 20px; }
        .filters { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; min-width: 180px; }
        .filter-label { font-size: 12px; font-weight: 500; color: #6B7280; }
        .filter-input { height: 36px; padding: 0 10px; border: 1px solid #E1E6EE; border-radius: 6px; font-size: 13px; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th { padding: 10px 12px; text-align: left; background: #F7F9FC; border-bottom: 1px solid #E1E6EE; font-weight: 600; color: #6B7280; font-size: 11px; text-transform: uppercase; }
        .table td { padding: 10px 12px; border-bottom: 1px solid #E1E6EE; }
        .table tr:hover td { background: #F7F9FC; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; }
        .badge-active { background: #D1FAE5; color: #065F46; }
        .badge-inactive { background: #FEE2E2; color: #991B1B; }
        .btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
        .btn-primary { background: #1F7BFF; color: white; }
        .btn-primary:hover { background: #1A6CDC; }
        .btn-sm { height: 28px; padding: 0 10px; font-size: 12px; }
        .btn-outline { background: transparent; border: 1px solid #E1E6EE; color: #2F3B4C; }
        .btn-outline:hover { background: #F7F9FC; }
        .btn-danger { background: #E34F4F; color: white; }
        .empty-state { text-align: center; padding: 48px 20px; color: #6B7280; }
        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal { background: white; border-radius: 10px; width: 460px; padding: 24px; box-shadow: 0 8px 32px rgba(0,0,0,.15); }
        .modal-title { font-size: 16px; font-weight: 700; margin-bottom: 18px; }
        .form-row { margin-bottom: 14px; }
        .form-label { display: block; font-size: 12px; font-weight: 500; color: #6B7280; margin-bottom: 5px; }
        .form-input { width: 100%; height: 38px; padding: 0 10px; border: 1px solid #E1E6EE; border-radius: 6px; font-size: 13px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .toggle-wrap { display: flex; align-items: center; gap: 8px; }
        .toast { position: fixed; bottom: 24px; right: 24px; background: #1F7BFF; color: white; padding: 12px 20px; border-radius: 8px; font-size: 13px; z-index: 9999; opacity: 0; transition: opacity .3s; }
        .toast.show { opacity: 1; }
        .toast.error { background: #E34F4F; }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="page-header">
        <div>
            <div class="page-title">Counter Setup</div>
            <div class="page-subtitle">Define POS counters per branch for separate cash tracking</div>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="counter_cash.php" class="btn btn-outline" style="text-decoration:none;"><i class="fas fa-cash-register"></i> Counter Cash</a>
            <button class="btn btn-primary" id="addBtn"><i class="fas fa-plus"></i> Add Counter</button>
        </div>
    </div>

    <div class="card">
        <div class="filters">
            <div class="filter-group">
                <label class="filter-label">Branch</label>
                <select class="filter-input" id="branchFilter">
                    <option value="">All Branches</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select class="filter-input" id="statusFilter">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Search</label>
                <input type="text" class="filter-input" id="searchInput" placeholder="Counter name...">
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Counter Name</th>
                    <th>Branch</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <tr><td colspan="6" style="text-align:center;padding:40px;color:#6B7280;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="modal">
    <div class="modal">
        <div class="modal-title" id="modalTitle">Add Counter</div>
        <input type="hidden" id="editId">
        <div class="form-row">
            <label class="form-label">Counter Name *</label>
            <input type="text" class="form-input" id="counterName" placeholder="e.g. Counter 1">
        </div>
        <div class="form-row">
            <label class="form-label">Branch *</label>
            <select class="form-input" id="modalBranch">
                <option value="">Select Branch</option>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label">Status</label>
            <div class="toggle-wrap">
                <input type="checkbox" id="isActive" checked>
                <label for="isActive" style="font-size:13px;">Active</label>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-outline" id="cancelBtn">Cancel</button>
            <button class="btn btn-primary" id="saveBtn">Save Counter</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
let branchesData = [];

async function loadBranches() {
    const res = await fetch('../../../../server/api/master_setup/branch_setup/branch-list.php');
    const data = await res.json();
    if (data.success) {
        branchesData = data.branches || data.data || [];
        const opts = branchesData.map(b => `<option value="${b.id}">${b.branch_name}</option>`).join('');
        document.getElementById('branchFilter').innerHTML += opts;
        document.getElementById('modalBranch').innerHTML = '<option value="">Select Branch</option>' + opts;
    }
}

async function loadCounters() {
    const branch = document.getElementById('branchFilter').value;
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value;
    let url = '../../../../server/api/master_setup/counter_setup/counter-list.php?';
    if (branch) url += `branch_id=${branch}&`;
    if (status !== '') url += `status=${status}&`;
    if (search) url += `search=${encodeURIComponent(search)}&`;
    const res = await fetch(url);
    const data = await res.json();
    const tbody = document.getElementById('tableBody');
    if (!data.success || !data.counters.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#6B7280;">No counters found</td></tr>';
        return;
    }
    tbody.innerHTML = data.counters.map((c, i) => `
        <tr>
            <td>${i + 1}</td>
            <td><strong>${c.counter_name}</strong></td>
            <td>${c.branch_name || '-'}</td>
            <td>${c.company_name || '-'}</td>
            <td><span class="badge ${c.is_active == 1 ? 'badge-active' : 'badge-inactive'}">${c.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
            <td>
                <button class="btn btn-sm btn-outline" onclick="editCounter(${c.id},'${escHtml(c.counter_name)}',${c.branch_id},${c.is_active})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-sm btn-danger" style="margin-left:6px" onclick="deleteCounter(${c.id},'${escHtml(c.counter_name)}')">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function escHtml(s) { return (s||'').replace(/'/g, "\\'"); }

function openModal(title) {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modal').classList.add('open');
    document.getElementById('counterName').focus();
}

document.getElementById('addBtn').addEventListener('click', () => {
    document.getElementById('editId').value = '';
    document.getElementById('counterName').value = '';
    document.getElementById('modalBranch').value = '';
    document.getElementById('isActive').checked = true;
    openModal('Add Counter');
});

document.getElementById('cancelBtn').addEventListener('click', () => {
    document.getElementById('modal').classList.remove('open');
});

function editCounter(id, name, branchId, isActive) {
    document.getElementById('editId').value = id;
    document.getElementById('counterName').value = name;
    document.getElementById('modalBranch').value = branchId;
    document.getElementById('isActive').checked = isActive == 1;
    openModal('Edit Counter');
}

document.getElementById('saveBtn').addEventListener('click', async () => {
    const id = document.getElementById('editId').value;
    const name = document.getElementById('counterName').value.trim();
    const branchId = document.getElementById('modalBranch').value;
    const isActive = document.getElementById('isActive').checked ? 1 : 0;

    if (!name || !branchId) { showToast('Counter name and branch are required', true); return; }

    const url = id
        ? '../../../../server/api/master_setup/counter_setup/counter-edit.php'
        : '../../../../server/api/master_setup/counter_setup/counter-add.php';

    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id || undefined, counter_name: name, branch_id: branchId, is_active: isActive })
    });
    const data = await res.json();
    if (data.success) {
        document.getElementById('modal').classList.remove('open');
        showToast(data.message);
        loadCounters();
    } else {
        showToast(data.message, true);
    }
});

async function deleteCounter(id, name) {
    if (!confirm(`Delete counter "${name}"?`)) return;
    const res = await fetch('../../../../server/api/master_setup/counter_setup/counter-delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    const data = await res.json();
    showToast(data.message, !data.success);
    if (data.success) loadCounters();
}

function showToast(msg, isError = false) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show' + (isError ? ' error' : '');
    setTimeout(() => t.classList.remove('show'), 3000);
}

['branchFilter', 'statusFilter'].forEach(id => document.getElementById(id).addEventListener('change', loadCounters));
document.getElementById('searchInput').addEventListener('input', loadCounters);

loadBranches().then(loadCounters);
</script>
</body>
</html>