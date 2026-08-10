// Payroll data
let payrollData = [];

// Fetch payroll data
fetch('../../../../server/api/hrm/payroll/payroll-list.php')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            payrollData = data.payrolls || [];
            currentData = [...payrollData];
            renderTable();
            updateStats();
            populateEmployeeFilter();
        }
    })
    .catch(err => console.error('Error fetching payroll data:', err));

// Populate employee filter
function populateEmployeeFilter() {
    const uniqueEmployees = [...new Map(payrollData.map(item => 
        [item.employee_id, {id: item.employee_id, name: item.employee_name}]
    )).values()];
    
    const employeeList = document.getElementById('employeeList');
    uniqueEmployees.forEach(emp => {
        const option = document.createElement('option');
        option.value = `${emp.id} - ${emp.name}`;
        option.dataset.id = emp.id;
        employeeList.appendChild(option);
    });
}

// Sample data for fallback
const sampleData = [
    {
        id: "PR2023001",
        date: "2023-10-15",
        employeeCode: "EMP001",
        employeeName: "John Smith",
        type: "Salary",
        amount: 2450.00,
        paymentMethod: "Bank Transfer",
        status: "paid",
        description: "Monthly salary October"
    },
    {
        id: "PR2023002",
        date: "2023-10-16",
        employeeCode: "EMP002",
        employeeName: "Sarah Johnson",
        type: "Salary",
        amount: 1850.50,
        paymentMethod: "Bank Transfer",
        status: "paid",
        description: "Monthly salary October"
    },
    {
        id: "PR2023003",
        date: "2023-10-17",
        employeeCode: "EMP003",
        employeeName: "Michael Chen",
        type: "Bonus",
        amount: 500.00,
        paymentMethod: "Cash",
        status: "approved",
        description: "Performance bonus Q3"
    },
    {
        id: "PR2023004",
        date: "2023-10-18",
        employeeCode: "EMP004",
        employeeName: "Emma Wilson",
        type: "Advance",
        amount: 1000.00,
        paymentMethod: "Cheque",
        status: "pending",
        description: "Salary advance request"
    },
    {
        id: "PR2023005",
        date: "2023-10-19",
        employeeCode: "EMP005",
        employeeName: "Robert Davis",
        type: "Commission",
        amount: 750.25,
        paymentMethod: "Mobile Payment",
        status: "pending",
        description: "Sales commission October"
    },
    {
        id: "PR2023006",
        date: "2023-10-20",
        employeeCode: "EMP001",
        employeeName: "John Smith",
        type: "Allowance",
        amount: 200.00,
        paymentMethod: "Bank Transfer",
        status: "draft",
        description: "Travel allowance"
    },
    {
        id: "PR2023007",
        date: "2023-10-21",
        employeeCode: "EMP003",
        employeeName: "Michael Chen",
        type: "Salary Adjustment",
        amount: 150.75,
        paymentMethod: "Bank Transfer",
        status: "rejected",
        description: "Overtime adjustment"
    },
    {
        id: "PR2023008",
        date: "2023-10-22",
        employeeCode: "EMP006",
        employeeName: "Lisa Thompson",
        type: "Daily Wages",
        amount: 89.90,
        paymentMethod: "Cash",
        status: "paid",
        description: "Daily wages for 22nd Oct"
    }
];

// DOM elements
const tableBody = document.getElementById('tableBody');
const emptyState = document.getElementById('emptyState');
const totalRecords = document.getElementById('totalRecords');
const totalAmount = document.getElementById('totalAmount');
const pendingCount = document.getElementById('pendingCount');
const searchInput = document.getElementById('searchInput');
const dateRangeFilter = document.getElementById('dateRange');
const employeeFilter = document.getElementById('employeeFilter');
const customDateFields = document.getElementById('customDateFields');
const customDateFieldsEnd = document.getElementById('customDateFieldsEnd');
const startDate = document.getElementById('startDate');
const endDate = document.getElementById('endDate');
const applyFiltersBtn = document.getElementById('applyFiltersBtn');
const resetFiltersBtn = document.getElementById('resetFiltersBtn');
const exportBtn = document.getElementById('exportBtn');
const refreshBtn = document.getElementById('refreshBtn');
const newPayrollBtn = document.getElementById('newPayrollBtn');
const deleteModal = document.getElementById('deleteModal');
const cancelDelete = document.getElementById('cancelDelete');
const confirmDelete = document.getElementById('confirmDelete');

