// ── State ─────────────────────────────────────────────────────────────────────
let partyType = 'customer';   // 'customer' | 'supplier'
let adjType   = 'debit';      // 'debit' | 'credit'
let allParties = [];
let allAccounts = [];
let currentBalance = null;

// ── Init ──────────────────────────────────────────────────────────────────────
document.getElementById('voucherDate').valueAsDate = new Date();

// Load master data
Promise.all([
    fetch('../../../../server/api/vouchers/transfer_voucher/get-customers.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/journal_voucher/get-accounts.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/journal_voucher/get-companies.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/journal_voucher/get-next-voucher-number.php?prefix=ADJ').then(r => r.json()),
]).then(([custData, accData, compData, vnData]) => {
    if (custData.success) {
        allParties = custData.data.map(c => ({ id: c.id, code: c.customer_code, name: c.customer_name }));
    }
    if (accData.success) {
        allAccounts = accData.accounts;
        setupContraDropdown();
    }
    if (compData.success) {
        const sel = document.getElementById('company');
        compData.data.forEach((c, i) => {
            const o = new Option(c.company_name, c.id);
            if (i === 0 && compData.data.length === 1) o.selected = true;
            sel.appendChild(o);
        });
    }
    if (vnData.success) {
        document.getElementById('voucherNumber').value = vnData.voucherNumber;
    }
});

// ── Party Type Toggle ─────────────────────────────────────────────────────────
document.querySelectorAll('.party-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        partyType = this.dataset.type;
        document.querySelectorAll('.party-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        // Reset party selection
        document.getElementById('partySearch').value = '';
        document.getElementById('partyId').value = '';
        document.getElementById('partyCode').value = '';
        document.getElementById('subAccount').innerHTML = '<option value="">All / Main Account</option>';
        document.getElementById('subAccountGroup').style.display = 'none';
        resetBalance();

        document.getElementById('partyLabel').innerHTML =
            (partyType === 'customer' ? 'Customer' : 'Supplier') + ' <span class="req">*</span>';
        document.getElementById('partySearch').placeholder =
            'Search ' + (partyType === 'customer' ? 'customer' : 'supplier') + '...';

        // Load correct party list
        const api = partyType === 'customer'
            ? '../../../../server/api/vouchers/transfer_voucher/get-customers.php'
            : '../../../../server/api/vouchers/transfer_voucher/get-suppliers.php';

        fetch(api).then(r => r.json()).then(data => {
            if (data.success) {
                allParties = data.data.map(p => ({
                    id: p.id,
                    code: partyType === 'customer' ? p.customer_code : p.supplier_code,
                    name: partyType === 'customer' ? p.customer_name : p.supplier_name
                }));
            }
        });

        updateAdjHint();
    });
});

// ── Party Search Dropdown ─────────────────────────────────────────────────────
const partySearchInput = document.getElementById('partySearch');
const partyDropdown    = document.getElementById('partyDropdown');
let partyHighlight = -1;

partySearchInput.addEventListener('input', function () {
    renderPartyDropdown(this.value.toLowerCase());
});

partySearchInput.addEventListener('focus', function () {
    renderPartyDropdown(this.value.toLowerCase());
});

partySearchInput.addEventListener('keydown', function (e) {
    const items = partyDropdown.querySelectorAll('.dropdown-item');
    if (e.key === 'ArrowDown') { e.preventDefault(); partyHighlight = Math.min(partyHighlight + 1, items.length - 1); highlightItems(items, partyHighlight); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); partyHighlight = Math.max(partyHighlight - 1, -1); highlightItems(items, partyHighlight); }
    else if (e.key === 'Enter' && partyHighlight >= 0) { e.preventDefault(); items[partyHighlight].click(); }
    else if (e.key === 'Escape') partyDropdown.classList.remove('show');
});

document.addEventListener('click', e => {
    if (!partySearchInput.contains(e.target) && !partyDropdown.contains(e.target))
        partyDropdown.classList.remove('show');
});

