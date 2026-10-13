// DOM Elements
const currentDateEl = document.getElementById('currentDate');
const employeeIdEl = document.getElementById('employeeId');
const departmentEl = document.getElementById('department');
const filterDateFromEl = document.getElementById('filterDateFrom');
const filterDateToEl = document.getElementById('filterDateTo');
const filterEmployeeEl = document.getElementById('filterEmployee');
const filterDepartmentEl = document.getElementById('filterDepartment');
const scanBarcodeBtn = document.getElementById('scanBarcodeBtn');
const autoAttendanceEl = document.getElementById('autoAttendance');
const checkInInputEl = document.getElementById('checkInInput');
const checkOutInputEl = document.getElementById('checkOutInput');
const checkInTimeEl = document.getElementById('checkInTime');
const checkOutTimeEl = document.getElementById('checkOutTime');
const checkInStatusEl = document.getElementById('checkInStatus');
const checkOutStatusEl = document.getElementById('checkOutStatus');
const attendanceStatusEl = document.getElementById('attendanceStatus');
const gracePeriodEl = document.getElementById('gracePeriod');
const notesEl = document.getElementById('notes');
const checkInBtn = document.getElementById('checkInBtn');
const checkOutBtn = document.getElementById('checkOutBtn');
const saveBtn = document.getElementById('saveBtn');
const clearBtn = document.getElementById('clearBtn');
const attendanceTableBody = document.getElementById('attendanceTableBody');
const alertContainer = document.getElementById('alertContainer');

// Modal Elements
const editModal = document.getElementById('editModal');
const editEmployeeIdEl = document.getElementById('editEmployeeId');
const editDateEl = document.getElementById('editDate');
const editCheckInTimeEl = document.getElementById('editCheckInTime');
const editCheckOutTimeEl = document.getElementById('editCheckOutTime');
const editStatusEl = document.getElementById('editStatus');
const editNotesEl = document.getElementById('editNotes');

// Error elements
const employeeIdError = document.getElementById('employeeIdError');
const departmentError = document.getElementById('departmentError');
const checkInError = document.getElementById('checkInError');
const checkOutError = document.getElementById('checkOutError');

// Variable to store current record being edited
let currentEditingRecord = null;

// Remembers the grace period across page reloads (it's a personal
// preference, not a tenant setting) — defaults to 15 minutes.
const GRACE_PERIOD_STORAGE_KEY = 'attendance_grace_period_minutes';

function updateCurrentDateTime() {
    const now = new Date();

    // Current date ko date input mein set karein
    if (!currentDateEl.value) {
        currentDateEl.value = now.toISOString().split('T')[0];
    }

    // Update time displays every minute
    const timeOptions = { hour: '2-digit', minute: '2-digit', hour12: true };
    const currentTime = now.toLocaleTimeString('en-US', timeOptions);

    if (checkInTimeEl.textContent === '--:-- --') {
        checkInTimeEl.textContent = currentTime;
    }

    if (checkOutTimeEl.textContent === '--:-- --') {
        checkOutTimeEl.textContent = currentTime;
    }
}


// Format time for display
function formatTimeForDisplay(timeString) {
    if (!timeString) return '--:-- --';

    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;

    return `${displayHour.toString().padStart(2, '0')}:${minutes} ${ampm}`;
}

// Show alert message
function showAlert(message, type) {
    const alertEl = document.createElement('div');
    alertEl.className = `alert alert-${type}`;

    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    alertEl.innerHTML = `
                <i class="fas ${icon}"></i>
                <div>${message}</div>
            `;

    alertContainer.appendChild(alertEl);

    // Auto remove after 5 seconds
    setTimeout(() => {
        alertEl.remove();
    }, 5000);
}

// Store employee data
let employeesData = [];
const employeeDropdown = document.getElementById('employeeDropdown');

