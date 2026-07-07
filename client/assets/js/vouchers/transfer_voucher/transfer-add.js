// ─── State ───────────────────────────────────────────────────────────────────
let customers = [];
let suppliers = [];
let companies = [];
let currencies = [];
let paymentMethods = [];
let bankAccounts = [];

let fromType = 'customer'; // 'customer' | 'supplier'
let toType   = 'customer';

let fromPartyId   = null;
let fromPartyCode = null;
let toPartyId     = null;
let toPartyCode   = null;

// ─── Init ─────────────────────────────────────────────────────────────────────
document.getElementById('voucherDate').valueAsDate = new Date();
document.getElementById('voucherNumber').value = 'Auto-generated';

// ─── Fetch all master data ────────────────────────────────────────────────────
Promise.all([
    fetch('../../../../server/api/vouchers/transfer_voucher/get-customers.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/transfer_voucher/get-suppliers.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/transfer_voucher/get-companies.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/transfer_voucher/get-currencies.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/transfer_voucher/get-payment-methods.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/transfer_voucher/get-bank-accounts.php').then(r => r.json()),
]).then(([custData, suppData, compData, currData, pmData, bankData]) => {
    if (custData.success) customers = custData.data;
    if (suppData.success) suppliers = suppData.data;

    if (compData.success) {
        companies = compData.data;
        const sel = document.getElementById('company');
        const bulkSel = document.getElementById('bulkCompany');
        compData.data.forEach((c, i) => {
            const o1 = new Option(c.company_name, c.id);
            const o2 = new Option(c.company_name, c.id);
            if (i === 0 && compData.data.length === 1) { o1.selected = true; o2.selected = true; }
            sel.appendChild(o1);
            bulkSel.appendChild(o2);
        });
    }

    if (currData.success) {
        currencies = currData.data;
        const sel = document.getElementById('currency');
        const bulkSel = document.getElementById('bulkCurrency');
        currData.data.forEach((c, i) => {
            const o1 = new Option(`${c.name} (${c.symbol}) - ${c.code}`, c.currency_id);
            o1.dataset.symbol = c.symbol;
            const o2 = new Option(`${c.name} (${c.symbol})`, c.currency_id);
            if (i === 0) {
                o1.selected = true;
                o2.selected = true;
                document.getElementById('currencySymbol').textContent = c.symbol;
            }
            sel.appendChild(o1);
            bulkSel.appendChild(o2);
        });
    }

    if (pmData.success) {
        paymentMethods = pmData.data;
        const sel = document.getElementById('paymentMethod');
        pmData.data.forEach(m => sel.appendChild(new Option(m.name, m.id)));
    }

    if (bankData.success) {
        bankAccounts = bankData.data;
        const sel = document.getElementById('bankAccount');
        bankData.data.forEach(b => sel.appendChild(new Option(`${b.bank_name} - ${b.account_number}`, b.id)));
    }

    // Init dropdowns after data loaded
    setupPartyDropdown('fromParty', 'fromPartyDropdown', 'from');
    setupPartyDropdown('toParty', 'toPartyDropdown', 'to');
});

// ─── Currency symbol update ───────────────────────────────────────────────────
document.getElementById('currency').addEventListener('change', function () {
    const sym = this.options[this.selectedIndex]?.dataset?.symbol || '$';
    document.getElementById('currencySymbol').textContent = sym;
});

