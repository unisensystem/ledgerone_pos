// Data storage
let itemWiseData = [];
let billWiseData = [];
let currentPage = 1;
const itemsPerPage = 20;

// DOM Elements
const itemWiseToggle    = document.getElementById('item-wise-toggle');
const billWiseToggle    = document.getElementById('bill-wise-toggle');
const itemWiseReport    = document.getElementById('item-wise-report');
const billWiseReport    = document.getElementById('bill-wise-report');
const itemWiseDataContainer = document.getElementById('item-wise-data');
const billWiseDataContainer = document.getElementById('bill-wise-data');
const searchBtn         = document.getElementById('search-btn');
const resetBtn          = document.getElementById('reset-btn');
const exportBtn         = document.getElementById('export-btn');
const printBtn          = document.getElementById('print-btn');
const loading           = document.getElementById('loading');
const reportCount       = document.getElementById('report-count');
const paginationContainer = document.getElementById('pagination');
const totalSalesEl      = document.getElementById('total-sales');
const totalItemsEl      = document.getElementById('total-items');
const totalBillsEl      = document.getElementById('total-bills');
const netAmountEl       = document.getElementById('net-amount');
const referenceCard     = document.getElementById('reference-card');
const referenceNumber   = document.getElementById('reference-number');
const itemsSoldItem     = document.getElementById('items-sold-item');
const billsGeneratedItem = document.getElementById('bills-generated-item');

// Selected sales officers { id -> name }
let selectedSalesOfficers = {};

// Searchable-select instances (returned by makeSearchable)
let ssInvoiceType, ssSupplierMan, ssVendor, ssCity, ssCityZone, ssArea, ssCompany;

/* ─────────────────────────────────────────────────────────────
   makeSearchable(selectId)
   Replaces a <select> with a readonly text trigger + a
   position:fixed dropdown panel so it is never clipped.
───────────────────────────────────────────────────────────── */
function makeSearchable(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return null;

    // --- Trigger: readonly text input that shows the chosen value ---
    const trigger = document.createElement('input');
    trigger.type = 'text';
    trigger.readOnly = true;
    trigger.className = 'ss-trigger form-input';
    trigger.autocomplete = 'off';

    // --- Panel: appended to <body> so it is never overflow-clipped ---
    const panel = document.createElement('div');
    panel.className = 'ss-panel';

    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'ss-search';
    searchInput.placeholder = 'Search...';
    searchInput.autocomplete = 'off';

    const list = document.createElement('div');
    list.className = 'ss-list';

    panel.appendChild(searchInput);
    panel.appendChild(list);
    document.body.appendChild(panel);   // lives on <body>

    // Insert trigger before the original select, then hide the select
    select.parentNode.insertBefore(trigger, select);
    select.style.display = 'none';

    let isOpen = false;

    function syncTrigger() {
        const idx = select.selectedIndex;
        const text = (idx >= 0 && select.options[idx]) ? select.options[idx].text : '';
        trigger.value = text;
        trigger.style.color = select.value === '' ? 'var(--subtext)' : 'var(--body)';
    }

    function buildList(filter) {
        list.innerHTML = '';
        const f = (filter || '').toLowerCase();
        let count = 0;
        Array.from(select.options).forEach(function(opt) {
            if (f && opt.text.toLowerCase().indexOf(f) === -1) return;
            const item = document.createElement('div');
            item.className = 'ss-item' + (opt.value === select.value ? ' ss-selected' : '');
            item.textContent = opt.text;
            item.addEventListener('mousedown', function(e) {
                e.preventDefault();
                select.value = opt.value;
                syncTrigger();
                closePanel();
                searchInput.value = '';
                select.dispatchEvent(new Event('change'));
            });
            list.appendChild(item);
            count++;
        });
        if (count === 0) {
            const empty = document.createElement('div');
            empty.className = 'ss-empty';
            empty.textContent = 'No results found';
            list.appendChild(empty);
        }
    }

    function positionPanel() {
        const rect = trigger.getBoundingClientRect();
        panel.style.top   = (rect.bottom + window.scrollY + 4) + 'px';
        panel.style.left  = (rect.left + window.scrollX) + 'px';
        panel.style.width = rect.width + 'px';
    }

    function openPanel() {
        // Close every other open panel first
        document.querySelectorAll('.ss-panel.ss-open').forEach(function(p) {
            if (p !== panel) p.classList.remove('ss-open');
        });
        positionPanel();
        buildList('');
        panel.classList.add('ss-open');
        isOpen = true;
        setTimeout(function() { searchInput.focus(); }, 10);
    }

    function closePanel() {
        panel.classList.remove('ss-open');
        isOpen = false;
    }

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        isOpen ? closePanel() : openPanel();
    });

    searchInput.addEventListener('input', function() {
        buildList(this.value);
    });

    searchInput.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    document.addEventListener('click', function() {
        if (isOpen) closePanel();
    });

    window.addEventListener('scroll', function() {
        if (isOpen) closePanel();
    }, true);

    window.addEventListener('resize', function() {
        if (isOpen) closePanel();
    });

    syncTrigger();

    return {
        refresh: function() {
            syncTrigger();
            if (isOpen) buildList(searchInput.value);
        },
        reset: function() {
            if (select.options.length > 0) select.value = select.options[0].value;
            searchInput.value = '';
            syncTrigger();
        }
    };
}

