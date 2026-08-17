let employees = [];
let filteredEmployees = [];
let currentEmployee = null;
let currentPage = 1;
let rowsPerPage = 10;
let selectedLeaveType = 'paid';

// Initialize the table
async function initTable() {
    await loadEmployees();
    updateStats();
    renderTable();
}

// Load employees from API
async function loadEmployees() {
    try {
        const response = await fetch('../../../../server/api/hrm/employees/employee-list.php');
        const result = await response.json();
        
        if (result.success) {
            employees = result.employees.map(emp => ({
                id: emp.id,
                employeeId: emp.employee_id,
                name: emp.full_name,
                email: emp.email,
                department: emp.department_name || 'N/A',
                position: emp.position_title || 'N/A',
                status: emp.current_status,
                hireDate: emp.hire_date,
                phone: emp.phone_number,
                employmentType: emp.employment_type,
                location: emp.work_location || 'Office',
                manager: emp.manager_name || 'N/A',
                avatar: '',
                leaveBalancePaid: emp.leave_balance_paid || 0,
                leaveBalanceSick: emp.leave_balance_sick || 0,
                leaveBalanceUnpaid: emp.leave_balance_unpaid || 0
            }));
            
            // Update stats from API
            document.getElementById('activeCount').textContent = result.stats.active;
            document.getElementById('onLeaveCount').textContent = result.stats.on_leave;
            document.getElementById('suspendedCount').textContent = result.stats.suspended;
            document.getElementById('terminatedCount').textContent = result.stats.terminated;
            
            // Populate filter dropdowns
            populateFilters(result.departments, result.positions);
            
            filteredEmployees = [...employees];
        }
    } catch (error) {
        console.error('Error loading employees:', error);
    }
}

// Populate filter dropdowns
function populateFilters(departments, positions) {
    const deptFilter = document.getElementById('departmentFilter');
    const posFilter = document.getElementById('positionFilter');
    
    departments.forEach(dept => {
        const option = document.createElement('option');
        option.value = dept;
        option.textContent = dept;
        deptFilter.appendChild(option);
    });
    
    positions.forEach(pos => {
        const option = document.createElement('option');
        option.value = pos;
        option.textContent = pos;
        posFilter.appendChild(option);
    });
}

// Update statistics
function updateStats() {
    const activeCount = employees.filter(e => e.status === 'active').length;
    const onLeaveCount = employees.filter(e => e.status === 'on-leave').length;
    const suspendedCount = employees.filter(e => e.status === 'suspended').length;
    const terminatedCount = employees.filter(e => e.status === 'terminated').length;

    document.getElementById('activeCount').textContent = activeCount;
    document.getElementById('onLeaveCount').textContent = onLeaveCount;
    document.getElementById('suspendedCount').textContent = suspendedCount;
    document.getElementById('terminatedCount').textContent = terminatedCount;
    
    updateStatChanges();
}

// Calculate and display stat changes
function updateStatChanges() {
    const thisMonth = new Date().getMonth();
    const thisYear = new Date().getFullYear();
    
    const thisMonthEmployees = employees.filter(e => {
        const hireDate = new Date(e.hireDate);
        return hireDate.getMonth() === thisMonth && hireDate.getFullYear() === thisYear;
    });
    
    const activeChange = thisMonthEmployees.filter(e => e.status === 'active').length;
    const leaveChange = employees.filter(e => e.status === 'on-leave').length - employees.filter(e => e.status === 'active').length;
    const suspendedChange = employees.filter(e => e.status === 'suspended').length;
    const terminatedChange = employees.filter(e => e.status === 'terminated').length;
    
    setStatChange('activeChange', activeChange);
    setStatChange('leaveChange', leaveChange);
    setStatChange('suspendedChange', suspendedChange);
    setStatChange('terminatedChange', terminatedChange);
}

function setStatChange(elementId, value) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    element.className = 'stat-change';
    
    if (value > 0) {
        element.className += ' positive';
        element.textContent = `+${value} this month`;
    } else if (value < 0) {
        element.className += ' negative';
        element.textContent = `${value} this month`;
    } else {
        element.textContent = 'No change';
    }
}