// ─── Payment method show/hide bank/cheque/slip fields ────────────────────────
document.getElementById('paymentMethod').addEventListener('change', function () {
    const id = parseInt(this.value);
    const bankGrp     = document.getElementById('bankAccountGroup');
    const chequeNoGrp = document.getElementById('chequeNoGroup');
    const chequeDtGrp = document.getElementById('chequeDateGroup');
    const slipNoGrp   = document.getElementById('slipNoGroup');
    const attachGrp   = document.getElementById('attachmentGroup');
    const bankFld     = document.getElementById('bankAccount');
    const chequeNoFld = document.getElementById('chequeNo');
    const chequeDtFld = document.getElementById('chequeDate');
    const slipNoFld   = document.getElementById('slipNo');
    const attachFld   = document.getElementById('slipAttachment');

    // Hide all conditional fields
    [bankGrp, chequeNoGrp, chequeDtGrp, slipNoGrp, attachGrp].forEach(g => g.classList.add('hidden'));
    [bankFld, chequeNoFld, chequeDtFld, slipNoFld, attachFld].forEach(f => f.removeAttribute('required'));
    bankFld.value = ''; chequeNoFld.value = ''; chequeDtFld.value = '';
    slipNoFld.value = ''; attachFld.value = '';
    document.getElementById('attachmentPreview').style.display = 'none';

    if (id === 6) {
        // PDC/Cheque — bank account + cheque no + cheque date
        bankGrp.classList.remove('hidden');
        chequeNoGrp.classList.remove('hidden');
        chequeDtGrp.classList.remove('hidden');
        bankFld.setAttribute('required', 'required');
        chequeNoFld.setAttribute('required', 'required');
        chequeDtFld.setAttribute('required', 'required');
    } else if (id !== 7 && id) {
        // Bank Transfer — slip no + attachment only
        slipNoGrp.classList.remove('hidden');
        attachGrp.classList.remove('hidden');
    }
    // id === 7 = Cash — nothing extra
});

// Attachment preview
document.getElementById('slipAttachment').addEventListener('change', function () {
    const preview  = document.getElementById('attachmentPreview');
    const nameSpan = document.getElementById('attachmentName');
    if (this.files && this.files[0]) {
        nameSpan.textContent = this.files[0].name;
        preview.style.display = 'flex';
    } else {
        preview.style.display = 'none';
    }
});

document.getElementById('removeAttachment').addEventListener('click', function () {
    document.getElementById('slipAttachment').value = '';
    document.getElementById('attachmentPreview').style.display = 'none';
});

// ─── Party Type Toggle Buttons ────────────────────────────────────────────────
document.querySelectorAll('.party-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const side = this.dataset.side; // 'from' | 'to'
        const type = this.dataset.type; // 'customer' | 'supplier'

        // Update active button in this group
        document.querySelectorAll(`.party-btn[data-side="${side}"]`).forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        if (side === 'from') {
            fromType = type;
            fromPartyId = null;
            fromPartyCode = null;
        } else {
            toType = type;
            toPartyId = null;
            toPartyCode = null;
        }

        const labelEl = document.getElementById(`${side}PartyLabel`);
        const inputEl = document.getElementById(`${side}Party`);
        const subSel  = document.getElementById(`${side}SubAccount`);
        const prevBal = document.getElementById(`${side}PreviousBalance`);
        const remBal  = document.getElementById(`${side}RemainingBalance`);

        labelEl.textContent = type === 'customer' ? 'Customer Code' : 'Supplier Code';
        inputEl.placeholder = type === 'customer' ? 'Search customer...' : 'Search supplier...';
        inputEl.value = '';
        subSel.innerHTML = '<option value="">Select Sub Account</option>';
        prevBal.value = '';
        remBal.value = '';

        // Re-init dropdown with new data
        setupPartyDropdown(`${side}Party`, `${side}PartyDropdown`, side);
    });
});

// ─── Amount change → update remaining balances ────────────────────────────────
document.getElementById('amount').addEventListener('input', updateBothBalances);

function updateBothBalances() {
    const amt = parseFloat(document.getElementById('amount').value) || 0;

    const fromPrev = parseFloat(document.getElementById('fromPreviousBalance').value) || 0;
    const fromRemEl = document.getElementById('fromRemainingBalance');
    // FROM supplier: paid out = payable increases (+) | FROM customer: paid out = receivable reduces (-)
    const fromRem = fromType === 'supplier' ? fromPrev + amt : fromPrev - amt;
    fromRemEl.value = fromRem.toFixed(2);
    fromRemEl.style.color = fromType === 'supplier'
        ? (fromRem > 0 ? 'var(--error)' : 'var(--success)')
        : (fromRem > 0 ? 'var(--success)' : 'var(--error)');

    const toPrev = parseFloat(document.getElementById('toPreviousBalance').value) || 0;
    const toRemEl = document.getElementById('toRemainingBalance');
    // TO supplier: received payment = payable reduces (-) | TO customer: received = receivable increases (+)
    const toRem = toType === 'supplier' ? toPrev - amt : toPrev + amt;
    toRemEl.value = toRem.toFixed(2);
    toRemEl.style.color = toType === 'supplier'
        ? (toRem > 0 ? 'var(--error)' : 'var(--success)')
        : (toRem > 0 ? 'var(--success)' : 'var(--error)');
}

