/* =========================================================================
   Leave Management — wired to server/api/hrm/leave/leave-request.php
   SIMPLIFIED: Only 4 fixed leave types (Annual, Casual, Sick, Over Time).
   No custom leave types. Payment for a request is chosen manually via a
   Paid / Unpaid / Split dropdown — no quota tracking, no excess-approval flow.
   Includes edit/delete for pending requests + name/date/status filters +
   per-column hide/unhide.
   ========================================================================= */

const API_URL = '../../../../server/api/hrm/leave/leave-request.php';

let employees = [];
let leaveTypes = [];
let allRequests = []; // Store all requests for edit/delete operations

let selectedEmployee = null;
let selectedType = null;
let rangeStart = null, rangeEnd = null;
let calView = new Date();
let payMode = 'paid'; // 'paid' | 'unpaid' | 'split' — how this request is paid
let editingRecordId = null; // Track if we're editing an existing record

/* ---------- Helpers ---------- */
function esc(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
function fmt(n) { return Number.isInteger(n) ? String(n) : n.toFixed(1); }
function money(n, cur) { return `${cur || ''} ${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`.trim(); }
function dayWord(n) { return `${fmt(n)} day${n === 1 ? '' : 's'}`; }
function isOT(t) { return t && t.kind === 'overtime'; }

function amountCell(r, cur) {
    if (r.ot_hours !== null && r.ot_hours !== undefined) {
        return `<span class="amt-plus">+${money(r.ot_amount || 0, cur)}</span>`;
    }
    const rate = parseFloat(r.daily_rate || 0);
    const paid = parseFloat(r.paid_days || 0);
    const unpaid = parseFloat(r.unpaid_days || 0);
    let html = '';
    if (paid > 0) html += `<span class="amt-plus">+${money(rate * paid, cur)}</span>`;
    if (unpaid > 0) html += `${html ? '<br>' : ''}<span class="amt-minus">-${money(rate * unpaid, cur)}</span>`;
    return html || '<span class="amt-zero">—</span>';
}

function initials(name) { return name.split(' ').map(n => n[0]).join('').toUpperCase(); }
function ymd(d) { const m = String(d.getMonth() + 1).padStart(2, '0'); const day = String(d.getDate()).padStart(2, '0'); return `${d.getFullYear()}-${m}-${day}`; }
function sameDay(a, b) { return a && b && ymd(a) === ymd(b); }

async function api(action, payload) {
    const opts = action === 'bootstrap'
        ? { method: 'GET' }
        : { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, ...payload }) };
    const url = action === 'bootstrap' ? `${API_URL}?action=bootstrap` : API_URL;
    const res = await fetch(url, opts);
    return res.json();
}

/* ---------- Bootstrap ---------- */
async function init() {
    const data = await api('bootstrap');
    if (!data.success) { alert(data.message || 'Failed to load leave data'); return; }
    employees = data.employees;
    leaveTypes = data.leave_types;
    allRequests = data.recent_requests || [];
    selectedType = leaveTypes[0];
    renderEmpList();
    renderTypeList();
    selectType(selectedType);
    renderCalendar();
    renderRecentRequests(allRequests);
}
init();

/* ---------- Employee combobox ---------- */
const empList = document.getElementById('empList');
const empCombo = document.getElementById('empCombo');
const empComboTrigger = document.getElementById('empComboTrigger');
const empComboLabel = document.getElementById('empComboLabel');

function renderEmpList(filter = '') {
    const f = filter.toLowerCase();
    empList.innerHTML = '';
    employees
        .filter(e => !f || e.name.toLowerCase().includes(f) || e.id.toLowerCase().includes(f) || e.dept.toLowerCase().includes(f))
        .forEach(e => {
            const row = document.createElement('div');
            row.className = 'emp-row' + (selectedEmployee && selectedEmployee.id === e.id ? ' selected' : '');
            row.innerHTML = `<div class="emp-avatar">${initials(e.name)}</div>
                <div><div class="emp-name">${esc(e.name)}</div><div class="emp-meta">${esc(e.id)} • ${esc(e.dept)}</div></div>`;
            row.onclick = () => selectEmployee(e);
            empList.appendChild(row);
        });
    if (!employees.length) empList.innerHTML = '<div class="empty-row">No employees found</div>';
}

