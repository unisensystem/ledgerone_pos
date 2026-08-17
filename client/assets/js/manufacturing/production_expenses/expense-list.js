// Production Expenses List - Real Data Integration

let currentPage = 1;
let rowsPerPage = 10;
let filteredData = [];
let totalPages = 1;

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatCurrency(amount) {
    return 'Rs. ' + parseFloat(amount).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadExpenses() {
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    const poFilter = document.getElementById('filterPo').value.trim();
    const statusFilter = document.getElementById('filterStatus').value;
    
    const params = new URLSearchParams({
        page: currentPage,
        per_page: rowsPerPage
    });
    
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    if (poFilter) params.append('po_filter', poFilter);
    if (statusFilter) params.append('status', statusFilter);
    
    try {
        const response = await fetch(`../../../../server/api/manufacturing/production_expenses/expense-list.php?${params}`);
        const result = await response.json();
        
        if (result.success) {
            filteredData = result.data;
            totalPages = result.pagination.total_pages;
            renderTable();
        } else {
            showToast('error', 'Error', result.message);
        }
    } catch (error) {
        console.error('Error loading expenses:', error);
        showToast('error', 'Error', 'Failed to load expenses');
    }
}

function applyFilters() {
    currentPage = 1;
    loadExpenses();
}

function renderTable() {
    const tbody = document.getElementById('tableBody');
    
    if (filteredData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="empty-state"><i class="fas fa-inbox"></i><br/>No production expense entries found</td></tr>`;
        document.getElementById('pageInfo').innerText = `Page 1 of 1`;
        document.getElementById('prevPageBtn').disabled = true;
        document.getElementById('nextPageBtn').disabled = true;
        return;
    }

    tbody.innerHTML = filteredData.map(exp => `
      <tr onclick="viewExpenseDetail('${exp.id}')" style="cursor:pointer">
        <td><strong>${exp.id}</strong></td>
        <td>${formatDate(exp.entry_date)}</td>
        <td>${exp.po_number}</td>
        <td>${exp.finished_good}</td>
        <td class="amount-cell">${formatCurrency(exp.total_amount)}</td>
        <td>${getStatusBadge(exp.status)}</td>
        <td class="action-icons" onclick="event.stopPropagation()">
          <i class="fas fa-eye" onclick="viewExpenseDetail('${exp.id}')" title="View Details"></i>
          <i class="fas fa-edit" onclick="editExpense('${exp.id}')" title="Edit Entry"></i>
          <i class="fas fa-trash-alt" onclick="deleteExpense('${exp.id}')" title="Delete"></i>
        </td>
      </tr>
    `).join('');

    document.getElementById('pageInfo').innerText = `Page ${currentPage} of ${totalPages}`;
    document.getElementById('prevPageBtn').disabled = (currentPage === 1);
    document.getElementById('nextPageBtn').disabled = (currentPage === totalPages);
}

function getStatusBadge(status) {
    if (status === 'Posted') return `<span class="status-badge status-posted"><i class="fas fa-check-circle"></i> Posted</span>`;
    if (status === 'Draft') return `<span class="status-badge status-draft"><i class="fas fa-pen-fancy"></i> Draft</span>`;
    if (status === 'Cancelled') return `<span class="status-badge status-cancelled"><i class="fas fa-ban"></i> Cancelled</span>`;
    return `<span>${status}</span>`;
}

function changePage(delta) {
    const newPage = currentPage + delta;
    if (newPage < 1 || newPage > totalPages) return;
    currentPage = newPage;
    loadExpenses();
}

function viewExpenseDetail(entryId) {
    showToast('info', 'Feature', 'Detail view coming soon');
}

function editExpense(entryId) {
    showToast('info', 'Feature', 'Edit functionality coming soon');
}

function deleteExpense(entryId) {
    if (!confirm(`Are you sure you want to delete ${entryId}? This will remove all GL entries and cannot be undone.`)) {
        return;
    }
    
    // Extract numeric ID from entry ID (e.g., "PE-00001" -> 1)
    const numericId = parseInt(entryId.replace('PE-', ''));
    
    fetch('../../../../server/api/manufacturing/production_expenses/expense-delete.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ expense_id: numericId })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('success', 'Deleted', result.message);
            loadExpenses(); // Reload the list
        } else {
            showToast('error', 'Error', result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Error', 'Failed to delete expense');
    });
}

function adjustPeriodCosts() {
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    
    if (!dateFrom || !dateTo) {
        showToast('error', 'Validation', 'Select period dates first');
        return;
    }
    
    if (!confirm(`Recalculate and adjust costs for all batches completed between ${dateFrom} and ${dateTo}?\n\nThis will update finished goods inventory values based on actual expenses posted.`)) {
        return;
    }
    
    fetch('../../../../server/api/manufacturing/production_expenses/adjust-costs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            period_from: dateFrom,
            period_to: dateTo
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            const adjustments = result.adjustments || [];
            const totalAdj = adjustments.reduce((sum, a) => sum + a.total_adjustment, 0);
            showToast('success', 'Costs Adjusted', 
                `${result.total_batches} batches processed. Total adjustment: Rs. ${totalAdj.toFixed(2)}`);
        } else {
            showToast('error', 'Error', result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Error', 'Failed to adjust costs');
    });
}

function reverseAdjustment() {
    const modal = document.getElementById('reverseModal');
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    document.getElementById('reverseDateFrom').value = firstDay.toISOString().split('T')[0];
    document.getElementById('reverseDateTo').value = today.toISOString().split('T')[0];
    
    modal.style.display = 'flex';
}

function closeReverseModal() {
    document.getElementById('reverseModal').style.display = 'none';
}

function confirmReverseAdjustment() {
    const dateFrom = document.getElementById('reverseDateFrom').value;
    const dateTo = document.getElementById('reverseDateTo').value;
    
    if (!dateFrom || !dateTo) {
        showToast('error', 'Required', 'Both dates are required');
        return;
    }
    
    if (!confirm(`Reverse all cost adjustments for period ${dateFrom} to ${dateTo}?\n\nThis will restore original costs before adjustment.`)) {
        return;
    }
    
    closeReverseModal();
    
    fetch('../../../../server/api/manufacturing/production_expenses/reverse-adjustment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            period_from: dateFrom,
            period_to: dateTo
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('success', 'Reversed', result.message);
            loadExpenses();
        } else {
            showToast('error', 'Error', result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Error', 'Failed to reverse adjustment');
    });
}



// Toast display (enhanced)
function showToast(type, title, msg, isError = false) {
    const toast = document.getElementById('toast');
    const iconSpan = document.getElementById('toastIcon');
    const titleSpan = document.getElementById('toastTitle');
    const msgSpan = document.getElementById('toastMsg');
    iconSpan.className = type === 'success' ? 'fas fa-check-circle' : (type === 'error' ? 'fas fa-exclamation-triangle' : 'fas fa-info-circle');
    titleSpan.innerText = title;
    msgSpan.innerText = msg;
    toast.classList.remove('success', 'error');
    if (type === 'error' || isError) toast.classList.add('error');
    else toast.classList.add('success');
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3500);
}

window.onload = () => {
    const today = new Date();
    const sixtyDaysAgo = new Date();
    sixtyDaysAgo.setDate(today.getDate() - 60);
    document.getElementById('filterDateFrom').value = sixtyDaysAgo.toISOString().split('T')[0];
    document.getElementById('filterDateTo').value = today.toISOString().split('T')[0];
    loadExpenses();
};

window.applyFilters = applyFilters;
window.changePage = changePage;
window.viewExpenseDetail = viewExpenseDetail;
window.editExpense = editExpense;
window.deleteExpense = deleteExpense;
window.adjustPeriodCosts = adjustPeriodCosts;
window.reverseAdjustment = reverseAdjustment;
window.closeReverseModal = closeReverseModal;
window.confirmReverseAdjustment = confirmReverseAdjustment;