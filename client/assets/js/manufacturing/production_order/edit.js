(function() {
    const API_URL = '../../../../server/api/manufacturing/production_order/list.php';
    const UPDATE_URL = '../../../../server/api/manufacturing/production_order/update.php';
    const INDEX_API = '../../../../server/api/manufacturing/production_order/index.php';

    let products = [];
    let boms = [];
    let calculatedMaterials = [];

    async function loadProducts() {
        const res = await fetch(INDEX_API + '?action=products');
        const data = await res.json();
        if (data.success) {
            products = data.data;
            const datalist = document.getElementById('productList');
            datalist.innerHTML = '';
            products.forEach(p => {
                const opt = document.createElement('option');
                opt.value = `${p.code} - ${p.name}`;
                opt.setAttribute('data-id', p.id);
                datalist.appendChild(opt);
            });
        }
    }

    async function loadBOMs(productId) {
        if (!productId) return;
        const res = await fetch(INDEX_API + `?action=boms&product_id=${productId}`);
        const data = await res.json();
        if (data.success) {
            boms = data.data;
            const datalist = document.getElementById('bomList');
            datalist.innerHTML = '';
            boms.forEach(b => {
                const opt = document.createElement('option');
                opt.value = `${b.bom_code} v${b.version}`;
                opt.setAttribute('data-id', b.id);
                datalist.appendChild(opt);
            });
        }
    }

    async function loadBranches() {
        const res = await fetch(INDEX_API + '?action=branches');
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
        const res = await fetch(INDEX_API + `?action=machines&branch_id=${branchId}`);
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

    async function loadOrder() {
        try {
            const res = await fetch(`${API_URL}?action=view&id=${ORDER_ID}`);
            const data = await res.json();
            
            if (data.success && data.data) {
                const order = data.data;
                document.getElementById('orderNo').value = order.order_no;
                document.getElementById('productId').value = `${order.product_code} - ${order.product_name}`;
                document.getElementById('productId').setAttribute('data-selected-id', order.product_id);
                
                await loadBOMs(order.product_id);
                document.getElementById('bomId').value = `${order.bom_code} v${order.bom_version}`;
                document.getElementById('bomId').setAttribute('data-selected-id', order.bom_id);
                
                document.getElementById('branchId').value = order.branch_id;
                document.getElementById('orderQty').value = order.order_qty;
                document.getElementById('startDate').value = order.start_date || '';
                document.getElementById('endDate').value = order.end_date || '';
                
                await loadMachines(order.branch_id);
                document.getElementById('machineId').value = order.machine_id || '';
                
                loadMaterialsFromOrder();
            }
        } catch (err) {
            alert('Error loading order');
        }
    }

    async function loadMaterialsFromOrder() {
        const bomId = getSelectedBomId();
        const orderQty = document.getElementById('orderQty').value;
        const branchId = document.getElementById('branchId').value;
        
        if (!bomId || !orderQty || !branchId) {
            document.getElementById('materialsTable').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:32px; color:#6B7280;">Please fill BOM, Order Quantity, and Branch to calculate materials</td></tr>';
            return;
        }
        
        try {
            const res = await fetch(`${INDEX_API}?action=calculate_materials&bom_id=${bomId}&order_qty=${orderQty}&branch_id=${branchId}`);
            const data = await res.json();
            
            if (data.success) {
                calculatedMaterials = data.data;
                displayMaterials(data.data);
            }
        } catch (err) {
            alert('Error loading materials');
        }
    }

    function displayMaterials(materials) {
        const tbody = document.getElementById('materialsTable');
        if (!materials || materials.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:32px; color:#6B7280;">No materials found</td></tr>';
            return;
        }
        
        // Find max units
        let maxUnits = 0;
        materials.forEach(mat => {
            if (mat.units.length > maxUnits) {
                maxUnits = mat.units.length;
            }
        });
        
        tbody.innerHTML = '';
        materials.forEach(mat => {
            const tr = document.createElement('tr');
            
            // Material name
            const nameCell = document.createElement('td');
            nameCell.innerHTML = `<strong>${mat.material_code} - ${mat.material_name}</strong>`;
            tr.appendChild(nameCell);
            
            // Units
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
                    unitCell.innerHTML = `
                        <div style="text-align: center; color: #9AA1AE;">
                            <div style="font-weight: 600;">-</div>
                        </div>
                    `;
                    unitCell.style.background = '#F2F4F8';
                }
                tr.appendChild(unitCell);
            }
            
            // Available stock
            const stockCell = document.createElement('td');
            let totalStock = 0;
            mat.units.forEach(unit => {
                if (unit.is_base_unit == 1) {
                    totalStock = unit.available_stock;
                } else {
                    totalStock += unit.available_stock * (unit.conversion_factor || 1);
                }
            });
            stockCell.textContent = totalStock.toFixed(2);
            tr.appendChild(stockCell);
            
            // Status
            const statusCell = document.createElement('td');
            let totalRequired = 0;
            mat.units.forEach(unit => {
                if (unit.is_base_unit == 1) {
                    totalRequired = unit.required_qty;
                } else {
                    totalRequired += unit.required_qty * (unit.conversion_factor || 1);
                }
            });
            
            const status = totalStock >= totalRequired ? 'available' : 'short';
            const statusText = totalStock >= totalRequired ? 'Available' : 'Short';
            statusCell.innerHTML = `<span class="badge ${status}">${statusText}</span>`;
            tr.appendChild(statusCell);
            
            tbody.appendChild(tr);
        });
        
        updateTableHeaders(maxUnits);
    }

    function updateTableHeaders(maxUnits) {
        const thead = document.querySelector('#materialsTable').closest('table').querySelector('thead tr');
        
        thead.innerHTML = '<th>Material</th>';
        for (let i = 0; i < maxUnits; i++) {
            thead.innerHTML += `<th>Unit ${i + 1}</th>`;
        }
        thead.innerHTML += '<th>Available Stock</th><th>Status</th>';
    }

    function getSelectedProductId() {
        const input = document.getElementById('productId');
        const value = input.value;
        const product = products.find(p => `${p.code} - ${p.name}` === value);
        return product ? product.id : input.getAttribute('data-selected-id');
    }

    function getSelectedBomId() {
        const input = document.getElementById('bomId');
        const value = input.value;
        const bom = boms.find(b => `${b.bom_code} v${b.version}` === value);
        return bom ? bom.id : input.getAttribute('data-selected-id');
    }

    async function updateOrder() {
        const productId = getSelectedProductId();
        const bomId = getSelectedBomId();
        const branchId = document.getElementById('branchId').value;
        const machineId = document.getElementById('machineId').value;
        const orderQty = document.getElementById('orderQty').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;

        if (!productId || !bomId || !branchId || !orderQty) {
            alert('Please fill all required fields');
            return;
        }

        if (orderQty <= 0) {
            alert('Order quantity must be greater than 0');
            return;
        }

        if (calculatedMaterials.length === 0) {
            alert('Please click "Recalculate Materials" before updating');
            return;
        }

        // Flatten materials
        const materials = [];
        calculatedMaterials.forEach(mat => {
            mat.units.forEach(unit => {
                materials.push({
                    material_id: mat.material_id,
                    required_qty: unit.required_qty,
                    uom_id: unit.uom_id
                });
            });
        });

        const payload = {
            id: ORDER_ID,
            product_id: productId,
            bom_id: bomId,
            branch_id: branchId,
            machine_id: machineId || null,
            order_qty: orderQty,
            start_date: startDate,
            end_date: endDate,
            materials: materials
        };

        try {
            const res = await fetch(UPDATE_URL, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (data.success) {
                alert('Production Order updated successfully!');
                window.location.href = 'list.php';
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error updating order');
        }
    }

    document.getElementById('productId').addEventListener('input', function() {
        const productId = getSelectedProductId();
        if (productId) {
            loadBOMs(productId);
            document.getElementById('bomId').value = '';
            calculatedMaterials = [];
            document.getElementById('materialsTable').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:32px; color:#6B7280;">Click "Recalculate Materials" after selecting BOM</td></tr>';
        }
    });

    document.getElementById('branchId').addEventListener('change', function() {
        loadMachines(this.value);
        if (calculatedMaterials.length > 0) {
            loadMaterialsFromOrder();
        }
    });

    document.getElementById('loadMaterialsBtn').addEventListener('click', loadMaterialsFromOrder);
    document.getElementById('updateBtn').addEventListener('click', updateOrder);

    async function init() {
        await loadProducts();
        await loadBranches();
        await loadOrder();
    }

    init();
})();
