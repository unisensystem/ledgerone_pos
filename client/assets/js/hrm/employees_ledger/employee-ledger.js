// Data from API
let employees = [];
let transactions = [];

// DOM Elements
const summaryView = document.getElementById('summaryView');
const individualView = document.getElementById('individualView');
const summaryViewBtn = document.getElementById('summaryViewBtn');
const individualViewBtn = document.getElementById('individualViewBtn');
const summaryTableBody = document.getElementById('summaryTableBody');
const individualTableBody = document.getElementById('individualTableBody');
const generateReportBtn = document.getElementById('generateReport');
const resetFiltersBtn = document.getElementById('resetFilters');
const employeeSelect = document.getElementById('employeeSelect');
const departmentSelect = document.getElementById('departmentSelect');
const startDate = document.getElementById('startDate');
const endDate = document.getElementById('endDate');
const showZeroBalance = document.getElementById('showZeroBalance');
const loadingIndicator = document.getElementById('loadingIndicator');
const noDataMessage = document.getElementById('noDataMessage');
const reportTitle = document.getElementById('reportTitle');
const printBtn = document.getElementById('printBtn');
const exportBtn = document.getElementById('exportBtn');
const scanBarcodeBtn = document.getElementById('scanBarcodeBtn');
const barcodeScannerModal = document.getElementById('barcodeScannerModal');
const barcodeScannerVideo = document.getElementById('barcodeScannerVideo');
const closeScannerBtn = document.getElementById('closeScannerBtn');
const scannerStatus = document.getElementById('scannerStatus');
const showHelpBtn = document.getElementById('showHelp');
const helpSection = document.querySelector('.help-section');

// Barcode scanner
let codeReader = null;
let selectedDeviceId = null;

// Current state
let currentView = 'summary';
let currentEmployeeId = null;
let currentFilteredData = [];

// Initialize the report
document.addEventListener('DOMContentLoaded', function () {
    loadEmployeesAndDepartments();
    setupEventListeners();
    
    // Initialize Select2 for employee dropdown
    $('#employeeSelect').select2({
        placeholder: 'Select an employee',
        allowClear: true,
        width: '100%'
    });
});

// Set up event listeners
function setupEventListeners() {
    // View toggle
    summaryViewBtn.addEventListener('click', () => switchView('summary'));
    individualViewBtn.addEventListener('click', () => switchView('individual'));

    // Generate report
    generateReportBtn.addEventListener('click', generateReport);

    // Reset filters
    resetFiltersBtn.addEventListener('click', resetFilters);

    // Print report
    printBtn.addEventListener('click', () => {
        const selectedEmployee = employeeSelect.value;
        const startDateValue = startDate.value;
        const endDateValue = endDate.value;
        const view = currentView;
        
        const printUrl = `print.php?employee_id=${selectedEmployee}&start_date=${startDateValue}&end_date=${endDateValue}&view=${view}`;
        window.open(printUrl, '_blank');
    });

    // Export CSV
    exportBtn.addEventListener('click', exportToCSV);

    // Scan Barcode
    scanBarcodeBtn.addEventListener('click', openBarcodeScanner);
    closeScannerBtn.addEventListener('click', closeBarcodeScanner);

    // Show/hide help
    showHelpBtn.addEventListener('click', () => {
        helpSection.style.display = helpSection.style.display === 'none' ? 'block' : 'none';
        showHelpBtn.innerHTML = helpSection.style.display === 'none'
            ? '<i class="fas fa-question-circle"></i> Show Help'
            : '<i class="fas fa-times"></i> Hide Help';
    });
}

// Switch between summary and individual views
function switchView(view) {
    currentView = view;

    if (view === 'summary') {
        summaryView.style.display = 'block';
        individualView.style.display = 'none';
        summaryViewBtn.classList.add('active');
        individualViewBtn.classList.remove('active');
        reportTitle.textContent = 'Employee Ledger Summary';
        currentEmployeeId = null;
    } else {
        summaryView.style.display = 'none';
        individualView.style.display = 'block';
        summaryViewBtn.classList.remove('active');
        individualViewBtn.classList.add('active');
        reportTitle.textContent = currentEmployeeId
            ? `Ledger for ${getEmployeeName(currentEmployeeId)}`
            : 'Employee Ledger - Individual Transactions';

        // If we don't have a specific employee selected, show all transactions
        if (!currentEmployeeId) {
            renderIndividualView(currentFilteredData);
        }
    }
}

