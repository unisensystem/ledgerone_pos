// Sample employee data (will be fetched from API)
let employees = [];
let paymentMethods = [];
let bankAccounts = [];
let companies = [];

// Fetch companies
fetch('../../../../server/api/financial_reports/cash_flow/get-companies.php')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            companies = data.data || [];
            const sel = document.getElementById('companyId');
            companies.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.company_name;
                sel.appendChild(opt);
            });
            if (companies.length === 1) sel.value = companies[0].id;
        }
    })
    .catch(err => console.error('Error fetching companies:', err));

// Fetch employees
fetch('../../../../server/api/hrm/employees/employee-list.php')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            employees = data.employees || [];
            populateEmployeeDropdown();
        }
    })
    .catch(err => console.error('Error fetching employees:', err));

// Fetch payment methods
fetch('../../../../server/api/hrm/payroll/payment-methods.php')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            paymentMethods = data.payment_methods || [];
            console.log('Payment methods loaded:', paymentMethods);
        }
    })
    .catch(err => console.error('Error fetching payment methods:', err));

// Fetch bank accounts
fetch('../../../../server/api/hrm/payroll/bank-accounts.php')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            bankAccounts = data.bank_accounts || [];
            console.log('Bank accounts loaded:', bankAccounts);
        }
    })
    .catch(err => console.error('Error fetching bank accounts:', err));

// DOM elements
const dateInput = document.getElementById('date');
const payrollIdInput = document.getElementById('payrollId');
const tableBody = document.getElementById('tableBody');
const addRowBtn = document.getElementById('addRowBtn');
const balanceNotification = document.getElementById('balanceNotification');
const balanceAmount = document.getElementById('balanceAmount');
const closeNotification = document.getElementById('closeNotification');
const employeeDropdown = document.getElementById('employeeDropdown');
const employeeSearch = document.getElementById('employeeSearch');
const employeeItems = document.getElementById('employeeItems');
const typeDropdown = document.getElementById('typeDropdown');
const cancelBtn = document.getElementById('cancelBtn');
const submitBtn = document.getElementById('submitBtn');

// Salary rows are auto-calculated against the calendar month that the
// payroll Date falls in (1st to last day of that month) — there's no
// separate Attendance Period field on this form anymore; the standalone
// Leave module page is the single source of truth for leave/overtime.
function getPayrollPeriod() {
    const d = dateInput.value ? new Date(dateInput.value) : new Date();
    const year = d.getFullYear();
    const month = d.getMonth();
    const start = new Date(year, month, 1);
    const end = new Date(year, month + 1, 0);
    const fmt = (dt) => dt.toISOString().split('T')[0];
    return { start: fmt(start), end: fmt(end) };
}

// Initialize form
function initializeForm() {
    const urlParams = new URLSearchParams(window.location.search);
    const viewId = urlParams.get('view');
    const editId = urlParams.get('edit');

    if (viewId || editId) {
        loadPayrollData(viewId || editId, !!viewId);
    } else {
        // Set current date
        const today = new Date().toISOString().split('T')[0];
        dateInput.value = today;

        // Generate payroll ID from backend
        fetch('../../../../server/api/hrm/payroll/generate-payroll-id.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    payrollIdInput.value = data.payroll_code;
                }
            })
            .catch(err => console.error('Error generating payroll ID:', err));

        // Wait for data to load before adding first row
        setTimeout(() => {
            addTableRow();
        }, 800);
    }

    // Populate employee dropdown
    populateEmployeeDropdown();

    // Add event listeners
    addEventListeners();
}

// Load payroll data for view/edit
let editPayrollId = null;