// Load departments
async function loadDepartments() {
    try {
        const response = await fetch('../../../../server/api/hrm/attendance/get_departments.php');
        const result = await response.json();

        if (result.success) {
            result.data.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.department_name;
                option.textContent = dept.department_name;
                departmentEl.appendChild(option);

                // Also add to filter dropdown
                const filterOption = document.createElement('option');
                filterOption.value = dept.department_name;
                filterOption.textContent = dept.department_name;
                filterDepartmentEl.appendChild(filterOption);
            });
        }
    } catch (error) {
        console.error('Error loading departments:', error);
    }
}

// Load employees
async function loadEmployees() {
    try {
        const response = await fetch('../../../../server/api/hrm/attendance/get_employees.php');
        const result = await response.json();

        if (result.success) {
            employeesData = result.data;
            populateEmployeeFilter();
        }
    } catch (error) {
        console.error('Error loading employees:', error);
    }
}

// Fill the "Employee" filter dropdown in the history section from the
// same employee list used by the entry form's autocomplete.
function populateEmployeeFilter() {
    if (!filterEmployeeEl) return;

    const previousValue = filterEmployeeEl.value;

    filterEmployeeEl.innerHTML = '<option value="">All Employees</option>';

    employeesData.forEach(emp => {
        const option = document.createElement('option');
        option.value = emp.employee_id;
        option.textContent = `${emp.employee_id} - ${emp.full_name}`;
        filterEmployeeEl.appendChild(option);
    });

    if (previousValue) {
        filterEmployeeEl.value = previousValue;
    }
}

// Show dropdown with filtered employees
function showEmployeeDropdown(filter = '') {
    const filterLower = filter.toLowerCase();
    const filtered = employeesData.filter(emp =>
        emp.employee_id.toLowerCase().includes(filterLower) ||
        emp.full_name.toLowerCase().includes(filterLower)
    );

    employeeDropdown.innerHTML = '';

    if (filtered.length === 0) {
        employeeDropdown.classList.remove('show');
        return;
    }

    filtered.forEach(emp => {
        const item = document.createElement('div');
        item.className = 'dropdown-item';
        item.textContent = `${emp.employee_id} - ${emp.full_name}`;
        item.onclick = () => selectEmployee(emp);
        employeeDropdown.appendChild(item);
    });

    employeeDropdown.classList.add('show');
}

// Select employee from dropdown
async function selectEmployee(emp) {
    employeeIdEl.value = emp.employee_id;
    employeeDropdown.classList.remove('show');
    populateDepartment();

    // Update expected times from shift timing
    if (emp.shift_timing) {
        const [checkInTime, checkOutTime] = emp.shift_timing.split('-');
        document.querySelector('.time-card:nth-child(1) .helper-text').textContent = `Expected time: ${formatTimeForDisplay(checkInTime)}`;
        document.querySelector('.time-card:nth-child(2) .helper-text').textContent = `Expected time: ${formatTimeForDisplay(checkOutTime)}`;
    }

    if (autoAttendanceEl.checked) {
        await autoCheckInOut(emp.employee_id);
    }
}

// Hide dropdown
function hideEmployeeDropdown() {
    setTimeout(() => employeeDropdown.classList.remove('show'), 200);
}

// Scan barcode using camera
async function scanBarcode() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });

        // Create video element
        const video = document.createElement('video');
        video.srcObject = stream;
        video.setAttribute('playsinline', true);
        video.play();

        // Create modal overlay
        const modal = document.createElement('div');
        modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;';

        const closeBtn = document.createElement('button');
        closeBtn.textContent = 'Close';
        closeBtn.className = 'btn btn-secondary';
        closeBtn.style.cssText = 'position:absolute;top:20px;right:20px;';
        closeBtn.onclick = () => {
            stream.getTracks().forEach(track => track.stop());
            modal.remove();
        };

        video.style.cssText = 'max-width:90%;max-height:70%;';
        modal.appendChild(video);
        modal.appendChild(closeBtn);
        document.body.appendChild(modal);

        // Import and use barcode scanner library
        const { BrowserMultiFormatReader } = await import('https://cdn.jsdelivr.net/npm/@zxing/library@latest/esm/index.min.js');
        const codeReader = new BrowserMultiFormatReader();

        const result = await codeReader.decodeOnceFromVideoDevice(undefined, video);

        if (result) {
            employeeIdEl.value = result.text;
            populateDepartment();
            if (autoAttendanceEl.checked) {
                await autoCheckInOut(result.text);
            }
        }

        stream.getTracks().forEach(track => track.stop());
        modal.remove();
    } catch (error) {
        console.error('Barcode scan error:', error);
        showAlert('Failed to scan barcode. Please try again or enter manually.', 'error');
    }
}