// Load employees and departments for dropdowns
async function loadEmployeesAndDepartments() {
    try {
        const startDateValue = startDate.value;
        const endDateValue = endDate.value;
        const response = await fetch(`../../../../server/api/hrm/employees_ledger/employee-ledger.php?start_date=${startDateValue}&end_date=${endDateValue}`);
        const data = await response.json();
        
        if (data.success) {
            employees = data.employees;
            transactions = data.transactions;
            
            // Populate employee dropdown only if empty
            if (employeeSelect.options.length <= 1) {
                employeeSelect.innerHTML = '<option value="all">All Employees</option>';
                employees.forEach(emp => {
                    const option = document.createElement('option');
                    option.value = emp.id;
                    option.textContent = `${emp.name} (${emp.id.toUpperCase()})`;
                    employeeSelect.appendChild(option);
                });
                
                // Refresh Select2 after populating options
                $('#employeeSelect').trigger('change');
            }
            
            renderReport();
        }
    } catch (error) {
        console.error('Error loading data:', error);
        noDataMessage.style.display = 'block';
        noDataMessage.innerHTML = '<i class="fas fa-exclamation-triangle"></i><p>Error loading data. Please try again.</p>';
    }
}

// Generate report based on filters
function generateReport() {
    // Reload data with new date range
    loadEmployeesAndDepartments();
}

// Render report with current data
function renderReport() {
    // Show loading
    loadingIndicator.style.display = 'block';
    noDataMessage.style.display = 'none';

    setTimeout(() => {
        const selectedEmployee = employeeSelect.value;
        const selectedDepartment = departmentSelect.value;

        // Filter transactions based on selected employee
        let filteredTransactions = transactions;
        if (selectedEmployee !== 'all') {
            filteredTransactions = transactions.filter(t => t.employeeId === selectedEmployee);
        }

        // Filter by department if needed
        if (selectedDepartment !== 'all') {
            const employeesInDept = employees.filter(e => e.department.toLowerCase() === selectedDepartment.toLowerCase());
            const employeeIdsInDept = employeesInDept.map(e => e.id);
            filteredTransactions = filteredTransactions.filter(t => employeeIdsInDept.includes(t.employeeId));
        }

        // Store filtered data
        currentFilteredData = filteredTransactions;

        // Render the appropriate view
        if (currentView === 'summary') {
            renderSummaryView(filteredTransactions);
        } else {
            renderIndividualView(filteredTransactions);
        }

        // Hide loading
        loadingIndicator.style.display = 'none';

        // Show no data message if applicable
        if (filteredTransactions.length === 0 && employees.filter(e =>
            (selectedEmployee === 'all' || e.id === selectedEmployee) &&
            (selectedDepartment === 'all' || e.department.toLowerCase() === selectedDepartment.toLowerCase())
        ).length === 0) {
            noDataMessage.style.display = 'block';
        }
    }, 300);
}

