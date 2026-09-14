<?php
/**
 * Customer Statement Report - Page (FIXED v2)
 *
 * Default behavior:
 * - Customer blank = ALL customers
 * - Customer selected = selected customer only
 * - Data automatically loads when page opens
 * - Filters: City, Zone, Area, Salesman - ab sab MULTI-SELECT hain
 *   (checkbox dropdown), e.g. Ali + Usman dono select ho sakte hain
 * - FIX: Date ab display ho raha hai sabhi rows mein
 */

require_once '../../../../includes/dashboard.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Customer Statement - LedgerOne ERP</title>

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root{
        --primary:#2563eb;
        --border:#e2e8f0;
        --text:#1e293b;
        --subtext:#64748b;
        --bg:#f8fafc;
        --header-bg:#f1f5f9;
        --success:#16a34a;
        --danger:#dc2626;
    }

    *{
        box-sizing:border-box;
    }

    body{
        font-family:'Segoe UI',Roboto,Arial,sans-serif;
        background:var(--bg);
        color:var(--text);
        margin:0;
        padding:24px;
    }

    .container{
        max-width:1250px;
        margin:0 auto;
    }

    .card{
        background:#fff;
        border:1px solid var(--border);
        border-radius:10px;
        padding:20px 24px;
        margin-bottom:20px;
        box-shadow:0 1px 3px rgba(0,0,0,0.04);
    }

    h1{
        font-size:28px;
        margin:0 0 4px 0;
    }

    .subtitle{
        color:var(--subtext);
        font-size:13px;
        margin-bottom:18px;
    }

    .form-row{
        display:flex;
        flex-wrap:wrap;
        gap:14px;
        align-items:flex-end;
    }

    .form-group{
        display:flex;
        flex-direction:column;
        min-width:160px;
    }

    .form-group label{
        font-size:12px;
        font-weight:600;
        color:var(--subtext);
        margin-bottom:5px;
    }

    .form-control{
        padding:8px 10px;
        border:1px solid var(--border);
        border-radius:6px;
        font-size:14px;
        min-width:160px;
        background:#fff;
    }

    .form-control:focus{
        outline:none;
        border-color:var(--primary);
        box-shadow:0 0 0 2px rgba(37,99,235,.10);
    }

    .btn{
        padding:9px 16px;
        border-radius:6px;
        border:none;
        font-size:14px;
        cursor:pointer;
        display:inline-flex;
        align-items:center;
        gap:6px;
    }

    .btn-primary{
        background:var(--primary);
        color:#fff;
    }

    .btn-secondary{
        background:#e2e8f0;
        color:var(--text);
    }

    .btn-outline{
        background:#fff;
        border:1px solid var(--border);
        color:var(--text);
    }

    .btn:hover{
        opacity:.9;
    }

    .btn:disabled{
        opacity:.6;
        cursor:not-allowed;
    }

    /* -----------------------------------------------------------
     | Multi-select checkbox dropdown (City / Zone / Area / Salesman)
     * ----------------------------------------------------------- */
    .multiselect{
        position:relative;
        min-width:160px;
    }

    .multiselect-toggle{
        width:100%;
        text-align:left;
        padding:8px 10px;
        border:1px solid var(--border);
        border-radius:6px;
        background:#fff;
        font-size:14px;
        cursor:pointer;
        display:flex;
        justify-content:space-between;
        align-items:center;
        color:var(--text);
    }

    .multiselect-toggle:after{
        content:'\25BE';
        color:var(--subtext);
        margin-left:8px;
    }

    .multiselect-toggle:focus{
        outline:none;
        border-color:var(--primary);
        box-shadow:0 0 0 2px rgba(37,99,235,.10);
    }

    .multiselect-panel{
        display:none;
        position:absolute;
        top:calc(100% + 4px);
        left:0;
        min-width:210px;
        max-height:230px;
        overflow-y:auto;
        background:#fff;
        border:1px solid var(--border);
        border-radius:6px;
        box-shadow:0 4px 14px rgba(0,0,0,.10);
        padding:6px 0;
        z-index:30;
    }

    .multiselect-panel.open{
        display:block;
    }

    .ms-option{
        display:flex;
        align-items:center;
        gap:8px;
        padding:7px 12px;
        font-size:13.5px;
        cursor:pointer;
    }

    .ms-option:hover{
        background:var(--header-bg);
    }

    .ms-option input{
        cursor:pointer;
    }

    .ms-empty{
        padding:10px 12px;
        color:var(--subtext);
        font-size:13px;
    }

    .summary-cards{
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
        gap:14px;
        margin-top:18px;
    }

    .stat{
        border:1px solid var(--border);
        border-radius:8px;
        padding:14px 16px;
        background:var(--header-bg);
    }

    .stat .label{
        font-size:12px;
        color:var(--subtext);
        margin-bottom:4px;
    }

    .stat .value{
        font-size:18px;
        font-weight:700;
    }

    .stat.opening .value{
        color:var(--subtext);
    }

    .stat.closing .value{
        color:var(--primary);
    }

    .table-scroll{
        width:100%;
        overflow-x:auto;
    }

    table.table{
        width:100%;
        border-collapse:collapse;
        margin-top:18px;
        font-size:13.5px;
    }

    table.table th,
    table.table td{
        border:1px solid var(--border);
        padding:9px 10px;
        text-align:right;
        white-space:nowrap;
    }

    table.table th{
        background:var(--header-bg);
        text-align:center;
        font-weight:600;
    }

    table.table td:first-child,
    table.table th:first-child{
        text-align:left;
    }

    table.table td.customer-cell{
        text-align:left;
        font-weight:600;
    }

    table.table tbody tr:hover{
        background:#f8fafc;
    }

    .amt-in{
        color:var(--success);
    }

    .amt-out{
        color:var(--danger);
    }

    .total-row td{
        font-weight:700;
        background:var(--header-bg);
    }

    .empty-note{
        padding:24px;
        text-align:center;
        color:var(--subtext);
    }

    .loading{
        padding:30px;
        text-align:center;
        color:var(--subtext);
    }

    .customer-badge{
        display:inline-block;
        padding:3px 8px;
        border-radius:5px;
        background:#eff6ff;
        color:#1d4ed8;
        font-size:12px;
    }

    @media(max-width:700px){
        body{
            padding:10px;
        }

        .card{
            padding:15px;
        }

        .form-group,
        .form-control{
            width:100%;
            min-width:100%;
        }

        .form-group[style]{
            width:auto;
            min-width:auto;
        }
    }

    @media print{
        body{
            background:#fff;
            padding:0;
        }

        .card{
            border:none;
            box-shadow:none;
        }
    }
