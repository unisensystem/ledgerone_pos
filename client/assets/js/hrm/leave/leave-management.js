// ============================================
// Leave Management System
// Simple, clean, and professional
// ============================================

const API_URL = '../../../../server/api/hrm/leave/leave-request.php';

let employees = [];
let leaveTypes = [];
let allRequests = [];

let selectedEmployee = null;
let selectedType = null;
let rangeStart = null;
let rangeEnd = null;
let calView = new Date();
let payMode = 'paid';
let editingRecordId = null;

// ============ HELPERS ============

const escape = (str) => String(str).replace(/[&<>"']/g, c => 
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])
);

const format = (n) => Number.isInteger(n) ? String(n) : n.toFixed(1);

const money = (n, currency = '') => 
    `${currency} ${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`.trim();

const dayText = (n) => `${format(n)} day${n === 1 ? '' : 's'}`;

const isOvertime = (type) => type && type.kind === 'overtime';

const getInitials = (name) => name.split(' ').map(n => n[0]).join('').toUpperCase();

const toDate = (d) => {
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${m}-${day}`;
};

const sameDay = (a, b) => a && b && toDate(a) === toDate(b);

// ============ API CALLS ============

async function apiCall(action, payload = {}) {
    const isBootstrap = action === 'bootstrap';
    const url = isBootstrap ? `${API_URL}?action=bootstrap` : API_URL;
    const options = isBootstrap 
        ? { method: 'GET' }
        : { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, ...payload }) };
    
    const response = await fetch(url, options);
    return response.json();
}

// ============ INITIALIZE ============

async function init() {
    const data = await apiCall('bootstrap');
    if (!data.success) {
        alert(data.message || 'Failed to load leave data');
        return;
    }
    
    employees = data.employees;
    leaveTypes = data.leave_types;
    allRequests = data.recent_requests || [];
    selectedType = leaveTypes[0];
    
    renderEmployeeList();
    renderTypeList();
    selectType(selectedType);
    renderCalendar();
    renderRecentRequests(allRequests);
}

init();

// ============ EMPLOYEE SELECTION ============

const empList = document.getElementById('empList');
const empCombo = document.getElementById('empCombo');
const empComboTrigger = document.getElementById('empComboTrigger');
const empComboLabel = document.getElementById('empComboLabel');
const empSearch = document.getElementById('empSearch');

function renderEmployeeList(filter = '') {
    const text = filter.toLowerCase();
    empList.innerHTML = '';
    
    const filtered = employees.filter(e => 
        !text || e.name.toLowerCase().includes(text) || 
        e.id.toLowerCase().includes(text) || 
        e.dept.toLowerCase().includes(text)
    );
    
    if (!filtered.length) {
        empList.innerHTML = '<div class="empty-row">No employees found</div>';
        return;
    }
    
    filtered.forEach(emp => {
        const row = document.createElement('div');
        row.className = 'emp-row' + (selectedEmployee?.id === emp.id ? ' selected' : '');
        row.innerHTML = `
            <div class="emp-avatar">${getInitials(emp.name)}</div>
            <div>
                <div class="emp-name">${escape(emp.name)}</div>
                <div class="emp-meta">${escape(emp.id)} • ${escape(emp.dept)}</div>
            </div>
        `;
        row.onclick = () => selectEmployee(emp);
        empList.appendChild(row);
    });
}

function selectEmployee(emp) {
    selectedEmployee = emp;
    if (!editingRecordId) editingRecordId = null;
    
    renderEmployeeList(empSearch.value);
    empComboLabel.innerHTML = `<span class="picked"><span class="emp-avatar">${getInitials(emp.name)}</span>${escape(emp.name)} — ${escape(emp.id)}</span>`;
    empComboLabel.classList.remove('placeholder');
    closeCombo();
    renderTypeList();
    updateRateInfo();
    updateDaysOut();
    updateSubmitState();
}

function openCombo() {
    empCombo.classList.add('open');
    empSearch.focus();
}

function closeCombo() {
    empCombo.classList.remove('open');
}

empComboTrigger.onclick = () => empCombo.classList.contains('open') ? closeCombo() : openCombo();
document.addEventListener('click', ev => { if (!empCombo.contains(ev.target)) closeCombo(); });
empSearch.addEventListener('input', ev => renderEmployeeList(ev.target.value));
empSearch.addEventListener('click', ev => ev.stopPropagation());

// ============ LEAVE TYPE SELECTION ============

const typeList = document.getElementById('typeList');

function renderTypeList() {
    typeList.innerHTML = '';
    leaveTypes.forEach(type => {
        const card = document.createElement('div');
        card.className = 'type-card' + (selectedType?.id === type.id ? ' active' : '');
        card.innerHTML = `
            <div class="type-icon"><i class="fas ${type.icon}"></i></div>
            <div class="type-name">${escape(type.name)}</div>
        `;
        card.onclick = () => selectType(type);
        typeList.appendChild(card);
    });
}

function selectType(type) {
    selectedType = type;
    if (!editingRecordId) editingRecordId = null;
    
    renderTypeList();
    document.getElementById('modeTag').innerHTML = `<i class="fas ${type.icon}"></i> ${escape(type.name)}`;
    
    const isOT = isOvertime(type);
    document.getElementById('rangeMode').style.display = isOT ? 'none' : 'block';
    document.getElementById('otMode').style.display = isOT ? 'block' : 'none';
    document.getElementById('payModeRow').style.display = isOT ? 'none' : 'block';
    
    updateRateInfo();
    setPayMode('paid');
    updateDaysOut();
    updateSubmitState();
}

// ============ PAYMENT MODE ============

const payModeSelect = document.getElementById('payModeSelect');
const splitBox = document.getElementById('splitBox');
const splitUnpaidInput = document.getElementById('splitUnpaidInput');

function setPayMode(mode, unpaidDays = 0) {
    payMode = mode;
    payModeSelect.value = mode;
    splitBox.style.display = mode === 'split' ? 'block' : 'none';
    if (mode === 'split') splitUnpaidInput.value = unpaidDays;
    updateDaysOut();
}

payModeSelect.addEventListener('change', () => {
    if (!rangeStart) {
        alert('Please select dates first');
        payModeSelect.value = payMode;
        return;
    }
    payMode = payModeSelect.value;
    splitBox.style.display = payMode === 'split' ? 'block' : 'none';
    updateDaysOut();
});

splitUnpaidInput.addEventListener('input', updateDaysOut);

// ============ CALENDAR ============

const calGrid = document.getElementById('calGrid');
const calLabel = document.getElementById('calLabel');
const daysOfWeek = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

function renderCalendar() {
    calGrid.innerHTML = '';
    
    daysOfWeek.forEach(day => {
        const el = document.createElement('div');
        el.className = 'dow';
        el.textContent = day;
        calGrid.appendChild(el);
    });

    const year = calView.getFullYear();
    const month = calView.getMonth();
    
    calLabel.textContent = calView.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    
    const firstDay = new Date(year, month, 1);
    const startOffset = firstDay.getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    // Previous month days
    for (let i = startOffset - 1; i >= 0; i--) {
        const el = document.createElement('div');
        el.className = 'cal-day muted';
        el.textContent = daysInPrevMonth - i;
        calGrid.appendChild(el);
    }

    // Current month days
    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(year, month, d);
        const el = document.createElement('div');
        el.className = 'cal-day';
        el.textContent = d;
        
        if (rangeStart && sameDay(date, rangeStart)) el.classList.add('range-start');
        if (rangeEnd && sameDay(date, rangeEnd)) el.classList.add('range-end');
        if (rangeStart && rangeEnd && date > rangeStart && date < rangeEnd) el.classList.add('in-range');
        
        el.onclick = () => pickDate(date);
        calGrid.appendChild(el);
    }

    // Next month days
    const totalCells = startOffset + daysInMonth;
    const trailing = (7 - (totalCells % 7)) % 7;
    for (let d = 1; d <= trailing; d++) {
        const el = document.createElement('div');
        el.className = 'cal-day muted';
        el.textContent = d;
        calGrid.appendChild(el);
    }
}

function pickDate(date) {
    if (!rangeStart || (rangeStart && rangeEnd)) {
        rangeStart = date;
        rangeEnd = null;
    } else if (sameDay(date, rangeStart)) {
        rangeEnd = date;
    } else if (date < rangeStart) {
        rangeEnd = rangeStart;
        rangeStart = date;
    } else {
        rangeEnd = date;
    }
    
    renderCalendar();
    updateDaysOut();
    updateSubmitState();
}

document.getElementById('calPrev').onclick = () => {
    calView = new Date(calView.getFullYear(), calView.getMonth() - 1, 1);
    renderCalendar();
};

document.getElementById('calNext').onclick = () => {
    calView = new Date(calView.getFullYear(), calView.getMonth() + 1, 1);
    renderCalendar();
};

function countDays() {
    if (!rangeStart) return 0;
    if (document.getElementById('halfDay').checked) return 0.5;
    
    const end = rangeEnd || rangeStart;
    let count = 0;
    const cursor = new Date(rangeStart);
    
    while (cursor <= end) {
        count++;
        cursor.setDate(cursor.getDate() + 1);
    }
    return count;
}

document.getElementById('halfDay').addEventListener('change', () => {
    if (rangeStart) rangeEnd = rangeStart;
    renderCalendar();
    updateDaysOut();
});

// ============ RATE DISPLAY ============

function updateRateInfo() {
    const el = document.getElementById('rateInfo');
    if (!el) return;
    
    if (!selectedEmployee) {
        el.style.display = 'none';
        return;
    }
    
    el.style.display = 'block';
    const rateType = isOvertime(selectedType) ? 'Hourly' : 'Daily';
    const rateValue = isOvertime(selectedType) ? selectedEmployee.hourly_rate : selectedEmployee.daily_rate;
    
    el.innerHTML = `<i class="fas fa-coins"></i> ${rateType} rate: <b>${money(rateValue, selectedEmployee.currency)}</b>`;
}

// ============ DAYS SUMMARY ============

function updateDaysOut() {
    const out = document.getElementById('daysOut');
    out.className = 'days-out';
    
    if (!rangeStart) {
        out.textContent = 'Pick a date to see the total';
        return;
    }
    
    const total = countDays();
    document.getElementById('splitTotalDays').textContent = format(total);
    splitUnpaidInput.max = total;
    
    if (parseFloat(splitUnpaidInput.value) > total) {
        splitUnpaidInput.value = total;
    }

    if (!selectedEmployee) {
        out.textContent = `${dayText(total)} — select an employee to see the amount`;
        return;
    }

    let paidDays, unpaidDays, label;
    
    if (payMode === 'unpaid') {
        paidDays = 0;
        unpaidDays = total;
        label = 'All Unpaid';
    } else if (payMode === 'split') {
        unpaidDays = Math.min(Math.max(parseFloat(splitUnpaidInput.value) || 0, 0), total);
        paidDays = Math.round((total - unpaidDays) * 10) / 10;
        label = `${format(paidDays)} Paid + ${format(unpaidDays)} Unpaid`;
    } else {
        paidDays = total;
        unpaidDays = 0;
        label = 'All Paid';
    }

    if (unpaidDays > 0 && paidDays === 0) {
        out.classList.add('unpaid-out');
    }
    
    const amount = paidDays > 0 ? ` · est. ${money(selectedEmployee.daily_rate * paidDays, selectedEmployee.currency)}` : '';
    out.textContent = `${dayText(total)} — ${label}${amount} (${selectedType.name})`;
}

// ============ SUBMIT ============

function updateSubmitState() {
    const btn = document.getElementById('submitBtn');
    const isValid = selectedEmployee && selectedType && (isOvertime(selectedType) ? true : !!rangeStart);
    btn.disabled = !isValid;
    btn.textContent = editingRecordId ? '💾 Update' : '✈️ Submit';
}

['otDate', 'otHours'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', updateSubmitState);
});

document.getElementById('submitBtn').onclick = () => submitLeave();

async function submitLeave() {
    const reason = document.getElementById('reason').value.trim();
    if (!reason) {
        alert('Please enter a reason');
        return;
    }

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;

    let payload = {
        employee_id: selectedEmployee.id,
        leave_type_id: selectedType.id,
        reason,
        contact: document.getElementById('contact').value.trim(),
        approval_status: document.getElementById('approvalStatus').value,
    };

    if (isOvertime(selectedType)) {
        const date = document.getElementById('otDate').value;
        const hours = document.getElementById('otHours').value;
        
        if (!date || !hours) {
            alert('Enter overtime date and hours');
            updateSubmitState();
            return;
        }
        
        payload.ot_date = date;
        payload.ot_hours = hours;
        payload.ot_rate = document.getElementById('otRate').value;
    } else {
        if (!rangeStart) {
            updateSubmitState();
            return;
        }
        
        const end = rangeEnd || rangeStart;
        payload.start_date = toDate(rangeStart);
        payload.end_date = toDate(end);
        payload.is_half_day = document.getElementById('halfDay').checked;
        payload.pay_mode = payMode;
        
        if (payMode === 'split') {
            payload.unpaid_days = parseFloat(splitUnpaidInput.value) || 0;
        }
    }

    if (editingRecordId) {
        payload.id = editingRecordId;
    }

    const action = editingRecordId ? 'edit' : 'submit';
    const response = await apiCall(action, payload);

    if (!response.success) {
        alert(response.message || 'Could not submit request');
        updateSubmitState();
        return;
    }

    setTimeout(() => {
        window.location.reload();
    }, 500);
}

// ============ EDIT & DELETE ============

async function editRequest(recordId) {
    const request = allRequests.find(r => r.id === recordId);
    if (!request) {
        alert('Request not found');
        return;
    }

    const emp = employees.find(e => e.id === request.employee_id);
    if (emp) selectEmployee(emp);

    const type = leaveTypes.find(t => t.id === parseInt(request.leave_type_id || 0));
    if (type) selectType(type);

    document.getElementById('reason').value = request.reason || '';
    document.getElementById('contact').value = request.contact_during_leave || '';
    document.getElementById('approvalStatus').value = request.status === 'approved' ? 'approved' : 'pending';

    if (request.ot_hours !== null && request.ot_hours !== undefined) {
        document.getElementById('otDate').value = request.start_date;
        document.getElementById('otHours').value = request.ot_hours;
        
        if (request.ot_rate_multiplier) {
            const rateMap = { 1: '1x', 1.5: '1.5x', 2: '2x' };
            document.getElementById('otRate').value = rateMap[request.ot_rate_multiplier] || '1x';
        }
    } else if (request.start_date && request.end_date) {
        rangeStart = new Date(request.start_date + 'T00:00:00');
        rangeEnd = new Date(request.end_date + 'T00:00:00');
        renderCalendar();

        const total = request.total_days || 0;
        const unpaid = request.unpaid_days || 0;
        
        if (total > 0 && unpaid >= total) {
            setPayMode('unpaid');
        } else if (unpaid > 0) {
            setPayMode('split', unpaid);
        } else {
            setPayMode('paid');
        }
    }

    editingRecordId = recordId;
    updateSubmitState();
    document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
}

async function deleteRequest(recordId) {
    if (!confirm('Delete this leave request?')) return;

    const response = await apiCall('delete', { id: recordId });
    if (!response.success) {
        alert(response.message || 'Could not delete request');
        return;
    }

    const data = await apiCall('bootstrap');
    allRequests = data.recent_requests || [];
    applyRequestFilters();
}

// ============ REQUESTS TABLE ============

const reqTableWrap = document.getElementById('reqTableWrap');
const reqFilters = document.getElementById('reqFilters');
const reqToggleBtn = document.getElementById('reqToggleBtn');
let reqTableHidden = false;

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

// ============ COLUMN VISIBILITY ============

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

// ============ FILTERS ============

function applyRequestFilters() {
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

document.getElementById('reqFilterName').addEventListener('input', applyRequestFilters);
document.getElementById('reqFilterFrom').addEventListener('change', applyRequestFilters);
document.getElementById('reqFilterTo').addEventListener('change', applyRequestFilters);
document.getElementById('reqFilterStatus').addEventListener('change', applyRequestFilters);
document.getElementById('reqFilterClear').addEventListener('click', () => {
    document.getElementById('reqFilterName').value = '';
    document.getElementById('reqFilterFrom').value = '';
    document.getElementById('reqFilterTo').value = '';
    document.getElementById('reqFilterStatus').value = '';
    applyRequestFilters();
});

// ============ RENDER REQUESTS TABLE ============

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
        const currency = emp ? emp.currency : '';
        const isOT = r.ot_hours !== null;

        const dateStr = isOT ? r.start_date : `${r.start_date} → ${r.end_date}`;
        const totalStr = isOT
            ? `${format(r.ot_hours)} hrs @ ${format(r.ot_rate_multiplier)}x`
            : dayText(r.total_days || 0);
        
        const paidStr = isOT
            ? 'Paid'
            : ((r.unpaid_days || 0) > 0
                ? ((r.paid_days || 0) > 0 ? `${format(r.paid_days)} Paid + ${format(r.unpaid_days)} Unpaid` : 'Unpaid')
                : 'Paid');
        
        const statusLabel = r.status === 'approved' ? 'Approved' : (r.status === 'rejected' ? 'Rejected' : 'Pending');

        // Build amount cell
        let amountHtml = '<span class="amt-zero">—</span>';
        if (isOT) {
            amountHtml = `<span class="amt-plus">+${money(r.ot_amount || 0, currency)}</span>`;
        } else {
            const rate = parseFloat(r.daily_rate || 0);
            const paid = parseFloat(r.paid_days || 0);
            const unpaid = parseFloat(r.unpaid_days || 0);
            
            if (paid > 0) amountHtml = `<span class="amt-plus">+${money(rate * paid, currency)}</span>`;
            if (unpaid > 0) amountHtml += `${amountHtml !== '<span class="amt-zero">—</span>' ? '<br>' : ''}<span class="amt-minus">-${money(rate * unpaid, currency)}</span>`;
        }

        // Build action buttons - PRINT ONLY FOR APPROVED
        let actionsHtml = `<button class="req-btn-edit" onclick="editRequest(${r.id})" title="Edit"><i class="fas fa-edit"></i></button>
                           <button class="req-btn-delete" onclick="deleteRequest(${r.id})" title="Delete"><i class="fas fa-trash"></i></button>`;
        
        if (r.status === 'approved') {
            actionsHtml += `<a class="req-btn-print" href="leave-print.php?id=${r.id}" target="_blank" title="Print"><i class="fas fa-print"></i></a>`;
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escape(empName)}</td>
            <td><span class="type-pill"><span class="dot"></span>${escape(r.type_name)}</span></td>
            <td>${escape(paidStr)}</td>
            <td>${escape(dateStr)}</td>
            <td>${escape(totalStr)}</td>
            <td>${amountHtml}</td>
            <td><span class="status-pill status-${r.status}">${statusLabel}</span></td>
            <td>${actionsHtml}</td>
        `;
        body.appendChild(tr);
    });
}

// ============ STYLES ============

const style = document.createElement('style');
style.textContent = `
    .req-btn-edit, .req-btn-delete, .req-btn-print {
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
    
    .req-btn-print { color: #10b981; }
    .req-btn-print:hover { color: #059669; }

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