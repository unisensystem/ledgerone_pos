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
let leaveTypesCache = [];
let selectedLeaveTypeId = null;

async function loadLeaveTypesForModal(employeeCode) {
    const [typesRes, balRes] = await Promise.all([
        fetch('../../../../server/api/hrm/leave/leave-types.php'),
        fetch(`../../../../server/api/hrm/leave/leave-balance.php?employee_id=${encodeURIComponent(employeeCode)}`)
    ]);
    const typesData = await typesRes.json();
    const balData = await balRes.json();
    leaveTypesCache = typesData.success ? typesData.leave_types : [];
    const balances = balData.success ? balData.balances : [];

    const container = document.getElementById('leaveTypeSelector');
    container.innerHTML = leaveTypesCache.map(lt => {
        const bal = balances.find(b => b.leave_type_id == lt.id);
        const balanceText = lt.track_balance
            ? `Balance: ${bal ? Number(bal.remaining).toFixed(1) : '0.0'} days`
            : (lt.is_paid ? 'Not balance-tracked' : 'Unpaid — salary deduction applies');
        const icon = lt.is_paid ? 'fa-sun' : 'fa-moon';
        return `<div class="leave-type" data-leave-type-id="${lt.id}" onclick="selectLeaveType(${lt.id})">
            <i class="fas ${icon}"></i>
            <div>${lt.name}</div>
            <div class="leave-days">${balanceText}</div>
        </div>`;
    }).join('');

    if (leaveTypesCache.length > 0) selectLeaveType(leaveTypesCache[0].id);
}

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
    document.getElementById('leaveReason').value = '';
    document.getElementById('leaveContact').value = '';
    document.getElementById('leaveIsHalfDay').checked = false;
    document.getElementById('leaveHalfDayPeriod').style.display = 'none';
    document.getElementById('leaveOverrideBox').style.display = 'none';
    document.getElementById('leaveOverride').checked = false;
    document.getElementById('leaveValidationWarning').style.display = 'none';

    loadLeaveTypesForModal(employee.employeeId).then(() => calculateLeaveDays());
    openModal('leaveModal');
}

function selectLeaveType(leaveTypeId) {
    selectedLeaveTypeId = leaveTypeId;
    document.querySelectorAll('.leave-type').forEach(el => el.classList.remove('selected'));
    const el = document.querySelector(`.leave-type[data-leave-type-id="${leaveTypeId}"]`);
    if (el) el.classList.add('selected');
}

function toggleHalfDay() {
    const isHalf = document.getElementById('leaveIsHalfDay').checked;
    document.getElementById('leaveHalfDayPeriod').style.display = isHalf ? 'inline-block' : 'none';
    if (isHalf) document.getElementById('leaveEndDate').value = document.getElementById('leaveStartDate').value;
    calculateLeaveDays();
}

// Rough client-side estimate only (excludes Sunday as a common default) —
// the server is the source of truth and recalculates against the tenant's
// actual weekly-off/holiday configuration when the request is submitted.
function calculateLeaveDays() {
    const startDate = new Date(document.getElementById('leaveStartDate').value);
    const endDate = new Date(document.getElementById('leaveEndDate').value);
    const isHalfDay = document.getElementById('leaveIsHalfDay').checked;

    if (startDate && endDate && endDate >= startDate) {
        let count = 0;
        const cursor = new Date(startDate);
        while (cursor <= endDate) {
            if (cursor.getDay() !== 0) count++; // default estimate: Sunday off
            cursor.setDate(cursor.getDate() + 1);
        }
        const total = isHalfDay ? 0.5 : count;
        document.getElementById('leaveDaysCount').textContent = `Estimated: ${total} day(s) (weekends/holidays excluded — server confirms exact count)`;
    } else {
        document.getElementById('leaveDaysCount').textContent = 'Total days: 0';
    }
}

