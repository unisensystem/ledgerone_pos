(function() {
    const API_URL = '../../../../server/api/manufacturing/bill_of_material/list.php';
    const API_BASE = '../../../../server/api/manufacturing/bill_of_material/index.php';
    
    let allBoms = [];
    let rawMaterials = [];
    let productUOMCache = {};
    let selectedIds = new Set();

    function updateSelectionUI() {
        const count = selectedIds.size;
        const printSelectedBtn = document.getElementById('printSelectedBtn');
        const selectedCount = document.getElementById('selectedCount');
        printSelectedBtn.style.display = count > 0 ? 'inline-flex' : 'none';
        selectedCount.textContent = count;
        const allCheckboxes = document.querySelectorAll('#tableBody .row-checkbox');
        const selectAll = document.getElementById('selectAllCheckbox');
        if (allCheckboxes.length > 0) {
            selectAll.checked = [...allCheckboxes].every(cb => cb.checked);
            selectAll.indeterminate = !selectAll.checked && [...allCheckboxes].some(cb => cb.checked);
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
    }

    document.getElementById('selectAllCheckbox').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('#tableBody .row-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = this.checked;
            const id = parseInt(cb.dataset.id);
            this.checked ? selectedIds.add(id) : selectedIds.delete(id);
        });
        updateSelectionUI();
    });

    document.getElementById('printSelectedBtn').addEventListener('click', function() {
        if (selectedIds.size === 0) return;
        const ids = [...selectedIds].join(',');
        window.open(`bulk-print.php?ids=${ids}`, '_blank');
    });

    async function loadBOMs() {
        try {
            const res = await fetch(`${API_URL}?action=list`);
            const data = await res.json();
            if (data.success) {
                allBoms = data.data;
                populateFGFilter();
                filterData();
            } else {
                document.getElementById('tableBody').innerHTML = `<tr><td colspan="8" style="text-align:center;padding:32px;color:#E34F4F;">Failed to load BOMs: ${data.message || 'Unknown error'}</td></tr>`;
            }
        } catch (err) {
            console.error('Error loading BOMs:', err);
            document.getElementById('tableBody').innerHTML = `<tr><td colspan="8" style="text-align:center;padding:32px;color:#E34F4F;">Error loading BOMs. Check console for details.</td></tr>`;
        }
    }

    async function loadRawMaterials() {
        try {
            const res = await fetch(`${API_BASE}?action=raw_materials`);
            const data = await res.json();
            if (data.success) {
                rawMaterials = data.data;
            }
        } catch (err) {
            console.error('Error loading raw materials:', err);
        }
    }

    async function getProductUOM(productId) {
        if (productUOMCache[productId]) return productUOMCache[productId];
        const res = await fetch(`${API_BASE}?action=product_uom&product_id=${productId}`);
        const data = await res.json();
        if (data.success) {
            productUOMCache[productId] = data;
            return data;
        }
        return null;
    }

    function populateFGFilter() {
        const fgFilter = document.getElementById('fgFilter');
        const uniqueFGs = [...new Set(allBoms.map(b => `${b.fg_code} - ${b.fg_name}`))];
        
        fgFilter.innerHTML = '<option value="all">All finished goods</option>';
        uniqueFGs.forEach(fg => {
            const opt = document.createElement('option');
            opt.value = fg;
            opt.textContent = fg;
            fgFilter.appendChild(opt);
        });
    }

    async function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        
        selectedIds.clear();
        updateSelectionUI();

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:32px; color:#6B7280;">No BOM entries found</td></tr>';
            return;
        }

        for (const bom of data) {
            const tr = document.createElement('tr');
            
            // Fetch materials for this BOM
            let materialsHtml = `<span style="color:#6B7280;">${bom.material_count} materials</span>`;
            try {
                const res = await fetch(`${API_URL}?action=view&id=${bom.id}`);
                const result = await res.json();
                if (result.success && result.data.materials) {
                    const materials = result.data.materials.slice(0, 3);
                    materialsHtml = '<div class="raw-summary">';
                    materials.forEach(mat => {
                        materialsHtml += `<span class="raw-tag">${mat.material_code} - ${mat.material_name} (${mat.quantity} ${mat.unit_name})</span>`;
                    });
                    if (result.data.materials.length > 3) {
                        materialsHtml += `<span class="raw-tag">+${result.data.materials.length - 3} more</span>`;
                    }
                    materialsHtml += '</div>';
                }
            } catch (err) {
                console.error('Error loading materials:', err);
            }
            
            const checkboxCell = document.createElement('td');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'row-checkbox';
            checkbox.dataset.id = bom.id;
            checkbox.checked = selectedIds.has(bom.id);
            checkbox.addEventListener('change', function() {
                this.checked ? selectedIds.add(bom.id) : selectedIds.delete(bom.id);
                updateSelectionUI();
            });
            checkboxCell.appendChild(checkbox);
            tr.appendChild(checkboxCell);

            tr.innerHTML += `
                <td><strong>${bom.bom_code}</strong></td>
                <td><strong>${bom.fg_code} - ${bom.fg_name}</strong></td>
                <td><span class="version-pill">v${bom.version}</span></td>
                <td><span class="badge ${bom.is_active == 1 ? 'active' : 'inactive'}">${bom.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
                <td>${materialsHtml}</td>
                <td>${new Date(bom.updated_at).toLocaleDateString()}</td>
                <td class="actions-cell">
                    <button class="btn-icon" onclick="viewBOM(${bom.id})" title="View"><i class="las la-eye"></i></button>
                    <button class="btn-icon" onclick="editBOM(${bom.id})" title="Edit"><i class="las la-pencil-alt"></i></button>
                    <button class="btn-icon" onclick="window.open('print.php?id=${bom.id}','_blank')" title="Print / PDF"><i class="las la-print"></i></button>
                    <button class="btn-icon" onclick="deleteBOM(${bom.id})" title="Delete"><i class="las la-trash"></i></button>
                </td>
            `;
            
            tbody.appendChild(tr);
        }
    }

    function filterData() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        const fg = document.getElementById('fgFilter').value;

        const filtered = allBoms.filter(bom => {
            const fgName = `${bom.fg_code} - ${bom.fg_name}`.toLowerCase();
            const matchesSearch = searchTerm === '' || fgName.includes(searchTerm) || bom.version.includes(searchTerm);

            let matchesStatus = true;
            if (status === '1') matchesStatus = bom.is_active == 1;
            else if (status === '0') matchesStatus = bom.is_active == 0;

            let matchesFg = true;
            if (fg !== 'all') {
                matchesFg = `${bom.fg_code} - ${bom.fg_name}` === fg;
            }

            return matchesSearch && matchesStatus && matchesFg;
        });
        
        renderTable(filtered);
    }

    window.viewBOM = async function(id) {
        try {
            const res = await fetch(`${API_URL}?action=view&id=${id}`);
            const data = await res.json();
            
            if (data.success && data.data) {
                const bom = data.data;
                let html = `
                    <div class="detail-row">
                        <div class="detail-label">BOM Code:</div>
                        <div class="detail-value"><strong>${bom.bom_code}</strong></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Finished Good:</div>
                        <div class="detail-value"><strong>${bom.fg_code} - ${bom.fg_name}</strong></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Version:</div>
                        <div class="detail-value">${bom.version}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value"><span class="badge ${bom.is_active == 1 ? 'active' : 'inactive'}">${bom.is_active == 1 ? 'Active' : 'Inactive'}</span></div>
                    </div>
                    ${bom.remarks ? `<div class="detail-row">
                        <div class="detail-label">Remarks:</div>
                        <div class="detail-value">${bom.remarks}</div>
                    </div>` : ''}
                    <div class="raw-title">Raw Materials</div>
                `;
                
                // Group materials by product
                const materialsByProduct = {};
                bom.materials.forEach(mat => {
                    if (!materialsByProduct[mat.raw_material_id]) {
                        materialsByProduct[mat.raw_material_id] = {
                            code: mat.material_code,
                            name: mat.material_name,
                            units: []
                        };
                    }
                    materialsByProduct[mat.raw_material_id].units.push({
                        quantity: mat.quantity,
                        unit_name: mat.unit_name
                    });
                });
                
                // Display grouped materials
                for (const productId in materialsByProduct) {
                    const product = materialsByProduct[productId];
                    const unitsText = product.units.map(u => `${u.quantity} ${u.unit_name}`).join(', ');
                    html += `
                        <div class="detail-row">
                            <div class="detail-label">${product.code} - ${product.name}</div>
                            <div class="detail-value">${unitsText}</div>
                        </div>
                    `;
                }
                
                document.getElementById('viewModalBody').innerHTML = html;
                document.getElementById('viewModal').style.display = 'block';
            }
        } catch (err) {
            alert('Error loading BOM details');
        }
    };

    window.closeViewModal = function() {
        document.getElementById('viewModal').style.display = 'none';
    };

    window.editBOM = async function(id) {
        try {
            const res = await fetch(`${API_URL}?action=view&id=${id}`);
            const data = await res.json();
            
            console.log('Edit BOM data:', data);
            
            if (data.success && data.data) {
                const bom = data.data;
                
                document.getElementById('editBomId').value = bom.id;
                document.getElementById('editBomCode').value = bom.bom_code;
                document.getElementById('editFinishedGood').value = `${bom.fg_code} - ${bom.fg_name}`;
                document.getElementById('editBomBaseQty').value = bom.bom_base_qty || 1;
                document.getElementById('editVersion').value = bom.version;
                document.getElementById('editActive').checked = bom.is_active == 1;
                document.getElementById('editBatchLocked').checked = bom.batch_locked == 1;
                document.getElementById('editRemarks').value = bom.remarks || '';
                
                const editMaterialList = document.getElementById('editMaterialList');
                editMaterialList.innerHTML = '';
                
                if (bom.materials && bom.materials.length > 0) {
                    const materialsByProduct = {};
                    bom.materials.forEach(mat => {
                        if (!materialsByProduct[mat.raw_material_id]) {
                            materialsByProduct[mat.raw_material_id] = [];
                        }
                        materialsByProduct[mat.raw_material_id].push(mat);
                    });
                    
                    for (const productId in materialsByProduct) {
                        const materials = materialsByProduct[productId];
                        const row = await createEditMaterialRow(materials[0]);
                        
                        const unitsContainer = row.querySelector('.units-container');
                        const unitInputs = unitsContainer.querySelectorAll('input[type="number"]');
                        
                        materials.forEach(mat => {
                            unitInputs.forEach(input => {
                                if (input.dataset.unitId == mat.unit_id) {
                                    input.value = mat.quantity;
                                }
                            });
                        });
                        
                        editMaterialList.appendChild(row);
                    }
                } else {
                    const row = await createEditMaterialRow();
                    editMaterialList.appendChild(row);
                }
                
                document.getElementById('editModal').style.display = 'block';
            }
        } catch (err) {
            console.error('Error loading BOM for edit:', err);
            alert('Error loading BOM for edit');
        }
    };

    async function createEditMaterialRow(material = null) {
        const rowDiv = document.createElement('div');
        rowDiv.className = 'material-row';

        const rawGroup = document.createElement('div');
        rawGroup.className = 'field-group';
        const rawSelect = document.createElement('select');
        rawSelect.className = 'form-control';
        rawSelect.required = true;
        
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select material';
        rawSelect.appendChild(placeholder);
        
        rawMaterials.forEach(mat => {
            const opt = document.createElement('option');
            opt.value = mat.id;
            opt.textContent = `${mat.code} - ${mat.name}`;
            if (material && mat.id == material.raw_material_id) {
                opt.selected = true;
            }
            rawSelect.appendChild(opt);
        });
        
        rawGroup.appendChild(rawSelect);

        const unitsContainer = document.createElement('div');
        unitsContainer.className = 'units-container';
        unitsContainer.style.display = 'flex';
        unitsContainer.style.gap = '16px';
        unitsContainer.style.flex = '1';

        if (material && material.raw_material_id) {
            const uomData = await getProductUOM(material.raw_material_id);
            if (uomData && uomData.units) {
                rowDiv.dataset.productId = material.raw_material_id;
                
                uomData.units.forEach(unit => {
                    const unitGroup = document.createElement('div');
                    unitGroup.className = 'field-group';
                    const unitLabel = document.createElement('label');
                    unitLabel.className = 'form-label';
                    unitLabel.textContent = unit.uom_name;
                    const unitInput = document.createElement('input');
                    unitInput.type = 'number';
                    unitInput.className = 'form-control';
                    unitInput.placeholder = '0';
                    unitInput.step = 'any';
                    unitInput.dataset.unitId = unit.id;
                    unitInput.dataset.unitName = unit.uom_name;
                    
                    if (material && material.unit_id == unit.id) {
                        unitInput.value = material.quantity;
                    }
                    
                    unitGroup.appendChild(unitLabel);
                    unitGroup.appendChild(unitInput);
                    unitsContainer.appendChild(unitGroup);
                });
            }
        }

        rawSelect.addEventListener('change', async function() {
            const productId = this.value;
            if (productId) {
                const uomData = await getProductUOM(productId);
                if (uomData && uomData.units) {
                    rowDiv.dataset.productId = productId;
                    unitsContainer.innerHTML = '';
                    
                    uomData.units.forEach(unit => {
                        const unitGroup = document.createElement('div');
                        unitGroup.className = 'field-group';
                        const unitLabel = document.createElement('label');
                        unitLabel.className = 'form-label';
                        unitLabel.textContent = unit.uom_name;
                        const unitInput = document.createElement('input');
                        unitInput.type = 'number';
                        unitInput.className = 'form-control';
                        unitInput.placeholder = '0';
                        unitInput.step = 'any';
                        unitInput.dataset.unitId = unit.id;
                        unitInput.dataset.unitName = unit.uom_name;
                        unitGroup.appendChild(unitLabel);
                        unitGroup.appendChild(unitInput);
                        unitsContainer.appendChild(unitGroup);
                    });
                }
            }
        });

        const actionGroup = document.createElement('div');
        actionGroup.className = 'action-group';
        
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn-icon';
        removeBtn.innerHTML = '<i class="las la-minus"></i>';
        removeBtn.addEventListener('click', () => rowDiv.remove());
        
        actionGroup.appendChild(removeBtn);

        rowDiv.appendChild(rawGroup);
        rowDiv.appendChild(unitsContainer);
        rowDiv.appendChild(actionGroup);

        return rowDiv;
    }

    document.getElementById('editAddMaterialBtn').addEventListener('click', async () => {
        const row = await createEditMaterialRow();
        document.getElementById('editMaterialList').appendChild(row);
    });

    window.closeEditModal = function() {
        document.getElementById('editModal').style.display = 'none';
    };

    window.updateBOM = async function() {
        const bomId = parseInt(document.getElementById('editBomId').value);
        const bomBaseQty = parseFloat(document.getElementById('editBomBaseQty').value) || 1;
        const version = document.getElementById('editVersion').value;
        const isActive = document.getElementById('editActive').checked ? 1 : 0;
        const batchLocked = document.getElementById('editBatchLocked').checked ? 1 : 0;
        const remarks = document.getElementById('editRemarks').value;
        
        const materials = [];
        const rows = document.querySelectorAll('#editMaterialList .material-row');
        
        for (const row of rows) {
            const rawSelect = row.querySelector('select');
            const productId = rawSelect.value;
            
            if (!productId) {
                alert('Please select all materials');
                return;
            }
            
            const unitsContainer = row.querySelector('.units-container');
            const unitInputs = unitsContainer.querySelectorAll('input[type="number"]');
            
            unitInputs.forEach(input => {
                const qty = parseFloat(input.value) || 0;
                if (qty > 0) {
                    materials.push({
                        raw_material_id: parseInt(productId),
                        quantity: qty,
                        unit_id: parseInt(input.dataset.unitId)
                    });
                }
            });
        }
        
        if (materials.length === 0) {
            alert('Please enter at least one material quantity');
            return;
        }
        
        try {
            const res = await fetch(API_URL, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id: bomId,
                    bom_base_qty: bomBaseQty,
                    version,
                    is_active: isActive,
                    batch_locked: batchLocked,
                    remarks,
                    materials
                })
            });
            
            const data = await res.json();
            console.log('Update response:', data);
            
            if (data.success) {
                window.location.href = 'list.php?success=2';
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            console.error('Update error:', err);
            alert('Error updating BOM');
        }
    };

    window.deleteBOM = async function(id) {
        if (!confirm('Are you sure you want to delete this BOM?')) return;
        
        try {
            const res = await fetch(`${API_URL}?id=${id}`, {
                method: 'DELETE'
            });
            
            const data = await res.json();
            
            if (data.success) {
                window.location.href = 'list.php?success=3';
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error deleting BOM');
        }
    };

    document.getElementById('searchInput').addEventListener('input', filterData);
    document.getElementById('statusFilter').addEventListener('change', filterData);
    document.getElementById('fgFilter').addEventListener('change', filterData);

    document.getElementById('resetFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = 'all';
        document.getElementById('fgFilter').value = 'all';
        filterData();
    });

    window.onclick = function(event) {
        if (event.target.id === 'viewModal') {
            closeViewModal();
        }
        if (event.target.id === 'editModal') {
            closeEditModal();
        }
    };

    async function init() {
        await loadRawMaterials();
        await loadBOMs();
        
        // Check for success message
        const urlParams = new URLSearchParams(window.location.search);
        const successType = urlParams.get('success');
        if (successType) {
            const successMsg = document.getElementById('successMessage');
            const msgText = successMsg.querySelector('span');
            if (successType === '1') {
                msgText.textContent = 'BOM saved successfully!';
            } else if (successType === '2') {
                msgText.textContent = 'BOM updated successfully!';
            } else if (successType === '3') {
                msgText.textContent = 'BOM deleted successfully!';
            }
            successMsg.style.display = 'flex';
            setTimeout(() => {
                successMsg.style.display = 'none';
                window.history.replaceState({}, '', 'list.php');
            }, 3000);
        }
    }

    init();
})();