// Auto check-in or check-out based on existing attendance.
// Only decides WHICH action to fire (check-in vs check-out) — the actual
// save + status calculation is done by setCheckInNow()/setCheckOutNow(),
// so it stays in sync with manual entry and always shift-timing aware.
async function autoCheckInOut(employeeId) {
    try {
        const date = currentDateEl.value;
        const response = await fetch(`../../../../server/api/hrm/attendance/attendance.php?date=${date}`);
        const result = await response.json();

        if (result.success) {
            const existingRecord = result.data.find(record => record.employee_id === employeeId);

            if (existingRecord) {
                // Employee already checked in today -> auto check-out
                await setCheckOutNow();
                clearForm();
            } else {
                // Employee not checked in yet -> auto check-in
                await setCheckInNow();
                clearForm();
            }
        }
    } catch (error) {
        console.error('Error checking attendance:', error);
        showAlert('Error processing attendance', 'error');
    }
}

// Auto-populate department when employee ID is entered
function populateDepartment() {
    const employeeId = employeeIdEl.value.trim();
    const employee = employeesData.find(emp => emp.employee_id === employeeId);

    if (employee && employee.department_name) {
        departmentEl.value = employee.department_name;
    } else {
        departmentEl.value = '';
    }
}

// Validate form
function validateForm() {
    let isValid = true;

    // Clear previous errors
    employeeIdError.textContent = '';
    departmentError.textContent = '';
    checkInError.textContent = '';
    checkOutError.textContent = '';

    employeeIdEl.classList.remove('error');
    departmentEl.classList.remove('error');
    checkInInputEl.classList.remove('error');
    checkOutInputEl.classList.remove('error');

    // Validate Employee ID
    if (!employeeIdEl.value) {
        employeeIdError.textContent = 'Employee is required';
        employeeIdEl.classList.add('error');
        isValid = false;
    }

    // Validate Department
    if (!departmentEl.value) {
        departmentError.textContent = 'Department is required';
        departmentEl.classList.add('error');
        isValid = false;
    }

    // Validate Check-in time
    if (!checkInInputEl.value) {
        checkInError.textContent = 'Check-in time is required';
        checkInInputEl.classList.add('error');
        isValid = false;
    }

    // Validate Check-out time
    if (!checkOutInputEl.value) {
        checkOutError.textContent = 'Check-out time is required';
        checkOutInputEl.classList.add('error');
        isValid = false;
    }

    // Validate that check-out is after check-in
    if (checkInInputEl.value && checkOutInputEl.value) {
        const checkInTime = new Date(`2000-01-01T${checkInInputEl.value}`);
        const checkOutTime = new Date(`2000-01-01T${checkOutInputEl.value}`);

        if (checkOutTime <= checkInTime) {
            checkOutError.textContent = 'Check-out time must be after check-in time';
            checkOutInputEl.classList.add('error');
            isValid = false;
        }
    }

    return isValid;
}

// Reads the grace-period input, falling back to 15 minutes if it's empty,
// non-numeric, or negative.
function getGracePeriodMinutes() {
    const raw = gracePeriodEl ? parseInt(gracePeriodEl.value, 10) : NaN;
    return (!isNaN(raw) && raw >= 0) ? raw : 15;
}

function timeStringToMinutes(timeStr) {
    const [h, m] = timeStr.split(':').map(Number);
    return (h * 60) + m;
}

