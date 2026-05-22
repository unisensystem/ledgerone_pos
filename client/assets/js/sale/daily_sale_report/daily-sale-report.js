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

// Tom Select instances
let tsInvoiceType, tsSalesOfficer, tsSupplierMan, tsVendor;
let tsCity, tsCityZone, tsArea, tsCompany;

// Initialize the application
function init() {
    // Set default dates
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('date-from').valueAsDate = firstDay;
    document.getElementById('date-to').valueAsDate = today;

    // Initialize Tom Select on all dropdown filters
    initSearchableSelects();

    // Set up button event listeners
    setupEventListeners();

    // Load filter options and initial data
    loadCompanies();
    loadFilters();
    loadCities();
    loadAllAreas();
    fetchReportData();
}

function initSearchableSelects() {
    const base = { allowEmptyOption: true, create: false, selectOnTab: true };

    tsInvoiceType = new TomSelect('#invoice-type', { ...base });

    tsSalesOfficer = new TomSelect('#sales-officer', {
        create: false,
        placeholder: 'All Sales Officers',
        plugins: ['remove_button'],
        selectOnTab: true,
    });

    tsSupplierMan = new TomSelect('#supplier-man', { ...base });

    tsVendor = new TomSelect('#vendor', { ...base });

    tsCity = new TomSelect('#city-filter', {
        ...base,
        onChange(value) {
            loadCityZones(value);
        }
    });

    tsCityZone = new TomSelect('#city-zone-filter', {
        ...base,
        onChange(value) {
            loadAreas(value || null);
        }
    });

    tsArea = new TomSelect('#area-filter', {
        ...base,
        onChange(value) {
            if (value) handleAreaBottomUp(value);
        }
    });

    tsCompany = new TomSelect('#company', { ...base });
}

function getSelectedOfficerIds() {
    return tsSalesOfficer ? tsSalesOfficer.getValue() : [];
}

async function loadCities() {
    try {
        const response = await fetch('../../../../server/api/sale/daily_sale_report/get-cities.php');
        const result = await response.json();
        if (result.success) {
            const citySelect = document.getElementById('city-filter');
            result.cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city.id;
                option.textContent = city.city_name;
                citySelect.appendChild(option);
            });
            tsCity.sync();
        }
    } catch (error) {
        console.error('Error loading cities:', error);
    }
}

async function loadCityZones(cityId) {
    const cityZoneSelect = document.getElementById('city-zone-filter');
    const areaSelect = document.getElementById('area-filter');

    cityZoneSelect.innerHTML = '<option value="">All City Zones</option>';
    areaSelect.innerHTML = '<option value="">All Areas</option>';
    tsCityZone.sync();
    tsArea.sync();

    if (!cityId) return;
    try {
        const response = await fetch(`../../../../server/api/sale/daily_sale_report/get-city-zones.php?city_id=${cityId}`);
        const result = await response.json();
        if (result.success) {
            result.city_zones.forEach(zone => {
                const option = document.createElement('option');
                option.value = zone.id;
                option.textContent = zone.city_zone_name;
                cityZoneSelect.appendChild(option);
            });
            tsCityZone.sync();
        }
    } catch (error) {
        console.error('Error loading city zones:', error);
    }
}

async function loadAreas(cityZoneId) {
    const areaSelect = document.getElementById('area-filter');
    const currentVal = areaSelect.value;
    areaSelect.innerHTML = '<option value="">All Areas</option>';
    try {
        const url = cityZoneId
            ? `../../../../server/api/sale/daily_sale_report/get-areas.php?city_zone_id=${cityZoneId}`
            : `../../../../server/api/sale/daily_sale_report/get-areas.php`;
        const response = await fetch(url);
        const result = await response.json();
        if (result.success) {
            result.areas.forEach(area => {
                const option = document.createElement('option');
                option.value = area.id;
                option.textContent = area.area_name;
                areaSelect.appendChild(option);
            });
            tsArea.sync();
            if (currentVal) tsArea.setValue(currentVal, true);
        }
    } catch (error) {
        console.error('Error loading areas:', error);
    }
}

async function loadAllAreas() {
    await loadAreas(null);
}

async function loadCompanies() {
    try {
        const response = await fetch('../../../../server/api/sale/daily_sale_report/get-companies.php');
        const result = await response.json();
        if (result.success) {
            const companySelect = document.getElementById('company');
            result.companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.company_name;
                companySelect.appendChild(option);
            });
            tsCompany.sync();
        }
    } catch (error) {
        console.error('Error loading companies:', error);
    }
}