// Render summary view
function renderSummaryView(transactions) {
    summaryTableBody.innerHTML = '';

    // Get unique employees from transactions
    const employeeIds = [...new Set(transactions.map(t => t.employeeId))];

    // Add all employees matching the filter (even without transactions)
    const selectedEmployee = employeeSelect.value;
    const selectedDepartment = departmentSelect.value;

    employees.forEach(emp => {
        if ((selectedEmployee === 'all' || emp.id === selectedEmployee) &&
            (selectedDepartment === 'all' || emp.department.toLowerCase() === selectedDepartment.toLowerCase())) {
            if (!employeeIds.includes(emp.id)) {
                employeeIds.push(emp.id);
            }
        }
    });

    if (employeeIds.length === 0) {
        noDataMessage.style.display = 'block';
        return;
    }

    // Calculate totals for each employee
    employeeIds.forEach(empId => {
        const employee = employees.find(e => e.id === empId);
        if (!employee) return;

        const empTransactions = transactions.filter(t => t.employeeId === empId);

        // Calculate totals
        const totalDebit = empTransactions.reduce((sum, t) => sum + t.debit, 0);
        const totalCredit = empTransactions.reduce((sum, t) => sum + t.credit, 0);

        // Calculate closing balance
        let openingBalance = employee.openingBalance || 0;
        const openingType = employee.openingType;

        // Adjust opening balance based on type
        if (openingType === 'Cr') {
            openingBalance = -openingBalance; // Credit is negative for calculation
        }

        const closingBalance = openingBalance + totalDebit - totalCredit;
        const closingType = closingBalance >= 0 ? 'Dr' : 'Cr';
        const absClosingBalance = Math.abs(closingBalance);
        
        // Skip employees with zero opening AND closing balance when viewing all employees and checkbox is unchecked
        const selectedEmployee = employeeSelect.value;
        const hasOpeningBalance = employee.openingBalance && employee.openingBalance > 0;
        if (selectedEmployee === 'all' && absClosingBalance === 0 && !hasOpeningBalance && !showZeroBalance.checked) {
            return;
        }

        // Create row
        const row = document.createElement('tr');
        row.className = 'summary-row';
        row.dataset.employeeId = empId;

        row.innerHTML = `
                    <td>${empId.toUpperCase()}</td>
                    <td>${employee.name}</td>
                    <td>${employee.department}</td>
                    <td>${employee.openingBalance ? `${window.currencySymbol}${employee.openingBalance.toLocaleString()} ${employee.openingType || ''}` : `${window.currencySymbol}0`}</td>
                    <td class="amount-debit">${window.currencySymbol}${totalDebit.toLocaleString()}</td>
                    <td class="amount-credit">${window.currencySymbol}${totalCredit.toLocaleString()}</td>
                    <td class="${closingType === 'Dr' ? 'balance-dr' : 'balance-cr'}">${window.currencySymbol}${absClosingBalance.toLocaleString()} ${closingType}</td>
                `;

        // Add click event to show individual transactions
        row.addEventListener('click', () => {
            currentEmployeeId = empId;
            switchView('individual');
            renderIndividualTransactions(empId);
        });

        summaryTableBody.appendChild(row);
    });
}

// Render individual view for all filtered transactions
function renderIndividualView(transactions) {
    individualTableBody.innerHTML = '';

    if (transactions.length === 0) {
        noDataMessage.style.display = 'block';
        return;
    }

    // Group transactions by employee
    const transactionsByEmployee = {};
    transactions.forEach(t => {
        if (!transactionsByEmployee[t.employeeId]) {
            transactionsByEmployee[t.employeeId] = [];
        }
        transactionsByEmployee[t.employeeId].push(t);
    });

    // Render each employee's transactions
    Object.keys(transactionsByEmployee).forEach(empId => {
        const employee = employees.find(e => e.id === empId);
        if (!employee) return;

        // Add employee header row
        const headerRow = document.createElement('tr');
        headerRow.className = 'employee-details-row';
        headerRow.innerHTML = `
                    <td colspan="7">
                        <i class="fas fa-user"></i> ${employee.name} (${empId.toUpperCase()}) - ${employee.department}
                    </td>
                `;
        individualTableBody.appendChild(headerRow);

        // Add opening balance row
        const openingRow = document.createElement('tr');
        openingRow.className = 'balance-row';
        openingRow.innerHTML = `
                    <td colspan="3">Opening Balance</td>
                    <td colspan="2"></td>
                    <td class="${employee.openingType === 'Dr' ? 'balance-dr' : 'balance-cr'}">
                        ${employee.openingBalance ? `${window.currencySymbol}${employee.openingBalance.toLocaleString()} ${employee.openingType || ''}` : `${window.currencySymbol}0`}
                    </td>
                    <td></td>
                `;
        individualTableBody.appendChild(openingRow);

        // Get employee's transactions sorted by date
        const empTransactions = transactionsByEmployee[empId].sort((a, b) => new Date(a.date) - new Date(b.date));

        let runningBalance = employee.openingBalance || 0;
        let runningType = employee.openingType || null;

        // Render each transaction
        empTransactions.forEach(transaction => {
            // Calculate running balance
            if (runningType === 'Cr') {
                runningBalance = -runningBalance; // Convert to negative for calculation
            }

            runningBalance = runningBalance + transaction.debit - transaction.credit;
            runningType = runningBalance >= 0 ? 'Dr' : 'Cr';
            const absRunningBalance = Math.abs(runningBalance);

            const row = document.createElement('tr');
            const debitDisplay = transaction.debit > 0 ? window.currencySymbol + transaction.debit.toLocaleString() : (transaction.displayAmount > 0 ? window.currencySymbol + transaction.displayAmount.toLocaleString() : '');
            row.innerHTML = `
                        <td>${formatDate(transaction.date)}</td>
                        <td>${transaction.voucherNo}</td>
                        <td>${transaction.particulars}</td>
                        <td class="amount-debit">${debitDisplay}</td>
                        <td class="amount-credit">${transaction.credit > 0 ? window.currencySymbol + transaction.credit.toLocaleString() : ''}</td>
                        <td class="${runningType === 'Dr' ? 'balance-dr' : 'balance-cr'}">${window.currencySymbol}${absRunningBalance.toLocaleString()} ${runningType}</td>
                        <td></td>
                    `;
            individualTableBody.appendChild(row);
        });

        // Add closing balance row
        const absRunningBalance = Math.abs(runningBalance);
        const closingRow = document.createElement('tr');
        closingRow.className = 'balance-row';
        closingRow.innerHTML = `
                    <td colspan="3">Closing Balance</td>
                    <td colspan="2"></td>
                    <td class="${runningType === 'Dr' ? 'balance-dr' : 'balance-cr'}">
                        ${window.currencySymbol}${absRunningBalance.toLocaleString()} ${runningType}
                    </td>
                    <td></td>
                `;
        individualTableBody.appendChild(closingRow);

        // Add a spacer row
        const spacerRow = document.createElement('tr');
        spacerRow.innerHTML = '<td colspan="7" style="height: 20px;"></td>';
        individualTableBody.appendChild(spacerRow);
    });
}

