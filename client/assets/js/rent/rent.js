(function() {
    const API_URL = '../../../server/api/rent/rent.php';
    let customers = [];
    let products = [];
    let editMode = false;
    let viewMode = false;
    let rentId = null;

    async function init() {
        const urlParams = new URLSearchParams(window.location.search);
        rentId = urlParams.get('id');
        viewMode = urlParams.get('mode') === 'view';
        editMode = !!rentId && !viewMode;
        
        await loadRentNo();
        await loadBranches();
        await loadCustomers();
        await loadProducts();
        
        if (rentId) {
            await loadRentData(rentId);
            if (viewMode) {
                document.querySelector('.card-header h2').textContent = 'View Rent';
                document.querySelectorAll('input, select, textarea').forEach(el => el.readOnly = true);
                document.querySelectorAll('select').forEach(el => el.disabled = true);
                document.querySelectorAll('button').forEach(el => {
                    if (!el.textContent.includes('View List')) el.style.display = 'none';
                });
            } else {
                document.querySelector('.card-header h2').textContent = 'Edit Rent';
                document.querySelector('.btn-primary').textContent = 'Update Rent';
            }
        } else {
            document.getElementById('date').valueAsDate = new Date();
            document.getElementById('issueDate').valueAsDate = new Date();
            addItemRow();
        }
        
        document.getElementById('issueDate').addEventListener('change', calculateDays);
        document.getElementById('expectedReturnDate').addEventListener('change', calculateDays);
    }

    async function loadRentNo() {
        const res = await fetch(`${API_URL}?action=next_code`);
        const data = await res.json();
        if (data.success) {
            document.getElementById('rentNo').value = data.code;
        }
    }

    async function loadBranches() {
        const res = await fetch(`${API_URL}?action=branches`);
        const data = await res.json();
        if (data.success) {
            const select = document.getElementById('branch');
            data.data.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id;
                opt.textContent = `${b.branch_code} - ${b.branch_name}`;
                select.appendChild(opt);
            });
        }
    }

    async function loadCustomers() {
        try {
            const res = await fetch(`${API_URL}?action=customers`);
            const text = await res.text();
            console.log('Customers response:', text);
            const data = JSON.parse(text);
            if (data.success) {
                customers = data.data;
                const datalist = document.getElementById('customerList');
                customers.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = `${c.customer_code} - ${c.customer_name}`;
                    opt.dataset.id = c.id;
                    opt.dataset.phone = c.primary_phone || '';
                    opt.dataset.address = c.address || '';
                    datalist.appendChild(opt);
                });
            }

            document.getElementById('customer').addEventListener('change', function() {
                const selected = customers.find(c => `${c.customer_code} - ${c.customer_name}` === this.value);
                if (selected) {
                    document.getElementById('customerPhone').value = selected.primary_phone || '';
                    document.getElementById('customerAddress').value = selected.address || '';
                }
            });
        } catch (err) {
            console.error('Error loading customers:', err);
        }
    }

    async function loadProducts() {
        const res = await fetch(`${API_URL}?action=products`);
        const data = await res.json();
        if (data.success) {
            products = data.data;
        }
    }

    async function loadRentData(id) {
        const res = await fetch(`${API_URL}?action=view&id=${id}`);
        const data = await res.json();
        if (data.success && data.data) {
            const rent = data.data;
            document.getElementById('rentNo').value = rent.rent_no;
            document.getElementById('branch').value = rent.branch_id;
            document.getElementById('date').value = rent.date;
            document.getElementById('issueDate').value = rent.issue_date;
            document.getElementById('expectedReturnDate').value = rent.expected_return_date;
            document.getElementById('customer').value = `${rent.customer_name}`;
            document.getElementById('customerPhone').value = rent.customer_phone || '';
            document.getElementById('customerAddress').value = rent.customer_address || '';
            document.getElementById('nicNo').value = rent.nic_no || '';
            document.getElementById('refName').value = rent.reference_name || '';
            document.getElementById('refPhone').value = rent.reference_phone || '';
            document.getElementById('refNic').value = rent.reference_nic || '';
            document.getElementById('rentType').value = rent.rent_type;
            document.getElementById('rentRate').value = rent.rent_rate;
            document.getElementById('totalDays').value = rent.total_days;
            document.getElementById('totalRentAmount').value = rent.total_rent_amount;
            document.getElementById('remarks').value = rent.remarks || '';
            
            if (rent.nic_picture) {
                const img = document.createElement('div');
                img.innerHTML = `<a href="../../../uploads/rent/nic/${rent.nic_picture}" target="_blank"><img src="../../../uploads/rent/nic/${rent.nic_picture}" style="width:100px;height:60px;object-fit:cover;border:1px solid #ddd;border-radius:4px;cursor:pointer;"></a>`;
                document.getElementById('nicPicture').parentElement.appendChild(img);
            }
            
            if (rent.reference_nic_picture) {
                const img = document.createElement('div');
                img.innerHTML = `<a href="../../../uploads/rent/nic/${rent.reference_nic_picture}" target="_blank"><img src="../../../uploads/rent/nic/${rent.reference_nic_picture}" style="width:100px;height:60px;object-fit:cover;border:1px solid #ddd;border-radius:4px;cursor:pointer;"></a>`;
                document.getElementById('refNicPicture').parentElement.appendChild(img);
            }
            
            document.getElementById('itemList').innerHTML = '';
            rent.items.forEach(item => {
                addItemRow();
                const row = document.getElementById('itemList').lastElementChild;
                row.querySelector('input[placeholder="Description"]').value = item.description || '';
                row.querySelector('.product-input').value = `${item.item_code} - ${item.item_name}`;
                
                const unitInput = row.querySelector('input[placeholder="Unit"]');
                const product = products.find(p => p.id == item.product_id);
                if (product) {
                    unitInput.value = product.unit_name || '';
                } else {
                    unitInput.value = '';
                }
                
                row.querySelector('.qty-input').value = item.quantity;
                row.querySelector('.rate-input').value = item.rate;
                row.dataset.productId = item.product_id;
                row.dataset.unitId = item.unit_id;
            });
        }
    }

    window.addItemRow = function() {
        const row = document.createElement('div');
        row.className = 'item-row';
        row.innerHTML = `
            <div class="field-group">
                <input type="text" class="form-control" placeholder="Description">
            </div>
            <div class="field-group">
                <input type="text" class="form-control product-input" list="productList" placeholder="Select product" required>
                <datalist id="productList">
                    ${products.map(p => `<option value="${p.code} - ${p.name}" data-id="${p.id}" data-unit="${p.default_unit_id}" data-unit-name="${p.unit_name || ''}">`).join('')}
                </datalist>
            </div>
            <div class="field-group">
                <input type="text" class="form-control" placeholder="Unit" readonly>
            </div>
            <div class="field-group">
                <input type="number" class="form-control qty-input" placeholder="Quantity" step="0.01" required>
            </div>
            <div class="field-group">
                <input type="number" class="form-control rate-input" placeholder="Rate" step="0.01" required>
            </div>
            <button type="button" class="btn-icon" onclick="this.parentElement.remove(); calculateTotalRate()"><i class="las la-minus"></i></button>
        `;
        
        const productInput = row.querySelector('.product-input');
        productInput.addEventListener('change', function() {
            const selected = products.find(p => `${p.code} - ${p.name}` === this.value);
            if (selected) {
                row.querySelector('input[placeholder="Unit"]').value = selected.unit_name || '';
                row.dataset.productId = selected.id;
                row.dataset.unitId = selected.default_unit_id;
            }
        });
        
        row.querySelector('.qty-input').addEventListener('input', calculateTotalRate);
        row.querySelector('.rate-input').addEventListener('input', calculateTotalRate);
        
        document.getElementById('itemList').appendChild(row);
    };

    function calculateDays() {
        const issue = new Date(document.getElementById('issueDate').value);
        const expected = new Date(document.getElementById('expectedReturnDate').value);
        if (issue && expected && expected > issue) {
            const days = Math.ceil((expected - issue) / (1000 * 60 * 60 * 24));
            document.getElementById('totalDays').value = days;
            calculateRent();
        }
    }

    window.calculateTotalRate = function() {
        let total = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const rate = parseFloat(row.querySelector('.rate-input').value) || 0;
            total += qty * rate;
        });
        document.getElementById('rentRate').value = total.toFixed(2);
        calculateRent();
    };

    window.calculateRent = function() {
        const rate = parseFloat(document.getElementById('rentRate').value) || 0;
        const days = parseInt(document.getElementById('totalDays').value) || 0;
        const type = document.getElementById('rentType').value;
        
        let total = 0;
        if (type === 'Daily') {
            total = rate * days;
        } else if (type === 'Weekly') {
            total = rate * Math.ceil(days / 7);
        } else if (type === 'Monthly') {
            total = rate * Math.ceil(days / 30);
        }
        
        document.getElementById('totalRentAmount').value = total.toFixed(2);
    };

    window.saveRent = async function() {
        const customerValue = document.getElementById('customer').value;
        const selectedCustomer = customers.find(c => `${c.customer_code} - ${c.customer_name}` === customerValue);
        
        if (!selectedCustomer && !editMode) {
            alert('Please select a valid customer');
            return;
        }

        const items = [];
        const rows = document.querySelectorAll('.item-row');
        
        for (const row of rows) {
            const inputs = row.querySelectorAll('input');
            if (!row.dataset.productId || !inputs[3].value) {
                alert('Please fill all item fields');
                return;
            }
            
            items.push({
                product_id: parseInt(row.dataset.productId),
                item_code: inputs[1].value.split(' - ')[0],
                item_name: inputs[1].value.split(' - ')[1],
                description: inputs[0].value,
                quantity: parseFloat(inputs[3].value),
                rate: parseFloat(inputs[4].value),
                unit_id: parseInt(row.dataset.unitId)
            });
        }

        const formData = new FormData();
        formData.append('rent_no', document.getElementById('rentNo').value);
        formData.append('branch_id', document.getElementById('branch').value);
        formData.append('date', document.getElementById('date').value);
        formData.append('customer_id', selectedCustomer ? selectedCustomer.id : document.getElementById('customer').dataset.customerId);
        formData.append('customer_name', document.getElementById('customer').value);
        formData.append('customer_phone', document.getElementById('customerPhone').value);
        formData.append('customer_address', document.getElementById('customerAddress').value);
        formData.append('nic_no', document.getElementById('nicNo').value);
        formData.append('reference_name', document.getElementById('refName').value);
        formData.append('reference_phone', document.getElementById('refPhone').value);
        formData.append('reference_nic', document.getElementById('refNic').value);
        formData.append('rent_type', document.getElementById('rentType').value);
        formData.append('rent_rate', parseFloat(document.getElementById('rentRate').value));
        formData.append('total_days', parseInt(document.getElementById('totalDays').value));
        formData.append('total_rent_amount', parseFloat(document.getElementById('totalRentAmount').value));
        formData.append('issue_date', document.getElementById('issueDate').value);
        formData.append('expected_return_date', document.getElementById('expectedReturnDate').value);
        formData.append('remarks', document.getElementById('remarks').value);
        formData.append('items', JSON.stringify(items));
        
        if (editMode) {
            formData.append('rent_id', rentId);
        }
        
        const nicPicture = document.getElementById('nicPicture').files[0];
        const refNicPicture = document.getElementById('refNicPicture').files[0];
        if (nicPicture) formData.append('nic_picture', nicPicture);
        if (refNicPicture) formData.append('reference_nic_picture', refNicPicture);

        try {
            const res = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            
            const data = await res.json();
            
            if (data.success) {
                window.location.href = 'rent-list.php?success=1';
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error saving rent');
        }
    };

    init();
})();