async function submitLeaveRequest(overrideConfirmed) {
    const startDate = document.getElementById('leaveStartDate').value;
    const endDate = document.getElementById('leaveEndDate').value;
    const reason = document.getElementById('leaveReason').value;
    const isHalfDay = document.getElementById('leaveIsHalfDay').checked;

    if (!startDate || !endDate || !reason || !selectedLeaveTypeId) {
        alert('Please fill all required fields and select a leave type');
        return;
    }

    const override = document.getElementById('leaveOverride').checked;
    const overrideReason = document.getElementById('leaveOverrideReason').value;
    if (override && !overrideReason) {
        alert('Please enter a reason for the override');
        return;
    }

    try {
        const response = await fetch('../../../../server/api/hrm/leave/leave-request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                employee_id: currentEmployee.employeeId,
                leave_type_id: selectedLeaveTypeId,
                start_date: startDate,
                end_date: endDate,
                is_half_day: isHalfDay,
                half_day_period: isHalfDay ? document.getElementById('leaveHalfDayPeriod').value : null,
                reason: reason,
                contact: document.getElementById('leaveContact').value,
                override: override,
                override_reason: overrideReason
            })
        });

        const result = await response.json();
        if (result.success) {
            currentEmployee.status = result.status === 'approved' ? 'on-leave' : currentEmployee.status;
            await loadEmployees();
            renderTable();
            alert(result.message + (result.total_days ? ` (${result.total_days} day(s))` : ''));
            closeModal('leaveModal');
        } else {
            // Show the validation reason inline with an override option, instead of just an alert.
            const warnBox = document.getElementById('leaveValidationWarning');
            warnBox.style.display = 'flex';
            warnBox.textContent = '';
            const warnIcon = document.createElement('i');
            warnIcon.className = 'fas fa-exclamation-triangle';
            const warnText = document.createElement('span');
            warnText.textContent = result.message; // textContent — never render server text as HTML
            warnBox.appendChild(warnIcon);
            warnBox.appendChild(warnText);
            document.getElementById('leaveOverrideBox').style.display = 'block';
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
async function manageLeaveBalance(employeeId) {
    const employee = setCurrentEmployee(employeeId);
    if (!employee) return;

    document.getElementById('balanceEmployeeName').textContent = employee.name;
    document.getElementById('balanceEmployeeInfo').textContent = `ID: ${employee.employeeId} • Department: ${employee.department}`;

    const avatar = document.getElementById('balanceEmployeeAvatar');
    const initials = employee.name.split(' ').map(n => n[0]).join('').toUpperCase();
    avatar.innerHTML = employee.avatar ? `<img src="${employee.avatar}" alt="${employee.name}">` : initials;

    await loadBalanceBreakdown(employee.employeeId);
    openModal('leaveBalanceModal');
}

async function loadBalanceBreakdown(employeeCode) {
    const res = await fetch(`../../../../server/api/hrm/leave/leave-balance.php?employee_id=${encodeURIComponent(employeeCode)}`);
    const data = await res.json();
    if (!data.success) return;

    const body = document.getElementById('balanceBreakdownBody');
    body.innerHTML = '';
    data.balances.forEach(b => {
        const row = document.createElement('tr');
        const cells = [b.name, b.opening_balance, b.allocated, b.carry_forward, b.used, b.pending, b.adjustment, b.track_balance == 1 ? Number(b.remaining).toFixed(1) : 'Unlimited'];
        cells.forEach((val, i) => {
            const td = document.createElement('td');
            td.textContent = i === 0 ? val : (typeof val === 'number' ? Number(val).toFixed(1) : val);
            row.appendChild(td);
        });
        body.appendChild(row);
    });

    const select = document.getElementById('balanceLeaveType');
    select.innerHTML = '';
    data.balances.filter(b => b.track_balance == 1).forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.leave_type_id;
        opt.textContent = b.name;
        select.appendChild(opt);
    });

    renderBalanceHistory(data.transactions.filter(t => !select.value || t.leave_type_id == select.value));

    select.onchange = async () => {
        const res2 = await fetch(`../../../../server/api/hrm/leave/leave-balance.php?employee_id=${encodeURIComponent(employeeCode)}&leave_type_id=${select.value}`);
        const d2 = await res2.json();
        if (d2.success) renderBalanceHistory(d2.transactions);
    };
}

function renderBalanceHistory(transactions) {
    const body = document.getElementById('balanceHistoryBody');
    body.innerHTML = '';
    if (!transactions.length) {
        const row = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 4;
        td.textContent = 'No transactions yet';
        row.appendChild(td);
        body.appendChild(row);
        return;
    }
    transactions.slice(0, 15).forEach(t => {
        const row = document.createElement('tr');
        [t.transaction_date, t.transaction_type, Number(t.days).toFixed(1), t.reason || ''].forEach(val => {
            const td = document.createElement('td');
            td.textContent = val;
            row.appendChild(td);
        });
        body.appendChild(row);
    });
}

async function updateLeaveBalance() {
    const leaveTypeId = document.getElementById('balanceLeaveType').value;
    const operation = document.getElementById('balanceOperation').value;
    const amount = document.getElementById('balanceAmount').value;
    const reason = document.getElementById('balanceReason').value;

    if (!amount || !reason) {
        alert('Please enter the days and a reason');
        return;
    }

    try {
        const response = await fetch('../../../../server/api/hrm/employees/update-leave-balance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                employee_id: currentEmployee.employeeId,
                leave_type_id: leaveTypeId,
                amount: parseFloat(amount),
                operation: operation,
                reason: reason
            })
        });

        const result = await response.json();
        if (result.success) {
            document.getElementById('balanceAmount').value = '';
            document.getElementById('balanceReason').value = '';
            await loadBalanceBreakdown(currentEmployee.employeeId);
            alert(`Leave balance updated. New balance: ${result.new_balance} days`);
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