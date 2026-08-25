// Data storage
let itemWiseData = [];
let billWiseData = [];
let currentPage = 1;
const itemsPerPage = 20;

// DOM Elements
const itemWiseToggle = document.getElementById('item-wise-toggle');
const billWiseToggle = document.getElementById('bill-wise-toggle');
const itemWiseReport = document.getElementById('item-wise-report');
const billWiseReport = document.getElementById('bill-wise-report');
const itemWiseDataContainer = document.getElementById('item-wise-data');
const billWiseDataContainer = document.getElementById('bill-wise-data');
const searchBtn = document.getElementById('search-btn');
const resetBtn = document.getElementById('reset-btn');
const exportBtn = document.getElementById('export-btn');
const printBtn = document.getElementById('print-btn');
const loading = document.getElementById('loading');
const reportCount = document.getElementById('report-count');
const paginationContainer = document.getElementById('pagination');
const totalSalesEl = document.getElementById('total-sales');
const totalItemsEl = document.getElementById('total-items');
const totalBillsEl = document.getElementById('total-bills');
const netAmountEl = document.getElementById('net-amount');
const referenceCard = document.getElementById('reference-card');
const referenceNumber = document.getElementById('reference-number');
const itemsSoldItem = document.getElementById('items-sold-item');
const billsGeneratedItem = document.getElementById('bills-generated-item');

// Number of columns in each table body - kept in sync with the <thead> in the page.
// item-wise: S# | Product | Packing | PCS Total | CTN | PCS | DZN | FOC | Rate | Discount | Trader Offer | Amount | R.QTY | R.Amt | Net Amount
const ITEM_WISE_COLS = 15;
// bill-wise: S# | Invoice No | Customer Code | Customer Name | Address | Net Amount
const BILL_WISE_COLS = 6;

const multiSelects = {
    invoiceTypes: {
        triggerId: 'invoice-type-trigger',
        optionsId: 'invoice-type-options',
        labelId: 'invoice-type-label',
        allLabel: 'All Types',
        searchPlaceholder: 'Search types...',
        selected: {}
    },
    salesOfficers: {
        triggerId: 'sales-officer-trigger',
        optionsId: 'sales-officer-options',
        labelId: 'sales-officer-label',
        allLabel: 'All Sales Officers',
        searchPlaceholder: 'Search officers...',
        selected: {}
    },
    supplierMen: {
        triggerId: 'supplier-man-trigger',
        optionsId: 'supplier-man-options',
        labelId: 'supplier-man-label',
        allLabel: 'All Supplier Men',
        searchPlaceholder: 'Search supplier men...',
        selected: {}
    },
    vendors: {
        triggerId: 'vendor-trigger',
        optionsId: 'vendor-options',
        labelId: 'vendor-label',
        allLabel: 'All Vendors',
        searchPlaceholder: 'Search vendors...',
        selected: {}
    },
    cities: {
        triggerId: 'city-filter-trigger',
        optionsId: 'city-filter-options',
        labelId: 'city-filter-label',
        allLabel: 'All Cities',
        searchPlaceholder: 'Search cities...',
        selected: {}
    },
    cityZones: {
        triggerId: 'city-zone-filter-trigger',
        optionsId: 'city-zone-filter-options',
        labelId: 'city-zone-filter-label',
        allLabel: 'All City Zones',
        searchPlaceholder: 'Search city zones...',
        selected: {}
    },
    areas: {
        triggerId: 'area-filter-trigger',
        optionsId: 'area-filter-options',
        labelId: 'area-filter-label',
        allLabel: 'All Areas',
        searchPlaceholder: 'Search areas...',
        selected: {}
    },
    companies: {
        triggerId: 'company-trigger',
        optionsId: 'company-options',
        labelId: 'company-label',
        allLabel: 'All Companies',
        searchPlaceholder: 'Search companies...',
        selected: {}
    }
};

const invoiceTypeOptions = [
    { value: 'Cash', label: 'Cash' },
    { value: 'Credit', label: 'Credit' },
    { value: 'OutStation', label: 'Out Station' }
];