</style>
</head>

<body>

<div class="container">

    <div class="card" style="margin-top: 40px; margin-left:20px;">

        <h1>Customer Summary Division Report</h1>

        <div class="form-row">

            <!-- CUSTOMER -->
            <div class="form-group">
                <label for="customer-input">Customer</label>
                <input
                    type="text"
                    class="form-control"
                    id="customer-input"
                    list="customer-list"
                    placeholder="All Customers"
                    autocomplete="off"
                >
                <datalist id="customer-list"></datalist>
            </div>

            <!-- CITY (multi-select) -->
            <div class="form-group">
                <label>City</label>
                <div class="multiselect" id="city-multiselect">
                    <button type="button" class="multiselect-toggle" id="city-toggle">All Cities</button>
                    <div class="multiselect-panel" id="city-panel"></div>
                </div>
            </div>

            <!-- ZONE (multi-select) -->
            <div class="form-group">
                <label>Zone</label>
                <div class="multiselect" id="zone-multiselect">
                    <button type="button" class="multiselect-toggle" id="zone-toggle">All Zones</button>
                    <div class="multiselect-panel" id="zone-panel"></div>
                </div>
            </div>

            <!-- AREA (multi-select) -->
            <div class="form-group">
                <label>Area</label>
                <div class="multiselect" id="area-multiselect">
                    <button type="button" class="multiselect-toggle" id="area-toggle">All Areas</button>
                    <div class="multiselect-panel" id="area-panel"></div>
                </div>
            </div>

            <!-- SALESMAN (multi-select, NEW) -->
            <div class="form-group">
                <label>Salesman</label>
                <div class="multiselect" id="salesman-multiselect">
                    <button type="button" class="multiselect-toggle" id="salesman-toggle">All Salesmen</button>
                    <div class="multiselect-panel" id="salesman-panel"></div>
                </div>
            </div>

            <!-- FROM DATE -->
            <div class="form-group">
                <label for="from-date">From Date</label>
                <input type="date" class="form-control" id="from-date">
            </div>

            <!-- TO DATE -->
            <div class="form-group">
                <label for="to-date">To Date</label>
                <input type="date" class="form-control" id="to-date">
            </div>

            <!-- GENERATE -->
            <div class="form-group" style="min-width:auto;">
                <button class="btn btn-primary" id="generateBtn">
                    <i class="fas fa-filter"></i>
                    Generate
                </button>
            </div>

            <!-- PRINT -->
            <div class="form-group" style="min-width:auto;">
                <button class="btn btn-outline" id="printBtn">
                    <i class="fas fa-print"></i>
                    Print
                </button>
            </div>

            <!-- EXPORT -->
            <div class="form-group" style="min-width:auto;">
                <button class="btn btn-outline" id="exportBtn">
                    <i class="fas fa-file-csv"></i>
                    Export CSV
                </button>
            </div>

            <!-- CLEAR -->
            <div class="form-group" style="min-width:auto;">
                <button class="btn btn-secondary" id="clearBtn">
                    <i class="fas fa-times"></i>
                    Clear
                </button>
            </div>

        </div>

        <!-- SUMMARY -->
        <div class="summary-cards" id="summary-cards" style="display:none;">

            <div class="stat opening">
                <div class="label">Opening Balance</div>
                <div class="value" id="stat-opening">0.00</div>
            </div>

            <div class="stat">
                <div class="label">Total Sale Invoice</div>
                <div class="value" id="stat-si">0.00</div>
            </div>

            <div class="stat">
                <div class="label">Total Receive Voucher</div>
                <div class="value" id="stat-rv">0.00</div>
            </div>

            <div class="stat">
                <div class="label">Total Sale Return</div>
                <div class="value" id="stat-sr">0.00</div>
            </div>

            <div class="stat closing">
                <div class="label">Closing Balance</div>
                <div class="value" id="stat-closing">0.00</div>
            </div>

        </div>

        <!-- TABLE -->
        <div id="table-wrapper">
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i>
                Loading statement...
            </div>
        </div>

    </div>