// Render employee table
function renderTable() {
    const tbody = document.getElementById('employeeTableBody');
    tbody.innerHTML = '';

    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    const pageEmployees = filteredEmployees.slice(startIndex, endIndex);

    pageEmployees.forEach(employee => {
        const row = document.createElement('tr');
        row.className = employee.status;

        const statusBadge = getStatusBadge(employee.status);
        const avatarInitials = employee.name.split(' ').map(n => n[0]).join('').toUpperCase();

        row.innerHTML = `
                    <td>
                        <div class="employee-avatar">
                            ${employee.avatar ? `<img src="${employee.avatar}" alt="${employee.name}">` : avatarInitials}
                        </div>
                    </td>
                    <td>
                        <div class="employee-info">
                            <div>
                                <div class="employee-name">${employee.name}</div>
                                <div class="employee-email">${employee.email}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="employee-id">${employee.employeeId}</div>
                    </td>
                    <td>${employee.department}</td>
                    <td>${employee.position}</td>
                    <td>${statusBadge}</td>
                    <td>${formatDate(employee.hireDate)}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn" title="View Details" onclick="viewEmployee('${employee.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn" title="Edit" onclick="editEmployee('${employee.id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn terminate" title="Delete" onclick="deleteEmployee(${employee.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button class="action-btn" title="Employee Card" onclick="window.open('card.php?id=${employee.id}', '_blank')">
                                <i class="fas fa-id-card"></i>
                            </button>
                            <button class="action-btn leave" title="Manage Leave" onclick="manageLeave('${employee.id}')">
                                <i class="fas fa-umbrella-beach"></i>
                            </button>
                            <button class="action-btn" title="Leave Balance" onclick="manageLeaveBalance('${employee.id}')">
                                <i class="fas fa-calendar-plus"></i>
                            </button>
                            <button class="action-btn" title="Change Status" onclick="changeStatus('${employee.id}')">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="action-btn suspend" title="Suspend" onclick="suspendEmployee('${employee.id}')">
                                <i class="fas fa-ban"></i>
                            </button>
                            <button class="action-btn terminate" title="Terminate" onclick="terminateEmployee('${employee.id}')">
                                <i class="fas fa-user-slash"></i>
                            </button>
                        </div>
                    </td>
                `;

        tbody.appendChild(row);
    });

    updatePaginationInfo();
}

// Get status badge HTML
function getStatusBadge(status) {
    const statusMap = {
        'active': { class: 'status-active', text: 'Active' },
        'inactive': { class: 'status-inactive', text: 'Inactive' },
        'on-leave': { class: 'status-on-leave', text: 'On Leave' },
        'suspended': { class: 'status-suspended', text: 'Suspended' },
        'terminated': { class: 'status-terminated', text: 'Terminated' },
        'probation': { class: 'status-active', text: 'Probation' }
    };

    const statusInfo = statusMap[status] || { class: 'status-inactive', text: status };
    return `<span class="status-badge ${statusInfo.class}">${statusInfo.text}</span>`;
}

// Filter employees based on search and filters
function filterEmployees() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const department = document.getElementById('departmentFilter').value;
    const position = document.getElementById('positionFilter').value;
    const status = document.getElementById('statusFilter').value;

    filteredEmployees = employees.filter(employee => {
        const matchesSearch = !searchTerm ||
            employee.name.toLowerCase().includes(searchTerm) ||
            employee.employeeId.toLowerCase().includes(searchTerm) ||
            employee.department.toLowerCase().includes(searchTerm) ||
            employee.position.toLowerCase().includes(searchTerm);

        const matchesDept = !department || employee.department === department;
        const matchesPos = !position || employee.position === position;
        const matchesStatus = !status || employee.status === status;

        return matchesSearch && matchesDept && matchesPos && matchesStatus;
    });

    currentPage = 1;
    renderTable();
}