// Render individual transactions for a specific employee
function renderIndividualTransactions(employeeId) {
    individualTableBody.innerHTML = '';

    const employee = employees.find(e => e.id === employeeId);
    if (!employee) return;

    // Filter transactions for this employee
    const empTransactions = currentFilteredData.filter(t => t.employeeId === employeeId)
        .sort((a, b) => new Date(a.date) - new Date(b.date));

    // Update report title
    reportTitle.textContent = `Ledger for ${employee.name}`;

    // Add employee header row
    const headerRow = document.createElement('tr');
    headerRow.className = 'employee-details-row';
    headerRow.innerHTML = `
                <td colspan="7">
                    <i class="fas fa-user"></i> ${employee.name} (${employeeId.toUpperCase()}) - ${employee.department}
                </td>
            `;
    individualTableBody.appendChild(headerRow);

    // Add opening balance row
    const openingRow = document.createElement('tr');
    openingRow.className = 'balance-row';
    openingRow.innerHTML = `
                <td colspan="3">Opening Balance</td>
                <td colspan="2"></td>
                <td class="${employee.openingType === 'Dr' ? 'balance-dr' : 'balance-cr'}">
                    ${employee.openingBalance ? `${window.currencySymbol}${employee.openingBalance.toLocaleString()} ${employee.openingType || ''}` : `${window.currencySymbol}0`}
                </td>
                <td></td>
            `;
    individualTableBody.appendChild(openingRow);

    let runningBalance = employee.openingBalance || 0;
    let runningType = employee.openingType || null;

    // Render each transaction
    empTransactions.forEach(transaction => {
        // Calculate running balance
        if (runningType === 'Cr') {
            runningBalance = -runningBalance; // Convert to negative for calculation
        }

        runningBalance = runningBalance + transaction.debit - transaction.credit;
        runningType = runningBalance >= 0 ? 'Dr' : 'Cr';
        const absRunningBalance = Math.abs(runningBalance);

        const row = document.createElement('tr');
        const debitDisplay = transaction.debit > 0 ? window.currencySymbol + transaction.debit.toLocaleString() : (transaction.displayAmount > 0 ? window.currencySymbol + transaction.displayAmount.toLocaleString() : '');
        row.innerHTML = `
                    <td>${formatDate(transaction.date)}</td>
                    <td>${transaction.voucherNo}</td>
                    <td>${transaction.particulars}</td>
                    <td class="amount-debit">${debitDisplay}</td>
                    <td class="amount-credit">${transaction.credit > 0 ? window.currencySymbol + transaction.credit.toLocaleString() : ''}</td>
                    <td class="${runningType === 'Dr' ? 'balance-dr' : 'balance-cr'}">${window.currencySymbol}${absRunningBalance.toLocaleString()} ${runningType}</td>
                    <td></td>
                `;
        individualTableBody.appendChild(row);
    });

    // Add closing balance row
    const absRunningBalance = Math.abs(runningBalance);
    const closingRow = document.createElement('tr');
    closingRow.className = 'balance-row';
    closingRow.innerHTML = `
                <td colspan="3">Closing Balance</td>
                <td colspan="2"></td>
                <td class="${runningType === 'Dr' ? 'balance-dr' : 'balance-cr'}">
                    ${window.currencySymbol}${absRunningBalance.toLocaleString()} ${runningType}
                </td>
                <td></td>
            `;
    individualTableBody.appendChild(closingRow);
}