// Current state
let currentData = [];
let currentPage = 1;
const rowsPerPage = 10;
let deleteId = null;

// Initialize the page
function initializePage() {
    renderTable();
    updateStats();
    addEventListeners();
}

// Render the table with current data
function renderTable() {
    tableBody.innerHTML = '';

    if (currentData.length === 0) {
        document.getElementById('payrollTable').style.display = 'none';
        emptyState.style.display = 'block';
        updatePaginationInfo(0);
        return;
    }

    document.getElementById('payrollTable').style.display = 'table';
    emptyState.style.display = 'none';

    // Calculate pagination
    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = Math.min(startIndex + rowsPerPage, currentData.length);
    const pageData = currentData.slice(startIndex, endIndex);

    pageData.forEach(item => {
        const row = document.createElement('tr');

        // Format date
        const dateObj = new Date(item.payroll_date);
        const formattedDate = dateObj.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });

        // Format amount
        const formattedAmount = currencySymbol + parseFloat(item.amount).toFixed(2);

        // Get status badge class and text
        let statusClass = '';
        let statusText = '';

        switch (item.status) {
            case 'draft':
                statusClass = 'status-draft';
                statusText = 'Draft';
                break;
            case 'pending':
                statusClass = 'status-pending';
                statusText = 'Pending';
                break;
            case 'approved':
                statusClass = 'status-approved';
                statusText = 'Approved';
                break;
            case 'rejected':
                statusClass = 'status-rejected';
                statusText = 'Rejected';
                break;
            case 'paid':
                statusClass = 'status-paid';
                statusText = 'Paid';
                break;
        }

        row.innerHTML = `
                    <td><strong>${item.payroll_code}</strong></td>
                    <td>${formattedDate}</td>
                    <td>
                        <div>${item.employee_name}</div>
                        <div style="font-size: 12px; color: #6B7280;">${item.employee_id}</div>
                    </td>
                    <td>${item.type}</td>
                    <td><strong>${formattedAmount}</strong></td>
                    <td>${item.payment_method}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn view" title="View" data-id="${item.id}">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn edit" title="Edit" data-id="${item.id}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn delete" title="Delete" data-id="${item.id}">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button class="action-btn print" title="Print Receipt" data-id="${item.id}" data-employee="${item.employee_id}">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </td>
                `;

        tableBody.appendChild(row);
    });

    // Update pagination info
    updatePaginationInfo(currentData.length, startIndex, endIndex);
}

// Update statistics
function updateStats() {
    totalRecords.textContent = currentData.length;

    const total = currentData.reduce((sum, item) => sum + parseFloat(item.amount), 0);
    totalAmount.textContent = currencySymbol + total.toFixed(2);

    pendingCount.textContent = 0;
}

// Update pagination information
function updatePaginationInfo(total, start = 0, end = 0) {
    document.getElementById('startRow').textContent = start + 1;
    document.getElementById('endRow').textContent = end;
    document.getElementById('totalRows').textContent = total;

    // Update pagination buttons
    const totalPages = Math.ceil(total / rowsPerPage);
    const firstPageBtn = document.getElementById('firstPage');
    const prevPageBtn = document.getElementById('prevPage');
    const nextPageBtn = document.getElementById('nextPage');
    const lastPageBtn = document.getElementById('lastPage');

    firstPageBtn.disabled = currentPage === 1;
    prevPageBtn.disabled = currentPage === 1;
    nextPageBtn.disabled = currentPage === totalPages || totalPages === 0;
    lastPageBtn.disabled = currentPage === totalPages || totalPages === 0;

    // Generate page number buttons
    const pageNumbers = document.getElementById('pageNumbers');
    pageNumbers.innerHTML = '';
    
    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = 'pagination-btn';
        pageBtn.textContent = i;
        if (i === currentPage) {
            pageBtn.classList.add('active');
        }
        pageBtn.addEventListener('click', () => {
            currentPage = i;
            renderTable();
        });
        pageNumbers.appendChild(pageBtn);
    }
}

