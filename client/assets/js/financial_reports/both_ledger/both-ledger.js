document.addEventListener('DOMContentLoaded', function () {
    function esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    const summaryLedgerBtn = document.getElementById('summary-ledger-btn');
    const detailedLedgerBtn = document.getElementById('detailed-ledger-btn');
    const partyCode = document.getElementById('party-code');
    const partySearch = document.getElementById('party-search');
    const partyDropdown = document.getElementById('party-dropdown');
    const fromDate = document.getElementById('from-date');
    const toDate = document.getElementById('to-date');
    const fromDateError = document.getElementById('from-date-error');
    const toDateError = document.getElementById('to-date-error');
    const loadLedgerBtn = document.getElementById('load-ledger-btn');
    const resetFiltersBtn = document.getElementById('reset-filters-btn');
    const summaryLedgerTable = document.getElementById('summary-ledger-table');
    const detailedLedgerTable = document.getElementById('detailed-ledger-table');
    const ledgerTitle = document.getElementById('ledger-title');
    const currencyFilter = document.getElementById('currency-filter');
    const companySelect = document.getElementById('company');

    let allParties = [];
    let currentCurrencySymbol = window.currencySymbol || '';

    fromDate.value = '';
    toDate.value = '';

    loadCompanies();
    loadCurrencies();
    loadParties();

    function loadCompanies() {
        fetch('../../../../server/api/financial_reports/customer_ledger/get-companies.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    companySelect.innerHTML = '<option value="">All Companies</option>';
                    data.companies.forEach(c => {
                        const option = document.createElement('option');
                        option.value = c.id;
                        option.textContent = c.company_name;
                        companySelect.appendChild(option);
                    });
                }
            })
            .catch(err => console.error('Error loading companies:', err));
    }

    async function loadCurrencies() {
        try {
            const response = await fetch('../../../../server/api/financial_reports/both_ledger/both-ledger.php?type=currencies');
            const result = await response.json();
            if (result.success) {
                currencyFilter.innerHTML = '';
                result.data.forEach(currency => {
                    const isBase = currency.is_base_currency == 1;
                    currencyFilter.innerHTML += `<option value="${currency.id}" ${isBase ? 'selected' : ''}>${esc(currency.name)} (${esc(currency.symbol)})${isBase ? ' - Base' : ''}</option>`;
                    if (isBase) currentCurrencySymbol = currency.symbol;
                });
            }
        } catch (err) {
            console.error('Error loading currencies:', err);
        }
    }

    currencyFilter.addEventListener('change', function () { loadLedgerData(); });
    companySelect.addEventListener('change', function () { loadLedgerData(); });

    async function loadParties() {
        try {
            const response = await fetch('../../../../server/api/financial_reports/both_ledger/both-ledger.php?type=parties');
            const result = await response.json();
            if (result.success) {
                allParties = result.data;
                partyCode.innerHTML = '<option value="">Select Party</option>';
                result.data.forEach(p => {
                    partyCode.innerHTML += `<option value="${p.id}">${esc(p.customer_code)} - ${esc(p.customer_name)}</option>`;
                });
            }
        } catch (err) {
            console.error('Error loading parties:', err);
        }
    }

    let selectedIndex = -1;
    partySearch.addEventListener('input', function () {
        const term = this.value.toLowerCase().trim();
        selectedIndex = -1;
        const filtered = term.length === 0 || term === ' '
            ? allParties
            : allParties.filter(p => p.customer_name.toLowerCase().includes(term) || p.customer_code.toLowerCase().includes(term));
        if (filtered.length > 0) {
            partyDropdown.innerHTML = filtered.map(p => `<div class="customer-option" data-id="${p.id}">${esc(p.customer_code)} - ${esc(p.customer_name)}</div>`).join('');
            partyDropdown.style.display = 'block';
        } else {
            partyDropdown.style.display = 'none';
        }
    });

    partySearch.addEventListener('keydown', function (e) {
        const options = partyDropdown.querySelectorAll('.customer-option');
        if (options.length === 0) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); selectedIndex = Math.min(selectedIndex + 1, options.length - 1); updateSelection(options); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); selectedIndex = Math.max(selectedIndex - 1, -1); updateSelection(options); }
        else if (e.key === 'Enter' && selectedIndex >= 0) { e.preventDefault(); options[selectedIndex].click(); }
    });
    function updateSelection(options) {
        options.forEach((o, i) => o.classList.toggle('selected', i === selectedIndex));
    }

    partyDropdown.addEventListener('click', function (e) {
        if (e.target.classList.contains('customer-option')) {
            partySearch.value = e.target.textContent;
            partyCode.value = e.target.dataset.id;
            partyDropdown.style.display = 'none';
            if (partyCode.value) showDetailedLedger();
        }
    });

    document.addEventListener('click', function (e) {
        if (!partySearch.contains(e.target) && !partyDropdown.contains(e.target)) {
            partyDropdown.style.display = 'none';
        }
    });

    function showSummaryLedger() {
        hidePartyError();
        summaryLedgerBtn.classList.add('active');
        detailedLedgerBtn.classList.remove('active');
        summaryLedgerTable.style.display = 'table';
        detailedLedgerTable.style.display = 'none';
        ledgerTitle.textContent = 'Summary Ledger';
    }

    function showDetailedLedger() {
        if (!partyCode.value) { showPartyError(); return; }
        hidePartyError();
        detailedLedgerBtn.classList.add('active');
        summaryLedgerBtn.classList.remove('active');
        detailedLedgerTable.style.display = 'table';
        summaryLedgerTable.style.display = 'none';
        ledgerTitle.textContent = 'Detailed Ledger';
        loadLedgerData();
    }

    summaryLedgerBtn.addEventListener('click', function () { showSummaryLedger(); loadLedgerData(); });
    detailedLedgerBtn.addEventListener('click', showDetailedLedger);

    function showPartyError() {
        let errorCard = document.getElementById('party-error-card');
        if (!errorCard) {
            errorCard = document.createElement('div');
            errorCard.id = 'party-error-card';
            errorCard.className = 'card';
            errorCard.innerHTML = '<p style="color: var(--error); text-align: center; margin: 0;">Please select a Party first to view detailed ledger.</p>';
            document.querySelector('.card:last-of-type').insertAdjacentElement('afterend', errorCard);
        }
        errorCard.style.display = 'block';
        const tableCard = document.querySelector('.card:nth-last-child(2)');
        if (tableCard) tableCard.style.display = 'none';
    }
    function hidePartyError() {
        const errorCard = document.getElementById('party-error-card');
        if (errorCard) errorCard.style.display = 'none';
        const tableCard = document.querySelector('.card:nth-last-child(2)');
        if (tableCard) tableCard.style.display = 'block';
    }

    partyCode.addEventListener('change', function () {
        if (partyCode.value) showDetailedLedger();
        else showSummaryLedger();
    });

    function validateDates() {
        if (!fromDate.value || !toDate.value) return true;
        const from = new Date(fromDate.value);
        const to = new Date(toDate.value);
        if (isNaN(from.getTime()) || isNaN(to.getTime())) return false;
        if (from > to) {
            fromDate.classList.add('error'); toDate.classList.add('error');
            fromDateError.style.display = 'block'; toDateError.style.display = 'block';
            return false;
        }
        fromDate.classList.remove('error'); toDate.classList.remove('error');
        fromDateError.style.display = 'none'; toDateError.style.display = 'none';
        return true;
    }
    fromDate.addEventListener('change', validateDates);
    toDate.addEventListener('change', validateDates);

    loadLedgerBtn.addEventListener('click', loadLedgerData);
    resetFiltersBtn.addEventListener('click', function () {
        partyCode.value = '';
        partySearch.value = '';
        fromDate.value = '';
        toDate.value = '';
        companySelect.value = '';
        showSummaryLedger();
        loadLedgerData();
    });

    async function loadLedgerData() {
        if (!validateDates()) return;
        const isDetailed = detailedLedgerBtn.classList.contains('active');

        if (isDetailed && !partyCode.value) return;

        const params = new URLSearchParams({ type: isDetailed ? 'detailed' : 'summary' });
        if (fromDate.value) params.append('from_date', fromDate.value);
        if (toDate.value) params.append('to_date', toDate.value);
        if (isDetailed && partyCode.value) params.append('customer_id', partyCode.value);
        if (companySelect.value) params.append('company_id', companySelect.value);
        if (currencyFilter.value) params.append('currency_id', currencyFilter.value);

        try {
            const response = await fetch(`../../../../server/api/financial_reports/both_ledger/both-ledger.php?${params}`);
            const result = await response.json();
            if (result.success) {
                if (isDetailed) renderDetailedLedger(result.data);
                else renderSummaryLedger(result.data);
            } else {
                alert(result.message || 'Error loading data');
            }
        } catch (err) {
            console.error(err);
            alert('Network error occurred');
        }
    }

    function fmt(val) {
        const num = parseFloat(val || 0);
        return `${currentCurrencySymbol}${Math.abs(num).toFixed(2)} ${num >= 0 ? 'Dr' : 'Cr'}`;
    }
    function balClass(val) {
        return parseFloat(val || 0) >= 0 ? 'balance-positive' : 'balance-negative';
    }

    function renderSummaryLedger(data) {
        const tbody = summaryLedgerTable.querySelector('tbody');
        tbody.innerHTML = '';
        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:24px;">No "Both" parties found</td></tr>';
            return;
        }
        let totals = { co: 0, cd: 0, cc: 0, cb: 0, so: 0, sd: 0, sc: 0, sb: 0 };
        data.forEach(row => {
            totals.co += parseFloat(row.customer_opening_balance || 0);
            totals.cd += parseFloat(row.customer_total_debit || 0);
            totals.cc += parseFloat(row.customer_total_credit || 0);
            totals.cb += parseFloat(row.customer_closing_balance || 0);
            totals.so += parseFloat(row.supplier_opening_balance || 0);
            totals.sd += parseFloat(row.supplier_total_debit || 0);
            totals.sc += parseFloat(row.supplier_total_credit || 0);
            totals.sb += parseFloat(row.supplier_closing_balance || 0);
            tbody.innerHTML += `
                <tr>
                    <td>${esc(row.party_name)}</td>
                    <td class="${balClass(row.customer_opening_balance)}">${fmt(row.customer_opening_balance)}</td>
                    <td>${currentCurrencySymbol}${parseFloat(row.customer_total_debit || 0).toFixed(2)}</td>
                    <td>${currentCurrencySymbol}${parseFloat(row.customer_total_credit || 0).toFixed(2)}</td>
                    <td class="${balClass(row.customer_closing_balance)}">${fmt(row.customer_closing_balance)}</td>
                    <td class="${balClass(row.supplier_opening_balance)}">${fmt(row.supplier_opening_balance)}</td>
                    <td>${currentCurrencySymbol}${parseFloat(row.supplier_total_debit || 0).toFixed(2)}</td>
                    <td>${currentCurrencySymbol}${parseFloat(row.supplier_total_credit || 0).toFixed(2)}</td>
                    <td class="${balClass(row.supplier_closing_balance)}">${fmt(row.supplier_closing_balance)}</td>
                </tr>
            `;
        });
        tbody.innerHTML += `
            <tr class="totals-row">
                <td><strong>Totals</strong></td>
                <td><strong class="${balClass(totals.co)}">${fmt(totals.co)}</strong></td>
                <td><strong>${currentCurrencySymbol}${totals.cd.toFixed(2)}</strong></td>
                <td><strong>${currentCurrencySymbol}${totals.cc.toFixed(2)}</strong></td>
                <td><strong class="${balClass(totals.cb)}">${fmt(totals.cb)}</strong></td>
                <td><strong class="${balClass(totals.so)}">${fmt(totals.so)}</strong></td>
                <td><strong>${currentCurrencySymbol}${totals.sd.toFixed(2)}</strong></td>
                <td><strong>${currentCurrencySymbol}${totals.sc.toFixed(2)}</strong></td>
                <td><strong class="${balClass(totals.sb)}">${fmt(totals.sb)}</strong></td>
            </tr>
        `;
    }

    function txnTypeClass(txnType) {
        return 'txn-type-' + txnType.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    }

    function renderDetailedLedger(data) {
        const tbody = detailedLedgerTable.querySelector('tbody');
        tbody.innerHTML = '';
        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:24px;">No transactions found</td></tr>';
            return;
        }
        let totalDebit = 0, totalCredit = 0;
        data.forEach(row => {
            const debit = parseFloat(row.debit || 0);
            const credit = parseFloat(row.credit || 0);
            totalDebit += debit;
            totalCredit += credit;
            const isOpening = row.txn_type.indexOf('Opening') === 0;
            tbody.innerHTML += `
                <tr${isOpening ? ' style="background: rgba(31,123,255,0.04);"' : ''}>
                    <td>${esc(row.date)}</td>
                    <td>${esc(row.description)} <span class="side-tag">(${row.side === 'customer' ? 'Customer' : 'Supplier'})</span></td>
                    <td>${esc(row.reference)}</td>
                    <td><span class="txn-type-badge ${txnTypeClass(row.txn_type)}">${esc(row.txn_type)}</span></td>
                    <td>${debit > 0 ? currentCurrencySymbol + debit.toFixed(2) : ''}</td>
                    <td>${credit > 0 ? currentCurrencySymbol + credit.toFixed(2) : ''}</td>
                    <td class="${balClass(row.customer_running_balance)}">${fmt(row.customer_running_balance)}</td>
                    <td class="${balClass(row.supplier_running_balance)}">${fmt(row.supplier_running_balance)}</td>
                </tr>
            `;
        });
        tbody.innerHTML += `
            <tr class="totals-row">
                <td colspan="4" style="text-align:right;"><strong>Totals:</strong></td>
                <td><strong>${currentCurrencySymbol}${totalDebit.toFixed(2)}</strong></td>
                <td><strong>${currentCurrencySymbol}${totalCredit.toFixed(2)}</strong></td>
                <td colspan="2"></td>
            </tr>
        `;
    }

    // Initial view
    showSummaryLedger();
    loadLedgerData();
});