/* ─────────────────────────────────────────────────────────────
   Sales Officer: custom multi-select with search
───────────────────────────────────────────────────────────── */
function initSalesOfficerDropdown() {
    const trigger   = document.getElementById('sales-officer-trigger');
    const optionsEl = document.getElementById('sales-officer-options');
    if (!trigger || !optionsEl) return;

    // Inject sticky search input at the top of the options panel
    const searchWrap = document.createElement('div');
    searchWrap.className = 'so-search-wrap';

    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'so-search-input';
    searchInput.placeholder = 'Search officers...';
    searchInput.autocomplete = 'off';
    searchWrap.appendChild(searchInput);
    optionsEl.insertBefore(searchWrap, optionsEl.firstChild);

    function filterOfficers(val) {
        const f = val.toLowerCase();
        optionsEl.querySelectorAll('.multi-select-option').forEach(function(opt) {
            opt.style.display = (f && opt.textContent.toLowerCase().indexOf(f) === -1) ? 'none' : '';
        });
    }

    searchInput.addEventListener('input', function(e) {
        e.stopPropagation();
        filterOfficers(this.value);
    });

    searchInput.addEventListener('click', function(e) { e.stopPropagation(); });

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        const wasOpen = optionsEl.classList.contains('open');
        trigger.classList.toggle('open');
        optionsEl.classList.toggle('open');
        if (!wasOpen) {
            setTimeout(function() { searchInput.focus(); }, 10);
        } else {
            searchInput.value = '';
            filterOfficers('');
        }
    });

    document.addEventListener('click', function() {
        if (optionsEl.classList.contains('open')) {
            trigger.classList.remove('open');
            optionsEl.classList.remove('open');
            searchInput.value = '';
            filterOfficers('');
        }
    });
}

function updateSalesOfficerLabel() {
    const label = document.getElementById('sales-officer-label');
    if (!label) return;
    const names = Object.values(selectedSalesOfficers);
    label.textContent = names.length === 0 ? 'All Sales Officers' : names.join(', ');
}

function getSelectedOfficerIds() {
    return Object.keys(selectedSalesOfficers);
}

/* ─────────────────────────────────────────────────────────────
   Data loaders
───────────────────────────────────────────────────────────── */
async function loadCities() {
    try {
        const res = await fetch('../../../../server/api/sale/daily_sale_report/get-cities.php');
        const data = await res.json();
        if (data.success) {
            const sel = document.getElementById('city-filter');
            data.cities.forEach(function(city) {
                const opt = document.createElement('option');
                opt.value = city.id;
                opt.textContent = city.city_name;
                sel.appendChild(opt);
            });
            ssCity && ssCity.refresh();
        }
    } catch(e) { console.error('loadCities:', e); }
}