function closeAllMultiSelects(exceptOptions) {
    document.querySelectorAll('.multi-select-options.open').forEach(function(panel) {
        if (panel !== exceptOptions) panel.classList.remove('open');
    });
    document.querySelectorAll('.multi-select-trigger.open').forEach(function(trigger) {
        if (!exceptOptions || trigger.nextElementSibling !== exceptOptions) {
            trigger.classList.remove('open');
        }
    });
}

function initMultiSelect(key, onChange) {
    const config = multiSelects[key];
    const trigger = document.getElementById(config.triggerId);
    const optionsEl = document.getElementById(config.optionsId);
    if (!trigger || !optionsEl || optionsEl.dataset.multiSelectReady === 'true') return;
    optionsEl.dataset.multiSelectReady = 'true';
    config.onChange = onChange;

    const searchWrap = document.createElement('div');
    searchWrap.className = 'so-search-wrap';

    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'so-search-input';
    searchInput.placeholder = config.searchPlaceholder;
    searchInput.autocomplete = 'off';
    searchWrap.appendChild(searchInput);
    optionsEl.appendChild(searchWrap);

    function filterOptions(value) {
        const filter = value.toLowerCase();
        optionsEl.querySelectorAll('.multi-select-option').forEach(function(option) {
            option.style.display = filter && option.textContent.toLowerCase().indexOf(filter) === -1 ? 'none' : '';
        });
    }

    searchInput.addEventListener('input', function(e) {
        e.stopPropagation();
        filterOptions(this.value);
    });

    searchInput.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        const wasOpen = optionsEl.classList.contains('open');
        closeAllMultiSelects(optionsEl);
        trigger.classList.toggle('open', !wasOpen);
        optionsEl.classList.toggle('open', !wasOpen);
        if (!wasOpen) {
            setTimeout(function() { searchInput.focus(); }, 10);
        } else {
            searchInput.value = '';
            filterOptions('');
        }
    });

    optionsEl.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}

function initMultiSelects() {
    initMultiSelect('invoiceTypes');
    initMultiSelect('salesOfficers');
    initMultiSelect('supplierMen');
    initMultiSelect('vendors');
    initMultiSelect('cities', function() {
        loadCityZones(getSelectedIds('cities'), false);
        loadAreas([], getSelectedIds('cities'), false);
    });
    initMultiSelect('cityZones', function() {
        loadAreas(getSelectedIds('cityZones'), getSelectedIds('cities'), false);
    });
    initMultiSelect('areas');
    initMultiSelect('companies');

    document.addEventListener('click', function() {
        closeAllMultiSelects();
        document.querySelectorAll('.so-search-input').forEach(function(input) {
            input.value = '';
        });
        document.querySelectorAll('.multi-select-option').forEach(function(option) {
            option.style.display = '';
        });
    });

    renderMultiSelectOptions('invoiceTypes', invoiceTypeOptions);
}

function renderMultiSelectOptions(key, options, preserveSelections = true) {
    const config = multiSelects[key];
    const optionsEl = document.getElementById(config.optionsId);
    if (!optionsEl) return;

    const previousSelection = preserveSelections ? config.selected : {};
    const validValues = {};
    config.selected = {};

    options.forEach(function(option) {
        validValues[String(option.value)] = option.label;
    });

    Object.keys(previousSelection).forEach(function(value) {
        if (Object.prototype.hasOwnProperty.call(validValues, value)) {
            config.selected[value] = validValues[value];
        }
    });

    optionsEl.querySelectorAll('.multi-select-option, .multi-select-empty').forEach(function(el) {
        el.remove();
    });

    if (options.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'multi-select-empty';
        empty.textContent = 'No options found';
        optionsEl.appendChild(empty);
        updateMultiSelectLabel(key);
        return;
    }

    options.forEach(function(option) {
        const value = String(option.value);
        const label = document.createElement('label');
        label.className = 'multi-select-option';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = value;
        checkbox.checked = Object.prototype.hasOwnProperty.call(config.selected, value);
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                config.selected[value] = option.label;
            } else {
                delete config.selected[value];
            }
            updateMultiSelectLabel(key);
            if (typeof config.onChange === 'function') {
                config.onChange(getSelectedIds(key), value, this.checked);
            }
        });

        label.appendChild(checkbox);
        label.appendChild(document.createTextNode(option.label));
        optionsEl.appendChild(label);
    });

    updateMultiSelectLabel(key);
}

