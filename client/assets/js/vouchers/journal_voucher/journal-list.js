let journalEntries = [];
let currencySymbol = '$';
let companies = [];

// Current state
let currentPage = 1;
const entriesPerPage = 8;
let filteredEntries = [...journalEntries];


// Initialize page
document.addEventListener('DOMContentLoaded', async function () {
    // Set default date filters (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);

    document.getElementById('filterDateFrom').value = thirtyDaysAgo.toISOString().split('T')[0];
    document.getElementById('filterDateTo').value = today.toISOString().split('T')[0];

    // Fetch data
    await fetchCompanies();
    await fetchJournalEntries();

    // Set up event listeners
    setupEventListeners();
});

// Fetch companies
async function fetchCompanies() {
    try {
        const response = await fetch('../../../../server/api/vouchers/journal_voucher/get-companies.php');
        const result = await response.json();
        
        if (response.ok && result.success) {
            companies = result.data;
            const filterCompany = document.getElementById('filterCompany');
            result.data.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.company_name;
                filterCompany.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error fetching companies:', error);
    }
}

// Fetch journal entries from API
async function fetchJournalEntries() {
    try {
        const dateFrom = document.getElementById('filterDateFrom').value;
        const dateTo = document.getElementById('filterDateTo').value;
        const status = document.getElementById('filterStatus').value;

        const params = new URLSearchParams();
        if (dateFrom) params.append('dateFrom', dateFrom);
        if (dateTo) params.append('dateTo', dateTo);
        if (status) params.append('status', status);
        
        const companyId = document.getElementById('filterCompany').value;
        if (companyId) params.append('company_id', companyId);

        const response = await fetch(`../../../../server/api/vouchers/journal_voucher/journal-list.php?${params}`);
        const result = await response.json();

        if (response.ok && result.success) {
            journalEntries = result.entries.map(entry => ({
                id: entry.id,
                voucherNumber: entry.voucher_number,
                date: entry.voucher_date,
                description: entry.description,
                debitTotal: parseFloat(entry.total_debit),
                creditTotal: parseFloat(entry.total_credit),
                status: entry.status || 'posted',
                postedBy: entry.posted_by || '',
                postedOn: entry.posted_on || '',
                companyName: entry.company_name || '',
                entries: entry.entries.map(line => ({
                    account: line.account,
                    debit: parseFloat(line.debit),
                    credit: parseFloat(line.credit)
                }))
            }));

            // Update stats
            if (result.stats) {
                document.getElementById('totalEntries').textContent = result.stats.total || 0;
                document.getElementById('postedEntries').textContent = result.stats.posted || 0;
                document.getElementById('pendingEntries').textContent = result.stats.pending || 0;
                document.getElementById('draftEntries').textContent = result.stats.draft || 0;
            }

            // Update currency symbol
            if (result.currencySymbol) {
                currencySymbol = result.currencySymbol;
            }

            filteredEntries = [...journalEntries];
            renderTable();
        } else {
            console.error('Failed to fetch journal entries:', result.error);
        }
    } catch (error) {
        console.error('Error fetching journal entries:', error);
    }
}



// Render table with current data
function renderTable() {
    const tableBody = document.getElementById('journalTableBody');
    const noDataState = document.getElementById('noDataState');

    if (filteredEntries.length === 0) {
        tableBody.innerHTML = '';
        document.querySelector('.pagination').style.display = 'none';
        noDataState.style.display = 'block';
        return;
    }

    noDataState.style.display = 'none';
    document.querySelector('.pagination').style.display = 'flex';

    // Calculate pagination
    const totalPages = Math.ceil(filteredEntries.length / entriesPerPage);
    const startIndex = (currentPage - 1) * entriesPerPage;
    const endIndex = Math.min(startIndex + entriesPerPage, filteredEntries.length);
    const pageEntries = filteredEntries.slice(startIndex, endIndex);

    // Update pagination info
    document.getElementById('startEntry').textContent = startIndex + 1;
    document.getElementById('endEntry').textContent = endIndex;
    document.getElementById('totalEntriesCount').textContent = filteredEntries.length;

    // Update pagination buttons
    updatePaginationControls(totalPages);

    // Clear table
    tableBody.innerHTML = '';

    // Add rows
    pageEntries.forEach(entry => {
        const row = document.createElement('tr');

        // Format currency
        const formatCurrency = (amount) => {
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        };

        // Format date
        const formatDate = (dateString) => {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        };

        // Status badge
        let statusBadge = '';
        switch (entry.status) {
            case 'posted':
                statusBadge = `<span class="status-badge status-posted">Posted</span>`;
                break;
            case 'pending':
                statusBadge = `<span class="status-badge status-pending">Pending</span>`;
                break;
            case 'draft':
                statusBadge = `<span class="status-badge status-draft">Draft</span>`;
                break;
            case 'rejected':
                statusBadge = `<span class="status-badge status-rejected">Rejected</span>`;
                break;
        }

        // Action buttons - show delete for all entries
        let actionButtons = `<button class="table-action-btn view" onclick="viewEntry(${entry.id})" title="View">
                   <i class="fas fa-eye"></i>
               </button>`;
        
        if (entry.status === 'draft') {
            actionButtons += `
               <button class="table-action-btn edit" onclick="editEntry(${entry.id})" title="Edit">
                   <i class="fas fa-edit"></i>
               </button>
               <button class="table-action-btn" style="color: #2FBF71;" onclick="confirmPostDraft(${entry.id})" title="Post Voucher">
                   <i class="fas fa-check-circle"></i>
               </button>`;
        } else {
            actionButtons += `
               <button class="table-action-btn edit" onclick="reverseEntry(${entry.id})" title="Reverse Journal Entry">
                   <i class="fas fa-undo"></i>
               </button>`;
        }
        
        // Delete button for all entries
        actionButtons += `
               <button class="table-action-btn delete" onclick="confirmDelete(${entry.id})" title="Delete">
                   <i class="fas fa-trash"></i>
               </button>`;

        row.innerHTML = `
                    <td>${entry.id}</td>
                    <td><strong>${entry.voucherNumber}</strong></td>
                    <td>${formatDate(entry.date)}</td>
                    <td>${entry.description}</td>
                    <td>${currencySymbol}${formatCurrency(entry.debitTotal)}</td>
                    <td>${currencySymbol}${formatCurrency(entry.creditTotal)}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="table-actions">
                            ${actionButtons}
                        </div>
                    </td>
                `;

        tableBody.appendChild(row);
    });
}

// Update pagination controls
function updatePaginationControls(totalPages) {
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    const paginationControls = document.querySelector('.pagination-controls');

    // Update previous/next buttons
    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = currentPage === totalPages;

    // Remove existing page number buttons (except prev/next)
    const existingPageButtons = paginationControls.querySelectorAll('.pagination-btn:not(#prevPageBtn):not(#nextPageBtn)');
    existingPageButtons.forEach(btn => btn.remove());

    // Add page number buttons
    let startPage = Math.max(1, currentPage - 1);
    let endPage = Math.min(totalPages, startPage + 2);

    // Adjust if we're at the end
    if (endPage - startPage < 2 && startPage > 1) {
        startPage = Math.max(1, endPage - 2);
    }

    // Insert page buttons before the next button
    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
        pageBtn.textContent = i;
        pageBtn.onclick = () => {
            currentPage = i;
            renderTable();
        };

        nextBtn.parentNode.insertBefore(pageBtn, nextBtn);
    }
}