// Reset filters
function resetFilters() {
    employeeSelect.value = 'all';
    departmentSelect.value = 'all';
    startDate.value = '2023-01-01';
    endDate.value = new Date().toISOString().split('T')[0];
    currentEmployeeId = null;
    switchView('summary');
    generateReport();
}

// Export to CSV
function exportToCSV() {
    const selectedEmployee = employeeSelect.value;
    const employee = employees.find(e => e.id === selectedEmployee);
    const filename = employee
        ? `employee_ledger_${employee.name.replace(/\s+/g, '_')}.csv`
        : 'employee_ledger_all.csv';

    let csvContent = "";

    if (currentView === 'summary') {
        csvContent += "Employee ID,Employee Name,Department,Opening Balance,Total Debit,Total Credit,Closing Balance\n";

        const rows = document.querySelectorAll('#summaryTableBody tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const rowData = Array.from(cells).map(cell => {
                const text = cell.textContent.trim().replace(window.currencySymbol, '').replace(/,/g, '');
                return `"${text}"`;
            }).join(',');
            csvContent += rowData + "\n";
        });
    } else {
        csvContent += "Date,Voucher No.,Particulars,Debit Amount,Credit Amount,Balance\n";

        const rows = document.querySelectorAll('#individualTableBody tr:not(.employee-details-row):not(.balance-row)');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length > 0) {
                const rowData = Array.from(cells).slice(0, 6).map(cell => {
                    const text = cell.textContent.trim().replace(window.currencySymbol, '').replace(/,/g, '');
                    return `"${text}"`;
                }).join(',');
                csvContent += rowData + "\n";
            }
        });
    }

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Helper functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function getEmployeeName(employeeId) {
    const employee = employees.find(e => e.id === employeeId);
    return employee ? employee.name : 'Unknown Employee';
}

// Barcode Scanner Functions
function openBarcodeScanner() {
    barcodeScannerModal.style.display = 'flex';
    scannerStatus.textContent = 'Initializing camera...';
    
    codeReader = new ZXing.BrowserMultiFormatReader();
    
    codeReader.listVideoInputDevices()
        .then(videoInputDevices => {
            if (videoInputDevices.length === 0) {
                scannerStatus.textContent = 'No camera found on this device';
                return;
            }
            
            // Prefer back camera on mobile
            selectedDeviceId = videoInputDevices.find(device => 
                device.label.toLowerCase().includes('back')
            )?.deviceId || videoInputDevices[0].deviceId;
            
            scannerStatus.textContent = 'Point camera at barcode...';
            
            codeReader.decodeFromVideoDevice(selectedDeviceId, barcodeScannerVideo, (result, err) => {
                if (result) {
                    const employeeId = result.text;
                    scannerStatus.textContent = `Scanned: ${employeeId}`;
                    
                    // Check if employee exists
                    const employee = employees.find(e => e.id === employeeId);
                    if (employee) {
                        closeBarcodeScanner();
                        
                        // Set employee in dropdown
                        $('#employeeSelect').val(employeeId).trigger('change');
                        
                        // Switch to individual view
                        currentEmployeeId = employeeId;
                        switchView('individual');
                        
                        // Filter and render
                        const filteredTransactions = transactions.filter(t => t.employeeId === employeeId);
                        currentFilteredData = filteredTransactions;
                        renderIndividualTransactions(employeeId);
                    } else {
                        scannerStatus.textContent = `Employee ${employeeId} not found. Scanning...`;
                    }
                }
            });
        })
        .catch(err => {
            scannerStatus.textContent = 'Error accessing camera: ' + err;
        });
}

function closeBarcodeScanner() {
    if (codeReader) {
        codeReader.reset();
        codeReader = null;
    }
    barcodeScannerModal.style.display = 'none';
}