async function loadCityZones(cityId) {
    const sel = document.getElementById('city-zone-filter');
    const areaSel = document.getElementById('area-filter');
    sel.innerHTML = '<option value="">All City Zones</option>';
    areaSel.innerHTML = '<option value="">All Areas</option>';
    ssCityZone && ssCityZone.refresh();
    ssArea && ssArea.refresh();
    if (!cityId) return;
    try {
        const res = await fetch('../../../../server/api/sale/daily_sale_report/get-city-zones.php?city_id=' + cityId);
        const data = await res.json();
        if (data.success) {
            data.city_zones.forEach(function(zone) {
                const opt = document.createElement('option');
                opt.value = zone.id;
                opt.textContent = zone.city_zone_name;
                sel.appendChild(opt);
            });
            ssCityZone && ssCityZone.refresh();
        }
    } catch(e) { console.error('loadCityZones:', e); }
}

async function loadAreas(cityZoneId) {
    const sel = document.getElementById('area-filter');
    const prev = sel.value;
    sel.innerHTML = '<option value="">All Areas</option>';
    try {
        const url = cityZoneId
            ? '../../../../server/api/sale/daily_sale_report/get-areas.php?city_zone_id=' + cityZoneId
            : '../../../../server/api/sale/daily_sale_report/get-areas.php';
        const res = await fetch(url);
        const data = await res.json();
        if (data.success) {
            data.areas.forEach(function(area) {
                const opt = document.createElement('option');
                opt.value = area.id;
                opt.textContent = area.area_name;
                sel.appendChild(opt);
            });
            if (prev) sel.value = prev;
            ssArea && ssArea.refresh();
        }
    } catch(e) { console.error('loadAreas:', e); }
}

async function loadAllAreas() { await loadAreas(null); }

async function loadCompanies() {
    try {
        const res = await fetch('../../../../server/api/sale/daily_sale_report/get-companies.php');
        const data = await res.json();
        if (data.success) {
            const sel = document.getElementById('company');
            data.companies.forEach(function(c) {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.company_name;
                sel.appendChild(opt);
            });
            ssCompany && ssCompany.refresh();
        }
    } catch(e) { console.error('loadCompanies:', e); }
}

async function loadFilters() {
    try {
        const res = await fetch('../../../../server/api/sale/daily_sale_report/get-filters.php');
        const data = await res.json();
        if (data.success) {
            // Sales Officers (preserve the search wrap at top)
            const optionsEl = document.getElementById('sales-officer-options');
            optionsEl.querySelectorAll('.multi-select-option').forEach(function(el) { el.remove(); });
            data.salesOfficers.forEach(function(officer) {
                const label = document.createElement('label');
                label.className = 'multi-select-option';
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.value = officer.id;
                cb.addEventListener('change', function() {
                    if (this.checked) selectedSalesOfficers[officer.id] = officer.full_name;
                    else delete selectedSalesOfficers[officer.id];
                    updateSalesOfficerLabel();
                });
                label.appendChild(cb);
                label.appendChild(document.createTextNode(officer.full_name));
                optionsEl.appendChild(label);
            });

            // Supplier Men
            const supSel = document.getElementById('supplier-man');
            supSel.innerHTML = '<option value="">All Supplier Men</option>';
            data.supplierMen.forEach(function(m) {
                const opt = document.createElement('option');
                opt.value = m.id; opt.textContent = m.full_name;
                supSel.appendChild(opt);
            });
            ssSupplierMan && ssSupplierMan.refresh();

            // Vendors
            const venSel = document.getElementById('vendor');
            venSel.innerHTML = '<option value="">All Vendors</option>';
            data.vendors.forEach(function(v) {
                const opt = document.createElement('option');
                opt.value = v.id; opt.textContent = v.supplier_name;
                venSel.appendChild(opt);
            });
            ssVendor && ssVendor.refresh();
        }
    } catch(e) { console.error('loadFilters:', e); }
}