function renderPartyDropdown(term) {
    partyHighlight = -1;
    const filtered = term
        ? allParties.filter(p => p.name.toLowerCase().includes(term) || p.code.toLowerCase().includes(term))
        : allParties;

    if (!filtered.length) { partyDropdown.classList.remove('show'); return; }

    partyDropdown.innerHTML = filtered.slice(0, 12).map(p =>
        `<div class="dropdown-item" data-id="${p.id}" data-code="${p.code}" data-name="${p.name}">
            <strong>${p.code}</strong> — ${p.name}
        </div>`
    ).join('');
    partyDropdown.classList.add('show');

    partyDropdown.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', function () {
            selectParty(this.dataset.id, this.dataset.code, this.dataset.name);
        });
    });
}

function selectParty(id, code, name) {
    document.getElementById('partySearch').value = `${code} — ${name}`;
    document.getElementById('partyId').value = id;
    document.getElementById('partyCode').value = code;
    partyDropdown.classList.remove('show');

    // Load sub accounts
    const subApi = partyType === 'customer'
        ? `../../../../server/api/vouchers/transfer_voucher/get-customer-sub-accounts.php?customer_code=${code}`
        : `../../../../server/api/vouchers/transfer_voucher/get-sub-accounts.php?supplier_code=${code}`;

    fetch(subApi).then(r => r.json()).then(data => {
        const sel = document.getElementById('subAccount');
        sel.innerHTML = '<option value="">All / Main Account</option>';
        if (data.success && data.data.length > 0) {
            data.data.forEach(s => sel.appendChild(new Option(s.sub_account_name, s.id)));
            document.getElementById('subAccountGroup').style.display = 'flex';
        } else {
            document.getElementById('subAccountGroup').style.display = 'none';
        }
    });

    fetchBalance(id);
}

// ── Fetch Balance ─────────────────────────────────────────────────────────────
function fetchBalance(id) {
    const api = partyType === 'customer'
        ? `../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?customer_id=${id}&type=summary`
        : `../../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?supplier_id=${id}&type=summary`;

    fetch(api).then(r => r.json()).then(data => {
        if (data.success && data.data && data.data.length > 0) {
            currentBalance = parseFloat(data.data[0].closing_balance || 0);
            renderBalance('balanceAmount', 'balanceLabel', currentBalance);
            updateNewBalance();
        }
    });
}

function renderBalance(amountId, labelId, value) {
    const amountEl = document.getElementById(amountId);
    const labelEl  = document.getElementById(labelId);
    if (value === null || isNaN(value)) {
        amountEl.textContent = '—';
        labelEl.textContent = '';
        labelEl.className = 'balance-label';
        return;
    }
    amountEl.textContent = Math.abs(value).toFixed(2);
    if (value > 0) {
        labelEl.textContent = 'Dr';
        labelEl.className = 'balance-label dr';
    } else if (value < 0) {
        labelEl.textContent = 'Cr';
        labelEl.className = 'balance-label cr';
    } else {
        labelEl.textContent = 'Zero';
        labelEl.className = 'balance-label zero';
    }
}

function resetBalance() {
    currentBalance = null;
    renderBalance('balanceAmount', 'balanceLabel', null);
    renderBalance('newBalanceAmount', 'newBalanceLabel', null);
    document.getElementById('summaryCard').style.display = 'none';
}

// ── Adjustment Type Toggle ────────────────────────────────────────────────────
document.querySelectorAll('.adj-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        adjType = this.dataset.type;
        document.querySelectorAll('.adj-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        updateAdjHint();
        updateNewBalance();
        updatePreview();
    });
});

function updateAdjHint() {
    const hintEl = document.getElementById('adjHintText');
    if (partyType === 'customer') {
        hintEl.textContent = adjType === 'debit'
            ? 'Debit: Customer owes you more — balance increases (Dr)'
            : 'Credit: Customer owes you less — balance decreases (Cr)';
    } else {
        hintEl.textContent = adjType === 'debit'
            ? 'Debit: You owe supplier less — payable decreases'
            : 'Credit: You owe supplier more — payable increases';
    }
}

