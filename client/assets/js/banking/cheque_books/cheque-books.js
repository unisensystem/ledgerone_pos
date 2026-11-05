const chequeBooksApi = '../../../../server/api/banking/cheque_books/cheque-books.php';
const wastedChequesApi = '../../../../server/api/banking/cheque_books/wasted-cheques.php';
const availableChequesApi = '../../../../server/api/banking/cheque_books/cheque-books.php?action=available';
const bankSelect = document.getElementById('bank-select');
const accountSelect = document.getElementById('bank-account-select');
const bookForm = document.getElementById('cheque-book-form');
const bookBody = document.getElementById('cheque-books-body');
const holderInput = document.getElementById('account-holder');
const messageBox = document.getElementById('cb-message');
let chequeBankAccounts = [];
let chequeBooks = [];
let currentViewedBookId = null;

function escapeChequeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
}

function showChequeMessage(message, type = 'success') {
    messageBox.textContent = message;
    messageBox.className = `cb-message ${type}`;
    window.setTimeout(() => { messageBox.className = 'cb-message'; }, 4500);
}

async function chequeApi(url, options = {}) {
    const response = await fetch(url, options);
    const result = await response.json();
    if (!response.ok || !result.success) {
        throw new Error(result.message || 'The request could not be completed.');
    }
    return result;
}

async function loadChequeBooksLookups() {
    try {
        const [bankResult, accountResult] = await Promise.all([
            chequeApi(`${chequeBooksApi}?action=banks`),
            chequeApi(`${chequeBooksApi}?action=accounts`)
        ]);
        bankResult.data.forEach(bank => {
            const option = document.createElement('option');
            option.value = bank;
            option.textContent = bank;
            bankSelect.appendChild(option);
        });
        chequeBankAccounts = accountResult.data;
        await loadChequeBooks();
    } catch (error) {
        showChequeMessage(error.message, 'error');
    }
}

function updateChequeAccounts(selectedId = '') {
    const bank = bankSelect.value;
    accountSelect.innerHTML = '<option value="">Select bank account</option>';
    chequeBankAccounts.filter(account => account.bank_name === bank).forEach(account => {
        const option = document.createElement('option');
        option.value = account.id;
        option.textContent = `${account.account_number}${account.account_title ? ` - ${account.account_title}` : ''}`;
        option.dataset.holder = account.account_title || '';
        accountSelect.appendChild(option);
    });
    accountSelect.disabled = !bank;
    accountSelect.value = selectedId ? String(selectedId) : '';
    holderInput.value = accountSelect.selectedOptions[0]?.dataset.holder || '';
}

async function loadChequeBooks() {
    try {
        const result = await chequeApi(chequeBooksApi);
        chequeBooks = result.data;
        renderChequeBooks();
    } catch (error) {
        bookBody.innerHTML = `<tr><td class="cb-empty" colspan="9">${escapeChequeHtml(error.message)}</td></tr>`;
    }
}

function renderChequeBooks() {
    if (!chequeBooks.length) {
        bookBody.innerHTML = '<tr><td class="cb-empty" colspan="9">No cheque books have been added.</td></tr>';
        return;
    }
    bookBody.innerHTML = chequeBooks.map(book => `
        <tr>
            <td>${escapeChequeHtml(book.bank_name)}<br><small>${escapeChequeHtml(book.account_number)}</small></td>
            <td>${escapeChequeHtml(book.account_title || '—')}</td>
            <td>${escapeChequeHtml(book.cheque_from_display)} – ${escapeChequeHtml(book.cheque_to_display)}</td>
            <td>${book.total_cheques}</td><td>${book.available_cheques}</td>
            <td>${book.used_cheques}</td><td>${book.wasted_cheques}</td>
            <td>${escapeChequeHtml(book.issue_date)}</td>
            <td><div class="cb-row-actions">
                <button class="cb-button small secondary" data-action="view" data-id="${book.id}">View</button>
                <button class="cb-button small secondary" data-action="edit" data-id="${book.id}">Edit</button>
                <button class="cb-button small secondary" data-action="waste" data-id="${book.id}">Wasted Cheque</button>
                <button class="cb-button small danger" data-action="delete" data-id="${book.id}">Delete</button>
            </div></td>
        </tr>`).join('');
}

