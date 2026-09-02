<?php
/**
 * Customer Statement Report - Page
 *
 * Default behavior:
 * - Customer blank = ALL customers
 * - Customer selected = selected customer only
 * - Data automatically loads when page opens
 * - Added filters: City, Zone, Area
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

            <!-- CITY -->
            <div class="form-group">
                <label for="city-select">City</label>
                <select class="form-control" id="city-select">
                    <option value="">All Cities</option>
                </select>
            </div>

            <!-- ZONE -->
            <div class="form-group">
                <label for="zone-select">Zone</label>
                <select class="form-control" id="zone-select">
                    <option value="">All Zones</option>
                </select>
            </div>

            <!-- AREA -->
            <div class="form-group">
                <label for="area-select">Area</label>
                <select class="form-control" id="area-select">
                    <option value="">All Areas</option>
                </select>
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
 | Load Cities
 * ----------------------------------------------------------- */
async function loadCities(){
    try{
        const res = await fetch(`${API_BASE}?action=cities`);
        const data = await res.json();

        if(!data.success) return;

        const sel = document.getElementById('city-select');
        sel.innerHTML = '<option value="">All Cities</option>' +
            data.data.map(c =>
                `<option value="${esc(c.id)}">${esc(c.name)}</option>`
            ).join('');
    }catch(e){
        console.error('Error loading cities:', e);
    }
}


/* -----------------------------------------------------------
 | Load Zones (depends on City)
 * ----------------------------------------------------------- */
async function loadZones(cityId = null){
    const sel = document.getElementById('zone-select');
    sel.innerHTML = '<option value="">All Zones</option>';

    // Clear areas when zones change
    document.getElementById('area-select').innerHTML =
        '<option value="">All Areas</option>';

    if(!cityId) return;

    try{
        const res = await fetch(
            `${API_BASE}?action=zones&city_id=${encodeURIComponent(cityId)}`
        );
        const data = await res.json();

        if(!data.success) return;

        sel.innerHTML = '<option value="">All Zones</option>' +
            data.data.map(z =>
                `<option value="${esc(z.id)}">${esc(z.name)}</option>`
            ).join('');
    }catch(e){
        console.error('Error loading zones:', e);
    }
}


/* -----------------------------------------------------------
 | Load Areas (depends on Zone)
 * ----------------------------------------------------------- */
