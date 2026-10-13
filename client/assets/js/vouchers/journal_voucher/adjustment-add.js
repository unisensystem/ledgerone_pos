// ── State ─────────────────────────────────────────────────────────────────────
let adjMode    = 'single';    // 'single' | 'cross'
let partyType  = 'customer';  // party1
let partyType2 = 'customer';  // party2 (cross mode)
let adjType    = 'debit';     // 'debit' | 'credit'  (single mode only)
let allParties = [];
let allParties2 = [];
let allAccounts = [];
let currentBalance  = null;
let currentBalance2 = null;

// ── Init ──────────────────────────────────────────────────────────────────────
document.getElementById('voucherDate').valueAsDate = new Date();

Promise.all([
    fetch('../../../../server/api/vouchers/transfer_voucher/get-customers.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/journal_voucher/get-accounts.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/journal_voucher/get-companies.php').then(r => r.json()),
    fetch('../../../../server/api/vouchers/journal_voucher/get-next-voucher-number.php?prefix=ADJ').then(r => r.json()),
]).then(([custData, accData, compData, vnData]) => {
    if (custData.success) {
        allParties  = custData.data.map(c => ({ id: c.id, code: c.customer_code, name: c.customer_name }));
        allParties2 = [...allParties];
    }
    if (accData.success) { allAccounts = accData.accounts; setupContraDropdown(); }
    if (compData.success) {
        const sel = document.getElementById('company');
        compData.data.forEach((c, i) => {
            const o = new Option(c.company_name, c.id);
            if (i === 0 && compData.data.length === 1) o.selected = true;
            sel.appendChild(o);
        });
    }
    if (vnData.success) document.getElementById('voucherNumber').value = vnData.voucherNumber;
});

// ── Mode Toggle ───────────────────────────────────────────────────────────────
document.querySelectorAll('.mode-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        adjMode = this.dataset.mode;
        document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        const isCross = adjMode === 'cross';

        // Party 1 card title
        document.getElementById('party1CardTitle').textContent = isCross ? 'Party 1 Selection' : 'Party Selection';

        // Show/hide party2 card
        document.getElementById('party2Card').style.display  = isCross ? 'block' : 'none';

        // Contra account: only single mode
        document.getElementById('contraGroup').style.display = isCross ? 'none' : '';

        // New balance: single mode = in adjustment details, cross mode = in party cards
        document.getElementById('newBalSingleGroup').style.display = isCross ? 'none' : '';
        document.getElementById('party1NewBalGroup').style.display  = isCross ? '' : 'none';
        document.getElementById('party2NewBalGroup').style.display  = isCross ? '' : 'none';
        document.getElementById('party1EntryGroup').style.display   = isCross ? '' : 'none';
        document.getElementById('party2EntryGroup').style.display   = isCross ? '' : 'none';

        // Adj type button labels
        document.getElementById('btnDebit').innerHTML  = isCross
            ? '<i class="fas fa-arrow-up"></i> Debit Party 1 / Credit Party 2'
            : '<i class="fas fa-arrow-up"></i> Debit (Increase)';
        document.getElementById('btnCredit').innerHTML = isCross
            ? '<i class="fas fa-arrow-down"></i> Credit Party 1 / Debit Party 2'
            : '<i class="fas fa-arrow-down"></i> Credit (Decrease)';

        document.getElementById('modeHintText').textContent = isCross
            ? 'Cross party: choose direction — Party 1 Debit means Party 2 gets Credit (and vice versa)'
            : 'Single party: adjust one customer or supplier balance against a contra account';

        resetBalance();
        resetBalance2();
        updateAdjHint();
        updateCrossPartyBadges();
        updatePreview();
    });
});

// ── Party 1 Type Toggle ───────────────────────────────────────────────────────
document.querySelectorAll('.party-btn[id^="btnCustomer"], .party-btn[id^="btnSupplier"]').forEach(btn => {
    if (btn.id === 'btnCustomer2' || btn.id === 'btnSupplier2') return;
    btn.addEventListener('click', function () {
        partyType = this.dataset.type;
        document.getElementById('btnCustomer').classList.toggle('active', partyType === 'customer');
        document.getElementById('btnSupplier').classList.toggle('active', partyType === 'supplier');
        resetParty1();
        loadPartyList(1);
        updateAdjHint();
    });
});

