/* Biometric Devices management page */

const API_DEVICES   = '/server/api/hrm/biometric/biometric-devices.php';
const API_MAPPINGS  = '/server/api/hrm/biometric/biometric-mappings.php';
const API_LOGS      = '/server/api/hrm/biometric/biometric-logs.php';
const API_EMPLOYEES = '/server/api/hrm/attendance/get_employees.php';

// ── State ──────────────────────────────────────────────────────────────────

let allDevices  = [];  // cached for edit modal lookup
let allMappings = [];  // cached for edit modal lookup

// ── Toast ──────────────────────────────────────────────────────────────────

let toastTimer;
function toast(msg, type = 'success') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = `show ${type}`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 3200);
}

// ── Modals ─────────────────────────────────────────────────────────────────

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});

// ── Tabs ───────────────────────────────────────────────────────────────────

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
        if (btn.dataset.tab === 'logs') loadLogs();
    });
});

// ── XSS-safe escaping ──────────────────────────────────────────────────────

function esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// ── Devices ────────────────────────────────────────────────────────────────

async function loadDevices() {
    const tbody = document.getElementById('devices-tbody');
    try {
        const res  = await fetch(API_DEVICES);
        const json = await res.json();

        allDevices = json.data || [];

        if (!json.success || !allDevices.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="no-data">No devices registered yet</td></tr>';
            return;
        }

        tbody.innerHTML = allDevices.map((d, i) => {
            const masked   = d.api_token.substring(0, 8) + '••••••••••••••••••••' + d.api_token.slice(-4);
            const badgeCls = d.is_active == 1 ? 'badge-active' : 'badge-inactive';
            const badgeTxt = d.is_active == 1 ? 'Active' : 'Inactive';
            const lastSync = d.last_sync ? new Date(d.last_sync).toLocaleString() : '—';
            return `
            <tr>
                <td>${i + 1}</td>
                <td><strong>${esc(d.device_name)}</strong></td>
                <td>${d.device_location ? esc(d.device_location) : '<span style="color:var(--subtext)">—</span>'}</td>
                <td><span class="badge ${badgeCls}">${badgeTxt}</span></td>
                <td style="font-size:12px;color:var(--subtext)">${lastSync}</td>
                <td>
                    <div class="token-row">
                        <span class="token-box" style="font-size:11px;padding:4px 8px">${masked}</span>
                        <button class="btn-icon btn-copy" title="Copy full token"
                                onclick="copyFullToken('${esc(d.api_token)}')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </td>
                <td>
                    <button class="btn-icon btn-edit" title="Edit" data-id="${d.id}" onclick="openEditDevice(${d.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon btn-del" title="Delete" onclick="deleteDevice(${d.id}, '${esc(d.device_name)}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');

    } catch {
        tbody.innerHTML = '<tr><td colspan="7" class="no-data">Failed to load devices</td></tr>';
    }
}

document.getElementById('btn-add-device').addEventListener('click', async () => {
    const name     = document.getElementById('dev-name').value.trim();
    const location = document.getElementById('dev-location').value.trim();

    if (!name) { toast('Device name is required', 'error'); return; }

    const res  = await fetch(API_DEVICES, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ device_name: name, device_location: location }),
    });
    const json = await res.json();

    if (json.success) {
        document.getElementById('dev-name').value     = '';
        document.getElementById('dev-location').value = '';
        await loadDevices();
        showNewToken(json.data.api_token);
    } else {
        toast(json.message || 'Failed to register device', 'error');
    }
});

function showNewToken(token) {
    document.getElementById('new-token-display').textContent = token;
    openModal('modal-token');
}

function copyToken() {
    const token = document.getElementById('new-token-display').textContent;
    navigator.clipboard.writeText(token).then(() => toast('Token copied!'));
}

function copyFullToken(token) {
    navigator.clipboard.writeText(token).then(() => toast('Token copied to clipboard'));
}

function openEditDevice(id) {
    const d = allDevices.find(x => x.id == id);
    if (!d) return;
    document.getElementById('edit-dev-id').value       = d.id;
    document.getElementById('edit-dev-name').value     = d.device_name;
    document.getElementById('edit-dev-location').value = d.device_location || '';
    document.getElementById('edit-dev-active').value   = d.is_active;
    openModal('modal-edit-device');
}

document.getElementById('btn-save-device').addEventListener('click', async () => {
    const id       = parseInt(document.getElementById('edit-dev-id').value);
    const name     = document.getElementById('edit-dev-name').value.trim();
    const location = document.getElementById('edit-dev-location').value.trim();
    const active   = document.getElementById('edit-dev-active').value;

    if (!name) { toast('Device name is required', 'error'); return; }

    const res  = await fetch(API_DEVICES, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, device_name: name, device_location: location, is_active: active }),
    });
    const json = await res.json();

    if (json.success) {
        closeModal('modal-edit-device');
        toast('Device updated');
        loadDevices();
    } else {
        toast(json.message || 'Failed to update', 'error');
    }
});

document.getElementById('btn-regen-token').addEventListener('click', async () => {
    if (!confirm('Regenerate token? The old token stops working immediately.')) return;

    const id       = parseInt(document.getElementById('edit-dev-id').value);
    const name     = document.getElementById('edit-dev-name').value.trim();
    const location = document.getElementById('edit-dev-location').value.trim();
    const active   = document.getElementById('edit-dev-active').value;

    const res  = await fetch(API_DEVICES, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, device_name: name, device_location: location,
                               is_active: active, regenerate_token: true }),
    });
    const json = await res.json();

    if (json.success) {
        closeModal('modal-edit-device');
        await loadDevices();
        showNewToken(json.data.api_token);
    } else {
        toast(json.message || 'Failed to regenerate token', 'error');
    }
});

async function deleteDevice(id, name) {
    if (!confirm(`Delete device "${name}"? This cannot be undone.`)) return;

    const res  = await fetch(API_DEVICES, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
    });
    const json = await res.json();

    if (json.success) { toast('Device deleted'); loadDevices(); }
    else toast(json.message || 'Failed to delete', 'error');
}

// ── Employee list (shared by both mapping selects) ─────────────────────────

async function loadEmployees() {
    try {
        const res  = await fetch(API_EMPLOYEES);
        const json = await res.json();
        const employees = json.data || [];

        const opts = employees.map(e =>
            `<option value="${esc(e.employee_id)}">${esc(e.full_name)} (${esc(e.employee_id)})</option>`
        ).join('');

        ['map-employee', 'edit-map-employee'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = '<option value="">— Select employee —</option>' + opts;
        });
    } catch { /* non-fatal */ }
}

// ── Employee Mappings ──────────────────────────────────────────────────────

async function loadMappings() {
    const tbody = document.getElementById('mappings-tbody');
    try {
        const res  = await fetch(API_MAPPINGS);
        const json = await res.json();

        allMappings = json.data || [];

        if (!json.success || !allMappings.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="no-data">No employee mappings yet</td></tr>';
            return;
        }

        tbody.innerHTML = allMappings.map(m => `
            <tr>
                <td><strong>${esc(m.machine_employee_id)}</strong></td>
                <td>${m.full_name ? esc(m.full_name) : '<span style="color:var(--subtext)">—</span>'}</td>
                <td style="font-family:monospace;font-size:12px">${esc(m.employee_id)}</td>
                <td style="color:var(--subtext)">${m.department_name ? esc(m.department_name) : '—'}</td>
                <td style="font-size:12px;color:var(--subtext)">${new Date(m.created_at).toLocaleDateString()}</td>
                <td>
                    <button class="btn-icon btn-edit" title="Edit" onclick="openEditMapping(${m.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon btn-del" title="Remove" onclick="deleteMapping(${m.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`).join('');

    } catch {
        tbody.innerHTML = '<tr><td colspan="6" class="no-data">Failed to load mappings</td></tr>';
    }
}

document.getElementById('btn-add-mapping').addEventListener('click', async () => {
    const machineId  = document.getElementById('map-machine-id').value.trim();
    const employeeId = document.getElementById('map-employee').value;

    if (!machineId || !employeeId) { toast('Machine ID and employee are required', 'error'); return; }

    const res  = await fetch(API_MAPPINGS, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ machine_employee_id: machineId, employee_id: employeeId }),
    });
    const json = await res.json();

    if (json.success) {
        document.getElementById('map-machine-id').value = '';
        document.getElementById('map-employee').value   = '';
        toast('Mapping added');
        loadMappings();
    } else {
        toast(json.message || 'Failed to add mapping', 'error');
    }
});