// ── Contra Account Dropdown ───────────────────────────────────────────────────
const contraInput    = document.getElementById('contraSearch');
const contraDropdown = document.getElementById('contraDropdown');
let contraHighlight  = -1;

function setupContraDropdown() {
    contraInput.addEventListener('input', function () {
        renderContraDropdown(this.value.toLowerCase());
    });
    contraInput.addEventListener('focus', function () {
        renderContraDropdown(this.value.toLowerCase());
    });
    contraInput.addEventListener('keydown', function (e) {
        const items = contraDropdown.querySelectorAll('.dropdown-item');
        if (e.key === 'ArrowDown') { e.preventDefault(); contraHighlight = Math.min(contraHighlight + 1, items.length - 1); highlightItems(items, contraHighlight); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); contraHighlight = Math.max(contraHighlight - 1, -1); highlightItems(items, contraHighlight); }
        else if (e.key === 'Enter' && contraHighlight >= 0) { e.preventDefault(); items[contraHighlight].click(); }
        else if (e.key === 'Escape') contraDropdown.classList.remove('show');
    });
    document.addEventListener('click', e => {
        if (!contraInput.contains(e.target) && !contraDropdown.contains(e.target))
            contraDropdown.classList.remove('show');
    });
}

function renderContraDropdown(term) {
    contraHighlight = -1;
    const filtered = term
        ? allAccounts.filter(a => a.name.toLowerCase().includes(term))
        : allAccounts;

    if (!filtered.length) { contraDropdown.classList.remove('show'); return; }

    contraDropdown.innerHTML = filtered.slice(0, 15).map(a =>
        `<div class="dropdown-item" data-id="${a.id}" data-name="${a.name}">${a.name}</div>`
    ).join('');
    contraDropdown.classList.add('show');

    contraDropdown.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', function () {
            contraInput.value = this.dataset.name;
            document.getElementById('contraAccountId').value = this.dataset.id;
            contraDropdown.classList.remove('show');
            updatePreview();
        });
    });
}

// ── Amount Change ─────────────────────────────────────────────────────────────
document.getElementById('amount').addEventListener('input', function () {
    updateNewBalance();
    updatePreview();
});

function updateNewBalance() {
    if (currentBalance === null) return;
    const amt = parseFloat(document.getElementById('amount').value) || 0;
    let newBal;

    if (partyType === 'customer') {
        newBal = adjType === 'debit' ? currentBalance + amt : currentBalance - amt;
    } else {
        // Supplier: credit-normal, currentBalance is negative for Cr
        // debit adj → reduces payable → balance moves toward zero (add amt to negative)
        // credit adj → increases payable → balance moves away from zero (subtract amt from negative)
        newBal = adjType === 'debit' ? currentBalance + amt : currentBalance - amt;
    }

    renderBalance('newBalanceAmount', 'newBalanceLabel', newBal);
}

