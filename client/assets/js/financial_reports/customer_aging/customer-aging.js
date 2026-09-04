// customer-aging.js
// Handles: filter bar (company, customer, country/region/city/zone/area) as
// searchable type-to-filter comboboxes, view mode (all invoices vs unbilled
// RV/SR), data loading, table + summary rendering, pagination, and exports.

const DATA_ENDPOINT = '../../../../server/api/financial_reports/customer_aging/customer-aging.php';
const FILTERS_ENDPOINT = '../../../../server/api/financial_reports/customer_aging/location_filters.php';

const PAGE_SIZE = 10;

const state = {
    page: 1,
    rawRows: [],       // raw API response rows
    allRows: [],       // displayed rows (raw or auto-cleared)
    autoClearMode: false,
    viewMode: 'invoices', // 'invoices' | 'unbilled'
    currencySymbol: '',
    filters: {
        company_id: '',
        customer_id: '',
        country_id: '',
        region_id: '',
        city_id: '',
        zone_id: '',
        area_id: ''
    }
};

let companyCombo, customerCombo, countryCombo, regionCombo, cityCombo, zoneCombo, areaCombo;

document.addEventListener('DOMContentLoaded', () => {
    initCombos();
    loadCompanies();
    loadCountries();
    setupButtons();
    setupAutoClearToggle();
    setupViewModeToggle();
    loadReportData();

    // Bucket cards: click to filter by that bucket
    document.querySelectorAll('.bucket-card').forEach((card, index) => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function () {
            const buckets = ['all', '15+', '25+', '45+'];
            if (index < buckets.length) {
                filterByBucket(buckets[index]);
            }
        });
    });
});

function setupAutoClearToggle() {
    const toggle = document.getElementById('autoClearToggle');
    const label  = document.getElementById('autoClearLabel');
    if (!toggle) return;
    toggle.addEventListener('change', function () {
        state.autoClearMode = this.checked;
        if (label) {
            label.style.opacity = state.autoClearMode ? '1' : '0.5';
            label.style.color   = state.autoClearMode ? 'var(--primary)' : '';
            label.style.fontWeight = state.autoClearMode ? '600' : '';
        }
        applyAndRender();
    });
    if (label) label.style.opacity = '0.5';
}

// ---------- View mode: All Invoices vs Unbilled RV/SR ----------

function setupViewModeToggle() {
    const toggle = document.getElementById('viewModeToggle');
    const label  = document.getElementById('viewModeLabel');
    if (!toggle) return;
    toggle.addEventListener('change', function () {
        state.viewMode = this.checked ? 'unbilled' : 'invoices';
        if (label) {
            label.style.opacity = this.checked ? '1' : '0.5';
            label.style.color   = this.checked ? 'var(--primary)' : '';
            label.style.fontWeight = this.checked ? '600' : '';
        }
        updateViewModeUI();
        state.page = 1;
        loadReportData();
    });
    if (label) label.style.opacity = '0.5';
    updateViewModeUI();
}

function updateViewModeUI() {
    const isUnbilled = state.viewMode === 'unbilled';

    const bucketsSection = document.getElementById('bucketsSummarySection');
    if (bucketsSection) bucketsSection.style.display = isUnbilled ? 'none' : '';

    const autoClearWrapper = document.getElementById('autoClearWrapper');
    if (autoClearWrapper) autoClearWrapper.style.display = isUnbilled ? 'none' : '';

    const refNoHeader = document.getElementById('colRefNo');
    if (refNoHeader) refNoHeader.textContent = isUnbilled ? 'Ref No' : 'Invoice No';

    const refDateHeader = document.getElementById('colRefDate');
    if (refDateHeader) refDateHeader.textContent = isUnbilled ? 'Date' : 'Invoice Date';

    const daysHeader = document.getElementById('colDaysOverdue');
    if (daysHeader) daysHeader.textContent = isUnbilled ? '-' : 'Days Overdue';

    const bucketHeader = document.getElementById('colBucket');
    if (bucketHeader) bucketHeader.textContent = isUnbilled ? '-' : 'Bucket';

    if (isUnbilled) {
        state.autoClearMode = false;
        const toggle = document.getElementById('autoClearToggle');
        if (toggle) toggle.checked = false;
    }
}

// ---------- Generic searchable combobox ----------
// Reused for Company / Customer / Country / Region / City / Zone / Area.
// `fetchItems(query)` must return a Promise resolving to [{id, label}].