</div>

<script>

/*
 * Page:
 * client/pages/financial_reports/customer_summary_division_report/
 *
 * API:
 * server/api/financial_reports/customer_summary_division_report/
 */

const API_BASE =
    '../../../../server/api/financial_reports/customer_summary_division_report/customer-statement-api.php';


let currentCurrency = '$';
let currentRows = [];
let currentCustomerName = 'All Customers';
let currentCustomerId = null;

/* Multi-select state - Sets of string ids (City / Zone / Area / Salesman) */
const selectedCityIds     = new Set();
const selectedZoneIds     = new Set();
const selectedAreaIds     = new Set();
const selectedSalesmanIds = new Set();


/* -----------------------------------------------------------
 | Escape HTML
 * ----------------------------------------------------------- */
function esc(v){
    return String(v ?? '').replace(
        /[&<>"']/g,
        c => ({
            '&':'&amp;',
            '<':'&lt;',
            '>':'&gt;',
            '"':'&quot;',
            "'":'&#39;'
        }[c])
    );
}


/* -----------------------------------------------------------
 | Format number
 * ----------------------------------------------------------- */
function fmt(n){
    const number = parseFloat(n);
    if(isNaN(number)){
        return '0.00';
    }
    return number.toFixed(2);
}


/* -----------------------------------------------------------
 | Multi-select helpers (generic - City / Zone / Area / Salesman
 | sab isi function se render hote hain)
 * ----------------------------------------------------------- */
function renderMultiSelectOptions(panelEl, toggleEl, items, selectedSet, allLabel, onChange){
    if(!items || !items.length){
        panelEl.innerHTML = `<div class="ms-empty">No options available.</div>`;
    }else{
        panelEl.innerHTML = items.map(it => `
            <label class="ms-option">
                <input type="checkbox" value="${esc(it.id)}" ${selectedSet.has(String(it.id)) ? 'checked' : ''}>
                <span>${esc(it.name)}</span>
            </label>
        `).join('');
    }

    panelEl.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', function(){
            if(this.checked){
                selectedSet.add(this.value);
            }else{
                selectedSet.delete(this.value);
            }
            updateMultiSelectLabel(toggleEl, selectedSet, allLabel);
            onChange();
        });
    });

    updateMultiSelectLabel(toggleEl, selectedSet, allLabel);
}