// ── Party 2 Type Toggle ───────────────────────────────────────────────────────
document.getElementById('btnCustomer2').addEventListener('click', function () {
    partyType2 = 'customer';
    document.getElementById('btnCustomer2').classList.add('active');
    document.getElementById('btnSupplier2').classList.remove('active');
    resetParty2();
    loadPartyList(2);
});
document.getElementById('btnSupplier2').addEventListener('click', function () {
    partyType2 = 'supplier';
    document.getElementById('btnSupplier2').classList.add('active');
    document.getElementById('btnCustomer2').classList.remove('active');
    resetParty2();
    loadPartyList(2);
});

function loadPartyList(num) {
    const type = num === 1 ? partyType : partyType2;
    const api = type === 'customer'
        ? '../../../../server/api/vouchers/transfer_voucher/get-customers.php'
        : '../../../../server/api/vouchers/transfer_voucher/get-suppliers.php';

    fetch(api).then(r => r.json()).then(data => {
        if (data.success) {
            const list = data.data.map(p => ({
                id:   p.id,
                code: type === 'customer' ? p.customer_code : p.supplier_code,
                name: type === 'customer' ? p.customer_name : p.supplier_name
            }));
            if (num === 1) allParties  = list;
            else           allParties2 = list;
        }
    });
}

function resetParty1() {
    document.getElementById('partySearch').value = '';
    document.getElementById('partyId').value = '';
    document.getElementById('partyCode').value = '';
    document.getElementById('subAccount').innerHTML = '<option value="">All / Main Account</option>';
    document.getElementById('subAccountGroup').style.display = 'none';
    document.getElementById('partyLabel').innerHTML =
        (partyType === 'customer' ? 'Customer' : 'Supplier') + ' <span class="req">*</span>';
    document.getElementById('partySearch').placeholder = 'Search ' + (partyType === 'customer' ? 'customer' : 'supplier') + '...';
    resetBalance();
}

function resetParty2() {
    document.getElementById('partySearch2').value = '';
    document.getElementById('partyId2').value = '';
    document.getElementById('partyCode2').value = '';
    document.getElementById('subAccount2').innerHTML = '<option value="">All / Main Account</option>';
    document.getElementById('subAccountGroup2').style.display = 'none';
    document.getElementById('partyLabel2').innerHTML =
        (partyType2 === 'customer' ? 'Customer' : 'Supplier') + ' <span class="req">*</span>';
    document.getElementById('partySearch2').placeholder = 'Search ' + (partyType2 === 'customer' ? 'customer' : 'supplier') + '...';
    resetBalance2();
}

// ── Party 1 Search ────────────────────────────────────────────────────────────
const partySearchInput = document.getElementById('partySearch');
const partyDropdown    = document.getElementById('partyDropdown');
let partyHighlight = -1;

partySearchInput.addEventListener('input',  () => renderPartyDropdown(partySearchInput.value.toLowerCase(), 1));
partySearchInput.addEventListener('focus',  () => renderPartyDropdown(partySearchInput.value.toLowerCase(), 1));
partySearchInput.addEventListener('keydown', e => handleDropdownKeys(e, partyDropdown, partyHighlight, v => partyHighlight = v));
document.addEventListener('click', e => {
    if (!partySearchInput.contains(e.target) && !partyDropdown.contains(e.target))
        partyDropdown.classList.remove('show');
});

// ── Party 2 Search ────────────────────────────────────────────────────────────
const partySearchInput2 = document.getElementById('partySearch2');
const partyDropdown2    = document.getElementById('partyDropdown2');
let partyHighlight2 = -1;

partySearchInput2.addEventListener('input',  () => renderPartyDropdown(partySearchInput2.value.toLowerCase(), 2));
partySearchInput2.addEventListener('focus',  () => renderPartyDropdown(partySearchInput2.value.toLowerCase(), 2));
partySearchInput2.addEventListener('keydown', e => handleDropdownKeys(e, partyDropdown2, partyHighlight2, v => partyHighlight2 = v));
document.addEventListener('click', e => {
    if (!partySearchInput2.contains(e.target) && !partyDropdown2.contains(e.target))
        partyDropdown2.classList.remove('show');
});