// Apply filters
async function applyFilters() {
    currentPage = 1;
    await fetchJournalEntries();
}

// Reset filters
async function resetFilters() {
    // Reset to default dates (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);

    document.getElementById('filterDateFrom').value = thirtyDaysAgo.toISOString().split('T')[0];
    document.getElementById('filterDateTo').value = today.toISOString().split('T')[0];
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterCompany').value = '';

    currentPage = 1;
    await fetchJournalEntries();
}

// View entry details
function viewEntry(id) {
    const entry = journalEntries.find(e => e.id === id);
    if (!entry) return;

    // Format date
    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    // Format datetime
    const formatDateTime = (dateTimeString) => {
        if (!dateTimeString) return 'N/A';
        const date = new Date(dateTimeString);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    // Populate modal
    document.getElementById('modalVoucher').textContent = entry.voucherNumber;
    document.getElementById('modalDate').textContent = formatDate(entry.date);
    document.getElementById('modalCompany').textContent = entry.companyName || '-';
    document.getElementById('modalDescription').textContent = entry.description;
    document.getElementById('modalPostedBy').textContent = entry.postedBy || 'N/A';
    document.getElementById('modalPostedOn').textContent = formatDateTime(entry.postedOn);

    // Status badge
    const statusElement = document.getElementById('modalStatus');
    statusElement.textContent = entry.status.charAt(0).toUpperCase() + entry.status.slice(1);
    statusElement.className = `status-badge status-${entry.status}`;

    // Entry lines
    const entryLinesBody = document.getElementById('modalEntryLines');
    entryLinesBody.innerHTML = '';

    entry.entries.forEach(line => {
        const row = document.createElement('tr');
        row.innerHTML = `
                    <td>${line.account}</td>
                    <td>${line.debit > 0 ? currencySymbol + line.debit.toFixed(2) : '-'}</td>
                    <td>${line.credit > 0 ? currencySymbol + line.credit.toFixed(2) : '-'}</td>
                `;
        entryLinesBody.appendChild(row);
    });

    // Totals
    document.getElementById('modalTotalDebit').textContent = currencySymbol + entry.debitTotal.toFixed(2);
    document.getElementById('modalTotalCredit').textContent = currencySymbol + entry.creditTotal.toFixed(2);

    // Show modal
    document.getElementById('viewEntryModal').classList.add('show');
}

// Edit entry
function editEntry(id) {
    window.location.href = `journal-add.php?edit=${id}`;
}

// Reverse entry
function reverseEntry(id) {
    const entry = journalEntries.find(e => e.id === id);
    if (entry && confirm(`Create a reversing entry for ${entry.voucherNumber}?`)) {
        window.location.href = `journal-add.php?reverse=${id}`;
    }
}

// Confirm delete
let entryToDelete = null;
function confirmDelete(id) {
    const entry = journalEntries.find(e => e.id === id);
    if (!entry) return;

    entryToDelete = id;
    document.getElementById('deleteVoucherName').textContent = entry.voucherNumber;
    document.getElementById('deleteModal').classList.add('show');
}

// Delete entry
async function deleteEntry() {
    if (!entryToDelete) return;

    const deleteBtn = document.getElementById('confirmDeleteBtn');
    const originalHtml = deleteBtn.innerHTML;
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

    try {
        const response = await fetch(`../../../../server/api/vouchers/journal_voucher/journal-delete.php?id=${entryToDelete}`, {
            method: 'DELETE'
        });
        const result = await response.json();

        if (response.ok && result.success) {
            console.log(`Deleted: ${result.voucherNumber} (${result.linesDeleted} lines removed)`);
            document.getElementById('deleteModal').classList.remove('show');
            entryToDelete = null;
            currentPage = 1;
            await fetchJournalEntries();
        } else {
            alert(result.error || 'Failed to delete entry');
        }
    } catch (error) {
        alert('Error deleting entry: ' + error.message);
    } finally {
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = originalHtml;
    }
}

// Confirm post draft
let draftToPost = null;
function confirmPostDraft(id) {
    const entry = journalEntries.find(e => e.id === id);
    if (!entry) return;

    draftToPost = id;
    document.getElementById('postDraftVoucherName').textContent = entry.voucherNumber;
    document.getElementById('postDraftModal').classList.add('show');
}

// Post draft entry
async function postDraftEntry() {
    if (!draftToPost) return;

    try {
        const response = await fetch(`../../../../server/api/vouchers/journal_voucher/journal-post-draft.php?id=${draftToPost}`, {
            method: 'POST'
        });
        const result = await response.json();

        if (response.ok && result.success) {
            await fetchJournalEntries();
            document.getElementById('postDraftModal').classList.remove('show');
            draftToPost = null;
        } else {
            alert(result.error || 'Failed to post entry');
        }
    } catch (error) {
        alert('Error posting entry: ' + error.message);
    }
}

// Export data
function exportData() {
    alert(`Exporting ${filteredEntries.length} journal entries...`);
    const dataStr = JSON.stringify(filteredEntries, null, 2);
    const dataUri = 'data:application/json;charset=utf-8,' + encodeURIComponent(dataStr);

    const exportFileDefaultName = `journal-entries-${new Date().toISOString().split('T')[0]}.json`;

    const linkElement = document.createElement('a');
    linkElement.setAttribute('href', dataUri);
    linkElement.setAttribute('download', exportFileDefaultName);
    linkElement.click();
}

// Setup event listeners
function setupEventListeners() {
    // Filter buttons
    document.getElementById('applyFiltersBtn').addEventListener('click', applyFilters);
    document.getElementById('resetFiltersBtn').addEventListener('click', resetFilters);
    document.getElementById('clearFiltersBtn').addEventListener('click', resetFilters);

    // New entry button
    document.getElementById('newEntryBtn').addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = 'journal-add.php';
    });

    // Export button
    document.getElementById('exportBtn').addEventListener('click', exportData);

    // Pagination buttons
    document.getElementById('prevPageBtn').addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    });

    document.getElementById('nextPageBtn').addEventListener('click', function () {
        const totalPages = Math.ceil(filteredEntries.length / entriesPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
        }
    });

    // Modal close buttons
    document.getElementById('closeViewModal').addEventListener('click', function () {
        document.getElementById('viewEntryModal').classList.remove('show');
    });

    document.getElementById('closeModalBtn').addEventListener('click', function () {
        document.getElementById('viewEntryModal').classList.remove('show');
    });

    // Print button
    document.getElementById('printEntryBtn').addEventListener('click', function () {
        window.print();
    });

    document.getElementById('closeDeleteModal').addEventListener('click', function () {
        document.getElementById('deleteModal').classList.remove('show');
        entryToDelete = null;
    });

    document.getElementById('cancelDeleteBtn').addEventListener('click', function () {
        document.getElementById('deleteModal').classList.remove('show');
        entryToDelete = null;
    });

    document.getElementById('confirmDeleteBtn').addEventListener('click', deleteEntry);

    document.getElementById('closePostDraftModal').addEventListener('click', function () {
        document.getElementById('postDraftModal').classList.remove('show');
        draftToPost = null;
    });

    document.getElementById('cancelPostDraftBtn').addEventListener('click', function () {
        document.getElementById('postDraftModal').classList.remove('show');
        draftToPost = null;
    });

    document.getElementById('confirmPostDraftBtn').addEventListener('click', postDraftEntry);

    // Close modals when clicking outside
    window.addEventListener('click', function (event) {
        const viewModal = document.getElementById('viewEntryModal');
        const deleteModal = document.getElementById('deleteModal');
        const postDraftModal = document.getElementById('postDraftModal');

        if (event.target === viewModal) {
            viewModal.classList.remove('show');
        }

        if (event.target === deleteModal) {
            deleteModal.classList.remove('show');
            entryToDelete = null;
        }

        if (event.target === postDraftModal) {
            postDraftModal.classList.remove('show');
            draftToPost = null;
        }
    });
}
