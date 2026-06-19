const API = '../../../../server/api/master_setup/project_setup/';

let allProjects = [];
let currentPage = 1;
const perPage = 15;
let deleteTargetId = null;

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

// ─── Modal helpers ─────────────────────────────────────────────────────────────
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
window.closeModal = closeModal;

// ─── Status badge ──────────────────────────────────────────────────────────────
function statusBadge(status) {
    const labels = { active: 'Active', completed: 'Completed', on_hold: 'On Hold', cancelled: 'Cancelled' };
    return `<span class="badge badge-${status}">${labels[status] || status}</span>`;
}

// ─── Date format ───────────────────────────────────────────────────────────────
function fmt(d) { if (!d) return '—'; const dt = new Date(d); return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); }

// ─── Render table ─────────────────────────────────────────────────────────────
function getFiltered() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const s = document.getElementById('statusFilter').value;
    return allProjects.filter(p => {
        const matchQ = !q || p.project_name.toLowerCase().includes(q) || p.project_code.toLowerCase().includes(q) || (p.city_name || '').toLowerCase().includes(q);
        const matchS = !s || p.status === s;
        return matchQ && matchS;
    });
}

function renderTable() {
    const filtered = getFiltered();
    const total = filtered.length;
    const start = (currentPage - 1) * perPage;
    const page = filtered.slice(start, start + perPage);
    const tbody = document.getElementById('projectTableBody');

    if (!page.length) {
        tbody.innerHTML = `<tr><td colspan="11"><div class="empty-state"><i class="fas fa-folder-open"></i><p>No projects found</p></div></td></tr>`;
        renderPagination(0);
        return;
    }

    tbody.innerHTML = page.map((p, i) => `
        <tr>
            <td>${start + i + 1}</td>
            <td><span class="project-code">${p.project_code}</span></td>
            <td><strong>${p.project_name}</strong></td>
            <td>${p.city_name || '—'}</td>
            <td>${p.project_manager_name || '—'}</td>
            <td>${p.team_leader_name || '—'}</td>
            <td><span class="badge badge-active" style="background:#f0f4ff;color:var(--primary);">${p.team_count} member${p.team_count != 1 ? 's' : ''}</span></td>
            <td>${fmt(p.start_date)}</td>
            <td>${fmt(p.end_date)}</td>
            <td>${statusBadge(p.status)}</td>
            <td>
                <div class="action-btns">
                    <button class="btn btn-secondary btn-icon btn-sm" title="View" onclick="viewProject(${p.id})"><i class="fas fa-eye"></i></button>
                    <a href="project-edit.php?id=${p.id}" class="btn btn-primary btn-icon btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                    <button class="btn btn-danger btn-icon btn-sm" title="Delete" onclick="confirmDelete(${p.id}, '${p.project_name.replace(/'/g, "\\'")}')"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>`).join('');

    renderPagination(total);
}

function renderPagination(total) {
    const totalPages = Math.ceil(total / perPage);
    const pc = document.getElementById('paginationContainer');
    if (totalPages <= 1) { pc.innerHTML = ''; return; }

    const start = (currentPage - 1) * perPage + 1;
    const end = Math.min(currentPage * perPage, total);

    let btns = '';
    for (let i = 1; i <= totalPages; i++) {
        btns += `<button class="page-btn${i === currentPage ? ' active' : ''}" onclick="goPage(${i})">${i}</button>`;
    }

    pc.innerHTML = `
        <span class="pagination-info">Showing ${start}–${end} of ${total}</span>
        <div class="pagination-btns">
            <button class="page-btn" onclick="goPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>
            ${btns}
            <button class="page-btn" onclick="goPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>
        </div>`;
}

window.goPage = function(p) { currentPage = p; renderTable(); };

// ─── View project modal ────────────────────────────────────────────────────────
window.viewProject = async function(id) {
    const res = await fetch(API + `project-get.php?id=${id}`);
    const result = await res.json();
    if (!result.success) { showToast('Failed to load project', 'error'); return; }
    const p = result.data;

    const teamChips = p.team_members.length
        ? p.team_members.map(m => `<span class="tag">${m.full_name}</span>`).join('')
        : '<span style="color:var(--subtext);font-size:13px;">No team members assigned</span>';

    document.getElementById('viewModalBody').innerHTML = `
        <div class="detail-grid">
            <div class="detail-item"><label>Project Code</label><p><span class="project-code">${p.project_code}</span></p></div>
            <div class="detail-item"><label>Project Name</label><p>${p.project_name}</p></div>
            <div class="detail-item"><label>City</label><p>${p.city_name || '—'}</p></div>
            <div class="detail-item"><label>Status</label><p>${statusBadge(p.status)}</p></div>
            <div class="detail-item"><label>Start Date</label><p>${fmt(p.start_date)}</p></div>
            <div class="detail-item"><label>End Date</label><p>${fmt(p.end_date)}</p></div>
            <div class="detail-item"><label>Project Manager</label><p>${p.project_manager_name || '—'}</p></div>
            <div class="detail-item"><label>Team Leader</label><p>${p.team_leader_name || '—'}</p></div>
            <div class="detail-item full"><label>Team Members</label><div class="team-chips">${teamChips}</div></div>
            ${p.description ? `<div class="detail-item full"><label>Description</label><p style="font-weight:400;color:var(--body)">${p.description}</p></div>` : ''}
        </div>`;

    document.getElementById('viewEditBtn').onclick = () => window.location.href = `project-edit.php?id=${id}`;
    openModal('viewModal');
};

// ─── Delete ────────────────────────────────────────────────────────────────────
window.confirmDelete = function(id, name) {
    deleteTargetId = id;
    document.getElementById('deleteProjectName').textContent = name;
    openModal('deleteModal');
};

document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
    if (!deleteTargetId) return;
    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

    const res = await fetch(API + 'project-delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: deleteTargetId })
    });
    const result = await res.json();
    closeModal('deleteModal');
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash"></i> Delete';

    if (result.success) {
        showToast('Project deleted successfully');
        allProjects = allProjects.filter(p => p.id !== deleteTargetId);
        renderTable();
    } else {
        showToast(result.message || 'Delete failed', 'error');
    }
    deleteTargetId = null;
});

// ─── Load & Init ───────────────────────────────────────────────────────────────
async function loadProjects() {
    const res = await fetch(API + 'project-list.php');
    const result = await res.json();
    if (result.success) { allProjects = result.data; renderTable(); }
    else showToast('Failed to load projects', 'error');
}

document.addEventListener('DOMContentLoaded', () => {
    loadProjects();
    document.getElementById('searchInput').addEventListener('input', () => { currentPage = 1; renderTable(); });
    document.getElementById('statusFilter').addEventListener('change', () => { currentPage = 1; renderTable(); });

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('active'); });
    });
});