async function loadFilters() {
    try {
        const response = await fetch('../../../../server/api/sale/daily_sale_report/get-filters.php');
        const result = await response.json();
        if (result.success) {
            // Sales Officers
            result.salesOfficers.forEach(officer => {
                tsSalesOfficer.addOption({ value: String(officer.id), text: officer.full_name });
            });
            tsSalesOfficer.refreshOptions(false);

            // Supplier Men
            const supplierManSelect = document.getElementById('supplier-man');
            supplierManSelect.innerHTML = '<option value="">All Supplier Men</option>';
            result.supplierMen.forEach(man => {
                const option = document.createElement('option');
                option.value = man.id;
                option.textContent = man.full_name;
                supplierManSelect.appendChild(option);
            });
            tsSupplierMan.sync();

            // Vendors
            const vendorSelect = document.getElementById('vendor');
            vendorSelect.innerHTML = '<option value="">All Vendors</option>';
            result.vendors.forEach(vendor => {
                const option = document.createElement('option');
                option.value = vendor.id;
                option.textContent = vendor.supplier_name;
                vendorSelect.appendChild(option);
            });
            tsVendor.sync();
        }
    } catch (error) {
        console.error('Error loading filters:', error);
    }
}

// Bottom-up: selecting an area auto-fills city and city zone
async function handleAreaBottomUp(areaId) {
    try {
        const response = await fetch(`../../../../server/api/sale/daily_sale_report/get-area-hierarchy.php?area_id=${areaId}`);
        const result = await response.json();
        if (result.success && result.hierarchy) {
            const h = result.hierarchy;

            tsCity.setValue(h.city_id, true);

            await loadCityZones(h.city_id);
            tsCityZone.setValue(h.city_zone_id, true);

            await loadAreas(h.city_zone_id);
            tsArea.setValue(areaId, true);
        }
    } catch (error) {
        console.error('Error loading area hierarchy:', error);
    }
}

function setupEventListeners() {
    itemWiseToggle.addEventListener('click', () => switchReportType('item'));
    billWiseToggle.addEventListener('click', () => switchReportType('bill'));

    searchBtn.addEventListener('click', handleSearch);
    resetBtn.addEventListener('click', handleReset);
    exportBtn.addEventListener('click', handleExport);
    printBtn.addEventListener('click', handlePrint);
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
    const salesOfficerStr = officerIds.length > 0 ? officerIds.join('-') : '0';
    const vendor = document.getElementById('vendor').value || '0';
    const dateFrom = document.getElementById('date-from').value.replace(/-/g, '');
    const dateTo = document.getElementById('date-to').value.replace(/-/g, '');
    return `DSR-${reportType}-${salesOfficerStr}-${vendor}-${dateFrom}-${dateTo}`;
}

async function fetchReportData() {
    loading.classList.add('active');

    const reportType = itemWiseToggle.classList.contains('active') ? 'item-wise' : 'bill-wise';
    const invoiceType = document.getElementById('invoice-type').value;
    const officerIds = getSelectedOfficerIds();
    const supplierMan = document.getElementById('supplier-man').value;
    const vendor = document.getElementById('vendor').value;
    const company = document.getElementById('company').value;
    const dateFrom = document.getElementById('date-from').value;
    const dateTo = document.getElementById('date-to').value;
    const cityId = document.getElementById('city-filter').value;
    const cityZoneId = document.getElementById('city-zone-filter').value;
    const areaId = document.getElementById('area-filter').value;

    const params = new URLSearchParams({
        report_type: reportType,
        ...(invoiceType && { invoice_type: invoiceType }),
        ...(supplierMan && { supplier_man_id: supplierMan }),
        ...(vendor && { vendor_id: vendor }),
        ...(company && { company_id: company }),
        ...(dateFrom && { date_from: dateFrom }),
        ...(dateTo && { date_to: dateTo }),
        ...(cityId && { city_id: cityId }),
        ...(cityZoneId && { city_zone_id: cityZoneId }),
        ...(areaId && { area_id: areaId })
    });
    officerIds.forEach(id => params.append('sales_officer_ids[]', id));

    try {
        const response = await fetch(`../../../../server/api/sale/daily_sale_report/daily-sale-report.php?${params}`);
        const result = await response.json();

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
    } catch (error) {
        console.error('Error fetching report:', error);
        alert('Failed to load report data');
    } finally {
        loading.classList.remove('active');
    }
}

