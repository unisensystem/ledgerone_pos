let users = [];
let roles = [];
let employees = [];
let allBranches = [];
let stats = { total: 0, active: 0, blocked: 0 };

// ========== PROGRESS BAR HELPER FUNCTIONS ==========

// Format bytes to MB/GB
function formatBytes(bytes) {
    if (bytes === 0) return '0 MB';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

// Show progress overlay
function showRestoreProgress(title = 'Restoring Database') {
    const overlay = document.getElementById('restoreProgressOverlay');
    if (overlay) {
        document.getElementById('restoreProgressTitle').textContent = title;
        document.getElementById('restoreProgressPercentage').textContent = '0%';
        document.getElementById('restoreProgressBarFill').style.width = '0%';
        document.getElementById('restoreProgressStatus').textContent = 'Preparing to restore...';
        document.getElementById('restoreUploadedSize').textContent = '0 MB';
        document.getElementById('restoreUploadSpeed').textContent = '0 MB/s';
        overlay.classList.remove('success', 'error');
        overlay.classList.add('active');
    }
}

// Update progress bar
function updateRestoreProgress(percentage, status = null, uploaded = null, total = null, speed = null) {
    const overlay = document.getElementById('restoreProgressOverlay');
    if (!overlay) return;

    percentage = Math.min(percentage, 100);
    document.getElementById('restoreProgressPercentage').textContent = Math.round(percentage) + '%';
    document.getElementById('restoreProgressBarFill').style.width = percentage + '%';

    if (status) {
        document.getElementById('restoreProgressStatus').textContent = status;
    }
    if (uploaded) {
        document.getElementById('restoreUploadedSize').textContent = formatBytes(uploaded);
    }
    if (total) {
        document.getElementById('restoreTotalSize').textContent = formatBytes(total);
    }
    if (speed) {
        document.getElementById('restoreUploadSpeed').textContent = speed;
    }
}

// Show success state
function showRestoreSuccess(message = 'Database Restored Successfully!') {
    const overlay = document.getElementById('restoreProgressOverlay');
    if (overlay) {
        overlay.classList.remove('error');
        overlay.classList.add('success');
        document.getElementById('restoreProgressTitle').textContent = message;
        document.getElementById('restoreProgressPercentage').textContent = '100%';
        document.getElementById('restoreProgressBarFill').style.width = '100%';
        document.getElementById('restoreProgressStatus').textContent = 'Completed';
    }
}

// Show error state
function showRestoreError(message = 'Restore Failed') {
    const overlay = document.getElementById('restoreProgressOverlay');
    if (overlay) {
        overlay.classList.remove('success');
        overlay.classList.add('error');
        document.getElementById('restoreProgressTitle').textContent = message;
        document.getElementById('restoreProgressStatus').textContent = 'An error occurred';
    }
}

// Hide progress overlay
function hideRestoreProgress() {
    const overlay = document.getElementById('restoreProgressOverlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

// ========== END PROGRESS BAR FUNCTIONS ==========

// Fetch branches from API
async function fetchBranches() {
    try {
        const response = await fetch('../../../../server/api/system_setup/admin_panel/get-branches.php');
        const data = await response.json();
        if (data.success) allBranches = data.branches;
    } catch (error) {
        console.error('Error fetching branches:', error);
    }
}

// Fetch users from API
async function fetchUsers() {
    try {
        const response = await fetch('../../../../server/api/system_setup/admin_panel/panel.php');
        const data = await response.json();
        if (data.success) {
            users = data.users.map(user => ({
                id: user.id,
                name: user.full_name,
                email: user.email,
                role: user.role_name || 'No Role',
                status: user.is_active ? 'active' : 'blocked',
                lastLogin: user.last_login_at || 'Never',
                employee_id: user.employee_id
            }));
            roles = data.roles || [];
            employees = data.employees || [];
            stats = data.stats;
            updateStats();
            renderUsersTable();
            updateRoleSelects();
            updateEmployeeSelects();
        }
    } catch (error) {
        console.error('Error fetching users:', error);
    }
}

// Update statistics display
function updateStats() {
    document.querySelector('.d-flex.gap-16 > div:nth-child(1) > div:first-child').textContent = stats.total;
    document.querySelector('.d-flex.gap-16 > div:nth-child(2) > div:first-child').textContent = stats.active;
    document.querySelector('.d-flex.gap-16 > div:nth-child(3) > div:first-child').textContent = stats.blocked;
}

// Update role select options
function updateRoleSelects() {
    const selects = document.querySelectorAll('select[name="role"], #roleSelect');
    selects.forEach(select => {
        const currentValue = select.value;
        select.innerHTML = '';
        if (select.name === 'role' && select.closest('#createUserModal')) {
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select Role';
            select.appendChild(defaultOption);
        }
        if (select.id === 'roleSelect') {
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select a role';
            select.appendChild(defaultOption);
        }
        roles.forEach(role => {
            const option = document.createElement('option');
            option.value = role.id;
            option.textContent = role.name;
            if (role.id == currentValue) option.selected = true;
            select.appendChild(option);
        });
    });
}

// Update employee select options
function updateEmployeeSelects() {
    const selects = document.querySelectorAll('select[name="employee_id"]');
    selects.forEach(select => {
        const currentValue = select.value;
        select.innerHTML = '<option value="">Select Employee (Optional)</option>';
        employees.forEach(emp => {
            const option = document.createElement('option');
            option.value = emp.id;
            option.textContent = `${emp.employee_id} - ${emp.full_name}`;
            if (emp.id == currentValue) option.selected = true;
            select.appendChild(option);
        });
    });
}

// Initialize the users table
function renderUsersTable() {
    const tableBody = document.getElementById('usersTableBody');
    tableBody.innerHTML = '';

    users.forEach(user => {
        const row = document.createElement('tr');
        const statusClass = `status-${user.status}`;

        row.innerHTML = `
                    <td>#${user.id.toString().padStart(4, '0')}</td>
                    <td>
                        <div style="font-weight: 500;">${user.name}</div>
                    </td>
                    <td>${user.email}</td>
                    <td>
                        <span class="status-badge" style="background-color: rgba(31, 123, 255, 0.1); color: var(--primary);">
                            ${user.role}
                        </span>
                    </td>
                    <td><span class="status-badge ${statusClass}">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span></td>
                    <td>${user.lastLogin}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-ghost btn-sm btn-icon" onclick="editUser(${user.id})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteUser(${user.id})" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
        tableBody.appendChild(row);
    });
}

// ─── Branch Multi-Select Helper ────────────────────────────────────────────

function initPicker(searchId, dropdownId, tagsId, hiddenId, allItems, getLabel, getTag, preselected = [], emptyHint = '') {
    const searchInput = document.getElementById(searchId);
    const dropdown = document.getElementById(dropdownId);
    const tagsContainer = document.getElementById(tagsId);
    const hiddenInput = document.getElementById(hiddenId);
    let selectedIds = [...preselected];

    function renderTags() {
        tagsContainer.innerHTML = '';
        selectedIds.forEach(id => {
            const item = allItems.find(x => x.id == id);
            if (!item) return;
            const tag = document.createElement('span');
            tag.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:rgba(31,123,255,0.1);color:var(--primary);border-radius:12px;font-size:12px;';
            tag.innerHTML = `${getTag(item)} <button type="button" data-id="${id}" style="background:none;border:none;color:var(--primary);cursor:pointer;font-size:14px;padding:0;line-height:1;">&times;</button>`;
            tag.querySelector('button').addEventListener('click', () => {
                selectedIds = selectedIds.filter(x => x != id);
                updateHidden(); renderTags(); renderDropdown(searchInput.value);
            });
            tagsContainer.appendChild(tag);
        });
        if (selectedIds.length === 0 && emptyHint) {
            const hint = document.createElement('span');
            hint.style.cssText = 'font-size:12px;color:var(--subtext);';
            hint.textContent = emptyHint;
            tagsContainer.appendChild(hint);
        }
    }

    function updateHidden() { hiddenInput.value = JSON.stringify(selectedIds); }

    function renderDropdown(term = '') {
        dropdown.innerHTML = '';
        const filtered = allItems.filter(x => getLabel(x).toLowerCase().includes(term.toLowerCase()) && !selectedIds.includes(x.id));
        if (filtered.length === 0) {
            dropdown.innerHTML = '<div style="padding:8px 12px;color:var(--subtext);font-size:13px;">No results found</div>';
        } else {
            filtered.forEach(x => {
                const item = document.createElement('div');
                item.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:13px;';
                item.textContent = getLabel(x);
                item.addEventListener('mouseenter', () => item.style.background = 'var(--surface-1)');
                item.addEventListener('mouseleave', () => item.style.background = '');
                item.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    selectedIds.push(x.id);
                    updateHidden(); renderTags();
                    searchInput.value = ''; renderDropdown('');
                });
                dropdown.appendChild(item);
            });
        }
    }

    searchInput.addEventListener('focus', () => { renderDropdown(searchInput.value); dropdown.style.display = 'block'; });
    searchInput.addEventListener('input', () => { renderDropdown(searchInput.value); dropdown.style.display = 'block'; });
    searchInput.addEventListener('blur', () => { setTimeout(() => { dropdown.style.display = 'none'; }, 150); });
    updateHidden(); renderTags();
}

function initBranchPicker(searchId, dropdownId, tagsId, hiddenId, preselected = []) {
    initPicker(
        searchId, dropdownId, tagsId, hiddenId,
        allBranches,
        b => b.parent_branch_name ? `${b.parent_branch_name} > ${b.branch_code} - ${b.branch_name} (${b.branch_type})` : `${b.branch_code} - ${b.branch_name} (${b.branch_type})`,
        b => `${b.branch_code} - ${b.branch_name}`,
        preselected,
        'No branch selected — user will have no branch access.'
    );
}

// ─── Modal functions ─────────────────────────────────────────────────────────
function openCreateUserModal() {
    document.getElementById('createUserModal').style.display = 'flex';
    document.getElementById('createBranchIds').value = '[]';
    document.getElementById('createSelectedBranches').innerHTML = '';
    document.getElementById('createBranchSearch').value = '';
    document.getElementById('createBranchDropdown').style.display = 'none';
    initBranchPicker('createBranchSearch', 'createBranchDropdown', 'createSelectedBranches', 'createBranchIds', []);
}

function closeCreateUserModal() {
    document.getElementById('createUserModal').style.display = 'none';
    document.getElementById('createUserForm').reset();
    document.getElementById('createSelectedBranches').innerHTML = '';
    document.getElementById('createBranchIds').value = '';
}

async function openEditUserModal(userId) {
    const user = users.find(u => u.id === userId);
    if (!user) return;

    let preselectedBranches = [];
    try {
        const brRes = await fetch(`../../../../server/api/system_setup/admin_panel/user-branch.php?user_id=${userId}`);
        const brData = await brRes.json();
        if (brData.success) preselectedBranches = brData.branches.map(b => b.branch_id);
    } catch (_) { }

    const modalBody = document.querySelector('#editUserModal .modal-body');
    modalBody.innerHTML = `
        <form id="editUserForm">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" id="editFullName" class="form-input" value="${user.name}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="editEmail" class="form-input" value="${user.email}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Employee</label>
                    <select id="editEmployee" class="form-select" name="employee_id">
                        <option value="">Select Employee (Optional)</option>
                        ${employees.map(emp => `<option value="${emp.id}" ${user.employee_id == emp.id ? 'selected' : ''}>${emp.employee_id} - ${emp.full_name}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select id="editRole" class="form-select" name="role">
                        <option value="">Select Role</option>
                        ${roles.map(role => `<option value="${role.id}" ${user.role === role.name ? 'selected' : ''}>${role.name}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="editStatus" class="form-select">
                        <option value="active" ${user.status === 'active' ? 'selected' : ''}>Active</option>
                        <option value="blocked" ${user.status === 'blocked' ? 'selected' : ''}>Blocked</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reset Password</label>
                    <input type="password" id="editPassword" class="form-input" placeholder="Leave blank to keep current password">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Branch Access</label>
                    <div style="position: relative;">
                        <input type="text" id="editBranchSearch" class="form-input" placeholder="Search branches..." autocomplete="off">
                        <div id="editBranchDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:var(--surface-card); border:1px solid var(--border-default); border-radius:var(--radius); max-height:200px; overflow-y:auto; z-index:1050; box-shadow:0 4px 12px rgba(0,0,0,0.1);"></div>
                    </div>
                    <div id="editSelectedBranches" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;"></div>
                    <input type="hidden" id="editBranchIds" value="">
                </div>
            </div>
        </form>
    `;

    document.getElementById('editUserModal').style.display = 'flex';
    window.currentEditingUserId = userId;

    initBranchPicker('editBranchSearch', 'editBranchDropdown', 'editSelectedBranches', 'editBranchIds', preselectedBranches);
}

function closeEditUserModal() {
    document.getElementById('editUserModal').style.display = 'none';
    window.currentEditingUserId = null;
}

function openAccessModal() {
    document.getElementById('accessModal').style.display = 'flex';
    updateRoleSelects();
}

function closeAccessModal() {
    document.getElementById('accessModal').style.display = 'none';
    document.getElementById('roleSelect').value = '';
    document.getElementById('permissionsTree').innerHTML = '';
}

async function loadRolePermissions() {
    const roleId = document.getElementById('roleSelect').value;
    if (!roleId) {
        document.getElementById('permissionsTree').innerHTML = '';
        return;
    }

    try {
        const response = await fetch(`../../../../server/api/system_setup/admin_panel/permissions.php?role_id=${roleId}`);
        const data = await response.json();
        if (data.success) {
            renderPermissionsTree(data.permissions);
        }
    } catch (error) {
        console.error('Error loading permissions:', error);
    }
}

function renderPermissionsTree(permissions) {
    const tree = document.getElementById('permissionsTree');

    const permissionsStructure = {
        'Dashboard': [],
        'Customer / Supplier': ['New Customer', 'New Supplier', 'New Customer + Supplier (Both)', 'Leads'],
        'Sale': ['Sale Order', 'Create Quotation', 'Sale Invoice', 'Grocery Sale Invoice', 'Med Sale Invoice', 'Sale Tax Invoice', 'Meter Invoice', 'POS Invoice', 'Primery Trade Invoice', 'Traders Sale Invoice', 'Issue Delivery Challan', 'Sale Reports', 'Discount Report', 'Daily Sale Report', 'Daily Sales Report (DSR) - Multiple Unit', 'Daily Product Sale', 'Daily Product Sale 2', 'Invoice Payment Report', 'Sale Return'],
        'Purchase': ['Record Purchase Order', 'New Purchase', 'Grocery Purchase Invoice', 'Purchase Tax Invoice', 'Purchase Reports', 'Purchase Return', 'Project Product Rates'],
        'Inventory': ['New Product', 'Stock Adjustment', 'Stock Transfer', 'Stock Position', 'Inward Gatepass', 'Outward Gatepass', 'Container Setup'],
        'Manufacturing': ['Bill of Materials (BOM)', 'Unit Measurement', 'Machine Setup', 'Production Order', 'WIP Management', 'Production Completion', 'Production Expenses', 'Production Wastage', 'Production Report'],
        'Vouchers': ['Receive Voucher', 'Payment Voucher', 'Transfer Voucher', 'Expense Voucher', 'Journal Entry', 'Cash Opening'],
        'Banking': ['New Bank', 'Post Dated Cheques (PDCs)', 'Bank Statement'],
        'Rent Management': ['Issue Rent', 'Rent List'],
        'Chart of Accounts': [],
        'Financial Reports': ['General Ledger', 'Customer Ledger', 'Our Standing', 'Supplier Ledger', 'Both (Customer + Supplier) Ledger', 'Customer Aging', 'Supplier Aging', 'Recovery Sheet', 'Customer Summary Division Report (CSDR)', 'Sales Officer-wise Recovery', 'Trial Balance', 'Balance Sheet', 'Profit & Loss Statement', 'Cash Flow', 'Financial Summary', 'Pending DSR Sheet', 'Project Tracking', 'Container Tracking'],
        'HRM': ['New Employee', 'Record Attendance', 'Leave Management', 'New Payroll', 'Employees Ledger'],
        'Master Setup': ['Branch Setup', 'Territory Setup', 'Rate List Setup', 'Tax Rates Setup', 'Tax Regimes Setup', 'Project Setup'],
'System Setup': ['Company Profile', 'Admin Panel', 'Currency Setup', 'Software Info', 'GL Debug']
    };

    const subPermissions = ['Add', 'Edit', 'Delete'];
    const noSubPermCategories = ['Dashboard', 'Financial Reports'];
    const noSubPermForms = ['Sale Reports', 'Discount Report', 'Purchase Reports', 'Stock Position', 'Software Info', 'Company Profile', 'Employees Ledger', 'Post Dated Cheques (PDCs)', 'Bank Statement', 'Daily Sale Report', 'Daily Sales Report (DSR) - Multiple Unit', 'Daily Product Sale', 'Daily Product Sale 2', 'Invoice Payment Report', 'Bill of Materials (BOM)', 'Production Wastage', 'Production Report', 'Tax Rates Setup', 'Tax Regimes Setup', 'Project Product Rates', 'Container Tracking' , 'GL Debug'];

    // Create permission lookup map
    const permMap = {};
    if (permissions) {
        permissions.forEach(p => {
            const key = `${p.category}|${p.form_name}|${p.sub_permission}`;
            permMap[key] = p;
        });
    }

    let html = '';
    Object.keys(permissionsStructure).forEach(category => {
        html += `<div class="permission-category">
            <div class="category-header" onclick="toggleCategory(this)">
                <i class="fas fa-chevron-down"></i>
                <span>${category}</span>
            </div>
            <div class="category-content">`;

        const forms = permissionsStructure[category];
        const hasSubPerms = !noSubPermCategories.includes(category) && forms.length > 0;

        if (forms.length === 0) {
            const key = `${category}|${category}|Coming Soon`;
            const perm = permMap[key];
            const isChecked = perm && parseInt(perm.allowed) === 1 ? 'checked' : '';
            const permId = perm ? perm.id : '';

            html += `<div class="form-item">
                <div class="form-header">
                    <input type="checkbox" ${isChecked} ${permId ? `data-id="${permId}"` : ''} data-category="${category}" data-form="${category}" data-sub="Coming Soon">
                    <strong>Access</strong>
                </div>
            </div>`;
        } else {
            forms.forEach(form => {
                if (hasSubPerms && !noSubPermForms.includes(form)) {
                    const viewKey = `${category}|${form}|View`;
                    const viewPerm = permMap[viewKey];
                    const viewChecked = viewPerm && parseInt(viewPerm.allowed) === 1 ? 'checked' : '';
                    const viewPermId = viewPerm ? viewPerm.id : '';

                    html += `<div class="form-item">
                        <div class="form-header">
                            <input type="checkbox" ${viewChecked} ${viewPermId ? `data-id="${viewPermId}"` : ''} data-category="${category}" data-form="${form}" data-sub="View">
                            <strong>${form}</strong>
    </div>
    <div class="sub-permissions">`;

                    const formSubPermissions = ['Sale Tax Invoice', 'New Purchase'].includes(form)
                        ? ['Add', 'Edit', 'Delete', 'Duplicate']
                        : ['Add', 'Edit', 'Delete'];

                    formSubPermissions.forEach(sub => {
                        const key = `${category}|${form}|${sub}`;
                        const perm = permMap[key];
                        const isChecked = perm && parseInt(perm.allowed) === 1 ? 'checked' : '';
                        const permId = perm ? perm.id : '';

                        html += `<div class="permission-item">
                            <input type="checkbox" id="perm_${category}_${form}_${sub}" ${isChecked} ${permId ? `data-id="${permId}"` : ''} data-category="${category}" data-form="${form}" data-sub="${sub}">
                            <label for="perm_${category}_${form}_${sub}">${sub}</label>
                        </div>`;
                    });

                    html += `</div></div>`;
                } else {
                    const key = `${category}|${form}|Coming Soon`;
                    const perm = permMap[key];
                    const isChecked = perm && parseInt(perm.allowed) === 1 ? 'checked' : '';
                    const permId = perm ? perm.id : '';

                    html += `<div class="form-item">
                        <div class="form-header">
                            <input type="checkbox" ${isChecked} ${permId ? `data-id="${permId}"` : ''} data-category="${category}" data-form="${form}" data-sub="Coming Soon">
                            <strong>${form}</strong>
                        </div>
                    </div>`;
                }
            });
        }

        html += `</div></div>`;
    });

    tree.innerHTML = html;
}

function toggleCategory(header) {
    const content = header.nextElementSibling;
    const icon = header.querySelector('i');
    content.style.display = content.style.display === 'none' ? 'block' : 'none';
    icon.className = content.style.display === 'none' ? 'fas fa-chevron-right' : 'fas fa-chevron-down';
}

function openFormatModal() {
    document.getElementById('formatModal').style.display = 'flex';
    const confirmInput = document.getElementById('confirmFormat');
    const formatBtn = document.getElementById('formatBtn');

    confirmInput.addEventListener('input', function () {
        formatBtn.disabled = this.value !== 'CONFIRM';
        if (this.value === 'CONFIRM') {
            formatBtn.style.opacity = '1';
            formatBtn.style.cursor = 'pointer';
        } else {
            formatBtn.style.opacity = '0.5';
            formatBtn.style.cursor = 'not-allowed';
        }
    });

    // Reset on open
    confirmInput.value = '';
    formatBtn.disabled = true;
    formatBtn.style.opacity = '0.5';
    formatBtn.style.cursor = 'not-allowed';
}

function closeFormatModal() {
    document.getElementById('formatModal').style.display = 'none';
    document.getElementById('confirmFormat').value = '';
    document.getElementById('formatBtn').disabled = true;
}

function openBackupModal() {
    // Set default backup name with current date
    const now = new Date();
    const dateStr = now.toISOString().split('T')[0].replace(/-/g, '_');
    document.getElementById('backupName').value = `backup_${dateStr}`;
    document.getElementById('backupModal').style.display = 'flex';
}

function closeBackupModal() {
    document.getElementById('backupModal').style.display = 'none';
}

function openRestoreModal() {
    document.getElementById('restoreModal').style.display = 'flex';
}

function closeRestoreModal() {
    document.getElementById('restoreModal').style.display = 'none';
    document.getElementById('restoreFile').value = '';
}

// ⭐ UPDATED RESTORE FUNCTION WITH PROGRESS TRACKING
async function restoreDatabase() {
    const fileInput = document.getElementById('restoreFile');
    const file = fileInput.files[0];

    if (!file) {
        alert('Please select a backup file');
        return;
    }

    if (!file.name.endsWith('.sql')) {
        alert('Please select a valid SQL file');
        return;
    }

    if (!confirm('Are you sure you want to restore this backup? All current data will be replaced.')) {
        return;
    }

    const formData = new FormData();
    formData.append('backup_file', file);

    closeRestoreModal();
    showRestoreProgress('Restoring Database');
    updateRestoreProgress(5, 'Uploading file...');

    try {
        const xhr = new XMLHttpRequest();
        const startTime = Date.now();

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 50;
                const speed = (e.loaded / 1024 / 1024) / ((Date.now() - startTime) / 1000);
                updateRestoreProgress(
                    5 + percentComplete,
                    'Uploading file...',
                    e.loaded,
                    e.total,
                    speed.toFixed(2) + ' MB/s'
                );
            }
        });

        // The upload itself is usually near-instant — the real wait is the server
        // running every INSERT statement in the dump, which has no progress signal
        // of its own (this is a single synchronous request/response). Without this,
        // the bar sits frozen at 55% still saying "Uploading file..." for however
        // long the restore actually takes, which looks stuck even when it isn't.
        // Switch the label the moment the upload finishes, then nudge the bar up
        // slowly so it stays visibly alive until the real server response arrives.
        let serverWaitInterval = null;
        xhr.upload.addEventListener('load', () => {
            updateRestoreProgress(55, 'Upload complete — restoring data on the server (large backups can take a while)...');
            let simulated = 55;
            serverWaitInterval = setInterval(() => {
                if (simulated < 70) {
                    simulated += 1;
                    updateRestoreProgress(simulated, 'Restoring data on the server (large backups can take a while)...');
                }
            }, 1000);
        });

        xhr.addEventListener('loadend', async () => {
            if (serverWaitInterval) {
                clearInterval(serverWaitInterval);
                serverWaitInterval = null;
            }
            // NOTE: restore_backup.php sends http_response_code(500) on failure (e.g. a
            // statement in the dump doesn't match the current schema) — it still sends a
            // valid JSON body either way. The old code here only handled xhr.status===200,
            // so on any error response this whole block was skipped: no alert, no
            // hideRestoreProgress() — the modal just sat frozen at whatever percentage it
            // was on forever, looking exactly like a hang even though the server had
            // already responded (often within seconds). Parse the body regardless of
            // status and branch on data.success instead.
            if (xhr.status === 200 || xhr.status === 500 || xhr.status === 400) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    updateRestoreProgress(75, 'Processing data...');

                    await new Promise(resolve => setTimeout(resolve, 500));
                    updateRestoreProgress(90, 'Finalizing...');

                    await new Promise(resolve => setTimeout(resolve, 500));

                    if (data.success) {
                        showRestoreSuccess('Database Restored Successfully!');
                        setTimeout(() => {
                            hideRestoreProgress();
                            alert('Database restored successfully!');
                            location.reload();
                        }, 1500);
                    } else {
                        showRestoreError('Restore Failed: ' + data.message);
                        setTimeout(() => {
                            hideRestoreProgress();
                            alert('Error: ' + data.message);
                        }, 2000);
                    }
                } catch (e) {
                    showRestoreError('Error processing response');
                    setTimeout(() => {
                        hideRestoreProgress();
                        alert('Error: Invalid server response');
                    }, 2000);
                }
            } else {
                // Any other status (network-level failure, unexpected server error with no
                // JSON body, etc.) — surface it instead of leaving the modal frozen.
                showRestoreError('Restore Failed');
                setTimeout(() => {
                    hideRestoreProgress();
                    alert('Error: Server returned status ' + xhr.status + '. Check the server error log for details.');
                }, 2000);
            }
        });

        xhr.addEventListener('error', () => {
            showRestoreError('Connection Error');
            setTimeout(() => {
                hideRestoreProgress();
                alert('Error: Could not connect to server');
            }, 2000);
        });

        xhr.open('POST', '../../../../server/api/backup/restore_backup.php');
        xhr.send(formData);

    } catch (error) {
        showRestoreError('Error');
        setTimeout(() => {
            hideRestoreProgress();
            alert('Error: ' + error.message);
        }, 2000);
    }
}

// ⭐ UPDATED BACKUP FUNCTION WITH PROGRESS TRACKING
async function createBackup() {
    const backupName = document.getElementById('backupName').value;
    const location = document.querySelector('#backupModal select').value;

    if (!backupName) {
        alert('Please enter a backup name');
        return;
    }

    if (location === 'external') {
        closeBackupModal();
        showRestoreProgress('Creating Backup');
        updateRestoreProgress(10, 'Preparing database...');

        try {
            const startTime = Date.now();
            const response = await fetch(`../../../../server/api/backup/generate_backup.php?name=${encodeURIComponent(backupName)}`);

            if (!response.ok) {
                const err = await response.json().catch(() => null);
                showRestoreError('Backup Failed: ' + (err?.message || response.statusText));
                setTimeout(hideRestoreProgress, 2000);
                return;
            }

            const contentType = response.headers.get('Content-Type') || '';
            if (contentType.includes('application/json')) {
                const err = await response.json();
                showRestoreError('Backup Failed: ' + (err?.message || 'Unknown error'));
                setTimeout(hideRestoreProgress, 2000);
                return;
            }

            updateRestoreProgress(50, 'Downloading backup...');

            const blob = await response.blob();
            const fileSize = blob.size;

            updateRestoreProgress(90, 'Saving file...');

            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${backupName}.sql`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);

            const elapsed = (Date.now() - startTime) / 1000;
            const speed = (fileSize / 1024 / 1024) / elapsed;

            updateRestoreProgress(100, 'Backup complete!', fileSize, fileSize, speed.toFixed(2) + ' MB/s');
            showRestoreSuccess('Backup Created Successfully!');

            setTimeout(() => {
                hideRestoreProgress();
                alert('Backup downloaded successfully. Check your Downloads folder.');
            }, 1500);
        } catch (error) {
            showRestoreError('Backup Failed: ' + error.message);
            setTimeout(hideRestoreProgress, 2000);
        }
    } else {
        alert(`Backup "${backupName}" created successfully in cloud storage!`);
        closeBackupModal();
    }
}

// User actions
async function createUser() {
    const form = document.getElementById('createUserForm');
    const formData = new FormData(form);
    const roleId = formData.get('role');

    // Parse selected branch IDs
    let branchIds = [];
    try {
        branchIds = JSON.parse(document.getElementById('createBranchIds').value || '[]');
    } catch (_) { branchIds = []; }

    try {
        // Step 1 — create user
        const response = await fetch('../../../../server/api/system_setup/admin_panel/user-add.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (!data.success) {
            alert('Error: ' + data.message);
            return;
        }

        const newUserId = data.user_id;

        // Step 2 — assign role (if selected)
        if (roleId && newUserId) {
            const roleResponse = await fetch('../../../../server/api/system_setup/admin_panel/user-role.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: newUserId, role_id: parseInt(roleId) })
            });
            const roleData = await roleResponse.json();
            if (!roleData.success) {
                alert('User created but role assignment failed: ' + roleData.message);
                closeCreateUserModal();
                fetchUsers();
                return;
            }
        }

        // Step 3 — save branch access
        if (branchIds.length > 0 && newUserId) {
            const branchResponse = await fetch('../../../../server/api/system_setup/admin_panel/user-branch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: newUserId, branch_ids: branchIds })
            });
            const branchData = await branchResponse.json();
            if (!branchData.success) {
                alert('User created but branch access assignment failed: ' + branchData.message);
                closeCreateUserModal(); fetchUsers(); return;
            }
        }

        alert('User created successfully!');
        closeCreateUserModal();
        fetchUsers();
    } catch (error) {
        alert('Error creating user');
    }
}

function editUser(userId) {
    openEditUserModal(userId);
}

async function saveUser() {
    if (!window.currentEditingUserId) return;

    const userId = window.currentEditingUserId;
    const fullName = document.getElementById('editFullName').value;
    const email = document.getElementById('editEmail').value;
    const employeeId = document.getElementById('editEmployee').value;
    const roleId = document.getElementById('editRole').value;
    const status = document.getElementById('editStatus').value;
    const password = document.getElementById('editPassword').value;

    let branchIds = [];
    try { branchIds = JSON.parse(document.getElementById('editBranchIds').value || '[]'); } catch (_) { }

    try {
        // Step 1 — update user details
        const response = await fetch('../../../../server/api/system_setup/admin_panel/user-edit.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: userId,
                full_name: fullName,
                email: email,
                employee_id: employeeId || null,
                is_active: status === 'active' ? 1 : 0,
                password: password
            })
        });
        const data = await response.json();
        if (!data.success) {
            alert('Error: ' + data.message);
            return;
        }

        // Step 2 — assign role (if selected)
        if (roleId) {
            const roleResponse = await fetch('../../../../server/api/system_setup/admin_panel/user-role.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: parseInt(userId), role_id: parseInt(roleId) })
            });
            const roleData = await roleResponse.json();
            if (!roleData.success) {
                alert('User updated but role assignment failed: ' + roleData.message + (roleData.debug ? '\n' + roleData.debug : ''));
                closeEditUserModal();
                fetchUsers();
                return;
            }
        }

        // Step 3 — save branch access
        const branchResponse = await fetch('../../../../server/api/system_setup/admin_panel/user-branch.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: parseInt(userId), branch_ids: branchIds })
        });
        const branchData = await branchResponse.json();
        if (!branchData.success) {
            alert('User updated but branch access save failed: ' + branchData.message);
            closeEditUserModal(); fetchUsers(); return;
        }

        alert('User updated successfully!');
        closeEditUserModal();
        fetchUsers();
    } catch (error) {
        console.error('Error:', error);
        alert('Error updating user');
    }
}