function openEditMapping(id) {
    const m = allMappings.find(x => x.id == id);
    if (!m) return;
    document.getElementById('edit-map-id').value         = m.id;
    document.getElementById('edit-map-machine-id').value = m.machine_employee_id;
    document.getElementById('edit-map-employee').value   = m.employee_id;
    openModal('modal-edit-mapping');
}

document.getElementById('btn-save-mapping').addEventListener('click', async () => {
    const id         = parseInt(document.getElementById('edit-map-id').value);
    const machineId  = document.getElementById('edit-map-machine-id').value.trim();
    const employeeId = document.getElementById('edit-map-employee').value;

    if (!machineId || !employeeId) { toast('All fields are required', 'error'); return; }

    const res  = await fetch(API_MAPPINGS, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, machine_employee_id: machineId, employee_id: employeeId }),
    });
    const json = await res.json();

    if (json.success) {
        closeModal('modal-edit-mapping');
        toast('Mapping updated');
        loadMappings();
    } else {
        toast(json.message || 'Failed to update', 'error');
    }
});

async function deleteMapping(id) {
    if (!confirm('Remove this mapping?')) return;

    const res  = await fetch(API_MAPPINGS, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
    });
    const json = await res.json();

    if (json.success) { toast('Mapping removed'); loadMappings(); }
    else toast(json.message || 'Failed to remove', 'error');
}