function selectEmployee(e) {
    selectedEmployee = e;
    // Don't reset editingRecordId — let edit mode continue if already active
    if (!editingRecordId) {  // Only reset if NOT editing
        editingRecordId = null;
    }
    renderEmpList(document.getElementById('empSearch').value);
    empComboLabel.innerHTML = `<span class="picked"><span class="emp-avatar">${initials(e.name)}</span>${esc(e.name)} — ${esc(e.id)}</span>`;
    empComboLabel.classList.remove('placeholder');
    closeCombo();
    renderTypeList();
    updateRateInfo();
    updateDaysOut();
    updateSubmitState();
}

function openCombo() { empCombo.classList.add('open'); document.getElementById('empSearch').focus(); }
function closeCombo() { empCombo.classList.remove('open'); }
empComboTrigger.onclick = () => empCombo.classList.contains('open') ? closeCombo() : openCombo();
document.addEventListener('click', ev => { if (!empCombo.contains(ev.target)) closeCombo(); });
document.getElementById('empSearch').addEventListener('input', ev => renderEmpList(ev.target.value));
document.getElementById('empSearch').addEventListener('click', ev => ev.stopPropagation());

/* ---------- Leave types (fixed: Annual, Casual, Sick, Over Time) ---------- */
const typeList = document.getElementById('typeList');

function renderTypeList() {
    typeList.innerHTML = '';
    leaveTypes.forEach(t => {
        const card = document.createElement('div');
        card.className = 'type-card' + (selectedType && selectedType.id === t.id ? ' active' : '');
        card.innerHTML = `
            <div class="type-icon"><i class="fas ${t.icon}"></i></div>
            <div class="type-name">${esc(t.name)}</div>
        `;
        card.onclick = () => selectType(t);
        typeList.appendChild(card);
    });
}

function selectType(t) {
    selectedType = t;
    // Don't reset editingRecordId — let edit mode continue if already active
    if (!editingRecordId) {  // Only reset if NOT editing
        editingRecordId = null;
    }
    renderTypeList();
    const modeTag = document.getElementById('modeTag');
    modeTag.innerHTML = `<i class="fas ${t.icon}"></i> ${esc(t.name)}`;
    document.getElementById('rangeMode').style.display = isOT(t) ? 'none' : 'block';
    document.getElementById('otMode').style.display = isOT(t) ? 'block' : 'none';
    document.getElementById('payModeRow').style.display = isOT(t) ? 'none' : 'block';
    updateRateInfo();
    setPayMode('paid');
    updateDaysOut();
    updateSubmitState();
}

/* ---------- Payment mode (Paid / Unpaid / Split) ---------- */
const payModeSelect = document.getElementById('payModeSelect');
const splitBox = document.getElementById('splitBox');
const splitUnpaidInput = document.getElementById('splitUnpaidInput');

function setPayMode(mode, unpaidVal = 0) {
    payMode = mode;
    payModeSelect.value = mode;
    splitBox.style.display = mode === 'split' ? 'block' : 'none';
    if (mode === 'split') splitUnpaidInput.value = unpaidVal;
    updateDaysOut();
}

payModeSelect.addEventListener('change', () => {
    // Agar dates select nahi hain toh message dikhao aur dropdown reset karo
    if (!rangeStart) {
        alert('Please select dates from calendar first');
        payModeSelect.value = payMode; // purani value restore karo
        return;
    }
    payMode = payModeSelect.value;
    splitBox.style.display = payMode === 'split' ? 'block' : 'none';
    updateDaysOut();
});
splitUnpaidInput.addEventListener('input', updateDaysOut);

/* ---------- Calendar ---------- */
const calGrid = document.getElementById('calGrid');
const calLabel = document.getElementById('calLabel');
const dows = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