/* ─────────────────────────────────────────────────────────────
   Area bottom-up hierarchy
───────────────────────────────────────────────────────────── */
async function handleAreaHierarchy(areaId) {
    try {
        const res = await fetch('../../../../server/api/sale/daily_sale_report/get-area-hierarchy.php?area_id=' + areaId);
        const data = await res.json();
        if (data.success && data.hierarchy) {
            const h = data.hierarchy;
            document.getElementById('city-filter').value = h.city_id;
            ssCity && ssCity.refresh();

            await loadCityZones(h.city_id);
            document.getElementById('city-zone-filter').value = h.city_zone_id;
            ssCityZone && ssCityZone.refresh();

            await loadAreas(h.city_zone_id);
            document.getElementById('area-filter').value = areaId;
            ssArea && ssArea.refresh();
        }
    } catch(e) { console.error('handleAreaHierarchy:', e); }
}

/* ─────────────────────────────────────────────────────────────
   Init
───────────────────────────────────────────────────────────── */
function init() {
    // Default dates
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('date-from').valueAsDate = firstDay;
    document.getElementById('date-to').valueAsDate   = today;

    // Convert every <select> into a searchable dropdown
    ssInvoiceType  = makeSearchable('invoice-type');
    ssSupplierMan  = makeSearchable('supplier-man');
    ssVendor       = makeSearchable('vendor');
    ssCity         = makeSearchable('city-filter');
    ssCityZone     = makeSearchable('city-zone-filter');
    ssArea         = makeSearchable('area-filter');
    ssCompany      = makeSearchable('company');

    // Sales Officer search
    initSalesOfficerDropdown();

    // Area cascade: city change → load zones
    document.getElementById('city-filter').addEventListener('change', function() {
        loadCityZones(this.value);
    });
    // Zone change → load areas
    document.getElementById('city-zone-filter').addEventListener('change', function() {
        loadAreas(this.value);
    });
    // Area change → back-fill city & zone
    document.getElementById('area-filter').addEventListener('change', function() {
        if (this.value) handleAreaHierarchy(this.value);
    });

    // Button listeners
    searchBtn.addEventListener('click', handleSearch);
    resetBtn.addEventListener('click', handleReset);
    exportBtn.addEventListener('click', handleExport);
    printBtn.addEventListener('click', handlePrint);

    // Report type toggle
    itemWiseToggle.addEventListener('click', function() { switchReportType('item'); });
    billWiseToggle.addEventListener('click', function() { switchReportType('bill'); });

    // Load data
    loadCompanies();
    loadFilters();
    loadCities();
    loadAllAreas();
    fetchReportData();
}

function switchReportType(type) {
    if (type === 'item') {
        itemWiseToggle.classList.add('active');
        billWiseToggle.classList.remove('active');
        itemWiseReport.style.display = 'block';
        billWiseReport.style.display = 'none';
    } else {
        itemWiseToggle.classList.remove('active');
        billWiseToggle.classList.add('active');
        itemWiseReport.style.display = 'none';
        billWiseReport.style.display = 'block';
    }
    currentPage = 1;
    fetchReportData();
}

function generateReference() {
    const reportType = itemWiseToggle.classList.contains('active') ? 'I' : 'B';
    const officerIds = getSelectedOfficerIds();
    const soStr = officerIds.length > 0 ? officerIds.join('-') : '0';
    const vendor = document.getElementById('vendor').value || '0';
    const dateFrom = document.getElementById('date-from').value.replace(/-/g,'');
    const dateTo   = document.getElementById('date-to').value.replace(/-/g,'');
    return 'DSR-' + reportType + '-' + soStr + '-' + vendor + '-' + dateFrom + '-' + dateTo;
}