function updateMultiSelectLabel(key) {
    const config = multiSelects[key];
    const label = document.getElementById(config.labelId);
    if (!label) return;

    const names = Object.values(config.selected);
    label.textContent = names.length === 0 ? config.allLabel : names.join(', ');
}

function getSelectedIds(key) {
    return Object.keys(multiSelects[key].selected);
}

function resetMultiSelect(key) {
    multiSelects[key].selected = {};
    const optionsEl = document.getElementById(multiSelects[key].optionsId);
    if (optionsEl) {
        optionsEl.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox) {
            checkbox.checked = false;
        });
        optionsEl.querySelectorAll('.multi-select-option').forEach(function(option) {
            option.style.display = '';
        });
        const searchInput = optionsEl.querySelector('.so-search-input');
        if (searchInput) searchInput.value = '';
    }
    updateMultiSelectLabel(key);
}

function appendArrayParams(params, name, values) {
    values.forEach(function(value) {
        params.append(name + '[]', value);
    });
}

function bindClick(element, handler) {
    if (element) {
        element.addEventListener('click', handler);
    }
}

async function loadCities() {
    try {
        const res = await fetch('../../../../server/api/sale/daily_sale_report/get-cities.php');
        const data = await res.json();
        if (data.success) {
            renderMultiSelectOptions('cities', data.cities.map(function(city) {
                return { value: city.id, label: city.city_name };
            }));
        }
    } catch (e) {
        console.error('loadCities:', e);
    }
}

async function loadCityZones(cityIds = [], preserveSelections = true) {
    try {
        const params = new URLSearchParams();
        cityIds.forEach(function(id) {
            params.append('city_ids[]', id);
        });
        const query = params.toString();
        const res = await fetch('../../../../server/api/sale/daily_sale_report/get-city-zones.php' + (query ? '?' + query : ''));
        const data = await res.json();
        if (data.success) {
            renderMultiSelectOptions('cityZones', data.city_zones.map(function(zone) {
                return { value: zone.id, label: zone.city_zone_name };
            }), preserveSelections);
        }
    } catch (e) {
        console.error('loadCityZones:', e);
    }
}

async function loadAreas(cityZoneIds = [], cityIds = [], preserveSelections = true) {
    try {
        const params = new URLSearchParams();
        cityZoneIds.forEach(function(id) {
            params.append('city_zone_ids[]', id);
        });
        cityIds.forEach(function(id) {
            params.append('city_ids[]', id);
        });
        const query = params.toString();
        const res = await fetch('../../../../server/api/sale/daily_sale_report/get-areas.php' + (query ? '?' + query : ''));
        const data = await res.json();
        if (data.success) {
            renderMultiSelectOptions('areas', data.areas.map(function(area) {
                return { value: area.id, label: area.area_name };
            }), preserveSelections);
        }
    } catch (e) {
        console.error('loadAreas:', e);
    }
}

async function loadCompanies() {
    try {
        const res = await fetch('../../../../server/api/sale/daily_sale_report/get-companies.php');
        const data = await res.json();
        if (data.success) {
            renderMultiSelectOptions('companies', data.companies.map(function(company) {
                return { value: company.id, label: company.company_name };
            }));
        }
    } catch (e) {
        console.error('loadCompanies:', e);
    }
}

async function loadProjects() {
    try {
        const res = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
        const data = await res.json();
        if (data.projects) {
            const select = document.getElementById('project');
            select.innerHTML = '<option value="">All Projects</option>';
            data.projects.forEach(function(project) {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = project.project_code + ' - ' + project.project_name + (project.city_name ? ' - ' + project.city_name : '');
                select.appendChild(option);
            });
        }
    } catch (e) {
        console.error('loadProjects:', e);
    }
}