function renderPartyDropdown(term, num) {
    const list     = num === 1 ? allParties : allParties2;
    const dropdown = num === 1 ? partyDropdown : partyDropdown2;
    if (num === 1) partyHighlight  = -1;
    else           partyHighlight2 = -1;

    const filtered = term ? list.filter(p =>
        p.name.toLowerCase().includes(term) || p.code.toLowerCase().includes(term)
    ) : list;

    if (!filtered.length) { dropdown.classList.remove('show'); return; }

    dropdown.innerHTML = filtered.slice(0, 12).map(p =>
        `<div class="dropdown-item" data-id="${p.id}" data-code="${p.code}" data-name="${p.name}">
            <strong>${p.code}</strong> — ${p.name}
        </div>`
    ).join('');
    dropdown.classList.add('show');

    dropdown.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', function () {
            selectParty(this.dataset.id, this.dataset.code, this.dataset.name, num);
        });
    });
}

function selectParty(id, code, name, num) {
    const type = num === 1 ? partyType : partyType2;
    if (num === 1) {
        document.getElementById('partySearch').value = `${code} — ${name}`;
        document.getElementById('partyId').value = id;
        document.getElementById('partyCode').value = code;
        partyDropdown.classList.remove('show');
    } else {
        document.getElementById('partySearch2').value = `${code} — ${name}`;
        document.getElementById('partyId2').value = id;
        document.getElementById('partyCode2').value = code;
        partyDropdown2.classList.remove('show');
    }

    // Load sub accounts
    const subApi = type === 'customer'
        ? `../../../../server/api/vouchers/transfer_voucher/get-customer-sub-accounts.php?customer_code=${code}`
        : `../../../../server/api/vouchers/transfer_voucher/get-sub-accounts.php?supplier_code=${code}`;

    fetch(subApi).then(r => r.json()).then(data => {
        const selId  = num === 1 ? 'subAccount'      : 'subAccount2';
        const grpId  = num === 1 ? 'subAccountGroup' : 'subAccountGroup2';
        const sel    = document.getElementById(selId);
        sel.innerHTML = '<option value="">All / Main Account</option>';
        if (data.success && data.data.length > 0) {
            data.data.forEach(s => sel.appendChild(new Option(s.sub_account_name, s.id)));
            document.getElementById(grpId).style.display = 'flex';
        } else {
            document.getElementById(grpId).style.display = 'none';
        }
    });

    fetchBalance(id, num);
}

// ── Fetch Balance ─────────────────────────────────────────────────────────────
function fetchBalance(id, num) {
    const type = num === 1 ? partyType : partyType2;
    const api = type === 'customer'
        ? `../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?customer_id=${id}&type=summary`
        : `../../../../server/api/financial_reports/supplier_ledger/supplier-ledger.php?supplier_id=${id}&type=summary`;

    fetch(api).then(r => r.json()).then(data => {
        const bal = (data.success && data.data && data.data.length > 0)
            ? parseFloat(data.data[0].closing_balance || 0)
            : 0;
        if (num === 1) { currentBalance  = bal; renderBalance('balanceAmount',  'balanceLabel',  bal); }
        else           { currentBalance2 = bal; renderBalance('balanceAmount2', 'balanceLabel2', bal); }
        updateNewBalance();
        updateCrossPartyBadges();
    });
}

function renderBalance(amountId, labelId, value) {
    const amountEl = document.getElementById(amountId);
    const labelEl  = document.getElementById(labelId);
    if (!amountEl || !labelEl) return;
    if (value === null || value === undefined || isNaN(value)) {
        amountEl.textContent = '\u2014'; labelEl.textContent = ''; labelEl.className = 'balance-label'; return;
    }
    amountEl.textContent = Math.abs(value).toFixed(2);
    if (value > 0)      { labelEl.textContent = 'Dr'; labelEl.className = 'balance-label dr'; }
    else if (value < 0) { labelEl.textContent = 'Cr'; labelEl.className = 'balance-label cr'; }
    else                { labelEl.textContent = 'Zero'; labelEl.className = 'balance-label zero'; }
}

function resetBalance()  {
    currentBalance = null;
    renderBalance('balanceAmount', 'balanceLabel', null);
    renderBalance('newBalanceAmount', 'newBalanceLabel', null);
    renderBalance('newBalSingleAmount', 'newBalSingleLabel', null);
    document.getElementById('summaryCard').style.display = 'none';
}
function resetBalance2() {
    currentBalance2 = null;
    renderBalance('balanceAmount2', 'balanceLabel2', null);
    renderBalance('newBalanceAmount2', 'newBalanceLabel2', null);
}

// ── Adjustment Type Toggle (both modes) ──────────────────────────────────────
document.querySelectorAll('.adj-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        adjType = this.dataset.type;
        document.querySelectorAll('.adj-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        updateAdjHint();
        updateCrossPartyBadges();
        updateNewBalance();
        updatePreview();
    });
});