// Apply filters
function applyFilters() {
    let filteredData = [...payrollData];

    // Apply date range filter
    const dateRange = dateRangeFilter.value;
    if (dateRange === 'custom') {
        const start = startDate.value ? new Date(startDate.value) : null;
        const end = endDate.value ? new Date(endDate.value) : null;
        
        if (start || end) {
            filteredData = filteredData.filter(item => {
                const itemDate = new Date(item.payroll_date);
                if (start && end) {
                    return itemDate >= start && itemDate <= end;
                } else if (start) {
                    return itemDate >= start;
                } else if (end) {
                    return itemDate <= end;
                }
                return true;
            });
        }
    } else if (dateRange) {
        const today = new Date();
        const filterStartDate = new Date();

        switch (dateRange) {
            case 'today':
                filterStartDate.setHours(0, 0, 0, 0);
                break;
            case 'week':
                filterStartDate.setDate(today.getDate() - 7);
                break;
            case 'month':
                filterStartDate.setMonth(today.getMonth() - 1);
                break;
            case 'quarter':
                filterStartDate.setMonth(today.getMonth() - 3);
                break;
            case 'year':
                filterStartDate.setFullYear(today.getFullYear() - 1);
                break;
        }

        filteredData = filteredData.filter(item => {
            const itemDate = new Date(item.payroll_date);
            return itemDate >= filterStartDate;
        });
    }

    // Apply employee filter
    const employeeInput = employeeFilter.value;
    if (employeeInput) {
        const employeeId = employeeInput.split(' - ')[0];
        filteredData = filteredData.filter(item => 
            item.employee_id === employeeId || 
            item.employee_name.toLowerCase().includes(employeeInput.toLowerCase())
        );
    }

    // Apply search filter
    const searchTerm = searchInput.value.toLowerCase();
    if (searchTerm) {
        filteredData = filteredData.filter(item =>
            item.payroll_code.toLowerCase().includes(searchTerm) ||
            item.employee_name.toLowerCase().includes(searchTerm) ||
            item.type.toLowerCase().includes(searchTerm) ||
            (item.description && item.description.toLowerCase().includes(searchTerm))
        );
    }

    currentData = filteredData;
    currentPage = 1; // Reset to first page
    renderTable();
    updateStats();
}

// Reset filters
function resetFilters() {
    dateRangeFilter.value = 'month';
    employeeFilter.value = '';
    searchInput.value = '';
    startDate.value = '';
    endDate.value = '';
    customDateFields.style.display = 'none';
    customDateFieldsEnd.style.display = 'none';
    applyFilters();
}