// ── Push Log ───────────────────────────────────────────────────────────────

async function loadLogs() {
    const tbody  = document.getElementById('logs-tbody');
    const status = document.getElementById('log-status-filter').value;
    const date   = document.getElementById('log-date-filter').value;

    tbody.innerHTML = '<tr><td colspan="8" class="no-data"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>';

    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (date)   params.set('date', date);

    try {
        const res  = await fetch(API_LOGS + '?' + params.toString());
        const json = await res.json();

        if (!json.success || !json.data.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="no-data">No log entries found for this filter</td></tr>';
            return;
        }

        tbody.innerHTML = json.data.map(l => {
            const statusCls = `status-${l.status}`;
            return `
            <tr>
                <td style="font-size:12px;color:var(--subtext)">${new Date(l.created_at).toLocaleString()}</td>
                <td>${esc(l.device_name || '—')}</td>
                <td><strong>${esc(l.machine_employee_id)}</strong></td>
                <td style="font-family:monospace;font-size:12px">${l.employee_id ? esc(l.employee_id) : '—'}</td>
                <td>${esc(l.punch_time)}</td>
                <td style="color:var(--subtext)">${l.punch_type_raw !== null ? esc(l.punch_type_raw) : '—'}</td>
                <td><span class="${statusCls}">${esc(l.status)}</span></td>
                <td style="font-size:12px;color:var(--subtext)">${l.note ? esc(l.note) : '—'}</td>
            </tr>`;
        }).join('');

    } catch {
        tbody.innerHTML = '<tr><td colspan="8" class="no-data">Failed to load logs</td></tr>';
    }
}

document.getElementById('btn-load-logs').addEventListener('click', loadLogs);

// ── Push URL copy ──────────────────────────────────────────────────────────

function copyPushUrl() {
    const text = document.getElementById('push-url-display').textContent.trim();
    navigator.clipboard.writeText(text).then(() => toast('URL copied to clipboard'));
}

// ── Init ───────────────────────────────────────────────────────────────────

document.getElementById('log-date-filter').value = new Date().toISOString().split('T')[0];

loadDevices();
loadMappings();
loadEmployees();