// ─── Searchable Dropdown Setup ────────────────────────────────────────────────
function setupPartyDropdown(inputId, dropdownId, side) {
    const input    = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    const type     = side === 'from' ? fromType : toType;
    const data     = type === 'customer' ? customers : suppliers;
    const codeKey  = type === 'customer' ? 'customer_code' : 'supplier_code';
    const nameKey  = type === 'customer' ? 'customer_name' : 'supplier_name';

    // Remove old listeners by cloning
    const newInput = input.cloneNode(true);
    input.parentNode.replaceChild(newInput, input);
    const inp = document.getElementById(inputId);

    let highlightedIndex = -1;

    function renderDropdown(term) {
        dropdown.innerHTML = '';
        highlightedIndex = -1;
        const filtered = data.filter(d =>
            d[codeKey].toLowerCase().includes(term) ||
            d[nameKey].toLowerCase().includes(term)
        ).slice(0, 10);

        if (!filtered.length) {
            dropdown.classList.remove('show');
            return;
        }

        filtered.forEach(item => {
            const div = document.createElement('div');
            div.className = 'dropdown-item';
            div.innerHTML = `<strong>${item[codeKey]}</strong> - ${item[nameKey]}`;
            div.dataset.code = item[codeKey];
            div.dataset.id   = item.id || '';
            div.addEventListener('click', () => selectParty(div, side, type, codeKey, nameKey));
            dropdown.appendChild(div);
        });
        dropdown.classList.add('show');
    }

    inp.addEventListener('input', function () {
        renderDropdown(this.value.toLowerCase());
    });

    inp.addEventListener('focus', function () {
        renderDropdown(this.value.toLowerCase());
    });

    inp.addEventListener('keydown', function (e) {
        const items = dropdown.querySelectorAll('.dropdown-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
            items.forEach((it, i) => it.classList.toggle('highlighted', i === highlightedIndex));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightedIndex = Math.max(highlightedIndex - 1, -1);
            items.forEach((it, i) => it.classList.toggle('highlighted', i === highlightedIndex));
        } else if (e.key === 'Enter' && highlightedIndex >= 0) {
            e.preventDefault();
            selectParty(items[highlightedIndex], side, type, codeKey, nameKey);
        } else if (e.key === 'Escape') {
            dropdown.classList.remove('show');
        }
    });

    document.addEventListener('click', function (e) {
        if (!inp.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
}

function selectParty(div, side, type, codeKey, nameKey) {
    const code = div.dataset.code;
    const id   = div.dataset.id;
    const inp  = document.getElementById(`${side}Party`);
    const dropdown = document.getElementById(`${side}PartyDropdown`);

    inp.value = code;
    dropdown.classList.remove('show');

    if (side === 'from') { fromPartyCode = code; fromPartyId = id; }
    else                 { toPartyCode   = code; toPartyId   = id; }

    // Load sub accounts
    loadSubAccounts(side, type, code, id);

    // Load balance
    const balanceApi = type === 'customer'
        ? `../../../../server/api/vouchers/transfer_voucher/get-customer-balance.php?customer_code=${code}`
        : `../../../../server/api/vouchers/transfer_voucher/get-supplier-balance.php?supplier_code=${code}`;

    fetch(balanceApi)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById(`${side}PreviousBalance`).value = parseFloat(data.balance).toFixed(2);
                updateBothBalances();
            }
        });
}

function loadSubAccounts(side, type, code, id) {
    const sel = document.getElementById(`${side}SubAccount`);
    sel.innerHTML = '<option value="">Select Sub Account</option>';

    const api = type === 'customer'
        ? `../../../../server/api/vouchers/transfer_voucher/get-customer-sub-accounts.php?customer_code=${code}`
        : `../../../../server/api/vouchers/transfer_voucher/get-sub-accounts.php?supplier_code=${code}`;

    fetch(api)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                data.data.forEach(sa => {
                    sel.appendChild(new Option(sa.sub_account_name, sa.id));
                });
            }
        });
}

