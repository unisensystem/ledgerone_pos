<?php
/**
 * Customer Statement - Print View (UPDATED v4)
 * Supports:
 * - All Customers (when customer_id is empty)
 * - Single Customer
 * - City / Zone / Area / Salesman filters - SAB MULTI-SELECT
 *   (comma-separated ids), jaise main page se aate hain
 * - Date range
 *
 * Top par selected City/Zone/Area/Salesman ke naam dikhte hain
 * Bottom totals row sab columns ka total dikhata hai
 *
 * Transfer Voucher column (signed: TO = +, FROM = -) + uska total
 * hide_cols param - main page mein jo columns hide hain wo print mein
 *      bhi hide, aur unki calculation closing balance se bhi nikal di jati hai
 * NEW: Adjustment column (signed net: RV adjust_amount = -,
 *      Balance Adjustment JV Debit = +, Credit = -) + uska total
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

$customer_id   = $_GET['customer_id']   ?? '';
$from_date     = $_GET['from_date']     ?? '';
$to_date       = $_GET['to_date']       ?? '';
$city_id       = $_GET['city_id']       ?? '';
$city_zone_id  = $_GET['city_zone_id']  ?? '';
$area_id       = $_GET['area_id']       ?? '';
$salesman_id   = $_GET['salesman_id']   ?? '';
$hide_cols     = $_GET['hide_cols']     ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Statement - Print</title>
<style>
    :root{
        --border:#333;
        --subtext:#555;
        --header-bg:#f1f5f9;
    }
    *{box-sizing:border-box;}
    body{
        font-family:'Segoe UI',Roboto,Arial,sans-serif;
        color:#111;
        margin:0;
        padding:32px 40px;
    }
    .toolbar{
        display:flex;
        justify-content:flex-end;
        gap:10px;
        margin-bottom:20px;
    }
    .btn{
        padding:8px 16px;
        border-radius:6px;
        border:1px solid #ccc;
        background:#fff;
        font-size:13px;
        cursor:pointer;
    }
    .letterhead{
        text-align:center;
        margin-bottom:22px;
        border-bottom:2px solid var(--border);
        padding-bottom:14px;
    }
    .letterhead h1{margin:0 0 4px 0;font-size:22px;letter-spacing:0.5px;}
    .letterhead .company-sub{color:var(--subtext);font-size:12px;}

    .meta-row{
        display:flex;
        justify-content:space-between;
        flex-wrap:wrap;
        gap:12px;
        margin-bottom:18px;
        font-size:13px;
    }
    .meta-row div strong{
        display:block;
        font-size:11px;
        color:var(--subtext);
        font-weight:600;
        text-transform:uppercase;
        letter-spacing:0.4px;
        margin-bottom:2px;
    }

    .summary-line{
        display:flex;
        justify-content:space-between;
        flex-wrap:wrap;
        gap:10px;
        border:1px solid #ccc;
        border-radius:6px;
        padding:10px 16px;
        margin-bottom:20px;
        font-size:13px;
        background:var(--header-bg);
    }
    .summary-line span strong{display:block;font-size:14px;}

    table.table{
        width:100%;
        border-collapse:collapse;
        font-size:12.5px;
    }
    table.table th, table.table td{
        border:1px solid #999;
        padding:7px 8px;
        text-align:right;
    }
    table.table th{
        background:var(--header-bg);
        text-align:center;
    }
    table.table td:first-child,
    table.table th:first-child{text-align:left;}
    table.table td.customer-cell{text-align:left;}
    .total-row td{font-weight:700;background:var(--header-bg);}
    .empty-note{padding:24px;text-align:center;color:var(--subtext);}

    .footer-note{margin-top:24px;font-size:11px;color:var(--subtext);text-align:right;}

    @media print{
        .toolbar{display:none;}
        body{padding:0 10px;}
    }
</style>
</head>
<body>

<div class="toolbar">
    <button class="btn" onclick="window.print()">Print</button>
    <button class="btn" onclick="window.close()">Close</button>
</div>

<div class="letterhead">
    <h1>Customer Summary Division Report</h1>
</div>

<div class="meta-row">
    <div>
        <strong>Customer</strong>
        <span id="p-customer">Loading…</span>
    </div>
    <div>
        <strong>Period</strong>
        <span id="p-period">-</span>
    </div>
    <div>
        <strong>Printed On</strong>
        <span id="p-printed"></span>
    </div>
</div>

<!-- Applied filters (City / Zone / Area / Salesman) - sirf jo select huay hon wo dikhte hain -->
<div class="meta-row" id="p-filters-row" style="display:none;"></div>

<!-- Summary line JS se banti hai (sirf visible columns) -->
<div class="summary-line" id="p-summary" style="display:none;"></div>

<div id="p-table-wrapper">
    <div class="empty-note">Loading statement…</div>
</div>

<div class="footer-note">This is a system-generated statement.</div>

<script>
const API_BASE = '../../../../server/api/financial_reports/customer_summary_division_report/customer-statement-api.php';

const customerId  = <?php echo json_encode($customer_id); ?>;
const fromDate    = <?php echo json_encode($from_date); ?>;
const toDate      = <?php echo json_encode($to_date); ?>;
const cityId      = <?php echo json_encode($city_id); ?>;
const cityZoneId  = <?php echo json_encode($city_zone_id); ?>;
const areaId      = <?php echo json_encode($area_id); ?>;
const salesmanId  = <?php echo json_encode($salesman_id); ?>;
const hideColsRaw = <?php echo json_encode($hide_cols); ?>;

/* Main page ke same columns (same order) */
const COLUMNS = [
    { key:'date',             label:'Date' },
    { key:'customer_name',    label:'Customer' },
    { key:'opening_balance',  label:'Opening Balance' },
    { key:'sale_invoice',     label:'Sale Invoice' },
    { key:'receive_voucher',  label:'Receive Voucher' },
    { key:'sale_return',      label:'Sale Return' },
    { key:'transfer_voucher', label:'Transfer Voucher' },
    { key:'adjustment',       label:'Adjustment' },
    { key:'closing_balance',  label:'Closing Balance' }
];

