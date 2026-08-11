(function() {
    const API_URL = '../../../../server/api/manufacturing/bill_of_material/index.php';
    let finishedGoods = [];
    let rawMaterials = [];
    let productUOMCache = {};
    let maxUnits = 0;

    const materialListEl = document.getElementById('materialList');
    const addBtn = document.getElementById('addMaterialBtn');
    const finishedGoodInput = document.getElementById('finishedGood');
    const saveBtn = document.querySelector('#saveBtn');
    const cancelBtn = document.querySelector('#cancelBtn');

    async function loadFinishedGoods() {
        const res = await fetch(`${API_URL}?action=finished_goods`);
        const data = await res.json();
        if (data.success) {
            finishedGoods = data.data;
            const datalist = document.getElementById('productList');
            datalist.innerHTML = '';
            finishedGoods.forEach(p => {
                const opt = document.createElement('option');
                opt.value = `${p.code} - ${p.name}`;
                opt.dataset.id = p.id;
                datalist.appendChild(opt);
            });
        }
    }

    async function loadRawMaterials() {
        const res = await fetch(`${API_URL}?action=raw_materials`);
        const data = await res.json();
        if (data.success) {
            rawMaterials = data.data;
        }
    }

    async function getProductUOM(productId) {
        if (productUOMCache[productId]) return productUOMCache[productId];
        const res = await fetch(`${API_URL}?action=product_uom&product_id=${productId}`);
        const data = await res.json();
        if (data.success) {
            productUOMCache[productId] = data;
            return data;
        }
        return null;
    }

    function recalculateMaxUnits() {
        const rows = materialListEl.querySelectorAll('.material-row');
        let max = 0;
        rows.forEach(row => {
            const unitCount = parseInt(row.dataset.unitCount || 0);
            if (unitCount > max) max = unitCount;
        });
        maxUnits = max;
    }

    async function createMaterialRow() {
        const uid = Date.now() + Math.floor(Math.random() * 1000);
        const rowDiv = document.createElement('div');
        rowDiv.className = 'material-row';
        rowDiv.dataset.unitCount = 0;

        const rawGroup = document.createElement('div');
        rawGroup.className = 'field-group';
        const rawLabel = document.createElement('label');
        rawLabel.className = 'form-label';
        rawLabel.textContent = 'Raw material';
        rawLabel.htmlFor = `rawMat_${uid}`;
        const rawInput = document.createElement('input');
        rawInput.type = 'text';
        rawInput.id = `rawMat_${uid}`;
        rawInput.className = 'form-control';
        rawInput.setAttribute('list', `rawMatList_${uid}`);
        rawInput.placeholder = 'Search material';
        rawInput.required = true;
        const rawDatalist = document.createElement('datalist');
        rawDatalist.id = `rawMatList_${uid}`;
        rawMaterials.forEach(mat => {
            const opt = document.createElement('option');
            opt.value = `${mat.code} - ${mat.name}`;
            opt.dataset.id = mat.id;
            rawDatalist.appendChild(opt);
        });
        rawGroup.appendChild(rawLabel);
        rawGroup.appendChild(rawInput);
        rawGroup.appendChild(rawDatalist);

        const unitsContainer = document.createElement('div');
        unitsContainer.className = 'units-container';
        unitsContainer.style.display = 'flex';
        unitsContainer.style.gap = '16px';
        unitsContainer.style.flex = '1';

        rawInput.addEventListener('change', async function() {
            const selected = rawMaterials.find(m => `${m.code} - ${m.name}` === this.value);
            if (selected) {
                const uomData = await getProductUOM(selected.id);
                if (uomData && uomData.units && uomData.units.length > 0) {
                    rowDiv.dataset.productId = selected.id;
                    rowDiv.dataset.unitCount = uomData.units.length;
                    unitsContainer.innerHTML = '';
                    
                    uomData.units.forEach((unit) => {
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
                        unitInput.min = '0';
                        unitInput.dataset.unitId = unit.id;
                        unitInput.dataset.unitName = unit.uom_name;
                        unitInput.dataset.conversionFactor = unit.conversion_factor || 1;
                        unitInput.dataset.isBaseUnit = unit.is_base_unit || 0;
                        unitGroup.appendChild(unitLabel);
                        unitGroup.appendChild(unitInput);
                        unitsContainer.appendChild(unitGroup);
                    });
                    
                    recalculateMaxUnits();
                    updateAllRowsStructure();
                }
            }
        });

        const actionGroup = document.createElement('div');
        actionGroup.className = 'action-group';
        const addBtnIcon = document.createElement('button');
        addBtnIcon.type = 'button';
        addBtnIcon.className = 'btn btn-icon';
        addBtnIcon.innerHTML = '<i class="las la-plus"></i>';
        addBtnIcon.addEventListener('click', async (e) => {
            e.preventDefault();
            const newRow = await createMaterialRow();
            materialListEl.appendChild(newRow);
        });

        const removeBtnIcon = document.createElement('button');
        removeBtnIcon.type = 'button';
        removeBtnIcon.className = 'btn btn-icon danger';
        removeBtnIcon.innerHTML = '<i class="las la-minus"></i>';
        removeBtnIcon.addEventListener('click', (e) => {
            e.preventDefault();
            if (materialListEl.children.length > 1) {
                rowDiv.remove();
                recalculateMaxUnits();
                updateAllRowsStructure();
            }
        });

        actionGroup.appendChild(addBtnIcon);
        actionGroup.appendChild(removeBtnIcon);

        rowDiv.appendChild(rawGroup);
        rowDiv.appendChild(unitsContainer);
        rowDiv.appendChild(actionGroup);

        return rowDiv;
    }

    function updateAllRowsStructure() {
        const rows = materialListEl.querySelectorAll('.material-row');
        rows.forEach(row => {
            const unitsContainer = row.querySelector('.units-container');
            if (!unitsContainer) return;
            
            const currentUnits = unitsContainer.querySelectorAll('.field-group');
            const currentCount = currentUnits.length;
            
            if (currentCount < maxUnits) {
                for (let i = currentCount; i < maxUnits; i++) {
                    const unitGroup = document.createElement('div');
                    unitGroup.className = 'field-group';
                    const unitLabel = document.createElement('label');
                    unitLabel.className = 'form-label';
                    unitLabel.textContent = '-';
                    const unitInput = document.createElement('input');
                    unitInput.type = 'text';
                    unitInput.className = 'form-control';
                    unitInput.value = '-';
                    unitInput.readOnly = true;
                    unitInput.style.background = '#F2F4F8';
                    unitInput.style.color = '#9AA1AE';
                    unitGroup.appendChild(unitLabel);
                    unitGroup.appendChild(unitInput);
                    unitsContainer.appendChild(unitGroup);
                }
            }
        });
    }

    async function initRows() {
        materialListEl.innerHTML = '';
        const row = await createMaterialRow();
        materialListEl.appendChild(row);
    }

    async function saveBOM() {
        const finishedGoodValue = finishedGoodInput.value;
        const selectedFG = finishedGoods.find(p => `${p.code} - ${p.name}` === finishedGoodValue);
        
        if (!selectedFG) {
            alert('Please select a valid finished good');
            return;
        }

        const materials = [];
        const rows = materialListEl.querySelectorAll('.material-row');
        
        for (const row of rows) {
            const rawInput = row.querySelector('input[list]');
            const rawValue = rawInput.value;
            const selectedRM = rawMaterials.find(m => `${m.code} - ${m.name}` === rawValue);
            
            if (!selectedRM) {
                alert('Please select valid raw materials');
                return;
            }
            
            const unitsContainer = row.querySelector('.units-container');
            const unitInputs = unitsContainer.querySelectorAll('input[type="number"]');
            
            let hasQuantity = false;
            unitInputs.forEach(input => {
                const qty = parseFloat(input.value) || 0;
                if (qty > 0) {
                    hasQuantity = true;
                    materials.push({
                        raw_material_id: selectedRM.id,
                        quantity: qty,
                        unit_id: parseInt(input.dataset.unitId)
                    });
                }
            });
            
            if (!hasQuantity) {
                alert('Please enter at least one quantity for each material');
                return;
            }
        }

        if (materials.length === 0) {
            alert('Please enter at least one material quantity');
            return;
        }

        const payload = {
            bom_code: document.getElementById('bomCode').value,
            finished_good_id: selectedFG.id,
            version: document.getElementById('version').value,
            is_active: document.getElementById('active').checked ? 1 : 0,
            bom_base_qty: parseFloat(document.getElementById('bomBaseQty').value) || 1,
            batch_locked: document.getElementById('batchLocked').checked ? 1 : 0,
            remarks: document.getElementById('remarks').value,
            materials
        };

        try {
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (data.success) {
                window.location.href = 'list.php?success=1';
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error saving BOM: ' + err.message);
        }
    }

    addBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        const row = await createMaterialRow();
        materialListEl.appendChild(row);
    });

    saveBtn.addEventListener('click', (e) => {
        e.preventDefault();
        saveBOM();
    });

    cancelBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (confirm('Discard changes?')) location.reload();
    });

    async function init() {
        await loadFinishedGoods();
        await loadRawMaterials();
        await generateBOMCode();
        await initRows();
    }

    async function generateBOMCode() {
        try {
            const res = await fetch(`${API_URL}?action=next_code`);
            const data = await res.json();
            if (data.success) {
                document.getElementById('bomCode').value = data.code;
            }
        } catch (err) {
            console.error('Error generating BOM code:', err);
        }
    }

    init();
})();