async function loadFilters() {
    try {
        const res = await fetch('../../../../server/api/sale/daily_sale_report/get-filters.php');
        const data = await res.json();
        if (data.success) {
            renderMultiSelectOptions('salesOfficers', data.salesOfficers.map(function(officer) {
                return { value: officer.id, label: officer.full_name };
            }));
            renderMultiSelectOptions('supplierMen', data.supplierMen.map(function(man) {
                return { value: man.id, label: man.full_name };
            }));
            renderMultiSelectOptions('vendors', data.vendors.map(function(vendor) {
                return { value: vendor.id, label: vendor.supplier_name };
            }));
        }
    } catch (e) {
        console.error('loadFilters:', e);
    }
}

function init() {
    const requiredElements = [
        itemWiseToggle,
        billWiseToggle,
        itemWiseReport,
        billWiseReport,
        itemWiseDataContainer,
        billWiseDataContainer,
        loading,
        reportCount,
        paginationContainer,
        totalSalesEl,
        totalItemsEl,
        totalBillsEl,
        netAmountEl,
        referenceCard,
        referenceNumber,
        itemsSoldItem,
        billsGeneratedItem,
        document.getElementById('date-from'),
        document.getElementById('date-to')
    ];

    if (requiredElements.some(function(element) { return !element; })) {
        console.warn('Daily Sale Report script skipped because the DSR page markup is not available.');
        return;
    }

    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('date-from').valueAsDate = firstDay;
    document.getElementById('date-to').valueAsDate = today;

    initMultiSelects();

    bindClick(searchBtn, handleSearch);
    bindClick(resetBtn, handleReset);
    bindClick(exportBtn, handleExport);
    bindClick(printBtn, handlePrint);

    bindClick(itemWiseToggle, function() { switchReportType('item'); });
    bindClick(billWiseToggle, function() { switchReportType('bill'); });

    loadCompanies();
    loadProjects();
    loadFilters();
    loadCities();
    loadCityZones();
    loadAreas();
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
    const officerIds = getSelectedIds('salesOfficers');
    const vendorIds = getSelectedIds('vendors');
    const soStr = officerIds.length > 0 ? officerIds.join('-') : '0';
    const vendorStr = vendorIds.length > 0 ? vendorIds.join('-') : '0';
    const dateFrom = document.getElementById('date-from').value.replace(/-/g, '');
    const dateTo = document.getElementById('date-to').value.replace(/-/g, '');
    return 'DSR-' + reportType + '-' + soStr + '-' + vendorStr + '-' + dateFrom + '-' + dateTo;
}

function buildReportParams(includeReference) {
    const reportType = itemWiseToggle.classList.contains('active') ? 'item-wise' : 'bill-wise';
    const params = new URLSearchParams({ report_type: reportType });
    const dateFrom = document.getElementById('date-from').value;
    const dateTo = document.getElementById('date-to').value;
    const project = document.getElementById('project').value;

    if (includeReference) params.set('reference', generateReference());
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    if (project) params.set('project_id', project);

    appendArrayParams(params, 'invoice_types', getSelectedIds('invoiceTypes'));
    appendArrayParams(params, 'sales_officer_ids', getSelectedIds('salesOfficers'));
    appendArrayParams(params, 'supplier_man_ids', getSelectedIds('supplierMen'));
    appendArrayParams(params, 'vendor_ids', getSelectedIds('vendors'));
    appendArrayParams(params, 'company_ids', getSelectedIds('companies'));
    appendArrayParams(params, 'city_ids', getSelectedIds('cities'));
    appendArrayParams(params, 'city_zone_ids', getSelectedIds('cityZones'));
    appendArrayParams(params, 'area_ids', getSelectedIds('areas'));

    return params;
}

