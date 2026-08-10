let chartOfAccounts = [];
let allAccounts = [];
let expandedRows = new Set();

async function fetchTrialBalanceData() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/trial_balance/trial-balance.php');
        const result = await response.json();
        if (result.success) {
            allAccounts = result.data;
            chartOfAccounts = result.data;
            if (chartOfAccounts.length === 0) {
                showNotification('No accounts found. Please add accounts first.', 'warning');
            }
            populateTrialBalance();
        } else {
            showNotification('Failed to load trial balance data', 'error');
        }
    } catch (error) {
        showNotification('Error loading data', 'error');
    }
}

function populateTrialBalance() {
    const tableBody = document.getElementById('trialBalanceBody');
    tableBody.innerHTML = '';

    let totalDebits = 0;
    let totalCredits = 0;

    chartOfAccounts.forEach(account => {
        const row = document.createElement('tr');

        // Add class for parent/child styling
        if (account.isParent) {
            row.classList.add('parent-account');
        }

        // Calculate balance
        const balance = account.debit - account.credit;
        
        // Add to totals only for leaf accounts (non-parent) to avoid double-counting
        if (!account.isParent) {
            if (balance > 0) {
                totalDebits += balance;
            } else if (balance < 0) {
                totalCredits += Math.abs(balance);
            }
        }

        // Format amounts with commas
        const formatAmount = (amount) => {
            return amount.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };

        row.dataset.level = account.level;
        row.dataset.code = account.code;
        row.dataset.isParent = account.isParent;

        row.innerHTML = `
                    <td>
                        <div class="account-code">${account.code}</div>
                    </td>
                    <td>
                        <div class="coa-row">
                            <div class="coa-level-${account.level}">
                                ${account.isParent ? '<i class="fas fa-chevron-right toggle-icon" style="font-size: 12px; color: var(--subtext); cursor: pointer; margin-right: 8px;"></i>' : ''}
                                ${account.name}
                            </div>
                        </div>
                    </td>
                    <td class="debit-amount">${balance > 0 ? formatAmount(balance) : '-'}</td>
                    <td class="credit-amount">${balance < 0 ? formatAmount(Math.abs(balance)) : '-'}</td>
                    <td class="text-right">${balance !== 0 ? formatAmount(Math.abs(balance)) : '-'}</td>
                `;

        if (account.isParent) {
            row.style.cursor = 'pointer';
            row.addEventListener('click', function(e) {
                toggleRow(account.code, account.level);
            });
        }

        tableBody.appendChild(row);
    });

    // Update totals
    document.querySelector('.total-row .debit-amount strong').textContent = formatAmount(totalDebits);
    document.querySelector('.total-row .credit-amount strong').textContent = formatAmount(totalCredits);

    // Update validation status
    updateValidationStatus(totalDebits, totalCredits);
}