// Set quick filter
function setQuickFilter(filter) {
    const chips = document.querySelectorAll('.filter-chip');
    chips.forEach(chip => chip.classList.remove('active'));
    event.target.classList.add('active');

    // Reset other filters
    document.getElementById('departmentFilter').value = '';
    document.getElementById('positionFilter').value = '';
    document.getElementById('statusFilter').value = '';

    switch (filter) {
        case 'probation':
            filteredEmployees = employees.filter(e => e.employmentType === 'probation');
            break;
        case 'contract':
            filteredEmployees = employees.filter(e => e.employmentType === 'contract');
            break;
        case 'remote':
            filteredEmployees = employees.filter(e => e.location === 'Remote');
            break;
        case 'leaves':
            filteredEmployees = employees.filter(e => e.status === 'on-leave');
            break;
        default:
            filteredEmployees = [...employees];
    }

    currentPage = 1;
    renderTable();
}

// Change rows per page
function changeRowsPerPage() {
    rowsPerPage = parseInt(document.getElementById('rowsPerPage').value);
    currentPage = 1;
    renderTable();
}

// Change page
function changePage(direction) {
    const totalPages = Math.ceil(filteredEmployees.length / rowsPerPage);
    currentPage += direction;

    if (currentPage < 1) currentPage = 1;
    if (currentPage > totalPages) currentPage = totalPages;

    renderTable();
}

// Update pagination info
function updatePaginationInfo() {
    const total = filteredEmployees.length;
    const start = Math.min((currentPage - 1) * rowsPerPage + 1, total);
    const end = Math.min(currentPage * rowsPerPage, total);
    const totalPages = Math.ceil(total / rowsPerPage);

    document.getElementById('tableInfo').textContent = `Showing ${start}-${end} of ${total} employees`;
    document.getElementById('paginationInfo').textContent = `Page ${currentPage} of ${totalPages}`;

    // Update pagination buttons
    const pageControls = document.querySelector('.pagination-controls');
    pageControls.innerHTML = '';

    // Previous button
    const prevBtn = document.createElement('button');
    prevBtn.className = 'page-btn';
    prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prevBtn.onclick = () => changePage(-1);
    pageControls.appendChild(prevBtn);

    // Page buttons
    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = 'page-btn' + (i === currentPage ? ' active' : '');
        pageBtn.textContent = i;
        pageBtn.onclick = () => {
            currentPage = i;
            renderTable();
        };
        pageControls.appendChild(pageBtn);
    }

    // Next button
    const nextBtn = document.createElement('button');
    nextBtn.className = 'page-btn';
    nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    nextBtn.onclick = () => changePage(1);
    pageControls.appendChild(nextBtn);
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Modal Functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Set current employee
function setCurrentEmployee(employeeId) {
    currentEmployee = employees.find(e => e.id == employeeId);
    return currentEmployee;
}

// Manage Leave
function manageLeave(employeeId) {
    const employee = setCurrentEmployee(employeeId);
    if (!employee) return;

    document.getElementById('leaveEmployeeName').textContent = employee.name;
    document.getElementById('leaveEmployeeInfo').textContent = `ID: ${employee.employeeId} • Department: ${employee.department}`;

    const avatar = document.getElementById('leaveEmployeeAvatar');
    const initials = employee.name.split(' ').map(n => n[0]).join('').toUpperCase();
    avatar.innerHTML = employee.avatar ? `<img src="${employee.avatar}" alt="${employee.name}">` : initials;

    // Set default dates
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);

    document.getElementById('leaveStartDate').valueAsDate = today;
    document.getElementById('leaveEndDate').valueAsDate = tomorrow;

    // Reset leave type selection and update balances
    document.querySelectorAll('.leave-type').forEach(el => el.classList.remove('selected'));
    document.querySelector('.leave-type.paid').classList.add('selected');
    selectedLeaveType = 'paid';
    
    // Update leave balances
    document.querySelector('.leave-type.paid .leave-days').textContent = `Balance: ${employee.leaveBalancePaid} days`;
    document.querySelector('.leave-type.sick .leave-days').textContent = `Balance: ${employee.leaveBalanceSick} days`;
    document.querySelector('.leave-type.unpaid .leave-days').textContent = 'Unlimited';

    calculateLeaveDays();
    openModal('leaveModal');
}

