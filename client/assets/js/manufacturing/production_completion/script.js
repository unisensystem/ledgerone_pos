(function() {
    const API_URL = BASE_URL + '/server/api/manufacturing/production_completion/index.php';

    let products = [];
    let branchId = null;
    let productionOrderId = null;
    let isEditMode = EDIT_ID !== null;

    async function loadNextCompletionNo() {
        if (isEditMode) return; // Skip in edit mode
        const res = await fetch(API_URL + '?action=next_completion_no');
        const data = await res.json();
        if (data.success) {
            document.getElementById('completionNo').value = data.completion_no;
        }
    }

    async function loadProductionOrders(editPoId = null) {
        let url = API_URL + '?action=production_orders';
        if (editPoId) {
            url += `&edit_po_id=${editPoId}`;
        }
        
        const res = await fetch(url);
        const data = await res.json();
        if (data.success) {
            const select = document.getElementById('productionOrderId');
            select.innerHTML = '<option value="">Select Production Order</option>';
            data.data.forEach(po => {
                const opt = document.createElement('option');
                opt.value = po.id;
                opt.textContent = `${po.order_no} - ${po.product_name} (${po.status})`;
                select.appendChild(opt);
            });
            
            if (isEditMode) {
                select.disabled = true; // Disable in edit mode
            }
        }
    }

    async function loadProductionOrderDetails(poId) {
        try {
            const res = await fetch(API_URL + `?action=order_details&po_id=${poId}`);
            const data = await res.json();
            
            if (data.success && data.data) {
                const order = data.data;
                branchId = order.branch_id;
                productionOrderId = poId;
                
                document.getElementById('branchName').value = order.branch_name;
                document.getElementById('machineName').value = order.machine_name || '-';
                
                await loadProducts(poId);
            }
        } catch (err) {
            alert('Error loading order details');
        }
    }

    async function loadExistingCompletion() {
        try {
            const res = await fetch(BASE_URL + `/server/api/manufacturing/production_completion/list.php?action=view&id=${EDIT_ID}`);
            const data = await res.json();
            
            if (data.success && data.data) {
                const completion = data.data;
                
                // Store the production order ID first
                productionOrderId = completion.production_order_id;
                branchId = completion.branch_id;
                
                // Reload production orders with the edit parameter
                await loadProductionOrders(completion.production_order_id);
                
                // Now populate the form
                document.getElementById('completionNo').value = completion.completion_no;
                document.getElementById('productionOrderId').value = completion.production_order_id;
                document.getElementById('branchName').value = completion.branch_name;
                document.getElementById('machineName').value = completion.machine_name || '-';
                document.getElementById('completeDate').value = completion.complete_date;
                
                await loadProducts(completion.production_order_id, completion.products);
            }
        } catch (err) {
            alert('Error loading completion data');
        }
    }

    async function loadProducts(poId, existingProducts = null) {
        try {
            const res = await fetch(API_URL + `?action=products&po_id=${poId}`);
            const text = await res.text();
            console.log('Raw Response:', text);
            
            const data = JSON.parse(text);
            console.log('Parsed Data:', data);
            
            if (data.success) {
                products = data.data;
                
                // If editing, merge existing completed quantities
                if (existingProducts) {
                    products.forEach(prod => {
                        const existing = existingProducts.find(ep => 
                            ep.product_id === prod.product_id && ep.uom_id === prod.uom_id
                        );
                        if (existing) {
                            prod.existing_completed_qty = parseFloat(existing.completed_qty);
                        }
                    });
                }
                
                renderProducts(products);
                calculateTotals();
            } else {
                alert('Error: ' + (data.message || 'Failed to load products'));
            }
        } catch (err) {
            console.error('Error:', err);
            alert('Error loading products: ' + err.message);
        }
    }

    function renderProducts(prods) {
        const tbody = document.getElementById('productsTable');
        if (!prods || prods.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:32px; color:#6B7280;">No products found</td></tr>';
            return;
        }
        
        // Update table headers
        updateTableHeaders();
        
        // Group products by product_id
        const productsByProduct = {};
        
        prods.forEach(prod => {
            if (!productsByProduct[prod.product_id]) {
                productsByProduct[prod.product_id] = {
                    code: prod.product_code,
                    name: prod.product_name,
                    order_qty: prod.order_qty,
                    units: []
                };
            }
            productsByProduct[prod.product_id].units.push(prod);
        });
        
        tbody.innerHTML = '';
        let globalIdx = 0;
        
        for (const productId in productsByProduct) {
            const product = productsByProduct[productId];
            
            // Each unit gets its own row
            product.units.forEach((prod, unitIdx) => {
                const remaining = parseFloat(prod.order_qty) - parseFloat(prod.completed_qty || 0);
                const defaultQty = isEditMode && prod.existing_completed_qty ? prod.existing_completed_qty : remaining;
                
                products[globalIdx] = prod;
                
                const row = document.createElement('tr');
                
                // Show product name only in first row
                if (unitIdx === 0) {
                    row.innerHTML = `
                        <td rowspan="${product.units.length}" style="font-weight:600; vertical-align:middle; border-right:2px solid #E5E7EB;">${product.code}<br>${product.name}</td>
                        <td>${prod.order_qty}</td>
                        <td>${remaining.toFixed(2)}</td>
                        <td><input type="number" class="complete-qty" data-idx="${globalIdx}" step="0.01" min="0" max="${remaining + (prod.existing_completed_qty || 0)}" value="${defaultQty}" required style="width:80px;"></td>
                        <td>${prod.uom_name || 'N/A'}</td>
                        <td class="unit-cost-${globalIdx}">${parseFloat(prod.unit_cost).toFixed(2)}</td>
                        <td class="total-cost-${globalIdx}">${(defaultQty * prod.unit_cost).toFixed(2)}</td>
                    `;
                } else {
                    row.innerHTML = `
                        <td>${prod.order_qty}</td>
                        <td>${remaining.toFixed(2)}</td>
                        <td><input type="number" class="complete-qty" data-idx="${globalIdx}" step="0.01" min="0" max="${remaining + (prod.existing_completed_qty || 0)}" value="${defaultQty}" required style="width:80px;"></td>
                        <td>${prod.uom_name || 'N/A'}</td>
                        <td class="unit-cost-${globalIdx}">${parseFloat(prod.unit_cost).toFixed(2)}</td>
                        <td class="total-cost-${globalIdx}">${(defaultQty * prod.unit_cost).toFixed(2)}</td>
                    `;
                }
                
                tbody.appendChild(row);
                globalIdx++;
            });
        }

        document.querySelectorAll('.complete-qty').forEach(input => {
            input.addEventListener('input', function() {
                const idx = this.getAttribute('data-idx');
                calculateRowTotal(idx);
                calculateTotals();
            });
        });
    }
    
    function updateTableHeaders() {
        const table = document.querySelector('table');
        if (!table) return;
        
        let thead = table.querySelector('thead');
        if (!thead) return;
        
        thead.innerHTML = `
            <tr>
                <th style="min-width:200px;">Product</th>
                <th>Planned Qty</th>
                <th>Remaining Qty</th>
                <th>Completed Qty</th>
                <th>UOM</th>
                <th>Unit Cost</th>
                <th>Total Cost</th>
            </tr>
        `;
    }

    function calculateRowTotal(idx) {
        const input = document.querySelector(`.complete-qty[data-idx="${idx}"]`);
        const completeQty = parseFloat(input.value || 0);
        const unitCost = parseFloat(products[idx].unit_cost || 0);
        const totalCost = completeQty * unitCost;
        
        document.querySelector(`.total-cost-${idx}`).textContent = totalCost.toFixed(2);
    }

    function calculateTotals() {
        let totalProducts = 0;
        let totalQuantity = 0;
        let totalCost = 0;
        
        document.querySelectorAll('.complete-qty').forEach(input => {
            const qty = parseFloat(input.value || 0);
            if (qty > 0) {
                totalProducts++;
                totalQuantity += qty;
                const idx = input.getAttribute('data-idx');
                const unitCost = parseFloat(products[idx].unit_cost || 0);
                totalCost += qty * unitCost;
            }
        });
        
        document.getElementById('totalProducts').value = totalProducts;
        document.getElementById('totalQuantity').value = totalQuantity.toFixed(2);
        document.getElementById('totalCost').value = totalCost.toFixed(2);
    }

    async function completeProduction() {
        const poId = document.getElementById('productionOrderId').value;
        const completeDate = document.getElementById('completeDate').value;
        
        if (!poId) {
            alert('Please select a production order');
            return;
        }
        
        if (!completeDate) {
            alert('Please select complete date');
            return;
        }
        
        const completedProducts = [];
        document.querySelectorAll('.complete-qty').forEach(input => {
            const qty = parseFloat(input.value || 0);
            if (qty > 0) {
                const idx = input.getAttribute('data-idx');
                const prod = products[idx];
                completedProducts.push({
                    product_id: prod.product_id,
                    completed_qty: qty,
                    unit_cost: prod.unit_cost,
                    uom_id: prod.uom_id
                });
            }
        });
        
        if (completedProducts.length === 0) {
            alert('Please enter completed quantities');
            return;
        }
        
        const payload = {
            po_id: poId,
            branch_id: branchId,
            complete_date: completeDate,
            products: completedProducts
        };
        
        if (isEditMode) {
            payload.completion_id = EDIT_ID;
        }
        
        try {
            const method = isEditMode ? 'PUT' : 'POST';
            const res = await fetch(API_URL, {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (data.success) {
                alert(isEditMode ? 'Production completion updated successfully!' : 'Production completed successfully!');
                window.location.href = 'list.php';
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error completing production');
        }
    }

    document.getElementById('productionOrderId').addEventListener('change', function() {
        const poId = this.value;
        if (poId) {
            loadProductionOrderDetails(poId);
        } else {
            document.getElementById('branchName').value = '';
            document.getElementById('machineName').value = '';
            document.getElementById('totalProducts').value = '';
            document.getElementById('totalQuantity').value = '';
            document.getElementById('totalCost').value = '';
            document.getElementById('productsTable').innerHTML = '<tr><td colspan="7" style="text-align:center; padding:32px; color:#6B7280;">Select a production order to load products</td></tr>';
        }
    });

    document.getElementById('completeBtn').addEventListener('click', completeProduction);
    document.getElementById('cancelBtn').addEventListener('click', () => window.location.href = 'list.php');

    async function init() {
        const today = new Date().toISOString().split('T')[0];
        if (!isEditMode) {
            document.getElementById('completeDate').value = today;
        }

        await loadNextCompletionNo();
        await loadProductionOrders();
        
        if (isEditMode) {
            await loadExistingCompletion();
        }
    }

    init();
})();