// Update attendance status based on BOTH check-in (vs shift start) and
// check-out (vs shift end), compared against the employee's shift_timing.
// Grace period is read from the on-page "Late Arrival Grace Period" field
// and applies to both ends.
//
// Runs on every check-in/check-out change — typed manually or set via the
// Check-in/Check-out Now buttons — regardless of whether "Auto Attendance"
// is ticked. That checkbox only controls whether selecting/scanning an
// employee automatically fires a check-in or check-out; it never gates
// this status calculation.
function updateAttendanceStatus() {
    if (!checkInInputEl.value) return;

    const employeeId = employeeIdEl.value.trim();
    const employee = employeesData.find(emp => emp.employee_id === employeeId);

    if (!employee || !employee.shift_timing) {
        attendanceStatusEl.value = 'present';
        return;
    }

    const graceMinutes = getGracePeriodMinutes();
    const [expectedCheckIn, expectedCheckOut] = employee.shift_timing.split('-');

    const expectedInMinutes = timeStringToMinutes(expectedCheckIn);
    const actualInMinutes = timeStringToMinutes(checkInInputEl.value);
    const isLate = actualInMinutes > (expectedInMinutes + graceMinutes);

    let isEarlyLeave = false;
    if (checkOutInputEl.value && expectedCheckOut) {
        const expectedOutMinutes = timeStringToMinutes(expectedCheckOut);
        const actualOutMinutes = timeStringToMinutes(checkOutInputEl.value);
        isEarlyLeave = actualOutMinutes < (expectedOutMinutes - graceMinutes);
    }

    if (isLate && isEarlyLeave) {
        attendanceStatusEl.value = 'halfday';
    } else if (isLate) {
        attendanceStatusEl.value = 'late';
    } else if (isEarlyLeave) {
        attendanceStatusEl.value = 'halfday';
    } else {
        attendanceStatusEl.value = 'present';
    }
}

// Save attendance record
async function saveAttendanceRecord(isCheckout = false) {

    if (!currentDateEl.value) {
        showAlert('Please select attendance date.', 'error');
        return;
    }

    if (!validateForm()) {

        showAlert('Please fix the errors in the form before saving.', 'error');
        return;
    }

    const record = {
        employeeId: employeeIdEl.value,
        department: departmentEl.value,
        checkIn: checkInInputEl.value,
        checkOut: checkOutInputEl.value,
        status: attendanceStatusEl.value,
        notes: notesEl.value.trim(),
date: currentDateEl.value,
        isCheckout: isCheckout
    };

    try {
        const response = await fetch('../../../../server/api/hrm/attendance/attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(record)
        });

        const result = await response.json();

        if (result.success) {
            showAlert(`Attendance record saved successfully for ${record.employeeId}`, 'success');
            updateAttendanceTable();
        } else {
            showAlert(result.message || 'Failed to save attendance', 'error');
        }
    } catch (error) {
        showAlert('Error saving attendance record', 'error');
    }
}

// Open Edit Modal
function openEditModal(recordId, recordData) {
    currentEditingRecord = recordId;
    editEmployeeIdEl.value = recordData.employee_id;
    editDateEl.value = recordData.attendance_date;
    editCheckInTimeEl.value = recordData.check_in;
    editCheckOutTimeEl.value = recordData.check_out;
    editStatusEl.value = recordData.status;
    editNotesEl.value = recordData.notes || '';
    editModal.classList.add('show');
}

// Close Edit Modal
function closeEditModal() {
    editModal.classList.remove('show');
    currentEditingRecord = null;
}