async function fetchReportData() {
    loading.classList.add('active');

    const reportType  = itemWiseToggle.classList.contains('active') ? 'item-wise' : 'bill-wise';
    const invoiceType = document.getElementById('invoice-type').value;
    const officerIds  = getSelectedOfficerIds();
    const supplierMan = document.getElementById('supplier-man').value;
    const vendor      = document.getElementById('vendor').value;
    const company     = document.getElementById('company').value;
    const dateFrom    = document.getElementById('date-from').value;
    const dateTo      = document.getElementById('date-to').value;
    const cityId      = document.getElementById('city-filter').value;
    const cityZoneId  = document.getElementById('city-zone-filter').value;
    const areaId      = document.getElementById('area-filter').value;

    const params = new URLSearchParams({ report_type: reportType });
    if (invoiceType) params.set('invoice_type', invoiceType);
    if (supplierMan) params.set('supplier_man_id', supplierMan);
    if (vendor)      params.set('vendor_id', vendor);
    if (company)     params.set('company_id', company);
    if (dateFrom)    params.set('date_from', dateFrom);
    if (dateTo)      params.set('date_to', dateTo);
    if (cityId)      params.set('city_id', cityId);
    if (cityZoneId)  params.set('city_zone_id', cityZoneId);
    if (areaId)      params.set('area_id', areaId);
    officerIds.forEach(function(id) { params.append('sales_officer_ids[]', id); });

    try {
        const res = await fetch('../../../../server/api/sale/daily_sale_report/daily-sale-report.php?' + params);
        const result = await res.json();
        if (result.success) {
            if (reportType === 'item-wise') { itemWiseData = result.data; loadItemWiseData(); }
            else                            { billWiseData = result.data; loadBillWiseData(); }
            updateSummary();
        } else {
            alert('Error: ' + result.message);
        }
    } catch(e) {
        console.error('fetchReportData:', e);
        alert('Failed to load report data');
    } finally {
        loading.classList.remove('active');
    }
}

function loadItemWiseData() {
    itemWiseDataContainer.innerHTML = '';
    if (itemWiseData.length === 0) {
        itemWiseDataContainer.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px">No data found</td></tr>';
        reportCount.textContent = '0 items found';
        paginationContainer.innerHTML = '';
        return;
    }
    const start = (currentPage - 1) * itemsPerPage;
    const paged = itemWiseData.slice(start, start + itemsPerPage);
    const groupByOfficer = getSelectedOfficerIds().length > 1;

    if (groupByOfficer) {
        const groups = {};
        paged.forEach(function(item) {
            const k = item.sales_officer_name || 'Unknown';
            if (!groups[k]) groups[k] = [];
            groups[k].push(item);
        });
        let idx = start;
        Object.entries(groups).forEach(function(entry) {
            const hdr = document.createElement('tr');
            hdr.innerHTML = '<td colspan="6" style="background:var(--surface-2);font-weight:600;font-size:13px;padding:8px 16px;color:var(--primary)">' + entry[0] + '</td>';
            itemWiseDataContainer.appendChild(hdr);
            entry[1].forEach(function(item) {
                idx++;
                itemWiseDataContainer.appendChild(buildItemRow(item, idx));
            });
        });
    } else {
        paged.forEach(function(item, i) {
            itemWiseDataContainer.appendChild(buildItemRow(item, start + i + 1));
        });
    }
    reportCount.textContent = itemWiseData.length + ' items found';
    renderPagination(itemWiseData.length);
}

function buildItemRow(item, num) {
    const row = document.createElement('tr');
    const indent = item.isChild ? 'padding-left:30px' : '';
    const qty = item.units.map(function(u) { return u.unit + ' ' + parseInt(u.qty); }).join(', ');
    row.innerHTML =
        '<td>' + num + '</td>' +
        '<td style="' + indent + '">' + (item.isChild ? '↳ ' : '') + item.product + '</td>' +
        '<td class="text-right">' + qty + '</td>' +
        '<td class="text-right">' + parseFloat(item.foc_qty || 0).toLocaleString() + '</td>' +
        '<td class="text-right">' + currencySymbol + parseFloat(item.rate).toFixed(2) + '</td>' +
        '<td class="text-right">' + currencySymbol + parseFloat(item.amount).toLocaleString() + '</td>';
    return row;
}