function renderCalendar() {
    calGrid.innerHTML = '';
    dows.forEach(d => { const el = document.createElement('div'); el.className = 'dow'; el.textContent = d; calGrid.appendChild(el); });

    const year = calView.getFullYear(), month = calView.getMonth();
    calLabel.textContent = calView.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    const firstDay = new Date(year, month, 1);
    const startOffset = firstDay.getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    for (let i = startOffset - 1; i >= 0; i--) {
        const el = document.createElement('div'); el.className = 'cal-day muted'; el.textContent = daysInPrevMonth - i; calGrid.appendChild(el);
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(year, month, d);
        const el = document.createElement('div'); el.className = 'cal-day'; el.textContent = d;
        if (rangeStart && sameDay(date, rangeStart)) el.classList.add('range-start');
        if (rangeEnd && sameDay(date, rangeEnd)) el.classList.add('range-end');
        if (rangeStart && rangeEnd && date > rangeStart && date < rangeEnd) el.classList.add('in-range');
        el.onclick = () => pickDate(date);
        calGrid.appendChild(el);
    }
    const totalCells = startOffset + daysInMonth;
    const trailing = (7 - (totalCells % 7)) % 7;
    for (let d = 1; d <= trailing; d++) {
        const el = document.createElement('div'); el.className = 'cal-day muted'; el.textContent = d; calGrid.appendChild(el);
    }
}

function pickDate(date) {
    if (!rangeStart || (rangeStart && rangeEnd)) { rangeStart = date; rangeEnd = null; }
    else if (sameDay(date, rangeStart)) { rangeEnd = date; }
    else if (date < rangeStart) { rangeEnd = rangeStart; rangeStart = date; }
    else { rangeEnd = date; }
    renderCalendar();
    updateDaysOut();
    updateSubmitState();
}

document.getElementById('calPrev').onclick = () => { calView = new Date(calView.getFullYear(), calView.getMonth() - 1, 1); renderCalendar(); };
document.getElementById('calNext').onclick = () => { calView = new Date(calView.getFullYear(), calView.getMonth() + 1, 1); renderCalendar(); };

function countDays() {
    if (!rangeStart) return 0;
    if (document.getElementById('halfDay').checked) return 0.5;
    const end = rangeEnd || rangeStart;
    let count = 0;
    const cursor = new Date(rangeStart);
    while (cursor <= end) { count++; cursor.setDate(cursor.getDate() + 1); }
    return count;
}

document.getElementById('halfDay').addEventListener('change', () => {
    if (rangeStart) rangeEnd = rangeStart;
    renderCalendar();
    updateDaysOut();
});

/* ---------- Rate info ---------- */
function updateRateInfo() {
    const el = document.getElementById('rateInfo');
    if (!el) return;
    if (!selectedEmployee) { el.textContent = ''; el.style.display = 'none'; return; }
    el.style.display = 'block';
    if (isOT(selectedType)) {
        el.innerHTML = `<i class="fas fa-coins"></i> Hourly rate: <b>${money(selectedEmployee.hourly_rate, selectedEmployee.currency)}</b> (from salary)`;
    } else {
        el.innerHTML = `<i class="fas fa-coins"></i> Daily rate: <b>${money(selectedEmployee.daily_rate, selectedEmployee.currency)}</b> (from salary)`;
    }
}

/* ---------- Days-out summary ---------- */
function updateDaysOut() {
    const out = document.getElementById('daysOut');
    out.className = 'days-out';
    if (!rangeStart) { out.textContent = 'Pick a date to see the total'; return; }
    const total = countDays();

    document.getElementById('splitTotalDays').textContent = fmt(total);
    splitUnpaidInput.max = total;
    if (parseFloat(splitUnpaidInput.value) > total) splitUnpaidInput.value = total;

    if (!selectedEmployee) { out.textContent = `${dayWord(total)} — select an employee to see the amount`; return; }

    let paidDays, unpaidDays, label;
    if (payMode === 'unpaid') {
        paidDays = 0; unpaidDays = total; label = 'All Unpaid';
    } else if (payMode === 'split') {
        unpaidDays = Math.min(Math.max(parseFloat(splitUnpaidInput.value) || 0, 0), total);
        paidDays = round1(total - unpaidDays);
        label = `${fmt(paidDays)} Paid + ${fmt(unpaidDays)} Unpaid`;
    } else {
        paidDays = total; unpaidDays = 0; label = 'All Paid';
    }

    if (unpaidDays > 0 && paidDays === 0) out.classList.add('unpaid-out');
    const amountNote = paidDays > 0 ? ` · est. ${money(selectedEmployee.daily_rate * paidDays, selectedEmployee.currency)}` : '';
    out.textContent = `${dayWord(total)} — ${label}${amountNote} (${selectedType.name})`;
}
function round1(n) { return Math.round(n * 10) / 10; }

