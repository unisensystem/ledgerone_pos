let leaveTypesCache = [];
let currentActionLeaveId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadLeaveTypes();
    loadLeaveRequests();
});

function showAlert(message, type) {
    const container = document.getElementById('alertContainer');
    const div = document.createElement('div');
    div.className = `alert alert-${type}`;
    div.textContent = message; // textContent only — never render server/user text as HTML
    container.innerHTML = '';
    container.appendChild(div);
    setTimeout(() => { div.remove(); }, 5000);
}

async function loadLeaveTypes() {
    const res = await fetch('../../../../server/api/hrm/leave/leave-types.php');
    const data = await res.json();
    if (!data.success) return;
    leaveTypesCache = data.leave_types;
    const select = document.getElementById('filterLeaveType');
    leaveTypesCache.forEach(lt => {
        const opt = document.createElement('option');
        opt.value = lt.id;
        opt.textContent = lt.name;
        select.appendChild(opt);
    });
}

function clearFilters() {
    document.getElementById('filterEmployee').value = '';
    document.getElementById('filterLeaveType').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterStartDate').value = '';
    document.getElementById('filterEndDate').value = '';
    loadLeaveRequests();
}

async function loadLeaveRequests() {
    const params = new URLSearchParams();
    const employee = document.getElementById('filterEmployee').value.trim();
    const status = document.getElementById('filterStatus').value;
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;
    if (employee) params.set('employee_id', employee);
    if (status) params.set('status', status);
    if (startDate) params.set('start_date', startDate);
    if (endDate) params.set('end_date', endDate);

    const tbody = document.getElementById('leaveTableBody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center">Loading...</td></tr>';

    try {
        const res = await fetch(`../../../../server/api/hrm/leave/leave-request.php?${params.toString()}`);
        const data = await res.json();
        if (!data.success) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center">Failed to load</td></tr>';
            return;
        }

        const leaveTypeFilter = document.getElementById('filterLeaveType').value;
        let requests = data.requests;
        if (leaveTypeFilter) requests = requests.filter(r => String(r.leave_type_id) === leaveTypeFilter);

        updateStats(requests);
        renderTable(requests);
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">Error loading requests</td></tr>';
    }
}

function updateStats(requests) {
    document.getElementById('statPending').textContent = requests.filter(r => r.status === 'pending').length;
    document.getElementById('statApproved').textContent = requests.filter(r => r.status === 'approved').length;
    document.getElementById('statRejected').textContent = requests.filter(r => r.status === 'rejected').length;
}

function renderTable(requests) {
    const tbody = document.getElementById('leaveTableBody');
    tbody.innerHTML = '';

    if (!requests.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No leave requests match these filters</td></tr>';
        return;
    }

    requests.forEach(r => {
        const row = document.createElement('tr');

        const cells = [
            String(r.id),
            `${r.full_name || r.employee_id} (${r.employee_id})`,
            r.leave_type_name || r.leave_type,
            `${r.start_date} to ${r.end_date}`,
            String(r.total_days) + (r.is_half_day == 1 ? ' (half-day)' : ''),
            r.reason || '',
        ];
        cells.forEach(text => {
            const td = document.createElement('td');
            td.textContent = text;
            row.appendChild(td);
        });

        const statusTd = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = `badge badge-${r.status}`;
        badge.textContent = r.status;
        statusTd.appendChild(badge);
        row.appendChild(statusTd);

        const returnedTd = document.createElement('td');
        if (r.actual_return_date) {
            returnedTd.textContent = r.actual_return_date + (Number(r.overstay_days) > 0 ? ` (+${r.overstay_days}d overstay)` : '');
        } else if (r.status === 'approved') {
            returnedTd.textContent = 'Not yet returned';
        } else {
            returnedTd.textContent = '-';
        }
        row.appendChild(returnedTd);

        const actionsTd = document.createElement('td');
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'action-buttons';

        if (r.status === 'pending') {
            actionsDiv.appendChild(makeButton('btn-success', 'fa-check', 'Approve', () => approveLeave(r.id)));
            actionsDiv.appendChild(makeButton('btn-danger', 'fa-times', 'Reject', () => openReject(r.id)));
        }
        if (r.status === 'approved' && !r.actual_return_date) {
            actionsDiv.appendChild(makeButton('btn-primary', 'fa-door-open', 'Return', () => openReturn(r.id, r.end_date, r.leave_type_name)));
            actionsDiv.appendChild(makeButton('btn-secondary', 'fa-ban', 'Cancel', () => openCancel(r.id)));
        }
        if (r.status === 'pending') {
            actionsDiv.appendChild(makeButton('btn-secondary', 'fa-ban', 'Cancel', () => openCancel(r.id)));
        }
        if (actionsDiv.children.length === 0) {
            actionsDiv.textContent = '-';
        }
        actionsTd.appendChild(actionsDiv);
        row.appendChild(actionsTd);

        tbody.appendChild(row);
    });
}

