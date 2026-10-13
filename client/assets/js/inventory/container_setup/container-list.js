let containers = [];
let currentPage = 1;
let itemsPerPage = 10;
let currentDeleteId = null;
let tenantId = null;

const API_BASE = '../../../../server/api/inventory/container_setup';

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    getTenantId();
    fetchContainers();
    setupEventListeners();
});

function getTenantId() {
    const sessionTenantId = document.querySelector('meta[name="tenant-id"]')?.getAttribute('content');
    if (sessionTenantId) {
        tenantId = sessionTenantId;
    }
}

function setupEventListeners() {
    document.getElementById('addContainerForm').addEventListener('submit', handleAddSubmit);
    document.getElementById('editContainerForm').addEventListener('submit', handleEditSubmit);
    document.getElementById('confirmDeleteBtn').addEventListener('click', handleDeleteConfirm);
    document.getElementById('searchInput').addEventListener('input', filterContainers);
    document.getElementById('statusFilter').addEventListener('change', filterContainers);
    document.getElementById('exportBtn').addEventListener('click', () => {
        document.getElementById('exportDropdown').style.display = 
            document.getElementById('exportDropdown').style.display === 'block' ? 'none' : 'block';
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown')) {
            document.getElementById('exportDropdown').style.display = 'none';
        }
    });
}

async function fetchContainers() {
    try {
        const response = await fetch(`${API_BASE}/container-list.php`);
        const data = await response.json();
        
        if (data.success) {
            containers = data.containers || [];
            if (data.tenant_id) {
                tenantId = data.tenant_id;
            }
            renderTable();
        } else {
            showError(data.message || 'Failed to fetch containers');
        }
    } catch (error) {
        console.error('Error fetching containers:', error);
        showError('Error fetching containers');
    }
}

function renderTable() {
    const tableBody = document.getElementById('containersTableBody');
    const filtered = getFilteredData();
    const totalPages = Math.ceil(filtered.length / itemsPerPage);
    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const pageData = filtered.slice(start, end);

    tableBody.innerHTML = '';
    
    if (pageData.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--text-subtle);">No containers found</td></tr>';
        document.getElementById('paginationInfo').textContent = `Showing 0 to 0 of ${filtered.length} entries`;
        return;
    }

    pageData.forEach(container => {
        const row = document.createElement('tr');
        const statusClass = container.is_active ? 'status-active' : 'status-inactive';
        const statusText = container.is_active ? 'Active' : 'Inactive';
        const createdDate = new Date(container.created_at).toLocaleDateString();

        row.innerHTML = `
            <td><strong>${escapeHtml(container.container_code)}</strong></td>
            <td>${escapeHtml(container.container_name)}</td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            <td>${createdDate}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-ghost btn-icon" onclick="viewContainer(${container.id})" title="View">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-ghost btn-icon" onclick="editContainer(${container.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-ghost btn-icon" onclick="deleteContainer(${container.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tableBody.appendChild(row);
    });

    updatePagination(totalPages, filtered.length);
}

function getFilteredData() {
    let filtered = containers;
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;

    if (searchTerm) {
        filtered = filtered.filter(c => 
            c.container_code.toLowerCase().includes(searchTerm) ||
            c.container_name.toLowerCase().includes(searchTerm)
        );
    }

    if (statusFilter) {
        filtered = filtered.filter(c => 
            (statusFilter === 'active' ? c.is_active : !c.is_active)
        );
    }

    return filtered;
}

function filterContainers() {
    currentPage = 1;
    renderTable();
}

function updatePagination(totalPages, totalItems) {
    const pageButtons = document.getElementById('pageButtons');
    pageButtons.innerHTML = '';

    for (let i = 1; i <= totalPages && i <= 5; i++) {
        const btn = document.createElement('button');
        btn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
        btn.textContent = i;
        btn.onclick = () => {
            currentPage = i;
            renderTable();
        };
        pageButtons.appendChild(btn);
    }

    const start = (currentPage - 1) * itemsPerPage + 1;
    const end = Math.min(currentPage * itemsPerPage, totalItems);
    document.getElementById('paginationInfo').textContent = `Showing ${start} to ${end} of ${totalItems} entries`;

    document.getElementById('prevPage').disabled = currentPage === 1;
    document.getElementById('prevPage').onclick = () => {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    };

    document.getElementById('nextPage').disabled = currentPage === totalPages;
    document.getElementById('nextPage').onclick = () => {
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
        }
    };
}

function openAddModal() {
    document.getElementById('addContainerForm').reset();
    generateContainerCode();
    document.getElementById('addModal').classList.add('show');
    document.getElementById('addModal').style.display = 'flex';
}

function generateContainerCode() {
    const prefix = 'CNT';
    const timestamp = Date.now().toString().slice(-6);
    const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    const code = `${prefix}-${timestamp}-${random}`;
    document.getElementById('addContainerCode').value = code;
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('show');
    document.getElementById('addModal').style.display = 'none';
}

function openEditModal(container) {
    document.getElementById('editContainerId').value = container.id;
    document.getElementById('editContainerCode').value = container.container_code;
    document.getElementById('editContainerName').value = container.container_name;
    document.getElementById('editIsActive').checked = container.is_active;
    
    document.getElementById('editModal').classList.add('show');
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
    document.getElementById('editModal').style.display = 'none';
}

function openDeleteModal(container) {
    currentDeleteId = container.id;
    document.getElementById('deleteContainerName').textContent = `${container.container_code} - ${container.container_name}`;
    document.getElementById('deleteModal').classList.add('show');
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.getElementById('deleteModal').style.display = 'none';
    currentDeleteId = null;
}

let currentViewContainer = null;

function openViewModal(container) {
    currentViewContainer = container;
    document.getElementById('viewContainerCode').textContent = container.container_code;
    document.getElementById('viewContainerName').textContent = container.container_name;
    document.getElementById('viewStatus').textContent = container.is_active ? 'Active' : 'Inactive';
    document.getElementById('viewCreatedDate').textContent = new Date(container.created_at).toLocaleDateString();

    document.getElementById('viewModal').classList.add('show');
    document.getElementById('viewModal').style.display = 'flex';
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('show');
    document.getElementById('viewModal').style.display = 'none';
}

function viewContainerActivityHistory() {
    if (!currentViewContainer) return;
    openActivityHistory('Container', currentViewContainer.id, currentViewContainer.container_code);
}

async function handleAddSubmit(e) {
    e.preventDefault();
    
    const containerName = document.getElementById('addContainerName').value.trim();
    const isActive = document.getElementById('addIsActive').checked ? 1 : 0;

    if (!containerName) {
        showError('Please enter container name');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/container-add.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tenant_id: tenantId,
                container_name: containerName,
                is_active: isActive
            })
        });

        const data = await response.json();
        
        if (data.success) {
            showSuccess('Container added successfully');
            closeAddModal();
            fetchContainers();
        } else {
            showError(data.message || 'Failed to add container');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Error adding container');
    }
}

async function handleEditSubmit(e) {
    e.preventDefault();
    
    const id = document.getElementById('editContainerId').value;
    const containerCode = document.getElementById('editContainerCode').value.trim();
    const containerName = document.getElementById('editContainerName').value.trim();
    const isActive = document.getElementById('editIsActive').checked ? 1 : 0;

    if (!containerCode || !containerName) {
        showError('Please fill all required fields');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/container-edit.php`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: id,
                tenant_id: tenantId,
                container_code: containerCode,
                container_name: containerName,
                is_active: isActive
            })
        });

        const data = await response.json();
        
        if (data.success) {
            showSuccess('Container updated successfully');
            closeEditModal();
            fetchContainers();
        } else {
            showError(data.message || 'Failed to update container');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Error updating container');
    }
}