// Supports prefixes like "D-92851806": total is counted from the trailing digits only.
function parseChequeText(value) {
    const match = value.trim().match(/^(.*?)(\d+)$/);
    return match ? { prefix: match[1], num: BigInt(match[2]) } : null;
}

function updateTotalCheques() {
    const total = document.getElementById('total-cheques');
    const from = parseChequeText(document.getElementById('cheque-from').value);
    const to = parseChequeText(document.getElementById('cheque-to').value);
    if (from && to
        && from.prefix.toLowerCase() === to.prefix.toLowerCase()
        && to.num >= from.num
        && to.num - from.num <= 1000000n) {
        total.value = String(to.num - from.num + 1n);
    } else {
        total.value = '0';
    }
}

function resetChequeBookForm() {
    bookForm.reset();
    document.getElementById('book-id').value = '';
    document.getElementById('form-heading').textContent = 'Add Cheque Book';
    document.getElementById('cancel-edit').hidden = true;
    document.getElementById('total-cheques').value = '0';
    accountSelect.innerHTML = '<option value="">Select bank account</option>';
    accountSelect.disabled = true;
    holderInput.value = '';
    document.getElementById('issue-date').value = new Date().toISOString().slice(0, 10);
}

async function saveChequeBook(event) {
    event.preventDefault();
    const id = document.getElementById('book-id').value;
    const payload = {
        bank_account_id: accountSelect.value,
        cheque_from: document.getElementById('cheque-from').value.trim(),
        cheque_to: document.getElementById('cheque-to').value.trim(),
        issue_date: document.getElementById('issue-date').value,
        remarks: document.getElementById('remarks').value.trim()
    };
    const button = document.getElementById('save-book');
    button.disabled = true;
    try {
        const result = await chequeApi(id ? `${chequeBooksApi}?id=${encodeURIComponent(id)}` : chequeBooksApi, {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        showChequeMessage(result.message);
        resetChequeBookForm();
        await loadChequeBooks();
    } catch (error) {
        showChequeMessage(error.message, 'error');
    } finally {
        button.disabled = false;
    }
}

function editChequeBook(bookId) {
    const book = chequeBooks.find(item => Number(item.id) === Number(bookId));
    if (!book) return;
    bankSelect.value = book.bank_name;
    updateChequeAccounts(book.bank_account_id);
    document.getElementById('book-id').value = book.id;
    document.getElementById('cheque-from').value = book.cheque_from_display;
    document.getElementById('cheque-to').value = book.cheque_to_display;
    document.getElementById('issue-date').value = book.issue_date;
    document.getElementById('remarks').value = book.remarks || '';
    document.getElementById('form-heading').textContent = 'Edit Cheque Book';
    document.getElementById('cancel-edit').hidden = false;
    updateTotalCheques();
    bookForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function showChequeBookDetails(bookId) {
    const book = chequeBooks.find(item => Number(item.id) === Number(bookId));
    if (!book) return;
    currentViewedBookId = book.id;
    const wasteResult = await chequeApi(`${wastedChequesApi}?book_id=${encodeURIComponent(bookId)}`);
    const wasteRows = wasteResult.data.length
        ? `<div class="cb-table-wrap"><table class="cb-table"><thead><tr><th>Cheque</th><th>Waste Date</th><th>Reason</th><th></th></tr></thead><tbody>${wasteResult.data.map(item => `
            <tr><td>${escapeChequeHtml(item.cheque_no)}</td><td>${escapeChequeHtml(item.waste_date)}</td>
            <td>${escapeChequeHtml(item.reason)}</td><td><button class="cb-button small secondary" data-remove-waste="${item.id}">Undo</button></td></tr>`).join('')}</tbody></table></div>`
        : '<p>No wasted cheques in this book.</p>';
    document.getElementById('view-book-content').innerHTML = `
        <p><strong>Bank:</strong> ${escapeChequeHtml(book.bank_name)}<br>
        <strong>Bank account:</strong> ${escapeChequeHtml(book.account_number)}<br>
        <strong>Account holder:</strong> ${escapeChequeHtml(book.account_title || '—')}<br>
        <strong>Range:</strong> ${escapeChequeHtml(book.cheque_from_display)} – ${escapeChequeHtml(book.cheque_to_display)}<br>
        <strong>Issue date:</strong> ${escapeChequeHtml(book.issue_date)}<br>
        <strong>Remarks:</strong> ${escapeChequeHtml(book.remarks || '—')}</p>
        <h3>Wasted Cheques</h3>${wasteRows}`;
    document.getElementById('view-dialog').showModal();
}

async function openWasteDialog(bookId) {
    const book = chequeBooks.find(item => Number(item.id) === Number(bookId));
    if (!book) return;
    const response = await chequeApi(`${availableChequesApi}&bank_account_id=${encodeURIComponent(book.bank_account_id)}`);
    const options = response.data.filter(item => Number(item.cheque_book_id) === Number(bookId));
    const select = document.getElementById('waste-cheque-number');
    select.innerHTML = '<option value="">Select available cheque</option>';
    options.forEach(item => {
        const option = document.createElement('option');
        // Server currently expects digits only for wasted cheques, so send the trailing digits
        // but still show the full number (with prefix) in the dropdown.
        option.value = (String(item.cheque_no).match(/(\d+)\s*$/) || [])[1] || item.cheque_no;
        option.textContent = item.cheque_no;
        select.appendChild(option);
    });
    if (!options.length) {
        showChequeMessage('No available cheques remain in this book.', 'error');
        return;
    }
    document.getElementById('waste-book-id').value = book.id;
    document.getElementById('waste-date').value = new Date().toISOString().slice(0, 10);
    document.getElementById('waste-reason').value = '';
    document.getElementById('waste-dialog').showModal();
}

bookForm.addEventListener('submit', saveChequeBook);
bankSelect.addEventListener('change', () => updateChequeAccounts());
accountSelect.addEventListener('change', () => {
    holderInput.value = accountSelect.selectedOptions[0]?.dataset.holder || '';
});
document.getElementById('cheque-from').addEventListener('input', updateTotalCheques);
document.getElementById('cheque-to').addEventListener('input', updateTotalCheques);
document.getElementById('cancel-edit').addEventListener('click', resetChequeBookForm);

bookBody.addEventListener('click', async event => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const bookId = button.dataset.id;
    try {
        if (button.dataset.action === 'edit') editChequeBook(bookId);
        if (button.dataset.action === 'view') await showChequeBookDetails(bookId);
        if (button.dataset.action === 'waste') await openWasteDialog(bookId);
        if (button.dataset.action === 'delete' && window.confirm('Delete this cheque book?')) {
            const result = await chequeApi(`${chequeBooksApi}?id=${encodeURIComponent(bookId)}`, { method: 'DELETE' });
            showChequeMessage(result.message);
            await loadChequeBooks();
        }
    } catch (error) {
        showChequeMessage(error.message, 'error');
    }
});

document.getElementById('waste-form').addEventListener('submit', async event => {
    event.preventDefault();
    try {
        const result = await chequeApi(wastedChequesApi, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cheque_book_id: document.getElementById('waste-book-id').value,
                cheque_no: document.getElementById('waste-cheque-number').value,
                waste_date: document.getElementById('waste-date').value,
                reason: document.getElementById('waste-reason').value.trim()
            })
        });
        document.getElementById('waste-dialog').close();
        showChequeMessage(result.message);
        await loadChequeBooks();
    } catch (error) {
        showChequeMessage(error.message, 'error');
    }
});

document.getElementById('view-book-content').addEventListener('click', async event => {
    const button = event.target.closest('[data-remove-waste]');
    if (!button || !window.confirm('Remove this wasted-cheque mark?')) return;
    try {
        const result = await chequeApi(`${wastedChequesApi}?id=${encodeURIComponent(button.dataset.removeWaste)}`, { method: 'DELETE' });
        showChequeMessage(result.message);
        await loadChequeBooks();
        await showChequeBookDetails(currentViewedBookId);
    } catch (error) {
        showChequeMessage(error.message, 'error');
    }
});

document.querySelectorAll('[data-close-dialog]').forEach(button => {
    button.addEventListener('click', () => document.getElementById(button.dataset.closeDialog).close());
});
document.getElementById('view-dialog').addEventListener('click', event => {
    if (event.target === event.currentTarget) event.currentTarget.close();
});
document.getElementById('waste-dialog').addEventListener('click', event => {
    if (event.target === event.currentTarget) event.currentTarget.close();
});
document.getElementById('issue-date').value = new Date().toISOString().slice(0, 10);
loadChequeBooksLookups();