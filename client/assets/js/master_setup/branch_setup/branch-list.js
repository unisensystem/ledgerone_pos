let branchesData = [];
let currentPage = 1;
let totalPages = 1;
const itemsPerPage = 10;

// Fetch branches from API
async function fetchBranches(page = 1) {
    try {
        const search = document.getElementById('searchInput').value;
        const status = document.getElementById('statusFilter').value;
        const companyId = document.getElementById('companyFilter').value;
        const type = document.getElementById('typeFilter').value;
        
        const params = new URLSearchParams({
            page: page,
            limit: itemsPerPage
        });
        
        if (search) params.append('search', search);
        if (status) params.append('status', status);
        if (companyId) params.append('company_id', companyId);
        if (type) params.append('type', type);
        
        const response = await fetch(`../../../../server/api/master_setup/branch_setup/branch-list.php?${params.toString()}`);
        const result = await response.json();
        
        if (result.success) {
            branchesData = result.data;
            currentPage = result.pagination.current_page;
            totalPages = result.pagination.total_pages;
            renderTable(branchesData);
            updatePagination(result.pagination);
        } else {
            console.error('Failed to fetch branches:', result.message);
        }
    } catch (error) {
        console.error('Error fetching branches:', error);
    }
}

// Function to render table rows
function renderTable(data) {
    const tableBody = document.getElementById('branchesTableBody');
    if (!tableBody) return;

    tableBody.innerHTML = '';

    if (data.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7">
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No branches found</h3>
                        <p>Try adjusting your search or filter criteria</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    data.forEach(branch => {
        const row = document.createElement('tr');

        // Format location
        const location = `${branch.city}, ${branch.state}`;

        // Format branch type for display
        const typeMap = {
            'head_office': 'Head Office',
            'regional_office': 'Regional Office',
            'branch_office': 'Branch Office',
            'warehouse': 'Warehouse',
            'distribution_center': 'Distribution Center',
            'retail_store': 'Retail Store',
            'service_center': 'Service Center',
            'manufacturing_unit': 'Manufacturing Unit'
        };

        row.innerHTML = `
            <td>${branch.branch_code}</td>
            <td>
                <div><strong>${branch.branch_name}</strong></div>
                <div style="font-size: 12px; color: var(--subtext);">${branch.email}</div>
            </td>
            <td><span class="type-badge">${typeMap[branch.branch_type] || branch.branch_type}</span></td>
            <td>${location}</td>
            <td>${branch.phone}</td>
            <td>
                <span class="status-badge ${branch.is_active ? 'status-active' : 'status-inactive'}">
                    ${branch.is_active ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td>
                <div class="actions">
                    <button class="btn btn-ghost btn-sm" onclick="editBranch(${branch.id})">
                        <i class="fas fa-edit icon"></i> Edit
                    </button>
                    <button class="btn btn-ghost btn-sm" onclick="viewBranch(${branch.id})">
                        <i class="fas fa-eye icon"></i> View
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteBranch(${branch.id})">
                        <i class="fas fa-trash icon"></i> Delete
                    </button>
                </div>
            </td>
        `;

        tableBody.appendChild(row);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const companyFilter = document.getElementById('companyFilter');
    const typeFilter = document.getElementById('typeFilter');

    // Load parent branches for edit modal
    async function loadParentBranches() {
        try {
            const response = await fetch('../../../../server/api/master_setup/branch_setup/parent-branches.php');
            const result = await response.json();
            
            if (result.success) {
                const parentSelect = document.getElementById('editParentBranch');
                parentSelect.innerHTML = '<option value="">No Parent Branch</option>';
                
                result.data.forEach(branch => {
                    const option = document.createElement('option');
                    option.value = branch.id;
                    option.textContent = `${branch.branch_name} - ${branch.branch_code}`;
                    parentSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading parent branches:', error);
        }
    }

    // Load companies for edit modal
    async function loadCompanies() {
        try {
            const response = await fetch('../../../../server/api/companies/get-companies.php');
            const result = await response.json();
            
            if (result.success && result.data) {
                const companySelect = document.getElementById('editCompany');
                companySelect.innerHTML = '<option value="">Select Company</option>';
                
                result.data.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.company_name;
                    companySelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error fetching companies:', error);
        }
    }

    // Load companies for filter
    async function loadCompaniesFilter() {
        try {
            const response = await fetch('../../../../server/api/companies/get-companies.php');
            const result = await response.json();
            
            if (result.success && result.data) {
                const companySelect = document.getElementById('companyFilter');
                companySelect.innerHTML = '<option value="">All Companies</option>';
                
                result.data.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.company_name;
                    companySelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error fetching companies for filter:', error);
        }
    }

    loadParentBranches();
    loadCompanies();
    loadCompaniesFilter();

    // Dropdown functionality
    const exportBtn = document.getElementById('exportBtn');
    const exportDropdown = document.getElementById('exportDropdown');

    exportBtn.addEventListener('click', function(e) {
        e.preventDefault();
        exportDropdown.style.display = exportDropdown.style.display === 'block' ? 'none' : 'block';
    });

    document.addEventListener('click', function(e) {
        if (!exportBtn.contains(e.target) && !exportDropdown.contains(e.target)) {
            exportDropdown.style.display = 'none';
        }
    });

    // Event listeners for filtering (reload from page 1)
    searchInput.addEventListener('input', () => fetchBranches(1));
    statusFilter.addEventListener('change', () => fetchBranches(1));
    companyFilter.addEventListener('change', () => fetchBranches(1));
    typeFilter.addEventListener('change', () => fetchBranches(1));

    // Initial render
    fetchBranches();

});

// Update pagination controls
function updatePagination(pagination) {
        const paginationInfo = document.getElementById('paginationInfo');
        const paginationControls = document.querySelector('.pagination-controls');
        
        const start = (pagination.current_page - 1) * pagination.per_page + 1;
        const end = Math.min(pagination.current_page * pagination.per_page, pagination.total);
        
        paginationInfo.textContent = `Showing ${start} to ${end} of ${pagination.total} entries`;
        
        // Generate dynamic pagination buttons
        let buttonsHtml = '';
        
        // Previous button
        buttonsHtml += `<button class="pagination-btn" id="prevPage" ${pagination.current_page <= 1 ? 'disabled' : ''}>
            <i class="fas fa-chevron-left"></i>
        </button>`;
        
        // Page number buttons
        const maxVisible = 5;
        let startPage = Math.max(1, pagination.current_page - Math.floor(maxVisible / 2));
        let endPage = Math.min(pagination.total_pages, startPage + maxVisible - 1);
        
        if (endPage - startPage + 1 < maxVisible) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            buttonsHtml += `<button class="pagination-btn ${i === pagination.current_page ? 'active' : ''}" onclick="fetchBranches(${i})">${i}</button>`;
        }
        
        // Next button
        buttonsHtml += `<button class="pagination-btn" id="nextPage" ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''}>
            <i class="fas fa-chevron-right"></i>
        </button>`;
        
        paginationControls.innerHTML = buttonsHtml;
        
        // Re-attach event listeners for prev/next buttons
        document.getElementById('prevPage').addEventListener('click', function () {
            if (pagination.current_page > 1) {
                fetchBranches(pagination.current_page - 1);
            }
        });
        
        document.getElementById('nextPage').addEventListener('click', function () {
            if (pagination.current_page < pagination.total_pages) {
                fetchBranches(pagination.current_page + 1);
            }
        });
    }

// Action functions
function editBranch(id) {
    const branch = branchesData.find(b => b.id == id);
    if (branch) {
        document.getElementById('editBranchId').value = branch.id;
        document.getElementById('editBranchName').value = branch.branch_name;
        document.getElementById('editBranchType').value = branch.branch_type;
        document.getElementById('editCompany').value = branch.company_id || '';
        document.getElementById('editParentBranch').value = branch.parent_branch_id || '';
        document.getElementById('editAddress').value = branch.address || '';
        document.getElementById('editCountry').value = branch.country || '';
        document.getElementById('editState').value = branch.state || '';
        document.getElementById('editCity').value = branch.city || '';
        document.getElementById('editZipcode').value = branch.zipcode || '';
        document.getElementById('editPhone').value = branch.phone || '';
        document.getElementById('editEmail').value = branch.email || '';
        document.getElementById('editManager').value = branch.manager_id || '';
        document.getElementById('editIsActive').checked = branch.is_active == 1;
        document.getElementById('editAllowsSales').checked = branch.allows_sales == 1;
        document.getElementById('editAllowsInventory').checked = branch.allows_inventory == 1;
        document.getElementById('editModal').style.display = 'block';
    }
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeEditModal();
    }
}

// Handle edit form submission
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('editBranchForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = {
            id: document.getElementById('editBranchId').value,
            branch_name: document.getElementById('editBranchName').value,
            branch_type: document.getElementById('editBranchType').value,
            company_id: document.getElementById('editCompany').value,
            parent_branch_id: document.getElementById('editParentBranch').value || null,
            address: document.getElementById('editAddress').value,
            country: document.getElementById('editCountry').value,
            state: document.getElementById('editState').value,
            city: document.getElementById('editCity').value,
            zipcode: document.getElementById('editZipcode').value,
            phone: document.getElementById('editPhone').value,
            email: document.getElementById('editEmail').value,
            manager_id: document.getElementById('editManager').value || null,
            is_active: document.getElementById('editIsActive').checked ? 1 : 0,
            allows_sales: document.getElementById('editAllowsSales').checked ? 1 : 0,
            allows_inventory: document.getElementById('editAllowsInventory').checked ? 1 : 0
        };
        
        try {
            const response = await fetch('../../../../server/api/master_setup/branch_setup/branch-edit.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeEditModal();
                fetchBranches(currentPage);
                alert('Branch updated successfully!');
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error updating branch: ' + error.message);
        }
    });
    
    // Close modal with X button
    document.querySelector('.close').addEventListener('click', closeEditModal);
});

function viewBranch(id) {
    const branch = branchesData.find(b => b.id == id);
    if (branch) {
        const typeMap = {
            'head_office': 'Head Office',
            'regional_office': 'Regional Office',
            'branch_office': 'Branch Office',
            'warehouse': 'Warehouse',
            'distribution_center': 'Distribution Center',
            'retail_store': 'Retail Store',
            'service_center': 'Service Center',
            'manufacturing_unit': 'Manufacturing Unit'
        };
        
        document.getElementById('viewBranchCode').textContent = branch.branch_code;
        document.getElementById('viewBranchName').textContent = branch.branch_name;
        document.getElementById('viewBranchType').textContent = typeMap[branch.branch_type] || branch.branch_type;
        document.getElementById('viewCompany').textContent = branch.company_name || 'N/A';
        document.getElementById('viewAddress').textContent = branch.address || 'N/A';
        document.getElementById('viewLocation').textContent = `${branch.city || ''}, ${branch.state || ''}, ${branch.country || ''}`.replace(/^,\s*|,\s*$/g, '') || 'N/A';
        document.getElementById('viewPhone').textContent = branch.phone || 'N/A';
        document.getElementById('viewEmail').textContent = branch.email || 'N/A';
        document.getElementById('viewStatus').textContent = branch.is_active ? 'Active' : 'Inactive';
        document.getElementById('viewModal').style.display = 'block';
    }
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

let branchToDelete = null;

function deleteBranch(id) {
    const branch = branchesData.find(b => b.id == id);
    if (branch) {
        branchToDelete = id;
        document.getElementById('deleteBranchName').textContent = branch.branch_name;
        document.getElementById('deleteModal').style.display = 'block';
    }
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    branchToDelete = null;
}

// Handle delete confirmation
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
        if (branchToDelete) {
            try {
                const response = await fetch(`../../../../server/api/master_setup/branch_setup/branch-delete.php?id=${branchToDelete}`, {
                    method: 'DELETE'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeDeleteModal();
                    fetchBranches(currentPage);
                    alert('Branch deleted successfully!');
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error deleting branch: ' + error.message);
            }
        }
    });
});

// Export functions
function printList() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const type = document.getElementById('typeFilter').value;
    
    let url = 'print.php?';
    const params = [];
    
    if (search) params.push(`search=${encodeURIComponent(search)}`);
    if (status) params.push(`status=${encodeURIComponent(status)}`);
    if (type) params.push(`type=${encodeURIComponent(type)}`);
    
    url += params.join('&');
    window.open(url, '_blank');
    document.getElementById('exportDropdown').style.display = 'none';
}

function exportExcel() {
    alert('Export to Excel functionality would be implemented here');
    document.getElementById('exportDropdown').style.display = 'none';
}

function exportJSON() {
    const dataStr = JSON.stringify(branchesData, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'branches.json';
    link.click();
    document.getElementById('exportDropdown').style.display = 'none';
}