function updateMultiSelectLabel(toggleEl, selectedSet, allLabel){
    if(selectedSet.size === 0){
        toggleEl.textContent = allLabel;
    }else if(selectedSet.size === 1){
        toggleEl.textContent = '1 selected';
    }else{
        toggleEl.textContent = selectedSet.size + ' selected';
    }
}

// Open/close dropdown panels on toggle click
document.querySelectorAll('.multiselect-toggle').forEach(btn => {
    btn.addEventListener('click', function(e){
        e.stopPropagation();
        const panel = this.nextElementSibling;
        const isOpen = panel.classList.contains('open');
        document.querySelectorAll('.multiselect-panel.open')
            .forEach(p => p.classList.remove('open'));
        if(!isOpen){
            panel.classList.add('open');
        }
    });
});

// Close any open panel when clicking outside
document.addEventListener('click', function(){
    document.querySelectorAll('.multiselect-panel.open')
        .forEach(p => p.classList.remove('open'));
});


/* -----------------------------------------------------------
 | Load Cities (multi-select)
 * ----------------------------------------------------------- */
async function loadCities(){
    try{
        const res = await fetch(`${API_BASE}?action=cities`);
        const data = await res.json();

        if(!data.success) return;

        renderMultiSelectOptions(
            document.getElementById('city-panel'),
            document.getElementById('city-toggle'),
            data.data,
            selectedCityIds,
            'All Cities',
            onCitySelectionChange
        );
    }catch(e){
        console.error('Error loading cities:', e);
    }
}

async function onCitySelectionChange(){
    // City selection badalne par Zones (aur unke through Areas) refresh hote hain
    await loadZones();
}


/* -----------------------------------------------------------
 | Load Zones (multi-select, cascades from selected Cities)
 | Koi city select na ho to TENANT KE SAB zones dikhte hain.
 * ----------------------------------------------------------- */
async function loadZones(){
    const cityIdsParam = [...selectedCityIds].join(',');

    let url = `${API_BASE}?action=zones`;
    if(cityIdsParam) url += `&city_id=${encodeURIComponent(cityIdsParam)}`;

    try{
        const res = await fetch(url);
        const data = await res.json();

        if(!data.success) return;

        // Jo zones ab valid list mein nahi, unki selection hata do
        const validIds = new Set(data.data.map(z => String(z.id)));
        [...selectedZoneIds].forEach(id => {
            if(!validIds.has(id)) selectedZoneIds.delete(id);
        });

        renderMultiSelectOptions(
            document.getElementById('zone-panel'),
            document.getElementById('zone-toggle'),
            data.data,
            selectedZoneIds,
            'All Zones',
            onZoneSelectionChange
        );

        await loadAreas();
    }catch(e){
        console.error('Error loading zones:', e);
    }
}

async function onZoneSelectionChange(){
    await loadAreas();
}


/* -----------------------------------------------------------
 | Load Areas (multi-select, cascades from selected Zones)
 | Koi zone select na ho to TENANT KE SAB areas dikhte hain.
 * ----------------------------------------------------------- */