// ─── Mode Toggle (Single / Bulk) ──────────────────────────────────────────────
document.querySelectorAll('input[name="voucherMode"]').forEach(radio => {
    radio.addEventListener('change', function () {
        if (this.value === 'single') {
            document.getElementById('singleVoucherForm').style.display = 'block';
            document.getElementById('bulkVoucherForm').style.display = 'none';
        } else {
            document.getElementById('singleVoucherForm').style.display = 'none';
            document.getElementById('bulkVoucherForm').style.display = 'block';
            if (document.getElementById('bulkTableBody').children.length === 0) addBulkRow();
        }
    });
});

// ─── Form Submit ──────────────────────────────────────────────────────────────
const form    = document.getElementById('transferVoucherForm');
const postBtn = document.getElementById('postBtn');

form.addEventListener('submit', function (e) {
    e.preventDefault();

    // Validate same party
    if (fromType === toType && fromPartyCode && toPartyCode && fromPartyCode === toPartyCode) {
        alert('From and To party cannot be the same!');
        return;
    }

    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    requiredFields.forEach(f => {
        if (!f.value.trim()) { isValid = false; f.style.borderColor = 'var(--error)'; }
        else f.style.borderColor = '';
    });

    if (!isValid) { alert('Please fill in all required fields.'); return; }

    postBtn.disabled = true;
    postBtn.innerHTML = '<div class="spinner"></div> Processing...';

    const fd = new FormData();
    fd.append('voucher_date',       document.getElementById('voucherDate').value);
    fd.append('company_id',         document.getElementById('company').value);
    fd.append('currency_id',        document.getElementById('currency').value);
    fd.append('amount',             document.getElementById('amount').value);
    fd.append('payment_method_id',  document.getElementById('paymentMethod').value);
    fd.append('bank_account_id',    document.getElementById('bankAccount').value || '');
    fd.append('cheque_no',          document.getElementById('chequeNo').value || '');
    fd.append('cheque_date',        document.getElementById('chequeDate').value || '');
    fd.append('slip_no',            document.getElementById('slipNo').value || '');
    const slipFile = document.getElementById('slipAttachment').files[0];
    if (slipFile) fd.append('attachment', slipFile);
    fd.append('description',        document.getElementById('description').value || '');
    fd.append('from_type',          fromType);
    fd.append('from_party_code',    fromPartyCode || document.getElementById('fromParty').value.trim());
    fd.append('from_sub_account_id', document.getElementById('fromSubAccount').value || '');
    fd.append('to_type',            toType);
    fd.append('to_party_code',      toPartyCode || document.getElementById('toParty').value.trim());
    fd.append('to_sub_account_id',  document.getElementById('toSubAccount').value || '');

    fetch('../../../../server/api/vouchers/transfer_voucher/transfer-add.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Transfer voucher posted successfully!');
            resetForm();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(() => alert('Network error occurred'))
    .finally(() => {
        postBtn.disabled = false;
        postBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Post Voucher';
    });
});

// ─── Reset ────────────────────────────────────────────────────────────────────
document.getElementById('resetBtn').addEventListener('click', resetForm);

