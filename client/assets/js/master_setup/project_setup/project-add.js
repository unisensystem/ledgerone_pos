const API = '../../../../server/api/master_setup/project_setup/';

let employees = [];
let selectedTeamMembers = new Map(); // id -> full_name

// ─── Toast ────────────────────────────────────────────────────────────────────
function showToast(message, type = 'success') {
    const tc = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'exclamation-triangle';
    t.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
    tc.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// ─── Searchable single-select dropdown ────────────────────────────────────────
function buildSearchableDropdown(inputId, dropdownId, hiddenId, data, labelKey = 'full_name') {
    const input    = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    const hidden   = document.getElementById(hiddenId);

    function render(list) {
        dropdown.innerHTML = '';
        if (!list.length) {
            dropdown.innerHTML = '<div class="dropdown-empty">No results found</div>';
            dropdown.style.display = 'block';
            return;
        }
        list.forEach(item => {
            const d = document.createElement('div');
            d.className = 'dropdown-item';
            d.textContent = item[labelKey] + (item.employee_id ? ` (${item.employee_id})` : '');
            d.onclick = () => {
                input.value  = item[labelKey];
                hidden.value = item.id;
                dropdown.style.display = 'none';
            };
            dropdown.appendChild(d);
        });
        dropdown.style.display = 'block';
    }

    input.addEventListener('input', () => {
        const q = input.value.toLowerCase();
        hidden.value = '';
        render(q ? data.filter(e => e[labelKey].toLowerCase().includes(q)) : data);
    });

    input.addEventListener('focus', () => render(data));

    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !dropdown.contains(e.target))
            dropdown.style.display = 'none';
    });
}

// ─── Team Members multi-select ─────────────────────────────────────────────────
function buildTeamList(data) {
    const list = document.getElementById('teamList');
    list.innerHTML = '';

    if (!data.length) {
        list.innerHTML = '<div class="dropdown-empty">No employees found</div>';
        return;
    }

    data.forEach(emp => {
        const row = document.createElement('div');
        row.className = 'team-item';
        row.dataset.id = emp.id;
        row.dataset.name = emp.full_name;

        const checked = selectedTeamMembers.has(String(emp.id)) ? 'checked' : '';
        row.innerHTML = `
            <input type="checkbox" id="tm_${emp.id}" value="${emp.id}" ${checked}>
            <label for="tm_${emp.id}">${emp.full_name}</label>
            <span class="emp-code">${emp.employee_id || ''}</span>`;

        row.querySelector('input').addEventListener('change', function () {
            if (this.checked) {
                selectedTeamMembers.set(String(emp.id), emp.full_name);
            } else {
                selectedTeamMembers.delete(String(emp.id));
            }
            renderTags();
        });

        list.appendChild(row);
    });
}

function renderTags() {
    const container = document.getElementById('selectedTags');
    container.innerHTML = '';
    selectedTeamMembers.forEach((name, id) => {
        const tag = document.createElement('span');
        tag.className = 'tag';
        tag.innerHTML = `${name} <span class="tag-remove" data-id="${id}">&times;</span>`;
        tag.querySelector('.tag-remove').addEventListener('click', () => {
            selectedTeamMembers.delete(id);
            const cb = document.getElementById(`tm_${id}`);
            if (cb) cb.checked = false;
            renderTags();
        });
        container.appendChild(tag);
    });
}

function filterTeamList(query) {
    const rows = document.querySelectorAll('#teamList .team-item');
    rows.forEach(row => {
        row.style.display = row.dataset.name.toLowerCase().includes(query.toLowerCase()) ? '' : 'none';
    });
}

// ─── Load data ─────────────────────────────────────────────────────────────────
async function loadCities() {
    const res = await fetch(API + 'get-cities.php');
    const result = await res.json();
    if (!result.success) return;
    const sel = document.getElementById('city');
    result.data.forEach(c => {
        const o = document.createElement('option');
        o.value = c.id; o.textContent = c.city_name;
        sel.appendChild(o);
    });
}

async function loadEmployees() {
    const res = await fetch(API + 'get-employees.php');
    const result = await res.json();
    if (!result.success) return;
    employees = result.data;

    buildSearchableDropdown('projectManager', 'managerDropdown', 'projectManagerId', employees);
    buildSearchableDropdown('teamLeader', 'leaderDropdown', 'teamLeaderId', employees);
    buildTeamList(employees);
}

// ─── Validation ────────────────────────────────────────────────────────────────
function validate() {
    let ok = true;
    const checks = [
        ['projectName', 'projectNameError'],
        ['startDate',   'startDateError'],
        ['endDate',     'endDateError'],
    ];
    checks.forEach(([fid, eid]) => {
        const f = document.getElementById(fid);
        const e = document.getElementById(eid);
        if (!f.value.trim()) {
            f.classList.add('input-error'); e.style.display = 'block'; ok = false;
        } else {
            f.classList.remove('input-error'); e.style.display = 'none';
        }
    });

    const start = document.getElementById('startDate').value;
    const end   = document.getElementById('endDate').value;
    if (start && end && end < start) {
        document.getElementById('endDate').classList.add('input-error');
        document.getElementById('endDateError').textContent = 'End date cannot be before start date';
        document.getElementById('endDateError').style.display = 'block';
        ok = false;
    }
    return ok;
}

// ─── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadCities();
    loadEmployees();

    document.getElementById('teamSearch').addEventListener('input', function () {
        filterTeamList(this.value);
    });

    // Clear error on input
    ['projectName', 'startDate', 'endDate'].forEach(id => {
        document.getElementById(id).addEventListener('input', function () {
            this.classList.remove('input-error');
            document.getElementById(id.charAt(0).toUpperCase() + id.slice(1) + 'Error').style.display = 'none';
        });
    });

    // Form submit
    document.getElementById('projectForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!validate()) return;

        const btn = this.querySelector('[type="submit"]');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        const payload = {
            company_id:          1, // will be resolved server-side via session tenant
            project_name:        document.getElementById('projectName').value.trim(),
            city_id:             document.getElementById('city').value || null,
            start_date:          document.getElementById('startDate').value,
            end_date:            document.getElementById('endDate').value,
            status:              document.getElementById('status').value,
            project_manager_id:  document.getElementById('projectManagerId').value || null,
            team_leader_id:      document.getElementById('teamLeaderId').value || null,
            description:         document.getElementById('description').value.trim(),
            team_members:        Array.from(selectedTeamMembers.keys()).map(Number)
        };

        try {
            const res = await fetch(API + 'project-add.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await res.json();
            if (result.success) {
                document.getElementById('projectCode').value = result.project_code;
                showToast(`Project ${result.project_code} created successfully!`);
                setTimeout(() => window.location.href = 'project-list.php', 1500);
            } else {
                showToast(result.message || 'Failed to create project', 'error');
                btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus"></i> Create Project';
            }
        } catch (err) {
            showToast('Network error: ' + err.message, 'error');
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus"></i> Create Project';
        }
    });

    // Reset
    document.getElementById('resetBtn').addEventListener('click', () => {
        if (!confirm('Reset all fields?')) return;
        document.getElementById('projectForm').reset();
        selectedTeamMembers.clear();
        renderTags();
        buildTeamList(employees);
        ['projectManagerId', 'teamLeaderId'].forEach(id => document.getElementById(id).value = '');
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
        document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
    });
});