// Format amount helper
function formatAmount(amount) {
    return amount.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Update validation status based on totals
function updateValidationStatus(totalDebits, totalCredits) {
    const validationEl = document.getElementById('validationStatus');

    if (Math.abs(totalDebits - totalCredits) < 0.01) {
        // Balanced
        validationEl.className = 'validation-status validation-success';
        validationEl.innerHTML = `
                    <i class="fas fa-check-circle text-success"></i>
                    <div>
                        <strong>Trial Balance is in balance!</strong>
                        <div>Total Debits (${currencySymbol}${formatAmount(totalDebits)}) = Total Credits (${currencySymbol}${formatAmount(totalCredits)})</div>
                    </div>
                `;
    } else {
        // Not balanced
        const difference = Math.abs(totalDebits - totalCredits);
        validationEl.className = 'validation-status validation-error';
        validationEl.innerHTML = `
                    <i class="fas fa-exclamation-circle text-error"></i>
                    <div>
                        <strong>Trial Balance is out of balance!</strong>
                        <div>Total Debits (${currencySymbol}${formatAmount(totalDebits)}) ≠ Total Credits (${currencySymbol}${formatAmount(totalCredits)}). Difference: ${currencySymbol}${formatAmount(difference)}</div>
                        <small>Check for missing entries or incorrect amounts.</small>
                    </div>
                `;
    }
}

// Event Listeners
document.getElementById('refreshBtn').addEventListener('click', async function () {
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    await fetchTrialBalanceData();
    this.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
    showNotification('Trial Balance data refreshed successfully.', 'success');
});

document.getElementById('printBtn').addEventListener('click', function () {
    window.print();
});

document.getElementById('exportBtn').addEventListener('click', function () {
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';

    // Simulate export process
    setTimeout(() => {
        this.innerHTML = '<i class="fas fa-download"></i> Export';
        showNotification('Trial Balance exported to CSV successfully.', 'success');
    }, 1000);
});

document.getElementById('dateRange').addEventListener('change', function() {
    const customDateRange = document.getElementById('customDateRange');
    const customDateRange2 = document.getElementById('customDateRange2');
    
    if (this.value === 'custom') {
        customDateRange.classList.remove('hidden');
        customDateRange2.classList.remove('hidden');
    } else {
        customDateRange.classList.add('hidden');
        customDateRange2.classList.add('hidden');
    }
});

document.getElementById('applyFilters').addEventListener('click', function () {
    const dateRange = document.getElementById('dateRange').value;
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;

    if (dateRange === 'custom' && (!fromDate || !toDate)) {
        showNotification('Please select both from and to dates', 'warning');
        return;
    }
    const accountLevel = document.getElementById('accountLevel').value;
    const zeroBalances = document.getElementById('zeroBalances').value;

    chartOfAccounts = allAccounts.filter(account => {
        if (zeroBalances === 'hide' && account.debit === 0 && account.credit === 0) {
            return false;
        }
        if (accountLevel === 'summary' && account.level > 1) {
            return false;
        }
        return true;
    });

    populateTrialBalance();
    
    let message = 'Filters applied successfully';
    if (dateRange === 'custom') {
        message += ` (${fromDate} to ${toDate})`;
    }
    showNotification(message, 'success');
});

document.getElementById('expandAll').addEventListener('click', function () {
    document.querySelectorAll('#trialBalanceBody tr').forEach(row => {
        row.style.display = '';
        const icon = row.querySelector('.toggle-icon');
        if (icon) {
            icon.className = 'fas fa-chevron-down toggle-icon';
        }
    });
    expandedRows.clear();
    chartOfAccounts.forEach(acc => expandedRows.add(acc.code));
    showNotification('All accounts expanded.', 'info');
});

document.getElementById('collapseAll').addEventListener('click', function () {
    const rows = document.querySelectorAll('#trialBalanceBody tr');
    rows.forEach(row => {
        if (row.dataset.level > 0) {
            row.style.display = 'none';
        }
        const icon = row.querySelector('.toggle-icon');
        if (icon) {
            icon.className = 'fas fa-chevron-right toggle-icon';
        }
    });
    expandedRows.clear();
    showNotification('All accounts collapsed.', 'info');
});

function toggleRow(code, level) {
    const rows = document.querySelectorAll('#trialBalanceBody tr');
    let found = false;
    let toggleIcon = null;

    rows.forEach(row => {
        if (row.dataset.code === code) {
            found = true;
            toggleIcon = row.querySelector('.toggle-icon');
            return;
        }
        if (found && parseInt(row.dataset.level) > level) {
            if (row.style.display === 'none') {
                if (parseInt(row.dataset.level) === level + 1) {
                    row.style.display = '';
                }
            } else {
                row.style.display = 'none';
            }
        } else if (found && parseInt(row.dataset.level) <= level) {
            found = false;
        }
    });

    if (toggleIcon) {
        if (toggleIcon.classList.contains('fa-chevron-right')) {
            toggleIcon.className = 'fas fa-chevron-down toggle-icon';
        } else {
            toggleIcon.className = 'fas fa-chevron-right toggle-icon';
        }
    }
}

// Show notification function
function showNotification(message, type) {
    // Remove existing notification
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    // Create notification
    const notification = document.createElement('div');
    notification.className = `validation-status validation-${type}`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '1000';
    notification.style.maxWidth = '350px';
    notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';

    const icon = type === 'success' ? 'fa-check-circle' :
        type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
    const iconColor = type === 'success' ? 'success' :
        type === 'error' ? 'error' : 'warning';

    notification.innerHTML = `
                <i class="fas ${icon} text-${iconColor}"></i>
                <div>${message}</div>
            `;

    notification.classList.add('notification');
    document.body.appendChild(notification);

    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function () {
    fetchTrialBalanceData();

    const isFirstVisit = !localStorage.getItem('trialBalanceVisited');
    if (isFirstVisit) {
        setTimeout(() => {
            showNotification('Welcome to Trial Balance Report! Check the help section for tips.', 'info');
            localStorage.setItem('trialBalanceVisited', 'true');
        }, 1000);
    }
});