// Initialize variables
let pdcsData = [];
let currentPage = 1;
const itemsPerPage = 10;
let filteredData = [];
let companies = [];
let allBankAccounts = [];

// DOM Elements
const tableBody = document.getElementById('pdcs-table-body');
const totalPdcsEl = document.getElementById('total-pdcs');
const pendingPdcsEl = document.getElementById('pending-pdcs');
const totalAmountEl = document.getElementById('total-amount');
const avgAmountEl = document.getElementById('avg-amount');
const paginationInfoEl = document.getElementById('pagination-info');
const applyFiltersBtn = document.getElementById('apply-filters');
const resetFiltersBtn = document.getElementById('reset-filters');
const addPdcBtn = document.getElementById('add-pdc');
const prevPageBtn = document.getElementById('prev-page');
const nextPageBtn = document.getElementById('next-page');

// Initialize the application
async function initApp() {
    await loadBanks();
    await loadCompanies();
    await loadBankAccounts();
    await fetchPDCs();
    setupEventListeners();
}

// Load banks for filter dropdown
async function loadBanks() {
    try {
        const response = await fetch('../../../../server/api/banking/post_dated_cheques/get_banks.php');
        const result = await response.json();
        
        if (result.success) {
            const bankFilter = document.getElementById('bank-filter');
            result.data.forEach(bank => {
                const option = document.createElement('option');
                option.value = bank;
                option.textContent = bank;
                bankFilter.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading banks:', error);
    }
}

// Load all bank accounts for approve modal
async function loadBankAccounts() {
    try {
        const response = await fetch('../../../../server/api/banking/post_dated_cheques/get_bank_accounts.php');
        const result = await response.json();
        if (result.success) {
            allBankAccounts = result.data;
        }
    } catch (error) {
        console.error('Error loading bank accounts:', error);
    }
}

// Load companies for filter dropdown
async function loadCompanies() {
    try {
        const response = await fetch('../../../../server/api/banking/post_dated_cheques/get_companies.php');
        const result = await response.json();
        
        if (result.success) {
            companies = result.data;
            const companyFilter = document.getElementById('company-filter');
            result.data.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.company_name;
                companyFilter.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading companies:', error);
    }
}

// Fetch PDCs from API
async function fetchPDCs() {
    try {
        const params = new URLSearchParams();
        
        const chequeNo = document.getElementById('cheque-no-filter').value;
        const account = document.getElementById('account-filter').value;
        const bank = document.getElementById('bank-filter').value;
        const status = document.getElementById('status-filter').value;
        const type = document.getElementById('type-filter').value;
        const dateFilter = document.getElementById('date-filter').value;
        const minAmount = document.getElementById('min-amount').value;
        const maxAmount = document.getElementById('max-amount').value;
        const companyId = document.getElementById('company-filter').value;
        
        if (chequeNo) params.append('cheque_no', chequeNo);
        if (account) params.append('account', account);
        if (bank) params.append('bank', bank);
        if (status) params.append('status', status);
        if (type) params.append('type', type);
        if (dateFilter) params.append('date_filter', dateFilter);
        if (minAmount) params.append('min_amount', minAmount);
        if (maxAmount) params.append('max_amount', maxAmount);
        if (companyId) params.append('company_id', companyId);
        
        const response = await fetch(`../../../../server/api/banking/post_dated_cheques/post_dated_cheques.php?${params}`);
        const result = await response.json();
        
        if (result.success) {
            pdcsData = result.data;
            filteredData = [...pdcsData];
            currentPage = 1;
            renderTable();
            updateStats();
            initializeChart();
        } else {
            showNotification(result.message || 'Failed to fetch PDCs', 'error');
        }
    } catch (error) {
        console.error('Error fetching PDCs:', error);
        showNotification('Error loading data', 'error');
    }
}

// Render table with current data
function renderTable() {
    tableBody.innerHTML = '';

    // Calculate pagination
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = filteredData.slice(startIndex, endIndex);

    if (pageData.length === 0) {
        tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            No PDCs found matching your filters.
                        </td>
                    </tr>
                `;
        return;
    }

    // Create table rows
    pageData.forEach(pdc => {
        const row = document.createElement('tr');

        // Format date
        const formattedDate = new Date(pdc.date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });

        // Format amount
        const formattedAmount = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(pdc.amount).replace('$', CURRENCY_SYMBOL);

        // Partial paid info
        const isPartiallyPaid = pdc.paidAmount > 0 && pdc.status === 'Pending';
        const isFullyPaid = pdc.paidAmount >= pdc.amount && pdc.status === 'Pending';
        const paidBadge = isFullyPaid
            ? `<span style="display:inline-block;margin-left:6px;padding:2px 8px;border-radius:12px;font-size:11px;background:#e6f7ef;color:#2fbf71;font-weight:600;">Fully Paid</span>`
            : isPartiallyPaid
            ? `<span style="display:inline-block;margin-left:6px;padding:2px 8px;border-radius:12px;font-size:11px;background:#fff5e6;color:#e8b23f;font-weight:600;">Partial ${CURRENCY_SYMBOL}${pdc.paidAmount.toFixed(2)}/${CURRENCY_SYMBOL}${pdc.amount.toFixed(2)}</span>`
            : '';

        // Approve button — hide if fully paid via cash
        const canApprove = pdc.status === 'Pending' && !isFullyPaid;

        // Determine status class
        let statusClass = '';
        switch (pdc.status) {
            case 'Pending': statusClass = 'status-pending'; break;
            case 'Approved': statusClass = 'status-approved'; break;
            case 'Rejected': statusClass = 'status-rejected'; break;
            case 'Cancelled': statusClass = 'status-cancelled'; break;
        }

        // Create row HTML
        row.innerHTML = `
                    <td>${pdc.chequeNo}</td>
                    <td>${pdc.account}</td>
                    <td>${pdc.bankName}</td>
                    <td>${pdc.type}</td>
                    <td><strong>${formattedAmount}</strong>${paidBadge}</td>
                    <td>${formattedDate}</td>
                    <td><span class="status-badge ${statusClass}">${pdc.status}</span></td>
                    <td>
                        <div class="action-buttons">
                            ${canApprove ? `
                                <button class="action-btn action-approve" onclick="updateStatus(${pdc.id}, 'Approved')" title="Approve">
                                    <i class="fas fa-check"></i>
                                </button>
                            ` : ''}
                            ${pdc.status === 'Pending' ? `
                                <button class="action-btn action-reject" onclick="updateStatus(${pdc.id}, 'Rejected')" title="Reject">
                                    <i class="fas fa-times"></i>
                                </button>
                            ` : ''}
                            ${(pdc.paidAmount > 0) ? `
                                <button class="action-btn" style="color:#1f7bff;background:rgba(31,123,255,0.1);" onclick="viewPdcPayments(${pdc.id})" title="View Payments">
                                    <i class="fas fa-eye"></i>
                                </button>
                            ` : ''}
                            <button class="action-btn action-cancel" onclick="updateStatus(${pdc.id}, 'Cancelled')" title="Cancel">
                                <i class="fas fa-ban"></i>
                            </button>
                        </div>
                    </td>
                `;

        tableBody.appendChild(row);
    });

    // Update pagination info
    updatePaginationInfo();
}

// Update pagination information
function updatePaginationInfo() {
    const startIndex = (currentPage - 1) * itemsPerPage + 1;
    const endIndex = Math.min(startIndex + itemsPerPage - 1, filteredData.length);
    paginationInfoEl.textContent = `Showing ${startIndex}-${endIndex} of ${filteredData.length} PDCs`;

    // Update pagination button states
    prevPageBtn.disabled = currentPage === 1;
    nextPageBtn.disabled = endIndex >= filteredData.length;
    
    // Generate page number buttons
    const paginationControls = document.getElementById('pagination-controls');
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    
    // Clear existing page buttons (keep prev/next)
    const pageButtons = paginationControls.querySelectorAll('.pagination-btn:not(#prev-page):not(#next-page)');
    pageButtons.forEach(btn => btn.remove());
    
    // Insert page number buttons before next button
    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = 'pagination-btn' + (i === currentPage ? ' active' : '');
        pageBtn.textContent = i;
        pageBtn.addEventListener('click', () => {
            currentPage = i;
            renderTable();
        });
        paginationControls.insertBefore(pageBtn, nextPageBtn);
    }
}

// Update stats cards
function updateStats() {
    // Calculate stats
    const totalPdcs = filteredData.length;
    const pendingPdcs = filteredData.filter(pdc => pdc.status === 'Pending').length;
    const totalAmount = filteredData.reduce((sum, pdc) => sum + pdc.amount, 0);
    const avgAmount = totalPdcs > 0 ? totalAmount / totalPdcs : 0;

    // Update DOM
    totalPdcsEl.textContent = totalPdcs;
    pendingPdcsEl.textContent = pendingPdcs;
    totalAmountEl.textContent = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0
    }).format(totalAmount).replace('$', CURRENCY_SYMBOL);
    avgAmountEl.textContent = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0
    }).format(avgAmount).replace('$', CURRENCY_SYMBOL);
}

// Apply filters
async function applyFilters() {
    await fetchPDCs();
}

// Reset filters
async function resetFilters() {
    document.getElementById('cheque-no-filter').value = '';
    document.getElementById('account-filter').value = '';
    document.getElementById('bank-filter').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('type-filter').value = '';
    document.getElementById('date-filter').value = '';
    document.getElementById('min-amount').value = '';
    document.getElementById('max-amount').value = '';
    document.getElementById('company-filter').value = '';

    await fetchPDCs();
}

// Update PDC status
let pendingStatusUpdate = null;

async function updateStatus(id, newStatus) {
    if (newStatus === 'Approved') {
        // Open approve modal with bank selection
        const pdc = filteredData.find(p => p.id === id);
        pendingStatusUpdate = { id, newStatus };
        
        document.getElementById('approve-cheque-no').textContent = pdc?.chequeNo || '';
        document.getElementById('approve-amount').textContent = 
            CURRENCY_SYMBOL + parseFloat(pdc?.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
        
        // Populate bank dropdown
        const select = document.getElementById('approve-bank-select');
        select.innerHTML = '';
        allBankAccounts.forEach(bank => {
            const opt = document.createElement('option');
            opt.value = bank.id;
            opt.textContent = `${bank.bank_name} - ${bank.account_number}`;
            if (bank.id == pdc?.bankAccountId) opt.selected = true;
            select.appendChild(opt);
        });
        
        document.getElementById('approve-modal').classList.add('active');
    } else {
        pendingStatusUpdate = { id, newStatus };
        const modal = document.getElementById('confirmation-modal');
        const message = document.getElementById('modal-message');
        message.textContent = `Are you sure you want to change the status to "${newStatus}"?`;
        modal.classList.add('active');
    }
}

async function confirmStatusUpdate(bankAccountId = null) {
    if (!pendingStatusUpdate) return;
    
    const { id, newStatus } = pendingStatusUpdate;
    document.getElementById('confirmation-modal').classList.remove('active');
    document.getElementById('approve-modal').classList.remove('active');
    
    try {
        const body = { id, status: newStatus };
        if (bankAccountId) body.bank_account_id = parseInt(bankAccountId);
        
        const response = await fetch('../../../../server/api/banking/post_dated_cheques/update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(`PDC status updated to ${newStatus}`);
            await fetchPDCs();
        } else {
            showNotification(result.message || 'Failed to update status', 'error');
        }
    } catch (error) {
        console.error('Error updating status:', error);
        showNotification('Error updating status', 'error');
    }
    
    pendingStatusUpdate = null;
}

// View payments against a PDC
async function viewPdcPayments(pdcId) {
    try {
        const response = await fetch(`../../../../server/api/banking/post_dated_cheques/get-pdc-payments.php?pdc_id=${pdcId}`);
        const result = await response.json();
        if (!result.success) { showNotification('Failed to load payments', 'error'); return; }

        const pdc = result.pdc;
        const payments = result.data;
        const totalPaid = result.total_paid;
        const remaining = result.remaining;

        const chequeDate = new Date(pdc.cheque_date).toLocaleDateString();
        const progressPct = Math.min((totalPaid / pdc.amount) * 100, 100).toFixed(1);

        let rows = payments.length === 0
            ? `<tr><td colspan="4" style="text-align:center;color:#6b7280;padding:20px;">No cash payments yet</td></tr>`
            : payments.map(p => `
                <tr>
                    <td style="padding:10px;font-size:14px;border-bottom:1px solid #e1e6ee;">${p.voucher_number}</td>
                    <td style="padding:10px;font-size:14px;border-bottom:1px solid #e1e6ee;">${new Date(p.voucher_date).toLocaleDateString()}</td>
                    <td style="padding:10px;font-size:14px;border-bottom:1px solid #e1e6ee;">${p.payment_method || 'N/A'}</td>
                    <td style="padding:10px;font-size:14px;border-bottom:1px solid #e1e6ee;"><strong>${CURRENCY_SYMBOL}${parseFloat(p.amount).toFixed(2)}</strong></td>
                </tr>`).join('');

        document.getElementById('pdc-payments-body').innerHTML = `
            <div style="margin-bottom:16px;padding:12px;background:#f7f9fc;border-radius:8px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                    <span><strong>Cheque:</strong> ${pdc.cheque_no} &mdash; ${pdc.bank_name || 'N/A'}</span>
                    <span><strong>Date:</strong> ${chequeDate}</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                    <span>Total: <strong>${CURRENCY_SYMBOL}${parseFloat(pdc.amount).toFixed(2)}</strong></span>
                    <span>Paid: <strong style="color:#2fbf71;">${CURRENCY_SYMBOL}${totalPaid.toFixed(2)}</strong></span>
                    <span>Remaining: <strong style="color:${remaining > 0 ? '#e8b23f' : '#2fbf71'}">${CURRENCY_SYMBOL}${remaining.toFixed(2)}</strong></span>
                </div>
                <div style="background:#e1e6ee;border-radius:20px;height:8px;">
                    <div style="background:#2fbf71;height:8px;border-radius:20px;width:${progressPct}%;transition:width 0.3s;"></div>
                </div>
                <div style="text-align:right;font-size:12px;color:#6b7280;margin-top:4px;">${progressPct}% cleared</div>
            </div>
            <div style="overflow-x:auto;">
            <table style="width:100%;min-width:unset;border-collapse:collapse;">
                <thead><tr style="background:#f7f9fc;">
                    <th style="padding:10px;text-align:left;font-size:13px;font-weight:600;color:#6b7280;border-bottom:1px solid #e1e6ee;min-width:unset;">Voucher #</th>
                    <th style="padding:10px;text-align:left;font-size:13px;font-weight:600;color:#6b7280;border-bottom:1px solid #e1e6ee;min-width:unset;">Date</th>
                    <th style="padding:10px;text-align:left;font-size:13px;font-weight:600;color:#6b7280;border-bottom:1px solid #e1e6ee;min-width:unset;">Method</th>
                    <th style="padding:10px;text-align:left;font-size:13px;font-weight:600;color:#6b7280;border-bottom:1px solid #e1e6ee;min-width:unset;">Amount</th>
                </tr></thead>
                <tbody>${rows}</tbody>
            </table>
            </div>`;

        document.getElementById('pdc-payments-modal').classList.add('active');
    } catch (err) {
        showNotification('Error loading payments', 'error');
    }
}

// Show notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    const color = type === 'success' ? '#2FBF71' : '#E34F4F';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-left: 4px solid ${color};
        z-index: 1000;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideIn 0.3s ease;
    `;

    notification.innerHTML = `
        <i class="fas ${icon}" style="color: ${color};"></i>
        <span>${message}</span>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => document.body.removeChild(notification), 300);
    }, 3000);

    if (!document.getElementById('notification-styles')) {
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

// Initialize chart
function initializeChart() {
    const ctx = document.getElementById('pdcs-chart').getContext('2d');

    // Calculate data for chart
    const statusCounts = {
        'Pending': filteredData.filter(pdc => pdc.status === 'Pending').length,
        'Approved': filteredData.filter(pdc => pdc.status === 'Approved').length,
        'Rejected': filteredData.filter(pdc => pdc.status === 'Rejected').length,
        'Cancelled': filteredData.filter(pdc => pdc.status === 'Cancelled').length
    };

    const typeCounts = {
        'Received': filteredData.filter(pdc => pdc.type === 'Received').length,
        'Paid': filteredData.filter(pdc => pdc.type === 'Paid').length,
        'Expense': filteredData.filter(pdc => pdc.type === 'Expense').length
    };

    // Destroy existing chart if it exists
    if (window.pdcsChart) {
        window.pdcsChart.destroy();
    }

    // Create new chart
    window.pdcsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Pending', 'Approved', 'Rejected', 'Cancelled'],
            datasets: [{
                label: 'PDCs by Status',
                data: [
                    statusCounts.Pending,
                    statusCounts.Approved,
                    statusCounts.Rejected,
                    statusCounts.Cancelled
                ],
                backgroundColor: [
                    'rgba(232, 178, 63, 0.7)',
                    'rgba(47, 191, 113, 0.7)',
                    'rgba(227, 79, 79, 0.7)',
                    'rgba(107, 114, 128, 0.7)'
                ],
                borderColor: [
                    '#E8B23F',
                    '#2FBF71',
                    '#E34F4F',
                    '#6B7280'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#EFF2F7'
                    },
                    ticks: {
                        color: '#6B7280'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#6B7280'
                    }
                }
            }
        }
    });
}

// Set up event listeners
function setupEventListeners() {
    applyFiltersBtn.addEventListener('click', applyFilters);
    resetFiltersBtn.addEventListener('click', resetFilters);

    addPdcBtn.addEventListener('click', () => {
        showNotification('Add PDC feature would open a form in a real application');
    });

    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    });

    nextPageBtn.addEventListener('click', () => {
        const maxPage = Math.ceil(filteredData.length / itemsPerPage);
        if (currentPage < maxPage) {
            currentPage++;
            renderTable();
        }
    });

    document.getElementById('cheque-no-filter').addEventListener('keyup', (e) => {
        if (e.key === 'Enter') applyFilters();
    });

    document.getElementById('account-filter').addEventListener('keyup', (e) => {
        if (e.key === 'Enter') applyFilters();
    });
    
    // Modal event listeners
    document.getElementById('modal-confirm').addEventListener('click', confirmStatusUpdate);
    document.getElementById('modal-cancel').addEventListener('click', () => {
        document.getElementById('confirmation-modal').classList.remove('active');
        pendingStatusUpdate = null;
    });
    
    document.getElementById('confirmation-modal').addEventListener('click', (e) => {
        if (e.target.id === 'confirmation-modal') {
            document.getElementById('confirmation-modal').classList.remove('active');
            pendingStatusUpdate = null;
        }
    });
    
    // Approve modal event listeners
    document.getElementById('approve-modal-confirm').addEventListener('click', () => {
        const bankAccountId = document.getElementById('approve-bank-select').value;
        confirmStatusUpdate(bankAccountId);
    });
    document.getElementById('approve-modal-cancel').addEventListener('click', () => {
        document.getElementById('approve-modal').classList.remove('active');
        pendingStatusUpdate = null;
    });
    document.getElementById('approve-modal').addEventListener('click', (e) => {
        if (e.target.id === 'approve-modal') {
            document.getElementById('approve-modal').classList.remove('active');
            pendingStatusUpdate = null;
        }
    });

    // PDC payments modal
    document.getElementById('pdc-payments-close').addEventListener('click', () => {
        document.getElementById('pdc-payments-modal').classList.remove('active');
    });
    document.getElementById('pdc-payments-modal').addEventListener('click', (e) => {
        if (e.target.id === 'pdc-payments-modal') {
            document.getElementById('pdc-payments-modal').classList.remove('active');
        }
    });
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', initApp);