async function handleDeleteConfirm() {
    if (!currentDeleteId) return;

    try {
        const response = await fetch(`${API_BASE}/container-delete.php?id=${currentDeleteId}`, {
            method: 'DELETE'
        });

        const data = await response.json();
        
        if (data.success) {
            showSuccess('Container deleted successfully');
            closeDeleteModal();
            fetchContainers();
        } else {
            showError(data.message || 'Failed to delete container');
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Error deleting container');
    }
}

function editContainer(id) {
    const container = containers.find(c => c.id === id);
    if (container) {
        openEditModal(container);
    }
}

function deleteContainer(id) {
    const container = containers.find(c => c.id === id);
    if (container) {
        openDeleteModal(container);
    }
}

function viewContainer(id) {
    const container = containers.find(c => c.id === id);
    if (container) {
        openViewModal(container);
    }
}

function printList() {
    window.print();
}

function exportExcel() {
    const headers = ['Container Code', 'Container Name', 'Status', 'Created Date'];
    const rows = containers.map(c => [
        c.container_code,
        c.container_name,
        c.is_active ? 'Active' : 'Inactive',
        new Date(c.created_at).toLocaleDateString()
    ]);

    let csv = headers.join(',') + '\n';
    rows.forEach(row => {
        csv += row.map(cell => `"${cell}"`).join(',') + '\n';
    });

    const link = document.createElement('a');
    link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    link.download = `containers_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
}

function exportJSON() {
    const json = JSON.stringify(containers, null, 2);
    const link = document.createElement('a');
    link.href = 'data:application/json;charset=utf-8,' + encodeURIComponent(json);
    link.download = `containers_${new Date().toISOString().split('T')[0]}.json`;
    link.click();
}

function showSuccess(message) {
    // You can implement a toast notification here
    console.log('Success:', message);
    alert(message);
}

function showError(message) {
    // You can implement a toast notification here
    console.error('Error:', message);
    alert(message);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
