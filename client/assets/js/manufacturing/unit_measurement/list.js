(function() {
    const API_URL = '../../../../server/api/manufacturing/unit_measurement/index.php';
    let allUOMs = [];

    async function loadUOMs() {
        try {
            const res = await fetch(`${API_URL}?action=list`);
            const data = await res.json();
            if (data.success) {
                allUOMs = data.data;
                filterData();
            }
        } catch (err) {
            console.error('Error loading UOMs:', err);
        }
    }

    function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:32px; color:#6B7280;">No UOMs found</td></tr>';
            return;
        }

        data.forEach(uom => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${uom.uom_name}</strong></td>
                <td><span class="type-badge">${uom.uom_type}</span></td>
                <td>${uom.base_unit_name || '-'}</td>
                <td>${uom.conversion_factor}</td>
                <td><span class="badge ${uom.is_base_unit == 1 ? 'base' : 'derived'}">${uom.is_base_unit == 1 ? 'Base Unit' : 'Derived'}</span></td>
                <td class="actions-cell">
                    <button class="btn-icon" onclick="viewUOM(${uom.id})" title="View"><i class="las la-eye"></i></button>
                    <button class="btn-icon" onclick="editUOM(${uom.id})" title="Edit"><i class="las la-pencil-alt"></i></button>
                    <button class="btn-icon" onclick="deleteUOM(${uom.id})" title="Delete"><i class="las la-trash"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function filterData() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const type = document.getElementById('typeFilter').value;
        const base = document.getElementById('baseFilter').value;

        const filtered = allUOMs.filter(uom => {
            const matchesSearch = searchTerm === '' || uom.uom_name.toLowerCase().includes(searchTerm);
            const matchesType = type === 'all' || uom.uom_type === type;
            let matchesBase = true;
            if (base === '1') matchesBase = uom.is_base_unit == 1;
            else if (base === '0') matchesBase = uom.is_base_unit == 0;
            
            return matchesSearch && matchesType && matchesBase;
        });
        
        renderTable(filtered);
    }

    async function loadBaseUnits(type) {
        if (!type) return;
        try {
            const res = await fetch(`${API_URL}?action=base_units&type=${type}`);
            const data = await res.json();
            if (data.success) {
                const select = document.getElementById('baseUnitId');
                select.innerHTML = '<option value="">Select Base Unit</option>';
                data.data.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.id;
                    opt.textContent = u.uom_name;
                    select.appendChild(opt);
                });
            }
        } catch (err) {
            console.error('Error loading base units:', err);
        }
    }

    function toggleBaseUnitFields() {
        const isBase = document.getElementById('isBaseUnit').checked;
        const baseUnitGroup = document.getElementById('baseUnitGroup');
        const conversionGroup = document.getElementById('conversionGroup');
        const baseUnitSelect = document.getElementById('baseUnitId');
        const conversionInput = document.getElementById('conversionFactor');

        if (isBase) {
            baseUnitGroup.style.display = 'none';
            conversionGroup.style.display = 'none';
            baseUnitSelect.value = '';
            conversionInput.value = '1';
            baseUnitSelect.removeAttribute('required');
        } else {
            baseUnitGroup.style.display = 'block';
            conversionGroup.style.display = 'block';
            baseUnitSelect.setAttribute('required', 'required');
        }
    }

    window.openAddModal = function() {
        document.getElementById('modalTitle').textContent = 'Add New UOM';
        document.getElementById('uomId').value = '';
        document.getElementById('uomName').value = '';
        document.getElementById('uomType').value = '';
        document.getElementById('isBaseUnit').checked = false;
        document.getElementById('baseUnitId').value = '';
        document.getElementById('conversionFactor').value = '1';
        toggleBaseUnitFields();
        document.getElementById('uomModal').style.display = 'block';
    };

    window.editUOM = async function(id) {
        try {
            const res = await fetch(`${API_URL}?action=view&id=${id}`);
            const data = await res.json();
            
            if (data.success && data.data) {
                const uom = data.data;
                document.getElementById('modalTitle').textContent = 'Edit UOM';
                document.getElementById('uomId').value = uom.id;
                document.getElementById('uomName').value = uom.uom_name;
                document.getElementById('uomType').value = uom.uom_type;
                document.getElementById('isBaseUnit').checked = uom.is_base_unit == 1;
                document.getElementById('conversionFactor').value = uom.conversion_factor;
                
                await loadBaseUnits(uom.uom_type);
                document.getElementById('baseUnitId').value = uom.base_unit_id || '';
                
                toggleBaseUnitFields();
                document.getElementById('uomModal').style.display = 'block';
            }
        } catch (err) {
            alert('Error loading UOM details');
        }
    };

    window.saveUOM = async function() {
        const id = document.getElementById('uomId').value;
        const uomName = document.getElementById('uomName').value;
        let uomType = document.getElementById('uomType').value;
        const customType = document.getElementById('customType').value;
        const isBaseUnit = document.getElementById('isBaseUnit').checked ? 1 : 0;
        const baseUnitId = document.getElementById('baseUnitId').value;
        const conversionFactor = document.getElementById('conversionFactor').value;

        // Use custom type if selected
        if (uomType === '__custom__' && customType.trim()) {
            uomType = customType.trim();
        } else if (uomType === '__custom__') {
            alert('Please enter a custom type');
            return;
        }

        if (!uomName || !uomType) {
            alert('Please fill all required fields');
            return;
        }

        if (isBaseUnit == 0 && !baseUnitId) {
            alert('Please select a base unit');
            return;
        }

        if (conversionFactor <= 0) {
            alert('Conversion factor must be greater than 0');
            return;
        }

        const payload = {
            uom_name: uomName,
            uom_type: uomType,
            is_base_unit: isBaseUnit,
            base_unit_id: isBaseUnit == 1 ? null : baseUnitId,
            conversion_factor: isBaseUnit == 1 ? 1 : conversionFactor
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
                showSuccess(id ? 'UOM updated successfully!' : 'UOM added successfully!');
                loadUOMs();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error saving UOM');
        }
    };

    window.viewUOM = async function(id) {
        try {
            const res = await fetch(`${API_URL}?action=view&id=${id}`);
            const data = await res.json();
            
            if (data.success && data.data) {
                const uom = data.data;
                let html = `
                    <div class="detail-row">
                        <div class="detail-label">UOM Name:</div>
                        <div class="detail-value"><strong>${uom.uom_name}</strong></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Type:</div>
                        <div class="detail-value"><span class="type-badge">${uom.uom_type}</span></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Is Base Unit:</div>
                        <div class="detail-value"><span class="badge ${uom.is_base_unit == 1 ? 'base' : 'derived'}">${uom.is_base_unit == 1 ? 'Yes' : 'No'}</span></div>
                    </div>
                    ${uom.base_unit_name ? `<div class="detail-row">
                        <div class="detail-label">Base Unit:</div>
                        <div class="detail-value">${uom.base_unit_name}</div>
                    </div>` : ''}
                    <div class="detail-row">
                        <div class="detail-label">Conversion Factor:</div>
                        <div class="detail-value">${uom.conversion_factor}</div>
                    </div>
                `;
                
                document.getElementById('viewModalBody').innerHTML = html;
                document.getElementById('viewModal').style.display = 'block';
            }
        } catch (err) {
            alert('Error loading UOM details');
        }
    };

    window.deleteUOM = async function(id) {
        if (!confirm('Are you sure you want to delete this UOM?')) return;
        
        try {
            const res = await fetch(`${API_URL}?id=${id}`, {
                method: 'DELETE'
            });
            const data = await res.json();
            
            if (data.success) {
                showSuccess('UOM deleted successfully!');
                loadUOMs();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error deleting UOM');
        }
    };

    window.closeModal = function() {
        document.getElementById('uomModal').style.display = 'none';
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

    document.getElementById('isBaseUnit').addEventListener('change', toggleBaseUnitFields);
    document.getElementById('uomType').addEventListener('change', function() {
        const customInput = document.getElementById('customType');
        if (this.value === '__custom__') {
            customInput.style.display = 'block';
            customInput.focus();
        } else {
            customInput.style.display = 'none';
            customInput.value = '';
            if (this.value) {
                loadBaseUnits(this.value);
            }
        }
    });

    document.getElementById('customType').addEventListener('blur', function() {
        if (this.value.trim()) {
            const select = document.getElementById('uomType');
            const newType = this.value.trim();
            
            let exists = false;
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value === newType) {
                    exists = true;
                    select.value = newType;
                    break;
                }
            }
            
            if (!exists) {
                const opt = document.createElement('option');
                opt.value = newType;
                opt.textContent = newType;
                select.insertBefore(opt, select.options[select.options.length - 1]);
                select.value = newType;
            }
            
            this.style.display = 'none';
            this.value = '';
            loadBaseUnits(newType);
        } else {
            document.getElementById('uomType').value = '';
            this.style.display = 'none';
        }
    });

    document.getElementById('searchInput').addEventListener('input', filterData);
    document.getElementById('typeFilter').addEventListener('change', filterData);
    document.getElementById('baseFilter').addEventListener('change', filterData);
    
    document.getElementById('resetFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        document.getElementById('typeFilter').value = 'all';
        document.getElementById('baseFilter').value = 'all';
        filterData();
    });

    window.onclick = function(event) {
        if (event.target.id === 'uomModal') {
            closeModal();
        }
        if (event.target.id === 'viewModal') {
            closeViewModal();
        }
    };

    loadUOMs();
})();
