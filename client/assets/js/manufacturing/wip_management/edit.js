(function() {
    const API_URL = BASE_URL + '/server/api/manufacturing/wip_management/index.php';

    let materials = [];
    let branchId = null;
    let machineId = null;
    let productionOrderId = null;
    let wipProductId = null;
    let allProducts = [];

    async function loadWIPDetails() {
        try {
            const res = await fetch(API_URL + `?action=wip_details&id=${WIP_ID}`);
            const data = await res.json();
            
            if (data.success && data.data) {
                const wip = data.data;
                branchId = wip.branch_id;
                machineId = wip.machine_id;
                productionOrderId = wip.production_order_id;
                wipProductId = wip.wip_product_id;
                
                document.getElementById('wipNumber').value = wip.wip_number;
                document.getElementById('productionOrder').value = wip.order_no;
                document.getElementById('branchName').value = wip.branch_name;
                document.getElementById('machineName').value = wip.machine_name || '-';
                document.getElementById('issueDate').value = wip.issue_date;
                document.getElementById('totalItems').value = wip.total_items;
                document.getElementById('totalCost').value = parseFloat(wip.total_cost).toFixed(2);
                
                await loadWIPItems();
                await loadAllProducts();
            }
        } catch (err) {
            alert('Error loading WIP details');
        }
    }

    async function loadWIPItems() {
        try {
            const res = await fetch(API_URL + `?action=wip_items&id=${WIP_ID}`);
            const data = await res.json();
            
            if (data.success) {
                materials = data.data;
                renderItems(materials);
            }
        } catch (err) {
            alert('Error loading WIP items');
        }
    }

    async function loadAllProducts() {
        const res = await fetch(API_URL + '?action=all_materials');
        const data = await res.json();
        if (data.success) {
            allProducts = data.data;
        }
    }

    function renderItems(items) {
        const tbody = document.getElementById('materialsTable');
        
        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="20" style="text-align:center; padding:32px; color:#6B7280;">No items found</td></tr>';
            return;
        }
        
        // Update table headers
        updateTableHeaders();
        
        // Group materials by product
        const materialsByProduct = {};
        
        items.forEach(item => {
            if (!materialsByProduct[item.material_id]) {
                materialsByProduct[item.material_id] = {
                    code: item.material_code,
                    name: item.material_name,
                    units: []
                };
            }
            materialsByProduct[item.material_id].units.push(item);
        });
        
        tbody.innerHTML = '';
        let globalIdx = 0;
        
        for (const productId in materialsByProduct) {
            const product = materialsByProduct[productId];
            
            // Each unit gets its own row
            product.units.forEach((item, unitIdx) => {
                const row = document.createElement('tr');
                
                materials[globalIdx] = item;
                
                if (VIEW_MODE) {
                    // Show product name only in first row
                    if (unitIdx === 0) {
                        row.innerHTML = `
                            <td rowspan="${product.units.length}" style="font-weight:600; vertical-align:middle; border-right:2px solid #E5E7EB;">${product.code}<br>${product.name}</td>
                            <td>${item.required_qty}</td>
                            <td>${item.issued_qty}</td>
                            <td>${item.available_qty}</td>
                            <td>${item.issue_qty}</td>
                            <td>${item.uom_name}</td>
                            <td>${parseFloat(item.unit_cost).toFixed(2)}</td>
                            <td>${parseFloat(item.total_cost).toFixed(2)}</td>
                            <td>-</td>
                        `;
                    } else {
                        row.innerHTML = `
                            <td>${item.required_qty}</td>
                            <td>${item.issued_qty}</td>
                            <td>${item.available_qty}</td>
                            <td>${item.issue_qty}</td>
                            <td>${item.uom_name}</td>
                            <td>${parseFloat(item.unit_cost).toFixed(2)}</td>
                            <td>${parseFloat(item.total_cost).toFixed(2)}</td>
                            <td>-</td>
                        `;
                    }
                } else {
                    // Show product name only in first row
                    if (unitIdx === 0) {
                        row.innerHTML = `
                            <td rowspan="${product.units.length}" style="font-weight:600; vertical-align:middle; border-right:2px solid #E5E7EB;">${product.code}<br>${product.name}</td>
                            <td>${item.required_qty}</td>
                            <td>${item.issued_qty}</td>
                            <td>${item.available_qty}</td>
                            <td><input type="number" class="issue-qty" data-idx="${globalIdx}" step="0.01" min="0" value="${item.issue_qty}" style="width:80px;"></td>
                            <td>${item.uom_name}</td>
                            <td>${parseFloat(item.unit_cost).toFixed(2)}</td>
                            <td class="total-cost-${globalIdx}">${parseFloat(item.total_cost).toFixed(2)}</td>
                            <td><button class="btn btn-danger" onclick="deleteItem(${globalIdx})"><i class="las la-trash"></i></button></td>
                        `;
                    } else {
                        row.innerHTML = `
                            <td>${item.required_qty}</td>
                            <td>${item.issued_qty}</td>
                            <td>${item.available_qty}</td>
                            <td><input type="number" class="issue-qty" data-idx="${globalIdx}" step="0.01" min="0" value="${item.issue_qty}" style="width:80px;"></td>
                            <td>${item.uom_name}</td>
                            <td>${parseFloat(item.unit_cost).toFixed(2)}</td>
                            <td class="total-cost-${globalIdx}">${parseFloat(item.total_cost).toFixed(2)}</td>
                            <td><button class="btn btn-danger" onclick="deleteItem(${globalIdx})"><i class="las la-trash"></i></button></td>
                        `;
                    }
                }
                
                tbody.appendChild(row);
                globalIdx++;
            });
        }

        if (!VIEW_MODE) {
            document.querySelectorAll('.issue-qty').forEach(input => {
                input.addEventListener('input', function() {
                    const idx = this.getAttribute('data-idx');
                    calculateRowTotal(idx);
                    calculateTotals();
                });
            });
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
                <th>Actions</th>
            </tr>
        `;
    }

    function calculateRowTotal(idx) {
        const input = document.querySelector(`.issue-qty[data-idx="${idx}"]`);
        const issueQty = parseFloat(input.value || 0);
        const unitCost = parseFloat(materials[idx].unit_cost || 0);
        const totalCost = issueQty * unitCost;
        
        materials[idx].issue_qty = issueQty;
        materials[idx].total_cost = totalCost;
        document.querySelector(`.total-cost-${idx}`).textContent = totalCost.toFixed(2);
    }

    function calculateTotals() {
        let totalItems = 0;
        let totalCost = 0;
        
        materials.forEach(mat => {
            if (parseFloat(mat.issue_qty) > 0) {
                totalItems++;
                totalCost += parseFloat(mat.total_cost || 0);
            }
        });
        
        document.getElementById('totalItems').value = totalItems;
        document.getElementById('totalCost').value = totalCost.toFixed(2);
    }

    window.deleteItem = async function(idx) {
        if (confirm('Delete this item?')) {
            const item = materials[idx];
            
            try {
                const res = await fetch(API_URL + '?action=delete_wip_item', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        item_id: item.id,
                        wip_id: WIP_ID
                    })
                });
                const data = await res.json();
                
                if (data.success) {
                    alert('Item deleted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (err) {
                alert('Error deleting item');
            }
        }
    };

    async function updateWIP() {
        const issueDate = document.getElementById('issueDate').value;
        
        if (!issueDate) {
            alert('Please select issue date');
            return;
        }
        
        const payload = {
            wip_id: WIP_ID,
            issue_date: issueDate,
            materials: materials.map(mat => ({
                id: mat.id,
                issue_qty: mat.issue_qty,
                total_cost: mat.total_cost
            }))
        };
        
        try {
            const res = await fetch(API_URL + '?action=update_wip', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (data.success) {
                alert('WIP updated successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error updating WIP');
        }
    }

    document.getElementById('updateBtn')?.addEventListener('click', updateWIP);

    loadWIPDetails();
})();