/* ---------- Submit ---------- */
function updateSubmitState() {
    const btn = document.getElementById('submitBtn');
    const ok = selectedEmployee && selectedType && (isOT(selectedType) ? true : !!rangeStart);
    btn.disabled = !ok;
    btn.textContent = editingRecordId ? '💾 Update Request' : '✈️ Submit Request';
}

['otDate', 'otHours'].forEach(id => document.getElementById(id)?.addEventListener('input', updateSubmitState));

document.getElementById('submitBtn').onclick = () => submitLeave();

async function submitLeave() {
    const reason = document.getElementById('reason').value.trim();
    if (!reason) { alert('Please enter a reason'); return; }

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;

    let payload = {
        employee_id: selectedEmployee.id,
        leave_type_id: selectedType.id,
        reason,
        contact: document.getElementById('contact').value.trim(),
        approval_status: document.getElementById('approvalStatus').value,
    };

    if (isOT(selectedType)) {
        const d = document.getElementById('otDate').value;
        const h = document.getElementById('otHours').value;
        if (!d || !h) { alert('Enter overtime date and hours'); updateSubmitState(); return; }
        payload.ot_date = d;
        payload.ot_hours = h;
        payload.ot_rate = document.getElementById('otRate').value;
    } else {
        if (!rangeStart) { updateSubmitState(); return; }
        const end = rangeEnd || rangeStart;
        payload.start_date = ymd(rangeStart);
        payload.end_date = ymd(end);
        payload.is_half_day = document.getElementById('halfDay').checked;
        payload.pay_mode = payMode;
        if (payMode === 'split') {
            payload.unpaid_days = parseFloat(splitUnpaidInput.value) || 0;
        }
    }

    if (editingRecordId) payload.id = editingRecordId;

    const action = editingRecordId ? 'edit' : 'submit';
    const res = await api(action, payload);

    if (!res.success) {
        alert(res.message || 'Could not submit the request');
        updateSubmitState();
        return;
    }

    // ✅ Auto-refresh page on successful submit/edit
    setTimeout(() => {
        window.location.reload();
    }, 500);
}

/* ---------- Edit & Delete ---------- */
async function editRequest(recordId) {
    const req = allRequests.find(r => r.id === recordId);
    if (!req) { alert('Request not found'); return; }

    const emp = employees.find(e => e.id === req.employee_id);
    if (emp) selectEmployee(emp);

    const type = leaveTypes.find(t => t.id === parseInt(req.leave_type_id || 0));
    if (type) selectType(type);

    document.getElementById('reason').value = req.reason || '';
    document.getElementById('contact').value = req.contact_during_leave || '';
    document.getElementById('approvalStatus').value = req.status === 'approved' ? 'approved' : 'pending';

    if (req.ot_hours !== null && req.ot_hours !== undefined) {
        document.getElementById('otDate').value = req.start_date;
        document.getElementById('otHours').value = req.ot_hours;
        if (req.ot_rate_multiplier) {
            const rateMap = { 1: '1x', 1.5: '1.5x', 2: '2x' };
            document.getElementById('otRate').value = rateMap[req.ot_rate_multiplier] || '1x';
        }
    } else if (req.start_date && req.end_date) {
        rangeStart = new Date(req.start_date + 'T00:00:00');
        rangeEnd = new Date(req.end_date + 'T00:00:00');
        renderCalendar();

        const total = req.total_days || 0;
        const unpaid = req.unpaid_days || 0;
        if (total > 0 && unpaid >= total) setPayMode('unpaid');
        else if (unpaid > 0) setPayMode('split', unpaid);
        else setPayMode('paid');
    }

    editingRecordId = recordId;
    updateSubmitState();

    document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
}