function loadPayrollData(id, isViewMode) {
    if (!isViewMode) {
        editPayrollId = id;
    }
    fetch(`../../../../server/api/hrm/payroll/payroll-get.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const payroll = data.payroll;
                dateInput.value = payroll.payroll_date;
                payrollIdInput.value = payroll.payroll_code;
                if (payroll.company_id) {
                    document.getElementById('companyId').value = payroll.company_id;
                }

                // Wait for data to load
                setTimeout(() => {
                    payroll.items.forEach((item, index) => {
                        addTableRow();
                    });

                    setTimeout(() => {
                        const rows = tableBody.querySelectorAll('tr');
                        payroll.items.forEach((item, index) => {
                            if (rows[index]) {
                                const row = rows[index];
                                row.querySelector('.employee-code').value = `${item.employee_id} - ${item.employee_name}`;
                                row.querySelector('.employee-id').value = item.employee_id;
                                row.querySelector('.type').value = item.type;
                                row.querySelector('.payment-method').value = item.payment_method_id;
                                row.querySelector('.bank-account').value = item.bank_account_id || '';
                                row.querySelector('.cheque-date').value = item.cheque_date || '';
                                row.querySelector('.description').value = item.description || '';
                                row.querySelector('.amount').value = item.amount;

                                // Show the Details button for Salary rows, matching the
                                // type-dropdown-selection behavior (see typeDropdown handler).
                                const detailsBtn = row.querySelector('[data-action="details"]');
                                if (detailsBtn) detailsBtn.style.display = item.type === 'Salary' ? 'inline-flex' : 'none';

                                // If this item already carries a breakdown (from auto-calculate
                                // at save time), restore it onto the row so re-submitting
                                // without recalculating doesn't drop it.
                                if (item.daily_rate !== null && item.daily_rate !== undefined) {
                                    row.dataset.paidLeaveDays = item.paid_leave_days || 0;
                                    row.dataset.unpaidLeaveDays = item.unpaid_leave_days || 0;
                                    row.dataset.dailyRate = item.daily_rate;
                                    row.dataset.grossAmount = item.gross_amount;
                                    row.dataset.deductionAmount = item.deduction_amount;
                                    row.dataset.otHours = item.ot_hours || 0;
                                    row.dataset.otAmount = item.ot_amount || 0;
                                    const hint = row.querySelector('.breakdown-hint');
                                    hint.style.display = 'block';
                                    hint.textContent = `Paid Leave ${item.paid_leave_days || 0} · Unpaid Leave ${item.unpaid_leave_days || 0} · `
                                        + `Deduction ${currencySymbol}${Number(item.deduction_amount).toFixed(2)} (@ ${currencySymbol}${Number(item.daily_rate).toFixed(2)}/day)`
                                        + (item.ot_hours > 0 ? ` · Overtime ${item.ot_hours} hrs = +${currencySymbol}${Number(item.ot_amount).toFixed(2)}` : '');
                                    // Day-by-day leave/OT rows aren't stored with a saved payroll
                                    // item (only the totals are), so "View Details" on a loaded row
                                    // re-fetches them fresh from calculate-suggested.php on click.
                                }

                                // Trigger payment method change to show/hide fields
                                row.querySelector('.payment-method').dispatchEvent(new Event('change'));
                            }
                        });
                    }, 200);

                    if (isViewMode) {
                        // Disable all inputs in view mode
                        document.querySelectorAll('input, select, button').forEach(el => {
                            if (!el.id || el.id !== 'cancelBtn') el.disabled = true;
                        });
                        submitBtn.style.display = 'none';
                    }
                }, 1200);
            } else {
                alert('Error: ' + data.message);
                window.location.href = 'payroll-list.php';
            }
        })
        .catch(err => {
            alert('Error loading payroll: ' + err.message);
            window.location.href = 'payroll-list.php';
        });
}

// Add a new row to the payroll table
function addTableRow() {
    const rowIndex = tableBody.children.length;
    const row = document.createElement('tr');

    let paymentMethodOptions = '';
    if (paymentMethods && paymentMethods.length > 0) {
        paymentMethodOptions = paymentMethods.map(method =>
            `<option value="${method.id}">${method.name}</option>`
        ).join('');
    }

    let bankAccountOptions = '';
    if (bankAccounts && bankAccounts.length > 0) {
        bankAccountOptions = bankAccounts.map(account =>
            `<option value="${account.id}">${account.name}</option>`
        ).join('');
    }

    row.innerHTML = `
                <td>
                    <div class="dropdown">
                        <input type="text" class="employee-code" placeholder="Select employee" readonly data-row="${rowIndex}">
                        <input type="hidden" class="employee-id" data-row="${rowIndex}">
                    </div>
                </td>
                <td>
                    <div class="dropdown">
                        <input type="text" class="type" placeholder="Select type" readonly data-row="${rowIndex}">
                    </div>
                </td>
                <td>
                    <select class="payment-method">
                        <option value="">Select method</option>
                        ${paymentMethodOptions}
                    </select>
                </td>
                <td>
                    <select class="bank-account">
                        <option value="">Select account</option>
                        ${bankAccountOptions}
                    </select>
                </td>
                <td>
                    <input type="date" class="cheque-date">
                </td>
                <td>
                    <input type="text" class="description" placeholder="Enter description">
                </td>
                <td>
                    <input type="number" class="amount" placeholder="0.00" min="0" step="0.01">
                    <div class="helper-text breakdown-hint" style="display:none;"></div>
                </td>
                <td>
                    <div class="table-actions">
                        <button class="btn btn-icon btn-details" data-action="details" title="View this month's approved leave & overtime (Salary rows only)" style="display:none;">
                            <i class="fas fa-list-ul"></i>
                        </button>
                        <button class="btn btn-icon btn-add" data-action="add" title="Add row">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn btn-icon btn-remove" data-action="remove" title="Remove row" ${rowIndex === 0 ? 'disabled' : ''}>
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </td>
            `;
    tableBody.appendChild(row);

    // Add event listeners to the new row
    addRowEventListeners(row, rowIndex);
}

// Populate employee dropdown
function populateEmployeeDropdown() {
    if (!employeeItems) return;
    employeeItems.innerHTML = '';
    employees.forEach(employee => {
        const item = document.createElement('div');
        item.className = 'dropdown-item';
        item.textContent = `${employee.employee_id} - ${employee.full_name}`;
        item.dataset.code = employee.employee_id;
        item.dataset.name = employee.full_name;
        item.dataset.balance = employee.opening_balance || 0;
        employeeItems.appendChild(item);
    });
}

// Add event listeners
function addEventListeners() {
    // Add row button
    addRowBtn.addEventListener('click', () => addTableRow());

    // Close notification button
    closeNotification.addEventListener('click', () => {
        balanceNotification.classList.remove('show');
    });

    // Employee search
    employeeSearch.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const items = employeeItems.querySelectorAll('.dropdown-item');

        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.matches('.employee-code, .employee-name, .type') &&
            !e.target.closest('.dropdown-content')) {
            employeeDropdown.classList.remove('show');
            typeDropdown.classList.remove('show');
        }
    });

    // Form action buttons
    cancelBtn.addEventListener('click', () => {
        if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
            window.location.href = 'payroll-list.php';
        }
    });

    submitBtn.addEventListener('click', () => {
        if (validateForm()) {
            submitPayroll();
        }
    });

    // If the payroll Date changes after a Salary row already has an
    // auto-filled amount, its figure was computed for the old month — clear
    // the stale breakdown so it doesn't silently look current.
    dateInput.addEventListener('change', () => {
        tableBody.querySelectorAll('tr').forEach(row => autoFillSalaryIfReady(row));
    });
}

// If a row has both an employee and "Salary" selected, fetch and fill the
// suggested amount automatically — no manual "Calculate" click needed.
function autoFillSalaryIfReady(row) {
    const employeeId = row.querySelector('.employee-id').value;
    const type = row.querySelector('.type').value;
    if (employeeId && type === 'Salary') {
        calculateSuggestedSalary(row, true);
    }
}

// Add event listeners to a table row
function addRowEventListeners(row, rowIndex) {
    const employeeCodeInput = row.querySelector('.employee-code');

    employeeCodeInput.addEventListener('click', (e) => {
        e.stopPropagation();
        const rect = e.target.getBoundingClientRect();
        employeeDropdown.style.top = `${rect.bottom}px`;
        employeeDropdown.style.left = `${rect.left}px`;
        employeeDropdown.style.minWidth = `${rect.width}px`;
        employeeDropdown.dataset.row = rowIndex;
        employeeDropdown.classList.add('show');
        setTimeout(() => employeeSearch.focus(), 100);
    });

    const typeInput = row.querySelector('.type');
    typeInput.addEventListener('click', (e) => {
        e.stopPropagation();
        const rect = e.target.getBoundingClientRect();
        typeDropdown.style.top = `${rect.bottom}px`;
        typeDropdown.style.left = `${rect.left}px`;
        typeDropdown.style.minWidth = `${rect.width}px`;
        typeDropdown.dataset.row = rowIndex;
        typeDropdown.classList.add('show');
    });

    // Payment method change (show/hide bank account and cheque date)
    const paymentMethodSelect = row.querySelector('.payment-method');
    const bankAccountSelect = row.querySelector('.bank-account');
    const chequeDateInput = row.querySelector('.cheque-date');

    paymentMethodSelect.addEventListener('change', () => {
        const methodId = paymentMethodSelect.value;

        // Show bank account if payment method is not Cash (7)
        if (methodId && methodId != '7') {
            bankAccountSelect.style.display = 'block';
        } else {
            bankAccountSelect.style.display = 'none';
            bankAccountSelect.value = '';
        }

        // Show cheque date if payment method is Cheque (6)
        if (methodId == '6') {
            chequeDateInput.style.display = 'block';
        } else {
            chequeDateInput.style.display = 'none';
            chequeDateInput.value = '';
        }
    });

    // Initially hide bank account and cheque date
    bankAccountSelect.style.display = 'none';
    chequeDateInput.style.display = 'none';

    // Action buttons
    const addBtn = row.querySelector('[data-action="add"]');
    const removeBtn = row.querySelector('[data-action="remove"]');
    const detailsBtn = row.querySelector('[data-action="details"]');

    detailsBtn.addEventListener('click', () => viewLeaveDetails(row));
    addBtn.addEventListener('click', () => addTableRow());
    removeBtn.addEventListener('click', () => {
        if (tableBody.children.length > 1) {
            row.remove();
            updateRowIndices();
        }
    });
}

// Update row indices after removal
function updateRowIndices() {
    const rows = tableBody.querySelectorAll('tr');
    rows.forEach((row, index) => {
        const inputs = row.querySelectorAll('input[data-row]');
        inputs.forEach(input => {
            input.dataset.row = index;
        });

        // Enable/disable remove button for first row
        const removeBtn = row.querySelector('[data-action="remove"]');
        removeBtn.disabled = index === 0;
    });
}

// Employee dropdown item selection
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('dropdown-item') && e.target.closest('#employeeDropdown')) {
        const rowIndex = employeeDropdown.dataset.row;
        const rows = tableBody.querySelectorAll('tr');

        if (rows[rowIndex]) {
            const row = rows[rowIndex];
            const employeeCodeInput = row.querySelector('.employee-code');
            const employeeIdInput = row.querySelector('.employee-id');

            employeeCodeInput.value = e.target.textContent;
            employeeIdInput.value = e.target.dataset.code;

            balanceAmount.textContent = `${currencySymbol}${parseFloat(e.target.dataset.balance).toFixed(2)}`;
            balanceNotification.classList.add('show');

            setTimeout(() => {
                balanceNotification.classList.remove('show');
            }, 8000);

            employeeItems.querySelectorAll('.dropdown-item').forEach(item => {
                item.classList.remove('selected');
            });
            e.target.classList.add('selected');

            // Employee is set — if the row's type is already "Salary", fetch
            // the suggested amount right away.
            autoFillSalaryIfReady(row);
        }

        employeeDropdown.classList.remove('show');
    }
});

// Type dropdown item selection
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('dropdown-item') && e.target.closest('#typeDropdown')) {
        const rowIndex = typeDropdown.dataset.row;
        const rows = tableBody.querySelectorAll('tr');

        if (rows[rowIndex]) {
            const row = rows[rowIndex];
            const typeInput = row.querySelector('.type');

            // Set value
            typeInput.value = e.target.dataset.value;

            // "View Details" only makes sense for a Salary row.
            const detailsBtn = row.querySelector('[data-action="details"]');
            if (detailsBtn) detailsBtn.style.display = typeInput.value === 'Salary' ? 'inline-flex' : 'none';

            // Type is now "Salary" (or changed away from it) — fetch the
            // suggested amount if an employee is already selected.
            if (typeInput.value === 'Salary') {
                autoFillSalaryIfReady(row);
            }
        }

        // Close dropdown
        typeDropdown.classList.remove('show');
    }
});

// Reads the row's selected employee + the calendar month of the payroll
// Date, and fills the Amount field with a suggested net Salary computed from
// APPROVED leave and APPROVED overtime for that month (see
// server/api/hrm/payroll/calculate-suggested.php). HR can still edit the
// amount afterward — this is a starting point, not a lock-in.
// `silent` suppresses alert popups for the automatic (non-button) triggers.
async function calculateSuggestedSalary(row, silent = false) {
    const employeeId = row.querySelector('.employee-id').value;
    const hint = row.querySelector('.breakdown-hint');

    if (!employeeId) { if (!silent) alert('Please select an employee for this row first.'); return; }
    if (!dateInput.value) { if (!silent) alert('Please set the payroll Date first — it decides which month\'s leave/overtime is used.'); return; }

    const period = getPayrollPeriod();

    try {
        const res = await fetch(`../../../../server/api/hrm/payroll/calculate-suggested.php?employee_id=${encodeURIComponent(employeeId)}&period_start=${period.start}&period_end=${period.end}`);
        const data = await res.json();
        if (!data.success) { if (!silent) alert('Error: ' + data.message); return; }

        row.querySelector('.amount').value = data.suggested_net_amount;
        row.dataset.paidLeaveDays = data.attendance.paid_leave_days;
        row.dataset.unpaidLeaveDays = data.attendance.unpaid_leave_days;
        row.dataset.dailyRate = data.daily_rate;
        row.dataset.grossAmount = data.gross_amount;
        row.dataset.deductionAmount = data.unpaid_leave_deduction;
        row.dataset.otHours = data.overtime.total_hours;
        row.dataset.otAmount = data.overtime.total_amount;
        row.dataset.leaveRecords = JSON.stringify(data.leave_records || []);
        row.dataset.otRecords = JSON.stringify(data.overtime.records || []);
        row.dataset.pendingLeaveCount = data.attendance.pending_leave_requests;

        hint.style.display = 'block';
        hint.textContent = `Paid Leave ${data.attendance.paid_leave_days} · Unpaid Leave ${data.attendance.unpaid_leave_days} · `
            + `Deduction ${currencySymbol}${Number(data.unpaid_leave_deduction).toFixed(2)} (@ ${currencySymbol}${Number(data.daily_rate).toFixed(2)}/day)`
            + (data.overtime.total_hours > 0 ? ` · Overtime ${data.overtime.total_hours} hrs = +${currencySymbol}${Number(data.overtime.total_amount).toFixed(2)}` : '')
            + (data.attendance.pending_leave_requests > 0 ? ` · ${data.attendance.pending_leave_requests} pending leave request(s) not counted yet` : '');
    } catch (err) {
        if (!silent) alert('Error calculating suggested salary');
    }
}

// Opens the popup for a row: which approved leave dates fell in this month
// (paid/unpaid) and which approved overtime was logged, for the selected
// employee. Reuses data already fetched automatically; if the row somehow
// hasn't been calculated yet, runs it first so Amount, the hint, and the
// popup all stay consistent.
async function viewLeaveDetails(row) {
    const employeeId = row.querySelector('.employee-id').value;

    if (!employeeId) { alert('Please select an employee for this row first.'); return; }
    if (!dateInput.value) { alert('Please set the payroll Date first.'); return; }

    if (!row.dataset.leaveRecords) {
        await calculateSuggestedSalary(row);
    }
    if (!row.dataset.leaveRecords) return; // calculate failed — alert already shown

    const employeeLabel = row.querySelector('.employee-code').value;
    const leaveRecords = JSON.parse(row.dataset.leaveRecords || '[]');
    const otRecords = JSON.parse(row.dataset.otRecords || '[]');
    const pendingCount = Number(row.dataset.pendingLeaveCount || 0);
    showDetailModal(employeeLabel, leaveRecords, otRecords, pendingCount);
}

// Singleton detail popup — built once, reused for every row.
let detailModal = null;
function ensureDetailModal() {
    if (detailModal) return detailModal;
    detailModal = document.createElement('div');
    detailModal.id = 'attendanceDetailModal';
    detailModal.className = 'detail-modal-overlay';
    detailModal.innerHTML = `
        <div class="detail-modal">
            <div class="detail-modal-header">
                <h3 id="detailModalTitle">Leave & Overtime</h3>
                <button type="button" class="detail-modal-close" id="detailModalClose"><i class="fas fa-times"></i></button>
            </div>
            <div class="detail-modal-body" id="detailModalBody"></div>
        </div>
    `;
    document.body.appendChild(detailModal);
    detailModal.querySelector('#detailModalClose').addEventListener('click', () => detailModal.classList.remove('show'));
    detailModal.addEventListener('click', (e) => {
        if (e.target === detailModal) detailModal.classList.remove('show');
    });
    return detailModal;
}

function showDetailModal(employeeLabel, leaveRecords, otRecords, pendingCount) {
    const modal = ensureDetailModal();
    modal.querySelector('#detailModalTitle').textContent = `${employeeLabel} — Leave & Overtime`;
    const body = modal.querySelector('#detailModalBody');

    // Find the row to get breakdown numbers
    const rows = tableBody.querySelectorAll('tr');
    let grossAmount = 0, unpaidDeduction = 0, otAmount = 0, netAmount = 0, dailyRate = 0, paidLeaveDays = 0, unpaidLeaveDays = 0, otHours = 0;
    rows.forEach(row => {
        const empCode = row.querySelector('.employee-code').value;
        if (empCode && employeeLabel.includes(empCode.split(' - ')[0])) {
            grossAmount     = Number(row.dataset.grossAmount || 0);
            unpaidDeduction = Number(row.dataset.deductionAmount || 0);
            otAmount        = Number(row.dataset.otAmount || 0);
            dailyRate       = Number(row.dataset.dailyRate || 0);
            paidLeaveDays   = Number(row.dataset.paidLeaveDays || 0);
            unpaidLeaveDays = Number(row.dataset.unpaidLeaveDays || 0);
            otHours         = Number(row.dataset.otHours || 0);
            netAmount       = grossAmount - unpaidDeduction + otAmount;
        }
    });

    let html = '';

    // ── Summary Card ──
    html += `
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; margin-bottom:16px;">
            <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.6px; margin-bottom:10px;">Salary Breakdown</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                <div style="background:white; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px;">
                    <div style="font-size:11px; color:#94a3b8; margin-bottom:3px;">Gross Salary</div>
                    <div style="font-size:15px; font-weight:700; color:#0f172a;">${currencySymbol} ${grossAmount.toLocaleString('en-PK', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                </div>
                <div style="background:white; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px;">
                    <div style="font-size:11px; color:#94a3b8; margin-bottom:3px;">Daily Rate</div>
                    <div style="font-size:15px; font-weight:700; color:#0f172a;">${currencySymbol} ${dailyRate.toLocaleString('en-PK', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
                </div>
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px 12px;">
                    <div style="font-size:11px; color:#94a3b8; margin-bottom:3px;">Unpaid Leave Deduction</div>
                    <div style="font-size:15px; font-weight:700; color:#dc2626;">
                        − ${currencySymbol} ${unpaidDeduction.toLocaleString('en-PK', {minimumFractionDigits:2, maximumFractionDigits:2})}
                    </div>
                    <div style="font-size:11px; color:#94a3b8; margin-top:2px;">${unpaidLeaveDays} day(s) × ${currencySymbol}${dailyRate.toFixed(2)}</div>
                </div>
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:10px 12px;">
                    <div style="font-size:11px; color:#94a3b8; margin-bottom:3px;">Overtime Added</div>
                    <div style="font-size:15px; font-weight:700; color:#16a34a;">
                        + ${currencySymbol} ${otAmount.toLocaleString('en-PK', {minimumFractionDigits:2, maximumFractionDigits:2})}
                    </div>
                    <div style="font-size:11px; color:#94a3b8; margin-top:2px;">${otHours} hour(s)</div>
                </div>
            </div>
            <!-- Net total -->
            <div style="margin-top:10px; padding:10px 12px; background:linear-gradient(135deg,#1f7bff,#1559b8); border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:13px; font-weight:600; color:rgba(255,255,255,.85);">Net Salary</span>
                <span style="font-size:18px; font-weight:800; color:white;">${currencySymbol} ${netAmount.toLocaleString('en-PK', {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
            </div>
        </div>`;

    // ── Approved Leave ──
    html += `<h4 style="margin:14px 0 8px; font-size:13px; color:#374151;">Approved Leave</h4>`;
    if (leaveRecords.length === 0) {
        html += `<p class="detail-empty">No approved leave this month.</p>`;
    } else {
        html += `<table class="detail-table">
            <thead><tr><th>Dates</th><th>Type</th><th>Total</th><th>Paid</th><th>Unpaid</th></tr></thead><tbody>`;
        leaveRecords.forEach(r => {
            const dates = r.start_date === r.end_date ? r.start_date : `${r.start_date} – ${r.end_date}`;
            html += `<tr>
                <td>${dates}</td>
                <td>${r.leave_type}</td>
                <td>${r.total_days} day(s)</td>
                <td style="color:#16a34a; font-weight:600;">${r.paid_days}</td>
                <td style="color:#dc2626; font-weight:600;">${r.unpaid_days}</td>
            </tr>`;
        });
        // Leave totals row
        const totalPaid   = leaveRecords.reduce((s, r) => s + r.paid_days, 0);
        const totalUnpaid = leaveRecords.reduce((s, r) => s + r.unpaid_days, 0);
        html += `<tr style="background:#f8fafc; font-weight:700; border-top:2px solid #e2e8f0;">
            <td colspan="2">Total</td>
            <td>${(totalPaid + totalUnpaid)} day(s)</td>
            <td style="color:#16a34a;">${totalPaid}</td>
            <td style="color:#dc2626;">${totalUnpaid}</td>
        </tr>`;
        html += `</tbody></table>`;
    }

    // ── Overtime ──
    html += `<h4 style="margin:14px 0 8px; font-size:13px; color:#374151;">Overtime</h4>`;
    if (otRecords.length === 0) {
        html += `<p class="detail-empty">No approved overtime this month.</p>`;
    } else {
        html += `<table class="detail-table">
            <thead><tr><th>Date</th><th>Hours</th><th>Rate</th><th>Amount</th></tr></thead><tbody>`;
        otRecords.forEach(r => {
            html += `<tr>
                <td>${r.date}</td>
                <td>${r.hours} hrs</td>
                <td>${r.rate_multiplier}x</td>
                <td style="color:#16a34a; font-weight:600;">+ ${currencySymbol}${Number(r.amount).toFixed(2)}</td>
            </tr>`;
        });
        // OT totals row
        const totalOtHrs = otRecords.reduce((s, r) => s + r.hours, 0);
        const totalOtAmt = otRecords.reduce((s, r) => s + r.amount, 0);
        html += `<tr style="background:#f8fafc; font-weight:700; border-top:2px solid #e2e8f0;">
            <td colspan="2">Total</td>
            <td>${totalOtHrs} hrs</td>
            <td style="color:#16a34a;">+ ${currencySymbol}${totalOtAmt.toFixed(2)}</td>
        </tr>`;
        html += `</tbody></table>`;
    }

    if (pendingCount > 0) {
        html += `<div style="margin-top:12px; padding:10px 12px; background:#fef3c7; border:1px solid #fde68a; border-radius:8px; font-size:13px; color:#92400e;">
            <i class="fas fa-triangle-exclamation"></i>
            <strong>${pendingCount}</strong> pending leave request(s) this month are not counted yet — approve them on the Leave page first.
        </div>`;
    }

    body.innerHTML = html;
    modal.classList.add('show');
}
// Form validation
function validateForm() {
    const rows = tableBody.querySelectorAll('tr');

    if (!dateInput.value) {
        alert('Please select a date.');
        dateInput.focus();
        return false;
    }

    if (rows.length === 0) {
        alert('Please add at least one payroll entry.');
        return false;
    }

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const employeeId = row.querySelector('.employee-id').value;
        const type = row.querySelector('.type').value;
        const paymentMethod = row.querySelector('.payment-method').value;
        const amount = row.querySelector('.amount').value;

        if (!employeeId) {
            alert(`Row ${i + 1}: Please select an employee.`);
            return false;
        }
        if (!type) {
            alert(`Row ${i + 1}: Please select a payroll type.`);
            return false;
        }
        if (!paymentMethod) {
            alert(`Row ${i + 1}: Please select a payment method.`);
            return false;
        }
        if (!amount || parseFloat(amount) <= 0) {
            alert(`Row ${i + 1}: Please enter a valid amount.`);
            return false;
        }
    }

    return true;
}

// Submit payroll
function submitPayroll() {
    const rows = tableBody.querySelectorAll('tr');
    const items = [];
    let anyCalculated = false;

    rows.forEach(row => {
        const employeeId = row.querySelector('.employee-id').value;
        const type = row.querySelector('.type').value;
        const paymentMethodId = row.querySelector('.payment-method').value;
        const bankAccountId = row.querySelector('.bank-account').value || null;
        const chequeDate = row.querySelector('.cheque-date').value || null;
        const description = row.querySelector('.description').value || null;
        const amount = parseFloat(row.querySelector('.amount').value);

        const item = {
            employee_code: employeeId,
            type: type,
            payment_method_id: paymentMethodId,
            bank_account_id: bankAccountId,
            cheque_date: chequeDate,
            description: description,
            amount: amount
        };

        // If this row was auto-filled from approved leave/overtime, carry the
        // breakdown along so it's stored with the payroll entry for later review.
        if (row.dataset.dailyRate !== undefined) {
            anyCalculated = true;
            item.paid_leave_days = Number(row.dataset.paidLeaveDays || 0);
            item.unpaid_leave_days = Number(row.dataset.unpaidLeaveDays || 0);
            item.daily_rate = Number(row.dataset.dailyRate);
            item.gross_amount = Number(row.dataset.grossAmount);
            item.deduction_amount = Number(row.dataset.deductionAmount);
            item.ot_hours = Number(row.dataset.otHours || 0);
            item.ot_amount = Number(row.dataset.otAmount || 0);
        }

        items.push(item);
    });

    const period = getPayrollPeriod();

    const payload = {
        payroll_date: dateInput.value,
        payroll_code: payrollIdInput.value,
        company_id: document.getElementById('companyId').value || null,
        period_start: period.start,
        period_end: period.end,
        items: items
    };

    // Payroll built from approved leave/overtime starts as a draft so HR can
    // review before it's locked in — existing manual-entry payrolls keep
    // defaulting to 'finalized' server-side (unchanged behavior) when this
    // is omitted.
    if (anyCalculated && !editPayrollId) {
        payload.status = 'draft';
    }

    if (editPayrollId) {
        payload.payroll_id = editPayrollId;
    }

    submitBtn.disabled = true;

    const apiUrl = editPayrollId
        ? '../../../../server/api/hrm/payroll/payroll-edit.php'
        : '../../../../server/api/hrm/payroll/payroll-add.php';

    fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Payroll submitted successfully!');
                window.location.href = 'payroll-list.php';
            } else {
                alert('Error: ' + data.message);
                submitBtn.disabled = false;
            }
        })
        .catch(err => {
            alert('Error submitting payroll: ' + err.message);
            submitBtn.disabled = false;
        });
}

// Styles for the "View Details" popup — injected here (rather than the CSS file)
// since the modal markup itself is also built entirely in JS.
const detailModalStyle = document.createElement('style');
detailModalStyle.textContent = `
    .detail-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.45);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    .detail-modal-overlay.show { display: flex; }
    .detail-modal {
        background: #fff;
        border-radius: 10px;
        width: min(560px, 92vw);
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0,0,0,.2);
    }
    .detail-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        border-bottom: 1px solid #e5e7eb;
        position: sticky;
        top: 0;
        background: #fff;
    }
    .detail-modal-header h3 { margin: 0; font-size: 16px; }
    .detail-modal-close { background: none; border: none; cursor: pointer; font-size: 16px; color: #6b7280; }
    .detail-modal-close:hover { color: #111827; }
    .detail-modal-body { padding: 14px 18px; }
    .detail-modal-body h4 { margin: 14px 0 8px; font-size: 13px; color: #374151; }
    .detail-modal-body h4:first-child { margin-top: 0; }
    .detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .detail-table th, .detail-table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
    .detail-table th { color: #6b7280; font-weight: 600; }
    .detail-empty { font-size: 13px; color: #9ca3af; margin: 0; }
`;
document.head.appendChild(detailModalStyle);

// Initialize the form when page loads
document.addEventListener('DOMContentLoaded', initializeForm);