function loadBillWiseData() {
    billWiseDataContainer.innerHTML = '';
    if (billWiseData.length === 0) {
        billWiseDataContainer.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px">No data found</td></tr>';
        reportCount.textContent = '0 bills found';
        paginationContainer.innerHTML = '';
        return;
    }
    const start = (currentPage - 1) * itemsPerPage;
    const paged = billWiseData.slice(start, start + itemsPerPage);
    const groupByOfficer = getSelectedOfficerIds().length > 1;

    if (groupByOfficer) {
        const groups = {};
        paged.forEach(function(item) {
            const k = item.sales_officer_name || 'Unknown';
            if (!groups[k]) groups[k] = [];
            groups[k].push(item);
        });
        let idx = start;
        Object.entries(groups).forEach(function(entry) {
            const hdr = document.createElement('tr');
            hdr.innerHTML = '<td colspan="6" style="background:var(--surface-2);font-weight:600;font-size:13px;padding:8px 16px;color:var(--primary)">' + entry[0] + '</td>';
            billWiseDataContainer.appendChild(hdr);
            entry[1].forEach(function(item) {
                idx++;
                billWiseDataContainer.appendChild(buildBillRow(item, idx));
            });
        });
    } else {
        paged.forEach(function(item, i) {
            billWiseDataContainer.appendChild(buildBillRow(item, start + i + 1));
        });
    }
    reportCount.textContent = billWiseData.length + ' bills found';
    renderPagination(billWiseData.length);
}

function buildBillRow(item, num) {
    const row = document.createElement('tr');
    row.innerHTML =
        '<td>' + num + '</td>' +
        '<td><strong>' + item.invoiceNo + '</strong></td>' +
        '<td>' + item.custCode + '</td>' +
        '<td>' + item.custName + '</td>' +
        '<td>' + (item.address || '-') + '</td>' +
        '<td class="text-right"><strong>' + currencySymbol + parseFloat(item.netAmount).toLocaleString() + '</strong></td>';
    return row;
}

function updateSummary() {
    if (itemWiseToggle.classList.contains('active')) {
        const totalQty = itemWiseData.filter(function(i) { return !i.isChild; }).reduce(function(s, i) {
            return s + i.units.reduce(function(us, u) { return us + parseInt(u.qty); }, 0);
        }, 0);
        const totalAmt = itemWiseData.filter(function(i) { return !i.isChild; }).reduce(function(s, i) {
            return s + parseFloat(i.amount || 0);
        }, 0);
        totalSalesEl.textContent = currencySymbol + totalAmt.toLocaleString();
        totalItemsEl.textContent = totalQty.toLocaleString();
        netAmountEl.textContent  = currencySymbol + totalAmt.toLocaleString();
        itemsSoldItem.style.display    = 'block';
        billsGeneratedItem.style.display = 'none';
    } else {
        const totalAmt = billWiseData.reduce(function(s, i) { return s + parseFloat(i.netAmount || 0); }, 0);
        totalSalesEl.textContent = currencySymbol + totalAmt.toLocaleString();
        totalItemsEl.textContent = '-';
        totalBillsEl.textContent = billWiseData.length;
        netAmountEl.textContent  = currencySymbol + totalAmt.toLocaleString();
        itemsSoldItem.style.display    = 'none';
        billsGeneratedItem.style.display = 'block';
    }
}

function handleSearch() {
    currentPage = 1;
    referenceNumber.textContent = generateReference();
    referenceCard.style.display = 'flex';
    fetchReportData();
}

