const API_BASE = '../../../../server/api/banking/bank_statement/bank-statement.php';

let currentStatement = null;

function escHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

function formatMoney(n) {
    const num = parseFloat(n) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(d) {
    if (!d) return '';
    const dt = new Date(d);
    if (isNaN(dt.getTime())) return d;
    return dt.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

async function loadBankAccounts() {
    try {
        const res = await fetch(`${API_BASE}?type=banks`);
        const data = await res.json();
        const select = document.getElementById('bankAccountFilter');
        if (data.success && data.data.length) {
            data.data.forEach(bank => {
                const opt = document.createElement('option');
                opt.value = bank.id;
                opt.textContent = `${bank.bank_name} — ${bank.account_title} (${bank.account_number})`;
                select.appendChild(opt);
            });
        } else {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'No bank accounts found';
            opt.disabled = true;
            select.appendChild(opt);
        }
    } catch (err) {
        console.error('Failed to load bank accounts:', err);
    }
}

function toISODate(d) {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function computeDateRange(preset) {
    const today = new Date();
    const to = new Date(today);
    let from;

    switch (preset) {
        case 'today':
            from = new Date(today);
            break;
        case 'week': {
            const day = today.getDay();
            from = new Date(today);
            from.setDate(today.getDate() - day);
            break;
        }
        case 'quarter': {
            const quarterStartMonth = Math.floor(today.getMonth() / 3) * 3;
            from = new Date(today.getFullYear(), quarterStartMonth, 1);
            break;
        }
        case 'year':
            from = new Date(today.getFullYear(), 0, 1);
            break;
        case 'month':
        default:
            from = new Date(today.getFullYear(), today.getMonth(), 1);
            break;
    }

    return { from: toISODate(from), to: toISODate(to) };
}

function applyDateRangePreset() {
    const preset = document.getElementById('dateRangePreset').value;
    const isCustom = preset === 'custom';
    document.getElementById('fromDateGroup').style.display = isCustom ? '' : 'none';
    document.getElementById('toDateGroup').style.display = isCustom ? '' : 'none';

    if (!isCustom) {
        const { from, to } = computeDateRange(preset);
        document.getElementById('fromDate').value = from;
        document.getElementById('toDate').value = to;
    }
}

function setDefaultDates() {
    document.getElementById('dateRangePreset').value = 'month';
    applyDateRangePreset();
}

async function generateStatement() {
    const bankAccountId = document.getElementById('bankAccountFilter').value;
    if (!bankAccountId) {
        alert('Please select a bank account first.');
        return;
    }

    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    const txType = document.getElementById('txTypeFilter').value;
    const search = document.getElementById('searchFilter').value.trim();

    const generateBtn = document.getElementById('generateBtn');
    const originalText = generateBtn.innerHTML;
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

    try {
        const params = new URLSearchParams({
            bank_account_id: bankAccountId,
            tx_type: txType,
        });
        if (fromDate) params.set('from_date', fromDate);
        if (toDate) params.set('to_date', toDate);
        if (search) params.set('search', search);

        const res = await fetch(`${API_BASE}?${params.toString()}`);
        const data = await res.json();

        if (!data.success) {
            alert(data.message || 'Failed to generate statement');
            return;
        }

        currentStatement = data;
        renderStatement(data);
        document.getElementById('printBtn').disabled = false;
    } catch (err) {
        console.error('Failed to generate statement:', err);
        alert('Something went wrong while generating the statement.');
    } finally {
        generateBtn.disabled = false;
        generateBtn.innerHTML = originalText;
    }
}

function renderStatement(data) {
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('statementResult').style.display = 'block';

    const bank = data.bank;
    const symbol = bank.currency_symbol || '';

    document.getElementById('shBankName').textContent = bank.is_mfb && bank.mfb_name
        ? `${bank.bank_name} (${bank.mfb_name})`
        : (bank.bank_name || '—');
    document.getElementById('shAccountTitle').textContent =
        `${bank.account_title || ''}${bank.company_name ? ' · ' + bank.company_name : ''}`;
    document.getElementById('shAccountNumber').textContent = bank.account_number || '—';
    document.getElementById('shAccountType').textContent = bank.account_type
        ? bank.account_type.charAt(0).toUpperCase() + bank.account_type.slice(1)
        : '—';
    document.getElementById('shCurrency').textContent = bank.currency_code
        ? `${bank.currency_code}${bank.currency_name ? ' - ' + bank.currency_name : ''}`
        : '—';
    document.getElementById('shIban').textContent = bank.iban || '—';
    document.getElementById('shSwift').textContent = bank.swift_code || '—';
    document.getElementById('shBranch').textContent = bank.branch_name || '—';

    const statusBadge = document.getElementById('shStatusBadge');
    statusBadge.style.display = 'inline-block';
    statusBadge.textContent = bank.is_active ? 'Active' : 'Inactive';
    statusBadge.className = 'status-badge ' + (bank.is_active ? 'active' : 'inactive');

    const logoWrapper = document.getElementById('shLogoWrapper');
    logoWrapper.innerHTML = '';
    if (bank.bank_logo_path) {
        const filename = bank.bank_logo_path.split('/').pop();
        const img = document.createElement('img');
        img.src = '../../../assets/uploads/bank_logo/' + filename;
        img.alt = 'Bank Logo';
        img.style.width = '48px';
        img.style.height = '48px';
        img.style.objectFit = 'contain';
        img.style.borderRadius = '8px';
        img.style.backgroundColor = '#ffffff';
        img.style.padding = '4px';
        logoWrapper.appendChild(img);
    }

    // Build the extra-details chip bar — only chips with real data get added,
    // and the whole bar stays hidden when the bank account has nothing on file.
    const branchAddressParts = [bank.branch_address, bank.branch_city, bank.branch_state].filter(Boolean);
    const chips = [
        { icon: 'fa-location-dot', label: 'Branch Address', value: branchAddressParts.join(', ') },
        { icon: 'fa-building', label: 'Branch Code', value: bank.branch_code },
        { icon: 'fa-user-tie', label: 'Contact', value: bank.contact_person },
        { icon: 'fa-phone', label: 'Phone', value: bank.contact_number },
        { icon: 'fa-envelope', label: 'Email', value: bank.email },
        { icon: 'fa-briefcase', label: 'Company', value: bank.company_name },
    ].filter(c => c.value && c.value.trim() !== '');

    const extraBar = document.getElementById('shExtraDetails');
    if (chips.length) {
        extraBar.innerHTML = chips.map(c => `
            <span class="detail-chip"><i class="fas ${c.icon}"></i>${escHtml(c.label)}: <strong>${escHtml(c.value)}</strong></span>
        `).join('');
        extraBar.style.display = 'flex';
    } else {
        extraBar.innerHTML = '';
        extraBar.style.display = 'none';
    }

    document.getElementById('shStatementRef').textContent = data.statement_ref || '—';
    document.getElementById('shGeneratedBy').textContent = data.generated_by || '—';
    document.getElementById('shGeneratedAt').textContent = data.generated_at ? formatDate(data.generated_at) : '—';

    const fromLabel = data.from_date ? formatDate(data.from_date) : 'Beginning';
    const toLabel = formatDate(data.to_date);
    document.getElementById('shPeriod').textContent = `Statement Period: ${fromLabel} to ${toLabel}`;

    const openingEl = document.getElementById('sumOpening');
    openingEl.textContent = `${symbol} ${formatMoney(Math.abs(data.opening_balance))}${data.opening_balance < 0 ? ' DR' : ''}`;
    openingEl.style.color = data.opening_balance < 0 ? 'var(--error)' : '';
    document.querySelector('.opening-balance').classList.toggle('overdrawn', data.opening_balance < 0);
    document.getElementById('sumOpeningAsOf').textContent = bank.as_of_date ? `as of ${formatDate(bank.as_of_date)}` : '';
    document.getElementById('sumDeposits').textContent = `${symbol} ${formatMoney(data.total_deposits)}`;
    document.getElementById('sumDepositsCount').textContent = `${data.deposit_count} transaction${data.deposit_count === 1 ? '' : 's'}`;
    document.getElementById('sumWithdrawals').textContent = `${symbol} ${formatMoney(data.total_withdrawals)}`;
    document.getElementById('sumWithdrawalsCount').textContent = `${data.withdrawal_count} transaction${data.withdrawal_count === 1 ? '' : 's'}`;
    const closingEl = document.getElementById('sumClosing');
    closingEl.textContent = `${symbol} ${formatMoney(Math.abs(data.closing_balance))}${data.closing_balance < 0 ? ' DR' : ''}`;
    closingEl.style.color = data.closing_balance < 0 ? 'var(--error)' : '';
    const closingCard = document.querySelector('.closing-balance');
    closingCard.classList.toggle('overdrawn', data.closing_balance < 0);
    closingCard.querySelector('i').style.color = data.closing_balance < 0 ? 'var(--error)' : 'var(--success)';

    const netChangeEl = document.getElementById('sumNetChange');
    const netChange = data.net_change;
    netChangeEl.textContent = `${netChange >= 0 ? '▲' : '▼'} ${symbol} ${formatMoney(Math.abs(netChange))} this period`;
    netChangeEl.style.color = netChange >= 0 ? 'var(--success)' : 'var(--error)';

    document.getElementById('txCountBadge').textContent =
        `(${data.transaction_count} total — ${data.deposit_count} deposits, ${data.withdrawal_count} withdrawals)`;

    document.getElementById('footDeposits').textContent = formatMoney(data.total_deposits);
    document.getElementById('footWithdrawals').textContent = formatMoney(data.total_withdrawals);
    document.getElementById('footClosing').textContent = formatMoney(data.closing_balance);

    const tbody = document.getElementById('statementTableBody');
    tbody.innerHTML = '';

    if (!data.transactions.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center" style="padding: 32px; color: var(--text-subtle);">
                    No transactions found for this period.
                </td>
            </tr>
        `;
        return;
    }

    let openingRow = '';
    if (data.from_date) {
        openingRow = `
            <tr style="background-color: var(--surface-1);">
                <td>${escHtml(formatDate(data.from_date))}</td>
                <td colspan="4"><em>Opening Balance</em></td>
                <td class="text-right running-balance${data.opening_balance < 0 ? ' negative' : ''}">${formatMoney(Math.abs(data.opening_balance))}${data.opening_balance < 0 ? ' DR' : ''}</td>
            </tr>
        `;
    }

    const rowsHtml = data.transactions.map(tx => `
        <tr>
            <td>${escHtml(formatDate(tx.date))}</td>
            <td>${escHtml(tx.description)}</td>
            <td>${escHtml(tx.voucher_number || '—')}</td>
            <td class="text-right ${tx.deposit > 0 ? 'positive' : ''}">${tx.deposit > 0 ? formatMoney(tx.deposit) : '—'}</td>
            <td class="text-right ${tx.withdrawal > 0 ? 'negative' : ''}">${tx.withdrawal > 0 ? formatMoney(tx.withdrawal) : '—'}</td>
            <td class="text-right running-balance${tx.balance < 0 ? ' negative' : ''}">${formatMoney(Math.abs(tx.balance))}${tx.balance < 0 ? ' DR' : ''}</td>
        </tr>
    `).join('');

    tbody.innerHTML = openingRow + rowsHtml;
}

function resetFilters() {
    document.getElementById('bankAccountFilter').value = '';
    document.getElementById('txTypeFilter').value = 'all';
    document.getElementById('searchFilter').value = '';
    setDefaultDates();
    document.getElementById('statementResult').style.display = 'none';
    document.getElementById('emptyState').style.display = 'block';
    document.getElementById('printBtn').disabled = true;
    currentStatement = null;
}

function printStatement() {
    if (!currentStatement) return;
    const bankAccountId = document.getElementById('bankAccountFilter').value;
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    const txType = document.getElementById('txTypeFilter').value;
    const search = document.getElementById('searchFilter').value.trim();

    const params = new URLSearchParams({
        bank_account_id: bankAccountId,
        tx_type: txType,
    });
    if (fromDate) params.set('from_date', fromDate);
    if (toDate) params.set('to_date', toDate);
    if (search) params.set('search', search);

    window.open(`print.php?${params.toString()}`, '_blank');
}

document.addEventListener('DOMContentLoaded', () => {
    setDefaultDates();
    loadBankAccounts();

    document.getElementById('generateBtn').addEventListener('click', generateStatement);
    document.getElementById('resetFiltersBtn').addEventListener('click', resetFilters);
    document.getElementById('printBtn').addEventListener('click', printStatement);
    document.getElementById('dateRangePreset').addEventListener('change', applyDateRangePreset);

    document.getElementById('searchFilter').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            generateStatement();
        }
    });
});