function selectLeaveType(type) {
    selectedLeaveType = type;
    document.querySelectorAll('.leave-type').forEach(el => el.classList.remove('selected'));
    document.querySelector(`.leave-type.${type}`).classList.add('selected');
}

function calculateLeaveDays() {
    const startDate = new Date(document.getElementById('leaveStartDate').value);
    const endDate = new Date(document.getElementById('leaveEndDate').value);

    if (startDate && endDate && endDate >= startDate) {
        const timeDiff = endDate.getTime() - startDate.getTime();
        const dayDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
        document.getElementById('leaveDaysCount').textContent = `Total days: ${dayDiff}`;
    } else {
        document.getElementById('leaveDaysCount').textContent = 'Total days: 0';
    }
}

async function submitLeaveRequest() {
    const startDate = document.getElementById('leaveStartDate').value;
    const endDate = document.getElementById('leaveEndDate').value;
    const reason = document.getElementById('leaveReason').value;

    if (!startDate || !endDate || !reason) {
        alert('Please fill all required fields');
        return;
    }

    try {
        const response = await fetch('../../../../server/api/hrm/employees/employee-list.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'leave',
                employee_id: currentEmployee.id,
                start_date: startDate,
                end_date: endDate,
                reason: reason,
                leave_type: selectedLeaveType,
                contact: document.getElementById('leaveContact').value
            })
        });
        
        const result = await response.json();
        if (result.success) {
            currentEmployee.status = 'on-leave';
            updateStats();
            renderTable();
            alert(`Leave request submitted for ${currentEmployee.name}`);
            closeModal('leaveModal');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error submitting leave request');
    }
}

// Suspend Employee
function suspendEmployee(employeeId) {
    const employee = setCurrentEmployee(employeeId);
    if (!employee) return;

    document.getElementById('suspendEmployeeName').textContent = employee.name;
    document.getElementById('suspendEmployeeInfo').textContent = `ID: ${employee.employeeId} • Department: ${employee.department}`;

    const avatar = document.getElementById('suspendEmployeeAvatar');
    const initials = employee.name.split(' ').map(n => n[0]).join('').toUpperCase();
    avatar.innerHTML = employee.avatar ? `<img src="${employee.avatar}" alt="${employee.name}">` : initials;

    // Set default dates
    const today = new Date();
    const nextWeek = new Date(today);
    nextWeek.setDate(nextWeek.getDate() + 7);

    document.getElementById('suspendStartDate').valueAsDate = today;
    document.getElementById('suspendEndDate').valueAsDate = nextWeek;

    openModal('suspendModal');
}

async function submitSuspension() {
    const startDate = document.getElementById('suspendStartDate').value;
    const endDate = document.getElementById('suspendEndDate').value;
    const type = document.getElementById('suspensionType').value;
    const reason = document.getElementById('suspendReason').value;

    if (!startDate || !endDate || !type || !reason) {
        alert('Please fill all required fields');
        return;
    }

    try {
        const response = await fetch('../../../../server/api/hrm/employees/employee-list.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'suspend',
                employee_id: currentEmployee.id,
                start_date: startDate,
                end_date: endDate,
                type: type,
                reason: reason,
                notes: document.getElementById('suspendNotes').value
            })
        });
        
        const result = await response.json();
        if (result.success) {
            currentEmployee.status = 'suspended';
            updateStats();
            renderTable();
            alert(`${currentEmployee.name} has been suspended`);
            closeModal('suspendModal');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error processing suspension');
    }
}

// Terminate Employee
function terminateEmployee(employeeId) {
    const employee = setCurrentEmployee(employeeId);
    if (!employee) return;

    document.getElementById('terminateEmployeeName').textContent = employee.name;
    document.getElementById('terminateEmployeeInfo').textContent = `ID: ${employee.employeeId} • Department: ${employee.department}`;

    const avatar = document.getElementById('terminateEmployeeAvatar');
    const initials = employee.name.split(' ').map(n => n[0]).join('').toUpperCase();
    avatar.innerHTML = employee.avatar ? `<img src="${employee.avatar}" alt="${employee.name}">` : initials;

    // Set default date to today
    document.getElementById('terminationDate').valueAsDate = new Date();

    openModal('terminateModal');
}