// ── Preview Table ─────────────────────────────────────────────────────────────
function updatePreview() {
    const partyName   = document.getElementById('partySearch').value;
    const contraName  = contraInput.value;
    const contraId    = document.getElementById('contraAccountId').value;
    const amt         = parseFloat(document.getElementById('amount').value) || 0;

    if (!partyName || !contraName || !contraId || amt <= 0) {
        document.getElementById('summaryCard').style.display = 'none';
        return;
    }

    // Party account name
    const partyAccountName = partyType === 'customer' ? 'Trade Debtors (Customer)' : 'Trade Creditors (Supplier)';

    // Determine debit/credit sides
    // Customer: adjType=debit → DR party account, CR contra
    //           adjType=credit → CR party account, DR contra
    // Supplier: adjType=debit → DR contra, CR party account (reduces payable)
    //           adjType=credit → CR contra, DR party account (increases payable)
    let rows = [];

    if (partyType === 'customer') {
        if (adjType === 'debit') {
            rows = [
                { account: partyAccountName + ' — ' + partyName, debit: amt, credit: 0 },
                { account: contraName, debit: 0, credit: amt },
            ];
        } else {
            rows = [
                { account: contraName, debit: amt, credit: 0 },
                { account: partyAccountName + ' — ' + partyName, debit: 0, credit: amt },
            ];
        }
    } else {
        if (adjType === 'debit') {
            // Reduce supplier payable: DR supplier account, CR contra
            rows = [
                { account: partyAccountName + ' — ' + partyName, debit: amt, credit: 0 },
                { account: contraName, debit: 0, credit: amt },
            ];
        } else {
            // Increase supplier payable: CR supplier account, DR contra
            rows = [
                { account: contraName, debit: amt, credit: 0 },
                { account: partyAccountName + ' — ' + partyName, debit: 0, credit: amt },
            ];
        }
    }

    const tbody = document.getElementById('previewBody');
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${r.account}</td>
            <td class="text-right ${r.debit > 0 ? 'debit-val' : ''}">${r.debit > 0 ? r.debit.toFixed(2) : '—'}</td>
            <td class="text-right ${r.credit > 0 ? 'credit-val' : ''}">${r.credit > 0 ? r.credit.toFixed(2) : '—'}</td>
        </tr>
    `).join('') + `
        <tr>
            <td><strong>Total</strong></td>
            <td class="text-right"><strong>${amt.toFixed(2)}</strong></td>
            <td class="text-right"><strong>${amt.toFixed(2)}</strong></td>
        </tr>
    `;

    document.getElementById('summaryCard').style.display = 'block';
}

// ── Highlight helper ──────────────────────────────────────────────────────────
function highlightItems(items, idx) {
    items.forEach((it, i) => it.classList.toggle('highlighted', i === idx));
    if (idx >= 0) items[idx].scrollIntoView({ block: 'nearest' });
}

// ── Post Button ───────────────────────────────────────────────────────────────
document.getElementById('postBtn').addEventListener('click', function () {
    if (!validate()) return;

    const partyName  = document.getElementById('partySearch').value;
    const contraName = contraInput.value;
    const amt        = parseFloat(document.getElementById('amount').value);
    const desc       = document.getElementById('description').value;

    document.getElementById('modalBody').innerHTML = `
        <p><strong>Party:</strong> ${partyName}</p>
        <p><strong>Type:</strong> ${partyType === 'customer' ? 'Customer' : 'Supplier'}</p>
        <p><strong>Adjustment:</strong> ${adjType === 'debit' ? 'Debit' : 'Credit'} — ${amt.toFixed(2)}</p>
        <p><strong>Contra Account:</strong> ${contraName}</p>
        <p><strong>Description:</strong> ${desc}</p>
        <p style="margin-top:12px; color:#E34F4F; font-size:13px;">
            <i class="fas fa-exclamation-triangle"></i> This entry will be posted to the accounting ledger and cannot be undone.
        </p>
    `;
    document.getElementById('confirmModal').classList.add('show');
});

document.getElementById('closeModal').addEventListener('click', () => document.getElementById('confirmModal').classList.remove('show'));
document.getElementById('cancelBtn').addEventListener('click', () => document.getElementById('confirmModal').classList.remove('show'));
window.addEventListener('click', e => {
    if (e.target === document.getElementById('confirmModal'))
        document.getElementById('confirmModal').classList.remove('show');
});

document.getElementById('confirmBtn').addEventListener('click', async function () {
    document.getElementById('confirmModal').classList.remove('show');
    await postAdjustment();
});

// ── Validate ──────────────────────────────────────────────────────────────────
function validate() {
    const date        = document.getElementById('voucherDate').value;
    const company     = document.getElementById('company').value;
    const partyId     = document.getElementById('partyId').value;
    const contraId    = document.getElementById('contraAccountId').value;
    const amt         = parseFloat(document.getElementById('amount').value);
    const desc        = document.getElementById('description').value.trim();

    if (!date)    { showAlert('Please select a date.', 'error'); return false; }
    if (!company) { showAlert('Please select a company.', 'error'); return false; }
    if (!partyId) { showAlert('Please select a ' + (partyType === 'customer' ? 'customer' : 'supplier') + '.', 'error'); return false; }
    if (!contraId){ showAlert('Please select a contra account.', 'error'); return false; }
    if (!amt || amt <= 0) { showAlert('Please enter a valid amount greater than 0.', 'error'); return false; }
    if (!desc)    { showAlert('Please enter a description / reason for this adjustment.', 'error'); return false; }
    return true;
}

// ── Post Adjustment ───────────────────────────────────────────────────────────
async function postAdjustment() {
    const postBtn = document.getElementById('postBtn');
    postBtn.disabled = true;
    postBtn.innerHTML = '<div class="spinner"></div> Posting...';

    const payload = {
        voucher_date:      document.getElementById('voucherDate').value,
        voucher_number:    document.getElementById('voucherNumber').value,
        company_id:        document.getElementById('company').value,
        party_type:        partyType,
        party_id:          document.getElementById('partyId').value,
        party_code:        document.getElementById('partyCode').value,
        sub_account_id:    document.getElementById('subAccount').value || null,
        adj_type:          adjType,
        contra_account_id: document.getElementById('contraAccountId').value,
        amount:            parseFloat(document.getElementById('amount').value),
        description:       document.getElementById('description').value.trim(),
    };

    try {
        const res    = await fetch('../../../../server/api/vouchers/journal_voucher/adjustment-add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();

        if (result.success) {
            showAlert(`Adjustment posted successfully! Voucher: ${result.voucher_number}`, 'success');
            resetForm();
            // Refresh voucher number
            fetch('../../../../server/api/vouchers/journal_voucher/get-next-voucher-number.php?prefix=ADJ')
                .then(r => r.json()).then(d => { if (d.success) document.getElementById('voucherNumber').value = d.voucherNumber; });
        } else {
            showAlert('Error: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (err) {
        showAlert('Network error: ' + err.message, 'error');
    } finally {
        postBtn.disabled = false;
        postBtn.innerHTML = '<i class="fas fa-check-circle"></i> Post Adjustment';
    }
}

// ── Reset ─────────────────────────────────────────────────────────────────────
document.getElementById('resetBtn').addEventListener('click', resetForm);

function resetForm() {
    document.getElementById('voucherDate').valueAsDate = new Date();
    document.getElementById('company').selectedIndex = 0;
    document.getElementById('partySearch').value = '';
    document.getElementById('partyId').value = '';
    document.getElementById('partyCode').value = '';
    document.getElementById('subAccount').innerHTML = '<option value="">All / Main Account</option>';
    document.getElementById('subAccountGroup').style.display = 'none';
    document.getElementById('contraSearch').value = '';
    document.getElementById('contraAccountId').value = '';
    document.getElementById('amount').value = '';
    document.getElementById('description').value = '';
    document.getElementById('summaryCard').style.display = 'none';
    resetBalance();

    // Reset toggles
    partyType = 'customer';
    adjType   = 'debit';
    document.querySelectorAll('.party-btn').forEach(b => b.classList.toggle('active', b.dataset.type === 'customer'));
    document.querySelectorAll('.adj-btn').forEach(b => b.classList.toggle('active', b.dataset.type === 'debit'));
    document.getElementById('partyLabel').innerHTML = 'Customer <span class="req">*</span>';
    document.getElementById('partySearch').placeholder = 'Search customer...';
    updateAdjHint();
}

// ── Alert ─────────────────────────────────────────────────────────────────────
function showAlert(msg, type) {
    const el = document.getElementById('alertBox');
    el.textContent = msg;
    el.className = 'alert ' + type;
    el.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => { el.style.display = 'none'; }, 6000);
}