function makeButton(cls, icon, label, onClick) {
    const btn = document.createElement('button');
    btn.className = `btn btn-sm ${cls}`;
    const i = document.createElement('i');
    i.className = `fas ${icon}`;
    btn.appendChild(i);
    btn.appendChild(document.createTextNode(' ' + label));
    btn.onclick = onClick;
    return btn;
}

function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

async function approveLeave(leaveId, override) {
    if (!override && !confirm('Approve this leave request?')) return;
    try {
        const res = await fetch('../../../../server/api/hrm/leave/leave-approve.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leave_id: leaveId, decision: 'approve', override: !!override })
        });
        const data = await res.json();
        if (!data.success && data.message && data.message.includes('already has recorded attendance')) {
            // The employee already has real attendance on some of these days — ask
            // HR to explicitly confirm before it gets replaced with leave.
            if (confirm(data.message + '\n\nApprove and replace anyway?')) {
                approveLeave(leaveId, true);
            }
            return;
        }
        showAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) loadLeaveRequests();
    } catch (e) {
        showAlert('Error approving leave', 'error');
    }
}

function openReject(leaveId) {
    currentActionLeaveId = leaveId;
    document.getElementById('rejectReason').value = '';
    openModal('rejectModal');
}

async function confirmReject() {
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) { alert('Please enter a rejection reason'); return; }
    try {
        const res = await fetch('../../../../server/api/hrm/leave/leave-approve.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leave_id: currentActionLeaveId, decision: 'reject', remarks: reason })
        });
        const data = await res.json();
        showAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) { closeModal('rejectModal'); loadLeaveRequests(); }
    } catch (e) {
        showAlert('Error rejecting leave', 'error');
    }
}

function openCancel(leaveId) {
    currentActionLeaveId = leaveId;
    document.getElementById('cancelReason').value = '';
    document.getElementById('cancelWarning').style.display = 'none';
    document.getElementById('cancelAckBox').style.display = 'none';
    document.getElementById('cancelAcknowledge').checked = false;
    openModal('cancelModal');
}

async function confirmCancel() {
    const reason = document.getElementById('cancelReason').value.trim();
    if (!reason) { alert('Please enter a cancellation reason'); return; }
    const ack = document.getElementById('cancelAcknowledge').checked;

    try {
        const res = await fetch('../../../../server/api/hrm/leave/leave-cancel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leave_id: currentActionLeaveId, reason, acknowledge_finalized_payroll: ack })
        });
        const data = await res.json();
        if (data.success) {
            showAlert(data.warning || data.message, data.warning ? 'error' : 'success');
            closeModal('cancelModal');
            loadLeaveRequests();
        } else if (data.message && data.message.includes('finalized payroll')) {
            // Show the specific finalized-payroll warning inline with an acknowledge checkbox,
            // instead of just failing silently.
            const warnBox = document.getElementById('cancelWarning');
            warnBox.style.display = 'block';
            warnBox.textContent = data.message;
            document.getElementById('cancelAckBox').style.display = 'block';
        } else {
            showAlert(data.message, 'error');
        }
    } catch (e) {
        showAlert('Error cancelling leave', 'error');
    }
}

function openReturn(leaveId, plannedEndDate, leaveTypeName) {
    currentActionLeaveId = leaveId;
    document.getElementById('returnLeaveInfo').textContent = `${leaveTypeName || 'Leave'} was approved to end on ${plannedEndDate}.`;
    document.getElementById('returnActualDate').value = new Date().toISOString().split('T')[0];
    openModal('returnModal');
}

async function confirmReturn() {
    const actualReturnDate = document.getElementById('returnActualDate').value;
    if (!actualReturnDate) { alert('Please select the actual return date'); return; }
    try {
        const res = await fetch('../../../../server/api/hrm/leave/leave-return.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leave_id: currentActionLeaveId, actual_return_date: actualReturnDate })
        });
        const data = await res.json();
        showAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) { closeModal('returnModal'); loadLeaveRequests(); }
    } catch (e) {
        showAlert('Error recording return', 'error');
    }
}
