let stationsData = [];
let branches = [];
let products = [];
let filteredStations = [];
let currentPage = 1;
const itemsPerPage = 5;

// Load stations, branches and products on page load
document.addEventListener('DOMContentLoaded', () => {
    loadStations();
    loadBranches();
    loadProducts();
});

async function loadBranches() {
    try {
        const response = await fetch('../../../../server/api/master_setup/fuel_pump_setup/get-branches.php');
        const result = await response.json();
        
        if (result.success) {
            branches = result.branches;
        }
    } catch (error) {
        console.error('Error loading branches:', error);
    }
}

async function loadProducts() {
    try {
        const response = await fetch('../../../../server/api/master_setup/fuel_pump_setup/get-products.php');
        const result = await response.json();
        
        if (result.success) {
            products = result.products;
            populateEditPumpTypeDropdown();
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

function populateEditPumpTypeDropdown() {
    setupEditPumpTypeSearchableDropdown();
}

function setupEditPumpTypeSearchableDropdown() {
    const input = document.getElementById('editPumpType');
    const dropdown = document.getElementById('editPumpTypeDropdown');
    const hiddenInput = document.getElementById('editPumpTypeId');
    
    input.addEventListener('input', () => {
        const query = input.value.toLowerCase();
        const filtered = products.filter(product => 
            product.name.toLowerCase().includes(query)
        );
        showEditPumpTypeDropdown(filtered);
    });
    
    input.addEventListener('focus', () => showEditPumpTypeDropdown(products));
    
    function showEditPumpTypeDropdown(items) {
        dropdown.innerHTML = '';
        if (items.length === 0) {
            dropdown.style.display = 'none';
            return;
        }
        
        items.forEach(product => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.textContent = product.name;
            item.addEventListener('click', () => {
                input.value = product.name;
                hiddenInput.value = product.id;
                dropdown.style.display = 'none';
            });
            dropdown.appendChild(item);
        });
        
        dropdown.style.display = 'block';
    }
}

async function loadStations() {
    try {
        const response = await fetch('../../../../server/api/master_setup/fuel_pump_setup/pump-list.php');
        const result = await response.json();
        
        if (result.success) {
            stationsData = result.stations.map(station => ({
                id: station.id,
                pumpName: station.station_name,
                parentBranch: station.branch_name || 'Unknown Branch',
                type: station.fuel_type,
                status: station.status,
                lastService: station.last_maintenance_date,
                installationDate: station.installation_date
            }));
            populateTable(stationsData);
        } else {
            console.error('Failed to load stations:', result.message);
        }
    } catch (error) {
        console.error('Error loading stations:', error);
    }
}

// Populate table with data
function populateTable(stations) {
    filteredStations = stations;
    currentPage = 1;
    renderTable();
    renderPagination();
}

function renderTable() {
    const tableBody = document.getElementById('stationsTableBody');
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedStations = filteredStations.slice(startIndex, endIndex);

    if (filteredStations.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7">
                    <div class="empty-state">
                        <i class="fas fa-gas-pump"></i>
                        <h3>No stations found</h3>
                        <p>Try adjusting your filters or add a new station</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    tableBody.innerHTML = paginatedStations.map(station => `
        <tr>
            <td>${station.pumpName}</td>
            <td>${station.parentBranch}</td>
            <td>${station.type}</td>
            <td>
                <span class="status-badge status-${station.status}">
                    ${station.status.charAt(0).toUpperCase() + station.status.slice(1)}
                </span>
            </td>
            <td>${formatDate(station.lastService)}</td>
            <td>${formatDate(station.installationDate)}</td>
            <td>
                <div class="actions">
                    <button class="action-btn edit-btn" onclick="editStation(${station.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn delete-btn" onclick="deleteStation(${station.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Format date for display
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Filter stations based on search and filters
function filterStations() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const branchFilter = document.getElementById('branchFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    const typeFilter = document.getElementById('typeFilter').value;

    const filtered = stationsData.filter(station => {
        const matchesSearch = station.pumpName.toLowerCase().includes(searchTerm) ||
            station.parentBranch.toLowerCase().includes(searchTerm);
        const matchesBranch = !branchFilter || station.parentBranch === getBranchName(branchFilter);
        const matchesStatus = !statusFilter || station.status === statusFilter;
        const matchesType = !typeFilter || station.type.toLowerCase().includes(typeFilter);

        return matchesSearch && matchesBranch && matchesStatus && matchesType;
    });

    populateTable(filtered);
}

function renderPagination() {
    const totalPages = Math.ceil(filteredStations.length / itemsPerPage);
    const startItem = filteredStations.length === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, filteredStations.length);
    
    // Update pagination info
    document.getElementById('paginationInfo').textContent = 
        `Showing ${startItem}-${endItem} of ${filteredStations.length} stations`;
    
    // Update pagination controls
    const paginationControls = document.querySelector('.pagination-controls');
    paginationControls.innerHTML = `
        <button class="pagination-btn" id="prevPage" ${currentPage === 1 ? 'disabled' : ''}>
            <i class="fas fa-chevron-left"></i>
        </button>
        ${generatePageButtons(totalPages)}
        <button class="pagination-btn" id="nextPage" ${currentPage === totalPages ? 'disabled' : ''}>
            <i class="fas fa-chevron-right"></i>
        </button>
    `;
    
    // Add event listeners
    document.getElementById('prevPage').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
            renderPagination();
        }
    });
    
    document.getElementById('nextPage').addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
            renderPagination();
        }
    });
    
    // Add page number click handlers
    document.querySelectorAll('.page-number').forEach(btn => {
        btn.addEventListener('click', (e) => {
            currentPage = parseInt(e.target.textContent);
            renderTable();
            renderPagination();
        });
    });
}

function generatePageButtons(totalPages) {
    let buttons = '';
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    
    if (endPage - startPage + 1 < maxVisible) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        buttons += `<button class="pagination-btn page-number ${i === currentPage ? 'active' : ''}">${i}</button>`;
    }
    
    return buttons;
}

// Get branch name from value
function getBranchName(branchValue) {
    const branches = {
        'branch1': 'Downtown Station',
        'branch2': 'Westside Fuel Center',
        'branch3': 'Northgate Petroleum',
        'branch4': 'East End Fuels',
        'branch5': 'Central City Station'
    };
    return branches[branchValue] || '';
}

// Add station button handler
document.getElementById('addStationBtn').addEventListener('click', () => {
    window.location.href = 'pump-add.php';
});

// Edit station function
async function editStation(stationId) {
    try {
        const response = await fetch(`../../../../server/api/master_setup/fuel_pump_setup/pump-edit.php?id=${stationId}`);
        const result = await response.json();
        
        if (result.success) {
            const station = result.station;
            document.getElementById('editStationId').value = station.id;
            document.getElementById('editPumpName').value = station.station_name;
            // Set pump type
            const product = products.find(p => p.id == station.fuel_type_id);
            if (product) {
                document.getElementById('editPumpType').value = product.name;
                document.getElementById('editPumpTypeId').value = product.id;
            } else {
                document.getElementById('editPumpType').value = '';
                document.getElementById('editPumpTypeId').value = '';
            }
            document.getElementById('editStatus').value = station.status;
            document.getElementById('editManufacturer').value = station.manufacturer || '';
            document.getElementById('editModel').value = station.model || '';
            document.getElementById('editSerialNumber').value = station.serial_number || '';
            document.getElementById('editFlowRate').value = station.flow_rate || '';
            document.getElementById('editLocationDescription').value = station.location_description || '';
            document.getElementById('editInstallationDate').value = station.installation_date || '';
            document.getElementById('editLastService').value = station.last_maintenance_date || '';
            
            // Set branch
            const branch = branches.find(b => b.id == station.branch_id);
            if (branch) {
                document.getElementById('editParentBranch').value = `${branch.branch_name} (${branch.branch_type})`;
                document.getElementById('editParentBranchId').value = branch.id;
            }
            
            setupEditSearchableDropdown();
            document.getElementById('editModal').style.display = 'block';
        } else {
            alert('Error loading station data: ' + result.message);
        }
    } catch (error) {
        alert('Error loading station data');
        console.error('Error:', error);
    }
}

function setupEditSearchableDropdown() {
    const input = document.getElementById('editParentBranch');
    const dropdown = document.getElementById('editBranchDropdown');
    const hiddenInput = document.getElementById('editParentBranchId');
    
    input.addEventListener('input', () => {
        const query = input.value.toLowerCase();
        const filtered = branches.filter(branch => 
            branch.branch_name.toLowerCase().includes(query) || 
            branch.branch_type.toLowerCase().includes(query)
        );
        showEditDropdown(filtered);
    });
    
    input.addEventListener('focus', () => showEditDropdown(branches));
    
    function showEditDropdown(items) {
        dropdown.innerHTML = '';
        if (items.length === 0) {
            dropdown.style.display = 'none';
            return;
        }
        
        items.forEach(branch => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            const displayText = branch.parent_branch_name 
                ? `${branch.parent_branch_name} → ${branch.branch_name} (${branch.branch_type})`
                : `${branch.branch_name} (${branch.branch_type})`;
            item.textContent = displayText;
            item.addEventListener('click', () => {
                input.value = displayText;
                hiddenInput.value = branch.id;
                dropdown.style.display = 'none';
            });
            dropdown.appendChild(item);
        });
        
        dropdown.style.display = 'block';
    }
}



// Modal event listeners
document.getElementById('closeModal').addEventListener('click', () => {
    document.getElementById('editModal').style.display = 'none';
});

document.getElementById('cancelEdit').addEventListener('click', () => {
    document.getElementById('editModal').style.display = 'none';
});

window.addEventListener('click', (e) => {
    if (e.target === document.getElementById('editModal')) {
        document.getElementById('editModal').style.display = 'none';
    }
});

document.getElementById('editForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = {
        id: document.getElementById('editStationId').value,
        station_name: document.getElementById('editPumpName').value.trim(),
        branch_id: document.getElementById('editParentBranchId').value,
        fuel_type_id: document.getElementById('editPumpTypeId').value || null,
        manufacturer: document.getElementById('editManufacturer').value.trim() || null,
        model: document.getElementById('editModel').value.trim() || null,
        serial_number: document.getElementById('editSerialNumber').value.trim() || null,
        flow_rate: document.getElementById('editFlowRate').value || null,
        location_description: document.getElementById('editLocationDescription').value.trim() || null,
        status: document.getElementById('editStatus').value,
        installation_date: document.getElementById('editInstallationDate').value || null,
        last_maintenance_date: document.getElementById('editLastService').value || null
    };
    
    try {
        const response = await fetch('../../../../server/api/master_setup/fuel_pump_setup/pump-edit.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Station updated successfully!');
            document.getElementById('editModal').style.display = 'none';
            loadStations(); // Reload the table
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error updating station');
        console.error('Error:', error);
    }
});



// Delete station function
let stationToDelete = null;

function deleteStation(stationId) {
    const station = stationsData.find(s => s.id == stationId);
    if (!station) return;
    
    stationToDelete = stationId;
    document.getElementById('deleteStationName').textContent = station.pumpName;
    document.getElementById('deleteModal').style.display = 'block';
}

// Delete modal event listeners
document.getElementById('closeDeleteModal').addEventListener('click', () => {
    document.getElementById('deleteModal').style.display = 'none';
    stationToDelete = null;
});

document.getElementById('cancelDelete').addEventListener('click', () => {
    document.getElementById('deleteModal').style.display = 'none';
    stationToDelete = null;
});

document.getElementById('confirmDelete').addEventListener('click', async () => {
    if (!stationToDelete) return;
    
    try {
        const response = await fetch('../../../../server/api/master_setup/fuel_pump_setup/pump-delete.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: stationToDelete })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Station deleted successfully!');
            document.getElementById('deleteModal').style.display = 'none';
            stationToDelete = null;
            loadStations(); // Reload the table
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error deleting station');
        console.error('Error:', error);
    }
});

window.addEventListener('click', (e) => {
    if (e.target === document.getElementById('deleteModal')) {
        document.getElementById('deleteModal').style.display = 'none';
        stationToDelete = null;
    }
});

// Add event listeners to filters
document.getElementById('searchInput').addEventListener('input', filterStations);
document.getElementById('branchFilter').addEventListener('change', filterStations);
document.getElementById('statusFilter').addEventListener('change', filterStations);
document.getElementById('typeFilter').addEventListener('change', filterStations);

