(function() {
    const API_URL = '../../../../server/api/manufacturing/production_order/list.php';
    let allOrders = [];

    async function loadOrders() {
        try {
            const res = await fetch(`${API_URL}?action=list`);
            const data = await res.json();
            if (data.success) {
                allOrders = data.data;
                filterData();
            }
        } catch (err) {
            console.error('Error loading orders:', err);
        }
    }

    function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:32px; color:#6B7280;">No orders found</td></tr>';
            return;
        }

        data.forEach(order => {
            const tr = document.createElement('tr');
            const statusClass = order.status.toLowerCase().replace(' ', '-');
            
            tr.innerHTML = `
                <td><strong>${order.order_no}</strong></td>
                <td>${order.product_code} - ${order.product_name}</td>
                <td>${order.bom_code} v${order.bom_version}</td>
                <td>${order.branch_name}</td>
                <td>${order.order_qty}</td>
                <td><span class="badge ${statusClass}" onclick="openStatusModal(${order.id}, '${order.status}')">${order.status}</span></td>
                <td>${order.start_date || '-'}</td>
                <td class="actions-cell">
                    <button class="btn-icon" onclick="viewOrder(${order.id})" title="View"><i class="las la-eye"></i></button>
                    <button class="btn-icon" onclick="editOrder(${order.id})" title="Edit"><i class="las la-pencil-alt"></i></button>
                    <button class="btn-icon" onclick="printOrder(${order.id})" title="Print"><i class="las la-print"></i></button>
                    <button class="btn-icon" onclick="deleteOrder(${order.id})" title="Delete"><i class="las la-trash"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function filterData() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;

        const filtered = allOrders.filter(order => {
            const matchesSearch = searchTerm === '' || 
                order.order_no.toLowerCase().includes(searchTerm) || 
                order.product_name.toLowerCase().includes(searchTerm);
            
            const matchesStatus = status === 'all' || order.status === status;
            
            return matchesSearch && matchesStatus;
        });
        
        renderTable(filtered);
    }

    window.openStatusModal = function(id, currentStatus) {
        document.getElementById('statusOrderId').value = id;
        document.getElementById('newStatus').value = currentStatus;
        document.getElementById('statusModal').style.display = 'block';
    };

    window.closeStatusModal = function() {
        document.getElementById('statusModal').style.display = 'none';
    };

    window.updateStatus = async function() {
        const id = document.getElementById('statusOrderId').value;
        const newStatus = document.getElementById('newStatus').value;

        try {
            const res = await fetch(API_URL, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id, status: newStatus })
            });
            const data = await res.json();
            
            if (data.success) {
                closeStatusModal();
                showSuccess('Status updated successfully!');
                loadOrders();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error updating status');
        }
    };

    window.viewOrder = function(id) {
        window.location.href = `view.php?id=${id}`;
    };

    window.editOrder = function(id) {
        window.location.href = `edit.php?id=${id}`;
    };

    window.printOrder = function(id) {
        window.open(`print.php?id=${id}`, '_blank');
    };

    window.deleteOrder = async function(id) {
        if (!confirm('Are you sure you want to delete this order?')) return;
        
        try {
            const res = await fetch(`${API_URL}?id=${id}`, {
                method: 'DELETE'
            });
            const data = await res.json();
            
            if (data.success) {
                showSuccess('Order deleted successfully!');
                loadOrders();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error deleting order');
        }
    };

    function showSuccess(message) {
        const successMsg = document.getElementById('successMessage');
        successMsg.querySelector('span').textContent = message;
        successMsg.style.display = 'flex';
        setTimeout(() => {
            successMsg.style.display = 'none';
        }, 3000);
    }

    document.getElementById('searchInput').addEventListener('input', filterData);
    document.getElementById('statusFilter').addEventListener('change', filterData);
    
    document.getElementById('resetFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = 'all';
        filterData();
    });

    window.onclick = function(event) {
        if (event.target.id === 'statusModal') {
            closeStatusModal();
        }
    };

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success')) {
        showSuccess('Production Order created successfully!');
        window.history.replaceState({}, '', 'list.php');
    }

    loadOrders();
})();