function resetForm() {
    form.reset();
    document.getElementById('voucherDate').valueAsDate = new Date();
    document.getElementById('voucherNumber').value = 'Auto-generated';
    fromType = 'customer'; toType = 'customer';
    fromPartyId = null; fromPartyCode = null;
    toPartyId   = null; toPartyCode   = null;

    // Reset party type buttons
    document.querySelectorAll('.party-btn[data-side="from"]').forEach(b => b.classList.toggle('active', b.dataset.type === 'customer'));
    document.querySelectorAll('.party-btn[data-side="to"]').forEach(b => b.classList.toggle('active', b.dataset.type === 'customer'));

    document.getElementById('fromPartyLabel').textContent = 'Customer Code';
    document.getElementById('toPartyLabel').textContent   = 'Customer Code';
    document.getElementById('fromParty').placeholder = 'Search customer...';
    document.getElementById('toParty').placeholder   = 'Search customer...';
    document.getElementById('fromSubAccount').innerHTML = '<option value="">Select Sub Account</option>';
    document.getElementById('toSubAccount').innerHTML   = '<option value="">Select Sub Account</option>';
    document.getElementById('fromPreviousBalance').value = '';
    document.getElementById('fromRemainingBalance').value = '';
    document.getElementById('toPreviousBalance').value = '';
    document.getElementById('toRemainingBalance').value = '';

    // Hide bank/cheque/slip fields
    ['bankAccountGroup','chequeNoGroup','chequeDateGroup','slipNoGroup','attachmentGroup'].forEach(id => {
        document.getElementById(id).classList.add('hidden');
    });
    document.getElementById('slipNo').value = '';
    document.getElementById('slipAttachment').value = '';
    document.getElementById('attachmentPreview').style.display = 'none';

    form.querySelectorAll('input, select, textarea').forEach(f => f.style.borderColor = '');

    setupPartyDropdown('fromParty', 'fromPartyDropdown', 'from');
    setupPartyDropdown('toParty',   'toPartyDropdown',   'to');
}

// ─── Bulk Mode ────────────────────────────────────────────────────────────────
document.getElementById('addRowBtn').addEventListener('click', addBulkRow);

function addBulkRow() {
    const tbody = document.getElementById('bulkTableBody');
    const today = new Date().toISOString().split('T')[0];
    const row = document.createElement('tr');

    let pmOptions = paymentMethods.map(m => `<option value="${m.id}">${m.name}</option>`).join('');
    let bankOptions = bankAccounts.map(b => `<option value="${b.id}">${b.bank_name} - ${b.account_number}</option>`).join('');

    row.innerHTML = `
        <td><input type="date" class="bulk-date" value="${today}" required></td>
        <td>
            <select class="bulk-from-type">
                <option value="customer">Customer</option>
                <option value="supplier">Supplier</option>
            </select>
        </td>
        <td style="position:relative;">
            <input type="text" class="bulk-from-party" placeholder="Search..." autocomplete="off" required>
            <div class="bulk-party-dropdown"></div>
        </td>
        <td>
            <select class="bulk-to-type">
                <option value="customer">Customer</option>
                <option value="supplier">Supplier</option>
            </select>
        </td>
        <td style="position:relative;">
            <input type="text" class="bulk-to-party" placeholder="Search..." autocomplete="off" required>
            <div class="bulk-party-dropdown"></div>
        </td>
        <td><input type="number" class="bulk-amount" step="0.01" min="0" placeholder="0.00" required></td>
        <td>
            <select class="bulk-payment-method" required>
                <option value="">Select</option>
                ${pmOptions}
            </select>
        </td>
        <td>
            <select class="bulk-bank-account">
                <option value="">Select</option>
                ${bankOptions}
            </select>
        </td>
        <td><input type="date" class="bulk-cheque-date"></td>
        <td><input type="text" class="bulk-cheque-no" placeholder="Cheque No"></td>
        <td><textarea class="bulk-description" placeholder="Description"></textarea></td>
        <td><button type="button" class="remove-row-btn" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
    `;

    tbody.appendChild(row);

    setupBulkPartyDropdown(row, 'from');
    setupBulkPartyDropdown(row, 'to');

    row.querySelector('.bulk-from-type').addEventListener('change', function () {
        const inp = row.querySelector('.bulk-from-party');
        inp.value = '';
        inp.placeholder = this.value === 'customer' ? 'Search customer...' : 'Search supplier...';
    });

    row.querySelector('.bulk-to-type').addEventListener('change', function () {
        const inp = row.querySelector('.bulk-to-party');
        inp.value = '';
        inp.placeholder = this.value === 'customer' ? 'Search customer...' : 'Search supplier...';
    });

    row.querySelector('.bulk-amount').addEventListener('input', updateBulkTotal);
}