async function deleteRequest(recordId) {
    if (!confirm('Delete this leave request? This cannot be undone.')) return;

    const res = await api('delete', { id: recordId });
    if (!res.success) { alert(res.message || 'Could not delete request'); return; }

    const data = await api('bootstrap');
    allRequests = data.recent_requests || [];
    applyReqFilters();
}

/* ---------- Requests table: always visible, manual hide/show ---------- */
const reqTableWrap = document.getElementById('reqTableWrap');
const reqFilters = document.getElementById('reqFilters');
const reqToggleBtn = document.getElementById('reqToggleBtn');
let reqTableHidden = false;

reqTableWrap.style.display = 'block';
reqFilters.style.display = 'flex';

function updateReqToggleBtn() {
    reqToggleBtn.innerHTML = reqTableHidden
        ? '<i class="fas fa-eye"></i> Show'
        : '<i class="fas fa-eye-slash"></i> Hide';
}
reqToggleBtn.onclick = () => {
    reqTableHidden = !reqTableHidden;
    reqTableWrap.style.display = reqTableHidden ? 'none' : 'block';
    reqFilters.style.display = reqTableHidden ? 'none' : 'flex';
    updateReqToggleBtn();
};
function openReqTable() {
    reqTableHidden = false;
    reqTableWrap.style.display = 'block';
    reqFilters.style.display = 'flex';
    updateReqToggleBtn();
}

/* ---------- Column visibility (per-column hide/unhide) ---------- */
const reqTable = document.getElementById('reqTable');
const colToggle = document.getElementById('colToggle');
const colToggleBtn = document.getElementById('colToggleBtn');

colToggleBtn.onclick = (ev) => {
    ev.stopPropagation();
    colToggle.classList.toggle('open');
};
document.addEventListener('click', ev => {
    if (!colToggle.contains(ev.target)) colToggle.classList.remove('open');
});
document.querySelectorAll('.col-check').forEach(cb => {
    cb.addEventListener('change', () => {
        const col = cb.dataset.col;
        reqTable.classList.toggle(`hide-col-${col}`, !cb.checked);
    });
});

/* ---------- Filters ---------- */
function applyReqFilters() {
    const name = document.getElementById('reqFilterName').value.trim().toLowerCase();
    const from = document.getElementById('reqFilterFrom').value;
    const to = document.getElementById('reqFilterTo').value;
    const status = document.getElementById('reqFilterStatus').value;

    const filtered = allRequests.filter(r => {
        if (status && r.status !== status) return false;
        const rStart = r.start_date;
        const rEnd = r.end_date || r.start_date;
        if (from && rEnd < from) return false;
        if (to && rStart > to) return false;
        if (name) {
            const emp = employees.find(e => e.id === r.employee_id);
            const empName = (emp ? emp.name : (r.employee_name || r.employee_id || '')).toLowerCase();
            if (!empName.includes(name)) return false;
        }
        return true;
    });
    renderRecentRequests(filtered);
}
document.getElementById('reqFilterName').addEventListener('input', applyReqFilters);
document.getElementById('reqFilterFrom').addEventListener('change', applyReqFilters);
document.getElementById('reqFilterTo').addEventListener('change', applyReqFilters);
document.getElementById('reqFilterStatus').addEventListener('change', applyReqFilters);
document.getElementById('reqFilterClear').addEventListener('click', () => {
    document.getElementById('reqFilterName').value = '';
    document.getElementById('reqFilterFrom').value = '';
    document.getElementById('reqFilterTo').value = '';
    document.getElementById('reqFilterStatus').value = '';
    applyReqFilters();
});