function handleReset() {
    ssInvoiceType && ssInvoiceType.reset();
    ssSupplierMan && ssSupplierMan.reset();
    ssVendor      && ssVendor.reset();
    ssCompany     && ssCompany.reset();

    // Reset city — then clear zones/areas
    document.getElementById('city-filter').value = '';
    ssCity && ssCity.reset();
    document.getElementById('city-zone-filter').innerHTML = '<option value="">All City Zones</option>';
    ssCityZone && ssCityZone.reset();
    document.getElementById('area-filter').innerHTML = '<option value="">All Areas</option>';
    loadAllAreas();

    // Reset Sales Officer
    selectedSalesOfficers = {};
    document.querySelectorAll('#sales-officer-options input[type="checkbox"]').forEach(function(cb) { cb.checked = false; });
    const soSearch = document.querySelector('.so-search-input');
    if (soSearch) {
        soSearch.value = '';
        document.querySelectorAll('#sales-officer-options .multi-select-option').forEach(function(opt) { opt.style.display = ''; });
    }
    updateSalesOfficerLabel();

    // Reset dates
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('date-from').valueAsDate = firstDay;
    document.getElementById('date-to').valueAsDate   = today;

    referenceCard.style.display = 'none';
    referenceNumber.textContent = '-';

    currentPage = 1;
    fetchReportData();
}

function handleExport() {
    const type = itemWiseToggle.classList.contains('active') ? 'item-wise' : 'bill-wise';
    alert(type + ' report exported as CSV successfully!');
}

function handlePrint() {
    const reportType  = itemWiseToggle.classList.contains('active') ? 'item-wise' : 'bill-wise';
    const invoiceType = document.getElementById('invoice-type').value;
    const officerIds  = getSelectedOfficerIds();
    const supplierMan = document.getElementById('supplier-man').value;
    const vendor      = document.getElementById('vendor').value;
    const company     = document.getElementById('company').value;
    const dateFrom    = document.getElementById('date-from').value;
    const dateTo      = document.getElementById('date-to').value;
    const cityId      = document.getElementById('city-filter').value;
    const cityZoneId  = document.getElementById('city-zone-filter').value;
    const areaId      = document.getElementById('area-filter').value;

    const params = new URLSearchParams({ report_type: reportType, reference: generateReference() });
    if (invoiceType) params.set('invoice_type', invoiceType);
    if (supplierMan) params.set('supplier_man_id', supplierMan);
    if (vendor)      params.set('vendor_id', vendor);
    if (company)     params.set('company_id', company);
    if (dateFrom)    params.set('date_from', dateFrom);
    if (dateTo)      params.set('date_to', dateTo);
    if (cityId)      params.set('city_id', cityId);
    if (cityZoneId)  params.set('city_zone_id', cityZoneId);
    if (areaId)      params.set('area_id', areaId);
    officerIds.forEach(function(id) { params.append('sales_officer_ids[]', id); });

    window.open('print.php?' + params, '_blank');
}

function renderPagination(total) {
    const pages = Math.ceil(total / itemsPerPage);
    if (pages <= 1) { paginationContainer.innerHTML = ''; return; }
    let html = '<button ' + (currentPage === 1 ? 'disabled' : '') + ' onclick="changePage(' + (currentPage-1) + ')">&lsaquo;</button>';
    for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || (i >= currentPage-1 && i <= currentPage+1)) {
            html += '<button class="' + (i === currentPage ? 'active' : '') + '" onclick="changePage(' + i + ')">' + i + '</button>';
        } else if (i === currentPage-2 || i === currentPage+2) {
            html += '<button disabled>...</button>';
        }
    }
    html += '<button ' + (currentPage === pages ? 'disabled' : '') + ' onclick="changePage(' + (currentPage+1) + ')">&rsaquo;</button>';
    paginationContainer.innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    itemWiseToggle.classList.contains('active') ? loadItemWiseData() : loadBillWiseData();
}

document.addEventListener('DOMContentLoaded', init);