// Save Edited Record
async function saveEditedRecord() {
    const checkInTime = editCheckInTimeEl.value;
    const checkOutTime = editCheckOutTimeEl.value;
    const status = editStatusEl.value;
    const notes = editNotesEl.value;

    // Validate times
    if (!checkInTime || !checkOutTime) {
        showAlert('Check-in and check-out times are required', 'error');
        return;
    }

    const checkInTimeObj = new Date(`2000-01-01T${checkInTime}`);
    const checkOutTimeObj = new Date(`2000-01-01T${checkOutTime}`);

    if (checkOutTimeObj <= checkInTimeObj) {
        showAlert('Check-out time must be after check-in time', 'error');
        return;
    }

    try {
        const response = await fetch('../../../../server/api/hrm/attendance/attendance.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: currentEditingRecord,
                checkIn: checkInTime,
                checkOut: checkOutTime,
                status: status,
                notes: notes
            })
        });

        const result = await response.json();

        if (result.success) {
            showAlert('Attendance record updated successfully', 'success');
            closeEditModal();
            updateAttendanceTable();
        } else {
            showAlert(result.message || 'Failed to update attendance', 'error');
        }
    } catch (error) {
        console.error('Error updating attendance:', error);
        showAlert('Error updating attendance record', 'error');
    }
}

// Delete Attendance Record
async function deleteAttendanceRecord(recordId, employeeId, date) {
    if (!confirm(`Are you sure you want to delete attendance record for ${employeeId} on ${date}? This action cannot be undone.`)) {
        return;
    }

    try {
        const response = await fetch('../../../../server/api/hrm/attendance/attendance.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: recordId
            })
        });

        const result = await response.json();

        if (result.success) {
            showAlert('Attendance record deleted successfully', 'success');
            updateAttendanceTable();
        } else {
            showAlert(result.message || 'Failed to delete attendance', 'error');
        }
    } catch (error) {
        console.error('Error deleting attendance:', error);
        showAlert('Error deleting attendance record', 'error');
    }
}

