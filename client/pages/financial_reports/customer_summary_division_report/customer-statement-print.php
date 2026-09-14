<?php
/**
 * Customer Statement - Print View (UPDATED)
 * Supports:
 * - All Customers (when customer_id is empty)
 * - Single Customer
 * - City / Zone / Area / Salesman filters - ab SAB MULTI-SELECT
 *   (comma-separated ids), jaise main page se aate hain
 * - Date range
 *
 * NEW: Top par selected City/Zone/Area/Salesman ke naam dikhte hain
 * NEW: Bottom totals row ab sab columns ka total dikhata hai
 *      (Opening, Sale Invoice, Receive Voucher, Sale Return, Closing)
 *      - pehle sirf "Final Closing Balance" dikhta tha
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

<!-- NEW: Applied filters (City / Zone / Area / Salesman) - sirf jo select huay hon wo dikhte hain -->
<div class="meta-row" id="p-filters-row" style="display:none;"></div>

<div class="summary-line" id="p-summary" style="display:none;">
    <span>Opening Balance<strong id="p-opening">0.00</strong></span>
    <span>Total Sale Invoice<strong id="p-si">0.00</strong></span>
    <span>Total Receive Voucher<strong id="p-rv">0.00</strong></span>
    <span>Total Sale Return<strong id="p-sr">0.00</strong></span>
    <span>Closing Balance<strong id="p-closing">0.00</strong></span>
</div>

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

function esc(v){
    return String(v ?? '').replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
}

function fmt(n){
    return parseFloat(n || 0).toFixed(2);
}

document.getElementById('p-printed').textContent = new Date().toLocaleString();

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
        const data = await res.json();

        if (!data.success) {
            document.getElementById('p-table-wrapper').innerHTML =
                `<div class="empty-note">${esc(data.message || 'Failed to load statement')}</div>`;
            return;
        }

        const currency = data.currency || '$';

        // Customer name (All Customers when no specific customer)
        document.getElementById('p-customer').textContent =
            data.customer_name || 'All Customers';

        // Period text
        document.getElementById('p-period').textContent =
            (fromDate || toDate)
                ? `${fromDate || 'Start'} to ${toDate || 'Today'}`
                : 'All Transactions';

        const rows = data.rows || [];

        // NEW: totals ab API se directly (rows se manually jama karne ki
        // zaroorat nahi - main page ki tarah)
        const totalSI = data.total_sale_invoice || 0;
        const totalRV = data.total_receive_voucher || 0;
        const totalSR = data.total_sale_return || 0;

        document.getElementById('p-summary').style.display = 'flex';
        document.getElementById('p-opening').textContent  = `${currency}${fmt(data.opening_balance)}`;
        document.getElementById('p-si').textContent       = `${currency}${fmt(totalSI)}`;
        document.getElementById('p-rv').textContent       = `${currency}${fmt(totalRV)}`;
        document.getElementById('p-sr').textContent       = `${currency}${fmt(totalSR)}`;
        document.getElementById('p-closing').textContent  = `${currency}${fmt(data.closing_balance)}`;

        const wrapper = document.getElementById('p-table-wrapper');

        if (rows.length === 0) {
            wrapper.innerHTML = `
                <div class="empty-note">
                    No transactions found for the selected filters.
                </div>`;
            return;
        }

        // Show Customer column only when multiple customers are returned
        const showCustomerColumn = !customerId;

        const headerRow = showCustomerColumn
            ? `<tr>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Opening Balance</th>
                    <th>Sale Invoice</th>
                    <th>Receive Voucher</th>
                    <th>Sale Return</th>
                    <th>Closing Balance</th>
               </tr>`
            : `<tr>
                    <th>Date</th>
                    <th>Opening Balance</th>
                    <th>Sale Invoice</th>
                    <th>Receive Voucher</th>
                    <th>Sale Return</th>
                    <th>Closing Balance</th>
               </tr>`;

        const bodyRows = rows.map(r => {
            if (showCustomerColumn) {
                return `
                    <tr>
                        <td>${esc(r.date)}</td>
                        <td class="customer-cell">${esc(r.customer_name || '-')}</td>
                        <td>${currency}${fmt(r.opening_balance)}</td>
                        <td>${parseFloat(r.sale_invoice) > 0 ? currency + fmt(r.sale_invoice) : '-'}</td>
                        <td>${parseFloat(r.receive_voucher) > 0 ? currency + fmt(r.receive_voucher) : '-'}</td>
                        <td>${parseFloat(r.sale_return) > 0 ? currency + fmt(r.sale_return) : '-'}</td>
                        <td><strong>${currency}${fmt(r.closing_balance)}</strong></td>
                    </tr>`;
            } else {
                return `
                    <tr>
                        <td>${esc(r.date)}</td>
                        <td>${currency}${fmt(r.opening_balance)}</td>
                        <td>${parseFloat(r.sale_invoice) > 0 ? currency + fmt(r.sale_invoice) : '-'}</td>
                        <td>${parseFloat(r.receive_voucher) > 0 ? currency + fmt(r.receive_voucher) : '-'}</td>
                        <td>${parseFloat(r.sale_return) > 0 ? currency + fmt(r.sale_return) : '-'}</td>
                        <td><strong>${currency}${fmt(r.closing_balance)}</strong></td>
                    </tr>`;
            }
        }).join('');

        // NEW: Full totals row - Opening / Sale Invoice / Receive Voucher /
        // Sale Return / Closing - sab columns ka total (sirf closing nahi)
        const totalsRow = showCustomerColumn
            ? `<tr class="total-row">
                    <td colspan="2">TOTALS</td>
                    <td>${currency}${fmt(data.opening_balance)}</td>
                    <td>${currency}${fmt(totalSI)}</td>
                    <td>${currency}${fmt(totalRV)}</td>
                    <td>${currency}${fmt(totalSR)}</td>
                    <td>${currency}${fmt(data.closing_balance)}</td>
               </tr>`
            : `<tr class="total-row">
                    <td>TOTALS</td>
                    <td>${currency}${fmt(data.opening_balance)}</td>
                    <td>${currency}${fmt(totalSI)}</td>
                    <td>${currency}${fmt(totalRV)}</td>
                    <td>${currency}${fmt(totalSR)}</td>
                    <td>${currency}${fmt(data.closing_balance)}</td>
               </tr>`;

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
 | NEW: Applied filter names (City / Zone / Area / Salesman)
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