async function submitTermination() {
    const date = document.getElementById('terminationDate').value;
    const type = document.getElementById('terminationType').value;
    const notice = document.getElementById('noticePeriod').value;
    const reason = document.getElementById('terminationReason').value;

    if (!date || !type || !notice || !reason) {
        alert('Please fill all required fields');
        return;
    }

    if (confirm(`Are you sure you want to terminate ${currentEmployee.name}? This action cannot be undone.`)) {
        try {
            const response = await fetch('../../../../server/api/hrm/employees/employee-list.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'terminate',
                    employee_id: currentEmployee.id,
                    date: date,
                    type: type,
                    notice: notice,
                    reason: reason,
                    exit_notes: document.getElementById('exitInterview').value,
                    final_pay: document.getElementById('finalPay').checked,
                    gratuity: document.getElementById('gratuityPayment').checked,
                    assets_returned: document.getElementById('returnAssets').checked
                })
            });
            
            const result = await response.json();
            if (result.success) {
                currentEmployee.status = 'terminated';
                updateStats();
                renderTable();
                alert(`${currentEmployee.name} has been terminated`);
                closeModal('terminateModal');
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Error processing termination');
        }
    }
}

// Manage Leave Balance
function manageLeaveBalance(employeeId) {
    const employee = setCurrentEmployee(employeeId);
    if (!employee) return;

    document.getElementById('balanceEmployeeName').textContent = employee.name;
    document.getElementById('balanceEmployeeInfo').textContent = `ID: ${employee.employeeId} • Department: ${employee.department}`;

    const avatar = document.getElementById('balanceEmployeeAvatar');
    const initials = employee.name.split(' ').map(n => n[0]).join('').toUpperCase();
    avatar.innerHTML = employee.avatar ? `<img src="${employee.avatar}" alt="${employee.name}">` : initials;

    // Update current balance display
    updateBalanceDisplay();
    
    openModal('leaveBalanceModal');
}

function updateBalanceDisplay() {
    const leaveType = document.getElementById('balanceLeaveType').value;
    let balance;
    
    switch(leaveType) {
        case 'paid':
            balance = currentEmployee.leaveBalancePaid;
            break;
        case 'sick':
            balance = currentEmployee.leaveBalanceSick;
            break;
        case 'unpaid':
            balance = currentEmployee.leaveBalanceUnpaid;
            break;
    }
    
    document.getElementById('currentBalance').textContent = `${balance} days`;
}

async function updateLeaveBalance() {
    const leaveType = document.getElementById('balanceLeaveType').value;
    const operation = document.getElementById('balanceOperation').value;
    const amount = document.getElementById('balanceAmount').value;

    if (!amount) {
        alert('Please enter an amount');
        return;
    }

    try {
        const response = await fetch('../../../../server/api/hrm/employees/update-leave-balance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                employee_id: currentEmployee.id,
                leave_type: leaveType,
                amount: parseInt(amount),
                operation: operation
            })
        });
        
        const result = await response.json();
        if (result.success) {
            // Update local data
            switch(leaveType) {
                case 'paid':
                    currentEmployee.leaveBalancePaid = result.new_balance;
                    break;
                case 'sick':
                    currentEmployee.leaveBalanceSick = result.new_balance;
                    break;
                case 'unpaid':
                    currentEmployee.leaveBalanceUnpaid = result.new_balance;
                    break;
            }
            
            updateBalanceDisplay();
            alert(`Leave balance updated successfully. New balance: ${result.new_balance} days`);
            closeModal('leaveBalanceModal');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error updating leave balance');
    }
}

// Change Status
function changeStatus(employeeId) {
    const employee = setCurrentEmployee(employeeId);
    if (!employee) return;

    document.getElementById('statusEmployeeName').textContent = employee.name;
    document.getElementById('statusEmployeeInfo').innerHTML = `ID: ${employee.employeeId} • Current Status: ${getStatusBadge(employee.status)}`;

    const avatar = document.getElementById('statusEmployeeAvatar');
    const initials = employee.name.split(' ').map(n => n[0]).join('').toUpperCase();
    avatar.innerHTML = employee.avatar ? `<img src="${employee.avatar}" alt="${employee.name}">` : initials;

    // Set effective date to today
    document.getElementById('statusEffectiveDate').valueAsDate = new Date();

    // Load status history
    loadStatusHistory(employeeId);

    openModal('statusModal');
}