/* Hidden columns (main page se hide_cols aata hai) - sirf valid keys */
const hiddenCols = new Set(
    String(hideColsRaw || '')
        .split(',')
        .map(k => k.trim())
        .filter(k => COLUMNS.some(c => c.key === k))
);

let currency = '$';

function esc(v){
    return String(v ?? '').replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
}

function fmt(n){
    return parseFloat(n || 0).toFixed(2);
}

/* Signed amount: +$100.00 / -$100.00 / - (zero) */
function fmtSigned(n){
    const number = parseFloat(n) || 0;
    if(number > 0) return '+' + currency + fmt(number);
    if(number < 0) return '-' + currency + fmt(Math.abs(number));
    return '-';
}

document.getElementById('p-printed').textContent = new Date().toLocaleString();


/* -----------------------------------------------------------
 | Build view - hidden columns ko calculation se nikal deta hai
 | (main page ke same logic)
 |
 |   closing = opening + sale_invoice - receive_voucher
 |             - sale_return + transfer_voucher + adjustment
 |
 | Har customer ka running balance alag; opening column hidden
 | ho to opening 0 se start hota hai.
 * ----------------------------------------------------------- */
function buildView(data, hidden){
    const num = v => parseFloat(v) || 0;
    const use = k => !hidden.has(k);

    const running = {};

    const rows = (data.rows || []).map(r => {
        const key = String(r.customer_id ?? r.customer_name ?? '');

        const opening = (key in running)
            ? running[key]
            : (use('opening_balance') ? num(r.opening_balance) : 0);

        const closing = opening
            + (use('sale_invoice')     ? num(r.sale_invoice)     : 0)
            - (use('receive_voucher')  ? num(r.receive_voucher)  : 0)
            - (use('sale_return')      ? num(r.sale_return)      : 0)
            + (use('transfer_voucher') ? num(r.transfer_voucher) : 0)
            + (use('adjustment')       ? num(r.adjustment)       : 0);

        running[key] = closing;

        return Object.assign({}, r, {
            opening_balance: opening,
            closing_balance: closing
        });
    });

    const totalOpening = use('opening_balance') ? num(data.opening_balance) : 0;

    const totalClosing = totalOpening
        + (use('sale_invoice')     ? num(data.total_sale_invoice)     : 0)
        - (use('receive_voucher')  ? num(data.total_receive_voucher)  : 0)
        - (use('sale_return')      ? num(data.total_sale_return)      : 0)
        + (use('transfer_voucher') ? num(data.total_transfer_voucher) : 0)
        + (use('adjustment')       ? num(data.total_adjustment)       : 0);

    return Object.assign({}, data, {
        rows: rows,
        opening_balance: totalOpening,
        closing_balance: totalClosing
    });
}


/* -----------------------------------------------------------
 | Row cell renderer (per column key)
 * ----------------------------------------------------------- */
