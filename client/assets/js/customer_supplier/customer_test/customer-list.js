(function () {
    let customers = [];
    let currentPage = 1;
    const itemsPerPage = 10;
    const tbody = document.getElementById('tableBody');
    const searchInput = document.getElementById('searchInput');
    const pagination = document.querySelector('.pagination');

    async function loadCustomers() {
        try {
            const response = await fetch('../../../../server/api/customer_supplier/customer_test/customer-list.php');
            const result = await response.json();
            
            if (result.success) {
                customers = result.data;
                renderTable();
            } else {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:#E34F4F;">${result.message}</td></tr>`;
            }
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:#E34F4F;">Failed to load customers</td></tr>`;
        }
    }

    function renderTable(filter = '') {
        const filterLower = filter.toLowerCase().trim();
        const filtered = customers.filter(c =>
            c.customer_code.toLowerCase().includes(filterLower) ||
            c.customer_name.toLowerCase().includes(filterLower) ||
            c.phone_number.toString().includes(filterLower) ||
            (c.email && c.email.toLowerCase().includes(filterLower))
        );

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:#6B7280;">No customers found</td></tr>`;
            pagination.style.display = 'none';
            return;
        }

        const totalPages = Math.ceil(filtered.length / itemsPerPage);
        if (currentPage > totalPages) currentPage = 1;
        
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pageData = filtered.slice(start, end);

        let html = '';
        pageData.forEach(c => {
            html += `<tr>
                        <td><span class="cell-subtle">${c.customer_code}</span></td>
                        <td><strong>${c.customer_name}</strong></td>
                        <td>${c.phone_number}</td>
                        <td class="email-cell">${c.email || '-'}</td>
                        <td><span class="status-badge status-active">Active</span></td>
                        <td style="text-align: right;">
                            <button class="action-btn" data-code="${c.customer_code}" data-action="edit">Edit</button>
                            <button class="action-btn delete" data-code="${c.customer_code}" data-action="delete">Delete</button>
                        </td>
                    </tr>`;
        });
        tbody.innerHTML = html;
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        if (totalPages <= 1) {
            pagination.style.display = 'none';
            return;
        }
        pagination.style.display = 'flex';
        
        let html = `<div class="pagination-btn" data-page="prev">←</div>`;
        
        for (let i = 1; i <= totalPages; i++) {
            html += `<div class="pagination-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</div>`;
        }
        
        html += `<div class="pagination-btn" data-page="next">→</div>`;
        pagination.innerHTML = html;
    }

    loadCustomers();

    searchInput.addEventListener('input', (e) => {
        currentPage = 1;
        renderTable(e.target.value);
    });

    pagination.addEventListener('click', (e) => {
        const btn = e.target.closest('.pagination-btn');
        if (!btn) return;
        
        const page = btn.getAttribute('data-page');
        const totalPages = Math.ceil(customers.length / itemsPerPage);
        
        if (page === 'prev' && currentPage > 1) {
            currentPage--;
        } else if (page === 'next' && currentPage < totalPages) {
            currentPage++;
        } else if (page !== 'prev' && page !== 'next') {
            currentPage = parseInt(page);
        }
        
        renderTable(searchInput.value);
    });

    tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('.action-btn');
        if (!btn) return;

        const code = btn.getAttribute('data-code');
        const action = btn.getAttribute('data-action');

        if (action === 'edit') {
            alert(`✏️ Edit customer: ${code}`);
        } else if (action === 'delete') {
            if (confirm(`Delete customer ${code}?`)) {
                alert('Delete functionality not implemented yet');
            }
        }
    });

    document.getElementById('newCustomerBtn').addEventListener('click', () => {
        window.location.href = 'customer-add.php';
    });
})();