// Update attendance table — driven by the From/To date range filters (plus
// Employee and Department filters applied client-side on top of that
// range's data). If either date is left empty, it defaults to today, so
// with nothing selected yet the table normally just shows today's
// attendance.
async function updateAttendanceTable() {
    try {
        const today = new Date().toISOString().split('T')[0];
        const dateFrom = (filterDateFromEl && filterDateFromEl.value) || today;
        const dateTo = (filterDateToEl && filterDateToEl.value) || today;
        const response = await fetch(`../../../../server/api/hrm/attendance/attendance.php?date_from=${dateFrom}&date_to=${dateTo}`);

        if (!response.ok) {
            // Server returned a non-2xx status (401/500/etc.) — surface that
            // instead of trying (and failing) to parse a body as attendance data.
            throw new Error(`Server responded with ${response.status}`);
        }

        const result = await response.json();

        attendanceTableBody.innerHTML = '';

        if (!result.success || result.data.length === 0) {
            attendanceTableBody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--subtext);">
                        No attendance records found.
                    </td>
                </tr>
            `;
            return;
        }

        // Apply client-side filters (employee = exact match from dropdown,
        // department = exact match from dropdown)
        const filterEmployee = filterEmployeeEl.value;
        const filterDepartment = filterDepartmentEl.value;

        let filteredData = result.data;

        if (filterEmployee) {
            filteredData = filteredData.filter(record =>
                record.employee_id === filterEmployee
            );
        }

        if (filterDepartment) {
            filteredData = filteredData.filter(record =>
                record.department_name === filterDepartment
            );
        }

        if (filteredData.length === 0) {
            attendanceTableBody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--subtext);">
                        No attendance records found.
                    </td>
                </tr>
            `;
            return;
        }

       filteredData.forEach(record => {
    const row = document.createElement('tr');

    let statusBadgeClass = 'badge-present';
    let statusText = 'Present';

    if (record.status === 'late') {
        statusBadgeClass = 'badge-late';
        statusText = 'Late';
    } else if (record.status === 'absent') {
        statusBadgeClass = 'badge-absent';
        statusText = 'Absent';
    } else if (record.status === 'halfday') {
        statusBadgeClass = 'badge-late';
        statusText = 'Half Day';
    } else if (record.status === 'on_leave') {
        statusBadgeClass = record.leave_is_paid == 1 ? 'badge-late' : 'badge-absent';
        statusText = record.leave_type_name || 'On Leave';
    }

    row.innerHTML = `
        <td>${record.attendance_date}</td>
        <td>${record.employee_id}</td>
        <td>${record.full_name || '-'}</td>
        <td>${record.department_name || '-'}</td>
        <td>${formatTimeForDisplay(record.check_in)}</td>
        <td>${formatTimeForDisplay(record.check_out)}</td>
        <td><span class="status-badge ${statusBadgeClass}">${statusText}</span></td>
        <td>
            <div class="action-buttons">
                <button class="btn-icon btn-edit" title="Edit Record" type="button">
                    <i class="fas fa-edit"></i>
                </button>

                <button class="btn-icon btn-delete" title="Delete Record" type="button">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    `;

    // Edit button
    const editButton = row.querySelector('.btn-edit');

    editButton.addEventListener('click', function () {
        openEditModal(record.id, record);
    });

    // Delete button
    const deleteButton = row.querySelector('.btn-delete');

    deleteButton.addEventListener('click', function () {
        deleteAttendanceRecord(
            record.id,
            record.employee_id,
            record.attendance_date
        );
    });

    attendanceTableBody.appendChild(row);
});
    } catch (error) {
        console.error('Error loading attendance records:', error);
        attendanceTableBody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; color: var(--error);">
                    Error loading attendance records. Please refresh and try again.
                </td>
            </tr>
        `;
    }
}

// Clear form
function clearForm() {
    employeeIdEl.value = '';
    departmentEl.value = '';
    checkInInputEl.value = '09:00';
    checkOutInputEl.value = '17:00';
    checkInTimeEl.textContent = formatTimeForDisplay(checkInInputEl.value);
    checkOutTimeEl.textContent = formatTimeForDisplay(checkOutInputEl.value);
    checkInStatusEl.textContent = 'Pending';
    checkInStatusEl.className = 'time-status status-pending';
    checkOutStatusEl.textContent = 'Pending';
    checkOutStatusEl.className = 'time-status status-pending';
    attendanceStatusEl.value = 'present';
    notesEl.value = '';

    // Clear errors
    employeeIdError.textContent = '';
    departmentError.textContent = '';
    checkInError.textContent = '';
    checkOutError.textContent = '';

    employeeIdEl.classList.remove('error');
    departmentEl.classList.remove('error');
    checkInInputEl.classList.remove('error');
    checkOutInputEl.classList.remove('error');
}

// Set check-in to current time
async function setCheckInNow() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const currentTime = `${hours}:${minutes}`;

    checkInInputEl.value = currentTime;
    checkInTimeEl.textContent = formatTimeForDisplay(currentTime);
    checkInStatusEl.textContent = 'Completed';
    checkInStatusEl.className = 'time-status status-completed';

    // Update attendance status based on check-in time (and check-out, if set)
    updateAttendanceStatus();

    // Save to database
    if (employeeIdEl.value && departmentEl.value) {
        await saveAttendanceRecord(false);
    } else {
        showAlert('Check-in time set. Please select employee and department to save.', 'success');
    }
}

// Set check-out to current time
async function setCheckOutNow() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const currentTime = `${hours}:${minutes}`;

    checkOutInputEl.value = currentTime;
    checkOutTimeEl.textContent = formatTimeForDisplay(currentTime);
    checkOutStatusEl.textContent = 'Completed';
    checkOutStatusEl.className = 'time-status status-completed';

    // Update attendance status based on check-out time vs shift end too
    updateAttendanceStatus();

    // Save to database
    if (employeeIdEl.value && departmentEl.value) {
        await saveAttendanceRecord(true);
    } else {
        showAlert('Check-out time set. Please select employee and department to save.', 'success');
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === editModal) {
        closeEditModal();
    }
});

// Initialize the application
function init() {
    // Load departments and employees
    loadDepartments();
    loadEmployees();

    // Set current time for check-in and check-out
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const currentTime = `${hours}:${minutes}`;

    checkInInputEl.value = currentTime;
    checkOutInputEl.value = currentTime;

    // Restore the last-used grace period (per-browser convenience, not a
    // tenant-wide setting) so HR doesn't have to re-type it every day.
    if (gracePeriodEl) {
        const savedGrace = localStorage.getItem(GRACE_PERIOD_STORAGE_KEY);
        if (savedGrace !== null) {
            gracePeriodEl.value = savedGrace;
        }
        gracePeriodEl.addEventListener('change', () => {
            const val = getGracePeriodMinutes();
            gracePeriodEl.value = val;
            localStorage.setItem(GRACE_PERIOD_STORAGE_KEY, String(val));
            updateAttendanceStatus();
        });
    }

    // Set attendance date (entry form) to today
    if (currentDateEl) {
        currentDateEl.value = new Date().toISOString().split('T')[0];
    }

    // Set the history table's date range filter to today by default, so
    // the list normally shows just today's attendance until HR picks a
    // different From/To range.
    if (filterDateFromEl && filterDateToEl) {
        const today = new Date().toISOString().split('T')[0];
        filterDateFromEl.value = today;
        filterDateToEl.value = today;
    }

    // Set current date and time
    updateCurrentDateTime();

    // Update time every minute
    setInterval(updateCurrentDateTime, 60000);

    // Initialize time displays
    checkInTimeEl.textContent = formatTimeForDisplay(checkInInputEl.value);
    checkOutTimeEl.textContent = formatTimeForDisplay(checkOutInputEl.value);

    // Load existing attendance records
    updateAttendanceTable();

    // Event Listeners
    checkInBtn.addEventListener('click', setCheckInNow);
    checkOutBtn.addEventListener('click', setCheckOutNow);
    saveBtn.addEventListener('click', saveAttendanceRecord);
    clearBtn.addEventListener('click', clearForm);
    scanBarcodeBtn.addEventListener('click', scanBarcode);

    // Update time displays when input changes
    checkInInputEl.addEventListener('change', function () {
        checkInTimeEl.textContent = formatTimeForDisplay(this.value);
        checkInStatusEl.textContent = 'Manual';
        checkInStatusEl.className = 'time-status status-completed';
        updateAttendanceStatus();
    });

    checkOutInputEl.addEventListener('change', function () {
        checkOutTimeEl.textContent = formatTimeForDisplay(this.value);
        checkOutStatusEl.textContent = 'Manual';
        checkOutStatusEl.className = 'time-status status-completed';
        updateAttendanceStatus();
    });

    // Show dropdown on focus and input
    employeeIdEl.addEventListener('focus', () => showEmployeeDropdown(employeeIdEl.value));
    employeeIdEl.addEventListener('input', function() {
        showEmployeeDropdown(this.value);
        populateDepartment();
    });
    employeeIdEl.addEventListener('blur', hideEmployeeDropdown);

    // Auto-select first option on Enter key and auto check-in/out
    employeeIdEl.addEventListener('keydown', async function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const items = employeeDropdown.querySelectorAll('.dropdown-item');
            if (items.length > 0) {
                items[0].click();
            }

            // Check if employee is already checked in today
            if (autoAttendanceEl.checked) {
                const employeeId = employeeIdEl.value.trim();
                if (employeeId) {
                    await autoCheckInOut(employeeId);
                }
            }
        }
    });

    // Validate form on input
    departmentEl.addEventListener('change', validateForm);
    checkInInputEl.addEventListener('change', validateForm);
    checkOutInputEl.addEventListener('change', validateForm);

    // Filter event listeners (history table)
    filterDateFromEl.addEventListener('change', updateAttendanceTable);
    filterDateToEl.addEventListener('change', updateAttendanceTable);
    filterEmployeeEl.addEventListener('change', updateAttendanceTable);
    filterDepartmentEl.addEventListener('change', updateAttendanceTable);
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', init);