function setupBulkPartyDropdown(row, side) {
    const input    = row.querySelector(`.bulk-${side}-party`);
    const dropdown = input.nextElementSibling;
    const typeSelect = row.querySelector(`.bulk-${side}-type`);

    input.addEventListener('input', function () {
        const type = typeSelect.value;
        const data = type === 'customer' ? customers : suppliers;
        const codeKey = type === 'customer' ? 'customer_code' : 'supplier_code';
        const nameKey = type === 'customer' ? 'customer_name' : 'supplier_name';
        const term = this.value.toLowerCase();

        dropdown.innerHTML = '';
        const filtered = data.filter(d =>
            d[codeKey].toLowerCase().includes(term) ||
            d[nameKey].toLowerCase().includes(term)
        ).slice(0, 10);

        if (!filtered.length) { dropdown.classList.remove('show'); return; }

        filtered.forEach(item => {
            const div = document.createElement('div');
            div.className = 'dropdown-item';
            div.innerHTML = `<strong>${item[codeKey]}</strong> - ${item[nameKey]}`;
            div.addEventListener('click', () => {
                input.value = item[codeKey];
                dropdown.classList.remove('show');
            });
            dropdown.appendChild(div);
        });
        dropdown.classList.add('show');
    });

    input.addEventListener('focus', function () {
        this.dispatchEvent(new Event('input'));
    });

    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
}

function removeRow(btn) {
    btn.closest('tr').remove();
    updateBulkTotal();
}

function updateBulkTotal() {
    let total = 0;
    document.querySelectorAll('.bulk-amount').forEach(inp => {
        total += parseFloat(inp.value) || 0;
    });
    document.getElementById('bulkTotalAmount').textContent = total.toFixed(2);
}

document.getElementById('postBulkBtn').addEventListener('click', async function () {
    const rows = document.querySelectorAll('#bulkTableBody tr');
    if (!rows.length) { alert('Please add at least one row'); return; }

    const bulkCompanyId  = document.getElementById('bulkCompany').value;
    const bulkCurrencyId = document.getElementById('bulkCurrency').value;
    if (!bulkCompanyId)  { alert('Please select a company'); return; }
    if (!bulkCurrencyId) { alert('Please select a currency'); return; }

    this.disabled = true;
    this.innerHTML = '<div class="spinner"></div> Processing...';

    let successCount = 0, failCount = 0;

    for (const row of rows) {
        const fromPartyVal = row.querySelector('.bulk-from-party').value.trim();
        const toPartyVal   = row.querySelector('.bulk-to-party').value.trim();

        if (!fromPartyVal || !toPartyVal) { failCount++; row.style.background = 'rgba(227,79,79,0.1)'; continue; }

        const fd = new FormData();
        fd.append('company_id',        bulkCompanyId);
        fd.append('currency_id',       bulkCurrencyId);
        fd.append('voucher_date',      row.querySelector('.bulk-date').value);
        fd.append('from_type',         row.querySelector('.bulk-from-type').value);
        fd.append('from_party_code',   fromPartyVal);
        fd.append('to_type',           row.querySelector('.bulk-to-type').value);
        fd.append('to_party_code',     toPartyVal);
        fd.append('amount',            row.querySelector('.bulk-amount').value);
        fd.append('payment_method_id', row.querySelector('.bulk-payment-method').value);
        fd.append('bank_account_id',   row.querySelector('.bulk-bank-account').value || '');
        fd.append('cheque_date',       row.querySelector('.bulk-cheque-date').value || '');
        fd.append('cheque_no',         row.querySelector('.bulk-cheque-no').value || '');
        fd.append('description',       row.querySelector('.bulk-description').value || '');

        try {
            const res  = await fetch('../../../../server/api/vouchers/transfer_voucher/transfer-add.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) { successCount++; row.style.background = 'rgba(47,191,113,0.1)'; }
            else              { failCount++;    row.style.background = 'rgba(227,79,79,0.1)'; }
        } catch {
            failCount++;
            row.style.background = 'rgba(227,79,79,0.1)';
        }
    }

    alert(`Completed: ${successCount} successful, ${failCount} failed`);
    this.disabled = false;
    this.innerHTML = '<i class="fas fa-paper-plane"></i> Post All Vouchers';

    if (successCount > 0) {
        setTimeout(() => {
            document.getElementById('bulkTableBody').innerHTML = '';
            addBulkRow();
            updateBulkTotal();
        }, 2000);
    }
});