async function loadAreas(zoneId = null){
    const sel = document.getElementById('area-select');
    sel.innerHTML = '<option value="">All Areas</option>';

    if(!zoneId) return;

    try{
        const res = await fetch(
            `${API_BASE}?action=areas&city_zone_id=${encodeURIComponent(zoneId)}`
        );
        const data = await res.json();

        if(!data.success) return;

        sel.innerHTML = '<option value="">All Areas</option>' +
            data.data.map(a =>
                `<option value="${esc(a.id)}">${esc(a.name)}</option>`
            ).join('');
    }catch(e){
        console.error('Error loading areas:', e);
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

    const cityId  = document.getElementById('city-select').value || null;
    const zoneId  = document.getElementById('zone-select').value || null;
    const areaId  = document.getElementById('area-select').value || null;

    // Validate dates
    if(fromDate && toDate && fromDate > toDate){
        alert('From Date cannot be greater than To Date.');
        return;
    }

    let url = `${API_BASE}?action=statement`;

    if(customerId) url += `&customer_id=${encodeURIComponent(customerId)}`;
    if(fromDate)   url += `&from_date=${encodeURIComponent(fromDate)}`;
    if(toDate)     url += `&to_date=${encodeURIComponent(toDate)}`;
    if(cityId)     url += `&city_id=${encodeURIComponent(cityId)}`;
    if(zoneId)     url += `&city_zone_id=${encodeURIComponent(zoneId)}`;
    if(areaId)     url += `&area_id=${encodeURIComponent(areaId)}`;

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

    const totalSI = (data.rows || []).reduce(
        (sum, row) => sum + parseFloat(row.sale_invoice || 0), 0
    );

    const totalRV = (data.rows || []).reduce(
        (sum, row) => sum + parseFloat(row.receive_voucher || 0), 0
    );

    const totalSR = (data.rows || []).reduce(
        (sum, row) => sum + parseFloat(row.sale_return || 0), 0
    );

    document.getElementById('stat-si').textContent =
        `${currentCurrency}${fmt(totalSI)}`;

    document.getElementById('stat-rv').textContent =
        `${currentCurrency}${fmt(totalRV)}`;

    document.getElementById('stat-sr').textContent =
        `${currentCurrency}${fmt(totalSR)}`;
}


/* -----------------------------------------------------------
 | Render Table
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
                        <td colspan="6">Final Closing Balance</td>
                        <td>
                            ${currentCurrency}${fmt(data.closing_balance)}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
}


/* -----------------------------------------------------------
 | Export CSV
 * ----------------------------------------------------------- */
function exportCSV(){

    if(currentRows.length === 0){
        alert('No data to export. Generate a statement first.');
        return;
    }

    const headers = [
        'Date',
        'Customer',
        'Opening Balance',
        'Sale Invoice',
        'Receive Voucher',
        'Sale Return',
        'Closing Balance'
    ];

    const escapeCell = value => {
        let s = value === undefined || value === null ? '' : String(value);
        if(/[",\n]/.test(s)){
            s = '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    };

    const lines = [headers.map(escapeCell).join(',')];

    currentRows.forEach(r => {
        lines.push([
            r.date,
            r.customer_name || '',
            fmt(r.opening_balance),
            fmt(r.sale_invoice),
            fmt(r.receive_voucher),
            fmt(r.sale_return),
            fmt(r.closing_balance)
        ].map(escapeCell).join(','));
    });

    const csv = '\uFEFF' + lines.join('\r\n');

    const blob = new Blob([csv], { type:'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');

    a.href = url;

    const safeName = (currentCustomerName || 'All_Customers')
        .replace(/[^\w\-]+/g, '_');

    a.download =
        `customer_statement_${safeName}_${new Date().toISOString().slice(0,10)}.csv`;

    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}


/* -----------------------------------------------------------
 | Print
 * ----------------------------------------------------------- */
function openPrintView(){

    const customerId = resolveCustomerId();
    const fromDate   = document.getElementById('from-date').value;
    const toDate     = document.getElementById('to-date').value;
    const cityId     = document.getElementById('city-select').value || null;
    const zoneId     = document.getElementById('zone-select').value || null;
    const areaId     = document.getElementById('area-select').value || null;

    let url = `customer-statement-print.php`;
    const params = [];

    if(customerId) params.push(`customer_id=${encodeURIComponent(customerId)}`);
    if(fromDate)   params.push(`from_date=${encodeURIComponent(fromDate)}`);
    if(toDate)     params.push(`to_date=${encodeURIComponent(toDate)}`);
    if(cityId)     params.push(`city_id=${encodeURIComponent(cityId)}`);
    if(zoneId)     params.push(`city_zone_id=${encodeURIComponent(zoneId)}`);
    if(areaId)     params.push(`area_id=${encodeURIComponent(areaId)}`);

    if(params.length){
        url += '?' + params.join('&');
    }

    window.open(url, '_blank');
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

// Cascade: City → Zone → Area
document.getElementById('city-select').addEventListener('change', function(){
    loadZones(this.value || null);
});

document.getElementById('zone-select').addEventListener('change', function(){
    loadAreas(this.value || null);
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
    document.getElementById('city-select').value = '';
    document.getElementById('zone-select').innerHTML =
        '<option value="">All Zones</option>';
    document.getElementById('area-select').innerHTML =
        '<option value="">All Areas</option>';

    currentCustomerId   = null;
    currentCustomerName = 'All Customers';

    await generateStatement();
});


/* -----------------------------------------------------------
 | Initial Load
 * ----------------------------------------------------------- */
(async function init(){
    await loadCities();
    await loadCustomers();   // this already triggers generateStatement
})();

</script>

</body>
</html>