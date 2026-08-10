// customer-aging.js
// Handles: filter bar (company, customer, country/region/city/zone/area),
// data loading, table + summary rendering, pagination, and exports.

const DATA_ENDPOINT = '../../../../server/api/financial_reports/customer_aging/customer-aging.php';
const FILTERS_ENDPOINT = '../../../../server/api/financial_reports/customer_aging/location_filters.php';

const PAGE_SIZE = 10;

const state = {
    page: 1,
    allRows: [],       // full filtered dataset returned by the API
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

document.addEventListener('DOMContentLoaded', () => {
    loadCompanies();
    loadCountries();
    setupCascadingFilters();
    setupCustomerCombo();
    setupButtons();
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

// ---------- Lookup loaders ----------

async function loadCompanies() {
    const rows = await fetchLookup('companies');
    const select = document.getElementById('companyFilter');
    resetSelect(select, 'All Companies');
    rows.forEach(r => select.appendChild(makeOption(r.id, r.company_name)));

    if (rows.length === 1) {
        select.value = rows[0].id;
        state.filters.company_id = rows[0].id;
    }
}

async function loadCountries() {
    const rows = await fetchLookup('countries');
    const select = document.getElementById('countryFilter');
    resetSelect(select, 'All Countries');
    rows.forEach(r => select.appendChild(makeOption(r.id, r.country_name)));
}

async function loadRegions(countryId) {
    const select = document.getElementById('regionFilter');
    resetSelect(select, 'All Regions');
    if (!countryId) {
        select.disabled = true;
        return;
    }
    const rows = await fetchLookup('regions', { country_id: countryId });
    rows.forEach(r => select.appendChild(makeOption(r.id, r.region_name)));
    select.disabled = false;
}

async function loadCities(regionId) {
    const select = document.getElementById('cityFilter');
    resetSelect(select, 'All Cities');
    if (!regionId) {
        select.disabled = true;
        return;
    }
    const rows = await fetchLookup('cities', { region_id: regionId });
    rows.forEach(r => select.appendChild(makeOption(r.id, r.city_name)));
    select.disabled = false;
}

async function loadZones(cityId) {
    const select = document.getElementById('zoneFilter');
    resetSelect(select, 'All Zones');
    if (!cityId) {
        select.disabled = true;
        return;
    }
    const rows = await fetchLookup('zones', { city_id: cityId });
    rows.forEach(r => select.appendChild(makeOption(r.id, r.city_zone_name)));
    select.disabled = false;
}

async function loadAreas(zoneId) {
    const select = document.getElementById('areaFilter');
    resetSelect(select, 'All Areas');
    if (!zoneId) {
        select.disabled = true;
        return;
    }
    const rows = await fetchLookup('areas', { zone_id: zoneId });
    rows.forEach(r => select.appendChild(makeOption(r.id, r.area_name)));
    select.disabled = false;
}

function setupCascadingFilters() {
    document.getElementById('countryFilter').addEventListener('change', async (e) => {
        state.filters.country_id = e.target.value;
        state.filters.region_id = state.filters.city_id = state.filters.zone_id = state.filters.area_id = '';
        await loadRegions(e.target.value);
        resetSelect(document.getElementById('cityFilter'), 'All Cities');
        document.getElementById('cityFilter').disabled = true;
        resetSelect(document.getElementById('zoneFilter'), 'All Zones');
        document.getElementById('zoneFilter').disabled = true;
        resetSelect(document.getElementById('areaFilter'), 'All Areas');
        document.getElementById('areaFilter').disabled = true;
    });

    document.getElementById('regionFilter').addEventListener('change', async (e) => {
        state.filters.region_id = e.target.value;
        state.filters.city_id = state.filters.zone_id = state.filters.area_id = '';
        await loadCities(e.target.value);
        resetSelect(document.getElementById('zoneFilter'), 'All Zones');
        document.getElementById('zoneFilter').disabled = true;
        resetSelect(document.getElementById('areaFilter'), 'All Areas');
        document.getElementById('areaFilter').disabled = true;
    });

    document.getElementById('cityFilter').addEventListener('change', async (e) => {
        state.filters.city_id = e.target.value;
        state.filters.zone_id = state.filters.area_id = '';
        await loadZones(e.target.value);
        resetSelect(document.getElementById('areaFilter'), 'All Areas');
        document.getElementById('areaFilter').disabled = true;
    });

    document.getElementById('zoneFilter').addEventListener('change', async (e) => {
        state.filters.zone_id = e.target.value;
        state.filters.area_id = '';
        await loadAreas(e.target.value);
    });

    document.getElementById('areaFilter').addEventListener('change', (e) => {
        state.filters.area_id = e.target.value;
    });

    document.getElementById('companyFilter').addEventListener('change', (e) => {
        state.filters.company_id = e.target.value;
    });
}

// ---------- Customer: dropdown + type-to-search combobox ----------

function setupCustomerCombo() {
    const input = document.getElementById('customerFilterInput');
    const hidden = document.getElementById('customerFilter');
    const dropdown = document.getElementById('customerDropdown');
    let debounceTimer = null;

    async function openWithResults(query) {
        const rows = await fetchLookup('customers', query ? { q: query } : {});
        dropdown.innerHTML = '';

        const allOption = document.createElement('div');
        allOption.className = 'combo-option';
        allOption.textContent = 'All Customers';
        allOption.addEventListener('click', () => selectCustomer('', ''));
        dropdown.appendChild(allOption);

        if (rows.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'combo-empty';
            empty.textContent = 'No matching customers';
            dropdown.appendChild(empty);
        } else {
            rows.forEach(r => {
                const opt = document.createElement('div');
                opt.className = 'combo-option';
                opt.textContent = `${r.customer_code} — ${r.customer_name}`;
                opt.addEventListener('click', () => selectCustomer(r.id, `${r.customer_code} — ${r.customer_name}`));
                dropdown.appendChild(opt);
            });
        }
        dropdown.classList.add('open');
    }

    function selectCustomer(id, label) {
        hidden.value = id;
        input.value = label;
        input.dataset.selectedLabel = label;
        state.filters.customer_id = id;
        dropdown.classList.remove('open');
    }

    input.addEventListener('focus', () => openWithResults(input.value.trim()));
    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const q = input.value.trim();
        if (hidden.value && q !== input.dataset.selectedLabel) {
            hidden.value = '';
            state.filters.customer_id = '';
        }
        debounceTimer = setTimeout(() => openWithResults(q), 250);
    });

    document.addEventListener('click', (e) => {
        if (!document.getElementById('customerCombo').contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });
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
        document.getElementById('companyFilter').value = '';
        document.getElementById('customerFilterInput').value = '';
        document.getElementById('customerFilter').value = '';
        document.getElementById('countryFilter').value = '';
        ['regionFilter', 'cityFilter', 'zoneFilter', 'areaFilter'].forEach(id => {
            const el = document.getElementById(id);
            resetSelect(el, `All ${id.replace('Filter', 's').replace(/^./, c => c.toUpperCase())}`);
            el.disabled = true;
        });
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

    try {
        const res = await fetch(`${DATA_ENDPOINT}?${params.toString()}`);
        const json = await res.json();
        if (!json.success) {
            console.error(json.message);
            return;
        }
        state.allRows = json.data;
        renderSummary(json.summary);
        renderPage();
    } catch (err) {
        console.error('Failed to load aging report:', err);
    }
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
        tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 20px;">No overdue invoices found</td></tr>';
    } else {
        pageRows.forEach((row, i) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${start + i + 1}</td>
                <td>${escapeHtml(row.customerCode)}</td>
                <td>${escapeHtml(row.customerName)}</td>
                <td>${escapeHtml(row.address)}</td>
                <td>${escapeHtml(row.invoiceNo)}</td>
                <td>${escapeHtml(row.invoiceDate)}</td>
                <td>${escapeHtml(row.type)}</td>
                <td>${escapeHtml(row.amount)}</td>
                <td>${row.daysOverdue}</td>
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
    window.print();
}

function exportToJSON() {
    const blob = new Blob([JSON.stringify(state.allRows, null, 2)], { type: 'application/json' });
    downloadBlob(blob, 'customer-aging-report.json');
}

function exportToExcel() {
    const headers = ['S#', 'Customer Code', 'Customer Name', 'Address', 'Invoice No', 'Invoice Date', 'Type', 'Amount', 'Days Overdue', 'Bucket'];
    const rows = state.allRows.map((row, i) => [
        i + 1, row.customerCode, row.customerName, row.address,
        row.invoiceNo, row.invoiceDate, row.type, row.amount, row.daysOverdue, row.bucket
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

function makeOption(value, label) {
    const opt = document.createElement('option');
    opt.value = value;
    opt.textContent = label;
    return opt;
}

function resetSelect(select, placeholderLabel) {
    select.innerHTML = '';
    select.appendChild(makeOption('', placeholderLabel));
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}