async function deleteUser(userId) {
    if (confirm(`Are you sure you want to delete user #${userId}? This action cannot be undone.`)) {
        try {
            const response = await fetch(`../../../../server/api/system_setup/admin_panel/user-delete.php?id=${userId}`, {
                method: 'DELETE'
            });

            const data = await response.json();
            if (data.success) {
                alert('User deleted successfully!');
                fetchUsers();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            alert('Error deleting user');
        }
    }
}

async function toggleBlockUser(userId) {
    const user = users.find(u => u.id === userId);
    if (!user) return;

    const newStatus = user.status === 'blocked' ? 1 : 0;
    const action = newStatus === 0 ? 'block' : 'unblock';

    if (confirm(`Are you sure you want to ${action} ${user.name}?`)) {
        try {
            const response = await fetch('../../../../server/api/system_setup/admin_panel/user-block.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: userId,
                    is_active: newStatus
                })
            });

            const data = await response.json();
            if (data.success) {
                alert(`User ${action}ed successfully!`);
                fetchUsers();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            alert('Error updating user status');
        }
    }
}

async function saveAccessSettings() {
    const roleId = document.getElementById('roleSelect').value;
    if (!roleId) {
        alert('Please select a role');
        return;
    }

    const checkboxes = document.querySelectorAll('#permissionsTree input[type="checkbox"]');
    const updates = [];
    const creates = [];

    checkboxes.forEach(cb => {
        if (!cb.dataset.sub) return;
        if (cb.dataset.id) {
            updates.push({ id: cb.dataset.id, allowed: cb.checked ? 1 : 0 });
        } else if (cb.checked) {
            creates.push({
                category: cb.dataset.category,
                form_name: cb.dataset.form,
                sub_permission: cb.dataset.sub,
                allowed: 1
            });
        }
    });

    try {
        if (updates.length > 0) {
            const response = await fetch('../../../../server/api/system_setup/admin_panel/permissions.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role_id: roleId, permissions: updates })
            });
            const data = await response.json();
            if (!data.success) {
                alert('Error updating permissions: ' + data.message);
                return;
            }
        }

        if (creates.length > 0) {
            const response = await fetch('../../../../server/api/system_setup/admin_panel/permissions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role_id: roleId, permissions: creates })
            });
            const data = await response.json();
            if (!data.success) {
                alert('Error creating permissions: ' + data.message + (data.debug ? '\n' + data.debug : ''));
                return;
            }
        }

        alert('Permissions saved successfully!');
        closeAccessModal();
    } catch (error) {
        alert('Error saving permissions');
    }
}