function loadStatusHistory(employeeId) {
    const historyList = document.getElementById('statusHistoryList');
    historyList.innerHTML = `
                <div class="history-item">
                    <span class="status status-active">Active</span>
                    <span class="reason">Hired as ${currentEmployee.position}</span>
                    <span class="date">${formatDate(currentEmployee.hireDate)}</span>
                </div>
            `;

    // Add other status changes if they exist
    if (currentEmployee.suspensionStart) {
        historyList.innerHTML += `
                    <div class="history-item">
                        <span class="status status-suspended">Suspended</span>
                        <span class="reason">${currentEmployee.suspensionReason || 'Suspension'}</span>
                        <span class="date">${formatDate(currentEmployee.suspensionStart)}</span>
                    </div>
                `;
    }

    if (currentEmployee.leaveStart) {
        historyList.innerHTML += `
                    <div class="history-item">
                        <span class="status status-on-leave">On Leave</span>
                        <span class="reason">${currentEmployee.leaveReason || 'Leave'}</span>
                        <span class="date">${formatDate(currentEmployee.leaveStart)}</span>
                    </div>
                `;
    }
}

async function submitStatusChange() {
    const newStatus = document.getElementById('newStatus').value;
    const effectiveDate = document.getElementById('statusEffectiveDate').value;
    const reason = document.getElementById('statusChangeReason').value;

    if (!newStatus || !reason) {
        alert('Please fill all required fields');
        return;
    }

    try {
        const response = await fetch('../../../../server/api/hrm/employees/employee-list.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'status',
                employee_id: currentEmployee.id,
                status: newStatus,
                effective_date: effectiveDate,
                reason: reason
            })
        });
        
        const result = await response.json();
        if (result.success) {
            currentEmployee.status = newStatus;
            updateStats();
            renderTable();
            alert(`${currentEmployee.name}'s status updated to ${newStatus}`);
            closeModal('statusModal');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error updating status');
    }
}

// Other functions
function viewEmployee(employeeId) {
    window.location.href = `employee-add.php?id=${employeeId}&mode=view`;
}

function editEmployee(employeeId) {
    window.location.href = `employee-add.php?id=${employeeId}&mode=edit`;
}

async function deleteEmployee(employeeId) {
    const employee = employees.find(e => e.id === employeeId);
    if (!employee) return;
    
    if (!confirm(`Are you sure you want to delete ${employee.name}? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch('../../../../server/api/hrm/employees/employee-delete.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: employeeId })
        });
        
        const result = await response.json();
        if (result.success) {
            alert('Employee deleted successfully');
            await loadEmployees();
            updateStats();
            renderTable();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error deleting employee');
    }
}

function showAddEmployeeModal() {
    window.location.href="employee-add.php"
}

function printWithFilters() {
    const search = document.getElementById('searchInput').value;
    const department = document.getElementById('departmentFilter').value;
    const position = document.getElementById('positionFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (department) params.append('department', department);
    if (position) params.append('position', position);
    if (status) params.append('status', status);
    
    window.open('print.php?' + params.toString(), '_blank');
}



// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    initTable();
    
    // Add event listener for leave type change in balance modal
    if (document.getElementById('balanceLeaveType')) {
        document.getElementById('balanceLeaveType').addEventListener('change', updateBalanceDisplay);
    }
});

// Set today's date for all date inputs
const today = new Date().toISOString().split('T')[0];
document.querySelectorAll('input[type="date"]').forEach(input => {
    if (!input.value) {
        input.value = today;
    }
});

// Set leave dates to today and tomorrow
const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
const tomorrowStr = tomorrow.toISOString().split('T')[0];

if (document.getElementById('leaveEndDate')) {
    document.getElementById('leaveEndDate').value = tomorrowStr;
}