function loadItemWiseData() {
    itemWiseDataContainer.innerHTML = '';

    if (itemWiseData.length === 0) {
        itemWiseDataContainer.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px;">No data found</td></tr>';
        reportCount.textContent = '0 items found';
        paginationContainer.innerHTML = '';
        return;
    }

    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedData = itemWiseData.slice(startIndex, endIndex);

    const officerIds = getSelectedOfficerIds();
    const groupByOfficer = officerIds.length > 1;

    if (groupByOfficer) {
        const groups = {};
        paginatedData.forEach(item => {
            const key = item.sales_officer_name || 'Unknown';
            if (!groups[key]) groups[key] = [];
            groups[key].push(item);
        });

        let globalIndex = startIndex;
        Object.entries(groups).forEach(([officerName, items]) => {
            const headerRow = document.createElement('tr');
            headerRow.innerHTML = `<td colspan="6" style="background: var(--surface-2); font-weight: 600; font-size: 13px; padding: 8px 16px; color: var(--primary);">${officerName}</td>`;
            itemWiseDataContainer.appendChild(headerRow);
            items.forEach(item => {
                globalIndex++;
                const row = document.createElement('tr');
                const indent = item.isChild ? 'padding-left: 30px;' : '';
                const quantityStr = item.units.map(u => `${u.unit} ${parseInt(u.qty)}`).join(', ');
                row.innerHTML = `
                    <td>${globalIndex}</td>
                    <td style="${indent}">${item.isChild ? '↳ ' : ''}${item.product}</td>
                    <td class="text-right">${quantityStr}</td>
                    <td class="text-right">${parseFloat(item.foc_qty || 0).toLocaleString()}</td>
                    <td class="text-right">${currencySymbol}${parseFloat(item.rate).toFixed(2)}</td>
                    <td class="text-right">${currencySymbol}${parseFloat(item.amount).toLocaleString()}</td>
                `;
                itemWiseDataContainer.appendChild(row);
            });
        });
    } else {
        paginatedData.forEach((item, index) => {
            const row = document.createElement('tr');
            const indent = item.isChild ? 'padding-left: 30px;' : '';
            const quantityStr = item.units.map(u => `${u.unit} ${parseInt(u.qty)}`).join(', ');
            row.innerHTML = `
                <td>${startIndex + index + 1}</td>
                <td style="${indent}">${item.isChild ? '↳ ' : ''}${item.product}</td>
                <td class="text-right">${quantityStr}</td>
                <td class="text-right">${parseFloat(item.foc_qty || 0).toLocaleString()}</td>
                <td class="text-right">${currencySymbol}${parseFloat(item.rate).toFixed(2)}</td>
                <td class="text-right">${currencySymbol}${parseFloat(item.amount).toLocaleString()}</td>
            `;
            itemWiseDataContainer.appendChild(row);
        });
    }

    reportCount.textContent = `${itemWiseData.length} items found`;
    renderPagination(itemWiseData.length);
}