async function formatDatabase() {
    if (confirm('This will permanently delete ALL data. Are you absolutely sure?')) {
        try {
            const response = await fetch('../../../../database/format_database.php', {
                method: 'POST'
            });

            const data = await response.json();
            if (data.success) {
                alert('Database formatted successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            alert('Error formatting database');
        }
        closeFormatModal();
    }
}

// Roles management
async function fetchRoles() {
    try {
        const response = await fetch('../../../../server/api/system_setup/admin_panel/roles.php');
        const data = await response.json();
        if (data.success) {
            roles = data.roles;
            renderRolesTable();
            updateRoleSelects();
        }
    } catch (error) {
        console.error('Error fetching roles:', error);
    }
}

function renderRolesTable() {
    const tableBody = document.getElementById('rolesTableBody');
    if (!tableBody) return;

    tableBody.innerHTML = '';
    roles.forEach(role => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>#${role.id.toString().padStart(4, '0')}</td>
            <td>${role.name}</td>
            <td>${role.description || 'No description'}</td>
            <td><span class="status-badge ${role.is_system_role ? 'status-active' : 'status-inactive'}">${role.is_system_role ? 'Yes' : 'No'}</span></td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-ghost btn-sm btn-icon" onclick="editRole(${role.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteRole(${role.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

function openCreateRoleModal() {
    document.getElementById('createRoleModal').style.display = 'flex';
}

function closeCreateRoleModal() {
    document.getElementById('createRoleModal').style.display = 'none';
    document.getElementById('createRoleForm').reset();
}

function openEditRoleModal(roleId) {
    const role = roles.find(r => r.id === roleId);
    if (!role) return;

    const modalBody = document.querySelector('#editRoleModal .modal-body');
    modalBody.innerHTML = `
        <form id="editRoleForm">
            <div class="form-group">
                <label class="form-label">Role Name</label>
                <input type="text" name="name" class="form-input" value="${role.name}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-input" rows="3">${role.description || ''}</textarea>
            </div>
        </form>
    `;

    document.getElementById('editRoleModal').style.display = 'flex';
    window.currentEditingRoleId = roleId;
}

function closeEditRoleModal() {
    document.getElementById('editRoleModal').style.display = 'none';
    window.currentEditingRoleId = null;
}

async function createRole() {
    const form = document.getElementById('createRoleForm');
    const formData = new FormData(form);

    try {
        const response = await fetch('../../../../server/api/system_setup/admin_panel/roles.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: formData.get('name'),
                description: formData.get('description')
            })
        });

        const data = await response.json();
        if (data.success) {
            alert('Role created successfully!');
            closeCreateRoleModal();
            fetchRoles();
        } else {
            console.error('Server error:', data);
            alert('Error: ' + data.message + (data.debug ? '\n' + data.debug : ''));
        }
    } catch (error) {
        console.error('Error creating role:', error);
        alert('Error creating role');
    }
}

function editRole(roleId) {
    openEditRoleModal(roleId);
}

async function saveRole() {
    if (window.currentEditingRoleId) {
        const form = document.getElementById('editRoleForm');
        const inputs = form.querySelectorAll('input, textarea');

        try {
            const response = await fetch('../../../../server/api/system_setup/admin_panel/roles.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: window.currentEditingRoleId,
                    name: inputs[0].value,
                    description: inputs[1].value
                })
            });

            const data = await response.json();
            if (data.success) {
                alert('Role updated successfully!');
                closeEditRoleModal();
                fetchRoles();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            alert('Error updating role');
        }
    }
}

async function deleteRole(roleId) {
    if (confirm(`Are you sure you want to delete this role? This action cannot be undone.`)) {
        try {
            const response = await fetch(`../../../../server/api/system_setup/admin_panel/roles.php?id=${roleId}`, {
                method: 'DELETE'
            });

            const data = await response.json();
            if (data.success) {
                alert('Role deleted successfully!');
                fetchRoles();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            alert('Error deleting role');
        }
    }
}

// Image preview function
function previewImage(input) {
    const preview = document.getElementById('preview');
    const previewContainer = document.getElementById('imagePreview');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        previewContainer.style.display = 'none';
    }
}

// Remove image function
function removeImage() {
    const fileInput = document.querySelector('input[name="profile_picture"]');
    const previewContainer = document.getElementById('imagePreview');

    fileInput.value = '';
    previewContainer.style.display = 'none';
}

// Initialize
document.addEventListener('DOMContentLoaded', function () {
    fetchUsers();
    fetchRoles();
    fetchBranches();

    // Close modals when clicking outside
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
});