function updateAdjHint() {
    const hintEl = document.getElementById('adjHintText');
    if (adjMode === 'cross') {
        const p1label = partyType  === 'customer' ? 'Customer' : 'Supplier';
        const p2label = partyType2 === 'customer' ? 'Customer' : 'Supplier';
        if (adjType === 'debit') {
            hintEl.textContent = `Party 1 (${p1label}) → Debit (balance ↑) | Party 2 (${p2label}) → Credit (balance ↓)`;
        } else {
            hintEl.textContent = `Party 1 (${p1label}) → Credit (balance ↓) | Party 2 (${p2label}) → Debit (balance ↑)`;
        }
    } else {
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
}

// ── Cross-party entry side badges ────────────────────────────────────────────
function updateCrossPartyBadges() {
    if (adjMode !== 'cross') return;
    const badge1 = document.getElementById('party1EntryBadge');
    const badge2 = document.getElementById('party2EntryBadge');

    if (adjType === 'debit') {
        badge1.className = 'entry-side-badge debit';
        badge1.innerHTML = '<i class="fas fa-arrow-up"></i> Debit (Dr) — Balance Increases';
        badge2.className = 'entry-side-badge credit';
        badge2.innerHTML = '<i class="fas fa-arrow-down"></i> Credit (Cr) — Balance Decreases';
    } else {
        badge1.className = 'entry-side-badge credit';
        badge1.innerHTML = '<i class="fas fa-arrow-down"></i> Credit (Cr) — Balance Decreases';
        badge2.className = 'entry-side-badge debit';
        badge2.innerHTML = '<i class="fas fa-arrow-up"></i> Debit (Dr) — Balance Increases';
    }
}

// ── Contra Account Dropdown ───────────────────────────────────────────────────
const contraInput    = document.getElementById('contraSearch');
const contraDropdown = document.getElementById('contraDropdown');
let contraHighlight  = -1;

function setupContraDropdown() {
    contraInput.addEventListener('input',  () => renderContraDropdown(contraInput.value.toLowerCase()));
    contraInput.addEventListener('focus',  () => renderContraDropdown(contraInput.value.toLowerCase()));
    contraInput.addEventListener('keydown', e => handleDropdownKeys(e, contraDropdown, contraHighlight, v => contraHighlight = v));
    document.addEventListener('click', e => {
        if (!contraInput.contains(e.target) && !contraDropdown.contains(e.target))
            contraDropdown.classList.remove('show');
    });
}

function renderContraDropdown(term) {
    contraHighlight = -1;
    const filtered = term ? allAccounts.filter(a => a.name.toLowerCase().includes(term)) : allAccounts;
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

// ── Keyboard nav helper ───────────────────────────────────────────────────────
function handleDropdownKeys(e, dropdown, highlight, setHighlight) {
    const items = dropdown.querySelectorAll('.dropdown-item');
    if (e.key === 'ArrowDown')  { e.preventDefault(); setHighlight(Math.min(highlight + 1, items.length - 1)); highlightItems(items, highlight + 1); }
    else if (e.key === 'ArrowUp')   { e.preventDefault(); setHighlight(Math.max(highlight - 1, -1)); highlightItems(items, highlight - 1); }
    else if (e.key === 'Enter' && highlight >= 0) { e.preventDefault(); items[highlight].click(); }
    else if (e.key === 'Escape') dropdown.classList.remove('show');
}

function highlightItems(items, idx) {
    items.forEach((it, i) => it.classList.toggle('highlighted', i === idx));
    if (idx >= 0) items[idx].scrollIntoView({ block: 'nearest' });
}

// ── Amount Change ─────────────────────────────────────────────────────────────
document.getElementById('amount').addEventListener('input', () => { updateNewBalance(); updatePreview(); });

function updateNewBalance() {
    const amt = parseFloat(document.getElementById('amount').value) || 0;

    if (adjMode === 'cross') {
        if (currentBalance !== null) {
            const newBal1 = adjType === 'debit' ? currentBalance + amt : currentBalance - amt;
            renderBalance('newBalanceAmount', 'newBalanceLabel', newBal1);
        }
        if (currentBalance2 !== null) {
            const newBal2 = adjType === 'debit' ? currentBalance2 - amt : currentBalance2 + amt;
            renderBalance('newBalanceAmount2', 'newBalanceLabel2', newBal2);
        }
    } else {
        if (currentBalance !== null) {
            const newBal = adjType === 'debit' ? currentBalance + amt : currentBalance - amt;
            renderBalance('newBalSingleAmount', 'newBalSingleLabel', newBal);
        } else {
            renderBalance('newBalSingleAmount', 'newBalSingleLabel', null);
        }
    }
}

// ── Preview Table ─────────────────────────────────────────────────────────────
function updatePreview() {
    const partyName  = document.getElementById('partySearch').value;
    const amt        = parseFloat(document.getElementById('amount').value) || 0;

    if (adjMode === 'cross') {
        const party2Name = document.getElementById('partySearch2').value;
        if (!partyName || !party2Name || amt <= 0) { document.getElementById('summaryCard').style.display = 'none'; return; }

        const acct1 = partyType  === 'customer' ? 'Trade Debtors (Customer)'  : 'Trade Creditors (Supplier)';
        const acct2 = partyType2 === 'customer' ? 'Trade Debtors (Customer)'  : 'Trade Creditors (Supplier)';

        // adjType='debit'  => Party1 DR, Party2 CR
        // adjType='credit' => Party1 CR, Party2 DR
        let rows;
        if (adjType === 'debit') {
            rows = [
                { account: acct1 + ' — ' + partyName,  debit: amt, credit: 0 },
                { account: acct2 + ' — ' + party2Name, debit: 0,   credit: amt },
            ];
        } else {
            rows = [
                { account: acct2 + ' — ' + party2Name, debit: amt, credit: 0 },
                { account: acct1 + ' — ' + partyName,  debit: 0,   credit: amt },
            ];
        }
        renderPreviewRows(rows, amt);
    } else {
        const contraName = contraInput.value;
        const contraId   = document.getElementById('contraAccountId').value;
        if (!partyName || !contraName || !contraId || amt <= 0) { document.getElementById('summaryCard').style.display = 'none'; return; }

        const partyAccountName = partyType === 'customer' ? 'Trade Debtors (Customer)' : 'Trade Creditors (Supplier)';
        let rows = [];
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
        renderPreviewRows(rows, amt);
    }
}

function renderPreviewRows(rows, amt) {
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

// ── Validate ──────────────────────────────────────────────────────────────────
function validate() {
    const date    = document.getElementById('voucherDate').value;
    const company = document.getElementById('company').value;
    const partyId = document.getElementById('partyId').value;
    const amt     = parseFloat(document.getElementById('amount').value);
    const desc    = document.getElementById('description').value.trim();

    if (!date)    { showAlert('Please select a date.', 'error'); return false; }
    if (!company) { showAlert('Please select a company.', 'error'); return false; }
    if (!partyId) { showAlert('Please select Party 1.', 'error'); return false; }
    if (!amt || amt <= 0) { showAlert('Please enter a valid amount.', 'error'); return false; }
    if (!desc)    { showAlert('Please enter a description.', 'error'); return false; }

    if (adjMode === 'cross') {
        const partyId2 = document.getElementById('partyId2').value;
        if (!partyId2) { showAlert('Please select Party 2.', 'error'); return false; }
        if (partyId === partyId2 && partyType === partyType2) { showAlert('Party 1 and Party 2 cannot be the same.', 'error'); return false; }
    } else {
        const contraId = document.getElementById('contraAccountId').value;
        if (!contraId) { showAlert('Please select a contra account.', 'error'); return false; }
    }
    return true;
}

// ── Post Button ───────────────────────────────────────────────────────────────
document.getElementById('postBtn').addEventListener('click', function () {
    if (!validate()) return;

    const partyName  = document.getElementById('partySearch').value;
    const amt        = parseFloat(document.getElementById('amount').value);
    const desc       = document.getElementById('description').value;

    const p1side = adjType === 'debit' ? 'Debit ↑' : 'Credit ↓';
    const p2side = adjType === 'debit' ? 'Credit ↓' : 'Debit ↑';
    let bodyHtml = `
        <p><strong>Mode:</strong> ${adjMode === 'cross' ? 'Cross Party' : 'Single Party'}</p>
        <p><strong>Party 1:</strong> ${partyName} (${partyType}) — <span style="color:#1F7BFF">${p1side}</span></p>
    `;
    if (adjMode === 'cross') {
        bodyHtml += `<p><strong>Party 2:</strong> ${document.getElementById('partySearch2').value} (${partyType2}) — <span style="color:#E34F4F">${p2side}</span></p>`;
    } else {
        bodyHtml += `<p><strong>Adjustment:</strong> ${adjType === 'debit' ? 'Debit' : 'Credit'} — ${amt.toFixed(2)}</p>`;
        bodyHtml += `<p><strong>Contra Account:</strong> ${contraInput.value}</p>`;
    }
    bodyHtml += `
        <p><strong>Amount:</strong> ${amt.toFixed(2)}</p>
        <p><strong>Description:</strong> ${desc}</p>
        <p style="margin-top:12px; color:#E34F4F; font-size:13px;">
            <i class="fas fa-exclamation-triangle"></i> This entry will be posted and cannot be undone.
        </p>
    `;

    document.getElementById('modalBody').innerHTML = bodyHtml;
    document.getElementById('confirmModal').classList.add('show');
});

document.getElementById('closeModal').addEventListener('click', () => document.getElementById('confirmModal').classList.remove('show'));
document.getElementById('cancelBtn').addEventListener('click',  () => document.getElementById('confirmModal').classList.remove('show'));
window.addEventListener('click', e => { if (e.target === document.getElementById('confirmModal')) document.getElementById('confirmModal').classList.remove('show'); });

document.getElementById('confirmBtn').addEventListener('click', async function () {
    document.getElementById('confirmModal').classList.remove('show');
    await postAdjustment();
});

// ── Post Adjustment ───────────────────────────────────────────────────────────
async function postAdjustment() {
    const postBtn = document.getElementById('postBtn');
    postBtn.disabled = true;
    postBtn.innerHTML = '<div class="spinner"></div> Posting...';

    const payload = {
        adj_mode:          adjMode,
        voucher_date:      document.getElementById('voucherDate').value,
        voucher_number:    document.getElementById('voucherNumber').value,
        company_id:        document.getElementById('company').value,
        // Party 1
        party_type:        partyType,
        party_id:          document.getElementById('partyId').value,
        party_code:        document.getElementById('partyCode').value,
        sub_account_id:    document.getElementById('subAccount').value || null,
        // Party 2 (cross mode)
        party_type2:       partyType2,
        party_id2:         document.getElementById('partyId2').value || null,
        party_code2:       document.getElementById('partyCode2').value || null,
        sub_account_id2:   document.getElementById('subAccount2').value || null,
        // Single mode fields
        adj_type:          adjType,
        contra_account_id: document.getElementById('contraAccountId').value || null,
        // Common
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
    document.getElementById('amount').value = '';
    document.getElementById('description').value = '';
    document.getElementById('contraSearch').value = '';
    document.getElementById('contraAccountId').value = '';
    document.getElementById('summaryCard').style.display = 'none';

    partyType = 'customer'; partyType2 = 'customer'; adjType = 'debit';
    document.querySelectorAll('.party-btn').forEach(b => b.classList.toggle('active', b.dataset.type === 'customer'));
    document.querySelectorAll('.adj-btn').forEach(b => b.classList.toggle('active', b.dataset.type === 'debit'));

    // Reset adj button labels based on current mode
    const isCross = adjMode === 'cross';
    document.getElementById('btnDebit').innerHTML  = isCross
        ? '<i class="fas fa-arrow-up"></i> Debit Party 1 / Credit Party 2'
        : '<i class="fas fa-arrow-up"></i> Debit (Increase)';
    document.getElementById('btnCredit').innerHTML = isCross
        ? '<i class="fas fa-arrow-down"></i> Credit Party 1 / Debit Party 2'
        : '<i class="fas fa-arrow-down"></i> Credit (Decrease)';
    document.getElementById('party1CardTitle').textContent = adjMode === 'cross' ? 'Party 1 Selection' : 'Party Selection';
    document.getElementById('party1EntryGroup').style.display  = adjMode === 'cross' ? '' : 'none';
    document.getElementById('party1NewBalGroup').style.display = adjMode === 'cross' ? '' : 'none';
    document.getElementById('party2EntryGroup').style.display  = adjMode === 'cross' ? '' : 'none';
    document.getElementById('party2NewBalGroup').style.display = adjMode === 'cross' ? '' : 'none';
    document.getElementById('newBalSingleGroup').style.display = adjMode === 'cross' ? 'none' : '';

    resetParty1(); resetParty2();
    updateAdjHint();
    updateCrossPartyBadges();
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