function cellHtml(key, r){
    switch(key){
        case 'date':
            return `<td>${esc(r.date)}</td>`;
        case 'customer_name':
            return `<td class="customer-cell">${esc(r.customer_name || '-')}</td>`;
        case 'opening_balance':
            return `<td>${currency}${fmt(r.opening_balance)}</td>`;
        case 'sale_invoice':
        case 'receive_voucher':
        case 'sale_return':
            return `<td>${parseFloat(r[key]) > 0 ? currency + fmt(r[key]) : '-'}</td>`;
        case 'transfer_voucher':
            return `<td>${fmtSigned(r.transfer_voucher)}</td>`;
        case 'adjustment':
            return `<td>${fmtSigned(r.adjustment)}</td>`;
        case 'closing_balance':
            return `<td><strong>${currency}${fmt(r.closing_balance)}</strong></td>`;
    }
    return '<td></td>';
}


async function loadStatement(){

    // Build URL - customer_id is OPTIONAL now
    // City/Zone/Area/Salesman - comma-separated multi-select ids
    let url = `${API_BASE}?action=statement`;

    if (customerId)  url += `&customer_id=${encodeURIComponent(customerId)}`;
    if (fromDate)    url += `&from_date=${encodeURIComponent(fromDate)}`;
    if (toDate)      url += `&to_date=${encodeURIComponent(toDate)}`;
    if (cityId)      url += `&city_id=${encodeURIComponent(cityId)}`;
    if (cityZoneId)  url += `&city_zone_id=${encodeURIComponent(cityZoneId)}`;
    if (areaId)      url += `&area_id=${encodeURIComponent(areaId)}`;
    if (salesmanId)  url += `&salesman_id=${encodeURIComponent(salesmanId)}`;

    try {
        const res  = await fetch(url);
        const raw  = await res.json();

        if (!raw.success) {
            document.getElementById('p-table-wrapper').innerHTML =
                `<div class="empty-note">${esc(raw.message || 'Failed to load statement')}</div>`;
            return;
        }

        currency = raw.currency || '$';

        // Hidden columns ke baghair recalculated data
        const data = buildView(raw, hiddenCols);

        // Customer name (All Customers when no specific customer)
        document.getElementById('p-customer').textContent =
            data.customer_name || 'All Customers';

        // Period text
        document.getElementById('p-period').textContent =
            (fromDate || toDate)
                ? `${fromDate || 'Start'} to ${toDate || 'Today'}`
                : 'All Transactions';

        const rows = data.rows || [];

        // Totals API se directly
        const totalSI  = data.total_sale_invoice || 0;
        const totalRV  = data.total_receive_voucher || 0;
        const totalSR  = data.total_sale_return || 0;
        const totalTV  = data.total_transfer_voucher || 0;
        const totalADJ = data.total_adjustment || 0;

        // Summary line - sirf visible columns
        const summaryItems = [
            { key:'opening_balance',  label:'Opening Balance',      value:`${currency}${fmt(data.opening_balance)}` },
            { key:'sale_invoice',     label:'Total Sale Invoice',   value:`${currency}${fmt(totalSI)}` },
            { key:'receive_voucher',  label:'Total Receive Voucher',value:`${currency}${fmt(totalRV)}` },
            { key:'sale_return',      label:'Total Sale Return',    value:`${currency}${fmt(totalSR)}` },
            { key:'transfer_voucher', label:'Net Transfer Voucher', value: totalTV === 0 ? `${currency}0.00` : fmtSigned(totalTV) },
            { key:'adjustment',       label:'Net Adjustment',       value: totalADJ === 0 ? `${currency}0.00` : fmtSigned(totalADJ) },
            { key:'closing_balance',  label:'Closing Balance',      value:`${currency}${fmt(data.closing_balance)}` }
        ].filter(i => !hiddenCols.has(i.key));

        const summaryEl = document.getElementById('p-summary');
        if (summaryItems.length) {
            summaryEl.style.display = 'flex';
            summaryEl.innerHTML = summaryItems
                .map(i => `<span>${esc(i.label)}<strong>${esc(i.value)}</strong></span>`)
                .join('');
        } else {
            summaryEl.style.display = 'none';
        }

        const wrapper = document.getElementById('p-table-wrapper');

        if (rows.length === 0) {
            wrapper.innerHTML = `
                <div class="empty-note">
                    No transactions found for the selected filters.
                </div>`;
            return;
        }

        // Visible columns: hidden nahi + (Customer column sirf tab jab
        // specific customer select na ho)
        const visibleCols = COLUMNS.filter(c => {
            if (hiddenCols.has(c.key)) return false;
            if (c.key === 'customer_name' && customerId) return false;
            return true;
        });

        if (visibleCols.length === 0) {
            wrapper.innerHTML = `
                <div class="empty-note">
                    All columns are hidden. Please show at least one column.
                </div>`;
            return;
        }

        const headerRow = `<tr>${visibleCols.map(c => `<th>${esc(c.label)}</th>`).join('')}</tr>`;

        const bodyRows = rows.map(r =>
            `<tr>${visibleCols.map(c => cellHtml(c.key, r)).join('')}</tr>`
        ).join('');

        // Totals row - "TOTALS" label pehle visible text column mein (Date, warna Customer)
        const labelKey = visibleCols.some(c => c.key === 'date')
            ? 'date'
            : (visibleCols.some(c => c.key === 'customer_name') ? 'customer_name' : null);

        const totalCell = key => {
            switch (key) {
                case 'date':
                case 'customer_name':
                    return `<td>${key === labelKey ? 'TOTALS' : ''}</td>`;
                case 'opening_balance':
                    return `<td>${currency}${fmt(data.opening_balance)}</td>`;
                case 'sale_invoice':
                    return `<td>${currency}${fmt(totalSI)}</td>`;
                case 'receive_voucher':
                    return `<td>${currency}${fmt(totalRV)}</td>`;
                case 'sale_return':
                    return `<td>${currency}${fmt(totalSR)}</td>`;
                case 'transfer_voucher':
                    return `<td>${fmtSigned(totalTV)}</td>`;
                case 'adjustment':
                    return `<td>${fmtSigned(totalADJ)}</td>`;
                case 'closing_balance':
                    return `<td>${currency}${fmt(data.closing_balance)}</td>`;
            }
            return '<td></td>';
        };

        const totalsRow =
            `<tr class="total-row">${visibleCols.map(c => totalCell(c.key)).join('')}</tr>`;

        wrapper.innerHTML = `
            <table class="table">
                <thead>
                    ${headerRow}
                </thead>
                <tbody>
                    ${bodyRows}
                    ${totalsRow}
                </tbody>
            </table>
        `;

    } catch (e) {
        console.error('Error loading statement for print:', e);
        document.getElementById('p-table-wrapper').innerHTML =
            '<div class="empty-note">Error loading statement.</div>';
    }
}