function createSearchableCombo({ comboId, inputId, hiddenId, dropdownId, placeholder, fetchItems, onSelect }) {
    const input = document.getElementById(inputId);
    const hidden = document.getElementById(hiddenId);
    const dropdown = document.getElementById(dropdownId);
    let debounceTimer = null;

    async function openWithResults(query) {
        const items = await fetchItems(query);
        dropdown.innerHTML = '';

        const allOption = document.createElement('div');
        allOption.className = 'combo-option';
        allOption.textContent = placeholder;
        allOption.addEventListener('click', () => select('', ''));
        dropdown.appendChild(allOption);

        if (items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'combo-empty';
            empty.textContent = 'No matches';
            dropdown.appendChild(empty);
        } else {
            items.forEach(item => {
                const opt = document.createElement('div');
                opt.className = 'combo-option';
                opt.textContent = item.label;
                opt.addEventListener('click', () => select(item.id, item.label));
                dropdown.appendChild(opt);
            });
        }
        dropdown.classList.add('open');
    }

    function select(id, label) {
        hidden.value = id;
        input.value = label;
        input.dataset.selectedLabel = label;
        dropdown.classList.remove('open');
        onSelect(id);
    }

    function clear(disable) {
        select('', '');
        if (disable !== undefined) input.disabled = disable;
    }

    function enable() {
        input.disabled = false;
    }

    input.addEventListener('focus', () => {
        if (input.disabled) return;
        openWithResults(input.value.trim());
    });

    input.addEventListener('input', () => {
        if (input.disabled) return;
        clearTimeout(debounceTimer);
        const q = input.value.trim();
        if (hidden.value && q !== input.dataset.selectedLabel) {
            hidden.value = '';
            onSelect('');
        }
        debounceTimer = setTimeout(() => openWithResults(q), 200);
    });

    document.addEventListener('click', (e) => {
        if (!document.getElementById(comboId).contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    return { select, clear, enable };
}

// Backs a combo whose full option list is fetched once (or per cascade step)
// and then filtered client-side as the user types.
function makeListFetcher() {
    let rows = [];
    const fetcher = (query) => {
        const q = query.trim().toLowerCase();
        return q ? rows.filter(r => r.label.toLowerCase().includes(q)) : rows;
    };
    return { fetcher, setRows: (r) => { rows = r; } };
}

function initCombos() {
    const companyList = makeListFetcher();
    companyCombo = createSearchableCombo({
        comboId: 'companyCombo', inputId: 'companyFilterInput', hiddenId: 'companyFilter',
        dropdownId: 'companyDropdown', placeholder: 'All Companies',
        fetchItems: companyList.fetcher,
        onSelect: (id) => { state.filters.company_id = id; }
    });
    companyCombo._setRows = companyList.setRows;

    customerCombo = createSearchableCombo({
        comboId: 'customerCombo', inputId: 'customerFilterInput', hiddenId: 'customerFilter',
        dropdownId: 'customerDropdown', placeholder: 'All Customers',
        fetchItems: async (query) => {
            const rows = await fetchLookup('customers', query ? { q: query } : {});
            return rows.map(r => ({ id: r.id, label: `${r.customer_code} — ${r.customer_name}` }));
        },
        onSelect: (id) => { state.filters.customer_id = id; }
    });

    const countryList = makeListFetcher();
    countryCombo = createSearchableCombo({
        comboId: 'countryCombo', inputId: 'countryFilterInput', hiddenId: 'countryFilter',
        dropdownId: 'countryDropdown', placeholder: 'All Countries',
        fetchItems: countryList.fetcher,
        onSelect: (id) => {
            state.filters.country_id = id;
            state.filters.region_id = state.filters.city_id = state.filters.zone_id = state.filters.area_id = '';
            regionCombo.clear(true);
            cityCombo.clear(true);
            zoneCombo.clear(true);
            areaCombo.clear(true);
            loadRegions(id);
        }
    });
    countryCombo._setRows = countryList.setRows;

    const regionList = makeListFetcher();
    regionCombo = createSearchableCombo({
        comboId: 'regionCombo', inputId: 'regionFilterInput', hiddenId: 'regionFilter',
        dropdownId: 'regionDropdown', placeholder: 'All Regions',
        fetchItems: regionList.fetcher,
        onSelect: (id) => {
            state.filters.region_id = id;
            state.filters.city_id = state.filters.zone_id = state.filters.area_id = '';
            cityCombo.clear(true);
            zoneCombo.clear(true);
            areaCombo.clear(true);
            loadCities(id);
        }
    });
    regionCombo._setRows = regionList.setRows;

    const cityList = makeListFetcher();
    cityCombo = createSearchableCombo({
        comboId: 'cityCombo', inputId: 'cityFilterInput', hiddenId: 'cityFilter',
        dropdownId: 'cityDropdown', placeholder: 'All Cities',
        fetchItems: cityList.fetcher,
        onSelect: (id) => {
            state.filters.city_id = id;
            state.filters.zone_id = state.filters.area_id = '';
            zoneCombo.clear(true);
            areaCombo.clear(true);
            loadZones(id);
        }
    });
    cityCombo._setRows = cityList.setRows;

    const zoneList = makeListFetcher();
    zoneCombo = createSearchableCombo({
        comboId: 'zoneCombo', inputId: 'zoneFilterInput', hiddenId: 'zoneFilter',
        dropdownId: 'zoneDropdown', placeholder: 'All Zones',
        fetchItems: zoneList.fetcher,
        onSelect: (id) => {
            state.filters.zone_id = id;
            state.filters.area_id = '';
            areaCombo.clear(true);
            loadAreas(id);
        }
    });
    zoneCombo._setRows = zoneList.setRows;

    const areaList = makeListFetcher();
    areaCombo = createSearchableCombo({
        comboId: 'areaCombo', inputId: 'areaFilterInput', hiddenId: 'areaFilter',
        dropdownId: 'areaDropdown', placeholder: 'All Areas',
        fetchItems: areaList.fetcher,
        onSelect: (id) => { state.filters.area_id = id; }
    });
    areaCombo._setRows = areaList.setRows;
}

// ---------- Lookup loaders ----------

async function loadCompanies() {
    const rows = await fetchLookup('companies');
    const items = rows.map(r => ({ id: r.id, label: r.company_name }));
    companyCombo._setRows(items);
    if (items.length === 1) {
        companyCombo.select(items[0].id, items[0].label);
    }
}

async function loadCountries() {
    const rows = await fetchLookup('countries');
    countryCombo._setRows(rows.map(r => ({ id: r.id, label: r.country_name })));
}

async function loadRegions(countryId) {
    if (!countryId) { regionCombo.clear(true); return; }
    const rows = await fetchLookup('regions', { country_id: countryId });
    regionCombo._setRows(rows.map(r => ({ id: r.id, label: r.region_name })));
    regionCombo.enable();
}

async function loadCities(regionId) {
    if (!regionId) { cityCombo.clear(true); return; }
    const rows = await fetchLookup('cities', { region_id: regionId });
    cityCombo._setRows(rows.map(r => ({ id: r.id, label: r.city_name })));
    cityCombo.enable();
}

async function loadZones(cityId) {
    if (!cityId) { zoneCombo.clear(true); return; }
    const rows = await fetchLookup('zones', { city_id: cityId });
    zoneCombo._setRows(rows.map(r => ({ id: r.id, label: r.city_zone_name })));
    zoneCombo.enable();
}

async function loadAreas(zoneId) {
    if (!zoneId) { areaCombo.clear(true); return; }
    const rows = await fetchLookup('areas', { zone_id: zoneId });
    areaCombo._setRows(rows.map(r => ({ id: r.id, label: r.area_name })));
    areaCombo.enable();
}

// ---------- Buttons ----------

function setupButtons() {
    document.getElementById('applyFiltersBtn').addEventListener('click', () => {
        state.page = 1;
        loadReportData();
    });

    document.getElementById('resetFiltersBtn').addEventListener('click', () => {
        state.filters = {
            company_id: '', customer_id: '', country_id: '',
            region_id: '', city_id: '', zone_id: '', area_id: ''
        };
        companyCombo.clear();
        customerCombo.clear();
        countryCombo.clear();
        regionCombo.clear(true);
        cityCombo.clear(true);
        zoneCombo.clear(true);
        areaCombo.clear(true);
        state.page = 1;
        loadReportData();
    });
}

function filterByBucket(bucket) {
    // Placeholder hook for bucket-card clicks; extend as needed
    // e.g. could set a client-side filter on state.allRows before renderPage()
    console.log('Filter by bucket:', bucket);
}

// ---------- Data load + render ----------

async function loadReportData() {
    const params = new URLSearchParams();
    Object.entries(state.filters).forEach(([key, val]) => {
        if (val) params.append(key, val);
    });
    params.append('view_mode', state.viewMode);

    try {
        const res = await fetch(`${DATA_ENDPOINT}?${params.toString()}`);
        const json = await res.json();
        if (!json.success) {
            console.error(json.message);
            return;
        }
        state.rawRows = json.data;
        state.currencySymbol = json.currency_symbol || '';
        applyAndRender(json.summary);
    } catch (err) {
        console.error('Failed to load aging report:', err);
    }
}

function applyAndRender(rawSummary) {
    if (state.autoClearMode && state.viewMode !== 'unbilled') {
        state.allRows = simulateAutoClear(state.rawRows);
        renderSummary(buildSummaryFromRows(state.allRows));
    } else {
        state.allRows = state.rawRows;
        if (rawSummary) renderSummary(rawSummary);
        else renderSummary(buildSummaryFromRows(state.allRows));
    }
    state.page = 1;
    renderPage();
}

function simulateAutoClear(rows) {
    const customerMap = {};
    rows.forEach(row => {
        const key = row.customerName;
        if (!customerMap[key]) {
            customerMap[key] = { unallocated: (row.unallocatedReceived || 0) + (row.unallocatedTransfer || 0), bills: [] };
        }
        customerMap[key].bills.push(Object.assign({}, row));
    });

    const result = [];
    Object.values(customerMap).forEach(customer => {
        customer.bills.sort((a, b) => new Date(a.invoiceDate) - new Date(b.invoiceDate));
        let remaining = customer.unallocated;
        customer.bills.forEach(bill => {
            const balance = bill.outstandingRaw;
            const cleared = Math.min(remaining, balance);
            remaining -= cleared;
            const simBalance = balance - cleared;
            bill.simBalance  = simBalance;
            bill.simCleared  = cleared;
            bill.simStatus   = simBalance <= 0 ? 'cleared' : (cleared > 0 ? 'partial' : 'pending');
            if (simBalance > 0) {
                result.push(bill);
            }
        });
    });

    result.sort((a, b) => b.daysOverdue - a.daysOverdue);
    return result;
}

function buildSummaryFromRows(rows) {
    let total = 0, b15 = 0, b25 = 0, b45 = 0, c15 = 0, c25 = 0, c45 = 0;
    rows.forEach(row => {
        const amount = state.autoClearMode ? (row.simBalance ?? row.outstandingRaw) : row.outstandingRaw;
        const days   = row.daysOverdue;
        total += amount;
        if (days >= 45)      { b45 += amount; c45++; }
        else if (days >= 25) { b25 += amount; c25++; }
        else if (days >= 15) { b15 += amount; c15++; }
    });
    const sym = state.currencySymbol;
    const fmt = v => sym + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return {
        total_overdue: fmt(total),
        bucket_15: fmt(b15), count_15: c15,
        bucket_25: fmt(b25), count_25: c25,
        bucket_45: fmt(b45), count_45: c45,
    };
}

function renderSummary(summary) {
    document.getElementById('totalOverdueValue').textContent = summary.total_overdue;
    document.getElementById('bucket15Value').textContent = summary.bucket_15;
    document.getElementById('bucket15Count').textContent = summary.count_15;
    document.getElementById('bucket25Value').textContent = summary.bucket_25;
    document.getElementById('bucket25Count').textContent = summary.count_25;
    document.getElementById('bucket45Value').textContent = summary.bucket_45;
    document.getElementById('bucket45Count').textContent = summary.count_45;
}

function renderPage() {
    const total = state.allRows.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    state.page = Math.min(state.page, totalPages);

    const start = (state.page - 1) * PAGE_SIZE;
    const pageRows = state.allRows.slice(start, start + PAGE_SIZE);

    const tbody = document.getElementById('reportTableBody');
    tbody.innerHTML = '';

    if (total === 0) {
        const msg = state.viewMode === 'unbilled'
            ? 'No unbilled receive vouchers or sale returns found'
            : (state.autoClearMode
                ? 'No outstanding invoices after auto-clear simulation'
                : 'No overdue invoices found');
        tbody.innerHTML = `<tr><td colspan="10" style="text-align: center; padding: 20px;">${msg}</td></tr>`;
    } else {
        pageRows.forEach((row, i) => {
            const sym    = state.currencySymbol;
            const amount = state.autoClearMode
                ? sym + (row.simBalance ?? row.outstandingRaw).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
                : row.amount;
            const statusBadge = state.autoClearMode && row.simStatus
                ? `<span class="status-${row.simStatus}">${row.simStatus === 'partial' ? 'Partial' : 'Pending'}</span>`
                : '';
            const daysOverdue = (row.daysOverdue === null || row.daysOverdue === undefined) ? '-' : row.daysOverdue;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${start + i + 1}</td>
                <td>${escapeHtml(row.customerCode)}</td>
                <td>${escapeHtml(row.customerName)}</td>
                <td>${escapeHtml(row.address)}</td>
                <td>${escapeHtml(row.invoiceNo)}</td>
                <td>${escapeHtml(row.invoiceDate)}</td>
                <td>${escapeHtml(row.type)}</td>
                <td>${escapeHtml(amount)} ${statusBadge}</td>
                <td>${daysOverdue}</td>
                <td>${escapeHtml(row.bucket)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    const infoText = total === 0
        ? 'Showing 0 entries'
        : `Showing ${start + 1}-${Math.min(start + PAGE_SIZE, total)} of ${total} entries`;
    document.getElementById('paginationInfoTop').textContent = infoText;
    document.getElementById('paginationInfoBottom').textContent = `Page ${total === 0 ? 0 : state.page} of ${totalPages}`;

    renderPaginationControls(totalPages);
}

function renderPaginationControls(totalPages) {
    const controls = document.getElementById('paginationControls');
    controls.innerHTML = '';

    const prev = document.createElement('button');
    prev.className = 'btn btn-ghost';
    prev.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prev.disabled = state.page <= 1;
    prev.addEventListener('click', () => { state.page--; renderPage(); });
    controls.appendChild(prev);

    for (let p = 1; p <= totalPages; p++) {
        if (p === 1 || p === totalPages || (p >= state.page - 1 && p <= state.page + 1)) {
            const btn = document.createElement('button');
            btn.className = 'btn ' + (p === state.page ? 'btn-primary' : 'btn-ghost');
            btn.textContent = p;
            btn.addEventListener('click', () => { state.page = p; renderPage(); });
            controls.appendChild(btn);
        } else if (p === state.page - 2 || p === state.page + 2) {
            const dots = document.createElement('span');
            dots.textContent = '...';
            dots.style.padding = '0 8px';
            controls.appendChild(dots);
        }
    }

    const next = document.createElement('button');
    next.className = 'btn btn-ghost';
    next.innerHTML = '<i class="fas fa-chevron-right"></i>';
    next.disabled = state.page >= totalPages;
    next.addEventListener('click', () => { state.page++; renderPage(); });
    controls.appendChild(next);
}

// ---------- Exports ----------

function printReport() {
    const params = new URLSearchParams();
    Object.entries(state.filters).forEach(([key, val]) => {
        if (val) params.append(key, val);
    });
    params.append('view_mode', state.viewMode);
    if (state.autoClearMode) params.append('auto_clear', '1');
    window.open(`print.php?${params.toString()}`, '_blank');
}

function exportToJSON() {
    const blob = new Blob([JSON.stringify(state.allRows, null, 2)], { type: 'application/json' });
    downloadBlob(blob, 'customer-aging-report.json');
}

function exportToExcel() {
    const headers = ['S#', 'Customer Code', 'Customer Name', 'Address', 'Invoice No', 'Invoice Date', 'Type', 'Amount', 'Days Overdue', 'Bucket'];
    const rows = state.allRows.map((row, i) => [
        i + 1, row.customerCode, row.customerName, row.address,
        row.invoiceNo, row.invoiceDate, row.type, row.amount,
        (row.daysOverdue === null || row.daysOverdue === undefined) ? '-' : row.daysOverdue,
        row.bucket
    ]);
    const csv = [headers, ...rows]
        .map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(','))
        .join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    downloadBlob(blob, 'customer-aging-report.csv');
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

// ---------- Helpers ----------

async function fetchLookup(type, extraParams = {}) {
    const params = new URLSearchParams({ type, ...extraParams });
    const res = await fetch(`${FILTERS_ENDPOINT}?${params.toString()}`);
    const json = await res.json();
    return json.success ? json.data : [];
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}