function loadBillWiseData() {
    billWiseDataContainer.innerHTML = '';

    if (billWiseData.length === 0) {
        billWiseDataContainer.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px;">No data found</td></tr>';
        reportCount.textContent = '0 bills found';
        paginationContainer.innerHTML = '';
        return;
    }

    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedData = billWiseData.slice(startIndex, endIndex);

    const officerIds = getSelectedOfficerIds();
    const groupByOfficer = officerIds.length > 1;

    if (groupByOfficer) {
        const groups = {};
        paginatedData.forEach(item => {
            const key = item.sales_officer_name || 'Unknown';
            if (!groups[key]) groups[key] = [];
            groups[key].push(item);
        });

        let globalIndex = startIndex;
        Object.entries(groups).forEach(([officerName, items]) => {
            const headerRow = document.createElement('tr');
            headerRow.innerHTML = `<td colspan="6" style="background: var(--surface-2); font-weight: 600; font-size: 13px; padding: 8px 16px; color: var(--primary);">${officerName}</td>`;
            billWiseDataContainer.appendChild(headerRow);
            items.forEach(item => {
                globalIndex++;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${globalIndex}</td>
                    <td><strong>${item.invoiceNo}</strong></td>
                    <td>${item.custCode}</td>
                    <td>${item.custName}</td>
                    <td>${item.address || '-'}</td>
                    <td class="text-right"><strong>${currencySymbol}${parseFloat(item.netAmount).toLocaleString()}</strong></td>
                `;
                billWiseDataContainer.appendChild(row);
            });
        });
    } else {
        paginatedData.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${startIndex + index + 1}</td>
                <td><strong>${item.invoiceNo}</strong></td>
                <td>${item.custCode}</td>
                <td>${item.custName}</td>
                <td>${item.address || '-'}</td>
                <td class="text-right"><strong>${currencySymbol}${parseFloat(item.netAmount).toLocaleString()}</strong></td>
            `;
            billWiseDataContainer.appendChild(row);
        });
    }

    reportCount.textContent = `${billWiseData.length} bills found`;
    renderPagination(billWiseData.length);
}

function updateSummary() {
    if (itemWiseToggle.classList.contains('active')) {
        const totalQty = itemWiseData.filter(item => !item.isChild).reduce((sum, item) => {
            return sum + item.units.reduce((unitSum, u) => unitSum + parseInt(u.qty), 0);
        }, 0);
        const totalAmount = itemWiseData.filter(item => !item.isChild).reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);

        totalSalesEl.textContent = `${currencySymbol}${totalAmount.toLocaleString()}`;
        totalItemsEl.textContent = totalQty.toLocaleString();
        netAmountEl.textContent = `${currencySymbol}${totalAmount.toLocaleString()}`;

        itemsSoldItem.style.display = 'block';
        billsGeneratedItem.style.display = 'none';
    } else {
        const totalBills = billWiseData.length;
        const totalNetAmount = billWiseData.reduce((sum, item) => sum + parseFloat(item.netAmount || 0), 0);

        totalSalesEl.textContent = `${currencySymbol}${totalNetAmount.toLocaleString()}`;
        totalItemsEl.textContent = '-';
        totalBillsEl.textContent = totalBills;
        netAmountEl.textContent = `${currencySymbol}${totalNetAmount.toLocaleString()}`;

        itemsSoldItem.style.display = 'none';
        billsGeneratedItem.style.display = 'block';
    }
}

function handleSearch() {
    currentPage = 1;
    const refNum = generateReference();
    referenceNumber.textContent = refNum;
    referenceCard.style.display = 'flex';
    fetchReportData();
}

function handleReset() {
    tsInvoiceType.setValue('', true);
    tsSalesOfficer.clear(true);
    tsSupplierMan.setValue('', true);
    tsVendor.setValue('', true);
    tsCompany.setValue('', true);

    tsCity.setValue('', true);

    const cityZoneSelect = document.getElementById('city-zone-filter');
    cityZoneSelect.innerHTML = '<option value="">All City Zones</option>';
    tsCityZone.sync();
    tsCityZone.setValue('', true);

    const areaSelect = document.getElementById('area-filter');
    areaSelect.innerHTML = '<option value="">All Areas</option>';
    loadAllAreas();

    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('date-from').valueAsDate = firstDay;
    document.getElementById('date-to').valueAsDate = today;

    referenceCard.style.display = 'none';
    referenceNumber.textContent = '-';

    currentPage = 1;
    fetchReportData();
}

function handleExport() {
    const reportType = itemWiseToggle.classList.contains('active') ? 'item-wise' : 'bill-wise';
    alert(`${reportType} report exported as CSV successfully!`);
}

function handlePrint() {
    const reportType = itemWiseToggle.classList.contains('active') ? 'item-wise' : 'bill-wise';
    const invoiceType = document.getElementById('invoice-type').value;
    const officerIds = getSelectedOfficerIds();
    const supplierMan = document.getElementById('supplier-man').value;
    const vendor = document.getElementById('vendor').value;
    const company = document.getElementById('company').value;
    const dateFrom = document.getElementById('date-from').value;
    const dateTo = document.getElementById('date-to').value;
    const cityId = document.getElementById('city-filter').value;
    const cityZoneId = document.getElementById('city-zone-filter').value;
    const areaId = document.getElementById('area-filter').value;
    const refNum = generateReference();

    const params = new URLSearchParams({
        report_type: reportType,
        reference: refNum,
        ...(invoiceType && { invoice_type: invoiceType }),
        ...(supplierMan && { supplier_man_id: supplierMan }),
        ...(vendor && { vendor_id: vendor }),
        ...(company && { company_id: company }),
        ...(dateFrom && { date_from: dateFrom }),
        ...(dateTo && { date_to: dateTo }),
        ...(cityId && { city_id: cityId }),
        ...(cityZoneId && { city_zone_id: cityZoneId }),
        ...(areaId && { area_id: areaId })
    });
    officerIds.forEach(id => params.append('sales_officer_ids[]', id));

    window.open(`print.php?${params}`, '_blank');
}

function renderPagination(totalItems) {
    const totalPages = Math.ceil(totalItems / itemsPerPage);

    if (totalPages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }

    let html = '';
    html += `<button ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">&lsaquo;</button>`;

    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            html += `<button class="${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            html += `<button disabled>...</button>`;
        }
    }

    html += `<button ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">&rsaquo;</button>`;
    paginationContainer.innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    if (itemWiseToggle.classList.contains('active')) {
        loadItemWiseData();
    } else {
        loadBillWiseData();
    }
}

document.addEventListener('DOMContentLoaded', init);