/* -----------------------------------------------------------
 | Applied filter names (City / Zone / Area / Salesman)
 | ids ko naam mein resolve karne ke liye respective list
 | endpoints call karta hai, phir sirf selected filters top
 | par dikhata hai.
 * ----------------------------------------------------------- */
async function fetchNameMap(action){
    try{
        const res  = await fetch(`${API_BASE}?action=${action}`);
        const data = await res.json();
        if(!data.success) return {};

        const map = {};
        data.data.forEach(item => { map[String(item.id)] = item.name; });
        return map;
    }catch(e){
        console.error(`Error loading ${action}:`, e);
        return {};
    }
}

function idsToNames(idsParam, map){
    if(!idsParam) return '';
    return idsParam.split(',')
        .map(id => map[id.trim()] || id.trim())
        .filter(Boolean)
        .join(', ');
}

async function loadFilterLabels(){
    const anyFilter = cityId || cityZoneId || areaId || salesmanId;
    if(!anyFilter) return;

    const [cityMap, zoneMap, areaMap, salesmanMap] = await Promise.all([
        cityId     ? fetchNameMap('cities')   : {},
        cityZoneId ? fetchNameMap('zones')    : {},
        areaId     ? fetchNameMap('areas')    : {},
        salesmanId ? fetchNameMap('salesmen') : {}
    ]);

    const parts = [];
    if (cityId)     parts.push({ label: 'City',     value: idsToNames(cityId, cityMap) });
    if (cityZoneId) parts.push({ label: 'Zone',     value: idsToNames(cityZoneId, zoneMap) });
    if (areaId)     parts.push({ label: 'Area',     value: idsToNames(areaId, areaMap) });
    if (salesmanId) parts.push({ label: 'Salesman', value: idsToNames(salesmanId, salesmanMap) });

    if (!parts.length) return;

    const filtersRow = document.getElementById('p-filters-row');
    filtersRow.style.display = 'flex';
    filtersRow.innerHTML = parts.map(p => `
        <div>
            <strong>${esc(p.label)}</strong>
            <span>${esc(p.value)}</span>
        </div>
    `).join('');
}

loadStatement();
loadFilterLabels();
</script>
</body>
</html>