async function loadAreas(){
    const zoneIdsParam = [...selectedZoneIds].join(',');

    let url = `${API_BASE}?action=areas`;
    if(zoneIdsParam) url += `&city_zone_id=${encodeURIComponent(zoneIdsParam)}`;

    try{
        const res = await fetch(url);
        const data = await res.json();

        if(!data.success) return;

        const validIds = new Set(data.data.map(a => String(a.id)));
        [...selectedAreaIds].forEach(id => {
            if(!validIds.has(id)) selectedAreaIds.delete(id);
        });

        renderMultiSelectOptions(
            document.getElementById('area-panel'),
            document.getElementById('area-toggle'),
            data.data,
            selectedAreaIds,
            'All Areas',
            () => {}
        );
    }catch(e){
        console.error('Error loading areas:', e);
    }
}


/* -----------------------------------------------------------
 | Load Salesmen (multi-select, NEW)
 * ----------------------------------------------------------- */
async function loadSalesmen(){
    try{
        const res = await fetch(`${API_BASE}?action=salesmen`);
        const data = await res.json();

        if(!data.success) return;

        renderMultiSelectOptions(
            document.getElementById('salesman-panel'),
            document.getElementById('salesman-toggle'),
            data.data,
            selectedSalesmanIds,
            'All Salesmen',
            () => {}
        );
    }catch(e){
        console.error('Error loading salesmen:', e);
    }
}


/* -----------------------------------------------------------
 | Load Customers
 * ----------------------------------------------------------- */
async function loadCustomers(){

    try{
        const res = await fetch(`${API_BASE}?action=customers`);
        const data = await res.json();

        if(!data.success){
            document.getElementById('table-wrapper').innerHTML =
                `<div class="empty-note">
                    ${esc(data.message || 'Could not load customers.')}
                </div>`;
            return;
        }

        const dl = document.getElementById('customer-list');
        dl.innerHTML = data.data.map(c => `
            <option value="${esc(c.customer_name)}" data-id="${esc(c.id)}">
        `).join('');

        // Customer blank by default → ALL customers
        document.getElementById('customer-input').value = '';
        currentCustomerId = null;
        currentCustomerName = 'All Customers';

        // Automatically load ALL customers
        await generateStatement();

    }catch(e){
        console.error('Error loading customers:', e);
        document.getElementById('table-wrapper').innerHTML =
            `<div class="empty-note">
                Could not load customers. Please check API path.
            </div>`;
    }
}


/* -----------------------------------------------------------
 | Resolve Customer ID
 * Blank customer = null = ALL customers
 * ----------------------------------------------------------- */
function resolveCustomerId(){
    const name = document.getElementById('customer-input').value.trim();

    if(!name){
        return null;
    }

    const options = document.querySelectorAll('#customer-list option');

    for(const opt of options){
        if(opt.value === name){
            return opt.getAttribute('data-id');
        }
    }

    return null;
}


/* -----------------------------------------------------------
 | Generate Statement
 * ----------------------------------------------------------- */