async function fetchReportData() {
    loading.classList.add('active');
    const reportType = itemWiseToggle.classList.contains('active') ? 'item-wise' : 'bill-wise';
    const params = buildReportParams(false);

    try {
        const res = await fetch('../../../../server/api/sale/daily_sale_report/daily-sale-report.php?' + params);
        const result = await res.json();
        if (result.success) {
            if (reportType === 'item-wise') {
                itemWiseData = result.data;
                loadItemWiseData();
            } else {
                billWiseData = result.data;
                loadBillWiseData();
            }
            updateSummary();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        console.error('fetchReportData:', e);
        alert('Failed to load report data');
    } finally {
        loading.classList.remove('active');
    }
}

function loadItemWiseData() {
    itemWiseDataContainer.innerHTML = '';
    if (itemWiseData.length === 0) {
        itemWiseDataContainer.innerHTML = '<tr><td colspan="' + ITEM_WISE_COLS + '" style="text-align:center;padding:40px">No data found</td></tr>';
        reportCount.textContent = '0 items found';
        paginationContainer.innerHTML = '';
        return;
    }

    const start = (currentPage - 1) * itemsPerPage;
    const paged = itemWiseData.slice(start, start + itemsPerPage);
    const groupByOfficer = getSelectedIds('salesOfficers').length > 1;

    if (groupByOfficer) {
        const groups = {};
        paged.forEach(function(item) {
            const key = item.sales_officer_name || 'Unknown';
            if (!groups[key]) groups[key] = [];
            groups[key].push(item);
        });
        let index = start;
        Object.entries(groups).forEach(function(entry) {
            const header = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = ITEM_WISE_COLS;
            cell.className = 'group-header-cell';
            cell.textContent = entry[0];
            header.appendChild(cell);
            itemWiseDataContainer.appendChild(header);
            entry[1].forEach(function(item) {
                index++;
                itemWiseDataContainer.appendChild(buildItemRow(item, index));
            });
        });
    } else {
        paged.forEach(function(item, index) {
            itemWiseDataContainer.appendChild(buildItemRow(item, start + index + 1));
        });
    }

    // Totals row - always computed from the full filtered dataset (itemWiseData),
    // not just the current page, so it stays correct while paginating.
    itemWiseDataContainer.appendChild(buildItemTotalsRow(itemWiseData));

    reportCount.textContent = itemWiseData.length + ' items found';
    renderPagination(itemWiseData.length);
}

function buildItemRow(item, num) {
    const row = document.createElement('tr');
    const indent = item.isChild ? 'padding-left:30px' : '';
    const totalPack = Math.round(parseFloat(item.total_pack || 0));
    const focQty = Math.round(parseFloat(item.foc_qty || 0));
    // Packing comes from the API as 'ctn_pack_size' (the Carton unit's effective
    // conversion factor for this product). It used to be read from a non-existent
    // 'pcs_per_ctn' field, which is why Packing always showed "-".
    const packing = (item.ctn_pack_size !== null && item.ctn_pack_size !== undefined && item.ctn_pack_size !== '')
        ? parseInt(item.ctn_pack_size, 10)
        : '-';

    const ctnQty = item.ctn_qty > 0 ? parseInt(item.ctn_qty, 10) : '-';
    const pcsQty = item.pcs_qty > 0 ? parseInt(item.pcs_qty, 10) : '-';
    const dznQty = item.dzn_qty > 0 ? parseInt(item.dzn_qty, 10) : '-';

    const discountAmt = parseFloat(item.discount_amount || 0);
    const tradeOfferAmt = parseFloat(item.trade_offer_amount || 0);
    const amount = parseFloat(item.amount || 0);

    const rQty = parseFloat(item.r_qty || 0);
    const rAmt = parseFloat(item.r_amt || 0);
    const rQtyDisplay = rQty ? rQty.toFixed(2) : '';
    const rAmtDisplay = rAmt ? (currencySymbol + rAmt.toFixed(2)) : '';
    const netAmount = amount - rAmt;

    row.innerHTML =
        '<td>' + num + '</td>' +
        '<td style="' + indent + '">' + (item.isChild ? '&rarr; ' : '') + item.product + '</td>' +
        '<td class="text-right">' + packing + '</td>' +
        '<td class="text-right">' + totalPack.toLocaleString() + '</td>' +
        '<td class="text-right">' + ctnQty + '</td>' +
        '<td class="text-right">' + pcsQty + '</td>' +
        '<td class="text-right">' + dznQty + '</td>' +
        '<td class="text-right">' + focQty.toLocaleString() + '</td>' +
        '<td class="text-right">' + currencySymbol + parseFloat(item.rate).toFixed(2) + '</td>' +
        '<td class="text-right">' + currencySymbol + discountAmt.toFixed(2) + '</td>' +
        '<td class="text-right">' + currencySymbol + tradeOfferAmt.toFixed(2) + '</td>' +
        '<td class="text-right">' + currencySymbol + amount.toLocaleString() + '</td>' +
        '<td class="text-right">' + rQtyDisplay + '</td>' +
        '<td class="text-right">' + rAmtDisplay + '</td>' +
        '<td class="text-right">' + currencySymbol + netAmount.toLocaleString() + '</td>';
    return row;
}

// Builds the "grand totals" row shown under the item-wise table. Totals are computed
// from the full filtered dataset (not just the visible page) and skip child rows,
// matching the same rule used for the summary cards.
function buildItemTotalsRow(data) {
    const rows = data.filter(function(item) { return !item.isChild; });
    const totals = rows.reduce(function(acc, item) {
        acc.totalPack += parseFloat(item.total_pack || 0);
        acc.ctnQty += parseFloat(item.ctn_qty || 0);
        acc.pcsQty += parseFloat(item.pcs_qty || 0);
        acc.dznQty += parseFloat(item.dzn_qty || 0);
        acc.focQty += parseFloat(item.foc_qty || 0);
        acc.discount += parseFloat(item.discount_amount || 0);
        acc.tradeOffer += parseFloat(item.trade_offer_amount || 0);
        acc.amount += parseFloat(item.amount || 0);
        acc.rQty += parseFloat(item.r_qty || 0);
        acc.rAmt += parseFloat(item.r_amt || 0);
        return acc;
    }, { totalPack: 0, ctnQty: 0, pcsQty: 0, dznQty: 0, focQty: 0, discount: 0, tradeOffer: 0, amount: 0, rQty: 0, rAmt: 0 });

    const netAmount = totals.amount - totals.rAmt;

    const row = document.createElement('tr');
    row.className = 'totals-row';
    row.style.background = '#f0f0f0';
    row.style.fontWeight = 'bold';
    row.innerHTML =
        '<td colspan="2">Total (' + rows.length + ' Items)</td>' +
        '<td class="text-right">-</td>' +
        '<td class="text-right">' + Math.round(totals.totalPack).toLocaleString() + '</td>' +
        '<td class="text-right">' + Math.round(totals.ctnQty).toLocaleString() + '</td>' +
        '<td class="text-right">' + Math.round(totals.pcsQty).toLocaleString() + '</td>' +
        '<td class="text-right">' + Math.round(totals.dznQty).toLocaleString() + '</td>' +
        '<td class="text-right">' + Math.round(totals.focQty).toLocaleString() + '</td>' +
        '<td class="text-right">-</td>' +
        '<td class="text-right">' + currencySymbol + totals.discount.toFixed(2) + '</td>' +
        '<td class="text-right">' + currencySymbol + totals.tradeOffer.toFixed(2) + '</td>' +
        '<td class="text-right">' + currencySymbol + totals.amount.toLocaleString() + '</td>' +
        '<td class="text-right">' + (totals.rQty ? totals.rQty.toFixed(2) : '') + '</td>' +
        '<td class="text-right">' + (totals.rAmt ? currencySymbol + totals.rAmt.toFixed(2) : '') + '</td>' +
        '<td class="text-right">' + currencySymbol + netAmount.toLocaleString() + '</td>';
    return row;
}

function loadBillWiseData() {
    billWiseDataContainer.innerHTML = '';
    if (billWiseData.length === 0) {
        billWiseDataContainer.innerHTML = '<tr><td colspan="' + BILL_WISE_COLS + '" style="text-align:center;padding:40px">No data found</td></tr>';
        reportCount.textContent = '0 bills found';
        paginationContainer.innerHTML = '';
        return;
    }

    const start = (currentPage - 1) * itemsPerPage;
    const paged = billWiseData.slice(start, start + itemsPerPage);
    const groupByOfficer = getSelectedIds('salesOfficers').length > 1;

    if (groupByOfficer) {
        const groups = {};
        paged.forEach(function(item) {
            const key = item.sales_officer_name || 'Unknown';
            if (!groups[key]) groups[key] = [];
            groups[key].push(item);
        });
        let index = start;
        Object.entries(groups).forEach(function(entry) {
            const header = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = BILL_WISE_COLS;
            cell.className = 'group-header-cell';
            cell.textContent = entry[0];
            header.appendChild(cell);
            billWiseDataContainer.appendChild(header);
            entry[1].forEach(function(item) {
                index++;
                billWiseDataContainer.appendChild(buildBillRow(item, index));
            });
        });
    } else {
        paged.forEach(function(item, index) {
            billWiseDataContainer.appendChild(buildBillRow(item, start + index + 1));
        });
    }

    // Totals row - computed from the full filtered dataset, not just the current page.
    billWiseDataContainer.appendChild(buildBillTotalsRow(billWiseData));

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

// Builds the "grand totals" row shown under the bill-wise table.
function buildBillTotalsRow(data) {
    const totalNet = data.reduce(function(sum, item) {
        return sum + parseFloat(item.netAmount || 0);
    }, 0);

    const row = document.createElement('tr');
    row.className = 'totals-row';
    row.style.background = '#f0f0f0';
    row.style.fontWeight = 'bold';
    row.innerHTML =
        '<td colspan="5">Total (' + data.length + ' Bills)</td>' +
        '<td class="text-right">' + currencySymbol + totalNet.toLocaleString() + '</td>';
    return row;
}

function updateSummary() {
    if (itemWiseToggle.classList.contains('active')) {
        const totalQty = itemWiseData.filter(function(item) {
            return !item.isChild;
        }).reduce(function(sum, item) {
            return sum + item.units.reduce(function(unitSum, unit) {
                return unitSum + parseInt(unit.qty, 10);
            }, 0);
        }, 0);
        const totalAmt = itemWiseData.filter(function(item) {
            return !item.isChild;
        }).reduce(function(sum, item) {
            return sum + parseFloat(item.amount || 0);
        }, 0);
        const totalRAmt = itemWiseData.filter(function(item) {
            return !item.isChild;
        }).reduce(function(sum, item) {
            return sum + parseFloat(item.r_amt || 0);
        }, 0);
        totalSalesEl.textContent = currencySymbol + totalAmt.toLocaleString();
        totalItemsEl.textContent = totalQty.toLocaleString();
        netAmountEl.textContent = currencySymbol + (totalAmt - totalRAmt).toLocaleString();
        itemsSoldItem.style.display = 'block';
        billsGeneratedItem.style.display = 'none';
    } else {
        const totalAmt = billWiseData.reduce(function(sum, item) {
            return sum + parseFloat(item.netAmount || 0);
        }, 0);
        totalSalesEl.textContent = currencySymbol + totalAmt.toLocaleString();
        totalItemsEl.textContent = '-';
        totalBillsEl.textContent = billWiseData.length;
        netAmountEl.textContent = currencySymbol + totalAmt.toLocaleString();
        itemsSoldItem.style.display = 'none';
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
    Object.keys(multiSelects).forEach(resetMultiSelect);
    closeAllMultiSelects();

    loadCityZones([], false);
    loadAreas([], [], false);

    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('date-from').valueAsDate = firstDay;
    document.getElementById('date-to').valueAsDate = today;
    document.getElementById('project').value = '';

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
    window.open('print.php?' + buildReportParams(true), '_blank');
}

function renderPagination(total) {
    const pages = Math.ceil(total / itemsPerPage);
    if (pages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }

    let html = '<button ' + (currentPage === 1 ? 'disabled' : '') + ' onclick="changePage(' + (currentPage - 1) + ')">&lsaquo;</button>';
    for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            html += '<button class="' + (i === currentPage ? 'active' : '') + '" onclick="changePage(' + i + ')">' + i + '</button>';
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            html += '<button disabled>...</button>';
        }
    }
    html += '<button ' + (currentPage === pages ? 'disabled' : '') + ' onclick="changePage(' + (currentPage + 1) + ')">&rsaquo;</button>';
    paginationContainer.innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    itemWiseToggle.classList.contains('active') ? loadItemWiseData() : loadBillWiseData();
}

document.addEventListener('DOMContentLoaded', init);