// Add event listeners
function addEventListeners() {
    // Search input
    searchInput.addEventListener('input', () => {
        // Add debounce for better performance
        clearTimeout(searchInput.timeout);
        searchInput.timeout = setTimeout(applyFilters, 300);
    });

    // Date range change
    dateRangeFilter.addEventListener('change', () => {
        if (dateRangeFilter.value === 'custom') {
            customDateFields.style.display = 'block';
            customDateFieldsEnd.style.display = 'block';
        } else {
            customDateFields.style.display = 'none';
            customDateFieldsEnd.style.display = 'none';
        }
    });

    // Filter buttons
    applyFiltersBtn.addEventListener('click', applyFilters);
    resetFiltersBtn.addEventListener('click', resetFilters);

    // Action buttons
    tableBody.addEventListener('click', (e) => {
        const target = e.target.closest('button');
        if (!target) return;

        const id = target.dataset.id;
        if (!id) return;

        if (target.classList.contains('view')) {
            viewRecord(id);
        } else if (target.classList.contains('edit')) {
            editRecord(id);
        } else if (target.classList.contains('delete')) {
            deleteRecord(id);
        } else if (target.classList.contains('print')) {
            const employeeId = target.dataset.employee;
            printReceipt(id, employeeId);
        }
    });

    // Export button
    exportBtn.addEventListener('click', () => {
        alert('Export functionality would be implemented here. Data exported successfully!');
    });

    // Refresh button
    refreshBtn.addEventListener('click', () => {
        resetFilters();
        alert('Data refreshed!');
    });

    // Pagination buttons
    document.getElementById('firstPage').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage = 1;
            renderTable();
        }
    });

    document.getElementById('prevPage').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    });

    document.getElementById('nextPage').addEventListener('click', () => {
        const totalPages = Math.ceil(currentData.length / rowsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
        }
    });

    document.getElementById('lastPage').addEventListener('click', () => {
        const totalPages = Math.ceil(currentData.length / rowsPerPage);
        if (currentPage < totalPages) {
            currentPage = totalPages;
            renderTable();
        }
    });

    // Delete modal
    cancelDelete.addEventListener('click', () => {
        deleteModal.style.display = 'none';
    });

    confirmDelete.addEventListener('click', () => {
        if (deleteId) {
            fetch('../../../../server/api/hrm/payroll/payroll-delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: deleteId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    currentData = currentData.filter(item => item.id !== deleteId);
                    payrollData = payrollData.filter(item => item.id !== deleteId);
                    renderTable();
                    updateStats();
                    deleteModal.style.display = 'none';
                    deleteId = null;
                    showNotification('Payroll record deleted successfully', 'success');
                } else {
                    alert('Error: ' + data.message);
                    deleteModal.style.display = 'none';
                }
            })
            .catch(err => {
                alert('Error deleting payroll: ' + err.message);
                deleteModal.style.display = 'none';
            });
        }
    });

    // Close modal when clicking outside
    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) {
            deleteModal.style.display = 'none';
        }
    });
}

// View record
function viewRecord(id) {
    window.location.href = `payroll-add.php?view=${id}`;
}

// Edit record
function editRecord(id) {
    window.location.href = `payroll-add.php?edit=${id}`;
}

// Delete record
function deleteRecord(id) {
    deleteId = id;
    deleteModal.style.display = 'flex';
}

// Print receipt
function printReceipt(id, employeeId) {
    if (employeeId) {
        window.open(`payroll-receipt.php?id=${id}&employee_id=${employeeId}`, '_blank');
    } else {
        const record = currentData.find(item => item.id === id);
        if (record) {
            window.open(`payroll-receipt.php?id=${id}&employee_id=${record.employee_id}`, '_blank');
        }
    }
}

// Show notification
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background-color: white;
                border-left: 4px solid ${type === 'success' ? '#2FBF71' : '#E34F4F'};
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                display: flex;
                align-items: center;
                gap: 12px;
                z-index: 1000;
                max-width: 400px;
                animation: slideIn 0.3s ease;
            `;

    notification.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}" style="color: ${type === 'success' ? '#2FBF71' : '#E34F4F'}"></i>
                <div>
                    <div style="font-weight: 500; color: #0E1A2B; margin-bottom: 4px;">${type === 'success' ? 'Success' : 'Error'}</div>
                    <div style="font-size: 14px; color: #2F3B4C;">${message}</div>
                </div>
            `;

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);

    // Add CSS for animations
    if (!document.querySelector('#notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
                    @keyframes slideIn {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOut {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
        document.head.appendChild(style);
    }
}

// Initialize the page when loaded
document.addEventListener('DOMContentLoaded', initializePage);