async function generateStatement(){

    const customerId = resolveCustomerId();
    currentCustomerId = customerId;

    const fromDate = document.getElementById('from-date').value;
    const toDate   = document.getElementById('to-date').value;

    // Multi-select filters - comma-separated ids
    const cityIdsParam     = [...selectedCityIds].join(',');
    const zoneIdsParam     = [...selectedZoneIds].join(',');
    const areaIdsParam     = [...selectedAreaIds].join(',');
    const salesmanIdsParam = [...selectedSalesmanIds].join(',');

    // Validate dates
    if(fromDate && toDate && fromDate > toDate){
        alert('From Date cannot be greater than To Date.');
        return;
    }

    let url = `${API_BASE}?action=statement`;

    if(customerId)       url += `&customer_id=${encodeURIComponent(customerId)}`;
    if(fromDate)         url += `&from_date=${encodeURIComponent(fromDate)}`;
    if(toDate)           url += `&to_date=${encodeURIComponent(toDate)}`;
    if(cityIdsParam)     url += `&city_id=${encodeURIComponent(cityIdsParam)}`;
    if(zoneIdsParam)     url += `&city_zone_id=${encodeURIComponent(zoneIdsParam)}`;
    if(areaIdsParam)     url += `&area_id=${encodeURIComponent(areaIdsParam)}`;
    if(salesmanIdsParam) url += `&salesman_id=${encodeURIComponent(salesmanIdsParam)}`;

    document.getElementById('table-wrapper').innerHTML =
        `<div class="loading">
            <i class="fas fa-spinner fa-spin"></i>
            Loading statement...
        </div>`;

    try{
        const res  = await fetch(url);
        const data = await res.json();

        if(!data.success){
            document.getElementById('table-wrapper').innerHTML =
                `<div class="empty-note">
                    ${esc(data.message || 'Failed to load statement')}
                </div>`;
            return;
        }

        currentCurrency     = data.currency || '$';
        currentRows         = data.rows || [];
        currentCustomerName = data.customer_name || 'All Customers';

        renderSummary(data);
        renderTable(data);

    }catch(e){
        console.error('Error generating statement:', e);
        document.getElementById('table-wrapper').innerHTML =
            `<div class="empty-note">
                Error generating statement.
            </div>`;
    }
}


/* -----------------------------------------------------------
 | Render Summary
 * ----------------------------------------------------------- */
function renderSummary(data){

    document.getElementById('summary-cards').style.display = 'grid';

    document.getElementById('stat-opening').textContent =
        `${currentCurrency}${fmt(data.opening_balance)}`;

    document.getElementById('stat-closing').textContent =
        `${currentCurrency}${fmt(data.closing_balance)}`;

    // FIX: Ab API se totals directly le rahe hain
    const totalSI = data.total_sale_invoice || 0;
    const totalRV = data.total_receive_voucher || 0;
    const totalSR = data.total_sale_return || 0;

    document.getElementById('stat-si').textContent =
        `${currentCurrency}${fmt(totalSI)}`;

    document.getElementById('stat-rv').textContent =
        `${currentCurrency}${fmt(totalRV)}`;

    document.getElementById('stat-sr').textContent =
        `${currentCurrency}${fmt(totalSR)}`;
}


/* -----------------------------------------------------------
 | Render Table (FIXED - Date ab display ho raha hai!)
 * ----------------------------------------------------------- */
