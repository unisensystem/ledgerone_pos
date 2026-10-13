(function() {
    console.log('Script loaded, BASE_URL:', typeof BASE_URL !== 'undefined' ? BASE_URL : 'UNDEFINED');
    
    if (typeof BASE_URL === 'undefined') {
        console.error('BASE_URL is not defined!');
        return;
    }
    
    const API_URL = BASE_URL + '/server/api/manufacturing/wip_management/index.php';

    let materials = [];
    let branchId = null;
    let machineId = null;
    let productionOrderId = null;
    let wipProductId = null;

    async function loadProductionOrders() {
        console.log('Fetching from:', API_URL + '?action=production_orders');
        try {
            const res = await fetch(API_URL + '?action=production_orders');
            const text = await res.text();
            console.log('Response:', text);
            
            if (!res.ok) {
                alert('API Error: ' + res.status + ' - Please ensure the API file exists on the server');
                return;
            }
            
            if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) {
                alert('API returned HTML instead of JSON. The API file may not exist on the server at: ' + API_URL);
                return;
            }
            
            const data = JSON.parse(text);
        if (data.success) {
            const select = document.getElementById('productionOrderId');
            select.innerHTML = '<option value="">Select Production Order</option>';
            data.data.forEach(po => {
                const opt = document.createElement('option');
                opt.value = po.id;
                opt.textContent = `${po.order_no} - ${po.product_name} (${po.status})`;
                opt.setAttribute('data-branch-id', po.branch_id);
                select.appendChild(opt);
            });
        }
        } catch (error) {
            console.error('Error loading production orders:', error);
            alert('Failed to load production orders. Please check console for details.');
        }
        
        // Load next WIP number
        const wipRes = await fetch(API_URL + '?action=next_wip_number');
        const wipData = await wipRes.json();
        if (wipData.success) {
            document.getElementById('wipNumber').value = wipData.next_wip_number;
        }
    }

    async function loadProductionOrderDetails(poId) {
        try {
            const res = await fetch(API_URL + `?action=order_details&po_id=${poId}`);
            const data = await res.json();
            
            if (data.success && data.data) {
                const order = data.data;
                branchId = order.branch_id;
                machineId = order.machine_id;
                productionOrderId = poId;
                wipProductId = order.wip_product_id;
                
                document.getElementById('branchName').value = order.branch_name;
                document.getElementById('machineName').value = order.machine_name || '-';
                
                await loadMaterials(poId);
            }
        } catch (err) {
            alert('Error loading order details');
        }
    }

    async function loadMaterials(poId) {
        try {
            const res = await fetch(API_URL + `?action=materials&po_id=${poId}&branch_id=${branchId}`);
            const data = await res.json();
            
            if (data.success) {
                materials = data.data;
                renderMaterials(materials);
                calculateTotals();
            } else {
                console.error('API Error:', data);
                alert('Error: ' + (data.message || 'Failed to load materials'));
            }
        } catch (err) {
            console.error('Fetch Error:', err);
            alert('Error loading materials: ' + err.message);
        }
    }

    function renderMaterials(mats) {
        const tbody = document.getElementById('materialsTable');
        if (!mats || mats.length === 0) {
            tbody.innerHTML = '<tr><td colspan="20" style="text-align:center; padding:32px; color:#6B7280;">No materials found</td></tr>';
            return;
        }
        
        // Update table headers (simple single row)
        updateTableHeaders();
        
        // Group materials by product
        const materialsByProduct = {};
        
        mats.forEach(mat => {
            if (!materialsByProduct[mat.material_id]) {
                materialsByProduct[mat.material_id] = {
                    code: mat.material_code,
                    name: mat.material_name,
                    units: []
                };
            }
            materialsByProduct[mat.material_id].units.push(mat);
        });
        
        tbody.innerHTML = '';
        let globalIdx = 0;
        
        for (const productId in materialsByProduct) {
            const product = materialsByProduct[productId];
            
            // Each unit gets its own row
            product.units.forEach((mat, unitIdx) => {
                const row = document.createElement('tr');
                const remaining = parseFloat(mat.required_qty) - parseFloat(mat.issued_qty || 0);
                const available = parseFloat(mat.available_stock || 0);
                const status = available >= remaining ? 'Available' : available > 0 ? 'Partial' : 'Insufficient';
                const statusClass = status === 'Available' ? 'status-available' : status === 'Partial' ? 'status-partial' : 'status-insufficient';
                
                materials[globalIdx] = mat;
                
                // Show product name only in first row
                if (unitIdx === 0) {
                    row.innerHTML = `
                        <td rowspan="${product.units.length}" style="font-weight:600; vertical-align:middle; border-right:2px solid #E5E7EB;">${product.code}<br>${product.name}</td>
                        <td>${mat.required_qty}</td>
                        <td>${mat.issued_qty || 0}</td>
                        <td><span class="status-badge ${statusClass}">${available}</span></td>
                        <td><input type="number" class="issue-qty" data-idx="${globalIdx}" step="0.01" min="0" max="${Math.min(remaining, available)}" value="0" style="width:80px;"></td>
                        <td>${mat.uom_name}</td>
                        <td>${parseFloat(mat.unit_cost || 0).toFixed(2)}</td>
                        <td class="total-cost-${globalIdx}">0.00</td>
                    `;
                } else {
                    row.innerHTML = `
                        <td>${mat.required_qty}</td>
                        <td>${mat.issued_qty || 0}</td>
                        <td><span class="status-badge ${statusClass}">${available}</span></td>
                        <td><input type="number" class="issue-qty" data-idx="${globalIdx}" step="0.01" min="0" max="${Math.min(remaining, available)}" value="0" style="width:80px;"></td>
                        <td>${mat.uom_name}</td>
                        <td>${parseFloat(mat.unit_cost || 0).toFixed(2)}</td>
                        <td class="total-cost-${globalIdx}">0.00</td>
                    `;
                }
                
                tbody.appendChild(row);
                globalIdx++;
            });
        }

        document.querySelectorAll('.issue-qty').forEach(input => {
            input.addEventListener('input', function() {
                const idx = this.getAttribute('data-idx');
                calculateRowTotal(idx);
                calculateTotals();
            });
        });

        const toggle = document.getElementById('autocompleteToggle');
        if (toggle && toggle.checked) {
            applyAutoFill();
        }
    }
    
    function updateTableHeaders() {
        const table = document.querySelector('table');
        if (!table) return;
        
        let thead = table.querySelector('thead');
        if (!thead) return;
        
        thead.innerHTML = `
            <tr>
                <th style="min-width:200px;">Material</th>
                <th>Required Qty</th>
                <th>Issued Qty</th>
                <th>Available</th>
                <th>Issue Qty</th>
                <th>UOM</th>
                <th>Unit Cost</th>
                <th>Total Cost</th>
            </tr>
        `;
    }

    function calculateRowTotal(idx) {
        const input = document.querySelector(`.issue-qty[data-idx="${idx}"]`);
        const issueQty = parseFloat(input.value || 0);
        const unitCost = parseFloat(materials[idx].unit_cost || 0);
        const totalCost = issueQty * unitCost;
        
        document.querySelector(`.total-cost-${idx}`).textContent = totalCost.toFixed(2);
    }

    function calculateTotals() {
        let totalItems = 0;
        let totalCost = 0;
        
        document.querySelectorAll('.issue-qty').forEach(input => {
            const qty = parseFloat(input.value || 0);
            if (qty > 0) {
                totalItems++;
                const idx = input.getAttribute('data-idx');
                const unitCost = parseFloat(materials[idx].unit_cost || 0);
                totalCost += qty * unitCost;
            }
        });
        
        document.getElementById('totalItems').value = totalItems;
        document.getElementById('totalCost').value = totalCost.toFixed(2);
    }

    function applyAutoFill() {
        document.querySelectorAll('.issue-qty').forEach(input => {
            const maxVal = parseFloat(input.max || 0);
            input.value = maxVal > 0 ? maxVal : 0;
            const idx = input.getAttribute('data-idx');
            calculateRowTotal(idx);
        });
        calculateTotals();
    }

    function clearAutoFill() {
        document.querySelectorAll('.issue-qty').forEach(input => {
            input.value = 0;
            const idx = input.getAttribute('data-idx');
            calculateRowTotal(idx);
        });
        calculateTotals();
    }

    document.getElementById('autocompleteToggle').addEventListener('change', function() {
        if (this.checked) {
            applyAutoFill();
        } else {
            clearAutoFill();
        }
    });

    window.clearRow = function(idx) {
        const input = document.querySelector(`.issue-qty[data-idx="${idx}"]`);
        input.value = 0;
        calculateRowTotal(idx);
        calculateTotals();
    };

    async function issueMaterials() {
        const poId = document.getElementById('productionOrderId').value;
        const issueDate = document.getElementById('issueDate').value;
        
        if (!poId) {
            alert('Please select a production order');
            return;
        }
        
        if (!issueDate) {
            alert('Please select issue date');
            return;
        }
        
        const issuedMaterials = [];
        document.querySelectorAll('.issue-qty').forEach(input => {
            const qty = parseFloat(input.value || 0);
            if (qty > 0) {
                const idx = input.getAttribute('data-idx');
                const mat = materials[idx];
                issuedMaterials.push({
                    pom_id: mat.pom_id,
                    material_id: mat.material_id,
                    required_qty: mat.required_qty,
                    issued_qty: mat.issued_qty || 0,
                    available_qty: mat.available_stock,
                    issue_qty: qty,
                    unit_cost: mat.unit_cost,
                    uom_id: mat.uom_id
                });
            }
        });
        
        if (issuedMaterials.length === 0) {
            alert('Please enter issue quantities');
            return;
        }
        
        const payload = {
            po_id: poId,
            branch_id: branchId,
            machine_id: machineId,
            issue_date: issueDate,
            wip_product_id: wipProductId,
            materials: issuedMaterials
        };
        
        try {
            const res = await fetch(API_URL, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (data.success) {
                alert('Materials issued successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error issuing materials');
        }
    }

    document.getElementById('productionOrderId').addEventListener('change', function() {
        const poId = this.value;
        if (poId) {
            loadProductionOrderDetails(poId);
        } else {
            document.getElementById('branchName').value = '';
            document.getElementById('machineName').value = '';
            document.getElementById('totalItems').value = '';
            document.getElementById('totalCost').value = '';
            document.getElementById('materialsTable').innerHTML = '<tr><td colspan="9" style="text-align:center; padding:32px; color:#6B7280;">Select a production order to load materials</td></tr>';
        }
    });

    document.getElementById('issueBtn').addEventListener('click', issueMaterials);
    document.getElementById('cancelBtn').addEventListener('click', () => location.reload());

    const today = new Date().toISOString().split('T')[0];
    document.getElementById('issueDate').value = today;

    loadProductionOrders();
})();
