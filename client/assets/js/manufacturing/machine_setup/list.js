(function() {
    const API_URL = '../../../../server/api/manufacturing/machine_setup/index.php';
    let allMachines = [];
    let branches = [];

    async function loadMachines() {
        try {
            const res = await fetch(`${API_URL}?action=list`);
            const data = await res.json();
            if (data.success) {
                allMachines = data.data;
                filterData();
            }
        } catch (err) {
            console.error('Error loading machines:', err);
        }
    }

    async function loadBranches() {
        try {
            const res = await fetch(`${API_URL}?action=branches`);
            const data = await res.json();
            if (data.success) {
                branches = data.data;
                const select = document.getElementById('branchId');
                select.innerHTML = '<option value="">Select branch</option>';
                branches.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = b.branch_name;
                    select.appendChild(opt);
                });
            }
        } catch (err) {
            console.error('Error loading branches:', err);
        }
    }

    async function generateCode() {
        try {
            const res = await fetch(`${API_URL}?action=next_code`);
            const data = await res.json();
            if (data.success) {
                document.getElementById('machineCode').value = data.code;
            }
        } catch (err) {
            console.error('Error generating code:', err);
        }
    }

    function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:32px; color:#6B7280;">No machines found</td></tr>';
            return;
        }

        data.forEach(machine => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${machine.code}</strong></td>
                <td><strong>${machine.machine_name}</strong></td>
                <td>${machine.branch_name || '-'}</td>
                <td>${machine.capacity_speed || '-'}</td>
                <td><span class="badge ${machine.is_active == 1 ? 'active' : 'inactive'}">${machine.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
                <td class="actions-cell">
                    <button class="btn-icon" onclick="viewMachine(${machine.id})" title="View"><i class="las la-eye"></i></button>
                    <button class="btn-icon" onclick="editMachine(${machine.id})" title="Edit"><i class="las la-pencil-alt"></i></button>
                    <button class="btn-icon" onclick="deleteMachine(${machine.id})" title="Delete"><i class="las la-trash"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function filterData() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;

        const filtered = allMachines.filter(machine => {
            const matchesSearch = searchTerm === '' || 
                machine.code.toLowerCase().includes(searchTerm) || 
                machine.machine_name.toLowerCase().includes(searchTerm);
            
            let matchesStatus = true;
            if (status === '1') matchesStatus = machine.is_active == 1;
            else if (status === '0') matchesStatus = machine.is_active == 0;
            
            return matchesSearch && matchesStatus;
        });
        
        renderTable(filtered);
    }

    window.openAddModal = function() {
        document.getElementById('modalTitle').textContent = 'Add Machine';
        document.getElementById('machineId').value = '';
        generateCode();
        document.getElementById('machineName').value = '';
        document.getElementById('branchId').value = '';
        document.getElementById('capacitySpeed').value = '';
        document.getElementById('notes').value = '';
        document.getElementById('isActive').checked = true;
        document.getElementById('machineModal').style.display = 'block';
    };

    window.editMachine = async function(id) {
        try {
            const res = await fetch(`${API_URL}?action=view&id=${id}`);
            const data = await res.json();
            
            if (data.success && data.data) {
                const machine = data.data;
                document.getElementById('modalTitle').textContent = 'Edit Machine';
                document.getElementById('machineId').value = machine.id;
                document.getElementById('machineCode').value = machine.code;
                document.getElementById('machineName').value = machine.machine_name;
                document.getElementById('branchId').value = machine.branch_id || '';
                document.getElementById('capacitySpeed').value = machine.capacity_speed || '';
                document.getElementById('notes').value = machine.notes || '';
                document.getElementById('isActive').checked = machine.is_active == 1;
                document.getElementById('machineModal').style.display = 'block';
            }
        } catch (err) {
            alert('Error loading machine details');
        }
    };

    window.saveMachine = async function() {
        const id = document.getElementById('machineId').value;
        const code = document.getElementById('machineCode').value;
        const machineName = document.getElementById('machineName').value;
        const branchId = document.getElementById('branchId').value;
        const capacitySpeed = document.getElementById('capacitySpeed').value;
        const notes = document.getElementById('notes').value;
        const isActive = document.getElementById('isActive').checked ? 1 : 0;

        if (!code || !machineName || !branchId) {
            alert('Please fill all required fields');
            return;
        }

        const payload = {
            code,
            machine_name: machineName,
            branch_id: branchId,
            capacity_speed: capacitySpeed,
            notes,
            is_active: isActive
        };

        if (id) payload.id = id;

        try {
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (data.success) {
                closeModal();
                showSuccess(id ? 'Machine updated successfully!' : 'Machine added successfully!');
                loadMachines();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error saving machine');
        }
    };

    window.viewMachine = async function(id) {
        try {
            const res = await fetch(`${API_URL}?action=view&id=${id}`);
            const data = await res.json();
            
            if (data.success && data.data) {
                const machine = data.data;
                let html = `
                    <div class="detail-row">
                        <div class="detail-label">Code:</div>
                        <div class="detail-value"><strong>${machine.code}</strong></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Machine Name:</div>
                        <div class="detail-value"><strong>${machine.machine_name}</strong></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Branch:</div>
                        <div class="detail-value">${machine.branch_name || '-'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Capacity/Speed:</div>
                        <div class="detail-value">${machine.capacity_speed || '-'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value"><span class="badge ${machine.is_active == 1 ? 'active' : 'inactive'}">${machine.is_active == 1 ? 'Active' : 'Inactive'}</span></div>
                    </div>
                    ${machine.notes ? `<div class="detail-row">
                        <div class="detail-label">Notes:</div>
                        <div class="detail-value">${machine.notes}</div>
                    </div>` : ''}
                `;
                
                document.getElementById('viewModalBody').innerHTML = html;
                document.getElementById('viewModal').style.display = 'block';
            }
        } catch (err) {
            alert('Error loading machine details');
        }
    };

    window.deleteMachine = async function(id) {
        if (!confirm('Are you sure you want to delete this machine?')) return;
        
        try {
            const res = await fetch(`${API_URL}?id=${id}`, {
                method: 'DELETE'
            });
            const data = await res.json();
            
            if (data.success) {
                showSuccess('Machine deleted successfully!');
                loadMachines();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error deleting machine');
        }
    };

    window.closeModal = function() {
        document.getElementById('machineModal').style.display = 'none';
    };

    window.closeViewModal = function() {
        document.getElementById('viewModal').style.display = 'none';
    };

    function showSuccess(message) {
        const successMsg = document.getElementById('successMessage');
        successMsg.querySelector('span').textContent = message;
        successMsg.style.display = 'flex';
        setTimeout(() => {
            successMsg.style.display = 'none';
        }, 3000);
    }

    document.getElementById('searchInput').addEventListener('input', filterData);
    document.getElementById('statusFilter').addEventListener('change', filterData);
    
    document.getElementById('resetFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = 'all';
        filterData();
    });

    window.onclick = function(event) {
        if (event.target.id === 'machineModal') {
            closeModal();
        }
        if (event.target.id === 'viewModal') {
            closeViewModal();
        }
    };

    async function init() {
        await loadBranches();
        await loadMachines();
    }

    init();
})();