function renderTable(data){

    const wrapper = document.getElementById('table-wrapper');
    const rows    = data.rows || [];

    if(rows.length === 0){
        wrapper.innerHTML =
            `<div class="empty-note">
                No transactions found for the selected period / filters.
            </div>`;
        return;
    }

    const bodyRows = rows.map(r => `
        <tr>
            <td>${esc(r.date)}</td>
            <td class="customer-cell">
                <span class="customer-badge">
                    ${esc(r.customer_name || '-')}
                </span>
            </td>
            <td>${currentCurrency}${fmt(r.opening_balance)}</td>
            <td class="amt-in" title="${esc(r.invoice_nos || '')}">
                ${
                    parseFloat(r.sale_invoice || 0) > 0
                    ? currentCurrency + fmt(r.sale_invoice)
                    : '-'
                }
            </td>
            <td class="amt-out" title="${esc(r.voucher_nos || '')}">
                ${
                    parseFloat(r.receive_voucher || 0) > 0
                    ? currentCurrency + fmt(r.receive_voucher)
                    : '-'
                }
            </td>
            <td class="amt-out" title="${esc(r.return_nos || '')}">
                ${
                    parseFloat(r.sale_return || 0) > 0
                    ? currentCurrency + fmt(r.sale_return)
                    : '-'
                }
            </td>
            <td>
                <strong>
                    ${currentCurrency}${fmt(r.closing_balance)}
                </strong>
            </td>
        </tr>
    `).join('');

    // Totals row
    wrapper.innerHTML = `
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Opening Balance</th>
                        <th>Sale Invoice</th>
                        <th>Receive Voucher</th>
                        <th>Sale Return</th>
                        <th>Closing Balance</th>
                    </tr>
                </thead>
                <tbody>
                    ${bodyRows}
                    <tr class="total-row">
                        <td colspan="2"><strong>TOTALS</strong></td>
                        <td><strong>${currentCurrency}${fmt(data.opening_balance)}</strong></td>
                        <td><strong>${currentCurrency}${fmt(data.total_sale_invoice)}</strong></td>
                        <td><strong>${currentCurrency}${fmt(data.total_receive_voucher)}</strong></td>
                        <td><strong>${currentCurrency}${fmt(data.total_sale_return)}</strong></td>
                        <td><strong>${currentCurrency}${fmt(data.closing_balance)}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
}


/* -----------------------------------------------------------
 | Print
 * ----------------------------------------------------------- */
function openPrintView(){

    const customerId = resolveCustomerId();
    const fromDate   = document.getElementById('from-date').value;
    const toDate     = document.getElementById('to-date').value;

    const cityIdsParam     = [...selectedCityIds].join(',');
    const zoneIdsParam     = [...selectedZoneIds].join(',');
    const areaIdsParam     = [...selectedAreaIds].join(',');
    const salesmanIdsParam = [...selectedSalesmanIds].join(',');

    let url = `customer-statement-print.php`;
    const params = [];

    if(customerId)       params.push(`customer_id=${encodeURIComponent(customerId)}`);
    if(fromDate)         params.push(`from_date=${encodeURIComponent(fromDate)}`);
    if(toDate)           params.push(`to_date=${encodeURIComponent(toDate)}`);
    if(cityIdsParam)     params.push(`city_id=${encodeURIComponent(cityIdsParam)}`);
    if(zoneIdsParam)     params.push(`city_zone_id=${encodeURIComponent(zoneIdsParam)}`);
    if(areaIdsParam)     params.push(`area_id=${encodeURIComponent(areaIdsParam)}`);
    if(salesmanIdsParam) params.push(`salesman_id=${encodeURIComponent(salesmanIdsParam)}`);

    if(params.length){
        url += '?' + params.join('&');
    }

    window.open(url, '_blank');
}


/* -----------------------------------------------------------
 | Export CSV
 * ----------------------------------------------------------- */
function exportCSV(){
    if(currentRows.length === 0){
        alert('No data to export.');
        return;
    }

    let csv = 'Date,Customer,Opening Balance,Sale Invoice,Receive Voucher,Sale Return,Closing Balance\n';

    currentRows.forEach(row => {
        csv += `"${row.date}","${row.customer_name}",${row.opening_balance},${row.sale_invoice},${row.receive_voucher},${row.sale_return},${row.closing_balance}\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `customer-statement-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}


/* -----------------------------------------------------------
 | Event Listeners
 * ----------------------------------------------------------- */

// Customer input change
document.getElementById('customer-input')
    .addEventListener('change', function(){
        const customerId = resolveCustomerId();
        if(this.value.trim() && !customerId){
            this.value = '';
            currentCustomerId = null;
        }
    });

// Buttons
document.getElementById('generateBtn')
    .addEventListener('click', generateStatement);

document.getElementById('printBtn')
    .addEventListener('click', openPrintView);

document.getElementById('exportBtn')
    .addEventListener('click', exportCSV);

document.getElementById('clearBtn').addEventListener('click', async function(){
    document.getElementById('customer-input').value = '';
    document.getElementById('from-date').value = '';
    document.getElementById('to-date').value = '';

    selectedCityIds.clear();
    selectedZoneIds.clear();
    selectedAreaIds.clear();
    selectedSalesmanIds.clear();

    // Panels ko unchecked state mein re-render karna, aur cascade refresh
    await loadCities();
    await loadZones();
    await loadAreas();
    await loadSalesmen();

    currentCustomerId   = null;
    currentCustomerName = 'All Customers';

    await generateStatement();
});


/* -----------------------------------------------------------
 | Initial Load
 * ----------------------------------------------------------- */
(async function init(){
    await loadCities();
    await loadZones();
    await loadAreas();
    await loadSalesmen();
    await loadCustomers();   // this already triggers generateStatement
})();

</script>

</body>
</html>