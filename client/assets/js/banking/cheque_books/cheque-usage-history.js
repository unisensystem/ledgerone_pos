const historyApi = '../../../../server/api/banking/cheque_books/cheque-usage-history.php';
const historyBody = document.getElementById('history-body');
let historyAccounts = [];
let historySortOrder = 'desc'; // 'desc' = newest first (top to bottom), 'asc' = oldest first

function escapeHistoryHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
}

function transactionViewUrl(row) {
    const paths = {
        Payment: `../../vouchers/payment_voucher/print.php?id=${encodeURIComponent(row.reference_id)}`,
        Receipt: `../../vouchers/receive_voucher/print.php?id=${encodeURIComponent(row.reference_id)}`,
        Transfer: `../../vouchers/transfer_voucher/print.php?id=${encodeURIComponent(row.reference_id)}`,
        Expense: `../../vouchers/expense_voucher/print.php?id=${encodeURIComponent(row.reference_id)}`,
        Payroll: `../../hrm/payroll/payroll-receipt.php?id=${encodeURIComponent(row.reference_id)}`,
        'Production Expense': `../../manuacturing/production_expenses/expense-list.php?search=${encodeURIComponent(row.voucher_number)}`,
        // Wasted cheques are managed from the cheque book page
        Wasted: 'cheque-books.php'
    };
    return paths[row.transaction_type] || '#';
}

async function fetchChequeHistory(event) {
    if (event) event.preventDefault();
    const params = new URLSearchParams();
    const filters = {
        bank: document.getElementById('filter-bank').value,
        bank_account_id: document.getElementById('filter-account').value,
        account_number: document.getElementById('filter-account-number').value.trim(),
        account_holder: document.getElementById('filter-holder').value.trim(),
        cheque_no: document.getElementById('filter-cheque-number').value.trim(),
        transaction_type: document.getElementById('filter-type').value,
        status: document.getElementById('filter-status').value,
        date_from: document.getElementById('filter-date-from').value,
        date_to: document.getElementById('filter-date-to').value,
        order: historySortOrder
    };
    Object.entries(filters).forEach(([key, value]) => { if (value) params.set(key, value); });
    historyBody.innerHTML = '<tr><td colspan="10" class="empty">Loading cheque history...</td></tr>';
    try {
        const response = await fetch(`${historyApi}?${params.toString()}`);
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load cheque history.');
        updateHistoryLookups(result.banks || [], result.accounts || []);
        if (!result.data.length) {
            historyBody.innerHTML = '<tr><td colspan="10" class="empty">No cheque transactions found.</td></tr>';
            return;
        }
        historyBody.innerHTML = result.data.map(row => {
            const isWasted = row.transaction_type === 'Wasted';
            const amount = isWasted
                ? '—'
                : Number(row.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            return `
            <tr><td>${escapeHistoryHtml(row.cheque_no)}</td>
            <td>${escapeHistoryHtml(row.bank_name || '—')}<br><small>${escapeHistoryHtml(row.account_number || '—')}</small></td>
            <td>${escapeHistoryHtml(row.account_holder || '—')}</td>
            <td>${escapeHistoryHtml(row.transaction_type)}</td><td>${escapeHistoryHtml(row.voucher_number || '—')}</td>
            <td>${escapeHistoryHtml(row.party_name || '—')}</td><td>${escapeHistoryHtml(row.transaction_date)}</td>
            <td>${amount}</td>
            <td>${escapeHistoryHtml(row.transaction_status || '—')}</td>
            <td><a class="button" href="${transactionViewUrl(row)}" target="_blank" rel="noopener">View</a></td></tr>`;
        }).join('');
    } catch (error) {
        historyBody.innerHTML = `<tr><td colspan="10" class="empty">${escapeHistoryHtml(error.message)}</td></tr>`;
    }
}

function updateHistoryLookups(banks, accounts) {
    const bankSelect = document.getElementById('filter-bank');
    const currentBank = bankSelect.value;
    if (bankSelect.options.length === 1) {
        banks.forEach(bank => {
            const option = document.createElement('option');
            option.value = bank;
            option.textContent = bank;
            bankSelect.appendChild(option);
        });
        bankSelect.value = currentBank;
    }
    if (JSON.stringify(historyAccounts) !== JSON.stringify(accounts)) {
        historyAccounts = accounts;
        updateHistoryAccountOptions();
    }
}

function updateHistoryAccountOptions() {
    const select = document.getElementById('filter-account');
    const current = select.value;
    const selectedBank = document.getElementById('filter-bank').value;
    select.innerHTML = '<option value="">All Accounts</option>';
    historyAccounts.filter(account => !selectedBank || account.bank_name === selectedBank).forEach(account => {
        const option = document.createElement('option');
        option.value = account.id;
        option.textContent = `${account.bank_name} - ${account.account_number}`;
        select.appendChild(option);
    });
    if ([...select.options].some(option => option.value === current)) select.value = current;
}

document.getElementById('sort-toggle').addEventListener('click', () => {
    historySortOrder = historySortOrder === 'desc' ? 'asc' : 'desc';
    document.getElementById('sort-toggle').innerHTML = historySortOrder === 'desc'
        ? '<i class="fa-solid fa-arrow-down-wide-short"></i> Newest First'
        : '<i class="fa-solid fa-arrow-up-short-wide"></i> Oldest First';
    fetchChequeHistory();
});
document.getElementById('history-filters').addEventListener('submit', fetchChequeHistory);
document.getElementById('filter-bank').addEventListener('change', () => {
    updateHistoryAccountOptions();
    fetchChequeHistory();
});
document.getElementById('reset-filters').addEventListener('click', () => {
    document.getElementById('history-filters').reset();
    updateHistoryAccountOptions();
    fetchChequeHistory();
});
fetchChequeHistory();