/* Render requests */
function renderRecentRequests(rows) {
    const body = document.getElementById('reqBody');
    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="8" class="empty-row">No requests match these filters</td></tr>';
        return;
    }
    body.innerHTML = '';
    rows.forEach(r => {
        const emp = employees.find(e => e.id === r.employee_id);
        const empName = emp ? emp.name : (r.employee_name || r.employee_id);
        const cur = emp ? emp.currency : '';
        const isOt = r.ot_hours !== null;

        const dateStr = isOt ? r.start_date : `${r.start_date} → ${r.end_date}`;
        const totalStr = isOt
            ? `${fmt(r.ot_hours)} hrs @ ${fmt(r.ot_rate_multiplier)}x`
            : dayWord(r.total_days || 0);
        const paidStr = isOt
            ? 'Paid'
            : ((r.unpaid_days || 0) > 0
                ? ((r.paid_days || 0) > 0 ? `${fmt(r.paid_days)} Paid + ${fmt(r.unpaid_days)} Unpaid` : 'Unpaid')
                : 'Paid');
        const statusLabel = r.status === 'approved' ? 'Approved' : (r.status === 'rejected' ? 'Rejected' : 'Pending');

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${esc(empName)}</td>
            <td><span class="type-pill"><span class="dot"></span>${esc(r.type_name)}</span></td>
            <td>${esc(paidStr)}</td>
            <td>${esc(dateStr)}</td>
            <td>${esc(totalStr)}</td>
            <td>${amountCell(r, cur)}</td>
            <td><span class="status-pill status-${r.status}">${statusLabel}</span></td>
            <td>
<button class="req-btn-edit" onclick="editRequest(${r.id})" title="Edit"><i class="fas fa-edit"></i></button>
<button class="req-btn-delete" onclick="deleteRequest(${r.id})" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
        `;
        body.appendChild(tr);
    });
}

// Add styles for edit/delete buttons + per-column hide/unhide panel + pay-mode split box
const style = document.createElement('style');
style.textContent = `
    .req-btn-edit, .req-btn-delete {
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px 8px;
        margin: 0 2px;
        font-size: 14px;
        transition: color 0.2s;
    }
    .req-btn-edit { color: #3b82f6; }
    .req-btn-edit:hover { color: #1d4ed8; }
    .req-btn-delete { color: #ef4444; }
    .req-btn-delete:hover { color: #dc2626; }

    .req-head-actions { display: flex; align-items: center; gap: 8px; }
    .col-toggle { position: relative; display: inline-block; }
    .col-panel {
        display: none;
        position: absolute;
        right: 0;
        top: calc(100% + 4px);
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 12px;
        box-shadow: 0 4px 14px rgba(0,0,0,.1);
        z-index: 30;
        min-width: 160px;
    }
    .col-toggle.open .col-panel { display: block; }
    .col-panel label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        padding: 4px 0;
        cursor: pointer;
        white-space: nowrap;
    }
    .leave-table.hide-col-1 th:nth-child(1), .leave-table.hide-col-1 td:nth-child(1) { display: none; }
    .leave-table.hide-col-2 th:nth-child(2), .leave-table.hide-col-2 td:nth-child(2) { display: none; }
    .leave-table.hide-col-3 th:nth-child(3), .leave-table.hide-col-3 td:nth-child(3) { display: none; }
    .leave-table.hide-col-4 th:nth-child(4), .leave-table.hide-col-4 td:nth-child(4) { display: none; }
    .leave-table.hide-col-5 th:nth-child(5), .leave-table.hide-col-5 td:nth-child(5) { display: none; }
    .leave-table.hide-col-6 th:nth-child(6), .leave-table.hide-col-6 td:nth-child(6) { display: none; }
    .leave-table.hide-col-7 th:nth-child(7), .leave-table.hide-col-7 td:nth-child(7) { display: none; }
    .leave-table.hide-col-8 th:nth-child(8), .leave-table.hide-col-8 td:nth-child(8) { display: none; }

    #payModeRow { margin-top: 10px; }
    .split-box { margin-top: 8px; }
    .split-box label { display: block; font-size: 13px; margin-bottom: 4px; color: #475569; }
`;
document.head.appendChild(style);