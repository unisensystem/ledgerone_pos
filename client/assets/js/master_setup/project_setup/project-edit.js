const API = '../../../../server/api/master_setup/project_setup/';
const projectId = document.getElementById('projectId').value;

let employees = [];
let selectedTeamMembers = new Map();

// ─── Toast ─────────────────────────────────────────────────────────────────────
function showToast(message, type = 'success') {
    const tc = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    t.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
    tc.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// ─── Searchable single-select ──────────────────────────────────────────────────
function buildSearchableDropdown(inputId, dropdownId, hiddenId, data) {
    const input    = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    const hidden   = document.getElementById(hiddenId);

    function render(list) {
        dropdown.innerHTML = '';
        if (!list.length) { dropdown.innerHTML = '<div class="dropdown-empty">No results</div>'; dropdown.style.display = 'block'; return; }
        list.forEach(item => {
            const d = document.createElement('div');
            d.className = 'dropdown-item';
            d.textContent = item.full_name + (item.employee_id ? ` (${item.employee_id})` : '');
            d.onclick = () => { input.value = item.full_name; hidden.value = item.id; dropdown.style.display = 'none'; };
            dropdown.appendChild(d);
        });
        dropdown.style.display = 'block';
    }

    input.addEventListener('input', () => {
        const q = input.value.toLowerCase();
        hidden.value = '';
        render(q ? data.filter(e => e.full_name.toLowerCase().includes(q)) : data);
    });
    input.addEventListener('focus', () => render(data));
    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
    });
}

// ─── Team members ──────────────────────────────────────────────────────────────
function buildTeamList(data) {
    const list = document.getElementById('teamList');
    list.innerHTML = '';
    if (!data.length) { list.innerHTML = '<div class="dropdown-empty">No employees</div>'; return; }

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
            if (this.checked) selectedTeamMembers.set(String(emp.id), emp.full_name);
            else selectedTeamMembers.delete(String(emp.id));
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

function filterTeamList(q) {
    document.querySelectorAll('#teamList .team-item').forEach(row => {
        row.style.display = row.dataset.name.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
    });
}

// ─── Validation ────────────────────────────────────────────────────────────────
function validate() {
    let ok = true;
    [['projectName','projectNameError'],['startDate','startDateError'],['endDate','endDateError']].forEach(([fid, eid]) => {
        const f = document.getElementById(fid), e = document.getElementById(eid);
        if (!f.value.trim()) { f.classList.add('input-error'); e.style.display = 'block'; ok = false; }
        else { f.classList.remove('input-error'); e.style.display = 'none'; }
    });
    const s = document.getElementById('startDate').value, en = document.getElementById('endDate').value;
    if (s && en && en < s) {
        document.getElementById('endDate').classList.add('input-error');
        document.getElementById('endDateError').textContent = 'End date cannot be before start date';
        document.getElementById('endDateError').style.display = 'block'; ok = false;
    }
    return ok;
}

// ─── Load & prefill ────────────────────────────────────────────────────────────
async function loadCities(selectedId) {
    const res = await fetch(API + 'get-cities.php');
    const result = await res.json();
    if (!result.success) return;
    const sel = document.getElementById('city');
    result.data.forEach(c => {
        const o = document.createElement('option');
        o.value = c.id; o.textContent = c.city_name;
        if (String(c.id) === String(selectedId)) o.selected = true;
        sel.appendChild(o);
    });
}

async function loadEmployees(project) {
    const res = await fetch(API + 'get-employees.php');
    const result = await res.json();
    if (!result.success) return;
    employees = result.data;

    buildSearchableDropdown('projectManager', 'managerDropdown', 'projectManagerId', employees);
    buildSearchableDropdown('teamLeader', 'leaderDropdown', 'teamLeaderId', employees);

    // Pre-fill manager & leader
    if (project.project_manager_id) {
        const mgr = employees.find(e => String(e.id) === String(project.project_manager_id));
        if (mgr) { document.getElementById('projectManager').value = mgr.full_name; document.getElementById('projectManagerId').value = mgr.id; }
    }
    if (project.team_leader_id) {
        const tl = employees.find(e => String(e.id) === String(project.team_leader_id));
        if (tl) { document.getElementById('teamLeader').value = tl.full_name; document.getElementById('teamLeaderId').value = tl.id; }
    }

    // Pre-select team members
    project.team_members.forEach(m => selectedTeamMembers.set(String(m.employee_id), m.full_name));
    buildTeamList(employees);
    renderTags();
}

async function loadProject() {
    const res = await fetch(API + `project-get.php?id=${projectId}`);
    const result = await res.json();
    if (!result.success) { showToast('Project not found', 'error'); return; }
    const p = result.data;

    document.getElementById('projectCode').value    = p.project_code;
    document.getElementById('projectName').value    = p.project_name;
    document.getElementById('startDate').value      = p.start_date;
    document.getElementById('endDate').value        = p.end_date;
    document.getElementById('status').value         = p.status;
    document.getElementById('description').value    = p.description || '';

    await loadCities(p.city_id);
    await loadEmployees(p);
}

// ─── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadProject();

    document.getElementById('teamSearch').addEventListener('input', function () { filterTeamList(this.value); });

    ['projectName','startDate','endDate'].forEach(id => {
        document.getElementById(id).addEventListener('input', function () {
            this.classList.remove('input-error');
            const errEl = document.getElementById(id.charAt(0).toUpperCase() + id.slice(1) + 'Error');
            if (errEl) errEl.style.display = 'none';
        });
    });

    document.getElementById('projectForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!validate()) return;

        const btn = this.querySelector('[type="submit"]');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        const payload = {
            id:                  parseInt(projectId),
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
            const res = await fetch(API + 'project-edit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await res.json();
            if (result.success) {
                showToast('Project updated successfully!');
                setTimeout(() => window.location.href = 'project-list.php', 1500);
            } else {
                showToast(result.message || 'Update failed', 'error');
                btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
            }
        } catch (err) {
            showToast('Network error: ' + err.message, 'error');
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }
    });
});
