(function() {
    const API_URL = '../../../../server/api/manufacturing/production_order/index.php';
    let materialRequirements = [];
    let products = [];
    let boms = [];

    async function loadProducts() {
        const res = await fetch(`${API_URL}?action=products`);
        const data = await res.json();
        if (data.success) {
            products = data.data;
            const datalist = document.getElementById('productList');
            datalist.innerHTML = '';
            products.forEach(p => {
                const opt = document.createElement('option');
                opt.value = `${p.code} - ${p.name}`;
                opt.dataset.id = p.id;
                datalist.appendChild(opt);
            });
        }
    }

    async function loadBOMs(productId) {
        if (!productId) {
            document.getElementById('bomList').innerHTML = '';
            return;
        }
        const res = await fetch(`${API_URL}?action=boms&product_id=${productId}`);
        const data = await res.json();
        if (data.success) {
            boms = data.data;
            const datalist = document.getElementById('bomList');
            datalist.innerHTML = '';
            boms.forEach(b => {
                const opt = document.createElement('option');
                opt.value = `${b.bom_code} - v${b.version}`;
                opt.dataset.id = b.id;
                opt.dataset.version = b.version;
                datalist.appendChild(opt);
            });
        }
    }

    async function loadBranches() {
        const res = await fetch(`${API_URL}?action=branches`);
        const data = await res.json();
        if (data.success) {
            const select = document.getElementById('branchId');
            select.innerHTML = '<option value="">Select Branch</option>';
            data.data.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id;
                opt.textContent = b.branch_name;
                select.appendChild(opt);
            });
        }
    }

    async function loadMachines(branchId) {
        if (!branchId) {
            document.getElementById('machineId').innerHTML = '<option value="">Select Machine</option>';
            return;
        }
        const res = await fetch(`${API_URL}?action=machines&branch_id=${branchId}`);
        const data = await res.json();
        if (data.success) {
            const select = document.getElementById('machineId');
            select.innerHTML = '<option value="">Select Machine</option>';
            data.data.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = `${m.code} - ${m.machine_name}`;
                select.appendChild(opt);
            });
        }
    }

    async function generateOrderNo() {
        const res = await fetch(`${API_URL}?action=next_order_no`);
        const data = await res.json();
        if (data.success) {
            document.getElementById('orderNo').value = data.order_no;
        }
    }

    async function loadMaterials() {
        const productValue = document.getElementById('productId').value;
        const bomValue = document.getElementById('bomId').value;
        const orderQty = document.getElementById('orderQty').value;
        const branchId = document.getElementById('branchId').value;

        const selectedProduct = products.find(p => `${p.code} - ${p.name}` === productValue);
        const selectedBOM = boms.find(b => `${b.bom_code} - v${b.version}` === bomValue);

        if (!selectedBOM || !orderQty || !branchId) {
            alert('Please select BOM, enter quantity, and select branch');
            return;
        }

        if (orderQty <= 0) {
            alert('Order quantity must be greater than 0');
            return;
        }

        try {
            const url = `${API_URL}?action=calculate_materials&bom_id=${selectedBOM.id}&order_qty=${orderQty}&branch_id=${branchId}`;
            console.log('Fetching materials from:', url);
            
            const res = await fetch(url);
            console.log('Response status:', res.status);
            
            const text = await res.text();
            console.log('Raw response:', text);
            
            const data = JSON.parse(text);
            console.log('Parsed data:', data);

            if (data.success) {
                materialRequirements = data.data;
                renderMaterialsTable(data.data);
            } else {
                console.error('API Error:', data.message);
                alert('Error: ' + data.message);
            }
        } catch (err) {
            console.error('Fetch Error:', err);
            alert('Error loading materials: ' + err.message);
        }
    }

    function renderMaterialsTable(materials) {
        const tbody = document.getElementById('materialsTable');
        tbody.innerHTML = '';

        if (materials.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:32px; color:#6B7280;">No materials found in BOM</td></tr>';
            return;
        }

        // Find max units
        let maxUnits = 0;
        materials.forEach(mat => {
            if (mat.units.length > maxUnits) {
                maxUnits = mat.units.length;
            }
        });

        materials.forEach(mat => {
            const tr = document.createElement('tr');
            
            // Material name cell
            const nameCell = document.createElement('td');
            nameCell.innerHTML = `<strong>${mat.material_code} - ${mat.material_name}</strong>`;
            tr.appendChild(nameCell);
            
            // Units cells - create cells for all units
            for (let i = 0; i < maxUnits; i++) {
                const unitCell = document.createElement('td');
                if (i < mat.units.length) {
                    const unit = mat.units[i];
                    unitCell.innerHTML = `
                        <div style="text-align: center;">
                            <div style="font-weight: 600; color: #0E1A2B;">${unit.uom_name}</div>
                            <div style="font-size: 14px; color: #2F3B4C; margin-top: 4px;">${unit.required_qty}</div>
                        </div>
                    `;
                } else {
                    // Readonly cell for missing units
                    unitCell.innerHTML = `
                        <div style="text-align: center; color: #9AA1AE;">
                            <div style="font-weight: 600;">-</div>
                        </div>
                    `;
                    unitCell.style.background = '#F2F4F8';
                }
                tr.appendChild(unitCell);
            }
            
            // Available stock - parse all values as numbers
            const stockCell = document.createElement('td');
            let totalStock = 0;
            mat.units.forEach(unit => {
                const stock = parseFloat(unit.available_stock) || 0;
                const convFactor = parseFloat(unit.conversion_factor) || 1;
                if (unit.is_base_unit == 1) {
                    totalStock = stock;
                } else {
                    totalStock += stock * convFactor;
                }
            });
            console.log('totalStock type:', typeof totalStock, 'value:', totalStock);
            stockCell.textContent = Number(totalStock).toFixed(2);
            tr.appendChild(stockCell);
            
            // Status - parse all values as numbers
            const statusCell = document.createElement('td');
            let totalRequired = 0;
            mat.units.forEach(unit => {
                const reqQty = parseFloat(unit.required_qty) || 0;
                const convFactor = parseFloat(unit.conversion_factor) || 1;
                if (unit.is_base_unit == 1) {
                    totalRequired = reqQty;
                } else {
                    totalRequired += reqQty * convFactor;
                }
            });
            
            const status = totalStock >= totalRequired ? 'available' : 'short';
            const statusText = totalStock >= totalRequired ? 'Available' : 'Short';
            statusCell.innerHTML = `<span class="badge ${status}">${statusText}</span>`;
            tr.appendChild(statusCell);

            tbody.appendChild(tr);
        });
        
        // Update table headers
        updateTableHeaders(maxUnits);
    }

    function updateTableHeaders(maxUnits) {
        const thead = document.querySelector('#materialsTable').closest('table').querySelector('thead tr');
        
        // Rebuild headers
        thead.innerHTML = '<th>Material</th>';
        for (let i = 0; i < maxUnits; i++) {
            thead.innerHTML += `<th>Unit ${i + 1}</th>`;
        }
        thead.innerHTML += '<th>Available Stock</th><th>Status</th>';
    }

    async function saveProductionOrder() {
        const orderNo = document.getElementById('orderNo').value;
        const productValue = document.getElementById('productId').value;
        const bomValue = document.getElementById('bomId').value;
        const branchId = document.getElementById('branchId').value;
        const machineId = document.getElementById('machineId').value;
        const orderQty = document.getElementById('orderQty').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;

        const selectedProduct = products.find(p => `${p.code} - ${p.name}` === productValue);
        const selectedBOM = boms.find(b => `${b.bom_code} - v${b.version}` === bomValue);

        if (!selectedProduct || !selectedBOM || !branchId || !orderQty) {
            alert('Please fill all required fields');
            return;
        }

        if (orderQty <= 0) {
            alert('Order quantity must be greater than 0');
            return;
        }

        if (materialRequirements.length === 0) {
            alert('Please load materials first');
            return;
        }

        // Flatten materials for saving
        const materials = [];
        materialRequirements.forEach(mat => {
            mat.units.forEach(unit => {
                materials.push({
                    material_id: mat.material_id,
                    required_qty: unit.required_qty,
                    uom_id: unit.uom_id
                });
            });
        });

        const payload = {
            order_no: orderNo,
            product_id: selectedProduct.id,
            bom_id: selectedBOM.id,
            bom_version: selectedBOM.version,
            branch_id: branchId,
            machine_id: machineId || null,
            order_qty: orderQty,
            start_date: startDate,
            end_date: endDate,
            materials: materials
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
            alert('Error saving production order');
        }
    }

    document.getElementById('productId').addEventListener('change', function() {
        const selectedProduct = products.find(p => `${p.code} - ${p.name}` === this.value);
        if (selectedProduct) {
            loadBOMs(selectedProduct.id);
        }
    });

    document.getElementById('branchId').addEventListener('change', function() {
        loadMachines(this.value);
    });

    document.getElementById('loadMaterialsBtn').addEventListener('click', loadMaterials);
    document.getElementById('saveBtn').addEventListener('click', saveProductionOrder);
    document.getElementById('cancelBtn').addEventListener('click', () => {
        if (confirm('Discard changes?')) location.reload();
    });

    async function init() {
        await generateOrderNo();
        await loadProducts();
        await loadBranches();
    }

    init();
})();
