(function() {
    const API_URL = '../../../server/api/rent/rent.php';
    let allRents = [];
    let bankAccounts = [];

    async function init() {
        await loadBankAccounts();
        await loadRents();
        
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success')) {
            const msg = document.getElementById('successMessage');
            msg.style.display = 'flex';
            setTimeout(() => {
                msg.style.display = 'none';
                window.history.replaceState({}, '', 'rent-list.php');
            }, 3000);
        }
    }

    async function loadBankAccounts() {
        const res = await fetch(`${API_URL}?action=bank_accounts`);
        const data = await res.json();
        if (data.success) {
            bankAccounts = data.data;
        }
    }

    async function loadRents() {
        const res = await fetch(`${API_URL}?action=list`);
        const data = await res.json();
        if (data.success) {
            allRents = data.data;
            renderTable(allRents);
        }
    }

    function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:32px;">No records found</td></tr>';
            return;
        }

        data.forEach(rent => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${rent.rent_no}</strong></td>
                <td>${rent.customer_name}</td>
                <td>${new Date(rent.issue_date).toLocaleDateString()}</td>
                <td>${new Date(rent.expected_return_date).toLocaleDateString()}</td>
                <td>Rs. ${parseFloat(rent.total_rent_amount).toFixed(2)}</td>
                <td>Rs. ${parseFloat(rent.late_charges || 0).toFixed(2)}</td>
                <td>Rs. ${parseFloat(rent.damage_charges || 0).toFixed(2)}</td>
                <td>Rs. ${parseFloat(rent.net_amount || rent.total_rent_amount).toFixed(2)}</td>
                <td><span class="badge ${rent.status.toLowerCase()}">${rent.status}</span></td>
                <td>
                    <button class="btn-icon" onclick="viewRent(${rent.id})" title="View"><i class="las la-eye"></i></button>
                    <button class="btn-icon" onclick="printRent(${rent.id})" title="Print"><i class="las la-print"></i></button>
                    ${rent.status === 'Issued' ? `<button class="btn-icon" onclick="editRent(${rent.id})" title="Edit"><i class="las la-edit"></i></button>` : ''}
                    ${rent.status === 'Issued' ? `<button class="btn-icon" onclick="returnRent(${rent.id})" title="Return"><i class="las la-undo"></i></button>` : ''}
                    <button class="btn-icon" onclick="deleteRent(${rent.id})" title="Delete"><i class="las la-trash"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    window.filterData = function() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        
        const filtered = allRents.filter(r => {
            const matchSearch = r.rent_no.toLowerCase().includes(search) || r.customer_name.toLowerCase().includes(search);
            const matchStatus = status === 'all' || r.status === status;
            return matchSearch && matchStatus;
        });
        
        renderTable(filtered);
    };

    document.getElementById('searchInput').addEventListener('input', filterData);

    window.viewRent = async function(id) {
        window.location.href = `rent-issue.php?id=${id}&mode=view`;
    };

    window.printRent = async function(id) {
        const res = await fetch(`${API_URL}?action=view&id=${id}`);
        const data = await res.json();
        
        if (data.success && data.data) {
            const rent = data.data;
            const itemsHtml = rent.items.map(item => `
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;">${item.item_name}</td>
                    <td style="padding: 8px; border: 1px solid #ddd;">${item.description || '-'}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">${item.quantity}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">Rs. ${parseFloat(item.rate).toFixed(2)}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">Rs. ${(item.quantity * item.rate).toFixed(2)}</td>
                </tr>
            `).join('');
            
            const printWindow = window.open('', '', 'width=900,height=700');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Rent Invoice - ${rent.rent_no}</title>
                    <style>
                        @media print {
                            @page { margin: 0.5cm; }
                        }
                        body { 
                            font-family: Arial, sans-serif; 
                            padding: 30px; 
                            max-width: 900px;
                            margin: 0 auto;
                        }
                        .header {
                            text-align: center;
                            border-bottom: 3px solid #333;
                            padding-bottom: 15px;
                            margin-bottom: 25px;
                        }
                        .header h1 { 
                            margin: 0 0 5px 0; 
                            font-size: 28px;
                            color: #333;
                        }
                        .header p { 
                            margin: 0; 
                            color: #666;
                            font-size: 14px;
                        }
                        .invoice-info {
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 20px;
                            margin-bottom: 25px;
                        }
                        .info-section {
                            background: #f9f9f9;
                            padding: 15px;
                            border-radius: 5px;
                        }
                        .info-section h3 {
                            margin: 0 0 10px 0;
                            font-size: 14px;
                            color: #333;
                            border-bottom: 2px solid #ddd;
                            padding-bottom: 5px;
                        }
                        .info-row {
                            display: grid;
                            grid-template-columns: 120px 1fr;
                            margin-bottom: 8px;
                            font-size: 13px;
                        }
                        .info-label {
                            font-weight: bold;
                            color: #555;
                        }
                        table { 
                            width: 100%; 
                            border-collapse: collapse; 
                            margin: 20px 0;
                        }
                        th { 
                            background: #333; 
                            color: white;
                            padding: 10px; 
                            border: 1px solid #333; 
                            text-align: left;
                            font-size: 13px;
                        }
                        td { 
                            padding: 8px; 
                            border: 1px solid #ddd;
                            font-size: 13px;
                        }
                        .totals {
                            margin-top: 20px;
                            text-align: right;
                        }
                        .total-row {
                            display: flex;
                            justify-content: flex-end;
                            margin-bottom: 8px;
                            font-size: 14px;
                        }
                        .total-label {
                            width: 200px;
                            font-weight: bold;
                            text-align: right;
                            padding-right: 15px;
                        }
                        .total-value {
                            width: 150px;
                            text-align: right;
                            padding: 5px 10px;
                            background: #f9f9f9;
                        }
                        .grand-total {
                            border-top: 2px solid #333;
                            padding-top: 10px;
                            margin-top: 10px;
                        }
                        .grand-total .total-value {
                            background: #333;
                            color: white;
                            font-size: 16px;
                            font-weight: bold;
                        }
                        .footer {
                            margin-top: 40px;
                            padding-top: 20px;
                            border-top: 2px solid #ddd;
                            text-align: center;
                            font-size: 12px;
                            color: #666;
                        }
                        .remarks {
                            margin-top: 20px;
                            padding: 15px;
                            background: #f9f9f9;
                            border-left: 4px solid #333;
                        }
                        .remarks strong {
                            display: block;
                            margin-bottom: 5px;
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>RENT INVOICE</h1>
                        <p>Rent No: ${rent.rent_no} | Date: ${new Date(rent.date).toLocaleDateString()}</p>
                    </div>
                    
                    <div class="invoice-info">
                        <div class="info-section">
                            <h3>Customer Information</h3>
                            <div class="info-row">
                                <span class="info-label">Name:</span>
                                <span>${rent.customer_name}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                <span>${rent.customer_phone || '-'}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Address:</span>
                                <span>${rent.customer_address || '-'}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">NIC:</span>
                                <span>${rent.nic_no || '-'}</span>
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <h3>Rent Details</h3>
                            <div class="info-row">
                                <span class="info-label">Issue Date:</span>
                                <span>${new Date(rent.issue_date).toLocaleDateString()}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Expected Return:</span>
                                <span>${new Date(rent.expected_return_date).toLocaleDateString()}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Rent Type:</span>
                                <span>${rent.rent_type}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Total Days:</span>
                                <span>${rent.total_days}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Status:</span>
                                <span><strong>${rent.status}</strong></span>
                            </div>
                        </div>
                    </div>
                    
                    ${rent.reference_name ? `
                    <div class="info-section" style="margin-bottom: 20px;">
                        <h3>Reference Information</h3>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                            <div class="info-row">
                                <span class="info-label">Name:</span>
                                <span>${rent.reference_name}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                <span>${rent.reference_phone || '-'}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">NIC:</span>
                                <span>${rent.reference_nic || '-'}</span>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Description</th>
                                <th style="text-align: center;">Quantity</th>
                                <th style="text-align: right;">Rate</th>
                                <th style="text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>${itemsHtml}</tbody>
                    </table>
                    
                    <div class="totals">
                        <div class="total-row">
                            <div class="total-label">Rent Amount:</div>
                            <div class="total-value">Rs. ${parseFloat(rent.total_rent_amount).toFixed(2)}</div>
                        </div>
                        ${rent.status === 'Returned' ? `
                            <div class="total-row">
                                <div class="total-label">Late Charges:</div>
                                <div class="total-value">Rs. ${parseFloat(rent.late_charges || 0).toFixed(2)}</div>
                            </div>
                            <div class="total-row">
                                <div class="total-label">Damage Charges:</div>
                                <div class="total-value">Rs. ${parseFloat(rent.damage_charges || 0).toFixed(2)}</div>
                            </div>
                            <div class="total-row grand-total">
                                <div class="total-label">Net Amount:</div>
                                <div class="total-value">Rs. ${parseFloat(rent.net_amount || 0).toFixed(2)}</div>
                            </div>
                            <div style="margin-top: 15px; font-size: 13px;">
                                <strong>Return Date:</strong> ${new Date(rent.actual_return_date).toLocaleDateString()} | 
                                <strong>Payment Method:</strong> ${rent.payment_method}
                            </div>
                        ` : `
                            <div class="total-row grand-total">
                                <div class="total-label">Total Amount:</div>
                                <div class="total-value">Rs. ${parseFloat(rent.total_rent_amount).toFixed(2)}</div>
                            </div>
                        `}
                    </div>
                    
                    ${rent.remarks ? `
                    <div class="remarks">
                        <strong>Remarks:</strong>
                        <p style="margin: 5px 0 0 0;">${rent.remarks}</p>
                    </div>
                    ` : ''}
                    
                    <div class="footer">
                        <p>Thank you for your business!</p>
                        <p>This is a computer generated invoice.</p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    };

    window.editRent = function(id) {
        window.location.href = `rent-issue.php?id=${id}`;
    };

    window.deleteRent = async function(id) {
        if (!confirm('Are you sure you want to delete this rent record?')) return;
        
        try {
            const res = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
            const data = await res.json();
            
            if (data.success) {
                alert('Rent deleted successfully');
                loadRents();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error deleting rent');
        }
    };

    window.returnRent = async function(id) {
        const res = await fetch(`${API_URL}?action=view&id=${id}`);
        const data = await res.json();
        
        if (data.success && data.data) {
            const rent = data.data;
            
            let html = `
                <input type="hidden" id="returnRentId" value="${rent.id}">
                <div class="form-grid">
                    <div class="field-group">
                        <label>Actual Return Date</label>
                        <input type="date" id="actualReturnDate" class="form-control" value="${new Date().toISOString().split('T')[0]}">
                    </div>
                    <div class="field-group">
                        <label>Late Charges</label>
                        <input type="number" id="lateCharges" class="form-control" value="0" step="0.01" onchange="calculateNetAmount()">
                    </div>
                    <div class="field-group">
                        <label>Damage Charges</label>
                        <input type="number" id="damageCharges" class="form-control" value="0" step="0.01" onchange="calculateNetAmount()">
                    </div>
                    <div class="field-group">
                        <label>Net Amount</label>
                        <input type="number" id="netAmount" class="form-control" value="${rent.total_rent_amount}" readonly>
                    </div>
                    <div class="field-group">
                        <label>Payment Method</label>
                        <select id="paymentMethod" class="form-control" onchange="toggleBankAccount()">
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank</option>
                            <option value="Online">Online</option>
                        </select>
                    </div>
                    <div class="field-group" id="bankAccountField" style="display:none;">
                        <label>Bank Account</label>
                        <select id="bankAccount" class="form-control">
                            ${bankAccounts.map(a => `<option value="${a.id}">${a.bank_name} - ${a.account_number}</option>`).join('')}
                        </select>
                    </div>
                </div>
                <h4 style="margin: 20px 0 10px;">Items Condition After Return</h4>
                <div id="returnItems">
                    ${rent.items.map(item => `
                        <div class="field-group" style="margin-bottom: 12px;">
                            <label>${item.item_name}</label>
                            <input type="text" class="form-control item-condition" data-id="${item.id}" data-product="${item.product_id}" data-qty="${item.quantity}" data-unit="${item.unit_id}" data-rate="${item.rate}" placeholder="Condition after return">
                        </div>
                    `).join('')}
                </div>
                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="closeReturnModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="submitReturn()">Submit Return</button>
                </div>
            `;
            
            document.getElementById('modalTitle').textContent = 'Return Rent';
            document.getElementById('returnModalBody').innerHTML = html;
            document.getElementById('netAmount').dataset.base = rent.total_rent_amount;
            document.getElementById('returnModal').style.display = 'block';
        }
    };

    window.closeReturnModal = function() {
        document.getElementById('returnModal').style.display = 'none';
    };

    window.toggleBankAccount = function() {
        const method = document.getElementById('paymentMethod').value;
        const field = document.getElementById('bankAccountField');
        field.style.display = (method === 'Bank' || method === 'Online') ? 'block' : 'none';
    };

    window.calculateNetAmount = function() {
        const rentAmount = parseFloat(document.getElementById('netAmount').dataset.base) || 0;
        const late = parseFloat(document.getElementById('lateCharges').value) || 0;
        const damage = parseFloat(document.getElementById('damageCharges').value) || 0;
        document.getElementById('netAmount').value = (rentAmount + late + damage).toFixed(2);
    };

    window.submitReturn = async function() {
        const items = [];
        document.querySelectorAll('.item-condition').forEach(input => {
            items.push({
                id: parseInt(input.dataset.id),
                product_id: parseInt(input.dataset.product),
                quantity: parseFloat(input.dataset.qty),
                unit_id: parseInt(input.dataset.unit),
                rate: parseFloat(input.dataset.rate),
                condition_after: input.value
            });
        });

        const method = document.getElementById('paymentMethod').value;
        const payload = {
            rent_id: parseInt(document.getElementById('returnRentId').value),
            actual_return_date: document.getElementById('actualReturnDate').value,
            late_charges: parseFloat(document.getElementById('lateCharges').value),
            damage_charges: parseFloat(document.getElementById('damageCharges').value),
            payment_method: method,
            bank_account_id: (method === 'Bank' || method === 'Online') ? parseInt(document.getElementById('bankAccount').value) : null,
            items
        };

        try {
            const res = await fetch(API_URL, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            
            const data = await res.json();
            
            if (data.success) {
                window.location.href = 'rent-list.php?success=2';
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error returning rent');
        }
    };

    init();
})();
